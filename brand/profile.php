<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_role('brand');

$stmt = $pdo->prepare("SELECT * FROM brands WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$brand = $stmt->fetch() ?: [
    'id' => null, 'brand_name' => '', 'contact_person' => '', 'industry' => '',
    'website' => '', 'phone' => '', 'country' => '', 'city' => '', 'logo' => '',
    'subscription_tier' => 'basic', 'user_id' => $_SESSION['user_id'],
];

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $brand_name = $_POST['brand_name'];
    $contact_person = $_POST['contact_person'];
    $industry = $_POST['industry'];
    $website = $_POST['website'];
    $phone = $_POST['phone'];
    $country = $_POST['country'];
    $city = $_POST['city'];

    // Handle Logo Upload
    $logo_path = $brand['logo'];
    if (!empty($_FILES['logo']['name'])) {
        $target_dir = "../assets/uploads/profiles/";
        if (!file_exists($target_dir)) mkdir($target_dir, 0755, true);
        
        $allowed_ext = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $file_ext = strtolower(pathinfo($_FILES["logo"]["name"], PATHINFO_EXTENSION));
        if (!in_array($file_ext, $allowed_ext)) {
            $error = "Logo must be a JPG, PNG, WEBP, or GIF image.";
        }
        $new_filename = "brand_" . $brand['id'] . "_" . time() . "." . $file_ext;
        $target_file = $target_dir . $new_filename;

        if (!$error && move_uploaded_file($_FILES["logo"]["tmp_name"], $target_file)) {
            $logo_path = "assets/uploads/profiles/" . $new_filename;
        }
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE brands 
            SET brand_name = ?, contact_person = ?, industry = ?, website = ?, phone = ?, country = ?, city = ?, logo = ?
            WHERE user_id = ?
        ");
        $stmt->execute([
            $brand_name, $contact_person, $industry, $website, $phone, $country, $city, $logo_path, $_SESSION['user_id']
        ]);
        $success = "Profile updated successfully!";
        // Refresh brand data
        $brand['brand_name'] = $brand_name;
        $brand['contact_person'] = $contact_person;
        $brand['industry'] = $industry;
        $brand['website'] = $website;
        $brand['phone'] = $phone;
        $brand['country'] = $country;
        $brand['city'] = $city;
        $brand['logo'] = $logo_path;
    } catch (Exception $e) {
        $error = "Error updating profile: " . $e->getMessage();
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
            <header class="p-8 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-200 dark:border-gray-800 shadow-sm relative overflow-hidden">
                <div aria-hidden="true" class="absolute top-0 right-0 -mr-16 -mt-16 w-64 h-64 bg-secondary/5 rounded-full blur-3xl"></div>
                <div class="relative">
                    <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Company Profile</h2>
                    <p class="text-gray-600 dark:text-gray-400 mt-2">Manage your brand identity and contact information.</p>
                </div>
            </header>

            <?php if ($success): ?>
                <div class="p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-2xl text-green-800 dark:text-green-400 font-bold">
                    ✅ <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-2xl text-red-800 dark:text-red-400 font-bold">
                    ⚠️ <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- Subscription Status -->
            <div class="p-8 bg-gray-900 rounded-[2.5rem] border border-gray-800 shadow-2xl relative overflow-hidden group">
                <div aria-hidden="true" class="absolute top-0 right-0 -mr-16 -mt-16 w-64 h-64 bg-secondary/20 rounded-full blur-3xl group-hover:bg-secondary/30 transition-all duration-500"></div>
                <div class="relative flex flex-col md:flex-row items-center justify-between gap-6">
                    <div class="flex items-center gap-6">
                        <div class="w-16 h-16 rounded-2xl bg-secondary/10 flex items-center justify-center text-3xl">
                            <?php echo $brand['subscription_tier'] === 'pro' ? '💎' : '🏢'; ?>
                        </div>
                        <div>
                            <div class="flex items-center gap-3">
                                <h3 class="text-xl font-black text-white uppercase tracking-tight">
                                    <?php echo ucfirst($brand['subscription_tier']); ?> Plan
                                </h3>
                                <span class="px-3 py-1 bg-green-500/10 text-green-400 text-[10px] font-black uppercase rounded-full tracking-widest border border-green-500/20">Active</span>
                            </div>
                            <p class="text-gray-400 mt-1 font-medium italic">
                                <?php 
                                    if($brand['subscription_tier'] === 'basic') echo "Enjoying 3 campaigns per month.";
                                    elseif($brand['subscription_tier'] === 'pro') echo "Enjoying 15 campaigns per month with priority support.";
                                    else echo "Unlimited campaigns and managed services.";
                                ?>
                            </p>
                        </div>
                    </div>
                    <a href="subscription.php" class="px-8 py-3 bg-white/10 hover:bg-white/20 text-white font-bold rounded-xl transition-all border border-white/10 flex items-center gap-2">
                        Manage Plan
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="9 5l7 7-7 7"/></svg>
                    </a>
                </div>
            </div>

            <form method="POST" enctype="multipart/form-data" class="space-y-8">
                <section class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-200 dark:border-gray-800 shadow-sm space-y-6">
                    <!-- Brand Identity -->
                    <div class="flex items-center gap-6 mb-8 pb-8 border-b border-gray-100 dark:border-gray-800">
                        <div class="w-24 h-24 rounded-3xl bg-gray-50 dark:bg-gray-800 border-2 border-dashed border-gray-200 dark:border-gray-700 flex items-center justify-center overflow-hidden relative group">
                            <?php if ($brand['logo']): ?>
                                <img src="<?php echo APP_URL . $brand['logo']; ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <span class="text-gray-400 text-xs text-center p-2">No Logo</span>
                            <?php endif; ?>
                            <input type="file" name="logo" class="absolute inset-0 opacity-0 cursor-pointer">
                            <div class="absolute inset-0 bg-black/50 text-white text-[10px] font-bold flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">Change</div>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white"><?php echo e($brand['brand_name']); ?></h3>
                            <p class="text-sm text-gray-500">Upload your brand logo (PNG, JPG, max 2MB)</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-200 mb-2">Company Name</label>
                            <input type="text" name="brand_name" value="<?php echo e($brand['brand_name']); ?>" required class="w-full px-5 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl focus:ring-2 focus:ring-secondary/20 outline-none transition-all dark:text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-200 mb-2">Contact Person</label>
                            <input type="text" name="contact_person" value="<?php echo e($brand['contact_person']); ?>" required class="w-full px-5 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl focus:ring-2 focus:ring-secondary/20 outline-none transition-all dark:text-white">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-200 mb-2">Industry</label>
                            <input type="text" name="industry" value="<?php echo e($brand['industry']); ?>" placeholder="e.g. Beauty, Tech, Fashion" class="w-full px-5 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl focus:ring-2 focus:ring-secondary/20 outline-none transition-all dark:text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-200 mb-2">Website</label>
                            <input type="url" name="website" value="<?php echo e($brand['website']); ?>" placeholder="https://yourbrand.com" class="w-full px-5 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl focus:ring-2 focus:ring-secondary/20 outline-none transition-all dark:text-white">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-200 mb-2">Phone Number</label>
                            <input type="text" name="phone" value="<?php echo e($brand['phone']); ?>" class="w-full px-5 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl focus:ring-2 focus:ring-secondary/20 outline-none transition-all dark:text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-200 mb-2">Country</label>
                            <input type="text" name="country" value="<?php echo e($brand['country']); ?>" class="w-full px-5 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl focus:ring-2 focus:ring-secondary/20 outline-none transition-all dark:text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-200 mb-2">City</label>
                            <input type="text" name="city" value="<?php echo e($brand['city']); ?>" class="w-full px-5 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl focus:ring-2 focus:ring-secondary/20 outline-none transition-all dark:text-white">
                        </div>
                    </div>

                    <div class="pt-6">
                        <button type="submit" class="w-full md:w-auto px-12 py-4 bg-secondary text-white font-bold rounded-2xl hover:scale-[1.02] active:scale-95 transition-all shadow-lg shadow-secondary/20">
                            Save Changes
                        </button>
                    </div>
                </section>
            </form>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
