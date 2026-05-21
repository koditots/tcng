<?php
// study-abroad.php
$page_title = "Study Abroad - Get Admission & Visa Guidance";
require_once 'includes/header.php';
?>

<!-- Hero Section -->
<section class="study-hero">
    <div class="container">
        <div class="hero-content">
            <div class="hero-text animate-fade-left">
                <h1 class="hero-title">Study Abroad with <?php echo getSiteSetting($pdo, 'site_name'); ?></h1>
                <p class="hero-subtitle">Get admission, visa guidance, and a smooth arrival from start to finish.</p>
                <p class="hero-description">Nigerian students (and others) trust us to turn study-abroad plans into reality. Since 2021, we've helped applicants secure offers, visas, housing, and flights across the UK, Canada, USA, Ireland, Australia, the EU and more.</p>
                <div class="hero-buttons">
                    <a href="#assessment" class="btn btn-primary">Start Your Journey</a>
                    <a href="#services" class="btn btn-outline">Our Services</a>
                </div>
            </div>
            <div class="hero-image animate-fade-right">
                <div class="floating-elements">
                    <div class="floating-card card-1">
                        <span class="card-icon">🎓</span>
                        <span>Admission Success</span>
                    </div>
                    <div class="floating-card card-2">
                        <span class="card-icon">✈️</span>
                        <span>Flight Booking</span>
                    </div>
                    <div class="floating-card card-3">
                        <span class="card-icon">🏠</span>
                        <span>Housing</span>
                    </div>
                </div>
                <img src="https://images.unsplash.com/photo-1523050854058-8df90110c9f1?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80" alt="Students studying abroad" class="hero-main-image">
            </div>
        </div>
    </div>
</section>

<!-- Why Choose Us Section -->
<section class="why-choose-section">
    <div class="container">
        <div class="section-header animate-fade-up">
            <h2 class="section-title">Why Choose <?php echo getSiteSetting($pdo, 'site_name'); ?></h2>
            <p class="section-subtitle">Comprehensive support for your study abroad journey</p>
        </div>
        
        <div class="features-grid">
            <div class="feature-card animate-fade-in" data-delay="100">
                <div class="feature-icon">🔄</div>
                <h3 class="feature-title">End-to-End Support</h3>
                <p class="feature-description">School shortlist → Applications → Offer/LoA → Visa coaching → Housing → Flights → Airport pickup.</p>
            </div>
            
            <div class="feature-card animate-fade-in" data-delay="200">
                <div class="feature-icon">🎯</div>
                <h3 class="feature-title">High-Acceptance Matching</h3>
                <p class="feature-description">We recommend schools and intakes that fit your profile, budget, and timelines.</p>
            </div>
            
            <div class="feature-card animate-fade-in" data-delay="300">
                <div class="feature-icon">📝</div>
                <h3 class="feature-title">Document Coaching</h3>
                <p class="feature-description">We help craft strong SOP/Personal Statement, CV, referee letters, and sponsor letters.</p>
            </div>
            
            <div class="feature-card animate-fade-in" data-delay="400">
                <div class="feature-icon">💰</div>
                <h3 class="feature-title">Proof-of-Funds Guidance</h3>
                <p class="feature-description">Advice on exact amounts, acceptable formats, and duration for fund holding.</p>
            </div>
            
            <div class="feature-card animate-fade-in" data-delay="500">
                <div class="feature-icon">🔍</div>
                <h3 class="feature-title">Transparent Fees</h3>
                <p class="feature-description">Clear administration and service fees with no hidden charges.</p>
            </div>
            
            <div class="feature-card animate-fade-in" data-delay="600">
                <div class="feature-icon">⭐</div>
                <h3 class="feature-title">Professional Preparation</h3>
                <p class="feature-description">We prepare strong files that stand out to institutions and visa officers.</p>
            </div>
        </div>
    </div>
</section>

<!-- Services Section -->
<section class="services-section" id="services">
    <div class="container">
        <div class="section-header animate-fade-up">
            <h2 class="section-title">What We Do For You</h2>
            <p class="section-subtitle">Comprehensive services for your study abroad success</p>
        </div>
        
        <div class="services-grid">
            <div class="service-card animate-slide-left" data-delay="100">
                <div class="service-number">01</div>
                <div class="service-content">
                    <h3 class="service-title">Profile & Course Match</h3>
                    <p class="service-description">We suggest 3–6 programs that fit your grades, budget, and goals.</p>
                </div>
                <div class="service-icon">🎓</div>
            </div>
            
            <div class="service-card animate-slide-right" data-delay="200">
                <div class="service-number">02</div>
                <div class="service-content">
                    <h3 class="service-title">Application Management</h3>
                    <p class="service-description">We complete forms, upload documents, and track every application.</p>
                </div>
                <div class="service-icon">📋</div>
            </div>
            
            <div class="service-card animate-slide-left" data-delay="300">
                <div class="service-number">03</div>
                <div class="service-content">
                    <h3 class="service-title">Offer to LoA/CAS</h3>
                    <p class="service-description">We help you meet conditions, pay deposits, and pass credibility/GTE checks.</p>
                </div>
                <div class="service-icon">✅</div>
            </div>
            
            <div class="service-card animate-slide-right" data-delay="400">
                <div class="service-number">04</div>
                <div class="service-content">
                    <h3 class="service-title">Visa File Build</h3>
                    <p class="service-description">Country-specific checklist, proof-of-funds review, biometrics/interview prep.</p>
                </div>
                <div class="service-icon">🛂</div>
            </div>
            
            <div class="service-card animate-slide-left" data-delay="500">
                <div class="service-number">05</div>
                <div class="service-content">
                    <h3 class="service-title">Accommodation & Flights</h3>
                    <p class="service-description">Student housing options, baggage-friendly routes, airport pickup.</p>
                </div>
                <div class="service-icon">🏠✈️</div>
            </div>
        </div>
    </div>
</section>

<!-- Countries Section -->
<section class="countries-section">
    <div class="container">
        <div class="section-header animate-fade-up">
            <h2 class="section-title">Countries We Cover</h2>
            <p class="section-subtitle">Global opportunities for your education journey</p>
        </div>
        
        <div class="countries-grid">
            <?php
            $countries = [
                ['name' => 'United Kingdom', 'flag' => '🇬🇧', 'image' => 'https://images.unsplash.com/photo-1513635269975-59663e0ac1ad?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80'],
                ['name' => 'Canada', 'flag' => '🇨🇦', 'image' => 'https://images.unsplash.com/photo-1519832979-6fa011b87667?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80'],
                ['name' => 'United States', 'flag' => '🇺🇸', 'image' => 'https://images.unsplash.com/photo-1485738422979-f5c462d49f74?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80'],
                ['name' => 'Ireland', 'flag' => '🇮🇪', 'image' => 'https://images.unsplash.com/photo-1506905925340-14faa638f5fb?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80'],
                ['name' => 'Australia', 'flag' => '🇦🇺', 'image' => 'https://images.unsplash.com/photo-1506905925340-14faa638f5fb?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80'],
                ['name' => 'New Zealand', 'flag' => '🇳🇿', 'image' => 'https://images.unsplash.com/photo-1507699622108-4be3abd695ad?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80'],
                ['name' => 'Germany', 'flag' => '🇩🇪', 'image' => 'https://images.unsplash.com/photo-1467269204594-9661b134dd2b?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80'],
                ['name' => 'Netherlands', 'flag' => '🇳🇱', 'image' => 'https://images.unsplash.com/photo-1512470876302-972faa2aa9a4?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80']
            ];
            
            foreach ($countries as $index => $country) {
                echo '
                <div class="country-card animate-zoom-in" data-delay="' . ($index * 100) . '">
                    <div class="country-image">
                        <img src="' . $country['image'] . '" alt="' . $country['name'] . '">
                        <div class="country-overlay">
                            <span class="country-flag">' . $country['flag'] . '</span>
                            <h4 class="country-name">' . $country['name'] . '</h4>
                        </div>
                    </div>
                </div>';
            }
            ?>
        </div>
        
        <div class="countries-note animate-fade-up" data-delay="800">
            <p>We can't list every country here. But if you don't see your target country? Tell us more, and we may have a partner route.</p>
            <div class="contact-input">
                <input type="text" placeholder="Enter your target country..." class="form-control">
                <button class="btn btn-primary">Send Inquiry</button>
            </div>
        </div>
    </div>
</section>

<!-- Process Section -->
<section class="process-section">
    <div class="container">
        <div class="section-header animate-fade-up">
            <h2 class="section-title">How It Works</h2>
            <p class="section-subtitle">Simple steps to your study abroad success</p>
        </div>
        
        <div class="process-steps">
            <div class="process-step animate-fade-in" data-delay="100">
                <div class="step-number">1</div>
                <div class="step-content">
                    <h3 class="step-title">Professional Assessment</h3>
                    <p class="step-description">Tell us more about your course, budget, grades, and intake.</p>
                </div>
                <div class="step-connector"></div>
            </div>
            
            <div class="process-step animate-fade-in" data-delay="200">
                <div class="step-number">2</div>
                <div class="step-content">
                    <h3 class="step-title">Shortlist & Plan</h3>
                    <p class="step-description">Get recommended schools, fees, timelines, and fund requirements.</p>
                </div>
                <div class="step-connector"></div>
            </div>
            
            <div class="process-step animate-fade-in" data-delay="300">
                <div class="step-number">3</div>
                <div class="step-content">
                    <h3 class="step-title">Apply</h3>
                    <p class="step-description">We prepare documents and submit your applications.</p>
                </div>
                <div class="step-connector"></div>
            </div>
            
            <div class="process-step animate-fade-in" data-delay="400">
                <div class="step-number">4</div>
                <div class="step-content">
                    <h3 class="step-title">Offer & LoA/CAS</h3>
                    <p class="step-description">Complete conditions, deposits, and compliance checks.</p>
                </div>
                <div class="step-connector"></div>
            </div>
            
            <div class="process-step animate-fade-in" data-delay="500">
                <div class="step-number">5</div>
                <div class="step-content">
                    <h3 class="step-title">Visa Coaching</h3>
                    <p class="step-description">Build a compliant file; book biometrics/interview and prepare your answers.</p>
                </div>
                <div class="step-connector"></div>
            </div>
            
            <div class="process-step animate-fade-in" data-delay="600">
                <div class="step-number">6</div>
                <div class="step-content">
                    <h3 class="step-title">Ready to Travel</h3>
                    <p class="step-description">Housing, flights, and pickup arranged. (Optional)</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Requirements Section -->
<section class="requirements-section">
    <div class="container">
        <div class="requirements-content">
            <div class="requirements-text animate-fade-left">
                <h2 class="section-title">Entry Requirements & Documents</h2>
                <div class="requirements-list">
                    <div class="requirement-item">
                        <span class="requirement-icon">📘</span>
                        <span>Valid passport (6+ months to expiry)</span>
                    </div>
                    <div class="requirement-item">
                        <span class="requirement-icon">📄</span>
                        <span>Transcripts/certificates (WAEC/NECO, ND/HND, BSc/BA, etc.)</span>
                    </div>
                    <div class="requirement-item">
                        <span class="requirement-icon">🔤</span>
                        <span>English proficiency (IELTS/TOEFL/Duolingo or MOI)</span>
                    </div>
                    <div class="requirement-item">
                        <span class="requirement-icon">📝</span>
                        <span>SOP/Personal statement, CV, referee letters</span>
                    </div>
                    <div class="requirement-item">
                        <span class="requirement-icon">💰</span>
                        <span>Proof of funds (self or sponsor), tuition deposit</span>
                    </div>
                    <div class="requirement-item">
                        <span class="requirement-icon">🏥</span>
                        <span>Health insurance/medical checks (country-specific)</span>
                    </div>
                </div>
            </div>
            <div class="requirements-image animate-fade-right">
                <img src="https://images.unsplash.com/photo-1582573618380-1c43d5d1ff1c?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80" alt="Study documents">
            </div>
        </div>
    </div>
</section>

<!-- Benefits Section -->
<section class="benefits-section">
    <div class="container">
        <div class="section-header animate-fade-up">
            <h2 class="section-title">What You'll Get From Us</h2>
            <p class="section-subtitle">Comprehensive support for your study abroad journey</p>
        </div>
        
        <div class="benefits-grid">
            <div class="benefit-card animate-zoom-in" data-delay="100">
                <div class="benefit-icon">🗺️</div>
                <h3 class="benefit-title">Realistic Roadmap</h3>
                <p class="benefit-description">A clear admission & visa roadmap for your intake based on your program.</p>
            </div>
            
            <div class="benefit-card animate-zoom-in" data-delay="200">
                <div class="benefit-icon">📋</div>
                <h3 class="benefit-title">Professional Documents</h3>
                <p class="benefit-description">Professionally reviewed documents that tell your story clearly.</p>
            </div>
            
            <div class="benefit-card animate-zoom-in" data-delay="300">
                <div class="benefit-icon">📱</div>
                <h3 class="benefit-title">Regular Updates</h3>
                <p class="benefit-description">WhatsApp and Email updates at every milestone.</p>
            </div>
            
            <div class="benefit-card animate-zoom-in" data-delay="400">
                <div class="benefit-icon">😌</div>
                <h3 class="benefit-title">Organized Process</h3>
                <p class="benefit-description">A calm, organized process from offer to arrival.</p>
            </div>
        </div>
    </div>
</section>

<!-- FAQ Section -->
<section class="faq-section">
    <div class="container">
        <div class="section-header animate-fade-up">
            <h2 class="section-title">Frequently Asked Questions</h2>
            <p class="section-subtitle">Get answers to common questions about studying abroad</p>
        </div>
        
        <div class="faq-grid">
            <div class="faq-item animate-fade-in" data-delay="100">
                <div class="faq-question">
                    <h3>Do you guarantee visas?</h3>
                    <span class="faq-toggle">+</span>
                </div>
                <div class="faq-answer">
                    <p>No one can guarantee visas. We don't guarantee outcomes, but we strengthen your file with accurate documents and preparation, which could give you a better chance.</p>
                </div>
            </div>
            
            <div class="faq-item animate-fade-in" data-delay="200">
                <div class="faq-question">
                    <h3>Do I need IELTS?</h3>
                    <span class="faq-toggle">+</span>
                </div>
                <div class="faq-answer">
                    <p>Not necessarily. Many schools may accept MOI (Medium of Instruction) or Duolingo. We'll shortlist schools based on your profile and language requirements.</p>
                </div>
            </div>
            
            <div class="faq-item animate-fade-in" data-delay="300">
                <div class="faq-question">
                    <h3>How much proof of funds do I need?</h3>
                    <span class="faq-toggle">+</span>
                </div>
                <div class="faq-answer">
                    <p>The proof of funds depends on the country and city. We'll give you the exact figure and acceptable format for your offer after admission has been confirmed.</p>
                </div>
            </div>
            
            <div class="faq-item animate-fade-in" data-delay="400">
                <div class="faq-question">
                    <h3>Can my spouse/children join me?</h3>
                    <span class="faq-toggle">+</span>
                </div>
                <div class="faq-answer">
                    <p>Yes, it's possible in many countries. We'll advise on rules and timing depending on the specific country's regulations for dependent visas.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="cta-section" id="assessment">
    <div class="container">
        <div class="cta-content animate-fade-up">
            <h2 class="cta-title">Ready to Start Your Journey?</h2>
            <p class="cta-description">To continue, you'll be connected with our Study Consultant. We'll assess your goals, shortlist schools, and give you a clear plan for the next intake.</p>
            
            <div class="contact-methods">
                <div class="contact-method animate-zoom-in" data-delay="100">
                    <div class="contact-icon">📧</div>
                    <h3 class="contact-title">Send an Email</h3>
                    <p class="contact-detail">Austin</p>
                    <a href="mailto:austin@travelcentre.ng" class="btn btn-outline">austin@travelcentre.ng</a>
                </div>
                
                <div class="contact-method animate-zoom-in" data-delay="200">
                    <div class="contact-icon">💬</div>
                    <h3 class="contact-title">Chat on WhatsApp</h3>
                    <p class="contact-detail">Austin</p>
                    <a href="https://wa.me/2349034072383" class="btn btn-primary">+234 903 407 2383</a>
                </div>
            </div>
            
            <div class="cta-note animate-fade-up" data-delay="300">
                <p>Send us an email or WhatsApp message with your details to get started today!</p>
            </div>
        </div>
    </div>
</section>

<style>
    /* CSS Variables for Study Abroad Page */
    :root {
        --study-primary: #4f46e5;
        --study-secondary: #7c3aed;
        --study-accent: #06b6d4;
        --study-success: #10b981;
        --study-warning: #f59e0b;
        --study-gradient: linear-gradient(135deg, #4f46e5 0%, #7c3aed 50%, #06b6d4 100%);
        --study-gradient-light: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
        --shadow-study: 0 10px 40px rgba(79, 70, 229, 0.15);
    }

    /* Study Hero Section */
    .study-hero {
        background: var(--study-gradient);
        color: white;
        padding: 6rem 0;
        position: relative;
        overflow: hidden;
    }

    .study-hero::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><polygon fill="rgba(255,255,255,0.05)" points="0,1000 1000,0 1000,1000"/></svg>');
        background-size: cover;
    }

    .hero-content {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 4rem;
        align-items: center;
        position: relative;
        z-index: 2;
    }

    .hero-title {
        font-size: 3.5rem;
        font-weight: 800;
        margin-bottom: 1.5rem;
        line-height: 1.2;
        background: linear-gradient(45deg, #fff, #e0e7ff);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .hero-subtitle {
        font-size: 1.5rem;
        font-weight: 600;
        margin-bottom: 1rem;
        opacity: 0.95;
    }

    .hero-description {
        font-size: 1.1rem;
        line-height: 1.7;
        margin-bottom: 2.5rem;
        opacity: 0.9;
    }

    .hero-buttons {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .hero-image {
        position: relative;
        text-align: center;
    }

    .hero-main-image {
        width: 100%;
        max-width: 500px;
        border-radius: 20px;
        box-shadow: var(--shadow-study);
        transform: perspective(1000px) rotateY(-5deg) rotateX(5deg);
        transition: transform 0.5s ease;
    }

    .hero-image:hover .hero-main-image {
        transform: perspective(1000px) rotateY(0) rotateX(0);
    }

    .floating-elements {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
    }

    .floating-card {
        position: absolute;
        background: rgba(255, 255, 255, 0.95);
        color: var(--study-primary);
        padding: 1rem 1.5rem;
        border-radius: 15px;
        box-shadow: var(--shadow-study);
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-weight: 600;
        animation: float 6s ease-in-out infinite;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .card-1 {
        top: 10%;
        left: -10%;
        animation-delay: 0s;
    }

    .card-2 {
        top: 50%;
        right: -5%;
        animation-delay: 2s;
    }

    .card-3 {
        bottom: 20%;
        left: 5%;
        animation-delay: 4s;
    }

    @keyframes float {
        0%, 100% { transform: translateY(0) rotate(0deg); }
        50% { transform: translateY(-20px) rotate(5deg); }
    }

    /* Why Choose Us Section */
    .why-choose-section {
        padding: 6rem 0;
        background: #f8fafc;
    }

    .features-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 2rem;
    }

    .feature-card {
        background: white;
        padding: 2.5rem 2rem;
        border-radius: 20px;
        box-shadow: var(--shadow-study);
        text-align: center;
        transition: all 0.3s ease;
        border: 1px solid #e2e8f0;
        position: relative;
        overflow: hidden;
    }

    .feature-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--study-gradient);
        transform: scaleX(0);
        transition: transform 0.3s ease;
    }

    .feature-card:hover::before {
        transform: scaleX(1);
    }

    .feature-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 20px 60px rgba(79, 70, 229, 0.2);
    }

    .feature-icon {
        font-size: 3.5rem;
        margin-bottom: 1.5rem;
        display: block;
    }

    .feature-title {
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 1rem;
        color: var(--study-primary);
    }

    .feature-description {
        color: #64748b;
        line-height: 1.6;
    }

    /* Services Section */
    .services-section {
        padding: 6rem 0;
        background: white;
    }

    .services-grid {
        display: grid;
        gap: 1.5rem;
        max-width: 800px;
        margin: 0 auto;
    }

    .service-card {
        background: white;
        padding: 2rem;
        border-radius: 15px;
        box-shadow: var(--shadow-study);
        display: flex;
        align-items: center;
        gap: 1.5rem;
        transition: all 0.3s ease;
        border-left: 4px solid var(--study-primary);
    }

    .service-card:hover {
        transform: translateX(10px);
        box-shadow: 0 15px 50px rgba(79, 70, 229, 0.2);
    }

    .service-number {
        font-size: 2rem;
        font-weight: 800;
        color: var(--study-primary);
        opacity: 0.3;
    }

    .service-content {
        flex: 1;
    }

    .service-title {
        font-size: 1.3rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        color: var(--study-primary);
    }

    .service-description {
        color: #64748b;
        line-height: 1.6;
    }

    .service-icon {
        font-size: 2.5rem;
        opacity: 0.7;
    }

    /* Countries Section */
    .countries-section {
        padding: 6rem 0;
        background: #f8fafc;
    }

    .countries-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 3rem;
    }

    .country-card {
        background: white;
        border-radius: 15px;
        overflow: hidden;
        box-shadow: var(--shadow-study);
        transition: all 0.3s ease;
    }

    .country-card:hover {
        transform: translateY(-5px) scale(1.02);
        box-shadow: 0 20px 60px rgba(79, 70, 229, 0.3);
    }

    .country-image {
        position: relative;
        height: 200px;
        overflow: hidden;
    }

    .country-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s ease;
    }

    .country-card:hover .country-image img {
        transform: scale(1.1);
    }

    .country-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(to bottom, transparent, rgba(79, 70, 229, 0.8));
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        padding: 1.5rem;
        color: white;
    }

    .country-flag {
        font-size: 2rem;
        margin-bottom: 0.5rem;
    }

    .country-name {
        font-size: 1.2rem;
        font-weight: 700;
        margin: 0;
    }

    .countries-note {
        text-align: center;
        background: white;
        padding: 2rem;
        border-radius: 15px;
        box-shadow: var(--shadow-study);
    }

    .contact-input {
        display: flex;
        gap: 1rem;
        max-width: 500px;
        margin: 1.5rem auto 0;
    }

    /* Process Section */
    .process-section {
        padding: 6rem 0;
        background: white;
    }

    .process-steps {
        max-width: 800px;
        margin: 0 auto;
        position: relative;
    }

    .process-step {
        display: flex;
        align-items: center;
        margin-bottom: 3rem;
        position: relative;
    }

    .step-number {
        width: 60px;
        height: 60px;
        background: var(--study-gradient);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.5rem;
        font-weight: 700;
        flex-shrink: 0;
        margin-right: 2rem;
        position: relative;
        z-index: 2;
        box-shadow: var(--shadow-study);
    }

    .step-content {
        flex: 1;
        background: #f8fafc;
        padding: 2rem;
        border-radius: 15px;
        border-left: 4px solid var(--study-primary);
    }

    .step-title {
        font-size: 1.3rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        color: var(--study-primary);
    }

    .step-description {
        color: #64748b;
        line-height: 1.6;
        margin: 0;
    }

    .step-connector {
        position: absolute;
        left: 30px;
        top: 60px;
        bottom: -3rem;
        width: 2px;
        background: var(--study-primary);
        opacity: 0.3;
    }

    .process-step:last-child .step-connector {
        display: none;
    }

    /* Requirements Section */
    .requirements-section {
        padding: 6rem 0;
        background: var(--study-gradient);
        color: white;
    }

    .requirements-content {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 4rem;
        align-items: center;
    }

    .requirements-section .section-title {
        color: white;
        font-size: 2.5rem;
        margin-bottom: 2rem;
    }

    .requirements-list {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .requirement-item {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem 1.5rem;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 10px;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        transition: all 0.3s ease;
    }

    .requirement-item:hover {
        background: rgba(255, 255, 255, 0.2);
        transform: translateX(10px);
    }

    .requirement-icon {
        font-size: 1.5rem;
        flex-shrink: 0;
    }

    .requirements-image img {
        width: 100%;
        border-radius: 20px;
        box-shadow: var(--shadow-study);
    }

    /* Benefits Section */
    .benefits-section {
        padding: 6rem 0;
        background: #f8fafc;
    }

    .benefits-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 2rem;
    }

    .benefit-card {
        background: white;
        padding: 2.5rem 2rem;
        border-radius: 20px;
        text-align: center;
        box-shadow: var(--shadow-study);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .benefit-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: var(--study-gradient);
        opacity: 0;
        transition: opacity 0.3s ease;
        z-index: 1;
    }

    .benefit-card:hover::before {
        opacity: 0.05;
    }

    .benefit-card:hover {
        transform: translateY(-10px) scale(1.05);
    }

    .benefit-icon {
        font-size: 3rem;
        margin-bottom: 1.5rem;
        position: relative;
        z-index: 2;
    }

    .benefit-title {
        font-size: 1.3rem;
        font-weight: 700;
        margin-bottom: 1rem;
        color: var(--study-primary);
        position: relative;
        z-index: 2;
    }

    .benefit-description {
        color: #64748b;
        line-height: 1.6;
        position: relative;
        z-index: 2;
    }

    /* FAQ Section */
    .faq-section {
        padding: 6rem 0;
        background: white;
    }

    .faq-grid {
        max-width: 800px;
        margin: 0 auto;
    }

    .faq-item {
        background: white;
        border-radius: 15px;
        box-shadow: var(--shadow-study);
        margin-bottom: 1rem;
        overflow: hidden;
        border: 1px solid #e2e8f0;
    }

    .faq-question {
        padding: 1.5rem 2rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        cursor: pointer;
        transition: background-color 0.3s ease;
    }

    .faq-question:hover {
        background: #f8fafc;
    }

    .faq-question h3 {
        margin: 0;
        font-size: 1.2rem;
        font-weight: 600;
        color: var(--study-primary);
    }

    .faq-toggle {
        font-size: 1.5rem;
        font-weight: 300;
        transition: transform 0.3s ease;
    }

    .faq-answer {
        padding: 0 2rem;
        max-height: 0;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .faq-item.active .faq-answer {
        padding: 0 2rem 1.5rem;
        max-height: 200px;
    }

    .faq-item.active .faq-toggle {
        transform: rotate(45deg);
    }

    /* CTA Section */
    .cta-section {
        padding: 6rem 0;
        background: var(--study-gradient);
        color: white;
        text-align: center;
    }

    .cta-title {
        font-size: 3rem;
        font-weight: 800;
        margin-bottom: 1.5rem;
    }

    .cta-description {
        font-size: 1.3rem;
        margin-bottom: 3rem;
        opacity: 0.9;
        max-width: 600px;
        margin-left: auto;
        margin-right: auto;
    }

    .contact-methods {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 2rem;
        max-width: 800px;
        margin: 0 auto 3rem;
    }

    .contact-method {
        background: rgba(255, 255, 255, 0.1);
        padding: 2.5rem 2rem;
        border-radius: 20px;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        transition: all 0.3s ease;
    }

    .contact-method:hover {
        background: rgba(255, 255, 255, 0.2);
        transform: translateY(-10px);
    }

    .contact-icon {
        font-size: 3rem;
        margin-bottom: 1rem;
    }

    .contact-title {
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
    }

    .contact-detail {
        font-size: 1.1rem;
        margin-bottom: 1.5rem;
        opacity: 0.9;
    }

    .cta-note {
        background: rgba(255, 255, 255, 0.1);
        padding: 1.5rem;
        border-radius: 15px;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    /* Animations */
    .animate-fade-left {
        opacity: 0;
        transform: translateX(-50px);
        animation: fadeLeft 0.8s ease forwards;
    }

    .animate-fade-right {
        opacity: 0;
        transform: translateX(50px);
        animation: fadeRight 0.8s ease forwards;
    }

    .animate-zoom-in {
        opacity: 0;
        transform: scale(0.8);
        animation: zoomIn 0.6s ease forwards;
    }

    .animate-slide-left {
        opacity: 0;
        transform: translateX(-30px);
        animation: slideLeft 0.6s ease forwards;
    }

    .animate-slide-right {
        opacity: 0;
        transform: translateX(30px);
        animation: slideRight 0.6s ease forwards;
    }

    @keyframes fadeLeft {
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    @keyframes fadeRight {
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    @keyframes zoomIn {
        to {
            opacity: 1;
            transform: scale(1);
        }
    }

    @keyframes slideLeft {
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    @keyframes slideRight {
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .hero-content {
            grid-template-columns: 1fr;
            gap: 2rem;
            text-align: center;
        }

        .hero-title {
            font-size: 2.5rem;
        }

        .hero-buttons {
            justify-content: center;
        }

        .floating-card {
            position: relative;
            margin: 1rem auto;
            width: fit-content;
        }

        .card-1, .card-2, .card-3 {
            position: relative;
            top: auto;
            left: auto;
            right: auto;
            bottom: auto;
        }

        .features-grid {
            grid-template-columns: 1fr;
        }

        .services-grid {
            grid-template-columns: 1fr;
        }

        .service-card {
            flex-direction: column;
            text-align: center;
        }

        .countries-grid {
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        }

        .process-step {
            flex-direction: column;
            text-align: center;
        }

        .step-number {
            margin-right: 0;
            margin-bottom: 1rem;
        }

        .step-connector {
            left: 50%;
            top: 60px;
            bottom: -3rem;
            height: 2rem;
            width: 2px;
        }

        .requirements-content {
            grid-template-columns: 1fr;
            gap: 2rem;
        }

        .contact-input {
            flex-direction: column;
        }

        .contact-methods {
            grid-template-columns: 1fr;
        }
    }
</style>

<script>
    // Initialize animations
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize animations with delays
        const animatedElements = document.querySelectorAll('[class*="animate-"]');
        animatedElements.forEach(element => {
            const delay = element.getAttribute('data-delay') || 0;
            element.style.animationDelay = delay + 'ms';
        });

        // FAQ functionality
        const faqItems = document.querySelectorAll('.faq-item');
        faqItems.forEach(item => {
            const question = item.querySelector('.faq-question');
            question.addEventListener('click', () => {
                item.classList.toggle('active');
            });
        });

        // Country inquiry form
        const inquiryButton = document.querySelector('.countries-note .btn');
        const inquiryInput = document.querySelector('.countries-note .form-control');
        
        inquiryButton.addEventListener('click', function() {
            const country = inquiryInput.value.trim();
            if (country) {
                alert(`Thank you for your interest in ${country}! Our study consultant will contact you shortly with available options.`);
                inquiryInput.value = '';
            } else {
                alert('Please enter a country name to continue.');
            }
        });

        // Smooth scrolling for anchor links
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

        // Intersection Observer for animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.animationPlayState = 'running';
                }
            });
        }, observerOptions);

        animatedElements.forEach(element => {
            observer.observe(element);
        });
    });

    // Email functionality
    function sendEmail() {
        const email = 'austin@travelcentre.ng';
        const subject = 'Study Abroad Inquiry';
        const body = 'Hello Austin,\n\nI am interested in studying abroad and would like to get more information about your services.\n\nPlease contact me with details.\n\nBest regards,';
        
        window.location.href = `mailto:${email}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
    }

    // WhatsApp functionality
    function openWhatsApp() {
        const number = '2349034072383';
        const message = 'Hello Austin, I am interested in studying abroad and would like to get more information about your services.';
        
        window.open(`https://wa.me/${number}?text=${encodeURIComponent(message)}`, '_blank');
    }
</script>

<?php
require_once 'includes/footer.php';
?>