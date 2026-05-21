<?php
// admin/invoice-preview.php
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

$invoice_id = intval($_GET['id'] ?? 0);
$download = isset($_GET['download']) && $_GET['download'] == 1;

if (!$invoice_id) {
    die('Invalid invoice ID');
}

// Fetch invoice from database
try {
    $stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$invoice) {
        die('Invoice not found');
    }
} catch (Exception $e) {
    die('Error fetching invoice: ' . $e->getMessage());
}

// Decode JSON data
$flight_data = json_decode($invoice['flight_data'], true);
$passenger_data = json_decode($invoice['passenger_data'], true);
$contact_info = json_decode($invoice['contact_info'], true);

// Set headers for download
if ($download) {
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="invoice-' . $invoice['invoice_number'] . '.pdf"');
    // In a real implementation, you would generate PDF here
    // For now, we'll redirect to the view page
    header("Location: invoice-preview.php?id={$invoice_id}");
    exit;
}

$page_title = "Invoice #" . $invoice['invoice_number'];

// Extract flight details
$itinerary = $flight_data['itineraries'][0] ?? [];
$first_segment = $itinerary['segments'][0] ?? [];
$last_segment = end($itinerary['segments']) ?? [];
$base_price = $invoice['base_price'];
$final_price = $invoice['final_price'];
$price_adjustment = $invoice['price_adjustment'];
$adjustment_type = $invoice['adjustment_type'];

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

// Calculate adjustment amount
$adjustment_amount = 0;
if ($adjustment_type === 'percentage' && $price_adjustment != 0) {
    $adjustment_amount = ($base_price * $price_adjustment) / 100;
} elseif ($adjustment_type === 'flat' && $price_adjustment != 0) {
    $adjustment_amount = $price_adjustment;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Travel Centre</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            line-height: 1.6;
        }
        
        .invoice-container {
            max-width: 1000px;
            margin: 2rem auto;
            background: white;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            border-radius: 10px;
            overflow: hidden;
        }
        
        .invoice-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .invoice-header h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }
        
        .invoice-header p {
            font-size: 1.2rem;
            opacity: 0.9;
        }
        
        .invoice-body {
            padding: 2rem;
        }
        
        .invoice-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 2px solid #e9ecef;
        }
        
        .info-section h3 {
            color: #667eea;
            margin-bottom: 1rem;
            font-size: 1.3rem;
            border-left: 4px solid #667eea;
            padding-left: 1rem;
        }
        
        .info-grid {
            display: grid;
            gap: 0.75rem;
        }
        
        .info-item {
            display: flex;
            justify-content: between;
        }
        
        .info-label {
            font-weight: bold;
            color: #495057;
            min-width: 120px;
        }
        
        .info-value {
            color: #6c757d;
        }
        
        .flight-summary {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            border-left: 4px solid #667eea;
        }
        
        .flight-route {
            font-size: 1.5rem;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 1rem;
            text-align: center;
        }
        
        .flight-timeline {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            gap: 1rem;
            align-items: center;
            margin: 1.5rem 0;
        }
        
        .timeline-connector {
            height: 2px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            width: 100px;
            margin: 0 auto;
            position: relative;
        }
        
        .timeline-connector::before {
            content: '✈️';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 0.25rem;
            border-radius: 50%;
            font-size: 0.8rem;
        }
        
        .time-display {
            text-align: center;
        }
        
        .time-main {
            font-size: 1.5rem;
            font-weight: bold;
            color: #495057;
        }
        
        .time-sub {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .flight-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            text-align: center;
            margin-top: 1rem;
        }
        
        .detail-item {
            padding: 1rem;
            background: white;
            border-radius: 5px;
            border: 1px solid #e9ecef;
        }
        
        .detail-label {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 0.5rem;
        }
        
        .detail-value {
            font-weight: bold;
            color: #495057;
        }
        
        .passenger-table {
            width: 100%;
            border-collapse: collapse;
            margin: 1.5rem 0;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .passenger-table th {
            background: #667eea;
            color: white;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
        }
        
        .passenger-table td {
            padding: 1rem;
            border-bottom: 1px solid #e9ecef;
            color: #495057;
        }
        
        .passenger-table tr:last-child td {
            border-bottom: none;
        }
        
        .price-breakdown {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin: 2rem 0;
        }
        
        .price-item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .price-item:last-child {
            border-bottom: none;
        }
        
        .price-total {
            font-size: 1.3rem;
            font-weight: bold;
            color: #28a745;
            border-top: 2px solid #28a745;
            margin-top: 1rem;
            padding-top: 1rem;
        }
        
        .invoice-actions {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 2px solid #e9ecef;
        }
        
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
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5a6fd8;
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
        }
        
        .btn-info {
            background: #17a2b8;
            color: white;
        }
        
        .btn-info:hover {
            background: #138496;
            transform: translateY(-2px);
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
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
        
        .payment-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .payment-paid {
            background: #d4edda;
            color: #155724;
        }
        
        @media print {
            body {
                background: white;
            }
            
            .invoice-container {
                box-shadow: none;
                margin: 0;
            }
            
            .invoice-actions {
                display: none;
            }
            
            .btn {
                display: none;
            }
        }
        
        @media (max-width: 768px) {
            .invoice-info {
                grid-template-columns: 1fr;
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
            
            .invoice-actions {
                flex-direction: column;
            }
            
            .flight-details-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <!-- Invoice Header -->
        <div class="invoice-header">
            <h1>Travel Centre</h1>
            <p>Flight Booking Invoice</p>
            <div style="margin-top: 1rem; display: flex; justify-content: center; gap: 2rem; flex-wrap: wrap;">
                <span class="status-badge status-<?php echo $invoice['status']; ?>">
                    <?php echo ucfirst($invoice['status']); ?>
                </span>
                <span class="status-badge payment-<?php echo $invoice['payment_status']; ?>">
                    Payment: <?php echo ucfirst($invoice['payment_status']); ?>
                </span>
            </div>
        </div>
        
        <!-- Invoice Body -->
        <div class="invoice-body">
            <!-- Invoice Information -->
            <div class="invoice-info">
                <div class="info-section">
                    <h3>Invoice Details</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Invoice Number:</span>
                            <span class="info-value"><?php echo htmlspecialchars($invoice['invoice_number']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Quote Reference:</span>
                            <span class="info-value"><?php echo htmlspecialchars($invoice['quote_reference']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Tracking ID:</span>
                            <span class="info-value"><?php echo htmlspecialchars($invoice['tracking_id']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Issue Date:</span>
                            <span class="info-value"><?php echo date('F j, Y', strtotime($invoice['created_at'])); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="info-section">
                    <h3>Contact Information</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Name:</span>
                            <span class="info-value"><?php echo htmlspecialchars($contact_info['name'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Email:</span>
                            <span class="info-value"><?php echo htmlspecialchars($contact_info['email'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Phone:</span>
                            <span class="info-value"><?php echo htmlspecialchars($contact_info['phone'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Address:</span>
                            <span class="info-value"><?php echo htmlspecialchars($contact_info['address'] ?? 'N/A'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Flight Summary -->
            <div class="flight-summary">
                <div class="flight-route">
                    <?php echo $flight_route; ?>
                </div>
                
                <div style="text-align: center; color: #6c757d; margin-bottom: 1rem;">
                    <?php echo $departure_date; ?>
                </div>
                
                <div class="flight-timeline">
                    <div class="time-display">
                        <div class="time-main"><?php echo $departure_time; ?></div>
                        <div class="time-sub"><?php echo $first_segment['departure']['iataCode'] ?? ''; ?></div>
                    </div>
                    
                    <div class="timeline-connector"></div>
                    
                    <div class="time-display">
                        <div class="time-main"><?php echo $arrival_time; ?></div>
                        <div class="time-sub"><?php echo $last_segment['arrival']['iataCode'] ?? ''; ?></div>
                    </div>
                </div>
                
                <div class="flight-details-grid">
                    <div class="detail-item">
                        <div class="detail-label">Airline</div>
                        <div class="detail-value"><?php echo $airline_name; ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Class</div>
                        <div class="detail-value"><?php echo $flight_class; ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Duration</div>
                        <div class="detail-value"><?php echo $duration; ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Source</div>
                        <div class="detail-value"><?php echo isset($flight_data['source']) ? $flight_data['source'] : 'API'; ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Passenger Details -->
            <div class="info-section">
                <h3>Passenger Details (<?php echo count($passenger_data); ?> Passenger<?php echo count($passenger_data) > 1 ? 's' : ''; ?>)</h3>
                <table class="passenger-table">
                    <thead>
                        <tr>
                            <th>Passenger</th>
                            <th>Full Name</th>
                            <th>Email</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($passenger_data as $index => $passenger): ?>
                            <tr>
                                <td>Passenger <?php echo $index + 1; ?></td>
                                <td><?php echo htmlspecialchars($passenger['name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($passenger['email'] ?? 'N/A'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Price Breakdown -->
            <div class="price-breakdown">
                <h3 style="color: #667eea; margin-bottom: 1rem; border-left: 4px solid #667eea; padding-left: 1rem;">Price Breakdown</h3>
                
                <div class="price-item">
                    <span>Base Fare:</span>
                    <span>₦<?php echo number_format($base_price, 2); ?></span>
                </div>
                
                <?php if ($adjustment_amount != 0): ?>
                <div class="price-item">
                    <span>
                        <?php 
                        if ($adjustment_type === 'percentage') {
                            echo "Price Adjustment ({$price_adjustment}%):";
                        } else {
                            echo "Price Adjustment:";
                        }
                        ?>
                    </span>
                    <span style="color: <?php echo $adjustment_amount > 0 ? '#28a745' : '#dc3545'; ?>">
                        <?php echo $adjustment_amount > 0 ? '+' : ''; ?>₦<?php echo number_format($adjustment_amount, 2); ?>
                    </span>
                </div>
                <?php endif; ?>
                
                <div class="price-item price-total">
                    <span>Total Amount:</span>
                    <span>₦<?php echo number_format($final_price, 2); ?></span>
                </div>
            </div>
            
            <!-- Important Notes -->
            <div style="background: #fff3cd; padding: 1.5rem; border-radius: 8px; border-left: 4px solid #ffc107; margin-top: 2rem;">
                <h4 style="color: #856404; margin-bottom: 1rem;">
                    <i class="fas fa-info-circle"></i> Important Information
                </h4>
                <ul style="color: #856404; padding-left: 1.5rem;">
                    <li>This invoice is valid for 30 days from the issue date</li>
                    <li>Payment must be completed to confirm your booking</li>
                    <li>Use the Tracking ID to check your booking status</li>
                    <li>Contact support@travelcentre.ng for any inquiries</li>
                    <li>Call +234 903 407 2383 for urgent assistance</li>
                </ul>
            </div>
            
            <!-- Action Buttons -->
            <div class="invoice-actions">
                <button onclick="window.print()" class="btn btn-primary">
                    <i class="fas fa-print"></i> Print Invoice
                </button>
                <a href="invoice-preview.php?id=<?php echo $invoice['id']; ?>&download=1" class="btn btn-success">
                    <i class="fas fa-download"></i> Download PDF
                </a>
                <a href="../admin/invoice.php?action=send_payment_link&id=<?php echo $invoice['id']; ?>" class="btn btn-info">
                    <i class="fas fa-share"></i> Share Invoice
                </a>
                <a href="../track-ticket.php?tracking_id=<?php echo urlencode($invoice['tracking_id']); ?>" 
                   class="btn btn-primary" target="_blank">
                    <i class="fas fa-ticket-alt"></i> Track Booking
                </a>
                <a href="../admin/invoice.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Back to Invoices
                </a>
            </div>
        </div>
    </div>

    <script>
        // Auto-print if download parameter is set
        <?php if ($download): ?>
        window.onload = function() {
            window.print();
            // Redirect back after a delay
            setTimeout(function() {
                window.location.href = 'invoice.php?tab=invoices';
            }, 1000);
        };
        <?php endif; ?>

        // Add keyboard shortcut for printing (Ctrl+P)
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
        });

        // Enhanced print functionality
        function printInvoice() {
            const originalTitle = document.title;
            document.title = "Invoice_<?php echo $invoice['invoice_number']; ?>_TravelCentre";
            window.print();
            document.title = originalTitle;
        }

        // Share functionality
        function shareInvoice() {
            if (navigator.share) {
                navigator.share({
                    title: 'Travel Centre Invoice - <?php echo $invoice['invoice_number']; ?>',
                    text: 'Flight Booking Invoice from Travel Centre. Amount: ₦<?php echo number_format($final_price, 2); ?>',
                    url: window.location.href,
                })
                .catch(error => console.log('Error sharing:', error));
            } else {
                // Fallback: copy to clipboard
                const tempInput = document.createElement('input');
                tempInput.value = window.location.href;
                document.body.appendChild(tempInput);
                tempInput.select();
                document.execCommand('copy');
                document.body.removeChild(tempInput);
                alert('Invoice link copied to clipboard!');
            }
        }

        // Download as PDF functionality
        function downloadPDF() {
            // In a real implementation, this would generate a PDF
            // For now, we'll use the browser's print to PDF
            window.print();
        }

        // Auto-focus on print for better UX
        document.addEventListener('DOMContentLoaded', function() {
            // Add print styles dynamically
            const printStyles = `
                @media print {
                    .invoice-actions { display: none !important; }
                    .btn { display: none !important; }
                    body { background: white !important; }
                    .invoice-container { box-shadow: none !important; margin: 0 !important; }
                }
            `;
            const styleSheet = document.createElement('style');
            styleSheet.innerText = printStyles;
            document.head.appendChild(styleSheet);
        });
    </script>
</body>
</html>