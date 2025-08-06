<?php
require_once 'config.php';

// Get user progress data
function getUserProgress($user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            c.id as course_id,
            c.title,
            c.description,
            c.age_group,
            c.total_lessons,
            COALESCE(cp.lessons_completed, 0) as lessons_completed,
            COALESCE(cp.progress_percentage, 0) as progress_percentage,
            COALESCE(cp.status, 'not_started') as status,
            cp.last_accessed
        FROM courses c
        LEFT JOIN course_progress cp ON c.id = cp.course_id AND cp.user_id = ?
        ORDER BY c.age_group, c.id
    ");
    
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get overall statistics
function getUserStats($user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_courses,
            SUM(CASE WHEN cp.status = 'completed' THEN 1 ELSE 0 END) as completed_courses,
            SUM(CASE WHEN cp.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_courses,
            ROUND(AVG(CASE WHEN cp.progress_percentage > 0 THEN cp.progress_percentage ELSE NULL END), 2) as avg_progress
        FROM courses c
        LEFT JOIN course_progress cp ON c.id = cp.course_id AND cp.user_id = ?
    ");
    
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

$user_progress = getUserProgress($_SESSION['user_id']);
$user_stats = getUserStats($_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile - Course Progress</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
        }

        .back-btn {
            position: absolute;
            left: 30px;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-50%) scale(1.05);
        }

        .profile-info {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            margin-bottom: 20px;
        }

        .profile-avatar {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
        }

        .profile-details h1 {
            font-size: 2.5em;
            margin-bottom: 5px;
        }

        .profile-details p {
            opacity: 0.9;
            font-size: 1.1em;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .stat-card {
            background: rgba(255,255,255,0.1);
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            backdrop-filter: blur(10px);
        }

        .stat-number {
            font-size: 2em;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-label {
            opacity: 0.9;
            font-size: 0.9em;
        }

        .content {
            padding: 40px;
        }

        .section-title {
            font-size: 2em;
            color: #333;
            margin-bottom: 30px;
            text-align: center;
            position: relative;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 3px;
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            border-radius: 2px;
        }

        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 40px;
        }

        .course-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border: 2px solid transparent;
            position: relative;
            overflow: hidden;
        }

        .course-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }

        .course-card.completed::before {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }

        .course-card.in-progress::before {
            background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
        }

        .course-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .course-title {
            font-size: 1.3em;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .age-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: bold;
        }

        .course-description {
            color: #666;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .progress-section {
            margin-bottom: 20px;
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .progress-text {
            font-weight: bold;
            color: #333;
        }

        .progress-percentage {
            font-size: 1.1em;
            font-weight: bold;
            color: #4facfe;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: #f0f0f0;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 10px;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        .progress-fill.completed {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }

        .progress-fill.in-progress {
            background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
        }

        .lesson-info {
            display: flex;
            justify-content: space-between;
            font-size: 0.9em;
            color: #666;
            margin-bottom: 15px;
        }

        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-in-progress {
            background: #fff3cd;
            color: #856404;
        }

        .status-not-started {
            background: #f8d7da;
            color: #721c24;
        }

        .course-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s ease;
            text-align: center;
            flex: 1;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(79, 172, 254, 0.4);
        }

        .btn-secondary {
            background: #f8f9fa;
            color: #333;
            border: 2px solid #e9ecef;
        }

        .btn-secondary:hover {
            background: #e9ecef;
            transform: scale(1.05);
        }

        .last-accessed {
            font-size: 0.8em;
            color: #999;
            margin-top: 10px;
            font-style: italic;
        }

        @media (max-width: 768px) {
            .container {
                margin: 10px;
                border-radius: 15px;
            }

            .header {
                padding: 20px;
            }

            .profile-details h1 {
                font-size: 2em;
            }

            .content {
                padding: 20px;
            }

            .courses-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
            
            <div class="profile-info">
                <div class="profile-avatar">
                    👤
                </div>
                <div class="profile-details">
                    <h1><?php echo htmlspecialchars($_SESSION['username']); ?></h1>
                    <p><?php echo htmlspecialchars($_SESSION['email']); ?></p>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $user_stats['total_courses']; ?></div>
                    <div class="stat-label">Total Courses</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $user_stats['completed_courses']; ?></div>
                    <div class="stat-label">Completed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $user_stats['in_progress_courses']; ?></div>
                    <div class="stat-label">In Progress</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $user_stats['avg_progress'] ?? 0; ?>%</div>
                    <div class="stat-label">Average Progress</div>
                </div>
            </div>
        </div>

        <div class="content">
            <h2 class="section-title">Course Progress</h2>
            
            <div class="courses-grid">
                <?php foreach ($user_progress as $course): ?>
                    <div class="course-card <?php echo $course['status']; ?>">
                        <div class="course-header">
                            <div>
                                <div class="course-title"><?php echo htmlspecialchars($course['title']); ?></div>
                            </div>
                            <div class="age-badge">Age <?php echo htmlspecialchars($course['age_group']); ?></div>
                        </div>
                        
                        <div class="course-description">
                            <?php echo htmlspecialchars($course['description']); ?>
                        </div>
                        
                        <div class="progress-section">
                            <div class="progress-header">
                                <span class="progress-text">Progress</span>
                                <span class="progress-percentage"><?php echo number_format($course['progress_percentage'], 1); ?>%</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill <?php echo $course['status']; ?>" 
                                     style="width: <?php echo $course['progress_percentage']; ?>%"></div>
                            </div>
                            <div class="lesson-info">
                                <span>Lessons: <?php echo $course['lessons_completed']; ?>/<?php echo $course['total_lessons']; ?></span>
                                <span class="status-badge status-<?php echo str_replace('_', '-', $course['status']); ?>">
                                    <?php echo ucwords(str_replace('_', ' ', $course['status'])); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="course-actions">
                            <?php if ($course['status'] === 'not_started'): ?>
                                <a href="courses age <?php echo $course['age_group']; ?>.php?course_id=<?php echo $course['course_id']; ?>" class="btn btn-primary">Start Course</a>
                            <?php elseif ($course['status'] === 'in_progress'): ?>
                                <a href="courses age <?php echo $course['age_group']; ?>.php?course_id=<?php echo $course['course_id']; ?>" class="btn btn-primary">Continue</a>
                            <?php else: ?>
                                <a href="courses age <?php echo $course['age_group']; ?>.php?course_id=<?php echo $course['course_id']; ?>" class="btn btn-secondary">Review</a>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($course['last_accessed']): ?>
                            <div class="last-accessed">
                                Last accessed: <?php echo date('M j, Y g:i A', strtotime($course['last_accessed'])); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>