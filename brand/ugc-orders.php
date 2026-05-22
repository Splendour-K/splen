<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_role('brand');

$stmt = $pdo->prepare("SELECT * FROM brands WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$brand = $stmt->fetch();

$filter_status = $_GET['status'] ?? '';
$sql = "
    SELECT u.*,
        (SELECT COUNT(*) FROM ugc_order_submissions s WHERE s.ugc_order_id = u.id) AS submission_count,
        (SELECT COUNT(*) FROM ugc_order_submissions s WHERE s.ugc_order_id = u.id AND s.status = 'submitted') AS pending_count,
        (SELECT COUNT(*) FROM ugc_order_submissions s WHERE s.ugc_order_id = u.id AND s.status = 'approved') AS approved_count
    FROM ugc_orders u
    WHERE u.brand_id = ?
";
$params = [$brand['id']];
if ($filter_status !== '') {
    $sql .= " AND u.status = ?";
    $params[] = $filter_status;
}
$sql .= " ORDER BY u.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ugc_orders = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="pt-24 min-h-screen bg-gray-50 dark:bg-gray-950">
    <div class="max-w-7xl mx-auto px-6 md:px-12 flex flex-col md:flex-row gap-8 py-8">
        <?php include '../includes/brand_sidebar.php'; ?>

        <main class="flex-1 space-y-8">
            <header class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">UGC Commissions</p>
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white">My UGC Orders</h2>
                        <p class="text-gray-600 dark:text-gray-400 mt-2">Commission videos, review submissions, and approve payments.</p>
                    </div>
                    <a href="create-ugc-order.php" class="inline-flex items-center px-6 py-3 bg-secondary text-white font-bold rounded-xl hover:scale-105 transition-all w-fit">
                        New UGC Order
                    </a>
                </div>
            </header>

            <div class="flex flex-wrap gap-2">
                <a href="ugc-orders.php" class="px-4 py-2 rounded-xl font-bold text-sm <?php echo $filter_status === '' ? 'bg-secondary text-white' : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border border-gray-100 dark:border-gray-800'; ?>">All</a>
                <a href="?status=published" class="px-4 py-2 rounded-xl font-bold text-sm <?php echo $filter_status === 'published' ? 'bg-secondary text-white' : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border border-gray-100 dark:border-gray-800'; ?>">Active</a>
                <a href="?status=closed" class="px-4 py-2 rounded-xl font-bold text-sm <?php echo $filter_status === 'closed' ? 'bg-secondary text-white' : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border border-gray-100 dark:border-gray-800'; ?>">Closed</a>
                <a href="?status=completed" class="px-4 py-2 rounded-xl font-bold text-sm <?php echo $filter_status === 'completed' ? 'bg-secondary text-white' : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border border-gray-100 dark:border-gray-800'; ?>">Completed</a>
            </div>

            <div class="space-y-4">
                <?php foreach ($ugc_orders as $order): ?>
                    <div class="p-6 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-5">
                            <div class="flex-1">
                                <div class="flex flex-wrap items-center gap-3 mb-2">
                                    <span class="px-3 py-1 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-full text-[10px] font-black uppercase"><?php echo ucfirst(e($order['status'])); ?></span>
                                    <span class="px-3 py-1 bg-secondary/10 text-secondary rounded-full text-[10px] font-black uppercase"><?php echo e($order['currency']); ?> <?php echo number_format((float)$order['budget_per_creator'], 2); ?> per video</span>
                                </div>
                                <h3 class="text-xl font-black text-gray-900 dark:text-white"><?php echo e($order['title']); ?></h3>
                                <p class="text-sm text-gray-500 mt-1">
                                    Deadline: <?php echo date('M d, Y', strtotime($order['deadline'])); ?>
                                </p>
                                <p class="text-xs text-gray-400 mt-1">
                                    Submissions: <span class="font-bold text-gray-900 dark:text-white"><?php echo (int)$order['submission_count']; ?></span>
                                    | Pending: <span class="font-bold text-orange-600"><?php echo (int)$order['pending_count']; ?></span>
                                    | Approved: <span class="font-bold text-green-600"><?php echo (int)$order['approved_count']; ?></span>
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-3">
                                <a href="ugc-order-review.php?order_id=<?php echo $order['id']; ?>" class="px-5 py-3 bg-secondary text-white font-bold rounded-xl text-sm hover:scale-105 transition">
                                    Review & Approve
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (empty($ugc_orders)): ?>
                <div class="p-12 text-center bg-white dark:bg-gray-900 rounded-[2.5rem] border border-dashed border-gray-200 dark:border-gray-800 shadow-sm">
                    <p class="text-gray-400 text-sm">No UGC orders yet. <a href="create-ugc-order.php" class="text-secondary font-bold">Create your first order.</a></p>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
