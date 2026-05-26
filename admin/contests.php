<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_role('admin');

$msg   = '';
$error = '';

// ── POST-based mutations (safe for state changes) ──────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action     = $_POST['action']     ?? '';
    $contest_id = (int)($_POST['contest_id'] ?? 0);

    if ($contest_id) {
        if ($action === 'delete') {
            try {
                $pdo->prepare("DELETE FROM contest_rewards WHERE contest_id = ?")->execute([$contest_id]);
                try { $pdo->prepare("DELETE FROM contest_reference_links WHERE contest_id = ?")->execute([$contest_id]); } catch (Exception $e) {}
                $pdo->prepare("DELETE FROM contest_submissions WHERE contest_id = ?")->execute([$contest_id]);
                $pdo->prepare("DELETE FROM contests WHERE id = ?")->execute([$contest_id]);
                log_activity($_SESSION['user_id'], 'Contest Deleted', "Contest #{$contest_id} permanently deleted by admin");
                $msg = "Contest deleted permanently.";
            } catch (Exception $e) {
                $error = "Delete failed: " . $e->getMessage();
            }

        } elseif (in_array($action, ['set_live', 'set_closed', 'set_completed', 'announce_winners'])) {
            $status_map = [
                'set_live'         => 'live',
                'set_closed'       => 'closed',
                'set_completed'    => 'completed',
                'announce_winners' => 'results_announced',
            ];
            $new_status = $status_map[$action];
            $pdo->prepare("UPDATE contests SET status = ? WHERE id = ?")->execute([$new_status, $contest_id]);
            log_activity($_SESSION['user_id'], 'Contest Status Changed', "Contest #{$contest_id} → {$new_status}");
            $msg = "Contest status updated to " . ucfirst(str_replace('_', ' ', $new_status)) . ".";
        }
    }
}

// ── Filters ────────────────────────────────────────────────────
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT c.*,
        b.brand_name, b.id AS brand_table_id,
        (SELECT COUNT(*) FROM contest_submissions WHERE contest_id = c.id) AS submission_count,
        (SELECT COUNT(*) FROM contest_submissions WHERE contest_id = c.id AND status = 'winner') AS winner_count,
        (SELECT COUNT(*) FROM contest_submissions WHERE contest_id = c.id AND status = 'submitted') AS pending_count
    FROM contests c
    JOIN brands b ON c.brand_id = b.id
    WHERE 1=1
";
$params = [];

if ($filter !== 'all') {
    $sql .= " AND c.status = ?";
    $params[] = $filter;
}
if ($search !== '') {
    $sql .= " AND (c.title LIKE ? OR b.brand_name LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
$sql .= " ORDER BY c.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$contests = $stmt->fetchAll();

// ── Prize rewards keyed by contest_id ─────────────────────────
$rewards_by_contest = [];
try {
    foreach ($pdo->query("SELECT * FROM contest_rewards ORDER BY contest_id, position_number ASC")->fetchAll() as $r) {
        $rewards_by_contest[$r['contest_id']][] = $r;
    }
} catch (Exception $e) {}

// ── Header stats ───────────────────────────────────────────────
$stat = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'live') AS live_count,
        SUM(status = 'closed') AS closed_count,
        SUM(status = 'completed') AS completed_count,
        SUM(status = 'results_announced') AS announced_count,
        COALESCE(SUM(total_contest_budget), 0) AS total_prize_pool
    FROM contests
")->fetch();

include '../includes/header.php';
?>

<div class="pt-24 min-h-screen bg-gray-50 dark:bg-gray-950">
    <div class="max-w-7xl mx-auto px-6 md:px-12 flex flex-col md:flex-row gap-8 py-8">
        <?php include 'dashboard_sidebar.php'; ?>

        <main class="flex-1 space-y-8 min-w-0">

            <!-- Header -->
            <header class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm relative overflow-hidden">
                <div aria-hidden="true" class="absolute top-0 right-0 -mr-20 -mt-20 w-72 h-72 bg-secondary/5 rounded-full blur-3xl pointer-events-none"></div>
                <div class="relative flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Admin — Full Control</p>
                        <h2 class="text-3xl font-black text-gray-900 dark:text-white mt-1">Contest Management</h2>
                        <p class="text-gray-500 mt-1">Create, edit, moderate, and manage all contests across the platform.</p>
                    </div>
                    <a href="edit-contest.php" class="inline-flex items-center gap-2 px-6 py-3 bg-secondary text-white font-bold rounded-xl hover:scale-105 transition shadow-md shadow-secondary/20 text-sm">
                        + Create Contest
                    </a>
                </div>

                <!-- Stats row -->
                <div class="flex flex-wrap gap-4 mt-6">
                    <?php $stat_items = [
                        ['label' => 'Total',     'value' => (int)$stat['total'],     'color' => 'text-gray-900 dark:text-white'],
                        ['label' => 'Live',      'value' => (int)$stat['live_count'], 'color' => 'text-green-600'],
                        ['label' => 'Closed',    'value' => (int)$stat['closed_count'], 'color' => 'text-orange-500'],
                        ['label' => 'Completed', 'value' => (int)$stat['completed_count'], 'color' => 'text-blue-600'],
                        ['label' => 'Announced', 'value' => (int)$stat['announced_count'], 'color' => 'text-purple-600'],
                        ['label' => 'Prize Pool',  'value' => format_money((float)$stat['total_prize_pool'], 'GHS'), 'color' => 'text-secondary'],
                    ]; foreach ($stat_items as $si): ?>
                    <div class="px-4 py-2.5 bg-gray-50 dark:bg-gray-800 rounded-xl text-center">
                        <p class="text-[10px] font-black uppercase text-gray-400 tracking-widest"><?php echo $si['label']; ?></p>
                        <p class="text-lg font-black <?php echo $si['color']; ?>"><?php echo $si['value']; ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </header>

            <?php if ($msg): ?>
                <div class="p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-2xl text-green-800 dark:text-green-400 font-bold">✅ <?php echo e($msg); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-2xl text-red-800 dark:text-red-400 font-bold">⚠️ <?php echo e($error); ?></div>
            <?php endif; ?>

            <!-- Search + Filter -->
            <div class="space-y-3">
                <form method="GET" class="flex gap-2">
                    <input type="hidden" name="filter" value="<?php echo e($filter); ?>">
                    <input type="text" name="search" placeholder="Search by contest title or brand name…"
                           value="<?php echo e($search); ?>"
                           class="flex-1 px-5 py-3 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-2xl focus:outline-none focus:border-secondary">
                    <button type="submit" class="px-6 py-3 bg-secondary text-white font-bold rounded-2xl hover:scale-105 transition">Search</button>
                    <?php if ($search): ?>
                        <a href="?filter=<?php echo e($filter); ?>" class="px-4 py-3 bg-gray-200 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-bold rounded-2xl hover:bg-gray-300 transition">✕</a>
                    <?php endif; ?>
                </form>

                <div class="flex flex-wrap gap-2">
                    <?php
                    $filter_tabs = [
                        'all'               => 'All',
                        'live'              => '🟢 Live',
                        'closed'            => '🔒 Closed',
                        'completed'         => '✅ Completed',
                        'results_announced' => '📢 Announced',
                    ];
                    foreach ($filter_tabs as $fk => $fl):
                        $href = '?filter=' . $fk . ($search ? '&search=' . urlencode($search) : '');
                    ?>
                    <a href="<?php echo $href; ?>" class="px-4 py-2 rounded-xl font-bold text-sm transition <?php echo $filter === $fk ? 'bg-secondary text-white' : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border border-gray-100 dark:border-gray-800 hover:border-secondary/40'; ?>">
                        <?php echo $fl; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Contests List -->
            <div class="space-y-4">
                <?php foreach ($contests as $contest):
                    $rewards    = $rewards_by_contest[$contest['id']] ?? [];
                    $status_cfg = [
                        'live'               => ['🟢 Live',             'bg-green-100 dark:bg-green-900/30 text-green-700'],
                        'closed'             => ['🔒 Closed',            'bg-gray-100 dark:bg-gray-800 text-gray-600'],
                        'completed'          => ['✅ Completed',          'bg-blue-100 dark:bg-blue-900/30 text-blue-700'],
                        'results_announced'  => ['📢 Announced',         'bg-purple-100 dark:bg-purple-900/30 text-purple-700'],
                    ];
                    [$slabel, $sclass] = $status_cfg[$contest['status']] ?? [ucfirst($contest['status']), 'bg-gray-100 text-gray-600'];
                ?>
                <div class="bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm overflow-hidden">

                    <?php if (!empty($contest['featured_image'])): ?>
                    <div class="w-full h-28 overflow-hidden">
                        <img src="<?php echo APP_URL . e($contest['featured_image']); ?>" alt="" class="w-full h-full object-cover">
                    </div>
                    <?php endif; ?>

                    <div class="p-6">
                        <div class="flex flex-col md:flex-row gap-5">
                            <!-- Info -->
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-2 mb-2">
                                    <span class="px-3 py-1 <?php echo $sclass; ?> rounded-full text-[10px] font-black uppercase"><?php echo $slabel; ?></span>
                                    <span class="px-3 py-1 bg-secondary/10 text-secondary rounded-full text-[10px] font-black uppercase"><?php echo format_money((float)$contest['total_contest_budget'], $contest['currency']); ?></span>
                                    <?php if (!empty($contest['category'])): ?>
                                    <span class="px-3 py-1 bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 rounded-full text-[10px] font-black uppercase"><?php echo e($contest['category']); ?></span>
                                    <?php endif; ?>
                                </div>

                                <h3 class="text-xl font-black text-gray-900 dark:text-white truncate"><?php echo e($contest['title']); ?></h3>
                                <p class="text-sm text-gray-500 mt-0.5">by <strong><?php echo e($contest['brand_name']); ?></strong></p>

                                <div class="flex flex-wrap gap-5 mt-3 text-xs">
                                    <div>
                                        <p class="text-[10px] font-black uppercase text-gray-400">Deadline</p>
                                        <p class="font-bold text-gray-900 dark:text-white"><?php echo date('M d, Y', strtotime($contest['submission_deadline'])); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-black uppercase text-gray-400">Submissions</p>
                                        <p class="font-bold text-gray-900 dark:text-white"><?php echo (int)$contest['submission_count']; ?></p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-black uppercase text-gray-400">Pending Review</p>
                                        <p class="font-bold text-orange-600"><?php echo (int)$contest['pending_count']; ?></p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-black uppercase text-gray-400">Winners</p>
                                        <p class="font-bold text-green-600"><?php echo (int)$contest['winner_count']; ?></p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-black uppercase text-gray-400">Winners Slots</p>
                                        <p class="font-bold text-gray-900 dark:text-white"><?php echo (int)($contest['number_of_winners'] ?? 1); ?></p>
                                    </div>
                                </div>

                                <!-- Prize Breakdown inline -->
                                <?php if (!empty($rewards)): ?>
                                <div class="flex flex-wrap gap-2 mt-3">
                                    <?php
                                    $icons = ['🥇','🥈','🥉','🏅','🏅'];
                                    foreach ($rewards as $ri => $reward):
                                        $icon = $icons[$ri] ?? '🏅';
                                    ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-amber-50 dark:bg-amber-900/20 text-amber-800 dark:text-amber-300 rounded-lg text-[10px] font-black">
                                        <?php echo $icon; ?> <?php echo e($reward['position_name']); ?> — <?php echo format_money((float)$reward['reward_amount'], $reward['currency']); ?>
                                    </span>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Actions -->
                            <div class="flex flex-col gap-2 flex-shrink-0 md:w-48">
                                <a href="contest-submissions.php?contest_id=<?php echo $contest['id']; ?>" class="px-4 py-2.5 bg-secondary text-white font-bold rounded-xl text-xs text-center hover:scale-105 transition">
                                    📋 Review Submissions (<?php echo (int)$contest['pending_count']; ?>)
                                </a>
                                <a href="edit-contest.php?id=<?php echo $contest['id']; ?>" class="px-4 py-2.5 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-bold rounded-xl text-xs text-center hover:bg-gray-200 dark:hover:bg-gray-700 transition">
                                    ✏️ Edit
                                </a>
                                <a href="<?php echo APP_URL; ?>contest-detail.php?id=<?php echo $contest['id']; ?>" target="_blank" class="px-4 py-2.5 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-bold rounded-xl text-xs text-center hover:bg-gray-200 transition">
                                    👁 View Public Page
                                </a>

                                <!-- Status Controls -->
                                <div class="border-t border-gray-100 dark:border-gray-700 pt-2 space-y-1.5">
                                    <?php if ($contest['status'] !== 'live'): ?>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="set_live">
                                        <input type="hidden" name="contest_id" value="<?php echo $contest['id']; ?>">
                                        <button type="submit" class="w-full px-4 py-2 bg-green-600 text-white font-bold rounded-xl text-[10px] uppercase hover:bg-green-700 transition">🟢 Set Live</button>
                                    </form>
                                    <?php endif; ?>
                                    <?php if ($contest['status'] === 'live'): ?>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="set_closed">
                                        <input type="hidden" name="contest_id" value="<?php echo $contest['id']; ?>">
                                        <button type="submit" class="w-full px-4 py-2 bg-orange-500 text-white font-bold rounded-xl text-[10px] uppercase hover:bg-orange-600 transition">🔒 Close</button>
                                    </form>
                                    <?php endif; ?>
                                    <?php if (in_array($contest['status'], ['closed'])): ?>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="set_completed">
                                        <input type="hidden" name="contest_id" value="<?php echo $contest['id']; ?>">
                                        <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white font-bold rounded-xl text-[10px] uppercase hover:bg-blue-700 transition">✅ Mark Completed</button>
                                    </form>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="announce_winners">
                                        <input type="hidden" name="contest_id" value="<?php echo $contest['id']; ?>">
                                        <button type="submit" class="w-full px-4 py-2 bg-purple-600 text-white font-bold rounded-xl text-[10px] uppercase hover:bg-purple-700 transition">📢 Announce Winners</button>
                                    </form>
                                    <?php endif; ?>
                                    <?php if ($contest['status'] === 'completed'): ?>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="announce_winners">
                                        <input type="hidden" name="contest_id" value="<?php echo $contest['id']; ?>">
                                        <button type="submit" class="w-full px-4 py-2 bg-purple-600 text-white font-bold rounded-xl text-[10px] uppercase hover:bg-purple-700 transition">📢 Announce Winners</button>
                                    </form>
                                    <?php endif; ?>
                                    <form method="POST" onsubmit="return confirm('Permanently delete this contest and ALL its submissions? This cannot be undone.');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="contest_id" value="<?php echo $contest['id']; ?>">
                                        <button type="submit" class="w-full px-4 py-2 bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 font-bold rounded-xl text-[10px] uppercase hover:bg-red-600 hover:text-white transition">🗑 Delete</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if (empty($contests)): ?>
            <div class="p-16 text-center bg-white dark:bg-gray-900 rounded-[2.5rem] border border-dashed border-gray-200 dark:border-gray-800">
                <div class="text-5xl mb-4">🏆</div>
                <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">No contests found</h3>
                <p class="text-gray-400 mb-6">
                    <?php echo $search ? 'No results for "' . e($search) . '".' : 'No contests match the selected filter.'; ?>
                </p>
                <a href="edit-contest.php" class="px-6 py-3 bg-secondary text-white font-bold rounded-xl hover:scale-105 transition">Create First Contest</a>
            </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
