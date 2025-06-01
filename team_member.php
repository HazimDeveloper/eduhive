<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'config/functions.php';

// Ensure user is logged in
requireLogin();

// Get current user data
$user_id = getCurrentUserId();
$user_name = getCurrentUserName() ?: 'User';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'add_member':
            $member_data = [
                'name' => $_POST['name'] ?? '',
                'email' => $_POST['email'] ?? '',
                'role' => $_POST['role'] ?? '',
                'group_name' => $_POST['group_name'] ?? ''
            ];
            
            if (empty($member_data['name']) || empty($member_data['email']) || empty($member_data['group_name'])) {
                echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
                exit();
            }
            
            if (!isValidEmail($member_data['email'])) {
                echo json_encode(['success' => false, 'message' => 'Please enter a valid email address']);
                exit();
            }
            
            $member_id = addTeamMember($user_id, $member_data);
            if ($member_id) {
                $member = getTeamMember($member_id, $user_id);
                echo json_encode(['success' => true, 'message' => 'Team member added successfully', 'member' => $member]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to add team member. Email might already exist.']);
            }
            exit();
            
        case 'update_member':
            $member_id = (int)$_POST['member_id'];
            $member_data = [
                'name' => $_POST['name'] ?? '',
                'email' => $_POST['email'] ?? '',
                'role' => $_POST['role'] ?? '',
                'group_name' => $_POST['group_name'] ?? ''
            ];
            
            if (empty($member_data['name']) || empty($member_data['email']) || empty($member_data['group_name'])) {
                echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
                exit();
            }
            
            if (!isValidEmail($member_data['email'])) {
                echo json_encode(['success' => false, 'message' => 'Please enter a valid email address']);
                exit();
            }
            
            $success = updateTeamMember($member_id, $user_id, $member_data);
            if ($success) {
                $member = getTeamMember($member_id, $user_id);
                echo json_encode(['success' => true, 'message' => 'Team member updated successfully', 'member' => $member]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update team member. Email might already exist.']);
            }
            exit();
            
        case 'delete_member':
            $member_id = (int)$_POST['member_id'];
            $success = deleteTeamMember($member_id, $user_id);
            
            if ($success) {
                echo json_encode(['success' => true, 'message' => 'Team member removed successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to remove team member']);
            }
            exit();
            
        case 'get_member':
            $member_id = (int)$_POST['member_id'];
            $member = getTeamMember($member_id, $user_id);
            
            if ($member) {
                echo json_encode(['success' => true, 'member' => $member]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Team member not found']);
            }
            exit();
    }
    
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit();
}

// Get team members grouped by group name
$team_members = getTeamMembersGrouped($user_id);
$team_stats = getTeamMemberStats($user_id);

// Group mapping for display
$group_titles = [
    'FYP' => 'FYP Group Members',
    'Programming' => 'Programming Group Members',
    'Other' => 'Other Group Members'
];
?>
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
          <a href="dashboard.php">Dashboard</a>
        </li>
        <li class="nav-item">
          <a href="calendar.php">Calendar</a>
        </li>
        <li class="nav-item">
          <a href="class_schedule.php">Class Schedules</a>
        </li>
        <li class="nav-item">
          <a href="task.php">Task</a>
        </li>
        <li class="nav-item">
          <a href="record_time.php">Record Time</a>
        </li>
        <li class="nav-item">
          <a href="reward.php">Reward</a>
        </li>
        <li class="nav-item active">
          <a href="team_member.php">Team Members</a>
        </li>
      </ul>
    </nav>

    <!-- Main Team Members Content -->
    <main class="team-main">
      <div class="team-header">
        <h1>Team Members</h1>
        <div class="header-actions">
          <span class="user-name"><?php echo htmlspecialchars($user_name); ?> ></span>
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
        <?php if (empty($team_members)): ?>
          <!-- Show default empty groups -->
          <div class="team-group">
            <h2 class="group-title">FYP Group Members</h2>
            <div class="members-list" data-group="FYP">
              <!-- Empty state will be shown by CSS -->
            </div>
          </div>
          
          <div class="team-group">
            <h2 class="group-title">Programming Group Members</h2>
            <div class="members-list" data-group="Programming">
              <!-- Empty state will be shown by CSS -->
            </div>
          </div>
        <?php else: ?>
          <?php foreach ($team_members as $group_name => $members): ?>
          <div class="team-group">
            <h2 class="group-title"><?php echo htmlspecialchars($group_titles[$group_name] ?? $group_name . ' Members'); ?></h2>
            <div class="members-list" data-group="<?php echo htmlspecialchars($group_name); ?>">
              <?php foreach ($members as $member): ?>
              <div class="member-row" data-member-id="<?php echo $member['id']; ?>">
                <div class="member-info">
                  <div class="member-avatar">
                    <div class="avatar-placeholder"><?php echo htmlspecialchars(getUserInitials($member['name'])); ?></div>
                  </div>
                  <div class="member-details">
                    <div class="member-name"><?php echo htmlspecialchars($member['name']); ?></div>
                    <div class="member-email"><?php echo htmlspecialchars($member['email']); ?></div>
                  </div>
                </div>
                <div class="member-date"><?php echo formatMemberDate($member['created_at']); ?></div>
                <div class="member-active"><?php echo formatMemberDate($member['created_at']); ?></div>
                <div class="member-actions">
                  <button class="action-btn delete-btn" onclick="deleteMember(<?php echo $member['id']; ?>)">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <polyline points="3,6 5,6 21,6"/>
                      <path d="M19,6V20a2,2,0,0,1-2,2H7a2,2,0,0,1-2-2V6M8,6V4a2,2,0,0,1,2-2h4a2,2,0,0,1,2,2V6"/>
                      <line x1="10" y1="11" x2="10" y2="17"/>
                      <line x1="14" y1="11" x2="14" y2="17"/>
                    </svg>
                  </button>
                  <button class="action-btn edit-btn" onclick="editMember(<?php echo $member['id']; ?>)">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                      <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                  </button>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
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
        <input type="hidden" id="editMemberId">
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
    let currentEditingMemberId = null;

    document.addEventListener('DOMContentLoaded', function() {
      initializeEventListeners();
    });

    function initializeEventListeners() {
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
      document.getElementById('addMemberForm').addEventListener('submit', handleAddMember);

      // Edit member form submission
      document.getElementById('editMemberForm').addEventListener('submit', handleEditMember);
    }

    function handleAddMember(e) {
      e.preventDefault();
      
      const formData = new FormData();
      formData.append('action', 'add_member');
      formData.append('name', document.getElementById('memberName').value);
      formData.append('email', document.getElementById('memberEmail').value);
      formData.append('group_name', document.getElementById('memberGroup').value);
      formData.append('role', document.getElementById('memberRole').value);

      fetch('team_member.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          addMemberToUI(data.member);
          document.getElementById('addMemberModal').style.display = 'none';
          document.getElementById('addMemberForm').reset();
          showNotification(data.message);
        } else {
          showNotification(data.message, 'error');
        }
      })
      .catch(error => {
        console.error('Error adding member:', error);
        showNotification('Error adding team member', 'error');
      });
    }

    function handleEditMember(e) {
      e.preventDefault();
      
      const formData = new FormData();
      formData.append('action', 'update_member');
      formData.append('member_id', document.getElementById('editMemberId').value);
      formData.append('name', document.getElementById('editMemberName').value);
      formData.append('email', document.getElementById('editMemberEmail').value);
      formData.append('group_name', document.getElementById('editMemberGroup').value);
      formData.append('role', document.getElementById('editMemberRole').value);

      fetch('team_member.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          updateMemberInUI(data.member);
          document.getElementById('editMemberModal').style.display = 'none';
          showNotification(data.message);
        } else {
          showNotification(data.message, 'error');
        }
      })
      .catch(error => {
        console.error('Error updating member:', error);
        showNotification('Error updating team member', 'error');
      });
    }

    function deleteMember(memberId) {
      if (!confirm('Are you sure you want to remove this team member?')) {
        return;
      }

      const formData = new FormData();
      formData.append('action', 'delete_member');
      formData.append('member_id', memberId);

      fetch('team_member.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          removeMemberFromUI(memberId);
          showNotification(data.message);
        } else {
          showNotification(data.message, 'error');
        }
      })
      .catch(error => {
        console.error('Error deleting member:', error);
        showNotification('Error removing team member', 'error');
      });
    }

    function editMember(memberId) {
      const formData = new FormData();
      formData.append('action', 'get_member');
      formData.append('member_id', memberId);

      fetch('team_member.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          const member = data.member;
          document.getElementById('editMemberId').value = member.id;
          document.getElementById('editMemberName').value = member.name;
          document.getElementById('editMemberEmail').value = member.email;
          document.getElementById('editMemberGroup').value = member.group_name;
          document.getElementById('editMemberRole').value = member.role || '';
          
          document.getElementById('editMemberModal').style.display = 'block';
        } else {
          showNotification(data.message, 'error');
        }
      })
      .catch(error => {
        console.error('Error fetching member:', error);
        showNotification('Error loading member data', 'error');
      });
    }

    function addMemberToUI(member) {
      const groupName = member.group_name;
      let groupSection = findOrCreateGroupSection(groupName);
      const membersList = groupSection.querySelector('.members-list');
      
      const initials = getUserInitials(member.name);
      const memberRow = createMemberRow(member, initials);
      
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

    function updateMemberInUI(member) {
      const memberRow = document.querySelector(`[data-member-id="${member.id}"]`);
      if (memberRow) {
        // Check if group changed
        const currentGroup = memberRow.closest('.team-group').querySelector('.group-title').textContent;
        const newGroupName = member.group_name;
        
        if (!currentGroup.includes(newGroupName)) {
          // Move to new group
          memberRow.remove();
          addMemberToUI(member);
        } else {
          // Update in place
          const initials = getUserInitials(member.name);
          memberRow.querySelector('.member-name').textContent = member.name;
          memberRow.querySelector('.member-email').textContent = member.email;
          memberRow.querySelector('.avatar-placeholder').textContent = initials;
        }
      }
    }

    function removeMemberFromUI(memberId) {
      const memberRow = document.querySelector(`[data-member-id="${memberId}"]`);
      if (memberRow) {
        memberRow.style.transition = 'all 0.3s ease';
        memberRow.style.opacity = '0';
        memberRow.style.transform = 'translateX(-20px)';
        
        setTimeout(() => {
          memberRow.remove();
        }, 300);
      }
    }

    function findOrCreateGroupSection(groupName) {
      const groupTitles = {
        'FYP': 'FYP Group Members',
        'Programming': 'Programming Group Members',
        'Other': 'Other Group Members'
      };
      
      const expectedTitle = groupTitles[groupName] || groupName + ' Members';
      
      // Find existing group
      const groups = document.querySelectorAll('.team-group');
      for (let group of groups) {
        const title = group.querySelector('.group-title').textContent;
        if (title === expectedTitle) {
          return group;
        }
      }
      
      // Create new group if not found
      return createNewGroupSection(expectedTitle, groupName);
    }

    function createNewGroupSection(title, groupName) {
      const teamGroups = document.querySelector('.team-groups');
      const newGroup = document.createElement('div');
      newGroup.className = 'team-group';
      newGroup.innerHTML = `
        <h2 class="group-title">${title}</h2>
        <div class="members-list" data-group="${groupName}"></div>
      `;
      teamGroups.appendChild(newGroup);
      return newGroup;
    }

    function createMemberRow(member, initials) {
      const memberRow = document.createElement('div');
      memberRow.className = 'member-row';
      memberRow.setAttribute('data-member-id', member.id);
      
      const dateAdded = formatDate(member.created_at);
      
      memberRow.innerHTML = `
        <div class="member-info">
          <div class="member-avatar">
            <div class="avatar-placeholder">${initials}</div>
          </div>
          <div class="member-details">
            <div class="member-name">${member.name}</div>
            <div class="member-email">${member.email}</div>
          </div>
        </div>
        <div class="member-date">${dateAdded}</div>
        <div class="member-active">${dateAdded}</div>
        <div class="member-actions">
          <button class="action-btn delete-btn" onclick="deleteMember(${member.id})">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="3,6 5,6 21,6"/>
              <path d="M19,6V20a2,2,0,0,1-2,2H7a2,2,0,0,1-2-2V6M8,6V4a2,2,0,0,1,2-2h4a2,2,0,0,1,2,2V6"/>
              <line x1="10" y1="11" x2="10" y2="17"/>
              <line x1="14" y1="11" x2="14" y2="17"/>
            </svg>
          </button>
          <button class="action-btn edit-btn" onclick="editMember(${member.id})">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
              <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
            </svg>
          </button>
        </div>
      `;
      
      return memberRow;
    }

    function getUserInitials(name) {
      return name.split(' ').map(n => n[0]).join('').toUpperCase().substr(0, 2);
    }

    function formatDate(dateString) {
      try {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
          year: 'numeric',
          month: 'short',
          day: 'numeric'
        });
      } catch (e) {
        return 'Today';
      }
    }

    function showNotification(message, type = 'success') {
      const notification = document.createElement('div');
      notification.className = 'notification';
      notification.textContent = message;
      
      if (type === 'error') {
        notification.style.background = 'linear-gradient(45deg, #dc3545, #c82333)';
      }
      
      document.body.appendChild(notification);
      
      setTimeout(() => {
        notification.classList.add('show');
      }, 100);
      
      setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
          if (document.body.contains(notification)) {
            document.body.removeChild(notification);
          }
        }, 300);
      }, 3000);
    }
  </script>
</body>
</html>