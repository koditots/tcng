<?php
// includes/functions.php

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check if user is admin
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

// Get site setting - FIXED VERSION
function getSiteSetting($pdo, $key, $default = '') {
    try {
        // Get the most recent settings row
        $stmt = $pdo->query("SELECT * FROM site_settings ORDER BY id DESC LIMIT 1");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($settings && isset($settings[$key])) {
            return $settings[$key];
        }
        
        return $default;
    } catch (Exception $e) {
        error_log("getSiteSetting error for $key: " . $e->getMessage());
        return $default;
    }
}

// Get all site settings at once
function getSiteSettings($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM site_settings ORDER BY id DESC LIMIT 1");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$settings) {
            // Return default settings if none exist
            return [
                'site_name' => 'Travel Centre',
                'site_title' => 'Travel Centre - Book Flights, Hotels & Visa Services',
                'site_description' => 'Your one-stop travel solution for flights, hotels, and visa services',
                'site_keywords' => 'flights, hotels, visa, travel, booking',
                'logo' => '',
                'favicon' => '',
                'admin_email' => 'admin@travelcentre.ng',
                'support_email' => 'support@travelcentre.ng',
                'phone' => '+234 123 456 7890',
                'address' => 'Lagos, Nigeria',
                'currency' => 'NGN',
                'timezone' => 'Africa/Lagos',
                'currency_rate' => '450'
            ];
        }
        
        return $settings;
    } catch (Exception $e) {
        error_log("getSiteSettings error: " . $e->getMessage());
        return [];
    }
}

// Sanitize input data
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Redirect function
function redirect($url) {
    header("Location: $url");
    exit;
}

// Generate random string
function generateRandomString($length = 10) {
    return substr(str_shuffle(str_repeat($x = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length / strlen($x)))), 1, $length);
}

// Format currency
function formatCurrency($amount, $currency = 'NGN') {
    $symbols = [
        'NGN' => '₦',
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£'
    ];
    $symbol = $symbols[$currency] ?? $currency;
    return $symbol . number_format($amount, 2);
}

// Send email - wrapped to use config.php's global sendEmail if available
if (!function_exists('sendEmail')) {
    function sendEmail($to, $subject, $message, $headers = '') {
        if (empty($headers)) {
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: " . SMTP_FROM . "\r\n";
        }
        
        return mail($to, $subject, $message, $headers);
    }
}

// Send HTML email - GLOBAL PROPER VERSION to prevent stub functions from taking over
if (!function_exists('sendHTMLEmail')) {
    function sendHTMLEmail($to, $subject, $body, $from = null) {
        global $pdo;
        
        if (function_exists('sendEmail') && $to && $subject && $body) {
            $result = sendEmail($to, $subject, $body, true);
            error_log("sendHTMLEmail: Calling sendEmail for $to, result=" . ($result ? 'true' : 'false'));
            return $result;
        }
        
        $smtp_host = '';
        $smtp_port = 587;
        $smtp_username = '';
        $smtp_password = '';
        $smtp_encryption = 'tls';
        $smtp_from_email = '';
        $smtp_from_name = 'Travel Centre';
        
        try {
            $stmt = $pdo->prepare("SELECT smtp_host, smtp_port, smtp_username, smtp_password, smtp_encryption, smtp_from_email, smtp_from_name FROM site_settings ORDER BY id DESC LIMIT 1");
            $stmt->execute();
            $settings = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($settings) {
                $smtp_host = $settings['smtp_host'] ?? '';
                $smtp_port = $settings['smtp_port'] ?? 587;
                $smtp_username = $settings['smtp_username'] ?? '';
                $smtp_password = $settings['smtp_password'] ?? '';
                $smtp_encryption = $settings['smtp_encryption'] ?? 'tls';
                $smtp_from_email = $settings['smtp_from_email'] ?? $settings['admin_email'] ?? '';
                $smtp_from_name = $settings['smtp_from_name'] ?? 'Travel Centre';
            }
        } catch (Exception $e) {
            error_log("sendHTMLEmail: Could not get SMTP settings: " . $e->getMessage());
        }
        
        if ($from) {
            $smtp_from_email = $from;
        }
        
        if (!empty($smtp_host) && !empty($smtp_username) && !empty($smtp_password)) {
            if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                $phpmailer_path = __DIR__ . '/PHPMailer/src/';
                if (file_exists($phpmailer_path . 'PHPMailer.php')) {
                    require_once $phpmailer_path . 'PHPMailer.php';
                    require_once $phpmailer_path . 'SMTP.php';
                    require_once $phpmailer_path . 'Exception.php';
                }
            }
            
            if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                try {
                    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                    $mail->isSMTP();
                    $mail->Host = $smtp_host;
                    $mail->SMTPAuth = true;
                    $mail->Username = $smtp_username;
                    $mail->Password = $smtp_password;
                    $mail->SMTPSecure = $smtp_encryption;
                    $mail->Port = intval($smtp_port);
                    $mail->CharSet = 'UTF-8';
                    
                    $from_email = $smtp_from_email ?: $smtp_username;
                    $mail->setFrom($from_email, $smtp_from_name);
                    $mail->addAddress($to);
                    
                    $mail->isHTML(true);
                    $mail->Subject = $subject;
                    $mail->Body = $body;
                    
                    $mail->send();
                    error_log("sendHTMLEmail: PHPMailer successful to $to");
                    return true;
                } catch (Exception $e) {
                    error_log("sendHTMLEmail: PHPMailer failed: " . $e->getMessage());
                }
            }
        }
        
        $from_email = $smtp_from_email ?: (defined('SMTP_FROM') ? SMTP_FROM : 'no-reply@travelcentre.ng');
        $headers = "From: $smtp_from_name <$from_email>\r\n";
        $headers .= "Reply-To: $from_email\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        
        $result = mail($to, $subject, $body, $headers);
        error_log("sendHTMLEmail: Fallback mail() to $to, result=" . ($result ? 'true' : 'false'));
        return $result;
    }
}

// Add notification
function addNotification($pdo, $user_id, $title, $message, $type = 'info', $related_type = null, $related_id = null) {
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, related_type, related_id) VALUES (?, ?, ?, ?, ?, ?)");
        return $stmt->execute([$user_id, $title, $message, $type, $related_type, $related_id]);
    } catch (Exception $e) {
        return false;
    }
}

// Get user notifications
function getUserNotifications($pdo, $user_id, $limit = 10) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
        $stmt->execute([$user_id, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

// Get user by ID
function getUserById($pdo, $id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return null;
    }
}

// NEW FUNCTIONS FOR FLIGHT SEARCH ENHANCEMENTS

/**
 * Get popular destinations from database or return default list
 */
function getPopularDestinations($pdo, $limit = 6) {
    try {
        // First try to get from database if we have a destinations table
        $stmt = $pdo->prepare("SELECT * FROM popular_destinations WHERE is_active = 1 ORDER BY display_order LIMIT ?");
        $stmt->execute([$limit]);
        $destinations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($destinations)) {
            return $destinations;
        }
    } catch (Exception $e) {
        // Table might not exist, fall through to default
    }
    
    // Fallback to default destinations
    return [
        ['city' => 'Lagos', 'country' => 'Nigeria', 'price' => '₦45,000', 'code' => 'LOS'],
        ['city' => 'Abuja', 'country' => 'Nigeria', 'price' => '₦55,000', 'code' => 'ABV'],
        ['city' => 'London', 'country' => 'UK', 'price' => '₦350,000', 'code' => 'LHR'],
        ['city' => 'Dubai', 'country' => 'UAE', 'price' => '₦280,000', 'code' => 'DXB'],
        ['city' => 'New York', 'country' => 'USA', 'price' => '₦420,000', 'code' => 'JFK'],
        ['city' => 'Paris', 'country' => 'France', 'price' => '₦380,000', 'code' => 'CDG']
    ];
}

/**
 * Get airline name by code
 */
function getAirlineName($code) {
    $airlines = [
        'LH' => 'Lufthansa',
        'BA' => 'British Airways',
        'AF' => 'Air France',
        'KL' => 'KLM',
        'TK' => 'Turkish Airlines',
        'ET' => 'Ethiopian Airlines',
        'QR' => 'Qatar Airways',
        'EK' => 'Emirates',
        'EY' => 'Etihad Airways',
        'SQ' => 'Singapore Airlines',
        'AA' => 'American Airlines',
        'DL' => 'Delta Air Lines',
        'UA' => 'United Airlines',
        'VS' => 'Virgin Atlantic',
        'LY' => 'El Al',
        'AC' => 'Air Canada',
        'CX' => 'Cathay Pacific',
        'NH' => 'All Nippon Airways',
        'JL' => 'Japan Airlines',
        'KE' => 'Korean Air',
        'OZ' => 'Asiana Airlines',
        'TG' => 'Thai Airways',
        'MH' => 'Malaysia Airlines',
        'GA' => 'Garuda Indonesia',
        'PR' => 'Philippine Airlines',
        'SV' => 'Saudia',
        'MS' => 'Egyptair',
        'AT' => 'Royal Air Maroc',
        'KQ' => 'Kenya Airways',
        'SA' => 'South African Airways',
        'WB' => 'RwandAir',
        'UL' => 'SriLankan Airlines',
        'AI' => 'Air India',
        '6E' => 'IndiGo',
        '9W' => 'Jet Airways',
        'SG' => 'SpiceJet',
        'FR' => 'Ryanair',
        'U2' => 'EasyJet',
        'DY' => 'Norwegian Air Shuttle'
    ];
    
    return $airlines[$code] ?? $code;
}

/**
 * Format flight duration from ISO 8601 to human readable
 */
function formatFlightDuration($duration) {
    // Remove PT from the beginning
    $duration = str_replace('PT', '', $duration);
    
    $hours = 0;
    $minutes = 0;
    
    // Check if hours exist
    if (strpos($duration, 'H') !== false) {
        $parts = explode('H', $duration);
        $hours = intval($parts[0]);
        $duration = $parts[1] ?? '';
    }
    
    // Check if minutes exist
    if (strpos($duration, 'M') !== false) {
        $minutes = intval(str_replace('M', '', $duration));
    }
    
    if ($hours > 0 && $minutes > 0) {
        return "{$hours}h {$minutes}m";
    } elseif ($hours > 0) {
        return "{$hours}h";
    } else {
        return "{$minutes}m";
    }
}

/**
 * Calculate layover time between segments
 */
function calculateLayoverTime($arrivalTime, $nextDepartureTime) {
    $arrival = strtotime($arrivalTime);
    $nextDeparture = strtotime($nextDepartureTime);
    $layover = $nextDeparture - $arrival;
    
    $hours = floor($layover / 3600);
    $minutes = floor(($layover % 3600) / 60);
    
    if ($hours > 0 && $minutes > 0) {
        return "{$hours}h {$minutes}m";
    } elseif ($hours > 0) {
        return "{$hours}h";
    } else {
        return "{$minutes}m";
    }
}

/**
 * Validate airport code
 */
function isValidAirportCode($code) {
    return preg_match('/^[A-Z]{3}$/', $code);
}

/**
 * Get airport information by code - UPDATED TO USE AMADEUS API
 */
function getAirportInfo($code, $access_token = '') {
    // First, try to get from Amadeus API if we have an access token
    if (!empty($access_token)) {
        try {
            $url = "https://test.api.amadeus.com/v1/reference-data/locations?subType=AIRPORT&keyword=" . urlencode($code) . "&page[limit]=1";
            $response = httpGet($url, $access_token);
            $data = json_decode($response, true);
            
            if (isset($data['data'][0])) {
                $airport = $data['data'][0];
                return [
                    'name' => $airport['name'] ?? 'Unknown Airport',
                    'city' => $airport['address']['cityName'] ?? 'Unknown',
                    'country' => $airport['address']['countryName'] ?? 'Unknown'
                ];
            }
        } catch (Exception $e) {
            // Fall back to static data if API fails
            error_log("Amadeus API error in getAirportInfo: " . $e->getMessage());
        }
    }
    
    // Fallback to static data
    $airports = [
        'LOS' => ['name' => 'Murtala Muhammed International Airport', 'city' => 'Lagos', 'country' => 'Nigeria'],
        'ABV' => ['name' => 'Nnamdi Azikiwe International Airport', 'city' => 'Abuja', 'country' => 'Nigeria'],
        'LHR' => ['name' => 'Heathrow Airport', 'city' => 'London', 'country' => 'United Kingdom'],
        'DXB' => ['name' => 'Dubai International Airport', 'city' => 'Dubai', 'country' => 'UAE'],
        'JFK' => ['name' => 'John F. Kennedy International Airport', 'city' => 'New York', 'country' => 'USA'],
        'CDG' => ['name' => 'Charles de Gaulle Airport', 'city' => 'Paris', 'country' => 'France'],
        'ACC' => ['name' => 'Kotoka International Airport', 'city' => 'Accra', 'country' => 'Ghana'],
        'NBO' => ['name' => 'Jomo Kenyatta International Airport', 'city' => 'Nairobi', 'country' => 'Kenya'],
        'ADD' => ['name' => 'Bole International Airport', 'city' => 'Addis Ababa', 'country' => 'Ethiopia'],
        'CPT' => ['name' => 'Cape Town International Airport', 'city' => 'Cape Town', 'country' => 'South Africa'],
        'JNB' => ['name' => 'O.R. Tambo International Airport', 'city' => 'Johannesburg', 'country' => 'South Africa'],
        'FRA' => ['name' => 'Frankfurt Airport', 'city' => 'Frankfurt', 'country' => 'Germany'],
        'AMS' => ['name' => 'Amsterdam Airport Schiphol', 'city' => 'Amsterdam', 'country' => 'Netherlands'],
        'IST' => ['name' => 'Istanbul Airport', 'city' => 'Istanbul', 'country' => 'Turkey'],
        'AUH' => ['name' => 'Abu Dhabi International Airport', 'city' => 'Abu Dhabi', 'country' => 'UAE'],
        'DOH' => ['name' => 'Hamad International Airport', 'city' => 'Doha', 'country' => 'Qatar'],
        'SIN' => ['name' => 'Changi Airport', 'city' => 'Singapore', 'country' => 'Singapore'],
        'BKK' => ['name' => 'Suvarnabhumi Airport', 'city' => 'Bangkok', 'country' => 'Thailand'],
        'HKG' => ['name' => 'Hong Kong International Airport', 'city' => 'Hong Kong', 'country' => 'China'],
        'PEK' => ['name' => 'Beijing Capital International Airport', 'city' => 'Beijing', 'country' => 'China'],
        'CAI' => ['name' => 'Cairo International Airport', 'city' => 'Cairo', 'country' => 'Egypt'],
        'FRA' => ['name' => 'Frankfurt Airport', 'city' => 'Frankfurt', 'country' => 'Germany'],
        'AMS' => ['name' => 'Amsterdam Airport Schiphol', 'city' => 'Amsterdam', 'country' => 'Netherlands'],
        'IST' => ['name' => 'Istanbul Airport', 'city' => 'Istanbul', 'country' => 'Turkey'],
        'ADD' => ['name' => 'Addis Ababa Bole International Airport', 'city' => 'Addis Ababa', 'country' => 'Ethiopia']
    ];
    
    return $airports[$code] ?? ['name' => 'Unknown Airport', 'city' => 'Unknown', 'country' => 'Unknown'];
}

/**
 * Search airports by query (for auto-suggest) - UPDATED TO USE AMADEUS API
 */
function searchAirports($query, $access_token = '') {
    // If no access token provided or query is too short, return empty
    if (empty($access_token)) {
        return [];
    }
    
    $query = trim($query);
    if (strlen($query) < 2) {
        return [];
    }
    
    try {
        // Use Amadeus API to search for airports
        $url = "https://test.api.amadeus.com/v1/reference-data/locations?subType=AIRPORT,CITY&keyword=" . urlencode($query) . "&page[limit]=20";
        $response = httpGet($url, $access_token);
        $data = json_decode($response, true);
        
        $airports = [];
        if (isset($data['data']) && is_array($data['data'])) {
            foreach ($data['data'] as $item) {
                // Only include airports with IATA codes
                if (isset($item['iataCode']) && !empty($item['iataCode'])) {
                    $airports[] = [
                        'code' => $item['iataCode'],
                        'name' => $item['name'] ?? 'Unknown Airport',
                        'city' => $item['address']['cityName'] ?? 'Unknown',
                        'country' => $item['address']['countryName'] ?? 'Unknown'
                    ];
                }
            }
        }
        
        return $airports;
    } catch (Exception $e) {
        error_log("Amadeus API error in searchAirports: " . $e->getMessage());
        
        // Fallback to static search if API fails
        return searchAirportsStatic($query);
    }
}

/**
 * Fallback static airport search when Amadeus API fails
 */
function searchAirportsStatic($query) {
    $allAirports = [
        ['code' => 'LOS', 'name' => 'Murtala Muhammed International Airport', 'city' => 'Lagos', 'country' => 'Nigeria'],
        ['code' => 'ABV', 'name' => 'Nnamdi Azikiwe International Airport', 'city' => 'Abuja', 'country' => 'Nigeria'],
        ['code' => 'LHR', 'name' => 'Heathrow Airport', 'city' => 'London', 'country' => 'United Kingdom'],
        ['code' => 'DXB', 'name' => 'Dubai International Airport', 'city' => 'Dubai', 'country' => 'UAE'],
        ['code' => 'JFK', 'name' => 'John F. Kennedy International Airport', 'city' => 'New York', 'country' => 'USA'],
        ['code' => 'CDG', 'name' => 'Charles de Gaulle Airport', 'city' => 'Paris', 'country' => 'France'],
        ['code' => 'ACC', 'name' => 'Kotoka International Airport', 'city' => 'Accra', 'country' => 'Ghana'],
        ['code' => 'NBO', 'name' => 'Jomo Kenyatta International Airport', 'city' => 'Nairobi', 'country' => 'Kenya'],
        ['code' => 'ADD', 'name' => 'Bole International Airport', 'city' => 'Addis Ababa', 'country' => 'Ethiopia'],
        ['code' => 'CPT', 'name' => 'Cape Town International Airport', 'city' => 'Cape Town', 'country' => 'South Africa'],
        ['code' => 'JNB', 'name' => 'O.R. Tambo International Airport', 'city' => 'Johannesburg', 'country' => 'South Africa'],
        ['code' => 'FRA', 'name' => 'Frankfurt Airport', 'city' => 'Frankfurt', 'country' => 'Germany'],
        ['code' => 'AMS', 'name' => 'Amsterdam Airport Schiphol', 'city' => 'Amsterdam', 'country' => 'Netherlands'],
        ['code' => 'IST', 'name' => 'Istanbul Airport', 'city' => 'Istanbul', 'country' => 'Turkey'],
        ['code' => 'AUH', 'name' => 'Abu Dhabi International Airport', 'city' => 'Abu Dhabi', 'country' => 'UAE'],
        ['code' => 'DOH', 'name' => 'Hamad International Airport', 'city' => 'Doha', 'country' => 'Qatar'],
        ['code' => 'SIN', 'name' => 'Changi Airport', 'city' => 'Singapore', 'country' => 'Singapore'],
        ['code' => 'BKK', 'name' => 'Suvarnabhumi Airport', 'city' => 'Bangkok', 'country' => 'Thailand'],
        ['code' => 'HKG', 'name' => 'Hong Kong International Airport', 'city' => 'Hong Kong', 'country' => 'China'],
        ['code' => 'PEK', 'name' => 'Beijing Capital International Airport', 'city' => 'Beijing', 'country' => 'China'],
        ['code' => 'CAI', 'name' => 'Cairo International Airport', 'city' => 'Cairo', 'country' => 'Egypt'],
        ['code' => 'FRA', 'name' => 'Frankfurt Airport', 'city' => 'Frankfurt', 'country' => 'Germany'],
        ['code' => 'AMS', 'name' => 'Amsterdam Airport Schiphol', 'city' => 'Amsterdam', 'country' => 'Netherlands'],
        ['code' => 'IST', 'name' => 'Istanbul Airport', 'city' => 'Istanbul', 'country' => 'Turkey'],
        ['code' => 'ADD', 'name' => 'Addis Ababa Bole International Airport', 'city' => 'Addis Ababa', 'country' => 'Ethiopia']
    ];
    
    $query = strtoupper(trim($query));
    if (empty($query)) {
        return [];
    }
    
    return array_filter($allAirports, function($airport) use ($query) {
        return strpos($airport['code'], $query) !== false ||
               stripos($airport['city'], $query) !== false ||
               stripos($airport['name'], $query) !== false ||
               stripos($airport['country'], $query) !== false;
    });
}

/**
 * Convert flight price from USD to NGN using database rate
 */
function convertToNaira($usdAmount, $pdo = null) {
    // Try to get conversion rate from database
    $conversionRate = 450; // Default fallback
    
    if ($pdo) {
        try {
            $rate = getSiteSetting($pdo, 'currency_rate', 450);
            if ($rate && is_numeric($rate)) {
                $conversionRate = floatval($rate);
            }
        } catch (Exception $e) {
            // Use default rate if there's an error
        }
    }
    
    return $usdAmount * $conversionRate;
}

/**
 * Format flight price with currency
 */
function formatFlightPrice($amount, $currency = 'NGN', $pdo = null) {
    $convertedAmount = convertToNaira($amount, $pdo);
    return formatCurrency($convertedAmount, $currency);
}

/**
 * Get travel class display name
 */
function getTravelClassName($classCode) {
    $classes = [
        'ECONOMY' => 'Economy',
        'PREMIUM_ECONOMY' => 'Premium Economy',
        'BUSINESS' => 'Business',
        'FIRST' => 'First Class'
    ];
    
    return $classes[$classCode] ?? ucfirst(strtolower($classCode));
}

/**
 * Validate flight search parameters
 */
function validateFlightSearch($origin, $destination, $departureDate, $returnDate = '', $tripType = 'round_trip') {
    $errors = [];
    
    // Validate airport codes
    if (!isValidAirportCode($origin)) {
        $errors[] = 'Invalid origin airport code';
    }
    
    if (!isValidAirportCode($destination)) {
        $errors[] = 'Invalid destination airport code';
    }
    
    // Check if origin and destination are different
    if ($origin === $destination) {
        $errors[] = 'Origin and destination cannot be the same';
    }
    
    // Validate dates
    $today = date('Y-m-d');
    if ($departureDate < $today) {
        $errors[] = 'Departure date cannot be in the past';
    }
    
    if ($tripType === 'round_trip' && $returnDate && $returnDate <= $departureDate) {
        $errors[] = 'Return date must be after departure date';
    }
    
    return $errors;
}

/**
 * Log flight search for analytics
 */
function logFlightSearch($pdo, $userId, $origin, $destination, $departureDate, $returnDate = '', $passengers = 1) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO flight_searches 
            (user_id, origin, destination, departure_date, return_date, passengers, search_date) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        return $stmt->execute([
            $userId ?: null,
            $origin,
            $destination,
            $departureDate,
            $returnDate ?: null,
            $passengers
        ]);
    } catch (Exception $e) {
        // Silently fail - logging shouldn't break the search
        return false;
    }
}

/**
 * Get recent searches for a user
 */
function getRecentSearches($pdo, $userId, $limit = 5) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM flight_searches 
            WHERE user_id = ? 
            ORDER BY search_date DESC 
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Get airline logo URL from CDN
 */
function getAirlineLogo($airlineCode) {
    return "https://images.kiwi.com/airlines/64/{$airlineCode}.png";
}

/**
 * Generate receipt content for flight booking
 */
function generateReceiptContent($flightData, $passengerNames, $passengerCount, $travelClass, $finalPrice, $origin, $destination, $departureDate, $airlineCode, $airlineName) {
    $itinerary = $flightData['itineraries'][0];
    $firstSegment = $itinerary['segments'][0];
    $lastSegment = $itinerary['segments'][count($itinerary['segments']) - 1];
    
    // Format dates
    $departureDateTime = new DateTime($firstSegment['departure']['at']);
    $formattedDepartureDate = $departureDateTime->format('M j, Y');
    $departureTime = $departureDateTime->format('g:i A');
    
    $invoiceDate = new DateTime();
    $formattedInvoiceDate = $invoiceDate->format('F j, Y');
    
    // Generate random invoice numbers
    $invoiceNo = 'TC-INV-' . date('Y') . '-' . rand(1000, 9999);
    $bookingRef = 'TC-' . $origin . '-' . $destination . '-' . rand(1000, 9999);
    $paymentRef = 'PSK-' . rand(1000, 9999);
    
    // Calculate fare breakdown
    $baseFare = $finalPrice * 0.8;
    $taxes = $finalPrice * 0.15;
    $serviceFee = $finalPrice * 0.05;
    
    $receipt = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Flight Receipt - Travel Centre</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                margin: 0;
                padding: 20px;
                background: #f5f5f5;
            }
            .receipt-container {
                max-width: 800px;
                margin: 0 auto;
                background: white;
                padding: 30px;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            .receipt-header {
                text-align: center;
                border-bottom: 3px solid #007bff;
                padding-bottom: 20px;
                margin-bottom: 30px;
            }
            .company-logo {
                color: #007bff;
                font-size: 28px;
                font-weight: bold;
                margin-bottom: 10px;
            }
            .company-info h2 {
                color: #666;
                font-size: 18px;
                margin: 5px 0;
            }
            .company-info p {
                color: #666;
                margin: 2px 0;
                font-size: 14px;
            }
            .invoice-section {
                background: #f8f9fa;
                padding: 15px;
                border-radius: 6px;
                margin-bottom: 20px;
            }
            .invoice-section p {
                margin: 5px 0;
                font-size: 14px;
            }
            .receipt-table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
                font-size: 14px;
            }
            .receipt-table th {
                background: #007bff;
                color: white;
                padding: 12px 8px;
                text-align: left;
                border: 1px solid #ddd;
            }
            .receipt-table td {
                padding: 10px 8px;
                border: 1px solid #ddd;
            }
            .receipt-table tbody tr:nth-child(even) {
                background: #f8f9fa;
            }
            .flight-details-section {
                margin: 25px 0;
            }
            .flight-details-section h3 {
                color: #333;
                border-bottom: 2px solid #007bff;
                padding-bottom: 8px;
                margin-bottom: 15px;
            }
            .flight-details-table {
                width: 100%;
                border-collapse: collapse;
                font-size: 14px;
            }
            .flight-details-table th {
                background: #28a745;
                color: white;
                padding: 10px 8px;
                text-align: left;
                border: 1px solid #ddd;
            }
            .flight-details-table td {
                padding: 10px 8px;
                border: 1px solid #ddd;
            }
            .fare-breakdown-section {
                margin: 25px 0;
            }
            .fare-table {
                width: 100%;
                border-collapse: collapse;
            }
            .fare-table td {
                padding: 15px;
                vertical-align: top;
                border: 1px solid #ddd;
            }
            .fare-table h4 {
                color: #333;
                margin-bottom: 10px;
                border-bottom: 1px solid #eee;
                padding-bottom: 5px;
            }
            .fare-table p {
                margin: 8px 0;
                font-size: 14px;
            }
            .receipt-footer {
                text-align: center;
                margin-top: 30px;
                padding-top: 20px;
                border-top: 3px solid #007bff;
            }
            .receipt-footer h3 {
                color: #007bff;
                margin-bottom: 10px;
            }
            .receipt-footer p {
                color: #666;
                margin: 3px 0;
            }
            .total-price {
                font-size: 18px;
                font-weight: bold;
                color: #dc3545;
            }
            @media print {
                body {
                    background: white;
                    margin: 0;
                    padding: 0;
                }
                .receipt-container {
                    box-shadow: none;
                    padding: 0;
                }
                .no-print {
                    display: none;
                }
            }
        </style>
    </head>
    <body>
        <div class="receipt-container">
            <div class="receipt-header">
                <div class="company-logo">TRAVEL CENTRE</div>
                <div class="company-info">
                    <h2>Official Flight Booking Partner</h2>
                    <p>flight.travelcentre.ng | email support@travelcentre.ng</p>
                </div>
            </div>
            
            <div class="invoice-section">
                <p><strong>INVOICE No:</strong> ' . $invoiceNo . '</p>
                <p><strong>INVOICE NO:</strong> ' . rand(1000, 9999) . '</p>
                <p><strong>DATE:</strong> ' . $formattedInvoiceDate . '</p>
            </div>
            
            <table class="receipt-table">
                <thead>
                    <tr>
                        <th>BOOKING REFERENCE</th>
                        <th>PASSENGER NAME</th>
                        <th>ROUTE</th>
                        <th>DEPARTURE DATE</th>
                    </tr>
                </thead>
                <tbody>';
    
    foreach ($passengerNames as $passengerName) {
        $receipt .= '
                    <tr>
                        <td>' . $bookingRef . '</td>
                        <td>' . htmlspecialchars($passengerName) . '</td>
                        <td>' . $origin . ' → ' . $destination . '</td>
                        <td>' . $formattedDepartureDate . '</td>
                    </tr>';
    }
    
    $receipt .= '
                </tbody>
            </table>
            
            <div class="flight-details-section">
                <h3>FLIGHT DETAILS</h3>
                <table class="flight-details-table">
                    <thead>
                        <tr>
                            <th>AIRLINE</th>
                            <th>FLIGHT NO.</th>
                            <th>CLASS</th>
                            <th>DURATION</th>
                            <th>STOPS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>' . $airlineName . '</td>
                            <td>' . $airlineCode . $firstSegment['number'] . '</td>
                            <td>' . getTravelClassName($travelClass) . '</td>
                            <td>' . formatFlightDuration($itinerary['duration']) . '</td>
                            <td>' . (count($itinerary['segments']) - 1) . ' stop' . (count($itinerary['segments']) > 2 ? 's' : '') . '</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="fare-breakdown-section">
                <table class="fare-table">
                    <tr>
                        <td width="50%">
                            <h4>FARE BREAKDOWN</h4>
                            <p>Base Fare: ₦' . number_format($baseFare, 2) . '</p>
                            <p>Taxes & Airline Charges: ₦' . number_format($taxes, 2) . '</p>
                            <p>Service Fee: ₦' . number_format($serviceFee, 2) . '</p>
                            <p class="total-price">Total: ₦' . number_format($finalPrice, 2) . '</p>
                        </td>
                        <td width="50%">
                            <h4>PAYMENT SUMMARY</h4>
                            <p><strong>REFERENCE:</strong> ' . $paymentRef . '</p>
                            <p><strong>STATUS:</strong> Pending Confirmation</p>
                            <p>All payments are processed through secure channels. Receipts are auto-issued after confirmation.</p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="receipt-footer">
                <h3>THANK YOU!</h3>
                <p>TravelCentre.ng – Your trusted flight partner across Africa.</p>
                <p>For assistance, +234 xxx xxx xxxx | support@travelcentre.ng</p>
            </div>
        </div>
    </body>
    </html>';
    
    return $receipt;
}

/**
 * Generate simplified receipt for sharing
 */
function generateSimpleReceipt($flightData, $passengerNames, $passengerCount, $travelClass, $finalPrice, $origin, $destination, $departureDate, $airlineCode, $airlineName) {
    $itinerary = $flightData['itineraries'][0];
    $firstSegment = $itinerary['segments'][0];
    
    $departureDateTime = new DateTime($firstSegment['departure']['at']);
    $formattedDepartureDate = $departureDateTime->format('M j, Y');
    
    $receipt = "✈️ *FLIGHT RECEIPT - TRAVEL CENTRE*\n\n";
    $receipt .= "📍 *Route:* {$origin} → {$destination}\n";
    $receipt .= "📅 *Departure:* {$formattedDepartureDate}\n";
    $receipt .= "🛫 *Airline:* {$airlineName}\n";
    $receipt .= "🎫 *Flight:* {$airlineCode}{$firstSegment['number']}\n";
    $receipt .= "💺 *Class:* " . getTravelClassName($travelClass) . "\n";
    $receipt .= "👥 *Passengers:* " . implode(', ', array_slice($passengerNames, 0, 3));
    if (count($passengerNames) > 3) {
        $receipt .= " and " . (count($passengerNames) - 3) . " more";
    }
    $receipt .= "\n";
    $receipt .= "💰 *Total:* ₦" . number_format($finalPrice, 2) . "\n\n";
    $receipt .= "📋 *Booking Ref:* TC-{$origin}-{$destination}-" . rand(1000, 9999) . "\n";
    $receipt .= "⏰ *Generated:* " . date('M j, Y g:i A') . "\n\n";
    $receipt .= "Thank you for choosing Travel Centre!";
    
    return $receipt;
}

/**
 * Save receipt to database for record keeping
 */
function saveReceiptToDatabase($pdo, $receiptData) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO flight_receipts 
            (user_id, booking_reference, passenger_names, flight_data, total_amount, receipt_html, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        return $stmt->execute([
            $receiptData['user_id'] ?? null,
            $receiptData['booking_reference'],
            json_encode($receiptData['passenger_names']),
            json_encode($receiptData['flight_data']),
            $receiptData['total_amount'],
            $receiptData['receipt_html']
        ]);
    } catch (Exception $e) {
        // Silently fail if table doesn't exist or there's an error
        error_log("Failed to save receipt: " . $e->getMessage());
        return false;
    }
}

/**
 * Get receipt by booking reference
 */
function getReceiptByReference($pdo, $bookingReference) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM flight_receipts WHERE booking_reference = ?");
        $stmt->execute([$bookingReference]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Generate WhatsApp message for receipt sharing
 */
function generateWhatsAppReceiptMessage($flightData, $passengerNames, $passengerCount, $travelClass, $finalPrice, $origin, $destination, $departureDate, $airlineCode, $airlineName) {
    $itinerary = $flightData['itineraries'][0];
    $firstSegment = $itinerary['segments'][0];
    
    $departureDateTime = new DateTime($firstSegment['departure']['at']);
    $formattedDepartureDate = $departureDateTime->format('M j, Y');
    $departureTime = $departureDateTime->format('g:i A');
    
    $message = "📄 *FLIGHT BOOKING RECEIPT* 📄\n\n";
    $message .= "✈️ *Travel Centre Booking Confirmation*\n\n";
    $message .= "📍 *Route:* {$origin} → {$destination}\n";
    $message .= "🛫 *Airline:* {$airlineName}\n";
    $message .= "🎫 *Flight:* {$airlineCode}{$firstSegment['number']}\n";
    $message .= "📅 *Departure:* {$formattedDepartureDate} at {$departureTime}\n";
    $message .= "⏱️ *Duration:* " . formatFlightDuration($itinerary['duration']) . "\n";
    $message .= "💺 *Class:* " . getTravelClassName($travelClass) . "\n";
    $message .= "👥 *Passengers:* {$passengerCount}\n";
    
    // Show first 2 passengers only in WhatsApp message
    if (!empty($passengerNames)) {
        $message .= "👤 *Names:* " . implode(', ', array_slice($passengerNames, 0, 2));
        if (count($passengerNames) > 2) {
            $message .= " and " . (count($passengerNames) - 2) . " more";
        }
        $message .= "\n";
    }
    
    $message .= "💰 *Total Amount:* ₦" . number_format($finalPrice, 2) . "\n\n";
    $message .= "📋 *Booking Reference:* TC-{$origin}-{$destination}-" . rand(1000, 9999) . "\n\n";
    $message .= "Thank you for booking with Travel Centre! 🎉\n";
    $message .= "For assistance: +234 xxx xxx xxxx";
    
    return $message;
}

/**
 * Validate passenger names
 */
function validatePassengerNames($passengerNames, $passengerCount) {
    $errors = [];
    
    if (count($passengerNames) !== $passengerCount) {
        $errors[] = "Number of passenger names must match passenger count";
    }
    
    foreach ($passengerNames as $index => $name) {
        $name = trim($name);
        if (empty($name)) {
            $errors[] = "Passenger " . ($index + 1) . " name is required";
        } elseif (strlen($name) < 2) {
            $errors[] = "Passenger " . ($index + 1) . " name is too short";
        } elseif (strlen($name) > 100) {
            $errors[] = "Passenger " . ($index + 1) . " name is too long";
        } elseif (!preg_match('/^[a-zA-Z\s\-\'\.]+$/', $name)) {
            $errors[] = "Passenger " . ($index + 1) . " name contains invalid characters";
        }
    }
    
    return $errors;
}

/**
 * Create receipt PDF (placeholder function - would integrate with a PDF library)
 */
function createReceiptPDF($receiptHtml) {
    // This is a placeholder function
    // In a real implementation, you would use a library like TCPDF, Dompdf, or mPDF
    // to convert the HTML receipt to PDF
    
    return [
        'success' => false,
        'message' => 'PDF generation not implemented. Please use print functionality instead.',
        'file_path' => null
    ];
}

/**
 * Share receipt via email
 */
function shareReceiptViaEmail($to, $subject, $receiptHtml, $attachmentPath = null) {
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8\r\n";
    $headers .= "From: Travel Centre <noreply@travelcentre.ng>\r\n";
    
    $message = "
    <html>
    <head>
        <title>{$subject}</title>
    </head>
    <body>
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background: #007bff; color: white; padding: 20px; text-align: center;'>
                <h1>Travel Centre</h1>
                <p>Your Flight Booking Receipt</p>
            </div>
            <div style='padding: 20px; background: #f9f9f9;'>
                <p>Dear Customer,</p>
                <p>Please find your flight booking receipt attached below.</p>
                <p>If you have any questions, please contact our support team.</p>
            </div>
            <div style='background: white; padding: 20px; border: 1px solid #ddd; margin: 20px 0;'>
                {$receiptHtml}
            </div>
            <div style='background: #f8f9fa; padding: 15px; text-align: center; color: #666;'>
                <p>Travel Centre - Your trusted flight partner across Africa</p>
                <p>Email: support@travelcentre.ng | Phone: +234 xxx xxx xxxx</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($to, $subject, $message, $headers);
}

/**
 * Apply filters to flight results
 */
function applyFilters($flights, $selectedAirlines, $maxStops, $minPrice, $maxPrice, $departureTime, $conversionRate) {
    $filteredFlights = [];
    
    foreach ($flights as $flight) {
        // Filter by airlines
        if (!empty($selectedAirlines)) {
            $itinerary = $flight['itineraries'][0];
            $firstSegment = $itinerary['segments'][0];
            $airlineCode = $firstSegment['carrierCode'];
            
            if (!in_array($airlineCode, $selectedAirlines)) {
                continue;
            }
        }
        
        // Filter by stops
        if ($maxStops >= 0) {
            $itinerary = $flight['itineraries'][0];
            $stops = count($itinerary['segments']) - 1;
            
            if ($stops > $maxStops) {
                continue;
            }
        }
        
        // Filter by price
        $basePrice = floatval($flight['price']['grandTotal']);
        $convertedPrice = $basePrice * $conversionRate;
        
        if ($convertedPrice < $minPrice || $convertedPrice > $maxPrice) {
            continue;
        }
        
        // Filter by departure time
        if (!empty($departureTime)) {
            $itinerary = $flight['itineraries'][0];
            $firstSegment = $itinerary['segments'][0];
            $departureHour = intval(date('H', strtotime($firstSegment['departure']['at'])));
            
            switch ($departureTime) {
                case 'morning':
                    if ($departureHour < 6 || $departureHour >= 12) continue 2;
                    break;
                case 'afternoon':
                    if ($departureHour < 12 || $departureHour >= 18) continue 2;
                    break;
                case 'evening':
                    if ($departureHour < 18) continue 2;
                    break;
            }
        }
        
        $filteredFlights[] = $flight;
    }
    
    return $filteredFlights;
}

/**
 * Get filter and ad panel settings
 */
function getFlightSearchSettings($pdo) {
    $settings = [
        'filter_enabled' => true,
        'ad_panel_enabled' => true,
        'ad_panel_content' => '<div class="ad-panel-default"><h3>Special Offers</h3><p>Book now and get up to 20% off on selected routes!</p></div>'
    ];
    
    try {
        // Check if filter panel is enabled
        $filter_enabled = getSiteSetting($pdo, 'filter_panel_enabled', '1');
        $settings['filter_enabled'] = ($filter_enabled == '1') ? true : false;
        
        // Check if ad panel is enabled
        $ad_panel_enabled = getSiteSetting($pdo, 'ad_panel_enabled', '1');
        $settings['ad_panel_enabled'] = ($ad_panel_enabled == '1') ? true : false;
        
        // Get ad panel content
        $ad_content = getSiteSetting($pdo, 'ad_panel_content');
        if ($ad_content) {
            $settings['ad_panel_content'] = $ad_content;
        }
        
    } catch (Exception $e) {
        // Use default settings if there's an error
    }
    
    return $settings;
}

/**
 * Get airport suggestions for search form - UPDATED TO USE AMADEUS API
 */
function getAirportSuggestions($query, $access_token = '') {
    // Use the searchAirports function which now uses Amadeus API
    return searchAirports($query, $access_token);
}

// Get airport suggestions from Amadeus API
function getAirportSuggestionsFromAmadeus($query) {
    global $pdo;
    
    try {
        // Get access token
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
            
            // Search airports using Amadeus API
            $search_url = AMADEUS_BASE_URL . '/v1/reference-data/locations?subType=AIRPORT&keyword=' . urlencode($query) . '&page[limit]=10';
            $search_response = httpGet($search_url, $access_token);
            $search_result = json_decode($search_response, true);

            $suggestions = [];
            if (isset($search_result['data'])) {
                foreach ($search_result['data'] as $airport) {
                    $suggestions[] = [
                        'code' => $airport['iataCode'],
                        'name' => $airport['name'],
                        'city' => $airport['address']['cityName'] ?? '',
                        'country' => $airport['address']['countryName'] ?? ''
                    ];
                }
            }
            
            return $suggestions;
        }
    } catch (Exception $e) {
        error_log("Amadeus airport search error: " . $e->getMessage());
    }
    
    return [];
}

// Get airline name from Amadeus API
function getAirlineNameFromAmadeus($airlineCode) {
    // Simple cache to avoid repeated API calls
    static $cache = [];
    
    if (isset($cache[$airlineCode])) {
        return $cache[$airlineCode];
    }
    
    try {
        // Get access token
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
            
            // Get airline info from Amadeus
            $search_url = AMADEUS_BASE_URL . '/v1/reference-data/airlines?airlineCodes=' . $airlineCode;
            $search_response = httpGet($search_url, $access_token);
            $search_result = json_decode($search_response, true);

            if (isset($search_result['data'][0]['businessName'])) {
                $airlineName = $search_result['data'][0]['businessName'];
                $cache[$airlineCode] = $airlineName;
                return $airlineName;
            }
        }
    } catch (Exception $e) {
        error_log("Amadeus airline info error: " . $e->getMessage());
    }
    
    // Fallback to code if API fails
    $cache[$airlineCode] = $airlineCode;
    return $airlineCode;
}

// Get airline logo (fallback to external service)
function getAirlineLogoFromAmadeus($airlineCode) {
    // Use external service for logos since Amadeus doesn't provide them
    return 'https://images.kiwi.com/airlines/64/' . $airlineCode . '.png';
}

// Get airline info from Amadeus (for AJAX requests)
function getAirlineInfoFromAmadeus($airlineCode) {
    $info = [
        'name' => getAirlineNameFromAmadeus($airlineCode),
        'logo' => getAirlineLogoFromAmadeus($airlineCode)
    ];
    
    return $info;
}

/**
 * HTTP GET function for API calls
 */
function httpGet($url, $access_token = '') {
    $ch = curl_init();
    $headers = [
        'Accept: application/json'
    ];
    
    if ($access_token) {
        $headers[] = 'Authorization: Bearer ' . $access_token;
    }
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'TravelCentre/1.0'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        throw new Exception('cURL Error: " . $curl_error . " - URL: " . $url');
    }
    
    if ($http_code !== 200) {
        // Get more details about the error
        $error_details = json_decode($response, true);
        $error_message = isset($error_details['errors'][0]['detail']) ? $error_details['errors'][0]['detail'] : $response;
        throw new Exception('HTTP Error: " . $http_code . " - " . $error_message . " - URL: " . $url');
    }
    
    return $response;
}

/**
 * HTTP POST function for API calls
 */
function httpPost($url, $data) {
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded'
        ]
    ]);
    
    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        throw new Exception('cURL Error: ' . $curl_error);
    }
    
    return $response;
}

/**
 * Get quick search routes for popular destinations
 */
function getQuickSearchRoutes() {
    $next_week = date('Y-m-d', strtotime('+7 days'));
    $two_weeks = date('Y-m-d', strtotime('+14 days'));
    
    return [
        ['from' => 'LOS', 'to' => 'ABV', 'label' => 'Lagos → Abuja'],
        ['from' => 'LOS', 'to' => 'LHR', 'label' => 'Lagos → London'],
        ['from' => 'ABV', 'to' => 'DXB', 'label' => 'Abuja → Dubai'],
        ['from' => 'LOS', 'to' => 'ACC', 'label' => 'Lagos → Accra'],
        ['from' => 'LOS', 'to' => 'JFK', 'label' => 'Lagos → New York'],
        ['from' => 'ABV', 'to' => 'CDG', 'label' => 'Abuja → Paris']
    ];
}

/**
 * Generate WhatsApp booking message
 */
function generateWhatsAppMessage($flightData, $passengers, $travelClass, $finalPrice) {
    $itinerary = $flightData['itineraries'][0];
    $firstSegment = $itinerary['segments'][0];
    $lastSegment = end($itinerary['segments']);
    $airlineCode = $firstSegment['carrierCode'];
    
    $message = "🛫 *FLIGHT BOOKING REQUEST* 🛬\n\n";
    $message .= "*Route:* {$firstSegment['departure']['iataCode']} → {$lastSegment['arrival']['iataCode']}\n";
    $message .= "*Airline:* " . getAirlineName($airlineCode) . "\n";
    $message .= "*Flight Number:* {$airlineCode}{$firstSegment['number']}\n";
    $message .= "*Departure:* " . date('M j, Y g:i A', strtotime($firstSegment['departure']['at'])) . "\n";
    $message .= "*Arrival:* " . date('M j, Y g:i A', strtotime($lastSegment['arrival']['at'])) . "\n";
    $message .= "*Duration:* " . formatFlightDuration($itinerary['duration']) . "\n";
    $message .= "*Passengers:* {$passengers}\n";
    $message .= "*Class:* {$travelClass}\n";
    $message .= "*Total Price:* ₦" . number_format($finalPrice, 2) . "\n\n";
    $message .= "Please confirm availability and proceed with booking.";
    
    return $message;
}

/**
 * NEW FUNCTION: Get Amadeus access token from session or generate new one
 */
function getAmadeusAccessToken($pdo = null) {
    // Check if we have a valid token in session
    if (isset($_SESSION['amadeus_access_token']) && isset($_SESSION['amadeus_token_expiry'])) {
        $expiry = $_SESSION['amadeus_token_expiry'];
        // Check if token is still valid (with 60 second buffer)
        if (time() < ($expiry - 60)) {
            return $_SESSION['amadeus_access_token'];
        }
    }
    
    // If no valid token, get a new one
    return refreshAmadeusAccessToken($pdo);
}

/**
 * NEW FUNCTION: Refresh Amadeus access token
 */
function refreshAmadeusAccessToken($pdo = null) {
    try {
        // Get API credentials from database or constants
        $api_key = '';
        $api_secret = '';
        
        if ($pdo) {
            // Try to get from database first
            $api_key = getSiteSetting($pdo, 'amadeus_api_key');
            $api_secret = getSiteSetting($pdo, 'amadeus_api_secret');
        }
        
        // Fallback to constants if not found in database
        if (empty($api_key) && defined('AMADEUS_API_KEY')) {
            $api_key = AMADEUS_API_KEY;
        }
        if (empty($api_secret) && defined('AMADEUS_API_SECRET')) {
            $api_secret = AMADEUS_API_SECRET;
        }
        
        if (empty($api_key) || empty($api_secret)) {
            throw new Exception('Amadeus API credentials not configured');
        }
        
        $url = "https://test.api.amadeus.com/v1/security/oauth2/token";
        $data = [
            'grant_type' => 'client_credentials',
            'client_id' => $api_key,
            'client_secret' => $api_secret
        ];
        
        $response = httpPost($url, $data);
        $token_data = json_decode($response, true);
        
        if (isset($token_data['access_token'])) {
            // Store token and expiry in session
            $_SESSION['amadeus_access_token'] = $token_data['access_token'];
            $_SESSION['amadeus_token_expiry'] = time() + $token_data['expires_in'];
            
            return $token_data['access_token'];
        } else {
            throw new Exception('Failed to get access token: ' . $response);
        }
    } catch (Exception $e) {
        error_log("Amadeus token refresh error: " . $e->getMessage());
        return '';
    }
}

/**
 * NEW FUNCTION: Search airports with enhanced Amadeus API integration
 */
function searchAirportsEnhanced($query, $pdo = null) {
    $access_token = getAmadeusAccessToken($pdo);
    
    if (empty($access_token)) {
        error_log("No Amadeus access token available for airport search");
        return searchAirportsStatic($query);
    }
    
    return searchAirports($query, $access_token);
}

/**
 * NEW FUNCTION: Get airport information with enhanced Amadeus API integration
 */
function getAirportInfoEnhanced($code, $pdo = null) {
    $access_token = getAmadeusAccessToken($pdo);
    
    if (empty($access_token)) {
        return getAirportInfo($code);
    }
    
    return getAirportInfo($code, $access_token);
}

/**
 * NEW FUNCTION: Test Amadeus API connection
 */
function testAmadeusConnection($pdo = null) {
    try {
        $access_token = getAmadeusAccessToken($pdo);
        
        if (empty($access_token)) {
            return [
                'success' => false,
                'message' => 'Failed to obtain Amadeus access token. Please check your API credentials.'
            ];
        }
        
        // Test with a simple airport search
        $test_query = 'Lagos';
        $airports = searchAirports($test_query, $access_token);
        
        if (is_array($airports) && !empty($airports)) {
            return [
                'success' => true,
                'message' => 'Amadeus API connection successful! Found ' . count($airports) . ' airports for query: ' . $test_query,
                'sample_data' => array_slice($airports, 0, 3) // Return first 3 results as sample
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Amadeus API connection successful but no airports found. Please check your API permissions.'
            ];
        }
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Amadeus API test failed: ' . $e->getMessage()
        ];
    }
}

// Add this function to includes/functions.php
function sendGuestBookingEmails($pdo, $booking_id, $guest_email, $booking_reference, $total_amount, $currency) {
    // Get booking details
    $stmt = $pdo->prepare("SELECT * FROM flight_bookings WHERE id = ?");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get admin email settings
    $admin_email = getSiteSetting($pdo, 'admin_email', 'admin@travelcentre.com');
    
    // Send email to guest
    $guest_subject = "Flight Booking Pending Payment - Travel Centre";
    $guest_message = "
        Dear Guest,
        
        Thank you for your flight booking with Travel Centre!
        
        Booking Reference: {$booking_reference}
        Total Amount: {$currency} {$total_amount}
        
        Your booking is pending payment. Please complete the payment to confirm your flight.
        
        You can track your booking status using your booking reference.
        
        Best regards,
        Travel Centre Team
    ";
    
    // Send email to admin
    $admin_subject = "New Guest Booking - Pending Payment";
    $admin_message = "
        New guest booking received:
        
        Booking Reference: {$booking_reference}
        Guest Email: {$guest_email}
        Total Amount: {$currency} {$total_amount}
        Booking ID: {$booking_id}
        
        Please check the admin dashboard for details.
    ";
    
    // In a real implementation, you would use PHPMailer or similar
    // For now, we'll just log this (you should implement proper email sending)
    error_log("Guest booking email to: {$guest_email}");
    error_log("Admin notification email to: {$admin_email}");
    
    return true;
}
?>