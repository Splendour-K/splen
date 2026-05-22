<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_role('brand');

$stmt = $pdo->prepare("SELECT * FROM brands WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$brand = $stmt->fetch();

$brand_id = $brand['id'];

// Handling Duplicate Action
if (isset($_GET['action']) && $_GET['action'] === 'duplicate' && isset($_GET['id'])) {
    $c_id = $_GET['id'];
    // Verify ownership
    $stmt_v = $pdo->prepare("SELECT * FROM campaigns WHERE id = ? AND brand_id = ?");
    $stmt_v->execute([$c_id, $brand_id]);
    $to_clone = $stmt_v->fetch();

    if ($to_clone) {
        $quota = check_brand_quota($brand_id);
        if ($quota['can_create']) {
            unset($to_clone['id'], $to_clone['created_at'], $to_clone['updated_at']);
            $to_clone['title'] .= " (Clone)";
            $to_clone['status'] = 'published';

            $cols = implode(", ", array_keys($to_clone));
            $placeholders = ":" . implode(", :", array_keys($to_clone));
            $sql_clone = "INSERT INTO campaigns ($cols) VALUES ($placeholders)";
            $stmt_clone = $pdo->prepare($sql_clone);
            $stmt_clone->execute($to_clone);
            header("Location: my-campaigns.php?cloned=1");
            exit();
        } else {
            header("Location: my-campaigns.php?error=quota");
            exit();
        }
    }
}

// Fetch all campaigns for this brand
$stmt = $pdo->prepare("
    SELECT c.*, 
    (SELECT COUNT(*) FROM applications WHERE campaign_id = c.id) as app_count,
    (SELECT COUNT(*) FROM jobs WHERE campaign_id = c.id) as job_count
    FROM campaigns c 
    WHERE c.brand_id = ? 
    ORDER BY c.created_at DESC
");
$stmt->execute([$brand_id]);
$campaigns = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="pt-24 min-h-screen bg-gray-50 dark:bg-gray-950">
    <div class="max-w-7xl mx-auto px-6 md:px-12 flex flex-col md:flex-row gap-8 py-8">
        <!-- Sidebar -->
        <?php include '../includes/brand_sidebar.php'; ?>

        <!-- Main Content -->
        <main class="flex-1 space-y-8">
            <header class="p-8 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm relative overflow-hidden">
                <div aria-hidden="true" class="absolute top-0 right-0 -mr-16 -mt-16 w-64 h-64 bg-secondary/5 rounded-full blur-3xl"></div>
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 relative">
                    <div>
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white">My Campaigns</h2>
                        <p class="text-gray-600 dark:text-gray-400 mt-2">Manage your active and past brand briefs.</p>
                    </div>
                    <a href="<?php echo APP_URL; ?>brand/create-campaign.php" class="inline-flex items-center px-6 py-3 bg-secondary text-white font-bold rounded-xl hover:scale-105 transition-all">
                        Create New Brief
                    </a>
                </div>
            </header>

            <?php if (isset($_GET['cloned'])): ?>
                <div class="p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-2xl text-green-800 dark:text-green-400 font-bold">
                    ✅ Brief duplicated successfully! It is now live.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error']) && $_GET['error'] === 'quota'): ?>
                <div class="p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-2xl text-red-800 dark:text-red-400 font-bold">
                    ⚠️ Monthly quota reached! Upgrade your plan to clone more campaigns.
                </div>
            <?php endif; ?>

            <?php if (empty($campaigns)): ?>
                <div class="p-12 text-center bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                    <div class="w-20 h-20 bg-gray-100 dark:bg-gray-800 rounded-full flex items-center justify-center text-3xl mx-auto mb-6">📝</div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">No campaigns yet</h3>
                    <p class="text-gray-500 max-w-sm mx-auto mb-8">Ready to get some high-quality content? Start by creating your first campaign brief.</p>
                    <a href="<?php echo APP_URL; ?>brand/create-campaign.php" class="text-secondary font-bold hover:underline">Start Briefing →</a>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 gap-6">
                    <?php foreach ($campaigns as $camp): ?>
                        <div class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm hover:shadow-xl hover:shadow-gray-200/50 dark:hover:shadow-none transition-all duration-500 overflow-hidden group">
                            <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                                <div class="flex-1">
                                    <div class="flex items-center gap-3 mb-4">
                                        <span class="px-3 py-1 bg-secondary/10 text-secondary text-[10px] font-black rounded-full uppercase"><?php echo e($camp['status']); ?></span>
                                        <span class="text-[10px] text-gray-400 font-bold uppercase tracking-widest"><?php echo date('M d, Y', strtotime($camp['created_at'])); ?></span>
                                    </div>
                                    <h3 class="text-2xl font-black text-gray-900 dark:text-white mb-2"><?php echo e($camp['title']); ?></h3>
                                    <p class="text-gray-500 text-sm line-clamp-2 mb-6"><?php echo e($camp['main_message']); ?></p>
                                    
                                    <div class="flex flex-wrap gap-6">
                                        <div class="flex items-center gap-2">
                                            <span class="text-gray-400">👤</span>
                                            <div>
                                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Applicants</p>
                                                <p class="text-sm font-bold text-gray-900 dark:text-white"><?php echo $camp['app_count']; ?></p>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-gray-400">🎬</span>
                                            <div>
                                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Active Jobs</p>
                                                <p class="text-sm font-bold text-gray-900 dark:text-white"><?php echo $camp['job_count']; ?></p>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-gray-400">💰</span>
                                            <div>
                                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Budget</p>
                                                <p class="text-sm font-bold text-gray-900 dark:text-white"><?php echo $camp['currency']; ?> <?php echo number_format($camp['budget_per_creator'], 0); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="flex md:flex-col gap-3">
                                    <a href="<?php echo APP_URL; ?>brand/applications.php?camp_id=<?php echo $camp['id']; ?>" class="px-6 py-3 bg-gray-50 dark:bg-gray-800 hover:bg-secondary hover:text-white text-gray-700 dark:text-gray-300 font-bold rounded-xl transition-all text-center text-sm">Review App</a>
                                    <div class="flex gap-2">
                                        <a href="<?php echo APP_URL; ?>brand/edit-campaign.php?id=<?php echo $camp['id']; ?>" class="flex-1 px-4 py-3 bg-gray-50 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 font-bold rounded-xl transition-all text-center text-xs">Edit</a>
                                        <a href="?action=duplicate&id=<?php echo $camp['id']; ?>" class="flex-1 px-4 py-3 bg-gray-50 dark:bg-gray-800 hover:bg-orange-100 dark:hover:bg-orange-950 text-orange-600 font-bold rounded-xl transition-all text-center text-xs" title="Duplicate Brief">Clone</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>