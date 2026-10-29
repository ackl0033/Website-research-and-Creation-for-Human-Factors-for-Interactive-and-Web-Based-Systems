<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

require_once '../inc/dbconn.inc.php';
$user_id = $_SESSION['user_id'];

// Get confirmed meetings from messages where the user is involved
// Look for confirmation messages that contain meeting details
$sql = "SELECT m.*, c.user1_id, c.user2_id, c.user1_username, c.user2_username
    FROM messages m 
    JOIN conversations c ON m.conversation_id = c.id 
    WHERE (c.user1_id = ? OR c.user2_id = ?) 
    AND (m.message_text LIKE '%Your request has been confirmed! Meeting details:%' OR m.message_text LIKE '%Meeting details:%')
    ORDER BY m.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

$events = [];
$today = new DateTime();
$today->setTime(0, 0, 0); // Set to beginning of day for comparison

// Parse confirmed meetings from messages
while ($row = $result->fetch_assoc()) {
    // Parse the meeting details from the confirmation message
    // Format: "Your request has been confirmed! Meeting details: Monday (2025-10-20) at 2:00 PM for 2 hour(s) with John."
    if (preg_match('/Meeting details: (\w+) \(([^)]+)\) at ([^)]+) for ([^)]+) hour\(s\) with (\w+)/', $row['message_text'], $matches)) {
        $day = $matches[1];
        $date = $matches[2];
        $time = $matches[3];
        $duration = $matches[4];
        $partner = $matches[5];
        
        // Parse the meeting date
        try {
            $meetingDate = new DateTime($date);
            $meetingDate->setTime(0, 0, 0); // Set to beginning of day for comparison
            
            // Skip meetings that are in the past
            if ($meetingDate < $today) {
                continue;
            }
        } catch (Exception $e) {
            // If date parsing fails, skip this event
            continue;
        }
        
        // Determine who the user is meeting with
        $other_user = '';
        if ($row['user1_id'] == $user_id) {
            $other_user = $row['user2_username'];
        } else {
            $other_user = $row['user1_username'];
        }
        
        // If the partner name doesn't match the conversation partner, use the one from the message
        if ($partner !== $other_user) {
            $other_user = $partner;
        }
        
        // Extract tokens/participants and completion flag if present
        $tokens = 0; $reqBy = null; $helperId = null; $isDone = false;
        if (preg_match('/<!--TOK:(\d+)-->/', $row['message_text'], $mm)) { $tokens = intval($mm[1]); }
        if (preg_match('/<!--REQBY:(\d+)-->/', $row['message_text'], $mm2)) { $reqBy = intval($mm2[1]); }
        if (preg_match('/<!--HELPER:(\d+)-->/', $row['message_text'], $mm3)) { $helperId = intval($mm3[1]); }
        if (strpos($row['message_text'], '<!--DONE-->') !== false) { $isDone = true; }

        $events[] = [
            'id' => $row['id'], // message id
            'title' => "Meeting with $other_user",
            'date' => $date,
            'day' => $day,
            'time' => $time,
            'duration' => $duration,
            'partner' => $other_user,
            'created_at' => $row['created_at'],
            'meeting_date' => $meetingDate->format('Y-m-d'),
            'tokens' => $tokens,
            'requester_id' => $reqBy,
            'helper_id' => $helperId,
            'completed' => $isDone
        ];
    }
}

echo json_encode($events);
?>

// GPT-5 
line 2 when the message button is accepted it will parse information using fetch and retrieve
confirmed meetings and then return it in a structured format to then display in the calendar.
