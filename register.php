<?php
// register.php
require_once 'config.php';

$page_title = "Register";
$error = '';
$success = '';

// NEW: Capture redirect parameter and flight data
$redirect = $_GET['redirect'] ?? '';
$flight_search_data = $_SESSION['flight_search_data'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = sanitize($_POST['first_name']);
    $last_name = sanitize($_POST['last_name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $post_redirect = $_POST['redirect'] ?? '';
    
    // Validation
    if ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long';
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            $error = 'Email already registered';
        } else {
            // Create user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $username = strtolower($first_name . $last_name) . rand(100, 999);
            
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, first_name, last_name, phone) VALUES (?, ?, ?, ?, ?, ?)");
            
            if ($stmt->execute([$username, $email, $hashed_password, $first_name, $last_name, $phone])) {
                $user_id = $pdo->lastInsertId();
                
                // Automatically log the user in after registration
                $_SESSION['user_id'] = $user_id;
                $_SESSION['user_name'] = $first_name . ' ' . $last_name;
                $_SESSION['user_email'] = $email;
                $_SESSION['logged_in'] = true;
                
                // Get site settings for email
                $site_name = getSiteSetting($pdo, 'site_name');
                $site_url = SITE_URL;
                $support_email = getSiteSetting($pdo, 'support_email');
                $admin_email = getSiteSetting($pdo, 'admin_email');
                
                // Send welcome email using template system
                $email_sent = sendTemplateEmail($email, 'welcome', [
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'username' => $username,
                    'email' => $email,
                    'phone' => $phone,
                    'site_name' => $site_name,
                    'site_url' => $site_url,
                    'support_email' => $support_email,
                    'login_url' => $site_url . '/login.php',
                    'registration_date' => date('F j, Y'),
                    'current_year' => date('Y')
                ]);
                
                // If template email fails, send basic welcome email
                if (!$email_sent) {
                    $subject = "Welcome to " . $site_name;
                    $message = "
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <style>
                                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                                .header { background: linear-gradient(135deg, #007bff, #0056b3); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                                .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
                                .button { display: inline-block; background: #007bff; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
                                .footer { text-align: center; margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 14px; }
                            </style>
                        </head>
                        <body>
                            <div class='container'>
                                <div class='header'>
                                    <h1>Welcome to $site_name!</h1>
                                    <p>Your Travel Journey Begins Here</p>
                                </div>
                                <div class='content'>
                                    <h2>Hello, $first_name!</h2>
                                    <p>Thank you for registering with <strong>$site_name</strong>. We're excited to have you on board!</p>
                                    
                                    <h3>Your Account Details:</h3>
                                    <ul>
                                        <li><strong>Name:</strong> $first_name $last_name</li>
                                        <li><strong>Username:</strong> $username</li>
                                        <li><strong>Email:</strong> $email</li>
                                        <li><strong>Phone:</strong> $phone</li>
                                        <li><strong>Registration Date:</strong> " . date('F j, Y') . "</li>
                                    </ul>
                                    
                                    <h3>What You Can Do Now:</h3>
                                    <ul>
                                        <li>✈️ Search and book flights</li>
                                        <li>📋 Manage your bookings</li>
                                        <li>👤 Update your profile</li>
                                        <li>📧 Receive booking confirmations</li>
                                    </ul>
                                    
                                    <div style='text-align: center; margin: 30px 0;'>
                                        <a href='$site_url/dashboard.php' class='button'>Go to Dashboard</a>
                                    </div>
                                    
                                    <p>If you have any questions, feel free to contact our support team at <a href='mailto:$support_email'>$support_email</a>.</p>
                                    
                                    <p>Happy travels!<br>The $site_name Team</p>
                                </div>
                                <div class='footer'>
                                    <p>&copy; " . date('Y') . " $site_name. All rights reserved.</p>
                                    <p><a href='$site_url'>$site_url</a></p>
                                </div>
                            </div>
                        </body>
                        </html>
                    ";
                    
                    $email_sent = sendEmail($email, $subject, $message, true);
                }
                
                // Send admin notification about new registration
                if (!empty($admin_email) && $admin_email !== $email) {
                    $admin_subject = "New User Registration - $site_name";
                    $admin_message = "
                        <h2>New User Registration</h2>
                        <p>A new user has registered on $site_name:</p>
                        <ul>
                            <li><strong>Name:</strong> $first_name $last_name</li>
                            <li><strong>Email:</strong> $email</li>
                            <li><strong>Phone:</strong> $phone</li>
                            <li><strong>Username:</strong> $username</li>
                            <li><strong>Registration Date:</strong> " . date('F j, Y g:i A') . "</li>
                        </ul>
                        <p>Total users: " . getTotalUsers($pdo) . "</p>
                    ";
                    
                    sendEmail($admin_email, $admin_subject, $admin_message, true);
                }
                
                // Add notification to user account
                addNotification($pdo, $user_id, 'Welcome!', 'Thank you for registering with ' . $site_name . '. You can now book flights and manage your travel plans.', 'success');
                
                // Log the registration
                error_log("New user registration: $email ($first_name $last_name)");
                
                // NEW: Enhanced redirect handling for flight bookings
                $redirect_url = 'dashboard.php';
                $flight_redirect = $_SESSION['flight_redirect'] ?? null;
                $flight_search_data = $_SESSION['flight_search_data'] ?? null;
                
                // Priority 1: Flight booking redirect
                if ($flight_redirect && $flight_search_data) {
                    $redirect_url = $flight_redirect;
                    // Clear flight redirect session data
                    unset($_SESSION['flight_redirect']);
                    
                    // Add notification about flight booking
                    addNotification($pdo, $user_id, 'Continue Flight Booking', 'You can now continue with your flight booking.', 'info', 'flight');
                }
                // Priority 2: POST redirect parameter
                elseif (!empty($post_redirect)) {
                    $redirect_url = $post_redirect;
                }
                // Priority 3: GET redirect parameter
                elseif (!empty($redirect)) {
                    $redirect_url = $redirect;
                }
                // Priority 4: Session redirect URL
                elseif (isset($_SESSION['redirect_url'])) {
                    $redirect_url = $_SESSION['redirect_url'];
                    unset($_SESSION['redirect_url']);
                }
                
                // If it's a flight-related redirect, preserve search data
                if (strpos($redirect_url, 'flight') !== false && $flight_search_data) {
                    $_SESSION['flight_search_data'] = $flight_search_data;
                }
                
                // Redirect to appropriate page
                header("Location: " . $redirect_url);
                exit;
                
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}

// Helper function to get total users count
function getTotalUsers($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['total'] ?? 0;
}

require_once 'includes/header.php';
?>

<div style="max-width: 500px; margin: 4rem auto; padding: 2rem; background: white; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
    <h2 style="text-align: center; margin-bottom: 2rem; color: #333;">Create Your Account</h2>
    
    <!-- NEW: Flight Search/Booking Notice -->
    <?php if ($flight_search_data || (isset($_GET['redirect']) && strpos($_GET['redirect'], 'flight') !== false)): ?>
        <div style="background: #e7f3ff; color: #004085; padding: 0.75rem; border-radius: 5px; margin-bottom: 1rem; border-left: 4px solid #007bff;">
            <div style="display: flex; align-items: center; margin-bottom: 0.5rem;">
                <span style="margin-right: 0.5rem;">✈️</span>
                <strong>Flight Booking Notice</strong>
            </div>
            <p style="margin: 0; font-size: 0.9rem;">
                Create an account to continue with your flight booking. You will be returned to your search results after registration.
            </p>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div style="background: #f8d7da; color: #721c24; padding: 0.75rem; border-radius: 5px; margin-bottom: 1rem;">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div style="background: #d1edff; color: #155724; padding: 0.75rem; border-radius: 5px; margin-bottom: 1rem;">
            <?php echo $success; ?>
            <p style="margin-top: 1rem;">
                <a href="login.php" class="btn btn-primary" style="display: inline-block; padding: 0.75rem 1.5rem; text-decoration: none;">Login Now</a>
                <a href="<?php echo SITE_URL; ?>" class="btn btn-secondary" style="display: inline-block; padding: 0.75rem 1.5rem; text-decoration: none; margin-left: 0.5rem;">Go to Homepage</a>
            </p>
        </div>
    <?php endif; ?>
    
    <?php if (!$success && empty($_SESSION['user_id'])): ?>
    <form method="POST" action="">
        <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
            <div>
                <label style="display: block; margin-bottom: 0.5rem; font-weight: bold; color: #333;">First Name *</label>
                <input type="text" name="first_name" required style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px;" 
                       value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; font-weight: bold; color: #333;">Last Name *</label>
                <input type="text" name="last_name" required style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px;"
                       value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
            </div>
        </div>
        
        <div style="margin-bottom: 1rem;">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: bold; color: #333;">Email Address *</label>
            <input type="email" name="email" required style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px;"
                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
        </div>
        
        <div style="margin-bottom: 1rem;">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: bold; color: #333;">Phone Number *</label>
            <input type="tel" name="phone" required style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px;"
                   value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
        </div>
        
        <div style="margin-bottom: 1rem;">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: bold; color: #333;">Password *</label>
            <input type="password" name="password" required style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px;">
            <small style="color: #666;">Minimum 6 characters</small>
        </div>
        
        <div style="margin-bottom: 1rem;">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: bold; color: #333;">Confirm Password *</label>
            <input type="password" name="confirm_password" required style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px;">
        </div>
        
        <div style="margin-bottom: 1rem; background: #f8f9fa; padding: 1rem; border-radius: 5px;">
            <p style="margin: 0; color: #666; font-size: 0.9rem;">
                By registering, you agree to our <a href="terms.php" style="color: #007bff;">Terms of Service</a> and <a href="privacy.php" style="color: #007bff;">Privacy Policy</a>.
            </p>
        </div>
        
        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.75rem; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 1rem;">
            <?php if ($flight_search_data || (isset($_GET['redirect']) && strpos($_GET['redirect'], 'flight') !== false)): ?>
                Create Account & Continue Booking
            <?php else: ?>
                Create Account
            <?php endif; ?>
        </button>
        
        <div style="text-align: center; margin-top: 1rem;">
            <p>Already have an account? 
                <a href="login.php<?php echo !empty($redirect) ? '?redirect=' . urlencode($redirect) : ''; ?>" 
                   style="color: #007bff; text-decoration: none;">
                    Login here
                </a>
            </p>
        </div>
    </form>
    <?php endif; ?>
</div>

<?php
require_once 'includes/footer.php';
?>