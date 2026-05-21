<?php
// track-ticket.php
require_once 'config.php';
require_once 'includes/functions.php';

$page_title = "Track Flight Ticket";

// Handle form submission
$tracking_id = '';
$booking_reference = '';
$booking_data = null;
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tracking_id = sanitize($_POST['tracking_id'] ?? '');
    $booking_reference = sanitize($_POST['booking_reference'] ?? '');
    
    if (empty($tracking_id) && empty($booking_reference)) {
        $error = "Please enter either a Tracking ID or Booking Reference.";
    } else {
        try {
            // Build query based on provided input - FIXED LOGIC
            $sql = "SELECT * FROM flight_bookings WHERE ";
            $params = [];
            
            if (!empty($tracking_id) && !empty($booking_reference)) {
                $sql .= "(tracking_id = ? OR booking_reference = ?)";
                $params = [$tracking_id, $booking_reference];
            } elseif (!empty($tracking_id)) {
                $sql .= "tracking_id = ?";
                $params = [$tracking_id];
            } else {
                $sql .= "booking_reference = ?";
                $params = [$booking_reference];
            }
            
            $sql .= " ORDER BY created_at DESC LIMIT 1";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $booking_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$booking_data) {
                $error = "No booking found with the provided details. Please check your Tracking ID or Booking Reference.";
            } else {
                $success = "Booking found! Here are your flight details.";
            }
        } catch (Exception $e) {
            $error = "Error retrieving booking information: " . $e->getMessage();
            error_log("Track ticket error: " . $e->getMessage());
        }
    }
}

// If booking data found, decode the JSON fields
if ($booking_data) {
    $flight_data = json_decode($booking_data['flight_data'], true);
    $passenger_info = json_decode($booking_data['passenger_info'], true);
    $contact_info = json_decode($booking_data['contact_info'], true);
    
    // Calculate prices
    $total_amount = floatval($booking_data['total_amount']);
    $currency = $booking_data['currency'] ?? 'NGN';
    
    // Get currency symbol
    $currency_symbol = '₦';
    switch($currency) {
        case 'USD': $currency_symbol = '$'; break;
        case 'EUR': $currency_symbol = '€'; break;
        case 'GBP': $currency_symbol = '£'; break;
    }
    
    // Prepare flight details
    $itinerary = $flight_data['itineraries'][0] ?? [];
    $first_segment = $itinerary['segments'][0] ?? [];
    $last_segment = end($itinerary['segments']) ?? [];
    
    $flight_route = ($first_segment['departure']['iataCode'] ?? '') . ' → ' . ($last_segment['arrival']['iataCode'] ?? '');
    $departure_date = !empty($first_segment['departure']['at']) ? date('M j, Y', strtotime($first_segment['departure']['at'])) : '';
    $departure_time = !empty($first_segment['departure']['at']) ? date('H:i', strtotime($first_segment['departure']['at'])) : '';
    $arrival_time = !empty($last_segment['arrival']['at']) ? date('H:i', strtotime($last_segment['arrival']['at'])) : '';
    $airline = $first_segment['carrierCode'] ?? '';
    $flight_class = $flight_data['travelerPricings'][0]['fareDetailsBySegment'][0]['cabin'] ?? 'Economy';
    $duration = isset($itinerary['duration']) ? substr($itinerary['duration'], 2) : '';
    
    // Get airline name
    $airline_name = $airline;
    if (function_exists('getAirlineNameFromAmadeus')) {
        $airline_name = getAirlineNameFromAmadeus($airline);
    }
    
    // Current date and time
    $booking_date = !empty($booking_data['created_at']) ? date('F j, Y \a\t H:i', strtotime($booking_data['created_at'])) : date('F j, Y \a\t H:i');
    
    // Prepare passenger rows for table
    $passenger_rows = '';
    $passenger_count = is_array($passenger_info) ? count($passenger_info) : 0;
    if (is_array($passenger_info)) {
        foreach ($passenger_info as $index => $passenger) {
            $passenger_num = $index + 1;
            $passenger_name = ($passenger['first_name'] ?? '') . ' ' . ($passenger['last_name'] ?? '');
            $passenger_dob = !empty($passenger['dob']) ? date('M j, Y', strtotime($passenger['dob'])) : '';
            $passenger_gender = !empty($passenger['gender']) ? ucfirst($passenger['gender']) : '';
            
            $passenger_rows .= "
            <tr>
                <td>Passenger {$passenger_num}</td>
                <td>{$passenger_name}</td>
                <td>{$passenger_dob}</td>
                <td>{$passenger_gender}</td>
            </tr>";
        }
    }
    
    // Determine booking status and styling
    $payment_status = $booking_data['payment_status'] ?? 'pending';
    $status_badge = '';
    $status_message = '';
    
    switch($payment_status) {
        case 'confirmed':
            $status_badge = "<span class='status-badge status-confirmed'><svg width='16' height='16' viewBox='0 0 24 24' fill='currentColor' style='margin-right: 0.5rem;'><path d='M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z'/></svg>Confirmed</span>";
            $status_message = "Your booking has been confirmed and your flight ticket is ready.";
            break;
        case 'pending':
            $status_badge = "<span class='status-badge status-pending'><svg width='16' height='16' viewBox='0 0 24 24' fill='currentColor' style='margin-right: 0.5rem;'><path d='M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z'/></svg>Pending Payment</span>";
            $status_message = "Your booking is pending payment. Complete payment to confirm your flight.";
            break;
        case 'cancelled':
            $status_badge = "<span class='status-badge status-cancelled'><svg width='16' height='16' viewBox='0 0 24 24' fill='currentColor' style='margin-right: 0.5rem;'><path d='M12 2C6.47 2 2 6.47 2 12s4.47 10 10 10 10-4.47 10-10S17.53 2 12 2zm5 13.59L15.59 17 12 13.41 8.41 17 7 15.59 10.59 12 7 8.41 8.41 7 12 10.59 15.59 7 17 8.41 13.41 12 17 15.59z'/></svg>Cancelled</span>";
            $status_message = "This booking has been cancelled.";
            break;
        default:
            $status_badge = "<span class='status-badge status-unknown'><svg width='16' height='16' viewBox='0 0 24 24' fill='currentColor' style='margin-right: 0.5rem;'><path d='M11 15h2v2h-2zm0-8h2v6h-2zm.99-5C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8z'/></svg>Unknown</span>";
            $status_message = "Booking status is unknown.";
    }
}

require_once 'includes/header.php';
?>

<style>
    /* Modern CSS Variables */
    :root {
        --primary: #667eea;
        --primary-dark: #5a6fd8;
        --secondary: #764ba2;
        --success: #48bb78;
        --warning: #ed8936;
        --danger: #f56565;
        --light: #f8fafc;
        --dark: #2d3748;
        --gray: #718096;
        --gray-light: #e2e8f0;
        --shadow: 0 20px 40px rgba(0,0,0,0.1);
        --shadow-sm: 0 10px 25px rgba(0,0,0,0.05);
        --radius: 16px;
        --radius-sm: 12px;
        --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* Hero Section */
    .tracking-hero {
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.9) 0%, rgba(118, 75, 162, 0.9) 100%), 
                   url('https://images.unsplash.com/photo-1436491865332-7a61a109cc05?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2074&q=80') center/cover;
        padding: 5rem 0;
        color: white;
        position: relative;
        overflow: hidden;
        text-align: center;
    }

    .tracking-hero::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000" opacity="0.1"><path fill="white" d="M500,250c138.07,0,250,111.93,250,250S638.07,750,500,750S250,638.07,250,500S361.93,250,500,250z"/></svg>') center/cover;
    }

    .hero-content {
        position: relative;
        z-index: 2;
    }

    .hero-content h1 {
        font-size: 3.5rem;
        font-weight: 800;
        margin-bottom: 1rem;
        text-shadow: 0 4px 20px rgba(0,0,0,0.3);
        background: linear-gradient(135deg, #fff 0%, #e2e8f0 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .hero-content p {
        font-size: 1.3rem;
        opacity: 0.95;
        margin-bottom: 0;
        font-weight: 300;
    }

    /* Main Container */
    .tracking-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 3rem 2rem;
    }

    /* Cards */
    .tracking-card {
        background: white;
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        overflow: hidden;
        transition: var(--transition);
        border: 1px solid var(--gray-light);
        backdrop-filter: blur(10px);
    }

    .tracking-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-sm);
    }

    .card-header {
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        color: white;
        padding: 2rem;
        position: relative;
        overflow: hidden;
    }

    .card-header::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
        animation: float 6s ease-in-out infinite;
    }

    .card-header h2 {
        margin: 0;
        font-size: 1.8rem;
        font-weight: 700;
        position: relative;
        z-index: 2;
    }

    .card-body {
        padding: 2.5rem;
    }

    /* Form Elements */
    .form-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }

    @media (min-width: 768px) {
        .form-grid {
            grid-template-columns: 1fr 1fr;
        }
    }

    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 600;
        color: var(--dark);
        font-size: 1.1rem;
    }

    .form-control {
        width: 100%;
        padding: 1rem 1.5rem;
        border: 2px solid var(--gray-light);
        border-radius: var(--radius-sm);
        font-size: 1rem;
        transition: var(--transition);
        background: white;
    }

    .form-control:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    /* Buttons */
    .btn-primary {
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        color: white;
        border: none;
        padding: 1.25rem 2.5rem;
        border-radius: var(--radius-sm);
        font-weight: 700;
        font-size: 1.2rem;
        transition: var(--transition);
        cursor: pointer;
        width: 100%;
        position: relative;
        overflow: hidden;
    }

    .btn-primary::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
        transition: var(--transition);
    }

    .btn-primary:hover::before {
        left: 100%;
    }

    .btn-primary:hover {
        transform: translateY(-3px);
        box-shadow: 0 15px 30px rgba(102, 126, 234, 0.4);
    }

    /* Status Badges */
    .status-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.75rem 1.5rem;
        border-radius: 25px;
        font-size: 1rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .status-confirmed {
        background: #d4edda;
        color: #155724;
        animation: pulse 2s infinite;
    }

    .status-pending {
        background: #fff3cd;
        color: #856404;
    }

    .status-cancelled {
        background: #f8d7da;
        color: #721c24;
    }

    .status-unknown {
        background: #e2e3e5;
        color: #383d41;
    }

    /* Quick Actions */
    .quick-actions {
        display: flex;
        justify-content: center;
        gap: 1rem;
        margin: 2rem 0;
        flex-wrap: wrap;
    }

    .action-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.75rem;
        padding: 1rem 2rem;
        background: white;
        color: var(--primary);
        text-decoration: none;
        border-radius: var(--radius-sm);
        font-weight: 600;
        transition: var(--transition);
        border: 2px solid var(--gray-light);
    }

    .action-btn:hover {
        background: var(--primary);
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        border-color: var(--primary);
    }

    .action-btn.success {
        background: var(--success);
        color: white;
        border-color: var(--success);
    }

    .action-btn.success:hover {
        background: #38a169;
        box-shadow: 0 10px 25px rgba(72, 187, 120, 0.3);
    }

    /* Info Cards */
    .info-card {
        background: var(--light);
        border-radius: var(--radius-sm);
        padding: 2rem;
        margin: 1.5rem 0;
        border-left: 4px solid var(--primary);
        position: relative;
        overflow: hidden;
    }

    .info-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    }

    .info-card.warning {
        background: #fff3cd;
        border-left-color: var(--warning);
    }

    .info-card.success {
        background: #d1edff;
        border-left-color: var(--primary);
    }

    .info-card.danger {
        background: #f8d7da;
        border-left-color: var(--danger);
    }

    /* Flight Details */
    .flight-details {
        background: white;
        border: 2px solid var(--gray-light);
        border-radius: var(--radius-sm);
        padding: 2rem;
        margin: 1.5rem 0;
        position: relative;
    }

    .flight-route {
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        font-size: 1.8rem;
        font-weight: 800;
        margin-bottom: 1rem;
    }

    .flight-timeline {
        display: grid;
        grid-template-columns: 1fr auto 1fr;
        gap: 1rem;
        align-items: center;
        margin: 2rem 0;
    }

    .timeline-connector {
        height: 3px;
        background: linear-gradient(90deg, var(--primary) 0%, var(--secondary) 100%);
        width: 80px;
        margin: 0 auto;
        border-radius: 2px;
        position: relative;
    }

    .timeline-connector::before {
        content: '✈️';
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: white;
        padding: 0.5rem;
        border-radius: 50%;
        font-size: 1.2rem;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }

    /* Tables */
    .passenger-table {
        width: 100%;
        border-collapse: collapse;
        margin: 1.5rem 0;
        background: white;
        border-radius: var(--radius-sm);
        overflow: hidden;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }

    .passenger-table th {
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        color: white;
        padding: 1.25rem;
        text-align: left;
        font-weight: 600;
        font-size: 1.1rem;
    }

    .passenger-table td {
        padding: 1.25rem;
        border-bottom: 1px solid var(--gray-light);
        color: var(--dark);
    }

    .passenger-table tr:last-child td {
        border-bottom: none;
    }

    .passenger-table tr:hover {
        background: var(--light);
    }

    /* FAQ Section */
    .faq-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 2rem;
    }

    @media (min-width: 768px) {
        .faq-grid {
            grid-template-columns: 1fr 1fr;
        }
    }

    .faq-item h4 {
        color: var(--primary);
        margin-bottom: 1rem;
        font-size: 1.2rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .faq-item h4 svg {
        color: var(--secondary);
        flex-shrink: 0;
    }

    /* Animations */
    @keyframes float {
        0%, 100% { transform: translateY(0px) rotate(0deg); }
        50% { transform: translateY(-20px) rotate(180deg); }
    }

    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(40px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .animate-fade-in-up {
        animation: fadeInUp 0.8s ease-out;
    }

    .animate-delay-1 {
        animation-delay: 0.2s;
    }

    .animate-delay-2 {
        animation-delay: 0.4s;
    }

    /* Mobile Optimizations */
    @media (max-width: 768px) {
        .tracking-hero {
            padding: 3rem 0;
        }

        .hero-content h1 {
            font-size: 2.5rem;
        }

        .tracking-container {
            padding: 2rem 1rem;
        }

        .card-body {
            padding: 1.5rem;
        }

        .quick-actions {
            flex-direction: column;
        }

        .action-btn {
            justify-content: center;
        }

        .flight-timeline {
            grid-template-columns: 1fr;
            text-align: center;
        }

        .timeline-connector {
            transform: rotate(90deg);
            width: 50px;
            margin: 1rem auto;
        }

        .passenger-table {
            font-size: 0.875rem;
        }

        .passenger-table th,
        .passenger-table td {
            padding: 0.875rem;
        }
    }

    /* Focus States for Accessibility */
    .form-control:focus,
    .btn-primary:focus,
    .action-btn:focus {
        outline: 2px solid var(--primary);
        outline-offset: 2px;
    }
</style>

<!-- Hero Section -->
<section class="tracking-hero">
    <div class="tracking-container">
        <div class="hero-content animate-fade-in-up">
            <h1>Track Your Flight Ticket</h1>
            <p>Monitor your booking status and access flight details instantly</p>
        </div>
    </div>
</section>

<!-- Main Tracking Section -->
<div class="tracking-container">
    <!-- Tracking Form -->
    <div class="tracking-card animate-fade-in-up">
        <div class="card-header">
            <h2>Enter Your Booking Details</h2>
        </div>
        <div class="card-body">
            <?php if ($error): ?>
                <div class="info-card danger">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                        </svg>
                        <div>
                            <h4 style="margin: 0 0 0.5rem 0; color: #721c24;">Tracking Error</h4>
                            <p style="margin: 0; color: #721c24;"><?php echo htmlspecialchars($error); ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="info-card success">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                        </svg>
                        <div>
                            <h4 style="margin: 0 0 0.5rem 0; color: #155724;">Success!</h4>
                            <p style="margin: 0; color: #155724;"><?php echo htmlspecialchars($success); ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style="margin-right: 0.5rem;">
                                <path d="M20.5 3l-.16.03L15 5.1 9 3 3.36 4.9c-.21.07-.36.25-.36.48V20.5c0 .28.22.5.5.5l.16-.03L9 18.9l6 2.1 5.64-1.9c.21-.07.36-.25.36-.48V3.5c0-.28-.22-.5-.5-.5zM15 19l-6-2.11V5l6 2.11V19z"/>
                            </svg>
                            Tracking ID
                        </label>
                        <input type="text" name="tracking_id" value="<?php echo htmlspecialchars($tracking_id); ?>" 
                               placeholder="e.g., TRKABC123XYZ" 
                               class="form-control">
                        <small style="color: var(--gray); margin-top: 0.5rem; display: block;">
                            Enter your Tracking ID (starts with TRK)
                        </small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style="margin-right: 0.5rem;">
                                <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                            </svg>
                            Booking Reference
                        </label>
                        <input type="text" name="booking_reference" value="<?php echo htmlspecialchars($booking_reference); ?>" 
                               placeholder="e.g., TC20241201123456" 
                               class="form-control">
                        <small style="color: var(--gray); margin-top: 0.5rem; display: block;">
                            Enter your Booking Reference (starts with TC)
                        </small>
                    </div>
                </div>
                
                <div style="text-align: center; margin-top: 2rem;">
                    <button type="submit" class="btn-primary">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style="margin-right: 0.75rem;">
                            <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                        </svg>
                        Track Flight
                    </button>
                </div>
            </form>
            
            <div class="info-card" style="margin-top: 2rem;">
                <h4 style="margin-bottom: 1rem; color: var(--dark); display: flex; align-items: center; gap: 0.75rem;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/>
                    </svg>
                    Need Help Finding Your Details?
                </h4>
                <ul style="color: var(--gray); margin: 0; padding-left: 1.2rem;">
                    <li>Check your booking confirmation email for Tracking ID and Booking Reference</li>
                    <li>Tracking ID starts with "TRK" (e.g., TRKABC123XYZ)</li>
                    <li>Booking Reference starts with "TC" followed by date and random characters</li>
                    <li>You can enter either Tracking ID or Booking Reference - both will work</li>
                </ul>
            </div>
        </div>
    </div>
    
    <!-- Booking Details (Shown when booking found) -->
    <?php if ($booking_data): ?>
        <div class="tracking-card animate-fade-in-up animate-delay-1" style="margin-top: 2rem;">
            <!-- Status Header -->
            <div class="card-header">
                <h2>Flight Ticket Status</h2>
            </div>
            <div class="card-body">
                <div style="text-align: center; margin-bottom: 2rem;">
                    <div style="display: flex; justify-content: center; align-items: center; gap: 1.5rem; flex-wrap: wrap; margin-bottom: 1rem;">
                        <?php echo $status_badge; ?>
                    </div>
                    <div style="font-size: 1.2rem; color: var(--dark); font-weight: 600;">
                        <?php echo $status_message; ?>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="quick-actions">
                    <?php if ($payment_status === 'pending'): ?>
                        <a href="payment.php?booking_id=<?php echo $booking_data['id']; ?>&type=<?php echo $booking_data['is_guest'] ? 'guest' : 'user'; ?>" 
                           class="action-btn success">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                            </svg>
                            Complete Payment
                        </a>
                    <?php endif; ?>
                    
                    <a href="invoice.php?booking_ref=<?php echo $booking_data['booking_reference']; ?>" 
                       class="action-btn">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/>
                        </svg>
                        View Invoice
                    </a>
                    
                    <a href="contact.php" 
                       class="action-btn">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M20 2H4c-1.1 0-1.99.9-1.99 2L2 22l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-2 12H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z"/>
                        </svg>
                        Contact Support
                    </a>
                </div>
                
                <!-- Booking Information -->
                <div style="display: grid; grid-template-columns: 1fr; gap: 2rem; background: var(--light); padding: 2rem; border-radius: var(--radius-sm); margin-bottom: 2rem;">
                    <div>
                        <h3 style="color: var(--dark); margin-bottom: 1.5rem; font-size: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm-5 14H4v-4h11v4zm0-5H4V9h11v4zm5 5h-4V9h4v9z"/>
                            </svg>
                            Booking Information
                        </h3>
                        <div style="display: grid; grid-template-columns: 1fr; gap: 1rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: white; border-radius: var(--radius-sm);">
                                <span style="font-weight: 600; color: var(--dark);">Booking Reference:</span>
                                <span style="background: var(--primary); color: white; padding: 0.5rem 1rem; border-radius: 20px; font-weight: 700; font-size: 0.9rem;">
                                    <?php echo $booking_data['booking_reference']; ?>
                                </span>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: white; border-radius: var(--radius-sm);">
                                <span style="font-weight: 600; color: var(--dark);">Tracking ID:</span>
                                <span style="color: var(--primary); font-weight: 700;"><?php echo $booking_data['tracking_id']; ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: white; border-radius: var(--radius-sm);">
                                <span style="font-weight: 600; color: var(--dark);">Booking Date:</span>
                                <span style="color: var(--gray);"><?php echo $booking_date; ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: white; border-radius: var(--radius-sm);">
                                <span style="font-weight: 600; color: var(--dark);">Booking Type:</span>
                                <span style="color: var(--gray);"><?php echo $booking_data['is_guest'] ? 'Guest Booking' : 'Registered User'; ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <h3 style="color: var(--dark); margin-bottom: 1.5rem; font-size: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2C13.1 2 14 2.9 14 4s-.9 2-2 2-2-.9-2-2 .9-2 2-2zm9 7h-6v13h-2v-6h-2v6H9V9H3V7h18v2z"/>
                            </svg>
                            Payment Information
                        </h3>
                        <div style="display: grid; grid-template-columns: 1fr; gap: 1rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: white; border-radius: var(--radius-sm);">
                                <span style="font-weight: 600; color: var(--dark);">Total Amount:</span>
                                <span style="color: var(--primary); font-weight: 700; font-size: 1.2rem;">
                                    <?php echo $currency_symbol . number_format($total_amount, 2) . ' ' . $currency; ?>
                                </span>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: white; border-radius: var(--radius-sm);">
                                <span style="font-weight: 600; color: var(--dark);">Payment Status:</span>
                                <span style="text-transform: capitalize; font-weight: 600;"><?php echo $payment_status; ?></span>
                            </div>
                            <?php if ($booking_data['is_guest']): ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: white; border-radius: var(--radius-sm);">
                                    <span style="font-weight: 600; color: var(--dark);">Guest Email:</span>
                                    <span style="color: var(--gray);"><?php echo $booking_data['guest_email']; ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Flight Details -->
                <div style="margin-bottom: 2rem;">
                    <h3 style="color: var(--dark); margin-bottom: 1.5rem; font-size: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M21 16v-2l-8-5V3.5c0-.83-.67-1.5-1.5-1.5S10 2.67 10 3.5V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5l8 2.5z"/>
                        </svg>
                        Flight Details
                    </h3>
                    <div class="flight-details">
                        <div class="flight-route">
                            <?php echo $flight_route; ?>
                        </div>
                        
                        <div style="color: var(--gray); margin-bottom: 1rem; font-weight: 600; font-size: 1.1rem;">
                            <?php echo $departure_date; ?>
                        </div>
                        
                        <div class="flight-timeline">
                            <div style="text-align: center;">
                                <strong style="font-size: 1.5rem; color: var(--dark);"><?php echo $departure_time; ?></strong>
                                <div style="color: var(--gray); font-size: 1rem; margin-top: 0.5rem;">
                                    <?php echo $first_segment['departure']['iataCode'] ?? ''; ?>
                                </div>
                            </div>
                            
                            <div class="timeline-connector"></div>
                            
                            <div style="text-align: center;">
                                <strong style="font-size: 1.5rem; color: var(--dark);"><?php echo $arrival_time; ?></strong>
                                <div style="color: var(--gray); font-size: 1rem; margin-top: 0.5rem;">
                                    <?php echo $last_segment['arrival']['iataCode'] ?? ''; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 2rem; text-align: center;">
                            <div>
                                <div style="font-weight: 600; color: var(--dark);">Airline</div>
                                <div style="color: var(--gray);"><?php echo $airline_name; ?></div>
                            </div>
                            <div>
                                <div style="font-weight: 600; color: var(--dark);">Class</div>
                                <div style="color: var(--gray);"><?php echo $flight_class; ?></div>
                            </div>
                            <div>
                                <div style="font-weight: 600; color: var(--dark);">Duration</div>
                                <div style="color: var(--gray);"><?php echo $duration; ?></div>
                            </div>
                            <div>
                                <div style="font-weight: 600; color: var(--dark);">Segments</div>
                                <div style="color: var(--gray);"><?php echo count($itinerary['segments']); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Passenger Details -->
                <div style="margin-bottom: 2rem;">
                    <h3 style="color: var(--dark); margin-bottom: 1.5rem; font-size: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                        </svg>
                        Passenger Details (<?php echo $passenger_count; ?> Passenger<?php echo $passenger_count > 1 ? 's' : ''; ?>)
                    </h3>
                    <table class="passenger-table">
                        <thead>
                            <tr>
                                <th>Passenger</th>
                                <th>Full Name</th>
                                <th>Date of Birth</th>
                                <th>Gender</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php echo $passenger_rows; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Contact Information -->
                <div style="margin-bottom: 2rem;">
                    <h3 style="color: var(--dark); margin-bottom: 1.5rem; font-size: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                        </svg>
                        Contact Information
                    </h3>
                    <div style="background: var(--light); padding: 2rem; border-radius: var(--radius-sm);">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
                            <div>
                                <div style="font-weight: 600; color: var(--dark); margin-bottom: 0.5rem;">Email</div>
                                <div style="color: var(--gray);"><?php echo $contact_info['email'] ?? 'N/A'; ?></div>
                            </div>
                            <div>
                                <div style="font-weight: 600; color: var(--dark); margin-bottom: 0.5rem;">Phone</div>
                                <div style="color: var(--gray);"><?php echo $contact_info['phone'] ?? 'N/A'; ?></div>
                            </div>
                            <div style="grid-column: 1 / -1;">
                                <div style="font-weight: 600; color: var(--dark); margin-bottom: 0.5rem;">Address</div>
                                <div style="color: var(--gray);"><?php echo $contact_info['address'] ?? 'N/A'; ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Next Steps -->
                <div class="info-card <?php echo $payment_status === 'confirmed' ? 'success' : ($payment_status === 'pending' ? 'warning' : 'danger'); ?>">
                    <h4 style="margin-bottom: 1rem; color: var(--dark); display: flex; align-items: center; gap: 0.75rem;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                        </svg>
                        Next Steps
                    </h4>
                    <?php if ($payment_status === 'confirmed'): ?>
                        <ul style="color: var(--dark); margin: 0; padding-left: 1.2rem;">
                            <li>Your flight ticket has been confirmed</li>
                            <li>You will receive your e-ticket via email within 24 hours</li>
                            <li>Check-in online 24 hours before departure</li>
                            <li>Arrive at the airport at least 2 hours before departure</li>
                            <li>Carry a valid government-issued ID</li>
                        </ul>
                    <?php elseif ($payment_status === 'pending'): ?>
                        <ul style="color: var(--dark); margin: 0; padding-left: 1.2rem;">
                            <li>Complete your payment to confirm the booking</li>
                            <li>Payment must be completed within 24 hours</li>
                            <li>After payment, you'll receive confirmation within 1 hour</li>
                            <li>Contact support if you need payment assistance</li>
                        </ul>
                    <?php else: ?>
                        <ul style="color: var(--dark); margin: 0; padding-left: 1.2rem;">
                            <li>This booking is no longer active</li>
                            <li>Contact support for more information</li>
                            <li>Consider making a new booking</li>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- FAQ Section -->
    <div class="tracking-card animate-fade-in-up animate-delay-2" style="margin-top: 2rem;">
        <div class="card-header">
            <h2>Frequently Asked Questions</h2>
        </div>
        <div class="card-body">
            <div class="faq-grid">
                <div>
                    <div class="faq-item">
                        <h4>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm2.07-7.75l-.9.92C13.45 12.9 13 13.5 13 15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2s-2 .9-2 2H8c0-2.21 1.79-4 4-4s4 1.79 4 4c0 .88-.36 1.68-.93 2.25z"/>
                            </svg>
                            Where can I find my Tracking ID?
                        </h4>
                        <p style="color: var(--gray); margin: 0;">Your Tracking ID is provided in the booking confirmation email. It starts with "TRK" followed by 10 characters.</p>
                    </div>
                    
                    <div class="faq-item" style="margin-top: 2rem;">
                        <h4>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm2.07-7.75l-.9.92C13.45 12.9 13 13.5 13 15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2s-2 .9-2 2H8c0-2.21 1.79-4 4-4s4 1.79 4 4c0 .88-.36 1.68-.93 2.25z"/>
                            </svg>
                            What if my payment is still pending?
                        </h4>
                        <p style="color: var(--gray); margin: 0;">Complete your payment using the "Complete Payment" button above. Your booking will be confirmed within 1 hour after payment.</p>
                    </div>
                    
                    <div class="faq-item" style="margin-top: 2rem;">
                        <h4>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm2.07-7.75l-.9.92C13.45 12.9 13 13.5 13 15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2s-2 .9-2 2H8c0-2.21 1.79-4 4-4s4 1.79 4 4c0 .88-.36 1.68-.93 2.25z"/>
                            </svg>
                            When will I receive my e-ticket?
                        </h4>
                        <p style="color: var(--gray); margin: 0;">E-tickets are typically sent within 24 hours after payment confirmation. Check your email including spam folder.</p>
                    </div>
                </div>
                <div>
                    <div class="faq-item">
                        <h4>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm2.07-7.75l-.9.92C13.45 12.9 13 13.5 13 15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2s-2 .9-2 2H8c0-2.21 1.79-4 4-4s4 1.79 4 4c0 .88-.36 1.68-.93 2.25z"/>
                            </svg>
                            Can I make changes to my booking?
                        </h4>
                        <p style="color: var(--gray); margin: 0;">Changes may be possible depending on the airline's policy. Contact our support team for assistance with modifications.</p>
                    </div>
                    
                    <div class="faq-item" style="margin-top: 2rem;">
                        <h4>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm2.07-7.75l-.9.92C13.45 12.9 13 13.5 13 15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2s-2 .9-2 2H8c0-2.21 1.79-4 4-4s4 1.79 4 4c0 .88-.36 1.68-.93 2.25z"/>
                            </svg>
                            What if I need to cancel my flight?
                        </h4>
                        <p style="color: var(--gray); margin: 0;">Cancellation policies vary by airline. Contact support for cancellation requests and refund information.</p>
                    </div>
                    
                    <div class="faq-item" style="margin-top: 2rem;">
                        <h4>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm2.07-7.75l-.9.92C13.45 12.9 13 13.5 13 15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2s-2 .9-2 2H8c0-2.21 1.79-4 4-4s4 1.79 4 4c0 .88-.36 1.68-.93 2.25z"/>
                            </svg>
                            Need urgent assistance?
                        </h4>
                        <p style="color: var(--gray); margin: 0;">Call our support team at <strong>+234 903 407 2383</strong> or email <strong>support@travelcentre.ng</strong> for immediate help.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add this after the existing flight tracking form -->

<div class="tracking-card" style="margin-top: 2rem;">
    <div class="card-header">
        <h2>Track Visa Application</h2>
    </div>
    <div class="card-body">
        <form method="POST" action="track-visa.php">
            <div class="form-group">
                <label class="form-label">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style="margin-right: 0.5rem;">
                        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/>
                        <circle cx="12" cy="9" r="2.5"/>
                    </svg>
                    Visa Tracking ID
                </label>
                <input type="text" name="tracking_id" class="form-control" 
                       placeholder="e.g., VTRKABC123XYZ">
                <small style="color: var(--gray); margin-top: 0.5rem; display: block;">
                    Enter your Visa Tracking ID (starts with VTRK)
                </small>
            </div>
            
            <div style="text-align: center; margin-top: 1.5rem;">
                <button type="submit" class="btn-primary" style="width: auto; padding: 0.75rem 1.5rem;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" style="margin-right: 0.5rem;">
                        <circle cx="11" cy="11" r="8"/>
                        <path d="m21 21-4.3-4.3"/>
                    </svg>
                    Track Visa
                </button>
            </div>
        </form>
        
        <div class="info-card" style="margin-top: 1.5rem;">
            <h4 style="margin-bottom: 0.75rem; color: var(--dark); display: flex; align-items: center; gap: 0.5rem;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/>
                </svg>
                Need Help Finding Your Visa Tracking ID?
            </h4>
            <ul style="color: var(--gray); margin: 0; padding-left: 1.2rem;">
                <li>Check your visa application confirmation email</li>
                <li>Visa Tracking ID starts with "VTRK"</li>
                <li>Contact support if you cannot find your tracking ID</li>
            </ul>
        </div>
    </div>
</div>

<script>
// Auto-focus on first input field
document.addEventListener('DOMContentLoaded', function() {
    const firstInput = document.querySelector('input[type="text"]');
    if (firstInput) {
        firstInput.focus();
    }
});

// Add printing functionality
function printTicket() {
    window.print();
}

// Add keyboard shortcut for printing (Ctrl+P)
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
        e.preventDefault();
        printTicket();
    }
});

// Smooth scrolling to results when form is submitted and has results
<?php if ($booking_data): ?>
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        const resultsSection = document.querySelector('.tracking-card.animate-delay-1');
        if (resultsSection) {
            resultsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }, 500);
});
<?php endif; ?>
</script>

<?php
require_once 'includes/footer.php';
?>