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

// Process password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            // Verify token and expiry
            $stmt = $pdo->prepare("SELECT id, token_expiry FROM users WHERE email = ? AND reset_token = ? AND status = 'active'");
            $stmt->execute([$email, $token]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // Check if token is expired
                if (strtotime($user['token_expiry']) < time()) {
                    $error = "Reset link has expired. Please request a new password reset.";
                } else {
                    // Hash new password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Update password and clear reset token
                    $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, token_expiry = NULL, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$hashed_password, $user['id']]);
                    
                    $success = "Your password has been reset successfully. You can now login with your new password.";
                    
                    // Log the password reset
                    error_log("Password reset successful for user: " . $email . " from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown'));
                }
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
                <?php echo $success; ?>
            </div>
            
            <div style="text-align: center; margin-top: 2rem;">
                <a href="login.php" class="btn btn-primary" style="padding: 0.75rem 2rem;">Login Now</a>
            </div>
        <?php else: ?>
            <?php if ($error): ?>
                <div style="background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 5px; margin-bottom: 1.5rem; border-left: 4px solid #dc3545;">
                    <?php echo $error; ?>
                </div>
                
                <?php if (strpos($error, 'expired') !== false || strpos($error, 'invalid') !== false): ?>
                    <div style="text-align: center; margin-top: 1rem;">
                        <a href="forgot-password.php" class="btn" style="background: #6c757d; color: white; padding: 0.5rem 1rem; text-decoration: none; border-radius: 5px;">
                            Request New Reset Link
                        </a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php if (empty($error) || (!empty($error) && strpos($error, 'expired') === false && strpos($error, 'invalid') === false)): ?>
            <form method="POST" action="">
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: bold; color: #333;">New Password</label>
                    <input type="password" name="password" required 
                           style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; font-size: 1rem;"
                           placeholder="Enter new password" minlength="6">
                    <small style="color: #666;">Password must be at least 6 characters long</small>
                </div>
                
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: bold; color: #333;">Confirm Password</label>
                    <input type="password" name="confirm_password" required 
                           style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; font-size: 1rem;"
                           placeholder="Confirm new password" minlength="6">
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.75rem; font-size: 1.1rem;">
                    Reset Password
                </button>
            </form>
            <?php endif; ?>
            
            <div style="text-align: center; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #eee;">
                <p style="color: #666; margin: 0;">
                    Remember your password? 
                    <a href="login.php" style="color: #007bff; text-decoration: none;">Back to Login</a>
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Password strength indicator and confirmation match
document.addEventListener('DOMContentLoaded', function() {
    const passwordInput = document.querySelector('input[name="password"]');
    const confirmInput = document.querySelector('input[name="confirm_password"]');
    
    if (passwordInput && confirmInput) {
        // Create password strength indicator
        const strengthIndicator = document.createElement('div');
        strengthIndicator.style.cssText = 'margin-top: 0.5rem; font-size: 0.8rem;';
        passwordInput.parentNode.appendChild(strengthIndicator);
        
        passwordInput.addEventListener('input', function() {
            const password = passwordInput.value;
            let strength = 'Weak';
            let color = '#dc3545';
            
            if (password.length >= 8) {
                strength = 'Medium';
                color = '#ffc107';
            }
            if (password.length >= 10 && /[A-Z]/.test(password) && /[0-9]/.test(password) && /[^A-Za-z0-9]/.test(password)) {
                strength = 'Strong';
                color = '#28a745';
            }
            
            strengthIndicator.innerHTML = `Password strength: <strong style="color: ${color}">${strength}</strong>`;
        });
        
        // Password confirmation match
        confirmInput.addEventListener('input', function() {
            if (confirmInput.value !== passwordInput.value) {
                confirmInput.style.borderColor = '#dc3545';
            } else {
                confirmInput.style.borderColor = '#28a745';
            }
        });
    }
});
</script>

<?php
require_once 'includes/footer.php';
?>