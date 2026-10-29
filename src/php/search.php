<?php
session_start();
require_once "php/connect.php";
require_once "php/checkSuspension.php";

// Check if user is suspended
if (isset($_SESSION['user_id'])) {
    checkUserSuspension($conn, $_SESSION['user_id']);
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
$currentUsername = $_SESSION['username'] ?? null;
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>FUSS | Search</title>
  <link rel="stylesheet" href="css/styles.css">
  <script>
    window.FUSS = window.FUSS || {};
    window.FUSS.isAdmin = <?= json_encode($currentUserIsAdmin) ?>;
    window.FUSS.currentUsername = <?= json_encode($currentUsername) ?>;
  </script>
  <script src="js/script.js" defer></script>
  <style>
    /*search specific styles */
    .search-container {
      margin-bottom: 24px;
      display: flex;
      flex-direction: column;
      gap: 16px;
    }
       

    .searchBar {
      display: flex;
      align-items: center;
      flex-wrap: wrap;
      gap: 8px;
    }

    .searchBar input[type="text"] {
      flex: 1;
      min-width: 250px;
      padding: 10px 12px;
      font-size: 16px;
      border: 1px solid #ccc;
      border-radius: 8px;
    }

    .searchButton {
      padding: 10px 16px;
      border-radius: 8px;
      border: none;
      font-weight: 600;
      cursor: pointer;
      background: #1e3a8a;
    
      color: white;
    }

    .searchButton:hover {
      background: #384663;
    }

    .filters {
      display: flex;
      flex-direction: row;
      
      display: flex;
      align-items: center;
      flex-wrap: wrap;
      gap: 5px;
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
      width: 80px;
      min-width: 0;
      max-width: 90px;
      padding: 6px 8px;
      font-size: 15px;
      text-align: center;
    }
  
  

    /* Results section */
    .results {
      margin-top: 20px;
      display: grid;
      max-height: 900px;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 16px;
      overflow: auto;
      padding-right: 5px;
    }

    .result-card {
      background: white;
      border-radius: 12px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.1);
      padding: 16px;
    }
    .result-user {
      background: #eff7ff;
    }
    .result-skill {
      background: #f9fffa;
    }

    .result-card h3 { margin-top: 0; }
    .result-card p { margin-bottom: 5px; }
    
    /* Make card a column flex so footer buttons can stick to bottom */
    .result-card {
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      height: 100%; /* ensure cards are same height in grid */
    }

    /* tag layout: inline, wrap and sit next to each other */
    .tagContainer {
      display: flex;
      flex-direction: row;
      flex-wrap: wrap;
      gap: 6px;
      margin: 8px 0;
      align-items: center;
    }

    .tag {
      display: inline-flex;
      align-items: center;
      padding: 6px 8px;
      margin: 2px 0;
      font-weight: 600;
      color: #070404;
      background: #c5d7f3;
      border-radius: 12px;
    }

    .buttonContainer {
      display: flex;
      flex-direction: row;
      justify-content: space-between; /* request left, view right */
      gap: 10px;
      margin-top: 20px;
      width: 100%;
      align-items: center;
    }

    /* helper to right-align a single button inside container */
    .push-right {
      margin-left: auto;
    }
    
    .request-btn {
      background: #1e3a8a;
      color: white;
      border: none;
      padding: 8px 12px;
      border-radius: 6px;
      cursor: pointer;
    }
    .request-btn:hover { background: #334155; }

    .profile-btn {
      background: #6d991c;
      color: white;
      border: none;
      padding: 8px 12px;
      border-radius: 6px;
      cursor: pointer;
    }
    .profile-btn:hover { background: #334155; }

    .removeService-btn {
      background: #740e0e;
      color: white;
      border: none;
      padding: 8px 12px;
      border-radius: 6px;
      cursor: pointer;
    }
    .removeService-btn:hover { background: #334155; }

    .no-results {
      margin-top: 20px;
      color: #6b7280;
      font-style: italic;
    }

    /* admin manage panel styles */
    .manage-controls { display: none; margin-left: -10px; margin-right: 20px; }
    .manage-btn {
      background: transparent;
      border: 1px solid #e11d48; /* red outline */
      color: #e11d48;
      padding: 2px 6px;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 700;
      line-height: 1;
    }
    .manage-btn:hover { background: rgba(225,29,72,0.06); }

    .manage-panel {
      position: absolute;
      background: white;
      border: 1px solid rgba(0,0,0,0.12);
      box-shadow: 0 4px 12px rgba(0,0,0,0.08);
      border-radius: 8px;
      padding: 8px;
      z-index: 9999;
      min-width: 160px;
    }
    .manage-panel .option-row {
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:8px;
      padding:4px 2px;
    }
    .manage-panel .del-btn {
      background: #dc2626; /* red */
      color: white;
      border: none;
      padding: 2px 6px;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 700;
    }
    .manage-panel .del-btn[disabled] { opacity: 0.5; cursor: not-allowed; }

    .manage-add-btn {
      background: #059669; /* green */
      color: white;
      border: none;
      padding: 2px 6px;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 700;
      margin-left: 2px;
    }
    .manage-add-btn:hover { background: #047857; }

    /* description box that caps height and scrolls when content is large */
    .desc-container {
      min-height: 30px;
      max-height: 180px;
      overflow: auto;
      padding-right: 6px; /* room for scrollbar */
      margin-bottom: 8px;
    }
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
            <a href="#">English</a>
            <a href="#">中文</a>
            <a href="#">日本語</a>
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
    <a href="search.php" class="nav-link active"><span>Search</span></a>
  <a href="request.php" class="nav-link"><span>Request</span></a>
    <a href="messages.php" class="nav-link"><span>Messages</span></a>
    <a href="profile.php" class="nav-link"><span>Profile</span></a>
    <a href="settings.php" class="nav-link"><span>Settings</span></a>
  </nav>

  <!-- Main Content -->
  <main role="main">
    <h1>Search for Skills and Requests</h1>
    <div class="search-container">
      <div class="searchBar">
        <input type="text" id="searchInput" placeholder="Search a task, name or skill..." aria-label="Search field">
        <button class="searchButton" id="searchButton">Search</button>
      </div>
      <div class="filters">
         <div class="group">
          <label for="typeFilter" >Type:</label>
            <div style="display:flex; align-items:center; gap:8px; position:relative;">
              <select id="typeFilter" class="filter" aria-label="Filter by type"></select>
              <div class="manage-controls" data-target="typeFilter">
                <button class="manage-btn" aria-label="Manage type options" onclick="openManage(event,'typeFilter')">-</button>
                <button class="manage-add-btn" aria-label="Add type option" onclick="addOptionPrompt('typeFilter')">+</button>
              </div>
            </div>
        </div>
        <div class="group"> 
          <label for="categoryFilter" >Category:</label>
            <div style="display:flex; align-items:center; gap:8px; position:relative;">
              <select id="categoryFilter" class="filter" aria-label="Filter by category"></select>
              <div class="manage-controls" data-target="categoryFilter">
                <button class="manage-btn" aria-label="Manage category options" onclick="openManage(event,'categoryFilter')">-</button>
                <button class="manage-add-btn" aria-label="Add category option" onclick="addOptionPrompt('categoryFilter')">+</button>
              </div>
            </div>
        </div>
        <div class="group"> 
          <label for="topicFilter" >Topic:</label>
            <div style="display:flex; align-items:center; gap:8px; position:relative;">
              <select id="topicFilter" class="filter" aria-label="Filter by topic"></select>
              <div class="manage-controls" data-target="topicFilter">
                <button class="manage-btn" aria-label="Manage topic options" onclick="openManage(event,'topicFilter')">-</button>
                <button class="manage-add-btn" aria-label="Add topic option" onclick="addOptionPrompt('topicFilter')">+</button>
              </div>
            </div>
        </div>
        <div class="group">
          <label for="academicFilter" >Academic Year:</label>
            <div style="display:flex; align-items:center; gap:8px; position:relative;">
              <select id="academicFilter" class="filter" aria-label="Filter by academic"></select>
              <div class="manage-controls" data-target="academicFilter">
                <button class="manage-btn" aria-label="Manage academic options" onclick="openManage(event,'academicFilter')">-</button>
                <button class="manage-add-btn" aria-label="Add academic option" onclick="addOptionPrompt('academicFilter')">+</button>
              </div>
            </div>
        </div>
        <div class="group">
          <label for="dayFilter" >Day:</label>
            <div style="display:flex; align-items:center; gap:8px; position:relative;">
              <select id="dayFilter" class="filter" aria-label="Filter by day"></select>
              <div class="manage-controls" data-target="dayFilter">
                <button class="manage-btn" aria-label="Manage day options" onclick="openManage(event,'dayFilter')">-</button>
                <button class="manage-add-btn" aria-label="Add day option" onclick="addOptionPrompt('dayFilter')">+</button>
              </div>
            </div>
        </div>
        <div class="time-group">
          <label for="startTimeFilter">Start Time:</label>
          <input type="text" id="startTimeFilter" class="filter time-input" aria-label="Start time" placeholder="HH:MM" pattern="^([01]\d|2[0-3]):([0-5]\d)$" maxlength="5" inputmode="numeric">
          <label for="endTimeFilter" style="margin-left:10px;">End Time:</label>
          <input type="text" id="endTimeFilter" class="filter time-input" aria-label="End time" placeholder="HH:MM" pattern="^([01]\d|2[0-3]):([0-5]\d)$" maxlength="5" inputmode="numeric">
        </div>
      </div>
    </div>

    <div id="results" class="results" aria-live="polite">
      <!-- Search results appear here -->
    </div>
    <p id="noResults" class="no-results" style="display:none;">No results found.</p>
    <!-- Accept Request Modal (should only be present once) -->
    <div id="acceptRequestModal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.4); z-index:1000; align-items:center; justify-content:center;">
      <div class="modal-content" style="background:#fff; padding:24px; border-radius:8px; max-width:400px; margin:auto; position:relative;">
        <span id="closeAcceptModal" style="position:absolute; top:8px; right:12px; cursor:pointer; font-size:22px;">&times;</span>
        <h2>Accept Request</h2>
        <form id="acceptRequestForm">
          <input type="hidden" id="acceptRequestId">
          <div style="margin-bottom:12px;">
            <label for="acceptDay">Select Day:</label>
            <select id="acceptDay" required></select>
          </div>
          <div style="margin-bottom:12px;">
            <label for="acceptTime">Select Start Time:</label>
            <select id="acceptTime" required></select>
          </div>
          <div style="margin-bottom:12px;">
            <label>Duration (hours):</label>
            <input type="text" id="acceptDuration" readonly>
          </div>
          <button type="submit" class="primary-btn">Send Request</button>
        </form>
      </div>
    </div>

    <script>
    // Accept Request Modal logic
    let currentAcceptRequest = null;
    
    // Generate 30-minute intervals between start and end time, considering duration
    function generateTimeOptions(start, end, durationHours) {
      const times = [];
      const [startH, startM] = start.split(':').map(Number);
      const [endH, endM] = end.split(':').map(Number);
      
      // Calculate the latest possible start time based on duration
      const durationMinutes = Math.ceil((durationHours || 1) * 60); // Convert hours to minutes, default 1 hour
      const endMinutes = endH * 60 + endM;
      const latestStartMinutes = endMinutes - durationMinutes;
      const latestStartH = Math.floor(latestStartMinutes / 60);
      const latestStartM = latestStartMinutes % 60;
      
      let currentH = startH;
      let currentM = startM;
      
      while (currentH < latestStartH || (currentH === latestStartH && currentM <= latestStartM)) {
        const timeStr = `${currentH.toString().padStart(2, '0')}:${currentM.toString().padStart(2, '0')}`;
        times.push(timeStr);
        
        currentM += 30;
        if (currentM >= 60) {
          currentM -= 60;
          currentH += 1;
        }
      }
      return times;
    }
    
    document.addEventListener('DOMContentLoaded', function() {
  // Track hidden requests by id
  window.hiddenRequestIds = new Set();
      window.openAcceptRequestModal = function(requestId) {
        // Find the request data from mockData
        const req = mockData.find(r => r.id == requestId);
        if (!req) return;
        currentAcceptRequest = req;
        document.getElementById('acceptRequestId').value = req.id;
        // Populate days and dates
        const daySel = document.getElementById('acceptDay');
        daySel.innerHTML = '';
        let days = [];
        let availRaw = req.availability_days_raw || req.availability_days;
        if (Array.isArray(availRaw)) days = availRaw;
        else if (typeof availRaw === 'string' && availRaw.startsWith('[')) {
          try { days = JSON.parse(availRaw); } catch { days = []; }
        } else if (typeof availRaw === 'string') {
          days = [availRaw];
        }
        days = days.filter(Boolean);
        days.forEach(day => {
          const opt = document.createElement('option');
          opt.value = day;
          opt.textContent = day;
          daySel.appendChild(opt);
        });

        // Add date picker for available days up to 1 month ahead
        let dateSel = document.getElementById('acceptDate');
        if (!dateSel) {
          dateSel = document.createElement('select');
          dateSel.id = 'acceptDate';
          dateSel.required = true;
          daySel.parentNode.insertBefore(dateSel, daySel.nextSibling);
          // Add label if not present
          if (!document.getElementById('acceptDateLabel')) {
            const dateLabel = document.createElement('label');
            dateLabel.id = 'acceptDateLabel';
            dateLabel.htmlFor = 'acceptDate';
            dateLabel.textContent = 'Select Date:';
            daySel.parentNode.insertBefore(dateLabel, dateSel);
          }
        }
        function getDatesForDay(dayName) {
          const dayMap = { 'Sunday': 0, 'Monday': 1, 'Tuesday': 2, 'Wednesday': 3, 'Thursday': 4, 'Friday': 5, 'Saturday': 6 };
          const today = new Date();
          const end = new Date();
          end.setMonth(end.getMonth() + 1);
          let dates = [];
          let d = new Date(today);
          d.setHours(0,0,0,0);
          while (d <= end) {
            if (d.getDay() === dayMap[dayName]) {
              dates.push(new Date(d));
            }
            d.setDate(d.getDate() + 1);
          }
          return dates;
        }
        function updateDateOptions() {
          const selectedDay = daySel.value;
          const dates = getDatesForDay(selectedDay);
          dateSel.innerHTML = '';
          dates.forEach(date => {
            const opt = document.createElement('option');
            opt.value = date.toISOString().slice(0, 10);
            opt.textContent = date.toLocaleDateString();
            dateSel.appendChild(opt);
          });
        }
        daySel.onchange = updateDateOptions;
        // Initialize date options
        updateDateOptions();
        // Set duration
        document.getElementById('acceptDuration').value = req.estimated_hours || '';
        // Populate time options between start_time and end_time
        const timeSelect = document.getElementById('acceptTime');
        timeSelect.innerHTML = '';
        const startTimeRaw = req.start_time_raw || req.start_time;
        const endTimeRaw = req.end_time_raw || req.end_time;
        // Convert TIME format (HH:MM:SS) to HH:MM if needed
        const startTime = startTimeRaw ? startTimeRaw.substring(0, 5) : null;
        const endTime = endTimeRaw ? endTimeRaw.substring(0, 5) : null;
        
        if (!startTime || !endTime) {
          // Fallback if times are missing
          const opt = document.createElement('option');
          opt.value = '';
          opt.textContent = 'No time specified';
          timeSelect.appendChild(opt);
        } else {
          const estimatedHours = parseFloat(req.estimated_hours) || 1;
          const timeOptions = generateTimeOptions(startTime, endTime, estimatedHours);
          timeOptions.forEach(time => {
            const opt = document.createElement('option');
            opt.value = time;
            opt.textContent = time;
            timeSelect.appendChild(opt);
          });
        }
        // Show modal
        document.getElementById('acceptRequestModal').style.display = 'flex';
      }
      document.getElementById('closeAcceptModal').onclick = function() {
        document.getElementById('acceptRequestModal').style.display = 'none';
      };
      document.getElementById('acceptRequestForm').onsubmit = function(e) {
        e.preventDefault();
        const reqId = document.getElementById('acceptRequestId').value;
        const day = document.getElementById('acceptDay').value;
        const date = document.getElementById('acceptDate').value;
        const time = document.getElementById('acceptTime').value;
        const duration = document.getElementById('acceptDuration').value;
        const params =
          'request_id=' + encodeURIComponent(reqId) +
          '&selected_day=' + encodeURIComponent(day) +
          '&selected_date=' + encodeURIComponent(date) +
          '&selected_time=' + encodeURIComponent(time) +
          '&duration=' + encodeURIComponent(duration);
        fetch('php/acceptRequest.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: params
        })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            alert('Request sent for confirmation!');
            document.getElementById('acceptRequestModal').style.display = 'none';
            // Hide the request from search (add to hidden set)
            window.hiddenRequestIds.add(Number(reqId));
            renderResults(mockData);
          } else {
            alert(data.error || 'Failed to send request.');
          }
        })
        .catch(() => alert('Failed to send request.'));
      };
    });
    </script>

  </main>

  <!-- Footer -->
  <footer role="contentinfo">
    <p>&copy; 2025 Flinders Uni Skill Share (FUSS)</p>
  </footer>

  <!-- JS for Search Functionality -->
  <script>


    // User and skill mock data
    const offeredSkills = ["COMP2030 Tutoring","HTML/CSS Help","Essay Proofreading","Data Visualization Assistance","Language Exchange: Japanese-English"];
    const requestedSkills = ["Math Tutoring","PT","Moving Help","Guitar Lessons","Resume Proofreading"];

    // Filter option data stored here so admin changes persist in-memory
    const filterOptions = {
      typeFilter: ['All', 'Skill', 'User'],
      categoryFilter: ['All', 'Academic', 'Athletic', 'Practical', 'Creative', 'Other'],
      topicFilter: ['All', 'ENGR2792', 'COMP3752', 'COMP9035', 'COMP2030'],
      academicFilter: ['All', '1st', '2nd', '3rd', '4th', '5th'],
      dayFilter: ['All', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']
    };

    // Renders all filter <select> options from the in-memory filterOptions object, preserving previous selections if possible.
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
        // restore selection if still present, otherwise reset to 'All'
        if (filterOptions[selectId].includes(prev)) sel.value = prev;
        else sel.value = 'All';
      });
    }

    // Skill mock data (keep for now)
    let mockData = []; // Will contain only real data from database

    // Fetch requests and users from backend and merge with skills
    Promise.all([
      fetch('php/getRequests.php').then(r => r.json()),
      fetch('php/getUsers.php').then(r => r.json())
    ]).then(([requests, users]) => {
      // Convert requests to the same format as skills for display
      const requestData = (Array.isArray(requests) ? requests : []).map(req => ({
        id: req.id,
        title: req.title,
        name: req.username,
        type: "Request",
        category: req.category,
        topic: req.topic,
        academic: req.academic_year || req.academic,
        is_direct: req.is_direct, // ensure this is present for filtering
        day: (() => {
          const weekOrder = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
          let daysArr = [];
          if (Array.isArray(req.availability_days)) {
            daysArr = req.availability_days;
          } else if (typeof req.availability_days === 'string' && req.availability_days.startsWith('[')) {
            try {
              daysArr = JSON.parse(req.availability_days);
            } catch { daysArr = [req.availability_days]; }
          } else if (typeof req.availability_days === 'string') {
            daysArr = [req.availability_days];
          }
          // Sort days by week order
          daysArr = daysArr.filter(d => weekOrder.includes(d));
          daysArr.sort((a, b) => weekOrder.indexOf(a) - weekOrder.indexOf(b));
          return daysArr.join(', ');
        })(),
        time: req.start_time_display ? req.start_time_display + (req.end_time_display ? ' - ' + req.end_time_display : '') : '',
        description: req.description,
        tokens: req.tokens,
        estimated_hours: req.estimated_hours,
        start_time: req.start_time,
        end_time: req.end_time
      }));
      // Attach raw availability_days and original time fields for modal use
      requestData.forEach((req, i) => {
        req.availability_days_raw = requests[i].availability_days;
        req.start_time_raw = requests[i].start_time;
        req.end_time_raw = requests[i].end_time;
      });
      mockData = [...requestData, ...(Array.isArray(users) ? users : [])];
      performSearch(); // Use performSearch to apply current filters
    }).catch(err => {
      console.error('Failed to fetch users or requests:', err);
      renderResults([]); // fallback to empty array if fetch fails
    });

    const searchButton = document.getElementById("searchButton");
    const searchInput = document.getElementById("searchInput");
    const typeFilter = document.getElementById("typeFilter");
    const categoryFilter = document.getElementById("categoryFilter");
    const topicFilter = document.getElementById("topicFilter");
    const academicFilter = document.getElementById("academicFilter");
    const dayFilter = document.getElementById("dayFilter");
    const startTimeFilter = document.getElementById("startTimeFilter");
    const endTimeFilter = document.getElementById("endTimeFilter");
    const resultsContainer = document.getElementById("results");
    const noResults = document.getElementById("noResults");

    // Toggle this to true to show admin remove buttons
    let isAdmin = (window.FUSS && window.FUSS.isAdmin) || false;

    function renderResults(filteredData) {
      resultsContainer.innerHTML = "";
      // Filter out hidden requests
      // Hide requests that are already accepted/confirmed or hidden in the session
      const visibleData = filteredData.filter(item => {
        if (item.type === "Request") {
          if (window.hiddenRequestIds && window.hiddenRequestIds.has(Number(item.id))) return false;
        }
        return true;
      });
      if (visibleData.length === 0) {
        noResults.style.display = "block";
        return;
      }
      noResults.style.display = "none";

      visibleData.forEach(item => {
    // Listen for request re-list/decline events from messages.php
    window.addEventListener('request:relist', function(e) {
      if (e.detail && e.detail.requestId) {
        window.hiddenRequestIds.delete(Number(e.detail.requestId));
        renderResults(mockData);
      }
    });
    // Listen for request removal/confirm events from messages.php
    window.addEventListener('request:remove', function(e) {
      if (e.detail && e.detail.requestId) {
        window.hiddenRequestIds.add(Number(e.detail.requestId));
        renderResults(mockData);
      }
    });
        const card = document.createElement("div");
        card.className = "result-card";
        if (item.type == "User") {
          card.classList.add("result-user");
          const skillsOfferedText = Array.isArray(item.skillsOffered) && item.skillsOffered.length > 0 
            ? item.skillsOffered.join(', ') 
            : 'No skills offered';
          card.innerHTML = `
            <h3>${item.Name}</h3>
            <p><strong>Type:</strong> ${item.type}</p>
            <p><strong>Year:</strong> ${item.year}</p>
            <p><strong>Skills Offered:</strong> ${skillsOfferedText}</p>
            <div class="buttonContainer">
              <div></div>
              <a class="profile-btn push-right" href="profile.php?user=${encodeURIComponent(item.Name)}">View Profile</a>
            </div>
          `;
        } else if (item.type == "Request") {
          card.classList.add("result-request");
          card.dataset.requestId = item.id; // Store request ID for deletion
          // Determine if user can remove this request (own request or admin)
          const currentUser = window.FUSS && window.FUSS.currentUsername;
          const canRemove = isAdmin || (currentUser && item.name === currentUser);
          card.innerHTML = `
            <h3>${item.title}</h3>
            <div class="tagContainer">
              <span class="tag">${item.category}</span>
              <span class="tag">${item.topic}</span>
              <span class="tag">${item.academic}</span>
            </div>
            <div class="desc-container"><p>${item.description}</p></div>
            <p><strong>Tokens Offered:</strong> ${item.tokens} Token(s)</p>
            ${item.estimated_hours ? `<p><strong>Estimated Duration:</strong> ${item.estimated_hours} hour${item.estimated_hours !== 1 ? 's' : ''}</p>` : ''}
            <p><strong>Availability:</strong> ${item.day}</p>
            <p><strong>Time:</strong> ${item.time}</p>
            <p><strong>Posted by:</strong> ${item.name}</p>
            <div class="buttonContainer">
              ${canRemove ? 
                `<button class="removeService-btn" onclick="removeRequest(event, ${item.id})">Remove Request</button>` : 
                `<button class="request-btn" onclick="openAcceptRequestModal(${item.id})">Accept Request</button>`
              }

              <a class="profile-btn" href="profile.php?user=${encodeURIComponent(item.name)}">View Profile</a>
            </div>
          `;
        } else if (item.type == "Skill") {
          card.classList.add("result-skill");
          // include a data-index so remove can identify the correct item
          card.dataset.index = mockData.indexOf(item);
          card.innerHTML = `
            <h3>${item.title}</h3>
            <div class="tagContainer">
              <span class="tag">${item.category}</span>
              <span class="tag">${item.topic}</span>
              <span class="tag">${item.academic}</span>
            </div>
            <div class="desc-container"><p>${item.description}</p></div>
            <p><strong>Availablity:</strong> ${item.day}<strong>  </strong>${item.time}</pS>
            <p><strong>Cost:</strong> ${item.tokens} Token(s)</p>
            <p><strong>Posted by:</strong> ${item.name || "Anonymous"}</p>
            <div class="buttonContainer">
              ${isAdmin ? `<button class="removeService-btn" data-index="${mockData.indexOf(item)}" onclick="removeService(event)">Remove Service</button>` : `<button class="request-btn" onclick="alert('Feature coming soon!')">Request Service</button>`}
              <a class="profile-btn" href="profile.php?user=${encodeURIComponent(item.name)}">View Profile</a>
            </div>
          `;
        }
        resultsContainer.appendChild(card);
      });
    }

    function performSearch() {
      const query = searchInput.value.toLowerCase();
      const type = typeFilter.value;
      const category = categoryFilter.value;
      const topic = topicFilter.value;
      const academic = academicFilter.value;
      const day = dayFilter.value;
      const startTime = startTimeFilter.value;
      const endTime = endTimeFilter.value;

      const filtered = mockData.filter(item => {
        // Helper to validate and compare time
        function isValidTime(str) {
          return /^([01]\d|2[0-3]):([0-5]\d)$/.test(str);
        }
        function timeInRange(itemTime, start, end) {
          if (!isValidTime(start) || !isValidTime(end)) return true;
          // Convert to minutes
          const [itemH, itemM] = itemTime.split(":").map(Number);
          const [startH, startM] = start.split(":").map(Number);
          const [endH, endM] = end.split(":").map(Number);
          const itemMin = itemH * 60 + itemM;
          const startMin = startH * 60 + startM;
          const endMin = endH * 60 + endM;
          return itemMin >= startMin && itemMin <= endMin;
        }
        if (item.type === "Skill") {
          const matchQuery = (item.title && item.title.toLowerCase().includes(query)) || (item.description && item.description.toLowerCase().includes(query));
          const matchType = type === "All" || item.type === type;
          const matchCategory = category === "All" || item.category === category;
          const matchTopic = topic === "All" || item.topic === topic;
          const matchAcademic = academic === "All" || item.academic === academic;
          const matchDay = day === "All" || item.day === day;
          // Assume item.time is in HH:MM format
          const matchTime = (!startTime && !endTime) || (item.time && timeInRange(item.time, startTime, endTime));
          return matchQuery && matchType && matchCategory && matchTopic && matchAcademic && matchDay && matchTime;
        }
        if (item.type === "Request") {
          const matchQuery = (item.title && item.title.toLowerCase().includes(query)) || (item.description && item.description.toLowerCase().includes(query)) || (item.name && item.name.toLowerCase().includes(query));
          const matchType = type === "All" || item.type === type;
          const matchCategory = category === "All" || item.category === category;
          const matchTopic = topic === "All" || item.topic === topic;
          const matchAcademic = academic === "All" || item.academic === academic;
          const matchDay = day === "All" || item.day.includes(day) || item.day === day;
          // For requests, item.time is already formatted as "HH:MM - HH:MM"
          const matchTime = (!startTime && !endTime) || (item.time && item.time.includes(':'));
          return matchQuery && matchType && matchCategory && matchTopic && matchAcademic && matchDay && matchTime;
        }
        if (item.type === "User") {
          const matchType = type === "All" || item.type === type;
          const matchAcademic = academic === "All" || item.year === academic;
          let matchQuery = false;
          if (item.Name && item.Name.toLowerCase().includes(query)) matchQuery = true;
          if (Array.isArray(item.skillsOffered)) {
            matchQuery = matchQuery || item.skillsOffered.some(skill => skill.toLowerCase().includes(query));
          }
          if (Array.isArray(item.skillsRequested)) {
            matchQuery = matchQuery || item.skillsRequested.some(skill => skill.toLowerCase().includes(query));
          }
          return matchQuery && matchType && matchAcademic;
        }
        return false;
      });
      renderResults(filtered);
    }
    searchButton.addEventListener("click", performSearch);
    searchInput.addEventListener("keyup", e => { if (e.key === "Enter") performSearch(); });
    typeFilter.addEventListener("change", performSearch);
    categoryFilter.addEventListener("change", performSearch);
    topicFilter.addEventListener("change", performSearch);
    academicFilter.addEventListener("change", performSearch);
    dayFilter.addEventListener("change", performSearch);
    function autoFormatTimeInput(e) {
      let val = e.target.value.replace(/[^0-9:]/g, "");
      if (val.length === 2 && !val.includes(":")) {
        val = val + ":";
      }
      if (val.length > 5) val = val.slice(0, 5);
      e.target.value = val;
    }
    startTimeFilter.addEventListener("input", autoFormatTimeInput);
    endTimeFilter.addEventListener("input", autoFormatTimeInput);
    startTimeFilter.addEventListener("input", performSearch);
    endTimeFilter.addEventListener("input", performSearch);

  // Render selects from in-memory option lists then display results
  renderFilterOptions();
  // Results will be populated when fetch completes

    /* Admin: manage individual select options panel */
    // shared panel element
    const managePanel = document.createElement('div');
    managePanel.className = 'manage-panel';
    managePanel.style.display = 'none';
    document.body.appendChild(managePanel);

    // show/hide manage controls next to selects based on isAdmin
    function refreshManageControls() {
      const controls = document.querySelectorAll('.manage-controls');
      controls.forEach(c => c.style.display = isAdmin ? 'block' : 'none');
    }
    document.addEventListener('DOMContentLoaded', () => {
      refreshManageControls();
    });
    // expose render/refresh for other admin helpers
    window.renderFilterOptions = renderFilterOptions;
    window.refreshManageControls = refreshManageControls;

    // open the manage panel for a selectId
    function openManage(evt, selectId) {
      if (!isAdmin) return;
      evt.stopPropagation();
      const select = document.getElementById(selectId);
      if (!select) return;
      // build panel content
      managePanel.innerHTML = '';
      // build rows from filterOptions so we can mutate the arrays
      const arr = filterOptions[selectId] || Array.from(select.options).map(o => o.value);
      arr.forEach(val => {
        const row = document.createElement('div');
        row.className = 'option-row';
        const label = document.createElement('div');
        label.textContent = val;
        const del = document.createElement('button');
        del.className = 'del-btn';
        del.textContent = '-';
        if (val === 'All') {
          del.disabled = true;
          del.title = 'Cannot delete All';
        } else {
          del.addEventListener('click', (e) => {
            e.stopPropagation();
            if (!confirm(`Delete option "${val}" from ${selectId}?`)) return;
            // remove from filterOptions array
            const idx = filterOptions[selectId].indexOf(val);
            if (idx !== -1) filterOptions[selectId].splice(idx, 1);
            renderFilterOptions();
            closeManage();
            performSearch();
          });
        }
        row.appendChild(label);
        row.appendChild(del);
        managePanel.appendChild(row);
      });

      // position panel near the clicked button
      const rect = evt.currentTarget.getBoundingClientRect();
      managePanel.style.display = 'block';
      managePanel.style.left = `${rect.right + 8}px`;
      managePanel.style.top = `${rect.top}px`;
    }

    function closeManage() {
      managePanel.style.display = 'none';
      managePanel.innerHTML = '';
    }

    // prompt-based add for admins
    function addOptionPrompt(selectId) {
      if (!isAdmin) return;
      const val = prompt('Enter a new option for ' + selectId + '\n(Exact value will be used)');
      if (val === null) return; // cancelled
      const trimmed = String(val).trim();
      if (!trimmed) { alert('Please provide a non-empty option'); return; }
      const arr = filterOptions[selectId];
      if (!arr) return;
      // check duplicates case-insensitive
      if (arr.some(x => x.toLowerCase() === trimmed.toLowerCase())) { alert('Option already exists'); return; }
      arr.push(trimmed);
      renderFilterOptions();
      performSearch();
      // refresh panel if open for this select
      closeManage();
    }
    window.addOptionPrompt = addOptionPrompt;

    // close on outside click or Escape
    document.addEventListener('click', (e) => { if (!managePanel.contains(e.target)) closeManage(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeManage(); });

    // expose to inline handlers
    window.openManage = openManage;
    window.closeManage = closeManage;

    // Remove service handler (used when isAdmin === true)
    function removeService(e) {
      // allow passing event or index string
      let idx = null;
      if (e && e.target && e.target.dataset && e.target.dataset.index) {
        idx = Number(e.target.dataset.index);
      } else if (typeof e === 'number') {
        idx = e;
      }
      if (idx === null || isNaN(idx)) return;
      // Confirm removal
      if (!confirm('Remove this service?')) return;
      // remove from mockData
      mockData.splice(idx, 1);
      // re-render using current filters
      performSearch();
    }
    
    // Remove request handler - calls backend to delete request and refund tokens
    async function removeRequest(e, requestId) {
      if (!requestId) {
        console.error('No request ID provided');
        return;
      }
      
      // Confirm removal
      if (!confirm('Remove this request? Tokens will be refunded to you.')) return;
      
      try {
        const formData = new FormData();
        formData.append('requestId', requestId);
        
        const response = await fetch('/php/deleteRequest.php', {
          method: 'POST',
          credentials: 'include',
          body: formData
        });
        
        const result = await response.json();
        
        if (response.ok && result.success) {
          alert(result.message || 'Request removed successfully and tokens refunded!');
          // Remove from local data and re-render
          mockData = mockData.filter(item => !(item.type === 'Request' && item.id == requestId));
          performSearch();
        } else {
          alert(result.error || 'Failed to remove request');
        }
      } catch (error) {
        console.error('Error removing request:', error);
        alert('Failed to remove request. Please try again.');
      }
    }
    
    // expose so inline onclick can call it

    window.removeService = removeService;
    window.removeRequest = removeRequest;
</script>
<script>
// Hide request from search after accept (called from modal logic)
window.hideRequestAfterAccept = function(requestId) {
  if (!window.hiddenRequestIds) window.hiddenRequestIds = new Set();
  window.hiddenRequestIds.add(Number(requestId));
  if (typeof renderResults === 'function' && typeof mockData !== 'undefined') renderResults(mockData);
}
document.addEventListener('DOMContentLoaded', function() {
  if (typeof window.openAcceptRequestModal === 'function') {
    const origOpenAcceptRequestModal = window.openAcceptRequestModal;
    window.openAcceptRequestModal = function(requestId) {
      origOpenAcceptRequestModal(requestId);
      window.hideRequestAfterAccept(requestId);
    }
  }
});
</script>
</body>
</html>