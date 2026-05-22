<?php
require_once "../config/database.php";
require_once "../includes/functions.php";
require_role("admin");

$success = $_GET["success"] ?? "";
$error = $_GET["error"] ?? "";

// Handle User Actions (Suspend/Activate)
if (isset($_GET["action"]) && isset($_GET["user_id"])) {
    $action = $_GET["action"];
    $user_id = (int)$_GET["user_id"];

    if ($action === "suspend") {
        $stmt = $pdo->prepare("UPDATE users SET status = \"suspended\" WHERE id = ?");
        $stmt->execute([$user_id]);
        log_activity($_SESSION["user_id"], "User Suspended", "Suspended User ID: $user_id");
        header("Location: users.php?success=User Suspended");
        exit();
    }
    if ($action === "activate") {
        $stmt = $pdo->prepare("UPDATE users SET status = \"active\" WHERE id = ?");
        $stmt->execute([$user_id]);
        log_activity($_SESSION["user_id"], "User Activated", "Activated User ID: $user_id");
        header("Location: users.php?success=User Activated");
        exit();
    }

    if ($action === "verify_creator") {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE creators SET verification_status = 'verified' WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $stmt = $pdo->prepare("UPDATE creator_verifications SET status = 'verified' WHERE creator_id = (SELECT id FROM creators WHERE user_id = ?)");
            $stmt->execute([$user_id]);
            
            create_notification($user_id, "Verification Successful!", "Welcome! You can now apply for UGC campaigns.", "system", "creator/browse.php", "creator_dashboard", $user_id);
            
            $pdo->commit();
            log_activity($_SESSION["user_id"], "Creator Verified", "Verified User ID: $user_id");
            header("Location: users.php?success=Creator Verified");
            exit();
        } catch (Exception $e) { $pdo->rollBack(); $error = $e->getMessage(); }
    }
}

// Fetch Users with Profile Info
$stmt = $pdo->query("SELECT u.*, 
                    CASE 
                        WHEN u.role = \"brand\" THEN b.brand_name 
                        WHEN u.role = \"creator\" THEN cr.full_name 
                        ELSE \"Administrator\"
                    END as display_name,
                    CASE 
                        WHEN u.role = \"brand\" THEN b.logo 
                        WHEN u.role = \"creator\" THEN cr.profile_photo 
                        ELSE NULL
                    END as profile_img
                    FROM users u
                    LEFT JOIN brands b ON u.id = b.user_id
                    LEFT JOIN creators cr ON u.id = cr.user_id
                    ORDER BY u.created_at DESC");
$users = $stmt->fetchAll();

include "../includes/header.php";
?>

<div class="pt-24 min-h-screen bg-gray-50 dark:bg-gray-950">
    <div class="max-w-7xl mx-auto px-6 md:px-12 flex flex-col md:flex-row gap-8 py-8">
        <?php include "dashboard_sidebar.php"; ?>

        <main class="flex-1 space-y-8">
            <header class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm flex flex-col md:flex-row justify-between items-center gap-6">
                <div>
                    <h2 class="text-3xl font-black text-gray-900 dark:text-white">User Management</h2>
                    <p class="text-gray-500 font-bold mt-1">Control access for <?php echo count($users); ?> registered members.</p>
                </div>
                <div class="flex gap-4">
                    <button class="px-6 py-3 bg-gray-50 dark:bg-gray-800 text-gray-400 font-black rounded-xl text-xs uppercase tracking-widest border border-gray-100 dark:border-gray-700">Export CSV</button>
                </div>
            </header>

            <?php if ($success): ?>
                <div class="p-4 bg-green-50 text-green-700 font-bold rounded-2xl border border-green-100">✅ <?php echo e($success); ?></div>
            <?php endif; ?>

            <div class="grid grid-cols-1 gap-4">
                <?php foreach ($users as $u): ?>
                    <div class="p-6 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm flex items-center gap-6 group hover:border-orange-500/30 transition-all">
                        <div class="w-16 h-16 rounded-[1.5rem] overflow-hidden bg-gray-50 dark:bg-gray-800 border-2 border-gray-50 dark:border-gray-700">
                             <?php if ($u["profile_img"]): ?>
                                <img src="<?php echo APP_URL . $u["profile_img"]; ?>" class="w-full h-full object-cover">
                             <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center text-2xl">👤</div>
                             <?php endif; ?>
                        </div>

                        <div class="flex-1">
                            <h4 class="text-xl font-black text-gray-900 dark:text-white"><?php echo e($u["display_name"]); ?></h4>
                            <div class="flex items-center gap-4 mt-1">
                                <span class="text-[10px] font-black uppercase tracking-widest px-3 py-1 bg-gray-50 dark:bg-gray-800 rounded-full text-gray-500 border border-gray-100 dark:border-gray-700"><?php echo $u["role"]; ?></span>
                                <span class="text-xs font-bold text-gray-400"><?php echo e($u["email"]); ?></span>
                                <?php
                                if ($u["role"] === "creator") {
                                    $v_status = $pdo->prepare("SELECT * FROM creator_verifications WHERE creator_id = (SELECT id FROM creators WHERE user_id = ?)");
                                    $v_status->execute([$u["id"]]);
                                    $v_data = $v_status->fetch();
                                    
                                    if ($v_data && $v_data['status'] === "pending") {
                                        echo '<span class="px-2 py-0.5 bg-orange-100 text-orange-600 text-[9px] font-black uppercase rounded-full">Pending Verification</span>';
                                        if ($v_data['id_upload']) {
                                            echo '<a href="'.APP_URL.$v_data['id_upload'].'" target="_blank" class="text-[9px] text-blue-500 underline ml-2">View ID</a>';
                                        }
                                        if ($v_data['letter_upload']) {
                                            echo '<a href="'.APP_URL.$v_data['letter_upload'].'" target="_blank" class="text-[9px] text-blue-500 underline ml-2">View Letter</a>';
                                        }
                                    } elseif ($v_data && $v_data['status'] === 'verified') {
                                        echo '<span class="px-2 py-0.5 bg-green-100 text-green-600 text-[9px] font-black uppercase rounded-full">Verified Creator</span>';
                                    }
                                }
                                ?>
                            </div>
                        </div>

                        <div class="text-right flex items-center gap-4">
                            <?php if ($u["role"] === "creator" && ($v_data['status'] ?? "") === "pending"): ?>
                                <a href="?action=verify_creator&user_id=<?php echo $u["id"]; ?>" class="px-4 py-2 bg-orange-500 text-white text-[10px] font-black uppercase tracking-widest rounded-xl hover:bg-orange-600 transition shadow-lg shadow-orange-500/20">Verify Now</a>
                            <?php endif; ?>
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-widest mb-1 <?php echo $u["status"] === "suspended" ? "text-red-500" : "text-green-500"; ?>"><?php echo $u["status"]; ?></p>
                                <p class="text-xs font-bold text-gray-400"><?php echo date("M d, Y", strtotime($u["created_at"])); ?></p>
                            </div>
                            
                            <div class="flex gap-2">
                                <?php if ($u["status"] === "active"): ?>
                                    <a href="?action=suspend&user_id=<?php echo $u["id"]; ?>" onclick="return confirm(\"Suspend this user?\")" class="w-12 h-12 bg-red-50 text-red-500 rounded-2xl flex items-center justify-center hover:bg-red-500 hover:text-white transition-all shadow-sm">
                                        🚫
                                    </a>
                                <?php else: ?>
                                    <a href="?action=activate&user_id=<?php echo $u["id"]; ?>" onclick="return confirm(\"Activate this user?\")" class="w-12 h-12 bg-green-50 text-green-500 rounded-2xl flex items-center justify-center hover:bg-green-500 hover:text-white transition-all shadow-sm">
                                        ✅
                                    </a>
                                <?php endif; ?>
                                <button class="w-12 h-12 bg-gray-50 dark:bg-gray-800 text-gray-400 rounded-2xl flex items-center justify-center hover:bg-orange-600 hover:text-white transition-all shadow-sm">
                                    ⚙️
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </main>
    </div>
</div>

<?php include "../includes/footer.php"; ?>
