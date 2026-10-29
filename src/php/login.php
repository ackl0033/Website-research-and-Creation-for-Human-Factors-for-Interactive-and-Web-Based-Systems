<?php
session_start();
include 'connect.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $username = isset($_POST['username']) ? trim($_POST['username']) : '';
  $password = isset($_POST['password']) ? trim($_POST['password']) : '';

  if (empty($username) || empty($password)) {
    header("Location: ../signIn.html?error=missing_fields&msg=" . urlencode('Please fill in both fields.'));
    exit;
  }
  
  $sql = "SELECT id, username, password, isSuspended, suspended_reason, suspended_until FROM users WHERE username = ?";
  $stmt = mysqli_prepare($conn, $sql);
  mysqli_stmt_bind_param($stmt, "s", $username);
  mysqli_stmt_execute($stmt);
  mysqli_stmt_bind_result($stmt, $uid, $uname, $hash, $isSuspended, $s_reason, $s_until);
  if (mysqli_stmt_fetch($stmt)) {
    // We have a row; close the statement cursor
    mysqli_stmt_close($stmt);
    if (password_verify($password, $hash)) {
      if ($isSuspended) {
        $suspensionMsg = "Account Suspended\n\nReason: " . ($s_reason ?: 'No reason provided');
        if (!empty($s_until)) {
          $suspensionMsg .= "\nSuspension expires: " . date('F j, Y g:i A', strtotime($s_until));
        } else {
          $suspensionMsg .= "\nDuration: Indefinitely";
        }
        $suspensionMsg .= "\n\nContact an administrator for more information.";
        header("Location: ../signIn.html?error=suspended&msg=" . urlencode($suspensionMsg));
        exit;
      }
      $_SESSION['user_id'] = $uid;
      $_SESSION['username'] = $uname;
      header("Location: ../home.php");
      exit;
    } else {
      header("Location: ../signIn.html?error=invalid_password&msg=" . urlencode('Invalid password.'));
      exit;
    }
  } else {
    mysqli_stmt_close($stmt);
    header("Location: ../signIn.html?error=user_not_found&msg=" . urlencode('User not found.'));
    exit;
  }
}
mysqli_close($conn);
?>