<?php
// admin_dashboard.php
session_start();
require_once 'config.php';

if (!defined('COACH_TIMEZONE')) {
    define('COACH_TIMEZONE', 'America/Boise');
}
if (date_default_timezone_get() !== COACH_TIMEZONE) {
    date_default_timezone_set(COACH_TIMEZONE);
}

function formatCoachBookingDateTime($date, $time) {
    if (empty($date)) {
        return [
            'date' => null,
            'time' => null
        ];
    }

    $coachTz = new DateTimeZone(COACH_TIMEZONE);

    $dateTimeString = $date . ' ' . (!empty($time) ? $time : '00:00:00');

    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $dateTimeString, $coachTz);

    if (!$dt) {
        return [
            'date' => date('M j, Y', strtotime($date)),
            'time' => !empty($time) ? date('g:i A', strtotime($time)) : null
        ];
    }

    return [
        'date' => $dt->format('M j, Y'),
        'time' => !empty($time) ? $dt->format('g:i A T') : null
    ];
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require 'PHPMailer/PHPMailer/src/Exception.php';
require 'PHPMailer/PHPMailer/src/PHPMailer.php';
require 'PHPMailer/PHPMailer/src/SMTP.php'; 

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

// Handle Booking Status Updates & Emails
if (isset($_GET['action']) && $_GET['action'] == 'update_status' && isset($_GET['id']) && isset($_GET['status'])) {
    $allowed_statuses = ['pending', 'contacted', 'completed', 'voided'];
    if (in_array($_GET['status'], $allowed_statuses)) {
        try {
            $client_stmt = $pdo->prepare("SELECT name, email FROM bookings WHERE id = ?");
            $client_stmt->execute([$_GET['id']]);
            $client = $client_stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?");
            $stmt->execute([$_GET['status'], $_GET['id']]);

            if ($_GET['status'] === 'completed' && $client) {
                $client_name = $client['name'];
                $client_email = $client['email'];

                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();                                            
                    $mail->Host       = 'smtp.gmail.com';                     
                    $mail->SMTPAuth   = true;                                   
                    $mail->Username   = EMAIL_USER;     
                    $mail->Password   = EMAIL_PASS; 
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;            
                    $mail->Port       = 465;                                    

                    $mail->setFrom(EMAIL_USER, 'Bright English Coaching');
                    $mail->addAddress($client_email, $client_name);
                    $mail->isHTML(true);
                    
                    $mail->Subject = 'Next Steps | Bright English Coaching';
                    
                    $mail->Body = '
                    <div style="font-family: \'Inter\', Helvetica, Arial, sans-serif; color: #1c1a17; line-height: 1.65; max-width: 600px; margin: 0 auto; padding: 32px; border: 1px solid #e5e7eb; border-radius: 4px; background-color: #ffffff;">
                        <h2 style="color: #1C1A17; font-family: \'Playfair Display\', Georgia, serif; font-weight: 600; font-size: 24px;">Great speaking with you, ' . htmlspecialchars($client_name) . '.</h2>
                        
                        <p>Thank you again for taking the time to meet with me today.</p>
                        <p>Based on our conversation, we identified several pronunciation and speech patterns that are affecting your communication.</p>
                        <p>The good news is that these aren\'t random mistakes. They\'re specific speech habits that can be corrected through focused practice and personalized coaching.</p>
                        <p>Over the next eight weeks we\'ll systematically work on those habits until speaking more clearly becomes natural and automatic.</p>
                        <p><strong>This isn\'t about learning more English. It\'s about making sure the English you already know is communicated with clarity and confidence.</strong></p>
                        
                        <div style="background-color: #f9f8f6; padding: 24px; margin: 28px 0; border-left: 4px solid #1c1a17;">
                            <p style="margin-top: 0; font-weight: 600; font-size: 16px;">The 8-Week Coaching Program Includes:</p>
                            <ul style="margin-bottom: 0; padding-left: 20px; color: #4b5563;">
                                <li style="margin-bottom: 8px;">8 private 1-on-1 coaching sessions</li>
                                <li style="margin-bottom: 8px;">Daily personalized voice coaching via WhatsApp</li>
                                <li style="margin-bottom: 8px;">Customized practice exercises based on your specific speech patterns</li>
                                <li>Connected speech and native listening training</li>
                            </ul>
                        </div>
                        
                        <p>Whenever you are ready to get started and secure your spot, simply reply to this email.</p>
                        <p>I look forward to helping you bridge the gap between your brilliant ideas and how you are heard.</p>
                        <p style="margin-top: 36px; margin-bottom: 0;">Best,<br><strong>Jordan</strong><br><span style="color: #6b7280; font-size: 14px;">Bright English Coaching</span></p>
                    </div>';

                    $mail->AltBody = "Hi " . $client_name . ",\n\nGreat speaking with you today...\n\n(View email in an HTML client for full details).";
                    $mail->send();
                } catch (Exception $e) {
                    error_log("Follow-up email could not be sent. Mailer Error: {$mail->ErrorInfo}");
                }
            }

            header("Location: admin_dashboard.php?view=" . ($_GET['view'] ?? 'active'));
            exit;
        } catch (PDOException $e) {
            $error_msg = "Error updating status: " . $e->getMessage();
        }
    }
}

// Purge Discarded Booking
if (isset($_GET['action']) && $_GET['action'] == 'purge_request' && isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM bookings WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        header("Location: admin_dashboard.php?view=" . ($_GET['view'] ?? 'voided'));
        exit;
    } catch (PDOException $e) {
        $error_msg = "Error purging record: " . $e->getMessage();
    }
}

// Purge ALL Discarded Bookings
if (isset($_GET['action']) && $_GET['action'] == 'purge_all_voided') {
    try {
        $stmt = $pdo->prepare("DELETE FROM bookings WHERE status = 'voided'");
        $stmt->execute();
        header("Location: admin_dashboard.php?view=voided");
        exit;
    } catch (PDOException $e) {
        $error_msg = "Error purging records: " . $e->getMessage();
    }
}

$current_view = $_GET['view'] ?? 'active';

// Fetch Bookings
try {
    if ($current_view === 'completed') {
        $stmt = $pdo->query("SELECT * FROM bookings WHERE status = 'completed' ORDER BY id DESC");
    } elseif ($current_view === 'voided') {
        $stmt = $pdo->query("SELECT * FROM bookings WHERE status = 'voided' ORDER BY id DESC");
    } else {
        $stmt = $pdo->query("SELECT * FROM bookings WHERE status IN ('pending', 'contacted') ORDER BY FIELD(status, 'pending', 'contacted'), id DESC");
    }
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $bookings = [];
    $error_msg = "Could not retrieve bookings: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inbox Dashboard | Bright English Coaching</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css?v=13">
</head>
<body class="page-admin">

<nav class="nav">
    <div class="nav-inner">
        <div class="nav-brand">Dashboard</div>
        
        <div class="nav-links">
            <a href="admin_dashboard.php" class="nav-link active">Inbox</a>
            <a href="availability_manager.php" class="nav-link">Availability</a>
            <a href="content_page.php" class="nav-link">Site Content</a>
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
        <h1 style="font-family: 'Playfair Display', serif; font-size: 2.4rem; color: #1C1A17; margin-bottom: 6px;">Hello Jordan!</h1>
        <p style="color: #6b7280; font-size: 0.95rem;" id="live-greeting">Loading local time...</p>
    </div>

    <?php if ($success_msg): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <!-- CONSULTATION REQUESTS -->
    <div class="card">
        <div class="card-top" style="display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 16px;">
            <div>
                <h1 class="requests-heading">Consultation Requests</h1>
                <div class="tabs">
                    <a href="?view=active"    class="tab <?= $current_view === 'active'    ? 'active' : '' ?>">Active Inbox</a>
                    <a href="?view=completed" class="tab <?= $current_view === 'completed' ? 'active' : '' ?>">Completed</a>
                    <a href="?view=voided"    class="tab <?= $current_view === 'voided'    ? 'active' : '' ?>">Discarded</a>
                </div>
            </div>
            
            <?php if ($current_view === 'voided' && count($bookings) > 0): ?>
                <div style="padding-bottom: 16px;">
                    <a href="?action=purge_all_voided" onclick="return confirm('Permanently delete ALL discarded requests? This cannot be undone.');" style="background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; padding: 8px 16px; font-size: 0.7rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; text-decoration: none; border-radius: 2px; transition: background 0.2s;" onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='#fef2f2'">Empty Discarded Folder</a>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="table-controls">
            <div class="control-group">
                <label>Show</label>
                <select id="pageSize" onchange="changePageSize()">
                    <option value="10">10</option>
                    <option value="20" selected>20</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                <label>entries</label>
            </div>
            
            <div class="control-group">
                <label>Sort</label>
                <select id="sortOrder" onchange="applyFilters()">
                    <option value="newest">Newest First</option>
                    <option value="oldest">Oldest First</option>
                </select>
            </div>

            <div class="control-group">
                <input type="text" id="searchInput" onkeyup="applyFilters()" placeholder="Search names, emails, or dates...">
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Submitted</th> 
                        <th>Name & Email</th>
                        <th>Requested Date & Time</th>
                        <th>Message</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody id="booking-tbody">
                    <?php if (count($bookings) > 0): ?>
                        <?php foreach ($bookings as $booking):
                            $status = $booking['status'] ?? 'pending';
                            $badge_class = match($status) {
                                'pending'   => 'badge-pending',
                                'contacted' => 'badge-contacted',
                                'completed' => 'badge-completed',
                                'voided'    => 'badge-voided',
                                default     => 'badge-completed'
                            };
                            
                            $submitted_time = !empty($booking['created_at']) ? strtotime($booking['created_at']) : time();
                        ?>
                        <tr data-timestamp="<?= $submitted_time ?>">
                            <td><span class="badge <?= $badge_class ?>"><?= htmlspecialchars($status) ?></span></td>
                            
                            <td>
                                <div class="td-date"><?= date('M j, Y', $submitted_time) ?></div>
                                <div class="td-time"><?= date('g:i A', $submitted_time) ?></div>
                            </td>

                            <td>
                                <div class="td-name"><?= htmlspecialchars($booking['name']) ?></div>
                                <a href="mailto:<?= htmlspecialchars($booking['email']) ?>" class="td-email">
                                    <?= htmlspecialchars($booking['email']) ?>
                                </a>
                            </td>

                            <td>
                                <?php if (!empty($booking['requested_date'])): ?>
                                <?php
                                    $coachBookingTime = formatCoachBookingDateTime(
                                        $booking['requested_date'],
                                        $booking['requested_time'] ?? null
                                    );
                                ?>
                        
                                <div class="td-date"><?= htmlspecialchars($coachBookingTime['date']) ?></div>
                        
                                <?php if (!empty($coachBookingTime['time'])): ?>
                                    <div class="td-time"><?= htmlspecialchars($coachBookingTime['time']) ?></div>
                                <?php endif; ?>
                                <?php else: ?>
                                <span style="color:#9ca3af;">Not Specified</span>
                                <?php endif; ?>
                            </td>
                            <td class="td-msg"><?= nl2br(htmlspecialchars($booking['message'])) ?></td>

                            <td class="td-actions">
                                <div class="actions-col">
                                    <?php if ($status === 'pending'): ?>
                                        <a href="?action=update_status&id=<?= $booking['id'] ?>&status=contacted&view=<?= $current_view ?>" onclick="return confirm('Mark this request as contacted?');" class="act act-blue">Mark Contacted</a>
                                    <?php endif; ?>
                                    <?php if ($status === 'contacted'): ?>
                                        <a href="?action=update_status&id=<?= $booking['id'] ?>&status=pending&view=<?= $current_view ?>" onclick="return confirm('Move this request back to pending?');" class="act act-gray">Undo (Back to Pending)</a>
                                    <?php endif; ?>
                                    <?php if ($status === 'pending' || $status === 'contacted'): ?>
                                        <a href="?action=update_status&id=<?= $booking['id'] ?>&status=completed&view=<?= $current_view ?>" onclick="return confirm('Mark this request as completed? An automated follow-up email will be sent to the client right now.');" class="act act-gray">Mark Completed</a>
                                    <?php endif; ?>
                                    <?php if ($status === 'completed'): ?>
                                        <a href="?action=update_status&id=<?= $booking['id'] ?>&status=pending&view=<?= $current_view ?>" onclick="return confirm('Reopen this request to your inbox?');" class="act act-gray">Reopen to Inbox</a>
                                    <?php endif; ?>
                                    <?php if ($status !== 'voided'): ?>
                                        <a href="?action=update_status&id=<?= $booking['id'] ?>&status=voided&view=<?= $current_view ?>" onclick="return confirm('Are you sure you want to void this request?');" class="act act-red">Void</a>
                                    <?php endif; ?>
                                    <?php if ($status === 'voided'): ?>
                                        <a href="?action=update_status&id=<?= $booking['id'] ?>&status=pending&view=<?= $current_view ?>" onclick="return confirm('Restore this request to your inbox?');" class="act act-dark">Restore</a>
                                        <a href="?action=purge_request&id=<?= $booking['id'] ?>&view=<?= $current_view ?>"
                                           onclick="return confirm('Permanently purge this record? This cannot be reversed.');"
                                           class="act act-redbold">
                                            <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                            Purge
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr class="empty-row">
                            <td colspan="6"> <?php
                                    if ($current_view === 'completed') echo 'There are no completed requests.';
                                    elseif ($current_view === 'voided') echo 'The discarded ledger is empty.';
                                    else echo 'No active consultation requests found.';
                                ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="pagination" id="paginationControls"></div>
    </div>

</main>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        
        // --- LIVE TIME GREETING ---
        function updateTime() {
            const timeDisplay = document.getElementById('live-greeting');
            if (timeDisplay) {
                const now = new Date();
                const options = { 
                    weekday: 'long', 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric', 
                    hour: 'numeric', 
                    minute: '2-digit' 
                };
            timeDisplay.innerText = "It's " + now.toLocaleDateString('en-US', options);
            }
        }
        updateTime(); 
        setInterval(updateTime, 60000); 
        // --------------------------

        // --- SCROLL POSITION MEMORY FIX ---
        if (sessionStorage.getItem('scrollPosition')) {
            window.scrollTo(0, sessionStorage.getItem('scrollPosition'));
            sessionStorage.removeItem('scrollPosition'); 
        }
        
        const actionLinks = document.querySelectorAll('.td-actions a, .td-actions button');
        actionLinks.forEach(link => {
            link.addEventListener('click', () => {
                sessionStorage.setItem('scrollPosition', window.scrollY);
            });
        });
        // ----------------------------------

        const tableBody = document.getElementById('booking-tbody');
        if (!tableBody) return;

        const allRows = Array.from(tableBody.querySelectorAll('tr:not(.empty-row)'));
        if (allRows.length === 0) {
            document.getElementById('paginationControls').style.display = 'none';
            return;
        }

        let filteredRows = [...allRows];
        let currentPage = 1;
        let pageSize = parseInt(document.getElementById('pageSize').value);

        window.applyFilters = function() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const sortOrder = document.getElementById('sortOrder').value;
            
            filteredRows = allRows.filter(row => {
                return row.innerText.toLowerCase().includes(searchTerm);
            });
            
            filteredRows.sort((a, b) => {
                const timeA = parseInt(a.getAttribute('data-timestamp')) || 0;
                const timeB = parseInt(b.getAttribute('data-timestamp')) || 0;
                return sortOrder === 'newest' ? timeB - timeA : timeA - timeB;
            });
            
            filteredRows.forEach(row => tableBody.appendChild(row));
            
            currentPage = 1;
            renderTable();
        };

        window.renderTable = function() {
            allRows.forEach(row => row.style.display = 'none');

            const start = (currentPage - 1) * pageSize;
            const end = start + pageSize;
            const rowsToShow = filteredRows.slice(start, end);

            rowsToShow.forEach(row => row.style.display = '');
            renderPagination();
        };

        window.renderPagination = function() {
            const totalPages = Math.ceil(filteredRows.length / pageSize) || 1;
            const paginationContainer = document.getElementById('paginationControls');
            paginationContainer.innerHTML = '';

            const infoText = document.createElement('div');
            infoText.className = 'pagination-info';
            const startItem = filteredRows.length === 0 ? 0 : ((currentPage - 1) * pageSize) + 1;
            const endItem = Math.min(currentPage * pageSize, filteredRows.length);
            infoText.innerText = `Showing ${startItem} to ${endItem} of ${filteredRows.length} entries`;
            paginationContainer.appendChild(infoText);

            const prevBtn = document.createElement('button');
            prevBtn.className = 'page-btn';
            prevBtn.innerText = 'Prev';
            prevBtn.disabled = currentPage === 1;
            prevBtn.onclick = () => { currentPage--; renderTable(); };
            paginationContainer.appendChild(prevBtn);

            for(let i = 1; i <= totalPages; i++) {
                const pageBtn = document.createElement('button');
                pageBtn.className = `page-btn ${i === currentPage ? 'active' : ''}`;
                pageBtn.innerText = i;
                pageBtn.onclick = () => { currentPage = i; renderTable(); };
                paginationContainer.appendChild(pageBtn);
            }

            const nextBtn = document.createElement('button');
            nextBtn.className = 'page-btn';
            nextBtn.innerText = 'Next';
            nextBtn.disabled = currentPage === totalPages;
            nextBtn.onclick = () => { currentPage++; renderTable(); };
            paginationContainer.appendChild(nextBtn);
        };

        window.changePageSize = function() {
            pageSize = parseInt(document.getElementById('pageSize').value);
            currentPage = 1;
            renderTable();
        };

        applyFilters(); 
    });
</script>
</body>
</html>