<?php
// my-bookings.php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$page_title = "My Bookings";

$user_id = $_SESSION['user_id'];

// Get user bookings
$stmt = $pdo->prepare("SELECT * FROM flight_bookings WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once 'includes/header.php';
?>

<div class="container">
    <h1 style="margin: 2rem 0; color: #333;">My Bookings</h1>
    
    <?php if (!empty($bookings)): ?>
        <div style="display: grid; gap: 1.5rem;">
            <?php foreach ($bookings as $booking): 
                $flight_data = json_decode($booking['flight_data'], true);
                $itinerary = $flight_data['itineraries'][0];
                $first_segment = $itinerary['segments'][0];
                $last_segment = end($itinerary['segments']);
            ?>
                <div style="background: white; padding: 1.5rem; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
                    <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 1rem; align-items: center; margin-bottom: 1rem;">
                        <div>
                            <h3 style="color: #333; margin-bottom: 0.5rem;">
                                <?php echo $first_segment['departure']['iataCode']; ?> → <?php echo $last_segment['arrival']['iataCode']; ?>
                            </h3>
                            <p style="color: #666; margin-bottom: 0.5rem;">
                                <?php echo date('F j, Y', strtotime($first_segment['departure']['at'])); ?> • 
                                <?php echo $first_segment['carrierCode']; ?> • 
                                <?php echo $flight_data['travelerPricings'][0]['fareDetailsBySegment'][0]['cabin']; ?>
                            </p>
                            <p style="color: #666; font-size: 0.9rem;">
                                Booking Ref: <strong><?php echo $booking['booking_reference']; ?></strong>
                                <?php if ($booking['ticket_number']): ?>
                                    • Ticket: <strong><?php echo $booking['ticket_number']; ?></strong>
                                <?php endif; ?>
                            </p>
                        </div>
                        
                        <div style="text-align: center;">
                            <p style="font-size: 1.2rem; font-weight: bold; color: #007bff;">
                                ₦<?php echo number_format($booking['total_amount'], 2); ?>
                            </p>
                            <p style="color: #666; font-size: 0.9rem;">Total Paid</p>
                        </div>
                        
                        <div style="text-align: center;">
                            <span style="padding: 0.5rem 1rem; border-radius: 15px; font-weight: bold; 
                                background: <?php 
                                    switch($booking['status']) {
                                        case 'confirmed': echo '#d4edda'; break;
                                        case 'paid': echo '#d4edda'; break;
                                        case 'pending': echo '#fff3cd'; break;
                                        case 'cancelled': echo '#f8d7da'; break;
                                        default: echo '#e2e3e5';
                                    }
                                ?>; 
                                color: <?php 
                                    switch($booking['status']) {
                                        case 'confirmed': echo '#155724'; break;
                                        case 'paid': echo '#155724'; break;
                                        case 'pending': echo '#856404'; break;
                                        case 'cancelled': echo '#721c24'; break;
                                        default: echo '#383d41';
                                    }
                                ?>;">
                                <?php echo ucfirst($booking['status']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                        <a href="booking-details.php?id=<?php echo $booking['id']; ?>" class="btn btn-primary">View Details</a>
                        <a href="print-ticket.php?booking_id=<?php echo $booking['id']; ?>" target="_blank" class="btn" style="background: #28a745; color: white;">Print Ticket</a>
                        <a href="contact.php" class="btn" style="background: #6c757d; color: white;">Get Help</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 4rem 0;">
            <h3 style="color: #666; margin-bottom: 1rem;">No bookings found</h3>
            <p style="color: #666; margin-bottom: 2rem;">You haven't made any bookings yet.</p>
            <a href="flights.php" class="btn btn-primary">Book Your First Flight</a>
        </div>
    <?php endif; ?>
</div>

<?php
require_once 'includes/footer.php';
?>