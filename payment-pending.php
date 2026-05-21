<?php
// payment-pending.php
require_once 'config.php';

$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;
$reference = isset($_GET['reference']) ? sanitize($_GET['reference']) : '';
$guest_booking = isset($_GET['guest']) ? boolval($_GET['guest']) : false;

// Get booking and payment details - modified for guest users
if ($guest_booking) {
    // For guest users - no user_id check
    $stmt = $pdo->prepare("SELECT fb.*, p.payment_reference, p.amount 
                          FROM flight_bookings fb 
                          JOIN payments p ON fb.id = p.booking_id 
                          WHERE fb.id = ? AND p.payment_reference = ?");
    $stmt->execute([$booking_id, $reference]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    // For logged in users - maintain existing user_id check
    if (!isLoggedIn()) {
        redirect('login.php');
    }
    
    $stmt = $pdo->prepare("SELECT fb.*, p.payment_reference, p.amount 
                          FROM flight_bookings fb 
                          JOIN payments p ON fb.id = p.booking_id 
                          WHERE fb.id = ? AND fb.user_id = ? AND p.payment_reference = ?");
    $stmt->execute([$booking_id, $_SESSION['user_id'], $reference]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$booking) {
    if ($guest_booking) {
        redirect('index.php');
    } else {
        redirect('dashboard.php');
    }
}

// Get payment settings for bank details
$payment_settings = getPaymentSettings($pdo);

$page_title = "Payment Instructions";
require_once 'includes/header.php';
?>

<div class="container">
    <div style="max-width: 800px; margin: 2rem auto;">
        <div style="text-align: center; margin-bottom: 2rem;">
            <div style="font-size: 4rem; color: #ffc107;">⏳</div>
            <h1 style="color: #333; margin-bottom: 1rem;">Payment Instructions</h1>
            <p style="color: #666; font-size: 1.1rem;">Please complete your payment via bank transfer</p>
        </div>

        <div style="background: white; padding: 2rem; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); margin-bottom: 2rem;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                <!-- Booking Details -->
                <div>
                    <h3 style="color: #333; margin-bottom: 1rem;">Booking Details</h3>
                    <div style="background: #f8f9fa; padding: 1rem; border-radius: 5px;">
                        <div style="margin-bottom: 0.5rem;">
                            <strong>Booking Reference:</strong>
                            <div style="color: #007bff; font-weight: bold;"><?php echo $booking['booking_reference']; ?></div>
                        </div>
                        <div style="margin-bottom: 0.5rem;">
                            <strong>Payment Reference:</strong>
                            <div style="font-family: 'Courier New', monospace;"><?php echo $reference; ?></div>
                        </div>
                        <div>
                            <strong>Amount to Pay:</strong>
                            <div style="font-size: 1.2rem; font-weight: bold; color: #007bff;">
                                ₦<?php echo number_format($booking['amount'], 2); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bank Details -->
                <div>
                    <h3 style="color: #333; margin-bottom: 1rem;">Bank Transfer Details</h3>
                    <div style="background: #fff3cd; padding: 1.5rem; border-radius: 5px; border-left: 4px solid #ffc107;">
                        <div style="margin-bottom: 1rem;">
                            <strong>Bank Name:</strong>
                            <div style="font-weight: bold;"><?php echo $payment_settings['bank_name']; ?></div>
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <strong>Account Name:</strong>
                            <div style="font-weight: bold;"><?php echo $payment_settings['account_name']; ?></div>
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <strong>Account Number:</strong>
                            <div style="font-weight: bold; font-family: 'Courier New', monospace; font-size: 1.1rem;">
                                <?php echo $payment_settings['account_number']; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Important Instructions -->
            <div style="background: #e7f3ff; padding: 1.5rem; border-radius: 5px; border-left: 4px solid #007bff;">
                <h4 style="color: #0056b3; margin-bottom: 1rem;">📋 Important Instructions</h4>
                <ul style="color: #333; margin: 0; padding-left: 1.5rem;">
                    <li>Use the <strong>Payment Reference</strong> above as the transfer narration</li>
                    <li>Transfer the <strong>exact amount</strong> shown above</li>
                    <li>Your booking will be confirmed within 24 hours of payment receipt</li>
                    <li>You will receive a confirmation email once payment is verified</li>
                    <li>Keep your transfer receipt for reference</li>
                </ul>
            </div>
        </div>

        <!-- Next Steps -->
        <div style="background: #d4edda; padding: 1.5rem; border-radius: 5px; border-left: 4px solid #28a745;">
            <h4 style="color: #155724; margin-bottom: 1rem;">✅ What Happens Next?</h4>
            <ol style="color: #155724; margin: 0; padding-left: 1.5rem;">
                <li>Complete the bank transfer using the details above</li>
                <li>Our team will verify your payment (usually within 24 hours)</li>
                <li>You'll receive a confirmation email with your ticket</li>
                <li>Your booking status will update to "Confirmed"</li>
                <li>You can print your ticket from your dashboard</li>
            </ol>
        </div>

        <!-- Action Buttons -->
        <div style="display: flex; gap: 1rem; justify-content: center; margin-top: 2rem; flex-wrap: wrap;">
            <?php if ($guest_booking): ?>
                <a href="index.php" class="btn" style="background: #6c757d; color: white; padding: 0.75rem 1.5rem; text-decoration: none; border-radius: 5px;">
                    Back to Home
                </a>
                <a href="booking-details.php?id=<?php echo $booking_id; ?>&guest=true" class="btn btn-primary" style="background: #007bff; color: white; padding: 0.75rem 1.5rem; text-decoration: none; border-radius: 5px;">
                    View Booking Details
                </a>
            <?php else: ?>
                <a href="dashboard.php" class="btn" style="background: #6c757d; color: white; padding: 0.75rem 1.5rem; text-decoration: none; border-radius: 5px;">
                    Back to Dashboard
                </a>
                <a href="booking-details.php?id=<?php echo $booking_id; ?>" class="btn btn-primary" style="background: #007bff; color: white; padding: 0.75rem 1.5rem; text-decoration: none; border-radius: 5px;">
                    View Booking Details
                </a>
            <?php endif; ?>
            <button onclick="window.print()" class="btn" style="background: #17a2b8; color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 5px; cursor: pointer;">
                Print Instructions
            </button>
        </div>

        <!-- Support Info -->
        <div style="text-align: center; margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #dee2e6;">
            <p style="color: #666; margin-bottom: 0.5rem;">
                Need help? Contact our support team:
            </p>
            <p style="color: #007bff; font-weight: bold;">
                <?php echo getSiteSetting($pdo, 'support_email'); ?> | 
                <?php echo getSiteSetting($pdo, 'support_phone'); ?>
            </p>
        </div>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>