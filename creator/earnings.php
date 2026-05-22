<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_role('creator');

// Fetch Creator data
$stmt = $pdo->prepare("SELECT id, country FROM creators WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$creator = $stmt->fetch();
require_creator_record($creator);
$creator_id = $creator['id'];

// Display currency: ?currency=… on the URL, otherwise default by country
$default_currency = 'USD';
if (!empty($creator['country'])) {
    $country = strtolower($creator['country']);
    if (str_contains($country, 'nigeria')) $default_currency = 'NGN';
    elseif (str_contains($country, 'ghana')) $default_currency = 'GHS';
}
$display_currency = strtoupper($_GET['currency'] ?? $default_currency);
if (!in_array($display_currency, supported_currencies())) {
    $display_currency = $default_currency;
}

$commission_rate = (int)get_setting("platform_commission", 10);
$net_multiplier = (1 - ($commission_rate / 100));

// Fetch all payments (every payment carries its own currency)
$stmt = $pdo->prepare("
    SELECT p.*,
        c.title AS campaign_title,
        b.brand_name
    FROM payments p
    LEFT JOIN jobs j ON p.job_id = j.id
    LEFT JOIN campaigns c ON j.campaign_id = c.id
    LEFT JOIN brands b ON j.brand_id = b.id
    WHERE p.creator_id = ?
    ORDER BY p.created_at DESC
");
$stmt->execute([$creator_id]);
$payments = $stmt->fetchAll();

// Aggregate: per-original-currency AND converted-to-display
$by_currency = [];          // ['NGN' => ['paid' => x, 'pending' => y], ...]
$total_paid_display = 0.0;
$pending_payout_display = 0.0;

foreach ($payments as $pay) {
    $ccy = strtoupper($pay['currency'] ?: 'USD');
    $amt = (float)$pay['amount'];

    if (!isset($by_currency[$ccy])) {
        $by_currency[$ccy] = ['paid' => 0.0, 'pending' => 0.0];
    }

    if ($pay['status'] === 'completed') {
        $by_currency[$ccy]['paid'] += $amt;
        $total_paid_display += convert_currency($amt, $ccy, $display_currency);
    } elseif ($pay['status'] === 'pending') {
        $by_currency[$ccy]['pending'] += $amt;
        $pending_payout_display += convert_currency($amt, $ccy, $display_currency);
    }
}

$total_earned_net_display = ($total_paid_display + $pending_payout_display) * $net_multiplier;
$available_payout_net_display = $pending_payout_display * $net_multiplier;

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
                <div class="relative flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Earnings & Payouts</h2>
                        <p class="text-gray-600 dark:text-gray-400 mt-2">Manage your revenue and withdrawal history.</p>
                    </div>

                    <!-- Display currency picker -->
                    <form method="GET" class="flex items-center gap-2">
                        <label class="text-[10px] font-black uppercase tracking-widest text-gray-400">Show in</label>
                        <select name="currency" onchange="this.form.submit()" class="px-4 py-2 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl font-bold text-sm focus:border-primary focus:outline-none dark:text-white">
                            <?php foreach (supported_currencies() as $ccy): ?>
                                <option value="<?php echo $ccy; ?>" <?php echo $ccy === $display_currency ? 'selected' : ''; ?>>
                                    <?php echo $ccy; ?> (<?php echo get_currency_symbol($ccy); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
            </header>

            <!-- Earnings Stats (in chosen display currency) -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="p-8 bg-primary rounded-[2rem] text-white shadow-xl shadow-primary/20 relative overflow-hidden">
                    <div class="absolute top-0 right-0 -mr-8 -mt-8 w-32 h-32 bg-white/10 rounded-full blur-2xl"></div>
                    <p class="text-white/70 font-bold uppercase tracking-wider text-xs">Total Net Earned</p>
                    <h2 class="text-5xl font-black mt-2"><?php echo format_money($total_earned_net_display, $display_currency); ?></h2>
                    <p class="text-sm mt-4 text-white/60 italic">After <?php echo $commission_rate; ?>% platform fee · converted to <?php echo $display_currency; ?></p>
                </div>
                <div class="p-8 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm flex flex-col justify-between">
                    <div>
                        <p class="text-gray-500 font-bold uppercase tracking-wider text-xs">Available for Payout (Net)</p>
                        <h2 class="text-5xl font-black text-gray-900 dark:text-white mt-2"><?php echo format_money($available_payout_net_display, $display_currency); ?></h2>
                    </div>
                    <?php if ($available_payout_net_display > 0): ?>
                        <button class="mt-6 w-full py-4 bg-gray-900 dark:bg-white text-white dark:text-gray-900 font-bold rounded-2xl hover:bg-primary hover:text-white transition-all shadow-lg">Request Payout</button>
                    <?php else: ?>
                        <button disabled class="mt-6 w-full py-4 bg-gray-100 dark:bg-gray-800 text-gray-400 font-bold rounded-2xl cursor-not-allowed">No Balance</button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Per-original-currency breakdown -->
            <?php if (!empty($by_currency)): ?>
                <section class="p-8 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Earnings by Original Currency</h3>
                    <p class="text-xs text-gray-500 mb-6">Brands pay in different currencies. This shows what you've actually earned in each.</p>
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
                        <?php foreach ($by_currency as $ccy => $totals): ?>
                            <?php $total = $totals['paid'] + $totals['pending']; ?>
                            <div class="p-5 rounded-2xl bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-gray-700">
                                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1"><?php echo $ccy; ?></p>
                                <p class="text-2xl font-black text-gray-900 dark:text-white"><?php echo format_money($total, $ccy); ?></p>
                                <?php if ($totals['pending'] > 0): ?>
                                    <p class="text-[10px] text-orange-600 font-bold mt-1"><?php echo format_money($totals['pending'], $ccy); ?> pending</p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <!-- Transaction History -->
            <section class="p-8 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-8">Transaction History</h3>

                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b border-gray-50 dark:border-gray-800">
                                <th class="pb-4 text-xs font-bold uppercase tracking-widest text-gray-400">Project / Source</th>
                                <th class="pb-4 text-xs font-bold uppercase tracking-widest text-gray-400">Date</th>
                                <th class="pb-4 text-xs font-bold uppercase tracking-widest text-gray-400">Paid In</th>
                                <th class="pb-4 text-xs font-bold uppercase tracking-widest text-gray-400">Fee (<?php echo $commission_rate; ?>%)</th>
                                <th class="pb-4 text-xs font-bold uppercase tracking-widest text-gray-400">Net (Original)</th>
                                <th class="pb-4 text-xs font-bold uppercase tracking-widest text-gray-400">Net (<?php echo $display_currency; ?>)</th>
                                <th class="pb-4 text-xs font-bold uppercase tracking-widest text-gray-400">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-800">
                            <?php foreach ($payments as $pay):
                                $ccy = strtoupper($pay['currency'] ?: 'USD');
                                $gross = (float)$pay['amount'];
                                $commission = $gross * ($commission_rate / 100);
                                $net = $gross - $commission;
                                $net_in_display = convert_currency($net, $ccy, $display_currency);
                            ?>
                                <tr class="group hover:bg-gray-50/50 dark:hover:bg-gray-800/50 transition">
                                    <td class="py-6">
                                        <p class="text-sm font-bold text-gray-900 dark:text-white"><?php echo e($pay['campaign_title'] ?: 'Splennet Payment'); ?></p>
                                        <p class="text-xs text-gray-500"><?php echo e($pay['brand_name'] ?: ucfirst(str_replace('_', ' ', $pay['payment_type'] ?? 'payout'))); ?></p>
                                    </td>
                                    <td class="py-6 text-sm text-gray-600 dark:text-gray-400 font-medium whitespace-nowrap">
                                        <?php echo date('M d, Y', strtotime($pay['created_at'])); ?>
                                    </td>
                                    <td class="py-6">
                                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-300"><?php echo format_money($gross, $ccy); ?></span>
                                        <span class="block text-[10px] text-gray-400 font-bold uppercase tracking-widest"><?php echo $ccy; ?></span>
                                    </td>
                                    <td class="py-6">
                                        <span class="text-sm font-semibold text-red-500/80">-<?php echo format_money($commission, $ccy); ?></span>
                                    </td>
                                    <td class="py-6">
                                        <span class="text-sm font-black text-gray-900 dark:text-white"><?php echo format_money($net, $ccy); ?></span>
                                    </td>
                                    <td class="py-6">
                                        <?php if ($ccy === $display_currency): ?>
                                            <span class="text-sm text-gray-400 italic">—</span>
                                        <?php else: ?>
                                            <span class="text-sm font-black text-primary"><?php echo format_money($net_in_display, $display_currency); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-6">
                                        <?php
                                            $st = $pay['status'];
                                            $class = ($st === 'completed') ? 'bg-green-100 text-green-700 dark:bg-green-900/30' : 'bg-orange-100 text-orange-700 dark:bg-orange-900/30';
                                        ?>
                                        <span class="px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest <?php echo $class; ?>">
                                            <?php echo e($st); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (empty($payments)): ?>
                    <div class="py-12 text-center">
                        <p class="text-gray-500 font-medium">No transactions recorded yet.</p>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
</div>

<?php
include '../includes/footer.php';
?>
