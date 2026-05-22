<?php
require_once "config/database.php";
require_once "includes/functions.php";
if (!is_logged_in()) redirect("login.php");

$conv_id = $_GET["id"] ?? "";
if (!$conv_id) redirect("messages.php");

$user_id = $_SESSION["user_id"];
$role = $_SESSION["role"];

// Verify conversation belongs to user
if ($role === "creator") {
    $stmt = $pdo->prepare("SELECT id FROM creators WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $my_cid = $stmt->fetchColumn(); $id_field = "creator_id";
} else {
    $stmt = $pdo->prepare("SELECT id FROM brands WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $my_cid = $stmt->fetchColumn(); $id_field = "brand_id";
}

$stmt_verify = $pdo->prepare("SELECT * FROM conversations WHERE id = ? AND $id_field = ?");
$stmt_verify->execute([$conv_id, $my_cid]);
$conversation = $stmt_verify->fetch();

if (!$conversation) redirect("messages.php");

// Fetch partner details
if ($role === "creator") {
    $stmt_p = $pdo->prepare("SELECT b.brand_name as name, b.logo as photo, u.id as user_id FROM brands b JOIN users u ON b.user_id = u.id WHERE b.id = ?");
    $stmt_p->execute([$conversation["brand_id"]]);
} else {
    $stmt_p = $pdo->prepare("SELECT c.full_name as name, c.profile_photo as photo, u.id as user_id FROM creators c JOIN users u ON c.user_id = u.id WHERE c.id = ?");
    $stmt_p->execute([$conversation["creator_id"]]);
}
$partner = $stmt_p->fetch();

if (!$partner) {
    redirect("messages.php");
}

// Send message logic
if ($_SERVER["REQUEST_METHOD"] === "POST" && !empty($_POST["message"])) {
    $msg = trim($_POST["message"]);
    $stmt_send = $pdo->prepare("INSERT INTO messages (conversation_id, sender_id, receiver_id, message) VALUES (?, ?, ?, ?)");
    $stmt_send->execute([$conv_id, $user_id, $partner["user_id"], $msg]);
    
    // Update conversation last message
    $stmt_upd = $pdo->prepare("UPDATE conversations SET last_message = ?, updated_at = NOW() WHERE id = ?");
    $stmt_upd->execute([$msg, $conv_id]);
    
    header("Location: view-message.php?id=$conv_id");
    exit();
}

// Fetch messages
$stmt_msg = $pdo->prepare("SELECT * FROM messages WHERE conversation_id = ? ORDER BY created_at ASC");
$stmt_msg->execute([$conv_id]);
$messages = $stmt_msg->fetchAll();

include "includes/header.php";
?>

<div class="pt-24 min-h-screen bg-gray-50 dark:bg-gray-950 flex flex-col">
    <div class="max-w-7xl mx-auto px-6 md:px-12 flex flex-col md:flex-row gap-8 py-8 w-full flex-1">
        <!-- Dynamic Sidebar based on role -->
        <?php 
        if ($role === 'brand') include 'includes/brand_sidebar.php';
        elseif ($role === 'creator') include 'includes/creator_sidebar.php';
        ?>

        <main class="flex-1 flex flex-col bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-xl overflow-hidden h-[calc(100vh-12rem)] min-h-[600px]">
            <!-- Chat Header -->
            <header class="p-6 border-b border-gray-100 dark:border-gray-800 flex justify-between items-center bg-white/80 dark:bg-gray-900/80 backdrop-blur-xl sticky top-0 z-10">
                <div class="flex items-center gap-4">
                    <a href="<?php echo APP_URL; ?>messages.php" class="px-4 py-3 bg-gray-50 dark:bg-gray-800 rounded-2xl hover:bg-orange-50 transition text-gray-600 dark:text-gray-300 text-sm font-black">
                        ← Back
                    </a>
                    <div class="w-12 h-12 rounded-2xl overflow-hidden border-2 border-orange-100 dark:border-orange-900/30">
                        <img src="<?php echo $partner["photo"] ? APP_URL . $partner["photo"] : APP_URL . "assets/images/default-avatar.png"; ?>" class="w-full h-full object-cover">
                    </div>
                    <div>
                        <h3 class="font-black text-gray-900 dark:text-white"><?php echo e($partner["name"]); ?></h3>
                        <p class="text-[10px] font-bold text-green-500 uppercase tracking-widest flex items-center gap-1">
                            <span class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></span> Online
                        </p>
                    </div>
                </div>
            </header>

            <!-- Messages Area -->
            <div class="flex-1 overflow-y-auto p-6 space-y-6 bg-gray-50/30 dark:bg-transparent" id="messageContainer">
                <?php if (empty($messages)): ?>
                    <div class="h-full flex flex-col items-center justify-center text-center opacity-50">
                        <div class="text-6xl mb-4">👋</div>
                        <p class="font-bold text-gray-400">Say hello to <?php echo e($partner['name']); ?>!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($messages as $m): $is_me = $m["sender_id"] == $user_id; ?>
                        <div class="flex <?php echo $is_me ? "justify-end" : "justify-start"; ?>">
                            <div class="max-w-[80%] <?php echo $is_me ? "bg-orange-600 text-white rounded-t-3xl rounded-bl-3xl shadow-lg shadow-orange-600/20" : "bg-white dark:bg-gray-800 text-gray-900 dark:text-white rounded-t-3xl rounded-br-3xl border border-gray-100 dark:border-gray-700"; ?> p-5 relative group">
                                <p class="text-sm font-semibold leading-relaxed"><?php echo nl2br(e($m["message"])); ?></p>
                                <span class="text-[9px] font-black uppercase tracking-tighter mt-2 block opacity-50 <?php echo $is_me ? "text-white" : "text-gray-400"; ?>">
                                    <?php echo date("H:i", strtotime($m["created_at"])); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Message Input -->
            <footer class="p-6 border-t border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-900">
                <form method="POST" class="flex gap-4">
                    <input type="text" name="message" placeholder="Type your message here..." required autocomplete="off" class="flex-1 px-8 py-4 bg-gray-50 dark:bg-gray-800 border-2 border-transparent focus:border-orange-500 rounded-[1.5rem] outline-none transition-all font-medium text-gray-900 dark:text-white">
                    <button type="submit" class="w-14 h-14 bg-orange-600 text-white rounded-2xl flex items-center justify-center hover:scale-110 active:scale-95 transition-all shadow-lg shadow-orange-600/20 font-black" aria-label="Send message">
                        ➤
                    </button>
                </form>
            </footer>
        </main>
    </div>
</div>

<script>
    const messageContainer = document.getElementById("messageContainer");
    messageContainer.scrollTop = messageContainer.scrollHeight;
</script>

<?php include "includes/footer.php"; ?>
