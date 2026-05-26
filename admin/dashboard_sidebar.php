<?php
// Get current page filename
$current_page = basename($_SERVER["PHP_SELF"]);
?>
<aside class="w-full md:w-64 space-y-4">
    <div class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm sticky top-24">
        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-6">Master Control</p>
        <nav class="space-y-1">
            <?php
            $nav_items = [
                ["url" => "dashboard.php", "label" => "Live Overview", "icon" => "📊"],
                ["url" => "users.php", "label" => "User Management", "icon" => "👥"],
                ["url" => "messages.php", "label" => "All Messages", "icon" => "💬"],
                ["url" => "media-library.php", "label" => "Media Library", "icon" => "🎞️"],
                ["url" => "campaigns.php", "label" => "Campaign Control", "icon" => "📢"],
                ["url" => "contests.php", "label" => "Contest Management", "icon" => "🏆"],
                ["url" => "contest-submissions.php", "label" => "Contest Reviews", "icon" => "🎬"],
                ["url" => "ugc-orders.php", "label" => "UGC Orders", "icon" => "🎬"],
                ["url" => "ugc-submissions.php", "label" => "UGC Reviews", "icon" => "🎥"],
                ["url" => "view-count-verification.php", "label" => "View Verification", "icon" => "👁️"],
                ["url" => "watermark-management.php", "label" => "Watermark Management", "icon" => "🔓"],
                ["url" => "cpm-payouts.php", "label" => "CPM Payouts", "icon" => "📊"],
                ["url" => "brand-wallets.php", "label" => "Brand Wallets", "icon" => "💳"],
                ["url" => "payment-verification.php", "label" => "Payments", "icon" => "💰"],
                ["url" => "performance-reviews.php", "label" => "Payout Approvals", "icon" => "💰"],
                ["url" => "support-tickets.php", "label" => "Support Tickets", "icon" => "🎫"],
                ["url" => "moderation.php", "label" => "Moderation", "icon" => "🛡️"],
                ["url" => "settings.php", "label" => "Site Settings", "icon" => "⚙️"],
            ];

            foreach ($nav_items as $item):
                $is_active = ($current_page === $item["url"]);
                $class = $is_active 
                    ? "bg-gray-900 text-white font-bold" 
                    : "text-gray-500 hover:bg-orange-50 dark:hover:bg-orange-900/10 hover:text-orange-600 font-bold";
            ?>
                <a href="<?php echo $item["url"]; ?>" class="flex items-center gap-3 px-4 py-3 rounded-2xl transition-all <?php echo $class; ?>">
                    <span class="w-8 h-8 rounded-lg flex items-center justify-center bg-gray-50 dark:bg-gray-800 text-lg"><?php echo $item["icon"]; ?></span>
                    <span class="text-sm"><?php echo $item["label"]; ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="mt-8 pt-8 border-t border-gray-100 dark:border-gray-800">
             <a href="../logout.php" class="flex items-center gap-3 px-4 py-3 rounded-2xl text-red-500 font-bold hover:bg-red-50 transition">
                <span class="w-8 h-8 rounded-lg flex items-center justify-center bg-red-50">🚪</span>
                <span class="text-sm">Logout</span>
             </a>
        </div>
    </div>

    <!-- Quick Insights -->
    <div class="p-6 bg-gradient-to-br from-gray-900 to-gray-800 rounded-[2.5rem] text-white shadow-xl">
        <h4 class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-4">System Shield</h4>
        <div class="flex items-center justify-between mb-4">
            <span class="text-xs font-bold text-green-400">Firewall Active</span>
            <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
        </div>
        <div class="h-2 bg-gray-700 rounded-full overflow-hidden">
            <div class="h-full bg-orange-500 w-[85%]"></div>
        </div>
        <p class="text-[9px] font-bold text-gray-500 mt-3 uppercase tracking-tighter">85% Database Health</p>
    </div>
</aside>
