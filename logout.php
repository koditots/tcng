<?php
// logout.php
require_once 'config.php';

// Add logout notification if user was logged in
if (isset($_SESSION['user_id'])) {
    addNotification($pdo, $_SESSION['user_id'], 'Logout', 'You have been logged out of your account.', 'info');
}

// Destroy session
session_destroy();

// Redirect to home page
redirect('index.php');
?>