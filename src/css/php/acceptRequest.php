<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

require_once '../inc/dbconn.inc.php';
$user_id = $_SESSION['user_id'];

// Get POST data
$request_id = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;
$selected_day = $_POST['selected_day'] ?? '';
$selected_time = $_POST['selected_time'] ?? '';
$selected_date = $_POST['selected_date'] ?? '';
$duration = isset($_POST['duration']) ? floatval($_POST['duration']) : null;

if (!$request_id || !$selected_day || !$selected_time || !$selected_date || !$duration) {
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

// Get request info and owner
$stmt = $conn->prepare("SELECT r.*, u.id as owner_id, u.username as owner_username FROM requests r JOIN users u ON r.user_id = u.id WHERE r.id = ?");
$stmt->bind_param('i', $request_id);
$stmt->execute();
$result = $stmt->get_result();
if (!($req = $result->fetch_assoc())) {
    echo json_encode(['error' => 'Request not found']);
    exit;
}

// Mark request as pending (hide from search for others)
$update = $conn->prepare("UPDATE requests SET is_direct = 1, direct_to_user = ? WHERE id = ?");
$update->bind_param('ii', $user_id, $request_id);
$update->execute();

// Send a message to the request owner for confirmation
// Find or create a conversation
$owner_id = $req['user_id'];
$smaller_id = min($user_id, $owner_id);
$larger_id = max($user_id, $owner_id);

// Get or create conversation
$stmt = $conn->prepare("SELECT id FROM conversations WHERE (user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?)");
$stmt->bind_param('iiii', $smaller_id, $larger_id, $larger_id, $smaller_id);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $conv_id = $row['id'];
} else {
    // Create conversation
    $stmt2 = $conn->prepare("INSERT INTO conversations (user1_id, user2_id, user1_username, user2_username) VALUES (?, ?, ?, ?)");
    $stmt2->bind_param('iiss', $smaller_id, $larger_id, $req['owner_username'], $_SESSION['username']);
    $stmt2->execute();
    $conv_id = $conn->insert_id;
}

$msg = "User {$_SESSION['username']} wants to accept your request on $selected_day ($selected_date) at $selected_time for $duration hour(s). Please confirm or decline. <!--RID:$request_id-->";
$insert = $conn->prepare("INSERT INTO messages (conversation_id, sender_id, receiver_id, message_text) VALUES (?, ?, ?, ?)");
$insert->bind_param('iiis', $conv_id, $user_id, $owner_id, $msg);
$insert->execute();

// Optionally, store a pending confirmation in a new table or in the request row (not implemented here)

echo json_encode(['success' => true, 'message' => 'Request sent for confirmation.']);
