<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// index.php
require_once 'config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require 'PHPMailer/PHPMailer/src/Exception.php';
require 'PHPMailer/PHPMailer/src/PHPMailer.php';
require 'PHPMailer/PHPMailer/src/SMTP.php'; 

// NOTE: Make sure config.php defines the coach's real timezone, e.g.:
//   define('COACH_TIMEZONE', 'America/Boise');
// This fallback prevents a fatal error if it hasn't been added yet.
if (!defined('COACH_TIMEZONE')) {
    define('COACH_TIMEZONE', 'America/Boise');
}

// Helper function to turn YouTube URLs into Embed URLs
function getYoutubeEmbedUrl($url) {
    if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i', $url, $matches)) {
        return "https://www.youtube.com/embed/" . $matches[1];
    }
    return null;
}

// Fetch site content for the CMS
try {
    $content_stmt = $pdo->query("SELECT section_key, content FROM site_content");
    $site_content = [];
    while ($row = $content_stmt->fetch(PDO::FETCH_ASSOC)) {
        $site_content[$row['section_key']] = $row['content'];
    }
} catch (PDOException $e) {
    $site_content = [];
}

// Fetch slots grouped by date for the consultation section
try {
    $slots_stmt = $pdo->query("
        SELECT a.slot_date, a.slot_time 
        FROM available_slots a
        LEFT JOIN bookings b 
          ON a.slot_date = b.requested_date 
         AND a.slot_time = b.requested_time 
         AND b.status != 'voided'
        WHERE a.slot_date >= CURDATE() 
          AND b.id IS NULL
        ORDER BY a.slot_date ASC, a.slot_time ASC
    ");
    $all_slots = $slots_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $all_slots = [];
}

// Coach-local grouping (kept as-is; used for the "any slots at all?" check)
$slots_by_date = [];
foreach ($all_slots as $row) {
    $slots_by_date[$row['slot_date']][] = $row['slot_time'];
}
$available_dates = array_keys($slots_by_date);

// Timezone-aware flat list, used by the JS scheduler so visitors see times
// (and dates) converted into their own local timezone.
$coach_tz = new DateTimeZone(COACH_TIMEZONE);
$utc_tz   = new DateTimeZone('UTC');

$slots_flat = [];
foreach ($all_slots as $row) {
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $row['slot_date'] . ' ' . $row['slot_time'], $coach_tz);
    if (!$dt) continue;
    $dt->setTimezone($utc_tz);

    $slots_flat[] = [
        'date' => $row['slot_date'],  // coach-local date (needed for DB matching on submit)
        'time' => $row['slot_time'],  // coach-local time (needed for DB matching on submit)
        'utc'  => $dt->format('c'),   // ISO 8601 UTC — browser converts this to visitor's local time
    ];
}
$slots_json = json_encode($slots_flat);

$success_message = '';
$error_message   = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name     = trim($_POST['name']    ?? '');
    $email    = trim($_POST['email']   ?? '');
    $linkedin = trim($_POST['linkedin'] ?? '');
    $message  = trim($_POST['message'] ?? '');

    if (!empty($name) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();                                            
            $mail->Host       = 'smtp.gmail.com';                     
            $mail->SMTPAuth   = true;                                   
            $mail->Username   = EMAIL_USER;     
            $mail->Password   = EMAIL_PASS; 
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;            
            $mail->Port       = 465;

            $mail->setFrom(EMAIL_USER, 'Bright English Coaching Website');
            $mail->addAddress(EMAIL_USER, 'Jordan'); 
            $mail->addReplyTo($email, $name);

            $mail->isHTML(true);
            $mail->Subject = 'New Contact Form Submission from ' . $name;
            
            $body = "<h3>New Message from Bright English Coaching Contact Form</h3>";
            $body .= "<p><strong>Name:</strong> " . htmlspecialchars($name) . "</p>";
            $body .= "<p><strong>Email:</strong> " . htmlspecialchars($email) . "</p>";
            
            if (!empty($linkedin)) {
                $body .= "<p><strong>LinkedIn:</strong> " . htmlspecialchars($linkedin) . "</p>";
            }
            
            $body .= "<p><strong>Message:</strong><br>" . nl2br(htmlspecialchars($message)) . "</p>";

            $mail->Body    = $body;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<h3>', '</h3>', '<p>', '</p>'], ["\n", '', "\n\n", '', "\n"], $body));

            $mail->send();
            $success_message = "Thank you! Your message has been received. Jordan will be in touch shortly.";
        } catch (Exception $e) {
            $error_message = "Something went wrong. Please try again later.";
        }
    } else {
        $error_message = "Please provide a valid name and email address.";
    }
}

// Fetch Approved Testimonials
try {
    $test_stmt = $pdo->query("SELECT client_name, client_role, quote, rating, video_url FROM testimonials WHERE status = 'approved' ORDER BY id DESC");
    $approved_testimonials = $test_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $approved_testimonials = [];
}

// ==========================================
// SEO HELPERS
// ==========================================

$scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? 'brightenglishcoaching.com';
$site_url = $scheme . '://' . $host;
$canonical_url = $site_url . '/';

$page_title = !empty($site_content['meta_title'])
    ? $site_content['meta_title']
    : 'Bright English Coaching | Executive Pronunciation Coaching for Spanish Speakers';

$page_description = !empty($site_content['meta_description'])
    ? $site_content['meta_description']
    : 'Specialized English pronunciation coaching for Spanish-speaking professionals. Communicate complex ideas clearly and lead with absolute confidence.';

$og_image_path = !empty($site_content['about_me_image'])
    ? $site_content['about_me_image']
    : 'assets/logo.png';
$og_image_url = $site_url . '/' . ltrim($og_image_path, '/');

$faqs = [
    [
        'q' => 'Will I lose my accent?',
        'a' => 'No. The goal is not to eliminate your accent or change who you are. The goal is to help you communicate more clearly and confidently so that the people focus on your ideas rather than your pronunciation.'
    ],
    [
        'q' => 'How long does it take to see results?',
        'a' => 'Every client is different, but many people begin noticing improvements in clarity, confidence, and communication within the first few weeks of consistent practice. Long-term improvement depends on your goals, starting point, and commitment to the process.'
    ],
    [
        'q' => 'How is this different from standard English or ESL courses?',
        'a' => 'Most English courses focus on grammar, vocabulary, reading, and writing. My coaching focuses specifically on pronunciation, connected speech, rhythm, stress patterns, and the speech habits that commonly transfer from Spanish into English.'
    ],
    [
        'q' => 'What is the commitment required?',
        'a' => 'The program consists of 8 weekly 1-on-1 coaching sessions, personalized practice exercises, and ongoing support between sessions. Most clients spend approximately 10-15 minutes per day practicing outside of our meetings.'
    ],
    [
        'q' => 'Who is this program designed for?',
        'a' => 'This program is designed for Spanish-speaking professionals who already have a solid foundation in English but want to improve their clarity, confidence, and professional communication. Many of my clients are engineers, researchers, and other professionals who regularly use English at work.'
    ],
];

$rating_count = 0;
$rating_sum   = 0;
if (!empty($approved_testimonials)) {
    foreach ($approved_testimonials as $t) {
        $r = !empty($t['rating']) ? (int)$t['rating'] : 5;
        $rating_sum += $r;
        $rating_count++;
    }
}
$rating_average = $rating_count > 0 ? round($rating_sum / $rating_count, 1) : null;

$service_schema = [
    '@context'    => 'https://schema.org',
    '@type'       => 'ProfessionalService',
    'name'        => 'Bright English Coaching',
    'description' => $page_description,
    'url'         => $canonical_url,
    'image'       => $og_image_url,
    'priceRange'  => $site_content['program_fee'] ?? '$1,250',
    'sameAs'      => [
        'https://www.linkedin.com/in/yourprofile'
    ],
];
if ($rating_count > 0) {
    $service_schema['aggregateRating'] = [
        '@type'       => 'AggregateRating',
        'ratingValue' => $rating_average,
        'reviewCount' => $rating_count,
    ];
}

$faq_schema = [
    '@context'   => 'https://schema.org',
    '@type'      => 'FAQPage',
    'mainEntity' => array_map(function ($faq) {
        return [
            '@type'          => 'Question',
            'name'           => $faq['q'],
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text'  => $faq['a'],
            ],
        ];
    }, $faqs),
];
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

    <title><?= htmlspecialchars($page_title) ?></title>
    <meta name="description" content="<?= htmlspecialchars($page_description) ?>">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?= htmlspecialchars($canonical_url) ?>">
    <link rel="icon" type="image/png" href="assets/logo.png">

    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= htmlspecialchars($canonical_url) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($page_title) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($page_description) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($og_image_url) ?>">
    <meta property="og:site_name" content="Bright English Coaching">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="<?= htmlspecialchars($canonical_url) ?>">
    <meta name="twitter:title" content="<?= htmlspecialchars($page_title) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($page_description) ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($og_image_url) ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css?v=11">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/dark.css">

    <script type="application/ld+json"><?= json_encode($service_schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
    <script type="application/ld+json"><?= json_encode($faq_schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>

    <style>
        /* Testimonial Slider Styles */
        .testimonial-slider {
            display: flex !important;
            flex-wrap: nowrap !important;
            overflow-x: auto !important;
            gap: 24px;
            padding: 20px 24px 40px !important;
            scroll-snap-type: x mandatory;
            scroll-behavior: smooth;
        }
        
        .testimonial-slider::-webkit-scrollbar {
            display: none; 
        }
        
        .testimonial-slider.hide-scrollbar {
            -ms-overflow-style: none;  
            scrollbar-width: none;  
        }
        
        .testimonial-card {
            flex: 0 0 350px !important; 
            scroll-snap-align: start;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        @media (max-width: 480px) {
            .testimonial-card {
                flex: 0 0 85vw !important; 
            }
        }

        /* --- Accessibility (A11y) & Dark Mode Styles --- */
        
        /* Font Toggles */
        /* Force scale the entire body to override hardcoded px values */
        body.a11y-text-large { zoom: 1.15; }
        body.a11y-text-xlarge { zoom: 1.30; }
        
        /* Optional: Prevent the accessibility panel itself from becoming massively oversized */
        body.a11y-text-large .a11y-panel, 
        body.a11y-text-large .a11y-widget-btn { zoom: 0.87; }
        body.a11y-text-xlarge .a11y-panel, 
        body.a11y-text-xlarge .a11y-widget-btn { zoom: 0.77; }
        body.a11y-dyslexia * {
            font-family: 'Comic Sans MS', 'Arial', sans-serif !important;
            letter-spacing: 0.05em !important;
            word-spacing: 0.1em !important;
            line-height: 1.8 !important;
        }

        /* Dark Mode overrides */
        body.dark-mode {
            background-color: #121212;
            color: #f3f4f6;
        }
        body.dark-mode h1, body.dark-mode h2, body.dark-mode h3, 
        body.dark-mode h4, body.dark-mode p, body.dark-mode span:not(.pillar-num):not(.live-badge):not(.flatpickr-day):not(.flatpickr-weekday):not(.flatpickr-monthDropdown-months),
        body.dark-mode li, body.dark-mode label, body.dark-mode summary {
            color: #f3f4f6 !important;
        }
        body.dark-mode .nav { background-color: #1f2937; border-bottom: 1px solid #374151; }
        body.dark-mode .nav-links a { color: #f3f4f6; }
        body.dark-mode .mobile-menu { background-color: #1f2937; }
        body.dark-mode .section-problem, body.dark-mode .section-process, 
        body.dark-mode .section-outcomes, body.dark-mode .section-promise, 
        body.dark-mode .section-services, body.dark-mode .section-personalized, 
        body.dark-mode .section-roadmap, body.dark-mode .section-faq, 
        body.dark-mode .section-consultation, body.dark-mode .section-contact {
            background-color: #121212;
            border-color: #374151 !important;
        }
        body.dark-mode .testimonial-card, body.dark-mode .services-box:not(.dark-box), 
        body.dark-mode .scheduler-card, body.dark-mode .diagnostic-box {
            background-color: #1f2937;
            border: 1px solid #374151;
            box-shadow: none;
        }
        body.dark-mode .stars span[style*="color: #1C1A17"] { color: #f59e0b !important; }
        body.dark-mode input, body.dark-mode textarea {
            background-color: #374151 !important;
            color: #f3f4f6 !important;
            border: 1px solid #4b5563;
        }
        body.dark-mode input::placeholder, body.dark-mode textarea::placeholder {
            color: #9ca3af !important;
        }

        /* A11y Panel UI */
        .a11y-widget-btn {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background-color: #2563eb;
            color: white;
            border: none;
            border-radius: 50%;
            width: 56px;
            height: 56px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            cursor: pointer;
            z-index: 9999;
            transition: transform 0.2s ease, background-color 0.2s;
        }
        .a11y-widget-btn:hover { transform: scale(1.05); background-color: #1d4ed8; }
        .a11y-panel {
            position: fixed;
            bottom: 90px;
            right: 24px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            width: 280px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            z-index: 9998;
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: all 0.3s ease;
        }
        body.dark-mode .a11y-panel {
            background: #1f2937;
            border-color: #374151;
        }
        .a11y-panel.active {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        .a11y-panel h4 {
            margin: 0 0 16px 0;
            font-size: 1.1rem;
            color: #111827;
        }
        .a11y-option {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            font-size: 0.95rem;
        }
        .a11y-option button {
            background: #f3f4f6;
            border: 1px solid #d1d5db;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: background 0.2s;
        }
        body.dark-mode .a11y-option button {
            background: #374151;
            border-color: #4b5563;
            color: #fff;
        }
        .a11y-option button:hover { background: #e5e7eb; }
        body.dark-mode .a11y-option button:hover { background: #4b5563; }
        .a11y-reset {
            width: 100%;
            margin-top: 10px;
            padding: 8px;
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }
        .a11y-reset:hover { background: #dc2626; }
    </style>
</head>
<body>

<!-- NAV -->
<nav class="nav">
    <div class="nav-inner">
        <a href="index.php" class="nav-brand">
            <img id="main-logo" src="assets/logo.png" alt="Bright English Coaching Logo" style="max-height: 150px; width: auto;">
        </a>
        <div class="nav-links">
            <a href="#testimonials">Testimonials</a>
            <a href="#process">The Process</a>
            <a href="#about">About Jordan</a>
            <a href="book.php" class="btn-hero">Book a Professional Communication Consultation</a>
        </div>
        <button class="hamburger" id="hamburger" aria-label="Toggle menu">
            <svg id="icon-menu" width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
            <svg id="icon-close" width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:none;">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>
    <div class="mobile-menu" id="mobile-menu">
        <div class="mobile-menu-inner">
            <a href="#testimonials" class="mobile-link">Testimonials</a>
            <a href="#process" class="mobile-link">The Process</a>
            <a href="#about" class="mobile-link">About Jordan</a>
            <a href="book.php" class="btn-mobile-book mobile-link">Book a Professional Communication Consultation</a>
        </div>
    </div>
</nav>

<!-- HERO (DYNAMIC CMS) -->
<header class="hero">
    <div class="hero-inner">
        <h1>
            <?= htmlspecialchars($site_content['hero_headline'] ?? 'Hablas con impacto en español.') ?><br>
            <span class="muted"><?= htmlspecialchars($site_content['hero_subheadline'] ?? 'Now, lead with absolute clarity in English.') ?></span>
        </h1>
        <p style="max-width: 680px;"><?= htmlspecialchars($site_content['hero_description'] ?? 'Pronunciation coaching for Spanish-speaking professionals who want to communicate more clearly and confidently in English. Helping engineers, healthcare professionals, researchers, and managers become easier to understand without losing their accent.') ?></p>
        <a href="book.php" class="btn-hero">Book a Professional Communication Consultation</a>
    </div>
</header>

<!-- LOGOS -->
<div class="logos-bar">
    <p class="logos-label">Empowering leaders who excel at</p>
    <div class="logos-wrap">
        <span style="font-family:'Playfair Display',serif; font-style:italic;">Engineering</span>
        <span style="letter-spacing:0.15em;font-family:'Playfair Display',serif; font-style:italic;">Healthcare</span>
        <span style="font-family:'Playfair Display',serif; font-style:italic;">Research</span>
        <span style="font-family:'Playfair Display',serif; font-style:italic;">Technology</span>
        <span style="letter-spacing:0.1em;font-family:'Playfair Display',serif; font-style:italic;">Finance</span>
    </div>
</div>

<!-- TESTIMONIALS -->
<div id="testimonial-slider" class="testimonial-slider hide-scrollbar">
    <?php if (isset($approved_testimonials) && count($approved_testimonials) > 0): ?>
        <?php foreach ($approved_testimonials as $t): 
            $rating = !empty($t['rating']) ? (int)$t['rating'] : 5;
            $embedUrl = !empty($t['video_url']) ? getYoutubeEmbedUrl($t['video_url']) : null;
        ?>
            <div class="testimonial-card">
                <div>
                    <div class="stars" style="font-size: 1.1rem; margin-bottom: 12px;">
                        <span style="color: #1C1A17;"><?= str_repeat('★', $rating) ?></span><span style="color: #e5e7eb;"><?= str_repeat('★', 5 - $rating) ?></span>
                    </div>
                    
                    <?php if ($embedUrl): ?>
                        <div style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; margin-bottom: 20px; border-radius: 4px;">
                            <iframe src="<?= $embedUrl ?>" loading="lazy" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;" frameborder="0" allowfullscreen></iframe>
                        </div>
                    <?php endif; ?>

                    <p class="t-quote">"<?= htmlspecialchars($t['quote']) ?>"</p>
                </div>
                <div style="margin-top: 24px;">
                    <p class="t-name"><?= htmlspecialchars($t['client_name']) ?></p>
                    <p class="t-role"><?= htmlspecialchars($t['client_role']) ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p style="color: #6b7280; grid-column: 1 / -1; text-align: center; width: 100%;">More success stories coming soon.</p>
    <?php endif; ?>
</div>

<!-- PROBLEM / SYMPTOMS (DYNAMIC CMS) -->
<section id="problem" class="section-problem">
    <div class="container-sm">
        <div class="text-center">
            <p class="section-label"><?= htmlspecialchars($site_content['problem_label'] ?? 'Does This Sound Familiar?') ?></p>
            <h2 class="section-title"><?= htmlspecialchars($site_content['problem_headline'] ?? 'You know exactly what you want to say, but...') ?></h2>
        </div>
        <ul class="problem-list">
            <?php 
            $default_problems = "People sometimes ask you to repeat certain words.\nYou understand English well but still hesitate to speak up in meetings.\nFast native speech is difficult to follow.\nYour communication doesn't always reflect your level of expertise.\nYou feel more confident communicating complex ideas in Spanish than in English.";
            $problems = explode("\n", trim($site_content['problem_list'] ?? $default_problems));
            foreach ($problems as $problem): 
                if (trim($problem) !== ''): 
            ?>
                <li><span>✓</span> <?= htmlspecialchars(trim($problem)) ?></li>
            <?php 
                endif; 
            endforeach; 
            ?>
        </ul>
    </div>
</section>

<!-- PARADOX (DYNAMIC CMS) -->
<section class="section-paradox">
    <div class="paradox-inner">
        <p class="paradox-label">The Paradox</p>
        <h2><?= htmlspecialchars($site_content['paradox_headline'] ?? 'Your expertise deserves to be understood.') ?></h2>
        
        <div style="color: #d1d5db; font-size: 1.05rem; font-weight: 300; line-height: 1.85; max-width: 720px;">
            <?= nl2br(htmlspecialchars($site_content['paradox_description'] ?? "In Spanish, you can explain complex ideas, build relationships, and communicate with confidence. But in English, pronunciation patterns from your first language may sometimes make it harder for others to fully understand you.\n\nThe goal isn't to eliminate your accent. The goal is to help you communicate with greater clarity and confidence so that people focus on your ideas, not your pronunciation.\n\nUnlike traditional English courses that focus primarily on grammar and vocabulary, my coaching focuses on the pronunciation patterns, rhythm differences, and speech habits that commonly transfer from Spanish into English.\n\nBy identifying and improving those patterns, you can become easier to understand, more confident in meetings and presentations, and better equipped to communicate effectively in professional environments.")) ?>
        </div>
    </div>
</section>

<!-- PROCESS (DYNAMIC CMS) -->
<section id="process" class="section-process">
    <div class="container">
        <p class="section-label"><?= htmlspecialchars($site_content['process_label'] ?? 'Curriculum Focus') ?></p>
        <h2 class="section-title"><?= htmlspecialchars($site_content['process_headline'] ?? 'The Three Pillars of Clear Communication') ?></h2>
        <div class="pillars-grid">
            <div>
                <span class="pillar-num">01</span>
                <p class="pillar-title"><?= htmlspecialchars($site_content['pillar1_title'] ?? 'Pronunciation & Sound Accuracy') ?></p>
                <p class="pillar-text"><?= htmlspecialchars($site_content['pillar1_text'] ?? 'Many Spanish speakers already have strong English skills, but certain sound patterns can make words harder to understand. We focus on vowel sounds, consonants, word endings, and the pronunciation patterns that most commonly transfer from Spanish into English.') ?></p>
            </div>
            <div>
                <span class="pillar-num">02</span>
                <p class="pillar-title"><?= htmlspecialchars($site_content['pillar2_title'] ?? 'Rhythm & Connected Speech') ?></p>
                <p class="pillar-text"><?= htmlspecialchars($site_content['pillar2_text'] ?? 'Native English speakers don\'t pronounce every word separately. We work on stress patterns, reductions, linking, and connected speech so you can both understand fast native speakers more easily and sound more natural yourself.') ?></p>
            </div>
            <div>
                <span class="pillar-num">03</span>
                <p class="pillar-title"><?= htmlspecialchars($site_content['pillar3_title'] ?? 'Professional Communication') ?></p>
                <p class="pillar-text"><?= htmlspecialchars($site_content['pillar3_text'] ?? 'Apply what you\'ve learned to real-world situations such as meetings, presentations, interviews, and everyday workplace conversations. The goal is not perfect English, the goal is clear, confident communication.') ?></p>
            </div>
        </div>
        <a href="book.php" class="btn-process">Book a Professional Communication Consultation</a>
    </div>
</section>

<!-- OUTCOMES (DYNAMIC CMS) -->
<section id="outcomes" class="section-outcomes">
    <div class="container-sm">
        <div class="text-center">
            <p class="section-label"><?= htmlspecialchars($site_content['outcomes_label'] ?? 'The Result') ?></p>
            <h2 class="section-title"><?= htmlspecialchars($site_content['outcomes_headline'] ?? 'Communicate with Absolute Clarity') ?></h2>
            <p class="outcomes-subtitle"><?= htmlspecialchars($site_content['outcomes_subtitle'] ?? 'After completing this program, you will:') ?></p>
        </div>
        <ul class="outcomes-list">
            <?php 
            $default_outcomes = "Be asked to repeat yourself far less often.\nHear \"What?\" and \"Can you repeat that?\" much less.\nFeel more confident speaking during meetings, presentations, interviews, and everyday conversations.\nStrengthen your chances of earning promotions and new career opportunities by communicating more clearly.\nBuild stronger relationships with your boss, coworkers, customers, and clients.\nUnderstand fast native English much more easily.\nSound more natural, confident, and professional every time you speak.";
            $outcomes = explode("\n", trim($site_content['outcomes_list'] ?? $default_outcomes));
            foreach ($outcomes as $outcome): 
                if (trim($outcome) !== ''): 
            ?>
                <li><span>—</span> <?= htmlspecialchars(trim($outcome)) ?></li>
            <?php 
                endif; 
            endforeach; 
            ?>
        </ul>
    </div>
</section>

<!-- PROMISE / GUARANTEE (DYNAMIC CMS) -->
<section id="promise" class="section-promise" style="padding: 90px 24px; border-top: 1px solid #e5e7eb;">
    <div class="container-sm text-center" style="max-width: 800px; margin: 0 auto;">
        <p class="section-label" style="font-size: 0.85rem; letter-spacing: 0.12em; font-weight: 600; margin-bottom: 16px;"><?= htmlspecialchars($site_content['promise_label'] ?? 'My Promise') ?></p>
        <h2 class="section-title"><?= htmlspecialchars($site_content['promise_headline'] ?? 'You\'ll Notice Improvement From Your Very First Lesson') ?></h2>
        <p style="color:#374151; max-width: 680px; margin: 0 auto 28px; font-size: 1.1rem; line-height: 1.85; font-weight: 400;">
            <?= htmlspecialchars($site_content['promise_text'] ?? 'I\'m so confident in this program that I back it with a continuation guarantee: if you attend every lesson, complete your practice, and actively use the personalized coaching between sessions, but still don\'t feel you\'re where you want to be after eight weeks, I\'ll continue coaching you for an additional two weeks completely free of charge.') ?>
        </p>
    </div>
</section>

<!-- SERVICES / PROGRAM OVERVIEW (DYNAMIC CMS) -->
<section id="services" class="section-services">
    <div class="services-container">
        
        <div class="services-left">
            <p class="section-label"><?= htmlspecialchars($site_content['services_label'] ?? 'Program Overview') ?></p>
            <h2><?= htmlspecialchars($site_content['services_headline'] ?? 'The 8-Week Executive Pronunciation Program') ?></h2>
            <p><?= htmlspecialchars($site_content['services_desc'] ?? 'A highly focused, 1-on-1 coaching experience designed to identify and improve the specific pronunciation patterns affecting your clarity in English.') ?></p>
            
            <div class="capacity-alert">
                <p><strong>Note:</strong> Because every client receives personalized feedback and direct support between sessions, I only work with a limited number of coaching clients at any given time.</p>
            </div>

            <div class="services-investment">
                <span class="inv-label">Program Investment</span>
                <span class="inv-price"><?= htmlspecialchars($site_content['program_fee'] ?? '$1,250') ?></span>
            </div>
            
            <a href="book.php" class="btn-dark">Book a Professional Communication Consultation</a>
        </div>

        <div class="services-right">
            <div class="services-box dark-box">
                <p class="services-box-title">Personalized Coaching Between Every Lesson</p>
                <p style="font-size: 0.9rem; color: #d1d5db; margin-bottom: 16px; line-height: 1.6;">Unlike traditional pronunciation coaching, I won't disappear until next week's lesson. If you have an important meeting, presentation, interview, or conversation coming up, simply send me a voice message.</p>
                <p style="font-size: 0.9rem; color: #d1d5db; line-height: 1.6;">I'll personally listen to it, identify pronunciation issues, and send you personalized feedback so you continue improving throughout the week instead of waiting until your next lesson. I respond within a reasonable amount of time — typically around 2–3 hours during normal working hours.</p>
            </div>
            
            <div class="services-box">
                <p class="services-box-title">What We Cover</p>
                <ul class="services-list">
                    <li>Speak clearly enough that people understand you the first time</li>
                    <li>Feel confident leading meetings and presentations</li>
                    <li>Sound more professional in high-stakes conversations</li>
                    <li>Communicate naturally instead of translating everything in your head</li>
                    <li>Build stronger relationships because people focus on your ideas instead of your accent</li>
                    <li>Improve pronunciation, fluency, rhythm, and confidence through real conversations rather than textbook exercises</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<!-- PERSONALIZED COACHING (DYNAMIC CMS) -->
<section id="personalized" class="section-personalized" style="padding: 90px 24px; border-top: 1px solid #e5e7eb;">
    <div class="container-sm text-center" style="max-width: 800px; margin: 0 auto;">
        <p class="section-label" style="font-size: 0.85rem; letter-spacing: 0.12em; font-weight: 600; margin-bottom: 16px;"><?= htmlspecialchars($site_content['personalized_label'] ?? 'Not a Textbook Course') ?></p>
        <h2 class="section-title"><?= htmlspecialchars($site_content['personalized_headline'] ?? 'Every Lesson Is Designed Specifically Around YOU') ?></h2>
        <p style="color:#6b7280; max-width: 640px; margin: 0 auto 8px; font-size: 1.05rem; line-height: 1.8;">
            <?= htmlspecialchars($site_content['personalized_text1'] ?? 'Your career. Your meetings. Your presentations. Your pronunciation challenges. Your professional vocabulary. Your communication goals.') ?>
        </p>
        <p style="color:#6b7280; max-width: 680px; margin: 16px auto 0; font-size: 1.05rem; line-height: 1.8;">
            <?= htmlspecialchars($site_content['personalized_text2'] ?? 'Rather than following a generic curriculum, every lesson is built around helping you communicate more clearly in the situations that actually matter to you.') ?>
        </p>
    </div>
</section>

<!-- LONG-TERM ROADMAP (DYNAMIC CMS) -->
<section id="roadmap" class="section-roadmap" style="padding: 90px 24px; border-top: 1px solid #e5e7eb;">
    <div class="container-sm text-center" style="max-width: 800px; margin: 0 auto;">
        <p class="section-label" style="font-size: 0.85rem; letter-spacing: 0.12em; font-weight: 600; margin-bottom: 16px;"><?= htmlspecialchars($site_content['roadmap_label'] ?? 'After the Program') ?></p>
        <h2 class="section-title"><?= htmlspecialchars($site_content['roadmap_headline'] ?? 'Your Personalized Long-Term Improvement Plan') ?></h2>
        <p style="color:#6b7280; max-width: 680px; margin: 0 auto; font-size: 1.05rem; line-height: 1.8;">
            <?= htmlspecialchars($site_content['roadmap_text'] ?? 'At the end of the program, you\'ll receive a personalized roadmap summarizing everything we worked on together — your common pronunciation patterns, reminders for difficult sounds, fluency tips, common grammar mistakes that repeatedly came up, and a personalized action plan so you can continue improving long after the coaching program has finished.') ?>
        </p>
    </div>
</section>

<!-- ABOUT (DYNAMIC CMS) -->
<section id="about" class="section-about">
    <div class="about-grid">
        <div class="about-img">
            <img src="<?= htmlspecialchars($site_content['about_me_image'] ?? 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&q=80&w=800') ?>" alt="Jordan, executive English pronunciation coach for Spanish-speaking professionals" loading="lazy">
        </div>
        <div class="about-text">
            <p class="section-label">About the Coach</p>
            
            <div style="color: #6b7280; font-size: 1.05rem; line-height: 1.8; margin-bottom: 20px;">
                <?= nl2br(htmlspecialchars($site_content['about_me_text'] ?? "I am Jordan, the founder of Bright English Coaching. My background is in identifying subtle differences in sound. Years of musical training taught me to hear small variations in pitch, rhythm, and sound placement that most people miss.\n\nI've also spent hundreds of hours on the other side of the process, learning Spanish and now Basque. Through that journey, I've experienced firsthand the challenge of retraining speech habits and developing new sounds that don't exist in your native language.\n\nToday, I work with Spanish-speaking professionals, including engineers, researchers, healthcare professionals, and managers who already have strong English skills but want to communicate with greater clarity and confidence in professional environments.\n\nThe goal isn't to eliminate your accent. The goal is to help your English reflect the same level of confidence, professionalism, and expertise that you already possess.")) ?>
            </div>
            <a href="https://www.linkedin.com/in/yourprofile" target="_blank" rel="noopener noreferrer" style="display: inline-flex; align-items: center; gap: 8px; color: #1C1A17; font-weight: 600; text-decoration: none; margin-top: 12px; font-family: 'Inter', sans-serif; font-size: 0.95rem; transition: opacity 0.2s;" onmouseover="this.style.opacity='0.7'" onmouseout="this.style.opacity='1'">
                <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/>
                </svg>
                Connect with Jordan on LinkedIn
            </a>
        </div>
    </div>
</section>

<!-- FAQ -->
<section class="section-faq">
    <div class="container-xs text-center">
        <p class="section-label">Common Questions</p>
        <h2 class="section-title">Addressing Your Concerns</h2>
        <p style="color:#6b7280;">Everything you need to know about physiological accent adjustment and executive speech training.</p>
        <div class="faq-list">
            <?php foreach ($faqs as $faq): ?>
            <details>
                <summary><?= htmlspecialchars($faq['q']) ?> <span class="arrow">▼</span></summary>
                <p><?= htmlspecialchars($faq['a']) ?></p>
            </details>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- CONSULTATION SCHEDULER -->
<section id="consultation" class="section-consultation">
    <div class="container-sm text-center" style="margin-bottom:40px;">
        <p class="section-label">Book Your Session</p>
        <h2 class="section-title">Professional Communication Consultation</h2>
        
        <div class="diagnostic-box">
            <p class="diagnostic-title">During our consultation we will:</p>
            <ul class="diagnostic-list">
                <li>✓ Discuss your communication goals and professional challenges</li>
                <li>✓ Identify the pronunciation patterns having the biggest impact on your clarity</li>
                <li>✓ Explain exactly what is preventing you from sounding confident</li>
                <li>✓ Determine whether the coaching program is the right fit</li>
            </ul>
        </div>
    </div>

    <div class="scheduler-card">
        <div class="scheduler-header">
            <div>
                <h3>Select a Date & Time</h3>
                <p>Times shown in your local timezone (<span id="cs-visitor-tz"></span>)</p>
            </div>
            <div class="live-badge">Live Availability</div>
        </div>

        <?php if (!empty($_GET['booked'])): ?>
            <div class="cs-success">
                ✓ Your consultation has been booked. Jordan will be in touch with a calendar invitation shortly.
            </div>
        <?php endif; ?>

        <div id="cs-step1">
            <p class="step-label">1. Select a Date</p>
            
            <?php if (count($available_dates) > 0): ?>
                <div class="cs-form-field" style="max-width: 300px; margin-bottom: 36px;">
                    <input type="text" id="date-picker" placeholder="Click to choose a date..." readonly style="cursor: pointer; background: #fff;">
                </div>
            <?php else: ?>
                <p class="no-slots-msg">Jordan's schedule is currently full. Please check back shortly.</p>
            <?php endif; ?>

            <div id="cs-time-section" class="cs-time-section">
                <p class="step-label">2. Choose a Time on <span id="cs-time-header"></span></p>
                <div id="cs-time-grid" class="cs-time-grid"></div>
            </div>

            <div id="cs-continue-row" class="cs-continue-row">
                <button type="button" class="btn-dark" onclick="csGoToForm()">Continue</button>
            </div>
        </div>

        <form id="cs-step2" class="cs-step2" action="book.php" method="POST">
            <div class="cs-summary-bar">
                <div>
                    <div class="sb-label">Your Selection</div>
                    <div class="sb-value" id="cs-summary-text"></div>
                </div>
                <button type="button" class="btn-change" onclick="csGoBack()">Change</button>
            </div>

            <input type="hidden" id="cs-hidden-date" name="requested_date">
            <input type="hidden" id="cs-hidden-time" name="requested_time">

            <div class="cs-form-field">
                <label>Your Full Name</label>
                <input type="text" name="name" required placeholder="Enter your full name">
            </div>
            <div class="cs-form-row">
                <div class="cs-form-field">
                    <label>Your Email</label>
                    <input type="email" name="email" required placeholder="you@company.com">
                </div>
                <div class="cs-form-field">
                    <label>Company / Profession</label>
                    <input type="text" name="company" required placeholder="e.g. Senior Staff Scientist">
                </div>
            </div>
            <div class="cs-form-footer">
                <button type="submit" class="btn-dark">Confirm Booking</button>
            </div>
        </form>
    </div>
</section>

<!-- CONTACT (DYNAMIC CMS) -->
<section id="contact" class="section-contact">
    <div class="contact-inner">
        <div class="text-center" style="margin-bottom:40px;">
            <p class="section-label">Have Questions?</p>
            <h2><?= htmlspecialchars($site_content['contact_headline'] ?? 'Prefer to reach out directly?') ?></h2>
            <p><?= htmlspecialchars($site_content['contact_description'] ?? 'If you want to ask a question before scheduling, send a direct message. Jordan answers all inquiries personally.') ?></p>
        </div>

        <?php if ($success_message): ?>
            <div class="contact-alert-success"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="contact-alert-error"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <form action="index.php#contact" method="POST" class="contact-form">
            <div class="cf-field">
                <label>Your Full Name</label>
                <input type="text" name="name" required placeholder="Enter your name">
            </div>
            <div class="cf-row">
                <div class="cf-field">
                    <label>Email Address</label>
                    <input type="email" name="email" required placeholder="you@company.com">
                </div>
                <div class="cf-field">
                    <label>LinkedIn Profile (Optional)</label>
                    <input type="text" name="linkedin" placeholder="linkedin.com/in/username">
                </div>
            </div>
            <div class="cf-field">
                <label>Your Message / Current Situation</label>
                <textarea name="message" rows="4" required placeholder="Describe your current pronunciation challenges or questions..." style="width:100%;background:#fff;color:#111;border:none;padding:14px 16px;font-size:0.95rem;font-family:'Inter',sans-serif;outline:none;resize:vertical;"></textarea>
            </div>
            <button type="submit" class="btn-contact">Send Message</button>
        </form>
    </div>
</section>

<!-- FOOTER -->
<footer>
    <div class="footer-main">
        <div class="footer-grid">
            <div>
                <a href="#" class="footer-brand">
                    <img id="footer-logo" src="assets/logo-dark-2.png" alt="Bright English Coaching Logo" style="max-height: 200px; width: auto; margin-bottom: 12px;">
                </a>
                <p class="footer-desc">Specialized English pronunciation coaching helping Spanish-speaking professionals communicate clearly and lead with absolute confidence.</p>
            </div>
            <div class="footer-col">
                <p class="footer-col-title">Navigation</p>
                <ul>
                    <li><a href="#">Home</a></li>
                    <li><a href="#about">About Me</a></li>
                    <li><a href="#process">Services</a></li>
                    <li><a href="#contact">Contact</a></li>
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
                    <li><a href="https://www.linkedin.com/in/yourprofile" target="_blank" rel="noopener noreferrer">LinkedIn</a></li>
                </ul>
            </div>
        </div>
    </div>
    <div class="footer-bottom">
        &copy; <?php echo date("Y"); ?> Bright English Coaching. All rights reserved.
    </div>
</footer>

<!-- ACCESSIBILITY WIDGET -->
<button id="a11y-toggle-btn" class="a11y-widget-btn" aria-label="Accessibility Options">
    <svg width="28" height="28" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 2a10 10 0 100 20 10 10 0 000-20zm0 4a2 2 0 110 4 2 2 0 010-4zm4 6H8v2h2v4h4v-4h2v-2z" />
    </svg>
</button>
<div id="a11y-panel" class="a11y-panel">
    <h4>Accessibility Options</h4>
    
    <div class="a11y-option">
        <span>Dark Mode</span>
        <button id="btn-dark-mode">Toggle</button>
    </div>
    
    <div class="a11y-option">
        <span>Text Size</span>
        <div style="display:flex;gap:4px;">
            <button id="btn-text-normal">A</button>
            <button id="btn-text-large">A+</button>
            <button id="btn-text-xlarge">A++</button>
        </div>
    </div>
    
    <div class="a11y-option">
        <span>Dyslexia Font</span>
        <button id="btn-dyslexia">Toggle</button>
    </div>
    
    <button id="btn-a11y-reset" class="a11y-reset">Reset Preferences</button>
</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script>
    // --- Accessibility Logic ---
    const a11yToggleBtn = document.getElementById('a11y-toggle-btn');
    const a11yPanel = document.getElementById('a11y-panel');
    const btnDarkMode = document.getElementById('btn-dark-mode');
    const btnTextNormal = document.getElementById('btn-text-normal');
    const btnTextLarge = document.getElementById('btn-text-large');
    const btnTextXlarge = document.getElementById('btn-text-xlarge');
    const btnDyslexia = document.getElementById('btn-dyslexia');
    const btnReset = document.getElementById('btn-a11y-reset');
    const mainLogo = document.getElementById('main-logo');
    const footerLogo = document.getElementById('footer-logo'); // Defaults to assets/logo-dark-2.png based on HTML

    // Load preferences
    document.addEventListener('DOMContentLoaded', () => {
        if (localStorage.getItem('a11y-darkmode') === 'true') toggleDarkMode(true);
        if (localStorage.getItem('a11y-dyslexia') === 'true') toggleDyslexia(true);
        const savedTextSize = localStorage.getItem('a11y-textsize');
        if (savedTextSize) setTextSize(savedTextSize);
    });

    // Panel Toggle
    a11yToggleBtn.addEventListener('click', () => {
        a11yPanel.classList.toggle('active');
    });

    // Dark Mode Toggle Logic
    btnDarkMode.addEventListener('click', () => {
        const isDark = document.body.classList.contains('dark-mode');
        toggleDarkMode(!isDark);
    });

    function toggleDarkMode(enable) {
        if (enable) {
            document.body.classList.add('dark-mode');
            localStorage.setItem('a11y-darkmode', 'true');
            // Change primary logo based on provided file structure naming conventions
            if (mainLogo) mainLogo.src = 'assets/logo-dark.png';
        } else {
            document.body.classList.remove('dark-mode');
            localStorage.setItem('a11y-darkmode', 'false');
            if (mainLogo) mainLogo.src = 'assets/logo.png';
        }
    }

    // Text Size Logic
    btnTextNormal.addEventListener('click', () => setTextSize('normal'));
    btnTextLarge.addEventListener('click', () => setTextSize('large'));
    btnTextXlarge.addEventListener('click', () => setTextSize('xlarge'));

    function setTextSize(size) {
        document.body.classList.remove('a11y-text-large', 'a11y-text-xlarge');
        if (size === 'large') document.body.classList.add('a11y-text-large');
        if (size === 'xlarge') document.body.classList.add('a11y-text-xlarge');
        localStorage.setItem('a11y-textsize', size);
    }

    // Dyslexia Logic
    btnDyslexia.addEventListener('click', () => {
        const isDyslexic = document.body.classList.contains('a11y-dyslexia');
        toggleDyslexia(!isDyslexic);
    });

    function toggleDyslexia(enable) {
        if (enable) {
            document.body.classList.add('a11y-dyslexia');
            localStorage.setItem('a11y-dyslexia', 'true');
        } else {
            document.body.classList.remove('a11y-dyslexia');
            localStorage.setItem('a11y-dyslexia', 'false');
        }
    }

    // Reset Logic
    btnReset.addEventListener('click', () => {
        toggleDarkMode(false);
        setTextSize('normal');
        toggleDyslexia(false);
        localStorage.removeItem('a11y-darkmode');
        localStorage.removeItem('a11y-textsize');
        localStorage.removeItem('a11y-dyslexia');
    });

    // --- End Accessibility Logic ---

    // Nav Logic
    const hamburger  = document.getElementById('hamburger');
    const mobileMenu = document.getElementById('mobile-menu');
    const iconMenu   = document.getElementById('icon-menu');
    const iconClose  = document.getElementById('icon-close');

    hamburger.addEventListener('click', () => {
        mobileMenu.classList.toggle('open');
        iconMenu.style.display  = mobileMenu.classList.contains('open') ? 'none'  : 'block';
        iconClose.style.display = mobileMenu.classList.contains('open') ? 'block' : 'none';
    });
    document.querySelectorAll('.mobile-link').forEach(l => {
        l.addEventListener('click', () => {
            mobileMenu.classList.remove('open');
            iconMenu.style.display  = 'block';
            iconClose.style.display = 'none';
        });
    });

    // ==========================================
    // Scheduler Logic (timezone-aware)
    // ==========================================
    // slotsFlat: [{ date: 'YYYY-MM-DD' (coach-local), time: 'HH:MM:SS' (coach-local), utc: ISO8601 }, ...]
    const slotsFlat = <?= $slots_json ?>;

    // Detect the visitor's timezone and show it in the UI
    const csVisitorTz = Intl.DateTimeFormat().resolvedOptions().timeZone;
    const csVisitorTzEl = document.getElementById('cs-visitor-tz');
    if (csVisitorTzEl) csVisitorTzEl.textContent = csVisitorTz;

    // Group slots by the VISITOR's local calendar date (browser Date getters
    // are automatically local — this converts both date and time at once).
    const slotsByDate = {};
    slotsFlat.forEach(slot => {
        const localDt = new Date(slot.utc);
        const localKey = localDt.getFullYear() + '-' +
            String(localDt.getMonth() + 1).padStart(2, '0') + '-' +
            String(localDt.getDate()).padStart(2, '0');

        if (!slotsByDate[localKey]) slotsByDate[localKey] = [];
        slotsByDate[localKey].push({
            localDt: localDt,     // for display, already in visitor's local time
            dbDate: slot.date,    // original coach-local date — required for booking submission
            dbTime: slot.time     // original coach-local time — required for booking submission
        });
    });
    Object.keys(slotsByDate).forEach(k => slotsByDate[k].sort((a, b) => a.localDt - b.localDt));

    const availableDatesArray = Object.keys(slotsByDate);

    let csDate = '', csDateDisplay = '', csTime = '', csTimeDisplay = '';

    function formatTime(dateObj) {
        return dateObj.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
    }

    if (document.getElementById("date-picker")) {
        flatpickr("#date-picker", {
            enable: availableDatesArray,
            dateFormat: "Y-m-d",
            minDate: "today",
            disableMobile: "true",
            appendTo: document.documentElement, // keep calendar outside <body> so it's unaffected by the a11y text-size zoom and dark-mode span overrides
            onChange: function(selectedDates, dateStr, instance) {
                if (selectedDates.length > 0) {
                    const options = { month: 'long', day: 'numeric' };
                    const display = selectedDates[0].toLocaleDateString('en-US', options);
                    csSelectDate(dateStr, display);
                }
            }
        });
    }

    function csSelectDate(localDateKey, displayDate) {
        csDateDisplay = displayDate;
        csTime = ''; csTimeDisplay = ''; csDate = '';

        const times = slotsByDate[localDateKey] || [];
        const grid  = document.getElementById('cs-time-grid');
        grid.innerHTML = '';

        if (times.length === 0) {
            grid.innerHTML = '<p class="no-slots-msg">No time slots available for this date.</p>';
        } else {
            times.forEach(slot => {
                const btn = document.createElement('button');
                btn.type      = 'button';
                btn.className = 'cs-time-card';
                btn.textContent = formatTime(slot.localDt);
                btn.onclick = () => csSelectTime(btn, slot.dbTime, formatTime(slot.localDt), slot.dbDate);
                grid.appendChild(btn);
            });
        }

        document.getElementById('cs-time-section').classList.add('visible');
        document.getElementById('cs-time-header').textContent = displayDate.toUpperCase();
        document.getElementById('cs-continue-row').classList.remove('visible');
    }

    function csSelectTime(el, dbTime, displayTime, dbDate) {
        csTime = dbTime; csTimeDisplay = displayTime; csDate = dbDate;
        document.querySelectorAll('.cs-time-card').forEach(c => c.classList.remove('selected'));
        el.classList.add('selected');
        document.getElementById('cs-continue-row').classList.add('visible');
    }

    function csGoToForm() {
        document.getElementById('cs-step1').style.display = 'none';
        document.getElementById('cs-step2').style.display = 'block';
        document.getElementById('cs-summary-text').textContent = `${csDateDisplay} at ${csTimeDisplay} (${csVisitorTz})`;
        document.getElementById('cs-hidden-date').value = csDate;
        document.getElementById('cs-hidden-time').value = csTime;
        document.getElementById('consultation').scrollIntoView({ behavior: 'smooth' });
    }

    function csGoBack() {
        document.getElementById('cs-step2').style.display = 'none';
        document.getElementById('cs-step1').style.display = 'block';
        document.getElementById('consultation').scrollIntoView({ behavior: 'smooth' });
    }
    
    // Slider Logic
    document.addEventListener("DOMContentLoaded", function() {
        const slider = document.getElementById('testimonial-slider');
        if (!slider) return;

        let autoPlayInterval;
        const autoPlayDelay = 4000; 

        function startAutoPlay() {
            autoPlayInterval = setInterval(() => {
                const firstCard = slider.querySelector('.testimonial-card');
                if (!firstCard) return;

                const scrollAmount = firstCard.offsetWidth + 24; 

                if (slider.scrollLeft + slider.clientWidth >= slider.scrollWidth - 10) {
                    slider.scrollTo({ left: 0, behavior: 'smooth' });
                } else {
                    slider.scrollBy({ left: scrollAmount, behavior: 'smooth' });
                }
            }, autoPlayDelay);
        }

        function stopAutoPlay() {
            clearInterval(autoPlayInterval);
        }

        startAutoPlay();

        slider.addEventListener('mouseenter', stopAutoPlay);
        slider.addEventListener('mouseleave', startAutoPlay);

        slider.addEventListener('touchstart', stopAutoPlay, {passive: true});
        slider.addEventListener('touchend', startAutoPlay, {passive: true});
    });
</script>
</body>
</html>