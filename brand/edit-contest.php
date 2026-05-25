<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_role('brand');

$stmt = $pdo->prepare("SELECT * FROM brands WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$brand = $stmt->fetch();
require_brand_record($brand);

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header("Location: " . APP_URL . "brand/my-contests.php"); exit(); }

// Load contest — must belong to this brand
$stmt = $pdo->prepare("SELECT * FROM contests WHERE id = ? AND brand_id = ?");
$stmt->execute([$id, $brand['id']]);
$contest = $stmt->fetch();
if (!$contest) { header("Location: " . APP_URL . "brand/my-contests.php"); exit(); }

// Load existing contest_rewards
$rewards = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM contest_rewards WHERE contest_id = ? ORDER BY position_number ASC");
    $stmt->execute([$id]);
    $rewards = $stmt->fetchAll();
} catch (Exception $e) {}

// Load existing reference links
$ref_links = [];
try {
    $stmt = $pdo->prepare("SELECT link_url FROM contest_reference_links WHERE contest_id = ? ORDER BY id ASC");
    $stmt->execute([$id]);
    $ref_links = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

// Check if winners have been selected (lock prize amounts if so)
$winners_exist = false;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM contest_submissions WHERE contest_id = ? AND status = 'winner'");
    $stmt->execute([$id]);
    $winners_exist = (int)$stmt->fetchColumn() > 0;
} catch (Exception $e) {}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title                    = trim($_POST['title'] ?? '');
    $description              = $_POST['description'] ?? '';
    $category                 = $_POST['category'] ?? '';
    $submission_deadline      = $_POST['submission_deadline'] ?? '';
    $winner_announcement_date = $_POST['winner_announcement_date'] ?? '';
    $terms_conditions         = $_POST['terms_conditions'] ?? '';
    $reference_links_raw      = array_values(array_filter(array_map('trim', $_POST['reference_links'] ?? [])));

    // Prize editing (only if no winners selected yet)
    $prize_amounts_post = array_map('floatval', $_POST['prize_amounts'] ?? []);
    $position_names_post = array_map('trim', $_POST['position_names'] ?? []);

    if (!$title || !$submission_deadline) {
        $error = "Title and Submission Deadline are required.";
    } elseif (strlen($title) < 3) {
        $error = "Title must be at least 3 characters.";
    } else {
        // Validate prize amounts if prizes are editable
        if (!$winners_exist && !empty($prize_amounts_post)) {
            $new_total = array_sum($prize_amounts_post);
            $original_budget = (float)$contest['total_contest_budget'];
            if ($new_total > $original_budget + 0.01) { // allow tiny float difference
                $error = "Total prize amounts (" . format_money($new_total, $contest['currency']) . ") cannot exceed the original reserved budget (" . format_money($original_budget, $contest['currency']) . ").";
            }
            foreach ($prize_amounts_post as $amt) {
                if ($amt <= 0) {
                    $error = "Each prize position must have an amount greater than 0.";
                    break;
                }
            }
        }

        if (!$error) {
            // Handle featured image upload
            $new_featured_image = null;
            if (!empty($_FILES['featured_image']['name']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
                $fi_dir = "../assets/uploads/listings/";
                if (!is_dir($fi_dir)) mkdir($fi_dir, 0755, true);
                $fi_ext  = strtolower(pathinfo($_FILES['featured_image']['name'], PATHINFO_EXTENSION));
                $fi_size = $_FILES['featured_image']['size'];
                if (in_array($fi_ext, ['jpg','jpeg','png','webp']) && $fi_size <= 5 * 1024 * 1024) {
                    $fi_name = "contest_{$id}_" . time() . ".{$fi_ext}";
                    if (move_uploaded_file($_FILES['featured_image']['tmp_name'], $fi_dir . $fi_name)) {
                        $new_featured_image = "assets/uploads/listings/{$fi_name}";
                    }
                } elseif ($fi_size > 5 * 1024 * 1024) {
                    $error = "Featured image must be under 5 MB.";
                }
            }

            if (!$error) {
                // Build UPDATE query
                $update_sql = "UPDATE contests SET title=?, description=?, category=?, submission_deadline=?, winner_announcement_date=?, terms_conditions=?";
                $update_params = [$title, $description, $category, $submission_deadline, $winner_announcement_date ?: null, $terms_conditions];

                if ($new_featured_image !== null) {
                    $update_sql .= ", featured_image=?";
                    $update_params[] = $new_featured_image;
                }
                $update_sql .= " WHERE id=? AND brand_id=?";
                $update_params[] = $id;
                $update_params[] = $brand['id'];

                try {
                    $pdo->prepare($update_sql)->execute($update_params);

                    // Update prize amounts (only if no winners yet)
                    if (!$winners_exist && !empty($prize_amounts_post)) {
                        $pdo->prepare("DELETE FROM contest_rewards WHERE contest_id = ?")->execute([$id]);
                        $rew_stmt = $pdo->prepare("INSERT INTO contest_rewards (contest_id, position_number, position_name, reward_amount, currency) VALUES (?, ?, ?, ?, ?)");
                        $new_total = 0;
                        foreach ($prize_amounts_post as $pi => $amt) {
                            $pos_name = $position_names_post[$pi] ?? ('Position ' . ($pi + 1));
                            $rew_stmt->execute([$id, $pi + 1, $pos_name ?: ('Position ' . ($pi + 1)), $amt, $contest['currency']]);
                            $new_total += $amt;
                        }
                        // Update contest total_contest_budget to match new sum
                        $pdo->prepare("UPDATE contests SET total_contest_budget=? WHERE id=?")->execute([$new_total, $id]);
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

                    $success = "Contest updated successfully!";

                    // Reload contest data
                    $stmt = $pdo->prepare("SELECT * FROM contests WHERE id = ?");
                    $stmt->execute([$id]);
                    $contest = $stmt->fetch();

                    // Reload rewards
                    try {
                        $stmt = $pdo->prepare("SELECT * FROM contest_rewards WHERE contest_id = ? ORDER BY position_number ASC");
                        $stmt->execute([$id]);
                        $rewards = $stmt->fetchAll();
                    } catch (Exception $e) {}

                    // Reload ref links
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

include '../includes/header.php';
?>

<div class="pt-24 min-h-screen bg-gray-50 dark:bg-gray-950">
    <div class="max-w-7xl mx-auto px-6 md:px-12 flex flex-col md:flex-row gap-8 py-8">
        <?php include '../includes/brand_sidebar.php'; ?>

        <main class="flex-1 space-y-8">
            <header class="p-8 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm relative overflow-hidden">
                <div aria-hidden="true" class="absolute top-0 right-0 -mr-16 -mt-16 w-64 h-64 bg-secondary/5 rounded-full blur-3xl"></div>
                <div class="relative flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <a href="<?php echo APP_URL; ?>brand/my-contests.php" class="text-sm font-bold text-gray-400 hover:text-secondary transition mb-3 inline-flex items-center gap-1">← My Contests</a>
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Edit Contest</h2>
                        <p class="text-gray-500 mt-1">Editing: <span class="font-bold text-secondary"><?php echo e($contest['title']); ?></span></p>
                    </div>
                    <div class="flex flex-col items-end gap-1">
                        <span class="px-3 py-1.5 bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 rounded-full text-[10px] font-black uppercase"><?php echo ucfirst($contest['status']); ?></span>
                        <span class="text-xs text-gray-400">Budget: <?php echo format_money((float)$contest['total_contest_budget'], $contest['currency']); ?> <?php echo e($contest['currency']); ?></span>
                    </div>
                </div>
            </header>

            <?php if ($success): ?>
                <div class="p-6 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-2xl text-green-800 dark:text-green-400 font-bold flex items-center gap-3">
                    <span class="text-xl">✅</span> <?php echo e($success); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="p-6 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-2xl text-red-800 dark:text-red-400 font-bold flex items-center gap-3">
                    <span class="text-xl">⚠️</span> <?php echo e($error); ?>
                </div>
            <?php endif; ?>

            <div class="p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-2xl text-blue-800 dark:text-blue-300 text-sm font-medium">
                ℹ️ Editing this contest will <strong>not</strong> affect any existing submissions already received. Prize amounts cannot be increased beyond the originally reserved budget.
            </div>

            <form method="POST" enctype="multipart/form-data" class="space-y-8">

                <!-- Section 1: Contest Details -->
                <section class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm space-y-6">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
                        <span class="w-8 h-8 rounded-lg bg-secondary text-white flex items-center justify-center font-black text-sm">1</span>
                        Contest Details
                    </h3>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Contest Title <span class="text-red-500">*</span></label>
                        <input type="text" name="title" value="<?php echo e($contest['title']); ?>" minlength="3" maxlength="120" required class="w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Description</label>
                        <div class="ql-wrap" id="description_wrap"><div id="description_editor"></div></div>
                        <input type="hidden" name="description" id="description_h">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Category</label>
                        <select name="category" class="w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary">
                            <option value="">Select a category</option>
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
                </section>

                <!-- Section 2: Featured Image -->
                <section class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm space-y-5">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
                        <span class="w-8 h-8 rounded-lg bg-blue-500/10 text-blue-500 flex items-center justify-center text-sm">🖼️</span>
                        Featured Image <span class="ml-2 text-xs font-normal text-gray-400 bg-gray-100 dark:bg-gray-800 px-2 py-0.5 rounded-full">Optional</span>
                    </h3>
                    <p class="text-sm text-gray-500 -mt-2">Leave empty to keep the current image. JPG, PNG, WEBP · max 5 MB.</p>
                    <label class="block relative w-full rounded-2xl overflow-hidden cursor-pointer group" style="aspect-ratio:16/7;" id="featured-img-label">
                        <?php if (!empty($contest['featured_image'])): ?>
                            <img id="featured-img-preview" src="<?php echo APP_URL . e($contest['featured_image']); ?>" alt="Current" class="w-full h-full object-cover absolute inset-0">
                            <div id="featured-img-placeholder" class="hidden absolute inset-0 flex flex-col items-center justify-center bg-gray-50 dark:bg-gray-800 border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-2xl">
                                <p class="text-sm font-bold text-gray-400">Click to upload</p>
                            </div>
                        <?php else: ?>
                            <div id="featured-img-placeholder" class="absolute inset-0 flex flex-col items-center justify-center bg-gray-50 dark:bg-gray-800 border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-2xl group-hover:border-secondary transition">
                                <svg class="w-10 h-10 text-gray-300 mb-2 group-hover:text-secondary transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <p class="text-sm font-bold text-gray-400 group-hover:text-secondary transition">Click to upload a featured image</p>
                                <p class="text-xs text-gray-400 mt-1">16:9 · JPG, PNG, WEBP · max 5 MB</p>
                            </div>
                            <img id="featured-img-preview" class="w-full h-full object-cover absolute inset-0 hidden rounded-2xl" alt="Preview">
                        <?php endif; ?>
                        <div class="absolute inset-0 bg-black/40 text-white text-sm font-bold flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none rounded-2xl">📷 Change Image</div>
                        <input type="file" name="featured_image" accept="image/jpeg,image/png,image/webp" class="absolute inset-0 opacity-0 cursor-pointer w-full h-full" onchange="(function(i){const p=document.getElementById('featured-img-preview'),ph=document.getElementById('featured-img-placeholder');if(i.files&&i.files[0]){const r=new FileReader();r.onload=e=>{p.src=e.target.result;p.classList.remove('hidden');ph.classList.add('hidden');};r.readAsDataURL(i.files[0]);}})(this)">
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
                            <input type="datetime-local" name="submission_deadline" value="<?php echo e(str_replace(' ', 'T', $contest['submission_deadline'] ?? '')); ?>" required class="w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary">
                            <p class="text-xs text-gray-500 mt-1">You can extend but not set a past date.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Winner Announcement Date</label>
                            <input type="datetime-local" name="winner_announcement_date" value="<?php echo e(str_replace(' ', 'T', $contest['winner_announcement_date'] ?? '')); ?>" class="w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary">
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
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Contest Rules</label>
                        <div class="ql-wrap" id="terms_wrap"><div id="terms_editor"></div></div>
                        <input type="hidden" name="terms_conditions" id="terms_conditions_h">
                    </div>
                </section>

                <!-- Section 5: Prize Distribution -->
                <section class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm space-y-6">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
                        <span class="w-8 h-8 rounded-lg bg-amber-400/20 text-amber-600 flex items-center justify-center text-sm">🏆</span>
                        Prize Distribution
                    </h3>

                    <?php if ($winners_exist): ?>
                        <div class="p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl text-amber-800 dark:text-amber-400 text-sm font-medium">
                            🔒 Prize amounts are locked because winners have already been selected for this contest.
                        </div>
                        <?php foreach ($rewards as $ri => $reward):
                            $icons = ['🥇','🥈','🥉','🏅','🏅','🏅'];
                            $icon = $icons[$ri] ?? '🏅';
                        ?>
                        <div class="flex items-center gap-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-xl">
                            <span class="text-2xl"><?php echo $icon; ?></span>
                            <span class="font-bold text-gray-900 dark:text-white flex-1"><?php echo e($reward['position_name']); ?></span>
                            <span class="font-black text-secondary text-lg"><?php echo format_money((float)$reward['reward_amount'], $reward['currency']); ?></span>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-sm text-gray-500">Set the prize for each winner position. Total cannot exceed the originally reserved budget of <strong><?php echo format_money((float)$contest['total_contest_budget'], $contest['currency']); ?></strong>.</p>

                        <div id="prize-rows-edit" class="space-y-3">
                            <?php foreach ($rewards as $ri => $reward):
                                $icons = ['🥇','🥈','🥉','🏅','🏅','🏅'];
                                $icon = $icons[$ri] ?? '🏅';
                            ?>
                            <div class="flex gap-3 items-center">
                                <div class="w-10 h-10 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-lg flex-shrink-0"><?php echo $icon; ?></div>
                                <input type="text" name="position_names[]" value="<?php echo e($reward['position_name']); ?>" class="w-36 px-3 py-2.5 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary text-sm font-bold">
                                <input type="number" name="prize_amounts[]" step="0.01" min="0.01" value="<?php echo (float)$reward['reward_amount']; ?>" class="flex-1 px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary font-bold text-lg prize-amount-edit" oninput="calcEditTotal()">
                                <span class="text-sm font-bold text-gray-500 flex-shrink-0"><?php echo e($contest['currency']); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="p-4 bg-secondary/5 border border-secondary/20 rounded-xl flex items-center justify-between">
                            <div>
                                <p class="text-sm font-bold text-gray-700 dark:text-gray-300">New Total</p>
                                <p class="text-xs text-gray-500 mt-0.5">Max: <?php echo format_money((float)$contest['total_contest_budget'], $contest['currency']); ?></p>
                            </div>
                            <p class="text-xl font-black text-secondary" id="edit-prize-total">
                                <?php echo format_money((float)$contest['total_contest_budget'], $contest['currency']); ?>
                            </p>
                        </div>

                        <script>
                        (function() {
                            const SYM = <?php echo json_encode(get_currency_symbol($contest['currency'])); ?>;
                            const MAX = <?php echo (float)$contest['total_contest_budget']; ?>;
                            window.calcEditTotal = function() {
                                const inputs = document.querySelectorAll('.prize-amount-edit');
                                let total = 0;
                                inputs.forEach(inp => total += parseFloat(inp.value || 0));
                                const el = document.getElementById('edit-prize-total');
                                el.textContent = SYM + total.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                                el.className = 'text-xl font-black ' + (total > MAX + 0.01 ? 'text-red-500' : 'text-secondary');
                            };
                            calcEditTotal();
                        })();
                        </script>
                    <?php endif; ?>
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
                                    <input type="url" name="reference_links[]" value="<?php echo e($rl); ?>" placeholder="https://..." class="flex-1 px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary text-sm">
                                    <button type="button" onclick="this.parentElement.remove()" class="px-4 py-3 bg-red-100 dark:bg-red-900/20 text-red-500 font-black rounded-xl hover:bg-red-500 hover:text-white transition w-12 flex-shrink-0">×</button>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="flex gap-2 ref-link-row">
                                <input type="url" name="reference_links[]" placeholder="https://www.tiktok.com/@example/video/... (optional)" class="flex-1 px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary text-sm">
                                <button type="button" onclick="this.parentElement.remove()" class="px-4 py-3 bg-red-100 dark:bg-red-900/20 text-red-500 font-black rounded-xl hover:bg-red-500 hover:text-white transition w-12 flex-shrink-0">×</button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <button type="button" onclick="addRefLink()" class="flex items-center gap-2 px-4 py-3 border-2 border-dashed border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 rounded-xl hover:border-secondary hover:text-secondary transition text-sm font-bold w-full justify-center">
                        <span class="text-base leading-none">+</span> Add reference link
                    </button>
                </section>

                <div class="flex gap-4">
                    <button type="submit" class="flex-1 py-4 bg-secondary text-white font-bold rounded-xl hover:scale-105 transition shadow-lg shadow-secondary/20 text-lg">
                        Save Changes
                    </button>
                    <a href="<?php echo APP_URL; ?>brand/my-contests.php" class="px-8 py-4 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-bold rounded-xl text-center hover:scale-105 transition">
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
    row.innerHTML = `
        <input type="url" name="reference_links[]" placeholder="https://..." class="flex-1 px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary text-sm">
        <button type="button" onclick="this.parentElement.remove()" class="px-4 py-3 bg-red-100 dark:bg-red-900/20 text-red-500 font-black rounded-xl hover:bg-red-500 hover:text-white transition w-12 flex-shrink-0">×</button>
    `;
    container.appendChild(row);
    row.querySelector('input').focus();
}
</script>

<!-- ── Quill Rich Text Editor ── -->
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<style>
.ql-wrap{border-radius:1rem;overflow:hidden;border:1.5px solid #d1d5db;background:#f8fafc;transition:border-color .2s,box-shadow .2s}
.ql-wrap:focus-within{border-color:#ea580c!important;box-shadow:0 0 0 4px rgba(234,88,12,.12);outline:none}
.dark .ql-wrap{background:#1e293b;border-color:#374151}
.dark .ql-wrap:focus-within{border-color:#ea580c!important;box-shadow:0 0 0 4px rgba(234,88,12,.15)}
.ql-toolbar.ql-snow{border:none!important;border-bottom:1px solid #e2e8f0!important;background:#f1f5f9;padding:8px 12px;font-family:'Urbanist',sans-serif!important}
.dark .ql-toolbar.ql-snow{background:#0f172a;border-bottom-color:#1e293b!important}
.dark .ql-toolbar .ql-stroke{stroke:#94a3b8}.dark .ql-toolbar .ql-fill{fill:#94a3b8}
.dark .ql-toolbar button:hover .ql-stroke,.dark .ql-toolbar button.ql-active .ql-stroke{stroke:#f8fafc}
.dark .ql-toolbar button:hover .ql-fill,.dark .ql-toolbar button.ql-active .ql-fill{fill:#f8fafc}
.dark .ql-toolbar .ql-picker-label{color:#94a3b8}.dark .ql-toolbar .ql-picker-options{background:#0f172a;border-color:#334155}
.dark .ql-toolbar .ql-picker-item:hover,.dark .ql-toolbar .ql-picker-item.ql-selected,.dark .ql-toolbar .ql-active .ql-picker-label{color:#f8fafc}
.ql-container.ql-snow{border:none!important;font-family:'Urbanist',sans-serif!important;font-size:.9375rem}
.ql-editor{min-height:8rem;padding:14px 18px;color:#0f172a}
.ql-editor.ql-blank::before{color:#94a3b8;font-style:normal;left:18px;right:18px}
.dark .ql-editor{color:#f8fafc}.dark .ql-editor.ql-blank::before{color:#475569}
.ql-editor p{margin-bottom:.25em}.ql-editor h2{font-size:1.2em;font-weight:700}
.ql-editor h3{font-size:1.05em;font-weight:600}.ql-editor ul,.ql-editor ol{padding-left:1.5em}
.ql-editor blockquote{border-left:4px solid #ea580c;padding-left:1em;opacity:.8;margin:.3em 0}
.ql-editor a{color:#ea580c;text-decoration:underline}
</style>
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
(function() {
    const TB = [
        [{ header: [2, 3, false] }],
        ['bold', 'italic', 'underline', 'strike'],
        [{ list: 'ordered' }, { list: 'bullet' }],
        ['blockquote', 'link'],
        ['clean']
    ];
    const qlDesc = new Quill('#description_editor', {
        theme: 'snow',
        placeholder: "What's this contest about?",
        modules: { toolbar: TB }
    });
    qlDesc.root.innerHTML = <?php echo json_encode($contest['description'] ?? ''); ?>;

    const qlTerms = new Quill('#terms_editor', {
        theme: 'snow',
        placeholder: 'Contest rules and requirements...',
        modules: { toolbar: TB }
    });
    qlTerms.root.innerHTML = <?php echo json_encode($contest['terms_conditions'] ?? ''); ?>;

    qlDesc.on('text-change',  () => { document.getElementById('description_h').value      = qlDesc.root.innerHTML; });
    qlTerms.on('text-change', () => { document.getElementById('terms_conditions_h').value  = qlTerms.root.innerHTML; });

    document.querySelector('form').addEventListener('submit', function() {
        document.getElementById('description_h').value      = qlDesc.root.innerHTML;
        document.getElementById('terms_conditions_h').value = qlTerms.root.innerHTML;
    });
})();
</script>

<?php include '../includes/footer.php'; ?>
