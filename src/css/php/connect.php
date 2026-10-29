<?php
$host = "db";
$dbname = "web_dev_db";
$user = "root";
$pass = "password";

$conn = mysqli_connect($host, $user, $pass, $dbname);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>