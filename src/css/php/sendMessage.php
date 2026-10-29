<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

require_once '../inc/dbconn.inc.php';
$user_id = $_SESSION['user_id'];

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$conversation_id = isset($input['conversation_id']) ? intval($input['conversation_id']) : 0;
$message_text = isset($input['message_text']) ? trim($input['message_text']) : '';

if (!$conversation_id || !$message_text) {
    echo json_encode(['success' => false, 'error' => 'Conversation ID and message text required']);
    exit;
}

try {
    // Verify user is part of this conversation and get receiver info
    $stmt = $conn->prepare("
        SELECT user1_id, user2_id 
        FROM conversations 
        WHERE id = ? AND (user1_id = ? OR user2_id = ?)
    ");
    $stmt->bind_param("iii", $conversation_id, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$conv_data = $result->fetch_assoc()) {
        echo json_encode(['success' => false, 'error' => 'Access denied to this conversation']);
        exit;
    }
    
    // Determine receiver ID
    $receiver_id = ($conv_data['user1_id'] == $user_id) ? $conv_data['user2_id'] : $conv_data['user1_id'];
    
    // Insert the message
    $stmt = $conn->prepare("
        INSERT INTO messages (conversation_id, sender_id, receiver_id, message_text) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("iiis", $conversation_id, $user_id, $receiver_id, $message_text);
    
    if ($stmt->execute()) {
        // Update conversation last_message_at timestamp
        $stmt = $conn->prepare("
            UPDATE conversations 
            SET last_message_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $stmt->bind_param("i", $conversation_id);
        $stmt->execute();
        
        echo json_encode(['success' => true, 'message_id' => $conn->insert_id]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to send message']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>

// GPT-5
  line 112 there is another table called conversations that stores all info for a one-on-one
  conversation. there is also another table called messages that stores all of the messages between each user.
  the messages.php will pull this info from the database based on the users id and will display the
  messages in the appropriate conversation thread.
