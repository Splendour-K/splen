<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
include 'includes/header.php';
?>

<div class="pt-24 min-h-screen bg-gray-50 dark:bg-gray-950">

    <!-- Hero -->
    <section class="max-w-7xl mx-auto px-6 md:px-12 py-12">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 items-center">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.35em] text-secondary mb-4">About Splennet</p>
                <h1 class="text-4xl md:text-6xl font-black text-gray-900 dark:text-white leading-tight">Built for brands that need real creator content, fast.</h1>
                <p class="mt-6 text-lg text-gray-600 dark:text-gray-400 max-w-2xl">Splennet is a UGC marketplace where verified university students create short-form video content for brands across TikTok, Reels, and YouTube Shorts — at a fraction of the cost of a traditional agency.</p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="<?php echo APP_URL; ?>register.php?role=brand" class="px-6 py-3 rounded-xl bg-primary text-white font-bold hover:scale-105 transition">Start as a Brand</a>
                    <a href="<?php echo APP_URL; ?>register.php?role=creator" class="px-6 py-3 rounded-xl bg-white dark:bg-gray-900 text-gray-900 dark:text-white border border-gray-200 dark:border-gray-700 font-bold hover:scale-105 transition">Join as a Creator</a>
                </div>
            </div>
            <div class="p-8 md:p-10 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-xl">
                <div class="grid grid-cols-2 gap-4">
                    <div class="p-5 rounded-2xl bg-gray-50 dark:bg-gray-800">
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Content Types</p>
                        <p class="mt-2 text-2xl font-black text-gray-900 dark:text-white">3 Formats</p>
                        <p class="text-xs text-gray-400 mt-1">Campaigns, Contests, UGC Orders</p>
                    </div>
                    <div class="p-5 rounded-2xl bg-gray-50 dark:bg-gray-800">
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Verification</p>
                        <p class="mt-2 text-2xl font-black text-gray-900 dark:text-white">Admin-reviewed</p>
                        <p class="text-xs text-gray-400 mt-1">Every creator is a real student</p>
                    </div>
                    <div class="p-5 rounded-2xl bg-gray-50 dark:bg-gray-800">
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Payments</p>
                        <p class="mt-2 text-2xl font-black text-gray-900 dark:text-white">Escrow-backed</p>
                        <p class="text-xs text-gray-400 mt-1">Funds reserved before work begins</p>
                    </div>
                    <div class="p-5 rounded-2xl bg-gray-50 dark:bg-gray-800">
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Platform Fee</p>
                        <p class="mt-2 text-2xl font-black text-gray-900 dark:text-white">15%</p>
                        <p class="text-xs text-gray-400 mt-1">Only on successful campaigns</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Mission -->
    <section class="max-w-7xl mx-auto px-6 md:px-12 py-12">
        <div class="p-8 md:p-14 bg-gray-900 dark:bg-gray-800 rounded-[2.5rem] text-white">
            <div class="max-w-3xl">
                <p class="text-[10px] font-black uppercase tracking-[0.35em] text-primary mb-4">Our Mission</p>
                <h2 class="text-3xl md:text-4xl font-black leading-tight mb-6">Give every student creator a seat at the table — and every brand a direct line to authentic content.</h2>
                <p class="text-gray-400 text-lg leading-relaxed">
                    Traditional content agencies are expensive, slow, and disconnected from the audiences brands are actually trying to reach. At the same time, millions of students have the creativity, the phones, and the energy to produce compelling content — but no structured way to monetize that skill while studying.
                </p>
                <p class="text-gray-400 text-lg leading-relaxed mt-4">
                    Splennet bridges that gap. We built a platform where the entire workflow — brief to approval to payout — is managed in one place, with guardrails that protect both sides. Brands get content that actually converts. Creators get paid fairly and on time.
                </p>
            </div>
        </div>
    </section>

    <!-- For Each Role -->
    <section class="max-w-7xl mx-auto px-6 md:px-12 pb-12">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="p-8 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800">
                <span class="text-3xl mb-4 block">🏢</span>
                <h2 class="text-xl font-black text-gray-900 dark:text-white mb-3">For Brands</h2>
                <p class="text-gray-600 dark:text-gray-400 text-sm leading-relaxed">Launch campaigns with clear briefs, shortlist creators from a verified pool, review submitted videos, manage your content budget through a funded wallet, and track performance — all without leaving the platform. Our admin team moderates every submission before it reaches you.</p>
            </div>
            <div class="p-8 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800">
                <span class="text-3xl mb-4 block">🎓</span>
                <h2 class="text-xl font-black text-gray-900 dark:text-white mb-3">For Creators</h2>
                <p class="text-gray-600 dark:text-gray-400 text-sm leading-relaxed">Verify your student status once, then apply for any active campaign, enter contests, or accept direct UGC orders. Submit your content as a video link, get feedback, and receive secure payouts once your work is approved. Build a commercial portfolio alongside your degree.</p>
            </div>
            <div class="p-8 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800">
                <span class="text-3xl mb-4 block">🛡️</span>
                <h2 class="text-xl font-black text-gray-900 dark:text-white mb-3">Platform Integrity</h2>
                <p class="text-gray-600 dark:text-gray-400 text-sm leading-relaxed">Our admin team reviews creator verifications, approves payouts, moderates content submissions, manages disputes, and monitors platform health. Every financial transaction is recorded and auditable. The platform is designed for accountability at every step.</p>
            </div>
        </div>
    </section>

    <!-- Values -->
    <section class="max-w-7xl mx-auto px-6 md:px-12 pb-24">
        <div class="text-center mb-10">
            <h2 class="text-3xl font-black text-gray-900 dark:text-white">What we stand for</h2>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-5">
            <div class="p-6 bg-white dark:bg-gray-900 rounded-[1.5rem] border border-gray-100 dark:border-gray-800 text-center">
                <span class="text-3xl block mb-3">🔍</span>
                <h4 class="font-black text-gray-900 dark:text-white mb-1">Transparency</h4>
                <p class="text-xs text-gray-500">Clear pricing, honest workflows, no hidden fees.</p>
            </div>
            <div class="p-6 bg-white dark:bg-gray-900 rounded-[1.5rem] border border-gray-100 dark:border-gray-800 text-center">
                <span class="text-3xl block mb-3">🎯</span>
                <h4 class="font-black text-gray-900 dark:text-white mb-1">Authenticity</h4>
                <p class="text-xs text-gray-500">Real students, real opinions, real content that connects.</p>
            </div>
            <div class="p-6 bg-white dark:bg-gray-900 rounded-[1.5rem] border border-gray-100 dark:border-gray-800 text-center">
                <span class="text-3xl block mb-3">⚡</span>
                <h4 class="font-black text-gray-900 dark:text-white mb-1">Speed</h4>
                <p class="text-xs text-gray-500">From brief to delivered content in days, not weeks.</p>
            </div>
            <div class="p-6 bg-white dark:bg-gray-900 rounded-[1.5rem] border border-gray-100 dark:border-gray-800 text-center">
                <span class="text-3xl block mb-3">🤝</span>
                <h4 class="font-black text-gray-900 dark:text-white mb-1">Fairness</h4>
                <p class="text-xs text-gray-500">Creators get paid on time. Brands get what they paid for.</p>
            </div>
        </div>
    </section>

</div>

<?php include 'includes/footer.php'; ?>
