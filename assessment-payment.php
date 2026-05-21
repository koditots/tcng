<?php
// assessment-payment.php

// Start output buffering at the very beginning
if (!headers_sent()) {
    ob_start();
}

require_once 'config.php';

$page_title = "Assessment Payment";
$error = '';
$success = '';

// Get booking details
if (!isset($_GET['booking_id']) || empty($_GET['booking_id'])) {
    header("Location: visa-assessment.php");
    exit;
}

$booking_id = intval($_GET['booking_id']);

try {
    // Fetch booking details with payment link
    $stmt = $pdo->prepare("
        SELECT 
            vb.*
        FROM visa_assessment_bookings vb
        WHERE vb.id = ?
    ");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        throw new Exception("Booking not found. Please check your booking reference and try again.");
    }
    
    // Check if booking is already paid
    if ($booking['status'] === 'paid') {
        $success = "Your assessment session has already been confirmed! Our visa specialist will contact you within 24 hours.";
    }
    // Check if booking is pending and needs payment
    elseif ($booking['status'] === 'pending') {
        // If payment_link is empty in booking, get it from site_settings based on grade
        if (empty($booking['payment_link'])) {
            $grade = $booking['readiness_grade'];
            $settings_stmt = $pdo->prepare("
                SELECT 
                    visa_assessment_booking_link_low,
                    visa_assessment_booking_link_medium,
                    visa_assessment_booking_link_high
                FROM site_settings ORDER BY id DESC LIMIT 1
            ");
            $settings_stmt->execute();
            $settings = $settings_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($settings) {
                switch ($grade) {
                    case 'low':
                        $booking['payment_link'] = $settings['visa_assessment_booking_link_low'];
                        break;
                    case 'medium':
                        $booking['payment_link'] = $settings['visa_assessment_booking_link_medium'];
                        break;
                    case 'high':
                        $booking['payment_link'] = $settings['visa_assessment_booking_link_high'];
                        break;
                }
            }
        }
        
        // Check if payment is required
        if ($booking['booking_fee'] <= 0 || empty($booking['payment_link'])) {
            // No payment required, mark as paid
            $update_stmt = $pdo->prepare("UPDATE visa_assessment_bookings SET status = 'paid' WHERE id = ?");
            $update_stmt->execute([$booking_id]);
            $success = "Your assessment session has been confirmed! Our visa specialist will contact you within 24 hours.";
        }
    }
    
} catch (Exception $e) {
    $error = $e->getMessage();
}

require_once 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Travel Centre</title>
    <style>
        .payment-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .payment-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
        }
        .booking-details {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            text-align: left;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }
        .detail-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .payment-button {
            display: inline-block;
            background: #28a745;
            color: white;
            padding: 15px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 18px;
            font-weight: bold;
            margin: 20px 0;
            transition: all 0.3s ease;
        }
        .payment-button:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .return-button {
            display: inline-block;
            background: #007bff;
            color: white;
            padding: 12px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 16px;
            font-weight: bold;
            margin: 10px 0;
            transition: all 0.3s ease;
        }
        .return-button:hover {
            background: #0056b3;
            transform: translateY(-1px);
        }
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-danger {
            background: #fee;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .alert-success {
            background: #eff;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .alert-info {
            background: #e7f3ff;
            border: 1px solid #b3d7ff;
            color: #004085;
        }
        .info-box {
            margin-top: 30px;
            padding: 20px;
            background: #fff3cd;
            border-radius: 8px;
            text-align: left;
            border-left: 4px solid #ffc107;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 10px;
        }
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        .status-paid {
            background: #d4edda;
            color: #155724;
        }
        .status-failed {
            background: #f8d7da;
            color: #721c24;
        }
        .loading {
            display: none;
            text-align: center;
            margin: 20px 0;
        }
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #007bff;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 2s linear infinite;
            margin: 0 auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .payment-instructions {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: left;
        }
        .payment-instructions h4 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        .payment-instructions ol {
            margin-left: 20px;
            color: #555;
        }
        .payment-instructions li {
            margin-bottom: 8px;
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <h1>Assessment Session Payment</h1>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <strong>Error:</strong> <?php echo $error; ?>
            </div>
            <a href="visa-assessment.php" class="return-button">Return to Assessment</a>
            
        <?php elseif ($success): ?>
            <div class="alert alert-success">
                <strong>Success!</strong> <?php echo $success; ?>
            </div>
            <div class="payment-card">
                <h2>🎉 Booking Confirmed!</h2>
                <p>Your visa assessment session has been successfully booked.</p>
                
                <div class="booking-details">
                    <div class="detail-row">
                        <strong>Application Number:</strong>
                        <span><?php echo htmlspecialchars($booking['application_number']); ?></span>
                    </div>
                    <div class="detail-row">
                        <strong>Readiness Score:</strong>
                        <span><?php echo htmlspecialchars($booking['readiness_score']); ?>%</span>
                    </div>
                    <div class="detail-row">
                        <strong>Assessment Grade:</strong>
                        <span><?php echo ucfirst(htmlspecialchars($booking['readiness_grade'])); ?> Chance</span>
                    </div>
                    <div class="detail-row">
                        <strong>Session Fee:</strong>
                        <span>NGN <?php echo number_format($booking['booking_fee'], 2); ?></span>
                    </div>
                    <div class="detail-row">
                        <strong>Status:</strong>
                        <span class="status-badge status-paid">PAID</span>
                    </div>
                </div>
                
                <div class="info-box">
                    <h4>📞 What Happens Next?</h4>
                    <p>Our visa specialist will contact you within 24 hours via email or phone to schedule your assessment session.</p>
                    <p><strong>Contact:</strong> <?php echo htmlspecialchars($booking['email']); ?> | <?php echo htmlspecialchars($booking['phone']); ?></p>
                </div>
                
                <a href="visa-assessment.php" class="return-button">Book Another Assessment</a>
            </div>
            
        <?php else: ?>
            <div class="payment-card">
                <h2>Complete Your Payment</h2>
                <p>Please complete the payment to secure your visa assessment session.</p>
                
                <div class="booking-details">
                    <div class="detail-row">
                        <strong>Application Number:</strong>
                        <span><?php echo htmlspecialchars($booking['application_number']); ?></span>
                    </div>
                    <div class="detail-row">
                        <strong>Customer Name:</strong>
                        <span><?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?></span>
                    </div>
                    <div class="detail-row">
                        <strong>Readiness Score:</strong>
                        <span><?php echo htmlspecialchars($booking['readiness_score']); ?>%</span>
                    </div>
                    <div class="detail-row">
                        <strong>Assessment Grade:</strong>
                        <span><?php echo ucfirst(htmlspecialchars($booking['readiness_grade'])); ?> Chance</span>
                    </div>
                    <div class="detail-row">
                        <strong>Session Fee:</strong>
                        <span>NGN <?php echo number_format($booking['booking_fee'], 2); ?></span>
                    </div>
                    <div class="detail-row">
                        <strong>Status:</strong>
                        <span class="status-badge status-pending">PENDING PAYMENT</span>
                    </div>
                </div>

                <?php if (!empty($booking['payment_link'])): ?>
                    <div class="payment-instructions">
                        <h4>💳 Payment Instructions</h4>
                        <ol>
                            <li>Click the "Pay Now" button below</li>
                            <li>You will be redirected to our secure payment gateway</li>
                            <li>Complete your payment using your preferred method</li>
                            <li>Return to this page after successful payment</li>
                            <li>Your booking will be automatically confirmed</li>
                        </ol>
                    </div>
                    
                    <a href="<?php echo htmlspecialchars($booking['payment_link']); ?>" 
                       class="payment-button" 
                       target="_blank"
                       onclick="document.getElementById('paymentLoading').style.display='block';">
                       Pay Now - NGN <?php echo number_format($booking['booking_fee'], 2); ?>
                    </a>
                    
                    <div class="loading" id="paymentLoading">
                        <div class="spinner"></div>
                        <p>Redirecting to payment gateway...</p>
                    </div>
                    
                    <p style="color: #666; margin-top: 20px; font-size: 14px;">
                        <strong>Note:</strong> You will be redirected to our secure payment gateway. 
                        After successful payment, our visa specialist will contact you within 24 hours to schedule your session.
                    </p>
                    
                <?php else: ?>
                    <div class="alert alert-danger">
                        <strong>Payment Link Not Available</strong>
                        <p>We apologize, but the payment link is currently not available for your assessment grade (<?php echo ucfirst($booking['readiness_grade']); ?>).</p>
                        <p>Please contact our support team for assistance with your booking.</p>
                    </div>
                <?php endif; ?>
                
                <div class="info-box">
                    <h4>📞 Need Help?</h4>
                    <p>If you encounter any issues with payment, please contact our support team.</p>
                    <p><strong>Email:</strong> support@travelcentre.com</p>
                    <p><strong>Phone:</strong> +234 XXX XXX XXXX</p>
                    <p><strong>Hours:</strong> Monday - Friday, 9:00 AM - 6:00 PM</p>
                </div>

                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e9ecef;">
                    <a href="visa-assessment.php" class="return-button">Return to Assessment</a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-hide loading after 5 seconds (safety measure)
            setTimeout(function() {
                const loading = document.getElementById('paymentLoading');
                if (loading) {
                    loading.style.display = 'none';
                }
            }, 5000);

            // Check if payment was completed (you can enhance this with webhooks later)
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('payment') === 'success') {
                alert('Payment completed successfully! Your booking is now confirmed.');
            } else if (urlParams.get('payment') === 'failed') {
                alert('Payment failed. Please try again or contact support.');
            }
        });
    </script>
</body>
</html>

<?php
require_once 'includes/footer.php';
?>