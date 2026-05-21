<?php
// admin/payments.php
require_once '../config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

$page_title = "Payment Management";

// Get filters from URL
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : 'all';
$date_from = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : '';
$payment_method = isset($_GET['payment_method']) ? sanitize($_GET['payment_method']) : 'all';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Build query for payments
$query = "SELECT p.*, 
          u.first_name, u.last_name, u.email,
          fb.booking_reference, fb.total_amount as booking_amount,
          fb.status as booking_status
          FROM payments p
          LEFT JOIN users u ON p.user_id = u.id
          LEFT JOIN flight_bookings fb ON p.booking_id = fb.id
          WHERE 1=1";
$params = [];

// Apply filters
if ($status_filter !== 'all') {
    $query .= " AND p.status = ?";
    $params[] = $status_filter;
}

if (!empty($date_from)) {
    $query .= " AND DATE(p.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND DATE(p.created_at) <= ?";
    $params[] = $date_to;
}

if ($payment_method !== 'all') {
    $query .= " AND p.payment_method = ?";
    $params[] = $payment_method;
}

if (!empty($search)) {
    $query .= " AND (p.transaction_id LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR fb.booking_reference LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$query .= " ORDER BY p.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get payment statistics
$stats_query = "SELECT 
    COUNT(*) as total_payments,
    SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful_payments,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_payments,
    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_payments,
    SUM(CASE WHEN status = 'success' THEN amount ELSE 0 END) as total_revenue,
    AVG(CASE WHEN status = 'success' THEN amount ELSE NULL END) as average_amount
    FROM payments WHERE 1=1";

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

// Handle payment actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $payment_id = intval($_POST['payment_id'] ?? 0);
    
    if ($payment_id && in_array($action, ['approve', 'reject', 'refund', 'delete'])) {
        try {
            $pdo->beginTransaction();
            
            switch ($action) {
                case 'approve':
                    $stmt = $pdo->prepare("UPDATE payments SET status = 'success', updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$payment_id]);
                    
                    // Also update booking status if there's a booking
                    $booking_stmt = $pdo->prepare("UPDATE flight_bookings SET status = 'confirmed', updated_at = NOW() WHERE id = (SELECT booking_id FROM payments WHERE id = ?)");
                    $booking_stmt->execute([$payment_id]);
                    
                    $_SESSION['success'] = "Payment approved successfully!";
                    break;
                    
                case 'reject':
                    $stmt = $pdo->prepare("UPDATE payments SET status = 'failed', updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$payment_id]);
                    $_SESSION['success'] = "Payment rejected successfully!";
                    break;
                    
                case 'refund':
                    $stmt = $pdo->prepare("UPDATE payments SET status = 'refunded', updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$payment_id]);
                    $_SESSION['success'] = "Payment marked as refunded!";
                    break;
                    
                case 'delete':
                    $stmt = $pdo->prepare("DELETE FROM payments WHERE id = ?");
                    $stmt->execute([$payment_id]);
                    $_SESSION['success'] = "Payment record deleted successfully!";
                    break;
            }
            
            $pdo->commit();
            header("Location: payments.php?" . http_build_query($_GET));
            exit;
            
        } catch (Exception $e) {
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
        .btn-info { background: #17a2b8; color: white; }
        .btn-sm { padding: 0.25rem 0.5rem; font-size: 0.8rem; }
        
        .alert { padding: 1rem; border-radius: 5px; margin-bottom: 1rem; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat-card { background: white; padding: 1.5rem; border-radius: 10px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1); border-left: 4px solid #007bff; }
        .stat-number { font-size: 2rem; font-weight: bold; color: #007bff; margin-bottom: 0.5rem; }
        .stat-label { color: #666; font-size: 0.9rem; }
        
        .filters { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .filter-group { display: flex; flex-direction: column; }
        .filter-group label { margin-bottom: 0.5rem; font-weight: bold; color: #333; }
        .form-control { padding: 0.5rem; border: 1px solid #ddd; border-radius: 5px; font-size: 1rem; }
        
        .payment-card { background: white; border-radius: 10px; padding: 1.5rem; margin-bottom: 1rem; box-shadow: 0 2px 10px rgba(0,0,0,0.1); border-left: 4px solid #007bff; }
        .payment-header { display: flex; justify-content: between; align-items: start; margin-bottom: 1rem; }
        .payment-info { flex: 1; }
        .payment-actions { display: flex; gap: 0.5rem; }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem; font-weight: bold; }
        .status-success { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-failed { background: #f8d7da; color: #721c24; }
        .status-refunded { background: #d1ecf1; color: #0c5460; }
        
        .method-badge { padding: 0.25rem 0.5rem; border-radius: 15px; font-size: 0.7rem; font-weight: bold; background: #e9ecef; color: #495057; }
        
        .empty-state { text-align: center; padding: 3rem; color: #666; }
        .empty-state-icon { font-size: 4rem; margin-bottom: 1rem; }
        
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #dee2e6; }
        .table th { background: #f8f9fa; font-weight: bold; color: #333; }
        .table tr:hover { background: #f8f9fa; }
        
        .action-buttons { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        
        .search-box { display: flex; gap: 0.5rem; }
        .search-box input { flex: 1; }
        
        .export-buttons { display: flex; gap: 0.5rem; margin-bottom: 1rem; }
        
        .no-transaction { color: #6c757d; font-style: italic; }
    </style>
</head>
<body>
<!-- Include Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-bar">
            <div>
                <a href="dashboard.php" class="btn btn-secondary" style="text-decoration: none;">← Dashboard</a>
            </div>
            <h1>Payment Management</h1>
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

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_payments'] ?? 0; ?></div>
                    <div class="stat-label">Total Payments</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['successful_payments'] ?? 0; ?></div>
                    <div class="stat-label">Successful</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['pending_payments'] ?? 0; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['failed_payments'] ?? 0; ?></div>
                    <div class="stat-label">Failed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">₦<?php echo number_format($stats['total_revenue'] ?? 0, 0); ?></div>
                    <div class="stat-label">Total Revenue</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">₦<?php echo number_format($stats['average_amount'] ?? 0, 0); ?></div>
                    <div class="stat-label">Average Payment</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card">
                <div class="card-header">
                    <h3>Filter Payments</h3>
                </div>
                <div class="card-body">
                    <form method="GET" action="" class="filters">
                        <div class="filter-group">
                            <label for="status">Payment Status</label>
                            <select id="status" name="status" class="form-control">
                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                                <option value="success" <?php echo $status_filter === 'success' ? 'selected' : ''; ?>>Successful</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="failed" <?php echo $status_filter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                <option value="refunded" <?php echo $status_filter === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="payment_method">Payment Method</label>
                            <select id="payment_method" name="payment_method" class="form-control">
                                <option value="all" <?php echo $payment_method === 'all' ? 'selected' : ''; ?>>All Methods</option>
                                <option value="paystack" <?php echo $payment_method === 'paystack' ? 'selected' : ''; ?>>Paystack</option>
                                <option value="flutterwave" <?php echo $payment_method === 'flutterwave' ? 'selected' : ''; ?>>Flutterwave</option>
                                <option value="bank_transfer" <?php echo $payment_method === 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                                <option value="cash" <?php echo $payment_method === 'cash' ? 'selected' : ''; ?>>Cash</option>
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
                                <input type="text" id="search" name="search" class="form-control" placeholder="Transaction ID, Name, Email, Booking Ref..." value="<?php echo $search; ?>">
                                <button type="submit" class="btn btn-primary">Search</button>
                            </div>
                        </div>
                        
                        <div class="filter-group" style="display: flex; align-items: end; gap: 0.5rem;">
                            <button type="submit" class="btn btn-primary">Apply Filters</button>
                            <a href="payments.php" class="btn btn-secondary">Clear</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Export Buttons -->
            <div class="export-buttons">
                <button onclick="exportToCSV()" class="btn btn-success">📊 Export to CSV</button>
                <button onclick="printPayments()" class="btn btn-secondary">🖨️ Print Report</button>
            </div>

            <!-- Payments List -->
            <div class="card">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <h3>Payments (<?php echo count($payments); ?>)</h3>
                    <div>
                        <span class="btn btn-secondary">Total: ₦<?php echo number_format($stats['total_revenue'] ?? 0, 2); ?></span>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($payments)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">💳</div>
                            <h3>No Payments Found</h3>
                            <p>No payment records match your current filters.</p>
                            <a href="payments.php" class="btn btn-primary">Clear Filters</a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Transaction ID</th>
                                        <th>Customer</th>
                                        <th>Booking Ref</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td>
                                            <?php if (!empty($payment['transaction_id'])): ?>
                                                <strong><?php echo htmlspecialchars($payment['transaction_id']); ?></strong>
                                            <?php else: ?>
                                                <span class="no-transaction">No Transaction ID</span>
                                            <?php endif; ?>
                                            <?php if (!empty($payment['booking_id'])): ?>
                                                <br><small>Booking: #<?php echo $payment['booking_id']; ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($payment['first_name'])): ?>
                                                <?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?>
                                                <br><small><?php echo htmlspecialchars($payment['email']); ?></small>
                                            <?php else: ?>
                                                <em>User deleted</em>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($payment['booking_reference'])): ?>
                                                <?php echo htmlspecialchars($payment['booking_reference']); ?>
                                                <br><small class="status-badge status-<?php echo $payment['booking_status'] ?? 'unknown'; ?>">
                                                    <?php echo ucfirst($payment['booking_status'] ?? 'unknown'); ?>
                                                </small>
                                            <?php else: ?>
                                                <em>No booking</em>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong>₦<?php echo number_format($payment['amount'] ?? 0, 2); ?></strong>
                                            <?php if (!empty($payment['booking_amount']) && $payment['booking_amount'] != $payment['amount']): ?>
                                                <br><small>Booking: ₦<?php echo number_format($payment['booking_amount'], 2); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="method-badge"><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'] ?? 'unknown')); ?></span>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo $payment['status'] ?? 'unknown'; ?>">
                                                <?php echo ucfirst($payment['status'] ?? 'unknown'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo date('M j, Y', strtotime($payment['created_at'])); ?>
                                            <br><small><?php echo date('g:i A', strtotime($payment['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <?php if (($payment['status'] ?? '') === 'pending'): ?>
                                                    <form method="POST" action="" style="display: inline;">
                                                        <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                                        <button type="submit" name="action" value="approve" class="btn btn-success btn-sm">Approve</button>
                                                        <button type="submit" name="action" value="reject" class="btn btn-warning btn-sm">Reject</button>
                                                    </form>
                                                <?php elseif (($payment['status'] ?? '') === 'success'): ?>
                                                    <form method="POST" action="" style="display: inline;">
                                                        <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                                        <button type="submit" name="action" value="refund" class="btn btn-info btn-sm">Refund</button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <form method="POST" action="" style="display: inline;">
                                                    <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                                    <button type="submit" name="action" value="delete" class="btn btn-danger btn-sm" 
                                                            onclick="return confirm('Are you sure you want to delete this payment record? This action cannot be undone.')">
                                                        Delete
                                                    </button>
                                                </form>
                                                
                                                <?php if (!empty($payment['booking_id'])): ?>
                                                    <a href="booking-details.php?id=<?php echo $payment['booking_id']; ?>" class="btn btn-secondary btn-sm">View Booking</a>
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
            const exportUrl = 'export-payments.php?' + params.toString() + '&format=csv';
            
            // Trigger download
            window.location.href = exportUrl;
        }
        
        function printPayments() {
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
                <div style="display: flex; gap: 0.5rem; margin-top: 0.5rem;">
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
        
        document.getElementById('payment_method')?.addEventListener('change', function() {
            this.form.submit();
        });
    </script>

    <style>
        @media print {
            .sidebar, .top-bar, .filters, .export-buttons, .action-buttons {
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
        
        .no-transaction {
            color: #6c757d;
            font-style: italic;
        }
    </style>
</body>
</html>