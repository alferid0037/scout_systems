<?php
// Error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Session start
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 86400,
        'cookie_secure' => true,
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict'
    ]);
}

// Base URL
define('BASE_URL', 'http://localhost/final/');

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'auth_system');

// Email configuration (for password reset)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USER', 'abdulfetaendris564@gmail.com');
define('SMTP_PASS', 'nuaixcyvdpfsohej');
define('SMTP_PORT', 587);
define('FROM_EMAIL', 'abdulfetaendris564@gmail.com');
define('FROM_NAME', 'ethio scout');

// Create database connection
try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Set default fetch mode to associative array
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    // Log the error instead of exposing it directly in production
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed. Please try again later.");
}



// Include necessary files
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/mailer.php';

?>



