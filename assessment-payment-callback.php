<?php
// assessment-payment-callback.php
require_once 'config.php';

// Get payment reference from URL
$gateway = $_GET['gateway'] ?? '';
$reference = $_GET['reference'] ?? '';

if (empty($gateway) || empty($reference)) {
    header("Location: assessment-payment.php?error=Invalid callback parameters");
    exit;
}

try {
    // Get booking details using payment reference
    $stmt = $pdo->prepare("SELECT * FROM visa_assessment_bookings WHERE payment_reference = ?");
    $stmt->execute([$reference]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        header("Location: assessment-payment.php?error=Booking not found");
        exit;
    }
    
    // Verify payment based on gateway
    if ($gateway === 'paystack') {
        $payment_verified = verifyPaystackPayment($reference);
    } elseif ($gateway === 'flutterwave') {
        $payment_verified = verifyFlutterwavePayment($reference);
    } else {
        header("Location: assessment-payment.php?error=Invalid payment gateway");
        exit;
    }
    
    if ($payment_verified) {
        // Update booking status to paid
        $stmt = $pdo->prepare("UPDATE visa_assessment_bookings SET status = 'paid', paid_at = NOW() WHERE payment_reference = ?");
        $stmt->execute([$reference]);
        
        // Send confirmation email
        sendPaymentConfirmationEmail($booking);
        
        // Redirect to success page
        header("Location: assessment-payment.php?booking_id=" . $booking['id'] . "&success=1");
        exit;
    } else {
        header("Location: assessment-payment.php?booking_id=" . $booking['id'] . "&error=Payment verification failed");
        exit;
    }
    
} catch (Exception $e) {
    error_log("Payment callback error: " . $e->getMessage());
    header("Location: assessment-payment.php?error=Payment processing error");
    exit;
}

// Payment verification functions
function verifyPaystackPayment($reference) {
    // Implement Paystack payment verification
    // This should call Paystack API to verify transaction status
    return true; // Placeholder
}

function verifyFlutterwavePayment($reference) {
    // Implement Flutterwave payment verification  
    // This should call Flutterwave API to verify transaction status
    return true; // Placeholder
}

function sendPaymentConfirmationEmail($booking) {
    // Implement email sending for payment confirmation
    $to = $booking['email'];
    $subject = "Assessment Session Payment Confirmation";
    $message = "
        Dear {$booking['first_name']} {$booking['last_name']},
        
        Thank you for your payment! Your visa assessment session has been confirmed.
        
        Booking Details:
        - Application Number: {$booking['application_number']}
        - Session Fee: NGN " . number_format($booking['booking_fee'], 2) . "
        - Readiness Score: {$booking['readiness_score']}%
        
        Our visa specialist will contact you within 24 hours to schedule your session.
        
        Best regards,
        Travel Centre Team
    ";
    
    // Use your email sending function here
    error_log("Payment confirmation email sent to: " . $to);
}
?>