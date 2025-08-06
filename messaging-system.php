<?php
require_once 'config.php';

// Check if session is not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database helper function
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

class MessagingSystem {
    private $pdo;
    
    public function __construct() {
        $this->pdo = getPDO();
    }
    
    /**
     * Send a new message
     */
    public function sendMessage($sender_id, $recipient_id, $subject, $message) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO messages (sender_id, recipient_id, subject, message, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            
            $result = $stmt->execute([$sender_id, $recipient_id, $subject, $message]);
            
            if ($result) {
                // Create notification for recipient
                $this->createNotification($recipient_id, 'New Message', "You have a new message: " . $subject);
                return ['success' => true, 'message' => 'Message sent successfully'];
            }
            
            return ['success' => false, 'message' => 'Failed to send message'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get messages for a user (inbox)
     */
    public function getMessages($user_id, $limit = 50) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT m.*, 
                       u.username as sender_name,
                       u.role as sender_role,
                       u.organization as sender_organization
                FROM messages m 
                JOIN professional_users u ON m.sender_id = u.id 
                WHERE m.recipient_id = ? 
                ORDER BY m.created_at DESC 
                LIMIT ?
            ");
            
            $stmt->execute([$user_id, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Get sent messages for a user
     */
    public function getSentMessages($user_id, $limit = 50) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT m.*, 
                       u.username as recipient_name,
                       u.role as recipient_role,
                       u.organization as recipient_organization
                FROM messages m 
                JOIN professional_users u ON m.recipient_id = u.id 
                WHERE m.sender_id = ? 
                ORDER BY m.created_at DESC 
                LIMIT ?
            ");
            
            $stmt->execute([$user_id, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Get a specific message
     */
    public function getMessage($message_id, $user_id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT m.*, 
                       sender.username as sender_name,
                       sender.role as sender_role,
                       recipient.username as recipient_name,
                       recipient.role as recipient_role
                FROM messages m 
                JOIN professional_users sender ON m.sender_id = sender.id 
                JOIN professional_users recipient ON m.recipient_id = recipient.id 
                WHERE m.id = ? AND (m.sender_id = ? OR m.recipient_id = ?)
            ");
            
            $stmt->execute([$message_id, $user_id, $user_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            return null;
        }
    }
    
    /**
     * Mark message as read
     */
    public function markAsRead($message_id, $user_id) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE messages 
                SET is_read = 1 
                WHERE id = ? AND recipient_id = ?
            ");
            
            return $stmt->execute([$message_id, $user_id]);
            
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Get unread message count
     */
    public function getUnreadCount($user_id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count 
                FROM messages 
                WHERE recipient_id = ? AND is_read = 0
            ");
            
            $stmt->execute([$user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['count'];
            
        } catch (PDOException $e) {
            return 0;
        }
    }
    
    /**
     * Delete a message
     */
    public function deleteMessage($message_id, $user_id) {
        try {
            // Only allow deletion if user is sender or recipient
            $stmt = $this->pdo->prepare("
                DELETE FROM messages 
                WHERE id = ? AND (sender_id = ? OR recipient_id = ?)
            ");
            
            return $stmt->execute([$message_id, $user_id, $user_id]);
            
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Get all users for recipient selection
     */
    public function getAvailableRecipients($current_user_id, $role_filter = null) {
        try {
            $query = "
                SELECT id, username, role, organization 
                FROM professional_users 
                WHERE id != ? AND status = 'active'
            ";
            
            $params = [$current_user_id];
            
            if ($role_filter) {
                $query .= " AND role = ?";
                $params[] = $role_filter;
            }
            
            $query .= " ORDER BY username ASC";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Create notification (helper function)
     */
    private function createNotification($recipient_id, $title, $message) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO notifications (recipient_id, title, message, created_at) 
                VALUES (?, ?, ?, NOW())
            ");
            
            return $stmt->execute([$recipient_id, $title, $message]);
            
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Search messages
     */
    public function searchMessages($user_id, $search_term, $limit = 20) {
        try {
            $search_term = '%' . $search_term . '%';
            
            $stmt = $this->pdo->prepare("
                SELECT m.*, 
                       u.username as sender_name,
                       u.role as sender_role
                FROM messages m 
                JOIN professional_users u ON m.sender_id = u.id 
                WHERE m.recipient_id = ? 
                AND (m.subject LIKE ? OR m.message LIKE ? OR u.username LIKE ?)
                ORDER BY m.created_at DESC 
                LIMIT ?
            ");
            
            $stmt->execute([$user_id, $search_term, $search_term, $search_term, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            return [];
        }
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    // Check if user is logged in
    if (!isset($_SESSION['professional_logged_in']) || !isset($_SESSION['professional_id'])) {
        echo json_encode(['success' => false, 'message' => 'User not authenticated']);
        exit();
    }
    
    $messaging = new MessagingSystem();
    $user_id = $_SESSION['professional_id'];
    
    switch ($_POST['action']) {
        case 'send_message':
            $recipient_id = (int)$_POST['recipient_id'];
            $subject = trim($_POST['subject']);
            $message = trim($_POST['message']);
            
            if (empty($recipient_id) || empty($subject) || empty($message)) {
                echo json_encode(['success' => false, 'message' => 'All fields are required']);
                exit();
            }
            
            $result = $messaging->sendMessage($user_id, $recipient_id, $subject, $message);
            echo json_encode($result);
            break;
            
        case 'get_messages':
            $messages = $messaging->getMessages($user_id);
            echo json_encode($messages);
            break;
            
        case 'get_sent_messages':
            $messages = $messaging->getSentMessages($user_id);
            echo json_encode($messages);
            break;
            
        case 'get_message':
            $message_id = (int)$_POST['message_id'];
            $message = $messaging->getMessage($message_id, $user_id);
            
            if ($message) {
                // Mark as read if user is recipient
                if ($message['recipient_id'] == $user_id) {
                    $messaging->markAsRead($message_id, $user_id);
                }
                echo json_encode(['success' => true, 'message' => $message]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Message not found']);
            }
            break;
            
        case 'mark_message_read':
            $message_id = (int)$_POST['message_id'];
            $result = $messaging->markAsRead($message_id, $user_id);
            echo json_encode(['success' => $result]);
            break;
            
        case 'delete_message':
            $message_id = (int)$_POST['message_id'];
            $result = $messaging->deleteMessage($message_id, $user_id);
            echo json_encode(['success' => $result]);
            break;
            
        case 'get_unread_count':
            $count = $messaging->getUnreadCount($user_id);
            echo json_encode(['count' => $count]);
            break;
            
        case 'get_recipients':
            $role_filter = isset($_POST['role_filter']) ? $_POST['role_filter'] : null;
            $recipients = $messaging->getAvailableRecipients($user_id, $role_filter);
            echo json_encode($recipients);
            break;
            
        case 'search_messages':
            $search_term = trim($_POST['search_term']);
            if (empty($search_term)) {
                echo json_encode([]);
                exit();
            }
            
            $messages = $messaging->searchMessages($user_id, $search_term);
            echo json_encode($messages);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
    exit();
}

// Example usage functions
function sendQuickMessage($sender_id, $recipient_id, $subject, $message) {
    $messaging = new MessagingSystem();
    return $messaging->sendMessage($sender_id, $recipient_id, $subject, $message);
}

function getUserInbox($user_id) {
    $messaging = new MessagingSystem();
    return $messaging->getMessages($user_id);
}

function getUnreadMessageCount($user_id) {
    $messaging = new MessagingSystem();
    return $messaging->getUnreadCount($user_id);
}

// Utility function to format message date
function formatMessageDate($date) {
    $timestamp = strtotime($date);
    $now = time();
    $diff = $now - $timestamp;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . ' minutes ago';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . ' hours ago';
    } elseif ($diff < 604800) {
        return floor($diff / 86400) . ' days ago';
    } else {
        return date('M j, Y', $timestamp);
    }
}

// Utility function to truncate message preview
function getMessagePreview($message, $length = 100) {
    if (strlen($message) <= $length) {
        return $message;
    }
    return substr($message, 0, $length) . '...';
}
?>