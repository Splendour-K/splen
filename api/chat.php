<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$action = $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'];

if ($action === 'fetch_messages') {
    $chat_id = $_GET['chat_id'] ?? 0;
    
    // Security check: user must be part of conversation
    $stmt = $pdo->prepare("
        SELECT c.*, b.user_id as brand_user_id, cr.user_id as creator_user_id 
        FROM conversations c
        JOIN brands b ON c.brand_id = b.id
        JOIN creators cr ON c.creator_id = cr.id
        WHERE c.id = ?
    ");
    $stmt->execute([$chat_id]);
    $conv = $stmt->fetch();
    
    if (!$conv || ($conv['brand_user_id'] != $user_id && $conv['creator_user_id'] != $user_id)) {
        echo json_encode(['error' => 'Forbidden']);
        exit();
    }
    
    $stmt = $pdo->prepare("SELECT * FROM messages WHERE conversation_id = ? ORDER BY created_at ASC");
    $stmt->execute([$chat_id]);
    $messages = $stmt->fetchAll();
    
    echo json_encode(['messages' => $messages]);
    exit();
}

if ($action === 'send_message') {
    $chat_id = $_POST['chat_id'] ?? 0;
    $message_text = trim($_POST['message'] ?? '');
    
    if (empty($message_text)) {
        echo json_encode(['error' => 'Empty message']);
        exit();
    }

    // Security check: user must be part of conversation
    $stmt = $pdo->prepare("
        SELECT c.*, b.user_id as brand_user_id, cr.user_id as creator_user_id 
        FROM conversations c
        JOIN brands b ON c.brand_id = b.id
        JOIN creators cr ON c.creator_id = cr.id
        WHERE c.id = ?
    ");
    $stmt->execute([$chat_id]);
    $conv = $stmt->fetch();
    
    if (!$conv || ($conv['brand_user_id'] != $user_id && $conv['creator_user_id'] != $user_id)) {
        echo json_encode(['error' => 'Forbidden']);
        exit();
    }
    
    $receiver_id = ($conv['brand_user_id'] == $user_id) ? $conv['creator_user_id'] : $conv['brand_user_id'];
    
    $stmt = $pdo->prepare("INSERT INTO messages (conversation_id, sender_id, receiver_id, message) VALUES (?, ?, ?, ?)");
    $stmt->execute([$chat_id, $user_id, $receiver_id, $message_text]);
    $msg_id = $pdo->lastInsertId();
    
    // Update conversation last_message
    $update = $pdo->prepare("UPDATE conversations SET last_message = ?, updated_at = CURRENT_TIMESTAMP, last_message_id = ? WHERE id = ?");
    $update->execute([$message_text, $msg_id, $chat_id]);
    
    echo json_encode(['success' => true, 'message_id' => $msg_id]);
    exit();
}

echo json_encode(['error' => 'Invalid action']);
?>