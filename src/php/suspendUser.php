<?php
// Suspend a user (admin action)
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
$reason = $_POST['reason'] ?? 'No reason provided';
$duration = $_POST['duration'] ?? 'Indefinitely';

// Calculate suspended_until
if ($duration === 'Indefinitely') {
    $suspended_until = null;
} else {
    // Use South Australia timezone for suspension end
    $now = new DateTime('now', new DateTimeZone('Australia/Adelaide'));
    $suspended_until = $now->modify("+{$duration}")->format('Y-m-d H:i:s');
}

$sql = "UPDATE users SET isSuspended = TRUE, suspended_reason = ?, suspended_until = ? WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ssi", $reason, $suspended_until, $userId);
$success = mysqli_stmt_execute($stmt);

if ($success) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to suspend user']);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>