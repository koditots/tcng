<?php
// visa-payment-callback.php
require_once 'config.php';

$gateway = $_GET['gateway'] ?? '';
$reference = $_GET['reference'] ?? '';
$transaction_id = $_GET['transaction_id'] ?? '';

if (empty($gateway) || empty($reference)) {
    $_SESSION['error'] = "Invalid payment callback parameters.";
    header("Location: my-visa-applications.php");
    exit;
}

try {
    // Get payment record
    $stmt = $pdo->prepare("
        SELECT vap.*, va.user_id, va.application_number, va.total_amount 
        FROM visa_application_payments vap
        JOIN visa_applications va ON vap.visa_application_id = va.id
        WHERE vap.payment_reference = ?
    ");
    $stmt->execute([$reference]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        throw new Exception("Payment record not found.");
    }

    // Verify payment based on gateway
    if ($gateway == 'paystack') {
        $verification = verifyPaystackPayment($reference);
        
        if ($verification && $verification['status'] === true && $verification['data']['status'] === 'success') {
            // Payment successful
            $update_success = updateVisaApplicationPaymentStatus(
                $pdo,
                $reference,
                'completed',
                $verification['data']['id'],
                $verification
            );

            if ($update_success) {
                $_SESSION['success'] = "Payment completed successfully! Your visa application is now under review.";
                
                // Send email notification
                $user_stmt = $pdo->prepare("SELECT email, first_name FROM users WHERE id = ?");
                $user_stmt->execute([$payment['user_id']]);
                $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    $subject = "Visa Application Payment Confirmed";
                    $body = "
                        <h2>Payment Confirmed</h2>
                        <p>Dear {$user['first_name']},</p>
                        <p>Your payment for visa application <strong>{$payment['application_number']}</strong> has been confirmed.</p>
                        <p><strong>Amount:</strong> ₦" . number_format($payment['total_amount'], 2) . "</p>
                        <p><strong>Reference:</strong> {$reference}</p>
                        <p>Your application is now under review. We will notify you once there's an update.</p>
                        <p>Thank you for choosing Travel Centre!</p>
                    ";
                    sendEmail($user['email'], $subject, $body, true);
                }
                
            } else {
                throw new Exception("Failed to update payment status.");
            }
        } else {
            // Payment failed
            updateVisaApplicationPaymentStatus($pdo, $reference, 'failed');
            $_SESSION['error'] = "Payment failed or was cancelled. Please try again.";
        }

    } elseif ($gateway == 'flutterwave') {
        if (empty($transaction_id)) {
            $tx_id = $_GET['transaction_id'] ?? $_POST['transaction_id'] ?? '';
        } else {
            $tx_id = $transaction_id;
        }

        if (!empty($tx_id)) {
            $verification = verifyFlutterwavePayment($tx_id);
            
            if ($verification && $verification['status'] === 'success') {
                // Payment successful
                $update_success = updateVisaApplicationPaymentStatus(
                    $pdo,
                    $reference,
                    'completed',
                    $tx_id,
                    $verification
                );

                if ($update_success) {
                    $_SESSION['success'] = "Payment completed successfully! Your visa application is now under review.";
                    
                    // Send email notification
                    $user_stmt = $pdo->prepare("SELECT email, first_name FROM users WHERE id = ?");
                    $user_stmt->execute([$payment['user_id']]);
                    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($user) {
                        $subject = "Visa Application Payment Confirmed";
                        $body = "
                            <h2>Payment Confirmed</h2>
                            <p>Dear {$user['first_name']},</p>
                            <p>Your payment for visa application <strong>{$payment['application_number']}</strong> has been confirmed.</p>
                            <p><strong>Amount:</strong> ₦" . number_format($payment['total_amount'], 2) . "</p>
                            <p><strong>Reference:</strong> {$reference}</p>
                            <p>Your application is now under review. We will notify you once there's an update.</p>
                            <p>Thank you for choosing Travel Centre!</p>
                        ";
                        sendEmail($user['email'], $subject, $body, true);
                    }
                    
                } else {
                    throw new Exception("Failed to update payment status.");
                }
            } else {
                // Payment failed
                updateVisaApplicationPaymentStatus($pdo, $reference, 'failed');
                $_SESSION['error'] = "Payment failed or was cancelled. Please try again.";
            }
        } else {
            $_SESSION['error'] = "Transaction ID not provided.";
        }
    }

} catch (Exception $e) {
    error_log("Payment callback error: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred while processing your payment: " . $e->getMessage();
}

// Redirect to application details
header("Location: visa-application-details.php?id=" . $payment['visa_application_id']);
exit;
?>