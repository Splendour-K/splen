<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_role('creator');

// Fetch Creator data
$stmt = $pdo->prepare("SELECT id FROM creators WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$creator = $stmt->fetch();
$creator_id = $creator['id'];

// Get all applications
$stmt = $pdo->prepare("
    SELECT a.*, c.title, c.category, c.budget_per_creator, b.brand_name 
    FROM applications a
    JOIN campaigns c ON a.campaign_id = c.id
    JOIN brands b ON c.brand_id = b.id
    WHERE a.creator_id = ?
    ORDER BY a.created_at DESC
");
$stmt->execute([$creator_id]);
$applications = $stmt->fetchAll();

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
                <div class="relative">
                    <h2 class="text-3xl font-bold text-gray-900 dark:text-white">My Applications</h2>
                    <p class="text-gray-600 dark:text-gray-400 mt-2">Track your status for campaigns you've applied to.</p>
                </div>
            </header>

            <div class="grid grid-cols-1 gap-6">
                <?php foreach ($applications as $app): ?>
                    <div class="p-6 bg-white dark:bg-gray-900 rounded-3xl border border-gray-100 dark:border-gray-800 shadow-sm flex flex-col md:flex-row items-center justify-between gap-6">
                        <div class="flex items-center gap-6">
                            <div class="w-16 h-16 rounded-2xl bg-primary/10 flex items-center justify-center text-2xl">
                                <?php echo e(mb_substr($app['brand_name'], 0, 1)); ?>
                            </div>
                            <div>
                                <span class="text-[10px] font-bold uppercase tracking-widest text-primary bg-primary/5 px-2 py-1 rounded"><?php echo e($app['category']); ?></span>
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white mt-1"><?php echo e($app['title']); ?></h3>
                                <p class="text-sm text-gray-500 font-medium"><?php echo e($app['brand_name']); ?></p>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-8 text-center md:text-left">
                            <div>
                                <p class="text-[10px] font-bold uppercase text-gray-400 mb-1">Budget</p>
                                <p class="text-sm font-black text-gray-900 dark:text-white">$<?php echo number_format($app['budget_per_creator'], 0); ?></p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold uppercase text-gray-400 mb-1">Applied On</p>
                                <p class="text-sm font-medium text-gray-900 dark:text-white"><?php echo date('M d, Y', strtotime($app['created_at'])); ?></p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold uppercase text-gray-400 mb-1">Status</p>
                                <?php 
                                    $statusClasses = [
                                        'pending' => 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
                                        'accepted' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
                                        'rejected' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                                        'withdrawn' => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400'
                                    ];
                                    $class = $statusClasses[$app['status']] ?? $statusClasses['pending'];
                                ?>
                                <span class="px-3 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider <?php echo $class; ?>">
                                    <?php echo e($app['status']); ?>
                                </span>
                            </div>
                        </div>

                        <div class="w-full md:w-auto">
                            <a href="<?php echo APP_URL; ?>creator/campaign-view.php?id=<?php echo $app['campaign_id']; ?>" class="block text-center px-6 py-3 bg-gray-50 dark:bg-gray-800 hover:bg-primary hover:text-white transition-all rounded-xl text-sm font-bold text-gray-900 dark:text-white border border-gray-100 dark:border-gray-700">
                                View Brief
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if (empty($applications)): ?>
                    <div class="py-20 text-center bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800">
                        <div class="text-6xl mb-4">📝</div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white">No applications yet</h3>
                        <p class="text-gray-500 mt-2">Start browsing campaigns and apply to work with brands!</p>
                        <a href="<?php echo APP_URL; ?>creator/browse.php" class="mt-6 inline-block px-8 py-3 bg-primary text-white font-bold rounded-2xl shadow-lg shadow-primary/20 hover:scale-105 transition">Browse Campaigns</a>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<?php 
include '../includes/footer.php';
?>