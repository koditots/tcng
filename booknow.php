<?php
// booknow.php
require_once 'config.php';
require_once 'includes/functions.php';

$page_title = "Book Flight";
$error = '';
$success = '';
$booking_details = null;
$payment_success = false;
$payment_error = '';

// Check if user is logged in
if (!isLoggedIn()) {
    // Store flight data in session for after login
    $_SESSION['pending_booking'] = $_POST;
    
    // Redirect to login page
    header("Location: login.php?redirect=booknow");
    exit;
}

// Process flight booking
if ($_POST && !isset($_POST['process_payment'])) {
    try {
        // Validate required fields
        if (!isset($_POST['flight_data']) || empty($_POST['flight_data'])) {
            throw new Exception("Flight data is missing");
        }
        
        $flight_data = json_decode($_POST['flight_data'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid flight data format");
        }
        
        $passengers = intval($_POST['passengers'] ?? 1);
        $travel_class = sanitize($_POST['travel_class'] ?? 'ECONOMY');
        $conversion_rate = floatval($_POST['conversion_rate'] ?? 450);
        
        // Get additional flight details
        $origin = sanitize($_POST['origin'] ?? '');
        $destination = sanitize($_POST['destination'] ?? '');
        $departure_date = sanitize($_POST['departure_date'] ?? '');
        $return_date = sanitize($_POST['return_date'] ?? '');
        $trip_type = sanitize($_POST['trip_type'] ?? 'one_way');
        
        // Get flight times
        $departure_time = sanitize($_POST['departure_time'] ?? '');
        $arrival_time = sanitize($_POST['arrival_time'] ?? '');
        $return_departure_time = sanitize($_POST['return_departure_time'] ?? '');
        $return_arrival_time = sanitize($_POST['return_arrival_time'] ?? '');
        
        // Calculate price
        $base_price = floatval($flight_data['price']['grandTotal'] ?? 0);
        if ($base_price <= 0) {
            throw new Exception("Invalid flight price");
        }
        
        $converted_price = $base_price * $conversion_rate;
        $final_price = round($converted_price, 2);
        
        // Generate booking reference
        $booking_reference = generateBookingReference();
        
        // Prepare flight data for storage
        $flight_data_json = json_encode($flight_data);
        
        // Get user ID from session
        $user_id = $_SESSION['user_id'];
        
        // Get airline information
        $itinerary = $flight_data['itineraries'][0] ?? [];
        $first_segment = $itinerary['segments'][0] ?? [];
        $airline_code = $first_segment['carrierCode'] ?? 'Unknown';
        $airline_name = getAirlineNameFromAmadeus($airline_code);
        
        // Insert booking into database
        $stmt = $pdo->prepare("INSERT INTO flight_bookings (user_id, booking_reference, flight_data, passengers, travel_class, total_amount, currency, status, payment_status, origin, destination, departure_date, return_date, trip_type, airline_code, airline_name, departure_time, arrival_time, return_departure_time, return_arrival_time) VALUES (?, ?, ?, ?, ?, ?, 'NGN', 'pending', 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $user_id, 
            $booking_reference, 
            $flight_data_json, 
            $passengers, 
            $travel_class, 
            $final_price,
            $origin,
            $destination,
            $departure_date,
            $return_date,
            $trip_type,
            $airline_code,
            $airline_name,
            $departure_time,
            $arrival_time,
            $return_departure_time,
            $return_arrival_time
        ]);
        
        $current_booking_id = $pdo->lastInsertId();
        
        // Store booking ID in session for payment processing
        $_SESSION['current_booking_id'] = $current_booking_id;
        
        // Get booking details for display
        $stmt = $pdo->prepare("SELECT * FROM flight_bookings WHERE id = ?");
        $stmt->execute([$current_booking_id]);
        $booking_details = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $error = "Booking failed: " . $e->getMessage();
        error_log("Booking error: " . $e->getMessage());
    }
}

// Process payment
if (isset($_POST['process_payment'])) {
    try {
        $booking_id = $_SESSION['current_booking_id'] ?? ($_POST['booking_id'] ?? 0);
        $payment_method = sanitize($_POST['payment_method'] ?? 'card');
        
        if (empty($booking_id)) {
            throw new Exception("Booking ID not found");
        }
        
        // Get booking details
        $stmt = $pdo->prepare("SELECT * FROM flight_bookings WHERE id = ?");
        $stmt->execute([$booking_id]);
        $booking_details = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$booking_details) {
            throw new Exception("Booking not found");
        }
        
        // Simulate payment processing
        // In a real application, you would integrate with a payment gateway here
        
        // Update booking status to confirmed and payment to paid
        $stmt = $pdo->prepare("UPDATE flight_bookings SET status = 'confirmed', payment_status = 'paid', payment_method = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$payment_method, $booking_id]);
        
        $payment_success = true;
        
        // Clear session
        unset($_SESSION['current_booking_id']);
        
    } catch (Exception $e) {
        $payment_error = "Payment failed: " . $e->getMessage();
        error_log("Payment error: " . $e->getMessage());
    }
}

require_once 'includes/header.php';
?>

<div class="booknow-page-wrapper">
    <div class="container">
        <!-- Success and Error Messages -->
        <?php if ($error): ?>
            <div class="auth-message auth-error">
                <div class="auth-icon">⚠️</div>
                <div class="auth-text"><?php echo $error; ?></div>
            </div>
        <?php endif; ?>

        <?php if ($payment_success): ?>
            <div class="auth-message auth-success">
                <div class="auth-icon">✅</div>
                <div class="auth-text">
                    <strong>Payment Successful!</strong> Your flight booking has been confirmed. 
                    <?php if ($booking_details): ?>
                    Booking Reference: <?php echo $booking_details['booking_reference']; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($payment_error): ?>
            <div class="auth-message auth-error">
                <div class="auth-icon">⚠️</div>
                <div class="auth-text"><?php echo $payment_error; ?></div>
            </div>
        <?php endif; ?>

        <div class="booknow-layout">
            <!-- Booking Summary -->
            <div class="booking-summary-section">
                <h2 class="section-title">Booking Summary</h2>
                
                <?php if ($booking_details): ?>
                    <?php
                    $flight_data = json_decode($booking_details['flight_data'], true);
                    $itinerary = $flight_data['itineraries'][0] ?? [];
                    $first_segment = $itinerary['segments'][0] ?? [];
                    $last_segment = end($itinerary['segments']) ?? [];
                    ?>
                    
                    <div class="booking-summary-card">
                        <div class="summary-header">
                            <h3><?php echo $booking_details['origin']; ?> → <?php echo $booking_details['destination']; ?></h3>
                            <span class="booking-ref">Ref: <?php echo $booking_details['booking_reference']; ?></span>
                        </div>
                        
                        <div class="flight-details-summary">
                            <div class="airline-info">
                                <img src="<?php echo getAirlineLogoFromAmadeus($booking_details['airline_code']); ?>" 
                                     alt="<?php echo $booking_details['airline_name']; ?>" 
                                     class="airline-logo">
                                <span class="airline-name"><?php echo $booking_details['airline_name']; ?></span>
                            </div>
                            
                            <div class="flight-timeline-summary">
                                <div class="time-point">
                                    <span class="time"><?php echo $booking_details['departure_time']; ?></span>
                                    <span class="airport"><?php echo $booking_details['origin']; ?></span>
                                </div>
                                
                                <div class="flight-duration">
                                    <span class="duration">
                                        <?php 
                                        if (!empty($itinerary['duration'])) {
                                            echo formatFlightDuration($itinerary['duration']);
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </span>
                                </div>
                                
                                <div class="time-point">
                                    <span class="time"><?php echo $booking_details['arrival_time']; ?></span>
                                    <span class="airport"><?php echo $booking_details['destination']; ?></span>
                                </div>
                            </div>
                            
                            <?php if ($booking_details['trip_type'] === 'round_trip' && $booking_details['return_departure_time']): ?>
                                <div class="return-flight-summary">
                                    <h4>Return Flight</h4>
                                    <div class="flight-timeline-summary">
                                        <div class="time-point">
                                            <span class="time"><?php echo $booking_details['return_departure_time']; ?></span>
                                            <span class="airport"><?php echo $booking_details['destination']; ?></span>
                                        </div>
                                        
                                        <div class="flight-duration">
                                            <span class="duration">
                                                <?php 
                                                if (!empty($itinerary['duration'])) {
                                                    echo formatFlightDuration($itinerary['duration']);
                                                } else {
                                                    echo 'N/A';
                                                }
                                                ?>
                                            </span>
                                        </div>
                                        
                                        <div class="time-point">
                                            <span class="time"><?php echo $booking_details['return_arrival_time']; ?></span>
                                            <span class="airport"><?php echo $booking_details['origin']; ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="booking-details">
                            <div class="detail-row">
                                <span class="label">Passengers:</span>
                                <span class="value"><?php echo $booking_details['passengers']; ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Travel Class:</span>
                                <span class="value"><?php echo getTravelClassName($booking_details['travel_class']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Departure Date:</span>
                                <span class="value"><?php echo date('M j, Y', strtotime($booking_details['departure_date'])); ?></span>
                            </div>
                            <?php if ($booking_details['return_date']): ?>
                                <div class="detail-row">
                                    <span class="label">Return Date:</span>
                                    <span class="value"><?php echo date('M j, Y', strtotime($booking_details['return_date'])); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="price-summary">
                            <div class="total-price">
                                <span class="label">Total Amount:</span>
                                <span class="amount">₦<?php echo number_format($booking_details['total_amount'], 2); ?></span>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="no-booking-message">
                        <p>No booking details found. Please complete the flight booking process.</p>
                        <a href="flights.php" class="btn-primary">Search Flights</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Payment Section -->
            <div class="payment-section">
                <h2 class="section-title">Complete Your Booking</h2>
                
                <?php if (!$payment_success && $booking_details): ?>
                    <form id="paymentForm" method="POST" class="payment-form-modern">
                        <input type="hidden" name="process_payment" value="1">
                        <input type="hidden" name="booking_id" value="<?php echo $booking_details['id']; ?>">
                        
                        <div class="form-section">
                            <h4 class="section-title">Payment Method</h4>
                            <div class="payment-methods-modern">
                                <label class="payment-method-option">
                                    <input type="radio" name="payment_method" value="card" checked>
                                    <span class="checkmark"></span>
                                    <span class="method-icon">💳</span>
                                    <span class="method-label">Credit/Debit Card</span>
                                </label>
                                
                                <label class="payment-method-option">
                                    <input type="radio" name="payment_method" value="transfer">
                                    <span class="checkmark"></span>
                                    <span class="method-icon">🏦</span>
                                    <span class="method-label">Bank Transfer</span>
                                </label>
                                
                                <label class="payment-method-option">
                                    <input type="radio" name="payment_method" value="wallet">
                                    <span class="checkmark"></span>
                                    <span class="method-icon">📱</span>
                                    <span class="method-label">Digital Wallet</span>
                                </label>
                            </div>
                        </div>
                        
                        <div id="cardPaymentSection" class="payment-details-section">
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="input-label">Card Number</label>
                                    <input type="text" name="card_number" class="modern-input" placeholder="1234 5678 9012 3456" maxlength="19" required>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="input-label">Expiry Date</label>
                                    <input type="text" name="expiry_date" class="modern-input" placeholder="MM/YY" maxlength="5" required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="input-label">CVV</label>
                                    <input type="text" name="cvv" class="modern-input" placeholder="123" maxlength="3" required>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="input-label">Cardholder Name</label>
                                    <input type="text" name="cardholder_name" class="modern-input" placeholder="John Doe" required>
                                </div>
                            </div>
                        </div>
                        
                        <div id="transferPaymentSection" class="payment-details-section" style="display: none;">
                            <div class="bank-transfer-info">
                                <p>Please transfer the amount to our bank account:</p>
                                <div class="bank-details">
                                    <p><strong>Bank:</strong> Travel Centre Bank</p>
                                    <p><strong>Account Number:</strong> 1234567890</p>
                                    <p><strong>Account Name:</strong> Travel Centre NG</p>
                                    <p><strong>Amount:</strong> ₦<?php echo number_format($booking_details['total_amount'], 2); ?></p>
                                </div>
                                <p class="note">After transferring, please send proof of payment to support@travelcentre.ng</p>
                            </div>
                        </div>
                        
                        <div id="walletPaymentSection" class="payment-details-section" style="display: none;">
                            <div class="wallet-info">
                                <p>Select your digital wallet:</p>
                                <div class="wallet-options">
                                    <button type="button" class="wallet-option">PayPal</button>
                                    <button type="button" class="wallet-option">Apple Pay</button>
                                    <button type="button" class="wallet-option">Google Pay</button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn-pay-now">
                                Pay ₦<?php echo number_format($booking_details['total_amount'], 2); ?>
                            </button>
                        </div>
                    </form>
                <?php elseif ($payment_success): ?>
                    <div class="success-section">
                        <div class="success-icon">✅</div>
                        <h3>Booking Confirmed!</h3>
                        <p>Your flight has been successfully booked and payment has been processed.</p>
                        <div class="success-actions">
                            <a href="my-bookings.php" class="btn-primary">View My Bookings</a>
                            <a href="flights.php" class="btn-secondary">Book Another Flight</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="error-section">
                        <div class="error-icon">⚠️</div>
                        <h3>No Booking Found</h3>
                        <p>Please go back to flights and select a flight to book.</p>
                        <a href="flights.php" class="btn-primary">Search Flights</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    // Payment method toggle
    document.addEventListener('DOMContentLoaded', function() {
        const paymentMethods = document.querySelectorAll('input[name="payment_method"]');
        
        paymentMethods.forEach(method => {
            method.addEventListener('change', function() {
                const method = this.value;
                
                // Hide all sections first
                document.getElementById('cardPaymentSection').style.display = 'none';
                document.getElementById('transferPaymentSection').style.display = 'none';
                document.getElementById('walletPaymentSection').style.display = 'none';
                
                // Show selected section
                if (method === 'card') {
                    document.getElementById('cardPaymentSection').style.display = 'block';
                } else if (method === 'transfer') {
                    document.getElementById('transferPaymentSection').style.display = 'block';
                } else if (method === 'wallet') {
                    document.getElementById('walletPaymentSection').style.display = 'block';
                }
            });
        });
        
        // Initialize with card payment visible
        document.getElementById('cardPaymentSection').style.display = 'block';
    });
</script>

<style>
.booknow-page-wrapper {
    padding: 2rem 0;
    background: #f8fafc;
    min-height: 100vh;
}

.booknow-layout {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    max-width: 1200px;
    margin: 0 auto;
}

.section-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 1.5rem;
}

.booking-summary-card {
    background: white;
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid #f1f5f9;
}

.summary-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #e5e7eb;
}

.summary-header h3 {
    font-size: 1.25rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
}

.booking-ref {
    font-size: 0.875rem;
    color: #6b7280;
    font-weight: 500;
}

.airline-info {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.airline-logo {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    object-fit: contain;
}

.airline-name {
    font-weight: 600;
    color: #1e293b;
}

.flight-timeline-summary {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.5rem;
}

.time-point {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    flex: 1;
}

.time-point .time {
    font-size: 1.125rem;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 0.25rem;
}

.time-point .airport {
    font-size: 0.875rem;
    color: #6b7280;
}

.flight-duration {
    flex: 2;
    text-align: center;
}

.flight-duration .duration {
    font-size: 0.875rem;
    color: #6b7280;
    font-weight: 500;
}

.return-flight-summary {
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid #e5e7eb;
}

.return-flight-summary h4 {
    font-size: 1rem;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 1rem;
}

.booking-details {
    margin: 1.5rem 0;
    padding: 1.5rem 0;
    border-top: 1px solid #e5e7eb;
    border-bottom: 1px solid #e5e7eb;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
}

.detail-row:last-child {
    margin-bottom: 0;
}

.detail-row .label {
    color: #6b7280;
    font-size: 0.875rem;
}

.detail-row .value {
    color: #1e293b;
    font-weight: 500;
    font-size: 0.875rem;
}

.price-summary {
    margin-top: 1.5rem;
}

.total-price {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    background: #f8fafc;
    border-radius: 8px;
}

.total-price .label {
    font-size: 1.125rem;
    font-weight: 600;
    color: #1e293b;
}

.total-price .amount {
    font-size: 1.5rem;
    font-weight: 700;
    color: #3b82f6;
}

.payment-section {
    background: white;
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid #f1f5f9;
    height: fit-content;
}

.payment-form-modern {
    width: 100%;
}

.payment-methods-modern {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
}

.payment-method-option {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.payment-method-option:hover {
    border-color: #3b82f6;
    background: #f8fafc;
}

.payment-method-option input:checked + .checkmark {
    background: #3b82f6;
    border-color: #3b82f6;
}

.payment-method-option input:checked + .checkmark::after {
    content: '✓';
    color: white;
    font-size: 12px;
    font-weight: bold;
}

.method-icon {
    font-size: 1.25rem;
}

.method-label {
    flex: 1;
    font-weight: 500;
    color: #1e293b;
}

.payment-details-section {
    margin-bottom: 1.5rem;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1rem;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.input-label {
    display: block;
    font-weight: 600;
    color: #374151;
    margin-bottom: 0.5rem;
    font-size: 0.875rem;
}

.modern-input {
    width: 100%;
    padding: 1rem;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 1rem;
    transition: all 0.3s ease;
    background: white;
}

.modern-input:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.bank-transfer-info, .wallet-info {
    background: #f8f9fa;
    padding: 1.5rem;
    border-radius: 8px;
    text-align: center;
}

.bank-details {
    background: white;
    padding: 1rem;
    border-radius: 6px;
    margin: 1rem 0;
    text-align: left;
}

.bank-details p {
    margin: 0.5rem 0;
    font-size: 0.875rem;
}

.note {
    font-size: 0.75rem;
    color: #6b7280;
    font-style: italic;
}

.wallet-options {
    display: flex;
    gap: 1rem;
    justify-content: center;
    margin-top: 1rem;
}

.wallet-option {
    background: white;
    border: 2px solid #e5e7eb;
    padding: 1rem 1.5rem;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 500;
}

.wallet-option:hover {
    border-color: #3b82f6;
    background: #f8fafc;
}

.form-actions {
    margin-top: 2rem;
}

.btn-pay-now {
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    color: white;
    border: none;
    padding: 1rem 2rem;
    border-radius: 8px;
    font-size: 1.125rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    width: 100%;
    box-shadow: 0 4px 15px rgba(59, 130, 246, 0.4);
}

.btn-pay-now:hover {
    background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(59, 130, 246, 0.6);
}

.success-section, .error-section {
    text-align: center;
    padding: 2rem;
}

.success-icon, .error-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
}

.success-section h3, .error-section h3 {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 1rem;
    color: #1e293b;
}

.success-section p, .error-section p {
    color: #6b7280;
    margin-bottom: 2rem;
}

.success-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
}

.btn-primary, .btn-secondary {
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s ease;
    display: inline-block;
}

.btn-primary {
    background: #3b82f6;
    color: white;
    border: none;
}

.btn-primary:hover {
    background: #2563eb;
    transform: translateY(-1px);
}

.btn-secondary {
    background: #6b7280;
    color: white;
    border: none;
}

.btn-secondary:hover {
    background: #4b5563;
    transform: translateY(-1px);
}

.no-booking-message {
    text-align: center;
    padding: 2rem;
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

/* Auth Messages */
.auth-message {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 2rem;
    max-width: 1200px;
    margin-left: auto;
    margin-right: auto;
}

.auth-error {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #dc2626;
}

.auth-success {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    color: #16a34a;
}

.auth-icon {
    font-size: 1.5rem;
}

/* Responsive Design */
@media (max-width: 768px) {
    .booknow-layout {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
    
    .container {
        padding: 0 1rem;
    }
    
    .booking-summary-card,
    .payment-section {
        padding: 1.5rem;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .success-actions {
        flex-direction: column;
    }
    
    .flight-timeline-summary {
        flex-direction: column;
        gap: 1rem;
    }
    
    .time-point {
        flex-direction: row;
        justify-content: space-between;
        width: 100%;
        text-align: left;
    }
}

@media (max-width: 480px) {
    .booknow-page-wrapper {
        padding: 1rem 0;
    }
    
    .section-title {
        font-size: 1.25rem;
    }
    
    .summary-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .total-price {
        flex-direction: column;
        gap: 0.5rem;
        text-align: center;
    }
    
    .payment-method-option {
        padding: 0.75rem;
    }
}
</style>

<?php
require_once 'includes/footer.php';
?>