<?php
// includes/footer.php
// Get site settings for logo
$site_logo = getSiteSetting($pdo, 'logo');
$site_name = getSiteSetting($pdo, 'site_name');
?>
    
    <footer style="background: linear-gradient(135deg, #1a2a3a 0%, #2c3e50 100%); color: white; padding: 3rem 0 1.5rem; margin-top: 4rem; position: relative; overflow: hidden; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
        <!-- Background Pattern -->
        <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: url('data:image/svg+xml,%3Csvg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 100 100\" fill=\"%232c3e50\"%3E%3Ccircle cx=\"20\" cy=\"20\" r=\"1\" fill=\"%231a2a3a\"/%3E%3Ccircle cx=\"80\" cy=\"80\" r=\"1\" fill=\"%231a2a3a\"/%3E%3Ccircle cx=\"50\" cy=\"50\" r=\"1\" fill=\"%231a2a3a\"/%3E%3C/svg%3E'); opacity: 0.4; z-index: 0;"></div>
        
        <div class="container" style="position: relative; z-index: 1; max-width: 1200px; margin: 0 auto; padding: 0 20px;">
            <div class="footer-grid" style="display: grid; grid-template-columns: 2fr 1fr 1fr 1.5fr; gap: 2rem; margin-bottom: 2.5rem; align-items: start;">
                <!-- Company Info & Carousel -->
                <div class="footer-column company-info">
                    <div class="logo-description-row" style="margin-bottom: 1.5rem;">
                        <?php if (!empty($site_logo)): ?>
                            <!-- Display Logo Only - Same as receipt -->
                            <div class="company-logo-modern" style="text-align: left; margin-bottom: 1rem;">
                                <img src="<?php echo $site_logo; ?>" alt="<?php echo htmlspecialchars($site_name); ?>" 
                                     class="footer-logo" style="max-width: 180px; height: auto; margin-bottom: 10px; filter: brightness(0) invert(1);">
                            </div>
                        <?php else: ?>
                            <h3 style="color: #3498db; margin: 0; font-size: 1.6rem; font-weight: 700; background: linear-gradient(135deg, #3498db, #2ecc71); -webkit-background-clip: text; -webkit-text-fill-color: transparent; line-height: 1.3;"><?php echo $site_name; ?></h3>
                        <?php endif; ?>
                    </div>
                    <p class="site-description" style="color: #bdc3c7; line-height: 1.6; margin-bottom: 1.5rem; font-size: 0.9rem; font-weight: 400;"><?php echo getSiteSetting($pdo, 'site_description'); ?></p>
                    
                    <!-- Travel Carousel Widget -->
                    <div class="carousel-widget" style="background: rgba(255,255,255,0.08); border-radius: 12px; padding: 1.2rem; margin-bottom: 1.5rem; border: 1px solid rgba(255,255,255,0.1); backdrop-filter: blur(10px);">
                        <h5 style="color: #3498db; margin-bottom: 1rem; font-size: 1rem; text-align: center; font-weight: 600;">✈️ Travel Inspiration</h5>
                        <div style="position: relative; overflow: hidden; border-radius: 10px; height: 130px; box-shadow: 0 6px 20px rgba(0,0,0,0.2);">
                            <div id="travelCarousel" style="display: flex; transition: transform 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94); height: 100%;">
                                <!-- Travel images from online sources -->
                                <div style="min-width: 100%; background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.4)), url('https://images.unsplash.com/photo-1488646953014-85cb44e25828?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=400&q=80') center/cover; display: flex; align-items: center; justify-content: center;">
                                    <span style="color: white; font-weight: 600; text-shadow: 2px 2px 6px rgba(0,0,0,0.7); font-size: 0.9rem; background: rgba(0,0,0,0.5); padding: 6px 14px; border-radius: 18px;">🌍 World Travel</span>
                                </div>
                                <div style="min-width: 100%; background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.4)), url('https://images.unsplash.com/photo-1503220317375-aaad61436b1b?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=400&q=80') center/cover; display: flex; align-items: center; justify-content: center;">
                                    <span style="color: white; font-weight: 600; text-shadow: 2px 2px 6px rgba(0,0,0,0.7); font-size: 0.9rem; background: rgba(0,0,0,0.5); padding: 6px 14px; border-radius: 18px;">🏔️ Adventure</span>
                                </div>
                                <div style="min-width: 100%; background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.4)), url('https://images.unsplash.com/photo-1469474968028-56623f02e42e?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=400&q=80') center/cover; display: flex; align-items: center; justify-content: center;">
                                    <span style="color: white; font-weight: 600; text-shadow: 2px 2px 6px rgba(0,0,0,0.7); font-size: 0.9rem; background: rgba(0,0,0,0.5); padding: 6px 14px; border-radius: 18px;">🌳 Nature</span>
                                </div>
                                <div style="min-width: 100%; background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.4)), url('https://images.unsplash.com/photo-1506929562872-bb421503ef21?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=400&q=80') center/cover; display: flex; align-items: center; justify-content: center;">
                                    <span style="color: white; font-weight: 600; text-shadow: 2px 2px 6px rgba(0,0,0,0.7); font-size: 0.9rem; background: rgba(0,0,0,0.5); padding: 6px 14px; border-radius: 18px;">🏖️ Beach</span>
                                </div>
                                <div style="min-width: 100%; background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.4)), url('https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=400&q=80') center/cover; display: flex; align-items: center; justify-content: center;">
                                    <span style="color: white; font-weight: 600; text-shadow: 2px 2px 6px rgba(0,0,0,0.7); font-size: 0.9rem; background: rgba(0,0,0,0.5); padding: 6px 14px; border-radius: 18px;">🏙️ City Life</span>
                                </div>
                            </div>
                        </div>
                        <div style="display: flex; justify-content: center; gap: 0.4rem; margin-top: 1rem;">
                            <span class="carousel-dot active" style="width: 8px; height: 8px; background: #3498db; border-radius: 50%; cursor: pointer; transition: all 0.3s ease;"></span>
                            <span class="carousel-dot" style="width: 8px; height: 8px; background: #bdc3c7; border-radius: 50%; cursor: pointer; transition: all 0.3s ease;"></span>
                            <span class="carousel-dot" style="width: 8px; height: 8px; background: #bdc3c7; border-radius: 50%; cursor: pointer; transition: all 0.3s ease;"></span>
                            <span class="carousel-dot" style="width: 8px; height: 8px; background: #bdc3c7; border-radius: 50%; cursor: pointer; transition: all 0.3s ease;"></span>
                            <span class="carousel-dot" style="width: 8px; height: 8px; background: #bdc3c7; border-radius: 50%; cursor: pointer; transition: all 0.3s ease;"></span>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Links -->
                <div class="footer-column quick-links">
                    <h4 style="color: #3498db; margin-bottom: 1.2rem; font-size: 1.1rem; font-weight: 700; position: relative; padding-bottom: 0.6rem;">
                        Quick Links
                        <span style="position: absolute; bottom: 0; left: 0; width: 35px; height: 3px; background: linear-gradient(135deg, #3498db, #2ecc71); border-radius: 2px;"></span>
                    </h4>
                    <ul style="list-style: none; padding: 0; margin: 0;">
                        <?php
                        $stmt = $pdo->prepare("SELECT * FROM menus WHERE menu_location = 'footer' AND is_active = TRUE ORDER BY menu_order");
                        $stmt->execute();
                        $footer_menus = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (!empty($footer_menus)) {
                            foreach ($footer_menus as $menu) {
                                echo '<li style="margin-bottom: 0.7rem;">
                                        <a href="' . $menu['url'] . '" style="color: #bdc3c7; text-decoration: none; transition: all 0.3s ease; display: flex; align-items: center; gap: 0.7rem; font-size: 0.9rem; font-weight: 500; padding: 0.4rem 0.5rem; border-radius: 6px;" 
                                           onmouseover="this.style.color=\'#3498db\'; this.style.transform=\'translateX(6px)\'; this.style.background=\'rgba(255,255,255,0.05)\'" 
                                           onmouseout="this.style.color=\'#bdc3c7\'; this.style.transform=\'translateX(0)\'; this.style.background=\'transparent\'">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="#3498db" style="flex-shrink: 0;">
                                                <path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/>
                                            </svg>
                                            ' . $menu['title'] . '
                                        </a>
                                      </li>';
                            }
                        } else {
                            $default_links = [
                                ['url' => 'index.php', 'title' => 'Home'],
                                ['url' => 'flights.php', 'title' => 'Flights'],
                                ['url' => 'about.php', 'title' => 'About Us'],
                                ['url' => 'contact.php', 'title' => 'Contact']
                            ];
                            foreach ($default_links as $link) {
                                echo '<li style="margin-bottom: 0.7rem;">
                                        <a href="' . $link['url'] . '" style="color: #bdc3c7; text-decoration: none; transition: all 0.3s ease; display: flex; align-items: center; gap: 0.7rem; font-size: 0.9rem; font-weight: 500; padding: 0.4rem 0.5rem; border-radius: 6px;" 
                                           onmouseover="this.style.color=\'#3498db\'; this.style.transform=\'translateX(6px)\'; this.style.background=\'rgba(255,255,255,0.05)\'" 
                                           onmouseout="this.style.color=\'#bdc3c7\'; this.style.transform=\'translateX(0)\'; this.style.background=\'transparent\'">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="#3498db" style="flex-shrink: 0;">
                                                <path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/>
                                            </svg>
                                            ' . $link['title'] . '
                                        </a>
                                      </li>';
                            }
                        }
                        ?>
                    </ul>
                </div>
                
                <!-- Services -->
                <div class="footer-column services">
                    <h4 style="color: #3498db; margin-bottom: 1.2rem; font-size: 1.1rem; font-weight: 700; position: relative; padding-bottom: 0.6rem;">
                        Our Services
                        <span style="position: absolute; bottom: 0; left: 0; width: 35px; height: 3px; background: linear-gradient(135deg, #3498db, #2ecc71); border-radius: 2px;"></span>
                    </h4>
                    <div class="services-grid" style="display: grid; grid-template-columns: 1fr; gap: 0.7rem;">
                        <a href="flights.php" class="service-box" style="color: #bdc3c7; text-decoration: none; transition: all 0.3s ease; display: flex; align-items: center; gap: 0.7rem; font-size: 0.9rem; font-weight: 500; padding: 0.7rem; border-radius: 8px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);" 
                           onmouseover="this.style.color='#3498db'; this.style.transform='translateY(-2px)'; this.style.background='rgba(255,255,255,0.1)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.2)'" 
                           onmouseout="this.style.color='#bdc3c7'; this.style.transform='translateY(0)'; this.style.background='rgba(255,255,255,0.05)'; this.style.boxShadow='none'">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="#3498db" style="flex-shrink: 0;">
                                <path d="M21 16v-2l-8-5V3.5c0-.83-.67-1.5-1.5-1.5S10 2.67 10 3.5V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5l8 2.5z"/>
                            </svg>
                            Flight Booking
                        </a>
                        <a href="https://bookhotels.ng/" class="service-box" style="color: #bdc3c7; text-decoration: none; transition: all 0.3s ease; display: flex; align-items: center; gap: 0.7rem; font-size: 0.9rem; font-weight: 500; padding: 0.7rem; border-radius: 8px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);" 
                           onmouseover="this.style.color='#3498db'; this.style.transform='translateY(-2px)'; this.style.background='rgba(255,255,255,0.1)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.2)'" 
                           onmouseout="this.style.color='#bdc3c7'; this.style.transform='translateY(0)'; this.style.background='rgba(255,255,255,0.05)'; this.style.boxShadow='none'">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="#3498db" style="flex-shrink: 0;">
                                <path d="M7 14c1.66 0 3-1.34 3-3S8.66 8 7 8s-3 1.34-3 3 1.34 3 3 3zm0-4c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm12-3h-8v8H3V7H1v10h22v-6c0-2.21-1.79-4-4-4zm2 8h-8V9h6c1.1 0 2 .9 2 2v4z"/>
                            </svg>
                            Hotel Reservation
                        </a>
                        <a href="#" class="service-box" style="color: #bdc3c7; text-decoration: none; transition: all 0.3s ease; display: flex; align-items: center; gap: 0.7rem; font-size: 0.9rem; font-weight: 500; padding: 0.7rem; border-radius: 8px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);" 
                           onmouseover="this.style.color='#3498db'; this.style.transform='translateY(-2px)'; this.style.background='rgba(255,255,255,0.1)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.2)'" 
                           onmouseout="this.style.color='#bdc3c7'; this.style.transform='translateY(0)'; this.style.background='rgba(255,255,255,0.05)'; this.style.boxShadow='none'">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="#3498db" style="flex-shrink: 0;">
                                <path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm0 4c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm6 12H6v-1.4c0-2 4-3.1 6-3.1s6 1.1 6 3.1V19z"/>
                            </svg>
                            Visa Assistance
                        </a>
                        <a href="#" class="service-box" style="color: #bdc3c7; text-decoration: none; transition: all 0.3s ease; display: flex; align-items: center; gap: 0.7rem; font-size: 0.9rem; font-weight: 500; padding: 0.7rem; border-radius: 8px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);" 
                           onmouseover="this.style.color='#3498db'; this.style.transform='translateY(-2px)'; this.style.background='rgba(255,255,255,0.1)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.2)'" 
                           onmouseout="this.style.color='#bdc3c7'; this.style.transform='translateY(0)'; this.style.background='rgba(255,255,255,0.05)'; this.style.boxShadow='none'">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="#3498db" style="flex-shrink: 0;">
                                <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/>
                            </svg>
                            Travel Insurance
                        </a>
                    </div>

                    <!-- Contact Info Boxes - Now placed below services on desktop -->
                    <div class="contact-info-boxes" style="display: flex; flex-wrap: wrap; justify-content: center; gap: 1rem; margin-top: 1.5rem; width: 100%;">
                        <!-- Email Box -->
                        <div class="contact-box" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 0.8rem; display: flex; align-items: center; gap: 0.7rem; transition: all 0.3s ease; flex: 0 1 auto; max-width: 280px; min-width: 250px;"
                             onmouseover="this.style.background='rgba(255,255,255,0.1)'; this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.2)'" 
                             onmouseout="this.style.background='rgba(255,255,255,0.05)'; this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                            <div style="background: rgba(52, 152, 219, 0.2); border-radius: 6px; padding: 0.5rem; display: flex; align-items: center; justify-content: center;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="#3498db" style="flex-shrink: 0;">
                                    <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                                </svg>
                            </div>
                            <div style="flex: 1; min-width: 0;">
                                <div style="font-size: 0.75rem; color: #bdc3c7; margin-bottom: 0.2rem; white-space: nowrap;">Email</div>
                                <div style="font-size: 0.8rem; font-weight: 500; color: white; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">hello@travelcentre.ng</div>
                            </div>
                        </div>
                        
                        <!-- Phone Box -->
                        <div class="contact-box" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 0.8rem; display: flex; align-items: center; gap: 0.7rem; transition: all 0.3s ease; flex: 0 1 auto; max-width: 280px; min-width: 250px;"
                             onmouseover="this.style.background='rgba(255,255,255,0.1)'; this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.2)'" 
                             onmouseout="this.style.background='rgba(255,255,255,0.05)'; this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                            <div style="background: rgba(52, 152, 219, 0.2); border-radius: 6px; padding: 0.5rem; display: flex; align-items: center; justify-content: center;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="#3498db" style="flex-shrink: 0;">
                                    <path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/>
                                </svg>
                            </div>
                            <div style="flex: 1; min-width: 0;">
                                <div style="font-size: 0.75rem; color: #bdc3c7; margin-bottom: 0.2rem; white-space: nowrap;">Phone</div>
                                <div style="font-size: 0.8rem; font-weight: 500; color: white; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo getSiteSetting($pdo, 'phone'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Newsletter & Social -->
                <div class="footer-column newsletter">
                    <h4 style="color: #3498db; margin-bottom: 1.2rem; font-size: 1.1rem; font-weight: 700; position: relative; padding-bottom: 0.6rem;">
                        Stay Connected
                        <span style="position: absolute; bottom: 0; left: 0; width: 35px; height: 3px; background: linear-gradient(135deg, #3498db, #2ecc71); border-radius: 2px;"></span>
                    </h4>
                    <p style="color: #bdc3c7; margin-bottom: 1.2rem; font-size: 0.9rem; line-height: 1.5;">Get exclusive travel deals, expert tips, and destination inspiration delivered straight to your inbox.</p>
                    
                    <form id="newsletterForm" style="margin-bottom: 1.5rem;">
                        <div style="display: flex; flex-direction: column; gap: 0.8rem;">
                            <input type="email" placeholder="Your email address" required 
                                   style="padding: 0.8rem 1rem; border: none; border-radius: 8px; background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.2); font-size: 0.9rem; transition: all 0.3s ease; backdrop-filter: blur(10px);"
                                   onfocus="this.style.background='rgba(255,255,255,0.15)'; this.style.borderColor='#3498db'"
                                   onblur="this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='rgba(255,255,255,0.2)'">
                            <button type="submit" 
                                    style="padding: 0.8rem 1.2rem; background: linear-gradient(135deg, #3498db, #2980b9); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.3s ease; font-size: 0.9rem; display: flex; align-items: center; justify-content: center; gap: 0.5rem;"
                                    onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(52, 152, 219, 0.4)'"
                                    onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                                </svg>
                                Subscribe
                            </button>
                        </div>
                    </form>
                    
                    <div>
                        <h5 style="color: #3498db; margin-bottom: 0.8rem; font-size: 1rem; font-weight: 600;">Follow Our Journey</h5>
                        <div style="display: flex; gap: 0.6rem; flex-wrap: wrap;">
                            <a href="#" class="social-button" style="color: #bdc3c7; text-decoration: none; padding: 0.7rem; background: rgba(255,255,255,0.1); border-radius: 8px; transition: all 0.3s ease; display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; border: 1px solid rgba(255,255,255,0.1); backdrop-filter: blur(10px);"
                               onmouseover="this.style.color='white'; this.style.background='#3b5998'; this.style.transform='translateY(-2px)'; this.style.borderColor='#3b5998'; this.style.boxShadow='0 4px 15px rgba(59, 89, 152, 0.4)'"
                               onmouseout="this.style.color='#bdc3c7'; this.style.background='rgba(255,255,255,0.1)'; this.style.transform='translateY(0)'; this.style.borderColor='rgba(255,255,255,0.1)'; this.style.boxShadow='none'">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                                </svg>
                                Facebook
                            </a>
                            <a href="#" class="social-button" style="color: #bdc3c7; text-decoration: none; padding: 0.7rem; background: rgba(255,255,255,0.1); border-radius: 8px; transition: all 0.3s ease; display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; border: 1px solid rgba(255,255,255,0.1); backdrop-filter: blur(10px);"
                               onmouseover="this.style.color='white'; this.style.background='#e4405f'; this.style.transform='translateY(-2px)'; this.style.borderColor='#e4405f'; this.style.boxShadow='0 4px 15px rgba(228, 64, 95, 0.4)'"
                               onmouseout="this.style.color='#bdc3c7'; this.style.background='rgba(255,255,255,0.1)'; this.style.transform='translateY(0)'; this.style.borderColor='rgba(255,255,255,0.1)'; this.style.boxShadow='none'">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 1 0 0 12.324 6.162 6.162 0 0 0 0-12.324zM12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm6.406-11.845a1.44 1.44 0 1 0 0 2.881 1.44 1.44 0 0 0 0-2.881z"/>
                                </svg>
                                Instagram
                            </a>
                            <a href="#" class="social-button" style="color: #bdc3c7; text-decoration: none; padding: 0.7rem; background: rgba(255,255,255,0.1); border-radius: 8px; transition: all 0.3s ease; display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; border: 1px solid rgba(255,255,255,0.1); backdrop-filter: blur(10px);"
                               onmouseover="this.style.color='white'; this.style.background='#1da1f2'; this.style.transform='translateY(-2px)'; this.style.borderColor='#1da1f2'; this.style.boxShadow='0 4px 15px rgba(29, 161, 242, 0.4)'"
                               onmouseout="this.style.color='#bdc3c7'; this.style.background='rgba(255,255,255,0.1)'; this.style.transform='translateY(0)'; this.style.borderColor='rgba(255,255,255,0.1)'; this.style.boxShadow='none'">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723 10.055 10.055 0 01-3.127 1.195 4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.937 4.937 0 004.604 3.417 9.868 9.868 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.054 0 13.999-7.496 13.999-13.986 0-.209 0-.42-.015-.63a9.936 9.936 0 002.46-2.548l-.047-.02z"/>
                                </svg>
                                Twitter
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Copyright -->
            <div style="border-top: 1px solid rgba(255,255,255,0.15); padding-top: 1.5rem; text-align: center; color: #95a5a6;">
                <div class="copyright-content" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                    <p style="margin: 0; font-size: 0.85rem; font-weight: 500;">&copy; <?php echo date('Y'); ?> <?php echo $site_name; ?>. All rights reserved.</p>
                    <div style="display: flex; gap: 1.2rem; flex-wrap: wrap;">
                        <a href="privacy.php" style="color: #95a5a6; text-decoration: none; font-size: 0.85rem; transition: all 0.3s ease; font-weight: 500; padding: 0.3rem 0.5rem; border-radius: 4px;" onmouseover="this.style.color='#3498db'; this.style.background='rgba(255,255,255,0.05)'" onmouseout="this.style.color='#95a5a6'; this.style.background='transparent'">Privacy Policy</a>
                        <a href="terms.php" style="color: #95a5a6; text-decoration: none; font-size: 0.85rem; transition: all 0.3s ease; font-weight: 500; padding: 0.3rem 0.5rem; border-radius: 4px;" onmouseover="this.style.color='#3498db'; this.style.background='rgba(255,255,255,0.05)'" onmouseout="this.style.color='#95a5a6'; this.style.background='transparent'">Terms of Service</a>
                        <a href="sitemap.php" style="color: #95a5a6; text-decoration: none; font-size: 0.85rem; transition: all 0.3s ease; font-weight: 500; padding: 0.3rem 0.5rem; border-radius: 4px;" onmouseover="this.style.color='#3498db'; this.style.background='rgba(255,255,255,0.05)'" onmouseout="this.style.color='#95a5a6'; this.style.background='transparent'">Sitemap</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Back to Top Button -->
        <div id="backToTop" style="position: fixed; bottom: 100px; right: 20px; background: linear-gradient(135deg, #3498db, #2980b9); color: white; width: 45px; height: 45px; border-radius: 50%; display: none; align-items: center; justify-content: center; cursor: pointer; z-index: 9998; box-shadow: 0 4px 15px rgba(52, 152, 219, 0.4); transition: all 0.3s ease; border: none;"
             onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 6px 20px rgba(52, 152, 219, 0.6)'"
             onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 15px rgba(52, 152, 219, 0.4)'">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <path d="M7.41 15.41L12 10.83l4.59 4.58L18 14l-6-6-6 6z"/>
            </svg>
        </div>

        <!-- WhatsApp Floating Button -->
        <div id="whatsappButton" style="position: fixed; bottom: 30px; right: 20px; background: #25D366; color: white; width: 55px; height: 55px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 9999; box-shadow: 0 4px 15px rgba(37, 211, 102, 0.4); transition: all 0.3s ease;"
             onmouseover="this.style.transform='translateY(-3px) scale(1.1)'; this.style.boxShadow='0 6px 20px rgba(37, 211, 102, 0.6)'"
             onmouseout="this.style.transform='translateY(0) scale(1)'; this.style.boxShadow='0 4px 15px rgba(37, 211, 102, 0.4)'">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor">
                <path d="M16.75 13.96c.25.13.41.2.46.3.06.11.04.61-.21 1.18-.2.56-1.24 1.1-1.7 1.12-.46.02-.47.36-2.96-.73-2.49-1.09-3.99-3.75-4.11-3.92-.12-.17-.96-1.38-.92-2.61.05-1.22.69-1.8.95-2.04.24-.26.51-.29.68-.26h.47c.15 0 .36-.06.55.45l.69 1.87c.06.13.1.28.01.44l-.27.41-.39.42c-.12.12-.26.25-.12.5.12.26.62 1.09 1.32 1.78.91.88 1.71 1.17 1.95 1.3.24.14.39.12.54-.04l.81-.94c.19-.25.35-.19.58-.11l1.67.88M12 2a10 10 0 0 1 10 10 10 10 0 0 1-10 10c-1.97 0-3.8-.57-5.35-1.55L2 22l1.55-4.65A9.969 9.969 0 0 1 2 12 10 10 0 0 1 12 2m0 2a8 8 0 0 0-8 8c0 1.72.54 3.31 1.46 4.61L4.5 19.5l2.89-.96A7.95 7.95 0 0 0 12 20a8 8 0 0 0 8-8 8 8 0 0 0-8-8z"/>
            </svg>
        </div>

        <!-- WhatsApp Chat Modal -->
        <div id="whatsappModal" style="position: fixed; bottom: 95px; right: 20px; width: 320px; background: white; border-radius: 14px; box-shadow: 0 8px 30px rgba(0,0,0,0.3); z-index: 10000; display: none; overflow: hidden; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
            <div style="background: linear-gradient(135deg, #25D366, #128C7E); color: white; padding: 1.2rem; display: flex; align-items: center; gap: 0.8rem;">
                <div style="width: 45px; height: 45px; background: white; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="#25D366">
                        <path d="M16.75 13.96c.25.13.41.2.46.3.06.11.04.61-.21 1.18-.2.56-1.24 1.1-1.7 1.12-.46.02-.47.36-2.96-.73-2.49-1.09-3.99-3.75-4.11-3.92-.12-.17-.96-1.38-.92-2.61.05-1.22.69-1.8.95-2.04.24-.26.51-.29.68-.26h.47c.15 0 .36-.06.55.45l.69 1.87c.06.13.1.28.01.44l-.27.41-.39.42c-.12.12-.26.25-.12.5.12.26.62 1.09 1.32 1.78.91.88 1.71 1.17 1.95 1.3.24.14.39.12.54-.04l.81-.94c.19-.25.35-.19.58-.11l1.67.88M12 2a10 10 0 0 1 10 10 10 10 0 0 1-10 10c-1.97 0-3.8-.57-5.35-1.55L2 22l1.55-4.65A9.969 9.969 0 0 1 2 12 10 10 0 0 1 12 2m0 2a8 8 0 0 0-8 8c0 1.72.54 3.31 1.46 4.61L4.5 19.5l2.89-.96A7.95 7.95 0 0 0 12 20a8 8 0 0 0 8-8 8 8 0 0 0-8-8z"/>
                    </svg>
                </div>
                <div>
                    <h4 style="margin: 0; font-size: 1.1rem; font-weight: 600;">WhatsApp Support</h4>
                    <p style="margin: 0.3rem 0 0; font-size: 0.85rem; opacity: 0.9;">We're here to help!</p>
                </div>
                <button onclick="closeWhatsAppModal()" style="margin-left: auto; background: none; border: none; color: white; cursor: pointer; padding: 0.5rem; border-radius: 50%; transition: background 0.3s ease;"
                        onmouseover="this.style.background='rgba(255,255,255,0.2)'"
                        onmouseout="this.style.background='transparent'">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                    </svg>
                </button>
            </div>
            <form id="whatsappForm" style="padding: 1.2rem;">
                <div style="display: flex; flex-direction:column; gap: 0.8rem;">
                    <div>
                        <label style="display: block; margin-bottom: 0.4rem; color: #2c3e50; font-weight: 600; font-size: 0.85rem;">Your Name *</label>
                        <input type="text" id="whatsappName" required 
                               style="width: 100%; padding: 0.7rem 0.9rem; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 0.9rem; transition: all 0.3s ease;"
                               onfocus="this.style.borderColor='#3498db'; this.style.boxShadow='0 0 0 3px rgba(52, 152, 219, 0.1)'"
                               onblur="this.style.borderColor='#e0e0e0'; this.style.boxShadow='none'"
                               placeholder="Enter your full name">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 0.4rem; color: #2c3e50; font-weight: 600; font-size: 0.85rem;">Email Address *</label>
                        <input type="email" id="whatsappEmail" required 
                               style="width: 100%; padding: 0.7rem 0.9rem; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 0.9rem; transition: all 0.3s ease;"
                               onfocus="this.style.borderColor='#3498db'; this.style.boxShadow='0 0 0 3px rgba(52, 152, 219, 0.1)'"
                               onblur="this.style.borderColor='#e0e0e0'; this.style.boxShadow='none'"
                               placeholder="Enter your email">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 0.4rem; color: #2c3e50; font-weight: 600; font-size: 0.85rem;">Reason for Contact *</label>
                        <select id="whatsappReason" required 
                                style="width: 100%; padding: 0.7rem 0.9rem; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 0.9rem; transition: all 0.3s ease; background: white;"
                                onfocus="this.style.borderColor='#3498db'; this.style.boxShadow='0 0 0 3px rgba(52, 152, 219, 0.1)'"
                                onblur="this.style.borderColor='#e0e0e0'; this.style.boxShadow='none'">
                            <option value="">Select a reason</option>
                            <option value="Flight Booking">Flight Booking</option>
                            <option value="Hotel Reservation">Hotel Reservation</option>
                            <option value="Visa Assistance">Visa Assistance</option>
                            <option value="Travel Insurance">Travel Insurance</option>
                            <option value="General Inquiry">General Inquiry</option>
                            <option value="Complaint">Complaint</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 0.4rem; color: #2c3e50; font-weight: 600; font-size: 0.85rem;">Additional Message</label>
                        <textarea id="whatsappMessage" rows="3"
                                  style="width: 100%; padding: 0.7rem 0.9rem; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 0.9rem; transition: all 0.3s ease; resize: vertical; font-family: inherit;"
                                  onfocus="this.style.borderColor='#3498db'; this.style.boxShadow='0 0 0 3px rgba(52, 152, 219, 0.1)'"
                                  onblur="this.style.borderColor='#e0e0e0'; this.style.boxShadow='none'"
                                  placeholder="Tell us more about your inquiry..."></textarea>
                    </div>
                    <button type="submit" 
                            style="width: 100%; padding: 0.8rem; background: linear-gradient(135deg, #25D366, #128C7E); color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.95rem; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center; gap: 0.5rem;"
                            onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(37, 211, 102, 0.4)'"
                            onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M16.75 13.96c.25.13.41.2.46.3.06.11.04.61-.21 1.18-.2.56-1.24 1.1-1.7 1.12-.46.02-.47.36-2.96-.73-2.49-1.09-3.99-3.75-4.11-3.92-.12-.17-.96-1.38-.92-2.61.05-1.22.69-1.8.95-2.04.24-.26.51-.29.68-.26h.47c.15 0 .36-.06.55.45l.69 1.87c.06.13.1.28.01.44l-.27.41-.39.42c-.12.12-.26.25-.12.5.12.26.62 1.09 1.32 1.78.91.88 1.71 1.17 1.95 1.3.24.14.39.12.54-.04l.81-.94c.19-.25.35-.19.58-.11l1.67.88M12 2a10 10 0 0 1 10 10 10 10 0 0 1-10 10c-1.97 0-3.8-.57-5.35-1.55L2 22l1.55-4.65A9.969 9.969 0 0 1 2 12 10 10 0 0 1 12 2m0 2a8 8 0 0 0-8 8c0 1.72.54 3.31 1.46 4.61L4.5 19.5l2.89-.96A7.95 7.95 0 0 0 12 20a8 8 0 0 0 8-8 8 8 0 0 0-8-8z"/>
                        </svg>
                        Start WhatsApp Chat
                    </button>
                </div>
            </form>
        </div>
    </footer>

    <style>
        /* Responsive Styles for Footer */
        @media (max-width: 1200px) {
            .footer-grid {
                gap: 1.5rem;
            }
            
            .footer-logo {
                max-width: 160px !important;
            }
        }
        
        @media (max-width: 992px) {
            .footer-grid {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 2rem;
            }
            
            .company-info {
                grid-column: 1 / -1;
            }
            
            .carousel-widget {
                max-width: 500px;
                margin-left: auto;
                margin-right: auto;
            }
            
            .newsletter form {
                max-width: 300px;
                margin-left: auto;
                margin-right: auto;
            }
            
            .social-button {
                justify-content: center;
                min-width: 100px;
            }
            
            .copyright-content {
                justify-content: center !important;
                text-align: center;
                flex-direction: column;
                gap: 1rem !important;
            }
            
            .copyright-content > div {
                justify-content: center !important;
            }
        }
        
        @media (max-width: 768px) {
            /* Mobile Layout */
            .footer-grid {
                grid-template-columns: 1fr !important;
                gap: 1.5rem !important;
            }
            
            /* Hide quick links and newsletter on mobile */
            .quick-links,
            .carousel-widget,
            .newsletter {
                display: none !important;
            }
            
            /* Services grid for mobile - 2 per row with smaller boxes */
            .services-grid {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 0.6rem !important;
            }
            
            .service-box {
                display: flex !important;
                flex-direction: column !important;
                align-items: center !important;
                justify-content: center !important;
                text-align: center !important;
                padding: 0.6rem 0.4rem !important;
                min-height: 85px !important;
                font-size: 0.8rem !important;
            }
            
            .service-box svg {
                margin-bottom: 0.4rem !important;
                width: 18px !important;
                height: 18px !important;
            }
            
            /* Contact info boxes - properly centered on mobile */
            .contact-info-boxes {
                display: flex !important;
                flex-direction: column !important;
                align-items: center !important;
                justify-content: center !important;
                width: 100% !important;
                gap: 0.8rem !important;
                margin-bottom: 1.5rem !important;
                margin-top: 1.5rem !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
                padding: 0 !important;
            }
            
            .contact-box {
                padding: 0.8rem !important;
                max-width: 300px !important;
                width: 100% !important;
                min-width: auto !important;
                margin-left: auto !important;
                margin-right: auto !important;
                display: flex !important;
                justify-content: flex-start !important;
            }
            
            /* Logo and description in same row on mobile */
            .company-info {
                display: flex !important;
                flex-wrap: wrap !important;
                align-items: center !important;
                justify-content: center !important;
                gap: 1.5rem !important;
                margin-bottom: 1.5rem !important;
                text-align: center !important;
            }
            
            .logo-description-row {
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                gap: 1.5rem !important;
                flex: 1 !important;
                margin-bottom: 0 !important;
                width: 100% !important;
            }
            
            .company-logo-modern {
                flex: 0 0 auto !important;
                text-align: center !important;
                margin-bottom: 0 !important;
                width: 100% !important;
            }
            
            .footer-logo {
                max-width: 100px !important;
                margin-bottom: 0 !important;
                margin-left: auto !important;
                margin-right: auto !important;
                display: block !important;
            }
            
            .site-description {
                flex: 1 !important;
                margin-bottom: 0 !important;
                text-align: center !important;
                font-size: 0.85rem !important;
                line-height: 1.5 !important;
                width: 100% !important;
            }
            
            .services h4 {
                font-size: 1rem !important;
                margin-bottom: 1rem !important;
                text-align: left !important;
            }
            
            .services h4 span {
                left: 0 !important;
                transform: none !important;
            }
            
            footer {
                padding: 2.5rem 0 1.5rem !important;
            }
            
            .container {
                padding: 0 15px !important;
            }
            
            h4 {
                font-size: 1rem !important;
                margin-bottom: 1rem !important;
            }
            
            p {
                font-size: 0.85rem !important;
            }
            
            .social-button {
                padding: 0.6rem !important;
                font-size: 0.8rem !important;
                min-width: 90px;
            }
            
            .social-button svg {
                width: 14px !important;
                height: 14px !important;
            }
            
            #backToTop {
                width: 40px !important;
                height: 40px !important;
                right: 15px !important;
                bottom: 90px !important;
            }
            
            #whatsappButton {
                width: 50px !important;
                height: 50px !important;
                right: 15px !important;
            }
            
            #whatsappModal {
                width: 280px !important;
                right: 15px !important;
                bottom: 85px !important;
            }
        }
        
        @media (max-width: 576px) {
            .footer-grid {
                gap: 2rem !important;
            }
            
            /* Services grid adjustments for smaller screens */
            .services-grid {
                gap: 0.5rem !important;
            }
            
            .service-box {
                padding: 0.5rem 0.3rem !important;
                min-height: 80px !important;
                font-size: 0.75rem !important;
            }
            
            .service-box svg {
                width: 16px !important;
                height: 16px !important;
                margin-bottom: 0.3rem !important;
            }
            
            /* Contact boxes adjustments - ensure perfect centering */
            .contact-info-boxes {
                gap: 0.7rem !important;
                align-items: center !important;
                justify-content: center !important;
                width: 100% !important;
                padding: 0 !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
            }
            
            .contact-box {
                padding: 0.7rem !important;
                max-width: 280px !important;
                width: 100% !important;
                margin-left: auto !important;
                margin-right: auto !important;
            }
            
            /* Adjust logo/description row for smaller screens */
            .logo-description-row {
                flex-direction: column !important;
                align-items: center !important;
                justify-content: center !important;
                gap: 1rem !important;
            }
            
            .footer-logo {
                max-width: 120px !important;
                margin-left: auto !important;
                margin-right: auto !important;
            }
            
            .site-description {
                font-size: 0.8rem !important;
                text-align: center !important;
            }
            
            .services h4 {
                font-size: 0.95rem !important;
            }
            
            .social-button {
                font-size: 0.75rem !important;
                padding: 0.5rem !important;
            }
            
            .copyright-content > div {
                flex-direction: column;
                gap: 0.5rem !important;
            }
            
            .copyright-content a {
                padding: 0.3rem 0.8rem !important;
                display: inline-block;
            }
            
            #whatsappModal {
                width: calc(100% - 30px) !important;
                max-width: 300px !important;
                left: 50% !important;
                right: auto !important;
                transform: translateX(-50%);
            }
        }
        
        @media (max-width: 480px) {
            footer {
                padding: 2rem 0 1.5rem !important;
            }
            
            .logo-description-row {
                gap: 0.8rem !important;
            }
            
            .footer-logo {
                max-width: 100px !important;
            }
            
            .site-description {
                font-size: 0.75rem !important;
            }
            
            .services-grid {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 0.5rem !important;
            }
            
            .service-box {
                padding: 0.5rem 0.25rem !important;
                min-height: 75px !important;
                font-size: 0.7rem !important;
            }
            
            .service-box svg {
                width: 14px !important;
                height: 14px !important;
            }
            
            /* Ensure contact boxes are perfectly centered on very small screens */
            .contact-info-boxes {
                gap: 0.6rem !important;
                align-items: center !important;
                justify-content: center !important;
                width: 100% !important;
            }
            
            .contact-box {
                padding: 0.6rem !important;
                max-width: 100% !important;
                width: 100% !important;
            }
            
            h4 {
                font-size: 0.9rem !important;
            }
            
            .copyright-content p,
            .copyright-content a {
                font-size: 0.8rem !important;
            }
        }
        
        @media (max-width: 360px) {
            .services-grid {
                grid-template-columns: 1fr !important;
                gap: 0.6rem !important;
            }
            
            .service-box {
                padding: 0.6rem !important;
                min-height: 70px !important;
                font-size: 0.75rem !important;
            }
            
            .social-button {
                flex-basis: 100%;
                margin-bottom: 0.5rem;
            }
            
            .footer-logo {
                max-width: 80px !important;
            }
            
            .site-description {
                font-size: 0.7rem !important;
            }
            
            /* Contact boxes for very small screens */
            .contact-box {
                padding: 0.5rem !important;
                max-width: 100% !important;
            }
            
            svg {
                width: 14px !important;
                height: 14px !important;
            }
        }
        
        /* Desktop-specific styles for contact boxes */
        @media (min-width: 769px) {
            .contact-info-boxes {
                display: flex !important;
                flex-wrap: wrap !important;
                justify-content: center !important;
                gap: 1rem !important;
                margin-top: 1.5rem !important;
                width: 100% !important;
            }
            
            .contact-box {
                flex: 0 1 auto !important;
                max-width: 280px !important;
                min-width: 250px !important;
                width: 100% !important;
            }
            
            /* Ensure text stays on one line */
            .contact-box > div:last-child {
                flex: 1 !important;
                min-width: 0 !important;
            }
            
            .contact-box > div:last-child > div:last-child {
                white-space: nowrap !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
            }
        }
    </style>

    <script>
        // Newsletter form submission
        document.getElementById('newsletterForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const email = this.querySelector('input[type="email"]').value;
            
            // Simple email validation
            if (!email || !email.includes('@')) {
                alert('Please enter a valid email address.');
                return;
            }
            
            // Simulate API call
            const submitBtn = this.querySelector('button');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Subscribing...';
            submitBtn.disabled = true;
            
            setTimeout(() => {
                alert('Thank you for subscribing with: ' + email);
                this.reset();
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            }, 1000);
        });
        
        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
        
        // Handle logo loading errors in footer
        document.addEventListener('DOMContentLoaded', function() {
            const footerLogo = document.querySelector('footer img');
            if (footerLogo) {
                footerLogo.addEventListener('error', function() {
                    this.style.display = 'none';
                    // Show site name if logo fails to load
                    const siteName = '<?php echo $site_name; ?>';
                    if (siteName) {
                        this.parentElement.innerHTML = '<h3 style="color: #3498db; margin: 0; font-size: 1.5rem; font-weight: 700; background: linear-gradient(135deg, #3498db, #2ecc71); -webkit-background-clip: text; -webkit-text-fill-color: transparent; line-height: 1.3;">' + siteName + '</h3>';
                    }
                });
            }
            
            // Carousel functionality
            const carousel = document.getElementById('travelCarousel');
            const dots = document.querySelectorAll('.carousel-dot');
            let currentIndex = 0;
            let carouselInterval;
            
            function updateCarousel() {
                if (carousel) {
                    carousel.style.transform = `translateX(-${currentIndex * 100}%)`;
                    
                    // Update dots
                    dots.forEach((dot, index) => {
                        dot.style.background = index === currentIndex ? '#3498db' : '#bdc3c7';
                        dot.style.transform = index === currentIndex ? 'scale(1.2)' : 'scale(1)';
                    });
                }
            }
            
            function startCarousel() {
                carouselInterval = setInterval(() => {
                    currentIndex = (currentIndex + 1) % 5;
                    updateCarousel();
                }, 4000);
            }
            
            function stopCarousel() {
                clearInterval(carouselInterval);
            }
            
            // Initialize carousel
            if (carousel && dots.length > 0) {
                startCarousel();
                
                // Add click events to dots
                dots.forEach((dot, index) => {
                    dot.addEventListener('click', () => {
                        currentIndex = index;
                        updateCarousel();
                        stopCarousel();
                        startCarousel();
                    });
                });
                
                // Pause on hover
                carousel.parentElement.addEventListener('mouseenter', stopCarousel);
                carousel.parentElement.addEventListener('mouseleave', startCarousel);
            }
            
            // Back to top functionality
            const backToTop = document.getElementById('backToTop');
            
            window.addEventListener('scroll', function() {
                if (window.pageYOffset > 300) {
                    backToTop.style.display = 'flex';
                } else {
                    backToTop.style.display = 'none';
                }
            });
            
            backToTop.addEventListener('click', function() {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });
            
            // WhatsApp functionality
            const whatsappButton = document.getElementById('whatsappButton');
            const whatsappModal = document.getElementById('whatsappModal');
            const whatsappForm = document.getElementById('whatsappForm');
            
            whatsappButton.addEventListener('click', function() {
                whatsappModal.style.display = 'block';
            });
            
            function closeWhatsAppModal() {
                whatsappModal.style.display = 'none';
            }
            
            // Close modal when clicking outside
            document.addEventListener('click', function(event) {
                if (!whatsappModal.contains(event.target) && !whatsappButton.contains(event.target)) {
                    closeWhatsAppModal();
                }
            });
            
            whatsappForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const name = document.getElementById('whatsappName').value;
                const email = document.getElementById('whatsappEmail').value;
                const reason = document.getElementById('whatsappReason').value;
                const message = document.getElementById('whatsappMessage').value;
                
                if (!name || !email || !reason) {
                    alert('Please fill in all required fields.');
                    return;
                }
                
                // Format the message for WhatsApp
                const whatsappMessage = `Hello! I would like to inquire about ${reason}.\n\nName: ${name}\nEmail: ${email}\n\n${message ? 'Additional Message: ' + message : 'No additional message provided.'}`;
                
                // Encode the message for URL
                const encodedMessage = encodeURIComponent(whatsappMessage);
                const phoneNumber = '+2349034072383';
                
                // Open WhatsApp with pre-filled message
                window.open(`https://wa.me/${phoneNumber}?text=${encodedMessage}`, '_blank');
                
                // Close the modal
                closeWhatsAppModal();
                
                // Reset the form
                whatsappForm.reset();
            });
            
            // Handle responsive adjustments on window resize
            function handleResponsiveFooter() {
                const screenWidth = window.innerWidth;
                const copyrightContent = document.querySelector('.copyright-content');
                
                if (screenWidth <= 576) {
                    // Stack copyright links on very small screens
                    if (copyrightContent) {
                        copyrightContent.style.flexDirection = 'column';
                        copyrightContent.style.gap = '0.5rem';
                        copyrightContent.style.textAlign = 'center';
                    }
                }
            }
            
            // Initial call
            handleResponsiveFooter();
            
            // Listen for resize events
            window.addEventListener('resize', handleResponsiveFooter);
        });
    </script>
</body>
</html>