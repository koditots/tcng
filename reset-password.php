<?php
// reset-password.php
require_once 'config.php';

$page_title = "Reset Password";
$success = '';
$error = '';

$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';

// Validate token and email
if (empty($token) || empty($email)) {
    $error = "Invalid reset link. Please request a new password reset.";
}

// Check if token is valid and not expired
if (empty($error)) {
    try {
        $stmt = $pdo->prepare("SELECT id, name, token_expiry FROM users WHERE email = ? AND reset_token = ? AND status = 'active'");
        $stmt->execute([$email, $token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            $error = "Invalid reset link. Please request a new password reset.";
        } elseif (strtotime($user['token_expiry']) < time()) {
            $error = "Reset link has expired. Please request a new password reset.";
        }
    } catch (Exception $e) {
        error_log("Reset password token validation error: " . $e->getMessage());
        $error = "An error occurred while validating your reset link. Please try again.";
    }
}

// Process password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($password) || empty($confirm_password)) {
        $error = "Please fill in all fields.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        try {
            // Re-verify token before resetting password
            $stmt = $pdo->prepare("SELECT id, token_expiry FROM users WHERE email = ? AND reset_token = ? AND status = 'active'");
            $stmt->execute([$email, $token]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && strtotime($user['token_expiry']) >= time()) {
                // Hash new password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Update password and clear reset token
                $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, token_expiry = NULL, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$hashed_password, $user['id']]);
                
                $success = "Your password has been reset successfully. You can now login with your new password.";
                
                // Send confirmation email
                $site_name = getSiteSetting($pdo, 'site_name') ?: 'Travel Centre';
                $subject = "Password Reset Successful - " . $site_name;
                
                $message = "
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <style>
                            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                            .header { background: linear-gradient(135deg, #28a745, #20c997); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                            .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
                            .success-banner { background: #d4edda; color: #155724; padding: 20px; text-align: center; margin: 20px 0; border-radius: 5px; border-left: 4px solid #28a745; }
                            .info-box { background: white; padding: 20px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #007bff; }
                            .security-note { background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #ffc107; }
                            .footer { text-align: center; margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 14px; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h1>$site_name</h1>
                                <p>Password Reset Successful</p>
                            </div>
                            
                            <div class='content'>
                                <div class='success-banner'>
                                    <h2>✅ Password Reset Successful!</h2>
                                    <p>Your password has been updated successfully.</p>
                                </div>
                                
                                <p>Hello " . htmlspecialchars($user['name']) . ",</p>
                                
                                <p>This email confirms that your password for $site_name has been successfully reset.</p>
                                
                                <div class='info-box'>
                                    <p><strong>Reset Details:</strong></p>
                                    <p>Account: " . htmlspecialchars($email) . "</p>
                                    <p>Reset Time: " . date('F j, Y g:i A') . "</p>
                                    <p>IP Address: " . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown') . "</p>
                                </div>
                                
                                <div class='security-note'>
                                    <strong>🔒 Security Notice:</strong>
                                    <ul>
                                        <li>If you did not perform this action, please contact support immediately</li>
                                        <li>Ensure your new password is strong and unique</li>
                                        <li>Consider enabling two-factor authentication for added security</li>
                                    </ul>
                                </div>
                                
                                <p>You can now login to your account with your new password:</p>
                                <div style='text-align: center; margin: 20px 0;'>
                                    <a href='" . SITE_URL . "/login' style='background: #007bff; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>Login to Your Account</a>
                                </div>
                                
                                <p>Thank you for keeping your account secure!</p>
                                <p><strong>The $site_name Team</strong></p>
                            </div>
                            
                            <div class='footer'>
                                <p>&copy; " . date('Y') . " $site_name. All rights reserved.</p>
                                <p>This is an automated security message.</p>
                            </div>
                        </div>
                    </body>
                    </html>
                ";
                
                // Send confirmation email
                sendEmail($email, $subject, $message, true);
                
                // Log the password reset
                error_log("Password reset successful for user: " . $email . " from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown'));
                
            } else {
                $error = "Invalid or expired reset link. Please request a new password reset.";
            }
            
        } catch (Exception $e) {
            error_log("Password reset error: " . $e->getMessage());
            $error = "An error occurred while resetting your password. Please try again.";
        }
    }
}

require_once 'includes/header.php';
?>

<div class="container" style="max-width: 500px; margin: 2rem auto;">
    <div style="background: white; padding: 2rem; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
        <div style="text-align: center; margin-bottom: 2rem;">
            <h1 style="color: #333; margin-bottom: 0.5rem;">Reset Password</h1>
            <p style="color: #666;">Create your new password</p>
        </div>
        
        <?php if ($success): ?>
            <div style="background: #d4edda; color: #155724; padding: 1rem; border-radius: 5px; margin-bottom: 1.5rem; border-left: 4px solid #28a745;">
                <div style="display: flex; align-items: center; margin-bottom: 0.5rem;">
                    <span style="font-size: 1.5rem; margin-right: 0.5rem;">✅</span>
                    <strong>Success!</strong>
                </div>
                <?php echo $success; ?>
            </div>
            
            <div style="text-align: center; margin-top: 2rem;">
                <a href="login" class="btn btn-primary" style="padding: 0.75rem 2rem; text-decoration: none;">Login Now</a>
            </div>
            
            <div style="text-align: center; margin-top: 1rem;">
                <a href="<?php echo SITE_URL; ?>" class="btn" style="background: #6c757d; color: white; padding: 0.5rem 1.5rem; text-decoration: none;">Go to Homepage</a>
            </div>
        <?php else: ?>
            <?php if ($error): ?>
                <div style="background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 5px; margin-bottom: 1.5rem; border-left: 4px solid #dc3545;">
                    <div style="display: flex; align-items: center; margin-bottom: 0.5rem;">
                        <span style="font-size: 1.5rem; margin-right: 0.5rem;">❌</span>
                        <strong>Error</strong>
                    </div>
                    <?php echo $error; ?>
                </div>
                
                <?php if (strpos($error, 'expired') !== false || strpos($error, 'invalid') !== false): ?>
                    <div style="text-align: center; margin-top: 1rem;">
                        <a href="forgot-password" class="btn" style="background: #007bff; color: white; padding: 0.75rem 1.5rem; text-decoration: none; border-radius: 5px; display: inline-block;">
                            Request New Reset Link
                        </a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php if (empty($error) || (!empty($error) && strpos($error, 'expired') === false && strpos($error, 'invalid') === false)): ?>
            <form method="POST" action="" id="resetForm">
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: bold; color: #333;">
                        New Password
                        <span style="color: #dc3545;">*</span>
                    </label>
                    <input type="password" name="password" id="password" required 
                           style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; font-size: 1rem;"
                           placeholder="Enter new password" minlength="6"
                           oninput="checkPasswordStrength()">
                    <div id="passwordStrength" style="margin-top: 0.5rem; font-size: 0.8rem;"></div>
                    <small style="color: #666; display: block; margin-top: 0.25rem;">
                        Password must be at least 6 characters long
                    </small>
                </div>
                
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: bold; color: #333;">
                        Confirm Password
                        <span style="color: #dc3545;">*</span>
                    </label>
                    <input type="password" name="confirm_password" id="confirmPassword" required 
                           style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; font-size: 1rem;"
                           placeholder="Confirm new password" minlength="6"
                           oninput="checkPasswordMatch()">
                    <div id="passwordMatch" style="margin-top: 0.5rem; font-size: 0.8rem;"></div>
                </div>
                
                <button type="submit" class="btn btn-primary" id="submitBtn" style="width: 100%; padding: 0.75rem; font-size: 1.1rem;">
                    Reset Password
                </button>
            </form>
            <?php endif; ?>
            
            <div style="text-align: center; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #eee;">
                <p style="color: #666; margin: 0;">
                    Remember your password? 
                    <a href="login" style="color: #007bff; text-decoration: none; font-weight: bold;">Back to Login</a>
                </p>
            </div>
            
            <!-- Security Information -->
            <div style="background: #e7f3ff; padding: 1rem; border-radius: 5px; margin-top: 1.5rem; border-left: 4px solid #007bff;">
                <h4 style="color: #0056b3; margin-bottom: 0.5rem; font-size: 0.9rem;">🔒 Password Security Tips</h4>
                <ul style="color: #0056b3; font-size: 0.8rem; margin: 0; padding-left: 1.2rem;">
                    <li>Use at least 8 characters</li>
                    <li>Include uppercase and lowercase letters</li>
                    <li>Add numbers and special characters</li>
                    <li>Avoid common words and personal information</li>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function checkPasswordStrength() {
    const password = document.getElementById('password').value;
    const strengthIndicator = document.getElementById('passwordStrength');
    
    if (!password) {
        strengthIndicator.innerHTML = '';
        return;
    }
    
    let strength = 0;
    let tips = [];
    
    // Check password length
    if (password.length >= 8) strength++;
    else tips.push('at least 8 characters');
    
    // Check for mixed case
    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
    else tips.push('both uppercase and lowercase letters');
    
    // Check for numbers
    if (/\d/.test(password)) strength++;
    else tips.push('at least one number');
    
    // Check for special characters
    if (/[^A-Za-z0-9]/.test(password)) strength++;
    else tips.push('at least one special character');
    
    // Display strength
    let strengthText = '';
    let strengthColor = '';
    
    switch(strength) {
        case 0:
        case 1:
            strengthText = 'Very Weak';
            strengthColor = '#dc3545';
            break;
        case 2:
            strengthText = 'Weak';
            strengthColor = '#ff6b6b';
            break;
        case 3:
            strengthText = 'Medium';
            strengthColor = '#ffc107';
            break;
        case 4:
            strengthText = 'Strong';
            strengthColor = '#28a745';
            break;
    }
    
    strengthIndicator.innerHTML = `
        <span style="font-weight: bold; color: ${strengthColor}">${strengthText}</span>
        ${tips.length > 0 ? `<br><small style="color: #666;">Consider adding: ${tips.join(', ')}</small>` : ''}
    `;
}

function checkPasswordMatch() {
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    const matchIndicator = document.getElementById('passwordMatch');
    
    if (!confirmPassword) {
        matchIndicator.innerHTML = '';
        document.getElementById('confirmPassword').style.borderColor = '#ddd';
        return;
    }
    
    if (password === confirmPassword) {
        matchIndicator.innerHTML = '<span style="color: #28a745;">✅ Passwords match</span>';
        document.getElementById('confirmPassword').style.borderColor = '#28a745';
    } else {
        matchIndicator.innerHTML = '<span style="color: #dc3545;">❌ Passwords do not match</span>';
        document.getElementById('confirmPassword').style.borderColor = '#dc3545';
    }
}

// Form submission handling
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('resetForm');
    const submitBtn = document.getElementById('submitBtn');
    
    if (form) {
        form.addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            // Client-side validation
            if (password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long.');
                return;
            }
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match. Please confirm your password.');
                return;
            }
            
            // Show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Resetting Password... <span style="margin-left: 0.5rem;">⏳</span>';
        });
    }
    
    // Check password match on page load if fields have values
    checkPasswordMatch();
    checkPasswordStrength();
});
</script>

<style>
.btn {
    display: inline-block;
    padding: 0.75rem 1.5rem;
    background: #007bff;
    color: white;
    text-decoration: none;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 1rem;
    text-align: center;
    transition: background-color 0.3s ease;
}

.btn-primary {
    background: #007bff;
}

.btn-primary:hover {
    background: #0056b3;
}

.btn:disabled {
    background: #6c757d;
    cursor: not-allowed;
}

/* Responsive design */
@media (max-width: 576px) {
    .container {
        margin: 1rem;
        max-width: 100%;
    }
    
    div[style*="max-width: 500px;"] {
        max-width: 100% !important;
        padding: 1rem !important;
    }
}

/* Password input styling */
input[type="password"] {
    transition: border-color 0.3s ease;
}

input[type="password"]:focus {
    border-color: #007bff !important;
    outline: none;
    box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
}
</style>

<?php
require_once 'includes/footer.php';
?>