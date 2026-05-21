<?php
// visa-assessment.php

// Start output buffering at the very beginning with error suppression
if (!headers_sent()) {
    ob_start();
}

require_once 'config.php';

$page_title = "Visa Readiness Assessment";
$error = '';
$success = '';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get assessment booking settings
$assessment_fee_low = 5000.00;
$assessment_fee_medium = 3000.00;
$assessment_fee_high = 0.00;
$assessment_link_low = '';
$assessment_link_medium = '';
$assessment_link_high = '';

try {
    $stmt = $pdo->prepare("SELECT 
        visa_assessment_booking_fee_low,
        visa_assessment_booking_fee_medium,
        visa_assessment_booking_fee_high,
        visa_assessment_booking_link_low,
        visa_assessment_booking_link_medium,
        visa_assessment_booking_link_high
        FROM site_settings ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        $assessment_fee_low = floatval($result['visa_assessment_booking_fee_low']);
        $assessment_fee_medium = floatval($result['visa_assessment_booking_fee_medium']);
        $assessment_fee_high = floatval($result['visa_assessment_booking_fee_high']);
        $assessment_link_low = $result['visa_assessment_booking_link_low'];
        $assessment_link_medium = $result['visa_assessment_booking_link_medium'];
        $assessment_link_high = $result['visa_assessment_booking_link_high'];
    }
} catch (Exception $e) {
    // Use defaults if error
}

// Process assessment booking
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['book_assessment_session'])) {
        try {
            $readiness_score = intval($_POST['readiness_score']);
            $readiness_grade = $_POST['readiness_grade'];
            $application_number = 'ASSESS' . date('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            // Determine booking fee and payment link based on grade
            $booking_fee = 0;
            $payment_link = '';
            
            switch ($readiness_grade) {
                case 'low':
                    $booking_fee = $assessment_fee_low;
                    $payment_link = $assessment_link_low;
                    break;
                case 'medium':
                    $booking_fee = $assessment_fee_medium;
                    $payment_link = $assessment_link_medium;
                    break;
                case 'high':
                    $booking_fee = $assessment_fee_high;
                    $payment_link = $assessment_link_high;
                    break;
            }
            
            // Validate required fields
            if (empty($_POST['first_name']) || empty($_POST['last_name']) || empty($_POST['email']) || empty($_POST['phone'])) {
                throw new Exception("Please fill in all required personal information.");
            }
            
            // Save booking to database - UPDATED to include payment_link
            $stmt = $pdo->prepare("
                INSERT INTO visa_assessment_bookings (
                    application_number, first_name, last_name, email, phone,
                    readiness_score, readiness_grade, booking_fee, currency, 
                    payment_link, assessment_data, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            
            $assessment_data = json_encode([
                'personal_info' => [
                    'first_name' => $_POST['first_name'],
                    'last_name' => $_POST['last_name'],
                    'email' => $_POST['email'],
                    'phone' => $_POST['phone']
                ],
                'readiness_answers' => json_decode($_POST['readiness_answers'], true)
            ]);
            
            $stmt->execute([
                $application_number,
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['email'],
                $_POST['phone'],
                $readiness_score,
                $readiness_grade,
                $booking_fee,
                'NGN',
                $payment_link, // ADDED payment link
                $assessment_data
            ]);
            
            $booking_id = $pdo->lastInsertId();
            
            // Send notifications
            sendAssessmentBookingNotifications($booking_id, $_POST['first_name'] . ' ' . $_POST['last_name'], $_POST['email'], $readiness_score, $readiness_grade, $booking_fee);
            createOnsiteNotification($booking_id, $_POST['first_name'] . ' ' . $_POST['last_name'], $readiness_score, $readiness_grade, $booking_fee);
            
            // FIXED: More robust redirect handling
            if (ob_get_level()) {
                ob_end_clean(); // Clear all output buffers
            }
            
            // Ensure no output has been sent
            if (!headers_sent()) {
                header("Location: assessment-payment.php?booking_id=" . $booking_id);
                exit;
            } else {
                // Fallback: JavaScript redirect if headers already sent
                echo '<script>window.location.href = "assessment-payment.php?booking_id=' . $booking_id . '";</script>';
                exit;
            }
            
        } catch (Exception $e) {
            $error = "Error booking assessment: " . $e->getMessage();
            // Log the detailed error for debugging
            error_log("Assessment Booking Error: " . $e->getMessage());
            error_log("POST Data: " . print_r($_POST, true));
        }
    }
    // NEW: Handle proceed to visa application
    elseif (isset($_POST['proceed_to_application'])) {
        try {
            $readiness_score = intval($_POST['readiness_score']);
            $readiness_grade = $_POST['readiness_grade'];
            $readiness_answers = $_POST['readiness_answers'];
            
            // Store assessment results in session
            $_SESSION['assessment_completed'] = true;
            $_SESSION['readiness_score'] = $readiness_score;
            $_SESSION['readiness_grade'] = $readiness_grade;
            $_SESSION['readiness_answers'] = $readiness_answers;
            
            // FIXED: More robust redirect handling
            if (ob_get_level()) {
                ob_end_clean(); // Clear all output buffers
            }
            
            // Ensure no output has been sent
            if (!headers_sent()) {
                header("Location: visa-application.php");
                exit;
            } else {
                // Fallback: JavaScript redirect if headers already sent
                echo '<script>window.location.href = "visa-application.php";</script>';
                exit;
            }
            
        } catch (Exception $e) {
            $error = "Error proceeding to application: " . $e->getMessage();
        }
    }
}

// Functions
function sendAssessmentBookingNotifications($booking_id, $customer_name, $customer_email, $score, $grade, $fee) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT admin_email, smtp_host, smtp_username, smtp_password FROM site_settings ORDER BY id DESC LIMIT 1");
        $stmt->execute();
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($settings) {
            $admin_email = $settings['admin_email'];
            
            // Customer email
            $customer_subject = "Visa Assessment Session Booking Confirmation";
            $customer_message = "
                Dear $customer_name,
                
                Thank you for booking a visa assessment session with Travel Centre!
                
                Booking Details:
                - Readiness Score: $score%
                - Assessment Grade: " . ucfirst($grade) . "
                - Session Fee: NGN " . number_format($fee, 2) . "
                
                Our visa specialist will contact you within 24 hours to schedule your session.
                
                Best regards,
                Travel Centre Team
            ";
            
            // Admin email
            $admin_subject = "New Visa Assessment Session Booking";
            $admin_message = "
                New visa assessment session booking received:
                
                Customer: $customer_name
                Email: $customer_email
                Readiness Score: $score%
                Assessment Grade: " . ucfirst($grade) . "
                Session Fee: NGN " . number_format($fee, 2) . "
                Booking ID: $booking_id
                
                Please follow up with the customer to schedule the session.
            ";
            
            // Send emails (you can implement actual email sending here)
            error_log("Assessment Booking Email - Customer: $customer_email, Admin: $admin_email");
            
        }
    } catch (Exception $e) {
        error_log("Error sending assessment booking notifications: " . $e->getMessage());
    }
}

function createOnsiteNotification($booking_id, $customer_name, $score, $grade, $fee) {
    global $pdo;
    
    try {
        $notification_message = "New assessment booking from $customer_name - Score: $score% ($grade) - Fee: NGN " . number_format($fee, 2);
        $notification_link = "admin/assessment-bookings.php?action=view&id=$booking_id";
        
        $stmt = $pdo->prepare("
            INSERT INTO notifications (title, message, type, link, is_read, created_at) 
            VALUES (?, ?, 'assessment_booking', ?, 0, NOW())
        ");
        
        $stmt->execute([
            "New Assessment Booking",
            $notification_message,
            $notification_link
        ]);
        
    } catch (Exception $e) {
        error_log("Error creating onsite notification: " . $e->getMessage());
    }
}

// Only load the HTML if we're not redirecting
require_once 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Travel Centre</title>
    <style>
        .assessment-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .readiness-question {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
            border-left: 4px solid #007bff;
        }
        
        .question-text {
            font-weight: 600;
            margin-bottom: 15px;
            color: #2c3e50;
        }
        
        .question-options {
            display: flex;
            gap: 15px;
        }
        
        .radio-label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            padding: 10px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            background: white;
        }
        
        .radio-label:hover {
            border-color: #007bff;
        }
        
        .results-section {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-top: 30px;
            display: none;
        }
        
        .personal-info-form {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-top: 20px;
            display: none;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .form-input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 16px;
        }
        
        .form-input:focus {
            border-color: #007bff;
            outline: none;
        }
        
        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0056b3;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #1e7e34;
        }
        
        .booking-offer {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin: 20px 0;
            text-align: center;
        }
        
        .score-display {
            text-align: center;
            margin: 20px 0;
        }
        
        .score-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: conic-gradient(#28a745 0% var(--score-percent, 0%), #e9ecef var(--score-percent, 0%) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 700;
            color: #2c3e50;
            border: 8px solid #fff;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            margin: 0 auto 20px;
            position: relative;
        }
        
        .score-circle::before {
            content: '';
            position: absolute;
            width: 100px;
            height: 100px;
            background: white;
            border-radius: 50%;
            z-index: 1;
        }
        
        .score-circle span {
            position: relative;
            z-index: 2;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-danger {
            background: #fee;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .alert-success {
            background: #eff;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .grade-high { 
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            display: inline-block;
            margin-bottom: 15px;
        }
        .grade-medium { 
            background: linear-gradient(135deg, #f39c12, #f1c40f);
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            display: inline-block;
            margin-bottom: 15px;
        }
        .grade-low { 
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            display: inline-block;
            margin-bottom: 15px;
        }
        
        .loading {
            display: none;
            text-align: center;
            margin: 20px 0;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #007bff;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 2s linear infinite;
            margin: 0 auto;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* NEW: Hidden form for proceeding to application */
        .hidden-form {
            display: none;
        }
    </style>
</head>
<body>
    <div class="assessment-container">
        <h1>Visa Readiness Assessment</h1>
        <p>Complete this assessment to evaluate your visa application readiness and get personalized recommendations.</p>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <strong>Error:</strong> <?php echo $error; ?>
                <p>If this problem persists, please contact our support team.</p>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div id="assessmentQuestions">
            <h3>Assessment Questions</h3>
            <p>Please answer all questions honestly to get an accurate assessment.</p>
            
            <div class="readiness-question">
                <div class="question-text">1. Are you applying from your country of legal residence?</div>
                <div class="question-options">
                    <label class="radio-label">
                        <input type="radio" name="readiness_1" value="yes" required> Yes
                    </label>
                    <label class="radio-label">
                        <input type="radio" name="readiness_1" value="no" required> No
                    </label>
                </div>
            </div>
            
            <div class="readiness-question">
                <div class="question-text">2. Is your passport valid for at least 6 months beyond your planned return date?</div>
                <div class="question-options">
                    <label class="radio-label">
                        <input type="radio" name="readiness_2" value="yes" required> Yes
                    </label>
                    <label class="radio-label">
                        <input type="radio" name="readiness_2" value="no" required> No
                    </label>
                </div>
            </div>
            
            <div class="readiness-question">
                <div class="question-text">3. Do you have at least two blank visa pages in your passport?</div>
                <div class="question-options">
                    <label class="radio-label">
                        <input type="radio" name="readiness_3" value="yes" required> Yes
                    </label>
                    <label class="radio-label">
                        <input type="radio" name="readiness_3" value="no" required> No
                    </label>
                </div>
            </div>
            
            <div class="readiness-question">
                <div class="question-text">4. Does the name on your passport match the name you'll use on all bookings and forms?</div>
                <div class="question-options">
                    <label class="radio-label">
                        <input type="radio" name="readiness_4" value="yes" required> Yes
                    </label>
                    <label class="radio-label">
                        <input type="radio" name="readiness_4" value="no" required> No
                    </label>
                </div>
            </div>
            
            <div class="readiness-question">
                <div class="question-text">5. Do you have a clear travel purpose (tourism/business/study/visit) you can state in one sentence?</div>
                <div class="question-options">
                    <label class="radio-label">
                        <input type="radio" name="readiness_5" value="yes" required> Yes
                    </label>
                    <label class="radio-label">
                        <input type="radio" name="readiness_5" value="no" required> No
                    </label>
                </div>
            </div>
            
            <div class="readiness-question">
                <div class="question-text">6. Do you have specific travel dates (arrival and return)?</div>
                <div class="question-options">
                    <label class="radio-label">
                        <input type="radio" name="readiness_6" value="yes" required> Yes
                    </label>
                    <label class="radio-label">
                        <input type="radio" name="readiness_6" value="no" required> No
                    </label>
                </div>
            </div>
            
            <div class="readiness-question">
                <div class="question-text">7. Do your planned dates fit within the typical stay limit for your destination?</div>
                <div class="question-options">
                    <label class="radio-label">
                        <input type="radio" name="readiness_7" value="yes" required> Yes
                    </label>
                    <label class="radio-label">
                        <input type="radio" name="readiness_7" value="no" required> No
                    </label>
                </div>
            </div>
            
            <div class="readiness-question">
                <div class="question-text">8. Do you have a draft itinerary (cities, activities, meetings) you can submit if asked?</div>
                <div class="question-options">
                    <label class="radio-label">
                        <input type="radio" name="readiness_8" value="yes" required> Yes
                    </label>
                    <label class="radio-label">
                        <input type="radio" name="readiness_8" value="no" required> No
                    </label>
                </div>
            </div>
            
            <div class="readiness-question">
                <div class="question-text">9. Do you have a flight reservation (ticket or hold) in your name?</div>
                <div class="question-options">
                    <label class="radio-label">
                        <input type="radio" name="readiness_9" value="yes" required> Yes
                    </label>
                    <label class="radio-label">
                        <input type="radio" name="readiness_9" value="no" required> No
                    </label>
                </div>
            </div>
            
            <div class="readiness-question">
                <div class="question-text">10. Do you have accommodation arranged (hotel booking or invitation letter with address)?</div>
                <div class="question-options">
                    <label class="radio-label">
                        <input type="radio" name="readiness_10" value="yes" required> Yes
                    </label>
                    <label class="radio-label">
                        <input type="radio" name="readiness_10" value="no" required> No
                    </label>
                </div>
            </div>
            
            <button type="button" id="calculateScore" class="btn btn-primary">Calculate My Score</button>
        </div>
        
        <div id="assessmentResults" class="results-section">
            <h3>Your Assessment Results</h3>
            <div class="score-display">
                <div class="score-circle">
                    <span id="scoreValue">0</span>%
                </div>
                <div id="scoreGrade" class="grade-message"></div>
                <p id="scoreMessage" class="grade-description"></p>
            </div>
            
            <div id="bookingOffer" style="display: none;">
                <div class="booking-offer">
                    <h4>🚀 Boost Your Visa Chances!</h4>
                    <p>Our visa specialists will help you improve your application</p>
                    <div class="booking-price" id="bookingPrice">NGN 5,000</div>
                    <button type="button" id="showPersonalInfo" class="btn btn-success">Book Specialist Session</button>
                </div>
            </div>
            
            <div id="proceedOption" style="display: none;">
                <div class="alert alert-success">
                    <h4>🎉 Great! You're ready to apply</h4>
                    <p>Your assessment shows you have a strong application. You can proceed with the visa application.</p>
                    <button type="button" id="proceedToApplication" class="btn btn-success">Proceed to Visa Application</button>
                </div>
            </div>
        </div>
        
        <form id="personalInfoForm" class="personal-info-form" method="POST" action="">
            <h3>Personal Information</h3>
            <p>Please provide your details to book the assessment session.</p>
            
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">First Name *</label>
                    <input type="text" name="first_name" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Last Name *</label>
                    <input type="text" name="last_name" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Email *</label>
                    <input type="email" name="email" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Phone *</label>
                    <input type="tel" name="phone" class="form-input" required>
                </div>
            </div>
            
            <input type="hidden" name="readiness_score" id="hiddenScore">
            <input type="hidden" name="readiness_grade" id="hiddenGrade">
            <input type="hidden" name="readiness_answers" id="hiddenAnswers">
            <input type="hidden" name="book_assessment_session" value="1">
            
            <div class="loading" id="formLoading">
                <div class="spinner"></div>
                <p>Processing your booking...</p>
            </div>
            
            <button type="submit" id="submitButton" class="btn btn-primary">Save & Continue Booking</button>
        </form>

        <!-- NEW: Hidden form for proceeding to visa application -->
        <form id="proceedToApplicationForm" class="hidden-form" method="POST" action="">
            <input type="hidden" name="readiness_score" id="proceedScore">
            <input type="hidden" name="readiness_grade" id="proceedGrade">
            <input type="hidden" name="readiness_answers" id="proceedAnswers">
            <input type="hidden" name="proceed_to_application" value="1">
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const calculateBtn = document.getElementById('calculateScore');
            const resultsSection = document.getElementById('assessmentResults');
            const personalInfoForm = document.getElementById('personalInfoForm');
            const showPersonalInfoBtn = document.getElementById('showPersonalInfo');
            const bookingOffer = document.getElementById('bookingOffer');
            const proceedOption = document.getElementById('proceedOption');
            const proceedToApplicationBtn = document.getElementById('proceedToApplication');
            const proceedToApplicationForm = document.getElementById('proceedToApplicationForm');
            const formLoading = document.getElementById('formLoading');
            const submitButton = document.getElementById('submitButton');
            
            calculateBtn.addEventListener('click', function() {
                // Calculate score
                let score = calculateReadinessScore();
                let grade = getReadinessGrade(score);
                
                // Display results
                document.getElementById('scoreValue').textContent = score;
                document.getElementById('hiddenScore').value = score;
                document.getElementById('hiddenGrade').value = grade;
                
                const scoreCircle = document.querySelector('.score-circle');
                scoreCircle.style.setProperty('--score-percent', score + '%');
                
                // Show appropriate options based on score
                if (grade === 'high') {
                    document.getElementById('scoreGrade').textContent = 'High Chance';
                    document.getElementById('scoreGrade').className = 'grade-high';
                    document.getElementById('scoreMessage').textContent = 'Excellent! Your application appears strong.';
                    proceedOption.style.display = 'block';
                    bookingOffer.style.display = 'none';
                } else {
                    document.getElementById('scoreGrade').textContent = grade.charAt(0).toUpperCase() + grade.slice(1) + ' Chance';
                    document.getElementById('scoreGrade').className = 'grade-' + grade;
                    document.getElementById('scoreMessage').textContent = getGradeMessage(grade);
                    proceedOption.style.display = 'none';
                    bookingOffer.style.display = 'block';
                    
                    // Set booking price
                    const price = getBookingPrice(grade);
                    document.getElementById('bookingPrice').textContent = 'NGN ' + price.toLocaleString();
                }
                
                resultsSection.style.display = 'block';
                document.getElementById('assessmentQuestions').scrollIntoView({ behavior: 'smooth' });
            });
            
            showPersonalInfoBtn.addEventListener('click', function() {
                personalInfoForm.style.display = 'block';
                personalInfoForm.scrollIntoView({ behavior: 'smooth' });
            });
            
            // Handle proceed to visa application
            proceedToApplicationBtn.addEventListener('click', function() {
                // Get current assessment data
                const score = document.getElementById('hiddenScore').value;
                const grade = document.getElementById('hiddenGrade').value;
                const answers = getAllAnswers();
                
                // Set values in the hidden form
                document.getElementById('proceedScore').value = score;
                document.getElementById('proceedGrade').value = grade;
                document.getElementById('proceedAnswers').value = JSON.stringify(answers);
                
                // Submit the form
                proceedToApplicationForm.submit();
            });
            
            // Form submission handling
            personalInfoForm.addEventListener('submit', function(e) {
                // Show loading state
                formLoading.style.display = 'block';
                submitButton.disabled = true;
                submitButton.textContent = 'Processing...';
                
                // Store answers in hidden field before submission
                const answers = getAllAnswers();
                document.getElementById('hiddenAnswers').value = JSON.stringify(answers);
                
                // Form will submit normally and redirect via PHP header redirect
            });
            
            function calculateReadinessScore() {
                let score = 0;
                const totalQuestions = 10;
                const pointsPerQuestion = 100 / totalQuestions;
                
                for (let i = 1; i <= totalQuestions; i++) {
                    const selected = document.querySelector(`input[name="readiness_${i}"]:checked`);
                    if (selected && selected.value === 'yes') {
                        score += pointsPerQuestion;
                    }
                }
                
                return Math.round(score);
            }
            
            function getAllAnswers() {
                const answers = {};
                for (let i = 1; i <= 10; i++) {
                    const selected = document.querySelector(`input[name="readiness_${i}"]:checked`);
                    answers[i] = {
                        answer: selected ? selected.value : 'no',
                        question: document.querySelector(`input[name="readiness_${i}"]`).closest('.readiness-question').querySelector('.question-text').textContent.replace(/^\d+\.\s/, '')
                    };
                }
                return answers;
            }
            
            function getReadinessGrade(score) {
                if (score >= 80) return 'high';
                if (score >= 50) return 'medium';
                return 'low';
            }
            
            function getGradeMessage(grade) {
                const messages = {
                    'low': 'We strongly recommend booking a session with our visa specialist to significantly improve your application before submission.',
                    'medium': 'Good preparation, but there\'s room for improvement. We recommend booking a session with our specialist to boost your chances.',
                    'high': 'Excellent! Your application appears strong.'
                };
                return messages[grade] || '';
            }
            
            function getBookingPrice(grade) {
                const prices = {
                    'low': <?php echo $assessment_fee_low; ?>,
                    'medium': <?php echo $assessment_fee_medium; ?>,
                    'high': <?php echo $assessment_fee_high; ?>
                };
                return prices[grade] || 0;
            }
        });
    </script>
</body>
</html>

<?php
require_once 'includes/footer.php';
?>