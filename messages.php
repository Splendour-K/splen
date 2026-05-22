<?php
require_once "config/database.php";
require_once "includes/functions.php";
if (!is_logged_in()) redirect("login.php");

$user_id = $_SESSION["user_id"];
$role = $_SESSION["role"];

// Find if user is creator or brand to get their respective ID
if ($role === "creator") {
    $stmt = $pdo->prepare("SELECT id FROM creators WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $my_id = $stmt->fetchColumn();
    $id_field = "creator_id";
    $other_field = "brand_id";
} else {
    $stmt = $pdo->prepare("SELECT id FROM brands WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $my_id = $stmt->fetchColumn();
    $id_field = "brand_id";
    $other_field = "creator_id";
}

// Fetch Conversations
$conversations = [];
if ($my_id) {
    $sql = "SELECT c.*,
                   CASE WHEN c.brand_id = b.id AND ? = 'creator' THEN b.brand_name ELSE cr.full_name END as partner_name,
                   CASE WHEN ? = 'creator' THEN b.logo ELSE cr.profile_photo END as partner_photo
            FROM conversations c
            LEFT JOIN brands b ON c.brand_id = b.id
            LEFT JOIN creators cr ON c.creator_id = cr.id
            WHERE c.$id_field = ?
            ORDER BY c.updated_at DESC";

    $stmt_conv = $pdo->prepare($sql);
    $stmt_conv->execute([$role, $role, $my_id]);
    $conversations = $stmt_conv->fetchAll();
}

include "includes/header.php";
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
                    <h2 class="text-3xl font-black text-gray-900 dark:text-white">Messages</h2>
                    <p class="text-gray-500 font-bold mt-1">Chat with your partners and manage your projects.</p>
                </div>
                <div class="text-sm font-bold text-gray-400 uppercase tracking-widest bg-gray-50 dark:bg-gray-800 px-6 py-3 rounded-2xl border border-gray-100 dark:border-gray-800">
                    <?php echo count($conversations); ?> Conversations
                </div>
            </header>

            <div class="grid grid-cols-1 gap-4">
                <?php if (empty($conversations)): ?>
                    <div class="p-20 text-center bg-white dark:bg-gray-900 rounded-[3rem] border border-dashed border-gray-200 dark:border-gray-800">
                        <div class="text-6xl mb-6">💬</div>
                        <h3 class="text-2xl font-black text-gray-900 dark:text-white">No messages yet</h3>
                        <p class="text-gray-500 mt-2 max-w-xs mx-auto">Start looking for campaigns or applicants to begin a conversation.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($conversations as $c): ?>
                        <a href="view-message.php?id=<?php echo $c["id"]; ?>" class="group p-6 bg-white dark:bg-gray-900 hover:bg-orange-50 dark:hover:bg-orange-900/10 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm flex items-center gap-6 transition-all hover:scale-[1.01] hover:shadow-xl hover:shadow-orange-500/5">
                            <div class="w-16 h-16 rounded-[1.5rem] overflow-hidden border-2 border-gray-50 dark:border-gray-800 group-hover:border-orange-200 transition">
                                <img src="<?php echo $c["partner_photo"] ? APP_URL . $c["partner_photo"] : APP_URL . "assets/images/default-avatar.png"; ?>" class="w-full h-full object-cover">
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="text-lg font-black text-gray-900 dark:text-white group-hover:text-orange-600 transition truncate"><?php echo e($c["partner_name"]); ?></h4>
                                <p class="text-gray-500 dark:text-gray-400 text-sm font-medium mt-1 truncate">
                                    <?php echo $c["last_message"] ? e($c["last_message"]) : "New conversation started..."; ?>
                                </p>
                            </div>
                            <div class="text-[10px] font-bold text-gray-400 uppercase tracking-widest bg-gray-50 dark:bg-gray-800 px-3 py-1 rounded-full whitespace-nowrap hidden sm:block">
                                <?php echo time_ago($c["updated_at"]); ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<?php include "includes/footer.php"; ?>
