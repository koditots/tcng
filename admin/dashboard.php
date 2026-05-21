<?php
// admin/dashboard.php
session_start();
require_once '../config.php';

// Check if required functions exist, if not define them
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
}

if (!function_exists('isAdmin')) {
    function isAdmin() {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
    }
}

if (!function_exists('redirect')) {
    function redirect($url) {
        header("Location: $url");
        exit;
    }
}

if (!function_exists('getSiteSetting')) {
    function getSiteSetting($pdo, $setting_name, $default = '') {
        try {
            $stmt = $pdo->prepare("SELECT value FROM site_settings WHERE name = ?");
            $stmt->execute([$setting_name]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['value'] : $default;
        } catch (Exception $e) {
            return $default;
        }
    }
}

// Authentication check
if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

$page_title = "Admin Dashboard";
$current_page = 'dashboard';

// Initialize variables with default values
$total_users = $total_bookings = $total_revenue = $pending_bookings = 0;
$total_visa_applications = $pending_visa_applications = $approved_visa_applications = $rejected_visa_applications = 0;
$total_assessment_bookings = $pending_assessment_bookings = $completed_assessment_bookings = $assessment_revenue = 0;
$recent_bookings = $recent_users = $recent_visa_applications = $recent_assessment_bookings = [];

try {
    // Get basic statistics with error handling
    $total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() ?: 0;
    
    // Check if flight_bookings table exists
    $table_exists = $pdo->query("SHOW TABLES LIKE 'flight_bookings'")->fetchColumn();
    if ($table_exists) {
        $total_bookings = $pdo->query("SELECT COUNT(*) FROM flight_bookings")->fetchColumn() ?: 0;
        $total_revenue = $pdo->query("SELECT SUM(total_amount) FROM flight_bookings WHERE payment_status = 'paid'")->fetchColumn() ?: 0;
        $pending_bookings = $pdo->query("SELECT COUNT(*) FROM flight_bookings WHERE status = 'pending'")->fetchColumn() ?: 0;
        
        // Recent bookings
        $stmt = $pdo->prepare("SELECT fb.*, u.first_name, u.last_name FROM flight_bookings fb LEFT JOIN users u ON fb.user_id = u.id ORDER BY fb.created_at DESC LIMIT 5");
        $stmt->execute();
        $recent_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // Check if visa_applications table exists
    $table_exists = $pdo->query("SHOW TABLES LIKE 'visa_applications'")->fetchColumn();
    if ($table_exists) {
        $total_visa_applications = $pdo->query("SELECT COUNT(*) FROM visa_applications")->fetchColumn() ?: 0;
        $pending_visa_applications = $pdo->query("SELECT COUNT(*) FROM visa_applications WHERE status = 'pending'")->fetchColumn() ?: 0;
        $approved_visa_applications = $pdo->query("SELECT COUNT(*) FROM visa_applications WHERE status = 'approved'")->fetchColumn() ?: 0;
        $rejected_visa_applications = $pdo->query("SELECT COUNT(*) FROM visa_applications WHERE status = 'rejected'")->fetchColumn() ?: 0;

        // Recent visa applications with user details
        $stmt = $pdo->prepare("
            SELECT 
                va.*, 
                u.first_name as user_first_name, 
                u.last_name as user_last_name, 
                u.email as user_email,
                u.phone as user_phone,
                CASE 
                    WHEN u.id IS NOT NULL THEN 'Registered'
                    ELSE 'Not Registered'
                END as user_status
            FROM visa_applications va 
            LEFT JOIN users u ON va.user_id = u.id 
            ORDER BY va.created_at DESC 
            LIMIT 5
        ");
        $stmt->execute();
        $recent_visa_applications = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // Check if visa_assessment_bookings table exists
    $table_exists = $pdo->query("SHOW TABLES LIKE 'visa_assessment_bookings'")->fetchColumn();
    if ($table_exists) {
        $total_assessment_bookings = $pdo->query("SELECT COUNT(*) FROM visa_assessment_bookings")->fetchColumn() ?: 0;
        $pending_assessment_bookings = $pdo->query("SELECT COUNT(*) FROM visa_assessment_bookings WHERE status = 'pending'")->fetchColumn() ?: 0;
        $completed_assessment_bookings = $pdo->query("SELECT COUNT(*) FROM visa_assessment_bookings WHERE status = 'completed'")->fetchColumn() ?: 0;
        $assessment_revenue = $pdo->query("SELECT SUM(booking_fee) FROM visa_assessment_bookings WHERE status = 'completed'")->fetchColumn() ?: 0;

        // Recent visa assessment bookings
        $stmt = $pdo->prepare("
            SELECT 
                vab.*,
                CASE 
                    WHEN vab.readiness_grade = 'low' THEN 'Low Chance'
                    WHEN vab.readiness_grade = 'medium' THEN 'Medium Chance'
                    WHEN vab.readiness_grade = 'high' THEN 'High Chance'
                    ELSE 'Not Rated'
                END as grade_display
            FROM visa_assessment_bookings vab 
            ORDER BY vab.created_at DESC 
            LIMIT 5
        ");
        $stmt->execute();
        $recent_assessment_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // Recent users
    $stmt = $pdo->prepare("SELECT * FROM users ORDER BY created_at DESC LIMIT 5");
    $stmt->execute();
    $recent_users = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
}

// Ensure session variables exist
if (!isset($_SESSION['user_name'])) {
    $_SESSION['user_name'] = 'Admin';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - <?php echo htmlspecialchars(getSiteSetting($pdo, 'site_name', 'Travel Centre')); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f8f9fa;
            display: flex;
            font-size: 0.875rem;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 0;
            transition: margin-left 0.3s ease;
        }
        
        .sidebar.collapsed ~ .main-content {
            margin-left: 70px;
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
            font-size: 1.25rem;
            font-weight: 600;
            color: #333;
        }
        
        .top-bar span {
            font-size: 0.875rem;
            color: #666;
        }
        
        .content {
            padding: 1.25rem;
        }
        
        /* Stats Grid - More Compact */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid #007bff;
            position: relative;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }
        
        .stat-card h3 {
            color: #666;
            font-size: 0.8rem;
            margin-bottom: 0.4rem;
            font-weight: 500;
        }
        
        .stat-card .number {
            font-size: 1.5rem;
            font-weight: 700;
            color: #333;
            line-height: 1.2;
        }
        
        .notification-badge {
            position: absolute;
            top: -6px;
            right: -6px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: bold;
        }
        
        /* Stat card colors */
        .stat-card.users { border-left-color: #28a745; }
        .stat-card.bookings { border-left-color: #ffc107; }
        .stat-card.revenue { border-left-color: #dc3545; }
        .stat-card.pending { border-left-color: #6c757d; }
        .stat-card.visa-total { border-left-color: #17a2b8; }
        .stat-card.visa-pending { border-left-color: #e83e8c; }
        .stat-card.visa-approved { border-left-color: #28a745; }
        .stat-card.visa-rejected { border-left-color: #dc3545; }
        .stat-card.assessment-total { border-left-color: #6f42c1; }
        .stat-card.assessment-pending { border-left-color: #fd7e14; }
        .stat-card.assessment-completed { border-left-color: #20c997; }
        .stat-card.assessment-revenue { border-left-color: #e83e8c; }
        
        /* Cards */
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 1.25rem;
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
            font-size: 1rem;
            font-weight: 600;
            color: #333;
        }
        
        .card-body {
            padding: 1.25rem;
        }
        
        /* Tables - More Compact */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
        }
        
        th, td {
            padding: 0.6rem 0.5rem;
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
            letter-spacing: 0.5px;
        }
        
        /* Buttons - More Compact */
        .btn {
            padding: 0.4rem 0.8rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 500;
            transition: all 0.2s ease;
            line-height: 1;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0056b3;
            transform: translateY(-1px);
        }
        
        .btn-sm {
            padding: 0.3rem 0.6rem;
            font-size: 0.7rem;
        }
        
        /* Status Badges */
        .status-badge {
            padding: 0.2rem 0.5rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
            line-height: 1.2;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-confirmed { background: #d1edff; color: #004085; }
        .status-paid { background: #d4edda; color: #155724; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .status-under-review { background: #d1ecf1; color: #0c5460; }
        .status-completed { background: #d4edda; color: #155724; }
        
        .user-status {
            padding: 0.2rem 0.4rem;
            border-radius: 10px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        
        .registered { background: #d4edda; color: #155724; }
        .not-registered { background: #fff3cd; color: #856404; }
        
        .visa-type {
            padding: 0.2rem 0.4rem;
            border-radius: 10px;
            font-size: 0.65rem;
            font-weight: 600;
            background: #e9ecef;
            color: #495057;
        }
        
        .assessment-grade {
            padding: 0.2rem 0.4rem;
            border-radius: 10px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        
        .grade-low { background: #f8d7da; color: #721c24; }
        .grade-medium { background: #fff3cd; color: #856404; }
        .grade-high { background: #d4edda; color: #155724; }
        
        /* Main Grid Layout */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.25rem;
            margin-bottom: 1.25rem;
        }
        
        .dashboard-grid-full {
            grid-column: 1 / -1;
        }
        
        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 0.75rem;
            margin-top: 1rem;
        }
        
        /* Responsive Design */
        @media (max-width: 1200px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
            
            .sidebar.collapsed ~ .main-content {
                margin-left: 0;
            }
            
            .top-bar {
                padding: 0.6rem 1rem;
            }
            
            .content {
                padding: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 0.75rem;
            }
            
            .stat-card {
                padding: 0.75rem;
            }
            
            .stat-card .number {
                font-size: 1.25rem;
            }
            
            .card-header {
                padding: 0.75rem 1rem;
            }
            
            .card-body {
                padding: 1rem;
            }
            
            th, td {
                padding: 0.5rem 0.4rem;
                font-size: 0.75rem;
            }
            
            .quick-actions {
                grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
            
            .stat-card h3 {
                font-size: 0.7rem;
            }
            
            .stat-card .number {
                font-size: 1.1rem;
            }
            
            .btn {
                padding: 0.35rem 0.6rem;
                font-size: 0.7rem;
            }
            
            table {
                font-size: 0.7rem;
            }
            
            th, td {
                padding: 0.4rem 0.3rem;
            }
        }

        /* Table responsive wrapper */
        .table-responsive {
            overflow-x: auto;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            color: #666;
            padding: 2rem 1rem;
            font-size: 0.875rem;
        }

        /* Compact view for user info */
        .user-info-compact {
            display: flex;
            flex-direction: column;
            gap: 0.2rem;
        }

        .user-name {
            font-weight: 600;
            font-size: 0.8rem;
        }

        .user-email {
            font-size: 0.7rem;
            color: #666;
        }
    </style>
</head>
<body>
    <!-- Include Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <h1>Admin Dashboard</h1>
            <div>
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
            </div>
        </div>

        <!-- Content -->
        <div class="content">
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card users">
                    <h3>Total Users</h3>
                    <div class="number"><?php echo $total_users; ?></div>
                </div>
                <div class="stat-card bookings">
                    <h3>Flight Bookings</h3>
                    <div class="number"><?php echo $total_bookings; ?></div>
                </div>
                <div class="stat-card revenue">
                    <h3>Flight Revenue</h3>
                    <div class="number">₦<?php echo number_format($total_revenue, 2); ?></div>
                </div>
                <div class="stat-card pending">
                    <h3>Pending Flights</h3>
                    <div class="number"><?php echo $pending_bookings; ?></div>
                </div>
                <div class="stat-card visa-total">
                    <h3>Visa Applications</h3>
                    <div class="number"><?php echo $total_visa_applications; ?></div>
                </div>
                <div class="stat-card visa-pending">
                    <h3>Pending Visas</h3>
                    <div class="number"><?php echo $pending_visa_applications; ?></div>
                    <?php if ($pending_visa_applications > 0): ?>
                        <div class="notification-badge"><?php echo $pending_visa_applications; ?></div>
                    <?php endif; ?>
                </div>
                <div class="stat-card visa-approved">
                    <h3>Approved Visas</h3>
                    <div class="number"><?php echo $approved_visa_applications; ?></div>
                </div>
                <div class="stat-card visa-rejected">
                    <h3>Rejected Visas</h3>
                    <div class="number"><?php echo $rejected_visa_applications; ?></div>
                </div>
                <div class="stat-card assessment-total">
                    <h3>Assessments</h3>
                    <div class="number"><?php echo $total_assessment_bookings; ?></div>
                </div>
                <div class="stat-card assessment-pending">
                    <h3>Pending Assess</h3>
                    <div class="number"><?php echo $pending_assessment_bookings; ?></div>
                    <?php if ($pending_assessment_bookings > 0): ?>
                        <div class="notification-badge"><?php echo $pending_assessment_bookings; ?></div>
                    <?php endif; ?>
                </div>
                <div class="stat-card assessment-completed">
                    <h3>Completed Assess</h3>
                    <div class="number"><?php echo $completed_assessment_bookings; ?></div>
                </div>
                <div class="stat-card assessment-revenue">
                    <h3>Assess Revenue</h3>
                    <div class="number">₦<?php echo number_format($assessment_revenue, 2); ?></div>
                </div>
            </div>

            <div class="dashboard-grid">
                <!-- Recent Bookings -->
                <div class="card">
                    <div class="card-header">
                        <h3>Recent Flight Bookings</h3>
                        <a href="bookings.php" class="btn btn-primary btn-sm">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recent_bookings)): ?>
                            <div class="table-responsive">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Ref</th>
                                            <th>Customer</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_bookings as $booking): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars(substr($booking['booking_reference'] ?? 'N/A', 0, 8)); ?></td>
                                                <td class="user-info-compact">
                                                    <span class="user-name"><?php echo htmlspecialchars(($booking['first_name'] ?? '') . ' ' . ($booking['last_name'] ?? '')); ?></span>
                                                </td>
                                                <td>₦<?php echo number_format($booking['total_amount'] ?? 0, 0); ?></td>
                                                <td>
                                                    <span class="status-badge status-<?php echo htmlspecialchars($booking['status'] ?? 'pending'); ?>">
                                                        <?php echo ucfirst(substr($booking['status'] ?? 'Pending', 0, 7)); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="booking-details.php?id=<?php echo $booking['id'] ?? ''; ?>" class="btn btn-primary btn-sm">View</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">No bookings found</div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Users -->
                <div class="card">
                    <div class="card-header">
                        <h3>Recent Users</h3>
                        <a href="users.php" class="btn btn-primary btn-sm">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recent_users)): ?>
                            <div class="table-responsive">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Role</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_users as $user): ?>
                                            <tr>
                                                <td class="user-info-compact">
                                                    <span class="user-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></span>
                                                </td>
                                                <td class="user-email"><?php echo htmlspecialchars(substr($user['email'], 0, 15)) . (strlen($user['email']) > 15 ? '...' : ''); ?></td>
                                                <td>
                                                    <span class="status-badge <?php echo ($user['role'] ?? 'user') === 'admin' ? 'status-paid' : 'status-pending'; ?>">
                                                        <?php echo ucfirst($user['role'] ?? 'user'); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">No users found</div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Visa Applications -->
                <div class="card dashboard-grid-full">
                    <div class="card-header">
                        <h3>Recent Visa Applications</h3>
                        <a href="visa-applications.php" class="btn btn-primary btn-sm">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recent_visa_applications)): ?>
                            <div class="table-responsive">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Applicant</th>
                                            <th>Type</th>
                                            <th>Status</th>
                                            <th>User</th>
                                            <th>Date</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_visa_applications as $application): ?>
                                            <tr>
                                                <td class="user-info-compact">
                                                    <span class="user-name">
                                                        <?php if ($application['user_id']): ?>
                                                            <?php echo htmlspecialchars($application['user_first_name'] . ' ' . $application['user_last_name']); ?>
                                                        <?php else: ?>
                                                            <?php echo htmlspecialchars($application['applicant_name'] ?? 'Guest'); ?>
                                                        <?php endif; ?>
                                                    </span>
                                                    <span class="user-email">
                                                        <?php echo htmlspecialchars($application['user_id'] ? $application['user_email'] : ($application['applicant_email'] ?? 'No email')); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="visa-type"><?php echo htmlspecialchars($application['visa_type'] ?? 'Standard'); ?></span>
                                                </td>
                                                <td>
                                                    <span class="status-badge status-<?php echo htmlspecialchars($application['status']); ?>">
                                                        <?php echo ucfirst($application['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="user-status <?php echo strtolower($application['user_status'] === 'Registered' ? 'registered' : 'not-registered'); ?>">
                                                        <?php echo htmlspecialchars($application['user_status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M j', strtotime($application['created_at'])); ?></td>
                                                <td>
                                                    <a href="visa-applications.php?action=view&id=<?php echo $application['id']; ?>" class="btn btn-primary btn-sm">View</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">No visa applications found</div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Assessment Bookings -->
                <div class="card dashboard-grid-full">
                    <div class="card-header">
                        <h3>Recent Assessment Bookings</h3>
                        <a href="assessment-bookings.php" class="btn btn-primary btn-sm">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recent_assessment_bookings)): ?>
                            <div class="table-responsive">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Applicant</th>
                                            <th>Score</th>
                                            <th>Grade</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_assessment_bookings as $booking): ?>
                                            <tr>
                                                <td class="user-info-compact">
                                                    <span class="user-name"><?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?></span>
                                                    <span class="user-email"><?php echo htmlspecialchars($booking['email']); ?></span>
                                                </td>
                                                <td><?php echo htmlspecialchars($booking['readiness_score'] ?? '0'); ?>%</td>
                                                <td>
                                                    <span class="assessment-grade grade-<?php echo htmlspecialchars($booking['readiness_grade'] ?? 'medium'); ?>">
                                                        <?php echo htmlspecialchars($booking['grade_display'] ?? 'Not Rated'); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="status-badge status-<?php echo htmlspecialchars($booking['status']); ?>">
                                                        <?php echo ucfirst($booking['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M j', strtotime($booking['created_at'])); ?></td>
                                                <td>
                                                    <a href="assessment-bookings.php?action=view&id=<?php echo $booking['id']; ?>" class="btn btn-primary btn-sm">View</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">No assessment bookings found</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h3>Quick Actions</h3>
                </div>
                <div class="card-body">
                    <div class="quick-actions">
                        <a href="visa-applications.php" class="btn btn-primary">Visa Apps</a>
                        <a href="assessment-bookings.php" class="btn btn-primary">Assessments</a>
                        <a href="bookings.php" class="btn btn-primary">Bookings</a>
                        <a href="users.php" class="btn btn-primary">Users</a>
                        <a href="flights.php" class="btn btn-primary">Flights</a>
                        <a href="settings.php" class="btn btn-primary">Settings</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>