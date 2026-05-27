<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

if (is_logged_in()) {
    if ($_SESSION['role'] === 'brand') redirect('brand/dashboard.php');
    if ($_SESSION['role'] === 'creator') redirect('creator/dashboard.php');
    if ($_SESSION['role'] === 'admin') redirect('admin/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        if ($user['status'] === 'suspended') {
            $error = "Your account has been suspended. Please contact support.";
        } else {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['email'] = $user['email'];

            log_activity($user['id'], "Login", "User logged into the platform");

            if ($user['role'] === 'brand') redirect('brand/dashboard.php');
            if ($user['role'] === 'creator') redirect('creator/dashboard.php');
            if ($user['role'] === 'admin') redirect('admin/dashboard.php');
        }
    } else {
        $error = "Invalid email or password.";
    }
}

include 'includes/header.php';
?>

<main class="relative min-h-screen flex items-center justify-center py-20">
    <div aria-hidden="true" class="absolute inset-0 grid grid-cols-2 -space-x-52 opacity-40 dark:opacity-20 pointer-events-none">
        <div class="blur-[106px] h-56 bg-gradient-to-br from-primary to-purple-400 dark:from-blue-700"></div>
        <div class="blur-[106px] h-32 bg-gradient-to-r from-cyan-400 to-sky-300 dark:to-indigo-600"></div>
    </div>

    <div class="max-w-md w-full px-6 relative">
        <div class="bg-white dark:bg-gray-900 rounded-3xl border border-gray-100 dark:border-gray-800 shadow-2xl shadow-gray-600/10 p-8 md:p-12">
            <a href="<?php echo APP_URL; ?>index.php" class="block text-center text-2xl font-black text-gray-900 dark:text-white tracking-tighter mb-6">SPLEN<span class="text-primary italic">NET</span></a>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2 text-center">Welcome back</h2>
            <p class="text-gray-500 dark:text-gray-400 text-center text-sm mb-8">Sign in to your brand or creator account</p>

            <?php if ($error): ?>
                <div class="bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 p-4 rounded-xl text-sm mb-6 border border-red-100 dark:border-red-800">
                    <?php echo e($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Email Address</label>
                    <input type="email" name="email" required class="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Password</label>
                    <input type="password" name="password" required class="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition">
                </div>
                <button type="submit" class="relative flex h-12 w-full items-center justify-center px-8 before:absolute before:inset-0 before:rounded-full before:bg-primary before:transition before:duration-300 hover:before:scale-[1.02] active:duration-75 active:before:scale-95">
                    <span class="relative text-base font-semibold text-white">Login</span>
                </button>
            </form>
            
            <p class="mt-8 text-center text-gray-600 dark:text-gray-400">
                Don't have an account? <a href="register.php" class="text-primary font-semibold hover:underline">Register</a>
            </p>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
