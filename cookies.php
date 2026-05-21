<?php
// cookies.php
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

$page_title = "Cookie Policy - " . $site_name;
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

        /* Cookie Policy Specific Styles */
        .wp-cookie-section {
            margin-bottom: 60px;
        }

        .wp-cookie-content {
            background: var(--wp-white);
            border-radius: var(--wp-border-radius);
            box-shadow: var(--wp-box-shadow);
            padding: 50px;
            margin-bottom: 40px;
        }

        .wp-cookie-header {
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

        .wp-cookie-section-content {
            margin-bottom: 40px;
            padding-bottom: 30px;
            border-bottom: 1px solid var(--wp-gray-medium);
        }

        .wp-cookie-section-content:last-of-type {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .wp-cookie-section-content h2 {
            color: var(--wp-blue);
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--wp-gray-light);
        }

        .wp-cookie-section-content h3 {
            color: var(--wp-black);
            font-size: 1.3rem;
            font-weight: 600;
            margin: 25px 0 15px 0;
        }

        .wp-cookie-section-content p {
            color: var(--wp-gray-dark);
            line-height: 1.7;
            margin-bottom: 15px;
            font-size: 15px;
        }

        .wp-cookie-section-content ul {
            margin: 15px 0;
            padding-left: 20px;
        }

        .wp-cookie-section-content li {
            color: var(--wp-gray-dark);
            line-height: 1.6;
            margin-bottom: 8px;
            font-size: 15px;
        }

        .wp-cookie-section-content strong {
            color: var(--wp-black);
            font-weight: 600;
        }

        /* Cookie Settings Panel */
        .wp-cookie-settings {
            background: var(--wp-gray-light);
            padding: 30px;
            border-radius: var(--wp-border-radius);
            margin: 30px 0;
        }

        .wp-cookie-settings h3 {
            color: var(--wp-blue);
            font-size: 1.4rem;
            margin-bottom: 20px;
        }

        .wp-cookie-toggle {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid var(--wp-gray-medium);
        }

        .wp-cookie-toggle:last-child {
            border-bottom: none;
        }

        .wp-cookie-toggle-info h4 {
            color: var(--wp-black);
            font-size: 1.1rem;
            margin-bottom: 5px;
        }

        .wp-cookie-toggle-info p {
            color: var(--wp-gray-dark);
            font-size: 14px;
            margin: 0;
        }

        .wp-toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 30px;
        }

        .wp-toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .wp-toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }

        .wp-toggle-slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .wp-toggle-slider {
            background-color: var(--wp-blue);
        }

        input:checked + .wp-toggle-slider:before {
            transform: translateX(30px);
        }

        /* Cookie Table */
        .wp-cookie-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: var(--wp-white);
            border-radius: var(--wp-border-radius);
            overflow: hidden;
            box-shadow: var(--wp-box-shadow);
        }

        .wp-cookie-table th {
            background: var(--wp-blue);
            color: var(--wp-white);
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }

        .wp-cookie-table td {
            padding: 15px;
            border-bottom: 1px solid var(--wp-gray-medium);
        }

        .wp-cookie-table tr:last-child td {
            border-bottom: none;
        }

        .wp-cookie-table tr:hover {
            background: var(--wp-gray-light);
        }

        .wp-cookie-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 600;
            margin-right: 5px;
        }

        .wp-badge-essential {
            background: #28a745;
            color: white;
        }

        .wp-badge-functional {
            background: #17a2b8;
            color: white;
        }

        .wp-badge-performance {
            background: #ffc107;
            color: black;
        }

        .wp-badge-marketing {
            background: #dc3545;
            color: white;
        }

        /* Cookie Actions */
        .wp-cookie-actions {
            display: flex;
            gap: 15px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

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

        .wp-consent-section {
            background: linear-gradient(135deg, var(--wp-blue) 0%, #005a87 100%);
            color: var(--wp-white);
            padding: 30px;
            border-radius: var(--wp-border-radius);
            text-align: center;
            margin-top: 40px;
        }

        .wp-consent-section p {
            color: var(--wp-white);
            font-size: 16px;
            font-weight: 500;
            margin: 0;
            line-height: 1.6;
        }

        /* Quick Links Section */
        .wp-cookie-links {
            margin-bottom: 60px;
        }

        .wp-cookie-links-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }

        .wp-cookie-link-card {
            background: var(--wp-white);
            border-radius: var(--wp-border-radius);
            box-shadow: var(--wp-box-shadow);
            padding: 35px 30px;
            text-align: center;
            transition: var(--wp-transition);
            border: 1px solid var(--wp-gray-medium);
        }

        .wp-cookie-link-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            border-color: var(--wp-blue);
        }

        .wp-cookie-link-card h3 {
            color: var(--wp-blue);
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .wp-cookie-link-card p {
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

            .wp-cookie-content {
                padding: 30px 20px;
            }

            .wp-cookie-section-content h2 {
                font-size: 1.5rem;
            }

            .wp-cookie-section-content h3 {
                font-size: 1.2rem;
            }

            .wp-cookie-links-grid {
                grid-template-columns: 1fr;
            }

            .wp-cookie-link-card {
                padding: 25px 20px;
            }

            .wp-contact-info {
                padding: 20px;
            }

            .wp-consent-section {
                padding: 25px 20px;
            }

            .wp-hero-section {
                min-height: 40vh;
            }

            .wp-cookie-table {
                display: block;
                overflow-x: auto;
            }

            .wp-cookie-actions {
                flex-direction: column;
            }

            .wp-cookie-toggle {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .wp-toggle-switch {
                align-self: flex-end;
            }
        }

        @media (max-width: 480px) {
            .wp-hero-title {
                font-size: 1.8rem;
            }

            .wp-cookie-content {
                padding: 20px 15px;
            }

            .wp-cookie-section-content h2 {
                font-size: 1.3rem;
            }

            .wp-cookie-section-content h3 {
                font-size: 1.1rem;
            }

            .wp-cookie-section-content p,
            .wp-cookie-section-content li {
                font-size: 14px;
            }

            .wp-cookie-link-card {
                padding: 20px 15px;
            }

            .wp-hero-section {
                min-height: 35vh;
            }

            .wp-cookie-settings {
                padding: 20px 15px;
            }
        }

        /* Print Styles */
        @media print {
            .wp-hero-section,
            .wp-cookie-links,
            .wp-consent-section,
            .wp-cookie-settings {
                display: none;
            }

            .wp-cookie-content {
                box-shadow: none;
                padding: 0;
            }

            .wp-cookie-section-content {
                break-inside: avoid;
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
                        <h1 class="wp-hero-title">Cookie Policy</h1>
                        <p class="wp-hero-subtitle">Learn how we use cookies and similar technologies to enhance your browsing experience.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Main Content Area -->
        <div class="wp-main-content">
            <div class="wp-container">
                <div class="wp-content-area">
                    <!-- Cookie Policy Content -->
                    <section class="wp-cookie-section">
                        <div class="wp-cookie-content">
                            <!-- Last Updated -->
                            <div class="wp-cookie-header">
                                <p class="wp-last-updated">Last Updated: <?php echo date('F j, Y'); ?></p>
                            </div>

                            <!-- Introduction -->
                            <div class="wp-cookie-section-content">
                                <h2>1. What Are Cookies?</h2>
                                <p>Cookies are small text files that are stored on your computer or mobile device when you visit our website. They help us provide you with a better experience by remembering your preferences, understanding how you use our site, and showing you relevant content.</p>
                                <p>Cookies are widely used to make websites work more efficiently and provide information to the website owners.</p>
                            </div>

                            <!-- Types of Cookies -->
                            <div class="wp-cookie-section-content">
                                <h2>2. Types of Cookies We Use</h2>
                                
                                <h3>2.1 Essential Cookies</h3>
                                <p>These cookies are necessary for the website to function properly. They enable basic functions like page navigation and access to secure areas of the website. The website cannot function properly without these cookies.</p>

                                <h3>2.2 Functional Cookies</h3>
                                <p>These cookies allow the website to remember choices you make and provide enhanced, more personal features. They may be set by us or by third-party providers whose services we have added to our pages.</p>

                                <h3>2.3 Performance Cookies</h3>
                                <p>These cookies help us understand how visitors interact with our website by collecting and reporting information anonymously. They help us improve how our website works.</p>

                                <h3>2.4 Marketing Cookies</h3>
                                <p>These cookies are used to track visitors across websites. The intention is to display ads that are relevant and engaging for the individual user and thereby more valuable for publishers and third-party advertisers.</p>
                            </div>

                            <!-- Cookie Settings Panel -->
                            <div class="wp-cookie-settings">
                                <h3>Cookie Preferences</h3>
                                <p>Manage your cookie preferences below. Essential cookies cannot be disabled as they are necessary for the website to function properly.</p>
                                
                                <div class="wp-cookie-toggle">
                                    <div class="wp-cookie-toggle-info">
                                        <h4>Essential Cookies</h4>
                                        <p>Required for basic website functionality</p>
                                    </div>
                                    <label class="wp-toggle-switch">
                                        <input type="checkbox" checked disabled>
                                        <span class="wp-toggle-slider"></span>
                                    </label>
                                </div>

                                <div class="wp-cookie-toggle">
                                    <div class="wp-cookie-toggle-info">
                                        <h4>Functional Cookies</h4>
                                        <p>Remember your preferences and settings</p>
                                    </div>
                                    <label class="wp-toggle-switch">
                                        <input type="checkbox" id="functionalCookies" checked>
                                        <span class="wp-toggle-slider"></span>
                                    </label>
                                </div>

                                <div class="wp-cookie-toggle">
                                    <div class="wp-cookie-toggle-info">
                                        <h4>Performance Cookies</h4>
                                        <p>Help us improve our website</p>
                                    </div>
                                    <label class="wp-toggle-switch">
                                        <input type="checkbox" id="performanceCookies" checked>
                                        <span class="wp-toggle-slider"></span>
                                    </label>
                                </div>

                                <div class="wp-cookie-toggle">
                                    <div class="wp-cookie-toggle-info">
                                        <h4>Marketing Cookies</h4>
                                        <p>Show you relevant advertisements</p>
                                    </div>
                                    <label class="wp-toggle-switch">
                                        <input type="checkbox" id="marketingCookies">
                                        <span class="wp-toggle-slider"></span>
                                    </label>
                                </div>

                                <div class="wp-cookie-actions">
                                    <button class="wp-btn wp-btn-primary" id="savePreferences">Save Preferences</button>
                                    <button class="wp-btn wp-btn-outline" id="acceptAll">Accept All Cookies</button>
                                    <button class="wp-btn wp-btn-outline" id="rejectAll">Reject All Non-Essential</button>
                                </div>
                            </div>

                            <!-- Detailed Cookie Information -->
                            <div class="wp-cookie-section-content">
                                <h2>3. Detailed Cookie Information</h2>
                                <p>The table below provides more information about the cookies we use and why:</p>

                                <table class="wp-cookie-table">
                                    <thead>
                                        <tr>
                                            <th>Cookie Name</th>
                                            <th>Type</th>
                                            <th>Purpose</th>
                                            <th>Duration</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>session_id</td>
                                            <td><span class="wp-cookie-badge wp-badge-essential">Essential</span></td>
                                            <td>Maintains your session state across page requests</td>
                                            <td>Session</td>
                                        </tr>
                                        <tr>
                                            <td>user_preferences</td>
                                            <td><span class="wp-cookie-badge wp-badge-functional">Functional</span></td>
                                            <td>Stores your language and currency preferences</td>
                                            <td>1 year</td>
                                        </tr>
                                        <tr>
                                            <td>_ga</td>
                                            <td><span class="wp-cookie-badge wp-badge-performance">Performance</span></td>
                                            <td>Google Analytics - distinguishes unique users</td>
                                            <td>2 years</td>
                                        </tr>
                                        <tr>
                                            <td>_gid</td>
                                            <td><span class="wp-cookie-badge wp-badge-performance">Performance</span></td>
                                            <td>Google Analytics - distinguishes unique users</td>
                                            <td>24 hours</td>
                                        </tr>
                                        <tr>
                                            <td>_fbp</td>
                                            <td><span class="wp-cookie-badge wp-badge-marketing">Marketing</span></td>
                                            <td>Facebook Pixel - tracks conversion events</td>
                                            <td>3 months</td>
                                        </tr>
                                        <tr>
                                            <td>ads_preferences</td>
                                            <td><span class="wp-cookie-badge wp-badge-marketing">Marketing</span></td>
                                            <td>Stores your advertising preferences</td>
                                            <td>1 year</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Managing Cookies -->
                            <div class="wp-cookie-section-content">
                                <h2>4. Managing Cookies</h2>
                                
                                <h3>4.1 Browser Settings</h3>
                                <p>You can control and/or delete cookies as you wish. You can delete all cookies that are already on your computer and you can set most browsers to prevent them from being placed. However, if you do this, you may have to manually adjust some preferences every time you visit a site and some services and functionalities may not work.</p>

                                <h3>4.2 How to Manage Cookies in Popular Browsers</h3>
                                <ul>
                                    <li><strong>Google Chrome:</strong> Settings → Privacy and security → Cookies and other site data</li>
                                    <li><strong>Mozilla Firefox:</strong> Options → Privacy & Security → Cookies and Site Data</li>
                                    <li><strong>Safari:</strong> Preferences → Privacy → Cookies and website data</li>
                                    <li><strong>Microsoft Edge:</strong> Settings → Privacy, search, and services → Cookies</li>
                                </ul>

                                <h3>4.3 Third-Party Cookies</h3>
                                <p>Please note that third parties (including, for example, advertising networks and providers of external services like web traffic analysis services) may also use cookies, over which we have no control.</p>
                            </div>

                            <!-- Changes to Policy -->
                            <div class="wp-cookie-section-content">
                                <h2>5. Changes to This Cookie Policy</h2>
                                <p>We may update this Cookie Policy from time to time to reflect changes in our practices or for other operational, legal, or regulatory reasons. We will notify you of any material changes by posting the updated policy on our website with a new "Last Updated" date.</p>
                            </div>

                            <!-- Contact Information -->
                            <div class="wp-cookie-section-content">
                                <h2>6. Contact Us</h2>
                                <p>If you have any questions about our use of cookies or other technologies, please contact us:</p>
                                <div class="wp-contact-info">
                                    <p><strong>Email:</strong> privacy@<?php echo strtolower(str_replace(' ', '', $site_name)); ?>.com</p>
                                    <p><strong>Phone:</strong> +234-1-700-0000</p>
                                    <p><strong>Address:</strong> 
                                        <?php echo $site_name; ?> Privacy Office<br>
                                        123 Travel Street, Victoria Island<br>
                                        Lagos, Nigeria
                                    </p>
                                </div>
                            </div>

                            <!-- Consent Acknowledgement -->
                            <div class="wp-consent-section">
                                <p>By using our website and adjusting your cookie preferences, you consent to our use of cookies as described in this policy.</p>
                            </div>
                        </div>
                    </section>

                    <!-- Quick Links -->
                    <section class="wp-cookie-links">
                        <div class="wp-cookie-links-grid">
                            <div class="wp-cookie-link-card">
                                <h3>Privacy Policy</h3>
                                <p>Learn how we protect and manage your personal information.</p>
                                <a href="privacy.php" class="wp-btn wp-btn-outline">View Policy</a>
                            </div>
                            <div class="wp-cookie-link-card">
                                <h3>Terms of Service</h3>
                                <p>Read our terms and conditions for using our travel booking services.</p>
                                <a href="terms.php" class="wp-btn wp-btn-outline">View Terms</a>
                            </div>
                            <div class="wp-cookie-link-card">
                                <h3>Data Request</h3>
                                <p>Submit a request to access, correct, or delete your personal data.</p>
                                <a href="data-request.php" class="wp-btn wp-btn-outline">Submit Request</a>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Load saved preferences
            loadCookiePreferences();

            // Save preferences button
            document.getElementById('savePreferences').addEventListener('click', function() {
                saveCookiePreferences();
                showNotification('Cookie preferences saved successfully!');
            });

            // Accept all cookies
            document.getElementById('acceptAll').addEventListener('click', function() {
                document.getElementById('functionalCookies').checked = true;
                document.getElementById('performanceCookies').checked = true;
                document.getElementById('marketingCookies').checked = true;
                saveCookiePreferences();
                showNotification('All cookies accepted!');
            });

            // Reject all non-essential cookies
            document.getElementById('rejectAll').addEventListener('click', function() {
                document.getElementById('functionalCookies').checked = false;
                document.getElementById('performanceCookies').checked = false;
                document.getElementById('marketingCookies').checked = false;
                saveCookiePreferences();
                showNotification('Non-essential cookies rejected!');
            });

            function loadCookiePreferences() {
                const preferences = JSON.parse(localStorage.getItem('cookiePreferences') || '{}');
                
                if (preferences.functional !== undefined) {
                    document.getElementById('functionalCookies').checked = preferences.functional;
                }
                if (preferences.performance !== undefined) {
                    document.getElementById('performanceCookies').checked = preferences.performance;
                }
                if (preferences.marketing !== undefined) {
                    document.getElementById('marketingCookies').checked = preferences.marketing;
                }
            }

            function saveCookiePreferences() {
                const preferences = {
                    functional: document.getElementById('functionalCookies').checked,
                    performance: document.getElementById('performanceCookies').checked,
                    marketing: document.getElementById('marketingCookies').checked,
                    timestamp: new Date().toISOString()
                };
                
                localStorage.setItem('cookiePreferences', JSON.stringify(preferences));
                
                // Update cookie consent in a real implementation
                updateCookieConsent(preferences);
            }

            function updateCookieConsent(preferences) {
                // In a real implementation, this would set the actual cookies
                // based on user preferences
                console.log('Updating cookie consent:', preferences);
                
                // Example: Set a consent cookie
                document.cookie = `cookie_consent=true; max-age=31536000; path=/; samesite=lax`;
                
                if (!preferences.marketing) {
                    // Example: Clear marketing cookies
                    clearMarketingCookies();
                }
            }

            function clearMarketingCookies() {
                // In a real implementation, this would clear marketing cookies
                const cookies = document.cookie.split(';');
                cookies.forEach(cookie => {
                    const cookieName = cookie.split('=')[0].trim();
                    if (cookieName.includes('_fbp') || cookieName.includes('ads_')) {
                        document.cookie = `${cookieName}=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;`;
                    }
                });
            }

            function showNotification(message) {
                // Create notification element
                const notification = document.createElement('div');
                notification.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: var(--wp-blue);
                    color: white;
                    padding: 15px 20px;
                    border-radius: var(--wp-border-radius);
                    box-shadow: var(--wp-box-shadow);
                    z-index: 1000;
                    transform: translateX(100%);
                    transition: transform 0.3s ease;
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
                        document.body.removeChild(notification);
                    }, 300);
                }, 3000);
            }

            // Add smooth scrolling for anchor links
            const anchorLinks = document.querySelectorAll('a[href^="#"]');
            anchorLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const targetId = this.getAttribute('href').substring(1);
                    const targetElement = document.getElementById(targetId);
                    if (targetElement) {
                        targetElement.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });

            // Print functionality
            const printButton = document.createElement('button');
            printButton.textContent = 'Print Cookie Policy';
            printButton.className = 'wp-btn wp-btn-primary';
            printButton.style.marginBottom = '20px';
            printButton.addEventListener('click', function() {
                window.print();
            });

            const cookieHeader = document.querySelector('.wp-cookie-header');
            if (cookieHeader) {
                cookieHeader.parentNode.insertBefore(printButton, cookieHeader);
            }

            // Add intersection observer for section animations
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

            // Observe cookie sections for animation
            const sections = document.querySelectorAll('.wp-cookie-section-content');
            sections.forEach(section => {
                section.style.opacity = '0';
                section.style.transform = 'translateY(20px)';
                section.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                observer.observe(section);
            });

            const linkCards = document.querySelectorAll('.wp-cookie-link-card');
            linkCards.forEach(card => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                observer.observe(card);
            });
        });
    </script>
<?php
require_once 'includes/footer.php';
?>