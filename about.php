<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
include 'includes/header.php';
?>

<div class="pt-24 min-h-screen bg-gray-50 dark:bg-gray-950">
    <section class="max-w-7xl mx-auto px-6 md:px-12 py-12">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 items-center">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.35em] text-secondary mb-4">About Splennet</p>
                <h1 class="text-4xl md:text-6xl font-black text-gray-900 dark:text-white leading-tight">Built for brands that need real creator content, fast.</h1>
                <p class="mt-6 text-lg text-gray-600 dark:text-gray-400 max-w-2xl">Splennet connects student creators with brands through campaigns, contests, UGC orders, and verified workflows designed to keep launch operations simple and measurable.</p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="<?php echo APP_URL; ?>register.php?role=brand" class="px-6 py-3 rounded-xl bg-primary text-white font-bold hover:scale-105 transition">Start as a Brand</a>
                    <a href="<?php echo APP_URL; ?>register.php?role=creator" class="px-6 py-3 rounded-xl bg-white dark:bg-gray-900 text-gray-900 dark:text-white border border-gray-200 dark:border-gray-700 font-bold hover:scale-105 transition">Join as a Creator</a>
                </div>
            </div>
            <div class="p-8 md:p-10 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-xl">
                <div class="grid grid-cols-2 gap-4">
                    <div class="p-5 rounded-2xl bg-gray-50 dark:bg-gray-800">
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Marketplace</p>
                        <p class="mt-2 text-2xl font-black text-gray-900 dark:text-white">UGC + CPM</p>
                    </div>
                    <div class="p-5 rounded-2xl bg-gray-50 dark:bg-gray-800">
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Workflow</p>
                        <p class="mt-2 text-2xl font-black text-gray-900 dark:text-white">Verified</p>
                    </div>
                    <div class="p-5 rounded-2xl bg-gray-50 dark:bg-gray-800">
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Access</p>
                        <p class="mt-2 text-2xl font-black text-gray-900 dark:text-white">Mobile-first</p>
                    </div>
                    <div class="p-5 rounded-2xl bg-gray-50 dark:bg-gray-800">
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Support</p>
                        <p class="mt-2 text-2xl font-black text-gray-900 dark:text-white">Admin-reviewed</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="max-w-7xl mx-auto px-6 md:px-12 pb-12">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="p-8 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800">
                <h2 class="text-xl font-black text-gray-900 dark:text-white">For Brands</h2>
                <p class="mt-3 text-gray-600 dark:text-gray-400">Launch campaigns, shortlist creators, manage submissions, and review performance in one place.</p>
            </div>
            <div class="p-8 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800">
                <h2 class="text-xl font-black text-gray-900 dark:text-white">For Creators</h2>
                <p class="mt-3 text-gray-600 dark:text-gray-400">Discover opportunities, verify your student status, submit content, and track earnings and messages.</p>
            </div>
            <div class="p-8 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800">
                <h2 class="text-xl font-black text-gray-900 dark:text-white">For Admins</h2>
                <p class="mt-3 text-gray-600 dark:text-gray-400">Moderate submissions, verify payouts, review messages, and keep the platform stable at launch.</p>
            </div>
        </div>
    </section>
</div>

<?php include 'includes/footer.php'; ?>