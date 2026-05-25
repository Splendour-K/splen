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

    // Handle Featured Image Upload (optional, non-fatal)
    $new_featured_image = null; // null = keep existing
    if (!empty($_FILES['featured_image']['name']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
        $fi_dir  = "../assets/uploads/listings/";
        if (!is_dir($fi_dir)) mkdir($fi_dir, 0755, true);
        $fi_ext  = strtolower(pathinfo($_FILES['featured_image']['name'], PATHINFO_EXTENSION));
        $fi_size = $_FILES['featured_image']['size'];
        if (in_array($fi_ext, ['jpg','jpeg','png','webp']) && $fi_size <= 5 * 1024 * 1024) {
            $fi_name = "campaign_{$id}_" . time() . ".{$fi_ext}";
            if (move_uploaded_file($_FILES['featured_image']['tmp_name'], $fi_dir . $fi_name)) {
                $new_featured_image = "assets/uploads/listings/{$fi_name}";
            }
        } elseif ($fi_size > 5 * 1024 * 1024) {
            $error = "Featured image must be under 5 MB.";
        }
    }

    $stmt = $pdo->prepare("UPDATE campaigns SET title = ?, product_name = ?, category = ?, video_type = ?, video_length = ?, budget_per_creator = ?, currency = ?, deadline = ?, main_message = ?, words_to_say = ?, usage_rights_package = ?, status = ? WHERE id = ?");

    if ($stmt->execute([$title, $product_name, $category, $video_type, $video_length, $budget, $currency, $deadline, $main_message, $words_to_say, $usage_rights, $status, $id])) {
        $success = "Campaign updated successfully!";

        // Update featured image separately (requires fix_featured_images.sql migration)
        if ($new_featured_image !== null) {
            try {
                $pdo->prepare("UPDATE campaigns SET featured_image = ? WHERE id = ?")
                    ->execute([$new_featured_image, $id]);
            } catch (\Exception $fi_e) {
                error_log("featured_image column not found (run fix_featured_images.sql): " . $fi_e->getMessage());
            }
        }

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

            <form method="POST" enctype="multipart/form-data" class="p-8 md:p-12 bg-white dark:bg-gray-900 rounded-[3rem] border border-gray-100 dark:border-gray-800 shadow-sm space-y-10">
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
                                <?php foreach ([
                                    'Beauty','Skincare','Fashion','Food & Drink',
                                    'Tech Products','Mobile Apps','Books & Education',
                                    'Health & Wellness','Sports & Fitness','Gaming',
                                    'Music & Entertainment','Travel','Finance & Fintech',
                                    'Home & Lifestyle','Automotive','Pets','Other',
                                ] as $c): ?>
                                    <option value="<?php echo $c; ?>" <?php echo $campaign['category'] === $c ? 'selected' : ''; ?>><?php echo $c; ?></option>
                                <?php endforeach; ?>
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
                            <select name="video_type" class="w-full px-6 py-4 bg-gray-50 dark:bg-gray-800 border-2 border-transparent focus:border-secondary rounded-2xl outline-none transition-all dark:text-white font-medium">
                                <?php foreach ([
                                    'Product Review','Unboxing','Testimonial',
                                    'Campus Lifestyle','App Demo','Tutorial / How-To',
                                    'Day in the Life','Behind the Scenes',
                                    'Get Ready With Me (GRWM)','Haul',
                                    'Challenge','Skit / Comedy','Vlog',
                                    'Before & After','Q&A','Other',
                                ] as $vt): ?>
                                    <option value="<?php echo $vt; ?>" <?php echo $campaign['video_type'] === $vt ? 'selected' : ''; ?>><?php echo $vt; ?></option>
                                <?php endforeach; ?>
                            </select>
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

                <!-- Featured Image -->
                <section>
                    <h3 class="text-xl font-black text-gray-900 dark:text-white mb-6 flex items-center gap-3">
                        <span class="w-8 h-8 bg-blue-500/10 text-blue-500 rounded-lg flex items-center justify-center text-sm">🖼️</span>
                        Featured Image <span class="ml-2 text-xs font-normal text-gray-400 bg-gray-100 dark:bg-gray-800 px-2 py-0.5 rounded-full">Optional</span>
                    </h3>
                    <p class="text-xs text-gray-500 mb-4">Upload a banner image for your campaign listing. JPG, PNG, WEBP · max 5 MB. Leave empty to keep the current image.</p>
                    <label class="block relative w-full rounded-2xl overflow-hidden cursor-pointer group" style="aspect-ratio:16/7;" id="fi-label">
                        <?php if (!empty($campaign['featured_image'])): ?>
                            <img id="fi-preview" src="<?php echo APP_URL . e($campaign['featured_image']); ?>" alt="Current featured image" class="w-full h-full object-cover absolute inset-0">
                            <div id="fi-placeholder" class="hidden absolute inset-0 flex flex-col items-center justify-center bg-gray-50 dark:bg-gray-800 border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-2xl">
                                <p class="text-sm font-bold text-gray-400">Click to upload a featured image</p>
                            </div>
                            <div class="absolute inset-0 bg-black/40 text-white text-sm font-bold flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">📷 Change Image</div>
                        <?php else: ?>
                            <div id="fi-placeholder" class="absolute inset-0 flex flex-col items-center justify-center bg-gray-50 dark:bg-gray-800 border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-2xl group-hover:border-secondary transition">
                                <svg class="w-10 h-10 text-gray-300 mb-2 group-hover:text-secondary transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <p class="text-sm font-bold text-gray-400 group-hover:text-secondary transition">Click to upload a featured image</p>
                                <p class="text-xs text-gray-400 mt-1">16:9 ratio · JPG, PNG, WEBP · max 5 MB</p>
                            </div>
                            <img id="fi-preview" class="w-full h-full object-cover absolute inset-0 hidden rounded-2xl" alt="Preview">
                        <?php endif; ?>
                        <input type="file" name="featured_image" accept="image/jpeg,image/png,image/webp" class="absolute inset-0 opacity-0 cursor-pointer w-full h-full" onchange="(function(i){const p=document.getElementById('fi-preview'),ph=document.getElementById('fi-placeholder');if(i.files&&i.files[0]){const r=new FileReader();r.onload=e=>{p.src=e.target.result;p.classList.remove('hidden');ph.classList.add('hidden');};r.readAsDataURL(i.files[0]);}})(this)">
                    </label>
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