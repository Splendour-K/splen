<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_role('admin');

$filter = $_GET['filter'] ?? 'pending';
$search = $_GET['search'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_submission_id'])) {
    $delete_id = (int)$_POST['delete_submission_id'];
    $stmt = $pdo->prepare("SELECT video_file, watermarked_preview_file, clean_video_file FROM ugc_order_submissions WHERE id = ?");
    $stmt->execute([$delete_id]);
    $media = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($media) {
        delete_uploaded_file_path($media['video_file']);
        delete_uploaded_file_path($media['watermarked_preview_file']);
        delete_uploaded_file_path($media['clean_video_file']);
        $pdo->prepare("DELETE FROM ugc_order_submissions WHERE id = ?")->execute([$delete_id]);
    }

    redirect('admin/ugc-submissions.php?message=Submission deleted');
    exit;
}

$sql = "
    SELECT us.*, uo.title as order_title, uo.brand_id, uo.budget_per_creator as payment_per_video, uo.currency,
        cr.full_name as creator_name, cr.school,
        b.brand_name AS company_name, b.user_id as brand_user_id
    FROM ugc_order_submissions us
    JOIN ugc_orders uo ON us.ugc_order_id = uo.id
    JOIN creators cr ON us.creator_id = cr.id
    JOIN brands b ON uo.brand_id = b.id
    WHERE 1=1
";

if ($filter === 'pending') {
    $sql .= " AND us.status = 'submitted'";
} elseif ($filter === 'flagged') {
    $sql .= " AND us.flag_reason IS NOT NULL";
}

if ($search) {
    $sql .= " AND (uo.title LIKE ? OR cr.full_name LIKE ?)";
}

$sql .= " ORDER BY us.created_at DESC";

$stmt = $pdo->prepare($sql);

if ($search) {
    $search_param = '%' . $search . '%';
    $stmt->execute([$search_param, $search_param]);
} else {
    $stmt->execute();
}

$submissions = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="pt-24 min-h-screen bg-gray-50 dark:bg-gray-950">
    <div class="max-w-7xl mx-auto px-6 md:px-12 flex flex-col md:flex-row gap-8 py-8">
        <?php include 'dashboard_sidebar.php'; ?>

        <main class="flex-1 space-y-8">
            <header class="p-8 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm relative overflow-hidden">
                <div aria-hidden="true" class="absolute top-0 right-0 -mr-16 -mt-16 w-64 h-64 bg-secondary/5 rounded-full blur-3xl"></div>
                <div class="relative">
                    <h2 class="text-3xl font-bold text-gray-900 dark:text-white">UGC Submissions Review</h2>
                    <p class="text-gray-600 dark:text-gray-400 mt-2">Verify quality and view counts for UGC payments</p>
                </div>
            </header>

            <?php if (!empty($_GET['message'])): ?>
                <div class="p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-900 rounded-2xl text-green-800 dark:text-green-300 text-sm font-medium">
                    ✓ <?php echo e($_GET['message']); ?>
                </div>
            <?php endif; ?>

            <div class="space-y-4">
                <div class="flex flex-col md:flex-row gap-4">
                    <div class="flex-1">
                        <form method="GET" class="flex gap-2">
                            <input type="text" name="search" placeholder="Search creator or order..." value="<?php echo e($search); ?>" class="flex-1 px-4 py-3 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary">
                            <button type="submit" class="px-6 py-3 bg-secondary text-white font-bold rounded-xl hover:scale-105 transition">Search</button>
                        </form>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a href="?filter=pending" class="px-4 py-2 rounded-xl font-bold text-sm <?php echo $filter === 'pending' ? 'bg-secondary text-white' : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border border-gray-100 dark:border-gray-800'; ?>">Pending Review</a>
                    <a href="?filter=all" class="px-4 py-2 rounded-xl font-bold text-sm <?php echo $filter === 'all' ? 'bg-secondary text-white' : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border border-gray-100 dark:border-gray-800'; ?>">All Submissions</a>
                    <a href="?filter=flagged" class="px-4 py-2 rounded-xl font-bold text-sm <?php echo $filter === 'flagged' ? 'bg-secondary text-white' : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border border-gray-100 dark:border-gray-800'; ?>">Flagged</a>
                </div>
            </div>

            <?php if ($_GET['message'] ?? false): ?>
                <div class="p-4 bg-green-100 dark:bg-green-900/30 border border-green-200 dark:border-green-800 rounded-xl text-green-800 dark:text-green-400 font-bold">
                    ✓ <?php echo e($_GET['message']); ?>
                </div>
            <?php endif; ?>

            <div class="space-y-4">
                <?php foreach ($submissions as $submission): ?>
                    <div class="p-6 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                        <div class="flex flex-col lg:flex-row gap-6">
                            <!-- Thumbnail -->
                            <div class="w-full lg:w-48 h-40 rounded-xl overflow-hidden bg-gray-200 dark:bg-gray-800 flex-shrink-0">
                                <?php if ($submission['watermarked_preview_file']): ?>
                                    <img src="<?php echo e($submission['watermarked_preview_file']); ?>" alt="Preview" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center text-gray-400 text-4xl">🎬</div>
                                <?php endif; ?>
                            </div>

                            <!-- Details -->
                            <div class="flex-1">
                                <div class="flex flex-wrap items-center gap-3 mb-3">
                                    <span class="px-3 py-1 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-full text-[10px] font-black uppercase"><?php echo ucfirst(e($submission['status'])); ?></span>
                                    <span class="px-3 py-1 bg-secondary/10 text-secondary rounded-full text-[10px] font-black uppercase"><?php echo e($submission['currency']); ?> <?php echo number_format((float)$submission['payment_per_video'], 2); ?></span>
                                    <?php if ($submission['flag_reason']): ?>
                                        <span class="px-3 py-1 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 rounded-full text-[10px] font-black uppercase">⚠ Flagged</span>
                                    <?php endif; ?>
                                </div>

                                <h3 class="text-xl font-black text-gray-900 dark:text-white mb-1"><?php echo e($submission['order_title']); ?></h3>
                                <p class="text-sm text-gray-500 mb-3"><?php echo e($submission['company_name']); ?> • By <?php echo e($submission['creator_name']); ?></p>

                                <div class="grid grid-cols-3 gap-3 my-3">
                                    <div class="p-2 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                        <p class="text-xs text-gray-500">View Count</p>
                                        <p class="text-lg font-black text-gray-900 dark:text-white"><?php echo (int)$submission['view_count']; ?></p>
                                    </div>
                                    <div class="p-2 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                        <p class="text-xs text-gray-500">Engagement</p>
                                        <p class="text-lg font-black text-gray-900 dark:text-white"><?php echo (int)$submission['engagement_count']; ?></p>
                                    </div>
                                    <div class="p-2 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                        <p class="text-xs text-gray-500">Quality Check</p>
                                        <p class="text-lg font-black text-gray-900 dark:text-white"><?php echo $submission['quality_verified'] ? '✓' : '○'; ?></p>
                                    </div>
                                </div>

                                <p class="text-xs text-gray-400">
                                    Submitted: <?php echo date('M d, Y H:i', strtotime($submission['created_at'])); ?>
                                </p>

                                <?php if ($submission['flag_reason']): ?>
                                    <div class="mt-3 p-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg">
                                        <p class="text-xs font-bold text-yellow-700 dark:text-yellow-400"><strong>Flag:</strong> <?php echo e($submission['flag_reason']); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Actions -->
                            <div class="flex flex-col gap-3 flex-shrink-0">
                                <form method="POST" onsubmit="return confirm('Delete this submission and its uploaded files?');">
                                    <input type="hidden" name="delete_submission_id" value="<?php echo $submission['id']; ?>">
                                    <button type="submit" class="w-full px-4 py-2 bg-red-50 text-red-600 font-bold rounded-lg text-sm hover:bg-red-600 hover:text-white transition">
                                        Delete
                                    </button>
                                </form>
                                <?php if ($submission['status'] === 'submitted' && !$submission['flag_reason']): ?>
                                    <form method="POST" action="../api/ugc-actions-admin.php" style="display:inline;">
                                        <input type="hidden" name="submission_id" value="<?php echo $submission['id']; ?>">
                                        <input type="hidden" name="action" value="verify">
                                        <button type="submit" class="w-full px-4 py-2 bg-green-600 text-white font-bold rounded-lg text-sm hover:scale-105 transition">
                                            Verify Quality
                                        </button>
                                    </form>
                                    <form method="POST" action="../api/ugc-actions-admin.php" style="display:inline;">
                                        <input type="hidden" name="submission_id" value="<?php echo $submission['id']; ?>">
                                        <input type="hidden" name="action" value="flag">
                                        <input type="hidden" name="flag_reason" value="Quality issue - needs review">
                                        <button type="submit" class="w-full px-4 py-2 bg-orange-600 text-white font-bold rounded-lg text-sm hover:scale-105 transition">
                                            Flag for Review
                                        </button>
                                    </form>
                                <?php elseif ($submission['flag_reason']): ?>
                                    <form method="POST" action="../api/ugc-actions-admin.php" style="display:inline;">
                                        <input type="hidden" name="submission_id" value="<?php echo $submission['id']; ?>">
                                        <input type="hidden" name="action" value="resolve_flag">
                                        <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white font-bold rounded-lg text-sm hover:scale-105 transition">
                                            Resolve Flag
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="px-4 py-2 bg-gray-200 dark:bg-gray-800 text-gray-700 dark:text-gray-400 font-bold rounded-lg text-sm text-center">
                                        Verified
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (empty($submissions)): ?>
                <div class="p-12 text-center bg-white dark:bg-gray-900 rounded-[2.5rem] border border-dashed border-gray-200 dark:border-gray-800 shadow-sm">
                    <p class="text-gray-400">No submissions to review.</p>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
