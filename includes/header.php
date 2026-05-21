<?php
// includes/header.php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Load configuration
require_once __DIR__ . '/../config.php';

// Get site settings for logo and favicon - FIXED: Proper path handling and query
$site_logo = '';
$site_favicon = '';
$site_name = 'Flight Booking'; // Default fallback
$site_description = '';
$site_keywords = '';
$site_phone = '+234 903 407 2383';
$site_email = 'info@travelcentre.ng';

try {
    // Get all site settings in one query - FIXED: Ensure table exists and handle errors
    $stmt = $pdo->query("SELECT * FROM site_settings ORDER BY id DESC LIMIT 1");
    $site_settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($site_settings) {
        $site_logo = !empty($site_settings['logo']) ? $site_settings['logo'] : '';
        $site_favicon = !empty($site_settings['favicon']) ? $site_settings['favicon'] : '';
        $site_name = !empty($site_settings['site_name']) ? $site_settings['site_name'] : 'Flight Booking';
        $site_description = !empty($site_settings['site_description']) ? $site_settings['site_description'] : '';
        $site_keywords = !empty($site_settings['site_keywords']) ? $site_settings['site_keywords'] : '';
        $site_phone = !empty($site_settings['site_phone']) ? $site_settings['site_phone'] : '+234 903 407 2383';
        $site_email = !empty($site_settings['site_email']) ? $site_settings['site_email'] : 'info@travelcentre.ng';
        
        // Debug: Log successful settings fetch
        error_log("Header: Successfully loaded settings for: " . $site_name);
    } else {
        // If no settings found, use defaults
        error_log("Header: No site settings found, using defaults");
    }
} catch (PDOException $e) {
    // Log error but don't break the site - FIXED: Better error handling
    error_log("Header settings error: " . $e->getMessage());
    // Use defaults if query fails
    $site_name = 'Flight Booking';
}

// FIXED: Function to get correct file path
function getFilePath($file_path) {
    if (empty($file_path)) {
        return '';
    }
    
    // If path already includes uploads directory, return as is
    if (strpos($file_path, 'uploads/') === 0) {
        return $file_path;
    }
    
    // If it's just a filename, assume it's in uploads directory
    if (strpos($file_path, '/') === false) {
        return 'uploads/' . $file_path;
    }
    
    return $file_path;
}

// Apply path correction
$site_logo = getFilePath($site_logo);
$site_favicon = getFilePath($site_favicon);

// Debug: Log final paths
error_log("Header - Logo path: " . $site_logo);
error_log("Header - Favicon path: " . $site_favicon);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?><?php echo htmlspecialchars($site_name); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($site_description); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($site_keywords); ?>">
    
    <!-- Favicon -->
    <?php if (!empty($site_favicon)): ?>
        <link rel="icon" href="<?php echo htmlspecialchars($site_favicon); ?>" type="image/x-icon">
    <?php else: ?>
        <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
    <?php endif; ?>
    
    <!-- CSS Styles -->
    <style>
        /* Reset and Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #ffffff;
            background: linear-gradient(135deg, #0c0c0c 0%, #1a1a2e 50%, #16213e 100%);
            padding-top: 120px;
            min-height: 100vh;
        }

        /* Ripple Effect */
        .ripple {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.6);
            transform: scale(0);
            animation: ripple-animation 0.6s linear;
            pointer-events: none;
        }

        @keyframes ripple-animation {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }
        
        /* Top Contact Bar - Modern Dark Design */
        .top-contact-bar {
            background: linear-gradient(135deg, rgba(15, 15, 15, 0.95) 0%, rgba(26, 26, 46, 0.95) 100%);
            color: #ffffff;
            padding: 10px 0;
            font-size: 0.85rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1001;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }
        
        .top-contact-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        .contact-info {
            display: flex;
            gap: 2rem;
        }
        
        .contact-item {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            transition: all 0.3s ease;
            padding: 6px 12px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .contact-item:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }
        
        .contact-item a {
            color: #ffffff;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            font-weight: 500;
        }
        
        .contact-icon {
            width: 18px;
            height: 18px;
            color: #3498db;
        }
        
        .social-links {
            display: flex;
            gap: 1rem;
        }
        
        .social-link {
            color: #ffffff;
            text-decoration: none;
            padding: 8px;
            border-radius: 8px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            width: 36px;
            height: 36px;
        }
        
        .social-link:hover {
            background: rgba(52, 152, 219, 0.2);
            transform: translateY(-2px) scale(1.1);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }
        
        .social-link svg {
            width: 18px;
            height: 18px;
        }
        
        /* Header Styles - Modern Dark Elementor Style */
        .header {
            background: linear-gradient(135deg, rgba(15, 15, 15, 0.95) 0%, rgba(26, 26, 46, 0.95) 100%);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            color: #ffffff;
            padding: 1rem 0;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            position: fixed;
            top: 50px;
            left: 0;
            right: 0;
            z-index: 1000;
            transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
        }
        
        .header.scrolled {
            padding: 0.7rem 0;
            background: rgba(10, 10, 10, 0.98);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4);
            transform: translateY(-5px);
        }
        
        .container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        /* Header layout with flexbox for proper alignment */
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: nowrap;
            min-height: 60px;
        }
        
        .logo-section {
            flex: 0 0 auto;
            display: flex;
            align-items: center;
        }
        
        .logo {
            font-size: 1.8rem;
            font-weight: bold;
            text-decoration: none;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            transition: all 0.3s ease;
            padding: 8px 12px;
            border-radius: 12px;
            white-space: nowrap;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .logo:hover {
            transform: translateY(-2px);
            background: rgba(52, 152, 219, 0.2);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }
        
        .header.scrolled .logo {
            transform: scale(0.95);
        }
        
        .logo-img {
            height: 45px;
            width: auto;
            max-width: 200px;
            object-fit: contain;
            transition: all 0.4s ease;
            border-radius: 8px;
            filter: brightness(0) invert(1);
        }
        
        .header.scrolled .logo-img {
            height: 38px;
        }
        
        .logo-text {
            font-size: 1.8rem;
            font-weight: 800;
            background: linear-gradient(135deg, #3498db 0%, #2ecc71 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            transition: all 0.3s ease;
            white-space: nowrap;
        }
        
        .header.scrolled .logo-text {
            font-size: 1.6rem;
        }
        
        /* Navigation section with proper flex behavior */
        .nav-section {
            flex: 1 1 auto;
            display: flex;
            justify-content: center;
            align-items: center;
            min-width: 0;
        }
        
        .nav-menu {
            display: flex;
            list-style: none;
            gap: 0.5rem;
            margin: 0;
            padding: 0;
            flex-wrap: nowrap;
            justify-content: center;
            align-items: center;
        }
        
        .nav-menu a {
            color: #ffffff;
            text-decoration: none;
            padding: 0.8rem 1.2rem;
            border-radius: 10px;
            transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            font-weight: 600;
            position: relative;
            overflow: hidden;
            white-space: nowrap;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .nav-menu a::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 3px;
            background: linear-gradient(135deg, #3498db 0%, #2ecc71 100%);
            transition: all 0.3s ease;
            transform: translateX(-50%);
            border-radius: 4px 4px 0 0;
        }
        
        .nav-menu a:hover {
            background: rgba(52, 152, 219, 0.2);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(52, 152, 219, 0.3);
            border-color: rgba(52, 152, 219, 0.3);
        }
        
        .nav-menu a:hover::before {
            width: 80%;
        }
        
        .nav-menu a.active {
            background: linear-gradient(135deg, #3498db 0%, #2ecc71 100%);
            color: white;
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.4);
            border-color: transparent;
        }
        
        .nav-menu a.active::before {
            display: none;
        }
        
        /* User menu section with proper flex behavior */
        .user-section {
            flex: 0 0 auto;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 0.8rem;
            white-space: nowrap;
        }
        
        /* Track Button Styles - Replaced Icon with Text */
        .btn-track {
            padding: 0.7rem 1.3rem;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            font-weight: 600;
            position: relative;
            overflow: hidden;
            white-space: nowrap;
            background: rgba(52, 152, 219, 0.2);
            border: 1px solid rgba(52, 152, 219, 0.3);
            color: #3498db;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .btn-track::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }
        
        .btn-track:hover::before {
            left: 100%;
        }
        
        .btn-track:hover {
            background: #3498db;
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(52, 152, 219, 0.4);
            border-color: #3498db;
        }
        
        .header.scrolled .btn-track {
            padding: 0.6rem 1.1rem;
            font-size: 0.85rem;
        }
        
        /* Dropdown Menu Styles */
        .nav-menu .menu-item-has-children {
            position: relative;
        }
        
        .nav-menu .menu-item-has-children > a::after {
            content: '▼';
            margin-left: 5px;
            font-size: 0.6em;
            transition: transform 0.3s ease;
            display: inline-block;
            vertical-align: middle;
            line-height: 1;
        }
        
        .nav-menu .menu-item-has-children:hover > a::after {
            transform: rotate(180deg);
        }
        
        .sub-menu {
            position: absolute;
            top: 100%;
            left: 0;
            background: linear-gradient(135deg, rgba(20, 20, 20, 0.95) 0%, rgba(30, 30, 50, 0.95) 100%);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            min-width: 220px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.4);
            border-radius: 12px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-15px) scale(0.95);
            transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            z-index: 1001;
            border: 1px solid rgba(255, 255, 255, 0.15);
            padding: 8px 0;
            overflow: hidden;
        }
        
        .nav-menu .menu-item-has-children:hover .sub-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(8px) scale(1);
        }
        
        .sub-menu li {
            list-style: none;
        }
        
        .sub-menu a {
            color: #ffffff;
            padding: 0.9rem 1.2rem;
            display: block;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
            border-radius: 0;
            font-weight: 500;
            background: transparent;
            border: none;
        }
        
        .sub-menu a::before {
            display: none;
        }
        
        .sub-menu a:hover {
            background: rgba(52, 152, 219, 0.2);
            color: #3498db;
            transform: translateX(8px);
            box-shadow: none;
        }
        
        .sub-menu li:last-child a {
            border-bottom: none;
        }
        
        .user-menu {
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }
        
        .btn {
            padding: 0.7rem 1.3rem;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            font-weight: 600;
            position: relative;
            overflow: hidden;
            white-space: nowrap;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #ffffff;
        }
        
        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }
        
        .btn:hover::before {
            left: 100%;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.4);
            border: none;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #2980b9 0%, #3498db 100%);
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(52, 152, 219, 0.5);
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid #3498db;
            color: #3498db;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.2);
        }
        
        .btn-outline:hover {
            background: #3498db;
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(52, 152, 219, 0.4);
        }
        
        .btn-icon {
            padding: 0.7rem;
            border-radius: 50%;
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            background: rgba(52, 152, 219, 0.2);
            color: #3498db;
            transition: all 0.3s ease;
            white-space: nowrap;
            border: 1px solid rgba(52, 152, 219, 0.3);
        }
        
        .header.scrolled .btn-icon {
            width: 40px;
            height: 40px;
        }
        
        .btn-icon:hover {
            background: #3498db;
            color: white;
            transform: translateY(-3px) scale(1.1);
            box-shadow: 0 8px 25px rgba(52, 152, 219, 0.4);
        }
        
        .icon-text {
            display: inline;
        }
        
        /* Mobile Menu */
        .mobile-menu-btn {
            display: none;
            background: rgba(52, 152, 219, 0.2);
            border: 1px solid rgba(52, 152, 219, 0.3);
            color: #3498db;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.7rem;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .mobile-menu-btn:hover {
            background: #3498db;
            color: white;
            transform: scale(1.1);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.4);
        }
        
        /* Notifications */
        .notification-container {
            position: relative;
        }
        
        .notification-badge {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            border-radius: 50%;
            padding: 0.2rem 0.5rem;
            font-size: 0.7rem;
            position: absolute;
            top: -5px;
            right: -5px;
            min-width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            box-shadow: 0 3px 10px rgba(231, 76, 60, 0.4);
            border: 2px solid rgba(15, 15, 15, 0.95);
        }
        
        .header.scrolled .notification-badge {
            font-size: 0.6rem;
            min-width: 18px;
            height: 18px;
        }
        
        /* User Dropdown */
        .user-dropdown {
            position: relative;
        }
        
        .user-dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: linear-gradient(135deg, rgba(20, 20, 20, 0.95) 0%, rgba(30, 30, 50, 0.95) 100%);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            min-width: 200px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.4);
            border-radius: 12px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-15px) scale(0.95);
            transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            z-index: 1001;
            margin-top: 0.8rem;
            border: 1px solid rgba(255, 255, 255, 0.15);
            padding: 8px 0;
            overflow: hidden;
        }
        
        .user-dropdown:hover .user-dropdown-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(8px) scale(1);
        }
        
        .user-dropdown-menu a {
            color: #ffffff;
            padding: 0.9rem 1.2rem;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .user-dropdown-menu a:hover {
            background: rgba(52, 152, 219, 0.2);
            color: #3498db;
            transform: translateX(5px);
        }
        
        .user-dropdown-menu a:last-child {
            border-bottom: none;
            color: #e74c3c;
        }
        
        .user-dropdown-menu a:last-child:hover {
            background: rgba(231, 76, 60, 0.2);
            color: #c0392b;
        }
        
        /* Mobile-only track menu item */
        .track-mobile-only {
            display: none;
        }
        
        /* Desktop-only track button */
        .track-desktop-only {
            display: inline-flex;
        }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .nav-menu a {
                padding: 0.7rem 1rem;
                font-size: 0.9rem;
            }
            
            .contact-info {
                gap: 1.5rem;
            }
            
            .logo-text {
                font-size: 1.6rem;
            }
            
            .btn-track {
                padding: 0.6rem 1.1rem;
                font-size: 0.85rem;
            }
        }
        
        @media (max-width: 1024px) {
            .nav-menu {
                gap: 0.3rem;
            }
            
            .nav-menu a {
                padding: 0.6rem 0.8rem;
                font-size: 0.85rem;
            }
            
            .btn {
                padding: 0.6rem 1rem;
                font-size: 0.85rem;
            }
            
            .logo-text {
                font-size: 1.5rem;
            }
            
            .btn-track {
                padding: 0.6rem 1rem;
                font-size: 0.8rem;
            }
        }
        
        @media (max-width: 900px) {
            .nav-menu a {
                padding: 0.5rem 0.7rem;
                font-size: 0.8rem;
            }
            
            .logo-text {
                font-size: 1.4rem;
            }
            
            .btn-track {
                padding: 0.5rem 0.9rem;
                font-size: 0.8rem;
            }
        }
        
        @media (max-width: 768px) {
            body {
                padding-top: 70px;
            }
            
            .top-contact-bar {
                display: none;
            }
            
            .header {
                top: 0;
                padding: 0.5rem 0;
            }
            
            .header.scrolled {
                padding: 0.3rem 0;
            }
            
            .mobile-menu-btn {
                display: block;
            }
            
            /* Mobile layout with proper grid */
            .header-content {
                display: grid;
                grid-template-columns: auto 1fr auto;
                gap: 1rem;
                align-items: center;
            }
            
            .logo-section {
                grid-column: 1;
                justify-self: start;
            }
            
            .nav-section {
                grid-column: 1 / -1;
                grid-row: 2;
                justify-self: stretch;
                display: none;
            }
            
            .user-section {
                grid-column: 2 / 4;
                justify-self: end;
            }
            
            .nav-section.active {
                display: block;
            }
            
            .nav-menu {
                display: none;
                width: 100%;
                flex-direction: column;
                gap: 0;
                background: linear-gradient(135deg, rgba(20, 20, 20, 0.98) 0%, rgba(30, 30, 50, 0.98) 100%);
                backdrop-filter: blur(15px);
                -webkit-backdrop-filter: blur(15px);
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                padding: 1rem 0;
                box-shadow: 0 20px 50px rgba(0, 0, 0, 0.4);
                border-radius: 0 0 15px 15px;
                border-top: 1px solid rgba(255, 255, 255, 0.15);
                z-index: 999;
            }
            
            .nav-menu.active {
                display: flex;
            }
            
            .nav-menu li {
                width: 100%;
            }
            
            .nav-menu a {
                padding: 1rem 1.5rem;
                display: block;
                border-radius: 0;
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
                background: transparent;
                border: none;
                font-size: 0.9rem;
            }
            
            .nav-menu a:hover {
                background: rgba(52, 152, 219, 0.2);
                transform: none;
                box-shadow: none;
            }
            
            .nav-menu a::before {
                display: none;
            }
            
            /* Mobile dropdown handling */
            .nav-menu .menu-item-has-children .sub-menu {
                position: static;
                background: rgba(10, 10, 10, 0.8);
                box-shadow: none;
                opacity: 1;
                visibility: visible;
                transform: none;
                display: none;
                margin-left: 1rem;
                border-radius: 0;
                border: none;
                backdrop-filter: none;
            }
            
            .nav-menu .menu-item-has-children.active .sub-menu {
                display: block;
            }
            
            .sub-menu a {
                color: #ffffff;
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
                padding: 0.8rem 1rem 0.8rem 2rem;
                font-size: 0.85rem;
            }
            
            .sub-menu a:hover {
                background: rgba(52, 152, 219, 0.2);
                color: #3498db;
                transform: none;
            }
            
            /* User Menu Mobile Optimization - Turn icons to text */
            .user-menu {
                gap: 0.5rem;
            }
            
            /* Hide icons on mobile for buttons */
            .btn-icon svg,
            .btn-outline svg,
            .btn-primary svg,
            .btn-track svg {
                display: none;
            }
            
            /* Show text only on mobile */
            .btn-icon .icon-text,
            .btn-outline .icon-text,
            .btn-primary .icon-text {
                display: inline;
                font-size: 0.8rem;
            }
            
            /* Adjust button sizes for mobile */
            .btn-icon,
            .btn-outline,
            .btn-primary {
                padding: 0.5rem 0.8rem;
                width: auto;
                height: auto;
                border-radius: 8px;
                min-width: 70px;
                justify-content: center;
                font-size: 0.8rem;
            }
            
            .btn-icon {
                background: rgba(52, 152, 219, 0.2);
                color: #3498db;
                border: 1px solid rgba(52, 152, 219, 0.3);
            }
            
            /* Track Button Mobile Optimization - Hide desktop version */
            .track-desktop-only {
                display: none;
            }
            
            /* Show mobile track in navigation */
            .track-mobile-only {
                display: block;
            }
            
            /* Notification button mobile optimization */
            .notification-container .btn-icon {
                min-width: 40px;
                padding: 0.5rem;
            }
            
            .logo-img {
                height: 35px;
                max-width: 150px;
            }
            
            .header.scrolled .logo-img {
                height: 30px;
            }
            
            .logo-text {
                font-size: 1.3rem;
            }
            
            .header.scrolled .logo-text {
                font-size: 1.2rem;
            }
            
            /* Reduce contact item padding on mobile */
            .contact-item {
                padding: 4px 8px;
            }
            
            /* Navigation dropdown icon - keep as text on mobile */
            .nav-menu .menu-item-has-children > a::after {
                content: '▼';
                float: right;
                font-size: 0.6em;
                margin-top: 3px;
            }
        }
        
        @media (max-width: 480px) {
            body {
                padding-top: 65px;
            }
            
            .logo-img {
                height: 30px;
                max-width: 120px;
            }
            
            .header.scrolled .logo-img {
                height: 25px;
            }
            
            .logo-text {
                font-size: 1.2rem;
            }
            
            .header.scrolled .logo-text {
                font-size: 1.1rem;
            }
            
            .logo {
                gap: 0.3rem;
                padding: 6px 8px;
            }
            
            .user-menu {
                gap: 0.3rem;
            }
            
            /* Further reduce button sizes on very small screens */
            .btn-icon,
            .btn-outline,
            .btn-primary {
                padding: 0.4rem 0.6rem;
                min-width: 60px;
                font-size: 0.75rem;
            }
            
            /* Track Button Extra Small Screens */
            .btn-track {
                padding: 0.4rem 0.6rem;
                font-size: 0.7rem;
                min-width: 50px;
            }
            
            .header {
                padding: 0.4rem 0;
            }
            
            .header.scrolled {
                padding: 0.2rem 0;
            }
            
            /* Reduce navigation font size on very small screens */
            .nav-menu a {
                padding: 0.8rem 1rem;
                font-size: 0.85rem;
            }
            
            .sub-menu a {
                padding: 0.7rem 0.8rem 0.7rem 1.8rem;
                font-size: 0.8rem;
            }
            
            /* Reduce mobile menu button size */
            .mobile-menu-btn {
                padding: 0.5rem;
                font-size: 1.2rem;
            }
            
            /* Further reduce notification button */
            .notification-container .btn-icon {
                min-width: 35px;
                padding: 0.4rem;
                font-size: 0.7rem;
            }
        }

        /* Fix for header covering hero content */
        .wp-hero-content {
            position: relative;
            z-index: 3;
            width: 100%;
            padding-top: 100px;
            margin-bottom: 40px;
        }
        
        .wp-hero-text {
            text-align: center;
            max-width: 900px;
            margin: 0 auto;
            padding: 40px 0;
        }
        
        /* Adjust hero section min-height to account for header */
        .wp-hero-section {
            position: relative;
            min-height: 80vh;
            display: flex;
            align-items: center;
            overflow: hidden;
            color: #ffffff;
            margin-bottom: 30px;
        }
        
        /* Ensure the hero content is properly aligned */
        .wp-hero-title {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 20px;
            line-height: 1.2;
            text-shadow: 2px 2px 8px rgba(0, 0, 0, 0.5);
            padding-top: 20px;
        }
        
        /* Mobile adjustments */
        @media (max-width: 768px) {
            .wp-hero-content {
                padding-top: 80px;
            }
            
            .wp-hero-text {
                padding: 20px 0;
            }
            
            .wp-hero-section {
                min-height: 70vh;
            }
        }
        
        @media (max-width: 480px) {
            .wp-hero-content {
                padding-top: 70px;
            }
            
            .wp-hero-section {
                min-height: 65vh;
            }
        }
    </style>
</head>
<body>
    <!-- Top Contact Bar - Modern Dark Design -->
    <div class="top-contact-bar">
        <div class="top-contact-content">
            <div class="contact-info">
                <div class="contact-item">
                    <a href="tel:<?php echo htmlspecialchars($site_phone); ?>">
                        <svg class="contact-icon" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/>
                        </svg>
                        <span class="contact-text"><?php echo htmlspecialchars($site_phone); ?></span>
                    </a>
                </div>
                <div class="contact-item">
                    <a href="mailto:<?php echo htmlspecialchars($site_email); ?>">
                        <svg class="contact-icon" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                        </svg>
                        <span class="contact-text"><?php echo htmlspecialchars($site_email); ?></span>
                    </a>
                </div>
            </div>
            <div class="social-links">
                <a href="#" class="social-link" aria-label="Facebook">
                    <svg viewBox="0 0 24 24" fill="currentColor">
                        <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                    </svg>
                </a>
                <a href="#" class="social-link" aria-label="Twitter">
                    <svg viewBox="0 0 24 24" fill="currentColor">
                        <path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/>
                    </svg>
                </a>
                <a href="#" class="social-link" aria-label="Instagram">
                    <svg viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                    </svg>
                </a>
            </div>
        </div>
    </div>

    <!-- Main Header -->
    <header class="header" id="mainHeader">
        <div class="container">
            <div class="header-content">
                <!-- Logo Section -->
                <div class="logo-section">
                    <a href="index.php" class="logo">
                        <?php if (!empty($site_logo) && file_exists($site_logo)): ?>
                            <!-- Display Logo Image with proper path handling -->
                            <img src="<?php echo htmlspecialchars($site_logo); ?>" alt="<?php echo htmlspecialchars($site_name); ?>" class="logo-img" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline';">
                            <span class="logo-text" style="display: none;"><?php echo htmlspecialchars($site_name); ?></span>
                        <?php else: ?>
                            <!-- Display Site Name as Fallback -->
                            <span class="logo-text"><?php echo htmlspecialchars($site_name); ?></span>
                        <?php endif; ?>
                    </a>
                </div>
                
                <!-- Navigation Section -->
                <div class="nav-section" id="navSection">
                    <ul class="nav-menu" id="navMenu">
                        <?php
                        try {
                            // Get header menus with hierarchy support - FIXED QUERY to prevent duplicates
                            $stmt = $pdo->prepare("
                                SELECT DISTINCT m.*, 
                                       (SELECT COUNT(*) FROM menus AS child WHERE child.parent_id = m.id AND child.is_active = TRUE) as child_count
                                FROM menus m 
                                WHERE m.menu_location = 'header' 
                                AND m.is_active = TRUE 
                                AND (m.parent_id IS NULL OR m.parent_id = 0)
                                ORDER BY m.menu_order ASC
                            ");
                            $stmt->execute();
                            $parent_menus = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            // Debug: Check if we have menus
                            if (empty($parent_menus)) {
                                // If no menus in database, show default menu with Track added
                                echo '
                                <li><a href="index.php" class="' . (basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '') . '">Home</a></li>
                                <li><a href="flights.php" class="' . (basename($_SERVER['PHP_SELF']) == 'flights.php' ? 'active' : '') . '">Flights</a></li>
                                <li class="menu-item-has-children">
                                    <a href="services.php">Services</a>
                                    <ul class="sub-menu">
                                        <li><a href="track-ticket.php">Track Ticket</a></li>
                                        <li><a href="flight-status.php">Flight Status</a></li>
                                    </ul>
                                </li>
                                <li><a href="about.php" class="' . (basename($_SERVER['PHP_SELF']) == 'about.php' ? 'active' : '') . '">About</a></li>
                                <li><a href="contact.php" class="' . (basename($_SERVER['PHP_SELF']) == 'contact.php' ? 'active' : '') . '">Contact</a></li>
                                ';
                            } else {
                                foreach ($parent_menus as $menu) {
                                    $has_children = $menu['child_count'] > 0;
                                    $menu_class = $has_children ? 'menu-item-has-children' : '';
                                    $current_page = basename($_SERVER['PHP_SELF']);
                                    $request_uri = $_SERVER['REQUEST_URI'];
                                    $is_active = ($menu['url'] == $current_page || strpos($request_uri, $menu['url']) !== false) ? 'active' : '';
                                    
                                    echo '<li class="' . $menu_class . ' ' . $is_active . '">';
                                    echo '<a href="' . htmlspecialchars($menu['url']) . '">' . htmlspecialchars($menu['title']) . '</a>';
                                    
                                    if ($has_children) {
                                        // Get child menus
                                        $child_stmt = $pdo->prepare("
                                            SELECT * FROM menus 
                                            WHERE parent_id = ? 
                                            AND is_active = TRUE 
                                            ORDER BY menu_order ASC
                                        ");
                                        $child_stmt->execute([$menu['id']]);
                                        $child_menus = $child_stmt->fetchAll(PDO::FETCH_ASSOC);
                                        
                                        if (!empty($child_menus)) {
                                            echo '<ul class="sub-menu">';
                                            foreach ($child_menus as $child_menu) {
                                                $child_active = ($child_menu['url'] == $current_page || strpos($request_uri, $child_menu['url']) !== false) ? 'active' : '';
                                                echo '<li class="' . $child_active . '">';
                                                echo '<a href="' . htmlspecialchars($child_menu['url']) . '">' . htmlspecialchars($child_menu['title']) . '</a>';
                                                echo '</li>';
                                            }
                                            // Add Track to dropdown if not already there
                                            $has_track = false;
                                            foreach ($child_menus as $child_menu) {
                                                if (strpos(strtolower($child_menu['title']), 'track') !== false || strpos(strtolower($child_menu['url']), 'track') !== false) {
                                                    $has_track = true;
                                                    break;
                                                }
                                            }
                                            if (!$has_track && strpos(strtolower($menu['title']), 'service') !== false) {
                                                echo '<li><a href="track-ticket.php">Track Ticket</a></li>';
                                            }
                                            echo '</ul>';
                                        }
                                    }
                                    
                                    echo '</li>';
                                }
                            }
                        } catch (PDOException $e) {
                            // Fallback menu items if database query fails
                            error_log("Menu query error: " . $e->getMessage());
                            echo '
                            <li><a href="index.php" class="' . (basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '') . '">Home</a></li>
                            <li><a href="flights.php" class="' . (basename($_SERVER['PHP_SELF']) == 'flights.php' ? 'active' : '') . '">Flights</a></li>
                            <li class="menu-item-has-children">
                                <a href="services.php">Services</a>
                                <ul class="sub-menu">
                                    <li><a href="track-ticket.php">Track Ticket</a></li>
                                    <li><a href="flight-status.php">Flight Status</a></li>
                                </ul>
                            </li>
                            <li><a href="about.php" class="' . (basename($_SERVER['PHP_SELF']) == 'about.php' ? 'active' : '') . '">About</a></li>
                            <li><a href="contact.php" class="' . (basename($_SERVER['PHP_SELF']) == 'contact.php' ? 'active' : '') . '">Contact</a></li>
                            ';
                        }
                        ?>
                        <!-- Mobile-only track menu item -->
                        <li class="track-mobile-only">
                            <a href="track-ticket.php">Track Ticket</a>
                        </li>
                    </ul>
                </div>
                
                <!-- User Section -->
                <div class="user-section">
                    <div class="user-menu">
                        <!-- Track Ticket Button - Desktop only -->
                        <a href="track-ticket.php" class="btn btn-track track-desktop-only" title="Track Ticket">
                            Track
                        </a>

                        <?php if (isset($_SESSION['user_id'])): ?>
                            <div class="notification-container">
                                <a href="notifications.php" class="btn btn-icon" title="Notifications">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.93 6 11v5l-2 2v1h16v-1l-2-2z"/>
                                    </svg>
                                    <span class="icon-text">Alerts</span>
                                    
                                    <?php
                                    try {
                                        $unread = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = FALSE");
                                        $unread->execute([$_SESSION['user_id']]);
                                        $unread_count = $unread->fetchColumn();
                                        if ($unread_count > 0) {
                                            echo '<span class="notification-badge">' . $unread_count . '</span>';
                                        }
                                    } catch (PDOException $e) {
                                        error_log("Notification count error: " . $e->getMessage());
                                    }
                                    ?>
                                </a>
                            </div>

                            <div class="user-dropdown">
                                <a href="dashboard.php" class="btn btn-icon" title="Dashboard">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/>
                                    </svg>
                                    <span class="icon-text">Account</span>
                                </a>
                                <div class="user-dropdown-menu">
                                    <a href="dashboard.php">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/>
                                        </svg>
                                        My Dashboard
                                    </a>
                                    <a href="profile.php">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                        </svg>
                                        My Profile
                                    </a>
                                    <a href="my-bookings.php">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M20 6h-4V4c0-1.11-.89-2-2-2h-4c-1.11 0-2 .89-2 2v2H4c-1.11 0-1.99.89-1.99 2L2 19c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-6 0h-4V4h4v2z"/>
                                        </svg>
                                        My Bookings
                                    </a>
                                    <a href="logout.php">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/>
                                        </svg>
                                        Logout
                                    </a>
                                </div>
                            </div>
                        <?php else: ?>
                            <a href="login.php" class="btn btn-outline">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M10 20v-6h4v-2h-4V4l-6 6 6 6zm-2 0h2v-6l-4-4 4-4v-2H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2z"/>
                                </svg>
                                <span class="icon-text">Login</span>
                            </a>
                            <a href="register.php" class="btn btn-primary">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                                </svg>
                                <span class="icon-text">Register</span>
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <button class="mobile-menu-btn" onclick="toggleMenu()" aria-label="Toggle navigation menu">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <script>
        // Toggle mobile menu
        function toggleMenu() {
            const navMenu = document.getElementById('navMenu');
            const navSection = document.getElementById('navSection');
            navMenu.classList.toggle('active');
            navSection.classList.toggle('active');
        }
        
        // Close mobile menu when clicking outside
        document.addEventListener('click', function(event) {
            const navMenu = document.getElementById('navMenu');
            const mobileBtn = document.querySelector('.mobile-menu-btn');
            if (!navMenu.contains(event.target) && !mobileBtn.contains(event.target)) {
                navMenu.classList.remove('active');
                document.getElementById('navSection').classList.remove('active');
            }
        });
        
        // Handle mobile dropdown menus
        document.addEventListener('DOMContentLoaded', function() {
            const menuItemsWithChildren = document.querySelectorAll('.menu-item-has-children > a');
            
            menuItemsWithChildren.forEach(item => {
                item.addEventListener('click', function(e) {
                    if (window.innerWidth <= 768) {
                        e.preventDefault();
                        const parent = this.parentElement;
                        parent.classList.toggle('active');
                    }
                });
            });
        });
        
        // Sticky header on scroll with enhanced effects
        window.addEventListener('scroll', function() {
            const header = document.getElementById('mainHeader');
            const topBar = document.querySelector('.top-contact-bar');
            if (window.scrollY > 50) {
                header.classList.add('scrolled');
                if (topBar && window.innerWidth > 768) {
                    topBar.style.transform = 'translateY(-5px)';
                }
            } else {
                header.classList.remove('scrolled');
                if (topBar && window.innerWidth > 768) {
                    topBar.style.transform = 'translateY(0)';
                }
            }
        });
        
        // Handle logo loading errors - fallback to text
        document.addEventListener('DOMContentLoaded', function() {
            const logoImg = document.querySelector('.logo-img');
            if (logoImg) {
                logoImg.addEventListener('error', function() {
                    this.style.display = 'none';
                    const logoText = this.nextElementSibling;
                    if (logoText && logoText.classList.contains('logo-text')) {
                        logoText.style.display = 'inline';
                    }
                });
            }
            
            // Initialize scroll state
            const header = document.getElementById('mainHeader');
            if (window.scrollY > 50) {
                header.classList.add('scrolled');
            }
            
            // Add click animation to buttons
            const buttons = document.querySelectorAll('.btn, .btn-track');
            buttons.forEach(button => {
                button.addEventListener('click', function(e) {
                    // Create ripple effect
                    const ripple = document.createElement('span');
                    const rect = this.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    const x = e.clientX - rect.left - size / 2;
                    const y = e.clientY - rect.top - size / 2;
                    
                    ripple.style.width = ripple.style.height = size + 'px';
                    ripple.style.left = x + 'px';
                    ripple.style.top = y + 'px';
                    ripple.classList.add('ripple');
                    
                    this.appendChild(ripple);
                    
                    setTimeout(() => {
                        ripple.remove();
                    }, 600);
                });
            });
        });
    </script>