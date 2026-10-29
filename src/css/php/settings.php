<?php
session_start();

// Check if user is suspended
if (isset($_SESSION['user_id'])) {
    require_once "php/connect.php";
    require_once "php/checkSuspension.php";
    checkUserSuspension($conn, $_SESSION['user_id']);
}

// Get profile username from query param if set, else use session
$profileUsername = isset($_GET['user']) ? $_GET['user'] : $_SESSION['username'];

function showBio($profileInfo) {
  require "php/connect.php";
  global $profileUsername;
  $checkQuery = "SELECT $profileInfo FROM users WHERE username = ?";
  $stmt = mysqli_prepare($conn, $checkQuery);
  mysqli_stmt_bind_param($stmt, "s", $profileUsername);
  mysqli_stmt_execute($stmt);
  $result = mysqli_stmt_get_result($stmt);
  $row = mysqli_fetch_array($result);
  return $row[$profileInfo];
}

function getCurrentUserAdminStatus() {
  if (!isset($_SESSION['username'])) return false;
  require "php/connect.php";
  $checkQuery = "SELECT isAdmin FROM users WHERE username = ?";
  $stmt = mysqli_prepare($conn, $checkQuery);
  mysqli_stmt_bind_param($stmt, "s", $_SESSION['username']);
  mysqli_stmt_execute($stmt);
  $result = mysqli_stmt_get_result($stmt);
  $row = mysqli_fetch_array($result);
  return $row ? (bool)$row['isAdmin'] : false;
}

$currentUserIsAdmin = getCurrentUserAdminStatus();

?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>FUSS | Settings</title>
  <link rel="stylesheet" href="css/styles.css">
  <script>
    window.FUSS = window.FUSS || {};
    window.FUSS.isAdmin = <?= json_encode($currentUserIsAdmin) ?>;
  </script>
  <script src="js/script.js" defer></script>
</head>

<body>
  <!-- Header -->
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

  <!-- Sidebar Navigation -->
  <nav class="sidebar" role="navigation" aria-label="Main navigation">
  <a href="home.php" class="nav-link"><span><?= $currentUserIsAdmin ? 'Admin Dashboard' : 'Home' ?></span></a>
    <a href="search.php" class="nav-link"><span>Search</span></a>
  <a href="request.php" class="nav-link"><span>Request</span></a>
    <a href="messages.php" class="nav-link"><span>Messages</span></a>
    <a href="profile.php" class="nav-link"><span>Profile</span></a>
    <a href="settings.php" class="nav-link active"><span>Settings</span></a>
  </nav>

  <!-- Main Settings Page -->
  <main role="main">
    <h1>Account Settings</h1>

    <section class="settings-section" aria-label="Account Details">
      <h2>Personal Information</h2>
      <form id="personalInfoForm">
        <fieldset>
          <label for="username">Username</label>
          <input type="text" id="username" value="<?= showBio('username') ?>" disabled>

          <label for="email">Email</label>
          <input type="email" id="email" name="email" value="<?= showBio('email') ?>" disabled>

          <label for="profileName">Full Name</label>
          <input type="text" id="profileName" name="profileName" value="<?= showBio('name') ?>" required>

          <div class="form-buttons">
            <button type="submit" class="primary-btn">Save Changes</button>
            <button type="reset" class="secondary-btn">Cancel</button>
          </div>
        </fieldset>
      </form>
    </section>

    <section class="settings-section" aria-label="Password Update">
      <h2>Change Password</h2>
      <form id="passwordForm">
        <fieldset>
          <label for="currentPassword">Current Password</label>
          <input type="password" id="currentPassword" name="currentPassword" required>

          <label for="newPassword">New Password</label>
          <input type="password" id="newPassword" name="newPassword" required>

          <label for="confirmPassword">Confirm New Password</label>
          <input type="password" id="confirmPassword" name="confirmPassword" required>

          <div class="form-buttons">
            <button type="submit" class="primary-btn">Update Password</button>
          </div>
        </fieldset>
      </form>
    </section>

    <section class="settings-section" aria-label="Preferences">
      <h2>Preferences</h2>
      <form id="preferencesForm">
        <fieldset>
          <label for="notifications">Email Notifications</label>
          <select id="notifications" name="notifications">
            <option value="all" <?= showBio("notification_pref") === "all" ? "selected" : "" ?>>All notifications</option>
            <option value="important" <?= showBio("notification_pref") === "important" ? "selected" : "" ?>>Only important updates</option>
            <option value="none" <?= showBio("notification_pref") === "none" ? "selected" : "" ?>>No email notifications</option>
          </select>

          <label for="languagePref">Preferred Language</label>
          <select id="languagePref" name="languagePref">
            <option value="en" <?= showBio("language_pref") === "en" ? "selected" : "" ?>>English</option>
            <option value="zh" <?= showBio("language_pref") === "zh" ? "selected" : "" ?>>中文</option>
            <option value="ja" <?= showBio("language_pref") === "ja" ? "selected" : "" ?>>日本語</option>
          </select>

          <div class="form-buttons">
            <button type="submit" class="primary-btn">Save Preferences</button>
          </div>
        </fieldset>
      </form>
    </section>
  </main>

  <footer role="contentinfo">
    <p>&copy; 2025 Flinders Uni Skill Share (FUSS)</p>
  </footer>
</body>
</html>
