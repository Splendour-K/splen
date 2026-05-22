<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_role('admin');

$success = $_GET['success'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_ticket'])) {
    $ticket_id = (int)$_POST['ticket_id'];
    $status = $_POST['status'] ?? 'open';

    $stmt = $pdo->prepare('UPDATE support_tickets SET status = ? WHERE id = ?');
    $stmt->execute([$status, $ticket_id]);

    header('Location: support-tickets.php?success=' . urlencode('Support ticket updated.'));
    exit;
}

$stmt = $pdo->query("\n    SELECT st.*, u.email, u.role, b.brand_name, c.full_name\n    FROM support_tickets st\n    JOIN users u ON st.user_id = u.id\n    LEFT JOIN brands b ON b.user_id = u.id\n    LEFT JOIN creators c ON c.user_id = u.id\n    ORDER BY st.created_at DESC\n");
$tickets = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="pt-24 min-h-screen bg-gray-50 dark:bg-gray-950">
    <div class="max-w-7xl mx-auto px-6 md:px-12 flex flex-col md:flex-row gap-8 py-8">
        <?php include 'dashboard_sidebar.php'; ?>

        <main class="flex-1 space-y-8">
            <header class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                <h2 class="text-3xl font-black text-gray-900 dark:text-white">Support Tickets</h2>
                <p class="text-gray-500 font-bold mt-1">Brand and creator requests waiting for admin response.</p>
            </header>

            <?php if ($success): ?>
                <div class="p-4 bg-green-100 text-green-700 rounded-2xl font-bold text-sm"><?php echo e($success); ?></div>
            <?php endif; ?>

            <section class="space-y-4">
                <?php if (empty($tickets)): ?>
                    <div class="p-12 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-dashed border-gray-200 dark:border-gray-800 text-center text-gray-400 font-bold uppercase tracking-widest text-xs">
                        No support tickets yet.
                    </div>
                <?php else: ?>
                    <?php foreach ($tickets as $ticket): ?>
                        <div class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm space-y-4">
                            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">Ticket #<?php echo (int)$ticket['id']; ?></p>
                                    <h3 class="text-lg font-black text-gray-900 dark:text-white"><?php echo e($ticket['subject']); ?></h3>
                                    <p class="text-sm text-gray-500 font-bold mt-1">
                                        <?php echo e($ticket['brand_name'] ?: $ticket['full_name'] ?: $ticket['email']); ?>
                                        <span class="mx-2">•</span>
                                        <?php echo e($ticket['role']); ?>
                                    </p>
                                </div>
                                <div class="text-right space-y-2">
                                    <span class="inline-flex px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest <?php echo $ticket['status'] === 'resolved' ? 'bg-green-100 text-green-700' : ($ticket['status'] === 'closed' ? 'bg-gray-100 text-gray-700' : 'bg-orange-100 text-orange-700'); ?>">
                                        <?php echo e($ticket['status']); ?>
                                    </span>
                                    <p class="text-[10px] font-bold text-gray-400"><?php echo e($ticket['priority']); ?> priority</p>
                                </div>
                            </div>

                            <p class="text-sm leading-relaxed text-gray-600 dark:text-gray-400 whitespace-pre-wrap"><?php echo e($ticket['message']); ?></p>

                            <form method="POST" class="flex flex-col md:flex-row gap-3 md:items-center">
                                <input type="hidden" name="ticket_id" value="<?php echo (int)$ticket['id']; ?>">
                                <select name="status" class="px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white font-bold">
                                    <option value="open" <?php echo $ticket['status'] === 'open' ? 'selected' : ''; ?>>Open</option>
                                    <option value="in_progress" <?php echo $ticket['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="resolved" <?php echo $ticket['status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                    <option value="closed" <?php echo $ticket['status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
                                </select>
                                <button type="submit" name="update_ticket" class="px-5 py-3 bg-gray-900 dark:bg-white text-white dark:text-gray-900 font-black rounded-xl text-[10px] uppercase tracking-widest">Update Ticket</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>