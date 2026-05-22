<?php
require_once "../config/database.php";
require_once "../includes/functions.php";
require_role("admin");

$type = $_GET["type"] ?? "";
$id = (int)($_GET["id"] ?? 0);
$action = $_GET["action"] ?? "";

if ($type === "submission") {
    if ($action === "approve") {
        $stmt_sub = $pdo->prepare("SELECT s.*, j.creator_id, c.title as campaign_title FROM submissions s JOIN jobs j ON s.job_id = j.id JOIN campaigns c ON j.campaign_id = c.id WHERE s.id = ?");
        $stmt_sub->execute([$id]);
        $sub = $stmt_sub->fetch();

        if (!$sub) {
            header("Location: moderation.php?error=Submission not found");
            exit();
        }

        $pdo->prepare("UPDATE submissions SET status = 'approved' WHERE id = ?")->execute([$id]);
        
        $stmt_user = $pdo->prepare("SELECT user_id FROM creators WHERE id = ?");
        $stmt_user->execute([$sub['creator_id']]);
        $user_id = $stmt_user->fetchColumn();

        if (!$user_id) {
            header("Location: moderation.php?error=Creator not found");
            exit();
        }

        create_notification($user_id, "Submission Approved!", "Admin has approved your work for " . $sub['campaign_title'], "job", "creator/my-jobs.php", "job_submission", $id);
        log_activity($_SESSION["user_id"], "Submission Approved", "Submission ID: $id (Admin Override)");
        
        header("Location: moderation.php?success=Submission Approved");
        exit();
    }
}

header("Location: moderation.php");
exit();