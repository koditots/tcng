<?php
// profile.php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$page_title = "My Profile";

$user_id = $_SESSION['user_id'];

// Get user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    redirect('logout.php');
}

// Update profile if form submitted
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = sanitize($_POST['first_name']);
    $last_name = sanitize($_POST['last_name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $address = sanitize($_POST['address']);
    $date_of_birth = sanitize($_POST['date_of_birth']);
    $gender = sanitize($_POST['gender']);
    
    // Validate required fields
    if (empty($first_name) || empty($last_name) || empty($email)) {
        $error = "First name, last name, and email are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        try {
            // Check if email already exists (excluding current user)
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $user_id]);
            $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing_user) {
                $error = "Email address is already registered by another user.";
            } else {
                // Check which columns exist in the database
                $stmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'address'");
                $stmt->execute();
                $address_column_exists = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $stmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'date_of_birth'");
                $stmt->execute();
                $dob_column_exists = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $stmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'gender'");
                $stmt->execute();
                $gender_column_exists = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $stmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'phone'");
                $stmt->execute();
                $phone_column_exists = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Build dynamic UPDATE query based on available columns
                $update_fields = ["first_name = ?", "last_name = ?", "email = ?", "updated_at = NOW()"];
                $update_values = [$first_name, $last_name, $email];
                
                if ($phone_column_exists) {
                    $update_fields[] = "phone = ?";
                    $update_values[] = $phone;
                }
                
                if ($address_column_exists) {
                    $update_fields[] = "address = ?";
                    $update_values[] = $address;
                }
                
                if ($dob_column_exists) {
                    $update_fields[] = "date_of_birth = ?";
                    $update_values[] = $date_of_birth;
                }
                
                if ($gender_column_exists) {
                    $update_fields[] = "gender = ?";
                    $update_values[] = $gender;
                }
                
                $update_values[] = $user_id; // For WHERE clause
                
                $sql = "UPDATE users SET " . implode(", ", $update_fields) . " WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($update_values);
                
                // Update session variables
                $_SESSION['user_name'] = $first_name . ' ' . $last_name;
                $_SESSION['user_email'] = $email;
                
                $success = "Profile updated successfully!";
                
                // Refresh user data
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {
            $error = "Error updating profile: " . $e->getMessage();
        }
    }
}

// Get user statistics
$stmt = $pdo->prepare("SELECT 
    COUNT(*) as total_bookings,
    SUM(CASE WHEN status IN ('confirmed', 'paid') THEN 1 ELSE 0 END) as confirmed_bookings,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_bookings,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings
    FROM flight_bookings WHERE user_id = ?");
$stmt->execute([$user_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

require_once 'includes/header.php';
?>

<div class="container">
    <div style="max-width: 1000px; margin: 0 auto;">
        <!-- Header -->
        <div style="display: flex; justify-content: between; align-items: center; margin: 2rem 0;">
            <a href="dashboard.php" class="btn" style="background: #6c757d; color: white; padding: 0.5rem 1rem; text-decoration: none; border-radius: 5px;">
                ← Back to Dashboard
            </a>
            <h1 style="color: #333; margin: 0;">My Profile</h1>
        </div>

        <?php if ($success): ?>
            <div style="background: #d4edda; color: #155724; padding: 1rem; border-radius: 5px; margin-bottom: 2rem; border: 1px solid #c3e6cb;">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 5px; margin-bottom: 2rem; border: 1px solid #f5c6cb;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 2rem;">
            <!-- Sidebar - User Stats -->
            <div>
                <!-- Profile Summary -->
                <div style="background: white; padding: 2rem; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); margin-bottom: 1.5rem; text-align: center;">
                    <div style="width: 80px; height: 80px; background: #007bff; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; font-size: 2rem; color: white; font-weight: bold;">
                        <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                    </div>
                    <h3 style="color: #333; margin-bottom: 0.5rem;"><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></h3>
                    <p style="color: #666; margin-bottom: 1rem;"><?php echo $user['email']; ?></p>
                    <div style="background: #e7f3ff; padding: 0.5rem; border-radius: 5px; font-size: 0.9rem;">
                        Member since <?php echo date('M Y', strtotime($user['created_at'])); ?>
                    </div>
                </div>

                <!-- Booking Statistics -->
                <div style="background: white; padding: 1.5rem; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
                    <h4 style="color: #333; margin-bottom: 1rem;">Booking Statistics</h4>
                    <div style="display: grid; gap: 1rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem; background: #f8f9fa; border-radius: 5px;">
                            <span style="color: #666;">Total Bookings</span>
                            <span style="font-weight: bold; color: #007bff;"><?php echo $stats['total_bookings'] ?? 0; ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem; background: #f8f9fa; border-radius: 5px;">
                            <span style="color: #666;">Confirmed</span>
                            <span style="font-weight: bold; color: #28a745;"><?php echo $stats['confirmed_bookings'] ?? 0; ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem; background: #f8f9fa; border-radius: 5px;">
                            <span style="color: #666;">Pending</span>
                            <span style="font-weight: bold; color: #ffc107;"><?php echo $stats['pending_bookings'] ?? 0; ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem; background: #f8f9fa; border-radius: 5px;">
                            <span style="color: #666;">Cancelled</span>
                            <span style="font-weight: bold; color: #dc3545;"><?php echo $stats['cancelled_bookings'] ?? 0; ?></span>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div style="background: white; padding: 1.5rem; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); margin-top: 1.5rem;">
                    <h4 style="color: #333; margin-bottom: 1rem;">Quick Actions</h4>
                    <div style="display: grid; gap: 0.5rem;">
                        <a href="dashboard.php" style="display: block; padding: 0.75rem; background: #f8f9fa; border-radius: 5px; text-decoration: none; color: #333; transition: all 0.3s;">
                            📊 View Dashboard
                        </a>
                        <a href="booking-history.php" style="display: block; padding: 0.75rem; background: #f8f9fa; border-radius: 5px; text-decoration: none; color: #333; transition: all 0.3s;">
                            📋 Booking History
                        </a>
                        <a href="change-password.php" style="display: block; padding: 0.75rem; background: #f8f9fa; border-radius: 5px; text-decoration: none; color: #333; transition: all 0.3s;">
                            🔒 Change Password
                        </a>
                    </div>
                </div>
            </div>

            <!-- Main Content - Profile Form -->
            <div>
                <div style="background: white; padding: 2rem; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
                    <h3 style="color: #333; margin-bottom: 1.5rem; padding-bottom: 0.5rem; border-bottom: 2px solid #007bff;">Edit Profile Information</h3>
                    
                    <form method="POST" action="">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                            <div class="form-group">
                                <label for="first_name" style="display: block; margin-bottom: 0.5rem; font-weight: bold; color: #333;">First Name *</label>
                                <input type="text" id="first_name" name="first_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['first_name']); ?>" required 
                                       style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px;">
                            </div>
                            
                            <div class="form-group">
                                <label for="last_name" style="display: block; margin-bottom: 0.5rem; font-weight: bold; color: #333;">Last Name *</label>
                                <input type="text" id="last_name" name="last_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['last_name']); ?>" required 
                                       style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px;">
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label for="email" style="display: block; margin-bottom: 0.5rem; font-weight: bold; color: #333;">Email Address *</label>
                            <input type="email" id="email" name="email" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" required 
                                   style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px;">
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                            <div class="form-group">
                                <label for="phone" style="display: block; margin-bottom: 0.5rem; font-weight: bold; color: #333;">Phone Number</label>
                                <input type="tel" id="phone" name="phone" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" 
                                       style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px;">
                            </div>
                            
                            <div class="form-group">
                                <label for="date_of_birth" style="display: block; margin-bottom: 0.5rem; font-weight: bold; color: #333;">Date of Birth</label>
                                <input type="date" id="date_of_birth" name="date_of_birth" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['date_of_birth'] ?? ''); ?>" 
                                       style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px;">
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                            <div class="form-group">
                                <label for="gender" style="display: block; margin-bottom: 0.5rem; font-weight: bold; color: #333;">Gender</label>
                                <select id="gender" name="gender" class="form-control" 
                                        style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px;">
                                    <option value="">Select Gender</option>
                                    <option value="male" <?php echo ($user['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="female" <?php echo ($user['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                                    <option value="other" <?php echo ($user['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: bold; color: #333;">Account Status</label>
                                <div style="padding: 0.75rem; background: #f8f9fa; border-radius: 5px; color: #666;">
                                    <?php echo ucfirst($user['status'] ?? 'active'); ?>
                                </div>
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label for="address" style="display: block; margin-bottom: 0.5rem; font-weight: bold; color: #333;">Address</label>
                            <textarea id="address" name="address" class="form-control" rows="3" 
                                      style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px;"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                        </div>

                        <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                            <button type="submit" class="btn btn-primary" style="background: #007bff; color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 5px; cursor: pointer;">
                                Update Profile
                            </button>
                            <button type="reset" class="btn" style="background: #6c757d; color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 5px; cursor: pointer;">
                                Reset Changes
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Account Information -->
                <div style="background: white; padding: 2rem; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); margin-top: 1.5rem;">
                    <h3 style="color: #333; margin-bottom: 1.5rem; padding-bottom: 0.5rem; border-bottom: 2px solid #007bff;">Account Information</h3>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem;">
                        <div>
                            <strong style="color: #666;">User ID</strong>
                            <div style="font-family: 'Courier New', monospace;"><?php echo $user['id']; ?></div>
                        </div>
                        <div>
                            <strong style="color: #666;">Account Created</strong>
                            <div><?php echo date('F j, Y g:i A', strtotime($user['created_at'])); ?></div>
                        </div>
                        <div>
                            <strong style="color: #666;">Last Updated</strong>
                            <div><?php echo $user['updated_at'] ? date('F j, Y g:i A', strtotime($user['updated_at'])) : 'Never'; ?></div>
                        </div>
                        <div>
                            <strong style="color: #666;">User Role</strong>
                            <div><?php echo ucfirst($user['role']); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation
document.querySelector('form').addEventListener('submit', function(e) {
    const firstName = document.getElementById('first_name').value.trim();
    const lastName = document.getElementById('last_name').value.trim();
    const email = document.getElementById('email').value.trim();
    
    if (!firstName || !lastName || !email) {
        e.preventDefault();
        alert('Please fill in all required fields (marked with *).');
        return false;
    }
    
    if (!validateEmail(email)) {
        e.preventDefault();
        alert('Please enter a valid email address.');
        return false;
    }
});

function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// Phone number formatting (optional)
document.getElementById('phone').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length > 0) {
        value = value.match(/.{1,4}/g).join(' ');
    }
    e.target.value = value;
});
</script>

<?php
require_once 'includes/footer.php';
?>