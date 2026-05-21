<?php
// my-visa-applications.php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$page_title = "My Visa Applications";
$user_id = $_SESSION['user_id'];

// Get all user visa applications
$stmt = $pdo->prepare("SELECT * FROM visa_applications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once 'includes/header.php';
?>

<div class="container">
    <h1 style="margin: 2rem 0; color: #333;">My Visa Applications</h1>
    
    <?php if (!empty($applications)): ?>
        <div style="background: white; padding: 1.5rem; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f8f9fa;">
                            <th style="padding: 1rem; text-align: left; border-bottom: 2px solid #dee2e6;">Application Number</th>
                            <th style="padding: 1rem; text-align: left; border-bottom: 2px solid #dee2e6;">Destination</th>
                            <th style="padding: 1rem; text-align: left; border-bottom: 2px solid #dee2e6;">Purpose</th>
                            <th style="padding: 1rem; text-align: left; border-bottom: 2px solid #dee2e6;">Applied Date</th>
                            <th style="padding: 1rem; text-align: left; border-bottom: 2px solid #dee2e6;">Amount</th>
                            <th style="padding: 1rem; text-align: left; border-bottom: 2px solid #dee2e6;">Status</th>
                            <th style="padding: 1rem; text-align: left; border-bottom: 2px solid #dee2e6;">Payment Status</th>
                            <th style="padding: 1rem; text-align: left; border-bottom: 2px solid #dee2e6;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($applications as $application): ?>
                            <?php
                            // Determine payment status based on application status
                            $payment_status = 'pending';
                            $payment_bg_color = '#fff3cd';
                            $payment_text_color = '#856404';
                            $payment_text = 'Pending';
                            
                            if ($application['status'] === 'pending_payment') {
                                $payment_status = 'pending';
                                $payment_bg_color = '#fff3cd';
                                $payment_text_color = '#856404';
                                $payment_text = 'Pending';
                            } else {
                                $payment_status = 'paid';
                                $payment_bg_color = '#d4edda';
                                $payment_text_color = '#155724';
                                $payment_text = 'Paid';
                            }
                            
                            // For applications that are approved, under review, etc., assume payment is completed
                            if (in_array($application['status'], ['approved', 'rejected', 'pending_review', 'under_review'])) {
                                $payment_status = 'paid';
                                $payment_bg_color = '#d4edda';
                                $payment_text_color = '#155724';
                                $payment_text = 'Paid';
                            }
                            ?>
                            <tr>
                                <td style="padding: 1rem; border-bottom: 1px solid #dee2e6; font-weight: 600;"><?php echo $application['application_number']; ?></td>
                                <td style="padding: 1rem; border-bottom: 1px solid #dee2e6;"><?php echo $application['destination_country']; ?></td>
                                <td style="padding: 1rem; border-bottom: 1px solid #dee2e6;"><?php echo ucfirst($application['purpose_of_travel']); ?></td>
                                <td style="padding: 1rem; border-bottom: 1px solid #dee2e6;"><?php echo date('M j, Y', strtotime($application['created_at'])); ?></td>
                                <td style="padding: 1rem; border-bottom: 1px solid #dee2e6;">₦<?php echo number_format($application['total_amount'], 2); ?></td>
                                <td style="padding: 1rem; border-bottom: 1px solid #dee2e6;">
                                    <span style="padding: 0.5rem 1rem; border-radius: 20px; font-size: 0.8rem; font-weight: bold; 
                                        background: <?php 
                                            switch($application['status']) {
                                                case 'approved': echo '#d4edda'; break;
                                                case 'rejected': echo '#f8d7da'; break;
                                                case 'pending_review': echo '#fff3cd'; break;
                                                case 'pending_payment': echo '#d1edff'; break;
                                                case 'under_review': echo '#e2e3e5'; break;
                                                default: echo '#e9ecef';
                                            }
                                        ?>; 
                                        color: <?php 
                                            switch($application['status']) {
                                                case 'approved': echo '#155724'; break;
                                                case 'rejected': echo '#721c24'; break;
                                                case 'pending_review': echo '#856404'; break;
                                                case 'pending_payment': echo '#004085'; break;
                                                case 'under_review': echo '#383d41'; break;
                                                default: echo '#495057';
                                            }
                                        ?>;">
                                        <?php echo ucfirst(str_replace('_', ' ', $application['status'])); ?>
                                    </span>
                                </td>
                                <td style="padding: 1rem; border-bottom: 1px solid #dee2e6;">
                                    <span style="padding: 0.5rem 1rem; border-radius: 20px; font-size: 0.8rem; font-weight: bold; 
                                        background: <?php echo $payment_bg_color; ?>; 
                                        color: <?php echo $payment_text_color; ?>;">
                                        <?php echo $payment_text; ?>
                                    </span>
                                </td>
                                <td style="padding: 1rem; border-bottom: 1px solid #dee2e6;">
                                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                        <a href="visa-application-details.php?id=<?php echo $application['id']; ?>" class="btn" style="padding: 0.5rem 1rem; background: #007bff; color: white; text-decoration: none; border-radius: 5px; font-size: 0.8rem;">View</a>
                                        <?php if ($application['status'] == 'pending_payment'): ?>
                                            <a href="visa-payment.php?application_id=<?php echo $application['id']; ?>" class="btn" style="padding: 0.5rem 1rem; background: #28a745; color: white; text-decoration: none; border-radius: 5px; font-size: 0.8rem;">Pay Now</a>
                                        <?php endif; ?>
                                        <?php if ($payment_status == 'paid'): ?>
                                            <a href="visa-receipt.php?id=<?php echo $application['id']; ?>" class="btn" style="padding: 0.5rem 1rem; background: #17a2b8; color: white; text-decoration: none; border-radius: 5px; font-size: 0.8rem;">Receipt</a>
                                        <?php endif; ?>
                                        <?php if (in_array($application['status'], ['rejected', 'cancelled'])): ?>
                                            <a href="visa-application.php?reapply=<?php echo $application['id']; ?>" class="btn" style="padding: 0.5rem 1rem; background: #6f42c1; color: white; text-decoration: none; border-radius: 5px; font-size: 0.8rem;">Reapply</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 4rem 2rem; background: white; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
            <div style="font-size: 4rem; margin-bottom: 1rem;">🛂</div>
            <h2 style="color: #333; margin-bottom: 1rem;">No Visa Applications Yet</h2>
            <p style="color: #666; margin-bottom: 2rem;">Start your visa application process today.</p>
            <a href="visa-assessment.php" class="btn btn-primary" style="padding: 1rem 2rem; font-size: 1.1rem;">Start Visa Application</a>
        </div>
    <?php endif; ?>
</div>

<?php
require_once 'includes/footer.php';
?>