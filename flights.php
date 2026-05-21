<?php
// flights.php

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output buffering to catch any unexpected output
ob_start();

try {
    require_once 'config.php';
    require_once 'includes/functions.php'; // Include functions file

    $page_title = "Search Flights";
    $flights = [];
    $error = '';
    $auth_error = '';
    $auth_success = '';

    // Check for authentication messages
    if (isset($_GET['auth_error'])) {
        $auth_error = urldecode($_GET['auth_error']);
    }
    if (isset($_GET['auth_success'])) {
        $auth_success = urldecode($_GET['auth_success']);
    }

    // Initialize database-related variables with safe defaults
    $conversion_rate = 1;
    $website_email = 'support@travelcentre.ng';
    $website_logo = '';
    $smtp_settings = [];
    $special_offer = ['enabled' => false];
    $search_settings = ['filter_enabled' => true, 'ad_panel_enabled' => true, 'ad_panel_content' => ''];
    $filter_enabled = true;
    $ad_panel_enabled = true;
    $ad_panel_content = '';

    // Check if database connection is available
    if (isset($pdo) && $pdo) {
        // Get currency conversion rate from database
        try {
            $stmt = $pdo->prepare("SELECT value FROM site_settings WHERE name = 'currency_rate'");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result && is_numeric($result['value'])) {
                $conversion_rate = floatval($result['value']);
            }
        } catch (Exception $e) {
            error_log("Currency rate error: " . $e->getMessage());
            // Use default rate if there's an error
            $conversion_rate = 1;
        }

        // Get website email from database
        try {
            $stmt = $pdo->prepare("SELECT admin_email FROM site_settings ORDER BY id DESC LIMIT 1");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result && !empty($result['admin_email'])) {
                $website_email = $result['admin_email'];
            }
        } catch (Exception $e) {
            error_log("Website email error: " . $e->getMessage());
            // Use default email if there's an error
            $website_email = 'support@travelcentre.ng';
        }

        // Get website logo from database
        try {
            $stmt = $pdo->prepare("SELECT logo FROM site_settings ORDER BY id DESC LIMIT 1");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result && !empty($result['logo'])) {
                $website_logo = $result['logo'];
            }
        } catch (Exception $e) {
            error_log("Website logo error: " . $e->getMessage());
            // Use default if there's an error
            $website_logo = '';
        }

        // Get SMTP settings from database - SET GLOBALS for email functions
        global $smtp_host, $smtp_port, $smtp_username, $smtp_password, $smtp_encryption, $smtp_from_email, $smtp_from_name;
        try {
            $stmt = $pdo->prepare("SELECT smtp_host, smtp_port, smtp_username, smtp_password, smtp_encryption, smtp_from_email, smtp_from_name FROM site_settings ORDER BY id DESC LIMIT 1");
            $stmt->execute();
            $smtp_result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($smtp_result && !empty($smtp_result['smtp_host'])) {
                $smtp_settings = $smtp_result;
                $smtp_host = $smtp_result['smtp_host'] ?? '';
                $smtp_port = $smtp_result['smtp_port'] ?? 587;
                $smtp_username = $smtp_result['smtp_username'] ?? '';
                $smtp_password = $smtp_result['smtp_password'] ?? '';
                $smtp_encryption = $smtp_result['smtp_encryption'] ?? 'tls';
                $smtp_from_email = $smtp_result['smtp_from_email'] ?? $website_email;
                $smtp_from_name = $smtp_result['smtp_from_name'] ?? 'Travel Centre';
            }
        } catch (Exception $e) {
            error_log("SMTP settings error: " . $e->getMessage());
            $smtp_settings = [];
        }

        // Get special offers from database
        try {
            $stmt = $pdo->prepare("SELECT offer_title, offer_description, offer_discount, offer_valid_until, offer_enabled, offer_image FROM site_settings ORDER BY id DESC LIMIT 1");
            $stmt->execute();
            $offer_result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($offer_result && isset($offer_result['offer_enabled']) && $offer_result['offer_enabled'] == 1) {
                $special_offer = [
                    'title' => $offer_result['offer_title'] ?? 'Special Flight Deal!',
                    'description' => $offer_result['offer_description'] ?? 'Book your dream vacation now and enjoy incredible savings!',
                    'discount' => $offer_result['offer_discount'] ?? '50%',
                    'valid_until' => $offer_result['offer_valid_until'] ?? '',
                    'image' => $offer_result['offer_image'] ?? '',
                    'enabled' => true
                ];
                
                // Check if offer is still valid
                if (!empty($special_offer['valid_until']) && strtotime($special_offer['valid_until']) < time()) {
                    $special_offer['enabled'] = false;
                }
            } else {
                $special_offer['enabled'] = false;
            }
        } catch (Exception $e) {
            error_log("Special offer error: " . $e->getMessage());
            // Offer not available or error
            $special_offer['enabled'] = false;
        }

        // Get filter and ad panel settings using the function from functions.php
        if (function_exists('getFlightSearchSettings')) {
            $search_settings = getFlightSearchSettings($pdo);
        }
        $filter_enabled = $search_settings['filter_enabled'] ?? true;
        $ad_panel_enabled = $search_settings['ad_panel_enabled'] ?? true;
        $ad_panel_content = $search_settings['ad_panel_content'] ?? '';
    }

    // Get search parameters with proper sanitization
    $origin = isset($_GET['origin']) ? strtoupper(trim($_GET['origin'])) : '';
    $destination = isset($_GET['destination']) ? strtoupper(trim($_GET['destination'])) : '';
    $departure_date = isset($_GET['departure_date']) ? trim($_GET['departure_date']) : '';
    $return_date = isset($_GET['return_date']) ? trim($_GET['return_date']) : '';
    $passengers = isset($_GET['passengers']) ? intval($_GET['passengers']) : 1;
    $travel_class = isset($_GET['travel_class']) ? trim($_GET['travel_class']) : 'ECONOMY';
    $trip_type = isset($_GET['trip_type']) ? trim($_GET['trip_type']) : 'one_way';

    // Validate and sanitize inputs
    if ($passengers < 1) $passengers = 1;
    if ($passengers > 9) $passengers = 9;

    // Get filter parameters
    $selected_airlines = isset($_GET['airlines']) ? (array)$_GET['airlines'] : [];
    $max_stops = isset($_GET['max_stops']) ? intval($_GET['max_stops']) : -1;
    $min_price = isset($_GET['min_price']) ? floatval($_GET['min_price']) : 0;
    $max_price = isset($_GET['max_price']) ? floatval($_GET['max_price']) : 1000000;
    $departure_time = isset($_GET['departure_time']) ? trim($_GET['departure_time']) : '';

    // For one-way trips, clear return date
    if ($trip_type === 'one_way') {
        $return_date = '';
    }

    // Search flights if parameters are provided
    if ($origin && $destination && $departure_date) {
        try {
            // First, authenticate with Amadeus API
            $auth_url = AMADEUS_BASE_URL . '/v1/security/oauth2/token';
            $auth_data = [
                'grant_type' => 'client_credentials',
                'client_id' => AMADEUS_API_KEY_DB,
                'client_secret' => AMADEUS_API_SECRET_DB
            ];

            $auth_response = httpPost($auth_url, $auth_data);
            $auth_result = json_decode($auth_response, true);

            if (isset($auth_result['access_token'])) {
                $access_token = $auth_result['access_token'];
                
                // Build flight search parameters
                $search_params = [
                    'originLocationCode' => $origin,
                    'destinationLocationCode' => $destination,
                    'departureDate' => $departure_date,
                    'adults' => $passengers,
                    'currencyCode' => 'NGN',
                    'max' => 50 // Get more results for filtering
                ];
                
                // Add travel class if specified and not empty
                if (!empty($travel_class) && $travel_class !== 'ECONOMY') {
                    $search_params['travelClass'] = $travel_class;
                }
                
                // Add return date only for round trips
                if ($return_date && $trip_type === 'round_trip') {
                    $search_params['returnDate'] = $return_date;
                }

                // Build flight search URL
                $search_url = AMADEUS_BASE_URL . '/v2/shopping/flight-offers?' . http_build_query($search_params);

                // Search flights
                $search_response = httpGet($search_url, $access_token);
                $search_result = json_decode($search_response, true);

                if (isset($search_result['data']) && is_array($search_result['data'])) {
                    $all_flights = $search_result['data'];
                    
                    // Apply filters if any filter is active
                    if (!empty($selected_airlines) || $max_stops >= 0 || $min_price > 0 || $max_price < 1000000 || !empty($departure_time)) {
                        if (function_exists('applyFilters')) {
                            $flights = applyFilters($all_flights, $selected_airlines, $max_stops, $min_price, $max_price, $departure_time, $conversion_rate);
                        } else {
                            $flights = $all_flights;
                        }
                    } else {
                        $flights = $all_flights;
                    }
                    
                } else {
                    if (isset($search_result['errors'])) {
                        $api_error = $search_result['errors'][0]['detail'] ?? $search_result['errors'][0]['title'] ?? 'Unknown API error';
                        $error = 'API Error: ' . $api_error;
                    } else {
                        $error = 'No flights found for your search criteria. Try different dates or routes.';
                    }
                }
            } else {
                $auth_error = $auth_result['error_description'] ?? $auth_result['error'] ?? 'Unknown authentication error';
                $error = 'Authentication failed: ' . $auth_error;
            }
        } catch (Exception $e) {
            $error = 'Error searching flights: ' . $e->getMessage();
            error_log("Flight search error: " . $e->getMessage());
        }
    }

    // Process airport search if AJAX request
    if (isset($_GET['search_airports']) && isset($_GET['query'])) {
        $query = trim($_GET['query']);
        if (function_exists('getAirportSuggestionsFromAmadeus')) {
            $suggestions = getAirportSuggestionsFromAmadeus($query);
        } else {
            $suggestions = [];
        }
        header('Content-Type: application/json');
        echo json_encode($suggestions);
        exit;
    }

    // Process airline data if AJAX request
    if (isset($_GET['get_airline_info']) && isset($_GET['airline_code'])) {
        $airline_code = trim($_GET['airline_code']);
        if (function_exists('getAirlineInfoFromAmadeus')) {
            $airline_info = getAirlineInfoFromAmadeus($airline_code);
        } else {
            $airline_info = [];
        }
        header('Content-Type: application/json');
        echo json_encode($airline_info);
        exit;
    }

    // Process WhatsApp booking (keep for backward compatibility)
    if (isset($_POST['whatsapp_booking'])) {
        $flight_data = json_decode($_POST['flight_data'], true);
        if ($flight_data && isset($flight_data['itineraries'][0])) {
            $itinerary = $flight_data['itineraries'][0];
            $first_segment = $itinerary['segments'][0];
            $last_segment = end($itinerary['segments']);
            $airline_code = $first_segment['carrierCode'];
            
            // Calculate price
            $base_price = floatval($flight_data['price']['grandTotal']);
            $converted_price = $base_price * $conversion_rate;
            $final_price = round($converted_price, 2);
            
            // Get flight times
            $departure_time = date('g:i A', strtotime($first_segment['departure']['at']));
            $arrival_time = date('g:i A', strtotime($last_segment['arrival']['at']));
            
            // For round trips, get return flight times if available
            $return_departure_time = '';
            $return_arrival_time = '';
            if ($trip_type === 'round_trip' && isset($flight_data['itineraries'][1])) {
                $return_itinerary = $flight_data['itineraries'][1];
                $return_first_segment = $return_itinerary['segments'][0];
                $return_last_segment = end($return_itinerary['segments']);
                $return_departure_time = date('g:i A', strtotime($return_first_segment['departure']['at']));
                $return_arrival_time = date('g:i A', strtotime($return_last_segment['arrival']['at']));
            }
            
            // Build WhatsApp message with flight times
            if (function_exists('generateWhatsAppMessageWithTimes')) {
                $message = generateWhatsAppMessageWithTimes(
                    $flight_data, 
                    $_POST['passengers'], 
                    $_POST['travel_class'], 
                    $final_price,
                    $departure_time,
                    $arrival_time,
                    $return_departure_time,
                    $return_arrival_time
                );
                
                // Encode message for URL
                $encoded_message = urlencode($message);
                $whatsapp_url = "https://wa.me/23409034072383?text={$encoded_message}";
                
                // Redirect to WhatsApp
                header("Location: $whatsapp_url");
                exit;
            }
        }
    }

    // NEW: Function to generate enhanced invoice HTML (same as book-flight.php)
    function generateEnhancedInvoiceHTML($flight_data, $passenger_data, $contact_info, $booking_reference, $tracking_id, $total_amount, $currency, $booking_type, $website_logo, $website_email, $conversion_rate, $origin, $destination, $departure_date, $return_date, $trip_type, $travel_class, $passengers) {
        
        // Get currency symbol
        $currency_symbol = '₦';
        switch($currency) {
            case 'USD': $currency_symbol = '$'; break;
            case 'EUR': $currency_symbol = '€'; break;
            case 'GBP': $currency_symbol = '£'; break;
        }
        
        // Prepare flight details
        $itinerary = $flight_data['itineraries'][0];
        $first_segment = $itinerary['segments'][0];
        $last_segment = end($itinerary['segments']);
        
        $flight_route = $first_segment['departure']['iataCode'] . ' → ' . $last_segment['arrival']['iataCode'];
        $departure_date_formatted = date('M j, Y', strtotime($first_segment['departure']['at']));
        $departure_time = date('H:i', strtotime($first_segment['departure']['at']));
        $arrival_time = date('H:i', strtotime($last_segment['arrival']['at']));
        $airline = $first_segment['carrierCode'];
        $flight_class = $travel_class;
        $duration = substr($itinerary['duration'], 2);
        
        // Get airline name
        $airline_name = $airline;
        if (function_exists('getAirlineNameFromAmadeus')) {
            $airline_name = getAirlineNameFromAmadeus($airline);
        }
        
        // Current date and time
        $invoice_date = date('F j, Y');
        $booking_date = date('F j, Y \a\t H:i');
        
        // Generate invoice number
        $invoice_no = "INV-" . date('Ymd') . "-" . strtoupper(substr($booking_reference, -6));
        
        // Prepare passenger rows for table
        $passenger_rows = '';
        foreach ($passenger_data as $index => $passenger) {
            $passenger_num = $index + 1;
            $passenger_rows .= "
            <tr>
                <td style='padding: 10px; border-bottom: 1px solid #e2e8f0;'>Passenger {$passenger_num}</td>
                <td style='padding: 10px; border-bottom: 1px solid #e2e8f0;'>{$passenger['name']}</td>
                <td style='padding: 10px; border-bottom: 1px solid #e2e8f0;'>{$passenger['email']}</td>
            </tr>";
        }
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Flight Invoice - {$booking_reference}</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; background: white; }
                .invoice-container { max-width: 800px; margin: 0 auto; padding: 30px; }
                .header { text-align: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid #667eea; }
                .logo { max-width: 150px; margin-bottom: 10px; }
                .company-info h2 { color: #667eea; margin: 5px 0; }
                .invoice-details { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0; background: #f8fafc; padding: 20px; border-radius: 8px; }
                .section { margin: 25px 0; }
                .section-title { color: #2d3748; margin-bottom: 15px; font-size: 18px; font-weight: 600; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px; }
                table { width: 100%; border-collapse: collapse; margin: 15px 0; }
                th { background: #667eea; color: white; padding: 12px; text-align: left; font-weight: 600; }
                td { padding: 12px; border-bottom: 1px solid #e2e8f0; }
                .price-summary { background: linear-gradient(135deg, #48bb78 0%, #38a169 100%); color: white; padding: 20px; border-radius: 8px; text-align: center; margin: 20px 0; }
                .price-amount { font-size: 28px; font-weight: 700; }
                .footer { margin-top: 40px; padding-top: 20px; border-top: 2px solid #667eea; text-align: center; color: #718096; }
                @media print {
                    body { margin: 0; }
                    .invoice-container { padding: 20px; }
                }
            </style>
        </head>
        <body>
            <div class='invoice-container'>
                <div class='header'>
                    " . (!empty($website_logo) ? "<img src='{$website_logo}' alt='Travel Centre' class='logo'>" : "<h1 style='color: #667eea;'>✈️ TRAVEL CENTRE</h1>") . "
                    <div class='company-info'>
                        <h2>Official Flight Booking Partner</h2>
                        <p>flight.travelcentre.ng | {$website_email}</p>
                    </div>
                </div>

                <div class='invoice-details'>
                    <div>
                        <p><strong>INVOICE No:</strong> {$invoice_no}</p>
                        <p><strong>DATE:</strong> {$invoice_date}</p>
                        <p><strong>BOOKING REF:</strong> {$booking_reference}</p>
                    </div>
                    <div>
                        <p><strong>TRACKING ID:</strong> {$tracking_id}</p>
                        <p><strong>BOOKING DATE:</strong> {$booking_date}</p>
                        <p><strong>BOOKING TYPE:</strong> Flight Quote</p>
                    </div>
                </div>

                <div class='section'>
                    <div class='section-title'>Flight Details</div>
                    <table>
                        <thead>
                            <tr>
                                <th>Route</th>
                                <th>Airline</th>
                                <th>Departure</th>
                                <th>Arrival</th>
                                <th>Class</th>
                                <th>Passengers</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>{$flight_route}</td>
                                <td>{$airline_name}</td>
                                <td>{$departure_date_formatted} at {$departure_time}</td>
                                <td>{$departure_date_formatted} at {$arrival_time}</td>
                                <td>{$flight_class}</td>
                                <td>{$passengers}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class='section'>
                    <div class='section-title'>Passenger Details</div>
                    <table>
                        <thead>
                            <tr>
                                <th>Passenger</th>
                                <th>Full Name</th>
                                <th>Email</th>
                            </tr>
                        </thead>
                        <tbody>
                            {$passenger_rows}
                        </tbody>
                    </table>
                </div>

                <div class='section'>
                    <div class='section-title'>Contact Information</div>
                    <div style='background: #f8fafc; padding: 15px; border-radius: 6px;'>
                        <p><strong>Email:</strong> {$contact_info['email']}</p>
                        <p><strong>Phone:</strong> {$contact_info['phone']}</p>
                        <p><strong>Address:</strong> {$contact_info['address']}</p>
                    </div>
                </div>

                <div class='price-summary'>
                    <div class='price-amount'>{$currency_symbol}{$total_amount} {$currency}</div>
                    <div>Total Amount</div>
                </div>

                <div class='section'>
                    <div class='section-title'>Quote Status</div>
                    <div style='background: #e6fffa; color: #065f46; padding: 15px; border-radius: 6px; text-align: center;'>
                        <strong>Status: Flight Quote</strong><br>
                        This is a flight quote. Proceed to booking to confirm your seats.
                    </div>
                </div>

                <div class='footer'>
                    <p><strong>Thank you for considering Travel Centre!</strong></p>
                    <p>For assistance: {$website_email} | +234 903 407 2383</p>
                    <p style='margin-top: 10px; font-size: 12px;'>
                        This quote has been automatically generated. Please contact us to proceed with booking.
                    </p>
                </div>
            </div>
        </body>
        </html>";
    }

    // NEW: Function to send enhanced booking emails (same as book-flight.php)
    function sendEnhancedBookingEmails($pdo, $flight_data, $passenger_data, $contact_info, $booking_reference, $tracking_id, $total_amount, $currency, $booking_type, $website_email, $website_logo, $origin, $destination, $departure_date, $return_date, $trip_type, $travel_class, $passengers) {
        $admin_email = $website_email;
        $user_email = $contact_info['email'];
        
        $website_url = 'https://travelcentre.ng';
        
        // Get currency symbol
        $currency_symbol = '₦';
        switch($currency) {
            case 'USD': $currency_symbol = '$'; break;
            case 'EUR': $currency_symbol = '€'; break;
            case 'GBP': $currency_symbol = '£'; break;
        }
        
        // Prepare flight details
        $itinerary = $flight_data['itineraries'][0];
        $first_segment = $itinerary['segments'][0];
        $last_segment = end($itinerary['segments']);
        
        $flight_route = $first_segment['departure']['iataCode'] . ' → ' . $last_segment['arrival']['iataCode'];
        $departure_date_formatted = date('M j, Y', strtotime($first_segment['departure']['at']));
        $departure_time = date('H:i', strtotime($first_segment['departure']['at']));
        $arrival_time = date('H:i', strtotime($last_segment['arrival']['at']));
        $airline = $first_segment['carrierCode'];
        $flight_class = $travel_class;
        $duration = substr($itinerary['duration'], 2);
        
        // Get airline name
        $airline_name = $airline;
        if (function_exists('getAirlineNameFromAmadeus')) {
            $airline_name = getAirlineNameFromAmadeus($airline);
        }
        
        // Prepare passenger details
        $passenger_details = '';
        $passenger_count = count($passenger_data);
        foreach ($passenger_data as $index => $passenger) {
            $passenger_num = $index + 1;
            $passenger_details .= "
            <tr>
                <td style='padding: 8px; border-bottom: 1px solid #eee;'>Passenger {$passenger_num}</td>
                <td style='padding: 8px; border-bottom: 1px solid #eee;'>{$passenger['name']}</td>
                <td style='padding: 8px; border-bottom: 1px solid #eee;'>{$passenger['email']}</td>
            </tr>";
        }
        
        // Current date and time
        $booking_date = date('F j, Y \a\t H:i');
        
        // Prepare email subject
        $subject = "✈️ Flight Quote - {$booking_reference}";
        
        // Prepare HTML email body with modern design
        $email_body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Flight Quote</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; background: #f8fafc; }
                .container { max-width: 700px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; color: white; }
                .header h1 { font-size: 28px; margin-bottom: 10px; font-weight: 700; }
                .header p { opacity: 0.9; font-size: 16px; }
                .logo { max-width: 120px; margin-bottom: 15px; }
                .content { padding: 30px; }
                .section { margin-bottom: 25px; padding: 20px; background: #f8fafc; border-radius: 8px; border-left: 4px solid #667eea; }
                .section-title { color: #2d3748; margin-bottom: 15px; font-size: 18px; font-weight: 600; display: flex; align-items: center; }
                .section-title svg { margin-right: 10px; }
                .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
                .info-item { margin-bottom: 10px; }
                .info-label { font-weight: 600; color: #4a5568; font-size: 14px; }
                .info-value { color: #2d3748; font-size: 15px; }
                table { width: 100%; border-collapse: collapse; margin: 15px 0; }
                th { background: #667eea; color: white; padding: 12px; text-align: left; font-weight: 600; }
                td { padding: 12px; border-bottom: 1px solid #e2e8f0; }
                .status-badge { display: inline-block; padding: 6px 12px; background: #e6fffa; color: #065f46; border-radius: 20px; font-size: 12px; font-weight: 600; }
                .price-section { background: linear-gradient(135deg, #48bb78 0%, #38a169 100%); color: white; padding: 20px; border-radius: 8px; text-align: center; }
                .price-amount { font-size: 32px; font-weight: 700; margin-bottom: 5px; }
                .price-label { opacity: 0.9; font-size: 14px; }
                .actions { text-align: center; margin: 25px 0; }
                .btn { display: inline-block; padding: 12px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 6px; font-weight: 600; margin: 0 10px; }
                .btn-secondary { background: #718096; }
                .footer { background: #2d3748; color: white; padding: 25px; text-align: center; }
                .footer a { color: #a0aec0; text-decoration: none; }
                .footer a:hover { color: white; }
                .tracking-info { background: #e6fffa; border-left: 4px solid #38b2ac; padding: 15px; border-radius: 6px; margin: 20px 0; }
                @media (max-width: 600px) {
                    .info-grid { grid-template-columns: 1fr; }
                    .btn { display: block; margin: 10px 0; }
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    " . (!empty($website_logo) ? "<img src='{$website_logo}' alt='Travel Centre' class='logo'>" : "<h1>✈️ TRAVEL CENTRE</h1>") . "
                    <h1>Flight Quote</h1>
                    <p>Your flight quote has been generated</p>
                </div>
                
                <div class='content'>
                    <!-- Quote Summary -->
                    <div class='section'>
                        <div class='section-title'>
                            <svg width='20' height='20' viewBox='0 0 24 24' fill='currentColor'>
                                <path d='M17 3H7c-1.1 0-1.99.9-1.99 2L5 21l7-3 7 3V5c0-1.1-.9-2-2-2z'/>
                            </svg>
                            Quote Summary
                        </div>
                        <div class='info-grid'>
                            <div class='info-item'>
                                <div class='info-label'>Quote Reference</div>
                                <div class='info-value'><strong>{$booking_reference}</strong></div>
                            </div>
                            <div class='info-item'>
                                <div class='info-label'>Tracking ID</div>
                                <div class='info-value'><strong>{$tracking_id}</strong></div>
                            </div>
                            <div class='info-item'>
                                <div class='info-label'>Quote Date</div>
                                <div class='info-value'>{$booking_date}</div>
                            </div>
                            <div class='info-item'>
                                <div class='info-label'>Quote Type</div>
                                <div class='info-value'>Flight Quote</div>
                            </div>
                        </div>
                    </div>

                    <!-- Flight Details -->
                    <div class='section'>
                        <div class='section-title'>
                            <svg width='20' height='20' viewBox='0 0 24 24' fill='currentColor'>
                                <path d='M21 16v-2l-8-5V3.5c0-.83-.67-1.5-1.5-1.5S10 2.67 10 3.5V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5l8 2.5z'/>
                            </svg>
                            Flight Details
                        </div>
                        <div class='info-grid'>
                            <div class='info-item'>
                                <div class='info-label'>Route</div>
                                <div class='info-value'><strong>{$flight_route}</strong></div>
                            </div>
                            <div class='info-item'>
                                <div class='info-label'>Airline</div>
                                <div class='info-value'>{$airline_name}</div>
                            </div>
                            <div class='info-item'>
                                <div class='info-label'>Departure</div>
                                <div class='info-value'>{$departure_date_formatted} at {$departure_time}</div>
                            </div>
                            <div class='info-item'>
                                <div class='info-label'>Arrival</div>
                                <div class='info-value'>{$departure_date_formatted} at {$arrival_time}</div>
                            </div>
                            <div class='info-item'>
                                <div class='info-label'>Duration</div>
                                <div class='info-value'>{$duration}</div>
                            </div>
                            <div class='info-item'>
                                <div class='info-label'>Class</div>
                                <div class='info-value'>{$flight_class}</div>
                            </div>
                            <div class='info-item'>
                                <div class='info-label'>Passengers</div>
                                <div class='info-value'>{$passengers}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Passenger Details -->
                    <div class='section'>
                        <div class='section-title'>
                            <svg width='20' height='20' viewBox='0 0 24 24' fill='currentColor'>
                                <path d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/>
                            </svg>
                            Passenger Details ({$passenger_count} Passenger" . ($passenger_count > 1 ? 's' : '') . ")
                        </div>
                        <table>
                            <thead>
                                <tr>
                                    <th>Passenger</th>
                                    <th>Full Name</th>
                                    <th>Email</th>
                                </tr>
                            </thead>
                            <tbody>
                                {$passenger_details}
                            </tbody>
                        </table>
                    </div>

                    <!-- Contact Information -->
                    <div class='section'>
                        <div class='section-title'>
                            <svg width='20' height='20' viewBox='0 0 24 24' fill='currentColor'>
                                <path d='M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z'/>
                            </svg>
                            Contact Information
                        </div>
                        <div class='info-grid'>
                            <div class='info-item'>
                                <div class='info-label'>Email</div>
                                <div class='info-value'>{$contact_info['email']}</div>
                            </div>
                            <div class='info-item'>
                                <div class='info-label'>Phone</div>
                                <div class='info-value'>{$contact_info['phone']}</div>
                            </div>
                            <div class='info-item'>
                                <div class='info-label'>Address</div>
                                <div class='info-value'>{$contact_info['address']}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Price & Status -->
                    <div class='price-section'>
                        <div class='price-amount'>{$currency_symbol}{$total_amount} {$currency}</div>
                        <div class='price-label'>Total Amount</div>
                    </div>

                    <div class='tracking-info'>
                        <strong>📋 Quote Status:</strong> <span class='status-badge'>Flight Quote</span><br>
                        <strong>🚨 Next Step:</strong> Contact us to proceed with booking and payment
                    </div>

                    <!-- Important Information -->
                    <div class='section'>
                        <div class='section-title'>
                            <svg width='20' height='20' viewBox='0 0 24 24' fill='currentColor'>
                                <path d='M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z'/>
                            </svg>
                            Important Information
                        </div>
                        <ul style='padding-left: 20px; color: #4a5568;'>
                            <li style='margin-bottom: 8px;'>Please keep your <strong>Quote Reference ({$booking_reference})</strong> and <strong>Tracking ID ({$tracking_id})</strong> for future reference</li>
                            <li style='margin-bottom: 8px;'>This is a flight quote, not a confirmed booking</li>
                            <li style='margin-bottom: 8px;'>Contact us to proceed with booking and payment</li>
                            <li style='margin-bottom: 8px;'>For any inquiries, please contact our support team with your Quote Reference</li>
                        </ul>
                    </div>

                    <!-- Action Buttons -->
                    <div class='actions'>
                        <a href='{$website_url}/contact.php' class='btn' style='background: #48bb78;'>Contact Us to Book</a>
                        <a href='{$website_url}' class='btn btn-secondary'>Visit Our Website</a>
                    </div>
                </div>

                <div class='footer'>
                    <p>Thank you for considering Travel Centre!</p>
                    <p>For assistance, contact: {$website_email} | +234 903 407 2383</p>
                    <p><a href='{$website_url}'>View Online</a> | <a href='{$website_url}/contact.php'>Contact Support</a></p>
                    <p style='margin-top: 15px; font-size: 12px; color: #a0aec0;'>
                        This is an automated email. Please do not reply to this message.
                    </p>
                </div>
            </div>
        </body>
        </html>";

        // Prepare admin email (different subject)
        $admin_subject = "🚨 ADMIN: New Flight Quote - {$booking_reference}";
        $admin_body = str_replace("Flight Quote", "ADMIN NOTIFICATION - NEW FLIGHT QUOTE", $email_body);
        $admin_body = str_replace("Your flight quote has been generated", "New flight quote generated for " . $contact_info['email'], $admin_body);

        // Send emails
        $admin_sent = sendHTMLEmail($admin_email, $admin_subject, $admin_body);
        $user_sent = sendHTMLEmail($user_email, $subject, $email_body);
        
        // Log email sending results
        if (!$admin_sent) {
            error_log("Failed to send quote email to admin: {$admin_email}");
        }
        if (!$user_sent) {
            error_log("Failed to send quote email to user: {$user_email}");
        }
        
        return $admin_sent && $user_sent;
    }

    // Process flight invoice email sending - UPDATED with enhanced functionality
    if (isset($_POST['send_invoice_email'])) {
        header('Content-Type: application/json');
        
        try {
            $passenger_data = json_decode($_POST['passenger_data'], true);
            $invoice_html = $_POST['invoice_html'] ?? '';
            $flight_details = json_decode($_POST['flight_details'], true);
            
            // Validate required data
            if (empty($passenger_data) || empty($invoice_html) || empty($flight_details)) {
                echo json_encode(['success' => false, 'message' => 'Missing required data for email sending']);
                exit;
            }
            
            // NEW: Generate booking reference and tracking ID for the quote
            $booking_reference = 'QTE' . date('YmdHis') . strtoupper(generateRandomString(6));
            $tracking_id = 'TRK' . strtoupper(generateRandomString(10));
            
            // Calculate total amount
            $base_price = floatval($flight_details['price']);
            $total_amount = $base_price;
            $currency = 'NGN';
            
            // Prepare contact info from first passenger
            $contact_info = [
                'email' => $passenger_data[0]['email'],
                'phone' => $flight_details['contact_phone'] ?? 'Not provided',
                'address' => $flight_details['contact_address'] ?? 'Not provided'
            ];
            
            // NEW: Send enhanced booking emails with the same format as book-flight.php
            $email_sent = sendEnhancedBookingEmails(
                $pdo, 
                $flight_details['flight_data'], 
                $passenger_data, 
                $contact_info, 
                $booking_reference, 
                $tracking_id, 
                $total_amount, 
                $currency, 
                'quote', 
                $website_email, 
                $website_logo,
                $flight_details['origin'],
                $flight_details['destination'], 
                $flight_details['departure_date'],
                $flight_details['return_date'] ?? '',
                $flight_details['trip_type'],
                $flight_details['travel_class'],
                $flight_details['passengers']
            );
            
            if ($email_sent) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Flight quote sent successfully to ' . count($passenger_data) . ' passenger(s)',
                    'booking_reference' => $booking_reference,
                    'tracking_id' => $tracking_id
                ]);
            } else {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Failed to send email. Please check your email settings.'
                ]);
            }
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error sending email: ' . $e->getMessage()]);
        }
        exit;
    }

} catch (Exception $e) {
    error_log("Critical error in flights.php: " . $e->getMessage());
    $error = "A system error occurred. Please try again later.";
}

// Clear any unexpected output
ob_clean();

require_once 'includes/header.php';
?>

<div class="flights-page-wrapper">
    <!-- Hero Section with Search Form -->
    <section class="flight-hero-section">
        <div class="hero-background">
            <div class="hero-overlay"></div>
        </div>
        <div class="container">
            <!-- Authentication Messages -->
            <?php if ($auth_error): ?>
                <div class="auth-message auth-error">
                    <div class="auth-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                        </svg>
                    </div>
                    <div class="auth-text"><?php echo htmlspecialchars($auth_error); ?></div>
                    <button class="auth-close" onclick="this.parentElement.style.display='none'">&times;</button>
                </div>
            <?php endif; ?>
            
            <?php if ($auth_success): ?>
                <div class="auth-message auth-success">
                    <div class="auth-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                        </svg>
                    </div>
                    <div class="auth-text"><?php echo htmlspecialchars($auth_success); ?></div>
                    <button class="auth-close" onclick="this.parentElement.style.display='none'">&times;</button>
                </div>
            <?php endif; ?>

            <div class="hero-content">
                <h1 class="hero-title">Find Your Perfect Flight</h1>
                <p class="hero-subtitle">Compare prices from multiple airlines and book with confidence</p>
                
                <!-- Search Form -->
                <div class="search-form-modern">
                    <form method="GET" action="flights.php" class="modern-search-form">
                        <!-- Trip Type Toggle - UPDATED: One Way as default -->
                        <div class="form-section trip-type-section">
                            <div class="trip-type-toggle">
                                <input type="radio" name="trip_type" value="one_way" id="one_way" <?php echo $trip_type === 'one_way' ? 'checked' : ''; ?>>
                                <label for="one_way" class="trip-option">
                                    <span class="trip-icon">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M5 13h11.17l-4.88 4.88c-.39.39-.39 1.03 0 1.42.39.39 1.02.39 1.41 0l6.59-6.59c.39-.39.39-1.02 0-1.41l-6.58-6.6c-.39-.39-1.02-.39-1.41 0-.39.39-.39 1.02 0 1.41L16.17 11H5c-.55 0-1 .45-1 1s.45 1 1 1z"/>
                                        </svg>
                                    </span>
                                    One Way
                                </label>
                                
                                <input type="radio" name="trip_type" value="round_trip" id="round_trip" <?php echo $trip_type === 'round_trip' ? 'checked' : ''; ?>>
                                <label for="round_trip" class="trip-option">
                                    <span class="trip-icon">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/>
                                        </svg>
                                    </span>
                                    Round Trip
                                </label>
                            </div>
                        </div>

                        <!-- Location Inputs -->
                        <div class="form-section location-section">
                            <div class="location-inputs-grid">
                                <div class="location-input-group">
                                    <label class="input-label">From</label>
                                    <div class="input-with-icon">
                                        <span class="input-icon">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                                            </svg>
                                        </span>
                                        <input type="text" name="origin" id="origin" value="<?php echo htmlspecialchars($origin); ?>" 
                                               placeholder="City or airport" required class="modern-input location-input" autocomplete="off">
                                        <!-- Loading spinner for origin -->
                                        <div class="loading-spinner" id="originLoading" style="display: none;"></div>
                                    </div>
                                    <div id="originSuggestions" class="suggestions-dropdown"></div>
                                </div>
                                
                                <button type="button" class="swap-locations-btn" onclick="swapLocations()" aria-label="Swap locations">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M6.99 11L3 15l3.99 4v-3H14v-2H6.99v-3zM21 9l-3.99-4v3H10v2h7.01v3L21 9z"/>
                                    </svg>
                                </button>
                                
                                <div class="location-input-group">
                                    <label class="input-label">To</label>
                                    <div class="input-with-icon">
                                        <span class="input-icon">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M21 16v-2l-8-5V3.5c0-.83-.67-1.5-1.5-1.5S10 2.67 10 3.5V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5l8 2.5z"/>
                                            </svg>
                                        </span>
                                        <input type="text" name="destination" id="destination" value="<?php echo htmlspecialchars($destination); ?>" 
                                               placeholder="City or airport" required class="modern-input location-input" autocomplete="off">
                                        <!-- Loading spinner for destination -->
                                        <div class="loading-spinner" id="destinationLoading" style="display: none;"></div>
                                    </div>
                                    <div id="destinationSuggestions" class="suggestions-dropdown"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Date Inputs -->
                        <div class="form-section date-section">
                            <div class="date-inputs-grid">
                                <div class="date-input-group">
                                    <label class="input-label">Departure</label>
                                    <div class="input-with-icon">
                                        <span class="input-icon">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/>
                                            </svg>
                                        </span>
                                        <input type="date" name="departure_date" id="departureDate" value="<?php echo htmlspecialchars($departure_date); ?>" 
                                               required class="modern-input date-input" min="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                </div>
                                
                                <div class="date-input-group" id="returnDateGroup">
                                    <label class="input-label">Return</label>
                                    <div class="input-with-icon">
                                        <span class="input-icon">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/>
                                            </svg>
                                        </span>
                                        <input type="date" name="return_date" id="returnDate" value="<?php echo htmlspecialchars($return_date); ?>" 
                                               class="modern-input date-input" min="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Passenger & Class -->
                        <div class="form-section passenger-section">
                            <div class="passenger-class-grid">
                                <div class="passenger-input-group">
                                    <label class="input-label">Passengers</label>
                                    <div class="passenger-selector-modern">
                                        <button type="button" class="passenger-btn minus" onclick="adjustPassengers(-1)">−</button>
                                        <span class="passenger-count" id="passengerCount"><?php echo $passengers; ?></span>
                                        <button type="button" class="passenger-btn plus" onclick="adjustPassengers(1)">+</button>
                                    </div>
                                    <input type="hidden" name="passengers" id="passengers" value="<?php echo $passengers; ?>">
                                </div>
                                
                                <div class="class-input-group">
                                    <label class="input-label">Class</label>
                                    <select name="travel_class" class="modern-select class-select">
                                        <option value="ECONOMY" <?php echo $travel_class == 'ECONOMY' ? 'selected' : ''; ?>>Economy</option>
                                        <option value="PREMIUM_ECONOMY" <?php echo $travel_class == 'PREMIUM_ECONOMY' ? 'selected' : ''; ?>>Premium Economy</option>
                                        <option value="BUSINESS" <?php echo $travel_class == 'BUSINESS' ? 'selected' : ''; ?>>Business</option>
                                        <option value="FIRST" <?php echo $travel_class == 'FIRST' ? 'selected' : ''; ?>>First Class</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-actions-modern">
                            <button type="submit" class="search-btn-primary">
                                <span class="btn-icon">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                                    </svg>
                                </span>
                                Search Flights
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Quick Search Routes -->
                <div class="quick-routes-section">
                    <h3 class="quick-routes-title">Popular Routes</h3>
                    <div class="quick-routes-grid">
                        <?php
                        $quick_routes = [];
                        if (function_exists('getQuickSearchRoutes')) {
                            $quick_routes = getQuickSearchRoutes();
                        } else {
                            // Default routes if function doesn't exist
                            $quick_routes = [
                                ['from' => 'LOS', 'to' => 'ABV', 'label' => 'Lagos → Abuja'],
                                ['from' => 'LOS', 'to' => 'PHC', 'label' => 'Lagos → Port Harcourt'],
                                ['from' => 'LOS', 'to' => 'KAN', 'label' => 'Lagos → Kano'],
                                ['from' => 'ABV', 'to' => 'PHC', 'label' => 'Abuja → Port Harcourt']
                            ];
                        }
                        
                        $next_week = date('Y-m-d', strtotime('+7 days'));
                        $two_weeks = date('Y-m-d', strtotime('+14 days'));
                        
                        foreach ($quick_routes as $route) {
                            $url = "flights.php?origin={$route['from']}&destination={$route['to']}&departure_date=$next_week&return_date=$two_weeks&trip_type=round_trip";
                            echo "<a href='$url' class='quick-route-card'>
                                    <span class='route-arrow'>
                                        <svg width=\"16\" height=\"16\" viewBox=\"0 0 24 24\" fill=\"currentColor\">
                                            <path d=\"M5 13h11.17l-4.88 4.88c-.39.39-.39 1.03 0 1.42.39.39 1.02.39 1.41 0l6.59-6.59c.39-.39.39-1.02 0-1.41l-6.58-6.6c-.39-.39-1.02-.39-1.41 0-.39.39-.39 1.02 0 1.41L16.17 11H5c-.55 0-1 .45-1 1s.45 1 1 1z\"/>
                                        </svg>
                                    </span>
                                    <span class='route-text'>{$route['label']}</span>
                                  </a>";
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php if ($error): ?>
        <section class="error-section">
            <div class="container">
                <div class="error-card">
                    <div class="error-icon">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                        </svg>
                    </div>
                    <div class="error-content">
                        <h3 class="error-title">Search Error</h3>
                        <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
                        <div class="error-tips">
                            <strong>Try these routes:</strong> <span class="route-suggestion">LOS→ABV</span> or <span class="route-suggestion">LOS→LHR</span> with future dates
                        </div>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- Flight Results Section -->
    <?php if (!empty($flights) || ($origin && $destination && $departure_date)): ?>
    <section class="flight-results-section">
        <div class="container">
            <div class="results-layout">
                <!-- Filter Sidebar -->
                <?php if ($filter_enabled && !empty($flights)): ?>
                <aside class="filter-sidebar">
                    <div class="filter-header">
                        <h3 class="filter-title">Filters</h3>
                        <button type="button" class="clear-filters-btn" onclick="clearAllFilters()">
                            Clear All
                        </button>
                    </div>
                    
                    <form method="GET" id="filter-form" class="filter-form">
                        <!-- Hidden search parameters -->
                        <input type="hidden" name="origin" value="<?php echo htmlspecialchars($origin); ?>">
                        <input type="hidden" name="destination" value="<?php echo htmlspecialchars($destination); ?>">
                        <input type="hidden" name="departure_date" value="<?php echo htmlspecialchars($departure_date); ?>">
                        <input type="hidden" name="return_date" value="<?php echo htmlspecialchars($return_date); ?>">
                        <input type="hidden" name="passengers" value="<?php echo $passengers; ?>">
                        <input type="hidden" name="travel_class" value="<?php echo htmlspecialchars($travel_class); ?>">
                        <input type="hidden" name="trip_type" value="<?php echo htmlspecialchars($trip_type); ?>">
                        
                        <!-- Price Filter -->
                        <div class="filter-group">
                            <h4 class="filter-group-title">Price Range (₦)</h4>
                            <div class="price-inputs-modern">
                                <div class="price-input-group">
                                    <input type="number" name="min_price" id="minPrice" placeholder="Min" 
                                           value="<?php echo $min_price; ?>" class="price-input-modern">
                                </div>
                                <div class="price-input-group">
                                    <input type="number" name="max_price" id="maxPrice" placeholder="Max" 
                                           value="<?php echo $max_price; ?>" class="price-input-modern">
                                </div>
                            </div>
                            <div class="price-slider-container">
                                <input type="range" id="priceSlider" min="0" max="1000000" step="1000" 
                                       value="<?php echo min($max_price, 1000000); ?>" class="price-slider-modern">
                            </div>
                        </div>
                        
                        <!-- Stops Filter -->
                        <div class="filter-group">
                            <h4 class="filter-group-title">Stops</h4>
                            <div class="filter-options">
                                <label class="filter-option">
                                    <input type="radio" name="max_stops" value="-1" 
                                           <?php echo $max_stops == -1 ? 'checked' : ''; ?> onchange="this.form.submit()">
                                    <span class="checkmark"></span>
                                    <span class="option-label">Any Stops</span>
                                </label>
                                <label class="filter-option">
                                    <input type="radio" name="max_stops" value="0" 
                                           <?php echo $max_stops == 0 ? 'checked' : ''; ?> onchange="this.form.submit()">
                                    <span class="checkmark"></span>
                                    <span class="option-label">Non-stop</span>
                                </label>
                                <label class="filter-option">
                                    <input type="radio" name="max_stops" value="1" 
                                           <?php echo $max_stops == 1 ? 'checked' : ''; ?> onchange="this.form.submit()">
                                    <span class="checkmark"></span>
                                    <span class="option-label">1 Stop max</span>
                                </label>
                                <label class="filter-option">
                                    <input type="radio" name="max_stops" value="2" 
                                           <?php echo $max_stops == 2 ? 'checked' : ''; ?> onchange="this.form.submit()">
                                    <span class="checkmark"></span>
                                    <span class="option-label">2 Stops max</span>
                                </label>
                            </div>
                        </div>
                        
                        <!-- Airlines Filter -->
                        <?php 
                        $airlines = [];
                        foreach ($flights as $flight) {
                            if (isset($flight['itineraries'][0]['segments'][0])) {
                                $itinerary = $flight['itineraries'][0];
                                $first_segment = $itinerary['segments'][0];
                                $airline_code = $first_segment['carrierCode'];
                                if (!isset($airlines[$airline_code])) {
                                    $airline_name = '';
                                    if (function_exists('getAirlineNameFromAmadeus')) {
                                        $airline_name = getAirlineNameFromAmadeus($airline_code);
                                    } else {
                                        $airline_name = $airline_code; // Fallback to code if function doesn't exist
                                    }
                                    $airlines[$airline_code] = [
                                        'code' => $airline_code,
                                        'name' => $airline_name,
                                        'count' => 0
                                    ];
                                }
                                $airlines[$airline_code]['count']++;
                            }
                        }
                        ?>
                        <?php if (!empty($airlines)): ?>
                        <div class="filter-group">
                            <h4 class="filter-group-title">Airlines</h4>
                            <div class="filter-options">
                                <?php foreach ($airlines as $airline): ?>
                                <label class="filter-option">
                                    <input type="checkbox" name="airlines[]" value="<?php echo $airline['code']; ?>" 
                                           <?php echo in_array($airline['code'], $selected_airlines) ? 'checked' : ''; ?> 
                                           onchange="this.form.submit()">
                                    <span class="checkmark"></span>
                                    <span class="option-label"><?php echo htmlspecialchars($airline['name']); ?></span>
                                    <span class="option-count">(<?php echo $airline['count']; ?>)</span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Departure Time Filter -->
                        <div class="filter-group">
                            <h4 class="filter-group-title">Departure Time</h4>
                            <div class="filter-options">
                                <label class="filter-option">
                                    <input type="radio" name="departure_time" value="" 
                                           <?php echo empty($departure_time) ? 'checked' : ''; ?> onchange="this.form.submit()">
                                    <span class="checkmark"></span>
                                    <span class="option-label">Any Time</span>
                                </label>
                                <label class="filter-option">
                                    <input type="radio" name="departure_time" value="morning" 
                                           <?php echo $departure_time == 'morning' ? 'checked' : ''; ?> onchange="this.form.submit()">
                                    <span class="checkmark"></span>
                                    <span class="option-label">Morning (6AM - 12PM)</span>
                                </label>
                                <label class="filter-option">
                                    <input type="radio" name="departure_time" value="afternoon" 
                                           <?php echo $departure_time == 'afternoon' ? 'checked' : ''; ?> onchange="this.form.submit()">
                                    <span class="checkmark"></span>
                                    <span class="option-label">Afternoon (12PM - 6PM)</span>
                                </label>
                                <label class="filter-option">
                                    <input type="radio" name="departure_time" value="evening" 
                                           <?php echo $departure_time == 'evening' ? 'checked' : ''; ?> onchange="this.form.submit()">
                                    <span class="checkmark"></span>
                                    <span class="option-label">Evening (6PM+)</span>
                                </label>
                            </div>
                        </div>
                    </form>
                </aside>
                <?php endif; ?>
                
                <!-- Main Results -->
                <main class="results-main-content">
                    <?php if (!empty($flights)): ?>
                        <div class="results-header-modern">
                            <div class="results-info">
                                <h2 class="results-title"><?php echo count($flights); ?> Flights Found</h2>
                                <p class="results-route">
                                    <?php echo htmlspecialchars($origin); ?> → <?php echo htmlspecialchars($destination); ?> • 
                                    <?php echo date('M j, Y', strtotime($departure_date)); ?>
                                    <?php if ($return_date): ?>
                                         - <?php echo date('M j, Y', strtotime($return_date)); ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="results-sort">
                                <label class="sort-label">Sort by:</label>
                                <select class="sort-select" onchange="sortFlights(this.value)">
                                    <option value="price_asc">Price (Low to High)</option>
                                    <option value="price_desc">Price (High to Low)</option>
                                    <option value="duration_asc">Duration (Shortest)</option>
                                    <option value="departure_asc">Departure (Earliest)</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="flight-cards-container">
                            <?php foreach ($flights as $flight): ?>
                                <div class="flight-card-modern" data-flight-id="<?php echo $flight['id'] ?? ''; ?>">
                                    <?php
                                    $itinerary = $flight['itineraries'][0] ?? [];
                                    $first_segment = $itinerary['segments'][0] ?? [];
                                    $last_segment = end($itinerary['segments']) ?? [];
                                    $airline_code = $first_segment['carrierCode'] ?? '';
                                    
                                    $airline_logo = '';
                                    $airline_name = '';
                                    if (function_exists('getAirlineLogoFromAmadeus')) {
                                        $airline_logo = getAirlineLogoFromAmadeus($airline_code);
                                    }
                                    if (function_exists('getAirlineNameFromAmadeus')) {
                                        $airline_name = getAirlineNameFromAmadeus($airline_code);
                                    } else {
                                        $airline_name = $airline_code;
                                    }
                                    
                                    // Calculate price with proper conversion
                                    $base_price = floatval($flight['price']['grandTotal'] ?? 0);
                                    $converted_price = $base_price * $conversion_rate;
                                    $final_price = round($converted_price, 2);
                                    
                                    // Get flight times
                                    $departure_time_display = '';
                                    $arrival_time_display = '';
                                    if (!empty($first_segment['departure']['at'])) {
                                        $departure_time_display = date('g:i A', strtotime($first_segment['departure']['at']));
                                    }
                                    if (!empty($last_segment['arrival']['at'])) {
                                        $arrival_time_display = date('g:i A', strtotime($last_segment['arrival']['at']));
                                    }
                                    
                                    // For round trips, get return flight times if available
                                    $return_departure_time = '';
                                    $return_arrival_time = '';
                                    if ($trip_type === 'round_trip' && isset($flight['itineraries'][1])) {
                                        $return_itinerary = $flight['itineraries'][1];
                                        $return_first_segment = $return_itinerary['segments'][0] ?? [];
                                        $return_last_segment = end($return_itinerary['segments']) ?? [];
                                        if (!empty($return_first_segment['departure']['at'])) {
                                            $return_departure_time = date('g:i A', strtotime($return_first_segment['departure']['at']));
                                        }
                                        if (!empty($return_last_segment['arrival']['at'])) {
                                            $return_arrival_time = date('g:i A', strtotime($return_last_segment['arrival']['at']));
                                        }
                                    }
                                    
                                    $travel_class_name = '';
                                    if (function_exists('getTravelClassName')) {
                                        $travel_class_name = getTravelClassName($travel_class);
                                    } else {
                                        $travel_class_name = ucfirst(strtolower(str_replace('_', ' ', $travel_class)));
                                    }
                                    
                                    $flight_duration = '';
                                    if (function_exists('formatFlightDuration') && isset($itinerary['duration'])) {
                                        $flight_duration = formatFlightDuration($itinerary['duration']);
                                    } else if (isset($itinerary['duration'])) {
                                        $flight_duration = $itinerary['duration'];
                                    }
                                    ?>
                                    
                                    <div class="flight-card-header">
                                        <div class="airline-info-modern">
                                            <img src="<?php echo $airline_logo; ?>" alt="<?php echo htmlspecialchars($airline_name); ?>" class="airline-logo-modern" onerror="this.src='https://images.kiwi.com/airlines/64/<?php echo $airline_code; ?>.png'">
                                            <div class="airline-details-modern">
                                                <h3 class="flight-route"><?php echo $first_segment['departure']['iataCode'] ?? ''; ?> → <?php echo $last_segment['arrival']['iataCode'] ?? ''; ?></h3>
                                                <p class="airline-name-modern"><?php echo htmlspecialchars($airline_name); ?></p>
                                            </div>
                                        </div>
                                        <div class="flight-price-modern">
                                            <span class="price-amount">₦<?php echo number_format($final_price, 2); ?></span>
                                            <span class="price-per-person">per person</span>
                                        </div>
                                    </div>
                                    
                                    <div class="flight-card-body">
                                        <div class="flight-timeline">
                                            <div class="time-point departure">
                                                <span class="time"><?php echo $departure_time_display; ?></span>
                                                <span class="airport"><?php echo $first_segment['departure']['iataCode'] ?? ''; ?></span>
                                            </div>
                                            
                                            <div class="flight-duration">
                                                <div class="duration-line">
                                                    <div class="line"></div>
                                                    <div class="duration-info">
                                                        <span class="duration-text"><?php echo $flight_duration; ?></span>
                                                        <span class="stops-info"><?php echo count($itinerary['segments'] ?? []) - 1; ?> stop<?php echo (count($itinerary['segments'] ?? []) > 2) ? 's' : ''; ?></span>
                                                    </div>
                                                    <div class="line"></div>
                                                </div>
                                            </div>
                                            
                                            <div class="time-point arrival">
                                                <span class="time"><?php echo $arrival_time_display; ?></span>
                                                <span class="airport"><?php echo $last_segment['arrival']['iataCode'] ?? ''; ?></span>
                                            </div>
                                        </div>
                                        
                                        <div class="flight-meta-modern">
                                            <span class="meta-badge"><?php echo $travel_class_name; ?></span>
                                            <span class="meta-badge">Flight: <?php echo $airline_code . ($first_segment['number'] ?? ''); ?></span>
                                            <!-- UPDATED: Show flight times -->
                                            <span class="meta-badge time-badge">Departure: <?php echo $departure_time_display; ?></span>
                                            <span class="meta-badge time-badge">Arrival: <?php echo $arrival_time_display; ?></span>
                                            <?php if ($return_departure_time): ?>
                                                <span class="meta-badge time-badge">Return: <?php echo $return_departure_time; ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="flight-card-footer">
                                        <div class="flight-actions-modern">
                                            <!-- UPDATED: Compact button layout -->
                                            <div class="action-buttons-compact">
                                                <button type="button" class="btn-details-compact" onclick="toggleFlightDetails('<?php echo $flight['id'] ?? ''; ?>')">
                                                    <span class="btn-icon-compact">
                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                                                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/>
                                                        </svg>
                                                    </span>
                                                    <span class="btn-text-compact">Details</span>
                                                </button>
                                                
                                                <!-- Book Now Button - UPDATED: Post to clean URL to avoid .php redirect losing POST -->
                                                <form method="POST" action="book-flight" class="book-form-compact">
                                                    <input type="hidden" name="flight_data" value="<?php echo htmlspecialchars(json_encode($flight)); ?>">
                                                    <input type="hidden" name="passengers" value="<?php echo $passengers; ?>">
                                                    <input type="hidden" name="travel_class" value="<?php echo htmlspecialchars($travel_class); ?>">
                                                    <input type="hidden" name="conversion_rate" value="<?php echo $conversion_rate; ?>">
                                                    <input type="hidden" name="origin" value="<?php echo htmlspecialchars($origin); ?>">
                                                    <input type="hidden" name="destination" value="<?php echo htmlspecialchars($destination); ?>">
                                                    <input type="hidden" name="departure_date" value="<?php echo htmlspecialchars($departure_date); ?>">
                                                    <input type="hidden" name="return_date" value="<?php echo htmlspecialchars($return_date); ?>">
                                                    <input type="hidden" name="trip_type" value="<?php echo htmlspecialchars($trip_type); ?>">
                                                    <!-- UPDATED: Added flight time parameters -->
                                                    <input type="hidden" name="departure_time" value="<?php echo $departure_time_display; ?>">
                                                    <input type="hidden" name="arrival_time" value="<?php echo $arrival_time_display; ?>">
                                                    <input type="hidden" name="return_departure_time" value="<?php echo $return_departure_time; ?>">
                                                    <input type="hidden" name="return_arrival_time" value="<?php echo $return_arrival_time; ?>">
                                                    <button type="submit" class="btn-book-now-compact">
                                                        <span class="btn-icon-compact">
                                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                                                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                                            </svg>
                                                        </span>
                                                        <span class="btn-text-compact">Book</span>
                                                    </button>
                                                </form>
                                                
                                                <button type="button" class="btn-share-compact" 
                                                    onclick="openShareModal(
                                                        <?php echo htmlspecialchars(json_encode($flight), ENT_QUOTES, 'UTF-8'); ?>,
                                                        <?php echo $passengers; ?>,
                                                        '<?php echo $travel_class; ?>',
                                                        <?php echo $final_price; ?>,
                                                        '<?php echo $origin; ?>',
                                                        '<?php echo $destination; ?>',
                                                        '<?php echo $departure_date; ?>',
                                                        '<?php echo $return_date; ?>',
                                                        '<?php echo $trip_type; ?>',
                                                        '<?php echo $airline_code; ?>',
                                                        '<?php echo addslashes($airline_name); ?>',
                                                        '<?php echo $departure_time_display; ?>',
                                                        '<?php echo $arrival_time_display; ?>',
                                                        '<?php echo $return_departure_time; ?>',
                                                        '<?php echo $return_arrival_time; ?>'
                                                    )">
                                                    <span class="btn-icon-compact">
                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                                                            <path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92 1.61 0 2.92-1.31 2.92-2.92s-1.31-2.92-2.92-2.92z"/>
                                                        </svg>
                                                    </span>
                                                    <span class="btn-text-compact">Share</span>
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <!-- Flight Details Expandable -->
                                        <div id="flight-details-<?php echo $flight['id'] ?? ''; ?>" class="flight-details-expanded">
                                            <!-- Outbound Flight -->
                                            <div class="flight-itinerary-section">
                                                <h4 class="itinerary-title">Outbound Flight</h4>
                                                <?php if (!empty($itinerary['segments'])): ?>
                                                    <?php foreach ($itinerary['segments'] as $index => $segment): ?>
                                                        <div class="flight-segment">
                                                            <div class="segment-info">
                                                                <div class="segment-airport departure">
                                                                    <strong class="airport-code"><?php echo $segment['departure']['iataCode'] ?? ''; ?></strong>
                                                                    <span class="segment-time"><?php echo !empty($segment['departure']['at']) ? date('g:i A', strtotime($segment['departure']['at'])) : ''; ?></span>
                                                                    <span class="segment-date"><?php echo !empty($segment['departure']['at']) ? date('M j', strtotime($segment['departure']['at'])) : ''; ?></span>
                                                                </div>
                                                                
                                                                <div class="segment-duration">
                                                                    <div class="duration-display">
                                                                        <span class="duration">
                                                                            <?php 
                                                                            if (function_exists('formatFlightDuration') && isset($segment['duration'])) {
                                                                                echo formatFlightDuration($segment['duration']);
                                                                            } else if (isset($segment['duration'])) {
                                                                                echo $segment['duration'];
                                                                            }
                                                                            ?>
                                                                        </span>
                                                                        <div class="flight-line">
                                                                            <div class="line"></div>
                                                                            <div class="plane-icon">
                                                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                                                                    <path d="M21 16v-2l-8-5V3.5c0-.83-.67-1.5-1.5-1.5S10 2.67 10 3.5V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5l8 2.5z"/>
                                                                                </svg>
                                                                            </div>
                                                                            <div class="line"></div>
                                                                        </div>
                                                                    </div>
                                                                    <small class="flight-number">
                                                                        <?php echo ($segment['carrierCode'] ?? '') . ($segment['number'] ?? ''); ?> • <?php echo $segment['aircraft']['code'] ?? 'N/A'; ?>
                                                                    </small>
                                                                </div>
                                                                
                                                                <div class="segment-airport arrival">
                                                                    <strong class="airport-code"><?php echo $segment['arrival']['iataCode'] ?? ''; ?></strong>
                                                                    <span class="segment-time"><?php echo !empty($segment['arrival']['at']) ? date('g:i A', strtotime($segment['arrival']['at'])) : ''; ?></span>
                                                                    <span class="segment-date"><?php echo !empty($segment['arrival']['at']) ? date('M j', strtotime($segment['arrival']['at'])) : ''; ?></span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <?php if ($index < count($itinerary['segments']) - 1): ?>
                                                            <div class="layover-info">
                                                                <span class="layover-icon">
                                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                                                        <path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/>
                                                                    </svg>
                                                                </span>
                                                                <span class="layover-text">
                                                                    Layover: <?php 
                                                                        if (!empty($itinerary['segments'][$index + 1]['departure']['at']) && !empty($segment['arrival']['at'])) {
                                                                            $next_departure = strtotime($itinerary['segments'][$index + 1]['departure']['at']);
                                                                            $current_arrival = strtotime($segment['arrival']['at']);
                                                                            $layover = $next_departure - $current_arrival;
                                                                            $hours = floor($layover / 3600);
                                                                            $minutes = floor(($layover % 3600) / 60);
                                                                            echo $hours . 'h ' . $minutes . 'm';
                                                                        } else {
                                                                            echo 'N/A';
                                                                        }
                                                                    ?>
                                                                </span>
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- Return Flight for Round Trips -->
                                            <?php if ($trip_type === 'round_trip' && isset($flight['itineraries'][1])): ?>
                                                <?php $return_itinerary = $flight['itineraries'][1]; ?>
                                                <div class="flight-itinerary-section">
                                                    <h4 class="itinerary-title">Return Flight</h4>
                                                    <?php if (!empty($return_itinerary['segments'])): ?>
                                                        <?php foreach ($return_itinerary['segments'] as $index => $segment): ?>
                                                            <div class="flight-segment">
                                                                <div class="segment-info">
                                                                    <div class="segment-airport departure">
                                                                        <strong class="airport-code"><?php echo $segment['departure']['iataCode'] ?? ''; ?></strong>
                                                                        <span class="segment-time"><?php echo !empty($segment['departure']['at']) ? date('g:i A', strtotime($segment['departure']['at'])) : ''; ?></span>
                                                                        <span class="segment-date"><?php echo !empty($segment['departure']['at']) ? date('M j', strtotime($segment['departure']['at'])) : ''; ?></span>
                                                                    </div>
                                                                    
                                                                    <div class="segment-duration">
                                                                        <div class="duration-display">
                                                                            <span class="duration">
                                                                                <?php 
                                                                                if (function_exists('formatFlightDuration') && isset($segment['duration'])) {
                                                                                    echo formatFlightDuration($segment['duration']);
                                                                                } else if (isset($segment['duration'])) {
                                                                                    echo $segment['duration'];
                                                                                }
                                                                                ?>
                                                                            </span>
                                                                            <div class="flight-line">
                                                                                <div class="line"></div>
                                                                                <div class="plane-icon">
                                                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                                                                        <path d="M21 16v-2l-8-5V3.5c0-.83-.67-1.5-1.5-1.5S10 2.67 10 3.5V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5l8 2.5z"/>
                                                                                    </svg>
                                                                                </div>
                                                                                <div class="line"></div>
                                                                            </div>
                                                                        </div>
                                                                        <small class="flight-number">
                                                                            <?php echo ($segment['carrierCode'] ?? '') . ($segment['number'] ?? ''); ?> • <?php echo $segment['aircraft']['code'] ?? 'N/A'; ?>
                                                                        </small>
                                                                    </div>
                                                                    
                                                                    <div class="segment-airport arrival">
                                                                        <strong class="airport-code"><?php echo $segment['arrival']['iataCode'] ?? ''; ?></strong>
                                                                        <span class="segment-time"><?php echo !empty($segment['arrival']['at']) ? date('g:i A', strtotime($segment['arrival']['at'])) : ''; ?></span>
                                                                        <span class="segment-date"><?php echo !empty($segment['arrival']['at']) ? date('M j', strtotime($segment['arrival']['at'])) : ''; ?></span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            
                                                            <?php if ($index < count($return_itinerary['segments']) - 1): ?>
                                                                <div class="layover-info">
                                                                    <span class="layover-icon">
                                                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                                                            <path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/>
                                                                        </svg>
                                                                    </span>
                                                                    <span class="layover-text">
                                                                        Layover: <?php 
                                                                            if (!empty($return_itinerary['segments'][$index + 1]['departure']['at']) && !empty($segment['arrival']['at'])) {
                                                                                $next_departure = strtotime($return_itinerary['segments'][$index + 1]['departure']['at']);
                                                                                $current_arrival = strtotime($segment['arrival']['at']);
                                                                                $layover = $next_departure - $current_arrival;
                                                                                $hours = floor($layover / 3600);
                                                                                $minutes = floor(($layover % 3600) / 60);
                                                                                echo $hours . 'h ' . $minutes . 'm';
                                                                            } else {
                                                                                echo 'N/A';
                                                                            }
                                                                        ?>
                                                                    </span>
                                                                </div>
                                                            <?php endif; ?>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ($origin && $destination && $departure_date && empty($error)): ?>
                        <div class="no-results-modern">
                            <div class="no-results-icon">
                                <svg width="60" height="60" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M21 16v-2l-8-5V3.5c0-.83-.67-1.5-1.5-1.5S10 2.67 10 3.5V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5l8 2.5z"/>
                                </svg>
                            </div>
                            <h3 class="no-results-title">No flights found</h3>
                            <p class="no-results-message">Try adjusting your search criteria, dates, or try different routes.</p>
                            <div class="no-results-actions">
                                <button onclick="history.back()" class="btn-secondary">Modify Search</button>
                                <a href="flights.php?origin=LOS&destination=ABV&departure_date=<?php echo date('Y-m-d', strtotime('+7 days')); ?>" class="btn-primary">Try LOS→ABV</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </main>
                
                <!-- Right Sidebar -->
                <aside class="results-sidebar">
                    <!-- Special Offers -->
                    <?php if ($special_offer['enabled']): ?>
                    <div class="special-offer-card">
                        <div class="offer-badge">Special Offer</div>
                        <div class="offer-content">
                            <?php if (!empty($special_offer['image'])): ?>
                                <div class="offer-image">
                                    <img src="<?php echo $special_offer['image']; ?>" alt="<?php echo htmlspecialchars($special_offer['title']); ?>" class="offer-img">
                                </div>
                            <?php endif; ?>
                            
                            <div class="offer-details">
                                <h4 class="offer-title"><?php echo htmlspecialchars($special_offer['title']); ?></h4>
                                <p class="offer-description"><?php echo htmlspecialchars($special_offer['description']); ?></p>
                                
                                <div class="offer-highlight">
                                    <span class="discount-tag"><?php echo htmlspecialchars($special_offer['discount']); ?> OFF</span>
                                </div>
                                
                                <?php if (!empty($special_offer['valid_until'])): ?>
                                    <div class="offer-validity">
                                        <span class="valid-until">Valid until: <?php echo date('M j, Y', strtotime($special_offer['valid_until'])); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="offer-actions">
                            <button class="btn-offer" onclick="applySpecialOffer()">
                                Apply Offer
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Ad Panel -->
                    <?php if ($ad_panel_enabled): ?>
                    <div class="ad-panel-modern">
                        <?php echo html_entity_decode($ad_panel_content); ?>
                    </div>
                    <?php endif; ?>
                </aside>
            </div>
        </div>
    </section>
    <?php endif; ?>
</div>

<!-- Share Invoice Modal -->
<div id="shareReceiptModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <h3 class="modal-title">Generate Flight Quote</h3>
            <button type="button" class="modal-close" onclick="closeShareModal()">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                </svg>
            </button>
        </div>
        
        <div class="modal-body">
            <form id="passengerForm" class="passenger-form-modern">
                <div class="form-section">
                    <h4 class="section-title">Passenger Information</h4>
                    <p class="section-description">Enter passenger details to generate and send flight quote via email</p>
                    
                    <!-- Contact Information -->
                    <div class="form-section">
                        <h4 class="section-title">Contact Information</h4>
                        <div class="passenger-field-row">
                            <div class="passenger-name-field">
                                <label class="input-label">Contact Phone *</label>
                                <input type="tel" class="modern-input contact-phone" 
                                       placeholder="+234 XXX XXX XXXX" required 
                                       name="contact_phone">
                            </div>
                            <div class="passenger-email-field">
                                <label class="input-label">Contact Address *</label>
                                <input type="text" class="modern-input contact-address" 
                                       placeholder="Your complete address" required 
                                       name="contact_address">
                            </div>
                        </div>
                    </div>
                    
                    <div id="passengerFields" class="passenger-fields-modern">
                        <!-- Passenger fields will be dynamically added here -->
                    </div>
                </div>
                
                <div class="form-actions-modal">
                    <button type="button" class="btn-modal-secondary" onclick="closeShareModal()">Cancel</button>
                    <button type="button" class="btn-modal-primary" onclick="generateInvoice()">Generate Quote</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Invoice Preview Modal -->
<div id="receiptPreviewModal" class="modal-overlay">
    <div class="modal-container receipt-modal">
        <div class="modal-header">
            <h3 class="modal-title">Flight Quote</h3>
            <div class="email-status" id="emailStatus" style="display: none;">
                <span class="status-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                    </svg>
                </span>
                <span class="status-text" id="emailStatusText"></span>
            </div>
            <button type="button" class="modal-close" onclick="closeReceiptPreview()">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                </svg>
            </button>
        </div>
        
        <div class="modal-body">
            <div id="receiptContent" class="receipt-content-modern">
                <!-- Invoice content will be generated here -->
            </div>
            
            <div class="receipt-actions-modern">
                <button type="button" class="btn-modal-secondary" onclick="closeReceiptPreview()">Close</button>
                <button type="button" class="btn-modal-primary" onclick="printInvoice()">Print Quote</button>
                <button type="button" class="btn-modal-info" onclick="shareInvoice()">Share</button>
            </div>
        </div>
    </div>
</div>

<script>
// Trip type toggle
function toggleReturnDate() {
    const tripType = document.querySelector('input[name="trip_type"]:checked').value;
    const returnDateGroup = document.getElementById('returnDateGroup');
    
    if (tripType === 'one_way') {
        returnDateGroup.style.display = 'none';
        document.getElementById('returnDate').value = '';
    } else {
        returnDateGroup.style.display = 'block';
    }
}

// Initialize trip type functionality
function initTripType() {
    const tripTypeInputs = document.querySelectorAll('input[name="trip_type"]');
    tripTypeInputs.forEach(input => {
        input.addEventListener('change', toggleReturnDate);
    });
    toggleReturnDate(); // Initial call
}

// Passenger counter
function adjustPassengers(change) {
    const countElement = document.getElementById('passengerCount');
    const hiddenInput = document.getElementById('passengers');
    let currentCount = parseInt(countElement.textContent);
    let newCount = currentCount + change;
    
    if (newCount >= 1 && newCount <= 9) {
        countElement.textContent = newCount;
        hiddenInput.value = newCount;
        
        // Add animation
        countElement.style.transform = 'scale(1.2)';
        setTimeout(() => {
            countElement.style.transform = 'scale(1)';
        }, 200);
    }
}

// Swap locations
function swapLocations() {
    const originInput = document.getElementById('origin');
    const destinationInput = document.getElementById('destination');
    const temp = originInput.value;
    originInput.value = destinationInput.value;
    destinationInput.value = temp;
    
    // Add animation to swap button
    const swapBtn = document.querySelector('.swap-locations-btn');
    swapBtn.style.transform = 'rotate(180deg)';
    setTimeout(() => {
        swapBtn.style.transform = 'rotate(0)';
    }, 300);
}

// Airport search functionality using Amadeus API - UPDATED with loading spinner
function initAirportSearch(fieldId) {
    const input = document.getElementById(fieldId);
    const suggestions = document.getElementById(fieldId + 'Suggestions');
    const loadingSpinner = document.getElementById(fieldId + 'Loading');
    
    if (!input || !suggestions || !loadingSpinner) return;
    
    let debounceTimer;
    
    input.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        const query = this.value.trim();
        
        if (query.length >= 2) {
            // Show loading spinner
            loadingSpinner.style.display = 'block';
            debounceTimer = setTimeout(() => {
                fetch(`flights.php?search_airports=1&query=${encodeURIComponent(query)}`)
                    .then(response => response.json())
                    .then(data => {
                        showSuggestions(fieldId, data);
                        // Hide loading spinner
                        loadingSpinner.style.display = 'none';
                    })
                    .catch(error => {
                        console.error('Error fetching airport suggestions:', error);
                        suggestions.style.display = 'none';
                        // Hide loading spinner on error too
                        loadingSpinner.style.display = 'none';
                    });
            }, 300);
        } else {
            suggestions.style.display = 'none';
            // Also hide loading spinner if query is too short
            loadingSpinner.style.display = 'none';
        }
    });
    
    // Hide suggestions when clicking outside
    document.addEventListener('click', function(e) {
        if (!input.contains(e.target) && !suggestions.contains(e.target)) {
            suggestions.style.display = 'none';
        }
    });
    
    // Keyboard navigation
    input.addEventListener('keydown', function(e) {
        const items = suggestions.querySelectorAll('.suggestion-item-modern');
        const activeItem = suggestions.querySelector('.suggestion-item-modern.active');
        let activeIndex = -1;
        
        if (activeItem) {
            activeIndex = Array.from(items).indexOf(activeItem);
        }
        
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            const nextIndex = (activeIndex + 1) % items.length;
            setActiveSuggestion(items, nextIndex);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            const prevIndex = (activeIndex - 1 + items.length) % items.length;
            setActiveSuggestion(items, prevIndex);
        } else if (e.key === 'Enter' && activeItem) {
            e.preventDefault();
            activeItem.click();
        }
    });
}

function setActiveSuggestion(items, index) {
    items.forEach(item => item.classList.remove('active'));
    if (items[index]) {
        items[index].classList.add('active');
        items[index].scrollIntoView({ block: 'nearest' });
    }
}

function showSuggestions(fieldId, airports) {
    const suggestions = document.getElementById(fieldId + 'Suggestions');
    const input = document.getElementById(fieldId);
    
    if (!suggestions || !input) return;
    
    if (airports.length === 0) {
        suggestions.style.display = 'none';
        return;
    }
    
    let html = '';
    airports.forEach(airport => {
        html += `
            <div class="suggestion-item-modern"
                 onclick="selectAirport('${fieldId}', '${airport.code}', '${airport.name} - ${airport.city}, ${airport.country}')">
                <div class="suggestion-code">${airport.code}</div>
                <div class="suggestion-details">
                    <div class="suggestion-name">${airport.name}</div>
                    <div class="suggestion-location">${airport.city}, ${airport.country}</div>
                </div>
            </div>
        `;
    });
    
    suggestions.innerHTML = html;
    suggestions.style.display = 'block';
}

function selectAirport(fieldId, code, displayText) {
    const input = document.getElementById(fieldId);
    const suggestions = document.getElementById(fieldId + 'Suggestions');
    
    if (input && suggestions) {
        input.value = code;
        suggestions.style.display = 'none';
    }
}

// Flight details toggle
function toggleFlightDetails(flightId) {
    const element = document.getElementById('flight-details-' + flightId);
    const button = event.currentTarget;
    
    if (!element || !button) return;
    
    if (element.style.display === 'none' || !element.style.display) {
        element.style.display = 'block';
        button.classList.add('active');
    } else {
        element.style.display = 'none';
        button.classList.remove('active');
    }
}

// Filter functions
function clearAllFilters() {
    const form = document.getElementById('filter-form');
    if (!form) return;
    
    const inputs = form.querySelectorAll('input[type="checkbox"], input[type="radio"], input[type="number"]');
    
    inputs.forEach(input => {
        if (input.type === 'checkbox') {
            input.checked = false;
        } else if (input.type === 'radio') {
            if (input.name === 'max_stops' && input.value === '-1') {
                input.checked = true;
            } else if (input.name === 'departure_time' && input.value === '') {
                input.checked = true;
            }
        } else if (input.type === 'number') {
            input.value = '';
        }
    });
    
    form.submit();
}

function updatePriceSlider() {
    const slider = document.getElementById('priceSlider');
    const maxPrice = document.getElementById('maxPrice');
    
    if (slider && maxPrice) {
        slider.addEventListener('input', function() {
            maxPrice.value = this.value;
        });
        
        maxPrice.addEventListener('input', function() {
            slider.value = this.value || 0;
        });
    }
}

// Flight sorting
function sortFlights(sortBy) {
    const flightCards = document.querySelectorAll('.flight-card-modern');
    const flightsArray = Array.from(flightCards);
    
    flightsArray.sort((a, b) => {
        switch (sortBy) {
            case 'price_asc':
                return getFlightPrice(a) - getFlightPrice(b);
            case 'price_desc':
                return getFlightPrice(b) - getFlightPrice(a);
            case 'duration_asc':
                return getFlightDuration(a) - getFlightDuration(b);
            case 'departure_asc':
                return getFlightDeparture(a) - getFlightDeparture(b);
            default:
                return 0;
        }
    });
    
    const container = document.querySelector('.flight-cards-container');
    container.innerHTML = '';
    flightsArray.forEach(card => container.appendChild(card));
}

function getFlightPrice(card) {
    const priceText = card.querySelector('.price-amount').textContent;
    return parseFloat(priceText.replace('₦', '').replace(',', ''));
}

function getFlightDuration(card) {
    // This would need to be implemented based on your duration format
    return 0;
}

function getFlightDeparture(card) {
    // This would need to be implemented based on your time format
    return 0;
}

// Special offer functions
function applySpecialOffer() {
    // Show confirmation animation
    const offerBtn = event.target;
    offerBtn.innerHTML = '✅ Offer Applied!';
    offerBtn.disabled = true;
    
    setTimeout(() => {
        alert('Special offer applied! You will see discounted prices in the flight results.');
    }, 500);
}

// Share invoice functionality with email
let currentFlightData = null;
let currentPassengerCount = 1;
let currentTravelClass = '';
let currentPrice = 0;
let currentOrigin = '';
let currentDestination = '';
let currentDepartureDate = '';
let currentReturnDate = '';
let currentTripType = '';
let currentAirlineCode = '';
let currentAirlineName = '';
let currentDepartureTime = '';
let currentArrivalTime = '';
let currentReturnDepartureTime = '';
let currentReturnArrivalTime = '';

function openShareModal(flightData, passengerCount, travelClass, price, origin, destination, departureDate, returnDate, tripType, airlineCode, airlineName, departureTime, arrivalTime, returnDepartureTime, returnArrivalTime) {
    currentFlightData = flightData;
    currentPassengerCount = passengerCount;
    currentTravelClass = travelClass;
    currentPrice = price;
    currentOrigin = origin;
    currentDestination = destination;
    currentDepartureDate = departureDate;
    currentReturnDate = returnDate;
    currentTripType = tripType;
    currentAirlineCode = airlineCode;
    currentAirlineName = airlineName;
    currentDepartureTime = departureTime || '';
    currentArrivalTime = arrivalTime || '';
    currentReturnDepartureTime = returnDepartureTime || '';
    currentReturnArrivalTime = returnArrivalTime || '';
    
    // Generate passenger fields with email
    const passengerFields = document.getElementById('passengerFields');
    if (!passengerFields) return;
    
    passengerFields.innerHTML = '';
    
    for (let i = 1; i <= passengerCount; i++) {
        passengerFields.innerHTML += `
            <div class="passenger-field-group-modern">
                <div class="passenger-field-row">
                    <div class="passenger-name-field">
                        <label class="input-label">Passenger ${i} Full Name *</label>
                        <input type="text" class="modern-input passenger-name" 
                               placeholder="Enter full name" required 
                               name="passenger_${i}" data-index="${i}">
                    </div>
                    <div class="passenger-email-field">
                        <label class="input-label">Email Address *</label>
                        <input type="email" class="modern-input passenger-email" 
                               placeholder="Enter email address" required 
                               name="passenger_email_${i}" data-index="${i}">
                    </div>
                </div>
            </div>
        `;
    }
    
    // Show modal
    const modal = document.getElementById('shareReceiptModal');
    if (modal) {
        modal.style.display = 'flex';
        setTimeout(() => {
            modal.classList.add('active');
        }, 10);
    }
}

function closeShareModal() {
    const modal = document.getElementById('shareReceiptModal');
    if (modal) {
        modal.classList.remove('active');
        setTimeout(() => {
            modal.style.display = 'none';
        }, 300);
    }
    currentFlightData = null;
}

function closeReceiptPreview() {
    const modal = document.getElementById('receiptPreviewModal');
    if (modal) {
        modal.classList.remove('active');
        setTimeout(() => {
            modal.style.display = 'none';
        }, 300);
    }
}

function generateInvoice() {
    // Validate form
    const passengerData = [];
    const passengerInputs = document.querySelectorAll('.passenger-name');
    const emailInputs = document.querySelectorAll('.passenger-email');
    const contactPhone = document.querySelector('.contact-phone');
    const contactAddress = document.querySelector('.contact-address');
    
    let isValid = true;
    
    // Validate contact information
    if (!contactPhone.value.trim()) {
        isValid = false;
        contactPhone.style.borderColor = '#dc3545';
        contactPhone.classList.add('error');
    } else {
        contactPhone.style.borderColor = '';
        contactPhone.classList.remove('error');
    }
    
    if (!contactAddress.value.trim()) {
        isValid = false;
        contactAddress.style.borderColor = '#dc3545';
        contactAddress.classList.add('error');
    } else {
        contactAddress.style.borderColor = '';
        contactAddress.classList.remove('error');
    }
    
    passengerInputs.forEach((input, index) => {
        const emailInput = document.querySelector(`.passenger-email[data-index="${input.dataset.index}"]`);
        
        if (!input.value.trim()) {
            isValid = false;
            input.style.borderColor = '#dc3545';
            input.classList.add('error');
        } else if (!emailInput.value.trim() || !isValidEmail(emailInput.value)) {
            isValid = false;
            emailInput.style.borderColor = '#dc3545';
            emailInput.classList.add('error');
        } else {
            input.style.borderColor = '';
            input.classList.remove('error');
            emailInput.style.borderColor = '';
            emailInput.classList.remove('error');
            
            passengerData.push({
                name: input.value.trim(),
                email: emailInput.value.trim()
            });
        }
    });
    
    if (!isValid) {
        // Add shake animation to empty fields
        const emptyInputs = document.querySelectorAll('.passenger-name.error, .passenger-email.error, .contact-phone.error, .contact-address.error');
        emptyInputs.forEach(input => {
            input.classList.add('shake');
            setTimeout(() => input.classList.remove('shake'), 600);
        });
        
        // Show error message
        alert('Please fill in all passenger names, valid email addresses, and contact information.');
        return;
    }
    
    // Generate invoice content first
    generateInvoiceContent(passengerData, contactPhone.value, contactAddress.value);
    
    // Then send emails
    sendInvoiceEmails(passengerData, contactPhone.value, contactAddress.value);
    
    // Close share modal and open invoice preview
    closeShareModal();
    const previewModal = document.getElementById('receiptPreviewModal');
    if (previewModal) {
        previewModal.style.display = 'flex';
        setTimeout(() => {
            previewModal.classList.add('active');
        }, 10);
    }
}

function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

function sendInvoiceEmails(passengerData, contactPhone, contactAddress) {
    const invoiceHTML = document.getElementById('receiptContent').innerHTML;
    
    const flightDetails = {
        flight_data: currentFlightData,
        origin: currentOrigin,
        destination: currentDestination,
        departure_date: currentDepartureDate,
        return_date: currentReturnDate,
        trip_type: currentTripType,
        airline: currentAirlineName,
        price: currentPrice,
        departure_time: currentDepartureTime,
        arrival_time: currentArrivalTime,
        return_departure_time: currentReturnDepartureTime,
        return_arrival_time: currentReturnArrivalTime,
        travel_class: currentTravelClass,
        passengers: currentPassengerCount,
        contact_phone: contactPhone,
        contact_address: contactAddress
    };

    // Show sending status
    const emailStatus = document.getElementById('emailStatus');
    const emailStatusText = document.getElementById('emailStatusText');
    emailStatus.style.display = 'flex';
    emailStatusText.textContent = 'Sending emails...';
    emailStatus.style.background = '#e7f3ff';
    emailStatus.style.color = '#0056b3';

    fetch('flights.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            'send_invoice_email': 'true',
            'passenger_data': JSON.stringify(passengerData),
            'invoice_html': invoiceHTML,
            'flight_details': JSON.stringify(flightDetails)
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            emailStatusText.textContent = data.message;
            emailStatus.style.background = '#d4edda';
            emailStatus.style.color = '#155724';
            
            // Show booking reference if available
            if (data.booking_reference) {
                emailStatusText.textContent += ` | Quote Ref: ${data.booking_reference}`;
            }
        } else {
            emailStatusText.textContent = data.message;
            emailStatus.style.background = '#f8d7da';
            emailStatus.style.color = '#721c24';
        }
    })
    .catch(error => {
        console.error('Error sending emails:', error);
        emailStatusText.textContent = 'Failed to send emails. Please try again.';
        emailStatus.style.background = '#f8d7da';
        emailStatus.style.color = '#721c24';
    });
}

// UPDATED: Generate enhanced invoice content matching book-flight.php format
function generateInvoiceContent(passengerData, contactPhone, contactAddress) {
    const receiptContent = document.getElementById('receiptContent');
    if (!receiptContent || !currentFlightData) return;
    
    const flight = currentFlightData;
    const itinerary = flight.itineraries ? flight.itineraries[0] : null;
    const segments = itinerary?.segments || [];
    const firstSegment = segments[0] || {};
    const lastSegment = segments[segments.length - 1] || {};
    
    // Format dates
    const departureDate = firstSegment.departure ? new Date(firstSegment.departure.at) : new Date(currentDepartureDate);
    const formattedDepartureDate = departureDate.toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric' 
    });

    let formattedReturnDate = '';
    if (currentReturnDate && currentTripType === 'round_trip') {
        const returnDate = new Date(currentReturnDate);
        formattedReturnDate = returnDate.toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric' 
        });
    }
    
    const invoiceDate = new Date().toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    });
    
    // Generate random invoice numbers
    const invoiceNo = `TC-QTE-${new Date().getFullYear()}-${Math.floor(1000 + Math.random() * 9000)}`;
    const bookingRef = `QTE-${currentOrigin}-${currentDestination}-${Math.floor(1000 + Math.random() * 9000)}`;
    const trackingId = `TRK-${Math.floor(100000 + Math.random() * 900000)}`;
    
    // Calculate fare breakdown
    const baseFare = currentPrice * 0.8;
    const taxes = currentPrice * 0.15;
    const serviceFee = currentPrice * 0.05;
    
    // Get flight duration
    const duration = itinerary?.duration ? itinerary.duration.replace('PT', '').replace('H', 'h ').replace('M', 'm') : 'N/A';
    const stops = segments.length - 1;

    // Determine trip type display
    const tripTypeDisplay = currentTripType === 'round_trip' ? 'Round Trip' : 'One Way';
    const routeDisplay = currentTripType === 'round_trip' ? 
        `${currentOrigin} → ${currentDestination} → ${currentOrigin}` : 
        `${currentOrigin} → ${currentDestination}`;
    
    // Prepare passenger rows
    let passengerRows = '';
    passengerData.forEach((passenger, index) => {
        passengerRows += `
            <tr>
                <td style="padding: 10px; border-bottom: 1px solid #e2e8f0;">Passenger ${index + 1}</td>
                <td style="padding: 10px; border-bottom: 1px solid #e2e8f0;">${passenger.name}</td>
                <td style="padding: 10px; border-bottom: 1px solid #e2e8f0;">${passenger.email}</td>
            </tr>
        `;
    });
    
    receiptContent.innerHTML = `
        <div class="receipt-template-modern">
            <div class="receipt-header-modern">
                <div class="company-logo-modern">
                    <?php if (!empty($website_logo)): ?>
                        <img src="<?php echo $website_logo; ?>" alt="Travel Centre Logo" class="site-logo-receipt">
                    <?php else: ?>
                        <h1>TRAVEL CENTRE</h1>
                    <?php endif; ?>
                </div>
                <div class="company-info-modern">
                    <h2>Official Flight Booking Partner</h2>
                    <p>flight.travelcentre.ng | email <?php echo $website_email; ?></p>
                </div>
            </div>
            
            <div class="receipt-details-modern">
                <div class="invoice-section-modern">
                    <p><strong>QUOTE No:</strong> ${invoiceNo}</p>
                    <p><strong>DATE:</strong> ${invoiceDate}</p>
                    <p><strong>TRIP TYPE:</strong> ${tripTypeDisplay}</p>
                    ${currentDepartureTime ? `<p><strong>DEPARTURE TIME:</strong> ${currentDepartureTime}</p>` : ''}
                    ${currentArrivalTime ? `<p><strong>ARRIVAL TIME:</strong> ${currentArrivalTime}</p>` : ''}
                    ${currentReturnDepartureTime ? `<p><strong>RETURN DEPARTURE:</strong> ${currentReturnDepartureTime}</p>` : ''}
                    ${currentReturnArrivalTime ? `<p><strong>RETURN ARRIVAL:</strong> ${currentReturnArrivalTime}</p>` : ''}
                </div>
                
                <table class="receipt-table-modern">
                    <thead>
                        <tr>
                            <th>QUOTE REFERENCE</th>
                            <th>TRACKING ID</th>
                            <th>ROUTE</th>
                            <th>DEPARTURE DATE</th>
                            ${currentTripType === 'round_trip' ? '<th>RETURN DATE</th>' : ''}
                            <th>PASSENGERS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>${bookingRef}</td>
                            <td>${trackingId}</td>
                            <td>${routeDisplay}</td>
                            <td>${formattedDepartureDate}</td>
                            ${currentTripType === 'round_trip' ? `<td>${formattedReturnDate}</td>` : '<td>-</td>'}
                            <td>${currentPassengerCount}</td>
                        </tr>
                    </tbody>
                </table>
                
                <div class="flight-details-section-modern">
                    <h3>${currentTripType === 'round_trip' ? 'OUTBOUND FLIGHT' : 'FLIGHT DETAILS'}</h3>
                    <table class="flight-details-table-modern">
                        <thead>
                            <tr>
                                <th>AIRLINE</th>
                                <th>ROUTE</th>
                                <th>CLASS</th>
                                <th>DURATION</th>
                                <th>STOPS</th>
                                <th>DEPARTURE</th>
                                <th>ARRIVAL</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>${currentAirlineName}</td>
                                <td>${currentOrigin} → ${currentDestination}</td>
                                <td>${currentTravelClass.replace('_', ' ')}</td>
                                <td>${duration}</td>
                                <td>${stops} stop${stops !== 1 ? 's' : ''}</td>
                                <td>${currentDepartureTime}</td>
                                <td>${currentArrivalTime}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                ${currentTripType === 'round_trip' ? `
                <div class="flight-details-section-modern">
                    <h3>RETURN FLIGHT</h3>
                    <table class="flight-details-table-modern">
                        <thead>
                            <tr>
                                <th>AIRLINE</th>
                                <th>ROUTE</th>
                                <th>CLASS</th>
                                <th>DURATION</th>
                                <th>STOPS</th>
                                <th>DEPARTURE</th>
                                <th>ARRIVAL</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>${currentAirlineName}</td>
                                <td>${currentDestination} → ${currentOrigin}</td>
                                <td>${currentTravelClass.replace('_', ' ')}</td>
                                <td>${duration}</td>
                                <td>${stops} stop${stops !== 1 ? 's' : ''}</td>
                                <td>${currentReturnDepartureTime || 'TBD'}</td>
                                <td>${currentReturnArrivalTime || 'TBD'}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                ` : ''}
                
                <div class="section">
                    <div class="section-title">Passenger Details</div>
                    <table>
                        <thead>
                            <tr>
                                <th>Passenger</th>
                                <th>Full Name</th>
                                <th>Email</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${passengerRows}
                        </tbody>
                    </table>
                </div>

                <div class="section">
                    <div class="section-title">Contact Information</div>
                    <div style="background: #f8fafc; padding: 15px; border-radius: 6px;">
                        <p><strong>Email:</strong> ${passengerData[0].email}</p>
                        <p><strong>Phone:</strong> ${contactPhone}</p>
                        <p><strong>Address:</strong> ${contactAddress}</p>
                    </div>
                </div>
                
                <div class="fare-breakdown-section-modern">
                    <table class="fare-table-modern">
                        <tr>
                            <td width="50%">
                                <h4>FARE BREAKDOWN</h4>
                                <p>Base Fare: ₦${baseFare.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</p>
                                <p>Taxes & Airline Charges: ₦${taxes.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</p>
                                <p>Service Fee: ₦${serviceFee.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</p>
                                <p><strong>Total: ₦${currentPrice.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong></p>
                            </td>
                            <td width="50%">
                                <h4>QUOTE SUMMARY</h4>
                                <p><strong>REFERENCE:</strong> ${bookingRef}</p>
                                <p><strong>TRACKING ID:</strong> ${trackingId}</p>
                                <p><strong>STATUS:</strong> Flight Quote</p>
                                <p><strong>TRIP TYPE:</strong> ${tripTypeDisplay}</p>
                                ${currentDepartureTime ? `<p><strong>DEPARTURE TIME:</strong> ${currentDepartureTime}</p>` : ''}
                                ${currentArrivalTime ? `<p><strong>ARRIVAL TIME:</strong> ${currentArrivalTime}</p>` : ''}
                                ${currentReturnDepartureTime ? `<p><strong>RETURN DEPARTURE:</strong> ${currentReturnDepartureTime}</p>` : ''}
                                ${currentReturnArrivalTime ? `<p><strong>RETURN ARRIVAL:</strong> ${currentReturnArrivalTime}</p>` : ''}
                                <p>This is a flight quote. Contact us to proceed with booking and payment.</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="receipt-footer-modern">
                    <h3>THANK YOU FOR YOUR INTEREST!</h3>
                    <p>TravelCentre.ng – Your trusted flight partner across Africa.</p>
                    <p>For assistance, +234 903 407 2383 | <?php echo $website_email; ?></p>
                    <p><em>This quote has been sent to the provided email addresses.</em></p>
                </div>
            </div>
        </div>
    `;
}

function printInvoice() {
    const receiptContent = document.getElementById('receiptContent');
    if (!receiptContent) return;
    
    const receiptHTML = receiptContent.innerHTML;
    const printWindow = window.open('', '_blank', 'width=800,height=600');
    
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Flight Quote - Travel Centre</title>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    margin: 20px; 
                    line-height: 1.4;
                    color: #333;
                }
                .receipt-template-modern { 
                    max-width: 800px; 
                    margin: 0 auto; 
                    border: 1px solid #ddd;
                    padding: 20px;
                }
                .receipt-header-modern { 
                    text-align: center; 
                    margin-bottom: 30px; 
                    border-bottom: 2px solid #007bff; 
                    padding-bottom: 20px; 
                }
                .company-logo-modern h1 { 
                    color: #007bff; 
                    margin: 0 0 10px 0; 
                    font-size: 28px; 
                    font-weight: bold;
                }
                .site-logo-receipt {
                    max-width: 200px;
                    height: auto;
                    margin-bottom: 10px;
                }
                .company-info-modern h2 { 
                    margin: 5px 0; 
                    font-size: 18px; 
                    color: #666; 
                    font-weight: 600;
                }
                .company-info-modern p { 
                    margin: 5px 0; 
                    color: #666; 
                    font-size: 14px;
                }
                .receipt-table-modern, .flight-details-table-modern, .fare-table-modern { 
                    width: 100%; 
                    border-collapse: collapse; 
                    margin: 20px 0; 
                    font-size: 14px;
                }
                .receipt-table-modern th, .flight-details-table-modern th, .fare-table-modern th { 
                    background: #f8f9fa; 
                    padding: 12px 8px; 
                    text-align: left; 
                    border: 1px solid #ddd; 
                    font-weight: 600;
                }
                .receipt-table-modern td, .flight-details-table-modern td, .fare-table-modern td { 
                    padding: 12px 8px; 
                    border: 1px solid #ddd;
                }
                .invoice-section-modern { 
                    margin: 20px 0; 
                    padding: 15px;
                    background: #f8f9fa;
                    border-radius: 5px;
                }
                .invoice-section-modern p {
                    margin: 5px 0;
                    font-size: 14px;
                }
                .flight-details-section-modern h3, .fare-breakdown-section-modern h4 { 
                    color: #333; 
                    margin: 20px 0 12px 0; 
                    font-size: 16px;
                    border-bottom: 1px solid #eee;
                    padding-bottom: 8px;
                }
                .fare-table-modern td { 
                    vertical-align: top; 
                    padding: 15px;
                }
                .fare-table-modern p { 
                    margin: 8px 0; 
                }
                .receipt-footer-modern { 
                    text-align: center; 
                    margin-top: 30px; 
                    padding-top: 20px; 
                    border-top: 2px solid #007bff; 
                }
                .receipt-footer-modern h3 { 
                    color: #007bff; 
                    margin: 0 0 10px 0; 
                    font-size: 20px;
                }
                .receipt-footer-modern p { 
                    margin: 5px 0; 
                    color: #666;
                }
                @media print {
                    body { margin: 0; }
                    .receipt-template-modern { border: none; padding: 0; }
                    .receipt-actions-modern { display: none !important; }
                }
            </style>
        </head>
        <body>
            <div class="receipt-template-modern">
                <div class="receipt-header-modern">
                    <div class="company-logo-modern">
                        <?php if (!empty($website_logo)): ?>
                            <img src="<?php echo $website_logo; ?>" alt="Travel Centre Logo" class="site-logo-receipt">
                        <?php else: ?>
                            <h1>TRAVEL CENTRE</h1>
                        <?php endif; ?>
                    </div>
                    <div class="company-info-modern">
                        <h2>Official Flight Booking Partner</h2>
                        <p>flight.travelcentre.ng | email <?php echo $website_email; ?></p>
                    </div>
                </div>
                
                <div class="receipt-details-modern">
                    ${receiptHTML.split('<div class="receipt-details-modern">')[1] || receiptHTML}
                </div>
            </div>
            <script>
                window.onload = function() {
                    window.print();
                    setTimeout(function() {
                        window.close();
                    }, 500);
                };
            <\/script>
        </body>
        </html>
    `);
    
    printWindow.document.close();
}

function shareInvoice() {
    if (navigator.share) {
        navigator.share({
            title: 'Flight Quote - Travel Centre',
            text: `Flight quote for ${currentOrigin} to ${currentDestination} - ${currentTripType === 'round_trip' ? 'Round Trip' : 'One Way'} - Departure: ${currentDepartureTime} - Arrival: ${currentArrivalTime}${currentReturnDepartureTime ? ` - Return: ${currentReturnDepartureTime}` : ''}`,
            url: window.location.href
        }).catch(error => {
            console.log('Share cancelled or failed:', error);
        });
    } else {
        alert('Web Share API not supported in your browser. You can use the Print option to save as PDF.');
    }
}

// Close modals when clicking outside
window.onclick = function(event) {
    const shareModal = document.getElementById('shareReceiptModal');
    const receiptModal = document.getElementById('receiptPreviewModal');
    
    if (event.target === shareModal) {
        closeShareModal();
    }
    if (event.target === receiptModal) {
        closeReceiptPreview();
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initTripType();
    initAirportSearch('origin');
    initAirportSearch('destination');
    updatePriceSlider();
    
    // Add scroll animations
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);

    // Observe flight cards for animation
    document.querySelectorAll('.flight-card-modern').forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(card);
    });

    // Add hover effects to cards
    document.querySelectorAll('.flight-card-modern').forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
});
</script>

<style>
/* ADDED: Loading spinner styles */
.loading-spinner {
    border: 2px solid #f3f3f3;
    border-top: 2px solid #3b82f6;
    border-radius: 50%;
    width: 16px;
    height: 16px;
    animation: spin 1s linear infinite;
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    display: none;
    z-index: 10;
}

@keyframes spin {
    0% { transform: translateY(-50%) rotate(0deg); }
    100% { transform: translateY(-50%) rotate(360deg); }
}

/* Adjust input padding to make room for spinner */
.input-with-icon {
    position: relative;
}

.input-with-icon .modern-input {
    padding-right: 40px; /* Make room for the spinner */
}

/* UPDATED: Compact button styles */
.action-buttons-compact {
    display: flex;
    gap: 0.5rem;
    width: 100%;
    justify-content: space-between;
}

.btn-details-compact,
.btn-book-now-compact,
.btn-share-compact {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.5rem 0.75rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    flex: 1;
    justify-content: center;
    min-width: 0;
    border: 1px solid;
}

.btn-icon-compact {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.btn-text-compact {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.btn-details-compact {
    background: white;
    color: #6b7280;
    border-color: #d1d5db;
}

.btn-details-compact:hover {
    background: #f8fafc;
    border-color: #9ca3af;
}

.btn-details-compact.active {
    background: #3b82f6;
    color: white;
    border-color: #3b82f6;
}

.btn-book-now-compact {
    background: #3b82f6;
    color: white;
    border-color: #3b82f6;
}

.btn-book-now-compact:hover {
    background: #2563eb;
    border-color: #2563eb;
    transform: translateY(-1px);
}

.btn-share-compact {
    background: white;
    color: #6b7280;
    border-color: #d1d5db;
}

.btn-share-compact:hover {
    background: #f8fafc;
    border-color: #9ca3af;
}

.book-form-compact {
    display: flex;
    flex: 1;
    min-width: 0;
}

/* Rest of your existing CSS remains exactly the same */
/* ... (all your existing CSS code) ... */

/* Modern CSS Reset and Base Styles */
.flights-page-wrapper {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
    line-height: 1.6;
    color: #1a1a1a;
    background: #f8fafc;
    min-height: 100vh;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
    width: 100%;
}

/* Hero Section */
.flight-hero-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    position: relative;
    overflow: hidden;
    padding: 80px 0 60px;
}

.hero-background {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000" fill="%23667eea"><circle cx="200" cy="200" r="2" fill="%23ffffff" opacity="0.1"/><circle cx="600" cy="300" r="1" fill="%23ffffff" opacity="0.1"/><circle cx="800" cy="150" r="1.5" fill="%23ffffff" opacity="0.1"/></svg>');
}

.hero-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.3);
}

.hero-content {
    position: relative;
    z-index: 2;
    text-align: center;
    color: white;
}

.hero-title {
    font-size: 3rem;
    font-weight: 800;
    margin-bottom: 1rem;
    background: linear-gradient(135deg, #fff 0%, #e2e8f0 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    text-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.hero-subtitle {
    font-size: 1.25rem;
    opacity: 0.9;
    margin-bottom: 3rem;
    font-weight: 400;
}

/* Modern Search Form */
.search-form-modern {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    margin-bottom: 2rem;
}

.modern-search-form {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.form-section {
    width: 100%;
}

/* Trip Type Toggle - UPDATED: One Way as default */
.trip-type-section {
    display: flex;
    justify-content: center;
}

.trip-type-toggle {
    display: flex;
    background: #f1f5f9;
    border-radius: 12px;
    padding: 4px;
    gap: 4px;
}

.trip-type-toggle input {
    display: none;
}

.trip-option {
    padding: 12px 24px;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 500;
    color: #64748b;
}

.trip-type-toggle input:checked + .trip-option {
    background: white;
    color: #1e293b;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.trip-icon {
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Location Inputs */
.location-section {
    margin: 1rem 0;
}

.location-inputs-grid {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    gap: 1rem;
    align-items: end;
}

.location-input-group {
    position: relative;
}

.input-label {
    display: block;
    font-weight: 600;
    color: #374151;
    margin-bottom: 0.5rem;
    font-size: 0.875rem;
}

.input-with-icon {
    position: relative;
    display: flex;
    align-items: center;
}

.input-icon {
    position: absolute;
    left: 1rem;
    color: #6b7280;
    z-index: 2;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modern-input {
    width: 100%;
    padding: 1rem 1rem 1rem 3rem;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    font-size: 1rem;
    transition: all 0.3s ease;
    background: white;
}

.modern-input:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.swap-locations-btn {
    background: #f8fafc;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    padding: 0.75rem;
    cursor: pointer;
    transition: all 0.3s ease;
    color: #6b7280;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    height: fit-content;
    align-self: center;
}

.swap-locations-btn:hover {
    background: #3b82f6;
    color: white;
    border-color: #3b82f6;
    transform: rotate(180deg);
}

/* Date Inputs */
.date-inputs-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.date-input-group {
    position: relative;
}

/* Passenger & Class */
.passenger-class-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.passenger-selector-modern {
    display: flex;
    align-items: center;
    background: #f8fafc;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    padding: 0.5rem;
}

.passenger-btn {
    background: white;
    border: none;
    border-radius: 8px;
    width: 2.5rem;
    height: 2.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 1.25rem;
    font-weight: 600;
    transition: all 0.3s ease;
    color: #374151;
}

.passenger-btn:hover {
    background: #3b82f6;
    color: white;
}

.passenger-count {
    font-weight: 600;
    font-size: 1.125rem;
    margin: 0 1rem;
    min-width: 1.5rem;
    text-align: center;
    transition: transform 0.2s ease;
    color: #000000;
}

.modern-select {
    width: 100%;
    padding: 1rem;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    font-size: 1rem;
    background: white;
    cursor: pointer;
    transition: all 0.3s ease;
}

.modern-select:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

/* Search Button */
.form-actions-modern {
    display: flex;
    justify-content: center;
    margin-top: 1rem;
}

.search-btn-primary {
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    color: white;
    border: none;
    padding: 1rem 3rem;
    border-radius: 12px;
    font-size: 1.125rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    box-shadow: 0 4px 15px rgba(59, 130, 246, 0.4);
}

.search-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(59, 130, 246, 0.6);
}

/* Quick Routes */
.quick-routes-section {
    margin-top: 2rem;
}

.quick-routes-title {
    color: white;
    font-size: 1.25rem;
    font-weight: 600;
    margin-bottom: 1rem;
    opacity: 0.9;
}

.quick-routes-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    justify-content: center;
}

.quick-route-card {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 50px;
    text-decoration: none;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 500;
}

.quick-route-card:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: translateY(-2px);
}

.route-arrow {
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Auth Messages */
.auth-message {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 2rem;
    max-width: 600px;
    margin-left: auto;
    margin-right: auto;
    animation: slideInDown 0.5s ease;
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
    display: flex;
    align-items: center;
    justify-content: center;
}

.auth-close {
    background: none;
    border: none;
    font-size: 1.25rem;
    cursor: pointer;
    margin-left: auto;
    color: inherit;
}

/* Error Section */
.error-section {
    padding: 2rem 0;
}

.error-card {
    background: white;
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    gap: 1.5rem;
    border-left: 4px solid #ef4444;
}

.error-icon {
    display: flex;
    align-items: center;
    justify-content: center;
}

.error-content {
    flex: 1;
}

.error-title {
    color: #dc2626;
    margin-bottom: 0.5rem;
    font-weight: 600;
}

.error-message {
    color: #6b7280;
    margin-bottom: 1rem;
}

.error-tips {
    font-size: 0.875rem;
    color: #6b7280;
}

.route-suggestion {
    background: #fef2f2;
    color: #dc2626;
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    font-weight: 600;
    margin: 0 0.25rem;
}

/* Results Layout */
.results-layout {
    display: grid;
    grid-template-columns: 280px 1fr 300px;
    gap: 2rem;
    padding: 2rem 0;
}

/* Filter Sidebar */
.filter-sidebar {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    height: fit-content;
    position: sticky;
    top: 2rem;
}

.filter-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #e5e7eb;
}

.filter-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: #1e293b;
}

.clear-filters-btn {
    background: none;
    border: none;
    color: #3b82f6;
    cursor: pointer;
    font-size: 0.875rem;
    font-weight: 500;
}

.clear-filters-btn:hover {
    text-decoration: underline;
}

.filter-group {
    margin-bottom: 2rem;
}

.filter-group-title {
    font-size: 0.875rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 1rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

/* Price Filter */
.price-inputs-modern {
    display: flex;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.price-input-group {
    flex: 1;
}

.price-input-modern {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 0.875rem;
}

.price-slider-container {
    margin-top: 1rem;
}

.price-slider-modern {
    width: 100%;
    height: 4px;
    border-radius: 2px;
    background: #e5e7eb;
    outline: none;
}

.price-slider-modern::-webkit-slider-thumb {
    appearance: none;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: #3b82f6;
    cursor: pointer;
    box-shadow: 0 2px 6px rgba(59, 130, 246, 0.4);
}

/* Filter Options */
.filter-options {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.filter-option {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    padding: 0.5rem 0;
}

.filter-option input {
    display: none;
}

.checkmark {
    width: 18px;
    height: 18px;
    border: 2px solid #d1d5db;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.filter-option input:checked + .checkmark {
    background: #3b82f6;
    border-color: #3b82f6;
}

.filter-option input:checked + .checkmark::after {
    content: '✓';
    color: white;
    font-size: 12px;
    font-weight: bold;
}

.option-label {
    flex: 1;
    font-size: 0.875rem;
    color: #374151;
}

.option-count {
    color: #9ca3af;
    font-size: 0.75rem;
}

/* Results Main Content */
.results-main-content {
    min-width: 0;
}

.results-header-modern {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 2rem;
    padding: 1.5rem;
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

.results-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 0.5rem;
}

.results-route {
    color: #6b7280;
    font-size: 0.875rem;
}

.results-sort {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.sort-label {
    font-size: 0.875rem;
    color: #6b7280;
}

.sort-select {
    padding: 0.5rem 1rem;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 0.875rem;
    background: white;
    cursor: pointer;
}

/* Flight Cards */
.flight-cards-container {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.flight-card-modern {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid #f1f5f9;
    transition: all 0.3s ease;
    overflow: hidden;
}

.flight-card-modern:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
}

.flight-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 1.5rem;
    border-bottom: 1px solid #f1f5f9;
}

.airline-info-modern {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.airline-logo-modern {
    width: 48px;
    height: 48px;
    border-radius: 8px;
    object-fit: contain;
    background: #f8fafc;
    padding: 4px;
}

.airline-details-modern {
    flex: 1;
}

.flight-route {
    font-size: 1.25rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 0.25rem;
}

.airline-name-modern {
    color: #6b7280;
    font-size: 0.875rem;
    margin: 0;
}

.flight-price-modern {
    text-align: right;
}

.price-amount {
    font-size: 1.5rem;
    font-weight: 700;
    color: #3b82f6;
    display: block;
}

.price-per-person {
    font-size: 0.75rem;
    color: #6b7280;
}

.flight-card-body {
    padding: 1.5rem;
}

.flight-timeline {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;
}

.time-point {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    flex: 1;
}

.time {
    font-size: 1.125rem;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 0.25rem;
}

.airport {
    font-size: 0.875rem;
    color: #6b7280;
}

.flight-duration {
    flex: 2;
    max-width: 200px;
}

.duration-line {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.line {
    flex: 1;
    height: 2px;
    background: #3b82f6;
    border-radius: 1px;
}

.duration-info {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
}

.duration-text {
    font-size: 0.75rem;
    font-weight: 600;
    color: #3b82f6;
}

.stops-info {
    font-size: 0.75rem;
    color: #6b7280;
}

.flight-meta-modern {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.meta-badge {
    background: #f1f5f9;
    color: #475569;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 500;
}

/* UPDATED: Time badge specific styling */
.time-badge {
    background: #dbeafe;
    color: #1e40af;
    border: 1px solid #93c5fd;
}

.flight-card-footer {
    padding: 1.5rem;
    background: #f8fafc;
    border-top: 1px solid #e5e7eb;
}

.flight-actions-modern {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
}

/* UPDATED: More compact flight card layout */
.flight-card-modern {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
    border: 1px solid #f1f5f9;
    transition: all 0.3s ease;
    overflow: hidden;
    margin-bottom: 1rem;
}

.flight-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 1.25rem;
    border-bottom: 1px solid #f1f5f9;
}

.flight-card-body {
    padding: 1.25rem;
}

.flight-card-footer {
    padding: 1rem 1.25rem;
    background: #f8fafc;
    border-top: 1px solid #e5e7eb;
}

.airline-logo-modern {
    width: 40px;
    height: 40px;
    border-radius: 6px;
}

.flight-route {
    font-size: 1.125rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 0.25rem;
}

.airline-name-modern {
    color: #6b7280;
    font-size: 0.8125rem;
    margin: 0;
}

.price-amount {
    font-size: 1.25rem;
    font-weight: 700;
    color: #3b82f6;
    display: block;
}

.price-per-person {
    font-size: 0.6875rem;
    color: #6b7280;
}

/* UPDATED: More compact flight timeline */
.flight-timeline {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;
    gap: 0.5rem;
}

.time-point {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    flex: 1;
    min-width: 0;
}

.time {
    font-size: 1rem;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 0.25rem;
}

.airport {
    font-size: 0.8125rem;
    color: #6b7280;
}

.flight-duration {
    flex: 2;
    max-width: 150px;
}

.duration-line {
    display: flex;
    align-items: center;
    gap: 0.375rem;
}

.line {
    flex: 1;
    height: 2px;
    background: #3b82f6;
    border-radius: 1px;
}

.duration-info {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.125rem;
}

.duration-text {
    font-size: 0.6875rem;
    font-weight: 600;
    color: #3b82f6;
}

.stops-info {
    font-size: 0.6875rem;
    color: #6b7280;
}

/* UPDATED: More compact flight meta */
.flight-meta-modern {
    display: flex;
    gap: 0.375rem;
    flex-wrap: wrap;
}

.meta-badge {
    background: #f1f5f9;
    color: #475569;
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    font-size: 0.6875rem;
    font-weight: 500;
}

.time-badge {
    background: #dbeafe;
    color: #1e40af;
    border: 1px solid #93c5fd;
}

/* UPDATED: More responsive container padding */
.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 1rem;
    width: 100%;
}

/* UPDATED: More compact results layout */
.results-layout {
    display: grid;
    grid-template-columns: 250px 1fr 280px;
    gap: 1.5rem;
    padding: 1.5rem 0;
}

/* UPDATED: More compact filter sidebar */
.filter-sidebar {
    background: white;
    border-radius: 12px;
    padding: 1.25rem;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
    height: fit-content;
    position: sticky;
    top: 1rem;
}

.filter-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.25rem;
    padding-bottom: 0.875rem;
    border-bottom: 1px solid #e5e7eb;
}

.filter-title {
    font-size: 1.125rem;
    font-weight: 700;
    color: #1e293b;
}

.filter-group {
    margin-bottom: 1.5rem;
}

.filter-group-title {
    font-size: 0.8125rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

/* UPDATED: More compact search form */
.search-form-modern {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    margin-bottom: 1.5rem;
}

.modern-search-form {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

/* Flight Details Expanded */
.flight-details-expanded {
    display: none;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid #e5e7eb;
}

.flight-segment {
    background: white;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 0.75rem;
    border: 1px solid #f1f5f9;
}

.segment-info {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    gap: 1rem;
    align-items: center;
}

.segment-airport {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.arrival {
    text-align: right;
}

.airport-code {
    font-size: 1rem;
    font-weight: 700;
    color: #1e293b;
}

.segment-time {
    font-size: 0.875rem;
    color: #1e293b;
    font-weight: 500;
}

.segment-date {
    font-size: 0.75rem;
    color: #6b7280;
}

.segment-duration {
    text-align: center;
}

.duration-display {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
}

.duration {
    font-size: 0.75rem;
    font-weight: 600;
    color: #3b82f6;
}

.flight-line {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    width: 100%;
}

.plane-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    color: #3b82f6;
}

.flight-number {
    color: #6b7280;
    font-size: 0.75rem;
}

.layover-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem;
    background: #fff7ed;
    border-radius: 6px;
    margin: 0.5rem 0;
}

.layover-icon {
    display: flex;
    align-items: center;
    justify-content: center;
}

.layover-text {
    font-size: 0.875rem;
    color: #ea580c;
    font-weight: 500;
}

/* Results Sidebar */
.results-sidebar {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    height: fit-content;
    position: sticky;
    top: 2rem;
}

/* Special Offer Card */
.special-offer-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 16px;
    padding: 1.5rem;
    color: white;
    position: relative;
    overflow: hidden;
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
}

.offer-badge {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

.offer-content {
    margin-bottom: 1rem;
}

.offer-image {
    margin-bottom: 1rem;
    text-align: center;
}

.offer-img {
    width: 100%;
    max-width: 200px;
    height: 120px;
    border-radius: 8px;
    object-fit: cover;
    margin: 0 auto;
}

.offer-title {
    font-size: 1.125rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.offer-description {
    font-size: 0.875rem;
    opacity: 0.9;
    margin-bottom: 1rem;
    line-height: 1.4;
}

.offer-highlight {
    margin-bottom: 1rem;
}

.discount-tag {
    background: #ffd700;
    color: #333;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 700;
    display: inline-block;
}

.offer-validity {
    font-size: 0.75rem;
    opacity: 0.8;
}

.offer-actions {
    text-align: center;
}

.btn-offer {
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    color: white;
    border: 1px solid rgba(255, 255, 255, 0.3);
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 600;
    width: 100%;
}

.btn-offer:hover {
    background: rgba(255, 255, 255, 0.3);
}

/* Ad Panel */
.ad-panel-modern {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

/* No Results */
.no-results-modern {
    text-align: center;
    padding: 4rem 2rem;
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

.no-results-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1.5rem;
    opacity: 0.5;
}

.no-results-title {
    color: #6b7280;
    margin-bottom: 1rem;
    font-size: 1.5rem;
    font-weight: 600;
}

.no-results-message {
    color: #9ca3af;
    margin-bottom: 2rem;
    max-width: 400px;
    margin-left: auto;
    margin-right: auto;
}

.no-results-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
}

.btn-primary {
    background: #3b82f6;
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 600;
    text-decoration: none;
    display: inline-block;
}

.btn-primary:hover {
    background: #2563eb;
    transform: translateY(-1px);
}

.btn-secondary {
    background: #6b7280;
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 600;
}

.btn-secondary:hover {
    background: #4b5563;
    transform: translateY(-1px);
}

/* Suggestions Dropdown */
.suggestions-dropdown {
    display: none;
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    max-height: 200px;
    overflow-y: auto;
    z-index: 1000;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    margin-top: 0.25rem;
}

.suggestion-item-modern {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #f1f5f9;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    transition: background-color 0.2s ease;
}

.suggestion-item-modern:hover {
    background: #f8fafc;
}

.suggestion-item-modern:last-child {
    border-bottom: none;
}

.suggestion-code {
    font-weight: 700;
    color: #3b82f6;
    font-size: 0.875rem;
    min-width: 40px;
}

.suggestion-details {
    flex: 1;
}

.suggestion-name {
    font-weight: 600;
    color: #1e293b;
    font-size: 0.875rem;
    margin-bottom: 0.125rem;
}

.suggestion-location {
    color: #6b7280;
    font-size: 0.75rem;
}

/* Modal Styles */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
    z-index: 10000;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.modal-overlay.active {
    opacity: 1;
}

.modal-container {
    background: white;
    border-radius: 16px;
    width: 100%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
    transform: scale(0.9);
    transition: transform 0.3s ease;
}

.modal-overlay.active .modal-container {
    transform: scale(1);
}

.receipt-modal {
    max-width: 800px;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 1px solid #e5e7eb;
}

.modal-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    cursor: pointer;
    color: #6b7280;
    padding: 0.5rem;
    border-radius: 6px;
    transition: background-color 0.2s ease;
}

.modal-close:hover {
    background: #f1f5f9;
}

.modal-body {
    padding: 1.5rem;
}

/* Passenger Form Modern */
.passenger-form-modern {
    width: 100%;
}

.section-title {
    font-size: 1rem;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #e5e7eb;
}

.passenger-fields-modern {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin-bottom: 2rem;
}

.passenger-field-group-modern {
    width: 100%;
}

.passenger-field-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1rem;
}

.passenger-name-field,
.passenger-email-field {
    display: flex;
    flex-direction: column;
}

.section-description {
    color: #6b7280;
    font-size: 0.875rem;
    margin-bottom: 1.5rem;
    line-height: 1.4;
}

.form-actions-modal {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
}

.btn-modal-primary {
    background: #3b82f6;
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 600;
}

.btn-modal-primary:hover {
    background: #2563eb;
}

.btn-modal-secondary {
    background: #6b7280;
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 600;
}

.btn-modal-secondary:hover {
    background: #4b5563;
}

.btn-modal-info {
    background: #06b6d4;
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 600;
}

.btn-modal-info:hover {
    background: #0891b2;
}

/* Receipt Content Modern */
.receipt-content-modern {
    max-height: 60vh;
    overflow-y: auto;
}

.receipt-template-modern {
    background: white;
    padding: 2rem;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
}

.receipt-header-modern {
    text-align: center;
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 2px solid #667eea;
}

.company-logo-modern h1 {
    color: #667eea;
    margin: 0 0 0.5rem 0;
    font-size: 2rem;
    font-weight: 800;
}

.site-logo-receipt {
    max-width: 200px;
    height: auto;
    margin-bottom: 10px;
}

.company-info-modern h2 {
    margin: 0.25rem 0;
    font-size: 1.125rem;
    color: #6b7280;
    font-weight: 600;
}

.company-info-modern p {
    margin: 0.25rem 0;
    color: #9ca3af;
    font-size: 0.875rem;
}

.invoice-section-modern {
    margin: 1.5rem 0;
    padding: 1rem;
    background: #f8fafc;
    border-radius: 8px;
}

.invoice-section-modern p {
    margin: 0.5rem 0;
    font-size: 0.875rem;
}

.receipt-table-modern,
.flight-details-table-modern,
.fare-table-modern {
    width: 100%;
    border-collapse: collapse;
    margin: 1.5rem 0;
    font-size: 0.875rem;
}

.receipt-table-modern th,
.flight-details-table-modern th,
.fare-table-modern th {
    background: #f8fafc;
    padding: 0.75rem;
    text-align: left;
    border: 1px solid #e5e7eb;
    font-weight: 600;
}

.receipt-table-modern td,
.flight-details-table-modern td,
.fare-table-modern td {
    padding: 0.75rem;
    border: 1px solid #e5e7eb;
}

.flight-details-section-modern h3,
.fare-breakdown-section-modern h4 {
    color: #1e293b;
    margin: 1.5rem 0 1rem 0;
    font-size: 1.125rem;
}

.fare-table-modern td {
    vertical-align: top;
}

.fare-table-modern p {
    margin: 0.5rem 0;
}

.receipt-footer-modern {
    text-align: center;
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 2px solid #667eea;
}

.receipt-footer-modern h3 {
    color: #667eea;
    margin: 0 0 10px 0;
    font-size: 1.25rem;
}

.receipt-footer-modern p {
    margin: 0.25rem 0;
    color: #6b7280;
    font-size: 0.875rem;
}

.receipt-actions-modern {
    display: flex;
    gap: 1rem;
    justify-content: center;
    margin-top: 2rem;
    flex-wrap: wrap;
}

.email-status {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 500;
    margin-left: auto;
}

.status-icon {
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Animations */
@keyframes slideInDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-5px); }
    75% { transform: translateX(5px); }
}

.shake {
    animation: shake 0.5s ease;
}

/* Responsive Design */
@media (max-width: 1024px) {
    .results-layout {
        grid-template-columns: 250px 1fr;
    }
    
    .results-sidebar {
        display: none;
    }
    
    .hero-title {
        font-size: 2.5rem;
    }
}

@media (max-width: 768px) {
    .results-layout {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .filter-sidebar {
        position: static;
        order: 2;
    }
    
    .results-main-content {
        order: 1;
    }
    
    .hero-title {
        font-size: 2rem;
    }
    
    .hero-subtitle {
        font-size: 1.125rem;
    }
    
    .search-form-modern {
        padding: 1.5rem;
    }
    
    .location-inputs-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .swap-locations-btn {
        align-self: center;
        transform: rotate(90deg);
        margin-bottom: 0;
        padding: 0.5rem;
        width: 48px;
        height: 48px;
        margin: 0.5rem auto;
    }
    
    .date-inputs-grid {
        grid-template-columns: 1fr;
    }
    
    .passenger-class-grid {
        grid-template-columns: 1fr;
    }
    
    .results-header-modern {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }
    
    .flight-card-header {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }
    
    .flight-price-modern {
        align-self: flex-end;
    }
    
    .flight-actions-modern {
        flex-direction: column;
        align-items: stretch;
    }
    
    .action-buttons-compact {
        justify-content: stretch;
    }
    
    .btn-details-compact,
    .btn-book-now-compact,
    .btn-share-compact {
        flex: 1;
        justify-content: center;
    }
    
    .modal-container {
        margin: 1rem;
    }
    
    .form-actions-modal {
        flex-direction: column;
    }
    
    .receipt-actions-modern {
        flex-direction: column;
    }
    
    .passenger-field-row {
        grid-template-columns: 1fr;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .container {
        padding: 0 1rem;
    }
    
    .flight-hero-section {
        padding: 60px 0 40px;
    }
    
    .hero-title {
        font-size: 1.75rem;
    }
    
    .search-form-modern {
        padding: 1rem;
        border-radius: 12px;
    }
    
    .trip-option {
        padding: 0.75rem 1rem;
        font-size: 0.875rem;
    }
    
    .modern-input {
        padding: 0.875rem 0.875rem 0.875rem 2.5rem;
    }
    
    .search-btn-primary {
        width: 100%;
        justify-content: center;
    }
    
    .quick-routes-grid {
        flex-direction: column;
        align-items: center;
    }
    
    .quick-route-card {
        width: 100%;
        max-width: 200px;
        justify-content: center;
    }
    
    .flight-card-modern {
        border-radius: 12px;
    }
    
    .flight-timeline {
        flex-direction: column;
        gap: 1rem;
    }
    
    .time-point {
        flex-direction: row;
        justify-content: space-between;
        width: 100%;
        text-align: left;
    }
    
    .flight-duration {
        max-width: 100%;
        width: 100%;
    }
    
    .duration-line {
        justify-content: center;
    }
    
    .segment-info {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }
    
    .segment-airport {
        flex-direction: row;
        justify-content: space-between;
    }
    
    .arrival {
        text-align: left;
    }
    
    /* UPDATED: Even more compact buttons for very small screens */
    .action-buttons-compact {
        gap: 0.375rem;
    }
    
    .btn-details-compact,
    .btn-book-now-compact,
    .btn-share-compact {
        padding: 0.375rem 0.625rem;
        font-size: 0.6875rem;
    }
    
    .btn-icon-compact {
        width: 12px;
        height: 12px;
    }
}

/* Print Styles */
@media print {
    .flight-hero-section,
    .filter-sidebar,
    .results-sidebar,
    .flight-actions-modern,
    .modal-overlay {
        display: none !important;
    }
    
    .results-layout {
        grid-template-columns: 1fr;
        padding: 0;
    }
    
    .flight-card-modern {
        box-shadow: none;
        border: 1px solid #ddd;
        break-inside: avoid;
    }
}
</style>

<?php
require_once 'includes/footer.php';
?>
