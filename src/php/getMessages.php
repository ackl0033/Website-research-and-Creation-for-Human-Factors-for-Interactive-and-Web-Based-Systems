<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

require_once '../inc/dbconn.inc.php';
$user_id = $_SESSION['user_id'];
$conversation_id = isset($_GET['conversation_id']) ? intval($_GET['conversation_id']) : 0;

if (!$conversation_id) {
    echo json_encode(['error' => 'Conversation ID required']);
    exit;
}

try {
    // Verify user is part of this conversation
    $stmt = $conn->prepare("
        SELECT id FROM conversations 
        WHERE id = ? AND (user1_id = ? OR user2_id = ?)
    ");
    $stmt->bind_param("iii", $conversation_id, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$result->fetch_assoc()) {
        echo json_encode(['error' => 'Access denied to this conversation']);
        exit;
    }
    
    // Get messages for this conversation
    $stmt = $conn->prepare("
        SELECT 
            id,
            sender_id,
            receiver_id,
            message_text,
            is_read,
            created_at
        FROM messages 
        WHERE conversation_id = ?
        ORDER BY created_at ASC
    ");
    
    $stmt->bind_param("i", $conversation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = [
            'id' => $row['id'],
            'sender_id' => $row['sender_id'],
            'receiver_id' => $row['receiver_id'],
            'message_text' => $row['message_text'],
            'is_read' => $row['is_read'],
            'created_at' => $row['created_at']
        ];
    }
    
    // Mark messages as read for the current user
    $stmt = $conn->prepare("
        UPDATE messages 
        SET is_read = 1 
        WHERE conversation_id = ? AND receiver_id = ? AND is_read = 0
    ");
    $stmt->bind_param("ii", $conversation_id, $user_id);
    $stmt->execute();
    
    echo json_encode($messages);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>

// GPT-5
  line 112 there is another table called conversations that stores all info for a one-on-one
  conversation. there is also another table called messages that stores all of the messages between each user.
  the messages.php will pull this info from the database based on the users id and will display the
  messages in the appropriate conversation thread.
