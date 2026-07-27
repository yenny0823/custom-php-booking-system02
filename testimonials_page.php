<?php
// testimonials_page.php
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

// Handle Testimonial Actions (Approve, Reject, Delete, Add Video, Manually Add Testimonial, Edit Testimonial)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'])) {
    
    // 1. Approve / Reject Pending Testimonials
    if (($_POST['action'] === 'approve' || $_POST['action'] === 'reject') && isset($_POST['testimonial_id'])) {
        $status = $_POST['action'] === 'approve' ? 'approved' : 'rejected';
        $stmt = $pdo->prepare("UPDATE testimonials SET status = ? WHERE id = ?");
        $stmt->execute([$status, $_POST['testimonial_id']]);
        header("Location: testimonials_page.php");
        exit;
    }

    // 2. Add/Update Video Link on Approved Testimonial
    if ($_POST['action'] === 'add_video' && isset($_POST['testimonial_id'])) {
        $video_url = trim($_POST['video_url'] ?? '');
        $stmt = $pdo->prepare("UPDATE testimonials SET video_url = ? WHERE id = ?");
        $stmt->execute([$video_url ?: null, $_POST['testimonial_id']]);
        header("Location: testimonials_page.php");
        exit;
    }

    // 3. Delete Approved Testimonial
    if ($_POST['action'] === 'delete_testimonial' && isset($_POST['testimonial_id'])) {
        $stmt = $pdo->prepare("DELETE FROM testimonials WHERE id = ?");
        $stmt->execute([$_POST['testimonial_id']]);
        header("Location: testimonials_page.php");
        exit;
    }

    // 4. Manually Add a New Testimonial
    if ($_POST['action'] === 'add_testimonial') {
        $name      = trim($_POST['client_name'] ?? '');
        $role      = trim($_POST['client_role'] ?? '');
        $quote     = trim($_POST['quote'] ?? '');
        $rating    = (int)($_POST['rating'] ?? 5);
        $video_url = trim($_POST['video_url'] ?? '');

        if ($rating < 1) $rating = 1;
        if ($rating > 5) $rating = 5;

        if (!empty($name) && !empty($quote)) {
            $stmt = $pdo->prepare("INSERT INTO testimonials (client_name, client_role, quote, rating, video_url, status) VALUES (?, ?, ?, ?, ?, 'approved')");
            $stmt->execute([$name, $role, $quote, $rating, $video_url ?: null]);
            header("Location: testimonials_page.php");
            exit;
        } else {
            $error_msg = "Please provide at least a name and a quote for the testimonial.";
        }
    }

    // 5. Edit Existing Testimonial Text
    if ($_POST['action'] === 'edit_testimonial' && isset($_POST['testimonial_id'])) {
        $name      = trim($_POST['client_name'] ?? '');
        $role      = trim($_POST['client_role'] ?? '');
        $quote     = trim($_POST['quote'] ?? '');
        $rating    = (int)($_POST['rating'] ?? 5);

        if ($rating < 1) $rating = 1;
        if ($rating > 5) $rating = 5;

        if (!empty($name) && !empty($quote)) {
            $stmt = $pdo->prepare("UPDATE testimonials SET client_name = ?, client_role = ?, quote = ?, rating = ? WHERE id = ?");
            $stmt->execute([$name, $role, $quote, $rating, $_POST['testimonial_id']]);
            header("Location: testimonials_page.php");
            exit;
        } else {
            $error_msg = "Name and quote cannot be empty.";
        }
    }
}

// Fetch Pending Testimonials
$pending_stmt = $pdo->query("SELECT * FROM testimonials WHERE status = 'pending' ORDER BY created_at ASC");
$pending_testimonials = $pending_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Approved Testimonials
$approved_stmt = $pdo->query("SELECT * FROM testimonials WHERE status = 'approved' ORDER BY id DESC");
$approved_testimonials = $approved_stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Testimonials | Bright English Coaching</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css?v=15">
</head>
<body class="page-admin">

<!-- EDIT TESTIMONIAL MODAL (Hidden by default) -->
<div id="editTestimonialModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:999; align-items:center; justify-content:center; padding:24px;">
    <div style="background:#fff; padding:32px; border-radius:4px; max-width:560px; width:100%; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
        <h3 style="margin-bottom:8px; font-family:'Playfair Display', serif; font-size:1.8rem; color:#1C1A17;">Edit Testimonial</h3>
        <p style="color:#6b7280; font-size:0.85rem; margin-bottom:24px;">Fix typos, adjust names, or change the star rating before it goes live.</p>
        
        <form method="POST" action="testimonials_page.php">
            <input type="hidden" name="action" value="edit_testimonial">
            <input type="hidden" name="testimonial_id" id="edit_id">
            
            <div class="form-row-2" style="margin-bottom:16px;">
                <div class="form-group" style="margin:0;">
                    <label class="form-label">Client Name</label>
                    <input type="text" name="client_name" id="edit_name" required class="form-input">
                </div>
                <div class="form-group" style="margin:0;">
                    <label class="form-label">Professional Role</label>
                    <input type="text" name="client_role" id="edit_role" class="form-input">
                </div>
            </div>
            
            <div class="form-group" style="margin-bottom:16px;">
                <label class="form-label">Rating</label>
                <select name="rating" id="edit_rating" class="form-select">
                    <option value="5">5 Stars</option>
                    <option value="4">4 Stars</option>
                    <option value="3">3 Stars</option>
                    <option value="2">2 Stars</option>
                    <option value="1">1 Star</option>
                </select>
            </div>
            
            <div class="form-group" style="margin-bottom:16px;">
                <label class="form-label">Quote</label>
                <textarea name="quote" id="edit_quote" required rows="5" class="form-input" style="resize:vertical;"></textarea>
            </div>
            
            <div style="text-align:right; margin-top:24px; border-top:1px solid #e5e7eb; padding-top:24px;">
                <button type="button" onclick="closeEditModal()" style="padding:10px 16px; margin-right:8px; border:none; background:transparent; font-family:'Inter',sans-serif; font-size:0.8rem; font-weight:600; color:#6b7280; cursor:pointer;">Cancel</button>
                <button type="submit" class="btn-primary" style="width:auto; padding:10px 24px;">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<nav class="nav">
    <div class="nav-inner">
        <div class="nav-brand">Dashboard</div>
        
        <div class="nav-links">
            <a href="admin_dashboard.php" class="nav-link">Inbox</a>
            <a href="availability_manager.php" class="nav-link">Availability</a>
            <a href="content_page.php" class="nav-link">Site Content</a>
            <a href="testimonials_page.php" class="nav-link active">Testimonials</a>
        </div>

        <div class="nav-right">
            <span>Welcome, <?= htmlspecialchars($_SESSION['admin_username']) ?></span>
            <a href="?action=logout" class="btn-logout">Logout</a>
        </div>
    </div>
</nav>

<main class="main">
    <div style="margin-bottom: 36px;">
        <h1 style="font-family: 'Playfair Display', serif; font-size: 2.4rem; color: #1C1A17; margin-bottom: 6px;">Manage Testimonials</h1>
        <p style="color: #6b7280; font-size: 0.95rem;">Review pending submissions, add new reviews, and manage your live testimonial videos.</p>
    </div>

    <?php if ($success_msg): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <!-- PENDING TESTIMONIALS -->
    <?php if (!empty($pending_testimonials)): ?>
    <div class="card" style="margin-bottom: 48px; border: 2px solid #1C1A17;">
        <div class="card-top" style="background: #F9F8F6;">
            <h2 class="requests-heading">Pending Testimonials (Needs Review)</h2>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>Quote</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_testimonials as $test): ?>
                        <tr>
                            <td>
                                <span class="td-name"><?= htmlspecialchars($test['client_name']) ?></span><br>
                                <span class="td-time"><?= htmlspecialchars($test['client_role']) ?></span>
                            </td>
                            <td class="td-msg">"<?= htmlspecialchars($test['quote']) ?>"</td>
                            <td class="td-actions">
                                <button type="button" class="act act-blue" style="border:none; background:none; cursor:pointer; font-family: 'Inter', sans-serif; margin-right: 8px;"
                                        data-id="<?= $test['id'] ?>"
                                        data-name="<?= htmlspecialchars($test['client_name']) ?>"
                                        data-role="<?= htmlspecialchars($test['client_role']) ?>"
                                        data-rating="<?= !empty($test['rating']) ? (int)$test['rating'] : 5 ?>"
                                        data-quote="<?= htmlspecialchars($test['quote']) ?>"
                                        onclick="openEditModal(this)">Edit</button>

                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="testimonial_id" value="<?= $test['id'] ?>">
                                    <button type="submit" name="action" value="approve" onclick="return confirm('Approve this testimonial? It will become visible on the public website.');" class="badge badge-contacted" style="border:none; cursor:pointer; font-family: 'Inter', sans-serif;">Approve</button>
                                    <button type="submit" name="action" value="reject" onclick="return confirm('Reject and discard this testimonial?');" class="badge badge-voided" style="border:none; cursor:pointer; margin-left: 8px; font-family: 'Inter', sans-serif;">Reject</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- ADD TESTIMONIAL MANUALLY -->
    <div class="card" style="margin-bottom: 48px;">
        <div class="card-top">
            <h2 class="requests-heading">Add New Testimonial</h2>
            <p style="color: #6b7280; font-size: 0.85rem; margin-bottom: 16px;">Manually enter a testimonial provided by a client via email, WhatsApp, or during a session.</p>
        </div>
        <div style="padding: 24px 32px;">
            <form method="POST" action="testimonials_page.php">
                <input type="hidden" name="action" value="add_testimonial">
                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label">Client Name</label>
                        <input type="text" name="client_name" required class="form-input" placeholder="e.g. Maria G.">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Professional Role</label>
                        <input type="text" name="client_role" class="form-input" placeholder="e.g. Senior Product Manager">
                    </div>
                </div>
                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label">Rating</label>
                        <select name="rating" class="form-select">
                            <option value="5">5 Stars</option>
                            <option value="4">4 Stars</option>
                            <option value="3">3 Stars</option>
                            <option value="2">2 Stars</option>
                            <option value="1">1 Star</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Video URL (Optional)</label>
                        <input type="url" name="video_url" class="form-input" placeholder="YouTube or Vimeo link...">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Quote</label>
                    <textarea name="quote" required rows="3" class="form-input" placeholder="Enter their testimonial here..." style="resize: vertical;"></textarea>
                </div>
                <div style="text-align: right;">
                    <button type="submit" class="btn-primary" style="width: auto; padding: 12px 32px;">Publish Testimonial</button>
                </div>
            </form>
        </div>
    </div>

    <!-- LIVE TESTIMONIALS (MANAGE VIDEOS) -->
    <div class="card" style="margin-bottom: 48px;">
        <div class="card-top">
            <h2 class="requests-heading">Live Testimonials</h2>
            <p style="color: #6b7280; font-size: 0.85rem; margin-bottom: 16px;">Manage reviews currently visible on the website. You can paste a YouTube link here to embed a video above their quote.</p>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>Quote & Video Setup</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($approved_testimonials)): ?>
                        <tr class="empty-row"><td colspan="3">No live testimonials.</td></tr>
                    <?php else: ?>
                        <?php foreach ($approved_testimonials as $test): ?>
                            <tr>
                                <td>
                                    <span class="td-name"><?= htmlspecialchars($test['client_name']) ?></span><br>
                                    <span class="td-time"><?= htmlspecialchars($test['client_role']) ?></span>
                                </td>
                                <td class="td-msg">
                                    <span style="display:block; margin-bottom: 12px; color: #4b5563;">"<?= htmlspecialchars($test['quote']) ?>"</span>
                                    
                                    <form method="POST" style="display:flex; gap: 8px; align-items: center; background: #F9F8F6; padding: 12px; border: 1px solid #e5e7eb; border-radius: 4px;">
                                        <input type="hidden" name="testimonial_id" value="<?= $test['id'] ?>">
                                        <input type="hidden" name="action" value="add_video">
                                        <div style="flex: 1;">
                                            <input type="url" name="video_url" value="<?= htmlspecialchars($test['video_url'] ?? '') ?>" placeholder="Paste YouTube link here..." style="width: 100%; border: 1px solid #d1d5db; padding: 8px 12px; font-family: 'Inter', sans-serif; font-size: 0.85rem; border-radius: 2px; outline:none;">
                                        </div>
                                        <button type="submit" class="badge badge-contacted" style="border:none; cursor:pointer; padding: 9px 14px; font-family: 'Inter', sans-serif;">Save Video Link</button>
                                    </form>
                                </td>
                                <td class="td-actions">
                                    <button type="button" class="act act-blue" style="border:none; background:none; cursor:pointer; font-family: 'Inter', sans-serif; margin-right: 8px;"
                                            data-id="<?= $test['id'] ?>"
                                            data-name="<?= htmlspecialchars($test['client_name']) ?>"
                                            data-role="<?= htmlspecialchars($test['client_role']) ?>"
                                            data-rating="<?= !empty($test['rating']) ? (int)$test['rating'] : 5 ?>"
                                            data-quote="<?= htmlspecialchars($test['quote']) ?>"
                                            onclick="openEditModal(this)">Edit Text</button>

                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="testimonial_id" value="<?= $test['id'] ?>">
                                        <button type="submit" name="action" value="delete_testimonial" onclick="return confirm('Remove this testimonial from the website? This cannot be undone.');" class="act act-red" style="border:none; background:none; cursor:pointer; font-family: 'Inter', sans-serif;">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>
<script>
    // --- EDIT MODAL CONTROLS ---
    function openEditModal(btn) {
        document.getElementById('edit_id').value = btn.getAttribute('data-id');
        document.getElementById('edit_name').value = btn.getAttribute('data-name');
        document.getElementById('edit_role').value = btn.getAttribute('data-role');
        document.getElementById('edit_rating').value = btn.getAttribute('data-rating');
        document.getElementById('edit_quote').value = btn.getAttribute('data-quote');
        document.getElementById('editTestimonialModal').style.display = 'flex';
    }

    function closeEditModal() {
        document.getElementById('editTestimonialModal').style.display = 'none';
    }
</script>
</body>
</html>