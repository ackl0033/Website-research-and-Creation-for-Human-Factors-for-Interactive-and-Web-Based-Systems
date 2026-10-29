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

// Extract selected availability details from the message text
$selected_day = '';
$selected_date = '';
$selected_time = '';
$selected_duration = '';
$accepting_username = '';

// Parse the acceptance message format: "User {username} wants to accept your request on {day} ({date}) at {time} for {duration} hour(s)"
if (preg_match('/User (\w+) wants to accept your request on (\w+) \(([^)]+)\) at ([^)]+) for ([^)]+) hour\(s\)/', $msg['message_text'], $matches)) {
    $accepting_username = $matches[1];
    $selected_day = $matches[2];
    $selected_date = $matches[3];
    $selected_time = $matches[4];
    $selected_duration = $matches[5];
}

// Require request ID in the message text (either as #id or hidden comment)
$request_id = null;
if (preg_match('/<!--RID:(\d+)-->/i', $msg['message_text'], $idMatch)) {
    $request_id = intval($idMatch[1]);
} elseif (preg_match('/request #(\d+)/', $msg['message_text'], $idMatch)) {
    $request_id = intval($idMatch[1]);
}
if (!$request_id) {
    echo json_encode(['error' => 'No pending request found (request ID missing in message). Please ensure the request was accepted using the latest system.']);
    exit;
}

// Fetch the request row for further use
$stmt = $conn->prepare("SELECT * FROM requests WHERE id = ?");
$stmt->bind_param('i', $request_id);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();
if (!$request) {
    echo json_encode(['error' => 'Request not found']);
    exit;
}

if ($is_confirm) {
    // Confirm: remove the request from the table
    $stmt = $conn->prepare("DELETE FROM requests WHERE id = ?");
    $stmt->bind_param('i', $request_id);
    $stmt->execute();
    
    // Send a confirmation message with the selected availability details
    if ($selected_day && $selected_date && $selected_time && $selected_duration) {
        // Ensure date is today or in the future
        $todayYmd = date('Y-m-d');
        $dateToUse = ($selected_date < $todayYmd) ? $todayYmd : $selected_date;
        $confirmation = "Your request has been confirmed! Meeting details: $selected_day ($dateToUse) at $selected_time for $selected_duration hour(s) with $accepting_username.";
    } else {
        // Fallback: try to compute a next date from availability_days and include standard wording
        $fallbackDay = '';
        $fallbackDate = date('Y-m-d');
        $fallbackTime = $request['start_time'] ? substr($request['start_time'],0,5) : '';
        $fallbackDuration = $request['estimated_hours'] !== null ? (is_numeric($request['estimated_hours']) ? (float)$request['estimated_hours'] : 1) : 1;
        if (!empty($request['availability_days'])) {
            $days = $request['availability_days'];
            if (is_string($days)) {
                $decoded = json_decode($days, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $days = $decoded;
                }
            }
            if (is_array($days) && count($days) > 0) {
                // compute next date for the first weekday
                $map = ['Sunday'=>0,'Monday'=>1,'Tuesday'=>2,'Wednesday'=>3,'Thursday'=>4,'Friday'=>5,'Saturday'=>6];
                foreach ($days as $d) {
                    if (isset($map[$d])) { $fallbackDay = $d; break; }
                }
                if ($fallbackDay) {
                    $today = new DateTime('today');
                    $target = $map[$fallbackDay];
                    $diff = ($target - (int)$today->format('w') + 7) % 7;
                    if ($diff === 0) { $diff = 7; }
                    $today->modify("+{$diff} day");
                    $fallbackDate = $today->format('Y-m-d');
                }
            }
        }
        if (!$fallbackDay) { $fallbackDay = date('l', strtotime($fallbackDate)); }
        $confirmation = "Your request has been confirmed! Meeting details: $fallbackDay ($fallbackDate) at $fallbackTime for $fallbackDuration hour(s) with $accepting_username.";
    }
    // Add hidden tags for tokens and participants for later completion
    // IMPORTANT: helperId must be the user who accepted the request (the sender of the acceptance message),
    // not the current user confirming it (who is the requester/owner).
    $tokens = isset($request['tokens']) ? (int)$request['tokens'] : 0;
    $reqBy = (int)$request['user_id'];
    $helperId = isset($msg['sender_id']) ? (int)$msg['sender_id'] : (int)$user_id; // fallback to current user if missing (should not happen)
    $confirmation .= " <!--TOK:$tokens--><!--REQBY:$reqBy--><!--HELPER:$helperId-->";
    
    $insert = $conn->prepare("INSERT INTO messages (conversation_id, sender_id, receiver_id, message_text) VALUES (?, ?, ?, ?)");
    $insert->bind_param('iiis', $msg['conversation_id'], $user_id, $msg['sender_id'], $confirmation);
    $insert->execute();
        // Also remove this request title from the requester's skills_requested list
        if (!empty($request['title'])) {
            $reqById = (int)$request['user_id'];
            if ($reqById > 0) {
                $srStmt = $conn->prepare("SELECT skills_requested FROM users WHERE id = ?");
                if ($srStmt) {
                    $srStmt->bind_param('i', $reqById);
                    $srStmt->execute();
                    $srRes = $srStmt->get_result();
                    if ($row = $srRes->fetch_assoc()) {
                        $skillsJson = $row['skills_requested'];
                        $arr = [];
                        if ($skillsJson) {
                            $dec = json_decode($skillsJson, true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($dec)) $arr = $dec;
                        }
                        $title = trim((string)$request['title']);
                        $arr = array_values(array_filter($arr, function($s) use ($title) {
                            return mb_strtolower(trim((string)$s)) !== mb_strtolower($title);
                        }));
                        $newJson = json_encode($arr);
                        $updStmt = $conn->prepare("UPDATE users SET skills_requested = ? WHERE id = ?");
                        if ($updStmt) {
                            $updStmt->bind_param('si', $newJson, $reqById);
                            $updStmt->execute();
                        }
                    }
                }
            }
        }
        echo json_encode(['success' => true, 'message' => 'Request confirmed and removed.', 'request_id' => $request_id]);
} else {
    // Decline: make the request visible again
    $stmt = $conn->prepare("UPDATE requests SET is_direct = 0, direct_to_user = NULL WHERE id = ?");
    $stmt->bind_param('i', $request_id);
    $stmt->execute();
    
    // Send a decline message with the selected availability details
    if ($selected_day && $selected_date && $selected_time && $selected_duration) {
        $decline = "Your request was declined. The proposed meeting time was $selected_day ($selected_date) at $selected_time for $selected_duration hour(s) with $accepting_username. Your request is now visible to others again.";
    } else {
        // Fallback to original request details if parsing failed
        $decline = "Your request for {$request['availability_days']} at {$request['start_time']} for {$request['estimated_hours']} hour(s) was declined.";
    }
    
    $insert = $conn->prepare("INSERT INTO messages (conversation_id, sender_id, receiver_id, message_text) VALUES (?, ?, ?, ?)");
    $insert->bind_param('iiis', $msg['conversation_id'], $user_id, $msg['sender_id'], $decline);
    $insert->execute();
    echo json_encode(['success' => true, 'message' => 'Request declined and made visible again.', 'request_id' => $request_id]);
}
