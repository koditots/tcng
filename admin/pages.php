<?php
// admin/pages.php
require_once '../config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

$page_title = "Page Management";

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_page'])) {
        $title = sanitize($_POST['title']);
        $slug = sanitize($_POST['slug']);
        $content = $_POST['content'];
        $meta_title = sanitize($_POST['meta_title']);
        $meta_description = sanitize($_POST['meta_description']);
        $meta_keywords = sanitize($_POST['meta_keywords']);
        $show_in_menu = isset($_POST['show_in_menu']) ? 1 : 0;
        $menu_order = intval($_POST['menu_order']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Generate slug if empty
        if (empty($slug)) {
            $slug = strtolower(str_replace(' ', '-', $title));
        }
        
        $stmt = $pdo->prepare("INSERT INTO pages (title, slug, content, meta_title, meta_description, meta_keywords, show_in_menu, menu_order, is_active, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $slug, $content, $meta_title, $meta_description, $meta_keywords, $show_in_menu, $menu_order, $is_active, $_SESSION['user_id']]);
        
        $success = "Page created successfully!";
    }
    
    if (isset($_POST['update_page'])) {
        $page_id = intval($_POST['page_id']);
        $title = sanitize($_POST['title']);
        $slug = sanitize($_POST['slug']);
        $content = $_POST['content'];
        $meta_title = sanitize($_POST['meta_title']);
        $meta_description = sanitize($_POST['meta_description']);
        $meta_keywords = sanitize($_POST['meta_keywords']);
        $show_in_menu = isset($_POST['show_in_menu']) ? 1 : 0;
        $menu_order = intval($_POST['menu_order']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $stmt = $pdo->prepare("UPDATE pages SET title = ?, slug = ?, content = ?, meta_title = ?, meta_description = ?, meta_keywords = ?, show_in_menu = ?, menu_order = ?, is_active = ? WHERE id = ?");
        $stmt->execute([$title, $slug, $content, $meta_title, $meta_description, $meta_keywords, $show_in_menu, $menu_order, $is_active, $page_id]);
        
        $success = "Page updated successfully!";
    }
}

if (isset($_GET['action']) && isset($_GET['id'])) {
    $page_id = intval($_GET['id']);
    $action = sanitize($_GET['action']);
    
    if ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM pages WHERE id = ?");
        $stmt->execute([$page_id]);
        $success = "Page deleted successfully!";
    } elseif ($action === 'toggle_active') {
        $stmt = $pdo->prepare("UPDATE pages SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$page_id]);
    }
}

// Get all pages
$stmt = $pdo->query("SELECT p.*, u.first_name, u.last_name FROM pages p LEFT JOIN users u ON p.created_by = u.id ORDER BY p.created_at DESC");
$pages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get page for editing
$edit_page = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM pages WHERE id = ?");
    $stmt->execute([intval($_GET['edit'])]);
    $edit_page = $stmt->fetch(PDO::FETCH_ASSOC);
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
        textarea.form-control { min-height: 200px; resize: vertical; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .form-check { display: flex; align-items: center; gap: 0.5rem; }
        
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
        
        .tab-container { margin-bottom: 2rem; }
        .tab-buttons { display: flex; border-bottom: 1px solid #dee2e6; }
        .tab-button { padding: 1rem 2rem; background: none; border: none; cursor: pointer; border-bottom: 3px solid transparent; }
        .tab-button.active { border-bottom-color: #007bff; color: #007bff; font-weight: bold; }
        .tab-content { display: none; padding: 2rem 0; }
        .tab-content.active { display: block; }
    </style>
</head>
<body>
    <!-- Include Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-bar">
            <h1>Page Management</h1>
            <div>
                <span>Welcome, <?php echo $_SESSION['user_name']; ?></span>
            </div>
        </div>

        <div class="content">
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <div class="tab-container">
                <div class="tab-buttons">
                    <button class="tab-button active" onclick="openTab('pages-tab')">All Pages</button>
                    <button class="tab-button" onclick="openTab('add-page-tab')"><?php echo $edit_page ? 'Edit Page' : 'Add New Page'; ?></button>
                </div>

                <!-- Pages List Tab -->
                <div id="pages-tab" class="tab-content active">
                    <div class="card">
                        <div class="card-header">
                            <h3>All Pages (<?php echo count($pages); ?>)</h3>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($pages)): ?>
                                <div style="overflow-x: auto;">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Title</th>
                                                <th>Slug</th>
                                                <th>Menu</th>
                                                <th>Status</th>
                                                <th>Author</th>
                                                <th>Created</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pages as $page): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo $page['title']; ?></strong>
                                                        <?php if ($page['meta_title']): ?>
                                                            <br><small style="color: #666;">Meta: <?php echo $page['meta_title']; ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>/page.php?slug=<?php echo $page['slug']; ?></td>
                                                    <td>
                                                        <?php if ($page['show_in_menu']): ?>
                                                            <span class="badge badge-primary">Yes (<?php echo $page['menu_order']; ?>)</span>
                                                        <?php else: ?>
                                                            <span class="badge badge-warning">No</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge <?php echo $page['is_active'] ? 'badge-success' : 'badge-danger'; ?>">
                                                            <?php echo $page['is_active'] ? 'Active' : 'Inactive'; ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo $page['first_name'] . ' ' . $page['last_name']; ?></td>
                                                    <td><?php echo date('M j, Y', strtotime($page['created_at'])); ?></td>
                                                    <td>
                                                        <div style="display: flex; gap: 0.25rem;">
                                                            <a href="../page.php?slug=<?php echo $page['slug']; ?>" target="_blank" class="btn btn-primary btn-sm">View</a>
                                                            <a href="?edit=<?php echo $page['id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                                                            <a href="?action=toggle_active&id=<?php echo $page['id']; ?>" class="btn btn-<?php echo $page['is_active'] ? 'warning' : 'success'; ?> btn-sm">
                                                                <?php echo $page['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                                            </a>
                                                            <a href="?action=delete&id=<?php echo $page['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure?')">Delete</a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p style="text-align: center; color: #666; padding: 2rem;">No pages found.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Add/Edit Page Tab -->
                <div id="add-page-tab" class="tab-content">
                    <div class="card">
                        <div class="card-header">
                            <h3><?php echo $edit_page ? 'Edit Page' : 'Create New Page'; ?></h3>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <?php if ($edit_page): ?>
                                    <input type="hidden" name="page_id" value="<?php echo $edit_page['id']; ?>">
                                    <input type="hidden" name="update_page" value="1">
                                <?php else: ?>
                                    <input type="hidden" name="add_page" value="1">
                                <?php endif; ?>
                                
                                <div class="form-group">
                                    <label>Page Title *</label>
                                    <input type="text" name="title" class="form-control" value="<?php echo $edit_page['title'] ?? ''; ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label>Slug *</label>
                                    <input type="text" name="slug" class="form-control" value="<?php echo $edit_page['slug'] ?? ''; ?>" required>
                                    <small style="color: #666;">URL-friendly version of the title (e.g., about-us, contact)</small>
                                </div>
                                
                                <div class="form-group">
                                    <label>Page Content *</label>
                                    <textarea name="content" class="form-control" required><?php echo $edit_page['content'] ?? ''; ?></textarea>
                                    <small style="color: #666;">You can use HTML tags for formatting.</small>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Meta Title</label>
                                        <input type="text" name="meta_title" class="form-control" value="<?php echo $edit_page['meta_title'] ?? ''; ?>">
                                        <small style="color: #666;">Page title for SEO</small>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Menu Order</label>
                                        <input type="number" name="menu_order" class="form-control" value="<?php echo $edit_page['menu_order'] ?? 0; ?>" min="0">
                                        <small style="color: #666;">Display order in menu</small>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label>Meta Description</label>
                                    <textarea name="meta_description" class="form-control"><?php echo $edit_page['meta_description'] ?? ''; ?></textarea>
                                    <small style="color: #666;">Brief description for SEO (150-160 characters)</small>
                                </div>
                                
                                <div class="form-group">
                                    <label>Meta Keywords</label>
                                    <input type="text" name="meta_keywords" class="form-control" value="<?php echo $edit_page['meta_keywords'] ?? ''; ?>">
                                    <small style="color: #666;">Comma-separated keywords for SEO</small>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <div class="form-check">
                                            <input type="checkbox" name="show_in_menu" value="1" <?php echo ($edit_page['show_in_menu'] ?? 0) ? 'checked' : ''; ?>>
                                            <label>Show in navigation menu</label>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <div class="form-check">
                                            <input type="checkbox" name="is_active" value="1" <?php echo ($edit_page['is_active'] ?? 1) ? 'checked' : ''; ?>>
                                            <label>Page is active</label>
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <?php echo $edit_page ? 'Update Page' : 'Create Page'; ?>
                                </button>
                                
                                <?php if ($edit_page): ?>
                                    <a href="pages.php" class="btn" style="background: #6c757d; color: white; margin-left: 1rem;">Cancel</a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    function openTab(tabName) {
        // Hide all tab contents
        const tabContents = document.getElementsByClassName('tab-content');
        for (let i = 0; i < tabContents.length; i++) {
            tabContents[i].classList.remove('active');
        }
        
        // Remove active class from all tab buttons
        const tabButtons = document.getElementsByClassName('tab-button');
        for (let i = 0; i < tabButtons.length; i++) {
            tabButtons[i].classList.remove('active');
        }
        
        // Show the specific tab content and activate the button
        document.getElementById(tabName).classList.add('active');
        event.currentTarget.classList.add('active');
    }
    
    // Auto-generate slug from title
    document.querySelector('input[name="title"]')?.addEventListener('input', function() {
        const slugInput = document.querySelector('input[name="slug"]');
        if (!slugInput.value || slugInput.dataset.manual !== 'true') {
            const slug = this.value.toLowerCase()
                .replace(/ /g, '-')
                .replace(/[^\w-]+/g, '');
            slugInput.value = slug;
        }
    });
    
    // Mark slug as manually edited
    document.querySelector('input[name="slug"]')?.addEventListener('input', function() {
        this.dataset.manual = 'true';
    });
    
    // If editing, open the edit tab
    <?php if ($edit_page): ?>
    document.addEventListener('DOMContentLoaded', function() {
        openTab('add-page-tab');
    });
    <?php endif; ?>
    </script>
</body>
</html>