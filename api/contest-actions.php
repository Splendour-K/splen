<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_role('admin');

$submission_id = $_POST['submission_id'] ?? 0;
$action = $_POST['action'] ?? '';

$stmt = $pdo->prepare("
    SELECT cs.*, c.title as contest_title, c.brand_id, cr.id as creator_id
    FROM contest_submissions cs
    JOIN contests c ON cs.contest_id = c.id
    JOIN creators cr ON cs.creator_id = cr.id
    WHERE cs.id = ?
");
$stmt->execute([$submission_id]);
$submission = $stmt->fetch();

if (!$submission) {
    die("Submission not found.");
}

if ($action === 'approve') {
    $stmt = $pdo->prepare("UPDATE contest_submissions SET status = 'shortlisted' WHERE id = ?");
    $stmt->execute([$submission_id]);

    header("Location: ../admin/contest-submissions.php?message=Submission approved");
    exit;
} elseif ($action === 'flag') {
    $flag_reason = $_POST['flag_reason'] ?? 'Manual review required';
    $stmt = $pdo->prepare("UPDATE contest_submissions SET flag_reason = ?, flagged_at = NOW() WHERE id = ?");
    $stmt->execute([$flag_reason, $submission_id]);

    header("Location: ../admin/contest-submissions.php?message=Submission flagged for review");
    exit;
} elseif ($action === 'resolve_flag') {
    $stmt = $pdo->prepare("UPDATE contest_submissions SET flag_reason = NULL, flagged_at = NULL WHERE id = ?");
    $stmt->execute([$submission_id]);

    header("Location: ../admin/contest-submissions.php?message=Flag resolved");
    exit;
}
?>
