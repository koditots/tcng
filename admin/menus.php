<?php
// admin/menus.php
require_once '../config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

$page_title = "Menu Management";

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_menu'])) {
        $title = sanitize($_POST['title']);
        $url = sanitize($_POST['url']);
        $parent_id = intval($_POST['parent_id']);
        $menu_order = intval($_POST['menu_order']);
        $menu_location = sanitize($_POST['menu_location']);
        
        $stmt = $pdo->prepare("INSERT INTO menus (title, url, parent_id, menu_order, menu_location) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$title, $url, $parent_id, $menu_order, $menu_location]);
        
        $success = "Menu item added successfully!";
    }
    
    if (isset($_POST['update_menu'])) {
        $menu_id = intval($_POST['menu_id']);
        $title = sanitize($_POST['title']);
        $url = sanitize($_POST['url']);
        $parent_id = intval($_POST['parent_id']);
        $menu_order = intval($_POST['menu_order']);
        $menu_location = sanitize($_POST['menu_location']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $stmt = $pdo->prepare("UPDATE menus SET title = ?, url = ?, parent_id = ?, menu_order = ?, menu_location = ?, is_active = ? WHERE id = ?");
        $stmt->execute([$title, $url, $parent_id, $menu_order, $menu_location, $is_active, $menu_id]);
        
        $success = "Menu item updated successfully!";
    }
}

if (isset($_GET['action']) && isset($_GET['id'])) {
    $menu_id = intval($_GET['id']);
    $action = sanitize($_GET['action']);
    
    if ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM menus WHERE id = ?");
        $stmt->execute([$menu_id]);
        $success = "Menu item deleted successfully!";
    } elseif ($action === 'toggle_active') {
        $stmt = $pdo->prepare("UPDATE menus SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$menu_id]);
    }
}

// Get all menus
$stmt = $pdo->query("SELECT * FROM menus ORDER BY menu_location, menu_order");
$menus = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get menu for editing
$edit_menu = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM menus WHERE id = ?");
    $stmt->execute([intval($_GET['edit'])]);
    $edit_menu = $stmt->fetch(PDO::FETCH_ASSOC);
}

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
        
        .main-content { flex: 1; margin-left: 250px; padding: 0; }
        .top-bar { background: white; padding: 1rem 2rem; box-shadow: 0 2px 10px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .content { padding: 2rem; }
        
        .card { background: white; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); margin-bottom: 2rem; }
        .card-header { padding: 1.5rem; background: #f8f9fa; border-bottom: 1px solid #dee2e6; }
        .card-body { padding: 1.5rem; }
        
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: bold; color: #333; }
        .form-control { width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primary { background: #007bff; color: white; }
        .btn-sm { padding: 0.25rem 0.5rem; font-size: 0.8rem; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-warning { background: #ffc107; color: #333; }
        
        .alert { padding: 1rem; border-radius: 5px; margin-bottom: 1rem; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #dee2e6; }
        th { background: #f8f9fa; font-weight: bold; color: #333; }
        
        .badge { padding: 0.25rem 0.5rem; border-radius: 15px; font-size: 0.8rem; font-weight: bold; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-primary { background: #d1edff; color: #004085; }
        .badge-warning { background: #fff3cd; color: #856404; }
    </style>
</head>
<body>
   <!-- Include Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-bar">
            <h1>Menu Management</h1>
            <div>
                <span>Welcome, <?php echo $_SESSION['user_name']; ?></span>
            </div>
        </div>

        <div class="content">
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                <!-- Add/Edit Menu Form -->
                <div class="card">
                    <div class="card-header">
                        <h3><?php echo $edit_menu ? 'Edit Menu Item' : 'Add New Menu Item'; ?></h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <?php if ($edit_menu): ?>
                                <input type="hidden" name="menu_id" value="<?php echo $edit_menu['id']; ?>">
                                <input type="hidden" name="update_menu" value="1">
                            <?php else: ?>
                                <input type="hidden" name="add_menu" value="1">
                            <?php endif; ?>
                            
                            <div class="form-group">
                                <label>Menu Title</label>
                                <input type="text" name="title" class="form-control" value="<?php echo $edit_menu['title'] ?? ''; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label>URL</label>
                                <input type="text" name="url" class="form-control" value="<?php echo $edit_menu['url'] ?? ''; ?>" required>
                                <small style="color: #666;">Example: /about.php or https://example.com</small>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Parent Menu</label>
                                    <select name="parent_id" class="form-control">
                                        <option value="0">No Parent (Top Level)</option>
                                        <?php
                                        $top_menus = array_filter($menus, function($menu) {
                                            return $menu['parent_id'] == 0;
                                        });
                                        foreach ($top_menus as $menu) {
                                            $selected = ($edit_menu['parent_id'] ?? 0) == $menu['id'] ? 'selected' : '';
                                            echo '<option value="' . $menu['id'] . '" ' . $selected . '>' . $menu['title'] . '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label>Menu Order</label>
                                    <input type="number" name="menu_order" class="form-control" value="<?php echo $edit_menu['menu_order'] ?? 0; ?>" min="0">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Menu Location</label>
                                <select name="menu_location" class="form-control" required>
                                    <option value="header" <?php echo ($edit_menu['menu_location'] ?? '') === 'header' ? 'selected' : ''; ?>>Header</option>
                                    <option value="footer" <?php echo ($edit_menu['menu_location'] ?? '') === 'footer' ? 'selected' : ''; ?>>Footer</option>
                                    <option value="sidebar" <?php echo ($edit_menu['menu_location'] ?? '') === 'sidebar' ? 'selected' : ''; ?>>Sidebar</option>
                                </select>
                            </div>
                            
                            <?php if ($edit_menu): ?>
                            <div class="form-group">
                                <label>
                                    <input type="checkbox" name="is_active" value="1" <?php echo $edit_menu['is_active'] ? 'checked' : ''; ?>>
                                    Active
                                </label>
                            </div>
                            <?php endif; ?>
                            
                            <button type="submit" class="btn btn-primary">
                                <?php echo $edit_menu ? 'Update Menu Item' : 'Add Menu Item'; ?>
                            </button>
                            
                            <?php if ($edit_menu): ?>
                                <a href="menus.php" class="btn" style="background: #6c757d; color: white; margin-left: 1rem;">Cancel</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <!-- Menu List -->
                <div class="card">
                    <div class="card-header">
                        <h3>Menu Items</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($menus)): ?>
                            <div style="overflow-x: auto;">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>URL</th>
                                            <th>Location</th>
                                            <th>Order</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        function displayMenuItems($menus, $parent_id = 0, $level = 0) {
                                            $items = array_filter($menus, function($menu) use ($parent_id) {
                                                return $menu['parent_id'] == $parent_id;
                                            });
                                            
                                            usort($items, function($a, $b) {
                                                return $a['menu_order'] - $b['menu_order'];
                                            });
                                            
                                            foreach ($items as $menu) {
                                                $padding = $level * 20;
                                                echo '<tr>';
                                                echo '<td style="padding-left: ' . $padding . 'px;">';
                                                if ($level > 0) echo '↳ ';
                                                echo $menu['title'] . '</td>';
                                                echo '<td>' . $menu['url'] . '</td>';
                                                echo '<td><span class="badge badge-primary">' . ucfirst($menu['menu_location']) . '</span></td>';
                                                echo '<td>' . $menu['menu_order'] . '</td>';
                                                echo '<td><span class="badge ' . ($menu['is_active'] ? 'badge-success' : 'badge-danger') . '">' . ($menu['is_active'] ? 'Active' : 'Inactive') . '</span></td>';
                                                echo '<td>';
                                                echo '<div style="display: flex; gap: 0.25rem;">';
                                                echo '<a href="menus.php?edit=' . $menu['id'] . '" class="btn btn-primary btn-sm">Edit</a>';
                                                echo '<a href="menus.php?action=toggle_active&id=' . $menu['id'] . '" class="btn btn-warning btn-sm">' . ($menu['is_active'] ? 'Deactivate' : 'Activate') . '</a>';
                                                echo '<a href="menus.php?action=delete&id=' . $menu['id'] . '" class="btn btn-danger btn-sm" onclick="return confirm(\'Are you sure?\')">Delete</a>';
                                                echo '</div>';
                                                echo '</td>';
                                                echo '</tr>';
                                                
                                                // Display child items
                                                displayMenuItems($menus, $menu['id'], $level + 1);
                                            }
                                        }
                                        
                                        displayMenuItems($menus);
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p style="text-align: center; color: #666; padding: 2rem;">No menu items found.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>