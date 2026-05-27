<?php
// Get current page filename
$current_page = basename($_SERVER['PHP_SELF']);

// Fetch live unread count for notification badge
$_sidebar_unread = 0;
if (isset($pdo) && isset($_SESSION['user_id'])) {
    try {
        $__s = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $__s->execute([$_SESSION['user_id']]);
        $_sidebar_unread = (int)$__s->fetchColumn();
    } catch (Exception $e) {}
}
?>

<!-- ── Mobile menu trigger (visible only on small screens) ── -->
<button id="role-sidebar-toggle"
        type="button"
        aria-controls="role-sidebar-drawer"
        aria-expanded="false"
        aria-label="Open navigation menu"
        class="md:hidden fixed left-4 top-[5.25rem] z-40 w-12 h-12 bg-primary text-white rounded-2xl shadow-lg shadow-primary/30 flex items-center justify-center active:scale-95 transition">
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
       aria-label="Creator navigation">

    <!-- Mobile-only drawer header -->
    <div class="md:hidden flex items-center justify-between px-2 py-1">
        <span class="text-xs font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest">Creator Menu</span>
        <button id="role-sidebar-close" type="button" aria-label="Close menu" class="w-10 h-10 rounded-xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-gray-600 dark:text-gray-300 active:scale-95 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <div class="p-5 md:p-6 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm md:sticky md:top-24">
        <nav class="space-y-1">
            <?php
            // Root-level pages (not inside creator/)
            $root_pages = ['messages.php', 'notifications.php', 'contest-board.php'];

            $nav_items = [
                ['url' => 'dashboard.php',      'label' => 'Overview'],
                ['url' => 'ugc-orders.php',     'label' => 'Campaigns'],
                ['url' => 'contest-board.php',  'label' => 'Contest Board'],
                ['url' => 'my-contests.php',    'label' => 'My Contests'],
                ['url' => 'community.php',      'label' => 'Community'],
                ['url' => 'messages.php',       'label' => 'Messages'],
                ['url' => 'earnings.php',       'label' => 'Earnings'],
                ['url' => 'notifications.php',  'label' => 'Notifications'],
            ];

            foreach ($nav_items as $item):
                $is_active = ($current_page === $item['url']);
                $class = $is_active
                    ? 'bg-primary text-white font-bold'
                    : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 font-medium';

                $link = in_array($item['url'], $root_pages)
                    ? APP_URL . $item['url']
                    : APP_URL . "creator/" . $item['url'];
            ?>
                <a href="<?php echo $link; ?>" class="flex items-center justify-between px-4 py-3 rounded-xl transition <?php echo $class; ?>">
                    <div class="flex items-center space-x-3">
                        <span><?php echo $item['label']; ?></span>
                    </div>
                    <?php if ($item['url'] === 'notifications.php'): ?>
                        <span id="sidebar-notif-badge"
                              class="notif-badge w-5 h-5 bg-secondary text-white text-[10px] font-black rounded-lg items-center justify-center <?php echo $_sidebar_unread > 0 ? 'flex' : 'hidden'; ?>">
                            <?php echo $_sidebar_unread > 99 ? '99+' : $_sidebar_unread; ?>
                        </span>
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
