<?php
// check_menus.php
require_once 'config.php';

echo "<h2>Menu Database Check</h2>";

try {
    // Check total menus
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM menus");
    $total = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p>Total menus in database: " . $total['total'] . "</p>";
    
    // Check active header menus
    $stmt = $pdo->query("SELECT COUNT(*) as active FROM menus WHERE menu_location = 'header' AND is_active = TRUE");
    $active = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p>Active header menus: " . $active['active'] . "</p>";
    
    // Check for duplicates
    $stmt = $pdo->query("
        SELECT title, url, menu_location, parent_id, COUNT(*) as count 
        FROM menus 
        WHERE is_active = TRUE 
        GROUP BY title, url, menu_location, parent_id 
        HAVING COUNT(*) > 1
    ");
    $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($duplicates) > 0) {
        echo "<h3 style='color: red;'>❌ Duplicate Menus Found:</h3>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Title</th><th>URL</th><th>Location</th><th>Parent ID</th><th>Count</th></tr>";
        foreach ($duplicates as $dup) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($dup['title']) . "</td>";
            echo "<td>" . htmlspecialchars($dup['url']) . "</td>";
            echo "<td>" . htmlspecialchars($dup['menu_location']) . "</td>";
            echo "<td>" . ($dup['parent_id'] ?: 'NULL') . "</td>";
            echo "<td>" . $dup['count'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<h3>Fix Options:</h3>";
        echo "<form method='POST'>";
        echo "<button type='submit' name='fix_duplicates'>Remove Duplicate Menus</button>";
        echo "<button type='submit' name='reset_menus'>Reset to Default Menus</button>";
        echo "</form>";
    } else {
        echo "<h3 style='color: green;'>✅ No duplicate menus found!</h3>";
    }
    
    // Show all current menus
    echo "<h3>Current Menus:</h3>";
    $stmt = $pdo->query("SELECT id, title, url, menu_location, parent_id, menu_order, is_active FROM menus ORDER BY menu_location, parent_id, menu_order");
    $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($menus) > 0) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Title</th><th>URL</th><th>Location</th><th>Parent</th><th>Order</th><th>Active</th></tr>";
        foreach ($menus as $menu) {
            echo "<tr>";
            echo "<td>" . $menu['id'] . "</td>";
            echo "<td>" . htmlspecialchars($menu['title']) . "</td>";
            echo "<td>" . htmlspecialchars($menu['url']) . "</td>";
            echo "<td>" . htmlspecialchars($menu['menu_location']) . "</td>";
            echo "<td>" . ($menu['parent_id'] ?: 'NULL') . "</td>";
            echo "<td>" . $menu['menu_order'] . "</td>";
            echo "<td>" . ($menu['is_active'] ? 'Yes' : 'No') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No menus found in database.</p>";
    }
    
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['fix_duplicates'])) {
            echo "<h3>Fixing duplicates...</h3>";
            
            // Delete duplicates keeping the highest ID
            $stmt = $pdo->prepare("
                DELETE m1 FROM menus m1
                INNER JOIN (
                    SELECT MAX(id) as max_id, title, url, menu_location, parent_id
                    FROM menus 
                    WHERE is_active = TRUE
                    GROUP BY title, url, menu_location, parent_id
                    HAVING COUNT(*) > 1
                ) m2 ON m1.title = m2.title 
                    AND m1.url = m2.url 
                    AND m1.menu_location = m2.menu_location 
                    AND m1.parent_id <=> m2.parent_id
                    AND m1.id != m2.max_id
                WHERE m1.is_active = TRUE
            ");
            $stmt->execute();
            echo "<p style='color: green;'>✅ Duplicates removed!</p>";
            
        } elseif (isset($_POST['reset_menus'])) {
            echo "<h3>Resetting to default menus...</h3>";
            
            // Delete all header menus
            $stmt = $pdo->prepare("DELETE FROM menus WHERE menu_location = 'header'");
            $stmt->execute();
            
            // Insert default menus
            $default_menus = [
                ['Home', 'index.php', 'header', 1, TRUE],
                ['Flights', 'flights.php', 'header', 2, TRUE],
                ['About', 'about.php', 'header', 3, TRUE],
                ['Contact', 'contact.php', 'header', 4, TRUE]
            ];
            
            $stmt = $pdo->prepare("INSERT INTO menus (title, url, menu_location, menu_order, is_active) VALUES (?, ?, ?, ?, ?)");
            
            foreach ($default_menus as $menu) {
                $stmt->execute($menu);
            }
            
            echo "<p style='color: green;'>✅ Default menus restored!</p>";
        }
        
        // Refresh the page
        echo "<script>setTimeout(() => window.location.reload(), 2000);</script>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>