<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/wallet_functions.php';
require_role('admin');

$success = '';
$error   = '';
$view    = $_GET['view'] ?? 'list'; // 'list' | 'detail'
$wallet_id_focus = isset($_GET['wallet_id']) ? (int)$_GET['wallet_id'] : 0;
$search  = trim($_GET['search'] ?? '');

// ── POST actions ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action'] ?? '';
    $wallet_id = (int)($_POST['wallet_id'] ?? 0);
    $admin_id  = (int)$_SESSION['user_id'];

    if ($action === 'credit') {
        $amount = (float)($_POST['amount'] ?? 0);
        $desc   = trim($_POST['description'] ?? '');
        $curr   = strtoupper(trim($_POST['currency'] ?? 'GHS'));

        if ($amount <= 0) {
            $error = "Amount must be greater than zero.";
        } elseif (!$desc) {
            $error = "Please add a note for this credit.";
        } else {
            // Update wallet currency if admin changes it (only when balance is zero and no activity)
            $wstmt = $pdo->prepare("SELECT * FROM brand_wallets WHERE id = ?");
            $wstmt->execute([$wallet_id]);
            $wallet_row = $wstmt->fetch();
            if ($wallet_row && (float)$wallet_row['available_balance'] == 0 && (float)$wallet_row['reserved_balance'] == 0 && (float)$wallet_row['total_spent'] == 0) {
                $pdo->prepare("UPDATE brand_wallets SET currency = ? WHERE id = ?")->execute([$curr, $wallet_id]);
            }

            if (admin_credit_wallet($wallet_id, $amount, $admin_id, $desc)) {
                log_activity($admin_id, 'Wallet Credited', "Wallet #{$wallet_id} credited {$curr} {$amount}");
                $success = "Wallet credited successfully.";
            } else {
                $error = "Credit failed. Please try again.";
            }
        }
    }

    elseif ($action === 'debit') {
        $amount = (float)($_POST['amount'] ?? 0);
        $desc   = trim($_POST['description'] ?? '');
        $force  = isset($_POST['force_debit']);

        if ($amount <= 0) {
            $error = "Amount must be greater than zero.";
        } elseif (!$desc) {
            $error = "Please add a reason for this debit.";
        } else {
            if (admin_debit_wallet($wallet_id, $amount, $admin_id, $desc, $force)) {
                log_activity($admin_id, 'Wallet Debited', "Wallet #{$wallet_id} debited {$amount}");
                $success = "Wallet debited successfully.";
            } else {
                $error = "Debit failed. Insufficient balance (check 'Force debit' to override).";
            }
        }
    }

    elseif ($action === 'freeze') {
        set_wallet_status($wallet_id, 'frozen');
        $success = "Wallet frozen.";
        log_activity($admin_id, 'Wallet Frozen', "Wallet #{$wallet_id} frozen");
    }

    elseif ($action === 'unfreeze') {
        set_wallet_status($wallet_id, 'active');
        $success = "Wallet reactivated.";
        log_activity($admin_id, 'Wallet Unfrozen', "Wallet #{$wallet_id} reactivated");
    }

    elseif ($action === 'set_currency') {
        $curr = strtoupper(trim($_POST['currency'] ?? 'GHS'));
        $allowed_currencies = ['NGN', 'GHS', 'USD'];
        if (in_array($curr, $allowed_currencies, true)) {
            $pdo->prepare("UPDATE brand_wallets SET currency = ?, updated_at = NOW() WHERE id = ?")->execute([$curr, $wallet_id]);
            $success = "Wallet currency updated to {$curr}.";
        } else {
            $error = "Unsupported currency.";
        }
    }

    elseif ($action === 'refund') {
        $amount   = (float)($_POST['refund_amount'] ?? 0);
        $ref_type = trim($_POST['ref_type'] ?? 'manual');
        $ref_id   = (int)($_POST['ref_id'] ?? 0);
        $desc     = trim($_POST['description'] ?? 'Admin refund of unused reserved budget');

        if ($amount <= 0) {
            $error = "Refund amount must be greater than zero.";
        } else {
            if (refund_reserved_budget($wallet_id, $amount, $admin_id, $ref_type, $ref_id, $desc)) {
                $success = "Reserved budget refunded to available balance.";
                log_activity($admin_id, 'Budget Refunded', "Refunded {$amount} from reserved to available on wallet #{$wallet_id}");
            } else {
                $error = "Refund failed. Check that reserved balance is sufficient.";
            }
        }
    }

    // Stay on detail view if we had a wallet_id focus
    if ($wallet_id_focus === 0) $wallet_id_focus = $wallet_id;
    $view = 'detail';
    header("Location: brand-wallets.php?view=detail&wallet_id={$wallet_id_focus}&" . ($success ? 'msg=' . urlencode($success) : 'err=' . urlencode($error)));
    exit;
}

if (!empty($_GET['msg'])) $success = $_GET['msg'];
if (!empty($_GET['err'])) $error   = $_GET['err'];

// ── Load data ─────────────────────────────────────────────────
if ($view === 'detail' && $wallet_id_focus > 0) {
    $wstmt = $pdo->prepare("SELECT bw.*, b.brand_name, b.contact_person, u.email FROM brand_wallets bw JOIN brands b ON bw.brand_id = b.id JOIN users u ON b.user_id = u.id WHERE bw.id = ?");
    $wstmt->execute([$wallet_id_focus]);
    $focused_wallet = $wstmt->fetch();

    $txns_page  = max(1, (int)($_GET['tpage'] ?? 1));
    $txns_limit = 30;
    $txns_offset = ($txns_page - 1) * $txns_limit;
    $transactions = get_wallet_transactions($wallet_id_focus, $txns_limit, $txns_offset);

    $total_txns = (int)$pdo->prepare("SELECT COUNT(*) FROM wallet_transactions WHERE wallet_id = ?")->execute([$wallet_id_focus]) ? $pdo->query("SELECT COUNT(*) FROM wallet_transactions WHERE wallet_id = {$wallet_id_focus}")->fetchColumn() : 0;
    $total_txn_pages = max(1, (int)ceil($total_txns / $txns_limit));
}

// ── All wallets list ──────────────────────────────────────────
$list_sql = "
    SELECT bw.*, b.brand_name, b.contact_person, u.email
    FROM brand_wallets bw
    JOIN brands b ON bw.brand_id = b.id
    JOIN users u ON b.user_id = u.id
    WHERE 1=1
";
if ($search) {
    $list_sql .= " AND (b.brand_name LIKE ? OR u.email LIKE ? OR b.contact_person LIKE ?)";
}
$list_sql .= " ORDER BY bw.available_balance DESC, b.brand_name ASC";

$list_stmt = $pdo->prepare($list_sql);
if ($search) {
    $sp = '%' . $search . '%';
    $list_stmt->execute([$sp, $sp, $sp]);
} else {
    $list_stmt->execute();
}
$all_wallets = $list_stmt->fetchAll();

// Summary stats
$stats = $pdo->query("SELECT COUNT(*) as total, SUM(available_balance) as total_avail, SUM(reserved_balance) as total_reserved, SUM(total_spent) as total_spent FROM brand_wallets")->fetch();

include '../includes/header.php';
?>

<div class="pt-24 min-h-screen bg-gray-50 dark:bg-gray-950">
    <div class="max-w-7xl mx-auto px-6 md:px-12 flex flex-col md:flex-row gap-8 py-8">
        <?php include 'dashboard_sidebar.php'; ?>

        <main class="flex-1 space-y-8">

            <!-- Header -->
            <header class="p-8 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm relative overflow-hidden">
                <div aria-hidden="true" class="absolute top-0 right-0 -mr-16 -mt-16 w-64 h-64 bg-secondary/5 rounded-full blur-3xl"></div>
                <div class="relative flex flex-col md:flex-row justify-between items-start gap-4">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Admin</p>
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white mt-1">Brand Wallets</h2>
                        <p class="text-gray-600 dark:text-gray-400 mt-2">Fund, adjust, and manage all brand wallet balances.</p>
                    </div>
                    <?php if ($view === 'detail' && isset($focused_wallet)): ?>
                        <a href="brand-wallets.php" class="px-5 py-3 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 font-bold rounded-xl hover:border-secondary hover:text-secondary transition text-sm">
                            ← All Wallets
                        </a>
                    <?php endif; ?>
                </div>
            </header>

            <!-- Alerts -->
            <?php if ($success): ?>
                <div class="p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-900 rounded-2xl text-green-800 dark:text-green-300 text-sm font-bold">✓ <?php echo e($success); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-900 rounded-2xl text-red-800 dark:text-red-300 text-sm font-bold">✕ <?php echo e($error); ?></div>
            <?php endif; ?>

            <!-- ── DETAIL VIEW ──────────────────────────────────────── -->
            <?php if ($view === 'detail' && isset($focused_wallet)): ?>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-5">
                    <div class="p-6 bg-gray-900 rounded-[2rem] text-white">
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Available</p>
                        <p class="text-3xl font-black mt-2"><?php echo format_money($focused_wallet['available_balance'], $focused_wallet['currency']); ?></p>
                    </div>
                    <div class="p-6 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800">
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Reserved</p>
                        <p class="text-3xl font-black text-gray-900 dark:text-white mt-2"><?php echo format_money($focused_wallet['reserved_balance'], $focused_wallet['currency']); ?></p>
                    </div>
                    <div class="p-6 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800">
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Total Spent</p>
                        <p class="text-3xl font-black text-secondary mt-2"><?php echo format_money($focused_wallet['total_spent'], $focused_wallet['currency']); ?></p>
                    </div>
                    <div class="p-6 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800">
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Status</p>
                        <p class="text-3xl font-black text-gray-900 dark:text-white mt-2"><?php echo ucfirst($focused_wallet['status']); ?></p>
                        <p class="text-xs text-gray-500 mt-1"><?php echo $focused_wallet['currency']; ?> · <?php echo e($focused_wallet['brand_name']); ?></p>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

                    <!-- Admin Action Forms -->
                    <div class="lg:col-span-1 space-y-6">

                        <!-- Credit Wallet -->
                        <div class="p-6 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                            <h3 class="font-black text-gray-900 dark:text-white mb-5 flex items-center gap-2">
                                <span class="w-8 h-8 bg-green-100 dark:bg-green-900/30 rounded-xl flex items-center justify-center">💰</span>
                                Credit Wallet
                            </h3>
                            <form method="POST" class="space-y-4">
                                <input type="hidden" name="action" value="credit">
                                <input type="hidden" name="wallet_id" value="<?php echo $focused_wallet['id']; ?>">
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Amount</label>
                                    <input type="number" name="amount" step="0.01" min="0.01" required placeholder="0.00" class="w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white font-bold focus:outline-none focus:border-secondary">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Currency</label>
                                    <select name="currency" class="w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white font-bold focus:outline-none focus:border-secondary">
                                        <option value="GHS" <?php echo $focused_wallet['currency'] === 'GHS' ? 'selected' : ''; ?>>GHS — Ghana Cedi</option>
                                        <option value="NGN" <?php echo $focused_wallet['currency'] === 'NGN' ? 'selected' : ''; ?>>NGN — Nigerian Naira</option>
                                        <option value="USD" <?php echo $focused_wallet['currency'] === 'USD' ? 'selected' : ''; ?>>USD — US Dollar</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Note / Description</label>
                                    <input type="text" name="description" required placeholder="e.g. Manual top-up for Q1 campaign" class="w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white font-bold focus:outline-none focus:border-secondary">
                                </div>
                                <button type="submit" class="w-full px-6 py-3 bg-green-600 text-white font-black rounded-xl hover:bg-green-700 transition">
                                    ✓ Credit Wallet
                                </button>
                            </form>
                        </div>

                        <!-- Debit Wallet -->
                        <div class="p-6 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                            <h3 class="font-black text-gray-900 dark:text-white mb-5 flex items-center gap-2">
                                <span class="w-8 h-8 bg-red-100 dark:bg-red-900/30 rounded-xl flex items-center justify-center">📤</span>
                                Debit Wallet
                            </h3>
                            <form method="POST" class="space-y-4">
                                <input type="hidden" name="action" value="debit">
                                <input type="hidden" name="wallet_id" value="<?php echo $focused_wallet['id']; ?>">
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Amount</label>
                                    <input type="number" name="amount" step="0.01" min="0.01" required placeholder="0.00" class="w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white font-bold focus:outline-none focus:border-secondary">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Reason</label>
                                    <input type="text" name="description" required placeholder="e.g. Incorrect credit reversal" class="w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white font-bold focus:outline-none focus:border-secondary">
                                </div>
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox" name="force_debit" class="w-4 h-4 accent-red-600">
                                    <span class="text-xs font-bold text-red-600">Force debit (override balance check)</span>
                                </label>
                                <button type="submit" class="w-full px-6 py-3 bg-red-600 text-white font-black rounded-xl hover:bg-red-700 transition" onclick="return confirm('Debit this wallet?')">
                                    Debit Wallet
                                </button>
                            </form>
                        </div>

                        <!-- Refund Reserved Budget -->
                        <?php if ((float)$focused_wallet['reserved_balance'] > 0): ?>
                        <div class="p-6 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                            <h3 class="font-black text-gray-900 dark:text-white mb-5 flex items-center gap-2">
                                <span class="w-8 h-8 bg-blue-100 dark:bg-blue-900/30 rounded-xl flex items-center justify-center">↩️</span>
                                Refund Reserved Budget
                            </h3>
                            <p class="text-xs text-gray-500 mb-4">Return unused reserved funds to available balance when a campaign ends.</p>
                            <form method="POST" class="space-y-4">
                                <input type="hidden" name="action" value="refund">
                                <input type="hidden" name="wallet_id" value="<?php echo $focused_wallet['id']; ?>">
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Refund Amount (max: <?php echo format_money($focused_wallet['reserved_balance'], $focused_wallet['currency']); ?>)</label>
                                    <input type="number" name="refund_amount" step="0.01" min="0.01" max="<?php echo $focused_wallet['reserved_balance']; ?>" required placeholder="0.00" class="w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white font-bold focus:outline-none focus:border-secondary">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Reference Type</label>
                                    <select name="ref_type" class="w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white focus:outline-none">
                                        <option value="campaign">Campaign</option>
                                        <option value="contest">Contest</option>
                                        <option value="ugc_order">UGC Order</option>
                                        <option value="manual">Manual</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Reference ID (optional)</label>
                                    <input type="number" name="ref_id" min="0" placeholder="0" class="w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white font-bold focus:outline-none">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Note</label>
                                    <input type="text" name="description" placeholder="Unused budget returned after campaign closed" class="w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white font-bold focus:outline-none">
                                </div>
                                <button type="submit" class="w-full px-6 py-3 bg-blue-600 text-white font-black rounded-xl hover:bg-blue-700 transition">↩ Refund to Available</button>
                            </form>
                        </div>
                        <?php endif; ?>

                        <!-- Status Control -->
                        <div class="p-6 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                            <h3 class="font-black text-gray-900 dark:text-white mb-4">Wallet Status</h3>
                            <p class="text-sm text-gray-500 mb-4">Current status: <span class="font-black text-gray-900 dark:text-white"><?php echo ucfirst($focused_wallet['status']); ?></span></p>
                            <?php if ($focused_wallet['status'] === 'active'): ?>
                                <form method="POST">
                                    <input type="hidden" name="action" value="freeze">
                                    <input type="hidden" name="wallet_id" value="<?php echo $focused_wallet['id']; ?>">
                                    <button type="submit" class="w-full px-6 py-3 bg-orange-500 text-white font-black rounded-xl hover:bg-orange-600 transition" onclick="return confirm('Freeze this wallet? The brand will not be able to publish campaigns.')">
                                        🔒 Freeze Wallet
                                    </button>
                                </form>
                            <?php else: ?>
                                <form method="POST">
                                    <input type="hidden" name="action" value="unfreeze">
                                    <input type="hidden" name="wallet_id" value="<?php echo $focused_wallet['id']; ?>">
                                    <button type="submit" class="w-full px-6 py-3 bg-green-600 text-white font-black rounded-xl hover:bg-green-700 transition">
                                        🔓 Reactivate Wallet
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Transaction History -->
                    <div class="lg:col-span-2">
                        <div class="bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm overflow-hidden">
                            <div class="p-6 border-b border-gray-100 dark:border-gray-800">
                                <h3 class="font-bold text-gray-900 dark:text-white">Transaction History — <?php echo e($focused_wallet['brand_name']); ?></h3>
                                <p class="text-xs text-gray-500 mt-1"><?php echo e($focused_wallet['email']); ?></p>
                            </div>

                            <?php if (empty($transactions)): ?>
                                <div class="p-12 text-center"><p class="text-gray-400 text-sm">No transactions yet.</p></div>
                            <?php else: ?>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr class="bg-gray-50 dark:bg-gray-800/50">
                                                <th class="text-left px-5 py-3 text-[10px] font-black uppercase tracking-widest text-gray-400">Date</th>
                                                <th class="text-left px-5 py-3 text-[10px] font-black uppercase tracking-widest text-gray-400">Type</th>
                                                <th class="text-right px-5 py-3 text-[10px] font-black uppercase tracking-widest text-gray-400">Amount</th>
                                                <th class="text-right px-5 py-3 text-[10px] font-black uppercase tracking-widest text-gray-400">Balance After</th>
                                                <th class="text-left px-5 py-3 text-[10px] font-black uppercase tracking-widest text-gray-400">By</th>
                                                <th class="text-left px-5 py-3 text-[10px] font-black uppercase tracking-widest text-gray-400">Note</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                            <?php foreach ($transactions as $tx): ?>
                                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/30 transition">
                                                    <td class="px-5 py-3 text-xs text-gray-500 whitespace-nowrap"><?php echo date('M d, Y H:i', strtotime($tx['created_at'])); ?></td>
                                                    <td class="px-5 py-3 text-xs font-bold text-gray-700 dark:text-gray-300 whitespace-nowrap"><?php echo format_tx_type($tx['transaction_type']); ?></td>
                                                    <td class="px-5 py-3 text-right font-black whitespace-nowrap <?php echo tx_amount_class($tx['transaction_type']); ?>">
                                                        <?php echo tx_sign($tx['transaction_type']); ?><?php echo format_money($tx['amount'], $tx['currency']); ?>
                                                    </td>
                                                    <td class="px-5 py-3 text-right text-xs font-bold text-gray-900 dark:text-white whitespace-nowrap">
                                                        <?php echo format_money($tx['balance_after'], $tx['currency']); ?>
                                                    </td>
                                                    <td class="px-5 py-3 text-xs text-gray-500"><?php echo $tx['admin_id'] ? ('Admin #' . $tx['admin_id']) : 'System'; ?></td>
                                                    <td class="px-5 py-3 text-xs text-gray-500 max-w-[200px] truncate"><?php echo e($tx['description'] ?: '—'); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            <?php else: ?>
            <!-- ── LIST VIEW ─────────────────────────────────────────── -->

                <!-- Summary Stats -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="p-6 bg-gray-900 text-white rounded-[2rem]">
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Total Wallets</p>
                        <p class="text-3xl font-black mt-2"><?php echo number_format($stats['total']); ?></p>
                    </div>
                    <div class="p-6 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800">
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Total Available</p>
                        <p class="text-2xl font-black text-gray-900 dark:text-white mt-2"><?php echo number_format($stats['total_avail'], 0); ?></p>
                    </div>
                    <div class="p-6 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800">
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Total Reserved</p>
                        <p class="text-2xl font-black text-gray-900 dark:text-white mt-2"><?php echo number_format($stats['total_reserved'], 0); ?></p>
                    </div>
                    <div class="p-6 bg-secondary rounded-[2rem] text-white">
                        <p class="text-[10px] font-black uppercase tracking-widest text-white/60">Total Paid Out</p>
                        <p class="text-2xl font-black mt-2"><?php echo number_format($stats['total_spent'], 0); ?></p>
                    </div>
                </div>

                <!-- Search -->
                <form method="GET" class="flex gap-3">
                    <input type="hidden" name="view" value="list">
                    <input type="text" name="search" value="<?php echo e($search); ?>" placeholder="Search brand name or email..." class="flex-1 px-4 py-3 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl text-sm focus:outline-none focus:border-secondary">
                    <button type="submit" class="px-6 py-3 bg-secondary text-white font-bold rounded-xl hover:scale-105 transition">Search</button>
                    <?php if ($search): ?><a href="brand-wallets.php" class="px-6 py-3 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 font-bold rounded-xl hover:border-secondary transition text-sm flex items-center">Clear</a><?php endif; ?>
                </form>

                <!-- Wallets Table -->
                <div class="bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-gray-100 dark:border-gray-800">
                        <h3 class="font-bold text-gray-900 dark:text-white">All Brand Wallets <?php echo $search ? '— Results for "' . e($search) . '"' : ''; ?></h3>
                    </div>

                    <?php if (empty($all_wallets)): ?>
                        <div class="p-12 text-center"><p class="text-gray-400 text-sm">No wallets found.</p></div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="bg-gray-50 dark:bg-gray-800/50">
                                        <th class="text-left px-6 py-4 text-[10px] font-black uppercase tracking-widest text-gray-400">Brand</th>
                                        <th class="text-right px-6 py-4 text-[10px] font-black uppercase tracking-widest text-gray-400">Available</th>
                                        <th class="text-right px-6 py-4 text-[10px] font-black uppercase tracking-widest text-gray-400">Reserved</th>
                                        <th class="text-right px-6 py-4 text-[10px] font-black uppercase tracking-widest text-gray-400">Spent</th>
                                        <th class="text-center px-6 py-4 text-[10px] font-black uppercase tracking-widest text-gray-400">Currency</th>
                                        <th class="text-center px-6 py-4 text-[10px] font-black uppercase tracking-widest text-gray-400">Status</th>
                                        <th class="text-center px-6 py-4 text-[10px] font-black uppercase tracking-widest text-gray-400">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    <?php foreach ($all_wallets as $w): ?>
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/30 transition">
                                            <td class="px-6 py-4">
                                                <p class="font-bold text-gray-900 dark:text-white text-sm"><?php echo e($w['brand_name']); ?></p>
                                                <p class="text-xs text-gray-500"><?php echo e($w['email']); ?></p>
                                            </td>
                                            <td class="px-6 py-4 text-right font-black text-gray-900 dark:text-white whitespace-nowrap">
                                                <?php echo format_money($w['available_balance'], $w['currency']); ?>
                                            </td>
                                            <td class="px-6 py-4 text-right font-bold text-gray-600 dark:text-gray-400 whitespace-nowrap">
                                                <?php echo format_money($w['reserved_balance'], $w['currency']); ?>
                                            </td>
                                            <td class="px-6 py-4 text-right font-bold text-secondary whitespace-nowrap">
                                                <?php echo format_money($w['total_spent'], $w['currency']); ?>
                                            </td>
                                            <td class="px-6 py-4 text-center">
                                                <span class="px-3 py-1 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-full text-[10px] font-black"><?php echo e($w['currency']); ?></span>
                                            </td>
                                            <td class="px-6 py-4 text-center">
                                                <?php
                                                $sc = match($w['status']) {
                                                    'active' => 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400',
                                                    'frozen' => 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400',
                                                    default  => 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400',
                                                };
                                                ?>
                                                <span class="px-3 py-1 rounded-full text-[10px] font-black <?php echo $sc; ?>"><?php echo ucfirst($w['status']); ?></span>
                                            </td>
                                            <td class="px-6 py-4 text-center">
                                                <a href="?view=detail&wallet_id=<?php echo $w['id']; ?>" class="px-4 py-2 bg-gray-900 dark:bg-gray-700 text-white text-xs font-black rounded-xl hover:bg-secondary transition whitespace-nowrap">
                                                    Manage →
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

            <?php endif; ?>

        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
