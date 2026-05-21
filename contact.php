<?php
// contact.php
require_once 'includes/header.php';

// Database connection
try {
    $pdo = new PDO("mysql:host=localhost;dbname=travel_centre", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get site name from database or use default
    $stmt = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_name = 'site_name'");
    $site_setting = $stmt->fetch(PDO::FETCH_ASSOC);
    $site_name = $site_setting ? $site_setting['setting_value'] : "Travel Centre";
    
} catch (PDOException $e) {
    // If database connection fails, use default values
    $site_name = "Travel Centre";
}

$page_title = "Contact Us - " . $site_name;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $service = trim($_POST['service'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    // Basic validation
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Name is required";
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email address is required";
    }
    
    if (empty($subject)) {
        $errors[] = "Subject is required";
    }
    
    if (empty($message)) {
        $errors[] = "Message is required";
    }
    
    if (empty($errors)) {
        try {
            // Insert into database
            $stmt = $pdo->prepare("INSERT INTO contact_submissions (name, email, phone, subject, service, message, submitted_at, status) VALUES (?, ?, ?, ?, ?, ?, NOW(), 'new')");
            $stmt->execute([$name, $email, $phone, $subject, $service, $message]);
            
            $success_message = "Thank you for your message! We'll get back to you within 24 hours.";
            
            // Clear form
            $_POST = [];
            
        } catch (PDOException $e) {
            $errors[] = "Sorry, there was an error submitting your message. Please try again.";
        }
    }
}

// Create contact_submissions table if it doesn't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS contact_submissions (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        phone VARCHAR(50),
        subject VARCHAR(255) NOT NULL,
        service VARCHAR(100),
        message TEXT NOT NULL,
        submitted_at DATETIME NOT NULL,
        status ENUM('new', 'read', 'replied') DEFAULT 'new',
        admin_notes TEXT
    )");
} catch (PDOException $e) {
    // Table creation failed, but we can continue
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <style>
        /* WordPress Style Variables */
        :root {
            --wp-blue: #0073aa;
            --wp-gray-light: #f8f9fa;
            --wp-gray-medium: #e9ecef;
            --wp-gray-dark: #6c757d;
            --wp-black: #343a40;
            --wp-white: #ffffff;
            --wp-success: #28a745;
            --wp-danger: #dc3545;
            --wp-warning: #ffc107;
            --wp-border-radius: 4px;
            --wp-box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            --wp-box-shadow-hover: 0 5px 15px rgba(0,0,0,0.1);
            --wp-transition: all 0.3s ease;
        }

        /* Reset and Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            line-height: 1.6;
            color: var(--wp-black);
            background-color: #f5f5f5;
        }

        .wp-style-container {
            min-height: 100vh;
        }

        .wp-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }

        /* WordPress Hero Section */
        .wp-hero-section {
            position: relative;
            min-height: 50vh;
            display: flex;
            align-items: center;
            overflow: hidden;
            color: var(--wp-white);
            margin-bottom: 30px;
        }

        .wp-hero-background {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
        }

        .wp-hero-slides {
            position: relative;
            width: 100%;
            height: 100%;
        }

        .wp-hero-slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            opacity: 1;
        }

        .wp-hero-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(0,115,170,0.8) 0%, rgba(0,90,135,0.9) 100%);
            z-index: 2;
        }

        .wp-hero-content {
            position: relative;
            z-index: 3;
            width: 100%;
        }

        .wp-hero-text {
            text-align: center;
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .wp-hero-title {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 20px;
            line-height: 1.2;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .wp-hero-subtitle {
            font-size: 1.3rem;
            margin-bottom: 40px;
            opacity: 0.95;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
        }

        /* WordPress Main Content Layout */
        .wp-main-content {
            padding: 30px 0;
        }

        .wp-content-area {
            width: 100%;
        }

        /* WordPress Buttons */
        .wp-btn {
            padding: 12px 24px;
            border: none;
            border-radius: var(--wp-border-radius);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            cursor: pointer;
            transition: var(--wp-transition);
            font-size: 14px;
            text-align: center;
        }

        .wp-btn-primary {
            background: var(--wp-blue);
            color: var(--wp-white);
            border: 1px solid var(--wp-blue);
        }

        .wp-btn-primary:hover {
            background: #005a87;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,115,170,0.3);
        }

        .wp-btn-outline {
            background: transparent;
            border: 1px solid var(--wp-blue);
            color: var(--wp-blue);
        }

        .wp-btn-outline:hover {
            background: var(--wp-blue);
            color: var(--wp-white);
            transform: translateY(-2px);
        }

        /* Contact Section Styles */
        .wp-contact-section {
            margin-bottom: 60px;
        }

        .wp-contact-content {
            background: var(--wp-white);
            border-radius: var(--wp-border-radius);
            box-shadow: var(--wp-box-shadow);
            padding: 50px;
            margin-bottom: 40px;
        }

        .wp-contact-header {
            border-bottom: 2px solid var(--wp-gray-medium);
            padding-bottom: 20px;
            margin-bottom: 40px;
        }

        .wp-contact-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 50px;
            align-items: start;
        }

        /* Contact Information Styles */
        .wp-contact-info {
            padding: 30px;
            background: var(--wp-gray-light);
            border-radius: var(--wp-border-radius);
        }

        .wp-contact-methods {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .wp-contact-method {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 20px;
            background: var(--wp-white);
            border-radius: var(--wp-border-radius);
            box-shadow: var(--wp-box-shadow);
            transition: var(--wp-transition);
            border-left: 4px solid transparent;
        }

        .wp-contact-method:hover {
            transform: translateY(-5px);
            box-shadow: var(--wp-box-shadow-hover);
            border-left-color: var(--wp-blue);
        }

        .wp-contact-icon {
            width: 50px;
            height: 50px;
            background: var(--wp-blue);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--wp-white);
            flex-shrink: 0;
            transition: var(--wp-transition);
        }

        .wp-contact-method:hover .wp-contact-icon {
            transform: scale(1.1);
            background: #005a87;
        }

        .wp-contact-details h3 {
            color: var(--wp-black);
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .wp-contact-details p {
            color: var(--wp-gray-dark);
            margin-bottom: 10px;
            line-height: 1.5;
        }

        .wp-contact-link {
            color: var(--wp-blue);
            text-decoration: none;
            font-weight: 500;
            transition: var(--wp-transition);
        }

        .wp-contact-link:hover {
            color: #005a87;
            text-decoration: underline;
        }

        /* Business Hours */
        .wp-business-hours {
            margin-top: 30px;
            padding: 25px;
            background: var(--wp-white);
            border-radius: var(--wp-border-radius);
            box-shadow: var(--wp-box-shadow);
        }

        .wp-business-hours h3 {
            color: var(--wp-black);
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--wp-gray-light);
        }

        .wp-hours-list {
            list-style: none;
        }

        .wp-hours-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid var(--wp-gray-light);
        }

        .wp-hours-item:last-child {
            border-bottom: none;
        }

        .wp-hours-day {
            font-weight: 600;
            color: var(--wp-black);
        }

        .wp-hours-time {
            color: var(--wp-gray-dark);
        }

        /* Form Styles */
        .wp-form-container {
            background: var(--wp-white);
            padding: 40px;
            border-radius: var(--wp-border-radius);
            box-shadow: var(--wp-box-shadow);
        }

        .wp-form-group {
            margin-bottom: 25px;
        }

        .wp-form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--wp-black);
            font-size: 14px;
        }

        .wp-form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--wp-gray-medium);
            border-radius: var(--wp-border-radius);
            font-size: 14px;
            transition: var(--wp-transition);
            background: var(--wp-white);
        }

        .wp-form-control:focus {
            outline: none;
            border-color: var(--wp-blue);
            box-shadow: 0 0 0 2px rgba(0,115,170,0.2);
        }

        .wp-form-control.select {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 12px center;
            background-repeat: no-repeat;
            background-size: 16px;
            padding-right: 40px;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
        }

        textarea.wp-form-control {
            min-height: 120px;
            resize: vertical;
        }

        .wp-form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .wp-required {
            color: var(--wp-danger);
        }

        /* Alerts and Messages */
        .wp-alert {
            padding: 15px 20px;
            border-radius: var(--wp-border-radius);
            margin: 20px 0;
            border-left: 4px solid transparent;
        }

        .wp-alert-success {
            background: rgba(40,167,69,0.1);
            border-color: var(--wp-success);
            color: #155724;
        }

        .wp-alert-danger {
            background: rgba(220,53,69,0.1);
            border-color: var(--wp-danger);
            color: #721c24;
        }

        /* Quick Links Section */
        .wp-contact-links {
            margin-bottom: 60px;
        }

        .wp-contact-links-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }

        .wp-contact-link-card {
            background: var(--wp-white);
            border-radius: var(--wp-border-radius);
            box-shadow: var(--wp-box-shadow);
            padding: 35px 30px;
            text-align: center;
            transition: var(--wp-transition);
            border: 1px solid var(--wp-gray-medium);
        }

        .wp-contact-link-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--wp-box-shadow-hover);
            border-color: var(--wp-blue);
        }

        .wp-contact-link-card h3 {
            color: var(--wp-blue);
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .wp-contact-link-card p {
            color: var(--wp-gray-dark);
            line-height: 1.6;
            margin-bottom: 25px;
            font-size: 15px;
        }

        /* Map Section */
        .wp-map-section {
            margin: 40px 0;
        }

        .wp-map-container {
            border-radius: var(--wp-border-radius);
            overflow: hidden;
            box-shadow: var(--wp-box-shadow);
            height: 400px;
            background: var(--wp-gray-light);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .wp-hero-title {
                font-size: 2.2rem;
            }

            .wp-hero-subtitle {
                font-size: 1.1rem;
            }

            .wp-contact-content {
                padding: 30px 20px;
            }

            .wp-contact-grid {
                grid-template-columns: 1fr;
                gap: 30px;
            }

            .wp-contact-links-grid {
                grid-template-columns: 1fr;
            }

            .wp-contact-link-card {
                padding: 25px 20px;
            }

            .wp-hero-section {
                min-height: 40vh;
            }

            .wp-form-container {
                padding: 25px 20px;
            }

            .wp-form-row {
                grid-template-columns: 1fr;
            }

            .wp-contact-method {
                flex-direction: column;
                text-align: center;
            }

            .wp-contact-icon {
                align-self: center;
            }
        }

        @media (max-width: 480px) {
            .wp-hero-title {
                font-size: 1.8rem;
            }

            .wp-contact-content {
                padding: 20px 15px;
            }

            .wp-contact-link-card {
                padding: 20px 15px;
            }

            .wp-hero-section {
                min-height: 35vh;
            }

            .wp-form-container {
                padding: 20px 15px;
            }

            .wp-contact-info {
                padding: 20px;
            }
        }

        /* Animations */
        .wp-animate-fade-up {
            opacity: 0;
            transform: translateY(30px);
            animation: wpFadeUp 0.8s ease forwards;
        }

        .wp-animate-fade-left {
            opacity: 0;
            transform: translateX(-30px);
            animation: wpFadeLeft 0.8s ease forwards;
        }

        .wp-animate-fade-right {
            opacity: 0;
            transform: translateX(30px);
            animation: wpFadeRight 0.8s ease forwards;
        }

        .wp-animate-zoom {
            opacity: 0;
            transform: scale(0.9);
            animation: wpZoom 0.6s ease forwards;
        }

        @keyframes wpFadeUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes wpFadeLeft {
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes wpFadeRight {
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes wpZoom {
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
    </style>
</head>
<body>
    <div class="wp-style-container">
        <!-- Hero Section -->
        <section class="wp-hero-section">
            <div class="wp-hero-background">
                <div class="wp-hero-slides">
                    <div class="wp-hero-slide active" style="background-image: url('https://images.unsplash.com/photo-1488646953014-85cb44e25828?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80')"></div>
                </div>
                <div class="wp-hero-overlay"></div>
            </div>
            <div class="wp-hero-content">
                <div class="wp-container">
                    <div class="wp-hero-text">
                        <h1 class="wp-hero-title">Get In Touch</h1>
                        <p class="wp-hero-subtitle">We're here to help you with all your travel and education needs</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Main Content Area -->
        <div class="wp-main-content">
            <div class="wp-container">
                <div class="wp-content-area">
                    <!-- Contact Section -->
                    <section class="wp-contact-section">
                        <div class="wp-contact-content">
                            <!-- Success/Error Messages -->
                            <?php if (isset($success_message)): ?>
                                <div class="wp-alert wp-alert-success">
                                    <?php echo $success_message; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($errors)): ?>
                                <div class="wp-alert wp-alert-danger">
                                    <strong>Please correct the following errors:</strong>
                                    <ul style="margin: 10px 0 0 20px;">
                                        <?php foreach ($errors as $error): ?>
                                            <li><?php echo htmlspecialchars($error); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <div class="wp-contact-grid">
                                <!-- Contact Information -->
                                <div class="wp-contact-info wp-animate-fade-left">
                                    <h2 style="color: var(--wp-blue); margin-bottom: 30px;">Contact Information</h2>
                                    
                                    <div class="wp-contact-methods">
                                        <div class="wp-contact-method">
                                            <div class="wp-contact-icon">📞</div>
                                            <div class="wp-contact-details">
                                                <h3>Phone & WhatsApp</h3>
                                                <p>+234 903 407 2383</p>
                                                <a href="https://wa.me/2349034072383" class="wp-contact-link">Send WhatsApp Message</a>
                                            </div>
                                        </div>
                                        
                                        <div class="wp-contact-method">
                                            <div class="wp-contact-icon">📧</div>
                                            <div class="wp-contact-details">
                                                <h3>Email</h3>
                                                <p>info@travelcentre.ng</p>
                                                <a href="mailto:info@travelcentre.ng" class="wp-contact-link">Send Email</a>
                                            </div>
                                        </div>
                                        
                                        <div class="wp-contact-method">
                                            <div class="wp-contact-icon">📍</div>
                                            <div class="wp-contact-details">
                                                <h3>Office Address</h3>
                                                <p>123 Travel Street, Victoria Island<br>Lagos, Nigeria</p>
                                                <a href="https://www.google.com/maps/place/Victoria+Island,+Lagos/@6.4283662,3.4021453,15z/data=!3m1!4b1!4m6!3m5!1s0x103b8ad8d5e7047b:0x9a9e57e1b2f8a1e8!8m2!3d6.4283662!4d3.4109001!16s%2Fg%2F11g9f8v0_9?entry=ttu" target="_blank" class="wp-contact-link">Get Directions</a>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Business Hours -->
                                    <div class="wp-business-hours">
                                        <h3>Business Hours</h3>
                                        <ul class="wp-hours-list">
                                            <li class="wp-hours-item">
                                                <span class="wp-hours-day">Monday - Friday</span>
                                                <span class="wp-hours-time">9:00 AM - 6:00 PM</span>
                                            </li>
                                            <li class="wp-hours-item">
                                                <span class="wp-hours-day">Saturday</span>
                                                <span class="wp-hours-time">10:00 AM - 4:00 PM</span>
                                            </li>
                                            <li class="wp-hours-item">
                                                <span class="wp-hours-day">Sunday</span>
                                                <span class="wp-hours-time">Closed</span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>

                                <!-- Contact Form -->
                                <div class="wp-form-container wp-animate-fade-right">
                                    <h2 style="color: var(--wp-blue); margin-bottom: 25px;">Send Us a Message</h2>
                                    <form method="POST" id="contactForm">
                                        <div class="wp-form-row">
                                            <div class="wp-form-group">
                                                <label for="name">Full Name <span class="wp-required">*</span></label>
                                                <input type="text" id="name" name="name" class="wp-form-control" 
                                                       value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" 
                                                       required>
                                            </div>
                                            <div class="wp-form-group">
                                                <label for="email">Email Address <span class="wp-required">*</span></label>
                                                <input type="email" id="email" name="email" class="wp-form-control" 
                                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                                       required>
                                            </div>
                                        </div>

                                        <div class="wp-form-row">
                                            <div class="wp-form-group">
                                                <label for="phone">Phone Number</label>
                                                <input type="tel" id="phone" name="phone" class="wp-form-control" 
                                                       value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                                            </div>
                                            <div class="wp-form-group">
                                                <label for="service">Service Interested In</label>
                                                <select id="service" name="service" class="wp-form-control select">
                                                    <option value="">Select a service</option>
                                                    <option value="flight-booking" <?php echo ($_POST['service'] ?? '') === 'flight-booking' ? 'selected' : ''; ?>>Flight Booking</option>
                                                    <option value="hotel-booking" <?php echo ($_POST['service'] ?? '') === 'hotel-booking' ? 'selected' : ''; ?>>Hotel Booking</option>
                                                    <option value="visa-application" <?php echo ($_POST['service'] ?? '') === 'visa-application' ? 'selected' : ''; ?>>Visa Application</option>
                                                    <option value="study-abroad" <?php echo ($_POST['service'] ?? '') === 'study-abroad' ? 'selected' : ''; ?>>Study Abroad</option>
                                                    <option value="corporate-travel" <?php echo ($_POST['service'] ?? '') === 'corporate-travel' ? 'selected' : ''; ?>>Corporate Travel</option>
                                                    <option value="tour-packages" <?php echo ($_POST['service'] ?? '') === 'tour-packages' ? 'selected' : ''; ?>>Tour Packages</option>
                                                    <option value="travel-insurance" <?php echo ($_POST['service'] ?? '') === 'travel-insurance' ? 'selected' : ''; ?>>Travel Insurance</option>
                                                    <option value="pilgrimage" <?php echo ($_POST['service'] ?? '') === 'pilgrimage' ? 'selected' : ''; ?>>Pilgrimage Travel</option>
                                                    <option value="other" <?php echo ($_POST['service'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="wp-form-group">
                                            <label for="subject">Subject <span class="wp-required">*</span></label>
                                            <input type="text" id="subject" name="subject" class="wp-form-control" 
                                                   value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>" 
                                                   required>
                                        </div>

                                        <div class="wp-form-group">
                                            <label for="message">Message <span class="wp-required">*</span></label>
                                            <textarea id="message" name="message" class="wp-form-control" 
                                                      placeholder="Please provide details about your inquiry, including preferred dates, destinations, and any specific requirements..." 
                                                      required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                                        </div>

                                        <div class="wp-form-group">
                                            <button type="submit" class="wp-btn wp-btn-primary" style="padding: 15px 30px; font-size: 16px;">
                                                Send Message
                                            </button>
                                            <button type="reset" class="wp-btn wp-btn-outline" style="margin-left: 15px;">
                                                Clear Form
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <!-- Map Section -->
                            <div class="wp-map-section wp-animate-fade-up">
                                <h3 style="color: var(--wp-blue); margin-bottom: 20px;">Our Location</h3>
                                <div class="wp-map-container">
                                    <iframe 
                                        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3964.611284878952!2d3.408325674996548!3d6.428366293614165!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x103b8ad8d5e7047b%3A0x9a9e57e1b2f8a1e8!2sVictoria%20Island%2C%20Lagos!5e0!3m2!1sen!2sng!4v1700000000000!5m2!1sen!2sng" 
                                        width="100%" 
                                        height="100%" 
                                        style="border:0;" 
                                        allowfullscreen="" 
                                        loading="lazy" 
                                        referrerpolicy="no-referrer-when-downgrade">
                                    </iframe>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Quick Links -->
                    <section class="wp-contact-links">
                        <div class="wp-contact-links-grid">
                            <div class="wp-contact-link-card wp-animate-zoom" style="animation-delay: 100ms;">
                                <h3>Emergency Support</h3>
                                <p>24/7 emergency support for existing clients during their travels.</p>
                                <a href="tel:+2349034072383" class="wp-btn wp-btn-primary">Call Now</a>
                            </div>
                            <div class="wp-contact-link-card wp-animate-zoom" style="animation-delay: 200ms;">
                                <h3>Quick Quote</h3>
                                <p>Get a quick quote for your travel or study abroad plans.</p>
                                <a href="https://wa.me/2349034072383" class="wp-btn wp-btn-outline">Get Quote</a>
                            </div>
                            <div class="wp-contact-link-card wp-animate-zoom" style="animation-delay: 300ms;">
                                <h3>Travel Consultation</h3>
                                <p>Book a free consultation to discuss your travel plans in detail.</p>
                                <a href="https://wa.me/2349034072383?text=Hello%20TravelCentre!%20I'd%20like%20to%20book%20a%20travel%20consultation." class="wp-btn wp-btn-outline">Book Consultation</a>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize animations
            const animatedElements = document.querySelectorAll('[class*="wp-animate-"]');
            animatedElements.forEach(element => {
                // Add intersection observer for scroll animations
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            entry.target.style.animationPlayState = 'running';
                        }
                    });
                }, { threshold: 0.1 });

                observer.observe(element);
            });

            // Form validation enhancement
            const contactForm = document.getElementById('contactForm');
            if (contactForm) {
                contactForm.addEventListener('submit', function(e) {
                    const requiredFields = contactForm.querySelectorAll('[required]');
                    let valid = true;

                    requiredFields.forEach(field => {
                        if (!field.value.trim()) {
                            valid = false;
                            field.style.borderColor = 'var(--wp-danger)';
                        } else {
                            field.style.borderColor = '';
                        }
                    });

                    if (!valid) {
                        e.preventDefault();
                        showNotification('Please fill in all required fields.', 'error');
                    }
                });

                // Real-time validation
                const inputs = contactForm.querySelectorAll('input, textarea, select');
                inputs.forEach(input => {
                    input.addEventListener('input', function() {
                        if (this.hasAttribute('required') && !this.value.trim()) {
                            this.style.borderColor = 'var(--wp-danger)';
                        } else {
                            this.style.borderColor = '';
                        }
                    });
                });
            }

            // WhatsApp integration
            const whatsappLinks = document.querySelectorAll('a[href*="wa.me"]');
            whatsappLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    if (!this.getAttribute('href').includes('text=')) {
                        e.preventDefault();
                        const number = '2349034072383';
                        const message = `Hello TravelCentre! I'm interested in your services and would like to get more information.`;
                        window.open(`https://wa.me/${number}?text=${encodeURIComponent(message)}`, '_blank');
                    }
                });
            });

            // Email integration
            const emailLinks = document.querySelectorAll('a[href^="mailto:"]');
            emailLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const email = 'info@travelcentre.ng';
                    const subject = 'TravelCentre Service Inquiry';
                    const body = `Hello TravelCentre Team,\n\nI'm interested in your services and would like to get more information.\n\nBest regards,\n[Your Name]`;
                    window.location.href = `mailto:${email}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
                });
            });

            function showNotification(message, type = 'info') {
                // Create notification element
                const notification = document.createElement('div');
                const bgColor = type === 'error' ? 'var(--wp-danger)' : 'var(--wp-blue)';
                
                notification.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: ${bgColor};
                    color: white;
                    padding: 15px 20px;
                    border-radius: var(--wp-border-radius);
                    box-shadow: var(--wp-box-shadow);
                    z-index: 1000;
                    transform: translateX(100%);
                    transition: transform 0.3s ease;
                    max-width: 400px;
                `;
                notification.textContent = message;
                
                document.body.appendChild(notification);
                
                // Animate in
                setTimeout(() => {
                    notification.style.transform = 'translateX(0)';
                }, 100);
                
                // Animate out and remove
                setTimeout(() => {
                    notification.style.transform = 'translateX(100%)';
                    setTimeout(() => {
                        if (document.body.contains(notification)) {
                            document.body.removeChild(notification);
                        }
                    }, 300);
                }, 5000);
            }

            // Add hover effects to all interactive elements
            const interactiveElements = document.querySelectorAll('.wp-contact-method, .wp-contact-link-card, .wp-btn');
            interactiveElements.forEach(element => {
                element.style.transition = 'var(--wp-transition)';
            });

            // Map interaction
            const mapContainer = document.querySelector('.wp-map-container iframe');
            if (mapContainer) {
                mapContainer.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.02)';
                    this.style.transition = 'transform 0.3s ease';
                });
                
                mapContainer.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
            }
        });
    </script>
</body>
</html>

<?php
require_once 'includes/footer.php';
?>