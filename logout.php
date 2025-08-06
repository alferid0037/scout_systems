<?php
require_once 'config.php';
require_once 'includes/auth.php';

// Perform admin logout

// Log admin logout
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    try {
        $stmt = $pdo->prepare("INSERT INTO admin_logs (action, description, ip_address, user_agent) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            'admin_logout',
            'Admin user logged out',
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    } catch (PDOException $e) {
        error_log("Failed to log admin logout: " . $e->getMessage());
    }
}

// Clear all session data
$_SESSION = array();

// Destroy the session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// Redirect to login page
redirect('login.php');
exit();
?>
