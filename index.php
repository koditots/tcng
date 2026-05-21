<?php
// index.php
$page_title = "Home - Book Flights, Hotels & More";
require_once 'includes/header.php';

// Generate CSRF token for form protection
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$csrf_token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;
?>

<div class="wp-style-container">
    <!-- Hero Section with Sliding Background -->
    <section class="wp-hero-section">
        <div class="wp-hero-background">
            <div class="wp-hero-slides">
                <div class="wp-hero-slide active" style="background-image: url('https://images.unsplash.com/photo-1488646953014-85cb44e25828?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80')"></div>
                <div class="wp-hero-slide" style="background-image: url('https://images.unsplash.com/photo-1436491865332-7a61a109cc05?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80')"></div>
                <div class="wp-hero-slide" style="background-image: url('https://images.unsplash.com/photo-1503220317375-aaad61436b1b?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80')"></div>
                <div class="wp-hero-slide" style="background-image: url('https://images.unsplash.com/photo-1506929562872-bb421503ef21?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80')"></div>
            </div>
            <div class="wp-hero-overlay"></div>
        </div>
        <div class="wp-hero-content">
            <div class="wp-container">
                <div class="wp-hero-text">
                    <h1 class="wp-hero-title">Discover Amazing Destinations</h1>
                    <p class="wp-hero-subtitle">Book flights, hotels, and travel packages at the best prices with <?php echo getSiteSetting($pdo, 'site_name'); ?></p>
                    
                    <!-- Trip.com Style Search Tabs -->
                    <div class="wp-search-tabs-container">
                        <div class="wp-search-tabs">
                            <button class="wp-tab-btn active" data-tab="flights">
                                <span class="wp-tab-icon">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M21 16v-2l-8-5V3.5c0-.83-.67-1.5-1.5-1.5S10 2.67 10 3.5V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5l8 2.5z"/>
                                    </svg>
                                </span>
                                Flights
                            </button>
                            <a href="https://bookhotels.ng/" class="wp-tab-btn" target="_blank">
                                <span class="wp-tab-icon">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M7 13c1.66 0 3-1.34 3-3S8.66 7 7 7s-3 1.34-3 3 1.34 3 3 3zm12-6h-8v7H3V7H1v10h22v-6c0-2.21-1.79-4-4-4z"/>
                                    </svg>
                                </span>
                                Hotels
                            </a>
                            <a href="/visa-application.php" class="wp-tab-btn">
                                <span class="wp-tab-icon">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/>
                                    </svg>
                                </span>
                                Visa Application
                            </a>
                        </div>
                        
                        <!-- Flight Search Form -->
                        <div class="wp-tab-content active" id="flights-tab">
                            <form id="flightSearchForm" action="flights.php" method="GET" class="wp-search-form">
                                <!-- CSRF Protection -->
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                
                                <!-- Simplified Trip Type Selection - SWITCHED ORDER AND DEFAULT -->
                                <div class="wp-trip-type-selector">
                                    <div class="wp-radio-group">
                                        <label class="wp-radio-label">
                                            <input type="radio" name="trip_type" value="one_way" checked class="wp-radio-input">
                                            <span class="wp-radio-custom"></span>
                                            One-way
                                        </label>
                                        <label class="wp-radio-label">
                                            <input type="radio" name="trip_type" value="round_trip" class="wp-radio-input">
                                            <span class="wp-radio-custom"></span>
                                            Round-trip
                                        </label>
                                    </div>
                                </div>
                                
                                <!-- Main Search Fields -->
                                <div class="wp-search-fields-grid">
                                    <!-- From Field -->
                                    <div class="wp-search-field">
                                        <label class="wp-field-label">Leaving from</label>
                                        <div class="wp-input-with-icon">
                                            <span class="wp-input-icon">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                                    <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                                                </svg>
                                            </span>
                                            <input type="text" name="origin" id="origin" placeholder="City or airport" required class="wp-form-control">
                                            <div class="wp-loading-spinner" id="originLoading" style="display: none;"></div>
                                        </div>
                                        <div id="originSuggestions" class="wp-suggestions-box"></div>
                                    </div>
                                    
                                    <!-- To Field -->
                                    <div class="wp-search-field">
                                        <label class="wp-field-label">Going to</label>
                                        <div class="wp-input-with-icon">
                                            <span class="wp-input-icon">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                                    <path d="M21 16v-2l-8-5V3.5c0-.83-.67-1.5-1.5-1.5S10 2.67 10 3.5V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5l8 2.5z"/>
                                                </svg>
                                            </span>
                                            <input type="text" name="destination" id="destination" placeholder="City or airport" required class="wp-form-control">
                                            <div class="wp-loading-spinner" id="destinationLoading" style="display: none;"></div>
                                        </div>
                                        <div id="destinationSuggestions" class="wp-suggestions-box"></div>
                                    </div>
                                    
                                    <!-- Departure Date -->
                                    <div class="wp-search-field">
                                        <label class="wp-field-label">Departure</label>
                                        <div class="wp-input-with-icon">
                                            <span class="wp-input-icon">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                                    <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/>
                                                </svg>
                                            </span>
                                            <input type="date" name="departure_date" id="departureDate" placeholder="Select date" required class="wp-form-control">
                                        </div>
                                    </div>
                                    
                                    <!-- Return Date -->
                                    <div class="wp-search-field" id="returnDateField" style="display: none;">
                                        <label class="wp-field-label">Return</label>
                                        <div class="wp-input-with-icon">
                                            <span class="wp-input-icon">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                                    <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/>
                                                </svg>
                                            </span>
                                            <input type="date" name="return_date" id="returnDate" placeholder="Select date" class="wp-form-control">
                                        </div>
                                    </div>
                                    
                                    <!-- Passengers & Class - UPDATED FOR FULL DISPLAY -->
                                    <div class="wp-search-field">
                                        <label class="wp-field-label">Travelers & Class</label>
                                        <div class="wp-passenger-selector" id="passengerSelector">
                                            <div class="wp-passenger-display">
                                                <span class="wp-passenger-full-display">1 adult, Economy</span>
                                            </div>
                                            <span class="wp-dropdown-arrow">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                                    <path d="M7 10l5 5 5-5z"/>
                                                </svg>
                                            </span>
                                        </div>
                                        <div class="wp-passenger-modal" id="passengerModal">
                                            <div class="wp-passenger-options">
                                                <div class="wp-passenger-type">
                                                    <div class="wp-passenger-info">
                                                        <strong>Adults</strong>
                                                        <span>Ages 16+</span>
                                                    </div>
                                                    <div class="wp-passenger-controls">
                                                        <button type="button" class="wp-passenger-btn" data-type="adults" data-action="decrease">−</button>
                                                        <span class="wp-passenger-number" data-type="adults">1</span>
                                                        <button type="button" class="wp-passenger-btn" data-type="adults" data-action="increase">+</button>
                                                    </div>
                                                </div>
                                                <div class="wp-passenger-type">
                                                    <div class="wp-passenger-info">
                                                        <strong>Children</strong>
                                                        <span>Ages 2-15</span>
                                                    </div>
                                                    <div class="wp-passenger-controls">
                                                        <button type="button" class="wp-passenger-btn" data-type="children" data-action="decrease">−</button>
                                                        <span class="wp-passenger-number" data-type="children">0</span>
                                                        <button type="button" class="wp-passenger-btn" data-type="children" data-action="increase">+</button>
                                                    </div>
                                                </div>
                                                <div class="wp-passenger-type">
                                                    <div class="wp-passenger-info">
                                                        <strong>Infants</strong>
                                                        <span>Under 2</span>
                                                    </div>
                                                    <div class="wp-passenger-controls">
                                                        <button type="button" class="wp-passenger-btn" data-type="infants" data-action="decrease">−</button>
                                                        <span class="wp-passenger-number" data-type="infants">0</span>
                                                        <button type="button" class="wp-passenger-btn" data-type="infants" data-action="increase">+</button>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="wp-class-options">
                                                <label class="wp-class-option">
                                                    <input type="radio" name="travel_class" value="ECONOMY" checked class="wp-class-radio">
                                                    <span class="wp-class-custom"></span>
                                                    Economy
                                                </label>
                                                <label class="wp-class-option">
                                                    <input type="radio" name="travel_class" value="PREMIUM_ECONOMY" class="wp-class-radio">
                                                    <span class="wp-class-custom"></span>
                                                    Premium Economy
                                                </label>
                                                <label class="wp-class-option">
                                                    <input type="radio" name="travel_class" value="BUSINESS" class="wp-class-radio">
                                                    <span class="wp-class-custom"></span>
                                                    Business
                                                </label>
                                                <label class="wp-class-option">
                                                    <input type="radio" name="travel_class" value="FIRST" class="wp-class-radio">
                                                    <span class="wp-class-custom"></span>
                                                    First Class
                                                </label>
                                            </div>
                                            <div class="wp-passenger-actions">
                                                <button type="button" class="wp-btn wp-btn-primary" id="applyPassengers">Apply</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Action Buttons -->
                                <div class="wp-search-actions">
                                    <button type="submit" class="wp-btn wp-btn-primary wp-search-btn">
                                        <span class="wp-btn-icon">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                                            </svg>
                                        </span>
                                        Search Flights
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Rest of your existing content remains exactly the same -->
    <!-- Main Content Area -->
    <div class="wp-main-content">
        <div class="wp-container">
            <div class="wp-content-area">
                <!-- Quick Stats Section -->
                <section class="wp-stats-section">
                    <div class="wp-stats-grid">
                        <div class="wp-stat-item">
                            <div class="wp-stat-icon">
                                <svg width="40" height="40" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M21 16v-2l-8-5V3.5c0-.83-.67-1.5-1.5-1.5S10 2.67 10 3.5V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5l8 2.5z"/>
                                </svg>
                            </div>
                            <div class="wp-stat-content">
                                <h3 class="wp-stat-number" data-count="5000">0</h3>
                                <p class="wp-stat-label">Flights Daily</p>
                            </div>
                        </div>
                        <div class="wp-stat-item">
                            <div class="wp-stat-icon">
                                <svg width="40" height="40" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/>
                                </svg>
                            </div>
                            <div class="wp-stat-content">
                                <h3 class="wp-stat-number" data-count="150">0</h3>
                                <p class="wp-stat-label">Destinations</p>
                            </div>
                        </div>
                        <div class="wp-stat-item">
                            <div class="wp-stat-icon">
                                <svg width="40" height="40" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M16 4c0-1.11.89-2 2-2s2 .89 2 2-.89 2-2 2-2-.89-2-2zm4 18v-6h2.5l-2.54-7.63C19.68 7.55 18.92 7 18.06 7h-.12c-.86 0-1.63.55-1.9 1.37l-.86 2.58c1.08.6 1.82 1.73 1.82 3.05v8h3zm-7.5-10.5c.28 0 .5.22.5.5s-.22.5-.5.5-.5-.22-.5-.5.22-.5.5-.5zm-5 0c.28 0 .5.22.5.5s-.22.5-.5.5-.5-.22-.5-.5.22-.5.5-.5zM9 12c-2.21 0-4-1.79-4-4s1.79-4 4-4 4 1.79 4 4-1.79 4-4 4zm0-6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm-2 16v-6H5.5l2.54-7.63C8.32 7.55 9.08 7 9.94 7h.12c.86 0 1.63.55 1.9 1.37l.86 2.58c-1.08.6-1.82 1.73-1.82 3.05v8H7z"/>
                                </svg>
                            </div>
                            <div class="wp-stat-content">
                                <h3 class="wp-stat-number" data-count="100000">0</h3>
                                <p class="wp-stat-label">Happy Travelers</p>
                            </div>
                        </div>
                        <div class="wp-stat-item">
                            <div class="wp-stat-icon">
                                <svg width="40" height="40" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
                                </svg>
                            </div>
                            <div class="wp-stat-content">
                                <h3 class="wp-stat-number" data-count="15">0</h3>
                                <p class="wp-stat-label">Years Experience</p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Travel Deals & Tips Section (Former Sidebar Content) -->
                <section class="wp-sidebar-content-section">
                    <div class="wp-sidebar-content-grid">
                        <!-- Travel Deals Widget -->
                        <div class="wp-content-widget">
                            <h3 class="wp-widget-title">Travel Deals</h3>
                            <div class="wp-widget-content">
                                <ul class="wp-deals-list">
                                    <li>
                                        <span class="wp-deal-badge">Hot</span>
                                        <a href="flights.php?origin=LOS&destination=DXB" class="wp-deal-link">Lagos to Dubai from ₦280,000</a>
                                    </li>
                                    <li>
                                        <span class="wp-deal-badge">New</span>
                                        <a href="flights.php?origin=ABV&destination=LHR" class="wp-deal-link">Abuja to London from ₦350,000</a>
                                    </li>
                                    <li>
                                        <span class="wp-deal-badge">Sale</span>
                                        <a href="flights.php?origin=LOS&destination=ABV" class="wp-deal-link">Domestic flights from ₦25,000</a>
                                    </li>
                                    <li>
                                        <span class="wp-deal-badge">Limited</span>
                                        <a href="flights.php?class=BUSINESS" class="wp-deal-link">Business Class Special Offers</a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        
                        <!-- Travel Tips Widget -->
                        <div class="wp-content-widget">
                            <h3 class="wp-widget-title">Travel Tips</h3>
                            <div class="wp-widget-content">
                                <div class="wp-tips-list">
                                    <div class="wp-tip-item">
                                        <h4>Book in Advance</h4>
                                        <p>Save up to 40% by booking your flights 2-3 months ahead.</p>
                                    </div>
                                    <div class="wp-tip-item">
                                        <h4>Flexible Dates</h4>
                                        <p>Be flexible with your travel dates to find the best deals.</p>
                                    </div>
                                    <div class="wp-tip-item">
                                        <h4>Travel Insurance</h4>
                                        <p>Protect your trip with comprehensive travel insurance.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Popular Airlines Widget -->
                        <div class="wp-content-widget">
                            <h3 class="wp-widget-title">Popular Airlines</h3>
                            <div class="wp-widget-content">
                                <div class="wp-airlines-grid">
                                    <div class="wp-airline-item">
                                        <img src="https://images.kiwi.com/airlines/64/P4.png" alt="Air Peace" class="wp-airline-logo">
                                        <span class="wp-airline-name">Air Peace</span>
                                    </div>
                                    <div class="wp-airline-item">
                                        <img src="https://images.kiwi.com/airlines/64/ET.png" alt="Ethiopian Airlines" class="wp-airline-logo">
                                        <span class="wp-airline-name">Ethiopian Airlines</span>
                                    </div>
                                    <div class="wp-airline-item">
                                        <img src="https://images.kiwi.com/airlines/64/QR.png" alt="Qatar Airways" class="wp-airline-logo">
                                        <span class="wp-airline-name">Qatar Airways</span>
                                    </div>
                                    <div class="wp-airline-item">
                                        <img src="https://images.kiwi.com/airlines/64/EK.png" alt="Emirates" class="wp-airline-logo">
                                        <span class="wp-airline-name">Emirates</span>
                                    </div>
                                    <div class="wp-airline-item">
                                        <img src="https://images.kiwi.com/airlines/64/TK.png" alt="Turkish Airlines" class="wp-airline-logo">
                                        <span class="wp-airline-name">Turkish Airlines</span>
                                    </div>
                                    <div class="wp-airline-item">
                                        <img src="https://images.kiwi.com/airlines/64/BA.png" alt="British Airways" class="wp-airline-logo">
                                        <span class="wp-airline-name">British Airways</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Popular Destinations Section -->
                <section class="wp-destinations-section">
                    <div class="wp-section-header">
                        <h2 class="wp-section-title">Popular Destinations</h2>
                        <p class="wp-section-subtitle">Discover amazing places around the world</p>
                    </div>
                    
                    <div class="wp-destinations-grid">
                        <?php
                        $popular_destinations = [
                    [
                        'city' => 'Dubai', 
                        'country' => 'UAE', 
                        'price' => '₦280,000', 
                        'code' => 'DXB',
                        'image' => 'https://images.unsplash.com/photo-1512453979798-5ea266f8880c?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                        'description' => 'Experience luxury and modern architecture'
                    ],
                    [
                        'city' => 'London', 
                        'country' => 'UK', 
                        'price' => '₦350,000', 
                        'code' => 'LHR',
                        'image' => 'https://images.unsplash.com/photo-1513635269975-59663e0ac1ad?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                        'description' => 'Explore historic landmarks and royal heritage'
                    ],
                    [
                        'city' => 'New York', 
                        'country' => 'USA', 
                        'price' => '₦420,000', 
                        'code' => 'JFK',
                        'image' => 'https://images.unsplash.com/photo-1496442226666-8d4d0e62e6e9?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                        'description' => 'The city that never sleeps awaits you'
                    ],
                    [
                        'city' => 'Paris', 
                        'country' => 'France', 
                        'price' => '₦380,000', 
                        'code' => 'CDG',
                        'image' => 'https://images.unsplash.com/photo-1502602898657-3e91760cbb34?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                        'description' => 'Romantic city of lights and art'
                    ],
                    [
                        'city' => 'Lagos', 
                        'country' => 'Nigeria', 
                        'price' => '₦45,000', 
                        'code' => 'LOS',
                        'image' => 'https://images.pexels.com/photos/29715199/pexels-photo-29715199.jpeg',
                        'description' => 'Vibrant economic hub of West Africa'
                    ],

                    [
                        'city' => 'Abuja', 
                        'country' => 'Nigeria', 
                        'price' => '₦55,000', 
                        'code' => 'ABV',
                        'image' => 'https://images.pexels.com/photos/32656347/pexels-photo-32656347.jpeg',
                        'description' => 'Beautiful capital city with modern architecture'
                    ],

                        ];
                        
                        foreach ($popular_destinations as $index => $destination) {
                            echo '
                            <div class="wp-destination-card">
                                <div class="wp-card-image">
                                    <img src="' . $destination['image'] . '" alt="' . htmlspecialchars($destination['city']) . '" loading="lazy">
                                    <div class="wp-card-overlay">
                                        <div class="wp-price-tag">' . $destination['price'] . '</div>
                                        <button class="wp-wishlist-btn" title="Add to wishlist">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M16.5 3c-1.74 0-3.41.81-4.5 2.09C10.91 3.81 9.24 3 7.5 3 4.42 3 2 5.42 2 8.5c0 3.78 3.4 6.86 8.55 11.54L12 21.35l1.45-1.32C18.6 15.36 22 12.28 22 8.5 22 5.42 19.58 3 16.5 3zm-4.4 15.55l-.1.1-.1-.1C7.14 14.24 4 11.39 4 8.5 4 6.5 5.5 5 7.5 5c1.54 0 3.04.99 3.57 2.36h1.87C13.46 5.99 14.96 5 16.5 5c2 0 3.5 1.5 3.5 3.5 0 2.89-3.14 5.74-7.9 10.05z"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                <div class="wp-card-content">
                                    <h3 class="wp-destination-name">' . htmlspecialchars($destination['city']) . '</h3>
                                    <p class="wp-destination-country">' . htmlspecialchars($destination['country']) . '</p>
                                    <p class="wp-destination-description">' . htmlspecialchars($destination['description']) . '</p>
                                    <div class="wp-card-actions">
                                        <a href="flights.php?destination=' . urlencode($destination['code']) . '&origin=LOS&departure_date=' . date('Y-m-d', strtotime('+7 days')) . '" class="wp-btn wp-btn-primary">
                                            Book Flight
                                        </a>
                                        <button class="wp-btn wp-btn-outline wp-view-details" data-destination="' . htmlspecialchars($destination['city']) . '">
                                            View Details
                                        </button>
                                    </div>
                                </div>
                            </div>';
                        }
                        ?>
                    </div>
                    
                    <div class="wp-section-footer">
                        <a href="flights.php" class="wp-btn wp-btn-outline">View All Destinations</a>
                    </div>
                </section>

                <!-- Features Section -->
                <section class="wp-features-section">
                    <div class="wp-section-header">
                        <h2 class="wp-section-title">Why Choose <?php echo getSiteSetting($pdo, 'site_name'); ?></h2>
                        <p class="wp-section-subtitle">We provide the best travel experience for our customers</p>
                    </div>
                    
                    <div class="wp-features-grid">
                        <div class="wp-feature-card">
                            <div class="wp-feature-icon">
                                <svg width="40" height="40" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M21 16v-2l-8-5V3.5c0-.83-.67-1.5-1.5-1.5S10 2.67 10 3.5V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5l8 2.5z"/>
                                </svg>
                            </div>
                            <h3 class="wp-feature-title">Best Flight Deals</h3>
                            <p class="wp-feature-description">Get the best prices on flights to destinations worldwide with our advanced search technology.</p>
                        </div>
                        
                        <div class="wp-feature-card">
                            <div class="wp-feature-icon">
                                <svg width="40" height="40" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/>
                                </svg>
                            </div>
                            <h3 class="wp-feature-title">Secure Booking</h3>
                            <p class="wp-feature-description">Your transactions are protected with industry-leading security measures and encryption.</p>
                        </div>
                        
                        <div class="wp-feature-card">
                            <div class="wp-feature-icon">
                                <svg width="40" height="40" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/>
                                </svg>
                            </div>
                            <h3 class="wp-feature-title">24/7 Support</h3>
                            <p class="wp-feature-description">Our customer support team is available round the clock to assist you with your travel needs.</p>
                        </div>
                        
                        <div class="wp-feature-card">
                            <div class="wp-feature-icon">
                                <svg width="40" height="40" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/>
                                </svg>
                            </div>
                            <h3 class="wp-feature-title">Global Coverage</h3>
                            <p class="wp-feature-description">Access thousands of destinations worldwide with multiple airline options to choose from.</p>
                        </div>
                        
                        <div class="wp-feature-card">
                            <div class="wp-feature-icon">
                                <svg width="40" height="40" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/>
                                </svg>
                            </div>
                            <h3 class="wp-feature-title">Instant Confirmation</h3>
                            <p class="wp-feature-description">Get instant booking confirmation and e-tickets delivered to your email immediately.</p>
                        </div>
                        
                        <div class="wp-feature-card">
                            <div class="wp-feature-icon">
                                <svg width="40" height="40" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/>
                                </svg>
                            </div>
                            <h3 class="wp-feature-title">Best Price Guarantee</h3>
                            <p class="wp-feature-description">We guarantee the best prices for all our flights and travel packages.</p>
                        </div>
                    </div>
                </section>

                <!-- Special Offers Section -->
                <section class="wp-offers-section">
                    <div class="wp-section-header">
                        <h2 class="wp-section-title">Special Offers</h2>
                        <p class="wp-section-subtitle">Don't miss these amazing travel deals</p>
                    </div>
                    
                    <div class="wp-offers-slider">
                        <div class="wp-offer-slide">
                            <div class="wp-offer-badge">Limited Time</div>
                            <div class="wp-offer-content">
                                <h3 class="wp-offer-title">Summer Sale - 50% OFF</h3>
                                <p class="wp-offer-description">Book your summer vacation now and get 50% off on selected destinations.</p>
                                <div class="wp-offer-details">
                                    <span class="wp-offer-discount">50% OFF</span>
                                    <span class="wp-offer-validity">Valid until: Dec 31, 2024</span>
                                </div>
                                <a href="flights.php" class="wp-btn wp-btn-primary">Book Now</a>
                            </div>
                        </div>
                        
                        <div class="wp-offer-slide">
                            <div class="wp-offer-badge">New</div>
                            <div class="wp-offer-content">
                                <h3 class="wp-offer-title">Business Class Upgrade</h3>
                                <p class="wp-offer-description">Upgrade to business class for the price of premium economy on international flights.</p>
                                <div class="wp-offer-details">
                                    <span class="wp-offer-discount">Free Upgrade</span>
                                    <span class="wp-offer-validity">Limited seats available</span>
                                </div>
                                <a href="flights.php" class="wp-btn wp-btn-primary">Learn More</a>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Newsletter Section -->
                <section class="wp-newsletter-section">
                    <div class="wp-newsletter-content">
                        <h2 class="wp-newsletter-title">Stay Updated</h2>
                        <p class="wp-newsletter-subtitle">Subscribe to our newsletter for the latest travel deals and updates</p>
                        <form class="wp-newsletter-form">
                            <div class="wp-input-group">
                                <input type="email" placeholder="Enter your email address" class="wp-form-control" required>
                                <button type="submit" class="wp-btn wp-btn-primary">Subscribe</button>
                            </div>
                        </form>
                    </div>
                </section>
            </div>
        </div>
    </div>
</div>

<style>
    /* WordPress Style Variables */
    :root {
        --wp-blue: #0073aa;
        --wp-blue-dark: #005a87;
        --wp-blue-light: #00a0d2;
        --wp-orange: #ff6b35;
        --wp-orange-dark: #e55a2b;
        --wp-gray-light: #f8f9fa;
        --wp-gray-medium: #e9ecef;
        --wp-gray-dark: #6c757d;
        --wp-black: #343a40;
        --wp-white: #ffffff;
        --wp-border-radius: 8px;
        --wp-border-radius-lg: 12px;
        --wp-box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        --wp-box-shadow-lg: 0 8px 30px rgba(0,0,0,0.15);
        --wp-transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    }

    /* WordPress Container */
    .wp-style-container {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
        line-height: 1.6;
        color: var(--wp-black);
    }

    .wp-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 15px;
    }

    /* WordPress Hero Section with Sliding Background */
    .wp-hero-section {
        position: relative;
        min-height: 70vh;
        display: flex;
        align-items: center;
        overflow: hidden;
        color: var(--wp-white);
        margin-bottom: 30px;
    }

    .wp-hero-background {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 1;
    }

    .wp-hero-slides {
        position: relative;
        width: 100%;
        height: 100%;
    }

    .wp-hero-slide {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        opacity: 0;
        transition: opacity 1.5s ease-in-out;
    }

    .wp-hero-slide.active {
        opacity: 1;
    }

    .wp-hero-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, rgba(0,115,170,0.85) 0%, rgba(0,90,135,0.9) 100%);
        z-index: 2;
    }

    .wp-hero-content {
        position: relative;
        z-index: 3;
        width: 100%;
    }

    .wp-hero-text {
        text-align: center;
        max-width: 900px;
        margin: 0 auto;
    }

    .wp-hero-title {
        font-size: 3rem;
        font-weight: 700;
        margin-bottom: 20px;
        line-height: 1.2;
        text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
    }

    .wp-hero-subtitle {
        font-size: 1.3rem;
        margin-bottom: 40px;
        opacity: 0.95;
        text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
    }

    /* Trip.com Style Search Tabs */
    .wp-search-tabs-container {
        background: var(--wp-white);
        border-radius: var(--wp-border-radius-lg);
        box-shadow: var(--wp-box-shadow-lg);
        overflow: hidden;
        max-width: 900px;
        margin: 0 auto;
    }

    .wp-search-tabs {
        display: flex;
        background: var(--wp-gray-light);
        border-bottom: 1px solid var(--wp-gray-medium);
    }

    .wp-tab-btn {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 20px;
        background: transparent;
        border: none;
        color: var(--wp-gray-dark);
        font-weight: 600;
        font-size: 16px;
        cursor: pointer;
        transition: var(--wp-transition);
        text-decoration: none;
        position: relative;
    }

    .wp-tab-btn:hover {
        background: rgba(0,115,170,0.05);
        color: var(--wp-blue);
    }

    .wp-tab-btn.active {
        background: var(--wp-white);
        color: var(--wp-blue);
        box-shadow: 0 -2px 0 var(--wp-blue) inset;
    }

    .wp-tab-btn.active::after {
        content: '';
        position: absolute;
        bottom: -1px;
        left: 0;
        right: 0;
        height: 3px;
        background: var(--wp-blue);
    }

    .wp-tab-icon {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .wp-tab-content {
        display: none;
        padding: 30px;
    }

    .wp-tab-content.active {
        display: block;
        animation: fadeIn 0.5s ease-in-out;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Enhanced Flight Search Form Styles */
    .wp-search-form {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .wp-trip-type-selector {
        display: flex;
        justify-content: center;
        align-items: center;
        padding-bottom: 20px;
        border-bottom: 1px solid var(--wp-gray-medium);
    }

    .wp-radio-group {
        display: flex;
        gap: 25px;
    }

    .wp-radio-label {
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        font-weight: 500;
        color: var(--wp-black);
        transition: var(--wp-transition);
    }

    .wp-radio-input {
        display: none;
    }

    .wp-radio-custom {
        width: 18px;
        height: 18px;
        border: 2px solid var(--wp-gray-dark);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: var(--wp-transition);
    }

    .wp-radio-custom::after {
        content: '';
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: var(--wp-white);
        transition: var(--wp-transition);
    }

    .wp-radio-input:checked + .wp-radio-custom {
        border-color: var(--wp-blue);
        background: var(--wp-blue);
    }

    .wp-radio-input:checked + .wp-radio-custom::after {
        background: var(--wp-white);
    }

    .wp-search-fields-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 15px;
        align-items: end;
    }

    .wp-search-field {
        position: relative;
    }

    .wp-field-label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: var(--wp-black);
        font-size: 14px;
    }

    .wp-input-with-icon {
        position: relative;
    }

    .wp-input-icon {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 16px;
        z-index: 2;
        color: var(--wp-gray-dark);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .wp-form-control {
        width: 100%;
        padding: 12px 15px 12px 35px;
        border: 1px solid var(--wp-gray-medium);
        border-radius: var(--wp-border-radius);
        font-size: 14px;
        transition: var(--wp-transition);
        background: var(--wp-white);
        position: relative;
        height: 45px;
    }

    .wp-form-control:focus {
        outline: none;
        border-color: var(--wp-blue);
        box-shadow: 0 0 0 3px rgba(0,115,170,0.1);
    }

    /* Loading Spinner */
    .wp-loading-spinner {
        border: 2px solid #f3f3f3;
        border-top: 2px solid var(--wp-blue);
        border-radius: 50%;
        width: 16px;
        height: 16px;
        animation: spin 1s linear infinite;
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        display: none;
        z-index: 10;
    }

    @keyframes spin {
        0% { transform: translateY(-50%) rotate(0deg); }
        100% { transform: translateY(-50%) rotate(360deg); }
    }

    /* UPDATED: Enhanced Passenger Selector for Full Display */
    .wp-passenger-selector {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 15px;
        border: 1px solid var(--wp-gray-medium);
        border-radius: var(--wp-border-radius);
        background: var(--wp-white);
        cursor: pointer;
        transition: var(--wp-transition);
        height: 45px;
        position: relative;
    }

    .wp-passenger-selector:hover {
        border-color: var(--wp-blue);
    }

    .wp-passenger-display {
        display: flex;
        align-items: center;
        flex: 1;
        min-width: 0;
    }

    .wp-passenger-full-display {
        font-size: 14px;
        color: var(--wp-black);
        font-weight: 500;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        display: block;
        width: 100%;
    }

    .wp-dropdown-arrow {
        color: var(--wp-gray-dark);
        font-size: 10px;
        transition: var(--wp-transition);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-left: 8px;
        flex-shrink: 0;
    }

    /* Enhanced Passenger Modal - Better Positioning */
    .wp-passenger-modal {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: var(--wp-white);
        border: 1px solid var(--wp-gray-medium);
        border-radius: var(--wp-border-radius);
        box-shadow: var(--wp-box-shadow-lg);
        padding: 20px;
        z-index: 1000;
        display: none;
        margin-top: 5px;
        width: 100%;
        box-sizing: border-box;
    }

    .wp-passenger-modal.active {
        display: block;
        animation: fadeIn 0.3s ease-in-out;
    }

    .wp-passenger-options {
        margin-bottom: 20px;
    }

    .wp-passenger-type {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 0;
        border-bottom: 1px solid var(--wp-gray-light);
    }

    .wp-passenger-info {
        display: flex;
        flex-direction: column;
    }

    .wp-passenger-info strong {
        font-size: 14px;
        color: var(--wp-black);
    }

    .wp-passenger-info span {
        font-size: 12px;
        color: var(--wp-gray-dark);
    }

    .wp-passenger-controls {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .wp-passenger-btn {
        width: 30px;
        height: 30px;
        border: 1px solid var(--wp-gray-medium);
        border-radius: 50%;
        background: var(--wp-white);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: var(--wp-transition);
        font-size: 16px;
        color: var(--wp-gray-dark);
    }

    .wp-passenger-btn:hover {
        border-color: var(--wp-blue);
        color: var(--wp-blue);
    }

    .wp-passenger-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .wp-passenger-number {
        font-weight: 600;
        min-width: 20px;
        text-align: center;
    }

    .wp-class-options {
        display: flex;
        flex-direction: column;
        gap: 12px;
        margin-bottom: 20px;
    }

    .wp-class-option {
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        font-size: 14px;
        color: var(--wp-black);
    }

    .wp-class-radio {
        display: none;
    }

    .wp-class-custom {
        width: 18px;
        height: 18px;
        border: 2px solid var(--wp-gray-medium);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: var(--wp-transition);
    }

    .wp-class-custom::after {
        content: '';
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: var(--wp-white);
        transition: var(--wp-transition);
    }

    .wp-class-radio:checked + .wp-class-custom {
        border-color: var(--wp-blue);
        background: var(--wp-blue);
    }

    .wp-class-radio:checked + .wp-class-custom::after {
        background: var(--wp-white);
    }

    .wp-passenger-actions {
        text-align: right;
    }

    .wp-search-actions {
        display: flex;
        justify-content: center;
        margin-top: 10px;
    }

    /* WordPress Buttons */
    .wp-btn {
        padding: 12px 24px;
        border: none;
        border-radius: var(--wp-border-radius);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        cursor: pointer;
        transition: var(--wp-transition);
        font-size: 14px;
        height: 45px;
    }

    .wp-btn-primary {
        background: var(--wp-blue);
        color: var(--wp-white);
    }

    .wp-btn-primary:hover {
        background: var(--wp-blue-dark);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,115,170,0.3);
    }

    .wp-btn-outline {
        background: transparent;
        border: 1px solid var(--wp-blue);
        color: var(--wp-blue);
    }

    .wp-btn-outline:hover {
        background: var(--wp-blue);
        color: var(--wp-white);
        transform: translateY(-2px);
    }

    .wp-btn-icon {
        margin-right: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    /* Enhanced WordPress Suggestions Box */
    .wp-suggestions-box {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: var(--wp-white);
        border: 1px solid var(--wp-gray-medium);
        border-radius: var(--wp-border-radius);
        max-height: 250px;
        overflow-y: auto;
        z-index: 1000;
        box-shadow: var(--wp-box-shadow-lg);
        display: none;
    }

    .wp-suggestion-item {
        padding: 12px 15px;
        cursor: pointer;
        border-bottom: 1px solid var(--wp-gray-medium);
        transition: var(--wp-transition);
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .wp-suggestion-item:hover {
        background: var(--wp-gray-light);
    }

    .wp-suggestion-item:last-child {
        border-bottom: none;
    }

    .wp-airport-code {
        font-weight: bold;
        color: var(--wp-blue);
        min-width: 40px;
    }

    .wp-airport-name {
        color: var(--wp-gray-dark);
        font-size: 12px;
        flex: 1;
    }

    .wp-suggestion-loading {
        padding: 20px;
        text-align: center;
        color: var(--wp-gray-dark);
        font-style: italic;
    }

    /* Rest of your existing styles remain exactly the same */
    /* WordPress Main Content Layout */
    .wp-main-content {
        padding: 30px 0;
    }

    .wp-content-area {
        width: 100%;
    }

    /* WordPress Stats Section */
    .wp-stats-section {
        margin-bottom: 60px;
    }

    .wp-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 25px;
    }

    .wp-stat-item {
        text-align: center;
        padding: 30px 20px;
        background: var(--wp-white);
        border-radius: var(--wp-border-radius);
        box-shadow: var(--wp-box-shadow);
        transition: var(--wp-transition);
    }

    .wp-stat-item:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    }

    .wp-stat-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 20px;
    }

    .wp-stat-number {
        font-size: 2.5rem;
        font-weight: 700;
        color: var(--wp-blue);
        margin-bottom: 10px;
    }

    .wp-stat-label {
        color: var(--wp-gray-dark);
        font-size: 16px;
        font-weight: 500;
    }

    /* Sidebar Content Section */
    .wp-sidebar-content-section {
        margin-bottom: 60px;
    }

    .wp-sidebar-content-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 30px;
    }

    .wp-content-widget {
        background: var(--wp-white);
        border-radius: var(--wp-border-radius);
        box-shadow: var(--wp-box-shadow);
        overflow: hidden;
        transition: var(--wp-transition);
    }

    .wp-content-widget:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }

    .wp-widget-title {
        background: var(--wp-blue);
        color: var(--wp-white);
        padding: 20px;
        margin: 0;
        font-size: 1.2rem;
        font-weight: 600;
    }

    .wp-widget-content {
        padding: 25px;
    }

    /* WordPress Deals List */
    .wp-deals-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .wp-deals-list li {
        padding: 15px 0;
        border-bottom: 1px solid var(--wp-gray-medium);
        display: flex;
        align-items: center;
        transition: var(--wp-transition);
    }

    .wp-deals-list li:hover {
        background: var(--wp-gray-light);
        margin: 0 -25px;
        padding: 15px 25px;
    }

    .wp-deals-list li:last-child {
        border-bottom: none;
    }

    .wp-deal-badge {
        background: var(--wp-blue);
        color: var(--wp-white);
        padding: 4px 10px;
        border-radius: 3px;
        font-size: 11px;
        font-weight: 600;
        margin-right: 12px;
        min-width: 60px;
        text-align: center;
    }

    .wp-deal-link {
        color: var(--wp-black);
        text-decoration: none;
        font-size: 15px;
        transition: var(--wp-transition);
        flex: 1;
    }

    .wp-deal-link:hover {
        color: var(--wp-blue);
    }

    /* WordPress Tips List */
    .wp-tips-list {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .wp-tip-item h4 {
        margin: 0 0 8px 0;
        font-size: 16px;
        font-weight: 600;
        color: var(--wp-black);
    }

    .wp-tip-item p {
        margin: 0;
        font-size: 14px;
        color: var(--wp-gray-dark);
        line-height: 1.5;
    }

    /* WordPress Airlines Grid */
    .wp-airlines-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 20px;
    }

    .wp-airline-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 12px;
        padding: 20px 15px;
        background: var(--wp-gray-light);
        border-radius: var(--wp-border-radius);
        transition: var(--wp-transition);
        text-align: center;
    }

    .wp-airline-item:hover {
        background: var(--wp-blue);
        transform: translateY(-3px);
    }

    .wp-airline-item:hover .wp-airline-name {
        color: var(--wp-white);
    }

    .wp-airline-logo {
        width: 50px;
        height: 50px;
        border-radius: 8px;
        object-fit: contain;
        background: var(--wp-white);
        padding: 5px;
    }

    .wp-airline-name {
        font-size: 13px;
        color: var(--wp-black);
        font-weight: 500;
        transition: var(--wp-transition);
    }

    /* WordPress Section Styling */
    .wp-section-header {
        text-align: center;
        margin-bottom: 50px;
    }

    .wp-section-title {
        font-size: 2.2rem;
        font-weight: 600;
        color: var(--wp-black);
        margin-bottom: 15px;
    }

    .wp-section-subtitle {
        font-size: 1.1rem;
        color: var(--wp-gray-dark);
        max-width: 600px;
        margin: 0 auto;
    }

    .wp-section-footer {
        text-align: center;
        margin-top: 40px;
    }

    /* WordPress Destinations Section */
    .wp-destinations-section {
        margin-bottom: 60px;
    }

    .wp-destinations-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 30px;
        margin-bottom: 40px;
    }

    .wp-destination-card {
        background: var(--wp-white);
        border-radius: var(--wp-border-radius);
        overflow: hidden;
        box-shadow: var(--wp-box-shadow);
        transition: var(--wp-transition);
    }

    .wp-destination-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    }

    .wp-card-image {
        position: relative;
        height: 250px;
        overflow: hidden;
    }

    .wp-card-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: var(--wp-transition);
    }

    .wp-destination-card:hover .wp-card-image img {
        transform: scale(1.1);
    }

    .wp-card-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(to bottom, transparent, rgba(0,0,0,0.5));
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        padding: 20px;
    }

    .wp-price-tag {
        background: var(--wp-blue);
        color: var(--wp-white);
        padding: 8px 16px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 15px;
    }

    .wp-wishlist-btn {
        background: var(--wp-white);
        border: none;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: var(--wp-transition);
        font-size: 16px;
    }

    .wp-wishlist-btn:hover {
        background: #dc3545;
        color: var(--wp-white);
        transform: scale(1.1);
    }

    .wp-wishlist-btn:hover svg {
        fill: var(--wp-white);
    }

    .wp-card-content {
        padding: 25px;
    }

    .wp-destination-name {
        font-size: 1.4rem;
        font-weight: 600;
        margin-bottom: 8px;
        color: var(--wp-black);
    }

    .wp-destination-country {
        color: var(--wp-blue);
        font-weight: 500;
        margin-bottom: 12px;
        font-size: 15px;
    }

    .wp-destination-description {
        color: var(--wp-gray-dark);
        margin-bottom: 20px;
        line-height: 1.6;
        font-size: 14px;
    }

    .wp-card-actions {
        display: flex;
        gap: 12px;
    }

    .wp-card-actions .wp-btn {
        flex: 1;
        font-size: 14px;
        padding: 10px 20px;
    }

    /* WordPress Features Section */
    .wp-features-section {
        margin-bottom: 60px;
    }

    .wp-features-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 30px;
    }

    .wp-feature-card {
        text-align: center;
        padding: 35px 25px;
        background: var(--wp-white);
        border-radius: var(--wp-border-radius);
        box-shadow: var(--wp-box-shadow);
        transition: var(--wp-transition);
    }

    .wp-feature-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    }

    .wp-feature-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 20px;
    }

    .wp-feature-title {
        font-size: 1.3rem;
        font-weight: 600;
        margin-bottom: 15px;
        color: var(--wp-black);
    }

    .wp-feature-description {
        color: var(--wp-gray-dark);
        line-height: 1.6;
        font-size: 15px;
    }

    /* WordPress Offers Section */
    .wp-offers-section {
        margin-bottom: 60px;
        background: linear-gradient(135deg, var(--wp-blue) 0%, var(--wp-blue-dark) 100%);
        color: var(--wp-white);
        padding: 60px 0;
        border-radius: var(--wp-border-radius);
    }

    .wp-offers-section .wp-section-title,
    .wp-offers-section .wp-section-subtitle {
        color: var(--wp-white);
    }

    .wp-offers-slider {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 30px;
    }

    .wp-offer-slide {
        background: rgba(255,255,255,0.15);
        padding: 35px;
        border-radius: var(--wp-border-radius);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.2);
        position: relative;
        transition: var(--wp-transition);
    }

    .wp-offer-slide:hover {
        transform: translateY(-5px);
        background: rgba(255,255,255,0.2);
    }

    .wp-offer-badge {
        position: absolute;
        top: -12px;
        right: 25px;
        background: #dc3545;
        color: var(--wp-white);
        padding: 6px 15px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 13px;
    }

    .wp-offer-title {
        font-size: 1.5rem;
        font-weight: 600;
        margin-bottom: 15px;
    }

    .wp-offer-description {
        margin-bottom: 20px;
        opacity: 0.95;
        line-height: 1.6;
        font-size: 15px;
    }

    .wp-offer-details {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
    }

    .wp-offer-discount {
        background: #ffc107;
        color: var(--wp-black);
        padding: 6px 15px;
        border-radius: 20px;
        font-weight: 700;
        font-size: 15px;
    }

    .wp-offer-validity {
        font-size: 13px;
        opacity: 0.9;
    }

    /* WordPress Newsletter Section */
    .wp-newsletter-section {
        background: var(--wp-gray-light);
        padding: 60px 0;
        border-radius: var(--wp-border-radius);
        margin-bottom: 40px;
    }

    .wp-newsletter-content {
        text-align: center;
        max-width: 600px;
        margin: 0 auto;
    }

    .wp-newsletter-title {
        font-size: 1.8rem;
        font-weight: 600;
        margin-bottom: 15px;
        color: var(--wp-black);
    }

    .wp-newsletter-subtitle {
        font-size: 1.1rem;
        margin-bottom: 30px;
        color: var(--wp-gray-dark);
    }

    .wp-newsletter-form {
        display: flex;
        gap: 15px;
        max-width: 500px;
        margin: 0 auto;
    }

    .wp-input-group {
        display: flex;
        flex: 1;
        gap: 15px;
    }

    .wp-newsletter-form .wp-form-control {
        flex: 1;
        padding: 15px 20px;
        border: 1px solid var(--wp-gray-medium);
        font-size: 15px;
    }

    /* Enhanced Responsive Design */
    @media (max-width: 1024px) {
        .wp-search-fields-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 768px) {
        .wp-search-fields-grid {
            grid-template-columns: 1fr;
        }
        
        .wp-search-actions {
            flex-direction: column;
        }
        
        .wp-hero-title {
            font-size: 2.2rem;
        }

        .wp-hero-subtitle {
            font-size: 1.1rem;
        }

        .wp-tab-content {
            padding: 20px;
        }

        .wp-destinations-grid {
            grid-template-columns: 1fr;
        }

        .wp-features-grid {
            grid-template-columns: 1fr;
        }

        .wp-offers-slider {
            grid-template-columns: 1fr;
        }

        .wp-newsletter-form {
            flex-direction: column;
        }

        .wp-input-group {
            flex-direction: column;
        }

        .wp-card-actions {
            flex-direction: column;
        }

        .wp-section-title {
            font-size: 1.8rem;
        }

        .wp-stat-number {
            font-size: 2rem;
        }
        
        .wp-hero-section {
            min-height: 60vh;
        }

        .wp-trip-type-selector {
            flex-direction: column;
            gap: 15px;
        }

        .wp-radio-group {
            flex-wrap: wrap;
            justify-content: center;
        }

        /* Fixed passenger modal for mobile */
        .wp-passenger-modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 90%;
            max-width: 400px;
            max-height: 80vh;
            overflow-y: auto;
            margin-top: 0;
        }
    }

    @media (max-width: 480px) {
        .wp-hero-title {
            font-size: 1.8rem;
        }

        .wp-tab-content {
            padding: 15px;
        }

        .wp-destination-card,
        .wp-feature-card,
        .wp-offer-slide {
            padding: 15px;
        }
        
        .wp-stats-grid {
            grid-template-columns: 1fr;
        }
        
        .wp-sidebar-content-grid {
            grid-template-columns: 1fr;
        }
        
        .wp-airlines-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .wp-hero-section {
            min-height: 50vh;
        }

        .wp-search-tabs {
            flex-direction: column;
        }

        .wp-tab-btn {
            padding: 15px;
        }

        .wp-passenger-modal {
            width: 95%;
            padding: 15px;
        }
    }
</style>

<script>
    // Initialize on DOM load
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize counter animations
        initCounters();
        
        // Initialize enhanced airport suggestions using Amadeus API
        setupEnhancedAirportSuggestions('origin', 'originSuggestions');
        setupEnhancedAirportSuggestions('destination', 'destinationSuggestions');
        
        // Initialize trip type toggle - UPDATED FOR ONE-WAY DEFAULT
        initTripTypeToggle();
        
        // Initialize hero slider
        initHeroSlider();
        
        // Initialize tab functionality
        initSearchTabs();
        
        // Initialize passenger selector
        initPassengerSelector();
        
        // Initialize date pickers
        initDatePickers();
    });

    // Search Tabs Functionality
    function initSearchTabs() {
        const tabButtons = document.querySelectorAll('.wp-tab-btn');
        
        tabButtons.forEach(button => {
            // Only add click event to buttons that aren't links
            if (!button.getAttribute('href')) {
                button.addEventListener('click', function() {
                    const tabId = this.getAttribute('data-tab');
                    
                    // Remove active class from all buttons and content
                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    document.querySelectorAll('.wp-tab-content').forEach(content => content.classList.remove('active'));
                    
                    // Add active class to clicked button and corresponding content
                    this.classList.add('active');
                    document.getElementById(tabId + '-tab').classList.add('active');
                });
            }
        });
    }

    // Hero Slider Functionality
    function initHeroSlider() {
        const slides = document.querySelectorAll('.wp-hero-slide');
        let currentSlide = 0;
        
        function showSlide(index) {
            slides.forEach(slide => slide.classList.remove('active'));
            slides[index].classList.add('active');
        }
        
        function nextSlide() {
            currentSlide = (currentSlide + 1) % slides.length;
            showSlide(currentSlide);
        }
        
        // Change slide every 5 seconds
        setInterval(nextSlide, 5000);
        
        // Initial slide show
        showSlide(currentSlide);
    }

    // Counter animation
    function initCounters() {
        const counters = document.querySelectorAll('.wp-stat-number');
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const counter = entry.target;
                    const target = parseInt(counter.getAttribute('data-count'));
                    const duration = 2000;
                    const step = target / (duration / 16);
                    let current = 0;
                    
                    const timer = setInterval(() => {
                        current += step;
                        if (current >= target) {
                            counter.textContent = target.toLocaleString();
                            clearInterval(timer);
                        } else {
                            counter.textContent = Math.floor(current).toLocaleString();
                        }
                    }, 16);
                    
                    observer.unobserve(counter);
                }
            });
        }, { threshold: 0.5 });
        
        counters.forEach(counter => observer.observe(counter));
    }

    // Trip type toggle - UPDATED FOR ONE-WAY DEFAULT
    function initTripTypeToggle() {
        const tripTypeRadios = document.querySelectorAll('input[name="trip_type"]');
        const returnDateField = document.getElementById('returnDateField');
        
        // Set initial state - hide return date for one-way (default)
        returnDateField.style.display = 'none';
        
        tripTypeRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.value === 'one_way') {
                    returnDateField.style.display = 'none';
                    document.getElementById('returnDate').value = '';
                } else {
                    returnDateField.style.display = 'block';
                }
            });
        });
    }

    // Passenger selector functionality - UPDATED FOR FULL DISPLAY
    function initPassengerSelector() {
        const passengerSelector = document.getElementById('passengerSelector');
        const passengerModal = document.getElementById('passengerModal');
        const applyButton = document.getElementById('applyPassengers');
        
        let passengerCounts = {
            adults: 1,
            children: 0,
            infants: 0
        };
        
        let selectedClass = 'ECONOMY';
        
        // Toggle modal
        passengerSelector.addEventListener('click', function(e) {
            e.stopPropagation();
            passengerModal.classList.toggle('active');
        });
        
        // Close modal when clicking outside
        document.addEventListener('click', function(e) {
            if (!passengerSelector.contains(e.target) && !passengerModal.contains(e.target)) {
                passengerModal.classList.remove('active');
            }
        });
        
        // Handle passenger count changes
        document.querySelectorAll('.wp-passenger-btn').forEach(button => {
            button.addEventListener('click', function() {
                const type = this.getAttribute('data-type');
                const action = this.getAttribute('data-action');
                const numberElement = document.querySelector(`.wp-passenger-number[data-type="${type}"]`);
                
                if (action === 'increase') {
                    passengerCounts[type]++;
                } else if (action === 'decrease' && passengerCounts[type] > 0) {
                    passengerCounts[type]--;
                }
                
                numberElement.textContent = passengerCounts[type];
                updatePassengerButtons(type);
            });
        });
        
        // Handle class selection
        document.querySelectorAll('.wp-class-radio').forEach(radio => {
            radio.addEventListener('change', function() {
                selectedClass = this.value;
            });
        });
        
        // Apply passenger selection
        applyButton.addEventListener('click', function() {
            updatePassengerDisplay();
            passengerModal.classList.remove('active');
        });
        
        function updatePassengerButtons(type) {
            const buttons = document.querySelectorAll(`.wp-passenger-btn[data-type="${type}"]`);
            buttons.forEach(button => {
                if (button.getAttribute('data-action') === 'decrease') {
                    button.disabled = passengerCounts[type] === 0;
                }
            });
        }
        
        // UPDATED: Enhanced display function for full text
        function updatePassengerDisplay() {
            const totalPassengers = passengerCounts.adults + passengerCounts.children + passengerCounts.infants;
            
            // Build passenger text
            let passengerText = '';
            if (passengerCounts.adults > 0) {
                passengerText += `${passengerCounts.adults} ${passengerCounts.adults === 1 ? 'adult' : 'adults'}`;
            }
            if (passengerCounts.children > 0) {
                passengerText += passengerText ? `, ${passengerCounts.children} ${passengerCounts.children === 1 ? 'child' : 'children'}` : `${passengerCounts.children} ${passengerCounts.children === 1 ? 'child' : 'children'}`;
            }
            if (passengerCounts.infants > 0) {
                passengerText += passengerText ? `, ${passengerCounts.infants} ${passengerCounts.infants === 1 ? 'infant' : 'infants'}` : `${passengerCounts.infants} ${passengerCounts.infants === 1 ? 'infant' : 'infants'}`;
            }
            
            // Get class text
            const classText = getClassText(selectedClass);
            
            // Combine into full display
            const fullDisplay = `${passengerText}, ${classText}`;
            
            // Update the display
            document.querySelector('.wp-passenger-full-display').textContent = fullDisplay;
        }
        
        function getClassText(classValue) {
            const classMap = {
                'ECONOMY': 'Economy',
                'PREMIUM_ECONOMY': 'Premium Economy',
                'BUSINESS': 'Business',
                'FIRST': 'First Class'
            };
            return classMap[classValue] || 'Economy';
        }
        
        // Initialize buttons state and display
        Object.keys(passengerCounts).forEach(type => updatePassengerButtons(type));
        updatePassengerDisplay(); // Set initial display
    }

    // Date picker functionality
    function initDatePickers() {
        const departureInput = document.getElementById('departureDate');
        const returnInput = document.getElementById('returnDate');
        
        // Set minimum date to today
        const today = new Date();
        const tomorrow = new Date(today);
        tomorrow.setDate(tomorrow.getDate() + 1);
        
        // Format: YYYY-MM-DD for input type="date"
        const formatDate = (date) => {
            return date.toISOString().split('T')[0];
        };
        
        // Set default values
        const nextWeek = new Date(today);
        nextWeek.setDate(nextWeek.getDate() + 7);
        
        const nextWeekPlus2 = new Date(nextWeek);
        nextWeekPlus2.setDate(nextWeekPlus2.getDate() + 2);
        
        departureInput.value = formatDate(nextWeek);
        returnInput.value = formatDate(nextWeekPlus2);
        departureInput.min = formatDate(today);
        returnInput.min = formatDate(tomorrow);
    }

    // Enhanced airport suggestion functionality using Amadeus API (same as flights.php)
    function setupEnhancedAirportSuggestions(inputId, suggestionsId) {
        const input = document.getElementById(inputId);
        const suggestionsBox = document.getElementById(suggestionsId);
        const loadingSpinner = document.getElementById(inputId + 'Loading');

        let debounceTimer;
        let currentSuggestions = [];

        input.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            const value = this.value.trim();
            
            // Show loading spinner
            loadingSpinner.style.display = 'block';
            suggestionsBox.innerHTML = '';
            suggestionsBox.style.display = 'none';
            
            if (value.length < 2) {
                loadingSpinner.style.display = 'none';
                if (value.length === 0) {
                    // Show popular airports when input is empty
                    showPopularAirports(inputId);
                }
                return;
            }

            debounceTimer = setTimeout(() => {
                // Show loading state
                suggestionsBox.innerHTML = '<div class="wp-suggestion-loading">Searching for airports...</div>';
                suggestionsBox.style.display = 'block';
                
                // Fetch from Amadeus API via flights.php
                fetch(`flights.php?search_airports=1&query=${encodeURIComponent(value)}`)
                    .then(response => response.json())
                    .then(data => {
                        showEnhancedSuggestions(inputId, data);
                        loadingSpinner.style.display = 'none';
                    })
                    .catch(error => {
                        console.error('Error fetching airport suggestions:', error);
                        suggestionsBox.innerHTML = '<div class="wp-suggestion-loading">Error loading airports. Please try again.</div>';
                        loadingSpinner.style.display = 'none';
                    });
            }, 300);
        });

        // Show popular airports when input is focused and empty
        input.addEventListener('focus', function() {
            if (this.value.trim() === '') {
                showPopularAirports(inputId);
            }
        });

        // Hide suggestions when clicking outside
        document.addEventListener('click', function(e) {
            if (!input.contains(e.target) && !suggestionsBox.contains(e.target)) {
                suggestionsBox.style.display = 'none';
                loadingSpinner.style.display = 'none';
            }
        });

        // Keyboard navigation
        input.addEventListener('keydown', function(e) {
            const items = suggestionsBox.querySelectorAll('.wp-suggestion-item');
            const activeItem = suggestionsBox.querySelector('.wp-suggestion-item.active');
            let activeIndex = -1;
            
            if (activeItem) {
                activeIndex = Array.from(items).indexOf(activeItem);
            }
            
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                const nextIndex = (activeIndex + 1) % items.length;
                setActiveSuggestion(items, nextIndex);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                const prevIndex = (activeIndex - 1 + items.length) % items.length;
                setActiveSuggestion(items, prevIndex);
            } else if (e.key === 'Enter' && activeItem) {
                e.preventDefault();
                activeItem.click();
            }
        });
    }

    function setActiveSuggestion(items, index) {
        items.forEach(item => item.classList.remove('active'));
        if (items[index]) {
            items[index].classList.add('active');
            items[index].scrollIntoView({ block: 'nearest' });
        }
    }

    function showPopularAirports(fieldId) {
        const suggestionsBox = document.getElementById(fieldId + 'Suggestions');
        const input = document.getElementById(fieldId);
        
        if (!suggestionsBox || !input) return;

        const popularAirports = [
            { code: 'LOS', name: 'Murtala Muhammed International Airport', city: 'Lagos', country: 'Nigeria' },
            { code: 'ABV', name: 'Nnamdi Azikiwe International Airport', city: 'Abuja', country: 'Nigeria' },
            { code: 'PHC', name: 'Port Harcourt International Airport', city: 'Port Harcourt', country: 'Nigeria' },
            { code: 'KAN', name: 'Mallam Aminu Kano International Airport', city: 'Kano', country: 'Nigeria' },
            { code: 'LHR', name: 'Heathrow Airport', city: 'London', country: 'United Kingdom' },
            { code: 'DXB', name: 'Dubai International Airport', city: 'Dubai', country: 'UAE' },
            { code: 'JFK', name: 'John F. Kennedy International Airport', city: 'New York', country: 'USA' },
            { code: 'CDG', name: 'Charles de Gaulle Airport', city: 'Paris', country: 'France' }
        ];

        let html = '<div class="wp-suggestion-item" style="background: #f8f9fa; font-weight: bold; color: var(--wp-blue);">Popular Airports</div>';
        
        popularAirports.forEach(airport => {
            html += createSuggestionItem(airport, fieldId);
        });

        suggestionsBox.innerHTML = html;
        suggestionsBox.style.display = 'block';
        
        // Set first suggestion as active
        const firstItem = suggestionsBox.querySelector('.wp-suggestion-item:not([style])');
        if (firstItem) {
            firstItem.classList.add('active');
        }
    }

    function showEnhancedSuggestions(fieldId, data) {
        const suggestionsBox = document.getElementById(fieldId + 'Suggestions');
        const input = document.getElementById(fieldId);
        
        if (!suggestionsBox || !input) return;

        if (data.length === 0) {
            suggestionsBox.innerHTML = '<div class="wp-suggestion-loading">No airports found. Try a different search.</div>';
            suggestionsBox.style.display = 'block';
            return;
        }

        let html = '';
        
        // Show all results without limits
        data.forEach(airport => {
            html += createSuggestionItem(airport, fieldId);
        });

        suggestionsBox.innerHTML = html;
        suggestionsBox.style.display = 'block';
        
        // Set first suggestion as active
        const firstItem = suggestionsBox.querySelector('.wp-suggestion-item');
        if (firstItem) {
            firstItem.classList.add('active');
        }
    }

    function createSuggestionItem(airport, fieldId) {
        return `
            <div class="wp-suggestion-item"
                 onclick="selectAirport('${fieldId}', '${airport.code}', '${airport.name} - ${airport.city}, ${airport.country}')">
                <div class="wp-airport-code">${airport.code}</div>
                <div class="wp-airport-name">
                    <div>${airport.city}, ${airport.country}</div>
                    <small>${airport.name}</small>
                </div>
            </div>
        `;
    }

    function selectAirport(fieldId, code, displayText) {
        const input = document.getElementById(fieldId);
        const suggestionsBox = document.getElementById(fieldId + 'Suggestions');
        const loadingSpinner = document.getElementById(fieldId + 'Loading');
        
        if (input && suggestionsBox && loadingSpinner) {
            input.value = code;
            suggestionsBox.style.display = 'none';
            loadingSpinner.style.display = 'none';
        }
    }

    // Form validation
    document.getElementById('flightSearchForm').addEventListener('submit', function(e) {
        const origin = document.getElementById('origin').value.toUpperCase().trim();
        const destination = document.getElementById('destination').value.toUpperCase().trim();
        const departureDate = document.getElementById('departureDate').value;
        const tripType = document.querySelector('input[name="trip_type"]:checked').value;
        const returnDate = document.getElementById('returnDate').value;

        // Validate same origin and destination
        if (origin === destination) {
            e.preventDefault();
            alert('Origin and destination cannot be the same');
            return false;
        }

        // Validate airport codes
        const airportCodeRegex = /^[A-Z]{3}$/;
        if (!airportCodeRegex.test(origin)) {
            e.preventDefault();
            alert('Please enter a valid 3-letter airport code for origin (e.g., LOS for Lagos)');
            return false;
        }

        if (!airportCodeRegex.test(destination)) {
            e.preventDefault();
            alert('Please enter a valid 3-letter airport code for destination (e.g., ABV for Abuja)');
            return false;
        }

        // Validate dates
        if (!departureDate) {
            e.preventDefault();
            alert('Please select a departure date');
            return false;
        }

        if (tripType === 'round_trip' && !returnDate) {
            e.preventDefault();
            alert('Please select a return date for round trip');
            return false;
        }

        if (tripType === 'round_trip' && returnDate && returnDate <= departureDate) {
            e.preventDefault();
            alert('Return date must be after departure date');
            return false;
        }

        // Auto-format inputs
        document.getElementById('origin').value = origin;
        document.getElementById('destination').value = destination;
    });

    // Wishlist functionality
    document.querySelectorAll('.wp-wishlist-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            this.classList.toggle('active');
            if (this.classList.contains('active')) {
                this.style.background = '#dc3545';
                this.innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                </svg>`;
            } else {
                this.style.background = 'white';
                this.innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M16.5 3c-1.74 0-3.41.81-4.5 2.09C10.91 3.81 9.24 3 7.5 3 4.42 3 2 5.42 2 8.5c0 3.78 3.4 6.86 8.55 11.54L12 21.35l1.45-1.32C18.6 15.36 22 12.28 22 8.5 22 5.42 19.58 3 16.5 3zm-4.4 15.55l-.1.1-.1-.1C7.14 14.24 4 11.39 4 8.5 4 6.5 5.5 5 7.5 5c1.54 0 3.04.99 3.57 2.36h1.87C13.46 5.99 14.96 5 16.5 5c2 0 3.5 1.5 3.5 3.5 0 2.89-3.14 5.74-7.9 10.05z"/>
                </svg>`;
            }
        });
    });

    // View details functionality
    document.querySelectorAll('.wp-view-details').forEach(btn => {
        btn.addEventListener('click', function() {
            const destination = this.getAttribute('data-destination');
            alert(`More details about ${destination} coming soon!`);
        });
    });

    // Newsletter form submission
    document.querySelector('.wp-newsletter-form').addEventListener('submit', function(e) {
        e.preventDefault();
        const email = this.querySelector('input[type="email"]').value;
        alert(`Thank you for subscribing with ${email}! You'll receive our latest travel deals soon.`);
        this.reset();
    });
</script>

<?php
require_once 'includes/footer.php';
?>