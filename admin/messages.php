<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_role('admin');

$search = trim($_GET['search'] ?? '');
$conv_id = (int)($_GET['conversation_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_message_id'])) {
        $message_id = (int)$_POST['delete_message_id'];
        $stmt = $pdo->prepare("DELETE FROM messages WHERE id = ?");
        $stmt->execute([$message_id]);

        if ($conv_id > 0) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE conversation_id = ?");
            $stmt->execute([$conv_id]);
            if ((int)$stmt->fetchColumn() === 0) {
                $pdo->prepare("UPDATE conversations SET last_message = NULL, updated_at = NOW() WHERE id = ?")->execute([$conv_id]);
            } else {
                $stmt = $pdo->prepare("SELECT message FROM messages WHERE conversation_id = ? ORDER BY created_at DESC LIMIT 1");
                $stmt->execute([$conv_id]);
                $last_message = $stmt->fetchColumn() ?: null;
                $pdo->prepare("UPDATE conversations SET last_message = ?, updated_at = NOW() WHERE id = ?")->execute([$last_message, $conv_id]);
            }
        }

        redirect('admin/messages.php?conversation_id=' . $conv_id . '&message=Message deleted');
        exit;
    }

    if (isset($_POST['delete_conversation_id'])) {
        $delete_conv_id = (int)$_POST['delete_conversation_id'];
        $pdo->beginTransaction();
        try {
            $pdo->prepare("DELETE FROM messages WHERE conversation_id = ?")->execute([$delete_conv_id]);
            $pdo->prepare("DELETE FROM conversations WHERE id = ?")->execute([$delete_conv_id]);
            $pdo->commit();
            redirect('admin/messages.php?message=Conversation deleted');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
        }
    }
}

$conv_where = '1=1';
$conv_params = [];
if ($search !== '') {
    $conv_where .= ' AND (b.brand_name LIKE ? OR cr.full_name LIKE ? OR c.last_message LIKE ?)';
    $search_like = '%' . $search . '%';
    $conv_params = [$search_like, $search_like, $search_like];
}

$stmt_conversations = $pdo->prepare("
    SELECT c.*, 
        b.brand_name, b.logo, b.user_id AS brand_user_id,
        cr.full_name, cr.profile_photo, cr.user_id AS creator_user_id
    FROM conversations c
    JOIN brands b ON c.brand_id = b.id
    JOIN creators cr ON c.creator_id = cr.id
    WHERE {$conv_where}
    ORDER BY c.updated_at DESC
    LIMIT 200
");
$stmt_conversations->execute($conv_params);
$conversations = $stmt_conversations->fetchAll(PDO::FETCH_ASSOC);

$selected_conversation = null;
$messages = [];
if ($conv_id > 0) {
    $stmt = $pdo->prepare("SELECT c.*, b.brand_name, b.logo, b.user_id AS brand_user_id, cr.full_name, cr.profile_photo, cr.user_id AS creator_user_id FROM conversations c JOIN brands b ON c.brand_id = b.id JOIN creators cr ON c.creator_id = cr.id WHERE c.id = ?");
    $stmt->execute([$conv_id]);
    $selected_conversation = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($selected_conversation) {
        $stmt = $pdo->prepare("SELECT m.*, u.email as sender_email FROM messages m JOIN users u ON m.sender_id = u.id WHERE m.conversation_id = ? ORDER BY m.created_at ASC");
        $stmt->execute([$conv_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

include '../includes/header.php';
?>

<div class="pt-24 min-h-screen bg-gray-50 dark:bg-gray-950">
    <div class="max-w-7xl mx-auto px-6 md:px-12 flex flex-col md:flex-row gap-8 py-8 w-full">
        <?php include 'dashboard_sidebar.php'; ?>

        <main class="flex-1 space-y-8">
            <header class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm relative overflow-hidden">
                <div aria-hidden="true" class="absolute top-0 right-0 -mr-16 -mt-16 w-64 h-64 bg-orange-500/5 rounded-full blur-3xl"></div>
                <div class="relative flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <h2 class="text-3xl font-black text-gray-900 dark:text-white">All Messages</h2>
                        <p class="text-gray-500 font-bold mt-1">View every conversation between brands and creators.</p>
                    </div>
                    <form method="GET" class="flex gap-3 w-full md:w-auto">
                        <input type="text" name="search" value="<?php echo e($search); ?>" placeholder="Search by name or message..." class="w-full md:w-96 px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                        <button type="submit" class="px-6 py-3 bg-orange-500 text-white font-bold rounded-xl hover:bg-orange-600 transition">Search</button>
                    </form>
                </div>
            </header>

            <?php if (!empty($_GET['message'])): ?>
                <div class="p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-900 rounded-2xl text-green-800 dark:text-green-300 text-sm font-medium">
                    ✓ <?php echo e($_GET['message']); ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                <section class="xl:col-span-1 p-4 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                    <div class="space-y-3 max-h-[75vh] overflow-y-auto pr-1">
                        <?php foreach ($conversations as $conversation): ?>
                            <a href="?conversation_id=<?php echo (int)$conversation['id']; ?>" class="block p-4 rounded-2xl border <?php echo $conv_id === (int)$conversation['id'] ? 'border-orange-500 bg-orange-50 dark:bg-orange-900/10' : 'border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/60'; ?> transition">
                                <div class="flex items-center gap-3">
                                    <img src="<?php echo APP_URL . ($conversation['logo'] ?: $conversation['profile_photo'] ?: 'assets/images/default-avatar.png'); ?>" class="w-12 h-12 rounded-2xl object-cover">
                                    <div class="min-w-0 flex-1">
                                        <p class="font-black text-gray-900 dark:text-white truncate"><?php echo e($conversation['brand_name']); ?> / <?php echo e($conversation['full_name']); ?></p>
                                        <p class="text-xs text-gray-500 truncate"><?php echo e($conversation['last_message'] ?: 'No messages yet'); ?></p>
                                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mt-1"><?php echo time_ago($conversation['updated_at']); ?></p>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                        <?php if (empty($conversations)): ?>
                            <p class="text-center text-gray-400 text-sm py-10">No conversations found.</p>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="xl:col-span-2 p-6 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm min-h-[75vh] flex flex-col">
                    <?php if (!$selected_conversation): ?>
                        <div class="flex-1 flex items-center justify-center text-center">
                            <div>
                                <div class="text-6xl mb-4">💬</div>
                                <h3 class="text-2xl font-black text-gray-900 dark:text-white">Select a conversation</h3>
                                <p class="text-gray-500 mt-2">Open any thread to review the full message history.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="flex items-center justify-between pb-4 border-b border-gray-100 dark:border-gray-800 mb-4">
                            <div class="flex items-center gap-4">
                                <img src="<?php echo APP_URL . ($selected_conversation['logo'] ?: $selected_conversation['profile_photo'] ?: 'assets/images/default-avatar.png'); ?>" class="w-14 h-14 rounded-2xl object-cover">
                                <div>
                                    <h3 class="text-xl font-black text-gray-900 dark:text-white"><?php echo e($selected_conversation['brand_name']); ?> / <?php echo e($selected_conversation['full_name']); ?></h3>
                                    <p class="text-sm text-gray-500">Conversation ID: <?php echo (int)$selected_conversation['id']; ?></p>
                                </div>
                            </div>
                            <form method="POST" onsubmit="return confirm('Delete this entire conversation and all messages?');">
                                <input type="hidden" name="delete_conversation_id" value="<?php echo (int)$selected_conversation['id']; ?>">
                                <button type="submit" class="px-4 py-2 rounded-xl bg-red-50 text-red-600 font-bold text-xs uppercase tracking-widest hover:bg-red-600 hover:text-white transition">Delete Conversation</button>
                            </form>
                        </div>

                        <div class="flex-1 overflow-y-auto space-y-4 pr-1">
                            <?php foreach ($messages as $message): ?>
                                <div class="p-4 rounded-2xl border border-gray-100 dark:border-gray-800 <?php echo ($message['sender_id'] == $selected_conversation['brand_user_id'] || $message['sender_id'] == $selected_conversation['creator_user_id']) ? 'bg-gray-50 dark:bg-gray-800/50' : 'bg-orange-50 dark:bg-orange-900/10'; ?>">
                                    <div class="flex items-center justify-between gap-4 mb-2">
                                        <p class="text-sm font-black text-gray-900 dark:text-white"><?php echo e($message['sender_email']); ?></p>
                                        <div class="flex items-center gap-3">
                                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest"><?php echo time_ago($message['created_at']); ?></p>
                                            <form method="POST" onsubmit="return confirm('Delete this message?');">
                                                <input type="hidden" name="delete_message_id" value="<?php echo (int)$message['id']; ?>">
                                                <button type="submit" class="text-[10px] font-black uppercase tracking-widest text-red-500 hover:text-red-600">Delete</button>
                                            </form>
                                        </div>
                                    </div>
                                    <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed"><?php echo nl2br(e($message['message'])); ?></p>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($messages)): ?>
                                <p class="text-center text-gray-400 text-sm py-10">No messages in this conversation yet.</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
