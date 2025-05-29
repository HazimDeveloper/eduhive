<?php
require_once 'config/session.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>EduHive - Login</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="login-wrapper">
    <div class="login-box">
      <div class="brand">
        <div class="logo">
          <div class="logo-circle">
            <div class="graduation-cap">🎓</div>
            <div class="location-pin">📍</div>
          </div>
        </div>
        <h1>EduHive</h1>
      </div>

      <div class="login-form">
        <h2 class="login-title">LOG IN TO YOUR ACCOUNT</h2>
        
        <div id="message" class="message" style="display: none;"></div>
        
        <form id="loginForm">
          <input type="email" name="email" id="email" placeholder="Email" required>
          <input type="password" name="password" id="password" placeholder="Password" required>
          <button type="submit" id="loginBtn">Sign In</button>
        </form>
        
        <div class="options">
          <a href="recovery.php">Can't Log in?</a>
          <span class="separator">•</span>
          <a href="register.php">Create an account</a>
        </div>
      </div>
    </div>
  </div>

  <style>
    .message {
      padding: 10px;
      margin: 10px 0;
      border-radius: 5px;
      text-align: center;
      font-weight: 500;
    }
    
    .message.success {
      background-color: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }
    
    .message.error {
      background-color: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }
    
    .loading {
      opacity: 0.7;
      pointer-events: none;
    }
  </style>

  <script>
    document.getElementById('loginForm').addEventListener('submit', function(e) {
      e.preventDefault();
      
      const email = document.getElementById('email').value;
      const password = document.getElementById('password').value;
      const messageDiv = document.getElementById('message');
      const loginBtn = document.getElementById('loginBtn');
      
      // Show loading state
      loginBtn.textContent = 'Signing In...';
      loginBtn.classList.add('loading');
      
      // Prepare form data
      const formData = new FormData();
      formData.append('email', email);
      formData.append('password', password);
      
      fetch('auth/login_process.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          messageDiv.className = 'message success';
          messageDiv.textContent = data.message;
          messageDiv.style.display = 'block';
          
          // Redirect to dashboard
          setTimeout(() => {
            window.location.href = data.data.redirect;
          }, 1000);
        } else {
          messageDiv.className = 'message error';
          messageDiv.textContent = data.message;
          messageDiv.style.display = 'block';
        }
      })
      .catch(error => {
        messageDiv.className = 'message error';
        messageDiv.textContent = 'An error occurred. Please try again.';
        messageDiv.style.display = 'block';
      })
      .finally(() => {
        loginBtn.textContent = 'Sign In';
        loginBtn.classList.remove('loading');
      });
    });
  </script>
</body>
</html>