<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

if (is_logged_in()) {
    redirect('index.php');
}

$error = '';
$role = isset($_GET['role']) ? $_GET['role'] : 'creator';

// Valid brand invite code — change this value to invalidate old codes
define('BRAND_INVITE_CODE', '450272');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'];

    if ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif ($role === 'brand') {
        // Validate invite code for brand registration
        $invite_code = trim($_POST['invite_code'] ?? '');
        if ($invite_code !== BRAND_INVITE_CODE) {
            $error = "Invalid invite code. Please contact founders@splennet.com to receive a free invite code, or register as a Creator instead since Creator accounts are free.";
        }
    }

    if (!$error) {
        // Check if email exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = "Email already registered.";
        } else {
            $pdo->beginTransaction();
            try {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, role) VALUES (?, ?, ?)");
                $stmt->execute([$email, $password_hash, $role]);
                $user_id = $pdo->lastInsertId();

                if ($role === 'brand') {
                    $brand_name = $_POST['brand_name'] ?? '';
                    $contact_person = $_POST['contact_person'] ?? '';
                    $stmt = $pdo->prepare("INSERT INTO brands (user_id, brand_name, contact_person) VALUES (?, ?, ?)");
                    $stmt->execute([$user_id, $brand_name, $contact_person]);
                } else if ($role === 'creator') {
                    $full_name = $_POST['full_name'] ?? '';
                    $school = $_POST['school'] ?? '';
                    $stmt = $pdo->prepare("INSERT INTO creators (user_id, full_name, school) VALUES (?, ?, ?)");
                    $stmt->execute([$user_id, $full_name, $school]);
                }

                $pdo->commit();
                
                // Log them in
                $_SESSION['user_id'] = $user_id;
                $_SESSION['role'] = $role;
                $_SESSION['email'] = $email;

                if ($role === 'brand') redirect('brand/dashboard.php');
                if ($role === 'creator') redirect('creator/dashboard.php');
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Registration failed: " . $e->getMessage();
            }
        }
    }
}

include 'includes/header.php';
?>

<main class="relative min-h-screen py-20 flex items-center justify-center">
    <div aria-hidden="true" class="absolute inset-0 grid grid-cols-2 -space-x-52 opacity-40 dark:opacity-20 pointer-events-none">
        <div class="blur-[106px] h-56 bg-gradient-to-br from-primary to-purple-400 dark:from-blue-700"></div>
        <div class="blur-[106px] h-32 bg-gradient-to-r from-cyan-400 to-sky-300 dark:to-indigo-600"></div>
    </div>

    <div class="max-w-xl w-full px-6 relative">
        <div class="bg-white dark:bg-gray-900 rounded-3xl border border-gray-100 dark:border-gray-800 shadow-2xl shadow-gray-600/10 p-8 md:p-12">
            <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-2 text-center">Join Splennet</h2>
            <p class="text-gray-600 dark:text-gray-400 text-center mb-8">Start your UGC journey today</p>

            <?php if ($error): ?>
                <div class="bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 p-4 rounded-xl text-sm mb-6 border border-red-100 dark:border-red-800">
                    <?php echo e($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">I am a...</label>
                    <select name="role" id="role-selector" required onchange="toggleFields()" class="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition">
                        <option value="creator" <?php echo $role === 'creator' ? 'selected' : ''; ?>>Student Creator</option>
                        <option value="brand" <?php echo $role === 'brand' ? 'selected' : ''; ?>>Brand / Business</option>
                    </select>
                </div>

                <div id="creator-fields" class="space-y-6" style="<?php echo $role !== 'brand' ? 'display:block;' : 'display:none;'; ?>">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Full Name</label>
                        <input type="text" name="full_name" placeholder="John Doe" class="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">School</label>
                        <input type="text" name="school" placeholder="University of Ghana" class="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition">
                    </div>
                </div>

                <div id="brand-fields" class="space-y-6" style="<?php echo $role === 'brand' ? 'display:block;' : 'display:none;'; ?>">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Brand Name</label>
                        <input type="text" name="brand_name" placeholder="Splennet Labs" class="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Contact Person</label>
                        <input type="text" name="contact_person" placeholder="Jane Smith" class="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Invite Code <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="invite_code" placeholder="Enter your invite code" maxlength="20" class="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition font-mono tracking-widest" value="<?php echo e($_POST['invite_code'] ?? ''); ?>">
                        <p class="text-xs text-gray-500 mt-1.5">
                            Don't have an invite code? <a href="mailto:founders@splennet.com" class="text-primary font-semibold hover:underline">Contact founders@splennet.com</a> or <button type="button" onclick="document.getElementById('role-selector').value='creator';toggleFields();" class="text-primary font-semibold hover:underline">register as a Creator instead (free)</button>.
                        </p>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Email Address</label>
                    <input type="email" name="email" required placeholder="hello@example.com" class="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition">
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Password</label>
                        <input type="password" name="password" required class="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Confirm Password</label>
                        <input type="password" name="confirm_password" required class="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition">
                    </div>
                </div>

                <button type="submit" class="relative flex h-12 w-full items-center justify-center px-8 before:absolute before:inset-0 before:rounded-full before:bg-primary before:transition before:duration-300 hover:before:scale-[1.02] active:duration-75 active:before:scale-95">
                    <span class="relative text-base font-semibold text-white">Create Account</span>
                </button>
            </form>
            
            <p class="mt-8 text-center text-gray-600 dark:text-gray-400">
                Already have an account? <a href="login.php" class="text-primary font-semibold hover:underline">Login</a>
            </p>
        </div>
    </div>
</main>

<script>
function toggleFields() {
    const role = document.getElementById('role-selector').value;
    const creatorFields = document.getElementById('creator-fields');
    const brandFields = document.getElementById('brand-fields');

    if (role === 'brand') {
        brandFields.style.display = 'block';
        creatorFields.style.display = 'none';
        brandFields.querySelectorAll('input').forEach(i => i.required = true);
        creatorFields.querySelectorAll('input').forEach(i => i.required = false);
    } else {
        brandFields.style.display = 'none';
        creatorFields.style.display = 'block';
        brandFields.querySelectorAll('input').forEach(i => i.required = false);
        creatorFields.querySelectorAll('input').forEach(i => i.required = true);
    }
}
// Run once on load
toggleFields();
</script>

<?php include 'includes/footer.php'; ?>
