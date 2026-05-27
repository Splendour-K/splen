<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
include 'includes/header.php';
?>

<main class="space-y-24 mb-24">
    <!-- Hero -->
    <div class="relative">
        <div aria-hidden="true" class="absolute inset-0 grid grid-cols-2 -space-x-52 opacity-40 dark:opacity-20">
            <div class="blur-[106px] h-56 bg-gradient-to-br from-primary to-purple-400 dark:from-blue-700"></div>
            <div class="blur-[106px] h-32 bg-gradient-to-r from-cyan-400 to-sky-300 dark:to-indigo-600"></div>
        </div>
        <div class="max-w-7xl mx-auto px-6 md:px-12">
            <div class="relative pt-36 ml-auto">
                <div class="lg:w-2/3 text-center mx-auto">
                    <h1 class="text-gray-900 dark:text-white font-bold text-5xl md:text-6xl xl:text-7xl">Simple, <span class="text-primary dark:text-white">transparent</span> pricing.</h1>
                    <p class="mt-8 text-gray-700 dark:text-gray-300">No subscriptions. No retainers. No hidden fees. You set the content budget — we charge a single, flat service fee only when a campaign succeeds.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Pricing Cards -->
    <div>
        <div class="max-w-7xl mx-auto px-6 md:px-12">
            <div class="grid md:grid-cols-2 gap-8 max-w-4xl mx-auto">
                <!-- Brands -->
                <div class="bg-white dark:bg-gray-900 rounded-3xl border border-gray-100 dark:border-gray-800 shadow-2xl shadow-gray-600/10 p-8 md:p-12 transition hover:scale-[1.01]">
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">For Brands</p>
                    <h3 class="text-2xl font-bold text-gray-800 dark:text-white mb-1">Pay-per-success</h3>
                    <p class="text-gray-500 text-sm mb-8">Only pay Splennet a fee when content is approved.</p>
                    <div class="text-6xl font-black text-primary mb-2">15%</div>
                    <p class="text-sm text-gray-500 mb-8">platform fee on approved campaign value</p>
                    <ul class="space-y-3 mb-10">
                        <?php foreach ([
                            'Unlimited campaigns, contests & UGC orders',
                            'Verified student creator pool',
                            'Escrow wallet — funds reserved before work begins',
                            'Admin-moderated content review',
                            'In-app messaging with creators',
                            'View count & CPM performance tracking',
                            'Dispute resolution support',
                            'No monthly subscription',
                        ] as $feature): ?>
                        <li class="flex items-start gap-3 text-sm text-gray-600 dark:text-gray-300">
                            <span class="text-primary font-black flex-shrink-0">✓</span>
                            <?php echo $feature; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <a href="register.php?role=brand" class="relative flex h-12 w-full items-center justify-center px-8 before:absolute before:inset-0 before:rounded-full before:bg-primary before:transition before:duration-300 hover:before:scale-105 active:duration-75 active:before:scale-95">
                        <span class="relative text-base font-semibold text-white">Create Brand Account</span>
                    </a>
                    <p class="text-xs text-gray-400 text-center mt-3">Brand invite code required. <a href="mailto:founders@splennet.com" class="text-primary hover:underline">Request access →</a></p>
                </div>

                <!-- Creators -->
                <div class="bg-white dark:bg-gray-900 rounded-3xl border-2 border-primary shadow-2xl shadow-primary/10 p-8 md:p-12 transition hover:scale-[1.01] relative">
                    <span class="absolute -top-4 left-1/2 -translate-x-1/2 px-4 py-1 bg-primary text-white text-xs font-black rounded-full uppercase tracking-widest">Free Forever</span>
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">For Creators</p>
                    <h3 class="text-2xl font-bold text-gray-800 dark:text-white mb-1">Keep what you earn</h3>
                    <p class="text-gray-500 text-sm mb-8">Zero platform fees. Your earnings are 100% yours.</p>
                    <div class="text-6xl font-black text-secondary mb-2">Free</div>
                    <p class="text-sm text-gray-500 mb-8">no fees, no commissions deducted from creators</p>
                    <ul class="space-y-3 mb-10">
                        <?php foreach ([
                            'Browse all active campaigns & contests',
                            'Apply for UGC orders directly',
                            'Student verification (1–2 business days)',
                            'Secure escrow-backed payout',
                            'CPM earnings on performance milestones',
                            'Portfolio & earnings dashboard',
                            'In-app messaging with brands',
                            'Community access',
                        ] as $feature): ?>
                        <li class="flex items-start gap-3 text-sm text-gray-600 dark:text-gray-300">
                            <span class="text-secondary font-black flex-shrink-0">✓</span>
                            <?php echo $feature; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <a href="register.php?role=creator" class="relative flex h-12 w-full items-center justify-center px-8 before:absolute before:inset-0 before:rounded-full before:border before:border-transparent before:bg-primary/10 before:bg-gradient-to-b before:transition before:duration-300 hover:before:scale-105 active:duration-75 active:before:scale-95 dark:before:border-gray-700 dark:before:bg-gray-800">
                        <span class="relative text-base font-semibold text-primary dark:text-white">Join as Creator</span>
                    </a>
                    <p class="text-xs text-gray-400 text-center mt-3">Must be an enrolled student. <a href="<?php echo APP_URL; ?>creators.php" class="text-primary hover:underline">Learn more →</a></p>
                </div>
            </div>
        </div>
    </div>

    <!-- How the fee works -->
    <div class="pb-4">
        <div class="max-w-4xl mx-auto px-6 md:px-12">
            <div class="p-8 md:p-12 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                <h2 class="text-2xl font-black text-gray-900 dark:text-white mb-6 text-center">How the 15% service fee works</h2>
                <div class="grid md:grid-cols-3 gap-6">
                    <div class="text-center p-5">
                        <div class="w-12 h-12 bg-primary/10 text-primary rounded-2xl flex items-center justify-center text-xl mx-auto mb-4">💳</div>
                        <h4 class="font-bold text-gray-900 dark:text-white mb-2">1. Fund your wallet</h4>
                        <p class="text-xs text-gray-500">Top up your brand wallet with the total campaign budget. Funds are held in escrow — safe, transparent, auditable.</p>
                    </div>
                    <div class="text-center p-5">
                        <div class="w-12 h-12 bg-primary/10 text-primary rounded-2xl flex items-center justify-center text-xl mx-auto mb-4">✅</div>
                        <h4 class="font-bold text-gray-900 dark:text-white mb-2">2. Approve content</h4>
                        <p class="text-xs text-gray-500">Review submitted videos. Only approve content you're happy with. No content, no fee — you're only charged on what you accept.</p>
                    </div>
                    <div class="text-center p-5">
                        <div class="w-12 h-12 bg-primary/10 text-primary rounded-2xl flex items-center justify-center text-xl mx-auto mb-4">🚀</div>
                        <h4 class="font-bold text-gray-900 dark:text-white mb-2">3. Payout processed</h4>
                        <p class="text-xs text-gray-500">The 15% fee is deducted at payout time. The creator receives their agreed fee. Splennet takes 15% of that amount.</p>
                    </div>
                </div>

                <!-- Example -->
                <div class="mt-8 p-6 bg-gray-50 dark:bg-gray-800 rounded-2xl">
                    <p class="text-xs font-black uppercase tracking-widest text-gray-400 mb-4">Example</p>
                    <div class="grid grid-cols-3 text-center gap-4">
                        <div>
                            <p class="text-2xl font-black text-gray-900 dark:text-white">GHS 200</p>
                            <p class="text-xs text-gray-500 mt-1">Agreed creator fee</p>
                        </div>
                        <div>
                            <p class="text-2xl font-black text-primary">GHS 30</p>
                            <p class="text-xs text-gray-500 mt-1">Splennet fee (15%)</p>
                        </div>
                        <div>
                            <p class="text-2xl font-black text-green-600">GHS 170</p>
                            <p class="text-xs text-gray-500 mt-1">Creator receives</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
