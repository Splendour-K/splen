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
                    <h1 class="text-gray-900 dark:text-white font-bold text-5xl md:text-6xl xl:text-7xl">Verified student creators for <span class="text-primary dark:text-white">authentic content.</span></h1>
                    <p class="mt-8 text-gray-700 dark:text-gray-300">Splennet connects brands with verified university students who create high-performing short videos for TikTok, Reels, and ads.</p>
                    <div class="mt-16 flex flex-wrap justify-center gap-y-4 gap-x-6">
                        <a href="<?php echo APP_URL; ?>register.php?role=brand" class="relative flex h-11 w-full items-center justify-center px-6 before:absolute before:inset-0 before:rounded-full before:bg-primary before:transition before:duration-300 hover:before:scale-105 active:duration-75 active:before:scale-95 sm:w-max">
                            <span class="relative text-base font-semibold text-white">Post a campaign</span>
                        </a>
                        <a href="<?php echo APP_URL; ?>register.php?role=creator" class="relative flex h-11 w-full items-center justify-center px-6 before:absolute before:inset-0 before:rounded-full before:border before:border-transparent before:bg-primary/10 before:bg-gradient-to-b before:transition before:duration-300 hover:before:scale-105 active:duration-75 active:before:scale-95 dark:before:border-gray-700 dark:before:bg-gray-800 sm:w-max">
                            <span class="relative text-base font-semibold text-primary dark:text-white">Join as creator</span>
                        </a>
                    </div>
                    <div class="hidden py-8 mt-16 border-y border-gray-100 dark:border-gray-800 sm:flex justify-between">
                        <div class="text-left">
                            <h6 class="text-xl font-bold text-gray-800 dark:text-white">3x Affordable</h6>
                            <p class="mt-2 text-gray-600 dark:text-gray-400 font-medium">No agency overhead costs</p>
                        </div>
                        <div class="text-left border-l border-gray-100 dark:border-gray-800 pl-8">
                            <h6 class="text-xl font-bold text-gray-800 dark:text-white">48h Turnaround</h6>
                            <p class="mt-2 text-gray-600 dark:text-gray-400 font-medium">Agile student network</p>
                        </div>
                        <div class="text-left border-l border-gray-100 dark:border-gray-800 pl-8">
                            <h6 class="text-xl font-bold text-gray-800 dark:text-white">100% Authentic</h6>
                            <p class="mt-2 text-gray-600 dark:text-gray-400 font-medium">Real campus voices</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Featured Briefs Section -->
    <div id="briefs" class="max-w-7xl mx-auto px-6 md:px-12">
        <div class="flex items-center justify-between mb-12">
            <div>
                <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Featured Briefs</h2>
                <p class="text-gray-600 dark:text-gray-400 mt-2">Active campaigns from top brands hiring now.</p>
            </div>
            <a href="register.php?role=creator" class="text-primary font-bold hover:underline">View All &rarr;</a>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <?php
            $featured = [];
            try {
                $stmt = $pdo->query("SELECT c.*, COALESCE(b.brand_name, 'Brand') AS brand_name FROM campaigns c LEFT JOIN brands b ON c.brand_id = b.id WHERE c.status = 'published' ORDER BY c.created_at DESC LIMIT 3");
                if ($stmt !== false) {
                    $featured = $stmt->fetchAll();
                }
            } catch (Throwable $e) {
                error_log('Homepage featured briefs query failed: ' . $e->getMessage());
                $featured = [];
            }
            foreach ($featured as $f):
            ?>
                <div class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm hover:shadow-xl transition-all group">
                    <span class="px-3 py-1 bg-orange-100 text-orange-600 text-[9px] font-black uppercase rounded-full tracking-widest mb-4 inline-block">Featured</span>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2"><?php echo e($f["title"]); ?></h3>
                    <p class="text-sm text-gray-500 mb-6">By <span class="text-primary font-bold"><?php echo e($f["brand_name"]); ?></span></p>
                    <div class="flex items-center justify-between">
                        <span class="text-lg font-black text-gray-900 dark:text-white"><?php echo $f["currency"]; ?> <?php echo number_format($f["budget_per_creator"]); ?></span>
                        <a href="register.php?role=creator" class="w-10 h-10 bg-gray-900 dark:bg-white text-white dark:text-gray-900 rounded-full flex items-center justify-center group-hover:bg-primary group-hover:text-white transition-all">&rarr;</a>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($featured)): ?>
                <div class="col-span-3 py-12 text-center text-gray-400 font-bold uppercase tracking-widest text-xs border border-dashed border-gray-200 dark:border-gray-800 rounded-[2.5rem]">
                    New briefs landing soon. ⚡
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Features Section -->
    <div id="features">
        <div class="max-w-7xl mx-auto px-6 md:px-12">
            <div class="md:w-2/3 lg:w-1/2">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6 text-secondary">
                    <path fill-rule="evenodd" d="M9 4.5a.75.75 0 01.721.544l.813 2.846a3.75 3.75 0 002.576 2.576l2.846.813a.75.75 0 010 1.442l-2.846.813a3.75 3.75 0 00-2.576 2.576l-.813 2.846a.75.75 0 01-1.442 0l-.813-2.846a3.75 3.75 0 00-2.576-2.576l-2.846-.813a.75.75 0 010-1.442l2.846-.813A3.75 3.75 0 007.466 7.89l.813-2.846A.75.75 0 019 4.5z" clip-rule="evenodd" />
                </svg>
                <h2 class="my-8 text-2xl font-bold text-gray-700 dark:text-white md:text-4xl">
                    A better way to source UGC
                </h2>
                <p class="text-gray-600 dark:text-gray-300">Splennet simplifies the process of working with student creators. From discovery to payout, we handle the friction so you can focus on growth.</p>
            </div>
            <div class="mt-16 grid divide-x divide-y divide-gray-100 dark:divide-gray-700 overflow-hidden rounded-3xl border border-gray-100 text-gray-600 dark:border-gray-700 sm:grid-cols-2 lg:grid-cols-3 lg:divide-y-0 xl:grid-cols-3">
                <div class="group relative bg-white dark:bg-gray-800 transition hover:z-[1] hover:shadow-2xl hover:shadow-gray-600/10">
                    <div class="relative space-y-8 py-12 p-8">
                        <div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center text-primary text-2xl">👤</div>
                        <div class="space-y-2">
                            <h5 class="text-xl font-bold text-gray-800 dark:text-white transition group-hover:text-secondary">Top Creators</h5>
                            <p class="text-gray-700 dark:text-gray-300 font-medium">Ama from UG. Pro UGC creator for Beauty and Lifestyle brands.</p>
                        </div>
                    </div>
                </div>
                <div class="group relative bg-white dark:bg-gray-800 transition hover:z-[1] hover:shadow-2xl hover:shadow-gray-600/10">
                    <div class="relative space-y-8 py-12 p-8">
                        <div class="w-12 h-12 rounded-full bg-secondary/10 flex items-center justify-center text-secondary text-2xl">🎬</div>
                        <div class="space-y-2">
                            <h5 class="text-xl font-bold text-gray-800 dark:text-white transition group-hover:text-secondary">Active Briefs</h5>
                            <p class="text-gray-700 dark:text-gray-300 font-medium">GlowSkin Africa is hiring 5 creators for a skincare routine. $50/video.</p>
                        </div>
                    </div>
                </div>
                <div class="group relative bg-white dark:bg-gray-800 transition hover:z-[1] hover:shadow-2xl hover:shadow-gray-600/10">
                    <div class="relative space-y-8 py-12 p-8">
                        <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center text-green-700 text-2xl">💳</div>
                        <div class="space-y-2">
                            <h5 class="text-xl font-bold text-gray-800 dark:text-white transition group-hover:text-secondary">Secure Escrow</h5>
                            <p class="text-gray-700 dark:text-gray-300 font-medium">Money is held safely and only released once you approve the video link.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Call To Action -->
    <div class="relative py-16">
        <div aria-hidden="true" class="absolute inset-0 h-max w-full m-auto grid grid-cols-2 -space-x-52 opacity-40 dark:opacity-20">
            <div class="blur-[106px] h-56 bg-gradient-to-br from-primary to-purple-400 dark:from-blue-700"></div>
            <div class="blur-[106px] h-32 bg-gradient-to-r from-cyan-400 to-sky-300 dark:to-indigo-600"></div>
        </div>
        <div class="max-w-7xl mx-auto px-6 md:px-12">
            <div class="relative">
                <div class="flex items-center justify-center -space-x-2">
                    <img loading="lazy" width="400" height="400" src="https://plus.unsplash.com/premium_photo-1683121366370-627bca061be5?q=80&w=2070&auto=format&fit=crop" alt="member photo" class="h-8 w-8 rounded-full object-cover">
                    <img loading="lazy" width="200" height="200" src="https://images.unsplash.com/photo-1540569014015-19a7be504e3a?q=80&w=1935&auto=format&fit=crop" alt="member photo" class="h-12 w-12 rounded-full object-cover">
                    <img loading="lazy" width="200" height="200" src="https://images.unsplash.com/photo-1522075469751-3a6694fb2f61?q=80&w=1780&auto=format&fit=crop" alt="member photo" class="z-10 h-16 w-16 rounded-full object-cover">
                    <img loading="lazy" width="200" height="200" src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?q=80&w=1887&auto=format&fit=crop" alt="member photo" class="relative h-12 w-12 rounded-full object-cover">
                    <img loading="lazy" width="200" height="200" src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?q=80&w=1887&auto=format&fit=crop" alt="member photo" class="h-8 w-8 rounded-full object-cover">
                </div>
                <div class="mt-6 m-auto space-y-6 md:w-8/12 lg:w-7/12">
                    <h1 class="text-center text-4xl font-bold text-gray-800 dark:text-white md:text-5xl">Ready to start?</h1>
                    <p class="text-center text-xl text-gray-600 dark:text-gray-300">
                        Join 100+ brands and 1000+ verified student creators today.
                    </p>
                    <div class="flex flex-wrap justify-center gap-6">
                        <a href="register.php" class="relative flex h-12 w-full items-center justify-center px-8 before:absolute before:inset-0 before:rounded-full before:bg-primary before:transition before:duration-300 hover:before:scale-105 active:duration-75 active:before:scale-95 sm:w-max">
                            <span class="relative text-base font-semibold text-white">Get Started</span>
                        </a>
                        <a href="about.php" class="relative flex h-12 w-full items-center justify-center px-8 before:absolute before:inset-0 before:rounded-full before:border before:border-transparent before:bg-primary/10 before:bg-gradient-to-b before:transition before:duration-300 hover:before:scale-105 active:duration-75 active:before:scale-95 dark:before:border-gray-700 dark:before:bg-gray-800 sm:w-max">
                            <span class="relative text-base font-semibold text-primary dark:text-white">Learn More</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
