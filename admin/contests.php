<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_role('admin');

$action = $_GET['action'] ?? '';
$contest_id = $_GET['contest_id'] ?? 0;

// Handle contest status updates
if ($action === 'close_contest') {
    $stmt = $pdo->prepare("UPDATE contests SET status = 'closed' WHERE id = ?");
    if ($stmt->execute([$contest_id])) {
        header("Location: contests.php?success=Contest closed");
        exit;
    }
}

if ($action === 'announce_winners') {
    $stmt = $pdo->prepare("UPDATE contests SET status = 'results_announced' WHERE id = ?");
    if ($stmt->execute([$contest_id])) {
        header("Location: contests.php?success=Winners announced");
        exit;
    }
}

// Fetch all contests
$stmt = $pdo->query("
    SELECT c.*, b.brand_name,
    (SELECT COUNT(*) FROM contest_submissions WHERE contest_id = c.id) as submission_count,
    (SELECT COUNT(*) FROM contest_submissions WHERE contest_id = c.id AND status = 'winner') as winner_count
    FROM contests c
    JOIN brands b ON c.brand_id = b.id
    ORDER BY c.created_at DESC
");
$contests = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="pt-24 min-h-screen bg-gray-50 dark:bg-gray-950">
    <div class="max-w-7xl mx-auto px-6 md:px-12 flex flex-col md:flex-row gap-8 py-8">
        <?php include 'dashboard_sidebar.php'; ?>

        <main class="flex-1 space-y-8">
            <header class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                <div class="flex justify-between items-center">
                    <div>
                        <h2 class="text-3xl font-black text-gray-900 dark:text-white">Contests</h2>
                        <p class="text-gray-600 dark:text-gray-400 mt-2">Manage all creator contests.</p>
                    </div>
                    <a href="<?php echo APP_URL; ?>brand/create-contest.php" class="px-6 py-3 bg-secondary text-white font-bold rounded-xl text-sm">+ New Contest</a>
                </div>
            </header>

            <?php if (isset($_GET['success'])): ?>
                <div class="p-4 bg-green-50 text-green-700 font-bold rounded-2xl border border-green-100">✅ <?php echo $_GET['success']; ?></div>
            <?php endif; ?>

            <!-- Contests Table -->
            <div class="space-y-4">
                <?php foreach ($contests as $contest): ?>
                    <div class="p-6 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                            <div class="flex-1">
                                <h3 class="text-xl font-black text-gray-900 dark:text-white"><?php echo e($contest['title']); ?></h3>
                                <p class="text-sm text-gray-500 font-bold"><?php echo e($contest['brand_name']); ?></p>
                                
                                <div class="flex flex-wrap gap-3 mt-3">
                                    <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-[10px] font-bold uppercase"><?php echo $contest['submission_count']; ?> Submissions</span>
                                    <span class="px-3 py-1 bg-yellow-100 text-yellow-700 rounded-full text-[10px] font-bold uppercase">🏆 <?php echo $contest['winner_count']; ?> Winners</span>
                                    <span class="px-3 py-1 <?php 
                                        $status_class = match($contest['status']) {
                                            'live' => 'bg-green-100 text-green-700',
                                            'closed' => 'bg-red-100 text-red-700',
                                            'results_announced' => 'bg-purple-100 text-purple-700',
                                            default => 'bg-gray-100 text-gray-700'
                                        };
                                        echo $status_class;
                                    ?> rounded-full text-[10px] font-bold uppercase"><?php echo ucfirst(str_replace('_', ' ', $contest['status'])); ?></span>
                                </div>

                                <div class="text-xs text-gray-500 font-bold mt-2">
                                    Deadline: <strong><?php echo date('M d, Y', strtotime($contest['submission_deadline'])); ?></strong>
                                    | Budget: <strong><?php echo format_currency($contest['total_contest_budget'], $contest['currency']); ?></strong>
                                </div>
                            </div>

                            <div class="flex flex-col gap-2">
                                <a href="contest-submissions.php" class="px-4 py-2 bg-blue-500 text-white font-bold rounded-xl text-sm hover:bg-blue-600 transition">📋 View Submissions</a>
                                <?php if ($contest['status'] === 'live'): ?>
                                    <a href="?action=close_contest&contest_id=<?php echo $contest['id']; ?>" onclick="return confirm('Close this contest?')" class="px-4 py-2 bg-orange-500 text-white font-bold rounded-xl text-sm hover:bg-orange-600 transition">🔒 Close</a>
                                <?php elseif ($contest['status'] === 'closed'): ?>
                                    <a href="?action=announce_winners&contest_id=<?php echo $contest['id']; ?>" class="px-4 py-2 bg-purple-500 text-white font-bold rounded-xl text-sm hover:bg-purple-600 transition">📢 Announce Winners</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (empty($contests)): ?>
                <div class="py-12 text-center">
                    <p class="text-gray-500 font-bold uppercase tracking-widest text-sm">No contests created yet.</p>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
