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
            // ── Wallet check ─────────────────────────────────────
            $creator_count_num  = max(1, (int)$creator_count);
            $required_budget    = $budget_num * $creator_count_num;
            $wallet_check       = check_wallet_for_publish($brand['id'], $required_budget, $currency);

            if (!$wallet_check['ok']) {
                $error = wallet_error_message($wallet_check);
            } else {
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

                    // Reserve wallet budget
                    reserve_wallet_budget(
                        $brand['id'], $required_budget, 'campaign_reserve', 'campaign', $campaign_id,
                        "Campaign reserved: {$title} ({$creator_count_num} creator(s) × " . format_money($budget_num, $currency) . ")"
                    );

                    $creator_stmt = $pdo->query("SELECT user_id FROM creators");
                    $creator_user_ids = $creator_stmt->fetchAll(PDO::FETCH_COLUMN);

                    create_notification_batch(
                        $creator_user_ids,
                        'New Campaign Brief',
                        $brand['brand_name'] . ' published a new campaign: ' . $title,
                        'campaign_published',
                        'creator/campaign-view.php?id=' . $campaign_id,
                        'campaign',
                        $campaign_id
                    );
                    $success = "Campaign created successfully! " . format_money($required_budget, $currency) . " has been reserved from your wallet.";
                } catch (Exception $e) {
                    $error = "Error: " . $e->getMessage();
                }
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
            <form method="POST" class="space-y-8" id="campaign-form" novalidate>
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
                                <?php foreach (['Beauty','Fashion','Food & Drink','Tech Products','Mobile Apps','Skincare'] as $c): ?>
                                    <option value="<?php echo $c; ?>" <?php echo $category === $c ? 'selected' : ''; ?>><?php echo $c; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2 ml-1">Video Type</label>
                            <select name="video_type" class="w-full px-5 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-gray-700 rounded-2xl focus:border-secondary outline-none transition-all dark:text-white">
                                <?php foreach (['Product Review','Unboxing','Testimonial','Campus Lifestyle','App Demo'] as $vt): ?>
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
                        <textarea name="main_message" rows="4" maxlength="2000" placeholder="Describe what you want to achieve..." class="w-full px-5 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-gray-700 rounded-2xl focus:border-secondary outline-none transition-all dark:text-white"><?php echo e($main_message); ?></textarea>
                        <p class="text-xs text-gray-500 mt-1 ml-1">Optional. Up to 2,000 characters.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2 ml-1">Words to Say</label>
                            <textarea name="words_to_say" rows="3" placeholder="Key phrases the creator should mention..." class="w-full px-5 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-gray-700 rounded-2xl focus:border-secondary outline-none transition-all dark:text-white"><?php echo e($words_to_say); ?></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2 ml-1">Words to Avoid</label>
                            <textarea name="words_to_avoid" rows="3" placeholder="Forbidden phrases or competitors..." class="w-full px-5 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-gray-700 rounded-2xl focus:border-secondary outline-none transition-all dark:text-white"><?php echo e($words_to_avoid); ?></textarea>
                        </div>
                    </div>

                    <div>
                        <button type="submit" id="submit-btn" disabled class="w-full py-5 bg-secondary text-white text-lg font-black rounded-[1.5rem] shadow-xl shadow-secondary/20 hover:scale-[1.02] transition-all text-center disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:scale-100">
                            Publish Campaign Brief
                        </button>
                    </div>
                </section>
            </form>

            <script>
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
                    if (!validateAll(true)) { e.preventDefault(); }
                });

                validateAll(false);
                updateMinHint();
            })();
            </script>
            <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
