<?php
// Unsuspend a user (admin action)
require_once "connect.php";
session_start();

if (!isset($_SESSION['user_id']) || !isset($_POST['user_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing user id']);
    exit();
}

// Only allow admins
$adminId = $_SESSION['user_id'];
$adminCheck = mysqli_query($conn, "SELECT isAdmin FROM users WHERE id = $adminId");
if (!mysqli_fetch_assoc($adminCheck)['isAdmin']) {
    http_response_code(403);
    echo json_encode(['error' => 'Not authorized']);
    exit();
}

$userId = intval($_POST['user_id']);

$sql = "UPDATE users SET isSuspended = FALSE, suspended_reason = NULL, suspended_until = NULL WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $userId);
$success = mysqli_stmt_execute($stmt);

if ($success) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to unsuspend user']);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>