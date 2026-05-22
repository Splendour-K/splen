<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_role('creator');

$stmt = $pdo->prepare("SELECT * FROM creators WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$creator = $stmt->fetch();
require_creator_record($creator);

$tab = $_GET['tab'] ?? 'available';

if ($tab === 'my_submissions') {
    $stmt = $pdo->prepare("
        SELECT us.*, uo.title as order_title, uo.budget_per_creator as payment_per_video, uo.currency, uo.deadline as submission_deadline,
            b.brand_name AS company_name
        FROM ugc_order_submissions us
        JOIN ugc_orders uo ON us.ugc_order_id = uo.id
        JOIN brands b ON uo.brand_id = b.id
        WHERE us.creator_id = ?
        ORDER BY us.created_at DESC
    ");
    $stmt->execute([$creator['id']]);
    $submissions = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.*,
            (SELECT COUNT(*) FROM ugc_order_submissions WHERE ugc_order_id = u.id) as submission_count,
            (SELECT 1 FROM ugc_order_submissions WHERE ugc_order_id = u.id AND creator_id = ?) as already_submitted
        FROM ugc_orders u
        WHERE u.status = 'published' AND u.deadline > CURDATE()
        ORDER BY u.created_at DESC
    ");
    $stmt->execute([$creator['id']]);
    $available_orders = $stmt->fetchAll();
}

include '../includes/header.php';
?>

<div class="pt-24 min-h-screen bg-gray-50 dark:bg-gray-950">
    <div class="max-w-7xl mx-auto px-6 md:px-12 flex flex-col md:flex-row gap-8 py-8">
        <?php include '../includes/creator_sidebar.php'; ?>

        <main class="flex-1 space-y-8">
            <header class="p-8 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm relative overflow-hidden">
                <div aria-hidden="true" class="absolute top-0 right-0 -mr-16 -mt-16 w-64 h-64 bg-secondary/5 rounded-full blur-3xl"></div>
                <div class="relative">
                    <h2 class="text-3xl font-bold text-gray-900 dark:text-white">UGC Orders</h2>
                    <p class="text-gray-600 dark:text-gray-400 mt-2">Find UGC opportunities and earn flat-rate payments</p>
                </div>
            </header>

            <div class="flex flex-wrap gap-2">
                <a href="?tab=available" class="px-4 py-2 rounded-xl font-bold text-sm <?php echo $tab === 'available' ? 'bg-secondary text-white' : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border border-gray-100 dark:border-gray-800'; ?>">Available Orders</a>
                <a href="?tab=my_submissions" class="px-4 py-2 rounded-xl font-bold text-sm <?php echo $tab === 'my_submissions' ? 'bg-secondary text-white' : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border border-gray-100 dark:border-gray-800'; ?>">My Submissions</a>
            </div>

            <?php if ($tab === 'available'): ?>
                <div class="space-y-4">
                    <?php foreach ($available_orders as $order): ?>
                        <div class="p-6 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                            <div class="flex flex-col md:flex-row md:items-center justify-between gap-5">
                                <div class="flex-1">
                                    <div class="flex flex-wrap items-center gap-3 mb-2">
                                        <span class="px-3 py-1 bg-secondary/10 text-secondary rounded-full text-[10px] font-black uppercase"><?php echo e($order['currency']); ?> <?php echo number_format((float)$order['payment_per_video'], 2); ?>/video</span>
                                        <?php if ($order['already_submitted']): ?>
                                            <span class="px-3 py-1 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 rounded-full text-[10px] font-black uppercase">✓ Submitted</span>
                                        <?php endif; ?>
                                    </div>
                                    <h3 class="text-xl font-black text-gray-900 dark:text-white"><?php echo e($order['title']); ?></h3>
                                    <p class="text-sm text-gray-500 mt-1"><?php echo e($order['description'] ? substr($order['description'], 0, 100) . '...' : 'No description'); ?></p>
                                    <p class="text-xs text-gray-400 mt-2">
                                        Deadline: <?php echo date('M d, Y', strtotime($order['submission_deadline'])); ?> 
                                        | <?php echo (int)$order['submission_count']; ?> submissions
                                    </p>
                                </div>
                                <div>
                                    <?php if (!$order['already_submitted']): ?>
                                        <a href="submit-ugc-order.php?order_id=<?php echo $order['id']; ?>" class="px-6 py-3 bg-secondary text-white font-bold rounded-xl text-sm hover:scale-105 transition">
                                            Submit Video
                                        </a>
                                    <?php else: ?>
                                        <div class="px-6 py-3 bg-gray-200 dark:bg-gray-800 text-gray-700 dark:text-gray-400 font-bold rounded-xl text-sm text-center">
                                            Submitted
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if (empty($available_orders)): ?>
                        <div class="p-12 text-center bg-white dark:bg-gray-900 rounded-[2.5rem] border border-dashed border-gray-200 dark:border-gray-800 shadow-sm">
                            <p class="text-gray-400">No active UGC orders at the moment. Check back soon!</p>
                        </div>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php foreach ($submissions as $submission):
                        $video_src = $submission['clean_video_file']
                            ?: ($submission['video_file'] ?? null);
                    ?>
                        <div class="p-6 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm flex flex-col gap-4">
                            <div class="flex flex-col sm:flex-row gap-4">
                                <!-- Inline video playback (creator's own work) -->
                                <div class="w-full sm:w-40 aspect-[9/16] bg-black rounded-2xl overflow-hidden flex-shrink-0">
                                    <?php if ($video_src): ?>
                                        <video class="w-full h-full object-cover" controls preload="metadata" playsinline>
                                            <source src="<?php echo APP_URL . ltrim(e($video_src), '/'); ?>" type="video/mp4">
                                        </video>
                                    <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center text-gray-500 text-3xl">🎬</div>
                                    <?php endif; ?>
                                </div>

                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap items-center gap-2 mb-2">
                                        <span class="px-3 py-1 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-full text-[10px] font-black uppercase"><?php echo ucfirst(str_replace('_', ' ', e($submission['status']))); ?></span>
                                        <span class="px-3 py-1 bg-secondary/10 text-secondary rounded-full text-[10px] font-black uppercase"><?php echo e($submission['currency']); ?> <?php echo number_format((float)$submission['payment_per_video'], 2); ?></span>
                                    </div>
                                    <h3 class="text-lg font-black text-gray-900 dark:text-white truncate"><?php echo e($submission['order_title']); ?></h3>
                                    <p class="text-sm text-gray-500 mt-1 truncate"><?php echo e($submission['company_name']); ?></p>
                                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest mt-2">
                                        Submitted <?php echo date('M d, Y', strtotime($submission['created_at'])); ?>
                                    </p>

                                    <?php if ($submission['status'] === 'approved'): ?>
                                        <div class="mt-3 p-2 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
                                            <p class="text-xs font-bold text-green-700 dark:text-green-400">✓ Approved · Payment released</p>
                                        </div>
                                    <?php elseif ($submission['status'] === 'revision_requested'): ?>
                                        <div class="mt-3 p-2 bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 rounded-lg">
                                            <p class="text-xs font-bold text-orange-700 dark:text-orange-400">⚠ Revision needed</p>
                                        </div>
                                    <?php elseif ($submission['status'] === 'rejected'): ?>
                                        <div class="mt-3 p-2 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                                            <p class="text-xs font-bold text-red-700 dark:text-red-400">✕ Not selected</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="mt-3 p-2 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                                            <p class="text-xs font-bold text-blue-700 dark:text-blue-400">⏳ Awaiting brand review</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if (empty($submissions)): ?>
                        <div class="md:col-span-2 p-12 text-center bg-white dark:bg-gray-900 rounded-[2.5rem] border border-dashed border-gray-200 dark:border-gray-800 shadow-sm">
                            <p class="text-gray-400 mb-4">You haven't submitted any UGC videos yet.</p>
                            <a href="?tab=available" class="text-secondary font-bold hover:underline">Browse available orders →</a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
