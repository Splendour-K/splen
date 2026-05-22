<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_role('brand');

$ugc_order_id = $_GET['order_id'] ?? 0;

$stmt = $pdo->prepare("SELECT * FROM brands WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$brand = $stmt->fetch();

$stmt = $pdo->prepare("SELECT * FROM ugc_orders WHERE id = ? AND brand_id = ?");
$stmt->execute([$ugc_order_id, $brand['id']]);
$ugc_order = $stmt->fetch();

if (!$ugc_order) {
    die("UGC Order not found.");
}

$sort = $_GET['sort'] ?? 'newest';
$sort_sql = match($sort) {
    'most_viewed' => 'ORDER BY us.view_count DESC, us.created_at DESC',
    'most_engaged' => 'ORDER BY us.engagement_count DESC, us.created_at DESC',
    'approved' => 'ORDER BY us.status = "approved" DESC, us.created_at DESC',
    default => 'ORDER BY us.created_at DESC'
};

$stmt = $pdo->prepare("
    SELECT us.*, cr.full_name, cr.school, cr.profile_photo,
        (SELECT COUNT(*) FROM ugc_order_submissions WHERE status = 'submitted' AND ugc_order_id = ?) as pending_count
    FROM ugc_order_submissions us
    JOIN creators cr ON us.creator_id = cr.id
    WHERE us.ugc_order_id = ?
    $sort_sql
");
$stmt->execute([$ugc_order_id, $ugc_order_id]);
$submissions = $stmt->fetchAll();

$message = $_GET['message'] ?? '';

include '../includes/header.php';
?>

<div class="pt-24 min-h-screen bg-gray-50 dark:bg-gray-950">
    <div class="max-w-7xl mx-auto px-6 md:px-12 flex flex-col md:flex-row gap-8 py-8">
        <?php include '../includes/brand_sidebar.php'; ?>

        <main class="flex-1 space-y-8">
            <header class="p-8 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white"><?php echo e($ugc_order['title']); ?></h2>
                        <p class="text-gray-600 dark:text-gray-400 mt-1">Review submissions and approve videos</p>
                    </div>
                    <a href="ugc-orders.php" class="text-secondary font-bold hover:underline">← Back</a>
                </div>
            </header>

            <?php if ($message): ?>
                <div class="p-4 bg-green-100 dark:bg-green-900/30 border border-green-200 dark:border-green-800 rounded-xl text-green-800 dark:text-green-400 font-bold">
                    ✓ <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div class="flex flex-wrap gap-2 mb-6">
                <a href="?order_id=<?php echo $ugc_order_id; ?>&sort=newest" class="px-4 py-2 rounded-xl font-bold text-sm <?php echo $sort === 'newest' ? 'bg-secondary text-white' : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border border-gray-100 dark:border-gray-800'; ?>">Newest</a>
                <a href="?order_id=<?php echo $ugc_order_id; ?>&sort=most_viewed" class="px-4 py-2 rounded-xl font-bold text-sm <?php echo $sort === 'most_viewed' ? 'bg-secondary text-white' : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border border-gray-100 dark:border-gray-800'; ?>">Most Viewed</a>
                <a href="?order_id=<?php echo $ugc_order_id; ?>&sort=most_engaged" class="px-4 py-2 rounded-xl font-bold text-sm <?php echo $sort === 'most_engaged' ? 'bg-secondary text-white' : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border border-gray-100 dark:border-gray-800'; ?>">Most Engaged</a>
                <a href="?order_id=<?php echo $ugc_order_id; ?>&sort=approved" class="px-4 py-2 rounded-xl font-bold text-sm <?php echo $sort === 'approved' ? 'bg-secondary text-white' : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border border-gray-100 dark:border-gray-800'; ?>">Approved</a>
            </div>

            <div class="space-y-4">
                <?php foreach ($submissions as $submission): ?>
                    <div class="p-6 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                        <div class="flex flex-col md:flex-row gap-6">
                            <!-- In-page Video Player (watermarked preview until approval) -->
                            <div class="w-full md:w-64 aspect-[9/16] md:aspect-video rounded-2xl overflow-hidden bg-black flex-shrink-0 relative group">
                                <?php
                                    $preview_src = $submission['watermarked_preview_file'] ?: $submission['video_file'];
                                ?>
                                <?php if ($preview_src): ?>
                                    <video class="w-full h-full object-cover" controls controlsList="nodownload" preload="metadata" playsinline>
                                        <source src="<?php echo APP_URL . ltrim(e($preview_src), '/'); ?>" type="video/mp4">
                                        Your browser does not support the video tag.
                                    </video>
                                    <?php if ($submission['status'] !== 'approved'): ?>
                                        <div class="absolute top-2 right-2 px-2 py-1 bg-black/70 text-white text-[9px] font-black uppercase tracking-widest rounded-md pointer-events-none">Watermarked</div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center text-gray-500 text-4xl">📹</div>
                                <?php endif; ?>
                            </div>

                            <!-- Content -->
                            <div class="flex-1">
                                <div class="flex flex-wrap items-center gap-2 mb-3">
                                    <span class="px-3 py-1 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-full text-[10px] font-black uppercase"><?php echo ucfirst(e($submission['status'])); ?></span>
                                    <?php if ($submission['view_count'] > 0): ?>
                                        <span class="px-3 py-1 bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 rounded-full text-[10px] font-black uppercase">👁 <?php echo (int)$submission['view_count']; ?> views</span>
                                    <?php endif; ?>
                                    <?php if ($submission['engagement_count'] > 0): ?>
                                        <span class="px-3 py-1 bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-400 rounded-full text-[10px] font-black uppercase">💬 <?php echo (int)$submission['engagement_count']; ?></span>
                                    <?php endif; ?>
                                </div>

                                <div class="mb-4">
                                    <p class="font-bold text-gray-900 dark:text-white"><?php echo e($submission['full_name']); ?></p>
                                    <?php if ($submission['school']): ?>
                                        <p class="text-xs text-gray-500"><?php echo e($submission['school']); ?></p>
                                    <?php endif; ?>
                                    <p class="text-xs text-gray-400 mt-1">Submitted: <?php echo date('M d, Y H:i', strtotime($submission['created_at'])); ?></p>
                                </div>

                                <!-- Actions -->
                                <div class="flex flex-wrap gap-3">
                                    <?php if ($submission['status'] === 'submitted'): ?>
                                        <form method="POST" action="<?php echo APP_URL; ?>api/ugc-actions.php" style="display:inline;">
                                            <input type="hidden" name="submission_id" value="<?php echo $submission['id']; ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="px-4 py-2 bg-green-600 text-white font-bold rounded-lg text-sm hover:scale-105 transition">
                                                Approve & Pay
                                            </button>
                                        </form>
                                        <form method="POST" action="<?php echo APP_URL; ?>api/ugc-actions.php" style="display:inline;">
                                            <input type="hidden" name="submission_id" value="<?php echo $submission['id']; ?>">
                                            <input type="hidden" name="action" value="request_revision">
                                            <button type="submit" class="px-4 py-2 bg-orange-600 text-white font-bold rounded-lg text-sm hover:scale-105 transition">
                                                Request Revision
                                            </button>
                                        </form>
                                        <form method="POST" action="<?php echo APP_URL; ?>api/ugc-actions.php" style="display:inline;">
                                            <input type="hidden" name="submission_id" value="<?php echo $submission['id']; ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button type="submit" class="px-4 py-2 bg-red-600 text-white font-bold rounded-lg text-sm hover:scale-105 transition" onclick="return confirm('Reject this submission?')">
                                                Reject
                                            </button>
                                        </form>
                                    <?php elseif ($submission['status'] === 'approved'): ?>
                                        <span class="px-4 py-2 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 font-bold rounded-lg text-sm">
                                            ✓ Payment Released
                                        </span>
                                    <?php elseif ($submission['status'] === 'rejected'): ?>
                                        <span class="px-4 py-2 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 font-bold rounded-lg text-sm">
                                            ✕ Rejected
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (empty($submissions)): ?>
                <div class="p-12 text-center bg-white dark:bg-gray-900 rounded-[2.5rem] border border-dashed border-gray-200 dark:border-gray-800 shadow-sm">
                    <p class="text-gray-400">No submissions yet.</p>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
