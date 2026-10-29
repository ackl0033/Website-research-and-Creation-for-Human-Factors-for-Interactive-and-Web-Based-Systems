<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: signIn.html");
    exit;
}

require_once 'inc/dbconn.inc.php';
require_once 'php/checkSuspension.php';

// Check if user is suspended
checkUserSuspension($conn, $_SESSION['user_id']);

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Get the target user if specified in URL parameters
$target_user = isset($_GET['user']) ? $_GET['user'] : null;
$conversation_id = null;

// If target user is specified, create or get conversation
if ($target_user && $target_user !== $username) {
    // Get target user ID
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $target_user);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($target_user_data = $result->fetch_assoc()) {
        $target_user_id = $target_user_data['id'];
        
        // Create or get conversation (ensure consistent user ordering)
        $smaller_user_id = min($user_id, $target_user_id);
        $larger_user_id = max($user_id, $target_user_id);
        $smaller_username = ($user_id < $target_user_id) ? $username : $target_user;
        $larger_username = ($user_id < $target_user_id) ? $target_user : $username;
        
        $stmt = $conn->prepare("
            SELECT id FROM conversations 
            WHERE (user1_id = ? AND user2_id = ?) 
               OR (user1_id = ? AND user2_id = ?)
        ");
        $stmt->bind_param("iiii", $user_id, $target_user_id, $target_user_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($conv_data = $result->fetch_assoc()) {
            $conversation_id = $conv_data['id'];
        } else {
            // Create new conversation with consistent ordering
            $stmt = $conn->prepare("
                INSERT INTO conversations (user1_id, user2_id, user1_username, user2_username) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param("iiss", $smaller_user_id, $larger_user_id, $smaller_username, $larger_username);
            if ($stmt->execute()) {
                $conversation_id = $conn->insert_id;
            }
        }
    }
}
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>FUSS | Messages</title>
  <link rel="stylesheet" href="css/styles.css">
  <script src="js/script.js" defer></script>
  <style>
    .messages-container {
      display: grid;
      grid-template-columns: 300px 1fr;
      gap: 0;
      height: calc(100vh - 200px);
      background: white;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    }

    .conversations-list {
      border-right: 1px solid #e5e7eb;
      background: #f9fafb;
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }

    .conversations-scroll {
      flex: 1;
      overflow-y: auto;
    }

    .conversations-header {
      padding: 16px;
      background: #1e3a8a;
      color: white;
      font-weight: 600;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .new-message-btn {
      background: rgba(255,255,255,0.2);
      border: none;
      color: white;
      padding: 6px 12px;
      border-radius: 6px;
      cursor: pointer;
      font-size: 14px;
    }

    .new-message-btn:hover {
      background: rgba(255,255,255,0.3);
    }

    .conversation-item {
      padding: 12px 16px;
      border-bottom: 1px solid #e5e7eb;
      cursor: pointer;
      transition: background 0.2s;
    }

    .conversation-item:hover {
      background: #f3f4f6;
    }

    .conversation-item.active {
      background: #e0e7ff;
      border-left: 3px solid #1e3a8a;
    }

    .conversation-item .name {
      font-weight: 600;
      margin-bottom: 4px;
    }

    .conversation-item .preview {
      font-size: 14px;
      color: #6b7280;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .conversation-item .time {
      font-size: 12px;
      color: #9ca3af;
      margin-top: 4px;
    }

    .conversation-item.unread {
      background: #eff6ff;
    }

    .conversation-item.unread .name {
      color: #1e3a8a;
    }

    .chat-area {
      display: flex;
      flex-direction: column;
      background: white;
      height: 100%;
      overflow: hidden;
    }

    .chat-header {
      padding: 16px;
      border-bottom: 1px solid #e5e7eb;
      background: #f9fafb;
      flex-shrink: 0;
    }

    .chat-header h2 {
      margin: 0;
      font-size: 18px;
      color: #1e3a8a;
    }

    .chat-header .subtitle {
      font-size: 14px;
      color: #6b7280;
      margin-top: 4px;
    }

    .messages-area {
      flex: 1;
      overflow-y: auto;
      padding: 20px;
      display: flex;
      flex-direction: column;
      gap: 12px;
      max-height: 100%;
    }

    .message {
      display: flex;
      gap: 10px;
      max-width: 70%;
    }

    .message.sent {
      align-self: flex-end;
      flex-direction: row-reverse;
    }

    .message-bubble {
      background: #f3f4f6;
      padding: 10px 14px;
      border-radius: 12px;
      word-wrap: break-word;
    }

    .message.sent .message-bubble {
      background: #1e3a8a;
      color: white;
    }

    .message-time {
      font-size: 12px;
      color: #9ca3af;
      margin-top: 4px;
    }

    .message-input-area {
      padding: 16px;
      border-top: 1px solid #e5e7eb;
      background: #f9fafb;
      flex-shrink: 0;
    }

    .message-input-form {
      display: flex;
      gap: 10px;
    }

    .message-input-form input {
      flex: 1;
      padding: 10px 14px;
      border: 1px solid #d1d5db;
      border-radius: 8px;
      font-size: 15px;
    }

    .send-btn {
      background: #1e3a8a;
      color: white;
      border: none;
      padding: 10px 20px;
      border-radius: 8px;
      cursor: pointer;
      font-weight: 600;
    }

    .send-btn:hover {
      background: #334155;
    }

    .empty-state {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      height: 100%;
      color: #9ca3af;
    }

    .empty-state svg {
      width: 80px;
      height: 80px;
      margin-bottom: 16px;
      opacity: 0.3;
    }

    @media (max-width: 768px) {
      .messages-container {
        grid-template-columns: 1fr;
        height: calc(100vh - 180px);
      }

      .conversations-list {
        display: none;
      }

      .conversations-list.mobile-show {
        display: flex;
      }

      .chat-area.mobile-hide {
        display: none;
      }
      
      .message {
        max-width: 85%;
      }
    }

    @media (max-width: 900px) {
      .top-banner .message {
        display: none;
      }
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
            <a href="#" class="dropdownLanguage">English</a>
            <a href="#" class="dropdownLanguage">中文</a>
            <a href="#" class="dropdownLanguage">日本語</a>
          </div>
        </div>
        <?php if (isset($_SESSION['username'])): ?>
          <span class="welcome-text">Welcome, <?= htmlspecialchars($_SESSION['username']) ?></span>
        <?php endif; ?>
        <button class="logOutBtn" onclick="window.location.href='logout.php'">Log Out</button>
        <button id="menuToggle" aria-label="Toggle sidebar">☰</button>
      </div>
    </div>
  </header>

<<<<<<< Updated upstream
<<<<<<< Updated upstream
    <!-- Sidebar Navigation -->
  <nav class="sidebar" role="navigation" aria-label="Main navigation">
    <a href="home.php" class="nav-link"><span>Home</span></a>
    <a href="search.php" class="nav-link"><span>Search</span></a>
  <a href="request.php" class="nav-link"><span>Request</span></a>
    <a href="messages.php" class="nav-link active"><span>Messages</span></a>
    <a href="profile.php" class="nav-link"><span>Profile</span></a>
    <a href="settings.php" class="nav-link"><span>Settings</span></a>
  </nav>
=======
=======
>>>>>>> Stashed changes
  <!-- Sidebar Navigation -->
  <nav class="sidebar" role="navigation" aria-label="Main navigation">
  <a href="home.php" class="nav-link"><span><?php
    $isAdmin = false;
    if (isset($_SESSION['username'])) {
      $stmt = $conn->prepare("SELECT isAdmin FROM users WHERE username = ?");
      $stmt->bind_param("s", $_SESSION['username']);
      $stmt->execute();
      $result = $stmt->get_result();
      $row = $result->fetch_assoc();
      $isAdmin = $row ? (bool)$row['isAdmin'] : false;
    }
    echo ($isAdmin ? 'Admin Dashboard' : 'Home');
  ?></span></a>
  <a href="search.php" class="nav-link"><span>Search</span></a>
  <a href="request.php" class="nav-link"><span>Request</span></a>
  <a href="messages.php" class="nav-link active"><span>Messages</span></a>
  <a href="profile.php" class="nav-link"><span>Profile</span></a>
  <a href="settings.php" class="nav-link"><span>Settings</span></a>
  </nav>

  <!-- Main Content -->
  <main role="main">
    <h1>Messages</h1>

    <div class="messages-container">
      <!-- Conversations List -->
      <div class="conversations-list" id="conversationsList">
        <div class="conversations-header">
          <span>Conversations</span>
        </div>
        
        <div class="conversations-scroll" id="conversationsContainer">
          <!-- Conversations will be loaded here -->
        </div>
      </div>

      <!-- Chat Area -->
      <div class="chat-area" id="chatArea">
        <div class="chat-header" id="chatHeader" style="display: none;">
          <h2 id="chatName"></h2>
          <div class="subtitle">Click on a conversation to start chatting</div>
        </div>

        <div class="messages-area" id="messagesArea">
          <div class="empty-state">
            <p>Select a conversation to start messaging</p>
            <p style="font-size: 14px; margin-top: 8px;">Visit someone's profile and click "Message" to start a new conversation!</p>
          </div>
        </div>

        <div class="message-input-area" id="messageInputArea" style="display: none;">
          <form class="message-input-form" id="messageForm">
            <input type="text" id="messageInput" placeholder="Type a message..." required>
            <input type="hidden" id="currentConversationId" value="">
            <button type="submit" class="send-btn">Send</button>
          </form>
        </div>
      </div>
    </div>
  </main>

  <!-- Footer -->
  <footer role="contentinfo">
    <p>&copy; 2025 Flinders Uni Skill Share (FUSS)</p>
  </footer>

  <script>
    // Remove hidden tags like <!--RID:...-->, <!--DREQ:...-->, <!--TOK:...-->, <!--REQBY:...-->, <!--HELPER:...-->, <!--DONE-->
    function stripHiddenTags(text) {
      if (typeof text !== 'string') return '';
      return text.replace(/<!--[^>]*-->/g, '');
    }
    const currentUserId = <?= json_encode($user_id) ?>;
    const currentUsername = <?= json_encode($username) ?>;
    const targetUser = <?= isset($target_user) ? json_encode($target_user) : 'null' ?>;
    const initialConversationId = <?= isset($conversation_id) ? json_encode($conversation_id) : 'null' ?>;

    // Load conversations on page load
    window.addEventListener('DOMContentLoaded', function() {
      loadConversations();
      
      // If we have an initial conversation, select it
      if (initialConversationId) {
        setTimeout(() => {
          selectConversation(initialConversationId, targetUser);
        }, 500);
      }
    });

    // Load user's conversations
    function loadConversations() {
      fetch('php/getConversations.php')
        .then(response => response.json())
        .then(conversations => {
          const container = document.getElementById('conversationsContainer');
          container.innerHTML = '';
          
          if (conversations.length === 0) {
            container.innerHTML = '<div style="padding: 20px; text-align: center; color: #9ca3af;">No conversations yet</div>';
            return;
          }
          
          conversations.forEach(conv => {
            const item = document.createElement('div');
            item.className = 'conversation-item';
            item.dataset.conversationId = conv.id;
            item.dataset.otherUser = conv.other_username;
            // Display the time of the last message in Adelaide time
            let time = '';
            if (conv.last_message_at) {
              let date = new Date(conv.last_message_at);
              if (isNaN(date.getTime())) {
                date = new Date(conv.last_message_at.replace(' ', 'T'));
              }
              if (!isNaN(date.getTime())) {
                time = date.toLocaleTimeString('en-AU', {
                  hour: 'numeric',
                  minute: '2-digit',
                  hour12: true,
                  timeZone: 'Australia/Adelaide'
                });
              } else {
                time = 'Invalid time';
              }
            }
            const previewText = stripHiddenTags(conv.last_message || 'No messages yet');
            item.innerHTML = '<div class="name">' + conv.other_username + '</div>' +
              '<div class="preview">' + escapeHtml(previewText) + '</div>' +
              '<div class="time">' + time + '</div>';
            item.addEventListener('click', () => {
              selectConversation(conv.id, conv.other_username);
            });
            container.appendChild(item);
          });
        })
        .catch(error => {
          console.error('Error loading conversations:', error);
        });
    }

    // Select a conversation and load messages
    function selectConversation(conversationId, otherUsername) {
      // Update UI
      document.querySelectorAll('.conversation-item').forEach(item => {
        item.classList.remove('active');
      });
      
      const selectedItem = document.querySelector('[data-conversation-id="' + conversationId + '"]');
      if (selectedItem) {
        selectedItem.classList.add('active');
        selectedItem.classList.remove('unread');
      }
      
      // Update chat header
      document.getElementById('chatName').textContent = otherUsername;
      document.getElementById('chatHeader').style.display = 'block';
      document.getElementById('messageInputArea').style.display = 'block';
      document.getElementById('currentConversationId').value = conversationId;
      
      // Load messages
      loadMessages(conversationId);
    }

    // Load messages for a conversation
    function loadMessages(conversationId) {
      fetch('php/getMessages.php?conversation_id=' + conversationId)
        .then(response => response.json())
        .then(messages => {
          const messagesArea = document.getElementById('messagesArea');
          messagesArea.innerHTML = '';
          
          if (messages.length === 0) {
            messagesArea.innerHTML = '<div class="empty-state"><p>No messages yet</p><p style="font-size: 14px; margin-top: 8px;">Start the conversation!</p></div>';
            return;
          }
          
          messages.forEach(function(msg) {
            const messageDiv = document.createElement('div');
            messageDiv.className = 'message ' + (msg.sender_id == currentUserId ? 'sent' : 'received');
            
            // Parse and format time
            let date = new Date(msg.created_at);
            if (isNaN(date.getTime())) {
              date = new Date(msg.created_at.replace(' ', 'T'));
            }
            
            let time = 'Invalid time';
            if (!isNaN(date.getTime())) {
              try {
                time = date.toLocaleTimeString('en-AU', {
                  hour: 'numeric',
                  minute: '2-digit',
                  hour12: true,
                  timeZone: 'Australia/Adelaide'
                });
              } catch(e) {
                time = date.toLocaleTimeString();
              }
            }
            
            // Check if this is a pending request message (acceptance or direct request)
            const isPending = msg.message_text && msg.message_text.includes('wants to accept your request');
            const isDirectRequest = msg.message_text && msg.message_text.includes('Direct request from') && msg.message_text.includes('Please confirm or decline');
            
            // Build message HTML
            let msgHtml = '<div>';
            const cleanedText = stripHiddenTags(msg.message_text);
            msgHtml += '<div class="message-bubble">' + escapeHtml(cleanedText) + '</div>';
            msgHtml += '<div class="message-time">' + time + '</div>';
            
            if ((isPending || isDirectRequest) && msg.receiver_id == currentUserId) {
              msgHtml += '<div class="pending-actions" style="margin-top:8px;">';
              if (isPending) {
                msgHtml += '<button class="primary-btn" onclick="confirmRequestAction(' + msg.id + ', true)">Confirm</button>';
                msgHtml += '<button class="secondary-btn" onclick="confirmRequestAction(' + msg.id + ', false)">Decline</button>';
              } else if (isDirectRequest) {
                msgHtml += '<button class="primary-btn" onclick="confirmDirectRequest(' + msg.id + ', true)">Accept Request</button>';
                msgHtml += '<button class="secondary-btn" onclick="confirmDirectRequest(' + msg.id + ', false)">Decline</button>';
              }
              msgHtml += '</div>';
            }
            msgHtml += '</div>';
            
            messageDiv.innerHTML = msgHtml;
            messagesArea.appendChild(messageDiv);
          });
    // Handle confirm/decline actions for pending requests
    window.confirmRequestAction = function(messageId, isConfirm) {
      // Call backend to confirm or decline
      fetch('php/confirmRequestAction.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'message_id=' + encodeURIComponent(messageId) + '&confirm=' + (isConfirm ? 1 : 0)
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          alert(data.message || 'Action completed.');
          // Try to extract request_id from response (if available)
          let requestId = data.request_id;
          // If not in response, try to extract from message text (fallback)
          if (!requestId && data.message_text) {
            const match = data.message_text.match(/request #(\d+)/);
            if (match) requestId = match[1];
          }
          // Dispatch custom event for search page to update
          if (requestId) {
            const eventName = isConfirm ? 'request:remove' : 'request:relist';
            window.dispatchEvent(new CustomEvent(eventName, { detail: { requestId } }));
          }
          // Reload messages and conversations
          const conversationId = document.getElementById('currentConversationId').value;
          loadMessages(conversationId);
          loadConversations();
        } else {
          alert(data.error || 'Failed to process action.');
        }
      })
      .catch(() => alert('Failed to process action.'));
    }

    // Handle confirm/decline actions for direct requests
    window.confirmDirectRequest = function(messageId, isConfirm) {
      // Call backend to confirm or decline direct request
      fetch('php/confirmDirectRequest.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'message_id=' + encodeURIComponent(messageId) + '&confirm=' + (isConfirm ? 1 : 0)
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          alert(data.message || 'Action completed.');
          // Dispatch custom event for search page to update
          if (data.request_id) {
            const eventName = isConfirm ? 'request:remove' : 'request:relist';
            window.dispatchEvent(new CustomEvent(eventName, { detail: { requestId: data.request_id } }));
          }
          // Reload messages and conversations
          const conversationId = document.getElementById('currentConversationId').value;
          loadMessages(conversationId);
          loadConversations();
        } else {
          alert(data.error || 'Failed to process action.');
        }
      })
      .catch(() => alert('Failed to process direct request action.'));
    }
          
          // Scroll to bottom
          messagesArea.scrollTop = messagesArea.scrollHeight;
        })
        .catch(error => {
          console.error('Error loading messages:', error);
        });
    }

    // Send message
    document.getElementById('messageForm').addEventListener('submit', function(e) {
      e.preventDefault();
      
      const messageInput = document.getElementById('messageInput');
      const conversationId = document.getElementById('currentConversationId').value;
      const messageText = messageInput.value.trim();
      
      if (!messageText || !conversationId) return;
      
      fetch('php/sendMessage.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          conversation_id: conversationId,
          message_text: messageText
        })
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          messageInput.value = '';
          loadMessages(conversationId);
          loadConversations(); // Refresh conversations list
        } else {
          alert('Error sending message: ' + data.error);
        }
      })
      .catch(error => {
        console.error('Error sending message:', error);
      });
    });

    // Utility functions
    function formatTimeAgo(dateString) {
      // Parse as-is and compare in Adelaide time
      let date = new Date(dateString);
      if (isNaN(date.getTime())) {
        // Fallback: try replacing space with T
        date = new Date(dateString.replace(' ', 'T'));
      }
      const now = new Date();
      const adlNow = new Date(now.toLocaleString('en-AU', { timeZone: 'Australia/Adelaide' }));
      const adlDate = new Date(date.toLocaleString('en-AU', { timeZone: 'Australia/Adelaide' }));
      if (isNaN(adlDate.getTime())) return 'Invalid date';
      const diffMs = adlNow - adlDate;
      const diffMins = Math.floor(diffMs / 60000);
      const diffHours = Math.floor(diffMs / 3600000);
      const diffDays = Math.floor(diffMs / 86400000);
      if (diffMins < 1) return 'Just now';
      if (diffMins < 60) return diffMins + 'm ago';
      if (diffHours < 24) return diffHours + 'h ago';
      if (diffDays < 7) return diffDays + 'd ago';
      return adlDate.toLocaleDateString('en-AU', { timeZone: 'Australia/Adelaide' });
    }

    function escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }
  </script>
  // GPT-5
  line 112 there is another table called conversations that stores all info for a one-on-one
  conversation. there is also another table called messages that stores all of the messages between each user.
  the messages.php will pull this info from the database based on the users id and will display the
  messages in the appropriate conversation thread.
</body>
</html>