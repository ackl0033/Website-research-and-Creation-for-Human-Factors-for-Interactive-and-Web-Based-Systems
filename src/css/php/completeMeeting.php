<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

require_once '../inc/dbconn.inc.php';
require_once __DIR__ . '/transactions.inc.php';
$user_id = $_SESSION['user_id'];

// Expect message_id to identify the confirmation message
$message_id = isset($_POST['message_id']) ? intval($_POST['message_id']) : 0;
if (!$message_id) {
    echo json_encode(['error' => 'Missing message id']);
    exit;
}

// Load message and conversation
$stmt = $conn->prepare("SELECT m.*, c.user1_id, c.user2_id FROM messages m JOIN conversations c ON m.conversation_id = c.id WHERE m.id = ?");
$stmt->bind_param('i', $message_id);
$stmt->execute();
$msg = $stmt->get_result()->fetch_assoc();
if (!$msg) {
    echo json_encode(['error' => 'Message not found']);
    exit;
}

// Only the requester (original request owner) can complete/credit tokens
$reqBy = null; $helperId = null; $tokens = 0;
if (preg_match('/<!--REQBY:(\d+)-->/', $msg['message_text'], $m1)) { $reqBy = intval($m1[1]); }
if (preg_match('/<!--HELPER:(\d+)-->/', $msg['message_text'], $m2)) { $helperId = intval($m2[1]); }
if (preg_match('/<!--TOK:(\d+)-->/', $msg['message_text'], $m3)) { $tokens = intval($m3[1]); }
// Fallback for older messages: try to extract "N token(s)" from message text
if ($tokens <= 0) {
    if (preg_match('/(\d+)\s*token\(s\)/i', $msg['message_text'], $mtok)) {
        $tokens = intval($mtok[1]);
    }
}

if (!$reqBy || !$helperId) {
    echo json_encode(['error' => 'Confirmation message missing required tags']);
    exit;
}

if ($user_id !== $reqBy) {
    echo json_encode(['error' => 'Only the requester can complete this meeting']);
    exit;
}

// Safety: if helperId erroneously equals requester (older messages), infer helper as the other participant in the conversation
if ($helperId === $reqBy) {
    $otherId = ($msg['sender_id'] == $reqBy) ? (int)$msg['receiver_id'] : (int)$msg['sender_id'];
    if ($otherId && $otherId !== $reqBy) {
        $helperId = $otherId;
    }
}

// Prevent double-completion by marking message
if (strpos($msg['message_text'], '<!--DONE-->') !== false) {
    echo json_encode(['error' => 'This meeting is already completed']);
    exit;
}

// Ensure transaction table exists
ensure_transactions_table($conn);

// Credit tokens to helper
if ($tokens > 0) {
    $u = $conn->prepare("UPDATE users SET token = token + ? WHERE id = ?");
    $u->bind_param('ii', $tokens, $helperId);
    if (!$u->execute()) {
        echo json_encode(['error' => 'Failed to credit tokens']);
        exit;
    }
    // Log transactions for both sides
    log_transaction($conn, $helperId, $reqBy, $tokens, 'credit', 'meeting_complete', $message_id, null, 'Meeting completed; tokens credited');
    log_transaction($conn, $reqBy, $helperId, $tokens, 'debit', 'meeting_complete', $message_id, null, 'Meeting completed; tokens debited');
}

// Mark message as completed
$newText = $msg['message_text'] . ' <!--DONE-->';
$up = $conn->prepare("UPDATE messages SET message_text = ? WHERE id = ?");
$up->bind_param('si', $newText, $message_id);
$up->execute();

echo json_encode([
    'success' => true, 
    'message' => 'Meeting completed and tokens credited', 
    'credited_tokens' => $tokens,
    'show_review_prompt' => true,
    'helper_id' => $helperId,
    'message_id' => $message_id
]);
