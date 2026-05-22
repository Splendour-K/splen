<?php
// Get current page filename
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="w-full md:w-64 space-y-4">
    <div class="p-6 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm sticky top-24">
        <nav class="space-y-1">
            <?php
            $nav_items = [
                ['url' => 'dashboard.php', 'label' => 'Overview'],
                ['url' => 'browse.php', 'label' => 'Browse Campaigns'],
                ['url' => 'my-applications.php', 'label' => 'My Applications'],
                ['url' => 'my-jobs.php', 'label' => 'My Jobs'],
                ['url' => 'my-contests.php', 'label' => 'My Contests'],
                ['url' => 'ugc-orders.php', 'label' => 'UGC Orders'],
                ['url' => 'community.php', 'label' => 'Community'],
                ['url' => 'messages.php', 'label' => 'Messages'],
                ['url' => 'earnings.php', 'label' => 'Earnings'],
                ['url' => 'notifications.php', 'label' => 'Notifications'],
            ];

            foreach ($nav_items as $item):
                $is_active = ($current_page === $item['url']);
                $class = $is_active 
                    ? 'bg-primary text-white font-bold' 
                    : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 font-medium';
                
                $link = ($item['url'] === 'messages.php' || $item['url'] === 'notifications.php') 
                    ? APP_URL . $item['url'] 
                    : APP_URL . "creator/" . $item['url'];
            ?>
                <a href="<?php echo $link; ?>" class="flex items-center justify-between px-4 py-3 rounded-xl transition <?php echo $class; ?>">
                    <div class="flex items-center space-x-3">
                        <span><?php echo $item['label']; ?></span>
                    </div>
                    <?php if ($item['url'] === 'notifications.php' && isset($unread_count) && $unread_count > 0): ?>
                        <span class="w-5 h-5 bg-secondary text-white text-[10px] font-black rounded-lg flex items-center justify-center"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>

            <div class="pt-4 mt-4 border-t border-gray-100 dark:border-gray-800">
                <a href="<?php echo APP_URL; ?>creator/profile.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl <?php echo ($current_page === 'profile.php' ? 'bg-primary text-white font-bold' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 font-medium'); ?> transition">
                    <span>Settings</span>
                </a>
                <a href="<?php echo APP_URL; ?>creator/support.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl <?php echo ($current_page === 'support.php' ? 'bg-primary text-white font-bold' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 font-medium'); ?> transition">
                    <span>Help</span>
                </a>
            </div>
        </nav>
    </div>
</aside>