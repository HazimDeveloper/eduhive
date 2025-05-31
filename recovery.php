<?php
// =====================================================
// FILE: recovery.php (Enhanced with email/SMS)
// =====================================================
require_once 'config/session.php';

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
    <title>EduHive - Password Recovery</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="recovery-wrapper">
        <div class="recovery-box">
            <div class="brand">
                <div class="logo">
                    <div class="logo-circle">
                        <div class="graduation-cap">🎓</div>
                        <div class="location-pin">📍</div>
                    </div>
                </div>
                <h1>EduHive</h1>
            </div>

            <div class="recovery-form">
                <h2 class="recovery-title">RECOVER YOUR PASSWORD</h2>
                <p class="recovery-subtitle">Enter your email address and we'll send you instructions to reset your password.</p>
                
                <div id="message" class="message" style="display: none;"></div>
                
                <form id="recoveryForm">
                    <input type="email" name="email" id="email" placeholder="Enter your email address" required>
                    
                    <div class="recovery-options">
                        <label class="recovery-method">
                            <input type="radio" name="method" value="email" checked>
                            <span>Send recovery link via Email</span>
                        </label>
                        <label class="recovery-method">
                            <input type="radio" name="method" value="sms">
                            <span>Send recovery code via SMS</span>
                        </label>
                    </div>
                    
                    <input type="tel" name="phone" id="phone" placeholder="Phone number (for SMS option)" style="display: none;">
                    
                    <button type="submit" id="recoveryBtn">
                        <span id="recoveryText">Send Recovery Instructions</span>
                        <span id="recoverySpinner" class="spinner" style="display: none;">⟳</span>
                    </button>
                </form>
                
                <div class="back-link">
                    <a href="login.php">← Back to Login</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Show/hide phone field based on recovery method
        document.querySelectorAll('input[name="method"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const phoneField = document.getElementById('phone');
                if (this.value === 'sms') {
                    phoneField.style.display = 'block';
                    phoneField.required = true;
                } else {
                    phoneField.style.display = 'none';
                    phoneField.required = false;
                }
            });
        });

        document.getElementById('recoveryForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const recoveryBtn = document.getElementById('recoveryBtn');
            const recoveryText = document.getElementById('recoveryText');
            const recoverySpinner = document.getElementById('recoverySpinner');
            const messageDiv = document.getElementById('message');
            
            recoveryText.style.display = 'none';
            recoverySpinner.style.display = 'inline-block';
            recoveryBtn.disabled = true;
            
            const formData = new FormData(this);
            
            fetch('auth/password_recovery.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    messageDiv.className = 'message success';
                    messageDiv.textContent = data.message;
                    messageDiv.style.display = 'block';
                    this.reset();
                } else {
                    messageDiv.className = 'message error';
                    messageDiv.textContent = data.message;
                    messageDiv.style.display = 'block';
                }
            })
            .catch(error => {
                messageDiv.className = 'message error';
                messageDiv.textContent = 'Network error. Please try again.';
                messageDiv.style.display = 'block';
            })
            .finally(() => {
                recoveryText.style.display = 'inline-block';
                recoverySpinner.style.display = 'none';
                recoveryBtn.disabled = false;
            });
        });
    </script>

    <style>
        .recovery-options {
            margin: 20px 0;
        }
        .recovery-method {
            display: block;
            margin: 10px 0;
            font-size: 14px;
            color: #333;
            cursor: pointer;
        }
        .recovery-method input[type="radio"] {
            margin-right: 8px;
        }
    </style>
</body>
</html>