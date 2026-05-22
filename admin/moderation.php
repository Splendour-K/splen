<?php
require_once "../config/database.php";
require_once "../includes/functions.php";
require_role("admin");

$success = $_GET["success"] ?? "";

// Handle Actions
if (isset($_POST["resolve_dispute"])) {
    $job_id = (int)$_POST["job_id"];
    $outcome = $_POST["outcome"]; // "creator" or "brand"
    $note = $_POST["admin_note"];

    if ($outcome === "creator") {
        // Payout to creator
        $pdo->prepare("UPDATE jobs SET status = 'completed', payment_status = 'ready_payout' WHERE id = ?")->execute([$job_id]);
        
        $stmt_info = $pdo->prepare("SELECT creator_id, brand_id FROM jobs WHERE id = ?");
        $stmt_info->execute([$job_id]);
        $info = $stmt_info->fetch();

        $creator_user = $pdo->prepare("SELECT user_id FROM creators WHERE id = ?");
        $creator_user->execute([$info['creator_id']]);
        $c_uid = $creator_user->fetchColumn();

        $brand_user = $pdo->prepare("SELECT user_id FROM brands WHERE id = ?");
        $brand_user->execute([$info['brand_id']]);
        $b_uid = $brand_user->fetchColumn();

        create_notification($c_uid, "Dispute Won", "Admin has settled the dispute in your favor. Payout is ready.", "payment", "creator/my-jobs.php?job_id=" . $job_id, "dispute", $job_id);
        create_notification($b_uid, "Dispute Resolved", "Admin has settled the dispute. Fund released to creator.", "system", "brand/submissions.php?job_id=" . $job_id, "dispute", $job_id);

        $msg = "Dispute resolved in favor of the creator.";
    } else {
        // Refund/Cancel - simplified for now
        $pdo->prepare("UPDATE jobs SET status = 'cancelled', payment_status = 'disputed' WHERE id = ?")->execute([$job_id]);
        
        $stmt_info = $pdo->prepare("SELECT creator_id, brand_id FROM jobs WHERE id = ?");
        $stmt_info->execute([$job_id]);
        $info = $stmt_info->fetch();

        $creator_user = $pdo->prepare("SELECT user_id FROM creators WHERE id = ?");
        $creator_user->execute([$info['creator_id']]);
        $c_uid = $creator_user->fetchColumn();

        $brand_user = $pdo->prepare("SELECT user_id FROM brands WHERE id = ?");
        $brand_user->execute([$info['brand_id']]);
        $b_uid = $brand_user->fetchColumn();

        create_notification($c_uid, "Dispute Closed", "Admin has settled the dispute. No payout approved.", "system", "creator/my-jobs.php?job_id=" . $job_id, "dispute", $job_id);
        create_notification($b_uid, "Dispute Resolved", "Admin has settled the dispute in your favor.", "system", "brand/submissions.php?job_id=" . $job_id, "dispute", $job_id);

        $msg = "Dispute resolved in favor of the brand.";
    }

    log_activity($_SESSION["user_id"], "Dispute Resolved", "Job ID: $job_id, Outcome: $outcome, Note: $note");
    header("Location: moderation.php?success=" . urlencode($msg));
    exit();
}

// Fetch Disputed Jobs
$stmt = $pdo->query("
    SELECT j.*, c.title as campaign_title, b.brand_name, cr.full_name as creator_name 
    FROM jobs j 
    JOIN campaigns c ON j.campaign_id = c.id 
    JOIN brands b ON j.brand_id = b.id 
    JOIN creators cr ON j.creator_id = cr.id 
    WHERE j.status = 'disputed' OR j.payment_status = 'disputed'
");
$disputed_jobs = $stmt->fetchAll();

// Fetch Submissions Pending Admin Review
$stmt_sub = $pdo->query("
    SELECT s.*, j.campaign_id, c.title as campaign_title, cr.full_name as creator_name
    FROM submissions s
    JOIN jobs j ON s.job_id = j.id
    JOIN campaigns c ON j.campaign_id = c.id
    JOIN creators cr ON j.creator_id = cr.id
    WHERE s.status = 'admin_review'
");
$admin_review_submissions = $stmt_sub->fetchAll();

include "../includes/header.php";
?>

<div class="pt-24 min-h-screen bg-gray-50 dark:bg-gray-950">
    <div class="max-w-7xl mx-auto px-6 md:px-12 flex flex-col md:flex-row gap-8 py-8">
        <?php include "dashboard_sidebar.php"; ?>

        <main class="flex-1 space-y-8">
            <header class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                <h2 class="text-3xl font-black text-gray-900 dark:text-white">Moderation Hub</h2>
                <p class="text-gray-500 font-bold mt-1">Resolve disputes and review flagged content.</p>
            </header>

            <?php if ($success): ?>
                <div class="p-4 bg-green-100 text-green-700 rounded-2xl font-bold text-sm">
                    <?php echo e($success); ?>
                </div>
            <?php endif; ?>

            <!-- Disputed Jobs -->
            <section class="space-y-6">
                <h3 class="text-xl font-black text-gray-900 dark:text-white">Active Disputes</h3>
                <?php if (empty($disputed_jobs)): ?>
                    <div class="p-12 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-dashed border-gray-200 dark:border-gray-800 text-center text-gray-400 font-bold uppercase tracking-widest text-xs">
                        No active disputes found.
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 gap-6">
                        <?php foreach ($disputed_jobs as $job): ?>
                            <div class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                                <div class="flex flex-col md:flex-row justify-between gap-6">
                                    <div class="space-y-2">
                                        <h4 class="text-lg font-black text-gray-900 dark:text-white"><?php echo e($job["campaign_title"]); ?></h4>
                                        <p class="text-xs font-bold text-gray-500">
                                            Brand: <span class="text-primary"><?php echo e($job["brand_name"]); ?></span> | 
                                            Creator: <span class="text-orange-500"><?php echo e($job["creator_name"]); ?></span>
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <span class="px-3 py-1 bg-red-100 text-red-600 text-[10px] font-black uppercase rounded-full tracking-widest">Disputed</span>
                                    </div>
                                </div>

                                <form method="POST" class="mt-8 pt-8 border-t border-gray-100 dark:border-gray-800 space-y-4">
                                    <input type="hidden" name="job_id" value="<?php echo $job["id"]; ?>">
                                    <div>
                                        <label class="block text-[10px] font-black uppercase text-gray-400 mb-2">Internal Admin Note</label>
                                        <textarea name="admin_note" class="w-full p-4 bg-gray-50 dark:bg-gray-800 border border-transparent rounded-2xl focus:border-orange-500 outline-none text-sm font-bold" placeholder="Reasoning for this decision..."></textarea>
                                    </div>
                                    <div class="flex gap-4">
                                        <button type="submit" name="resolve_dispute" value="creator" class="flex-1 py-3 bg-orange-500 text-white font-black rounded-xl text-[10px] uppercase tracking-widest hover:bg-orange-600 transition-all">Settle to Creator</button>
                                        <button type="submit" name="resolve_dispute" value="brand" class="flex-1 py-3 bg-gray-900 dark:bg-white text-white dark:text-gray-900 font-black rounded-xl text-[10px] uppercase tracking-widest hover:bg-gray-700 transition-all">Refund to Brand</button>
                                    </div>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Admin Review Submissions -->
             <section class="space-y-6">
                <h3 class="text-xl font-black text-gray-900 dark:text-white">Flagged Submissions</h3>
                <?php if (empty($admin_review_submissions)): ?>
                    <div class="p-12 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-dashed border-gray-200 dark:border-gray-800 text-center text-gray-400 font-bold uppercase tracking-widest text-xs">
                        No flagged submissions.
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php foreach ($admin_review_submissions as $sub): ?>
                            <div class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm flex flex-col">
                                <div class="mb-4">
                                    <h4 class="text-sm font-black text-gray-900 dark:text-white uppercase tracking-tight"><?php echo e($sub["campaign_title"]); ?></h4>
                                    <p class="text-[10px] font-bold text-gray-400 mb-4"><?php echo e($sub["creator_name"]); ?> submitted a video</p>
                                </div>
                                <div class="bg-gray-100 dark:bg-gray-800 rounded-2xl h-48 flex items-center justify-center mb-6 overflow-hidden">
                                    <?php $preview_src = $sub['watermarked_preview_file'] ?? ($sub['clean_video_file'] ?? ($sub['video_file'] ?? null)); ?>
                                    <?php if ($preview_src): ?>
                                        <video class="w-full h-full object-cover" controls playsinline preload="metadata">
                                            <source src="<?php echo APP_URL . ltrim(e($preview_src), '/'); ?>" type="video/mp4">
                                        </video>
                                    <?php else: ?>
                                        <span class="text-xs font-black text-gray-400 uppercase tracking-widest">No Preview</span>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-auto flex gap-3">
                                    <?php if ($preview_src): ?>
                                        <a href="<?php echo APP_URL . ltrim(e($preview_src), '/'); ?>" target="_blank" class="flex-1 py-3 bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-white text-center font-black rounded-xl text-[10px] uppercase tracking-widest hover:bg-gray-200 transition-all">Watch</a>
                                    <?php else: ?>
                                        <span class="flex-1 py-3 bg-gray-100 dark:bg-gray-800 text-gray-400 text-center font-black rounded-xl text-[10px] uppercase tracking-widest">No File</span>
                                    <?php endif; ?>
                                    <a href="moderation_action.php?type=submission&id=<?php echo $sub["id"]; ?>&action=approve" class="flex-1 py-3 bg-blue-500 text-white text-center font-black rounded-xl text-[10px] uppercase tracking-widest hover:bg-blue-600 transition-all text-white">Approve</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
             </section>
        </main>
    </div>
</div>

<?php include "../includes/footer.php"; ?>