function makeListEditable(listElement) {
  const items = listElement.querySelectorAll("li");
  const existingSkills = [];
  
  // Collect existing skills (filter out placeholder text)
  items.forEach((li) => {
    const text = li.textContent.trim();
    if (text && li.style.fontStyle !== 'italic') {
      existingSkills.push(text);
    }
  });
  
  // Always provide exactly 3 input fields
  listElement.innerHTML = '';
  for (let i = 0; i < 3; i++) {
    const li = document.createElement("li");
    const input = document.createElement("input");
    input.type = "text";
    input.value = existingSkills[i] || ""; // Use existing skill or empty
    input.placeholder = `Skill ${i + 1}`;
    input.className = "editable-skill-input";
    li.appendChild(input);
    listElement.appendChild(li);
  }
}

function collectEditedSkills(listElement) {
  const inputs = listElement.querySelectorAll("input");
  const skills = [];
  inputs.forEach(input => {
    const trimmedValue = input.value.trim();
    if (trimmedValue !== '') {
      skills.push(trimmedValue);
    }
  });
  return skills;
}

function sendSkillsToBackend(fieldName, skillsArray) {
  const fullUrl = window.location.origin + "/php/update.php";

  const formBody = `${encodeURIComponent(fieldName)}=${encodeURIComponent(JSON.stringify(skillsArray))}`;

  fetch(fullUrl, {
    method: 'POST',
    credentials: 'include',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: formBody
  })
  .then(res => res.text())
  .then(data => {
    console.log("✅ Updated:", data);
    alert("✅ Skills updated!");
  })
  .catch(err => {
    console.error("❌ Error:", err);
    alert("❌ Failed to update skills.");
  });
}

// Dropdown toggle
const dropdownButton = document.getElementById('dropdownButton');
const dropdownContent = document.getElementById('dropdownContent');
dropdownButton.addEventListener('click', () => {
  dropdownContent.style.display =
    dropdownContent.style.display === 'block' ? 'none' : 'block';
});

// Hide dropdown when clicking outside
window.addEventListener('click', e => {
  if (!dropdownButton.contains(e.target) && !dropdownContent.contains(e.target)) {
    dropdownContent.style.display = 'none';
  }
});

// Sidebar toggle for mobile
const menuToggle = document.getElementById('menuToggle');
const sidebar = document.querySelector('.sidebar');
menuToggle.addEventListener('click', () => {
  sidebar.classList.toggle('active');
});

/* settings page */

document.addEventListener("DOMContentLoaded", () => {

  // --- PERSONAL INFO FORM ---
  const personalInfoForm = document.getElementById("personalInfoForm");

if (personalInfoForm) {
  personalInfoForm.addEventListener("submit", async (e) => {
    e.preventDefault();

    const formData = new FormData(personalInfoForm);

    try {
      const response = await fetch("/php/update.php", {
        method: "POST",
        body: formData
      });

      const text = await response.text();
      alert("✅ " + text);
    } catch (error) {
      alert("❌ Failed to update personal information. Please try again.");
      console.error("Error:", error);
    }
  });
}

  // --- PASSWORD CHANGE FORM ---
const passwordForm = document.getElementById("passwordForm");
if (passwordForm) {
  passwordForm.addEventListener("submit", (e) => {
    e.preventDefault();

    const current = document.getElementById("currentPassword").value.trim();
    const newPass = document.getElementById("newPassword").value.trim();
    const confirm = document.getElementById("confirmPassword").value.trim();

    if (!current || !newPass || !confirm) {
      alert("⚠️ Please fill out all password fields.");
      return;
    }

    if (newPass.length < 8) {
      alert("⚠️ New password must be at least 8 characters.");
      return;
    }

    if (newPass !== confirm) {
      alert("❌ New passwords do not match!");
      return;
    }
    
    const fullurl = window.location.origin + "/php/update_password.php";

    // ✅ Send data to PHP via fetch
    fetch(fullurl, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        currentPassword: current,
        newPassword: newPass,
      }),
    })
      .then((res) => res.text())
      .then((response) => {
        alert(response);
        if (response.includes("success")) {
          passwordForm.reset();
        }
      })
      .catch((err) => {
        console.error("Error:", err);
        alert("❌ Something went wrong.");
      });
  });
}

  // --- PREFERENCES FORM ---
  const preferencesForm = document.getElementById("preferencesForm");
  if (preferencesForm) {
    preferencesForm.addEventListener("submit", (e) => {
      e.preventDefault();

      const notifications = document.getElementById("notifications").value;
      const languagePref = document.getElementById("languagePref").value;

      fetch("/php/update_preferences.php", {
  method: "POST",
  headers: {
    "Content-Type": "application/json",
  },
  body: JSON.stringify({
    notifications: notifications,
    language: languagePref,
  }),
})
.then((res) => res.text())
.then((response) => {
  alert(response);

  // Optionally update localStorage too
  localStorage.setItem("user_notifications", notifications);
  localStorage.setItem("user_language", languagePref);

  // update dropdown language immediately
  const langDisplay = document.querySelector("#dropdownButton");
  if (langDisplay) {
    langDisplay.textContent =
      languagePref === "zh" ? "中文 ▼" :
      languagePref === "ja" ? "日本語 ▼" :
      "English ▼";
  }
})
.catch((err) => {
  console.error("Error saving preferences:", err);
  alert("❌ Failed to save preferences.");
});
    });
  }

});

/* FUSS: Editable Profile Page*/
document.addEventListener("DOMContentLoaded", () => {
  // Ensure FUSS flags are available
  setTimeout(() => {
    const isAdmin = (window.FUSS && window.FUSS.isAdmin) || false;
    const isOwner = (window.FUSS && window.FUSS.isOwner) || false;
    
    const editBtn = document.getElementById("editProfileBtn");
    const saveBtn = document.getElementById("saveProfileBtn");
    const changePicBtn = document.getElementById("changePicBtn");
    const profilePicInput = document.getElementById("profilePicInput");
    const profilePicForm = document.getElementById("profilePicForm");
    const profilePic = document.getElementById("profilePic");
    const editableFields = [
      { id: "profileName", label: "Full Name" },
      { id: "profileDegree", label: "Degree" },
      { id: "profileCollege", label: "College" },
      { id: "profileYear", label: "Year" },
      { id: "profileBio", label: "Bio" }
    ];

    // Always show edit buttons for admins and owners
    if (editBtn) editBtn.style.display = (isAdmin || isOwner) ? "inline-block" : "none";
    if (saveBtn) saveBtn.style.display = "none";
    if (changePicBtn) changePicBtn.style.display = (isAdmin || isOwner) ? "inline-block" : "none";

    // ---- ENABLE EDIT MODE ----
    if (editBtn) {
      editBtn.addEventListener("click", () => {
        editableFields.forEach(field => {
          const span = document.getElementById(field.id); 
          if (!span) return;
          const value = span.textContent;
          const input = document.createElement("input");
          input.type = "text";
          input.value = value;
          input.className = "editable-input";
          span.replaceWith(input);
          input.id = field.id; // keep same id
        });
        editBtn.style.display = "none";
        if (saveBtn) saveBtn.style.display = "inline-block";
      });
    }
    // ---- SAVE CHANGES ----
    if (saveBtn) {
      saveBtn.addEventListener("click", () => {
        const data = {};
        editableFields.forEach(field => {
          const input = document.getElementById(field.id);
          if (input) {
            data[field.id] = input.value.trim();
          }
        });
        const formBody = Object.entries(data)
          .map(([key, val]) => `${encodeURIComponent(key)}=${encodeURIComponent(val)}`)
          .join('&');
        const fullurl = window.location.origin + "/php/update.php";
        fetch(fullurl, {
          method: 'POST',
          credentials: 'include',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: formBody,
        })
        .then(response => response.text())
        .then(data => {
          editableFields.forEach(field => {
            const input = document.getElementById(field.id);
            if (input) {
              const span = document.createElement("span");
              span.id = field.id;
              span.textContent = input.value.trim();
              input.replaceWith(span);
            }
          });
          alert("✅ Profile information updated!");
          saveBtn.style.display = "none";
          if (editBtn) editBtn.style.display = "inline-block";
        })
        .catch(error => {
          console.error('Error:', error);
          alert("❌ Failed to update profile.");
        });
      });
    }
    // ---- CHANGE PROFILE PICTURE ----
    if (changePicBtn && profilePicInput && profilePicForm) {
      changePicBtn.addEventListener("click", () => profilePicInput.click());
      profilePicInput.addEventListener("change", async (event) => {
        const file = event.target.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = e => { profilePic.src = e.target.result; };
        reader.readAsDataURL(file);
        const formData = new FormData();
        formData.append('profile_image', file);
        try {
          const resp = await fetch('/php/upload_profile_image.php', {
            method: 'POST',
            credentials: 'include',
            body: formData
          });
          const data = await resp.json().catch(() => ({}));
          if (!resp.ok || !data.success) {
            alert(data.error || '❌ Failed to upload image.');
            return;
          }
          if (data.path) {
            profilePic.src = data.path + `?t=${Date.now()}`;
          }
          alert('✅ Profile picture updated!');
        } catch (err) {
          console.error(err);
          alert('❌ Failed to upload image.');
        }
      });
    }

    // -- Direct Request Button on Profile Page --
    const directRequestBtn = document.getElementById("directRequestBtn");

    if (directRequestBtn) {
      // Show button only if user is not the owner and viewed user is not an admin
      const viewedUserIsAdmin = window.FUSS && window.FUSS.viewedUserIsAdmin;
      if (!isOwner && !viewedUserIsAdmin) {
        directRequestBtn.style.display = "inline-block";
      }

      // Add click event to redirect to request page with direct params
      directRequestBtn.addEventListener("click", () => {
        const viewed = (window.FUSS && window.FUSS.viewedUsername) ? encodeURIComponent(window.FUSS.viewedUsername) : '';
        const url = viewed ? `request.php?isdirect=1&username=${viewed}` : 'request.php?isdirect=1';
        window.location.href = url;
      });
    }

    // -- Message Button on Profile Page --
    const messageBtn = document.getElementById("messageBtn");

    if (messageBtn) {
      // Show button only if user is not the owner
      if (!isOwner) {
        messageBtn.style.display = "inline-block";
      }

      // Add click event to redirect to messages page with username parameter
      messageBtn.addEventListener("click", () => {
        const urlParams = new URLSearchParams(window.location.search);
        const profileUsername = urlParams.get('user') || 'currentUser';
        window.location.href = `messages.php?user=${encodeURIComponent(profileUsername)}`;
      });
    }

    // -- Remove Review Buttons (Admin Only) --
    const removeReviewBtns = document.querySelectorAll(".remove-review-btn");

    if (removeReviewBtns.length > 0 && isAdmin) {
      // Show remove buttons only for admins
      removeReviewBtns.forEach(btn => {
        btn.style.display = "inline-block";
        
        // Add click event to remove the review
        btn.addEventListener("click", (e) => {
          const confirmed = confirm("Are you sure you want to remove this review?");
          if (confirmed) {
            const reviewCard = e.target.closest(".review-card");
            if (reviewCard) {
              reviewCard.remove();
              alert("Review removed successfully.");
            }
          }
        });
      });
    }

    // -- Delete User Button (Admin Only) --
    const deleteUserBtn = document.getElementById("deleteUserBtn");

    if (deleteUserBtn && isAdmin && !isOwner) {
      deleteUserBtn.addEventListener("click", () => {
        const confirmed = confirm("⚠️ WARNING: This will permanently delete this user account and all associated data. This action cannot be undone.\n\nAre you absolutely sure you want to delete this user?");
        if (confirmed) {
          const doubleConfirmed = confirm("This is your final confirmation. Delete this user account permanently?");
          if (doubleConfirmed) {
            // Here you would typically send a request to your backend to delete the user
            alert("User deletion functionality would be implemented here.");
            // Example:
            // fetch('/php/delete_user.php', { method: 'POST', ... })
            // .then(() => window.location.href = 'admin_panel.php');
          }
        }
      });
      // Show delete button only for admins viewing other users
      deleteUserBtn.style.display = "inline-block";
    }

    // -- Skills Offered --
    const editSkillOfferedBtn = document.getElementById("editSkillOfferedBtn");
    const confirmSkillOfferedBtn = document.getElementById("confirmSkillOfferedBtn");
    const skillsOffered = document.getElementById("skillsOffered");

    if (editSkillOfferedBtn && confirmSkillOfferedBtn && skillsOffered) {
      // Show skill edit buttons for admins and owners
      editSkillOfferedBtn.style.display = (isAdmin || isOwner) ? "inline-block" : "none";
      
      editSkillOfferedBtn.addEventListener("click", () => {
        makeListEditable(skillsOffered);
        confirmSkillOfferedBtn.style.display = "inline-block";
        editSkillOfferedBtn.style.display = "none";
      });

      confirmSkillOfferedBtn.addEventListener("click", () => {
        const updatedSkills = collectEditedSkills(skillsOffered);

        // Replace inputs with updated text - always show 3 slots
        skillsOffered.innerHTML = "";
        
        for (let i = 0; i < 3; i++) {
          const li = document.createElement("li");
          if (i < updatedSkills.length && updatedSkills[i].trim()) {
            // Show actual skill
            li.textContent = updatedSkills[i];
          } else {
            // Show empty slot
            li.style.color = "#6b7280";
            li.style.fontStyle = "italic";
            li.textContent = "- Empty Skill Slot -";
          }
          skillsOffered.appendChild(li);
        }

        sendSkillsToBackend("skills_offered", updatedSkills);

        confirmSkillOfferedBtn.style.display = "none";
        editSkillOfferedBtn.style.display = "inline-block";
      });
    }

    // -- Skills Requested --
    const editSkillRequestedBtn = document.getElementById("editSkillRequestedBtn");
    const confirmSkillRequestedBtn = document.getElementById("confirmSkillRequestedBtn");
    const skillsRequested = document.getElementById("skillsRequested");

    if (editSkillRequestedBtn && confirmSkillRequestedBtn && skillsRequested) {
      // Show skill edit buttons for admins and owners
      editSkillRequestedBtn.style.display = (isAdmin || isOwner) ? "inline-block" : "none";
      
      editSkillRequestedBtn.addEventListener("click", () => {
        makeListEditable(skillsRequested);
        confirmSkillRequestedBtn.style.display = "inline-block";
        editSkillRequestedBtn.style.display = "none";
      });

      confirmSkillRequestedBtn.addEventListener("click", () => {
        const updatedSkills = collectEditedSkills(skillsRequested);

        // Replace inputs with updated text
        skillsRequested.innerHTML = "";
        updatedSkills.forEach(skill => {
          const li = document.createElement("li");
          li.textContent = skill;
          skillsRequested.appendChild(li);
        });

        sendSkillsToBackend("skills_requested", updatedSkills);

        confirmSkillRequestedBtn.style.display = "none";
        editSkillRequestedBtn.style.display = "inline-block";
      });
    }
  }, 0); // Ensure FUSS is set
});

// Skills Requested filtering functions (independent of admin/owner status)
const skillsRequestedSection = document.getElementById('skillsRequestedSection');

// Ensure the Skills Requested section only shows when there are public requests
async function filterSkillsRequestedAgainstPublicRequests() {
  const skillsRequested = document.getElementById("skillsRequested");
  if (!skillsRequestedSection || !skillsRequested) return;
  try {
    const res = await fetch('/php/getRequests.php', { credentials: 'include' });
    const list = await res.json();
    const viewedUser = (window.FUSS && window.FUSS.viewedUsername) || '';
    const titles = new Set(
      (Array.isArray(list) ? list : [])
        .filter(r => r && (r.username === viewedUser))
        .map(r => String(r.title || '').trim().toLowerCase())
    );
    // Hide skills that don't have a matching public request title; show those that do
    const skillLis = skillsRequested.querySelectorAll('li[data-skill]');
    if (skillLis.length === 0) {
      // No requested skills present at all: hide section
      skillsRequestedSection.style.display = 'none';
      return;
    }
    skillLis.forEach(li => {
      const key = (li.getAttribute('data-skill') || '').trim().toLowerCase();
      li.style.display = titles.has(key) ? '' : 'none';
    });
    // Hide the entire section if nothing visible
    const anyVisible = !!skillsRequested.querySelector('li[data-skill]:not([style*="display: none"])');
    skillsRequestedSection.style.display = anyVisible ? '' : 'none';
  } catch (e) {
    // On failure, do a conservative check: hide if no data-skill items
    const skillsRequested = document.getElementById("skillsRequested");
    const anyVisible = !!(skillsRequested && skillsRequested.querySelector('li[data-skill]'));
    skillsRequestedSection.style.display = anyVisible ? '' : 'none';
  }
}

// Re-evaluate when requests are removed/relisted elsewhere
window.addEventListener('request:remove', () => filterSkillsRequestedAgainstPublicRequests());
window.addEventListener('request:relist', () => filterSkillsRequestedAgainstPublicRequests());

// Helpers for mapping request id to requested skill <li>
function findRequestedSkillLi(skillTitle) {
  const skillsRequested = document.getElementById("skillsRequested");
  if (!skillsRequested) return null;
  const key = String(skillTitle || '').trim().toLowerCase();
  if (!key) return null;
  return skillsRequested.querySelector(`li[data-skill="${CSS.escape(key)}"]`);
}

async function getRequestInfoById(id) {
  try {
    const res = await fetch(`/php/getRequestInfo.php?id=${encodeURIComponent(id)}`, { credentials: 'include' });
    const data = await res.json();
    if (data && data.id && data.title) return data;
  } catch (e) { /* ignore */ }
  return null;
}

async function handleRequestRemove(requestId) {
  const info = await getRequestInfoById(requestId);
  if (!info) return;
  // Only act if we're viewing the request owner's profile
  if ((window.FUSS && window.FUSS.viewedUserId) !== info.user_id) return;
  const li = findRequestedSkillLi(info.title);
  if (li) li.style.display = 'none';
  // Hide the entire section if none visible
  filterSkillsRequestedAgainstPublicRequests();
}

async function handleRequestRelist(requestId) {
  const info = await getRequestInfoById(requestId);
  if (!info) return;
  if ((window.FUSS && window.FUSS.viewedUserId) !== info.user_id) return;
  const li = findRequestedSkillLi(info.title);
  if (li) li.style.display = '';
  filterSkillsRequestedAgainstPublicRequests();
}

// Initial evaluation of Skills Requested visibility on page load
document.addEventListener('DOMContentLoaded', () => {
  filterSkillsRequestedAgainstPublicRequests && filterSkillsRequestedAgainstPublicRequests();
  // Wire cross-page events if they arrive after load
  window.addEventListener('request:remove', (e) => {
    if (e && e.detail && e.detail.requestId) handleRequestRemove(e.detail.requestId);
  });
  window.addEventListener('request:relist', (e) => {
    if (e && e.detail && e.detail.requestId) handleRequestRelist(e.detail.requestId);
  });
});