[file name]: invoice.php
[file content begin]
<?php
// invoice.php
require_once 'config.php';
require_once 'includes/functions.php';

$page_title = "Flight Invoice";

// Check if booking reference is provided
if (!isset($_GET['booking_ref']) || empty($_GET['booking_ref'])) {
    redirect('flights.php');
}

$booking_reference = sanitize($_GET['booking_ref']);

// Initialize variables
$booking_data = null;
$error = '';
$website_email = 'info@travelcentre.ng';
$website_logo = '';

try {
    // Get website settings
    $stmt = $pdo->query("SELECT admin_email, logo FROM site_settings ORDER BY id DESC LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($settings) {
        if (!empty($settings['admin_email'])) $website_email = $settings['admin_email'];
        if (!empty($settings['logo'])) $website_logo = $settings['logo'];
    }
} catch (Exception $e) {
    error_log("Website settings error: " . $e->getMessage());
}

try {
    // Fetch booking data from database
    $stmt = $pdo->prepare("SELECT * FROM flight_bookings WHERE booking_reference = ?");
    $stmt->execute([$booking_reference]);
    $booking_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking_data) {
        $error = "Booking not found. Please check your booking reference.";
    }
} catch (Exception $e) {
    $error = "Error retrieving booking information: " . $e->getMessage();
    error_log("Invoice error: " . $e->getMessage());
}

// If booking data found, decode the JSON fields
if ($booking_data) {
    $flight_data = json_decode($booking_data['flight_data'], true);
    $passenger_info = json_decode($booking_data['passenger_info'], true);
    $contact_info = json_decode($booking_data['contact_info'], true);
    
    // Calculate prices
    $total_amount = floatval($booking_data['total_amount']);
    $base_fare = floatval($booking_data['base_fare'] ?? $total_amount * 0.8);
    $taxes_fees = floatval($booking_data['taxes_fees'] ?? $total_amount * 0.15);
    $service_fee = floatval($booking_data['service_fee'] ?? $total_amount * 0.05);
    
    $currency = $booking_data['currency'] ?? 'NGN';
    
    // Get currency symbol
    $currency_symbol = '¥';
    switch($currency) {
        case 'NGN': $currency_symbol = '₦'; break;
        case 'USD': $currency_symbol = '$'; break;
        case 'EUR': $currency_symbol = '€'; break;
        case 'GBP': $currency_symbol = '£'; break;
    }
    
    // Prepare flight details for round trip
    $itineraries = $flight_data['itineraries'] ?? [];
    $outbound_itinerary = $itineraries[0] ?? [];
    $return_itinerary = $itineraries[1] ?? $itineraries[0] ?? [];
    
    // Outbound flight details
    $outbound_first_segment = $outbound_itinerary['segments'][0] ?? [];
    $outbound_last_segment = end($outbound_itinerary['segments']) ?? [];
    
    $departure_iata = $outbound_first_segment['departure']['iataCode'] ?? 'LOS';
    $arrival_iata = $outbound_last_segment['arrival']['iataCode'] ?? 'ABB';
    $flight_route = $departure_iata . ' → ' . $arrival_iata . ' → ' . $departure_iata;
    
    $departure_date = !empty($outbound_first_segment['departure']['at']) ? date('M j, Y', strtotime($outbound_first_segment['departure']['at'])) : 'Nov 21, 2025';
    $departure_time = !empty($outbound_first_segment['departure']['at']) ? date('g:i A', strtotime($outbound_first_segment['departure']['at'])) : '7:00 PM';
    $arrival_time = !empty($outbound_last_segment['arrival']['at']) ? date('g:i A', strtotime($outbound_last_segment['arrival']['at'])) : '1:30 PM';
    
    // Return flight details
    $return_first_segment = $return_itinerary['segments'][0] ?? [];
    $return_last_segment = end($return_itinerary['segments']) ?? [];
    
    $return_departure_time = !empty($return_first_segment['departure']['at']) ? date('g:i A', strtotime($return_first_segment['departure']['at'])) : '2:10 PM';
    $return_arrival_time = !empty($return_last_segment['arrival']['at']) ? date('g:i A', strtotime($return_last_segment['arrival']['at'])) : '3:20 PM';
    $return_date = !empty($return_first_segment['departure']['at']) ? date('M j, Y', strtotime($return_first_segment['departure']['at'])) : 'Nov 23, 2025';
    
    $outbound_airline = $outbound_first_segment['carrierCode'] ?? 'AIR PEACE LIMITED';
    $outbound_flight_no = $outbound_first_segment['number'] ?? 'P47130';
    $outbound_duration = isset($outbound_itinerary['duration']) ? substr($outbound_itinerary['duration'], 2) : '18h 30m';
    $outbound_stops = count($outbound_itinerary['segments'] ?? []) - 1;
    $outbound_stops_text = $outbound_stops . ' stop' . ($outbound_stops !== 1 ? 's' : '');
    
    $return_airline = $return_first_segment['carrierCode'] ?? 'AIR PEACE LIMITED';
    $return_flight_no = $return_first_segment['number'] ?? 'P4921';
    $return_duration = isset($return_itinerary['duration']) ? substr($return_itinerary['duration'], 2) : '18h 30m';
    $return_stops = count($return_itinerary['segments'] ?? []) - 1;
    $return_stops_text = $return_stops . ' stop' . ($return_stops !== 1 ? 's' : '');
    
    $flight_class = $flight_data['travelerPricings'][0]['fareDetailsBySegment'][0]['cabin'] ?? 'ECONOMY';
    
    // Get airline name
    $outbound_airline_name = $outbound_airline;
    $return_airline_name = $return_airline;
    if (function_exists('getAirlineNameFromAmadeus')) {
        $outbound_airline_name = getAirlineNameFromAmadeus($outbound_airline);
        $return_airline_name = getAirlineNameFromAmadeus($return_airline);
    }
    
    // Current date and time
    $invoice_date = date('F j, Y');
    $booking_date = !empty($booking_data['created_at']) ? date('F j, Y \a\t H:i', strtotime($booking_data['created_at'])) : date('F j, Y \a\t H:i');
    
    // Generate invoice number
    $invoice_no = "TC-INV-" . date('Y') . "-" . ($booking_data['id'] ?? '2383');
    
    // Prepare passenger rows for table
    $passenger_rows = '';
    $passenger_count = is_array($passenger_info) ? count($passenger_info) : 1;
    if (is_array($passenger_info) && count($passenger_info) > 0) {
        $passenger = $passenger_info[0];
        $passenger_name = ($passenger['first_name'] ?? 'Ablaque') . ' ' . ($passenger['last_name'] ?? '');
        $passenger_email = $contact_info['email'] ?? 'austcomdesign@gmail.com';
    } else {
        $passenger_name = 'Ablaque';
        $passenger_email = 'austcomdesign@gmail.com';
    }
}

require_once 'includes/header.php';
?>

<div class="container" style="max-width: 1000px; margin: 2rem auto; padding: 0 20px;">
    <?php if ($error): ?>
        <div class="no-print" style="background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 5px; margin-bottom: 2rem; text-align: center;">
            <h3 style="margin: 0 0 0.5rem 0;">Invoice Error</h3>
            <p style="margin: 0;"><?php echo htmlspecialchars($error); ?></p>
            <a href="flights.php" style="display: inline-block; margin-top: 1rem; padding: 0.5rem 1rem; background: #007bff; color: white; text-decoration: none; border-radius: 5px;">Search Flights</a>
        </div>
    <?php elseif ($booking_data): ?>
        <!-- Print Modal -->
        <div id="printModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 10000; justify-content: center; align-items: center;">
            <div style="background: white; padding: 2rem; border-radius: 10px; text-align: center; max-width: 400px; width: 90%;">
                <h2 style="margin: 0 0 1rem 0; color: #333;">Invoice Ready</h2>
                <p style="margin: 0 0 2rem 0; color: #666;">Your flight invoice has been generated. What would you like to do?</p>
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <?php if (($booking_data['payment_status'] ?? 'pending') === 'pending'): ?>
                        <a href="payment.php?booking_id=<?php echo $booking_data['id']; ?>&type=<?php echo $booking_data['is_guest'] ? 'guest' : 'user'; ?>" 
                           style="padding: 1rem 1.5rem; background: #28a745; color: white; text-decoration: none; border-radius: 5px; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                            </svg>
                            Continue with Payment
                        </a>
                    <?php endif; ?>
                    <button onclick="printInvoice()" style="padding: 1rem 1.5rem; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/>
                        </svg>
                        Print/Download Invoice
                    </button>
                    <button onclick="closePrintModal()" style="padding: 1rem 1.5rem; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: 600;">
                        Close
                    </button>
                </div>
            </div>
        </div>

        <!-- Invoice Actions -->
        <div class="no-print" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; padding: 1rem; background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <div>
                <h1 style="margin: 0; color: #333;">Flight Invoice</h1>
                <p style="margin: 0.5rem 0 0 0; color: #666;">Reference: <?php echo $booking_reference; ?></p>
            </div>
            <div style="display: flex; gap: 1rem;">
                <button onclick="showPrintModal()" style="padding: 0.75rem 1.5rem; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; display: flex; align-items: center; gap: 0.5rem;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/>
                    </svg>
                    Print Options
                </button>
                <a href="flights.php" style="padding: 0.75rem 1.5rem; background: #6c757d; color: white; text-decoration: none; border-radius: 5px; display: flex; align-items: center; gap: 0.5rem;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/>
                    </svg>
                    Back to Search
                </a>
            </div>
        </div>

        <!-- Invoice Content -->
        <div id="invoice-content" class="invoice-container" style="background: white; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); overflow: hidden; font-family: Arial, sans-serif;">
            <!-- Header -->
            <div class="header" style="text-align: center; padding: 1.5rem; border-bottom: 1px solid #e2e8f0;">
                <h2 style="margin: 0 0 0.5rem 0; font-size: 1.2rem; font-weight: 600; color: #333;">Official Flight Booking Partner</h2>
                <p style="margin: 0; color: #666; font-size: 0.9rem;">flight.travelcentre.ng | email <?php echo $website_email; ?></p>
            </div>

            <!-- Invoice Details -->
            <div class="invoice-details" style="padding: 1.5rem; border-bottom: 1px solid #e2e8f0;">
                <h3 style="margin: 0 0 1rem 0; font-size: 1.1rem; font-weight: 600;">INVOICE No: <?php echo $invoice_no; ?></h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <p style="margin: 0.25rem 0; font-size: 0.9rem;"><strong>DATE:</strong> <?php echo date('F j, Y'); ?></p>
                        <p style="margin: 0.25rem 0; font-size: 0.9rem;"><strong>TRIP TYPE:</strong> Round Trip</p>
                        <p style="margin: 0.25rem 0; font-size: 0.9rem;"><strong>DEPARTURE TIME:</strong> <?php echo $departure_time; ?></p>
                        <p style="margin: 0.25rem 0; font-size: 0.9rem;"><strong>ARRIVAL TIME:</strong> <?php echo $arrival_time; ?></p>
                    </div>
                    <div>
                        <p style="margin: 0.25rem 0; font-size: 0.9rem;"><strong>RETURN DEPARTURE:</strong> <?php echo $return_departure_time; ?></p>
                        <p style="margin: 0.25rem 0; font-size: 0.9rem;"><strong>RETURN ARRIVAL:</strong> <?php echo $return_arrival_time; ?></p>
                        <p style="margin: 0.25rem 0; font-size: 0.9rem;"><strong>BOOKING DATE:</strong> <?php echo $booking_date; ?></p>
                    </div>
                </div>
            </div>

            <!-- Passenger and Route Details -->
            <div class="section" style="padding: 1.5rem; border-bottom: 1px solid #e2e8f0;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="background: #f8f9fa; padding: 10px; text-align: left; font-weight: 600; border: 1px solid #dee2e6;">BOOKING REFERENCE</th>
                            <th style="background: #f8f9fa; padding: 10px; text-align: left; font-weight: 600; border: 1px solid #dee2e6;">PASSENGER NAME</th>
                            <th style="background: #f8f9fa; padding: 10px; text-align: left; font-weight: 600; border: 1px solid #dee2e6;">EMAIL</th>
                            <th style="background: #f8f9fa; padding: 10px; text-align: left; font-weight: 600; border: 1px solid #dee2e6;">ROUTE</th>
                            <th style="background: #f8f9fa; padding: 10px; text-align: left; font-weight: 600; border: 1px solid #dee2e6;">DEPARTURE DATE</th>
                            <th style="background: #f8f9fa; padding: 10px; text-align: left; font-weight: 600; border: 1px solid #dee2e6;">RETURN DATE</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="padding: 10px; border: 1px solid #dee2e6;"><?php echo $booking_reference; ?></td>
                            <td style="padding: 10px; border: 1px solid #dee2e6;"><?php echo $passenger_name; ?></td>
                            <td style="padding: 10px; border: 1px solid #dee2e6;"><?php echo $passenger_email; ?></td>
                            <td style="padding: 10px; border: 1px solid #dee2e6;"><?php echo $flight_route; ?></td>
                            <td style="padding: 10px; border: 1px solid #dee2e6;"><?php echo $departure_date; ?></td>
                            <td style="padding: 10px; border: 1px solid #dee2e6;"><?php echo $return_date; ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Outbound Flight -->
            <div class="section" style="padding: 1.5rem; border-bottom: 1px solid #e2e8f0;">
                <h4 style="margin: 0 0 1rem 0; font-size: 1rem; font-weight: 600;">OUTBOUND FLIGHT</h4>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="background: #f8f9fa; padding: 10px; text-align: left; font-weight: 600; border: 1px solid #dee2e6;">AIRLINE</th>
                            <th style="background: #f8f9fa; padding: 10px; text-align: left; font-weight: 600; border: 1px solid #dee2e6;">FLIGHT NO.</th>
                            <th style="background: #f8f9fa; padding: 10px; text-align: left; font-weight: 600; border: 1px solid #dee2e6;">CLASS</th>
                            <th style="background: #f8f9fa; padding: 10px; text-align: left; font-weight: 600; border: 1px solid #dee2e6;">DURATION</th>
                            <th style="background: #f8f9fa; padding: 10px; text-align: left; font-weight: 600; border: 1px solid #dee2e6;">STOPS</th>
                            <th style="background: #f8f9fa; padding: 10px; text-align: left; font-weight: 600; border: 1px solid #dee2e6;">DEPARTURE</th>
                            <th style="background: #f8f9fa; padding: 10px; text-align: left; font-weight: 600; border: 1px solid #dee2e6;">ARRIVAL</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="padding: 10px; border: 1px solid #dee2e6;"><?php echo $outbound_airline_name; ?></td>
                            <td style="padding: 10px; border: 1px solid #dee2e6;"><?php echo $outbound_flight_no; ?></td>
                            <td style="padding: 10px; border: 1px solid #dee2e6;"><?php echo $flight_class; ?></td>
                            <td style="padding: 10px; border: 1px solid #dee2e6;"><?php echo $outbound_duration; ?></td>
                            <td style="padding: 10px; border: 1px solid #dee2e6;"><?php echo $outbound_stops_text; ?></td>
                            <td style="padding: 10px; border: 1px solid #dee2e6;"><?php echo $departure_time; ?></td>
                            <td style="padding: 10px; border: 1px solid #dee2e6;"><?php echo $arrival_time; ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Return Flight -->
            <div class="section" style="padding: 1.5rem; border-bottom: 1px solid #e2e8f0;">
                <h4 style="margin: 0 0 1rem 0; font-size: 1rem; font-weight: 600;">RETURN FLIGHT</h4>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="background: #f8f9fa; padding: 10px; text-align: left; font-weight: 600; border: 1px solid #dee2e6;">AIRLINE</th>
                            <th style="background: #f8f9fa; padding: 10px; text-align: left; font-weight: 600; border: 1px solid #dee2e6;">FLIGHT NO.</th>
                            <th style="background: #f8f9fa; padding: 10px; text-align: left; font-weight: 600; border: 1px solid #dee2e6;">CLASS</th>
                            <th style="background: #f8f9fa; padding: 10px; text-align: left; font-weight: 600; border: 1px solid #dee2e6;">DURATION</th>
                            <th style="background: #f8f9fa; padding: 10px; text-align: left; font-weight: 600; border: 1px solid #dee2e6;">STOPS</th>
                            <th style="background: #f8f9fa; padding: 10px; text-align: left; font-weight: 600; border: 1px solid #dee2e6;">DEPARTURE</th>
                            <th style="background: #f8f9fa; padding: 10px; text-align: left; font-weight: 600; border: 1px solid #dee2e6;">ARRIVAL</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="padding: 10px; border: 1px solid #dee2e6;"><?php echo $return_airline_name; ?></td>
                            <td style="padding: 10px; border: 1px solid #dee2e6;"><?php echo $return_flight_no; ?></td>
                            <td style="padding: 10px; border: 1px solid #dee2e6;"><?php echo $flight_class; ?></td>
                            <td style="padding: 10px; border: 1px solid #dee2e6;"><?php echo $return_duration; ?></td>
                            <td style="padding: 10px; border: 1px solid #dee2e6;"><?php echo $return_stops_text; ?></td>
                            <td style="padding: 10px; border: 1px solid #dee2e6;"><?php echo $return_departure_time; ?></td>
                            <td style="padding: 10px; border: 1px solid #dee2e6;"><?php echo $return_arrival_time; ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Page Break for Print -->
            <div style="page-break-before: always;"></div>

            <!-- Fare Breakdown -->
            <div class="section" style="padding: 1.5rem; border-bottom: 1px solid #e2e8f0;">
                <h4 style="margin: 0 0 1rem 0; font-size: 1rem; font-weight: 600;">FARE BREAKDOWN</h4>
                <div style="display: grid; grid-template-columns: 1fr; gap: 0.5rem;">
                    <div style="display: flex; justify-content: space-between;">
                        <span>Base Fare:</span>
                        <span><?php echo $currency_symbol . number_format($base_fare, 2); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span>Taxes & Airline Charges:</span>
                        <span><?php echo $currency_symbol . number_format($taxes_fees, 2); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span>Service Fee:</span>
                        <span><?php echo $currency_symbol . number_format($service_fee, 2); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-weight: 600; border-top: 1px solid #dee2e6; padding-top: 0.5rem;">
                        <span>Total:</span>
                        <span><?php echo $currency_symbol . number_format($total_amount, 2); ?></span>
                    </div>
                </div>
            </div>

            <!-- Payment Summary -->
            <div class="section" style="padding: 1.5rem; border-bottom: 1px solid #e2e8f0;">
                <h4 style="margin: 0 0 1rem 0; font-size: 1rem; font-weight: 600;">PAYMENT SUMMARY</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <p style="margin: 0.25rem 0; font-size: 0.9rem;"><strong>REFERENCE:</strong> PSK-<?php echo substr($booking_reference, -4); ?></p>
                        <p style="margin: 0.25rem 0; font-size: 0.9rem;"><strong>STATUS:</strong> 
                            <span style="padding: 0.25rem 0.5rem; border-radius: 3px; font-size: 0.8rem; 
                                background: <?php echo ($booking_data['payment_status'] ?? 'pending') === 'confirmed' ? '#d4edda' : '#fff3cd'; ?>; 
                                color: <?php echo ($booking_data['payment_status'] ?? 'pending') === 'confirmed' ? '#155724' : '#856404'; ?>;">
                                <?php echo ($booking_data['payment_status'] ?? 'pending') === 'confirmed' ? 'Confirmed' : 'Pending Confirmation'; ?>
                            </span>
                        </p>
                    </div>
                    <div>
                        <p style="margin: 0.25rem 0; font-size: 0.9rem;"><strong>TRIP TYPE:</strong> Round Trip</p>
                        <p style="margin: 0.25rem 0; font-size: 0.9rem;"><strong>DEPARTURE TIME:</strong> <?php echo $departure_time; ?></p>
                        <p style="margin: 0.25rem 0; font-size: 0.9rem;"><strong>ARRIVAL TIME:</strong> <?php echo $arrival_time; ?></p>
                        <p style="margin: 0.25rem 0; font-size: 0.9rem;"><strong>RETURN DEPARTURE:</strong> <?php echo $return_departure_time; ?></p>
                        <p style="margin: 0.25rem 0; font-size: 0.9rem;"><strong>RETURN ARRIVAL:</strong> <?php echo $return_arrival_time; ?></p>
                    </div>
                </div>
                <p style="margin: 1rem 0 0 0; font-size: 0.8rem; color: #666;">
                    All payments are processed through secure channels. Invoices are auto-issued after confirmation.
                </p>
            </div>

            <!-- Thank You Section -->
            <div class="section" style="padding: 1.5rem; text-align: center;">
                <h3 style="margin: 0 0 1rem 0; font-size: 1.2rem; font-weight: 600;">THANK YOU FOR YOUR BOOKING!</h3>
                <p style="margin: 0 0 1rem 0; font-size: 0.9rem;">
                    TravelCentre.ng – Your trusted flight partner across Africa.<br>
                    For assistance, +234 903 407 2383 | <?php echo $website_email; ?>
                </p>
                <p style="margin: 0; font-size: 0.8rem; color: #666;">
                    This invoice has been sent to the provided email addresses.
                </p>
            </div>

            <!-- Footer -->
            <div class="footer no-print" style="background: #f8f9fa; padding: 1.5rem; text-align: center; border-top: 1px solid #e2e8f0;">
                <p style="margin: 0 0 1rem 0; font-weight: 600;">Thank you for choosing Travel Centre!</p>
                <p style="margin: 0 0 1rem 0;">For assistance: <?php echo $website_email; ?> | +234 903 407 2383</p>
                <div style="display: flex; justify-content: center; gap: 1rem; margin-bottom: 1rem;">
                    <a href="flights.php" style="color: #007bff; text-decoration: none;">Search Flights</a>
                    <a href="contact.php" style="color: #007bff; text-decoration: none;">Contact Support</a>
                    <a href="track-booking.php" style="color: #007bff; text-decoration: none;">Track Booking</a>
                </div>
                <p style="margin: 0; font-size: 0.875rem; color: #666;">
                    This invoice has been automatically generated. Please keep your Booking Reference for future reference.
                </p>
            </div>
        </div>

        <!-- Additional Actions -->
        <div class="no-print" style="display: flex; justify-content: center; gap: 1rem; margin-top: 2rem; flex-wrap: wrap;">
            <?php if (($booking_data['payment_status'] ?? 'pending') === 'pending'): ?>
                <a href="payment.php?booking_id=<?php echo $booking_data['id']; ?>&type=<?php echo $booking_data['is_guest'] ? 'guest' : 'user'; ?>" 
                   style="padding: 1rem 2rem; background: #28a745; color: white; text-decoration: none; border-radius: 5px; font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                    </svg>
                    Complete Payment
                </a>
            <?php endif; ?>
            <button onclick="showPrintModal()" style="padding: 1rem 2rem; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/>
                </svg>
                Print Options
            </button>
            <a href="flights.php" style="padding: 1rem 2rem; background: #6c757d; color: white; text-decoration: none; border-radius: 5px; font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/>
                </svg>
                Book Another Flight
            </a>
        </div>
    <?php endif; ?>
</div>

<style>
/* Print-specific styles */
@media print {
    /* Hide everything except the invoice content */
    body * {
        visibility: hidden;
        margin: 0 !important;
        padding: 0 !important;
    }
    
    /* Show only the invoice container and its children */
    #invoice-content,
    #invoice-content * {
        visibility: visible;
    }
    
    /* Position the invoice at the top left */
    #invoice-content {
        position: absolute;
        left: 0;
        top: 0;
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        box-shadow: none !important;
        border: none !important;
        border-radius: 0 !important;
        font-family: Arial, sans-serif !important;
    }
    
    /* Hide no-print elements */
    .no-print, #printModal {
        display: none !important;
    }
    
    /* Ensure proper printing of colors */
    th {
        background: #f8f9fa !important;
        color: #000 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    
    /* Remove any margins and padding from body */
    body {
        margin: 0 !important;
        padding: 0 !important;
        background: white !important;
        font-size: 12pt;
    }
    
    /* Ensure tables break properly */
    table {
        page-break-inside: auto;
    }
    
    tr {
        page-break-inside: avoid;
        page-break-after: auto;
    }
    
    /* Remove any margins from the printed page */
    @page {
        margin: 0.5cm;
        size: A4;
    }
    
    /* Page break for multi-page invoices */
    .page-break {
        page-break-before: always;
    }
}

/* Regular screen styles */
@media (max-width: 768px) {
    .invoice-details {
        grid-template-columns: 1fr !important;
        gap: 1rem !important;
    }
    
    table {
        font-size: 0.75rem;
    }
    
    th, td {
        padding: 6px !important;
    }
    
    .no-print {
        flex-direction: column;
        align-items: center;
    }
    
    .no-print > div {
        flex-direction: column;
        gap: 0.5rem;
    }
}

/* Print Modal Styles */
#printModal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.8);
    z-index: 10000;
    justify-content: center;
    align-items: center;
}
</style>

<script>
// Print function
function printInvoice() {
    window.print();
    closePrintModal();
}

// Show print modal
function showPrintModal() {
    document.getElementById('printModal').style.display = 'flex';
}

// Close print modal
function closePrintModal() {
    document.getElementById('printModal').style.display = 'none';
}

// Add keyboard shortcut for printing (Ctrl+P or Cmd+P)
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
        e.preventDefault();
        printInvoice();
    }
});

// Auto-show print modal when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Small delay to ensure page is fully loaded
    setTimeout(function() {
        showPrintModal();
    }, 500);
});

// Close modal when clicking outside
document.getElementById('printModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closePrintModal();
    }
});

// Add a simple print stylesheet for better compatibility
function addPrintStyles() {
    const style = document.createElement('style');
    style.innerHTML = `
        @media print {
            @page {
                margin: 0.5cm;
                size: A4;
            }
            
            body {
                margin: 0;
                padding: 0;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                font-family: Arial, sans-serif;
            }
            
            .invoice-container {
                font-size: 12pt;
            }
        }
    `;
    document.head.appendChild(style);
}

// Initialize print styles when page loads
document.addEventListener('DOMContentLoaded', function() {
    addPrintStyles();
});
</script>

<?php
require_once 'includes/footer.php';
?>
[file content end]