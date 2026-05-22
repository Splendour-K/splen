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
                    <h1 class="text-gray-900 dark:text-white font-bold text-5xl md:text-6xl xl:text-7xl">Simple, <span class="text-primary dark:text-white">transparent</span> pricing.</h1>
                    <p class="mt-8 text-gray-700 dark:text-gray-300">No hidden fees. No subscription traps. You set the budget, we handle the friction.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Pricing Cards -->
    <div class="pb-20">
        <div class="max-w-7xl mx-auto px-6 md:px-12">
            <div class="grid md:grid-cols-2 gap-8 max-w-4xl mx-auto">
                <!-- Brands -->
                <div class="bg-white dark:bg-gray-900 rounded-3xl border border-gray-100 dark:border-gray-800 shadow-2xl shadow-gray-600/10 p-8 md:p-12 transition hover:scale-[1.02]">
                    <h3 class="text-2xl font-bold text-gray-800 dark:text-white mb-2">For Brands</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-8">Pay per success</p>
                    <div class="text-5xl font-black text-primary mb-6">15%</div>
                    <p class="text-gray-600 dark:text-gray-300 mb-12 italic leading-relaxed">
                        Platform service fee per successful campaign. We only win when you get the content you love.
                    </p>
                    <a href="register.php?role=brand" class="relative flex h-12 w-full items-center justify-center px-8 before:absolute before:inset-0 before:rounded-full before:bg-primary before:transition before:duration-300 hover:before:scale-105 active:duration-75 active:before:scale-95">
                        <span class="relative text-base font-semibold text-white">Create Brand Account</span>
                    </a>
                </div>

                <!-- Creators -->
                <div class="bg-white dark:bg-gray-900 rounded-3xl border border-primary dark:border-primary/50 shadow-2xl shadow-primary/10 p-8 md:p-12 transition hover:scale-[1.02]">
                    <h3 class="text-2xl font-bold text-gray-800 dark:text-white mb-2">For Creators</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-8">Keep what you earn</p>
                    <div class="text-5xl font-black text-secondary mb-6">Free</div>
                    <p class="text-gray-600 dark:text-gray-300 mb-12 italic leading-relaxed">
                        It is completely free for students to join, apply for jobs, and build their creator portfolios.
                    </p>
                    <a href="register.php?role=creator" class="relative flex h-12 w-full items-center justify-center px-8 before:absolute before:inset-0 before:rounded-full before:border before:border-transparent before:bg-primary/10 before:bg-gradient-to-b before:transition before:duration-300 hover:before:scale-105 active:duration-75 active:before:scale-95 dark:before:border-gray-700 dark:before:bg-gray-800">
                        <span class="relative text-base font-semibold text-primary dark:text-white">Join as Creator</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
