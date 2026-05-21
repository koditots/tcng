<?php
// admin/settings.php
require_once '../config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

$page_title = "Site Settings";

$settings = getSiteSettings($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['test_smtp'])) {
        header('Content-Type: application/json');
        
        $test_email = $_POST['test_email'] ?? '';
        
        $temp_settings = [
            'smtp_host' => $_POST['smtp_host'] ?? '',
            'smtp_port' => $_POST['smtp_port'] ?? '',
            'smtp_username' => $_POST['smtp_username'] ?? '',
            'smtp_password' => $_POST['smtp_password'] ?? '',
            'smtp_encryption' => $_POST['smtp_encryption'] ?? 'tls',
            'smtp_from_email' => $_POST['smtp_from_email'] ?? '',
            'smtp_from_name' => $_POST['smtp_from_name'] ?? '',
        ];
        
        require_once '../includes/PHPMailer/src/PHPMailer.php';
        require_once '../includes/PHPMailer/src/SMTP.php';
        require_once '../includes/PHPMailer/src/Exception.php';
        
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $temp_settings['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $temp_settings['smtp_username'];
            $mail->Password = $temp_settings['smtp_password'];
            $mail->SMTPSecure = $temp_settings['smtp_encryption'];
            $mail->Port = intval($temp_settings['smtp_port']);
            $mail->CharSet = 'UTF-8';
            
            $mail->setFrom($temp_settings['smtp_from_email'], $temp_settings['smtp_from_name']);
            $mail->addAddress($test_email);
            $mail->isHTML(true);
            $mail->Subject = 'SMTP Test - ' . ($settings['site_name'] ?? 'Travel Centre');
            $mail->Body = '<h2>SMTP Test Successful!</h2><p>This is a test email to verify your email configuration works correctly.</p><p><strong>Time:</strong> ' . date('Y-m-d H:i:s') . '</p>';
            
            if ($mail->send()) {
                echo json_encode(['success' => true, 'message' => 'Test email sent successfully to ' . $test_email]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Mailer Error: ' . $mail->ErrorInfo]);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'SMTP Error: ' . $e->getMessage()]);
        }
        exit;
    }
    
    if (isset($_POST['remove_logo'])) {
        try {
            $current_logo = $settings['logo'] ?? '';
            if ($current_logo && file_exists('../' . $current_logo)) {
                unlink('../' . $current_logo);
            }
            
            $columns = array_keys($settings);
            $settings['logo'] = '';
            
            $placeholders = ':' . implode(', :', $columns);
            $update_cols = [];
            foreach ($columns as $col) {
                $update_cols[] = "$col = VALUES($col)";
            }
            
            $stmt = $pdo->prepare("INSERT INTO site_settings (" . implode(', ', $columns) . ") VALUES ($placeholders)");
            $stmt->execute($settings);
            
            $success = "Logo removed successfully!";
            $settings = getSiteSettings($pdo);
        } catch (Exception $e) {
            $error = "Error removing logo: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['remove_favicon'])) {
        try {
            $current_favicon = $settings['favicon'] ?? '';
            if ($current_favicon && file_exists('../' . $current_favicon)) {
                unlink('../' . $current_favicon);
            }
            
            $columns = array_keys($settings);
            $settings['favicon'] = '';
            
            $placeholders = ':' . implode(', :', $columns);
            
            $stmt = $pdo->prepare("INSERT INTO site_settings (" . implode(', ', $columns) . ") VALUES ($placeholders)");
            $stmt->execute($settings);
            
            $success = "Favicon removed successfully!";
            $settings = getSiteSettings($pdo);
        } catch (Exception $e) {
            $error = "Error removing favicon: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['remove_offer_image'])) {
        try {
            $current_offer = $settings['offer_image'] ?? '';
            if ($current_offer && file_exists('../' . $current_offer)) {
                unlink('../' . $current_offer);
            }
            
            $columns = array_keys($settings);
            $settings['offer_image'] = '';
            
            $placeholders = ':' . implode(', :', $columns);
            
            $stmt = $pdo->prepare("INSERT INTO site_settings (" . implode(', ', $columns) . ") VALUES ($placeholders)");
            $stmt->execute($settings);
            
            $success = "Offer image removed successfully!";
            $settings = getSiteSettings($pdo);
        } catch (Exception $e) {
            $error = "Error removing offer image: " . $e->getMessage();
        }
    }
    
    if (!isset($_POST['remove_logo']) && !isset($_POST['remove_favicon']) && !isset($_POST['remove_offer_image'])) {
        try {
            $new_settings = $settings;
            
            $new_settings['guest_booking_enabled'] = isset($_POST['guest_booking_enabled']) ? '1' : '0';
            $new_settings['allow_guest_registration'] = isset($_POST['allow_guest_registration']) ? '1' : '0';
            $new_settings['notify_admin_guest_bookings'] = isset($_POST['notify_admin_guest_bookings']) ? '1' : '0';
            $new_settings['notify_user_guest_bookings'] = isset($_POST['notify_user_guest_bookings']) ? '1' : '0';
            $new_settings['ticket_tracking_enabled'] = isset($_POST['ticket_tracking_enabled']) ? '1' : '0';
            $new_settings['tracking_page_url'] = $_POST['tracking_page_url'] ?? 'track-ticket.php';
            
            $new_settings['visa_assessment_booking_fee_low'] = $_POST['visa_assessment_booking_fee_low'] ?? 5000;
            $new_settings['visa_assessment_booking_link_low'] = $_POST['visa_assessment_booking_link_low'] ?? '';
            $new_settings['visa_assessment_booking_fee_medium'] = $_POST['visa_assessment_booking_fee_medium'] ?? 3000;
            $new_settings['visa_assessment_booking_link_medium'] = $_POST['visa_assessment_booking_link_medium'] ?? '';
            $new_settings['visa_assessment_booking_fee_high'] = $_POST['visa_assessment_booking_fee_high'] ?? 0;
            $new_settings['visa_assessment_booking_link_high'] = $_POST['visa_assessment_booking_link_high'] ?? '';
            
            $new_settings['tax_enabled'] = isset($_POST['tax_enabled']) ? '1' : '0';
            $new_settings['tax_name'] = $_POST['tax_name'] ?? 'Tax';
            $new_settings['tax_type'] = $_POST['tax_type'] ?? 'percentage';
            $new_settings['tax_value'] = $_POST['tax_value'] ?? 0;
            
            $new_settings['service_fee_enabled'] = isset($_POST['service_fee_enabled']) ? '1' : '0';
            $new_settings['service_fee_name'] = $_POST['service_fee_name'] ?? 'Service Fee';
            $new_settings['service_fee_type'] = $_POST['service_fee_type'] ?? 'percentage';
            $new_settings['service_fee_value'] = $_POST['service_fee_value'] ?? 0;
            
            $new_settings['offer_enabled'] = isset($_POST['offer_enabled']) ? '1' : '0';
            $new_settings['offer_title'] = $_POST['offer_title'] ?? '';
            $new_settings['offer_discount'] = $_POST['offer_discount'] ?? '';
            $new_settings['offer_description'] = $_POST['offer_description'] ?? '';
            $new_settings['offer_valid_until'] = $_POST['offer_valid_until'] ?? '';
            
            $new_settings['site_name'] = $_POST['site_name'] ?? '';
            $new_settings['site_title'] = $_POST['site_title'] ?? '';
            $new_settings['site_description'] = $_POST['site_description'] ?? '';
            $new_settings['site_keywords'] = $_POST['site_keywords'] ?? '';
            $new_settings['admin_email'] = $_POST['admin_email'] ?? '';
            $new_settings['support_email'] = $_POST['support_email'] ?? '';
            $new_settings['phone'] = $_POST['phone'] ?? '';
            $new_settings['currency'] = $_POST['currency'] ?? 'NGN';
            $new_settings['timezone'] = $_POST['timezone'] ?? 'Africa/Lagos';
            $new_settings['currency_rate'] = $_POST['currency_rate'] ?? '450';
            $new_settings['address'] = $_POST['address'] ?? '';
            
            $new_settings['smtp_host'] = $_POST['smtp_host'] ?? '';
            $new_settings['smtp_port'] = $_POST['smtp_port'] ?? '587';
            $new_settings['smtp_username'] = $_POST['smtp_username'] ?? '';
            $new_settings['smtp_password'] = $_POST['smtp_password'] ?? '';
            $new_settings['smtp_encryption'] = $_POST['smtp_encryption'] ?? 'tls';
            $new_settings['smtp_from_email'] = $_POST['smtp_from_email'] ?? '';
            $new_settings['smtp_from_name'] = $_POST['smtp_from_name'] ?? '';
            
            $new_settings['amadeus_api_key'] = $_POST['amadeus_api_key'] ?? '';
            $new_settings['amadeus_api_secret'] = $_POST['amadeus_api_secret'] ?? '';
            $new_settings['paystack_public_key'] = $_POST['paystack_public_key'] ?? '';
            $new_settings['paystack_secret_key'] = $_POST['paystack_secret_key'] ?? '';
            $new_settings['flutterwave_public_key'] = $_POST['flutterwave_public_key'] ?? '';
            $new_settings['flutterwave_secret_key'] = $_POST['flutterwave_secret_key'] ?? '';
            
            $upload_dir = '../uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $current_logo = $settings['logo'] ?? '';
                if ($current_logo && file_exists('../' . $current_logo)) {
                    unlink('../' . $current_logo);
                }
                
                $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
                $new_filename = 'uploads/logo_' . time() . '.' . strtolower($ext);
                if (move_uploaded_file($_FILES['logo']['tmp_name'], '../' . $new_filename)) {
                    $new_settings['logo'] = $new_filename;
                }
            }
            
            if (isset($_FILES['favicon']) && $_FILES['favicon']['error'] === UPLOAD_ERR_OK) {
                $current_favicon = $settings['favicon'] ?? '';
                if ($current_favicon && file_exists('../' . $current_favicon)) {
                    unlink('../' . $current_favicon);
                }
                
                $ext = pathinfo($_FILES['favicon']['name'], PATHINFO_EXTENSION);
                $new_filename = 'uploads/favicon_' . time() . '.' . strtolower($ext);
                if (move_uploaded_file($_FILES['favicon']['tmp_name'], '../' . $new_filename)) {
                    $new_settings['favicon'] = $new_filename;
                }
            }
            
            if (isset($_FILES['offer_image']) && $_FILES['offer_image']['error'] === UPLOAD_ERR_OK) {
                $current_offer = $settings['offer_image'] ?? '';
                if ($current_offer && file_exists('../' . $current_offer)) {
                    unlink('../' . $current_offer);
                }
                
                $ext = pathinfo($_FILES['offer_image']['name'], PATHINFO_EXTENSION);
                $new_filename = 'uploads/offer_' . time() . '.' . strtolower($ext);
                if (move_uploaded_file($_FILES['offer_image']['tmp_name'], '../' . $new_filename)) {
                    $new_settings['offer_image'] = $new_filename;
                }
            }
            
            $all_columns = [];
            $stmt = $pdo->query("DESCRIBE site_settings");
            $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($cols as $col) {
                if ($col !== 'id' && $col !== 'created_at') {
                    $all_columns[] = $col;
                }
            }
            
            $final_settings = [];
            foreach ($all_columns as $col) {
                if (isset($new_settings[$col])) {
                    $final_settings[$col] = $new_settings[$col];
                } elseif (isset($settings[$col])) {
                    $final_settings[$col] = $settings[$col];
                } else {
                    $final_settings[$col] = '';
                }
            }
            
            $placeholders = ':' . implode(', :', array_keys($final_settings));
            
            $sql = "INSERT INTO site_settings (" . implode(', ', array_keys($final_settings)) . ") VALUES ($placeholders)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($final_settings);
            
            $success = "Settings saved successfully!";
            $settings = getSiteSettings($pdo);
            
        } catch (Exception $e) {
            $error = "Error saving settings: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo getSiteSetting($pdo, 'site_name') ?: 'Admin Panel'; ?></title>
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
            display: flex;
            font-size: 0.875rem;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 0;
            min-height: 100vh;
        }
        
        .top-bar {
            background: white;
            padding: 0.75rem 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .top-bar h1 {
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .content {
            padding: 1.5rem;
        }
        
        /* Cards */
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        
        .card-header {
            padding: 1rem 1.25rem;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-header h3 {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .card-body {
            padding: 1.25rem;
        }
        
        /* Forms */
        .form-group {
            margin-bottom: 0.875rem;
            position: relative;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.375rem;
            font-weight: 600;
            color: #333;
            font-size: 0.8rem;
        }
        
        .form-control {
            width: 100%;
            padding: 0.625rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.8rem;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 0.875rem;
        }
        
        /* Buttons */
        .btn {
            padding: 0.5rem 0.75rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.375rem;
            font-size: 0.8rem;
            transition: all 0.3s;
            font-weight: 500;
            line-height: 1.2;
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
        
        .btn-warning {
            background: #ffc107;
            color: #333;
        }
        
        .btn-warning:hover {
            background: #e0a800;
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
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #545b62;
            transform: translateY(-1px);
        }
        
        .btn-sm {
            padding: 0.375rem 0.625rem;
            font-size: 0.75rem;
        }
        
        .btn-xs {
            padding: 0.25rem 0.5rem;
            font-size: 0.7rem;
            gap: 0.25rem;
        }
        
        /* Action buttons */
        .action-buttons {
            display: flex;
            gap: 0.25rem;
            flex-wrap: nowrap;
        }
        
        /* Alerts */
        .alert {
            padding: 0.875rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            font-size: 0.8rem;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Special Sections */
        .api-section {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            border-left: 4px solid #007bff;
        }
        
        .currency-info { 
            background: #e7f3ff; 
            padding: 0.875rem;
            border-radius: 4px; 
            margin-top: 0.5rem; 
            border-left: 4px solid #007bff;
            font-size: 0.8rem;
        }
        
        .file-upload { 
            background: #f8f9fa; 
            padding: 1rem;
            border-radius: 6px; 
            border: 2px dashed #dee2e6;
            margin-bottom: 0.875rem;
        }
        
        .file-upload:hover {
            border-color: #007bff;
        }
        
        .file-preview { 
            display: flex; 
            align-items: center; 
            gap: 0.75rem; 
            margin-top: 0.75rem;
            padding: 0.875rem;
            background: white;
            border-radius: 4px;
            border: 1px solid #dee2e6;
            font-size: 0.8rem;
        }
        
        .file-preview img { 
            max-width: 80px; 
            max-height: 50px; 
            border-radius: 4px;
        }
        
        .file-info {
            flex: 1;
        }
        
        .file-actions {
            display: flex;
            gap: 0.375rem;
        }
        
        .test-email-btn {
            margin-top: 0.75rem;
        }
        
        .smtp-info { 
            background: #fff3cd; 
            padding: 0.875rem;
            border-radius: 4px; 
            margin-top: 0.75rem;
            border-left: 4px solid #ffc107;
            font-size: 0.8rem;
        }
        
        .loading {
            display: none;
        }
        
        .test-result {
            margin-top: 0.75rem;
            padding: 0.875rem;
            border-radius: 4px;
            font-size: 0.8rem;
        }
        
        .test-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .test-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Special Offer Section */
        .offer-section { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.25rem;
        }
        
        .offer-section h4 {
            color: white;
            margin-bottom: 0.875rem;
            font-size: 1rem;
        }
        
        .offer-section .form-group label {
            color: white;
            font-size: 0.8rem;
        }
        
        .offer-section .form-control {
            background: rgba(255,255,255,0.9);
            font-size: 0.8rem;
        }
        
        .offer-preview { 
            background: rgba(255,255,255,0.1); 
            padding: 1rem;
            border-radius: 6px; 
            margin-top: 0.75rem;
            border: 2px dashed rgba(255,255,255,0.3);
            font-size: 0.8rem;
        }
        
        .offer-preview img { 
            max-width: 150px; 
            max-height: 90px; 
            border-radius: 6px;
            margin-bottom: 0.75rem;
        }
        
        /* Checkbox Group */
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
        }
        
        /* Tax and Fee Styles */
        .fee-section { 
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.25rem;
        }
        
        .fee-section h4 {
            color: white;
            margin-bottom: 0.875rem;
            font-size: 1rem;
        }
        
        .fee-section .form-group label {
            color: white;
            font-size: 0.8rem;
        }
        
        .fee-section .form-control {
            background: rgba(255,255,255,0.9);
            font-size: 0.8rem;
        }
        
        .fee-grid { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 1.25rem; 
            margin-bottom: 1.25rem;
        }
        
        .fee-card { 
            background: rgba(255,255,255,0.1); 
            padding: 1rem;
            border-radius: 6px;
            border: 2px dashed rgba(255,255,255,0.3);
        }
        
        .fee-preview { 
            background: rgba(255,255,255,0.1); 
            padding: 1rem;
            border-radius: 6px; 
            margin-top: 0.75rem;
            border: 2px dashed rgba(255,255,255,0.3);
        }
        
        .preview-breakdown { 
            background: rgba(255,255,255,0.2); 
            padding: 0.875rem;
            border-radius: 4px; 
            margin-top: 0.75rem;
        }
        
        .breakdown-item { 
            display: flex; 
            justify-content: space-between; 
            margin-bottom: 0.375rem; 
            padding-bottom: 0.375rem;
            border-bottom: 1px solid rgba(255,255,255,0.2);
            font-size: 0.8rem;
        }
        
        .breakdown-total { 
            display: flex; 
            justify-content: space-between; 
            margin-top: 0.375rem; 
            padding-top: 0.375rem;
            border-top: 2px solid rgba(255,255,255,0.5);
            font-weight: bold;
            font-size: 0.9rem;
        }
        
        .type-toggle { 
            display: flex; 
            gap: 0.75rem; 
            margin-bottom: 0.75rem;
        }
        
        .type-toggle label { 
            display: flex; 
            align-items: center; 
            gap: 0.375rem; 
            cursor: pointer;
            font-size: 0.8rem;
        }
        
        .type-toggle input[type="radio"] {
            width: auto;
        }
        
        /* Visa Assessment Settings */
        .visa-assessment-section { 
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.25rem;
        }
        
        .visa-assessment-section h4 {
            color: white;
            margin-bottom: 0.875rem;
            font-size: 1rem;
        }
        
        .visa-assessment-section .form-group label {
            color: white;
            font-size: 0.8rem;
        }
        
        .visa-assessment-section .form-control {
            background: rgba(255,255,255,0.9);
            font-size: 0.8rem;
        }
        
        .visa-grid { 
            display: grid; 
            grid-template-columns: 1fr 1fr 1fr; 
            gap: 1.25rem; 
            margin-bottom: 1.25rem;
        }
        
        .visa-card { 
            background: rgba(255,255,255,0.1); 
            padding: 1rem;
            border-radius: 6px;
            border: 2px dashed rgba(255,255,255,0.3);
        }
        
        .visa-preview { 
            background: rgba(255,255,255,0.1); 
            padding: 1rem;
            border-radius: 6px; 
            margin-top: 0.75rem;
            border: 2px dashed rgba(255,255,255,0.3);
        }
        
        .grade-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.7rem;
            font-weight: bold;
            margin-bottom: 8px;
        }
        
        .grade-low {
            background: #ff6b6b;
            color: white;
        }
        
        .grade-medium {
            background: #feca57;
            color: #000;
        }
        
        .grade-high {
            background: #1dd1a1;
            color: white;
        }
        
        /* Guest Booking Styles */
        .guest-booking-section { 
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.25rem;
        }
        
        .guest-booking-section h4 {
            color: white;
            margin-bottom: 0.875rem;
            font-size: 1rem;
        }
        
        .guest-booking-section .form-group label {
            color: white;
            font-size: 0.8rem;
        }
        
        .guest-booking-section .form-control {
            background: rgba(255,255,255,0.9);
            font-size: 0.8rem;
        }
        
        .guest-grid { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 1.25rem; 
            margin-bottom: 1.25rem;
        }
        
        .guest-card { 
            background: rgba(255,255,255,0.1); 
            padding: 1rem;
            border-radius: 6px;
            border: 2px dashed rgba(255,255,255,0.3);
        }
        
        .guest-preview { 
            background: rgba(255,255,255,0.1); 
            padding: 1rem;
            border-radius: 6px; 
            margin-top: 0.75rem;
            border: 2px dashed rgba(255,255,255,0.3);
        }
        
        /* Test Email Field */
        .test-email-field {
            background: #e7f3ff;
            padding: 1rem;
            border-radius: 6px;
            margin-top: 0.75rem;
            border-left: 4px solid #007bff;
        }
        
        .test-email-field h5 {
            color: #0056b3;
            margin-bottom: 0.75rem;
            font-size: 0.9rem;
        }

        /* CPanel SMTP Recommendations */
        .cpanel-recommendations {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
            padding: 1.25rem;
            border-radius: 6px;
            margin-bottom: 1.25rem;
            font-size: 0.8rem;
        }
        
        .cpanel-recommendations h4 {
            color: white;
            margin-bottom: 0.75rem;
            font-size: 0.9rem;
        }
        
        .cpanel-recommendations ul {
            margin-left: 1.25rem;
        }
        
        .cpanel-recommendations li {
            margin-bottom: 0.375rem;
        }

        /* Quick actions */
        .quick-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }

        /* Responsive design */
        @media (max-width: 1200px) {
            .main-content {
                margin-left: 0;
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
            
            .top-bar {
                padding: 0.5rem 1rem;
            }
            
            .top-bar h1 {
                font-size: 1.25rem;
            }
            
            .content {
                padding: 1rem;
            }
            
            .fee-grid,
            .visa-grid,
            .guest-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 0.125rem;
            }
            
            .btn-xs {
                width: 100%;
                justify-content: center;
            }
            
            .file-preview {
                flex-direction: column;
                text-align: center;
            }
            
            .file-actions {
                justify-content: center;
            }
            
            .type-toggle {
                flex-direction: column;
                gap: 0.5rem;
            }
        }
        
        @media (max-width: 480px) {
            .card-body {
                padding: 1rem;
            }
            
            .card-header {
                padding: 0.75rem 1rem;
                flex-direction: column;
                gap: 0.5rem;
                align-items: flex-start;
            }
            
            .quick-actions {
                flex-direction: column;
            }
            
            .quick-actions .btn {
                width: 100%;
                justify-content: center;
            }
            
            .offer-section,
            .fee-section,
            .visa-assessment-section,
            .guest-booking-section {
                padding: 1rem;
            }
        }
        
        /* Print styles */
        @media print {
            .sidebar, .top-bar, .action-buttons, .quick-actions {
                display: none !important;
            }
            .main-content {
                margin-left: 0 !important;
            }
            .card {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
            }
        }
        
        .btn-outline {
            background: transparent;
            border: 1px solid #007bff;
            color: #007bff;
            font-size: 0.8rem;
            padding: 0.375rem 0.75rem;
        }
        
        .btn-outline:hover {
            background: #007bff;
            color: white;
        }
    </style>
</head>
<body>
    <!-- Include Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-bar">
            <h1>Site Settings</h1>
            <div>
                <span>Welcome, <?php echo $_SESSION['user_name'] ?? 'Admin'; ?></span>
            </div>
        </div>

        <div class="content">
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <button type="button" class="btn btn-primary btn-sm" onclick="scrollToSection('general')">
                    <i class="fas fa-cog"></i> General
                </button>
                <button type="button" class="btn btn-info btn-sm" onclick="scrollToSection('email')">
                    <i class="fas fa-envelope"></i> Email
                </button>
                <button type="button" class="btn btn-success btn-sm" onclick="scrollToSection('apis')">
                    <i class="fas fa-code"></i> APIs
                </button>
                <button type="button" class="btn btn-warning btn-sm" onclick="scrollToSection('offers')">
                    <i class="fas fa-gift"></i> Offers
                </button>
            </div>

            <form method="POST" action="" enctype="multipart/form-data" id="settingsForm">
                
                <!-- Guest Booking & Tracking Settings -->
                <div class="card">
                    <div class="card-header">
                        <h3>Guest Booking & Tracking</h3>
                    </div>
                    <div class="card-body">
                        <div class="guest-booking-section">
                            <h4>👤 Guest Booking Configuration</h4>
                            <p style="margin-bottom: 1.25rem; opacity: 0.9; font-size: 0.8rem;">
                                Configure guest bookings and ticket tracking settings.
                            </p>

                            <div class="guest-grid">
                                <div class="guest-card">
                                    <h5>📝 Guest Booking</h5>
                                    
                                    <div class="checkbox-group form-group">
                                        <input type="checkbox" id="guest_booking_enabled" name="guest_booking_enabled" value="1" 
                                               <?php echo ($settings['guest_booking_enabled'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                        <label for="guest_booking_enabled" style="display: inline; margin-bottom: 0;">Enable Guest Bookings</label>
                                        <small style="display: block; color: rgba(255,255,255,0.8); margin-top: 0.375rem; font-size: 0.75rem;">
                                            Allow bookings without account
                                        </small>
                                    </div>

                                    <div class="checkbox-group form-group">
                                        <input type="checkbox" id="allow_guest_registration" name="allow_guest_registration" value="1" 
                                               <?php echo ($settings['allow_guest_registration'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                        <label for="allow_guest_registration" style="display: inline; margin-bottom: 0;">Allow Guest Registration</label>
                                        <small style="display: block; color: rgba(255,255,255,0.8); margin-top: 0.375rem; font-size: 0.75rem;">
                                            Show registration option after payment
                                        </small>
                                    </div>
                                </div>

                                <div class="guest-card">
                                    <h5>🔔 Notifications</h5>
                                    
                                    <div class="checkbox-group form-group">
                                        <input type="checkbox" id="notify_admin_guest_bookings" name="notify_admin_guest_bookings" value="1" 
                                               <?php echo ($settings['notify_admin_guest_bookings'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                        <label for="notify_admin_guest_bookings" style="display: inline; margin-bottom: 0;">Notify Admin</label>
                                        <small style="display: block; color: rgba(255,255,255,0.8); margin-top: 0.375rem; font-size: 0.75rem;">
                                            Email admin on guest bookings
                                        </small>
                                    </div>

                                    <div class="checkbox-group form-group">
                                        <input type="checkbox" id="notify_user_guest_bookings" name="notify_user_guest_bookings" value="1" 
                                               <?php echo ($settings['notify_user_guest_bookings'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                        <label for="notify_user_guest_bookings" style="display: inline; margin-bottom: 0;">Notify Users</label>
                                        <small style="display: block; color: rgba(255,255,255,0.8); margin-top: 0.375rem; font-size: 0.75rem;">
                                            Send confirmation to guests
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <div class="guest-card">
                                <h5>🎫 Ticket Tracking</h5>
                                
                                <div class="checkbox-group form-group">
                                    <input type="checkbox" id="ticket_tracking_enabled" name="ticket_tracking_enabled" value="1" 
                                           <?php echo ($settings['ticket_tracking_enabled'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                    <label for="ticket_tracking_enabled" style="display: inline; margin-bottom: 0;">Enable Ticket Tracking</label>
                                    <small style="display: block; color: rgba(255,255,255,0.8); margin-top: 0.375rem; font-size: 0.75rem;">
                                        Allow flight status tracking
                                    </small>
                                </div>

                                <div class="form-group">
                                    <label for="tracking_page_url">Tracking Page URL</label>
                                    <input type="text" id="tracking_page_url" name="tracking_page_url" class="form-control" 
                                           value="<?php echo $settings['tracking_page_url'] ?? 'track-ticket.php'; ?>" 
                                           placeholder="track-ticket.php">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Visa Assessment Booking Settings -->
                <div class="card">
                    <div class="card-header">
                        <h3>Visa Assessment Booking</h3>
                    </div>
                    <div class="card-body">
                        <div class="visa-assessment-section">
                            <h4>🛂 Visa Assessment Payment</h4>
                            <p style="margin-bottom: 1.25rem; opacity: 0.9; font-size: 0.8rem;">
                                Configure booking fees for different visa assessment grades.
                            </p>

                            <div class="visa-grid">
                                <div class="visa-card">
                                    <span class="grade-badge grade-low">Low Chance</span>
                                    <div class="form-group">
                                        <label for="visa_assessment_booking_fee_low">Booking Fee (₦)</label>
                                        <input type="number" id="visa_assessment_booking_fee_low" name="visa_assessment_booking_fee_low" class="form-control" 
                                               value="<?php echo $settings['visa_assessment_booking_fee_low'] ?? 5000; ?>" 
                                               step="0.01" min="0" placeholder="5000.00">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="visa_assessment_booking_link_low">Payment Link</label>
                                        <input type="url" id="visa_assessment_booking_link_low" name="visa_assessment_booking_link_low" class="form-control" 
                                               value="<?php echo $settings['visa_assessment_booking_link_low'] ?? ''; ?>" 
                                               placeholder="https://paystack.com/pay/visa-low">
                                    </div>
                                </div>

                                <div class="visa-card">
                                    <span class="grade-badge grade-medium">Medium Chance</span>
                                    <div class="form-group">
                                        <label for="visa_assessment_booking_fee_medium">Booking Fee (₦)</label>
                                        <input type="number" id="visa_assessment_booking_fee_medium" name="visa_assessment_booking_fee_medium" class="form-control" 
                                               value="<?php echo $settings['visa_assessment_booking_fee_medium'] ?? 3000; ?>" 
                                               step="0.01" min="0" placeholder="3000.00">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="visa_assessment_booking_link_medium">Payment Link</label>
                                        <input type="url" id="visa_assessment_booking_link_medium" name="visa_assessment_booking_link_medium" class="form-control" 
                                               value="<?php echo $settings['visa_assessment_booking_link_medium'] ?? ''; ?>" 
                                               placeholder="https://paystack.com/pay/visa-medium">
                                    </div>
                                </div>

                                <div class="visa-card">
                                    <span class="grade-badge grade-high">High Chance</span>
                                    <div class="form-group">
                                        <label for="visa_assessment_booking_fee_high">Booking Fee (₦)</label>
                                        <input type="number" id="visa_assessment_booking_fee_high" name="visa_assessment_booking_fee_high" class="form-control" 
                                               value="<?php echo $settings['visa_assessment_booking_fee_high'] ?? 0; ?>" 
                                               step="0.01" min="0" placeholder="0.00">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="visa_assessment_booking_link_high">Payment Link</label>
                                        <input type="url" id="visa_assessment_booking_link_high" name="visa_assessment_booking_link_high" class="form-control" 
                                               value="<?php echo $settings['visa_assessment_booking_link_high'] ?? ''; ?>" 
                                               placeholder="https://paystack.com/pay/visa-high">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tax and Service Fee Settings -->
                <div class="card">
                    <div class="card-header">
                        <h3>Tax & Service Fees</h3>
                    </div>
                    <div class="card-body">
                        <div class="fee-section">
                            <h4>💰 Additional Charges</h4>
                            <p style="margin-bottom: 1.25rem; opacity: 0.9; font-size: 0.8rem;">
                                Configure automatic charges for flight bookings.
                            </p>

                            <div class="fee-grid">
                                <div class="fee-card">
                                    <h5>📊 Tax Settings</h5>
                                    
                                    <div class="checkbox-group form-group">
                                        <input type="checkbox" id="tax_enabled" name="tax_enabled" value="1" 
                                               <?php echo ($settings['tax_enabled'] ?? 0) ? 'checked' : ''; ?> 
                                               onchange="toggleFeeSettings('tax')">
                                        <label for="tax_enabled" style="display: inline; margin-bottom: 0;">Enable Tax</label>
                                    </div>

                                    <div id="taxSettings" style="<?php echo ($settings['tax_enabled'] ?? 0) ? '' : 'display: none;'; ?>">
                                        <div class="form-group">
                                            <label for="tax_name">Tax Name</label>
                                            <input type="text" id="tax_name" name="tax_name" class="form-control" 
                                                   value="<?php echo $settings['tax_name'] ?? 'Tax'; ?>" 
                                                   placeholder="e.g., VAT, GST">
                                        </div>

                                        <div class="type-toggle">
                                            <label>
                                                <input type="radio" name="tax_type" value="percentage" 
                                                       <?php echo ($settings['tax_type'] ?? 'percentage') === 'percentage' ? 'checked' : ''; ?> 
                                                       onchange="updateFeePreview()"> Percentage (%)
                                            </label>
                                            <label>
                                                <input type="radio" name="tax_type" value="flat" 
                                                       <?php echo ($settings['tax_type'] ?? '') === 'flat' ? 'checked' : ''; ?> 
                                                       onchange="updateFeePreview()"> Flat Amount
                                            </label>
                                        </div>

                                        <div class="form-group">
                                            <label for="tax_value">
                                                Tax Value 
                                                <span id="taxValueLabel">
                                                    <?php echo ($settings['tax_type'] ?? 'percentage') === 'percentage' ? '(%)' : '(Amount)'; ?>
                                                </span>
                                            </label>
                                            <input type="number" id="tax_value" name="tax_value" class="form-control" 
                                                   value="<?php echo $settings['tax_value'] ?? 0; ?>" 
                                                   step="<?php echo ($settings['tax_type'] ?? 'percentage') === 'percentage' ? '0.1' : '1'; ?>" 
                                                   min="0" 
                                                   oninput="updateFeePreview()">
                                        </div>
                                    </div>
                                </div>

                                <div class="fee-card">
                                    <h5>🔧 Service Fee</h5>
                                    
                                    <div class="checkbox-group form-group">
                                        <input type="checkbox" id="service_fee_enabled" name="service_fee_enabled" value="1" 
                                               <?php echo ($settings['service_fee_enabled'] ?? 0) ? 'checked' : ''; ?> 
                                               onchange="toggleFeeSettings('service_fee')">
                                        <label for="service_fee_enabled" style="display: inline; margin-bottom: 0;">Enable Service Fee</label>
                                    </div>

                                    <div id="serviceFeeSettings" style="<?php echo ($settings['service_fee_enabled'] ?? 0) ? '' : 'display: none;'; ?>">
                                        <div class="form-group">
                                            <label for="service_fee_name">Service Fee Name</label>
                                            <input type="text" id="service_fee_name" name="service_fee_name" class="form-control" 
                                                   value="<?php echo $settings['service_fee_name'] ?? 'Service Fee'; ?>" 
                                                   placeholder="e.g., Booking Fee">
                                        </div>

                                        <div class="type-toggle">
                                            <label>
                                                <input type="radio" name="service_fee_type" value="percentage" 
                                                       <?php echo ($settings['service_fee_type'] ?? 'percentage') === 'percentage' ? 'checked' : ''; ?> 
                                                       onchange="updateFeePreview()"> Percentage (%)
                                            </label>
                                            <label>
                                                <input type="radio" name="service_fee_type" value="flat" 
                                                       <?php echo ($settings['service_fee_type'] ?? '') === 'flat' ? 'checked' : ''; ?> 
                                                       onchange="updateFeePreview()"> Flat Amount
                                            </label>
                                        </div>

                                        <div class="form-group">
                                            <label for="service_fee_value">
                                                Service Fee Value 
                                                <span id="serviceFeeValueLabel">
                                                    <?php echo ($settings['service_fee_type'] ?? 'percentage') === 'percentage' ? '(%)' : '(Amount)'; ?>
                                                </span>
                                            </label>
                                            <input type="number" id="service_fee_value" name="service_fee_value" class="form-control" 
                                                   value="<?php echo $settings['service_fee_value'] ?? 0; ?>" 
                                                   step="<?php echo ($settings['service_fee_type'] ?? 'percentage') === 'percentage' ? '0.1' : '1'; ?>" 
                                                   min="0" 
                                                   oninput="updateFeePreview()">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Special Offers Section -->
                <div class="card" id="offers">
                    <div class="card-header">
                        <h3>Special Offers</h3>
                    </div>
                    <div class="card-body">
                        <div class="offer-section">
                            <h4>🎁 Current Special Offer</h4>
                            
                            <div class="checkbox-group form-group">
                                <input type="checkbox" id="offer_enabled" name="offer_enabled" value="1" 
                                       <?php echo ($settings['offer_enabled'] ?? 0) ? 'checked' : ''; ?>>
                                <label for="offer_enabled" style="display: inline; margin-bottom: 0;">Enable Special Offer</label>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="offer_title">Offer Title</label>
                                    <input type="text" id="offer_title" name="offer_title" class="form-control" 
                                           value="<?php echo $settings['offer_title'] ?? 'Special Flight Deal!'; ?>" 
                                           placeholder="e.g., Summer Sale - 50% Off">
                                </div>
                                
                                <div class="form-group">
                                    <label for="offer_discount">Discount</label>
                                    <input type="text" id="offer_discount" name="offer_discount" class="form-control" 
                                           value="<?php echo $settings['offer_discount'] ?? '50%'; ?>" 
                                           placeholder="e.g., 50%">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="offer_description">Offer Description</label>
                                <textarea id="offer_description" name="offer_description" class="form-control" rows="2" 
                                          placeholder="Describe your special offer..."><?php echo $settings['offer_description'] ?? 'Book your dream vacation now and enjoy incredible savings! Limited time offer.'; ?></textarea>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="offer_valid_until">Valid Until</label>
                                    <input type="date" id="offer_valid_until" name="offer_valid_until" class="form-control" 
                                           value="<?php echo $settings['offer_valid_until'] ?? ''; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="offer_image">Offer Image</label>
                                    <div class="file-upload">
                                        <input type="file" id="offer_image" name="offer_image" class="form-control" 
                                               accept=".jpg,.jpeg,.png,.gif,.webp" 
                                               onchange="previewFile('offer_image', 'offerImagePreview')">
                                        
                                        <?php if (!empty($settings['offer_image'])): ?>
                                        <div class="offer-preview" id="offerImagePreview">
                                            <img src="../<?php echo $settings['offer_image']; ?>" alt="Current Offer Image">
                                            <div class="file-info">
                                                <strong>Current Offer Image</strong><br>
                                                <small><?php echo basename($settings['offer_image']); ?></small>
                                            </div>
                                            <div class="file-actions">
                                                <button type="button" class="btn btn-secondary btn-xs" onclick="window.open('../<?php echo $settings['offer_image']; ?>', '_blank')">View</button>
                                                <button type="submit" name="remove_offer_image" class="btn btn-danger btn-xs" onclick="return confirm('Remove offer image?')">Remove</button>
                                            </div>
                                        </div>
                                        <?php else: ?>
                                        <div class="offer-preview" id="offerImagePreview" style="display: none;">
                                            <img id="offer_imagePreviewImg" src="" alt="Offer Image Preview">
                                            <div class="file-info">
                                                <strong>New Offer Image Preview</strong><br>
                                                <small id="offer_imageFileName"></small>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Branding Settings -->
                <div class="card">
                    <div class="card-header">
                        <h3>Branding & Appearance</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="logo">Site Logo</label>
                                <div class="file-upload">
                                    <input type="file" id="logo" name="logo" class="form-control" 
                                           accept=".jpg,.jpeg,.png,.gif,.svg" 
                                           onchange="previewFile('logo', 'logoPreview')">
                                    
                                    <?php if (!empty($settings['logo'])): ?>
                                    <div class="file-preview" id="logoPreview">
                                        <img src="../<?php echo $settings['logo']; ?>" alt="Current Logo">
                                        <div class="file-info">
                                            <strong>Current Logo</strong><br>
                                            <small><?php echo basename($settings['logo']); ?></small>
                                        </div>
                                        <div class="file-actions">
                                            <button type="button" class="btn btn-secondary btn-xs" onclick="window.open('../<?php echo $settings['logo']; ?>', '_blank')">View</button>
                                            <button type="submit" name="remove_logo" class="btn btn-danger btn-xs" onclick="return confirm('Remove logo?')">Remove</button>
                                        </div>
                                    </div>
                                    <?php else: ?>
                                    <div class="file-preview" id="logoPreview" style="display: none;">
                                        <img id="logoPreviewImg" src="" alt="Logo Preview">
                                        <div class="file-info">
                                            <strong>New Logo Preview</strong><br>
                                            <small id="logoFileName"></small>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="favicon">Favicon</label>
                                <div class="file-upload">
                                    <input type="file" id="favicon" name="favicon" class="form-control" 
                                           accept=".ico,.png" 
                                           onchange="previewFile('favicon', 'faviconPreview')">
                                    
                                    <?php if (!empty($settings['favicon'])): ?>
                                    <div class="file-preview" id="faviconPreview">
                                        <img src="../<?php echo $settings['favicon']; ?>" alt="Current Favicon" style="width: 32px; height: 32px;">
                                        <div class="file-info">
                                            <strong>Current Favicon</strong><br>
                                            <small><?php echo basename($settings['favicon']); ?></small>
                                        </div>
                                        <div class="file-actions">
                                            <button type="button" class="btn btn-secondary btn-xs" onclick="window.open('../<?php echo $settings['favicon']; ?>', '_blank')">View</button>
                                            <button type="submit" name="remove_favicon" class="btn btn-danger btn-xs" onclick="return confirm('Remove favicon?')">Remove</button>
                                        </div>
                                    </div>
                                    <?php else: ?>
                                    <div class="file-preview" id="faviconPreview" style="display: none;">
                                        <img id="faviconPreviewImg" src="" alt="Favicon Preview" style="width: 32px; height: 32px;">
                                        <div class="file-info">
                                            <strong>New Favicon Preview</strong><br>
                                            <small id="faviconFileName"></small>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- General Settings -->
                <div class="card" id="general">
                    <div class="card-header">
                        <h3>General Settings</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="site_name">Site Name</label>
                                <input type="text" id="site_name" name="site_name" class="form-control" 
                                       value="<?php echo $settings['site_name'] ?? ''; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="site_title">Site Title</label>
                                <input type="text" id="site_title" name="site_title" class="form-control" 
                                       value="<?php echo $settings['site_title'] ?? ''; ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="site_description">Site Description</label>
                            <textarea id="site_description" name="site_description" class="form-control" rows="2"><?php echo $settings['site_description'] ?? ''; ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="site_keywords">Site Keywords</label>
                            <input type="text" id="site_keywords" name="site_keywords" class="form-control" 
                                   value="<?php echo $settings['site_keywords'] ?? ''; ?>" 
                                   placeholder="keyword1, keyword2, keyword3">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="admin_email">Admin Email</label>
                                <input type="email" id="admin_email" name="admin_email" class="form-control" 
                                       value="<?php echo $settings['admin_email'] ?? ''; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="support_email">Support Email</label>
                                <input type="email" id="support_email" name="support_email" class="form-control" 
                                       value="<?php echo $settings['support_email'] ?? ''; ?>" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="text" id="phone" name="phone" class="form-control" 
                                       value="<?php echo $settings['phone'] ?? ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="currency">Currency</label>
                                <select id="currency" name="currency" class="form-control">
                                    <option value="NGN" <?php echo ($settings['currency'] ?? 'NGN') === 'NGN' ? 'selected' : ''; ?>>NGN - Nigerian Naira</option>
                                    <option value="USD" <?php echo ($settings['currency'] ?? '') === 'USD' ? 'selected' : ''; ?>>USD - US Dollar</option>
                                    <option value="EUR" <?php echo ($settings['currency'] ?? '') === 'EUR' ? 'selected' : ''; ?>>EUR - Euro</option>
                                    <option value="GBP" <?php echo ($settings['currency'] ?? '') === 'GBP' ? 'selected' : ''; ?>>GBP - British Pound</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="timezone">Timezone</label>
                                <select id="timezone" name="timezone" class="form-control">
                                    <option value="Africa/Lagos" <?php echo ($settings['timezone'] ?? 'Africa/Lagos') === 'Africa/Lagos' ? 'selected' : ''; ?>>Africa/Lagos</option>
                                    <option value="UTC" <?php echo ($settings['timezone'] ?? '') === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                                    <option value="America/New_York" <?php echo ($settings['timezone'] ?? '') === 'America/New_York' ? 'selected' : ''; ?>>America/New York</option>
                                    <option value="Europe/London" <?php echo ($settings['timezone'] ?? '') === 'Europe/London' ? 'selected' : ''; ?>>Europe/London</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="currency_rate">Currency Rate (USD to NGN)</label>
                                <input type="number" id="currency_rate" name="currency_rate" class="form-control" 
                                       value="<?php echo $settings['currency_rate'] ?? '450'; ?>" step="0.01" min="1" required
                                       placeholder="Enter USD to NGN conversion rate">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="address">Address</label>
                            <textarea id="address" name="address" class="form-control" rows="2"><?php echo $settings['address'] ?? ''; ?></textarea>
                        </div>

                        <div class="currency-info">
                            <h5>💱 Currency Conversion Information</h5>
                            <ul>
                                <li><strong>Purpose:</strong> Converts USD flight prices from Amadeus API to NGN</li>
                                <li><strong>Current Rate:</strong> $1 = ₦<?php echo number_format($settings['currency_rate'] ?? 450, 2); ?></li>
                                <li><strong>Example:</strong> A $100 flight = ₦<?php echo number_format(($settings['currency_rate'] ?? 450) * 100, 2); ?></li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- SMTP Settings -->
                <div class="card" id="email">
                    <div class="card-header">
                        <h3>Email Settings (SMTP)</h3>
                    </div>
                    <div class="card-body">
                        <div class="cpanel-recommendations">
                            <h4>🚀 CPanel SMTP Settings</h4>
                            <ul>
                                <li><strong>SMTP Host:</strong> <code>mail.yourdomain.com</code> or <code>localhost</code></li>
                                <li><strong>SMTP Port:</strong> <code>587</code> (TLS) or <code>465</code> (SSL)</li>
                                <li><strong>SMTP Username:</strong> Full email address</li>
                                <li><strong>From Email:</strong> Must match SMTP username</li>
                            </ul>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="smtp_host">SMTP Host *</label>
                                <input type="text" id="smtp_host" name="smtp_host" class="form-control" 
                                       value="<?php echo $settings['smtp_host'] ?? ''; ?>" 
                                       placeholder="mail.yourdomain.com" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="smtp_port">SMTP Port *</label>
                                <input type="number" id="smtp_port" name="smtp_port" class="form-control" 
                                       value="<?php echo $settings['smtp_port'] ?? '587'; ?>" 
                                       placeholder="587" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="smtp_username">SMTP Username *</label>
                                <input type="text" id="smtp_username" name="smtp_username" class="form-control" 
                                       value="<?php echo $settings['smtp_username'] ?? ''; ?>" 
                                       placeholder="noreply@yourdomain.com" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="smtp_password">SMTP Password *</label>
                                <input type="password" id="smtp_password" name="smtp_password" class="form-control" 
                                       value="<?php echo $settings['smtp_password'] ?? ''; ?>" 
                                       placeholder="Your SMTP password" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="smtp_encryption">Encryption *</label>
                                <select id="smtp_encryption" name="smtp_encryption" class="form-control" required>
                                    <option value="tls" <?php echo ($settings['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : ''; ?>>TLS (Recommended)</option>
                                    <option value="ssl" <?php echo ($settings['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                    <option value="" <?php echo empty($settings['smtp_encryption'] ?? '') ? 'selected' : ''; ?>>None</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="smtp_from_email">From Email *</label>
                                <input type="email" id="smtp_from_email" name="smtp_from_email" class="form-control" 
                                       value="<?php echo $settings['smtp_from_email'] ?? ($settings['admin_email'] ?? ''); ?>" 
                                       placeholder="noreply@yourdomain.com" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="smtp_from_name">From Name *</label>
                            <input type="text" id="smtp_from_name" name="smtp_from_name" class="form-control" 
                                   value="<?php echo $settings['smtp_from_name'] ?? ($settings['site_name'] ?? ''); ?>" 
                                   placeholder="<?php echo $settings['site_name'] ?? 'Your Site Name'; ?>" required>
                        </div>

                        <div class="test-email-field">
                            <h5>✉️ Test Email Configuration</h5>
                            <div class="form-group">
                                <label for="test_email">Test Email Address *</label>
                                <input type="email" id="test_email" name="test_email" class="form-control" 
                                       value="<?php echo $settings['admin_email'] ?? ''; ?>" 
                                       placeholder="Enter email address to send test" required>
                            </div>
                        </div>

                        <div class="test-email-btn">
                            <button type="button" class="btn btn-success btn-sm" onclick="testSMTP()" id="testSmtpBtn">
                                Test SMTP Connection
                            </button>
                            <div id="smtpLoading" class="loading" style="display: inline-block; margin-left: 10px;">
                                <span style="color: #666; font-size: 0.8rem;">⏳ Testing...</span>
                            </div>
                            <div id="testResult" class="test-result" style="display: none;"></div>
                        </div>
                    </div>
                </div>

                <!-- API Settings -->
                <div class="card" id="apis">
                    <div class="card-header">
                        <h3>API Settings</h3>
                    </div>
                    <div class="card-body">
                        <div class="api-section">
                            <h4>Amadeus Flight API</h4>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="amadeus_api_key">Amadeus API Key</label>
                                    <input type="text" id="amadeus_api_key" name="amadeus_api_key" class="form-control" 
                                           value="<?php echo $settings['amadeus_api_key'] ?? (defined('AMADEUS_API_KEY') ? AMADEUS_API_KEY : ''); ?>" 
                                           placeholder="Enter your Amadeus API Key">
                                </div>
                                
                                <div class="form-group">
                                    <label for="amadeus_api_secret">Amadeus API Secret</label>
                                    <input type="password" id="amadeus_api_secret" name="amadeus_api_secret" class="form-control" 
                                           value="<?php echo $settings['amadeus_api_secret'] ?? (defined('AMADEUS_API_SECRET') ? AMADEUS_API_SECRET : ''); ?>" 
                                           placeholder="Enter your Amadeus API Secret">
                                </div>
                            </div>
                        </div>

                        <div class="api-section">
                            <h4>Paystack Payment API</h4>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="paystack_public_key">Paystack Public Key</label>
                                    <input type="text" id="paystack_public_key" name="paystack_public_key" class="form-control" 
                                           value="<?php echo $settings['paystack_public_key'] ?? (defined('PAYSTACK_PUBLIC_KEY') ? PAYSTACK_PUBLIC_KEY : ''); ?>" 
                                           placeholder="Enter your Paystack Public Key">
                                </div>
                                
                                <div class="form-group">
                                    <label for="paystack_secret_key">Paystack Secret Key</label>
                                    <input type="password" id="paystack_secret_key" name="paystack_secret_key" class="form-control" 
                                           value="<?php echo $settings['paystack_secret_key'] ?? (defined('PAYSTACK_SECRET_KEY') ? PAYSTACK_SECRET_KEY : ''); ?>" 
                                           placeholder="Enter your Paystack Secret Key">
                                </div>
                            </div>
                        </div>

                        <div class="api-section">
                            <h4>Flutterwave Payment API</h4>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="flutterwave_public_key">Flutterwave Public Key</label>
                                    <input type="text" id="flutterwave_public_key" name="flutterwave_public_key" class="form-control" 
                                           value="<?php echo $settings['flutterwave_public_key'] ?? (defined('FLUTTERWAVE_PUBLIC_KEY') ? FLUTTERWAVE_PUBLIC_KEY : ''); ?>" 
                                           placeholder="Enter your Flutterwave Public Key">
                                </div>
                                
                                <div class="form-group">
                                    <label for="flutterwave_secret_key">Flutterwave Secret Key</label>
                                    <input type="password" id="flutterwave_secret_key" name="flutterwave_secret_key" class="form-control" 
                                           value="<?php echo $settings['flutterwave_secret_key'] ?? (defined('FLUTTERWAVE_SECRET_KEY') ? FLUTTERWAVE_SECRET_KEY : ''); ?>" 
                                           placeholder="Enter your Flutterwave Secret Key">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Save All Settings</button>
            </form>
        </div>
    </div>

    <script>
        // Tax and Service Fee Functions
        function toggleFeeSettings(type) {
            const enabled = document.getElementById(type + '_enabled').checked;
            const settingsDiv = document.getElementById(type + 'Settings');
            const previewDiv = document.getElementById(type + 'Preview');
            
            if (settingsDiv) {
                settingsDiv.style.display = enabled ? 'block' : 'none';
            }
            if (previewDiv) {
                previewDiv.style.display = enabled ? 'flex' : 'none';
            }
            
            updateFeePreview();
        }

        function updateFeePreview() {
            const basePrice = 50000;
            
            // Tax calculations
            const taxEnabled = document.getElementById('tax_enabled')?.checked;
            const taxType = document.querySelector('input[name="tax_type"]:checked')?.value;
            const taxValue = parseFloat(document.getElementById('tax_value')?.value) || 0;
            const taxName = document.getElementById('tax_name')?.value || 'Tax';
            
            // Service fee calculations
            const serviceFeeEnabled = document.getElementById('service_fee_enabled')?.checked;
            const serviceFeeType = document.querySelector('input[name="service_fee_type"]:checked')?.value;
            const serviceFeeValue = parseFloat(document.getElementById('service_fee_value')?.value) || 0;
            const serviceFeeName = document.getElementById('service_fee_name')?.value || 'Service Fee';
            
            // Update labels
            const taxValueLabel = document.getElementById('taxValueLabel');
            const serviceFeeValueLabel = document.getElementById('serviceFeeValueLabel');
            
            if (taxValueLabel) taxValueLabel.textContent = taxType === 'percentage' ? '(%)' : '(Amount)';
            if (serviceFeeValueLabel) serviceFeeValueLabel.textContent = serviceFeeType === 'percentage' ? '(%)' : '(Amount)';
            
            // Update step attributes for inputs
            const taxInput = document.getElementById('tax_value');
            const serviceFeeInput = document.getElementById('service_fee_value');
            
            if (taxInput) taxInput.step = taxType === 'percentage' ? '0.1' : '1';
            if (serviceFeeInput) serviceFeeInput.step = serviceFeeType === 'percentage' ? '0.1' : '1';
        }

        // Toggle settings on page load
        document.addEventListener('DOMContentLoaded', function() {
            toggleFeeSettings('tax');
            toggleFeeSettings('service_fee');
            updateFeePreview();
            
            // Set required fields for SMTP
            const smtpRequired = ['smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_from_email', 'smtp_from_name', 'test_email'];
            smtpRequired.forEach(field => {
                const element = document.getElementById(field);
                if (element) {
                    element.required = true;
                }
            });
        });

        // File preview function
        function previewFile(inputId, previewId) {
            const fileInput = document.getElementById(inputId);
            const preview = document.getElementById(previewId);
            const fileName = document.getElementById(inputId + 'FileName');
            const previewImg = document.getElementById(inputId + 'PreviewImg');
            
            const file = fileInput.files[0];
            
            if (file) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    if (previewImg) previewImg.src = e.target.result;
                    if (fileName) fileName.textContent = file.name;
                    if (preview) preview.style.display = 'flex';
                }
                
                reader.readAsDataURL(file);
            } else {
                if (preview) preview.style.display = 'none';
            }
        }

        // Test SMTP Connection
        function testSMTP() {
            const testBtn = document.getElementById('testSmtpBtn');
            const loading = document.getElementById('smtpLoading');
            const resultDiv = document.getElementById('testResult');
            
            // Validate required fields first
            const requiredFields = ['smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_from_email', 'test_email'];
            const missingFields = [];
            
            requiredFields.forEach(field => {
                const value = document.getElementById(field).value.trim();
                if (!value) {
                    missingFields.push(field.replace(/_/g, ' '));
                }
            });
            
            if (missingFields.length > 0) {
                resultDiv.className = 'test-result test-error';
                resultDiv.innerHTML = `<strong>❌ Missing Required Fields:</strong> Please fill in: ${missingFields.join(', ')}`;
                resultDiv.style.display = 'block';
                return;
            }
            
            // Validate test email format
            const testEmail = document.getElementById('test_email').value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(testEmail)) {
                resultDiv.className = 'test-result test-error';
                resultDiv.innerHTML = `<strong>❌ Invalid Email:</strong> Please enter a valid test email address`;
                resultDiv.style.display = 'block';
                return;
            }
            
            // Get form data
            const formData = new FormData();
            formData.append('test_smtp', 'true');
            formData.append('smtp_host', document.getElementById('smtp_host').value);
            formData.append('smtp_port', document.getElementById('smtp_port').value);
            formData.append('smtp_username', document.getElementById('smtp_username').value);
            formData.append('smtp_password', document.getElementById('smtp_password').value);
            formData.append('smtp_encryption', document.getElementById('smtp_encryption').value);
            formData.append('smtp_from_email', document.getElementById('smtp_from_email').value);
            formData.append('smtp_from_name', document.getElementById('smtp_from_name').value);
            formData.append('test_email', testEmail);

            // Show loading state
            testBtn.disabled = true;
            loading.style.display = 'inline-block';
            resultDiv.style.display = 'none';

            // Send request to the same page
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    resultDiv.className = 'test-result test-success';
                    resultDiv.innerHTML = `<strong>✅ Success!</strong> ${data.message}`;
                } else {
                    resultDiv.className = 'test-result test-error';
                    resultDiv.innerHTML = `<strong>❌ Error:</strong> ${data.message}`;
                }
                resultDiv.style.display = 'block';
            })
            .catch(error => {
                console.error('SMTP Test Error:', error);
                resultDiv.className = 'test-result test-error';
                resultDiv.innerHTML = `<strong>❌ Network Error:</strong> Failed to test SMTP connection. Please check your settings and try again.`;
                resultDiv.style.display = 'block';
            })
            .finally(() => {
                testBtn.disabled = false;
                loading.style.display = 'none';
                
                // Scroll to result
                resultDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            });
        }

        // Scroll to section function
        function scrollToSection(sectionId) {
            const element = document.getElementById(sectionId);
            if (element) {
                element.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        // Quick navigation
        document.addEventListener('DOMContentLoaded', function() {
            // Add quick navigation to sections
            const sections = {
                'general': 'General Settings',
                'email': 'Email Settings', 
                'apis': 'API Settings',
                'offers': 'Special Offers'
            };
        });
    </script>
</body>
</html>