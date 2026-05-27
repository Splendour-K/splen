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

    <!-- How It Works -->
    <div id="how-it-works">
        <div class="max-w-7xl mx-auto px-6 md:px-12">
            <div class="text-center mb-16">
                <p class="text-[10px] font-black uppercase tracking-[0.35em] text-secondary mb-4">How it works</p>
                <h2 class="text-3xl font-bold text-gray-900 dark:text-white md:text-4xl">From brief to approved content in days</h2>
                <p class="mt-4 text-gray-600 dark:text-gray-300 max-w-xl mx-auto">Splennet handles every step — so brands get quality content and creators get paid fairly, every time.</p>
            </div>
            <div class="grid md:grid-cols-2 gap-16 items-start">
                <!-- For Brands -->
                <div>
                    <p class="text-xs font-black uppercase tracking-widest text-primary mb-6">For Brands</p>
                    <div class="space-y-6">
                        <div class="flex gap-4">
                            <div class="w-9 h-9 rounded-xl bg-primary text-white font-black flex items-center justify-center text-sm flex-shrink-0">1</div>
                            <div>
                                <h4 class="font-bold text-gray-900 dark:text-white">Post a brief</h4>
                                <p class="text-sm text-gray-500 mt-1">Describe your campaign, content requirements, budget, and deadline. Choose from campaigns, UGC orders, or contests.</p>
                            </div>
                        </div>
                        <div class="flex gap-4">
                            <div class="w-9 h-9 rounded-xl bg-primary text-white font-black flex items-center justify-center text-sm flex-shrink-0">2</div>
                            <div>
                                <h4 class="font-bold text-gray-900 dark:text-white">Review applications</h4>
                                <p class="text-sm text-gray-500 mt-1">Browse creator profiles and proposals. Shortlist the right fit and approve who proceeds to create.</p>
                            </div>
                        </div>
                        <div class="flex gap-4">
                            <div class="w-9 h-9 rounded-xl bg-primary text-white font-black flex items-center justify-center text-sm flex-shrink-0">3</div>
                            <div>
                                <h4 class="font-bold text-gray-900 dark:text-white">Approve & pay</h4>
                                <p class="text-sm text-gray-500 mt-1">Review submitted content. Approve what you love — payment releases automatically from escrow.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- For Creators -->
                <div>
                    <p class="text-xs font-black uppercase tracking-widest text-secondary mb-6">For Creators</p>
                    <div class="space-y-6">
                        <div class="flex gap-4">
                            <div class="w-9 h-9 rounded-xl bg-secondary text-white font-black flex items-center justify-center text-sm flex-shrink-0">1</div>
                            <div>
                                <h4 class="font-bold text-gray-900 dark:text-white">Verify your student status</h4>
                                <p class="text-sm text-gray-500 mt-1">Submit your student ID to get verified. This unlocks your ability to apply for all active opportunities.</p>
                            </div>
                        </div>
                        <div class="flex gap-4">
                            <div class="w-9 h-9 rounded-xl bg-secondary text-white font-black flex items-center justify-center text-sm flex-shrink-0">2</div>
                            <div>
                                <h4 class="font-bold text-gray-900 dark:text-white">Apply for briefs</h4>
                                <p class="text-sm text-gray-500 mt-1">Browse the catalog of campaigns, UGC orders, and contests. Apply with a short pitch and portfolio link.</p>
                            </div>
                        </div>
                        <div class="flex gap-4">
                            <div class="w-9 h-9 rounded-xl bg-secondary text-white font-black flex items-center justify-center text-sm flex-shrink-0">3</div>
                            <div>
                                <h4 class="font-bold text-gray-900 dark:text-white">Create, submit, earn</h4>
                                <p class="text-sm text-gray-500 mt-1">Produce your content, submit the link, and get paid once the brand approves. It's that straightforward.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Why Splennet -->
    <div id="features">
        <div class="max-w-7xl mx-auto px-6 md:px-12">
            <div class="md:w-2/3 lg:w-1/2 mb-16">
                <h2 class="text-2xl font-bold text-gray-700 dark:text-white md:text-4xl">
                    A better way to source UGC
                </h2>
                <p class="mt-4 text-gray-600 dark:text-gray-300">Splennet removes the friction from brand-creator collaboration. No agency overhead. No unreliable freelancers. Just verified students, clear briefs, and escrow-backed payments.</p>
            </div>
            <div class="grid divide-x divide-y divide-gray-100 dark:divide-gray-700 overflow-hidden rounded-3xl border border-gray-100 text-gray-600 dark:border-gray-700 sm:grid-cols-2 lg:grid-cols-3 lg:divide-y-0">
                <div class="group relative bg-white dark:bg-gray-800 transition hover:z-[1] hover:shadow-2xl hover:shadow-gray-600/10">
                    <div class="relative space-y-8 py-12 p-8">
                        <div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center text-primary text-2xl">✅</div>
                        <div class="space-y-2">
                            <h5 class="text-xl font-bold text-gray-800 dark:text-white transition group-hover:text-secondary">Verified Creators</h5>
                            <p class="text-gray-700 dark:text-gray-300 font-medium">Every creator on Splennet is a verified enrolled student. No fake accounts, no bots — just real campus voices.</p>
                        </div>
                    </div>
                </div>
                <div class="group relative bg-white dark:bg-gray-800 transition hover:z-[1] hover:shadow-2xl hover:shadow-gray-600/10">
                    <div class="relative space-y-8 py-12 p-8">
                        <div class="w-12 h-12 rounded-full bg-secondary/10 flex items-center justify-center text-secondary text-2xl">🎬</div>
                        <div class="space-y-2">
                            <h5 class="text-xl font-bold text-gray-800 dark:text-white transition group-hover:text-secondary">Campaigns, Contests & UGC Orders</h5>
                            <p class="text-gray-700 dark:text-gray-300 font-medium">Three flexible content formats — pick what works for your campaign goal and scale accordingly.</p>
                        </div>
                    </div>
                </div>
                <div class="group relative bg-white dark:bg-gray-800 transition hover:z-[1] hover:shadow-2xl hover:shadow-gray-600/10">
                    <div class="relative space-y-8 py-12 p-8">
                        <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center text-green-700 text-2xl">💳</div>
                        <div class="space-y-2">
                            <h5 class="text-xl font-bold text-gray-800 dark:text-white transition group-hover:text-secondary">Escrow-Protected Payments</h5>
                            <p class="text-gray-700 dark:text-gray-300 font-medium">Funds are held securely and only released when you approve the content. Zero risk of paying for work you don't use.</p>
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
                        Brands get authentic content. Creators get paid. Everyone wins.
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
