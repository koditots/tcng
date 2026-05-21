<?php
// booking-history.php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$page_title = "Booking History";

$user_id = $_SESSION['user_id'];

// Pagination setup
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get total number of bookings for pagination
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM flight_bookings WHERE user_id = ?");
$stmt->execute([$user_id]);
$total_bookings = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_bookings / $limit);

// Get bookings with pagination
$stmt = $pdo->prepare("SELECT * FROM flight_bookings WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->bindValue(1, $user_id, PDO::PARAM_INT);
$stmt->bindValue(2, $limit, PDO::PARAM_INT);
$stmt->bindValue(3, $offset, PDO::PARAM_INT);
$stmt->execute();
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get booking statistics
$stmt = $pdo->prepare("SELECT 
    COUNT(*) as total_bookings,
    SUM(CASE WHEN status IN ('confirmed', 'paid') THEN 1 ELSE 0 END) as confirmed_bookings,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_bookings,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings,
    SUM(total_amount) as total_spent
    FROM flight_bookings WHERE user_id = ? AND payment_status = 'paid'");
$stmt->execute([$user_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

require_once 'includes/header.php';
?>

<div class="container">
    <div style="max-width: 1200px; margin: 0 auto;">
        <!-- Header -->
        <div style="display: flex; justify-content: between; align-items: center; margin: 2rem 0;">
            <a href="dashboard.php" class="btn" style="background: #6c757d; color: white; padding: 0.5rem 1rem; text-decoration: none; border-radius: 5px;">
                ← Back to Dashboard
            </a>
            <h1 style="color: #333; margin: 0;">Booking History</h1>
        </div>

        <!-- Statistics Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
            <div style="background: white; padding: 1.5rem; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); text-align: center;">
                <div style="font-size: 2rem; color: #007bff; margin-bottom: 0.5rem;">📋</div>
                <div style="font-size: 1.5rem; font-weight: bold; color: #333;"><?php echo $stats['total_bookings'] ?? 0; ?></div>
                <div style="color: #666;">Total Bookings</div>
            </div>
            
            <div style="background: white; padding: 1.5rem; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); text-align: center;">
                <div style="font-size: 2rem; color: #28a745; margin-bottom: 0.5rem;">✅</div>
                <div style="font-size: 1.5rem; font-weight: bold; color: #333;"><?php echo $stats['confirmed_bookings'] ?? 0; ?></div>
                <div style="color: #666;">Confirmed</div>
            </div>
            
            <div style="background: white; padding: 1.5rem; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); text-align: center;">
                <div style="font-size: 2rem; color: #ffc107; margin-bottom: 0.5rem;">⏳</div>
                <div style="font-size: 1.5rem; font-weight: bold; color: #333;"><?php echo $stats['pending_bookings'] ?? 0; ?></div>
                <div style="color: #666;">Pending</div>
            </div>
            
            <div style="background: white; padding: 1.5rem; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); text-align: center;">
                <div style="font-size: 2rem; color: #dc3545; margin-bottom: 0.5rem;">❌</div>
                <div style="font-size: 1.5rem; font-weight: bold; color: #333;"><?php echo $stats['cancelled_bookings'] ?? 0; ?></div>
                <div style="color: #666;">Cancelled</div>
            </div>
            
            <div style="background: white; padding: 1.5rem; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); text-align: center;">
                <div style="font-size: 2rem; color: #17a2b8; margin-bottom: 0.5rem;">💰</div>
                <div style="font-size: 1.5rem; font-weight: bold; color: #333;">₦<?php echo number_format($stats['total_spent'] ?? 0, 2); ?></div>
                <div style="color: #666;">Total Spent</div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div style="background: white; padding: 1.5rem; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); margin-bottom: 1.5rem;">
            <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                <div>
                    <label style="font-weight: bold; color: #333; margin-right: 0.5rem;">Filter by Status:</label>
                    <select id="statusFilter" onchange="filterBookings()" style="padding: 0.5rem; border: 1px solid #ddd; border-radius: 5px;">
                        <option value="all">All Statuses</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="paid">Paid</option>
                        <option value="pending">Pending</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                
                <div style="flex: 1;">
                    <input type="text" id="searchInput" placeholder="Search by booking reference, destination..." 
                           onkeyup="filterBookings()" style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 5px;">
                </div>
                
                <div>
                    <button onclick="resetFilters()" class="btn" style="background: #6c757d; color: white; padding: 0.5rem 1rem; border: none; border-radius: 5px; cursor: pointer;">
                        Reset
                    </button>
                </div>
            </div>
        </div>

        <!-- Bookings List -->
        <div style="background: white; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); overflow: hidden;">
            <!-- Table Header -->
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr auto; gap: 1rem; padding: 1rem 1.5rem; background: #f8f9fa; border-bottom: 1px solid #dee2e6; font-weight: bold; color: #333;">
                <div>Booking Details</div>
                <div>Flight</div>
                <div>Amount</div>
                <div>Status</div>
                <div>Actions</div>
            </div>

            <!-- Bookings -->
            <div id="bookingsList">
                <?php if (empty($bookings)): ?>
                    <div style="padding: 3rem; text-align: center; color: #666;">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">📭</div>
                        <h3 style="color: #333; margin-bottom: 0.5rem;">No Bookings Found</h3>
                        <p>You haven't made any bookings yet.</p>
                        <a href="index.php" class="btn btn-primary" style="background: #007bff; color: white; padding: 0.75rem 1.5rem; text-decoration: none; border-radius: 5px; margin-top: 1rem; display: inline-block;">
                            Book Your First Flight
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($bookings as $booking): ?>
                        <?php
                        $flight_data = json_decode($booking['flight_data'], true);
                        $itinerary = $flight_data['itineraries'][0];
                        $first_segment = $itinerary['segments'][0];
                        $last_segment = end($itinerary['segments']);
                        ?>
                        <div class="booking-item" style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr auto; gap: 1rem; padding: 1.5rem; border-bottom: 1px solid #f8f9fa; align-items: center; transition: background-color 0.3s;" 
                             data-status="<?php echo $booking['status']; ?>"
                             data-reference="<?php echo strtolower($booking['booking_reference']); ?>"
                             data-destination="<?php echo strtolower($last_segment['arrival']['iataCode']); ?>">
                            
                            <!-- Booking Details -->
                            <div>
                                <div style="font-weight: bold; color: #333; margin-bottom: 0.25rem;">
                                    #<?php echo $booking['booking_reference']; ?>
                                </div>
                                <div style="color: #666; font-size: 0.9rem;">
                                    <?php echo date('M j, Y', strtotime($booking['created_at'])); ?>
                                </div>
                                <?php if ($booking['ticket_number']): ?>
                                    <div style="color: #007bff; font-size: 0.8rem; margin-top: 0.25rem;">
                                        Ticket: <?php echo $booking['ticket_number']; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Flight Information -->
                            <div>
                                <div style="font-weight: bold; color: #333; margin-bottom: 0.25rem;">
                                    <?php echo $first_segment['departure']['iataCode']; ?> → <?php echo $last_segment['arrival']['iataCode']; ?>
                                </div>
                                <div style="color: #666; font-size: 0.9rem;">
                                    <?php echo date('M j', strtotime($first_segment['departure']['at'])); ?> • 
                                    <?php echo substr($itinerary['duration'], 2); ?>
                                </div>
                                <div style="color: #666; font-size: 0.8rem;">
                                    <?php echo $first_segment['carrierCode']; ?>
                                </div>
                            </div>
                            
                            <!-- Amount -->
                            <div>
                                <div style="font-weight: bold; color: #333; margin-bottom: 0.25rem;">
                                    ₦<?php echo number_format($booking['total_amount'], 2); ?>
                                </div>
                                <div style="color: #666; font-size: 0.9rem;">
                                    <?php echo ucfirst($booking['payment_method'] ?? 'N/A'); ?>
                                </div>
                                <div style="color: <?php echo $booking['payment_status'] === 'paid' ? '#28a745' : '#ffc107'; ?>; font-size: 0.8rem;">
                                    <?php echo strtoupper($booking['payment_status']); ?>
                                </div>
                            </div>
                            
                            <!-- Status -->
                            <div>
                                <span class="status-badge" style="padding: 0.5rem 1rem; border-radius: 15px; font-weight: bold; font-size: 0.8rem; display: inline-block; background: 
                                    <?php 
                                        switch($booking['status']) {
                                            case 'confirmed': echo '#d4edda'; break;
                                            case 'paid': echo '#d4edda'; break;
                                            case 'pending': echo '#fff3cd'; break;
                                            case 'cancelled': echo '#f8d7da'; break;
                                            default: echo '#e2e3e5'; break;
                                        }
                                    ?>; color: 
                                    <?php 
                                        switch($booking['status']) {
                                            case 'confirmed': echo '#155724'; break;
                                            case 'paid': echo '#155724'; break;
                                            case 'pending': echo '#856404'; break;
                                            case 'cancelled': echo '#721c24'; break;
                                            default: echo '#383d41'; break;
                                        }
                                    ?>;">
                                    <?php echo strtoupper($booking['status']); ?>
                                </span>
                            </div>
                            
                            <!-- Actions -->
                            <div style="display: flex; gap: 0.5rem;">
                                <a href="booking-details.php?id=<?php echo $booking['id']; ?>" class="btn" style="background: #007bff; color: white; padding: 0.5rem 1rem; text-decoration: none; border-radius: 5px; font-size: 0.8rem;">
                                    View
                                </a>
                                <?php if ($booking['status'] === 'confirmed' || $booking['status'] === 'paid'): ?>
                                    <a href="print-ticket.php?booking_id=<?php echo $booking['id']; ?>" target="_blank" class="btn" style="background: #28a745; color: white; padding: 0.5rem 1rem; text-decoration: none; border-radius: 5px; font-size: 0.8rem;">
                                        Print
                                    </a>
                                <?php endif; ?>
                                <?php if ($booking['status'] === 'pending' && $booking['payment_status'] === 'pending'): ?>
                                    <a href="payment.php?booking_id=<?php echo $booking['id']; ?>" class="btn" style="background: #ffc107; color: #212529; padding: 0.5rem 1rem; text-decoration: none; border-radius: 5px; font-size: 0.8rem;">
                                        Pay
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
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
                </div>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>" class="btn" style="background: #6c757d; color: white; padding: 0.5rem 1rem; text-decoration: none; border-radius: 5px;">
                        Next →
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function filterBookings() {
    const statusFilter = document.getElementById('statusFilter').value;
    const searchInput = document.getElementById('searchInput').value.toLowerCase();
    const bookingItems = document.querySelectorAll('.booking-item');
    
    let visibleCount = 0;
    
    bookingItems.forEach(item => {
        const status = item.getAttribute('data-status');
        const reference = item.getAttribute('data-reference');
        const destination = item.getAttribute('data-destination');
        
        const statusMatch = statusFilter === 'all' || status === statusFilter;
        const searchMatch = !searchInput || 
                           reference.includes(searchInput) || 
                           destination.includes(searchInput);
        
        if (statusMatch && searchMatch) {
            item.style.display = 'grid';
            visibleCount++;
        } else {
            item.style.display = 'none';
        }
    });
    
    // Show no results message if no items are visible
    const noResults = document.getElementById('noResults');
    if (visibleCount === 0 && bookingItems.length > 0) {
        if (!noResults) {
            const noResultsDiv = document.createElement('div');
            noResultsDiv.id = 'noResults';
            noResultsDiv.style.padding = '3rem';
            noResultsDiv.style.textAlign = 'center';
            noResultsDiv.style.color = '#666';
            noResultsDiv.innerHTML = `
                <div style="font-size: 3rem; margin-bottom: 1rem;">🔍</div>
                <h3 style="color: #333; margin-bottom: 0.5rem;">No Bookings Found</h3>
                <p>No bookings match your current filters.</p>
            `;
            document.getElementById('bookingsList').appendChild(noResultsDiv);
        }
    } else if (noResults) {
        noResults.remove();
    }
}

function resetFilters() {
    document.getElementById('statusFilter').value = 'all';
    document.getElementById('searchInput').value = '';
    filterBookings();
}

// Initialize filters on page load
document.addEventListener('DOMContentLoaded', function() {
    filterBookings();
});
</script>

<style>
.booking-item:hover {
    background-color: #f8f9fa;
}

@media (max-width: 768px) {
    .booking-item {
        grid-template-columns: 1fr !important;
        gap: 1rem !important;
        text-align: center;
    }
    
    .booking-item > div {
        display: flex;
        flex-direction: column;
        align-items: center;
    }
    
    .booking-item .status-badge {
        margin: 0.5rem 0;
    }
}
</style>

<?php
require_once 'includes/footer.php';
?>