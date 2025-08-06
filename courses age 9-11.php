<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: signin.php');
    exit();
}

// Get player registration data
$stmt = $pdo->prepare("SELECT * FROM player_registrations WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$registration = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if player is approved
if (!$registration || $registration['registration_status'] !== 'approved') {
    header('Location: dashboard.php');
    exit();
}

/**
 * Calculate age from birth date components
 */
function calculateAge($day, $month, $year) {
    $birth_date = new DateTime("$year-$month-$day");
    $today = new DateTime();
    return $today->diff($birth_date)->y;
}

// Calculate player's age and age group
$age = calculateAge($registration['birth_day'], $registration['birth_month'], $registration['birth_year']);
$age_group = '';
if ($age >= 6 && $age <= 8) $age_group = '6-8';
elseif ($age >= 9 && $age <= 11) $age_group = '9-11';
elseif ($age >= 12 && $age <= 14) $age_group = '12-14';
elseif ($age >= 15 && $age <= 18) $age_group = '15-18';

// Get course modules for player's age group
$query = "SELECT cm.*, pp.progress_percentage, pp.is_completed, pp.completed_at, pp.quiz_score
          FROM course_modules cm 
          LEFT JOIN player_progress pp ON cm.id = pp.module_id AND pp.user_id = ?
          WHERE cm.age_group = ?
          ORDER BY cm.module_number";
$stmt = $pdo->prepare($query);
$stmt->execute([$_SESSION['user_id'], $age_group]);
$modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * Handle module completion form submission
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_module'])) {
    $module_id = filter_input(INPUT_POST, 'module_id', FILTER_VALIDATE_INT);
    $quiz_score = filter_input(INPUT_POST, 'quiz_score', FILTER_VALIDATE_INT) ?? 0;
    
    if ($module_id === false) {
        $_SESSION['error'] = 'Invalid module ID';
        header('Location: courses.php');
        exit();
    }

    try {
        $pdo->beginTransaction();
        
        // Check if progress record exists
        $stmt = $pdo->prepare("SELECT id FROM player_progress WHERE user_id = ? AND module_id = ?");
        $stmt->execute([$_SESSION['user_id'], $module_id]);
        
        if ($existing = $stmt->fetch()) {
            // Update existing progress
            $query = "UPDATE player_progress SET progress_percentage = 100, is_completed = TRUE, 
                      completed_at = NOW(), quiz_score = ? WHERE id = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$quiz_score, $existing['id']]);
        } else {
            // Create new progress record
            $query = "INSERT INTO player_progress (user_id, module_id, progress_percentage, is_completed, 
                      completed_at, quiz_score) VALUES (?, ?, 100, TRUE, NOW(), ?)";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$_SESSION['user_id'], $module_id, $quiz_score]);
        }
        
        // Create notification
        $notification_msg = "Congratulations! You've completed module #$module_id.";
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], 'Module Completed', $notification_msg, 'success']);
        
        $pdo->commit();
        $_SESSION['success'] = 'Module completed successfully!';
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = 'Error completing module: ' . $e->getMessage();
    }
    
    header('Location: courses.php');
    exit();
}

// Set module-specific colors
$module_colors = [
    1 => '#3562A6', // Module 1 - Blue
    2 => '#0E1EB5', // Module 2 - Dark Blue
    3 => '#078930', // Module 3 - Green
    4 => '#A62E2E', // Module 4 - Red
    5 => '#6A0DAD'  // Module 5 - Purple
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses - Ethiopian Football Scouting</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #3562A6;
            --secondary: #0E1EB5;
            --dark: #0C1E2E;
            --light: #F5F5F5;
            --accent: #078930;
            --text: #333333;
            --success: #27ae60;
            --error: #e74c3c;
            
            /* Module-specific colors */
            --module1-color: <?= $module_colors[1] ?>;
            --module2-color: <?= $module_colors[2] ?>;
            --module3-color: <?= $module_colors[3] ?>;
            --module4-color: <?= $module_colors[4] ?>;
            --module5-color: <?= $module_colors[5] ?>;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            max-width: 100%;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
            color: var(--text);
            line-height: 1.6;
        }
        
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 30px;
            background-color: var(--dark);
            color: white;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        
        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            color: white;
        }
        
        .nav-links {
            display: flex;
            gap: 25px;
        }
        
        .nav-links a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            padding: 8px 12px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }
        
        .nav-links a:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .nav-links a.active {
            background-color: rgba(52, 152, 219, 0.2);
            color: var(--primary);
        }
        
        h1 {
            color: var(--primary);
            text-align: center;
            margin-bottom: 30px;
            font-size: 2.2rem;
        }
        
        .progress-tracker {
            text-align: center;
            margin-bottom: 30px;
            font-weight: bold;
            font-size: 1.1rem;
        }
        
        .progress-tracker span {
            display: inline-block;
            margin: 0 5px;
            padding: 8px 15px;
            border-radius: 50%;
            background-color: #e0e0e0;
            color: #666;
            font-size: 1rem;
        }
        
        .progress-tracker span.completed {
            background-color: var(--accent);
            color: white;
        }
        
        .module-selector {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .module-btn {
            padding: 15px 25px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            color: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            min-width: 250px;
            text-align: center;
        }
        
        .module-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .module-btn.locked {
            background-color: #cccccc !important;
            cursor: not-allowed;
            opacity: 0.7;
        }
        
        .module-btn.locked::after {
            content: " 🔒";
        }
        
        #module1-btn { background-color: var(--module1-color); }
        #module2-btn { background-color: var(--module2-color); }
        #module3-btn { background-color: var(--module3-color); }
        #module4-btn { background-color: var(--module4-color); }
        #module5-btn { background-color: var(--module5-color); }
        
        .module-container {
            display: none;
            background-color: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .module-container.active {
            display: block;
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .module-title {
            color: var(--primary);
            border-bottom: 2px solid #eee;
            padding-bottom: 15px;
            margin-bottom: 25px;
            font-size: 1.8rem;
        }
        
        .lesson-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px dashed #eee;
        }
        
        .lesson-section h3 {
            color: var(--primary);
            font-size: 1.4rem;
            margin-bottom: 15px;
        }
        
        .lesson-section p {
            margin-bottom: 15px;
            line-height: 1.7;
        }
        
        .key-points {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid var(--primary);
        }
        
        .key-points h4 {
            margin-top: 0;
            color: var(--primary);
        }
        
        .key-points ul {
            padding-left: 20px;
        }
        
        .key-points li {
            margin-bottom: 8px;
        }
        
        .fun-icon {
            margin-right: 8px;
            font-size: 1.2em;
        }
        
        .position-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .position-card {
            background-color: #f5f5f5;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid var(--primary);
        }
        
        .position-card h4 {
            margin-top: 0;
            color: var(--primary);
        }
        
        .video-container {
            margin: 40px 0;
            text-align: center;
        }
        
        .video-container h3 {
            margin-bottom: 15px;
            color: var(--primary);
        }
        
        iframe {
            max-width: 100%;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        /* Quiz Styles */
        .quiz-container {
            background-color: #f0f8ff;
            border-radius: 10px;
            padding: 25px;
            margin-top: 40px;
            border: 1px solid #d1e3fa;
        }
        
        .quiz-container h3 {
            color: var(--primary);
            margin-top: 0;
            font-size: 1.5rem;
        }
        
        .question {
            margin-bottom: 25px;
            padding: 20px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .question h4 {
            margin-top: 0;
            margin-bottom: 15px;
            color: var(--primary);
        }
        
        .option {
            display: block;
            margin: 12px 0;
            padding: 12px 15px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.2s;
            border: 1px solid #ddd;
            background-color: #f9f9f9;
        }
        
        .option:hover {
            background-color: #f1f8e9;
            border-color: #c8e6c9;
        }
        
        .selected {
            background-color: #c8e6c9;
            border-color: #81c784;
        }
        
        .submit-btn {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 12px 30px;
            font-size: 1rem;
            border-radius: 5px;
            cursor: pointer;
            display: block;
            margin: 25px auto;
            transition: background-color 0.2s;
            font-weight: bold;
        }
        
        .submit-btn:hover {
            background-color: var(--secondary);
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .submit-btn:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .feedback {
            margin-top: 15px;
            padding: 12px;
            border-radius: 4px;
            font-weight: bold;
        }
        
        .correct {
            background-color: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #2e7d32;
        }
        
        .incorrect {
            background-color: #ffebee;
            color: #c62828;
            border-left: 4px solid #c62828;
        }
        
        .explanation {
            font-weight: normal;
            margin-top: 8px;
            font-size: 0.9rem;
            color: #555;
        }
        
        .result {
            font-size: 1.2rem;
            font-weight: bold;
            text-align: center;
            margin: 25px 0;
            padding: 20px;
            border-radius: 5px;
            background-color: #e3f2fd;
            border-left: 4px solid var(--primary);
        }
        
        .hidden {
            display: none;
        }
        
        .try-again-btn {
            background-color: var(--accent);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 15px;
            transition: all 0.2s;
        }
        
        .try-again-btn:hover {
            background-color: #056a28;
        }
        
        /* Module-specific styling */
        #module1-container h2, #module1-container h3 { color: var(--module1-color); }
        #module2-container h2, #module2-container h3 { color: var(--module2-color); }
        #module3-container h2, #module3-container h3 { color: var(--module3-color); }
        #module4-container h2, #module4-container h3 { color: var(--module4-color); }
        #module5-container h2, #module5-container h3 { color: var(--module5-color); }
        
        #module1-container .submit-btn { background-color: var(--module1-color); }
        #module2-container .submit-btn { background-color: var(--module2-color); }
        #module3-container .submit-btn { background-color: var(--module3-color); }
        #module4-container .submit-btn { background-color: var(--module4-color); }
        #module5-container .submit-btn { background-color: var(--module5-color); }
        
        #module1-container .submit-btn:hover { background-color: #2a4f85; }
        #module2-container .submit-btn:hover { background-color: #0b187d; }
        #module3-container .submit-btn:hover { background-color: #056a28; }
        #module4-container .submit-btn:hover { background-color: #8a2525; }
        #module5-container .submit-btn:hover { background-color: #550a8a; }
        
        /* Responsive styles */
        @media (max-width: 768px) {
            .module-selector {
                flex-direction: column;
                align-items: center;
            }
            
            .module-btn {
                width: 100%;
            }
            
            .navbar {
                flex-direction: column;
                padding: 15px;
            }
            
            .nav-links {
                margin-top: 15px;
                flex-wrap: wrap;
                justify-content: center;
            }
            
            h1 {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>
    <!-- Alert Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
        <script>
            setTimeout(() => {
                const alert = document.querySelector('.alert-success');
                if (alert) alert.remove();
            }, 5000);
        </script>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
        <script>
            setTimeout(() => {
                const alert = document.querySelector('.alert-error');
                if (alert) alert.remove();
            }, 5000);
        </script>
    <?php endif; ?>

    <nav class="navbar">
        <div class="logo">ETHIO SCOUT</div>
        <div class="nav-links">
            <a href="dashboard.php">Dashboard</a>
            <a href="#" class="active">My Courses</a>
            <a href="signin.php">Logout</a>
        </div>
    </nav>

    <h1>⚽ Football Training Program (Age 9-11)</h1>
    
    <div class="progress-tracker">
        Progress: 
        <span id="module1-progress" class="completed">1</span>
        <span id="module2-progress">2</span>
        <span id="module3-progress">3</span>
        <span id="module4-progress">4</span>
        <span id="module5-progress">5</span>
    </div>
    
    <div class="module-selector">
        <button id="module1-btn" class="module-btn">Module 1: Football Rules & Team Understanding</button>
        <button id="module2-btn" class="module-btn locked">Module 2: Ball Control & Dribbling</button>
        <button id="module3-btn" class="module-btn locked">Module 3: Passing & Receiving</button>
        <button id="module4-btn" class="module-btn locked">Module 4: Shooting & Scoring</button>
        <button id="module5-btn" class="module-btn locked">Module 5: Fitness & Team Play</button>
    </div>
    
    <!-- Module 1 Container -->
    <div id="module1-container" class="module-container active">
        <h2 class="module-title">1. Football Rules & Team Understanding</h2>
        
        <div class="lesson-section">
            <h3>1. Detailed Rules Overview</h3>
            <p><strong>Definition:</strong> This topic expands the players' knowledge of fundamental football rules, helping them play correctly and confidently.</p>
            
            <div class="key-points">
                <h4>Key Rules to Cover:</h4>
                <ul>
                    <li><span class="fun-icon">🚩</span> <strong>Offside rule (basic):</strong> An attacking player must be behind or level with the second-last defender when receiving the ball.</li>
                    <li><span class="fun-icon">✋</span> <strong>Throw-ins:</strong> Two hands over the head, both feet on the ground.</li>
                    <li><span class="fun-icon">🥅</span> <strong>Goal kicks and corner kicks</strong></li>
                    <li><span class="fun-icon">🖐️</span> <strong>Handball rule:</strong> No intentional use of the hand or arm.</li>
                    <li><span class="fun-icon">🛑</span> <strong>Fouls and free kicks:</strong> Kicking, pushing, tripping, etc.</li>
                </ul>
                
                <h4>Why It Matters:</h4>
                <p>Understanding rules prevents unnecessary fouls and builds game awareness.</p>
            </div>
        </div>
        
        <div class="lesson-section">
            <h3>2. Importance of Teamwork</h3>
            <p><strong>Definition:</strong> Teaching young players that football is a team game, where working together leads to better success than individual efforts.</p>
            
            <div class="key-points">
                <h4>Focus Areas:</h4>
                <ul>
                    <li><span class="fun-icon">🔄</span> <strong>Passing and support:</strong> Look for teammates in better positions.</li>
                    <li><span class="fun-icon">🗣️</span> <strong>Communication:</strong> Call for the ball, alert teammates.</li>
                    <li><span class="fun-icon">👥</span> <strong>Respect roles:</strong> Play assigned positions rather than chasing the ball.</li>
                </ul>
                
                <h4>Activity Example:</h4>
                <p>"Pass and move" drills that require players to cooperate and communicate.</p>
            </div>
        </div>
        
        <div class="lesson-section">
            <h3>3. Basic Positions on the Field</h3>
            <p><strong>Definition:</strong> Introducing players to the standard positions and what each player's responsibility is during a match.</p>
            
            <div class="position-grid">
                <div class="position-card">
                    <h4><span class="fun-icon">🧤</span> Goalkeeper</h4>
                    <p><strong>Fun Name:</strong> The Protector</p>
                    <p><strong>Job:</strong> Stop the ball from going in the net</p>
                </div>
                <div class="position-card">
                    <h4><span class="fun-icon">🛡️</span> Defenders</h4>
                    <p><strong>Fun Name:</strong> The Bodyguards</p>
                    <p><strong>Job:</strong> Stop the other team from scoring</p>
                </div>
                <div class="position-card">
                    <h4><span class="fun-icon">🔗</span> Midfielders</h4>
                    <p><strong>Fun Name:</strong> The Connectors</p>
                    <p><strong>Job:</strong> Help both defense and attack</p>
                </div>
                <div class="position-card">
                    <h4><span class="fun-icon">🎯</span> Forwards</h4>
                    <p><strong>Fun Name:</strong> The Goal Hunters</p>
                    <p><strong>Job:</strong> Score goals for the team</p>
                </div>
            </div>
            
            <div class="key-points">
                <h4>Drill Idea:</h4>
                <p>Use cones or a small field to let each player try different positions in a rotation.</p>
            </div>
        </div>
        
        <div class="video-container">
            <h3>Watch and Learn: Teamwork in Action!</h3>
            <iframe width="560" height="315" src="https://www.youtube.com/embed/dQw4w9WgXcQ" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
            <p><em>Fun examples of teamwork and positions in football</em></p>
        </div>
        
        <div class="quiz-container">
            <h3>🧠 Football Quiz</h3>
            <p>Test what you've learned about rules and teamwork! Select one answer for each question.</p>
            
            <div id="quiz1">
                <div class="question" id="q1-1">
                    <h4>1. Which part of the body should NOT touch the ball in most cases?</h4>
                    <div class="option" data-question="1" data-answer="A">A. Head</div>
                    <div class="option" data-question="1" data-answer="B">B. Foot</div>
                    <div class="option" data-question="1" data-answer="C">C. Hand</div>
                    <div class="option" data-question="1" data-answer="D">D. Chest</div>
                    <div class="feedback hidden" id="feedback1-1"></div>
                </div>
                
                <div class="question" id="q1-2">
                    <h4>2. Why is teamwork important in football?</h4>
                    <div class="option" data-question="2" data-answer="A">A. To pass all the time</div>
                    <div class="option" data-question="2" data-answer="B">B. Because one player can't do everything alone</div>
                    <div class="option" data-question="2" data-answer="C">C. So players don't have to run</div>
                    <div class="option" data-question="2" data-answer="D">D. To impress coaches</div>
                    <div class="feedback hidden" id="feedback1-2"></div>
                </div>
                
                <div class="question" id="q1-3">
                    <h4>3. What does a defender mainly do?</h4>
                    <div class="option" data-question="3" data-answer="A">A. Score goals</div>
                    <div class="option" data-question="3" data-answer="B">B. Sing the team song</div>
                    <div class="option" data-question="3" data-answer="C">C. Stop the other team from scoring</div>
                    <div class="option" data-question="3" data-answer="D">D. Take corner kicks</div>
                    <div class="feedback hidden" id="feedback1-3"></div>
                </div>
                
                <div class="question" id="q1-4">
                    <h4>4. Which of these is true about throw-ins?</h4>
                    <div class="option" data-question="4" data-answer="A">A. You can use one hand</div>
                    <div class="option" data-question="4" data-answer="B">B. You must be running</div>
                    <div class="option" data-question="4" data-answer="C">C. You must keep both feet on the ground</div>
                    <div class="option" data-question="4" data-answer="D">D. You can shoot directly from it</div>
                    <div class="feedback hidden" id="feedback1-4"></div>
                </div>
                
                <div class="question" id="q1-5">
                    <h4>5. What is the role of the goalkeeper?</h4>
                    <div class="option" data-question="5" data-answer="A">A. To dribble through everyone</div>
                    <div class="option" data-question="5" data-answer="B">B. To stop goals from being scored</div>
                    <div class="option" data-question="5" data-answer="C">C. To stay off the field</div>
                    <div class="option" data-question="5" data-answer="D">D. To run with midfielders</div>
                    <div class="feedback hidden" id="feedback1-5"></div>
                </div>
                
                <button class="submit-btn" id="submit-btn1">Submit Answers</button>
                <div class="result hidden" id="result1"></div>
                <div id="try-again1" class="hidden" style="text-align: center;">
                    <p>You need at least 3 correct answers to unlock the next module.</p>
                    <button class="try-again-btn" id="try-again-btn1">Try Again</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Module 2 Container -->
    <div id="module2-container" class="module-container">
        <h2 class="module-title">2. Ball Control & Dribbling</h2>
        
        <div class="lesson-section">
            <h3>1. Using Different Parts of the Foot</h3>
            <p><strong>Definition:</strong> Players learn to control and move the ball using the inside, outside, sole, and laces (top) of their feet.</p>
            
            <div class="key-points">
                <h4>Why It Matters:</h4>
                <ul>
                    <li>Improves overall control</li>
                    <li>Allows players to dribble and change direction in tight spaces</li>
                    <li>Builds confidence with both feet</li>
                </ul>
            </div>
            
            <div class="key-points">
                <h4>Techniques:</h4>
                <ul>
                    <li><strong>Inside foot:</strong> for short touches or direction change</li>
                    <li><strong>Outside foot:</strong> to trick opponents or shift quickly</li>
                    <li><strong>Sole (bottom):</strong> for stopping, rolling, or dragging the ball</li>
                    <li><strong>Laces:</strong> for longer pushes or speed dribbling</li>
                </ul>
            </div>
            
            <div class="key-points">
                <h4>Drill Idea:</h4>
                <p>"Foot part circuit" – players must dribble in a pattern using only one part of the foot per segment.</p>
            </div>
        </div>
        
        <div class="lesson-section">
            <h3>2. Changing Direction While Dribbling</h3>
            <p><strong>Definition:</strong> Teaches players to turn, stop, or cut the ball to avoid defenders and create space.</p>
            
            <div class="key-points">
                <h4>Key Techniques:</h4>
                <ul>
                    <li>Inside cut</li>
                    <li>Outside cut</li>
                    <li>Pull-back turn</li>
                    <li>Drag-back + turn combo</li>
                </ul>
                
                <h4>Why It Matters:</h4>
                <ul>
                    <li>Improves agility</li>
                    <li>Keeps possession under pressure</li>
                    <li>Helps beat defenders</li>
                </ul>
            </div>
            
            <div class="key-points">
                <h4>Drill Idea:</h4>
                <p>Set up cones in zig-zag and have players dribble through while changing direction using different moves.</p>
            </div>
        </div>
        
        <div class="lesson-section">
            <h3>3. Shielding the Ball from Opponents</h3>
            <p><strong>Definition:</strong> Shielding means using your body to protect the ball from defenders while keeping control.</p>
            
            <div class="key-points">
                <h4>Key Skills:</h4>
                <ul>
                    <li>Keep the ball on the far foot (away from opponent)</li>
                    <li>Stay low with knees slightly bent</li>
                    <li>Arms out for balance</li>
                    <li>Keep head up to scan the field</li>
                </ul>
                
                <h4>When to Use:</h4>
                <ul>
                    <li>Holding the ball while waiting for a pass</li>
                    <li>Protecting it near the sideline</li>
                    <li>Slowing the game down</li>
                </ul>
            </div>
            
            <div class="key-points">
                <h4>Drill Idea:</h4>
                <p>1v1 shielding game: One player protects the ball in a circle, the other tries to steal it in 10 seconds.</p>
            </div>
        </div>
        
        <div class="video-container">
            <h3>Watch and Learn: Dribbling Skills!</h3>
            <iframe width="560" height="315" src="https://www.youtube.com/embed/dQw4w9WgXcQ" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
            <p><em>Fun dribbling exercises for young players</em></p>
        </div>
        
        <div class="quiz-container">
            <h3>🧠 Dribbling Quiz</h3>
            <p>Test what you've learned about ball control! Select one answer for each question.</p>
            
            <div id="quiz2">
                <div class="question" id="q2-1">
                    <h4>1. Which part of the foot is best for stopping or dragging the ball?</h4>
                    <div class="option" data-question="1" data-answer="A">A. Inside</div>
                    <div class="option" data-question="1" data-answer="B">B. Outside</div>
                    <div class="option" data-question="1" data-answer="C">C. Sole</div>
                    <div class="option" data-question="1" data-answer="D">D. Laces</div>
                    <div class="feedback hidden" id="feedback2-1"></div>
                </div>
                
                <div class="question" id="q2-2">
                    <h4>2. What is the main reason to change direction while dribbling?</h4>
                    <div class="option" data-question="2" data-answer="A">A. To show off</div>
                    <div class="option" data-question="2" data-answer="B">B. To confuse your teammates</div>
                    <div class="option" data-question="2" data-answer="C">C. To avoid defenders and keep control</div>
                    <div class="option" data-question="2" data-answer="D">D. To waste time</div>
                    <div class="feedback hidden" id="feedback2-2"></div>
                </div>
                
                <div class="question" id="q2-3">
                    <h4>3. What is shielding in football?</h4>
                    <div class="option" data-question="3" data-answer="A">A. Running away from the ball</div>
                    <div class="option" data-question="3" data-answer="B">B. Passing it backward only</div>
                    <div class="option" data-question="3" data-answer="C">C. Using your body to protect the ball from opponents</div>
                    <div class="option" data-question="3" data-answer="D">D. Holding the ball with your hands</div>
                    <div class="feedback hidden" id="feedback2-3"></div>
                </div>
                
                <div class="question" id="q2-4">
                    <h4>4. Which part of the foot is usually used for fast dribbling?</h4>
                    <div class="option" data-question="4" data-answer="A">A. Laces (top of foot)</div>
                    <div class="option" data-question="4" data-answer="B">B. Heels</div>
                    <div class="option" data-question="4" data-answer="C">C. Knees</div>
                    <div class="option" data-question="4" data-answer="D">D. Toes only</div>
                    <div class="feedback hidden" id="feedback2-4"></div>
                </div>
                
                <div class="question" id="q2-5">
                    <h4>5. What helps you balance while shielding the ball?</h4>
                    <div class="option" data-question="5" data-answer="A">A. Closing your eyes</div>
                    <div class="option" data-question="5" data-answer="B">B. Putting your arms out</div>
                    <div class="option" data-question="5" data-answer="C">C. Jumping</div>
                    <div class="option" data-question="5" data-answer="D">D. Sitting down</div>
                    <div class="feedback hidden" id="feedback2-5"></div>
                </div>
                
                <button class="submit-btn" id="submit-btn2" disabled>Submit Answers</button>
                <div class="result hidden" id="result2"></div>
                <div id="try-again2" class="hidden" style="text-align: center;">
                    <p>You need at least 3 correct answers to unlock the next module.</p>
                    <button class="try-again-btn" id="try-again-btn2">Try Again</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Module 3 Container -->
    <div id="module3-container" class="module-container">
        <h2 class="module-title">3. Passing & Receiving</h2>
        
        <div class="lesson-section">
            <h3>1. Passing Accuracy Drills</h3>
            <p><strong>Definition:</strong> Focuses on helping players deliver precise, well-timed passes to teammates using different parts of the foot.</p>
            
            <div class="key-points">
                <h4>Key Techniques:</h4>
                <ul>
                    <li>Use the inside foot for short, accurate passes</li>
                    <li>Focus on body posture and eye contact</li>
                    <li>Follow through in the direction of the pass</li>
                    <li>Control power – not too soft or too hard</li>
                </ul>
            </div>
            
            <div class="key-points">
                <h4>Drill Idea:</h4>
                <p>"Gate Passing Challenge" - Set up cones as gates and challenge players to pass the ball through them to a partner.</p>
            </div>
        </div>
        
        <div class="lesson-section">
            <h3>2. Receiving with Different Body Parts</h3>
            <p><strong>Definition:</strong> Players learn to control the ball using their chest or thigh when receiving aerial passes.</p>
            
            <div class="key-points">
                <h4>Chest Control Tips:</h4>
                <ul>
                    <li>Relax chest and absorb the ball</li>
                    <li>Angle body so the ball drops in front</li>
                </ul>
                
                <h4>Thigh Control Tips:</h4>
                <ul>
                    <li>Lift one thigh to cushion the ball</li>
                    <li>Let the ball drop for a smooth ground touch</li>
                </ul>
                
                <h4>Why It Matters:</h4>
                <ul>
                    <li>Builds versatility in receiving</li>
                    <li>Increases confidence with high balls</li>
                    <li>Helps control passes under pressure</li>
                </ul>
            </div>
            
            <div class="key-points">
                <h4>Drill Idea:</h4>
                <p>"Air Control Challenge" - Toss or kick the ball gently to a player to practice controlling with chest and thigh, then passing it back.</p>
            </div>
        </div>
        
        <div class="lesson-section">
            <h3>3. Introduction to Long Passes</h3>
            <p><strong>Definition:</strong> Teaches the basics of long-range passing used to switch play, clear danger, or create scoring chances.</p>
            
            <div class="key-points">
                <h4>Key Skills:</h4>
                <ul>
                    <li>Use laces (top of foot) for power</li>
                    <li>Step into the pass for momentum</li>
                    <li>Aim for open space or a moving teammate</li>
                    <li>Follow through with kicking leg</li>
                </ul>
            </div>
            
            <div class="key-points">
                <h4>Drill Idea:</h4>
                <p>"Long Pass Targets" - Players pass to targets or teammates across the field using different distances.</p>
            </div>
        </div>
        
        <div class="video-container">
            <h3>Watch and Learn: Passing Skills!</h3>
            <iframe width="560" height="315" src="https://www.youtube.com/embed/dQw4w9WgXcQ" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
            <p><em>Fun passing exercises for young players</em></p>
        </div>
        
        <div class="quiz-container">
            <h3>🧠 Passing & Receiving Quiz</h3>
            <p>Test what you've learned about passing and controlling the ball! Select one answer for each question.</p>
            
            <div id="quiz3">
                <div class="question" id="q3-1">
                    <h4>1. Which part of the foot is best for accurate short passes?</h4>
                    <div class="option" data-question="1" data-answer="A">A. Outside</div>
                    <div class="option" data-question="1" data-answer="B">B. Inside</div>
                    <div class="option" data-question="1" data-answer="C">C. Heel</div>
                    <div class="option" data-question="1" data-answer="D">D. Toe</div>
                    <div class="feedback hidden" id="feedback3-1"></div>
                </div>
                
                <div class="question" id="q3-2">
                    <h4>2. What should you do when receiving a ball with your chest?</h4>
                    <div class="option" data-question="2" data-answer="A">A. Push your chest forward</div>
                    <div class="option" data-question="2" data-answer="B">B. Let the ball bounce away</div>
                    <div class="option" data-question="2" data-answer="C">C. Relax your chest and absorb the ball</div>
                    <div class="option" data-question="2" data-answer="D">D. Use your hands</div>
                    <div class="feedback hidden" id="feedback3-2"></div>
                </div>
                
                <div class="question" id="q3-3">
                    <h4>3. When should you use a long pass?</h4>
                    <div class="option" data-question="3" data-answer="A">A. When the ball is too close</div>
                    <div class="option" data-question="3" data-answer="B">B. To pass to a far teammate</div>
                    <div class="option" data-question="3" data-answer="C">C. To shoot at goal only</div>
                    <div class="option" data-question="3" data-answer="D">D. When you're tired</div>
                    <div class="feedback hidden" id="feedback3-3"></div>
                </div>
                
                <div class="question" id="q3-4">
                    <h4>4. Which body part can help you control a bouncing ball from the air?</h4>
                    <div class="option" data-question="4" data-answer="A">A. Head only</div>
                    <div class="option" data-question="4" data-answer="B">B. Elbow</div>
                    <div class="option" data-question="4" data-answer="C">C. Thigh or chest</div>
                    <div class="option" data-question="4" data-answer="D">D. Knee</div>
                    <div class="feedback hidden" id="feedback3-4"></div>
                </div>
                
                <div class="question" id="q3-5">
                    <h4>5. What helps you pass more accurately?</h4>
                    <div class="option" data-question="5" data-answer="A">A. Kicking as hard as possible</div>
                    <div class="option" data-question="5" data-answer="B">B. Closing your eyes</div>
                    <div class="option" data-question="5" data-answer="C">C. Looking at your target and following through</div>
                    <div class="option" data-question="5" data-answer="D">D. Using your hands</div>
                    <div class="feedback hidden" id="feedback3-5"></div>
                </div>
                
                <button class="submit-btn" id="submit-btn3" disabled>Submit Answers</button>
                <div class="result hidden" id="result3"></div>
                <div id="try-again3" class="hidden" style="text-align: center;">
                    <p>You need at least 3 correct answers to unlock the next module.</p>
                    <button class="try-again-btn" id="try-again-btn3">Try Again</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Module 4 Container -->
    <div id="module4-container" class="module-container">
        <h2 class="module-title">4. Shooting & Scoring</h2>
        
        <div class="lesson-section">
            <h3>1. Shooting with Laces for Power</h3>
            <p><strong>Definition:</strong> Teaches players how to strike the ball with the laces (top of the foot) to generate powerful shots on goal.</p>
            
            <div class="key-points">
                <h4>Technique Tips:</h4>
                <ul>
                    <li><span class="fun-icon">🔒</span> Keep your ankle locked</li>
                    <li><span class="fun-icon">👀</span> Head down, eyes on the ball</li>
                    <li><span class="fun-icon">🦶</span> Plant foot beside the ball</li>
                    <li><span class="fun-icon">⚽</span> Strike the center of the ball with the laces</li>
                    <li><span class="fun-icon">🎯</span> Follow through in the direction of the target</li>
                </ul>
            </div>
            
            <div class="key-points">
                <h4>Why It Matters:</h4>
                <p>Using the laces helps develop shooting strength and makes it harder for goalkeepers to save.</p>
            </div>
            
            <div class="key-points">
                <h4>Drill Idea:</h4>
                <p>Set up a shooting line with a goal; players practice 5 shots each using only their laces.</p>
            </div>
            
            <div class="video-container">
                <iframe src="https://www.youtube.com/embed/fM1dT0Kgh9o" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                <p><em>How to shoot with power using your laces</em></p>
            </div>
        </div>
        
        <div class="lesson-section">
            <h3>2. Accuracy and Placement Drills</h3>
            <p><strong>Definition:</strong> Focuses on aiming shots to specific areas of the goal, rather than just kicking hard.</p>
            
            <div class="key-points">
                <h4>Key Tips:</h4>
                <ul>
                    <li><span class="fun-icon">👟</span> Use the inside foot for better control when placing the shot</li>
                    <li><span class="fun-icon">↖️↘️</span> Aim for the corners of the net</li>
                    <li><span class="fun-icon">🧤</span> Look at the goalkeeper's position to decide where to shoot</li>
                    <li><span class="fun-icon">🎯</span> Combine low power with high precision</li>
                </ul>
            </div>
            
            <div class="key-points">
                <h4>Drill Idea:</h4>
                <p>Place cones in the goal's corners. Players try to hit each corner using both laces and inside-foot shots.</p>
            </div>
        </div>
        
        <div class="lesson-section">
            <h3>3. Shooting on the Move</h3>
            <p><strong>Definition:</strong> Players learn how to shoot while running, after dribbling, or when receiving a moving ball.</p>
            
            <div class="key-points">
                <h4>Key Concepts:</h4>
                <ul>
                    <li><span class="fun-icon">⏱️</span> Time your steps before the shot</li>
                    <li><span class="fun-icon">🚫</span> Don't stop the ball unless needed</li>
                    <li><span class="fun-icon">⚖️</span> Keep balance and shoot in stride</li>
                    <li><span class="fun-icon">👟👟</span> Practice both footed shots</li>
                </ul>
            </div>
            
            <div class="key-points">
                <h4>Why It Matters:</h4>
                <p>Real match scenarios often require shooting in motion—this improves game performance.</p>
            </div>
            
            <div class="key-points">
                <h4>Drill Idea:</h4>
                <p>Set up a cone dribbling path leading to a shot; players must shoot without stopping after the last cone.</p>
            </div>
        </div>
        
        <div class="quiz-container">
            <h3>🧠 Shooting & Scoring Quiz</h3>
            <p>Test your knowledge of shooting techniques and scoring strategies.</p>
            
            <div id="quiz4">
                <div class="question" id="q4-1">
                    <h4>1. Which part of the foot is used for powerful shooting?</h4>
                    <div class="option" data-question="1" data-answer="A">a) Heel</div>
                    <div class="option" data-question="1" data-answer="B">b) Laces</div>
                    <div class="option" data-question="1" data-answer="C">c) Inside</div>
                    <div class="option" data-question="1" data-answer="D">d) Toes</div>
                    <div class="feedback hidden" id="feedback4-1"></div>
                </div>
                
                <div class="question" id="q4-2">
                    <h4>2. Where should you aim to increase your chance of scoring?</h4>
                    <div class="option" data-question="2" data-answer="A">a) Straight at the keeper</div>
                    <div class="option" data-question="2" data-answer="B">b) High above the goal</div>
                    <div class="option" data-question="2" data-answer="C">c) Corners of the goal</div>
                    <div class="option" data-question="2" data-answer="D">d) At your teammate</div>
                    <div class="feedback hidden" id="feedback4-2"></div>
                </div>
                
                <div class="question" id="q4-3">
                    <h4>3. What is the benefit of shooting with the inside foot?</h4>
                    <div class="option" data-question="3" data-answer="A">a) Adds power</div>
                    <div class="option" data-question="3" data-answer="B">b) Improves placement and accuracy</div>
                    <div class="option" data-question="3" data-answer="C">c) Makes the ball spin backward</div>
                    <div class="option" data-question="3" data-answer="D">d) Makes noise</div>
                    <div class="feedback hidden" id="feedback4-3"></div>
                </div>
                
                <div class="question" id="q4-4">
                    <h4>4. What should you do when shooting on the move?</h4>
                    <div class="option" data-question="4" data-answer="A">a) Stop completely before shooting</div>
                    <div class="option" data-question="4" data-answer="B">b) Look away from the ball</div>
                    <div class="option" data-question="4" data-answer="C">c) Stay balanced and shoot in stride</div>
                    <div class="option" data-question="4" data-answer="D">d) Kick with your knee</div>
                    <div class="feedback hidden" id="feedback4-4"></div>
                </div>
                
                <div class="question" id="q4-5">
                    <h4>5. What helps generate power when shooting with laces?</h4>
                    <div class="option" data-question="5" data-answer="A">a) Bending the ankle</div>
                    <div class="option" data-question="5" data-answer="B">b) Locking the ankle and following through</div>
                    <div class="option" data-question="5" data-answer="C">c) Jumping before kicking</div>
                    <div class="option" data-question="5" data-answer="D">d) Holding the ball</div>
                    <div class="feedback hidden" id="feedback4-5"></div>
                </div>
                
                <button class="submit-btn" id="submit-btn4" disabled>Submit Answers</button>
                <div class="result hidden" id="result4"></div>
                <div id="try-again4" class="hidden" style="text-align: center;">
                    <p>You need at least 3 correct answers to unlock the next module.</p>
                    <button class="try-again-btn" id="try-again-btn4">Try Again</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Module 5 Container -->
    <div id="module5-container" class="module-container">
        <h2 class="module-title">5. Fitness & Team Play</h2>
        
        <div class="lesson-section">
            <h3>1. Endurance and Speed Drills</h3>
            <p><strong>Definition:</strong> Exercises designed to improve a young player's ability to run longer distances (endurance) and sprint quickly (speed), both of which are essential in football.</p>
            
            <div class="key-points">
                <h4>Key Activities:</h4>
                <ul>
                    <li><span class="fun-icon">🏃‍♂️</span> Cone sprints: short, explosive runs between cones</li>
                    <li><span class="fun-icon">🏁</span> Relay races: boost competitive energy and fun</li>
                    <li><span class="fun-icon">🔄</span> Shuttle runs & ladder drills: build coordination and acceleration</li>
                    <li><span class="fun-icon">⏱️</span> Jog–Sprint Intervals: improve stamina gradually</li>
                </ul>
            </div>
            
            <div class="key-points">
                <h4>Why It Matters:</h4>
                <p>Enhances a player's ability to keep up with the game, both defensively and offensively.</p>
            </div>
            
            <div class="video-container">
                <iframe src="https://www.youtube.com/embed/8vA3FvQ9d1M" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                <p><em>Fun speed and agility drills for young players</em></p>
            </div>
        </div>
        
        <div class="lesson-section">
            <h3>2. Understanding Positions in Team Play</h3>
            <p><strong>Definition:</strong> Introduces the main player roles on the pitch (goalkeeper, defenders, midfielders, forwards) and what each one does.</p>
            
            <div class="key-points">
                <h4>Position Basics:</h4>
                <ul>
                    <li><span class="fun-icon">🧤</span> Goalkeeper: Protects the goal</li>
                    <li><span class="fun-icon">🛡️</span> Defenders: Stop attacks and protect the goalkeeper</li>
                    <li><span class="fun-icon">🔗</span> Midfielders: Link defense and attack; pass, support, and move</li>
                    <li><span class="fun-icon">⚽</span> Forwards/Strikers: Score goals and pressure the opposing defense</li>
                </ul>
            </div>
            
            <div class="key-points">
                <h4>Activity Idea:</h4>
                <p>Walk the players through a full-sized pitch and show where each position stands during kickoff, goal kicks, and corners.</p>
            </div>
        </div>
        
        <div class="lesson-section">
            <h3>3. Basic Tactical Awareness</h3>
            <p><strong>Definition:</strong> Helps young players understand how to move and think during play: when to pass, move into space, support teammates, or defend.</p>
            
            <div class="key-points">
                <h4>Concepts Introduced:</h4>
                <ul>
                    <li><span class="fun-icon">↔️</span> Spacing: Don't crowd the ball</li>
                    <li><span class="fun-icon">🤝</span> Support: Always be a passing option</li>
                    <li><span class="fun-icon">👥</span> Marking: Stay close to your assigned opponent</li>
                    <li><span class="fun-icon">🔺</span> Shape: Stay in formation (like a triangle or line)</li>
                </ul>
            </div>
            
            <div class="key-points">
                <h4>Why It Matters:</h4>
                <p>Good teamwork makes the game easier and more fun for everyone!</p>
            </div>
            
            <div class="key-points">
                <h4>Game Drill Idea:</h4>
                <p>Play small-sided games (3v3 or 4v4) and pause to teach awareness concepts in live play.</p>
            </div>
        </div>
        
        <div class="quiz-container">
            <h3>🧠 Fitness & Team Play Quiz</h3>
            <p>Test your knowledge of football fitness and teamwork concepts.</p>
            
            <div id="quiz5">
                <div class="question" id="q5-1">
                    <h4>1. Which drill improves a player's sprinting ability?</h4>
                    <div class="option" data-question="1" data-answer="A">a) Walking in circles</div>
                    <div class="option" data-question="1" data-answer="B">b) Cone sprints</div>
                    <div class="option" data-question="1" data-answer="C">c) Kicking the ball high</div>
                    <div class="option" data-question="1" data-answer="D">d) Jumping jacks</div>
                    <div class="feedback hidden" id="feedback5-1"></div>
                </div>
                
                <div class="question" id="q5-2">
                    <h4>2. What is the role of a midfielder?</h4>
                    <div class="option" data-question="2" data-answer="A">a) Only score goals</div>
                    <div class="option" data-question="2" data-answer="B">b) Stand by the goal</div>
                    <div class="option" data-question="2" data-answer="C">c) Help both in attack and defense</div>
                    <div class="option" data-question="2" data-answer="D">d) Do nothing</div>
                    <div class="feedback hidden" id="feedback5-2"></div>
                </div>
                
                <div class="question" id="q5-3">
                    <h4>3. What does "tactical awareness" mean in football?</h4>
                    <div class="option" data-question="3" data-answer="A">a) Knowing what to eat before games</div>
                    <div class="option" data-question="3" data-answer="B">b) Understanding game strategy and movement</div>
                    <div class="option" data-question="3" data-answer="C">c) Knowing everyone's favorite team</div>
                    <div class="option" data-question="3" data-answer="D">d) Cheering from the bench</div>
                    <div class="feedback hidden" id="feedback5-3"></div>
                </div>
                
                <div class="question" id="q5-4">
                    <h4>4. Why is endurance important in football?</h4>
                    <div class="option" data-question="4" data-answer="A">a) To fall asleep after the game</div>
                    <div class="option" data-question="4" data-answer="B">b) To play without getting tired too quickly</div>
                    <div class="option" data-question="4" data-answer="C">c) To help the coach</div>
                    <div class="option" data-question="4" data-answer="D">d) To win penalties</div>
                    <div class="feedback hidden" id="feedback5-4"></div>
                </div>
                
                <div class="question" id="q5-5">
                    <h4>5. What should players do to maintain good spacing on the field?</h4>
                    <div class="option" data-question="5" data-answer="A">a) Stay close to the ball</div>
                    <div class="option" data-question="5" data-answer="B">b) Run away from the game</div>
                    <div class="option" data-question="5" data-answer="C">c) Spread out and find open space</div>
                    <div class="option" data-question="5" data-answer="D">d) Sit on the bench</div>
                    <div class="feedback hidden" id="feedback5-5"></div>
                </div>
                
                <button class="submit-btn" id="submit-btn5" disabled>Submit Answers</button>
                <div class="result hidden" id="result5"></div>
            </div>
        </div>
    </div>

    <script>
        // Module navigation and locking system
        const moduleButtons = {
            1: document.getElementById('module1-btn'),
            2: document.getElementById('module2-btn'),
            3: document.getElementById('module3-btn'),
            4: document.getElementById('module4-btn'),
            5: document.getElementById('module5-btn')
        };
        
        const moduleContainers = {
            1: document.getElementById('module1-container'),
            2: document.getElementById('module2-container'),
            3: document.getElementById('module3-container'),
            4: document.getElementById('module4-container'),
            5: document.getElementById('module5-container')
        };
        
        const progressIndicators = {
            1: document.getElementById('module1-progress'),
            2: document.getElementById('module2-progress'),
            3: document.getElementById('module3-progress'),
            4: document.getElementById('module4-progress'),
            5: document.getElementById('module5-progress')
        };
        
        // Track module completion
        const moduleStatus = {
            1: false,
            2: false,
            3: false,
            4: false,
            5: false
        };
        
        // Correct answers for each quiz
        const correctAnswers = {
            // Module 1
            "1-1": "C",
            "1-2": "B",
            "1-3": "C",
            "1-4": "C",
            "1-5": "B",
            // Module 2
            "2-1": "C",
            "2-2": "C",
            "2-3": "C",
            "2-4": "A",
            "2-5": "B",
            // Module 3
            "3-1": "B",
            "3-2": "C",
            "3-3": "B",
            "3-4": "C",
            "3-5": "C",
            // Module 4
            "4-1": "B",
            "4-2": "C",
            "4-3": "B",
            "4-4": "C",
            "4-5": "B",
            // Module 5
            "5-1": "B",
            "5-2": "C",
            "5-3": "B",
            "5-4": "B",
            "5-5": "C"
        };
        
        // Explanations for each question
        const explanations = {
            // Module 1
            "1-1": "Hands are not allowed (except for the goalkeeper in their area).",
            "1-2": "Football is a team sport where cooperation leads to success.",
            "1-3": "Defenders primarily protect their goal from opponents.",
            "1-4": "Both feet must stay on the ground during a throw-in.",
            "1-5": "The goalkeeper's main job is to prevent goals.",
            // Module 2
            "2-1": "The sole (bottom) of the foot is best for stopping or dragging.",
            "2-2": "Changing direction helps avoid defenders and maintain control.",
            "2-3": "Shielding means protecting the ball with your body.",
            "2-4": "Laces (top of foot) are used for fast dribbling.",
            "2-5": "Arms out helps maintain balance while shielding.",
            // Module 3
            "3-1": "Inside of the foot provides the most control for short passes.",
            "3-2": "Relaxing helps absorb the ball's momentum for control.",
            "3-3": "Long passes are used to reach teammates far away.",
            "3-4": "Thigh and chest are both good for controlling aerial balls.",
            "3-5": "Looking at your target improves passing accuracy.",
            // Module 4
            "4-1": "Laces provide the most power for shooting.",
            "4-2": "Corners are hardest for goalkeepers to reach.",
            "4-3": "Inside foot offers better accuracy for placement.",
            "4-4": "Maintaining balance while moving is key.",
            "4-5": "Locked ankle and follow-through generate power.",
            // Module 5
            "5-1": "Cone sprints develop explosive speed.",
            "5-2": "Midfielders connect defense and attack.",
            "5-3": "Tactical awareness means understanding positioning.",
            "5-4": "Endurance helps maintain performance throughout the game.",
            "5-5": "Good spacing creates passing options."
        };
        
        // User answers storage
        const userAnswers = {};
        
        // Quiz submission status
        const quizSubmitted = {};
        
        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            // Set up all quizzes
            for (let moduleNum = 1; moduleNum <= 5; moduleNum++) {
                setupQuiz(moduleNum);
            }
            
            // Set up module buttons to toggle content
            for (const [moduleNum, button] of Object.entries(moduleButtons)) {
                button.addEventListener('click', function() {
                    if (this.classList.contains('locked')) return;
                    
                    // Hide all modules
                    for (const container of Object.values(moduleContainers)) {
                        container.classList.remove('active');
                    }
                    
                    // Show selected module
                    moduleContainers[moduleNum].classList.add('active');
                });
            }
            
            // Open first module by default
            moduleContainers[1].classList.add('active');
            
            // Load progress from localStorage if available
            loadProgress();
        });
        
        // Set up a quiz for a specific module
        function setupQuiz(moduleNum) {
            quizSubmitted[moduleNum] = false;
            
            // Set up option click handlers
            document.querySelectorAll(`#quiz${moduleNum} .option`).forEach(option => {
                option.addEventListener('click', function() {
                    if (quizSubmitted[moduleNum]) return;
                    
                    const questionId = `${moduleNum}-${this.getAttribute('data-question')}`;
                    const answer = this.getAttribute('data-answer');
                    
                    // Remove selected class from all options in this question
                    document.querySelectorAll(`[data-question="${this.getAttribute('data-question')}"]`).forEach(opt => {
                        opt.classList.remove('selected');
                    });
                    
                    // Add selected class to clicked option
                    this.classList.add('selected');
                    
                    // Store user's answer
                    userAnswers[questionId] = answer;
                    
                    // Enable submit button if all questions are answered
                    checkAllAnswered(moduleNum);
                });
            });
            
            // Set up submit button
            const submitBtn = document.getElementById(`submit-btn${moduleNum}`);
            submitBtn.addEventListener('click', function() {
                if (quizSubmitted[moduleNum]) return;
                
                submitQuiz(moduleNum);
            });
            
            // Set up try again button if it exists
            const tryAgainBtn = document.getElementById(`try-again-btn${moduleNum}`);
            if (tryAgainBtn) {
                tryAgainBtn.addEventListener('click', function() {
                    resetQuiz(moduleNum);
                });
            }
        }
        
        // Check if all questions in a quiz are answered
        function checkAllAnswered(moduleNum) {
            let allAnswered = true;
            for (let q = 1; q <= 5; q++) {
                if (!userAnswers[`${moduleNum}-${q}`]) {
                    allAnswered = false;
                    break;
                }
            }
            
            document.getElementById(`submit-btn${moduleNum}`).disabled = !allAnswered;
        }
        
        // Submit a quiz
        function submitQuiz(moduleNum) {
            quizSubmitted[moduleNum] = true;
            let score = 0;
            
            // Check each answer
            for (let q = 1; q <= 5; q++) {
                const questionId = `${moduleNum}-${q}`;
                const feedback = document.getElementById(`feedback${questionId}`);
                feedback.classList.remove('hidden');
                
                if (userAnswers[questionId] === correctAnswers[questionId]) {
                    feedback.innerHTML = "✅ Correct! <div class='explanation'>" + explanations[questionId] + "</div>";
                    feedback.className = "feedback correct";
                    score++;
                } else {
                    feedback.innerHTML = `❌ Incorrect! The correct answer is ${getCorrectAnswerText(questionId)} <div class='explanation'>${explanations[questionId]}</div>`;
                    feedback.className = "feedback incorrect";
                }
            }
            
            // Show result
            const result = document.getElementById(`result${moduleNum}`);
            result.classList.remove('hidden');
            result.textContent = `You scored ${score} out of 5! ${getResultMessage(moduleNum, score)}`;
            
            // Disable options
            document.querySelectorAll(`#quiz${moduleNum} .option`).forEach(option => {
                option.style.cursor = 'default';
                option.style.pointerEvents = 'none';
            });
            
            // Disable submit button
            document.getElementById(`submit-btn${moduleNum}`).disabled = true;
            
            // Handle module completion
            if (score >= 3) {
                moduleStatus[moduleNum] = true;
                
                // Unlock next module if not the last one
                if (moduleNum < 5) {
                    unlockModule(moduleNum + 1);
                }
                
                // Show try again button if score < 5
                if (score < 5 && moduleNum < 5) {
                    document.getElementById(`try-again${moduleNum}`).classList.remove('hidden');
                }
            } else {
                // Show try again button for failed attempts
                document.getElementById(`try-again${moduleNum}`).classList.remove('hidden');
            }
            
            // Update progress
            updateProgress();
        }
        
        // Reset a quiz
        function resetQuiz(moduleNum) {
            // Clear user answers for this module
            for (let q = 1; q <= 5; q++) {
                delete userAnswers[`${moduleNum}-${q}`];
            }
            
            // Reset UI
            document.querySelectorAll(`#quiz${moduleNum} .option`).forEach(option => {
                option.classList.remove('selected');
                option.style.cursor = 'pointer';
                option.style.pointerEvents = 'auto';
            });
            
            document.querySelectorAll(`#quiz${moduleNum} .feedback`).forEach(feedback => {
                feedback.classList.add('hidden');
                feedback.textContent = '';
            });
            
            document.getElementById(`result${moduleNum}`).classList.add('hidden');
            document.getElementById(`try-again${moduleNum}`).classList.add('hidden');
            document.getElementById(`submit-btn${moduleNum}`).disabled = true;
            
            quizSubmitted[moduleNum] = false;
        }
        
        // Get the text of the correct answer
        function getCorrectAnswerText(questionId) {
            const [moduleNum, qNum] = questionId.split('-');
            const question = document.getElementById(`q${moduleNum}-${qNum}`);
            const options = question.querySelectorAll('.option');
            
            for (let option of options) {
                if (option.getAttribute('data-answer') === correctAnswers[questionId]) {
                    return option.textContent;
                }
            }
            return '';
        }
        
        // Get result message based on score
        function getResultMessage(moduleNum, score) {
            const messages = {
                1: {
                    5: "Perfect score! You're a football expert! ⭐",
                    3: "Good job! You understand football well! 👍",
                    default: "Nice try! Keep learning and you'll improve! 💪"
                },
                2: {
                    5: "Perfect score! You're a dribbling star! ⭐",
                    3: "Good job! You understand ball control well! 👍",
                    default: "Nice try! Keep practicing your skills! 💪"
                },
                3: {
                    5: "Perfect score! You're a passing pro! ⭐",
                    3: "Great job! You understand passing well! 👍",
                    default: "Nice try! Keep practicing your skills! 💪"
                },
                4: {
                    5: "Perfect score! You're a shooting star! ⭐",
                    3: "Great job! You'll be scoring in no time! 👍",
                    default: "Keep practicing! Review the techniques to improve your shooting! 💪"
                },
                5: {
                    5: "Perfect score! You're a teamwork champion! ⭐",
                    3: "Great job! You understand football teamwork! 👍",
                    default: "Keep learning! Review the material to improve! 💪"
                }
            };
            
            if (score === 5) return messages[moduleNum][5];
            if (score >= 3) return messages[moduleNum][3];
            return messages[moduleNum].default;
        }
        
        // Unlock a module
        function unlockModule(moduleNum) {
            moduleButtons[moduleNum].classList.remove('locked');
            progressIndicators[moduleNum].classList.add('completed');
            
            // Show success message
            alert(`Congratulations! You've unlocked Module ${moduleNum}!`);
        }
        
        // Update progress bar and save to localStorage
        function updateProgress() {
            let completed = 0;
            for (let i = 1; i <= 5; i++) {
                if (moduleStatus[i]) completed++;
            }
            
            // Save to localStorage
            localStorage.setItem('footballTrainingProgress', JSON.stringify({
                moduleStatus: moduleStatus,
                userAnswers: userAnswers
            }));
        }
        
        // Load progress from localStorage
        function loadProgress() {
            const savedProgress = localStorage.getItem('footballTrainingProgress');
            if (savedProgress) {
                const progress = JSON.parse(savedProgress);
                
                // Restore module status
                for (let i = 1; i <= 5; i++) {
                    if (progress.moduleStatus[i]) {
                        moduleStatus[i] = true;
                        
                        // Unlock module
                        moduleButtons[i].classList.remove('locked');
                        progressIndicators[i].classList.add('completed');
                    }
                }
                
                // Restore user answers
                Object.assign(userAnswers, progress.userAnswers);
                
                // Update UI for answered questions
                for (const questionId in userAnswers) {
                    const [moduleNum, qNum] = questionId.split('-');
                    const option = document.querySelector(`#quiz${moduleNum} .option[data-question="${qNum}"][data-answer="${userAnswers[questionId]}"]`);
                    if (option) {
                        option.classList.add('selected');
                    }
                }
            }
        }
    </script>
</body>
</html>