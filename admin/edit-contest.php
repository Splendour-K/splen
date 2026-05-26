<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_role('admin');

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header("Location: contests.php"); exit(); }

// Load contest (admin — no brand restriction)
$stmt = $pdo->prepare("
    SELECT c.*, b.brand_name
    FROM contests c
    JOIN brands b ON c.brand_id = b.id
    WHERE c.id = ?
");
$stmt->execute([$id]);
$contest = $stmt->fetch();
if (!$contest) { header("Location: contests.php"); exit(); }

// Load rewards
$rewards = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM contest_rewards WHERE contest_id = ? ORDER BY position_number ASC");
    $stmt->execute([$id]);
    $rewards = $stmt->fetchAll();
} catch (Exception $e) {}

// Load reference links
$ref_links = [];
try {
    $stmt = $pdo->prepare("SELECT link_url FROM contest_reference_links WHERE contest_id = ? ORDER BY id ASC");
    $stmt->execute([$id]);
    $ref_links = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title                    = trim($_POST['title'] ?? '');
    $description              = $_POST['description'] ?? '';
    $category                 = $_POST['category'] ?? '';
    $status                   = $_POST['status'] ?? $contest['status'];
    $submission_deadline      = $_POST['submission_deadline'] ?? '';
    $winner_announcement_date = $_POST['winner_announcement_date'] ?? '';
    $terms_conditions         = $_POST['terms_conditions'] ?? '';
    $reference_links_raw      = array_values(array_filter(array_map('trim', $_POST['reference_links'] ?? [])));
    $prize_amounts_post       = array_map('floatval', $_POST['prize_amounts'] ?? []);
    $position_names_post      = array_map('trim', $_POST['position_names'] ?? []);

    $allowed_statuses = ['draft', 'live', 'closed', 'completed', 'announced'];
    if (!in_array($status, $allowed_statuses)) $status = $contest['status'];

    if (!$title || !$submission_deadline) {
        $error = "Title and Submission Deadline are required.";
    } elseif (strlen($title) < 3) {
        $error = "Title must be at least 3 characters.";
    } else {
        if (!$error) {
            // Handle featured image upload
            $new_featured_image = null;
            if (!empty($_FILES['featured_image']['name']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
                $fi_dir = "../assets/uploads/listings/";
                if (!is_dir($fi_dir)) mkdir($fi_dir, 0755, true);
                $fi_ext  = strtolower(pathinfo($_FILES['featured_image']['name'], PATHINFO_EXTENSION));
                $fi_size = $_FILES['featured_image']['size'];
                if (in_array($fi_ext, ['jpg','jpeg','png','webp']) && $fi_size <= 5 * 1024 * 1024) {
                    $fi_name = "contest_{$id}_admin_" . time() . ".{$fi_ext}";
                    if (move_uploaded_file($_FILES['featured_image']['tmp_name'], $fi_dir . $fi_name)) {
                        $new_featured_image = "assets/uploads/listings/{$fi_name}";
                    }
                } elseif ($fi_size > 5 * 1024 * 1024) {
                    $error = "Featured image must be under 5 MB.";
                }
            }

            if (!$error) {
                $update_sql = "UPDATE contests SET title=?, description=?, category=?, status=?, submission_deadline=?, winner_announcement_date=?, terms_conditions=?";
                $update_params = [$title, $description, $category, $status, $submission_deadline, $winner_announcement_date ?: null, $terms_conditions];

                if ($new_featured_image !== null) {
                    $update_sql .= ", featured_image=?";
                    $update_params[] = $new_featured_image;
                }
                $update_sql .= " WHERE id=?";
                $update_params[] = $id;

                try {
                    $pdo->prepare($update_sql)->execute($update_params);

                    // Update prize amounts (admin can always edit)
                    if (!empty($prize_amounts_post)) {
                        $valid_prizes = true;
                        foreach ($prize_amounts_post as $amt) {
                            if ($amt <= 0) { $valid_prizes = false; break; }
                        }
                        if ($valid_prizes) {
                            $pdo->prepare("DELETE FROM contest_rewards WHERE contest_id = ?")->execute([$id]);
                            $rew_stmt = $pdo->prepare("INSERT INTO contest_rewards (contest_id, position_number, position_name, reward_amount, currency) VALUES (?, ?, ?, ?, ?)");
                            $new_total = 0;
                            foreach ($prize_amounts_post as $pi => $amt) {
                                $pos_name = $position_names_post[$pi] ?? ('Position ' . ($pi + 1));
                                $rew_stmt->execute([$id, $pi + 1, $pos_name ?: ('Position ' . ($pi + 1)), $amt, $contest['currency']]);
                                $new_total += $amt;
                            }
                            $pdo->prepare("UPDATE contests SET total_contest_budget=? WHERE id=?")->execute([$new_total, $id]);
                        }
                    }

                    // Update reference links
                    try {
                        $pdo->prepare("DELETE FROM contest_reference_links WHERE contest_id = ?")->execute([$id]);
                        if (!empty($reference_links_raw)) {
                            $rl_stmt = $pdo->prepare("INSERT INTO contest_reference_links (contest_id, link_url, link_type) VALUES (?, ?, 'inspiration')");
                            foreach ($reference_links_raw as $rl) {
                                if (strlen($rl) >= 10) $rl_stmt->execute([$id, $rl]);
                            }
                        }
                    } catch (Exception $rl_e) {}

                    log_activity($_SESSION['user_id'], 'Contest Edited by Admin', "Contest #$id '{$title}' updated, status: $status");
                    $success = "Contest updated successfully!";

                    // Reload
                    $stmt = $pdo->prepare("SELECT c.*, b.brand_name FROM contests c JOIN brands b ON c.brand_id = b.id WHERE c.id = ?");
                    $stmt->execute([$id]);
                    $contest = $stmt->fetch();

                    try {
                        $stmt = $pdo->prepare("SELECT * FROM contest_rewards WHERE contest_id = ? ORDER BY position_number ASC");
                        $stmt->execute([$id]);
                        $rewards = $stmt->fetchAll();
                    } catch (Exception $e) {}

                    try {
                        $stmt = $pdo->prepare("SELECT link_url FROM contest_reference_links WHERE contest_id = ? ORDER BY id ASC");
                        $stmt->execute([$id]);
                        $ref_links = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    } catch (Exception $e) {}

                } catch (Exception $e) {
                    $error = "Update failed: " . $e->getMessage();
                }
            }
        }
    }
}

$icons = ['🥇','🥈','🥉','🏅','🏅','🏅'];

include '../includes/header.php';
?>

<div class="pt-24 min-h-screen bg-gray-50 dark:bg-gray-950">
    <div class="max-w-7xl mx-auto px-6 md:px-12 flex flex-col md:flex-row gap-8 py-8">
        <?php include 'dashboard_sidebar.php'; ?>

        <main class="flex-1 space-y-8">

            <!-- Header -->
            <header class="p-8 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm relative overflow-hidden">
                <div aria-hidden="true" class="absolute top-0 right-0 -mr-16 -mt-16 w-64 h-64 bg-secondary/5 rounded-full blur-3xl"></div>
                <div class="relative flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <a href="contests.php" class="text-sm font-bold text-gray-400 hover:text-secondary transition mb-3 inline-flex items-center gap-1">← All Contests</a>
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Admin — Contest Edit</p>
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Edit Contest</h2>
                        <p class="text-gray-500 mt-1">Brand: <span class="font-bold text-secondary"><?php echo e($contest['brand_name']); ?></span></p>
                    </div>
                    <div class="flex flex-col items-end gap-2">
                        <span class="px-3 py-1.5 rounded-full text-[10px] font-black uppercase
                            <?php echo $contest['status'] === 'live'       ? 'bg-green-100 text-green-700' :
                                      ($contest['status'] === 'closed'     ? 'bg-orange-100 text-orange-700' :
                                      ($contest['status'] === 'completed'  ? 'bg-blue-100 text-blue-700' :
                                      ($contest['status'] === 'announced'  ? 'bg-purple-100 text-purple-700' :
                                                                             'bg-gray-100 text-gray-600'))); ?>">
                            <?php echo ucfirst($contest['status']); ?>
                        </span>
                        <span class="text-xs text-gray-400">Prize Pool: <?php echo format_money((float)$contest['total_contest_budget'], $contest['currency']); ?></span>
                        <a href="contest-submissions.php?contest_id=<?php echo $id; ?>&filter=all" class="text-xs font-bold text-secondary hover:underline">
                            📊 View Submissions →
                        </a>
                    </div>
                </div>
            </header>

            <?php if ($success): ?>
                <div class="p-5 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-2xl text-green-800 dark:text-green-400 font-bold flex items-center gap-3">
                    <span class="text-xl">✅</span> <?php echo e($success); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="p-5 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-2xl text-red-800 dark:text-red-400 font-bold flex items-center gap-3">
                    <span class="text-xl">⚠️</span> <?php echo e($error); ?>
                </div>
            <?php endif; ?>

            <div class="p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-2xl text-blue-800 dark:text-blue-300 text-sm font-medium">
                🛡️ You are editing as <strong>admin</strong>. Prize amounts and status can be changed freely. No wallet checks are applied.
            </div>

            <form method="POST" enctype="multipart/form-data" class="space-y-8">

                <!-- Section 1: Core Details -->
                <section class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm space-y-6">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
                        <span class="w-8 h-8 rounded-lg bg-secondary text-white flex items-center justify-center font-black text-sm">1</span>
                        Contest Details
                    </h3>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Contest Title <span class="text-red-500">*</span></label>
                        <input type="text" name="title" value="<?php echo e($contest['title']); ?>" minlength="3" maxlength="120" required
                               class="w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Description</label>
                        <div class="ql-wrap" id="description_wrap"><div id="description_editor"></div></div>
                        <input type="hidden" name="description" id="description_h">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Category</label>
                            <select name="category" class="w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary">
                                <option value="">Select category</option>
                                <?php $cats = [
                                    'beauty'=>'Beauty','skincare'=>'Skincare','fashion'=>'Fashion & Style',
                                    'food'=>'Food & Beverage','tech'=>'Tech & Innovation','apps'=>'Mobile Apps',
                                    'books'=>'Books & Education','wellness'=>'Health & Wellness','sports'=>'Sports & Fitness',
                                    'gaming'=>'Gaming','music'=>'Music & Entertainment','travel'=>'Travel',
                                    'finance'=>'Finance & Fintech','lifestyle'=>'Lifestyle','home'=>'Home & Lifestyle',
                                    'automotive'=>'Automotive','pets'=>'Pets','product'=>'Product Demo',
                                    'education'=>'Education','other'=>'Other',
                                ];
                                foreach ($cats as $val => $label): ?>
                                    <option value="<?php echo $val; ?>" <?php echo ($contest['category'] ?? '') === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Status <span class="text-xs font-normal text-gray-400">(Admin override)</span></label>
                            <select name="status" class="w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary font-bold">
                                <?php foreach (['draft'=>'Draft','live'=>'Live','closed'=>'Closed','completed'=>'Completed','announced'=>'Announced'] as $val => $label): ?>
                                    <option value="<?php echo $val; ?>" <?php echo $contest['status'] === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </section>

                <!-- Section 2: Featured Image -->
                <section class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm space-y-5">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
                        <span class="w-8 h-8 rounded-lg bg-blue-500/10 text-blue-500 flex items-center justify-center text-sm">🖼️</span>
                        Featured Image
                        <span class="ml-2 text-xs font-normal text-gray-400 bg-gray-100 dark:bg-gray-800 px-2 py-0.5 rounded-full">Optional</span>
                    </h3>
                    <p class="text-sm text-gray-500 -mt-2">Leave empty to keep the current image. JPG, PNG, WEBP · max 5 MB.</p>
                    <label class="block relative w-full rounded-2xl overflow-hidden cursor-pointer group" style="aspect-ratio:16/7;" id="featured-img-label">
                        <?php if (!empty($contest['featured_image'])): ?>
                            <img id="featured-img-preview" src="<?php echo APP_URL . e($contest['featured_image']); ?>" alt="Current" class="w-full h-full object-cover absolute inset-0">
                            <div id="featured-img-placeholder" class="hidden absolute inset-0 flex flex-col items-center justify-center bg-gray-50 dark:bg-gray-800 border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-2xl">
                                <p class="text-sm font-bold text-gray-400">Click to change image</p>
                            </div>
                        <?php else: ?>
                            <div id="featured-img-placeholder" class="absolute inset-0 flex flex-col items-center justify-center bg-gray-50 dark:bg-gray-800 border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-2xl group-hover:border-secondary transition">
                                <p class="text-sm font-bold text-gray-400 group-hover:text-secondary transition">Click to upload a featured image</p>
                                <p class="text-xs text-gray-400 mt-1">16:9 · JPG, PNG, WEBP · max 5 MB</p>
                            </div>
                            <img id="featured-img-preview" class="w-full h-full object-cover absolute inset-0 hidden rounded-2xl" alt="Preview">
                        <?php endif; ?>
                        <div class="absolute inset-0 bg-black/40 text-white text-sm font-bold flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none rounded-2xl">📷 Change Image</div>
                        <input type="file" name="featured_image" accept="image/jpeg,image/png,image/webp" class="absolute inset-0 opacity-0 cursor-pointer w-full h-full"
                               onchange="(function(i){const p=document.getElementById('featured-img-preview'),ph=document.getElementById('featured-img-placeholder');if(i.files&&i.files[0]){const r=new FileReader();r.onload=e=>{p.src=e.target.result;p.classList.remove('hidden');ph.classList.add('hidden');};r.readAsDataURL(i.files[0]);}})(this)">
                    </label>
                </section>

                <!-- Section 3: Timeline -->
                <section class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm space-y-6">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
                        <span class="w-8 h-8 rounded-lg bg-secondary text-white flex items-center justify-center font-black text-sm">3</span>
                        Timeline
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Submission Deadline <span class="text-red-500">*</span></label>
                            <input type="datetime-local" name="submission_deadline" value="<?php echo e(str_replace(' ', 'T', $contest['submission_deadline'] ?? '')); ?>" required
                                   class="w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Winner Announcement Date</label>
                            <input type="datetime-local" name="winner_announcement_date" value="<?php echo e(str_replace(' ', 'T', $contest['winner_announcement_date'] ?? '')); ?>"
                                   class="w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary">
                        </div>
                    </div>
                </section>

                <!-- Section 4: Terms & Conditions -->
                <section class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm space-y-6">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
                        <span class="w-8 h-8 rounded-lg bg-secondary text-white flex items-center justify-center font-black text-sm">4</span>
                        Terms & Conditions
                    </h3>
                    <div>
                        <div class="ql-wrap" id="terms_wrap"><div id="terms_editor"></div></div>
                        <input type="hidden" name="terms_conditions" id="terms_conditions_h">
                    </div>
                </section>

                <!-- Section 5: Prize Distribution -->
                <section class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm space-y-6">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
                        <span class="w-8 h-8 rounded-lg bg-amber-400/20 text-amber-600 flex items-center justify-center text-sm">🏆</span>
                        Prize Distribution
                        <span class="text-xs font-normal text-gray-400 bg-gray-100 dark:bg-gray-800 px-2 py-0.5 rounded-full">Admin override — no wallet check</span>
                    </h3>

                    <div id="prize-rows-edit" class="space-y-3">
                        <?php foreach ($rewards as $ri => $reward): ?>
                        <div class="flex gap-3 items-center">
                            <div class="w-10 h-10 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-lg flex-shrink-0"><?php echo $icons[$ri] ?? '🏅'; ?></div>
                            <input type="text" name="position_names[]" value="<?php echo e($reward['position_name']); ?>"
                                   class="w-36 px-3 py-2.5 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary text-sm font-bold">
                            <input type="number" name="prize_amounts[]" step="0.01" min="0.01" value="<?php echo (float)$reward['reward_amount']; ?>"
                                   class="flex-1 px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary font-bold text-lg prize-amount-edit"
                                   oninput="calcEditTotal()">
                            <span class="text-sm font-bold text-gray-500 flex-shrink-0"><?php echo e($contest['currency']); ?></span>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($rewards)): ?>
                        <p class="text-sm text-gray-400 italic">No prize positions defined. Add them below.</p>
                        <?php endif; ?>
                    </div>

                    <button type="button" onclick="addPrizeRow()" class="flex items-center gap-2 px-4 py-3 border-2 border-dashed border-gray-200 dark:border-gray-700 text-gray-500 rounded-xl hover:border-secondary hover:text-secondary transition text-sm font-bold w-full justify-center">
                        + Add Position
                    </button>

                    <div class="p-4 bg-secondary/5 border border-secondary/20 rounded-xl flex items-center justify-between">
                        <p class="text-sm font-bold text-gray-700 dark:text-gray-300">Total Prize Pool</p>
                        <p class="text-xl font-black text-secondary" id="edit-prize-total">
                            <?php echo format_money((float)$contest['total_contest_budget'], $contest['currency']); ?>
                        </p>
                    </div>

                    <script>
                    (function() {
                        const SYM = <?php echo json_encode(get_currency_symbol($contest['currency'])); ?>;
                        window.calcEditTotal = function() {
                            let total = 0;
                            document.querySelectorAll('.prize-amount-edit').forEach(i => total += parseFloat(i.value || 0));
                            document.getElementById('edit-prize-total').textContent = SYM + total.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});
                        };
                        const PRIZE_ICONS = <?php echo json_encode($icons); ?>;
                        let _prizeCount = <?php echo count($rewards); ?>;
                        window.addPrizeRow = function() {
                            const icon = PRIZE_ICONS[_prizeCount] || '🏅';
                            const row  = document.createElement('div');
                            row.className = 'flex gap-3 items-center';
                            row.innerHTML = `<div class="w-10 h-10 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-lg flex-shrink-0">${icon}</div>
                                <input type="text" name="position_names[]" placeholder="Position name" class="w-36 px-3 py-2.5 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary text-sm font-bold">
                                <input type="number" name="prize_amounts[]" step="0.01" min="0.01" placeholder="0.00" class="flex-1 px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary font-bold text-lg prize-amount-edit" oninput="calcEditTotal()">
                                <span class="text-sm font-bold text-gray-500 flex-shrink-0"><?php echo e($contest['currency']); ?></span>
                                <button type="button" onclick="this.parentElement.remove(); calcEditTotal();" class="w-9 h-9 bg-red-100 text-red-500 font-black rounded-xl hover:bg-red-500 hover:text-white transition flex items-center justify-center flex-shrink-0">×</button>`;
                            document.getElementById('prize-rows-edit').appendChild(row);
                            _prizeCount++;
                        };
                        calcEditTotal();
                    })();
                    </script>
                </section>

                <!-- Section 6: Reference Videos -->
                <section class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm space-y-5">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
                        <span class="w-8 h-8 rounded-lg bg-purple-500/10 text-purple-500 flex items-center justify-center text-sm">🎬</span>
                        Reference Videos
                        <span class="text-xs font-medium text-gray-400">(Optional)</span>
                    </h3>

                    <div id="ref-links-container" class="space-y-3">
                        <?php if (!empty($ref_links)): ?>
                            <?php foreach ($ref_links as $rl): ?>
                                <div class="flex gap-2 ref-link-row">
                                    <input type="url" name="reference_links[]" value="<?php echo e($rl); ?>" placeholder="https://…"
                                           class="flex-1 px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary text-sm">
                                    <button type="button" onclick="this.parentElement.remove()" class="px-4 py-3 bg-red-100 dark:bg-red-900/20 text-red-500 font-black rounded-xl hover:bg-red-500 hover:text-white transition w-12 flex-shrink-0">×</button>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="flex gap-2 ref-link-row">
                                <input type="url" name="reference_links[]" placeholder="https://www.tiktok.com/@example/video/… (optional)"
                                       class="flex-1 px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary text-sm">
                                <button type="button" onclick="this.parentElement.remove()" class="px-4 py-3 bg-red-100 dark:bg-red-900/20 text-red-500 font-black rounded-xl hover:bg-red-500 hover:text-white transition w-12 flex-shrink-0">×</button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <button type="button" onclick="addRefLink()"
                            class="flex items-center gap-2 px-4 py-3 border-2 border-dashed border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 rounded-xl hover:border-secondary hover:text-secondary transition text-sm font-bold w-full justify-center">
                        <span class="text-base leading-none">+</span> Add reference link
                    </button>
                </section>

                <!-- Submit -->
                <div class="flex gap-4">
                    <button type="submit" class="flex-1 py-4 bg-secondary text-white font-bold rounded-xl hover:scale-105 transition shadow-lg shadow-secondary/20 text-lg">
                        💾 Save Changes
                    </button>
                    <a href="contests.php" class="py-4 px-8 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-bold rounded-xl hover:bg-gray-200 dark:hover:bg-gray-700 transition text-lg">
                        Cancel
                    </a>
                </div>

            </form>
        </main>
    </div>
</div>

<script>
function addRefLink() {
    const container = document.getElementById('ref-links-container');
    const row = document.createElement('div');
    row.className = 'flex gap-2 ref-link-row';
    row.innerHTML = `<input type="url" name="reference_links[]" placeholder="https://..." class="flex-1 px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary text-sm">
                     <button type="button" onclick="this.parentElement.remove()" class="px-4 py-3 bg-red-100 dark:bg-red-900/20 text-red-500 font-black rounded-xl hover:bg-red-500 hover:text-white transition w-12 flex-shrink-0">×</button>`;
    container.appendChild(row);
}
</script>

<?php
// ── Quill rich-text initialisation ────────────────────────────
$desc_content  = addslashes(strip_tags($contest['description'] ?? '', '<b><i><u><s><em><strong><h1><h2><h3><ul><ol><li><p><br><blockquote><a>'));
$terms_content = addslashes(strip_tags($contest['terms_conditions'] ?? '', '<b><i><u><s><em><strong><h1><h2><h3><ul><ol><li><p><br><blockquote><a>'));
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const descQ  = new Quill('#description_editor', { theme:'snow', placeholder:'Describe the contest…' });
    const termsQ = new Quill('#terms_editor',       { theme:'snow', placeholder:'Contest rules & terms…' });

    const descRaw  = <?php echo json_encode($contest['description']      ?? ''); ?>;
    const termsRaw = <?php echo json_encode($contest['terms_conditions'] ?? ''); ?>;
    if (descRaw)  descQ.clipboard.dangerouslyPasteHTML(descRaw);
    if (termsRaw) termsQ.clipboard.dangerouslyPasteHTML(termsRaw);

    document.querySelector('form').addEventListener('submit', function() {
        document.getElementById('description_h').value      = descQ.root.innerHTML;
        document.getElementById('terms_conditions_h').value = termsQ.root.innerHTML;
    });
});
</script>

<?php include '../includes/footer.php'; ?>
