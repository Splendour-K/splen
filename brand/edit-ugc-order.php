<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/wallet_functions.php';
require_role('brand');

$stmt = $pdo->prepare("SELECT * FROM brands WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$brand = $stmt->fetch();
require_brand_record($brand);

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header("Location: " . APP_URL . "brand/ugc-orders.php"); exit(); }

// Load order — must belong to this brand
$stmt = $pdo->prepare("SELECT * FROM ugc_orders WHERE id = ? AND brand_id = ?");
$stmt->execute([$id, $brand['id']]);
$order = $stmt->fetch();
if (!$order) { header("Location: " . APP_URL . "brand/ugc-orders.php"); exit(); }

// Load wallet balance
$wallet           = get_brand_wallet($brand['id']);
$wallet_available = (float)($wallet['available_balance'] ?? 0);
$wallet_currency  = $wallet['currency'] ?? 'GHS';

$error   = '';
$success = '';

// Preserve POST values on error
$title              = $_POST['title']               ?? $order['title'];
$product_name       = $_POST['product_name']        ?? $order['product_name'];
$full_description   = $_POST['description']         ?? $order['full_description'];
$budget_per_creator = $_POST['budget_per_creator']  ?? $order['budget_per_creator'];
$creator_count      = $_POST['creator_count']       ?? $order['creator_count'];
$currency           = $_POST['currency']            ?? $order['currency'];
$deadline           = $_POST['deadline']            ?? $order['deadline'];
$usage_rights_pkg   = $_POST['usage_rights']        ?? $order['usage_rights_package'];
$revision_limit     = $_POST['revision_limit']      ?? $order['revision_limit'];
$status_val         = $_POST['status']              ?? $order['status'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_budget = (float)$budget_per_creator;
    $new_count  = max(1, (int)$creator_count);
    $new_total  = $new_budget * $new_count;

    $original_total = (float)$order['budget_per_creator'] * (int)$order['creator_count'];
    $budget_diff    = $new_total - $original_total;

    // Basic validation
    if (!$title || !$product_name || !$budget_per_creator || !$deadline) {
        $error = "Title, Product Name, Budget, and Deadline are required.";
    } elseif (strlen(trim($title)) < 3) {
        $error = "Title must be at least 3 characters.";
    } elseif ($new_budget <= 0) {
        $error = "Budget per video must be greater than 0.";
    } else {
        // If budget increased, check wallet for the difference
        if ($budget_diff > 0.01) {
            if (strtoupper($wallet_currency) !== strtoupper($currency)) {
                $error = "Your wallet currency ({$wallet_currency}) doesn't match the order currency ({$currency}). Please contact admin.";
            } elseif ($wallet_available < $budget_diff) {
                $shortfall = $budget_diff - $wallet_available;
                $error = "Insufficient wallet balance to increase the budget by " . format_money($budget_diff, $currency) . ". "
                       . "Available: " . format_money($wallet_available, $wallet_currency) . ". "
                       . "You need " . format_money($shortfall, $wallet_currency) . " more. Please top up your wallet.";
            }
        }
    }

    if (!$error) {
        try {
            $pdo->prepare("
                UPDATE ugc_orders
                SET title=?, product_name=?, full_description=?, budget_per_creator=?,
                    creator_count=?, currency=?, deadline=?, usage_rights_package=?,
                    revision_limit=?, status=?
                WHERE id=? AND brand_id=?
            ")->execute([
                trim($title), trim($product_name), $full_description,
                $new_budget, $new_count, $currency,
                $deadline, $usage_rights_pkg, (int)$revision_limit,
                $status_val, $id, $brand['id'],
            ]);

            // Reserve extra budget from wallet if total increased
            if ($budget_diff > 0.01) {
                reserve_wallet_budget(
                    $brand['id'], $budget_diff, 'ugc_order_reserve', 'ugc_order', $id,
                    "Budget increase for UGC order #{$id}: +" . format_money($budget_diff, $currency)
                );
            }

            $success = "UGC Order updated successfully!";

            // Reload order data
            $stmt = $pdo->prepare("SELECT * FROM ugc_orders WHERE id = ?");
            $stmt->execute([$id]);
            $order = $stmt->fetch();

            // Refresh display values
            $title              = $order['title'];
            $product_name       = $order['product_name'];
            $full_description   = $order['full_description'];
            $budget_per_creator = $order['budget_per_creator'];
            $creator_count      = $order['creator_count'];
            $currency           = $order['currency'];
            $deadline           = $order['deadline'];
            $usage_rights_pkg   = $order['usage_rights_package'];
            $revision_limit     = $order['revision_limit'];
            $status_val         = $order['status'];

            // Refresh wallet
            $wallet           = get_brand_wallet($brand['id']);
            $wallet_available = (float)($wallet['available_balance'] ?? 0);

        } catch (Exception $e) {
            $error = "Update failed: " . $e->getMessage();
        }
    }
}

$original_total = (float)$order['budget_per_creator'] * (int)$order['creator_count'];

include '../includes/header.php';
?>

<div class="pt-24 min-h-screen bg-gray-50 dark:bg-gray-950">
    <div class="max-w-7xl mx-auto px-6 md:px-12 flex flex-col md:flex-row gap-8 py-8">
        <?php include '../includes/brand_sidebar.php'; ?>

        <main class="flex-1 space-y-8">
            <!-- Page Header -->
            <header class="p-8 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm relative overflow-hidden">
                <div aria-hidden="true" class="absolute top-0 right-0 -mr-16 -mt-16 w-64 h-64 bg-secondary/5 rounded-full blur-3xl"></div>
                <div class="relative flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <a href="<?php echo APP_URL; ?>brand/ugc-orders.php" class="text-sm font-bold text-gray-400 hover:text-secondary transition mb-3 inline-flex items-center gap-1">← My UGC Orders</a>
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Edit UGC Order</h2>
                        <p class="text-gray-500 mt-1">Editing: <span class="font-bold text-secondary"><?php echo e($order['title']); ?></span></p>
                    </div>
                    <div class="flex flex-col items-end gap-1 text-right">
                        <span class="px-3 py-1.5 bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 rounded-full text-[10px] font-black uppercase"><?php echo ucfirst($order['status']); ?></span>
                        <span class="text-xs text-gray-400">Wallet: <strong class="text-green-600"><?php echo format_money($wallet_available, $wallet_currency); ?></strong> available</span>
                    </div>
                </div>
            </header>

            <?php if ($success): ?>
                <div class="p-6 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-2xl text-green-800 dark:text-green-400 font-bold flex items-center gap-3">
                    <span class="text-xl">✅</span> <?php echo e($success); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="p-6 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-2xl text-red-800 dark:text-red-400 font-bold flex items-center gap-3">
                    <span class="text-xl">⚠️</span> <?php echo e($error); ?>
                </div>
            <?php endif; ?>

            <div class="p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-2xl text-blue-800 dark:text-blue-300 text-sm font-medium">
                ℹ️ Editing this order will <strong>not</strong> affect existing approved submissions. Increasing the budget will reserve the extra amount from your wallet.
            </div>

            <form method="POST" class="space-y-8">

                <!-- Section 1: Order Details -->
                <section class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm space-y-6">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
                        <span class="w-8 h-8 rounded-lg bg-secondary text-white flex items-center justify-center font-black text-sm">1</span>
                        Order Details
                    </h3>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Order Title <span class="text-red-500">*</span></label>
                        <input type="text" name="title" value="<?php echo e($title); ?>" required minlength="3" maxlength="120"
                               class="w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Product Name <span class="text-red-500">*</span></label>
                        <input type="text" name="product_name" value="<?php echo e($product_name); ?>" required minlength="2" maxlength="120"
                               class="w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Description</label>
                        <textarea name="description" rows="5" maxlength="2000"
                                  class="w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary"><?php echo e($full_description); ?></textarea>
                        <p class="text-xs text-gray-400 mt-1">Up to 2,000 characters.</p>
                    </div>
                </section>

                <!-- Section 2: Budget & Deliverables -->
                <section class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm space-y-6">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
                        <span class="w-8 h-8 rounded-lg bg-secondary text-white flex items-center justify-center font-black text-sm">2</span>
                        Budget & Deliverables
                    </h3>

                    <!-- Wallet info -->
                    <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-xl flex flex-wrap gap-6 text-sm">
                        <div>
                            <p class="text-[10px] font-black uppercase text-gray-400 tracking-widest">Current Reserved Budget</p>
                            <p class="font-black text-gray-900 dark:text-white"><?php echo format_money($original_total, $order['currency']); ?></p>
                            <p class="text-xs text-gray-500"><?php echo format_money((float)$order['budget_per_creator'], $order['currency']); ?>/video × <?php echo (int)$order['creator_count']; ?> creator(s)</p>
                        </div>
                        <div class="w-px bg-gray-200 dark:bg-gray-700 hidden md:block"></div>
                        <div>
                            <p class="text-[10px] font-black uppercase text-gray-400 tracking-widest">Wallet Available</p>
                            <p class="font-black text-green-600"><?php echo format_money($wallet_available, $wallet_currency); ?></p>
                        </div>
                        <div class="w-px bg-gray-200 dark:bg-gray-700 hidden md:block"></div>
                        <div>
                            <p class="text-[10px] font-black uppercase text-gray-400 tracking-widest">New Total (Live Preview)</p>
                            <p class="font-black text-secondary" id="budget-total-preview"><?php echo format_money($original_total, $order['currency']); ?></p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Payment Per Video <span class="text-red-500">*</span></label>
                            <input type="number" name="budget_per_creator" id="f-budget" step="0.01" min="0.01"
                                   value="<?php echo (float)$budget_per_creator; ?>"
                                   class="w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary"
                                   oninput="updateBudgetPreview()">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Number of Creators</label>
                            <input type="number" name="creator_count" id="f-count" min="1" max="100"
                                   value="<?php echo max(1, (int)$creator_count); ?>"
                                   class="w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary"
                                   oninput="updateBudgetPreview()">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Currency</label>
                            <select name="currency" id="f-currency" class="w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary" onchange="updateBudgetPreview()">
                                <?php foreach (["USD","NGN","GHS","EUR","GBP"] as $c): ?>
                                    <option value="<?php echo $c; ?>" <?php echo $currency === $c ? 'selected' : ''; ?>><?php echo $c; ?> (<?php echo get_currency_symbol($c); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Revision Limit</label>
                            <input type="number" name="revision_limit" min="0" max="5" value="<?php echo (int)$revision_limit; ?>"
                                   class="w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary">
                            <p class="text-xs text-gray-400 mt-1">0 = no revisions, 5 = generous.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Usage Rights</label>
                            <select name="usage_rights" class="w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary">
                                <option value="basic" <?php echo $usage_rights_pkg === 'basic' ? 'selected' : ''; ?>>Basic (Single Use)</option>
                                <option value="ad"    <?php echo $usage_rights_pkg === 'ad'    ? 'selected' : ''; ?>>Ad Rights (Paid Ads)</option>
                                <option value="full"  <?php echo $usage_rights_pkg === 'full'  ? 'selected' : ''; ?>>Full Rights (Commercial)</option>
                            </select>
                        </div>
                    </div>
                </section>

                <!-- Section 3: Timeline & Status -->
                <section class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm space-y-6">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
                        <span class="w-8 h-8 rounded-lg bg-secondary text-white flex items-center justify-center font-black text-sm">3</span>
                        Timeline & Status
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Submission Deadline <span class="text-red-500">*</span></label>
                            <input type="date" name="deadline" value="<?php echo e(is_string($deadline) ? substr($deadline, 0, 10) : date('Y-m-d', strtotime($deadline))); ?>"
                                   required class="w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Order Status</label>
                            <select name="status" class="w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary">
                                <option value="published"  <?php echo $status_val === 'published'  ? 'selected' : ''; ?>>Active / Published</option>
                                <option value="paused"     <?php echo $status_val === 'paused'     ? 'selected' : ''; ?>>Paused</option>
                                <option value="closed"     <?php echo $status_val === 'closed'     ? 'selected' : ''; ?>>Closed</option>
                                <option value="completed"  <?php echo $status_val === 'completed'  ? 'selected' : ''; ?>>Completed</option>
                            </select>
                        </div>
                    </div>
                </section>

                <div class="flex gap-4">
                    <button type="submit" class="flex-1 py-4 bg-secondary text-white font-bold rounded-xl hover:scale-105 transition shadow-lg shadow-secondary/20 text-lg">
                        Save Changes
                    </button>
                    <a href="<?php echo APP_URL; ?>brand/ugc-orders.php" class="px-8 py-4 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-bold rounded-xl text-center hover:scale-105 transition">
                        Cancel
                    </a>
                </div>
            </form>
        </main>
    </div>
</div>

<script>
(function() {
    const CCY_SYMBOLS = <?php echo json_encode([
        'USD' => '$', 'NGN' => '₦', 'GHS' => '₵', 'EUR' => '€', 'GBP' => '£'
    ]); ?>;
    const ORIGINAL_TOTAL  = <?php echo $original_total; ?>;
    const WALLET_AVAIL    = <?php echo $wallet_available; ?>;

    window.updateBudgetPreview = function() {
        const budget  = parseFloat(document.getElementById('f-budget').value) || 0;
        const count   = parseInt(document.getElementById('f-count').value)    || 1;
        const ccy     = document.getElementById('f-currency').value;
        const sym     = CCY_SYMBOLS[ccy] || '';
        const total   = budget * count;
        const diff    = total - ORIGINAL_TOTAL;

        const el = document.getElementById('budget-total-preview');
        el.textContent = sym + total.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});

        if (diff > 0.01 && diff > WALLET_AVAIL + 0.01) {
            el.className = 'font-black text-red-500';
        } else if (diff > 0.01) {
            el.className = 'font-black text-secondary';
        } else {
            el.className = 'font-black text-secondary';
        }
    };

    updateBudgetPreview();
})();
</script>

<?php include '../includes/footer.php'; ?>
