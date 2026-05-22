<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_role('admin');

$submission_id = $_POST['submission_id'] ?? 0;
$action = $_POST['action'] ?? '';

$stmt = $pdo->prepare("
    SELECT us.*, uo.title as order_title, uo.brand_id, cr.id as creator_id
    FROM ugc_order_submissions us
    JOIN ugc_orders uo ON us.ugc_order_id = uo.id
    JOIN creators cr ON us.creator_id = cr.id
    WHERE us.id = ?
");
$stmt->execute([$submission_id]);
$submission = $stmt->fetch();

if (!$submission) {
    die("Submission not found.");
}

if ($action === 'verify') {
    $stmt = $pdo->prepare("UPDATE ugc_order_submissions SET quality_verified = 1, status = 'approved' WHERE id = ?");
    $stmt->execute([$submission_id]);

    header("Location: ../admin/ugc-submissions.php?message=Quality verified");
    exit;
} elseif ($action === 'flag') {
    $flag_reason = $_POST['flag_reason'] ?? 'Manual review required';
    $stmt = $pdo->prepare("UPDATE ugc_order_submissions SET flag_reason = ?, flagged_at = NOW(), status = 'under_review' WHERE id = ?");
    $stmt->execute([$flag_reason, $submission_id]);

    header("Location: ../admin/ugc-submissions.php?message=Submission flagged for review");
    exit;
} elseif ($action === 'resolve_flag') {
    $stmt = $pdo->prepare("UPDATE ugc_order_submissions SET flag_reason = NULL, flagged_at = NULL, status = 'approved' WHERE id = ?");
    $stmt->execute([$submission_id]);

    header("Location: ../admin/ugc-submissions.php?message=Flag resolved");
    exit;
}
?>
