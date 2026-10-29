<?php
session_start();
require_once 'connect.php';

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    http_response_code(403);
    echo "You must be logged in to post a request.";
    exit;
}

// Extract and sanitize POST data
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$tokens = intval($_POST['tokens'] ?? 0);
$estimatedHours = !empty($_POST['estimatedHours']) ? floatval($_POST['estimatedHours']) : null;
$category = $_POST['categoryFilter'] ?? '';
$topic = $_POST['topicFilter'] ?? '';
$academicYear = $_POST['academicFilter'] ?? '';
$startTime = $_POST['startTimeFilter'] ?? '';
$endTime = $_POST['endTimeFilter'] ?? '';
$availabilityDays = $_POST['availabilityDays'] ?? '[]'; // JSON string
// Direct mode: accept either explicit flag value or presence, and support username->id mapping
$isDirect = 0;
if (isset($_POST['isDirect'])) {
    $isDirect = (string)$_POST['isDirect'] === '1' || (string)$_POST['isDirect'] === 'true' ? 1 : 0;
}

// Resolve direct target user id
$directToUser = null; // numeric user id or null
if (!empty($_POST['directToUser']) && ctype_digit((string)$_POST['directToUser'])) {
    $directToUser = (int)$_POST['directToUser'];
} elseif (!empty($_POST['directUsername'])) {
    $u = trim($_POST['directUsername']);
    if ($u !== '') {
        $lookup = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ?");
        if ($lookup) {
            mysqli_stmt_bind_param($lookup, 's', $u);
            mysqli_stmt_execute($lookup);
            mysqli_stmt_bind_result($lookup, $uid);
            if (mysqli_stmt_fetch($lookup)) {
                $directToUser = (int)$uid;
            }
            mysqli_stmt_close($lookup);
        }
    }
}

if (!$title || !$description || $tokens < 1) {
    echo "Missing required fields.";
    exit;
}

// Atomic token check and request insert
$user_id = $_SESSION['user_id'];

// Start transaction
mysqli_begin_transaction($conn);

try {
    // Lock user's token row
    $tokStmt = mysqli_prepare($conn, "SELECT token FROM users WHERE id = ? FOR UPDATE");
    if (!$tokStmt) { throw new Exception('Prepare failed: ' . mysqli_error($conn)); }
    mysqli_stmt_bind_param($tokStmt, 'i', $user_id);
    mysqli_stmt_execute($tokStmt);
    mysqli_stmt_bind_result($tokStmt, $currentTokens);
    if (!mysqli_stmt_fetch($tokStmt)) {
        mysqli_stmt_close($tokStmt);
        throw new Exception('User not found.');
    }
    mysqli_stmt_close($tokStmt);

    // Convert VARCHAR token to integer for comparison
    $currentTokens = intval($currentTokens);
    
    if ($currentTokens < $tokens) {
        // Not enough tokens
        mysqli_rollback($conn);
        http_response_code(400);
        echo "❌ Not enough tokens. You have $currentTokens tokens, but this request requires $tokens.";
        exit;
    }

    // Insert request
    $query = "INSERT INTO requests (user_id, title, description, tokens, estimated_hours, category, topic, academic_year, availability_days, start_time, end_time, is_direct, direct_to_user)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) { throw new Exception('Prepare failed: ' . mysqli_error($conn)); }

    // i = int, s = string, d = double/float
    mysqli_stmt_bind_param($stmt, "issidsssssssi",
        $user_id,         // i
        $title,           // s
        $description,     // s
        $tokens,          // s
        $estimatedHours,  // i (can be null)
        $category,        // d
        $topic,           // s
        $academicYear,    // s
        $availabilityDays,// s
        $startTime,       // s
        $endTime,         // s
        $isDirect,        // s
        $directToUser     // i
    );
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new Exception('Insert failed: ' . $err);
    }
    
    // Get the inserted request ID immediately after INSERT
    $requestId = mysqli_insert_id($conn);
    
    // Debug: Log the request ID
    error_log("Direct request created with ID: " . $requestId);
    
    mysqli_stmt_close($stmt);

    // Deduct tokens and update skills_requested
    $upd = mysqli_prepare($conn, "UPDATE users SET token = token - ? WHERE id = ?");
    if (!$upd) { throw new Exception('Prepare failed: ' . mysqli_error($conn)); }
    mysqli_stmt_bind_param($upd, 'ii', $tokens, $user_id);
    if (!mysqli_stmt_execute($upd)) {
        $err = mysqli_stmt_error($upd);
        mysqli_stmt_close($upd);
        throw new Exception('Token update failed: ' . $err);
    }
    mysqli_stmt_close($upd);
    
    // Update user's skills_requested based on the request
    // Get current skills_requested
    $skillsStmt = mysqli_prepare($conn, "SELECT skills_requested FROM users WHERE id = ?");
    if ($skillsStmt) {
        mysqli_stmt_bind_param($skillsStmt, 'i', $user_id);
        mysqli_stmt_execute($skillsStmt);
        mysqli_stmt_bind_result($skillsStmt, $currentSkills);
        if (mysqli_stmt_fetch($skillsStmt)) {
            mysqli_stmt_close($skillsStmt);
            
            // Decode current skills or initialize empty array
            $skillsArray = [];
            if ($currentSkills) {
                $decoded = json_decode($currentSkills, true);
                if (is_array($decoded)) {
                    $skillsArray = array_filter($decoded, function($skill) {
                        return !empty(trim($skill));
                    });
                }
            }
            
            // Add new skill based on request title (limit to avoid too many skills)
            if (!in_array($title, $skillsArray)) {
                array_unshift($skillsArray, $title); // Add to beginning
                $skillsArray = array_slice($skillsArray, 0, 3); // Keep only 3 most recent
            }
            
            // Update skills_requested
            $newSkillsJson = json_encode($skillsArray);
            $updateSkillsStmt = mysqli_prepare($conn, "UPDATE users SET skills_requested = ? WHERE id = ?");
            if ($updateSkillsStmt) {
                mysqli_stmt_bind_param($updateSkillsStmt, 'si', $newSkillsJson, $user_id);
                mysqli_stmt_execute($updateSkillsStmt);
                mysqli_stmt_close($updateSkillsStmt);
            }
        } else {
            mysqli_stmt_close($skillsStmt);
        }
    }
    
    // If this is a direct request, send a confirmation message to the target user
    if ($isDirect && $directToUser) {
        // Get target user info
        $userStmt = mysqli_prepare($conn, "SELECT username FROM users WHERE id = ?");
        if ($userStmt) {
            mysqli_stmt_bind_param($userStmt, 'i', $directToUser);
            mysqli_stmt_execute($userStmt);
            mysqli_stmt_bind_result($userStmt, $targetUsername);
            if (mysqli_stmt_fetch($userStmt)) {
                mysqli_stmt_close($userStmt);
                
                // Create or get conversation
                $sender_id = $user_id;
                $receiver_id = $directToUser;
                $smaller_id = min($sender_id, $receiver_id);
                $larger_id = max($sender_id, $receiver_id);
                
                // Check if conversation exists
                $convStmt = mysqli_prepare($conn, "SELECT id FROM conversations WHERE (user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?)");
                if ($convStmt) {
                    mysqli_stmt_bind_param($convStmt, 'iiii', $smaller_id, $larger_id, $larger_id, $smaller_id);
                    mysqli_stmt_execute($convStmt);
                    mysqli_stmt_bind_result($convStmt, $conv_id);
                    if (!mysqli_stmt_fetch($convStmt)) {
                        mysqli_stmt_close($convStmt);
                        
                        // Create new conversation
                        $createConvStmt = mysqli_prepare($conn, "INSERT INTO conversations (user1_id, user2_id, user1_username, user2_username) VALUES (?, ?, ?, ?)");
                        if ($createConvStmt) {
                            mysqli_stmt_bind_param($createConvStmt, 'iiss', $smaller_id, $larger_id, $_SESSION['username'], $targetUsername);
                            mysqli_stmt_execute($createConvStmt);
                            $conv_id = mysqli_insert_id($conn);
                            mysqli_stmt_close($createConvStmt);
                        }
                    } else {
                        mysqli_stmt_close($convStmt);
                    }
                }
                
                // Send confirmation message
                if (isset($conv_id)) {
                    $availabilityDaysDecoded = json_decode($availabilityDays, true);
                    $daysText = is_array($availabilityDaysDecoded) ? implode(', ', $availabilityDaysDecoded) : 'Not specified';
                    $timeText = $startTime && $endTime ? "$startTime - $endTime" : 'Not specified';
                    $hoursText = $estimatedHours ? "$estimatedHours hour(s)" : 'Not specified';
                    
                    $message = "Direct request from {$_SESSION['username']}: \"$title\" - $description. Available: $daysText at $timeText for $hoursText. Cost: $tokens token(s). Please confirm or decline. <!--DREQ:$requestId-->";
                    
                    $msgStmt = mysqli_prepare($conn, "INSERT INTO messages (conversation_id, sender_id, receiver_id, message_text) VALUES (?, ?, ?, ?)");
                    if ($msgStmt) {
                        mysqli_stmt_bind_param($msgStmt, 'iiis', $conv_id, $sender_id, $receiver_id, $message);
                        mysqli_stmt_execute($msgStmt);
                        mysqli_stmt_close($msgStmt);
                    }
                }
            } else {
                mysqli_stmt_close($userStmt);
            }
        }
    }
    
    // Commit
    mysqli_commit($conn);
    
    if ($isDirect && $directToUser) {
        echo "✅ Direct request sent to user for confirmation. Tokens deducted: $tokens. Remaining: " . ($currentTokens - $tokens) . ".";
    } else {
        echo "✅ Request submitted successfully. Tokens deducted: $tokens. Remaining: " . ($currentTokens - $tokens) . ".";
    }
} catch (Exception $e) {
    mysqli_rollback($conn);
    http_response_code(500);
    echo "❌ Error: " . $e->getMessage();
}
?>