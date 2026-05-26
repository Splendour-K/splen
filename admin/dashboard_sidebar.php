<?php
// Get current page filename
$current_page = basename($_SERVER["PHP_SELF"]);
?>

<!-- ── Mobile menu trigger (visible only on small screens) ── -->
<button id="role-sidebar-toggle"
        type="button"
        aria-controls="role-sidebar-drawer"
        aria-expanded="false"
        aria-label="Open admin menu"
        class="md:hidden fixed left-4 top-[5.25rem] z-40 w-12 h-12 bg-gray-900 text-white rounded-2xl shadow-lg shadow-black/30 flex items-center justify-center active:scale-95 transition">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6h16M4 12h16M4 18h16"/>
    </svg>
</button>

<!-- ── Backdrop for mobile drawer ── -->
<div id="role-sidebar-backdrop"
     class="md:hidden fixed inset-0 z-[55] bg-black/60 backdrop-blur-sm opacity-0 pointer-events-none transition-opacity duration-300"
     aria-hidden="true"></div>

<!-- ── Sidebar: mobile drawer + desktop sticky column ── -->
<aside id="role-sidebar-drawer"
       class="fixed inset-y-0 left-0 z-[60] w-72 max-w-[85vw] overflow-y-auto -translate-x-full transition-transform duration-300 ease-out
              md:relative md:translate-x-0 md:w-64 md:max-w-none md:z-auto md:transition-none md:overflow-visible
              md:inset-auto md:overflow-y-visible
              space-y-4 p-4 md:p-0"
       aria-label="Admin navigation">

    <!-- Mobile-only drawer header -->
    <div class="md:hidden flex items-center justify-between px-2 py-1">
        <span class="text-xs font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest">Master Control</span>
        <button id="role-sidebar-close" type="button" aria-label="Close menu" class="w-10 h-10 rounded-xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-gray-600 dark:text-gray-300 active:scale-95 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <div class="p-6 md:p-8 bg-white dark:bg-gray-900 rounded-[2rem] md:rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm md:sticky md:top-24">
        <p class="hidden md:block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-6">Master Control</p>
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

        <div class="mt-6 md:mt-8 pt-6 md:pt-8 border-t border-gray-100 dark:border-gray-800">
             <a href="../logout.php" class="flex items-center gap-3 px-4 py-3 rounded-2xl text-red-500 font-bold hover:bg-red-50 transition">
                <span class="w-8 h-8 rounded-lg flex items-center justify-center bg-red-50">🚪</span>
                <span class="text-sm">Logout</span>
             </a>
        </div>
    </div>

    <!-- Quick Insights (desktop sidebar only — too tall for mobile drawer) -->
    <div class="hidden md:block p-6 bg-gradient-to-br from-gray-900 to-gray-800 rounded-[2.5rem] text-white shadow-xl">
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

<!-- ── Drawer behaviour (open/close, ESC, viewport sync) ── -->
<script>
(function() {
    const toggle   = document.getElementById('role-sidebar-toggle');
    const closeBtn = document.getElementById('role-sidebar-close');
    const drawer   = document.getElementById('role-sidebar-drawer');
    const backdrop = document.getElementById('role-sidebar-backdrop');
    if (!toggle || !drawer || !backdrop) return;

    const MQ_DESKTOP = window.matchMedia('(min-width: 768px)');

    function open() {
        if (MQ_DESKTOP.matches) return;
        drawer.classList.remove('-translate-x-full');
        backdrop.classList.remove('opacity-0', 'pointer-events-none');
        document.body.style.overflow = 'hidden';
        toggle.setAttribute('aria-expanded', 'true');
        const firstLink = drawer.querySelector('a, button');
        if (firstLink) firstLink.focus({ preventScroll: true });
    }
    function close() {
        drawer.classList.add('-translate-x-full');
        backdrop.classList.add('opacity-0', 'pointer-events-none');
        document.body.style.overflow = '';
        toggle.setAttribute('aria-expanded', 'false');
    }

    toggle.addEventListener('click', open);
    backdrop.addEventListener('click', close);
    if (closeBtn) closeBtn.addEventListener('click', close);
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') close();
    });

    MQ_DESKTOP.addEventListener('change', (e) => { if (e.matches) close(); });
})();
</script>
