<?php
session_start();
require_once 'connect.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(["error" => "Not authenticated"]);
    exit;
}

// Get request ID from POST data
$requestId = intval($_POST['requestId'] ?? 0);
if ($requestId <= 0) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid request ID"]);
    exit;
}

$currentUsername = $_SESSION['username'];

// Start transaction
mysqli_begin_transaction($conn);

try {
    // Get request details and verify ownership
    $getStmt = mysqli_prepare($conn, "SELECT r.tokens, r.user_id, u.username FROM requests r JOIN users u ON r.user_id = u.id WHERE r.id = ?");
    if (!$getStmt) {
        throw new Exception('Prepare failed: ' . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($getStmt, 'i', $requestId);
    mysqli_stmt_execute($getStmt);
    mysqli_stmt_bind_result($getStmt, $tokens, $userId, $requestOwner);
    
    if (!mysqli_stmt_fetch($getStmt)) {
        mysqli_stmt_close($getStmt);
        throw new Exception('Request not found');
    }
    mysqli_stmt_close($getStmt);
    
    // Check if current user owns this request or is admin
    $isAdmin = false;
    $adminStmt = mysqli_prepare($conn, "SELECT isAdmin FROM users WHERE username = ?");
    if ($adminStmt) {
        mysqli_stmt_bind_param($adminStmt, 's', $currentUsername);
        mysqli_stmt_execute($adminStmt);
        mysqli_stmt_bind_result($adminStmt, $adminStatus);
        if (mysqli_stmt_fetch($adminStmt)) {
            $isAdmin = (bool)$adminStatus;
        }
        mysqli_stmt_close($adminStmt);
    }
    
    if ($requestOwner !== $currentUsername && !$isAdmin) {
        throw new Exception('You can only delete your own requests');
    }
    
    // Delete the request
    $deleteStmt = mysqli_prepare($conn, "DELETE FROM requests WHERE id = ?");
    if (!$deleteStmt) {
        throw new Exception('Prepare failed: ' . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($deleteStmt, 'i', $requestId);
    if (!mysqli_stmt_execute($deleteStmt)) {
        $err = mysqli_stmt_error($deleteStmt);
        mysqli_stmt_close($deleteStmt);
        throw new Exception('Delete failed: ' . $err);
    }
    mysqli_stmt_close($deleteStmt);
    
    // Refund tokens to the user who created the request
    $refundStmt = mysqli_prepare($conn, "UPDATE users SET token = token + ? WHERE id = ?");
    if (!$refundStmt) {
        throw new Exception('Prepare failed: ' . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($refundStmt, 'ii', $tokens, $userId);
    if (!mysqli_stmt_execute($refundStmt)) {
        $err = mysqli_stmt_error($refundStmt);
        mysqli_stmt_close($refundStmt);
        throw new Exception('Refund failed: ' . $err);
    }
    mysqli_stmt_close($refundStmt);
    
    // Commit transaction
    mysqli_commit($conn);
    
    echo json_encode([
        "success" => true, 
        "message" => "Request deleted successfully and $tokens tokens refunded to $requestOwner"
    ]);
    
} catch (Exception $e) {
    mysqli_rollback($conn);
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
?>