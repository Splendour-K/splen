<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

$contest_id = (int)($_GET['id'] ?? 0);
if (!$contest_id) { header('Location: ' . APP_URL . 'contest-board.php'); exit(); }

// Fetch contest + brand
$stmt = $pdo->prepare("
    SELECT c.*, b.brand_name AS company_name, b.logo_url AS brand_logo
    FROM contests c
    JOIN brands b ON c.brand_id = b.id
    WHERE c.id = ?
");
$stmt->execute([$contest_id]);
$contest = $stmt->fetch();
if (!$contest) { header('Location: ' . APP_URL . 'contest-board.php'); exit(); }

// Auth state
$logged_in = isset($_SESSION['user_id']);
$role      = $_SESSION['role'] ?? '';
$creator   = null;

if ($logged_in && $role === 'creator') {
    $stmt = $pdo->prepare("SELECT * FROM creators WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $creator = $stmt->fetch() ?: null;
}

// Check if this creator already submitted
$already_submitted = false;
if ($creator) {
    $stmt = $pdo->prepare("SELECT id FROM contest_submissions WHERE contest_id = ? AND creator_id = ?");
    $stmt->execute([$contest_id, $creator['id']]);
    $already_submitted = (bool)$stmt->fetchColumn();
}

// Prize rewards breakdown
$rewards = get_contest_rewards($contest_id);

// Leaderboard (all submissions ranked by views)
$sort = $_GET['sort'] ?? 'top_ranked';
$leaderboard = get_contest_board_data($contest_id, $sort);

// Deadline status
$deadline_ts  = strtotime($contest['submission_deadline']);
$is_open      = ($contest['status'] === 'live' && $deadline_ts > time());
$diff_secs    = max(0, $deadline_ts - time());
$days_left    = floor($diff_secs / 86400);
$hours_left   = floor(($diff_secs % 86400) / 3600);
$mins_left    = floor(($diff_secs % 3600) / 60);

if ($diff_secs <= 0)           $countdown_str = 'Deadline passed';
elseif ($days_left > 0)        $countdown_str = $days_left . 'd ' . $hours_left . 'h remaining';
elseif ($hours_left > 0)       $countdown_str = $hours_left . 'h ' . $mins_left . 'm remaining';
else                           $countdown_str = $mins_left . 'm remaining';

$prize_formatted = format_money((float)$contest['total_contest_budget'], $contest['currency']);

include 'includes/header.php';
?>

<div class="pt-24 min-h-screen bg-gray-50 dark:bg-gray-950">
    <div class="max-w-7xl mx-auto px-6 md:px-12 <?php echo ($role === 'creator' || $role === 'brand') ? 'flex flex-col md:flex-row gap-8' : ''; ?> py-8">

        <?php
        if ($role === 'brand')        include 'includes/brand_sidebar.php';
        elseif ($role === 'creator')  include 'includes/creator_sidebar.php';
        ?>

        <main class="flex-1 space-y-8">
            <!-- Back link -->
            <a href="<?php echo APP_URL; ?>contest-board.php" class="inline-flex items-center gap-2 text-sm font-bold text-gray-500 hover:text-secondary transition">
                ← Back to Contest Board
            </a>

            <!-- Hero Card -->
            <header class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm relative overflow-hidden">
                <div aria-hidden="true" class="absolute top-0 right-0 -mr-20 -mt-20 w-80 h-80 bg-secondary/5 rounded-full blur-3xl pointer-events-none"></div>
                <div class="relative">
                    <!-- Status + brand -->
                    <div class="flex flex-wrap items-center gap-3 mb-4">
                        <?php
                        $status_labels = [
                            'live'               => ['🟢 Live', 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400'],
                            'closed'             => ['🔒 Closed', 'bg-gray-100 dark:bg-gray-800 text-gray-600'],
                            'completed'          => ['✅ Completed', 'bg-blue-100 dark:bg-blue-900/30 text-blue-700'],
                            'results_announced'  => ['📢 Results Announced', 'bg-purple-100 dark:bg-purple-900/30 text-purple-700'],
                        ];
                        [$status_text, $status_class] = $status_labels[$contest['status']] ?? ['Unknown', 'bg-gray-100 text-gray-600'];
                        ?>
                        <span class="px-3 py-1.5 <?php echo $status_class; ?> rounded-full text-[10px] font-black uppercase tracking-wider"><?php echo $status_text; ?></span>
                        <span class="text-sm text-gray-500 font-medium">by <?php echo e($contest['company_name']); ?></span>
                        <?php if (!empty($contest['category'])): ?>
                        <span class="px-3 py-1.5 bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 rounded-full text-[10px] font-black uppercase"><?php echo e($contest['category']); ?></span>
                        <?php endif; ?>
                    </div>

                    <h1 class="text-3xl md:text-4xl font-black text-gray-900 dark:text-white leading-tight mb-6"><?php echo e($contest['title']); ?></h1>

                    <!-- Key stats -->
                    <div class="flex flex-wrap gap-4">
                        <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-2xl min-w-[120px]">
                            <p class="text-[10px] font-black uppercase text-gray-400 mb-1">Prize Pool</p>
                            <p class="text-2xl font-black text-secondary"><?php echo $prize_formatted; ?></p>
                        </div>
                        <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-2xl min-w-[120px]">
                            <p class="text-[10px] font-black uppercase text-gray-400 mb-1">Deadline</p>
                            <p class="text-lg font-black text-gray-900 dark:text-white"><?php echo date('M d, Y', $deadline_ts); ?></p>
                            <p class="text-[11px] font-bold <?php echo $diff_secs < 172800 ? 'text-red-500' : 'text-gray-500'; ?>"><?php echo $countdown_str; ?></p>
                        </div>
                        <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-2xl min-w-[120px]">
                            <p class="text-[10px] font-black uppercase text-gray-400 mb-1">Winners</p>
                            <p class="text-lg font-black text-gray-900 dark:text-white"><?php echo (int)($contest['number_of_winners'] ?? 1); ?></p>
                        </div>
                        <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-2xl min-w-[120px]">
                            <p class="text-[10px] font-black uppercase text-gray-400 mb-1">Submissions</p>
                            <p class="text-lg font-black text-gray-900 dark:text-white"><?php echo count($leaderboard); ?></p>
                        </div>
                    </div>

                    <!-- CTA -->
                    <div class="mt-6 flex flex-wrap gap-3">
                        <?php if ($is_open): ?>
                            <?php if ($already_submitted): ?>
                                <div class="px-6 py-3 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 font-bold rounded-xl text-sm">
                                    ✓ You've already submitted to this contest
                                </div>
                            <?php elseif ($creator): ?>
                                <a href="<?php echo APP_URL; ?>creator/submit-to-contest.php?contest_id=<?php echo $contest_id; ?>" class="px-8 py-3 bg-secondary text-white font-bold rounded-xl hover:scale-105 transition shadow-lg shadow-secondary/20">
                                    Submit Your Entry ↗
                                </a>
                            <?php elseif ($logged_in): ?>
                                <span class="px-6 py-3 bg-gray-200 dark:bg-gray-800 text-gray-500 font-bold rounded-xl text-sm">Brands can't submit to contests</span>
                            <?php else: ?>
                                <a href="<?php echo APP_URL; ?>login.php" class="px-8 py-3 bg-secondary text-white font-bold rounded-xl hover:scale-105 transition shadow-lg shadow-secondary/20">Login to Submit</a>
                                <a href="<?php echo APP_URL; ?>register.php" class="px-6 py-3 border border-secondary text-secondary font-bold rounded-xl hover:bg-secondary hover:text-white transition">Create Account</a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </header>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Left: Brief + Rules -->
                <div class="lg:col-span-1 space-y-6">
                    <!-- About -->
                    <?php if (!empty($contest['description'])): ?>
                    <div class="p-6 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                        <h3 class="text-sm font-black uppercase tracking-widest text-gray-400 mb-4">About This Contest</h3>
                        <p class="text-gray-700 dark:text-gray-300 text-sm leading-relaxed"><?php echo nl2br(e($contest['description'])); ?></p>
                    </div>
                    <?php endif; ?>

                    <!-- Prize Breakdown -->
                    <?php if (!empty($rewards)): ?>
                    <div class="p-6 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                        <h3 class="text-sm font-black uppercase tracking-widest text-gray-400 mb-4">Prize Breakdown</h3>
                        <div class="space-y-3">
                            <?php foreach ($rewards as $i => $reward):
                                $icons = ['🥇', '🥈', '🥉', '🏅', '🏅'];
                                $icon = $icons[$i] ?? '🏅';
                            ?>
                            <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-xl">
                                <div class="flex items-center gap-2">
                                    <span class="text-xl"><?php echo $icon; ?></span>
                                    <span class="font-bold text-gray-900 dark:text-white text-sm"><?php echo e($reward['position_name']); ?></span>
                                </div>
                                <span class="font-black text-secondary text-sm"><?php echo format_money((float)$reward['reward_amount'], $reward['currency']); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- CPM Info -->
                    <?php if (!empty($contest['pay_per_1000_views']) && (float)$contest['pay_per_1000_views'] > 0): ?>
                    <div class="p-6 bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800 rounded-[2rem]">
                        <h3 class="text-sm font-black uppercase tracking-widest text-indigo-600 dark:text-indigo-400 mb-3">💡 Bonus CPM Pay</h3>
                        <p class="text-sm text-indigo-800 dark:text-indigo-200 font-medium mb-2">
                            Earn <strong><?php echo format_money((float)$contest['pay_per_1000_views'], $contest['currency']); ?></strong> per 1,000 verified views on your posted video.
                        </p>
                        <?php if (!empty($contest['max_payable_views_per_creator'])): ?>
                        <p class="text-xs text-indigo-600 dark:text-indigo-400">Capped at <?php echo number_format((int)$contest['max_payable_views_per_creator']); ?> views per creator.</p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Rules -->
                    <?php if (!empty($contest['terms_conditions'])): ?>
                    <div class="p-6 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                        <h3 class="text-sm font-black uppercase tracking-widest text-gray-400 mb-4">Rules & Requirements</h3>
                        <div class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed whitespace-pre-line"><?php echo e($contest['terms_conditions']); ?></div>
                    </div>
                    <?php endif; ?>

                    <!-- Submission tip -->
                    <?php if ($is_open): ?>
                    <div class="p-5 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-2xl text-sm text-amber-800 dark:text-amber-200">
                        <p class="font-bold mb-1">💡 Submission tips</p>
                        <ul class="list-disc list-inside space-y-1 text-xs font-medium">
                            <li>Upload an unwatermarked file — we watermark it for the brand preview.</li>
                            <li>Report your real view counts — fake engagement causes disqualification.</li>
                            <li>One submission per creator per contest.</li>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Right: Leaderboard -->
                <div class="lg:col-span-2 space-y-6">
                    <div>
                        <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
                            <h2 class="text-xl font-black text-gray-900 dark:text-white">
                                Leaderboard
                                <span class="ml-2 text-sm font-bold text-gray-500">(<?php echo count($leaderboard); ?> entries)</span>
                            </h2>
                            <div class="flex gap-2 text-sm">
                                <?php
                                $lsorts = ['top_ranked' => '🏆 Top', 'most_viewed' => '👁 Most Viewed', 'most_engaged' => '💬 Engaged', 'newest' => '✨ Newest'];
                                foreach ($lsorts as $lv => $ll):
                                    $q = '?id=' . $contest_id . '&sort=' . $lv;
                                ?>
                                <a href="<?php echo $q; ?>" class="px-3 py-1.5 rounded-xl font-bold transition <?php echo $sort === $lv ? 'bg-secondary text-white' : 'bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700 hover:border-secondary/50'; ?>">
                                    <?php echo $ll; ?>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <?php if (!empty($leaderboard)): ?>
                        <div class="space-y-4">
                            <?php foreach ($leaderboard as $rank => $entry):
                                $rank_num   = $rank + 1;
                                $is_winner  = $entry['status'] === 'winner';
                                $is_short   = $entry['status'] === 'shortlisted';
                                $preview    = $entry['watermarked_preview_file'] ?? ($entry['video_file'] ?? null);
                                $entry_title = !empty($entry['title']) ? $entry['title'] : (!empty($entry['submission_note']) ? mb_substr($entry['submission_note'], 0, 60) : 'Untitled Entry');
                            ?>
                            <div class="p-5 bg-white dark:bg-gray-900 rounded-[2rem] border <?php echo $is_winner ? 'border-amber-400/60 shadow-lg shadow-amber-500/10' : ($is_short ? 'border-blue-300/60' : 'border-gray-100 dark:border-gray-800'); ?> shadow-sm flex gap-5">
                                <!-- Rank -->
                                <div class="flex-shrink-0 flex flex-col items-center justify-center w-10">
                                    <?php if ($rank_num === 1 && $is_winner): ?>
                                        <span class="text-2xl">🥇</span>
                                    <?php elseif ($rank_num === 2 && $is_winner): ?>
                                        <span class="text-2xl">🥈</span>
                                    <?php elseif ($rank_num === 3 && $is_winner): ?>
                                        <span class="text-2xl">🥉</span>
                                    <?php else: ?>
                                        <span class="text-sm font-black text-gray-400">#<?php echo $rank_num; ?></span>
                                    <?php endif; ?>
                                </div>

                                <!-- Preview thumbnail -->
                                <div class="flex-shrink-0 w-16 h-24 rounded-xl overflow-hidden bg-gray-200 dark:bg-gray-800 relative">
                                    <?php if ($preview): ?>
                                        <video class="w-full h-full object-cover" preload="none" muted playsinline>
                                            <source src="<?php echo APP_URL . ltrim(e($preview), '/'); ?>" type="video/mp4">
                                        </video>
                                        <div class="absolute inset-0 flex items-center justify-center bg-black/30">
                                            <span class="text-white text-lg">▶</span>
                                        </div>
                                    <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center text-gray-400 text-2xl">🎬</div>
                                    <?php endif; ?>
                                </div>

                                <!-- Details -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap items-center gap-2 mb-1">
                                        <?php if ($is_winner): ?>
                                            <span class="px-2 py-0.5 bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 rounded text-[9px] font-black uppercase">🏆 Winner</span>
                                        <?php elseif ($is_short): ?>
                                            <span class="px-2 py-0.5 bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 rounded text-[9px] font-black uppercase">⭐ Shortlisted</span>
                                        <?php endif; ?>
                                    </div>

                                    <h4 class="font-bold text-gray-900 dark:text-white text-sm leading-snug truncate"><?php echo e($entry_title); ?></h4>
                                    <p class="text-xs text-gray-500 mt-0.5"><?php echo e($entry['full_name']); ?><?php if ($entry['school']): ?> · <?php echo e($entry['school']); ?><?php endif; ?></p>

                                    <div class="flex flex-wrap gap-4 mt-2">
                                        <div>
                                            <span class="text-[10px] font-bold text-gray-400 uppercase">Views</span>
                                            <span class="ml-1 text-sm font-black text-gray-900 dark:text-white"><?php echo number_format((int)$entry['view_count']); ?></span>
                                        </div>
                                        <div>
                                            <span class="text-[10px] font-bold text-gray-400 uppercase">Engagement</span>
                                            <span class="ml-1 text-sm font-black text-gray-900 dark:text-white"><?php echo number_format((int)$entry['engagement_count']); ?></span>
                                        </div>
                                        <div>
                                            <span class="text-[10px] font-bold text-gray-400 uppercase">Submitted</span>
                                            <span class="ml-1 text-sm font-black text-gray-900 dark:text-white"><?php echo time_ago($entry['submitted_at'] ?: $entry['created_at']); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <?php else: ?>
                        <div class="p-16 text-center bg-white dark:bg-gray-900 rounded-[2.5rem] border border-dashed border-gray-200 dark:border-gray-800 shadow-sm">
                            <div class="text-5xl mb-4">🎬</div>
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">No submissions yet</h3>
                            <p class="text-gray-400 text-sm mb-6">Be the first to enter this contest!</p>
                            <?php if ($is_open && $creator): ?>
                            <a href="<?php echo APP_URL; ?>creator/submit-to-contest.php?contest_id=<?php echo $contest_id; ?>" class="px-6 py-3 bg-secondary text-white font-bold rounded-xl hover:scale-105 transition">Submit Now</a>
                            <?php elseif ($is_open && !$logged_in): ?>
                            <a href="<?php echo APP_URL; ?>login.php" class="px-6 py-3 bg-secondary text-white font-bold rounded-xl hover:scale-105 transition">Login to Submit</a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
