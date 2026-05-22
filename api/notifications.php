<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
if (!is_logged_in()) {
    echo json_encode(['error' => 'unauthenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];
$action = $_REQUEST['action'] ?? 'list';

switch ($action) {
    case 'list':
        $limit = intval($_GET['limit'] ?? 50);
        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
        $stmt->bindValue(1, $user_id, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['ok' => true, 'notifications' => $rows]);
        break;

    case 'unread_count':
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        $count = (int)$stmt->fetchColumn();
        echo json_encode(['ok' => true, 'unread' => $count]);
        break;

    case 'mark_read':
        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);
            echo json_encode(['ok' => true]);
        } else {
            // mark all
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
            $stmt->execute([$user_id]);
            echo json_encode(['ok' => true]);
        }
        break;

    case 'delete':
        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);
            echo json_encode(['ok' => true]);
        } else {
            echo json_encode(['error' => 'missing_id']);
        }
        break;

    default:
        echo json_encode(['error' => 'unknown_action']);
}

?>
