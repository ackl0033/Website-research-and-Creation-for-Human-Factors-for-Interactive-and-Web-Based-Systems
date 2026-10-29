<?php
session_start();
require_once "connect.php";

header('Content-Type: text/plain');

// Ensure user is logged in
if (!isset($_SESSION['username'])) {
    echo "Error: User not logged in.";
    exit;
}

// Get raw POST data
$data = json_decode(file_get_contents("php://input"), true);

$currentPassword = $data['currentPassword'] ?? '';
$newPassword = $data['newPassword'] ?? '';
$username = $_SESSION['username'];

if (empty($currentPassword) || empty($newPassword)) {
    echo "❌ Please fill out all fields.";
    exit;
}

// Fetch current password hash from DB
$stmt = $conn->prepare("SELECT password FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->bind_result($hashedPassword);
$stmt->fetch();
$stmt->close();

// Verify current password
if (!password_verify($currentPassword, $hashedPassword)) {
    echo "❌ Current password is incorrect.";
    exit;
}

// Hash new password
$newHashed = password_hash($newPassword, PASSWORD_DEFAULT);

// Update password in DB
$update = $conn->prepare("UPDATE users SET password = ? WHERE username = ?");
$update->bind_param("ss", $newHashed, $username);

if ($update->execute()) {
    if ($update->affected_rows > 0) {
        echo "✅ Password updated successfully!";
    } else {
        echo "⚠️ No rows were updated — is the username correct?";
    }
} else {
    echo "❌ Failed to update password: " . $update->error;
}


$update->close();
$conn->close();
?>