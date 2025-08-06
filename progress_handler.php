<?php
require_once 'config.php';

// Function to update course progress
function updateCourseProgress($user_id, $course_id, $lesson_number) {
    global $pdo;
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Mark lesson as completed
        $stmt = $pdo->prepare("
            INSERT INTO lesson_progress (user_id, course_id, lesson_number, completed, completed_at) 
            VALUES (?, ?, ?, TRUE, NOW()) 
            ON DUPLICATE KEY UPDATE completed = TRUE, completed_at = NOW()
        ");
        $stmt->execute([$user_id, $course_id, $lesson_number]);
        
        // Get total lessons for the course
        $stmt = $pdo->prepare("SELECT total_lessons FROM courses WHERE id = ?");
        $stmt->execute([$course_id]);
        $total_lessons = $stmt->fetchColumn();
        
        // Count completed lessons
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM lesson_progress 
            WHERE user_id = ? AND course_id = ? AND completed = TRUE
        ");
        $stmt->execute([$user_id, $course_id]);
        $completed_lessons = $stmt->fetchColumn();
        
        // Calculate progress percentage
        $progress_percentage = ($completed_lessons / $total_lessons) * 100;
        
        // Determine status
        $status = 'not_started';
        if ($completed_lessons > 0 && $completed_lessons < $total_lessons) {
            $status = 'in_progress';
        } elseif ($completed_lessons >= $total_lessons) {
            $status = 'completed';
        }
        
        // Update course progress
        $stmt = $pdo->prepare("
            INSERT INTO course_progress (user_id, course_id, lessons_completed, progress_percentage, status, last_accessed) 
            VALUES (?, ?, ?, ?, ?, NOW()) 
            ON DUPLICATE KEY UPDATE 
                lessons_completed = VALUES(lessons_completed),
                progress_percentage = VALUES(progress_percentage),
                status = VALUES(status),
                last_accessed = NOW()
        ");
        $stmt->execute([$user_id, $course_id, $completed_lessons, $progress_percentage, $status]);
        
        // Commit transaction
        $pdo->commit();
        
        return [
            'success' => true,
            'completed_lessons' => $completed_lessons,
            'total_lessons' => $total_lessons,
            'progress_percentage' => $progress_percentage,
            'status' => $status
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Function to get course progress
function getCourseProgress($user_id, $course_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            cp.lessons_completed,
            cp.progress_percentage,
            cp.status,
            c.total_lessons,
            c.title
        FROM course_progress cp
        JOIN courses c ON cp.course_id = c.id
        WHERE cp.user_id = ? AND cp.course_id = ?
    ");
    $stmt->execute([$user_id, $course_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Function to get lesson progress
function getLessonProgress($user_id, $course_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT lesson_number, completed, completed_at 
        FROM lesson_progress 
        WHERE user_id = ? AND course_id = ? 
        ORDER BY lesson_number
    ");
    $stmt->execute([$user_id, $course_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['action'])) {
        switch ($input['action']) {
            case 'complete_lesson':
                $user_id = $_SESSION['user_id'];
                $course_id = $input['course_id'];
                $lesson_number = $input['lesson_number'];
                
                $result = updateCourseProgress($user_id, $course_id, $lesson_number);
                echo json_encode($result);
                break;
                
            case 'get_progress':
                $user_id = $_SESSION['user_id'];
                $course_id = $input['course_id'];
                
                $progress = getCourseProgress($user_id, $course_id);
                $lessons = getLessonProgress($user_id, $course_id);
                
                echo json_encode([
                    'success' => true,
                    'progress' => $progress,
                    'lessons' => $lessons
                ]);
                break;
                
            default:
                echo json_encode(['success' => false, 'error' => 'Invalid action']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'No action specified']);
    }
    exit;
}
?>