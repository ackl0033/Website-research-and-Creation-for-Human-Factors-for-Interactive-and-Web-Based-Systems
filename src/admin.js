// Admin Dashboard JavaScript

// Section Navigation
const navItems = document.querySelectorAll('.sidebar nav li');
const panels = document.querySelectorAll('.panel');

navItems.forEach(item => {
  item.addEventListener('click', function() {
    const section = this.dataset.section;
    
    
    navItems.forEach(nav => nav.classList.remove('active'));
    this.classList.add('active');
    
    
    panels.forEach(panel => {
      panel.classList.remove('active');
      if (panel.id === section) {
        panel.classList.add('active');
      }
    });
  });
});


document.getElementById('refreshBtn')?.addEventListener('click', function() {
  this.textContent = '↻ Refreshing...';
  this.disabled = true;
  
  
  updateDashboardStats();
  
  
  setTimeout(() => {
    this.textContent = '↻ Refresh';
    this.disabled = false;
    showMessage('Data refreshed successfully', 'success');
  }, 1000);
});

// Logout
document.getElementById('logoutBtn')?.addEventListener('click', function() {
  if (confirm('Are you sure you want to logout?')) {
    window.location.href = 'signIn.html';
  }
});

// Student Management Actions
document.querySelectorAll('.btn.edit').forEach(btn => {
  btn.addEventListener('click', function() {
    const row = this.closest('tr');
    const name = row.querySelector('td:nth-child(2)').textContent;
   
    window.location.href = `profile.php?user=${encodeURIComponent(name)}`;
  });
});

document.querySelectorAll('.btn.suspend').forEach(btn => {
  btn.addEventListener('click', function() {
    const row = this.closest('tr');
    const name = row.querySelector('td:nth-child(2)').textContent;
    
    if (confirm(`Suspend ${name}?\n\nThis will prevent them from accessing the platform.`)) {
      const statusBadge = row.querySelector('.status');
      statusBadge.textContent = 'Suspended';
      statusBadge.className = 'status suspended';
      
      this.textContent = 'Reactivate';
      this.className = 'btn small reactivate';
      
      showMessage(`${name} has been suspended`, 'success');
    }
  });
});

document.querySelectorAll('.btn.reactivate').forEach(btn => {
  btn.addEventListener('click', function() {
    const row = this.closest('tr');
    const name = row.querySelector('td:nth-child(2)').textContent;
    
    if (confirm(`Reactivate ${name}?`)) {
      const statusBadge = row.querySelector('.status');
      statusBadge.textContent = 'Active';
      statusBadge.className = 'status active';
      
      this.textContent = 'Suspend';
      this.className = 'btn small suspend';
      
      showMessage(`${name} has been reactivated`, 'success');
    }
  });
});

document.querySelectorAll('.btn.delete').forEach(btn => {
  btn.addEventListener('click', function() {
    const row = this.closest('tr');
    const userId = row.querySelector('td:first-child').textContent;
    const name = row.querySelector('td:nth-child(2)').textContent;
    const confirmation = prompt(`Type "${name}" to confirm deletion:\n\nWARNING: This will permanently delete all user data.`);
    if (confirmation === name) {
      
      fetch('php/deleteUser.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `user_id=${encodeURIComponent(userId)}`
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          row.remove();
          showMessage(`${name} has been deleted`, 'success');
        } else {
          showMessage('Failed to delete user: ' + (data.error || 'Unknown error'), 'error');
        }
      });
    } else if (confirmation !== null) {
      alert('Deletion cancelled - name did not match');
    }
  });
});


document.getElementById('creditForm')?.addEventListener('submit', function(e) {
  e.preventDefault();
  
  const studentUsername = document.getElementById('studentUsername').value;
  const amount = document.getElementById('creditAmount').value;
  const messageEl = document.getElementById('creditMessage');
  
  if (!studentUsername || !amount) {
    messageEl.textContent = '⚠️ Please fill in all fields';
    messageEl.className = 'message error';
    return;
  }
  
  // Call backend to adjust credits
  fetch('php/adjustCredits.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `username=${encodeURIComponent(studentUsername)}&amount=${encodeURIComponent(amount)}`
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      messageEl.textContent = `✓ Successfully adjusted ${amount > 0 ? '+' : ''}${amount} credits for ${studentUsername}. New balance: ${data.newBalance}`;
      messageEl.className = 'message success';
      
      this.reset();
      document.getElementById('usernameDropdown').style.display = 'none';
      
      // Refresh student table to show updated credits
      loadStudentTable();
    } else {
      messageEl.textContent = '❌ Failed to adjust credits: ' + (data.error || 'Unknown error');
      messageEl.className = 'message error';
    }
  })
  .catch(error => {
    console.error('Error adjusting credits:', error);
    messageEl.textContent = '❌ Failed to adjust credits: Network error';
    messageEl.className = 'message error';
  });
  
  
  setTimeout(() => {
    messageEl.textContent = '';
  }, 5000);
});

// Username autocomplete for credit adjustment
const usernameInput = document.getElementById('studentUsername');
const usernameDropdown = document.getElementById('usernameDropdown');
let usernameTimeout;

if (usernameInput && usernameDropdown) {
  usernameInput.addEventListener('input', function() {
    const query = this.value.trim();
    
    clearTimeout(usernameTimeout);
    
    if (query.length < 2) {
      usernameDropdown.style.display = 'none';
      return;
    }
    
    usernameTimeout = setTimeout(() => {
      fetch('php/getNonAdminUsers.php')
        .then(response => response.json())
        .then(users => {
          const filteredUsers = users.filter(user => 
            (user.username && user.username.toLowerCase().includes(query.toLowerCase())) ||
            (user.name && user.name.toLowerCase().includes(query.toLowerCase()))
          ).slice(0, 5);
          
          if (filteredUsers.length > 0) {
            usernameDropdown.innerHTML = filteredUsers.map(user => 
              `<div class="username-option" data-username="${user.username}">
                ${user.name || user.username} (${user.username})
              </div>`
            ).join('');
            usernameDropdown.style.display = 'block';
            
            
            usernameDropdown.querySelectorAll('.username-option').forEach(option => {
              option.addEventListener('click', function() {
                usernameInput.value = this.dataset.username;
                usernameDropdown.style.display = 'none';
              });
            });
          } else {
            usernameDropdown.style.display = 'none';
          }
        })
        .catch(error => {
          console.error('Error fetching usernames:', error);
          usernameDropdown.style.display = 'none';
        });
    }, 300);
  });
  
  // Hide dropdown when clicking outside
  document.addEventListener('click', function(e) {
    if (!usernameInput.contains(e.target) && !usernameDropdown.contains(e.target)) {
      usernameDropdown.style.display = 'none';
    }
  });
}

// Add Category Form
document.getElementById('addCategoryForm')?.addEventListener('submit', function(e) {
  e.preventDefault();
  
  const newCategory = document.getElementById('newCategory').value.trim();
  
  if (!newCategory) {
    alert('Please enter a category name');
    return;
  }
  
  const categoryList = document.getElementById('categoryList');
  const li = document.createElement('li');
  li.textContent = newCategory;
  categoryList.appendChild(li);
  
  this.reset();
  showMessage(`Category "${newCategory}" added successfully`, 'success');
});


document.querySelectorAll('.btn.deactivate').forEach(btn => {
  btn.addEventListener('click', function() {
    const row = this.closest('tr');
    const skillTitle = row.querySelector('td:nth-child(2)').textContent;
    
    if (confirm(`Deactivate skill: ${skillTitle}?`)) {
      const statusBadge = row.querySelector('.status');
      statusBadge.textContent = 'Inactive';
      statusBadge.className = 'status suspended';
      
      this.textContent = 'Activate';
      this.className = 'btn small reactivate';
      
      showMessage(`${skillTitle} has been deactivated`, 'success');
    }
  });
});

// Student Management Table Population
function loadStudentTable() {
  fetch('php/getNonAdminUsers.php')
    .then(response => response.json())
    .then(users => {
      const table = document.getElementById('studentTable');
      table.innerHTML = '';
      users.forEach(user => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${user.id}</td>
          <td>${user.name || user.username}</td>
          <td>${user.email}</td>
          <td>${user.token}</td>
          <td><span class="status ${user.isSuspended == 1 ? 'suspended' : 'active'}">${user.isSuspended == 1 ? 'Suspended' : 'Active'}</span></td>
          <td>
            <button class="btn small edit">Edit</button>
            <button class="btn small ${user.isSuspended == 1 ? 'unsuspend' : 'suspend'}">${user.isSuspended == 1 ? 'Unsuspend' : 'Suspend'}</button>
            <button class="btn small delete">Delete</button>
          </td>
        `;
        table.appendChild(tr);
      });
      attachSuspendHandlers();
    });
}

function attachSuspendHandlers() {

  document.querySelectorAll('.btn.edit').forEach(btn => {
    btn.addEventListener('click', function() {
      const row = this.closest('tr');
      const name = row.querySelector('td:nth-child(2)').textContent;
     
      window.location.href = `profile.php?user=${encodeURIComponent(name)}`;
    });
  });
  
  // Handle delete buttons
  document.querySelectorAll('.btn.delete').forEach(btn => {
    btn.addEventListener('click', function() {
      const row = this.closest('tr');
      const userId = row.querySelector('td:first-child').textContent;
      const name = row.querySelector('td:nth-child(2)').textContent;
      const confirmation = prompt(`Type "${name}" to confirm deletion:\n\nWARNING: This will permanently delete all user data.`);
      if (confirmation === name) {
        // Call backend to delete user
        fetch('php/deleteUser.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: `user_id=${encodeURIComponent(userId)}`
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            row.remove();
            showMessage(`${name} has been deleted`, 'success');
          } else {
            showMessage('Failed to delete user: ' + (data.error || 'Unknown error'), 'error');
          }
        });
      } else if (confirmation !== null) {
        alert('Deletion cancelled - name did not match');
      }
    });
  });
  // Handle suspend buttons
  document.querySelectorAll('.btn.suspend').forEach(btn => {
    btn.addEventListener('click', function() {
      const row = this.closest('tr');
      const userId = row.querySelector('td:first-child').textContent;
      const name = row.querySelector('td:nth-child(2)').textContent;
     
      const reason = prompt(`Suspend ${name}\n\nEnter reason for suspension:`);
      if (reason === null) return;
      let duration = prompt('Enter suspension duration (e.g., 7 days, 1 month, Indefinitely):', 'Indefinitely');
      if (duration === null || duration.trim() === '') duration = 'Indefinitely';
      
      fetch('php/suspendUser.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `user_id=${encodeURIComponent(userId)}&reason=${encodeURIComponent(reason)}&duration=${encodeURIComponent(duration)}`
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          showMessage(`${name} suspended: ${reason} (${duration})`, 'success');
          loadStudentTable();
        } else {
          showMessage('Failed to suspend user: ' + (data.error || 'Unknown error'), 'error');
        }
      });
    });
  });
  
  // Handle unsuspend buttons
  document.querySelectorAll('.btn.unsuspend').forEach(btn => {
    btn.addEventListener('click', function() {
      const row = this.closest('tr');
      const userId = row.querySelector('td:first-child').textContent;
      const name = row.querySelector('td:nth-child(2)').textContent;
      
      if (confirm(`Unsuspend ${name}?\n\nThis will restore their access to the platform.`)) {
        fetch('php/unsuspendUser.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: `user_id=${encodeURIComponent(userId)}`
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            showMessage(`${name} has been unsuspended`, 'success');
            loadStudentTable();
          } else {
            showMessage('Failed to unsuspend user: ' + (data.error || 'Unknown error'), 'error');
          }
        });
      }
    });
  });
}


function showMessage(text, type = 'success') {
 
  const msg = document.createElement('div');
  msg.className = `message ${type}`;
  msg.textContent = text;
  msg.style.position = 'fixed';
  msg.style.top = '80px';
  msg.style.right = '20px';
  msg.style.zIndex = '10000';
  msg.style.minWidth = '300px';
  msg.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
  
  document.body.appendChild(msg);
  

  setTimeout(() => {
    msg.style.opacity = '0';
    msg.style.transition = 'opacity 0.3s';
    setTimeout(() => msg.remove(), 300);
  }, 4000);
}

// Update dashboard stats (from database)
function updateDashboardStats() {
  fetch('php/getAdminStats.php')
    .then(response => response.json())
    .then(data => {
      if (data.error) {
        console.error('Error fetching admin stats:', data.error);
        showMessage('Error loading dashboard stats: ' + data.error, 'error');
        return;
      }
      
  document.getElementById('totalStudents').textContent = data.totalStudents;
  document.getElementById('activeStudents').textContent = data.activeStudents;
  document.getElementById('activeSkills').textContent = data.activeRequests;
  document.getElementById('totalCredits').textContent = data.totalCredits;
    })
    .catch(error => {
      console.error('Error fetching admin stats:', error);
      showMessage('Failed to load dashboard statistics', 'error');
    });
}

// Initialize
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', function() {
    console.log('Admin Dashboard initialized');
    updateDashboardStats();
    loadStudentTable();
  });
} else {
  console.log('Admin Dashboard initialized');
  updateDashboardStats();
  loadStudentTable();
}

// Mobile sidebar toggle (optional)
function toggleMobileSidebar() {
  const sidebar = document.querySelector('.sidebar');
  sidebar.classList.toggle('mobile-active');
}