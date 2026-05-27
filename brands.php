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
                    <h1 class="text-gray-900 dark:text-white font-bold text-5xl md:text-6xl xl:text-7xl">Work with <span class="text-primary dark:text-white">authentic voices.</span></h1>
                    <p class="mt-8 text-gray-700 dark:text-gray-300">Get high-quality, high-converting social content from students who actually use and love your products. Authentic, relatable, and affordable.</p>
                    <div class="mt-16 flex flex-wrap justify-center gap-y-4 gap-x-6">
                        <a href="register.php?role=brand" class="relative flex h-11 w-full items-center justify-center px-6 before:absolute before:inset-0 before:rounded-full before:bg-primary before:transition before:duration-300 hover:before:scale-105 active:duration-75 active:before:scale-95 sm:w-max">
                            <span class="relative text-base font-semibold text-white">Start Hiring Students</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Value Prop Grid -->
    <div>
        <div class="max-w-7xl mx-auto px-6 md:px-12">
            <div class="grid divide-x divide-y divide-gray-100 dark:divide-gray-700 overflow-hidden rounded-3xl border border-gray-100 text-gray-600 dark:border-gray-700 sm:grid-cols-2 lg:divide-y-0">
                <div class="group relative bg-white dark:bg-gray-800 transition hover:z-[1] hover:shadow-2xl hover:shadow-gray-600/10">
                    <div class="relative space-y-8 py-12 p-8">
                        <div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center text-primary text-2xl">💰</div>
                        <div class="space-y-2">
                            <h5 class="text-xl font-semibold text-gray-700 dark:text-white transition group-hover:text-secondary">Affordable Content</h5>
                            <p class="text-gray-600 dark:text-gray-300">Traditional production costs a fraction of an agency retainer. You set the budget — student creators deliver quality UGC at a price that actually scales.</p>
                        </div>
                    </div>
                </div>
                <div class="group relative bg-white dark:bg-gray-800 transition hover:z-[1] hover:shadow-2xl hover:shadow-gray-600/10">
                    <div class="relative space-y-8 py-12 p-8">
                        <div class="w-12 h-12 rounded-full bg-secondary/10 flex items-center justify-center text-secondary text-2xl">🎯</div>
                        <div class="space-y-2">
                            <h5 class="text-xl font-semibold text-gray-700 dark:text-white transition group-hover:text-secondary">Campus Reach</h5>
                            <p class="text-gray-600 dark:text-gray-300">Our creators are active in their university communities, giving your brand organic visibility where it counts most.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Content Types -->
    <div id="content-types" class="pb-20">
        <div class="max-w-7xl mx-auto px-6 md:px-12">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-bold text-gray-900 dark:text-white md:text-4xl">Content for every objective</h2>
                <p class="mt-4 text-gray-600 dark:text-gray-300">Student creators specialize in the formats that perform best on TikTok, Reels, and YouTube Shorts.</p>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-6">
                <div class="p-6 bg-gray-50 dark:bg-gray-900 rounded-3xl border border-gray-100 dark:border-gray-800 transition hover:scale-105 hover:shadow-lg">
                    <span class="text-3xl mb-3 block">📦</span>
                    <h4 class="text-base font-bold text-gray-800 dark:text-white mb-1">Unboxings</h4>
                    <p class="text-xs text-gray-500">First-impression reactions that drive purchase intent and social shares.</p>
                </div>
                <div class="p-6 bg-gray-50 dark:bg-gray-900 rounded-3xl border border-gray-100 dark:border-gray-800 transition hover:scale-105 hover:shadow-lg">
                    <span class="text-3xl mb-3 block">⭐</span>
                    <h4 class="text-base font-bold text-gray-800 dark:text-white mb-1">Product Reviews</h4>
                    <p class="text-xs text-gray-500">Honest, peer-to-peer testimonials that build trust with student audiences.</p>
                </div>
                <div class="p-6 bg-gray-50 dark:bg-gray-900 rounded-3xl border border-gray-100 dark:border-gray-800 transition hover:scale-105 hover:shadow-lg">
                    <span class="text-3xl mb-3 block">🎓</span>
                    <h4 class="text-base font-bold text-gray-800 dark:text-white mb-1">Campus Lifestyle</h4>
                    <p class="text-xs text-gray-500">Organic product integration into everyday campus life for maximum relatability.</p>
                </div>
                <div class="p-6 bg-gray-50 dark:bg-gray-900 rounded-3xl border border-gray-100 dark:border-gray-800 transition hover:scale-105 hover:shadow-lg">
                    <span class="text-3xl mb-3 block">📱</span>
                    <h4 class="text-base font-bold text-gray-800 dark:text-white mb-1">App Demos</h4>
                    <p class="text-xs text-gray-500">Screen-recorded walkthroughs and feature highlights that convert app installs.</p>
                </div>
                <div class="p-6 bg-gray-50 dark:bg-gray-900 rounded-3xl border border-gray-100 dark:border-gray-800 transition hover:scale-105 hover:shadow-lg">
                    <span class="text-3xl mb-3 block">👗</span>
                    <h4 class="text-base font-bold text-gray-800 dark:text-white mb-1">Fashion Try-ons</h4>
                    <p class="text-xs text-gray-500">Authentic outfit showcases that drive direct traffic to product pages.</p>
                </div>
                <div class="p-6 bg-gray-50 dark:bg-gray-900 rounded-3xl border border-gray-100 dark:border-gray-800 transition hover:scale-105 hover:shadow-lg">
                    <span class="text-3xl mb-3 block">🍕</span>
                    <h4 class="text-base font-bold text-gray-800 dark:text-white mb-1">Food & Drink</h4>
                    <p class="text-xs text-gray-500">Taste-test and dining content with the energy and appetite of a student audience.</p>
                </div>
            </div>

            <!-- CTA Strip -->
            <div class="mt-16 p-8 md:p-10 bg-gray-900 dark:bg-gray-800 rounded-[2rem] flex flex-col sm:flex-row items-center justify-between gap-6">
                <div>
                    <h3 class="text-xl font-black text-white">Ready to source your first campaign?</h3>
                    <p class="text-gray-400 text-sm mt-1">Create a brand account in under two minutes.</p>
                </div>
                <a href="register.php?role=brand" class="px-8 py-3 bg-primary text-white font-bold rounded-full hover:scale-105 transition flex-shrink-0">Get Started →</a>
            </div>
        </div>
    </div>
</main>

<?php include "includes/footer.php"; ?>
