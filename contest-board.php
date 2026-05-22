<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

$contest_id = $_GET['contest_id'] ?? 0;

$stmt = $pdo->prepare("SELECT c.*, b.brand_name AS company_name FROM contests c JOIN brands b ON c.brand_id = b.id WHERE c.id = ?");
$stmt->execute([$contest_id]);
$contest = $stmt->fetch();

if (!$contest) {
    die("Contest not found.");
}

$sort = $_GET['sort'] ?? 'newest';
$sort_sql = match($sort) {
    'most_viewed' => 'ORDER BY cs.view_count DESC, cs.created_at DESC',
    'most_engaged' => 'ORDER BY cs.engagement_count DESC, cs.created_at DESC',
    default => 'ORDER BY cs.created_at DESC'
};

$stmt = $pdo->prepare("
    SELECT cs.*, cr.full_name, cr.school
    FROM contest_submissions cs
    JOIN creators cr ON cs.creator_id = cr.id
    WHERE cs.contest_id = ? AND cs.status IN ('shortlisted', 'winner')
    $sort_sql
");
$stmt->execute([$contest_id]);
$submissions = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="pt-24 min-h-screen bg-gray-50 dark:bg-gray-950">
    <div class="max-w-7xl mx-auto px-6 md:px-12 py-8 space-y-8">
        <header class="p-8 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm relative overflow-hidden">
            <div aria-hidden="true" class="absolute top-0 right-0 -mr-16 -mt-16 w-64 h-64 bg-secondary/5 rounded-full blur-3xl"></div>
            <div class="relative">
                <a href="contests.php" class="text-secondary font-bold hover:underline mb-4 inline-block">← Back to Contests</a>
                <h1 class="text-4xl font-bold text-gray-900 dark:text-white"><?php echo e($contest['title']); ?></h1>
                <p class="text-gray-600 dark:text-gray-400 mt-2">By <?php echo e($contest['company_name']); ?></p>

                <div class="flex flex-wrap gap-4 mt-6">
                    <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-xl">
                        <p class="text-xs text-gray-500 mb-1">Prize Pool</p>
                        <p class="text-2xl font-black text-secondary"><?php echo e($contest['currency']); ?> <?php echo number_format((float)$contest['total_contest_budget'], 2); ?></p>
                    </div>
                    <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-xl">
                        <p class="text-xs text-gray-500 mb-1">Status</p>
                        <p class="text-lg font-black text-gray-900 dark:text-white"><?php echo ucfirst(e($contest['status'])); ?></p>
                    </div>
                    <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-xl">
                        <p class="text-xs text-gray-500 mb-1">Submissions</p>
                        <p class="text-lg font-black text-gray-900 dark:text-white"><?php echo count($submissions); ?></p>
                    </div>
                </div>
            </div>
        </header>

        <?php if ($contest['description']): ?>
            <div class="p-8 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-3">About This Contest</h3>
                <p class="text-gray-600 dark:text-gray-400"><?php echo nl2br(e($contest['description'])); ?></p>
            </div>
        <?php endif; ?>

        <div class="flex flex-wrap gap-2">
            <a href="?contest_id=<?php echo $contest_id; ?>&sort=newest" class="px-4 py-2 rounded-xl font-bold text-sm <?php echo $sort === 'newest' ? 'bg-secondary text-white' : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border border-gray-100 dark:border-gray-800'; ?>">Newest</a>
            <a href="?contest_id=<?php echo $contest_id; ?>&sort=most_viewed" class="px-4 py-2 rounded-xl font-bold text-sm <?php echo $sort === 'most_viewed' ? 'bg-secondary text-white' : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border border-gray-100 dark:border-gray-800'; ?>">Most Viewed</a>
            <a href="?contest_id=<?php echo $contest_id; ?>&sort=most_engaged" class="px-4 py-2 rounded-xl font-bold text-sm <?php echo $sort === 'most_engaged' ? 'bg-secondary text-white' : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border border-gray-100 dark:border-gray-800'; ?>">Most Engaged</a>
        </div>

        <div class="space-y-4">
            <?php foreach ($submissions as $submission): ?>
                <div class="p-6 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                    <div class="flex flex-col md:flex-row gap-6">
                        <div class="w-full md:w-48 h-40 rounded-xl overflow-hidden bg-gray-200 dark:bg-gray-800 flex-shrink-0">
                            <?php if ($submission['watermarked_preview_file']): ?>
                                <img src="<?php echo e($submission['watermarked_preview_file']); ?>" alt="Entry" class="w-full h-full object-cover">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center text-gray-400 text-4xl">🎬</div>
                            <?php endif; ?>
                        </div>

                        <div class="flex-1">
                            <div class="flex flex-wrap items-center gap-3 mb-3">
                                <?php if ($submission['status'] === 'winner'): ?>
                                    <span class="px-3 py-1 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 rounded-full text-[10px] font-black uppercase">🏆 Winner</span>
                                <?php else: ?>
                                    <span class="px-3 py-1 bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 rounded-full text-[10px] font-black uppercase">⭐ Shortlisted</span>
                                <?php endif; ?>
                                <?php if ($submission['view_count'] > 0): ?>
                                    <span class="px-3 py-1 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-full text-[10px] font-black uppercase">👁 <?php echo (int)$submission['view_count']; ?> views</span>
                                <?php endif; ?>
                            </div>

                            <h3 class="text-xl font-black text-gray-900 dark:text-white"><?php echo e($submission['title'] ?? 'Untitled'); ?></h3>
                            <p class="text-gray-600 dark:text-gray-400 mt-2">By <?php echo e($submission['full_name']); ?></p>
                            <?php if ($submission['school']): ?>
                                <p class="text-sm text-gray-500"><?php echo e($submission['school']); ?></p>
                            <?php endif; ?>

                            <?php if ($submission['description']): ?>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-3"><?php echo e($submission['description']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($submissions)): ?>
            <div class="p-12 text-center bg-white dark:bg-gray-900 rounded-[2.5rem] border border-dashed border-gray-200 dark:border-gray-800 shadow-sm">
                <p class="text-gray-400">No submissions to display yet.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
