<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/wallet_functions.php';
require_role('brand');

$stmt = $pdo->prepare("SELECT * FROM brands WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$brand = $stmt->fetch();
require_brand_record($brand);

$brand_id = $brand['id'];

// Stats Logic
// Active Campaigns
$stmt = $pdo->prepare("SELECT COUNT(*) FROM campaigns WHERE brand_id = ? AND status = 'published'");
$stmt->execute([$brand_id]);
$active_campaigns_count = $stmt->fetchColumn();

// Total Applications received (pending)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM applications a JOIN campaigns c ON a.campaign_id = c.id WHERE c.brand_id = ? AND a.status = 'pending'");
$stmt->execute([$brand_id]);
$pending_apps_count = $stmt->fetchColumn();

// Submissions awaiting review
$stmt = $pdo->prepare("SELECT COUNT(*) FROM jobs WHERE brand_id = ? AND status IN ('awaiting_review', 'draft_submitted')");
$stmt->execute([$brand_id]);
$awaiting_review_count = $stmt->fetchColumn();

// Total Spent
$stmt = $pdo->prepare("SELECT SUM(calculated_amount) FROM payments p JOIN jobs j ON p.job_id = j.id WHERE j.brand_id = ? AND p.status = 'completed'");
$stmt->execute([$brand_id]);
$total_spent = $stmt->fetchColumn() ?: 0;

// Total Creators Hired
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT creator_id) FROM jobs WHERE brand_id = ? AND status IN ('approved', 'completed', 'in_progress')");
$stmt->execute([$brand_id]);
$total_hired = $stmt->fetchColumn();

// Quota Info
$quota = check_brand_quota($brand_id);

// Profile completion
$profile_status = check_brand_profile_completion($brand);

// Wallet
$wallet = get_brand_wallet($brand_id);

// Recent Applications
$stmt = $pdo->prepare("
    SELECT a.*, c.title as campaign_title, cr.full_name as creator_name 
    FROM applications a 
    JOIN campaigns c ON a.campaign_id = c.id 
    JOIN creators cr ON a.creator_id = cr.id 
    WHERE c.brand_id = ? 
    ORDER BY a.created_at DESC LIMIT 5
");
$stmt->execute([$brand_id]);
$recent_apps = $stmt->fetchAll();

// Recent Conversations
$stmt = $pdo->prepare("
    SELECT c.*, cr.full_name as creator_name
    FROM conversations c
    JOIN creators cr ON c.creator_id = cr.id
    WHERE c.brand_id = ?
    ORDER BY c.updated_at DESC LIMIT 3
");
$stmt->execute([$brand_id]);
$recent_chats = $stmt->fetchAll();

// UGC Orders Stats
$stmt = $pdo->prepare("SELECT COUNT(*) FROM ugc_orders WHERE brand_id = ? AND status = 'published'");
$stmt->execute([$brand_id]);
$ugc_orders_count = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM ugc_order_submissions us JOIN ugc_orders uo ON us.ugc_order_id = uo.id WHERE uo.brand_id = ? AND us.status = 'submitted'");
$stmt->execute([$brand_id]);
$ugc_pending_count = $stmt->fetchColumn();

// Contests Stats
$stmt = $pdo->prepare("SELECT COUNT(*) FROM contests WHERE brand_id = ? AND status = 'live'");
$stmt->execute([$brand_id]);
$contests_count = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM contest_submissions cs JOIN contests c ON cs.contest_id = c.id WHERE c.brand_id = ? AND cs.status = 'submitted'");
$stmt->execute([$brand_id]);
$contest_pending_count = $stmt->fetchColumn();

include '../includes/header.php';
?>

<div class="pt-32 md:pt-24 min-h-screen bg-gray-50 dark:bg-gray-950">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-12 flex flex-col md:flex-row gap-6 md:gap-8 py-4 md:py-8">
        <!-- Sidebar -->
        <?php include '../includes/brand_sidebar.php'; ?>

        <!-- Main Content -->
        <main class="flex-1 space-y-6 md:space-y-8 min-w-0">
            <header class="p-6 md:p-8 bg-white dark:bg-gray-900 rounded-[1.5rem] md:rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm relative overflow-hidden">
                <div aria-hidden="true" class="absolute top-0 right-0 -mr-16 -mt-16 w-64 h-64 bg-secondary/5 rounded-full blur-3xl"></div>

                <!-- Announcement Bar -->
                <?php if ($msg = get_setting("announcement_text")): ?>
                    <div class="mb-6 py-2 px-4 md:px-6 bg-secondary/10 border border-secondary/20 rounded-xl text-secondary text-[10px] md:text-xs font-bold uppercase tracking-widest flex items-center gap-3">
                        <span class="animate-pulse">📢</span> <?php echo e($msg); ?>
                    </div>
                <?php endif; ?>

                <div class="relative flex flex-col md:flex-row md:items-center justify-between gap-4 md:gap-6">
                    <div>
                        <h2 class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-white">Brand Overview</h2>
                        <p class="text-sm md:text-base text-gray-600 dark:text-gray-400 mt-2">Scale your brand with high-performing student content.</p>
                    </div>

                    <div class="flex flex-col items-stretch md:items-end gap-2">
                        <a href="<?php echo APP_URL; ?>brand/create-campaign.php" class="inline-flex items-center justify-center px-5 md:px-6 py-3 bg-secondary text-white font-bold rounded-xl hover:scale-105 transition-all w-full md:w-fit">
                            <span>New Campaign</span>
                            <span class="ml-2 text-xl">+</span>
                        </a>
                        <div class="text-[10px] font-black uppercase tracking-widest text-gray-400 text-center md:text-right">
                            Quota: <span class="text-secondary"><?php echo $quota['used']; ?> / <?php echo $quota['limit']; ?></span> Used this month
                        </div>
                    </div>
                </div>
            </header>

            <!-- Stats Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-3 md:gap-4">
                <div class="p-4 md:p-6 bg-white dark:bg-gray-900 rounded-2xl md:rounded-[2rem] border border-gray-100 dark:border-gray-800">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Active Briefs</p>
                    <h3 class="text-xl md:text-2xl font-black text-gray-900 dark:text-white"><?php echo $active_campaigns_count; ?></h3>
                </div>
                <div class="p-4 md:p-6 bg-white dark:bg-gray-900 rounded-2xl md:rounded-[2rem] border border-gray-100 dark:border-gray-800">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">UGC Orders</p>
                    <h3 class="text-xl md:text-2xl font-black text-gray-900 dark:text-white"><?php echo $ugc_orders_count; ?></h3>
                </div>
                <div class="p-4 md:p-6 bg-white dark:bg-gray-900 rounded-2xl md:rounded-[2rem] border border-gray-100 dark:border-gray-800">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Contests</p>
                    <h3 class="text-xl md:text-2xl font-black text-gray-900 dark:text-white"><?php echo $contests_count; ?></h3>
                </div>
                <div class="p-4 md:p-6 bg-white dark:bg-gray-900 rounded-2xl md:rounded-[2rem] border border-gray-100 dark:border-gray-800">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Creators Hired</p>
                    <h3 class="text-xl md:text-2xl font-black text-gray-900 dark:text-white"><?php echo $total_hired; ?></h3>
                </div>
                <div class="p-4 md:p-6 bg-white dark:bg-gray-900 rounded-2xl md:rounded-[2rem] border border-gray-100 dark:border-gray-800 border-b-4 border-b-secondary">
                    <p class="text-[10px] font-bold <?php echo $awaiting_review_count > 0 ? 'text-secondary' : 'text-gray-400'; ?> uppercase tracking-widest mb-1">Work for Review</p>
                    <h3 class="text-xl md:text-2xl font-black text-gray-900 dark:text-white"><?php echo $awaiting_review_count; ?></h3>
                </div>
                <div class="p-4 md:p-6 bg-secondary rounded-2xl md:rounded-[2rem] text-white">
                    <p class="text-white/60 text-[10px] font-bold uppercase tracking-widest mb-1">Total Spent</p>
                    <h3 class="text-xl md:text-2xl font-black"><?php echo number_format($total_spent, 0); ?></h3>
                </div>
            </div>

            <!-- Wallet Summary Card -->
            <div class="p-5 md:p-6 bg-gray-900 rounded-2xl md:rounded-[2rem] border border-gray-800 relative overflow-hidden">
                <div class="absolute -bottom-10 -right-10 w-48 h-48 bg-secondary/20 rounded-full blur-3xl"></div>
                <div class="relative flex flex-col md:flex-row md:items-center justify-between gap-5 md:gap-6">
                    <div class="flex items-center gap-4 md:gap-5">
                        <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-white/10 flex items-center justify-center text-xl md:text-2xl flex-shrink-0">💳</div>
                        <div class="min-w-0">
                            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Brand Wallet · <?php echo e($wallet['currency']); ?></p>
                            <p class="text-2xl md:text-3xl font-black text-white mt-1 break-all"><?php echo format_money($wallet['available_balance'], $wallet['currency']); ?></p>
                            <p class="text-[11px] md:text-xs text-gray-500 mt-1">
                                <?php echo format_money($wallet['reserved_balance'], $wallet['currency']); ?> reserved
                                &nbsp;·&nbsp;
                                <?php echo format_money($wallet['total_spent'], $wallet['currency']); ?> spent
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2 md:gap-3 md:flex-shrink-0">
                        <?php if ($wallet['status'] !== 'active'): ?>
                            <span class="px-3 md:px-4 py-2 bg-red-500/20 text-red-400 font-black text-[11px] md:text-xs rounded-xl border border-red-500/30">🔒 <?php echo ucfirst($wallet['status']); ?></span>
                        <?php elseif ((float)$wallet['available_balance'] <= 0): ?>
                            <span class="px-3 md:px-4 py-2 bg-yellow-500/20 text-yellow-400 font-black text-[11px] md:text-xs rounded-xl border border-yellow-500/30">⚠ Balance Empty</span>
                        <?php else: ?>
                            <span class="px-3 md:px-4 py-2 bg-green-500/20 text-green-400 font-black text-[11px] md:text-xs rounded-xl border border-green-500/30">✓ Active</span>
                        <?php endif; ?>
                        <a href="<?php echo APP_URL; ?>brand/wallet.php" class="px-4 md:px-5 py-2 bg-white/10 hover:bg-white/20 text-white text-xs md:text-sm font-bold rounded-xl transition border border-white/10 flex-1 md:flex-none text-center">
                            View Transactions
                        </a>
                        <a href="<?php echo APP_URL; ?>brand/wallet.php#fund" class="px-4 md:px-5 py-2 bg-secondary text-white text-xs md:text-sm font-bold rounded-xl hover:scale-105 transition flex-1 md:flex-none text-center">
                            Request Funding
                        </a>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 md:gap-8">
                <!-- Recent Applications -->
                <section class="lg:col-span-2 p-6 md:p-8 bg-white dark:bg-gray-900 rounded-2xl md:rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                    <div class="flex justify-between items-center mb-6 md:mb-8">
                        <h3 class="text-lg md:text-xl font-bold text-gray-900 dark:text-white">Recent Applications</h3>
                        <a href="<?php echo APP_URL; ?>brand/applications.php" class="text-xs font-bold text-secondary hover:underline">View All →</a>
                    </div>

                    <?php if (empty($recent_apps)): ?>
                        <div class="py-12 text-center">
                            <p class="text-gray-400 text-sm">No applications yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-3 md:space-y-4">
                            <?php foreach ($recent_apps as $app): ?>
                                <div class="p-4 md:p-5 rounded-2xl bg-gray-50 dark:bg-gray-800/50 border border-gray-100 dark:border-gray-700 flex items-center justify-between gap-3 group">
                                    <div class="flex items-center gap-3 md:gap-4 min-w-0 flex-1">
                                        <div class="w-10 h-10 rounded-xl bg-secondary/10 flex items-center justify-center text-secondary font-bold text-sm flex-shrink-0">
                                            <?php echo strtoupper(substr($app['creator_name'], 0, 1)); ?>
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-sm font-bold text-gray-900 dark:text-white truncate"><?php echo e($app['creator_name']); ?></p>
                                            <p class="text-[10px] text-gray-500 font-medium truncate">For: <?php echo e($app['campaign_title']); ?></p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3 md:gap-4 flex-shrink-0">
                                        <span class="px-2 md:px-3 py-1 bg-secondary/10 text-secondary text-[10px] font-black rounded-full uppercase"><?php echo $app['status']; ?></span>
                                        <a href="applications.php" class="hidden md:flex w-8 h-8 rounded-lg bg-gray-200 dark:bg-gray-700 items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                            <span class="text-gray-600 dark:text-white">→</span>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <!-- Side Info -->
                <aside class="space-y-6 md:space-y-8">
                    <!-- Profile Completion -->
                    <?php if (!$profile_status['is_complete']): ?>
                        <div class="p-6 md:p-8 bg-gray-900 rounded-2xl md:rounded-[2.5rem] border border-gray-800 shadow-2xl relative overflow-hidden group">
                           <div aria-hidden="true" class="absolute top-0 right-0 -mr-16 -mt-16 w-32 h-32 bg-secondary/10 rounded-full blur-2xl group-hover:bg-secondary/20 transition-all"></div>
                           <h4 class="text-white font-bold text-sm mb-4">Complete Profile</h4>
                           <div class="relative h-2 w-full bg-white/10 rounded-full mb-4">
                               <div class="absolute h-full bg-secondary rounded-full transition-all duration-1000" style="width: <?php echo $profile_status['percent']; ?>%"></div>
                           </div>
                           <p class="text-[10px] text-gray-400 font-medium leading-relaxed mb-5 md:mb-6">Completing your profile increases trust and application conversion by <span class="text-secondary">40%</span>.</p>
                           <a href="profile.php" class="block text-center py-3 bg-white/10 hover:bg-white/20 text-white text-xs font-black uppercase tracking-widest rounded-xl transition-all border border-white/5">Update Info</a>
                        </div>
                    <?php endif; ?>

                    <!-- Conversations -->
                    <div class="p-6 md:p-8 bg-white dark:bg-gray-900 rounded-2xl md:rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                        <h4 class="font-bold text-gray-900 dark:text-white mb-5 md:mb-6">Recent Chats</h4>
                        <div class="space-y-3 md:space-y-4">
                            <?php foreach ($recent_chats as $chat): ?>
                                <a href="view-message.php?id=<?php echo $chat['id']; ?>" class="block p-4 rounded-2xl hover:bg-gray-50 dark:hover:bg-gray-800 transition border border-transparent hover:border-gray-100 dark:hover:border-gray-700">
                                    <p class="text-xs font-bold text-gray-900 dark:text-white truncate"><?php echo e($chat['creator_name']); ?></p>
                                    <p class="text-[10px] text-gray-500 truncate mt-1"><?php echo e($chat['last_message'] ?: 'Conversation started'); ?></p>
                                </a>
                            <?php endforeach; ?>
                            <?php if (empty($recent_chats)): ?>
                                <p class="text-[10px] text-gray-400 text-center py-4">No active chats.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Platform Update -->
                    <div class="p-6 md:p-8 bg-gray-900 rounded-2xl md:rounded-[2.5rem] text-white relative overflow-hidden group">
                        <div class="absolute -bottom-10 -right-10 w-40 h-40 bg-secondary/20 rounded-full blur-3xl group-hover:scale-110 transition-transform duration-700"></div>
                        <h3 class="text-lg md:text-xl font-black mb-3 md:mb-4">Scale Faster</h3>
                        <p class="text-gray-400 text-xs leading-relaxed mb-5 md:mb-6">Need more content? You can duplicate your best-performing campaigns with one click in "My Campaigns".</p>
                        <a href="my-campaigns.php" class="text-xs font-bold text-secondary flex items-center gap-2 group-hover:gap-3 transition-all">
                            Manage Campaigns <span>→</span>
                        </a>
                    </div>
                </aside>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
