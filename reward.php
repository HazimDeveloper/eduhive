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
        <li class="nav-item active">
          <a href="#reward">Reward</a>
        </li>
        <li class="nav-item">
          <a href="#team">Team Members</a>
        </li>
      </ul>
    </nav>

    <!-- Main Rewards Content -->
    <main class="rewards-main">
      <div class="rewards-header">
        <h1>Rewards</h1>
        <div class="user-name">NUR KHALIDA BINTI NAZERI ></div>
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
            <button class="claim-btn">Claim Reward</button>
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
            <button class="claim-btn">Claim Reward</button>
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
            <button class="claim-btn">Claim Reward</button>
          </div>
        </div>
      </div>
      
      <!-- Statistics Section -->
      <div class="stats-section">
        <!-- Total Badges -->
        <div class="stat-card badges-card">
          <div class="stat-content">
            <h2>Total Badges Collected</h2>
            <div class="stat-number">23</div>
            <p class="stat-description">Badges Collected. Congrats!</p>
          </div>
        </div>
        
        <!-- Total Ranking -->
        <div class="stat-card ranking-card">
          <div class="stat-content">
            <h2>Total Ranking Among Members</h2>
            <div class="ranking-display">
              <span class="hashtag">#</span>
              <span class="rank-number">1</span>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Badge Collection -->
      <div class="badge-collection">
        <h2>Your Badge Collection</h2>
        <div class="badges-grid">
          <div class="collected-badge">
            <div class="badge-icon daily">🏆</div>
            <span>Daily Achiever</span>
          </div>
          <div class="collected-badge">
            <div class="badge-icon task">🎯</div>
            <span>Task Master</span>
          </div>
          <div class="collected-badge">
            <div class="badge-icon time">⏰</div>
            <span>Time Keeper</span>
          </div>
          <div class="collected-badge">
            <div class="badge-icon streak">🔥</div>
            <span>7-Day Streak</span>
          </div>
          <div class="collected-badge">
            <div class="badge-icon productivity">📈</div>
            <span>Productivity Pro</span>
          </div>
          <div class="collected-badge">
            <div class="badge-icon milestone">🌟</div>
            <span>Milestone</span>
          </div>
        </div>
      </div>
      
      <!-- Leaderboard Section -->
      <div class="leaderboard-section">
        <h2>Team Leaderboard</h2>
        <div class="leaderboard">
          <div class="leaderboard-item current-user">
            <div class="rank">#1</div>
            <div class="user-info">
              <div class="user-avatar">👤</div>
              <div class="user-details">
                <span class="user-name-text">NUR KHALIDA BINTI NAZERI</span>
                <span class="user-points">156 points</span>
              </div>
            </div>
            <div class="user-badges">23 badges</div>
          </div>
          
          <div class="leaderboard-item">
            <div class="rank">#2</div>
            <div class="user-info">
              <div class="user-avatar">👤</div>
              <div class="user-details">
                <span class="user-name-text">Ahmad Rahman</span>
                <span class="user-points">142 points</span>
              </div>
            </div>
            <div class="user-badges">19 badges</div>
          </div>
          
          <div class="leaderboard-item">
            <div class="rank">#3</div>
            <div class="user-info">
              <div class="user-avatar">👤</div>
              <div class="user-details">
                <span class="user-name-text">Sarah Lee</span>
                <span class="user-points">128 points</span>
              </div>
            </div>
            <div class="user-badges">15 badges</div>
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
        <div class="modal-badge" id="modalBadge">
          🏆
        </div>
        <h3 id="modalTitle">Congratulations!</h3>
        <p id="modalDescription">You've earned a new badge!</p>
        <div class="modal-points">
          <span>+10 Points</span>
        </div>
        <button class="collect-btn">Collect Reward</button>
      </div>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const claimButtons = document.querySelectorAll('.claim-btn');
      const modal = document.getElementById('rewardModal');
      const closeModal = document.querySelector('.close');
      const collectBtn = document.querySelector('.collect-btn');
      
      // Reward data
      const rewards = {
        'daily': {
          title: 'Daily Reward Claimed!',
          description: 'You\'ve completed your daily check-in.',
          badge: '🏆',
          points: 10
        },
        'task': {
          title: 'Task Completed!',
          description: 'Great job finishing your task!',
          badge: '🎯',
          points: 15
        },
        'achievement': {
          title: 'Achievement Unlocked!',
          description: 'You\'ve completed more than 10 tasks!',
          badge: '🌟',
          points: 25
        }
      };

      // Load user progress from localStorage
      let userProgress = JSON.parse(localStorage.getItem('eduhive-user-progress')) || {
        totalBadges: 23,
        rank: 1,
        points: 156,
        claimedRewards: []
      };

      // Check if rewards are already claimed
      updateRewardButtons();

      // Add click event to all claim buttons
      claimButtons.forEach((button, index) => {
        button.addEventListener('click', function(e) {
          e.preventDefault();
          
          const rewardTypes = ['daily', 'task', 'achievement'];
          const rewardType = rewardTypes[index];
          const reward = rewards[rewardType];
          
          if (!userProgress.claimedRewards.includes(rewardType)) {
            showRewardModal(reward);
            
            // Mark as claimed
            userProgress.claimedRewards.push(rewardType);
            userProgress.totalBadges += 1;
            userProgress.points += reward.points;
            
            // Save progress
            localStorage.setItem('eduhive-user-progress', JSON.stringify(userProgress));
            
            // Update UI
            updateRewardButtons();
            updateStats();
          }
        });
      });

      function showRewardModal(reward) {
        document.getElementById('modalBadge').textContent = reward.badge;
        document.getElementById('modalTitle').textContent = reward.title;
        document.getElementById('modalDescription').textContent = reward.description;
        document.querySelector('.modal-points span').textContent = `+${reward.points} Points`;
        
        modal.style.display = 'block';
      }

      function updateRewardButtons() {
        claimButtons.forEach((button, index) => {
          const rewardTypes = ['daily', 'task', 'achievement'];
          const rewardType = rewardTypes[index];
          
          if (userProgress.claimedRewards.includes(rewardType)) {
            button.textContent = 'Claimed';
            button.disabled = true;
            button.classList.add('claimed');
          }
        });
      }

      function updateStats() {
        document.querySelector('.stat-number').textContent = userProgress.totalBadges;
        document.querySelector('.rank-number').textContent = userProgress.rank;
        document.querySelector('.user-points').textContent = `${userProgress.points} points`;
      }

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

      function showSuccessNotification() {
        // Create a simple notification
        const notification = document.createElement('div');
        notification.className = 'success-notification';
        notification.textContent = 'Reward collected successfully!';
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

      // Simulate daily reset (for demo purposes)
      function checkDailyReset() {
        const lastLogin = localStorage.getItem('eduhive-last-login');
        const today = new Date().toDateString();
        
        if (lastLogin !== today) {
          // Reset daily reward
          userProgress.claimedRewards = userProgress.claimedRewards.filter(r => r !== 'daily');
          localStorage.setItem('eduhive-user-progress', JSON.stringify(userProgress));
          localStorage.setItem('eduhive-last-login', today);
          updateRewardButtons();
        }
      }

      // Badge collection animation
      document.querySelectorAll('.collected-badge').forEach((badge, index) => {
        badge.style.animationDelay = `${index * 0.1}s`;
      });

      // Initialize
      updateStats();
      checkDailyReset();
    });
  </script>
</body>
</html>