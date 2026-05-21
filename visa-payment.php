<?php
// visa-payment.php
require_once 'config.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$page_title = "Visa Payment";

// Initialize variables
$error = '';
$success = '';
$application_data = [];
$payment_data = [];

// Check if there's a pending visa payment in session or tracking_id in URL
if (!isset($_SESSION['pending_visa_payment']) && !isset($_GET['tracking_id'])) {
    header('Location: visa-application.php');
    exit();
}

// Get application data from session or database
if (isset($_SESSION['pending_visa_payment'])) {
    $pending_payment = $_SESSION['pending_visa_payment'];
    
    try {
        // First try to get application data from session (this is where it's actually stored)
        if (isset($_SESSION['visa_application_data'])) {
            $session_application_data = $_SESSION['visa_application_data'];
            
            $application_data = [
                'application_id' => $pending_payment['application_id'],
                'application_number' => $pending_payment['application_number'],
                'tracking_id' => $pending_payment['tracking_id'],
                'service_fee' => $pending_payment['service_fee'], // Use the fee from pending_payment
                'country' => $session_application_data['country'],
                'email' => $pending_payment['email'],
                'full_name' => $session_application_data['personal_info']['full_name'] ?? '',
                'phone' => $session_application_data['contact_info']['phone'] ?? ''
            ];
        } else {
            // Fallback: try to fetch from database using the new data structure
            $stmt = $pdo->prepare("
                SELECT va.*, vp.amount as service_fee 
                FROM visa_applications va 
                LEFT JOIN visa_payments vp ON va.id = vp.application_id 
                WHERE va.id = ? AND va.application_number = ?
                ORDER BY vp.id DESC LIMIT 1
            ");
            $stmt->execute([$pending_payment['application_id'], $pending_payment['application_number']]);
            $application = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$application) {
                $error = "Application not found. Please submit a new application.";
                unset($_SESSION['pending_visa_payment']);
            } else {
                // Decode the JSON data to get contact info
                $contact_info = json_decode($application['contact_info'] ?? '{}', true);
                $personal_info = json_decode($application['personal_info'] ?? '{}', true);
                
                $application_data = [
                    'application_id' => $application['id'],
                    'application_number' => $application['application_number'],
                    'tracking_id' => $application['tracking_id'],
                    'service_fee' => $application['service_fee'] ?? $pending_payment['service_fee'],
                    'country' => $application['country'] ?? 'Unknown',
                    'email' => $contact_info['email'] ?? $pending_payment['email'],
                    'full_name' => $contact_info['full_name'] ?? ($personal_info['personal_info']['full_name'] ?? 'Unknown'),
                    'phone' => $contact_info['phone'] ?? ''
                ];
            }
        }
    } catch (Exception $e) {
        $error = "Error retrieving application: " . $e->getMessage();
        error_log("Payment page error: " . $e->getMessage());
    }
} elseif (isset($_GET['tracking_id'])) {
    // If accessing via tracking ID link
    $tracking_id = sanitize($_GET['tracking_id']);
    
    try {
        // Try to find application by tracking ID in session first
        $found_in_session = false;
        if (isset($_SESSION['pending_visa_payment']) && 
            isset($_SESSION['pending_visa_payment']['tracking_id']) && 
            $_SESSION['pending_visa_payment']['tracking_id'] === $tracking_id) {
            
            $pending_payment = $_SESSION['pending_visa_payment'];
            if (isset($_SESSION['visa_application_data'])) {
                $session_application_data = $_SESSION['visa_application_data'];
                
                $application_data = [
                    'application_id' => $pending_payment['application_id'],
                    'application_number' => $pending_payment['application_number'],
                    'tracking_id' => $pending_payment['tracking_id'],
                    'service_fee' => $pending_payment['service_fee'], // Use the fee from pending_payment
                    'country' => $session_application_data['country'],
                    'email' => $pending_payment['email'],
                    'full_name' => $session_application_data['personal_info']['full_name'] ?? '',
                    'phone' => $session_application_data['contact_info']['phone'] ?? ''
                ];
                $found_in_session = true;
            }
        }
        
        if (!$found_in_session) {
            // Fallback: try database lookup with the new data structure
            $stmt = $pdo->prepare("
                SELECT va.*, vp.amount as service_fee 
                FROM visa_applications va 
                LEFT JOIN visa_payments vp ON va.id = vp.application_id 
                WHERE va.tracking_id = ? OR va.application_number LIKE ?
                ORDER BY vp.id DESC LIMIT 1
            ");
            $stmt->execute([$tracking_id, '%' . $tracking_id . '%']);
            $application = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$application) {
                $error = "Application not found. Please check your tracking ID or submit a new application.";
            } else {
                // Decode the JSON data to get contact info
                $contact_info = json_decode($application['contact_info'] ?? '{}', true);
                $personal_info = json_decode($application['personal_info'] ?? '{}', true);
                
                $application_data = [
                    'application_id' => $application['id'],
                    'application_number' => $application['application_number'],
                    'tracking_id' => $application['tracking_id'],
                    'service_fee' => $application['service_fee'] ?? 0,
                    'country' => $application['country'] ?? 'Unknown',
                    'email' => $contact_info['email'] ?? '',
                    'full_name' => $contact_info['full_name'] ?? ($personal_info['personal_info']['full_name'] ?? 'Unknown'),
                    'phone' => $contact_info['phone'] ?? ''
                ];
                
                // Store in session for payment processing
                $_SESSION['pending_visa_payment'] = [
                    'application_id' => $application['id'],
                    'application_number' => $application['application_number'],
                    'tracking_id' => $application['tracking_id'],
                    'service_fee' => $application['service_fee'] ?? 0,
                    'email' => $contact_info['email'] ?? ''
                ];
                
                // Also store the full application data in session if available
                if (!isset($_SESSION['visa_application_data'])) {
                    $_SESSION['visa_application_data'] = [
                        'country' => $application['country'],
                        'personal_info' => $personal_info,
                        'contact_info' => $contact_info
                    ];
                }
            }
        }
    } catch (Exception $e) {
        $error = "Error retrieving application: " . $e->getMessage();
        error_log("Payment tracking error: " . $e->getMessage());
    }
}

// Get payment gateway settings from admin
try {
    $stmt = $pdo->query("SELECT * FROM site_settings ORDER BY id DESC LIMIT 1");
    $site_settings = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching site settings: " . $e->getMessage());
    $site_settings = [];
}

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($application_data)) {
    try {
        $payment_method = sanitize($_POST['payment_method']);
        $phone = sanitize($_POST['phone']);
        
        // Validate payment method
        $valid_methods = ['paystack', 'flutterwave', 'bank_transfer'];
        if (!in_array($payment_method, $valid_methods)) {
            throw new Exception("Invalid payment method selected");
        }

        // Generate payment reference
        $payment_reference = 'VPAY' . date('YmdHis') . strtoupper(generateRandomString(8));
        
        // Prepare payment data - Use the correct service_fee from application_data
        $payment_data = [
            'application_id' => $application_data['application_id'],
            'application_number' => $application_data['application_number'],
            'tracking_id' => $application_data['tracking_id'],
            'amount' => $application_data['service_fee'], // This is the exact amount in Naira
            'payment_method' => $payment_method,
            'payment_reference' => $payment_reference,
            'phone' => $phone,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s')
        ];

        // Save payment record
        $stmt = $pdo->prepare("
            INSERT INTO visa_payments (
                application_id, amount, 
                payment_method, payment_reference, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $payment_data['application_id'],
            $payment_data['amount'],
            $payment_data['payment_method'],
            $payment_data['payment_reference'],
            $payment_data['status'],
            $payment_data['created_at']
        ]);

        $payment_id = $pdo->lastInsertId();

        // Process payment based on method
        if ($payment_method === 'paystack') {
            // Initialize Paystack payment
            $payment_result = processPaystackPayment($payment_data, $application_data, $site_settings);
            
            if ($payment_result['status'] === 'success') {
                // Redirect to Paystack payment page
                header('Location: ' . $payment_result['payment_url']);
                exit();
            } else {
                throw new Exception("Payment initialization failed: " . $payment_result['message']);
            }
        } elseif ($payment_method === 'flutterwave') {
            // For Flutterwave (coming soon), show message
            $success = "Flutterwave payment gateway is coming soon. Please choose Paystack for now.";
            // Update payment status to failed since it's not available
            updatePaymentStatus($payment_id, 'failed');
        } elseif ($payment_method === 'bank_transfer') {
            // For Bank Transfer (coming soon), show message
            $success = "Bank transfer payment gateway is coming soon. Please choose Paystack for now.";
            // Update payment status to failed since it's not available
            updatePaymentStatus($payment_id, 'failed');
        }

    } catch (Exception $e) {
        $error = "Payment processing error: " . $e->getMessage();
        error_log("Visa payment error: " . $e->getMessage());
    }
}

// Function to process Paystack payment
function processPaystackPayment($payment_data, $application_data, $site_settings) {
    $paystack_public_key = $site_settings['paystack_public_key'] ?? '';
    $paystack_secret_key = $site_settings['paystack_secret_key'] ?? '';
    
    if (empty($paystack_secret_key)) {
        return [
            'status' => 'error',
            'message' => 'Paystack is not configured. Please contact support.'
        ];
    }
    
    $payment_url = "https://api.paystack.co/transaction/initialize";
    
    $callback_url = "https://" . $_SERVER['HTTP_HOST'] . "/visa-payment-success.php?reference=" . $payment_data['payment_reference'];
    
    $post_data = [
        'email' => $application_data['email'],
        'amount' => $payment_data['amount'] * 100, // Convert Naira to kobo for Paystack
        'reference' => $payment_data['payment_reference'],
        'callback_url' => $callback_url,
        'metadata' => [
            'custom_fields' => [
                [
                    'display_name' => "Application Number",
                    'variable_name' => "application_number",
                    'value' => $application_data['application_number']
                ],
                [
                    'display_name' => "Tracking ID", 
                    'variable_name' => "tracking_id",
                    'value' => $application_data['tracking_id']
                ],
                [
                    'display_name' => "Service Type",
                    'variable_name' => "service_type", 
                    'value' => "Visa Application - " . $application_data['country']
                ]
            ]
        ]
    ];
    
    $headers = [
        'Authorization: Bearer ' . $paystack_secret_key,
        'Content-Type: application/json'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $payment_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $response_data = json_decode($response, true);
        if ($response_data['status'] === true) {
            return [
                'status' => 'success',
                'payment_url' => $response_data['data']['authorization_url']
            ];
        }
    }
    
    return [
        'status' => 'error',
        'message' => 'Unable to initialize Paystack payment'
    ];
}

// Function to get bank transfer details
function getBankTransferDetails($site_settings) {
    return [
        'bank_name' => $site_settings['bank_name'] ?? 'Guaranty Trust Bank',
        'account_name' => $site_settings['account_name'] ?? 'Travel Centre Nigeria Limited',
        'account_number' => $site_settings['account_number'] ?? '1234567890'
    ];
}

// Function to send bank transfer email
function sendBankTransferEmail($application_data, $payment_data, $bank_details, $site_settings) {
    $to = $application_data['email'];
    $subject = "Bank Transfer Instructions - Visa Application {$application_data['application_number']}";
    
    $site_name = $site_settings['site_name'] ?? 'Travel Centre';
    $support_email = $site_settings['support_email'] ?? 'support@travelcentre.ng';
    $phone = $site_settings['phone'] ?? '+234 903 407 2383';
    
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
            .bank-details { background: #e6ffed; border: 1px solid #68d391; }
            .footer { background: #2d3748; color: white; padding: 25px; text-align: center; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Bank Transfer Instructions</h1>
                <p>Complete your visa application payment via bank transfer</p>
            </div>
            
            <div class='content'>
                <div class='section'>
                    <h3 style='color: #2d3748; margin-bottom: 15px;'>Application Details</h3>
                    <div class='info-grid'>
                        <div class='info-item'>
                            <div class='info-label'>Application Number</div>
                            <div class='info-value'><strong>{$application_data['application_number']}</strong></div>
                        </div>
                        <div class='info-item'>
                            <div class='info-label'>Tracking ID</div>
                            <div class='info-value'><strong>{$application_data['tracking_id']}</strong></div>
                        </div>
                        <div class='info-item'>
                            <div class='info-label'>Destination Country</div>
                            <div class='info-value'>{$application_data['country']}</div>
                        </div>
                        <div class='info-item'>
                            <div class='info-label'>Amount Due</div>
                            <div class='info-value'>₦" . number_format($application_data['service_fee'], 2) . "</div>
                        </div>
                    </div>
                </div>

                <div class='section bank-details'>
                    <h3 style='color: #2d3748; margin-bottom: 15px;'>Bank Transfer Details</h3>
                    <div class='info-grid'>
                        <div class='info-item'>
                            <div class='info-label'>Bank Name</div>
                            <div class='info-value'><strong>{$bank_details['bank_name']}</strong></div>
                        </div>
                        <div class='info-item'>
                            <div class='info-label'>Account Name</div>
                            <div class='info-value'><strong>{$bank_details['account_name']}</strong></div>
                        </div>
                        <div class='info-item'>
                            <div class='info-label'>Account Number</div>
                            <div class='info-value'><strong>{$bank_details['account_number']}</strong></div>
                        </div>
                        <div class='info-item'>
                            <div class='info-label'>Payment Reference</div>
                            <div class='info-value'><strong>{$payment_data['payment_reference']}</strong></div>
                        </div>
                    </div>
                </div>

                <div class='section'>
                    <h3 style='color: #2d3748; margin-bottom: 15px;'>Important Instructions</h3>
                    <ul style='color: #718096; padding-left: 20px;'>
                        <li>Transfer the exact amount: <strong>₦" . number_format($application_data['service_fee'], 2) . "</strong></li>
                        <li>Use the payment reference: <strong>{$payment_data['payment_reference']}</strong></li>
                        <li>Complete transfer within 24 hours</li>
                        <li>Send proof of payment to: {$support_email}</li>
                        <li>Your application will be processed once payment is confirmed</li>
                    </ul>
                </div>
            </div>

            <div class='footer'>
                <p>Thank you for choosing {$site_name}!</p>
                <p>For assistance: {$support_email} | {$phone}</p>
            </div>
        </div>
    </body>
    </html>";

    return sendHTMLEmail($to, $subject, $body);
}

// Function to update payment status
function updatePaymentStatus($payment_id, $status) {
    global $pdo;
    
    $stmt = $pdo->prepare("UPDATE visa_payments SET status = ?, updated_at = NOW() WHERE id = ?");
    return $stmt->execute([$status, $payment_id]);
}

// Function to update application payment status
function updateApplicationPaymentStatus($application_id, $status) {
    global $pdo;
    
    // Update the visa_payments table status for this application
    $stmt = $pdo->prepare("UPDATE visa_payments SET status = ? WHERE application_id = ? ORDER BY id DESC LIMIT 1");
    return $stmt->execute([$status, $application_id]);
}

// Helper function for sanitization
if (!function_exists('sanitize')) {
    function sanitize($input) {
        if (is_array($input)) {
            return array_map('sanitize', $input);
        }
        return htmlspecialchars(trim($input ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

// Helper function for generating random string
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

// Helper function for sending HTML emails
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

require_once 'includes/header.php';
?>

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

.payment-hero {
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    color: var(--white);
    padding: 2.5rem 0;
    text-align: center;
}

.payment-container {
    max-width: 800px;
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

.payment-card {
    background: var(--white);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    margin: 2rem 0;
    overflow: hidden;
}

.payment-section {
    border-bottom: 1px solid var(--gray-light);
    padding: 1.5rem;
}

.payment-section:last-child {
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

.info-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1rem;
}

@media (min-width: 768px) {
    .info-grid {
        grid-template-columns: 1fr 1fr;
    }
}

.info-item {
    display: flex;
    justify-content: space-between;
    padding: 0.75rem 0;
    border-bottom: 1px solid var(--gray-light);
}

.info-label {
    font-weight: 500;
    color: var(--gray);
}

.info-value {
    font-weight: 600;
    color: var(--dark);
}

.amount-display {
    background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
    color: var(--white);
    padding: 1.5rem;
    text-align: center;
    border-radius: var(--radius);
    margin: 1rem 0;
}

.amount {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.amount-label {
    opacity: 0.9;
    font-size: 0.9rem;
}

.payment-methods {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1rem;
    margin: 1.5rem 0;
}

@media (min-width: 768px) {
    .payment-methods {
        grid-template-columns: 1fr 1fr 1fr;
    }
}

.payment-method {
    border: 2px solid var(--gray-light);
    border-radius: var(--radius);
    padding: 1.5rem;
    text-align: center;
    cursor: pointer;
    transition: var(--transition);
    background: var(--white);
    position: relative;
    overflow: hidden;
}

.payment-method:hover {
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: var(--shadow);
}

.payment-method.selected {
    border-color: var(--primary);
    background: rgba(102, 126, 234, 0.05);
}

.payment-method.coming-soon {
    cursor: not-allowed;
    opacity: 0.7;
}

.payment-method.coming-soon:hover {
    border-color: var(--gray-light);
    transform: none;
    box-shadow: none;
}

.coming-soon-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    background: var(--warning);
    color: white;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 600;
}

.method-icon {
    font-size: 2rem;
    margin-bottom: 1rem;
    color: var(--primary);
}

.payment-method.coming-soon .method-icon {
    color: var(--gray);
}

.method-name {
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: var(--dark);
}

.payment-method.coming-soon .method-name {
    color: var(--gray);
}

.method-description {
    font-size: 0.8rem;
    color: var(--gray);
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

.btn-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
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

.secure-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: var(--gray-light);
    color: var(--gray);
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.8rem;
    margin-top: 1rem;
}

/* Responsive Design */
@media (max-width: 768px) {
    .payment-hero {
        padding: 2rem 0;
    }
    
    .hero-content h1 {
        font-size: 1.75rem;
    }
    
    .payment-section {
        padding: 1.25rem;
    }
    
    .payment-methods {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .payment-container {
        padding: 0 0.75rem;
    }
    
    .payment-section {
        padding: 1rem;
    }
    
    .hero-content h1 {
        font-size: 1.5rem;
    }
    
    .amount {
        font-size: 1.75rem;
    }
}
</style>

<!-- Hero Section -->
<section class="payment-hero">
    <div class="payment-container">
        <div class="hero-content">
            <h1>Visa Application Payment</h1>
            <p>Complete your payment to proceed with your visa application</p>
        </div>
    </div>
</section>

<!-- Main Payment Container -->
<div class="payment-container">
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

    <?php if ($success): ?>
        <div class="alert alert-success">
            <div class="alert-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                    <polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
            </div>
            <div><?php echo $success; ?></div>
        </div>
    <?php endif; ?>

    <?php if (!empty($application_data)): ?>
    <div class="payment-card">
        <!-- Application Summary -->
        <div class="payment-section">
            <div class="section-header">
                <div class="section-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <line x1="16" y1="13" x2="8" y2="13"/>
                        <line x1="16" y1="17" x2="8" y2="17"/>
                        <polyline points="10 9 9 9 8 9"/>
                    </svg>
                </div>
                <h3>Application Summary</h3>
            </div>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Application Number:</span>
                    <span class="info-value"><?php echo $application_data['application_number']; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Tracking ID:</span>
                    <span class="info-value"><?php echo $application_data['tracking_id']; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Destination Country:</span>
                    <span class="info-value"><?php echo $application_data['country']; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Applicant Name:</span>
                    <span class="info-value"><?php echo $application_data['full_name']; ?></span>
                </div>
            </div>
        </div>

        <!-- Payment Amount -->
        <div class="payment-section">
            <div class="section-header">
                <div class="section-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="6" width="20" height="12" rx="2"/>
                        <circle cx="12" cy="12" r="2"/>
                        <path d="M6 12h.01M18 12h.01"/>
                    </svg>
                </div>
                <h3>Payment Details</h3>
            </div>
            <div class="amount-display">
                <div class="amount">₦<?php echo number_format($application_data['service_fee'], 2); ?></div>
                <div class="amount-label">Visa Application Service Fee</div>
            </div>
        </div>

        <!-- Payment Method Selection -->
        <form method="POST" action="" id="paymentForm">
            <div class="payment-section">
                <div class="section-header">
                    <div class="section-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                            <line x1="1" y1="10" x2="23" y2="10"/>
                        </svg>
                    </div>
                    <h3>Select Payment Method</h3>
                </div>

                <div class="payment-methods">
                    <?php if (!empty($site_settings['paystack_public_key'])): ?>
                    <div class="payment-method" data-method="paystack">
                        <div class="method-icon">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                                <line x1="1" y1="10" x2="23" y2="10"/>
                            </svg>
                        </div>
                        <div class="method-name">Paystack</div>
                        <div class="method-description">Pay with card, bank transfer, USSD</div>
                    </div>
                    <?php endif; ?>

                    <div class="payment-method coming-soon" data-method="flutterwave">
                        <div class="coming-soon-badge">Coming Soon</div>
                        <div class="method-icon">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <div class="method-name">Flutterwave</div>
                        <div class="method-description">Pay with card, mobile money</div>
                    </div>

                    <div class="payment-method coming-soon" data-method="bank_transfer">
                        <div class="coming-soon-badge">Coming Soon</div>
                        <div class="method-icon">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="2" y="2" width="20" height="20" rx="2.18" ry="2.18"/>
                                <line x1="7" y1="2" x2="7" y2="22"/>
                                <line x1="17" y1="2" x2="17" y2="22"/>
                                <line x1="2" y1="12" x2="22" y2="12"/>
                                <line x1="2" y1="7" x2="7" y2="7"/>
                                <line x1="2" y1="17" x2="7" y2="17"/>
                                <line x1="17" y1="17" x2="22" y2="17"/>
                                <line x1="17" y1="7" x2="22" y2="7"/>
                            </svg>
                        </div>
                        <div class="method-name">Bank Transfer</div>
                        <div class="method-description">Transfer directly to our bank</div>
                    </div>
                </div>

                <input type="hidden" name="payment_method" id="paymentMethod" required>

                <div class="form-group">
                    <label for="phone" class="form-label required">Phone Number</label>
                    <input type="tel" id="phone" name="phone" class="form-control" 
                           value="<?php echo $application_data['phone'] ?? ''; ?>" 
                           required placeholder="Enter your phone number">
                </div>
            </div>

            <!-- Submit Button -->
            <div class="payment-section" style="text-align: center;">
                <button type="submit" class="btn-primary" id="submitBtn">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 2C13.1 2 14 2.9 14 4s-.9 2-2 2-2-.9-2-2 .9-2 2-2zm9 7h-6v13h-2v-6h-2v6H9V9H3V7h18v2z"/>
                    </svg>
                    Proceed to Payment
                </button>
                <div class="secure-badge">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                    Secure Payment • SSL Encrypted
                </div>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const paymentMethods = document.querySelectorAll('.payment-method');
    const paymentMethodInput = document.getElementById('paymentMethod');
    const submitBtn = document.getElementById('submitBtn');
    const form = document.getElementById('paymentForm');
    
    // Handle payment method selection
    paymentMethods.forEach(method => {
        method.addEventListener('click', function() {
            // Skip if it's a coming soon method
            if (this.classList.contains('coming-soon')) {
                showComingSoonMessage(this.getAttribute('data-method'));
                return;
            }
            
            // Remove selected class from all methods
            paymentMethods.forEach(m => m.classList.remove('selected'));
            
            // Add selected class to clicked method
            this.classList.add('selected');
            
            // Update hidden input
            paymentMethodInput.value = this.getAttribute('data-method');
            
            // Update button text based on selected method
            const methodName = this.querySelector('.method-name').textContent;
            updateSubmitButton(methodName);
            
            // Auto-submit form for Paystack payment
            if (paymentMethodInput.value === 'paystack') {
                setTimeout(() => {
                    if (validateForm()) {
                        submitForm();
                    }
                }, 500);
            }
        });
    });
    
    // Function to show coming soon message
    function showComingSoonMessage(method) {
        const methodNames = {
            'flutterwave': 'Flutterwave',
            'bank_transfer': 'Bank Transfer'
        };
        
        const methodName = methodNames[method] || method;
        alert(`${methodName} payment gateway is coming soon. Please choose Paystack for now.`);
    }
    
    // Function to update submit button text
    function updateSubmitButton(methodName) {
        submitBtn.innerHTML = `
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 2C13.1 2 14 2.9 14 4s-.9 2-2 2-2-.9-2-2 .9-2 2-2zm9 7h-6v13h-2v-6h-2v6H9V9H3V7h18v2z"/>
            </svg>
            Pay with ${methodName}
        `;
    }
    
    // Function to validate form
    function validateForm() {
        if (!paymentMethodInput.value) {
            alert('Please select a payment method');
            return false;
        }
        
        const phone = document.getElementById('phone').value;
        if (!phone) {
            alert('Please enter your phone number');
            return false;
        }
        
        // Validate phone number format
        const phoneRegex = /^[0-9+]{10,15}$/;
        if (!phoneRegex.test(phone.replace(/\s/g, ''))) {
            alert('Please enter a valid phone number');
            return false;
        }
        
        return true;
    }
    
    // Function to submit form
    function submitForm() {
        // Show loading state
        submitBtn.disabled = true;
        submitBtn.innerHTML = `
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
            </svg>
            Processing...
        `;
        
        // Submit the form
        form.submit();
    }
    
    // Form submission handler
    form.addEventListener('submit', function(e) {
        // For all methods, prevent default and handle via auto-submit
        e.preventDefault();
    });
    
    // Auto-select Paystack as default payment method
    if (paymentMethods.length > 0 && !paymentMethodInput.value) {
        const paystackMethod = Array.from(paymentMethods).find(method => 
            method.getAttribute('data-method') === 'paystack' && !method.classList.contains('coming-soon')
        );
        if (paystackMethod) {
            paystackMethod.click();
        }
    }
});
</script>

<?php
require_once 'includes/footer.php';
?>