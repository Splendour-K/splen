<?php
require_once "../config/database.php";
require_once "../includes/functions.php";
require_role("admin");

$success = $_GET["success"] ?? "";
$action = $_GET["action"] ?? "";
$proof_id = $_GET["proof_id"] ?? "";

if (isset($_POST['delete_proof_id'])) {
    $delete_proof_id = (int)$_POST['delete_proof_id'];
    $stmt = $pdo->prepare("SELECT analytics_screenshot FROM performance_proofs WHERE id = ?");
    $stmt->execute([$delete_proof_id]);
    $screenshot = $stmt->fetchColumn();
    delete_uploaded_file_path($screenshot);

    $pdo->prepare("DELETE FROM performance_proofs WHERE id = ?")->execute([$delete_proof_id]);
    header("Location: performance-reviews.php?success=Deleted");
    exit();
}

if ($proof_id && $action === "approve") {
    $pdo->beginTransaction();
    try {
        $stmt_p = $pdo->prepare("SELECT * FROM performance_proofs WHERE id = ?");
        $stmt_p->execute([$proof_id]);
        $proof = $stmt_p->fetch();

        // Update Proof Status
        $upd_proof = $pdo->prepare("UPDATE performance_proofs SET status = \"approved\" WHERE id = ?");
        $upd_proof->execute([$proof_id]);

        // Calculate Commission & Net
        $commission_rate = (float)get_setting("platform_commission", 10);
        $gross_amount = (float)$proof["calculated_payment"];
        $commission_amount = $gross_amount * ($commission_rate / 100);
        $net_amount = $gross_amount - $commission_amount;

        // Update Payment
        $upd_pay = $pdo->prepare("UPDATE payments SET status = \"completed\", actual_views = ?, approved_views = ?, calculated_amount = ?, commission_rate = ?, commission_amount = ?, net_amount = ? WHERE job_id = ?");
        $upd_pay->execute([$proof["view_count"], $proof["view_count"], $gross_amount, $commission_rate, $commission_amount, $net_amount, $proof["job_id"]]);

        $pdo->commit();
        header("Location: performance-reviews.php?success=Verified");
        exit();
    } catch (Exception $e) { $pdo->rollBack(); echo $e->getMessage(); }
}

if ($proof_id && $action === "reject") {
    $upd_proof = $pdo->prepare("UPDATE performance_proofs SET status = \"rejected\" WHERE id = ?");
    if ($upd_proof->execute([$proof_id])) {
        header("Location: performance-reviews.php?success=Rejected");
        exit();
    }
}

// Fetch Pending Proofs
$stmt_proofs = $pdo->query("SELECT p.*, c.title as campaign_title, cr.full_name as creator_name, b.brand_name 
                            FROM performance_proofs p 
                            JOIN campaigns c ON p.campaign_id = c.id 
                            JOIN creators cr ON p.creator_id = cr.id 
                            JOIN brands b ON c.brand_id = b.id 
                            WHERE p.status = \"pending\" 
                            ORDER BY p.created_at DESC");
$proofs = $stmt_proofs->fetchAll();

include "../includes/header.php";
?>

<div class="pt-24 min-h-screen bg-gray-50 dark:bg-gray-950">
    <div class="max-w-7xl mx-auto px-6 md:px-12 flex flex-col md:flex-row gap-8 py-8">
        <?php include "dashboard_sidebar.php"; ?>

        <main class="flex-1 space-y-8">
            <header class="p-8 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm flex justify-between items-center">
                <div>
                    <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Performance Verification</h2>
                    <p class="text-gray-600 dark:text-gray-400 mt-2">Review analytics screenshots and approve view-based payouts.</p>
                </div>
            </header>

            <?php if (!empty($_GET['success']) && $_GET['success'] === 'Deleted'): ?>
                <div class="p-4 bg-green-100 dark:bg-green-900/30 border border-green-200 dark:border-green-800 rounded-xl text-green-800 dark:text-green-400 font-bold mb-6">
                    ✅ Proof deleted successfully
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="p-4 bg-green-100 dark:bg-green-900/30 border border-green-200 dark:border-green-800 rounded-xl text-green-800 dark:text-green-400 font-bold mb-6">
                    ✅ Proof Verified Successfully
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 gap-6">
                <?php if (empty($proofs)): ?>
                    <div class="p-20 text-center bg-white dark:bg-gray-900 rounded-[2.5rem] border border-dashed border-gray-200 dark:border-gray-800">
                         <div class="text-5xl mb-6">🏜️</div>
                         <h3 class="text-xl font-bold text-gray-900 dark:text-white">No pending proofs</h3>
                         <p class="text-gray-400">Everything is up to date.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($proofs as $p): ?>
                        <div class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm flex flex-col md:flex-row gap-8">
                            <div class="w-full md:w-64">
                                <p class="text-[10px] font-black uppercase text-gray-400 tracking-widest mb-4">Analytics Proof</p>
                                <a href="<?php echo APP_URL . $p["analytics_screenshot"]; ?>" target="_blank" class="block rounded-2xl overflow-hidden border border-gray-100 dark:border-gray-700 hover:scale-95 transition">
                                    <img src="<?php echo APP_URL . $p["analytics_screenshot"]; ?>" alt="Proof" class="w-full h-auto">
                                </a>
                            </div>

                            <div class="flex-1 space-y-6">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h3 class="text-xl font-black text-gray-900 dark:text-white"><?php echo e($p["campaign_title"]); ?></h3>
                                        <p class="text-sm text-gray-500 font-bold">Creator: <span class="text-primary"><?php echo e($p["creator_name"]); ?></span></p>
                                        <p class="text-xs text-gray-400 mt-1">Brand: <?php echo e($p["brand_name"]); ?></p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-2xl font-black text-gray-900 dark:text-white"><?php echo number_format($p["view_count"]); ?></p>
                                        <p class="text-[10px] text-orange-500 font-black uppercase tracking-widest">Reported Views</p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-2xl border border-gray-100 dark:border-gray-700">
                                         <p class="text-[10px] text-gray-400 font-bold uppercase mb-1">Platform</p>
                                         <p class="text-sm font-bold text-gray-900 dark:text-white"><?php echo e($p["platform"]); ?></p>
                                    </div>
                                    <div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-2xl border border-gray-100 dark:border-gray-700">
                                         <p class="text-[10px] text-gray-400 font-bold uppercase mb-1">Live Link</p>
                                         <a href="<?php echo e($p["posted_video_link"]); ?>" target="_blank" class="text-xs font-bold text-secondary truncate block"><?php echo e($p["posted_video_link"]); ?></a>
                                    </div>
                                </div>

                                <div class="p-4 bg-secondary/5 rounded-2xl border border-secondary/20 flex justify-between items-center">
                                    <p class="text-xs font-bold text-secondary uppercase tracking-widest">Calculated Payout</p>
                                    <p class="text-xl font-black text-secondary"><?php echo number_format($p["calculated_payment"], 2); ?></p>
                                </div>

                                <div class="flex gap-4 pt-4">
                                    <a href="?action=approve&proof_id=<?php echo $p["id"]; ?>" onclick="return confirm(\"Verify this proof? This will finalize the creator payment.\")" class="flex-1 py-4 bg-gray-900 dark:bg-white text-white dark:text-gray-900 font-bold rounded-2xl text-center hover:bg-green-500 hover:text-white transition-all shadow-lg">Verify & Approve Payment</a>
                                    <a href="?action=reject&proof_id=<?php echo $p["id"]; ?>" onclick="return confirm(\"Reject this proof? Creator will need to submit again.\")" class="px-8 py-4 border border-red-200 text-red-500 font-bold rounded-2xl hover:bg-red-50 transition text-center flex items-center justify-center">Reject</a>
                                </div>
                                <form method="POST" onsubmit="return confirm('Delete this proof and its screenshot?');">
                                    <input type="hidden" name="delete_proof_id" value="<?php echo $p['id']; ?>">
                                    <button type="submit" class="mt-2 text-[10px] font-black uppercase tracking-widest text-gray-500 hover:text-red-600">Delete Proof</button>
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
