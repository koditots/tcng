<?php
// admin/user-bookings.php
require_once '../config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

$page_title = "User Bookings";

// Get user ID from URL
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$user_id) {
    redirect('users.php');
}

// Get user details
$stmt = $pdo->prepare("SELECT id, first_name, last_name, email FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $_SESSION['error'] = "User not found.";
    redirect('users.php');
}

// Get booking status filter
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : 'all';
$date_filter = isset($_GET['date']) ? sanitize($_GET['date']) : '';

// Build query for user bookings
$query = "SELECT * FROM flight_bookings WHERE user_id = ?";
$params = [$user_id];

if ($status_filter !== 'all') {
    $query .= " AND status = ?";
    $params[] = $status_filter;
}

if (!empty($date_filter)) {
    $query .= " AND DATE(created_at) = ?";
    $params[] = $date_filter;
}

$query .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get booking statistics
$stats_query = "SELECT 
    COUNT(*) as total_bookings,
    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_bookings,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings,
    SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_bookings,
    SUM(CASE WHEN status IN ('confirmed', 'paid') THEN total_amount ELSE 0 END) as total_revenue
    FROM flight_bookings WHERE user_id = ?";
$stats_stmt = $pdo->prepare($stats_query);
$stats_stmt->execute([$user_id]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Handle booking actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $booking_id = intval($_POST['booking_id'] ?? 0);
    
    if ($booking_id && in_array($action, ['confirm', 'cancel', 'delete'])) {
        try {
            // Start transaction for multiple database operations
            $pdo->beginTransaction();
            
            switch ($action) {
                case 'confirm':
                    $stmt = $pdo->prepare("UPDATE flight_bookings SET status = 'confirmed', updated_at = NOW() WHERE id = ? AND user_id = ?");
                    $stmt->execute([$booking_id, $user_id]);
                    $_SESSION['success'] = "Booking confirmed successfully!";
                    break;
                    
                case 'cancel':
                    $stmt = $pdo->prepare("UPDATE flight_bookings SET status = 'cancelled', updated_at = NOW() WHERE id = ? AND user_id = ?");
                    $stmt->execute([$booking_id, $user_id]);
                    $_SESSION['success'] = "Booking cancelled successfully!";
                    break;
                    
                case 'delete':
                    // First, check if there are related payments
                    $payment_stmt = $pdo->prepare("SELECT COUNT(*) as payment_count FROM payments WHERE booking_id = ?");
                    $payment_stmt->execute([$booking_id]);
                    $payment_count = $payment_stmt->fetch(PDO::FETCH_ASSOC)['payment_count'];
                    
                    if ($payment_count > 0) {
                        // Option 1: Delete related payments first (recommended for complete removal)
                        $delete_payments = $pdo->prepare("DELETE FROM payments WHERE booking_id = ?");
                        $delete_payments->execute([$booking_id]);
                        
                        // Then delete the booking
                        $delete_booking = $pdo->prepare("DELETE FROM flight_bookings WHERE id = ? AND user_id = ?");
                        $delete_booking->execute([$booking_id, $user_id]);
                        
                        $_SESSION['success'] = "Booking and associated payment records deleted successfully!";
                    } else {
                        // No payments, just delete the booking
                        $delete_booking = $pdo->prepare("DELETE FROM flight_bookings WHERE id = ? AND user_id = ?");
                        $delete_booking->execute([$booking_id, $user_id]);
                        $_SESSION['success'] = "Booking deleted successfully!";
                    }
                    break;
            }
            
            // Commit transaction
            $pdo->commit();
            
            // Refresh page to show updated data
            header("Location: user-bookings.php?id=" . $user_id);
            exit;
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            $_SESSION['error'] = "Error processing request: " . $e->getMessage();
        }
    }
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
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; background: #f8f9fa; display: flex; }
        
        .main-content { flex: 1; margin-left: 250px; padding: 0; }
        .top-bar { background: white; padding: 1rem 2rem; box-shadow: 0 2px 10px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .content { padding: 2rem; }
        
        .card { background: white; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); margin-bottom: 2rem; }
        .card-header { padding: 1.5rem; background: #f8f9fa; border-bottom: 1px solid #dee2e6; }
        .card-body { padding: 1.5rem; }
        
        .btn { padding: 0.5rem 1rem; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 0.9rem; }
        .btn-primary { background: #007bff; color: white; }
        .btn-primary:hover { background: #0056b3; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-sm { padding: 0.25rem 0.5rem; font-size: 0.8rem; }
        
        .alert { padding: 1rem; border-radius: 5px; margin-bottom: 1rem; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat-card { background: white; padding: 1.5rem; border-radius: 10px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1); border-left: 4px solid #007bff; }
        .stat-number { font-size: 2rem; font-weight: bold; color: #007bff; margin-bottom: 0.5rem; }
        .stat-label { color: #666; font-size: 0.9rem; }
        
        .filters { display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; align-items: end; }
        .filter-group { display: flex; flex-direction: column; }
        .filter-group label { margin-bottom: 0.5rem; font-weight: bold; color: #333; }
        .form-control { padding: 0.5rem; border: 1px solid #ddd; border-radius: 5px; font-size: 1rem; }
        
        .booking-card { background: white; border-radius: 10px; padding: 1.5rem; margin-bottom: 1rem; box-shadow: 0 2px 10px rgba(0,0,0,0.1); border-left: 4px solid #007bff; }
        .booking-header { display: flex; justify-content: between; align-items: start; margin-bottom: 1rem; }
        .booking-info { flex: 1; }
        .booking-actions { display: flex; gap: 0.5rem; }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem; font-weight: bold; }
        .status-confirmed { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .status-paid { background: #d1ecf1; color: #0c5460; }
        
        .flight-details { display: grid; grid-template-columns: 1fr auto 1fr; gap: 1rem; align-items: center; background: #f8f9fa; padding: 1rem; border-radius: 5px; margin-bottom: 1rem; }
        .flight-route { text-align: center; }
        .flight-time { font-size: 1.2rem; font-weight: bold; }
        .flight-date { color: #666; font-size: 0.9rem; }
        .flight-duration { text-align: center; color: #666; }
        
        .empty-state { text-align: center; padding: 3rem; color: #666; }
        .empty-state-icon { font-size: 4rem; margin-bottom: 1rem; }
        
        .pagination { display: flex; justify-content: center; gap: 0.5rem; margin-top: 2rem; }
        .page-link { padding: 0.5rem 1rem; border: 1px solid #ddd; border-radius: 5px; text-decoration: none; color: #007bff; }
        .page-link:hover { background: #f8f9fa; }
        .page-link.active { background: #007bff; color: white; border-color: #007bff; }
        
        .action-buttons { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        
        .payment-info { 
            background: #e7f3ff; 
            padding: 0.5rem; 
            border-radius: 5px; 
            margin-top: 0.5rem;
            font-size: 0.8rem;
            color: #0056b3;
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
                <a href="users.php" class="btn btn-secondary" style="text-decoration: none;">← Back to Users</a>
                <a href="edit-user.php?id=<?php echo $user_id; ?>" class="btn btn-secondary" style="text-decoration: none;">👤 Edit User</a>
            </div>
            <h1>User Bookings</h1>
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

            <!-- User Info Card -->
            <div class="card">
                <div class="card-header">
                    <h3>User Information</h3>
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                        <div>
                            <strong>Name:</strong> <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                        </div>
                        <div>
                            <strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?>
                        </div>
                        <div>
                            <strong>User ID:</strong> #<?php echo $user['id']; ?>
                        </div>
                        <div>
                            <strong>Total Bookings:</strong> <?php echo $stats['total_bookings'] ?? 0; ?>
                        </div>
                    </div>
                </div>
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
                <?php if ($stats['total_revenue'] > 0): ?>
                <div class="stat-card">
                    <div class="stat-number">₦<?php echo number_format($stats['total_revenue'], 0); ?></div>
                    <div class="stat-label">Total Revenue</div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Filters -->
            <div class="card">
                <div class="card-header">
                    <h3>Filter Bookings</h3>
                </div>
                <div class="card-body">
                    <form method="GET" action="" class="filters">
                        <input type="hidden" name="id" value="<?php echo $user_id; ?>">
                        
                        <div class="filter-group">
                            <label for="status">Status</label>
                            <select id="status" name="status" class="form-control">
                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                <option value="paid" <?php echo $status_filter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="date">Booking Date</label>
                            <input type="date" id="date" name="date" class="form-control" value="<?php echo $date_filter; ?>">
                        </div>
                        
                        <div class="filter-group">
                            <button type="submit" class="btn btn-primary">Apply Filters</button>
                            <a href="user-bookings.php?id=<?php echo $user_id; ?>" class="btn btn-secondary">Clear</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Bookings List -->
            <div class="card">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <h3>Bookings (<?php echo count($bookings); ?>)</h3>
                    <div>
                        <a href="bookings.php" class="btn btn-primary">View All Bookings</a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($bookings)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">✈️</div>
                            <h3>No Bookings Found</h3>
                            <p>This user hasn't made any bookings yet.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($bookings as $booking): ?>
                            <?php
                            $flight_data = json_decode($booking['flight_data'], true);
                            $itinerary = $flight_data['itineraries'][0] ?? [];
                            $first_segment = $itinerary['segments'][0] ?? [];
                            $last_segment = end($itinerary['segments']) ?? [];
                            
                            // Check if booking has payments
                            $payment_stmt = $pdo->prepare("SELECT COUNT(*) as payment_count FROM payments WHERE booking_id = ?");
                            $payment_stmt->execute([$booking['id']]);
                            $has_payments = $payment_stmt->fetch(PDO::FETCH_ASSOC)['payment_count'] > 0;
                            ?>
                            
                            <div class="booking-card">
                                <div class="booking-header">
                                    <div class="booking-info">
                                        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.5rem;">
                                            <h4 style="margin: 0; color: #333;">
                                                <?php echo $first_segment['departure']['iataCode'] ?? 'N/A'; ?> → 
                                                <?php echo $last_segment['arrival']['iataCode'] ?? 'N/A'; ?>
                                            </h4>
                                            <span class="status-badge status-<?php echo $booking['status']; ?>">
                                                <?php echo ucfirst($booking['status']); ?>
                                            </span>
                                        </div>
                                        <div style="color: #666; font-size: 0.9rem;">
                                            <strong>Booking Reference:</strong> <?php echo $booking['booking_reference']; ?> | 
                                            <strong>Amount:</strong> ₦<?php echo number_format($booking['total_amount'], 2); ?> | 
                                            <strong>Date:</strong> <?php echo date('M j, Y g:i A', strtotime($booking['created_at'])); ?>
                                        </div>
                                        <?php if ($has_payments): ?>
                                            <div class="payment-info">
                                                💳 This booking has payment records. Deleting will also remove payment history.
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="booking-actions">
                                        <form method="POST" action="" style="display: inline;">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                            <?php if ($booking['status'] === 'pending'): ?>
                                                <button type="submit" name="action" value="confirm" class="btn btn-success btn-sm">Confirm</button>
                                                <button type="submit" name="action" value="cancel" class="btn btn-warning btn-sm">Cancel</button>
                                            <?php elseif ($booking['status'] === 'confirmed'): ?>
                                                <button type="submit" name="action" value="cancel" class="btn btn-warning btn-sm">Cancel</button>
                                            <?php endif; ?>
                                            <button type="submit" name="action" value="delete" class="btn btn-danger btn-sm" 
                                                    onclick="return confirmDelete(<?php echo $has_payments ? 'true' : 'false'; ?>)">
                                                Delete
                                            </button>
                                        </form>
                                        <a href="../print-ticket.php?booking_id=<?php echo $booking['id']; ?>" target="_blank" class="btn btn-primary btn-sm">View Ticket</a>
                                        <?php if ($has_payments): ?>
                                            <a href="payments.php?booking_id=<?php echo $booking['id']; ?>" class="btn btn-secondary btn-sm">View Payments</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if (!empty($itinerary)): ?>
                                <div class="flight-details">
                                    <div>
                                        <div class="flight-time"><?php echo date('H:i', strtotime($first_segment['departure']['at'])); ?></div>
                                        <div class="flight-date"><?php echo date('M j', strtotime($first_segment['departure']['at'])); ?></div>
                                        <div><strong><?php echo $first_segment['departure']['iataCode']; ?></strong></div>
                                    </div>
                                    <div class="flight-duration">
                                        <div><?php echo substr($itinerary['duration'], 2); ?></div>
                                        <div style="font-size: 0.8rem;"><?php echo count($itinerary['segments']); ?> segment(s)</div>
                                    </div>
                                    <div style="text-align: right;">
                                        <div class="flight-time"><?php echo date('H:i', strtotime($last_segment['arrival']['at'])); ?></div>
                                        <div class="flight-date"><?php echo date('M j', strtotime($last_segment['arrival']['at'])); ?></div>
                                        <div><strong><?php echo $last_segment['arrival']['iataCode']; ?></strong></div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.9rem; color: #666;">
                                    <div>
                                        <strong>Airline:</strong> <?php echo $first_segment['carrierCode'] ?? 'N/A'; ?> | 
                                        <strong>Passengers:</strong> <?php echo $booking['passenger_count'] ?? 1; ?>
                                    </div>
                                    <div>
                                        <a href="booking-details.php?id=<?php echo $booking['id']; ?>" class="btn btn-secondary btn-sm">View Details</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function confirmDelete(hasPayments) {
            if (hasPayments) {
                return confirm('⚠️ WARNING: This booking has payment records!\n\nDeleting will permanently remove:\n• Booking details\n• Payment history\n• All related records\n\nThis action cannot be undone. Are you sure you want to proceed?');
            } else {
                return confirm('Are you sure you want to delete this booking? This action cannot be undone.');
            }
        }
        
        // Confirm before deleting bookings
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                if (this.querySelector('button[value="delete"]') === e.submitter) {
                    const hasPayments = this.closest('.booking-card').querySelector('.payment-info') !== null;
                    if (!confirmDelete(hasPayments)) {
                        e.preventDefault();
                    }
                }
            });
        });
        
        // Auto-submit form when status changes (optional enhancement)
        document.getElementById('status')?.addEventListener('change', function() {
            this.form.submit();
        });
    </script>
</body>
</html>