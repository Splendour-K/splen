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

// Quota Check
$quota = check_brand_quota($brand['id']);
if (!$quota['can_create']) {
    $error = "Monthly limit reached! You have used all your " . $quota['limit'] . " campaign slots for this month. Upgrade to Pro for more!";
}

// Preserve all submitted values across validation failures.
$title                    = $_POST['title']                    ?? '';
$product_name             = $_POST['product_name']             ?? '';
$category                 = $_POST['category']                 ?? 'Beauty';
$goal                     = $_POST['goal']                     ?? '';
$location_country         = $_POST['location_country']         ?? '';
$location_city            = $_POST['location_city']            ?? '';
$preferred_university     = $_POST['preferred_university']     ?? '';
$video_type               = $_POST['video_type']               ?? 'Product Review';
$video_length             = $_POST['video_length']             ?? '';
$creator_count            = $_POST['creator_count']            ?? 1;
$budget_per_creator_raw   = $_POST['budget_per_creator']       ?? '';
$currency                 = $_POST['currency']                 ?? 'USD';
$deadline                 = $_POST['deadline']                 ?? '';
$main_message             = $_POST['main_message']             ?? '';
$required_shots           = $_POST['required_shots']           ?? '';
$words_to_say             = $_POST['words_to_say']             ?? '';
$words_to_avoid           = $_POST['words_to_avoid']           ?? '';
$call_to_action           = $_POST['call_to_action']           ?? '';
$posting_required         = isset($_POST['posting_required']) ? 1 : 0;
$usage_rights_package     = $_POST['usage_rights_package']     ?? 'basic';
$product_shipping_details = $_POST['product_shipping_details'] ?? '';
$revision_limit           = $_POST['revision_limit']           ?? 1;
$reference_links_raw      = array_values(array_filter(array_map('trim', $_POST['reference_links'] ?? [])));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $quota['can_create']) {
    $budget_num = (float)$budget_per_creator_raw;

    if (!$title || !$product_name || !$budget_num || !$deadline) {
        $error = "Title, Product Name, Budget, and Deadline are required.";
    } else if (strtotime($deadline) <= strtotime('today')) {
        $error = "Submission deadline must be in the future.";
    } else {
        $validation_error = validate_minimum_payment($budget_num, $currency);
        if ($validation_error !== true) {
            $error = $validation_error;
        } else {
            $creator_count_num  = max(1, (int)$creator_count);
            $required_budget    = $budget_num * $creator_count_num;

            $sql = "INSERT INTO campaigns (
                brand_id, title, product_name, category, goal, location_country, location_city,
                preferred_university, video_type, video_length, creator_count, budget_per_creator, currency,
                deadline, main_message, required_shots, words_to_say, words_to_avoid, call_to_action,
                posting_required, usage_rights_package, product_shipping_details, revision_limit, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'published')";

            $stmt = $pdo->prepare($sql);
            try {
                $stmt->execute([
                    $brand['id'], $title, $product_name, $category, $goal, $location_country, $location_city,
                    $preferred_university, $video_type, $video_length, $creator_count_num, $budget_num, $currency,
                    $deadline, $main_message, $required_shots, $words_to_say, $words_to_avoid, $call_to_action,
                    $posting_required, $usage_rights_package, $product_shipping_details, (int)$revision_limit
                ]);
                $campaign_id = (int)$pdo->lastInsertId();

                // Save featured image (optional)
                if (!empty($_FILES['featured_image']['name']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
                    $fi_dir  = "../assets/uploads/listings/";
                    if (!is_dir($fi_dir)) mkdir($fi_dir, 0755, true);
                    $fi_ext  = strtolower(pathinfo($_FILES['featured_image']['name'], PATHINFO_EXTENSION));
                    $fi_size = $_FILES['featured_image']['size'];
                    if (in_array($fi_ext, ['jpg','jpeg','png','webp']) && $fi_size <= 5 * 1024 * 1024) {
                        $fi_name = "campaign_{$campaign_id}_" . time() . ".{$fi_ext}";
                        if (move_uploaded_file($_FILES['featured_image']['tmp_name'], $fi_dir . $fi_name)) {
                            try {
                                $pdo->prepare("UPDATE campaigns SET featured_image = ? WHERE id = ?")
                                    ->execute(["assets/uploads/listings/{$fi_name}", $campaign_id]);
                            } catch (\Exception $fi_e) {
                                error_log("featured_image update failed: " . $fi_e->getMessage());
                            }
                        }
                    }
                }

                // Save reference links (optional)
                if (!empty($reference_links_raw)) {
                    try {
                        $rl_stmt = $pdo->prepare(
                            "INSERT INTO campaign_reference_links (campaign_id, link_url, link_type) VALUES (?, ?, 'inspiration')"
                        );
                        foreach ($reference_links_raw as $rl) {
                            if (strlen($rl) >= 10) {
                                $rl_stmt->execute([$campaign_id, $rl]);
                            }
                        }
                    } catch (\Exception $rl_e) {
                        error_log('campaign_reference_links insert failed: ' . $rl_e->getMessage());
                    }
                }

                // Best-effort wallet reservation — non-blocking
                $wallet_reserved = false;
                $wc = check_wallet_for_publish($brand['id'], $required_budget, $currency);
                if ($wc['ok']) {
                    $wallet_reserved = reserve_wallet_budget(
                        $brand['id'], $required_budget, 'campaign_reserve', 'campaign', $campaign_id,
                        "Campaign reserved: {$title} ({$creator_count_num} creator(s) × " . format_money($budget_num, $currency) . ")"
                    );
                }

                try {
                    $creator_stmt = $pdo->query("SELECT user_id FROM creators WHERE user_id IS NOT NULL");
                    $creator_user_ids = $creator_stmt->fetchAll(PDO::FETCH_COLUMN);
                    if ($creator_user_ids) {
                        create_notification_batch(
                            $creator_user_ids,
                            'New Campaign Brief',
                            $brand['brand_name'] . ' published a new campaign: ' . $title,
                            'campaign_published',
                            'creator/campaign-view.php?id=' . $campaign_id,
                            'campaign',
                            $campaign_id
                        );
                    }
                } catch (Exception $notif_e) {
                    error_log('Campaign notification failed: ' . $notif_e->getMessage());
                }

                $wallet_note = $wallet_reserved
                    ? format_money($required_budget, $currency) . " has been reserved from your wallet."
                    : "Note: Wallet reservation pending — please ensure your wallet is funded with " . $currency . ".";
                $success = "Campaign created successfully! " . $wallet_note;
            } catch (Exception $e) {
                $error = "Error creating campaign: " . $e->getMessage();
            }
        }
    }
}

$mins = minimum_payments();
$min_for_current = $mins[$currency] ?? 5;

// Wallet for display
$wallet_display = get_brand_wallet($brand['id']);

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
                <div class="relative text-center md:text-left">
                    <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Create a Campaign Brief</h2>
                    <p class="text-gray-600 dark:text-gray-400 mt-2">Tell creators exactly what you need. A clear brief gets better content.</p>
                </div>
            </header>

            <!-- Wallet Balance Bar -->
            <div class="flex items-center justify-between p-4 bg-white dark:bg-gray-900 rounded-[1.5rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                <div class="flex items-center gap-4">
                    <span class="text-xl">💳</span>
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Wallet Balance (<?php echo e($wallet_display['currency']); ?>)</p>
                        <p class="text-lg font-black text-gray-900 dark:text-white"><?php echo format_money($wallet_display['available_balance'], $wallet_display['currency']); ?> available</p>
                    </div>
                </div>
                <?php if ((float)$wallet_display['available_balance'] <= 0 || $wallet_display['status'] !== 'active'): ?>
                    <a href="<?php echo APP_URL; ?>brand/wallet.php" class="px-4 py-2 bg-yellow-500 text-white text-xs font-black rounded-xl hover:bg-yellow-600 transition">Fund Wallet</a>
                <?php else: ?>
                    <a href="<?php echo APP_URL; ?>brand/wallet.php" class="px-4 py-2 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 text-xs font-bold rounded-xl hover:bg-gray-200 transition">View Wallet</a>
                <?php endif; ?>
            </div>

            <?php if ($success): ?>
                <div class="p-8 bg-green-50 dark:bg-green-950/30 border border-green-200 dark:border-green-800 rounded-[2rem] text-center shadow-sm">
                    <div class="w-20 h-20 bg-green-100 dark:bg-green-900/40 rounded-full flex items-center justify-center text-4xl mx-auto mb-6">🚀</div>
                    <h3 class="text-2xl font-bold text-green-900 dark:text-green-400 mb-2">Campaign Published!</h3>
                    <p class="text-green-700 dark:text-green-300 mb-8"><?php echo e($success); ?></p>
                    <a href="<?php echo APP_URL; ?>brand/dashboard.php" class="inline-flex h-12 items-center justify-center px-8 bg-secondary text-white font-bold rounded-full hover:scale-105 transition">Go to Dashboard</a>
                </div>
            <?php else: ?>

            <?php if ($error): ?>
                <div class="p-6 bg-red-100 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-2xl text-red-800 dark:text-red-400 font-bold whitespace-pre-line flex items-start gap-3">
                    <span class="text-xl mt-0.5">⚠️</span> <span><?php echo e($error); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($quota['can_create']): ?>
            <form method="POST" enctype="multipart/form-data" class="space-y-8" id="campaign-form" novalidate>
                <!-- Section 1: Core Details -->
                <section class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm space-y-6">
                    <div class="flex items-center gap-3 mb-6">
                        <span class="w-10 h-10 bg-secondary/10 text-secondary rounded-xl flex items-center justify-center text-lg">📁</span>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white">Basic Information</h3>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2 ml-1">Campaign Title <span class="text-red-500">*</span></label>
                            <input type="text" id="f-title" name="title" data-required value="<?php echo e($title); ?>" minlength="3" maxlength="120" placeholder="e.g. Back to School TikTok Review" class="w-full px-5 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-gray-700 rounded-2xl focus:border-secondary outline-none transition-all dark:text-white">
                            <p class="text-xs text-gray-500 mt-1 ml-1">Required. 3–120 characters.</p>
                            <p class="text-xs text-red-500 font-medium mt-1 ml-1 hidden" data-error-for="f-title"></p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2 ml-1">Product Name <span class="text-red-500">*</span></label>
                            <input type="text" id="f-product" name="product_name" data-required value="<?php echo e($product_name); ?>" minlength="2" maxlength="120" placeholder="What are you promoting?" class="w-full px-5 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-gray-700 rounded-2xl focus:border-secondary outline-none transition-all dark:text-white">
                            <p class="text-xs text-gray-500 mt-1 ml-1">Required.</p>
                            <p class="text-xs text-red-500 font-medium mt-1 ml-1 hidden" data-error-for="f-product"></p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2 ml-1">Category</label>
                            <select name="category" class="w-full px-5 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-gray-700 rounded-2xl focus:border-secondary outline-none transition-all dark:text-white">
                                <?php foreach ([
                                    'Beauty','Skincare','Fashion','Food & Drink',
                                    'Tech Products','Mobile Apps','Books & Education',
                                    'Health & Wellness','Sports & Fitness','Gaming',
                                    'Music & Entertainment','Travel','Finance & Fintech',
                                    'Home & Lifestyle','Automotive','Pets','Other',
                                ] as $c): ?>
                                    <option value="<?php echo $c; ?>" <?php echo $category === $c ? 'selected' : ''; ?>><?php echo $c; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2 ml-1">Video Type</label>
                            <select name="video_type" class="w-full px-5 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-gray-700 rounded-2xl focus:border-secondary outline-none transition-all dark:text-white">
                                <?php foreach ([
                                    'Product Review','Unboxing','Testimonial',
                                    'Campus Lifestyle','App Demo','Tutorial / How-To',
                                    'Day in the Life','Behind the Scenes',
                                    'Get Ready With Me (GRWM)','Haul',
                                    'Challenge','Skit / Comedy','Vlog',
                                    'Before & After','Q&A','Other',
                                ] as $vt): ?>
                                    <option value="<?php echo $vt; ?>" <?php echo $video_type === $vt ? 'selected' : ''; ?>><?php echo $vt; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2 ml-1">Target Country</label>
                            <input type="text" name="location_country" value="<?php echo e($location_country); ?>" placeholder="e.g. Global, Nigeria, USA" class="w-full px-5 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-gray-700 rounded-2xl focus:border-secondary outline-none transition-all dark:text-white">
                        </div>
                    </div>
                </section>

                <!-- Section 1b: Featured Image (Optional) -->
                <section class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm space-y-5">
                    <div class="flex items-center gap-3">
                        <span class="w-10 h-10 bg-blue-500/10 text-blue-500 rounded-xl flex items-center justify-center text-lg">🖼️</span>
                        <div>
                            <h3 class="text-xl font-bold text-gray-900 dark:text-white">Featured Image <span class="ml-2 text-xs font-normal text-gray-400 bg-gray-100 dark:bg-gray-800 px-2 py-0.5 rounded-full">Optional</span></h3>
                            <p class="text-xs text-gray-500 mt-0.5">Add a banner image to make your listing stand out. JPG, PNG, WEBP · max 5 MB · 16:9 recommended.</p>
                        </div>
                    </div>
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

                <!-- Section 2: Budget & Terms -->
                <section class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm space-y-6">
                    <div class="flex items-center gap-3 mb-6">
                        <span class="w-10 h-10 bg-green-500/10 text-green-500 rounded-xl flex items-center justify-center text-lg">💰</span>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white">Budget & Logistics</h3>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2 ml-1">Pay Per Creator <span class="text-red-500">*</span></label>
                            <div class="flex gap-2">
                                <select id="f-currency" name="currency" class="w-24 px-3 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-gray-700 rounded-2xl focus:border-secondary outline-none transition-all dark:text-white">
                                    <?php foreach (["USD","NGN","GHS","EUR","GBP"] as $c): ?>
                                        <option value="<?php echo $c; ?>" <?php echo $currency === $c ? 'selected' : ''; ?>><?php echo $c; ?> (<?php echo get_currency_symbol($c); ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="number" id="f-amount" name="budget_per_creator" data-required value="<?php echo e($budget_per_creator_raw); ?>" step="0.01" min="0" placeholder="50" class="flex-1 px-5 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-gray-700 rounded-2xl focus:border-secondary outline-none transition-all dark:text-white">
                            </div>
                            <p class="text-xs text-gray-500 mt-1 ml-1">Required. Minimum <span id="min-display"><?php echo number_format($min_for_current); ?></span> <span id="ccy-display"><?php echo e($currency); ?></span> per creator.</p>
                            <p class="text-xs text-red-500 font-medium mt-1 ml-1 hidden" data-error-for="f-amount"></p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2 ml-1">Creator Limit</label>
                            <input type="number" name="creator_count" value="<?php echo (int)$creator_count; ?>" min="1" max="100" class="w-full px-5 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-gray-700 rounded-2xl focus:border-secondary outline-none transition-all dark:text-white">
                            <p class="text-xs text-gray-500 mt-1 ml-1">How many creators can take this brief? 1–100.</p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2 ml-1">Submission Deadline <span class="text-red-500">*</span></label>
                            <input type="date" id="f-deadline" name="deadline" data-required value="<?php echo e($deadline); ?>" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" class="w-full px-5 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-gray-700 rounded-2xl focus:border-secondary outline-none transition-all dark:text-white">
                            <p class="text-xs text-gray-500 mt-1 ml-1">Required. Must be at least tomorrow.</p>
                            <p class="text-xs text-red-500 font-medium mt-1 ml-1 hidden" data-error-for="f-deadline"></p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2 ml-1">Usage Rights</label>
                        <select name="usage_rights_package" class="w-full px-5 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-gray-700 rounded-2xl focus:border-secondary outline-none transition-all dark:text-white">
                            <option value="basic" <?php echo $usage_rights_package === 'basic' ? 'selected' : ''; ?>>Basic (Organic social, 6 months)</option>
                            <option value="ad"    <?php echo $usage_rights_package === 'ad'    ? 'selected' : ''; ?>>Ad Rights (Social + Paid, 12 months)</option>
                            <option value="full"  <?php echo $usage_rights_package === 'full'  ? 'selected' : ''; ?>>Full Rights (Long term across all channels)</option>
                        </select>
                    </div>
                </section>

                <!-- Section 3: The Brief -->
                <section class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm space-y-6">
                    <div class="flex items-center gap-3 mb-6">
                        <span class="w-10 h-10 bg-orange-500/10 text-orange-500 rounded-xl flex items-center justify-center text-lg">📝</span>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white">Creator Brief</h3>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2 ml-1">Campaign Goal / Description</label>
                        <div class="ql-wrap" id="main_message_wrap"><div id="main_message_editor"></div></div>
                        <input type="hidden" name="main_message" id="main_message_h">
                        <p class="text-xs text-gray-500 mt-1 ml-1">Optional — supports <strong>bold</strong>, <em>italic</em>, lists &amp; more.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2 ml-1">Words to Say</label>
                            <div class="ql-wrap" id="words_to_say_wrap"><div id="words_to_say_editor"></div></div>
                            <input type="hidden" name="words_to_say" id="words_to_say_h">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2 ml-1">Words to Avoid</label>
                            <textarea name="words_to_avoid" rows="3" placeholder="Forbidden phrases or competitors..." class="w-full px-5 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-gray-700 rounded-2xl focus:border-secondary outline-none transition-all dark:text-white"><?php echo e($words_to_avoid); ?></textarea>
                        </div>
                    </div>

                </section>

                <!-- Section 4: Reference Videos (Optional) -->
                <section class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm space-y-5">
                    <div class="flex items-center gap-3">
                        <span class="w-10 h-10 bg-purple-500/10 text-purple-500 rounded-xl flex items-center justify-center text-lg">🎬</span>
                        <div>
                            <h3 class="text-xl font-bold text-gray-900 dark:text-white">Reference Videos <span class="ml-2 text-xs font-normal text-gray-400 bg-gray-100 dark:bg-gray-800 px-2 py-0.5 rounded-full">Optional</span></h3>
                            <p class="text-xs text-gray-500 mt-0.5">Share example videos so creators know the style and tone you're after.</p>
                        </div>
                    </div>

                    <div id="ref-links-container" class="space-y-3">
                        <?php if (!empty($reference_links_raw)): ?>
                            <?php foreach ($reference_links_raw as $rl): ?>
                                <div class="flex gap-2 ref-link-row">
                                    <input type="url" name="reference_links[]" value="<?php echo e($rl); ?>" placeholder="https://www.tiktok.com/@example/video/..." class="flex-1 px-5 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-gray-700 rounded-2xl focus:border-secondary outline-none transition-all dark:text-white text-sm">
                                    <button type="button" onclick="this.parentElement.remove()" class="px-4 py-3 bg-red-100 dark:bg-red-900/20 text-red-500 font-black rounded-2xl hover:bg-red-500 hover:text-white transition w-12 flex-shrink-0">×</button>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="flex gap-2 ref-link-row">
                                <input type="url" name="reference_links[]" placeholder="https://www.tiktok.com/@example/video/... (optional)" class="flex-1 px-5 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-gray-700 rounded-2xl focus:border-secondary outline-none transition-all dark:text-white text-sm">
                                <button type="button" onclick="this.parentElement.remove()" class="px-4 py-3 bg-red-100 dark:bg-red-900/20 text-red-500 font-black rounded-2xl hover:bg-red-500 hover:text-white transition w-12 flex-shrink-0">×</button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <button type="button" onclick="addRefLink()" class="flex items-center gap-2 px-5 py-3 border-2 border-dashed border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 rounded-2xl hover:border-secondary hover:text-secondary transition text-sm font-bold w-full justify-center">
                        <span class="text-lg leading-none">+</span> Add another reference link
                    </button>
                    <p class="text-xs text-gray-400 ml-1">TikTok, Instagram Reels, YouTube Shorts, or any public video URL.</p>
                </section>

                <div>
                    <button type="submit" id="submit-btn" disabled class="w-full py-5 bg-secondary text-white text-lg font-black rounded-[1.5rem] shadow-xl shadow-secondary/20 hover:scale-[1.02] transition-all text-center disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:scale-100">
                        Publish Campaign Brief
                    </button>
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
                    <input type="url" name="reference_links[]" placeholder="https://..." class="flex-1 px-5 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-gray-700 rounded-2xl focus:border-secondary outline-none transition-all dark:text-white text-sm">
                    <button type="button" onclick="this.parentElement.remove()" class="px-4 py-3 bg-red-100 dark:bg-red-900/20 text-red-500 font-black rounded-2xl hover:bg-red-500 hover:text-white transition w-12 flex-shrink-0">×</button>
                `;
                container.appendChild(row);
                row.querySelector('input').focus();
            }

            (function() {
                const MIN_BY_CCY = <?php echo json_encode(minimum_payments()); ?>;
                const form = document.getElementById('campaign-form');
                const submitBtn = document.getElementById('submit-btn');
                const f_title = document.getElementById('f-title');
                const f_product = document.getElementById('f-product');
                const f_amount = document.getElementById('f-amount');
                const f_currency = document.getElementById('f-currency');
                const f_deadline = document.getElementById('f-deadline');
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

                function validateAll(showAll) {
                    let ok = true;
                    const reasons = {};

                    if (!f_title.value.trim() || f_title.value.trim().length < 3) {
                        reasons.title = 'Title must be at least 3 characters.';
                        ok = false;
                    }
                    if (!f_product.value.trim() || f_product.value.trim().length < 2) {
                        reasons.product = 'Product name is required.';
                        ok = false;
                    }
                    const amount = parseFloat(f_amount.value);
                    const minRequired = MIN_BY_CCY[f_currency.value] ?? 5;
                    if (isNaN(amount) || amount <= 0) {
                        reasons.amount = 'Enter a payment amount.';
                        ok = false;
                    } else if (amount < minRequired) {
                        reasons.amount = `Minimum is ${minRequired.toLocaleString()} ${f_currency.value} per creator.`;
                        ok = false;
                    }
                    if (!f_deadline.value) {
                        reasons.deadline = 'Pick a submission deadline.';
                        ok = false;
                    } else {
                        const picked = new Date(f_deadline.value + 'T23:59:59');
                        if (picked <= new Date()) {
                            reasons.deadline = 'Deadline must be in the future.';
                            ok = false;
                        }
                    }

                    setError(f_title,    (showAll || f_title.dataset.touched)    ? (reasons.title || '')    : '');
                    setError(f_product,  (showAll || f_product.dataset.touched)  ? (reasons.product || '')  : '');
                    setError(f_amount,   (showAll || f_amount.dataset.touched)   ? (reasons.amount || '')   : '');
                    setError(f_deadline, (showAll || f_deadline.dataset.touched) ? (reasons.deadline || '') : '');

                    submitBtn.disabled = !ok;
                    return ok;
                }

                function updateMinHint() {
                    const ccy = f_currency.value;
                    const min = MIN_BY_CCY[ccy] ?? 5;
                    minDisplay.textContent = min.toLocaleString();
                    ccyDisplay.textContent = ccy;
                }

                [f_title, f_product, f_amount, f_deadline].forEach(el => {
                    el.addEventListener('input', () => { el.dataset.touched = '1'; validateAll(false); });
                    el.addEventListener('blur',  () => { el.dataset.touched = '1'; validateAll(false); });
                });
                f_currency.addEventListener('change', () => { updateMinHint(); validateAll(false); });

                form.addEventListener('submit', (e) => {
                    // Sync rich editor content to hidden inputs before submission
                    if (window._qlMainMsg)    document.getElementById('main_message_h').value    = window._qlMainMsg.root.innerHTML;
                    if (window._qlWordsToSay) document.getElementById('words_to_say_h').value    = window._qlWordsToSay.root.innerHTML;
                    if (!validateAll(true)) { e.preventDefault(); }
                });

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
                window._qlMainMsg = new Quill('#main_message_editor', {
                    theme: 'snow',
                    placeholder: 'Describe what you want to achieve...',
                    modules: { toolbar: TB }
                });
                window._qlMainMsg.root.innerHTML = <?php echo json_encode($main_message); ?>;

                window._qlWordsToSay = new Quill('#words_to_say_editor', {
                    theme: 'snow',
                    placeholder: 'Key phrases the creator should mention...',
                    modules: { toolbar: TB }
                });
                window._qlWordsToSay.root.innerHTML = <?php echo json_encode($words_to_say); ?>;

                // Populate hidden inputs on any change too (belt-and-suspenders)
                window._qlMainMsg.on('text-change', function() {
                    document.getElementById('main_message_h').value = window._qlMainMsg.root.innerHTML;
                });
                window._qlWordsToSay.on('text-change', function() {
                    document.getElementById('words_to_say_h').value = window._qlWordsToSay.root.innerHTML;
                });
            })();
            </script>
            <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
