<?php
// book-flight.php

// Start session at the VERY beginning to avoid header errors
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';

// Include PHPMailer if available
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$page_title = "Book Flight";

// Get currency settings from database - FIXED QUERY
$default_currency = 'NGN';
$conversion_rate = 1;
$currency_symbol = '₦'; // Default symbol

// NEW: SMTP settings
$smtp_host = '';
$smtp_port = 587;
$smtp_username = '';
$smtp_password = '';
$smtp_encryption = 'tls';
$smtp_from_email = '';
$smtp_from_name = 'Travel Centre';

try {
    // Use direct column selection since your table has columns for each setting
    $stmt = $pdo->query("SELECT currency, currency_rate, smtp_host, smtp_port, smtp_username, smtp_password, smtp_encryption, smtp_from_email, smtp_from_name, admin_email, logo FROM site_settings ORDER BY id DESC LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (isset($settings['currency']) && !empty($settings['currency'])) {
        $default_currency = $settings['currency'];
    }
    if (isset($settings['currency_rate']) && is_numeric($settings['currency_rate'])) {
        $conversion_rate = floatval($settings['currency_rate']);
    }
    
    // Set SMTP settings
    if (isset($settings['smtp_host']) && !empty($settings['smtp_host'])) {
        $smtp_host = $settings['smtp_host'];
    }
    if (isset($settings['smtp_port']) && !empty($settings['smtp_port'])) {
        $smtp_port = $settings['smtp_port'];
    }
    if (isset($settings['smtp_username']) && !empty($settings['smtp_username'])) {
        $smtp_username = $settings['smtp_username'];
    }
    if (isset($settings['smtp_password']) && !empty($settings['smtp_password'])) {
        $smtp_password = $settings['smtp_password'];
    }
    if (isset($settings['smtp_encryption']) && !empty($settings['smtp_encryption'])) {
        $smtp_encryption = $settings['smtp_encryption'];
    }
    if (isset($settings['smtp_from_email']) && !empty($settings['smtp_from_email'])) {
        $smtp_from_email = $settings['smtp_from_email'];
    }
    if (isset($settings['smtp_from_name']) && !empty($settings['smtp_from_name'])) {
        $smtp_from_name = $settings['smtp_from_name'];
    }
    
    // Set currency symbol based on currency code
    switch($default_currency) {
        case 'USD':
            $currency_symbol = '$';
            break;
        case 'EUR':
            $currency_symbol = '€';
            break;
        case 'GBP':
            $currency_symbol = '£';
            break;
        case 'NGN':
        default:
            $currency_symbol = '₦';
            break;
    }
} catch (Exception $e) {
    // Use defaults if there's an error
    error_log("Settings error: " . $e->getMessage());
}

// NEW: Initialize payment reminder settings
$reminder_enabled = true;
$reminder_interval = 3; // hours
$max_reminders = 3;
$reminder_message = "Dear customer, your flight booking with reference {booking_reference} is still pending payment. Please complete your payment within 24 hours to secure your booking. Total amount: {currency_symbol}{total_amount} {currency}. Payment link: {payment_link}";

try {
    // Check if payment_reminder_settings table exists, if not create it
    $pdo->query("SELECT 1 FROM payment_reminder_settings LIMIT 1");
} catch (Exception $e) {
    // Table doesn't exist, create it
    $create_table_sql = "
    CREATE TABLE IF NOT EXISTS payment_reminder_settings (
        id INT PRIMARY KEY AUTO_INCREMENT,
        reminder_enabled BOOLEAN DEFAULT TRUE,
        reminder_interval_hours INT DEFAULT 3,
        max_reminders INT DEFAULT 3,
        reminder_message TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $pdo->exec($create_table_sql);
    
    // Insert default settings
    $insert_sql = "
    INSERT INTO payment_reminder_settings (reminder_enabled, reminder_interval_hours, max_reminders, reminder_message) 
    VALUES (?, ?, ?, ?)
    ";
    $stmt = $pdo->prepare($insert_sql);
    $stmt->execute([1, 3, 3, $reminder_message]);
}

try {
    // Get payment reminder settings
    $stmt = $pdo->query("SELECT * FROM payment_reminder_settings ORDER BY id DESC LIMIT 1");
    $reminder_settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($reminder_settings) {
        $reminder_enabled = (bool)$reminder_settings['reminder_enabled'];
        $reminder_interval = intval($reminder_settings['reminder_interval_hours']);
        $max_reminders = intval($reminder_settings['max_reminders']);
        if (!empty($reminder_settings['reminder_message'])) {
            $reminder_message = $reminder_settings['reminder_message'];
        }
    }
} catch (Exception $e) {
    error_log("Payment reminder settings error: " . $e->getMessage());
}

// NEW: Function to send payment reminders
function sendPaymentReminders($pdo, $reminder_settings) {
    if (!$reminder_settings['reminder_enabled']) {
        return 0;
    }
    
    $interval_hours = $reminder_settings['reminder_interval_hours'];
    $max_reminders = $reminder_settings['max_reminders'];
    
    // Calculate the time threshold for reminders
    $reminder_threshold = date('Y-m-d H:i:s', strtotime("-$interval_hours hours"));
    
    try {
        // Get pending bookings that need reminders
        $stmt = $pdo->prepare("
            SELECT fb.*, 
                   pr.reminder_count,
                   pr.last_reminder_sent,
                   pr.reminder_sent_at
            FROM flight_bookings fb
            LEFT JOIN payment_reminders pr ON fb.id = pr.booking_id
            WHERE fb.payment_status = 'pending'
            AND (pr.last_reminder_sent IS NULL OR pr.last_reminder_sent <= ?)
            AND (pr.reminder_count IS NULL OR pr.reminder_count < ?)
            AND fb.created_at <= ?
        ");
        
        $stmt->execute([$reminder_threshold, $max_reminders, $reminder_threshold]);
        $pending_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $reminders_sent = 0;
        
        foreach ($pending_bookings as $booking) {
            // Prepare reminder data
            $booking_id = $booking['id'];
            $booking_reference = $booking['booking_reference'];
            $total_amount = $booking['total_amount'];
            $currency = $booking['currency'];
            $is_guest = $booking['is_guest'];
            
            // Get contact info
            $contact_info = json_decode($booking['contact_info'], true);
            $email = $contact_info['email'] ?? ($is_guest ? $booking['guest_email'] : '');
            
            if (empty($email)) {
                continue; // Skip if no email
            }
            
            // Get currency symbol
            $currency_symbol = '₦';
            switch($currency) {
                case 'USD': $currency_symbol = '$'; break;
                case 'EUR': $currency_symbol = '€'; break;
                case 'GBP': $currency_symbol = '£'; break;
            }
            
            // Prepare payment link
            $payment_link = "https://travelcentre.ng/payment.php?booking_id=" . $booking_id . "&type=" . ($is_guest ? 'guest' : 'user');
            
            // Format total amount with commas
            $formatted_total_amount = number_format($total_amount, 2);
            
            // Prepare reminder message with placeholders
            $message = str_replace(
                ['{booking_reference}', '{total_amount}', '{currency}', '{currency_symbol}', '{payment_link}'],
                [$booking_reference, $formatted_total_amount, $currency, $currency_symbol, $payment_link],
                $reminder_settings['reminder_message']
            );
            
            // Send reminder email
            $subject = "🚨 Payment Reminder - Booking Reference: " . $booking_reference;
            $email_sent = sendReminderEmail($email, $subject, $message, $booking_reference);
            
            if ($email_sent) {
                // Update or insert reminder record
                $current_count = $booking['reminder_count'] ?? 0;
                $new_count = $current_count + 1;
                
                $update_stmt = $pdo->prepare("
                    INSERT INTO payment_reminders (booking_id, reminder_count, last_reminder_sent, reminder_sent_at)
                    VALUES (?, ?, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE 
                    reminder_count = ?, 
                    last_reminder_sent = NOW(),
                    reminder_sent_at = NOW()
                ");
                
                $update_stmt->execute([$booking_id, $new_count, $new_count]);
                $reminders_sent++;
                
                error_log("Payment reminder sent for booking: " . $booking_reference . " to: " . $email);
            }
        }
        
        return $reminders_sent;
        
    } catch (Exception $e) {
        error_log("Error sending payment reminders: " . $e->getMessage());
        return 0;
    }
}

// NEW: Function to send reminder email using SMTP
function sendReminderEmail($to, $subject, $message, $booking_reference) {
    global $pdo, $smtp_host, $smtp_port, $smtp_username, $smtp_password, $smtp_encryption, $smtp_from_email, $smtp_from_name;
    
    $website_email = 'support@travelcentre.ng';
    $website_url = 'https://travelcentre.ng';
    $website_logo = '';
    
    try {
        // Get website settings
        $stmt = $pdo->query("SELECT admin_email, logo FROM site_settings ORDER BY id DESC LIMIT 1");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($settings) {
            if (!empty($settings['admin_email'])) $website_email = $settings['admin_email'];
            if (!empty($settings['logo'])) $website_logo = $settings['logo'];
        }
    } catch (Exception $e) {
        error_log("Website settings error: " . $e->getMessage());
    }
    
    // Create HTML email template
    $html_message = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, Poppins; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f8f9fa; padding: 20px; border-radius: 0 0 10px 10px; }
            .reminder-box { background: white; padding: 20px; border-radius: 8px; border-left: 4px solid #ffc107; margin: 20px 0; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            .btn { display: inline-block; padding: 12px 24px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }
            .logo { max-width: 120px; margin-bottom: 15px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                " . (!empty($website_logo) ? "<img src='{$website_logo}' alt='Travel Centre' class='logo'>" : "<h1>✈️ Travel Centre</h1>") . "
                <p>Payment Reminder</p>
            </div>
            <div class='content'>
                <div class='reminder-box'>
                    <h3>Payment Required for Booking: {$booking_reference}</h3>
                    <p>" . nl2br(htmlspecialchars($message)) . "</p>
                    <a href='{$website_url}/payment.php' class='btn'>Complete Payment Now</a>
                </div>
                <p><strong>Need help?</strong> Contact our support team at {$website_email} or call +234 903 407 2383</p>
            </div>
            <div class='footer'>
                <p>This is an automated payment reminder. Please do not reply to this email.</p>
                <p>© " . date('Y') . " Travel Centre. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>";
    
    // Use SMTP if configured, otherwise fall back to mail()
    if (!empty($smtp_host) && !empty($smtp_username) && !empty($smtp_password)) {
        return sendEmailSMTP(
            $smtp_host,
            $smtp_port,
            $smtp_username,
            $smtp_password,
            $smtp_from_email,
            $smtp_from_name,
            $to,
            $subject,
            $html_message,
            true,
            $smtp_encryption
        );
    } else {
        // Fall back to PHP mail() function (may not work on some servers)
        $headers = "From: " . $smtp_from_name . " <" . $smtp_from_email . ">\r\n";
        $headers .= "Reply-To: {$website_email}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        
        return mail($to, $subject, $html_message, $headers);
    }
}

// NEW: Check and create payment_reminders table if it doesn't exist
try {
    $pdo->query("SELECT 1 FROM payment_reminders LIMIT 1");
} catch (Exception $e) {
    // Table doesn't exist, create it
    $create_reminders_table = "
    CREATE TABLE IF NOT EXISTS payment_reminders (
        id INT PRIMARY KEY AUTO_INCREMENT,
        booking_id INT NOT NULL,
        reminder_count INT DEFAULT 0,
        last_reminder_sent DATETIME NULL,
        reminder_sent_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (booking_id) REFERENCES flight_bookings(id) ON DELETE CASCADE,
        UNIQUE KEY unique_booking (booking_id)
    )";
    $pdo->exec($create_reminders_table);
}

// NEW: Send payment reminders if enabled (run this on page load)
if ($reminder_enabled) {
    $reminders_sent = sendPaymentReminders($pdo, [
        'reminder_enabled' => $reminder_enabled,
        'reminder_interval_hours' => $reminder_interval,
        'max_reminders' => $max_reminders,
        'reminder_message' => $reminder_message
    ]);
    
    // Log reminder activity (optional)
    if ($reminders_sent > 0) {
        error_log("Payment reminders sent: " . $reminders_sent . " at " . date('Y-m-d H:i:s'));
    }
}

// Handle flight data from various sources
$flight_data = null;
$flight_options = [];
$return_from_login = false;

// DEBUG: Log session data
error_log("Session flight_data exists: " . (isset($_SESSION['flight_data']) ? 'YES' : 'NO'));
error_log("POST flight_data exists: " . (isset($_POST['flight_data']) ? 'YES' : 'NO'));
error_log("GET flight_data exists: " . (isset($_GET['flight_data']) ? 'YES' : 'NO'));

// FIXED: Check for flight data in the correct order
if (isset($_POST['flight_data'])) {
    $flight_data = json_decode($_POST['flight_data'], true);
    $_SESSION['flight_data'] = $flight_data; // Store in session for persistence
    error_log("Flight data from POST");
} elseif (isset($_GET['flight_data'])) {
    $flight_data = json_decode(urldecode($_GET['flight_data']), true);
    $_SESSION['flight_data'] = $flight_data; // Store in session for persistence
    error_log("Flight data from GET");
} elseif (isset($_SESSION['flight_data'])) {
    $flight_data = $_SESSION['flight_data'];
    error_log("Flight data from SESSION");
} elseif (isset($_SESSION['pending_booking']['flight_data'])) {
    $flight_data = json_decode($_SESSION['pending_booking']['flight_data'], true);
    $return_from_login = true;
    error_log("Flight data from pending booking");
}

// NEW: Get flight options from session
if (isset($_SESSION['flight_search_results'])) {
    $flight_options = $_SESSION['flight_search_results'];
} elseif (isset($_POST['flight_options'])) {
    $flight_options = json_decode($_POST['flight_options'], true);
    $_SESSION['flight_search_results'] = $flight_options;
}

// If no flight data, redirect to flights page
if (!$flight_data) {
    error_log("No flight data found, redirecting to flights.php");
    header('Location: flights.php');
    exit;
}

// NEW: Function to get city name from airport code
function getCityName($iataCode) {
    $cityMap = [
        'LOS' => 'Lagos',
        'ABV' => 'Abuja',
        'PHC' => 'Port Harcourt',
        'KAN' => 'Kano',
        'QUO' => 'Uyo',
        'CBQ' => 'Calabar',
        'ENU' => 'Enugu',
        'IBA' => 'Ibadan',
        'ILR' => 'Ilorin',
        'JOS' => 'Jos',
        'KAD' => 'Kaduna',
        'MIU' => 'Maiduguri',
        'MDI' => 'Makurdi',
        'MXJ' => 'Minna',
        'YOL' => 'Yola',
        'ZAR' => 'Zaria',
        'ACC' => 'Accra',
        'LHR' => 'London',
        'JFK' => 'New York',
        'DXB' => 'Dubai',
        'CDG' => 'Paris',
        'FRA' => 'Frankfurt'
    ];
    
    return $cityMap[$iataCode] ?? $iataCode;
}

// Handle form submission for both logged-in and guest users
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['booking_type'])) {
        $booking_type = $_POST['booking_type'];
        $passengers = $_POST['passengers'];
        
        // Common booking data
        $contact_info = [
            'email' => sanitize($_POST['contact_email']),
            'phone' => sanitize($_POST['contact_phone']),
            'passport_number' => sanitize($_POST['passport_number'])
        ];
        
        // Generate booking reference and tracking ID
        $booking_reference = 'TC' . date('YmdHis') . strtoupper(generateRandomString(6));
        $tracking_id = 'TRK' . strtoupper(generateRandomString(10));
        
        // Calculate total amount with proper currency handling
        $base_price = floatval($flight_data['price']['grandTotal']);
        $price_currency = $flight_data['price']['currency'] ?? 'USD';
        
        // Convert to selected currency if necessary
        if ($default_currency === 'NGN' && $price_currency === 'USD') {
            $total_amount = round($base_price * $conversion_rate, 2);
            $booking_currency = 'NGN';
        } elseif ($default_currency === 'USD' && $price_currency === 'NGN') {
            $total_amount = round($base_price / $conversion_rate, 2);
            $booking_currency = 'USD';
        } else {
            $total_amount = $base_price;
            $booking_currency = $price_currency;
        }
        
        try {
            if ($booking_type === 'guest') {
                // Guest booking - REMOVED booking_date column
                $stmt = $pdo->prepare("INSERT INTO flight_bookings (
                    booking_reference, user_id, is_guest, guest_email, guest_phone, 
                    flight_data, passenger_info, contact_info, total_amount, currency, 
                    tracking_id, payment_status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $stmt->execute([
                    $booking_reference,
                    null, // user_id is null for guests
                    1, // is_guest
                    $contact_info['email'],
                    $contact_info['phone'],
                    json_encode($flight_data),
                    json_encode($passengers),
                    json_encode($contact_info),
                    $total_amount,
                    $booking_currency,
                    $tracking_id,
                    'pending'
                ]);
                
                $booking_id = $pdo->lastInsertId();
                
                // NEW: Initialize payment reminder record for this booking
                $reminder_stmt = $pdo->prepare("
                    INSERT INTO payment_reminders (booking_id, reminder_count, last_reminder_sent)
                    VALUES (?, 0, NULL)
                ");
                $reminder_stmt->execute([$booking_id]);
                
                // Send enhanced booking emails with better design
                sendEnhancedBookingEmails($pdo, $booking_id, $flight_data, $passengers, $contact_info, $booking_reference, $tracking_id, $total_amount, $booking_currency, $booking_type);
                
                // Store guest booking in session for payment and potential registration
                $_SESSION['guest_booking'] = [
                    'booking_id' => $booking_id,
                    'booking_reference' => $booking_reference,
                    'tracking_id' => $tracking_id,
                    'guest_email' => $contact_info['email'],
                    'timestamp' => time()
                ];
                
                // Clear session data after successful booking
                unset($_SESSION['flight_data']);
                unset($_SESSION['flight_search_results']);
                
                header('Location: payment.php?booking_id=' . $booking_id . '&type=guest');
                exit;
                
            } else {
                // Logged-in user booking - REMOVED booking_date column
                $stmt = $pdo->prepare("INSERT INTO flight_bookings (
                    booking_reference, user_id, flight_data, passenger_info, 
                    contact_info, total_amount, currency, tracking_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                
                $stmt->execute([
                    $booking_reference,
                    $_SESSION['user_id'],
                    json_encode($flight_data),
                    json_encode($passengers),
                    json_encode($contact_info),
                    $total_amount,
                    $booking_currency,
                    $tracking_id
                ]);
                
                $booking_id = $pdo->lastInsertId();
                
                // NEW: Initialize payment reminder record for this booking
                $reminder_stmt = $pdo->prepare("
                    INSERT INTO payment_reminders (booking_id, reminder_count, last_reminder_sent)
                    VALUES (?, 0, NULL)
                ");
                $reminder_stmt->execute([$booking_id]);
                
                // Send enhanced booking emails with better design
                sendEnhancedBookingEmails($pdo, $booking_id, $flight_data, $passengers, $contact_info, $booking_reference, $tracking_id, $total_amount, $booking_currency, $booking_type);
                
                // Add notification
                addNotification($pdo, $_SESSION['user_id'], 'Booking Created', 'Your flight booking has been created. Reference: ' . $booking_reference, 'success', 'booking', $booking_id);
                
                // Clear session data after successful booking
                unset($_SESSION['flight_data']);
                unset($_SESSION['flight_search_results']);
                
                header('Location: payment.php?booking_id=' . $booking_id);
                exit;
            }
            
            // Clear any pending booking session data
            if (isset($_SESSION['pending_booking'])) {
                unset($_SESSION['pending_booking']);
            }
            if (isset($_SESSION['flight_redirect'])) {
                unset($_SESSION['flight_redirect']);
            }
            
        } catch (Exception $e) {
            $error = "Booking failed: " . $e->getMessage();
        }
    }
}

// NEW: Payment Reminder Information Display
$reminder_info_html = "";
if ($reminder_enabled) {
    $reminder_info_html = "
    <div class='reminder-info-card'>
        <div class='reminder-icon'>
            <svg width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'>
                <path d='M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9'></path>
                <path d='M13.73 21a2 2 0 0 1-3.46 0'></path>
            </svg>
        </div>
        <div class='reminder-content'>
            <h4>Please note:</h4>
            <p><strong>Active:</strong> You will receive payment reminders every {$reminder_interval} hours if payment is not completed.</p>
            <p><strong>Maximum reminders:</strong> {$max_reminders} reminders will be sent before cancellation in <strong>24hrs</strong>.</p>
        </div>
    </div>";
}

// NEW FUNCTION: Send enhanced booking emails with better design
function sendEnhancedBookingEmails($pdo, $booking_id, $flight_data, $passengers, $contact_info, $booking_reference, $tracking_id, $total_amount, $currency, $booking_type) {
    global $smtp_host, $smtp_port, $smtp_username, $smtp_password, $smtp_encryption, $smtp_from_email, $smtp_from_name;
    
    $admin_email = 'admin@travelcentre.ng';
    $user_email = $contact_info['email'];
    
    // Get website settings for email
    $website_email = 'support@travelcentre.ng';
    $website_logo = '';
    $website_url = 'https://travelcentre.ng'; // Add website URL for links
    try {
        $stmt = $pdo->query("SELECT admin_email, logo FROM site_settings ORDER BY id DESC LIMIT 1");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($settings) {
            if (!empty($settings['admin_email'])) $website_email = $settings['admin_email'];
            if (!empty($settings['logo'])) $website_logo = $settings['logo'];
        }
    } catch (Exception $e) {
        error_log("Website settings error: " . $e->getMessage());
    }
    
    // Get currency symbol
    $currency_symbol = '₦';
    switch($currency) {
        case 'USD': $currency_symbol = '$'; break;
        case 'EUR': $currency_symbol = '€'; break;
        case 'GBP': $currency_symbol = '£'; break;
    }
    
    // Format total amount with commas for thousands separators
    $formatted_total_amount = number_format($total_amount, 2);
    
    // Prepare flight details
    $itinerary = $flight_data['itineraries'][0];
    $first_segment = $itinerary['segments'][0];
    $last_segment = end($itinerary['segments']);
    
    $departure_city = getCityName($first_segment['departure']['iataCode']);
    $arrival_city = getCityName($last_segment['arrival']['iataCode']);
    $flight_route = $departure_city . ' → ' . $arrival_city;
    $departure_date = date('M j, Y', strtotime($first_segment['departure']['at']));
    $departure_time = date('H:i', strtotime($first_segment['departure']['at']));
    $arrival_time = date('H:i', strtotime($last_segment['arrival']['at']));
    $airline = $first_segment['carrierCode'];
    $flight_class = $flight_data['travelerPricings'][0]['fareDetailsBySegment'][0]['cabin'] ?? 'Economy';
    $duration = substr($itinerary['duration'], 2);
    
    // Get airline name
    $airline_name = $airline;
    if (function_exists('getAirlineNameFromAmadeus')) {
        $airline_name = getAirlineNameFromAmadeus($airline);
    }
    
    // Prepare passenger details
    $passenger_details = '';
    $passenger_count = count($passengers);
    foreach ($passengers as $index => $passenger) {
        $passenger_num = $index + 1;
        $passenger_details .= "
        <tr>
            <td style='padding: 8px; border-bottom: 1px solid #eee;'>Passenger {$passenger_num}</td>
            <td style='padding: 8px; border-bottom: 1px solid #eee;'>{$passenger['first_name']} {$passenger['last_name']}</td>
            <td style='padding: 8px; border-bottom: 1px solid #eee;'>" . date('M j, Y', strtotime($passenger['dob'])) . "</td>
            <td style='padding: 8px; border-bottom: 1px solid #eee;'>" . ucfirst($passenger['gender']) . "</td>
        </tr>";
    }
    
    // Current date and time
    $booking_date = date('F j, Y \a\t H:i');
    
    // Generate invoice HTML for downloadable version
    $invoice_html = generateInvoiceHTML($flight_data, $passengers, $contact_info, $booking_reference, $tracking_id, $total_amount, $currency, $booking_type, $website_logo, $website_email);
    
    // Prepare email subject
    $subject = "✈️ Flight Booking Confirmation - {$booking_reference}";
    
    // Prepare payment and invoice links
    $payment_link = "{$website_url}/payment.php?booking_id={$booking_id}&type={$booking_type}";
    $invoice_link = "{$website_url}/invoice.php?booking_ref={$booking_reference}";
    
    // Prepare HTML email body with modern design
    $email_body = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Flight Booking Confirmation</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, Poppins; line-height: 1.6; color: #333; background: #f8fafc; }
            .container { max-width: 700px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; color: white; }
            .header h1 { font-size: 28px; margin-bottom: 10px; font-weight: 700; }
            .header p { opacity: 0.9; font-size: 16px; }
            .logo { max-width: 120px; margin-bottom: 15px; }
            .content { padding: 30px; }
            .section { margin-bottom: 25px; padding: 20px; background: #f8fafc; border-radius: 8px; border-left: 4px solid #667eea; }
            .section-title { color: #2d3748; margin-bottom: 15px; font-size: 18px; font-weight: 600; display: flex; align-items: center; }
            .section-title svg { margin-right: 10px; }
            .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
            .info-item { margin-bottom: 10px; }
            .info-label { font-weight: 600; color: #4a5568; font-size: 14px; }
            .info-value { color: #2d3748; font-size: 15px; }
            table { width: 100%; border-collapse: collapse; margin: 15px 0; }
            th { background: #667eea; color: white; padding: 12px; text-align: left; font-weight: 600; }
            td { padding: 12px; border-bottom: 1px solid #e2e8f0; }
            .status-badge { display: inline-block; padding: 6px 12px; background: #fed7d7; color: #c53030; border-radius: 20px; font-size: 12px; font-weight: 600; }
            .price-section { background: linear-gradient(135deg, #48bb78 0%, #38a169 100%); color: white; padding: 20px; border-radius: 8px; text-align: center; }
            .price-amount { font-size: 32px; font-weight: 700; margin-bottom: 5px; }
            .price-label { opacity: 0.9; font-size: 14px; }
            .actions { text-align: center; margin: 25px 0; }
            .btn { display: inline-block; padding: 12px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 6px; font-weight: 600; margin: 0 10px; }
            .btn-secondary { background: #718096; }
            .footer { background: #2d3748; color: white; padding: 25px; text-align: center; }
            .footer a { color: #a0aec0; text-decoration: none; }
            .footer a:hover { color: white; }
            .tracking-info { background: #e6fffa; border-left: 4px solid #38b2ac; padding: 15px; border-radius: 6px; margin: 20px 0; }
            @media (max-width: 600px) {
                .info-grid { grid-template-columns: 1fr; }
                .btn { display: block; margin: 10px 0; }
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                " . (!empty($website_logo) ? "<img src='{$website_logo}' alt='Travel Centre' class='logo'>" : "<h1>✈️ TRAVEL CENTRE</h1>") . "
                <h1>Flight Booking Confirmation</h1>
                <p>Your booking has been received and is pending payment</p>
            </div>
            
            <div class='content'>
                <!-- Booking Summary -->
                <div class='section'>
                    <div class='section-title'>
                        <svg width='20' height='20' viewBox='0 0 24 24' fill='currentColor'>
                            <path d='M17 3H7c-1.1 0-1.99.9-1.99 2L5 21l7-3 7 3V5c0-1.1-.9-2-2-2z'/>
                        </svg>
                        Booking Summary
                    </div>
                    <div class='info-grid'>
                        <div class='info-item'>
                            <div class='info-label'>Booking Reference</div>
                            <div class='info-value'><strong>{$booking_reference}</strong></div>
                        </div>
                        <div class='info-item'>
                            <div class='info-label'>Tracking ID</div>
                            <div class='info-value'><strong>{$tracking_id}</strong></div>
                        </div>
                        <div class='info-item'>
                            <div class='info-label'>Booking Date</div>
                            <div class='info-value'>{$booking_date}</div>
                        </div>
                        <div class='info-item'>
                            <div class='info-label'>Booking Type</div>
                            <div class='info-value'>" . ($booking_type === 'guest' ? 'Guest Booking' : 'Registered User') . "</div>
                        </div>
                    </div>
                </div>

                <!-- Flight Details -->
                <div class='section'>
                    <div class='section-title'>
                        <svg width='20' height='20' viewBox='0 0 24 24' fill='currentColor'>
                            <path d='M21 16v-2l-8-5V3.5c0-.83-.67-1.5-1.5-1.5S10 2.67 10 3.5V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5l8 2.5z'/>
                        </svg>
                        Flight Details
                    </div>
                    <div class='info-grid'>
                        <div class='info-item'>
                            <div class='info-label'>Route</div>
                            <div class='info-value'><strong>{$flight_route}</strong></div>
                        </div>
                        <div class='info-item'>
                            <div class='info-label'>Airline</div>
                            <div class='info-value'>{$airline_name}</div>
                        </div>
                        <div class='info-item'>
                            <div class='info-label'>Departure</div>
                            <div class='info-value'>{$departure_date} at {$departure_time}</div>
                        </div>
                        <div class='info-item'>
                            <div class='info-label'>Arrival</div>
                            <div class='info-value'>{$departure_date} at {$arrival_time}</div>
                        </div>
                        <div class='info-item'>
                            <div class='info-label'>Duration</div>
                            <div class='info-value'>{$duration}</div>
                        </div>
                        <div class='info-item'>
                            <div class='info-label'>Class</div>
                            <div class='info-value'>{$flight_class}</div>
                        </div>
                    </div>
                </div>

                <!-- Passenger Details -->
                <div class='section'>
                    <div class='section-title'>
                        <svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'>
                            <path d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/>
                        </svg>
                        Passenger Details ({$passenger_count} Passenger" . ($passenger_count > 1 ? 's' : '') . ")
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Passenger</th>
                                <th>Full Name</th>
                                <th>Date of Birth</th>
                                <th>Gender</th>
                            </tr>
                        </thead>
                        <tbody>
                            {$passenger_details}
                        </tbody>
                    </table>
                </div>

                <!-- Contact Information -->
                <div class='section'>
                    <div class='section-title'>
                        <svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'>
                            <path d='M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z'/>
                        </svg>
                        Contact Information
                    </div>
                    <div class='info-grid'>
                        <div class='info-item'>
                            <div class='info-label'>Email</div>
                            <div class='info-value'>{$contact_info['email']}</div>
                        </div>
                        <div class='info-item'>
                            <div class='info-label'>Phone</div>
                            <div class='info-value'>{$contact_info['phone']}</div>
                        </div>
                        <div class='info-item'>
                            <div class='info-label'>Passport Number</div>
                            <div class='info-value'>{$contact_info['passport_number']}</div>
                        </div>
                    </div>
                </div>

                <!-- Price & Status -->
                <div class='price-section'>
                    <div class='price-amount'>{$currency_symbol}{$formatted_total_amount} {$currency}</div>
                    <div class='price-label'>Total Amount</div>
                </div>

                <div class='tracking-info'>
                    <strong>📋 Booking Status:</strong> <span class='status-badge'>Pending Payment</span><br>
                    <strong>🚨 Next Step:</strong> Complete payment within 24 hours to confirm your booking
                </div>

                <!-- Important Information -->
                <div class='section'>
                    <div class='section-title'>
                        <svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'>
                            <path d='M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z'/>
                        </svg>
                        Important Information
                    </div>
                    <ul style='padding-left: 20px; color: #4a5568;'>
                        <li style='margin-bottom: 8px;'>Please keep your <strong>Booking Reference ({$booking_reference})</strong> and <strong>Tracking ID ({$tracking_id})</strong> for future reference</li>
                        <li style='margin-bottom: 8px;'>You can track your flight status using your Tracking ID</li>
                        <li style='margin-bottom: 8px;'>Payment must be completed within 24 hours to secure your booking</li>
                        <li style='margin-bottom: 8px;'>For any inquiries, please contact our support team with your Booking Reference</li>
                    </ul>
                </div>

                <!-- Action Buttons -->
                <div class='actions'>
                    <a href='{$payment_link}' class='btn' style='background: #48bb78;'>Complete Payment</a>
                    <a href='{$invoice_link}' class='btn btn-secondary'>Download Invoice</a>
                </div>
            </div>

            <div class='footer'>
                <p>Thank you for choosing Travel Centre!</p>
                <p>For assistance, contact: {$website_email} | +234 903 407 2383</p>
                <p><a href='{$website_url}'>View Online</a> | <a href='{$website_url}/track-booking.php'>Track Booking</a> | <a href='{$website_url}/contact.php'>Contact Support</a></p>
                <p style='margin-top: 15px; font-size: 12px; color: #a0aec0;'>
                    This is an automated email. Please do not reply to this message.
                </p>
            </div>
        </div>
    </body>
    </html>";

    // Prepare admin email (different subject)
    $admin_subject = "🚨 ADMIN: New Flight Booking - {$booking_reference}";
    $admin_body = str_replace("Flight Booking Confirmation", "ADMIN NOTIFICATION - NEW BOOKING", $email_body);
    $admin_body = str_replace("Your booking has been received", "New flight booking received from " . ($booking_type === 'guest' ? 'Guest User' : 'Registered User'), $admin_body);

    // Send emails
    $admin_sent = sendHTMLEmail($admin_email, $admin_subject, $admin_body);
    $user_sent = sendHTMLEmail($user_email, $subject, $email_body);
    
    // Log email sending results
    if (!$admin_sent) {
        error_log("Failed to send booking email to admin: {$admin_email}");
    }
    if (!$user_sent) {
        error_log("Failed to send booking email to user: {$user_email}");
    }
    
    return $admin_sent && $user_sent;
}

// NEW FUNCTION: Generate HTML invoice for downloadable version
function generateInvoiceHTML($flight_data, $passengers, $contact_info, $booking_reference, $tracking_id, $total_amount, $currency, $booking_type, $website_logo, $website_email) {
    
    // Get currency symbol
    $currency_symbol = '₦';
    switch($currency) {
        case 'USD': $currency_symbol = '$'; break;
        case 'EUR': $currency_symbol = '€'; break;
        case 'GBP': $currency_symbol = '£'; break;
    }
    
    // Format total amount with commas for thousands separators
    $formatted_total_amount = number_format($total_amount, 2);
    
    // Prepare flight details
    $itinerary = $flight_data['itineraries'][0];
    $first_segment = $itinerary['segments'][0];
    $last_segment = end($itinerary['segments']);
    
    $departure_city = getCityName($first_segment['departure']['iataCode']);
    $arrival_city = getCityName($last_segment['arrival']['iataCode']);
    $flight_route = $departure_city . ' → ' . $arrival_city;
    $departure_date = date('M j, Y', strtotime($first_segment['departure']['at']));
    $departure_time = date('H:i', strtotime($first_segment['departure']['at']));
    $arrival_time = date('H:i', strtotime($last_segment['arrival']['at']));
    $airline = $first_segment['carrierCode'];
    $flight_class = $flight_data['travelerPricings'][0]['fareDetailsBySegment'][0]['cabin'] ?? 'Economy';
    $duration = substr($itinerary['duration'], 2);
    
    // Get airline name
    $airline_name = $airline;
    if (function_exists('getAirlineNameFromAmadeus')) {
        $airline_name = getAirlineNameFromAmadeus($airline);
    }
    
    // Current date and time
    $invoice_date = date('F j, Y');
    $booking_date = date('F j, Y \a\t H:i');
    
    // Generate invoice number
    $invoice_no = "INV-" . date('Ymd') . "-" . strtoupper(substr($booking_reference, -6));
    
    // Prepare passenger rows for table
    $passenger_rows = '';
    foreach ($passengers as $index => $passenger) {
        $passenger_num = $index + 1;
        $passenger_rows .= "
        <tr>
            <td style='padding: 10px; border-bottom: 1px solid #e2e8f0;'>Passenger {$passenger_num}</td>
            <td style='padding: 10px; border-bottom: 1px solid #e2e8f0;'>{$passenger['first_name']} {$passenger['last_name']}</td>
            <td style='padding: 10px; border-bottom: 1px solid #e2e8f0;'>" . date('M j, Y', strtotime($passenger['dob'])) . "</td>
            <td style='padding: 10px; border-bottom: 1px solid #e2e8f0;'>" . ucfirst($passenger['gender']) . "</td>
        </tr>";
    }
    
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Flight Invoice - {$booking_reference}</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, Poppins; line-height: 1.6; color: #333; background: white; }
            .invoice-container { max-width: 800px; margin: 0 auto; padding: 30px; }
            .header { text-align: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid #667eea; }
            .logo { max-width: 150px; margin-bottom: 10px; }
            .company-info h2 { color: #667eea; margin: 5px 0; }
            .invoice-details { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0; background: #f8fafc; padding: 20px; border-radius: 8px; }
            .section { margin: 25px 0; }
            .section-title { color: #2d3748; margin-bottom: 15px; font-size: 18px; font-weight: 600; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px; }
            table { width: 100%; border-collapse: collapse; margin: 15px 0; }
            th { background: #667eea; color: white; padding: 12px; text-align: left; font-weight: 600; }
            td { padding: 12px; border-bottom: 1px solid #e2e8f0; }
            .price-summary { background: linear-gradient(135deg, #48bb78 0%, #38a169 100%); color: white; padding: 20px; border-radius: 8px; text-align: center; margin: 20px 0; }
            .price-amount { font-size: 28px; font-weight: 700; }
            .footer { margin-top: 40px; padding-top: 20px; border-top: 2px solid #667eea; text-align: center; color: #718096; }
            @media print {
                body { margin: 0; }
                .invoice-container { padding: 20px; }
            }
        </style>
    </head>
    <body>
        <div class='invoice-container'>
            <div class='header'>
                " . (!empty($website_logo) ? "<img src='{$website_logo}' alt='Travel Centre' class='logo'>" : "<h1 style='color: #667eea;'>✈️ TRAVEL CENTRE</h1>") . "
                <div class='company-info'>
                    <h2>Official Flight Booking Partner</h2>
                    <p>flight.travelcentre.ng | {$website_email}</p>
                </div>
            </div>

            <div class='invoice-details'>
                <div>
                    <p><strong>INVOICE No:</strong> {$invoice_no}</p>
                    <p><strong>DATE:</strong> {$invoice_date}</p>
                    <p><strong>BOOKING REF:</strong> {$booking_reference}</p>
                </div>
                <div>
                    <p><strong>TRACKING ID:</strong> {$tracking_id}</p>
                    <p><strong>BOOKING DATE:</strong> {$booking_date}</p>
                    <p><strong>BOOKING TYPE:</strong> " . ($booking_type === 'guest' ? 'Guest Booking' : 'Registered User') . "</p>
                </div>
            </div>

            <div class='section'>
                <div class='section-title'>Flight Details</div>
                <table>
                    <thead>
                        <tr>
                            <th>Route</th>
                            <th>Airline</th>
                            <th>Departure</th>
                            <th>Arrival</th>
                            <th>Class</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>{$flight_route}</td>
                            <td>{$airline_name}</td>
                            <td>{$departure_date} at {$departure_time}</td>
                            <td>{$departure_date} at {$arrival_time}</td>
                            <td>{$flight_class}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class='section'>
                <div class='section-title'>Passenger Details</div>
                <table>
                    <thead>
                        <tr>
                            <th>Passenger</th>
                            <th>Full Name</th>
                            <th>Date of Birth</th>
                            <th>Gender</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$passenger_rows}
                    </tbody>
                </table>
            </div>

            <div class='section'>
                <div class='section-title'>Contact Information</div>
                <div style='background: #f8fafc; padding: 15px; border-radius: 6px;'>
                    <p><strong>Email:</strong> {$contact_info['email']}</p>
                    <p><strong>Phone:</strong> {$contact_info['phone']}</p>
                    <p><strong>Passport Number:</strong> {$contact_info['passport_number']}</p>
                </div>
            </div>

            <div class='price-summary'>
                <div class='price-amount'>{$currency_symbol}{$formatted_total_amount} {$currency}</div>
                <div>Total Amount</div>
            </div>

            <div class='section'>
                <div class='section-title'>Booking Status</div>
                <div style='background: #fed7d7; color: #c53030; padding: 15px; border-radius: 6px; text-align: center;'>
                    <strong>Status: Pending Payment</strong><br>
                    Complete payment within 24 hours to confirm your booking
                </div>
            </div>

            <div class='footer'>
                <p><strong>Thank you for choosing Travel Centre!</strong></p>
                <p>For assistance: {$website_email} | +234 903 407 2383</p>
                <p style='margin-top: 10px; font-size: 12px;'>
                    This invoice has been automatically generated. Please keep your Booking Reference and Tracking ID for future reference.
                </p>
            </div>
        </div>
    </body>
    </html>";
}

// NEW FUNCTION: Send HTML email with SMTP or fallback to mail()
if (!function_exists('sendHTMLEmail')) {
    function sendHTMLEmail($to, $subject, $body) {
        global $smtp_host, $smtp_port, $smtp_username, $smtp_password, $smtp_encryption, $smtp_from_email, $smtp_from_name;
        
        // Use SMTP if configured
        if (!empty($smtp_host) && !empty($smtp_username) && !empty($smtp_password)) {
            return sendEmailSMTP(
                $smtp_host,
                $smtp_port,
                $smtp_username,
                $smtp_password,
                $smtp_from_email,
                $smtp_from_name,
                $to,
                $subject,
                $body,
                true,
                $smtp_encryption
            );
        } else {
            // Fall back to PHP mail() function
            $headers = "From: " . $smtp_from_name . " <" . $smtp_from_email . ">\r\n";
            $headers .= "Reply-To: " . $smtp_from_email . "\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            
            return mail($to, $subject, $body, $headers);
        }
    }
}

// NEW FUNCTION: Send email using SMTP with PHPMailer
if (!function_exists('sendEmailSMTP')) {
    function sendEmailSMTP($to, $subject, $body, $from_email = null, $from_name = null) {
    global $smtp_host, $smtp_port, $smtp_username, $smtp_password, $smtp_encryption;
    
    if (empty($smtp_host) || empty($smtp_username) || empty($smtp_password)) {
        error_log("SMTP settings not configured. Cannot send email to: " . $to);
        return false;
    }
    
    try {
        // Check if PHPMailer is available
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            error_log("PHPMailer not installed. Please run: composer require phpmailer/phpmailer");
            return false;
        }
        
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = $smtp_host;
        $mail->SMTPAuth = true;
        $mail->Username = $smtp_username;
        $mail->Password = $smtp_password;
        $mail->SMTPSecure = $smtp_encryption; // 'tls' or 'ssl'
        $mail->Port = $smtp_port;
        
        // Recipients
        $mail->setFrom($from_email ?: $smtp_username, $from_name ?: 'Travel Centre');
        $mail->addAddress($to);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $e->getMessage());
        return false;
    }
    }
}

// Calculate prices for display - UPDATED: Split price between inbound and outbound
$base_price = floatval($flight_data['price']['grandTotal']);
$price_currency = $flight_data['price']['currency'] ?? 'USD';

// NEW: Calculate individual flight prices for proper splitting
$outbound_price = $base_price;
$inbound_price = 0;

// Check if it's a round trip and split the price
if (isset($flight_data['itineraries'][1])) {
    // For round trips, split the total price between outbound and inbound
    $outbound_price = round($base_price * 0.6, 2); // 60% for outbound
    $inbound_price = round($base_price * 0.4, 2);  // 40% for inbound
}

if ($default_currency === 'NGN' && $price_currency === 'USD') {
    $display_price = round($base_price * $conversion_rate, 2);
    $display_outbound = round($outbound_price * $conversion_rate, 2);
    $display_inbound = round($inbound_price * $conversion_rate, 2);
    $display_base = round(($flight_data['price']['base'] ?? $base_price) * $conversion_rate, 2);
    $display_taxes = round((($flight_data['price']['total'] ?? $base_price) - ($flight_data['price']['base'] ?? $base_price)) * $conversion_rate, 2);
} else {
    $display_price = $base_price;
    $display_outbound = $outbound_price;
    $display_inbound = $inbound_price;
    $display_base = $flight_data['price']['base'] ?? $base_price;
    $display_taxes = ($flight_data['price']['total'] ?? $base_price) - ($flight_data['price']['base'] ?? $base_price);
}

require_once 'includes/header.php';
?>

<!-- The rest of your HTML and JavaScript remains exactly the same -->
<!-- [ALL THE REST OF YOUR HTML/JAVASCRIPT CODE REMAINS UNCHANGED] -->

<style>
/* ===== CSS VARIABLES ===== */
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
    --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1);
    --radius: 8px;
    --transition: all 0.3s ease;
}

/* ===== RESET & BASE STYLES ===== */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, Poppins;
    line-height: 1.6;
    color: var(--dark);
    background: var(--light);
}

/* ===== BOOKING HERO SECTION ===== */
.booking-hero {
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    color: var(--white);
    padding: 3rem 0;
    text-align: center;
}

.booking-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 1rem;
}

.hero-content h1 {
    font-size: 2.5rem;
    margin-bottom: 1rem;
    font-weight: 700;
}

.hero-content p {
    font-size: 1.2rem;
    opacity: 0.9;
}

/* ===== ANIMATIONS ===== */
.animate-fade-in-up {
    animation: fadeInUp 0.6s ease-out;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* ===== MESSAGE STYLES ===== */
.success-message,
.error-message {
    padding: 1rem;
    border-radius: var(--radius);
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.success-message {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.error-message {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

/* ===== BOOKING GRID ===== */
.booking-grid {
    display: grid;
    grid-template-columns: 1fr 400px;
    gap: 2rem;
    margin: 2rem 0;
}

/* ===== CARD STYLES ===== */
.booking-card {
    background: var(--white);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    overflow: hidden;
    margin-bottom: 2rem;
}

.card-header {
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    color: var(--white);
    padding: 1.5rem;
}

.card-header h3 {
    margin: 0;
    font-size: 1.5rem;
}

.card-body {
    padding: 1.5rem;
}

/* ===== FORM STYLES ===== */
.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: var(--dark);
}

.form-control {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 2px solid var(--gray-light);
    border-radius: var(--radius);
    font-size: 1rem;
    transition: var(--transition);
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

/* ===== BUTTON STYLES ===== */
.btn-primary {
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    color: var(--white);
    border: none;
    padding: 1rem 2rem;
    border-radius: var(--radius);
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
    width: 100%;
    justify-content: center;
    text-align: center;
    white-space: nowrap;
    min-height: 54px;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

/* OPTIMIZED PAYMENT BUTTON FOR MOBILE */
@media (max-width: 768px) {
    .btn-primary {
        padding: 0.875rem 1rem;
        font-size: 0.95rem;
        min-height: 50px;
        white-space: normal;
        line-height: 1.3;
    }
    
    .btn-primary .desktop-text {
        display: none;
    }
    
    .btn-primary .mobile-text {
        display: inline;
    }
}

@media (min-width: 769px) {
    .btn-primary .mobile-text {
        display: none;
    }
    
    .btn-primary .desktop-text {
        display: inline;
    }
}

.btn-group {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.option-btn {
    padding: 1.5rem 1rem;
    border: 2px solid var(--gray-light);
    border-radius: var(--radius);
    background: var(--white);
    cursor: pointer;
    transition: var(--transition);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    text-decoration: none;
    color: var(--dark);
}

.option-btn:hover {
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: var(--shadow);
}

.guest-btn {
    background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
    color: var(--white);
    border-color: #48bb78;
    font-weight: 700;
    font-size: 1.1rem;
}

.guest-btn:hover {
    background: linear-gradient(135deg, #38a169 0%, #2f855a 100%);
    color: var(--white);
    border-color: #38a169;
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(72, 187, 120, 0.3);
}

.login-btn {
    background: var(--white);
    color: var(--dark);
    border: 2px solid var(--primary);
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    transition: var(--transition);
}

.login-btn:hover {
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    color: var(--white);
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: var(--shadow);
}

/* ===== PASSENGER SECTION ===== */
.passenger-section {
    background: var(--light);
    padding: 1.5rem;
    border-radius: var(--radius);
    margin-bottom: 1.5rem;
    border-left: 4px solid var(--primary);
}

.passenger-section h4 {
    margin-bottom: 1rem;
    color: var(--dark);
}

/* ===== INFO CARDS ===== */
.info-card {
    padding: 1.5rem;
    border-radius: var(--radius);
    margin-bottom: 1.5rem;
}

.info-card.warning {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    color: #856404;
}

.info-card.success {
    background: #d1ecf1;
    border: 1px solid #bee5eb;
    color: #0c5460;
}

/* ===== PRICE BREAKDOWN ===== */
.price-breakdown {
    border-top: 1px solid var(--gray-light);
    padding-top: 1rem;
}

.price-row {
    display: flex;
    justify-content: space-between;
    padding: 0.5rem 0;
    border-bottom: 1px solid var(--gray-light);
}

.price-row.total {
    border-bottom: none;
    font-weight: 600;
    font-size: 1.2rem;
    color: var(--primary);
}

.price-total {
    color: var(--primary);
    font-weight: 700;
}

/* ===== FLIGHT SUMMARY ===== */
.flight-summary {
    position: sticky;
    top: 2rem;
    height: fit-content;
}

/* ===== REMINDER INFO CARD ===== */
.reminder-info-card {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: var(--radius);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: flex-start;
    gap: 1rem;
}

.reminder-icon {
    color: #856404;
    flex-shrink: 0;
}

.reminder-content h4 {
    margin-bottom: 0.5rem;
    color: #856404;
}

.reminder-content p {
    margin-bottom: 0.5rem;
    color: #856404;
    font-size: 0.9rem;
}

/* ===== OPTIMIZED UNIFIED CALENDAR STYLES - FULLY RESPONSIVE ===== */
.unified-calendar-container {
    background: var(--white);
    border-radius: 16px;
    width: 100%;
    max-width: min(900px, 95vw);
    margin: 2rem auto;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
    box-sizing: border-box;
}

.calendar-header {
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    color: var(--white);
    padding: clamp(1rem, 3vw, 1.5rem);
    text-align: center;
    box-sizing: border-box;
}

.calendar-header h2 {
    margin-bottom: 0.5rem;
    font-size: clamp(1.2rem, 4vw, 1.5rem);
    line-height: 1.3;
}

.calendar-header p {
    opacity: 0.9;
    font-size: clamp(0.9rem, 2.5vw, 1rem);
    line-height: 1.4;
}

.calendar-body {
    padding: clamp(0.75rem, 2vw, 1rem);
    background: #f8fafc;
    box-sizing: border-box;
    overflow: hidden;
}

/* Calendar Navigation */
.calendar-navigation {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: clamp(1rem, 3vw, 1.5rem);
    background: var(--white);
    padding: clamp(0.75rem, 2vw, 1rem);
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    gap: 0.5rem;
    flex-wrap: wrap;
    box-sizing: border-box;
}

.month-year-display {
    font-size: clamp(1rem, 3vw, 1.2rem);
    font-weight: 700;
    color: var(--dark);
    text-align: center;
    flex: 1;
    min-width: 150px;
    order: 1;
}

.calendar-nav-btn {
    background: var(--light);
    border: 2px solid var(--gray-light);
    border-radius: 8px;
    padding: clamp(0.4rem, 1.5vw, 0.5rem) clamp(0.6rem, 2vw, 0.75rem);
    cursor: pointer;
    transition: var(--transition);
    color: var(--dark);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: clamp(0.8rem, 2vw, 0.9rem);
    white-space: nowrap;
    box-sizing: border-box;
}

.calendar-nav-btn:hover {
    border-color: var(--primary);
    background: var(--primary);
    color: var(--white);
}

/* Calendar Grid - FULLY RESPONSIVE */
.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 1px;
    background: var(--gray-light);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    width: 100%;
    box-sizing: border-box;
}

.calendar-day-header {
    background: var(--dark);
    color: var(--white);
    padding: clamp(0.5rem, 1.5vw, 0.75rem) clamp(0.25rem, 1vw, 0.5rem);
    text-align: center;
    font-weight: 600;
    font-size: clamp(0.7rem, 2vw, 0.8rem);
    word-break: break-word;
    overflow: hidden;
    box-sizing: border-box;
}

.calendar-day {
    background: var(--white);
    padding: clamp(0.25rem, 1vw, 0.5rem);
    min-height: clamp(55px, 15vw, 80px);
    border: 1px solid var(--gray-light);
    transition: var(--transition);
    cursor: pointer;
    position: relative;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    align-items: center;
    box-sizing: border-box;
    overflow: hidden;
}

.calendar-day:hover {
    background: var(--light);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.calendar-day.other-month {
    background: #f8f9fa;
    color: var(--gray);
}

.calendar-day.selected-outbound {
    background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
    color: var(--white);
}

.calendar-day.selected-inbound {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: var(--white);
}

.calendar-day.disabled {
    background: #f8f9fa;
    color: #ccc;
    cursor: not-allowed;
}

.calendar-day.disabled:hover {
    background: #f8f9fa;
    transform: none;
    box-shadow: none;
}

.day-number {
    font-size: clamp(0.75rem, 2.5vw, 1rem);
    font-weight: 700;
    margin-bottom: 0.15rem;
    text-align: center;
    line-height: 1.2;
}

.flight-prices {
    font-size: clamp(0.5rem, 1.5vw, 0.7rem);
    line-height: 1.2;
    text-align: center;
    width: 100%;
    overflow: hidden;
}

.outbound-price {
    color: #48bb78;
    font-weight: 600;
}

.inbound-price {
    color: #667eea;
    font-weight: 600;
}

.calendar-day.selected-outbound .outbound-price,
.calendar-day.selected-inbound .inbound-price {
    color: var(--white);
    font-weight: 700;
}

/* Flight Selection Summary */
.flight-selection-summary {
    background: var(--white);
    padding: clamp(1rem, 2.5vw, 1.25rem);
    border-radius: 12px;
    margin-top: clamp(1rem, 3vw, 1.5rem);
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    box-sizing: border-box;
}

.selection-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: clamp(1rem, 2.5vw, 1.25rem);
    padding-bottom: clamp(0.75rem, 2vw, 1rem);
    border-bottom: 2px solid var(--gray-light);
    flex-wrap: wrap;
    gap: 0.5rem;
}

.selection-header h3 {
    color: var(--dark);
    font-size: clamp(1rem, 2.5vw, 1.1rem);
    font-weight: 600;
}

.clear-selection {
    background: var(--danger);
    color: var(--white);
    border: none;
    padding: clamp(0.3rem, 1vw, 0.4rem) clamp(0.6rem, 1.5vw, 0.8rem);
    border-radius: 6px;
    cursor: pointer;
    transition: var(--transition);
    font-size: clamp(0.7rem, 1.5vw, 0.8rem);
    white-space: nowrap;
}

.clear-selection:hover {
    background: #e53e3e;
    transform: translateY(-1px);
}

.selection-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: clamp(0.75rem, 2vw, 1rem);
}

.selection-card {
    background: var(--light);
    padding: clamp(1rem, 2.5vw, 1.25rem);
    border-radius: 8px;
    border-left: 4px solid var(--primary);
    box-sizing: border-box;
}

.selection-card.outbound {
    border-left-color: #48bb78;
}

.selection-card.inbound {
    border-left-color: #667eea;
}

.selection-type {
    font-size: clamp(0.75rem, 1.8vw, 0.8rem);
    color: var(--gray);
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.selection-date {
    font-size: clamp(1rem, 2.5vw, 1.1rem);
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 0.5rem;
    line-height: 1.3;
}

.selection-price {
    font-size: clamp(0.9rem, 2vw, 1rem);
    font-weight: 600;
    color: var(--primary);
}

.selection-card.outbound .selection-price {
    color: #48bb78;
}

.selection-card.inbound .selection-price {
    color: #667eea;
}

.no-selection {
    text-align: center;
    color: var(--gray);
    padding: 1.5rem;
    font-style: italic;
}

/* Calendar Footer */
.calendar-footer {
    background: var(--dark);
    color: var(--white);
    padding: clamp(1rem, 2.5vw, 1.25rem);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: clamp(1rem, 2.5vw, 1.5rem);
    position: relative;
    flex-wrap: wrap;
    box-sizing: border-box;
}

.calendar-footer::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
}

.price-summary-black {
    flex: 1;
    min-width: 200px;
}

.price-summary-black .price-row {
    border-bottom-color: rgba(255, 255, 255, 0.2);
    color: var(--white);
    padding: clamp(0.3rem, 1vw, 0.4rem) 0;
    font-size: clamp(0.8rem, 2vw, 0.9rem);
}

.price-summary-black .price-row.total {
    font-size: clamp(1rem, 2.5vw, 1.1rem);
    font-weight: 700;
    color: var(--white);
}

.continue-btn {
    background: var(--success);
    color: var(--white);
    border: none;
    padding: clamp(0.6rem, 1.5vw, 0.8rem) clamp(1rem, 2.5vw, 1.5rem);
    border-radius: 12px;
    cursor: pointer;
    transition: var(--transition);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    white-space: nowrap;
    box-shadow: 0 4px 15px rgba(72, 187, 120, 0.4);
    min-width: auto;
    text-align: center;
    justify-content: center;
    font-size: clamp(0.8rem, 2vw, 0.9rem);
    flex-shrink: 0;
}

.continue-btn:hover {
    background: #38a169;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(72, 187, 120, 0.6);
}

.continue-btn:disabled {
    background: var(--gray);
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

/* ===== RESPONSIVE DESIGN - OPTIMIZED FOR ALL DEVICES ===== */
@media (max-width: 768px) {
    .booking-grid {
        grid-template-columns: 1fr;
    }
    
    .unified-calendar-container {
        margin: 1rem auto;
        max-width: calc(100vw - 1rem);
        border-radius: 12px;
    }
    
    .calendar-navigation {
        flex-direction: column;
        gap: 0.75rem;
        text-align: center;
    }
    
    .month-year-display {
        order: -1;
    }
    
    .calendar-grid {
        grid-template-columns: repeat(7, minmax(0, 1fr));
        gap: 0.5px;
    }
    
    .calendar-day {
        min-height: clamp(50px, 12vw, 65px);
        padding: 0.2rem;
    }
    
    .selection-details {
        grid-template-columns: 1fr;
    }
    
    .calendar-footer {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
    
    .continue-btn {
        width: 100%;
        padding: 0.75rem 1rem;
        white-space: normal;
        line-height: 1.4;
    }
    
    .btn-group {
        grid-template-columns: 1fr;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .hero-content h1 {
        font-size: 2rem;
    }
    
    .hero-content p {
        font-size: 1rem;
    }
    
    .card-header h3 {
        font-size: 1.3rem;
    }
}

@media (max-width: 480px) {
    .calendar-day {
        min-height: clamp(45px, 10vw, 55px);
        padding: 0.15rem;
    }
    
    .day-number {
        font-size: 0.7rem;
    }
    
    .flight-prices {
        font-size: 0.55rem;
        line-height: 1;
    }
    
    .calendar-header h2 {
        font-size: 1.1rem;
    }
    
    .calendar-header p {
        font-size: 0.8rem;
    }
    
    .month-year-display {
        font-size: 1rem;
    }
    
    .selection-header h3 {
        font-size: 1rem;
    }
    
    .selection-date {
        font-size: 0.9rem;
    }
    
    .hero-content h1 {
        font-size: 1.7rem;
    }
    
    .booking-hero {
        padding: 2rem 0;
    }
    
    .calendar-day-header {
        font-size: 0.65rem;
        padding: 0.4rem 0.2rem;
    }
}

/* Extra small devices */
@media (max-width: 360px) {
    .calendar-day {
        min-height: 40px;
        padding: 0.1rem;
    }
    
    .day-number {
        font-size: 0.65rem;
    }
    
    .flight-prices {
        font-size: 0.5rem;
    }
    
    .unified-calendar-container {
        margin: 0.5rem auto;
        max-width: calc(100vw - 0.5rem);
    }
    
    .calendar-nav-btn {
        padding: 0.3rem 0.5rem;
        font-size: 0.75rem;
    }
}

/* ===== LOADING STATES ===== */
.loading {
    opacity: 0.7;
    pointer-events: none;
    position: relative;
}

.loading::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 20px;
    height: 20px;
    border: 2px solid transparent;
    border-top: 2px solid var(--primary);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: translate(-50%, -50%) rotate(0deg); }
    100% { transform: translate(-50%, -50%) rotate(360deg); }
}

/* ===== LEGEND STYLES ===== */
.calendar-legend {
    display: flex;
    justify-content: center;
    gap: clamp(0.75rem, 2vw, 1rem);
    margin-top: clamp(0.75rem, 2vw, 1rem);
    flex-wrap: wrap;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: clamp(0.75rem, 1.8vw, 0.8rem);
    color: var(--gray);
}

.legend-color {
    width: clamp(12px, 3vw, 14px);
    height: clamp(12px, 3vw, 14px);
    border-radius: 3px;
}

.legend-outbound {
    background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
}

.legend-inbound {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

/* Touch device optimizations */
@media (hover: none) {
    .calendar-day:hover {
        transform: none;
        box-shadow: none;
    }
    
    .calendar-nav-btn:hover {
        transform: none;
    }
    
    .continue-btn:hover {
        transform: none;
    }
}

/* High contrast mode support */
@media (prefers-contrast: high) {
    .calendar-day.selected-outbound {
        background: #48bb78;
        border: 2px solid #2d6a4f;
    }
    
    .calendar-day.selected-inbound {
        background: #667eea;
        border: 2px solid #4c63d2;
    }
}

/* Reduced motion support */
@media (prefers-reduced-motion: reduce) {
    .animate-fade-in-up {
        animation: none;
    }
    
    .calendar-day,
    .calendar-nav-btn,
    .continue-btn {
        transition: none;
    }
    
    .loading::after {
        animation: none;
    }
    .login-link {
    color: var(--primary);
    text-decoration: none;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: var(--radius);
    transition: var(--transition);
}

.login-link:hover {
    color: var(--primary-dark);
    background: rgba(102, 126, 234, 0.1);
    text-decoration: underline;
}
}
</style>

<!-- Unified Calendar Popup -->
<div id="unifiedCalendarPopup" class="unified-calendar-container" style="display: block; margin: 2rem auto;">
    <div class="calendar-header">
        <h2>Select Your Flight Dates</h2>
        <p>Choose your outbound and inbound dates in one calendar</p>
    </div>
    
    <div class="calendar-body">
        <!-- Calendar Navigation -->
        <div class="calendar-navigation">
            <button class="calendar-nav-btn" onclick="changeMonth(-1)">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M15 18l-6-6 6-6"/>
                </svg>
                Previous
            </button>
            <div class="month-year-display" id="currentMonthYear"><?php echo date('F Y'); ?></div>
            <button class="calendar-nav-btn" onclick="changeMonth(1)">
                Next
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 18l6-6-6-6"/>
                </svg>
            </button>
        </div>

        <!-- Calendar Grid -->
        <div class="calendar-grid" id="calendarGrid">
            <!-- Calendar headers -->
            <div class="calendar-day-header">Sun</div>
            <div class="calendar-day-header">Mon</div>
            <div class="calendar-day-header">Tue</div>
            <div class="calendar-day-header">Wed</div>
            <div class="calendar-day-header">Thu</div>
            <div class="calendar-day-header">Fri</div>
            <div class="calendar-day-header">Sat</div>
            
            <!-- Calendar days will be populated by JavaScript -->
        </div>

        <!-- Calendar Legend -->
        <div class="calendar-legend">
            <div class="legend-item">
                <div class="legend-color legend-outbound"></div>
                <span>Outbound Flight</span>
            </div>
            <div class="legend-item">
                <div class="legend-color legend-inbound"></div>
                <span>Inbound Flight</span>
            </div>
        </div>

        <!-- Flight Selection Summary -->
        <div class="flight-selection-summary">
            <div class="selection-header">
                <h3>Your Flight Selection</h3>
                <button class="clear-selection" onclick="clearSelection()">Clear Selection</button>
            </div>
            
            <div class="selection-details">
                <div class="selection-card outbound">
                    <div class="selection-type">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M5 12h14M12 5l7 7-7 7"/>
                        </svg>
                        Outbound Flight
                    </div>
                    <div class="selection-date" id="outboundDateDisplay">Not selected</div>
                    <div class="selection-price" id="outboundPriceDisplay">-</div>
                </div>
                
                <div class="selection-card inbound">
                    <div class="selection-type">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 12H5M12 19l-7-7 7-7"/>
                        </svg>
                        Inbound Flight
                    </div>
                    <div class="selection-date" id="inboundDateDisplay">Not selected</div>
                    <div class="selection-price" id="inboundPriceDisplay">-</div>
                </div>
            </div>
        </div>
    </div>

    <div class="calendar-footer">
        <div class="price-summary-black">
            <div class="price-breakdown">
                <div class="price-row">
                    <span>Outbound:</span>
                    <span id="outbound-price"><?php echo $currency_symbol . number_format($display_outbound, 2); ?></span>
                </div>
                <div class="price-row">
                    <span>Inbound:</span>
                    <span id="inbound-price"><?php echo $currency_symbol . number_format($display_inbound, 2); ?></span>
                </div>
                <div class="price-row total">
                    <span>Total:</span>
                    <span id="total-price"><?php 
                        $total_price = $display_outbound + $display_inbound;
                        echo $currency_symbol . number_format($total_price, 2);
                    ?></span>
                </div>
            </div>
        </div>
        <button class="continue-btn" id="continueBtn" onclick="continueToBooking()" disabled>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M5 12h14M12 5l7 7-7 7"/>
            </svg>
            Continue to Booking Details
        </button>
    </div>
</div>

<!-- Hero Section -->
<section class="booking-hero">
    <div class="booking-container">
        <div class="hero-content animate-fade-in-up">
            <h1>Complete Your Flight Booking</h1>
            <p>Secure your seats in just a few simple steps</p>
        </div>
    </div>
</section>

<!-- Main Booking Section -->
<div class="booking-container">
    <!-- Success message when returning from login -->
    <?php if (isset($return_from_login)): ?>
        <div class="success-message animate-fade-in-up">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                <polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
            <div>
                <strong>Welcome back!</strong>
                <p style="margin: 0.5rem 0 0 0;">Your flight selection has been restored. Please complete your booking details below.</p>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="error-message animate-fade-in-up">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <line x1="15" y1="9" x2="9" y2="15"/>
                <line x1="9" y1="9" x2="15" y2="15"/>
            </svg>
            <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <div class="booking-grid">
        <!-- Booking Form Column -->
        <div class="animate-fade-in-up" style="animation-delay: 0.2s;">
            <div class="booking-card">
                <div class="card-header">
                    <h3>Booking Details</h3>
                </div>
                <div class="card-body">
                    <?php if (!isLoggedIn()): ?>
                        <!-- Guest/Login Options - GUEST FIRST AND MORE PROMINENT -->
                        <div class="form-group">
                        <h4 style="margin-bottom: 1rem; color: var(--dark);">Choose how to continue</h4>
                        <div class="btn-group">
                            <button type="button" onclick="showGuestForm()" class="option-btn guest-btn">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 8px;">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                    <circle cx="12" cy="7" r="4"/>
                                </svg>
                                Continue as Guest
                            </button>
                        </div>
                        <div style="text-align: center; margin: 1rem 0;">
                            <a href="login.php?redirect=<?php echo urlencode('book-flight.php?flight_data=' . urlencode(json_encode($flight_data))); ?>" 
                               class="login-link" style="color: var(--primary); text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: var(--radius); transition: var(--transition);">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                                    <polyline points="10 17 15 12 10 7"/>
                                    <line x1="15" y1="12" x2="3" y2="12"/>
                                </svg>
                                Login to Continue
                            </a>
                        </div>
                        <p style="color: var(--gray); font-size: 0.9rem; text-align: center; margin: 1rem 0 0 0;">
                            <strong>Guest booking:</strong> Complete your booking without creating an account. You'll receive a tracking ID to monitor your flight status.
                        </p>
                    </div>
                        
                        <!-- Guest Booking Form (Initially Hidden) -->
                        <form method="POST" action="" id="guestForm" style="display: none;">
                            <input type="hidden" name="flight_data" value="<?php echo htmlspecialchars(json_encode($flight_data)); ?>">
                            <input type="hidden" name="booking_type" value="guest">
                            
                            <!-- Contact Information -->
                            <div class="form-group">
                                <h4 style="margin-bottom: 1.5rem; color: var(--dark); border-bottom: 2px solid var(--gray-light); padding-bottom: 0.5rem;">Contact Information</h4>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label class="form-label">Email Address *</label>
                                        <input type="email" name="contact_email" required class="form-control" placeholder="your@email.com">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Phone Number *</label>
                                        <input type="tel" name="contact_phone" required class="form-control" placeholder="+234 XXX XXX XXXX">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Passport Number *</label>
                                    <input type="text" name="passport_number" required class="form-control" placeholder="Enter your passport number">
                                </div>
                            </div>
                            
                            <!-- Passenger Information -->
                            <div class="form-group">
                                <h4 style="margin-bottom: 1.5rem; color: var(--dark); border-bottom: 2px solid var(--gray-light); padding-bottom: 0.5rem;">Passenger Information</h4>
                                <?php
                                // Get number of passengers from flight data
                                $adults = 1; // Default to 1 adult
                                if (isset($flight_data['travelerPricings'])) {
                                    foreach ($flight_data['travelerPricings'] as $traveler) {
                                        if ($traveler['travelerType'] === 'ADULT') {
                                            $adults = max($adults, 1);
                                        }
                                    }
                                }
                                
                                for ($i = 1; $i <= $adults; $i++):
                                ?>
                                <div class="passenger-section">
                                    <h4>Passenger <?php echo $i; ?> (Adult)</h4>
                                    <div class="form-grid">
                                        <div class="form-group">
                                            <label class="form-label">First Name *</label>
                                            <input type="text" name="passengers[<?php echo $i; ?>][first_name]" required class="form-control" placeholder="John">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Last Name *</label>
                                            <input type="text" name="passengers[<?php echo $i; ?>][last_name]" required class="form-control" placeholder="Doe">
                                        </div>
                                    </div>
                                    <div class="form-grid">
                                        <div class="form-group">
                                            <label class="form-label">Date of Birth *</label>
                                            <input type="date" name="passengers[<?php echo $i; ?>][dob]" required class="form-control">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Gender *</label>
                                            <select name="passengers[<?php echo $i; ?>][gender]" required class="form-control">
                                                <option value="male">Male</option>
                                                <option value="female">Female</option>
                                                <option value="other">Other</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <?php endfor; ?>
                            </div>
                            
                            <!-- OPTIMIZED PAYMENT BUTTON WITH MOBILE SUPPORT - CHANGED ICON TO ARROW -->
                            <button type="submit" class="btn-primary">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 8px;">
                                    <path d="M5 12h14M12 5l7 7-7 7"/>
                                </svg>
                                <span class="desktop-text">Proceed to Payment</span>
                                <span class="mobile-text">Proceed</span>
                            </button>
                            
                            <!-- Guest Information -->
                            <div class="info-card warning">
                                <h4 style="margin-bottom: 0.5rem; color: #856404;">Guest Booking Information</h4>
                                <ul style="margin: 0; color: #856404; font-size: 0.9rem; padding-left: 1.2rem;">
                                    <p></p>After completing your booking, you will receive a confirmation email containing:</p>
                                    <li>Your booking reference and tracking ID</li>
                                    <li>Flight details</li>
                                    <li>Passenger information</li>
                                    <li>A downloadable travel itinerary</li>
                                    <li>Payment receipt</li>
                                </ul>
                            </div>

                            
                            <!-- Payment Reminder Information -->
                            <?php echo $reminder_info_html; ?>
                        </form>
                        
                    <?php else: ?>
                        <!-- Logged-in User Form -->
                        <form method="POST" action="">
                            <input type="hidden" name="flight_data" value="<?php echo htmlspecialchars(json_encode($flight_data)); ?>">
                            <input type="hidden" name="booking_type" value="user">
                            
                            <!-- Contact Information -->
                            <div class="form-group">
                                <h4 style="margin-bottom: 1.5rem; color: var(--dark); border-bottom: 2px solid var(--gray-light); padding-bottom: 0.5rem;">Contact Information</h4>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label class="form-label">Email Address</label>
                                        <input type="email" name="contact_email" value="<?php echo $_SESSION['user_email']; ?>" required class="form-control">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Phone Number</label>
                                        <input type="tel" name="contact_phone" required class="form-control" placeholder="+234 XXX XXX XXXX">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Address</label>
                                    <textarea name="contact_address" required class="form-control" placeholder="Your complete address" style="height: 80px;"></textarea>
                                </div>
                            </div>
                            
                            <!-- Passenger Information -->
                            <div class="form-group">
                                <h4 style="margin-bottom: 1.5rem; color: var(--dark); border-bottom: 2px solid var(--gray-light); padding-bottom: 0.5rem;">Passenger Information</h4>
                                <?php
                                // Get number of passengers from flight data
                                $adults = 1; // Default to 1 adult
                                if (isset($flight_data['travelerPricings'])) {
                                    foreach ($flight_data['travelerPricings'] as $traveler) {
                                        if ($traveler['travelerType'] === 'ADULT') {
                                            $adults = max($adults, 1);
                                        }
                                    }
                                }
                                
                                for ($i = 1; $i <= $adults; $i++):
                                ?>
                                <div class="passenger-section">
                                    <h4>Passenger <?php echo $i; ?> (Adult)</h4>
                                    <div class="form-grid">
                                        <div class="form-group">
                                            <label class="form-label">First Name</label>
                                            <input type="text" name="passengers[<?php echo $i; ?>][first_name]" required class="form-control" placeholder="John">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Last Name</label>
                                            <input type="text" name="passengers[<?php echo $i; ?>][last_name]" required class="form-control" placeholder="Doe">
                                        </div>
                                    </div>
                                    <div class="form-grid">
                                        <div class="form-group">
                                            <label class="form-label">Date of Birth</label>
                                            <input type="date" name="passengers[<?php echo $i; ?>][dob]" required class="form-control">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Gender</label>
                                            <select name="passengers[<?php echo $i; ?>][gender]" required class="form-control">
                                                <option value="male">Male</option>
                                                <option value="female">Female</option>
                                                <option value="other">Other</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <?php endfor; ?>
                            </div>
                            
                            <!-- OPTIMIZED PAYMENT BUTTON WITH MOBILE SUPPORT - CHANGED ICON TO ARROW -->
                            <button type="submit" class="btn-primary">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 8px;">
                                    <path d="M5 12h14M12 5l7 7-7 7"/>
                                </svg>
                                <span class="desktop-text">Proceed to Payment</span>
                                <span class="mobile-text">Proceed</span>
                            </button>
                            
                            <!-- Payment Reminder Information -->
                            <?php echo $reminder_info_html; ?>
                        </form>
                    <?php endif; ?>
                    
                    <!-- Currency Information -->
                    <div class="info-card success">
                        <h4 style="margin-bottom: 0.5rem; color: var(--dark);">Currency Information</h4>
                        <p style="margin: 0; color: var(--gray);">
                            Booking in <?php echo $default_currency; ?> 
                            <?php if ($default_currency === 'NGN' && $price_currency === 'USD'): ?>
                                (Converted from USD at rate: $1 = <?php echo $currency_symbol . number_format($conversion_rate, 2); ?>)
                            <?php elseif ($default_currency === 'USD' && $price_currency === 'NGN'): ?>
                                (Converted from NGN at rate: ₦1 = $<?php echo number_format(1/$conversion_rate, 4); ?>)
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Flight Summary Column -->
        <div class="flight-summary animate-fade-in-up" style="animation-delay: 0.4s;">
            <div class="booking-card">
                <div class="card-header">
                    <h3>Flight Summary</h3>
                </div>
                <div class="card-body">
                    <?php
                    // Get flight details
                    $outbound_itinerary = $flight_data['itineraries'][0];
                    $outbound_first_segment = $outbound_itinerary['segments'][0];
                    $outbound_last_segment = end($outbound_itinerary['segments']);
                    
                    $departure_city = getCityName($outbound_first_segment['departure']['iataCode']);
                    $arrival_city = getCityName($outbound_last_segment['arrival']['iataCode']);
                    
                    // Check if it's a return flight
                    $is_return_flight = isset($flight_data['itineraries'][1]);
                    $return_route = '';
                    
                    if ($is_return_flight) {
                        $inbound_itinerary = $flight_data['itineraries'][1];
                        $inbound_first_segment = $inbound_itinerary['segments'][0];
                        $inbound_last_segment = end($inbound_itinerary['segments']);
                        
                        $return_departure_city = getCityName($inbound_first_segment['departure']['iataCode']);
                        $return_arrival_city = getCityName($inbound_last_segment['arrival']['iataCode']);
                        $return_route = $return_departure_city . ' → ' . $return_arrival_city;
                    }
                    
                    $route = $departure_city . ' → ' . $arrival_city;
                    ?>
                    
                    <!-- Route Information -->
                    <div style="border-bottom: 2px solid var(--gray-light); padding-bottom: 1rem; margin-bottom: 1rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                            <strong style="font-size: 1.3rem; color: var(--dark);">
                                <?php echo $route; ?>
                                <?php if ($is_return_flight): ?>
                                    <br><span style="font-size: 1rem; color: var(--primary);">Round Trip</span>
                                <?php endif; ?>
                            </strong>
                            <span style="color: var(--primary); font-weight: bold; font-size: 1.4rem;">
                                <?php echo $currency_symbol . number_format($display_price, 2); ?>
                            </span>
                        </div>
                        
                        <!-- Outbound Flight Details -->
                        <div style="margin-bottom: 1.5rem; padding: 1rem; background: var(--light); border-radius: var(--radius);">
                            <div style="display: flex; align-items: center; margin-bottom: 0.5rem;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 8px; color: var(--primary);">
                                    <path d="M5 12h14M12 5l7 7-7 7"/>
                                </svg>
                                <strong style="color: var(--dark);">Outbound</strong>
                                <span style="margin-left: auto; color: var(--gray); font-size: 0.9rem;">
                                    <?php echo date('M j, Y', strtotime($outbound_first_segment['departure']['at'])); ?>
                                </span>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: 1fr auto 1fr; gap: 0.5rem; align-items: center;">
                                <div>
                                    <strong style="font-size: 1.1rem;"><?php echo date('H:i', strtotime($outbound_first_segment['departure']['at'])); ?></strong>
                                    <br>
                                    <small style="color: var(--gray);"><?php echo $departure_city; ?></small>
                                </div>
                                <div style="text-align: center;">
                                    <div style="height: 2px; background: var(--primary); width: 40px; margin: 0 auto 5px auto;"></div>
                                    <small style="color: var(--gray); font-weight: 500;"><?php echo substr($outbound_itinerary['duration'], 2); ?></small>
                                </div>
                                <div style="text-align: right;">
                                    <strong style="font-size: 1.1rem;"><?php echo date('H:i', strtotime($outbound_last_segment['arrival']['at'])); ?></strong>
                                    <br>
                                    <small style="color: var(--gray);"><?php echo $arrival_city; ?></small>
                                </div>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; font-size: 0.9rem; color: var(--gray); margin-top: 0.5rem;">
                                <div><strong>Airline:</strong> <?php echo $outbound_first_segment['carrierCode']; ?></div>
                                <div><strong>Flight:</strong> <?php echo $outbound_first_segment['carrierCode'] . $outbound_first_segment['number']; ?></div>
                            </div>
                        </div>
                        
                        <!-- Inbound Flight Details (for return flights) -->
                        <?php if ($is_return_flight): ?>
                        <div style="padding: 1rem; background: var(--light); border-radius: var(--radius);">
                            <div style="display: flex; align-items: center; margin-bottom: 0.5rem;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 8px; color: var(--primary);">
                                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                                </svg>
                                <strong style="color: var(--dark);">Inbound</strong>
                                <span style="margin-left: auto; color: var(--gray); font-size: 0.9rem;">
                                    <?php echo date('M j, Y', strtotime($inbound_first_segment['departure']['at'])); ?>
                                </span>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: 1fr auto 1fr; gap: 0.5rem; align-items: center;">
                                <div>
                                    <strong style="font-size: 1.1rem;"><?php echo date('H:i', strtotime($inbound_first_segment['departure']['at'])); ?></strong>
                                    <br>
                                    <small style="color: var(--gray);"><?php echo $return_departure_city; ?></small>
                                </div>
                                <div style="text-align: center;">
                                    <div style="height: 2px; background: var(--primary); width: 40px; margin: 0 auto 5px auto;"></div>
                                    <small style="color: var(--gray); font-weight: 500;"><?php echo substr($inbound_itinerary['duration'], 2); ?></small>
                                </div>
                                <div style="text-align: right;">
                                    <strong style="font-size: 1.1rem;"><?php echo date('H:i', strtotime($inbound_last_segment['arrival']['at'])); ?></strong>
                                    <br>
                                    <small style="color: var(--gray);"><?php echo $return_arrival_city; ?></small>
                                </div>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; font-size: 0.9rem; color: var(--gray); margin-top: 0.5rem;">
                                <div><strong>Airline:</strong> <?php echo $inbound_first_segment['carrierCode']; ?></div>
                                <div><strong>Flight:</strong> <?php echo $inbound_first_segment['carrierCode'] . $inbound_first_segment['number']; ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Flight Class Information -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; font-size: 0.9rem; color: var(--gray); margin-top: 1rem;">
                            <div><strong>Class:</strong> <?php echo $flight_data['travelerPricings'][0]['fareDetailsBySegment'][0]['cabin'] ?? 'Economy'; ?></div>
                            <div><strong>Passengers:</strong> <?php echo count($flight_data['travelerPricings']); ?> Adult(s)</div>
                            <div><strong>Currency:</strong> <?php echo $default_currency; ?></div>
                            <div><strong>Booking Type:</strong> <?php echo $is_return_flight ? 'Round Trip' : 'One Way'; ?></div>
                        </div>
                    </div>
                    
                    <!-- Price Breakdown - UPDATED: Show split prices -->
                    <div class="price-breakdown">
                        <div class="price-row">
                            <span>Outbound Flight:</span>
                            <span><?php echo $currency_symbol . number_format($display_outbound, 2); ?></span>
                        </div>
                        <?php if ($is_return_flight): ?>
                        <div class="price-row">
                            <span>Inbound Flight:</span>
                            <span><?php echo $currency_symbol . number_format($display_inbound, 2); ?></span>
                        </div>
                        <?php endif; ?>
                       
                        </div>
                        <div class="price-row" style="border-bottom: none; padding-top: 1rem;">
                            <span style="font-weight: 600;">Total:</span>
                            <span class="price-total"><?php echo $currency_symbol . number_format($display_price, 2); ?></span>
                        </div>
                        
                        <!-- Original Price Info -->
                        <?php if ($price_currency !== $default_currency): ?>
                        <div class="info-card warning" style="margin-top: 1.5rem;">
                            <h4 style="margin-bottom: 0.5rem; color: #856404;">Original Price</h4>
                            <p style="margin: 0; color: #856404; font-size: 0.9rem;">
                                <?php 
                                $original_symbol = ($price_currency === 'USD') ? '$' : '₦';
                                echo $original_symbol . number_format($base_price, 2) . ' ' . $price_currency;
                                ?>
                                <br>
                                <strong>Exchange Rate:</strong> 
                                <?php 
                                if ($default_currency === 'NGN') {
                                    echo '$1 = ' . $currency_symbol . number_format($conversion_rate, 2);
                                } else {
                                    echo '₦1 = $' . number_format(1/$conversion_rate, 4);
                                }
                                ?>
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Email Notification Information -->
                    <div class="info-card success">
                        <h4 style="margin-bottom: 0.5rem; color: var(--dark);">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 8px;">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2-2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                <polyline points="22,6 12,13 2,6"/>
                            </svg>
                            Email Confirmation
                        </h4>
                        <p style="margin: 0; color: var(--gray); font-size: 0.9rem;">
                            After completing your booking, you will receive a confirmation email containing:
                        </p>
                        <ul style="margin: 0.5rem 0 0 1rem; color: var(--gray); font-size: 0.85rem;">
                            <li>Your booking reference and tracking ID</li>
                            <li>Flight details</li>
                            <li>Passenger information</li>
                            <li>A downloadable travel itinerary 
</li>
                            <li>Payment receipt</li>
                        </ul>
                    </div>
                    
                    <!-- Payment Reminder Notice -->
                    <?php if ($reminder_enabled): ?>
                    <div class="info-card warning">
                        <h4 style="margin-bottom: 0.5rem; color: #856404;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 8px;">
                                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                            </svg>
                            Payment Reminders
                        </h4>
                        <p style="margin: 0; color: #856404; font-size: 0.9rem;">
                            <strong>Please Note:</strong> Only payment guarantees your booking and locks in the fare shown on your reservation. Airline rates are subject to change at any time, and we cannot secure your fare until payment is <strong>confirmed.</strong>
                        </p>
                        
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Unified Calendar Functions
let currentDate = new Date();
let selectedOutbound = null;
let selectedInbound = null;

// Flight price data (this would typically come from your backend)
const flightPrices = {
    // Sample price data - in a real app, this would come from your flight search API
    '2024-01-15': { outbound: <?php echo $display_outbound; ?>, inbound: <?php echo $display_inbound; ?> },
    '2024-01-16': { outbound: <?php echo $display_outbound; ?> + 50, inbound: <?php echo $display_inbound; ?> + 30 },
    '2024-01-17': { outbound: <?php echo $display_outbound; ?> + 25, inbound: <?php echo $display_inbound; ?> + 15 },
    '2024-01-18': { outbound: <?php echo $display_outbound; ?> - 20, inbound: <?php echo $display_inbound; ?> - 10 },
    '2024-01-19': { outbound: <?php echo $display_outbound; ?>, inbound: <?php echo $display_inbound; ?> },
    '2024-01-20': { outbound: <?php echo $display_outbound; ?> + 75, inbound: <?php echo $display_inbound; ?> + 40 },
    '2024-01-21': { outbound: <?php echo $display_outbound; ?> + 40, inbound: <?php echo $display_inbound; ?> + 20 }
};

// Initialize calendar
document.addEventListener('DOMContentLoaded', function() {
    generateCalendar();
    updateSelectionSummary();
    updateContinueButton();
});

// Generate calendar for current month
function generateCalendar() {
    const calendarGrid = document.getElementById('calendarGrid');
    const monthYearDisplay = document.getElementById('currentMonthYear');
    
    // Clear existing days (keep headers)
    while (calendarGrid.children.length > 7) {
        calendarGrid.removeChild(calendarGrid.lastChild);
    }
    
    // Update month/year display
    monthYearDisplay.textContent = currentDate.toLocaleDateString('en-US', { 
        month: 'long', 
        year: 'numeric' 
    });
    
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();
    
    // Get first day of month and number of days
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const daysInMonth = lastDay.getDate();
    
    // Get day of week for first day (0 = Sunday, 6 = Saturday)
    let firstDayIndex = firstDay.getDay();
    
    // Add empty cells for days before the first day of the month
    for (let i = 0; i < firstDayIndex; i++) {
        const emptyDay = document.createElement('div');
        emptyDay.className = 'calendar-day other-month';
        calendarGrid.appendChild(emptyDay);
    }
    
    // Add days of the month
    for (let day = 1; day <= daysInMonth; day++) {
        const date = new Date(year, month, day);
        const dateString = date.toISOString().split('T')[0];
        
        const dayElement = document.createElement('div');
        dayElement.className = 'calendar-day';
        dayElement.setAttribute('data-date', dateString);
        
        // Check if date is in the past
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        if (date < today) {
            dayElement.classList.add('disabled');
        }
        
        // Get prices for this date
        const prices = flightPrices[dateString] || { 
            outbound: <?php echo $display_outbound; ?>, 
            inbound: <?php echo $display_inbound; ?> 
        };
        
        dayElement.innerHTML = `
            <div class="day-number">${day}</div>
            <div class="flight-prices">
                <div class="outbound-price"><?php echo $currency_symbol; ?>${prices.outbound.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
                <div class="inbound-price"><?php echo $currency_symbol; ?>${prices.inbound.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
            </div>
        `;
        
        // Add click event if not disabled
        if (!dayElement.classList.contains('disabled')) {
            dayElement.addEventListener('click', function() {
                handleDateSelection(dateString, prices);
            });
        }
        
        // Apply selection styling if this date is selected
        if (selectedOutbound === dateString) {
            dayElement.classList.add('selected-outbound');
        } else if (selectedInbound === dateString) {
            dayElement.classList.add('selected-inbound');
        }
        
        calendarGrid.appendChild(dayElement);
    }
}

// Handle date selection
function handleDateSelection(dateString, prices) {
    // If no outbound selected, select outbound
    if (!selectedOutbound) {
        selectedOutbound = dateString;
        updateCalendarStyling();
        updateSelectionSummary();
        updateContinueButton();
        return;
    }
    
    // If outbound is selected but no inbound, select inbound
    if (selectedOutbound && !selectedInbound) {
        const outboundDate = new Date(selectedOutbound);
        const inboundDate = new Date(dateString);
        
        // Ensure inbound is after outbound
        if (inboundDate <= outboundDate) {
            showNotification('Inbound date must be after outbound date', 'error');
            return;
        }
        
        selectedInbound = dateString;
        updateCalendarStyling();
        updateSelectionSummary();
        updateContinueButton();
        return;
    }
    
    // If both are selected, clear and start over with new outbound
    selectedOutbound = dateString;
    selectedInbound = null;
    updateCalendarStyling();
    updateSelectionSummary();
    updateContinueButton();
}

// Update calendar styling for selected dates
function updateCalendarStyling() {
    // Remove all selection classes
    document.querySelectorAll('.calendar-day').forEach(day => {
        day.classList.remove('selected-outbound', 'selected-inbound');
    });
    
    // Apply selection classes
    if (selectedOutbound) {
        const outboundDay = document.querySelector(`.calendar-day[data-date="${selectedOutbound}"]`);
        if (outboundDay) outboundDay.classList.add('selected-outbound');
    }
    
    if (selectedInbound) {
        const inboundDay = document.querySelector(`.calendar-day[data-date="${selectedInbound}"]`);
        if (inboundDay) inboundDay.classList.add('selected-inbound');
    }
}

// Update selection summary
function updateSelectionSummary() {
    const outboundDateDisplay = document.getElementById('outboundDateDisplay');
    const inboundDateDisplay = document.getElementById('inboundDateDisplay');
    const outboundPriceDisplay = document.getElementById('outboundPriceDisplay');
    const inboundPriceDisplay = document.getElementById('inboundPriceDisplay');
    
    if (selectedOutbound) {
        const outboundDate = new Date(selectedOutbound);
        const outboundPrices = flightPrices[selectedOutbound] || { outbound: <?php echo $display_outbound; ?>, inbound: <?php echo $display_inbound; ?> };
        
        outboundDateDisplay.textContent = outboundDate.toLocaleDateString('en-US', { 
            weekday: 'short', 
            month: 'short', 
            day: 'numeric' 
        });
        outboundPriceDisplay.textContent = `<?php echo $currency_symbol; ?>${outboundPrices.outbound.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
        
        // Update main price display
        document.getElementById('outbound-price').textContent = `<?php echo $currency_symbol; ?>${outboundPrices.outbound.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
    } else {
        outboundDateDisplay.textContent = 'Not selected';
        outboundPriceDisplay.textContent = '-';
    }
    
    if (selectedInbound) {
        const inboundDate = new Date(selectedInbound);
        const inboundPrices = flightPrices[selectedInbound] || { outbound: <?php echo $display_outbound; ?>, inbound: <?php echo $display_inbound; ?> };
        
        inboundDateDisplay.textContent = inboundDate.toLocaleDateString('en-US', { 
            weekday: 'short', 
            month: 'short', 
            day: 'numeric' 
        });
        inboundPriceDisplay.textContent = `<?php echo $currency_symbol; ?>${inboundPrices.inbound.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
        
        // Update main price display
        document.getElementById('inbound-price').textContent = `<?php echo $currency_symbol; ?>${inboundPrices.inbound.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
    } else {
        inboundDateDisplay.textContent = 'Not selected';
        inboundPriceDisplay.textContent = '-';
    }
    
    // Update total price
    updateTotalPrice();
}

// Update total price
function updateTotalPrice() {
    let total = 0;
    
    if (selectedOutbound) {
        const outboundPrices = flightPrices[selectedOutbound] || { outbound: <?php echo $display_outbound; ?>, inbound: <?php echo $display_inbound; ?> };
        total += outboundPrices.outbound;
    }
    
    if (selectedInbound) {
        const inboundPrices = flightPrices[selectedInbound] || { outbound: <?php echo $display_outbound; ?>, inbound: <?php echo $display_inbound; ?> };
        total += inboundPrices.inbound;
    }
    
    document.getElementById('total-price').textContent = `<?php echo $currency_symbol; ?>${total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
}

// Update continue button state
function updateContinueButton() {
    const continueBtn = document.getElementById('continueBtn');
    
    // Enable button only if outbound is selected (inbound is optional for one-way flights)
    if (selectedOutbound) {
        continueBtn.disabled = false;
    } else {
        continueBtn.disabled = true;
    }
}

// Change month
function changeMonth(direction) {
    currentDate.setMonth(currentDate.getMonth() + direction);
    generateCalendar();
}

// Clear selection
function clearSelection() {
    selectedOutbound = null;
    selectedInbound = null;
    updateCalendarStyling();
    updateSelectionSummary();
    updateContinueButton();
    
    // Reset prices to defaults
    document.getElementById('outbound-price').textContent = `<?php echo $currency_symbol . number_format($display_outbound, 2); ?>`;
    document.getElementById('inbound-price').textContent = `<?php echo $currency_symbol . number_format($display_inbound, 2); ?>`;
    updateTotalPrice();
}

function continueToBooking() {
    const popup = document.getElementById('unifiedCalendarPopup');
    popup.style.display = 'none';
    
    // Update the main flight data with selected dates
    const updatedFlightData = {
        ...<?php echo json_encode($flight_data); ?>,
        selectedOutboundDate: selectedOutbound,
        selectedInboundDate: selectedInbound,
        totalPrice: calculateTotalPrice()
    };
    
    // Update the hidden form field
    document.querySelector('input[name="flight_data"]').value = JSON.stringify(updatedFlightData);
    
    // Show success message
    showNotification('Flight dates selected successfully!', 'success');
}

// Calculate total price based on selections
function calculateTotalPrice() {
    let total = 0;
    
    if (selectedOutbound) {
        const outboundPrices = flightPrices[selectedOutbound] || { outbound: <?php echo $display_outbound; ?>, inbound: <?php echo $display_inbound; ?> };
        total += outboundPrices.outbound;
    }
    
    if (selectedInbound) {
        const inboundPrices = flightPrices[selectedInbound] || { outbound: <?php echo $display_outbound; ?>, inbound: <?php echo $display_inbound; ?> };
        total += inboundPrices.inbound;
    }
    
    return total;
}

// Show notification
function showNotification(message, type) {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        background: ${type === 'success' ? '#4CAF50' : '#f44336'};
        color: white;
        border-radius: 5px;
        z-index: 10000;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    `;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

// Set maximum date for date of birth to today
const today = new Date().toISOString().split('T')[0];
document.querySelectorAll('input[type="date"]').forEach(input => {
    input.max = today;
});

// Auto-fill passenger names from user profile if available
document.addEventListener('DOMContentLoaded', function() {
    const userName = "<?php echo $_SESSION['user_name'] ?? ''; ?>";
    if (userName) {
        const nameParts = userName.split(' ');
        const firstName = nameParts[0] || '';
        const lastName = nameParts.slice(1).join(' ') || '';
        
        // Fill first passenger details
        const firstNameInput = document.querySelector('input[name="passengers[1][first_name]"]');
        const lastNameInput = document.querySelector('input[name="passengers[1][last_name]"]');
        
        if (firstNameInput && !firstNameInput.value) firstNameInput.value = firstName;
        if (lastNameInput && !lastNameInput.value) lastNameInput.value = lastName;
    }
});

// Show guest form when "Continue as Guest" is clicked
function showGuestForm() {
    const guestForm = document.getElementById('guestForm');
    guestForm.style.display = 'block';
    
    // Add animation class
    guestForm.classList.add('animate-fade-in-up');
    
    // Scroll to the form smoothly
    guestForm.scrollIntoView({ 
        behavior: 'smooth',
        block: 'start'
    });
    
    // Remove the option buttons
    const btnGroup = document.querySelector('.btn-group');
    if (btnGroup) {
        btnGroup.style.display = 'none';
    }
}

// Add loading state to form submission
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 8px;"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg> Processing...';
                submitBtn.disabled = true;
                this.classList.add('loading');
            }
        });
    });
});
</script>

<?php
require_once 'includes/footer.php';
?>
