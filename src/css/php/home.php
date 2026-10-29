<?php
session_start();
require_once "php/connect.php";
require_once "php/checkSuspension.php";

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: signIn.html");
    exit();
}

// Check if user is suspended
checkUserSuspension($conn, $_SESSION['user_id']);

// Fetch user data from the database
$userId = $_SESSION['user_id'];
$sql = "SELECT username, token, isAdmin FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

if (!$user) {
    // User not found in database, clear session and redirect
    session_destroy();
    header("Location: signIn.html");
    exit();
}

mysqli_stmt_close($stmt);
mysqli_close($conn);

// Use safe, sanitized values
$username = htmlspecialchars($user['username']);
$userTokens = intval($user['token']);
$isAdmin = (bool)$user['isAdmin'];

// Redirect admin users to admin dashboard
if ($isAdmin) {
    header("Location: admin.html");
    exit();
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>FUSS | Home</title>
  <link rel="stylesheet" href="css/styles.css">
  <script>
    window.FUSS = window.FUSS || {};
    window.FUSS.isAdmin = <?= json_encode($isAdmin) ?>;
  </script>
  <script src="js/script.js" defer></script>
  <script>
    let currentDate = new Date();
    let calendarEvents = [];

    // Helpers to avoid UTC shift with YYYY-MM-DD
    function toYmdLocal(date) {
      const y = date.getFullYear();
      const m = String(date.getMonth() + 1).padStart(2, '0');
      const d = String(date.getDate()).padStart(2, '0');
      return `${y}-${m}-${d}`;
    }
    function parseYmdLocal(ymd) {
      // ymd expected as 'YYYY-MM-DD'
      const [y, m, d] = ymd.split('-').map(Number);
      return new Date(y, (m || 1) - 1, d || 1);
    }

    // Load data on page load
    document.addEventListener('DOMContentLoaded', function() {
      console.log('DOM loaded, initializing calendar...');
      loadRecentRequests();
      loadCalendarEvents();
      loadTransactions();
      setupCalendarNavigation();
      renderCalendar();
      
      // Debugging: log calendar grid status
      setTimeout(function() {
        const grid = document.getElementById('calendarGrid');
        console.log('Calendar grid after 1 second:', grid);
        if (grid) {
          console.log('Grid children count:', grid.children.length);
          console.log('Grid computed style:', window.getComputedStyle(grid).display);
        }
      }, 1000);
    });

    function loadRecentRequests() {
      fetch('php/getRecentRequests.php')
        .then(response => response.json())
        .then(data => {
          const list = document.getElementById('recentRequestsList');
          if (data.error) {
            list.innerHTML = '<li>Error loading requests: ' + data.error + '</li>';
            return;
          }
          
          if (data.length === 0) {
            list.innerHTML = '<li>No recent requests found.</li>';
            return;
          }
          
          list.innerHTML = '';
          data.forEach(request => {
            const li = document.createElement('li');
            const hoursText = request.estimated_hours ? ` • ${request.estimated_hours} hour${request.estimated_hours !== 1 ? 's' : ''}` : '';
            li.innerHTML = '<strong>' + request.username + ':</strong> ' + request.title + ' (' + request.token_cost + ' Token' + (request.token_cost !== 1 ? 's' : '') + hoursText + ')';
            list.appendChild(li);
          });
        })
        .catch(error => {
          console.error('Error loading recent requests:', error);
          document.getElementById('recentRequestsList').innerHTML = '<li>Error loading recent requests.</li>';
        });
    }

    function loadCalendarEvents() {
      fetch('php/getCalendarEvents.php')
        .then(response => response.json())
        .then(data => {
          if (data.error) {
            console.error('Error loading calendar events:', data.error);
            return;
          }
          calendarEvents = data;
          renderCalendar();
          renderEventsList();
        })
        .catch(error => {
          console.error('Error loading calendar events:', error);
        });
    }

    // Load recent token transactions for current user
    function loadTransactions() {
      fetch('php/getTransactions.php?limit=10')
        .then(response => response.json())
        .then(data => {
          const list = document.getElementById('transactionsList');
          if (!list) return;
          if (data.error) {
            list.innerHTML = '<li>Error loading transactions: ' + data.error + '</li>';
            return;
          }
          if (!Array.isArray(data) || data.length === 0) {
            list.innerHTML = '<li>No transactions yet.</li>';
            return;
          }
          list.innerHTML = '';
          data.forEach(tx => {
            const li = document.createElement('li');
            const sign = tx.direction === 'credit' ? '+' : '-';
            const cp = tx.counterparty_username ? (' @' + tx.counterparty_username) : '';
            let whenStr = tx.created_at;
            try {
              const when = new Date(tx.created_at.replace(' ', 'T'));
              if (!isNaN(when)) {
                whenStr = when.toLocaleString('en-AU', { hour12: true });
              }
            } catch (e) {}
            li.textContent = `${whenStr} • ${tx.type} • ${sign}${tx.amount} token(s)${cp}`;
            list.appendChild(li);
          });
        })
        .catch(() => {
          const list = document.getElementById('transactionsList');
          if (list) list.innerHTML = '<li>Error loading transactions.</li>';
        });
    }

    function setupCalendarNavigation() {
      document.getElementById('prevMonth').addEventListener('click', function() {
        currentDate.setMonth(currentDate.getMonth() - 1);
        renderCalendar();
      });

      document.getElementById('nextMonth').addEventListener('click', function() {
        currentDate.setMonth(currentDate.getMonth() + 1);
        renderCalendar();
      });
    }

    function renderCalendar() {
      console.log('Rendering calendar for:', currentDate);
      const monthNames = ["January", "February", "March", "April", "May", "June",
        "July", "August", "September", "October", "November", "December"];
      const dayNames = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];

      // Update month header
      const monthHeader = document.getElementById('currentMonth');
      if (monthHeader) {
        monthHeader.textContent = monthNames[currentDate.getMonth()] + ' ' + currentDate.getFullYear();
      }

      // Get first day of month and number of days
      const firstDay = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
      const lastDay = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0);
      const startDate = new Date(firstDay);
      startDate.setDate(startDate.getDate() - firstDay.getDay());

      const calendarGrid = document.getElementById('calendarGrid');
      if (!calendarGrid) {
        console.error('Calendar grid element not found!');
        return;
      }
      
      console.log('Calendar grid found, clearing and rendering...');
      calendarGrid.innerHTML = '';

      // Create table structure
      const table = document.createElement('table');
      table.className = 'calendar-table';
      
      // Create header row
      const thead = document.createElement('thead');
      const headerRow = document.createElement('tr');
      dayNames.forEach(day => {
        const th = document.createElement('th');
        th.textContent = day;
        headerRow.appendChild(th);
      });
      thead.appendChild(headerRow);
      table.appendChild(thead);
      
      // Create body
      const tbody = document.createElement('tbody');
      const today = new Date();
      
      // Create 6 weeks of calendar
      for (let week = 0; week < 6; week++) {
        const weekRow = document.createElement('tr');
        
        for (let day = 0; day < 7; day++) {
          const cellIndex = week * 7 + day;
          const cellDate = new Date(startDate);
          cellDate.setDate(startDate.getDate() + cellIndex);
          
          const td = document.createElement('td');
          td.textContent = cellDate.getDate();
          
          // Check if day is in current month
          if (cellDate.getMonth() !== currentDate.getMonth()) {
            td.classList.add('other-month');
          }
          
          // Check if day is today
          if (cellDate.toDateString() === today.toDateString()) {
            td.classList.add('today');
          }
          
          // Check if day has events
          // Compare using local YMD strings to avoid UTC date shifts
          const dayString = toYmdLocal(cellDate);
          const hasEvent = calendarEvents.some(event => {
            const eventYmd = event.meeting_date || event.date; // server returns 'YYYY-MM-DD'
            return !event.completed && eventYmd === dayString; // hide dots for completed meetings
          });
          
          if (hasEvent) {
            td.classList.add('has-event');
            const indicator = document.createElement('div');
            indicator.className = 'event-dot';
            td.appendChild(indicator);
          }
          
          weekRow.appendChild(td);
        }
        
        tbody.appendChild(weekRow);
      }
      
      table.appendChild(tbody);
      calendarGrid.appendChild(table);
      
      console.log('Calendar table rendered with', table.rows.length, 'rows');
    }

    function renderEventsList() {
      const eventsList = document.getElementById('eventsList');
      
      if (calendarEvents.length === 0) {
        eventsList.innerHTML = '<li>No upcoming meetings scheduled.</li>';
        return;
      }

      // Filter events to only show upcoming ones (today and future)
      const todayYmd = toYmdLocal(new Date());
      const currentUserId = <?php echo json_encode($userId); ?>;
      // Show upcoming events that are not completed, plus any past events that are not yet completed for which current user is requester
      const upcomingEvents = calendarEvents.filter(event => {
        if (event.completed) return false; // hide completed meetings entirely
        const eventYmd = event.meeting_date || event.date; // 'YYYY-MM-DD'
        if (typeof eventYmd !== 'string') return false;
        const isUpcoming = eventYmd >= todayYmd;
        const isRequester = event.requester_id && Number(event.requester_id) === Number(currentUserId);
        const needsCompletion = isRequester && !event.completed;
        return isUpcoming || needsCompletion; // keep overdue until completed
      });

      if (upcomingEvents.length === 0) {
        eventsList.innerHTML = '<li>No upcoming meetings scheduled.</li>';
        return;
      }

      eventsList.innerHTML = '';
      
      // Sort upcoming events by date
  const sortedEvents = upcomingEvents.sort((a, b) => (a.date < b.date ? -1 : (a.date > b.date ? 1 : 0)));
      
      sortedEvents.forEach(event => {
        const li = document.createElement('li');
        
  // Calculate if the meeting is today
  const isToday = (event.date === todayYmd);
  const isOverdue = (event.date < todayYmd) && !event.completed;
        const dayLabel = isToday ? 'Today' : event.day;
        
        li.innerHTML = 
          '<div class="event-title">' + event.title + '</div>' +
          '<div class="event-details">' + dayLabel + ', ' + event.date + ' at ' + event.time + ' (' + event.duration + 'h)</div>';

        // Add Complete button for the requester when not yet completed
        try {
          const currentUserId = <?php echo json_encode($userId); ?>;
          if (!event.completed && event.requester_id && Number(event.requester_id) === Number(currentUserId)) {
            const btn = document.createElement('button');
            btn.textContent = 'Complete';
            btn.className = 'primary-btn';
            btn.style.marginLeft = '8px';
            btn.addEventListener('click', async () => {
              if (!confirm('Mark this meeting as completed and credit tokens to the helper?')) return;
              try {
                const resp = await fetch('php/completeMeeting.php', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                  body: 'message_id=' + encodeURIComponent(event.id)
                });
                const data = await resp.json();
                if (data.success) {
                  alert(data.message || 'Meeting completed');
                  loadCalendarEvents();
                  
                  // Show review prompt if requested
                  if (data.show_review_prompt) {
                    showReviewPrompt(data.helper_id, data.message_id);
                  }
                } else {
                  alert(data.error || 'Failed to complete meeting');
                }
              } catch (e) {
                alert('Failed to complete meeting');
              }
            });
            li.appendChild(btn);
          }
        } catch (e) { /* ignore */ }
        
        // Add special styling for today's meetings
        if (isToday) {
          li.style.borderLeft = '4px solid #dc2626';
          li.style.backgroundColor = '#fef2f2';
        } else if (isOverdue) {
          li.style.borderLeft = '4px solid #f59e0b';
          li.style.backgroundColor = '#fffbeb';
        }
        
        eventsList.appendChild(li);
      });
    }

    // Review modal functionality
    let currentReviewData = null;
    let selectedRating = 0;

    function showReviewPrompt(helperId, messageId) {
      currentReviewData = { helperId, messageId };
      document.getElementById('reviewModal').style.display = 'block';
      resetStarRating();
    }

    function closeReviewModal() {
      document.getElementById('reviewModal').style.display = 'none';
      currentReviewData = null;
      selectedRating = 0;
      document.getElementById('reviewText').value = '';
    }

    function resetStarRating() {
      selectedRating = 0;
      const stars = document.querySelectorAll('#starRating span');
      stars.forEach(star => {
        star.style.color = '#ddd';
      });
    }

    // Star rating functionality
    document.addEventListener('DOMContentLoaded', function() {
      const stars = document.querySelectorAll('#starRating span');
      
      stars.forEach(star => {
        star.addEventListener('mouseover', function() {
          const rating = parseInt(this.dataset.rating);
          highlightStars(rating);
        });
        
        star.addEventListener('click', function() {
          selectedRating = parseInt(this.dataset.rating);
          highlightStars(selectedRating);
        });
      });
      
      document.getElementById('starRating').addEventListener('mouseleave', function() {
        highlightStars(selectedRating);
      });
    });

    function highlightStars(rating) {
      const stars = document.querySelectorAll('#starRating span');
      stars.forEach((star, index) => {
        if (index + 1 <= rating) {
          star.style.color = '#fbbf24';
        } else {
          star.style.color = '#ddd';
        }
      });
    }

    async function submitReview() {
      if (selectedRating === 0) {
        alert('Please select a rating');
        return;
      }
      
      if (!currentReviewData) {
        alert('Review data missing');
        return;
      }
      
      const reviewText = document.getElementById('reviewText').value;
      
      try {
        const response = await fetch('php/submitReview.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: `helper_id=${encodeURIComponent(currentReviewData.helperId)}&message_id=${encodeURIComponent(currentReviewData.messageId)}&rating=${encodeURIComponent(selectedRating)}&review_text=${encodeURIComponent(reviewText)}`
        });
        
        const data = await response.json();
        
        if (data.success) {
          alert('Review submitted successfully!');
          closeReviewModal();
        } else {
          alert(data.error || 'Failed to submit review');
        }
      } catch (error) {
        console.error('Error submitting review:', error);
        alert('Failed to submit review');
      }
    }
  </script>
  <!--GPT-5 line 68 it first created a new table to store each of the peer reviews to be able to be shown to the correct user after they write their review. it created a star system which
  had the hover command which allowed it to light up the stars when hovered over and
  then when clicked it would set the rating. it also had a text area for additional comments.-->
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
    <a href="home.php" class="nav-link active"><span>Home</span></a>
    <a href="search.php" class="nav-link"><span>Search</span></a>
    <a href="request.php" class="nav-link"><span>Request</span></a>
    <a href="messages.php" class="nav-link"><span>Messages</span></a>
    <a href="profile.php" class="nav-link"><span>Profile</span></a>
    <a href="settings.php" class="nav-link"><span>Settings</span></a>
  </nav>

  <!-- Main Page Content -->
  <main role="main">
    <h1>Welcome to FUSS</h1>
    <section class="user-summary" aria-label="User overview">
      <div class="user-info">
        <h2>Your Account Summary</h2>
        <p><strong>Username:</strong> <span id="userName"><?= $username ?></span></p>
        <p><strong>Tokens:</strong> <span id="userTokens"><?= $userTokens ?></span></p>
      </div>
      <div class="quick-actions">
  <button onclick="window.location.href='request.php'" class="primary-btn">Create Request</button>
        <button onclick="window.location.href='search.php'" class="secondary-btn">Find Skills</button>
      </div>
    </section>

    <section class="calendar-section" aria-label="Your Schedule">
      <h2>Your Schedule</h2>
      <div class="calendar-container">
        <div class="calendar-wrapper">
          <div class="calendar-header">
            <button id="prevMonth" class="calendar-nav">&lt;</button>
            <h3 id="currentMonth"></h3>
            <button id="nextMonth" class="calendar-nav">&gt;</button>
          </div>
          <div class="calendar-grid" id="calendarGrid">
            <!-- Calendar will be generated here -->
          </div>
        </div>
        <div class="events-list">
          <h4>Upcoming Meetings</h4>
          <p class="events-subtitle" id="eventsSubtitle">Complete meetings to credit tokens to helpers</p>
          <ul id="eventsList">
            <!-- Events will be loaded here -->
          </ul>
        </div>
      </div>
    </section>

    <section class="recent-section" aria-label="Recent Requests">
      <h2>Recent Requests</h2>
      <ul class="request-list" id="recentRequestsList">
        <!-- Recent requests will be loaded here -->
      </ul>
    </section>

    <section class="recent-section" aria-label="Transaction History">
      <h2>Transaction History</h2>
      <ul class="request-list" id="transactionsList">
        <!-- Transactions will be loaded here -->
      </ul>
    </section>
  </main>

  <!-- Review Modal -->
  <div id="reviewModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 12px; width: 90%; max-width: 500px;">
      <h3 style="margin-top: 0; color: #1e3a8a;">Write a Peer Review</h3>
      <p>How was your experience working with this person?</p>
      
      <div style="margin-bottom: 20px;">
        <label style="display: block; font-weight: 600; margin-bottom: 8px;">Rating:</label>
        <div id="starRating" style="font-size: 24px; color: #ddd; cursor: pointer;">
          <span data-rating="1">★</span>
          <span data-rating="2">★</span>
          <span data-rating="3">★</span>
          <span data-rating="4">★</span>
          <span data-rating="5">★</span>
        </div>
      </div>
      
      <div style="margin-bottom: 20px;">
        <label for="reviewText" style="display: block; font-weight: 600; margin-bottom: 8px;">Review (optional):</label>
        <textarea id="reviewText" rows="4" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;" placeholder="Share your experience..."></textarea>
      </div>
      
      <div style="text-align: right;">
        <button onclick="closeReviewModal()" style="background: #6b7280; color: white; border: none; padding: 10px 20px; border-radius: 6px; margin-right: 10px; cursor: pointer;">Skip</button>
        <button onclick="submitReview()" style="background: #1e3a8a; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer;">Submit Review</button>
      </div>
    </div>
  </div>

  <!-- Footer -->
  <footer role="contentinfo">
    <p>&copy; 2025 Flinders Uni Skill Share (FUSS)</p>
  </footer>
</body>
</html>
