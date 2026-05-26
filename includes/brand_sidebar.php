<?php
// Get current page filename
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- ── Mobile menu trigger (visible only on small screens) ── -->
<button id="role-sidebar-toggle"
        type="button"
        aria-controls="role-sidebar-drawer"
        aria-expanded="false"
        aria-label="Open navigation menu"
        class="md:hidden fixed left-4 top-[5.25rem] z-40 w-12 h-12 bg-secondary text-white rounded-2xl shadow-lg shadow-secondary/30 flex items-center justify-center active:scale-95 transition">
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
       aria-label="Brand navigation">

    <!-- Mobile-only drawer header (close button) -->
    <div class="md:hidden flex items-center justify-between px-2 py-1">
        <span class="text-xs font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest">Brand Menu</span>
        <button id="role-sidebar-close" type="button" aria-label="Close menu" class="w-10 h-10 rounded-xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-gray-600 dark:text-gray-300 active:scale-95 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <div class="p-5 md:p-6 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm md:sticky md:top-24">
        <nav class="space-y-1">
            <?php
            $nav_items = [
                ['url' => 'dashboard.php', 'label' => 'Overview'],
                ['url' => 'create-campaign.php', 'label' => 'Create Campaign'],
                ['url' => 'my-campaigns.php', 'label' => 'My Campaigns'],
                ['url' => 'applications.php', 'label' => 'Applications'],
                ['url' => 'submissions.php', 'label' => 'Work Reviews'],
                ['url' => 'create-ugc-order.php', 'label' => 'New UGC Order'],
                ['url' => 'ugc-orders.php', 'label' => 'UGC Orders'],
                ['url' => 'create-contest.php', 'label' => 'New Contest'],
                ['url' => 'my-contests.php', 'label' => 'My Contests'],
                ['url' => 'wallet.php', 'label' => '💳 Wallet'],
                ['url' => 'subscription.php', 'label' => 'Subscription'],
                ['url' => 'messages.php', 'label' => 'Messages'],
                ['url' => 'notifications.php', 'label' => 'Notifications'],
            ];

            foreach ($nav_items as $item):
                $is_active = ($current_page === $item['url']);
                $class = $is_active
                    ? 'bg-secondary text-white font-bold'
                    : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 font-medium';

                $link = ($item['url'] === 'messages.php' || $item['url'] === 'notifications.php')
                    ? APP_URL . $item['url']
                    : APP_URL . "brand/" . $item['url'];
            ?>
                <a href="<?php echo $link; ?>" class="flex items-center justify-between px-4 py-3 rounded-xl transition <?php echo $class; ?>">
                    <div class="flex items-center space-x-3">
                        <span><?php echo $item['label']; ?></span>
                    </div>
                    <?php if ($item['url'] === 'notifications.php' && isset($unread_count) && $unread_count > 0): ?>
                        <span class="w-5 h-5 bg-primary text-white text-[10px] font-black rounded-lg flex items-center justify-center"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>

            <div class="pt-4 mt-4 border-t border-gray-100 dark:border-gray-800">
                <a href="<?php echo APP_URL; ?>brand/profile.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl <?php echo ($current_page === 'profile.php' ? 'bg-secondary text-white font-bold' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 font-medium'); ?> transition">
                    <span>Company Profile</span>
                </a>
                <a href="<?php echo APP_URL; ?>brand/support.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl <?php echo ($current_page === 'support.php' ? 'bg-secondary text-white font-bold' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 font-medium'); ?> transition">
                    <span>Support</span>
                </a>
            </div>
        </nav>
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
        // Move focus into the drawer for accessibility
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

    // Auto-reset when crossing the desktop breakpoint
    MQ_DESKTOP.addEventListener('change', (e) => { if (e.matches) close(); });
})();
</script>
