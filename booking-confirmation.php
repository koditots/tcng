<?php
// booking-confirmation.php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$page_title = "Booking Confirmation";

$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;

// Get booking details
$stmt = $pdo->prepare("SELECT * FROM flight_bookings WHERE id = ? AND user_id = ?");
$stmt->execute([$booking_id, $_SESSION['user_id']]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    redirect('dashboard.php');
}

$flight_data = json_decode($booking['flight_data'], true);
$passenger_info = json_decode($booking['passenger_info'], true);
$contact_info = json_decode($booking['contact_info'], true);

require_once 'includes/header.php';
?>

<div class="container">
    <div style="max-width: 800px; margin: 2rem auto; background: white; padding: 3rem; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); text-align: center;">
        <!-- Success Icon -->
        <div style="font-size: 4rem; color: #28a745; margin-bottom: 1rem;">✅</div>
        
        <h1 style="color: #28a745; margin-bottom: 1rem;">Booking Confirmed!</h1>
        <p style="font-size: 1.2rem; color: #666; margin-bottom: 2rem;">
            Thank you for your booking. Your flight has been confirmed.
        </p>
        
        <!-- Booking Details Card -->
        <div style="border: 2px solid #e9ecef; border-radius: 10px; padding: 2rem; margin-bottom: 2rem; text-align: left;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                <div>
                    <h3 style="color: #333; margin-bottom: 1rem;">Booking Information</h3>
                    <p><strong>Booking Reference:</strong> <span style="color: #007bff;"><?php echo $booking['booking_reference']; ?></span></p>
                    <p><strong>Ticket Number:</strong> <span style="color: #007bff;"><?php echo $booking['ticket_number']; ?></span></p>
                    <p><strong>Status:</strong> <span style="color: #28a745; font-weight: bold;">Confirmed</span></p>
                    <p><strong>Total Paid:</strong> <span style="color: #007bff; font-weight: bold;">₦<?php echo number_format($booking['total_amount'], 2); ?></span></p>
                </div>
                <div>
                    <h3 style="color: #333; margin-bottom: 1rem;">Contact Information</h3>
                    <p><strong>Email:</strong> <?php echo $contact_info['email']; ?></p>
                    <p><strong>Phone:</strong> <?php echo $contact_info['phone']; ?></p>
                    <p><strong>Booking Date:</strong> <?php echo date('F j, Y g:i A', strtotime($booking['created_at'])); ?></p>
                </div>
            </div>
            
            <!-- Flight Details -->
            <?php
            $itinerary = $flight_data['itineraries'][0];
            $first_segment = $itinerary['segments'][0];
            $last_segment = end($itinerary['segments']);
            ?>
            <h3 style="color: #333; margin-bottom: 1rem;">Flight Details</h3>
            <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <div>
                        <strong style="font-size: 1.2rem;">
                            <?php echo $first_segment['departure']['iataCode']; ?> to <?php echo $last_segment['arrival']['iataCode']; ?>
                        </strong>
                        <p style="color: #666; margin: 0.25rem 0;"><?php echo date('l, F j, Y', strtotime($first_segment['departure']['at'])); ?></p>
                    </div>
                    <div style="text-align: right;">
                        <strong style="color: #007bff;"><?php echo $first_segment['carrierCode']; ?></strong>
                        <p style="color: #666; margin: 0.25rem 0;"><?php echo $flight_data['travelerPricings'][0]['fareDetailsBySegment'][0]['cabin']; ?> Class</p>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr auto 1fr; gap: 1rem; align-items: center;">
                    <div style="text-align: center;">
                        <strong style="font-size: 1.3rem;"><?php echo date('H:i', strtotime($first_segment['departure']['at'])); ?></strong>
                        <p style="color: #666; margin: 0.25rem 0;"><?php echo $first_segment['departure']['iataCode']; ?></p>
                        <small><?php echo date('M j, Y', strtotime($first_segment['departure']['at'])); ?></small>
                    </div>
                    <div style="text-align: center;">
                        <div style="height: 2px; background: #007bff; width: 80px; margin: 0 auto;"></div>
                        <small style="color: #666;"><?php echo substr($itinerary['duration'], 2); ?></small>
                    </div>
                    <div style="text-align: center;">
                        <strong style="font-size: 1.3rem;"><?php echo date('H:i', strtotime($last_segment['arrival']['at'])); ?></strong>
                        <p style="color: #666; margin: 0.25rem 0;"><?php echo $last_segment['arrival']['iataCode']; ?></p>
                        <small><?php echo date('M j, Y', strtotime($last_segment['arrival']['at'])); ?></small>
                    </div>
                </div>
            </div>
            
            <!-- Passengers -->
            <h3 style="color: #333; margin-top: 2rem; margin-bottom: 1rem;">Passengers</h3>
            <div style="display: grid; gap: 1rem;">
                <?php foreach ($passenger_info as $index => $passenger): ?>
                <div style="background: #f8f9fa; padding: 1rem; border-radius: 5px;">
                    <strong>Passenger <?php echo $index; ?></strong>
                    <p style="margin: 0.5rem 0 0 0;"><?php echo $passenger['first_name'] . ' ' . $passenger['last_name']; ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <a href="dashboard.php" class="btn" style="background: #6c757d; color: white; text-decoration: none; padding: 1rem; border-radius: 5px; text-align: center;">
                Go to Dashboard
            </a>
            <button onclick="printTicket()" class="btn btn-primary" style="padding: 1rem; border-radius: 5px;">
                Print Ticket
            </button>
        </div>
        
        <!-- Additional Information -->
        <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #e9ecef;">
            <h4 style="color: #333; margin-bottom: 1rem;">Important Information</h4>
            <ul style="text-align: left; color: #666;">
                <li>Please arrive at the airport at least 2 hours before departure</li>
                <li>Bring a valid government-issued ID and this confirmation</li>
                <li>Check-in online 24 hours before your flight</li>
                <li>Contact support if you need to make changes to your booking</li>
            </ul>
        </div>
    </div>
</div>

<script>
function printTicket() {
    // You can implement actual ticket printing logic here
    alert('Ticket printing feature will be implemented soon!');
    // For now, redirect to a print-friendly page
    window.open('print-ticket.php?booking_id=<?php echo $booking_id; ?>', '_blank');
}
</script>

<?php
require_once 'includes/footer.php';
?>