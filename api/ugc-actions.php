<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_role('brand');

$submission_id = $_POST['submission_id'] ?? 0;
$action = $_POST['action'] ?? '';

$stmt = $pdo->prepare("
    SELECT us.*, uo.*, b.id as brand_id
    FROM ugc_order_submissions us
    JOIN ugc_orders uo ON us.ugc_order_id = uo.id
    JOIN brands b ON uo.brand_id = b.id
    WHERE us.id = ? AND b.user_id = ?
");
$stmt->execute([$submission_id, $_SESSION['user_id']]);
$submission = $stmt->fetch();

if (!$submission) {
    die("Submission not found.");
}

if ($action === 'approve') {
    $stmt = $pdo->prepare("UPDATE ugc_order_submissions SET status = 'approved' WHERE id = ?");
    $stmt->execute([$submission_id]);

    $stmt = $pdo->prepare("INSERT INTO payments (creator_id, job_id, amount, currency, payment_type, status) VALUES (?, ?, ?, ?, 'fixed_ugc', 'completed')");
    $stmt->execute([$submission['creator_id'], 0, $submission['budget_per_creator'], $submission['currency']]);

    $stmt = $pdo->prepare("SELECT * FROM brands WHERE id = ?");
    $stmt->execute([$submission['brand_id']]);
    $brand = $stmt->fetch();

    create_notification_batch(
        [$submission['creator_id']],
        'UGC Video Approved!',
        'Your video for ' . $brand['company_name'] . ' has been approved. Payment released!',
        'ugc_approved',
        'creator/ugc-orders.php',
        'ugc',
        $submission['ugc_order_id']
    );

    header("Location: ../brand/ugc-order-review.php?order_id=" . $submission['ugc_order_id'] . "&message=Video approved and payment released");
    exit;
} elseif ($action === 'request_revision') {
    $stmt = $pdo->prepare("UPDATE ugc_order_submissions SET status = 'revision_requested' WHERE id = ?");
    $stmt->execute([$submission_id]);

    create_notification_batch(
        [$submission['creator_id']],
        'Revision Requested',
        'Your video submission needs revisions. Check your dashboard for details.',
        'ugc_revision_requested',
        'creator/ugc-orders.php',
        'ugc',
        $submission['ugc_order_id']
    );

    header("Location: ../brand/ugc-order-review.php?order_id=" . $submission['ugc_order_id'] . "&message=Revision requested");
    exit;
} elseif ($action === 'reject') {
    $stmt = $pdo->prepare("UPDATE ugc_order_submissions SET status = 'rejected' WHERE id = ?");
    $stmt->execute([$submission_id]);

    create_notification_batch(
        [$submission['creator_id']],
        'Submission Rejected',
        'Your video submission was not selected.',
        'ugc_rejected',
        'creator/ugc-orders.php',
        'ugc',
        $submission['ugc_order_id']
    );

    header("Location: ../brand/ugc-order-review.php?order_id=" . $submission['ugc_order_id'] . "&message=Submission rejected");
    exit;
}
?>
