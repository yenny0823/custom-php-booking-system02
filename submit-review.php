<?php
require_once 'config.php';
$message = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name   = trim($_POST['client_name'] ?? '');
    $role   = trim($_POST['client_role'] ?? '');
    $quote  = trim($_POST['quote'] ?? '');
    $rating = (int)($_POST['rating'] ?? 5);

    // Ensure rating stays within 1 to 5
    if ($rating < 1) $rating = 1;
    if ($rating > 5) $rating = 5;

    if (!empty($name) && !empty($quote)) {
        // We only insert text data; video_url defaults to NULL in the database
        $stmt = $pdo->prepare("INSERT INTO testimonials (client_name, client_role, quote, rating) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$name, $role, $quote, $rating])) {
            $message = "Thank you! Your testimonial has been submitted successfully.";
        } else {
            $message = "Something went wrong. Please try again.";
        }
    } else {
        $message = "Please fill out all required fields.";
    }
}
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
    <title>Leave a Review | Bright English Coaching</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css?v=8">
    <style>
        .star-rating { display: flex; flex-direction: row-reverse; justify-content: flex-end; gap: 4px; }
        .star-rating input { display: none; }
        .star-rating label { font-size: 1.8rem; color: #e5e7eb; cursor: pointer; transition: color 0.2s; line-height: 1; }
        .star-rating input:checked ~ label, .star-rating label:hover, .star-rating label:hover ~ label { color: #1C1A17; }
    </style>
</head>
<body class="page-booking">
    <div class="main">
        <div class="page-header">
            <h1>Leave a Review</h1>
            <p>Share your experience working with Jordan.</p>
        </div>

        <?php if ($message): ?>
            <div class="alert <?= strpos($message, 'Thank you') !== false ? 'alert-success' : 'alert-error' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="submit-review.php">
            <div class="field">
                <label>Overall Rating</label>
                <div class="star-rating">
                    <input type="radio" id="star5" name="rating" value="5" checked><label for="star5">★</label>
                    <input type="radio" id="star4" name="rating" value="4"><label for="star4">★</label>
                    <input type="radio" id="star3" name="rating" value="3"><label for="star3">★</label>
                    <input type="radio" id="star2" name="rating" value="2"><label for="star2">★</label>
                    <input type="radio" id="star1" name="rating" value="1"><label for="star1">★</label>
                </div>
            </div>
            <div class="form-row" style="margin-top: 24px;">
                <div class="field">
                    <label>Your Name</label>
                    <input type="text" name="client_name" required placeholder="e.g. Alejandro C.">
                </div>
                <div class="field">
                    <label>Professional Role</label>
                    <input type="text" name="client_role" placeholder="e.g. Senior Software Engineer">
                </div>
            </div>
            <div class="field">
                <label>Your Experience (Written Quote)</label>
                <textarea name="quote" rows="5" required placeholder="How did this program impact your communication?" style="width: 100%; background: #F9F8F6; border: 1px solid #e5e7eb; padding: 16px; font-family: 'Inter', sans-serif; outline: none;"></textarea>
            </div>
            <div class="form-footer">
                <button type="submit" class="btn-dark">Submit Testimonial</button>
            </div>
        </form>
    </div>
</body>
</html>