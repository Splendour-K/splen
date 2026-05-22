<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_role('creator');

$stmt = $pdo->prepare("SELECT * FROM creators WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$creator = $stmt->fetch();

$creator_id = $creator['id'];

// Profile check
$profile_status = check_profile_completion($creator);

// Metrics
// Pending Applications
$stmt = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE creator_id = ? AND status = 'pending'");
$stmt->execute([$creator_id]);
$pending_apps = $stmt->fetchColumn();

// Approved Applications (Shortlisted or Approved)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE creator_id = ? AND status IN ('shortlisted', 'approved')");
$stmt->execute([$creator_id]);
$approved_apps = $stmt->fetchColumn();

// Active Jobs
$stmt = $pdo->prepare("SELECT COUNT(*) FROM jobs WHERE creator_id = ? AND status NOT IN ('completed', 'disputed')");
$stmt->execute([$creator_id]);
$active_jobs = $stmt->fetchColumn();

// Total Earnings
$stmt = $pdo->prepare("SELECT SUM(calculated_amount) FROM payments WHERE creator_id = ? AND status = 'completed'");
$stmt->execute([$creator_id]);
$total_earnings = $stmt->fetchColumn() ?: 0;

// Contest Stats
$stmt = $pdo->prepare("SELECT COUNT(*) FROM contest_submissions WHERE creator_id = ?");
$stmt->execute([$creator_id]);
$contest_entries = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM contest_submissions WHERE creator_id = ? AND status = 'winner'");
$stmt->execute([$creator_id]);
$contest_wins = $stmt->fetchColumn();

// UGC Stats
$stmt = $pdo->prepare("SELECT COUNT(*) FROM ugc_order_submissions WHERE creator_id = ?");
$stmt->execute([$creator_id]);
$ugc_submissions = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM ugc_order_submissions WHERE creator_id = ? AND status = 'approved'");
$stmt->execute([$creator_id]);
$ugc_approved = $stmt->fetchColumn();

// Recommended Campaigns
$stmt = $pdo->prepare("SELECT c.*, b.brand_name FROM campaigns c JOIN brands b ON c.brand_id = b.id WHERE c.status = 'published' ORDER BY c.created_at DESC LIMIT 3");
$stmt->execute();
$recommended = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="pt-24 min-h-screen bg-gray-50 dark:bg-gray-950">
    <div class="max-w-7xl mx-auto px-6 md:px-12 flex flex-col md:flex-row gap-8 py-8">
        <!-- Sidebar -->
        <?php include '../includes/creator_sidebar.php'; ?>

        <!-- Main Content -->
        <main class="flex-1 space-y-8">
            <header class="p-8 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm relative overflow-hidden">
                <div aria-hidden="true" class="absolute top-0 right-0 -mr-16 -mt-16 w-64 h-64 bg-primary/5 rounded-full blur-3xl"></div>
                
                <!-- Announcement Bar -->
                <?php if ($msg = get_setting("announcement_text")): ?>
                    <div class="mb-6 py-2 px-6 bg-primary/10 border border-primary/20 rounded-xl text-primary text-xs font-bold uppercase tracking-widest flex items-center gap-3">
                        <span class="animate-pulse">📢</span> <?php echo e($msg); ?>
                    </div>
                <?php endif; ?>

                <div class="relative">
                    <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Welcome back, <?php echo explode(' ', e($creator['full_name']))[0]; ?>!</h2>
                    
                    <?php if (!$profile_status['is_complete']): ?>
                        <div class="mt-6 flex items-center justify-between p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-900 rounded-2xl">
                            <div class="flex items-center space-x-3">
                                <span class="text-blue-600">👤</span>
                                <p class="text-blue-800 dark:text-blue-400 text-sm">
                                    <span class="font-bold">Profile Incomplete (<?php echo $profile_status['percent']; ?>%):</span> Complete your profile to access better campaign opportunities.
                                </p>
                            </div>
                            <a href="<?php echo APP_URL; ?>creator/profile.php" class="text-sm font-bold text-blue-900 dark:text-blue-300 hover:underline">Complete Profile</a>
                        </div>
                    <?php endif; ?>

                    <?php if ($creator['verification_status'] === 'not_started'): ?>
                        <div class="mt-4 flex items-center justify-between p-4 bg-orange-50 dark:bg-orange-900/20 border border-orange-100 dark:border-orange-900 rounded-2xl">
                            <div class="flex items-center space-x-3">
                                <span class="text-orange-600 text-xl">⚠️</span>
                                <p class="text-orange-800 dark:text-orange-400 text-sm">
                                    <span class="font-bold">Verification Required:</span> Complete your student verification to start applying for jobs.
                                </p>
                            </div>
                            <a href="<?php echo APP_URL; ?>creator/verification.php" class="text-sm font-bold text-orange-900 dark:text-orange-300 hover:underline">Verify Now</a>
                        </div>
                    <?php elseif ($creator['verification_status'] === 'pending'): ?>
                        <div class="mt-4 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-900 rounded-2xl">
                             <p class="text-blue-800 dark:text-blue-400 text-sm">⏳ Your student verification is under review. This typically takes 1-2 business days.</p>
                        </div>
                    <?php elseif ($creator['verification_status'] === 'rejected'): ?>
                        <div class="mt-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-100 dark:border-red-900 rounded-2xl">
                             <p class="text-red-800 dark:text-red-400 text-sm font-bold">Your verification was not approved. Please upload valid student proof.</p>
                             <a href="<?php echo APP_URL; ?>creator/verification.php" class="text-sm underline">Re-submit Documents</a>
                        </div>
                    <?php endif; ?>
                </div>
            </header>

            <!-- Stats Grid -->
            <div class="grid grid-cols-2 md:grid-cols-6 gap-4">
                <div class="p-6 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-1">Pending Apps</p>
                    <h3 class="text-2xl font-black text-gray-900 dark:text-white"><?php echo $pending_apps; ?></h3>
                </div>
                <div class="p-6 bg-primary rounded-[2rem] text-white shadow-xl shadow-primary/20">
                    <p class="text-white/60 text-[10px] font-bold uppercase tracking-widest mb-1">Active Jobs</p>
                    <h3 class="text-2xl font-black"><?php echo $active_jobs; ?></h3>
                </div>
                <div class="p-6 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-1">Approved Apps</p>
                    <h3 class="text-2xl font-black text-gray-900 dark:text-white"><?php echo $approved_apps; ?></h3>
                </div>
                <div class="p-6 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-1">Contest Entries</p>
                    <h3 class="text-2xl font-black text-gray-900 dark:text-white"><?php echo $contest_entries; ?></h3>
                </div>
                <div class="p-6 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm border-b-4 border-b-primary">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-primary mb-1">Wins</p>
                    <h3 class="text-2xl font-black text-gray-900 dark:text-white"><?php echo $contest_wins; ?></h3>
                </div>
                <div class="p-6 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-1">UGC Submitted</p>
                    <h3 class="text-2xl font-black text-gray-900 dark:text-white"><?php echo $ugc_submissions; ?></h3>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Recommended Campaigns -->
                <div class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                    <div class="flex justify-between items-center mb-8">
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white">Trending Briefs</h3>
                        <a href="<?php echo APP_URL; ?>creator/browse.php" class="text-xs font-bold text-primary hover:underline">Full Catalog →</a>
                    </div>
                    
                    <?php if (empty($recommended)): ?>
                        <div class="py-12 text-center">
                            <p class="text-gray-500">No campaigns yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($recommended as $camp): ?>
                                <a href="<?php echo APP_URL; ?>creator/campaign-view.php?id=<?php echo $camp['id']; ?>" class="block p-5 rounded-2xl bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-gray-700 hover:border-primary transition group">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <h4 class="font-bold text-gray-900 dark:text-white group-hover:text-primary transition"><?php echo e($camp['title']); ?></h4>
                                            <p class="text-xs text-gray-500 mt-1"><?php echo e($camp['brand_name']); ?> • <?php echo $camp['currency']; ?> <?php echo number_format($camp['budget_per_creator']); ?></p>
                                        </div>
                                        <span class="text-xs font-black text-primary">Apply →</span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Notifications -->
                <div class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                    <div class="flex justify-between items-center mb-8">
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white">Updates</h3>
                        <a href="<?php echo APP_URL; ?>notifications.php" class="text-xs font-bold text-gray-500 hover:underline">See All</a>
                    </div>
                    <?php
                        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 4");
                        $stmt->execute([$_SESSION['user_id']]);
                        $notifications = $stmt->fetchAll();
                    ?>
                    <?php if (empty($notifications)): ?>
                        <div class="py-12 text-center">
                            <p class="text-gray-400 text-sm">No notifications yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-6">
                            <?php foreach ($notifications as $n): ?>
                                <div class="flex gap-4">
                                    <div class="w-2 h-2 mt-2 rounded-full <?php echo $n['is_read'] ? 'bg-gray-200' : 'bg-primary'; ?>"></div>
                                    <div>
                                        <p class="text-sm font-bold text-gray-900 dark:text-white"><?php echo e($n['title']); ?></p>
                                        <p class="text-xs text-gray-500 mt-1"><?php echo e($n['message']); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
