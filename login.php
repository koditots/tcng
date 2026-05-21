<?php
// login.php
require_once 'config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    $redirect = $_GET['redirect'] ?? '';
    $pending_visa = $_SESSION['pending_visa_application'] ?? null;
    
    // Handle visa application redirect
    if ($pending_visa) {
        header("Location: visa-payment.php?application_id=" . $pending_visa);
        exit;
    }
    
    // Handle regular redirects
    if ($redirect) {
        if (filter_var($redirect, FILTER_VALIDATE_URL)) {
            redirect($redirect);
        } else {
            // Handle internal redirects like visa-payment.php
            if (strpos($redirect, 'visa-') !== false) {
                header("Location: $redirect");
                exit;
            }
            redirect($redirect);
        }
    } else {
        redirect('dashboard.php');
    }
}

$page_title = "Login";
$error = '';

// Get site settings for display
$site_name = getSiteSetting($pdo, 'site_name') ?: 'Travel Centre';
$site_logo = getSiteSetting($pdo, 'logo') ?: '';

// NEW: Capture flight search data from session for redirection
$flight_search_data = $_SESSION['flight_search_data'] ?? null;
$pending_booking = $_SESSION['pending_booking'] ?? null;
$flight_redirect = $_SESSION['flight_redirect'] ?? null;

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $remember_me = isset($_POST['remember_me']);
    
    // Rate limiting check
    $login_attempts = getLoginAttempts($pdo, $email);
    $max_attempts = 5;
    $lockout_time = 15 * 60; // 15 minutes
    
    if ($login_attempts >= $max_attempts) {
        $error = 'Too many login attempts. Please try again in 15 minutes.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = TRUE");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            // Check if account is locked
            if ($user['account_locked'] && strtotime($user['lockout_until']) > time()) {
                $error = 'Account temporarily locked. Please try again later.';
                recordLoginAttempt($pdo, $email, false, $_SERVER['REMOTE_ADDR'] ?? 'Unknown');
            } else {
                // Successful login
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['login_time'] = time();
                
                // Clear login attempts
                clearLoginAttempts($pdo, $email);
                
                // Update last login
                $stmt = $pdo->prepare("UPDATE users SET last_login = NOW(), login_count = login_count + 1, account_locked = FALSE, lockout_until = NULL WHERE id = ?");
                $stmt->execute([$user['id']]);
                
                // Remember me functionality
                if ($remember_me) {
                    $token = bin2hex(random_bytes(32));
                    $expiry = date('Y-m-d H:i:s', strtotime('+30 days'));
                    
                    $stmt = $pdo->prepare("INSERT INTO remember_tokens (user_id, token, expiry) VALUES (?, ?, ?)");
                    $stmt->execute([$user['id'], $token, $expiry]);
                    
                    setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', true, true);
                }
                
                // Add notification
                createNotification($pdo, $user['id'], 'Login Successful', 'You have successfully logged into your account.', 'success', 'auth');
                
                // Log the login
                error_log("User login: " . $user['email'] . " from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown'));
                
                // NEW: Enhanced redirect handling with better flight booking support
                $redirect = $_POST['redirect'] ?? '';
                $pending_visa = $_SESSION['pending_visa_application'] ?? null;
                $flight_redirect = $_SESSION['flight_redirect'] ?? null;
                $pending_booking = $_SESSION['pending_booking'] ?? null;
                
                // Priority 1: Pending visa application payment
                if ($pending_visa) {
                    // Clear the pending visa session
                    unset($_SESSION['pending_visa_application']);
                    // Create notification for pending visa application
                    createNotification(
                        $pdo, 
                        $user['id'], 
                        'Complete Visa Payment', 
                        'Please complete payment for your pending visa application.', 
                        'info', 
                        'visa', 
                        $pending_visa
                    );
                    header("Location: visa-payment.php?application_id=" . $pending_visa);
                    exit;
                }
                
                // NEW: Priority 2: Flight booking redirect with session data
                if ($flight_redirect && $pending_booking) {
                    $redirect_url = $flight_redirect;
                    
                    // Clear flight redirect session data
                    unset($_SESSION['flight_redirect']);
                    
                    // If it's a book-flight page, redirect with flight data
                    if (strpos($flight_redirect, 'book-flight.php') !== false) {
                        // Ensure we have flight data in session
                        if (isset($pending_booking['flight_data'])) {
                            // Redirect to book-flight.php with flight data
                            $flight_data_param = urlencode($pending_booking['flight_data']);
                            header("Location: book-flight.php?flight_data=" . $flight_data_param);
                            exit;
                        } else {
                            // Fallback: redirect to book-flight.php without data
                            header("Location: book-flight.php");
                            exit;
                        }
                    }
                    // If it's a flight search results page
                    elseif (strpos($flight_redirect, 'flights.php') !== false) {
                        header("Location: flights.php?search_restored=1");
                        exit;
                    }
                }
                
                // Priority 3: Redirect parameter from form
                if ($redirect) {
                    // Check if it's a flight booking redirect
                    if (strpos($redirect, 'book-flight.php') !== false) {
                        // Parse the flight data from redirect URL if present
                        $url_parts = parse_url($redirect);
                        if (isset($url_parts['query'])) {
                            parse_str($url_parts['query'], $query_params);
                            if (isset($query_params['flight_data'])) {
                                header("Location: book-flight.php?flight_data=" . $query_params['flight_data']);
                                exit;
                            }
                        }
                        header("Location: book-flight.php");
                        exit;
                    }
                    
                    if (filter_var($redirect, FILTER_VALIDATE_URL)) {
                        redirect($redirect);
                    } else {
                        // Handle internal redirects
                        header("Location: $redirect");
                        exit;
                    }
                }
                
                // Priority 4: Redirect to visa payment page if specified
                if ($redirect && strpos($redirect, 'visa-payment.php') !== false) {
                    if (filter_var($redirect, FILTER_VALIDATE_URL)) {
                        redirect($redirect);
                    } else {
                        header("Location: $redirect");
                        exit;
                    }
                }
                
                // Priority 5: Redirect to visa application page if specified
                if ($redirect && strpos($redirect, 'visa-application.php') !== false) {
                    header("Location: visa-application.php");
                    exit;
                }
                
                // Default redirect based on role
                if ($user['role'] === 'admin') {
                    redirect('admin/dashboard.php');
                } else {
                    redirect('dashboard.php');
                }
            }
        } else {
            // Failed login
            $error = 'Invalid email or password';
            recordLoginAttempt($pdo, $email, false, $_SERVER['REMOTE_ADDR'] ?? 'Unknown');
            
            // Lock account after max attempts
            if ($login_attempts + 1 >= $max_attempts) {
                $lockout_until = date('Y-m-d H:i:s', time() + $lockout_time);
                $stmt = $pdo->prepare("UPDATE users SET account_locked = TRUE, lockout_until = ? WHERE email = ?");
                $stmt->execute([$lockout_until, $email]);
                $error = 'Too many failed attempts. Account locked for 15 minutes.';
            }
        }
    }
}

// Check for remember me token
if (empty($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $token = sanitize($_COOKIE['remember_token']);
    $stmt = $pdo->prepare("SELECT u.* FROM users u INNER JOIN remember_tokens rt ON u.id = rt.user_id WHERE rt.token = ? AND rt.expiry > NOW() AND u.is_active = TRUE");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['login_time'] = time();
        
        // Update last login
        $stmt = $pdo->prepare("UPDATE users SET last_login = NOW(), login_count = login_count + 1 WHERE id = ?");
        $stmt->execute([$user['id']]);
        
        // NEW: Check for flight redirect after remember me login
        $flight_redirect = $_SESSION['flight_redirect'] ?? null;
        $pending_booking = $_SESSION['pending_booking'] ?? null;
        
        if ($flight_redirect && $pending_booking) {
            unset($_SESSION['flight_redirect']);
            if (isset($pending_booking['flight_data'])) {
                $flight_data_param = urlencode($pending_booking['flight_data']);
                header("Location: book-flight.php?flight_data=" . $flight_data_param);
                exit;
            } else {
                header("Location: $flight_redirect");
                exit;
            }
        }
        
        // Check for pending visa application
        $pending_visa = $_SESSION['pending_visa_application'] ?? null;
        if ($pending_visa) {
            header("Location: visa-payment.php?application_id=" . $pending_visa);
            exit;
        }
        
        redirect('dashboard.php');
    } else {
        // Invalid token, clear cookie
        setcookie('remember_token', '', time() - 3600, '/');
    }
}

require_once 'includes/header.php';
?>

<div style="max-width: 400px; margin: 4rem auto; padding: 2rem; background: white; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
    <!-- Site Logo and Name -->
    <div style="text-align: center; margin-bottom: 2rem;">
        <?php if ($site_logo): ?>
            <img src="<?php echo $site_logo; ?>" alt="<?php echo $site_name; ?>" style="max-height: 60px; margin-bottom: 1rem;">
        <?php endif; ?>
        <h2 style="color: #333; margin-bottom: 0.5rem;">Welcome Back</h2>
        <p style="color: #666; margin: 0;">Login to your <?php echo $site_name; ?> account</p>
    </div>
    
    <?php if ($error): ?>
        <div style="background: #f8d7da; color: #721c24; padding: 0.75rem; border-radius: 5px; margin-bottom: 1rem; border-left: 4px solid #dc3545;">
            <div style="display: flex; align-items: center;">
                <span style="margin-right: 0.5rem;">⚠️</span>
                <strong>Error:</strong>
            </div>
            <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <!-- Success message after registration or password reset -->
    <?php if (isset($_GET['registered'])): ?>
        <div style="background: #d4edda; color: #155724; padding: 0.75rem; border-radius: 5px; margin-bottom: 1rem; border-left: 4px solid #28a745;">
            ✅ Registration successful! Please login with your credentials.
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['password_reset'])): ?>
        <div style="background: #d4edda; color: #155724; padding: 0.75rem; border-radius: 5px; margin-bottom: 1rem; border-left: 4px solid #28a745;">
            ✅ Password reset successful! Please login with your new password.
        </div>
    <?php endif; ?>
    
    <!-- NEW: Flight Search/Booking Notice -->
    <?php if ($pending_booking || $flight_redirect || (isset($_GET['redirect']) && strpos($_GET['redirect'], 'flight') !== false)): ?>
        <div style="background: #e7f3ff; color: #004085; padding: 0.75rem; border-radius: 5px; margin-bottom: 1rem; border-left: 4px solid #007bff;">
            <div style="display: flex; align-items: center; margin-bottom: 0.5rem;">
                <span style="margin-right: 0.5rem;">✈️</span>
                <strong>Flight Booking Notice</strong>
            </div>
            <p style="margin: 0; font-size: 0.9rem;">
                Please login to continue with your flight booking. You will be returned to your booking page after login.
            </p>
        </div>
    <?php endif; ?>
    
    <!-- Visa Application Notice -->
    <?php if (isset($_SESSION['pending_visa_application']) || (isset($_GET['redirect']) && strpos($_GET['redirect'], 'visa') !== false)): ?>
        <div style="background: #d1ecf1; color: #0c5460; padding: 0.75rem; border-radius: 5px; margin-bottom: 1rem; border-left: 4px solid #17a2b8;">
            <div style="display: flex; align-items: center; margin-bottom: 0.5rem;">
                <span style="margin-right: 0.5rem;">🛂</span>
                <strong>Visa Application Notice</strong>
            </div>
            <p style="margin: 0; font-size: 0.9rem;">
                <?php if (isset($_SESSION['pending_visa_application'])): ?>
                    Please login to complete payment for your visa application.
                <?php else: ?>
                    Please login to continue with your visa application.
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>
    
    <form method="POST" action="" id="loginForm">
        <input type="hidden" name="redirect" value="<?php echo $_GET['redirect'] ?? ''; ?>">
        
        <div style="margin-bottom: 1rem;">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: bold; color: #333;">
                Email Address
                <span style="color: #dc3545;">*</span>
            </label>
            <input type="email" name="email" value="<?php echo $_POST['email'] ?? ''; ?>" required 
                   style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; font-size: 1rem;"
                   placeholder="Enter your email address"
                   autocomplete="email">
        </div>
        
        <div style="margin-bottom: 1rem;">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: bold; color: #333;">
                Password
                <span style="color: #dc3545;">*</span>
            </label>
            <div style="position: relative;">
                <input type="password" name="password" id="password" required 
                       style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; font-size: 1rem; padding-right: 2.5rem;"
                       placeholder="Enter your password"
                       autocomplete="current-password">
                <button type="button" id="togglePassword" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #666;">
                    👁️
                </button>
            </div>
        </div>
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <label style="display: flex; align-items: center; cursor: pointer;">
                <input type="checkbox" name="remember_me" style="margin-right: 0.5rem;">
                <span style="color: #333;">Remember me</span>
            </label>
            
            <a href="forgot-password.php" style="color: #007bff; text-decoration: none; font-size: 0.9rem;">
                Forgot password?
            </a>
        </div>
        
        <button type="submit" class="btn btn-primary" id="loginBtn" style="width: 100%; padding: 0.75rem; margin-bottom: 1.5rem; font-size: 1rem;">
            <?php if (isset($_SESSION['pending_visa_application']) || (isset($_GET['redirect']) && strpos($_GET['redirect'], 'visa') !== false)): ?>
                Login & Continue Visa Application
            <?php elseif ($pending_booking || $flight_redirect || (isset($_GET['redirect']) && strpos($_GET['redirect'], 'flight') !== false)): ?>
                Login & Continue Flight Booking
            <?php else: ?>
                Login to Account
            <?php endif; ?>
        </button>
        
        <div style="text-align: center; padding-top: 1.5rem; border-top: 1px solid #eee;">
            <p style="color: #666; margin: 0;">
                Don't have an account? 
                <a href="register.php<?php echo isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''; ?>" 
                   style="color: #007bff; text-decoration: none; font-weight: bold;">
                    Create account
                </a>
            </p>
        </div>
    </form>
    
    <!-- Security Notice -->
    <div style="background: #fff3cd; color: #856404; padding: 0.75rem; border-radius: 5px; margin-top: 1.5rem; border-left: 4px solid #ffc107;">
        <small>
            <strong>🔒 Security Notice:</strong> 
            Ensure you're on the correct website and protect your login credentials.
        </small>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const loginBtn = document.getElementById('loginBtn');
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    
    // Toggle password visibility
    togglePassword.addEventListener('click', function() {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        togglePassword.textContent = type === 'password' ? '👁️' : '🔒';
    });
    
    // Form submission handling - FIXED: Remove the JavaScript that might be interfering
    loginForm.addEventListener('submit', function() {
        // Show loading state
        loginBtn.disabled = true;
        
        // Update button text based on context
        const hasVisaRedirect = window.location.href.includes('visa') || 
                               document.querySelector('input[name="redirect"]').value.includes('visa');
        const hasFlightRedirect = window.location.href.includes('flight') || 
                                 document.querySelector('input[name="redirect"]').value.includes('flight') ||
                                 <?php echo ($pending_booking || $flight_redirect) ? 'true' : 'false'; ?>;
        
        if (hasVisaRedirect) {
            loginBtn.innerHTML = 'Logging in & Redirecting... <span style="margin-left: 0.5rem;">⏳</span>';
        } else if (hasFlightRedirect) {
            loginBtn.innerHTML = 'Logging in & Continuing Booking... <span style="margin-left: 0.5rem;">✈️</span>';
        } else {
            loginBtn.innerHTML = 'Logging in... <span style="margin-left: 0.5rem;">⏳</span>';
        }
        
        // Allow the form to submit normally - don't prevent default
        return true;
    });
    
    // Auto-focus email field
    const emailInput = document.querySelector('input[name="email"]');
    if (emailInput && !emailInput.value) {
        emailInput.focus();
    }
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

/* Flight-specific button styling */
.btn-primary[style*="Flight Booking"] {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
}

.btn-primary[style*="Flight Booking"]:hover {
    background: linear-gradient(135deg, #0056b3 0%, #004085 100%);
}

/* Visa-specific button styling */
.btn-primary[style*="Visa Application"] {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.btn-primary[style*="Visa Application"]:hover {
    background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
}

/* Responsive design */
@media (max-width: 576px) {
    div[style*="max-width: 400px;"] {
        margin: 2rem 1rem !important;
        padding: 1.5rem !important;
    }
}

/* Input focus styles */
input:focus {
    border-color: #007bff !important;
    outline: none;
    box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
}
</style>

<?php
require_once 'includes/footer.php';
?>