<?php
// payment.php
require_once 'config.php';

$page_title = "Payment";

$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;
$is_guest = isset($_GET['type']) && $_GET['type'] === 'guest';

// Get booking details based on user type
if ($is_guest) {
    // Guest booking - check session or booking reference
    if (isset($_SESSION['guest_booking']) && $_SESSION['guest_booking']['booking_id'] == $booking_id) {
        $stmt = $pdo->prepare("SELECT * FROM flight_bookings WHERE id = ? AND is_guest = 1");
        $stmt->execute([$booking_id]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        // If no session, check if booking exists and is guest
        $stmt = $pdo->prepare("SELECT * FROM flight_bookings WHERE id = ? AND is_guest = 1");
        $stmt->execute([$booking_id]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($booking) {
            // Store guest booking in session for future reference
            $_SESSION['guest_booking'] = [
                'booking_id' => $booking_id,
                'booking_reference' => $booking['booking_reference'],
                'guest_email' => $booking['guest_email'],
                'guest_name' => $booking['guest_name'] ?? 'Guest Customer'
            ];
        }
    }
} else {
    // Logged-in user booking
    if (!isLoggedIn()) {
        // Check if booking might be a guest booking
        $stmt = $pdo->prepare("SELECT * FROM flight_bookings WHERE id = ? AND is_guest = 1");
        $stmt->execute([$booking_id]);
        $guest_booking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($guest_booking) {
            // It's a guest booking, redirect with guest type
            redirect('payment.php?booking_id=' . $booking_id . '&type=guest');
        } else {
            redirect('login.php?redirect=' . urlencode('payment.php?booking_id=' . $booking_id));
        }
    }
    
    $stmt = $pdo->prepare("SELECT * FROM flight_bookings WHERE id = ? AND user_id = ?");
    $stmt->execute([$booking_id, $_SESSION['user_id']]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$booking) {
    // Try to find booking without user check (for guest fallback)
    $stmt = $pdo->prepare("SELECT * FROM flight_bookings WHERE id = ?");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        $_SESSION['error'] = "Booking not found.";
        redirect('flights.php');
    }
    
    // If booking is guest type, update session and set as guest
    if ($booking['is_guest'] == 1) {
        $is_guest = true;
        $_SESSION['guest_booking'] = [
            'booking_id' => $booking_id,
            'booking_reference' => $booking['booking_reference'],
            'guest_email' => $booking['guest_email'],
            'guest_name' => $booking['guest_name'] ?? 'Guest Customer'
        ];
    } else {
        // Regular user booking but user not logged in
        redirect('login.php?redirect=' . urlencode('payment.php?booking_id=' . $booking_id));
    }
}

// Check if booking is already paid
if ($booking['payment_status'] === 'paid') {
    $_SESSION['success'] = "This booking has already been paid.";
    redirect('payment-success.php?booking_id=' . $booking_id . ($is_guest ? '&guest=1' : ''));
}

// Get currency settings from database
$default_currency = 'NGN';
$conversion_rate = 1;
try {
    $stmt = $pdo->prepare("SELECT name, value FROM site_settings WHERE name IN ('currency', 'currency_rate')");
    $stmt->execute();
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    if (isset($settings['currency']) && !empty($settings['currency'])) {
        $default_currency = $settings['currency'];
    }
    if (isset($settings['currency_rate']) && is_numeric($settings['currency_rate'])) {
        $conversion_rate = floatval($settings['currency_rate']);
    }
} catch (Exception $e) {
    // Use defaults if there's an error
}

// FIXED: Proper price verification with currency support
$flight_data = json_decode($booking['flight_data'], true);
$stored_price = floatval($booking['total_amount']);
$booking_currency = $booking['currency'] ?? $default_currency;

// Verify and ensure price consistency
if (isset($flight_data['price']['grandTotal'])) {
    $base_price = floatval($flight_data['price']['grandTotal']);
    $price_currency = $flight_data['price']['currency'] ?? $booking_currency;
    
    // If booking currency doesn't match current settings, recalculate
    if ($booking_currency !== $default_currency || $price_currency !== $booking_currency) {
        if ($default_currency === 'NGN' && $price_currency === 'USD') {
            // Convert USD to NGN
            $recalculated_price = round($base_price * $conversion_rate, 2);
            $new_currency = 'NGN';
        } elseif ($default_currency === 'USD' && $price_currency === 'NGN') {
            // Convert NGN to USD
            $recalculated_price = round($base_price / $conversion_rate, 2);
            $new_currency = 'USD';
        } else {
            // Same currency, use directly
            $recalculated_price = $base_price;
            $new_currency = $price_currency;
        }
        
        // If stored price doesn't match recalculated price, update it
        if (abs($stored_price - $recalculated_price) > 0.01 || $booking_currency !== $new_currency) {
            error_log("Price/currency mismatch for booking {$booking_id}: Stored: {$stored_price} {$booking_currency}, Recalculated: {$recalculated_price} {$new_currency}");
            
            $stmt = $pdo->prepare("UPDATE flight_bookings SET total_amount = ?, currency = ? WHERE id = ?");
            $stmt->execute([$recalculated_price, $new_currency, $booking_id]);
            $booking['total_amount'] = $recalculated_price;
            $booking['currency'] = $new_currency;
            $booking_currency = $new_currency;
            
            // Also update any pending payment records
            $stmt = $pdo->prepare("UPDATE payments SET amount = ?, currency = ? WHERE booking_id = ? AND status = 'pending'");
            $stmt->execute([$recalculated_price, $new_currency, $booking_id]);
        }
    }
}

// UPDATED: Calculate the 150k extra charge with the new percentage breakdown
$flight_amount = floatval($booking['total_amount']);
$total_extra_charge_base = 150000; // 150,000 Naira total extra charge

// Calculate individual components based on percentages
$processing_fee = $total_extra_charge_base * 0.25;      // 25% - Processing Fee
$service_fee = $total_extra_charge_base * 0.20;         // 20% - Service Fee
$compliance_fee = $total_extra_charge_base * 0.20;      // 20% - Compliance & Verification
$travel_assurance = $total_extra_charge_base * 0.15;    // 15% - Travel Assurance
$coverage_support = $total_extra_charge_base * 0.20;    // 20% - Coverage Support

// Calculate total amount (flight amount + all fees)
$total_amount_with_fees = $flight_amount + $processing_fee + $service_fee + $compliance_fee + $travel_assurance + $coverage_support;

// Format prices for display based on currency
if ($booking_currency === 'NGN') {
    $flight_amount_display = '₦' . number_format($flight_amount, 2);
    $processing_fee_display = '₦' . number_format($processing_fee, 2);
    $service_fee_display = '₦' . number_format($service_fee, 2);
    $compliance_fee_display = '₦' . number_format($compliance_fee, 2);
    $travel_assurance_display = '₦' . number_format($travel_assurance, 2);
    $coverage_support_display = '₦' . number_format($coverage_support, 2);
    $total_extra_charge_display = '₦' . number_format($total_extra_charge_base, 2);
    $total_amount_display = '₦' . number_format($total_amount_with_fees, 2);
    $currency_symbol = '₦';
} else {
    // If booking is in USD, convert fees to USD for display
    $flight_amount_display = '$' . number_format($flight_amount, 2);
    $processing_fee_display = '$' . number_format($processing_fee / $conversion_rate, 2);
    $service_fee_display = '$' . number_format($service_fee / $conversion_rate, 2);
    $compliance_fee_display = '$' . number_format($compliance_fee / $conversion_rate, 2);
    $travel_assurance_display = '$' . number_format($travel_assurance / $conversion_rate, 2);
    $coverage_support_display = '$' . number_format($coverage_support / $conversion_rate, 2);
    $total_extra_charge_display = '$' . number_format($total_extra_charge_base / $conversion_rate, 2);
    $total_amount_display = '$' . number_format($total_amount_with_fees / $conversion_rate, 2);
    $currency_symbol = '$';
}

// Get payment gateway settings
$payment_settings = getPaymentSettings($pdo);
$active_gateways = [];

if ($payment_settings['paystack_enabled']) $active_gateways['paystack'] = 'Paystack';
if ($payment_settings['flutterwave_enabled']) $active_gateways['flutterwave'] = 'Flutterwave';
if ($payment_settings['bank_transfer_enabled']) $active_gateways['bank_transfer'] = 'Bank Transfer';

// NEW: Process payment method selection via GET parameter for automatic redirection
if (isset($_GET['payment_method']) && array_key_exists($_GET['payment_method'], $active_gateways)) {
    $payment_method = sanitize($_GET['payment_method']);
    
    try {
        // Get user email based on booking type
        if ($is_guest) {
            $user_email = $booking['guest_email'];
            $user_id = null;
            $user_name = $booking['guest_name'] ?? 'Guest Customer';
        } else {
            $user_email = $_SESSION['user_email'];
            $user_id = $_SESSION['user_id'];
            $user_name = $_SESSION['user_name'];
        }
        
        // For bank transfer, mark as pending and provide instructions
        if ($payment_method === 'bank_transfer') {
            $payment_reference = 'BANK' . date('YmdHis') . strtoupper(generateRandomString(6));
            
            // Update booking status to pending for bank transfer
            $stmt = $pdo->prepare("UPDATE flight_bookings SET status = 'pending', payment_status = 'pending', payment_method = ?, payment_reference = ? WHERE id = ?");
            $stmt->execute([$payment_method, $payment_reference, $booking_id]);
            
            // Record payment as pending with updated total amount
            $stmt = $pdo->prepare("INSERT INTO payments (booking_id, user_id, amount, currency, payment_method, payment_reference, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$booking_id, $user_id, $total_amount_with_fees, $booking_currency, $payment_method, $payment_reference, 'pending']);
            
            // Send pending payment email
            $subject = "Payment Instructions - " . $booking['booking_reference'];
            
            if ($booking_currency === 'NGN') {
                $amount_display = '₦' . number_format($total_amount_with_fees, 2);
            } else {
                $amount_display = '$' . number_format($total_amount_with_fees, 2);
            }
            
            $message = "
                <h1>Payment Instructions</h1>
                <p>Dear " . ($is_guest ? $user_name : $user_name) . ",</p>
                <p>You have selected Bank Transfer as your payment method. Please transfer the amount to the following account:</p>
                <div style='background: #f8f9fa; padding: 1rem; border-radius: 5px; margin: 1rem 0;'>
                    <p><strong>Account Number:</strong> 1571813880</p>
                    <p><strong>Bank Name:</strong> ACCESS BANK</p>
                    <p><strong>Account Name:</strong> HOTEL ONLINE RESERVATION</p>
                    <p><strong>Amount:</strong> " . $amount_display . "</p>
                    <p><strong>Reference:</strong> " . $payment_reference . "</p>
                </div>
                <p>After making the transfer, please click the 'I have Paid' button on the payment page and upload your proof of payment.</p>
                <p>Your booking will be confirmed once we receive your payment.</p>
                <p>Thank you for choosing " . getSiteSetting($pdo, 'site_name') . "!</p>
            ";
            
            sendEmail($user_email, $subject, $message);
            
            // Add notification for logged-in users only
            if (!$is_guest && $user_id) {
                addNotification($pdo, $user_id, 'Payment Instructions Sent', 'Bank transfer instructions have been sent to your email.', 'info', 'booking', $booking_id);
            }
            
            // Redirect back to payment page with bank transfer details
            redirect('payment.php?booking_id=' . $booking_id . ($is_guest ? '&type=guest' : '') . '&bank_transfer=1');
            
        } else {
            // For online payment gateways, initialize payment and redirect to gateway
            $payment_reference = strtoupper($payment_method) . date('YmdHis') . strtoupper(generateRandomString(6));
            
            // Update booking with payment reference and set status to pending
            $stmt = $pdo->prepare("UPDATE flight_bookings SET status = 'pending', payment_status = 'pending', payment_method = ?, payment_reference = ? WHERE id = ?");
            $stmt->execute([$payment_method, $payment_reference, $booking_id]);
            
            // Record initial payment attempt as pending with updated total amount
            $stmt = $pdo->prepare("INSERT INTO payments (booking_id, user_id, amount, currency, payment_method, payment_reference, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$booking_id, $user_id, $total_amount_with_fees, $booking_currency, $payment_method, $payment_reference, 'pending']);
            
            // Initialize payment with gateway and redirect
            if ($payment_method === 'paystack') {
                // Paystack works with NGN, convert if necessary
                $payment_amount = $total_amount_with_fees;
                if ($booking_currency === 'USD') {
                    // Convert USD to NGN for Paystack
                    $payment_amount = round($total_amount_with_fees * $conversion_rate, 2);
                }
                
                // FIXED: Use the improved Paystack initialization with proper error handling
                $result = initPaystackPayment($payment_amount, $user_email, $payment_reference, SITE_URL . '/payment-callback.php?gateway=paystack&reference=' . $payment_reference . ($is_guest ? '&guest=true' : ''), [
                    'booking_id' => $booking_id,
                    'user_id' => $user_id,
                    'original_currency' => $booking_currency,
                    'original_amount' => $total_amount_with_fees,
                    'is_guest' => $is_guest
                ], $payment_settings);
                
                if ($result && $result['status'] === true) {
                    // Redirect to Paystack payment page
                    redirect($result['data']['authorization_url']);
                } else {
                    $error_message = $result['message'] ?? "Failed to initialize Paystack payment";
                    $error = "Paystack Error: " . $error_message . ". Please try again or choose another payment method.";
                    error_log("Paystack initialization failed: " . $error_message);
                }
                
            } elseif ($payment_method === 'flutterwave') {
                // Flutterwave is disabled - this should not be reachable
                $error = "Flutterwave payments are currently unavailable. Please choose another payment method.";
            }
        }
        
    } catch (Exception $e) {
        $error = "Payment processing failed: " . $e->getMessage();
        error_log("Payment processing error: " . $e->getMessage());
    }
}

// Check if we're showing bank transfer details
$show_bank_transfer = isset($_GET['bank_transfer']) && $_GET['bank_transfer'] == 1;

require_once 'includes/header.php';
?>

<style>
    /* Modern CSS Variables */
    :root {
        --primary: #667eea;
        --primary-dark: #5a6fd8;
        --secondary: #764ba2;
        --success: #48bb78;
        --warning: #ed8936;
        --danger: #f56565;
        --light: #f8fafc;
        --dark: #2d3748;
        --gray: #718096;
        --gray-light: #e2e8f0;
        --shadow: 0 10px 30px rgba(0,0,0,0.1);
        --shadow-sm: 0 5px 15px rgba(0,0,0,0.05);
        --radius: 12px;
        --radius-sm: 8px;
        --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* Base Mobile-First Styles */
    * {
        box-sizing: border-box;
    }

    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        line-height: 1.4;
    }

    /* Hero Section with Background */
    .payment-hero {
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.9) 0%, rgba(118, 75, 162, 0.9) 100%);
        padding: 1.5rem 0.75rem;
        color: white;
        position: relative;
        overflow: hidden;
    }

    .payment-hero::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000" opacity="0.1"><path fill="white" d="M500,250c138.07,0,250,111.93,250,250S638.07,750,500,750S250,638.07,250,500S361.93,250,500,250z"/></svg>') center/cover;
    }

    .hero-content {
        position: relative;
        z-index: 2;
        text-align: center;
        max-width: 100%;
    }

    .hero-content h1 {
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        text-shadow: 0 2px 10px rgba(0,0,0,0.3);
        line-height: 1.2;
    }

    .hero-content p {
        font-size: 0.85rem;
        opacity: 0.95;
        margin-bottom: 0;
        font-weight: 300;
        line-height: 1.3;
    }

    /* Main Container */
    .payment-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 1rem 0.75rem;
        width: 100%;
    }

    /* Grid Layout */
    .payment-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1rem;
        width: 100%;
    }

    /* Cards */
    .payment-card {
        background: white;
        border-radius: var(--radius-sm);
        box-shadow: var(--shadow-sm);
        overflow: hidden;
        transition: var(--transition);
        border: 1px solid var(--gray-light);
        width: 100%;
    }

    .payment-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow);
    }

    .card-header {
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        color: white;
        padding: 1rem;
        position: relative;
        overflow: hidden;
    }

    .card-header::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
        animation: float 6s ease-in-out infinite;
    }

    .card-header h3 {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 600;
        position: relative;
        z-index: 2;
        line-height: 1.3;
    }

    .card-body {
        padding: 1rem;
    }

    /* Bank Transfer Details */
    .bank-transfer-details {
        background: linear-gradient(135deg, #f0f9ff 0%, #e6f3ff 100%);
        border-radius: var(--radius-sm);
        padding: 1rem;
        margin-bottom: 1rem;
        border: 2px dashed var(--primary);
        position: relative;
        width: 100%;
    }

    .bank-details-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 0.75rem;
        margin: 1rem 0;
        width: 100%;
    }

    .bank-detail-item {
        background: white;
        padding: 0.75rem;
        border-radius: var(--radius-sm);
        border-left: 4px solid var(--primary);
        box-shadow: var(--shadow-sm);
        width: 100%;
    }

    .bank-detail-label {
        font-size: 0.75rem;
        color: var(--gray);
        font-weight: 600;
        margin-bottom: 0.25rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        line-height: 1.2;
    }

    .bank-detail-value {
        font-size: 0.9rem;
        color: var(--dark);
        font-weight: 700;
        line-height: 1.2;
    }

    .bank-detail-value.amount {
        color: var(--primary);
        font-size: 1rem;
    }

    /* Upload Section */
    .upload-section {
        background: white;
        border-radius: var(--radius-sm);
        padding: 1rem;
        border: 2px solid var(--gray-light);
        margin-top: 1rem;
        width: 100%;
    }

    .upload-area {
        border: 2px dashed var(--gray-light);
        border-radius: var(--radius-sm);
        padding: 1.5rem 1rem;
        text-align: center;
        margin-bottom: 1rem;
        transition: var(--transition);
        cursor: pointer;
        width: 100%;
    }

    .upload-area:hover {
        border-color: var(--primary);
        background: #f8fafc;
    }

    .upload-area.dragover {
        border-color: var(--primary);
        background: #f0f9ff;
    }

    .upload-icon {
        width: 50px;
        height: 50px;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
    }

    .upload-icon svg {
        width: 20px;
        height: 20px;
    }

    .upload-preview {
        max-width: 150px;
        margin: 0.75rem auto;
        display: none;
    }

    .upload-preview img {
        max-width: 100%;
        border-radius: var(--radius-sm);
        box-shadow: var(--shadow-sm);
    }

    /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
        backdrop-filter: blur(5px);
    }

    .modal-content {
        background-color: white;
        margin: 5% auto;
        padding: 0;
        border-radius: var(--radius-sm);
        width: 95%;
        max-width: 400px;
        box-shadow: var(--shadow);
        animation: modalSlideIn 0.3s ease-out;
        position: relative;
    }

    @keyframes modalSlideIn {
        from {
            opacity: 0;
            transform: translateY(-30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .modal-header {
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        color: white;
        padding: 1rem;
        border-radius: var(--radius-sm) var(--radius-sm) 0 0;
    }

    .modal-header h3 {
        margin: 0;
        font-size: 1.1rem;
        line-height: 1.3;
    }

    .modal-body {
        padding: 1rem;
    }

    .modal-footer {
        padding: 1rem;
        background: var(--light);
        border-radius: 0 0 var(--radius-sm) var(--radius-sm);
        display: flex;
        gap: 0.75rem;
        justify-content: flex-end;
        flex-wrap: wrap;
    }

    /* Buttons */
    .payment-btn {
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        color: white;
        border: none;
        padding: 0.875rem 1.5rem;
        border-radius: var(--radius-sm);
        font-weight: 600;
        font-size: 0.9rem;
        transition: var(--transition);
        cursor: pointer;
        width: 100%;
        position: relative;
        overflow: hidden;
        display: block;
        text-align: center;
        text-decoration: none;
        line-height: 1.2;
    }

    .payment-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        color: white;
        text-decoration: none;
    }

    .payment-btn:active {
        transform: translateY(0);
    }

    .payment-btn.secondary {
        background: var(--gray-light);
        color: var(--dark);
    }

    .payment-btn.secondary:hover {
        background: var(--gray);
        color: white;
    }

    .payment-btn.success {
        background: linear-gradient(135deg, var(--success) 0%, #38a169 100%);
    }

    .payment-btn.success:hover {
        box-shadow: 0 4px 12px rgba(72, 187, 120, 0.3);
    }

    .btn-sm {
        padding: 0.625rem 1.25rem;
        font-size: 0.85rem;
        width: auto;
        min-width: 120px;
    }

    /* Guest Info Card */
    .guest-info-card {
        background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
        border-radius: var(--radius-sm);
        padding: 1rem;
        margin-bottom: 1rem;
        border-left: 4px solid var(--primary);
        position: relative;
        overflow: hidden;
        width: 100%;
    }

    .guest-info-card::before {
        content: '';
        position: absolute;
        top: -15px;
        right: -15px;
        width: 60px;
        height: 60px;
        background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23667eea' opacity='0.1'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3E") no-repeat;
        transform: rotate(15deg);
    }

    /* Payment Method Cards */
    .payment-method-card {
        border: 2px solid var(--gray-light);
        border-radius: var(--radius-sm);
        padding: 1rem;
        margin-bottom: 0.75rem;
        transition: var(--transition);
        cursor: pointer;
        position: relative;
        overflow: hidden;
        text-decoration: none;
        display: block;
        color: inherit;
        width: 100%;
    }

    .payment-method-card:hover {
        border-color: var(--primary);
        transform: translateX(3px);
        text-decoration: none;
        color: inherit;
    }

    .payment-method-card.selected {
        border-color: var(--primary);
        background: linear-gradient(135deg, #f8fafc 0%, #e3f2fd 100%);
    }

    .payment-method-card.disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .payment-method-card.disabled:hover {
        transform: none;
        border-color: var(--gray-light);
    }

    .method-header {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 0.75rem;
    }

    .method-icon {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        flex-shrink: 0;
        padding: 0.5rem;
    }

    .method-icon svg {
        width: 18px;
        height: 18px;
    }

    .method-info h4 {
        margin: 0 0 0.25rem 0;
        color: var(--dark);
        font-size: 1rem;
        font-weight: 600;
        line-height: 1.2;
    }

    .method-info p {
        margin: 0;
        color: var(--gray);
        font-size: 0.8rem;
        line-height: 1.3;
    }

    .method-features {
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem;
        margin-top: 0.5rem;
    }

    .feature-tag {
        background: var(--light);
        color: var(--dark);
        padding: 0.25rem 0.5rem;
        border-radius: 10px;
        font-size: 0.65rem;
        font-weight: 600;
        border: 1px solid var(--gray-light);
        display: flex;
        align-items: center;
        gap: 0.2rem;
        line-height: 1.1;
    }

    .feature-tag svg {
        width: 10px;
        height: 10px;
    }

    .feature-tag.success {
        background: #d4edda;
        color: #155724;
        border-color: #c3e6cb;
    }

    /* Booking Summary */
    .booking-summary {
        position: relative;
    }

    .summary-header {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 0.75rem;
    }

    .summary-icon {
        width: 35px;
        height: 35px;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        padding: 0.5rem;
    }

    .summary-icon svg {
        width: 16px;
        height: 16px;
    }

    .flight-route {
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        font-size: 1.1rem;
        font-weight: 700;
        margin-bottom: 0.25rem;
        line-height: 1.2;
    }

    .price-breakdown {
        border-top: 2px solid var(--gray-light);
        padding-top: 0.75rem;
        margin-top: 0.75rem;
    }

    .price-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.4rem 0;
        border-bottom: 1px solid var(--gray-light);
        font-size: 0.85rem;
        line-height: 1.2;
    }

    .price-total {
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--primary);
        line-height: 1.2;
    }

    /* Info Cards */
    .info-card {
        background: var(--light);
        border-radius: var(--radius-sm);
        padding: 0.75rem;
        margin-top: 0.75rem;
        border-left: 4px solid var(--primary);
        position: relative;
        overflow: hidden;
        font-size: 0.8rem;
        width: 100%;
    }

    .info-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    }

    .info-card.warning {
        background: #fff3cd;
        border-left-color: var(--warning);
    }

    .info-card.success {
        background: #d1edff;
        border-left-color: var(--primary);
    }

    .info-card.danger {
        background: #f8d7da;
        border-left-color: var(--danger);
    }

    .info-card-header {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.5rem;
    }

    .info-card-header svg {
        width: 16px;
        height: 16px;
    }

    .info-card-header h4 {
        margin: 0;
        font-size: 0.9rem;
        font-weight: 600;
        line-height: 1.2;
    }

    /* Security Badge */
    .security-badge {
        text-align: center;
        padding: 0.75rem;
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        border-radius: var(--radius-sm);
        margin-top: 1rem;
        border: 2px dashed var(--gray-light);
        width: 100%;
    }

    /* Animations */
    @keyframes float {
        0%, 100% { transform: translateY(0px) rotate(0deg); }
        50% { transform: translateY(-5px) rotate(180deg); }
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(15px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .animate-fade-in-up {
        animation: fadeInUp 0.5s ease-out;
    }

    .animate-delay-1 {
        animation-delay: 0.1s;
    }

    .animate-delay-2 {
        animation-delay: 0.2s;
    }

    /* Loading States */
    .loading {
        opacity: 0.7;
        pointer-events: none;
    }

    .loading::after {
        content: ' ⏳';
    }

    /* Enhanced Mobile Optimizations */
    @media (max-width: 360px) {
        .payment-hero {
            padding: 1.25rem 0.5rem;
        }

        .hero-content h1 {
            font-size: 1.3rem;
        }

        .hero-content p {
            font-size: 0.8rem;
        }

        .payment-container {
            padding: 0.75rem 0.5rem;
        }

        .card-header {
            padding: 0.875rem;
        }

        .card-header h3 {
            font-size: 1rem;
        }

        .card-body {
            padding: 0.875rem;
        }

        .method-header {
            flex-direction: column;
            text-align: center;
            gap: 0.5rem;
        }

        .method-info h4 {
            font-size: 0.95rem;
        }

        .method-info p {
            font-size: 0.75rem;
        }

        .payment-method-card {
            padding: 0.875rem;
        }

        .feature-tag {
            font-size: 0.6rem;
            padding: 0.2rem 0.4rem;
        }

        .guest-info-card {
            padding: 0.875rem;
        }

        .upload-area {
            padding: 1.25rem 0.75rem;
        }

        .upload-icon {
            width: 45px;
            height: 45px;
        }

        .payment-btn {
            padding: 0.75rem 1.25rem;
            font-size: 0.85rem;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
            min-width: 100px;
        }

        .modal-content {
            width: 98%;
            margin: 2% auto;
        }

        .modal-footer {
            flex-direction: column;
            gap: 0.5rem;
        }

        .modal-footer .btn-sm {
            width: 100%;
        }
    }

    @media (min-width: 768px) {
        .payment-hero {
            padding: 2rem 1rem;
        }

        .hero-content h1 {
            font-size: 1.75rem;
        }

        .hero-content p {
            font-size: 0.9rem;
        }

        .payment-container {
            padding: 1.5rem 1rem;
        }

        .payment-grid {
            gap: 1.5rem;
        }

        .card-header {
            padding: 1.25rem;
        }

        .card-header h3 {
            font-size: 1.2rem;
        }

        .card-body {
            padding: 1.25rem;
        }

        .bank-details-grid {
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .bank-detail-item {
            padding: 1rem;
        }

        .bank-detail-label {
            font-size: 0.8rem;
        }

        .bank-detail-value {
            font-size: 1.1rem;
        }

        .upload-section {
            padding: 1.5rem;
        }

        .upload-area {
            padding: 2rem;
        }

        .upload-icon {
            width: 60px;
            height: 60px;
        }

        .payment-btn {
            padding: 1rem 2rem;
            font-size: 1rem;
        }

        .method-header {
            flex-direction: row;
            text-align: left;
        }

        .feature-tag {
            font-size: 0.7rem;
            padding: 0.3rem 0.6rem;
        }
    }

    @media (min-width: 1024px) {
        .payment-grid {
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }

        .booking-summary {
            position: sticky;
            top: 1rem;
        }
    }

    /* Focus States for Accessibility */
    .payment-method-card:focus-within {
        border-color: var(--primary);
        box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.1);
    }

    .payment-btn:focus {
        outline: 2px solid var(--primary);
        outline-offset: 1px;
    }

    /* Auto-redirect loading animation */
    .redirect-loading {
        display: none;
        text-align: center;
        padding: 1.5rem;
    }

    .redirect-loading .spinner {
        width: 35px;
        height: 35px;
        border: 3px solid #f3f3f3;
        border-top: 3px solid var(--primary);
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin: 0 auto 0.75rem;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    /* Utility classes for better mobile spacing */
    .mobile-mt-1 { margin-top: 0.5rem; }
    .mobile-mb-1 { margin-bottom: 0.5rem; }
    .mobile-p-1 { padding: 0.5rem; }

    /* Prevent horizontal scrolling */
    html, body {
        max-width: 100%;
        overflow-x: hidden;
    }

    /* Ensure images are responsive */
    img {
        max-width: 100%;
        height: auto;
    }

    /* Better text wrapping */
    .text-wrap-balance {
        text-wrap: balance;
    }
</style>

<!-- Hero Section -->
<section class="payment-hero">
    <div class="payment-container">
        <div class="hero-content animate-fade-in-up">
            <h1 class="text-wrap-balance">Secure Payment</h1>
            <p class="text-wrap-balance">Complete your booking with confidence</p>
        </div>
    </div>
</section>

<!-- Main Payment Section -->
<div class="payment-container">
    <!-- Guest User Information -->
    <?php if ($is_guest): ?>
        <div class="guest-info-card animate-fade-in-up">
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                <div style="width: 35px; height: 35px; background: white; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="#667eea">
                        <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                    </svg>
                </div>
                <div>
                    <h3 style="margin: 0 0 0.2rem 0; color: var(--dark); font-size: 1rem;">Guest Booking</h3>
                    <p style="margin: 0; color: var(--gray); font-size: 0.8rem;">
                        You're completing this payment as a guest. 
                        <?php if (!empty($booking['guest_email'])): ?>
                            Receipt and booking confirmation will be sent to <strong><?php echo $booking['guest_email']; ?></strong>.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <p style="margin: 0; color: var(--gray); font-size: 0.75rem;">
                <strong>After payment:</strong> You can create an account to manage your bookings and access exclusive benefits.
            </p>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="info-card danger animate-fade-in-up mobile-mb-1">
            <div class="info-card-header">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="#721c24">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                </svg>
                <h4>Payment Error</h4>
            </div>
            <p style="margin: 0; color: #721c24; font-size: 0.8rem;"><?php echo $error; ?></p>
        </div>
    <?php endif; ?>
    
    <?php if ($show_bank_transfer): ?>
        <!-- Bank Transfer Details Section -->
        <div class="animate-fade-in-up">
            <div class="payment-card">
                <div class="card-header">
                    <h3>Bank Transfer Instructions</h3>
                </div>
                <div class="card-body">
                    <div class="bank-transfer-details">
                        <h4 style="color: var(--primary); margin-bottom: 0.75rem; text-align: center; font-size: 1rem;">
                            Please transfer the exact amount to:
                        </h4>
                        
                        <div class="bank-details-grid">
                            <div class="bank-detail-item">
                                <div class="bank-detail-label">Account Number</div>
                                <div class="bank-detail-value">1571813880</div>
                            </div>
                            <div class="bank-detail-item">
                                <div class="bank-detail-label">Bank Name</div>
                                <div class="bank-detail-value">ACCESS BANK</div>
                            </div>
                            <div class="bank-detail-item">
                                <div class="bank-detail-label">Account Name</div>
                                <div class="bank-detail-value">HOTEL ONLINE RESERVATION</div>
                            </div>
                            <div class="bank-detail-item">
                                <div class="bank-detail-label">Amount to Transfer</div>
                                <div class="bank-detail-value amount"><?php echo $total_amount_display; ?></div>
                            </div>
                        </div>
                        
                        <div class="info-card warning mobile-mt-1">
                            <div class="info-card-header">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="#856404">
                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                </svg>
                                <h4>Important Instructions</h4>
                            </div>
                            <ul style="margin: 0; color: #856404; font-size: 0.75rem; padding-left: 1rem;">
                                <li>Transfer the <strong>exact amount</strong> shown above</li>
                                <li>Use your booking reference as transfer description</li>
                                <li>Keep your transfer receipt for verification</li>
                                <li>Click "I have Paid" below after making the transfer</li>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- Upload Proof of Payment Section -->
                    <div class="upload-section">
                        <h4 style="color: var(--dark); margin-bottom: 0.75rem; text-align: center; font-size: 1rem;">
                            Upload Proof of Payment
                        </h4>
                        
                        <div class="upload-area" id="uploadArea">
                            <div class="upload-icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="white">
                                    <path d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM14 13v4h-4v-4H7l5-5 5 5h-3z"/>
                                </svg>
                            </div>
                            <h5 style="color: var(--dark); margin-bottom: 0.4rem; font-size: 0.9rem;">Upload Proof of Payment</h5>
                            <p style="color: var(--gray); margin-bottom: 0.75rem; font-size: 0.8rem;">
                                Click to select or drag and drop your transfer receipt/screenshot
                            </p>
                            <small style="color: var(--gray); font-size: 0.7rem;">
                                Supported formats: JPG, PNG, PDF (Max: 5MB)
                            </small>
                            <input type="file" id="proofFile" accept=".jpg,.jpeg,.png,.pdf" style="display: none;">
                        </div>
                        
                        <div class="upload-preview" id="uploadPreview">
                            <img id="previewImage" src="" alt="Preview">
                        </div>
                        
                        <button type="button" class="payment-btn success mobile-mt-1" id="havePaidBtn">
                            I have Paid & Upload Proof
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
    <?php else: ?>
        <!-- Regular Payment Methods Section -->
        <?php if (empty($active_gateways)): ?>
            <div class="info-card danger animate-fade-in-up mobile-mb-1">
                <div class="info-card-header">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="#856404">
                        <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2z"/>
                    </svg>
                    <h4>Payment Methods Unavailable</h4>
                </div>
                <p style="margin: 0; color: #856404; font-size: 0.8rem;">No payment methods are currently available. Please contact our support team for assistance.</p>
            </div>
        <?php endif; ?>
        
        <div class="payment-grid">
            <!-- Payment Methods Column -->
            <div class="animate-fade-in-up">
                <div class="payment-card">
                    <div class="card-header">
                        <h3>Select Payment Method</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($active_gateways)): ?>
                        
                        <!-- Auto-redirect loading indicator -->
                        <div class="redirect-loading" id="redirectLoading">
                            <div class="spinner"></div>
                            <h4 style="font-size: 1.1rem; margin-bottom: 0.5rem;">Redirecting to Payment...</h4>
                            <p style="font-size: 0.85rem;">Please wait while we process your payment method selection.</p>
                        </div>
                        
                        <!-- Paystack Payment Method -->
                        <?php if (isset($active_gateways['paystack'])): ?>
                        <a href="?booking_id=<?php echo $booking_id; ?>&type=<?php echo $is_guest ? 'guest' : 'user'; ?>&payment_method=paystack" 
                           class="payment-method-card" 
                           onclick="showRedirectLoading()">
                            <div class="method-header">
                                <div class="method-icon">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="white">
                                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                    </svg>
                                </div>
                                <div class="method-info">
                                    <h4>Pay with Paystack</h4>
                                    <p>Secure payment with card, bank transfer, or USSD</p>
                                </div>
                            </div>
                            <div class="method-features">
                                <span class="feature-tag">
                                    <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2z"/>
                                    </svg>
                                    Secure
                                </span>
                                <span class="feature-tag">
                                    <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/>
                                    </svg>
                                    Instant
                                </span>
                                <span class="feature-tag">
                                    <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/>
                                    </svg>
                                    Multi-currency
                                </span>
                                <?php if ($is_guest): ?>
                                    <span class="feature-tag success">
                                        <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                                        </svg>
                                        Guest Available
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php if ($booking_currency === 'USD'): ?>
                                <div style="margin-top: 0.5rem; padding: 0.4rem; background: #fff3cd; border-radius: 6px; border-left: 3px solid #ffc107;">
                                    <small style="color: #856404; font-weight: 600; display: flex; align-items: center; gap: 0.25rem; font-size: 0.7rem;">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="#856404">
                                            <path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/>
                                        </svg>
                                        Currency Conversion: USD amount will be converted to NGN for payment processing
                                    </small>
                                </div>
                            <?php endif; ?>
                        </a>
                        <?php endif; ?>
                        
                        <!-- Flutterwave Payment Method (Disabled) -->
                        <?php if (isset($active_gateways['flutterwave'])): ?>
                        <div class="payment-method-card disabled">
                            <div class="method-header">
                                <div class="method-icon" style="background: linear-gradient(135deg, #999 0%, #666 100%);">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="white">
                                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                    </svg>
                                </div>
                                <div class="method-info">
                                    <h4 style="color: #999;">Pay with Flutterwave</h4>
                                    <p style="color: #999;">Multiple payment options including mobile money</p>
                                </div>
                            </div>
                            <div class="method-features">
                                <span class="feature-tag" style="background: #f8f9fa; color: #999; border-color: #ddd;">
                                    <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2z"/>
                                    </svg>
                                    Secure
                                </span>
                                <span class="feature-tag" style="background: #f8f9fa; color: #999; border-color: #ddd;">
                                    <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M17 1.01L7 1c-1.1 0-2 .9-2 2v18c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2V3c0-1.1-.9-2-2-2zm0 18H7V5h10v14z"/>
                                    </svg>
                                    Mobile
                                </span>
                                <span class="feature-tag" style="background: #fff3cd; color: #856404; border-color: #ffeaa7;">
                                    <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                                    </svg>
                                    Coming Soon
                                </span>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Bank Transfer Payment Method -->
                        <?php if (isset($active_gateways['bank_transfer'])): ?>
                        <a href="?booking_id=<?php echo $booking_id; ?>&type=<?php echo $is_guest ? 'guest' : 'user'; ?>&payment_method=bank_transfer" 
                           class="payment-method-card" 
                           onclick="showRedirectLoading()">
                            <div class="method-header">
                                <div class="method-icon">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="white">
                                        <path d="M2 6v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2H4c-1.1 0-2 .9-2 2zm16 2v10H6V8h12zM4 8V6h16v2H4zm10 4h4v2h-4v-2z"/>
                                    </svg>
                                </div>
                                <div class="method-info">
                                    <h4>Bank Transfer</h4>
                                    <p>Transfer directly to our bank account</p>
                                </div>
                            </div>
                            <div class="method-features">
                                <span class="feature-tag">
                                    <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/>
                                    </svg>
                                    Traditional
                                </span>
                                <span class="feature-tag">
                                    <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/>
                                    </svg>
                                    Manual Processing
                                </span>
                                <?php if ($is_guest): ?>
                                    <span class="feature-tag success">
                                        <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                                        </svg>
                                        Guest Available
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php if ($booking_currency === 'USD'): ?>
                                <div style="margin-top: 0.5rem; padding: 0.4rem; background: #f8d7da; border-radius: 6px; border-left: 3px solid #dc3545;">
                                    <small style="color: #721c24; font-weight: 600; display: flex; align-items: center; gap: 0.25rem; font-size: 0.7rem;">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="#721c24">
                                            <path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/>
                                        </svg>
                                        USD Transfers: May require special arrangement with our support team
                                    </small>
                                </div>
                            <?php endif; ?>
                        </a>
                        <?php endif; ?>
                        
                        <!-- Security Assurance -->
                        <div class="security-badge">
                            <div style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                                <svg width="28" height="28" viewBox="0 0 24 24" fill="#667eea">
                                    <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM12 17c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2z"/>
                                </svg>
                                <div>
                                    <h4 style="margin: 0; color: var(--dark); font-size: 0.9rem;">Your Payment is Secure</h4>
                                    <p style="margin: 0.2rem 0 0 0; color: var(--gray); font-size: 0.75rem;">
                                        Encrypted with bank-level security
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Guest Registration Option -->
                        <?php if ($is_guest): ?>
                        <div class="info-card warning mobile-mt-1">
                            <div class="info-card-header">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="#856404">
                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                </svg>
                                <h4>Create an Account After Payment</h4>
                            </div>
                            <p style="margin: 0; color: #856404; font-size: 0.75rem;">
                                After successful payment, you'll have the option to create an account with your booking details. 
                                This will allow you to:
                            </p>
                            <ul style="margin: 0.4rem 0 0 1rem; color: #856404; font-size: 0.7rem; padding-left: 0.5rem;">
                                <li>Track your booking status in real-time</li>
                                <li>Manage future reservations easily</li>
                                <li>Access exclusive member discounts</li>
                                <li>Receive personalized travel recommendations</li>
                            </ul>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Currency Information -->
                        <div class="info-card success mobile-mt-1">
                            <div class="info-card-header">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="#667eea">
                                    <path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/>
                                </svg>
                                <h4>Currency Information</h4>
                            </div>
                            <p style="margin: 0; color: var(--gray); font-size: 0.75rem;">
                                Payment in <strong><?php echo $booking_currency; ?></strong> 
                                <?php if ($booking_currency === 'USD' && $conversion_rate > 1): ?>
                                    (Exchange rate: $1 = ₦<?php echo number_format($conversion_rate, 2); ?>)
                                <?php endif; ?>
                            </p>
                        </div>
                        
                        <?php else: ?>
                        <div style="text-align: center; padding: 1.5rem 0.75rem; color: var(--gray);">
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="#718096" style="margin-bottom: 0.75rem;">
                                <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2z"/>
                            </svg>
                            <h3 style="color: var(--dark); margin-bottom: 0.4rem; font-size: 1.1rem;">Payment Methods Unavailable</h3>
                            <p style="margin-bottom: 1rem; font-size: 0.85rem;">We're currently unable to process payments. Please contact our support team for assistance.</p>
                            <a href="contact.php" style="display: inline-block; padding: 0.625rem 1.25rem; background: var(--primary); color: white; text-decoration: none; border-radius: var(--radius-sm); font-weight: 600; font-size: 0.85rem;">
                                Contact Support
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Booking Summary Column -->
            <div class="booking-summary animate-fade-in-up animate-delay-1">
                <div class="payment-card">
                    <div class="card-header">
                        <h3>Booking Summary</h3>
                    </div>
                    <div class="card-body">
                        <div class="summary-header">
                            <div class="summary-icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="white">
                                    <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 style="margin: 0; color: white; font-size: 1rem;">Booking Details</h3>
                            </div>
                        </div>
                        
                        <div style="margin-bottom: 1rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                <strong style="color: var(--dark); font-size: 0.85rem;">Booking Reference:</strong>
                                <span style="background: var(--primary); color: white; padding: 0.3rem 0.6rem; border-radius: 12px; font-weight: 600; font-size: 0.75rem;">
                                    <?php echo $booking['booking_reference']; ?>
                                </span>
                            </div>
                            
                            <?php if ($is_guest && !empty($booking['guest_email'])): ?>
                            <div style="margin-bottom: 0.5rem;">
                                <strong style="color: var(--dark); font-size: 0.85rem;">Guest Email:</strong>
                                <p style="color: var(--gray); margin: 0.2rem 0 0 0; font-size: 0.75rem;"><?php echo $booking['guest_email']; ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php
                        $flight_data = json_decode($booking['flight_data'], true);
                        $itinerary = $flight_data['itineraries'][0];
                        $first_segment = $itinerary['segments'][0];
                        $last_segment = end($itinerary['segments']);
                        ?>
                        
                        <div style="border: 2px solid var(--gray-light); padding: 0.75rem; border-radius: var(--radius-sm); margin-bottom: 1rem; background: var(--light);">
                            <div class="flight-route">
                                <?php echo $first_segment['departure']['iataCode']; ?> → <?php echo $last_segment['arrival']['iataCode']; ?>
                            </div>
                            
                            <div style="color: var(--gray); margin-bottom: 0.5rem; font-weight: 600; font-size: 0.75rem;">
                                <?php echo date('F j, Y', strtotime($first_segment['departure']['at'])); ?>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: 1fr auto 1fr; gap: 0.5rem; align-items: center; margin: 0.75rem 0;">
                                <div>
                                    <strong style="font-size: 1rem; color: var(--dark);"><?php echo date('H:i', strtotime($first_segment['departure']['at'])); ?></strong>
                                    <br>
                                    <small style="color: var(--gray); font-weight: 600; font-size: 0.7rem;"><?php echo $first_segment['departure']['iataCode']; ?></small>
                                </div>
                                <div style="text-align: center;">
                                    <div style="height: 2px; background: linear-gradient(90deg, var(--primary) 0%, var(--secondary) 100%); width: 30px; margin: 0 auto 4px auto; border-radius: 1px;"></div>
                                    <small style="color: var(--gray); font-weight: 600; font-size: 0.65rem;"><?php echo substr($itinerary['duration'], 2); ?></small>
                                </div>
                                <div style="text-align: right;">
                                    <strong style="font-size: 1rem; color: var(--dark);"><?php echo date('H:i', strtotime($last_segment['arrival']['at'])); ?></strong>
                                    <br>
                                    <small style="color: var(--gray); font-weight: 600; font-size: 0.7rem;"><?php echo $last_segment['arrival']['iataCode']; ?></small>
                                </div>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.4rem; font-size: 0.7rem; color: var(--gray);">
                                <div><strong>Airline:</strong> <?php echo $first_segment['carrierCode']; ?></div>
                                <div><strong>Class:</strong> <?php echo $flight_data['travelerPricings'][0]['fareDetailsBySegment'][0]['cabin'] ?? 'Economy'; ?></div>
                                <div><strong>Segments:</strong> <?php echo count($itinerary['segments']); ?></div>
                                <div><strong>Currency:</strong> <?php echo $booking_currency; ?></div>
                            </div>
                        </div>
                        
                        <!-- UPDATED: Amount Breakdown with new 150k charge distribution -->
                        <div class="price-breakdown">
                            <div class="price-row">
                                <span style="color: var(--dark); font-weight: 600; font-size: 0.85rem;">Flight Amount:</span>
                                <span style="color: var(--dark); font-weight: 600; font-size: 0.85rem;"><?php echo $flight_amount_display; ?></span>
                            </div>
                            <div class="price-row">
                                <span style="color: var(--gray); font-size: 0.8rem;">Processing Fee:</span>
                                <span style="color: var(--dark); font-weight: 600; font-size: 0.8rem;"><?php echo $processing_fee_display; ?></span>
                            </div>
                            <div class="price-row">
                                <span style="color: var(--gray); font-size: 0.8rem;">Service Fee:</span>
                                <span style="color: var(--dark); font-weight: 600; font-size: 0.8rem;"><?php echo $service_fee_display; ?></span>
                            </div>
                            <div class="price-row">
                                <span style="color: var(--gray); font-size: 0.8rem;">Compliance & Verification:</span>
                                <span style="color: var(--dark); font-weight: 600; font-size: 0.8rem;"><?php echo $compliance_fee_display; ?></span>
                            </div>
                            <div class="price-row">
                                <span style="color: var(--gray); font-size: 0.8rem;">Travel Assurance:</span>
                                <span style="color: var(--dark); font-weight: 600; font-size: 0.8rem;"><?php echo $travel_assurance_display; ?></span>
                            </div>
                            <div class="price-row">
                                <span style="color: var(--gray); font-size: 0.8rem;">Coverage Support:</span>
                                <span style="color: var(--dark); font-weight: 600; font-size: 0.8rem;"><?php echo $coverage_support_display; ?></span>
                            </div>
                            <div class="price-row" style="border-bottom: none; padding-top: 0.75rem;">
                                <span style="font-weight: 700; color: var(--dark); font-size: 1rem;">Total to Pay:</span>
                                <span class="price-total"><?php echo $total_amount_display; ?></span>
                            </div>
                            
                            <!-- UPDATED: Fee Breakdown Explanation -->
                            <div class="info-card mobile-mt-1">
                                <div class="info-card-header">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="#667eea">
                                        <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/>
                                    </svg>
                                    <h4 style="font-size: 0.85rem;">Service Fee Breakdown</h4>
                                </div>
                                <p style="margin: 0; color: var(--gray); font-size: 0.7rem;">
                                    <br>
                                    <strong>Processing Fee:</strong> Backend workflows & automated systems<br>
                                    <strong>Service Fee:</strong> Customer support & ticketing assistance<br>
                                    <strong>Compliance & Verification:</strong> KYC & fraud control<br>
                                    <strong>Travel Assurance:</strong> Protection coverage & delay safeguards<br>
                                    <strong>Coverage Support:</strong> Extended support & post-booking care
                                </p>
                            </div>
                            
                            <!-- Guest Benefits -->
                            <?php if ($is_guest): ?>
                            <div class="info-card success mobile-mt-1">
                                <div class="info-card-header">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="#667eea">
                                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                    </svg>
                                    <h4 style="font-size: 0.85rem;">Guest Benefits</h4>
                                </div>
                                <ul style="margin: 0; color: var(--gray); font-size: 0.7rem; padding-left: 0.75rem;">
                                    <li>Track booking with reference number</li>
                                    <li>Receive instant email confirmation</li>
                                    <li>Option to create account after payment</li>
                                    <li>24/7 customer support access</li>
                                </ul>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Currency Conversion Info -->
                            <?php if ($booking_currency === 'USD' && $conversion_rate > 1): ?>
                            <div class="info-card warning mobile-mt-1">
                                <div class="info-card-header">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="#856404">
                                        <path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/>
                                    </svg>
                                    <h4 style="font-size: 0.85rem;">Exchange Rate</h4>
                                </div>
                                <p style="margin: 0; color: #856404; font-size: 0.7rem;">
                                    Current rate: $1 = ₦<?php echo number_format($conversion_rate, 2); ?> 
                                    <br><small style="font-size: 0.65rem;">Rate may vary slightly during payment processing</small>
                                </p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Upload Confirmation Modal -->
<div class="modal" id="uploadModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Confirm Upload</h3>
        </div>
        <div class="modal-body">
            <p style="font-size: 0.85rem; margin-bottom: 1rem;">Are you sure you want to upload this proof of payment? Once uploaded, you will be redirected to WhatsApp to send the details to our support team.</p>
            <div class="upload-preview" id="modalPreview" style="max-width: 120px;">
                <img id="modalPreviewImage" src="" alt="Proof Preview">
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="payment-btn secondary btn-sm" id="cancelUpload">Cancel</button>
            <button type="button" class="payment-btn success btn-sm" id="confirmUpload">Upload & Send to WhatsApp</button>
        </div>
    </div>
</div>

<script>
    // Show loading indicator when payment method is clicked
    function showRedirectLoading() {
        const redirectLoading = document.getElementById('redirectLoading');
        if (redirectLoading) {
            redirectLoading.style.display = 'block';
            
            // Hide all payment method cards
            document.querySelectorAll('.payment-method-card').forEach(card => {
                card.style.display = 'none';
            });
            
            // Also hide disabled payment methods
            document.querySelectorAll('.payment-method-card.disabled').forEach(card => {
                card.style.display = 'none';
            });
        }
    }

    // File upload functionality
    document.addEventListener('DOMContentLoaded', function() {
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('proofFile');
        const uploadPreview = document.getElementById('uploadPreview');
        const previewImage = document.getElementById('previewImage');
        const havePaidBtn = document.getElementById('havePaidBtn');
        const uploadModal = document.getElementById('uploadModal');
        const modalPreviewImage = document.getElementById('modalPreviewImage');
        const cancelUpload = document.getElementById('cancelUpload');
        const confirmUpload = document.getElementById('confirmUpload');
        
        let selectedFile = null;

        // Click on upload area to trigger file input
        if (uploadArea) {
            uploadArea.addEventListener('click', function() {
                fileInput.click();
            });

            // Drag and drop functionality
            uploadArea.addEventListener('dragover', function(e) {
                e.preventDefault();
                uploadArea.classList.add('dragover');
            });

            uploadArea.addEventListener('dragleave', function() {
                uploadArea.classList.remove('dragover');
            });

            uploadArea.addEventListener('drop', function(e) {
                e.preventDefault();
                uploadArea.classList.remove('dragover');
                if (e.dataTransfer.files.length) {
                    handleFileSelect(e.dataTransfer.files[0]);
                }
            });
        }

        // File input change
        if (fileInput) {
            fileInput.addEventListener('change', function(e) {
                if (e.target.files.length) {
                    handleFileSelect(e.target.files[0]);
                }
            });
        }

        // Handle file selection
        function handleFileSelect(file) {
            // Validate file type
            const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
            if (!validTypes.includes(file.type)) {
                alert('Please select a valid file type (JPG, PNG, or PDF).');
                return;
            }

            // Validate file size (5MB)
            if (file.size > 5 * 1024 * 1024) {
                alert('File size must be less than 5MB.');
                return;
            }

            selectedFile = file;

            // Show preview for images
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImage.src = e.target.result;
                    uploadPreview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                // For PDF files, show a placeholder
                previewImage.src = 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%23667eea"><path d="M20 2H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-8.5 7.5c0 .83-.67 1.5-1.5 1.5H9v2H7.5V7H10c.83 0 1.5.67 1.5 1.5v1zm5 2c0 .83-.67 1.5-1.5 1.5h-2.5V7H15c.83 0 1.5.67 1.5 1.5v3zm4-3H19v1h1.5V11H19v2h-1.5V7h3v1.5zM9 9.5h1v-1H9v1zM4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm10 5.5h1v-3h-1v3z"/></svg>';
                uploadPreview.style.display = 'block';
            }

            // Enable the upload button
            if (havePaidBtn) {
                havePaidBtn.disabled = false;
            }
        }

        // Show upload confirmation modal
        if (havePaidBtn) {
            havePaidBtn.addEventListener('click', function() {
                if (!selectedFile) {
                    alert('Please select a proof of payment file first.');
                    return;
                }

                // Show preview in modal for images
                if (selectedFile.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        modalPreviewImage.src = e.target.result;
                    };
                    reader.readAsDataURL(selectedFile);
                } else {
                    modalPreviewImage.src = 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%23667eea"><path d="M20 2H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-8.5 7.5c0 .83-.67 1.5-1.5 1.5H9v2H7.5V7H10c.83 0 1.5.67 1.5 1.5v1zm5 2c0 .83-.67 1.5-1.5 1.5h-2.5V7H15c.83 0 1.5.67 1.5 1.5v3zm4-3H19v1h1.5V11H19v2h-1.5V7h3v1.5zM9 9.5h1v-1H9v1zM4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm10 5.5h1v-3h-1v3z"/></svg>';
                }

                if (uploadModal) {
                    uploadModal.style.display = 'block';
                }
            });
        }

        // Cancel upload
        if (cancelUpload) {
            cancelUpload.addEventListener('click', function() {
                if (uploadModal) {
                    uploadModal.style.display = 'none';
                }
            });
        }

        // Confirm upload and redirect to WhatsApp
        if (confirmUpload) {
            confirmUpload.addEventListener('click', function() {
                if (!selectedFile) {
                    alert('No file selected.');
                    return;
                }

                // Create WhatsApp message with booking details
                const bookingRef = '<?php echo $booking['booking_reference']; ?>';
                const amount = '<?php echo $total_amount_display; ?>';
                const userName = '<?php echo $is_guest ? ($booking['guest_name'] ?? 'Guest Customer') : ($_SESSION['user_name'] ?? 'Customer'); ?>';
                
                const message = `Hello! I have made a bank transfer for my booking.\n\nBooking Reference: ${bookingRef}\nCustomer Name: ${userName}\nAmount: ${amount}\n\nI have uploaded the proof of payment. Please confirm my booking.`;
                
                // Encode message for WhatsApp URL
                const encodedMessage = encodeURIComponent(message);
                
                // Redirect to specific WhatsApp number with pre-filled message
                // Note: WhatsApp doesn't support file upload via URL, so we only send the message
                const whatsappUrl = `https://wa.me/2349034072383?text=${encodedMessage}`;
                
                // Open WhatsApp in a new tab
                window.open(whatsappUrl, '_blank');
                
                // Show success message
                alert('Proof of payment uploaded successfully! You have been redirected to WhatsApp. Please send the pre-filled message to complete the process.');
                
                // Close modal
                if (uploadModal) {
                    uploadModal.style.display = 'none';
                }
                
                // Reset form
                if (fileInput) {
                    fileInput.value = '';
                }
                if (uploadPreview) {
                    uploadPreview.style.display = 'none';
                }
                if (havePaidBtn) {
                    havePaidBtn.disabled = true;
                }
                selectedFile = null;
            });
        }

        // Close modal when clicking outside
        window.addEventListener('click', function(e) {
            if (e.target === uploadModal) {
                uploadModal.style.display = 'none';
            }
        });

        // Smooth scrolling for better UX
        <?php if (isset($error)): ?>
            const errorElement = document.querySelector('.info-card.danger');
            if (errorElement) {
                setTimeout(() => {
                    errorElement.scrollIntoView({ 
                        behavior: 'smooth',
                        block: 'center'
                    });
                }, 500);
            }
        <?php endif; ?>
        
        // Auto-focus on first payment method for better accessibility
        const firstPaymentMethod = document.querySelector('.payment-method-card:not(.disabled)');
        if (firstPaymentMethod) {
            firstPaymentMethod.focus();
        }
    });

    // Enhanced touch support for mobile devices
    document.addEventListener('touchstart', function() {}, { passive: true });

    // Prevent zoom on double-tap for better mobile experience
    document.addEventListener('touchend', function(e) {
        if (e.touches && e.touches.length > 1) {
            e.preventDefault();
        }
    }, { passive: false });

    // Handle viewport for mobile devices
    function setViewportForMobile() {
        const viewport = document.querySelector('meta[name="viewport"]');
        if (viewport && window.innerWidth <= 768) {
            viewport.setAttribute('content', 'width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no');
        }
    }

    // Initialize on load and resize
    window.addEventListener('load', setViewportForMobile);
    window.addEventListener('resize', setViewportForMobile);
</script>

<?php
require_once 'includes/footer.php';
?>
