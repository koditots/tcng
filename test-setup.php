<?php
// test-setup.php
echo "<h1>Testing Travel Centre Setup</h1>";

try {
    // Test database connection
    require_once 'config.php';
    echo "<p style='color: green;'>✅ Database connection successful</p>";
    
    // Test if tables exist
    $tables = ['users', 'site_settings', 'flight_bookings', 'pages', 'menus'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->fetch()) {
            echo "<p style='color: green;'>✅ Table '$table' exists</p>";
        } else {
            echo "<p style='color: red;'>❌ Table '$table' missing</p>";
        }
    }
    
    // Test site settings
    $site_name = getSiteSetting($pdo, 'site_name');
    if ($site_name) {
        echo "<p style='color: green;'>✅ Site settings loaded: $site_name</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ No site settings found (this is normal for fresh install)</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h2>Next Steps:</h2>";
echo "<ol>";
echo "<li>Run the SQL script to create database tables</li>";
echo "<li>Visit <a href='index.php'>index.php</a> to test the homepage</li>";
echo "<li>Visit <a href='login.php'>login.php</a> to test authentication</li>";
echo "<li>Use admin/admin to login as admin</li>";
echo "</ol>";
?>