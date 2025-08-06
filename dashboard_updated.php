<?php
require_once 'config.php';

// Get user progress summary
function getUserProgressSummary($user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_courses,
            SUM(CASE WHEN cp.status = 'completed' THEN 1 ELSE 0 END) as completed_courses,
            SUM(CASE WHEN cp.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_courses,
            ROUND(AVG(CASE WHEN cp.progress_percentage > 0 THEN cp.progress_percentage ELSE NULL END), 1) as avg_progress
        FROM courses c
        LEFT JOIN course_progress cp ON c.id = cp.course_id AND cp.user_id = ?
    ");
    
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get recent course activity
function getRecentActivity($user_id, $limit = 3) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            c.title,
            c.age_group,
            cp.progress_percentage,
            cp.status,
            cp.last_accessed
        FROM course_progress cp
        JOIN courses c ON cp.course_id = c.id
        WHERE cp.user_id = ? AND cp.last_accessed IS NOT NULL
        ORDER BY cp.last_accessed DESC
        LIMIT ?
    ");
    
    $stmt->execute([$user_id, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$progress_summary = getUserProgressSummary($_SESSION['user_id']);
$recent_activity = getRecentActivity($_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Course Management System</title>
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

        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-left h1 {
            color: #333;
            font-size: 2.5em;
            margin-bottom: 10px;
        }

        .header-left p {
            color: #666;
            font-size: 1.1em;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .profile-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            position: relative;
        }

        .profile-icon:hover {
            transform: scale(1.1);
            box-shadow: 0 10px 25px rgba(79, 172, 254, 0.4);
        }

        .profile-icon::after {
            content: 'View Profile';
            position: absolute;
            top: -40px;
            left: 50%;
            transform: translateX(-50%);
            background: #333;
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }

        .profile-icon:hover::after {
            opacity: 1;
        }

        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 15px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .stat-icon.total {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .stat-icon.completed {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }

        .stat-icon.progress {
            background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
        }

        .stat-icon.average {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 1.1em;
        }

        .main-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }

        .courses-section {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
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

        .course-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }

        .course-card {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
        }

        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            border-color: #4facfe;
        }

        .course-age {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: bold;
            margin-bottom: 15px;
            display: inline-block;
        }

        .course-title {
            font-size: 1.3em;
            font-weight: bold;
            color: #333;
            margin-bottom: 15px;
        }

        .course-description {
            color: #666;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .course-btn {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 25px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .course-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(79, 172, 254, 0.4);
        }

        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .activity-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: white;
            flex-shrink: 0;
        }

        .activity-icon.completed {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }

        .activity-icon.in-progress {
            background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
        }

        .activity-details {
            flex: 1;
        }

        .activity-title {
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .activity-meta {
            font-size: 0.9em;
            color: #666;
        }

        .progress-indicator {
            font-size: 0.8em;
            font-weight: bold;
            color: #4facfe;
        }

        @media (max-width: 1024px) {
            .main-content {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
        }

        @media (max-width: 768px) {
            .dashboard-container {
                padding: 10px;
            }
            
            .header {
                padding: 20px;
            }
            
            .header-left h1 {
                font-size: 2em;
            }
            
            .course-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-overview {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="header">
            <div class="header-left">
                <h1>Learning Dashboard</h1>
                <p>Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>!</p>
            </div>
            <div class="header-right">
                <a href="profile.php" class="profile-icon">
                    👤
                </a>
            </div>
        </div>

        <div class="stats-overview">
            <div class="stat-card">
                <div class="stat-icon total">📚</div>
                <div class="stat-number"><?php echo $progress_summary['total_courses']; ?></div>
                <div class="stat-label">Total Courses</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon completed">✅</div>
                <div class="stat-number"><?php echo $progress_summary['completed_courses']; ?></div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon progress">⏳</div>
                <div class="stat-number"><?php echo $progress_summary['in_progress_courses']; ?></div>
                <div class="stat-label">In Progress</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon average">📊</div>
                <div class="stat-number"><?php echo $progress_summary['avg_progress'] ?? 0; ?>%</div>
                <div class="stat-label">Average Progress</div>
            </div>
        </div>

        <div class="main-content">
            <div class="courses-section">
                <h2 class="section-title">Available Courses</h2>
                <div class="course-grid">
                    <a href="courses age 6-8.php" class="course-card">
                        <div class="course-age">Ages 6-8</div>
                        <div class="course-title">Programming Basics for Kids</div>
                        <div class="course-description">
                            Learn the fundamentals of programming through fun games and interactive activities.
                        </div>
                        <div class="course-btn">Start Learning</div>
                    </a>

                    <a href="courses age 9-11.php" class="course-card">
                        <div class="course-age">Ages 9-11</div>
                        <div class="course-title">Web Development Fundamentals</div>
                        <div class="course-description">
                            Introduction to HTML, CSS, and JavaScript with hands-on projects.
                        </div>
                        <div class="course-btn">Start Learning</div>
                    </a>

                    <a href="courses age 12-14.php" class="course-card">
                        <div class="course-age">Ages 12-14</div>
                        <div class="course-title">Advanced Programming Concepts</div>
                        <div class="course-description">
                            Object-oriented programming, algorithms, and data structures.
                        </div>
                        <div class="course-btn">Start Learning</div>
                    </a>

                    <a href="courses age 15-18.php" class="course-card">
                        <div class="course-age">Ages 15-18</div>
                        <div class="course-title">Full Stack Development</div>
                        <div class="course-description">
                            Complete web application development with modern frameworks.
                        </div>
                        <div class="course-btn">Start Learning</div>
                    </a>
                </div>
            </div>

            <div class="sidebar">
                <div class="activity-card">
                    <h3 class="section-title">Recent Activity</h3>
                    <?php if (empty($recent_activity)): ?>
                        <p style="text-align: center; color: #666; padding: 20px;">
                            No recent activity. Start a course to see your progress here!
                        </p>
                    <?php else: ?>
                        <?php foreach ($recent_activity as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-icon <?php echo $activity['status']; ?>">
                                    <?php echo $activity['status'] === 'completed' ? '✓' : '⏳'; ?>
                                </div>
                                <div class="activity-details">
                                    <div class="activity-title"><?php echo htmlspecialchars($activity['title']); ?></div>
                                    <div class="activity-meta">
                                        Age <?php echo htmlspecialchars($activity['age_group']); ?> • 
                                        <span class="progress-indicator"><?php echo number_format($activity['progress_percentage'], 1); ?>%</span>
                                    </div>
                                    <div class="activity-meta">
                                        <?php echo date('M j, g:i A', strtotime($activity['last_accessed'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="activity-card">
                    <h3 class="section-title">Quick Actions</h3>
                    <div style="display: flex; flex-direction: column; gap: 15px;">
                        <a href="profile.php" class="course-btn" style="text-align: center;">View Full Profile</a>
                        <a href="database_setup.php" class="course-btn" style="text-align: center; background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">Setup Database</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>