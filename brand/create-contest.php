<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/wallet_functions.php';
require_role('brand');

$stmt = $pdo->prepare("SELECT * FROM brands WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$brand = $stmt->fetch();
require_brand_record($brand);

$error = '';
$success = '';

// Preserve all submitted values across validation failures.
$title                    = $_POST['title']                    ?? '';
$description              = $_POST['description']              ?? '';
$category                 = $_POST['category']                 ?? '';
$total_contest_budget_raw = $_POST['total_contest_budget']     ?? '';
$currency                 = $_POST['currency']                 ?? 'USD';
$submission_deadline      = $_POST['submission_deadline']      ?? '';
$winner_announcement_date = $_POST['winner_announcement_date'] ?? '';
$number_of_winners        = $_POST['number_of_winners']        ?? 1;
$terms_conditions         = $_POST['terms_conditions']         ?? '';
// Prize distribution (manual)
$prize_amounts_raw        = array_map('floatval', $_POST['prize_amounts']   ?? []);
$position_names_raw       = array_map('trim',     $_POST['position_names']  ?? []);
// CPM fields (optional)
$cpm_enabled              = isset($_POST['cpm_enabled']);
$pay_per_1000_views_raw   = $_POST['pay_per_1000_views']       ?? '';
$max_payable_views_raw    = $_POST['max_payable_views']        ?? '';
$reference_links_raw      = array_values(array_filter(array_map('trim', $_POST['reference_links'] ?? [])));

// Derive total budget from prize sum (JS sets hidden field, but also calculate server-side for safety)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($prize_amounts_raw)) {
    $total_contest_budget_raw = array_sum($prize_amounts_raw);
    $number_of_winners = count(array_filter($prize_amounts_raw, fn($a) => $a > 0));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $budget_num = (float)$total_contest_budget_raw;
    $winners_num = (int)$number_of_winners;

    if (!$title || !$budget_num || !$submission_deadline) {
        $error = "Title, Budget, and Submission Deadline are required.";
    } else if (strtotime($submission_deadline) <= time()) {
        $error = "Submission deadline must be in the future.";
    } else if ($winners_num < 1) {
        $error = "Number of winners must be at least 1.";
    } else {
        $validation_error = validate_minimum_payment($budget_num, $currency);
        if ($validation_error !== true) {
            $error = $validation_error;
        } else {
            // Compute total required budget (prize pool + optional CPM reserve)
            $cpm_rate_val   = $cpm_enabled ? (float)$pay_per_1000_views_raw : 0;
            $cpm_cap_val    = $cpm_enabled ? (int)$max_payable_views_raw    : 0;
            $cpm_total      = ($cpm_rate_val > 0 && $cpm_cap_val > 0)
                              ? ($cpm_rate_val * ($cpm_cap_val / 1000) * $winners_num)
                              : 0;
            $required_budget = $budget_num + $cpm_total;

            $sql = "INSERT INTO contests (
                brand_id, title, description, category, total_contest_budget, currency,
                submission_deadline, winner_announcement_date, number_of_winners, terms_conditions, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'live')";

            $stmt = $pdo->prepare($sql);
            try {
                $stmt->execute([
                    $brand['id'], $title, $description, $category, $budget_num, $currency,
                    $submission_deadline, $winner_announcement_date ?: null, $winners_num, $terms_conditions
                ]);
                $contest_id = $pdo->lastInsertId();

                // Save featured image (optional — column added by fix_contest_launch.sql)
                if (!empty($_FILES['featured_image']['name']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
                    $fi_dir  = "../assets/uploads/listings/";
                    if (!is_dir($fi_dir)) mkdir($fi_dir, 0755, true);
                    $fi_ext  = strtolower(pathinfo($_FILES['featured_image']['name'], PATHINFO_EXTENSION));
                    $fi_size = $_FILES['featured_image']['size'];
                    if (in_array($fi_ext, ['jpg','jpeg','png','webp']) && $fi_size <= 5 * 1024 * 1024) {
                        $fi_name = "contest_{$contest_id}_" . time() . ".{$fi_ext}";
                        if (move_uploaded_file($_FILES['featured_image']['tmp_name'], $fi_dir . $fi_name)) {
                            try {
                                $pdo->prepare("UPDATE contests SET featured_image = ? WHERE id = ?")
                                    ->execute(["assets/uploads/listings/{$fi_name}", $contest_id]);
                            } catch (\Exception $fi_e) {
                                error_log("featured_image (contests) update failed: " . $fi_e->getMessage());
                            }
                        }
                    }
                }

                // Save reference links (optional)
                if (!empty($reference_links_raw)) {
                    try {
                        $rl_stmt = $pdo->prepare(
                            "INSERT INTO contest_reference_links (contest_id, link_url, link_type) VALUES (?, ?, 'inspiration')"
                        );
                        foreach ($reference_links_raw as $rl) {
                            if (strlen($rl) >= 10) {
                                $rl_stmt->execute([$contest_id, $rl]);
                            }
                        }
                    } catch (\Exception $rl_e) {
                        error_log('contest_reference_links insert failed: ' . $rl_e->getMessage());
                    }
                }

                // Save optional CPM fields (graceful — only works after fix_contest_launch.sql is run)
                if ($cpm_enabled && $cpm_rate_val > 0) {
                    try {
                        $pdo->prepare("UPDATE contests SET pay_per_1000_views = ?, max_payable_views_per_creator = ?, cpm_budget = ? WHERE id = ?")
                            ->execute([$cpm_rate_val, $cpm_cap_val ?: null, $cpm_total ?: null, $contest_id]);
                    } catch (Exception $cpm_e) { /* CPM columns not yet migrated */ }
                }

                // Best-effort wallet reservation — non-blocking (does not prevent launch)
                $reserve_desc = "Contest reserved: {$title} (prize pool " . format_money($budget_num, $currency) .
                                ($cpm_total > 0 ? " + CPM " . format_money($cpm_total, $currency) : '') . ")";
                $wallet_reserved = false;
                $wc = check_wallet_for_publish($brand['id'], $required_budget, $currency);
                if ($wc['ok']) {
                    $wallet_reserved = reserve_wallet_budget(
                        $brand['id'], $required_budget, 'contest_reserve', 'contest', (int)$contest_id, $reserve_desc
                    );
                }

                // Save individual prize amounts
                $rew_stmt = $pdo->prepare("INSERT INTO contest_rewards (contest_id, position_number, position_name, reward_amount, currency) VALUES (?, ?, ?, ?, ?)");
                foreach ($prize_amounts_raw as $pi => $amt) {
                    if ($amt <= 0) continue;
                    $pos_num  = $pi + 1;
                    $pos_name = $position_names_raw[$pi] ?? '';
                    if ($pos_name === '') {
                        $default_names = ['1st Place','2nd Place','3rd Place','4th Place','5th Place'];
                        $pos_name = $default_names[$pi] ?? ('Position ' . $pos_num);
                    }
                    $rew_stmt->execute([$contest_id, $pos_num, $pos_name, $amt, $currency]);
                }

                // Notify all creators of the new contest
                try {
                    $cids = $pdo->query("SELECT user_id FROM creators WHERE user_id IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
                    if ($cids) {
                        create_notification_batch(
                            $cids,
                            'New Contest: ' . mb_substr($title, 0, 60),
                            ($brand['brand_name'] ?? 'A brand') . ' launched a new contest — ' . $currency . ' ' . number_format($budget_num, 0) . ' prize pool. Enter now!',
                            'contest',
                            'contest-board.php',
                            'contest',
                            (int)$contest_id
                        );
                    }
                } catch (Exception $notif_err) {
                    error_log('Contest notification failed: ' . $notif_err->getMessage());
                }

                $wallet_note = $wallet_reserved
                    ? format_money($required_budget, $currency) . " has been reserved from your wallet."
                    : "Note: Wallet reservation pending — please ensure your wallet is funded with " . $currency . ".";
                $success = "Contest launched successfully! " . $wallet_note;
            } catch (Exception $e) {
                $error = "Error creating contest: " . $e->getMessage();
            }
        }
    }
}

$mins = minimum_payments();
$min_for_current = $mins[$currency] ?? 5;

include '../includes/header.php';
?>

<div class="pt-24 min-h-screen bg-gray-50 dark:bg-gray-950">
    <div class="max-w-7xl mx-auto px-6 md:px-12 flex flex-col md:flex-row gap-8 py-8">
        <?php include '../includes/brand_sidebar.php'; ?>

        <main class="flex-1 space-y-8">
            <header class="p-8 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm relative overflow-hidden">
                <div aria-hidden="true" class="absolute top-0 right-0 -mr-16 -mt-16 w-64 h-64 bg-secondary/5 rounded-full blur-3xl"></div>
                <div class="relative text-center md:text-left">
                    <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Create a Contest</h2>
                    <p class="text-gray-600 dark:text-gray-400 mt-2">Launch a contest to get creative submissions from your audience.</p>
                </div>
            </header>

            <?php if ($success): ?>
                <div class="p-8 bg-green-50 dark:bg-green-950/30 border border-green-200 dark:border-green-800 rounded-[2rem] text-center shadow-sm">
                    <div class="w-20 h-20 bg-green-100 dark:bg-green-900/40 rounded-full flex items-center justify-center text-4xl mx-auto mb-6">🎉</div>
                    <h3 class="text-2xl font-bold text-green-900 dark:text-green-400 mb-2">Contest Published!</h3>
                    <p class="text-green-700 dark:text-green-300 mb-8"><?php echo e($success); ?></p>
                    <a href="<?php echo APP_URL; ?>brand/dashboard.php" class="inline-flex h-12 items-center justify-center px-8 bg-secondary text-white font-bold rounded-full hover:scale-105 transition">Go to Dashboard</a>
                </div>
            <?php else: ?>

            <?php if ($error): ?>
                <div class="p-6 bg-red-100 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-2xl text-red-800 dark:text-red-400 font-bold">
                    <p class="flex items-start gap-3"><span class="text-xl flex-shrink-0">⚠️</span><span><?php echo nl2br(e($error)); ?></span></p>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="space-y-8" id="contest-form" novalidate>
                <section class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm space-y-6">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
                        <span class="w-8 h-8 rounded-lg bg-secondary text-white flex items-center justify-center font-black text-sm">1</span>
                        Contest Details
                    </h3>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Contest Title <span class="text-red-500">*</span></label>
                        <input type="text" name="title" id="f-title" data-required value="<?php echo e($title); ?>" minlength="3" maxlength="120" class="w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary" placeholder="e.g., Best Student Product Video">
                        <p class="text-xs text-gray-500 mt-1">Required. 3–120 characters.</p>
                        <p class="text-xs text-red-500 font-medium mt-1 hidden" data-error-for="f-title"></p>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Description</label>
                        <div class="ql-wrap" id="description_wrap"><div id="description_editor"></div></div>
                        <input type="hidden" name="description" id="description_h">
                        <p class="text-xs text-gray-500 mt-1">Optional — supports <strong>bold</strong>, <em>italic</em>, lists &amp; more.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Category</label>
                        <select name="category" class="w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary">
                            <option value="">Select a category</option>
                            <?php $cats = [
                                'beauty'       => 'Beauty',
                                'skincare'     => 'Skincare',
                                'fashion'      => 'Fashion & Style',
                                'food'         => 'Food & Beverage',
                                'tech'         => 'Tech & Innovation',
                                'apps'         => 'Mobile Apps',
                                'books'        => 'Books & Education',
                                'wellness'     => 'Health & Wellness',
                                'sports'       => 'Sports & Fitness',
                                'gaming'       => 'Gaming',
                                'music'        => 'Music & Entertainment',
                                'travel'       => 'Travel',
                                'finance'      => 'Finance & Fintech',
                                'lifestyle'    => 'Lifestyle',
                                'home'         => 'Home & Lifestyle',
                                'automotive'   => 'Automotive',
                                'pets'         => 'Pets',
                                'product'      => 'Product Demo',
                                'education'    => 'Education',
                                'other'        => 'Other',
                            ];
                            foreach ($cats as $val => $label): ?>
                                <option value="<?php echo $val; ?>" <?php echo $category === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </section>

                <!-- Featured Image (Optional) -->
                <section class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm space-y-5">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
                        <span class="w-8 h-8 rounded-lg bg-blue-500/10 text-blue-500 flex items-center justify-center text-sm">🖼️</span>
                        Featured Image <span class="ml-2 text-xs font-normal text-gray-400 bg-gray-100 dark:bg-gray-800 px-2 py-0.5 rounded-full">Optional</span>
                    </h3>
                    <p class="text-sm text-gray-500 -mt-2">Add a banner image to make your contest stand out. JPG, PNG, WEBP · max 5 MB · 16:9 recommended.</p>
                    <label class="block relative w-full rounded-2xl overflow-hidden cursor-pointer group" style="aspect-ratio:16/7;" id="featured-img-label">
                        <div class="absolute inset-0 flex flex-col items-center justify-center bg-gray-50 dark:bg-gray-800 border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-2xl group-hover:border-secondary transition" id="featured-img-placeholder">
                            <svg class="w-10 h-10 text-gray-300 mb-2 group-hover:text-secondary transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <p class="text-sm font-bold text-gray-400 group-hover:text-secondary transition">Click to upload a featured image</p>
                            <p class="text-xs text-gray-400 mt-1">16:9 ratio · JPG, PNG, WEBP · max 5 MB</p>
                        </div>
                        <img id="featured-img-preview" class="w-full h-full object-cover absolute inset-0 hidden rounded-2xl" alt="Preview">
                        <div id="featured-img-change-hint" class="absolute inset-0 bg-black/40 text-white text-sm font-bold flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity rounded-2xl hidden pointer-events-none">
                            📷 Change Image
                        </div>
                        <input type="file" name="featured_image" accept="image/jpeg,image/png,image/webp" class="absolute inset-0 opacity-0 cursor-pointer w-full h-full" onchange="previewFeaturedImg(this)">
                    </label>
                </section>

                <section class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm space-y-6">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
                        <span class="w-8 h-8 rounded-lg bg-secondary text-white flex items-center justify-center font-black text-sm">2</span>
                        Prize Distribution
                    </h3>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Number of Winners <span class="text-red-500">*</span></label>
                            <input type="number" name="number_of_winners" id="f-winners" value="<?php echo max(1, (int)$number_of_winners); ?>" min="1" max="100" class="w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary">
                            <p class="text-xs text-gray-500 mt-1">Determines the number of prize positions below.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Currency</label>
                            <select name="currency" id="f-currency" class="w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary">
                                <?php foreach (["USD","NGN","GHS","EUR","GBP"] as $c): ?>
                                    <option value="<?php echo $c; ?>" <?php echo $currency === $c ? 'selected' : ''; ?>><?php echo $c; ?> (<?php echo get_currency_symbol($c); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Dynamic Prize Rows -->
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Prize Amounts per Position <span class="text-red-500">*</span></label>
                        <p class="text-xs text-gray-500 mb-3">Enter the exact prize for each winner. You can rename the position labels (e.g., "Grand Prize", "Runner-Up").</p>
                        <div id="prize-rows-container" class="space-y-3">
                            <!-- Generated dynamically by JS below -->
                        </div>
                        <!-- Total summary -->
                        <div class="mt-4 p-4 bg-secondary/5 border border-secondary/20 rounded-xl flex items-center justify-between">
                            <div>
                                <p class="text-sm font-bold text-gray-700 dark:text-gray-300">Total Prize Pool</p>
                                <p class="text-xs text-gray-500 mt-0.5">Min. <span id="min-display"><?php echo number_format($min_for_current); ?></span> <span id="ccy-display"><?php echo e($currency); ?></span> · deducted from your wallet on launch</p>
                            </div>
                            <p class="text-xl font-black text-secondary" id="prize-total-display">0.00</p>
                        </div>
                        <!-- Hidden field populated by JS — read by PHP backend -->
                        <input type="hidden" name="total_contest_budget" id="f-amount">
                        <p class="text-xs text-red-500 font-medium mt-2 hidden" data-error-for="f-amount"></p>
                    </div>
                </section>

                <section class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm space-y-6">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
                        <span class="w-8 h-8 rounded-lg bg-secondary text-white flex items-center justify-center font-black text-sm">3</span>
                        Timeline
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Submission Deadline <span class="text-red-500">*</span></label>
                            <input type="datetime-local" name="submission_deadline" id="f-deadline" data-required value="<?php echo e($submission_deadline); ?>" min="<?php echo date('Y-m-d\TH:i'); ?>" class="w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary">
                            <p class="text-xs text-gray-500 mt-1">Required. Must be in the future.</p>
                            <p class="text-xs text-red-500 font-medium mt-1 hidden" data-error-for="f-deadline"></p>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Winner Announcement Date</label>
                            <input type="datetime-local" name="winner_announcement_date" id="f-winner-date" value="<?php echo e($winner_announcement_date); ?>" class="w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary">
                            <p class="text-xs text-gray-500 mt-1">Optional. Must be after the submission deadline.</p>
                            <p class="text-xs text-red-500 font-medium mt-1 hidden" data-error-for="f-winner-date"></p>
                        </div>
                    </div>
                </section>

                <section class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm space-y-6">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
                        <span class="w-8 h-8 rounded-lg bg-secondary text-white flex items-center justify-center font-black text-sm">4</span>
                        Terms & Conditions
                    </h3>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Contest Rules</label>
                        <div class="ql-wrap" id="terms_wrap"><div id="terms_editor"></div></div>
                        <input type="hidden" name="terms_conditions" id="terms_conditions_h">
                        <p class="text-xs text-gray-500 mt-1">Optional — format rules as a list for easy reading.</p>
                    </div>
                </section>

                <!-- Section 5: Reference Videos (Optional) -->
                <section class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm space-y-5">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
                        <span class="w-8 h-8 rounded-lg bg-purple-500/10 text-purple-500 flex items-center justify-center text-sm">🎬</span>
                        Reference Videos
                        <span class="text-xs font-medium text-gray-400">(Optional)</span>
                    </h3>
                    <p class="text-sm text-gray-500 -mt-2">Share example videos so contestants understand the style, tone, and quality you expect.</p>

                    <div id="ref-links-container" class="space-y-3">
                        <?php if (!empty($reference_links_raw)): ?>
                            <?php foreach ($reference_links_raw as $rl): ?>
                                <div class="flex gap-2 ref-link-row">
                                    <input type="url" name="reference_links[]" value="<?php echo e($rl); ?>" placeholder="https://www.tiktok.com/@example/video/..." class="flex-1 px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary text-sm">
                                    <button type="button" onclick="this.parentElement.remove()" class="px-4 py-3 bg-red-100 dark:bg-red-900/20 text-red-500 font-black rounded-xl hover:bg-red-500 hover:text-white transition w-12 flex-shrink-0">×</button>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="flex gap-2 ref-link-row">
                                <input type="url" name="reference_links[]" placeholder="https://www.tiktok.com/@example/video/... (optional)" class="flex-1 px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary text-sm">
                                <button type="button" onclick="this.parentElement.remove()" class="px-4 py-3 bg-red-100 dark:bg-red-900/20 text-red-500 font-black rounded-xl hover:bg-red-500 hover:text-white transition w-12 flex-shrink-0">×</button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <button type="button" onclick="addRefLink()" class="flex items-center gap-2 px-4 py-3 border-2 border-dashed border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 rounded-xl hover:border-secondary hover:text-secondary transition text-sm font-bold w-full justify-center">
                        <span class="text-base leading-none">+</span> Add another reference link
                    </button>
                    <p class="text-xs text-gray-400">TikTok, Instagram Reels, YouTube Shorts, or any public video URL.</p>
                </section>

                <!-- Section 6: CPM (Optional) -->
                <section class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm space-y-6">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
                        <span class="w-8 h-8 rounded-lg bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300 flex items-center justify-center font-black text-sm">6</span>
                        CPM Bonus Pay
                        <span class="text-xs font-medium text-gray-400">(Optional)</span>
                    </h3>
                    <p class="text-sm text-gray-500">Pay creators per 1,000 verified views on their posted content — on top of the fixed prize. Leave unchecked to skip.</p>

                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="cpm_enabled" id="cpm-toggle" <?php echo $cpm_enabled ? 'checked' : ''; ?> class="w-5 h-5 rounded text-secondary" onchange="document.getElementById('cpm-fields').classList.toggle('hidden', !this.checked)">
                        <span class="font-bold text-gray-800 dark:text-gray-200 text-sm">Enable CPM payouts for this contest</span>
                    </label>

                    <div id="cpm-fields" class="space-y-4 <?php echo $cpm_enabled ? '' : 'hidden'; ?>">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Pay per 1,000 Views</label>
                                <input type="number" name="pay_per_1000_views" step="0.01" min="0" placeholder="e.g. 100" value="<?php echo e($pay_per_1000_views_raw); ?>" class="w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary">
                                <p class="text-xs text-gray-400 mt-1">Amount in <?php echo e($currency); ?> per 1,000 verified views</p>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Max Payable Views per Creator</label>
                                <input type="number" name="max_payable_views" min="0" placeholder="e.g. 500000" value="<?php echo e($max_payable_views_raw); ?>" class="w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary">
                                <p class="text-xs text-gray-400 mt-1">Leave blank for no cap</p>
                            </div>
                        </div>
                        <div class="p-4 bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800 rounded-xl">
                            <p class="text-xs text-indigo-700 dark:text-indigo-400">💡 CPM is paid in addition to fixed prizes. You approve each creator's view count before payment is issued.</p>
                        </div>
                    </div>
                </section>

                <div class="flex gap-4">
                    <button type="submit" id="submit-btn" disabled class="flex-1 py-4 bg-secondary text-white font-bold rounded-xl hover:scale-105 transition disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:scale-100">
                        Launch Contest
                    </button>
                    <a href="<?php echo APP_URL; ?>brand/my-contests.php" class="flex-1 py-4 bg-gray-200 dark:bg-gray-800 text-gray-900 dark:text-white font-bold rounded-xl text-center hover:scale-105 transition">
                        Cancel
                    </a>
                </div>
            </form>

            <script>
            function previewFeaturedImg(input) {
                const preview     = document.getElementById('featured-img-preview');
                const placeholder = document.getElementById('featured-img-placeholder');
                const changeHint  = document.getElementById('featured-img-change-hint');
                if (input.files && input.files[0]) {
                    const reader = new FileReader();
                    reader.onload = e => {
                        preview.src = e.target.result;
                        preview.classList.remove('hidden');
                        placeholder.classList.add('hidden');
                        changeHint.classList.remove('hidden');
                    };
                    reader.readAsDataURL(input.files[0]);
                }
            }

            function addRefLink() {
                const container = document.getElementById('ref-links-container');
                const row = document.createElement('div');
                row.className = 'flex gap-2 ref-link-row';
                row.innerHTML = `
                    <input type="url" name="reference_links[]" placeholder="https://..." class="flex-1 px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary text-sm">
                    <button type="button" onclick="this.parentElement.remove()" class="px-4 py-3 bg-red-100 dark:bg-red-900/20 text-red-500 font-black rounded-xl hover:bg-red-500 hover:text-white transition w-12 flex-shrink-0">×</button>
                `;
                container.appendChild(row);
                row.querySelector('input').focus();
            }

            /* ── Prize Distribution Helpers ── */
            const CCY_SYMBOLS = <?php echo json_encode(array_map('get_currency_symbol', array_combine(["USD","NGN","GHS","EUR","GBP"],["USD","NGN","GHS","EUR","GBP"]))); ?>;
            const PRIZE_DEFAULTS = <?php echo json_encode(array_values($prize_amounts_raw)); ?>;
            const NAME_DEFAULTS  = <?php echo json_encode(array_values($position_names_raw)); ?>;
            const ICONS = ['🥇','🥈','🥉','🏅','🏅','🏅','🏅','🏅','🏅','🏅'];
            const POS_NAMES = ['1st Place','2nd Place','3rd Place','4th Place','5th Place','6th Place','7th Place','8th Place','9th Place','10th Place'];

            function calcPrizeTotal() {
                const inputs = document.querySelectorAll('.prize-amount-input');
                let total = 0;
                inputs.forEach(inp => total += parseFloat(inp.value) || 0);
                document.getElementById('f-amount').value = total.toFixed(2);
                const ccy = document.getElementById('f-currency').value;
                const sym = CCY_SYMBOLS[ccy] || (ccy + ' ');
                document.getElementById('prize-total-display').textContent = sym + total.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                if (typeof validateAll === 'function') validateAll(false);
            }

            function generatePrizeRows(count) {
                count = Math.max(1, Math.min(100, parseInt(count) || 1));
                const container = document.getElementById('prize-rows-container');
                const ccy = document.getElementById('f-currency').value;
                container.innerHTML = '';
                for (let i = 0; i < count; i++) {
                    const icon     = ICONS[i] || '🏅';
                    const defName  = NAME_DEFAULTS[i] || POS_NAMES[i] || ('Position ' + (i+1));
                    const defAmt   = PRIZE_DEFAULTS[i] || '';
                    const row = document.createElement('div');
                    row.className = 'flex gap-3 items-center';
                    row.innerHTML = `
                        <div class="w-10 h-10 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-xl flex-shrink-0">${icon}</div>
                        <input type="text" name="position_names[]" value="${defName}" placeholder="Position name"
                            class="w-36 px-3 py-2.5 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary text-sm font-bold">
                        <input type="number" name="prize_amounts[]" step="0.01" min="0.01" value="${defAmt}" placeholder="0.00"
                            class="flex-1 px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary font-bold text-lg prize-amount-input"
                            oninput="calcPrizeTotal()">
                        <span class="text-sm font-bold text-gray-500 flex-shrink-0 ccy-lbl">${ccy}</span>
                    `;
                    container.appendChild(row);
                }
                calcPrizeTotal();
            }

            (function() {
                const MIN_BY_CCY = <?php echo json_encode(minimum_payments()); ?>;
                const form = document.getElementById('contest-form');
                const submitBtn = document.getElementById('submit-btn');
                const f_title = document.getElementById('f-title');
                const f_amount = document.getElementById('f-amount');
                const f_currency = document.getElementById('f-currency');
                const f_winners = document.getElementById('f-winners');
                const f_deadline = document.getElementById('f-deadline');
                const f_winner_date = document.getElementById('f-winner-date');
                const minDisplay = document.getElementById('min-display');
                const ccyDisplay = document.getElementById('ccy-display');

                function setError(input, message) {
                    const msgEl = form.querySelector(`[data-error-for="${input.id}"]`);
                    if (message) {
                        input.classList.add('border-red-400');
                        if (msgEl) { msgEl.textContent = message; msgEl.classList.remove('hidden'); }
                    } else {
                        input.classList.remove('border-red-400');
                        if (msgEl) { msgEl.classList.add('hidden'); msgEl.textContent = ''; }
                    }
                }

                window.validateAll = function(showAll) {
                    let ok = true;
                    const reasons = {};

                    if (!f_title.value.trim() || f_title.value.trim().length < 3) {
                        reasons.title = 'Title must be at least 3 characters.';
                        ok = false;
                    }

                    calcPrizeTotal();
                    const amount = parseFloat(f_amount.value) || 0;
                    const minRequired = MIN_BY_CCY[f_currency.value] ?? 5;
                    if (amount <= 0) {
                        reasons.amount = 'Enter prize amounts for each position.';
                        ok = false;
                    } else if (amount < minRequired) {
                        reasons.amount = `Total must be at least ${minRequired.toLocaleString()} ${f_currency.value}.`;
                        ok = false;
                    } else {
                        // Ensure every prize row has a positive amount
                        const prizeInputs = document.querySelectorAll('.prize-amount-input');
                        let allFilled = true;
                        prizeInputs.forEach(inp => { if (!(parseFloat(inp.value) > 0)) allFilled = false; });
                        if (!allFilled) {
                            reasons.amount = 'Every prize position must have an amount greater than 0.';
                            ok = false;
                        }
                    }

                    if (!f_deadline.value) {
                        reasons.deadline = 'Pick a submission deadline.';
                        ok = false;
                    } else {
                        const picked = new Date(f_deadline.value);
                        if (picked <= new Date()) {
                            reasons.deadline = 'Deadline must be in the future.';
                            ok = false;
                        }
                    }
                    if (f_winner_date.value && f_deadline.value) {
                        const wd = new Date(f_winner_date.value);
                        const dl = new Date(f_deadline.value);
                        if (wd <= dl) {
                            reasons.winner_date = 'Must be after the submission deadline.';
                            ok = false;
                        }
                    }

                    setError(f_title,       (showAll || f_title.dataset.touched)       ? (reasons.title       || '') : '');
                    setError(f_amount,      showAll                                     ? (reasons.amount      || '') : '');
                    setError(f_deadline,    (showAll || f_deadline.dataset.touched)    ? (reasons.deadline    || '') : '');
                    setError(f_winner_date, (showAll || f_winner_date.dataset.touched) ? (reasons.winner_date || '') : '');

                    submitBtn.disabled = !ok;
                    return ok;
                };

                function updateMinHint() {
                    const ccy = f_currency.value;
                    const min = MIN_BY_CCY[ccy] ?? 5;
                    minDisplay.textContent = min.toLocaleString();
                    ccyDisplay.textContent = ccy;
                    // Update all currency labels in prize rows
                    document.querySelectorAll('.ccy-lbl').forEach(el => el.textContent = ccy);
                    calcPrizeTotal();
                }

                f_winners.addEventListener('change', () => { generatePrizeRows(f_winners.value); });
                f_winners.addEventListener('input',  () => { generatePrizeRows(f_winners.value); });

                [f_title, f_deadline, f_winner_date].forEach(el => {
                    el.addEventListener('input', () => { el.dataset.touched = '1'; validateAll(false); });
                    el.addEventListener('blur',  () => { el.dataset.touched = '1'; validateAll(false); });
                });
                f_currency.addEventListener('change', () => { updateMinHint(); validateAll(false); });

                form.addEventListener('submit', (e) => {
                    calcPrizeTotal(); // belt-and-suspenders: ensure total is synced
                    if (window._qlDesc)  document.getElementById('description_h').value      = window._qlDesc.root.innerHTML;
                    if (window._qlTerms) document.getElementById('terms_conditions_h').value = window._qlTerms.root.innerHTML;
                    if (!validateAll(true)) { e.preventDefault(); }
                });

                // Initialise prize rows on first load
                generatePrizeRows(f_winners.value);
                validateAll(false);
                updateMinHint();
            })();
            </script>
            <!-- ── Quill Rich Text Editor ── -->
            <link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
            <style>
            .ql-wrap{border-radius:1rem;overflow:hidden;border:1.5px solid #d1d5db;background:#f8fafc;transition:border-color .2s,box-shadow .2s}
            .ql-wrap:focus-within{border-color:#ea580c!important;box-shadow:0 0 0 4px rgba(234,88,12,.12);outline:none}
            .dark .ql-wrap{background:#1e293b;border-color:#374151}
            .dark .ql-wrap:focus-within{border-color:#ea580c!important;box-shadow:0 0 0 4px rgba(234,88,12,.15)}
            .ql-toolbar.ql-snow{border:none!important;border-bottom:1px solid #e2e8f0!important;background:#f1f5f9;padding:8px 12px;font-family:'Urbanist',sans-serif!important}
            .dark .ql-toolbar.ql-snow{background:#0f172a;border-bottom-color:#1e293b!important}
            .dark .ql-toolbar .ql-stroke{stroke:#94a3b8}
            .dark .ql-toolbar .ql-fill{fill:#94a3b8}
            .dark .ql-toolbar button:hover .ql-stroke,.dark .ql-toolbar button.ql-active .ql-stroke{stroke:#f8fafc}
            .dark .ql-toolbar button:hover .ql-fill,.dark .ql-toolbar button.ql-active .ql-fill{fill:#f8fafc}
            .dark .ql-toolbar .ql-picker-label{color:#94a3b8}
            .dark .ql-toolbar .ql-picker-options{background:#0f172a;border-color:#334155}
            .dark .ql-toolbar .ql-picker-item:hover,.dark .ql-toolbar .ql-picker-item.ql-selected,.dark .ql-toolbar .ql-active .ql-picker-label{color:#f8fafc}
            .ql-container.ql-snow{border:none!important;font-family:'Urbanist',sans-serif!important;font-size:.9375rem}
            .ql-editor{min-height:8rem;padding:14px 18px;color:#0f172a}
            .ql-editor.ql-blank::before{color:#94a3b8;font-style:normal;left:18px;right:18px}
            .dark .ql-editor{color:#f8fafc}
            .dark .ql-editor.ql-blank::before{color:#475569}
            .ql-editor p{margin-bottom:.25em}
            .ql-editor h2{font-size:1.2em;font-weight:700}
            .ql-editor h3{font-size:1.05em;font-weight:600}
            .ql-editor ul,.ql-editor ol{padding-left:1.5em}
            .ql-editor blockquote{border-left:4px solid #ea580c;padding-left:1em;opacity:.8;margin:.3em 0}
            .ql-editor a{color:#ea580c;text-decoration:underline}
            </style>
            <script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
            <script>
            (function() {
                const TB = [
                    [{ header: [2, 3, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    ['blockquote', 'link'],
                    ['clean']
                ];
                window._qlDesc = new Quill('#description_editor', {
                    theme: 'snow',
                    placeholder: "What's this contest about?",
                    modules: { toolbar: TB }
                });
                window._qlDesc.root.innerHTML = <?php echo json_encode($description); ?>;

                window._qlTerms = new Quill('#terms_editor', {
                    theme: 'snow',
                    placeholder: 'List contest rules and requirements...',
                    modules: { toolbar: TB }
                });
                window._qlTerms.root.innerHTML = <?php echo json_encode($terms_conditions); ?>;

                // Sync to hidden inputs on change
                window._qlDesc.on('text-change',  () => { document.getElementById('description_h').value      = window._qlDesc.root.innerHTML; });
                window._qlTerms.on('text-change', () => { document.getElementById('terms_conditions_h').value = window._qlTerms.root.innerHTML; });
            })();
            </script>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
