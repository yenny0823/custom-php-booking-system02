<?php
// content_page.php
session_start();
require_once 'config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit;
}

if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_destroy();
    header("Location: admin_login.php");
    exit;
}

$success_msg = '';
$error_msg = '';

// List of all editable text keys on the site
$cms_keys = [
    'hero_headline', 'hero_subheadline', 'hero_description',
    'problem_label', 'problem_headline', 'problem_list',
    'paradox_headline', 'paradox_description',
    'process_label', 'process_headline',
    'pillar1_title', 'pillar1_text',
    'pillar2_title', 'pillar2_text',
    'pillar3_title', 'pillar3_text',
    'outcomes_label', 'outcomes_headline', 'outcomes_subtitle', 'outcomes_list',
    'promise_label', 'promise_headline', 'promise_text',
    'services_label', 'services_headline', 'services_desc', 'program_fee',
    'personalized_label', 'personalized_headline', 'personalized_text1', 'personalized_text2',
    'roadmap_label', 'roadmap_headline', 'roadmap_text',
    'about_me_text',
    'contact_headline', 'contact_description'
];

// ==========================================
// HANDLE SITE CONTENT (CMS) UPDATES
// ==========================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'update_site_content') {
    try {
        // Prepare statements for updating or inserting (in case the key doesn't exist in DB yet)
        $stmt_check = $pdo->prepare("SELECT 1 FROM site_content WHERE section_key = ?");
        $stmt_update = $pdo->prepare("UPDATE site_content SET content = ? WHERE section_key = ?");
        $stmt_insert = $pdo->prepare("INSERT INTO site_content (section_key, content) VALUES (?, ?)");

        // Loop through all defined keys and save their POST values
        foreach ($cms_keys as $key) {
            if (isset($_POST[$key])) {
                $val = trim($_POST[$key]);
                
                $stmt_check->execute([$key]);
                if ($stmt_check->fetchColumn()) {
                    $stmt_update->execute([$val, $key]);
                } else {
                    $stmt_insert->execute([$key, $val]);
                }
            }
        }
        
        // --- HANDLE IMAGE UPLOAD ---
        if (isset($_FILES['about_me_image']) && $_FILES['about_me_image']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['about_me_image']['tmp_name'];
            $file_name = basename($_FILES['about_me_image']['name']);
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
            
            if (in_array($file_ext, $allowed_exts)) {
                $upload_dir = 'assets/uploads/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $new_file_name = 'profile_' . time() . '.' . $file_ext;
                $dest_path = $upload_dir . $new_file_name;
                
                if (move_uploaded_file($file_tmp, $dest_path)) {
                    // Save image path to DB
                    $stmt_check->execute(['about_me_image']);
                    if ($stmt_check->fetchColumn()) {
                        $stmt_update->execute([$dest_path, 'about_me_image']);
                    } else {
                        $stmt_insert->execute(['about_me_image', $dest_path]);
                    }
                } else {
                    $error_msg = "Could not save the uploaded image. Please check your folder permissions.";
                }
            } else {
                $error_msg = "Invalid image format. Please upload a JPG, PNG, or WebP file.";
            }
        }
        
        if (empty($error_msg)) {
            $success_msg = "Website content updated successfully!";
        }
        
    } catch (PDOException $e) {
        $error_msg = "Error updating content: " . $e->getMessage();
    }
}

// Fetch Site Content for the Form
try {
    $content_stmt = $pdo->query("SELECT section_key, content FROM site_content");
    $site_content = [];
    while ($row = $content_stmt->fetch(PDO::FETCH_ASSOC)) {
        $site_content[$row['section_key']] = $row['content'];
    }
} catch (PDOException $e) {
    $site_content = [];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Site Content | Bright English Coaching</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css?v=14">
    <style>
        .section-divider {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            color: #1C1A17;
            margin: 48px 0 20px;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 8px;
        }
        .form-row {
            display: flex;
            gap: 24px;
            margin-bottom: 24px;
        }
        .form-row > div {
            flex: 1;
        }
        .helper-text {
            font-size: 0.8rem;
            color: #6b7280;
            margin-bottom: 8px;
            display: block;
        }
    </style>
</head>
<body class="page-admin">

<nav class="nav">
    <div class="nav-inner">
        <div class="nav-brand">Dashboard</div>
        
        <div class="nav-links">
            <a href="admin_dashboard.php" class="nav-link">Inbox</a>
            <a href="availability_manager.php" class="nav-link">Availability</a>
            <a href="content_page.php" class="nav-link active">Site Content</a>
            <a href="testimonials_page.php" class="nav-link">Testimonials</a>
        </div>

        <div class="nav-right">
            <span>Welcome, <?= htmlspecialchars($_SESSION['admin_username']) ?></span>
            <a href="?action=logout" class="btn-logout">Logout</a>
        </div>
    </div>
</nav>

<main class="main">

    <div style="margin-bottom: 36px;">
        <h1 style="font-family: 'Playfair Display', serif; font-size: 2.4rem; color: #1C1A17; margin-bottom: 6px;">Manage Site Content</h1>
        <p style="color: #6b7280; font-size: 0.95rem;">Update the main text, images, and lists across your live website.</p>
    </div>

    <?php if ($success_msg): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <div class="card" style="margin-bottom: 48px;">
        <div style="padding: 32px;">
            <form method="POST" action="content_page.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_site_content">
                
                <h3 class="section-divider" style="margin-top: 0;">1. Hero Section</h3>
                <div class="form-group" style="margin-bottom:24px;">
                    <label class="form-label" style="color:#1C1A17;">Main Headline</label>
                    <input type="text" name="hero_headline" class="form-input" style="font-size: 1.1rem; padding: 12px 16px;" value="<?= htmlspecialchars($site_content['hero_headline'] ?? 'Hablas con impacto en español.') ?>">
                </div>
                <div class="form-group" style="margin-bottom:24px;">
                    <label class="form-label" style="color:#1C1A17;">Subheadline (Italics)</label>
                    <input type="text" name="hero_subheadline" class="form-input" style="font-size: 1.1rem; padding: 12px 16px;" value="<?= htmlspecialchars($site_content['hero_subheadline'] ?? 'Now, lead with absolute clarity in English.') ?>">
                </div>
                <div class="form-group" style="margin-bottom:32px;">
                    <label class="form-label" style="color:#1C1A17;">Description Paragraph</label>
                    <textarea name="hero_description" rows="4" class="form-input" style="resize: vertical; font-size: 1rem; padding: 12px 16px;"><?= htmlspecialchars($site_content['hero_description'] ?? 'Pronunciation coaching for Spanish-speaking professionals...') ?></textarea>
                </div>

                <h3 class="section-divider">2. "Does This Sound Familiar?" Section</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" style="color:#1C1A17;">Section Label</label>
                        <input type="text" name="problem_label" class="form-input" value="<?= htmlspecialchars($site_content['problem_label'] ?? 'Does This Sound Familiar?') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="color:#1C1A17;">Main Headline</label>
                        <input type="text" name="problem_headline" class="form-input" value="<?= htmlspecialchars($site_content['problem_headline'] ?? 'You know exactly what you want to say, but...') ?>">
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:32px;">
                    <label class="form-label" style="color:#1C1A17;">Problem Symptoms List</label>
                    <span class="helper-text">Place each symptom on a new line. The website will automatically add the checkmarks.</span>
                    <textarea name="problem_list" rows="6" class="form-input" style="resize: vertical;"><?= htmlspecialchars($site_content['problem_list'] ?? "People sometimes ask you to repeat certain words.\nYou understand English well but still hesitate to speak up in meetings.") ?></textarea>
                </div>

                <h3 class="section-divider">3. "The Paradox" Section</h3>
                <div class="form-group" style="margin-bottom:24px;">
                    <label class="form-label" style="color:#1C1A17;">Paradox Headline</label>
                    <input type="text" name="paradox_headline" class="form-input" style="font-size: 1.1rem; padding: 12px 16px;" value="<?= htmlspecialchars($site_content['paradox_headline'] ?? 'Your expertise deserves to be understood.') ?>">
                </div>
                <div class="form-group" style="margin-bottom:32px;">
                    <label class="form-label" style="color:#1C1A17;">Paradox Description</label>
                    <textarea name="paradox_description" rows="8" class="form-input" style="resize: vertical; font-size: 0.95rem; line-height:1.6; padding: 16px;"><?= htmlspecialchars($site_content['paradox_description'] ?? 'In Spanish, you can explain complex ideas...') ?></textarea>
                </div>

                <h3 class="section-divider">4. The Three Pillars Section</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" style="color:#1C1A17;">Section Label</label>
                        <input type="text" name="process_label" class="form-input" value="<?= htmlspecialchars($site_content['process_label'] ?? 'Curriculum Focus') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="color:#1C1A17;">Main Headline</label>
                        <input type="text" name="process_headline" class="form-input" value="<?= htmlspecialchars($site_content['process_headline'] ?? 'The Three Pillars of Clear Communication') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" style="color:#1C1A17;">Pillar 1 Title</label>
                        <input type="text" name="pillar1_title" class="form-input" value="<?= htmlspecialchars($site_content['pillar1_title'] ?? 'Pronunciation & Sound Accuracy') ?>">
                        <textarea name="pillar1_text" rows="4" class="form-input" style="margin-top:8px; font-size:0.9rem;"><?= htmlspecialchars($site_content['pillar1_text'] ?? 'Many Spanish speakers already have strong English skills...') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="color:#1C1A17;">Pillar 2 Title</label>
                        <input type="text" name="pillar2_title" class="form-input" value="<?= htmlspecialchars($site_content['pillar2_title'] ?? 'Rhythm & Connected Speech') ?>">
                        <textarea name="pillar2_text" rows="4" class="form-input" style="margin-top:8px; font-size:0.9rem;"><?= htmlspecialchars($site_content['pillar2_text'] ?? 'Native English speakers don\'t pronounce every word separately...') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="color:#1C1A17;">Pillar 3 Title</label>
                        <input type="text" name="pillar3_title" class="form-input" value="<?= htmlspecialchars($site_content['pillar3_title'] ?? 'Professional Communication') ?>">
                        <textarea name="pillar3_text" rows="4" class="form-input" style="margin-top:8px; font-size:0.9rem;"><?= htmlspecialchars($site_content['pillar3_text'] ?? 'Apply what you\'ve learned to real-world situations...') ?></textarea>
                    </div>
                </div>

                <h3 class="section-divider">5. Outcomes Section</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" style="color:#1C1A17;">Section Label</label>
                        <input type="text" name="outcomes_label" class="form-input" value="<?= htmlspecialchars($site_content['outcomes_label'] ?? 'The Result') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="color:#1C1A17;">Main Headline</label>
                        <input type="text" name="outcomes_headline" class="form-input" value="<?= htmlspecialchars($site_content['outcomes_headline'] ?? 'Communicate with Absolute Clarity') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" style="color:#1C1A17;">Subtitle Text</label>
                    <input type="text" name="outcomes_subtitle" class="form-input" value="<?= htmlspecialchars($site_content['outcomes_subtitle'] ?? 'After completing this program, you will:') ?>">
                </div>
                <div class="form-group" style="margin-bottom:32px;">
                    <label class="form-label" style="color:#1C1A17;">Outcomes List</label>
                    <span class="helper-text">Place each outcome on a new line. The website will automatically format the list.</span>
                    <textarea name="outcomes_list" rows="6" class="form-input" style="resize: vertical;"><?= htmlspecialchars($site_content['outcomes_list'] ?? "Be asked to repeat yourself far less often.\nHear \"What?\" and \"Can you repeat that?\" much less.") ?></textarea>
                </div>

                <h3 class="section-divider">6. My Promise & Guarantee</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" style="color:#1C1A17;">Section Label</label>
                        <input type="text" name="promise_label" class="form-input" value="<?= htmlspecialchars($site_content['promise_label'] ?? 'My Promise') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="color:#1C1A17;">Headline</label>
                        <input type="text" name="promise_headline" class="form-input" value="<?= htmlspecialchars($site_content['promise_headline'] ?? 'You\'ll Notice Improvement From Your Very First Lesson') ?>">
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:32px;">
                    <label class="form-label" style="color:#1C1A17;">Promise Description</label>
                    <textarea name="promise_text" rows="4" class="form-input" style="resize: vertical;"><?= htmlspecialchars($site_content['promise_text'] ?? 'I\'m so confident in this program that I back it with a continuation guarantee...') ?></textarea>
                </div>

                <h3 class="section-divider">7. Program Overview & Investment</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" style="color:#1C1A17;">Label & Headline</label>
                        <input type="text" name="services_label" class="form-input" value="<?= htmlspecialchars($site_content['services_label'] ?? 'Program Overview') ?>" style="margin-bottom:8px;">
                        <input type="text" name="services_headline" class="form-input" value="<?= htmlspecialchars($site_content['services_headline'] ?? 'The 8-Week Executive Pronunciation Program') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="color:#1C1A17;">Program Fee (Investment)</label>
                        <input type="text" name="program_fee" class="form-input" value="<?= htmlspecialchars($site_content['program_fee'] ?? '$1,250') ?>">
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:32px;">
                    <label class="form-label" style="color:#1C1A17;">Program Description</label>
                    <textarea name="services_desc" rows="3" class="form-input" style="resize: vertical;"><?= htmlspecialchars($site_content['services_desc'] ?? 'A highly focused, 1-on-1 coaching experience designed to identify and improve...') ?></textarea>
                </div>

                <h3 class="section-divider">8. Personalized Approach & Long-Term Roadmap</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" style="color:#1C1A17;">Personalized Label</label>
                        <input type="text" name="personalized_label" class="form-input" value="<?= htmlspecialchars($site_content['personalized_label'] ?? 'Not a Textbook Course') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="color:#1C1A17;">Personalized Headline</label>
                        <input type="text" name="personalized_headline" class="form-input" value="<?= htmlspecialchars($site_content['personalized_headline'] ?? 'Every Lesson Is Designed Specifically Around YOU') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" style="color:#1C1A17;">Personalized Paragraph 1</label>
                        <textarea name="personalized_text1" rows="3" class="form-input"><?= htmlspecialchars($site_content['personalized_text1'] ?? 'Your career. Your meetings. Your presentations. Your pronunciation challenges...') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="color:#1C1A17;">Personalized Paragraph 2</label>
                        <textarea name="personalized_text2" rows="3" class="form-input"><?= htmlspecialchars($site_content['personalized_text2'] ?? 'Rather than following a generic curriculum, every lesson is built around helping you...') ?></textarea>
                    </div>
                </div>

                <div class="form-row" style="margin-top: 24px;">
                    <div class="form-group">
                        <label class="form-label" style="color:#1C1A17;">Roadmap Label & Headline</label>
                        <input type="text" name="roadmap_label" class="form-input" value="<?= htmlspecialchars($site_content['roadmap_label'] ?? 'After the Program') ?>" style="margin-bottom:8px;">
                        <input type="text" name="roadmap_headline" class="form-input" value="<?= htmlspecialchars($site_content['roadmap_headline'] ?? 'Your Personalized Long-Term Improvement Plan') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="color:#1C1A17;">Roadmap Text</label>
                        <textarea name="roadmap_text" rows="3" class="form-input"><?= htmlspecialchars($site_content['roadmap_text'] ?? 'At the end of the program, you\'ll receive a personalized roadmap summarizing everything...') ?></textarea>
                    </div>
                </div>

                <h3 class="section-divider">9. Program Details & Biography</h3>
                <div class="form-group" style="margin-bottom:24px;">
                    <label class="form-label" style="color:#1C1A17;">About Me (Profile Picture)</label>
                    <?php if (!empty($site_content['about_me_image'])): ?>
                        <div style="margin-bottom: 12px;">
                            <img src="<?= htmlspecialchars($site_content['about_me_image']) ?>" alt="Current Profile" style="width: 120px; height: 120px; object-fit: cover; border-radius: 4px; border: 1px solid #e5e7eb;">
                        </div>
                    <?php endif; ?>
                    <span class="helper-text">Upload a new image to replace your current headshot.</span>
                    <input type="file" name="about_me_image" accept="image/jpeg, image/png, image/webp" class="form-input" style="padding: 10px;">
                </div>
                <div class="form-group" style="margin-bottom:32px;">
                    <label class="form-label" style="color:#1C1A17;">About Me (Biography)</label>
                    <span class="helper-text">You can use the Enter key to create separate paragraphs. They will automatically be formatted on the website.</span>
                    <textarea name="about_me_text" rows="14" class="form-input" style="resize: vertical; font-size: 0.95rem; line-height:1.6; padding: 16px;"><?= htmlspecialchars($site_content['about_me_text'] ?? "I am Jordan, the founder of Bright English Coaching...") ?></textarea>
                </div>

                <h3 class="section-divider">10. Contact Section</h3>
                <div class="form-group" style="margin-bottom:24px;">
                    <label class="form-label" style="color:#1C1A17;">Contact Headline</label>
                    <input type="text" name="contact_headline" class="form-input" style="font-size: 1.1rem; padding: 12px 16px;" value="<?= htmlspecialchars($site_content['contact_headline'] ?? 'Prefer to reach out directly?') ?>">
                </div>
                <div class="form-group" style="margin-bottom:24px;">
                    <label class="form-label" style="color:#1C1A17;">Contact Description</label>
                    <textarea name="contact_description" rows="3" class="form-input" style="resize: vertical; font-size: 1rem; padding: 12px 16px;"><?= htmlspecialchars($site_content['contact_description'] ?? 'If you want to ask a question before scheduling, send a direct message.') ?></textarea>
                </div>
                
                <div style="text-align: right; border-top:1px solid #e5e7eb; padding-top:24px; margin-top:32px;">
                    <button type="submit" class="btn-primary" style="width: auto; padding: 14px 40px; font-size: 0.85rem;">Save All Website Changes</button>
                </div>
            </form>
        </div>
    </div>

</main>
</body>
</html>