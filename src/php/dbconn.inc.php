<?php
$DB_HOST = "db";
$DB_NAME = "web_dev_db";
$DB_USER = "root";
$DB_PASS = "password";

$conn = mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if (!$conn) {
    // Something went wrong...
    echo "Error: Unable to connect to database.<br>";
    echo "Debugging errno: " . mysqli_connect_errno() . "<br>";
    echo "Debugging error: " . mysqli_connect_error() . "<br>";
    exit;
}

// Set timezone to Adelaide
mysqli_query($conn, "SET time_zone = '+09:30'");