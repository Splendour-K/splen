<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
include 'includes/header.php';
?>

<div class="pt-36 pb-20 bg-gray-50 dark:bg-gray-950 min-h-screen">
    <div class="max-w-3xl mx-auto px-6">
        <h1 class="text-4xl font-black text-gray-900 dark:text-white mb-8">Contact Us</h1>
        <div class="bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 p-12 shadow-sm">
            <p class="text-gray-600 dark:text-gray-400 mb-8 font-medium">Have questions or need assistance? Our team is here to help you scale your UGC efforts.</p>
            
            <form class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Name</label>
                        <input type="text" class="w-full px-5 py-4 rounded-2xl bg-gray-50 dark:bg-gray-800 border-none focus:ring-2 focus:ring-primary outline-none dark:text-white font-bold transition">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Email</label>
                        <input type="email" class="w-full px-5 py-4 rounded-2xl bg-gray-50 dark:bg-gray-800 border-none focus:ring-2 focus:ring-primary outline-none dark:text-white font-bold transition">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Message</label>
                    <textarea rows="5" class="w-full px-5 py-4 rounded-2xl bg-gray-50 dark:bg-gray-800 border-none focus:ring-2 focus:ring-primary outline-none dark:text-white font-bold transition"></textarea>
                </div>
                <button type="submit" class="px-8 py-4 bg-primary text-white font-bold rounded-2xl hover:scale-[1.02] transition shadow-lg shadow-primary/20">Send Message</button>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>