<?php
require_once "../config/database.php";
require_once "../includes/functions.php";
require_role("creator");

$stmt = $pdo->prepare("SELECT * FROM creators WHERE user_id = ?");
$stmt->execute([$_SESSION["user_id"]]);
$creator = $stmt->fetch();
require_creator_record($creator);

$search = $_GET["search"] ?? "";
$category = $_GET["category"] ?? "";
$budget_min = $_GET["budget_min"] ?? "";
$country = $_GET["country"] ?? "";
$video_type = $_GET["video_type"] ?? "";
$sort = $_GET["sort"] ?? "newest";

$sql = "SELECT c.*, b.brand_name FROM campaigns c JOIN brands b ON c.brand_id = b.id WHERE c.status = \"published\"";
$params = [];
if ($search) { $sql .= " AND (c.title LIKE ? OR b.brand_name LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($category) { $sql .= " AND c.category = ?"; $params[] = $category; }
if ($budget_min) { $sql .= " AND c.budget_per_creator >= ?"; $params[] = $budget_min; }
if ($country) { $sql .= " AND (c.location_country = ? OR c.location_country = \"Global\")"; $params[] = $country; }
if ($video_type) { $sql .= " AND c.video_type = ?"; $params[] = $video_type; }

if ($sort === "newest") { $sql .= " ORDER BY c.is_featured DESC, c.created_at DESC"; } 
elseif ($sort === "highest_paying") { $sql .= " ORDER BY c.is_featured DESC, c.budget_per_creator DESC"; } 
elseif ($sort === "deadline") { $sql .= " ORDER BY c.is_featured DESC, c.deadline ASC"; }

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$campaigns = $stmt->fetchAll();

$categories = $pdo->query("SELECT DISTINCT category FROM campaigns WHERE status = \"published\"")->fetchAll(PDO::FETCH_COLUMN);
$countries = $pdo->query("SELECT DISTINCT location_country FROM campaigns WHERE status = \"published\"")->fetchAll(PDO::FETCH_COLUMN);

include "../includes/header.php";
?>
<div class="pt-24 min-h-screen bg-gray-50 dark:bg-gray-950">
    <div class="max-w-7xl mx-auto px-6 md:px-12 flex flex-col md:flex-row gap-8 py-8">
        <?php include "../includes/creator_sidebar.php"; ?>
        <main class="flex-1 space-y-8">
            <header class="p-8 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm relative overflow-hidden">
                <div aria-hidden="true" class="absolute top-0 right-0 -mr-16 -mt-16 w-64 h-64 bg-primary/5 rounded-full blur-3xl"></div>
                <div class="relative">
                    <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Browse Campaigns</h2>
                    <p class="text-gray-600 dark:text-gray-400 mt-2">Find the perfect brands to collaborate with.</p>
                </div>
            </header>

            <!-- Search and Filter Bar -->
            <section class="p-6 bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-[2rem] shadow-sm">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="md:col-span-2 relative">
                        <input type="text" name="search" value="<?php echo e($search); ?>" placeholder="Search brand or campaign..." class="w-full pl-12 pr-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl focus:border-primary focus:outline-none transition-all placeholder:text-gray-400 dark:placeholder:text-gray-500 font-medium">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">🔍</span>
                    </div>
                    
                    <select name="category" class="px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl focus:border-primary focus:outline-none transition-all font-medium">
                        <option value="">All Categories</option>
                        <?php foreach($categories as $cat): ?>
                            <option value="<?php echo e($cat); ?>" <?php echo $category === $cat ? "selected" : ""; ?>><?php echo e($cat); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <select name="sort" class="px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl focus:border-primary focus:outline-none transition-all font-medium">
                        <option value="newest" <?php echo $sort === "newest" ? "selected" : ""; ?>>Newest First</option>
                        <option value="highest_paying" <?php echo $sort === "highest_paying" ? "selected" : ""; ?>>Highest Paying</option>
                        <option value="deadline" <?php echo $sort === "deadline" ? "selected" : ""; ?>>Deadline Soon</option>
                    </select>

                    <div class="md:col-span-4 grid grid-cols-1 md:grid-cols-4 gap-4 mt-2">
                         <select name="country" class="px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl focus:border-primary focus:outline-none transition-all font-medium">
                            <option value="">All Countries</option>
                            <?php foreach($countries as $c): ?>
                                <option value="<?php echo e($c); ?>" <?php echo $country === $c ? "selected" : ""; ?>><?php echo e($c); ?></option>
                            <?php endforeach; ?>
                        </select>

                        <select name="video_type" class="px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl focus:border-primary focus:outline-none transition-all font-medium">
                            <option value="">Video Type</option>
                            <option value="TikTok/Reels" <?php echo $video_type === "TikTok/Reels" ? "selected" : ""; ?>>TikTok/Reels</option>
                            <option value="Product Demo" <?php echo $video_type === "Product Demo" ? "selected" : ""; ?>>Product Demo</option>
                            <option value="Testimonial" <?php echo $video_type === "Testimonial" ? "selected" : ""; ?>>Testimonial</option>
                        </select>

                        <input type="number" name="budget_min" value="<?php echo e($budget_min); ?>" placeholder="Min Budget" class="px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl focus:border-primary focus:outline-none transition-all font-medium">

                        <button type="submit" class="bg-primary hover:bg-primary-dark text-white font-bold py-3 px-6 rounded-2xl shadow-lg shadow-primary/20 transition-all">Apply Filters</button>
                    </div>
                </form>
            </section>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 gap-6">
                <?php if (empty($campaigns)): ?>
                    <div class="col-span-full py-24 text-center">
                         <div class="text-6xl mb-6">🔍</div>
                         <h3 class="text-2xl font-black text-gray-900 dark:text-white">No campaigns found</h3>
                         <p class="text-gray-500 mt-2">Try adjusting your filters or search terms.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($campaigns as $camp): ?>
                        <div class="p-8 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-200 dark:border-gray-800 shadow-sm transition hover:shadow-xl group flex flex-col <?php echo $camp['is_featured'] ? 'ring-2 ring-orange-500 ring-offset-4 dark:ring-offset-gray-950' : ''; ?>">
                            <div class="flex justify-between items-start mb-6">
                                <div>
                                    <div class="flex items-center gap-2 mb-2">
                                        <span class="px-3 py-1 bg-primary/10 text-primary text-[10px] font-black rounded-full uppercase tracking-widest"><?php echo e($camp["category"]); ?></span>
                                        <?php if ($camp["is_featured"]): ?>
                                            <span class="px-3 py-1 bg-orange-500 text-white text-[10px] font-black rounded-full uppercase tracking-widest flex items-center gap-1">
                                                <span class="w-1.5 h-1.5 bg-white rounded-full animate-pulse"></span> Featured
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <h3 class="text-xl font-bold text-gray-900 dark:text-white group-hover:text-primary transition"><?php echo e($camp["title"]); ?></h3>
                                    <p class="text-sm text-gray-500 font-medium">By <?php echo e($camp["brand_name"]); ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="text-2xl font-black text-gray-900 dark:text-white"><?php echo $camp["currency"]; ?> <?php echo number_format($camp["budget_per_creator"]); ?></p>
                                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">Per Video</p>
                                </div>
                            </div>
                            <div class="mt-auto flex gap-3">
                                <a href="campaign-view.php?id=<?php echo $camp["id"]; ?>" class="flex-1 py-3 text-center bg-gray-900 dark:bg-white text-white dark:text-gray-900 font-bold rounded-xl hover:bg-primary hover:text-white transition-all text-sm">View Details</a>
                                <?php if ($creator["verification_status"] !== "verified"): ?>
                                    <span class="px-4 py-3 bg-orange-50 dark:bg-orange-900/20 text-orange-600 dark:text-orange-400 text-[10px] font-black uppercase rounded-xl flex items-center">
                                        Verify to Apply
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>
<?php include "../includes/footer.php"; ?>