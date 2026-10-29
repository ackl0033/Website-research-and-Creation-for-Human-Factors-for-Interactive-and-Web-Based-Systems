<?php
require_once "connect.php";
header('Content-Type: application/json');

$sql = "SELECT id, username, year, skills_offered, skills_requested FROM users";
$result = mysqli_query($conn, $sql);

$users = [];
if ($result) {
	while ($row = mysqli_fetch_assoc($result)) {
		// Decode JSON fields if present
		$skillsOffered = isset($row['skills_offered']) ? json_decode($row['skills_offered'], true) : [];
		$skillsRequested = isset($row['skills_requested']) ? json_decode($row['skills_requested'], true) : [];
		
		// Show all users in search, but don't display skills requested
		$users[] = [
			"id" => $row['id'],
			"Name" => $row['username'],
			"type" => "User",
			"year" => $row['year'],
			"skillsOffered" => $skillsOffered,
			"skillsRequested" => [] // Always show empty array for skills requested in search
		];
	}
}
echo json_encode($users);
exit();
