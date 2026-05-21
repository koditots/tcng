<?php
// admin/email-templates.php
require_once '../config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

$page_title = "Email Templates";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_template'])) {
        $template_id = intval($_POST['template_id']);
        $subject = sanitize($_POST['subject']);
        $content = $_POST['content'];
        
        $stmt = $pdo->prepare("UPDATE email_templates SET subject = ?, content = ? WHERE id = ?");
        $stmt->execute([$subject, $content, $template_id]);
        
        $success = "Template updated successfully!";
    }
}

// Get all email templates
$stmt = $pdo->query("SELECT * FROM email_templates ORDER BY name");
$templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        
        .template-list { display: grid; gap: 1rem; }
        .template-item { border: 1px solid #dee2e6; border-radius: 5px; padding: 1rem; cursor: pointer; transition: all 0.3s; }
        .template-item:hover { border-color: #007bff; background: #f8f9fa; }
        .template-item.active { border-color: #007bff; background: #e7f1ff; }
        
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: bold; color: #333; }
        .form-control { width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; }
        textarea.form-control { min-height: 200px; resize: vertical; }
        
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primary { background: #007bff; color: white; }
        
        .alert { padding: 1rem; border-radius: 5px; margin-bottom: 1rem; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        
        .variables { background: #f8f9fa; padding: 1rem; border-radius: 5px; margin-bottom: 1rem; }
        .variable-item { display: inline-block; background: #e9ecef; padding: 0.25rem 0.5rem; border-radius: 3px; margin: 0.25rem; font-size: 0.9rem; }
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
            <li><a href="email-templates.php" class="active">✉️ Email Templates</a></li>
            <li><a href="../logout.php">🚪 Logout</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-bar">
            <h1>Email Templates</h1>
            <div>
                <span>Welcome, <?php echo $_SESSION['user_name']; ?></span>
            </div>
        </div>

        <div class="content">
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 2rem;">
                <!-- Template List -->
                <div class="card">
                    <div class="card-header">
                        <h3>Available Templates</h3>
                    </div>
                    <div class="card-body">
                        <div class="template-list">
                            <?php foreach ($templates as $template): ?>
                                <div class="template-item <?php echo $template['id'] == ($_GET['id'] ?? $templates[0]['id']) ? 'active' : ''; ?>" 
                                     onclick="location.href='email-templates.php?id=<?php echo $template['id']; ?>'">
                                    <strong><?php echo ucfirst(str_replace('_', ' ', $template['name'])); ?></strong>
                                    <p style="color: #666; margin: 0.5rem 0 0 0; font-size: 0.9rem;"><?php echo $template['subject']; ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Template Editor -->
                <div class="card">
                    <div class="card-header">
                        <h3>Edit Template</h3>
                    </div>
                    <div class="card-body">
                        <?php
                        $current_template_id = $_GET['id'] ?? $templates[0]['id'];
                        $current_template = null;
                        foreach ($templates as $template) {
                            if ($template['id'] == $current_template_id) {
                                $current_template = $template;
                                break;
                            }
                        }
                        
                        if ($current_template):
                        ?>
                            <form method="POST" action="">
                                <input type="hidden" name="template_id" value="<?php echo $current_template['id']; ?>">
                                <input type="hidden" name="update_template" value="1">
                                
                                <div class="form-group">
                                    <label>Template Name</label>
                                    <input type="text" class="form-control" value="<?php echo ucfirst(str_replace('_', ' ', $current_template['name'])); ?>" readonly>
                                </div>
                                
                                <div class="form-group">
                                    <label>Email Subject</label>
                                    <input type="text" name="subject" class="form-control" value="<?php echo $current_template['subject']; ?>" required>
                                </div>
                                
                                <?php if ($current_template['variables']): ?>
                                <div class="variables">
                                    <strong>Available Variables:</strong><br>
                                    <?php
                                    $variables = explode(',', $current_template['variables']);
                                    foreach ($variables as $variable) {
                                        echo '<span class="variable-item">{{' . trim($variable) . '}}</span>';
                                    }
                                    ?>
                                    <p style="margin: 0.5rem 0 0 0; font-size: 0.9rem; color: #666;">
                                        Use these variables in your template. They will be replaced with actual values when sending emails.
                                    </p>
                                </div>
                                <?php endif; ?>
                                
                                <div class="form-group">
                                    <label>Email Content (HTML)</label>
                                    <textarea name="content" class="form-control" required><?php echo $current_template['content']; ?></textarea>
                                    <small style="color: #666;">You can use HTML tags for formatting.</small>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Update Template</button>
                            </form>
                        <?php else: ?>
                            <p style="text-align: center; color: #666; padding: 2rem;">Select a template to edit.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>