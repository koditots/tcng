<?php
// print-ticket.php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;

// Get booking details
$stmt = $pdo->prepare("SELECT * FROM flight_bookings WHERE id = ? AND user_id = ?");
$stmt->execute([$booking_id, $_SESSION['user_id']]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking || $booking['status'] !== 'confirmed') {
    redirect('dashboard.php');
}

$flight_data = json_decode($booking['flight_data'], true);
$passenger_info = json_decode($booking['passenger_info'], true);
$contact_info = json_decode($booking['contact_info'], true);

// Enhanced passenger info validation and fallback
if (empty($passenger_info) || !is_array($passenger_info)) {
    // Try to get passenger info from travelerPricings in flight_data as fallback
    if (isset($flight_data['travelerPricings']) && is_array($flight_data['travelerPricings'])) {
        $passenger_info = [];
        foreach ($flight_data['travelerPricings'] as $traveler) {
            if (isset($traveler['travelerId']) && isset($traveler['associatedAdultId'])) {
                $passenger_info[] = [
                    'first_name' => 'Passenger',
                    'last_name' => '#' . $traveler['travelerId'],
                    'type' => $traveler['travelerType'] ?? 'ADULT'
                ];
            }
        }
    }
    
    // If still empty, create a default passenger entry
    if (empty($passenger_info)) {
        $passenger_info = [
            [
                'first_name' => 'Passenger',
                'last_name' => 'Information',
                'type' => 'ADULT'
            ]
        ];
    }
}

// Additional fallback: Check if we have contact info that can be used
if ((empty($passenger_info[0]['first_name']) || $passenger_info[0]['first_name'] === 'Passenger') && 
    !empty($contact_info) && is_array($contact_info)) {
    
    if (isset($contact_info['firstName']) || isset($contact_info['lastName'])) {
        $passenger_info[0]['first_name'] = $contact_info['firstName'] ?? 'Passenger';
        $passenger_info[0]['last_name'] = $contact_info['lastName'] ?? 'Information';
    }
}

$itinerary = $flight_data['itineraries'][0];
$first_segment = $itinerary['segments'][0];
$last_segment = end($itinerary['segments']);

// Function to format passenger name properly
function formatPassengerName($passenger) {
    $firstName = $passenger['first_name'] ?? 'Passenger';
    $lastName = $passenger['last_name'] ?? 'Information';
    
    // Check if we need to fallback to a more descriptive name
    if ($firstName === 'Passenger' && $lastName === 'Information') {
        return 'Passenger Information';
    }
    
    return $firstName . ' ' . $lastName;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Ticket - <?php echo $booking['booking_reference']; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            background: #f8f9fa;
            color: #333;
            line-height: 1.4;
        }
        
        .ticket {
            max-width: 800px;
            margin: 2rem auto;
            background: white;
            border: 2px solid #333;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .ticket-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .ticket-body {
            padding: 2rem;
        }
        
        .flight-info {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            gap: 2rem;
            align-items: center;
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .airport {
            text-align: center;
        }
        
        .airport-code {
            font-size: 2rem;
            font-weight: bold;
            color: #007bff;
        }
        
        .airport-name {
            color: #666;
            font-size: 0.9rem;
        }
        
        .flight-duration {
            text-align: center;
        }
        
        .duration-line {
            height: 2px;
            background: #007bff;
            margin: 0.5rem 0;
        }
        
        .passenger-info {
            margin-bottom: 2rem;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .info-item {
            margin-bottom: 0.5rem;
        }
        
        .info-label {
            font-weight: bold;
            color: #666;
            font-size: 0.9rem;
        }
        
        .info-value {
            color: #333;
            font-size: 1rem;
        }
        
        .passenger-status {
            background: #ffc107;
            color: #856404;
            padding: 0.25rem 0.5rem;
            border-radius: 3px;
            font-size: 0.8rem;
            margin-left: 0.5rem;
        }
        
        .barcode {
            text-align: center;
            margin: 2rem 0;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .barcode-number {
            font-family: 'Courier New', monospace;
            font-size: 1.2rem;
            letter-spacing: 2px;
            margin-bottom: 0.5rem;
        }
        
        .terms {
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid #ddd;
            font-size: 0.8rem;
            color: #666;
        }
        
        .print-only {
            display: none;
        }
        
        @media print {
            body {
                background: white;
                margin: 0;
                padding: 0;
            }
            
            .ticket {
                margin: 0;
                border: none;
                border-radius: 0;
                box-shadow: none;
            }
            
            .no-print {
                display: none;
            }
            
            .print-only {
                display: block;
            }
            
            .ticket-body {
                padding: 1rem;
            }
        }
        
        .action-buttons {
            text-align: center;
            margin: 2rem 0;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 0 0.5rem;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .warning-note {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 1rem;
            margin-bottom: 1rem;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="ticket">
        <div class="ticket-header">
            <h1>ELECTRONIC TICKET</h1>
            <p>BOARDING PASS</p>
        </div>
        
        <div class="ticket-body">
            <?php if (($passenger_info[0]['first_name'] ?? '') === 'Passenger' && ($passenger_info[0]['last_name'] ?? '') === 'Information'): ?>
            <div class="warning-note">
                <strong>Note:</strong> Passenger details are being retrieved. Please check back later or contact support if this persists.
            </div>
            <?php endif; ?>
            
            <!-- Flight Information -->
            <div class="flight-info">
                <div class="airport">
                    <div class="airport-code"><?php echo $first_segment['departure']['iataCode']; ?></div>
                    <div class="airport-name">Departure</div>
                    <div class="flight-time"><?php echo date('H:i', strtotime($first_segment['departure']['at'])); ?></div>
                    <div class="flight-date"><?php echo date('M j, Y', strtotime($first_segment['departure']['at'])); ?></div>
                </div>
                
                <div class="flight-duration">
                    <div style="font-size: 0.9rem; color: #666;">Flight Duration</div>
                    <div style="font-weight: bold; margin: 0.5rem 0;"><?php echo substr($itinerary['duration'], 2); ?></div>
                    <div class="duration-line"></div>
                    <div style="font-size: 0.9rem; color: #666;"><?php echo $first_segment['carrierCode']; ?></div>
                </div>
                
                <div class="airport">
                    <div class="airport-code"><?php echo $last_segment['arrival']['iataCode']; ?></div>
                    <div class="airport-name">Arrival</div>
                    <div class="flight-time"><?php echo date('H:i', strtotime($last_segment['arrival']['at'])); ?></div>
                    <div class="flight-date"><?php echo date('M j, Y', strtotime($last_segment['arrival']['at'])); ?></div>
                </div>
            </div>
            
            <!-- Passenger Information -->
            <div class="passenger-info">
                <h3 style="margin-bottom: 1rem; color: #333;">Passenger Information</h3>
                <div class="info-grid">
                    <?php foreach ($passenger_info as $index => $passenger): ?>
                    <div>
                        <div class="info-item">
                            <div class="info-label">Passenger <?php echo $index + 1; ?></div>
                            <div class="info-value">
                                <?php echo formatPassengerName($passenger); ?>
                                <?php if (isset($passenger['type'])): ?>
                                <span class="passenger-status"><?php echo $passenger['type']; ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Ticket Number</div>
                            <div class="info-value" style="font-family: 'Courier New', monospace;">
                                <?php 
                                // Handle multiple ticket numbers if available
                                if (is_array($booking['ticket_number'])) {
                                    echo $booking['ticket_number'][$index] ?? $booking['ticket_number'];
                                } else {
                                    echo $booking['ticket_number'];
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <div>
                        <div class="info-item">
                            <div class="info-label">Booking Reference</div>
                            <div class="info-value" style="font-family: 'Courier New', monospace;"><?php echo $booking['booking_reference']; ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Flight Class</div>
                            <div class="info-value">
                                <?php 
                                $cabin = $flight_data['travelerPricings'][0]['fareDetailsBySegment'][0]['cabin'] ?? 'ECONOMY';
                                echo ucfirst(strtolower($cabin));
                                ?>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <div class="info-item">
                            <div class="info-label">Airline</div>
                            <div class="info-value"><?php echo $first_segment['carrierCode']; ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Aircraft</div>
                            <div class="info-value"><?php echo $first_segment['aircraft']['code'] ?? 'N/A'; ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Flight Segments -->
            <div style="margin-bottom: 2rem;">
                <h3 style="margin-bottom: 1rem; color: #333;">Flight Details</h3>
                <?php foreach ($itinerary['segments'] as $segment): ?>
                <div style="display: grid; grid-template-columns: 1fr auto 1fr; gap: 1rem; align-items: center; padding: 1rem; background: #f8f9fa; border-radius: 5px; margin-bottom: 0.5rem;">
                    <div>
                        <strong><?php echo date('H:i', strtotime($segment['departure']['at'])); ?></strong>
                        <br>
                        <small><?php echo $segment['departure']['iataCode']; ?></small>
                    </div>
                    <div style="text-align: center;">
                        <div style="height: 1px; background: #007bff; width: 50px; margin: 0 auto;"></div>
                        <small><?php echo substr($segment['duration'], 2); ?></small>
                    </div>
                    <div style="text-align: right;">
                        <strong><?php echo date('H:i', strtotime($segment['arrival']['at'])); ?></strong>
                        <br>
                        <small><?php echo $segment['arrival']['iataCode']; ?></small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Barcode -->
            <div class="barcode">
                <div class="barcode-number"><?php echo $booking['ticket_number']; ?></div>
                <div style="font-family: 'Courier New', monospace; letter-spacing: 3px; font-size: 1.5rem;">
                    █▀█▀█▀█▀█▀█▀█▀█▀█▀█▀█
                </div>
                <small>Scan at airport for boarding</small>
            </div>
            
            <!-- Important Information -->
            <div class="terms">
                <h4>Important Information:</h4>
                <ul style="margin-left: 1.5rem;">
                    <li>Please arrive at the airport at least 2 hours before departure</li>
                    <li>Carry a valid government-issued photo ID</li>
                    <li>Check-in baggage allowance: 20kg</li>
                    <li>Carry-on baggage: 7kg</li>
                    <li>Web check-in available 24 hours before flight</li>
                </ul>
                
                <div style="margin-top: 1rem; text-align: center;">
                    <strong>Issued by: <?php echo getSiteSetting($pdo, 'site_name'); ?></strong><br>
                    <small>Ticket issued on: <?php echo date('F j, Y g:i A', strtotime($booking['created_at'])); ?></small>
                </div>
            </div>
        </div>
    </div>
    
    <div class="action-buttons no-print">
        <button onclick="window.print()" class="btn btn-primary">Print Ticket</button>
        <a href="dashboard.php" class="btn btn-success">Back to Dashboard</a>
        <button onclick="downloadTicket()" class="btn" style="background: #6c757d; color: white;">Download PDF</button>
        
        <?php if (($passenger_info[0]['first_name'] ?? '') === 'Passenger' && ($passenger_info[0]['last_name'] ?? '') === 'Information'): ?>
        <button onclick="refreshPassengerInfo()" class="btn" style="background: #17a2b8; color: white;">Refresh Passenger Info</button>
        <?php endif; ?>
    </div>
    
    <div class="print-only">
        <p style="text-align: center; margin-top: 1rem; font-size: 0.8rem;">
            This is your official boarding pass. Please keep it safe until you complete your journey.
        </p>
    </div>

    <script>
    function downloadTicket() {
        alert('Download Now!');
        // In a real implementation, you would generate a PDF here
        // For now, we'll just trigger the print dialog
        window.print();
    }
    
    function refreshPassengerInfo() {
        // This would typically make an AJAX call to refresh passenger data
        alert('Refreshing passenger information... Please wait.');
        window.location.reload();
    }
    
    // Auto-print if print parameter is set
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('print') === 'true') {
        window.print();
    }
    </script>
</body>
</html>