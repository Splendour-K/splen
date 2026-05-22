<?php
require_once "../config/database.php";
require_once "../includes/functions.php";
require_role("admin");

$success = $_GET["success"] ?? "";

// Handle Actions
if (isset($_GET["action"]) && isset($_GET["camp_id"])) {
    $action = $_GET["action"];
    $camp_id = (int)$_GET["camp_id"];

    if ($action === "feature") {
        $pdo->prepare("UPDATE campaigns SET is_featured = 1 WHERE id = ?")->execute([$camp_id]);
        header("Location: campaigns.php?success=Featured"); exit();
    }
    if ($action === "unfeature") {
        $pdo->prepare("UPDATE campaigns SET is_featured = 0 WHERE id = ?")->execute([$camp_id]);
        header("Location: campaigns.php?success=Unfeatured"); exit();
    }
    if ($action === "pause") {
        $pdo->prepare("UPDATE campaigns SET status = \"paused\" WHERE id = ?")->execute([$camp_id]);
        header("Location: campaigns.php?success=Paused"); exit();
    }
    if ($action === "delete") {
        $pdo->prepare("DELETE FROM campaigns WHERE id = ?")->execute([$camp_id]);
        header("Location: campaigns.php?success=Deleted"); exit();
    }
}

// Fetch Campaigns
$stmt = $pdo->query("SELECT c.*, b.brand_name FROM campaigns c JOIN brands b ON c.brand_id = b.id ORDER BY c.created_at DESC");
$campaigns = $stmt->fetchAll();

include "../includes/header.php";
?>

<div class="pt-24 min-h-screen bg-gray-50 dark:bg-gray-950">
    <div class="max-w-7xl mx-auto px-6 md:px-12 flex flex-col md:flex-row gap-8 py-8">
        <?php include "dashboard_sidebar.php"; ?>

        <main class="flex-1 space-y-8">
            <header class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                <h2 class="text-3xl font-black text-gray-900 dark:text-white">Campaign Control</h2>
                <p class="text-gray-500 font-bold mt-1">Audit and moderate all briefs on the platform.</p>
            </header>

            <div class="grid grid-cols-1 gap-6">
                <?php foreach ($campaigns as $c): ?>
                    <div class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm flex flex-col md:flex-row gap-8 group">
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-2">
                                <h3 class="text-xl font-black text-gray-900 dark:text-white"><?php echo e($c["title"]); ?></h3>
                                <?php if ($c["is_featured"]): ?>
                                    <span class="px-2 py-0.5 bg-orange-100 text-orange-600 text-[9px] font-black uppercase rounded-full">Starred</span>
                                <?php endif; ?>
                            </div>
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">Brand: <span class="text-primary"><?php echo e($c["brand_name"]); ?></span></p>
                            
                            <div class="flex gap-6">
                                <div class="text-sm">
                                    <p class="text-[10px] text-gray-400 font-black uppercase tracking-tighter">Budget</p>
                                    <p class="font-bold text-gray-900 dark:text-white"><?php echo $c["currency"]; ?> <?php echo number_format($c["budget_per_creator"]); ?></p>
                                </div>
                                <div class="text-sm">
                                    <p class="text-[10px] text-gray-400 font-black uppercase tracking-tighter">Status</p>
                                    <p class="font-bold text-orange-500"><?php echo e($c["status"]); ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <?php if (!$c["is_featured"]): ?>
                                <a href="?action=feature&camp_id=<?php echo $c["id"]; ?>" class="w-12 h-12 bg-gray-50 dark:bg-gray-800 rounded-2xl flex items-center justify-center hover:bg-orange-500 hover:text-white transition-all shadow-sm">⭐</a>
                            <?php else: ?>
                                <a href="?action=unfeature&camp_id=<?php echo $c["id"]; ?>" class="w-12 h-12 bg-orange-500 text-white rounded-2xl flex items-center justify-center hover:bg-gray-900 transition-all shadow-sm">✖️</a>
                            <?php endif; ?>
                            
                            <a href="?action=pause&camp_id=<?php echo $c["id"]; ?>" class="px-6 py-3 bg-gray-900 dark:bg-white text-white dark:text-gray-900 font-black rounded-xl text-[10px] uppercase tracking-widest hover:bg-orange-600 hover:text-white transition-all">Pause</a>
                            <a href="?action=delete&camp_id=<?php echo $c["id"]; ?>" onclick="return confirm(\"Permanently delete this campaign?\")" class="w-12 h-12 bg-red-50 text-red-500 rounded-2xl flex items-center justify-center hover:bg-red-500 hover:text-white transition-all shadow-sm">🗑️</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </main>
    </div>
</div>

<?php include "../includes/footer.php"; ?>
