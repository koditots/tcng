<?php
// notifications.php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$page_title = "Notifications";

$user_id = $_SESSION['user_id'];

// Mark notification as read if ID is provided
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $notification_id = intval($_GET['mark_read']);
    
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$notification_id, $user_id]);
    
    // Redirect to avoid resubmission
    redirect('notifications.php');
}

// Mark all as read
if (isset($_GET['mark_all_read'])) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    
    // Redirect to avoid resubmission
    redirect('notifications.php');
}

// Clear all notifications
if (isset($_GET['clear_all'])) {
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    // Redirect to avoid resubmission
    redirect('notifications.php');
}

// Delete single notification
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $notification_id = intval($_GET['delete']);
    
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
    $stmt->execute([$notification_id, $user_id]);
    
    // Redirect to avoid resubmission
    redirect('notifications.php');
}

// Pagination setup
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Get total number of notifications for pagination
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ?");
$stmt->execute([$user_id]);
$total_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_notifications / $limit);

// Get unread count
$stmt = $pdo->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->execute([$user_id]);
$unread_count = $stmt->fetch(PDO::FETCH_ASSOC)['unread_count'];

// Get notifications with pagination
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->bindValue(1, $user_id, PDO::PARAM_INT);
$stmt->bindValue(2, $limit, PDO::PARAM_INT);
$stmt->bindValue(3, $offset, PDO::PARAM_INT);
$stmt->execute();
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once 'includes/header.php';
?>

<div class="container">
    <div style="max-width: 800px; margin: 0 auto;">
        <!-- Header -->
        <div style="display: flex; justify-content: between; align-items: center; margin: 2rem 0;">
            <a href="dashboard.php" class="btn" style="background: #6c757d; color: white; padding: 0.5rem 1rem; text-decoration: none; border-radius: 5px;">
                ← Back to Dashboard
            </a>
            <h1 style="color: #333; margin: 0;">Notifications</h1>
        </div>

        <!-- Statistics and Actions -->
        <div style="display: grid; grid-template-columns: 1fr auto; gap: 1.5rem; margin-bottom: 2rem;">
            <!-- Statistics -->
            <div style="display: flex; gap: 1.5rem; align-items: center;">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <div style="width: 12px; height: 12px; background: #007bff; border-radius: 50%;"></div>
                    <span style="color: #666;">Total: <strong><?php echo $total_notifications; ?></strong></span>
                </div>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <div style="width: 12px; height: 12px; background: #dc3545; border-radius: 50%;"></div>
                    <span style="color: #666;">Unread: <strong><?php echo $unread_count; ?></strong></span>
                </div>
            </div>

            <!-- Action Buttons -->
            <div style="display: flex; gap: 0.5rem;">
                <?php if ($unread_count > 0): ?>
                    <a href="?mark_all_read=1" class="btn" style="background: #28a745; color: white; padding: 0.5rem 1rem; text-decoration: none; border-radius: 5px; font-size: 0.8rem;">
                        Mark All Read
                    </a>
                <?php endif; ?>
                <?php if ($total_notifications > 0): ?>
                    <a href="?clear_all=1" class="btn" style="background: #dc3545; color: white; padding: 0.5rem 1rem; text-decoration: none; border-radius: 5px; font-size: 0.8rem;"
                       onclick="return confirm('Are you sure you want to clear all notifications? This action cannot be undone.')">
                        Clear All
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Notifications List -->
        <div style="background: white; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); overflow: hidden;">
            <?php if (empty($notifications)): ?>
                <div style="padding: 4rem 2rem; text-align: center; color: #666;">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">🔔</div>
                    <h3 style="color: #333; margin-bottom: 0.5rem;">No Notifications</h3>
                    <p>You don't have any notifications yet.</p>
                    <p style="font-size: 0.9rem; margin-top: 1rem;">We'll notify you here about important updates, booking confirmations, visa applications, and more.</p>
                </div>
            <?php else: ?>
                <!-- Notifications Header -->
                <div style="display: grid; grid-template-columns: auto 1fr auto; gap: 1rem; padding: 1rem 1.5rem; background: #f8f9fa; border-bottom: 1px solid #dee2e6; font-weight: bold; color: #333;">
                    <div style="min-width: 40px;">Status</div>
                    <div>Notification</div>
                    <div style="min-width: 120px; text-align: center;">Actions</div>
                </div>

                <!-- Notifications -->
                <div id="notificationsList">
                    <?php foreach ($notifications as $notification): ?>
                        <div class="notification-item" style="display: grid; grid-template-columns: auto 1fr auto; gap: 1rem; padding: 1.5rem; border-bottom: 1px solid #f8f9fa; align-items: center; transition: background-color 0.3s; <?php echo !$notification['is_read'] ? 'background: #f0f8ff;' : ''; ?>">
                            
                            <!-- Status Indicator -->
                            <div style="min-width: 40px; text-align: center;">
                                <?php if (!$notification['is_read']): ?>
                                    <div style="width: 12px; height: 12px; background: #dc3545; border-radius: 50%; margin: 0 auto;"></div>
                                <?php else: ?>
                                    <div style="color: #28a745; font-size: 1.2rem;">✓</div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Notification Content -->
                            <div>
                                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                                    <!-- Type Icon -->
                                    <div style="font-size: 1.2rem;">
                                        <?php 
                                        switch($notification['type']) {
                                            case 'success': echo '✅'; break;
                                            case 'warning': echo '⚠️'; break;
                                            case 'error': echo '❌'; break;
                                            case 'info': echo 'ℹ️'; break;
                                            default: echo '🔔'; break;
                                        }
                                        ?>
                                    </div>
                                    
                                    <!-- Title -->
                                    <div style="font-weight: bold; color: #333; font-size: 1.1rem;">
                                        <?php echo htmlspecialchars($notification['title']); ?>
                                    </div>
                                    
                                    <!-- Type Badge -->
                                    <span style="padding: 0.25rem 0.5rem; border-radius: 10px; font-size: 0.7rem; font-weight: bold; background: 
                                        <?php 
                                            switch($notification['type']) {
                                                case 'success': echo '#d4edda'; break;
                                                case 'warning': echo '#fff3cd'; break;
                                                case 'error': echo '#f8d7da'; break;
                                                case 'info': echo '#e7f3ff'; break;
                                                default: echo '#e2e3e5'; break;
                                            }
                                        ?>; color: 
                                        <?php 
                                            switch($notification['type']) {
                                                case 'success': echo '#155724'; break;
                                                case 'warning': echo '#856404'; break;
                                                case 'error': echo '#721c24'; break;
                                                case 'info': echo '#0056b3'; break;
                                                default: echo '#383d41'; break;
                                            }
                                        ?>;">
                                        <?php echo strtoupper($notification['type']); ?>
                                    </span>
                                </div>
                                
                                <!-- Message -->
                                <div style="color: #666; margin-bottom: 0.5rem; line-height: 1.5;">
                                    <?php echo htmlspecialchars($notification['message']); ?>
                                </div>
                                
                                <!-- Metadata -->
                                <div style="display: flex; gap: 1rem; font-size: 0.8rem; color: #999;">
                                    <span>📅 <?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?></span>
                                    <?php if ($notification['related_type'] && $notification['related_id']): ?>
                                        <span>🔗 Related to <?php echo ucfirst($notification['related_type']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Actions -->
                            <div style="display: flex; gap: 0.5rem; min-width: 120px; justify-content: center; flex-wrap: wrap;">
                                <?php if (!$notification['is_read']): ?>
                                    <a href="?mark_read=<?php echo $notification['id']; ?>" class="btn" style="background: #28a745; color: white; padding: 0.5rem 0.75rem; text-decoration: none; border-radius: 5px; font-size: 0.7rem;">
                                        Mark Read
                                    </a>
                                <?php endif; ?>
                                
                                <!-- Related Action - Booking -->
                                <?php if ($notification['related_type'] === 'booking' && $notification['related_id']): ?>
                                    <a href="booking-details.php?id=<?php echo $notification['related_id']; ?>" class="btn" style="background: #007bff; color: white; padding: 0.5rem 0.75rem; text-decoration: none; border-radius: 5px; font-size: 0.7rem;">
                                        View Booking
                                    </a>
                                <?php endif; ?>
                                
                                <!-- Related Action - Visa Application -->
                                <?php if ($notification['related_type'] === 'visa' && $notification['related_id']): ?>
                                    <?php
                                    // Check if this is a payment-related visa notification
                                    $is_payment_notification = strpos(strtolower($notification['title']), 'payment') !== false || 
                                                              strpos(strtolower($notification['message']), 'payment') !== false;
                                    
                                    // Check if this is an approval notification
                                    $is_approval_notification = strpos(strtolower($notification['title']), 'approved') !== false || 
                                                              strpos(strtolower($notification['message']), 'approved') !== false;
                                    ?>
                                    
                                    <?php if ($is_payment_notification): ?>
                                        <a href="visa-payment.php?application_id=<?php echo $notification['related_id']; ?>" class="btn" style="background: #6f42c1; color: white; padding: 0.5rem 0.75rem; text-decoration: none; border-radius: 5px; font-size: 0.7rem;">
                                            Make Payment
                                        </a>
                                    <?php elseif ($is_approval_notification): ?>
                                        <a href="visa-application-details.php?id=<?php echo $notification['related_id']; ?>" class="btn" style="background: #28a745; color: white; padding: 0.5rem 0.75rem; text-decoration: none; border-radius: 5px; font-size: 0.7rem;">
                                            View Approval
                                        </a>
                                    <?php else: ?>
                                        <a href="visa-application-details.php?id=<?php echo $notification['related_id']; ?>" class="btn" style="background: #17a2b8; color: white; padding: 0.5rem 0.75rem; text-decoration: none; border-radius: 5px; font-size: 0.7rem;">
                                            View Application
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <!-- Related Action - Payment -->
                                <?php if ($notification['related_type'] === 'payment' && $notification['related_id']): ?>
                                    <a href="payment-details.php?id=<?php echo $notification['related_id']; ?>" class="btn" style="background: #fd7e14; color: white; padding: 0.5rem 0.75rem; text-decoration: none; border-radius: 5px; font-size: 0.7rem;">
                                        View Payment
                                    </a>
                                <?php endif; ?>
                                
                                <!-- Delete -->
                                <a href="?delete=<?php echo $notification['id']; ?>" class="btn" style="background: #dc3545; color: white; padding: 0.5rem 0.75rem; text-decoration: none; border-radius: 5px; font-size: 0.7rem;"
                                   onclick="return confirm('Are you sure you want to delete this notification?')">
                                    Delete
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div style="display: flex; justify-content: center; align-items: center; margin-top: 2rem; gap: 1rem;">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>" class="btn" style="background: #6c757d; color: white; padding: 0.5rem 1rem; text-decoration: none; border-radius: 5px;">
                        ← Previous
                    </a>
                <?php endif; ?>
                
                <div style="color: #666;">
                    Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                    (<?php echo $total_notifications; ?> total notifications)
                </div>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>" class="btn" style="background: #6c757d; color: white; padding: 0.5rem 1rem; text-decoration: none; border-radius: 5px;">
                        Next →
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Notification Types Info -->
        <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 10px; margin-top: 2rem;">
            <h4 style="color: #333; margin-bottom: 1rem;">📋 Notification Types & Actions</h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <span style="color: #28a745;">✅</span>
                    <span><strong>Success:</strong> Booking confirmations, payments, visa approvals</span>
                </div>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <span style="color: #ffc107;">⚠️</span>
                    <span><strong>Warning:</strong> Payment reminders, visa updates</span>
                </div>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <span style="color: #dc3545;">❌</span>
                    <span><strong>Error:</strong> Payment failures, visa issues</span>
                </div>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <span style="color: #007bff;">ℹ️</span>
                    <span><strong>Info:</strong> General updates, visa status changes</span>
                </div>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <span style="color: #6f42c1;">🛂</span>
                    <span><strong>Visa:</strong> Application updates, payment reminders</span>
                </div>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <span style="color: #fd7e14;">💳</span>
                    <span><strong>Payment:</strong> Transaction confirmations, failures</span>
                </div>
            </div>
        </div>

        <!-- Quick Tips -->
        <div style="background: #e7f3ff; padding: 1.5rem; border-radius: 10px; margin-top: 1.5rem;">
            <h4 style="color: #0056b3; margin-bottom: 1rem;">💡 Quick Tips</h4>
            <div style="display: grid; gap: 0.5rem;">
                <div style="display: flex; align-items: start; gap: 0.5rem;">
                    <span style="color: #007bff;">•</span>
                    <span>Click "Mark Read" to clear unread indicators</span>
                </div>
                <div style="display: flex; align-items: start; gap: 0.5rem;">
                    <span style="color: #007bff;">•</span>
                    <span>Use "Mark All Read" to quickly clear all unread notifications</span>
                </div>
                <div style="display: flex; align-items: start; gap: 0.5rem;">
                    <span style="color: #007bff;">•</span>
                    <span>Delete notifications you no longer need to keep your inbox clean</span>
                </div>
                <div style="display: flex; align-items: start; gap: 0.5rem;">
                    <span style="color: #007bff;">•</span>
                    <span>Click "View Booking" on booking-related notifications to see details</span>
                </div>
                <div style="display: flex; align-items: start; gap: 0.5rem;">
                    <span style="color: #007bff;">•</span>
                    <span>Click "Make Payment" on visa payment notifications to complete payment</span>
                </div>
                <div style="display: flex; align-items: start; gap: 0.5rem;">
                    <span style="color: #007bff;">•</span>
                    <span>Click "View Application" on visa notifications to check status</span>
                </div>
            </div>
        </div>

        <!-- Notification Categories -->
        <div style="background: #fff3cd; padding: 1.5rem; border-radius: 10px; margin-top: 1.5rem;">
            <h4 style="color: #856404; margin-bottom: 1rem;">📊 Notification Categories</h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <div style="text-align: center; padding: 1rem; background: white; border-radius: 8px; border-left: 4px solid #007bff;">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">✈️</div>
                    <strong>Flight Bookings</strong>
                    <p style="margin: 0.5rem 0 0 0; font-size: 0.8rem; color: #666;">Booking confirmations, updates</p>
                </div>
                <div style="text-align: center; padding: 1rem; background: white; border-radius: 8px; border-left: 4px solid #6f42c1;">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">🛂</div>
                    <strong>Visa Applications</strong>
                    <p style="margin: 0.5rem 0 0 0; font-size: 0.8rem; color: #666;">Status updates, payments</p>
                </div>
                <div style="text-align: center; padding: 1rem; background: white; border-radius: 8px; border-left: 4px solid #28a745;">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">💳</div>
                    <strong>Payments</strong>
                    <p style="margin: 0.5rem 0 0 0; font-size: 0.8rem; color: #666;">Payment confirmations, failures</p>
                </div>
                <div style="text-align: center; padding: 1rem; background: white; border-radius: 8px; border-left: 4px solid #17a2b8;">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">🔔</div>
                    <strong>System Updates</strong>
                    <p style="margin: 0.5rem 0 0 0; font-size: 0.8rem; color: #666;">General information, news</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-refresh notifications every 30 seconds (optional)
setTimeout(function() {
    location.reload();
}, 30000);

// Add hover effects
document.querySelectorAll('.notification-item').forEach(item => {
    item.addEventListener('mouseenter', function() {
        this.style.backgroundColor = '#f8f9fa';
    });
    
    item.addEventListener('mouseleave', function() {
        const isUnread = this.style.backgroundColor === 'rgb(240, 248, 255)';
        this.style.backgroundColor = isUnread ? '#f0f8ff' : 'white';
    });
});

// Mark as read on click (optional enhancement)
document.querySelectorAll('.notification-item').forEach(item => {
    item.addEventListener('click', function(e) {
        // Don't trigger if user clicked on a link
        if (e.target.tagName === 'A' || e.target.closest('a')) {
            return;
        }
        
        const markReadLink = this.querySelector('a[href*="mark_read"]');
        if (markReadLink) {
            window.location.href = markReadLink.href;
        }
    });
});

// Filter notifications by type (optional enhancement)
function filterNotifications(type) {
    const notifications = document.querySelectorAll('.notification-item');
    notifications.forEach(notification => {
        const typeElement = notification.querySelector('[style*="background:"]');
        if (type === 'all' || typeElement.textContent.includes(type.toUpperCase())) {
            notification.style.display = 'grid';
        } else {
            notification.style.display = 'none';
        }
    });
}

// Quick action for visa payment notifications
document.addEventListener('DOMContentLoaded', function() {
    // Highlight important visa notifications
    const visaNotifications = document.querySelectorAll('.notification-item');
    visaNotifications.forEach(notification => {
        const title = notification.querySelector('div[style*="font-weight: bold"]').textContent;
        if (title.includes('Payment') || title.includes('Urgent') || title.includes('Important')) {
            notification.style.borderLeft = '4px solid #dc3545';
        }
    });
});
</script>

<style>
.notification-item {
    cursor: pointer;
}

.notification-item:hover {
    background-color: #f8f9fa !important;
}

.btn {
    transition: all 0.3s ease;
}

.btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}

/* Visa-specific button styles */
.btn[style*="background: #6f42c1"]:hover {
    background: #5a359c !important;
}

.btn[style*="background: #17a2b8"]:hover {
    background: #138496 !important;
}

.btn[style*="background: #fd7e14"]:hover {
    background: #e56a00 !important;
}

@media (max-width: 768px) {
    .notification-item {
        grid-template-columns: 1fr !important;
        gap: 1rem !important;
        text-align: center;
    }
    
    .notification-item > div:first-child {
        justify-content: center;
    }
    
    .notification-item > div:last-child {
        justify-content: center;
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .notification-item .btn {
        font-size: 0.7rem;
        padding: 0.4rem 0.6rem;
        width: 100%;
        max-width: 120px;
    }
}

/* Animation for new notifications */
@keyframes highlightNew {
    0% { background-color: #ffffcc; }
    100% { background-color: #f0f8ff; }
}

.notification-item:first-child {
    animation: highlightNew 2s ease-in-out;
}
</style>

<?php
require_once 'includes/footer.php';
?>