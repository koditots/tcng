<?php
// visa-application.php
require_once 'config.php';

$page_title = "Visa Application";

// Utility functions if they don't exist
if (!function_exists('generateRandomString')) {
    function generateRandomString($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}

if (!function_exists('sanitize')) {
    function sanitize($data) {
        if (is_array($data)) {
            return array_map('sanitize', $data);
        }
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('sendHTMLEmail')) {
    function sendHTMLEmail($to, $subject, $body, $from = null) {
        if ($from === null) {
            $from = 'noreply@travelcentre.ng';
        }
        
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: Travel Centre <$from>" . "\r\n";
        $headers .= "Reply-To: support@travelcentre.ng" . "\r\n";
        
        // In a real application, you would use a proper email sending method
        // For now, we'll log the email and return true for development
        error_log("Would send email to: $to, Subject: $subject");
        return true;
        
        // Uncomment the line below to actually send emails in production
        // return mail($to, $subject, $body, $headers);
    }
}

// Get countries and service fees from database
$countries = [];
try {
    $stmt = $pdo->query("SELECT country_name, service_fee FROM country_fees ORDER BY country_name");
    $countries_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($countries_data as $country) {
        $countries[$country['country_name']] = $country['service_fee'];
    }
    // Remove US from dropdown options
    unset($countries['United States']);
} catch (Exception $e) {
    error_log("Error fetching countries from database: " . $e->getMessage());
    
    // Fallback to default countries if database query fails
    $countries = [
        'United Kingdom' => 150000,
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
}

// Handle form submission
$error = '';
$success = '';
$application_data = [];
$redirect_to_payment = false;

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Basic validation
        $required_fields = ['full_name', 'date_of_birth', 'home_address', 'visa_refused', 'purpose', 'duration', 
                           'travel_date_reason', 'accommodation', 'bookings_made', 'cities_to_visit', 'occupation',
                           'employment_duration', 'monthly_income', 'leave_letter', 'account_balance', 'funding_source',
                           'financial_stability', 'bank_statements', 'marital_status', 'children', 'family_in_country',
                           'assets_commitments', 'traveled_abroad', 'email', 'phone', 'passport_number', 'country'];
        
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
                throw new Exception("Please fill in all required fields: " . $field);
            }
        }

        // Validate terms agreement
        if (!isset($_POST['agree_terms']) || $_POST['agree_terms'] !== 'yes') {
            throw new Exception("You must agree to the Visa Application Disclaimer before submitting your application");
        }

        // Validate email
        if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Please enter a valid email address");
        }

        // Generate application number and tracking ID
        $application_number = 'VISA' . date('YmdHis') . strtoupper(generateRandomString(6));
        $tracking_id = 'VTRK' . strtoupper(generateRandomString(10));
        
        // Get selected country and service fee
        $selected_country = sanitize($_POST['country']);
        if (!isset($countries[$selected_country])) {
            throw new Exception("Please select a valid country");
        }
        $service_fee = $countries[$selected_country];
        
        // Collect all form data
        $application_data = [
            'application_number' => $application_number,
            'tracking_id' => $tracking_id,
            'country' => $selected_country,
            'service_fee' => $service_fee,
            'personal_info' => [
                'full_name' => sanitize($_POST['full_name']),
                'date_of_birth' => sanitize($_POST['date_of_birth']),
                'home_address' => sanitize($_POST['home_address']),
                'visa_refused' => sanitize($_POST['visa_refused']),
                'visa_refused_details' => sanitize($_POST['visa_refused_details'] ?? '')
            ],
            'purpose_of_travel' => [
                'purpose' => sanitize($_POST['purpose']),
                'duration' => sanitize($_POST['duration']),
                'travel_date_reason' => sanitize($_POST['travel_date_reason'])
            ],
            'trip_planning' => [
                'accommodation' => sanitize($_POST['accommodation']),
                'bookings_made' => sanitize($_POST['bookings_made']),
                'cities_to_visit' => sanitize($_POST['cities_to_visit'])
            ],
            'employment_income' => [
                'occupation' => sanitize($_POST['occupation']),
                'employment_duration' => sanitize($_POST['employment_duration']),
                'monthly_income' => sanitize($_POST['monthly_income']),
                'leave_letter' => sanitize($_POST['leave_letter'])
            ],
            'financial_capability' => [
                'account_balance' => sanitize($_POST['account_balance']),
                'funding_source' => sanitize($_POST['funding_source']),
                'financial_stability' => sanitize($_POST['financial_stability']),
                'bank_statements' => sanitize($_POST['bank_statements'])
            ],
            'sponsorship' => [
                'sponsor_name' => sanitize($_POST['sponsor_name'] ?? ''),
                'sponsor_relationship' => sanitize($_POST['sponsor_relationship'] ?? ''),
                'sponsor_occupation' => sanitize($_POST['sponsor_occupation'] ?? ''),
                'sponsor_reason' => sanitize($_POST['sponsor_reason'] ?? '')
            ],
            'family_social_ties' => [
                'marital_status' => sanitize($_POST['marital_status']),
                'children' => sanitize($_POST['children']),
                'family_in_country' => sanitize($_POST['family_in_country']),
                'assets_commitments' => sanitize($_POST['assets_commitments'])
            ],
            'travel_history' => [
                'traveled_abroad' => sanitize($_POST['traveled_abroad']),
                'countries_visited' => sanitize($_POST['countries_visited'] ?? ''),
                'returned_on_time' => sanitize($_POST['returned_on_time'] ?? '')
            ],
            'contact_info' => [
                'full_name' => sanitize($_POST['full_name']),
                'email' => sanitize($_POST['email']),
                'phone' => sanitize($_POST['phone']),
                'passport_number' => sanitize($_POST['passport_number'])
            ]
        ];

        // Save to database
        try {
            // Prepare contact_info and personal_info JSON
            $contact_info_json = json_encode($application_data['contact_info']);
            
            // Create personal_info without contact_info and system fields
            $personal_info_data = $application_data;
            unset($personal_info_data['contact_info']);
            unset($personal_info_data['application_number']);
            unset($personal_info_data['tracking_id']);
            unset($personal_info_data['country']);
            unset($personal_info_data['service_fee']);
            
            $personal_info_json = json_encode($personal_info_data);

            // Insert into database with all fields
            $stmt = $pdo->prepare("
                INSERT INTO visa_applications (
                    application_number, tracking_id, country, contact_info, personal_info, status, created_at
                ) VALUES (?, ?, ?, ?, ?, 'awaiting_documentation', NOW())
            ");
            $stmt->execute([
                $application_number,
                $tracking_id,
                $selected_country,
                $contact_info_json,
                $personal_info_json
            ]);
            $application_id = $pdo->lastInsertId();
            
            // Store payment information
            $stmt = $pdo->prepare("
                INSERT INTO visa_payments (
                    application_id, amount, status, created_at
                ) VALUES (?, ?, 'pending', NOW())
            ");
            $stmt->execute([$application_id, $service_fee]);
            
        } catch (Exception $e) {
            error_log("Database insertion failed: " . $e->getMessage());
            // Continue with session storage even if DB fails
            $application_id = 0;
        }

        // Store the complete application data in session
        $_SESSION['visa_application_data'] = $application_data;

        // Send acknowledgement email
        if (!sendVisaApplicationEmail($application_data, 'acknowledgement')) {
            error_log("Failed to send acknowledgement email to: " . $application_data['contact_info']['email']);
        }

        // Send admin notification
        if (!sendVisaAdminNotification($application_data)) {
            error_log("Failed to send admin notification");
        }

        // Store in session for payment
        $_SESSION['pending_visa_payment'] = [
            'application_id' => $application_id,
            'application_number' => $application_number,
            'tracking_id' => $tracking_id,
            'service_fee' => $service_fee,
            'email' => $application_data['contact_info']['email']
        ];

        // Set flag to redirect to payment page
        $redirect_to_payment = true;

    } catch (Exception $e) {
        $error = "Error submitting application: " . $e->getMessage();
        error_log("Visa application error: " . $e->getMessage());
    }
}

// Redirect to payment page if form was submitted successfully
if ($redirect_to_payment) {
    header('Location: visa-payment.php');
    exit();
}

// Function to send visa application emails
function sendVisaApplicationEmail($application_data, $type = 'acknowledgement') {
    $to = $application_data['contact_info']['email'];
    $application_number = $application_data['application_number'];
    $tracking_id = $application_data['tracking_id'];
    $service_fee = $application_data['service_fee'];
    $country = $application_data['country'];
    
    $subject = $type === 'acknowledgement' 
        ? "Visa Application Received - {$application_number}"
        : "Visa Payment Confirmation - {$application_number}";

    $website_email = 'support@travelcentre.ng';
    $website_url = 'https://travelcentre.ng';
    
    // Get website settings
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT admin_email, logo FROM site_settings ORDER BY id DESC LIMIT 1");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($settings) {
            if (!empty($settings['admin_email'])) $website_email = $settings['admin_email'];
        }
    } catch (Exception $e) {
        error_log("Website settings error: " . $e->getMessage());
    }

    $payment_link = "{$website_url}/visa-payment.php?tracking_id={$tracking_id}";

    if ($type === 'acknowledgement') {
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
                .status-badge { display: inline-block; padding: 6px 12px; background: #fed7d7; color: #c53030; border-radius: 20px; font-size: 12px; font-weight: 600; }
                .actions { text-align: center; margin: 25px 0; }
                .btn { display: inline-block; padding: 12px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 6px; font-weight: 600; }
                .footer { background: #2d3748; color: white; padding: 25px; text-align: center; }
                @media (max-width: 600px) {
                    .info-grid { grid-template-columns: 1fr; }
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Visa Application Received</h1>
                    <p>Your application has been submitted successfully</p>
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
                                <div class='info-label'>Service Fee</div>
                                <div class='info-value'>₦" . number_format($service_fee, 2) . "</div>
                            </div>
                        </div>
                    </div>

                    <div class='section'>
                        <h3 style='color: #2d3748; margin-bottom: 15px;'>Current Status</h3>
                        <div class='status-badge'>Awaiting Documentation</div>
                        <p style='margin-top: 10px; color: #718096;'>
                            Please complete your payment to proceed with document submission.
                        </p>
                    </div>

                    <div class='actions'>
                        <a href='{$payment_link}' class='btn'>Complete Payment</a>
                    </div>

                    <div class='section'>
                        <h3 style='color: #2d3748; margin-bottom: 15px;'>Next Steps</h3>
                        <ul style='color: #718096; padding-left: 20px;'>
                            <li>Complete payment within 24 hours</li>
                            <li>Check your email for document requirements</li>
                            <li>Upload required documents through our portal</li>
                            <li>Track your application status online</li>
                        </ul>
                    </div>
                </div>

                <div class='footer'>
                    <p>Thank you for choosing Travel Centre!</p>
                    <p>For assistance: {$website_email} | +234 903 407 2383</p>
                    <p><a href='{$website_url}/track-visa.php' style='color: #a0aec0;'>Track Your Application</a></p>
                </div>
            </div>
        </body>
        </html>";
    } else {
        // Payment confirmation email
        $body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: 'Poppins', Arial, sans-serif; line-height: 1.6; color: #333; background: #f8fafc; }
                .container { max-width: 700px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #48bb78 0%, #38a169 100%); padding: 30px; text-align: center; color: white; }
                .content { padding: 30px; }
                .section { margin-bottom: 25px; padding: 20px; background: #f8fafc; border-radius: 8px; border-left: 4px solid #48bb78; }
                .footer { background: #2d3748; color: white; padding: 25px; text-align: center; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Payment Confirmed</h1>
                    <p>Your visa application payment has been received</p>
                </div>
                
                <div class='content'>
                    <div class='section'>
                        <h3 style='color: #2d3748; margin-bottom: 15px;'>Payment Details</h3>
                        <p><strong>Application Number:</strong> {$application_number}</p>
                        <p><strong>Tracking ID:</strong> {$tracking_id}</p>
                        <p><strong>Amount Paid:</strong> ₦" . number_format($service_fee, 2) . "</p>
                        <p><strong>Payment Status:</strong> <span style='color: #48bb78; font-weight: 600;'>Confirmed</span></p>
                    </div>

                    <div class='section'>
                        <h3 style='color: #2d3748; margin-bottom: 15px;'>Next Steps</h3>
                        <ul style='color: #718096; padding-left: 20px;'>
                            <li>Check your email for document requirements</li>
                            <li>Upload required documents within 48 hours</li>
                            <li>Monitor your application status online</li>
                            <li>We will contact you for any additional requirements</li>
                        </ul>
                    </div>
                </div>

                <div class='footer'>
                    <p>Thank you for choosing Travel Centre!</p>
                    <p>For assistance: {$website_email} | +234 903 407 2383</p>
                </div>
            </div>
        </body>
        </html>";
    }

    // Send email
    return sendHTMLEmail($to, $subject, $body);
}

// Function to send admin notification
function sendVisaAdminNotification($application_data) {
    $admin_email = 'admin@travelcentre.ng';
    $application_number = $application_data['application_number'];
    $country = $application_data['country'];
    $service_fee = $application_data['service_fee'];
    
    $subject = "New Visa Application - {$application_number}";
    
    $body = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: 'Poppins', Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 700px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; text-align: center; color: white; }
            .content { padding: 25px; }
            .section { margin-bottom: 20px; padding: 15px; background: #f8fafc; border-radius: 6px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>New Visa Application</h2>
            </div>
            
            <div class='content'>
                <div class='section'>
                    <h3>Application Details</h3>
                    <p><strong>Application Number:</strong> {$application_data['application_number']}</p>
                    <p><strong>Tracking ID:</strong> {$application_data['tracking_id']}</p>
                    <p><strong>Country:</strong> {$country}</p>
                    <p><strong>Applicant:</strong> {$application_data['personal_info']['full_name']}</p>
                    <p><strong>Email:</strong> {$application_data['contact_info']['email']}</p>
                    <p><strong>Phone:</strong> {$application_data['contact_info']['phone']}</p>
                    <p><strong>Service Fee:</strong> ₦" . number_format($service_fee, 2) . "</p>
                </div>
                
                <p>Please review this application in the admin panel.</p>
            </div>
        </div>
    </body>
    </html>";

    return sendHTMLEmail($admin_email, $subject, $body);
}

require_once 'includes/header.php';
?>

<!-- The rest of your HTML and CSS remains exactly the same -->
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
    font-family: 'Poppins', Arial, sans-serif;
    line-height: 1.6;
    color: var(--dark);
    background: var(--light);
    font-size: 14px;
}

.visa-hero {
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    color: var(--white);
    padding: 2.5rem 0;
    text-align: center;
}

.visa-container {
    max-width: 1000px;
    margin: 0 auto;
    padding: 0 1rem;
}

.hero-content h1 {
    font-size: 2rem;
    margin-bottom: 0.75rem;
    font-weight: 600;
}

.hero-content p {
    font-size: 1rem;
    opacity: 0.9;
}

.visa-form-container {
    background: var(--white);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    margin: 2rem 0;
    overflow: hidden;
}

.form-section {
    border-bottom: 1px solid var(--gray-light);
    padding: 1.5rem;
}

.form-section:last-child {
    border-bottom: none;
}

.section-header {
    display: flex;
    align-items: center;
    margin-bottom: 1.25rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid var(--gray-light);
}

.section-icon {
    background: var(--primary);
    color: var(--white);
    padding: 0.5rem;
    border-radius: 6px;
    margin-right: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

.section-header h3 {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--dark);
    margin: 0;
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1rem;
}

@media (min-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr 1fr;
    }
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
    font-family: 'Poppins', Arial, sans-serif;
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
    min-height: 80px;
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

.btn-primary {
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    color: var(--white);
    border: none;
    padding: 0.875rem 1.5rem;
    border-radius: 6px;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-family: 'Poppins', Arial, sans-serif;
    width: 100%;
    justify-content: center;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
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

.fee-display {
    background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
    color: var(--white);
    padding: 1rem;
    border-radius: var(--radius);
    text-align: center;
    margin: 1rem 0;
}

.fee-amount {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
}

.fee-label {
    opacity: 0.9;
    font-size: 0.875rem;
}

.conditional-field {
    display: none;
}

.conditional-field.show {
    display: block;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.required::after {
    content: '*';
    color: var(--danger);
    margin-left: 0.25rem;
}

/* Terms Agreement Styles */
.terms-agreement {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: var(--radius);
    padding: 1.5rem;
    margin: 1.5rem 0;
}

.terms-checkbox {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.terms-checkbox input[type="checkbox"] {
    margin-top: 0.25rem;
    transform: scale(1.2);
}

.terms-checkbox label {
    font-weight: 500;
    color: var(--dark);
    line-height: 1.5;
}

.terms-link {
    color: var(--primary);
    text-decoration: none;
    font-weight: 600;
}

.terms-link:hover {
    text-decoration: underline;
}

.terms-note {
    font-size: 0.8rem;
    color: var(--gray);
    text-align: center;
    margin-top: 0.5rem;
}

/* Responsive Design */
@media (max-width: 768px) {
    .visa-hero {
        padding: 2rem 0;
    }
    
    .hero-content h1 {
        font-size: 1.75rem;
    }
    
    .form-section {
        padding: 1.25rem;
    }
    
    .section-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .section-icon {
        margin-right: 0;
    }
    
    .terms-agreement {
        padding: 1rem;
    }
    
    .terms-checkbox {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
}

@media (max-width: 480px) {
    .visa-container {
        padding: 0 0.75rem;
    }
    
    .form-section {
        padding: 1rem;
    }
    
    .hero-content h1 {
        font-size: 1.5rem;
    }
    
    .form-control {
        padding: 0.5rem 0.625rem;
        font-size: 0.8rem;
    }
    
    .btn-primary {
        padding: 0.75rem 1rem;
        font-size: 0.85rem;
    }
    
    .terms-agreement {
        padding: 0.75rem;
    }
}

/* Print Styles */
@media print {
    .btn-primary,
    .visa-hero {
        display: none;
    }
    
    .visa-form-container {
        box-shadow: none;
        border: 1px solid var(--gray-light);
    }
    
    .terms-agreement {
        background: transparent;
        border: 1px solid #ccc;
    }
}
</style>

<!-- Hero Section -->
<section class="visa-hero">
    <div class="visa-container">
        <div class="hero-content">
            <h1>Visa Application</h1>
            <p>Complete your visa application in a few simple steps</p>
        </div>
    </div>
</section>

<!-- Main Form Container -->
<div class="visa-container">
    <?php if ($error): ?>
        <div class="alert alert-error">
            <div class="alert-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="15" y1="9" x2="9" y2="15"/>
                    <line x1="9" y1="9" x2="15" y2="15"/>
                </svg>
            </div>
            <div><?php echo $error; ?></div>
        </div>
    <?php endif; ?>

    <form method="POST" action="" id="visaApplicationForm" class="visa-form-container">
        <!-- Personal Information -->
        <div class="form-section">
            <div class="section-header">
                <div class="section-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                </div>
                <h3>Personal Information</h3>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="full_name" class="form-label required">Full name as it appears on your passport</label>
                    <input type="text" id="full_name" name="full_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="date_of_birth" class="form-label required">Date of birth</label>
                    <input type="date" id="date_of_birth" name="date_of_birth" class="form-control" required max="<?php echo date('Y-m-d'); ?>">
                </div>
            </div>
            <div class="form-group">
                <label for="home_address" class="form-label required">Current home address</label>
                <textarea id="home_address" name="home_address" class="form-control" required></textarea>
            </div>
            <div class="form-group">
                <label for="visa_refused" class="form-label required">Have you ever been refused a visa before?</label>
                <div class="select-wrapper">
                    <select id="visa_refused" name="visa_refused" class="form-control" required onchange="toggleField('visa_refused_details', this.value)">
                        <option value="">Select an option</option>
                        <option value="no">No</option>
                        <option value="yes">Yes</option>
                    </select>
                </div>
            </div>
            <div id="visa_refused_details" class="form-group conditional-field">
                <label for="visa_refused_details" class="form-label">If yes, what happened?</label>
                <textarea id="visa_refused_details" name="visa_refused_details" class="form-control"></textarea>
            </div>
        </div>

        <!-- Purpose of Travel -->
        <div class="form-section">
            <div class="section-header">
                <div class="section-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 16v-2l-8-5V3.5c0-.83-.67-1.5-1.5-1.5S10 2.67 10 3.5V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5l8 2.5z"/>
                    </svg>
                </div>
                <h3>Purpose of Travel</h3>
            </div>
            <div class="form-group">
                <label for="purpose" class="form-label required">Purpose of your trip</label>
                <div class="select-wrapper">
                    <select id="purpose" name="purpose" class="form-control" required>
                        <option value="">Select purpose</option>
                        <option value="tourism">Tourism</option>
                        <option value="business">Business</option>
                        <option value="study">Study</option>
                        <option value="family_visit">Family Visit</option>
                        <option value="medical">Medical</option>
                        <option value="transit">Transit</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label for="duration" class="form-label required">How long do you intend to stay?</label>
                <input type="text" id="duration" name="duration" class="form-control" required placeholder="e.g., 2 weeks, 1 month">
            </div>
            <div class="form-group">
                <label for="travel_date_reason" class="form-label required">Why did you choose this particular travel date?</label>
                <textarea id="travel_date_reason" name="travel_date_reason" class="form-control" required></textarea>
            </div>
        </div>

        <!-- Trip Planning -->
        <div class="form-section">
            <div class="section-header">
                <div class="section-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                        <polyline points="9 22 9 12 15 12 15 22"/>
                    </svg>
                </div>
                <h3>Trip Planning</h3>
            </div>
            <div class="form-group">
                <label for="accommodation" class="form-label required">Where will you stay during your trip?</label>
                <textarea id="accommodation" name="accommodation" class="form-control" required placeholder="Hotel, Airbnb, friend/family address"></textarea>
            </div>
            <div class="form-group">
                <label for="bookings_made" class="form-label required">Have you booked your accommodation and flight?</label>
                <div class="select-wrapper">
                    <select id="bookings_made" name="bookings_made" class="form-control" required>
                        <option value="">Select an option</option>
                        <option value="yes">Yes</option>
                        <option value="no">No, but I plan to</option>
                        <option value="not_yet">Not yet</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label for="cities_to_visit" class="form-label required">Which cities do you plan to visit?</label>
                <textarea id="cities_to_visit" name="cities_to_visit" class="form-control" required placeholder="Please list all cities"></textarea>
            </div>
        </div>

        <!-- Employment & Income -->
        <div class="form-section">
            <div class="section-header">
                <div class="section-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 14.66V20a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h5.34"/>
                        <polygon points="18 2 22 6 12 16 8 16 8 12 18 2"/>
                    </svg>
                </div>
                <h3>Employment & Income</h3>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="occupation" class="form-label required">What do you do for a living?</label>
                    <input type="text" id="occupation" name="occupation" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="employment_duration" class="form-label required">How long have you been employed/self-employed?</label>
                    <input type="text" id="employment_duration" name="employment_duration" class="form-control" required placeholder="e.g., 3 years, 6 months">
                </div>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="monthly_income" class="form-label required">Monthly salary or business income</label>
                    <input type="text" id="monthly_income" name="monthly_income" class="form-control" required placeholder="e.g., ₦500,000">
                </div>
                <div class="form-group">
                    <label for="leave_letter" class="form-label required">Can you get an approved leave letter from your employer?</label>
                    <div class="select-wrapper">
                        <select id="leave_letter" name="leave_letter" class="form-control" required>
                            <option value="">Select an option</option>
                            <option value="yes">Yes</option>
                            <option value="no">No</option>
                            <option value="self_employed">Self-employed</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Financial Capability -->
        <div class="form-section">
            <div class="section-header">
                <div class="section-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="6" width="20" height="12" rx="2"/>
                        <circle cx="12" cy="12" r="2"/>
                        <path d="M6 12h.01M18 12h.01"/>
                    </svg>
                </div>
                <h3>Financial Capability</h3>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="account_balance" class="form-label required">How much money do you have in your account?</label>
                    <input type="text" id="account_balance" name="account_balance" class="form-control" required placeholder="e.g., ₦2,500,000">
                </div>
                <div class="form-group">
                    <label for="funding_source" class="form-label required">How do you intend to fund this trip?</label>
                    <input type="text" id="funding_source" name="funding_source" class="form-control" required>
                </div>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="financial_stability" class="form-label required">Are you financially stable to take care of all expenses?</label>
                    <div class="select-wrapper">
                        <select id="financial_stability" name="financial_stability" class="form-control" required>
                            <option value="">Select an option</option>
                            <option value="yes">Yes</option>
                            <option value="no">No</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="bank_statements" class="form-label required">Can you show your bank statements or savings?</label>
                    <div class="select-wrapper">
                        <select id="bank_statements" name="bank_statements" class="form-control" required>
                            <option value="">Select an option</option>
                            <option value="yes">Yes</option>
                            <option value="no">No</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sponsorship -->
        <div class="form-section">
            <div class="section-header">
                <div class="section-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </div>
                <h3>Sponsorship (If Applicable)</h3>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="sponsor_name" class="form-label">Who is sponsoring your trip?</label>
                    <input type="text" id="sponsor_name" name="sponsor_name" class="form-control">
                </div>
                <div class="form-group">
                    <label for="sponsor_relationship" class="form-label">Relationship with the sponsor</label>
                    <input type="text" id="sponsor_relationship" name="sponsor_relationship" class="form-control">
                </div>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="sponsor_occupation" class="form-label">What does your sponsor do for a living?</label>
                    <input type="text" id="sponsor_occupation" name="sponsor_occupation" class="form-control">
                </div>
                <div class="form-group">
                    <label for="sponsor_reason" class="form-label">Why is your sponsor willing to sponsor your trip?</label>
                    <input type="text" id="sponsor_reason" name="sponsor_reason" class="form-control">
                </div>
            </div>
        </div>

        <!-- Family & Social Ties -->
        <div class="form-section">
            <div class="section-header">
                <div class="section-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="8.5" cy="7" r="4"/>
                        <polyline points="17 11 19 13 23 9"/>
                    </svg>
                </div>
                <h3>Family & Social Ties</h3>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="marital_status" class="form-label required">Are you married or single?</label>
                    <div class="select-wrapper">
                        <select id="marital_status" name="marital_status" class="form-control" required>
                            <option value="">Select status</option>
                            <option value="single">Single</option>
                            <option value="married">Married</option>
                            <option value="divorced">Divorced</option>
                            <option value="widowed">Widowed</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="children" class="form-label required">Do you have children?</label>
                    <div class="select-wrapper">
                        <select id="children" name="children" class="form-control" required>
                            <option value="">Select an option</option>
                            <option value="yes">Yes</option>
                            <option value="no">No</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="family_in_country" class="form-label required">Do you have family members living in your home country?</label>
                    <div class="select-wrapper">
                        <select id="family_in_country" name="family_in_country" class="form-control" required>
                            <option value="">Select an option</option>
                            <option value="yes">Yes</option>
                            <option value="no">No</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="assets_commitments" class="form-label required">Do you have property, business, or commitments that ensure you will return?</label>
                    <div class="select-wrapper">
                        <select id="assets_commitments" name="assets_commitments" class="form-control" required>
                            <option value="">Select an option</option>
                            <option value="yes">Yes</option>
                            <option value="no">No</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Travel History -->
        <div class="form-section">
            <div class="section-header">
                <div class="section-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 12h14M12 5l7 7-7 7"/>
                    </svg>
                </div>
                <h3>Travel History</h3>
            </div>
            <div class="form-group">
                <label for="traveled_abroad" class="form-label required">Have you traveled outside your country before?</label>
                <div class="select-wrapper">
                    <select id="traveled_abroad" name="traveled_abroad" class="form-control" required onchange="toggleField('travel_history_fields', this.value)">
                        <option value="">Select an option</option>
                        <option value="yes">Yes</option>
                        <option value="no">No</option>
                    </select>
                </div>
            </div>
            <div id="travel_history_fields" class="conditional-field">
                <div class="form-group">
                    <label for="countries_visited" class="form-label">Which countries have you visited?</label>
                    <textarea id="countries_visited" name="countries_visited" class="form-control" placeholder="List all countries visited"></textarea>
                </div>
                <div class="form-group">
                    <label for="returned_on_time" class="form-label">Did you return within the authorized period?</label>
                    <div class="select-wrapper">
                        <select id="returned_on_time" name="returned_on_time" class="form-control">
                            <option value="">Select an option</option>
                            <option value="yes">Yes</option>
                            <option value="no">No</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contact Information -->
        <div class="form-section">
            <div class="section-header">
                <div class="section-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                        <polyline points="22,6 12,13 2,6"/>
                    </svg>
                </div>
                <h3>Contact Information</h3>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="email" class="form-label required">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="phone" class="form-label required">Phone Number</label>
                    <input type="tel" id="phone" name="phone" class="form-control" required>
                </div>
            </div>
            <div class="form-group">
                <label for="passport_number" class="form-label required">Passport Number</label>
                <input type="text" id="passport_number" name="passport_number" class="form-control" required>
            </div>
        </div>

        <!-- Destination Country Selection - MOVED TO END -->
        <div class="form-section">
            <div class="section-header">
                <div class="section-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/>
                        <circle cx="12" cy="9" r="2.5"/>
                    </svg>
                </div>
                <h3>Destination Country</h3>
            </div>
            <div class="form-group">
                <label for="country" class="form-label required">Select country you're travelling to:</label>
                <div class="select-wrapper">
                    <select id="country" name="country" class="form-control" required onchange="updateServiceFee()">
                        <option value="">Select a country</option>
                        <?php foreach ($countries as $country => $fee): ?>
                            <option value="<?php echo htmlspecialchars($country); ?>" data-fee="<?php echo $fee; ?>">
                                <?php echo htmlspecialchars($country); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div id="feeDisplay" class="fee-display" style="display: none;">
                <div class="fee-amount" id="feeAmount">₦0.00</div>
                <div class="fee-label">Visa Processing Service Fee</div>
            </div>
        </div>

        <!-- Terms Agreement Section -->
        <div class="form-section">
            <div class="terms-agreement">
                <div class="terms-checkbox">
                    <input type="checkbox" id="agree_terms" name="agree_terms" value="yes" required>
                    <label for="agree_terms" class="form-label">
                        I have read and agree to the <a href="visa-term.php" target="_blank" class="terms-link">Visa Application Disclaimer</a>
                    </label>
                </div>
                <div class="terms-note">
                    You must agree to the terms and conditions before submitting your application
                </div>
            </div>
        </div>

        <!-- Submit Button -->
        <div class="form-section" style="text-align: center;">
            <button type="submit" class="btn-primary">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                    <polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
                Submit Visa Application
            </button>
            <p style="margin-top: 1rem; color: var(--gray); font-size: 0.8rem;">
                By submitting this application, you confirm that all information provided is accurate and complete
            </p>
        </div>
    </form>
</div>

<script>
// Update service fee based on country selection
function updateServiceFee() {
    const countrySelect = document.getElementById('country');
    const feeDisplay = document.getElementById('feeDisplay');
    const feeAmount = document.getElementById('feeAmount');
    
    if (countrySelect.value) {
        const selectedOption = countrySelect.options[countrySelect.selectedIndex];
        const fee = selectedOption.getAttribute('data-fee');
        feeAmount.textContent = '₦' + parseFloat(fee).toLocaleString('en-US', {minimumFractionDigits: 2});
        feeDisplay.style.display = 'block';
    } else {
        feeDisplay.style.display = 'none';
    }
}

// Toggle conditional fields
function toggleField(fieldId, value) {
    const field = document.getElementById(fieldId);
    if (value === 'yes') {
        field.classList.add('show');
    } else {
        field.classList.remove('show');
        // Clear the field when hidden
        const inputs = field.querySelectorAll('input, textarea, select');
        inputs.forEach(input => {
            input.value = '';
        });
    }
}

// Form validation and submission
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('visaApplicationForm');
    
    form.addEventListener('submit', function(e) {
        let isValid = true;
        const requiredFields = form.querySelectorAll('[required]');
        
        // Check terms agreement
        const agreeTerms = document.getElementById('agree_terms');
        if (!agreeTerms.checked) {
            isValid = false;
            const termsSection = agreeTerms.closest('.terms-agreement');
            termsSection.style.borderColor = '#f56565';
            termsSection.style.background = '#fef5f5';
        } else {
            const termsSection = agreeTerms.closest('.terms-agreement');
            termsSection.style.borderColor = '#e2e8f0';
            termsSection.style.background = '#f8fafc';
        }
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                field.style.borderColor = '#f56565';
            } else {
                field.style.borderColor = '#e2e8f0';
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            alert('Please fill in all required fields and agree to the terms and conditions');
        }
    });

    // Set maximum date for date of birth
    const dobField = document.getElementById('date_of_birth');
    if (dobField) {
        const today = new Date().toISOString().split('T')[0];
        dobField.max = today;
    }

    // Initialize conditional fields
    toggleField('visa_refused_details', document.getElementById('visa_refused').value);
    toggleField('travel_history_fields', document.getElementById('traveled_abroad').value);
});

// Auto-format currency inputs
document.addEventListener('DOMContentLoaded', function() {
    const currencyFields = document.querySelectorAll('input[placeholder*="₦"]');
    
    currencyFields.forEach(field => {
        field.addEventListener('blur', function() {
            if (this.value) {
                // Remove any existing formatting
                let value = this.value.replace(/[^\d.]/g, '');
                if (value) {
                    value = parseFloat(value).toLocaleString('en-US', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                    this.value = '₦' + value;
                }
            }
        });
        
        field.addEventListener('focus', function() {
            // Remove formatting for editing
            if (this.value.startsWith('₦')) {
                this.value = this.value.replace(/[^\d.]/g, '');
            }
        });
    });
});
</script>

<?php
require_once 'includes/footer.php';
?>
