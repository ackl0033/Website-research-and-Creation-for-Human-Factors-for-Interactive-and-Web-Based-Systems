<?php
session_start();

// Temporary: surface errors to help diagnose blank page issues
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

function showBio($profileInfo, $profileUser) {
  require "php/connect.php";
  $checkQuery = "SELECT $profileInfo FROM users WHERE username = ?";
  $stmt = mysqli_prepare($conn, $checkQuery);
  mysqli_stmt_bind_param($stmt, "s", $profileUser);
  mysqli_stmt_execute($stmt);
  $value = null;
  mysqli_stmt_bind_result($stmt, $value);
  mysqli_stmt_fetch($stmt);
  mysqli_stmt_close($stmt);
  return $value;
}

function getProfileImagePath($profileUser) {
  require "php/connect.php";
  $checkQuery = "SELECT profile_image FROM users WHERE username = ?";
  $stmt = mysqli_prepare($conn, $checkQuery);
  mysqli_stmt_bind_param($stmt, "s", $profileUser);
  mysqli_stmt_execute($stmt);
  mysqli_stmt_bind_result($stmt, $profile_image);
  mysqli_stmt_fetch($stmt);
  mysqli_stmt_close($stmt);
  return !empty($profile_image) ? $profile_image : 'Images/default-avatar.svg';
}

function getCurrentUserAdminStatus() {
  if (!isset($_SESSION['username'])) return false;
  require "php/connect.php";
  $checkQuery = "SELECT isAdmin FROM users WHERE username = ?";
  $stmt = mysqli_prepare($conn, $checkQuery);
  mysqli_stmt_bind_param($stmt, "s", $_SESSION['username']);
  mysqli_stmt_execute($stmt);
  mysqli_stmt_bind_result($stmt, $isAdmin);
  mysqli_stmt_fetch($stmt);
  mysqli_stmt_close($stmt);
  return (bool)$isAdmin;
}

function getUserIdByUsername($username) {
  if (!$username) return 0;
  require "php/connect.php";
  $q = "SELECT id FROM users WHERE username = ?";
  $stmt = mysqli_prepare($conn, $q);
  if (!$stmt) { return 0; }
  mysqli_stmt_bind_param($stmt, 's', $username);
  mysqli_stmt_execute($stmt);
  mysqli_stmt_bind_result($stmt, $uid);
  if (mysqli_stmt_fetch($stmt)) {
    mysqli_stmt_close($stmt);
    return (int)$uid;
  }
  mysqli_stmt_close($stmt);
  return 0;
}

function userHasPublicRequests($username) {
  if (!$username) return false;
  require "php/connect.php";
  $sql = "SELECT COUNT(*) FROM requests r JOIN users u ON r.user_id = u.id WHERE u.username = ? AND r.is_direct = 0";
  $stmt = mysqli_prepare($conn, $sql);
  if (!$stmt) return false;
  mysqli_stmt_bind_param($stmt, 's', $username);
  mysqli_stmt_execute($stmt);
  mysqli_stmt_bind_result($stmt, $cnt);
  mysqli_stmt_fetch($stmt);
  mysqli_stmt_close($stmt);
  return (int)$cnt > 0;
}

function getViewedUserAdminStatus($username) {
  require "php/connect.php";
  $checkQuery = "SELECT isAdmin FROM users WHERE username = ?";
  $stmt = mysqli_prepare($conn, $checkQuery);
  mysqli_stmt_bind_param($stmt, "s", $username);
  mysqli_stmt_execute($stmt);
  $result = mysqli_stmt_get_result($stmt);
  $row = mysqli_fetch_array($result);
  return $row ? (bool)$row['isAdmin'] : false;
}

$profileUser = isset($_GET['user']) ? $_GET['user'] : (isset($_SESSION['username']) ? $_SESSION['username'] : null);
if (!$profileUser) {
  echo "No user specified.";
  exit();
}

$profileImage = getProfileImagePath($profileUser);
$currentUserIsAdmin = getCurrentUserAdminStatus();
$viewedUserId = getUserIdByUsername($profileUser);
$hasPublicRequests = userHasPublicRequests($profileUser);
$viewedUserIsAdmin = getViewedUserAdminStatus($profileUser);
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>FUSS | Profile</title>
  <link rel="stylesheet" href="css/styles.css">
  <script>
    window.FUSS = window.FUSS || {};
    window.FUSS.isOwner = <?= json_encode(isset($_SESSION['username']) && $_SESSION['username'] === $profileUser) ?>;
    window.FUSS.isAdmin = <?= json_encode($currentUserIsAdmin) ?>;
    window.FUSS.viewedUserIsAdmin = <?= json_encode($viewedUserIsAdmin) ?>;
    window.FUSS.viewedUsername = <?= json_encode($profileUser) ?>;
    window.FUSS.viewedUserId = <?= json_encode((int)$viewedUserId) ?>;
  </script>
  <script src="js/script.js" defer></script>
  <script>
    // Load reviews when page loads
    document.addEventListener('DOMContentLoaded', function() {
      loadUserReviews();
    });

    async function loadUserReviews() {
      const username = <?= json_encode($profileUser) ?>;
      
      try {
        const response = await fetch(`php/getReviews.php?username=${encodeURIComponent(username)}`);
        const data = await response.json();
        
        const reviewsList = document.getElementById('reviewsList');
        
        if (data.error) {
          reviewsList.innerHTML = '<p class="no-reviews">Error loading reviews: ' + data.error + '</p>';
          return;
        }
        
        if (data.length === 0) {
          reviewsList.innerHTML = '<p class="no-reviews">No reviews yet.</p>';
          return;
        }
        
        reviewsList.innerHTML = '';
        data.forEach(review => {
          const reviewCard = document.createElement('div');
          reviewCard.className = 'review-card';
          
          const stars = '⭐'.repeat(review.rating);
          const reviewText = review.review_text ? `"${review.review_text}"` : '<em>No written review</em>';
          
          reviewCard.innerHTML = `
            <p class="review-text">${reviewText}</p>
            <div style="display: flex; justify-content: space-between; align-items: center;">
              <p class="review-author">${stars} ${review.rating}.0 — <strong>${review.reviewer_name}</strong></p>
              <small class="review-date">${new Date(review.created_at).toLocaleDateString()}</small>
            </div>
          `;
          
          reviewsList.appendChild(reviewCard);
        });
      } catch (error) {
        console.error('Error loading reviews:', error);
        document.getElementById('reviewsList').innerHTML = '<p class="no-reviews">Error loading reviews.</p>';
      }
    }
  </script>
</head>

<body>
  <!-- ===== HEADER ===== -->
  <header class="top-banner" role="banner" aria-label="Site header">
    <div class="container">
      <div class="banner-left">
        <div class="logo">FUSS</div>
        <div class="message">Flinders University Skill Share Program</div>
      </div>

      <div class="banner-actions">
        <div class="dropdown" aria-label="Language selector">
          <button id="dropdownButton">Languages ▼</button>
          <div class="dropdown-content" id="dropdownContent">
            <a href="#" class="dropdownLanguage">English</a>
            <a href="#" class="dropdownLanguage">中文</a>
            <a href="#" class="dropdownLanguage">日本語</a>
          </div>
        </div>

        <!-- PHP session placeholder -->
        <?php if (isset($_SESSION['username'])): ?>
          <span class="welcome-text">Welcome, <?= htmlspecialchars($_SESSION['username']) ?></span>
        <?php endif; ?>

        <button class="logOutBtn" onclick="window.location.href='logout.php'">Log Out</button>
        <button id="menuToggle" aria-label="Toggle sidebar">☰</button>
      </div>
    </div>
  </header>

  <!-- ===== SIDEBAR ===== -->
  <nav class="sidebar" role="navigation" aria-label="Main navigation">
  <a href="home.php" class="nav-link"><span><?= getCurrentUserAdminStatus() ? 'Admin Dashboard' : 'Home' ?></span></a>
    <a href="search.php" class="nav-link"><span>Search</span></a>
  <a href="request.php" class="nav-link"><span>Request</span></a>
    <a href="messages.php" class="nav-link"><span>Messages</span></a>
    <a href="profile.php" class="nav-link active"><span>Profile</span></a>
    <a href="settings.php" class="nav-link"><span>Settings</span></a>
  </nav>

  <!-- ===== MAIN CONTENT ===== -->
  <main role="main">
  <!-- ===== PROFILE HEADER ===== -->
  <section class="profile-header">
    <div class="profile-avatar">
      <img id="profilePic" src="<?= htmlspecialchars($profileImage) ?>" alt="Profile picture">
      <form id="profilePicForm" method="POST" action="/php/upload_profile_image.php" enctype="multipart/form-data" style="display:none;">
        <input type="file" name="profile_image" id="profilePicInput" accept="image/*">
      </form>
      <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px;">
        <div>
          <button id="changePicBtn" class="secondary-btn">Change Picture</button>
        </div>
        <div>
          <button id="messageBtn" class="primary-btn" style="display: none;">💬 Message</button>
        </div>
      </div>
    </div>

    <div class="profile-info">

      <p><strong>Name:</strong> <span id="profileName"><?= showBio('name', $profileUser) ?></span></p>
      <p><strong>Degree:</strong> <span id="profileDegree"><?= showBio('degree', $profileUser) ?></span></p>
      <p><strong>College:</strong> <span id="profileCollege"><?= showBio('college', $profileUser) ?></span></p>
      <p><strong>Year:</strong> <span id="profileYear"><?= showBio('year', $profileUser) ?></span></p>
      <p><strong>Bio:</strong> <span id="profileBio"><?= showBio('bio', $profileUser) ?></span></p>

      <div class="credit-balance">
        <span class="credits">FUSS Credits: <strong id="profileCredits"><?= showBio('token', $profileUser) ?></strong></span>
      </div>

      <div class="form-buttons" style="margin-top: 10px;">
        <button id="editProfileBtn" class="secondary-btn small-btn">✏️ Edit Profile</button>
        <button id="saveProfileBtn" class="primary-btn small-btn" style="display: none;">✅ Save Changes</button>
      </div>
    </div>
  </section>

  <!-- ===== SKILLS OFFERED ===== -->
  <section class="skills-section">
  <?php $decoded_json = json_decode(showBio('skills_offered', $profileUser), true); ?>
    <h2>Skills Offered</h2>
    <ul class="skill-list" id="skillsOffered">
      <?php 
      // Ensure we always show 3 skill slots
      $skills = is_array($decoded_json) ? $decoded_json : [];
      $filledSkills = array_values(array_filter($skills, function($skill) { return !empty(trim($skill)); }));
      
      for ($i = 0; $i < 3; $i++): 
        if (isset($filledSkills[$i]) && !empty(trim($filledSkills[$i]))): ?>
          <li><?= htmlspecialchars($filledSkills[$i]) ?></li>
        <?php else: ?>
          <li style="color: #6b7280; font-style: italic;">- Empty Skill Slot -</li>
        <?php endif;
      endfor; ?>
    </ul>
    <button id="editSkillOfferedBtn" class="secondary-btn small-btn">✏️ Change Skills</button>
    <button id="confirmSkillOfferedBtn" class="primary-btn small-btn" style="display: none;">✅ Confirm Changes</button>
  </section>

  <!-- ===== SKILLS REQUESTED ===== -->
  <?php if ($hasPublicRequests): ?>
    <section class="skills-section" id="skillsRequestedSection">
    <?php $decoded_json = json_decode(showBio('skills_requested', $profileUser), true); ?>
      <h2>Skills Requested</h2>
      <p style="font-style: italic; color: #6b7280; margin-bottom: 16px;">
        Skills requested are automatically updated when you create requests. They help others find you in search.
      </p>
      <ul class="skill-list" id="skillsRequested">
        <?php if (empty($decoded_json) || !is_array($decoded_json)): ?>
          <li class="empty-requested" style="color: #6b7280; font-style: italic;">No skills requested yet. Create a request to add skills here.</li>
        <?php else: ?>
          <?php foreach ($decoded_json as $skill): ?>
            <?php if (!empty(trim($skill))): ?>
              <li data-skill="<?= htmlspecialchars(strtolower(trim($skill))) ?>"><?= htmlspecialchars($skill) ?></li>
            <?php endif; ?>
          <?php endforeach; ?>
        <?php endif; ?>
      </ul>
      <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <div>
          <button id="createRequestBtn" class="primary-btn small-btn" onclick="window.location.href='request.php'">📝 Create Request</button>
        </div>
        <button id="directRequestBtn" class="primary-btn small-btn" style="display: none;">📩 Direct Request</button>
      </div>
    </section>
  <?php endif; ?>

  <!-- ===== REVIEWS ===== -->
  <section class="reviews-section">
    <h2>Peer Reviews</h2>
    <div id="reviewsList">
      <!-- Reviews will be loaded here -->
    </div>
  </section>
</main>

<!-- Delete User Button (Admin Only) -->
<div style="display: flex; justify-content: flex-end; padding: 10px; z-index: 1000;">
  <button id="deleteUserBtn" class="secondary-btn" style="display: none; background-color: #dc2626; color: white; border-color: #dc2626;">
    🗑️ Delete User
  </button>
</div>

  <!-- ===== FOOTER ===== -->
  <footer role="contentinfo">
    <p>&copy; 2025 Flinders Uni Skill Share (FUSS)</p>
  </footer>

</body>
<script>
document.addEventListener('DOMContentLoaded', function() {
  const deleteBtn = document.getElementById('deleteUserBtn');
  if (deleteBtn && window.FUSS.isAdmin && !window.FUSS.isOwner) {
    deleteBtn.style.display = '';
    deleteBtn.addEventListener('click', function() {
      const username = window.FUSS.viewedUsername;
      const confirmation = prompt(`Type "${username}" to confirm deletion:\n\nWARNING: This will permanently delete all user data.`);
      if (confirmation === username) {
        fetch('php/deleteUser.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: `username=${encodeURIComponent(username)}`
        })
        .then(response => response.json())
        .then(result => {
          if (result.success) {
            alert(`${username} has been deleted.`);
            window.location.href = 'home.php';
          } else {
            alert('Failed to delete user: ' + (result.error || 'Unknown error'));
          }
        });
      } else if (confirmation !== null) {
        alert('Deletion cancelled - name did not match');
      }
    });
  }
});
</script>
</html>
