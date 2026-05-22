<?php
require_once "../config/database.php";
require_once "../includes/functions.php";
require_role("brand");

$stmt = $pdo->prepare("SELECT * FROM brands WHERE user_id = ?");
$stmt->execute([$_SESSION["user_id"]]);
$brand = $stmt->fetch();

$error = "";
$success = $_GET["success"] ?? "";

// Handle Actions
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"];
    $sub_id = $_POST["sub_id"];

    if ($action === "approve") {
        $pdo->beginTransaction();
        try {
            $stmt_sub = $pdo->prepare("SELECT s.*, j.id as job_id FROM submissions s JOIN jobs j ON s.job_id = j.id WHERE s.id = ?");
            $stmt_sub->execute([$sub_id]);
            $sub = $stmt_sub->fetch();

            $upd_sub = $pdo->prepare("UPDATE submissions SET status = \"approved\", approved_at = NOW() WHERE id = ?");
            $upd_sub->execute([$sub_id]);

            $upd_job = $pdo->prepare("UPDATE jobs SET status = \"completed\" WHERE id = ?");
            $upd_job->execute([$sub["job_id"]]);

            // For Performance campaigns, we don\"t mark payment finished yet, we wait for views.
            // For Direct UGC, we can mark it as ready for payout.
            $stmt_camp = $pdo->prepare("SELECT order_type FROM campaigns WHERE id = ?");
            $stmt_camp->execute([$sub["campaign_id"]]);
            $order_type = $stmt_camp->fetchColumn();

            if ($order_type === "direct_ugc") {
                // Fetch basic amount to calculate commission
                $stmt_p = $pdo->prepare("SELECT amount FROM payments WHERE job_id = ?");
                $stmt_p->execute([$sub["job_id"]]);
                $gross_amount = (float)$stmt_p->fetchColumn();

                $commission_rate = (float)get_setting("platform_commission", 10);
                $commission_amount = $gross_amount * ($commission_rate / 100);
                $net_amount = $gross_amount - $commission_amount;

                $upd_pay = $pdo->prepare("UPDATE payments SET status = \"completed\", calculated_amount = amount, commission_rate = ?, commission_amount = ?, net_amount = ? WHERE job_id = ?");
                $upd_pay->execute([$commission_rate, $commission_amount, $net_amount, $sub["job_id"]]);
            }

            $pdo->commit();
            header("Location: submissions.php?success=Approved!");
            exit();
        } catch (Exception $e) { $pdo->rollBack(); $error = $e->getMessage(); }
    }

    if ($action === "revision") {
        $note = $_POST["revision_note"];
        $upd_sub = $pdo->prepare("UPDATE submissions SET status = \"revision_requested\", submission_note = ? WHERE id = ?");
        $upd_sub->execute([$note, $sub_id]);
        header("Location: submissions.php?success=Revision%20Requested");
        exit();
    }

    if ($action === "dispute") {
        $note = $_POST["dispute_note"];
        $stmt_sub = $pdo->prepare("UPDATE jobs j JOIN submissions s ON s.job_id = j.id SET j.status = 'disputed', j.payment_status = 'disputed' WHERE s.id = ?");
        $stmt_sub->execute([$sub_id]);
        
        log_activity($_SESSION["user_id"], "Dispute Raised", "Brand raised dispute for submission $sub_id. Note: $note");
        header("Location: submissions.php?success=Dispute%20Sent%20to%20Admin");
        exit();
    }
}

// Fetch all submissions for this brand
$sql = "SELECT s.*, c.title, c.order_type, cr.full_name as creator_name, c.currency, c.budget_per_creator, conv.id as chat_id
        FROM submissions s 
        JOIN campaigns c ON s.campaign_id = c.id 
        JOIN creators cr ON s.creator_id = cr.id 
        LEFT JOIN conversations conv ON (conv.brand_id = c.brand_id AND conv.creator_id = s.creator_id)
        WHERE c.brand_id = ? 
        ORDER BY s.submitted_at DESC";
$stmt_list = $pdo->prepare($sql);
$stmt_list->execute([$brand["id"]]);
$submissions = $stmt_list->fetchAll();

include "../includes/header.php";
?>

<div class="pt-24 min-h-screen bg-gray-50 dark:bg-gray-950">
    <div class="max-w-7xl mx-auto px-6 md:px-12 flex flex-col md:flex-row gap-8 py-8">
        <?php include "../includes/brand_sidebar.php"; ?>

        <main class="flex-1 space-y-8">
            <header class="p-8 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Review Submissions</h2>
                <p class="text-gray-600 dark:text-gray-400 mt-2">Watch watermarked previews and approve content for release.</p>
            </header>

            <?php if ($success): ?>
                <div class="p-4 bg-green-100 dark:bg-green-900/30 border border-green-200 dark:border-green-800 rounded-xl text-green-800 dark:text-green-400 font-bold">
                    ✅ <?php echo e($success); ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 gap-6">
                <?php if (empty($submissions)): ?>
                    <div class="p-12 text-center bg-white dark:bg-gray-900 rounded-3xl border border-dashed border-gray-200 dark:border-gray-800">
                        <p class="text-gray-400">No submissions to review yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($submissions as $sub): ?>
                        <div class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm flex flex-col md:flex-row gap-8">
                            <div class="w-full md:w-64 aspect-[9/16] bg-black rounded-3xl overflow-hidden relative group">
                                <?php
                                    $play_src = ($sub["status"] === "approved" ? $sub["clean_video_file"] : $sub["watermarked_preview_file"])
                                        ?: $sub["clean_video_file"] ?: $sub["video_file"];
                                ?>
                                <?php if ($play_src): ?>
                                    <video class="w-full h-full object-cover" controls controlsList="nodownload" preload="metadata" playsinline>
                                        <source src="<?php echo APP_URL . ltrim(e($play_src), '/'); ?>" type="video/mp4">
                                        Your browser does not support the video tag.
                                    </video>
                                    <?php if ($sub["status"] !== "approved"): ?>
                                        <div class="absolute top-2 right-2 px-2 py-1 bg-black/70 text-white text-[9px] font-black uppercase tracking-widest rounded-md pointer-events-none">Watermarked</div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="flex items-center justify-center h-full text-gray-400 p-4 text-center text-xs">
                                        No video uploaded
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="flex-1 space-y-4">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <span class="px-3 py-1 bg-secondary/10 text-secondary text-[10px] font-black rounded-full uppercase tracking-widest"><?php echo str_replace("_", " ", $sub["order_type"]); ?></span>
                                        <h3 class="text-xl font-bold text-gray-900 dark:text-white mt-2"><?php echo e($sub["title"]); ?></h3>
                                        <p class="text-sm text-gray-500">By Creator: <span class="font-bold text-gray-900 dark:text-white"><?php echo e($sub["creator_name"]); ?></span></p>
                                        <?php if ($sub['chat_id']): ?>
                                            <a href="<?php echo APP_URL; ?>view-message.php?id=<?php echo $sub['chat_id']; ?>" class="inline-flex items-center text-xs font-bold text-secondary hover:underline mt-2">
                                                <span class="mr-2">💬</span> Message Creator
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">Status</p>
                                        <span class="text-sm font-black <?php echo $sub["status"] === "approved" ? "text-green-500" : "text-orange-500"; ?> uppercase"><?php echo $sub["status"]; ?></span>
                                    </div>
                                </div>

                                <div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-2xl border border-gray-100 dark:border-gray-700">
                                    <p class="text-[10px] text-gray-400 font-bold uppercase mb-2">Creator Note</p>
                                    <p class="text-sm text-gray-700 dark:text-gray-300"><?php echo nl2br(e($sub["submission_note"])); ?></p>
                                </div>

                                <div class="flex flex-wrap gap-3 pt-4">
                                    <?php if ($sub["status"] === "submitted"): ?>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="sub_id" value="<?php echo $sub["id"]; ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" onclick="return confirm(\"Approve this video? Payout will be processed.\")" class="px-6 py-3 bg-secondary text-white font-bold rounded-xl hover:scale-105 transition shadow-lg shadow-secondary/20">Approve & Release Clean File</button>
                                        </form>
                                        
                                        <button onclick="document.getElementById(\"rev-modal-<?php echo $sub["id"]; ?>\").classList.remove(\"hidden\")" class="px-6 py-3 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 font-bold rounded-xl hover:bg-gray-50 transition">Request Revision</button>
                                        <button onclick="document.getElementById(\"dis-modal-<?php echo $sub["id"]; ?>\").classList.remove(\"hidden\")" class="px-6 py-3 bg-red-50 text-red-600 font-bold rounded-xl hover:bg-red-100 transition">Dispute</button>
                                    <?php elseif ($sub["status"] === "approved"): ?>
                                        <a href="<?php echo APP_URL . $sub["clean_video_file"]; ?>" download class="px-6 py-3 bg-green-500 text-white font-bold rounded-xl hover:scale-105 transition flex items-center gap-2">
                                            <span>📥</span> Download Clean File
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Revision Modal -->
                        <div id="rev-modal-<?php echo $sub["id"]; ?>" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-6 hidden">
                            <div class="bg-white dark:bg-gray-900 p-8 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 w-full max-w-lg shadow-2xl">
                                <h3 class="text-2xl font-black text-gray-900 dark:text-white mb-4">Request Revision</h3>
                                <form method="POST">
                                    <input type="hidden" name="sub_id" value="<?php echo $sub["id"]; ?>">
                                    <input type="hidden" name="action" value="revision">
                                    <textarea name="revision_note" rows="5" required class="w-full px-5 py-4 bg-gray-50 dark:bg-gray-800 border border-transparent focus:border-secondary rounded-2xl outline-none mb-6 dark:text-white" placeholder="What should the creator change? Be specific..."></textarea>
                                    <div class="flex gap-4">
                                        <button type="submit" class="flex-1 py-4 bg-secondary text-white font-bold rounded-2xl shadow-xl shadow-secondary/20">Send Request</button>
                                        <button type="button" onclick="this.closest(\".fixed\").classList.add(\"hidden\")" class="flex-1 py-4 border border-gray-200 dark:border-gray-700 text-gray-500 font-bold rounded-2xl">Cancel</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Dispute Modal -->
                        <div id="dis-modal-<?php echo $sub["id"]; ?>" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-6 hidden">
                            <div class="bg-white dark:bg-gray-900 p-8 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 w-full max-w-lg shadow-2xl">
                                <h3 class="text-2xl font-black text-gray-900 dark:text-white mb-4">Raise Official Dispute</h3>
                                <p class="text-xs font-bold text-gray-500 mb-6 uppercase tracking-widest">A Splennet moderator will review this job.</p>
                                <form method="POST">
                                    <input type="hidden" name="sub_id" value="<?php echo $sub["id"]; ?>">
                                    <input type="hidden" name="action" value="dispute">
                                    <textarea name="dispute_note" rows="5" required class="w-full px-5 py-4 bg-red-50 dark:bg-gray-800 border border-transparent focus:border-red-500 rounded-2xl outline-none mb-6 dark:text-white" placeholder="Why are you disputing this? Please provide clear evidence or reasons."></textarea>
                                    <div class="flex gap-4">
                                        <button type="submit" class="flex-1 py-4 bg-red-600 text-white font-bold rounded-2xl shadow-xl shadow-red-500/20">Raise Dispute</button>
                                        <button type="button" onclick="this.closest(\".fixed\").classList.add(\"hidden\")" class="flex-1 py-4 border border-gray-200 dark:border-gray-700 text-gray-500 font-bold rounded-2xl">Cancel</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<?php include "../includes/footer.php"; ?>
