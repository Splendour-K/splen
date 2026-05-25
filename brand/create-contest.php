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
// CPM fields (optional)
$cpm_enabled              = isset($_POST['cpm_enabled']);
$pay_per_1000_views_raw   = $_POST['pay_per_1000_views']       ?? '';
$max_payable_views_raw    = $_POST['max_payable_views']        ?? '';
$reference_links_raw      = array_values(array_filter(array_map('trim', $_POST['reference_links'] ?? [])));

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
            // ── Wallet check ─────────────────────────────────────
            // Contest budget = total prize pool + CPM budget (if set)
            $cpm_rate_val   = $cpm_enabled ? (float)$pay_per_1000_views_raw : 0;
            $cpm_cap_val    = $cpm_enabled ? (int)$max_payable_views_raw    : 0;
            $cpm_total      = ($cpm_rate_val > 0 && $cpm_cap_val > 0)
                              ? ($cpm_rate_val * ($cpm_cap_val / 1000) * $winners_num)
                              : 0;
            $required_budget = $budget_num + $cpm_total;
            $wallet_check    = check_wallet_for_publish($brand['id'], $required_budget, $currency);

            if (!$wallet_check['ok']) {
                $error = wallet_error_message($wallet_check);
            } else {
                $sql = "INSERT INTO contests (
                    brand_id, title, description, category, total_contest_budget, currency,
                    submission_deadline, winner_announcement_date, number_of_winners, terms_conditions, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'live')";

                $stmt = $pdo->prepare($sql);
                try {
                    $stmt->execute([
                        $brand['id'], $title, $description, $category, $budget_num, $currency,
                        $submission_deadline, $winner_announcement_date, $winners_num, $terms_conditions
                    ]);
                    $contest_id = $pdo->lastInsertId();

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

                    // Save optional CPM fields (graceful — only works after fix_contests_v2.sql is run)
                    if ($cpm_enabled && $cpm_rate_val > 0) {
                        try {
                            $pdo->prepare("UPDATE contests SET pay_per_1000_views = ?, max_payable_views_per_creator = ?, cpm_budget = ? WHERE id = ?")
                                ->execute([$cpm_rate_val, $cpm_cap_val ?: null, $cpm_total ?: null, $contest_id]);
                        } catch (Exception $cpm_e) { /* CPM columns not yet migrated — run fix_contests_v2.sql */ }
                    }

                    // Reserve wallet budget
                    $reserve_desc = "Contest reserved: {$title} (prize pool " . format_money($budget_num, $currency) .
                                    ($cpm_total > 0 ? " + CPM " . format_money($cpm_total, $currency) : '') . ")";
                    reserve_wallet_budget(
                        $brand['id'], $required_budget, 'contest_reserve', 'contest', (int)$contest_id, $reserve_desc
                    );

                    $per_winner = $budget_num / $winners_num;
                    for ($i = 1; $i <= $winners_num; $i++) {
                        if ($i === 1) {
                            $position = "Grand Prize";
                            $amount = $per_winner * 1.5;
                        } else if ($i === 2 && $winners_num >= 2) {
                            $position = "2nd Place";
                            $amount = $per_winner;
                        } else if ($i === 3 && $winners_num >= 3) {
                            $position = "3rd Place";
                            $amount = $per_winner * 0.75;
                        } else {
                            $position = "Position " . $i;
                            $amount = $per_winner;
                        }
                        $stmt = $pdo->prepare("INSERT INTO contest_rewards (contest_id, position_number, position_name, reward_amount, currency) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$contest_id, $i, $position, $amount, $currency]);
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

                    $success = "Contest created successfully! " . format_money($required_budget, $currency) . " has been reserved from your wallet.";
                } catch (Exception $e) {
                    $error = "Error: " . $e->getMessage();
                }
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
                <div class="p-6 bg-red-100 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-2xl text-red-800 dark:text-red-400 font-bold flex items-center">
                    <span class="mr-3 text-xl">⚠️</span> <?php echo e($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-8" id="contest-form" novalidate>
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
                        <textarea name="description" rows="4" maxlength="2000" class="w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary" placeholder="What's this contest about?"><?php echo e($description); ?></textarea>
                        <p class="text-xs text-gray-500 mt-1">Optional. Up to 2,000 characters.</p>
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

                <section class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm space-y-6">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
                        <span class="w-8 h-8 rounded-lg bg-secondary text-white flex items-center justify-center font-black text-sm">2</span>
                        Budget & Prizes
                    </h3>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Total Budget <span class="text-red-500">*</span></label>
                            <input type="number" name="total_contest_budget" id="f-amount" data-required value="<?php echo e($total_contest_budget_raw); ?>" step="0.01" min="0" class="w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary" placeholder="0.00">
                            <p class="text-xs text-gray-500 mt-1">Required. Minimum <span id="min-display"><?php echo number_format($min_for_current); ?></span> <span id="ccy-display"><?php echo e($currency); ?></span>. This is the total prize pool.</p>
                            <p class="text-xs text-red-500 font-medium mt-1 hidden" data-error-for="f-amount"></p>
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

                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Number of Winners <span class="text-red-500">*</span></label>
                        <input type="number" name="number_of_winners" id="f-winners" value="<?php echo (int)$number_of_winners ?: 1; ?>" min="1" max="500" class="w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary">
                        <p class="text-xs text-gray-500 mt-1">Enter any number. Budget splits evenly; Grand Prize gets 1.5×, 3rd Place gets 0.75×, rest split equally.</p>
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
                        <textarea name="terms_conditions" rows="5" maxlength="4000" class="w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary" placeholder="List contest rules and requirements..."><?php echo e($terms_conditions); ?></textarea>
                        <p class="text-xs text-gray-500 mt-1">Optional. Up to 4,000 characters.</p>
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

            (function() {
                const MIN_BY_CCY = <?php echo json_encode(minimum_payments()); ?>;
                const form = document.getElementById('contest-form');
                const submitBtn = document.getElementById('submit-btn');
                const f_title = document.getElementById('f-title');
                const f_amount = document.getElementById('f-amount');
                const f_currency = document.getElementById('f-currency');
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

                function validateAll(showAll) {
                    let ok = true;
                    const reasons = {};

                    if (!f_title.value.trim() || f_title.value.trim().length < 3) {
                        reasons.title = 'Title must be at least 3 characters.';
                        ok = false;
                    }
                    const amount = parseFloat(f_amount.value);
                    const minRequired = MIN_BY_CCY[f_currency.value] ?? 5;
                    if (isNaN(amount) || amount <= 0) {
                        reasons.amount = 'Enter a total budget.';
                        ok = false;
                    } else if (amount < minRequired) {
                        reasons.amount = `Minimum is ${minRequired.toLocaleString()} ${f_currency.value}.`;
                        ok = false;
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
                    setError(f_amount,      (showAll || f_amount.dataset.touched)      ? (reasons.amount      || '') : '');
                    setError(f_deadline,    (showAll || f_deadline.dataset.touched)    ? (reasons.deadline    || '') : '');
                    setError(f_winner_date, (showAll || f_winner_date.dataset.touched) ? (reasons.winner_date || '') : '');

                    submitBtn.disabled = !ok;
                    return ok;
                }

                function updateMinHint() {
                    const ccy = f_currency.value;
                    const min = MIN_BY_CCY[ccy] ?? 5;
                    minDisplay.textContent = min.toLocaleString();
                    ccyDisplay.textContent = ccy;
                }

                [f_title, f_amount, f_deadline, f_winner_date].forEach(el => {
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
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
