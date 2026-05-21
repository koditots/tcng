<?php
// admin/booking-details.php
require_once '../config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

$page_title = "Booking Details";

$booking_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get booking details
$stmt = $pdo->prepare("SELECT fb.*, u.first_name, u.last_name, u.email, u.phone 
                      FROM flight_bookings fb 
                      LEFT JOIN users u ON fb.user_id = u.id 
                      WHERE fb.id = ?");
$stmt->execute([$booking_id]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    redirect('bookings.php');
}

$flight_data = json_decode($booking['flight_data'], true);
$passenger_info = json_decode($booking['passenger_info'], true);
$contact_info = json_decode($booking['contact_info'], true);

// Check if passenger_info is valid and is an array
if (!is_array($passenger_info)) {
    $passenger_info = [];
}

// Check if contact_info is valid and is an array
if (!is_array($contact_info)) {
    $contact_info = [];
}

$itinerary = $flight_data['itineraries'][0];
$first_segment = $itinerary['segments'][0];
$last_segment = end($itinerary['segments']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo getSiteSetting($pdo, 'site_name'); ?></title>
    <style>
        /* Reuse admin styles */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; background: #f8f9fa; display: flex; }
        .sidebar { width: 250px; background: #343a40; color: white; height: 100vh; position: fixed; }
        .sidebar-header { padding: 1.5rem; background: #2c3136; text-align: center; }
        .sidebar-menu { list-style: none; padding: 1rem 0; }
        .sidebar-menu li { margin-bottom: 0.5rem; }
        .sidebar-menu a { color: #adb5bd; text-decoration: none; padding: 0.75rem 1.5rem; display: block; transition: all 0.3s; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background: #495057; color: white; border-left: 4px solid #007bff; }
        .main-content { flex: 1; margin-left: 250px; padding: 0; }
        .top-bar { background: white; padding: 1rem 2rem; box-shadow: 0 2px 10px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .content { padding: 2rem; }
        
        .card { background: white; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); margin-bottom: 2rem; }
        .card-header { padding: 1.5rem; background: #f8f9fa; border-bottom: 1px solid #dee2e6; display: flex; justify-content: space-between; align-items: center; }
        .card-body { padding: 1.5rem; }
        
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem; }
        .info-section { margin-bottom: 2rem; }
        .info-section h3 { color: #333; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid #007bff; }
        
        .info-item { margin-bottom: 1rem; }
        .info-label { font-weight: bold; color: #666; display: block; margin-bottom: 0.25rem; }
        .info-value { color: #333; }
        
        .flight-summary { background: #f8f9fa; padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem; }
        .flight-route { display: grid; grid-template-columns: 1fr auto 1fr; gap: 1rem; align-items: center; text-align: center; }
        .airport { padding: 1rem; }
        .airport-code { font-size: 1.5rem; font-weight: bold; color: #007bff; }
        .flight-duration { padding: 1rem; }
        .duration-line { height: 2px; background: #007bff; margin: 0.5rem 0; }
        
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        
        .badge { padding: 0.5rem 1rem; border-radius: 15px; font-weight: bold; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        
        .passenger-list { display: grid; gap: 1rem; }
        .passenger-card { background: #f8f9fa; padding: 1rem; border-radius: 5px; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2><?php echo getSiteSetting($pdo, 'site_name'); ?></h2>
            <small>Admin Panel</small>
        </div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php">📊 Dashboard</a></li>
            <li><a href="bookings.php">📋 Bookings</a></li>
            <li><a href="users.php">👥 Users</a></li>
            <li><a href="flights.php">✈️ Flights</a></li>
            <li><a href="pages.php">📄 Pages</a></li>
            <li><a href="menus.php">🍔 Menus</a></li>
            <li><a href="settings.php">⚙️ Settings</a></li>
            <li><a href="payments.php">💳 Payments</a></li>
            <li><a href="email-templates.php">✉️ Email Templates</a></li>
            <li><a href="../logout.php">🚪 Logout</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-bar">
            <h1>Booking Details</h1>
            <div>
                <span>Welcome, <?php echo $_SESSION['user_name']; ?></span>
            </div>
        </div>

        <div class="content">
            <div class="card">
                <div class="card-header">
                    <h2>Booking #<?php echo $booking['booking_reference']; ?></h2>
                    <div>
                        <span class="badge badge-<?php 
                            switch($booking['status']) {
                                case 'confirmed': echo 'success';
                                case 'paid': echo 'success';
                                case 'pending': echo 'warning';
                                case 'cancelled': echo 'danger';
                                default: echo 'secondary';
                            }
                        ?>">
                            <?php echo strtoupper($booking['status']); ?>
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="info-grid">
                        <!-- Customer Information -->
                        <div class="info-section">
                            <h3>Customer Information</h3>
                            <div class="info-item">
                                <span class="info-label">Full Name</span>
                                <div class="info-value"><?php echo $booking['first_name'] . ' ' . $booking['last_name']; ?></div>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Email</span>
                                <div class="info-value"><?php echo $booking['email']; ?></div>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Phone</span>
                                <div class="info-value"><?php echo $booking['phone'] ?: 'N/A'; ?></div>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Contact Email</span>
                                <div class="info-value"><?php echo $contact_info['email'] ?? 'N/A'; ?></div>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Contact Phone</span>
                                <div class="info-value"><?php echo $contact_info['phone'] ?? 'N/A'; ?></div>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Address</span>
                                <div class="info-value"><?php echo $contact_info['address'] ?? 'N/A'; ?></div>
                            </div>
                        </div>

                        <!-- Booking Information -->
                        <div class="info-section">
                            <h3>Booking Information</h3>
                            <div class="info-item">
                                <span class="info-label">Booking Reference</span>
                                <div class="info-value" style="font-family: 'Courier New', monospace;"><?php echo $booking['booking_reference']; ?></div>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Ticket Number</span>
                                <div class="info-value" style="font-family: 'Courier New', monospace;"><?php echo $booking['ticket_number'] ?: 'Not issued'; ?></div>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Booking Date</span>
                                <div class="info-value"><?php echo date('F j, Y g:i A', strtotime($booking['created_at'])); ?></div>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Total Amount</span>
                                <div class="info-value" style="font-size: 1.2rem; font-weight: bold; color: #007bff;">
                                    ₦<?php echo number_format($booking['total_amount'], 2); ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Payment Status</span>
                                <div class="info-value">
                                    <span class="badge badge-<?php echo $booking['payment_status'] === 'paid' ? 'success' : 'warning'; ?>">
                                        <?php echo strtoupper($booking['payment_status']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Payment Method</span>
                                <div class="info-value"><?php echo $booking['payment_method'] ? ucfirst($booking['payment_method']) : 'N/A'; ?></div>
                            </div>
                            <?php if ($booking['payment_reference']): ?>
                            <div class="info-item">
                                <span class="info-label">Payment Reference</span>
                                <div class="info-value" style="font-family: 'Courier New', monospace;"><?php echo $booking['payment_reference']; ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Flight Information -->
                    <div class="info-section">
                        <h3>Flight Information</h3>
                        <div class="flight-summary">
                            <div class="flight-route">
                                <div class="airport">
                                    <div class="airport-code"><?php echo $first_segment['departure']['iataCode']; ?></div>
                                    <div class="airport-name">Departure</div>
                                    <div class="flight-time"><?php echo date('H:i', strtotime($first_segment['departure']['at'])); ?></div>
                                    <div class="flight-date"><?php echo date('M j, Y', strtotime($first_segment['departure']['at'])); ?></div>
                                </div>
                                
                                <div class="flight-duration">
                                    <div>Flight Duration</div>
                                    <div style="font-weight: bold; margin: 0.5rem 0;"><?php echo substr($itinerary['duration'], 2); ?></div>
                                    <div class="duration-line"></div>
                                    <div><?php echo $first_segment['carrierCode']; ?></div>
                                </div>
                                
                                <div class="airport">
                                    <div class="airport-code"><?php echo $last_segment['arrival']['iataCode']; ?></div>
                                    <div class="airport-name">Arrival</div>
                                    <div class="flight-time"><?php echo date('H:i', strtotime($last_segment['arrival']['at'])); ?></div>
                                    <div class="flight-date"><?php echo date('M j, Y', strtotime($last_segment['arrival']['at'])); ?></div>
                                </div>
                            </div>
                            
                            <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #dee2e6;">
                                <div class="info-item">
                                    <span class="info-label">Airline</span>
                                    <span class="info-value"><?php echo $first_segment['carrierCode']; ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Flight Class</span>
                                    <span class="info-value"><?php echo $flight_data['travelerPricings'][0]['fareDetailsBySegment'][0]['cabin']; ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Number of Segments</span>
                                    <span class="info-value"><?php echo count($itinerary['segments']); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Passenger Information -->
                    <div class="info-section">
                        <h3>Passenger Information</h3>
                        <div class="passenger-list">
                            <?php if (!empty($passenger_info)): ?>
                                <?php foreach ($passenger_info as $index => $passenger): ?>
                                <div class="passenger-card">
                                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                                        <div>
                                            <div class="info-label">Passenger <?php echo $index + 1; ?></div>
                                            <div class="info-value"><?php echo $passenger['first_name'] . ' ' . $passenger['last_name']; ?></div>
                                        </div>
                                        <div>
                                            <div class="info-label">Date of Birth</div>
                                            <div class="info-value"><?php echo date('M j, Y', strtotime($passenger['dob'])); ?></div>
                                        </div>
                                        <div>
                                            <div class="info-label">Gender</div>
                                            <div class="info-value"><?php echo ucfirst($passenger['gender']); ?></div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="passenger-card">
                                    <div class="info-value" style="text-align: center; color: #666;">
                                        No passenger information available
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="info-section">
                        <h3>Actions</h3>
                        <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                            <a href="../print-ticket.php?booking_id=<?php echo $booking['id']; ?>" target="_blank" class="btn btn-primary">Print Ticket</a>
                            <a href="bookings.php" class="btn" style="background: #6c757d; color: white;">Back to Bookings</a>
                            
                            <?php if ($booking['status'] === 'pending'): ?>
                                <a href="bookings.php?action=confirm&id=<?php echo $booking['id']; ?>" class="btn btn-success">Confirm Booking</a>
                            <?php endif; ?>
                            
                            <?php if ($booking['status'] !== 'cancelled'): ?>
                                <a href="bookings.php?action=cancel&id=<?php echo $booking['id']; ?>" class="btn btn-warning" onclick="return confirm('Cancel this booking?')">Cancel Booking</a>
                            <?php endif; ?>
                            
                            <a href="bookings.php?action=delete&id=<?php echo $booking['id']; ?>" class="btn btn-danger" onclick="return confirm('Delete this booking?')">Delete Booking</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>