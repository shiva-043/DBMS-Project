<?php
/**
 * RailReserve - Database Configuration and Core Utilities
 */

// Start session if not active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Credentials
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'railway_reservation');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Establish PDO connection
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // Graceful error display without exposing DB credentials
    die("<div style='font-family:sans-serif;padding:30px;text-align:center;'>
            <h2 style='color:#C43D42;'>Database Connection Error</h2>
            <p>Could not connect to MySQL database <strong>" . htmlspecialchars(DB_NAME) . "</strong>.</p>
            <p style='color:#64748B;'>Please verify that XAMPP MySQL is running and the database has been imported via <code>schema.sql</code>.</p>
         </div>");
}

// Compute Base Web Path dynamically so links work in any folder name
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$basePath = preg_replace('#/(user|admin|config|includes)$#', '', $scriptDir);
$basePath = rtrim($basePath, '/');
define('BASE_PATH', $basePath);

// ----------------------------------------------------
// Authentication & Authorization Helpers
// ----------------------------------------------------

function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function current_user() {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'        => $_SESSION['user_id'],
        'full_name' => $_SESSION['full_name'] ?? 'User',
        'email'     => $_SESSION['email'] ?? '',
        'role'      => $_SESSION['role'] ?? 'USER',
    ];
}

function require_user() {
    if (!is_logged_in()) {
        header("Location: " . BASE_PATH . "/login.php");
        exit;
    }
    if ($_SESSION['role'] === 'ADMIN') {
        header("Location: " . BASE_PATH . "/admin/dashboard.php");
        exit;
    }
}

function require_admin() {
    if (!is_logged_in()) {
        header("Location: " . BASE_PATH . "/login.php");
        exit;
    }
    if ($_SESSION['role'] !== 'ADMIN') {
        header("Location: " . BASE_PATH . "/user/search-trains.php");
        exit;
    }
}

// ----------------------------------------------------
// CSRF Protection Helpers
// ----------------------------------------------------

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

function verify_csrf() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
            die("<div style='font-family:sans-serif;padding:30px;text-align:center;color:#C43D42;'>
                    <h3>Invalid or Expired CSRF Token</h3>
                    <p>Please refresh the page and try again.</p>
                 </div>");
        }
    }
}

// ----------------------------------------------------
// Application Logic Helpers
// ----------------------------------------------------

/**
 * Format 24-hr time to readable 12-hr format
 */
function format_time($time) {
    return date('h:i A', strtotime("1970-01-01 $time"));
}

/**
 * Calculate journey duration in hours and minutes, handling past-midnight arrivals
 */
function calculate_duration($depTime, $arrTime) {
    $dep = strtotime("1970-01-01 $depTime");
    $arr = strtotime("1970-01-01 $arrTime");
    if ($arr < $dep) {
        $arr += 86400; // Passed midnight
    }
    $diffSec = $arr - $dep;
    $hours = floor($diffSec / 3600);
    $mins = floor(($diffSec % 3600) / 60);
    return "{$hours}h {$mins}m";
}

/**
 * Calculate available seats for a train on a journey date:
 * Available Seats = Train Total Seats - Sum of Passengers in Confirmed Bookings
 */
function get_available_seats($pdo, $train_id, $journey_date) {
    $stmt = $pdo->prepare("
        SELECT t.total_seats - COALESCE(COUNT(p.passenger_id), 0) AS available_seats
        FROM trains t
        LEFT JOIN bookings b 
            ON t.train_id = b.train_id 
            AND b.journey_date = :journey_date 
            AND b.booking_status = 'CONFIRMED'
        LEFT JOIN passengers p 
            ON b.booking_id = p.booking_id
        WHERE t.train_id = :train_id
        GROUP BY t.train_id, t.total_seats
    ");
    $stmt->execute([
        'train_id'     => $train_id,
        'journey_date' => $journey_date,
    ]);
    $res = $stmt->fetch();
    return $res ? (int)$res['available_seats'] : 0;
}

/**
 * Generate a unique 10-character PNR (e.g. RR + 8 random digits)
 */
function generate_pnr($pdo) {
    do {
        $pnr = 'RR' . str_pad((string)random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT);
        $chk = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE pnr = :pnr");
        $chk->execute(['pnr' => $pnr]);
    } while ((int)$chk->fetchColumn() > 0);
    
    return $pnr;
}
