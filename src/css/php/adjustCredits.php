<?php
header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Adjust user credits (admin action)
session_start();
require_once "connect.php";

try {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing admin session']);
        exit();
    }

    // Only allow admins
    $adminId = $_SESSION['user_id'];
    $adminCheck = mysqli_query($conn, "SELECT isAdmin FROM users WHERE id = $adminId");
    
    if (!$adminCheck) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error checking admin status']);
        exit();
    }
    
    $adminData = mysqli_fetch_assoc($adminCheck);
    if (!$adminData || !$adminData['isAdmin']) {
        http_response_code(403);
        echo json_encode(['error' => 'Not authorized']);
        exit();
    }

    // Get parameters
    if (!isset($_POST['username']) || !isset($_POST['amount'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing username or amount']);
        exit();
    }

    $username = trim($_POST['username']);
    $amount = intval($_POST['amount']);

    if (empty($username)) {
        http_response_code(400);
        echo json_encode(['error' => 'Username cannot be empty']);
        exit();
    }

    // Update user credits
    $sql = "UPDATE users SET token = token + ? WHERE username = ?";
    $stmt = mysqli_prepare($conn, $sql);
    
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, "is", $amount, $username);
    $success = mysqli_stmt_execute($stmt);
    
    if (!$success) {
        throw new Exception('Failed to execute statement: ' . mysqli_stmt_error($stmt));
    }
    
    $affected = mysqli_affected_rows($conn);

    if ($affected > 0) {
        // Get the updated credit amount
        $selectStmt = mysqli_prepare($conn, "SELECT token FROM users WHERE username = ?");
        mysqli_stmt_bind_param($selectStmt, "s", $username);
        mysqli_stmt_execute($selectStmt);
        $result = mysqli_stmt_get_result($selectStmt);
        $userData = mysqli_fetch_assoc($result);
        
        echo json_encode([
            'success' => true, 
            'newBalance' => $userData['token']
        ]);
        mysqli_stmt_close($selectStmt);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
    }

    mysqli_stmt_close($stmt);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
} finally {
    if (isset($conn)) {
        mysqli_close($conn);
    }
}
?>