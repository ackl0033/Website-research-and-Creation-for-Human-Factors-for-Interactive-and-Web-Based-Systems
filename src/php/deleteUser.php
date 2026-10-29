<?php
header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Permanently delete a user (admin action)
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

    // Get user identifier
    $userId = null;
    $username = null;
    
    if (isset($_POST['user_id']) && !empty($_POST['user_id'])) {
        $userId = intval($_POST['user_id']);
    } elseif (isset($_POST['username']) && !empty($_POST['username'])) {
        $username = trim($_POST['username']);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Missing user_id or username']);
        exit();
    }

    // Delete user and related data (handle foreign key constraints)
    if ($userId) {
        // First delete related records
        $deleteRequests = mysqli_prepare($conn, "DELETE FROM requests WHERE user_id = ?");
        mysqli_stmt_bind_param($deleteRequests, "i", $userId);
        mysqli_stmt_execute($deleteRequests);
        mysqli_stmt_close($deleteRequests);
        
        $deleteMessages = mysqli_prepare($conn, "DELETE FROM messages WHERE sender_id = ? OR receiver_id = ?");
        mysqli_stmt_bind_param($deleteMessages, "ii", $userId, $userId);
        mysqli_stmt_execute($deleteMessages);
        mysqli_stmt_close($deleteMessages);
        
        $deleteConversations = mysqli_prepare($conn, "DELETE FROM conversations WHERE user1_id = ? OR user2_id = ?");
        mysqli_stmt_bind_param($deleteConversations, "ii", $userId, $userId);
        mysqli_stmt_execute($deleteConversations);
        mysqli_stmt_close($deleteConversations);
        
        // Now delete the user
        $sql = "DELETE FROM users WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            throw new Exception('Failed to prepare statement: ' . mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmt, "i", $userId);
    } else {
        // Get user ID first for foreign key cleanup
        $getUserId = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ?");
        mysqli_stmt_bind_param($getUserId, "s", $username);
        mysqli_stmt_execute($getUserId);
        $result = mysqli_stmt_get_result($getUserId);
        $userData = mysqli_fetch_assoc($result);
        mysqli_stmt_close($getUserId);
        
        if (!$userData) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            exit();
        }
        
        $userIdToDelete = $userData['id'];
        
        // Delete related records
        $deleteRequests = mysqli_prepare($conn, "DELETE FROM requests WHERE user_id = ?");
        mysqli_stmt_bind_param($deleteRequests, "i", $userIdToDelete);
        mysqli_stmt_execute($deleteRequests);
        mysqli_stmt_close($deleteRequests);
        
        $deleteMessages = mysqli_prepare($conn, "DELETE FROM messages WHERE sender_id = ? OR receiver_id = ?");
        mysqli_stmt_bind_param($deleteMessages, "ii", $userIdToDelete, $userIdToDelete);
        mysqli_stmt_execute($deleteMessages);
        mysqli_stmt_close($deleteMessages);
        
        $deleteConversations = mysqli_prepare($conn, "DELETE FROM conversations WHERE user1_id = ? OR user2_id = ?");
        mysqli_stmt_bind_param($deleteConversations, "ii", $userIdToDelete, $userIdToDelete);
        mysqli_stmt_execute($deleteConversations);
        mysqli_stmt_close($deleteConversations);
        
        // Now delete the user
        $sql = "DELETE FROM users WHERE username = ?";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            throw new Exception('Failed to prepare statement: ' . mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmt, "s", $username);
    }

    $success = mysqli_stmt_execute($stmt);
    if (!$success) {
        throw new Exception('Failed to execute statement: ' . mysqli_stmt_error($stmt));
    }
    
    $affected = mysqli_affected_rows($conn);

    if ($affected > 0) {
        echo json_encode(['success' => true]);
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
