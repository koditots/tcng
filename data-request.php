<?php
// data-request.php
require_once 'includes/header.php';

// Database connection and site settings
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

$page_title = "Data Request - " . $site_name;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_type = $_POST['request_type'] ?? '';
    $full_name = $_POST['full_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $description = $_POST['description'] ?? '';
    $verification_method = $_POST['verification_method'] ?? '';
    
    // Basic validation
    $errors = [];
    
    if (empty($request_type)) {
        $errors[] = "Please select a request type";
    }
    
    if (empty($full_name)) {
        $errors[] = "Full name is required";
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email address is required";
    }
    
    if (empty($verification_method)) {
        $errors[] = "Please select a verification method";
    }
    
    if (empty($errors)) {
        // In a real application, you would:
        // 1. Store the request in your database
        // 2. Send confirmation emails
        // 3. Process the data request according to GDPR/other regulations
        
        $success_message = "Your data request has been submitted successfully! We will process your request within 30 days as required by data protection regulations.";
        
        // For demo purposes, we'll just show a success message
        $_POST = []; // Clear form
    }
}
?>


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

        /* Data Request Specific Styles */
        .wp-data-request-section {
            margin-bottom: 60px;
        }

        .wp-data-request-content {
            background: var(--wp-white);
            border-radius: var(--wp-border-radius);
            box-shadow: var(--wp-box-shadow);
            padding: 50px;
            margin-bottom: 40px;
        }

        .wp-data-request-header {
            border-bottom: 2px solid var(--wp-gray-medium);
            padding-bottom: 20px;
            margin-bottom: 40px;
        }

        .wp-last-updated {
            color: var(--wp-gray-dark);
            font-size: 14px;
            font-weight: 500;
            margin: 0;
        }

        /* Request Types Grid */
        .wp-request-types {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin: 30px 0;
        }

        .wp-request-type-card {
            background: var(--wp-white);
            border: 2px solid var(--wp-gray-medium);
            border-radius: var(--wp-border-radius);
            padding: 30px;
            text-align: center;
            transition: var(--wp-transition);
            cursor: pointer;
        }

        .wp-request-type-card:hover {
            border-color: var(--wp-blue);
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .wp-request-type-card.selected {
            border-color: var(--wp-blue);
            background: rgba(0,115,170,0.05);
        }

        .wp-request-icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }

        .wp-request-type-card h3 {
            color: var(--wp-black);
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .wp-request-type-card p {
            color: var(--wp-gray-dark);
            line-height: 1.5;
            font-size: 14px;
        }

        /* Form Styles */
        .wp-form-container {
            background: var(--wp-gray-light);
            padding: 40px;
            border-radius: var(--wp-border-radius);
            margin: 30px 0;
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
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .wp-required {
            color: var(--wp-danger);
        }

        /* Verification Methods */
        .wp-verification-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }

        .wp-verification-method {
            display: flex;
            align-items: center;
            padding: 15px;
            border: 1px solid var(--wp-gray-medium);
            border-radius: var(--wp-border-radius);
            cursor: pointer;
            transition: var(--wp-transition);
        }

        .wp-verification-method:hover {
            border-color: var(--wp-blue);
        }

        .wp-verification-method.selected {
            border-color: var(--wp-blue);
            background: rgba(0,115,170,0.05);
        }

        .wp-verification-method input {
            margin-right: 10px;
        }

        .wp-verification-method label {
            margin: 0;
            cursor: pointer;
            font-weight: 500;
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

        .wp-alert-warning {
            background: rgba(255,193,7,0.1);
            border-color: var(--wp-warning);
            color: #856404;
        }

        .wp-alert-info {
            background: rgba(23,162,184,0.1);
            border-color: #17a2b8;
            color: #0c5460;
        }

        /* Process Steps */
        .wp-process-steps {
            display: flex;
            justify-content: space-between;
            margin: 40px 0;
            position: relative;
        }

        .wp-process-steps::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--wp-gray-medium);
            z-index: 1;
        }

        .wp-process-step {
            text-align: center;
            position: relative;
            z-index: 2;
            flex: 1;
        }

        .wp-step-number {
            width: 40px;
            height: 40px;
            background: var(--wp-white);
            border: 2px solid var(--wp-gray-medium);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-weight: 600;
            transition: var(--wp-transition);
        }

        .wp-process-step.active .wp-step-number {
            background: var(--wp-blue);
            border-color: var(--wp-blue);
            color: var(--wp-white);
        }

        .wp-step-title {
            font-weight: 600;
            font-size: 14px;
            color: var(--wp-black);
        }

        .wp-process-step.active .wp-step-title {
            color: var(--wp-blue);
        }

        /* Contact Information */
        .wp-contact-info {
            background: var(--wp-gray-light);
            padding: 25px;
            border-radius: var(--wp-border-radius);
            margin: 20px 0;
        }

        .wp-contact-info p {
            margin-bottom: 10px;
            line-height: 1.5;
        }

        /* Quick Links Section */
        .wp-data-request-links {
            margin-bottom: 60px;
        }

        .wp-data-request-links-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }

        .wp-data-request-link-card {
            background: var(--wp-white);
            border-radius: var(--wp-border-radius);
            box-shadow: var(--wp-box-shadow);
            padding: 35px 30px;
            text-align: center;
            transition: var(--wp-transition);
            border: 1px solid var(--wp-gray-medium);
        }

        .wp-data-request-link-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            border-color: var(--wp-blue);
        }

        .wp-data-request-link-card h3 {
            color: var(--wp-blue);
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .wp-data-request-link-card p {
            color: var(--wp-gray-dark);
            line-height: 1.6;
            margin-bottom: 25px;
            font-size: 15px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .wp-hero-title {
                font-size: 2.2rem;
            }

            .wp-hero-subtitle {
                font-size: 1.1rem;
            }

            .wp-data-request-content {
                padding: 30px 20px;
            }

            .wp-request-types {
                grid-template-columns: 1fr;
            }

            .wp-data-request-links-grid {
                grid-template-columns: 1fr;
            }

            .wp-data-request-link-card {
                padding: 25px 20px;
            }

            .wp-contact-info {
                padding: 20px;
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

            .wp-process-steps {
                flex-direction: column;
                gap: 20px;
            }

            .wp-process-steps::before {
                display: none;
            }

            .wp-process-step {
                text-align: left;
                display: flex;
                align-items: center;
                gap: 15px;
            }

            .wp-step-number {
                margin: 0;
                flex-shrink: 0;
            }
        }

        @media (max-width: 480px) {
            .wp-hero-title {
                font-size: 1.8rem;
            }

            .wp-data-request-content {
                padding: 20px 15px;
            }

            .wp-data-request-link-card {
                padding: 20px 15px;
            }

            .wp-hero-section {
                min-height: 35vh;
            }

            .wp-form-container {
                padding: 20px 15px;
            }

            .wp-verification-methods {
                grid-template-columns: 1fr;
            }
        }

        /* Print Styles */
        @media print {
            .wp-hero-section,
            .wp-data-request-links,
            .wp-process-steps {
                display: none;
            }

            .wp-data-request-content {
                box-shadow: none;
                padding: 0;
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
                    <div class="wp-hero-slide active" style="background-image: url('https://images.unsplash.com/photo-1551288049-bebda4e38f71?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80')"></div>
                </div>
                <div class="wp-hero-overlay"></div>
            </div>
            <div class="wp-hero-content">
                <div class="wp-container">
                    <div class="wp-hero-text">
                        <h1 class="wp-hero-title">Data Request Portal</h1>
                        <p class="wp-hero-subtitle">Exercise your data protection rights and manage your personal information with us.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Main Content Area -->
        <div class="wp-main-content">
            <div class="wp-container">
                <div class="wp-content-area">
                    <!-- Data Request Content -->
                    <section class="wp-data-request-section">
                        <div class="wp-data-request-content">
                            <!-- Last Updated -->
                            <div class="wp-data-request-header">
                                <p class="wp-last-updated">Last Updated: <?php echo date('F j, Y'); ?></p>
                            </div>

                            <!-- Success Message -->
                            <?php if (isset($success_message)): ?>
                                <div class="wp-alert wp-alert-success">
                                    <?php echo $success_message; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Error Messages -->
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

                            <!-- Process Steps -->
                            <div class="wp-process-steps">
                                <div class="wp-process-step active">
                                    <div class="wp-step-number">1</div>
                                    <div class="wp-step-title">Select Request Type</div>
                                </div>
                                <div class="wp-process-step">
                                    <div class="wp-step-number">2</div>
                                    <div class="wp-step-title">Provide Details</div>
                                </div>
                                <div class="wp-process-step">
                                    <div class="wp-step-number">3</div>
                                    <div class="wp-step-title">Submit & Verify</div>
                                </div>
                            </div>

                            <!-- Information Alert -->
                            <div class="wp-alert wp-alert-info">
                                <strong>Important:</strong> Under data protection regulations, we have 30 days to respond to your request. We may need to verify your identity before processing certain requests.
                            </div>

                            <!-- Request Types -->
                            <h2 style="color: var(--wp-blue); margin: 30px 0 20px 0;">Select Your Request Type</h2>
                            <div class="wp-request-types" id="requestTypes">
                                <div class="wp-request-type-card" data-type="access">
                                    <div class="wp-request-icon">📋</div>
                                    <h3>Access Request</h3>
                                    <p>Request a copy of all personal data we hold about you</p>
                                </div>
                                <div class="wp-request-type-card" data-type="correction">
                                    <div class="wp-request-icon">✏️</div>
                                    <h3>Correction Request</h3>
                                    <p>Request correction of inaccurate or incomplete personal data</p>
                                </div>
                                <div class="wp-request-type-card" data-type="deletion">
                                    <div class="wp-request-icon">🗑️</div>
                                    <h3>Deletion Request</h3>
                                    <p>Request deletion of your personal data (Right to be Forgotten)</p>
                                </div>
                                <div class="wp-request-type-card" data-type="restriction">
                                    <div class="wp-request-icon">⏸️</div>
                                    <h3>Restriction Request</h3>
                                    <p>Request restriction of processing your personal data</p>
                                </div>
                                <div class="wp-request-type-card" data-type="objection">
                                    <div class="wp-request-icon">🚫</div>
                                    <h3>Objection Request</h3>
                                    <p>Object to processing of your personal data</p>
                                </div>
                                <div class="wp-request-type-card" data-type="portability">
                                    <div class="wp-request-icon">📤</div>
                                    <h3>Portability Request</h3>
                                    <p>Request transfer of your data to another organization</p>
                                </div>
                            </div>

                            <!-- Data Request Form -->
                            <form method="POST" class="wp-form-container" id="dataRequestForm">
                                <input type="hidden" name="request_type" id="requestType" value="">

                                <h3 style="color: var(--wp-blue); margin-bottom: 25px;">Request Details</h3>

                                <div class="wp-form-row">
                                    <div class="wp-form-group">
                                        <label for="full_name">Full Name <span class="wp-required">*</span></label>
                                        <input type="text" id="full_name" name="full_name" class="wp-form-control" 
                                               value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" 
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
                                        <label for="user_id">User ID (if known)</label>
                                        <input type="text" id="user_id" name="user_id" class="wp-form-control" 
                                               value="<?php echo htmlspecialchars($_POST['user_id'] ?? ''); ?>">
                                    </div>
                                </div>

                                <div class="wp-form-group">
                                    <label for="description">Additional Details <span class="wp-required">*</span></label>
                                    <textarea id="description" name="description" class="wp-form-control" 
                                              placeholder="Please provide specific details about your request. For access requests, specify if you need data from a particular time period. For correction requests, indicate what information needs to be corrected..." 
                                              required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                </div>

                                <div class="wp-form-group">
                                    <label>Identity Verification Method <span class="wp-required">*</span></label>
                                    <p style="color: var(--wp-gray-dark); font-size: 14px; margin-bottom: 15px;">
                                        To protect your privacy, we need to verify your identity before processing certain requests.
                                    </p>
                                    <div class="wp-verification-methods">
                                        <div class="wp-verification-method">
                                            <input type="radio" id="verify_email" name="verification_method" value="email" 
                                                   <?php echo ($_POST['verification_method'] ?? '') === 'email' ? 'checked' : ''; ?> required>
                                            <label for="verify_email">Email Verification</label>
                                        </div>
                                        <div class="wp-verification-method">
                                            <input type="radio" id="verify_phone" name="verification_method" value="phone"
                                                   <?php echo ($_POST['verification_method'] ?? '') === 'phone' ? 'checked' : ''; ?>>
                                            <label for="verify_phone">Phone Verification</label>
                                        </div>
                                        <div class="wp-verification-method">
                                            <input type="radio" id="verify_id" name="verification_method" value="id_document"
                                                   <?php echo ($_POST['verification_method'] ?? '') === 'id_document' ? 'checked' : ''; ?>>
                                            <label for="verify_id">ID Document</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="wp-form-group">
                                    <div class="wp-alert wp-alert-warning">
                                        <strong>Privacy Notice:</strong> The information you provide in this form will only be used to process your data request and verify your identity. We will not use this information for marketing purposes.
                                    </div>
                                </div>

                                <div class="wp-form-group">
                                    <button type="submit" class="wp-btn wp-btn-primary" style="padding: 15px 30px; font-size: 16px;">
                                        Submit Data Request
                                    </button>
                                    <button type="reset" class="wp-btn wp-btn-outline" style="margin-left: 15px;">
                                        Clear Form
                                    </button>
                                </div>
                            </form>

                            <!-- Contact Information -->
                            <div class="wp-contact-info">
                                <h3 style="color: var(--wp-blue); margin-bottom: 15px;">Need Help?</h3>
                                <p><strong>Email:</strong> privacy@<?php echo strtolower(str_replace(' ', '', $site_name)); ?>.com</p>
                                <p><strong>Phone:</strong> +234-1-700-0000</p>
                                <p><strong>Address:</strong> 
                                    <?php echo $site_name; ?> Privacy Office<br>
                                    123 Travel Street, Victoria Island<br>
                                    Lagos, Nigeria
                                </p>
                                <p style="margin-top: 15px; font-size: 14px; color: var(--wp-gray-dark);">
                                    For complex requests or if you prefer not to use this form, you can contact our Privacy Office directly.
                                </p>
                            </div>
                        </div>
                    </section>

                    <!-- Quick Links -->
                    <section class="wp-data-request-links">
                        <div class="wp-data-request-links-grid">
                            <div class="wp-data-request-link-card">
                                <h3>Privacy Policy</h3>
                                <p>Learn how we protect and manage your personal information.</p>
                                <a href="privacy.php" class="wp-btn wp-btn-outline">View Policy</a>
                            </div>
                            <div class="wp-data-request-link-card">
                                <h3>Cookie Policy</h3>
                                <p>Learn more about how we use cookies and tracking technologies.</p>
                                <a href="cookies.php" class="wp-btn wp-btn-outline">Cookie Settings</a>
                            </div>
                            <div class="wp-data-request-link-card">
                                <h3>Terms of Service</h3>
                                <p>Read our terms and conditions for using our travel booking services.</p>
                                <a href="terms.php" class="wp-btn wp-btn-outline">View Terms</a>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Request type selection
            const requestTypeCards = document.querySelectorAll('.wp-request-type-card');
            const requestTypeInput = document.getElementById('requestType');

            requestTypeCards.forEach(card => {
                card.addEventListener('click', function() {
                    // Remove selected class from all cards
                    requestTypeCards.forEach(c => c.classList.remove('selected'));
                    
                    // Add selected class to clicked card
                    this.classList.add('selected');
                    
                    // Update hidden input
                    const requestType = this.getAttribute('data-type');
                    requestTypeInput.value = requestType;
                    
                    // Update form description placeholder based on request type
                    updateDescriptionPlaceholder(requestType);
                    
                    // Scroll to form
                    document.getElementById('dataRequestForm').scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                });
            });

            function updateDescriptionPlaceholder(type) {
                const descriptionField = document.getElementById('description');
                const placeholders = {
                    'access': 'Please specify if you need data from a particular time period or specific services (e.g., flight bookings from 2023, hotel reservations, etc.)...',
                    'correction': 'Please specify exactly what information needs to be corrected and provide the correct information. Include any supporting documents if available...',
                    'deletion': 'Please explain why you are requesting deletion of your data. Note that we may not be able to delete all data due to legal obligations...',
                    'restriction': 'Please specify why you want to restrict processing of your data and which data processing activities you want to restrict...',
                    'objection': 'Please specify the processing activities you object to and the reasons for your objection...',
                    'portability': 'Please specify the format you prefer for data transfer and the organization you want the data transferred to...'
                };
                
                descriptionField.placeholder = placeholders[type] || 'Please provide specific details about your request...';
            }

            // Form validation
            const form = document.getElementById('dataRequestForm');
            form.addEventListener('submit', function(e) {
                if (!requestTypeInput.value) {
                    e.preventDefault();
                    showNotification('Please select a request type before submitting.', 'error');
                    document.getElementById('requestTypes').scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                    return;
                }
                
                // Additional validation can be added here
                console.log('Submitting data request:', {
                    type: requestTypeInput.value,
                    name: document.getElementById('full_name').value,
                    email: document.getElementById('email').value
                });
            });

            // Verification method selection
            const verificationMethods = document.querySelectorAll('.wp-verification-method');
            verificationMethods.forEach(method => {
                method.addEventListener('click', function() {
                    const radio = this.querySelector('input[type="radio"]');
                    radio.checked = true;
                    
                    // Update visual selection
                    verificationMethods.forEach(m => m.classList.remove('selected'));
                    this.classList.add('selected');
                });
            });

            // Reset form functionality
            form.addEventListener('reset', function() {
                requestTypeCards.forEach(card => card.classList.remove('selected'));
                requestTypeInput.value = '';
                verificationMethods.forEach(method => method.classList.remove('selected'));
            });

            function showNotification(message, type = 'info') {
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

            // Add intersection observer for animations
            const observerOptions = {
                root: null,
                rootMargin: '0px',
                threshold: 0.1
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, observerOptions);

            // Observe elements for animation
            const animatedElements = document.querySelectorAll('.wp-request-type-card, .wp-data-request-link-card');
            animatedElements.forEach(element => {
                element.style.opacity = '0';
                element.style.transform = 'translateY(20px)';
                element.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                observer.observe(element);
            });
        });
    </script>
<?php
require_once 'includes/footer.php';
?>