<?php
// admin/edit-user.php
require_once '../config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

$page_title = "Edit User";

// Get user ID from URL
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$user_id) {
    redirect('users.php');
}

// Get user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $_SESSION['error'] = "User not found.";
    redirect('users.php');
}

$success = '';
$error = '';

// Update user if form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = sanitize($_POST['first_name']);
    $last_name = sanitize($_POST['last_name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $date_of_birth = sanitize($_POST['date_of_birth']);
    $gender = sanitize($_POST['gender']);
    $status = sanitize($_POST['status']);
    $role = sanitize($_POST['role']);
    
    // Validate required fields
    if (empty($first_name) || empty($last_name) || empty($email)) {
        $error = "First name, last name, and email are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        try {
            // Check if email already exists (excluding current user)
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $user_id]);
            $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing_user) {
                $error = "Email address is already registered by another user.";
            } else {
                // Check which columns exist in the database
                $stmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'date_of_birth'");
                $stmt->execute();
                $dob_column_exists = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $stmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'gender'");
                $stmt->execute();
                $gender_column_exists = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $stmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'status'");
                $stmt->execute();
                $status_column_exists = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $stmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'phone'");
                $stmt->execute();
                $phone_column_exists = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Build dynamic UPDATE query based on available columns
                $update_fields = ["first_name = ?", "last_name = ?", "email = ?", "role = ?", "updated_at = NOW()"];
                $update_values = [$first_name, $last_name, $email, $role];
                
                if ($phone_column_exists) {
                    $update_fields[] = "phone = ?";
                    $update_values[] = $phone;
                }
                
                if ($dob_column_exists) {
                    $update_fields[] = "date_of_birth = ?";
                    $update_values[] = $date_of_birth;
                }
                
                if ($gender_column_exists) {
                    $update_fields[] = "gender = ?";
                    $update_values[] = $gender;
                }
                
                if ($status_column_exists) {
                    $update_fields[] = "status = ?";
                    $update_values[] = $status;
                }
                
                $update_values[] = $user_id; // For WHERE clause
                
                $sql = "UPDATE users SET " . implode(", ", $update_fields) . " WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($update_values);
                
                $success = "User updated successfully!";
                
                // Refresh user data
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {
            $error = "Error updating user: " . $e->getMessage();
        }
    }
}

// Get user statistics
$stmt = $pdo->prepare("SELECT 
    COUNT(*) as total_bookings,
    SUM(CASE WHEN status IN ('confirmed', 'paid') THEN 1 ELSE 0 END) as confirmed_bookings,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_bookings,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings,
    SUM(CASE WHEN status IN ('confirmed', 'paid') THEN total_amount ELSE 0 END) as total_spent
    FROM flight_bookings WHERE user_id = ?");
$stmt->execute([$user_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

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
        .card-header { padding: 1.5rem; background: #f8f9fa; border-bottom: 1px solid #dee2e6; }
        .card-body { padding: 1.5rem; }
        
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: bold; color: #333; }
        .form-control { width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; font-size: 1rem; }
        .form-control:focus { border-color: #007bff; outline: none; box-shadow: 0 0 0 2px rgba(0,123,255,0.25); }
        
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 1rem; }
        .btn-primary { background: #007bff; color: white; }
        .btn-primary:hover { background: #0056b3; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        
        .alert { padding: 1rem; border-radius: 5px; margin-bottom: 1rem; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .user-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .stat-card { background: #f8f9fa; padding: 1rem; border-radius: 8px; text-align: center; border-left: 4px solid #007bff; }
        .stat-number { font-size: 1.5rem; font-weight: bold; color: #007bff; }
        .stat-label { color: #666; font-size: 0.9rem; }
        
        .user-avatar { 
            width: 80px; 
            height: 80px; 
            background: #007bff; 
            border-radius: 50%; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 2rem; 
            color: white; 
            font-weight: bold;
            margin: 0 auto 1rem;
        }
        
        .action-buttons { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        
        .field-note {
            font-size: 0.8rem;
            color: #666;
            margin-top: 0.25rem;
            font-style: italic;
        }
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
            <div>
                <a href="users.php" class="btn btn-secondary" style="text-decoration: none;">← Back to Users</a>
            </div>
            <h1>Edit User</h1>
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

            <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 2rem;">
                <!-- User Summary -->
                <div>
                    <!-- User Profile -->
                    <div class="card">
                        <div class="card-body" style="text-align: center;">
                            <div class="user-avatar">
                                <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                            </div>
                            <h3 style="color: #333; margin-bottom: 0.5rem;"><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></h3>
                            <p style="color: #666; margin-bottom: 1rem;"><?php echo $user['email']; ?></p>
                            <div style="background: #e7f3ff; padding: 0.5rem; border-radius: 5px; font-size: 0.9rem;">
                                Member since <?php echo date('M Y', strtotime($user['created_at'])); ?>
                            </div>
                        </div>
                    </div>

                    <!-- User Statistics -->
                    <div class="card">
                        <div class="card-header">
                            <h3>User Statistics</h3>
                        </div>
                        <div class="card-body">
                            <div class="user-stats">
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
                            </div>
                            <?php if ($stats['total_spent'] > 0): ?>
                                <div style="text-align: center; padding: 1rem; background: #f8f9fa; border-radius: 5px;">
                                    <strong>Total Spent:</strong> ₦<?php echo number_format($stats['total_spent'], 2); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="card">
                        <div class="card-header">
                            <h3>Quick Actions</h3>
                        </div>
                        <div class="card-body">
                            <div style="display: grid; gap: 0.5rem;">
                                <a href="../profile.php?id=<?php echo $user_id; ?>" target="_blank" class="btn btn-secondary" style="text-align: center; text-decoration: none;">
                                    👤 View Public Profile
                                </a>
                                <a href="user-bookings.php?id=<?php echo $user_id; ?>" class="btn btn-secondary" style="text-align: center; text-decoration: none;">
                                    📋 View Bookings
                                </a>
                                <button onclick="resetPassword()" class="btn btn-secondary">
                                    🔒 Reset Password
                                </button>
                                <?php if (($user['status'] ?? 'active') === 'active'): ?>
                                    <button onclick="suspendUser()" class="btn btn-danger">
                                        ⚠️ Suspend User
                                    </button>
                                <?php else: ?>
                                    <button onclick="activateUser()" class="btn btn-primary">
                                        ✅ Activate User
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Edit User Form -->
                <div>
                    <div class="card">
                        <div class="card-header">
                            <h3>Edit User Information</h3>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                                    <div class="form-group">
                                        <label for="first_name">First Name *</label>
                                        <input type="text" id="first_name" name="first_name" class="form-control" 
                                               value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="last_name">Last Name *</label>
                                        <input type="text" id="last_name" name="last_name" class="form-control" 
                                               value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="email">Email Address *</label>
                                    <input type="email" id="email" name="email" class="form-control" 
                                           value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>

                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                                    <div class="form-group">
                                        <label for="phone">Phone Number</label>
                                        <input type="tel" id="phone" name="phone" class="form-control" 
                                               value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                        <div class="field-note">Optional - field may not be available in database</div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="date_of_birth">Date of Birth</label>
                                        <input type="date" id="date_of_birth" name="date_of_birth" class="form-control" 
                                               value="<?php echo htmlspecialchars($user['date_of_birth'] ?? ''); ?>">
                                        <div class="field-note">Optional - field may not be available in database</div>
                                    </div>
                                </div>

                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                                    <div class="form-group">
                                        <label for="gender">Gender</label>
                                        <select id="gender" name="gender" class="form-control">
                                            <option value="">Select Gender</option>
                                            <option value="male" <?php echo ($user['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                                            <option value="female" <?php echo ($user['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                                            <option value="other" <?php echo ($user['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                        <div class="field-note">Optional - field may not be available in database</div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="status">Account Status</label>
                                        <select id="status" name="status" class="form-control">
                                            <option value="active" <?php echo ($user['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo ($user['status'] ?? 'active') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                            <option value="suspended" <?php echo ($user['status'] ?? 'active') === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                        </select>
                                        <div class="field-note">Optional - field may not be available in database</div>
                                    </div>
                                </div>

                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                                    <div class="form-group">
                                        <label for="role">User Role</label>
                                        <select id="role" name="role" class="form-control">
                                            <option value="user" <?php echo ($user['role'] ?? 'user') === 'user' ? 'selected' : ''; ?>>User</option>
                                            <option value="admin" <?php echo ($user['role'] ?? 'user') === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                            <option value="agent" <?php echo ($user['role'] ?? 'user') === 'agent' ? 'selected' : ''; ?>>Travel Agent</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>User ID</label>
                                        <div style="padding: 0.75rem; background: #f8f9fa; border-radius: 5px; color: #666; font-family: 'Courier New', monospace;">
                                            <?php echo $user['id']; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Account Information</label>
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; background: #f8f9fa; padding: 1rem; border-radius: 5px;">
                                        <div>
                                            <strong>Created:</strong><br>
                                            <?php echo date('F j, Y g:i A', strtotime($user['created_at'])); ?>
                                        </div>
                                        <div>
                                            <strong>Last Updated:</strong><br>
                                            <?php echo $user['updated_at'] ? date('F j, Y g:i A', strtotime($user['updated_at'])) : 'Never'; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="action-buttons">
                                    <button type="submit" class="btn btn-primary">Update User</button>
                                    <button type="reset" class="btn btn-secondary">Reset Changes</button>
                                    <a href="users.php" class="btn btn-secondary">Cancel</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function resetPassword() {
            if (confirm('Are you sure you want to reset this user\'s password? A temporary password will be emailed to them.')) {
                // In a real implementation, you would make an AJAX call here
                alert('Password reset functionality would be implemented here. A temporary password would be generated and emailed to the user.');
            }
        }
        
        function suspendUser() {
            if (confirm('Are you sure you want to suspend this user? They will not be able to login or make bookings.')) {
                document.getElementById('status').value = 'suspended';
                document.querySelector('form').submit();
            }
        }
        
        function activateUser() {
            if (confirm('Are you sure you want to activate this user?')) {
                document.getElementById('status').value = 'active';
                document.querySelector('form').submit();
            }
        }
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const firstName = document.getElementById('first_name').value.trim();
            const lastName = document.getElementById('last_name').value.trim();
            const email = document.getElementById('email').value.trim();
            
            if (!firstName || !lastName || !email) {
                e.preventDefault();
                alert('Please fill in all required fields (marked with *).');
                return false;
            }
            
            if (!validateEmail(email)) {
                e.preventDefault();
                alert('Please enter a valid email address.');
                return false;
            }
        });
        
        function validateEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }
    </script>
</body>
</html>