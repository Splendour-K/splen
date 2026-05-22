<?php
require_once "../config/database.php";
require_once "../includes/functions.php";
require_role("admin");

$user_id = $_GET["user_id"] ?? null;
if (!$user_id) {
    header("Location: users.php");
    exit();
}

$success = "";
$error = "";

// Handle User Management Actions
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_user"])) {
    $new_role = $_POST["role"];
    $new_tier = $_POST["subscription_tier"] ?? null;
    $new_status = $_POST["status"];

    $pdo->beginTransaction();
    try {
        // Update user basics
        $stmt = $pdo->prepare("UPDATE users SET role = ?, status = ? WHERE id = ?");
        $stmt->execute([$new_role, $new_status, $user_id]);

        // If user is brand, update subscription tier
        if ($new_role === "brand" && $new_tier) {
            $stmt = $pdo->prepare("UPDATE brands SET subscription_tier = ? WHERE user_id = ?");
            $stmt->execute([$new_tier, $user_id]);
        }

        $pdo->commit();
        log_activity($_SESSION["user_id"], "User Managed", "Updated User ID: $user_id. Role: $new_role, Status: $new_status");
        $success = "User settings updated successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error updating user: " . $e->getMessage();
    }
}

// Handle Password Reset
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["reset_password"])) {
    $new_pass = password_hash($_POST["new_password"], PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    if ($stmt->execute([$new_pass, $user_id])) {
        log_activity($_SESSION["user_id"], "Password Reset", "Manual reset for User ID: $user_id");
        $success = "Password has been reset successfully.";
    } else {
        $error = "Failed to reset password.";
    }
}

// Fetch User Data
$stmt = $pdo->prepare("SELECT u.*, 
                    b.brand_name, b.subscription_tier,
                    cr.full_name as creator_name
                    FROM users u
                    LEFT JOIN brands b ON u.id = b.user_id
                    LEFT JOIN creators cr ON u.id = cr.user_id
                    WHERE u.id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header("Location: users.php");
    exit();
}

include "../includes/header.php";
?>

<div class="pt-24 min-h-screen bg-gray-50 dark:bg-gray-950">
    <div class="max-w-7xl mx-auto px-6 md:px-12 flex flex-col md:flex-row gap-8 py-8 w-full">
        <?php include "dashboard_sidebar.php"; ?>

        <main class="flex-1 space-y-8">
            <header class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm relative overflow-hidden">
                <div aria-hidden="true" class="absolute top-0 right-0 -mr-16 -mt-16 w-64 h-64 bg-orange-500/5 rounded-full blur-3xl"></div>
                <div class="relative flex items-center justify-between">
                    <div>
                        <h2 class="text-3xl font-black text-gray-900 dark:text-white">Manage User</h2>
                        <p class="text-gray-500 font-bold mt-1">Editing settings for <?php echo e($user['email']); ?></p>
                    </div>
                    <a href="users.php" class="px-6 py-3 bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 font-bold rounded-xl hover:bg-gray-200 transition-all flex items-center gap-2">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                        Back to List
                    </a>
                </div>
            </header>

            <?php if ($success): ?>
                <div class="p-4 bg-green-50 text-green-700 font-bold rounded-2xl border border-green-100 italic">✨ <?php echo $success; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="p-4 bg-red-50 text-red-700 font-bold rounded-2xl border border-red-100 italic">⚠️ <?php echo $error; ?></div>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Profile Overview -->
                <section class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm space-y-6">
                    <div class="flex items-center gap-4 mb-4">
                        <span class="w-12 h-12 rounded-2xl bg-orange-50 dark:bg-orange-950/20 text-orange-600 flex items-center justify-center font-bold">
                            <i data-lucide="user" class="w-6 h-6"></i>
                        </span>
                        <div>
                            <h3 class="text-xl font-black text-gray-900 dark:text-white uppercase tracking-tighter">Profile Info</h3>
                            <p class="text-[10px] text-gray-400 font-black uppercase tracking-widest leading-none">Registered member details</p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-2xl border border-gray-100 dark:border-gray-800">
                            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Full Name / Brand Name</p>
                            <p class="text-sm font-bold text-gray-900 dark:text-white"><?php echo e($user['creator_name'] ?: ($user['brand_name'] ?: 'Not Set')); ?></p>
                        </div>
                        <div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-2xl border border-gray-100 dark:border-gray-800">
                            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Email Address</p>
                            <p class="text-sm font-bold text-gray-900 dark:text-white"><?php echo e($user['email']); ?></p>
                        </div>
                        <div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-2xl border border-gray-100 dark:border-gray-800">
                            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Account Created</p>
                            <p class="text-sm font-bold text-gray-900 dark:text-white"><?php echo date("F j, Y @ H:i", strtotime($user['created_at'])); ?></p>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-2xl border border-gray-100 dark:border-gray-800">
                                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Role</p>
                                <span class="px-3 py-1 bg-orange-500 text-white text-[10px] font-black uppercase rounded-full"><?php echo $user['role']; ?></span>
                            </div>
                            <div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-2xl border border-gray-100 dark:border-gray-800">
                                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Status</p>
                                <span class="px-3 py-1 <?php echo $user['status'] === 'active' ? 'bg-green-500' : 'bg-red-500'; ?> text-white text-[10px] font-black uppercase rounded-full"><?php echo $user['status']; ?></span>
                            </div>
                        </div>
                        
                        <div class="pt-6">
                            <button onclick="document.getElementById('passwordResetModal').classList.remove('hidden')" class="w-full py-4 bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 font-bold rounded-2xl border border-gray-200 dark:border-gray-800 hover:bg-gray-50 transition-all flex items-center justify-center gap-2">
                                <i data-lucide="key-round" class="w-4 h-4"></i>
                                Reset User Password
                            </button>
                        </div>
                    </div>
                </section>

                <!-- Management Form -->
                <form method="POST" class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm space-y-8">
                    <div class="flex items-center gap-4 mb-4">
                        <span class="w-12 h-12 rounded-2xl bg-blue-50 dark:bg-blue-950/20 text-blue-600 flex items-center justify-center font-bold">
                            <i data-lucide="edit-3" class="w-6 h-6"></i>
                        </span>
                        <div>
                            <h3 class="text-xl font-black text-gray-900 dark:text-white uppercase tracking-tighter">Update Account</h3>
                            <p class="text-[10px] text-gray-400 font-black uppercase tracking-widest leading-none">Modify permissions and status</p>
                        </div>
                    </div>

                    <div class="space-y-6">
                        <div class="space-y-2">
                            <label class="text-[10px] font-black uppercase tracking-widest text-gray-400 ml-4">Account Status</label>
                            <select name="status" class="w-full px-6 py-4 bg-gray-50 dark:bg-gray-800 border-none rounded-2xl text-sm font-bold focus:ring-2 focus:ring-orange-500 transition-all">
                                <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>Active (Full Access)</option>
                                <option value="suspended" <?php echo $user['status'] === 'suspended' ? 'selected' : ''; ?>>Suspended (No Access)</option>
                                <option value="pending" <?php echo $user['status'] === 'pending' ? 'selected' : ''; ?>>Pending (Verification Needed)</option>
                            </select>
                        </div>

                        <div class="space-y-2">
                            <label class="text-[10px] font-black uppercase tracking-widest text-gray-400 ml-4">System Role</label>
                            <select name="role" class="w-full px-6 py-4 bg-gray-50 dark:bg-gray-800 border-none rounded-2xl text-sm font-bold focus:ring-2 focus:ring-orange-500 transition-all">
                                <option value="creator" <?php echo $user['role'] === 'creator' ? 'selected' : ''; ?>>Creator (UGC Talent)</option>
                                <option value="brand" <?php echo $user['role'] === 'brand' ? 'selected' : ''; ?>>Brand (Customer)</option>
                                <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Administrator (Staff)</option>
                            </select>
                        </div>

                        <?php if ($user['role'] === 'brand'): ?>
                        <div class="space-y-2">
                            <label class="text-[10px] font-black uppercase tracking-widest text-gray-400 ml-4">Subscription Tier</label>
                            <select name="subscription_tier" class="w-full px-6 py-4 bg-gray-50 dark:bg-gray-800 border-none rounded-2xl text-sm font-bold focus:ring-2 focus:ring-orange-500 transition-all text-purple-600">
                                <option value="free" <?php echo $user['subscription_tier'] === 'free' ? 'selected' : ''; ?>>Free Plan</option>
                                <option value="pro" <?php echo $user['subscription_tier'] === 'pro' ? 'selected' : ''; ?>>Pro Plan</option>
                                <option value="enterprise" <?php echo $user['subscription_tier'] === 'enterprise' ? 'selected' : ''; ?>>Enterprise Plan</option>
                            </select>
                        </div>
                        <?php endif; ?>

                        <div class="pt-4">
                            <button type="submit" name="update_user" class="w-full py-4 bg-gray-900 dark:bg-white text-white dark:text-gray-900 font-black rounded-2xl shadow-xl hover:scale-[1.02] active:scale-[0.98] transition-all uppercase tracking-widest text-xs">
                                Save All Changes
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Recent Activity for this User -->
            <section class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                <div class="flex items-center gap-4 mb-6">
                    <span class="w-12 h-12 rounded-2xl bg-purple-50 dark:bg-purple-950/20 text-purple-600 flex items-center justify-center font-bold">
                        <i data-lucide="clock" class="w-6 h-6"></i>
                    </span>
                    <div>
                        <h3 class="text-xl font-black text-gray-900 dark:text-white uppercase tracking-tighter">User Activity</h3>
                        <p class="text-[10px] text-gray-400 font-black uppercase tracking-widest leading-none">Last 10 events for this account</p>
                    </div>
                </div>

                <div class="divide-y divide-gray-50 dark:divide-gray-800">
                    <?php
                    $stmt = $pdo->prepare("SELECT * FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
                    $stmt->execute([$user_id]);
                    $user_logs = $stmt->fetchAll();
                    
                    foreach ($user_logs as $log):
                    ?>
                        <div class="py-4 flex justify-between items-center">
                            <div>
                                <p class="text-sm font-bold text-gray-900 dark:text-white"><?php echo e($log['action']); ?></p>
                                <p class="text-xs text-gray-500"><?php echo e($log['details']); ?></p>
                            </div>
                            <p class="text-[10px] font-bold text-gray-400"><?php echo date("M d, H:i", strtotime($log['created_at'])); ?></p>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($user_logs)): ?>
                        <div class="py-12 text-center text-gray-400 font-bold uppercase tracking-widest text-xs italic">No activity recorded.</div>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>
</div>

<!-- Simple Hidden Modal for Password Reset -->
<div id="passwordResetModal" class="hidden fixed inset-0 bg-gray-950/80 backdrop-blur-sm z-50 flex items-center justify-center p-6">
    <div class="bg-white dark:bg-gray-900 w-full max-w-md p-8 rounded-[2.5rem] shadow-2xl space-y-6">
        <h3 class="text-2xl font-black text-gray-900 dark:text-white uppercase tracking-tighter">Reset Password</h3>
        <p class="text-gray-500 font-bold">Are you sure you want to reset the password for <span class="text-orange-500"><?php echo e($user['email']); ?></span>?</p>
        
        <form method="POST">
            <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
            <div class="space-y-4">
                <input type="text" name="new_password" placeholder="New temporary password" class="w-full px-6 py-4 bg-gray-50 dark:bg-gray-800 border-none rounded-2xl text-sm font-bold focus:ring-2 focus:ring-orange-500" required>
                <div class="flex gap-4">
                    <button type="button" onclick="document.getElementById('passwordResetModal').classList.add('hidden')" class="flex-1 py-4 bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 font-bold rounded-2xl">Cancel</button>
                    <button type="submit" name="reset_password" class="flex-1 py-4 bg-orange-500 text-white font-black rounded-2xl uppercase tracking-widest text-xs">Reset Now</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include "../includes/footer.php"; ?>
                        <div class="flex justify-between items-center py-3 border-b border-gray-50 dark:border-gray-800">
                            <span class="text-xs font-black text-gray-400 uppercase tracking-widest">Full Name/Brand</span>
                            <span class="text-sm font-bold text-gray-900 dark:text-white"><?php echo e($user['display_name'] ?? $user['brand_name'] ?? $user['creator_name'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="flex justify-between items-center py-3 border-b border-gray-50 dark:border-gray-800">
                            <span class="text-xs font-black text-gray-400 uppercase tracking-widest">Email Address</span>
                            <span class="text-sm font-bold text-gray-900 dark:text-white"><?php echo e($user['email']); ?></span>
                        </div>
                        <div class="flex justify-between items-center py-3 border-b border-gray-50 dark:border-gray-800">
                            <span class="text-xs font-black text-gray-400 uppercase tracking-widest">Joined On</span>
                            <span class="text-sm font-bold text-gray-900 dark:text-white"><?php echo date("F d, Y", strtotime($user['created_at'])); ?></span>
                        </div>
                    </div>
                </section>

                <!-- Management Form -->
                <section class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                    <form method="POST" class="space-y-6">
                        <div class="flex items-center gap-4 mb-6">
                            <span class="w-12 h-12 rounded-2xl bg-blue-50 dark:bg-blue-950/20 text-blue-600 flex items-center justify-center font-bold">
                                <i data-lucide="lock" class="w-6 h-6"></i>
                            </span>
                            <div>
                                <h3 class="text-xl font-black text-gray-900 dark:text-white uppercase tracking-tighter">Permissions</h3>
                                <p class="text-[10px] text-gray-400 font-black uppercase tracking-widest leading-none">Access control settings</p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3 ml-2">Account Role</label>
                            <select name="role" class="w-full px-6 py-4 bg-gray-50 dark:bg-gray-800 border-2 border-transparent focus:border-orange-500 rounded-2xl outline-none transition-all font-bold dark:text-white">
                                <option value="creator" <?php echo $user['role'] === 'creator' ? 'selected' : ''; ?>>Creator</option>
                                <option value="brand" <?php echo $user['role'] === 'brand' ? 'selected' : ''; ?>>Brand</option>
                                <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Administrator</option>
                            </select>
                        </div>

                        <?php if ($user['role'] === 'brand' || isset($user['subscription_tier'])): ?>
                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3 ml-2">Subscription Tier</label>
                            <select name="subscription_tier" class="w-full px-6 py-4 bg-gray-50 dark:bg-gray-800 border-2 border-transparent focus:border-orange-500 rounded-2xl outline-none transition-all font-bold dark:text-white">
                                <option value="basic" <?php echo ($user['subscription_tier'] ?? 'basic') === 'basic' ? 'selected' : ''; ?>>Basic</option>
                                <option value="pro" <?php echo ($user['subscription_tier'] ?? 'basic') === 'pro' ? 'selected' : ''; ?>>Pro Plan</option>
                                <option value="enterprise" <?php echo ($user['subscription_tier'] ?? 'basic') === 'enterprise' ? 'selected' : ''; ?>>Enterprise</option>
                            </select>
                        </div>
                        <?php endif; ?>

                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3 ml-2">Account Status</label>
                            <select name="status" class="w-full px-6 py-4 bg-gray-50 dark:bg-gray-800 border-2 border-transparent focus:border-orange-500 rounded-2xl outline-none transition-all font-bold dark:text-white">
                                <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="suspended" <?php echo $user['status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                            </select>
                        </div>

                        <button type="submit" name="update_user" class="w-full py-4 bg-gray-900 dark:bg-white text-white dark:text-gray-950 font-black rounded-2xl hover:scale-[1.02] active:scale-95 transition-all shadow-xl shadow-gray-900/10 uppercase tracking-widest text-xs">
                            Update User Settings
                        </button>
                    </form>
                </section>
            </div>

            <!-- More Management Features -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm group hover:border-red-500/50 transition-all">
                    <div class="flex flex-col items-center text-center space-y-4">
                        <div class="w-16 h-16 rounded-2xl bg-red-50 text-red-500 flex items-center justify-center">
                            <i data-lucide="trash-2" class="w-8 h-8"></i>
                        </div>
                        <h4 class="text-lg font-black text-gray-900 dark:text-white">Delete User</h4>
                        <p class="text-xs text-gray-500 font-bold">Permanently remove this user and all associated data. Use with caution.</p>
                        <button onclick="confirmDelete()" class="px-6 py-2 bg-red-500 text-white font-black text-[10px] uppercase tracking-widest rounded-xl">Delete Forever</button>
                    </div>
                </div>

                <div class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm group hover:border-primary/50 transition-all">
                    <div class="flex flex-col items-center text-center space-y-4">
                        <div class="w-16 h-16 rounded-2xl bg-blue-50 text-primary flex items-center justify-center">
                            <i data-lucide="mail" class="w-8 h-8"></i>
                        </div>
                        <h4 class="text-lg font-black text-gray-900 dark:text-white">Send Notice</h4>
                        <p class="text-xs text-gray-500 font-bold">Email this user a custom notification regarding their account status.</p>
                        <button class="px-6 py-2 bg-primary text-white font-black text-[10px] uppercase tracking-widest rounded-xl">Write Email</button>
                    </div>
                </div>

                <div class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm group hover:border-orange-500/50 transition-all">
                    <div class="flex flex-col items-center text-center space-y-4">
                        <div class="w-16 h-16 rounded-2xl bg-orange-50 text-orange-600 flex items-center justify-center">
                            <i data-lucide="history" class="w-8 h-8"></i>
                        </div>
                        <h4 class="text-lg font-black text-gray-900 dark:text-white">Activity Logs</h4>
                        <p class="text-xs text-gray-500 font-bold">View full event history and logs associated with this specific user.</p>
                        <button class="px-6 py-2 bg-orange-600 text-white font-black text-[10px] uppercase tracking-widest rounded-xl">View Logs</button>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
    function confirmDelete() {
        if (confirm("CRITICAL: Are you absolutely sure you want to delete this user? This action CANNOT BE UNDONE and will remove all campaigns, earnings, and profile data.")) {
            window.location.href = "users.php?action=delete&user_id=<?php echo $user_id; ?>";
        }
    }
</script>

<?php include "../includes/footer.php"; ?>