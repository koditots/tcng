<?php
// page.php
require_once 'config.php';

$slug = isset($_GET['slug']) ? sanitize($_GET['slug']) : '';

if (empty($slug)) {
    redirect('index.php');
}

// Get page content
$stmt = $pdo->prepare("SELECT * FROM pages WHERE slug = ? AND is_active = TRUE");
$stmt->execute([$slug]);
$page = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$page) {
    $page_title = "Page Not Found";
    require_once 'includes/header.php';
    echo '<div class="container" style="text-align: center; padding: 4rem 0;">';
    echo '<h1>Page Not Found</h1>';
    echo '<p>The page you are looking for does not exist.</p>';
    echo '<a href="index.php" class="btn btn-primary">Go Home</a>';
    echo '</div>';
    require_once 'includes/footer.php';
    exit;
}

$page_title = $page['meta_title'] ?: $page['title'];
$meta_description = $page['meta_description'];
$meta_keywords = $page['meta_keywords'];

require_once 'includes/header.php';
?>

<div class="container">
    <div style="max-width: 800px; margin: 2rem auto; background: white; padding: 3rem; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
        <h1 style="color: #333; margin-bottom: 2rem; text-align: center;"><?php echo $page['title']; ?></h1>
        
        <div style="line-height: 1.8; color: #555;">
            <?php echo nl2br($page['content']); ?>
        </div>
        
        <?php if (isLoggedIn() && isAdmin()): ?>
        <div style="margin-top: 3rem; padding-top: 2rem; border-top: 1px solid #eee; text-align: center;">
            <a href="admin/pages.php?edit=<?php echo $page['id']; ?>" class="btn btn-primary">Edit This Page</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>