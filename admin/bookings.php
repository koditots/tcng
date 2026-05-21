<?php
// admin/bookings.php
require_once '../config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

$page_title = "Booking Management";

// Handle actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $booking_id = intval($_GET['id']);
    $action = sanitize($_GET['action']);
    
    switch ($action) {
        case 'confirm':
            $stmt = $pdo->prepare("UPDATE flight_bookings SET status = 'confirmed' WHERE id = ?");
            $stmt->execute([$booking_id]);
            
            // Get booking info for notification
            $stmt = $pdo->prepare("SELECT user_id, booking_reference FROM flight_bookings WHERE id = ?");
            $stmt->execute([$booking_id]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($booking) {
                addNotification($pdo, $booking['user_id'], 'Booking Confirmed', 'Your booking ' . $booking['booking_reference'] . ' has been confirmed by admin.', 'success', 'booking', $booking_id);
            }
            break;
            
        case 'cancel':
            $stmt = $pdo->prepare("UPDATE flight_bookings SET status = 'cancelled' WHERE id = ?");
            $stmt->execute([$booking_id]);
            break;
            
        case 'delete':
            $stmt = $pdo->prepare("DELETE FROM flight_bookings WHERE id = ?");
            $stmt->execute([$booking_id]);
            break;
    }
    
    redirect('bookings.php');
}

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $bulk_action = sanitize($_POST['bulk_action']);
    $selected_bookings = $_POST['selected_bookings'] ?? [];
    
    if (!empty($selected_bookings)) {
        $placeholders = str_repeat('?,', count($selected_bookings) - 1) . '?';
        
        switch ($bulk_action) {
            case 'confirm':
                $stmt = $pdo->prepare("UPDATE flight_bookings SET status = 'confirmed' WHERE id IN ($placeholders)");
                $stmt->execute($selected_bookings);
                break;
                
            case 'cancel':
                $stmt = $pdo->prepare("UPDATE flight_bookings SET status = 'cancelled' WHERE id IN ($placeholders)");
                $stmt->execute($selected_bookings);
                break;
                
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM flight_bookings WHERE id IN ($placeholders)");
                $stmt->execute($selected_bookings);
                break;
        }
        
        $success = "Bulk action completed successfully!";
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query
$query = "SELECT fb.*, u.first_name, u.last_name, u.email 
          FROM flight_bookings fb 
          LEFT JOIN users u ON fb.user_id = u.id 
          WHERE 1=1";
$params = [];

if ($status_filter) {
    $query .= " AND fb.status = ?";
    $params[] = $status_filter;
}

if ($date_from) {
    $query .= " AND DATE(fb.created_at) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $query .= " AND DATE(fb.created_at) <= ?";
    $params[] = $date_to;
}

$query .= " ORDER BY fb.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistics
$total_bookings = $pdo->query("SELECT COUNT(*) FROM flight_bookings")->fetchColumn();
$pending_bookings = $pdo->query("SELECT COUNT(*) FROM flight_bookings WHERE status = 'pending'")->fetchColumn();
$confirmed_bookings = $pdo->query("SELECT COUNT(*) FROM flight_bookings WHERE status = 'confirmed'")->fetchColumn();
$total_revenue = $pdo->query("SELECT SUM(total_amount) FROM flight_bookings WHERE status = 'confirmed'")->fetchColumn();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo getSiteSetting($pdo, 'site_name'); ?></title>
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
            overflow-x: hidden;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 0;
            min-height: 100vh;
            width: calc(100% - 250px);
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
            width: 100%;
            max-width: 100%;
        }
        
        /* Cards */
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
            overflow: hidden;
            width: 100%;
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
            width: 100%;
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
        
        .stat-card .number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #333;
            line-height: 1.2;
        }
        
        .stat-card .label {
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
            width: 100%;
            max-width: 100%;
        }
        
        .filter-actions {
            display: flex;
            gap: 0.5rem;
            align-items: end;
        }
        
        /* Bulk Actions */
        .bulk-actions {
            display: flex;
            gap: 0.75rem;
            align-items: center;
            margin-bottom: 1rem;
            padding: 0.875rem;
            background: #e7f1ff;
            border-radius: 4px;
            flex-wrap: wrap;
        }
        
        .bulk-actions select, .bulk-actions button {
            padding: 0.5rem 0.75rem;
            border: 1px solid #007bff;
            border-radius: 4px;
            font-size: 0.8rem;
        }
        
        .bulk-actions button {
            background: #007bff;
            color: white;
            cursor: pointer;
            border: none;
        }
        
        /* Tables */
        .table-container {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
            min-width: 800px;
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
            white-space: nowrap;
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
            flex-wrap: wrap;
        }
        
        /* Badges */
        .badge {
            padding: 0.2rem 0.4rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: bold;
            display: inline-block;
            white-space: nowrap;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .badge-info {
            background: #d1edff;
            color: #004085;
        }
        
        .badge-secondary {
            background: #e2e3e5;
            color: #383d41;
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
        
        .checkbox-cell {
            width: 30px;
        }
        
        /* Quick actions */
        .quick-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }
        
        /* Responsive design */
        @media (max-width: 1200px) {
            .main-content {
                margin-left: 0;
                width: 100%;
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                width: 100%;
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
            
            .bulk-actions {
                flex-direction: column;
                align-items: stretch;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 0.125rem;
            }
            
            .btn-xs {
                width: 100%;
                justify-content: center;
            }
            
            table {
                min-width: 700px;
            }
            
            .card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
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
            
            .quick-actions {
                flex-direction: column;
            }
            
            .quick-actions .btn {
                width: 100%;
                justify-content: center;
            }
            
            .filters {
                padding: 1rem;
            }
            
            .bulk-actions {
                padding: 0.75rem;
            }
            
            .table-container {
                margin: 0 -1rem;
                width: calc(100% + 2rem);
            }
        }
    </style>
</head>
<body>
    <!-- Include Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-bar">
            <h1>Booking Management</h1>
            <div>
                <span>Welcome, <?php echo $_SESSION['user_name']; ?></span>
            </div>
        </div>

        <div class="content">
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <a href="?status=pending" class="btn btn-warning btn-sm">
                    <i class="fas fa-clock"></i> Pending (<?php echo $pending_bookings; ?>)
                </a>
                <a href="?status=confirmed" class="btn btn-success btn-sm">
                    <i class="fas fa-check"></i> Confirmed (<?php echo $confirmed_bookings; ?>)
                </a>
                <a href="bookings.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-sync"></i> Reset Filters
                </a>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="number"><?php echo $total_bookings; ?></div>
                    <div class="label">Total Bookings</div>
                </div>
                <div class="stat-card">
                    <div class="number"><?php echo $pending_bookings; ?></div>
                    <div class="label">Pending</div>
                </div>
                <div class="stat-card">
                    <div class="number"><?php echo $confirmed_bookings; ?></div>
                    <div class="label">Confirmed</div>
                </div>
                <div class="stat-card">
                    <div class="number">₦<?php echo number_format($total_revenue ?: 0, 2); ?></div>
                    <div class="label">Total Revenue</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card">
                <div class="card-header">
                    <h3>Filters</h3>
                </div>
                <div class="card-body">
                    <form method="GET" action="">
                        <div class="filters">
                            <div class="filter-group">
                                <label>Status</label>
                                <select name="status">
                                    <option value="">All Status</option>
                                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                    <option value="paid" <?php echo $status_filter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                    <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label>From Date</label>
                                <input type="date" name="date_from" value="<?php echo $date_from; ?>">
                            </div>
                            
                            <div class="filter-group">
                                <label>To Date</label>
                                <input type="date" name="date_to" value="<?php echo $date_to; ?>">
                            </div>
                            
                            <div class="filter-group filter-actions">
                                <button type="submit" class="btn btn-primary">Apply Filters</button>
                                <a href="bookings.php" class="btn" style="background: #6c757d; color: white;">Clear</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Bookings Table -->
            <div class="card">
                <div class="card-header">
                    <h3>All Bookings (<?php echo count($bookings); ?>)</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($bookings)): ?>
                        <form method="POST" action="" id="bulkForm">
                            <div class="bulk-actions">
                                <select name="bulk_action" required>
                                    <option value="">Bulk Actions</option>
                                    <option value="confirm">Confirm Selected</option>
                                    <option value="cancel">Cancel Selected</option>
                                    <option value="delete">Delete Selected</option>
                                </select>
                                <button type="submit" onclick="return confirm('Are you sure?')">Apply</button>
                            </div>
                            
                            <div class="table-container">
                                <table>
                                    <thead>
                                        <tr>
                                            <th class="checkbox-cell">
                                                <input type="checkbox" id="selectAll">
                                            </th>
                                            <th>Reference</th>
                                            <th>Customer</th>
                                            <th>Flight</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Payment</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($bookings as $booking): 
                                            $flight_data = json_decode($booking['flight_data'], true);
                                            $itinerary = $flight_data['itineraries'][0];
                                            $first_segment = $itinerary['segments'][0];
                                            $last_segment = end($itinerary['segments']);
                                        ?>
                                            <tr>
                                                <td>
                                                    <input type="checkbox" name="selected_bookings[]" value="<?php echo $booking['id']; ?>" class="booking-checkbox">
                                                </td>
                                                <td>
                                                    <strong><?php echo $booking['booking_reference']; ?></strong>
                                                    <?php if ($booking['ticket_number']): ?>
                                                        <br><small>Ticket: <?php echo $booking['ticket_number']; ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php echo $booking['first_name'] . ' ' . $booking['last_name']; ?>
                                                    <br><small><?php echo $booking['email']; ?></small>
                                                </td>
                                                <td>
                                                    <?php echo $first_segment['departure']['iataCode']; ?> → <?php echo $last_segment['arrival']['iataCode']; ?>
                                                    <br><small><?php echo date('M j, Y', strtotime($first_segment['departure']['at'])); ?></small>
                                                </td>
                                                <td>₦<?php echo number_format($booking['total_amount'], 2); ?></td>
                                                <td>
                                                    <span class="badge badge-<?php 
                                                        switch($booking['status']) {
                                                            case 'confirmed': echo 'success';
                                                            case 'paid': echo 'success';
                                                            case 'pending': echo 'warning';
                                                            case 'cancelled': echo 'danger';
                                                            default: echo 'secondary';
                                                        }
                                                    ?>">
                                                        <?php echo ucfirst($booking['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?php echo $booking['payment_status'] === 'paid' ? 'success' : 'warning'; ?>">
                                                        <?php echo ucfirst($booking['payment_status']); ?>
                                                    </span>
                                                    <?php if ($booking['payment_method']): ?>
                                                        <br><small><?php echo ucfirst($booking['payment_method']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo date('M j, Y', strtotime($booking['created_at'])); ?></td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <a href="booking-details.php?id=<?php echo $booking['id']; ?>" class="btn btn-primary btn-xs">View</a>
                                                        <a href="../print-ticket.php?booking_id=<?php echo $booking['id']; ?>" target="_blank" class="btn btn-success btn-xs">Ticket</a>
                                                        
                                                        <?php if ($booking['status'] === 'pending'): ?>
                                                            <a href="?action=confirm&id=<?php echo $booking['id']; ?>" class="btn btn-success btn-xs">Confirm</a>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($booking['status'] !== 'cancelled'): ?>
                                                            <a href="?action=cancel&id=<?php echo $booking['id']; ?>" class="btn btn-warning btn-xs" onclick="return confirm('Cancel this booking?')">Cancel</a>
                                                        <?php endif; ?>
                                                        
                                                        <a href="?action=delete&id=<?php echo $booking['id']; ?>" class="btn btn-danger btn-xs" onclick="return confirm('Delete this booking?')">Delete</a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </form>
                    <?php else: ?>
                        <p style="text-align: center; color: #666; padding: 2rem;">No bookings found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Select all checkboxes
    document.getElementById('selectAll').addEventListener('click', function() {
        const checkboxes = document.getElementsByClassName('booking-checkbox');
        for (let checkbox of checkboxes) {
            checkbox.checked = this.checked;
        }
    });
    
    // Update select all checkbox when individual checkboxes change
    const checkboxes = document.getElementsByClassName('booking-checkbox');
    for (let checkbox of checkboxes) {
        checkbox.addEventListener('change', function() {
            const selectAll = document.getElementById('selectAll');
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            selectAll.checked = allChecked;
        });
    }
    </script>
</body>
</html>