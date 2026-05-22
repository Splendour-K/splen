<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_role('creator');

// Fetch Creator data
$stmt = $pdo->prepare("SELECT * FROM creators WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$creator = $stmt->fetch();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = $_POST['full_name'];
    $bio = $_POST['bio'];
    $tiktok = $_POST['tiktok_handle'];
    $ig = $_POST['instagram_handle'];
    $niche = $_POST['main_niche'];

    $upd = $pdo->prepare("UPDATE creators SET full_name = ?, bio = ?, tiktok_handle = ?, instagram_handle = ?, main_niche = ? WHERE user_id = ?");
    try {
        $upd->execute([$full_name, $bio, $tiktok, $ig, $niche, $_SESSION['user_id']]);
        $success = "Profile updated successfully!";
        // Refresh data
        $stmt->execute([$_SESSION['user_id']]);
        $creator = $stmt->fetch();
    } catch (Exception $e) {
        $error = "Error updating profile: " . $e->getMessage();
    }
}

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
                    <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Profile Settings</h2>
                    <p class="text-gray-600 dark:text-gray-400 mt-2">Update your creator information and social handles.</p>
                </div>
            </header>

            <?php if ($success): ?>
                <div class="p-6 bg-green-100 dark:bg-green-900/30 border border-green-200 dark:border-green-800 rounded-2xl text-green-800 dark:text-green-400 font-bold flex items-center shadow-lg shadow-green-500/10">
                    <span class="mr-3 text-xl">✨</span> <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="p-6 bg-red-100 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-2xl text-red-800 dark:text-red-400 font-bold flex items-center">
                    <span class="mr-3 text-xl">⚠️</span> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <section class="p-8 bg-white dark:bg-gray-900 rounded-[3rem] border border-gray-100 dark:border-gray-800 shadow-sm space-y-6">
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-6 flex items-center">
                            <span class="w-8 h-8 bg-primary/10 rounded-lg flex items-center justify-center mr-3 text-sm">👤</span>
                            Basic Information
                        </h3>
                        
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2 ml-1">Full Name</label>
                            <input type="text" name="full_name" value="<?php echo e($creator['full_name']); ?>" required class="w-full px-5 py-3 bg-gray-50 dark:bg-gray-800 border-2 border-transparent focus:border-primary focus:bg-white dark:focus:bg-gray-900 rounded-2xl transition-all outline-none font-medium text-gray-900 dark:text-white">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2 ml-1">Main Niche</label>
                            <select name="main_niche" class="w-full px-5 py-3 bg-gray-50 dark:bg-gray-800 border-2 border-transparent focus:border-primary focus:bg-white dark:focus:bg-gray-900 rounded-2xl transition-all outline-none font-medium text-gray-900 dark:text-white">
                                <option value="Beauty" <?php echo $creator['main_niche'] === 'Beauty' ? 'selected' : ''; ?>>Beauty & Skincare</option>
                                <option value="Tech" <?php echo $creator['main_niche'] === 'Tech' ? 'selected' : ''; ?>>Tech & Gadgets</option>
                                <option value="Lifestyle" <?php echo $creator['main_niche'] === 'Lifestyle' ? 'selected' : ''; ?>>Lifestyle</option>
                                <option value="Education" <?php echo $creator['main_niche'] === 'Education' ? 'selected' : ''; ?>>Education</option>
                                <option value="Fashion" <?php echo $creator['main_niche'] === 'Fashion' ? 'selected' : ''; ?>>Fashion</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2 ml-1">Short Bio</label>
                            <textarea name="bio" rows="4" class="w-full px-5 py-3 bg-gray-50 dark:bg-gray-800 border-2 border-transparent focus:border-primary focus:bg-white dark:focus:bg-gray-900 rounded-2xl transition-all outline-none font-medium text-gray-900 dark:text-white" placeholder="Tell brands about your style..."><?php echo e($creator['bio']); ?></textarea>
                        </div>
                    </section>

                    <section class="p-8 bg-white dark:bg-gray-900 rounded-[3rem] border border-gray-100 dark:border-gray-800 shadow-sm space-y-6">
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-6 flex items-center">
                            <span class="w-8 h-8 bg-secondary/10 rounded-lg flex items-center justify-center mr-3 text-sm">📱</span>
                            Social Presence
                        </h3>

                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2 ml-1">TikTok Handle</label>
                            <div class="relative">
                                <span class="absolute left-5 top-1/2 -translate-y-1/2 text-gray-400 font-bold">@</span>
                                <input type="text" name="tiktok_handle" value="<?php echo e($creator['tiktok_handle']); ?>" class="w-full pl-10 pr-5 py-3 bg-gray-50 dark:bg-gray-800 border-2 border-transparent focus:border-primary focus:bg-white dark:focus:bg-gray-900 rounded-2xl transition-all outline-none font-medium text-gray-900 dark:text-white" placeholder="username">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2 ml-1">Instagram Handle</label>
                            <div class="relative">
                                <span class="absolute left-5 top-1/2 -translate-y-1/2 text-gray-400 font-bold">@</span>
                                <input type="text" name="instagram_handle" value="<?php echo e($creator['instagram_handle']); ?>" class="w-full pl-10 pr-5 py-3 bg-gray-50 dark:bg-gray-800 border-2 border-transparent focus:border-primary focus:bg-white dark:focus:bg-gray-900 rounded-2xl transition-all outline-none font-medium text-gray-900 dark:text-white" placeholder="username">
                            </div>
                        </div>

                        <div class="p-6 bg-primary/5 rounded-[2rem] border border-primary/10">
                            <p class="text-xs font-bold text-primary uppercase tracking-widest mb-2">Pro Tip</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">Brands check your social profiles before approving applications. Make sure they represent your best work!</p>
                        </div>
                    </section>
                </div>

                <div class="flex justify-end pt-4">
                    <button type="submit" class="px-12 py-5 bg-gray-900 dark:bg-white text-white dark:text-gray-900 text-lg font-black rounded-2xl shadow-xl hover:bg-primary hover:text-white transition-all transform hover:scale-105 active:scale-95">
                        Save Changes
                    </button>
                </div>
            </form>
        </main>
    </div>
</div>

<?php 
include '../includes/footer.php';
?>