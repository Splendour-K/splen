<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_role('brand');

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: /brand/my-campaigns.php");
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM campaigns WHERE id = ?");
$stmt->execute([$id]);
$campaign = $stmt->fetch();

if (!$campaign) {
    die("Campaign not found.");
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $product_name = $_POST['product_name'];
    $category = $_POST['category'];
    $video_type = $_POST['video_type'];
    $video_length = $_POST['video_length'];
    $budget = $_POST['budget_per_creator'];
    $currency = $_POST['currency'] ?? 'USD';
    $deadline = $_POST['deadline'];
    $main_message = $_POST['main_message'];
    $words_to_say = $_POST['words_to_say'];
    $usage_rights = $_POST['usage_rights_package'];
    $status = $_POST['status'];

    $stmt = $pdo->prepare("UPDATE campaigns SET title = ?, product_name = ?, category = ?, video_type = ?, video_length = ?, budget_per_creator = ?, currency = ?, deadline = ?, main_message = ?, words_to_say = ?, usage_rights_package = ?, status = ? WHERE id = ?");
    
    if ($stmt->execute([$title, $product_name, $category, $video_type, $video_length, $budget, $currency, $deadline, $main_message, $words_to_say, $usage_rights, $status, $id])) {
        $success = "Campaign updated successfully!";
        $stmt_reload = $pdo->prepare("SELECT * FROM campaigns WHERE id = ?");
        $stmt_reload->execute([$id]);
        $campaign = $stmt_reload->fetch();
    } else {
        $error = "Update failed.";
    }
}

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
                <div class="relative">
                    <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Edit Campaign</h2>
                    <p class="text-gray-600 dark:text-gray-400 mt-2">Modify your campaign brief for <span class="text-secondary font-bold"><?php echo e($campaign['title']); ?></span>.</p>
                </div>
            </header>

            <?php if ($success): ?>
                <div class="p-6 bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-400 rounded-2xl border border-green-200 dark:border-green-800 flex items-center gap-3">
                    <span class="text-xl">✅</span>
                    <span class="font-bold"><?php echo $success; ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" class="p-8 md:p-12 bg-white dark:bg-gray-900 rounded-[3rem] border border-gray-100 dark:border-gray-800 shadow-sm space-y-10">
                <section>
                    <h3 class="text-xl font-black text-gray-900 dark:text-white mb-6">Basic Info</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-3 ml-1">Campaign Title</label>
                            <input type="text" name="title" value="<?php echo e($campaign['title']); ?>" required class="w-full px-6 py-4 bg-gray-50 dark:bg-gray-800 border-2 border-transparent focus:border-secondary rounded-2xl outline-none transition-all dark:text-white font-medium">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-3 ml-1">Product Name</label>
                            <input type="text" name="product_name" value="<?php echo e($campaign['product_name']); ?>" required class="w-full px-6 py-4 bg-gray-50 dark:bg-gray-800 border-2 border-transparent focus:border-secondary rounded-2xl outline-none transition-all dark:text-white font-medium">
                        </div>
                    </div>
                </section>

                <section>
                    <h3 class="text-xl font-black text-gray-900 dark:text-white mb-6">Targeting & Rewards</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                        <div>
                            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-3 ml-1">Category</label>
                            <select name="category" class="w-full px-6 py-4 bg-gray-50 dark:bg-gray-800 border-2 border-transparent focus:border-secondary rounded-2xl outline-none transition-all dark:text-white font-medium">
                                <option value="Tech" <?php echo $campaign['category'] == 'Tech' ? 'selected' : ''; ?>>Tech</option>
                                <option value="Lifestyle" <?php echo $campaign['category'] == 'Lifestyle' ? 'selected' : ''; ?>>Lifestyle</option>
                                <option value="Fashion" <?php echo $campaign['category'] == 'Fashion' ? 'selected' : ''; ?>>Fashion</option>
                                <option value="Education" <?php echo $campaign['category'] == 'Education' ? 'selected' : ''; ?>>Education</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-3 ml-1">Budget</label>
                            <div class="flex gap-2">
                                <select name="currency" class="w-24 px-3 py-4 bg-gray-50 dark:bg-gray-800 border-2 border-transparent focus:border-secondary rounded-2xl outline-none transition-all dark:text-white font-medium">
                                    <option value="NGN" <?php echo $campaign['currency'] == 'NGN' ? 'selected' : ''; ?>>NGN (₦)</option>
                                    <option value="GHS" <?php echo $campaign['currency'] == 'GHS' ? 'selected' : ''; ?>>GHS (₵)</option>
                                    <option value="USD" <?php echo $campaign['currency'] == 'USD' ? 'selected' : ''; ?>>USD ($)</option>
                                    <option value="EUR" <?php echo $campaign['currency'] == 'EUR' ? 'selected' : ''; ?>>EUR (€)</option>
                                    <option value="GBP" <?php echo $campaign['currency'] == 'GBP' ? 'selected' : ''; ?>>GBP (£)</option>
                                </select>
                                <input type="number" name="budget_per_creator" value="<?php echo $campaign['budget_per_creator']; ?>" required class="flex-1 px-6 py-4 bg-gray-50 dark:bg-gray-800 border-2 border-transparent focus:border-secondary rounded-2xl outline-none transition-all dark:text-white font-medium">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-3 ml-1">Deadline</label>
                            <input type="date" name="deadline" value="<?php echo $campaign['deadline']; ?>" required class="w-full px-6 py-4 bg-gray-50 dark:bg-gray-800 border-2 border-transparent focus:border-secondary rounded-2xl outline-none transition-all dark:text-white font-medium">
                        </div>
                    </div>
                </section>

                <section>
                    <h3 class="text-xl font-black text-gray-900 dark:text-white mb-6">Brief Details</h3>
                    <div class="space-y-8">
                        <div>
                            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-3 ml-1">Main Message / Brief</label>
                            <textarea name="main_message" rows="5" required class="w-full px-6 py-4 bg-gray-50 dark:bg-gray-800 border-2 border-transparent focus:border-secondary rounded-2xl outline-none transition-all dark:text-white font-medium" placeholder="What should the creators do?"><?php echo e($campaign['main_message']); ?></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-3 ml-1">Key Phrases / Words to Say</label>
                            <textarea name="words_to_say" rows="3" class="w-full px-6 py-4 bg-gray-50 dark:bg-gray-800 border-2 border-transparent focus:border-secondary rounded-2xl outline-none transition-all dark:text-white font-medium" placeholder="Keep it authentic..."><?php echo e($campaign['words_to_say']); ?></textarea>
                        </div>
                    </div>
                </section>

                <section>
                    <h3 class="text-xl font-black text-gray-900 dark:text-white mb-6">Configuration</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                        <div>
                            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-3 ml-1">Video Type</label>
                            <input type="text" name="video_type" value="<?php echo e($campaign['video_type']); ?>" class="w-full px-6 py-4 bg-gray-50 dark:bg-gray-800 border-2 border-transparent focus:border-secondary rounded-2xl outline-none transition-all dark:text-white font-medium">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-3 ml-1">Video Length</label>
                            <input type="text" name="video_length" value="<?php echo e($campaign['video_length']); ?>" class="w-full px-6 py-4 bg-gray-50 dark:bg-gray-800 border-2 border-transparent focus:border-secondary rounded-2xl outline-none transition-all dark:text-white font-medium">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-3 ml-1">Usage Rights</label>
                            <select name="usage_rights_package" class="w-full px-6 py-4 bg-gray-50 dark:bg-gray-800 border-2 border-transparent focus:border-secondary rounded-2xl outline-none transition-all dark:text-white font-medium">
                                <option value="basic" <?php echo $campaign['usage_rights_package'] == 'basic' ? 'selected' : ''; ?>>Basic (Organic)</option>
                                <option value="ad" <?php echo $campaign['usage_rights_package'] == 'ad' ? 'selected' : ''; ?>>Ads Rights</option>
                                <option value="full" <?php echo $campaign['usage_rights_package'] == 'full' ? 'selected' : ''; ?>>Full Ownership</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-3 ml-1">Campaign Status</label>
                            <select name="status" class="w-full px-6 py-4 bg-gray-50 dark:bg-gray-800 border-2 border-secondary/20 focus:border-secondary rounded-2xl outline-none transition-all dark:text-white font-bold">
                                <option value="published" <?php echo $campaign['status'] == 'published' ? 'selected' : ''; ?>>Active / Published</option>
                                <option value="draft" <?php echo $campaign['status'] == 'draft' ? 'selected' : ''; ?>>Draft</option>
                                <option value="paused" <?php echo $campaign['status'] == 'paused' ? 'selected' : ''; ?>>Paused</option>
                                <option value="completed" <?php echo $campaign['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                            </select>
                        </div>
                    </div>
                </section>

                <div class="flex gap-4">
                    <button type="submit" class="flex-1 py-5 bg-secondary text-white font-black rounded-2xl shadow-xl hover:shadow-secondary/20 hover:scale-[1.02] transition-all text-lg">Update Brief</button>
                    <a href="<?php echo APP_URL; ?>brand/my-campaigns.php" class="px-8 py-5 bg-gray-100 dark:bg-gray-800 text-gray-500 font-bold rounded-2xl flex items-center justify-center">Cancel</a>
                </div>
            </form>
        </main>
    </div>
</div>

<?php 
include '../includes/footer.php';
?>