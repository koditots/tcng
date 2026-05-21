<?php
// privacy.php
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

$page_title = "Privacy Policy - " . $site_name;
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

        /* Privacy Policy Specific Styles */
        .wp-privacy-section {
            margin-bottom: 60px;
        }

        .wp-privacy-content {
            background: var(--wp-white);
            border-radius: var(--wp-border-radius);
            box-shadow: var(--wp-box-shadow);
            padding: 50px;
            margin-bottom: 40px;
        }

        .wp-privacy-header {
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

        .wp-privacy-section-content {
            margin-bottom: 40px;
            padding-bottom: 30px;
            border-bottom: 1px solid var(--wp-gray-medium);
        }

        .wp-privacy-section-content:last-of-type {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .wp-privacy-section-content h2 {
            color: var(--wp-blue);
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--wp-gray-light);
        }

        .wp-privacy-section-content h3 {
            color: var(--wp-black);
            font-size: 1.3rem;
            font-weight: 600;
            margin: 25px 0 15px 0;
        }

        .wp-privacy-section-content p {
            color: var(--wp-gray-dark);
            line-height: 1.7;
            margin-bottom: 15px;
            font-size: 15px;
        }

        .wp-privacy-section-content ul {
            margin: 15px 0;
            padding-left: 20px;
        }

        .wp-privacy-section-content li {
            color: var(--wp-gray-dark);
            line-height: 1.6;
            margin-bottom: 8px;
            font-size: 15px;
        }

        .wp-privacy-section-content strong {
            color: var(--wp-black);
            font-weight: 600;
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

        /* Privacy Links Section */
        .wp-privacy-links {
            margin-bottom: 60px;
        }

        .wp-privacy-links-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }

        .wp-privacy-link-card {
            background: var(--wp-white);
            border-radius: var(--wp-border-radius);
            box-shadow: var(--wp-box-shadow);
            padding: 35px 30px;
            text-align: center;
            transition: var(--wp-transition);
            border: 1px solid var(--wp-gray-medium);
        }

        .wp-privacy-link-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            border-color: var(--wp-blue);
        }

        .wp-privacy-link-card h3 {
            color: var(--wp-blue);
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .wp-privacy-link-card p {
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

            .wp-privacy-content {
                padding: 30px 20px;
            }

            .wp-privacy-section-content h2 {
                font-size: 1.5rem;
            }

            .wp-privacy-section-content h3 {
                font-size: 1.2rem;
            }

            .wp-privacy-links-grid {
                grid-template-columns: 1fr;
            }

            .wp-privacy-link-card {
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
        }

        @media (max-width: 480px) {
            .wp-hero-title {
                font-size: 1.8rem;
            }

            .wp-privacy-content {
                padding: 20px 15px;
            }

            .wp-privacy-section-content h2 {
                font-size: 1.3rem;
            }

            .wp-privacy-section-content h3 {
                font-size: 1.1rem;
            }

            .wp-privacy-section-content p,
            .wp-privacy-section-content li {
                font-size: 14px;
            }

            .wp-privacy-link-card {
                padding: 20px 15px;
            }

            .wp-hero-section {
                min-height: 35vh;
            }
        }

        /* Print Styles */
        @media print {
            .wp-hero-section,
            .wp-privacy-links,
            .wp-consent-section {
                display: none;
            }

            .wp-privacy-content {
                box-shadow: none;
                padding: 0;
            }

            .wp-privacy-section-content {
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
                    <div class="wp-hero-slide active" style="background-image: url('https://images.unsplash.com/photo-1553877522-43269d4ea984?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80')"></div>
                </div>
                <div class="wp-hero-overlay"></div>
            </div>
            <div class="wp-hero-content">
                <div class="wp-container">
                    <div class="wp-hero-text">
                        <h1 class="wp-hero-title">Privacy Policy</h1>
                        <p class="wp-hero-subtitle">Your privacy is important to us. Learn how we protect and manage your personal information.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Main Content Area -->
        <div class="wp-main-content">
            <div class="wp-container">
                <div class="wp-content-area">
                    <!-- Privacy Policy Content -->
                    <section class="wp-privacy-section">
                        <div class="wp-privacy-content">
                            <!-- Last Updated -->
                            <div class="wp-privacy-header">
                                <p class="wp-last-updated">Last Updated: <?php echo date('F j, Y'); ?></p>
                            </div>

                            <!-- Introduction -->
                            <div class="wp-privacy-section-content">
                                <h2>1. Introduction</h2>
                                <p>Welcome to <?php echo $site_name; ?> ("we," "our," or "us"). We are committed to protecting your privacy and ensuring the security of your personal information. This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you use our travel booking services.</p>
                                <p>By accessing or using our website, mobile application, or services, you consent to the practices described in this Privacy Policy. If you do not agree with our policies and practices, please do not use our services.</p>
                            </div>

                            <!-- Information We Collect -->
                            <div class="wp-privacy-section-content">
                                <h2>2. Information We Collect</h2>
                                
                                <h3>2.1 Personal Information</h3>
                                <p>We may collect the following types of personal information:</p>
                                <ul>
                                    <li><strong>Contact Information:</strong> Name, email address, phone number, residential address</li>
                                    <li><strong>Identification Details:</strong> Passport number, government-issued ID, date of birth</li>
                                    <li><strong>Payment Information:</strong> Credit/debit card details, billing address, payment history</li>
                                    <li><strong>Travel Preferences:</strong> Seat preferences, meal requirements, special assistance needs</li>
                                    <li><strong>Booking Information:</strong> Flight details, hotel reservations, car rental information</li>
                                </ul>

                                <h3>2.2 Technical Information</h3>
                                <p>We automatically collect certain information when you visit our website:</p>
                                <ul>
                                    <li><strong>Device Information:</strong> IP address, browser type, operating system, device type</li>
                                    <li><strong>Usage Data:</strong> Pages visited, time spent on site, clickstream data, search queries</li>
                                    <li><strong>Location Data:</strong> General location information based on IP address</li>
                                    <li><strong>Cookies and Tracking:</strong> Cookies, web beacons, and similar technologies</li>
                                </ul>

                                <h3>2.3 Information from Third Parties</h3>
                                <p>We may receive information about you from:</p>
                                <ul>
                                    <li>Airlines, hotels, and other travel service providers</li>
                                    <li>Payment processors and financial institutions</li>
                                    <li>Social media platforms (when you connect your accounts)</li>
                                    <li>Business partners and affiliates</li>
                                </ul>
                            </div>

                            <!-- How We Use Your Information -->
                            <div class="wp-privacy-section-content">
                                <h2>3. How We Use Your Information</h2>
                                <p>We use your personal information for the following purposes:</p>
                                <ul>
                                    <li><strong>Service Provision:</strong> To process and manage your travel bookings, reservations, and payments</li>
                                    <li><strong>Customer Support:</strong> To provide customer service, respond to inquiries, and resolve issues</li>
                                    <li><strong>Communication:</strong> To send booking confirmations, travel updates, and important notifications</li>
                                    <li><strong>Personalization:</strong> To customize your experience and provide relevant travel recommendations</li>
                                    <li><strong>Marketing:</strong> To send promotional offers, travel deals, and newsletters (with your consent)</li>
                                    <li><strong>Security:</strong> To protect against fraud, unauthorized transactions, and security threats</li>
                                    <li><strong>Legal Compliance:</strong> To comply with legal obligations and regulatory requirements</li>
                                    <li><strong>Improvement:</strong> To analyze usage patterns and improve our services and website functionality</li>
                                </ul>
                            </div>

                            <!-- Information Sharing -->
                            <div class="wp-privacy-section-content">
                                <h2>4. Information Sharing and Disclosure</h2>
                                <p>We may share your information in the following circumstances:</p>

                                <h3>4.1 Service Providers</h3>
                                <p>We share information with third-party service providers who assist us in operating our business, including:</p>
                                <ul>
                                    <li>Airlines, hotels, car rental companies, and other travel suppliers</li>
                                    <li>Payment processors and financial institutions</li>
                                    <li>Customer support and communication services</li>
                                    <li>Analytics and marketing partners</li>
                                    <li>IT and infrastructure providers</li>
                                </ul>

                                <h3>4.2 Legal Requirements</h3>
                                <p>We may disclose your information when required by law or to:</p>
                                <ul>
                                    <li>Comply with legal processes, court orders, or government requests</li>
                                    <li>Protect our rights, property, or safety, and that of our users</li>
                                    <li>Investigate and prevent fraud, security breaches, or other illegal activities</li>
                                </ul>

                                <h3>4.3 Business Transfers</h3>
                                <p>In the event of a merger, acquisition, or sale of all or portion of our assets, your information may be transferred to the new entity.</p>

                                <h3>4.4 With Your Consent</h3>
                                <p>We may share your information with third parties when you explicitly consent to such sharing.</p>
                            </div>

                            <!-- Data Security -->
                            <div class="wp-privacy-section-content">
                                <h2>5. Data Security</h2>
                                <p>We implement appropriate technical and organizational security measures to protect your personal information, including:</p>
                                <ul>
                                    <li>SSL encryption for data transmission</li>
                                    <li>Secure servers and firewalls</li>
                                    <li>Regular security assessments and updates</li>
                                    <li>Access controls and authentication procedures</li>
                                    <li>Employee training on data protection</li>
                                </ul>
                                <p>While we strive to protect your personal information, no method of transmission over the Internet or electronic storage is 100% secure. We cannot guarantee absolute security but we work diligently to protect your data.</p>
                            </div>

                            <!-- Data Retention -->
                            <div class="wp-privacy-section-content">
                                <h2>6. Data Retention</h2>
                                <p>We retain your personal information only for as long as necessary to fulfill the purposes outlined in this Privacy Policy, unless a longer retention period is required or permitted by law. Our retention periods include:</p>
                                <ul>
                                    <li><strong>Booking Information:</strong> 7 years from the date of travel for tax and legal compliance</li>
                                    <li><strong>Customer Accounts:</strong> Until account deletion request or 3 years of inactivity</li>
                                    <li><strong>Marketing Data:</strong> Until consent withdrawal or 2 years of inactivity</li>
                                    <li><strong>Technical Data:</strong> Up to 2 years for analytics and improvement purposes</li>
                                </ul>
                            </div>

                            <!-- Your Rights -->
                            <div class="wp-privacy-section-content">
                                <h2>7. Your Rights and Choices</h2>
                                <p>Depending on your location, you may have the following rights regarding your personal information:</p>
                                <ul>
                                    <li><strong>Access:</strong> Request access to the personal information we hold about you</li>
                                    <li><strong>Correction:</strong> Request correction of inaccurate or incomplete information</li>
                                    <li><strong>Deletion:</strong> Request deletion of your personal information under certain circumstances</li>
                                    <li><strong>Objection:</strong> Object to processing of your personal information</li>
                                    <li><strong>Restriction:</strong> Request restriction of processing your personal information</li>
                                    <li><strong>Portability:</strong> Request transfer of your data to another organization</li>
                                    <li><strong>Withdraw Consent:</strong> Withdraw consent for marketing communications at any time</li>
                                </ul>
                                <p>To exercise these rights, please contact us using the information provided in the "Contact Us" section.</p>
                            </div>

                            <!-- Cookies and Tracking -->
                            <div class="wp-privacy-section-content">
                                <h2>8. Cookies and Tracking Technologies</h2>
                                <p>We use cookies and similar tracking technologies to enhance your experience on our website:</p>

                                <h3>8.1 Types of Cookies We Use</h3>
                                <ul>
                                    <li><strong>Essential Cookies:</strong> Required for basic website functionality and security</li>
                                    <li><strong>Performance Cookies:</strong> Help us understand how visitors interact with our website</li>
                                    <li><strong>Functional Cookies:</strong> Remember your preferences and settings</li>
                                    <li><strong>Marketing Cookies:</strong> Used to deliver relevant advertisements</li>
                                </ul>

                                <h3>8.2 Managing Cookies</h3>
                                <p>You can control cookie settings through your browser preferences. However, disabling certain cookies may affect the functionality of our website.</p>
                            </div>

                            <!-- International Transfers -->
                            <div class="wp-privacy-section-content">
                                <h2>9. International Data Transfers</h2>
                                <p>As a global travel service, your personal information may be transferred to and processed in countries outside of your residence, including countries that may have different data protection laws. We ensure appropriate safeguards are in place for such transfers, including:</p>
                                <ul>
                                    <li>Standard contractual clauses approved by relevant authorities</li>
                                    <li>Adequacy decisions where applicable</li>
                                    <li>Binding corporate rules for intra-group transfers</li>
                                </ul>
                            </div>

                            <!-- Children's Privacy -->
                            <div class="wp-privacy-section-content">
                                <h2>10. Children's Privacy</h2>
                                <p>Our services are not directed to individuals under the age of 16. We do not knowingly collect personal information from children under 16. If we become aware that we have collected personal information from a child under 16, we will take steps to delete such information promptly.</p>
                            </div>

                            <!-- Third-Party Links -->
                            <div class="wp-privacy-section-content">
                                <h2>11. Third-Party Links</h2>
                                <p>Our website may contain links to third-party websites, such as airlines, hotels, and travel partners. This Privacy Policy does not apply to those third-party websites. We encourage you to review the privacy policies of any third-party sites you visit.</p>
                            </div>

                            <!-- Changes to Policy -->
                            <div class="wp-privacy-section-content">
                                <h2>12. Changes to This Privacy Policy</h2>
                                <p>We may update this Privacy Policy from time to time to reflect changes in our practices or legal requirements. We will notify you of any material changes by posting the updated policy on our website with a new "Last Updated" date. We encourage you to review this Privacy Policy periodically.</p>
                            </div>

                            <!-- Contact Information -->
                            <div class="wp-privacy-section-content">
                                <h2>13. Contact Us</h2>
                                <p>If you have any questions, concerns, or requests regarding this Privacy Policy or our data practices, please contact us:</p>
                                <div class="wp-contact-info">
                                    <p><strong>Email:</strong> privacy@<?php echo strtolower(str_replace(' ', '', $site_name)); ?>.com</p>
                                    <p><strong>Phone:</strong> +234-1-700-0000</p>
                                    <p><strong>Address:</strong> 
                                        <?php echo $site_name; ?> Privacy Office<br>
                                        123 Travel Street, Victoria Island<br>
                                        Lagos, Nigeria
                                    </p>
                                </div>
                                <p>We will respond to your inquiry within 30 days.</p>
                            </div>

                            <!-- Consent Acknowledgement -->
                            <div class="wp-consent-section">
                                <p>By using our services, you acknowledge that you have read and understood this Privacy Policy and consent to the collection, use, and disclosure of your personal information as described herein.</p>
                            </div>
                        </div>
                    </section>

                    <!-- Quick Links -->
                    <section class="wp-privacy-links">
                        <div class="wp-privacy-links-grid">
                            <div class="wp-privacy-link-card">
                                <h3>Terms of Service</h3>
                                <p>Read our terms and conditions for using our travel booking services.</p>
                                <a href="terms.php" class="wp-btn wp-btn-outline">View Terms</a>
                            </div>
                            <div class="wp-privacy-link-card">
                                <h3>Cookie Policy</h3>
                                <p>Learn more about how we use cookies and tracking technologies.</p>
                                <a href="cookies.php" class="wp-btn wp-btn-outline">Cookie Settings</a>
                            </div>
                            <div class="wp-privacy-link-card">
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

            // Add copy to clipboard functionality for contact info
            const contactInfo = document.querySelector('.wp-contact-info');
            if (contactInfo) {
                contactInfo.style.cursor = 'pointer';
                contactInfo.title = 'Click to copy contact information';
                contactInfo.addEventListener('click', function() {
                    const textToCopy = this.innerText;
                    if (navigator.clipboard) {
                        navigator.clipboard.writeText(textToCopy).then(() => {
                            // Show temporary feedback
                            const originalBackground = this.style.background;
                            this.style.background = 'var(--wp-blue)';
                            this.style.color = 'var(--wp-white)';
                            
                            setTimeout(() => {
                                this.style.background = originalBackground;
                                this.style.color = '';
                            }, 1000);
                        });
                    }
                });
            }

            // Print functionality
            const printButton = document.createElement('button');
            printButton.textContent = 'Print Privacy Policy';
            printButton.className = 'wp-btn wp-btn-primary';
            printButton.style.marginBottom = '20px';
            printButton.addEventListener('click', function() {
                window.print();
            });

            const privacyHeader = document.querySelector('.wp-privacy-header');
            if (privacyHeader) {
                privacyHeader.parentNode.insertBefore(printButton, privacyHeader);
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

            // Observe privacy sections for animation
            const sections = document.querySelectorAll('.wp-privacy-section-content');
            sections.forEach(section => {
                section.style.opacity = '0';
                section.style.transform = 'translateY(20px)';
                section.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                observer.observe(section);
            });

            const linkCards = document.querySelectorAll('.wp-privacy-link-card');
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