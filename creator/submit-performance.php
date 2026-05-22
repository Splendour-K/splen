<?php
require_once "../config/database.php";
require_once "../includes/functions.php";
require_role("creator");

$job_id = $_GET["job_id"] ?? "";
if (!$job_id) redirect("creator/my-jobs.php");

// Fetch Job and Campaign
$stmt = $pdo->prepare("SELECT j.*, c.title, c.order_type, c.id as campaign_id, c.pay_per_1000_views, c.max_payable_views, c.currency, c.target_platform FROM jobs j JOIN campaigns c ON j.campaign_id = c.id WHERE j.id = ? AND j.creator_id = (SELECT id FROM creators WHERE user_id = ?)");
$stmt->execute([$job_id, $_SESSION["user_id"]]);
$job = $stmt->fetch();

if (!$job || $job["order_type"] !== "performance_campaign") redirect("creator/my-jobs.php");

// Check if already submitted
$stmt_check = $pdo->prepare("SELECT id FROM performance_proofs WHERE job_id = ? AND status != 'rejected'");
$stmt_check->execute([$job_id]);
if ($stmt_check->fetch()) {
    $success = "You have already submitted performance proof for this job. Please wait for verification.";
}

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $link = $_POST["posted_video_link"];
    $views = (int)$_POST["view_count"];
    $date_posted = $_POST["date_posted"];
    $screenshot = "";

    // File Upload
    if (!empty($_FILES["analytics_screenshot"]["name"])) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($_FILES["analytics_screenshot"]["name"], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            $error = "Invalid image type. Only JPG, PNG, and WEBP are allowed.";
        } else {
            $target_dir = "../assets/uploads/proofs/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
            $file_name = time() . "_proof_" . bin2hex(random_bytes(8)) . "." . $ext;
            $target_file = $target_dir . $file_name;
            if (move_uploaded_file($_FILES["analytics_screenshot"]["tmp_name"], $target_file)) {
                $screenshot = "assets/uploads/proofs/" . $file_name;
            }
        }
    }

    if ($screenshot && $link) {
        // Calculate potential payment
        $calc_views = min($views, $job["max_payable_views"]);
        $payment = ($calc_views / 1000) * $job["pay_per_1000_views"];

        $stmt_proof = $pdo->prepare("INSERT INTO performance_proofs (campaign_id, job_id, creator_id, posted_video_link, platform, date_posted, analytics_screenshot, view_count, calculated_payment, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, \"pending\")");
        try {
            $stmt_proof->execute([$job["campaign_id"], $job["id"], $job["creator_id"], $link, $job["target_platform"], $date_posted, $screenshot, $views, $payment]);
            $success = "Performance proof submitted! Brand will verify and finalize payment.";
        } catch (Exception $e) { $error = "Error: " . $e->getMessage(); }
    } else { $error = "Please upload an analytics screenshot and provide the video link."; }
}

include "../includes/header.php";
?>

<div class="pt-24 min-h-screen bg-gray-50 dark:bg-gray-950">
    <div class="max-w-7xl mx-auto px-6 md:px-12 flex flex-col md:flex-row gap-8 py-8">
        <?php include "../includes/creator_sidebar.php"; ?>

        <main class="flex-1 space-y-8">
            <header class="p-8 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm relative overflow-hidden">
                 <div aria-hidden="true" class="absolute top-0 right-0 -mr-16 -mt-16 w-64 h-64 bg-orange-500/5 rounded-full blur-3xl"></div>
                 <div class="relative">
                    <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Submit Performance Proof</h2>
                    <p class="text-gray-600 dark:text-gray-400 mt-2">Campaign: <span class="text-orange-600 font-bold"><?php echo e($job["title"]); ?></span></p>
                 </div>
            </header>

            <?php if ($success): ?>
                <div class="p-12 bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-[3rem] text-center shadow-xl">
                    <div class="w-20 h-20 bg-orange-100 dark:bg-orange-900/40 text-orange-600 rounded-full flex items-center justify-center text-4xl mx-auto mb-6">📊</div>
                    <h3 class="text-2xl font-black text-gray-900 dark:text-white mb-4">Proof Submitted!</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-8"><?php echo $success; ?></p>
                    <a href="my-jobs.php" class="px-8 py-4 bg-primary text-white font-bold rounded-xl hover:scale-105 transition">Back to My Jobs</a>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <section class="md:col-span-2 p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm space-y-6">
                        <form method="POST" enctype="multipart/form-data" class="space-y-6">
                            <div>
                                <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2 ml-1">Live Video Link (<?php echo e($job["target_platform"]); ?>)</label>
                                <input type="url" name="posted_video_link" required placeholder="Paste the URL of your post..." class="w-full px-5 py-4 bg-gray-50 dark:bg-gray-800 border-2 border-transparent focus:border-orange-500 rounded-2xl outline-none transition-all dark:text-white font-medium">
                            </div>

                            <div class="grid grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2 ml-1">Current View Count</label>
                                    <input type="number" name="view_count" id="vCount" required placeholder="0" class="w-full px-5 py-4 bg-gray-50 dark:bg-gray-800 border-2 border-transparent focus:border-orange-500 rounded-2xl outline-none transition-all dark:text-white font-medium" oninput="calcEarnings()">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2 ml-1">Date Posted</label>
                                    <input type="date" name="date_posted" required class="w-full px-5 py-4 bg-gray-50 dark:bg-gray-800 border-2 border-transparent focus:border-orange-500 rounded-2xl outline-none transition-all dark:text-white font-medium">
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-4 ml-1">Analytics Screenshot</label>
                                <div class="p-10 border-2 border-dashed border-gray-200 dark:border-gray-800 rounded-3xl text-center bg-gray-50/50 dark:bg-gray-800/30">
                                    <input type="file" name="analytics_screenshot" accept="image/*" required class="w-full">
                                    <p class="text-[10px] text-gray-400 mt-4 font-bold uppercase tracking-widest">Upload a clear screenshot of your video insights/analytics</p>
                                </div>
                            </div>

                            <button type="submit" class="w-full py-5 bg-orange-600 text-white text-lg font-black rounded-3xl shadow-xl shadow-orange-600/20 hover:scale-[1.02] transition-all">Submit Analytics for Verification</button>
                        </form>
                    </section>

                    <aside class="space-y-6">
                         <div class="p-8 bg-gray-900 text-white rounded-[2.5rem] shadow-xl">
                            <h4 class="text-sm font-black uppercase text-gray-400 tracking-widest mb-6">Earning Tracker</h4>
                            <div class="space-y-4">
                                <div class="flex justify-between text-xs">
                                    <span class="text-gray-400 uppercase">Rate:</span>
                                    <span class="font-bold"><?php echo $job["currency"]; ?> <?php echo number_format($job["pay_per_1000_views"]); ?> / 1k Views</span>
                                </div>
                                <div class="flex justify-between text-xs">
                                    <span class="text-gray-400 uppercase">View Cap:</span>
                                    <span class="font-bold"><?php echo number_format($job["max_payable_views"]); ?> Views</span>
                                </div>
                                <div class="h-px bg-gray-800 my-4"></div>
                                <div class="flex justify-between items-center">
                                    <span class="text-[10px] font-black uppercase text-orange-500 tracking-widest">Est. Payout</span>
                                    <span class="text-2xl font-black" id="estPayout"><?php echo $job["currency"]; ?> 0.00</span>
                                </div>
                            </div>
                         </div>
                    </aside>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<script>
function calcEarnings() {
    const views = parseInt(document.getElementById("vCount").value) || 0;
    const rate = <?php echo $job["pay_per_1000_views"]; ?>;
    const cap = <?php echo $job["max_payable_views"]; ?>;
    const currency = "<?php echo $job["currency"]; ?>";
    
    const payableViews = Math.min(views, cap);
    const earnings = (payableViews / 1000) * rate;
    
    document.getElementById("estPayout").innerText = currency + " " + earnings.toLocaleString(undefined, {minimumFractionDigits: 2});
}
</script>

<?php include "../includes/footer.php"; ?>
