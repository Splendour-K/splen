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

// Decode existing bank details JSON
$bank_details = [];
if (!empty($creator['bank_details_json'])) {
    $decoded = json_decode($creator['bank_details_json'], true);
    if (is_array($decoded)) $bank_details = $decoded;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = $_POST['full_name'];
    $bio       = $_POST['bio'];
    $tiktok    = $_POST['tiktok_handle'];
    $ig        = $_POST['instagram_handle'];
    $niche     = $_POST['main_niche'];
    $country   = trim($_POST['country'] ?? ($creator['country'] ?? ''));

    // Payout currency — validate
    $payout_currency = strtoupper(trim($_POST['payout_currency'] ?? 'USD'));
    if (!in_array($payout_currency, ['NGN', 'GHS', 'USD'])) $payout_currency = 'USD';

    // Bank details per currency — merge submitted with existing
    foreach (['NGN', 'GHS', 'USD'] as $ccy) {
        $submitted = $_POST['bank'][$ccy] ?? [];
        if (!empty(array_filter($submitted))) {
            $bank_details[$ccy] = array_map('trim', $submitted);
        }
    }
    $bank_details_json = json_encode($bank_details, JSON_UNESCAPED_UNICODE);

    // Handle optional profile photo upload
    $photo_path = $creator['profile_photo'] ?? null;
    if (!empty($_FILES['profile_photo']['name']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $target_dir = "../assets/uploads/profiles/";
        if (!file_exists($target_dir)) mkdir($target_dir, 0755, true);

        $allowed_ext = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $file_ext = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
        $file_size = $_FILES['profile_photo']['size'];

        if (!in_array($file_ext, $allowed_ext)) {
            $error = "Profile photo must be a JPG, PNG, WEBP, or GIF image.";
        } elseif ($file_size > 2 * 1024 * 1024) {
            $error = "Profile photo must be under 2 MB.";
        } else {
            $new_filename = "creator_" . $creator['id'] . "_" . time() . "." . $file_ext;
            $target_file  = $target_dir . $new_filename;
            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $target_file)) {
                $photo_path = "assets/uploads/profiles/" . $new_filename;
            } else {
                $error = "Could not save photo — the uploads folder may not be writable. Please try again or contact support.";
            }
        }
    }

    if (!$error) {
        try {
            $upd = $pdo->prepare("UPDATE creators SET full_name=?, bio=?, tiktok_handle=?, instagram_handle=?, main_niche=?, profile_photo=?, country=?, payout_currency=?, bank_details_json=? WHERE user_id=?");
            $upd->execute([$full_name, $bio, $tiktok, $ig, $niche, $photo_path, $country ?: null, $payout_currency, $bank_details_json, $_SESSION['user_id']]);
            $success = "Profile updated successfully!";
            // Refresh data
            $stmt->execute([$_SESSION['user_id']]);
            $creator = $stmt->fetch();
            $bank_details = json_decode($creator['bank_details_json'] ?? '{}', true) ?: [];
        } catch (Exception $e) {
            $error = "Error updating profile: " . $e->getMessage();
        }
    }
}

// Helper to get bank field value
$bv = function(string $ccy, string $field) use ($bank_details): string {
    return htmlspecialchars($bank_details[$ccy][$field] ?? '', ENT_QUOTES, 'UTF-8');
};

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

            <form method="POST" enctype="multipart/form-data" class="space-y-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <section class="p-8 bg-white dark:bg-gray-900 rounded-[3rem] border border-gray-100 dark:border-gray-800 shadow-sm space-y-6">
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-6 flex items-center">
                            <span class="w-8 h-8 bg-primary/10 rounded-lg flex items-center justify-center mr-3 text-sm">👤</span>
                            Basic Information
                        </h3>

                        <!-- Profile Photo Upload -->
                        <div class="flex items-center gap-5 pb-6 mb-2 border-b border-gray-100 dark:border-gray-800">
                            <label class="relative w-20 h-20 rounded-full bg-gray-50 dark:bg-gray-800 border-2 border-dashed border-gray-200 dark:border-gray-700 overflow-hidden group cursor-pointer flex-shrink-0" title="Click to upload profile photo">
                                <?php if (!empty($creator['profile_photo'])): ?>
                                    <img src="<?php echo APP_URL . e($creator['profile_photo']); ?>" alt="Profile photo" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center">
                                        <svg class="w-9 h-9 text-gray-300" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/>
                                        </svg>
                                    </div>
                                <?php endif; ?>
                                <input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp,image/gif" class="absolute inset-0 opacity-0 cursor-pointer w-full h-full">
                                <div class="absolute inset-0 bg-black/55 text-white text-[10px] font-bold flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity rounded-full pointer-events-none">
                                    📷 Change
                                </div>
                            </label>
                            <div>
                                <p class="font-bold text-gray-900 dark:text-white text-sm"><?php echo e($creator['full_name'] ?: 'Your Name'); ?></p>
                                <p class="text-xs text-gray-500 mt-1">Upload a profile photo (JPG, PNG · max 2 MB)</p>
                                <p class="text-xs text-gray-400 mt-0.5">Optional — not required</p>
                            </div>
                        </div>

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

                <!-- ── Payout Settings ── -->
                <section id="payout-settings" class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm space-y-8">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
                            <span class="w-8 h-8 bg-green-100 dark:bg-green-900/30 rounded-lg flex items-center justify-center text-sm">💳</span>
                            Payout Settings
                        </h3>
                        <p class="text-sm text-gray-500 mt-1">Choose your preferred payout currency and enter bank details so we can process your earnings correctly.</p>
                    </div>

                    <!-- Country + Payout Currency -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2 ml-1">Country of Residence</label>
                            <select name="country" class="w-full px-5 py-3 bg-gray-50 dark:bg-gray-800 border-2 border-transparent focus:border-primary focus:bg-white dark:focus:bg-gray-900 rounded-2xl transition-all outline-none font-medium text-gray-900 dark:text-white">
                                <option value="">Select your country</option>
                                <optgroup label="🌍 Africa">
                                    <?php foreach (['Nigeria','Ghana','Kenya','South Africa','Uganda','Tanzania','Senegal','Cameroon','Ivory Coast','Zimbabwe'] as $c): ?>
                                        <option value="<?php echo $c; ?>" <?php echo ($creator['country'] ?? '') === $c ? 'selected' : ''; ?>><?php echo $c; ?><?php echo $c==='Nigeria'?' (NGN)':($c==='Ghana'?' (GHS)':''); ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <optgroup label="🌐 Other">
                                    <?php foreach (['United Kingdom','United States','Canada','Other'] as $c): ?>
                                        <option value="<?php echo $c; ?>" <?php echo ($creator['country'] ?? '') === $c ? 'selected' : ''; ?>><?php echo $c; ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2 ml-1">Preferred Payout Currency</label>
                            <select name="payout_currency" id="payout-currency-select"
                                    onchange="showPayoutTab(this.value)"
                                    class="w-full px-5 py-3 bg-gray-50 dark:bg-gray-800 border-2 border-transparent focus:border-primary focus:bg-white dark:focus:bg-gray-900 rounded-2xl transition-all outline-none font-medium text-gray-900 dark:text-white">
                                <option value="NGN" <?php echo ($creator['payout_currency'] ?? 'USD') === 'NGN' ? 'selected' : ''; ?>>🇳🇬 NGN — Nigerian Naira</option>
                                <option value="GHS" <?php echo ($creator['payout_currency'] ?? 'USD') === 'GHS' ? 'selected' : ''; ?>>🇬🇭 GHS — Ghanaian Cedis</option>
                                <option value="USD" <?php echo ($creator['payout_currency'] ?? 'USD') === 'USD' ? 'selected' : ''; ?>>🌐 USD — US Dollar</option>
                            </select>
                            <p class="text-xs text-gray-400 mt-1.5 ml-1">All earnings are converted to this currency for payout. Admin-set exchange rates apply.</p>
                        </div>
                    </div>

                    <!-- Bank Details Tabs -->
                    <div class="space-y-4">
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-widest ml-1">Bank / Payout Details</p>
                        <p class="text-xs text-gray-500 -mt-2">Fill in your details for the currencies you want to receive payouts in. You can add details for multiple currencies — we'll use your preferred currency above.</p>

                        <!-- Tab buttons -->
                        <div class="flex gap-2 flex-wrap" id="payout-tab-btns">
                            <?php foreach (['NGN' => '🇳🇬 Nigerian Naira', 'GHS' => '🇬🇭 Ghanaian Cedis', 'USD' => '🌐 US Dollar'] as $ccy => $label): ?>
                                <button type="button" onclick="showPayoutTab('<?php echo $ccy; ?>')"
                                        id="tab-btn-<?php echo $ccy; ?>"
                                        class="px-4 py-2 rounded-xl text-sm font-bold transition tab-btn <?php echo ($creator['payout_currency'] ?? 'USD') === $ccy ? 'bg-primary text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-primary/10'; ?>">
                                    <?php echo $label; ?>
                                </button>
                            <?php endforeach; ?>
                        </div>

                        <!-- NGN Fields -->
                        <div id="payout-tab-NGN" class="payout-tab space-y-4 p-6 bg-gray-50 dark:bg-gray-800/50 rounded-2xl border border-gray-100 dark:border-gray-700 <?php echo ($creator['payout_currency'] ?? 'USD') !== 'NGN' ? 'hidden' : ''; ?>">
                            <p class="text-xs font-black uppercase tracking-widest text-orange-600 mb-3">🇳🇬 Nigerian Bank Account (NGN)</p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1.5">Bank Name</label>
                                    <input type="text" name="bank[NGN][bank_name]" value="<?php echo $bv('NGN','bank_name'); ?>" placeholder="e.g. GTBank, Access Bank, Zenith" class="w-full px-4 py-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-primary text-sm font-medium dark:text-white">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1.5">Account Number</label>
                                    <input type="text" name="bank[NGN][account_number]" value="<?php echo $bv('NGN','account_number'); ?>" placeholder="10-digit NUBAN account number" maxlength="10" pattern="\d{10}" class="w-full px-4 py-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-primary text-sm font-medium dark:text-white font-mono">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1.5">Account Name (as on bank records)</label>
                                <input type="text" name="bank[NGN][account_name]" value="<?php echo $bv('NGN','account_name'); ?>" placeholder="Full name as it appears on your bank account" class="w-full px-4 py-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-primary text-sm font-medium dark:text-white">
                            </div>
                        </div>

                        <!-- GHS Fields -->
                        <div id="payout-tab-GHS" class="payout-tab space-y-4 p-6 bg-gray-50 dark:bg-gray-800/50 rounded-2xl border border-gray-100 dark:border-gray-700 <?php echo ($creator['payout_currency'] ?? 'USD') !== 'GHS' ? 'hidden' : ''; ?>">
                            <p class="text-xs font-black uppercase tracking-widest text-green-700 mb-3">🇬🇭 Ghanaian Bank Account (GHS)</p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1.5">Bank Name</label>
                                    <input type="text" name="bank[GHS][bank_name]" value="<?php echo $bv('GHS','bank_name'); ?>" placeholder="e.g. Ecobank, GCB, Absa Ghana" class="w-full px-4 py-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-primary text-sm font-medium dark:text-white">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1.5">Account Number</label>
                                    <input type="text" name="bank[GHS][account_number]" value="<?php echo $bv('GHS','account_number'); ?>" placeholder="Bank account number" class="w-full px-4 py-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-primary text-sm font-medium dark:text-white font-mono">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1.5">Account Name</label>
                                <input type="text" name="bank[GHS][account_name]" value="<?php echo $bv('GHS','account_name'); ?>" placeholder="Full name as it appears on your bank account" class="w-full px-4 py-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-primary text-sm font-medium dark:text-white">
                            </div>
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1.5">Mobile Money Number <span class="text-gray-400 font-normal normal-case">(optional — MoMo / MTN / Vodafone Cash)</span></label>
                                <input type="text" name="bank[GHS][momo_number]" value="<?php echo $bv('GHS','momo_number'); ?>" placeholder="e.g. 0241234567" class="w-full px-4 py-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-primary text-sm font-medium dark:text-white font-mono">
                                <p class="text-xs text-gray-400 mt-1">We'll use bank account by default; MoMo is a backup option.</p>
                            </div>
                        </div>

                        <!-- USD Fields -->
                        <div id="payout-tab-USD" class="payout-tab space-y-4 p-6 bg-gray-50 dark:bg-gray-800/50 rounded-2xl border border-gray-100 dark:border-gray-700 <?php echo ($creator['payout_currency'] ?? 'USD') !== 'USD' ? 'hidden' : ''; ?>">
                            <p class="text-xs font-black uppercase tracking-widest text-blue-600 mb-3">🌐 USD Bank Account</p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1.5">Bank Name</label>
                                    <input type="text" name="bank[USD][bank_name]" value="<?php echo $bv('USD','bank_name'); ?>" placeholder="e.g. Chase, Bank of America" class="w-full px-4 py-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-primary text-sm font-medium dark:text-white">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1.5">Account Holder Name</label>
                                    <input type="text" name="bank[USD][account_name]" value="<?php echo $bv('USD','account_name'); ?>" placeholder="Full legal name on your account" class="w-full px-4 py-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-primary text-sm font-medium dark:text-white">
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1.5">Account Number (Checking)</label>
                                    <input type="text" name="bank[USD][account_number]" value="<?php echo $bv('USD','account_number'); ?>" placeholder="Checking account number" class="w-full px-4 py-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-primary text-sm font-medium dark:text-white font-mono">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1.5">ACH Routing Number <span class="text-gray-400 font-normal normal-case">(US only)</span></label>
                                    <input type="text" name="bank[USD][routing_number]" value="<?php echo $bv('USD','routing_number'); ?>" placeholder="9-digit routing number" maxlength="9" class="w-full px-4 py-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-primary text-sm font-medium dark:text-white font-mono">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1.5">SWIFT / BIC Code <span class="text-gray-400 font-normal normal-case">(for international wires)</span></label>
                                <input type="text" name="bank[USD][swift_bic]" value="<?php echo $bv('USD','swift_bic'); ?>" placeholder="e.g. CHASUS33, BOFAUS3N" class="w-full md:w-1/2 px-4 py-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-primary text-sm font-medium dark:text-white font-mono uppercase">
                                <p class="text-xs text-gray-400 mt-1">Required for international USD transfers. Not needed for ACH within the US.</p>
                            </div>
                        </div>

                        <div class="p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-2xl">
                            <p class="text-xs text-amber-800 dark:text-amber-300 font-medium">🔒 Your bank details are stored securely and only used by Splennet admins to process your payouts. They are never shared with brands.</p>
                        </div>
                    </div>
                </section>

                <div class="flex justify-end pt-4">
                    <button type="submit" class="px-12 py-5 bg-gray-900 dark:bg-white text-white dark:text-gray-900 text-lg font-black rounded-2xl shadow-xl hover:bg-primary hover:text-white transition-all transform hover:scale-105 active:scale-95">
                        Save Changes
                    </button>
                </div>
            </form>

            <script>
            function showPayoutTab(ccy) {
                // Hide all tab panels
                document.querySelectorAll('.payout-tab').forEach(el => el.classList.add('hidden'));
                // Deactivate all buttons
                document.querySelectorAll('.tab-btn').forEach(btn => {
                    btn.classList.remove('bg-primary', 'text-white');
                    btn.classList.add('bg-gray-100', 'dark:bg-gray-800', 'text-gray-600', 'dark:text-gray-300');
                });
                // Show selected panel
                const panel = document.getElementById('payout-tab-' + ccy);
                if (panel) panel.classList.remove('hidden');
                // Activate selected button
                const btn = document.getElementById('tab-btn-' + ccy);
                if (btn) {
                    btn.classList.add('bg-primary', 'text-white');
                    btn.classList.remove('bg-gray-100', 'dark:bg-gray-800', 'text-gray-600', 'dark:text-gray-300');
                }
                // Keep select in sync
                const sel = document.getElementById('payout-currency-select');
                if (sel) sel.value = ccy;
            }
            // Init to saved payout currency
            showPayoutTab('<?php echo htmlspecialchars($creator['payout_currency'] ?? 'USD', ENT_QUOTES); ?>');
            </script>
        </main>
    </div>
</div>

<?php 
include '../includes/footer.php';
?>