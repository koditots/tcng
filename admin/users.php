<?php
// admin/users.php
require_once '../config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

$page_title = "Manage Users";
$current_page = 'users'; // Set current page for sidebar

// Handle actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $user_id = intval($_GET['id']);
    $action = sanitize($_GET['action']);
    
    switch ($action) {
        case 'delete':
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND id != ?");
            $stmt->execute([$user_id, $_SESSION['user_id']]);
            break;
        case 'toggle_active':
            $stmt = $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?");
            $stmt->execute([$user_id]);
            break;
        case 'make_admin':
            $stmt = $pdo->prepare("UPDATE users SET role = 'admin' WHERE id = ?");
            $stmt->execute([$user_id]);
            break;
    }
    
    redirect('users.php');
}

// Get all users
$stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        
        /* Search Box */
        .search-box {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .search-box input {
            flex: 1;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 0.875rem;
            width: 100%;
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
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #1e7e34;
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
        
        /* Action buttons */
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
        
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-info {
            background: #d1edff;
            color: #004085;
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
            
            .card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
            }
            
            .search-box {
                flex-direction: column;
                gap: 0.75rem;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 0.125rem;
            }
            
            .btn-sm, .btn-xs {
                width: 100%;
                justify-content: center;
            }
            
            table {
                min-width: 700px;
            }
        }
        
        @media (max-width: 480px) {
            .card-body {
                padding: 1rem;
            }
            
            .card-header {
                padding: 0.75rem 1rem;
            }
            
            .search-box {
                margin-bottom: 1rem;
            }
            
            .table-container {
                margin: 0 -1rem;
                width: calc(100% + 2rem);
            }
            
            .top-bar {
                flex-direction: column;
                gap: 0.5rem;
                align-items: flex-start;
            }
            
            .top-bar h1 {
                font-size: 1.1rem;
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
            <h1>Manage Users</h1>
            <div>
                <span>Welcome, <?php echo $_SESSION['user_name']; ?></span>
            </div>
        </div>

        <div class="content">
            <div class="card">
                <div class="card-header">
                    <h3>All Users (<?php echo count($users); ?>)</h3>
                    <a href="add-user.php" class="btn btn-primary">Add New User</a>
                </div>
                <div class="card-body">
                    <div class="search-box">
                        <input type="text" id="searchInput" placeholder="Search users by name, email, or role..." onkeyup="searchUsers()">
                    </div>
                    
                    <?php if (!empty($users)): ?>
                        <div class="table-container">
                            <table id="usersTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Registered</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?php echo $user['id']; ?></td>
                                            <td>
                                                <strong><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></strong>
                                                <br>
                                                <small style="color: #666;">@<?php echo $user['username']; ?></small>
                                            </td>
                                            <td><?php echo $user['email']; ?></td>
                                            <td><?php echo $user['phone'] ?: 'N/A'; ?></td>
                                            <td>
                                                <span class="badge <?php echo $user['role'] === 'admin' ? 'badge-warning' : 'badge-info'; ?>">
                                                    <?php echo ucfirst($user['role']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $user['is_active'] ? 'badge-success' : 'badge-danger'; ?>">
                                                    <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="edit-user.php?id=<?php echo $user['id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                                                    
                                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                        <?php if ($user['role'] != 'admin'): ?>
                                                            <a href="users.php?action=make_admin&id=<?php echo $user['id']; ?>" class="btn btn-warning btn-sm" onclick="return confirm('Make this user an admin?')">Make Admin</a>
                                                        <?php endif; ?>
                                                        
                                                        <a href="users.php?action=toggle_active&id=<?php echo $user['id']; ?>" class="btn btn-<?php echo $user['is_active'] ? 'warning' : 'success'; ?> btn-sm">
                                                            <?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                                        </a>
                                                        
                                                        <a href="users.php?action=delete&id=<?php echo $user['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this user?')">Delete</a>
                                                    <?php else: ?>
                                                        <span style="color: #666; font-size: 0.8rem; white-space: nowrap;">Current User</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p style="text-align: center; color: #666; padding: 2rem;">No users found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
    function searchUsers() {
        const input = document.getElementById('searchInput');
        const filter = input.value.toLowerCase();
        const table = document.getElementById('usersTable');
        const tr = table.getElementsByTagName('tr');
        
        for (let i = 1; i < tr.length; i++) {
            const td = tr[i].getElementsByTagName('td');
            let found = false;
            
            for (let j = 0; j < td.length; j++) {
                if (td[j]) {
                    const txtValue = td[j].textContent || td[j].innerText;
                    if (txtValue.toLowerCase().indexOf(filter) > -1) {
                        found = true;
                        break;
                    }
                }
            }
            
            tr[i].style.display = found ? '' : 'none';
        }
    }
    </script>
</body>
</html>