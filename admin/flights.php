<?php
// admin/flights.php
require_once '../config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

$page_title = "Flight Management";

// Get filters from URL
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : 'all';
$date_from = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : '';
$airline_filter = isset($_GET['airline']) ? sanitize($_GET['airline']) : 'all';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Build query for flight bookings
$query = "SELECT fb.*, 
          u.first_name, u.last_name, u.email,
          COUNT(p.id) as payment_count,
          MAX(p.status) as payment_status
          FROM flight_bookings fb
          LEFT JOIN users u ON fb.user_id = u.id
          LEFT JOIN payments p ON fb.id = p.booking_id
          WHERE 1=1";
$params = [];

// Apply filters
if ($status_filter !== 'all') {
    $query .= " AND fb.status = ?";
    $params[] = $status_filter;
}

if (!empty($date_from)) {
    $query .= " AND DATE(fb.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND DATE(fb.created_at) <= ?";
    $params[] = $date_to;
}

if (!empty($search)) {
    $query .= " AND (fb.booking_reference LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$query .= " GROUP BY fb.id ORDER BY fb.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get flight statistics
$stats_query = "SELECT 
    COUNT(*) as total_bookings,
    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_bookings,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings,
    SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_bookings,
    SUM(CASE WHEN status IN ('confirmed', 'paid') THEN total_amount ELSE 0 END) as total_revenue,
    AVG(CASE WHEN status IN ('confirmed', 'paid') THEN total_amount ELSE NULL END) as average_booking_value
    FROM flight_bookings WHERE 1=1";

$stats_params = [];

if ($status_filter !== 'all') {
    $stats_query .= " AND status = ?";
    $stats_params[] = $status_filter;
}

if (!empty($date_from)) {
    $stats_query .= " AND DATE(created_at) >= ?";
    $stats_params[] = $date_from;
}

if (!empty($date_to)) {
    $stats_query .= " AND DATE(created_at) <= ?";
    $stats_params[] = $date_to;
}

$stats_stmt = $pdo->prepare($stats_query);
$stats_stmt->execute($stats_params);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get unique airlines for filter
$airlines_stmt = $pdo->query("
    SELECT DISTINCT 
    JSON_UNQUOTE(JSON_EXTRACT(flight_data, '$.itineraries[0].segments[0].carrierCode')) as airline_code
    FROM flight_bookings 
    WHERE flight_data IS NOT NULL 
    AND JSON_EXTRACT(flight_data, '$.itineraries[0].segments[0].carrierCode') IS NOT NULL
    ORDER BY airline_code
");
$airlines = $airlines_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle booking actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $booking_id = intval($_POST['booking_id'] ?? 0);
    
    if ($booking_id && in_array($action, ['confirm', 'cancel', 'delete'])) {
        try {
            $pdo->beginTransaction();
            
            // Get booking details before update for email
            $booking_stmt = $pdo->prepare("SELECT fb.*, u.first_name, u.last_name, u.email FROM flight_bookings fb LEFT JOIN users u ON fb.user_id = u.id WHERE fb.id = ?");
            $booking_stmt->execute([$booking_id]);
            $booking_details = $booking_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$booking_details) {
                throw new Exception("Booking not found");
            }
            
            $user_email = $booking_details['email'];
            $user_name = $booking_details['first_name'] . ' ' . $booking_details['last_name'];
            $booking_reference = $booking_details['booking_reference'];
            $old_status = $booking_details['status'];
            
            switch ($action) {
                case 'confirm':
                    $stmt = $pdo->prepare("UPDATE flight_bookings SET status = 'confirmed', updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$booking_id]);
                    $new_status = 'confirmed';
                    $_SESSION['success'] = "Booking confirmed successfully!";
                    break;
                    
                case 'cancel':
                    $stmt = $pdo->prepare("UPDATE flight_bookings SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$booking_id]);
                    $new_status = 'cancelled';
                    $_SESSION['success'] = "Booking cancelled successfully!";
                    break;
                    
                case 'delete':
                    // First delete related payments
                    $delete_payments = $pdo->prepare("DELETE FROM payments WHERE booking_id = ?");
                    $delete_payments->execute([$booking_id]);
                    
                    // Then delete the booking
                    $delete_booking = $pdo->prepare("DELETE FROM flight_bookings WHERE id = ?");
                    $delete_booking->execute([$booking_id]);
                    
                    $_SESSION['success'] = "Booking and associated records deleted successfully!";
                    break;
            }
            
            $pdo->commit();
            
            // Send status update email for confirm and cancel actions
            if (in_array($action, ['confirm', 'cancel']) && !empty($user_email)) {
                $email_sent = sendBookingStatusEmail($pdo, $booking_details, $old_status, $new_status);
                
                if ($email_sent) {
                    $_SESSION['success'] .= " Status update email sent to customer.";
                } else {
                    $_SESSION['success'] .= " (Email notification failed)";
                }
            }
            
            header("Location: flights.php?" . http_build_query($_GET));
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Error processing request: " . $e->getMessage();
        }
    }
}

// Function to send booking status update email
function sendBookingStatusEmail($pdo, $booking, $old_status, $new_status) {
    $user_email = $booking['email'];
    $user_name = $booking['first_name'] . ' ' . $booking['last_name'];
    $booking_reference = $booking['booking_reference'];
    
    // Get site settings
    $site_name = getSiteSetting($pdo, 'site_name');
    $site_url = SITE_URL;
    $support_email = getSiteSetting($pdo, 'support_email');
    
    // Parse flight data for email content
    $flight_data = json_decode($booking['flight_data'] ?? '{}', true);
    $itinerary = $flight_data['itineraries'][0] ?? [];
    $first_segment = $itinerary['segments'][0] ?? [];
    $last_segment = end($itinerary['segments']) ?? [];
    
    $departure_city = $first_segment['departure']['iataCode'] ?? 'N/A';
    $arrival_city = $last_segment['arrival']['iataCode'] ?? 'N/A';
    $departure_date = !empty($first_segment['departure']['at']) ? date('F j, Y', strtotime($first_segment['departure']['at'])) : 'N/A';
    $airline_code = $first_segment['carrierCode'] ?? 'N/A';
    
    $status_messages = [
        'confirmed' => [
            'subject' => "Booking Confirmed - $booking_reference",
            'title' => 'Booking Confirmed!',
            'message' => 'Your flight booking has been confirmed and is now active.',
            'color' => '#28a745',
            'icon' => '✅'
        ],
        'cancelled' => [
            'subject' => "Booking Cancelled - $booking_reference",
            'title' => 'Booking Cancelled',
            'message' => 'Your flight booking has been cancelled as requested.',
            'color' => '#dc3545',
            'icon' => '❌'
        ]
    ];
    
    $status_info = $status_messages[$new_status] ?? [
        'subject' => "Booking Status Updated - $booking_reference",
        'title' => 'Booking Status Updated',
        'message' => "Your booking status has been changed from $old_status to $new_status.",
        'color' => '#007bff',
        'icon' => '📝'
    ];
    
    $subject = $status_info['subject'];
    
    $message = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #007bff, #0056b3); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .status-banner { background: {$status_info['color']}; color: white; padding: 20px; text-align: center; margin: 20px 0; border-radius: 5px; }
                .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
                .booking-details { background: white; padding: 20px; border-radius: 5px; margin: 20px 0; border-left: 4px solid {$status_info['color']}; }
                .flight-info { display: grid; grid-template-columns: 1fr auto 1fr; gap: 15px; align-items: center; margin: 15px 0; }
                .flight-route { text-align: center; font-weight: bold; font-size: 18px; }
                .flight-date { text-align: center; color: #666; }
                .button { display: inline-block; background: #007bff; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
                .footer { text-align: center; margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 14px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>$site_name</h1>
                    <p>Flight Booking Status Update</p>
                </div>
                
                <div class='content'>
                    <div class='status-banner'>
                        <h2>{$status_info['icon']} {$status_info['title']}</h2>
                        <p>{$status_info['message']}</p>
                    </div>
                    
                    <h3>Hello, {$booking['first_name']}!</h3>
                    <p>This email confirms that your flight booking status has been updated.</p>
                    
                    <div class='booking-details'>
                        <h4>Booking Details:</h4>
                        <p><strong>Booking Reference:</strong> $booking_reference</p>
                        <p><strong>Previous Status:</strong> " . ucfirst($old_status) . "</p>
                        <p><strong>Current Status:</strong> " . ucfirst($new_status) . "</p>
                        <p><strong>Update Date:</strong> " . date('F j, Y g:i A') . "</p>
                        
                        <div class='flight-info'>
                            <div style='text-align: center;'>
                                <div style='font-size: 24px; font-weight: bold;'>$departure_city</div>
                                <div style='color: #666;'>Departure</div>
                            </div>
                            <div style='text-align: center;'>→</div>
                            <div style='text-align: center;'>
                                <div style='font-size: 24px; font-weight: bold;'>$arrival_city</div>
                                <div style='color: #666;'>Arrival</div>
                            </div>
                        </div>
                        
                        <div style='text-align: center; margin: 15px 0;'>
                            <div class='flight-date'>$departure_date • $airline_code</div>
                        </div>
                        
                        <p><strong>Amount:</strong> ₦" . number_format($booking['total_amount'] ?? 0, 2) . "</p>
                        <p><strong>Passengers:</strong> " . ($booking['passenger_count'] ?? 1) . "</p>
                    </div>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='$site_url/booking-details.php?reference=$booking_reference' class='button'>View Booking Details</a>
                    </div>
                    
                    <p><strong>Need Assistance?</strong></p>
                    <p>If you have any questions about this status update, please contact our support team:</p>
                    <p>📧 <a href='mailto:$support_email'>$support_email</a></p>
                    
                    <p>Thank you for choosing $site_name!</p>
                    <p><strong>The $site_name Team</strong></p>
                </div>
                
                <div class='footer'>
                    <p>&copy; " . date('Y') . " $site_name. All rights reserved.</p>
                    <p><a href='$site_url'>$site_url</a> | <a href='mailto:$support_email'>Contact Support</a></p>
                </div>
            </div>
        </body>
        </html>
    ";
    
    // Try to send using template system first
    $template_sent = sendTemplateEmail($user_email, 'booking_status_update', [
        'first_name' => $booking['first_name'],
        'last_name' => $booking['last_name'],
        'booking_reference' => $booking_reference,
        'old_status' => ucfirst($old_status),
        'new_status' => ucfirst($new_status),
        'departure_city' => $departure_city,
        'arrival_city' => $arrival_city,
        'departure_date' => $departure_date,
        'airline_code' => $airline_code,
        'amount' => '₦' . number_format($booking['total_amount'] ?? 0, 2),
        'passenger_count' => $booking['passenger_count'] ?? 1,
        'update_date' => date('F j, Y g:i A'),
        'site_name' => $site_name,
        'site_url' => $site_url,
        'support_email' => $support_email,
        'booking_url' => $site_url . '/booking-details.php?reference=' . $booking_reference
    ]);
    
    if ($template_sent) {
        return true;
    }
    
    // Fallback to direct email
    return sendEmail($user_email, $subject, $message, true);
}

// Check for success/error messages from session
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo getSiteSetting($pdo, 'site_name'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            background: #f8f9fa;
            display: flex;
            font-size: 0.875rem;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 0;
            min-height: 100vh;
        }
        
        .top-bar {
            background: white;
            padding: 0.75rem 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .top-bar h1 {
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .content {
            padding: 1.5rem;
        }
        
        /* Cards */
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        
        .card-header {
            padding: 1rem 1.25rem;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-header h3 {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .card-body {
            padding: 1.25rem;
        }
        
        /* Statistics Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solid #007bff;
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #007bff;
            line-height: 1.2;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.8rem;
            margin-top: 0.25rem;
        }
        
        /* Filters */
        .filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 0.875rem;
            margin-bottom: 1rem;
            padding: 1.25rem;
            background: #f8f9fa;
            border-radius: 6px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-group label {
            font-weight: 600;
            margin-bottom: 0.375rem;
            color: #333;
            font-size: 0.8rem;
        }
        
        .filter-group select, .filter-group input {
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.8rem;
        }
        
        .filter-actions {
            display: flex;
            gap: 0.5rem;
            align-items: end;
        }
        
        /* Buttons */
        .btn {
            padding: 0.5rem 0.75rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.375rem;
            font-size: 0.8rem;
            transition: all 0.3s;
            font-weight: 500;
            line-height: 1.2;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0056b3;
            transform: translateY(-1px);
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #1e7e34;
            transform: translateY(-1px);
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-1px);
        }
        
        .btn-warning {
            background: #ffc107;
            color: #333;
        }
        
        .btn-warning:hover {
            background: #e0a800;
            transform: translateY(-1px);
        }
        
        .btn-info {
            background: #17a2b8;
            color: white;
        }
        
        .btn-info:hover {
            background: #138496;
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #545b62;
            transform: translateY(-1px);
        }
        
        .btn-sm {
            padding: 0.375rem 0.625rem;
            font-size: 0.75rem;
        }
        
        .btn-xs {
            padding: 0.25rem 0.5rem;
            font-size: 0.7rem;
            gap: 0.25rem;
        }
        
        /* Action buttons in one line */
        .action-buttons {
            display: flex;
            gap: 0.25rem;
            flex-wrap: nowrap;
        }
        
        /* Alerts */
        .alert {
            padding: 0.875rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            font-size: 0.8rem;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Status badges */
        .status-badge {
            padding: 0.2rem 0.4rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: bold;
            display: inline-block;
        }
        
        .status-confirmed {
            background: #d4edda;
            color: #155724;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-paid {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        /* Tables */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
        }
        
        th, td {
            padding: 0.625rem;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
            line-height: 1.3;
        }
        
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
            font-size: 0.75rem;
            text-transform: uppercase;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        /* Quick actions */
        .quick-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }
        
        /* Export buttons */
        .export-buttons {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        
        /* Search box */
        .search-box {
            display: flex;
            gap: 0.5rem;
        }
        
        .search-box input {
            flex: 1;
        }
        
        /* Payment indicator */
        .payment-indicator { 
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 5px;
        }
        
        .payment-success { background: #28a745; }
        .payment-pending { background: #ffc107; }
        .payment-none { background: #6c757d; }
        
        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #666;
        }
        
        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        /* Responsive design */
        @media (max-width: 1200px) {
            .main-content {
                margin-left: 0;
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
            
            .top-bar {
                padding: 0.5rem 1rem;
            }
            
            .top-bar h1 {
                font-size: 1.25rem;
            }
            
            .content {
                padding: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .filters {
                grid-template-columns: 1fr;
            }
            
            .filter-actions {
                grid-column: 1;
                justify-content: stretch;
            }
            
            .filter-actions .btn {
                flex: 1;
                text-align: center;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 0.125rem;
            }
            
            .btn-xs {
                width: 100%;
                justify-content: center;
            }
            
            .export-buttons, .quick-actions {
                flex-direction: column;
            }
            
            .export-buttons .btn, .quick-actions .btn {
                width: 100%;
                justify-content: center;
            }
            
            table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .card-body {
                padding: 1rem;
            }
            
            .card-header {
                padding: 0.75rem 1rem;
                flex-direction: column;
                gap: 0.5rem;
                align-items: flex-start;
            }
            
            .search-box {
                flex-direction: column;
            }
        }
        
        /* Print styles */
        @media print {
            .sidebar, .top-bar, .filters, .export-buttons, .action-buttons, .quick-actions {
                display: none !important;
            }
            .main-content {
                margin-left: 0 !important;
            }
            .card {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
            }
        }
        
        .btn-outline {
            background: transparent;
            border: 1px solid #007bff;
            color: #007bff;
        }
        
        .btn-outline:hover {
            background: #007bff;
            color: white;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <!-- Include Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-bar">
            <div>
                <a href="dashboard.php" class="btn btn-secondary btn-sm" style="text-decoration: none;">
                    <i class="fas fa-arrow-left"></i> Dashboard
                </a>
            </div>
            <h1>Flight Management</h1>
            <div>
                <span>Welcome, <?php echo $_SESSION['user_name']; ?></span>
            </div>
        </div>

        <div class="content">
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <a href="?status=pending" class="btn btn-warning btn-sm">
                    <i class="fas fa-clock"></i> Pending (<?php echo $stats['pending_bookings'] ?? 0; ?>)
                </a>
                <a href="?status=confirmed" class="btn btn-success btn-sm">
                    <i class="fas fa-check"></i> Confirmed (<?php echo $stats['confirmed_bookings'] ?? 0; ?>)
                </a>
                <a href="?status=paid" class="btn btn-primary btn-sm">
                    <i class="fas fa-credit-card"></i> Paid (<?php echo $stats['paid_bookings'] ?? 0; ?>)
                </a>
                <a href="flights.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-sync"></i> Reset Filters
                </a>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_bookings'] ?? 0; ?></div>
                    <div class="stat-label">Total Bookings</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['confirmed_bookings'] ?? 0; ?></div>
                    <div class="stat-label">Confirmed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['pending_bookings'] ?? 0; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['cancelled_bookings'] ?? 0; ?></div>
                    <div class="stat-label">Cancelled</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['paid_bookings'] ?? 0; ?></div>
                    <div class="stat-label">Paid</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">₦<?php echo number_format($stats['total_revenue'] ?? 0, 0); ?></div>
                    <div class="stat-label">Total Revenue</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card">
                <div class="card-header">
                    <h3>Filter Flight Bookings</h3>
                </div>
                <div class="card-body">
                    <form method="GET" action="">
                        <div class="filters">
                            <div class="filter-group">
                                <label for="status">Booking Status</label>
                                <select id="status" name="status" class="form-control">
                                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                    <option value="paid" <?php echo $status_filter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                    <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label for="airline">Airline</label>
                                <select id="airline" name="airline" class="form-control">
                                    <option value="all" <?php echo $airline_filter === 'all' ? 'selected' : ''; ?>>All Airlines</option>
                                    <?php foreach ($airlines as $airline): ?>
                                        <?php if (!empty($airline['airline_code'])): ?>
                                            <option value="<?php echo $airline['airline_code']; ?>" <?php echo $airline_filter === $airline['airline_code'] ? 'selected' : ''; ?>>
                                                <?php echo $airline['airline_code']; ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label for="date_from">Date From</label>
                                <input type="date" id="date_from" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                            </div>
                            
                            <div class="filter-group">
                                <label for="date_to">Date To</label>
                                <input type="date" id="date_to" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                            </div>
                            
                            <div class="filter-group">
                                <label for="search">Search</label>
                                <div class="search-box">
                                    <input type="text" id="search" name="search" class="form-control" placeholder="Booking Ref, Customer Name, Email..." value="<?php echo $search; ?>">
                                    <button type="submit" class="btn btn-primary">Search</button>
                                </div>
                            </div>
                            
                            <div class="filter-group filter-actions">
                                <button type="submit" class="btn btn-primary">Apply Filters</button>
                                <a href="flights.php" class="btn btn-secondary">Clear</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Export Buttons -->
            <div class="export-buttons">
                <button onclick="exportToCSV()" class="btn btn-success btn-sm">
                    <i class="fas fa-file-csv"></i> Export to CSV
                </button>
                <button onclick="printBookings()" class="btn btn-secondary btn-sm">
                    <i class="fas fa-print"></i> Print Report
                </button>
                <a href="../flights.php" target="_blank" class="btn btn-info btn-sm">
                    <i class="fas fa-plane"></i> Search New Flights
                </a>
            </div>

            <!-- Bookings List -->
            <div class="card">
                <div class="card-header">
                    <h3>Flight Bookings (<?php echo count($bookings); ?>)</h3>
                    <div>
                        <span class="btn btn-secondary btn-sm">Revenue: ₦<?php echo number_format($stats['total_revenue'] ?? 0, 2); ?></span>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($bookings)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">✈️</div>
                            <h3>No Flight Bookings Found</h3>
                            <p>No flight bookings match your current filters.</p>
                            <a href="flights.php" class="btn btn-primary">Clear Filters</a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Booking Details</th>
                                        <th>Customer</th>
                                        <th>Flight Info</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Payment</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bookings as $booking): ?>
                                    <?php
                                        $flight_data = json_decode($booking['flight_data'] ?? '{}', true);
                                        $itinerary = $flight_data['itineraries'][0] ?? [];
                                        $first_segment = $itinerary['segments'][0] ?? [];
                                        $last_segment = end($itinerary['segments']) ?? [];
                                        $airline_code = $first_segment['carrierCode'] ?? 'N/A';
                                        
                                        // Determine payment indicator
                                        $payment_indicator = 'payment-none';
                                        $payment_text = 'No Payment';
                                        if ($booking['payment_count'] > 0) {
                                            if ($booking['payment_status'] === 'success') {
                                                $payment_indicator = 'payment-success';
                                                $payment_text = 'Paid';
                                            } else {
                                                $payment_indicator = 'payment-pending';
                                                $payment_text = 'Pending';
                                            }
                                        }
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo $booking['booking_reference']; ?></strong>
                                            <br>
                                            <small>ID: #<?php echo $booking['id']; ?></small>
                                            <?php if (($booking['passenger_count'] ?? 0) > 1): ?>
                                                <br><small><?php echo $booking['passenger_count'] ?? 1; ?> passengers</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($booking['first_name'])): ?>
                                                <?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?>
                                                <br><small><?php echo htmlspecialchars($booking['email']); ?></small>
                                                <br><small>User ID: #<?php echo $booking['user_id']; ?></small>
                                            <?php else: ?>
                                                <em>User deleted</em>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($first_segment)): ?>
                                                <strong><?php echo $first_segment['departure']['iataCode'] ?? 'N/A'; ?> → <?php echo $last_segment['arrival']['iataCode'] ?? 'N/A'; ?></strong>
                                                <br>
                                                <small><?php echo $airline_code; ?> • <?php echo count($itinerary['segments'] ?? []); ?> segment(s)</small>
                                                <br>
                                                <small><?php echo date('M j, Y', strtotime($first_segment['departure']['at'] ?? '')); ?></small>
                                            <?php else: ?>
                                                <em>Flight data unavailable</em>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong>₦<?php echo number_format($booking['total_amount'] ?? 0, 2); ?></strong>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo $booking['status']; ?>">
                                                <?php echo ucfirst($booking['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="<?php echo $payment_indicator; ?>"></span>
                                            <?php echo $payment_text; ?>
                                            <?php if ($booking['payment_count'] > 0): ?>
                                                <br><small>(<?php echo $booking['payment_count']; ?> payment(s))</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo date('M j, Y', strtotime($booking['created_at'])); ?>
                                            <br><small><?php echo date('g:i A', strtotime($booking['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <?php if ($booking['status'] === 'pending'): ?>
                                                    <form method="POST" action="" style="display: inline;">
                                                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                        <button type="submit" name="action" value="confirm" class="btn btn-success btn-xs">Confirm</button>
                                                        <button type="submit" name="action" value="cancel" class="btn btn-warning btn-xs">Cancel</button>
                                                    </form>
                                                <?php elseif ($booking['status'] === 'confirmed'): ?>
                                                    <form method="POST" action="" style="display: inline;">
                                                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                        <button type="submit" name="action" value="cancel" class="btn btn-warning btn-xs">Cancel</button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <form method="POST" action="" style="display: inline;">
                                                    <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                    <button type="submit" name="action" value="delete" class="btn btn-danger btn-xs" 
                                                            onclick="return confirm('Are you sure you want to delete this booking? This will also delete associated payment records.')">
                                                        Delete
                                                    </button>
                                                </form>
                                                
                                                <a href="booking-details.php?id=<?php echo $booking['id']; ?>" class="btn btn-secondary btn-xs">Details</a>
                                                <a href="../print-ticket.php?booking_id=<?php echo $booking['id']; ?>" target="_blank" class="btn btn-info btn-xs">Ticket</a>
                                                
                                                <?php if ($booking['payment_count'] > 0): ?>
                                                    <a href="payments.php?booking_id=<?php echo $booking['id']; ?>" class="btn btn-primary btn-xs">Payments</a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function exportToCSV() {
            // Get current filter parameters
            const params = new URLSearchParams(window.location.search);
            
            // Create export URL
            const exportUrl = 'export-flights.php?' + params.toString() + '&format=csv';
            
            // Trigger download
            window.location.href = exportUrl;
        }
        
        function printBookings() {
            window.print();
        }
        
        // Auto-apply date range presets
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            const lastWeek = new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
            const firstDayOfMonth = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0];
            
            // Add quick date buttons if needed
            const datePresets = document.createElement('div');
            datePresets.innerHTML = `
                <div style="display: flex; gap: 0.5rem; margin-top: 0.5rem; flex-wrap: wrap;">
                    <button type="button" class="btn btn-sm btn-outline" onclick="setDateRange('${lastWeek}', '${today}')">Last 7 Days</button>
                    <button type="button" class="btn btn-sm btn-outline" onclick="setDateRange('${firstDayOfMonth}', '${today}')">This Month</button>
                    <button type="button" class="btn btn-sm btn-outline" onclick="clearDateRange()">Clear Dates</button>
                </div>
            `;
            document.querySelector('.filters').appendChild(datePresets);
        });
        
        function setDateRange(from, to) {
            document.getElementById('date_from').value = from;
            document.getElementById('date_to').value = to;
            document.querySelector('form').submit();
        }
        
        function clearDateRange() {
            document.getElementById('date_from').value = '';
            document.getElementById('date_to').value = '';
            document.querySelector('form').submit();
        }
        
        // Auto-submit form when status changes
        document.getElementById('status')?.addEventListener('change', function() {
            this.form.submit();
        });
    </script>
</body>
</html>