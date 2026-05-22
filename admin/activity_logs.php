<?php
require_once "../config/database.php";
require_once "../includes/functions.php";
require_role("admin");

// Handle cleanup
if (isset($_GET['action']) && $_GET['action'] === 'clear_old') {
    $stmt = $pdo->query("DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    header("Location: activity_logs.php?success=Logs older than 30 days cleared.");
    exit();
}

// Fetch logs with user details
$stmt = $pdo->query("
    SELECT l.*, u.email, u.role
    FROM activity_logs l
    LEFT JOIN users u ON l.user_id = u.id
    ORDER BY l.created_at DESC
");
$logs = $stmt->fetchAll();

include "../includes/header.php";
?>

<div class="pt-24 min-h-screen bg-gray-50 dark:bg-gray-950">
    <div class="max-w-7xl mx-auto px-6 md:px-12 flex flex-col md:flex-row gap-8 py-8">
        <?php include "dashboard_sidebar.php"; ?>

        <main class="flex-1 space-y-8">
            <header class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm flex flex-col md:flex-row justify-between items-center gap-6">
                <div>
                    <h2 class="text-3xl font-black text-gray-900 dark:text-white">Activity Logs</h2>
                    <p class="text-gray-500 font-bold mt-1">Platform-wide events and security auditing.</p>
                </div>
                <div class="flex gap-4">
                    <a href="?action=clear_old" onclick="return confirm('Clear logs older than 30 days?')" class="px-6 py-3 bg-red-50 dark:bg-red-950/20 text-red-500 font-black rounded-xl text-xs uppercase tracking-widest border border-red-100 dark:border-red-950/30 flex items-center gap-2 transition-all">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                        Cleanup Logs
                    </a>
                </div>
            </header>

            <div class="overflow-hidden rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                <table class="w-full text-left bg-white dark:bg-gray-900 border-collapse">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-gray-800/50">
                            <th class="px-8 py-5 text-[10px] font-black uppercase tracking-widest text-gray-400">Timestamp</th>
                            <th class="px-8 py-5 text-[10px] font-black uppercase tracking-widest text-gray-400">User</th>
                            <th class="px-8 py-5 text-[10px] font-black uppercase tracking-widest text-gray-400">Action</th>
                            <th class="px-8 py-5 text-[10px] font-black uppercase tracking-widest text-gray-400">Details</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50 dark:divide-gray-800">
                        <?php foreach ($logs as $log): ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/30 transition-all">
                                <td class="px-8 py-5">
                                    <p class="text-xs font-bold text-gray-900 dark:text-white"><?php echo date("M d, Y", strtotime($log['created_at'])); ?></p>
                                    <p class="text-[10px] text-gray-400 font-medium"><?php echo date("H:i:s", strtotime($log['created_at'])); ?></p>
                                </td>
                                <td class="px-8 py-5">
                                    <p class="text-xs font-bold text-gray-900 dark:text-white"><?php echo e($log['email'] ?? 'System/Guest'); ?></p>
                                    <p class="text-[9px] font-black text-primary uppercase tracking-widest"><?php echo e($log['role'] ?? 'Guest'); ?></p>
                                </td>
                                <td class="px-8 py-5">
                                    <span class="px-3 py-1 bg-orange-50 dark:bg-orange-900/20 text-orange-600 dark:text-orange-400 text-[10px] font-black uppercase rounded-full tracking-wider border border-orange-100 dark:border-orange-900/30">
                                        <?php echo e($log['action']); ?>
                                    </span>
                                </td>
                                <td class="px-8 py-5 text-sm text-gray-500 dark:text-gray-400 font-medium">
                                    <?php echo e($log['details']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="4" class="px-8 py-20 text-center text-gray-400 font-bold uppercase tracking-widest text-xs"> No activity captured yet. 🛸</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</div>

<?php include "../includes/footer.php"; ?>