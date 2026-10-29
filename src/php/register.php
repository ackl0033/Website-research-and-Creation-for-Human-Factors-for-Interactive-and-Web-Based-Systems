<?php
require_once "connect.php";
session_start();

// Check if form data was sent using POST
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    
    $username = trim($_POST["username"]);
    $email = filter_var(trim($_POST["email"]), FILTER_SANITIZE_EMAIL);
    $password = filter_var($_POST["password"], FILTER_SANITIZE_SPECIAL_CHARS);
    $confirmPassword = filter_var($_POST["confirmPassword"], FILTER_SANITIZE_SPECIAL_CHARS);
    $emptySkillsArray = []; // Start with empty array instead of 3 empty strings
    $jsonData = json_encode($emptySkillsArray);

   
    if (empty($username) || empty($email) || empty($password) || empty($confirmPassword)) {
        header("Location: ../register.html?error=Please fill out all fields.");
        exit();
    }

    if ($password !== $confirmPassword) {
        header("Location: ../register.html?error=Passwords do not match.");
        exit();
    }
 
    if (strlen($password) < 8) {
        header("Location: ../register.html?error=Password must be at least 8 characters long.");
        exit();
    }

    // Check if email or username already exists
    $checkQuery = "SELECT * FROM users WHERE username=? OR email=?";
    $stmt = mysqli_prepare($conn, $checkQuery);
    mysqli_stmt_bind_param($stmt, "ss", $username, $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) > 0) {
        header("Location: ../register.html?error=Username or email already exists.");
        exit();
    }
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);


    // Insert new user
    $insertQuery = "INSERT INTO users (username, email, password, skills_offered, skills_requested, token, name, degree, college, year, bio) VALUES (?, ?, ?, ?, ?, 2, '', '', '', '', '')";
    $stmt = mysqli_prepare($conn, $insertQuery);
    mysqli_stmt_bind_param($stmt, "sssss", $username, $email, $hashedPassword, $jsonData, $jsonData);

    if (mysqli_stmt_execute($stmt)) {
        $userId = mysqli_insert_id($conn);
        
        // Set session variables properly
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
        
        header("Location: ../home.php?success=1");
        exit();
    } else {
        header("Location: ../register.html?error=Failed to register user.");
        exit();
    }

    mysqli_stmt_close($stmt);
}

mysqli_close($conn);
?>
