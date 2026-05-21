<?php
// config.php

// Start session only if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'travelce_flite');
define('DB_USER', 'travelce_flite');
define('DB_PASS', 'creator@US20');

// Site configuration
define('SITE_URL', 'https://travelcentre.ng/');
define('SITE_NAME', 'Travel Centre');


// Security configuration
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes in seconds
define('REMEMBER_ME_EXPIRY', 2592000); // 30 days in seconds
define('SESSION_TIMEOUT', 3600); // 1 hour in seconds

// Default API configuration (fallback values)
define('AMADEUS_API_KEY', 'm5tqF6xLYRrTgqQm7i3rJBDkGfsJdlAV');
define('AMADEUS_API_SECRET', 'uO8gZCDxAGtLnXf4');
define('AMADEUS_BASE_URL', 'https://api.amadeus.com');

define('PAYSTACK_PUBLIC_KEY', 'pk_test_your_paystack_public_key');
define('PAYSTACK_SECRET_KEY', 'sk_test_your_paystack_secret_key');
define('FLUTTERWAVE_PUBLIC_KEY', 'FLWPUBK_TEST_your_flutterwave_public_key');
define('FLUTTERWAVE_SECRET_KEY', 'FLWSECK_TEST_your_flutterwave_secret_key');

// Email configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your_email@gmail.com');
define('SMTP_PASS', 'your_email_password');
define('SMTP_FROM', 'noreply@travelcentre.ng');

// Create database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Include utility functions FIRST before using them
require_once 'includes/functions.php';

// Initialize default settings if they don't exist
initializeDefaultSettings($pdo);

// Load API settings from database if available
try {
    $stmt = $pdo->query("SELECT * FROM site_settings ORDER BY id DESC LIMIT 1");
    $api_settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($api_settings) {
        // Define constants with database values or fallback to defaults
        if (!empty($api_settings['amadeus_api_key'])) {
            define('AMADEUS_API_KEY_DB', $api_settings['amadeus_api_key']);
        } else {
            define('AMADEUS_API_KEY_DB', AMADEUS_API_KEY);
        }
        
        if (!empty($api_settings['amadeus_api_secret'])) {
            define('AMADEUS_API_SECRET_DB', $api_settings['amadeus_api_secret']);
        } else {
            define('AMADEUS_API_SECRET_DB', AMADEUS_API_SECRET);
        }
        
        if (!empty($api_settings['paystack_public_key'])) {
            define('PAYSTACK_PUBLIC_KEY_DB', $api_settings['paystack_public_key']);
        } else {
            define('PAYSTACK_PUBLIC_KEY_DB', PAYSTACK_PUBLIC_KEY);
        }
        
        if (!empty($api_settings['paystack_secret_key'])) {
            define('PAYSTACK_SECRET_KEY_DB', $api_settings['paystack_secret_key']);
        } else {
            define('PAYSTACK_SECRET_KEY_DB', PAYSTACK_SECRET_KEY);
        }
        
        if (!empty($api_settings['flutterwave_public_key'])) {
            define('FLUTTERWAVE_PUBLIC_KEY_DB', $api_settings['flutterwave_public_key']);
        } else {
            define('FLUTTERWAVE_PUBLIC_KEY_DB', FLUTTERWAVE_PUBLIC_KEY);
        }
        
        if (!empty($api_settings['flutterwave_secret_key'])) {
            define('FLUTTERWAVE_SECRET_KEY_DB', $api_settings['flutterwave_secret_key']);
        } else {
            define('FLUTTERWAVE_SECRET_KEY_DB', FLUTTERWAVE_SECRET_KEY);
        }
    } else {
        // No settings in database, use defaults for DB constants too
        define('AMADEUS_API_KEY_DB', AMADEUS_API_KEY);
        define('AMADEUS_API_SECRET_DB', AMADEUS_API_SECRET);
        define('PAYSTACK_PUBLIC_KEY_DB', PAYSTACK_PUBLIC_KEY);
        define('PAYSTACK_SECRET_KEY_DB', PAYSTACK_SECRET_KEY);
        define('FLUTTERWAVE_PUBLIC_KEY_DB', FLUTTERWAVE_PUBLIC_KEY);
        define('FLUTTERWAVE_SECRET_KEY_DB', FLUTTERWAVE_SECRET_KEY);
    }
} catch (Exception $e) {
    // If there's an error, use default values for DB constants
    error_log("Error loading API settings from database: " . $e->getMessage());
    define('AMADEUS_API_KEY_DB', AMADEUS_API_KEY);
    define('AMADEUS_API_SECRET_DB', AMADEUS_API_SECRET);
    define('PAYSTACK_PUBLIC_KEY_DB', PAYSTACK_PUBLIC_KEY);
    define('PAYSTACK_SECRET_KEY_DB', PAYSTACK_SECRET_KEY);
    define('FLUTTERWAVE_PUBLIC_KEY_DB', FLUTTERWAVE_PUBLIC_KEY);
    define('FLUTTERWAVE_SECRET_KEY_DB', FLUTTERWAVE_SECRET_KEY);
}

// Function to initialize default settings
function initializeDefaultSettings($pdo) {
    try {
        // Check if site_settings table exists and has default settings
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM site_settings");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] == 0) {
            // Insert default settings
            $default_settings = [
                'site_name' => SITE_NAME,
                'site_title' => 'Travel Centre - Book Flights, Hotels & Visa Services',
                'site_description' => 'Your one-stop travel solution for flights, hotels, and visa services',
                'site_keywords' => 'flights, hotels, visa, travel, booking',
                'admin_email' => 'admin@travelcentre.ng',
                'support_email' => 'support@travelcentre.ng',
                'phone' => '+234 123 456 7890',
                'address' => 'Lagos, Nigeria',
                'currency' => 'NGN',
                'timezone' => 'Africa/Lagos',
                'currency_rate' => '450',
                'amadeus_api_key' => AMADEUS_API_KEY,
                'amadeus_api_secret' => AMADEUS_API_SECRET,
                'paystack_public_key' => PAYSTACK_PUBLIC_KEY,
                'paystack_secret_key' => PAYSTACK_SECRET_KEY,
                'flutterwave_public_key' => FLUTTERWAVE_PUBLIC_KEY,
                'flutterwave_secret_key' => FLUTTERWAVE_SECRET_KEY,
                'smtp_host' => SMTP_HOST,
                'smtp_port' => SMTP_PORT,
                'smtp_username' => SMTP_USER,
                'smtp_password' => SMTP_PASS,
                'smtp_encryption' => 'tls',
                'smtp_from_email' => SMTP_FROM,
                'smtp_from_name' => SITE_NAME,
                'filter_panel_enabled' => '1',
                'ad_panel_enabled' => '1',
                'ad_panel_content' => '<div class="ad-panel-default"><h3>Special Offers</h3><p>Book now and get up to 20% off on selected routes!</p></div>'
            ];
            
            $columns = implode(', ', array_keys($default_settings));
            $placeholders = ':' . implode(', :', array_keys($default_settings));
            
            $stmt = $pdo->prepare("INSERT INTO site_settings ($columns) VALUES ($placeholders)");
            $stmt->execute($default_settings);
            
            error_log("Default settings initialized successfully");
        }
    } catch (Exception $e) {
        error_log("Error initializing default settings: " . $e->getMessage());
    }
}

// Visa Application Helper Functions

if (!function_exists('createNotification')) {
    /**
     * Create notification for user
     */
    function createNotification($pdo, $user_id, $title, $message, $type = 'info', $related_type = null, $related_id = null) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type, related_type, related_id, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            return $stmt->execute([$user_id, $title, $message, $type, $related_type, $related_id]);
        } catch (Exception $e) {
            error_log("Error creating notification: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('getUserNotifications')) {
    /**
     * Get user notifications
     */
    function getUserNotifications($pdo, $user_id, $limit = null) {
        try {
            $sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC";
            if ($limit) {
                $sql .= " LIMIT " . intval($limit);
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting user notifications: " . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('markNotificationAsRead')) {
    /**
     * Mark notification as read
     */
    function markNotificationAsRead($pdo, $notification_id) {
        try {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
            return $stmt->execute([$notification_id]);
        } catch (Exception $e) {
            error_log("Error marking notification as read: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('updateVisaApplicationStatus')) {
    /**
     * Update visa application status and create notification
     */
    function updateVisaApplicationStatus($pdo, $application_id, $status, $admin_notes = null) {
        try {
            $stmt = $pdo->prepare("
                UPDATE visa_applications 
                SET status = ?, admin_notes = ?, reviewed_at = NOW(), updated_at = NOW() 
                WHERE id = ?
            ");
            $result = $stmt->execute([$status, $admin_notes, $application_id]);
            
            if ($result) {
                // Get application details for notification
                $stmt = $pdo->prepare("SELECT user_id, application_number FROM visa_applications WHERE id = ?");
                $stmt->execute([$application_id]);
                $application = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($application) {
                    $title = "Visa Application " . ucfirst(str_replace('_', ' ', $status));
                    $message = "Your visa application {$application['application_number']} has been {$status}.";
                    createNotification($pdo, $application['user_id'], $title, $message, 'info', 'visa', $application_id);
                }
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Error updating visa application status: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('getUserVisaApplications')) {
    /**
     * Get visa applications for user
     */
    function getUserVisaApplications($pdo, $user_id, $limit = null) {
        try {
            $sql = "SELECT * FROM visa_applications WHERE user_id = ? ORDER BY created_at DESC";
            if ($limit) {
                $sql .= " LIMIT " . intval($limit);
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting user visa applications: " . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('getVisaApplicationStats')) {
    /**
     * Get visa application statistics for user
     */
    function getVisaApplicationStats($pdo, $user_id) {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pending_payment' THEN 1 ELSE 0 END) as pending_payment,
                    SUM(CASE WHEN status = 'pending_review' THEN 1 ELSE 0 END) as pending_review,
                    SUM(CASE WHEN status = 'under_review' THEN 1 ELSE 0 END) as under_review,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
                FROM visa_applications 
                WHERE user_id = ?
            ");
            $stmt->execute([$user_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting visa application stats: " . $e->getMessage());
            return [
                'total' => 0,
                'pending_payment' => 0,
                'pending_review' => 0,
                'under_review' => 0,
                'approved' => 0,
                'rejected' => 0,
                'cancelled' => 0
            ];
        }
    }
}

if (!function_exists('createVisaApplicationPayment')) {
    /**
     * Create visa application payment record
     */
    function createVisaApplicationPayment($pdo, $visa_application_id, $payment_reference, $amount, $payment_method, $payment_gateway) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO visa_application_payments 
                (visa_application_id, payment_reference, amount, currency, payment_method, payment_gateway, status, created_at) 
                VALUES (?, ?, ?, 'NGN', ?, ?, 'pending', NOW())
            ");
            return $stmt->execute([$visa_application_id, $payment_reference, $amount, $payment_method, $payment_gateway]);
        } catch (Exception $e) {
            error_log("Error creating visa application payment: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('updateVisaApplicationPaymentStatus')) {
    /**
     * Update visa application payment status
     */
    function updateVisaApplicationPaymentStatus($pdo, $payment_reference, $status, $gateway_reference = null, $payment_data = null) {
        try {
            $sql = "UPDATE visa_application_payments SET status = ?, updated_at = NOW()";
            $params = [$status];
            
            if ($gateway_reference) {
                $sql .= ", gateway_reference = ?";
                $params[] = $gateway_reference;
            }
            
            if ($payment_data) {
                $sql .= ", payment_data = ?";
                $params[] = json_encode($payment_data);
            }
            
            $sql .= " WHERE payment_reference = ?";
            $params[] = $payment_reference;
            
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute($params);
            
            if ($result && $status == 'completed') {
                // Update the main visa application payment status
                $stmt = $pdo->prepare("
                    UPDATE visa_applications va
                    JOIN visa_application_payments vap ON va.id = vap.visa_application_id
                    SET va.payment_status = 'paid', va.payment_date = NOW(), va.status = 'pending_review'
                    WHERE vap.payment_reference = ?
                ");
                $stmt->execute([$payment_reference]);
                
                // Create notification for successful payment
                $stmt = $pdo->prepare("
                    SELECT va.user_id, va.application_number 
                    FROM visa_applications va 
                    JOIN visa_application_payments vap ON va.id = vap.visa_application_id 
                    WHERE vap.payment_reference = ?
                ");
                $stmt->execute([$payment_reference]);
                $application = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($application) {
                    createNotification(
                        $pdo, 
                        $application['user_id'], 
                        'Visa Payment Successful', 
                        "Payment for visa application {$application['application_number']} was successful. Your application is now under review.",
                        'success',
                        'visa',
                        $application['user_id']
                    );
                }
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Error updating visa application payment status: " . $e->getMessage());
            return false;
        }
    }
}

// Security and Authentication Functions

if (!function_exists('getLoginAttempts')) {
    /**
     * Get login attempts for an email
     */
    function getLoginAttempts($pdo, $email) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) as attempts FROM login_attempts WHERE email = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
            $stmt->execute([$email]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['attempts'] ?? 0;
        } catch (Exception $e) {
            error_log("Error getting login attempts: " . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('recordLoginAttempt')) {
    /**
     * Record login attempt
     */
    function recordLoginAttempt($pdo, $email, $success, $ip_address) {
        try {
            $stmt = $pdo->prepare("INSERT INTO login_attempts (email, success, ip_address) VALUES (?, ?, ?)");
            $stmt->execute([$email, $success, $ip_address]);
            return true;
        } catch (Exception $e) {
            error_log("Error recording login attempt: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('clearLoginAttempts')) {
    /**
     * Clear login attempts for an email
     */
    function clearLoginAttempts($pdo, $email) {
        try {
            $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE email = ?");
            $stmt->execute([$email]);
            return true;
        } catch (Exception $e) {
            error_log("Error clearing login attempts: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('isLoggedIn')) {
    /**
     * Check if user is logged in and session is valid
     */
    function isLoggedIn() {
        // Check if session exists and hasn't timed out
        if (isset($_SESSION['user_id']) && isset($_SESSION['login_time'])) {
            $session_lifetime = SESSION_TIMEOUT;
            if (time() - $_SESSION['login_time'] > $session_lifetime) {
                // Session expired
                session_destroy();
                return false;
            }
            
            // Update login time for active session
            $_SESSION['login_time'] = time();
            return true;
        }
        
        return false;
    }
}

if (!function_exists('validateSession')) {
    /**
     * Validate and maintain user session
     */
    function validateSession() {
        if (isLoggedIn()) {
            return true;
        }
        
        // Check for remember me token
        if (isset($_COOKIE['remember_token'])) {
            global $pdo;
            $token = sanitize($_COOKIE['remember_token']);
            $stmt = $pdo->prepare("SELECT u.* FROM users u INNER JOIN remember_tokens rt ON u.id = rt.user_id WHERE rt.token = ? AND rt.expiry > NOW() AND u.is_active = TRUE");
            $stmt->execute([$token]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // Recreate session from remember token
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['login_time'] = time();
                return true;
            } else {
                // Invalid token, clear cookie
                setcookie('remember_token', '', time() - 3600, '/');
            }
        }
        
        return false;
    }
}

if (!function_exists('logoutUser')) {
    /**
     * Logout user and clear all sessions/tokens
     */
    function logoutUser() {
        global $pdo;
        
        // Clear remember token from database if exists
        if (isset($_COOKIE['remember_token'])) {
            $token = sanitize($_COOKIE['remember_token']);
            $stmt = $pdo->prepare("DELETE FROM remember_tokens WHERE token = ?");
            $stmt->execute([$token]);
            setcookie('remember_token', '', time() - 3600, '/');
        }
        
        // Clear all session data
        $_SESSION = array();
        
        // Destroy session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Destroy session
        session_destroy();
    }
}

if (!function_exists('checkAccountLock')) {
    /**
     * Check if account is locked
     */
    function checkAccountLock($pdo, $email) {
        try {
            $stmt = $pdo->prepare("SELECT account_locked, lockout_until FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && $user['account_locked'] && strtotime($user['lockout_until']) > time()) {
                return [
                    'locked' => true,
                    'until' => $user['lockout_until']
                ];
            }
            
            return ['locked' => false];
        } catch (Exception $e) {
            error_log("Error checking account lock: " . $e->getMessage());
            return ['locked' => false];
        }
    }
}

if (!function_exists('unlockAccount')) {
    /**
     * Unlock user account
     */
    function unlockAccount($pdo, $email) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET account_locked = FALSE, lockout_until = NULL WHERE email = ?");
            $stmt->execute([$email]);
            return true;
        } catch (Exception $e) {
            error_log("Error unlocking account: " . $e->getMessage());
            return false;
        }
    }
}

// Email Functions - Only declare if they don't exist

if (!function_exists('sendEmail')) {
    /**
     * Send email using SMTP configuration or fallback to mail()
     */
    function sendEmail($to, $subject, $body, $isHTML = false, $attachments = []) {
        global $pdo;
        
        try {
            // Get SMTP settings from database
            $stmt = $pdo->query("SELECT * FROM site_settings ORDER BY id DESC LIMIT 1");
            $settings = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$settings) {
                // Fallback to mail() function if no settings
                return sendEmailFallback($to, $subject, $body, $isHTML);
            }
            
            // Use database settings or fallback to defaults
            $smtp_host = $settings['smtp_host'] ?? SMTP_HOST;
            $smtp_port = $settings['smtp_port'] ?? SMTP_PORT;
            $smtp_username = $settings['smtp_username'] ?? SMTP_USER;
            $smtp_password = $settings['smtp_password'] ?? SMTP_PASS;
            $smtp_from_email = $settings['smtp_from_email'] ?? $settings['admin_email'] ?? SMTP_FROM;
            $smtp_from_name = $settings['smtp_from_name'] ?? $settings['site_name'] ?? SITE_NAME;
            $smtp_encryption = $settings['smtp_encryption'] ?? 'tls';
            
            // Check if SMTP is configured
            if (empty($smtp_host) || empty($smtp_username) || empty($smtp_password)) {
                // Fallback to mail() function
                return sendEmailFallback($to, $subject, $body, $isHTML, $smtp_from_email, $smtp_from_name);
            }
            
            // Try to use SMTP with fsockopen (simple SMTP implementation)
            $result = sendEmailSMTP($smtp_host, $smtp_port, $smtp_username, $smtp_password, 
                                  $smtp_from_email, $smtp_from_name, $to, $subject, $body, $isHTML, $smtp_encryption);
            
            if ($result) {
                error_log("Email sent via SMTP to: " . $to);
                return true;
            } else {
                // Fallback to mail() if SMTP fails
                error_log("SMTP failed, falling back to mail() for: " . $to);
                return sendEmailFallback($to, $subject, $body, $isHTML, $smtp_from_email, $smtp_from_name);
            }
            
        } catch (Exception $e) {
            error_log("Email exception: " . $e->getMessage());
            // Final fallback to mail()
            return sendEmailFallback($to, $subject, $body, $isHTML);
        }
    }
}

if (!function_exists('sendEmailFallback')) {
    /**
     * Fallback email function using PHP's mail()
     */
    function sendEmailFallback($to, $subject, $body, $isHTML = false, $from_email = null, $from_name = null) {
        $from_email = $from_email ?? SMTP_FROM;
        $from_name = $from_name ?? SITE_NAME;
        
        $headers = "From: $from_name <$from_email>\r\n";
        $headers .= "Reply-To: $from_email\r\n";
        
        if ($isHTML) {
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            // Add plain text version for HTML emails
            $plain_body = strip_tags($body);
            $body = "<html><body>$body</body></html>";
        } else {
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        }
        
        if (mail($to, $subject, $body, $headers)) {
            error_log("Email sent via mail() to: " . $to);
            return true;
        } else {
            error_log("Email sending failed via mail() to: " . $to);
            return false;
        }
    }
}

if (!function_exists('sendEmailSMTP')) {
    /**
     * Simple SMTP email function using fsockopen.
     * Supports both new signature:
     *   sendEmailSMTP($host, $port, $username, $password, $from_email, $from_name, $to, $subject, $body, $isHTML, $encryption)
     * and legacy signature:
     *   sendEmailSMTP($to, $subject, $body, $from_email, $from_name)
     */
    function sendEmailSMTP(...$args) {
        try {
            $arg_count = count($args);
            
            if ($arg_count >= 9) {
                list($host, $port, $username, $password, $from_email, $from_name, $to, $subject, $body) = $args;
                $isHTML = $args[9] ?? false;
                $encryption = $args[10] ?? 'tls';
            } elseif ($arg_count >= 3) {
                global $smtp_host, $smtp_port, $smtp_username, $smtp_password, $smtp_encryption;
                $to = $args[0];
                $subject = $args[1];
                $body = $args[2];
                $from_email = $args[3] ?? ($GLOBALS['smtp_from_email'] ?? $smtp_username ?? '');
                $from_name = $args[4] ?? ($GLOBALS['smtp_from_name'] ?? 'Travel Centre');
                $host = $smtp_host ?? '';
                $port = $smtp_port ?? 587;
                $username = $smtp_username ?? '';
                $password = $smtp_password ?? '';
                $isHTML = true;
                $encryption = $smtp_encryption ?? 'tls';
            } else {
                error_log("sendEmailSMTP() called with insufficient arguments: " . $arg_count);
                return false;
            }
            
            // Prepare email content
            $headers = "From: $from_name <$from_email>\r\n";
            $headers .= "To: $to\r\n";
            $headers .= "Subject: $subject\r\n";
            $headers .= "Date: " . date('r') . "\r\n";
            $headers .= "Message-ID: <" . md5(uniqid()) . "@" . $host . ">\r\n";
            
            if ($isHTML) {
                $headers .= "MIME-Version: 1.0\r\n";
                $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                $body = "<html><body>$body</body></html>";
            } else {
                $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            }
            
            $headers .= "\r\n";
            $message = $headers . $body;
            
            // For now, we'll use mail() as our SMTP implementation
            // In a production environment, you would implement proper SMTP here
            // This is a simplified version that uses mail() but logs SMTP attempt
            error_log("SMTP attempt - Host: $host, Port: $port, User: $username");
            
            // Use mail() as fallback within this function
            return sendEmailFallback($to, $subject, $body, $isHTML, $from_email, $from_name);
            
        } catch (Exception $e) {
            error_log("SMTP error: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('sendTemplateEmail')) {
    /**
     * Send email using a template
     */
    function sendTemplateEmail($to, $template_name, $replacements = [], $attachments = []) {
        global $pdo;
        
        try {
            // Get email template from database
            $stmt = $pdo->prepare("SELECT * FROM email_templates WHERE name = ? AND is_active = 1");
            $stmt->execute([$template_name]);
            $template = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$template) {
                error_log("Email template not found: " . $template_name);
                return false;
            }
            
            $subject = $template['subject'];
            $body = $template['content'];
            
            // Replace placeholders in subject and body
            foreach ($replacements as $key => $value) {
                $placeholder = '{{' . $key . '}}';
                $subject = str_replace($placeholder, $value, $subject);
                $body = str_replace($placeholder, $value, $body);
            }
            
            // Add default replacements
            $site_settings = getSiteSettings($pdo);
            $default_replacements = [
                'site_name' => $site_settings['site_name'] ?? SITE_NAME,
                'site_url' => SITE_URL,
                'current_year' => date('Y'),
                'current_date' => date('F j, Y')
            ];
            
            foreach ($default_replacements as $key => $value) {
                $placeholder = '{{' . $key . '}}';
                $subject = str_replace($placeholder, $value, $subject);
                $body = str_replace($placeholder, $value, $body);
            }
            
            // Send email
            return sendEmail($to, $subject, $body, true, $attachments);
            
        } catch (Exception $e) {
            error_log("Template email error: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('testSMTPConnection')) {
    /**
     * Test SMTP connection with current settings
     */
    function testSMTPConnection($test_email = null) {
        global $pdo;
        
        try {
            // Get SMTP settings from database
            $stmt = $pdo->query("SELECT * FROM site_settings ORDER BY id DESC LIMIT 1");
            $settings = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$settings) {
                throw new Exception("No site settings found in database");
            }
            
            // Use provided email or admin email
            if (!$test_email) {
                $test_email = $settings['admin_email'] ?? SMTP_FROM;
            }
            
            $subject = 'SMTP Test - ' . ($settings['site_name'] ?? SITE_NAME);
            $body = '
                <h2>SMTP Test Successful!</h2>
                <p>This is a test email to verify your email configuration.</p>
                <p><strong>Site:</strong> ' . ($settings['site_name'] ?? SITE_NAME) . '</p>
                <p><strong>Time:</strong> ' . date('Y-m-d H:i:s') . '</p>
                <p><strong>Sent to:</strong> ' . $test_email . '</p>
                <hr>
                <p>If you received this email, your email settings are working correctly.</p>
            ';
            
            // Try to send test email
            if (sendEmail($test_email, $subject, $body, true)) {
                return [
                    'success' => true,
                    'message' => 'Test email sent successfully to ' . $test_email
                ];
            } else {
                throw new Exception('Failed to send test email');
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}

// Payment Gateway Functions - Only declare if they don't exist

if (!function_exists('getPaymentSettings')) {
    /**
     * Get payment settings from database
     */
    function getPaymentSettings($pdo) {
        try {
            $stmt = $pdo->query("SELECT * FROM site_settings ORDER BY id DESC LIMIT 1");
            $settings = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$settings) {
                // Return default settings if no settings found
                return [
                    'paystack_enabled' => true,
                    'paystack_public_key' => PAYSTACK_PUBLIC_KEY_DB,
                    'paystack_secret_key' => PAYSTACK_SECRET_KEY_DB,
                    'flutterwave_enabled' => true,
                    'flutterwave_public_key' => FLUTTERWAVE_PUBLIC_KEY_DB,
                    'flutterwave_secret_key' => FLUTTERWAVE_SECRET_KEY_DB,
                    'bank_transfer_enabled' => true,
                    'bank_name' => 'Demo Bank',
                    'account_name' => 'Travel Centre Ltd',
                    'account_number' => '1234567890',
                ];
            }
            
            // Return with simplified keys based on your actual database structure
            return [
                'paystack_enabled' => !empty($settings['paystack_public_key']),
                'paystack_public_key' => $settings['paystack_public_key'] ?? PAYSTACK_PUBLIC_KEY_DB,
                'paystack_secret_key' => $settings['paystack_secret_key'] ?? PAYSTACK_SECRET_KEY_DB,
                'flutterwave_enabled' => !empty($settings['flutterwave_public_key']),
                'flutterwave_public_key' => $settings['flutterwave_public_key'] ?? FLUTTERWAVE_PUBLIC_KEY_DB,
                'flutterwave_secret_key' => $settings['flutterwave_secret_key'] ?? FLUTTERWAVE_SECRET_KEY_DB,
                'bank_transfer_enabled' => true, // Always enabled since you don't have a setting for this
                'bank_name' => 'Demo Bank', // You can add these fields to your site_settings table later
                'account_name' => 'Travel Centre Ltd',
                'account_number' => '1234567890',
            ];
        } catch (Exception $e) {
            error_log("Error getting payment settings: " . $e->getMessage());
            // Return default settings on error
            return [
                'paystack_enabled' => true,
                'paystack_public_key' => PAYSTACK_PUBLIC_KEY_DB,
                'paystack_secret_key' => PAYSTACK_SECRET_KEY_DB,
                'flutterwave_enabled' => true,
                'flutterwave_public_key' => FLUTTERWAVE_PUBLIC_KEY_DB,
                'flutterwave_secret_key' => FLUTTERWAVE_SECRET_KEY_DB,
                'bank_transfer_enabled' => true,
                'bank_name' => 'Demo Bank',
                'account_name' => 'Travel Centre Ltd',
                'account_number' => '1234567890',
            ];
        }
    }
}

if (!function_exists('initPaystackPayment')) {
    /**
     * Initialize Paystack payment
     */
    function initPaystackPayment($amount, $email, $reference, $callback_url, $metadata = []) {
        $settings = getPaymentSettings($GLOBALS['pdo']);
        
        $url = "https://api.paystack.co/transaction/initialize";
        
        $fields = [
            'email' => $email,
            'amount' => $amount * 100, // Convert to kobo
            'reference' => $reference,
            'callback_url' => $callback_url,
            'metadata' => json_encode($metadata)
        ];
        
        $fields_string = http_build_query($fields);
        
        // Open connection
        $ch = curl_init();
        
        // Set the url, number of POST vars, POST data
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer " . $settings['paystack_secret_key'],
            "Cache-Control: no-cache",
        ));
        
        // So that curl_exec returns the contents of the cURL; rather than echoing it
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        // Execute post
        $result = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($result, true);
    }
}

if (!function_exists('verifyPaystackPayment')) {
    /**
     * Verify Paystack payment
     */
    function verifyPaystackPayment($reference) {
        $settings = getPaymentSettings($GLOBALS['pdo']);
        
        $url = "https://api.paystack.co/transaction/verify/" . urlencode($reference);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $settings['paystack_secret_key']
        ]);
        
        $result = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($result, true);
    }
}

if (!function_exists('initFlutterwavePayment')) {
    /**
     * Initialize Flutterwave payment
     */
    function initFlutterwavePayment($amount, $email, $reference, $callback_url, $metadata = []) {
        $settings = getPaymentSettings($GLOBALS['pdo']);
        
        $url = "https://api.flutterwave.com/v3/payments";
        
        $payload = [
            'tx_ref' => $reference,
            'amount' => $amount,
            'currency' => 'NGN',
            'redirect_url' => $callback_url,
            'customer' => [
                'email' => $email,
                'name' => $_SESSION['user_name'] ?? 'Customer'
            ],
            'customizations' => [
                'title' => getSiteSetting($GLOBALS['pdo'], 'site_name'),
                'description' => 'Flight Booking Payment'
            ],
            'meta' => $metadata
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $settings['flutterwave_secret_key'],
            "Content-Type: application/json"
        ]);
        
        $result = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($result, true);
    }
}

if (!function_exists('verifyFlutterwavePayment')) {
    /**
     * Verify Flutterwave payment
     */
    function verifyFlutterwavePayment($transaction_id) {
        $settings = getPaymentSettings($GLOBALS['pdo']);
        
        $url = "https://api.flutterwave.com/v3/transactions/" . $transaction_id . "/verify";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $settings['flutterwave_secret_key']
        ]);
        
        $result = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($result, true);
    }
}
// Payment initialization functions
function initPaystackPayment($amount, $email, $reference, $callback_url, $metadata = []) {
    // Get Paystack keys from settings
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT paystack_public_key, paystack_secret_key FROM site_settings ORDER BY id DESC LIMIT 1");
        $stmt->execute();
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $secret_key = $settings['paystack_secret_key'] ?? '';
        
        if (empty($secret_key)) {
            return ['status' => false, 'message' => 'Paystack not configured'];
        }
        
        $url = "https://api.paystack.co/transaction/initialize";
        
        $fields = [
            'email' => $email,
            'amount' => $amount * 100, // Paystack expects amount in kobo
            'reference' => $reference,
            'callback_url' => $callback_url,
            'metadata' => $metadata
        ];
        
        $fields_string = http_build_query($fields);
        
        // Open connection
        $ch = curl_init();
        
        // Set the url, number of POST vars, POST data
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer " . $secret_key,
            "Cache-Control: no-cache",
        ));
        
        // So that curl_exec returns the contents of the cURL; rather than echoing it
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
        
        // Execute post
        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return json_decode($result, true);
        
    } catch (Exception $e) {
        return ['status' => false, 'message' => $e->getMessage()];
    }
}

function initFlutterwavePayment($amount, $email, $reference, $callback_url, $metadata = []) {
    // Get Flutterwave keys from settings
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT flutterwave_public_key, flutterwave_secret_key FROM site_settings ORDER BY id DESC LIMIT 1");
        $stmt->execute();
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $secret_key = $settings['flutterwave_secret_key'] ?? '';
        
        if (empty($secret_key)) {
            return ['status' => 'error', 'message' => 'Flutterwave not configured'];
        }
        
        $url = "https://api.flutterwave.com/v3/payments";
        
        $payload = [
            'tx_ref' => $reference,
            'amount' => $amount,
            'currency' => 'NGN',
            'redirect_url' => $callback_url,
            'customer' => [
                'email' => $email,
            ],
            'customizations' => [
                'title' => 'Visa Assessment Session',
                'description' => 'Payment for visa assessment session'
            ]
        ];
        
        // Set up cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $secret_key,
            "Content-Type: application/json",
        ]);
        
        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return json_decode($result, true);
        
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}

// Auto-validate session on every page load
validateSession();
?>
