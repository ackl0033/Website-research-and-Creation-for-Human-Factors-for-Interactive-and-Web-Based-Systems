<?php
session_start();
require_once "connect.php";


if (!isset($_SESSION['username'])) {
    echo "Error: User not logged in.";
    exit;
}

// Get username
$username = $_SESSION['username'];

// List of allowed fields to update for security
$allowed_fields = ['profileName', 'profileDegree', 'profileCollege', 'profileYear', 'profileBio', 'skills_offered', 'username'];

$field_map = [
  'profileName' => 'name',
  'profileDegree' => 'degree',
  'profileCollege' => 'college',
  'profileYear' => 'year',
  'profileBio' => 'bio',
  'skills_offered' => 'skills_offered'
];

$fields = [];
$values = [];
$params_types = ''; // for bind_param
$params_values = [];

// Loop over POST data and check allowed fields
foreach ($_POST as $key => $value) {
    if (array_key_exists($key, $field_map)) {
        $fields[] = $field_map[$key] . " = ?";
        $values[] = $value;
        $params_types .= 's'; 
        $params_values[] = &$values[count($values) - 1];
    }
}

if (count($fields) === 0) {
    echo "No valid data received.";
    exit;
}

// Build SQL UPDATE dynamically
$sql = "UPDATE users SET " . implode(", ", $fields) . " WHERE username = ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo "Prepare failed: " . $conn->error;
    exit;
}

// Add username param
$params_types .= 's';
$params_values[] = &$username;

// Bind params dynamically
call_user_func_array([$stmt, 'bind_param'], array_merge([$params_types], $params_values));

if ($stmt->execute()) {
    echo "Profile updated successfully.";
} else {
    echo "Error updating profile: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>