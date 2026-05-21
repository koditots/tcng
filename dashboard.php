<?php
// dashboard.php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$page_title = "Dashboard";
$user_id = $_SESSION['user_id'];

// Check for application success message
$application_success = '';
if (isset($_SESSION['application_success'])) {
    $application_success = $_SESSION['application_success'];
    unset($_SESSION['application_success']);
}

// Get user bookings
$stmt = $pdo->prepare("SELECT * FROM flight_bookings WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$user_id]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user visa applications
$stmt = $pdo->prepare("SELECT * FROM visa_applications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$user_id]);
$visa_applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user notifications
$notifications = getUserNotifications($pdo, $user_id, 5);

// Get visa application stats
$visa_stats = [
    'total' => count($visa_applications),
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
    'payment_pending' => 0
];

foreach ($visa_applications as $app) {
    switch ($app['status']) {
        case 'pending_payment': $visa_stats['payment_pending']++; break;
        case 'approved': $visa_stats['approved']++; break;
        case 'rejected': $visa_stats['rejected']++; break;
        case 'pending_review': $visa_stats['pending']++; break;
    }
}

require_once 'includes/header.php';
?>

<div class="container">
    <h1 style="margin: 2rem 0; color: #333;">Welcome, <?php echo $_SESSION['user_name']; ?>!</h1>
    
    <?php if ($application_success): ?>
        <div style="background: #d4edda; color: #155724; padding: 1rem; border-radius: 8px; margin-bottom: 2rem; border: 1px solid #c3e6cb;">
            <strong>Success!</strong> <?php echo $application_success; ?>
        </div>
    <?php endif; ?>
    
    <!-- Quick Stats -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
        <!-- Flight Bookings Stats -->
        <div style="background: white; padding: 1.5rem; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); text-align: center;">
            <div style="font-size: 2rem; color: #007bff; margin-bottom: 0.5rem;">✈️</div>
            <h3 style="color: #333; margin-bottom: 0.5rem; font-size: 1rem;">Flight Bookings</h3>
            <p style="font-size: 1.8rem; font-weight: bold; color: #333;"><?php echo count($bookings); ?></p>
        </div>
        
        <div style="background: white; padding: 1.5rem; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); text-align: center;">
            <div style="font-size: 2rem; color: #28a745; margin-bottom: 0.5rem;">✅</div>
            <h3 style="color: #333; margin-bottom: 0.5rem; font-size: 1rem;">Confirmed</h3>
            <p style="font-size: 1.8rem; font-weight: bold; color: #28a745;">
                <?php 
                $confirmed = array_filter($bookings, function($booking) {
                    return $booking['status'] === 'confirmed' || $booking['status'] === 'paid';
                });
                echo count($confirmed);
                ?>
            </p>
        </div>
        
        <div style="background: white; padding: 1.5rem; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); text-align: center;">
            <div style="font-size: 2rem; color: #ffc107; margin-bottom: 0.5rem;">⏳</div>
            <h3 style="color: #333; margin-bottom: 0.5rem; font-size: 1rem;">Pending</h3>
            <p style="font-size: 1.8rem; font-weight: bold; color: #ffc107;">
                <?php 
                $pending = array_filter($bookings, function($booking) {
                    return $booking['status'] === 'pending';
                });
                echo count($pending);
                ?>
            </p>
        </div>
        
        <!-- Visa Application Stats -->
        <div style="background: white; padding: 1.5rem; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); text-align: center;">
            <div style="font-size: 2rem; color: #6f42c1; margin-bottom: 0.5rem;">🛂</div>
            <h3 style="color: #333; margin-bottom: 0.5rem; font-size: 1rem;">Visa Applications</h3>
            <p style="font-size: 1.8rem; font-weight: bold; color: #6f42c1;"><?php echo $visa_stats['total']; ?></p>
        </div>
        
        <div style="background: white; padding: 1.5rem; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); text-align: center;">
            <div style="font-size: 2rem; color: #17a2b8; margin-bottom: 0.5rem;">💳</div>
            <h3 style="color: #333; margin-bottom: 0.5rem; font-size: 1rem;">Payment Pending</h3>
            <p style="font-size: 1.8rem; font-weight: bold; color: #17a2b8;"><?php echo $visa_stats['payment_pending']; ?></p>
        </div>
        
        <div style="background: white; padding: 1.5rem; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); text-align: center;">
            <div style="font-size: 2rem; color: #28a745; margin-bottom: 0.5rem;">👍</div>
            <h3 style="color: #333; margin-bottom: 0.5rem; font-size: 1rem;">Approved</h3>
            <p style="font-size: 1.8rem; font-weight: bold; color: #28a745;"><?php echo $visa_stats['approved']; ?></p>
        </div>
    </div>
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
        <!-- Recent Bookings -->
        <div style="background: white; padding: 1.5rem; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
            <h2 style="color: #333; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                ✈️ Recent Bookings
            </h2>
            
            <?php if (!empty($bookings)): ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #f8f9fa;">
                                <th style="padding: 0.75rem; text-align: left; border-bottom: 1px solid #dee2e6;">Reference</th>
                                <th style="padding: 0.75rem; text-align: left; border-bottom: 1px solid #dee2e6;">Date</th>
                                <th style="padding: 0.75rem; text-align: left; border-bottom: 1px solid #dee2e6;">Amount</th>
                                <th style="padding: 0.75rem; text-align: left; border-bottom: 1px solid #dee2e6;">Status</th>
                                <th style="padding: 0.75rem; text-align: left; border-bottom: 1px solid #dee2e6;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $booking): ?>
                                <tr>
                                    <td style="padding: 0.75rem; border-bottom: 1px solid #dee2e6;"><?php echo $booking['booking_reference']; ?></td>
                                    <td style="padding: 0.75rem; border-bottom: 1px solid #dee2e6;"><?php echo date('M j, Y', strtotime($booking['created_at'])); ?></td>
                                    <td style="padding: 0.75rem; border-bottom: 1px solid #dee2e6;">₦<?php echo number_format($booking['total_amount'], 2); ?></td>
                                    <td style="padding: 0.75rem; border-bottom: 1px solid #dee2e6;">
                                        <span style="padding: 0.25rem 0.5rem; border-radius: 15px; font-size: 0.8rem; font-weight: bold; 
                                            background: <?php 
                                                switch($booking['status']) {
                                                    case 'confirmed': echo '#d1edff'; break;
                                                    case 'paid': echo '#d4edda'; break;
                                                    case 'pending': echo '#fff3cd'; break;
                                                    default: echo '#f8d7da';
                                                }
                                            ?>; 
                                            color: <?php 
                                                switch($booking['status']) {
                                                    case 'confirmed': echo '#004085'; break;
                                                    case 'paid': echo '#155724'; break;
                                                    case 'pending': echo '#856404'; break;
                                                    default: echo '#721c24';
                                                }
                                            ?>;">
                                            <?php echo ucfirst($booking['status']); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 0.75rem; border-bottom: 1px solid #dee2e6;">
                                        <a href="booking-details.php?id=<?php echo $booking['id']; ?>" class="btn" style="padding: 0.25rem 0.5rem; background: #007bff; color: white; text-decoration: none; border-radius: 3px; font-size: 0.8rem;">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div style="text-align: center; margin-top: 1rem;">
                    <a href="my-bookings.php" class="btn btn-primary">View All Bookings</a>
                </div>
            <?php else: ?>
                <p style="text-align: center; color: #666; padding: 2rem;">No bookings found.</p>
                <div style="text-align: center;">
                    <a href="flights.php" class="btn btn-primary">Book Your First Flight</a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Visa Applications -->
        <div style="background: white; padding: 1.5rem; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
            <h2 style="color: #333; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                🛂 Visa Applications
            </h2>
            
            <?php if (!empty($visa_applications)): ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #f8f9fa;">
                                <th style="padding: 0.75rem; text-align: left; border-bottom: 1px solid #dee2e6;">App Number</th>
                                <th style="padding: 0.75rem; text-align: left; border-bottom: 1px solid #dee2e6;">Destination</th>
                                <th style="padding: 0.75rem; text-align: left; border-bottom: 1px solid #dee2e6;">Date</th>
                                <th style="padding: 0.75rem; text-align: left; border-bottom: 1px solid #dee2e6;">Status</th>
                                <th style="padding: 0.75rem; text-align: left; border-bottom: 1px solid #dee2e6;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($visa_applications as $application): ?>
                                <tr>
                                    <td style="padding: 0.75rem; border-bottom: 1px solid #dee2e6; font-size: 0.9rem;"><?php echo $application['application_number']; ?></td>
                                    <td style="padding: 0.75rem; border-bottom: 1px solid #dee2e6;"><?php echo $application['destination_country']; ?></td>
                                    <td style="padding: 0.75rem; border-bottom: 1px solid #dee2e6;"><?php echo date('M j, Y', strtotime($application['created_at'])); ?></td>
                                    <td style="padding: 0.75rem; border-bottom: 1px solid #dee2e6;">
                                        <span style="padding: 0.25rem 0.5rem; border-radius: 15px; font-size: 0.8rem; font-weight: bold; 
                                            background: <?php 
                                                switch($application['status']) {
                                                    case 'approved': echo '#d4edda'; break;
                                                    case 'rejected': echo '#f8d7da'; break;
                                                    case 'pending_review': echo '#fff3cd'; break;
                                                    case 'pending_payment': echo '#d1edff'; break;
                                                    default: echo '#e9ecef';
                                                }
                                            ?>; 
                                            color: <?php 
                                                switch($application['status']) {
                                                    case 'approved': echo '#155724'; break;
                                                    case 'rejected': echo '#721c24'; break;
                                                    case 'pending_review': echo '#856404'; break;
                                                    case 'pending_payment': echo '#004085'; break;
                                                    default: echo '#495057';
                                                }
                                            ?>;">
                                            <?php echo ucfirst(str_replace('_', ' ', $application['status'])); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 0.75rem; border-bottom: 1px solid #dee2e6;">
                                        <div style="display: flex; gap: 0.25rem; flex-wrap: wrap;">
                                            <a href="visa-application-details.php?id=<?php echo $application['id']; ?>" class="btn" style="padding: 0.25rem 0.5rem; background: #007bff; color: white; text-decoration: none; border-radius: 3px; font-size: 0.8rem;">View</a>
                                            <?php if ($application['status'] == 'pending_payment'): ?>
                                                <a href="visa-payment.php?application_id=<?php echo $application['id']; ?>" class="btn" style="padding: 0.25rem 0.5rem; background: #28a745; color: white; text-decoration: none; border-radius: 3px; font-size: 0.8rem;">Pay</a>
                                            <?php endif; ?>
                                            <?php if (in_array($application['status'], ['rejected', 'cancelled'])): ?>
                                                <a href="visa-application.php?reapply=<?php echo $application['id']; ?>" class="btn" style="padding: 0.25rem 0.5rem; background: #17a2b8; color: white; text-decoration: none; border-radius: 3px; font-size: 0.8rem;">Reapply</a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div style="text-align: center; margin-top: 1rem;">
                    <a href="my-visa-applications.php" class="btn btn-primary">View All Applications</a>
                </div>
            <?php else: ?>
                <p style="text-align: center; color: #666; padding: 2rem;">No visa applications found.</p>
                <div style="text-align: center;">
                    <a href="visa-assessment.php" class="btn btn-primary">Start Visa Application</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Notifications and Quick Actions -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
        <!-- Notifications -->
        <div style="background: white; padding: 1.5rem; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
            <h2 style="color: #333; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                🔔 Notifications
            </h2>
            
            <?php if (!empty($notifications)): ?>
                <div style="max-height: 300px; overflow-y: auto;">
                    <?php foreach ($notifications as $notification): ?>
                        <div style="padding: 0.75rem; border-bottom: 1px solid #f8f9fa; background: <?php echo $notification['is_read'] ? 'transparent' : '#f8f9fa'; ?>;">
                            <div style="font-weight: bold; color: #333; margin-bottom: 0.25rem; font-size: 0.9rem;"><?php echo $notification['title']; ?></div>
                            <div style="color: #666; font-size: 0.85rem; margin-bottom: 0.5rem;"><?php echo $notification['message']; ?></div>
                            <div style="color: #999; font-size: 0.75rem;"><?php echo date('M j, g:i A', strtotime($notification['created_at'])); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div style="text-align: center; margin-top: 1rem;">
                    <a href="notifications.php" class="btn btn-primary">View All Notifications</a>
                </div>
            <?php else: ?>
                <p style="text-align: center; color: #666; padding: 1rem;">No notifications.</p>
            <?php endif; ?>
        </div>
        
        <!-- Quick Actions -->
        <div style="background: white; padding: 1.5rem; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
            <h2 style="color: #333; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                ⚡ Quick Actions
            </h2>
            <div style="display: grid; grid-template-columns: 1fr; gap: 0.75rem;">
                <a href="flights.php" class="btn btn-primary" style="text-align: center; padding: 0.75rem; display: flex; align-items: center; justify-content: center; gap: 0.5rem; text-decoration: none;">
                    ✈️ Book Flight
                </a>
                <a href="visa-assessment.php" class="btn" style="text-align: center; padding: 0.75rem; background: #6f42c1; color: white; display: flex; align-items: center; justify-content: center; gap: 0.5rem; text-decoration: none;">
                    🛂 Apply for Visa
                </a>
                <a href="my-bookings.php" class="btn" style="text-align: center; padding: 0.75rem; background: #6c757d; color: white; display: flex; align-items: center; justify-content: center; gap: 0.5rem; text-decoration: none;">
                    📋 My Bookings
                </a>
                <a href="my-visa-applications.php" class="btn" style="text-align: center; padding: 0.75rem; background: #17a2b8; color: white; display: flex; align-items: center; justify-content: center; gap: 0.5rem; text-decoration: none;">
                    📄 My Visa Applications
                </a>
                <a href="profile.php" class="btn" style="text-align: center; padding: 0.75rem; background: #fd7e14; color: white; display: flex; align-items: center; justify-content: center; gap: 0.5rem; text-decoration: none;">
                    👤 My Profile
                </a>
            </div>
        </div>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>