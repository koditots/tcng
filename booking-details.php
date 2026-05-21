<?php
// booking-details.php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$page_title = "Booking Details";

$booking_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get booking details with user information
$stmt = $pdo->prepare("SELECT fb.*, u.first_name, u.last_name, u.email, u.phone 
                      FROM flight_bookings fb 
                      LEFT JOIN users u ON fb.user_id = u.id 
                      WHERE fb.id = ? AND fb.user_id = ?");
$stmt->execute([$booking_id, $_SESSION['user_id']]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    redirect('dashboard.php');
}

$flight_data = json_decode($booking['flight_data'], true);
$passenger_info = json_decode($booking['passenger_info'], true);
$contact_info = json_decode($booking['contact_info'], true);

// Debug: Check what data we have
error_log("Passenger Info: " . print_r($passenger_info, true));
error_log("Contact Info: " . print_r($contact_info, true));

// Check if passenger_info is valid and is an array
if (!is_array($passenger_info)) {
    $passenger_info = [];
    // Try alternative decoding if the first method fails
    $passenger_info_alt = json_decode($booking['passenger_info'], true);
    if (is_array($passenger_info_alt)) {
        $passenger_info = $passenger_info_alt;
    }
}

// Check if contact_info is valid and is an array
if (!is_array($contact_info)) {
    $contact_info = [];
    // Try alternative decoding if the first method fails
    $contact_info_alt = json_decode($booking['contact_info'], true);
    if (is_array($contact_info_alt)) {
        $contact_info = $contact_info_alt;
    }
}

// If still no passenger info, create default from user data
if (empty($passenger_info)) {
    $passenger_info = [
        [
            'first_name' => $booking['first_name'],
            'last_name' => $booking['last_name'],
            'email' => $booking['email'],
            'phone' => $booking['phone'] ?: 'Not provided',
            'dob' => '1990-01-01', // Default DOB
            'gender' => 'Not specified'
        ]
    ];
}

// If still no contact info, create default from user data
if (empty($contact_info)) {
    $contact_info = [
        'email' => $booking['email'],
        'phone' => $booking['phone'] ?: 'Not provided',
        'address' => 'Address not provided'
    ];
}

$itinerary = $flight_data['itineraries'][0];
$first_segment = $itinerary['segments'][0];
$last_segment = end($itinerary['segments']);

require_once 'includes/header.php';
?>

<div class="container">
    <div style="max-width: 1000px; margin: 0 auto;">
        <!-- Header with Back Button -->
        <div style="display: flex; justify-content: between; align-items: center; margin: 2rem 0;">
            <a href="dashboard.php" class="btn" style="background: #6c757d; color: white; padding: 0.5rem 1rem; text-decoration: none; border-radius: 5px;">
                ← Back to Dashboard
            </a>
            <h1 style="color: #333; margin: 0;">Booking Details</h1>
        </div>

        <!-- Main Booking Card -->
        <div style="background: white; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); margin-bottom: 2rem;">
            <!-- Booking Header -->
            <div style="padding: 1.5rem; background: #f8f9fa; border-bottom: 1px solid #dee2e6; border-radius: 10px 10px 0 0; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h2 style="color: #333; margin: 0;">Booking #<?php echo $booking['booking_reference']; ?></h2>
                    <p style="color: #666; margin: 0.5rem 0 0 0;">Created on <?php echo date('F j, Y g:i A', strtotime($booking['created_at'])); ?></p>
                </div>
                <div>
                    <span class="badge badge-<?php 
                        switch($booking['status']) {
                            case 'confirmed': echo 'success';
                            case 'paid': echo 'success';
                            case 'pending': echo 'warning';
                            case 'cancelled': echo 'danger';
                            default: echo 'secondary';
                        }
                    ?>" style="padding: 0.5rem 1rem; border-radius: 15px; font-weight: bold; background: 
                        <?php 
                            switch($booking['status']) {
                                case 'confirmed': echo '#d4edda'; break;
                                case 'paid': echo '#d4edda'; break;
                                case 'pending': echo '#fff3cd'; break;
                                case 'cancelled': echo '#f8d7da'; break;
                                default: echo '#e2e3e5'; break;
                            }
                        ?>; color: 
                        <?php 
                            switch($booking['status']) {
                                case 'confirmed': echo '#155724'; break;
                                case 'paid': echo '#155724'; break;
                                case 'pending': echo '#856404'; break;
                                case 'cancelled': echo '#721c24'; break;
                                default: echo '#383d41'; break;
                            }
                        ?>;">
                        <?php echo strtoupper($booking['status']); ?>
                    </span>
                </div>
            </div>

            <div style="padding: 2rem;">
                <!-- Customer Information -->
                <div style="margin-bottom: 2rem;">
                    <h3 style="color: #333; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid #007bff;">Customer Information</h3>
                    
                    <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px;">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
                            <div>
                                <strong style="color: #666;">Full Name</strong>
                                <div style="font-size: 1.1rem; font-weight: bold;"><?php echo $booking['first_name'] . ' ' . $booking['last_name']; ?></div>
                            </div>
                            <div>
                                <strong style="color: #666;">Email Address</strong>
                                <div><?php echo $booking['email']; ?></div>
                            </div>
                            <div>
                                <strong style="color: #666;">Phone Number</strong>
                                <div><?php echo $booking['phone'] ?: 'Not provided'; ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Flight Information -->
                <div style="margin-bottom: 2rem;">
                    <h3 style="color: #333; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid #007bff;">Flight Information</h3>
                    
                    <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px;">
                        <div style="display: grid; grid-template-columns: 1fr auto 1fr; gap: 2rem; align-items: center; text-align: center;">
                            <!-- Departure -->
                            <div style="padding: 1rem;">
                                <div style="font-size: 1.5rem; font-weight: bold; color: #007bff;"><?php echo $first_segment['departure']['iataCode']; ?></div>
                                <div style="color: #666; margin: 0.5rem 0;">Departure</div>
                                <div style="font-size: 1.2rem; font-weight: bold;"><?php echo date('H:i', strtotime($first_segment['departure']['at'])); ?></div>
                                <div style="color: #666;"><?php echo date('M j, Y', strtotime($first_segment['departure']['at'])); ?></div>
                            </div>
                            
                            <!-- Flight Duration -->
                            <div style="padding: 1rem;">
                                <div style="color: #666;">Flight Duration</div>
                                <div style="font-weight: bold; margin: 0.5rem 0; font-size: 1.1rem;"><?php echo substr($itinerary['duration'], 2); ?></div>
                                <div style="height: 2px; background: #007bff; margin: 0.5rem 0;"></div>
                                <div style="color: #666;"><?php echo $first_segment['carrierCode']; ?></div>
                            </div>
                            
                            <!-- Arrival -->
                            <div style="padding: 1rem;">
                                <div style="font-size: 1.5rem; font-weight: bold; color: #007bff;"><?php echo $last_segment['arrival']['iataCode']; ?></div>
                                <div style="color: #666; margin: 0.5rem 0;">Arrival</div>
                                <div style="font-size: 1.2rem; font-weight: bold;"><?php echo date('H:i', strtotime($last_segment['arrival']['at'])); ?></div>
                                <div style="color: #666;"><?php echo date('M j, Y', strtotime($last_segment['arrival']['at'])); ?></div>
                            </div>
                        </div>
                        
                        <!-- Flight Details -->
                        <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #dee2e6; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                            <div>
                                <strong style="color: #666;">Airline</strong>
                                <div><?php echo $first_segment['carrierCode']; ?></div>
                            </div>
                            <div>
                                <strong style="color: #666;">Flight Class</strong>
                                <div><?php echo $flight_data['travelerPricings'][0]['fareDetailsBySegment'][0]['cabin']; ?></div>
                            </div>
                            <div>
                                <strong style="color: #666;">Number of Stops</strong>
                                <div><?php echo count($itinerary['segments']) - 1; ?> stop(s)</div>
                            </div>
                            <div>
                                <strong style="color: #666;">Flight Number</strong>
                                <div><?php echo $first_segment['carrierCode'] . $first_segment['number']; ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Passenger Information -->
                <div style="margin-bottom: 2rem;">
                    <h3 style="color: #333; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid #007bff;">Passenger Information</h3>
                    
                    <div style="display: grid; gap: 1rem;">
                        <?php foreach ($passenger_info as $index => $passenger): ?>
                        <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px;">
                            <h4 style="color: #333; margin-bottom: 1rem;">Passenger <?php echo $index + 1; ?></h4>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                                <div>
                                    <strong style="color: #666;">Full Name</strong>
                                    <div><?php echo $passenger['first_name'] . ' ' . $passenger['last_name']; ?></div>
                                </div>
                                <div>
                                    <strong style="color: #666;">Date of Birth</strong>
                                    <div><?php echo isset($passenger['dob']) ? date('M j, Y', strtotime($passenger['dob'])) : 'Not specified'; ?></div>
                                </div>
                                <div>
                                    <strong style="color: #666;">Gender</strong>
                                    <div><?php echo isset($passenger['gender']) ? ucfirst($passenger['gender']) : 'Not specified'; ?></div>
                                </div>
                                <div>
                                    <strong style="color: #666;">Email</strong>
                                    <div><?php echo isset($passenger['email']) ? $passenger['email'] : $booking['email']; ?></div>
                                </div>
                                <div>
                                    <strong style="color: #666;">Phone</strong>
                                    <div><?php echo isset($passenger['phone']) ? $passenger['phone'] : ($booking['phone'] ?: 'Not provided'); ?></div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Contact Information -->
                <div style="margin-bottom: 2rem;">
                    <h3 style="color: #333; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid #007bff;">Contact Information</h3>
                    
                    <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px;">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                            <div>
                                <strong style="color: #666;">Contact Email</strong>
                                <div><?php echo isset($contact_info['email']) ? $contact_info['email'] : $booking['email']; ?></div>
                            </div>
                            
                            <div>
                                <strong style="color: #666;">Contact Phone</strong>
                                <div><?php echo isset($contact_info['phone']) ? $contact_info['phone'] : ($booking['phone'] ?: 'Not provided'); ?></div>
                            </div>
                            
                            <div style="grid-column: 1 / -1;">
                                <strong style="color: #666;">Address</strong>
                                <div><?php echo isset($contact_info['address']) ? $contact_info['address'] : 'Address not provided'; ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment & Booking Details -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                    <!-- Payment Information -->
                    <div>
                        <h3 style="color: #333; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid #007bff;">Payment Information</h3>
                        
                        <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px;">
                            <div style="margin-bottom: 1rem;">
                                <strong style="color: #666;">Total Amount</strong>
                                <div style="font-size: 1.5rem; font-weight: bold; color: #007bff;">
                                    ₦<?php echo number_format($booking['total_amount'], 2); ?>
                                </div>
                            </div>
                            
                            <div style="margin-bottom: 0.5rem;">
                                <strong style="color: #666;">Payment Status</strong>
                                <div>
                                    <span style="padding: 0.25rem 0.75rem; border-radius: 10px; font-weight: bold; background: <?php echo $booking['payment_status'] === 'paid' ? '#d4edda' : '#fff3cd'; ?>; color: <?php echo $booking['payment_status'] === 'paid' ? '#155724' : '#856404'; ?>;">
                                        <?php echo strtoupper($booking['payment_status']); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <?php if ($booking['payment_method']): ?>
                            <div style="margin-bottom: 0.5rem;">
                                <strong style="color: #666;">Payment Method</strong>
                                <div><?php echo ucfirst($booking['payment_method']); ?></div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($booking['payment_reference']): ?>
                            <div style="margin-bottom: 0.5rem;">
                                <strong style="color: #666;">Payment Reference</strong>
                                <div style="font-family: 'Courier New', monospace;"><?php echo $booking['payment_reference']; ?></div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($booking['ticket_number']): ?>
                            <div>
                                <strong style="color: #666;">Ticket Number</strong>
                                <div style="font-family: 'Courier New', monospace; font-weight: bold;"><?php echo $booking['ticket_number']; ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Booking Details -->
                    <div>
                        <h3 style="color: #333; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid #007bff;">Booking Details</h3>
                        
                        <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px;">
                            <div style="margin-bottom: 0.5rem;">
                                <strong style="color: #666;">Booking Reference</strong>
                                <div style="font-family: 'Courier New', monospace; font-weight: bold;"><?php echo $booking['booking_reference']; ?></div>
                            </div>
                            
                            <div style="margin-bottom: 0.5rem;">
                                <strong style="color: #666;">Booking Date</strong>
                                <div><?php echo date('F j, Y g:i A', strtotime($booking['created_at'])); ?></div>
                            </div>
                            
                            <div style="margin-bottom: 0.5rem;">
                                <strong style="color: #666;">Number of Passengers</strong>
                                <div><?php echo count($passenger_info); ?></div>
                            </div>
                            
                            <div style="margin-bottom: 0.5rem;">
                                <strong style="color: #666;">Currency</strong>
                                <div><?php echo $booking['currency']; ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #dee2e6;">
                    <h3 style="color: #333; margin-bottom: 1rem;">Actions</h3>
                    
                    <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                        <?php if ($booking['status'] === 'confirmed' || $booking['status'] === 'paid'): ?>
                            <a href="print-ticket.php?booking_id=<?php echo $booking['id']; ?>" target="_blank" class="btn btn-primary" style="background: #007bff; color: white; padding: 0.75rem 1.5rem; text-decoration: none; border-radius: 5px;">
                                Print Ticket
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($booking['status'] === 'pending' && $booking['payment_status'] === 'pending'): ?>
                            <a href="payment.php?booking_id=<?php echo $booking['id']; ?>" class="btn btn-primary" style="background: #007bff; color: white; padding: 0.75rem 1.5rem; text-decoration: none; border-radius: 5px;">
                                Complete Payment
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($booking['status'] !== 'cancelled'): ?>
                            <button onclick="confirmCancellation()" class="btn btn-warning" style="background: #ffc107; color: #212529; padding: 0.75rem 1.5rem; border: none; border-radius: 5px; cursor: pointer;">
                                Cancel Booking
                            </button>
                        <?php endif; ?>
                        
                        <a href="dashboard.php" class="btn" style="background: #6c757d; color: white; padding: 0.75rem 1.5rem; text-decoration: none; border-radius: 5px;">
                            Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function confirmCancellation() {
    if (confirm('Are you sure you want to cancel this booking? This action cannot be undone.')) {
        // You can implement cancellation logic here
        // For now, just show a message
        alert('Cancellation feature will be implemented soon. Please contact support for assistance.');
    }
}
</script>

<?php
require_once 'includes/footer.php';
?>