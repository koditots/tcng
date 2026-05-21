<?php
// payment-success.php
require_once 'config.php';

$page_title = "Payment Successful";

$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;
$is_guest = isset($_GET['guest']) ? boolval($_GET['guest']) : (isset($_GET['type']) && $_GET['type'] === 'guest');

if ($booking_id <= 0) {
    $_SESSION['error'] = "Invalid booking reference.";
    redirect('flights.php');
}

// Fetch booking details based on user type
if ($is_guest) {
    $stmt = $pdo->prepare("SELECT * FROM flight_bookings WHERE id = ? AND is_guest = 1");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    if (!isLoggedIn()) {
        redirect('login.php?redirect=' . urlencode('payment-success.php?booking_id=' . $booking_id));
    }

    $stmt = $pdo->prepare("SELECT * FROM flight_bookings WHERE id = ? AND user_id = ?");
    $stmt->execute([$booking_id, $_SESSION['user_id']]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$booking) {
    $_SESSION['error'] = "Booking not found.";
    redirect($is_guest ? 'index.php' : 'dashboard.php');
}

if ($booking['payment_status'] !== 'paid') {
    $_SESSION['error'] = "Payment has not been completed for this booking.";
    redirect('payment.php?booking_id=' . $booking_id . ($is_guest ? '&type=guest' : ''));
}

$contact_info = json_decode($booking['contact_info'], true);
$currency = $booking['currency'] ?? 'NGN';
$currency_symbol = 'â‚¦';
switch ($currency) {
    case 'USD':
        $currency_symbol = '$';
        break;
    case 'EUR':
        $currency_symbol = 'â‚¬';
        break;
    case 'GBP':
        $currency_symbol = 'Â£';
        break;
}

$email_display = $is_guest
    ? ($booking['guest_email'] ?? 'N/A')
    : ($contact_info['email'] ?? ($_SESSION['user_email'] ?? 'N/A'));

require_once 'includes/header.php';
?>

<div class="container">
    <div style="max-width: 800px; margin: 2rem auto; background: white; padding: 3rem; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); text-align: center;">
        <div style="font-size: 4rem; color: #28a745; margin-bottom: 1rem;">âœ…</div>
        <h1 style="color: #28a745; margin-bottom: 0.5rem;">Payment Successful</h1>
        <p style="font-size: 1.1rem; color: #666; margin-bottom: 2rem;">
            Your payment has been received and your booking is confirmed.
        </p>

        <div style="border: 2px solid #e9ecef; border-radius: 10px; padding: 2rem; margin-bottom: 2rem; text-align: left;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <div>
                    <h3 style="color: #333; margin-bottom: 0.75rem;">Booking Summary</h3>
                    <p><strong>Booking Reference:</strong> <span style="color: #007bff;"><?php echo $booking['booking_reference']; ?></span></p>
                    <p><strong>Ticket Number:</strong> <span style="color: #007bff;"><?php echo $booking['ticket_number']; ?></span></p>
                    <?php if (!empty($booking['tracking_id'])): ?>
                        <p><strong>Tracking ID:</strong> <span style="color: #007bff;"><?php echo $booking['tracking_id']; ?></span></p>
                    <?php endif; ?>
                    <p><strong>Status:</strong> <span style="color: #28a745; font-weight: bold;">Confirmed</span></p>
                    <p><strong>Amount Paid:</strong> <span style="color: #007bff; font-weight: bold;"><?php echo $currency_symbol . number_format((float)$booking['total_amount'], 2); ?></span></p>
                </div>
                <div>
                    <h3 style="color: #333; margin-bottom: 0.75rem;">Contact</h3>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($email_display); ?></p>
                    <?php if (!empty($contact_info['phone'])): ?>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($contact_info['phone']); ?></p>
                    <?php elseif (!empty($booking['guest_phone'])): ?>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($booking['guest_phone']); ?></p>
                    <?php endif; ?>
                    <p><strong>Booking Date:</strong> <?php echo date('F j, Y g:i A', strtotime($booking['created_at'])); ?></p>
                </div>
            </div>
        </div>

        <?php if (!empty($_SESSION['success'])): ?>
            <div style="background: #e6f4ea; color: #155724; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <?php if ($is_guest): ?>
                <a href="track-ticket.php" class="btn" style="background: #007bff; color: white; text-decoration: none; padding: 1rem; border-radius: 5px; text-align: center;">
                    Track Your Ticket
                </a>
                <a href="login.php?redirect=<?php echo urlencode('booking-confirmation.php?booking_id=' . $booking_id); ?>" class="btn" style="background: #6c757d; color: white; text-decoration: none; padding: 1rem; border-radius: 5px; text-align: center;">
                    Login to View Details
                </a>
            <?php else: ?>
                <a href="booking-confirmation.php?booking_id=<?php echo $booking_id; ?>" class="btn" style="background: #007bff; color: white; text-decoration: none; padding: 1rem; border-radius: 5px; text-align: center;">
                    View Booking Details
                </a>
                <a href="print-ticket.php?booking_id=<?php echo $booking_id; ?>" class="btn" style="background: #6c757d; color: white; text-decoration: none; padding: 1rem; border-radius: 5px; text-align: center;">
                    Print Ticket
                </a>
            <?php endif; ?>
        </div>

        <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #e9ecef; color: #666;">
            <p>If you need help, contact our support team and provide your booking reference.</p>
        </div>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>
