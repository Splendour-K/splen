<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

// Auth state — Contest Board is PUBLIC (no login required to browse)
$logged_in = isset($_SESSION['user_id']);
$role      = $_SESSION['role'] ?? '';
$creator   = null;

if ($logged_in && $role === 'creator') {
    $stmt = $pdo->prepare("SELECT * FROM creators WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $creator = $stmt->fetch() ?: null;
}

// Filters & sorting
$sort   = $_GET['sort']   ?? 'newest';
$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT c.*,
        (SELECT COUNT(*) FROM contest_submissions WHERE contest_id = c.id) AS submission_count,
        b.brand_name AS company_name
    FROM contests c
    JOIN brands b ON c.brand_id = b.id
    WHERE c.status = 'live' AND c.submission_deadline > NOW()
";

$params = [];
if ($search !== '') {
    $sql .= " AND (c.title LIKE ? OR c.description LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$sql .= match($sort) {
    'deadline'   => ' ORDER BY c.submission_deadline ASC',
    'popular'    => ' ORDER BY submission_count DESC, c.created_at DESC',
    'prize_desc' => ' ORDER BY c.total_contest_budget DESC, c.created_at DESC',
    default      => ' ORDER BY c.created_at DESC',
};

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$contests = $stmt->fetchAll();

// Total stats
$stats_stmt = $pdo->query("
    SELECT
        COUNT(DISTINCT c.id) AS total_live,
        COALESCE(SUM(c.total_contest_budget), 0) AS total_prizes,
        COUNT(cs.id) AS total_submissions
    FROM contests c
    LEFT JOIN contest_submissions cs ON cs.contest_id = c.id
    WHERE c.status = 'live' AND c.submission_deadline > NOW()
");
$stats = $stats_stmt->fetch();

include 'includes/header.php';
?>

<div class="pt-24 min-h-screen bg-gray-50 dark:bg-gray-950">
    <div class="max-w-7xl mx-auto px-6 md:px-12 <?php echo ($role === 'creator' || $role === 'brand') ? 'flex flex-col md:flex-row gap-8' : ''; ?> py-8">

        <?php
        if ($role === 'brand')   include 'includes/brand_sidebar.php';
        elseif ($role === 'creator') include 'includes/creator_sidebar.php';
        ?>

        <main class="flex-1 space-y-8">
            <!-- Hero Header -->
            <header class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm relative overflow-hidden">
                <div aria-hidden="true" class="absolute top-0 right-0 -mr-20 -mt-20 w-80 h-80 bg-secondary/5 rounded-full blur-3xl pointer-events-none"></div>
                <div aria-hidden="true" class="absolute bottom-0 left-0 -ml-16 -mb-16 w-60 h-60 bg-primary/5 rounded-full blur-3xl pointer-events-none"></div>
                <div class="relative">
                    <div class="flex flex-col md:flex-row md:items-end justify-between gap-6">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-widest text-secondary mb-2">🏆 Live Now</p>
                            <h1 class="text-4xl md:text-5xl font-black text-gray-900 dark:text-white leading-tight">Contest Board</h1>
                            <p class="text-gray-500 font-medium mt-2 max-w-lg">Compete. Create. Win prizes from top brands. No pre-approval needed — just submit your best work.</p>
                        </div>
                        <?php if ($stats['total_live'] > 0): ?>
                        <div class="flex gap-6 text-center">
                            <div>
                                <p class="text-3xl font-black text-secondary"><?php echo (int)$stats['total_live']; ?></p>
                                <p class="text-[10px] font-black uppercase text-gray-500 tracking-widest">Live Contests</p>
                            </div>
                            <div class="w-px bg-gray-200 dark:bg-gray-700"></div>
                            <div>
                                <p class="text-3xl font-black text-gray-900 dark:text-white"><?php echo number_format($stats['total_submissions']); ?></p>
                                <p class="text-[10px] font-black uppercase text-gray-500 tracking-widest">Submissions</p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </header>

            <!-- Search & Sort -->
            <div class="flex flex-col md:flex-row gap-4">
                <form method="GET" class="flex gap-3 flex-1">
                    <input type="hidden" name="sort" value="<?php echo e($sort); ?>">
                    <input
                        type="text"
                        name="search"
                        placeholder="Search contests..."
                        value="<?php echo e($search); ?>"
                        class="flex-1 px-5 py-3 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-2xl focus:outline-none focus:border-secondary text-gray-900 dark:text-white placeholder-gray-400"
                    >
                    <button type="submit" class="px-6 py-3 bg-secondary text-white font-bold rounded-2xl hover:scale-105 transition">Search</button>
                    <?php if ($search): ?>
                        <a href="contest-board.php" class="px-4 py-3 bg-gray-200 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-bold rounded-2xl hover:bg-gray-300 dark:hover:bg-gray-700 transition">✕</a>
                    <?php endif; ?>
                </form>

                <div class="flex gap-2 flex-wrap">
                    <?php
                    $sort_options = [
                        'newest'     => '✨ Newest',
                        'deadline'   => '⏰ Ending Soon',
                        'popular'    => '🔥 Most Popular',
                        'prize_desc' => '💰 Biggest Prize',
                    ];
                    foreach ($sort_options as $val => $label):
                        $is = $sort === $val;
                        $q = $search ? '?sort=' . $val . '&search=' . urlencode($search) : '?sort=' . $val;
                    ?>
                    <a href="<?php echo $q; ?>" class="px-4 py-2.5 rounded-xl font-bold text-sm transition <?php echo $is ? 'bg-secondary text-white shadow-lg shadow-secondary/20' : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border border-gray-100 dark:border-gray-800 hover:border-secondary/50'; ?>">
                        <?php echo $label; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Contests Grid -->
            <?php if (!empty($contests)): ?>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <?php foreach ($contests as $contest):
                    $deadline_ts  = strtotime($contest['submission_deadline']);
                    $now_ts       = time();
                    $diff_secs    = max(0, $deadline_ts - $now_ts);
                    $days_left    = floor($diff_secs / 86400);
                    $hours_left   = floor(($diff_secs % 86400) / 3600);

                    if ($days_left > 0)        $countdown = $days_left . 'd ' . $hours_left . 'h left';
                    elseif ($hours_left > 0)   $countdown = $hours_left . 'h left';
                    else                       $countdown = 'Closing soon';

                    $urgency = ($days_left <= 2) ? 'text-red-500' : 'text-gray-500';
                    $prize_formatted = format_money((float)$contest['total_contest_budget'], $contest['currency']);
                ?>
                <div class="group p-6 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm hover:shadow-xl hover:border-secondary/30 transition-all duration-300 flex flex-col gap-5">
                    <!-- Top badges -->
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="px-3 py-1.5 bg-secondary/10 text-secondary rounded-full text-[10px] font-black uppercase tracking-wider">💰 <?php echo $prize_formatted; ?> Prize Pool</span>
                        <span class="px-3 py-1.5 bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 rounded-full text-[10px] font-black uppercase tracking-wider"><?php echo (int)$contest['submission_count']; ?> entries</span>
                        <?php if ($contest['number_of_winners'] > 1): ?>
                        <span class="px-3 py-1.5 bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-400 rounded-full text-[10px] font-black uppercase tracking-wider">🏆 <?php echo (int)$contest['number_of_winners']; ?> winners</span>
                        <?php endif; ?>
                    </div>

                    <!-- Title & Brand -->
                    <div>
                        <h3 class="text-xl font-black text-gray-900 dark:text-white group-hover:text-secondary transition-colors leading-tight"><?php echo e($contest['title']); ?></h3>
                        <p class="text-sm font-medium text-gray-500 mt-1">by <?php echo e($contest['company_name']); ?></p>
                    </div>

                    <!-- Description preview -->
                    <?php if (!empty($contest['description'])): ?>
                    <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed line-clamp-2"><?php echo e(mb_substr($contest['description'], 0, 160)) . (mb_strlen($contest['description']) > 160 ? '…' : ''); ?></p>
                    <?php endif; ?>

                    <!-- Deadline & CTA -->
                    <div class="flex items-center justify-between mt-auto pt-4 border-t border-gray-100 dark:border-gray-800">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Deadline</p>
                            <p class="text-sm font-bold text-gray-900 dark:text-white"><?php echo date('M d, Y', $deadline_ts); ?></p>
                            <p class="text-[11px] font-bold <?php echo $urgency; ?>"><?php echo $countdown; ?></p>
                        </div>

                        <div class="flex gap-2">
                            <a href="<?php echo APP_URL; ?>contest-detail.php?id=<?php echo (int)$contest['id']; ?>" class="px-4 py-2.5 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 font-bold text-sm rounded-xl hover:border-secondary hover:text-secondary transition">
                                Details
                            </a>
                            <?php if ($logged_in && $creator): ?>
                                <a href="<?php echo APP_URL; ?>creator/submit-to-contest.php?contest_id=<?php echo (int)$contest['id']; ?>" class="px-5 py-2.5 bg-secondary text-white font-bold text-sm rounded-xl hover:scale-105 transition shadow-md shadow-secondary/20">
                                    Submit ↗
                                </a>
                            <?php elseif ($logged_in && $role === 'brand'): ?>
                                <a href="<?php echo APP_URL; ?>contest-detail.php?id=<?php echo (int)$contest['id']; ?>" class="px-5 py-2.5 bg-primary text-white font-bold text-sm rounded-xl hover:scale-105 transition">
                                    View Board
                                </a>
                            <?php else: ?>
                                <a href="<?php echo APP_URL; ?>login.php" class="px-5 py-2.5 bg-secondary text-white font-bold text-sm rounded-xl hover:scale-105 transition">
                                    Login to Submit
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php else: ?>
            <!-- Empty state -->
            <div class="p-20 text-center bg-white dark:bg-gray-900 rounded-[2.5rem] border border-dashed border-gray-200 dark:border-gray-800 shadow-sm">
                <div class="text-6xl mb-6">🏆</div>
                <?php if ($search): ?>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">No contests match "<?php echo e($search); ?>"</h3>
                    <p class="text-gray-400 mb-6">Try a different keyword or browse all contests.</p>
                    <a href="contest-board.php" class="px-6 py-3 bg-secondary text-white font-bold rounded-xl hover:scale-105 transition">View All Contests</a>
                <?php else: ?>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">No live contests right now</h3>
                    <p class="text-gray-400">Check back soon — brands are always launching new opportunities!</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Past / Closed section (brief) -->
            <?php
            $closed_stmt = $pdo->query("
                SELECT c.id, c.title, c.total_contest_budget, c.currency, c.status,
                    b.brand_name AS company_name,
                    (SELECT COUNT(*) FROM contest_submissions WHERE contest_id = c.id AND status = 'winner') AS winner_count
                FROM contests c JOIN brands b ON c.brand_id = b.id
                WHERE c.status IN ('closed','completed','results_announced')
                ORDER BY c.submission_deadline DESC LIMIT 6
            ");
            $past_contests = $closed_stmt->fetchAll();
            if (!empty($past_contests)): ?>
            <div>
                <h2 class="text-xl font-black text-gray-900 dark:text-white mb-4">Recently Closed</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($past_contests as $pc):
                        $label_map = ['closed' => '🔒 Closed', 'completed' => '✅ Completed', 'results_announced' => '📢 Results Announced'];
                        $label = $label_map[$pc['status']] ?? ucfirst($pc['status']);
                    ?>
                    <a href="<?php echo APP_URL; ?>contest-detail.php?id=<?php echo (int)$pc['id']; ?>" class="p-5 bg-white dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-800 shadow-sm hover:border-secondary/30 transition-all block">
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <span class="text-[10px] font-black uppercase text-gray-400"><?php echo $label; ?></span>
                            <?php if ($pc['winner_count'] > 0): ?>
                            <span class="text-[10px] font-black text-green-600">🏆 <?php echo $pc['winner_count']; ?> winner<?php echo $pc['winner_count'] > 1 ? 's' : ''; ?></span>
                            <?php endif; ?>
                        </div>
                        <h4 class="font-bold text-gray-900 dark:text-white text-sm leading-snug mb-1"><?php echo e($pc['title']); ?></h4>
                        <p class="text-[11px] text-gray-500"><?php echo e($pc['company_name']); ?> · <?php echo format_money((float)$pc['total_contest_budget'], $pc['currency']); ?></p>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
