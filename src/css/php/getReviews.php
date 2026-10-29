
<?php
// Error reporting for debugging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

ob_start(); // Buffer output to prevent accidental output before JSON
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    error_log('Not logged in');
    ob_end_clean();
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

require_once '../inc/dbconn.inc.php';

$username = isset($_GET['username']) ? $_GET['username'] : (isset($_SESSION['username']) ? $_SESSION['username'] : '');

if (!$username) {
    error_log('No username provided');
    ob_end_clean();
    echo json_encode(['error' => 'No username provided']);
    exit;
}

// Get the user_id from username
$userStmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
if (!$userStmt) {
    error_log('User statement prepare failed: ' . $conn->error);
    ob_end_clean();
    echo json_encode(['error' => 'User lookup failed']);
    exit;
}
$userStmt->bind_param('s', $username);
$userStmt->execute();
$userResult = $userStmt->get_result();
$userData = $userResult->fetch_assoc();

if (!$userData) {
    error_log('User not found: ' . $username);
    ob_end_clean();
    echo json_encode(['error' => 'User not found']);
    exit;
}

$user_id = $userData['id'];

// Get the 3 most recent reviews for this user
$stmt = $conn->prepare("
    SELECT r.rating, r.review_text, r.created_at, u.username as reviewer_name
    FROM reviews r
    JOIN users u ON r.reviewer_id = u.id
    WHERE r.reviewee_id = ?
    ORDER BY r.created_at DESC
    LIMIT 3
");
if (!$stmt) {
    error_log('Review statement prepare failed: ' . $conn->error);
    ob_end_clean();
    echo json_encode(['error' => 'Review lookup failed']);
    exit;
}
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

$reviews = [];
while ($row = $result->fetch_assoc()) {
    $reviews[] = [
        'rating' => intval($row['rating']),
        'review_text' => $row['review_text'],
        'reviewer_name' => $row['reviewer_name'],
        'created_at' => $row['created_at']
    ];
}

ob_end_clean(); // Discard any buffered output
echo json_encode($reviews);
?>
