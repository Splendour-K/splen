<?php
require_once "../config/database.php";
require_once "../includes/functions.php";
require_role("admin");

$success = "";
$error = "";

// Handle Settings Update
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_settings"])) {
    foreach ($_POST["settings"] as $key => $value) {
        $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$key, $value, $value]);
    }
    $success = "Global settings updated successfully!";
}

// Fetch current settings
$stmt = $pdo->query("SELECT * FROM site_settings");
$settings_raw = $stmt->fetchAll();
$settings = [];
foreach ($settings_raw as $s) {
    $settings[$s["setting_key"]] = $s["setting_value"];
}

include "../includes/header.php";
?>

<div class="pt-24 min-h-screen bg-gray-50 dark:bg-gray-950">
    <div class="max-w-7xl mx-auto px-6 md:px-12 flex flex-col md:flex-row gap-8 py-8">
        <?php include "dashboard_sidebar.php"; ?>

        <main class="flex-1 space-y-8">
            <header class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm relative overflow-hidden">
                <div aria-hidden="true" class="absolute top-0 right-0 -mr-16 -mt-16 w-64 h-64 bg-orange-500/5 rounded-full blur-3xl"></div>
                <div class="relative">
                    <h2 class="text-3xl font-black text-gray-900 dark:text-white">Site Configurations</h2>
                    <p class="text-gray-500 font-bold mt-1">Manage global platform limits, commissions, and announcements.</p>
                </div>
            </header>

            <?php if ($success): ?>
                <div class="p-4 bg-green-50 text-green-700 font-bold rounded-2xl border border-green-100 italic">✨ <?php echo $success; ?></div>
            <?php endif; ?>

            <form method="POST" class="space-y-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Brand Quota Settings -->
                    <section class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm space-y-6">
                        <div class="flex items-center gap-4 mb-4">
                            <span class="w-10 h-10 rounded-xl bg-orange-50 text-orange-600 flex items-center justify-center font-bold">🏢</span>
                            <h3 class="text-xl font-black text-gray-900 dark:text-white uppercase tracking-tighter">Brand Quotas</h3>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3 ml-2">Basic Tier Monthly Limit</label>
                            <input type="number" name="settings[basic_monthly_limit]" value="<?php echo e($settings["basic_monthly_limit"] ?? "3"); ?>" class="w-full px-6 py-4 bg-gray-50 dark:bg-gray-800 border-2 border-transparent focus:border-orange-500 rounded-2xl outline-none transition-all font-bold dark:text-white">
                        </div>

                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3 ml-2">Pro Tier Monthly Limit</label>
                            <input type="number" name="settings[pro_monthly_limit]" value="<?php echo e($settings["pro_monthly_limit"] ?? "15"); ?>" class="w-full px-6 py-4 bg-gray-50 dark:bg-gray-800 border-2 border-transparent focus:border-orange-500 rounded-2xl outline-none transition-all font-bold dark:text-white">
                        </div>

                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3 ml-2">Pro Tier Price ($)</label>
                            <input type="number" name="settings[pro_tier_price]" value="<?php echo e($settings["pro_tier_price"] ?? "49"); ?>" class="w-full px-6 py-4 bg-gray-50 dark:bg-gray-800 border-2 border-transparent focus:border-orange-500 rounded-2xl outline-none transition-all font-bold dark:text-white">
                        </div>
                    </section>

                    <!-- Platform Settings -->
                    <section class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm space-y-6">
                         <div class="flex items-center gap-4 mb-4">
                            <span class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold">🌍</span>
                            <h3 class="text-xl font-black text-gray-900 dark:text-white uppercase tracking-tighter">Global Config</h3>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3 ml-2">Maintenance Mode</label>
                            <select name="settings[maintenance_mode]" class="w-full px-6 py-4 bg-gray-50 dark:bg-gray-800 border-2 border-transparent focus:border-orange-500 rounded-2xl outline-none transition-all font-bold dark:text-white">
                                <option value="0" <?php echo ($settings["maintenance_mode"] ?? "0") == "0" ? "selected" : ""; ?>>Off (Live)</option>
                                <option value="1" <?php echo ($settings["maintenance_mode"] ?? "0") == "1" ? "selected" : ""; ?>>On (Under Maintenance)</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3 ml-2">Platform Fee (%)</label>
                            <input type="number" name="settings[platform_commission]" value="<?php echo e($settings["platform_commission"] ?? "10"); ?>" class="w-full px-6 py-4 bg-gray-50 dark:bg-gray-800 border-2 border-transparent focus:border-orange-500 rounded-2xl outline-none transition-all font-bold dark:text-white">
                        </div>
                    </section>

                    <!-- Video Watermark -->
                    <section class="md:col-span-2 p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm space-y-6">
                         <div class="flex items-center gap-4 mb-2">
                            <span class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold">🎬</span>
                            <h3 class="text-xl font-black text-gray-900 dark:text-white uppercase tracking-tighter">Video Watermark</h3>
                        </div>
                        <p class="text-xs text-gray-500 -mt-2 ml-14">Text burned onto preview videos shown to brands before approval.</p>

                        <?php $ffmpeg_ok = ffmpeg_available(); ?>
                        <div class="p-4 rounded-2xl border-2 <?php echo $ffmpeg_ok ? 'border-green-200 bg-green-50 dark:bg-green-900/20' : 'border-orange-200 bg-orange-50 dark:bg-orange-900/20'; ?>">
                            <p class="text-sm font-bold <?php echo $ffmpeg_ok ? 'text-green-800 dark:text-green-300' : 'text-orange-800 dark:text-orange-300'; ?>">
                                <?php if ($ffmpeg_ok): ?>
                                    ✓ FFmpeg detected — real watermarking is active.
                                <?php else: ?>
                                    ⚠ FFmpeg not detected on PATH — uploads fall back to a plain copy with an on-screen "Watermarked" badge.
                                <?php endif; ?>
                            </p>
                            <?php if (!$ffmpeg_ok): ?>
                                <p class="text-xs text-orange-700 dark:text-orange-400 mt-2">Install FFmpeg and add it to the system PATH to enable real burn-in watermarking. On Windows: download from ffmpeg.org and add the bin folder to your PATH environment variable, then restart Apache.</p>
                            <?php endif; ?>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3 ml-2">Watermark Text</label>
                            <input type="text" name="settings[watermark_text]" value="<?php echo e($settings["watermark_text"] ?? "SPLENNET PREVIEW"); ?>" maxlength="40" class="w-full px-6 py-4 bg-gray-50 dark:bg-gray-800 border-2 border-transparent focus:border-orange-500 rounded-2xl outline-none transition-all font-bold dark:text-white">
                            <p class="text-xs text-gray-500 mt-2 ml-2">Up to 40 characters. Burned in diagonally across the brand's preview.</p>
                        </div>
                    </section>

                    <!-- Exchange Rates -->
                    <section class="md:col-span-2 p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm space-y-6">
                         <div class="flex items-center gap-4 mb-2">
                            <span class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center font-bold">💱</span>
                            <h3 class="text-xl font-black text-gray-900 dark:text-white uppercase tracking-tighter">Exchange Rates (vs USD)</h3>
                        </div>
                        <p class="text-xs text-gray-500 -mt-2 ml-14">Set how many units of each currency equal <strong>1 USD</strong>. Used to display creator earnings in their chosen currency.</p>

                        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                            <div>
                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3 ml-2">1 USD = NGN</label>
                                <input type="number" step="0.01" name="settings[fx_rate_NGN]" value="<?php echo e($settings["fx_rate_NGN"] ?? "1500"); ?>" class="w-full px-6 py-4 bg-gray-50 dark:bg-gray-800 border-2 border-transparent focus:border-orange-500 rounded-2xl outline-none transition-all font-bold dark:text-white">
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3 ml-2">1 USD = GHS</label>
                                <input type="number" step="0.01" name="settings[fx_rate_GHS]" value="<?php echo e($settings["fx_rate_GHS"] ?? "12"); ?>" class="w-full px-6 py-4 bg-gray-50 dark:bg-gray-800 border-2 border-transparent focus:border-orange-500 rounded-2xl outline-none transition-all font-bold dark:text-white">
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3 ml-2">1 USD = EUR</label>
                                <input type="number" step="0.0001" name="settings[fx_rate_EUR]" value="<?php echo e($settings["fx_rate_EUR"] ?? "0.92"); ?>" class="w-full px-6 py-4 bg-gray-50 dark:bg-gray-800 border-2 border-transparent focus:border-orange-500 rounded-2xl outline-none transition-all font-bold dark:text-white">
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3 ml-2">1 USD = GBP</label>
                                <input type="number" step="0.0001" name="settings[fx_rate_GBP]" value="<?php echo e($settings["fx_rate_GBP"] ?? "0.78"); ?>" class="w-full px-6 py-4 bg-gray-50 dark:bg-gray-800 border-2 border-transparent focus:border-orange-500 rounded-2xl outline-none transition-all font-bold dark:text-white">
                            </div>
                        </div>
                    </section>

                    <!-- Custom Content -->
                    <section class="md:col-span-2 p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm space-y-6">
                         <div class="flex items-center gap-4 mb-4">
                            <span class="w-10 h-10 rounded-xl bg-green-50 text-green-600 flex items-center justify-center font-bold">📢</span>
                            <h3 class="text-xl font-black text-gray-900 dark:text-white uppercase tracking-tighter">Announcements</h3>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3 ml-2">Banner Message</label>
                            <textarea name="settings[announcement_text]" rows="2" class="w-full px-6 py-4 bg-gray-50 dark:bg-gray-800 border-2 border-transparent focus:border-orange-500 rounded-2xl outline-none transition-all font-bold dark:text-white"><?php echo e($settings["announcement_text"] ?? ""); ?></textarea>
                        </div>
                    </section>
                </div>

                <div class="flex justify-end pt-8">
                     <button type="submit" name="update_settings" class="px-12 py-5 bg-gray-900 text-white font-black rounded-3xl shadow-2xl hover:scale-105 active:scale-95 transition-all">Save Global Settings</button>
                </div>
            </form>
        </main>
    </div>
</div>

<?php include "../includes/footer.php"; ?>
