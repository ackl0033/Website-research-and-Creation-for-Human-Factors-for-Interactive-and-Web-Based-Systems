<?php
// Check if current user is suspended
function checkUserSuspension($conn, $userId) {
    $sql = "SELECT isSuspended, suspended_reason, suspended_until FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($user && $user['isSuspended']) {
        // Check if suspension has expired
        if ($user['suspended_until'] && strtotime($user['suspended_until']) < time()) {
            // Suspension expired, reactivate user
            $updateSql = "UPDATE users SET isSuspended = FALSE, suspended_reason = NULL, suspended_until = NULL WHERE id = ?";
            $updateStmt = mysqli_prepare($conn, $updateSql);
            mysqli_stmt_bind_param($updateStmt, "i", $userId);
            mysqli_stmt_execute($updateStmt);
            mysqli_stmt_close($updateStmt);
            return false; // User is no longer suspended
        }
        
        // User is still suspended
        $suspensionMsg = "❌ Account Suspended\\n\\nReason: " . ($user['suspended_reason'] ?: 'No reason provided');
        if ($user['suspended_until']) {
            $suspensionMsg .= "\\nSuspension expires: " . date('F j, Y g:i A', strtotime($user['suspended_until']));
        } else {
            $suspensionMsg .= "\\nDuration: Indefinitely";
        }
        $suspensionMsg .= "\\n\\nContact an administrator for more information.";
        
        session_destroy();
        echo "<script>alert('" . addslashes($suspensionMsg) . "'); window.location.href = 'signIn.html';</script>";
        exit;
    }
    
    return false; // User is not suspended
}
?>