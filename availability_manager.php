<?php
// availability_manager.php
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

// The currently selected date from the calendar (Defaults to today)
$selected_date = $_GET['date'] ?? date('Y-m-d');

// ==========================================
// ACTION: ADD SINGLE SLOT
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_slot') {
    $new_time = trim($_POST['slot_time'] ?? '');
    if (!empty($new_time)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO available_slots (slot_date, slot_time) VALUES (?, ?)");
            $stmt->execute([$selected_date, $new_time]);
            $success_msg = "Slot added for " . date('M j, Y', strtotime($selected_date)) . ".";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error_msg = "That time slot is already listed.";
            } else {
                $error_msg = "Error adding slot: " . $e->getMessage();
            }
        }
    } else {
        $error_msg = "Please provide a time.";
    }
}

// ==========================================
// ACTION: AUTO-FILL WEEKLY SCHEDULE
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'auto_fill') {
    $dow = $_POST['day_of_week'] ?? '';
    $months = (int)($_POST['duration_months'] ?? 0);
    $start1 = $_POST['start1'] ?? '';
    $end1 = $_POST['end1'] ?? '';
    $start2 = $_POST['start2'] ?? '';
    $end2 = $_POST['end2'] ?? '';

    if ($dow && $months > 0 && ($start1 || $start2)) {
        try {
            $start_date = new DateTime();
            $end_date = clone $start_date;
            $end_date->modify("+$months months");
            $period = new DatePeriod($start_date, new DateInterval('P1D'), $end_date);

            $times = [];
            // Helper function to generate 30-min intervals
            $generate_times = function($s, $e) use (&$times) {
                if(!$s || !$e) return;
                $current = strtotime($s);
                $endTime = strtotime($e);
                while($current < $endTime) {
                    $times[] = date('H:i:s', $current);
                    $current += 30 * 60; // 30 min intervals
                }
            };
            
            $generate_times($start1, $end1);
            $generate_times($start2, $end2);

            $check_stmt  = $pdo->prepare("SELECT COUNT(*) FROM available_slots WHERE slot_date = ? AND slot_time = ?");
            $insert_stmt = $pdo->prepare("INSERT INTO available_slots (slot_date, slot_time) VALUES (?, ?)");
            $added_count = 0;

            foreach ($period as $dt) {
                if ($dt->format('l') === $dow) {
                    $curr_date = $dt->format('Y-m-d');
                    foreach ($times as $t) {
                        $check_stmt->execute([$curr_date, $t]);
                        if ($check_stmt->fetchColumn() == 0) {
                            $insert_stmt->execute([$curr_date, $t]);
                            $added_count++;
                        }
                    }
                }
            }
            $success_msg = "Template applied! Generated $added_count slots for all {$dow}s over the next $months months.";
        } catch (Exception $e) {
            $error_msg = "Error generating template: " . $e->getMessage();
        }
    } else {
        $error_msg = "Please provide the day and at least one Morning or Afternoon time block.";
    }
}

// ==========================================
// ACTION: DELETE INDIVIDUAL SLOT
// ==========================================
if (isset($_GET['action']) && $_GET['action'] == 'delete_slot' && isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM available_slots WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        header("Location: availability_manager.php?date=" . urlencode($selected_date));
        exit;
    } catch (PDOException $e) {
        $error_msg = "Error deleting slot: " . $e->getMessage();
    }
}

// ==========================================
// ACTION: CLEAR ENTIRE DATE (VACATION)
// ==========================================
if (isset($_GET['action']) && $_GET['action'] == 'delete_date' && isset($_GET['date_to_clear'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM available_slots WHERE slot_date = ?");
        $stmt->execute([$_GET['date_to_clear']]);
        $success_msg = "Cleared all slots for " . date('M j, Y', strtotime($_GET['date_to_clear'])) . ".";
    } catch (PDOException $e) {
        $error_msg = "Error deleting date: " . $e->getMessage();
    }
}

// ==========================================
// ACTION: VACATION MODE (CLEAR DATE RANGE)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'vacation_mode') {
    $vac_start = $_POST['vac_start'] ?? '';
    $vac_end = $_POST['vac_end'] ?? '';

    if (!empty($vac_start) && !empty($vac_end)) {
        try {
            $stmt = $pdo->prepare("DELETE FROM available_slots WHERE slot_date >= ? AND slot_date <= ?");
            $stmt->execute([$vac_start, $vac_end]);
            $deleted_count = $stmt->rowCount();
            
            $success_msg = "Vacation mode activated: Successfully cleared $deleted_count slots between " . date('M j, Y', strtotime($vac_start)) . " and " . date('M j, Y', strtotime($vac_end)) . ".";
        } catch (PDOException $e) {
            $error_msg = "Error clearing vacation dates: " . $e->getMessage();
        }
    } else {
        $error_msg = "Please provide both a start and end date for your time off.";
    }
}

// ==========================================
// FETCH ALL SLOTS FOR CALENDAR
// ==========================================
try {
    $slots_stmt = $pdo->query("SELECT * FROM available_slots WHERE slot_date >= CURDATE() ORDER BY slot_date ASC, slot_time ASC");
    $available_slots = $slots_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $grouped_slots = [];
    foreach ($available_slots as $slot) {
        $grouped_slots[$slot['slot_date']][] = $slot;
    }
} catch (PDOException $e) {
    $available_slots = [];
    $grouped_slots = [];
}

// Extract just the dates that have slots for the JS calendar
$active_dates_array = array_keys($grouped_slots);
$active_dates_json = json_encode($active_dates_array);

// Slots specific to the date clicked by the admin
$selected_slots = $grouped_slots[$selected_date] ?? [];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Availability Manager | Bright English Coaching</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css?v=5">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
</head>
<body class="page-admin">

<nav class="nav">
    <div class="nav-inner">
        <div class="nav-brand">Dashboard</div>
        
        <div class="nav-links">
            <a href="admin_dashboard.php" class="nav-link">Inbox</a>
            <a href="availability_manager.php" class="nav-link active">Availability</a>
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

    <?php if ($success_msg): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <div style="margin-bottom: 24px;">
        <h1 style="font-family: 'Playfair Display', serif; font-size: 2rem; color: #1C1A17; margin-bottom: 4px;">Availability Manager</h1>
        <p style="color: #6b7280; font-size: 0.9rem;">Click a date on the calendar to manage its specific slots, or use the tools below to bulk manage your schedule.</p>
    </div>

    <div class="avail-grid">
        
        <div>
            <div class="panel" style="padding: 0; background: transparent; border: none; box-shadow: none;">
                <input type="text" id="inline-calendar" style="display: none;">
            </div>

            <div class="panel">
                <h2 class="panel-title">Auto-Fill Weekly Schedule</h2>
                <form method="POST" action="availability_manager.php?date=<?= $selected_date ?>">
                    <input type="hidden" name="action" value="auto_fill">
                    
                    <div class="form-row-2" style="margin-bottom: 16px;">
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label">Day of Week</label>
                            <select name="day_of_week" class="form-select">
                                <option value="Saturday">Saturday</option>
                                <option value="Sunday">Sunday</option>
                                <option value="Monday">Monday</option>
                                <option value="Tuesday">Tuesday</option>
                                <option value="Wednesday">Wednesday</option>
                                <option value="Thursday">Thursday</option>
                                <option value="Friday">Friday</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label">Duration</label>
                            <select name="duration_months" class="form-select">
                                <option value="1">Next 1 Month</option>
                                <option value="3" selected>Next 3 Months</option>
                                <option value="6">Next 6 Months</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row-2">
                        <div class="form-group">
                            <label class="form-label">Morning Start</label>
                            <input type="time" name="start1" class="form-input" value="08:00">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Morning End</label>
                            <input type="time" name="end1" class="form-input" value="12:00">
                        </div>
                    </div>

                    <div class="form-row-2">
                        <div class="form-group">
                            <label class="form-label">Afternoon Start</label>
                            <input type="time" name="start2" class="form-input" value="16:00">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Afternoon End</label>
                            <input type="time" name="end2" class="form-input" value="20:00">
                        </div>
                    </div>
                    
                    <p style="font-size: 0.75rem; color: #6b7280; margin-bottom: 20px;">Generates slots every 30 mins. E.g. ending at 12:00 PM creates slots up to 11:30 AM. Leave Afternoon blank if not needed.</p>
                    
                    <button type="submit" class="btn-primary btn-outline">Generate Slots</button>
                </form>
            </div>

            <div class="panel">
                <h2 class="panel-title" style="color: #991b1b; border-bottom-color: #fecaca;">Vacation Mode</h2>
                <p style="font-size: 0.85rem; color: #6b7280; margin-bottom: 16px;">Block out a range of dates. This will permanently remove all availability within the selected timeframe.</p>
                <form method="POST" action="availability_manager.php?date=<?= $selected_date ?>">
                    <input type="hidden" name="action" value="vacation_mode">
                    <div class="form-row-2" style="margin-bottom: 16px;">
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="vac_start" required min="<?= date('Y-m-d') ?>" class="form-input">
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label">End Date</label>
                            <input type="date" name="vac_end" required min="<?= date('Y-m-d') ?>" class="form-input">
                        </div>
                    </div>
                    <button type="submit" class="btn-primary btn-danger" onclick="return confirm('This will delete ALL slots between these dates. Are you completely sure?');">Block Dates</button>
                </form>
            </div>

        </div>

        <div class="panel">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px;">
                <div>
                    <h2 class="panel-title" style="border: none; padding: 0; margin-bottom: 4px;">Schedule for <?= date('l, M j, Y', strtotime($selected_date)) ?></h2>
                    <p style="color: #6b7280; font-size: 0.85rem;">Manage the individual 25-minute slots for this specific day.</p>
                </div>
                <?php if (count($selected_slots) > 0): ?>
                    <a href="?action=delete_date&date_to_clear=<?= $selected_date ?>&date=<?= $selected_date ?>" class="btn-primary btn-danger" style="width: auto; padding: 8px 16px; font-size: 0.7rem;" onclick="return confirm('Are you sure you want to clear all slots for this day?');">Clear Day</a>
                <?php endif; ?>
            </div>

            <?php if (count($selected_slots) > 0): ?>
                <div class="slots-list">
                    <?php foreach ($selected_slots as $slot): ?>
                        <div class="slot-pill">
                            <?= date('g:i A', strtotime($slot['slot_time'])) ?>
                            <a href="?action=delete_slot&id=<?= $slot['id'] ?>&date=<?= $selected_date ?>" class="slot-delete" aria-label="Delete">
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    No slots scheduled. Clients cannot book on this date.
                </div>
            <?php endif; ?>

            <div style="border-top: 1px solid #f3f4f6; padding-top: 24px; margin-top: 8px;">
                <h3 style="font-size: 0.85rem; font-weight: 700; text-transform: uppercase; color: #1C1A17; margin-bottom: 16px; letter-spacing: 0.05em;">Add Extra Slot</h3>
                <form method="POST" action="availability_manager.php?date=<?= $selected_date ?>" style="display: flex; gap: 12px;">
                    <input type="hidden" name="action" value="add_slot">
                    <input type="time" name="slot_time" required class="form-input" style="max-width: 200px;">
                    <button type="submit" class="btn-primary" style="width: auto; padding: 10px 24px;">Add Time</button>
                </form>
            </div>
        </div>

    </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    const activeDates = <?= $active_dates_json ?>;
    const selectedDate = "<?= $selected_date ?>";

    flatpickr("#inline-calendar", {
        inline: true,
        minDate: "today", // <--- THIS PREVENTS CLICKING PAST DATES
        defaultDate: selectedDate,
        onChange: function(selectedDates, dateStr) {
            window.location.href = '?date=' + dateStr;
        },
        onDayCreate: function(dObj, dStr, fp, dayElem) {
            const dateStrFormatted = fp.formatDate(dayElem.dateObj, "Y-m-d");
            if (activeDates.includes(dateStrFormatted)) {
                dayElem.innerHTML += '<span class="has-slots-dot"></span>';
            }
        }
    });
</script>
</body>
</html>