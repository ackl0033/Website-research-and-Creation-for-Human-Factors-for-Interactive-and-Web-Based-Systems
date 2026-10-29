<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

require_once '../inc/dbconn.inc.php';
$user_id = $_SESSION['user_id'];

$message_id = isset($_POST['message_id']) ? intval($_POST['message_id']) : 0;
$is_confirm = isset($_POST['confirm']) && $_POST['confirm'] == '1';
// Optional date from client; if not provided we'll compute next available date
$date = isset($_POST['date']) ? $_POST['date'] : null;

if (!$message_id) {
    echo json_encode(['error' => 'Missing message id']);
    exit;
}

// Find the message and conversation
$stmt = $conn->prepare("SELECT * FROM messages WHERE id = ?");
$stmt->bind_param('i', $message_id);
$stmt->execute();
$msg = $stmt->get_result()->fetch_assoc();
if (!$msg) {
    echo json_encode(['error' => 'Message not found']);
    exit;
}

// Only the receiver can confirm/decline
if ($msg['receiver_id'] != $user_id) {
    echo json_encode(['error' => 'Not authorized']);
    exit;
}

// Extract request ID from the message text (format: <!--DREQ:id-->)
$request_id = null;
if (preg_match('/<!--DREQ:(\d+)-->/i', $msg['message_text'], $idMatch)) {
    $request_id = intval($idMatch[1]);
}

// Debug: log the message text and parsing result
error_log("Direct request message text: " . $msg['message_text']);
error_log("Parsed request ID: " . ($request_id ? $request_id : 'none'));

if (!$request_id) {
    echo json_encode([
        'error' => 'No direct request found (request ID missing in message).', 
        'debug_message' => substr($msg['message_text'], 0, 200),
        'debug_user_id' => $user_id,
        'debug_receiver_id' => $msg['receiver_id']
    ]);
    exit;
}

// Fetch the request details
$stmt = $conn->prepare("SELECT r.*, u.username as requester_username FROM requests r JOIN users u ON r.user_id = u.id WHERE r.id = ?");
$stmt->bind_param('i', $request_id);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();

// Debug: log request lookup result
error_log("Looking for request ID: " . $request_id);
error_log("Request found: " . ($request ? 'yes' : 'no'));
if ($request) {
    error_log("Request details - is_direct: " . $request['is_direct'] . ", direct_to_user: " . $request['direct_to_user']);
}

if (!$request) {
    echo json_encode([
        'error' => 'Request not found', 
        'debug_request_id' => $request_id,
        'debug_message' => 'Request with this ID does not exist in database'
    ]);
    exit;
}

// Verify this is a direct request intended for this user
if (!$request['is_direct'] || $request['direct_to_user'] != $user_id) {
    echo json_encode(['error' => 'This request is not directed to you']);
    exit;
}

if ($is_confirm) {
    // Helper: compute next date for a given weekday name (Monday..Sunday)
    $computeNextDate = function(string $dayName): string {
        $map = [
            'Sunday' => 0, 'Monday' => 1, 'Tuesday' => 2, 'Wednesday' => 3,
            'Thursday' => 4, 'Friday' => 5, 'Saturday' => 6
        ];
        $dayName = ucfirst(strtolower($dayName));
        if (!isset($map[$dayName])) return date('Y-m-d');
        $today = new DateTime('today');
        $target = (int)$map[$dayName];
        $diff = ($target - (int)$today->format('w') + 7) % 7;
        if ($diff === 0) { $diff = 7; } // next same weekday
        $today->modify("+{$diff} day");
        return $today->format('Y-m-d');
    };

    // Determine meeting details
    // Day: from availability_days JSON or from the direct message text
    $selected_day = '';
    if (!empty($request['availability_days'])) {
        $days = $request['availability_days'];
        if (is_string($days)) {
            $decoded = json_decode($days, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $days = $decoded;
            }
        }
        if (is_array($days) && count($days) > 0) {
            // Choose the soonest upcoming day from the list
            $weekOrder = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
            // Compute next date for each and pick the earliest
            $candidates = [];
            foreach ($days as $d) {
                if (!in_array($d, $weekOrder, true)) continue;
                $dt = new DateTime($computeNextDate($d));
                $candidates[] = ['day'=>$d, 'date'=>$dt];
            }
            if (!empty($candidates)) {
                usort($candidates, function($a,$b){ return $a['date'] <=> $b['date']; });
                $selected_day = $candidates[0]['day'];
                $date = $date ?: $candidates[0]['date']->format('Y-m-d');
            }
        }
    }
    // Fallback: try parsing message text for a weekday
    if (!$selected_day) {
        if (preg_match('/\b(Sunday|Monday|Tuesday|Wednesday|Thursday|Friday|Saturday)\b/i', $msg['message_text'], $m)) {
            $selected_day = ucfirst(strtolower($m[1]));
            $date = $date ?: $computeNextDate($selected_day);
        } else {
            $selected_day = date('l');
            $date = $date ?: date('Y-m-d');
        }
    }

    // Time and duration from request
    $time = $request['start_time'] ? substr($request['start_time'], 0, 5) : '';
    $duration = $request['estimated_hours'] !== null ? (is_numeric($request['estimated_hours']) ? (float)$request['estimated_hours'] : 1) : 1;

    // Get accepting username (current user)
    $accepting_username = '';
    $uStmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
    if ($uStmt) {
        $uStmt->bind_param('i', $user_id);
        $uStmt->execute();
        $uStmt->bind_result($accepting_username);
        $uStmt->fetch();
        $uStmt->close();
    }
    if (!$accepting_username) { $accepting_username = 'User'; }

    // Confirm: Delete the request since it's now confirmed
    $stmt = $conn->prepare("DELETE FROM requests WHERE id = ?");
    $stmt->bind_param('i', $request_id);
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Failed to process request.']);
        exit;
    }

    // Send standard meeting confirmation message so calendar can parse it
    $tokens = isset($request['tokens']) ? (int)$request['tokens'] : 0;
    $reqBy = (int)$request['user_id'];
    $helperId = (int)$user_id;
    $confirmation = "Your request has been confirmed! Meeting details: {$selected_day} ({$date}) at {$time} for {$duration} hour(s) with {$accepting_username}. <!--TOK:$tokens--><!--REQBY:$reqBy--><!--HELPER:$helperId-->";
    $insert = $conn->prepare("INSERT INTO messages (conversation_id, sender_id, receiver_id, message_text) VALUES (?, ?, ?, ?)");
    $insert->bind_param('iiis', $msg['conversation_id'], $user_id, $msg['sender_id'], $confirmation);
    $insert->execute();

    echo json_encode(['success' => true, 'message' => 'Direct request confirmed!', 'request_id' => $request_id]);
} else {
    // Decline: Remove direct targeting but keep request visible to others
    $stmt = $conn->prepare("UPDATE requests SET is_direct = 0, direct_to_user = NULL WHERE id = ?");
    $stmt->bind_param('i', $request_id);
    $stmt->execute();
    
    // Send decline message
    $decline = "Direct request declined. I cannot help with \"{$request['title']}\" at this time. The request is now available to other users.";
    $insert = $conn->prepare("INSERT INTO messages (conversation_id, sender_id, receiver_id, message_text) VALUES (?, ?, ?, ?)");
    $insert->bind_param('iiis', $msg['conversation_id'], $user_id, $msg['sender_id'], $decline);
    $insert->execute();
    
    echo json_encode(['success' => true, 'message' => 'Direct request declined and made public.', 'request_id' => $request_id]);
}
?>