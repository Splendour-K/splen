<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_role('admin');

$filter = $_GET['filter'] ?? 'pending';
$search = $_GET['search'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_submission_id'])) {
    $delete_id = (int)$_POST['delete_submission_id'];
    $stmt = $pdo->prepare("SELECT watermarked_preview_file, video_file, clean_video_file FROM contest_submissions WHERE id = ?");
    $stmt->execute([$delete_id]);
    $media = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($media) {
        delete_uploaded_file_path($media['watermarked_preview_file']);
        delete_uploaded_file_path($media['video_file']);
        delete_uploaded_file_path($media['clean_video_file']);
        $pdo->prepare("DELETE FROM contest_submissions WHERE id = ?")->execute([$delete_id]);
    }

    redirect('admin/view-count-verification.php?message=Submission deleted');
    exit;
}

$where = "1=1";
$params = [];

if ($filter === 'pending') {
    $where .= " AND cs.views_verified = 0 AND cs.view_count > 0 AND COALESCE(cs.view_count_rejected, 0) = 0";
} elseif ($filter === 'verified') {
    $where .= " AND cs.views_verified = 1";
}

if ($search) {
    $where .= " AND (cr.full_name LIKE ? OR c.title LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$stmt = $pdo->prepare("
    SELECT cs.*, c.title as contest_title, c.id as contest_id, cr.full_name as creator_name, b.brand_name
    FROM contest_submissions cs
    JOIN contests c ON cs.contest_id = c.id
    JOIN creators cr ON cs.creator_id = cr.id
    JOIN brands b ON c.brand_id = b.id
    WHERE {$where} AND cs.status = 'winner'
    ORDER BY cs.created_at DESC
");
$stmt->execute($params);
$submissions = $stmt->fetchAll();

if (isset($_POST['verify_views']) && isset($_POST['submission_id'])) {
    $submission_id = $_POST['submission_id'];
    $verified_views = intval($_POST['verified_view_count'] ?? 0);

    $stmt = $pdo->prepare("UPDATE contest_submissions SET views_verified = 1, verified_view_count = ?, views_verified_at = NOW() WHERE id = ?");
    $stmt->execute([$verified_views, $submission_id]);

    redirect('admin/view-count-verification.php?message=View count verified');
    exit;
}

if (isset($_POST['reject_views']) && isset($_POST['submission_id'])) {
    $rejection_reason = $_POST['rejection_reason'] ?? 'Unable to verify';
    $submission_id = $_POST['submission_id'];

    $stmt = $pdo->prepare("UPDATE contest_submissions SET view_count_rejected = 1, rejection_reason = ?, views_rejected_at = NOW() WHERE id = ?");
    $stmt->execute([$rejection_reason, $submission_id]);

    redirect('admin/view-count-verification.php?message=View count rejected');
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
                    <h2 class="text-3xl font-black text-gray-900 dark:text-white">View Count Verification</h2>
                    <p class="text-gray-500 font-bold mt-1">Verify contest winner view counts for CPM payout calculations.</p>
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
                        <input type="text" name="search" placeholder="Search creator or contest..." value="<?php echo e($search); ?>"
                            class="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                    </div>
                    <select name="filter" class="px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white font-medium">
                        <option value="pending" <?php echo $filter === 'pending' ? 'selected' : ''; ?>>Pending Verification</option>
                        <option value="verified" <?php echo $filter === 'verified' ? 'selected' : ''; ?>>Verified</option>
                    </select>
                    <button type="submit" class="px-6 py-3 bg-orange-500 text-white font-bold rounded-xl hover:bg-orange-600 transition">Filter</button>
                </form>
            </div>

            <!-- Submissions -->
            <div class="space-y-6">
                <?php if (empty($submissions)): ?>
                    <div class="p-12 text-center bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800">
                        <p class="text-gray-400 text-sm">No submissions found.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($submissions as $sub): ?>
                        <div class="p-6 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                            <div class="flex flex-col md:flex-row gap-6">
                                <!-- Submission Preview -->
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
                                </div>

                                <!-- Details -->
                                <div class="flex-1">
                                    <div class="flex items-start justify-between mb-4">
                                        <div>
                                            <h4 class="font-bold text-gray-900 dark:text-white"><?php echo e($sub['creator_name']); ?></h4>
                                            <p class="text-sm text-gray-500"><?php echo e($sub['contest_title']); ?> • <?php echo e($sub['brand_name']); ?></p>
                                        </div>
                                        <span class="px-3 py-1 bg-yellow-100 text-yellow-700 dark:bg-yellow-900/20 dark:text-yellow-400 text-[10px] font-bold rounded-full">Winner</span>
                                    </div>

                                    <div class="grid grid-cols-3 gap-4 mb-6 py-4 border-t border-gray-100 dark:border-gray-700">
                                        <div>
                                            <p class="text-[10px] text-gray-500 uppercase tracking-widest font-bold mb-1">Claimed Views</p>
                                            <p class="font-bold text-gray-900 dark:text-white text-lg"><?php echo number_format($sub['view_count']); ?></p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] text-gray-500 uppercase tracking-widest font-bold mb-1">Engagement</p>
                                            <p class="font-bold text-gray-900 dark:text-white"><?php echo number_format($sub['engagement_count']); ?></p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] text-gray-500 uppercase tracking-widest font-bold mb-1">Status</p>
                                            <p class="font-bold <?php echo $sub['views_verified'] ? 'text-green-600' : 'text-yellow-600'; ?>"><?php echo $sub['views_verified'] ? '✓ Verified' : 'Pending'; ?></p>
                                        </div>
                                    </div>

                                    <?php if (!$sub['views_verified']): ?>
                                        <form method="POST" class="space-y-4">
                                            <input type="hidden" name="submission_id" value="<?php echo $sub['id']; ?>">

                                            <div>
                                                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Verified View Count</label>
                                                <input type="number" name="verified_view_count" value="<?php echo $sub['view_count']; ?>" min="0"
                                                    class="w-full px-4 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                                            </div>

                                            <div class="flex gap-3">
                                                <button type="submit" name="verify_views" value="1" class="flex-1 px-4 py-2 bg-green-500 text-white font-bold rounded-xl hover:bg-green-600 transition">✓ Verify Views</button>
                                                <button type="button" onclick="showRejectForm(<?php echo $sub['id']; ?>)" class="flex-1 px-4 py-2 bg-red-500 text-white font-bold rounded-xl hover:bg-red-600 transition">✕ Reject Views</button>
                                            </div>
                                        </form>

                                        <form method="POST" id="reject-form-<?php echo $sub['id']; ?>" class="hidden space-y-4 mt-4">
                                            <input type="hidden" name="submission_id" value="<?php echo $sub['id']; ?>">
                                            <textarea name="rejection_reason" placeholder="Reason for rejection..." class="w-full px-4 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white"></textarea>
                                            <button type="submit" name="reject_views" value="1" class="w-full px-4 py-2 bg-red-500 text-white font-bold rounded-xl hover:bg-red-600 transition">Reject View Count</button>
                                        </form>
                                    <?php else: ?>
                                        <p class="text-[10px] font-bold text-green-600">Views verified: <?php echo number_format($sub['verified_view_count']); ?> on <?php echo date('M d, Y', strtotime($sub['views_verified_at'])); ?></p>
                                    <?php endif; ?>

                                    <form method="POST" class="mt-4" onsubmit="return confirm('Delete this submission and its uploaded files?');">
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

<script>
function showRejectForm(submissionId) {
    const form = document.getElementById(`reject-form-${submissionId}`);
    form.classList.toggle('hidden');
}
</script>

<?php include '../includes/footer.php'; ?>
