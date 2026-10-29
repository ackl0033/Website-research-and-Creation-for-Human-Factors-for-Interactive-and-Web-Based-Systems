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

try {
    // Get all conversations for the current user
    $stmt = $conn->prepare("
        SELECT 
            c.id,
            c.user1_id,
            c.user2_id,
            c.user1_username,
            c.user2_username,
            c.last_message_at,
            m.message_text as last_message
        FROM conversations c
        LEFT JOIN (
            SELECT 
                conversation_id,
                message_text,
                ROW_NUMBER() OVER (PARTITION BY conversation_id ORDER BY created_at DESC) as rn
            FROM messages
        ) m ON c.id = m.conversation_id AND m.rn = 1
        WHERE c.user1_id = ? OR c.user2_id = ?
        ORDER BY c.last_message_at DESC
    ");
    
    $stmt->bind_param("ii", $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $conversations = [];
    while ($row = $result->fetch_assoc()) {
        // Determine the "other" user in the conversation
        $other_username = ($row['user1_id'] == $user_id) ? $row['user2_username'] : $row['user1_username'];
        // Convert last_message_at to ISO 8601 format for JS compatibility
        $last_message_at = $row['last_message_at'] ? str_replace(' ', 'T', $row['last_message_at']) : null;
        $conversations[] = [
            'id' => $row['id'],
            'other_username' => $other_username,
            'last_message' => $row['last_message'],
            'last_message_at' => $last_message_at
        ];
    }
    
    echo json_encode($conversations);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>

// GPT-5
  line 112 there is another table called conversations that stores all info for a one-on-one
  conversation. there is also another table called messages that stores all of the messages between each user.
  the messages.php will pull this info from the database based on the users id and will display the
  messages in the appropriate conversation thread.