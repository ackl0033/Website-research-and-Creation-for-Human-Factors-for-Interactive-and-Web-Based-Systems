<?php
session_start();

// Check if user is suspended
if (isset($_SESSION['user_id'])) {
    require_once "php/connect.php";
    require_once "php/checkSuspension.php";
    checkUserSuspension($conn, $_SESSION['user_id']);
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>FUSS | Create Request</title>
  <link rel="stylesheet" href="css/styles.css">
  <script src="js/script.js" defer></script>
  <style>
  .filters {
      display: flex;
      flex-direction: row;
      display: flex;
      align-items: center;
      flex-wrap: wrap;
      gap: 5px;
      margin-bottom: 0px;
    }

    select.filter {
      padding: 8px; 
      border-radius: 6px;
      border: 1px solid #ccc;
      font-size: 15px;
      width: 200px;
      margin-right: 10px;
      margin-bottom: 10px;
    }

     /* badge that displays stored/selected day next to the dropdown */
     .day-display-container { display: grid; grid-template-columns: repeat(3, max-content); gap:6px; align-items:start; margin-left:8px; }

     /* individual badge */
     .day-display { display:inline-flex; align-items:center; gap:8px; padding:4px 8px; background:#eef2ff; color:#1e293b; border-radius:999px; font-size:13px; user-select:none; border:1px solid rgba(30,41,59,0.06); }
     .day-display:hover { background:#e0e7ff; }
     /* small 'x' button on badge */
     .day-close { background:transparent; border:0; color:#334155; font-weight:600; cursor:pointer; font-size:12px; padding:0 6px; line-height:1; border-radius:6px; }
     .day-close:focus { outline:2px solid rgba(99,102,241,0.25); }
     .day-close:hover { color:#0f172a; }

    .group {
      width: fit-content;
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 5px;
    }
    .time-group {
      padding-top: 18px;
      display: flex;
      align-items: center;
      gap: 10px;
      width: fit-content;
      margin-bottom: 10px;
    }
    .filter.time-input {
      width: 120px;
      min-width: 0;
      max-width: 200px;
      padding: 6px 8px;
      font-size: 15px;
      text-align: center;
    }

   /* direct-message mode: light-blue form background (scoped to this form only) */
   #requestForm.direct-form { background: #eff8ff; padding: 14px; border-radius: 8px; }
  </style>
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
    <a href="home.php" class="nav-link active"><span><?php
      require_once 'php/connect.php';
      $isAdmin = false;
      if (isset($_SESSION['username'])) {
        $stmt = mysqli_prepare($conn, "SELECT isAdmin FROM users WHERE username = ?");
        mysqli_stmt_bind_param($stmt, "s", $_SESSION['username']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_array($result);
        $isAdmin = $row ? (bool)$row['isAdmin'] : false;
      }
      echo ($isAdmin ? 'Admin Dashboard' : 'Home');
    ?></span></a>
    <a href="search.php" class="nav-link"><span>Search</span></a>
  <a href="request.php" class="nav-link"><span>Request</span></a>
    <a href="messages.php" class="nav-link"><span>Messages</span></a>
    <a href="profile.php" class="nav-link"><span>Profile</span></a>
    <a href="settings.php" class="nav-link"><span>Settings</span></a>
  </nav>



  <!-- Main Page Content -->
  <main role="main">
    <h1>Create a Service Request</h1>
    <p>Post a task or request a skill from other students. Earn or spend tokens for each completed service.</p>

  <form id="requestForm" action="php/postRequest.php" method="POST" novalidate>
      <fieldset>
        <legend>
          <!-- Direct user display (shown only in direct-message mode) -->
        <div id="directUserDisplay" style="display:none; margin-bottom: 10px;">
          <strong>Direct Message To:</strong>
          <span id="directUserName"></span>
        </div>
        Request Details
        </legend>
        <label for="title">Request Title:</label>
        <input type="text" id="title" name="title" placeholder="e.g., Help with Python assignment" required>
        <label for="Tags">Please select relevant tags:</label>

       <div class="filters">
          <div class="group"> 
            <label for="categoryFilter">Category:</label>
              <div style="display:flex; align-items:center; gap:8px; position:relative;">
                <select id="categoryFilter" name="categoryFilter" class="filter" aria-label="Filter by category"></select>
              </div>
          </div>
          <div class="group"> 
            <label for="topicFilter" >Topic:</label>
              <div style="display:flex; align-items:center; gap:8px; position:relative;">
                <select id="topicFilter" name="topicFilter" class="filter" aria-label="Filter by topic"></select>
              </div>
          </div>
          <div class="group">
            <label for="academicFilter" >Academic Year:</label>
              <div style="display:flex; align-items:center; gap:8px; position:relative;">
                <select id="academicFilter" name="academicFilter" class="filter" aria-label="Filter by academic"></select>
              </div>
          </div>
       </div>
       <label for="availability">Preferred Availability:</label>
       <div class="group">
 
         <div style="display:flex; align-items:center; gap:8px; position:relative;">
           <label for="dayFilter">Day:</label>
           <select id="dayFilter" class="filter" aria-label="Filter by day"></select>
           <div id="daySelectionContainer" class="day-display-container" aria-live="polite" aria-label="Selected days"></div>
         </div>
         
          </div>
          <div class="time-group">
            <label for="startTimeFilter">Earliest Time:</label>
            <input type="text" id="startTimeFilter" name="startTimeFilter" class="filter time-input" aria-label="Start time" placeholder="e.g. 22:00 or 10pm">
            <label for="endTimeFilter" style="margin-left:10px;">Latest Time:</label>
            <input type="text" id="endTimeFilter" name="endTimeFilter" class="filter time-input" aria-label="End time" placeholder="e.g. 23:30 or 11:30pm">
        </div>
      </div>

        <label for="description">Description:</label>
        <textarea id="description" name="description" rows="6" placeholder="Describe the task or skill needed..." required></textarea>

        <label for="tokens">Tokens Offered:</label>
        <input type="number" id="tokens" name="tokens" min="1" max="10" required placeholder="e.g., 2">

        <label for="estimatedHours">Estimated Time (hours):</label>
        <input type="number" id="estimatedHours" name="estimatedHours" min="0.5" max="100" step="0.5" placeholder="e.g., 2.5">
        <small style="display: block; margin-top: 5px; color: #666;">Optional: How many hours do you estimate this task will take?</small>

        <div class="form-buttons">
          
          <input type="hidden" name="availabilityDays" id="availabilityDaysInput">
          <input type="hidden" name="isDirect" id="isDirectInput" value="0">
          <input type="hidden" name="directUsername" id="directUsernameInput" value="">
          <button id="submitBtn" type="submit" class="primary-btn">Submit Request</button>
          <button type="reset" class="secondary-btn">Clear Form</button>
        </div>
      </fieldset>
    </form>

    <p id="formStatus" aria-live="polite"></p>
  </main>

  <!-- Footer -->
  <footer role="contentinfo">
    <p>&copy; 2025 Flinders Uni Skill Share (FUSS)</p>
  </footer>

  <!-- Inline JS for page-specific form validation -->
  <script>
    // Filter option data stored here so admin changes persist in-memory
    const filterOptions = {
  categoryFilter: ['Academic', 'Athletic', 'Practical', 'Creative', 'Other'],
  topicFilter: ['ENGR2792', 'COMP3752', 'COMP9035', 'COMP2030'],
  academicFilter: ['1st', '2nd', '3rd', '4th', '5th'],
  dayFilter: ['Any', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']
    };

    // User and skill mock data
    const offeredSkills = ["COMP2030 Tutoring","HTML/CSS Help","Essay Proofreading","Data Visualization Assistance","Language Exchange: Japanese-English"];
    const requestedSkills = ["Math Tutoring","PT","Moving Help","Guitar Lessons","Resume Proofreading"];
  // Direct-message mode: parse from query params: ?isdirect=1&username=<user>
  const qp = new URLSearchParams(window.location.search);
  const isDirect = qp.get('isdirect') === '1' || qp.get('isdirect') === 'true';
  const directUserName = qp.get('username') || "";

    function renderFilterOptions() {
      Object.keys(filterOptions).forEach(selectId => {
        const sel = document.getElementById(selectId);
        if (!sel) return;
        // remember previous selection
        const prev = sel.value;
        sel.innerHTML = '';
        filterOptions[selectId].forEach(v => {
          const opt = document.createElement('option');
          opt.value = v;
          opt.textContent = v;
          sel.appendChild(opt);
        });
  // restore selection if still present, otherwise reset to first value
  if (filterOptions[selectId].includes(prev)) sel.value = prev;
  else sel.value = filterOptions[selectId][0];
      });
    }
    renderFilterOptions();

    // allow accumulating multiple selected days and show them as badges (click badge or 'x' to remove)
    const dayFilterEl = document.getElementById("dayFilter");
    const dayContainer = document.getElementById("daySelectionContainer");
    // in-memory list of selected days (no localStorage)
    const selectedDays = [];
    function renderSelectedDays() {
      if (!dayContainer) return;
      dayContainer.innerHTML = "";
      if (selectedDays.length === 0) {
        const any = document.createElement("span");
        any.className = "day-display";
        any.textContent = "Any";
        any.title = "No specific days selected";
        dayContainer.appendChild(any);
        return;
      }
      // Sort selectedDays by week order and remove duplicates
      const weekOrder = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
      const uniqueDays = Array.from(new Set(selectedDays));
      uniqueDays.sort((a, b) => weekOrder.indexOf(a) - weekOrder.indexOf(b));
      uniqueDays.forEach(d => {
        const b = document.createElement("span");
        b.className = "day-display";
        b.title = "Click to remove";

        // label
        const label = document.createElement("span");
        label.textContent = d;
        label.className = "day-label";
        label.addEventListener("click", () => {
          const idx = selectedDays.indexOf(d);
          if (idx > -1) selectedDays.splice(idx, 1);
          renderSelectedDays();
        });

        // small 'x' button
        const close = document.createElement("button");
        close.type = "button";
        close.className = "day-close";
        close.setAttribute("aria-label", `Remove ${d}`);
        close.textContent = "x";
        close.addEventListener("click", (ev) => {
          ev.stopPropagation();
          const idx = selectedDays.indexOf(d);
          if (idx > -1) selectedDays.splice(idx, 1);
          renderSelectedDays();
        });

        b.appendChild(label);
        b.appendChild(close);
        dayContainer.appendChild(b);
      });
    }
    if (dayFilterEl) {
      dayFilterEl.addEventListener("change", () => {
        const v = dayFilterEl.value;
        if (!v || v === "Any" || v === "None") {
          // ignore non-selectable placeholder options
          dayFilterEl.value = dayFilterEl.options[0]?.value || "";
          return;
        }
        if (!selectedDays.includes(v)) {
          selectedDays.push(v);
          renderSelectedDays();
        }
        // reset select so user can pick another day easily
        dayFilterEl.value = dayFilterEl.options[0]?.value || "";
      });
    }
    // initial render
    renderSelectedDays();


    document.getElementById("requestForm").addEventListener("submit", function (e) {
      e.preventDefault(); // Always prevent default form submission
      document.getElementById("availabilityDaysInput").value = JSON.stringify(selectedDays);
      const title = document.getElementById("title").value.trim();
      const description = document.getElementById("description").value.trim();
      const tokens = document.getElementById("tokens").value;
      const category = document.getElementById("categoryFilter").value;
      const topicFilter = document.getElementById("topicFilter").value;
      const academicFilter = document.getElementById("academicFilter").value;
      const startTime = document.getElementById("startTimeFilter").value;
      const endTime = document.getElementById("endTimeFilter").value;
      console.log('DEBUG startTime:', startTime, 'endTime:', endTime);
      const TimeRanges = startTime && endTime;

      if (!title || !description || !tokens || !category || !topicFilter || !academicFilter || !TimeRanges) {
        document.getElementById("formStatus").textContent = "⚠️ Please fill out all fields before submitting.";
        document.getElementById("formStatus").style.color = "red";
        return;
      } else if (tokens < 1) {
        document.getElementById("formStatus").textContent = "⚠️ Tokens must be at least 1.";
        document.getElementById("formStatus").style.color = "red";
        return;
      }

      // Prepare form data for AJAX
      const formData = new FormData(document.getElementById("requestForm"));
      fetch("php/postRequest.php", {
        method: "POST",
        body: formData
      })
      .then(response => response.text())
      .then(data => {
        document.getElementById("formStatus").textContent = data;
        document.getElementById("formStatus").style.color = data.includes("✅") ? "green" : "red";
        if (data.includes("✅")) {
          document.getElementById("requestForm").reset();
          selectedDays.length = 0;
          renderSelectedDays();
        }
      })
      .catch(error => {
        document.getElementById("formStatus").textContent = "❌ Error submitting request.";
        document.getElementById("formStatus").style.color = "red";
      });
    });

    // show direct-user display if isDirect
    (function(){
      const display = document.getElementById("directUserDisplay");
      const nameEl = document.getElementById("directUserName");
      const formEl = document.getElementById("requestForm");
      if (!display || !nameEl) return;
      if (isDirect) {
        nameEl.textContent = directUserName || "";
        display.style.display = "block";
        if (formEl) formEl.classList.add("direct-form");
        document.getElementById("submitBtn").textContent = "Send Direct Request";
        // populate hidden inputs for backend if needed
        const directInput = document.getElementById('directUsernameInput');
        const isDirectInput = document.getElementById('isDirectInput');
        if (directInput) directInput.value = directUserName || "";
        if (isDirectInput) isDirectInput.value = '1';
      } else {
        display.style.display = "none";
        document.getElementById("submitBtn").textContent = "Send Request";
        if (formEl) formEl.classList.remove("direct-form");
        const isDirectInput = document.getElementById('isDirectInput');
        if (isDirectInput) isDirectInput.value = '0';
      }
    })();
  </script>
</body>
</html>
