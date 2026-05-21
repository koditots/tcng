<?php
// payment.php
$page_title = "How to Pay - Secure Payment Methods";
require_once 'includes/header.php';
?>

<!-- Hero Section -->
<section class="payment-hero">
    <div class="container">
        <div class="hero-content">
            <div class="hero-text animate-fade-left">
                <h1 class="hero-title">How to Pay</h1>
                <p class="hero-subtitle">Secure & Simple Payment Methods</p>
                <p class="hero-description">At TravelCentre.ng, payments are simple and secure. You can pay by bank transfer or by card via Paystack. Your card details are encrypted and safe, and every payment is matched to your booking reference.</p>
                
                <div class="trust-badges">
                    <div class="trust-badge">
                        <div class="badge-icon">🛡️</div>
                        <div class="badge-content">
                            <span class="badge-title">NANTA-Certified</span>
                            <span class="badge-subtitle">Professional Standards</span>
                        </div>
                    </div>
                    <div class="trust-badge">
                        <div class="badge-icon">📊</div>
                        <div class="badge-content">
                            <span class="badge-title">50,000+</span>
                            <span class="badge-subtitle">Bookings Since 2021</span>
                        </div>
                    </div>
                    <div class="trust-badge">
                        <div class="badge-icon">🔒</div>
                        <div class="badge-content">
                            <span class="badge-title">Secure</span>
                            <span class="badge-subtitle">PCI-DSS Compliant</span>
                        </div>
                    </div>
                </div>

                <div class="security-alert">
                    <div class="alert-icon">⚠️</div>
                    <div class="alert-content">
                        <strong>No-Scam Zone:</strong> Our only receiving account name is <strong>Hotel Online Reservation</strong> (Our parent company)
                    </div>
                </div>
            </div>
            <div class="hero-image animate-fade-right">
                <div class="payment-methods-visual">
                    <div class="method-card bank-transfer">
                        <div class="method-icon">🏦</div>
                        <span>Bank Transfer</span>
                    </div>
                    <div class="method-card card-payment">
                        <div class="method-icon">💳</div>
                        <span>Card Payment</span>
                    </div>
                    <div class="secure-shield">
                        <div class="shield-icon">🔒</div>
                        <span>100% Secure</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Bank Transfer Section -->
<section class="bank-transfer-section">
    <div class="container">
        <div class="section-header animate-fade-up">
            <div class="section-icon">🏦</div>
            <h2 class="section-title">Bank Transfer</h2>
            <p class="section-subtitle">Direct bank transfers to our verified accounts</p>
        </div>

        <div class="bank-accounts">
            <div class="bank-account animate-zoom-in" data-delay="100">
                <div class="bank-logo fidelity"></div>
                <div class="account-details">
                    <h3 class="bank-name">FIDELITY BANK</h3>
                    <div class="account-info">
                        <div class="info-row">
                            <span class="info-label">Account Name:</span>
                            <span class="info-value">Hotel Online Reservation</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Account Number:</span>
                            <span class="info-value copyable" data-copy="5600742746">5600742746</span>
                            <button class="copy-btn" onclick="copyToClipboard('5600742746')">📋</button>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Currency:</span>
                            <span class="info-value">NGN</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bank-account animate-zoom-in" data-delay="200">
                <div class="bank-logo access"></div>
                <div class="account-details">
                    <h3 class="bank-name">ACCESS BANK</h3>
                    <div class="account-info">
                        <div class="info-row">
                            <span class="info-label">Account Name:</span>
                            <span class="info-value">Hotel Online Reservation</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Account Number:</span>
                            <span class="info-value copyable" data-copy="1571813880">1571813880</span>
                            <button class="copy-btn" onclick="copyToClipboard('1571813880')">📋</button>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Currency:</span>
                            <span class="info-value">NGN</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="transfer-steps">
            <h3 class="steps-title animate-fade-up">How to pay by transfer (step-by-step)</h3>
            <div class="steps-grid">
                <div class="step animate-fade-in" data-delay="100">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <h4>Request or confirm your invoice/booking reference</h4>
                        <p>Get your unique reference (e.g., TC-2025-00123) from your consultant</p>
                    </div>
                </div>
                <div class="step animate-fade-in" data-delay="200">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <h4>Transfer the amount</h4>
                        <p>Send the exact amount to Hotel Online Reservation account</p>
                    </div>
                </div>
                <div class="step animate-fade-in" data-delay="300">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <h4>Add reference in narration</h4>
                        <p>Include "TC-2025-00123 – Surname" in transfer description</p>
                    </div>
                </div>
                <div class="step animate-fade-in" data-delay="400">
                    <div class="step-number">4</div>
                    <div class="step-content">
                        <h4>Send proof of payment</h4>
                        <p>Share transfer receipt via WhatsApp or email</p>
                    </div>
                </div>
                <div class="step animate-fade-in" data-delay="500">
                    <div class="step-number">5</div>
                    <div class="step-content">
                        <h4>Receive confirmation</h4>
                        <p>Get receipt and booking status update once confirmed</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="processing-info">
            <div class="info-card animate-fade-up" data-delay="600">
                <h4>Processing Time</h4>
                <ul>
                    <li><strong>Same-bank transfers:</strong> Usually instant to 1 hour</li>
                    <li><strong>Inter-bank transfers:</strong> Up to 12–24 hours in some cases</li>
                </ul>
            </div>
            <div class="warning-card animate-fade-up" data-delay="700">
                <div class="warning-icon">⚠️</div>
                <div class="warning-content">
                    <h4>Important Security Notice</h4>
                    <p>If the account name shown by your banking app is not "Hotel Online Reservation", <strong>stop and contact us</strong> before paying.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Card Payment Section -->
<section class="card-payment-section">
    <div class="container">
        <div class="section-header animate-fade-up">
            <div class="section-icon">💳</div>
            <h2 class="section-title">Card Payment (Paystack)</h2>
            <p class="section-subtitle">Secure online payments with instant confirmation</p>
        </div>

        <div class="card-payment-content">
            <div class="payment-security animate-fade-left">
                <div class="security-header">
                    <div class="paystack-logo"></div>
                    <h3>PCI-DSS Certified Processor</h3>
                </div>
                <p>We use Paystack, a PCI-DSS certified processor. Your card number is handled by Paystack's secure, encrypted systems and not by us.</p>
                
                <div class="security-features">
                    <div class="security-feature">
                        <div class="feature-icon">🔐</div>
                        <span>3-D Secure/OTP verification</span>
                    </div>
                    <div class="security-feature">
                        <div class="feature-icon">🏆</div>
                        <span>PCI-DSS Level 1 compliance</span>
                    </div>
                    <div class="security-feature">
                        <div class="feature-icon">🔒</div>
                        <span>TLS/SSL encryption end-to-end</span>
                    </div>
                </div>
            </div>

            <div class="card-steps animate-fade-right">
                <h3>How to pay by card</h3>
                <div class="card-step">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <h4>Request payment link</h4>
                        <p>Ask your consultant for a secure Paystack payment link</p>
                    </div>
                </div>
                <div class="card-step">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <h4>Enter details</h4>
                        <p>Fill in your information and booking reference</p>
                    </div>
                </div>
                <div class="card-step">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <h4>Complete payment</h4>
                        <p>Pay with Visa, Mastercard, or supported bank cards</p>
                    </div>
                </div>
                <div class="card-step">
                    <div class="step-number">4</div>
                    <div class="step-content">
                        <h4>Instant confirmation</h4>
                        <p>Receive instant receipt and automatic booking update</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FAQ Section -->
<section class="faq-section">
    <div class="container">
        <div class="section-header animate-fade-up">
            <h2 class="section-title">Frequently Asked Questions</h2>
            <p class="section-subtitle">Common questions about payments and security</p>
        </div>

        <div class="faq-grid">
            <div class="faq-item animate-fade-in" data-delay="100">
                <div class="faq-question">
                    <h3>Is my card safe?</h3>
                    <span class="faq-toggle">+</span>
                </div>
                <div class="faq-answer">
                    <p>Yes. Payments are processed by Paystack using bank-grade security. We never store your card details.</p>
                </div>
            </div>

            <div class="faq-item animate-fade-in" data-delay="200">
                <div class="faq-question">
                    <h3>Why is the account name "Hotel Online Reservation"?</h3>
                    <span class="faq-toggle">+</span>
                </div>
                <div class="faq-answer">
                    <p>That's our parent company and the only approved recipient for bank transfers.</p>
                </div>
            </div>

            <div class="faq-item animate-fade-in" data-delay="300">
                <div class="faq-question">
                    <h3>Can I pay in instalments?</h3>
                    <span class="faq-toggle">+</span>
                </div>
                <div class="faq-answer">
                    <p>Some bookings allow deposit + balance. Ask your consultant; rules vary by airline/hotel/school.</p>
                </div>
            </div>

            <div class="faq-item animate-fade-in" data-delay="400">
                <div class="faq-question">
                    <h3>How will I know you got my money?</h3>
                    <span class="faq-toggle">+</span>
                </div>
                <div class="faq-answer">
                    <p>You'll receive a payment receipt by WhatsApp/email and see your booking status move to Paid/Issued (for tickets) or Confirmed (for hotels/visas).</p>
                </div>
            </div>

            <div class="faq-item animate-fade-in" data-delay="500">
                <div class="faq-question">
                    <h3>What if I send to the wrong account?</h3>
                    <span class="faq-toggle">+</span>
                </div>
                <div class="faq-answer">
                    <p>Pause immediately and contact your bank. We can only verify payments received into "Hotel Online Reservation."</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Refunds Section -->
<section class="refunds-section">
    <div class="container">
        <div class="refunds-content animate-fade-up">
            <div class="section-icon">🔄</div>
            <h2 class="section-title">Refunds & Changes</h2>
            <p class="refunds-description">
                Refunds, reissues, and date changes follow the airline/hotel/school policy on your invoice. Where a refund is approved, it is returned via the original payment method (timeline varies by provider). Your consultant will share expected timelines in writing.
            </p>
        </div>
    </div>
</section>

<!-- Anti-Fraud Section -->
<section class="anti-fraud-section">
    <div class="container">
        <div class="section-header animate-fade-up">
            <div class="section-icon">🚫</div>
            <h2 class="section-title">Anti-Fraud Tips (No-Scam Zone)</h2>
            <p class="section-subtitle">Protect yourself from payment fraud</p>
        </div>

        <div class="fraud-tips">
            <div class="fraud-tip animate-fade-in" data-delay="100">
                <div class="tip-icon">👤</div>
                <div class="tip-content">
                    <h4>No Personal Accounts</h4>
                    <p>We do not use personal accounts or middlemen. Pay only to Hotel Online Reservation or through our official Paystack link.</p>
                </div>
            </div>

            <div class="fraud-tip animate-fade-in" data-delay="200">
                <div class="tip-icon">🔗</div>
                <div class="tip-content">
                    <h4>Verify Payment Links</h4>
                    <p>Confirm payment links directly from our official numbers and email addresses.</p>
                </div>
            </div>

            <div class="fraud-tip animate-fade-in" data-delay="300">
                <div class="tip-icon">📝</div>
                <div class="tip-content">
                    <h4>Use Booking Reference</h4>
                    <p>Keep your invoice/booking reference in every payment narration for proper tracking.</p>
                </div>
            </div>

            <div class="fraud-tip animate-fade-in" data-delay="400">
                <div class="tip-icon">⏰</div>
                <div class="tip-content">
                    <h4>No Rush Payments</h4>
                    <p>If anyone pressures you to "rush" a transfer to a different name, stop and call us immediately.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Help Section -->
<section class="help-section">
    <div class="container">
        <div class="help-content animate-fade-up">
            <h2 class="help-title">Need help before you pay?</h2>
            <p class="help-subtitle">We're happy to confirm bank details, resend your invoice, or generate a fresh Paystack link.</p>

            <div class="contact-methods">
                <div class="contact-method animate-zoom-in" data-delay="100">
                    <div class="contact-icon">💬</div>
                    <div class="contact-details">
                        <h3 class="contact-title">WhatsApp/Phone</h3>
                        <p class="contact-number">+234 903 407 2383</p>
                        <a href="https://wa.me/2349034072383" class="btn btn-primary">Chat on WhatsApp</a>
                    </div>
                </div>

                <div class="contact-method animate-zoom-in" data-delay="200">
                    <div class="contact-icon">📧</div>
                    <div class="contact-details">
                        <h3 class="contact-title">Email</h3>
                        <p class="contact-email">info@travelcentre.ng</p>
                        <a href="mailto:info@travelcentre.ng" class="btn btn-outline">Send Email</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Company Ownership -->
<section class="ownership-section">
    <div class="container">
        <div class="ownership-content animate-fade-up">
            <div class="ownership-badge">
                <div class="badge-icon">🏢</div>
                <div class="badge-content">
                    <h4>Company Ownership</h4>
                    <p>This company is owned and managed by: <strong>Hotel Online Reservation</strong></p>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
    /* CSS Variables for Payment Page */
    :root {
        --payment-primary: #10b981;
        --payment-secondary: #059669;
        --payment-accent: #34d399;
        --payment-warning: #f59e0b;
        --payment-danger: #ef4444;
        --payment-gradient: linear-gradient(135deg, #10b981 0%, #059669 50%, #34d399 100%);
        --payment-gradient-light: linear-gradient(135deg, #34d399 0%, #6ee7b7 100%);
        --shadow-payment: 0 10px 40px rgba(16, 185, 129, 0.15);
    }

    /* Payment Hero Section */
    .payment-hero {
        background: var(--payment-gradient);
        color: white;
        padding: 6rem 0;
        position: relative;
        overflow: hidden;
    }

    .payment-hero::before {
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
        margin-bottom: 1rem;
        line-height: 1.2;
    }

    .hero-subtitle {
        font-size: 1.5rem;
        font-weight: 600;
        margin-bottom: 1.5rem;
        opacity: 0.95;
    }

    .hero-description {
        font-size: 1.1rem;
        line-height: 1.7;
        margin-bottom: 2.5rem;
        opacity: 0.9;
    }

    .trust-badges {
        display: flex;
        gap: 1.5rem;
        margin-bottom: 2rem;
        flex-wrap: wrap;
    }

    .trust-badge {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem 1.5rem;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 15px;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .badge-icon {
        font-size: 2rem;
    }

    .badge-content {
        display: flex;
        flex-direction: column;
    }

    .badge-title {
        font-weight: 700;
        font-size: 1.1rem;
    }

    .badge-subtitle {
        font-size: 0.9rem;
        opacity: 0.8;
    }

    .security-alert {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1.5rem;
        background: rgba(245, 158, 11, 0.2);
        border: 1px solid rgba(245, 158, 11, 0.4);
        border-radius: 15px;
        backdrop-filter: blur(10px);
    }

    .alert-icon {
        font-size: 1.5rem;
        flex-shrink: 0;
    }

    .alert-content {
        flex: 1;
    }

    .payment-methods-visual {
        position: relative;
        text-align: center;
    }

    .method-card {
        position: absolute;
        background: rgba(255, 255, 255, 0.95);
        color: var(--payment-primary);
        padding: 1.5rem 2rem;
        border-radius: 20px;
        box-shadow: var(--shadow-payment);
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.5rem;
        font-weight: 600;
        animation: float 6s ease-in-out infinite;
        backdrop-filter: blur(10px);
    }

    .bank-transfer {
        top: 20%;
        left: 10%;
        animation-delay: 0s;
    }

    .card-payment {
        bottom: 30%;
        right: 10%;
        animation-delay: 3s;
    }

    .method-icon {
        font-size: 2.5rem;
    }

    .secure-shield {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: rgba(255, 255, 255, 0.9);
        padding: 1rem 1.5rem;
        border-radius: 15px;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-weight: 600;
        color: var(--payment-primary);
        box-shadow: var(--shadow-payment);
    }

    .shield-icon {
        font-size: 1.5rem;
    }

    /* Bank Transfer Section */
    .bank-transfer-section {
        padding: 6rem 0;
        background: #f8fafc;
    }

    .section-header {
        text-align: center;
        margin-bottom: 4rem;
    }

    .section-icon {
        font-size: 3rem;
        margin-bottom: 1rem;
    }

    .section-title {
        font-size: 2.5rem;
        font-weight: 800;
        margin-bottom: 1rem;
        color: var(--payment-primary);
    }

    .section-subtitle {
        font-size: 1.2rem;
        color: #64748b;
        max-width: 600px;
        margin: 0 auto;
    }

    .bank-accounts {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 2rem;
        margin-bottom: 4rem;
    }

    .bank-account {
        background: white;
        padding: 2.5rem;
        border-radius: 20px;
        box-shadow: var(--shadow-payment);
        display: flex;
        align-items: center;
        gap: 2rem;
        transition: all 0.3s ease;
        border: 2px solid transparent;
    }

    .bank-account:hover {
        transform: translateY(-5px);
        border-color: var(--payment-primary);
    }

    .bank-logo {
        width: 80px;
        height: 80px;
        border-radius: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        color: white;
        font-size: 1.2rem;
        flex-shrink: 0;
    }

    .bank-logo.fidelity {
        background: linear-gradient(135deg, #009966, #00cc88);
    }

    .bank-logo.access {
        background: linear-gradient(135deg, #0047ba, #0066ff);
    }

    .bank-name {
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 1.5rem;
        color: var(--payment-primary);
    }

    .account-info {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .info-row {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .info-label {
        font-weight: 600;
        color: #64748b;
        min-width: 140px;
    }

    .info-value {
        font-weight: 600;
        color: #1f2937;
        font-family: 'Courier New', monospace;
    }

    .copyable {
        background: #f1f5f9;
        padding: 0.5rem 1rem;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .copyable:hover {
        background: #e2e8f0;
    }

    .copy-btn {
        background: var(--payment-primary);
        color: white;
        border: none;
        padding: 0.5rem;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .copy-btn:hover {
        background: var(--payment-secondary);
        transform: scale(1.1);
    }

    .transfer-steps {
        margin-bottom: 3rem;
    }

    .steps-title {
        font-size: 1.8rem;
        font-weight: 700;
        margin-bottom: 2rem;
        color: var(--payment-primary);
        text-align: center;
    }

    .steps-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
    }

    .step {
        background: white;
        padding: 2rem;
        border-radius: 15px;
        box-shadow: var(--shadow-payment);
        text-align: center;
        transition: all 0.3s ease;
        border-left: 4px solid var(--payment-primary);
    }

    .step:hover {
        transform: translateY(-5px);
    }

    .step-number {
        width: 50px;
        height: 50px;
        background: var(--payment-gradient);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.2rem;
        font-weight: 700;
        margin: 0 auto 1rem;
    }

    .step-content h4 {
        font-size: 1.1rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: var(--payment-primary);
    }

    .step-content p {
        color: #64748b;
        line-height: 1.5;
        margin: 0;
    }

    .processing-info {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 2rem;
    }

    .info-card {
        background: white;
        padding: 2rem;
        border-radius: 15px;
        box-shadow: var(--shadow-payment);
        border-top: 4px solid var(--payment-primary);
    }

    .info-card h4 {
        font-size: 1.3rem;
        font-weight: 700;
        margin-bottom: 1rem;
        color: var(--payment-primary);
    }

    .info-card ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .info-card li {
        padding: 0.5rem 0;
        color: #64748b;
        position: relative;
        padding-left: 1.5rem;
    }

    .info-card li::before {
        content: '•';
        position: absolute;
        left: 0;
        color: var(--payment-primary);
        font-weight: bold;
    }

    .warning-card {
        background: rgba(245, 158, 11, 0.1);
        border: 1px solid rgba(245, 158, 11, 0.3);
        padding: 2rem;
        border-radius: 15px;
        display: flex;
        align-items: flex-start;
        gap: 1rem;
    }

    .warning-icon {
        font-size: 2rem;
        flex-shrink: 0;
        color: var(--payment-warning);
    }

    .warning-content h4 {
        font-size: 1.3rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        color: var(--payment-warning);
    }

    .warning-content p {
        color: #92400e;
        margin: 0;
        line-height: 1.6;
    }

    /* Card Payment Section */
    .card-payment-section {
        padding: 6rem 0;
        background: white;
    }

    .card-payment-content {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 4rem;
        align-items: start;
    }

    .payment-security {
        background: #f8fafc;
        padding: 2.5rem;
        border-radius: 20px;
        border: 1px solid #e2e8f0;
    }

    .security-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .paystack-logo {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, #00a651, #00d46a);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 800;
        font-size: 0.8rem;
    }

    .security-header h3 {
        margin: 0;
        color: var(--payment-primary);
        font-size: 1.3rem;
    }

    .security-features {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        margin-top: 2rem;
    }

    .security-feature {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem;
        background: white;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
    }

    .feature-icon {
        font-size: 1.2rem;
        flex-shrink: 0;
    }

    .card-steps {
        background: white;
        padding: 2.5rem;
        border-radius: 20px;
        box-shadow: var(--shadow-payment);
    }

    .card-steps h3 {
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 2rem;
        color: var(--payment-primary);
    }

    .card-step {
        display: flex;
        align-items: flex-start;
        gap: 1rem;
        margin-bottom: 1.5rem;
        padding-bottom: 1.5rem;
        border-bottom: 1px solid #e2e8f0;
    }

    .card-step:last-child {
        margin-bottom: 0;
        padding-bottom: 0;
        border-bottom: none;
    }

    .card-step .step-number {
        width: 40px;
        height: 40px;
        background: var(--payment-primary);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1rem;
        font-weight: 700;
        flex-shrink: 0;
    }

    .card-step .step-content h4 {
        margin: 0 0 0.5rem 0;
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--payment-primary);
    }

    .card-step .step-content p {
        margin: 0;
        color: #64748b;
        line-height: 1.5;
    }

    /* FAQ Section */
    .faq-section {
        padding: 6rem 0;
        background: #f8fafc;
    }

    .faq-grid {
        max-width: 800px;
        margin: 0 auto;
    }

    .faq-item {
        background: white;
        border-radius: 15px;
        box-shadow: var(--shadow-payment);
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
        color: var(--payment-primary);
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

    /* Refunds Section */
    .refunds-section {
        padding: 6rem 0;
        background: white;
    }

    .refunds-content {
        text-align: center;
        max-width: 800px;
        margin: 0 auto;
    }

    .refunds-content .section-icon {
        font-size: 3rem;
        margin-bottom: 1.5rem;
    }

    .refunds-description {
        font-size: 1.2rem;
        line-height: 1.7;
        color: #64748b;
    }

    /* Anti-Fraud Section */
    .anti-fraud-section {
        padding: 6rem 0;
        background: #fef2f2;
    }

    .fraud-tips {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 2rem;
    }

    .fraud-tip {
        background: white;
        padding: 2rem;
        border-radius: 15px;
        box-shadow: var(--shadow-payment);
        display: flex;
        align-items: flex-start;
        gap: 1rem;
        border-left: 4px solid var(--payment-danger);
    }

    .tip-icon {
        font-size: 2rem;
        flex-shrink: 0;
    }

    .tip-content h4 {
        margin: 0 0 0.5rem 0;
        color: var(--payment-danger);
        font-weight: 700;
    }

    .tip-content p {
        margin: 0;
        color: #64748b;
        line-height: 1.6;
    }

    /* Help Section */
    .help-section {
        padding: 6rem 0;
        background: var(--payment-gradient);
        color: white;
    }

    .help-content {
        text-align: center;
        max-width: 800px;
        margin: 0 auto;
    }

    .help-title {
        font-size: 2.5rem;
        font-weight: 800;
        margin-bottom: 1rem;
    }

    .help-subtitle {
        font-size: 1.3rem;
        margin-bottom: 3rem;
        opacity: 0.9;
    }

    .contact-methods {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 2rem;
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

    .contact-number,
    .contact-email {
        font-size: 1.2rem;
        margin-bottom: 1.5rem;
        opacity: 0.9;
    }

    /* Ownership Section */
    .ownership-section {
        padding: 4rem 0;
        background: #f8fafc;
    }

    .ownership-content {
        text-align: center;
    }

    .ownership-badge {
        display: inline-flex;
        align-items: center;
        gap: 1rem;
        padding: 1.5rem 2rem;
        background: white;
        border-radius: 15px;
        box-shadow: var(--shadow-payment);
        border: 2px solid var(--payment-primary);
    }

    .badge-icon {
        font-size: 2rem;
    }

    .ownership-badge h4 {
        margin: 0 0 0.25rem 0;
        color: var(--payment-primary);
        font-weight: 700;
    }

    .ownership-badge p {
        margin: 0;
        color: #64748b;
    }

    /* Button Styles */
    .btn {
        padding: 0.75rem 1.5rem;
        border: none;
        border-radius: 10px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btn-primary {
        background: white;
        color: var(--payment-primary);
    }

    .btn-primary:hover {
        background: #f8fafc;
        transform: translateY(-2px);
        box-shadow: 0 10px 30px rgba(255, 255, 255, 0.3);
    }

    .btn-outline {
        background: transparent;
        border: 2px solid white;
        color: white;
    }

    .btn-outline:hover {
        background: white;
        color: var(--payment-primary);
        transform: translateY(-2px);
    }

    /* Animations */
    @keyframes float {
        0%, 100% { transform: translateY(0) rotate(0deg); }
        50% { transform: translateY(-10px) rotate(2deg); }
    }

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

    .animate-fade-up {
        opacity: 0;
        transform: translateY(30px);
        animation: fadeUp 0.8s ease forwards;
    }

    .animate-zoom-in {
        opacity: 0;
        transform: scale(0.8);
        animation: zoomIn 0.6s ease forwards;
    }

    .animate-fade-in {
        opacity: 0;
        animation: fadeIn 0.6s ease forwards;
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

    @keyframes fadeUp {
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes zoomIn {
        to {
            opacity: 1;
            transform: scale(1);
        }
    }

    @keyframes fadeIn {
        to {
            opacity: 1;
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

        .trust-badges {
            justify-content: center;
        }

        .method-card {
            position: relative;
            margin: 1rem auto;
            width: fit-content;
        }

        .bank-transfer, .card-payment {
            position: relative;
            top: auto;
            left: auto;
            right: auto;
            bottom: auto;
        }

        .secure-shield {
            position: relative;
            top: auto;
            left: auto;
            transform: none;
            margin: 2rem auto;
        }

        .bank-accounts {
            grid-template-columns: 1fr;
        }

        .bank-account {
            flex-direction: column;
            text-align: center;
        }

        .info-row {
            flex-direction: column;
            gap: 0.5rem;
            text-align: left;
        }

        .card-payment-content {
            grid-template-columns: 1fr;
            gap: 2rem;
        }

        .fraud-tips {
            grid-template-columns: 1fr;
        }

        .contact-methods {
            grid-template-columns: 1fr;
        }

        .ownership-badge {
            flex-direction: column;
            text-align: center;
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

        // Intersection Observer for animations
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.animationPlayState = 'running';
                }
            });
        }, { threshold: 0.1 });

        animatedElements.forEach(element => {
            observer.observe(element);
        });
    });

    // Copy to clipboard function
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(() => {
            // Show success message
            const originalText = event.target.textContent;
            event.target.textContent = '✅';
            setTimeout(() => {
                event.target.textContent = originalText;
            }, 2000);
        }).catch(err => {
            console.error('Failed to copy: ', err);
            event.target.textContent = '❌';
            setTimeout(() => {
                event.target.textContent = '📋';
            }, 2000);
        });
    }

    // WhatsApp functionality
    function openWhatsApp() {
        const number = '2349034072383';
        const message = `Hello TravelCentre! I need help with payment. Please assist me with:\n\n• Confirming bank details\n• Resending my invoice\n• Generating a Paystack link\n\nThank you!`;
        
        window.open(`https://wa.me/${number}?text=${encodeURIComponent(message)}`, '_blank');
    }

    // Email functionality
    function sendEmail() {
        const email = 'info@travelcentre.ng';
        const subject = 'Payment Assistance Required';
        const body = `Hello TravelCentre Team,\n\nI need assistance with payment. Please help me with:\n\n• Confirming bank details\n• Resending my invoice\n• Generating a Paystack link\n\nThank you!\n\nBest regards,\n[Your Name]`;
        
        window.location.href = `mailto:${email}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
    }
</script>

<?php
require_once 'includes/footer.php';
?>