<?php
// visa-application-details.php
require_once 'config.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

$page_title = "Visa Application Details";
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
        SELECT * FROM visa_applications 
        WHERE id = ? AND user_id = ?
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

// Function to format status with badges
function getStatusBadge($status) {
    $status_classes = [
        'pending_payment' => ['bg-warning', 'Pending Payment'],
        'pending_review' => ['bg-info', 'Under Review'],
        'approved' => ['bg-success', 'Approved'],
        'rejected' => ['bg-danger', 'Rejected'],
        'cancelled' => ['bg-secondary', 'Cancelled']
    ];
    
    $class = $status_classes[$status][0] ?? 'bg-secondary';
    $text = $status_classes[$status][1] ?? ucfirst(str_replace('_', ' ', $status));
    
    return "<span class='badge $class'>$text</span>";
}

// Function to format file links
function getFileLink($filename, $folder = 'visa_applications') {
    if (empty($filename)) {
        return '<span class="text-muted">Not uploaded</span>';
    }
    
    $file_path = "uploads/$folder/" . $filename;
    if (file_exists($file_path)) {
        return "<a href='$file_path' target='_blank' class='file-download-btn'><i class='fas fa-download'></i> Download File</a>";
    }
    
    return '<span class="text-muted">File not found</span>';
}

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
    --gradient-warning: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
    --gradient-danger: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    --shadow-sm: 0 2px 4px rgba(0,0,0,0.1);
    --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
    --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
    --shadow-xl: 0 20px 50px rgba(0,0,0,0.15);
    --border-radius: 12px;
    --border-radius-lg: 20px;
}

.application-container {
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    min-height: 100vh;
    padding: 30px 20px;
}

.application-card {
    background: white;
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow-xl);
    overflow: hidden;
    margin: 0 auto;
    max-width: 1400px;
}

.application-header {
    background: var(--gradient-primary);
    color: white;
    padding: 40px;
    position: relative;
    overflow: hidden;
}

.application-header::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%);
}

.header-content {
    position: relative;
    z-index: 2;
}

.application-title {
    font-size: 2.5em;
    font-weight: 700;
    margin-bottom: 5px;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
}

.application-subtitle {
    font-size: 1.2em;
    opacity: 0.9;
    margin-bottom: 0;
}

.status-badge-modern {
    padding: 10px 20px;
    border-radius: 25px;
    font-weight: 600;
    font-size: 0.9em;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    backdrop-filter: blur(10px);
    background: rgba(255,255,255,0.2);
    margin-bottom: 15px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-top: 30px;
}

.stat-card {
    background: rgba(255,255,255,0.1);
    backdrop-filter: blur(10px);
    border-radius: var(--border-radius);
    padding: 20px;
    text-align: center;
    border: 1px solid rgba(255,255,255,0.2);
    transition: transform 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-value {
    font-size: 2em;
    font-weight: 700;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 0.9em;
    opacity: 0.9;
}

.application-body {
    padding: 40px;
}

.section-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    margin-bottom: 40px;
}

@media (max-width: 768px) {
    .section-grid {
        grid-template-columns: 1fr;
    }
}

.section-card {
    background: white;
    border-radius: var(--border-radius);
    padding: 0;
    box-shadow: var(--shadow-md);
    border: 1px solid rgba(0,0,0,0.05);
    transition: all 0.3s ease;
    overflow: hidden;
}

.section-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
}

.section-header {
    background: var(--gradient-primary);
    color: white;
    padding: 20px 25px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.section-title {
    font-size: 1.3em;
    font-weight: 600;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.section-body {
    padding: 25px;
}

.info-row {
    display: flex;
    justify-content: space-between;
    padding: 12px 0;
    border-bottom: 1px solid #f0f0f0;
    transition: background-color 0.2s ease;
}

.info-row:hover {
    background-color: #f8f9fa;
    border-radius: 5px;
    padding-left: 10px;
    padding-right: 10px;
}

.info-row:last-child {
    border-bottom: none;
}

.info-label {
    font-weight: 600;
    color: var(--dark);
    flex: 1;
}

.info-value {
    flex: 2;
    color: var(--secondary);
    text-align: right;
}

.file-download-btn {
    background: var(--gradient-primary);
    color: white;
    padding: 8px 15px;
    border-radius: 20px;
    text-decoration: none;
    font-size: 0.85em;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.3s ease;
}

.file-download-btn:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
    color: white;
}

.timeline-modern {
    margin: 40px 0;
    padding: 0 20px;
}

.timeline-steps {
    display: flex;
    justify-content: space-between;
    position: relative;
    margin: 40px 0;
}

.timeline-steps::before {
    content: '';
    position: absolute;
    top: 25px;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--success) 0%, #e9ecef 100%);
    z-index: 1;
    border-radius: 2px;
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
    border: 4px solid #e9ecef;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 15px;
    font-size: 1.2em;
    transition: all 0.3s ease;
    position: relative;
}

.timeline-step.active .timeline-icon {
    background: var(--success);
    border-color: var(--success);
    color: white;
    transform: scale(1.1);
    box-shadow: 0 0 0 8px rgba(40, 167, 69, 0.2);
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

.timeline-step.rejected .timeline-icon {
    background: var(--danger);
    border-color: var(--danger);
    color: white;
}

.timeline-label {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 5px;
    font-size: 0.95em;
}

.timeline-date {
    font-size: 0.85em;
    color: var(--secondary);
}

.timeline-description {
    font-size: 0.9em;
    color: var(--secondary);
    margin-top: 5px;
    line-height: 1.4;
}

.action-buttons {
    display: flex;
    gap: 15px;
    justify-content: center;
    flex-wrap: wrap;
    margin-top: 40px;
    padding: 30px;
    background: var(--light);
    border-radius: var(--border-radius);
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
    font-size: 1em;
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

.btn-success-modern {
    background: var(--gradient-success);
    color: white;
    box-shadow: var(--shadow-md);
}

.btn-success-modern:hover {
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

/* Animation classes */
.fade-in {
    animation: fadeIn 0.6s ease-in;
}

.slide-up {
    animation: slideUp 0.6s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideUp {
    from { 
        opacity: 0;
        transform: translateY(30px);
    }
    to { 
        opacity: 1;
        transform: translateY(0);
    }
}

/* Print Styles */
@media print {
    .application-container {
        background: white !important;
        padding: 0 !important;
    }
    
    .application-card {
        box-shadow: none !important;
        margin: 0 !important;
    }
    
    .action-buttons, .no-print {
        display: none !important;
    }
    
    .application-header {
        background: #2c5aa0 !important;
        -webkit-print-color-adjust: exact;
    }
}

/* Responsive Design */
@media (max-width: 768px) {
    .application-container {
        padding: 15px 10px;
    }
    
    .application-body {
        padding: 20px;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .timeline-steps {
        flex-direction: column;
        gap: 30px;
    }
    
    .timeline-steps::before {
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
    
    .info-row {
        flex-direction: column;
        gap: 5px;
    }
    
    .info-value {
        text-align: left;
    }
}
</style>

<div class="application-container">
    <div class="application-card fade-in">
        <!-- Header Section -->
        <div class="application-header">
            <div class="header-content">
                <div class="d-flex justify-content-between align-items-start flex-wrap">
                    <div>
                        <div class="status-badge-modern">
                            <i class="fas fa-passport"></i>
                            Visa Application Details
                        </div>
                        <h1 class="application-title">Application #<?php echo $application['application_number']; ?></h1>
                        <p class="application-subtitle">Complete application information and status</p>
                    </div>
                    <div class="text-end">
                        <a href="my-visa-applications.php" class="btn-modern btn-outline-modern no-print">
                            <i class="fas fa-arrow-left"></i>Back to Applications
                        </a>
                    </div>
                </div>

                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $application['readiness_score']; ?>%</div>
                        <div class="stat-label">Readiness Score</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">₦<?php echo number_format($application['total_amount'], 2); ?></div>
                        <div class="stat-label">Application Fee</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $application['planned_stay_days']; ?> days</div>
                        <div class="stat-label">Planned Stay</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">
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
                            <span style="color: <?php echo $status_color; ?>; font-weight: 700;">
                                <?php echo ucfirst(str_replace('_', ' ', $application['status'])); ?>
                            </span>
                        </div>
                        <div class="stat-label">Current Status</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Body Section -->
        <div class="application-body">
            <!-- Modern Timeline -->
            <div class="timeline-modern">
                <h3 class="text-center mb-4"><i class="fas fa-road me-2"></i>Application Journey</h3>
                <div class="timeline-steps">
                    <?php
                    $timeline_steps = [
                        [
                            'icon' => 'fa-file-alt',
                            'label' => 'Application Submitted',
                            'date' => date('M j, Y g:i A', strtotime($application['created_at'])),
                            'status' => 'completed',
                            'description' => 'Your application has been successfully submitted'
                        ],
                        [
                            'icon' => 'fa-credit-card',
                            'label' => 'Payment',
                            'date' => $application['status'] === 'pending_payment' ? 'Pending' : 'Completed',
                            'status' => $application['status'] === 'pending_payment' ? 'active' : 'completed',
                            'description' => $application['status'] === 'pending_payment' ? 'Awaiting payment confirmation' : 'Payment successfully processed'
                        ],
                        [
                            'icon' => 'fa-cogs',
                            'label' => 'Processing',
                            'date' => 'In Progress',
                            'status' => in_array($application['status'], ['approved', 'rejected']) ? 'completed' : 
                                      ($application['status'] === 'pending_payment' ? 'pending' : 'active'),
                            'description' => 'Your application is being reviewed'
                        ],
                        [
                            'icon' => 'fa-flag-checkered',
                            'label' => 'Final Decision',
                            'date' => $application['status'] == 'approved' ? 'Approved' : 
                                     ($application['status'] == 'rejected' ? 'Rejected' : 'Pending'),
                            'status' => $application['status'] == 'approved' ? 'completed' : 
                                      ($application['status'] == 'rejected' ? 'rejected' : 'pending'),
                            'description' => $application['status'] == 'approved' ? 'Congratulations! Visa approved' : 
                                           ($application['status'] == 'rejected' ? 'Application was not approved' : 'Decision pending')
                        ]
                    ];
                    
                    foreach ($timeline_steps as $step): 
                        $status_class = $step['status'];
                    ?>
                    <div class="timeline-step <?php echo $status_class; ?> slide-up">
                        <div class="timeline-icon">
                            <i class="fas <?php echo $step['icon']; ?>"></i>
                        </div>
                        <div class="timeline-label"><?php echo $step['label']; ?></div>
                        <div class="timeline-date"><?php echo $step['date']; ?></div>
                        <div class="timeline-description"><?php echo $step['description']; ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Application Details Grid -->
            <div class="section-grid">
                <!-- Left Column -->
                <div class="section-column">
                    <!-- Personal Information -->
                    <div class="section-card slide-up">
                        <div class="section-header">
                            <h3 class="section-title"><i class="fas fa-user"></i>Personal Information</h3>
                        </div>
                        <div class="section-body">
                            <div class="info-row">
                                <span class="info-label">Full Name:</span>
                                <span class="info-value"><?php echo htmlspecialchars($application['first_name'] . ' ' . ($application['middle_name'] ? $application['middle_name'] . ' ' : '') . $application['last_name']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Date of Birth:</span>
                                <span class="info-value"><?php echo date('F j, Y', strtotime($application['date_of_birth'])); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Gender:</span>
                                <span class="info-value"><?php echo ucfirst($application['gender']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Email:</span>
                                <span class="info-value"><?php echo htmlspecialchars($application['email']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Phone:</span>
                                <span class="info-value"><?php echo htmlspecialchars($application['phone']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Address:</span>
                                <span class="info-value"><?php echo nl2br(htmlspecialchars($application['residential_address'])); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Nationality & Residence -->
                    <div class="section-card slide-up">
                        <div class="section-header">
                            <h3 class="section-title"><i class="fas fa-globe"></i>Nationality & Residence</h3>
                        </div>
                        <div class="section-body">
                            <div class="info-row">
                                <span class="info-label">Nationality:</span>
                                <span class="info-value"><?php echo htmlspecialchars($application['nationality']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Country of Residence:</span>
                                <span class="info-value"><?php echo htmlspecialchars($application['country_of_residence']); ?></span>
                            </div>
                            <?php if (!empty($application['state_province'])): ?>
                            <div class="info-row">
                                <span class="info-label">State/Province:</span>
                                <span class="info-value"><?php echo htmlspecialchars($application['state_province']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Travel Information -->
                    <div class="section-card slide-up">
                        <div class="section-header">
                            <h3 class="section-title"><i class="fas fa-plane"></i>Travel Information</h3>
                        </div>
                        <div class="section-body">
                            <div class="info-row">
                                <span class="info-label">Destination:</span>
                                <span class="info-value"><?php echo htmlspecialchars($application['destination_country']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Purpose:</span>
                                <span class="info-value"><?php echo ucfirst(str_replace('_', ' ', $application['purpose_of_travel'])); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Travel Month:</span>
                                <span class="info-value"><?php echo date('F Y', strtotime($application['intended_travel_month'] . '-01')); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Planned Stay:</span>
                                <span class="info-value"><?php echo $application['planned_stay_days']; ?> days</span>
                            </div>
                            <?php if (!empty($application['intended_arrival_date'])): ?>
                            <div class="info-row">
                                <span class="info-label">Arrival Date:</span>
                                <span class="info-value"><?php echo date('F j, Y', strtotime($application['intended_arrival_date'])); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($application['intended_return_date'])): ?>
                            <div class="info-row">
                                <span class="info-label">Return Date:</span>
                                <span class="info-value"><?php echo date('F j, Y', strtotime($application['intended_return_date'])); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="info-row">
                                <span class="info-label">Accommodation:</span>
                                <span class="info-value"><?php echo nl2br(htmlspecialchars($application['accommodation_details'])); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="section-column">
                    <!-- Passport Information -->
                    <div class="section-card slide-up">
                        <div class="section-header">
                            <h3 class="section-title"><i class="fas fa-passport"></i>Passport Information</h3>
                        </div>
                        <div class="section-body">
                            <div class="info-row">
                                <span class="info-label">Passport Number:</span>
                                <span class="info-value"><?php echo htmlspecialchars($application['passport_number']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Issue Date:</span>
                                <span class="info-value"><?php echo date('F j, Y', strtotime($application['passport_issue_date'])); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Expiry Date:</span>
                                <span class="info-value"><?php echo date('F j, Y', strtotime($application['passport_expiry_date'])); ?></span>
                            </div>
                            <?php if (!empty($application['passport_place_issue'])): ?>
                            <div class="info-row">
                                <span class="info-label">Place of Issue:</span>
                                <span class="info-value"><?php echo htmlspecialchars($application['passport_place_issue']); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($application['previous_passport_number'])): ?>
                            <div class="info-row">
                                <span class="info-label">Previous Passport:</span>
                                <span class="info-value"><?php echo htmlspecialchars($application['previous_passport_number']); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="info-row">
                                <span class="info-label">Passport Copy:</span>
                                <span class="info-value"><?php echo getFileLink($application['passport_file']); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Employment & Financial Information -->
                    <div class="section-card slide-up">
                        <div class="section-header">
                            <h3 class="section-title"><i class="fas fa-briefcase"></i>Employment & Financial</h3>
                        </div>
                        <div class="section-body">
                            <div class="info-row">
                                <span class="info-label">Employment Status:</span>
                                <span class="info-value"><?php echo ucfirst(str_replace('_', ' ', $application['current_status'])); ?></span>
                            </div>
                            
                            <?php if (!empty($application['employer_school_name'])): ?>
                            <div class="info-row">
                                <span class="info-label">Employer/School:</span>
                                <span class="info-value"><?php echo htmlspecialchars($application['employer_school_name']); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($application['position_course'])): ?>
                            <div class="info-row">
                                <span class="info-label">Position/Course:</span>
                                <span class="info-value"><?php echo htmlspecialchars($application['position_course']); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <div class="info-row">
                                <span class="info-label">Monthly Income:</span>
                                <span class="info-value"><?php echo $application['income_currency'] . ' ' . number_format($application['monthly_income'], 2); ?></span>
                            </div>
                            
                            <div class="info-row">
                                <span class="info-label">Funding Source:</span>
                                <span class="info-value"><?php echo ucfirst(str_replace('_', ' ', $application['funding_source'])); ?></span>
                            </div>
                            
                            <?php if ($application['funding_source'] === 'sponsor'): ?>
                                <?php if (!empty($application['sponsor_name'])): ?>
                                <div class="info-row">
                                    <span class="info-label">Sponsor Name:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($application['sponsor_name']); ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($application['sponsor_relationship'])): ?>
                                <div class="info-row">
                                    <span class="info-label">Relationship:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($application['sponsor_relationship']); ?></span>
                                </div>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <div class="info-row">
                                <span class="info-label">Documents:</span>
                                <span class="info-value">
                                    <div class="d-flex flex-column gap-2">
                                        <div>
                                            <small>Employment Letter:</small><br>
                                            <?php echo getFileLink($application['employment_letter_file']); ?>
                                        </div>
                                        <?php if ($application['funding_source'] === 'sponsor'): ?>
                                        <div>
                                            <small>Sponsor Letter:</small><br>
                                            <?php echo getFileLink($application['sponsor_letter_file']); ?>
                                        </div>
                                        <?php endif; ?>
                                        <div>
                                            <small>Bank Statements:</small><br>
                                            <?php echo getFileLink($application['bank_statements_file']); ?>
                                        </div>
                                    </div>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Information -->
                    <div class="section-card slide-up">
                        <div class="section-header">
                            <h3 class="section-title"><i class="fas fa-info-circle"></i>Additional Information</h3>
                        </div>
                        <div class="section-body">
                            <div class="info-row">
                                <span class="info-label">Traveling with Companions:</span>
                                <span class="info-value"><?php echo ucfirst($application['traveling_with_companions']); ?></span>
                            </div>
                            
                            <?php if (!empty($application['companions_details'])): ?>
                            <div class="info-row">
                                <span class="info-label">Companions Details:</span>
                                <span class="info-value"><?php echo nl2br(htmlspecialchars($application['companions_details'])); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <div class="info-row">
                                <span class="info-label">Family in Destination:</span>
                                <span class="info-value"><?php echo ucfirst($application['family_in_destination']); ?></span>
                            </div>
                            
                            <?php if (!empty($application['family_details'])): ?>
                            <div class="info-row">
                                <span class="info-label">Family Details:</span>
                                <span class="info-value"><?php echo nl2br(htmlspecialchars($application['family_details'])); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($application['previous_trips'])): ?>
                            <div class="info-row">
                                <span class="info-label">Previous Trips:</span>
                                <span class="info-value"><?php echo nl2br(htmlspecialchars($application['previous_trips'])); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <div class="info-row">
                                <span class="info-label">Visa Refusals:</span>
                                <span class="info-value"><?php echo ucfirst($application['visa_refusals']); ?></span>
                            </div>
                            
                            <?php if (!empty($application['refusal_details'])): ?>
                            <div class="info-row">
                                <span class="info-label">Refusal Details:</span>
                                <span class="info-value"><?php echo nl2br(htmlspecialchars($application['refusal_details'])); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Emergency Contact -->
                    <div class="section-card slide-up">
                        <div class="section-header">
                            <h3 class="section-title"><i class="fas fa-phone-alt"></i>Emergency Contact</h3>
                        </div>
                        <div class="section-body">
                            <div class="info-row">
                                <span class="info-label">Contact Name:</span>
                                <span class="info-value"><?php echo htmlspecialchars($application['emergency_contact_name']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Relationship:</span>
                                <span class="info-value"><?php echo htmlspecialchars($application['emergency_contact_relationship']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Phone:</span>
                                <span class="info-value"><?php echo htmlspecialchars($application['emergency_contact_phone']); ?></span>
                            </div>
                            <?php if (!empty($application['emergency_contact_email'])): ?>
                            <div class="info-row">
                                <span class="info-label">Email:</span>
                                <span class="info-value"><?php echo htmlspecialchars($application['emergency_contact_email']); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($application['emergency_contact_location'])): ?>
                            <div class="info-row">
                                <span class="info-label">Location:</span>
                                <span class="info-value"><?php echo htmlspecialchars($application['emergency_contact_location']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons no-print">
                <a href="my-visa-applications.php" class="btn-modern btn-outline-modern">
                    <i class="fas fa-arrow-left"></i>Back to Applications
                </a>
                <?php if ($application['status'] === 'pending_payment'): ?>
                <a href="visa-payment.php?application_id=<?php echo $application['id']; ?>" 
                   class="btn-modern btn-success-modern">
                    <i class="fas fa-credit-card"></i>Make Payment
                </a>
                <?php endif; ?>
                <?php if (in_array($application['status'], ['rejected', 'cancelled'])): ?>
                <a href="visa-application.php?reapply=<?php echo $application['id']; ?>" 
                   class="btn-modern btn-primary-modern">
                    <i class="fas fa-redo"></i>Reapply
                </a>
                <?php endif; ?>
                <button onclick="window.print()" class="btn-modern btn-outline-modern">
                    <i class="fas fa-print"></i>Print Application
                </button>
                <a href="visa-receipt.php?id=<?php echo $application['id']; ?>" class="btn-modern btn-primary-modern">
                    <i class="fas fa-receipt"></i>View Receipt
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Font Awesome for icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<script>
document.addEventListener('DOMContentLoaded', function() {
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

    // Observe all section cards for animation
    document.querySelectorAll('.section-card').forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(card);
    });

    // Add hover effects to info rows
    const infoRows = document.querySelectorAll('.info-row');
    infoRows.forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.backgroundColor = '#f8f9fa';
            this.style.paddingLeft = '15px';
            this.style.paddingRight = '15px';
        });
        
        row.addEventListener('mouseleave', function() {
            this.style.backgroundColor = '';
            this.style.paddingLeft = '';
            this.style.paddingRight = '';
        });
    });

    // Add loading animation
    const timelineSteps = document.querySelectorAll('.timeline-step');
    timelineSteps.forEach((step, index) => {
        setTimeout(() => {
            step.style.opacity = '1';
            step.style.transform = 'scale(1)';
        }, index * 300);
    });
});
</script>

<?php
require_once 'includes/footer.php';
?>