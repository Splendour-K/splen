<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_role('creator');

$stmt = $pdo->prepare("SELECT * FROM creators WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$creator = $stmt->fetch();
require_creator_record($creator);

// Handle stat update submission
$update_success = $update_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_stats') {
    $sub_id      = (int)($_POST['submission_id'] ?? 0);
    $link        = trim($_POST['posted_video_link'] ?? '');
    $plat        = trim($_POST['platform'] ?? '');
    $views       = (int)($_POST['view_count'] ?? 0);
    $engage      = (int)($_POST['engagement_count'] ?? 0);

    if ($sub_id > 0) {
        // Verify this submission belongs to this creator
        $check = $pdo->prepare("SELECT id FROM contest_submissions WHERE id = ? AND creator_id = ?");
        $check->execute([$sub_id, $creator['id']]);
        if ($check->fetchColumn()) {
            try {
                $pdo->prepare("UPDATE contest_submissions SET posted_video_link = ?, platform = ?, view_count = ?, engagement_count = ? WHERE id = ?")
                    ->execute([$link ?: null, $plat ?: null, $views, $engage, $sub_id]);
                $update_success = 'Stats updated!';
            } catch (Exception $e) {
                $update_error = 'Could not update stats: ' . $e->getMessage();
            }
        } else {
            $update_error = 'Submission not found.';
        }
    }
    header('Location: my-contests.php?tab=active&updated=1');
    exit();
}

$tab = $_GET['tab'] ?? 'active';

if ($tab === 'results') {
    $stmt = $pdo->prepare("
        SELECT cs.*, c.title as contest_title, c.submission_deadline, c.winner_announcement_date,
            b.brand_name AS company_name, cr.position_name, cr.reward_amount as prize_amount, cr.currency
        FROM contest_submissions cs
        JOIN contests c ON cs.contest_id = c.id
        JOIN brands b ON c.brand_id = b.id
        LEFT JOIN contest_rewards cr ON cs.contest_id = cr.contest_id
        WHERE cs.creator_id = ? AND (cs.status = 'winner' OR cs.status = 'shortlisted')
        ORDER BY cs.created_at DESC
    ");
    $stmt->execute([$creator['id']]);
    $results = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare("
        SELECT cs.*, c.title as contest_title, c.submission_deadline, c.status as contest_status, c.total_contest_budget, c.currency,
            b.brand_name AS company_name
        FROM contest_submissions cs
        JOIN contests c ON cs.contest_id = c.id
        JOIN brands b ON c.brand_id = b.id
        WHERE cs.creator_id = ? AND c.status IN ('live', 'closed')
        ORDER BY cs.created_at DESC
    ");
    $stmt->execute([$creator['id']]);
    $submissions = $stmt->fetchAll();
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
                    <h2 class="text-3xl font-bold text-gray-900 dark:text-white">My Contests</h2>
                    <p class="text-gray-600 dark:text-gray-400 mt-2">Track your contest entries and results</p>
                </div>
            </header>

            <?php if (!empty($_GET['updated'])): ?>
            <div class="p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-2xl text-green-800 dark:text-green-400 text-sm font-bold">
                ✓ Stats updated successfully!
            </div>
            <?php endif; ?>

            <div class="flex flex-wrap gap-2">
                <a href="?tab=active" class="px-4 py-2 rounded-xl font-bold text-sm <?php echo $tab === 'active' ? 'bg-secondary text-white' : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border border-gray-100 dark:border-gray-800'; ?>">My Entries</a>
                <a href="?tab=results" class="px-4 py-2 rounded-xl font-bold text-sm <?php echo $tab === 'results' ? 'bg-secondary text-white' : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border border-gray-100 dark:border-gray-800'; ?>">Wins & Shortlists</a>
                <a href="<?php echo APP_URL; ?>contest-board.php" class="px-4 py-2 rounded-xl font-bold text-sm bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border border-gray-100 dark:border-gray-800">Browse Contests →</a>
            </div>

            <?php
                // Helper: pick the best video path the creator should see for their own past work.
                // Creators see their own clean upload (no watermark — it's their work).
                $pick_creator_video = function($row) {
                    return $row['clean_video_file']
                        ?: ($row['video_file'] ?? null)
                        ?: ($row['watermarked_preview_file'] ?? null);
                };
            ?>

            <?php if ($tab === 'results'): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php foreach ($results as $result): ?>
                        <?php $video_src = $pick_creator_video($result); ?>
                        <div class="p-6 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm border-l-4 border-l-green-500 flex flex-col gap-4">
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
                                        <?php if ($result['status'] === 'winner'): ?>
                                            <span class="px-3 py-1 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 rounded-full text-[10px] font-black uppercase">🏆 <?php echo e($result['position_name']); ?></span>
                                        <?php else: ?>
                                            <span class="px-3 py-1 bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 rounded-full text-[10px] font-black uppercase">⭐ Shortlisted</span>
                                        <?php endif; ?>
                                    </div>
                                    <h3 class="text-lg font-black text-gray-900 dark:text-white truncate"><?php echo e($result['contest_title']); ?></h3>
                                    <p class="text-sm text-gray-500 mt-1 truncate"><?php echo e($result['company_name']); ?></p>
                                    <?php if ($result['status'] === 'winner' && $result['prize_amount']): ?>
                                        <p class="text-sm font-bold text-green-600 mt-2">Prize: <?php echo e($result['currency']); ?> <?php echo number_format((float)$result['prize_amount'], 2); ?></p>
                                    <?php endif; ?>
                                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest mt-2">Submitted <?php echo date('M d, Y', strtotime($result['created_at'])); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if (empty($results)): ?>
                        <div class="md:col-span-2 p-12 text-center bg-white dark:bg-gray-900 rounded-[2.5rem] border border-dashed border-gray-200 dark:border-gray-800 shadow-sm">
                            <p class="text-gray-400 mb-4">No wins or shortlists yet. Keep submitting!</p>
                            <a href="<?php echo APP_URL; ?>contests.php" class="text-secondary font-bold hover:underline">Browse active contests →</a>
                        </div>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php foreach ($submissions as $submission): ?>
                        <?php $video_src = $pick_creator_video($submission); ?>
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
                                        <span class="px-3 py-1 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-full text-[10px] font-black uppercase"><?php echo ucfirst(e($submission['status'])); ?></span>
                                        <span class="px-3 py-1 bg-secondary/10 text-secondary rounded-full text-[10px] font-black uppercase"><?php echo e($submission['currency']); ?> <?php echo number_format((float)$submission['total_contest_budget'], 2); ?> pool</span>
                                    </div>
                                    <h3 class="text-lg font-black text-gray-900 dark:text-white truncate"><?php echo e($submission['contest_title']); ?></h3>
                                    <p class="text-sm text-gray-500 mt-1 truncate"><?php echo e($submission['company_name']); ?></p>

                                    <?php if ($submission['contest_status'] === 'live'): ?>
                                        <p class="text-xs text-gray-400 mt-2">
                                            Deadline: <?php echo date('M d, Y', strtotime($submission['submission_deadline'])); ?>
                                        </p>
                                    <?php else: ?>
                                        <p class="text-xs text-gray-400 mt-2">
                                            Results: <?php echo date('M d, Y', strtotime($submission['submission_deadline'])); ?>
                                        </p>
                                    <?php endif; ?>

                                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest mt-2">Submitted <?php echo date('M d, Y', strtotime($submission['created_at'])); ?></p>

                                    <?php if ($submission['status'] === 'submitted'): ?>
                                        <div class="mt-3 p-2 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                                            <p class="text-xs font-bold text-blue-700 dark:text-blue-400">⏳ Awaiting review</p>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Current stats -->
                                    <?php if ($submission['view_count'] > 0 || $submission['posted_video_link']): ?>
                                    <div class="mt-3 flex flex-wrap gap-3 text-xs">
                                        <?php if ($submission['view_count'] > 0): ?>
                                        <span class="px-2 py-1 bg-gray-100 dark:bg-gray-800 rounded font-bold text-gray-700 dark:text-gray-300">👁 <?php echo number_format($submission['view_count']); ?> views</span>
                                        <?php endif; ?>
                                        <?php if ($submission['engagement_count'] > 0): ?>
                                        <span class="px-2 py-1 bg-gray-100 dark:bg-gray-800 rounded font-bold text-gray-700 dark:text-gray-300">💬 <?php echo number_format($submission['engagement_count']); ?> eng.</span>
                                        <?php endif; ?>
                                        <?php if ($submission['posted_video_link']): ?>
                                        <a href="<?php echo e($submission['posted_video_link']); ?>" target="_blank" rel="noopener" class="px-2 py-1 bg-secondary/10 text-secondary rounded font-bold">🔗 Posted</a>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Update Stats form (collapsible) -->
                            <div class="border-t border-gray-100 dark:border-gray-800 pt-4 mt-2">
                                <button type="button" onclick="this.nextElementSibling.classList.toggle('hidden')" class="text-xs font-bold text-secondary hover:underline">
                                    📊 Update Posted Stats
                                </button>
                                <form method="POST" class="hidden mt-3 space-y-3">
                                    <input type="hidden" name="action" value="update_stats">
                                    <input type="hidden" name="submission_id" value="<?php echo (int)$submission['id']; ?>">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        <div class="md:col-span-2">
                                            <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-1">Posted Video URL</label>
                                            <input type="url" name="posted_video_link" value="<?php echo e($submission['posted_video_link'] ?? ''); ?>" placeholder="https://tiktok.com/..." class="w-full px-3 py-2 text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg focus:outline-none focus:border-secondary">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-1">Platform</label>
                                            <select name="platform" class="w-full px-3 py-2 text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg focus:outline-none focus:border-secondary">
                                                <option value="">Select</option>
                                                <?php foreach (['tiktok'=>'TikTok','instagram'=>'Instagram','youtube'=>'YouTube','twitter'=>'Twitter/X','facebook'=>'Facebook','other'=>'Other'] as $val=>$lbl): ?>
                                                <option value="<?php echo $val; ?>" <?php echo ($submission['platform'] ?? '') === $val ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-1">Views</label>
                                            <input type="number" name="view_count" min="0" value="<?php echo (int)($submission['view_count'] ?? 0); ?>" class="w-full px-3 py-2 text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg focus:outline-none focus:border-secondary">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-1">Engagement</label>
                                            <input type="number" name="engagement_count" min="0" value="<?php echo (int)($submission['engagement_count'] ?? 0); ?>" class="w-full px-3 py-2 text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg focus:outline-none focus:border-secondary">
                                        </div>
                                    </div>
                                    <button type="submit" class="px-4 py-2 bg-secondary text-white font-bold text-xs rounded-lg hover:scale-105 transition">Save Stats</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if (empty($submissions)): ?>
                        <div class="md:col-span-2 p-12 text-center bg-white dark:bg-gray-900 rounded-[2.5rem] border border-dashed border-gray-200 dark:border-gray-800 shadow-sm">
                            <p class="text-gray-400 mb-4">You haven't submitted to any contests yet.</p>
                            <a href="<?php echo APP_URL; ?>contests.php" class="text-secondary font-bold hover:underline">Browse active contests →</a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
