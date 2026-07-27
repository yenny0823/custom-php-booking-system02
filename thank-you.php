<?php
// thank-you.php
//
// Dedicated confirmation page, reached only via a server-side redirect from
// book.php right after a booking has actually been inserted into the
// database. That's what makes this page the right place for a Google Ads
// (or any ad platform's) conversion tag: the tag only fires when someone
// really completed a booking, not just whenever this URL happens to load
// some other way.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Google tag (gtag.js) - Google Analytics 4 -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-XXXXXXXXXX"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', 'G-XXXXXXXXXX');
    </script>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmed | Bright English Coaching</title>
    <link rel="icon" type="image/png" href="assets/logo.png">
    <meta name="robots" content="noindex, nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css?v=3">

    <!-- ==========================================================
         GOOGLE ADS CONVERSION TRACKING — paste your snippets below
         ==========================================================
         In Google Ads: Tools & Settings > Conversions > + New conversion
         action > Website. Google will give you two blocks of code:

         1) The global site tag (skip this part if it's already on every
            page of the site, e.g. via Google Tag Manager):

         <script async src="https://www.googletagmanager.com/gtag/js?id=AW-XXXXXXXXX"></script>
         <script>
           window.dataLayer = window.dataLayer || [];
           function gtag(){dataLayer.push(arguments);}
           gtag('js', new Date());
           gtag('config', 'AW-XXXXXXXXX');
         </script>

         2) The event snippet — this is the one that must live ONLY on this
            page, since it's what actually records the conversion:

         <script>
           gtag('event', 'conversion', {
               'send_to': 'AW-XXXXXXXXX/AbCdEfGhIjKlMnOpQr'
           });
         </script>

         Replace AW-XXXXXXXXX and the send_to value with whatever Google Ads
         shows you for this specific conversion action, then remove this
         comment block.

         Heads up: because this page is reached via redirect, refreshing it
         or navigating back to it (e.g. browser back/forward, or someone
         bookmarking the URL) will re-fire the tag. For a small site this is
         usually an acceptable, minor over-count — but if precise numbers
         matter, consider passing a one-time booking ID in the redirect and
         only firing the tag the first time that ID is seen (e.g. checked
         against sessionStorage) or configure Google Ads to de-duplicate.
    -->
</head>

<body class="page-booking">
<nav class="nav">
    <div class="nav-inner">
        <a href="index.php" class="nav-brand">
            <img src="assets/logo.png" alt="Bright English Coaching Logo" style="max-height: 150px; width: auto;">
        </a>
        <a href="index.php" class="nav-back">Back to Home</a>
    </div>
</nav>

<main class="main">
    <div class="booking-success" style="background: #fff; border: 1px solid #e5e7eb; padding: 48px 40px; text-align: left; max-width: 760px; margin: 0 auto;">
        <h2 style="font-family: 'Playfair Display', serif; font-size: 2.4rem; color: #1C1A17; margin-bottom: 12px;">Booking Confirmed!</h2>
        <p style="color: #4b5563; font-size: 1.05rem; margin-bottom: 24px;">Thank you for scheduling your Communication Consultation.</p>

        <div style="background: #f0fdf4; border: 1px solid #bbf7d0; padding: 16px 20px; color: #166534; margin-bottom: 40px; border-radius: 4px;">
            ✓ A confirmation email has been sent to your inbox with your appointment details and Google Meet link.
        </div>

        <h3 style="font-size: 1.1rem; color: #1C1A17; margin-bottom: 16px;">During our assessment, we will:</h3>
        <ul style="color: #4b5563; line-height: 1.8; margin-bottom: 32px; padding-left: 20px;">
            <li>Discuss your communication goals and professional needs</li>
            <li>Identify the pronunciation patterns that may be affecting your clarity</li>
            <li>Pinpoint specific sounds, stress patterns, or speech habits that may be creating misunderstandings</li>
            <li>Determine the most effective path forward for improving your spoken English</li>
        </ul>

        <h3 style="font-size: 1.1rem; color: #1C1A17; margin-bottom: 16px;">Before we meet, it may be helpful to think about:</h3>
        <ul style="color: #4b5563; line-height: 1.8; margin-bottom: 32px; padding-left: 20px;">
            <li>Situations where English communication is most important in your work</li>
            <li>The biggest challenges you face when speaking English</li>
            <li>Any presentations, meetings, interviews, or conversations where you have struggled to be understood</li>
        </ul>

        <p style="color: #4b5563; line-height: 1.7; margin-bottom: 36px;">
            No formal preparation is required, but thinking about these questions beforehand will help us make the most of our time together.
        </p>

        <div style="border-top: 1px solid #e5e7eb; padding-top: 32px; margin-bottom: 32px;">
            <p style="color: #4b5563; line-height: 1.7; margin-bottom: 24px;">
                If you have any questions before your appointment, feel free to contact me at:<br>
                <a href="mailto:hello@brightenglishcoaching.com" style="color: #1C1A17; font-weight: 600; text-decoration: underline;">hello@brightenglishcoaching.com</a>
            </p>
            <p style="color: #1C1A17; font-weight: 600; margin-bottom: 4px; font-size: 1.05rem;">I look forward to meeting you.</p>
            <p style="color: #4b5563;">- Jordan<br>Bright English Coaching</p>
        </div>

        <a href="index.php" class="btn-dark" style="display: inline-block; text-align: center;">Return to Homepage</a>
    </div>
</main>
</body>
</html>