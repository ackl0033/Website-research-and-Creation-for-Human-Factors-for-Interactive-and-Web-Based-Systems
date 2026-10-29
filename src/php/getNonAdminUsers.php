<?php
require_once "connect.php";

// Get all non-admin users
$sql = "SELECT id, username, email, token, name, 
              COALESCE(isSuspended, 0) as isSuspended, 
              COALESCE(suspended_reason, '') as suspended_reason, 
              suspended_until 
       FROM users WHERE isAdmin = FALSE";
$result = mysqli_query($conn, $sql);

$users = [];
while ($row = mysqli_fetch_assoc($result)) {
    $users[] = $row;
}

header('Content-Type: application/json');
echo json_encode($users);
?>