<?php
// change-password.php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$page_title = "Change Password";

$user_id = $_SESSION['user_id'];

// Get user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    redirect('logout.php');
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate inputs
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "All password fields are required.";
    } elseif (!password_verify($current_password, $user['password'])) {
        $error = "Current password is incorrect.";
    } elseif (strlen($new_password) < 6) {
        $error = "New password must be at least 6 characters long.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New password and confirmation password do not match.";
    } elseif ($current_password === $new_password) {
        $error = "New password cannot be the same as current password.";
    } else {
        try {
            // Hash the new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update password in database
            $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$hashed_password, $user_id]);
            
            $success = "Password changed successfully!";
            
            // Send email notification
            $user_email = $user['email'];
            $subject = "Password Changed - " . getSiteSetting($pdo, 'site_name');
            $message = "
                <h1>Password Changed Successfully</h1>
                <p>Dear " . $user['first_name'] . ",</p>
                <p>Your password has been successfully changed for your account at " . getSiteSetting($pdo, 'site_name') . ".</p>
                <div style='background: #f8f9fa; padding: 1rem; border-radius: 5px; margin: 1rem 0;'>
                    <p><strong>Account:</strong> " . $user['email'] . "</p>
                    <p><strong>Changed on:</strong> " . date('F j, Y g:i A') . "</p>
                </div>
                <p>If you did not make this change, please contact our support team immediately.</p>
                <p>Thank you for using " . getSiteSetting($pdo, 'site_name') . "!</p>
            ";
            
            sendEmail($user_email, $subject, $message);
            
            // Add notification
            addNotification($pdo, $user_id, 'Password Changed', 'Your password has been changed successfully.', 'success', 'account');
            
        } catch (Exception $e) {
            $error = "Error changing password: " . $e->getMessage();
        }
    }
}

require_once 'includes/header.php';
?>

<div class="container">
    <div style="max-width: 600px; margin: 0 auto;">
        <!-- Header -->
        <div style="display: flex; justify-content: between; align-items: center; margin: 2rem 0;">
            <a href="profile.php" class="btn" style="background: #6c757d; color: white; padding: 0.5rem 1rem; text-decoration: none; border-radius: 5px;">
                ← Back to Profile
            </a>
            <h1 style="color: #333; margin: 0;">Change Password</h1>
        </div>

        <?php if ($success): ?>
            <div style="background: #d4edda; color: #155724; padding: 1rem; border-radius: 5px; margin-bottom: 2rem; border: 1px solid #c3e6cb;">
                <div style="display: flex; align-items: center;">
                    <span style="font-size: 1.2rem; margin-right: 0.5rem;">✅</span>
                    <span><?php echo $success; ?></span>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 5px; margin-bottom: 2rem; border: 1px solid #f5c6cb;">
                <div style="display: flex; align-items: center;">
                    <span style="font-size: 1.2rem; margin-right: 0.5rem;">❌</span>
                    <span><?php echo $error; ?></span>
                </div>
            </div>
        <?php endif; ?>

        <!-- Password Change Form -->
        <div style="background: white; padding: 2rem; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
            <div style="text-align: center; margin-bottom: 2rem;">
                <div style="font-size: 3rem; color: #007bff; margin-bottom: 1rem;">🔒</div>
                <h2 style="color: #333; margin-bottom: 0.5rem;">Update Your Password</h2>
                <p style="color: #666;">Choose a strong password to keep your account secure</p>
            </div>

            <form method="POST" action="" id="passwordForm">
                <!-- Current Password -->
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label for="current_password" style="display: block; margin-bottom: 0.5rem; font-weight: bold; color: #333;">
                        Current Password *
                    </label>
                    <div style="position: relative;">
                        <input type="password" id="current_password" name="current_password" class="form-control" 
                               required style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; padding-right: 2.5rem;">
                        <button type="button" class="toggle-password" data-target="current_password" 
                                style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #666; cursor: pointer;">
                            👁️
                        </button>
                    </div>
                </div>

                <!-- New Password -->
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label for="new_password" style="display: block; margin-bottom: 0.5rem; font-weight: bold; color: #333;">
                        New Password *
                    </label>
                    <div style="position: relative;">
                        <input type="password" id="new_password" name="new_password" class="form-control" 
                               required style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; padding-right: 2.5rem;">
                        <button type="button" class="toggle-password" data-target="new_password" 
                                style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #666; cursor: pointer;">
                            👁️
                        </button>
                    </div>
                    <div id="passwordStrength" style="margin-top: 0.5rem; font-size: 0.8rem;"></div>
                    <div style="font-size: 0.8rem; color: #666; margin-top: 0.25rem;">
                        Password must be at least 6 characters long
                    </div>
                </div>

                <!-- Confirm Password -->
                <div class="form-group" style="margin-bottom: 2rem;">
                    <label for="confirm_password" style="display: block; margin-bottom: 0.5rem; font-weight: bold; color: #333;">
                        Confirm New Password *
                    </label>
                    <div style="position: relative;">
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                               required style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; padding-right: 2.5rem;">
                        <button type="button" class="toggle-password" data-target="confirm_password" 
                                style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #666; cursor: pointer;">
                            👁️
                        </button>
                    </div>
                    <div id="passwordMatch" style="margin-top: 0.5rem; font-size: 0.8rem;"></div>
                </div>

                <!-- Password Requirements -->
                <div style="background: #f8f9fa; padding: 1rem; border-radius: 5px; margin-bottom: 2rem;">
                    <h4 style="color: #333; margin-bottom: 0.5rem; font-size: 1rem;">Password Requirements:</h4>
                    <ul style="color: #666; margin: 0; padding-left: 1.5rem; font-size: 0.9rem;">
                        <li>At least 6 characters long</li>
                        <li>Different from your current password</li>
                        <li>Should include letters and numbers</li>
                        <li>Avoid common words and patterns</li>
                    </ul>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn btn-primary" style="background: #007bff; color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 5px; cursor: pointer; width: 100%; font-size: 1rem;">
                    Change Password
                </button>
            </form>
        </div>

        <!-- Security Tips -->
        <div style="background: #e7f3ff; padding: 1.5rem; border-radius: 10px; margin-top: 1.5rem;">
            <h4 style="color: #0056b3; margin-bottom: 1rem;">🔐 Security Tips</h4>
            <div style="display: grid; gap: 0.5rem;">
                <div style="display: flex; align-items: start; gap: 0.5rem;">
                    <span style="color: #007bff;">•</span>
                    <span style="color: #333;">Use a unique password that you don't use elsewhere</span>
                </div>
                <div style="display: flex; align-items: start; gap: 0.5rem;">
                    <span style="color: #007bff;">•</span>
                    <span style="color: #333;">Consider using a password manager</span>
                </div>
                <div style="display: flex; align-items: start; gap: 0.5rem;">
                    <span style="color: #007bff;">•</span>
                    <span style="color: #333;">Enable two-factor authentication if available</span>
                </div>
                <div style="display: flex; align-items: start; gap: 0.5rem;">
                    <span style="color: #007bff;">•</span>
                    <span style="color: #333;">Never share your password with anyone</span>
                </div>
            </div>
        </div>

        <!-- Quick Links -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1.5rem;">
            <a href="profile.php" style="display: block; padding: 1rem; background: #f8f9fa; border-radius: 5px; text-decoration: none; color: #333; text-align: center; transition: all 0.3s;">
                👤 Edit Profile
            </a>
            <a href="dashboard.php" style="display: block; padding: 1rem; background: #f8f9fa; border-radius: 5px; text-decoration: none; color: #333; text-align: center; transition: all 0.3s;">
                📊 Back to Dashboard
            </a>
        </div>
    </div>
</div>

<script>
// Toggle password visibility
document.querySelectorAll('.toggle-password').forEach(button => {
    button.addEventListener('click', function() {
        const targetId = this.getAttribute('data-target');
        const input = document.getElementById(targetId);
        
        if (input.type === 'password') {
            input.type = 'text';
            this.innerHTML = '🔒';
        } else {
            input.type = 'password';
            this.innerHTML = '👁️';
        }
    });
});

// Password strength indicator
document.getElementById('new_password').addEventListener('input', function() {
    const password = this.value;
    const strengthDiv = document.getElementById('passwordStrength');
    
    if (password.length === 0) {
        strengthDiv.innerHTML = '';
        return;
    }
    
    let strength = 0;
    let message = '';
    let color = '';
    
    // Length check
    if (password.length >= 6) strength++;
    if (password.length >= 8) strength++;
    
    // Complexity checks
    if (/[A-Z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^A-Za-z0-9]/.test(password)) strength++;
    
    switch(strength) {
        case 0:
        case 1:
            message = 'Very Weak';
            color = '#dc3545';
            break;
        case 2:
            message = 'Weak';
            color = '#ffc107';
            break;
        case 3:
            message = 'Good';
            color = '#17a2b8';
            break;
        case 4:
            message = 'Strong';
            color = '#28a745';
            break;
        case 5:
            message = 'Very Strong';
            color = '#20c997';
            break;
    }
    
    strengthDiv.innerHTML = `<span style="color: ${color}; font-weight: bold;">Strength: ${message}</span>`;
});

// Password match indicator
document.getElementById('confirm_password').addEventListener('input', function() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = this.value;
    const matchDiv = document.getElementById('passwordMatch');
    
    if (confirmPassword.length === 0) {
        matchDiv.innerHTML = '';
        return;
    }
    
    if (newPassword === confirmPassword) {
        matchDiv.innerHTML = '<span style="color: #28a745; font-weight: bold;">✓ Passwords match</span>';
    } else {
        matchDiv.innerHTML = '<span style="color: #dc3545; font-weight: bold;">✗ Passwords do not match</span>';
    }
});

// Form validation
document.getElementById('passwordForm').addEventListener('submit', function(e) {
    const currentPassword = document.getElementById('current_password').value;
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (!currentPassword || !newPassword || !confirmPassword) {
        e.preventDefault();
        alert('Please fill in all password fields.');
        return false;
    }
    
    if (newPassword.length < 6) {
        e.preventDefault();
        alert('New password must be at least 6 characters long.');
        return false;
    }
    
    if (newPassword !== confirmPassword) {
        e.preventDefault();
        alert('New password and confirmation password do not match.');
        return false;
    }
    
    if (currentPassword === newPassword) {
        e.preventDefault();
        alert('New password cannot be the same as current password.');
        return false;
    }
});

// Clear error/success messages when user starts typing
document.querySelectorAll('input[type="password"]').forEach(input => {
    input.addEventListener('input', function() {
        const errorDiv = document.querySelector('div[style*="background: #f8d7da"]');
        const successDiv = document.querySelector('div[style*="background: #d4edda"]');
        
        if (errorDiv) errorDiv.style.display = 'none';
        if (successDiv) successDiv.style.display = 'none';
    });
});
</script>

<style>
.form-group {
    margin-bottom: 1.5rem;
}

.btn {
    transition: all 0.3s ease;
}

.btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 3px 10px rgba(0,0,0,0.2);
}

.toggle-password:hover {
    color: #007bff !important;
}

input:focus {
    border-color: #007bff !important;
    outline: none;
    box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
}

a:hover {
    background-color: #e9ecef !important;
}

@media (max-width: 768px) {
    .container {
        padding: 0 1rem;
    }
    
    h1 {
        font-size: 1.5rem;
    }
}
</style>

<?php
require_once 'includes/footer.php';
?>