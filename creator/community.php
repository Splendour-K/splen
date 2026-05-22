<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_role('creator');

// Fetch Top Creators (by completed jobs or total earnings - using jobs for now)
$stmt = $pdo->query("
    SELECT c.full_name, c.main_niche, COUNT(j.id) as jobs_done
    FROM creators c
    JOIN jobs j ON c.id = j.creator_id
    WHERE j.status = 'completed'
    GROUP BY c.id
    ORDER BY jobs_done DESC
    LIMIT 10
");
$top_creators = $stmt->fetchAll();

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
                    <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Creator Community</h2>
                    <p class="text-gray-600 dark:text-gray-400 mt-2">Connect and see how other student creators are doing.</p>
                </div>
            </header>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Leaderboard -->
                <section class="lg:col-span-2 p-8 bg-white dark:bg-gray-900 rounded-[3rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-8 flex items-center">
                        <span class="mr-3">🏆</span> Top Creators
                    </h3>
                    
                    <div class="space-y-4">
                        <?php foreach ($top_creators as $index => $c): ?>
                            <div class="flex items-center justify-between p-4 rounded-2xl hover:bg-gray-50 dark:hover:bg-gray-800 transition border border-transparent hover:border-gray-100 dark:hover:border-gray-700">
                                <div class="flex items-center space-x-4">
                                    <span class="w-8 h-8 flex items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800 text-sm font-black text-gray-500"><?php echo $index + 1; ?></span>
                                    <div>
                                        <p class="font-bold text-gray-900 dark:text-white"><?php echo e($c['full_name']); ?></p>
                                        <p class="text-xs text-gray-500 uppercase tracking-widest font-medium"><?php echo e($c['main_niche']); ?></p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-black text-primary"><?php echo $c['jobs_done']; ?> Jobs</p>
                                    <p class="text-[10px] text-gray-400 uppercase font-bold">Completed</p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <!-- Community Tips -->
                <section class="space-y-6">
                    <div class="p-8 bg-primary rounded-[2.5rem] text-white shadow-xl shadow-primary/20">
                        <h4 class="text-lg font-black mb-4">Did you know?</h4>
                        <p class="text-primary-soft/80 text-sm leading-relaxed">Creators who include a call-to-action in the first 3 seconds of their video have a 40% higher approval rate!</p>
                    </div>

                    <div class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                        <h4 class="text-lg font-black text-gray-900 dark:text-white mb-4">Creator Events</h4>
                        <div class="space-y-4 text-sm">
                            <div class="pb-4 border-b border-gray-50 dark:border-gray-800">
                                <p class="font-bold text-gray-900 dark:text-white">UGC Masterclass</p>
                                <p class="text-gray-500 mt-1">Starting in 2 days • Online</p>
                            </div>
                            <div>
                                <p class="font-bold text-gray-900 dark:text-white">Brand Pitching Workshop</p>
                                <p class="text-gray-500 mt-1">Next week • Discord</p>
                            </div>
                        </div>
                        <button class="mt-8 w-full py-4 bg-gray-900 dark:bg-white text-white dark:text-gray-900 font-bold rounded-2xl hover:scale-105 transition">View All Events</button>
                    </div>
                </section>
            </div>
        </main>
    </div>
</div>

<?php 
include '../includes/header.php';
?>