<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_role('brand');

$stmt = $pdo->prepare("SELECT * FROM brands WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$brand = $stmt->fetch();

// Actions (Approve/Reject)
if (isset($_GET['action']) && isset($_GET['app_id'])) {
    $action = $_GET['action'];
    $app_id = $_GET['app_id'];

    if ($action === 'approve') {
        // Start a job
        $pdo->beginTransaction();
        try {
            $upd = $pdo->prepare("UPDATE applications SET status = 'approved' WHERE id = ?");
            $upd->execute([$app_id]);

            $stmt_app = $pdo->prepare("SELECT a.*, c.user_id as creator_user_id FROM applications a JOIN creators c ON a.creator_id = c.id WHERE a.id = ?");
            $stmt_app->execute([$app_id]);
            $app_data = $stmt_app->fetch();

            $stmt_camp = $pdo->prepare("SELECT * FROM campaigns WHERE id = ?");
            $stmt_camp->execute([$app_data['campaign_id']]);
            $camp_data = $stmt_camp->fetch();

            $ins_job = $pdo->prepare("INSERT INTO jobs (application_id, campaign_id, brand_id, creator_id) VALUES (?, ?, ?, ?)");
            $ins_job->execute([$app_id, $app_data['campaign_id'], $brand['id'], $app_data['creator_id']]);
            $job_id = $pdo->lastInsertId();

            // Create Payment Record (V2 compliant)
            $pay_type = ($camp_data['order_type'] === 'performance_campaign') ? 'performance_views' : 'fixed_ugc';
            $amount = ($camp_data['order_type'] === 'direct_ugc') ? $camp_data['budget_per_creator'] : 0;
            
            $ins_pay = $pdo->prepare("INSERT INTO payments (job_id, creator_id, amount, status, payment_type, p_pay_per_1000_views, p_max_payable_views) VALUES (?, ?, ?, 'pending', ?, ?, ?)");
            $ins_pay->execute([
                $job_id, 
                $app_data['creator_id'], 
                $amount, 
                $pay_type, 
                $camp_data['pay_per_1000_views'], 
                $camp_data['max_payable_views']
            ]);

            // Create notification for creator
            $notify = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, 'Application Approved!', ?, 'job')");
            $notify->execute([$app_data['creator_user_id'], "Congratulations! Your application for " . $camp_data['title'] . " has been approved. You can now start working!"]);

            // Create or get conversation
            $check_conv = $pdo->prepare("SELECT id FROM conversations WHERE brand_id = ? AND creator_id = ?");
            $check_conv->execute([$brand['id'], $app_data['creator_id']]);
            $conv = $check_conv->fetch();

            if (!$conv) {
                $ins_conv = $pdo->prepare("INSERT INTO conversations (brand_id, creator_id, last_message) VALUES (?, ?, 'Conversation started')");
                $ins_conv->execute([$brand['id'], $app_data['creator_id']]);
                $conv_id = $pdo->lastInsertId();
            } else {
                $conv_id = $conv['id'];
            }

            // Initial message
            $ins_msg = $pdo->prepare("INSERT INTO messages (conversation_id, sender_id, receiver_id, message) VALUES (?, ?, ?, ?)");
            $ins_msg->execute([$conv_id, $_SESSION['user_id'], $app_data['creator_user_id'], "Hi! I've approved your application for " . $camp_data['title'] . ". Let's get to work!"]);

            $pdo->commit();
            redirect('brand/applications.php?success=1');
            exit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }

    if ($action === 'reject') {
        $upd = $pdo->prepare("UPDATE applications SET status = 'rejected' WHERE id = ?");
        $upd->execute([$app_id]);
        redirect('brand/applications.php?rejected=1');
        exit();
    }
}

// Fetch applications
$stmt = $pdo->prepare("
    SELECT a.*, c.full_name, c.school, c.main_niche, camp.title as campaign_title
    FROM applications a
    JOIN creators c ON a.creator_id = c.id
    JOIN campaigns camp ON a.campaign_id = camp.id
    WHERE camp.brand_id = ? AND a.status = 'pending'
    ORDER BY a.created_at DESC
");
$stmt->execute([$brand['id']]);
$applications = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="pt-24 min-h-screen bg-gray-50 dark:bg-gray-950">
    <div class="max-w-7xl mx-auto px-6 md:px-12 flex flex-col md:flex-row gap-8 py-8">
        <!-- Sidebar -->
        <?php include '../includes/brand_sidebar.php'; ?>

        <!-- Main Content -->
        <main class="flex-1 space-y-8">
            <header class="p-8 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm relative overflow-hidden">
                <div aria-hidden="true" class="absolute top-0 right-0 -mr-16 -mt-16 w-64 h-64 bg-secondary/5 rounded-full blur-3xl"></div>
                <div class="relative">
                    <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Pending Applications</h2>
                    <p class="text-gray-600 dark:text-gray-400 mt-2">Review creators who want to work with you.</p>
                </div>
            </header>

            <?php if (isset($_GET['success'])): ?>
                <div class="p-4 bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-400 rounded-2xl border border-green-200 dark:border-green-800 flex items-center gap-3">
                    <span class="text-xl">✅</span>
                    <span class="font-bold">Creator approved! A job record has been created.</span>
                </div>
            <?php endif; ?>

            <?php if (empty($applications)): ?>
                <div class="p-12 text-center bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                    <div class="w-20 h-20 bg-gray-100 dark:bg-gray-800 rounded-full flex items-center justify-center text-3xl mx-auto mb-6">🏜️</div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">No pending applications</h3>
                    <p class="text-gray-500 max-w-sm mx-auto">Wait for creators to apply to your active campaigns or consider refining your briefs.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <?php foreach ($applications as $app): ?>
                        <div class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm hover:shadow-xl hover:shadow-gray-200/50 dark:hover:shadow-none transition-all duration-500 flex flex-col group">
                            <div class="flex items-start justify-between mb-6">
                                <div class="flex gap-4">
                                    <div class="w-16 h-16 rounded-2xl bg-secondary/10 flex items-center justify-center text-secondary font-black text-2xl group-hover:scale-110 transition-transform">
                                        <?php echo strtoupper(substr($app['full_name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-black text-secondary uppercase tracking-widest mb-1"><?php echo e($app['campaign_title']); ?></p>
                                        <h3 class="text-xl font-black text-gray-900 dark:text-white"><?php echo e($app['full_name']); ?></h3>
                                        <p class="text-sm text-gray-500 font-medium"><?php echo e($app['school']); ?> • <?php echo e($app['main_niche']); ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex-1 bg-gray-50 dark:bg-gray-800/50 p-6 rounded-2xl mb-8 border border-gray-100 dark:border-gray-700">
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3">Application Message</p>
                                <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed mb-4"><?php echo nl2br(e($app['application_message'])); ?></p>
                                
                                <a href="<?php echo e($app['sample_video_link']); ?>" target="_blank" class="inline-flex items-center text-xs font-bold text-secondary hover:underline">
                                    View Portfolio Video <span class="ml-1">↗</span>
                                </a>
                            </div>

                            <div class="flex gap-3 mt-auto">
                                <a href="?action=approve&app_id=<?php echo $app['id']; ?>" class="flex-1 py-4 bg-secondary text-white font-bold rounded-xl text-center hover:shadow-lg hover:shadow-secondary/20 transition-all">Approve</a>
                                <a href="?action=reject&app_id=<?php echo $app['id']; ?>" class="flex-1 py-4 bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 font-bold rounded-xl text-center hover:bg-red-50 hover:text-red-500 transition-all">Reject</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
