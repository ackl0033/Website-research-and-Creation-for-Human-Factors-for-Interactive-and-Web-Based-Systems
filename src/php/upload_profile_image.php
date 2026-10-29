<?php
session_start();
require_once __DIR__ . '/connect.php';

// Only allow logged-in users
if (!isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(["error" => "Not authenticated"]);
    exit;
}

header('Content-Type: application/json');

// Validate file presence
if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(["error" => "No file uploaded or upload error."]);
    exit;
}

$username = $_SESSION['username'];

// Validate file size (max 2MB)
$maxSize = 2 * 1024 * 1024; // 2MB
if ($_FILES['profile_image']['size'] > $maxSize) {
    http_response_code(413);
    echo json_encode(["error" => "File too large. Max 2MB."]);
    exit;
}

// Validate MIME type using finfo with fallbacks
$mime = null;
if (class_exists('finfo')) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($_FILES['profile_image']['tmp_name']);
}
if (!$mime && function_exists('mime_content_type')) {
    $mime = mime_content_type($_FILES['profile_image']['tmp_name']);
}
if (!$mime && function_exists('exif_imagetype')) {
    $type = @exif_imagetype($_FILES['profile_image']['tmp_name']);
    $map = [
        IMAGETYPE_JPEG => 'image/jpeg',
        IMAGETYPE_PNG  => 'image/png',
        IMAGETYPE_WEBP => 'image/webp'
    ];
    $mime = $map[$type] ?? null;
}
if (!$mime) {
    $sizeInfo = @getimagesize($_FILES['profile_image']['tmp_name']);
    if ($sizeInfo && isset($sizeInfo['mime'])) {
        $mime = $sizeInfo['mime'];
    }
}

$allowed = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp'
];

if (!isset($allowed[$mime])) {
    http_response_code(415);
    echo json_encode(["error" => "Unsupported file type. Use JPG, PNG, or WEBP."]);
    exit;
}

// Build destination path (relative to web root /src)
$imagesDirRelative = 'Images/profile';
$imagesDir = realpath(__DIR__ . '/../') . DIRECTORY_SEPARATOR . $imagesDirRelative;

// Ensure directory exists
if (!is_dir($imagesDir)) {
    if (!mkdir($imagesDir, 0775, true)) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to create images directory."]);
        exit;
    }
}

// Generate safe filename
$ext = $allowed[$mime];
$safeUser = preg_replace('/[^a-zA-Z0-9_-]/', '_', $username);
$newName = $safeUser . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
$destPath = $imagesDir . DIRECTORY_SEPARATOR . $newName;
$relativePath = $imagesDirRelative . '/' . $newName; // For web

// Move uploaded file
if (!move_uploaded_file($_FILES['profile_image']['tmp_name'], $destPath)) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to save uploaded file."]);
    exit;
}

// Optional: remove previous profile image to avoid orphans
$prevSql = "SELECT profile_image FROM users WHERE username = ?";
if ($prevStmt = $conn->prepare($prevSql)) {
    $prevStmt->bind_param('s', $username);
    $prevStmt->execute();
    $prevStmt->bind_result($prevPath);
    if ($prevStmt->fetch()) {
        if ($prevPath) {
            $prevFull = realpath(__DIR__ . '/../' . $prevPath);
            // Ensure it's within the intended directory before deleting
            if ($prevFull && strpos($prevFull, realpath($imagesDir)) === 0 && file_exists($prevFull)) {
                @unlink($prevFull);
            }
        }
    }
    $prevStmt->close();
}

// Update DB with new path
$sql = "UPDATE users SET profile_image = ? WHERE username = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(["error" => "DB prepare failed: " . $conn->error]);
    exit;
}

$stmt->bind_param('ss', $relativePath, $username);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to update profile image in DB."]);
    exit;
}

echo json_encode(["success" => true, "path" => '/' . $relativePath]);
exit;
?>

// GPT-5 
line 39 it added a new varchar into the database which will store the path of the image and
then validates the image type and size before saving it to the server and updating the user's profile.
