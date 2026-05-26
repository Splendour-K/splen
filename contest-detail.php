<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

$contest_id = (int)($_GET['id'] ?? 0);
if (!$contest_id) { header('Location: ' . APP_URL . 'contest-board.php'); exit(); }

// Fetch contest + brand
$stmt = $pdo->prepare("
    SELECT c.*, b.brand_name AS company_name
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

$brand = null;
if ($logged_in && $role === 'brand') {
    $stmt = $pdo->prepare("SELECT * FROM brands WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $brand = $stmt->fetch() ?: null;
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

// Leaderboard
$sort            = $_GET['sort'] ?? 'top_ranked';
$leaderboard     = get_contest_board_data($contest_id, $sort);
$leaderboard_count = count($leaderboard);

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

        <main class="flex-1 space-y-8 min-w-0">

            <!-- Back link -->
            <a href="<?php echo APP_URL; ?>contest-board.php" class="inline-flex items-center gap-2 text-sm font-bold text-gray-500 hover:text-secondary transition">
                ← Back to Contest Board
            </a>

            <!-- ── Hero Card ─────────────────────────────────────────── -->
            <header class="bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm overflow-hidden">

                <!-- Featured Image Banner -->
                <?php if (!empty($contest['featured_image'])): ?>
                <div class="w-full overflow-hidden" style="aspect-ratio:21/6">
                    <img src="<?php echo APP_URL . e($contest['featured_image']); ?>" alt="<?php echo e($contest['title']); ?>" class="w-full h-full object-cover">
                </div>
                <?php endif; ?>

                <div class="p-8 relative">
                    <div aria-hidden="true" class="absolute top-0 right-0 -mr-20 -mt-20 w-80 h-80 bg-secondary/5 rounded-full blur-3xl pointer-events-none"></div>
                    <div class="relative">

                        <!-- Status + Brand + Category badges -->
                        <div class="flex flex-wrap items-center gap-3 mb-4">
                            <?php
                            $status_labels = [
                                'live'               => ['🟢 Live',               'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400'],
                                'closed'             => ['🔒 Closed',             'bg-gray-100 dark:bg-gray-800 text-gray-600'],
                                'completed'          => ['✅ Completed',           'bg-blue-100 dark:bg-blue-900/30 text-blue-700'],
                                'results_announced'  => ['📢 Results Announced',  'bg-purple-100 dark:bg-purple-900/30 text-purple-700'],
                            ];
                            [$status_text, $status_class] = $status_labels[$contest['status']] ?? ['Unknown', 'bg-gray-100 text-gray-600'];
                            ?>
                            <span class="px-3 py-1.5 <?php echo $status_class; ?> rounded-full text-[10px] font-black uppercase tracking-wider"><?php echo $status_text; ?></span>
                            <span class="text-sm text-gray-500 font-medium">by <strong class="text-gray-700 dark:text-gray-300"><?php echo e($contest['company_name']); ?></strong></span>
                            <?php if (!empty($contest['category'])): ?>
                            <span class="px-3 py-1.5 bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 rounded-full text-[10px] font-black uppercase"><?php echo e($contest['category']); ?></span>
                            <?php endif; ?>
                        </div>

                        <!-- Title -->
                        <h1 class="text-3xl md:text-4xl font-black text-gray-900 dark:text-white leading-tight mb-6"><?php echo e($contest['title']); ?></h1>

                        <!-- Key Stats Row -->
                        <div class="flex flex-wrap gap-3 mb-6">
                            <div class="flex items-center gap-3 px-5 py-3.5 bg-secondary/10 rounded-2xl">
                                <span class="text-xl">💰</span>
                                <div>
                                    <p class="text-[10px] font-black uppercase text-secondary/70 tracking-wider">Prize Pool</p>
                                    <p class="text-xl font-black text-secondary leading-none"><?php echo $prize_formatted; ?></p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 px-5 py-3.5 bg-gray-50 dark:bg-gray-800 rounded-2xl">
                                <span class="text-xl">⏰</span>
                                <div>
                                    <p class="text-[10px] font-black uppercase text-gray-400 tracking-wider">Deadline</p>
                                    <p class="text-base font-black text-gray-900 dark:text-white leading-none"><?php echo date('M d, Y', $deadline_ts); ?></p>
                                    <p class="text-[11px] font-bold <?php echo $diff_secs < 172800 ? 'text-red-500' : 'text-gray-500'; ?> mt-0.5"><?php echo $countdown_str; ?></p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 px-5 py-3.5 bg-gray-50 dark:bg-gray-800 rounded-2xl">
                                <span class="text-xl">🏆</span>
                                <div>
                                    <p class="text-[10px] font-black uppercase text-gray-400 tracking-wider">Winners</p>
                                    <p class="text-base font-black text-gray-900 dark:text-white leading-none"><?php echo (int)($contest['number_of_winners'] ?? 1); ?></p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 px-5 py-3.5 bg-gray-50 dark:bg-gray-800 rounded-2xl">
                                <span class="text-xl">📥</span>
                                <div>
                                    <p class="text-[10px] font-black uppercase text-gray-400 tracking-wider">Entries</p>
                                    <p class="text-base font-black text-gray-900 dark:text-white leading-none"><?php echo $leaderboard_count; ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- CTA Buttons -->
                        <div class="flex flex-wrap gap-3">
                            <?php if ($is_open): ?>
                                <?php if ($already_submitted): ?>
                                    <div class="inline-flex items-center gap-2 px-6 py-3 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 font-bold rounded-xl text-sm">
                                        <span>✓</span> You've already submitted to this contest
                                    </div>
                                <?php elseif ($creator): ?>
                                    <a href="<?php echo APP_URL; ?>creator/submit-to-contest.php?contest_id=<?php echo $contest_id; ?>" class="inline-flex items-center gap-2 px-8 py-3.5 bg-secondary text-white font-bold rounded-xl hover:scale-105 transition shadow-lg shadow-secondary/20 text-sm">
                                        Submit Your Entry ↗
                                    </a>
                                <?php elseif ($logged_in && $role === 'brand'): ?>
                                    <span class="px-6 py-3 bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 font-bold rounded-xl text-sm">Brands can't submit to contests</span>
                                <?php else: ?>
                                    <a href="<?php echo APP_URL; ?>login.php" class="inline-flex items-center gap-2 px-8 py-3.5 bg-secondary text-white font-bold rounded-xl hover:scale-105 transition shadow-lg shadow-secondary/20 text-sm">Login to Submit ↗</a>
                                    <a href="<?php echo APP_URL; ?>register.php" class="px-6 py-3.5 border border-secondary text-secondary font-bold rounded-xl hover:bg-secondary hover:text-white transition text-sm">Create Account</a>
                                <?php endif; ?>
                            <?php elseif ($contest['status'] === 'closed' || $contest['status'] === 'completed'): ?>
                                <div class="inline-flex items-center gap-2 px-5 py-3 bg-gray-100 dark:bg-gray-800 text-gray-500 font-bold rounded-xl text-sm">
                                    🔒 This contest is closed
                                </div>
                            <?php endif; ?>

                            <?php if ($role === 'brand' && isset($contest['brand_id']) && $contest['brand_id'] === ($brand['id'] ?? 0)): ?>
                                <a href="<?php echo APP_URL; ?>brand/contest-submissions.php?contest_id=<?php echo $contest_id; ?>" class="inline-flex items-center gap-2 px-6 py-3 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-bold rounded-xl hover:bg-gray-200 dark:hover:bg-gray-700 transition text-sm">
                                    📊 Review Submissions
                                </a>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>
            </header>

            <!-- ── Content + Info Sidebar ─────────────────────────── -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

                <!-- Left: Main Content (2/3) -->
                <div class="lg:col-span-2 space-y-6">

                    <!-- About This Contest -->
                    <?php if (!empty($contest['description'])): ?>
                    <div class="p-8 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                        <h3 class="text-xs font-black uppercase tracking-widest text-gray-400 mb-5">About This Contest</h3>
                        <div class="text-gray-700 dark:text-gray-300 leading-relaxed rich-content"><?php echo safe_rich_html($contest['description'] ?? ''); ?></div>
                    </div>
                    <?php endif; ?>

                    <!-- Rules & Requirements -->
                    <?php if (!empty($contest['terms_conditions'])): ?>
                    <div class="p-8 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                        <h3 class="text-xs font-black uppercase tracking-widest text-gray-400 mb-5">Rules & Requirements</h3>
                        <div class="text-gray-700 dark:text-gray-300 leading-relaxed rich-content"><?php echo safe_rich_html($contest['terms_conditions'] ?? ''); ?></div>
                    </div>
                    <?php endif; ?>

                    <!-- Empty state if no content -->
                    <?php if (empty($contest['description']) && empty($contest['terms_conditions'])): ?>
                    <div class="p-10 text-center bg-white dark:bg-gray-900 rounded-[2rem] border border-dashed border-gray-200 dark:border-gray-800">
                        <p class="text-gray-400 text-sm">No detailed description provided for this contest.</p>
                    </div>
                    <?php endif; ?>

                </div>

                <!-- Right: Info Sidebar (1/3) -->
                <div class="lg:col-span-1 space-y-5">

                    <!-- Prize Breakdown -->
                    <?php if (!empty($rewards)): ?>
                    <div class="p-6 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                        <h3 class="text-xs font-black uppercase tracking-widest text-gray-400 mb-4">Prize Breakdown</h3>
                        <div class="space-y-2.5">
                            <?php foreach ($rewards as $i => $reward):
                                $icons = ['🥇', '🥈', '🥉', '🏅', '🏅', '🏅'];
                                $icon = $icons[$i] ?? '🏅';
                            ?>
                            <div class="flex items-center justify-between p-3.5 bg-gray-50 dark:bg-gray-800 rounded-xl">
                                <div class="flex items-center gap-2.5">
                                    <span class="text-xl"><?php echo $icon; ?></span>
                                    <span class="font-bold text-gray-900 dark:text-white text-sm"><?php echo e($reward['position_name']); ?></span>
                                </div>
                                <span class="font-black text-secondary text-sm"><?php echo format_money((float)$reward['reward_amount'], $reward['currency']); ?></span>
                            </div>
                            <?php endforeach; ?>
                            <div class="flex items-center justify-between p-3.5 bg-secondary/5 rounded-xl border border-secondary/15 mt-3">
                                <span class="text-xs font-black uppercase tracking-widest text-gray-500">Total Prize Pool</span>
                                <span class="font-black text-secondary"><?php echo $prize_formatted; ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Bonus CPM Pay -->
                    <?php if (!empty($contest['pay_per_1000_views']) && (float)$contest['pay_per_1000_views'] > 0): ?>
                    <div class="p-6 bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800 rounded-[2rem]">
                        <h3 class="text-xs font-black uppercase tracking-widest text-indigo-600 dark:text-indigo-400 mb-3">💡 Bonus CPM Earnings</h3>
                        <p class="text-sm text-indigo-800 dark:text-indigo-200 font-medium mb-2">
                            Earn <strong><?php echo format_money((float)$contest['pay_per_1000_views'], $contest['currency']); ?></strong> per 1,000 verified views on your posted video.
                        </p>
                        <?php if (!empty($contest['max_payable_views_per_creator'])): ?>
                        <p class="text-xs text-indigo-600 dark:text-indigo-400">Capped at <?php echo number_format((int)$contest['max_payable_views_per_creator']); ?> views per creator.</p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Quick Info Card -->
                    <div class="p-6 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm space-y-4">
                        <h3 class="text-xs font-black uppercase tracking-widest text-gray-400">Quick Info</h3>
                        <div class="space-y-3 text-sm">
                            <?php if (!empty($contest['category'])): ?>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-500 font-medium">Category</span>
                                <span class="font-bold text-gray-900 dark:text-white capitalize"><?php echo e($contest['category']); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-500 font-medium">Deadline</span>
                                <span class="font-bold text-gray-900 dark:text-white"><?php echo date('M d, Y', $deadline_ts); ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-500 font-medium">Entries</span>
                                <span class="font-bold text-gray-900 dark:text-white"><?php echo $leaderboard_count; ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-500 font-medium">Winners</span>
                                <span class="font-bold text-gray-900 dark:text-white"><?php echo (int)($contest['number_of_winners'] ?? 1); ?></span>
                            </div>
                            <?php if (!empty($contest['winner_announcement_date'])): ?>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-500 font-medium">Results</span>
                                <span class="font-bold text-gray-900 dark:text-white"><?php echo date('M d, Y', strtotime($contest['winner_announcement_date'])); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-500 font-medium">Brand</span>
                                <span class="font-bold text-gray-900 dark:text-white"><?php echo e($contest['company_name']); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Submission Tips (if contest is open) -->
                    <?php if ($is_open): ?>
                    <div class="p-5 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-[2rem]">
                        <p class="text-sm font-black text-amber-800 dark:text-amber-300 mb-3">💡 Submission Tips</p>
                        <ul class="space-y-2 text-xs font-medium text-amber-800 dark:text-amber-200">
                            <li class="flex items-start gap-2"><span class="text-amber-500 flex-shrink-0">•</span> Upload an unwatermarked file — we watermark it for brand preview.</li>
                            <li class="flex items-start gap-2"><span class="text-amber-500 flex-shrink-0">•</span> Report your real view counts — fake engagement causes disqualification.</li>
                            <li class="flex items-start gap-2"><span class="text-amber-500 flex-shrink-0">•</span> One submission per creator per contest.</li>
                        </ul>
                    </div>
                    <?php endif; ?>

                </div>
            </div>

            <!-- ── Leaderboard (Full Width) ─────────────────────────── -->
            <div class="bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm p-8">
                <!-- Header -->
                <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
                    <div>
                        <h2 class="text-xl font-black text-gray-900 dark:text-white">
                            Leaderboard
                            <span class="ml-2 text-sm font-bold text-gray-400"><?php echo $leaderboard_count; ?> entries</span>
                        </h2>
                        <p class="text-xs text-gray-400 mt-1">Ranked by engagement and views</p>
                    </div>
                    <div class="flex flex-wrap gap-2 text-xs">
                        <?php
                        $lsorts = [
                            'top_ranked'   => '🏆 Top',
                            'most_viewed'  => '👁 Most Viewed',
                            'most_engaged' => '💬 Engaged',
                            'newest'       => '✨ Newest',
                        ];
                        foreach ($lsorts as $lv => $ll):
                            $q = '?id=' . $contest_id . '&sort=' . $lv;
                        ?>
                        <a href="<?php echo $q; ?>" class="px-3 py-1.5 rounded-xl font-bold transition <?php echo $sort === $lv ? 'bg-secondary text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:border-secondary/50'; ?>">
                            <?php echo $ll; ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if (!empty($leaderboard)): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach ($leaderboard as $rank => $entry):
                        $rank_num    = $rank + 1;
                        $is_winner   = $entry['status'] === 'winner';
                        $is_short    = $entry['status'] === 'shortlisted';
                        $preview     = $entry['watermarked_preview_file'] ?? ($entry['video_file'] ?? null);
                        $entry_title = !empty($entry['title']) ? $entry['title'] : (!empty($entry['submission_note']) ? mb_substr($entry['submission_note'], 0, 60) : 'Untitled Entry');
                    ?>
                    <div class="flex gap-4 p-5 bg-gray-50 dark:bg-gray-800 rounded-2xl border <?php echo $is_winner ? 'border-amber-400/60 shadow-sm shadow-amber-500/10' : ($is_short ? 'border-blue-300/50' : 'border-transparent'); ?>">
                        <!-- Rank -->
                        <div class="flex-shrink-0 flex flex-col items-center justify-start pt-1 w-8">
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
                        <div class="flex-shrink-0 w-14 h-20 rounded-xl overflow-hidden bg-gray-200 dark:bg-gray-700 relative">
                            <?php if ($preview): ?>
                                <video class="w-full h-full object-cover" preload="none" muted playsinline>
                                    <source src="<?php echo APP_URL . ltrim(e($preview), '/'); ?>" type="video/mp4">
                                </video>
                                <div class="absolute inset-0 flex items-center justify-center bg-black/30">
                                    <span class="text-white text-base">▶</span>
                                </div>
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center text-gray-400 text-2xl">🎬</div>
                            <?php endif; ?>
                        </div>

                        <!-- Details -->
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-1.5 mb-1">
                                <?php if ($is_winner): ?>
                                    <span class="px-2 py-0.5 bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 rounded text-[9px] font-black uppercase">🏆 Winner</span>
                                <?php elseif ($is_short): ?>
                                    <span class="px-2 py-0.5 bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 rounded text-[9px] font-black uppercase">⭐ Shortlisted</span>
                                <?php endif; ?>
                            </div>

                            <h4 class="font-bold text-gray-900 dark:text-white text-sm leading-snug truncate"><?php echo e($entry_title); ?></h4>
                            <p class="text-xs text-gray-500 mt-0.5"><?php echo e($entry['full_name']); ?><?php if ($entry['school']): ?> · <?php echo e($entry['school']); ?><?php endif; ?></p>

                            <div class="flex flex-wrap gap-3 mt-2.5">
                                <div>
                                    <span class="text-[10px] font-bold text-gray-400 uppercase">Views</span>
                                    <span class="ml-1 text-sm font-black text-gray-900 dark:text-white"><?php echo number_format((int)$entry['view_count']); ?></span>
                                </div>
                                <div>
                                    <span class="text-[10px] font-bold text-gray-400 uppercase">Eng.</span>
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
                <div class="py-16 text-center">
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

        </main>
    </div>
</div>

<style>
.rich-content h2 { font-size: 1.15em; font-weight: 700; margin-top: 1em; margin-bottom: .3em; }
.rich-content h3 { font-size: 1.05em; font-weight: 600; margin-top: .8em; margin-bottom: .2em; }
.rich-content p  { margin-bottom: .5em; }
.rich-content ul, .rich-content ol { padding-left: 1.4em; margin-bottom: .5em; }
.rich-content li { margin-bottom: .25em; }
.rich-content blockquote { border-left: 4px solid #ea580c; padding-left: 1em; opacity: .8; margin: .5em 0; }
.rich-content a  { color: #ea580c; text-decoration: underline; }
.rich-content strong { font-weight: 700; }
.rich-content em { font-style: italic; }
</style>

<?php include 'includes/footer.php'; ?>
