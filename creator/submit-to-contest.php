<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_role('creator');

$contest_id = $_GET['contest_id'] ?? 0;

$stmt = $pdo->prepare("SELECT * FROM creators WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$creator = $stmt->fetch();

$stmt = $pdo->prepare("SELECT * FROM contests WHERE id = ? AND status = 'live'");
$stmt->execute([$contest_id]);
$contest = $stmt->fetch();

if (!$contest) {
    die("Contest not found or not accepting submissions.");
}

$error = '';
$success = '';

if ($creator['verification_status'] !== 'verified') {
    $error = "You must be verified to submit to contests. Complete your profile verification first.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';

    if (!$title) {
        $error = "Submission title is required.";
    } else if (strtotime($contest['submission_deadline']) <= time()) {
        $error = "This contest is no longer accepting submissions.";
    } else if (!isset($_FILES['video']) || $_FILES['video']['error'] !== UPLOAD_ERR_OK) {
        $error = "Please upload a video file.";
    } else {
        $video_file = $_FILES['video'];
        $allowed_types = ['video/mp4', 'video/quicktime', 'video/x-msvideo'];
        
        if (!in_array($video_file['type'], $allowed_types)) {
            $error = "Only MP4, MOV, and AVI videos are allowed.";
        } else if ($video_file['size'] > 500 * 1024 * 1024) {
            $error = "Video must be less than 500MB.";
        } else {
            $upload_dir = '../uploads/contest_submissions/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_extension = pathinfo($video_file['name'], PATHINFO_EXTENSION);
            $filename = 'contest_' . $contest_id . '_' . $creator['id'] . '_' . time() . '.' . $file_extension;
            $filepath = $upload_dir . $filename;

            if (move_uploaded_file($video_file['tmp_name'], $filepath)) {
                // Real watermarked preview for the brand. Clean file stays locked until winner selection.
                $wm_filename = 'wm_' . $filename;
                $wm_filepath = $upload_dir . $wm_filename;
                @set_time_limit(180);
                generate_video_watermark($filepath, $wm_filepath);

                $clean_rel = '/uploads/contest_submissions/' . $filename;
                $wm_rel    = file_exists($wm_filepath) ? '/uploads/contest_submissions/' . $wm_filename : $clean_rel;
                $submission_note = trim($title . "\n\n" . $description);

                $sql = "INSERT INTO contest_submissions (
                    contest_id, creator_id, video_file,
                    watermarked_preview_file, clean_video_file, submission_note, status
                ) VALUES (?, ?, ?, ?, ?, ?, 'submitted')";

                $stmt = $pdo->prepare($sql);
                try {
                    $stmt->execute([
                        $contest_id, $creator['id'],
                        $clean_rel,
                        $wm_rel,
                        $clean_rel,
                        $submission_note
                    ]);

                    $submission_id = $pdo->lastInsertId();
                    $success = "Your submission has been received! Waiting for review...";

                    $stmt = $pdo->prepare("SELECT * FROM brands WHERE id = ?");
                    $stmt->execute([$contest['brand_id']]);
                    $brand = $stmt->fetch();

                    create_notification_batch(
                        [$brand['user_id']],
                        'New Contest Submission',
                        $creator['full_name'] . ' submitted to your contest: ' . $contest['title'],
                        'contest_submission',
                        'brand/contest-submissions.php?contest_id=' . $contest_id,
                        'contest',
                        $contest_id
                    );
                } catch (Exception $e) {
                    $error = "Error: " . $e->getMessage();
                }
            } else {
                $error = "Failed to upload video. Please try again.";
            }
        }
    }
}

include '../includes/header.php';
?>

<div class="pt-24 min-h-screen bg-gray-50 dark:bg-gray-950">
    <div class="max-w-7xl mx-auto px-6 md:px-12 flex flex-col md:flex-row gap-8 py-8">
        <?php include '../includes/creator_sidebar.php'; ?>

        <main class="flex-1 space-y-8">
            <header class="p-8 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm relative overflow-hidden">
                <div aria-hidden="true" class="absolute top-0 right-0 -mr-16 -mt-16 w-64 h-64 bg-secondary/5 rounded-full blur-3xl"></div>
                <div class="relative">
                    <h2 class="text-3xl font-bold text-gray-900 dark:text-white"><?php echo e($contest['title']); ?></h2>
                    <p class="text-gray-600 dark:text-gray-400 mt-2">Submit your best work to this contest</p>
                    <p class="text-sm text-gray-500 mt-3">
                        Deadline: <?php echo date('M d, Y H:i', strtotime($contest['submission_deadline'])); ?>
                    </p>
                </div>
            </header>

            <?php if ($success): ?>
                <div class="p-8 bg-green-50 dark:bg-green-950/30 border border-green-200 dark:border-green-800 rounded-[2rem] text-center shadow-sm">
                    <div class="w-20 h-20 bg-green-100 dark:bg-green-900/40 rounded-full flex items-center justify-center text-4xl mx-auto mb-6">✓</div>
                    <h3 class="text-2xl font-bold text-green-900 dark:text-green-400 mb-2">Submission Received!</h3>
                    <p class="text-green-700 dark:text-green-300 mb-8"><?php echo $success; ?></p>
                    <a href="<?php echo APP_URL; ?>creator/dashboard.php" class="inline-flex h-12 items-center justify-center px-8 bg-secondary text-white font-bold rounded-full hover:scale-105 transition">Go to Dashboard</a>
                </div>
            <?php elseif ($error): ?>
                <div class="p-6 bg-red-100 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-2xl text-red-800 dark:text-red-400 font-bold flex items-center">
                    <span class="mr-3 text-xl">⚠️</span> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if (!$success && !$error): ?>
            <form method="POST" enctype="multipart/form-data" class="space-y-8">
                <section class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm space-y-6">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
                        <span class="w-8 h-8 rounded-lg bg-secondary text-white flex items-center justify-center font-black text-sm">1</span>
                        Contest Details
                    </h3>

                    <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-2"><strong>Prize Pool:</strong></p>
                        <p class="text-2xl font-black text-secondary"><?php echo e($contest['currency']); ?> <?php echo number_format((float)$contest['total_contest_budget'], 2); ?></p>
                    </div>

                    <?php if ($contest['description']): ?>
                        <div>
                            <p class="text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">About this contest:</p>
                            <p class="text-gray-600 dark:text-gray-400 text-sm"><?php echo nl2br(e($contest['description'])); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if ($contest['terms_conditions']): ?>
                        <div>
                            <p class="text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Rules & Requirements:</p>
                            <p class="text-gray-600 dark:text-gray-400 text-sm"><?php echo nl2br(e($contest['terms_conditions'])); ?></p>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm space-y-6">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
                        <span class="w-8 h-8 rounded-lg bg-secondary text-white flex items-center justify-center font-black text-sm">2</span>
                        Your Submission
                    </h3>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Submission Title</label>
                        <input type="text" name="title" class="w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary" placeholder="Give your submission a catchy title" required>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Description (Optional)</label>
                        <textarea name="description" rows="3" class="w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-secondary" placeholder="Tell the judges about your submission..."></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-3">Upload Video</label>
                        <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-xl p-8 text-center">
                            <input type="file" name="video" accept="video/mp4,video/quicktime,video/x-msvideo" class="hidden" id="video-input" required onchange="handleVideoSelect(this)">
                            <label for="video-input" class="cursor-pointer">
                                <div class="text-4xl mb-3">🎬</div>
                                <p class="text-gray-700 dark:text-gray-300 font-bold mb-1">Click to upload or drag and drop</p>
                                <p class="text-xs text-gray-500">MP4, MOV, or AVI up to 500MB</p>
                            </label>
                            <p id="file-name" class="text-sm text-secondary font-bold mt-3"></p>
                        </div>
                    </div>

                    <!-- Preview Player (shown after a file is selected) -->
                    <div id="preview-section" class="hidden space-y-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-3">Preview Your Video</label>
                            <div class="w-full max-w-sm mx-auto aspect-[9/16] bg-black rounded-2xl overflow-hidden">
                                <video id="preview-player" class="w-full h-full object-contain" controls playsinline preload="metadata"></video>
                            </div>
                            <p class="text-xs text-gray-500 text-center mt-2">Play it back to confirm this is the right video before submitting.</p>
                        </div>

                        <div class="p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl">
                            <label class="flex items-start gap-3 cursor-pointer">
                                <input type="checkbox" id="confirm-final" class="mt-1 w-5 h-5 rounded border-gray-300 text-secondary focus:ring-secondary" required>
                                <span class="text-sm text-amber-900 dark:text-amber-200 font-medium">
                                    <strong>This is my final entry.</strong> I've reviewed the playback above and confirm this is what I want to submit to the contest.
                                </span>
                            </label>
                        </div>

                        <button type="button" onclick="resetVideo()" class="text-xs text-gray-500 hover:text-secondary underline">Choose a different file</button>
                    </div>

                    <div class="p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl">
                        <p class="text-xs text-blue-700 dark:text-blue-400"><strong>Note:</strong> Submissions must be uploaded files — no external links. Brands review a watermarked preview before winner selection.</p>
                    </div>
                </section>

                <div class="flex gap-4">
                    <button type="submit" id="submit-btn" disabled class="flex-1 py-4 bg-secondary text-white font-bold rounded-xl hover:scale-105 transition disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:scale-100">
                        Submit to Contest
                    </button>
                    <a href="<?php echo APP_URL; ?>contests.php" class="flex-1 py-4 bg-gray-200 dark:bg-gray-800 text-gray-900 dark:text-white font-bold rounded-xl text-center hover:scale-105 transition">
                        Cancel
                    </a>
                </div>
            </form>
            <?php endif; ?>
        </main>
    </div>
</div>

<script>
const videoInput = document.getElementById('video-input');
const previewSection = document.getElementById('preview-section');
const previewPlayer = document.getElementById('preview-player');
const fileNameLabel = document.getElementById('file-name');
const confirmCheckbox = document.getElementById('confirm-final');
const submitBtn = document.getElementById('submit-btn');

function handleVideoSelect(input) {
    const file = input.files[0];
    if (!file) { resetVideo(); return; }

    const maxBytes = 500 * 1024 * 1024;
    if (file.size > maxBytes) {
        alert('Video must be less than 500MB.');
        resetVideo();
        return;
    }

    fileNameLabel.textContent = 'Selected: ' + file.name;

    const url = URL.createObjectURL(file);
    previewPlayer.src = url;
    previewSection.classList.remove('hidden');
}

function resetVideo() {
    videoInput.value = '';
    fileNameLabel.textContent = '';
    previewPlayer.removeAttribute('src');
    previewPlayer.load();
    previewSection.classList.add('hidden');
    confirmCheckbox.checked = false;
    submitBtn.disabled = true;
}

confirmCheckbox?.addEventListener('change', () => {
    submitBtn.disabled = !(confirmCheckbox.checked && videoInput.files.length > 0);
});
</script>

<?php include '../includes/footer.php'; ?>
