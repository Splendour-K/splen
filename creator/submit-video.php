<?php
require_once "../config/database.php";
require_once "../includes/functions.php";
require_role("creator");

$job_id = $_GET["job_id"] ?? "";
if (!$job_id) redirect("creator/my-jobs.php");

// Fetch Job and Campaign
$stmt = $pdo->prepare("SELECT j.*, c.title, c.order_type, c.id as campaign_id FROM jobs j JOIN campaigns c ON j.campaign_id = c.id WHERE j.id = ? AND j.creator_id = (SELECT id FROM creators WHERE user_id = ?)");
$stmt->execute([$job_id, $_SESSION["user_id"]]);
$job = $stmt->fetch();

if (!$job) redirect("creator/my-jobs.php");

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $note = $_POST["submission_note"] ?? "";
    $clean_file = "";
    $watermarked_file = "";

    if (empty($_FILES["video_file"]["name"]) || ($_FILES["video_file"]["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $error = "Please upload a video file. External links are not accepted.";
    } else {
        $allowed = ['mp4', 'mov', 'avi', 'mkv'];
        $ext = strtolower(pathinfo($_FILES["video_file"]["name"], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            $error = "Invalid file type. Only MP4, MOV, AVI, and MKV are allowed.";
        } else if ($_FILES["video_file"]["size"] > 500 * 1024 * 1024) {
            $error = "Video must be less than 500MB.";
        } else {
            $target_dir = "../assets/uploads/videos/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
            $file_name = time() . "_clean_" . bin2hex(random_bytes(8)) . "." . $ext;
            $target_file = $target_dir . $file_name;

            if (move_uploaded_file($_FILES["video_file"]["tmp_name"], $target_file)) {
                $clean_file = "assets/uploads/videos/" . $file_name;

                // Generate a real watermarked preview for the brand's review screen.
                $wm_file_name = 'wm_' . $file_name;
                $wm_target_file = $target_dir . $wm_file_name;
                @set_time_limit(180);
                $wm_result = generate_video_watermark($target_file, $wm_target_file);
                $watermarked_file = ($wm_result['ok'] && file_exists($wm_target_file))
                    ? "assets/uploads/videos/" . $wm_file_name
                    : $clean_file;
            } else {
                $error = "Failed to upload video. Please try again.";
            }
        }
    }

    if (!$error && $clean_file) {
        $stmt_sub = $pdo->prepare("INSERT INTO submissions (job_id, creator_id, campaign_id, clean_video_file, watermarked_preview_file, submission_note, status) VALUES (?, ?, ?, ?, ?, ?, \"submitted\")");
        try {
            $stmt_sub->execute([$job["id"], $job["creator_id"], $job["campaign_id"], $clean_file, $watermarked_file, $note]);
            $success = "Video submitted successfully! Awaiting brand review.";
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

include "../includes/header.php";
?>

<div class="pt-24 min-h-screen bg-gray-50 dark:bg-gray-950">
    <div class="max-w-7xl mx-auto px-6 md:px-12 flex flex-col md:flex-row gap-8 py-8">
        <?php include "../includes/creator_sidebar.php"; ?>

        <main class="flex-1 space-y-8">
            <header class="p-8 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Submit Content</h2>
                <p class="text-gray-600 dark:text-gray-400 mt-2">Uploading for: <span class="text-primary font-bold"><?php echo e($job["title"]); ?></span></p>
            </header>

            <?php if ($success): ?>
                <div class="p-12 bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-[3rem] text-center shadow-xl">
                    <div class="w-20 h-20 bg-green-100 dark:bg-green-900/40 rounded-full flex items-center justify-center text-4xl mx-auto mb-6">✅</div>
                    <h3 class="text-2xl font-black text-gray-900 dark:text-white mb-4">Submission Sent!</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-8"><?php echo $success; ?></p>
                    <a href="my-jobs.php" class="px-8 py-4 bg-primary text-white font-bold rounded-xl hover:scale-105 transition">Back to My Jobs</a>
                </div>
            <?php else: ?>
                <form method="POST" enctype="multipart/form-data" class="space-y-8">
                    <section class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm space-y-6">
                        <div class="flex items-center gap-3 mb-6">
                            <span class="w-10 h-10 bg-primary/10 text-primary rounded-xl flex items-center justify-center text-lg">📤</span>
                            <h3 class="text-xl font-bold text-gray-900 dark:text-white">Content Details</h3>
                        </div>

                        <?php if ($error): ?>
                            <div class="p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-2xl text-red-800 dark:text-red-400 text-sm font-medium">
                                ⚠️ <?php echo e($error); ?>
                            </div>
                        <?php endif; ?>

                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-4 ml-1">Upload Video File</label>
                            <div class="p-10 border-2 border-dashed border-gray-200 dark:border-gray-800 rounded-3xl text-center bg-gray-50/50 dark:bg-gray-800/30">
                                <input type="file" id="video-input" name="video_file" accept="video/mp4,video/quicktime,video/x-msvideo,video/x-matroska" class="w-full" required onchange="handleVideoSelect(this)">
                                <p class="text-xs text-gray-400 mt-4 font-bold uppercase tracking-widest">Max File Size: 500MB • Formats: MP4, MOV, AVI, MKV</p>
                            </div>
                        </div>

                        <!-- Preview Player (shown after file is selected) -->
                        <div id="preview-section" class="hidden space-y-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-3 ml-1">Preview Your Video</label>
                                <div class="w-full max-w-sm mx-auto aspect-[9/16] bg-black rounded-2xl overflow-hidden">
                                    <video id="preview-player" class="w-full h-full object-contain" controls playsinline preload="metadata"></video>
                                </div>
                                <p class="text-xs text-gray-500 text-center mt-2">Play it back to confirm this is the right video before submitting.</p>
                            </div>
                            <button type="button" onclick="resetVideo()" class="text-xs text-gray-500 hover:text-primary underline">Choose a different file</button>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2 ml-1">Submission Note</label>
                            <textarea name="submission_note" rows="4" placeholder="Any details for the brand?" class="w-full px-5 py-4 bg-gray-50 dark:bg-gray-800 border border-transparent focus:border-primary rounded-2xl transition-all outline-none font-medium dark:text-white"></textarea>
                        </div>

                        <div class="space-y-3">
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" id="confirm-final" required class="w-5 h-5 rounded-lg border-gray-300 text-primary focus:ring-primary">
                                <span class="text-sm text-gray-600 dark:text-gray-400"><strong>This is my final video.</strong> I've reviewed the preview above and confirm this is what to send to the brand.</span>
                            </label>
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" required class="w-5 h-5 rounded-lg border-gray-300 text-primary focus:ring-primary">
                                <span class="text-sm text-gray-600 dark:text-gray-400">The video follows the campaign brief and creative requirements.</span>
                            </label>
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" required class="w-5 h-5 rounded-lg border-gray-300 text-primary focus:ring-primary">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Content is original and no copyrighted music was used without permission.</span>
                            </label>
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" required class="w-5 h-5 rounded-lg border-gray-300 text-primary focus:ring-primary">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Everyone shown in the video has given permission to be filmed.</span>
                            </label>
                        </div>

                        <div class="p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl">
                            <p class="text-xs text-blue-700 dark:text-blue-400"><strong>Note:</strong> All deliverables are uploaded files — no external links. The brand reviews a watermarked preview, and your payment is released through Splennet once approved.</p>
                        </div>

                        <button type="submit" id="submit-btn" disabled class="w-full py-5 bg-primary text-white text-lg font-black rounded-3xl shadow-xl shadow-primary/20 hover:scale-[1.02] transition-all disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:scale-100">Send to Brand</button>
                    </section>
                </form>

                <script>
                const videoInput = document.getElementById('video-input');
                const previewSection = document.getElementById('preview-section');
                const previewPlayer = document.getElementById('preview-player');
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

                    const url = URL.createObjectURL(file);
                    previewPlayer.src = url;
                    previewSection.classList.remove('hidden');
                    updateSubmitState();
                }

                function resetVideo() {
                    videoInput.value = '';
                    previewPlayer.removeAttribute('src');
                    previewPlayer.load();
                    previewSection.classList.add('hidden');
                    confirmCheckbox.checked = false;
                    updateSubmitState();
                }

                function updateSubmitState() {
                    submitBtn.disabled = !(confirmCheckbox.checked && videoInput.files.length > 0);
                }

                confirmCheckbox?.addEventListener('change', updateSubmitState);
                </script>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php include "../includes/footer.php"; ?>
