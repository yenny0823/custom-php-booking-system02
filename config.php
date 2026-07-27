<?php
// config.php
//
// Loads all secrets from environment variables (see .env.example).
// Locally: install vlucas/phpdotenv (composer require vlucas/phpdotenv) and
// this file will load a .env file automatically if present.
// On your host (e.g. Hostinger): set these as real environment variables in
// the hosting control panel instead of committing a .env file.

$envPath = __DIR__ . '/.env';
if (file_exists($envPath) && class_exists('Dotenv\Dotenv')) {
    Dotenv\Dotenv::createImmutable(__DIR__)->load();
} elseif (file_exists($envPath)) {
    // Minimal fallback .env loader if phpdotenv isn't installed.
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");
        if (!array_key_exists($key, $_ENV)) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
        }
    }
}

function env(string $key, $default = null) {
    $value = getenv($key);
    return $value !== false ? $value : $default;
}

$host     = env('DB_HOST', 'localhost');
$dbname   = env('DB_NAME');
$username = env('DB_USER');
$password = env('DB_PASS');

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die('Database Connection Failed: ' . $e->getMessage());
}

define('EMAIL_USER', env('EMAIL_USER'));
define('EMAIL_PASS', env('EMAIL_PASS'));

// Coach/business timezone. This automatically follows Boise daylight saving time.
define('COACH_TIMEZONE', env('COACH_TIMEZONE', 'America/Boise'));

// Clients must book at least this many hours before the appointment.
define('MIN_BOOKING_NOTICE_HOURS', (int) env('MIN_BOOKING_NOTICE_HOURS', 12));

date_default_timezone_set(COACH_TIMEZONE);
