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

    redirect('admin/watermark-management.php?message=Submission deleted');
    exit;
}

$where = "1=1";
$params = [];

if ($filter === 'pending') {
    $where .= " AND us.watermark_approved = 0 AND us.status = 'approved'";
} elseif ($filter === 'approved') {
    $where .= " AND us.watermark_approved = 1";
}

if ($search) {
    $where .= " AND (cr.full_name LIKE ? OR uo.title LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$stmt = $pdo->prepare("
    SELECT us.*, uo.title as order_title, uo.id as order_id, cr.full_name as creator_name, b.brand_name
    FROM ugc_order_submissions us
    JOIN ugc_orders uo ON us.ugc_order_id = uo.id
    JOIN creators cr ON us.creator_id = cr.id
    JOIN brands b ON uo.brand_id = b.id
    WHERE {$where}
    ORDER BY us.created_at DESC
");
$stmt->execute($params);
$submissions = $stmt->fetchAll();

if (isset($_POST['approve_clean_file']) && isset($_POST['submission_id'])) {
    $submission_id = $_POST['submission_id'];
    $stmt = $pdo->prepare("UPDATE ugc_order_submissions SET watermark_approved = 1, clean_file_unlocked_at = NOW() WHERE id = ?");
    $stmt->execute([$submission_id]);

    $stmt = $pdo->prepare("SELECT creator_id FROM ugc_order_submissions WHERE id = ?");
    $stmt->execute([$submission_id]);
    $creator_id = $stmt->fetchColumn();

    create_notification($creator_id, 'Clean File Unlocked', 'Your approved UGC video is ready for download with clean (unwatermarked) file access!', 'ugc_clean_unlocked', 'creator/ugc-orders.php?tab=my_submissions', 'ugc_submission', $submission_id);

    redirect('admin/watermark-management.php?message=Clean file unlocked');
    exit;
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
                    <h2 class="text-3xl font-black text-gray-900 dark:text-white">Watermark Management</h2>
                    <p class="text-gray-500 font-bold mt-1">Unlock clean files for approved UGC submissions.</p>
                </div>
            </header>

            <?php if (!empty($_GET['message'])): ?>
                <div class="p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-900 rounded-2xl text-green-800 dark:text-green-300 text-sm font-medium">
                    ✓ <?php echo e($_GET['message']); ?>
                </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="p-6 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                <form method="GET" class="flex flex-col md:flex-row gap-4">
                    <div class="flex-1">
                        <input type="text" name="search" placeholder="Search creator or order..." value="<?php echo e($search); ?>"
                            class="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                    </div>
                    <select name="filter" class="px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white font-medium">
                        <option value="pending" <?php echo $filter === 'pending' ? 'selected' : ''; ?>>Pending Clean File</option>
                        <option value="approved" <?php echo $filter === 'approved' ? 'selected' : ''; ?>>Clean File Unlocked</option>
                    </select>
                    <button type="submit" class="px-6 py-3 bg-orange-500 text-white font-bold rounded-xl hover:bg-orange-600 transition">Filter</button>
                </form>
            </div>

            <!-- Submissions Grid -->
            <div class="grid grid-cols-1 gap-6">
                <?php if (empty($submissions)): ?>
                    <div class="p-12 text-center bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800">
                        <p class="text-gray-400 text-sm">No submissions found.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($submissions as $sub): ?>
                        <div class="p-6 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                            <div class="flex flex-col md:flex-row gap-6">
                                <!-- Watermarked Preview -->
                                <div class="md:w-64 flex-shrink-0">
                                    <div class="bg-gray-100 dark:bg-gray-800 rounded-2xl overflow-hidden h-40 flex items-center justify-center">
                                        <?php if ($sub['watermarked_preview_file']): ?>
                                            <video src="<?php echo APP_URL . ltrim($sub['watermarked_preview_file'], '/'); ?>" class="w-full h-full object-cover" controls></video>
                                        <?php elseif ($sub['video_file']): ?>
                                            <video src="<?php echo APP_URL . ltrim($sub['video_file'], '/'); ?>" class="w-full h-full object-cover" controls></video>
                                        <?php else: ?>
                                            <p class="text-gray-400">No preview</p>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-[10px] text-gray-500 mt-2 text-center">Watermarked Preview</p>
                                </div>

                                <!-- Details -->
                                <div class="flex-1">
                                    <div class="flex items-start justify-between mb-4">
                                        <div>
                                            <h4 class="font-bold text-gray-900 dark:text-white"><?php echo e($sub['creator_name']); ?></h4>
                                            <p class="text-sm text-gray-500"><?php echo e($sub['order_title']); ?> • <?php echo e($sub['brand_name']); ?></p>
                                            <p class="text-[10px] text-gray-400 mt-1">Submitted <?php echo time_ago($sub['created_at']); ?></p>
                                        </div>
                                        <span class="px-3 py-1 bg-green-100 text-green-700 dark:bg-green-900/20 dark:text-green-400 text-[10px] font-bold rounded-full">Approved</span>
                                    </div>

                                    <div class="grid grid-cols-3 gap-4 mb-6 py-4 border-t border-gray-100 dark:border-gray-700">
                                        <div>
                                            <p class="text-[10px] text-gray-500 uppercase tracking-widest font-bold mb-1">Views</p>
                                            <p class="font-bold text-gray-900 dark:text-white"><?php echo $sub['view_count'] ?? 0; ?></p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] text-gray-500 uppercase tracking-widest font-bold mb-1">Engagement</p>
                                            <p class="font-bold text-gray-900 dark:text-white"><?php echo $sub['engagement_count'] ?? 0; ?></p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] text-gray-500 uppercase tracking-widest font-bold mb-1">Status</p>
                                            <p class="font-bold <?php echo $sub['watermark_approved'] ? 'text-green-600' : 'text-yellow-600'; ?>"><?php echo $sub['watermark_approved'] ? '✓ Unlocked' : 'Pending'; ?></p>
                                        </div>
                                    </div>

                                    <?php if (!$sub['watermark_approved']): ?>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="submission_id" value="<?php echo $sub['id']; ?>">
                                            <input type="hidden" name="approve_clean_file" value="1">
                                            <button type="submit" class="px-6 py-3 bg-green-500 text-white font-bold rounded-xl hover:bg-green-600 transition">🔓 Unlock Clean File</button>
                                        </form>
                                    <?php else: ?>
                                        <p class="text-[10px] font-bold text-green-600">Clean file unlocked on <?php echo date('M d, Y', strtotime($sub['clean_file_unlocked_at'])); ?></p>
                                    <?php endif; ?>

                                    <form method="POST" class="inline mt-3" onsubmit="return confirm('Delete this submission and its uploaded files?');">
                                        <input type="hidden" name="delete_submission_id" value="<?php echo $sub['id']; ?>">
                                        <button type="submit" class="px-6 py-3 bg-red-50 text-red-600 font-bold rounded-xl hover:bg-red-600 hover:text-white transition">Delete</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
