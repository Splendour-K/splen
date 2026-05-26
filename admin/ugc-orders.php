<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_role('admin');

$message = '';

// ── POST-based mutations ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action   = $_POST['action']   ?? '';
    $order_id = (int)($_POST['order_id'] ?? 0);

    if ($order_id) {
        switch ($action) {
            case 'publish':
                $pdo->prepare("UPDATE ugc_orders SET status = 'published' WHERE id = ?")->execute([$order_id]);
                log_activity($_SESSION['user_id'], 'UGC Order Published', "Order #$order_id published by admin");
                $message = 'Order published.';
                break;
            case 'close':
                $pdo->prepare("UPDATE ugc_orders SET status = 'closed' WHERE id = ?")->execute([$order_id]);
                log_activity($_SESSION['user_id'], 'UGC Order Closed', "Order #$order_id closed by admin");
                $message = 'Order closed.';
                break;
            case 'complete':
                $pdo->prepare("UPDATE ugc_orders SET status = 'completed' WHERE id = ?")->execute([$order_id]);
                log_activity($_SESSION['user_id'], 'UGC Order Completed', "Order #$order_id marked complete by admin");
                $message = 'Order marked as completed.';
                break;
            case 'delete':
                // Cascade: submissions → order
                $pdo->prepare("DELETE FROM ugc_order_submissions WHERE ugc_order_id = ?")->execute([$order_id]);
                $pdo->prepare("DELETE FROM ugc_orders WHERE id = ?")->execute([$order_id]);
                log_activity($_SESSION['user_id'], 'UGC Order Deleted', "Order #$order_id deleted by admin");
                $message = 'Order and all its submissions deleted.';
                break;
        }
    }
}

// ── Filters ───────────────────────────────────────────────────
$filter_status = $_GET['status'] ?? '';
$search        = trim($_GET['search'] ?? '');

$sql    = "
    SELECT u.*,
        b.brand_name,
        (SELECT COUNT(*) FROM ugc_order_submissions s WHERE s.ugc_order_id = u.id)                              AS submission_count,
        (SELECT COUNT(*) FROM ugc_order_submissions s WHERE s.ugc_order_id = u.id AND s.status = 'submitted')   AS pending_count,
        (SELECT COUNT(*) FROM ugc_order_submissions s WHERE s.ugc_order_id = u.id AND s.status = 'approved')    AS approved_count
    FROM ugc_orders u
    JOIN brands b ON u.brand_id = b.id
    WHERE 1=1
";
$params = [];

if ($filter_status !== '') {
    $sql    .= " AND u.status = ?";
    $params[] = $filter_status;
}
if ($search !== '') {
    $sql    .= " AND (u.title LIKE ? OR b.brand_name LIKE ?)";
    $s       = '%' . $search . '%';
    $params[] = $s;
    $params[] = $s;
}
$sql .= " ORDER BY u.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Stats
$stats = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'draft')     AS draft_count,
        SUM(status = 'published') AS published_count,
        SUM(status = 'closed')    AS closed_count,
        SUM(status = 'completed') AS completed_count,
        COALESCE(SUM(budget_per_creator * creator_count), 0) AS total_budget
    FROM ugc_orders
")->fetch();

include '../includes/header.php';
?>

<div class="pt-24 min-h-screen bg-gray-50 dark:bg-gray-950">
    <div class="max-w-7xl mx-auto px-6 md:px-12 flex flex-col md:flex-row gap-8 py-8">
        <?php include 'dashboard_sidebar.php'; ?>

        <main class="flex-1 space-y-8">

            <!-- Header -->
            <header class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Admin — UGC</p>
                <h2 class="text-3xl font-black text-gray-900 dark:text-white mt-1">UGC Order Management</h2>
                <p class="text-gray-500 font-bold mt-1">Manage all brand UGC commissions, moderate submissions, and control order lifecycle.</p>
            </header>

            <?php if ($message): ?>
                <div class="p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-900 rounded-2xl text-green-800 dark:text-green-300 text-sm font-bold">
                    ✓ <?php echo e($message); ?>
                </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="grid grid-cols-2 md:grid-cols-6 gap-4">
                <?php
                $total_budget_fmt = number_format((float)$stats['total_budget'], 0);
                $stat_cards = [
                    ['Total',      $stats['total'],           'bg-gray-900',   'text-white'],
                    ['Draft',      $stats['draft_count'],     'bg-gray-500',   'text-white'],
                    ['Published',  $stats['published_count'], 'bg-green-500',  'text-white'],
                    ['Closed',     $stats['closed_count'],    'bg-orange-500', 'text-white'],
                    ['Completed',  $stats['completed_count'], 'bg-blue-500',   'text-white'],
                    ['Total Budget', $total_budget_fmt,       'bg-secondary',  'text-white'],
                ];
                foreach ($stat_cards as [$label, $val, $bg, $tc]):
                ?>
                <div class="p-5 <?php echo $bg; ?> rounded-[1.5rem] shadow-sm">
                    <p class="text-[10px] font-black uppercase tracking-widest <?php echo $tc; ?> opacity-70"><?php echo $label; ?></p>
                    <p class="text-2xl font-black <?php echo $tc; ?> mt-1"><?php echo $val; ?></p>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Search + Filters -->
            <div class="space-y-4">
                <form method="GET" class="flex gap-2">
                    <input type="hidden" name="status" value="<?php echo e($filter_status); ?>">
                    <input type="text" name="search" placeholder="Search title or brand…" value="<?php echo e($search); ?>"
                           class="flex-1 px-4 py-3 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary text-sm">
                    <button type="submit" class="px-6 py-3 bg-secondary text-white font-bold rounded-xl hover:scale-105 transition text-sm">Search</button>
                </form>

                <div class="flex flex-wrap gap-2">
                    <?php
                    $tabs = ['' => 'All', 'draft' => 'Draft', 'published' => 'Published', 'closed' => 'Closed', 'completed' => 'Completed'];
                    foreach ($tabs as $key => $label):
                    ?>
                        <a href="?status=<?php echo $key; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>"
                           class="px-4 py-2 rounded-xl font-bold text-sm <?php echo $filter_status === $key ? 'bg-secondary text-white' : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border border-gray-100 dark:border-gray-800'; ?>">
                            <?php echo $label; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Orders List -->
            <div class="space-y-4">
                <?php foreach ($orders as $order): ?>
                    <div class="p-6 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                        <div class="flex flex-col lg:flex-row lg:items-start gap-5">

                            <!-- Info -->
                            <div class="flex-1">
                                <div class="flex flex-wrap items-center gap-2 mb-2">
                                    <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase
                                        <?php echo $order['status'] === 'published'  ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' :
                                                  ($order['status'] === 'completed'  ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' :
                                                  ($order['status'] === 'closed'     ? 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400' :
                                                                                       'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400')); ?>">
                                        <?php echo ucfirst(e($order['status'])); ?>
                                    </span>
                                    <span class="px-3 py-1 bg-secondary/10 text-secondary rounded-full text-[10px] font-black uppercase">
                                        <?php echo e($order['currency']); ?> <?php echo number_format((float)$order['budget_per_creator'], 2); ?>/video
                                    </span>
                                </div>

                                <h3 class="text-xl font-black text-gray-900 dark:text-white"><?php echo e($order['title']); ?></h3>
                                <p class="text-xs font-bold text-gray-400 mt-1">
                                    Brand: <span class="text-secondary"><?php echo e($order['brand_name']); ?></span>
                                    &nbsp;·&nbsp; <?php echo (int)$order['creator_count']; ?> creators
                                    &nbsp;·&nbsp; Deadline <?php echo date('M d, Y', strtotime($order['deadline'])); ?>
                                </p>

                                <div class="flex gap-5 mt-3 text-xs">
                                    <span>Submissions: <strong class="text-gray-900 dark:text-white"><?php echo (int)$order['submission_count']; ?></strong></span>
                                    <span>Pending: <strong class="text-orange-600"><?php echo (int)$order['pending_count']; ?></strong></span>
                                    <span>Approved: <strong class="text-green-600"><?php echo (int)$order['approved_count']; ?></strong></span>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="flex flex-wrap items-center gap-2 lg:flex-col lg:items-stretch lg:w-48">

                                <!-- Review Submissions -->
                                <a href="ugc-submissions.php?order_id=<?php echo $order['id']; ?>"
                                   class="px-4 py-2 bg-secondary text-white font-bold rounded-xl text-xs text-center hover:scale-105 transition">
                                    📋 Review (<?php echo (int)$order['pending_count']; ?>)
                                </a>

                                <!-- Edit -->
                                <a href="../brand/edit-ugc-order.php?id=<?php echo $order['id']; ?>"
                                   class="px-4 py-2 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-bold rounded-xl text-xs text-center hover:bg-gray-200 dark:hover:bg-gray-700 transition">
                                    ✏️ Edit
                                </a>

                                <!-- Status transitions -->
                                <?php if ($order['status'] === 'draft'): ?>
                                    <form method="POST">
                                        <input type="hidden" name="action"   value="publish">
                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                        <button type="submit" class="w-full px-4 py-2 bg-green-600 text-white font-bold rounded-xl text-xs hover:bg-green-700 transition">▶ Publish</button>
                                    </form>
                                <?php elseif ($order['status'] === 'published'): ?>
                                    <form method="POST">
                                        <input type="hidden" name="action"   value="close">
                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                        <button type="submit" class="w-full px-4 py-2 bg-orange-500 text-white font-bold rounded-xl text-xs hover:bg-orange-600 transition">🔒 Close</button>
                                    </form>
                                <?php elseif ($order['status'] === 'closed'): ?>
                                    <form method="POST">
                                        <input type="hidden" name="action"   value="complete">
                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                        <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white font-bold rounded-xl text-xs hover:bg-blue-700 transition">✓ Complete</button>
                                    </form>
                                <?php endif; ?>

                                <!-- Delete -->
                                <form method="POST" onsubmit="return confirm('Delete this UGC order and ALL its submissions permanently?');">
                                    <input type="hidden" name="action"   value="delete">
                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                    <button type="submit" class="w-full px-4 py-2 bg-red-50 text-red-600 font-bold rounded-xl text-xs hover:bg-red-600 hover:text-white transition">🗑 Delete</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (empty($orders)): ?>
                <div class="p-12 text-center bg-white dark:bg-gray-900 rounded-[2.5rem] border border-dashed border-gray-200 dark:border-gray-800 shadow-sm">
                    <p class="text-gray-400">No UGC orders match your filters.</p>
                </div>
            <?php endif; ?>

        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
