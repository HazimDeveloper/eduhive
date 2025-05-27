<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>EduHive - Team Members</title>
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="team-members.css">
</head>
<body class="dashboard-body">
  <div class="dashboard-container">
    <!-- Sidebar Navigation -->
    <nav class="sidebar">
      <div class="sidebar-header">
        <div class="sidebar-logo">
          <div class="logo-circle-small">
            <div class="graduation-cap-small">🎓</div>
            <div class="location-pin-small">📍</div>
          </div>
        </div>
        <h2>EduHive</h2>
      </div>
      
      <ul class="nav-menu">
        <li class="nav-item">
          <a href="dashboard.html">Dashboard</a>
        </li>
        <li class="nav-item">
          <a href="calendar.html">Calendar</a>
        </li>
        <li class="nav-item">
          <a href="schedule.html">Class Schedules</a>
        </li>
        <li class="nav-item">
          <a href="tasks.html">Task</a>
        </li>
        <li class="nav-item">
          <a href="record-time.html">Record Time</a>
        </li>
        <li class="nav-item">
          <a href="rewards.html">Reward</a>
        </li>
        <li class="nav-item active">
          <a href="#team">Team Members</a>
        </li>
      </ul>
    </nav>

    <!-- Main Team Members Content -->
    <main class="team-main">
      <div class="team-header">
        <h1>Team Members</h1>
        <div class="header-actions">
          <span class="user-name">NUR KHALIDA BINTI NAZERI ></span>
          <button class="add-members-btn" id="addMembersBtn">+ Add Members</button>
        </div>
      </div>
      
      <!-- Table Header -->
      <div class="table-header">
        <div class="column-header name-column">Name</div>
        <div class="column-header date-column">Date Added</div>
        <div class="column-header active-column">Last Active</div>
        <div class="column-header actions-column"></div>
      </div>
      
      <!-- Team Groups -->
      <div class="team-groups">
        <!-- FYP Group -->
        <div class="team-group">
          <h2 class="group-title">FYP Group Members</h2>
          <div class="members-list">
            <div class="member-row">
              <div class="member-info">
                <div class="member-avatar">
                  <img src="https://images.unsplash.com/photo-1494790108755-2616b612b562?w=60&h=60&fit=crop&crop=face" alt="Olivia Rhye" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                  <div class="avatar-placeholder" style="display: none;">OR</div>
                </div>
                <div class="member-details">
                  <div class="member-name">Olivia Rhye</div>
                  <div class="member-email">olivia@email.com</div>
                </div>
              </div>
              <div class="member-date">Oct 22, 2024</div>
              <div class="member-active">Jan 5, 2025</div>
              <div class="member-actions">
                <button class="action-btn delete-btn" onclick="deleteMember(this)">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="3,6 5,6 21,6"/>
                    <path d="M19,6V20a2,2,0,0,1-2,2H7a2,2,0,0,1-2-2V6M8,6V4a2,2,0,0,1,2-2h4a2,2,0,0,1,2,2V6"/>
                    <line x1="10" y1="11" x2="10" y2="17"/>
                    <line x1="14" y1="11" x2="14" y2="17"/>
                  </svg>
                </button>
                <button class="action-btn edit-btn" onclick="editMember(this)">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                  </svg>
                </button>
              </div>
            </div>
            
            <div class="member-row">
              <div class="member-info">
                <div class="member-avatar">
                  <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=60&h=60&fit=crop&crop=face" alt="Shawn Mendes" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                  <div class="avatar-placeholder" style="display: none;">SM</div>
                </div>
                <div class="member-details">
                  <div class="member-name">Shawn Mendes</div>
                  <div class="member-email">shawn@email.com</div>
                </div>
              </div>
              <div class="member-date">Oct 22, 2024</div>
              <div class="member-active">Jan 5, 2025</div>
              <div class="member-actions">
                <button class="action-btn delete-btn" onclick="deleteMember(this)">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="3,6 5,6 21,6"/>
                    <path d="M19,6V20a2,2,0,0,1-2,2H7a2,2,0,0,1-2-2V6M8,6V4a2,2,0,0,1,2-2h4a2,2,0,0,1,2,2V6"/>
                    <line x1="10" y1="11" x2="10" y2="17"/>
                    <line x1="14" y1="11" x2="14" y2="17"/>
                  </svg>
                </button>
                <button class="action-btn edit-btn" onclick="editMember(this)">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                  </svg>
                </button>
              </div>
            </div>
            
            <div class="member-row">
              <div class="member-info">
                <div class="member-avatar">
                  <img src="https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=60&h=60&fit=crop&crop=face" alt="Taylor Swift" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                  <div class="avatar-placeholder" style="display: none;">TS</div>
                </div>
                <div class="member-details">
                  <div class="member-name">Taylor Swift</div>
                  <div class="member-email">taylor@email.com</div>
                </div>
              </div>
              <div class="member-date">Oct 22, 2024</div>
              <div class="member-active">Jan 5, 2025</div>
              <div class="member-actions">
                <button class="action-btn delete-btn" onclick="deleteMember(this)">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="3,6 5,6 21,6"/>
                    <path d="M19,6V20a2,2,0,0,1-2,2H7a2,2,0,0,1-2-2V6M8,6V4a2,2,0,0,1,2-2h4a2,2,0,0,1,2,2V6"/>
                    <line x1="10" y1="11" x2="10" y2="17"/>
                    <line x1="14" y1="11" x2="14" y2="17"/>
                  </svg>
                </button>
                <button class="action-btn edit-btn" onclick="editMember(this)">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                  </svg>
                </button>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Programming Group -->
        <div class="team-group">
          <h2 class="group-title">Programming Group Members</h2>
          <div class="members-list">
            <div class="member-row">
              <div class="member-info">
                <div class="member-avatar">
                  <img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=60&h=60&fit=crop&crop=face" alt="Justin Tims" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                  <div class="avatar-placeholder" style="display: none;">JT</div>
                </div>
                <div class="member-details">
                  <div class="member-name">Justin Tims</div>
                  <div class="member-email">justin@email.com</div>
                </div>
              </div>
              <div class="member-date">Oct 22, 2024</div>
              <div class="member-active">Jan 3, 2025</div>
              <div class="member-actions">
                <button class="action-btn delete-btn" onclick="deleteMember(this)">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="3,6 5,6 21,6"/>
                    <path d="M19,6V20a2,2,0,0,1-2,2H7a2,2,0,0,1-2-2V6M8,6V4a2,2,0,0,1,2-2h4a2,2,0,0,1,2,2V6"/>
                    <line x1="10" y1="11" x2="10" y2="17"/>
                    <line x1="14" y1="11" x2="14" y2="17"/>
                  </svg>
                </button>
                <button class="action-btn edit-btn" onclick="editMember(this)">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                  </svg>
                </button>
              </div>
            </div>
            
            <div class="member-row">
              <div class="member-info">
                <div class="member-avatar">
                  <img src="https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=60&h=60&fit=crop&crop=face" alt="Candice Wo" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                  <div class="avatar-placeholder" style="display: none;">CW</div>
                </div>
                <div class="member-details">
                  <div class="member-name">Candice Wo</div>
                  <div class="member-email">candice@email.com</div>
                </div>
              </div>
              <div class="member-date">Oct 22, 2024</div>
              <div class="member-active">Jan 3, 2025</div>
              <div class="member-actions">
                <button class="action-btn delete-btn" onclick="deleteMember(this)">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="3,6 5,6 21,6"/>
                    <path d="M19,6V20a2,2,0,0,1-2,2H7a2,2,0,0,1-2-2V6M8,6V4a2,2,0,0,1,2-2h4a2,2,0,0,1,2,2V6"/>
                    <line x1="10" y1="11" x2="10" y2="17"/>
                    <line x1="14" y1="11" x2="14" y2="17"/>
                  </svg>
                </button>
                <button class="action-btn edit-btn" onclick="editMember(this)">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                  </svg>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <!-- Add Member Modal -->
  <div id="addMemberModal" class="modal" style="display: none;">
    <div class="modal-content">
      <span class="close">&times;</span>
      <h3>Add New Team Member</h3>
      <form id="addMemberForm">
        <input type="text" id="memberName" placeholder="Full Name" required>
        <input type="email" id="memberEmail" placeholder="Email Address" required>
        <select id="memberGroup" required>
          <option value="">Select Group</option>
          <option value="FYP">FYP Group</option>
          <option value="Programming">Programming Group</option>
          <option value="Other">Other</option>
        </select>
        <input type="text" id="memberRole" placeholder="Role/Position (Optional)">
        <div class="form-buttons">
          <button type="submit">Add Member</button>
          <button type="button" id="cancelAddMember">Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Edit Member Modal -->
  <div id="editMemberModal" class="modal" style="display: none;">
    <div class="modal-content">
      <span class="close">&times;</span>
      <h3>Edit Team Member</h3>
      <form id="editMemberForm">
        <input type="text" id="editMemberName" placeholder="Full Name" required>
        <input type="email" id="editMemberEmail" placeholder="Email Address" required>
        <select id="editMemberGroup" required>
          <option value="">Select Group</option>
          <option value="FYP">FYP Group</option>
          <option value="Programming">Programming Group</option>
          <option value="Other">Other</option>
        </select>
        <input type="text" id="editMemberRole" placeholder="Role/Position (Optional)">
        <div class="form-buttons">
          <button type="submit">Update Member</button>
          <button type="button" id="cancelEditMember">Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    let currentEditingMember = null;

    document.addEventListener('DOMContentLoaded', function() {
      const addMemberBtn = document.getElementById('addMembersBtn');
      const addMemberModal = document.getElementById('addMemberModal');
      const editMemberModal = document.getElementById('editMemberModal');
      const closeModals = document.querySelectorAll('.close');
      const cancelBtns = document.querySelectorAll('#cancelAddMember, #cancelEditMember');

      // Modal event listeners
      addMemberBtn.addEventListener('click', () => {
        addMemberModal.style.display = 'block';
      });

      closeModals.forEach(close => {
        close.addEventListener('click', () => {
          addMemberModal.style.display = 'none';
          editMemberModal.style.display = 'none';
        });
      });

      cancelBtns.forEach(cancel => {
        cancel.addEventListener('click', () => {
          addMemberModal.style.display = 'none';
          editMemberModal.style.display = 'none';
        });
      });

      window.addEventListener('click', (event) => {
        if (event.target === addMemberModal) {
          addMemberModal.style.display = 'none';
        }
        if (event.target === editMemberModal) {
          editMemberModal.style.display = 'none';
        }
      });

      // Add member form submission
      document.getElementById('addMemberForm').addEventListener('submit', (e) => {
        e.preventDefault();
        
        const name = document.getElementById('memberName').value;
        const email = document.getElementById('memberEmail').value;
        const group = document.getElementById('memberGroup').value;
        const role = document.getElementById('memberRole').value;

        addNewMember(name, email, group, role);
        
        addMemberModal.style.display = 'none';
        document.getElementById('addMemberForm').reset();
        
        showNotification('Team member added successfully!');
      });

      // Edit member form submission
      document.getElementById('editMemberForm').addEventListener('submit', (e) => {
        e.preventDefault();
        
        if (currentEditingMember) {
          const name = document.getElementById('editMemberName').value;
          const email = document.getElementById('editMemberEmail').value;
          const group = document.getElementById('editMemberGroup').value;
          const role = document.getElementById('editMemberRole').value;

          updateMember(currentEditingMember, name, email, group, role);
          
          editMemberModal.style.display = 'none';
          currentEditingMember = null;
          
          showNotification('Team member updated successfully!');
        }
      });
    });

    function addNewMember(name, email, group, role) {
      const groupSection = findGroupSection(group);
      if (!groupSection) return;

      const membersList = groupSection.querySelector('.members-list');
      const currentDate = new Date().toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      });

      const initials = name.split(' ').map(n => n[0]).join('').toUpperCase();
      
      const memberRow = document.createElement('div');
      memberRow.className = 'member-row';
      memberRow.innerHTML = `
        <div class="member-info">
          <div class="member-avatar">
            <div class="avatar-placeholder">${initials}</div>
          </div>
          <div class="member-details">
            <div class="member-name">${name}</div>
            <div class="member-email">${email}</div>
          </div>
        </div>
        <div class="member-date">${currentDate}</div>
        <div class="member-active">Today</div>
        <div class="member-actions">
          <button class="action-btn delete-btn" onclick="deleteMember(this)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="3,6 5,6 21,6"/>
              <path d="M19,6V20a2,2,0,0,1-2,2H7a2,2,0,0,1-2-2V6M8,6V4a2,2,0,0,1,2-2h4a2,2,0,0,1,2,2V6"/>
              <line x1="10" y1="11" x2="10" y2="17"/>
              <line x1="14" y1="11" x2="14" y2="17"/>
            </svg>
          </button>
          <button class="action-btn edit-btn" onclick="editMember(this)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
              <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
            </svg>
          </button>
        </div>
      `;

      membersList.appendChild(memberRow);
      
      // Add entrance animation
      memberRow.style.opacity = '0';
      memberRow.style.transform = 'translateY(20px)';
      setTimeout(() => {
        memberRow.style.transition = 'all 0.3s ease';
        memberRow.style.opacity = '1';
        memberRow.style.transform = 'translateY(0)';
      }, 100);
    }

    function findGroupSection(groupName) {
      const groupTitles = document.querySelectorAll('.group-title');
      for (let title of groupTitles) {
        if (title.textContent.includes(groupName)) {
          return title.parentElement;
        }
      }
      
      // If group doesn't exist, create it
      if (groupName === 'Other') {
        return createNewGroup('Other Group Members');
      }
      
      return null;
    }

    function createNewGroup(groupTitle) {
      const teamGroups = document.querySelector('.team-groups');
      const newGroup = document.createElement('div');
      newGroup.className = 'team-group';
      newGroup.innerHTML = `
        <h2 class="group-title">${groupTitle}</h2>
        <div class="members-list"></div>
      `;
      teamGroups.appendChild(newGroup);
      return newGroup;
    }

    function deleteMember(button) {
      if (confirm('Are you sure you want to remove this team member?')) {
        const memberRow = button.closest('.member-row');
        const memberName = memberRow.querySelector('.member-name').textContent;
        
        memberRow.style.transition = 'all 0.3s ease';
        memberRow.style.opacity = '0';
        memberRow.style.transform = 'translateX(-20px)';
        
        setTimeout(() => {
          memberRow.remove();
          showNotification(`${memberName} has been removed from the team.`);
        }, 300);
      }
    }

    function editMember(button) {
      const memberRow = button.closest('.member-row');
      const name = memberRow.querySelector('.member-name').textContent;
      const email = memberRow.querySelector('.member-email').textContent;
      
      // Pre-fill the edit form
      document.getElementById('editMemberName').value = name;
      document.getElementById('editMemberEmail').value = email;
      
      // Determine which group this member belongs to
      const groupTitle = memberRow.closest('.team-group').querySelector('.group-title').textContent;
      if (groupTitle.includes('FYP')) {
        document.getElementById('editMemberGroup').value = 'FYP';
      } else if (groupTitle.includes('Programming')) {
        document.getElementById('editMemberGroup').value = 'Programming';
      } else {
        document.getElementById('editMemberGroup').value = 'Other';
      }
      
      currentEditingMember = memberRow;
      document.getElementById('editMemberModal').style.display = 'block';
    }

    function updateMember(memberRow, name, email, group, role) {
      memberRow.querySelector('.member-name').textContent = name;
      memberRow.querySelector('.member-email').textContent = email;
      
      // Update initials in avatar
      const initials = name.split(' ').map(n => n[0]).join('').toUpperCase();
      const avatarPlaceholder = memberRow.querySelector('.avatar-placeholder');
      if (avatarPlaceholder) {
        avatarPlaceholder.textContent = initials;
      }
      
      // If group changed, move member to new group
      const currentGroup = memberRow.closest('.team-group').querySelector('.group-title').textContent;
      const targetGroupName = group === 'FYP' ? 'FYP' : group === 'Programming' ? 'Programming' : 'Other';
      
      if (!currentGroup.includes(targetGroupName)) {
        const targetGroup = findGroupSection(group);
        if (targetGroup) {
          memberRow.remove();
          targetGroup.querySelector('.members-list').appendChild(memberRow);
        }
      }
    }

    function showNotification(message) {
      const notification = document.createElement('div');
      notification.className = 'notification';
      notification.textContent = message;
      document.body.appendChild(notification);
      
      setTimeout(() => {
        notification.classList.add('show');
      }, 100);
      
      setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
          document.body.removeChild(notification);
        }, 300);
      }, 3000);
    }

    // Add some interactivity to member rows
    document.querySelectorAll('.member-row').forEach(row => {
      row.addEventListener('mouseenter', () => {
        row.style.transform = 'translateX(5px)';
      });
      
      row.addEventListener('mouseleave', () => {
        row.style.transform = 'translateX(0)';
      });
    });
  </script>
</body>
</html>