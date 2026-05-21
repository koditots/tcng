<?php
// admin/sidebar.php
// Sidebar component for admin panel

// Detect the current page automatically
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// Get logo and favicon from site settings
require_once '../config.php';

if (!function_exists('getSiteSetting')) {
    function getSiteSetting($pdo, $key) {
        try {
            $stmt = $pdo->query("SELECT * FROM site_settings ORDER BY id DESC LIMIT 1");
            $settings = $stmt->fetch(PDO::FETCH_ASSOC);
            return $settings[$key] ?? '';
        } catch (Exception $e) {
            error_log("getSiteSetting error: " . $e->getMessage());
            return '';
        }
    }
}

$site_logo = getSiteSetting($pdo, 'logo');
$site_favicon = getSiteSetting($pdo, 'favicon');
$site_name = getSiteSetting($pdo, 'site_name') ?: 'Travel Centre';
?>

<!-- Favicon -->
<?php if (!empty($site_favicon)): ?>
<link rel="icon" type="image/x-icon" href="../<?php echo htmlspecialchars($site_favicon); ?>">
<link rel="shortcut icon" type="image/x-icon" href="../<?php echo htmlspecialchars($site_favicon); ?>">
<?php endif; ?>

<!-- Sidebar Styles -->
<style>
    /* Sidebar Variables */
    :root {
        --sidebar-width: 250px;
        --sidebar-collapsed-width: 70px;
        --sidebar-mobile-height: 60px;
        --sidebar-z-index: 1000;
        --transition-speed: 0.3s;
        --main-content-padding: 15px;
    }

    /* Sidebar Base */
    .sidebar {
        width: var(--sidebar-width);
        background: #343a40;
        color: white;
        height: 100vh;
        position: fixed;
        overflow-y: auto;
        overflow-x: hidden;
        transition: all var(--transition-speed) ease;
        z-index: var(--sidebar-z-index);
        left: 0;
        top: 0;
    }

    /* Collapsed State */
    .sidebar.collapsed {
        width: var(--sidebar-collapsed-width);
    }

    .sidebar.collapsed .sidebar-header {
        padding: 1rem 0.5rem;
    }

    .sidebar.collapsed .logo-container {
        gap: 0.25rem;
    }

    .sidebar.collapsed .logo-img {
        max-height: 40px;
        max-width: 50px;
    }

    .sidebar.collapsed .logo-fallback {
        font-size: 1rem;
    }

    .sidebar.collapsed .admin-badge,
    .sidebar.collapsed .menu-text,
    .sidebar.collapsed .logo-text {
        display: none !important;
    }

    .sidebar.collapsed .sidebar-menu a {
        padding: 0.75rem;
        justify-content: center;
    }

    .sidebar.collapsed .sidebar-menu a .sidebar-icon {
        margin: 0;
    }

    /* Sidebar Header */
    .sidebar-header {
        padding: 1.25rem;
        background: #2c3136;
        text-align: center;
        border-bottom: 1px solid #495057;
        position: relative;
        transition: all var(--transition-speed) ease;
    }

    .logo-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.5rem;
        transition: all var(--transition-speed) ease;
    }

    .logo-img {
        max-height: 50px;
        max-width: 160px;
        object-fit: contain;
        transition: all var(--transition-speed) ease;
    }

    .logo-fallback {
        font-size: 1.3rem;
        font-weight: bold;
        color: white;
        transition: all var(--transition-speed) ease;
    }

    .logo-text {
        transition: all var(--transition-speed) ease;
    }

    .admin-badge {
        background: #007bff;
        color: white;
        padding: 0.2rem 0.6rem;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 500;
        transition: all var(--transition-speed) ease;
    }

    /* Toggle Button */
    .sidebar-toggle {
        position: absolute;
        top: 50%;
        right: -12px;
        transform: translateY(-50%);
        background: #007bff;
        border: none;
        color: white;
        width: 22px;
        height: 22px;
        border-radius: 50%;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        transition: all 0.3s ease;
        z-index: 1001;
    }

    .sidebar-toggle:hover {
        background: #0056b3;
        transform: translateY(-50%) scale(1.1);
    }

    .sidebar-toggle .toggle-icon {
        transition: transform var(--transition-speed) ease;
    }

    .sidebar.collapsed .sidebar-toggle .toggle-icon {
        transform: rotate(180deg);
    }

    /* Sidebar Menu */
    .sidebar-menu {
        list-style: none;
        padding: 0.75rem 0;
        margin: 0;
    }

    .sidebar-menu li {
        margin-bottom: 0.2rem;
    }

    .sidebar-menu a {
        color: #adb5bd;
        text-decoration: none;
        padding: 0.7rem 1.25rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        transition: all 0.3s;
        white-space: nowrap;
        overflow: hidden;
    }

    .sidebar-menu a:hover, .sidebar-menu a.active {
        background: #495057;
        color: white;
        border-left: 4px solid #007bff;
    }

    .sidebar-icon {
        width: 18px;
        height: 18px;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .sidebar-icon svg {
        width: 100%;
        height: 100%;
        fill: currentColor;
    }

    .menu-text {
        transition: opacity var(--transition-speed) ease;
        white-space: nowrap;
        overflow: hidden;
        font-size: 0.9rem;
    }

    /* Main Content Adjustment */
    .main-content {
        margin-left: var(--sidebar-width);
        transition: all var(--transition-speed) ease;
        padding: var(--main-content-padding);
        min-height: 100vh;
        box-sizing: border-box;
    }

    .sidebar.collapsed ~ .main-content {
        margin-left: var(--sidebar-collapsed-width);
    }

    /* Mobile Styles - FIXED */
    @media (max-width: 768px) {
        :root {
            --sidebar-width: 280px;
            --sidebar-collapsed-width: 0px;
            --main-content-padding: 12px;
        }

        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            transform: translateX(-100%);
            transition: transform var(--transition-speed) ease;
            z-index: var(--sidebar-z-index);
            overflow-y: auto;
        }

        .sidebar.mobile-open {
            transform: translateX(0);
        }

        .sidebar.collapsed {
            transform: translateX(-100%);
        }

        .sidebar.mobile-open.collapsed {
            transform: translateX(0);
        }

        .sidebar-header {
            padding: 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #2c3136;
        }

        .logo-container {
            flex-direction: row;
            gap: 0.8rem;
        }

        .logo-img {
            max-height: 35px;
            max-width: 120px;
        }

        .logo-fallback {
            font-size: 1.1rem;
        }

        .admin-badge {
            font-size: 0.65rem;
            padding: 0.2rem 0.5rem;
        }

        .sidebar-toggle {
            position: static;
            transform: none;
            background: #495057;
            color: #adb5bd;
            font-size: 1rem;
            width: 32px;
            height: 32px;
            border-radius: 4px;
        }

        .sidebar-toggle:hover {
            background: #5a6268;
            color: white;
            transform: none;
        }

        .sidebar-menu {
            display: block;
            padding: 0.5rem 0;
        }

        .sidebar-menu a {
            padding: 0.85rem 1.2rem;
            font-size: 0.9rem;
        }

        .sidebar-icon {
            width: 18px;
            height: 18px;
        }

        .menu-text {
            font-size: 0.9rem;
        }

        /* Mobile overlay */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }

        .sidebar-overlay.active {
            display: block;
        }

        /* Main content adjustment for mobile */
        .main-content {
            margin-left: 0;
            width: 100%;
            padding: var(--main-content-padding);
        }

        .sidebar.collapsed ~ .main-content,
        .sidebar ~ .main-content {
            margin-left: 0;
        }

        /* Mobile menu button */
        .mobile-menu-button {
            display: block;
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            cursor: pointer;
            z-index: 1002;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
        }

        .mobile-menu-button:hover {
            background: #0056b3;
            transform: scale(1.1);
        }

        .mobile-menu-icon {
            font-size: 1.2rem;
        }
    }

    /* Small Mobile Devices */
    @media (max-width: 480px) {
        :root {
            --sidebar-width: 100%;
            --main-content-padding: 10px;
        }

        .sidebar-header {
            padding: 0.8rem;
        }

        .logo-img {
            max-height: 30px;
            max-width: 100px;
        }

        .logo-fallback {
            font-size: 1rem;
        }

        .admin-badge {
            font-size: 0.6rem;
            padding: 0.15rem 0.4rem;
        }

        .sidebar-menu a {
            padding: 0.8rem 1rem;
            font-size: 0.85rem;
        }

        .sidebar-icon {
            width: 16px;
            height: 16px;
        }

        .menu-text {
            font-size: 0.85rem;
        }

        .mobile-menu-button {
            width: 45px;
            height: 45px;
            bottom: 15px;
            right: 15px;
        }
    }

    /* Large Screens */
    @media (min-width: 1200px) {
        :root {
            --sidebar-width: 260px;
            --sidebar-collapsed-width: 75px;
            --main-content-padding: 18px;
        }

        .sidebar-menu a {
            padding: 0.75rem 1.5rem;
        }
    }

    /* Extra Large Screens */
    @media (min-width: 1400px) {
        :root {
            --main-content-padding: 20px;
        }
    }

    /* Desktop only styles */
    @media (min-width: 769px) {
        .mobile-menu-button,
        .sidebar-overlay {
            display: none !important;
        }
    }

    /* Scrollbar Styling */
    .sidebar::-webkit-scrollbar {
        width: 4px;
    }

    .sidebar::-webkit-scrollbar-track {
        background: #2c3136;
    }

    .sidebar::-webkit-scrollbar-thumb {
        background: #495057;
        border-radius: 2px;
    }

    .sidebar::-webkit-scrollbar-thumb:hover {
        background: #5a6268;
    }

    /* Content Protection - Ensure content is never covered */
    .main-content {
        position: relative;
        z-index: 1;
    }
</style>

<!-- Mobile Menu Button -->
<button class="mobile-menu-button" id="mobileMenuButton" aria-label="Open menu">
    <span class="mobile-menu-icon">☰</span>
</button>

<!-- Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Sidebar HTML -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo-container">
            <?php if (!empty($site_logo)): ?>
                <img src="../<?php echo htmlspecialchars($site_logo); ?>" alt="<?php echo htmlspecialchars($site_name); ?>" class="logo-img">
            <?php else: ?>
                <div class="logo-fallback">
                    <span class="logo-text"><?php echo htmlspecialchars($site_name); ?></span>
                </div>
            <?php endif; ?>
            <div class="admin-badge">Admin Panel</div>
        </div>
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
            <span class="toggle-icon">
                <svg viewBox="0 0 24 24" width="10" height="10">
                    <path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z" fill="currentColor"/>
                </svg>
            </span>
        </button>
    </div>
    <ul class="sidebar-menu">
        <li>
            <a href="dashboard.php" class="<?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">
                <span class="sidebar-icon">
                    <svg viewBox="0 0 24 24">
                        <path d="M3 13h1v7c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2v-7h1c.6 0 1-.4 1-1s-.4-1-1-1h-1V4c0-1.1-.9-2-2-2H6C4.9 2 4 2.9 4 4v7H3c-.6 0-1 .4-1 1s.4 1 1 1zM6 4h12v7H6V4z"/>
                    </svg>
                </span>
                <span class="menu-text">Dashboard</span>
            </a>
        </li>
        <li>
            <a href="invoice.php" class="<?php echo $current_page === 'invoice' ? 'active' : ''; ?>">
                <span class="sidebar-icon">
                    <svg viewBox="0 0 24 24">
                        <path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/>
                    </svg>
                </span>
                <span class="menu-text">Invoice Management</span>
            </a>
        </li>
        <li>
            <a href="visa-applications.php" class="<?php echo $current_page === 'visa-applications' ? 'active' : ''; ?>">
                <span class="sidebar-icon">
                    <svg viewBox="0 0 24 24">
                        <path d="M14.06 9.02l.92.92L5.92 19H5v-.92l9.06-9.06M17.66 3c-.25 0-.51.1-.7.29l-1.83 1.83 3.75 3.75 1.83-1.83c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.2-.2-.45-.29-.71-.29zm-3.6 3.19L3 17.25V21h3.75L17.81 9.94l-3.75-3.75z"/>
                    </svg>
                </span>
                <span class="menu-text">Visa Applications</span>
            </a>
        </li>
        <li>
            <a href="bookings.php" class="<?php echo $current_page === 'bookings' ? 'active' : ''; ?>">
                <span class="sidebar-icon">
                    <svg viewBox="0 0 24 24">
                        <path d="M21 16v-2l-8-5V3.5c0-.83-.67-1.5-1.5-1.5S10 2.67 10 3.5V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5l8 2.5z"/>
                    </svg>
                </span>
                <span class="menu-text">Flight Bookings</span>
            </a>
        </li>
        <li>
            <a href="users.php" class="<?php echo $current_page === 'users' ? 'active' : ''; ?>">
                <span class="sidebar-icon">
                    <svg viewBox="0 0 24 24">
                        <path d="M16 4c0-1.11.89-2 2-2s2 .89 2 2-.89 2-2 2-2-.89-2-2zm4 18v-6h2.5l-2.54-7.63C19.68 7.55 18.92 7 18.06 7h-.12c-.86 0-1.63.55-1.9 1.37l-.86 2.58c1.08.6 1.82 1.73 1.82 3.05v8h3zm-7.5-10.5c.28 0 .5.22.5.5s-.22.5-.5.5-.5-.22-.5-.5.22-.5.5-.5zM9 12c1.65 0 3-1.35 3-3s-1.35-3-3-3-3 1.35-3 3 1.35 3 3 3zm0-4c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm6.5 11v-6H9v6h6.5zm-8 0v-6H3v6h4.5zm-1-8H4v-4h2.5v4z"/>
                    </svg>
                </span>
                <span class="menu-text">Users</span>
            </a>
        </li>
        <li>
            <a href="flights.php" class="<?php echo $current_page === 'flights' ? 'active' : ''; ?>">
                <span class="sidebar-icon">
                    <svg viewBox="0 0 24 24">
                        <path d="M21 16v-2l-8-5V3.5c0-.83-.67-1.5-1.5-1.5S10 2.67 10 3.5V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5l8 2.5z"/>
                    </svg>
                </span>
                <span class="menu-text">Flights</span>
            </a>
        </li>
        <li>
            <a href="menus.php" class="<?php echo $current_page === 'menus' ? 'active' : ''; ?>">
                <span class="sidebar-icon">
                    <svg viewBox="0 0 24 24">
                        <path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/>
                    </svg>
                </span>
                <span class="menu-text">Menu Management</span>
            </a>
        </li>
        <li>
            <a href="pages.php" class="<?php echo $current_page === 'pages' ? 'active' : ''; ?>">
                <span class="sidebar-icon">
                    <svg viewBox="0 0 24 24">
                        <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/>
                    </svg>
                </span>
                <span class="menu-text">Page Management</span>
            </a>
        </li>
        <li>
            <a href="settings.php" class="<?php echo $current_page === 'settings' ? 'active' : ''; ?>">
                <span class="sidebar-icon">
                    <svg viewBox="0 0 24 24">
                        <path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/>
                    </svg>
                </span>
                <span class="menu-text">Settings</span>
            </a>
        </li>
        <li>
            <a href="../logout.php">
                <span class="sidebar-icon">
                    <svg viewBox="0 0 24 24">
                        <path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/>
                    </svg>
                </span>
                <span class="menu-text">Logout</span>
            </a>
        </li>
    </ul>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const mobileMenuButton = document.getElementById('mobileMenuButton');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const isMobile = window.innerWidth <= 768;
    
    // Check for saved state in localStorage (desktop only)
    const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    
    // Initialize sidebar state
    if (isCollapsed && !isMobile) {
        sidebar.classList.add('collapsed');
    }
    
    // Desktop toggle functionality
    sidebarToggle.addEventListener('click', function() {
        if (!isMobile) {
            sidebar.classList.toggle('collapsed');
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        } else {
            // On mobile, close the sidebar when toggle is clicked
            closeMobileSidebar();
        }
    });
    
    // Mobile menu button functionality
    function openMobileSidebar() {
        sidebar.classList.add('mobile-open');
        sidebarOverlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
    function closeMobileSidebar() {
        sidebar.classList.remove('mobile-open');
        sidebarOverlay.classList.remove('active');
        document.body.style.overflow = '';
    }
    
    mobileMenuButton.addEventListener('click', openMobileSidebar);
    sidebarOverlay.addEventListener('click', closeMobileSidebar);
    
    // Close sidebar when clicking on a menu item (mobile only)
    if (isMobile) {
        const menuLinks = document.querySelectorAll('.sidebar-menu a');
        menuLinks.forEach(link => {
            link.addEventListener('click', closeMobileSidebar);
        });
    }
    
    // Handle window resize
    let resizeTimeout;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function() {
            const nowMobile = window.innerWidth <= 768;
            
            if (nowMobile && !isMobile) {
                // Switching to mobile - close sidebar and reset state
                closeMobileSidebar();
                sidebar.classList.remove('collapsed');
            } else if (!nowMobile && isMobile) {
                // Switching to desktop - restore saved state and reset mobile classes
                closeMobileSidebar();
                const savedState = localStorage.getItem('sidebarCollapsed') === 'true';
                if (savedState) {
                    sidebar.classList.add('collapsed');
                } else {
                    sidebar.classList.remove('collapsed');
                }
            }
        }, 250);
    });
    
    // Close sidebar with Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeMobileSidebar();
        }
    });
});
</script>