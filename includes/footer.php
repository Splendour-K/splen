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
</body>
</html>
