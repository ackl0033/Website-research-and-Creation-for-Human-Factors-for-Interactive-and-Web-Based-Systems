<?php
session_start();
header('Content-Type: application/json');

require_once 'connect.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
    echo json_encode(['error' => 'Missing id']);
    exit;
}

$stmt = mysqli_prepare($conn, "SELECT id, user_id, title FROM requests WHERE id = ?");
if (!$stmt) {
    echo json_encode(['error' => 'DB error']);
    exit;
}
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$row) {
    echo json_encode(['error' => 'Not found']);
    exit;
}

echo json_encode([
    'id' => (int)$row['id'],
    'user_id' => (int)$row['user_id'],
    'title' => $row['title']
]);
exit;
?>
