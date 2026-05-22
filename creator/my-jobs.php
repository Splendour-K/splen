<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_role('creator');

$stmt = $pdo->prepare("SELECT * FROM creators WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$creator = $stmt->fetch();

// Fetch jobs along with the most recent submission video per job (if any)
$stmt = $pdo->prepare("
    SELECT j.*, camp.title, camp.order_type, b.brand_name, b.id as brand_db_id, conv.id as chat_id,
        latest_sub.clean_video_file, latest_sub.watermarked_preview_file, latest_sub.video_file,
        latest_sub.status as submission_status, latest_sub.submitted_at as submission_submitted_at
    FROM jobs j
    JOIN campaigns camp ON j.campaign_id = camp.id
    JOIN brands b ON j.brand_id = b.id
    LEFT JOIN conversations conv ON (conv.brand_id = j.brand_id AND conv.creator_id = j.creator_id)
    LEFT JOIN (
        SELECT s1.* FROM submissions s1
        JOIN (
            SELECT job_id, MAX(id) AS max_id FROM submissions GROUP BY job_id
        ) s2 ON s1.id = s2.max_id
    ) latest_sub ON latest_sub.job_id = j.id
    WHERE j.creator_id = ?
    ORDER BY j.created_at DESC
");
$stmt->execute([$creator['id']]);
$jobs = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="pt-24 min-h-screen bg-gray-50 dark:bg-gray-950">
    <div class="max-w-7xl mx-auto px-6 md:px-12 flex flex-col md:flex-row gap-8 py-8">
        <!-- Sidebar -->
        <?php include '../includes/creator_sidebar.php'; ?>

        <!-- Main Content -->
        <main class="flex-1 space-y-8">
            <header class="p-8 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm relative overflow-hidden">
                <div aria-hidden="true" class="absolute top-0 right-0 -mr-16 -mt-16 w-64 h-64 bg-primary/5 rounded-full blur-3xl"></div>
                <div class="relative">
                    <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Professional Jobs</h2>
                    <p class="text-gray-600 dark:text-gray-400 mt-2">Manage your active collaborations and upload deliverables.</p>
                </div>
            </header>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <?php foreach ($jobs as $job): ?>
                    <?php
                        $job_video = $job['clean_video_file']
                            ?: ($job['video_file'] ?? null)
                            ?: ($job['watermarked_preview_file'] ?? null);
                    ?>
                    <div class="p-1 w-full rounded-[2.5rem] bg-gradient-to-br from-gray-100 to-transparent dark:from-gray-800 group hover:from-primary/20 transition-all duration-500">
                        <div class="p-8 bg-white dark:bg-gray-900 rounded-[2.3rem] shadow-sm h-full flex flex-col">
                            <div class="flex justify-between items-start mb-6">
                                <div class="w-12 h-12 rounded-2xl bg-primary/10 flex items-center justify-center text-xl">🎬</div>
                                <?php
                                    $statusColors = [
                                        'in_progress' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                                        'awaiting_review' => 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
                                        'revision_requested' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                                        'completed' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                    ];
                                    $jobStatus = $job['status'];
                                    $statusClass = $statusColors[$jobStatus] ?? $statusColors['in_progress'];
                                ?>
                                <span class="px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest <?php echo $statusClass; ?>">
                                    <?php echo str_replace('_', ' ', $jobStatus); ?>
                                </span>
                            </div>

                            <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2"><?php echo e($job['title']); ?></h3>
                            <p class="text-gray-500 font-medium mb-4">Brand: <span class="text-gray-900 dark:text-white"><?php echo e($job['brand_name']); ?></span></p>

                            <?php if ($job_video): ?>
                                <div class="mb-4 rounded-2xl overflow-hidden bg-black aspect-video">
                                    <video class="w-full h-full object-contain" controls preload="metadata" playsinline>
                                        <source src="<?php echo APP_URL . ltrim(e($job_video), '/'); ?>" type="video/mp4">
                                    </video>
                                </div>
                                <?php if (!empty($job['submission_submitted_at'])): ?>
                                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest mb-4">
                                        Your submission · <?php echo date('M d, Y', strtotime($job['submission_submitted_at'])); ?>
                                    </p>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php if ($job['chat_id']): ?>
                                <a href="<?php echo APP_URL; ?>view-message.php?id=<?php echo $job['chat_id']; ?>" class="inline-flex items-center text-xs font-bold text-primary hover:underline mb-8">
                                    <span class="mr-2">💬</span> Message Brand
                                </a>
                            <?php endif; ?>

                            <div class="mt-auto pt-6 border-t border-gray-50 dark:border-gray-800 flex items-center justify-between">
                                <div class="text-sm">
                                    <p class="text-gray-400 text-[10px] font-bold uppercase tracking-wider">Started</p>
                                    <p class="text-gray-900 dark:text-white font-bold"><?php echo date('M d', strtotime($job['created_at'])); ?></p>
                                </div>
                                
                                <?php if ($jobStatus === 'completed'): ?>
                                    <?php if ($job['order_type'] === 'performance_campaign'): ?>
                                        <a href="<?php echo APP_URL; ?>creator/submit-performance.php?job_id=<?php echo $job['id']; ?>" class="px-6 py-3 bg-secondary text-white rounded-xl text-xs font-black shadow-lg shadow-secondary/20 hover:scale-105 transition active:scale-95">
                                            Submit Analytics
                                        </a>
                                    <?php else: ?>
                                        <span class="flex items-center text-green-600 font-bold text-sm">
                                            <span class="mr-2">✨</span> Completed
                                        </span>
                                    <?php endif; ?>
                                <?php elseif ($jobStatus === 'awaiting_review' || $jobStatus === 'draft_submitted'): ?>
                                    <button class="px-6 py-3 bg-gray-100 dark:bg-gray-800 text-gray-400 rounded-xl text-xs font-bold cursor-not-allowed">
                                        Pending Review
                                    </button>
                                <?php else: ?>
                                    <div class="flex gap-2">
                                        <a href="<?php echo APP_URL; ?>creator/submit-video.php?job_id=<?php echo $job['id']; ?>" class="flex-1 text-center px-4 py-3 bg-primary text-white rounded-xl text-xs font-black shadow-lg shadow-primary/20 hover:scale-105 transition active:scale-95">
                                            Upload Content
                                        </a>
                                        <button onclick="document.getElementById('dis-modal-<?php echo $job['id']; ?>').classList.remove('hidden')" class="px-4 py-3 bg-red-50 text-red-500 rounded-xl text-xs font-bold hover:bg-red-100 transition">
                                            ⚠️
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Dispute Modal -->
                    <div id="dis-modal-<?php echo $job['id']; ?>" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-6 hidden">
                        <div class="bg-white dark:bg-gray-900 p-8 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 w-full max-w-lg shadow-2xl text-center">
                            <h3 class="text-2xl font-black text-gray-900 dark:text-white mb-4 italic">Raise Official Dispute</h3>
                            <p class="text-xs font-bold text-gray-400 mb-8 uppercase tracking-widest">A moderator will review this collaboration. Disputes can lead to account suspension if misused.</p>
                            <form method="POST" action="dispute-handler.php">
                                <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                <textarea name="dispute_reason" rows="4" required class="w-full px-5 py-4 bg-gray-50 dark:bg-gray-800 border-2 border-transparent focus:border-red-500 rounded-2xl outline-none mb-6 dark:text-white font-medium" placeholder="Explain the issue in detail..."></textarea>
                                <div class="flex gap-4">
                                    <button type="submit" class="flex-1 py-4 bg-red-600 text-white font-bold rounded-2xl shadow-xl shadow-red-500/20 uppercase tracking-widest text-xs">Submit Dispute</button>
                                    <button type="button" onclick="this.closest('.fixed').classList.add('hidden')" class="flex-1 py-4 border border-gray-200 dark:border-gray-700 text-gray-500 font-bold rounded-2xl uppercase tracking-widest text-xs">Back</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if (empty($jobs)): ?>
                    <div class="col-span-full py-24 flex flex-col items-center justify-center text-center p-12 bg-white dark:bg-gray-900 rounded-[3rem] border border-gray-100 dark:border-gray-800">
                        <div class="w-24 h-24 bg-gray-50 dark:bg-gray-800 rounded-full flex items-center justify-center text-5xl mb-8">🛠️</div>
                        <h3 class="text-2xl font-black text-gray-900 dark:text-white">Work items will appear here</h3>
                        <p class="text-gray-500 max-w-sm mt-4 font-medium leading-relaxed">Once a brand accepts your application, your contract and workspace will show up in this section.</p>
                        <a href="<?php echo APP_URL; ?>creator/browse.php" class="mt-10 px-10 py-4 bg-gray-900 dark:bg-white text-white dark:text-gray-900 font-black rounded-2xl transition hover:bg-primary hover:text-white shadow-xl">Browse Opportunities</a>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<?php 
include '../includes/footer.php';
?>
