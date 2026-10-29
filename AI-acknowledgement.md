I acknowledge the use of ChatGPT in generating content included in this submission (GPT-5, 2025).
how do i implemnet an calander on the home that adds the request if it was accepted through a message to it and shows it in a upcoming meetings.

✅ Interactive Calendar with Confirmed Meetings
What was added:
Backend - getCalendarEvents.php:

Fetches confirmed meeting messages from the database
Parses meeting details (date, time, duration, partner) from confirmation messages
Returns structured event data for the calendar
Frontend - Calendar Component:

Interactive calendar grid with month navigation
Event indicators showing days with confirmed meetings
Events list showing upcoming meetings with details
Responsive design that works on mobile devices
Styling - Calendar CSS:

Clean, modern calendar design
Event indicators for days with meetings
Hover effects and today highlighting
Mobile-responsive layout
Features:
✅ Visual Calendar Grid: Shows current month with navigation arrows
✅ Event Detection: Parses confirmation messages to extract meeting details
✅ Event Indicators: Blue dots on days with confirmed meetings
✅ Upcoming Events List: Shows meeting details (partner, date, time, duration)
✅ Month Navigation: Previous/Next month buttons
✅ Today Highlighting: Current day is highlighted
✅ Mobile Responsive: Adapts to smaller screens

How it works:
When a request is confirmed → Meeting details are saved in the confirmation message
Calendar loads → Scans all confirmation messages for meeting details
Calendar displays → Shows events on the appropriate dates
Event list shows → "Meeting with [Partner] on [Day], [Date] at [Time] ([Duration]h)"
The calendar is now positioned between the Account Summary and Recent Requests sections on the home page, providing users with a clear view of their scheduled meetings!

how do i add the ability to upload the profile picture and store it in the database to be accessed later
Database

Added a new column to store the image path:
ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_image VARCHAR(255) NULL;
File: Schema.sql
Backend endpoint

Created upload_profile_image.php to handle image uploads:
Requires a logged-in session ($_SESSION['username'])
Validates size (max 2MB) and MIME type (JPG/PNG/WEBP) using finfo with fallbacks
Stores files in profile with a randomized filename
Removes any previous image file to avoid orphaned files
Updates users.profile_image with a relative path like Images/profile/yourfile.jpg
Returns JSON: { success: true, path: "/Images/profile/..." } or { error: "..." }
Frontend/UI

profile.php
Added DB-backed image render with fallback
Reads profile_image from DB and displays it if set
Falls back to Images/default-avatar.svg
Added a hidden form with a file input to trigger uploads
script.js
Wired “Change Picture” button to:
Open file picker
Preview the selected image immediately
Upload via fetch('/php/upload_profile_image.php')
On success, updates the <img> source and busts cache

how can i implment a peer review feature that gives a prompt after completing a meeting and can this peer review be displayed in the profile of the other user.

✅ Peer Review System Implementation Complete
I have successfully implemented the complete peer review system as requested. Here's what was accomplished:

🔧 Backend Implementation
Database Schema: Created a robust reviews table with:

Rating constraints (1-5 stars only)
Foreign key relationships to users and messages
Unique constraint preventing duplicate reviews per meeting
Automatic timestamp tracking
API Endpoints:

submitReview.php: Handles review submission with validation and duplicate prevention
getReviews.php: Retrieves the 3 most recent reviews for any user
completeMeeting.php: Enhanced to trigger review prompts after meeting completion
🎨 Frontend Implementation
Review Modal (home.php):

Interactive 5-star rating system with hover effects
Optional text review input
Clean modal design with proper validation
Integrates seamlessly with meeting completion flow
Profile Integration (profile.php):

Dynamic loading of user reviews
Displays up to 3 most recent reviews with ratings and reviewer names
Proper error handling and empty states
Responsive design with consistent styling
🎯 User Experience
Complete Workflow:
After completing a meeting → Review prompt appears automatically
Users can rate 1-5 stars and optionally add text
Reviews are immediately visible on the helper's profile page
Clean, intuitive interface matching the existing design
🔒 Quality Features
Robust Implementation:
Prevents duplicate reviews for the same meeting
Proper input validation and sanitization
Error handling throughout the system
Mobile-responsive design
Consistent with existing UI patterns

how can i show the messages between 2 users and it only creates a message when you view profile and click the message button.

✅ Messaging System Implementation Complete
I've successfully implemented a complete messaging system for your FUSS application with the following features:

🗄️ Database Schema Updates
conversations table: Stores one-on-one conversations between users
messages table: Stores individual messages with read status tracking
Foreign key relationships: Proper data integrity between users, conversations, and messages
📱 Frontend Features
Dynamic conversation list: Shows all user conversations with last message preview and timestamps
Real-time message loading: Messages load dynamically from the database
Message sending: Users can send messages with immediate updates
Responsive design: Works on both desktop and mobile devices
Auto-scrolling: Messages automatically scroll to the bottom
🔗 Integration with Profiles
Message button functionality: When viewing someone's profile, clicking "Message" creates/opens a conversation
Automatic conversation creation: New conversations are created automatically when needed
Username-based routing: Direct navigation to conversations via URL parameters
⚙️ Backend API Endpoints
getConversations.php: Retrieves all conversations for the current user
getMessages.php: Loads messages for a specific conversation
sendMessage.php: Handles sending new messages with proper validation
🛡️ Security Features
Session validation: All endpoints check for logged-in users
Access control: Users can only access their own conversations
SQL injection protection: All database queries use prepared statements
Input sanitization: Message content is properly escaped
📍 Navigation Updates
Updated all navigation links from messages.html to messages.php
Consistent navigation across all pages
🚀 How It Works
Starting a conversation: Visit any user's profile and click the "💬 Message" button
Viewing conversations: All your conversations appear in the left sidebar
Sending messages: Type in the input field and press "Send" or hit Enter
Real-time updates: Conversations and messages refresh automatically
The messaging system is now fully functional and integrated with your existing user authentication and profile system. Users can start conversations by visiting profiles and clicking the message button, and all messages are stored persistently in the database.
