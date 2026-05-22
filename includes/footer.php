<footer class="bg-white dark:bg-gray-950 pt-20 pb-10 border-t border-gray-100 dark:border-gray-900">
    <div class="max-w-7xl mx-auto px-6 md:px-12">
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-12 mb-16">
            <div class="col-span-2 lg:col-span-2">
                <a href="<?php echo APP_URL; ?>index.php" class="text-2xl font-black text-gray-900 dark:text-white tracking-tighter mb-6 block">
                    SPLEN<span class="text-primary italic">NET</span>
                </a>
                <p class="text-gray-500 max-w-xs mb-8">
                    The #1 UGC marketplace connecting authentic student creators with modern brands.
                </p>
                <div class="flex flex-wrap gap-3">
                    <a href="<?php echo APP_URL; ?>contact.php" class="px-3 py-2 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 text-xs font-bold hover:bg-primary hover:text-white transition">Contact</a>
                    <a href="<?php echo APP_URL; ?>brands.php" class="px-3 py-2 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 text-xs font-bold hover:bg-primary hover:text-white transition">Brands</a>
                    <a href="<?php echo APP_URL; ?>creators.php" class="px-3 py-2 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 text-xs font-bold hover:bg-primary hover:text-white transition">Creators</a>
                </div>
            </div>
            <div>
                <h4 class="font-bold text-gray-900 dark:text-white mb-6">Platform</h4>
                <ul class="space-y-4">
                    <li><a href="<?php echo APP_URL; ?>brands.php" class="text-gray-500 hover:text-primary transition">For Brands</a></li>
                    <li><a href="<?php echo APP_URL; ?>creators.php" class="text-gray-500 hover:text-primary transition">For Creators</a></li>
                    <li><a href="<?php echo APP_URL; ?>pricing.php" class="text-gray-500 hover:text-primary transition">Pricing</a></li>
                </ul>
            </div>
            <div>
                <h4 class="font-bold text-gray-900 dark:text-white mb-6">Company</h4>
                <ul class="space-y-4">
                    <li><a href="<?php echo APP_URL; ?>about.php" class="text-gray-500 hover:text-primary transition">About Us</a></li>
                    <li><a href="<?php echo APP_URL; ?>contact.php" class="text-gray-500 hover:text-primary transition">Contact Us</a></li>
                    <li><a href="<?php echo APP_URL; ?>terms.php" class="text-gray-500 hover:text-primary transition">Terms of Use</a></li>
                    <li><a href="<?php echo APP_URL; ?>privacy.php" class="text-gray-500 hover:text-primary transition">Privacy Policy</a></li>
                </ul>
            </div>
        </div>
        <div class="flex flex-col md:flex-row justify-between items-center pt-8 border-t border-gray-100 dark:border-gray-900">
            <p class="text-gray-500 text-sm">&copy; 2026 Splennet. All rights reserved.</p>
            <div class="mt-4 md:mt-0">
                <button id="themeToggle" class="p-2.5 rounded-xl bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700 transition">
                    🌙
                </button>
            </div>
        </div>
    </div>
</footer>

<script>
    const themeToggle = document.getElementById('themeToggle');
    if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.classList.add('dark');
        themeToggle.innerText = '☀️';
    } else {
        document.documentElement.classList.remove('dark');
        themeToggle.innerText = '🌙';
    }

    themeToggle.addEventListener('click', () => {
        if (document.documentElement.classList.contains('dark')) {
            document.documentElement.classList.remove('dark');
            localStorage.theme = 'light';
            themeToggle.innerText = '🌙';
        } else {
            document.documentElement.classList.add('dark');
            localStorage.theme = 'dark';
            themeToggle.innerText = '☀️';
        }
    });
</script>

<script>
/* ── Splennet Notification Poller ──────────────────────────────────────────
   Polls api/notifications.php every 30 s (industry-standard interval).
   Uses Page Visibility API to poll immediately when the user returns to the
   tab, giving a "real-time" feel without persistent server connections.
   All errors are swallowed — this code can never break the page.
──────────────────────────────────────────────────────────────────────────── */
(function () {
    'use strict';
    if (!window.__LOGGED_IN__ || !window.APP_URL) return;

    var API        = window.APP_URL + 'api/notifications.php';
    var INTERVAL   = 30000; // 30 seconds
    var lastUnread = -1;    // -1 = first poll; skip toasts to avoid replaying old items
    var lastId     = 0;

    var ICONS = {
        payment: '💰', job: '🎬', application: '📩', message: '💬',
        campaign_published: '📢', ugc_order: '🎥', contest: '🏆',
        contest_submission: '🎬', ugc_submission: '📹', system: '⚡'
    };

    /* ── Badge helpers ── */
    function updateBadges(count) {
        var badges = document.querySelectorAll('.notif-badge');
        for (var i = 0; i < badges.length; i++) {
            var b = badges[i];
            if (count > 0) {
                b.textContent = count > 99 ? '99+' : String(count);
                b.classList.remove('hidden');
                b.classList.add('flex');
            } else {
                b.classList.add('hidden');
                b.classList.remove('flex');
            }
        }
    }

    /* ── Toast renderer ── */
    function showToast(n) {
        var container = document.getElementById('splt-toast-container');
        if (!container) return;
        var icon  = ICONS[n.type] || '🔔';
        var title = (n.title  || '').replace(/</g, '&lt;');
        var msg   = (n.message || '').replace(/</g, '&lt;');
        if (msg.length > 90) msg = msg.substring(0, 90) + '…';

        var el = document.createElement('div');
        el.className = 'splt-toast';
        el.setAttribute('role', 'alert');
        el.innerHTML =
            '<span class="splt-toast-icon">' + icon + '</span>' +
            '<div class="splt-toast-body">' +
              '<div class="splt-toast-title">' + title + '</div>' +
              '<div class="splt-toast-msg">'  + msg   + '</div>' +
            '</div>' +
            '<button class="splt-toast-close" aria-label="Dismiss">&times;</button>';

        /* click-to-navigate */
        var url = n.target_url;
        if (url) {
            if (!/^https?:\/\//i.test(url)) url = window.APP_URL + url.replace(/^\/+/, '');
            el.style.cursor = 'pointer';
            el.addEventListener('click', function (e) {
                if (e.target.classList.contains('splt-toast-close')) return;
                window.location.href = url;
            });
        }

        /* dismiss button */
        el.querySelector('.splt-toast-close').addEventListener('click', function () {
            dismiss(el);
        });

        container.prepend(el);

        /* auto-dismiss after 6 s */
        setTimeout(function () { dismiss(el); }, 6000);

        /* cap visible toasts at 4 */
        var toasts = container.querySelectorAll('.splt-toast:not(.splt-toast-exit)');
        if (toasts.length > 4) dismiss(toasts[toasts.length - 1]);
    }

    function dismiss(el) {
        if (!el || !el.parentNode || el.classList.contains('splt-toast-exit')) return;
        el.classList.add('splt-toast-exit');
        setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 350);
    }

    /* ── Polling logic ── */
    function poll() {
        fetch(API + '?action=unread_count', { credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
            .then(function (data) {
                if (!data.ok) return;
                var unread = data.unread || 0;
                updateBadges(unread);

                /* show toasts only after the very first poll (avoids replaying old items) */
                if (lastUnread >= 0 && unread > lastUnread) {
                    fetch(API + '?action=list&limit=15', { credentials: 'same-origin' })
                        .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
                        .then(function (d) {
                            if (!d.ok || !d.notifications) return;
                            var items = d.notifications;
                            var fresh = [];
                            for (var i = 0; i < items.length; i++) {
                                if (parseInt(items[i].id) > lastId && !parseInt(items[i].is_read)) {
                                    fresh.push(items[i]);
                                }
                            }
                            /* show at most 3 toasts at once to avoid flooding */
                            var show = fresh.slice(0, 3);
                            for (var j = 0; j < show.length; j++) showToast(show[j]);
                            if (items.length) lastId = Math.max.apply(null, items.map(function (n) { return parseInt(n.id); }));
                        })
                        .catch(function () {});
                }
                lastUnread = unread;
            })
            .catch(function () {}); /* never surface errors */
    }

    /* Delay first poll by 4 s so it doesn't compete with page render */
    setTimeout(poll, 4000);
    setInterval(poll, INTERVAL);

    /* Poll immediately when the user returns to the tab */
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') poll();
    });
}());
</script>
</body>
</html>
