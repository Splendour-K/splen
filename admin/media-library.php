<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_role('admin');

$search = trim($_GET['search'] ?? '');
$type = $_GET['type'] ?? 'all';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_media_type'], $_POST['delete_media_id'])) {
    $delete_type = $_POST['delete_media_type'];
    $delete_id = (int)$_POST['delete_media_id'];

    if ($delete_type === 'contest') {
        $stmt = $pdo->prepare("SELECT video_file, watermarked_preview_file, clean_video_file, posted_video_link FROM contest_submissions WHERE id = ?");
        $stmt->execute([$delete_id]);
        $media = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($media) {
            delete_uploaded_file_path($media['video_file']);
            delete_uploaded_file_path($media['watermarked_preview_file']);
            delete_uploaded_file_path($media['clean_video_file']);
            $pdo->prepare("DELETE FROM contest_submissions WHERE id = ?")->execute([$delete_id]);
        }
    } elseif ($delete_type === 'ugc') {
        $stmt = $pdo->prepare("SELECT video_file, watermarked_preview_file, clean_video_file FROM ugc_order_submissions WHERE id = ?");
        $stmt->execute([$delete_id]);
        $media = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($media) {
            delete_uploaded_file_path($media['video_file']);
            delete_uploaded_file_path($media['watermarked_preview_file']);
            delete_uploaded_file_path($media['clean_video_file']);
            $pdo->prepare("DELETE FROM ugc_order_submissions WHERE id = ?")->execute([$delete_id]);
        }
    } elseif ($delete_type === 'proof') {
        $stmt = $pdo->prepare("SELECT analytics_screenshot, posted_video_link FROM performance_proofs WHERE id = ?");
        $stmt->execute([$delete_id]);
        $media = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($media) {
            delete_uploaded_file_path($media['analytics_screenshot']);
            $pdo->prepare("DELETE FROM performance_proofs WHERE id = ?")->execute([$delete_id]);
        }
    }

    redirect('admin/media-library.php?message=Media deleted');
    exit;
}

$contest_where = '1=1';
$ugc_where = '1=1';
$job_where = '1=1';
$proof_where = '1=1';
$params = [];

if ($search !== '') {
    $like = '%' . $search . '%';
    $contest_where .= ' AND (c.title LIKE ? OR cr.full_name LIKE ? OR b.brand_name LIKE ?)';
    $ugc_where .= ' AND (uo.title LIKE ? OR cr.full_name LIKE ? OR b.brand_name LIKE ?)';
    $job_where .= ' AND (c.title LIKE ? OR cr.full_name LIKE ? OR b.brand_name LIKE ?)';
    $proof_where .= ' AND (p.posted_video_link LIKE ? OR cr.full_name LIKE ? OR b.brand_name LIKE ?)';
    $params = array_merge($params, [$like, $like, $like, $like, $like, $like, $like, $like, $like, $like, $like, $like]);
}

$contest_sql = "
    SELECT 'contest' AS media_type, cs.id AS item_id, c.title AS title, cr.full_name AS creator_name, b.brand_name,
           cs.status, cs.video_file, cs.watermarked_preview_file, cs.clean_video_file, cs.posted_video_link, cs.created_at
    FROM contest_submissions cs
    JOIN contests c ON cs.contest_id = c.id
    JOIN creators cr ON cs.creator_id = cr.id
    JOIN brands b ON c.brand_id = b.id
    WHERE {$contest_where}
    ORDER BY cs.created_at DESC
    LIMIT 100
";

$ugc_sql = "
    SELECT 'ugc' AS media_type, us.id AS item_id, uo.title AS title, cr.full_name AS creator_name, b.brand_name,
           us.status, us.video_file, us.watermarked_preview_file, us.clean_video_file, us.posted_video_link, us.created_at
    FROM ugc_order_submissions us
    JOIN ugc_orders uo ON us.ugc_order_id = uo.id
    JOIN creators cr ON us.creator_id = cr.id
    JOIN brands b ON uo.brand_id = b.id
    WHERE {$ugc_where}
    ORDER BY us.created_at DESC
    LIMIT 100
";

$job_sql = "
    SELECT 'job' AS media_type, s.id AS item_id, c.title AS title, cr.full_name AS creator_name, b.brand_name,
           s.status, s.video_file, s.watermarked_preview_file, s.clean_video_file, NULL AS posted_video_link, s.submitted_at AS created_at
    FROM submissions s
    JOIN jobs j ON s.job_id = j.id
    JOIN campaigns c ON j.campaign_id = c.id
    JOIN creators cr ON s.creator_id = cr.id
    JOIN brands b ON j.brand_id = b.id
    WHERE {$job_where}
    ORDER BY s.submitted_at DESC
    LIMIT 100
";

$proof_sql = "
    SELECT 'proof' AS media_type, p.id AS item_id, c.title AS title, cr.full_name AS creator_name, b.brand_name,
           p.status, NULL AS video_file, NULL AS watermarked_preview_file, NULL AS clean_video_file, p.posted_video_link, p.created_at
    FROM performance_proofs p
    JOIN campaigns c ON p.campaign_id = c.id
    JOIN creators cr ON p.creator_id = cr.id
    JOIN brands b ON c.brand_id = b.id
    WHERE {$proof_where}
    ORDER BY p.created_at DESC
    LIMIT 100
";

$stmt_contest = $pdo->prepare($contest_sql);
$stmt_contest->execute($search !== '' ? array_slice($params, 0, 3) : []);
$contest_media = $stmt_contest->fetchAll(PDO::FETCH_ASSOC);

$stmt_ugc = $pdo->prepare($ugc_sql);
$stmt_ugc->execute($search !== '' ? array_slice($params, 3, 3) : []);
$ugc_media = $stmt_ugc->fetchAll(PDO::FETCH_ASSOC);

$stmt_job = $pdo->prepare($job_sql);
$stmt_job->execute($search !== '' ? array_slice($params, 6, 3) : []);
$job_media = $stmt_job->fetchAll(PDO::FETCH_ASSOC);

$stmt_proof = $pdo->prepare($proof_sql);
$stmt_proof->execute($search !== '' ? array_slice($params, 9, 3) : []);
$proof_media = $stmt_proof->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<div class="pt-24 min-h-screen bg-gray-50 dark:bg-gray-950">
    <div class="max-w-7xl mx-auto px-6 md:px-12 flex flex-col md:flex-row gap-8 py-8 w-full">
        <?php include 'dashboard_sidebar.php'; ?>

        <main class="flex-1 space-y-8">
            <header class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm relative overflow-hidden">
                <div aria-hidden="true" class="absolute top-0 right-0 -mr-16 -mt-16 w-64 h-64 bg-orange-500/5 rounded-full blur-3xl"></div>
                <div class="relative flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <h2 class="text-3xl font-black text-gray-900 dark:text-white">Media Library</h2>
                        <p class="text-gray-500 font-bold mt-1">Review every uploaded video and posted proof on the platform.</p>
                    </div>
                    <form method="GET" class="flex gap-3 w-full md:w-auto">
                        <input type="text" name="search" value="<?php echo e($search); ?>" placeholder="Search creator, brand, or title..." class="w-full md:w-96 px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                        <button type="submit" class="px-6 py-3 bg-orange-500 text-white font-bold rounded-xl hover:bg-orange-600 transition">Search</button>
                    </form>
                </div>
            </header>

            <?php if (!empty($_GET['message'])): ?>
                <div class="p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-900 rounded-2xl text-green-800 dark:text-green-300 text-sm font-medium">
                    ✓ <?php echo e($_GET['message']); ?>
                </div>
            <?php endif; ?>

            <?php foreach ([['label' => 'Contest Videos', 'rows' => $contest_media], ['label' => 'Creator Job Videos', 'rows' => $job_media], ['label' => 'UGC Videos', 'rows' => $ugc_media], ['label' => 'Performance Proof Links', 'rows' => $proof_media]] as $section): ?>
                <section class="p-6 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm space-y-4">
                    <div class="flex items-center justify-between gap-4">
                        <h3 class="text-xl font-black text-gray-900 dark:text-white"><?php echo e($section['label']); ?></h3>
                        <p class="text-xs font-black uppercase tracking-widest text-gray-400"><?php echo count($section['rows']); ?> items</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                        <?php foreach ($section['rows'] as $row): ?>
                            <article class="overflow-hidden rounded-[2rem] border border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/40 shadow-sm">
                                <div class="aspect-[9/16] bg-black relative">
                                    <?php
                                        $src = $row['watermarked_preview_file'] ?: ($row['clean_video_file'] ?: $row['video_file']);
                                    ?>
                                    <?php if ($src): ?>
                                        <video class="w-full h-full object-cover" controls preload="metadata" playsinline>
                                            <source src="<?php echo APP_URL . ltrim($src, '/'); ?>" type="video/mp4">
                                        </video>
                                    <?php elseif (!empty($row['posted_video_link'])): ?>
                                        <div class="w-full h-full flex items-center justify-center p-6 text-center">
                                            <a href="<?php echo e($row['posted_video_link']); ?>" target="_blank" class="text-white font-bold underline break-all">Open external post</a>
                                        </div>
                                    <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center text-gray-400 font-bold">No media</div>
                                    <?php endif; ?>
                                </div>
                                <div class="p-4 space-y-2">
                                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400"><?php echo e($row['media_type']); ?> • <?php echo e($row['status']); ?></p>
                                    <h4 class="font-black text-gray-900 dark:text-white"><?php echo e($row['title']); ?></h4>
                                    <p class="text-sm text-gray-500">By <?php echo e($row['creator_name']); ?> for <?php echo e($row['brand_name']); ?></p>
                                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest"><?php echo time_ago($row['created_at']); ?></p>
                                    <form method="POST" onsubmit="return confirm('Delete this media record? This will remove the database record and stored files where applicable.');" class="pt-2">
                                        <input type="hidden" name="delete_media_type" value="<?php echo e($row['media_type']); ?>">
                                        <input type="hidden" name="delete_media_id" value="<?php echo (int)$row['item_id']; ?>">
                                        <button type="submit" class="text-[10px] font-black uppercase tracking-widest text-red-500 hover:text-red-600">Delete</button>
                                    </form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                        <?php if (empty($section['rows'])): ?>
                            <p class="text-gray-400 text-sm">No items found.</p>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
