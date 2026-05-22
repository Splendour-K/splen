<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_role('creator');

$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT c.*, b.brand_name FROM campaigns c JOIN brands b ON c.brand_id = b.id WHERE c.id = ?");
$stmt->execute([$id]);
$camp = $stmt->fetch();

if (!$camp) redirect('creator/browse.php');

$stmt = $pdo->prepare("SELECT * FROM creators WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$creator = $stmt->fetch();
require_creator_record($creator);

$error = '';
$success = '';

// Check if already applied
$stmt_check = $pdo->prepare("SELECT status FROM applications WHERE campaign_id = ? AND creator_id = ?");
$stmt_check->execute([$id, $creator['id']]);
$existing_app = $stmt_check->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($existing_app) {
        $error = "You have already applied for this campaign.";
    } else {
        $message = $_POST['application_message'];
        $sample = $_POST['sample_video_link'];
        $delivery = $_POST['estimated_delivery_date'];

        $ins = $pdo->prepare("INSERT INTO applications (campaign_id, creator_id, application_message, sample_video_link, estimated_delivery_date) VALUES (?, ?, ?, ?, ?)");
        try {
            $ins->execute([$id, $creator['id'], $message, $sample, $delivery]);
            $success = "Application submitted successfully!";
            
            // Refresh application status
            $stmt_check->execute([$id, $creator['id']]);
            $existing_app = $stmt_check->fetch();
            
            // Optional: Create notification for brand
            $notify = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) SELECT user_id, 'New Application', ?, 'application' FROM brands WHERE id = ?");
            $notify->execute(["New application received for: " . $camp['title'], $camp['brand_id']]);
            
        } catch (Exception $e) {
            $error = "Failed to submit: " . $e->getMessage();
        }
    }
}

include '../includes/header.php';
?>

<div class="pt-24 min-h-screen bg-gray-50 dark:bg-gray-950">
    <div class="max-w-7xl mx-auto px-6 md:px-12 flex flex-col md:flex-row gap-8 py-8">
        <!-- Sidebar -->
        <?php include '../includes/creator_sidebar.php'; ?>

        <!-- Main Content -->
        <main class="flex-1">
            <a href="<?php echo APP_URL; ?>creator/browse.php" class="inline-flex items-center text-sm font-bold text-gray-500 hover:text-primary transition mb-8">
                <span class="mr-2">←</span> Back to Browse
            </a>

            <?php if ($success): ?>
                <div class="mb-8 p-6 bg-green-100 dark:bg-green-900/30 border border-green-200 dark:border-green-800 rounded-2xl text-green-800 dark:text-green-400 font-bold flex items-center">
                    <span class="mr-3 text-xl">✅</span> <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="mb-8 p-6 bg-red-100 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-2xl text-red-800 dark:text-red-400 font-bold flex items-center">
                    <span class="mr-3 text-xl">⚠️</span> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Left: Brief Details -->
                <div class="lg:col-span-2 space-y-8">
                    <section class="p-8 md:p-12 bg-white dark:bg-gray-900 rounded-[3rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                        <header class="mb-10">
                            <span class="px-4 py-1 bg-primary/10 text-primary text-xs font-bold rounded-full uppercase tracking-widest"><?php echo e($camp['category']); ?></span>
                            <h1 class="text-4xl md:text-5xl font-black text-gray-900 dark:text-white mt-6"><?php echo e($camp['title']); ?></h1>
                            <p class="text-xl text-gray-500 mt-4 font-medium">Brand: <span class="text-gray-900 dark:text-white"><?php echo e($camp['brand_name']); ?></span></p>
                        </header>

                        <div class="space-y-10">
                            <div>
                                <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-4 flex items-center">
                                    <span class="w-8 h-8 bg-primary/10 rounded-lg flex items-center justify-center mr-3 text-sm">📝</span>
                                    The Brief
                                </h3>
                                <div class="text-gray-700 dark:text-gray-300 leading-relaxed text-lg prose dark:prose-invert max-w-none">
                                    <?php echo nl2br(e($camp['main_message'])); ?>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 py-8 border-y border-gray-100 dark:border-gray-800">
                                <div>
                                    <h4 class="font-bold text-gray-900 dark:text-white mb-4 uppercase tracking-wider text-xs">Video Requirements</h4>
                                    <ul class="space-y-3 text-gray-600 dark:text-gray-400 font-medium">
                                        <li class="flex items-center"><span class="text-primary mr-2">✓</span> Type: <?php echo e($camp['video_type']); ?></li>
                                        <li class="flex items-center"><span class="text-primary mr-2">✓</span> Length: <?php echo e($camp['video_length']); ?></li>
                                        <li class="flex items-center"><span class="text-primary mr-2">✓</span> Revisions: Max <?php echo $camp['revision_limit']; ?></li>
                                    </ul>
                                </div>
                                <div>
                                    <h4 class="font-bold text-gray-900 dark:text-white mb-4 uppercase tracking-wider text-xs">Usage Rights</h4>
                                    <div class="p-4 rounded-2xl bg-secondary/5 border border-secondary/10">
                                        <p class="text-secondary font-black capitalize text-lg"><?php echo e($camp['usage_rights_package']); ?> Rights</p>
                                        <p class="text-sm text-gray-500 mt-1">
                                            <?php 
                                            if ($camp['usage_rights_package'] === 'basic') echo "Organic social media for 6 months.";
                                            if ($camp['usage_rights_package'] === 'ad') echo "Social media + Paid ads for 12 months.";
                                            if ($camp['usage_rights_package'] === 'full') echo "Usage across all channels long-term.";
                                            ?>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-4 flex items-center">
                                    <span class="w-8 h-8 bg-primary/10 rounded-lg flex items-center justify-center mr-3 text-sm">🗣️</span>
                                    Key Messaging / Script
                                </h3>
                                <div class="p-8 bg-gray-50 dark:bg-gray-800/50 rounded-[2rem] border-2 border-dashed border-gray-200 dark:border-gray-700 italic text-gray-700 dark:text-gray-300 text-lg text-center relative">
                                    <span class="absolute top-4 left-4 text-4xl text-primary/20 leading-none">“</span>
                                    <?php echo nl2br(e($camp['words_to_say'])); ?>
                                    <span class="absolute bottom-4 right-4 text-4xl text-primary/20 leading-none">”</span>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- Right: Application Form -->
                <aside class="space-y-8">
                    <div class="p-8 bg-white dark:bg-gray-900 rounded-[3rem] border-4 border-primary shadow-2xl relative overflow-hidden">
                        <div class="absolute -top-12 -right-12 w-32 h-32 bg-primary/10 rounded-full blur-3xl"></div>
                        
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Creator Payout</h3>
                        <div class="text-5xl font-black text-primary mb-8"><?php echo $camp['currency']; ?> <?php echo number_format($camp['budget_per_creator'], 0); ?></div>

                        <?php if ($existing_app): ?>
                            <div class="p-8 bg-gray-50 dark:bg-gray-800 border-2 border-gray-100 dark:border-gray-700 rounded-[2rem] text-center">
                                <div class="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl">📝</div>
                                <p class="text-gray-900 dark:text-white font-bold">Application Submitted</p>
                                <p class="text-sm text-gray-500 mt-2">You applied on <?php echo date('M d'); ?>. Your status is currently <span class="font-bold text-primary uppercase text-xs"><?php echo $existing_app['status']; ?></span>.</p>
                                <a href="<?php echo APP_URL; ?>creator/my-applications.php" class="inline-block mt-6 text-sm font-bold text-primary hover:underline">Track Application →</a>
                            </div>
                        <?php elseif ($creator['verification_status'] !== 'verified'): ?>
                            <div class="p-6 bg-orange-50 dark:bg-orange-900/20 border border-orange-100 dark:border-orange-800 rounded-2xl text-orange-800 dark:text-orange-400 text-sm font-medium mb-6">
                                You must be verified to apply for this campaign.
                                <a href="<?php echo APP_URL; ?>creator/verification.php" class="block mt-2 font-bold underline">Start Verification →</a>
                            </div>
                        <?php else: ?>
                            <form method="POST" id="apply" class="space-y-6">
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2 ml-1">Your Pitch</label>
                                    <textarea name="application_message" rows="4" required class="w-full px-5 py-4 bg-gray-50 dark:bg-gray-800 border-2 border-transparent border-gray-100 dark:border-gray-700 focus:border-primary focus:bg-white dark:focus:bg-gray-900 rounded-2xl transition-all outline-none font-medium text-gray-900 dark:text-white placeholder:text-gray-400" placeholder="Tell the brand why you're perfect for this..."></textarea>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2 ml-1">Portfolio/Sample Link</label>
                                    <input type="url" name="sample_video_link" required class="w-full px-5 py-4 bg-gray-50 dark:bg-gray-800 border-2 border-transparent border-gray-100 dark:border-gray-700 focus:border-primary focus:bg-white dark:focus:bg-gray-900 rounded-2xl transition-all outline-none font-medium text-gray-900 dark:text-white placeholder:text-gray-400" placeholder="https://tiktok.com/@yourprofile">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2 ml-1">Estimated Delivery</label>
                                    <input type="date" name="estimated_delivery_date" required class="w-full px-5 py-4 bg-gray-50 dark:bg-gray-800 border-2 border-transparent border-gray-100 dark:border-gray-700 focus:border-primary focus:bg-white dark:focus:bg-gray-900 rounded-2xl transition-all outline-none font-medium text-gray-900 dark:text-white" min="<?php echo date('Y-m-d'); ?>">
                                </div>

                                <p class="text-[10px] text-gray-400 px-1">By applying, you agree to the usage rights and brief requirements of this campaign.</p>

                                <button type="submit" class="w-full py-5 bg-gray-900 dark:bg-white text-white dark:text-gray-900 text-lg font-black rounded-2xl shadow-xl hover:bg-primary hover:text-white dark:hover:bg-primary transition-all">
                                    Submit Application
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                    </div>

                    <div class="p-8 bg-gray-900 rounded-[2.5rem] text-white">
                        <h4 class="font-bold text-lg mb-4">Quick Tip</h4>
                        <p class="text-gray-400 text-sm leading-relaxed">Brands love creators who mention specific ideas for their brand. Be creative in your pitch!</p>
                    </div>
                </aside>
            </div>
        </main>
</div>

<?php 
include '../includes/footer.php';
?>
