<?php
require_once "connect.php";
header('Content-Type: application/json');

$sql = "SELECT r.*, u.username, TIME_FORMAT(r.start_time, '%H:%i') as start_time_formatted, TIME_FORMAT(r.end_time, '%H:%i') as end_time_formatted FROM requests r JOIN users u ON r.user_id = u.id WHERE r.is_direct = 0 ORDER BY r.created_at DESC";
$result = mysqli_query($conn, $sql);

$requests = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $requests[] = [
            "id" => $row['id'],
            "username" => $row['username'],
            "title" => $row['title'],
            "description" => $row['description'],
            "tokens" => $row['tokens'],
            "estimated_hours" => $row['estimated_hours'],
            "category" => $row['category'],
            "topic" => $row['topic'],
            "academic_year" => $row['academic_year'],
            "availability_days" => $row['availability_days'],
            "start_time" => $row['start_time'],
            "end_time" => $row['end_time'],
            "start_time_display" => $row['start_time_formatted'],
            "end_time_display" => $row['end_time_formatted'],
            "is_direct" => $row['is_direct'],
            "direct_to_user" => $row['direct_to_user'],
            "created_at" => $row['created_at']
        ];
    }
}
echo json_encode($requests);
exit();