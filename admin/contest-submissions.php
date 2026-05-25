<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_role('admin');

$filter = $_GET['filter'] ?? 'pending';
$search = $_GET['search'] ?? '';

// ── Handle Delete ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_submission_id'])) {
    $delete_id = (int)$_POST['delete_submission_id'];
    $stmt = $pdo->prepare("SELECT video_file, watermarked_preview_file, clean_video_file FROM contest_submissions WHERE id = ?");
    $stmt->execute([$delete_id]);
    $media = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($media) {
        delete_uploaded_file_path($media['video_file']);
        delete_uploaded_file_path($media['watermarked_preview_file']);
        delete_uploaded_file_path($media['clean_video_file']);
        $pdo->prepare("DELETE FROM contest_submissions WHERE id = ?")->execute([$delete_id]);
    }

    redirect('admin/contest-submissions.php?message=Submission+deleted');
    exit;
}

// ── Handle Mark Winner ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_winner_submission_id'])) {
    $submission_id = (int)$_POST['mark_winner_submission_id'];
    $winner_position = (int)($_POST['winner_position'] ?? 0);

    $stmt_info = $pdo->prepare("
        SELECT cs.contest_id, cr.user_id as creator_user_id, c.title as contest_title
        FROM contest_submissions cs
        JOIN creators cr ON cs.creator_id = cr.id
        JOIN contests c ON cs.contest_id = c.id
        WHERE cs.id = ?
    ");
    $stmt_info->execute([$submission_id]);
    $sub_info = $stmt_info->fetch();

    if ($sub_info && $winner_position > 0) {
        try {
            $pdo->prepare("UPDATE contest_submissions SET status = 'winner', winner_position = ? WHERE id = ?")
                ->execute([$winner_position, $submission_id]);
        } catch (Exception $e) {
            // winner_position column may not exist yet — fall back
            $pdo->prepare("UPDATE contest_submissions SET status = 'winner' WHERE id = ?")
                ->execute([$submission_id]);
        }

        create_notification(
            $sub_info['creator_user_id'],
            '🏆 You\'ve Won!',
            'Congratulations! You\'ve been selected as a winner in "' . $sub_info['contest_title'] . '"!',
            'contest_winner',
            'creator/my-contests.php',
            'contest',
            $sub_info['contest_id']
        );
        log_activity($_SESSION['user_id'], 'Contest Winner Selected', 'Submission #' . $submission_id . ' marked as winner');
        redirect('admin/contest-submissions.php?filter=all&message=Winner+selected+successfully');
    }
    exit;
}

// ── Handle Release Payment ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['release_payment_submission_id'])) {
    $submission_id = (int)$_POST['release_payment_submission_id'];
    $payment_amount = (float)($_POST['payment_amount'] ?? 0);
    $payment_note   = trim($_POST['payment_note'] ?? '');

    $stmt_info = $pdo->prepare("
        SELECT cs.creator_id, cs.contest_id, c.currency, c.title as contest_title,
               cr.user_id as creator_user_id
        FROM contest_submissions cs
        JOIN contests c ON cs.contest_id = c.id
        JOIN creators cr ON cs.creator_id = cr.id
        WHERE cs.id = ?
    ");
    $stmt_info->execute([$submission_id]);
    $sub_info = $stmt_info->fetch();

    if ($sub_info && $payment_amount > 0) {
        try {
            $currency = $sub_info['currency'] ?: 'GHS';
            $pdo->prepare("
                INSERT INTO payments (creator_id, amount, currency, payment_type, status, contest_submission_id, transaction_id)
                VALUES (?, ?, ?, 'contest_reward', 'completed', ?, ?)
            ")->execute([
                $sub_info['creator_id'],
                $payment_amount,
                $currency,
                $submission_id,
                'CONTEST_' . $submission_id . '_' . time()
            ]);

            // Mark payment released on submission (graceful — column may not exist yet)
            try {
                $pdo->prepare("UPDATE contest_submissions SET payment_released = 1 WHERE id = ?")
                    ->execute([$submission_id]);
            } catch (Exception $inner) { /* column not yet migrated */ }

            create_notification(
                $sub_info['creator_user_id'],
                '💸 Prize Payment Released!',
                'Your prize of ' . ($sub_info['currency'] ?: 'GHS') . ' ' . number_format($payment_amount, 2) . ' for "' . $sub_info['contest_title'] . '" has been released!',
                'payment',
                'creator/earnings.php',
                'payment',
                $submission_id
            );
            log_activity($_SESSION['user_id'], 'Contest Prize Released', 'Payment of ' . $payment_amount . ' for submission #' . $submission_id);
            redirect('admin/contest-submissions.php?filter=all&message=Prize+payment+released+successfully');
        } catch (Exception $e) {
            redirect('admin/contest-submissions.php?filter=all&message=' . urlencode('Error: ' . $e->getMessage()));
        }
    }
    exit;
}

// ── Main Query ────────────────────────────────────────────────
$sql = "
    SELECT cs.*, c.title as contest_title, c.brand_id, c.currency as contest_currency,
        cr.full_name as creator_name, cr.school,
        b.brand_name AS company_name, b.user_id as brand_user_id
    FROM contest_submissions cs
    JOIN contests c ON cs.contest_id = c.id
    JOIN creators cr ON cs.creator_id = cr.id
    JOIN brands b ON c.brand_id = b.id
    WHERE 1=1
";

if ($filter === 'pending') {
    $sql .= " AND cs.status = 'submitted'";
} elseif ($filter === 'flagged') {
    $sql .= " AND cs.flag_reason IS NOT NULL";
} elseif ($filter === 'winners') {
    $sql .= " AND cs.status = 'winner'";
}

if ($search) {
    $sql .= " AND (c.title LIKE ? OR cr.full_name LIKE ?)";
}

$sql .= " ORDER BY cs.created_at DESC";

$stmt = $pdo->prepare($sql);
if ($search) {
    $search_param = '%' . $search . '%';
    $stmt->execute([$search_param, $search_param]);
} else {
    $stmt->execute();
}
$submissions = $stmt->fetchAll();

// ── Pre-load all contest rewards (keyed by contest_id) ─────────
$all_rewards = [];
try {
    foreach ($pdo->query("SELECT * FROM contest_rewards ORDER BY contest_id, position_number ASC")->fetchAll() as $r) {
        $all_rewards[$r['contest_id']][] = $r;
    }
} catch (Exception $e) { /* table may not exist */ }

include '../includes/header.php';
?>

<div class="pt-24 min-h-screen bg-gray-50 dark:bg-gray-950">
    <div class="max-w-7xl mx-auto px-6 md:px-12 flex flex-col md:flex-row gap-8 py-8">
        <?php include 'dashboard_sidebar.php'; ?>

        <main class="flex-1 space-y-8">
            <header class="p-8 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm relative overflow-hidden">
                <div aria-hidden="true" class="absolute top-0 right-0 -mr-16 -mt-16 w-64 h-64 bg-secondary/5 rounded-full blur-3xl"></div>
                <div class="relative">
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Admin — Contests</p>
                    <h2 class="text-3xl font-bold text-gray-900 dark:text-white mt-1">Contest Submissions</h2>
                    <p class="text-gray-600 dark:text-gray-400 mt-2">Review submissions, select winners, and release prizes. Only admins can mark winners.</p>
                </div>
            </header>

            <?php if (!empty($_GET['message'])): ?>
                <div class="p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-900 rounded-2xl text-green-800 dark:text-green-300 text-sm font-medium">
                    ✓ <?php echo e($_GET['message']); ?>
                </div>
            <?php endif; ?>

            <!-- Search + Filters -->
            <div class="space-y-4">
                <form method="GET" class="flex gap-2">
                    <input type="hidden" name="filter" value="<?php echo e($filter); ?>">
                    <input type="text" name="search" placeholder="Search creator or contest..." value="<?php echo e($search); ?>" class="flex-1 px-4 py-3 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary">
                    <button type="submit" class="px-6 py-3 bg-secondary text-white font-bold rounded-xl hover:scale-105 transition">Search</button>
                </form>

                <div class="flex flex-wrap gap-2">
                    <?php
                    $tabs = ['pending' => 'Pending Review', 'all' => 'All Submissions', 'winners' => '🏆 Winners', 'flagged' => 'Flagged'];
                    foreach ($tabs as $key => $label):
                    ?>
                        <a href="?filter=<?php echo $key; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>"
                           class="px-4 py-2 rounded-xl font-bold text-sm <?php echo $filter === $key ? 'bg-secondary text-white' : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border border-gray-100 dark:border-gray-800'; ?>">
                            <?php echo $label; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Submissions List -->
            <div class="space-y-4">
                <?php foreach ($submissions as $submission):
                    $card_rewards   = $all_rewards[$submission['contest_id']] ?? [];
                    $is_winner      = $submission['status'] === 'winner';
                    $is_disqualified = $submission['status'] === 'disqualified';
                    $payment_released = !empty($submission['payment_released']);
                ?>
                    <div class="p-6 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                        <div class="flex flex-col lg:flex-row gap-6">

                            <!-- Video Player -->
                            <div class="w-full lg:w-48 flex-shrink-0">
                                <?php $preview_src = $submission['watermarked_preview_file'] ?? ($submission['video_file'] ?? null); ?>
                                <?php if ($preview_src): ?>
                                    <div class="w-full aspect-[9/16] bg-black rounded-2xl overflow-hidden">
                                        <video class="w-full h-full object-cover" controls preload="metadata" playsinline controlsList="nodownload">
                                            <source src="<?php echo APP_URL . ltrim(e($preview_src), '/'); ?>" type="video/mp4">
                                        </video>
                                    </div>
                                <?php else: ?>
                                    <div class="w-full aspect-[9/16] bg-gray-200 dark:bg-gray-800 rounded-2xl flex items-center justify-center text-4xl">🎬</div>
                                <?php endif; ?>
                            </div>

                            <!-- Details -->
                            <div class="flex-1 space-y-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="px-3 py-1 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-full text-[10px] font-black uppercase">
                                        <?php echo ucfirst(str_replace('_', ' ', $submission['status'])); ?>
                                    </span>
                                    <?php if ($is_winner): ?>
                                        <span class="px-3 py-1 bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400 rounded-full text-[10px] font-black uppercase">
                                            🏆 Winner <?php echo !empty($submission['winner_position']) ? '#' . $submission['winner_position'] : ''; ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($payment_released): ?>
                                        <span class="px-3 py-1 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 rounded-full text-[10px] font-black uppercase">✓ Paid</span>
                                    <?php endif; ?>
                                    <?php if ($submission['flag_reason']): ?>
                                        <span class="px-3 py-1 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 rounded-full text-[10px] font-black uppercase">⚠ Flagged</span>
                                    <?php endif; ?>
                                </div>

                                <h3 class="text-xl font-black text-gray-900 dark:text-white"><?php echo e($submission['contest_title']); ?></h3>
                                <p class="text-sm text-gray-500"><?php echo e($submission['company_name']); ?> · By <strong><?php echo e($submission['creator_name']); ?></strong></p>

                                <?php if ($submission['school']): ?>
                                    <p class="text-xs text-gray-400">School: <?php echo e($submission['school']); ?></p>
                                <?php endif; ?>

                                <div class="grid grid-cols-3 gap-3 py-2">
                                    <div>
                                        <p class="text-[10px] font-bold text-gray-400 uppercase">Views</p>
                                        <p class="text-base font-black text-gray-900 dark:text-white"><?php echo number_format($submission['view_count'] ?? 0); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-bold text-gray-400 uppercase">Engagement</p>
                                        <p class="text-base font-black text-gray-900 dark:text-white"><?php echo number_format($submission['engagement_count'] ?? 0); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-bold text-gray-400 uppercase">Submitted</p>
                                        <p class="text-base font-black text-gray-900 dark:text-white"><?php echo date('M d', strtotime($submission['created_at'])); ?></p>
                                    </div>
                                </div>

                                <?php if ($submission['flag_reason']): ?>
                                    <div class="p-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-xl">
                                        <p class="text-xs font-bold text-yellow-700 dark:text-yellow-400">Flag: <?php echo e($submission['flag_reason']); ?></p>
                                    </div>
                                <?php endif; ?>

                                <?php if ($submission['submission_note']): ?>
                                    <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-xl">
                                        <p class="text-xs font-bold text-gray-500 mb-1">Creator Note</p>
                                        <p class="text-sm text-gray-700 dark:text-gray-300"><?php echo e($submission['submission_note']); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Actions Column -->
                            <div class="lg:w-56 space-y-3 flex-shrink-0">

                                <!-- Standard Moderation Actions -->
                                <form method="POST" onsubmit="return confirm('Delete this submission permanently?');" class="mb-1">
                                    <input type="hidden" name="delete_submission_id" value="<?php echo $submission['id']; ?>">
                                    <button type="submit" class="w-full px-4 py-2 bg-red-50 text-red-600 font-bold rounded-xl text-xs hover:bg-red-600 hover:text-white transition">
                                        🗑 Delete
                                    </button>
                                </form>

                                <?php if (!$is_winner && !$is_disqualified && $submission['status'] === 'submitted' && !$submission['flag_reason']): ?>
                                    <form method="POST" action="../api/contest-actions.php">
                                        <input type="hidden" name="submission_id" value="<?php echo $submission['id']; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white font-bold rounded-xl text-xs hover:bg-blue-700 transition">
                                            ✓ Approve
                                        </button>
                                    </form>
                                    <form method="POST" action="../api/contest-actions.php">
                                        <input type="hidden" name="submission_id" value="<?php echo $submission['id']; ?>">
                                        <input type="hidden" name="action" value="flag">
                                        <input type="hidden" name="flag_reason" value="Policy violation — requires review">
                                        <button type="submit" class="w-full px-4 py-2 bg-orange-500 text-white font-bold rounded-xl text-xs hover:bg-orange-600 transition">
                                            🚩 Flag
                                        </button>
                                    </form>
                                <?php elseif ($submission['flag_reason']): ?>
                                    <form method="POST" action="../api/contest-actions.php">
                                        <input type="hidden" name="submission_id" value="<?php echo $submission['id']; ?>">
                                        <input type="hidden" name="action" value="resolve_flag">
                                        <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white font-bold rounded-xl text-xs hover:bg-blue-700 transition">
                                            Resolve Flag
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <!-- ── Winner Selection (Admin-only) ── -->
                                <?php if (!$is_winner && !$is_disqualified): ?>
                                    <div class="border-t border-gray-100 dark:border-gray-800 pt-3">
                                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">Select Winner</p>
                                        <form method="POST" class="space-y-2">
                                            <input type="hidden" name="mark_winner_submission_id" value="<?php echo $submission['id']; ?>">
                                            <?php if (!empty($card_rewards)): ?>
                                                <select name="winner_position" required class="w-full px-3 py-2 text-xs border border-gray-200 dark:border-gray-700 rounded-xl bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                                                    <option value="">Choose position…</option>
                                                    <?php foreach ($card_rewards as $reward): ?>
                                                        <option value="<?php echo $reward['position_number']; ?>">
                                                            <?php echo e($reward['position_name']); ?> — <?php echo e($submission['contest_currency'] ?? ''); ?> <?php echo number_format($reward['reward_amount']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" class="w-full px-4 py-2 bg-green-600 text-white font-bold rounded-xl text-xs hover:bg-green-700 transition">
                                                    🏆 Mark as Winner
                                                </button>
                                            <?php else: ?>
                                                <button type="submit" name="winner_position" value="1" class="w-full px-4 py-2 bg-green-600 text-white font-bold rounded-xl text-xs hover:bg-green-700 transition">
                                                    🏆 Mark as Winner
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    </div>
                                <?php endif; ?>

                                <!-- ── Prize Payment Release ── -->
                                <?php if ($is_winner): ?>
                                    <div class="border-t border-gray-100 dark:border-gray-800 pt-3">
                                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">Prize Payment</p>
                                        <?php if (!$payment_released): ?>
                                            <form method="POST" class="space-y-2" onsubmit="return confirm('Release prize payment for this creator?');">
                                                <input type="hidden" name="release_payment_submission_id" value="<?php echo $submission['id']; ?>">
                                                <input type="number" step="0.01" min="0.01" name="payment_amount" placeholder="Amount (<?php echo e($submission['contest_currency'] ?? 'GHS'); ?>)" required class="w-full px-3 py-2 text-xs border border-gray-200 dark:border-gray-700 rounded-xl bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                                                <input type="text" name="payment_note" placeholder="Note (optional)" class="w-full px-3 py-2 text-xs border border-gray-200 dark:border-gray-700 rounded-xl bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                                                <button type="submit" class="w-full px-4 py-2 bg-purple-600 text-white font-bold rounded-xl text-xs hover:bg-purple-700 transition">
                                                    💸 Release Payment
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="block px-4 py-2 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 font-bold rounded-xl text-xs text-center">
                                                ✓ Payment Released
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (empty($submissions)): ?>
                <div class="p-12 text-center bg-white dark:bg-gray-900 rounded-[2.5rem] border border-dashed border-gray-200 dark:border-gray-800 shadow-sm">
                    <p class="text-gray-400">No submissions found.</p>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
