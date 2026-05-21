<?php
session_start();
require_once 'config.php';

try {
    $stmt = $pdo->query("SELECT * FROM site_settings ORDER BY id DESC LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<h2>Database Test</h2>";
    echo "<pre>";
    print_r($settings);
    echo "</pre>";
    
    if ($settings) {
        echo "<p style='color: green;'>✅ Database connection successful and settings found!</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ Database connected but no settings found. Run the SQL above to create default settings.</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database error: " . $e->getMessage() . "</p>";
}
?>