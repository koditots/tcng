<?php
// admin/visa-application.php
session_start();
require_once '../config.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if required functions exist, if not define them
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
}

if (!function_exists('isAdmin')) {
    function isAdmin() {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
    }
}

if (!function_exists('redirect')) {
    function redirect($url) {
        header("Location: $url");
        exit;
    }
}

// Authentication check
if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

$page_title = "Visa Applications Management";

// Check admin permissions
$admin_role = $_SESSION['user_role'] ?? 'user';
if ($admin_role !== 'super_admin' && $admin_role !== 'visa_manager' && $admin_role !== 'admin') {
    $_SESSION['error'] = "You don't have permission to access visa applications";
    header('Location: index.php');
    exit();
}

// Initialize variables
$error = '';
$success = '';
$applications = [];
$application_details = [];
$countries_fees = [];
$status_history = [];

// Application statuses
$statuses = [
    'awaiting_documentation' => 'Awaiting Documentation',
    'documentation_received' => 'Documentation Received',
    'processing_documentation' => 'Processing Documentation',
    'interview_scheduled' => 'Interview Scheduled',
    'approved' => 'Approved',
    'denied' => 'Denied'
];

// Helper function for sanitization
if (!function_exists('sanitize')) {
    function sanitize($input) {
        if (is_array($input)) {
            return array_map('sanitize', $input);
        }
        return htmlspecialchars(trim($input ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

// Helper function for sending HTML emails
if (!function_exists('sendHTMLEmail')) {
    function sendHTMLEmail($to, $subject, $body) {
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: Travel Centre <support@travelcentre.ng>" . "\r\n";
        $headers .= "Reply-To: support@travelcentre.ng" . "\r\n";
        
        return @mail($to, $subject, $body, $headers);
    }
}

// Helper function to get status description
if (!function_exists('getStatusDescription')) {
    function getStatusDescription($status) {
        $descriptions = [
            'awaiting_documentation' => 'Please upload all required documents to proceed with your application.',
            'documentation_received' => 'We have received your documents and are reviewing them.',
            'processing_documentation' => 'Your documents are being processed and verified.',
            'interview_scheduled' => 'An interview has been scheduled. Please check your email for details.',
            'approved' => 'Congratulations! Your visa application has been approved.',
            'denied' => 'Unfortunately, your visa application has been denied. Please contact support for more information.'
        ];
        
        return $descriptions[$status] ?? 'Your application status has been updated.';
    }
}

// Helper function to get next steps
if (!function_exists('getNextSteps')) {
    function getNextSteps($status) {
        $steps = [
            'awaiting_documentation' => '
                <ul style="color: #718096; padding-left: 20px;">
                    <li>Upload all required documents through our portal</li>
                    <li>Ensure documents are clear and legible</li>
                    <li>Complete document submission within 48 hours</li>
                </ul>',
            'documentation_received' => '
                <ul style="color: #718096; padding-left: 20px;">
                    <li>Wait for document verification</li>
                    <li>Check your email regularly for updates</li>
                    <li>Ensure your contact information is up to date</li>
                </ul>',
            'processing_documentation' => '
                <ul style="color: #718096; padding-left: 20px;">
                    <li>Document verification in progress</li>
                    <li>This may take 3-5 business days</li>
                    <li>No action required from your side at this time</li>
                </ul>',
            'interview_scheduled' => '
                <ul style="color: #718096; padding-left: 20px;">
                    <li>Prepare for your scheduled interview</li>
                    <li>Bring all original documents</li>
                    <li>Arrive 15 minutes early</li>
                </ul>',
            'approved' => '
                <ul style="color: #718096; padding-left: 20px;">
                    <li>Your visa will be processed and delivered</li>
                    <li>You will receive tracking information</li>
                    <li>Prepare for your travel</li>
                </ul>',
            'denied' => '
                <ul style="color: #718096; padding-left: 20px;">
                    <li>Contact our support team for details</li>
                    <li>Review the reasons for denial</li>
                    <li>Consider reapplying after addressing issues</li>
                </ul>'
        ];
        
        return $steps[$status] ?? '<p style="color: #718096;">Please check your application portal regularly for updates.</p>';
    }
}

// Function to send status update email
if (!function_exists('sendStatusUpdateEmail')) {
    function sendStatusUpdateEmail($application, $new_status, $admin_notes = '') {
        global $pdo;
        
        // Get application email from contact_info JSON or fallback
        $contact_info = json_decode($application['contact_info'] ?? '{}', true);
        $email = $contact_info['email'] ?? $application['email'] ?? '';
        
        if (empty($email)) {
            error_log("No email found for application: " . $application['application_number']);
            return false;
        }
        
        $application_number = $application['application_number'];
        $tracking_id = $application['tracking_id'] ?? 'N/A';
        $country = $application['country'] ?? 'Unknown';
        
        $status_display = [
            'awaiting_documentation' => 'Awaiting Documentation',
            'documentation_received' => 'Documentation Received',
            'processing_documentation' => 'Processing Documentation',
            'interview_scheduled' => 'Interview Scheduled',
            'approved' => 'Approved',
            'denied' => 'Denied'
        ];
        
        $new_status_display = $status_display[$new_status] ?? $new_status;
        
        $subject = "Visa Application Status Update - {$application_number}";
        
        $website_email = 'support@travelcentre.ng';
        $website_url = 'https://travelcentre.ng';
        $phone = '+234 903 407 2383';
        $site_name = 'Travel Centre';

        // Get website settings
        try {
            $stmt = $pdo->query("SELECT admin_email, logo, site_name FROM site_settings ORDER BY id DESC LIMIT 1");
            $settings = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($settings) {
                if (!empty($settings['admin_email'])) {
                    $website_email = $settings['admin_email'];
                }
                if (!empty($settings['site_name'])) {
                    $site_name = $settings['site_name'];
                }
            }
        } catch (Exception $e) {
            error_log("Website settings error: " . $e->getMessage());
            // Use default values if there's an error
        }

        $tracking_link = "{$website_url}/track-visa.php?tracking_id=" . urlencode($tracking_id);

        // Build email body safely
        $previous_status_display = isset($application['status']) ? ($status_display[$application['status']] ?? 'Unknown') : 'Unknown';
        $status_description = getStatusDescription($new_status);
        $next_steps = getNextSteps($new_status);

        $body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: 'Poppins', Arial, sans-serif; line-height: 1.6; color: #333; background: #f8fafc; }
                .container { max-width: 700px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; color: white; }
                .content { padding: 30px; }
                .section { margin-bottom: 25px; padding: 20px; background: #f8fafc; border-radius: 8px; border-left: 4px solid #667eea; }
                .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
                .info-item { margin-bottom: 10px; }
                .info-label { font-weight: 600; color: #4a5568; font-size: 14px; }
                .info-value { color: #2d3748; font-size: 15px; }
                .status-badge { display: inline-block; padding: 8px 16px; border-radius: 20px; font-size: 14px; font-weight: 600; }
                .status-awaiting_documentation { background: #fed7d7; color: #c53030; }
                .status-documentation_received { background: #fefcbf; color: #d69e2e; }
                .status-processing_documentation { background: #bee3f8; color: #3182ce; }
                .status-interview_scheduled { background: #e9d8fd; color: #6b46c1; }
                .status-approved { background: #c6f6d5; color: #38a169; }
                .status-denied { background: #fed7d7; color: #e53e3e; }
                .actions { text-align: center; margin: 25px 0; }
                .btn { display: inline-block; padding: 12px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 6px; font-weight: 600; }
                .footer { background: #2d3748; color: white; padding: 25px; text-align: center; }
                .admin-notes { background: #fffaf0; border-left: 4px solid #ed8936; }
                @media (max-width: 600px) {
                    .info-grid { grid-template-columns: 1fr; }
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Application Status Updated</h1>
                    <p>Your visa application status has been updated</p>
                </div>
                
                <div class='content'>
                    <div class='section'>
                        <h3 style='color: #2d3748; margin-bottom: 15px;'>Application Summary</h3>
                        <div class='info-grid'>
                            <div class='info-item'>
                                <div class='info-label'>Application Number</div>
                                <div class='info-value'><strong>{$application_number}</strong></div>
                            </div>
                            <div class='info-item'>
                                <div class='info-label'>Tracking ID</div>
                                <div class='info-value'><strong>{$tracking_id}</strong></div>
                            </div>
                            <div class='info-item'>
                                <div class='info-label'>Destination Country</div>
                                <div class='info-value'>{$country}</div>
                            </div>
                            <div class='info-item'>
                                <div class='info-label'>Previous Status</div>
                                <div class='info-value'>{$previous_status_display}</div>
                            </div>
                        </div>
                    </div>

                    <div class='section'>
                        <h3 style='color: #2d3748; margin-bottom: 15px;'>Current Status</h3>
                        <div class='status-badge status-{$new_status}'>{$new_status_display}</div>
                        <p style='margin-top: 10px; color: #718096;'>
                            {$status_description}
                        </p>
                    </div>";

        if (!empty($admin_notes)) {
            $escaped_admin_notes = htmlspecialchars($admin_notes);
            $body .= "
                    <div class='section admin-notes'>
                        <h3 style='color: #2d3748; margin-bottom: 15px;'>Admin Notes</h3>
                        <p style='color: #718096;'>{$escaped_admin_notes}</p>
                    </div>";
        }

        $body .= "
                    <div class='actions'>
                        <a href='{$tracking_link}' class='btn'>Track Your Application</a>
                    </div>

                    <div class='section'>
                        <h3 style='color: #2d3748; margin-bottom: 15px;'>Next Steps</h3>
                        {$next_steps}
                    </div>
                </div>

                <div class='footer'>
                    <p>Thank you for choosing {$site_name}!</p>
                    <p>For assistance: {$website_email} | {$phone}</p>
                    <p><a href='{$website_url}/track-visa.php' style='color: #a0aec0;'>Track Your Application</a></p>
                </div>
            </div>
        </body>
        </html>";

        return sendHTMLEmail($email, $subject, $body);
    }
}

// Check and create required tables
try {
    // Check if visa_applications table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'visa_applications'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("
            CREATE TABLE visa_applications (
                id INT PRIMARY KEY AUTO_INCREMENT,
                application_number VARCHAR(100) UNIQUE NOT NULL,
                tracking_id VARCHAR(100) UNIQUE NOT NULL,
                country VARCHAR(255) NOT NULL,
                contact_info TEXT NOT NULL,
                personal_info TEXT NOT NULL,
                status VARCHAR(50) DEFAULT 'awaiting_documentation',
                admin_notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
    }
    
    // Check if visa_payments table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'visa_payments'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("
            CREATE TABLE visa_payments (
                id INT PRIMARY KEY AUTO_INCREMENT,
                application_id INT NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                payment_method VARCHAR(50),
                payment_reference VARCHAR(100),
                status VARCHAR(50) DEFAULT 'pending',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (application_id) REFERENCES visa_applications(id) ON DELETE CASCADE
            )
        ");
    }
    
    // Check if visa_status_history table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'visa_status_history'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("
            CREATE TABLE visa_status_history (
                id INT PRIMARY KEY AUTO_INCREMENT,
                application_id INT NOT NULL,
                status VARCHAR(50) NOT NULL,
                admin_notes TEXT,
                admin_id INT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (application_id) REFERENCES visa_applications(id) ON DELETE CASCADE
            )
        ");
    }
} catch (Exception $e) {
    $error = "Database setup error: " . $e->getMessage();
    error_log("Visa Application DB Error: " . $e->getMessage());
}

// Get all applications
try {
    $stmt = $pdo->query("
        SELECT va.*, 
               vp.status as payment_status,
               vp.amount as payment_amount,
               vp.payment_method,
               vp.payment_reference,
               vp.created_at as payment_date
        FROM visa_applications va 
        LEFT JOIN visa_payments vp ON va.id = vp.application_id 
        ORDER BY va.created_at DESC
    ");
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Error fetching applications: " . $e->getMessage();
    error_log("Visa Application Fetch Error: " . $e->getMessage());
}

// Get country fees from the database
try {
    $stmt = $pdo->query("SELECT * FROM country_fees ORDER BY country_name");
    $countries_fees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // If table doesn't exist, create it with default values
    $default_countries = [
        'United Kingdom' => 150000,
        'United States' => 200000,
        'Canada' => 180000,
        'United Arab Emirates' => 100000,
        'Saudi Arabia' => 100000,
        'Ghana' => 100000,
        'South Africa' => 100000,
        'Egypt' => 100000,
        'Germany' => 170000,
        'France' => 170000,
        'Netherlands' => 160000,
        'Italy' => 160000,
        'Spain' => 150000,
        'Turkey' => 100000,
        'Qatar' => 100000,
        'Malaysia' => 100000,
        'Singapore' => 100000,
        'Kenya' => 100000,
        'Australia' => 190000,
        'Ireland' => 150000
    ];
    
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS country_fees (
                id INT AUTO_INCREMENT PRIMARY KEY,
                country_name VARCHAR(255) NOT NULL UNIQUE,
                service_fee DECIMAL(10,2) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        
        $stmt = $pdo->prepare("INSERT IGNORE INTO country_fees (country_name, service_fee) VALUES (?, ?)");
        foreach ($default_countries as $country => $fee) {
            $stmt->execute([$country, $fee]);
        }
        
        // Reload countries
        $stmt = $pdo->query("SELECT * FROM country_fees ORDER BY country_name");
        $countries_fees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e2) {
        error_log("Error creating country_fees table: " . $e2->getMessage());
    }
}

// Handle application status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    try {
        $application_id = intval($_POST['application_id'] ?? 0);
        $new_status = sanitize($_POST['status'] ?? '');
        $admin_notes = sanitize($_POST['admin_notes'] ?? '');
        
        // Validate status
        if (!array_key_exists($new_status, $statuses)) {
            throw new Exception("Invalid status selected");
        }
        
        if ($application_id <= 0) {
            throw new Exception("Invalid application ID");
        }
        
        // Get current application data
        $stmt = $pdo->prepare("SELECT * FROM visa_applications WHERE id = ?");
        $stmt->execute([$application_id]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$application) {
            throw new Exception("Application not found");
        }
        
        // Get the old status for comparison
        $old_status = $application['status'];
        
        // Update application status
        $stmt = $pdo->prepare("
            UPDATE visa_applications 
            SET status = ?, admin_notes = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$new_status, $admin_notes, $application_id]);
        
        // Log status change
        $stmt = $pdo->prepare("
            INSERT INTO visa_status_history 
            (application_id, status, admin_notes, admin_id, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$application_id, $new_status, $admin_notes, $_SESSION['user_id'] ?? 0]);
        
        // Send notification email to user
        $email_sent = false;
        try {
            $email_sent = sendStatusUpdateEmail($application, $new_status, $admin_notes);
        } catch (Exception $email_error) {
            error_log("Email sending error: " . $email_error->getMessage());
            $email_sent = false;
        }
        
        if ($email_sent) {
            $success = "Application status updated successfully from '{$statuses[$old_status]}' to '{$statuses[$new_status]}'! Notification sent to applicant.";
        } else {
            $success = "Application status updated successfully from '{$statuses[$old_status]}' to '{$statuses[$new_status]}'! Could not send email notification.";
        }
        
        // Refresh applications list
        $stmt = $pdo->query("
            SELECT va.*, 
                   vp.status as payment_status,
                   vp.amount as payment_amount,
                   vp.payment_method,
                   vp.payment_reference,
                   vp.created_at as payment_date
            FROM visa_applications va 
            LEFT JOIN visa_payments vp ON va.id = vp.application_id 
            ORDER BY va.created_at DESC
        ");
        $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $error = "Error updating application: " . $e->getMessage();
        error_log("Visa Application Update Error: " . $e->getMessage());
    }
}

// Handle country fee update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_fee'])) {
    try {
        $country_id = intval($_POST['country_id'] ?? 0);
        $service_fee = floatval($_POST['service_fee'] ?? 0);
        
        // Validate fee
        if ($service_fee < 0) {
            throw new Exception("Invalid service fee amount");
        }
        
        if ($country_id <= 0) {
            throw new Exception("Invalid country ID");
        }
        
        // Update country fee
        $stmt = $pdo->prepare("
            UPDATE country_fees 
            SET service_fee = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$service_fee, $country_id]);
        
        $success = "Service fee updated successfully!";
        
        // Refresh countries list
        $stmt = $pdo->query("SELECT * FROM country_fees ORDER BY country_name");
        $countries_fees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $error = "Error updating service fee: " . $e->getMessage();
        error_log("Country Fee Update Error: " . $e->getMessage());
    }
}

// Handle application details view
if (isset($_GET['view_id'])) {
    try {
        $application_id = intval($_GET['view_id'] ?? 0);
        
        if ($application_id <= 0) {
            throw new Exception("Invalid application ID");
        }
        
        $stmt = $pdo->prepare("
            SELECT va.*, 
                   vp.status as payment_status,
                   vp.amount as payment_amount,
                   vp.payment_method,
                   vp.payment_reference,
                   vp.created_at as payment_date
            FROM visa_applications va 
            LEFT JOIN visa_payments vp ON va.id = vp.application_id 
            WHERE va.id = ?
        ");
        $stmt->execute([$application_id]);
        $application_details = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($application_details)) {
            $application_details = $application_details[0];
        }
        
        // Get status history separately
        try {
            $stmt = $pdo->prepare("
                SELECT vsh.*, u.username as admin_name 
                FROM visa_status_history vsh 
                LEFT JOIN users u ON vsh.admin_id = u.id 
                WHERE vsh.application_id = ? 
                ORDER BY vsh.created_at DESC
            ");
            $stmt->execute([$application_id]);
            $status_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // Fallback if users table doesn't exist or join fails
            $stmt = $pdo->prepare("
                SELECT vsh.* 
                FROM visa_status_history vsh 
                WHERE vsh.application_id = ? 
                ORDER BY vsh.created_at DESC
            ");
            $stmt->execute([$application_id]);
            $status_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Status history fallback used: " . $e->getMessage());
        }
        
    } catch (Exception $e) {
        $error = "Error fetching application details: " . $e->getMessage();
        error_log("Application Details Error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #667eea;
            --primary-dark: #5a6fd8;
            --secondary: #764ba2;
            --success: #48bb78;
            --warning: #ed8936;
            --danger: #f56565;
            --dark: #2d3748;
            --gray: #718096;
            --gray-light: #e2e8f0;
            --light: #f7fafc;
            --white: #ffffff;
            --shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1);
            --radius: 8px;
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f8f9fa;
            display: flex;
            min-height: 100vh;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 0;
            min-height: 100vh;
        }
        
        .top-bar {
            background: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .content {
            padding: 2rem;
        }
        
        .admin-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--gray-light);
        }

        .page-title {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--dark);
            margin: 0;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            text-align: center;
            border-left: 4px solid var(--primary);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--gray);
            font-size: 0.875rem;
            font-weight: 500;
        }

        .admin-card {
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .card-header {
            background: var(--light);
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--gray-light);
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark);
            margin: 0;
        }

        .card-body {
            padding: 1.5rem;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }

        .data-table th,
        .data-table td {
            padding: 0.875rem 1rem;
            text-align: left;
            border-bottom: 1px solid var(--gray-light);
        }

        .data-table th {
            background: var(--light);
            font-weight: 600;
            color: var(--dark);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .data-table tr:hover {
            background: var(--light);
        }

        .status-badge {
            display: inline-block;
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-awaiting_documentation { background: #fed7d7; color: #c53030; }
        .status-documentation_received { background: #fefcbf; color: #d69e2e; }
        .status-processing_documentation { background: #bee3f8; color: #3182ce; }
        .status-interview_scheduled { background: #e9d8fd; color: #6b46c1; }
        .status-approved { background: #c6f6d5; color: #38a169; }
        .status-denied { background: #fed7d7; color: #e53e3e; }

        .payment-status {
            display: inline-block;
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .payment-pending { background: #fefcbf; color: #d69e2e; }
        .payment-paid { background: #c6f6d5; color: #38a169; }
        .payment-failed { background: #fed7d7; color: #e53e3e; }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: var(--radius);
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }

        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.8rem;
        }

        .btn-primary {
            background: var(--primary);
            color: var(--white);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }

        .btn-success {
            background: var(--success);
            color: var(--white);
        }

        .btn-warning {
            background: var(--warning);
            color: var(--white);
        }

        .btn-danger {
            background: var(--danger);
            color: var(--white);
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--gray-light);
            color: var(--dark);
        }

        .btn-outline:hover {
            background: var(--light);
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.375rem;
            font-weight: 500;
            color: var(--dark);
            font-size: 0.875rem;
        }

        .form-control {
            width: 100%;
            padding: 0.625rem 0.75rem;
            border: 1.5px solid var(--gray-light);
            border-radius: 6px;
            font-size: 0.875rem;
            font-family: 'Arial', sans-serif;
            transition: var(--transition);
            background: var(--white);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .select-wrapper {
            position: relative;
        }

        .select-wrapper::after {
            content: '';
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            width: 0;
            height: 0;
            border-left: 5px solid transparent;
            border-right: 5px solid transparent;
            border-top: 5px solid var(--gray);
            pointer-events: none;
        }

        .alert {
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-icon {
            flex-shrink: 0;
            margin-top: 0.125rem;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: var(--white);
            margin: 5% auto;
            padding: 0;
            border-radius: var(--radius);
            width: 90%;
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--gray-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark);
            margin: 0;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--gray);
        }

        .modal-body {
            padding: 1.5rem;
        }

        .tabs {
            display: flex;
            border-bottom: 1px solid var(--gray-light);
            margin-bottom: 1.5rem;
        }

        .tab {
            padding: 0.75rem 1.5rem;
            background: none;
            border: none;
            border-bottom: 2px solid transparent;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray);
            cursor: pointer;
            transition: var(--transition);
        }

        .tab.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .info-section {
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: var(--light);
            border-radius: var(--radius);
        }

        .info-section h4 {
            margin-bottom: 0.75rem;
            color: var(--dark);
            border-bottom: 1px solid var(--gray-light);
            padding-bottom: 0.5rem;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .info-item {
            margin-bottom: 0.5rem;
        }

        .info-label {
            font-weight: 600;
            color: var(--dark);
            font-size: 0.875rem;
        }

        .info-value {
            color: var(--gray);
            font-size: 0.875rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
            
            .admin-container {
                padding: 0 0.75rem;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
            
            .card-body {
                padding: 1rem;
            }
            
            .data-table th,
            .data-table td {
                padding: 0.5rem 0.75rem;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .modal-content {
                width: 95%;
                margin: 10% auto;
            }
        }
    </style>
</head>
<body>
    <!-- Include Sidebar -->
    <?php 
    // Check if sidebar exists, if not use a basic one
    if (file_exists('sidebar.php')) {
        include 'sidebar.php'; 
    } else {
        // Basic sidebar fallback
        echo '<div style="width: 250px; background: #2d3748; color: white; height: 100vh; position: fixed; left: 0; top: 0;">';
        echo '<div style="padding: 1rem;">';
        echo '<h3>Admin Panel</h3>';
        echo '<ul style="list-style: none; padding: 0; margin-top: 2rem;">';
        echo '<li style="margin-bottom: 1rem;"><a href="index.php" style="color: white; text-decoration: none;"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>';
        echo '<li style="margin-bottom: 1rem;"><a href="visa-application.php" style="color: white; text-decoration: none;"><i class="fas fa-passport"></i> Visa Applications</a></li>';
        echo '</ul>';
        echo '</div>';
        echo '</div>';
    }
    ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <h1>Visa Applications Management</h1>
            <div>
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></span>
            </div>
        </div>

        <!-- Content -->
        <div class="content">
            <div class="admin-container">
                <!-- Page Header -->
                <div class="page-header">
                    <h1 class="page-title">Visa Applications Management</h1>
                    <div>
                        <button class="btn btn-primary" onclick="openModal('feesModal')">
                            <i class="fas fa-cog"></i>
                            Manage Service Fees
                        </button>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <div class="alert-icon">
                            <i class="fas fa-exclamation-circle"></i>
                        </div>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <div class="alert-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div><?php echo htmlspecialchars($success); ?></div>
                    </div>
                <?php endif; ?>

                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($applications); ?></div>
                        <div class="stat-label">Total Applications</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count(array_filter($applications, function($app) { return $app['status'] === 'awaiting_documentation'; })); ?></div>
                        <div class="stat-label">Awaiting Documentation</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count(array_filter($applications, function($app) { return $app['status'] === 'processing_documentation'; })); ?></div>
                        <div class="stat-label">In Progress</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count(array_filter($applications, function($app) { return $app['status'] === 'approved'; })); ?></div>
                        <div class="stat-label">Approved</div>
                    </div>
                </div>

                <!-- Applications Table -->
                <div class="admin-card">
                    <div class="card-header">
                        <h2 class="card-title">Visa Applications</h2>
                    </div>
                    <div class="card-body">
                        <?php if (empty($applications)): ?>
                            <div style="text-align: center; padding: 2rem; color: #6c757d;">
                                <i class="fas fa-file-alt fa-3x" style="margin-bottom: 1rem;"></i>
                                <p>No visa applications found.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>App Number</th>
                                            <th>Applicant</th>
                                            <th>Country</th>
                                            <th>Status</th>
                                            <th>Payment</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($applications as $application): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($application['application_number']); ?></strong>
                                                <?php if ($application['tracking_id']): ?>
                                                <br><small style="color: var(--gray);"><?php echo htmlspecialchars($application['tracking_id']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $contact_info = json_decode($application['contact_info'] ?? '{}', true);
                                                echo htmlspecialchars($contact_info['full_name'] ?? 'Unknown');
                                                ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($application['country'] ?? 'Unknown'); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo $application['status']; ?>">
                                                    <?php echo $statuses[$application['status']] ?? $application['status']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($application['payment_status']): ?>
                                                    <span class="payment-status payment-<?php echo $application['payment_status']; ?>">
                                                        <?php echo ucfirst($application['payment_status']); ?>
                                                    </span>
                                                    <br>
                                                    <small>₦<?php echo number_format($application['payment_amount'] ?? 0, 2); ?></small>
                                                <?php else: ?>
                                                    <span class="payment-status payment-pending">Pending</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($application['created_at'])); ?></td>
                                            <td>
                                                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                                    <button class="btn btn-sm btn-outline" 
                                                            onclick="viewApplication(<?php echo $application['id']; ?>)">
                                                        <i class="fas fa-eye"></i>
                                                        View
                                                    </button>
                                                    <button class="btn btn-sm btn-primary" 
                                                            onclick="updateStatus(<?php echo $application['id']; ?>, '<?php echo $application['status']; ?>')">
                                                        <i class="fas fa-edit"></i>
                                                        Update
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Update Application Status</h3>
                <button class="modal-close" onclick="closeModal('statusModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="statusForm" method="POST">
                    <input type="hidden" name="update_status" value="1">
                    <input type="hidden" name="application_id" id="application_id">
                    
                    <div class="form-group">
                        <label for="status" class="form-label">Status</label>
                        <div class="select-wrapper">
                            <select id="status" name="status" class="form-control" required>
                                <option value="">Select Status</option>
                                <?php foreach ($statuses as $key => $label): ?>
                                    <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="admin_notes" class="form-label">Admin Notes (Optional)</label>
                        <textarea id="admin_notes" name="admin_notes" class="form-control" 
                                  placeholder="Add any notes or instructions for the applicant..."></textarea>
                        <small style="color: var(--gray);">This note will be included in the status update email sent to the applicant.</small>
                    </div>
                    
                    <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
                        <button type="button" class="btn btn-outline" onclick="closeModal('statusModal')">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i>
                            Update Status & Send Email
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Application Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Application Details</h3>
                <button class="modal-close" onclick="closeModal('viewModal')">&times;</button>
            </div>
            <div class="modal-body">
                <?php if (!empty($application_details)): ?>
                <div class="tabs">
                    <button class="tab active" onclick="switchTab('details')">Details</button>
                    <button class="tab" onclick="switchTab('history')">Status History</button>
                </div>
                
                <div id="details-tab" class="tab-content active">
                    <?php 
                    $app = $application_details;
                    $contact_info = json_decode($app['contact_info'] ?? '{}', true);
                    $personal_info = json_decode($app['personal_info'] ?? '{}', true);
                    ?>
                    
                    <div class="info-grid" style="margin-bottom: 1.5rem;">
                        <div class="info-item">
                            <div class="info-label">Application Number</div>
                            <div class="info-value"><?php echo htmlspecialchars($app['application_number']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Tracking ID</div>
                            <div class="info-value"><?php echo htmlspecialchars($app['tracking_id'] ?? 'N/A'); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Country</div>
                            <div class="info-value"><?php echo htmlspecialchars($app['country']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Status</div>
                            <div class="info-value">
                                <span class="status-badge status-<?php echo $app['status']; ?>">
                                    <?php echo $statuses[$app['status']] ?? $app['status']; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Contact Information -->
                    <div class="info-section">
                        <h4>Contact Information</h4>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Full Name</div>
                                <div class="info-value"><?php echo htmlspecialchars($contact_info['full_name'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Email</div>
                                <div class="info-value"><?php echo htmlspecialchars($contact_info['email'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Phone</div>
                                <div class="info-value"><?php echo htmlspecialchars($contact_info['phone'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Passport Number</div>
                                <div class="info-value"><?php echo htmlspecialchars($contact_info['passport_number'] ?? 'N/A'); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Personal Information -->
                    <?php if (isset($personal_info['personal_info'])): ?>
                    <div class="info-section">
                        <h4>Personal Information</h4>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Date of Birth</div>
                                <div class="info-value"><?php echo htmlspecialchars($personal_info['personal_info']['date_of_birth'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Home Address</div>
                                <div class="info-value"><?php echo htmlspecialchars($personal_info['personal_info']['home_address'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Visa Refused Before</div>
                                <div class="info-value"><?php echo htmlspecialchars($personal_info['personal_info']['visa_refused'] ?? 'N/A'); ?></div>
                            </div>
                            <?php if (isset($personal_info['personal_info']['visa_refused_details']) && $personal_info['personal_info']['visa_refused'] === 'yes'): ?>
                            <div class="info-item">
                                <div class="info-label">Visa Refusal Details</div>
                                <div class="info-value"><?php echo htmlspecialchars($personal_info['personal_info']['visa_refused_details'] ?? 'N/A'); ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Purpose of Travel -->
                    <?php if (isset($personal_info['purpose_of_travel'])): ?>
                    <div class="info-section">
                        <h4>Purpose of Travel</h4>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Purpose</div>
                                <div class="info-value"><?php echo htmlspecialchars($personal_info['purpose_of_travel']['purpose'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Duration</div>
                                <div class="info-value"><?php echo htmlspecialchars($personal_info['purpose_of_travel']['duration'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Travel Date Reason</div>
                                <div class="info-value"><?php echo htmlspecialchars($personal_info['purpose_of_travel']['travel_date_reason'] ?? 'N/A'); ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Trip Planning -->
                    <?php if (isset($personal_info['trip_planning'])): ?>
                    <div class="info-section">
                        <h4>Trip Planning</h4>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Accommodation</div>
                                <div class="info-value"><?php echo htmlspecialchars($personal_info['trip_planning']['accommodation'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Bookings Made</div>
                                <div class="info-value"><?php echo htmlspecialchars($personal_info['trip_planning']['bookings_made'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Cities to Visit</div>
                                <div class="info-value"><?php echo htmlspecialchars($personal_info['trip_planning']['cities_to_visit'] ?? 'N/A'); ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Employment & Income -->
                    <?php if (isset($personal_info['employment_income'])): ?>
                    <div class="info-section">
                        <h4>Employment & Income</h4>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Occupation</div>
                                <div class="info-value"><?php echo htmlspecialchars($personal_info['employment_income']['occupation'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Employment Duration</div>
                                <div class="info-value"><?php echo htmlspecialchars($personal_info['employment_income']['employment_duration'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Monthly Income</div>
                                <div class="info-value"><?php echo htmlspecialchars($personal_info['employment_income']['monthly_income'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Leave Letter</div>
                                <div class="info-value"><?php echo htmlspecialchars($personal_info['employment_income']['leave_letter'] ?? 'N/A'); ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Financial Capability -->
                    <?php if (isset($personal_info['financial_capability'])): ?>
                    <div class="info-section">
                        <h4>Financial Capability</h4>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Account Balance</div>
                                <div class="info-value"><?php echo htmlspecialchars($personal_info['financial_capability']['account_balance'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Funding Source</div>
                                <div class="info-value"><?php echo htmlspecialchars($personal_info['financial_capability']['funding_source'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Financial Stability</div>
                                <div class="info-value"><?php echo htmlspecialchars($personal_info['financial_capability']['financial_stability'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Bank Statements</div>
                                <div class="info-value"><?php echo htmlspecialchars($personal_info['financial_capability']['bank_statements'] ?? 'N/A'); ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Sponsorship -->
                    <?php if (isset($personal_info['sponsorship']) && !empty(array_filter($personal_info['sponsorship']))): ?>
                    <div class="info-section">
                        <h4>Sponsorship</h4>
                        <div class="info-grid">
                            <?php if (!empty($personal_info['sponsorship']['sponsor_name'])): ?>
                            <div class="info-item">
                                <div class="info-label">Sponsor Name</div>
                                <div class="info-value"><?php echo htmlspecialchars($personal_info['sponsorship']['sponsor_name']); ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($personal_info['sponsorship']['sponsor_relationship'])): ?>
                            <div class="info-item">
                                <div class="info-label">Relationship</div>
                                <div class="info-value"><?php echo htmlspecialchars($personal_info['sponsorship']['sponsor_relationship']); ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($personal_info['sponsorship']['sponsor_occupation'])): ?>
                            <div class="info-item">
                                <div class="info-label">Sponsor Occupation</div>
                                <div class="info-value"><?php echo htmlspecialchars($personal_info['sponsorship']['sponsor_occupation']); ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($personal_info['sponsorship']['sponsor_reason'])): ?>
                            <div class="info-item">
                                <div class="info-label">Sponsor Reason</div>
                                <div class="info-value"><?php echo htmlspecialchars($personal_info['sponsorship']['sponsor_reason']); ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Family & Social Ties -->
                    <?php if (isset($personal_info['family_social_ties'])): ?>
                    <div class="info-section">
                        <h4>Family & Social Ties</h4>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Marital Status</div>
                                <div class="info-value"><?php echo htmlspecialchars($personal_info['family_social_ties']['marital_status'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Children</div>
                                <div class="info-value"><?php echo htmlspecialchars($personal_info['family_social_ties']['children'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Family in Country</div>
                                <div class="info-value"><?php echo htmlspecialchars($personal_info['family_social_ties']['family_in_country'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Assets & Commitments</div>
                                <div class="info-value"><?php echo htmlspecialchars($personal_info['family_social_ties']['assets_commitments'] ?? 'N/A'); ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Travel History -->
                    <?php if (isset($personal_info['travel_history'])): ?>
                    <div class="info-section">
                        <h4>Travel History</h4>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Traveled Abroad</div>
                                <div class="info-value"><?php echo htmlspecialchars($personal_info['travel_history']['traveled_abroad'] ?? 'N/A'); ?></div>
                            </div>
                            <?php if (isset($personal_info['travel_history']['countries_visited'])): ?>
                            <div class="info-item">
                                <div class="info-label">Countries Visited</div>
                                <div class="info-value"><?php echo htmlspecialchars($personal_info['travel_history']['countries_visited']); ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if (isset($personal_info['travel_history']['returned_on_time'])): ?>
                            <div class="info-item">
                                <div class="info-label">Returned on Time</div>
                                <div class="info-value"><?php echo htmlspecialchars($personal_info['travel_history']['returned_on_time']); ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Payment Information -->
                    <div class="info-section">
                        <h4>Payment Information</h4>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Payment Status</div>
                                <div class="info-value"><?php echo ucfirst($app['payment_status'] ?? 'Pending'); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Amount</div>
                                <div class="info-value">₦<?php echo number_format($app['payment_amount'] ?? 0, 2); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Payment Method</div>
                                <div class="info-value"><?php echo ucfirst($app['payment_method'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Reference</div>
                                <div class="info-value"><?php echo htmlspecialchars($app['payment_reference'] ?? 'N/A'); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Admin Notes -->
                    <?php if (!empty($app['admin_notes'])): ?>
                    <div class="info-section">
                        <h4>Admin Notes</h4>
                        <p><?php echo htmlspecialchars($app['admin_notes']); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div id="history-tab" class="tab-content">
                    <h4 style="margin-bottom: 1rem;">Status History</h4>
                    <div style="max-height: 300px; overflow-y: auto;">
                        <?php if (!empty($status_history)): ?>
                            <?php foreach ($status_history as $history): ?>
                                <div style="padding: 1rem; border-bottom: 1px solid var(--gray-light);">
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                        <strong><?php echo $statuses[$history['status']] ?? $history['status']; ?></strong>
                                        <small style="color: var(--gray);"><?php echo date('M j, Y g:i A', strtotime($history['created_at'])); ?></small>
                                    </div>
                                    <?php if (!empty($history['admin_notes'])): ?>
                                        <p style="margin: 0; color: var(--gray);"><?php echo htmlspecialchars($history['admin_notes']); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($history['admin_name'])): ?>
                                        <small style="color: var(--gray);">By: <?php echo htmlspecialchars($history['admin_name']); ?></small>
                                    <?php elseif (!empty($history['admin_id'])): ?>
                                        <small style="color: var(--gray);">By: Admin #<?php echo $history['admin_id']; ?></small>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="color: var(--gray); text-align: center; padding: 2rem;">No status history available.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                    <p>No application details found.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Manage Fees Modal -->
    <div id="feesModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Manage Service Fees</h3>
                <button class="modal-close" onclick="closeModal('feesModal')">&times;</button>
            </div>
            <div class="modal-body">
                <?php if (empty($countries_fees)): ?>
                    <p>No country fees found.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Country</th>
                                    <th>Service Fee (NGN)</th>
                                    <th>Last Updated</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($countries_fees as $country): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($country['country_name']); ?></td>
                                    <td>
                                        <form id="feeForm-<?php echo $country['id']; ?>" method="POST" style="display: flex; gap: 0.5rem; align-items: center;">
                                            <input type="hidden" name="update_fee" value="1">
                                            <input type="hidden" name="country_id" value="<?php echo $country['id']; ?>">
                                            <input type="number" name="service_fee" value="<?php echo $country['service_fee']; ?>" 
                                                   class="form-control" style="width: 120px;" min="0" step="1000" required>
                                            <button type="submit" class="btn btn-sm btn-primary">Update</button>
                                        </form>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($country['updated_at'])); ?></td>
                                    <td>
                                        <button type="submit" form="feeForm-<?php echo $country['id']; ?>" class="btn btn-sm btn-success">
                                            <i class="fas fa-check"></i>
                                            Save
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function updateStatus(applicationId, currentStatus) {
            document.getElementById('application_id').value = applicationId;
            document.getElementById('status').value = currentStatus;
            document.getElementById('admin_notes').value = '';
            openModal('statusModal');
        }

        function viewApplication(applicationId) {
            window.location.href = '?view_id=' + applicationId;
            // The modal will open automatically when the page loads with view_id parameter
        }

        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            // Remove view_id from URL if closing view modal
            if (modalId === 'viewModal') {
                const url = new URL(window.location);
                url.searchParams.delete('view_id');
                window.history.replaceState({}, '', url);
            }
        }

        function switchTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content and activate tab
            document.getElementById(tabName + '-tab').classList.add('active');
            event.currentTarget.classList.add('active');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
                // Remove view_id from URL if closing view modal
                if (event.target.id === 'viewModal') {
                    const url = new URL(window.location);
                    url.searchParams.delete('view_id');
                    window.history.replaceState({}, '', url);
                }
            }
        }

        // Auto-open view modal if view_id is in URL
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const viewId = urlParams.get('view_id');
            if (viewId) {
                openModal('viewModal');
            }
        });

        // Handle form submissions to prevent double submission
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function() {
                    const submitBtn = this.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                    }
                });
            });
        });
    </script>
</body>
</html>