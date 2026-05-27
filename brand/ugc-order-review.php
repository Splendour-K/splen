<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_role('brand');

$ugc_order_id = (int)($_GET['order_id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM brands WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$brand = $stmt->fetch();
require_brand_record($brand);

$stmt = $pdo->prepare("SELECT * FROM ugc_orders WHERE id = ? AND brand_id = ?");
$stmt->execute([$ugc_order_id, $brand['id']]);
$ugc_order = $stmt->fetch();

if (!$ugc_order) {
    die("Campaign not found.");
}

$sort = $_GET['sort'] ?? 'newest';
$sort_sql = match($sort) {
    'most_viewed'   => 'ORDER BY us.view_count DESC, us.created_at DESC',
    'most_engaged'  => 'ORDER BY us.engagement_count DESC, us.created_at DESC',
    'approved'      => 'ORDER BY (us.status = "approved") DESC, us.created_at DESC',
    default         => 'ORDER BY us.created_at DESC'
};

$stmt = $pdo->prepare("
    SELECT us.*, cr.full_name, cr.school, cr.profile_photo,
        (SELECT COUNT(*) FROM ugc_order_submissions WHERE status = 'submitted' AND ugc_order_id = ?) AS pending_count
    FROM ugc_order_submissions us
    JOIN creators cr ON us.creator_id = cr.id
    WHERE us.ugc_order_id = ?
    $sort_sql
");
$stmt->execute([$ugc_order_id, $ugc_order_id]);
$submissions = $stmt->fetchAll();

$message = $_GET['message'] ?? '';
$error   = $_GET['error']   ?? '';

include '../includes/header.php';
?>

<div class="pt-24 min-h-screen bg-gray-50 dark:bg-gray-950">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-12 flex flex-col md:flex-row gap-8 py-8">
        <?php include '../includes/brand_sidebar.php'; ?>

        <main class="flex-1 space-y-6 min-w-0">

            <!-- Header -->
            <header class="p-6 md:p-8 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Campaign Review</p>
                        <h2 class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-white"><?php echo e($ugc_order['title']); ?></h2>
                        <p class="text-gray-500 text-sm mt-1">Review video submissions — approve to release payment, or request revisions with feedback.</p>
                    </div>
                    <a href="ugc-orders.php" class="text-sm font-bold text-gray-500 hover:text-primary transition flex-shrink-0">← Campaigns</a>
                </div>
            </header>

            <!-- Alerts -->
            <?php if ($message): ?>
                <div class="p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl text-green-800 dark:text-green-400 font-bold text-sm flex items-center gap-2">
                    ✓ <?php echo e(urldecode($message)); ?>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl text-red-800 dark:text-red-400 font-bold text-sm flex items-center gap-2">
                    ⚠ <?php echo e(urldecode($error)); ?>
                </div>
            <?php endif; ?>

            <!-- Sort Bar -->
            <div class="flex flex-wrap gap-2">
                <?php foreach (['newest' => 'Newest', 'most_viewed' => 'Most Viewed', 'most_engaged' => 'Most Engaged', 'approved' => 'Approved First'] as $val => $label): ?>
                    <a href="?order_id=<?php echo $ugc_order_id; ?>&sort=<?php echo $val; ?>"
                       class="px-4 py-2 rounded-xl font-bold text-sm <?php echo $sort === $val ? 'bg-primary text-white' : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border border-gray-100 dark:border-gray-800'; ?>">
                        <?php echo $label; ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Submissions -->
            <div class="space-y-4">
                <?php foreach ($submissions as $submission): ?>
                    <?php
                    $status    = $submission['status'];
                    $preview   = $submission['watermarked_preview_file'] ?: $submission['video_file'];
                    $sub_id    = (int)$submission['id'];
                    $creator   = e($submission['full_name']);
                    ?>
                    <div class="bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm overflow-hidden" id="submission-<?php echo $sub_id; ?>">

                        <!-- Status bar -->
                        <?php
                        $bar = match($status) {
                            'approved'           => 'bg-green-500',
                            'rejected'           => 'bg-red-500',
                            'revision_requested' => 'bg-orange-400',
                            default              => 'bg-primary',
                        };
                        ?>
                        <div class="h-1 w-full <?php echo $bar; ?>"></div>

                        <div class="p-5 md:p-6 flex flex-col md:flex-row gap-5">

                            <!-- Video preview -->
                            <div class="w-full md:w-56 flex-shrink-0">
                                <div class="aspect-[9/16] md:aspect-video rounded-2xl overflow-hidden bg-black relative">
                                    <?php if ($preview): ?>
                                        <video class="w-full h-full object-cover" controls controlsList="nodownload" preload="metadata" playsinline>
                                            <source src="<?php echo APP_URL . ltrim(e($preview), '/'); ?>" type="video/mp4">
                                        </video>
                                        <?php if ($status !== 'approved'): ?>
                                            <div class="absolute top-2 right-2 px-2 py-0.5 bg-black/70 text-white text-[9px] font-black uppercase tracking-wider rounded pointer-events-none">Watermarked</div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center text-4xl text-gray-500">📹</div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Content -->
                            <div class="flex-1 min-w-0">
                                <!-- Meta -->
                                <div class="flex flex-wrap items-center gap-2 mb-3">
                                    <?php
                                    $badge_classes = [
                                        'approved'           => 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400',
                                        'rejected'           => 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400',
                                        'revision_requested' => 'bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-400',
                                        'submitted'          => 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400',
                                    ];
                                    $badge = $badge_classes[$status] ?? 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400';
                                    $status_labels = [
                                        'approved'           => '✓ Approved',
                                        'rejected'           => '✕ Rejected',
                                        'revision_requested' => '↩ Revision Requested',
                                        'submitted'          => '⏳ Awaiting Review',
                                    ];
                                    $status_label = $status_labels[$status] ?? ucfirst(str_replace('_', ' ', $status));
                                    ?>
                                    <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase <?php echo $badge; ?>"><?php echo $status_label; ?></span>
                                    <?php if ($submission['view_count'] > 0): ?>
                                        <span class="px-2 py-1 bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 rounded-full text-[10px] font-black">👁 <?php echo number_format($submission['view_count']); ?></span>
                                    <?php endif; ?>
                                </div>

                                <p class="font-bold text-gray-900 dark:text-white"><?php echo $creator; ?></p>
                                <?php if ($submission['school']): ?>
                                    <p class="text-xs text-gray-500"><?php echo e($submission['school']); ?></p>
                                <?php endif; ?>
                                <p class="text-xs text-gray-400 mt-1">Submitted <?php echo date('M d, Y · H:i', strtotime($submission['created_at'])); ?></p>

                                <!-- Existing feedback (shown to brand) -->
                                <?php if ($submission['brand_feedback'] && in_array($status, ['revision_requested', 'rejected'])): ?>
                                    <div class="mt-3 p-3 bg-gray-50 dark:bg-gray-800 rounded-xl border-l-4 <?php echo $status === 'rejected' ? 'border-red-400' : 'border-orange-400'; ?>">
                                        <p class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">Your Feedback</p>
                                        <p class="text-sm text-gray-700 dark:text-gray-300"><?php echo e($submission['brand_feedback']); ?></p>
                                    </div>
                                <?php endif; ?>

                                <!-- Action buttons / forms -->
                                <div class="mt-4">
                                    <?php if ($status === 'submitted' || $status === 'under_review'): ?>

                                        <!-- Approve -->
                                        <form method="POST" action="<?php echo APP_URL; ?>api/ugc-actions.php" class="inline">
                                            <input type="hidden" name="submission_id" value="<?php echo $sub_id; ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="px-5 py-2.5 bg-green-600 text-white font-bold rounded-xl text-sm hover:scale-105 transition shadow-sm">
                                                Approve & Pay
                                            </button>
                                        </form>

                                        <!-- Revision trigger -->
                                        <button type="button"
                                                onclick="toggleFeedbackForm(<?php echo $sub_id; ?>, 'revision')"
                                                class="ml-2 px-5 py-2.5 bg-orange-500 text-white font-bold rounded-xl text-sm hover:scale-105 transition">
                                            Request Revision
                                        </button>

                                        <!-- Reject trigger -->
                                        <button type="button"
                                                onclick="toggleFeedbackForm(<?php echo $sub_id; ?>, 'reject')"
                                                class="ml-2 px-5 py-2.5 bg-red-600 text-white font-bold rounded-xl text-sm hover:scale-105 transition">
                                            Reject
                                        </button>

                                        <!-- Inline feedback form (hidden until button clicked) -->
                                        <div id="feedback-form-<?php echo $sub_id; ?>" class="hidden mt-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700">
                                            <p id="feedback-label-<?php echo $sub_id; ?>" class="text-sm font-bold text-gray-700 dark:text-gray-300 mb-2"></p>
                                            <form method="POST" action="<?php echo APP_URL; ?>api/ugc-actions.php">
                                                <input type="hidden" name="submission_id" value="<?php echo $sub_id; ?>">
                                                <input type="hidden" id="feedback-action-<?php echo $sub_id; ?>" name="action" value="">
                                                <textarea name="feedback" required rows="3" placeholder="Provide clear, specific feedback the creator can act on..."
                                                    class="w-full px-4 py-3 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-sm text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-primary resize-none mb-3"></textarea>
                                                <div class="flex items-center gap-3">
                                                    <button type="submit" id="feedback-submit-<?php echo $sub_id; ?>"
                                                            class="px-5 py-2 text-white font-bold rounded-xl text-sm hover:scale-105 transition bg-gray-900">
                                                        Send Feedback
                                                    </button>
                                                    <button type="button" onclick="toggleFeedbackForm(<?php echo $sub_id; ?>, null)"
                                                            class="px-4 py-2 text-gray-500 font-bold rounded-xl text-sm hover:bg-gray-200 dark:hover:bg-gray-700 transition">
                                                        Cancel
                                                    </button>
                                                </div>
                                            </form>
                                        </div>

                                    <?php elseif ($status === 'approved'): ?>
                                        <span class="inline-flex items-center gap-1.5 px-4 py-2 bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400 font-bold rounded-xl text-sm border border-green-200 dark:border-green-800">
                                            ✓ Payment Released
                                        </span>
                                    <?php elseif ($status === 'rejected'): ?>
                                        <span class="inline-flex items-center gap-1.5 px-4 py-2 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 font-bold rounded-xl text-sm border border-red-200 dark:border-red-800">
                                            ✕ Rejected
                                        </span>
                                    <?php elseif ($status === 'revision_requested'): ?>
                                        <span class="inline-flex items-center gap-1.5 px-4 py-2 bg-orange-50 dark:bg-orange-900/20 text-orange-700 dark:text-orange-400 font-bold rounded-xl text-sm border border-orange-200 dark:border-orange-800">
                                            ↩ Awaiting Revision
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if (empty($submissions)): ?>
                    <div class="p-12 text-center bg-white dark:bg-gray-900 rounded-[2.5rem] border border-dashed border-gray-200 dark:border-gray-800">
                        <p class="text-gray-400 text-sm">No submissions yet. Creators will submit their videos here once the campaign is live.</p>
                    </div>
                <?php endif; ?>
            </div>

        </main>
    </div>
</div>

<script>
function toggleFeedbackForm(subId, type) {
    const wrap   = document.getElementById('feedback-form-' + subId);
    const label  = document.getElementById('feedback-label-' + subId);
    const action = document.getElementById('feedback-action-' + subId);
    const btn    = document.getElementById('feedback-submit-' + subId);

    if (!type) {
        wrap.classList.add('hidden');
        return;
    }

    if (type === 'revision') {
        label.textContent  = 'Describe what changes are needed (required):';
        action.value       = 'request_revision';
        btn.textContent    = 'Send Revision Request';
        btn.className      = btn.className.replace(/bg-\S+/, 'bg-orange-500');
    } else {
        label.textContent  = 'Explain why this submission is being rejected (required):';
        action.value       = 'reject';
        btn.textContent    = 'Confirm Rejection';
        btn.className      = btn.className.replace(/bg-\S+/, 'bg-red-600');
    }

    wrap.classList.remove('hidden');
    wrap.querySelector('textarea').focus();
}
</script>

<?php include '../includes/footer.php'; ?>
