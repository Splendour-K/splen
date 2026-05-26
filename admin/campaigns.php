<?php
require_once "../config/database.php";
require_once "../includes/functions.php";
require_role("admin");

$message = '';

// ── POST-based mutations ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action']  ?? '';
    $camp_id = (int)($_POST['camp_id'] ?? 0);

    if ($camp_id) {
        switch ($action) {
            case 'feature':
                $pdo->prepare("UPDATE campaigns SET is_featured = 1 WHERE id = ?")->execute([$camp_id]);
                log_activity($_SESSION['user_id'], 'Campaign Featured', "Campaign #$camp_id featured");
                $message = 'Campaign starred.';
                break;
            case 'unfeature':
                $pdo->prepare("UPDATE campaigns SET is_featured = 0 WHERE id = ?")->execute([$camp_id]);
                log_activity($_SESSION['user_id'], 'Campaign Unstarred', "Campaign #$camp_id unstarred");
                $message = 'Campaign unstarred.';
                break;
            case 'pause':
                $pdo->prepare("UPDATE campaigns SET status = 'paused' WHERE id = ?")->execute([$camp_id]);
                log_activity($_SESSION['user_id'], 'Campaign Paused', "Campaign #$camp_id paused");
                $message = 'Campaign paused.';
                break;
            case 'activate':
                $pdo->prepare("UPDATE campaigns SET status = 'active' WHERE id = ?")->execute([$camp_id]);
                log_activity($_SESSION['user_id'], 'Campaign Activated', "Campaign #$camp_id activated");
                $message = 'Campaign set to active.';
                break;
            case 'delete':
                $pdo->prepare("DELETE FROM campaigns WHERE id = ?")->execute([$camp_id]);
                log_activity($_SESSION['user_id'], 'Campaign Deleted', "Campaign #$camp_id deleted");
                $message = 'Campaign deleted.';
                break;
        }
    }
}

// ── Filters ───────────────────────────────────────────────────
$filter_status = $_GET['status'] ?? '';
$search        = trim($_GET['search'] ?? '');

$sql    = "SELECT c.*, b.brand_name FROM campaigns c JOIN brands b ON c.brand_id = b.id WHERE 1=1";
$params = [];

if ($filter_status !== '') {
    $sql    .= " AND c.status = ?";
    $params[] = $filter_status;
}
if ($search !== '') {
    $sql    .= " AND (c.title LIKE ? OR b.brand_name LIKE ?)";
    $s       = '%' . $search . '%';
    $params[] = $s;
    $params[] = $s;
}
$sql .= " ORDER BY c.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$campaigns = $stmt->fetchAll();

// Stats
$stats = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'active')  AS active_count,
        SUM(status = 'paused')  AS paused_count,
        SUM(status = 'draft')   AS draft_count,
        SUM(is_featured = 1)    AS featured_count
    FROM campaigns
")->fetch();

include "../includes/header.php";
?>

<div class="pt-24 min-h-screen bg-gray-50 dark:bg-gray-950">
    <div class="max-w-7xl mx-auto px-6 md:px-12 flex flex-col md:flex-row gap-8 py-8">
        <?php include "dashboard_sidebar.php"; ?>

        <main class="flex-1 space-y-8">

            <!-- Header -->
            <header class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Admin — Campaigns</p>
                <h2 class="text-3xl font-black text-gray-900 dark:text-white mt-1">Campaign Control</h2>
                <p class="text-gray-500 font-bold mt-1">Audit, moderate, and manage all brand briefs on the platform.</p>
            </header>

            <?php if ($message): ?>
                <div class="p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-900 rounded-2xl text-green-800 dark:text-green-300 text-sm font-bold">
                    ✓ <?php echo e($message); ?>
                </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <?php
                $stat_cards = [
                    ['Total',    $stats['total'],         'bg-gray-900',   'text-white'],
                    ['Active',   $stats['active_count'],  'bg-green-500',  'text-white'],
                    ['Paused',   $stats['paused_count'],  'bg-orange-500', 'text-white'],
                    ['Draft',    $stats['draft_count'],   'bg-gray-500',   'text-white'],
                    ['Starred',  $stats['featured_count'],'bg-yellow-400', 'text-gray-900'],
                ];
                foreach ($stat_cards as [$label, $val, $bg, $tc]):
                ?>
                <div class="p-5 <?php echo $bg; ?> rounded-[1.5rem] shadow-sm">
                    <p class="text-xs font-black uppercase tracking-widest <?php echo $tc; ?> opacity-70"><?php echo $label; ?></p>
                    <p class="text-3xl font-black <?php echo $tc; ?> mt-1"><?php echo (int)$val; ?></p>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Search + Filters -->
            <div class="space-y-4">
                <form method="GET" class="flex gap-2">
                    <input type="hidden" name="status" value="<?php echo e($filter_status); ?>">
                    <input type="text" name="search" placeholder="Search title or brand…" value="<?php echo e($search); ?>"
                           class="flex-1 px-4 py-3 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary text-sm">
                    <button type="submit" class="px-6 py-3 bg-secondary text-white font-bold rounded-xl hover:scale-105 transition text-sm">Search</button>
                </form>

                <div class="flex flex-wrap gap-2">
                    <?php
                    $tabs = ['' => 'All', 'active' => 'Active', 'paused' => 'Paused', 'draft' => 'Draft'];
                    foreach ($tabs as $key => $label):
                    ?>
                        <a href="?status=<?php echo $key; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>"
                           class="px-4 py-2 rounded-xl font-bold text-sm <?php echo $filter_status === $key ? 'bg-secondary text-white' : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border border-gray-100 dark:border-gray-800'; ?>">
                            <?php echo $label; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Campaigns List -->
            <div class="space-y-4">
                <?php foreach ($campaigns as $c): ?>
                    <div class="p-6 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                        <div class="flex flex-col md:flex-row md:items-center gap-5">

                            <!-- Info -->
                            <div class="flex-1">
                                <div class="flex flex-wrap items-center gap-2 mb-2">
                                    <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase
                                        <?php echo $c['status'] === 'active'  ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' :
                                                  ($c['status'] === 'paused' ? 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400' :
                                                                               'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'); ?>">
                                        <?php echo ucfirst(e($c['status'])); ?>
                                    </span>
                                    <?php if ($c['is_featured']): ?>
                                        <span class="px-3 py-1 bg-yellow-100 text-yellow-700 rounded-full text-[10px] font-black uppercase">⭐ Starred</span>
                                    <?php endif; ?>
                                </div>
                                <h3 class="text-xl font-black text-gray-900 dark:text-white"><?php echo e($c['title']); ?></h3>
                                <p class="text-xs font-bold text-gray-400 mt-1">
                                    Brand: <span class="text-secondary"><?php echo e($c['brand_name']); ?></span>
                                    &nbsp;·&nbsp; <?php echo e($c['currency']); ?> <?php echo number_format((float)$c['budget_per_creator']); ?> per creator
                                    &nbsp;·&nbsp; Created <?php echo date('M d, Y', strtotime($c['created_at'])); ?>
                                </p>
                            </div>

                            <!-- Actions -->
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="../brand/edit-campaign.php?id=<?php echo $c['id']; ?>" class="px-4 py-2 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-bold rounded-xl text-xs hover:bg-gray-200 dark:hover:bg-gray-700 transition">
                                    ✏️ Edit
                                </a>

                                <?php if (!$c['is_featured']): ?>
                                    <form method="POST">
                                        <input type="hidden" name="action"  value="feature">
                                        <input type="hidden" name="camp_id" value="<?php echo $c['id']; ?>">
                                        <button type="submit" class="px-4 py-2 bg-yellow-50 text-yellow-700 font-bold rounded-xl text-xs hover:bg-yellow-400 hover:text-white transition">⭐ Star</button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST">
                                        <input type="hidden" name="action"  value="unfeature">
                                        <input type="hidden" name="camp_id" value="<?php echo $c['id']; ?>">
                                        <button type="submit" class="px-4 py-2 bg-yellow-400 text-white font-bold rounded-xl text-xs hover:bg-gray-200 hover:text-gray-700 transition">✖ Unstar</button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($c['status'] !== 'paused'): ?>
                                    <form method="POST">
                                        <input type="hidden" name="action"  value="pause">
                                        <input type="hidden" name="camp_id" value="<?php echo $c['id']; ?>">
                                        <button type="submit" class="px-4 py-2 bg-gray-900 dark:bg-white text-white dark:text-gray-900 font-bold rounded-xl text-xs hover:bg-orange-600 hover:text-white transition">⏸ Pause</button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST">
                                        <input type="hidden" name="action"  value="activate">
                                        <input type="hidden" name="camp_id" value="<?php echo $c['id']; ?>">
                                        <button type="submit" class="px-4 py-2 bg-green-600 text-white font-bold rounded-xl text-xs hover:bg-green-700 transition">▶ Activate</button>
                                    </form>
                                <?php endif; ?>

                                <form method="POST" onsubmit="return confirm('Permanently delete this campaign and all its data?');">
                                    <input type="hidden" name="action"  value="delete">
                                    <input type="hidden" name="camp_id" value="<?php echo $c['id']; ?>">
                                    <button type="submit" class="px-4 py-2 bg-red-50 text-red-600 font-bold rounded-xl text-xs hover:bg-red-600 hover:text-white transition">🗑 Delete</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (empty($campaigns)): ?>
                <div class="p-12 text-center bg-white dark:bg-gray-900 rounded-[2.5rem] border border-dashed border-gray-200 dark:border-gray-800 shadow-sm">
                    <p class="text-gray-400">No campaigns match your filters.</p>
                </div>
            <?php endif; ?>

        </main>
    </div>
</div>

<?php include "../includes/footer.php"; ?>
