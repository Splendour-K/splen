<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_role('creator');

$stmt = $pdo->prepare("SELECT * FROM creators WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$creator = $stmt->fetch();

$stmt = $pdo->prepare("SELECT * FROM creator_verifications WHERE creator_id = ?");
$stmt->execute([$creator['id']]);
$verif = $stmt->fetch();

if (!$creator) {
    redirect('creator/dashboard.php');
}

$error = '';
$success = isset($_GET['submitted']) ? 'Verification documents submitted! Admin will review them.' : '';
$school_email = $verif['school_email'] ?? '';
$show_form = !$creator['verification_status'] || in_array($creator['verification_status'], ['not_started', 'rejected'], true) || (($verif['status'] ?? '') === 'more_info');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $school_email = trim($_POST['school_email'] ?? '');

    $upload_dir = __DIR__ . '/../assets/uploads/verifications/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $allowed_ext = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
    $id_path = $verif['id_upload'] ?? '';
    $letter_path = $verif['letter_upload'] ?? '';

    if (isset($_FILES['id_file']) && ($_FILES['id_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['id_file']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_ext, true)) {
            $error = 'Student ID must be JPG, PNG, WEBP, or PDF.';
        } else {
            $id_filename = 'id_' . $creator['id'] . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['id_file']['tmp_name'], $upload_dir . $id_filename)) {
                $id_path = 'assets/uploads/verifications/' . $id_filename;
            } else {
                $error = 'Unable to upload the student ID file.';
            }
        }
    }

    if (!$error && isset($_FILES['letter_file']) && ($_FILES['letter_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['letter_file']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_ext, true)) {
            $error = 'Proof of enrollment must be JPG, PNG, WEBP, or PDF.';
        } else {
            $letter_filename = 'letter_' . $creator['id'] . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['letter_file']['tmp_name'], $upload_dir . $letter_filename)) {
                $letter_path = 'assets/uploads/verifications/' . $letter_filename;
            } else {
                $error = 'Unable to upload the proof of enrollment file.';
            }
        }
    }

    if (!$error) {
        if ($school_email === '') {
            $error = 'School email is required.';
        } elseif (!$id_path || !$letter_path) {
            $error = 'Please upload both required documents.';
        } elseif (!$verif) {
            $ins = $pdo->prepare("INSERT INTO creator_verifications (creator_id, school_email, id_upload, letter_upload, status) VALUES (?, ?, ?, ?, 'pending')");
            $ins->execute([$creator['id'], $school_email, $id_path, $letter_path]);
            $pdo->prepare("UPDATE creators SET verification_status = 'pending' WHERE id = ?")->execute([$creator['id']]);
            redirect('creator/verification.php?submitted=1');
        } else {
            $upd = $pdo->prepare("UPDATE creator_verifications SET school_email = ?, id_upload = ?, letter_upload = ?, status = 'pending', admin_note = NULL WHERE creator_id = ?");
            $upd->execute([$school_email, $id_path, $letter_path, $creator['id']]);
            $pdo->prepare("UPDATE creators SET verification_status = 'pending' WHERE id = ?")->execute([$creator['id']]);
            redirect('creator/verification.php?submitted=1');
        }
    }
}

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
                    <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Student Verification</h2>
                    <p class="text-gray-600 dark:text-gray-400 mt-2">Prove you are a student to start earning.</p>
                </div>
            </header>

            <div class="max-w-2xl mx-auto w-full">
                <?php if ($success): ?>
                    <div class="p-8 bg-green-50 dark:bg-green-900/20 border border-green-100 dark:border-green-900/30 rounded-[2rem] text-center">
                        <div class="w-16 h-16 bg-green-100 dark:bg-green-900/40 rounded-full flex items-center justify-center text-2xl mx-auto mb-4">🎉</div>
                        <h3 class="text-xl font-bold text-green-900 dark:text-green-400 mb-2">Submitted!</h3>
                        <p class="text-green-700 dark:text-green-300"><?php echo e($success); ?></p>
                    </div>
                <?php elseif ($creator['verification_status'] === 'verified'): ?>
                    <div class="p-12 bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-[2rem] text-center shadow-sm">
                        <div class="w-20 h-20 bg-green-100 dark:bg-green-900/40 rounded-full flex items-center justify-center text-4xl mx-auto mb-6">✅</div>
                        <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">You are Verified!</h3>
                        <p class="text-gray-600 dark:text-gray-400">You now have full access to all campaigns and community features.</p>
                        <a href="<?php echo APP_URL; ?>creator/browse.php" class="mt-8 relative inline-flex h-12 items-center justify-center px-8 before:absolute before:inset-0 before:rounded-full before:bg-primary before:transition before:duration-300 hover:before:scale-105 active:duration-75 active:before:scale-95">
                            <span class="relative text-base font-semibold text-white">Browse Campaigns</span>
                        </a>
                    </div>
                <?php elseif ($creator['verification_status'] === 'pending'): ?>
                    <div class="p-12 bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-[2rem] text-center shadow-sm">
                        <div class="w-20 h-20 bg-orange-100 dark:bg-orange-900/40 rounded-full flex items-center justify-center text-4xl mx-auto mb-6">⏳</div>
                        <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Verification Pending</h3>
                        <p class="text-gray-600 dark:text-gray-400">Our team is currently reviewing your documents. This usually takes 24-48 hours. We'll email you once it's done!</p>
                        <?php if (!empty($verif['admin_note'])): ?>
                            <div class="mt-6 p-4 bg-gray-50 dark:bg-gray-800 rounded-2xl text-left">
                                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">Admin Note</p>
                                <p class="text-sm text-gray-700 dark:text-gray-300"><?php echo e($verif['admin_note']); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="p-8 md:p-12 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-2xl shadow-gray-600/10">
                        <?php if ($error): ?>
                            <div class="mb-6 p-4 bg-red-50 text-red-600 rounded-xl font-bold text-sm">
                                ⚠️ <?php echo e($error); ?>
                            </div>
                        <?php endif; ?>

                        <?php if (($verif['status'] ?? '') === 'rejected' && !empty($verif['admin_note'])): ?>
                            <div class="mb-6 p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-100 dark:border-yellow-900/30 rounded-xl text-sm text-yellow-900 dark:text-yellow-300">
                                <strong>Review note:</strong> <?php echo e($verif['admin_note']); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data" class="space-y-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">School Email (.edu or campus mail)</label>
                                <input type="email" name="school_email" value="<?php echo e($school_email); ?>" required placeholder="yourname@university.edu" class="w-full px-4 py-3 rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition dark:text-white">
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Student ID</label>
                                    <input type="file" name="id_file" required class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-primary/10 file:text-primary hover:file:bg-primary/20 transition-all cursor-pointer">
                                    <p class="text-[10px] text-gray-500">Photo of your physical student card.</p>
                                </div>
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Proof of Enrollment</label>
                                    <input type="file" name="letter_file" required class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-primary/10 file:text-primary hover:file:bg-primary/20 transition-all cursor-pointer">
                                    <p class="text-[10px] text-gray-500">Admission letter or fee receipt.</p>
                                </div>
                            </div>

                            <button type="submit" class="w-full py-5 bg-primary text-white font-black rounded-2xl shadow-xl shadow-primary/20 hover:scale-[1.02] transition active:scale-95">
                                <?php echo $verif ? 'Resubmit Verification' : 'Submit for Verification'; ?>
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
