<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_role('admin');

$filter = $_GET['filter'] ?? 'pending_calc';
$search = $_GET['search'] ?? '';

$where = "1=1";
$params = [];

if ($filter === 'pending_calc') {
    $where .= " AND c.cpm_calculated_at IS NULL AND c.status = 'live'";
} elseif ($filter === 'calculated') {
    $where .= " AND c.cpm_calculated_at IS NOT NULL";
} elseif ($filter === 'paid') {
    $where .= " AND c.cpm_calculated_at IS NOT NULL AND p.status = 'completed'";
}

if ($search) {
    $where .= " AND c.title LIKE ?";
    $params[] = "%{$search}%";
}

$stmt = $pdo->prepare("
    SELECT c.*, COUNT(DISTINCT cs.id) as winner_count, COUNT(DISTINCT p.id) as paid_count, b.brand_name
    FROM contests c
    LEFT JOIN contest_submissions cs ON c.id = cs.contest_id AND cs.status = 'winner' AND cs.views_verified = 1
    LEFT JOIN payments p ON cs.payment_id = p.id
    LEFT JOIN brands b ON c.brand_id = b.id
    WHERE {$where}
    GROUP BY c.id
    ORDER BY c.created_at DESC
");
$stmt->execute($params);
$contests = $stmt->fetchAll();

if (isset($_POST['calculate_payouts']) && isset($_POST['contest_id'])) {
    $contest_id = $_POST['contest_id'];

    $stmt = $pdo->prepare("SELECT * FROM contests WHERE id = ?");
    $stmt->execute([$contest_id]);
    $contest = $stmt->fetch();

    if (!$contest) {
        $error = "Contest not found.";
    } else {
        $stmt = $pdo->prepare("
            SELECT cs.* FROM contest_submissions cs
            WHERE cs.contest_id = ? AND cs.status = 'winner' AND cs.views_verified = 1
        ");
        $stmt->execute([$contest_id]);
        $winners = $stmt->fetchAll();

        if (empty($winners)) {
            $error = "No verified winners found for this contest.";
        } else {
            $total_budget = $contest['total_contest_budget'];
            $total_verified_views = 0;

            foreach ($winners as $winner) {
                $total_verified_views += ($winner['verified_view_count'] ?? 0);
            }

            if ($total_verified_views === 0) {
                $error = "No verified views from winners.";
            } else {
                $cpm = $total_budget / ($total_verified_views / 1000);

                foreach ($winners as $winner) {
                    $verified_views = $winner['verified_view_count'] ?? 0;
                    $payout_amount = ($verified_views / 1000) * $cpm;

                    $stmt = $pdo->prepare("
                        INSERT INTO payments (creator_id, job_id, amount, calculated_amount, currency, payment_type, status)
                        VALUES (?, 0, ?, ?, ?, 'contest_cpm', 'pending')
                    ");
                    $stmt->execute([
                        $winner['creator_id'],
                        $payout_amount,
                        $payout_amount,
                        $contest['currency']
                    ]);

                    $payment_id = $pdo->lastInsertId();

                    $stmt = $pdo->prepare("UPDATE contest_submissions SET payment_id = ? WHERE id = ?");
                    $stmt->execute([$payment_id, $winner['id']]);

                    create_notification(
                        $winner['creator_id'],
                        'CPM Payout Calculated',
                        'Your CPM payout for ' . $contest['title'] . ' has been calculated.',
                        'cpm_calculated',
                        'creator/my-contests.php',
                        'contest_payout',
                        $contest_id
                    );
                }

                $stmt = $pdo->prepare("UPDATE contests SET cpm_rate = ?, cpm_calculated_at = NOW() WHERE id = ?");
                $stmt->execute([$cpm, $contest_id]);

                redirect('admin/cpm-payouts.php?message=CPM payouts calculated successfully');
                exit;
            }
        }
    }
}

include '../includes/header.php';
?>

<div class="pt-24 min-h-screen bg-gray-50 dark:bg-gray-950">
    <div class="max-w-7xl mx-auto px-6 md:px-12 flex flex-col md:flex-row gap-8 py-8 w-full">
        <!-- Sidebar -->
        <?php include "dashboard_sidebar.php"; ?>

        <!-- Main Content -->
        <main class="flex-1 space-y-8">
            <header class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm relative overflow-hidden">
                <div aria-hidden="true" class="absolute top-0 right-0 -mr-16 -mt-16 w-64 h-64 bg-orange-500/5 rounded-full blur-3xl"></div>
                <div class="relative">
                    <h2 class="text-3xl font-black text-gray-900 dark:text-white">CPM Payouts</h2>
                    <p class="text-gray-500 font-bold mt-1">Calculate and manage contest CPM payouts.</p>
                </div>
            </header>

            <!-- Filters -->
            <div class="p-6 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                <form method="GET" class="flex flex-col md:flex-row gap-4">
                    <div class="flex-1">
                        <input type="text" name="search" placeholder="Search contests..." value="<?php echo e($search); ?>"
                            class="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                    </div>
                    <select name="filter" class="px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white font-medium">
                        <option value="pending_calc" <?php echo $filter === 'pending_calc' ? 'selected' : ''; ?>>Pending Calculation</option>
                        <option value="calculated" <?php echo $filter === 'calculated' ? 'selected' : ''; ?>>Calculated</option>
                        <option value="paid" <?php echo $filter === 'paid' ? 'selected' : ''; ?>>Paid Out</option>
                    </select>
                    <button type="submit" class="px-6 py-3 bg-orange-500 text-white font-bold rounded-xl hover:bg-orange-600 transition">Filter</button>
                </form>
            </div>

            <?php if (isset($error)): ?>
                <div class="p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-900 rounded-2xl">
                    <p class="text-red-700 dark:text-red-400 text-sm font-bold"><?php echo e($error); ?></p>
                </div>
            <?php endif; ?>

            <!-- Contests Grid -->
            <div class="space-y-6">
                <?php if (empty($contests)): ?>
                    <div class="p-12 text-center bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800">
                        <p class="text-gray-400 text-sm">No contests found.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($contests as $contest): ?>
                        <div class="p-6 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                                <div>
                                    <h3 class="font-bold text-gray-900 dark:text-white text-lg"><?php echo e($contest['title']); ?></h3>
                                    <p class="text-sm text-gray-500 mt-1"><?php echo e($contest['brand_name']); ?> • <?php echo e($contest['description']); ?></p>

                                    <div class="grid grid-cols-4 gap-4 mt-4">
                                        <div>
                                            <p class="text-[10px] text-gray-500 uppercase tracking-widest font-bold mb-1">Total Budget</p>
                                            <p class="font-bold text-gray-900 dark:text-white"><?php echo number_format($contest['total_contest_budget']); ?> <?php echo e($contest['currency']); ?></p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] text-gray-500 uppercase tracking-widest font-bold mb-1">Winners</p>
                                            <p class="font-bold text-gray-900 dark:text-white"><?php echo $contest['winner_count']; ?></p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] text-gray-500 uppercase tracking-widest font-bold mb-1">CPM Rate</p>
                                            <p class="font-bold text-gray-900 dark:text-white"><?php echo $contest['cpm_rate'] ? number_format($contest['cpm_rate'], 4) : '-'; ?></p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] text-gray-500 uppercase tracking-widest font-bold mb-1">Status</p>
                                            <p class="font-bold <?php echo $contest['cpm_calculated_at'] ? 'text-green-600' : 'text-yellow-600'; ?>"><?php echo $contest['cpm_calculated_at'] ? '✓ Calculated' : 'Pending'; ?></p>
                                        </div>
                                    </div>
                                </div>

                                <?php if (!$contest['cpm_calculated_at']): ?>
                                    <form method="POST" class="md:flex-shrink-0">
                                        <input type="hidden" name="contest_id" value="<?php echo $contest['id']; ?>">
                                        <button type="submit" name="calculate_payouts" class="px-6 py-3 bg-green-500 text-white font-bold rounded-xl hover:bg-green-600 transition whitespace-nowrap">
                                            Calculate CPM Payouts
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <div class="md:flex-shrink-0 text-right">
                                        <p class="text-[10px] font-bold text-gray-500">Calculated on</p>
                                        <p class="text-sm font-bold text-gray-900 dark:text-white"><?php echo date('M d, Y', strtotime($contest['cpm_calculated_at'])); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
