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
                            <p class="text-gray-600 dark:text-gray-300">Traditional production costs 10x more. Get professional UGC starting at just $30 per video, optimized for social algorithms.</p>
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
                <p class="mt-4 text-gray-600 dark:text-gray-300">Our creators specialize in various formats to help you achieve your marketing goals.</p>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-8">
                <div class="flex flex-col items-center p-8 bg-gray-50 dark:bg-gray-900 rounded-3xl border border-gray-100 dark:border-gray-800 transition hover:scale-105">
                    <span class="text-4xl mb-4">📦</span>
                    <h4 class="text-lg font-semibold text-gray-700 dark:text-white">Unboxings</h4>
                </div>
                <div class="flex flex-col items-center p-8 bg-gray-50 dark:bg-gray-900 rounded-3xl border border-gray-100 dark:border-gray-800 transition hover:scale-105">
                    <span class="text-4xl mb-4">⭐</span>
                    <h4 class="text-lg font-semibold text-gray-700 dark:text-white">Product Reviews</h4>
                </div>
                <div class="flex flex-col items-center p-8 bg-gray-50 dark:bg-gray-900 rounded-3xl border border-gray-100 dark:border-gray-800 transition hover:scale-105">
                    <span class="text-4xl mb-4">🎓</span>
                    <h4 class="text-lg font-semibold text-gray-700 dark:text-white">Campus Lifestyle</h4>
                </div>
                <div class="flex flex-col items-center p-8 bg-gray-50 dark:bg-gray-900 rounded-3xl border border-gray-100 dark:border-gray-800 transition hover:scale-105">
                    <span class="text-4xl mb-4">📱</span>
                    <h4 class="text-lg font-semibold text-gray-700 dark:text-white">App Demos</h4>
                </div>
                <div class="flex flex-col items-center p-8 bg-gray-50 dark:bg-gray-900 rounded-3xl border border-gray-100 dark:border-gray-800 transition hover:scale-105">
                    <span class="text-4xl mb-4">👗</span>
                    <h4 class="text-lg font-semibold text-gray-700 dark:text-white">Fashion Try-ons</h4>
                </div>
                <div class="flex flex-col items-center p-8 bg-gray-50 dark:bg-gray-900 rounded-3xl border border-gray-100 dark:border-gray-800 transition hover:scale-105">
                    <span class="text-4xl mb-4">🍕</span>
                    <h4 class="text-lg font-semibold text-gray-700 dark:text-white">Food & Drink</h4>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include "includes/footer.php"; ?>
