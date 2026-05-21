<?php
// visa-receipt.php
require_once 'config.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

$page_title = "Visa Application Receipt";
$user_id = $_SESSION['user_id'];

// Get application ID from query string
$application_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$application_id) {
    header("Location: my-visa-applications.php");
    exit;
}

// Fetch application details
try {
    $stmt = $pdo->prepare("
        SELECT va.*, u.first_name, u.last_name, u.email, u.phone 
        FROM visa_applications va 
        LEFT JOIN users u ON va.user_id = u.id 
        WHERE va.id = ? AND va.user_id = ?
    ");
    $stmt->execute([$application_id, $user_id]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$application) {
        $_SESSION['error'] = "Visa application not found or you don't have permission to view it.";
        header("Location: my-visa-applications.php");
        exit;
    }
} catch (Exception $e) {
    $_SESSION['error'] = "Error fetching application details: " . $e->getMessage();
    header("Location: my-visa-applications.php");
    exit;
}

// Get site settings for company information
$site_settings = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM site_settings ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $site_settings = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Use default values if settings not found
    $site_settings = [
        'site_name' => 'Travel Centre',
        'support_email' => 'support@travelcentre.com',
        'admin_email' => 'admin@travelcentre.com',
        'phone' => '+234 800 000 0000',
        'address' => 'Lagos, Nigeria'
    ];
}

// Generate receipt number
$receipt_number = 'RCPT' . date('Ymd') . str_pad($application_id, 4, '0', STR_PAD_LEFT);

require_once 'includes/header.php';
?>

<!-- Modern CSS Styles -->
<style>
:root {
    --primary: #2c5aa0;
    --primary-dark: #1e3d6f;
    --secondary: #6c757d;
    --success: #28a745;
    --warning: #ffc107;
    --danger: #dc3545;
    --light: #f8f9fa;
    --dark: #343a40;
    --gradient-primary: linear-gradient(135deg, #2c5aa0 0%, #1e3d6f 100%);
    --gradient-success: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    --shadow-sm: 0 2px 4px rgba(0,0,0,0.1);
    --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
    --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
    --border-radius: 12px;
    --border-radius-lg: 20px;
}

.receipt-container {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    padding: 40px 20px;
}

.receipt-card {
    background: white;
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow-lg);
    overflow: hidden;
    margin: 0 auto;
    max-width: 1000px;
}

.receipt-header {
    background: var(--gradient-primary);
    color: white;
    padding: 40px;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.receipt-header::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%);
    animation: float 6s ease-in-out infinite;
}

@keyframes float {
    0%, 100% { transform: translateY(0px) rotate(0deg); }
    50% { transform: translateY(-20px) rotate(180deg); }
}

.receipt-badge {
    background: rgba(255,255,255,0.2);
    backdrop-filter: blur(10px);
    border-radius: 50px;
    padding: 8px 20px;
    display: inline-block;
    margin-bottom: 15px;
    font-weight: 600;
    font-size: 0.9em;
}

.receipt-title {
    font-size: 2.5em;
    font-weight: 700;
    margin-bottom: 10px;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
}

.receipt-subtitle {
    font-size: 1.2em;
    opacity: 0.9;
    margin-bottom: 0;
}

.receipt-body {
    padding: 40px;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 30px;
    margin-bottom: 40px;
}

.info-card {
    background: var(--light);
    border-radius: var(--border-radius);
    padding: 25px;
    border-left: 4px solid var(--primary);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.info-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-md);
}

.info-card h4 {
    color: var(--primary);
    margin-bottom: 15px;
    font-weight: 600;
}

.payment-summary {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: var(--border-radius);
    padding: 30px;
    margin-bottom: 30px;
    border: 1px solid rgba(0,0,0,0.05);
}

.payment-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border-radius: var(--border-radius);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
}

.payment-table th {
    background: var(--primary);
    color: white;
    padding: 15px;
    text-align: left;
    font-weight: 600;
}

.payment-table td {
    padding: 15px;
    border-bottom: 1px solid #eee;
}

.payment-table tr:last-child td {
    border-bottom: none;
}

.payment-table .total-row {
    background: var(--light);
    font-weight: 700;
    font-size: 1.1em;
}

.status-badge {
    padding: 8px 16px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.85em;
    display: inline-block;
}

.timeline-container {
    margin: 40px 0;
}

.timeline {
    display: flex;
    justify-content: space-between;
    position: relative;
    margin: 40px 0;
}

.timeline::before {
    content: '';
    position: absolute;
    top: 25px;
    left: 0;
    right: 0;
    height: 3px;
    background: #e9ecef;
    z-index: 1;
}

.timeline-step {
    position: relative;
    z-index: 2;
    text-align: center;
    flex: 1;
}

.timeline-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: white;
    border: 3px solid #e9ecef;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 10px;
    font-size: 1.2em;
    transition: all 0.3s ease;
}

.timeline-step.active .timeline-icon {
    background: var(--success);
    border-color: var(--success);
    color: white;
    transform: scale(1.1);
}

.timeline-step.completed .timeline-icon {
    background: var(--success);
    border-color: var(--success);
    color: white;
}

.timeline-step.pending .timeline-icon {
    background: var(--warning);
    border-color: var(--warning);
    color: white;
}

.timeline-label {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 5px;
}

.timeline-date {
    font-size: 0.85em;
    color: var(--secondary);
}

.contact-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-top: 40px;
}

.contact-card {
    background: white;
    border-radius: var(--border-radius);
    padding: 25px;
    text-align: center;
    box-shadow: var(--shadow-sm);
    border: 1px solid rgba(0,0,0,0.05);
    transition: all 0.3s ease;
}

.contact-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-md);
}

.contact-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: var(--gradient-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 15px;
    color: white;
    font-size: 1.5em;
}

.receipt-footer {
    background: var(--light);
    padding: 25px 40px;
    border-top: 1px solid #dee2e6;
    text-align: center;
}

.action-buttons {
    display: flex;
    gap: 15px;
    justify-content: center;
    flex-wrap: wrap;
    margin-top: 30px;
}

.btn-modern {
    padding: 12px 30px;
    border-radius: 25px;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
}

.btn-primary-modern {
    background: var(--gradient-primary);
    color: white;
    box-shadow: var(--shadow-md);
}

.btn-primary-modern:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
    color: white;
}

.btn-outline-modern {
    background: white;
    color: var(--primary);
    border: 2px solid var(--primary);
}

.btn-outline-modern:hover {
    background: var(--primary);
    color: white;
    transform: translateY(-2px);
}

/* Print Styles */
@media print {
    .receipt-container {
        background: white !important;
        padding: 0 !important;
    }
    
    .receipt-card {
        box-shadow: none !important;
        margin: 0 !important;
    }
    
    .action-buttons, .no-print {
        display: none !important;
    }
    
    .receipt-header {
        background: #2c5aa0 !important;
        -webkit-print-color-adjust: exact;
    }
}

/* Responsive Design */
@media (max-width: 768px) {
    .receipt-container {
        padding: 20px 10px;
    }
    
    .receipt-body {
        padding: 20px;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .timeline {
        flex-direction: column;
        gap: 20px;
    }
    
    .timeline::before {
        display: none;
    }
    
    .action-buttons {
        flex-direction: column;
        align-items: center;
    }
    
    .btn-modern {
        width: 100%;
        justify-content: center;
    }
}
</style>

<div class="receipt-container">
    <div class="receipt-card">
        <!-- Header Section -->
        <div class="receipt-header">
            <div class="receipt-badge">
                <i class="fas fa-check-circle me-2"></i>Payment Confirmed
            </div>
            <h1 class="receipt-title">Payment Receipt</h1>
            <p class="receipt-subtitle">Visa Application Processing</p>
            
            <div class="row mt-4">
                <div class="col-md-6">
                    <h4 class="text-white mb-2"><?php echo $site_settings['site_name'] ?? 'Travel Centre'; ?></h4>
                    <p class="text-white opacity-75 mb-0">Official Receipt</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <h4 class="text-white mb-1">#<?php echo $receipt_number; ?></h4>
                    <p class="text-white opacity-75 mb-0"><?php echo date('F j, Y'); ?></p>
                </div>
            </div>
        </div>

        <!-- Body Section -->
        <div class="receipt-body">
            <!-- Information Grid -->
            <div class="info-grid">
                <div class="info-card">
                    <h4><i class="fas fa-building me-2"></i>From</h4>
                    <p class="mb-1 fw-bold"><?php echo $site_settings['site_name'] ?? 'Travel Centre'; ?></p>
                    <?php if (!empty($site_settings['address'])): ?>
                        <p class="mb-1 text-muted"><?php echo $site_settings['address']; ?></p>
                    <?php endif; ?>
                    <?php if (!empty($site_settings['phone'])): ?>
                        <p class="mb-1 text-muted"><?php echo $site_settings['phone']; ?></p>
                    <?php endif; ?>
                    <?php if (!empty($site_settings['support_email'])): ?>
                        <p class="mb-0 text-muted"><?php echo $site_settings['support_email']; ?></p>
                    <?php endif; ?>
                </div>

                <div class="info-card">
                    <h4><i class="fas fa-user me-2"></i>To</h4>
                    <p class="mb-1 fw-bold"><?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?></p>
                    <p class="mb-1 text-muted"><?php echo htmlspecialchars($application['email']); ?></p>
                    <p class="mb-0 text-muted"><?php echo htmlspecialchars($application['phone']); ?></p>
                </div>

                <div class="info-card">
                    <h4><i class="fas fa-info-circle me-2"></i>Application Details</h4>
                    <p class="mb-1"><strong>Application #:</strong> <?php echo $application['application_number']; ?></p>
                    <p class="mb-1"><strong>Destination:</strong> <?php echo htmlspecialchars($application['destination_country']); ?></p>
                    <p class="mb-0"><strong>Purpose:</strong> <?php echo ucfirst(str_replace('_', ' ', $application['purpose_of_travel'])); ?></p>
                </div>
            </div>

            <!-- Payment Summary -->
            <div class="payment-summary">
                <h4 class="mb-4 text-center"><i class="fas fa-receipt me-2"></i>Payment Summary</h4>
                <table class="payment-table">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <strong>Visa Application Fee</strong>
                                <div class="text-muted small mt-1">
                                    Processing and handling charges included
                                </div>
                            </td>
                            <td class="text-end fw-bold">₦<?php echo number_format($application['total_amount'], 2); ?></td>
                        </tr>
                        <tr class="total-row">
                            <td><strong>Total Amount</strong></td>
                            <td class="text-end"><strong>₦<?php echo number_format($application['total_amount'], 2); ?></strong></td>
                        </tr>
                    </tbody>
                </table>

                <div class="row mt-4">
                    <div class="col-md-6">
                        <p class="mb-2"><strong>Payment Method:</strong> Online Payment</p>
                        <p class="mb-2"><strong>Payment Status:</strong> 
                            <span class="status-badge" style="background: var(--success); color: white;">Paid</span>
                        </p>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <p class="mb-2"><strong>Application Status:</strong></p>
                        <?php
                        $status_color = '';
                        switch($application['status']) {
                            case 'approved': $status_color = 'var(--success)'; break;
                            case 'rejected': $status_color = 'var(--danger)'; break;
                            case 'pending_review': $status_color = 'var(--warning)'; break;
                            case 'pending_payment': $status_color = 'var(--primary)'; break;
                            case 'under_review': $status_color = 'var(--secondary)'; break;
                            default: $status_color = 'var(--secondary)';
                        }
                        ?>
                        <span class="status-badge" style="background: <?php echo $status_color; ?>; color: white;">
                            <?php echo ucfirst(str_replace('_', ' ', $application['status'])); ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Timeline -->
            <div class="timeline-container">
                <h4 class="text-center mb-4"><i class="fas fa-road me-2"></i>Application Journey</h4>
                <div class="timeline">
                    <?php
                    $steps = [
                        ['icon' => 'fa-file-alt', 'label' => 'Application Submitted', 'date' => date('M j, Y', strtotime($application['created_at'])), 'status' => 'completed'],
                        ['icon' => 'fa-credit-card', 'label' => 'Payment Completed', 'date' => date('M j, Y'), 'status' => 'completed'],
                        ['icon' => 'fa-cogs', 'label' => 'Processing', 'date' => 'In Progress', 'status' => in_array($application['status'], ['approved', 'rejected']) ? 'completed' : 'active'],
                        ['icon' => 'fa-flag-checkered', 'label' => 'Final Decision', 'date' => $application['status'] == 'approved' ? 'Approved' : ($application['status'] == 'rejected' ? 'Rejected' : 'Pending'), 'status' => in_array($application['status'], ['approved', 'rejected']) ? 'completed' : 'pending']
                    ];
                    
                    foreach ($steps as $step): 
                        $status_class = $step['status'] == 'completed' ? 'completed' : ($step['status'] == 'active' ? 'active' : 'pending');
                    ?>
                    <div class="timeline-step <?php echo $status_class; ?>">
                        <div class="timeline-icon">
                            <i class="fas <?php echo $step['icon']; ?>"></i>
                        </div>
                        <div class="timeline-label"><?php echo $step['label']; ?></div>
                        <div class="timeline-date"><?php echo $step['date']; ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Contact Information -->
            <div class="contact-grid">
                <div class="contact-card">
                    <div class="contact-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <h6>Email Support</h6>
                    <p class="text-muted mb-0 small"><?php echo $site_settings['support_email'] ?? 'support@travelcentre.com'; ?></p>
                </div>
                <div class="contact-card">
                    <div class="contact-icon">
                        <i class="fas fa-phone"></i>
                    </div>
                    <h6>Phone Support</h6>
                    <p class="text-muted mb-0 small"><?php echo $site_settings['phone'] ?? '+234 800 000 0000'; ?></p>
                </div>
                <div class="contact-card">
                    <div class="contact-icon">
                        <i class="fas fa-globe"></i>
                    </div>
                    <h6>Visit Website</h6>
                    <p class="text-muted mb-0 small"><?php echo SITE_URL; ?></p>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="receipt-footer">
            <div class="row align-items-center">
                <div class="col-md-6 text-md-start">
                    <small class="text-muted">
                        <i class="fas fa-clock me-1"></i>Generated: <?php echo date('F j, Y \a\t g:i A'); ?>
                    </small>
                </div>
                <div class="col-md-6 text-md-end">
                    <small class="text-muted">
                        <i class="fas fa-fingerprint me-1"></i>Transaction ID: <?php echo $receipt_number; ?>
                    </small>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons no-print">
                <button onclick="window.print()" class="btn-modern btn-primary-modern">
                    <i class="fas fa-print"></i>Print Receipt
                </button>
                <a href="my-visa-applications.php" class="btn-modern btn-outline-modern">
                    <i class="fas fa-arrow-left"></i>Back to Applications
                </a>
                <a href="visa-application-details.php?id=<?php echo $application['id']; ?>" class="btn-modern btn-outline-modern">
                    <i class="fas fa-eye"></i>View Application
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Font Awesome for icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add floating animation to receipt badge
    const badge = document.querySelector('.receipt-badge');
    if (badge) {
        setInterval(() => {
            badge.style.transform = 'translateY(-5px)';
            setTimeout(() => {
                badge.style.transform = 'translateY(0px)';
            }, 1000);
        }, 2000);
    }
    
    // Enhanced print functionality
    const printButton = document.querySelector('button[onclick="window.print()"]');
    if (printButton) {
        printButton.addEventListener('click', function() {
            setTimeout(() => {
                // Show a message when print is done (if possible)
                console.log('Print dialog opened');
            }, 500);
        });
    }
});
</script>

<?php
require_once 'includes/footer.php';
?>