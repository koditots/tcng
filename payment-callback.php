<?php
// payment-callback.php
require_once 'config.php';

$gateway = isset($_GET['gateway']) ? sanitize($_GET['gateway']) : '';
$reference = isset($_GET['reference']) ? sanitize($_GET['reference']) : '';
$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$guest_booking = isset($_GET['guest']) ? boolval($_GET['guest']) : false;

// Get payment details - modified for guest users
if ($guest_booking) {
    // For guest users - no user_id check
    $stmt = $pdo->prepare("SELECT p.*, fb.booking_reference, fb.total_amount, fb.user_id, fb.guest_email, fb.guest_name 
                          FROM payments p 
                          JOIN flight_bookings fb ON p.booking_id = fb.id 
                          WHERE p.payment_reference = ?");
    $stmt->execute([$reference]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    // For logged in users - maintain existing user_id check
    if (!isLoggedIn()) {
        redirect('login.php');
    }
    
    $stmt = $pdo->prepare("SELECT p.*, fb.booking_reference, fb.total_amount, fb.user_id 
                          FROM payments p 
                          JOIN flight_bookings fb ON p.booking_id = fb.id 
                          WHERE p.payment_reference = ? AND fb.user_id = ?");
    $stmt->execute([$reference, $_SESSION['user_id']]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$payment) {
    $_SESSION['error'] = "Payment not found.";
    if ($guest_booking) {
        redirect('index.php');
    } else {
        redirect('dashboard.php');
    }
}

$booking_id = $payment['booking_id'];

// Handle payment callback based on gateway
try {
    if ($status === 'success') {
        // Verify payment with gateway (in real implementation, verify webhook signature)
        $verification_result = verifyPayment($gateway, $reference);
        
        if ($verification_result['success']) {
            // Update payment status
            $stmt = $pdo->prepare("UPDATE payments SET status = 'success', gateway_reference = ?, verified_at = NOW() WHERE payment_reference = ?");
            $stmt->execute([$verification_result['gateway_reference'], $reference]);
            
            // Update booking status
            $stmt = $pdo->prepare("UPDATE flight_bookings SET status = 'confirmed', payment_status = 'paid' WHERE id = ?");
            $stmt->execute([$booking_id]);
            
            // Generate ticket number
            $ticket_number = 'TKT' . date('Ymd') . str_pad($booking_id, 6, '0', STR_PAD_LEFT);
            $stmt = $pdo->prepare("UPDATE flight_bookings SET ticket_number = ? WHERE id = ?");
            $stmt->execute([$ticket_number, $booking_id]);
            
            // Send confirmation email - handle both logged in and guest users
            if ($guest_booking && !empty($payment['guest_email'])) {
                // For guest users
                $user_email = $payment['guest_email'];
                $user_name = $payment['guest_name'] ?? 'Customer';
            } else {
                // For logged in users
                $user_email = $_SESSION['user_email'];
                $user_name = $_SESSION['user_name'];
            }
            
            $subject = "Booking Confirmed - " . $payment['booking_reference'];
            $message = "
                <h1>Booking Confirmed!</h1>
                <p>Dear " . $user_name . ",</p>
                <p>Your payment was successful and your flight booking has been confirmed.</p>
                <div style='background: #f8f9fa; padding: 1.5rem; border-radius: 5px; margin: 1rem 0;'>
                    <p><strong>Booking Reference:</strong> " . $payment['booking_reference'] . "</p>
                    <p><strong>Ticket Number:</strong> " . $ticket_number . "</p>
                    <p><strong>Amount Paid:</strong> ₦" . number_format($payment['amount'], 2) . "</p>
                    <p><strong>Payment Method:</strong> " . ucfirst($payment['payment_method']) . "</p>
                    <p><strong>Payment Reference:</strong> " . $reference . "</p>
                </div>
                <p>You can view and print your ticket using your booking reference.</p>
                <p>Thank you for choosing " . getSiteSetting($pdo, 'site_name') . "!</p>
            ";
            
            sendEmail($user_email, $subject, $message);
            
            // Add notification only for logged in users
            if (!$guest_booking && isset($_SESSION['user_id'])) {
                addNotification($pdo, $_SESSION['user_id'], 'Payment Successful', 'Your payment has been processed successfully. Ticket: ' . $ticket_number, 'success', 'booking', $booking_id);
            }
            
            $_SESSION['success'] = "Payment successful! Your booking has been confirmed.";
            
            if ($guest_booking) {
                redirect('payment-success.php?booking_id=' . $booking_id . '&guest=1');
            } else {
                redirect('payment-success.php?booking_id=' . $booking_id);
            }
            
        } else {
            // Payment verification failed
            $stmt = $pdo->prepare("UPDATE payments SET status = 'failed', failure_reason = ? WHERE payment_reference = ?");
            $stmt->execute([$verification_result['message'], $reference]);
            
            $_SESSION['error'] = "Payment verification failed: " . $verification_result['message'];
            if ($guest_booking) {
                redirect('payment.php?booking_id=' . $booking_id . '&guest=true');
            } else {
                redirect('payment.php?booking_id=' . $booking_id);
            }
        }
        
    } else {
        // Payment failed or cancelled
        $stmt = $pdo->prepare("UPDATE payments SET status = 'failed', failure_reason = 'User cancelled payment' WHERE payment_reference = ?");
        $stmt->execute([$reference]);
        
        $_SESSION['error'] = "Payment was cancelled or failed. Please try again.";
        if ($guest_booking) {
            redirect('payment.php?booking_id=' . $booking_id . '&guest=true');
        } else {
            redirect('payment.php?booking_id=' . $booking_id);
        }
    }
    
} catch (Exception $e) {
    error_log("Payment callback error: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred while processing your payment. Please contact support.";
    if ($guest_booking) {
        redirect('index.php');
    } else {
        redirect('dashboard.php');
    }
}

/**
 * Verify payment with gateway
 */
function verifyPayment($gateway, $reference) {
    // In a real implementation, you would verify with the actual gateway API
    // This is a simplified version for demo purposes
    
    switch ($gateway) {
        case 'paystack':
            // Simulate Paystack verification
            return [
                'success' => true,
                'gateway_reference' => 'PSK_' . $reference,
                'message' => 'Payment verified successfully'
            ];
            
        case 'flutterwave':
            // Simulate Flutterwave verification
            return [
                'success' => true,
                'gateway_reference' => 'FLW_' . $reference,
                'message' => 'Payment verified successfully'
            ];
            
        default:
            return [
                'success' => false,
                'gateway_reference' => null,
                'message' => 'Unknown payment gateway'
            ];
    }
}

// If we reach here, something went wrong
$_SESSION['error'] = "Invalid payment callback.";
if ($guest_booking) {
    redirect('index.php');
} else {
    redirect('dashboard.php');
}
?>
