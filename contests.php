<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

$sort = $_GET['sort'] ?? 'newest';
$search = $_GET['search'] ?? '';

$sql = "
    SELECT c.*,
        (SELECT COUNT(*) FROM contest_submissions WHERE contest_id = c.id) as submission_count,
        b.brand_name AS company_name
    FROM contests c
    JOIN brands b ON c.brand_id = b.id
    WHERE c.status = 'live' AND c.submission_deadline > NOW()
";

if ($search) {
    $sql .= " AND (c.title LIKE ? OR c.description LIKE ?)";
}

$sql .= match($sort) {
    'deadline' => ' ORDER BY c.submission_deadline ASC',
    'popular' => ' ORDER BY submission_count DESC, c.created_at DESC',
    default => ' ORDER BY c.created_at DESC'
};

$stmt = $pdo->prepare($sql);

if ($search) {
    $search_param = '%' . $search . '%';
    $stmt->execute([$search_param, $search_param]);
} else {
    $stmt->execute();
}

$contests = $stmt->fetchAll();

$logged_in = isset($_SESSION['user_id']);
if ($logged_in && isset($_SESSION['role']) && $_SESSION['role'] === 'creator') {
    $stmt = $pdo->prepare("SELECT * FROM creators WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $creator = $stmt->fetch();
}

include 'includes/header.php';
?>

<div class="pt-24 min-h-screen bg-gray-50 dark:bg-gray-950">
    <div class="max-w-7xl mx-auto px-6 md:px-12 py-8 space-y-8">
        <header class="p-8 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm relative overflow-hidden">
            <div aria-hidden="true" class="absolute top-0 right-0 -mr-16 -mt-16 w-64 h-64 bg-secondary/5 rounded-full blur-3xl"></div>
            <div class="relative">
                <h1 class="text-4xl font-bold text-gray-900 dark:text-white">Contest Board</h1>
                <p class="text-gray-600 dark:text-gray-400 mt-2">Compete with creators worldwide and win amazing prizes.</p>
            </div>
        </header>

        <div class="space-y-4">
            <div class="flex flex-col md:flex-row gap-4">
                <div class="flex-1">
                    <form method="GET" class="flex gap-2">
                        <input type="text" name="search" placeholder="Search contests..." value="<?php echo e($search); ?>" class="flex-1 px-4 py-3 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary">
                        <button type="submit" class="px-6 py-3 bg-secondary text-white font-bold rounded-xl hover:scale-105 transition">Search</button>
                    </form>
                </div>
                <div>
                    <select onchange="window.location.href='?sort=' + this.value + (new URLSearchParams(window.location.search).get('search') ? '&search=' + new URLSearchParams(window.location.search).get('search') : '')" class="px-4 py-3 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary">
                        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="deadline" <?php echo $sort === 'deadline' ? 'selected' : ''; ?>>Ending Soon</option>
                        <option value="popular" <?php echo $sort === 'popular' ? 'selected' : ''; ?>>Most Popular</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="space-y-4">
            <?php foreach ($contests as $contest): ?>
                <div class="p-6 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm hover:shadow-md transition">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                        <div class="flex-1">
                            <div class="flex flex-wrap items-center gap-3 mb-3">
                                <span class="px-3 py-1 bg-secondary/10 text-secondary rounded-full text-[10px] font-black uppercase"><?php echo e($contest['currency']); ?> <?php echo number_format((float)$contest['total_contest_budget'], 2); ?> Prize Pool</span>
                                <span class="px-3 py-1 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-full text-[10px] font-black uppercase"><?php echo (int)$contest['submission_count']; ?> submissions</span>
                            </div>

                            <h3 class="text-2xl font-black text-gray-900 dark:text-white"><?php echo e($contest['title']); ?></h3>
                            <p class="text-sm text-gray-500 mt-1"><?php echo e($contest['company_name']); ?></p>

                            <?php if ($contest['description']): ?>
                                <p class="text-gray-600 dark:text-gray-400 mt-3 text-sm"><?php echo e(substr($contest['description'], 0, 150)) . (strlen($contest['description']) > 150 ? '...' : ''); ?></p>
                            <?php endif; ?>

                            <p class="text-xs text-gray-400 mt-4">
                                Deadline: <span class="font-bold text-gray-900 dark:text-white"><?php echo date('M d, Y H:i', strtotime($contest['submission_deadline'])); ?></span>
                            </p>
                        </div>

                        <div class="flex flex-col gap-3 flex-shrink-0">
                            <?php if ($logged_in && isset($creator)): ?>
                                <a href="<?php echo APP_URL; ?>creator/submit-to-contest.php?contest_id=<?php echo $contest['id']; ?>" class="px-6 py-3 bg-secondary text-white font-bold rounded-xl text-center hover:scale-105 transition">
                                    Submit Now
                                </a>
                            <?php else: ?>
                                <a href="<?php echo APP_URL; ?>login.php" class="px-6 py-3 bg-secondary text-white font-bold rounded-xl text-center hover:scale-105 transition">
                                    Login to Submit
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($contests)): ?>
            <div class="p-12 text-center bg-white dark:bg-gray-900 rounded-[2.5rem] border border-dashed border-gray-200 dark:border-gray-800 shadow-sm">
                <p class="text-gray-400 text-lg">No active contests at the moment. Check back soon!</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
