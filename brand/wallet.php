<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/wallet_functions.php';
require_role('brand');

$stmt = $pdo->prepare("SELECT * FROM brands WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$brand = $stmt->fetch();
require_brand_record($brand);

$wallet  = get_brand_wallet($brand['id']);
$success = '';
$error   = '';

// ── Pagination ────────────────────────────────────────────────
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 25;
$offset   = ($page - 1) * $per_page;

$transactions = get_wallet_transactions($wallet['id'], $per_page, $offset);

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM wallet_transactions WHERE wallet_id = ?");
$count_stmt->execute([$wallet['id']]);
$total_txns  = (int)$count_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_txns / $per_page));

// ── Derived values ────────────────────────────────────────────
$available = (float)$wallet['available_balance'];
$reserved  = (float)$wallet['reserved_balance'];
$spent     = (float)$wallet['total_spent'];
$currency  = $wallet['currency'];
$is_frozen = $wallet['status'] !== 'active';
$is_low    = $available <= 0 && !$is_frozen;

include '../includes/header.php';
?>

<div class="pt-24 min-h-screen bg-gray-50 dark:bg-gray-950">
    <div class="max-w-7xl mx-auto px-6 md:px-12 flex flex-col md:flex-row gap-8 py-8">
        <?php include '../includes/brand_sidebar.php'; ?>

        <main class="flex-1 space-y-8">

            <!-- Header -->
            <header class="p-8 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm relative overflow-hidden">
                <div aria-hidden="true" class="absolute top-0 right-0 -mr-16 -mt-16 w-64 h-64 bg-secondary/5 rounded-full blur-3xl"></div>
                <div class="relative">
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Brand Wallet</p>
                    <h2 class="text-3xl font-bold text-gray-900 dark:text-white mt-1">My Wallet</h2>
                    <p class="text-gray-600 dark:text-gray-400 mt-2">View your balance, track spending, and manage campaign budgets.</p>
                </div>
            </header>

            <!-- Status Alerts -->
            <?php if ($is_frozen): ?>
                <div class="p-5 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-900 rounded-2xl flex items-start gap-4">
                    <span class="text-2xl">🔒</span>
                    <div>
                        <p class="font-black text-red-800 dark:text-red-300">Wallet <?php echo ucfirst($wallet['status']); ?></p>
                        <p class="text-sm text-red-700 dark:text-red-400 mt-1">Your wallet is not active. You cannot publish new campaigns until admin reactivates it. Please contact support.</p>
                    </div>
                </div>
            <?php elseif ($is_low): ?>
                <div class="p-5 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-2xl flex items-start gap-4">
                    <span class="text-2xl">⚠️</span>
                    <div>
                        <p class="font-black text-yellow-800 dark:text-yellow-300">Wallet Balance is Empty</p>
                        <p class="text-sm text-yellow-700 dark:text-yellow-400 mt-1">Your wallet balance is zero. You will not be able to publish new campaigns until your wallet is funded. Please request funding below.</p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Balance Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <!-- Available Balance -->
                <div class="p-8 bg-gray-900 rounded-[2.5rem] text-white relative overflow-hidden">
                    <div class="absolute -bottom-8 -right-8 w-32 h-32 bg-secondary/20 rounded-full blur-3xl"></div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Available Balance</p>
                    <p class="text-4xl font-black mt-2"><?php echo format_money($available, $currency); ?></p>
                    <p class="text-xs text-gray-500 mt-3 font-medium"><?php echo $currency; ?> · <?php echo ucfirst($wallet['status']); ?></p>
                </div>

                <!-- Reserved -->
                <div class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Reserved</p>
                    <p class="text-4xl font-black text-gray-900 dark:text-white mt-2"><?php echo format_money($reserved, $currency); ?></p>
                    <p class="text-xs text-gray-500 mt-3 font-medium">Locked for active campaigns</p>
                </div>

                <!-- Total Spent -->
                <div class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Total Spent</p>
                    <p class="text-4xl font-black text-secondary mt-2"><?php echo format_money($spent, $currency); ?></p>
                    <p class="text-xs text-gray-500 mt-3 font-medium">Paid out to creators</p>
                </div>
            </div>

            <!-- Wallet Actions -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Request Funding -->
                <div class="p-6 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 bg-primary/10 rounded-2xl flex items-center justify-center text-xl flex-shrink-0">💰</div>
                        <div class="flex-1">
                            <h3 class="font-black text-gray-900 dark:text-white">Request Wallet Funding</h3>
                            <p class="text-sm text-gray-500 mt-1">Need to top up your wallet to publish campaigns?</p>
                            <div class="mt-4 p-4 bg-primary/5 border border-primary/10 rounded-xl">
                                <p class="text-sm font-bold text-primary">📞 Contact Admin</p>
                                <p class="text-xs text-gray-600 dark:text-gray-400 mt-1 leading-relaxed">
                                    Please contact admin to fund your wallet. Online payment will be available soon.<br><br>
                                    Email: <a href="mailto:founders@splennet.com" class="text-primary font-bold hover:underline">founders@splennet.com</a>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Wallet Info -->
                <div class="p-6 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 bg-secondary/10 rounded-2xl flex items-center justify-center text-xl flex-shrink-0">ℹ️</div>
                        <div>
                            <h3 class="font-black text-gray-900 dark:text-white">How It Works</h3>
                            <ul class="mt-3 space-y-2 text-xs text-gray-500 leading-relaxed">
                                <li class="flex items-start gap-2"><span class="text-secondary font-black">1.</span> Admin funds your wallet before you publish.</li>
                                <li class="flex items-start gap-2"><span class="text-secondary font-black">2.</span> Budget is reserved when your campaign goes live.</li>
                                <li class="flex items-start gap-2"><span class="text-secondary font-black">3.</span> Reserved funds are paid out to creators upon delivery.</li>
                                <li class="flex items-start gap-2"><span class="text-secondary font-black">4.</span> Unused reserved funds are returned if a campaign closes early.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Transaction History -->
            <section class="bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm overflow-hidden">
                <div class="p-8 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white">Transaction History</h3>
                        <p class="text-xs text-gray-500 mt-1"><?php echo number_format($total_txns); ?> transaction<?php echo $total_txns !== 1 ? 's' : ''; ?> total</p>
                    </div>
                </div>

                <?php if (empty($transactions)): ?>
                    <div class="p-12 text-center">
                        <p class="text-4xl mb-4">💳</p>
                        <p class="text-gray-400 text-sm">No transactions yet. Transactions will appear here after your wallet is funded.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-gray-50 dark:bg-gray-800/50">
                                    <th class="text-left px-6 py-4 text-[10px] font-black uppercase tracking-widest text-gray-400">Date</th>
                                    <th class="text-left px-6 py-4 text-[10px] font-black uppercase tracking-widest text-gray-400">Type</th>
                                    <th class="text-right px-6 py-4 text-[10px] font-black uppercase tracking-widest text-gray-400">Amount</th>
                                    <th class="text-right px-6 py-4 text-[10px] font-black uppercase tracking-widest text-gray-400">Balance After</th>
                                    <th class="text-left px-6 py-4 text-[10px] font-black uppercase tracking-widest text-gray-400">Description</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                <?php foreach ($transactions as $tx): ?>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/30 transition">
                                        <td class="px-6 py-4 text-xs text-gray-500 whitespace-nowrap"><?php echo date('M d, Y H:i', strtotime($tx['created_at'])); ?></td>
                                        <td class="px-6 py-4">
                                            <span class="text-xs font-bold text-gray-700 dark:text-gray-300"><?php echo format_tx_type($tx['transaction_type']); ?></span>
                                        </td>
                                        <td class="px-6 py-4 text-right font-black whitespace-nowrap <?php echo tx_amount_class($tx['transaction_type']); ?>">
                                            <?php echo tx_sign($tx['transaction_type']); ?><?php echo format_money($tx['amount'], $tx['currency']); ?>
                                        </td>
                                        <td class="px-6 py-4 text-right text-xs font-bold text-gray-900 dark:text-white whitespace-nowrap">
                                            <?php echo format_money($tx['balance_after'], $tx['currency']); ?>
                                        </td>
                                        <td class="px-6 py-4 text-xs text-gray-500 max-w-xs truncate">
                                            <?php echo e($tx['description'] ?: '—'); ?>
                                            <?php if ($tx['reference_type'] && $tx['reference_id']): ?>
                                                <span class="ml-2 px-2 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-[10px] font-bold text-gray-500">
                                                    <?php echo ucfirst($tx['reference_type']); ?> #<?php echo $tx['reference_id']; ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($total_pages > 1): ?>
                        <div class="px-8 py-6 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between">
                            <p class="text-xs text-gray-500">Page <?php echo $page; ?> of <?php echo $total_pages; ?></p>
                            <div class="flex gap-2">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo $page - 1; ?>" class="px-4 py-2 rounded-xl text-sm font-bold bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 transition">← Prev</a>
                                <?php endif; ?>
                                <?php if ($page < $total_pages): ?>
                                    <a href="?page=<?php echo $page + 1; ?>" class="px-4 py-2 rounded-xl text-sm font-bold bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 transition">Next →</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>

        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
