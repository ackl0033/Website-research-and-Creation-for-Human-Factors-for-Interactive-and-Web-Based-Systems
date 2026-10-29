<?php

require_once "./php/connect.php";

echo "<h1>COMP2030 Assignment Dev Environment Setup</h1><h2>Connected successfully to MySQL database!</h2>";

// Create a table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS messages (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    message VARCHAR(255) NOT NULL,
    reg_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "<p>Table \"messages\" created successfully or already exists.</p>";
} else {
    echo "<p>Error creating table: " . $conn->error . "</p>";
}

// Insert some data (only if table is empty for demonstration)
$check_empty = $conn->query("SELECT COUNT(*) as count FROM messages");
$row = $check_empty->fetch_assoc();
if ($row['count'] == 0) {
    $insert_sql = "INSERT INTO messages (message) VALUES ('Connection and insert into db successful')";
    if ($conn->query($insert_sql) === TRUE) {
        echo "<p>New record created successfully.</p>";
    } else {
        echo "<p>Error: " . $insert_sql . "<br>" . $conn->error . "</p>";
    }
}

// Display messages
$result = $conn->query("SELECT id, message, reg_date FROM messages");

if ($result->num_rows > 0) {
    echo "<h2>Messages:</h2>";
    echo "<ul>";
    while($row = $result->fetch_assoc()) {
        echo "<li>" . $row["id"]. " - " . $row["message"]. " (" . $row["reg_date"]. ")</li>";
    }
    echo "</ul>";
} else {
    echo "<p>No messages yet.</p>";
}

$conn->close();
?>