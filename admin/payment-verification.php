<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_role('admin');

$filter = $_GET['filter'] ?? 'pending';
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'newest';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_payment_id'])) {
    $delete_payment_id = (int)$_POST['delete_payment_id'];
    $pdo->prepare("DELETE FROM payments WHERE id = ?")->execute([$delete_payment_id]);
    redirect('admin/payment-verification.php?message=Payment deleted');
    exit;
}

$where = "1=1";
$params = [];

if ($filter === 'pending') {
    $where .= " AND p.status = 'pending'";
} elseif ($filter === 'completed') {
    $where .= " AND p.status = 'completed'";
} elseif ($filter === 'disputed') {
    $where .= " AND p.status = 'disputed'";
}

if ($search) {
    $where .= " AND (cr.full_name LIKE ? OR b.brand_name LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$order = "p.created_at DESC";
if ($sort === 'amount_high') {
    $order = "p.calculated_amount DESC";
} elseif ($sort === 'amount_low') {
    $order = "p.calculated_amount ASC";
}

$stmt = $pdo->prepare("
    SELECT p.*, cr.full_name as creator_name, b.brand_name AS company_name
    FROM payments p
    LEFT JOIN creators cr ON p.creator_id = cr.id
    LEFT JOIN jobs j ON p.job_id = j.id
    LEFT JOIN brands b ON j.brand_id = b.id
    WHERE {$where}
    ORDER BY {$order}
");
$stmt->execute($params);
$payments = $stmt->fetchAll();

if (isset($_POST['verify_payment']) && isset($_POST['payment_id'])) {
    $payment_id = $_POST['payment_id'];
    $stmt = $pdo->prepare("UPDATE payments SET status = 'completed', verified_at = NOW() WHERE id = ?");
    $stmt->execute([$payment_id]);
    redirect('admin/payment-verification.php?message=Payment verified and completed');
    exit;
}

if (isset($_POST['dispute_payment']) && isset($_POST['payment_id'])) {
    $dispute_reason = $_POST['dispute_reason'] ?? 'Pending review';
    $payment_id = $_POST['payment_id'];
    $stmt = $pdo->prepare("UPDATE payments SET status = 'disputed', dispute_reason = ?, disputed_at = NOW() WHERE id = ?");
    $stmt->execute([$dispute_reason, $payment_id]);
    redirect('admin/payment-verification.php?message=Payment marked as disputed');
    exit;
}

include '../includes/header.php';
?>

<div class="pt-24 min-h-screen bg-gray-50 dark:bg-gray-950">
    <div class="max-w-7xl mx-auto px-6 md:px-12 flex flex-col md:flex-row gap-8 py-8 w-full">
        <!-- Sidebar -->
        <?php include "dashboard_sidebar.php"; ?>

        <!-- Main Content -->
        <main class="flex-1 space-y-8">
            <header class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm relative overflow-hidden">
                <div aria-hidden="true" class="absolute top-0 right-0 -mr-16 -mt-16 w-64 h-64 bg-orange-500/5 rounded-full blur-3xl"></div>
                <div class="relative">
                    <h2 class="text-3xl font-black text-gray-900 dark:text-white">Payment Verification</h2>
                    <p class="text-gray-500 font-bold mt-1">Verify and manage creator payments.</p>
                </div>
            </header>

            <?php if (!empty($_GET['message'])): ?>
                <div class="p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-900 rounded-2xl text-green-800 dark:text-green-300 text-sm font-medium">
                    ✓ <?php echo e($_GET['message']); ?>
                </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="p-6 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                <form method="GET" class="flex flex-col md:flex-row gap-4">
                    <div class="flex-1">
                        <input type="text" name="search" placeholder="Search creator or brand..." value="<?php echo e($search); ?>"
                            class="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                    </div>
                    <select name="filter" class="px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white font-medium">
                        <option value="pending" <?php echo $filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="completed" <?php echo $filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="disputed" <?php echo $filter === 'disputed' ? 'selected' : ''; ?>>Disputed</option>
                    </select>
                    <select name="sort" class="px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white font-medium">
                        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest</option>
                        <option value="amount_high" <?php echo $sort === 'amount_high' ? 'selected' : ''; ?>>Highest Amount</option>
                        <option value="amount_low" <?php echo $sort === 'amount_low' ? 'selected' : ''; ?>>Lowest Amount</option>
                    </select>
                    <button type="submit" class="px-6 py-3 bg-orange-500 text-white font-bold rounded-xl hover:bg-orange-600 transition">Filter</button>
                </form>
            </div>

            <!-- Payments List -->
            <div class="bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm overflow-hidden">
                <?php if (empty($payments)): ?>
                    <div class="p-12 text-center">
                        <p class="text-gray-400 text-sm">No payments found.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                                <tr>
                                    <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-widest text-gray-600 dark:text-gray-400">Creator</th>
                                    <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-widest text-gray-600 dark:text-gray-400">Amount</th>
                                    <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-widest text-gray-600 dark:text-gray-400">Type</th>
                                    <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-widest text-gray-600 dark:text-gray-400">Status</th>
                                    <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-widest text-gray-600 dark:text-gray-400">Date</th>
                                    <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-widest text-gray-600 dark:text-gray-400">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                <?php foreach ($payments as $payment): ?>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                                        <td class="px-6 py-4">
                                            <p class="font-bold text-gray-900 dark:text-white text-sm"><?php echo e($payment['creator_name'] ?? 'N/A'); ?></p>
                                        </td>
                                        <td class="px-6 py-4">
                                            <p class="font-bold text-gray-900 dark:text-white"><?php echo number_format((float)($payment['calculated_amount'] ?? $payment['amount'] ?? 0), 2); ?> <?php echo e($payment['currency'] ?? 'USD'); ?></p>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="text-[10px] font-bold uppercase bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded"><?php echo e($payment['payment_type']); ?></span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="px-3 py-1 rounded-full text-[10px] font-bold <?php
                                                echo $payment['status'] === 'completed' ? 'bg-green-100 text-green-700 dark:bg-green-900/20 dark:text-green-400' :
                                                ($payment['status'] === 'disputed' ? 'bg-red-100 text-red-700 dark:bg-red-900/20 dark:text-red-400' :
                                                'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/20 dark:text-yellow-400');
                                            ?>"><?php echo ucfirst($payment['status']); ?></span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <p class="text-[10px] text-gray-500"><?php echo time_ago($payment['created_at']); ?></p>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php if ($payment['status'] === 'pending'): ?>
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                                    <input type="hidden" name="verify_payment" value="1">
                                                    <button type="submit" class="text-[10px] font-bold text-green-600 hover:text-green-700">✓ Verify</button>
                                                </form>
                                                <button type="button" class="text-[10px] font-bold text-red-600 hover:text-red-700 ml-3" onclick="disputePayment(<?php echo $payment['id']; ?>)">⚠ Dispute</button>
                                            <?php endif; ?>
                                            <form method="POST" class="inline ml-3" onsubmit="return confirm('Delete this payment record?');">
                                                <input type="hidden" name="delete_payment_id" value="<?php echo $payment['id']; ?>">
                                                <button type="submit" class="text-[10px] font-bold text-gray-500 hover:text-red-600">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<script>
function disputePayment(paymentId) {
    const reason = prompt('Enter dispute reason:');
    if (reason) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="payment_id" value="${paymentId}">
            <input type="hidden" name="dispute_payment" value="1">
            <input type="hidden" name="dispute_reason" value="${reason}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include '../includes/footer.php'; ?>
