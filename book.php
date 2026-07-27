<?php
// book.php

require_once 'config.php';

if (!defined('COACH_TIMEZONE')) {
    define('COACH_TIMEZONE', 'America/Boise');
}

if (!defined('MIN_BOOKING_NOTICE_HOURS')) {
    define('MIN_BOOKING_NOTICE_HOURS', 12);
}

$coach_tz = new DateTimeZone(COACH_TIMEZONE);
$utc_tz   = new DateTimeZone('UTC');

/**
 * Normalizes a database/browser time into HH:MM:SS.
 */
function normalizeBookingTime(string $time): ?string
{
    $time = trim($time);

    if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
        return $time;
    }

    if (preg_match('/^\d{2}:\d{2}$/', $time)) {
        return $time . ':00';
    }

    return null;
}

/**
 * Builds a strict Boise-local appointment datetime.
 */
function createCoachDateTime(
    string $date,
    string $time,
    DateTimeZone $coach_tz
): ?DateTimeImmutable {
    $normalized_time = normalizeBookingTime($time);

    if ($normalized_time === null) {
        return null;
    }

    $value = $date . ' ' . $normalized_time;
    $dt = DateTimeImmutable::createFromFormat(
        '!Y-m-d H:i:s',
        $value,
        $coach_tz
    );

    if (!$dt || $dt->format('Y-m-d H:i:s') !== $value) {
        return null;
    }

    return $dt;
}

// Current earliest bookable instant in Boise.
$now_coach = new DateTimeImmutable('now', $coach_tz);
$booking_cutoff = $now_coach->modify('+' . MIN_BOOKING_NOTICE_HOURS . ' hours');
$cutoff_date = $booking_cutoff->format('Y-m-d');
$cutoff_time = $booking_cutoff->format('H:i:s');

// Fetch only slots that are at least 12 hours away and not already booked.
try {
    $slots_stmt = $pdo->prepare("
        SELECT a.slot_date, a.slot_time
        FROM available_slots a
        LEFT JOIN bookings b
          ON a.slot_date = b.requested_date
         AND a.slot_time = b.requested_time
         AND b.status != 'voided'
        WHERE (
            a.slot_date > :cutoff_date
            OR (
                a.slot_date = :cutoff_date_same_day
                AND a.slot_time >= :cutoff_time
            )
        )
        AND b.id IS NULL
        ORDER BY a.slot_date ASC, a.slot_time ASC
    ");

    $slots_stmt->execute([
        'cutoff_date'          => $cutoff_date,
        'cutoff_date_same_day' => $cutoff_date,
        'cutoff_time'          => $cutoff_time,
    ]);

    $all_slots = $slots_stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Unable to load booking slots: ' . $e->getMessage());
    $all_slots = [];
}

// Group by coach-local date for the initial availability check.
$slots_by_date = [];
foreach ($all_slots as $row) {
    $slots_by_date[$row['slot_date']][] = $row['slot_time'];
}
$available_dates = array_keys($slots_by_date);

// Flat timezone-aware list for the browser.
$slots_flat = [];
foreach ($all_slots as $row) {
    $slot_time = normalizeBookingTime((string) $row['slot_time']);

    if ($slot_time === null) {
        continue;
    }

    $dt = createCoachDateTime(
        (string) $row['slot_date'],
        $slot_time,
        $coach_tz
    );

    if (!$dt) {
        continue;
    }

    $slots_flat[] = [
        'date' => $dt->format('Y-m-d'),
        'time' => $dt->format('H:i:s'),
        'utc'  => $dt->setTimezone($utc_tz)->format('c'),
    ];
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Honeypot: bots commonly fill this hidden field.
    if (!empty($_POST['bot_check_field'])) {
        header('Location: thank-you.php');
        exit;
    }

    $name           = trim((string) ($_POST['name'] ?? ''));
    $email          = trim((string) ($_POST['email'] ?? ''));
    $company        = trim((string) ($_POST['company'] ?? ''));
    $requested_date = trim((string) ($_POST['requested_date'] ?? ''));
    $requested_time = trim((string) ($_POST['requested_time'] ?? ''));
    $visitor_tz     = trim((string) ($_POST['visitor_tz'] ?? ''));

    $normalized_requested_time = normalizeBookingTime($requested_time);
    $booking_dt = null;

    if ($normalized_requested_time !== null) {
        $booking_dt = createCoachDateTime(
            $requested_date,
            $normalized_requested_time,
            $coach_tz
        );
    }

    if (strlen($name) > 100 || strlen($company) > 150) {
        $error_message = 'Input exceeds the allowed length. Please provide valid details.';
    } elseif ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please provide a valid name and email address.';
    } elseif ($booking_dt === null) {
        $error_message = 'Please select a valid appointment date and time.';
    } else {
        // Recalculate at submission time so a stale/open browser page cannot bypass the rule.
        $minimum_booking_time = (new DateTimeImmutable('now', $coach_tz))
            ->modify('+' . MIN_BOOKING_NOTICE_HOURS . ' hours');

        if ($booking_dt < $minimum_booking_time) {
            $error_message = 'Appointments must be booked at least '
                . MIN_BOOKING_NOTICE_HOURS
                . ' hours in advance.';
        } else {
            try {
                $pdo->beginTransaction();

                // Lock the selected slot so two visitors cannot book it simultaneously.
                $slot_stmt = $pdo->prepare("
                    SELECT slot_date, slot_time
                    FROM available_slots
                    WHERE slot_date = ?
                      AND slot_time = ?
                    LIMIT 1
                    FOR UPDATE
                ");
                $slot_stmt->execute([
                    $booking_dt->format('Y-m-d'),
                    $booking_dt->format('H:i:s'),
                ]);

                $slot_exists = $slot_stmt->fetch();

                if (!$slot_exists) {
                    $pdo->rollBack();
                    $error_message = 'That appointment slot is no longer available.';
                } else {
                    $booked_stmt = $pdo->prepare("
                        SELECT id
                        FROM bookings
                        WHERE requested_date = ?
                          AND requested_time = ?
                          AND status != 'voided'
                        LIMIT 1
                    ");
                    $booked_stmt->execute([
                        $booking_dt->format('Y-m-d'),
                        $booking_dt->format('H:i:s'),
                    ]);

                    if ($booked_stmt->fetch()) {
                        $pdo->rollBack();
                        $error_message = 'That appointment has just been booked. Please select another time.';
                    } else {
                        $insert_stmt = $pdo->prepare("
                            INSERT INTO bookings
                                (name, email, message, requested_date, requested_time, status)
                            VALUES
                                (?, ?, ?, ?, ?, 'pending')
                        ");

                        $insert_stmt->execute([
                            $name,
                            $email,
                            $company,
                            $booking_dt->format('Y-m-d'),
                            $booking_dt->format('H:i:s'),
                        ]);

                        $booking_id = (int) $pdo->lastInsertId();
                        $pdo->commit();

                        // Boise-local ISO 8601 timestamp with its correct MST/MDT offset.
                        $requested_datetime_iso = $booking_dt->format('c');

                        $booking_data = [
                            'booking_id'            => $booking_id,
                            'client_name'           => $name,
                            'client_email'          => $email,
                            'client_company'        => $company,
                            'requested_date'        => $booking_dt->format('Y-m-d'),
                            'requested_time'        => $booking_dt->format('H:i:s'),
                            'requested_datetime_iso'=> $requested_datetime_iso,
                            'visitor_tz'            => $visitor_tz !== '' ? $visitor_tz : null,
                        ];

                        // Make.com webhook.
                        $webhook_url = 'https://hook.us2.make.com/ssvbxoco3jz5x13sd8aahpejacda9l6r';

                        $ch = curl_init($webhook_url);
                        curl_setopt_array($ch, [
                            CURLOPT_POST           => true,
                            CURLOPT_POSTFIELDS     => json_encode($booking_data),
                            CURLOPT_HTTPHEADER     => [
                                'Content-Type: application/json',
                                'Accept: application/json',
                            ],
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_CONNECTTIMEOUT => 10,
                            CURLOPT_TIMEOUT        => 20,
                        ]);

                        $response = curl_exec($ch);

                        if ($response === false) {
                            error_log('Booking webhook failed: ' . curl_error($ch));
                        }

                        curl_close($ch);

                        header('Location: thank-you.php');
                        exit;
                    }
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log('Booking submission failed: ' . $e->getMessage());
                $error_message = 'Something went wrong. Please try again later.';
            }
        }
    }
}

$slots_json = json_encode(
    $slots_flat,
    JSON_UNESCAPED_SLASHES
    | JSON_HEX_TAG
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT
);
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
    <title>Book Diagnostic | Bright English Coaching</title>
    <link rel="icon" type="image/png" href="assets/logo.png">
    <meta name="description" content="Specialized English pronunciation coaching for Spanish-speaking professionals. Communicate complex ideas clearly and lead with absolute confidence.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css?v=3">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/dark.css">
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

    <?php if ($error_message): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="page-header">
        <div>
            <h1>Professional Communication Consultation</h1>
            <p>Select Date &amp; Time (times shown in your local timezone — <span id="visitor-tz"></span>)</p>
            <p>Appointments must be booked at least <?= (int) MIN_BOOKING_NOTICE_HOURS ?> hours in advance.</p>
        </div>
        <div class="live-badge">Live Availability</div>
    </div>

    <div id="step-1-scheduler">
        <div>
            <p class="step-label">1. Select a Date</p>

            <?php if (count($available_dates) > 0): ?>
                <div class="field" style="max-width: 300px; margin-bottom: 36px;">
                    <input
                        type="text"
                        id="date-picker"
                        placeholder="Click to choose a date..."
                        readonly
                        style="cursor: pointer; background: #fff;"
                    >
                </div>
            <?php else: ?>
                <p class="no-slots">There are currently no available dates. Please check back later.</p>
            <?php endif; ?>
        </div>

        <div id="time-section" class="time-section">
            <p class="step-label">2. Choose a Time on <span id="time-header-date"></span></p>
            <div id="time-grid" class="time-grid"></div>
        </div>

        <div id="continue-row" class="continue-row">
            <button type="button" class="btn-dark" onclick="goToForm()">Continue</button>
        </div>
    </div>

    <form id="step-2-form" action="book.php" method="POST">
        <div style="display: none; position: absolute; left: -9999px;" aria-hidden="true">
            <label for="bot_check_field">Leave this field empty if you are a human</label>
            <input
                type="text"
                name="bot_check_field"
                id="bot_check_field"
                tabindex="-1"
                autocomplete="off"
            >
        </div>

        <div class="summary-bar">
            <div>
                <div class="sb-label">Your Selection</div>
                <div class="sb-value" id="summary-selection"></div>
            </div>
            <button type="button" class="btn-change" onclick="goBackToScheduler()">Change</button>
        </div>

        <input type="hidden" id="hidden_requested_date" name="requested_date">
        <input type="hidden" id="hidden_requested_time" name="requested_time">
        <input type="hidden" id="hidden_visitor_tz" name="visitor_tz">

        <div class="form-section">
            <div class="field">
                <label>Your Full Name</label>
                <input type="text" name="name" required placeholder="">
            </div>

            <div class="form-row">
                <div class="field">
                    <label>Your Email</label>
                    <input type="email" name="email" required placeholder="">
                </div>

                <div class="field">
                    <label>Company / Profession</label>
                    <input type="text" name="company" required placeholder="e.g. Senior Staff Scientist">
                </div>
            </div>
        </div>

        <div class="form-footer">
            <button type="submit" class="btn-dark">Confirm Booking</button>
        </div>
    </form>

</main>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script>
    // ==========================================
    // Scheduler logic: timezone-aware
    // ==========================================
    const slotsFlat = <?= $slots_json ?: '[]' ?>;
    const minimumNoticeHours = <?= (int) MIN_BOOKING_NOTICE_HOURS ?>;
    const minimumNoticeMs = minimumNoticeHours * 60 * 60 * 1000;

    const visitorTz = Intl.DateTimeFormat().resolvedOptions().timeZone || 'Local timezone';
    const visitorTzEl = document.getElementById('visitor-tz');

    if (visitorTzEl) {
        visitorTzEl.textContent = visitorTz;
    }

    const slotsByDate = {};

    // Filter again in the browser in case the page has been cached or left open.
    slotsFlat.forEach(slot => {
        const localDt = new Date(slot.utc);
        const earliestAllowed = new Date(Date.now() + minimumNoticeMs);

        if (Number.isNaN(localDt.getTime()) || localDt < earliestAllowed) {
            return;
        }

        const localKey = localDt.getFullYear() + '-' +
            String(localDt.getMonth() + 1).padStart(2, '0') + '-' +
            String(localDt.getDate()).padStart(2, '0');

        if (!slotsByDate[localKey]) {
            slotsByDate[localKey] = [];
        }

        slotsByDate[localKey].push({
            localDt: localDt,
            dbDate: slot.date,
            dbTime: slot.time,
            utc: slot.utc
        });
    });

    Object.keys(slotsByDate).forEach(key => {
        slotsByDate[key].sort((a, b) => a.localDt - b.localDt);
    });

    const availableDatesArray = Object.keys(slotsByDate);

    let selectedDateDb = '';
    let selectedDateDisplay = '';
    let selectedTimeDb = '';
    let selectedTimeDisplay = '';
    let selectedUtc = '';

    function formatTime(dateObj) {
        return dateObj.toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit'
        });
    }

    function selectionStillValid() {
        if (!selectedUtc) {
            return false;
        }

        const selectedInstant = new Date(selectedUtc);
        const earliestAllowed = new Date(Date.now() + minimumNoticeMs);

        return !Number.isNaN(selectedInstant.getTime())
            && selectedInstant >= earliestAllowed;
    }

    if (document.getElementById('date-picker')) {
        flatpickr('#date-picker', {
            enable: availableDatesArray,
            dateFormat: 'Y-m-d',
            minDate: 'today',
            disableMobile: true,
            appendTo: document.documentElement,
            onChange: function(selectedDates, dateStr) {
                if (selectedDates.length > 0) {
                    const display = selectedDates[0].toLocaleDateString('en-US', {
                        month: 'long',
                        day: 'numeric'
                    });

                    selectDate(dateStr, display);
                }
            }
        });
    }

    function selectDate(localDateKey, displayDate) {
        selectedDateDisplay = displayDate;
        selectedDateDb = '';
        selectedTimeDb = '';
        selectedTimeDisplay = '';
        selectedUtc = '';

        const times = slotsByDate[localDateKey] || [];
        const grid = document.getElementById('time-grid');
        grid.innerHTML = '';

        const stillValidTimes = times.filter(slot => {
            return new Date(slot.utc) >= new Date(Date.now() + minimumNoticeMs);
        });

        if (stillValidTimes.length === 0) {
            grid.innerHTML = '<p class="no-slots" style="grid-column:1/-1">No time slots available for this date.</p>';
        } else {
            stillValidTimes.forEach(slot => {
                const btn = document.createElement('button');
                const displayTime = formatTime(slot.localDt);

                btn.type = 'button';
                btn.className = 'time-card';
                btn.textContent = displayTime;
                btn.onclick = () => selectTime(
                    btn,
                    slot.dbTime,
                    displayTime,
                    slot.dbDate,
                    slot.utc
                );

                grid.appendChild(btn);
            });
        }

        document.getElementById('time-section').classList.add('visible');
        document.getElementById('time-header-date').textContent = displayDate.toUpperCase();
        document.getElementById('continue-row').classList.remove('visible');
    }

    function selectTime(el, dbTime, displayTime, dbDate, utc) {
        selectedTimeDb = dbTime;
        selectedTimeDisplay = displayTime;
        selectedDateDb = dbDate;
        selectedUtc = utc;

        document.querySelectorAll('.time-card').forEach(card => {
            card.classList.remove('selected');
        });

        el.classList.add('selected');
        document.getElementById('continue-row').classList.add('visible');
    }

    function goToForm() {
        if (!selectionStillValid()) {
            alert(`Appointments must be booked at least ${minimumNoticeHours} hours in advance. Please select another time.`);
            window.location.reload();
            return;
        }

        document.getElementById('step-1-scheduler').style.display = 'none';
        document.getElementById('step-2-form').style.display = 'block';

        document.getElementById('summary-selection').textContent =
            `${selectedDateDisplay} at ${selectedTimeDisplay} (${visitorTz})`;

        document.getElementById('hidden_requested_date').value = selectedDateDb;
        document.getElementById('hidden_requested_time').value = selectedTimeDb;
        document.getElementById('hidden_visitor_tz').value = visitorTz;

        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function goBackToScheduler() {
        document.getElementById('step-2-form').style.display = 'none';
        document.getElementById('step-1-scheduler').style.display = 'block';
    }

    const bookingForm = document.getElementById('step-2-form');

    if (bookingForm) {
        bookingForm.addEventListener('submit', function(event) {
            if (!selectionStillValid()) {
                event.preventDefault();
                alert(`Appointments must be booked at least ${minimumNoticeHours} hours in advance. Please select another time.`);
                window.location.reload();
            }
        });
    }
</script>
</body>
</html>