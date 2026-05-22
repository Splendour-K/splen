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
                    <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-6">No huge followers needed.</h2>
                    <p class="text-xl text-gray-700 dark:text-gray-300 leading-relaxed">
                        Brands care about your creativity and personality, not your follower count. If you can shoot a clear, fun video on your phone, you're exactly who we're looking for.
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
                            <h5 class="text-xl font-semibold text-gray-700 dark:text-white transition group-hover:text-secondary">Create your own schedule</h5>
                            <p class="text-gray-600 dark:text-gray-300">Choose jobs that fit your classes and study time. You are your own boss.</p>
                        </div>
                    </div>
                </div>
                <div class="group relative bg-white dark:bg-gray-800 transition hover:z-[1] hover:shadow-2xl hover:shadow-gray-600/10">
                    <div class="relative space-y-8 py-12 p-8">
                        <div class="w-12 h-12 rounded-full bg-secondary/10 flex items-center justify-center text-secondary text-2xl">💼</div>
                        <div class="space-y-2">
                            <h5 class="text-xl font-semibold text-gray-700 dark:text-white transition group-hover:text-secondary">Build your portfolio</h5>
                            <p class="text-gray-600 dark:text-gray-300">Work with real brands and build a professional creator CV that stands out.</p>
                        </div>
                    </div>
                </div>
                <div class="group relative bg-white dark:bg-gray-800 transition hover:z-[1] hover:shadow-2xl hover:shadow-gray-600/10">
                    <div class="relative space-y-8 py-12 p-8">
                        <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center text-green-600 text-2xl">💰</div>
                        <div class="space-y-2">
                            <h5 class="text-xl font-semibold text-gray-700 dark:text-white transition group-hover:text-secondary">Get paid fairly</h5>
                            <p class="text-gray-600 dark:text-gray-300">Secure payments for every approved video. No chasing brands for your money.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include "includes/footer.php"; ?>
