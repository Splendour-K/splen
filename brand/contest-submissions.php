<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_role('brand');

$stmt = $pdo->prepare("SELECT * FROM brands WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$brand = $stmt->fetch();
require_brand_record($brand);

$contest_id = (int)($_GET['contest_id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM contests WHERE id = ? AND brand_id = ?");
$stmt->execute([$contest_id, $brand['id']]);
$contest = $stmt->fetch();

if (!$contest) {
    redirect('../brand/my-contests.php');
}

$error = '';
$success = '';

// Handle submission actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $submission_id = (int)($_POST['submission_id'] ?? 0);
    $action = $_POST['action'];

    $stmt = $pdo->prepare("
        SELECT cs.*, cr.full_name, cr.school FROM contest_submissions cs
        JOIN creators cr ON cs.creator_id = cr.id
        WHERE cs.id = ? AND cs.contest_id = ? AND cs.creator_id IN (
            SELECT id FROM creators WHERE user_id IN (SELECT user_id FROM users WHERE id = ?)
        )
    ");

    if ($action === 'shortlist') {
        $stmt = $pdo->prepare("UPDATE contest_submissions SET status = 'shortlisted' WHERE id = ? AND contest_id = ?");
        if ($stmt->execute([$submission_id, $contest_id])) {
            $success = 'Submission shortlisted.';
            log_activity($_SESSION['user_id'], 'Contest Submission Shortlisted', 'Contest: ' . $contest['title']);
        }
    } elseif ($action === 'unshortlist') {
        $stmt = $pdo->prepare("UPDATE contest_submissions SET status = 'submitted' WHERE id = ? AND contest_id = ?");
        if ($stmt->execute([$submission_id, $contest_id])) {
            $success = 'Submission removed from shortlist.';
        }
    } elseif ($action === 'mark_winner') {
        $winner_position = (int)($_POST['winner_position'] ?? 0);
        $approved_views = (int)($_POST['approved_views'] ?? 0);

        if ($winner_position <= 0) {
            $error = 'Invalid winner position.';
        } else {
            $pdo->beginTransaction();
            try {
                // Update submission status
                $stmt = $pdo->prepare("UPDATE contest_submissions SET status = 'winner', approved_views = ? WHERE id = ? AND contest_id = ?");
                $stmt->execute([$approved_views, $submission_id, $contest_id]);

                // Get reward amount for this position
                $stmt_reward = $pdo->prepare("SELECT reward_amount, currency FROM contest_rewards WHERE contest_id = ? AND position_number = ?");
                $stmt_reward->execute([$contest_id, $winner_position]);
                $reward = $stmt_reward->fetch();

                if ($reward) {
                    // Create payment for contest reward
                    $stmt_payment = $pdo->prepare("
                        SELECT creator_id FROM contest_submissions WHERE id = ?
                    ");
                    $stmt_payment->execute([$submission_id]);
                    $creator_id = $stmt_payment->fetchColumn();

                    $stmt_create_payment = $pdo->prepare("
                        INSERT INTO payments (creator_id, amount, currency, status, payment_type, contest_submission_id)
                        VALUES (?, ?, ?, 'pending', 'contest_reward', ?)
                    ");
                    $stmt_create_payment->execute([
                        $creator_id,
                        $reward['reward_amount'],
                        $reward['currency'],
                        $submission_id
                    ]);
                }

                $pdo->commit();
                $success = 'Winner selected and payment created.';
                log_activity($_SESSION['user_id'], 'Contest Winner Selected', 'Contest: ' . $contest['title'] . ', Position: ' . $winner_position);
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Error selecting winner: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'approve_views') {
        $approved_views = (int)($_POST['approved_views'] ?? 0);

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE contest_submissions SET approved_views = ? WHERE id = ? AND contest_id = ?");
            $stmt->execute([$approved_views, $submission_id, $contest_id]);

            // If CPM budget exists, calculate and create CPM payment
            if ($contest['cpm_budget'] > 0 && $contest['pay_per_1000_views'] > 0) {
                $cpm_payment = calculate_cpm_payment($approved_views, $contest['pay_per_1000_views'], $contest['max_payable_views_per_creator']);

                if ($cpm_payment > 0) {
                    $stmt_creator = $pdo->prepare("SELECT creator_id FROM contest_submissions WHERE id = ?");
                    $stmt_creator->execute([$submission_id]);
                    $creator_id = $stmt_creator->fetchColumn();

                    $stmt_payment = $pdo->prepare("
                        INSERT INTO payments (creator_id, amount, currency, status, payment_type, contest_submission_id)
                        VALUES (?, ?, ?, 'pending', 'contest_cpm', ?)
                    ");
                    $stmt_payment->execute([
                        $creator_id,
                        $cpm_payment,
                        $contest['currency'],
                        $submission_id
                    ]);
                }
            }

            $pdo->commit();
            $success = 'Views approved and payment calculated.';
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error approving views: ' . $e->getMessage();
        }
    } elseif ($action === 'disqualify') {
        $stmt = $pdo->prepare("UPDATE contest_submissions SET status = 'disqualified' WHERE id = ? AND contest_id = ?");
        if ($stmt->execute([$submission_id, $contest_id])) {
            $success = 'Submission disqualified.';
            log_activity($_SESSION['user_id'], 'Contest Submission Disqualified', 'Contest: ' . $contest['title']);
        }
    }
}

// Get submissions
$sort = $_GET['sort'] ?? 'newest';
$submissions = get_contest_board_data($contest_id, $sort);

// Get rewards
$rewards = get_contest_rewards($contest_id);

include '../includes/header.php';
?>

<div class="pt-24 min-h-screen bg-gray-50 dark:bg-gray-950">
    <div class="max-w-7xl mx-auto px-6 md:px-12 flex flex-col md:flex-row gap-8 py-8">
        <?php include '../includes/brand_sidebar.php'; ?>

        <main class="flex-1 space-y-8">
            <header class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Contest review</p>
                <h2 class="text-3xl font-bold text-gray-900 dark:text-white mt-2"><?php echo e($contest['title']); ?></h2>
                <p class="text-gray-600 dark:text-gray-400 mt-2">Review submissions, shortlist entries, and select winners.</p>
            </header>

            <?php if (!empty($success)): ?>
                <div class="p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-900 rounded-2xl text-green-800 dark:text-green-300 text-sm font-medium">
                    ✓ <?php echo e($success); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-900 rounded-2xl text-red-800 dark:text-red-300 text-sm font-medium">
                    ✕ <?php echo e($error); ?>
                </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="flex flex-wrap gap-2">
                <a href="?contest_id=<?php echo $contest_id; ?>&sort=newest" class="px-4 py-2 rounded-xl font-bold text-sm <?php echo $sort === 'newest' ? 'bg-secondary text-white' : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border border-gray-100 dark:border-gray-800'; ?>">Newest</a>
                <a href="?contest_id=<?php echo $contest_id; ?>&sort=most_viewed" class="px-4 py-2 rounded-xl font-bold text-sm <?php echo $sort === 'most_viewed' ? 'bg-secondary text-white' : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border border-gray-100 dark:border-gray-800'; ?>">Most Viewed</a>
                <a href="?contest_id=<?php echo $contest_id; ?>&sort=shortlisted" class="px-4 py-2 rounded-xl font-bold text-sm <?php echo $sort === 'shortlisted' ? 'bg-secondary text-white' : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border border-gray-100 dark:border-gray-800'; ?>">Shortlisted</a>
                <a href="?contest_id=<?php echo $contest_id; ?>&sort=winners" class="px-4 py-2 rounded-xl font-bold text-sm <?php echo $sort === 'winners' ? 'bg-secondary text-white' : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border border-gray-100 dark:border-gray-800'; ?>">Winners</a>
            </div>

            <!-- Submissions Grid -->
            <div class="grid grid-cols-1 gap-6">
                <?php foreach ($submissions as $sub): ?>
                    <div class="p-6 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                        <div class="flex flex-col md:flex-row gap-6">
                            <!-- In-page Video Player (watermarked preview until winner selection) -->
                            <div class="w-full md:w-56">
                                <?php
                                    $preview_src = $sub['watermarked_preview_file'] ?? ($sub['video_file'] ?? null);
                                ?>
                                <?php if ($preview_src): ?>
                                    <div class="w-full aspect-[9/16] bg-black rounded-2xl overflow-hidden relative">
                                        <video class="w-full h-full object-cover" controls controlsList="nodownload" preload="metadata" playsinline>
                                            <source src="<?php echo APP_URL . ltrim(e($preview_src), '/'); ?>" type="video/mp4">
                                            Your browser does not support the video tag.
                                        </video>
                                        <?php if (($sub['status'] ?? '') !== 'winner'): ?>
                                            <div class="absolute top-2 right-2 px-2 py-1 bg-black/70 text-white text-[9px] font-black uppercase tracking-widest rounded-md pointer-events-none">Watermarked</div>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="w-full aspect-[9/16] bg-gray-200 dark:bg-gray-800 rounded-2xl flex items-center justify-center">
                                        <span class="text-gray-400 text-sm">No preview</span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Submission Details -->
                            <div class="flex-1 space-y-4">
                                <div>
                                    <div class="flex items-center gap-3 mb-2">
                                        <img src="<?php echo e($sub['profile_photo'] ?? '/images/default-avatar.png'); ?>" alt="<?php echo e($sub['creator_name']); ?>" class="w-10 h-10 rounded-full object-cover">
                                        <div>
                                            <p class="font-bold text-gray-900 dark:text-white"><?php echo e($sub['creator_name']); ?></p>
                                            <p class="text-xs text-gray-500"><?php echo e($sub['school']); ?></p>
                                        </div>
                                    </div>
                                    <span class="inline-block px-3 py-1 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-full text-[10px] font-black uppercase mt-2">
                                        <?php echo ucfirst(str_replace('_', ' ', $sub['status'])); ?>
                                    </span>
                                </div>

                                <div class="grid grid-cols-3 gap-4">
                                    <div>
                                        <p class="text-[10px] font-bold text-gray-400 uppercase">Views</p>
                                        <p class="text-lg font-black text-gray-900 dark:text-white"><?php echo number_format($sub['view_count']); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-bold text-gray-400 uppercase">Engagement</p>
                                        <p class="text-lg font-black text-gray-900 dark:text-white"><?php echo number_format($sub['engagement_count']); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-bold text-gray-400 uppercase">Submitted</p>
                                        <p class="text-lg font-black text-gray-900 dark:text-white"><?php echo time_ago($sub['submitted_at']); ?></p>
                                    </div>
                                </div>

                                <?php if ($sub['submission_note']): ?>
                                    <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-xl">
                                        <p class="text-xs font-bold text-gray-600 dark:text-gray-400 mb-1">Note</p>
                                        <p class="text-sm text-gray-700 dark:text-gray-300"><?php echo e($sub['submission_note']); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Actions -->
                            <div class="md:w-64 space-y-3">
                                <?php if ($sub['status'] !== 'winner' && $sub['status'] !== 'disqualified'): ?>
                                    <form method="POST" class="space-y-2">
                                        <input type="hidden" name="submission_id" value="<?php echo $sub['id']; ?>">

                                        <?php if ($sub['status'] !== 'shortlisted'): ?>
                                            <button type="submit" name="action" value="shortlist" class="w-full px-4 py-2 bg-blue-600 text-white text-sm font-bold rounded-xl hover:bg-blue-700 transition">
                                                ⭐ Shortlist
                                            </button>
                                        <?php else: ?>
                                            <button type="submit" name="action" value="unshortlist" class="w-full px-4 py-2 bg-gray-300 text-gray-700 text-sm font-bold rounded-xl hover:bg-gray-400 transition">
                                                ☆ Remove
                                            </button>
                                        <?php endif; ?>

                                        <!-- Winner Selection -->
                                        <div class="border-t border-gray-200 dark:border-gray-700 pt-2">
                                            <select name="winner_position" class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                                                <option value="">Select Winner Position...</option>
                                                <?php foreach ($rewards as $reward): ?>
                                                    <option value="<?php echo $reward['position_number']; ?>">
                                                        <?php echo e($reward['position_name']); ?> - <?php echo e($contest['currency']); ?> <?php echo number_format($reward['reward_amount']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <input type="hidden" name="approved_views" value="<?php echo $sub['view_count']; ?>">
                                            <button type="submit" name="action" value="mark_winner" class="w-full mt-2 px-4 py-2 bg-green-600 text-white text-sm font-bold rounded-xl hover:bg-green-700 transition">
                                                🏆 Mark as Winner
                                            </button>
                                        </div>

                                        <button type="submit" name="action" value="disqualify" class="w-full px-4 py-2 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 text-sm font-bold rounded-xl hover:bg-red-200 dark:hover:bg-red-900/50 transition">
                                            Disqualify
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($sub['status'] === 'winner' && $contest['cpm_budget'] > 0): ?>
                                    <div class="border-t border-gray-200 dark:border-gray-700 pt-2">
                                        <p class="text-xs font-bold text-gray-600 dark:text-gray-400 mb-2">Approve CPM Views</p>
                                        <form method="POST" class="space-y-2">
                                            <input type="hidden" name="submission_id" value="<?php echo $sub['id']; ?>">
                                            <input type="number" name="approved_views" value="<?php echo $sub['approved_views'] ?? $sub['view_count']; ?>" class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                                            <button type="submit" name="action" value="approve_views" class="w-full px-4 py-2 bg-purple-600 text-white text-sm font-bold rounded-xl hover:bg-purple-700 transition">
                                                ✓ Approve Views
                                            </button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (empty($submissions)): ?>
                <div class="p-12 text-center bg-white dark:bg-gray-900 rounded-[2.5rem] border border-dashed border-gray-200 dark:border-gray-800 shadow-sm">
                    <p class="text-gray-400 text-sm">No submissions yet.</p>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
