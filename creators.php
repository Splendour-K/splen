<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
include 'includes/header.php';
?>

<main class="space-y-40 mb-40">
    <!-- Hero Section -->
    <div class="relative" id="home">
        <div aria-hidden="true" class="absolute inset-0 grid grid-cols-2 -space-x-52 opacity-40 dark:opacity-20">
            <div class="blur-[106px] h-56 bg-gradient-to-br from-primary to-purple-400 dark:from-blue-700"></div>
            <div class="blur-[106px] h-32 bg-gradient-to-r from-cyan-400 to-sky-300 dark:to-indigo-600"></div>
        </div>
        <div class="max-w-7xl mx-auto px-6 md:px-12">
            <div class="relative pt-36 ml-auto">
                <div class="lg:w-2/3 text-center mx-auto">
                    <h1 class="text-gray-900 dark:text-white font-bold text-5xl md:text-6xl xl:text-7xl">Earn money <span class="text-primary dark:text-white">while studying.</span></h1>
                    <p class="mt-8 text-gray-700 dark:text-gray-300">You don't need to be an influencer. You just need to create good videos with your phone. Join the student UGC revolution.</p>
                    <div class="mt-16 flex flex-wrap justify-center gap-y-4 gap-x-6">
                        <a href="register.php?role=creator" class="relative flex h-11 w-full items-center justify-center px-6 before:absolute before:inset-0 before:rounded-full before:bg-primary before:transition before:duration-300 hover:before:scale-105 active:duration-75 active:before:scale-95 sm:w-max">
                            <span class="relative text-base font-semibold text-white">Join the Community</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Info Section -->
    <div>
        <div class="max-w-7xl mx-auto px-6 md:px-12">
            <div class="relative bg-secondary/10 dark:bg-gray-800 rounded-3xl p-8 md:p-16 border border-secondary/20 dark:border-gray-700">
                <div class="md:w-2/3">
                    <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-6">No huge following required.</h2>
                    <p class="text-xl text-gray-700 dark:text-gray-300 leading-relaxed">
                        Brands on Splennet care about your creativity and storytelling — not your subscriber count. If you can shoot an engaging, honest video on your phone, you already have what it takes.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Benefits Grid -->
    <div>
        <div class="max-w-7xl mx-auto px-6 md:px-12">
            <div class="grid divide-x divide-y divide-gray-100 dark:divide-gray-700 overflow-hidden rounded-3xl border border-gray-100 text-gray-600 dark:border-gray-700 sm:grid-cols-2 lg:grid-cols-3 lg:divide-y-0">
                <div class="group relative bg-white dark:bg-gray-800 transition hover:z-[1] hover:shadow-2xl hover:shadow-gray-600/10">
                    <div class="relative space-y-8 py-12 p-8">
                        <div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center text-primary text-2xl">⏰</div>
                        <div class="space-y-2">
                            <h5 class="text-xl font-semibold text-gray-700 dark:text-white transition group-hover:text-secondary">Work around your schedule</h5>
                            <p class="text-gray-600 dark:text-gray-300">Apply for opportunities that fit between your classes and exams. No long-term commitments — every job is on your terms.</p>
                        </div>
                    </div>
                </div>
                <div class="group relative bg-white dark:bg-gray-800 transition hover:z-[1] hover:shadow-2xl hover:shadow-gray-600/10">
                    <div class="relative space-y-8 py-12 p-8">
                        <div class="w-12 h-12 rounded-full bg-secondary/10 flex items-center justify-center text-secondary text-2xl">💼</div>
                        <div class="space-y-2">
                            <h5 class="text-xl font-semibold text-gray-700 dark:text-white transition group-hover:text-secondary">Build a real creator CV</h5>
                            <p class="text-gray-600 dark:text-gray-300">Every completed brand job adds to your portfolio. Graduate with verifiable commercial work experience — not just campus projects.</p>
                        </div>
                    </div>
                </div>
                <div class="group relative bg-white dark:bg-gray-800 transition hover:z-[1] hover:shadow-2xl hover:shadow-gray-600/10">
                    <div class="relative space-y-8 py-12 p-8">
                        <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center text-green-600 text-2xl">💰</div>
                        <div class="space-y-2">
                            <h5 class="text-xl font-semibold text-gray-700 dark:text-white transition group-hover:text-secondary">Guaranteed, secure payment</h5>
                            <p class="text-gray-600 dark:text-gray-300">Brand payments are held in escrow before work begins. Once your content is approved, your payout is processed — no chasing invoices.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Earnings Types -->
    <div class="pb-20">
        <div class="max-w-7xl mx-auto px-6 md:px-12">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Multiple ways to earn</h2>
                <p class="mt-3 text-gray-500">Different content types, different earning models — pick what suits you best.</p>
            </div>
            <div class="grid md:grid-cols-3 gap-6">
                <div class="p-8 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                    <span class="text-3xl mb-4 block">📢</span>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">Campaigns</h3>
                    <p class="text-sm text-gray-500">Apply to brand briefs, get shortlisted, and earn a fixed fee per approved video submission.</p>
                </div>
                <div class="p-8 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                    <span class="text-3xl mb-4 block">🏆</span>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">Contests</h3>
                    <p class="text-sm text-gray-500">Submit your best creative take on a brand challenge. Win a prize from the posted prize pool — results reviewed by the brand or by views.</p>
                </div>
                <div class="p-8 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                    <span class="text-3xl mb-4 block">🎥</span>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">UGC Orders</h3>
                    <p class="text-sm text-gray-500">Accept a direct content order from a brand, deliver the video, and earn your order fee plus potential CPM bonuses based on performance.</p>
                </div>
            </div>

            <!-- CTA -->
            <div class="mt-12 text-center">
                <a href="register.php?role=creator" class="inline-flex items-center gap-2 px-8 py-4 bg-primary text-white font-bold rounded-full hover:scale-105 transition text-base shadow-lg shadow-primary/30">
                    Start earning as a creator →
                </a>
                <p class="text-xs text-gray-400 mt-3">Free to join. Student verification required.</p>
            </div>
        </div>
    </div>
</main>

<?php include "includes/footer.php"; ?>
