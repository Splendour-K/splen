<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_role('admin');

// Actions
if (isset($_GET['action']) && isset($_GET['creator_id'])) {
    $action = $_GET['action'];
    $creator_id = $_GET['creator_id'];

    if ($action === 'verify') {
        $pdo->beginTransaction();
        try {
            $upd1 = $pdo->prepare("UPDATE creators SET verification_status = 'verified' WHERE id = ?");
            $upd1->execute([$creator_id]);

            $upd2 = $pdo->prepare("UPDATE creator_verifications SET status = 'verified' WHERE creator_id = ?");
            $upd2->execute([$creator_id]);

            // Notify Creator
            $stmt_u = $pdo->prepare("SELECT user_id FROM creators WHERE id = ?");
            $stmt_u->execute([$creator_id]);
            $uid = $stmt_u->fetchColumn();

            $notify = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, 'Identity Verified!', 'Your student status has been verified. You can now apply for campaigns!', 'system')");
            $notify->execute([$uid]);

            $pdo->commit();
            redirect('admin/dashboard.php?success=1');
            exit();
        } catch (Exception $e) {
            $pdo->rollBack();
        }
    }

    if ($action === 'reject') {
        $pdo->beginTransaction();
        try {
            $upd1 = $pdo->prepare("UPDATE creators SET verification_status = 'rejected' WHERE id = ?");
            $upd1->execute([$creator_id]);

            $upd2 = $pdo->prepare("UPDATE creator_verifications SET status = 'rejected' WHERE creator_id = ?");
            $upd2->execute([$creator_id]);

            $pdo->commit();
            redirect('admin/dashboard.php?rejected=1');
            exit();
        } catch (Exception $e) {
            $pdo->rollBack();
        }
    }
}

// Stats
$total_brands = $pdo->query("SELECT COUNT(*) FROM brands")->fetchColumn();
$total_creators = $pdo->query("SELECT COUNT(*) FROM creators")->fetchColumn();
$active_campaigns = $pdo->query("SELECT COUNT(*) FROM campaigns WHERE status = 'published'")->fetchColumn();

// Financial Stats
$payments_has_calculated_amount = table_has_column('payments', 'calculated_amount');
$payments_has_commission_amount = table_has_column('payments', 'commission_amount');
$contests_has_cpm_calculated_at = table_has_column('contests', 'cpm_calculated_at');

$total_revenue_sql = $payments_has_calculated_amount
    ? "SELECT COALESCE(SUM(calculated_amount), 0) FROM payments WHERE status = 'completed'"
    : "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'completed'";
$total_revenue = $pdo->query($total_revenue_sql)->fetchColumn() ?: 0;
$admin_commission = $payments_has_commission_amount
    ? ($pdo->query("SELECT COALESCE(SUM(commission_amount), 0) FROM payments WHERE status = 'completed'")->fetchColumn() ?: 0)
    : 0;

// Pending Contest Submissions
$pending_contests = $pdo->query("SELECT COUNT(*) FROM contest_submissions WHERE status = 'submitted'")->fetchColumn();

// Pending UGC Submissions
$pending_ugc = $pdo->query("SELECT COUNT(*) FROM ugc_order_submissions WHERE status = 'submitted'")->fetchColumn();

// CPM Payouts Pending
$pending_cpm = $contests_has_cpm_calculated_at
    ? ($pdo->query("SELECT COUNT(*) FROM contests WHERE cpm_calculated_at IS NULL AND status = 'live'")->fetchColumn() ?: 0)
    : ($pdo->query("SELECT COUNT(*) FROM contests WHERE status = 'live'")->fetchColumn() ?: 0);

// Pending Payments
$pending_payments = $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetchColumn();

// Pending Verifications
$stmt = $pdo->query("
    SELECT c.id, c.full_name, c.verification_status,
           cv.school_email, cv.id_upload, cv.letter_upload, cv.status as verif_status, cv.created_at as submitted_at
    FROM creators c
    JOIN creator_verifications cv ON c.id = cv.creator_id
    WHERE cv.status = 'pending'
    ORDER BY cv.created_at ASC
    LIMIT 10
");
$pending_verifs = $stmt->fetchAll();
$pending_verif_count = count($pending_verifs);

// Recent Activity Logs
$stmt_logs = $pdo->query("SELECT l.*, u.email FROM activity_logs l LEFT JOIN users u ON l.user_id = u.id ORDER BY l.created_at DESC LIMIT 5");
$logs = $stmt_logs->fetchAll();

include '../includes/header.php';
?>

<div class="pt-32 md:pt-24 min-h-screen bg-gray-50 dark:bg-gray-950">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-12 flex flex-col md:flex-row gap-6 md:gap-8 py-4 md:py-8 w-full">
        <!-- Sidebar -->
        <?php include "dashboard_sidebar.php"; ?>

        <!-- Main Content -->
        <main class="flex-1 space-y-6 md:space-y-8 min-w-0">
            <header class="p-6 md:p-8 bg-white dark:bg-gray-900 rounded-[1.5rem] md:rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm relative overflow-hidden">
                <div aria-hidden="true" class="absolute top-0 right-0 -mr-16 -mt-16 w-64 h-64 bg-orange-500/5 rounded-full blur-3xl"></div>
                <div class="relative">
                    <h2 class="text-2xl md:text-3xl font-black text-gray-900 dark:text-white">Live Overview</h2>
                    <p class="text-sm md:text-base text-gray-500 font-bold mt-1">Real-time platform performance and management.</p>
                </div>
            </header>

            <!-- Stats Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-3 md:gap-6">
                <div class="p-4 md:p-8 bg-white dark:bg-gray-900 rounded-2xl md:rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">Total Volume</p>
                    <h3 class="text-xl md:text-3xl font-black text-gray-900 dark:text-white break-all">$<?php echo number_format($total_revenue); ?></h3>
                </div>
                <div class="p-4 md:p-8 bg-gray-900 rounded-2xl md:rounded-[2.5rem] text-white shadow-xl">
                    <p class="text-gray-400 text-[10px] font-black uppercase tracking-widest mb-2 text-orange-400">Net Commission</p>
                    <h3 class="text-xl md:text-3xl font-black break-all">$<?php echo number_format($admin_commission); ?></h3>
                </div>
                <div class="p-4 md:p-8 bg-white dark:bg-gray-900 rounded-2xl md:rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">Active Creators</p>
                    <h3 class="text-xl md:text-3xl font-black text-gray-900 dark:text-white"><?php echo $total_creators; ?></h3>
                </div>
                <div class="p-4 md:p-8 bg-white dark:bg-gray-900 rounded-2xl md:rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">Active Briefs</p>
                    <h3 class="text-xl md:text-3xl font-black text-gray-900 dark:text-white"><?php echo $active_campaigns; ?></h3>
                </div>
                <div class="p-4 md:p-8 bg-white dark:bg-gray-900 rounded-2xl md:rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm <?php echo $pending_contests > 0 ? 'border-b-4 border-b-orange-500' : ''; ?>">
                    <p class="text-[10px] font-black uppercase tracking-widest <?php echo $pending_contests > 0 ? 'text-orange-500' : 'text-gray-400'; ?> mb-2">Pending Contests</p>
                    <h3 class="text-xl md:text-3xl font-black text-gray-900 dark:text-white"><?php echo $pending_contests; ?></h3>
                </div>
                <div class="p-4 md:p-8 bg-white dark:bg-gray-900 rounded-2xl md:rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm <?php echo $pending_ugc > 0 ? 'border-b-4 border-b-orange-500' : ''; ?>">
                    <p class="text-[10px] font-black uppercase tracking-widest <?php echo $pending_ugc > 0 ? 'text-orange-500' : 'text-gray-400'; ?> mb-2">Pending UGC</p>
                    <h3 class="text-xl md:text-3xl font-black text-gray-900 dark:text-white"><?php echo $pending_ugc; ?></h3>
                </div>
            </div>

            <!-- Pending Verifications - Full Width -->
            <?php if ($pending_verif_count > 0): ?>
            <section class="p-6 md:p-8 bg-white dark:bg-gray-900 rounded-2xl md:rounded-[2.5rem] border border-orange-200 dark:border-orange-800 shadow-sm border-l-4 border-l-orange-500">
                <div class="flex items-center justify-between mb-5 md:mb-6">
                    <h4 class="text-xs md:text-sm font-black uppercase tracking-widest text-orange-500">
                        🎓 Pending Badge Verifications (<?php echo $pending_verif_count; ?>)
                    </h4>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 md:gap-4">
                    <?php foreach ($pending_verifs as $v): ?>
                        <div class="p-4 md:p-5 bg-gray-50 dark:bg-gray-800/50 rounded-2xl border border-gray-100 dark:border-gray-700 flex flex-col gap-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-bold text-gray-900 dark:text-white truncate"><?php echo e($v['full_name']); ?></p>
                                    <p class="text-[10px] text-gray-500 font-medium truncate"><?php echo e($v['school_email'] ?: 'No email provided'); ?></p>
                                    <p class="text-[10px] text-gray-400"><?php echo time_ago($v['submitted_at']); ?></p>
                                </div>
                                <div class="flex gap-2 flex-shrink-0">
                                    <a href="?action=verify&creator_id=<?php echo $v['id']; ?>" class="px-3 py-2 bg-green-500 text-white text-[10px] font-black rounded-lg hover:scale-105 transition shadow-lg shadow-green-500/20">✓</a>
                                    <a href="?action=reject&creator_id=<?php echo $v['id']; ?>" class="px-3 py-2 bg-red-100 text-red-500 text-[10px] font-black rounded-lg hover:bg-red-500 hover:text-white transition">✗</a>
                                </div>
                            </div>
                            <?php if ($v['id_upload'] || $v['letter_upload']): ?>
                            <div class="flex gap-3 pt-3 border-t border-gray-100 dark:border-gray-700 flex-wrap">
                                <?php if ($v['id_upload']): ?>
                                    <a href="/<?php echo ltrim(e($v['id_upload']), '/'); ?>" target="_blank"
                                       class="px-3 py-1 bg-primary/10 text-primary text-[10px] font-bold rounded-lg hover:bg-primary/20 transition">
                                        📄 View Student ID
                                    </a>
                                <?php endif; ?>
                                <?php if ($v['letter_upload']): ?>
                                    <a href="/<?php echo ltrim(e($v['letter_upload']), '/'); ?>" target="_blank"
                                       class="px-3 py-1 bg-primary/10 text-primary text-[10px] font-bold rounded-lg hover:bg-primary/20 transition">
                                        📄 View Admission Letter
                                    </a>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <p class="text-[10px] text-gray-400 italic pt-2 border-t border-gray-100 dark:border-gray-700">School email only — no documents uploaded.</p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 md:gap-8">
                <!-- Quick Actions -->
                <section class="lg:col-span-2 p-6 md:p-8 bg-white dark:bg-gray-900 rounded-2xl md:rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                    <h4 class="text-xs md:text-sm font-black uppercase tracking-widest text-gray-400 mb-5 md:mb-6">Submission & Payment Reviews</h4>
                    <div class="space-y-3">
                        <a href="<?php echo APP_URL; ?>admin/contest-submissions.php" class="block p-4 bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 rounded-2xl hover:bg-orange-100 dark:hover:bg-orange-900/40 transition">
                            <p class="font-bold text-orange-900 dark:text-orange-400">🎬 Contest Submissions</p>
                            <p class="text-xs md:text-sm text-orange-700 dark:text-orange-300 mt-1">Review <?php echo $pending_contests; ?> pending submission<?php echo $pending_contests !== 1 ? 's' : ''; ?></p>
                        </a>
                        <a href="<?php echo APP_URL; ?>admin/ugc-submissions.php" class="block p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-2xl hover:bg-blue-100 dark:hover:bg-blue-900/40 transition">
                            <p class="font-bold text-blue-900 dark:text-blue-400">🎥 UGC Submissions</p>
                            <p class="text-xs md:text-sm text-blue-700 dark:text-blue-300 mt-1">Review <?php echo $pending_ugc; ?> pending submission<?php echo $pending_ugc !== 1 ? 's' : ''; ?></p>
                        </a>
                        <a href="<?php echo APP_URL; ?>admin/cpm-payouts.php" class="block p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-2xl hover:bg-green-100 dark:hover:bg-green-900/40 transition">
                            <p class="font-bold text-green-900 dark:text-green-400">📊 CPM Payouts</p>
                            <p class="text-xs md:text-sm text-green-700 dark:text-green-300 mt-1">Calculate <?php echo $pending_cpm; ?> pending CPM calculation<?php echo $pending_cpm !== 1 ? 's' : ''; ?></p>
                        </a>
                        <a href="<?php echo APP_URL; ?>admin/payment-verification.php" class="block p-4 bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 rounded-2xl hover:bg-purple-100 dark:hover:bg-purple-900/40 transition">
                            <p class="font-bold text-purple-900 dark:text-purple-400">💳 Payment Verification</p>
                            <p class="text-xs md:text-sm text-purple-700 dark:text-purple-300 mt-1">Verify <?php echo $pending_payments; ?> pending payment<?php echo $pending_payments !== 1 ? 's' : ''; ?></p>
                        </a>
                    </div>
                </section>

                 <!-- Logs -->
                 <section class="p-6 md:p-8 bg-white dark:bg-gray-900 rounded-2xl md:rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                    <h4 class="text-xs md:text-sm font-black uppercase tracking-widest text-gray-400 mb-5 md:mb-6">Recent Activity</h4>
                    <div class="space-y-3 md:space-y-4">
                        <?php foreach ($logs as $log): ?>
                            <div class="flex items-start gap-3 md:gap-4 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-2xl border border-gray-100 dark:border-gray-700">
                                <div class="w-8 h-8 rounded-lg bg-orange-100 text-orange-600 flex items-center justify-center text-xs flex-shrink-0">⚡</div>
                                <div class="min-w-0">
                                    <p class="text-xs font-bold text-gray-900 dark:text-white truncate"><?php echo e($log["action"]); ?></p>
                                    <p class="text-[10px] text-gray-500 truncate"><?php echo e($log["email"]); ?> • <?php echo time_ago($log["created_at"]); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($logs)): ?>
                             <p class="text-center text-xs text-gray-400 py-10 italic">No logs found yet.</p>
                        <?php endif; ?>
                    </div>
                 </section>

            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
