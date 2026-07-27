<?php
// privacy.php
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    
                <!-- Google tag (gtag.js) -->
            <script async src="https://www.googletagmanager.com/gtag/js?id=AW-XXXXXXXXXX">
                
            </script>
            <script>
              window.dataLayer = window.dataLayer || [];
              function gtag(){dataLayer.push(arguments);}
              gtag('js', new Date());
            
              gtag('config', 'AW-XXXXXXXXXX');
            </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy | Bright English Coaching</title>
    <link rel="icon" type="image/png" href="assets/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css?v=15">
    <style>
        .legal-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 120px 24px 80px;
            color: #4b5563;
            line-height: 1.8;
            font-family: 'Inter', sans-serif;
        }
        .legal-container h1 {
            font-family: 'Playfair Display', serif;
            font-size: 2.8rem;
            color: #1C1A17;
            margin-bottom: 8px;
        }
        .legal-container .last-updated {
            font-size: 0.9rem;
            color: #9ca3af;
            margin-bottom: 48px;
            display: block;
        }
        .legal-container h2 {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            color: #1C1A17;
            margin-top: 40px;
            margin-bottom: 16px;
        }
        .legal-container p, .legal-container ul {
            margin-bottom: 20px;
        }
        .legal-container ul {
            padding-left: 24px;
        }
        .legal-container li {
            margin-bottom: 8px;
        }
    </style>

</head>
<body>

<!-- NAV -->
<nav class="nav">
    <div class="nav-inner">
        <a href="index.php" class="nav-brand">
            <img src="assets/logo.png" alt="Bright English Coaching Logo" style="max-height: 150px; width: auto;" onerror="this.outerHTML='<span style=\'font-weight:bold;color:#1C1A17;\'>Bright English Coaching</span>'">
        </a>
        <div class="nav-links">
            <a href="index.php#testimonials">Testimonials</a>
            <a href="index.php#process">The Process</a>
            <a href="index.php#about">About Jordan</a>
            <a href="index.php#consultation" class="btn-nav">Free Diagnostic</a>
        </div>
    </div>
</nav>

<main class="legal-container">
    <h1>Privacy Policy</h1>
    <span class="last-updated">Last Updated: June 2026</span>

    <p>This Privacy Policy explains how Bright English Coaching collects, uses, and protects your information when you visit the website or enroll in the Professional Communication Coaching program.</p>

    <h2>1. Information We Collect</h2>
    <p>When you book a free pronunciation assessment or use the contact form, we collect the information you voluntarily provide. This includes your name, email address, company or profession, and any optional details such as your LinkedIn profile or personal message.</p>

    <h2>2. How We Use Your Information</h2>
    <p>Your information is used strictly to provide and improve the coaching service. Specifically, we use your data to:</p>
    <ul>
        <li>Schedule and manage your free pronunciation assessment.</li>
        <li>Provide WhatsApp voice feedback and support between sessions if you enroll in the program.</li>
        <li>Send necessary communication regarding your 8-week program, customized practice exercises, and improvement roadmap.</li>
    </ul>

    <h2>3. Data Protection and Sharing</h2>
    <p>Your privacy is a priority. We do not sell, rent, or trade your personal information to third parties. Your information is only shared with secure third-party platforms necessary to run the business, such as email providers, calendar scheduling tools, and website analytics (like Google Analytics).</p>

    <h2>4. Cookies and Tracking</h2>
    <p>This website uses basic cookies and analytics tools to understand how visitors interact with the site. This helps ensure the website functions correctly and allows us to improve the user experience. You can adjust your browser settings to decline cookies at any time.</p>

    <h2>5. Your Rights</h2>
    <p>You have the right to request access to the personal information we hold about you. You may also request that we correct or delete your information at any time by contacting us directly.</p>

    <h2>6. Contact</h2>
    <p>If you have any questions about this Privacy Policy or how your data is handled, please reach out via the contact form on the website.</p>
</main>

<!-- FOOTER -->
<footer>
    <div class="footer-main">
        <div class="footer-grid">
            <div>
                <a href="index.php" class="footer-brand">
                    <img src="assets/logo-dark-2.png" alt="Bright English Coaching Logo" style="max-height: 200px; width: auto; margin-bottom: 12px;" onerror="this.outerHTML='<span style=\'font-weight:bold;font-size:1.5rem;color:#1C1A17;display:block;margin-bottom:12px;\'>Bright English Coaching.</span>'">
                </a>
                <p class="footer-desc">Specialized English pronunciation coaching helping Spanish-speaking professionals communicate clearly and lead with absolute confidence.</p>
            </div>
            <div class="footer-col">
                <p class="footer-col-title">Navigation</p>
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="index.php#about">About Me</a></li>
                    <li><a href="index.php#process">Services</a></li>
                    <li><a href="index.php#contact">Contact</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <p class="footer-col-title">Legal</p>
                <ul>
                    <li><a href="privacy.php">Privacy Policy</a></li>
                    <li><a href="terms.php">Terms of Service</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <p class="footer-col-title">Connect</p>
                <ul>
                    <!-- target="_blank" ensures it opens in a new tab! -->
                    <li><a href="https://www.linkedin.com/in/yourprofile" target="_blank" rel="noopener noreferrer">LinkedIn</a></li>
                </ul>
            </div>
        </div>
    </div>
    <div class="footer-bottom">
        &copy; <?php echo date("Y"); ?> Bright English Coaching. All rights reserved.
    </div>
</footer>

</body>
</html>