<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_role('creator');

$contest_id = $_GET['contest_id'] ?? 0;

$stmt = $pdo->prepare("SELECT * FROM creators WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$creator = $stmt->fetch();
require_creator_record($creator);
$creator_id = $creator['id'];

if (!$contest_id) {
    die("Contest not found.");
}

$stmt = $pdo->prepare("
    SELECT c.*, cs.id as submission_id, cs.view_count, cs.engagement_count
    FROM contests c
    LEFT JOIN contest_submissions cs ON c.id = cs.contest_id AND cs.creator_id = ?
    WHERE c.id = ?
");
$stmt->execute([$creator_id, $contest_id]);
$contest = $stmt->fetch();

if (!$contest) {
    die("Contest not found.");
}

$status = get_contest_status($contest_id);

if ($status['current_status'] !== 'closed') {
    die("CPM submissions only open after submission deadline and before announcement.");
}

if (isset($_POST['submit_performance'])) {
    $views = intval($_POST['view_count'] ?? 0);
    $engagement = intval($_POST['engagement_count'] ?? 0);

    if ($views < 0 || $engagement < 0) {
        $error = "View and engagement counts must be positive numbers.";
    } else {
        if ($contest['submission_id']) {
            $stmt = $pdo->prepare("UPDATE contest_submissions SET view_count = ?, engagement_count = ?, performance_submitted_at = NOW() WHERE id = ?");
            $stmt->execute([$views, $engagement, $contest['submission_id']]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO contest_submissions (contest_id, creator_id, view_count, engagement_count, status, performance_submitted_at)
                VALUES (?, ?, ?, ?, 'submitted', NOW())
            ");
            $stmt->execute([$contest_id, $creator_id, $views, $engagement]);
        }

        create_notification($creator_id, 'Performance Submitted', 'Your CPM performance data for ' . e($contest['title']) . ' has been submitted. Awaiting verification.', 'cpm_submitted', 'creator/my-contests.php', 'contest_performance', $contest_id);

        redirect('creator/my-contests.php?message=Performance data submitted successfully');
        exit;
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
            <div class="max-w-2xl">
                <div class="p-8 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm mb-8">
                    <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-2"><?php echo e($contest['title']); ?></h2>
                    <p class="text-gray-500 font-bold">Submit your video's performance data</p>
                </div>

                <?php if (isset($error)): ?>
                    <div class="p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-900 rounded-2xl mb-6">
                        <p class="text-red-700 dark:text-red-400 text-sm font-bold"><?php echo e($error); ?></p>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-6">
                    <!-- Contest Info -->
                    <section class="p-6 bg-gray-50 dark:bg-gray-800 rounded-[2rem] border border-gray-200 dark:border-gray-700">
                        <h3 class="font-bold text-gray-900 dark:text-white mb-4">Contest Details</h3>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-[10px] text-gray-500 uppercase tracking-widest font-bold mb-1">Total Prize Pool</p>
                                <p class="text-lg font-black text-gray-900 dark:text-white"><?php echo number_format($contest['total_contest_budget']); ?> <?php echo e($contest['currency']); ?></p>
                            </div>
                            <div>
                                <p class="text-[10px] text-gray-500 uppercase tracking-widest font-bold mb-1">Number of Winners</p>
                                <p class="text-lg font-black text-gray-900 dark:text-white"><?php echo $contest['number_of_winners']; ?></p>
                            </div>
                            <div class="col-span-2">
                                <p class="text-[10px] text-gray-500 uppercase tracking-widest font-bold mb-1">Description</p>
                                <p class="text-sm text-gray-700 dark:text-gray-300"><?php echo e($contest['description']); ?></p>
                            </div>
                        </div>
                    </section>

                    <!-- Performance Data -->
                    <section>
                        <h3 class="font-bold text-gray-900 dark:text-white mb-4">Video Performance Data</h3>

                        <div class="space-y-4">
                            <div>
                                <label class="block font-bold text-gray-900 dark:text-white mb-2">Total Views</label>
                                <input type="number" name="view_count" min="0" value="<?php echo $contest['view_count'] ?? ''; ?>" required
                                    class="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary">
                                <p class="text-[10px] text-gray-500 mt-1">Enter the total number of views your submission received.</p>
                            </div>

                            <div>
                                <label class="block font-bold text-gray-900 dark:text-white mb-2">Total Engagements (Likes, Comments, Shares)</label>
                                <input type="number" name="engagement_count" min="0" value="<?php echo $contest['engagement_count'] ?? ''; ?>" required
                                    class="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary">
                                <p class="text-[10px] text-gray-500 mt-1">Total combined count of likes, comments, shares, and other interactions.</p>
                            </div>
                        </div>
                    </section>

                    <!-- Info Box -->
                    <div class="p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-900 rounded-2xl">
                        <p class="text-blue-800 dark:text-blue-400 text-sm">
                            <span class="font-bold">ℹ️ Note:</span> Your submitted performance data will be verified by our admin team before final payout calculation. Make sure to report accurate numbers.
                        </p>
                    </div>

                    <!-- Buttons -->
                    <div class="flex gap-4">
                        <button type="submit" name="submit_performance" class="flex-1 px-6 py-3 bg-primary text-white font-bold rounded-xl hover:bg-primary/90 transition">
                            Submit Performance Data
                        </button>
                        <a href="my-contests.php" class="flex-1 px-6 py-3 bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-white font-bold rounded-xl hover:bg-gray-300 dark:hover:bg-gray-600 transition text-center">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
