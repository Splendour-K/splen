<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_role('creator');

include '../includes/header.php';
?>

<div class="pt-24 min-h-screen bg-gray-50 dark:bg-gray-950">
    <div class="max-w-7xl mx-auto px-6 md:px-12 flex flex-col md:flex-row gap-8 py-8">
        <!-- Sidebar -->
        <?php include '../includes/creator_sidebar.php'; ?>

        <!-- Main Content -->
        <main class="flex-1 space-y-8">
            <header class="p-8 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm relative overflow-hidden">
                <div aria-hidden="true" class="absolute top-0 right-0 -mr-16 -mt-16 w-64 h-64 bg-primary/5 rounded-full blur-3xl"></div>
                <div class="relative">
                    <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Help & Support</h2>
                    <p class="text-gray-600 dark:text-gray-400 mt-2">Need help with your jobs or payouts?</p>
                </div>
            </header>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- FAQ Section -->
                <section class="space-y-4">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-6">Frequently Asked Questions</h3>
                    
                    <div class="p-6 bg-white dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-800 shadow-sm">
                        <p class="font-bold text-gray-900 dark:text-white">How do I get paid?</p>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">Once your video is approved by the brand, the payment is moved to your 'Available' balance and you can request a payout.</p>
                    </div>

                    <div class="p-6 bg-white dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-800 shadow-sm">
                        <p class="font-bold text-gray-900 dark:text-white">What if a brand requests a revision?</p>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">Check the 'My Jobs' section. You'll see feedback and an option to re-upload your video with the requested changes.</p>
                    </div>

                    <div class="p-6 bg-white dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-800 shadow-sm">
                        <p class="font-bold text-gray-900 dark:text-white">Can I cancel an application?</p>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">Yes, you can cancel any 'Pending' application directly from the 'My Applications' page.</p>
                    </div>
                </section>

                <!-- Contact Section -->
                <section class="p-8 bg-primary rounded-[3rem] text-white shadow-xl shadow-primary/20 flex flex-col justify-center items-center text-center">
                    <div class="w-20 h-20 bg-white/20 rounded-full flex items-center justify-center text-4xl mb-6">📩</div>
                    <h3 class="text-2xl font-black mb-4">Still need help?</h3>
                    <p class="text-primary-soft/80 mb-8 leading-relaxed">Our support team is available 24/7 to help you with any issues regarding brands or payments.</p>
                    <a href="mailto:support@splennet.com" class="px-8 py-4 bg-white text-primary font-black rounded-2xl hover:bg-gray-100 transition shadow-lg">Contact Support</a>
                </section>
            </div>
        </main>
    </div>
</div>

<?php 
include '../includes/footer.php';
?>