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
    // Get recent requests from other users (not current user)
    $stmt = $conn->prepare("
        SELECT 
            r.title,
            r.tokens,
            r.estimated_hours,
            u.username
        FROM requests r
        JOIN users u ON r.user_id = u.id
        WHERE r.user_id != ?
        ORDER BY r.created_at DESC
        LIMIT 10
    ");
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $requests = [];
    while ($row = $result->fetch_assoc()) {
        $requests[] = [
            'title' => $row['title'],
            'token_cost' => intval($row['tokens']),
            'estimated_hours' => floatval($row['estimated_hours']),
            'username' => $row['username']
        ];
    }
    
    echo json_encode($requests);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>