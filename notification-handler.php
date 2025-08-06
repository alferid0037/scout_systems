<?php
require_once 'config.php';
require_once 'includes/NotificationSystem.php';

// Check if session is not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['professional_logged_in']) || !isset($_SESSION['professional_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Database connection
function getPDO() {
    static $pdo;
    if (!$pdo) {
        try {
            $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    return $pdo;
}

$pdo = getPDO();
$notificationSystem = new NotificationSystem($pdo);
$userId = $_SESSION['professional_id'];
$userRole = $_SESSION['professional_role'];

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'send_notification':
            $recipientId = (int)$_POST['recipient_id'];
            $title = trim($_POST['title']);
            $message = trim($_POST['message']);
            $type = $_POST['type'] ?? 'info';
            $priority = $_POST['priority'] ?? 'normal';
            $category = $_POST['category'] ?? 'general';
            
            if (empty($title) || empty($message) || !$recipientId) {
                echo json_encode(['success' => false, 'error' => 'Missing required fields']);
                exit();
            }
            
            $result = $notificationSystem->sendNotification($userId, $recipientId, $title, $message, $type, $priority, $category);
            echo json_encode(['success' => $result]);
            break;
            
        case 'broadcast_notification':
            $targetRole = $_POST['target_role'];
            $title = trim($_POST['title']);
            $message = trim($_POST['message']);
            $type = $_POST['type'] ?? 'info';
            $priority = $_POST['priority'] ?? 'normal';
            $category = $_POST['category'] ?? 'general';
            
            if (empty($title) || empty($message) || empty($targetRole)) {
                echo json_encode(['success' => false, 'error' => 'Missing required fields']);
                exit();
            }
            
            $result = $notificationSystem->broadcastToRole($userId, $targetRole, $title, $message, $type, $priority, $category);
            echo json_encode(['success' => $result]);
            break;
            
        case 'send_from_template':
            $recipientId = (int)$_POST['recipient_id'];
            $templateName = $_POST['template_name'];
            $variables = json_decode($_POST['variables'] ?? '{}', true);
            $priority = $_POST['priority'] ?? 'normal';
            
            if (!$recipientId || empty($templateName)) {
                echo json_encode(['success' => false, 'error' => 'Missing required fields']);
                exit();
            }
            
            $result = $notificationSystem->sendFromTemplate($userId, $recipientId, $templateName, $variables, $priority);
            echo json_encode(['success' => $result]);
            break;
            
        case 'mark_as_read':
            $notificationId = (int)$_POST['notification_id'];
            $result = $notificationSystem->markAsRead($notificationId, $userId);
            echo json_encode(['success' => $result]);
            break;
            
        case 'mark_all_as_read':
            $result = $notificationSystem->markAllAsRead($userId);
            echo json_encode(['success' => $result]);
            break;
            
        case 'delete_notification':
            $notificationId = (int)$_POST['notification_id'];
            $result = $notificationSystem->deleteNotification($notificationId, $userId);
            echo json_encode(['success' => $result]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get_notifications':
            $limit = (int)($_GET['limit'] ?? 50);
            $unreadOnly = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';
            $notifications = $notificationSystem->getNotifications($userId, $limit, $unreadOnly);
            echo json_encode($notifications);
            break;
            
        case 'get_sent_notifications':
            $limit = (int)($_GET['limit'] ?? 50);
            $notifications = $notificationSystem->getSentNotifications($userId, $limit);
            echo json_encode($notifications);
            break;
            
        case 'get_unread_count':
            $count = $notificationSystem->getUnreadCount($userId);
            echo json_encode(['count' => $count]);
            break;
            
        case 'get_templates':
            $templates = $notificationSystem->getTemplates($userRole);
            echo json_encode($templates);
            break;
            
        case 'get_users':
            $role = $_GET['role'] ?? null;
            $users = $notificationSystem->getUsersByRole($role);
            echo json_encode($users);
            break;
            
        default:
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
