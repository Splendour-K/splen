<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_logged_in()) {
    header('HTTP/1.1 401 Unauthorized');
    exit();
}

// SSE headers
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

$user_id = $_SESSION['user_id'];
$last_id = intval($_GET['last_id'] ?? 0);

// Send a ping immediately
echo "event: ping\n";
echo "data: {\"time\":\"" . date('c') . "\"}\n\n";
@ob_flush(); flush();

while (!connection_aborted()) {
    // Fetch any notifications with id > last_id
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND id > ? ORDER BY id ASC");
    $stmt->execute([$user_id, $last_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $last_id = max($last_id, (int)$r['id']);
        $payload = json_encode($r);
        echo "event: notification\n";
        echo "id: {$r['id']}\n";
        echo "data: {$payload}\n\n";
        @ob_flush(); flush();
    }

    // Also send unread count occasionally
    $stmt2 = $pdo->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt2->execute([$user_id]);
    $unread = (int)$stmt2->fetchColumn();
    echo "event: unread_count\n";
    echo "data: {\"unread\": {$unread}}\n\n";
    @ob_flush(); flush();

    // Sleep a bit longer to reduce repeated DB work per open browser tab.
    sleep(8);
}

?>
