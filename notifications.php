<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!is_logged_in()) redirect('login.php');

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Mark all as read
if (isset($_GET['mark_read'])) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$user_id]);
    header("Location: notifications.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_read') {
    $notification_id = (int)($_POST['notification_id'] ?? 0);
    if ($notification_id > 0) {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$notification_id, $user_id]);
    }
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit();
}

// Fetch Notifications
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="pt-24 min-h-screen bg-gray-50 dark:bg-gray-950">
    <div class="max-w-7xl mx-auto px-6 md:px-12 flex flex-col md:flex-row gap-8 py-8">
        <!-- Dynamic Sidebar based on role -->
        <?php 
        if ($role === 'brand') include 'includes/brand_sidebar.php';
        elseif ($role === 'creator') include 'includes/creator_sidebar.php';
        ?>

        <main class="flex-1 space-y-8">
            <header class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm flex flex-col md:flex-row justify-between items-center gap-6">
                <div>
                    <h2 class="text-3xl font-black text-gray-900 dark:text-white">Inbox</h2>
                    <p class="text-gray-500 font-bold mt-1">Stay updated with your campaign activity.</p>
                </div>
                <?php if (!empty($notifications)): ?>
                    <a href="?mark_read=1" class="px-6 py-3 bg-gray-50 dark:bg-gray-800 text-gray-500 font-black rounded-xl text-[10px] uppercase tracking-widest hover:bg-orange-500 hover:text-white transition-all">Mark all as read</a>
                <?php endif; ?>
            </header>

            <div class="space-y-4" id="notifications-list">
                <?php foreach ($notifications as $n): ?>
                    <?php
                        $target_url = $n['target_url'] ?: ($role === 'brand' ? 'brand/dashboard.php' : 'creator/dashboard.php');
                        $href = preg_match('#^https?://#i', $target_url) ? $target_url : APP_URL . ltrim($target_url, '/');
                    ?>
                    <a href="<?php echo e($href); ?>" data-notification-id="<?php echo (int)$n['id']; ?>" class="notification-link block p-6 bg-white dark:bg-gray-900 rounded-3xl border <?php echo $n['is_read'] ? 'border-gray-100 dark:border-gray-800' : 'border-orange-500/30 ring-1 ring-orange-500/10 shadow-lg shadow-orange-500/5'; ?> transition-all relative hover:border-orange-500/50">
                        <?php if (!$n['is_read']): ?>
                            <div class="absolute top-6 right-6 w-2 h-2 bg-orange-500 rounded-full animate-ping"></div>
                        <?php endif; ?>
                        
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-2xl flex items-center justify-center bg-gray-50 dark:bg-gray-800 text-xl shadow-inner">
                                <?php 
                                    switch($n['type']) {
                                        case 'payment': echo '💰'; break;
                                        case 'job': echo '🎬'; break;
                                        case 'application': echo '📩'; break;
                                        case 'message': echo '💬'; break;
                                        default: echo '⚡';
                                    }
                                ?>
                            </div>
                            <div class="flex-1">
                                <h4 class="text-lg font-black text-gray-900 dark:text-white"><?php echo e($n['title']); ?></h4>
                                <p class="text-sm text-gray-600 dark:text-gray-400 font-medium mt-1"><?php echo e($n['message']); ?></p>
                                <p class="text-[10px] text-gray-400 font-bold uppercase mt-4"><?php echo time_ago($n['created_at']); ?></p>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>

                <?php if (empty($notifications)): ?>
                    <div class="p-20 text-center bg-white dark:bg-gray-900 rounded-[2.5rem] border border-dashed border-gray-200 dark:border-gray-800">
                        <div class="text-5xl mb-6">📭</div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white">All caught up!</h3>
                        <p class="text-gray-400">Your inbox is empty.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<script>
(function() {
    document.querySelectorAll('.notification-link').forEach(function(link) {
        link.addEventListener('click', function(event) {
            const id = this.getAttribute('data-notification-id');
            if (!id) return;
            event.preventDefault();
            fetch('<?php echo APP_URL; ?>notifications.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=mark_read&notification_id=' + encodeURIComponent(id)
            }).finally(() => {
                window.location.href = this.href;
            });
        });
    });
})();
</script>

<?php include 'includes/footer.php'; ?>