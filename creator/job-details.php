<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_role('creator');

if (!isset($_GET['id'])) {
    header("Location: my-jobs.php");
    exit;
}

$job_id = $_GET['id'];

// Get Job Details with Campaign and Brand info
$stmt = $pdo->prepare("
    SELECT j.*, c.title, c.description, c.perks, c.requirements, b.brand_name, b.industry
    FROM jobs j
    JOIN campaigns c ON j.campaign_id = c.id
    JOIN brands b ON j.brand_id = b.id
    JOIN creators cr ON j.creator_id = cr.id
    WHERE j.id = ? AND cr.user_id = ?
");
$stmt->execute([$job_id, $_SESSION['user_id']]);
$job = $stmt->fetch();

if (!$job) {
    header("Location: my-jobs.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM submissions WHERE job_id = ? ORDER BY submitted_at DESC LIMIT 1");
$stmt->execute([$job['id']]);
$submission = $stmt->fetch();

include '../includes/header.php';
?>

<div class="pt-24 min-h-screen bg-gray-50 dark:bg-gray-950">
    <div class="max-w-7xl mx-auto px-6 md:px-12 flex flex-col md:flex-row gap-8 py-8">
        <!-- Sidebar (Simplified) -->
        <aside class="w-full md:w-64">
            <a href="my-jobs.php" class="flex items-center space-x-2 text-gray-500 hover:text-primary transition font-bold mb-8">
                <span>← Back to My Jobs</span>
            </a>
            <div class="p-8 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm space-y-6">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">Job Status</p>
                    <?php 
                        $status = $job['status'];
                        $colors = [
                            'active' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30',
                            'submitted' => 'bg-orange-100 text-orange-700 dark:bg-orange-900/30',
                            'completed' => 'bg-green-100 text-green-700 dark:bg-green-900/30'
                        ];
                    ?>
                    <span class="px-4 py-2 rounded-xl text-xs font-black uppercase tracking-wider <?php echo $colors[$status] ?? 'bg-gray-100'; ?>">
                        <?php echo e($status); ?>
                    </span>
                </div>
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">Earnings</p>
                    <p class="text-2xl font-black text-gray-900 dark:text-white">$<?php echo number_format($job['amount'], 2); ?></p>
                </div>
                <?php if ($status === 'active'): ?>
                    <a href="submit-video.php?job_id=<?php echo $job['id']; ?>" class="block w-full py-4 bg-primary text-white text-center font-bold rounded-2xl shadow-lg shadow-primary/20 hover:scale-[1.02] transition">Submit Video</a>
                <?php endif; ?>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 space-y-8">
            <header class="p-10 bg-white dark:bg-gray-900 rounded-[3rem] border border-gray-100 dark:border-gray-800 shadow-sm relative overflow-hidden">
                <div aria-hidden="true" class="absolute top-0 right-0 -mr-16 -mt-16 w-64 h-64 bg-primary/5 rounded-full blur-3xl"></div>
                <div class="relative">
                    <div class="flex items-center space-x-2 text-primary font-bold text-sm mb-4">
                        <span><?php echo e($job['brand_name']); ?></span>
                        <span class="w-1 h-1 bg-gray-300 rounded-full"></span>
                        <span><?php echo e($job['industry']); ?></span>
                    </div>
                    <h2 class="text-4xl font-black text-gray-900 dark:text-white"><?php echo e($job['title']); ?></h2>
                </div>
            </header>

            <!-- Campaign Brief -->
            <section class="grid grid-cols-1 md:grid-cols-3 gap-8 text-gray-900 dark:text-white">
                <div class="md:col-span-2 space-y-8">
                    <div class="p-10 bg-white dark:bg-gray-900 rounded-[3rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                        <h3 class="text-xl font-black mb-6">Campaign Brief</h3>
                        <p class="text-gray-600 dark:text-gray-400 leading-relaxed"><?php echo nl2br(e($job['description'])); ?></p>
                    </div>

                    <div class="p-10 bg-white dark:bg-gray-900 rounded-[3rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                        <h3 class="text-xl font-black mb-6">Requirements</h3>
                        <p class="text-gray-600 dark:text-gray-400 leading-relaxed"><?php echo nl2br(e($job['requirements'])); ?></p>
                    </div>
                </div>

                <div class="space-y-8">
                    <div class="p-8 bg-secondary/10 rounded-[2.5rem] border border-secondary/20">
                        <h3 class="text-lg font-black text-secondary mb-4">Perks & Rewards</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed"><?php echo nl2br(e($job['perks'])); ?></p>
                    </div>

                    <div class="p-8 bg-gray-900 dark:bg-white rounded-[2.5rem] text-white dark:text-gray-900">
                        <h3 class="text-lg font-black mb-4">Timeline</h3>
                        <p class="text-sm opacity-80">Started: <?php echo date('M d, Y', strtotime($job['created_at'])); ?></p>
                        <p class="text-sm font-bold mt-2 text-primary">Deadline: Within 7 days</p>
                    </div>
                </div>
            </section>

            <!-- Deliverables -->
            <?php if ($status === 'submitted' || $status === 'completed'): ?>
                <section class="p-10 bg-white dark:bg-gray-900 rounded-[3rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                    <h3 class="text-xl font-black mb-6">Your Submission</h3>
                    <?php $preview_src = $submission['watermarked_preview_file'] ?? ($submission['clean_video_file'] ?? ($submission['video_file'] ?? null)); ?>
                    <div class="aspect-video bg-gray-100 dark:bg-gray-800 rounded-[2rem] overflow-hidden border-2 border-dashed border-gray-200 dark:border-gray-700 flex items-center justify-center">
                        <?php if ($preview_src): ?>
                            <video class="w-full h-full object-cover" controls playsinline preload="metadata">
                                <source src="<?php echo APP_URL . ltrim(e($preview_src), '/'); ?>" type="video/mp4">
                            </video>
                        <?php else: ?>
                            <span class="text-gray-400 font-bold italic">No preview available yet</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($job['feedback']): ?>
                        <div class="mt-8 p-6 bg-orange-50 dark:bg-orange-900/20 border border-orange-100 dark:border-orange-800 rounded-2xl">
                            <p class="text-xs font-black text-orange-600 uppercase tracking-widest mb-2">Brand Feedback</p>
                            <p class="text-gray-700 dark:text-gray-300 italic">"<?php echo e($job['feedback']); ?>"</p>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php 
include '../includes/footer.php';
?>