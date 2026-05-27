<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_role('brand');

$submission_id = (int)($_POST['submission_id'] ?? 0);
$action        = $_POST['action'] ?? '';
$feedback      = trim($_POST['feedback'] ?? '');

// Validate submission belongs to this brand
$stmt = $pdo->prepare("
    SELECT us.*, uo.budget_per_creator, uo.currency, uo.brand_id, uo.id AS order_id, uo.title AS order_title,
           b.brand_name
    FROM ugc_order_submissions us
    JOIN ugc_orders uo ON us.ugc_order_id = uo.id
    JOIN brands b ON uo.brand_id = b.id
    WHERE us.id = ? AND b.user_id = ?
");
$stmt->execute([$submission_id, $_SESSION['user_id']]);
$submission = $stmt->fetch();

if (!$submission) {
    http_response_code(404);
    die("Submission not found or access denied.");
}

$redirect_base = "../brand/ugc-order-review.php?order_id=" . $submission['order_id'];

if ($action === 'approve') {

    $pdo->beginTransaction();
    try {
        // Mark submission approved
        $stmt = $pdo->prepare("UPDATE ugc_order_submissions SET status = 'approved', approved_at = NOW() WHERE id = ?");
        $stmt->execute([$submission_id]);

        // Create payment record — job_id nullable, calculated_amount = net after 15% fee
        $gross   = (float)$submission['budget_per_creator'];
        $net     = round($gross * 0.85, 2);   // creator receives 85 %
        $fee     = round($gross * 0.15, 2);   // 15 % platform fee

        $stmt = $pdo->prepare("
            INSERT INTO payments
                (creator_id, job_id, amount, currency, calculated_amount, commission_rate, commission_amount, net_amount, payment_type, status)
            VALUES (?, NULL, ?, ?, ?, 15.00, ?, ?, 'fixed_ugc', 'pending')
        ");
        $stmt->execute([
            $submission['creator_id'],
            $gross,
            $submission['currency'],
            $gross,   // amount = full gross
            $fee,
            $net,
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        die("Payment processing error: " . $e->getMessage());
    }

    create_notification_batch(
        [$submission['creator_id']],
        'Campaign Video Approved! 🎉',
        'Your video for "' . $submission['order_title'] . '" by ' . $submission['brand_name'] . ' has been approved. Payment is being processed.',
        'ugc_approved',
        'creator/ugc-orders.php',
        'ugc',
        $submission['order_id']
    );

    header("Location: {$redirect_base}&message=Video+approved+and+payment+queued");
    exit;

} elseif ($action === 'request_revision') {

    if ($feedback === '') {
        header("Location: {$redirect_base}&error=Please+provide+revision+instructions+for+the+creator");
        exit;
    }

    $stmt = $pdo->prepare("UPDATE ugc_order_submissions SET status = 'revision_requested', brand_feedback = ? WHERE id = ?");
    $stmt->execute([$feedback, $submission_id]);

    create_notification_batch(
        [$submission['creator_id']],
        'Revision Requested',
        'Your video for "' . $submission['order_title'] . '" needs changes. Feedback: ' . mb_substr($feedback, 0, 120),
        'ugc_revision_requested',
        'creator/ugc-orders.php',
        'ugc',
        $submission['order_id']
    );

    header("Location: {$redirect_base}&message=Revision+requested+with+feedback");
    exit;

} elseif ($action === 'reject') {

    if ($feedback === '') {
        header("Location: {$redirect_base}&error=Please+provide+a+reason+for+rejecting+this+submission");
        exit;
    }

    $stmt = $pdo->prepare("UPDATE ugc_order_submissions SET status = 'rejected', brand_feedback = ? WHERE id = ?");
    $stmt->execute([$feedback, $submission_id]);

    create_notification_batch(
        [$submission['creator_id']],
        'Campaign Submission Not Approved',
        'Your video for "' . $submission['order_title'] . '" was not approved. Reason: ' . mb_substr($feedback, 0, 120),
        'ugc_rejected',
        'creator/ugc-orders.php',
        'ugc',
        $submission['order_id']
    );

    header("Location: {$redirect_base}&message=Submission+rejected");
    exit;
}

// Unknown action — redirect back
header("Location: {$redirect_base}");
exit;
