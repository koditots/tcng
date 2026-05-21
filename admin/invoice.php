<?php
// admin/invoice.php
session_start();
require_once '../config.php';

// Check if required functions exist, if not define them
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
}

if (!function_exists('isAdmin')) {
    function isAdmin() {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
    }
}

if (!function_exists('redirect')) {
    function redirect($url) {
        header("Location: $url");
        exit;
    }
}

// Authentication check
if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

$page_title = "Invoice Management";
$current_page = 'invoice'; // For active sidebar state

// Initialize variables
$invoices = [];
$error = '';
$success = '';
$search_results = [];
$selected_flight = null;

// Check if invoices table exists, if not create it
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'invoices'");
    if ($stmt->rowCount() == 0) {
        // Create invoices table
        $pdo->exec("
            CREATE TABLE invoices (
                id INT PRIMARY KEY AUTO_INCREMENT,
                invoice_number VARCHAR(100) UNIQUE NOT NULL,
                quote_reference VARCHAR(100) NOT NULL,
                tracking_id VARCHAR(100) NOT NULL,
                flight_data TEXT NOT NULL,
                passenger_data TEXT NOT NULL,
                contact_info TEXT NOT NULL,
                base_price DECIMAL(10,2) NOT NULL,
                final_price DECIMAL(10,2) NOT NULL,
                price_adjustment DECIMAL(10,2) DEFAULT 0,
                adjustment_type ENUM('none', 'percentage', 'flat') DEFAULT 'none',
                currency VARCHAR(10) DEFAULT 'NGN',
                status ENUM('quote', 'booked', 'cancelled') DEFAULT 'quote',
                payment_status ENUM('pending', 'paid', 'failed') DEFAULT 'pending',
                payment_link VARCHAR(255) DEFAULT NULL,
                created_by INT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
    }
} catch (Exception $e) {
    $error = "Database setup error: " . $e->getMessage();
}

// Get all saved invoices
try {
    $stmt = $pdo->prepare("
        SELECT * FROM invoices 
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Error loading invoices: " . $e->getMessage();
}

// Helper function for HTTP requests
if (!function_exists('httpPost')) {
    function httpPost($url, $data) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        $response = curl_exec($ch);
        if (curl_error($ch)) {
            throw new Exception('CURL Error: ' . curl_error($ch));
        }
        curl_close($ch);
        return $response;
    }
}

if (!function_exists('httpGet')) {
    function httpGet($url, $access_token = '') {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        if ($access_token) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $access_token
            ]);
        }
        
        $response = curl_exec($ch);
        if (curl_error($ch)) {
            throw new Exception('CURL Error: ' . curl_error($ch));
        }
        curl_close($ch);
        return $response;
    }
}

// Function to send email
function sendInvoiceEmail($to, $subject, $invoice_data, $payment_link, $tracking_link) {
    // In a real implementation, you would use PHPMailer or similar
    // This is a simplified version
    
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #667eea; color: white; padding: 20px; text-align: center; }
            .content { background: #f8f9fa; padding: 20px; }
            .button { display: inline-block; padding: 12px 24px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Travel Centre</h1>
                <h2>Flight Invoice #{$invoice_data['invoice_number']}</h2>
            </div>
            <div class='content'>
                <p>Dear {$invoice_data['contact_info']['name']},</p>
                <p>Your flight invoice has been generated. Here are your details:</p>
                
                <h3>Flight Details:</h3>
                <p><strong>Route:</strong> {$invoice_data['flight_route']}</p>
                <p><strong>Date:</strong> {$invoice_data['departure_date']}</p>
                <p><strong>Passengers:</strong> " . count($invoice_data['passenger_data']) . "</p>
                <p><strong>Total Amount:</strong> ₦" . number_format($invoice_data['final_price'], 2) . "</p>
                
                <h3>Important Links:</h3>
                <p>
                    <a href='{$payment_link}' class='button'>Make Payment</a>
                    <a href='{$tracking_link}' class='button'>Track Booking</a>
                </p>
                
                <p><strong>Tracking ID:</strong> {$invoice_data['tracking_id']}</p>
                <p><strong>Quote Reference:</strong> {$invoice_data['quote_reference']}</p>
                
                <p>If you have any questions, please contact us at support@travelcentre.ng or call +234 903 407 2383.</p>
            </div>
            <div class='footer'>
                <p>Travel Centre Nigeria<br>
                flight.travelcentre.ng</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Travel Centre <support@travelcentre.ng>" . "\r\n";
    
    return mail($to, $subject, $message, $headers);
}

// Process manual flight entry
if (isset($_POST['add_manual_flight'])) {
    $manual_origin = strtoupper(trim($_POST['manual_origin'] ?? ''));
    $manual_destination = strtoupper(trim($_POST['manual_destination'] ?? ''));
    $manual_departure_date = $_POST['manual_departure_date'] ?? '';
    $manual_departure_time = $_POST['manual_departure_time'] ?? '';
    $manual_arrival_time = $_POST['manual_arrival_time'] ?? '';
    $manual_airline = $_POST['manual_airline'] ?? '';
    $manual_flight_number = $_POST['manual_flight_number'] ?? '';
    $manual_flight_class = $_POST['manual_flight_class'] ?? 'ECONOMY';
    $manual_base_price = floatval($_POST['manual_base_price'] ?? 0);
    
    // Validate manual flight inputs
    if (empty($manual_origin) || empty($manual_destination) || empty($manual_departure_date) || 
        empty($manual_airline) || $manual_base_price <= 0) {
        $error = 'Please fill in all required manual flight fields (Origin, Destination, Departure Date, Airline, Base Price)';
    } else {
        // Create manual flight data structure similar to API response
        $manual_flight_data = [
            'type' => 'flight-offer',
            'source' => 'MANUAL',
            'lastTicketingDate' => date('Y-m-d', strtotime('+30 days')),
            'itineraries' => [
                [
                    'duration' => 'PT2H30M',
                    'segments' => [
                        [
                            'departure' => [
                                'iataCode' => $manual_origin,
                                'at' => $manual_departure_date . 'T' . $manual_departure_time . ':00'
                            ],
                            'arrival' => [
                                'iataCode' => $manual_destination,
                                'at' => $manual_departure_date . 'T' . $manual_arrival_time . ':00'
                            ],
                            'carrierCode' => $manual_airline,
                            'number' => $manual_flight_number,
                            'aircraft' => ['code' => '320'],
                            'operating' => ['carrierCode' => $manual_airline],
                            'duration' => 'PT2H30M',
                            'id' => '1',
                            'numberOfStops' => 0,
                            'blacklistedInEU' => false
                        ]
                    ]
                ]
            ],
            'price' => [
                'currency' => 'NGN',
                'total' => strval($manual_base_price),
                'base' => strval($manual_base_price),
                'grandTotal' => $manual_base_price
            ],
            'pricingOptions' => [
                'fareType' => ['PUBLISHED'],
                'includedCheckedBagsOnly' => true
            ],
            'validatingAirlineCodes' => [$manual_airline],
            'travelerPricings' => [
                [
                    'travelerId' => '1',
                    'fareOption' => 'STANDARD',
                    'travelerType' => 'ADULT',
                    'price' => [
                        'currency' => 'NGN',
                        'total' => strval($manual_base_price),
                        'base' => strval($manual_base_price)
                    ],
                    'fareDetailsBySegment' => [
                        [
                            'segmentId' => '1',
                            'cabin' => $manual_flight_class,
                            'fareBasis' => 'Y26',
                            'class' => 'Y',
                            'includedCheckedBags' => [
                                'weight' => 20,
                                'weightUnit' => 'KG'
                            ]
                        ]
                    ]
                ]
            ]
        ];
        
        $selected_flight = $manual_flight_data;
        $success = "Manual flight added successfully. Please fill in customer details.";
    }
}

// Process flight search - FIXED API ISSUE
if (isset($_POST['search_flights'])) {
    $origin = strtoupper(trim($_POST['origin'] ?? ''));
    $destination = strtoupper(trim($_POST['destination'] ?? ''));
    $departure_date = $_POST['departure_date'] ?? '';
    $return_date = $_POST['return_date'] ?? '';
    $passengers = intval($_POST['passengers'] ?? 1);
    $travel_class = $_POST['travel_class'] ?? 'ECONOMY';
    $trip_type = $_POST['trip_type'] ?? 'one_way';

    // Validate inputs
    if (empty($origin) || empty($destination) || empty($departure_date)) {
        $error = 'Please fill in all required fields (Origin, Destination, Departure Date)';
    } else {
        try {
            // Check if Amadeus constants are defined - FIXED: Use fallback if not defined
            $amadeus_base_url = defined('AMADEUS_BASE_URL') ? AMADEUS_BASE_URL : 'https://test.api.amadeus.com';
            $amadeus_api_key = defined('AMADEUS_API_KEY_DB') ? AMADEUS_API_KEY_DB : '';
            $amadeus_api_secret = defined('AMADEUS_API_SECRET_DB') ? AMADEUS_API_SECRET_DB : '';

            if (empty($amadeus_api_key) || empty($amadeus_api_secret)) {
                throw new Exception('Amadeus API credentials are not configured. Please check your config.php file.');
            }

            // Authenticate with Amadeus API
            $auth_url = $amadeus_base_url . '/v1/security/oauth2/token';
            $auth_data = [
                'grant_type' => 'client_credentials',
                'client_id' => $amadeus_api_key,
                'client_secret' => $amadeus_api_secret
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
                    'max' => 10
                ];
                
                if (!empty($travel_class) && $travel_class !== 'ECONOMY') {
                    $search_params['travelClass'] = $travel_class;
                }
                
                if ($return_date && $trip_type === 'round_trip') {
                    $search_params['returnDate'] = $return_date;
                }

                // Search flights
                $search_url = $amadeus_base_url . '/v2/shopping/flight-offers?' . http_build_query($search_params);
                $search_response = httpGet($search_url, $access_token);
                $search_result = json_decode($search_response, true);

                if (isset($search_result['data']) && is_array($search_result['data']) && !empty($search_result['data'])) {
                    $search_results = $search_result['data'];
                    $success = count($search_results) . " flights found from Amadeus API";
                } else {
                    if (isset($search_result['errors'])) {
                        $api_error = $search_result['errors'][0]['detail'] ?? $search_result['errors'][0]['title'] ?? 'Unknown API error';
                        $error = 'Amadeus API Error: ' . $api_error;
                    } else {
                        $error = 'No flights found from Amadeus API for your search criteria.';
                    }
                    
                    // Show sample flights if API returns no results (for testing)
                    $search_results = getSampleFlights($origin, $destination, $departure_date);
                    $success = "Sample flights shown (Amadeus API returned no results)";
                }
            } else {
                $auth_error = $auth_result['error_description'] ?? $auth_result['error'] ?? 'Unknown authentication error';
                $error = 'Amadeus API Authentication failed: ' . $auth_error;
                
                // Show sample flights if authentication fails (for testing)
                $search_results = getSampleFlights($origin, $destination, $departure_date);
                $success = "Sample flights shown (Authentication failed)";
            }
        } catch (Exception $e) {
            $error = 'Error searching flights: ' . $e->getMessage();
            
            // Show sample flights on error (for testing)
            $search_results = getSampleFlights($origin, $destination, $departure_date);
            $success = "Sample flights shown (Error occurred: " . $e->getMessage() . ")";
        }
    }
}

// Function to generate sample flights for testing
function getSampleFlights($origin, $destination, $departure_date) {
    $sample_flights = [];
    $airlines = ['Air Peace', 'Dana Air', 'Arik Air', 'Ibom Air', 'United Nigeria'];
    $airline_codes = ['P4', '9J', 'W3', 'QI', 'UN'];
    $prices = [45000, 52000, 48000, 55000, 51000];
    
    for ($i = 0; $i < 5; $i++) {
        $departure_time = date('H:i', strtotime('08:00 +' . ($i * 2) . ' hours'));
        $arrival_time = date('H:i', strtotime($departure_time . ' +2 hours'));
        
        $sample_flights[] = [
            'type' => 'flight-offer',
            'source' => 'SAMPLE',
            'lastTicketingDate' => date('Y-m-d', strtotime('+30 days')),
            'itineraries' => [
                [
                    'duration' => 'PT2H',
                    'segments' => [
                        [
                            'departure' => [
                                'iataCode' => $origin,
                                'at' => $departure_date . 'T' . $departure_time . ':00'
                            ],
                            'arrival' => [
                                'iataCode' => $destination,
                                'at' => $departure_date . 'T' . $arrival_time . ':00'
                            ],
                            'carrierCode' => $airline_codes[$i],
                            'number' => 'N' . (100 + $i),
                            'aircraft' => ['code' => '320'],
                            'operating' => ['carrierCode' => $airline_codes[$i]],
                            'duration' => 'PT2H',
                            'id' => ($i + 1),
                            'numberOfStops' => 0,
                            'blacklistedInEU' => false
                        ]
                    ]
                ]
            ],
            'price' => [
                'currency' => 'NGN',
                'total' => strval($prices[$i]),
                'base' => strval($prices[$i]),
                'grandTotal' => $prices[$i]
            ],
            'pricingOptions' => [
                'fareType' => ['PUBLISHED'],
                'includedCheckedBagsOnly' => true
            ],
            'validatingAirlineCodes' => [$airline_codes[$i]],
            'travelerPricings' => [
                [
                    'travelerId' => '1',
                    'fareOption' => 'STANDARD',
                    'travelerType' => 'ADULT',
                    'price' => [
                        'currency' => 'NGN',
                        'total' => strval($prices[$i]),
                        'base' => strval($prices[$i])
                    ],
                    'fareDetailsBySegment' => [
                        [
                            'segmentId' => ($i + 1),
                            'cabin' => 'ECONOMY',
                            'fareBasis' => 'Y26',
                            'class' => 'Y',
                            'includedCheckedBags' => [
                                'weight' => 20,
                                'weightUnit' => 'KG'
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }
    
    return $sample_flights;
}

// Process flight selection
if (isset($_POST['select_flight'])) {
    $flight_data_json = $_POST['flight_data'] ?? '';
    if ($flight_data_json) {
        $flight_data = json_decode($flight_data_json, true);
        if ($flight_data) {
            $selected_flight = $flight_data;
            $success = "Flight selected. Please fill in customer details.";
        } else {
            $error = "Invalid flight data";
        }
    }
}

// Process invoice generation
if (isset($_POST['generate_invoice'])) {
    $flight_data_json = $_POST['flight_data'] ?? '';
    $passenger_data_json = $_POST['passenger_data'] ?? '';
    $contact_info_json = $_POST['contact_info'] ?? '';
    $price_adjustment = floatval($_POST['price_adjustment'] ?? 0);
    $adjustment_type = $_POST['adjustment_type'] ?? 'none';
    
    $flight_data = json_decode($flight_data_json, true);
    $passenger_data = json_decode($passenger_data_json, true);
    $contact_info = json_decode($contact_info_json, true);
    
    if ($flight_data && $passenger_data && $contact_info) {
        try {
            // Calculate final price
            $base_price = floatval($flight_data['price']['grandTotal'] ?? 0);
            $final_price = $base_price;
            
            // Apply price adjustment
            if ($adjustment_type === 'percentage' && $price_adjustment != 0) {
                $adjustment = ($base_price * $price_adjustment) / 100;
                $final_price = $base_price + $adjustment;
            } elseif ($adjustment_type === 'flat' && $price_adjustment != 0) {
                $final_price = $base_price + $price_adjustment;
            }
            
            // Ensure final price is not negative
            if ($final_price < 0) {
                $final_price = 0;
            }
            
            // Generate invoice numbers
            $invoice_number = 'TC-INV-' . date('Ymd') . '-' . strtoupper(uniqid());
            $quote_reference = 'QTE-' . date('Ymd') . '-' . strtoupper(uniqid());
            $tracking_id = 'TRK-' . strtoupper(uniqid());
            
            // Generate payment link
            $payment_link = '../payment.php?invoice=' . urlencode($invoice_number) . '&tracking=' . urlencode($tracking_id);
            
            // Prepare invoice data
            $invoice_data = [
                'invoice_number' => $invoice_number,
                'quote_reference' => $quote_reference,
                'tracking_id' => $tracking_id,
                'flight_data' => $flight_data,
                'passenger_data' => $passenger_data,
                'contact_info' => $contact_info,
                'base_price' => $base_price,
                'final_price' => $final_price,
                'price_adjustment' => $price_adjustment,
                'adjustment_type' => $adjustment_type,
                'currency' => 'NGN',
                'status' => 'quote',
                'payment_status' => 'pending',
                'payment_link' => $payment_link,
                'created_by' => $_SESSION['user_id'],
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            // Save to database
            $stmt = $pdo->prepare("
                INSERT INTO invoices (
                    invoice_number, quote_reference, tracking_id, flight_data, 
                    passenger_data, contact_info, base_price, final_price,
                    price_adjustment, adjustment_type, currency, status, 
                    payment_status, payment_link, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $invoice_data['invoice_number'],
                $invoice_data['quote_reference'],
                $invoice_data['tracking_id'],
                json_encode($invoice_data['flight_data']),
                json_encode($invoice_data['passenger_data']),
                json_encode($invoice_data['contact_info']),
                $invoice_data['base_price'],
                $invoice_data['final_price'],
                $invoice_data['price_adjustment'],
                $invoice_data['adjustment_type'],
                $invoice_data['currency'],
                $invoice_data['status'],
                $invoice_data['payment_status'],
                $invoice_data['payment_link'],
                $invoice_data['created_by']
            ]);
            
            $invoice_id = $pdo->lastInsertId();
            
            // Send email to customer
            $flight_route = '';
            if (isset($flight_data['itineraries'][0]['segments'][0])) {
                $first_segment = $flight_data['itineraries'][0]['segments'][0];
                $last_segment = end($flight_data['itineraries'][0]['segments']);
                $flight_route = $first_segment['departure']['iataCode'] . ' → ' . $last_segment['arrival']['iataCode'];
            }
            
            $departure_date = '';
            if (isset($first_segment['departure']['at'])) {
                $departure_date = date('M j, Y', strtotime($first_segment['departure']['at']));
            }
            
            $email_invoice_data = [
                'invoice_number' => $invoice_number,
                'contact_info' => $contact_info,
                'flight_route' => $flight_route,
                'departure_date' => $departure_date,
                'passenger_data' => $passenger_data,
                'final_price' => $final_price,
                'tracking_id' => $tracking_id,
                'quote_reference' => $quote_reference
            ];
            
            $tracking_link = '../track-ticket.php?tracking_id=' . urlencode($tracking_id);
            $email_sent = sendInvoiceEmail(
                $contact_info['email'],
                "Flight Invoice #{$invoice_number} - Travel Centre",
                $email_invoice_data,
                $payment_link,
                $tracking_link
            );
            
            if ($email_sent) {
                $success = "Invoice generated successfully! Invoice #: " . $invoice_number . " - Email sent to customer with payment link.";
            } else {
                $success = "Invoice generated successfully! Invoice #: " . $invoice_number . " - Could not send email, please send payment link manually.";
            }
            
            // Clear selected flight after successful generation
            $selected_flight = null;
            $search_results = [];
            
        } catch (Exception $e) {
            $error = "Error generating invoice: " . $e->getMessage();
        }
    } else {
        $error = "Missing required data for invoice generation";
    }
}

// Process invoice actions (view, edit, delete, send payment link)
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $invoice_id = intval($_GET['id'] ?? 0);
    
    if ($invoice_id) {
        try {
            if ($action === 'delete') {
                $stmt = $pdo->prepare("DELETE FROM invoices WHERE id = ?");
                $stmt->execute([$invoice_id]);
                $success = "Invoice deleted successfully";
                
                // Refresh invoices list
                $stmt = $pdo->prepare("SELECT * FROM invoices ORDER BY created_at DESC");
                $stmt->execute();
                $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } elseif ($action === 'send_payment_link') {
                // Send payment link email
                $stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
                $stmt->execute([$invoice_id]);
                $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($invoice) {
                    $invoice_data = [
                        'invoice_number' => $invoice['invoice_number'],
                        'contact_info' => json_decode($invoice['contact_info'], true),
                        'flight_data' => json_decode($invoice['flight_data'], true),
                        'passenger_data' => json_decode($invoice['passenger_data'], true),
                        'final_price' => $invoice['final_price'],
                        'tracking_id' => $invoice['tracking_id'],
                        'quote_reference' => $invoice['quote_reference']
                    ];
                    
                    // Extract flight route
                    $flight_route = '';
                    if (isset($invoice_data['flight_data']['itineraries'][0]['segments'][0])) {
                        $first_segment = $invoice_data['flight_data']['itineraries'][0]['segments'][0];
                        $last_segment = end($invoice_data['flight_data']['itineraries'][0]['segments']);
                        $flight_route = $first_segment['departure']['iataCode'] . ' → ' . $last_segment['arrival']['iataCode'];
                    }
                    
                    $invoice_data['flight_route'] = $flight_route;
                    
                    $departure_date = '';
                    if (isset($first_segment['departure']['at'])) {
                        $departure_date = date('M j, Y', strtotime($first_segment['departure']['at']));
                    }
                    $invoice_data['departure_date'] = $departure_date;
                    
                    $payment_link = '../payment.php?invoice=' . urlencode($invoice['invoice_number']) . '&tracking=' . urlencode($invoice['tracking_id']);
                    $tracking_link = '../track-ticket.php?tracking_id=' . urlencode($invoice['tracking_id']);
                    
                    $email_sent = sendInvoiceEmail(
                        $invoice_data['contact_info']['email'],
                        "Flight Invoice #{$invoice['invoice_number']} - Travel Centre",
                        $invoice_data,
                        $payment_link,
                        $tracking_link
                    );
                    
                    if ($email_sent) {
                        $success = "Payment link sent successfully to " . $invoice_data['contact_info']['email'];
                    } else {
                        $error = "Failed to send email. Please try again or check email configuration.";
                    }
                }
            }
        } catch (Exception $e) {
            $error = "Error processing invoice action: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f8f9fa;
            display: flex;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 0;
        }
        
        .top-bar {
            background: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .content {
            padding: 2rem;
        }
        
        /* Cards */
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .card-header {
            padding: 1.5rem;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        
        .card-header h3 {
            margin: 0;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        /* Forms */
        .form-group {
            margin-bottom: 1rem;
            position: relative;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: bold;
            color: #333;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        /* Buttons */
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-size: 1rem;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0056b3;
            transform: translateY(-1px);
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #1e7e34;
            transform: translateY(-1px);
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-1px);
        }
        
        .btn-info {
            background: #17a2b8;
            color: white;
        }
        
        .btn-info:hover {
            background: #138496;
            transform: translateY(-1px);
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-warning:hover {
            background: #e0a800;
            transform: translateY(-1px);
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }
        
        /* Tables */
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        
        th {
            background: #f8f9fa;
            font-weight: bold;
            color: #333;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        /* Flight Cards */
        .flight-cards {
            display: grid;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .flight-card {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .flight-card:hover {
            border-color: #007bff;
            box-shadow: 0 2px 8px rgba(0,123,255,0.2);
        }
        
        .flight-card.selected {
            border-color: #28a745;
            background: #f8fff9;
        }
        
        .flight-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .flight-price {
            font-size: 1.25rem;
            font-weight: bold;
            color: #28a745;
        }
        
        .flight-details {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            gap: 1rem;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .flight-time {
            text-align: center;
        }
        
        .flight-route {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        /* Manual Flight Form */
        .manual-flight-form {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .manual-flight-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
            color: #856404;
        }
        
        /* Passenger Form */
        .passenger-form {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        
        .passenger-item {
            background: white;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            border: 1px solid #dee2e6;
            position: relative;
        }
        
        .remove-passenger {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
        }
        
        /* Alerts */
        .alert {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            border: 1px solid transparent;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }
        
        /* Tabs */
        .tabs {
            display: flex;
            border-bottom: 1px solid #dee2e6;
            margin-bottom: 1rem;
            background: white;
            border-radius: 5px 5px 0 0;
        }
        
        .tab {
            padding: 1rem 1.5rem;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }
        
        .tab.active {
            border-bottom-color: #007bff;
            color: #007bff;
            font-weight: bold;
        }
        
        .tab:hover:not(.active) {
            background: #f8f9fa;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Price Adjustment */
        .price-adjustment {
            background: #fff3cd;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            border: 1px solid #ffeaa7;
        }
        
        .price-summary {
            background: #e7f3ff;
            padding: 1rem;
            border-radius: 5px;
            margin-top: 1rem;
            text-align: center;
        }
        
        .final-price {
            font-size: 1.5rem;
            font-weight: bold;
            color: #007bff;
        }
        
        /* Status badges */
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: bold;
        }
        
        .status-quote {
            background: #e6fffa;
            color: #065f46;
        }
        
        .status-booked {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .payment-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .payment-paid {
            background: #d4edda;
            color: #155724;
        }
        
        .payment-failed {
            background: #f8d7da;
            color: #721c24;
        }
        
        /* Loading Spinner */
        .loading-spinner {
            display: none;
            text-align: center;
            padding: 2rem;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #007bff;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Autocomplete */
        .autocomplete-container {
            position: relative;
        }
        
        .autocomplete-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-top: none;
            border-radius: 0 0 5px 5px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .autocomplete-item {
            padding: 0.75rem;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .autocomplete-item:hover {
            background: #f8f9fa;
        }
        
        .autocomplete-item:last-child {
            border-bottom: none;
        }
        
        .airport-code {
            font-weight: bold;
            color: #007bff;
        }
        
        .airport-name {
            color: #666;
            font-size: 0.9rem;
        }
        
        .city-name {
            font-weight: 500;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .flight-details {
                grid-template-columns: 1fr;
                text-align: center;
            }
            
            .tabs {
                flex-direction: column;
            }
            
            .tab {
                text-align: center;
            }
            
            .autocomplete-results {
                position: relative;
                top: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Include Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <h1>Invoice Management</h1>
            <div>
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></span>
            </div>
        </div>

        <!-- Content -->
        <div class="content">
            <!-- Alerts -->
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Tabs -->
            <div class="tabs">
                <div class="tab active" onclick="showTab('search')">Search Flights</div>
                <div class="tab" onclick="showTab('manual')">Manual Flight Entry</div>
                <div class="tab" onclick="showTab('invoices')">Manage Invoices</div>
            </div>

            <!-- Search Flights Tab -->
            <div id="search-tab" class="tab-content active">
                <div class="card">
                    <div class="card-header">
                        <h3>Search Flights via API</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="flightSearchForm">
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Trip Type</label>
                                    <select name="trip_type" class="form-control" onchange="toggleReturnDate()">
                                        <option value="one_way" <?php echo ($_POST['trip_type'] ?? 'one_way') === 'one_way' ? 'selected' : ''; ?>>One Way</option>
                                        <option value="round_trip" <?php echo ($_POST['trip_type'] ?? '') === 'round_trip' ? 'selected' : ''; ?>>Round Trip</option>
                                    </select>
                                </div>
                                <div class="form-group autocomplete-container">
                                    <label class="form-label">Origin (City or Airport)</label>
                                    <input type="text" name="origin" id="originInput" class="form-control" placeholder="e.g., Lagos or LOS" value="<?php echo htmlspecialchars($_POST['origin'] ?? ''); ?>" required>
                                    <div class="autocomplete-results" id="originResults"></div>
                                </div>
                                <div class="form-group autocomplete-container">
                                    <label class="form-label">Destination (City or Airport)</label>
                                    <input type="text" name="destination" id="destinationInput" class="form-control" placeholder="e.g., Abuja or ABV" value="<?php echo htmlspecialchars($_POST['destination'] ?? ''); ?>" required>
                                    <div class="autocomplete-results" id="destinationResults"></div>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Departure Date</label>
                                    <input type="date" name="departure_date" class="form-control" value="<?php echo htmlspecialchars($_POST['departure_date'] ?? ''); ?>" required min="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="form-group" id="returnDateGroup" style="display: <?php echo ($_POST['trip_type'] ?? 'one_way') === 'round_trip' ? 'block' : 'none'; ?>;">
                                    <label class="form-label">Return Date</label>
                                    <input type="date" name="return_date" class="form-control" value="<?php echo htmlspecialchars($_POST['return_date'] ?? ''); ?>" min="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Passengers</label>
                                    <input type="number" name="passengers" class="form-control" value="<?php echo htmlspecialchars($_POST['passengers'] ?? 1); ?>" min="1" max="9">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Class</label>
                                    <select name="travel_class" class="form-control">
                                        <option value="ECONOMY" <?php echo ($_POST['travel_class'] ?? 'ECONOMY') === 'ECONOMY' ? 'selected' : ''; ?>>Economy</option>
                                        <option value="PREMIUM_ECONOMY" <?php echo ($_POST['travel_class'] ?? '') === 'PREMIUM_ECONOMY' ? 'selected' : ''; ?>>Premium Economy</option>
                                        <option value="BUSINESS" <?php echo ($_POST['travel_class'] ?? '') === 'BUSINESS' ? 'selected' : ''; ?>>Business</option>
                                        <option value="FIRST" <?php echo ($_POST['travel_class'] ?? '') === 'FIRST' ? 'selected' : ''; ?>>First Class</option>
                                    </select>
                                </div>
                            </div>
                            
                            <button type="submit" name="search_flights" class="btn btn-primary" id="searchButton">
                                <i class="fas fa-search"></i> Search Flights
                            </button>
                        </form>

                        <!-- Loading Spinner -->
                        <div class="loading-spinner" id="loadingSpinner">
                            <div class="spinner"></div>
                            <p>Searching for flights... Please wait</p>
                        </div>
                    </div>
                </div>

                <!-- Flight Results -->
                <?php if (!empty($search_results)): ?>
                    <div class="card">
                        <div class="card-header">
                            <h3>Available Flights</h3>
                            <p class="text-muted">
                                <?php 
                                $first_flight = $search_results[0];
                                $source = $first_flight['source'] ?? 'API';
                                if ($source === 'SAMPLE') {
                                    echo '<span style="color: #856404;"><i class="fas fa-exclamation-triangle"></i> Showing sample flights - Amadeus API not available</span>';
                                } else {
                                    echo '<span style="color: #155724;"><i class="fas fa-check-circle"></i> Live flights from Amadeus API</span>';
                                }
                                ?>
                            </p>
                        </div>
                        <div class="card-body">
                            <div class="flight-cards">
                                <?php foreach ($search_results as $index => $flight): ?>
                                    <?php
                                    $itinerary = $flight['itineraries'][0] ?? [];
                                    $first_segment = $itinerary['segments'][0] ?? [];
                                    $last_segment = end($itinerary['segments']) ?? [];
                                    $price = $flight['price']['grandTotal'] ?? 0;
                                    $departure_time = !empty($first_segment['departure']['at']) ? date('H:i', strtotime($first_segment['departure']['at'])) : '';
                                    $arrival_time = !empty($last_segment['arrival']['at']) ? date('H:i', strtotime($last_segment['arrival']['at'])) : '';
                                    $source = $flight['source'] ?? 'API';
                                    ?>
                                    <div class="flight-card" onclick="selectFlight(<?php echo $index; ?>, this)">
                                        <div class="flight-header">
                                            <strong><?php echo $first_segment['departure']['iataCode'] ?? ''; ?> → <?php echo $last_segment['arrival']['iataCode'] ?? ''; ?></strong>
                                            <div class="flight-price">₦<?php echo number_format($price, 2); ?></div>
                                        </div>
                                        <div class="flight-details">
                                            <div>
                                                <strong>Departure:</strong><br>
                                                <?php echo $departure_time; ?>
                                            </div>
                                            <div>→</div>
                                            <div>
                                                <strong>Arrival:</strong><br>
                                                <?php echo $arrival_time; ?>
                                            </div>
                                        </div>
                                        <div class="flight-route">
                                            <small>Airline: <?php echo $first_segment['carrierCode'] ?? ''; ?></small>
                                            <small>Stops: <?php echo count($itinerary['segments'] ?? []) - 1; ?></small>
                                            <small>Source: <?php echo $source; ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Manual Flight Entry Tab -->
            <div id="manual-tab" class="tab-content">
                <div class="card">
                    <div class="card-header">
                        <h3>Add Flight Manually</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="manualFlightForm">
                            <div class="manual-flight-form">
                                <div class="manual-flight-header">
                                    <i class="fas fa-plane fa-lg"></i>
                                    <h4>Flight Details</h4>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group autocomplete-container">
                                        <label class="form-label">Origin *</label>
                                        <input type="text" name="manual_origin" id="manualOriginInput" class="form-control" placeholder="e.g., LOS" value="<?php echo htmlspecialchars($_POST['manual_origin'] ?? ''); ?>" required maxlength="3">
                                        <div class="autocomplete-results" id="manualOriginResults"></div>
                                    </div>
                                    <div class="form-group autocomplete-container">
                                        <label class="form-label">Destination *</label>
                                        <input type="text" name="manual_destination" id="manualDestinationInput" class="form-control" placeholder="e.g., ABV" value="<?php echo htmlspecialchars($_POST['manual_destination'] ?? ''); ?>" required maxlength="3">
                                        <div class="autocomplete-results" id="manualDestinationResults"></div>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Departure Date *</label>
                                        <input type="date" name="manual_departure_date" class="form-control" value="<?php echo htmlspecialchars($_POST['manual_departure_date'] ?? ''); ?>" required min="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label">Departure Time *</label>
                                        <input type="time" name="manual_departure_time" class="form-control" value="<?php echo htmlspecialchars($_POST['manual_departure_time'] ?? '08:00'); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Arrival Time *</label>
                                        <input type="time" name="manual_arrival_time" class="form-control" value="<?php echo htmlspecialchars($_POST['manual_arrival_time'] ?? '10:30'); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Airline *</label>
                                        <input type="text" name="manual_airline" class="form-control" placeholder="e.g., Air Peace, Dana Air" value="<?php echo htmlspecialchars($_POST['manual_airline'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label">Flight Number</label>
                                        <input type="text" name="manual_flight_number" class="form-control" placeholder="e.g., P47123" value="<?php echo htmlspecialchars($_POST['manual_flight_number'] ?? ''); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Class</label>
                                        <select name="manual_flight_class" class="form-control">
                                            <option value="ECONOMY" <?php echo ($_POST['manual_flight_class'] ?? 'ECONOMY') === 'ECONOMY' ? 'selected' : ''; ?>>Economy</option>
                                            <option value="PREMIUM_ECONOMY" <?php echo ($_POST['manual_flight_class'] ?? '') === 'PREMIUM_ECONOMY' ? 'selected' : ''; ?>>Premium Economy</option>
                                            <option value="BUSINESS" <?php echo ($_POST['manual_flight_class'] ?? '') === 'BUSINESS' ? 'selected' : ''; ?>>Business</option>
                                            <option value="FIRST" <?php echo ($_POST['manual_flight_class'] ?? '') === 'FIRST' ? 'selected' : ''; ?>>First Class</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Base Price (₦) *</label>
                                        <input type="number" name="manual_base_price" class="form-control" value="<?php echo htmlspecialchars($_POST['manual_base_price'] ?? '50000'); ?>" min="0" step="0.01" required>
                                    </div>
                                </div>
                                
                                <button type="submit" name="add_manual_flight" class="btn btn-warning">
                                    <i class="fas fa-plus-circle"></i> Add Flight Manually
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Customer Details Form (Shown when flight is selected from either method) -->
            <?php if ($selected_flight): ?>
                <div class="card">
                    <div class="card-header">
                        <h3>Customer Details & Price Adjustment</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="invoiceForm">
                            <input type="hidden" name="flight_data" id="flightDataInput" value='<?php echo htmlspecialchars(json_encode($selected_flight)); ?>'>
                            
                            <!-- Flight Summary -->
                            <div class="price-adjustment">
                                <h4>Flight Summary</h4>
                                <?php
                                $itinerary = $selected_flight['itineraries'][0] ?? [];
                                $first_segment = $itinerary['segments'][0] ?? [];
                                $last_segment = end($itinerary['segments']) ?? [];
                                $base_price = $selected_flight['price']['grandTotal'] ?? 0;
                                $departure_time = !empty($first_segment['departure']['at']) ? date('H:i', strtotime($first_segment['departure']['at'])) : '';
                                $arrival_time = !empty($last_segment['arrival']['at']) ? date('H:i', strtotime($last_segment['arrival']['at'])) : '';
                                $flight_route = ($first_segment['departure']['iataCode'] ?? '') . ' → ' . ($last_segment['arrival']['iataCode'] ?? '');
                                $airline = $first_segment['carrierCode'] ?? '';
                                $flight_class = $selected_flight['travelerPricings'][0]['fareDetailsBySegment'][0]['cabin'] ?? 'Economy';
                                $source = $selected_flight['source'] ?? 'API';
                                ?>
                                
                                <div style="background: white; padding: 1rem; border-radius: 5px; margin-bottom: 1rem;">
                                    <div class="flight-header">
                                        <strong><?php echo $flight_route; ?></strong>
                                        <div class="flight-price">₦<?php echo number_format($base_price, 2); ?></div>
                                    </div>
                                    <div class="flight-details">
                                        <div>
                                            <strong>Departure:</strong><br>
                                            <?php echo $departure_time; ?>
                                        </div>
                                        <div>→</div>
                                        <div>
                                            <strong>Arrival:</strong><br>
                                            <?php echo $arrival_time; ?>
                                        </div>
                                    </div>
                                    <div class="flight-route">
                                        <small>Airline: <?php echo $airline; ?></small>
                                        <small>Class: <?php echo $flight_class; ?></small>
                                        <small>Source: <?php echo $source; ?></small>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Price Adjustment -->
                            <div class="price-adjustment">
                                <h4>Price Adjustment</h4>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label">Base Price</label>
                                        <input type="text" class="form-control" value="₦<?php echo number_format($base_price, 2); ?>" readonly>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Adjustment Type</label>
                                        <select name="adjustment_type" class="form-control" onchange="updatePriceSummary()">
                                            <option value="none">No Adjustment</option>
                                            <option value="percentage">Percentage (%)</option>
                                            <option value="flat">Flat Amount</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Adjustment Value</label>
                                        <input type="number" name="price_adjustment" class="form-control" value="0" step="0.01" onchange="updatePriceSummary()" oninput="updatePriceSummary()">
                                    </div>
                                </div>
                                <div class="price-summary">
                                    <div class="final-price" id="finalPrice">₦<?php echo number_format($base_price, 2); ?></div>
                                    <div>Final Amount</div>
                                </div>
                            </div>

                            <!-- Contact Information -->
                            <h4>Contact Information</h4>
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Full Name *</label>
                                    <input type="text" name="contact_name" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Email *</label>
                                    <input type="email" name="contact_email" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Phone *</label>
                                    <input type="tel" name="contact_phone" class="form-control" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Address *</label>
                                <textarea name="contact_address" class="form-control" rows="3" required placeholder="Full address including city and state"></textarea>
                            </div>

                            <!-- Passenger Details -->
                            <h4>Passenger Details</h4>
                            <div id="passengerForms">
                                <div class="passenger-item">
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label class="form-label">Full Name *</label>
                                            <input type="text" name="passenger_names[]" class="form-control" required>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Email *</label>
                                            <input type="email" name="passenger_emails[]" class="form-control" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="button" class="btn btn-primary" onclick="addPassenger()">
                                <i class="fas fa-plus"></i> Add Passenger
                            </button>
                            <button type="submit" name="generate_invoice" class="btn btn-success">
                                <i class="fas fa-file-invoice"></i> Generate Invoice & Send Payment Link
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Manage Invoices Tab -->
            <div id="invoices-tab" class="tab-content">
                <div class="card">
                    <div class="card-header">
                        <h3>Manage Invoices</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($invoices)): ?>
                            <div style="text-align: center; padding: 2rem; color: #6c757d;">
                                <i class="fas fa-file-invoice fa-3x" style="margin-bottom: 1rem;"></i>
                                <p>No invoices found. Generate your first invoice using the Search Flights or Manual Flight Entry tabs.</p>
                            </div>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Invoice #</th>
                                        <th>Customer</th>
                                        <th>Route</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($invoices as $invoice): 
                                        $contact_info = json_decode($invoice['contact_info'], true);
                                        $flight_data = json_decode($invoice['flight_data'], true);
                                        
                                        $flight_route = 'N/A';
                                        if (isset($flight_data['itineraries'][0]['segments'][0])) {
                                            $first_segment = $flight_data['itineraries'][0]['segments'][0];
                                            $last_segment = end($flight_data['itineraries'][0]['segments']);
                                            $flight_route = $first_segment['departure']['iataCode'] . ' → ' . $last_segment['arrival']['iataCode'];
                                        }
                                    ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($invoice['invoice_number']); ?></td>
                                            <td><?php echo htmlspecialchars($contact_info['name'] ?? 'N/A'); ?></td>
                                            <td><?php echo $flight_route; ?></td>
                                            <td>₦<?php echo number_format($invoice['final_price'], 2); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo $invoice['status']; ?>">
                                                    <?php echo ucfirst($invoice['status']); ?>
                                                </span>
                                                <br>
                                                <span class="status-badge payment-<?php echo $invoice['payment_status']; ?>">
                                                    <?php echo ucfirst($invoice['payment_status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($invoice['created_at'])); ?></td>
                                            <td>
                                                <div style="display: flex; gap: 0.25rem; flex-wrap: wrap;">
                                                    <a href="invoice-preview.php?id=<?php echo $invoice['id']; ?>" 
                                                       class="btn btn-primary btn-sm" target="_blank">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                    <a href="invoice-preview.php?id=<?php echo $invoice['id']; ?>&download=1" 
                                                       class="btn btn-success btn-sm">
                                                        <i class="fas fa-download"></i> Download
                                                    </a>
                                                    <a href="?action=send_payment_link&id=<?php echo $invoice['id']; ?>" class="btn btn-info btn-sm">
                                                        <i class="fas fa-paper-plane"></i> Share
                                                    </a>
                                                    <a href="?action=delete&id=<?php echo $invoice['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this invoice?')">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Airport data for autocomplete
        const airports = [
            { code: "LOS", name: "Murtala Muhammed International Airport", city: "Lagos", country: "Nigeria" },
            { code: "ABV", name: "Nnamdi Azikiwe International Airport", city: "Abuja", country: "Nigeria" },
            { code: "KAN", name: "Mallam Aminu Kano International Airport", city: "Kano", country: "Nigeria" },
            { code: "PHC", name: "Port Harcourt International Airport", city: "Port Harcourt", country: "Nigeria" },
            { code: "QUO", name: "Akwa Ibom International Airport", city: "Uyo", country: "Nigeria" },
            { code: "IBA", name: "Ibadan Airport", city: "Ibadan", country: "Nigeria" },
            { code: "ENU", name: "Akanu Ibiam International Airport", city: "Enugu", country: "Nigeria" },
            { code: "CBQ", name: "Margaret Ekpo International Airport", city: "Calabar", country: "Nigeria" },
            { code: "ABB", name: "Asaba International Airport", city: "Asaba", country: "Nigeria" },
            { code: "BNI", name: "Benin Airport", city: "Benin City", country: "Nigeria" },
            { code: "JFK", name: "John F. Kennedy International Airport", city: "New York", country: "USA" },
            { code: "LAX", name: "Los Angeles International Airport", city: "Los Angeles", country: "USA" },
            { code: "LHR", name: "Heathrow Airport", city: "London", country: "UK" },
            { code: "CDG", name: "Charles de Gaulle Airport", city: "Paris", country: "France" },
            { code: "DXB", name: "Dubai International Airport", city: "Dubai", country: "UAE" },
            { code: "ADD", name: "Addis Ababa Bole International Airport", city: "Addis Ababa", country: "Ethiopia" },
            { code: "NBO", name: "Jomo Kenyatta International Airport", city: "Nairobi", country: "Kenya" },
            { code: "ACC", name: "Kotoka International Airport", city: "Accra", country: "Ghana" },
            { code: "DKR", name: "Blaise Diagne International Airport", city: "Dakar", country: "Senegal" },
            { code: "JNB", name: "O.R. Tambo International Airport", city: "Johannesburg", country: "South Africa" }
        ];

        // Tab functionality
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            event.currentTarget.classList.add('active');
        }

        // Return date toggle
        function toggleReturnDate() {
            const tripType = document.querySelector('select[name="trip_type"]').value;
            const returnDateGroup = document.getElementById('returnDateGroup');
            
            if (tripType === 'round_trip') {
                returnDateGroup.style.display = 'block';
                returnDateGroup.querySelector('input').setAttribute('required', 'required');
            } else {
                returnDateGroup.style.display = 'none';
                returnDateGroup.querySelector('input').removeAttribute('required');
            }
        }

        // Flight selection - FIXED: Added element parameter
        function selectFlight(index, element) {
            // Remove selected class from all cards
            document.querySelectorAll('.flight-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Add selected class to clicked card
            element.classList.add('selected');
            
            // Get flight data from PHP array
            const flightData = <?php echo json_encode($search_results); ?>[index];
            if (!flightData) {
                alert('Error: Flight data not found');
                return;
            }
            
            // Create form to submit flight selection
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'flight_data';
            input.value = JSON.stringify(flightData);
            
            const submit = document.createElement('input');
            submit.type = 'hidden';
            submit.name = 'select_flight';
            submit.value = '1';
            
            form.appendChild(input);
            form.appendChild(submit);
            document.body.appendChild(form);
            form.submit();
        }

        // Passenger management
        let passengerCount = 1;
        
        function addPassenger() {
            passengerCount++;
            const passengerForms = document.getElementById('passengerForms');
            const newPassenger = document.createElement('div');
            newPassenger.className = 'passenger-item';
            newPassenger.innerHTML = `
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="passenger_names[]" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email *</label>
                        <input type="email" name="passenger_emails[]" class="form-control" required>
                    </div>
                </div>
                <button type="button" class="btn btn-danger btn-sm remove-passenger" onclick="removePassenger(this)">
                    <i class="fas fa-times"></i> Remove
                </button>
            `;
            passengerForms.appendChild(newPassenger);
        }
        
        function removePassenger(button) {
            if (passengerCount > 1) {
                button.parentElement.remove();
                passengerCount--;
            } else {
                alert('You need at least one passenger');
            }
        }

        // Price calculation
        function updatePriceSummary() {
            const basePrice = <?php echo $selected_flight ? ($selected_flight['price']['grandTotal'] ?? 0) : 0; ?>;
            const adjustmentType = document.querySelector('select[name="adjustment_type"]').value;
            const adjustmentValue = parseFloat(document.querySelector('input[name="price_adjustment"]').value) || 0;
            
            let finalPrice = basePrice;
            
            if (adjustmentType === 'percentage') {
                finalPrice = basePrice + (basePrice * adjustmentValue / 100);
            } else if (adjustmentType === 'flat') {
                finalPrice = basePrice + adjustmentValue;
            }
            
            // Ensure final price is not negative
            if (finalPrice < 0) {
                finalPrice = 0;
            }
            
            document.getElementById('finalPrice').textContent = '₦' + finalPrice.toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        // Form submission handling
        document.addEventListener('DOMContentLoaded', function() {
            const invoiceForm = document.getElementById('invoiceForm');
            if (invoiceForm) {
                invoiceForm.addEventListener('submit', function(e) {
                    // Validate required fields
                    const requiredFields = this.querySelectorAll('[required]');
                    let valid = true;
                    
                    requiredFields.forEach(field => {
                        if (!field.value.trim()) {
                            valid = false;
                            field.style.borderColor = '#dc3545';
                        } else {
                            field.style.borderColor = '';
                        }
                    });
                    
                    if (!valid) {
                        e.preventDefault();
                        alert('Please fill in all required fields');
                        return;
                    }
                    
                    // Prepare passenger data
                    const passengerNames = document.getElementsByName('passenger_names[]');
                    const passengerEmails = document.getElementsByName('passenger_emails[]');
                    const passengerData = [];
                    
                    for (let i = 0; i < passengerNames.length; i++) {
                        passengerData.push({
                            name: passengerNames[i].value,
                            email: passengerEmails[i].value
                        });
                    }
                    
                    // Create hidden fields for JSON data
                    const passengerInput = document.createElement('input');
                    passengerInput.type = 'hidden';
                    passengerInput.name = 'passenger_data';
                    passengerInput.value = JSON.stringify(passengerData);
                    this.appendChild(passengerInput);
                    
                    const contactInput = document.createElement('input');
                    contactInput.type = 'hidden';
                    contactInput.name = 'contact_info';
                    contactInput.value = JSON.stringify({
                        name: document.querySelector('input[name="contact_name"]').value,
                        email: document.querySelector('input[name="contact_email"]').value,
                        phone: document.querySelector('input[name="contact_phone"]').value,
                        address: document.querySelector('textarea[name="contact_address"]').value
                    });
                    this.appendChild(contactInput);
                });
            }
            
            // Initialize return date toggle
            toggleReturnDate();
            
            // Initialize autocomplete for both search and manual forms
            initAutocomplete();
        });

        // Autocomplete functionality
        function initAutocomplete() {
            // Function to filter airports based on search term
            function filterAirports(searchTerm) {
                if (!searchTerm) return [];
                
                const lowerSearchTerm = searchTerm.toLowerCase();
                return airports.filter(airport => 
                    airport.code.toLowerCase().includes(lowerSearchTerm) ||
                    airport.name.toLowerCase().includes(lowerSearchTerm) ||
                    airport.city.toLowerCase().includes(lowerSearchTerm) ||
                    airport.country.toLowerCase().includes(lowerSearchTerm)
                ).slice(0, 5); // Limit to 5 results
            }
            
            // Function to display autocomplete results
            function showResults(input, resultsContainer, searchTerm) {
                const filteredAirports = filterAirports(searchTerm);
                
                if (filteredAirports.length === 0) {
                    resultsContainer.innerHTML = '<div class="autocomplete-item">No airports found</div>';
                    resultsContainer.style.display = 'block';
                    return;
                }
                
                resultsContainer.innerHTML = filteredAirports.map(airport => `
                    <div class="autocomplete-item" data-code="${airport.code}" data-city="${airport.city}">
                        <div class="city-name">${airport.city}, ${airport.country}</div>
                        <div class="airport-name">${airport.name} <span class="airport-code">(${airport.code})</span></div>
                    </div>
                `).join('');
                
                resultsContainer.style.display = 'block';
                
                // Add click event to autocomplete items
                resultsContainer.querySelectorAll('.autocomplete-item').forEach(item => {
                    item.addEventListener('click', function() {
                        input.value = this.getAttribute('data-code');
                        resultsContainer.style.display = 'none';
                    });
                });
            }
            
            // Initialize autocomplete for search form
            const originInput = document.getElementById('originInput');
            const destinationInput = document.getElementById('destinationInput');
            const originResults = document.getElementById('originResults');
            const destinationResults = document.getElementById('destinationResults');
            
            if (originInput) {
                originInput.addEventListener('input', function() {
                    const searchTerm = this.value;
                    if (searchTerm.length >= 2) {
                        showResults(originInput, originResults, searchTerm);
                    } else {
                        originResults.style.display = 'none';
                    }
                });
                
                originInput.addEventListener('focus', function() {
                    const searchTerm = this.value;
                    if (searchTerm.length >= 2) {
                        showResults(originInput, originResults, searchTerm);
                    }
                });
            }
            
            if (destinationInput) {
                destinationInput.addEventListener('input', function() {
                    const searchTerm = this.value;
                    if (searchTerm.length >= 2) {
                        showResults(destinationInput, destinationResults, searchTerm);
                    } else {
                        destinationResults.style.display = 'none';
                    }
                });
                
                destinationInput.addEventListener('focus', function() {
                    const searchTerm = this.value;
                    if (searchTerm.length >= 2) {
                        showResults(destinationInput, destinationResults, searchTerm);
                    }
                });
            }
            
            // Initialize autocomplete for manual form
            const manualOriginInput = document.getElementById('manualOriginInput');
            const manualDestinationInput = document.getElementById('manualDestinationInput');
            const manualOriginResults = document.getElementById('manualOriginResults');
            const manualDestinationResults = document.getElementById('manualDestinationResults');
            
            if (manualOriginInput) {
                manualOriginInput.addEventListener('input', function() {
                    const searchTerm = this.value;
                    if (searchTerm.length >= 1) {
                        showResults(manualOriginInput, manualOriginResults, searchTerm);
                    } else {
                        manualOriginResults.style.display = 'none';
                    }
                });
                
                manualOriginInput.addEventListener('focus', function() {
                    const searchTerm = this.value;
                    if (searchTerm.length >= 1) {
                        showResults(manualOriginInput, manualOriginResults, searchTerm);
                    }
                });
            }
            
            if (manualDestinationInput) {
                manualDestinationInput.addEventListener('input', function() {
                    const searchTerm = this.value;
                    if (searchTerm.length >= 1) {
                        showResults(manualDestinationInput, manualDestinationResults, searchTerm);
                    } else {
                        manualDestinationResults.style.display = 'none';
                    }
                });
                
                manualDestinationInput.addEventListener('focus', function() {
                    const searchTerm = this.value;
                    if (searchTerm.length >= 1) {
                        showResults(manualDestinationInput, manualDestinationResults, searchTerm);
                    }
                });
            }
            
            // Hide autocomplete results when clicking outside
            document.addEventListener('click', function(e) {
                if (originInput && !originInput.contains(e.target) && originResults && !originResults.contains(e.target)) {
                    originResults.style.display = 'none';
                }
                if (destinationInput && !destinationInput.contains(e.target) && destinationResults && !destinationResults.contains(e.target)) {
                    destinationResults.style.display = 'none';
                }
                if (manualOriginInput && !manualOriginInput.contains(e.target) && manualOriginResults && !manualOriginResults.contains(e.target)) {
                    manualOriginResults.style.display = 'none';
                }
                if (manualDestinationInput && !manualDestinationInput.contains(e.target) && manualDestinationResults && !manualDestinationResults.contains(e.target)) {
                    manualDestinationResults.style.display = 'none';
                }
            });
        }

        // Loading spinner for flight search
        const flightSearchForm = document.getElementById('flightSearchForm');
        if (flightSearchForm) {
            flightSearchForm.addEventListener('submit', function() {
                const searchButton = document.getElementById('searchButton');
                const loadingSpinner = document.getElementById('loadingSpinner');
                
                // Disable search button and show loading spinner
                searchButton.disabled = true;
                searchButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Searching...';
                loadingSpinner.style.display = 'block';
                
                // Scroll to loading spinner
                loadingSpinner.scrollIntoView({ behavior: 'smooth' });
            });
        }

        // Auto-uppercase airport codes when user stops typing
        document.addEventListener('DOMContentLoaded', function() {
            const airportInputs = document.querySelectorAll('#originInput, #destinationInput, #manualOriginInput, #manualDestinationInput');
            let typingTimer;
            const doneTypingInterval = 1000; // 1 second
            
            airportInputs.forEach(input => {
                input.addEventListener('input', function() {
                    clearTimeout(typingTimer);
                    if (this.value.length === 3 && /^[A-Za-z]{3}$/.test(this.value)) {
                        this.value = this.value.toUpperCase();
                    }
                });
                
                input.addEventListener('keyup', function() {
                    clearTimeout(typingTimer);
                    typingTimer = setTimeout(() => {
                        if (this.value.length === 3 && /^[A-Za-z]{3}$/.test(this.value)) {
                            this.value = this.value.toUpperCase();
                        }
                    }, doneTypingInterval);
                });
            });
        });
    </script>
</body>
</html>