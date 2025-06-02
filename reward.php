<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'config/functions.php';

// Ensure user is logged in
requireLogin();

// Get current user data
$user_id = getCurrentUserId();
$user_name = getCurrentUserName() ?: 'User';

// Initialize reward tables
initializeRewardTables();

// Handle AJAX requests for claiming rewards
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'claim_reward') {
        $reward_type = cleanInput($_POST['reward_type']);
        $result = claimReward($user_id, $reward_type);
        echo json_encode($result);
        exit();
    }
    
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit();
}

// Get user data for the page
$user_progress = getUserProgress($user_id);
$user_ranking = getUserRanking($user_id);
$reward_eligibility = checkRewardEligibility($user_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>EduHive - Rewards</title>
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="rewards.css">
</head>
<body class="dashboard-body">
  <div class="dashboard-container">
    <!-- Sidebar Navigation -->
    <nav class="sidebar">
      <div class="sidebar-header">
        <div class="sidebar-logo">
             <img src="logoo.png"  width="40px" alt="">
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
        <li class="nav-item active">
          <a href="reward.php">Reward</a>
        </li>
        <li class="nav-item">
          <a href="team_member.php">Team Members</a>
        </li>
      </ul>
    </nav>

    <!-- Main Rewards Content -->
    <main class="rewards-main">
      <div class="rewards-header">
        <h1>Rewards</h1>
        <div class="user-name"><?php echo htmlspecialchars($user_name); ?> ></div>
      </div>
      
      <!-- Reward Cards -->
      <div class="rewards-grid">
        <!-- Daily Reward -->
        <div class="reward-card daily-reward">
          <div class="reward-content">
            <h3>Daily reward</h3>
            <div class="reward-badge daily-badge">
              <div class="badge-circle">
                <div class="badge-ribbon">
                  <div class="ribbon-top"></div>
                  <div class="ribbon-middle"></div>
                  <div class="ribbon-bottom"></div>
                </div>
              </div>
            </div>
            <?php if ($reward_eligibility['daily']['claimed']): ?>
              <button class="claim-btn claimed" disabled>Claimed Today</button>
            <?php elseif ($reward_eligibility['daily']['eligible']): ?>
              <button class="claim-btn" onclick="claimReward('daily')">Claim Reward</button>
            <?php else: ?>
              <button class="claim-btn" disabled>Check in daily</button>
            <?php endif; ?>
          </div>
        </div>
        
        <!-- Completed One Task -->
        <div class="reward-card task-reward">
          <div class="reward-content">
            <h3>Completed One Task</h3>
            <div class="reward-badge task-badge">
              <div class="badge-circle red-badge">
                <div class="badge-center"></div>
                <div class="badge-ribbon red-ribbon">
                  <div class="ribbon-top"></div>
                  <div class="ribbon-bottom"></div>
                </div>
              </div>
            </div>
            <?php if ($reward_eligibility['first_task']['claimed']): ?>
              <button class="claim-btn claimed" disabled>Claimed</button>
            <?php elseif ($reward_eligibility['first_task']['eligible']): ?>
              <button class="claim-btn" onclick="claimReward('first_task')">Claim Reward</button>
            <?php else: ?>
              <button class="claim-btn" disabled>Complete 1 task</button>
            <?php endif; ?>
          </div>
        </div>
        
        <!-- Completed Ten Tasks -->
        <div class="reward-card achievement-reward">
          <div class="reward-content">
            <h3>Completed more than Ten Task</h3>
            <div class="reward-badge achievement-badge">
              <div class="badge-circle gold-badge">
                <div class="badge-center gold-center"></div>
                <div class="badge-star">★</div>
                <div class="badge-ribbon gold-ribbon">
                  <div class="ribbon-left"></div>
                  <div class="ribbon-right"></div>
                </div>
              </div>
            </div>
            <?php if ($reward_eligibility['ten_tasks']['claimed']): ?>
              <button class="claim-btn claimed" disabled>Claimed</button>
            <?php elseif ($reward_eligibility['ten_tasks']['eligible']): ?>
              <button class="claim-btn" onclick="claimReward('ten_tasks')">Claim Reward</button>
            <?php else: ?>
              <button class="claim-btn" disabled>Complete 10 tasks</button>
            <?php endif; ?>
          </div>
        </div>
      </div>
      
      <!-- Statistics Section -->
      <div class="stats-section">
        <!-- Total Badges -->
        <div class="stat-card badges-card">
          <div class="stat-content">
            <h2>Total Badges Collected</h2>
            <div class="stat-number" id="totalBadges"><?php echo $user_progress['total_badges'] ?? 0; ?></div>
            <p class="stat-description">Badges Collected. Congrats!</p>
          </div>
        </div>
        
        <!-- Total Ranking -->
        <div class="stat-card ranking-card">
          <div class="stat-content">
            <h2>Total Ranking Among Members</h2>
            <div class="ranking-display">
              <span class="hashtag">#</span>
              <span class="rank-number" id="userRank"><?php echo $user_ranking; ?></span>
            </div>
          </div>
        </div>
      </div>
      

    </main>
  </div>

  <!-- Claim Reward Modal -->
  <div id="rewardModal" class="modal" style="display: none;">
    <div class="modal-content">
      <span class="close">&times;</span>
      <div class="modal-reward">
        <div class="modal-badge" id="modalBadge">🏆</div>
        <h3 id="modalTitle">Congratulations!</h3>
        <p id="modalDescription">You've earned a new badge!</p>
        <div class="modal-points">
          <span id="modalPoints">+10 Points</span>
        </div>
        <button class="collect-btn" id="collectBtn">Collect Reward</button>
      </div>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const modal = document.getElementById('rewardModal');
      const closeModal = document.querySelector('.close');
      const collectBtn = document.getElementById('collectBtn');
      
      // Modal event listeners
      closeModal.addEventListener('click', () => {
        modal.style.display = 'none';
      });

      collectBtn.addEventListener('click', () => {
        modal.style.display = 'none';
        showSuccessNotification();
      });

      window.addEventListener('click', (event) => {
        if (event.target === modal) {
          modal.style.display = 'none';
        }
      });
    });

    function claimReward(rewardType) {
      const rewardData = {
        'daily': {
          title: 'Daily Reward Claimed!',
          description: 'You\'ve completed your daily check-in.',
          badge: '🏆',
          points: 10
        },
        'first_task': {
          title: 'First Task Completed!',
          description: 'Great job finishing your first task!',
          badge: '🎯',
          points: 25
        },
        'ten_tasks': {
          title: 'Achievement Unlocked!',
          description: 'You\'ve completed more than 10 tasks!',
          badge: '🌟',
          points: 100
        }
      };

      const reward = rewardData[rewardType];
      
      // Send claim request to server
      fetch('reward.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=claim_reward&reward_type=${rewardType}`
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          showRewardModal(reward, data.points_earned);
          
          // Update UI
          updateRewardButton(rewardType);
          updateStats(data.new_badges);
          
        } else {
          showNotification(data.message || 'Failed to claim reward', 'error');
        }
      })
      .catch(error => {
        console.error('Error claiming reward:', error);
        showNotification('Error claiming reward', 'error');
      });
    }

    function showRewardModal(reward, points) {
      document.getElementById('modalBadge').textContent = reward.badge;
      document.getElementById('modalTitle').textContent = reward.title;
      document.getElementById('modalDescription').textContent = reward.description;
      document.getElementById('modalPoints').textContent = `+${points} Points`;
      
      document.getElementById('rewardModal').style.display = 'block';
    }

    function updateRewardButton(rewardType) {
      const buttons = document.querySelectorAll('.claim-btn');
      buttons.forEach(button => {
        if (button.onclick && button.onclick.toString().includes(rewardType)) {
          button.textContent = 'Claimed';
          button.disabled = true;
          button.classList.add('claimed');
        }
      });
    }

    function updateStats(newBadges) {
      const badgesElement = document.getElementById('totalBadges');
      if (badgesElement) {
        badgesElement.textContent = newBadges;
      }
    }

    function showSuccessNotification() {
      showNotification('Reward collected successfully!', 'success');
    }

    function showNotification(message, type = 'info') {
      const notification = document.createElement('div');
      notification.className = `success-notification${type === 'success' ? ' show' : ''}`;
      notification.textContent = message;
      notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? 'linear-gradient(45deg, #28a745, #20c997)' : '#dc3545'};
        color: white;
        padding: 15px 25px;
        border-radius: 10px;
        font-weight: 600;
        box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        z-index: 1001;
        transform: translateX(100%);
        transition: transform 0.3s ease;
      `;
      
      document.body.appendChild(notification);
      
      setTimeout(() => {
        notification.style.transform = 'translateX(0)';
      }, 100);
      
      setTimeout(() => {
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
          if (document.body.contains(notification)) {
            document.body.removeChild(notification);
          }
        }, 300);
      }, 3000);
    }

    // Check for daily reset and update buttons
    function checkDailyReset() {
      const lastLogin = localStorage.getItem('eduhive-last-login');
      const today = new Date().toDateString();
      
      if (lastLogin !== today) {
        localStorage.setItem('eduhive-last-login', today);
        // Could refresh daily rewards here
      }
    }

    // Initialize
    checkDailyReset();
  </script>
</body>
</html>