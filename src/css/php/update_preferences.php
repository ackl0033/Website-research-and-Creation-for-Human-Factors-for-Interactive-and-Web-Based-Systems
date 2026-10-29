<?php
session_start();
require_once "connect.php";
header("Content-Type: text/plain");

if (!isset($_SESSION["username"])) {
    echo "❌ User not logged in.";
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$notifications = $data["notifications"] ?? '';
$language = $data["language"] ?? '';
$username = $_SESSION["username"];

$valid_notifications = ["all", "important", "none"];
$valid_languages = ["en", "zh", "ja"];

if (!in_array($notifications, $valid_notifications) || !in_array($language, $valid_languages)) {
    echo "❌ Invalid input.";
    exit;
}

$stmt = $conn->prepare("UPDATE users SET notification_pref = ?, language_pref = ? WHERE username = ?");
$stmt->bind_param("sss", $notifications, $language, $username);

if ($stmt->execute()) {
    echo "✅ Preferences updated successfully!";
} else {
    echo "❌ Failed to update preferences: " . $stmt->error;
}

$stmt->close();
$conn->close();