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
  <title>EduHive - Create Account</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="register-wrapper">
    <div class="register-box">
      <div class="brand">
        <div class="logo">
          <div class="logo-circle">
            <div class="graduation-cap">🎓</div>
            <div class="location-pin">📍</div>
          </div>
        </div>
        <h1>EduHive</h1>
      </div>

      <div class="register-form">
        <h2 class="register-title">CREATE AN ACCOUNT</h2>
        
        <div id="message" class="message" style="display: none;"></div>
        
        <form id="registerForm">
          <input type="text" name="name" id="name" placeholder="Name" required>
          <input type="email" name="email" id="email" placeholder="Email" required>
          <input type="password" name="password" id="password" placeholder="Password" required minlength="6">
          <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm Password" required>
          <button type="submit" id="registerBtn">Sign Up</button>
        </form>
        
        <div class="back-link">
          <a href="login.php">Already have an account? Sign In</a>
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
    document.getElementById('registerForm').addEventListener('submit', function(e) {
      e.preventDefault();
      
      const name = document.getElementById('name').value;
      const email = document.getElementById('email').value;
      const password = document.getElementById('password').value;
      const confirmPassword = document.getElementById('confirm_password').value;
      const messageDiv = document.getElementById('message');
      const registerBtn = document.getElementById('registerBtn');
      
      // Validation
      if (password !== confirmPassword) {
        messageDiv.className = 'message error';
        messageDiv.textContent = 'Passwords do not match';
        messageDiv.style.display = 'block';
        return;
      }
      
      if (password.length < 6) {
        messageDiv.className = 'message error';
        messageDiv.textContent = 'Password must be at least 6 characters long';
        messageDiv.style.display = 'block';
        return;
      }
      
      // Show loading state
      registerBtn.textContent = 'Creating Account...';
      registerBtn.classList.add('loading');
      
      // Prepare form data
      const formData = new FormData();
      formData.append('name', name);
      formData.append('email', email);
      formData.append('password', password);
      formData.append('confirm_password', confirmPassword);
      formData.append('register', '1');
      
      fetch('auth/register_process.php', {
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
        registerBtn.textContent = 'Sign Up';
        registerBtn.classList.remove('loading');
      });
    });
  </script>
</body>
</html>