<?php
// track-visa.php
require_once 'config.php';

$page_title = "Track Visa Application";

$tracking_id = '';
$application_data = null;
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tracking_id = sanitize($_POST['tracking_id'] ?? '');
    
    if (empty($tracking_id)) {
        $error = "Please enter your Tracking ID";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM visa_applications WHERE tracking_id = ?");
            $stmt->execute([$tracking_id]);
            $application_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$application_data) {
                $error = "No visa application found with the provided Tracking ID";
            } else {
                $success = "Visa application found!";
                
                // Decode JSON fields
                $application_data['personal_info'] = json_decode($application_data['personal_info'], true);
                $application_data['purpose_of_travel'] = json_decode($application_data['purpose_of_travel'], true);
                $application_data['contact_info'] = json_decode($application_data['contact_info'], true);
            }
        } catch (Exception $e) {
            $error = "Error retrieving application information: " . $e->getMessage();
        }
    }
}

require_once 'includes/header.php';
?>

<style>
.tracking-container {
    max-width: 1000px;
    margin: 2rem auto;
    padding: 0 1rem;
}

.tracking-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    margin-bottom: 2rem;
}

.card-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 1.5rem;
}

.card-body {
    padding: 1.5rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: #2d3748;
}

.form-control {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.form-control:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    padding: 0.875rem 1.5rem;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    width: 100%;
    justify-content: center;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
}

.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-awaiting {
    background: #fed7d7;
    color: #c53030;
}

.status-received {
    background: #fef5e7;
    color: #d69e2e;
}

.status-processing {
    background: #e6fffa;
    color: #319795;
}

.status-interview {
    background: #ebf8ff;
    color: #3182ce;
}

.status-approved {
    background: #f0fff4;
    color: #38a169;
}

.status-denied {
    background: #fff5f5;
    color: #e53e3e;
}

.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.info-item {
    background: #f8fafc;
    padding: 1rem;
    border-radius: 8px;
    border-left: 4px solid #667eea;
}

.info-label {
    font-weight: 600;
    color: #4a5568;
    font-size: 0.875rem;
    margin-bottom: 0.25rem;
}

.info-value {
    color: #2d3748;
    font-size: 0.9rem;
}

.alert {
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

@media (max-width: 768px) {
    .tracking-container {
        margin: 1rem auto;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .card-header {
        padding: 1.25rem;
    }
    
    .card-body {
        padding: 1.25rem;
    }
}
</style>

<div class="tracking-container">
    <div class="tracking-card">
        <div class="card-header">
            <h1 style="margin: 0; font-size: 1.5rem;">Track Visa Application</h1>
            <p style="margin: 0.5rem 0 0 0; opacity: 0.9;">Enter your Tracking ID to check application status</p>
        </div>
        
        <div class="card-body">
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="15" y1="9" x2="9" y2="15"/>
                        <line x1="9" y1="9" x2="15" y2="15"/>
                    </svg>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                        <polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="tracking_id" class="form-label">Tracking ID</label>
                    <input type="text" id="tracking_id" name="tracking_id" value="<?php echo htmlspecialchars($tracking_id); ?>" 
                           class="form-control" placeholder="Enter your Tracking ID (starts with VTRK)" required>
                </div>
                
                <button type="submit" class="btn-primary">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/>
                        <path d="m21 21-4.3-4.3"/>
                    </svg>
                    Track Application
                </button>
            </form>
        </div>
    </div>

    <?php if ($application_data): ?>
        <div class="tracking-card">
            <div class="card-header">
                <h2 style="margin: 0;">Application Status</h2>
            </div>
            
            <div class="card-body">
                <!-- Application Summary -->
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Application Number</div>
                        <div class="info-value"><?php echo $application_data['application_number']; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Tracking ID</div>
                        <div class="info-value"><?php echo $application_data['tracking_id']; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Destination Country</div>
                        <div class="info-value"><?php echo $application_data['country']; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Service Fee</div>
                        <div class="info-value">₦<?php echo number_format($application_data['service_fee'], 2); ?></div>
                    </div>
                </div>

                <!-- Status Display -->
                <div class="info-item">
                    <div class="info-label">Current Status</div>
                    <?php
                    $status_class = 'status-' . str_replace('_', '-', $application_data['status']);
                    $status_text = ucwords(str_replace('_', ' ', $application_data['status']));
                    ?>
                    <div class="status-badge <?php echo $status_class; ?>">
                        <?php echo $status_text; ?>
                    </div>
                    
                    <?php if ($application_data['status'] === 'interview_scheduled' && $application_data['interview_date']): ?>
                        <div style="margin-top: 1rem;">
                            <div class="info-label">Interview Details</div>
                            <div class="info-value">
                                <strong>Date:</strong> <?php echo date('F j, Y', strtotime($application_data['interview_date'])); ?><br>
                                <strong>Time:</strong> <?php echo date('h:i A', strtotime($application_data['interview_time'])); ?><br>
                                <strong>Location:</strong> <?php echo $application_data['interview_place']; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($application_data['admin_notes']): ?>
                        <div style="margin-top: 1rem;">
                            <div class="info-label">Admin Notes</div>
                            <div class="info-value"><?php echo nl2br(htmlspecialchars($application_data['admin_notes'])); ?></div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Applicant Information -->
                <div class="info-item">
                    <div class="info-label">Applicant Information</div>
                    <div class="info-value">
                        <strong>Name:</strong> <?php echo $application_data['personal_info']['full_name']; ?><br>
                        <strong>Email:</strong> <?php echo $application_data['contact_info']['email']; ?><br>
                        <strong>Phone:</strong> <?php echo $application_data['contact_info']['phone']; ?><br>
                        <strong>Passport:</strong> <?php echo $application_data['contact_info']['passport_number']; ?>
                    </div>
                </div>

                <!-- Payment Status -->
                <div class="info-item">
                    <div class="info-label">Payment Status</div>
                    <div class="info-value">
                        <span style="color: <?php echo $application_data['payment_status'] === 'paid' ? '#38a169' : '#e53e3e'; ?>; font-weight: 600;">
                            <?php echo ucfirst($application_data['payment_status']); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
require_once 'includes/footer.php';
?>