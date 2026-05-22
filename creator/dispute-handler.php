<?php
require_once "../config/database.php";
require_once "../includes/functions.php";
require_role("creator");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $job_id = (int)$_POST["job_id"];
    $reason = $_POST["dispute_reason"];

    // Update job status
    $stmt = $pdo->prepare("UPDATE jobs SET status = 'disputed', payment_status = 'disputed' WHERE id = ? AND creator_id = ?");
    $stmt->execute([$job_id, $_SESSION["user_id"]]);

    log_activity($_SESSION["user_id"], "Dispute Raised", "Creator raised dispute for Job ID: $job_id. Reason: $reason");

    header("Location: my-jobs.php?success=Dispute Raised");
    exit();
}

header("Location: my-jobs.php");
exit();