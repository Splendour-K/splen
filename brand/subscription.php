<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_role('brand');

$stmt = $pdo->prepare("SELECT * FROM brands WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$brand = $stmt->fetch();
require_brand_record($brand);

$success = $_GET['success'] ?? "";

// Handle Upgrade (Mocked for now)
if (isset($_POST['upgrade_tier'])) {
    $new_tier = $_POST['tier'];
    $stmt = $pdo->prepare("UPDATE brands SET subscription_tier = ? WHERE id = ?");
    $stmt->execute([$new_tier, $brand['id']]);
    
    log_activity($_SESSION['user_id'], "Subscription Upgraded", "Upgraded to $new_tier tier");
    header("Location: subscription.php?success=Upgraded to " . ucfirst($new_tier) . " successfully!");
    exit();
}

$quota = check_brand_quota($brand['id']);

include '../includes/header.php';
?>

<div class="pt-24 min-h-screen bg-gray-50 dark:bg-gray-950">
    <div class="max-w-7xl mx-auto px-6 md:px-12 flex flex-col md:flex-row gap-8 py-8">
        <?php include '../includes/brand_sidebar.php'; ?>

        <main class="flex-1 space-y-8">
            <header class="p-8 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Subscription & Limits</h2>
                <p class="text-gray-600 dark:text-gray-400 mt-2">Manage your plan and usage quotas.</p>
            </header>

            <?php if ($success): ?>
                <div class="p-4 bg-green-100 dark:bg-green-900/30 border border-green-200 dark:border-green-800 rounded-xl text-green-800 dark:text-green-400 font-bold">
                    ✅ <?php echo e($success); ?>
                </div>
            <?php endif; ?>

            <!-- Current Usage -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm relative overflow-hidden">
                    <div class="relative">
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">Current Tier</p>
                        <h3 class="text-3xl font-black text-gray-900 dark:text-white uppercase tracking-tighter"><?php echo e($brand['subscription_tier']); ?></h3>
                        <p class="text-xs font-bold text-secondary mt-2">Resetting on the 1st of next month</p>
                    </div>
                </div>

                <div class="p-8 bg-gray-900 rounded-[2.5rem] text-white shadow-xl relative overflow-hidden">
                    <div class="relative">
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">Campaign Quota</p>
                        <h3 class="text-3xl font-black text-white"><?php echo $quota['used']; ?> / <?php echo $quota['limit']; ?></h3>
                        <div class="w-full bg-gray-800 h-2 rounded-full mt-4 overflow-hidden">
                            <div class="bg-secondary h-full" style="width: <?php echo ($quota['used'] / $quota['limit']) * 100; ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pricing / Upgrade Options -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Basic -->
                <div class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border <?php echo $brand['subscription_tier'] === 'basic' ? 'border-secondary shadow-lg' : 'border-gray-100 dark:border-gray-800'; ?> flex flex-col">
                    <h4 class="text-lg font-black text-gray-900 dark:text-white mb-2">Basic</h4>
                    <p class="text-4xl font-black text-gray-900 dark:text-white">$0<span class="text-sm font-bold text-gray-400">/mo</span></p>
                    <ul class="mt-8 space-y-4 flex-1">
                        <li class="flex items-center gap-2 text-sm font-medium text-gray-600 dark:text-gray-400">✅ <?php echo get_setting("basic_monthly_limit", 3); ?> Campaigns / month</li>
                        <li class="flex items-center gap-2 text-sm font-medium text-gray-600 dark:text-gray-400">✅ 10% Platform Fee</li>
                        <li class="flex items-center gap-2 text-sm font-medium text-gray-600 dark:text-gray-400">✅ Access to verified creators</li>
                    </ul>
                    <?php if ($brand['subscription_tier'] === 'basic'): ?>
                        <button disabled class="mt-8 w-full py-4 bg-gray-100 dark:bg-gray-800 text-gray-400 font-bold rounded-2xl cursor-not-allowed">Active Plan</button>
                    <?php else: ?>
                        <form method="POST">
                            <input type="hidden" name="tier" value="basic">
                            <button type="submit" name="upgrade_tier" class="mt-8 w-full py-4 border border-secondary text-secondary font-bold rounded-2xl hover:bg-secondary/5 transition-all">Downgrade</button>
                        </form>
                    <?php endif; ?>
                </div>

                <!-- Pro -->
                <div class="p-8 bg-gray-900 rounded-[2.5rem] border <?php echo $brand['subscription_tier'] === 'pro' ? 'border-secondary shadow-2xl' : 'border-transparent'; ?> flex flex-col relative text-white">
                    <div class="absolute top-4 right-8 px-3 py-1 bg-secondary text-white text-[9px] font-black uppercase rounded-full tracking-widest">Most Popular</div>
                    <h4 class="text-lg font-black text-white mb-2">Pro</h4>
                    <p class="text-4xl font-black text-white">$<?php echo get_setting("pro_tier_price", 49); ?><span class="text-sm font-bold text-gray-500">/mo</span></p>
                    <ul class="mt-8 space-y-4 flex-1">
                        <li class="flex items-center gap-2 text-sm font-medium text-gray-400">✅ <?php echo get_setting("pro_monthly_limit", 15); ?> Campaigns / month</li>
                        <li class="flex items-center gap-2 text-sm font-medium text-gray-400">✅ 5% Platform Fee</li>
                        <li class="flex items-center gap-2 text-sm font-medium text-gray-400">✅ Priority Support</li>
                        <li class="flex items-center gap-2 text-sm font-medium text-gray-400">✅ Analytics Dashboard</li>
                    </ul>
                    <?php if ($brand['subscription_tier'] === 'pro'): ?>
                        <button disabled class="mt-8 w-full py-4 bg-gray-800 text-gray-500 font-bold rounded-2xl cursor-not-allowed">Active Plan</button>
                    <?php else: ?>
                        <form method="POST">
                            <input type="hidden" name="tier" value="pro">
                            <button type="submit" name="upgrade_tier" class="mt-8 w-full py-4 bg-secondary text-white font-bold rounded-2xl shadow-xl shadow-secondary/20 hover:scale-[1.02] transition-all">Upgrade Now</button>
                        </form>
                    <?php endif; ?>
                </div>

                <!-- Enterprise -->
                <div class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border <?php echo $brand['subscription_tier'] === 'enterprise' ? 'border-secondary shadow-lg' : 'border-gray-100 dark:border-gray-800'; ?> flex flex-col">
                    <h4 class="text-lg font-black text-gray-900 dark:text-white mb-2">Enterprise</h4>
                    <p class="text-4xl font-black text-gray-900 dark:text-white">Custom</p>
                    <ul class="mt-8 space-y-4 flex-1">
                        <li class="flex items-center gap-2 text-sm font-medium text-gray-600 dark:text-gray-400">✅ Unlimited Campaigns</li>
                        <li class="flex items-center gap-2 text-sm font-medium text-gray-600 dark:text-gray-400">✅ 0% Platform Fee</li>
                        <li class="flex items-center gap-2 text-sm font-medium text-gray-600 dark:text-gray-400">✅ Dedicated Manager</li>
                        <li class="flex items-center gap-2 text-sm font-medium text-gray-600 dark:text-gray-400">✅ White-label Portal</li>
                    </ul>
                    <?php if ($brand['subscription_tier'] === 'enterprise'): ?>
                        <button disabled class="mt-8 w-full py-4 bg-gray-100 dark:bg-gray-800 text-gray-400 font-bold rounded-2xl cursor-not-allowed">Active Plan</button>
                    <?php else: ?>
                         <a href="<?php echo APP_URL; ?>brand/support.php" class="mt-8 w-full py-4 border border-gray-900 dark:border-white text-gray-900 dark:text-white text-center font-bold rounded-2xl hover:bg-gray-900 hover:text-white transition-all">Contact Sales</a>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>