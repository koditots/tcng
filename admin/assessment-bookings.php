<?php
// admin/assessment-bookings.php
require_once '../config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

$page_title = "Manage Assessment Bookings";

// Check if visa_assessment_bookings table exists
$table_exists = false;
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'visa_assessment_bookings'");
    $table_exists = $stmt->rowCount() > 0;
} catch (PDOException $e) {
    $table_exists = false;
}

// If table doesn't exist, show error message
if (!$table_exists) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo $page_title; ?> - <?php echo getSiteSetting($pdo, 'site_name'); ?></title>
        <style>
            body { font-family: Arial, sans-serif; background: #f8f9fa; margin: 0; padding: 2rem; }
            .alert { background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 5px; margin-bottom: 1rem; }
            .card { background: white; padding: 2rem; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .btn { background: #007bff; color: white; padding: 0.5rem 1rem; text-decoration: none; border-radius: 5px; display: inline-block; }
        </style>
    </head>
    <body>
        <div class="alert">
            <strong>Error:</strong> The visa_assessment_bookings table does not exist in the database.
        </div>
        <div class="card">
            <h2>Setup Required</h2>
            <p>To use the assessment bookings feature, you need to create the database table.</p>
            <p>Run the following SQL query in your database:</p>
            <pre style="background: #f8f9fa; padding: 1rem; border-radius: 5px; overflow-x: auto;">
CREATE TABLE visa_assessment_bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_number VARCHAR(50) NOT NULL,
    first_name VARCHAR(255) NOT NULL,
    last_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    readiness_score INT NOT NULL,
    readiness_grade ENUM('low', 'medium', 'high') NOT NULL,
    booking_fee DECIMAL(10,2) NOT NULL,
    currency VARCHAR(10) DEFAULT 'NGN',
    payment_link TEXT NULL,
    assessment_data TEXT NULL,
    status ENUM('pending', 'completed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL
);</pre>
            <p>After creating the table, <a href="assessment-bookings.php" class="btn">Refresh this page</a></p>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Handle actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $booking_id = $_GET['id'] ?? null;
    
    if ($action === 'update_status' && $booking_id) {
        $new_status = $_POST['status'];
        $admin_notes = $_POST['admin_notes'] ?? '';
        
        $stmt = $pdo->prepare("UPDATE visa_assessment_bookings SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$new_status, $booking_id]);
        $_SESSION['success_message'] = "Assessment booking status updated successfully";
    } 
    elseif ($action === 'delete' && $booking_id) {
        $stmt = $pdo->prepare("DELETE FROM visa_assessment_bookings WHERE id = ?");
        $stmt->execute([$booking_id]);
        $_SESSION['success_message'] = "Assessment booking deleted successfully";
    }
    
    redirect('assessment-bookings.php');
}

// Get filter parameters
$filter = $_GET['filter'] ?? 'all';
$grade_filter = $_GET['grade'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query for assessment bookings
$query = "
    SELECT 
        vab.*,
        CASE 
            WHEN vab.readiness_grade = 'low' THEN 'Low Chance'
            WHEN vab.readiness_grade = 'medium' THEN 'Medium Chance'
            WHEN vab.readiness_grade = 'high' THEN 'High Chance'
            ELSE 'Not Rated'
        END as grade_display
    FROM visa_assessment_bookings vab 
    WHERE 1=1
";

$params = [];

// Apply filters
if ($filter !== 'all') {
    $query .= " AND vab.status = ?";
    $params[] = $filter;
}

if ($grade_filter !== 'all') {
    $query .= " AND vab.readiness_grade = ?";
    $params[] = $grade_filter;
}

if (!empty($search)) {
    $query .= " AND (vab.first_name LIKE ? OR vab.last_name LIKE ? OR vab.email LIKE ? OR vab.application_number LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, array_fill(0, 4, $search_term));
}

$query .= " ORDER BY vab.created_at DESC";

// Get total count for pagination
$count_query = "SELECT COUNT(*) FROM ($query) as count_table";
$stmt = $pdo->prepare($count_query);
$stmt->execute($params);
$total_bookings = $stmt->fetchColumn();

// Pagination
$per_page = 10;
$total_pages = ceil($total_bookings / $per_page);
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $per_page;

$query .= " LIMIT $offset, $per_page";

// Execute main query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics for filters
$total_bookings_count = $pdo->query("SELECT COUNT(*) FROM visa_assessment_bookings")->fetchColumn();
$pending_bookings_count = $pdo->query("SELECT COUNT(*) FROM visa_assessment_bookings WHERE status = 'pending'")->fetchColumn();
$completed_bookings_count = $pdo->query("SELECT COUNT(*) FROM visa_assessment_bookings WHERE status = 'completed'")->fetchColumn();
$cancelled_bookings_count = $pdo->query("SELECT COUNT(*) FROM visa_assessment_bookings WHERE status = 'cancelled'")->fetchColumn();

// Get grade statistics
$low_grade_count = $pdo->query("SELECT COUNT(*) FROM visa_assessment_bookings WHERE readiness_grade = 'low'")->fetchColumn();
$medium_grade_count = $pdo->query("SELECT COUNT(*) FROM visa_assessment_bookings WHERE readiness_grade = 'medium'")->fetchColumn();
$high_grade_count = $pdo->query("SELECT COUNT(*) FROM visa_assessment_bookings WHERE readiness_grade = 'high'")->fetchColumn();

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
            background-color: #f8f9fa;
            display: flex;
        }
        
       
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 0;
        }
        
        .top-bar {
            background: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .content {
            padding: 2rem;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .card-header {
            padding: 1.5rem;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-header h3 {
            margin: 0;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        
        th {
            background: #f8f9fa;
            font-weight: bold;
            color: #333;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0056b3;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #1e7e34;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-warning:hover {
            background: #e0a800;
        }
        
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
        }
        
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        
        .assessment-grade {
            padding: 0.25rem 0.5rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .grade-low { background: #f8d7da; color: #721c24; }
        .grade-medium { background: #fff3cd; color: #856404; }
        .grade-high { background: #d4edda; color: #155724; }
        
        .filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding: 1.5rem;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-group label {
            margin-bottom: 0.5rem;
            font-weight: bold;
            color: #333;
        }
        
        .filter-group select, .filter-group input {
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            list-style: none;
            margin-top: 1.5rem;
        }
        
        .pagination li {
            margin: 0 0.25rem;
        }
        
        .pagination a {
            display: block;
            padding: 0.5rem 1rem;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            text-decoration: none;
            color: #007bff;
        }
        
        .pagination a:hover, .pagination a.active {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        .booking-popup {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .booking-content {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            max-width: 800px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .booking-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .detail-group {
            margin-bottom: 1rem;
        }
        
        .detail-group label {
            font-weight: bold;
            display: block;
            margin-bottom: 0.25rem;
            color: #333;
        }
        
        .status-form {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #dee2e6;
        }
        
        .status-form select, .status-form textarea {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        
        .status-form textarea {
            min-height: 100px;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .assessment-answers {
            margin-top: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .answer-item {
            margin-bottom: 0.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #dee2e6;
        }
        
        .answer-item:last-child {
            border-bottom: none;
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
            <h1>Manage Assessment Bookings</h1>
            <div>
                <span>Welcome, <?php echo $_SESSION['user_name']; ?></span>
            </div>
        </div>

        <!-- Content -->
        <div class="content">
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="card">
                <div class="card-header">
                    <h3>Filters</h3>
                </div>
                <div class="card-body">
                    <form method="GET" class="filters">
                        <div class="filter-group">
                            <label for="filter">Booking Status</label>
                            <select name="filter" id="filter" onchange="this.form.submit()">
                                <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Bookings (<?php echo $total_bookings_count; ?>)</option>
                                <option value="pending" <?php echo $filter === 'pending' ? 'selected' : ''; ?>>Pending (<?php echo $pending_bookings_count; ?>)</option>
                                <option value="completed" <?php echo $filter === 'completed' ? 'selected' : ''; ?>>Completed (<?php echo $completed_bookings_count; ?>)</option>
                                <option value="cancelled" <?php echo $filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled (<?php echo $cancelled_bookings_count; ?>)</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="grade">Readiness Grade</label>
                            <select name="grade" id="grade" onchange="this.form.submit()">
                                <option value="all" <?php echo $grade_filter === 'all' ? 'selected' : ''; ?>>All Grades</option>
                                <option value="low" <?php echo $grade_filter === 'low' ? 'selected' : ''; ?>>Low Chance (<?php echo $low_grade_count; ?>)</option>
                                <option value="medium" <?php echo $grade_filter === 'medium' ? 'selected' : ''; ?>>Medium Chance (<?php echo $medium_grade_count; ?>)</option>
                                <option value="high" <?php echo $grade_filter === 'high' ? 'selected' : ''; ?>>High Chance (<?php echo $high_grade_count; ?>)</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="search">Search</label>
                            <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search names, emails, application numbers...">
                        </div>
                        
                        <div class="filter-group" style="justify-content: flex-end;">
                            <button type="submit" class="btn btn-primary" style="margin-top: 1.5rem;">Apply Filters</button>
                            <a href="assessment-bookings.php" class="btn btn-danger" style="margin-top: 1.5rem; margin-left: 0.5rem;">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Bookings Table -->
            <div class="card">
                <div class="card-header">
                    <h3>Assessment Bookings (<?php echo $total_bookings; ?>)</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($bookings)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Applicant Details</th>
                                    <th>Application No.</th>
                                    <th>Score</th>
                                    <th>Grade</th>
                                    <th>Fee</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bookings as $booking): ?>
                                    <tr>
                                        <td>
                                            <div><strong><?php echo $booking['first_name'] . ' ' . $booking['last_name']; ?></strong></div>
                                            <div style="font-size: 0.8rem; color: #666;"><?php echo $booking['email']; ?></div>
                                            <div style="font-size: 0.8rem; color: #666;"><?php echo $booking['phone']; ?></div>
                                        </td>
                                        <td><?php echo $booking['application_number']; ?></td>
                                        <td><?php echo $booking['readiness_score']; ?>%</td>
                                        <td>
                                            <span class="assessment-grade grade-<?php echo $booking['readiness_grade']; ?>">
                                                <?php echo $booking['grade_display']; ?>
                                            </span>
                                        </td>
                                        <td>₦<?php echo number_format($booking['booking_fee'], 2); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $booking['status']; ?>">
                                                <?php echo ucfirst($booking['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo date('M j, Y', strtotime($booking['created_at'])); ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn btn-primary btn-sm view-booking" 
                                                        data-booking='<?php echo htmlspecialchars(json_encode($booking), ENT_QUOTES, 'UTF-8'); ?>'>
                                                    View
                                                </button>
                                                <a href="assessment-bookings.php?action=delete&id=<?php echo $booking['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this booking?')">Delete</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <ul class="pagination">
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li>
                                        <a href="?page=<?php echo $i; ?>&filter=<?php echo $filter; ?>&grade=<?php echo $grade_filter; ?>&search=<?php echo urlencode($search); ?>" 
                                           class="<?php echo $i == $current_page ? 'active' : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        <?php endif; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: #666; padding: 2rem;">No assessment bookings found matching your criteria.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Booking Popup -->
    <div class="booking-popup" id="bookingPopup">
        <div class="booking-content">
            <h3>Assessment Booking Details</h3>
            <div id="bookingDetails"></div>
            <div class="status-form" id="statusForm">
                <form method="POST" action="assessment-bookings.php?action=update_status&id=" id="statusFormElement">
                    <div class="form-group">
                        <label for="status">Update Status:</label>
                        <select name="status" id="status" required>
                            <option value="pending">Pending</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="admin_notes">Admin Notes:</label>
                        <textarea name="admin_notes" id="admin_notes" placeholder="Add any notes or comments..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-success">Update Status</button>
                    <button type="button" class="btn btn-danger" onclick="closeBookingPopup()">Cancel</button>
                </form>
            </div>
            <button type="button" class="btn btn-primary" onclick="closeBookingPopup()" style="margin-top: 1rem;">Close</button>
        </div>
    </div>

    <script>
        function viewBooking(booking) {
            const popup = document.getElementById('bookingPopup');
            const bookingDetails = document.getElementById('bookingDetails');
            const statusForm = document.getElementById('statusForm');
            const statusFormElement = document.getElementById('statusFormElement');
            
            // Parse assessment data if available
            let assessmentData = null;
            let answersHtml = '';
            
            try {
                if (booking.assessment_data) {
                    assessmentData = JSON.parse(booking.assessment_data);
                    if (assessmentData.readiness_answers) {
                        answersHtml = '<div class="assessment-answers"><h4>Assessment Answers:</h4>';
                        Object.entries(assessmentData.readiness_answers).forEach(([key, answer]) => {
                            answersHtml += `
                                <div class="answer-item">
                                    <strong>${answer.question}</strong><br>
                                    Answer: ${answer.answer === 'yes' ? '✅ Yes' : '❌ No'}
                                </div>
                            `;
                        });
                        answersHtml += '</div>';
                    }
                }
            } catch (e) {
                console.error('Error parsing assessment data:', e);
            }
            
            bookingDetails.innerHTML = `
                <div class="booking-details">
                    <div class="detail-group">
                        <label>Applicant Name:</label>
                        <div>${booking.first_name} ${booking.last_name}</div>
                    </div>
                    <div class="detail-group">
                        <label>Email:</label>
                        <div>${booking.email}</div>
                    </div>
                    <div class="detail-group">
                        <label>Phone:</label>
                        <div>${booking.phone}</div>
                    </div>
                    <div class="detail-group">
                        <label>Application Number:</label>
                        <div>${booking.application_number}</div>
                    </div>
                    <div class="detail-group">
                        <label>Readiness Score:</label>
                        <div>${booking.readiness_score}%</div>
                    </div>
                    <div class="detail-group">
                        <label>Readiness Grade:</label>
                        <div><span class="assessment-grade grade-${booking.readiness_grade}">${booking.grade_display}</span></div>
                    </div>
                    <div class="detail-group">
                        <label>Booking Fee:</label>
                        <div>₦${parseFloat(booking.booking_fee).toLocaleString('en-US', {minimumFractionDigits: 2})}</div>
                    </div>
                    <div class="detail-group">
                        <label>Currency:</label>
                        <div>${booking.currency}</div>
                    </div>
                    <div class="detail-group">
                        <label>Current Status:</label>
                        <div><span class="status-badge status-${booking.status}">${booking.status.charAt(0).toUpperCase() + booking.status.slice(1)}</span></div>
                    </div>
                    <div class="detail-group">
                        <label>Booking Date:</label>
                        <div>${new Date(booking.created_at).toLocaleDateString()}</div>
                    </div>
                </div>
                ${answersHtml}
            `;
            
            statusFormElement.action = `assessment-bookings.php?action=update_status&id=${booking.id}`;
            document.getElementById('status').value = booking.status;
            document.getElementById('admin_notes').value = booking.admin_notes || '';
            
            popup.style.display = 'flex';
        }
        
        function closeBookingPopup() {
            document.getElementById('bookingPopup').style.display = 'none';
        }
        
        // Add event listeners to view buttons
        document.addEventListener('DOMContentLoaded', function() {
            const viewButtons = document.querySelectorAll('.view-booking');
            viewButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const bookingData = this.getAttribute('data-booking');
                    const booking = JSON.parse(bookingData);
                    viewBooking(booking);
                });
            });
        });
        
        // Close popup when clicking outside
        document.getElementById('bookingPopup').addEventListener('click', function(e) {
            if (e.target === this) {
                closeBookingPopup();
            }
        });
    </script>
</body>
</html>