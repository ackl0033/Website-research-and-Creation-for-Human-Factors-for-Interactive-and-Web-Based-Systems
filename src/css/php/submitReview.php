<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

require_once '../inc/dbconn.inc.php';
$user_id = $_SESSION['user_id'];

$message_id = isset($_POST['message_id']) ? intval($_POST['message_id']) : 0;
$helper_id = isset($_POST['helper_id']) ? intval($_POST['helper_id']) : 0;
$rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
$review_text = isset($_POST['review_text']) ? trim($_POST['review_text']) : '';

// Debug logging
error_log("Review submission data: message_id=$message_id, helper_id=$helper_id, rating=$rating, user_id=$user_id");

if (!$message_id || !$helper_id || !$rating || $rating < 1 || $rating > 5) {
    error_log("Invalid review data - message_id: $message_id, helper_id: $helper_id, rating: $rating");
    echo json_encode(['error' => 'Invalid review data', 'debug' => [
        'message_id' => $message_id,
        'helper_id' => $helper_id, 
        'rating' => $rating,
        'post_data' => $_POST
    ]]);
    exit;
}

// Ensure reviews table exists
$createTableSQL = "CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reviewer_id INT NOT NULL,
    reviewee_id INT NOT NULL,
    meeting_message_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    review_text TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reviewer_id) REFERENCES users(id),
    FOREIGN KEY (reviewee_id) REFERENCES users(id),
    FOREIGN KEY (meeting_message_id) REFERENCES messages(id),
    UNIQUE KEY unique_review_per_meeting (reviewer_id, meeting_message_id)
)";
$conn->query($createTableSQL);

// Check if review already exists for this meeting
$checkStmt = $conn->prepare("SELECT id FROM reviews WHERE reviewer_id = ? AND meeting_message_id = ?");
$checkStmt->bind_param('ii', $user_id, $message_id);
$checkStmt->execute();
if ($checkStmt->get_result()->fetch_assoc()) {
    echo json_encode(['error' => 'You have already reviewed this meeting']);
    exit;
}

// Insert review
$stmt = $conn->prepare("INSERT INTO reviews (reviewer_id, reviewee_id, meeting_message_id, rating, review_text) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param('iiiis', $user_id, $helper_id, $message_id, $rating, $review_text);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Review submitted successfully']);
} else {
    error_log("Database error: " . $stmt->error);
    error_log("MySQL error: " . $conn->error);
    echo json_encode(['error' => 'Failed to submit review', 'debug' => [
        'stmt_error' => $stmt->error,
        'conn_error' => $conn->error,
        'reviewer_id' => $user_id,
        'reviewee_id' => $helper_id,
        'message_id' => $message_id,
        'rating' => $rating
    ]]);
}
?>
