<?php
session_start();
require_once "connect.php";

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

// Verify admin status
$userId = $_SESSION['user_id'];
$adminCheckSql = "SELECT isAdmin FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $adminCheckSql);
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$adminUser = mysqli_fetch_assoc($result);

if (!$adminUser || !$adminUser['isAdmin']) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied - admin only']);
    exit();
}

mysqli_stmt_close($stmt);

try {
    // Get total non-admin students
    $totalStudentsSql = "SELECT COUNT(*) as count FROM users WHERE isAdmin = FALSE";
    $result = mysqli_query($conn, $totalStudentsSql);
    $totalStudents = mysqli_fetch_assoc($result)['count'];

    // Get active non-admin students (assuming active means they have logged in recently or have some activity)
    // For now, we'll just count all non-admin users as active since we don't have a last_login field
    $activeStudentsSql = "SELECT COUNT(*) as count FROM users WHERE isAdmin = FALSE";
    $result = mysqli_query($conn, $activeStudentsSql);
    $activeStudents = mysqli_fetch_assoc($result)['count'];

    // Get number of active requests (all requests since there's no status column)
    $activeRequestsSql = "SELECT COUNT(*) as count FROM requests";
    $result = mysqli_query($conn, $activeRequestsSql);
    $activeRequests = mysqli_fetch_assoc($result)['count'];

    // Get total FUSS credits (sum of all tokens from non-admin users)
    $totalCreditsSql = "SELECT SUM(CAST(token AS UNSIGNED)) as total FROM users WHERE isAdmin = FALSE";
    $result = mysqli_query($conn, $totalCreditsSql);
    $totalCreditsResult = mysqli_fetch_assoc($result);
    $totalCredits = $totalCreditsResult['total'] ?? 0;

    // Return the stats
    echo json_encode([
        'totalStudents' => intval($totalStudents),
        'activeStudents' => intval($activeStudents),
        'activeRequests' => intval($activeRequests),
        'totalCredits' => intval($totalCredits)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} finally {
    mysqli_close($conn);
}
?>