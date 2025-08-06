<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: signin.php');
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM player_registrations WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$registration = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$registration || $registration['registration_status'] !== 'approved') {
    header('Location: dashboard.php');
    exit();
}

function calculateAge($day, $month, $year) {
    $birth_date = new DateTime("$year-$month-$day");
    $today = new DateTime();
    return $today->diff($birth_date)->y;
}

$age = calculateAge($registration['birth_day'], $registration['birth_month'], $registration['birth_year']);
if ($age < 6 || $age > 8) {
    header('Location: courses.php');
    exit();
}

$query = "SELECT cm.*, pp.progress_percentage, pp.is_completed, pp.completed_at, pp.quiz_score
          FROM course_modules cm 
          LEFT JOIN player_progress pp ON cm.id = pp.module_id AND pp.user_id = ?
          WHERE cm.age_group = '6-8'
          ORDER BY cm.module_number";
$stmt = $pdo->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_module'])) {
    $module_id = filter_input(INPUT_POST, 'module_id', FILTER_VALIDATE_INT);
    $quiz_score = filter_input(INPUT_POST, 'quiz_score', FILTER_VALIDATE_INT) ?? 0;
    
    if ($module_id === false) {
        $_SESSION['error'] = 'Invalid module ID';
        header('Location: courses_age_6-8.php');
        exit();
    }

    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("SELECT id FROM player_progress WHERE user_id = ? AND module_id = ?");
        $stmt->execute([$_SESSION['user_id'], $module_id]);
        
        if ($existing = $stmt->fetch()) {
            $query = "UPDATE player_progress SET progress_percentage = 100, is_completed = TRUE, 
                      completed_at = NOW(), quiz_score = ? WHERE id = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$quiz_score, $existing['id']]);
        } else {
            $query = "INSERT INTO player_progress (user_id, module_id, progress_percentage, is_completed, 
                      completed_at, quiz_score) VALUES (?, ?, 100, TRUE, NOW(), ?)";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$_SESSION['user_id'], $module_id, $quiz_score]);
        }
        
        $notification_msg = "Congratulations! You've completed module #$module_id.";
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], 'Module Completed', $notification_msg, 'success']);
        
        $pdo->commit();
        $_SESSION['success'] = 'Module completed successfully!';
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = 'Error completing module: ' . $e->getMessage();
    }
    
    header('Location: courses_age_6-8.php');
    exit();
}

$module_colors = [
    1 => '#2e7d32',
    2 => '#1c7ed6',
    3 => '#228b22',
    4 => '#b22222',
    5 => '#1565c0'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses (Age 6-8) - Ethiopian Football Scouting</title>
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
            --module1-color: <?= $module_colors[1] ?>;
            --module2-color: <?= $module_colors[2] ?>;
            --module3-color: <?= $module_colors[3] ?>;
            --module4-color: <?= $module_colors[4] ?>;
            --module5-color: <?= $module_colors[5] ?>;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
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
        
        #module1-container .submit-btn:hover { background-color: #1b5e20; }
        #module2-container .submit-btn:hover { background-color: #0b5ed7; }
        #module3-container .submit-btn:hover { background-color: #1a7f1a; }
        #module4-container .submit-btn:hover { background-color: #9a1c1c; }
        #module5-container .submit-btn:hover { background-color: #0d5cb6; }
        
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

    <h1>⚽ Football Training Program (Age 6-8)</h1>
    
    <div class="progress-tracker">
        Progress: 
        <span id="module1-progress" class="completed">1</span>
        <span id="module2-progress">2</span>
        <span id="module3-progress">3</span>
        <span id="module4-progress">4</span>
        <span id="module5-progress">5</span>
    </div>
    
    <div class="module-selector">
        <button id="module1-btn" class="module-btn">Module 1: Introduction to Football</button>
        <button id="module2-btn" class="module-btn locked">Module 2: Ball Control & Dribbling</button>
        <button id="module3-btn" class="module-btn locked">Module 3: Passing & Receiving</button>
        <button id="module4-btn" class="module-btn locked">Module 4: Shooting & Scoring</button>
        <button id="module5-btn" class="module-btn locked">Module 5: Fitness & Team Play</button>
    </div>
    
    <!-- Module 1 Container -->
    <div id="module1-container" class="module-container active">
        <h2 class="module-title">1. Introduction to Football</h2>
        
        <div class="lesson-section">
            <h3>1. What is Football?</h3>
            <p><strong>Definition:</strong> Football (also called soccer) is a game played with a ball and two teams. Each team has 11 players on the field. The main goal is to kick the ball into the other team's goal to score a point. Each point is called a goal.</p>
            
            <div class="key-points">
                <h4>Key Points:</h4>
                <ul>
                    <li><span class="fun-icon">🚫</span> You cannot use your hands to touch the ball (only the goalkeeper can).</li>
                    <li><span class="fun-icon">⚽</span> You use your feet to kick, your head to bounce, and your body to stop the ball.</li>
                    <li><span class="fun-icon">🥇</span> The team that scores the most goals wins the game.</li>
                </ul>
            </div>
        </div>
        
        <div class="lesson-section">
            <h3>2. Basic Rules of Football</h3>
            <p><strong>Definition:</strong> Football has a few simple rules that help everyone play fair and have fun.</p>
            
            <div class="key-points">
                <h4>Key Rules:</h4>
                <ul>
                    <li><span class="fun-icon">✋</span> <strong>No Hands!</strong> - Players are not allowed to touch the ball with their hands or arms.</li>
                    <li><span class="fun-icon">🚧</span> <strong>Out of Bounds</strong> - If the ball goes outside the white lines on the sides or end of the field, it is out of bounds.</li>
                    <li><span class="fun-icon">🥅</span> <strong>Goals</strong> - You score a goal by kicking the ball into the other team's net.</li>
                </ul>
            </div>
        </div>
        
        <div class="video-container">
            <h3>Watch and Learn: Football Basics!</h3>
            <iframe width="560" height="315" src="https://www.youtube.com/embed/qknP-E-vPQ4" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
            <p><em>Fun introduction to football for young players</em></p>
        </div>
        
        <div class="quiz-container">
            <h3>🧠 Football Quiz</h3>
            <p>Test what you've learned about football! Select one answer for each question.</p>
            
            <div id="quiz1">
                <div class="question" id="q1-1">
                    <h4>1. Can you touch the ball with your hands when playing football?</h4>
                    <div class="option" data-question="1" data-answer="A">A. Yes</div>
                    <div class="option" data-question="1" data-answer="B">B. No</div>
                    <div class="option" data-question="1" data-answer="C">C. Only if you're fast</div>
                    <div class="feedback hidden" id="feedback1-1"></div>
                </div>
                
                <div class="question" id="q1-2">
                    <h4>2. What happens when the ball goes out of the field?</h4>
                    <div class="option" data-question="2" data-answer="A">A. The game stops forever</div>
                    <div class="option" data-question="2" data-answer="B">B. You get a point</div>
                    <div class="option" data-question="2" data-answer="C">C. The other team gets the ball</div>
                    <div class="feedback hidden" id="feedback1-2"></div>
                </div>
                
                <div class="question" id="q1-3">
                    <h4>3. What do we call it when the ball goes into the goal?</h4>
                    <div class="option" data-question="3" data-answer="A">A. A kick</div>
                    <div class="option" data-question="3" data-answer="B">B. A score</div>
                    <div class="option" data-question="3" data-answer="C">C. A goal</div>
                    <div class="feedback hidden" id="feedback1-3"></div>
                </div>
                
                <div class="question" id="q1-4">
                    <h4>4. Which game helps you stop the ball quickly when someone says "Red Light"?</h4>
                    <div class="option" data-question="4" data-answer="A">A. Hide and Seek</div>
                    <div class="option" data-question="4" data-answer="B">B. Red Light, Green Light</div>
                    <div class="option" data-question="4" data-answer="C">C. Freeze Dance</div>
                    <div class="feedback hidden" id="feedback1-4"></div>
                </div>
                
                <div class="question" id="q1-5">
                    <h4>5. What do cones help you practice?</h4>
                    <div class="option" data-question="5" data-answer="A">A. Throwing</div>
                    <div class="option" data-question="5" data-answer="B">B. Jumping</div>
                    <div class="option" data-question="5" data-answer="C">C. Dribbling around objects</div>
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
            <h3>1. Basic Dribbling with Feet</h3>
            <p><strong>Definition:</strong> This skill involves gently tapping the ball with the inside, outside, or sole of the foot to move it in a controlled way. It helps young players learn how to move the ball while keeping it close to their feet.</p>
            
            <div class="key-points">
                <h4>Key Techniques:</h4>
                <ul>
                    <li><span class="fun-icon">👟</span> Use both feet (not just dominant foot)</li>
                    <li><span class="fun-icon">👀</span> Keep the ball close (small touches)</li>
                    <li><span class="fun-icon">👆</span> Eyes up (not always on the ball)</li>
                </ul>
                
                <h4>Drill Idea:</h4>
                <p>"Dribble across the field using only your right foot, then return using only your left."</p>
            </div>
        </div>
        
        <div class="lesson-section">
            <h3>2. Simple Ball Stops and Starts</h3>
            <p><strong>Definition:</strong> Learning how to stop the ball (using the sole or side of the foot) and then start dribbling again. This teaches control and body balance.</p>
            
            <div class="key-points">
                <h4>Key Skills:</h4>
                <ul>
                    <li><span class="fun-icon">🛑</span> Stop the ball with the bottom of the foot (sole stop)</li>
                    <li><span class="fun-icon">⏱️</span> Pause, then move the ball in a new direction</li>
                    <li><span class="fun-icon">🤸</span> Helps in quick decision-making and body coordination</li>
                </ul>
                
                <h4>Drill Idea:</h4>
                <p>"Dribble for 5 steps, stop the ball completely, count to 3, then start dribbling again."</p>
            </div>
        </div>
        
        <div class="lesson-section">
            <h3>3. Fun Obstacle Dribbling Drills</h3>
            <p><strong>Definition:</strong> This involves dribbling the ball around cones, objects, or markers in a zigzag or pattern. It's a fun way to improve footwork, coordination, and control.</p>
            
            <div class="key-points">
                <h4>Key Benefits:</h4>
                <ul>
                    <li><span class="fun-icon">↩️</span> Change of direction</li>
                    <li><span class="fun-icon">🏃</span> Dribbling at different speeds</li>
                    <li><span class="fun-icon">👟👟</span> Using both feet around obstacles</li>
                </ul>
                
                <h4>Drill Idea:</h4>
                <p>"Set up 5 cones in a line and dribble through them without touching the cones."</p>
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
                    <h4>1. What is the main goal of dribbling with your feet?</h4>
                    <div class="option" data-question="1" data-answer="A">A. Kick the ball far</div>
                    <div class="option" data-question="1" data-answer="B">B. Move the ball with control</div>
                    <div class="option" data-question="1" data-answer="C">C. Stand still with the ball</div>
                    <div class="feedback hidden" id="feedback2-1"></div>
                </div>
                
                <div class="question" id="q2-2">
                    <h4>2. Which part of your foot can you use to stop the ball?</h4>
                    <div class="option" data-question="2" data-answer="A">A. Elbow</div>
                    <div class="option" data-question="2" data-answer="B">B. Sole</div>
                    <div class="option" data-question="2" data-answer="C">C. Head</div>
                    <div class="feedback hidden" id="feedback2-2"></div>
                </div>
                
                <div class="question" id="q2-3">
                    <h4>3. When doing obstacle dribbling, what should you avoid?</h4>
                    <div class="option" data-question="3" data-answer="A">A. Smiling</div>
                    <div class="option" data-question="3" data-answer="B">B. Running too fast</div>
                    <div class="option" data-question="3" data-answer="C">C. Kicking cones</div>
                    <div class="feedback hidden" id="feedback2-3"></div>
                </div>
                
                <div class="question" id="q2-4">
                    <h4>4. What should your eyes do while dribbling?</h4>
                    <div class="option" data-question="4" data-answer="A">A. Always look at the sky</div>
                    <div class="option" data-question="4" data-answer="B">B. Look at the coach</div>
                    <div class="option" data-question="4" data-answer="C">C. Look up often to see around</div>
                    <div class="feedback hidden" id="feedback2-4"></div>
                </div>
                
                <div class="question" id="q2-5">
                    <h4>5. Why is it good to use both feet while dribbling?</h4>
                    <div class="option" data-question="5" data-answer="A">A. To confuse your coach</div>
                    <div class="option" data-question="5" data-answer="B">B. To balance and control better</div>
                    <div class="option" data-question="5" data-answer="C">C. Because it looks funny</div>
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
            <h3>1. Simple Short Passes with Inside Foot</h3>
            <p><strong>Definition:</strong> This skill teaches players how to make short and accurate passes to teammates using the inside part of their foot — the most controlled and reliable way to pass.</p>
            
            <div class="key-points">
                <h4>Key Techniques:</h4>
                <ul>
                    <li><span class="fun-icon">👣</span> Plant foot next to the ball</li>
                    <li><span class="fun-icon">🔄</span> Swing kicking leg gently</li>
                    <li><span class="fun-icon">⚽</span> Hit the middle of the ball with the inside of the foot</li>
                    <li><span class="fun-icon">🎯</span> Follow through toward the target</li>
                </ul>
                
                <h4>Drill Idea:</h4>
                <p>"Stand 5 steps apart with a partner and pass the ball back and forth 10 times without missing."</p>
            </div>
        </div>
        
        <div class="lesson-section">
            <h3>2. Basic Receiving and Controlling the Ball</h3>
            <p><strong>Definition:</strong> Receiving is stopping or slowing down the ball when it's passed to you. Controlling means keeping it close so you can make your next move.</p>
            
            <div class="key-points">
                <h4>Key Skills:</h4>
                <ul>
                    <li><span class="fun-icon">👟</span> Use the inside of the foot to receive</li>
                    <li><span class="fun-icon">👀</span> Keep eyes on the ball</li>
                    <li><span class="fun-icon">🛑</span> Cushion the ball by slightly pulling back when it touches the foot</li>
                    <li><span class="fun-icon">✋</span> Keep the ball close after receiving</li>
                </ul>
                
                <h4>Drill Idea:</h4>
                <p>"Your partner rolls the ball to you. You stop it with the inside of your foot and freeze."</p>
            </div>
        </div>
        
        <div class="lesson-section">
            <h3>3. Passing Games in Pairs</h3>
            <p><strong>Definition:</strong> Fun and simple games that involve two players working together to pass and receive the ball, helping develop timing, teamwork, and accuracy.</p>
            
            <div class="key-points">
                <h4>Key Benefits:</h4>
                <ul>
                    <li><span class="fun-icon">🗣️</span> Communication between players</li>
                    <li><span class="fun-icon">🔄</span> Taking turns passing and receiving</li>
                    <li><span class="fun-icon">😄</span> Staying focused and having fun</li>
                </ul>
                
                <h4>Drill Idea:</h4>
                <p>"Pass & Move Game" – After passing, each player takes one step to the left or right before receiving the next pass.</p>
            </div>
        </div>
        
        <div class="video-container">
            <h3>Watch and Learn: Passing Skills!</h3>
            <iframe width="560" height="315" src="https://www.youtube.com/embed/3H8jYgQfN4Y" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
            <p><em>Fun passing exercises for young players</em></p>
        </div>
        
        <div class="quiz-container">
            <h3>🧠 Passing & Receiving Quiz</h3>
            <p>Test what you've learned about passing and controlling the ball! Select one answer for each question.</p>
            
            <div id="quiz3">
                <div class="question" id="q3-1">
                    <h4>1. What part of the foot is best for short passes?</h4>
                    <div class="option" data-question="1" data-answer="A">A. Toes</div>
                    <div class="option" data-question="1" data-answer="B">B. Inside of the foot</div>
                    <div class="option" data-question="1" data-answer="C">C. Heel</div>
                    <div class="feedback hidden" id="feedback3-1"></div>
                </div>
                
                <div class="question" id="q3-2">
                    <h4>2. When receiving a ball, what should you do with your foot?</h4>
                    <div class="option" data-question="2" data-answer="A">A. Kick the ball hard</div>
                    <div class="option" data-question="2" data-answer="B">B. Run away</div>
                    <div class="option" data-question="2" data-answer="C">C. Stop the ball gently</div>
                    <div class="feedback hidden" id="feedback3-2"></div>
                </div>
                
                <div class="question" id="q3-3">
                    <h4>3. Why is passing important in football?</h4>
                    <div class="option" data-question="3" data-answer="A">A. To score goals alone</div>
                    <div class="option" data-question="3" data-answer="B">B. To share the ball with teammates</div>
                    <div class="option" data-question="3" data-answer="C">C. To waste time</div>
                    <div class="feedback hidden" id="feedback3-3"></div>
                </div>
                
                <div class="question" id="q3-4">
                    <h4>4. When passing with a partner, what should you do after you pass?</h4>
                    <div class="option" data-question="4" data-answer="A">A. Lie down</div>
                    <div class="option" data-question="4" data-answer="B">B. Look away</div>
                    <div class="option" data-question="4" data-answer="C">C. Move and get ready to receive</div>
                    <div class="feedback hidden" id="feedback3-4"></div>
                </div>
                
                <div class="question" id="q3-5">
                    <h4>5. What makes a passing game fun and helpful?</h4>
                    <div class="option" data-question="5" data-answer="A">A. Ignoring your partner</div>
                    <div class="option" data-question="5" data-answer="B">B. Teamwork and accuracy</div>
                    <div class="option" data-question="5" data-answer="C">C. Kicking very hard only</div>
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
            <h3>1. Basic Shooting Techniques with Inside Foot</h3>
            <p><strong>Definition:</strong> This teaches children to shoot the ball using the inside of the foot for better control and accuracy, rather than just power.</p>
            
            <div class="key-points">
                <h4>Key Techniques:</h4>
                <ul>
                    <li><span class="fun-icon">👟</span> Use the inside of the foot for controlled shots</li>
                    <li><span class="fun-icon">👣</span> Plant the non-kicking foot beside the ball</li>
                    <li><span class="fun-icon">👀</span> Eyes on the ball while shooting</li>
                    <li><span class="fun-icon">🎯</span> Follow through toward the target</li>
                </ul>
                
                <h4>Drill Idea:</h4>
                <p>"Place the ball still and try to shoot it into a small cone goal using the inside of your foot."</p>
            </div>
        </div>
        
        <div class="lesson-section">
            <h3>2. Target Practice (Shooting at Goals)</h3>
            <p><strong>Definition:</strong> Players aim to shoot at specific targets (like cones, corners of the net, or small goals) to improve accuracy and goal awareness.</p>
            
            <div class="key-points">
                <h4>Key Skills:</h4>
                <ul>
                    <li><span class="fun-icon">🎯</span> Aiming for specific parts of the goal</li>
                    <li><span class="fun-icon">💪</span> Controlling the power of the shot</li>
                    <li><span class="fun-icon">⏱️</span> Timing and balance</li>
                </ul>
                
                <h4>Drill Idea:</h4>
                <p>"Set up 4 cones in a goal - shoot and try to hit each cone one by one."</p>
            </div>
        </div>
        
        <div class="lesson-section">
            <h3>3. Fun Shooting Games</h3>
            <p><strong>Definition:</strong> Creative and playful drills that involve shooting at goals with challenges (e.g., points, turns, or small-sided play) to make learning fun.</p>
            
            <div class="key-points">
                <h4>Key Benefits:</h4>
                <ul>
                    <li><span class="fun-icon">😄</span> Enjoyment and motivation</li>
                    <li><span class="fun-icon">🏆</span> Competition in a friendly way</li>
                    <li><span class="fun-icon">⚡</span> Practicing under light pressure</li>
                </ul>
                
                <h4>Drill Idea:</h4>
                <p>"Shoot and Score Race" - Two players race to score goals from a set distance; first to score 3 wins.</p>
            </div>
        </div>
        
        <div class="video-container">
            <h3>Watch and Learn: Shooting Skills!</h3>
            <iframe width="560" height="315" src="https://www.youtube.com/embed/fA6mpexcy4M" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
            <p><em>Fun shooting exercises for young players</em></p>
        </div>
        
        <div class="quiz-container">
            <h3>🧠 Shooting & Scoring Quiz</h3>
            <p>Test your knowledge of shooting techniques and scoring strategies.</p>
            
            <div id="quiz4">
                <div class="question" id="q4-1">
                    <h4>1. What part of the foot should you use for accurate shooting?</h4>
                    <div class="option" data-question="1" data-answer="A">A. Heel</div>
                    <div class="option" data-question="1" data-answer="B">B. Toes</div>
                    <div class="option" data-question="1" data-answer="C">C. Inside of the foot</div>
                    <div class="feedback hidden" id="feedback4-1"></div>
                </div>
                
                <div class="question" id="q4-2">
                    <h4>2. Where should your plant (non-kicking) foot be when shooting?</h4>
                    <div class="option" data-question="2" data-answer="A">A. Behind the ball</div>
                    <div class="option" data-question="2" data-answer="B">B. Next to the ball</div>
                    <div class="option" data-question="2" data-answer="C">C. On top of the ball</div>
                    <div class="feedback hidden" id="feedback4-2"></div>
                </div>
                
                <div class="question" id="q4-3">
                    <h4>3. Why do we practice shooting at targets?</h4>
                    <div class="option" data-question="3" data-answer="A">A. To break the ball</div>
                    <div class="option" data-question="3" data-answer="B">B. To aim better and score more</div>
                    <div class="option" data-question="3" data-answer="C">C. To tire the goalie</div>
                    <div class="feedback hidden" id="feedback4-3"></div>
                </div>
                
                <div class="question" id="q4-4">
                    <h4>4. What makes shooting games fun and helpful?</h4>
                    <div class="option" data-question="4" data-answer="A">A. Playing alone</div>
                    <div class="option" data-question="4" data-answer="B">B. Competing with friends</div>
                    <div class="option" data-question="4" data-answer="C">C. Sitting during drills</div>
                    <div class="feedback hidden" id="feedback4-4"></div>
                </div>
                
                <div class="question" id="q4-5">
                    <h4>5. What should you look at when you kick the ball to shoot?</h4>
                    <div class="option" data-question="5" data-answer="A">A. The sky</div>
                    <div class="option" data-question="5" data-answer="B">B. The crowd</div>
                    <div class="option" data-question="5" data-answer="C">C. The ball</div>
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
            <h3>1. Basic Warm-Ups and Stretches</h3>
            <p><strong>Definition:</strong> Warm-ups and stretches help prepare young players' bodies for movement. They prevent injuries and make muscles ready for action.</p>
            
            <div class="key-points">
                <h4>Key Activities:</h4>
                <ul>
                    <li><span class="fun-icon">🏃‍♂️</span> Light jogging or skipping to warm up</li>
                    <li><span class="fun-icon">🤸</span> Gentle stretching of legs, arms, and back</li>
                    <li><span class="fun-icon">🔄</span> Moving all parts of the body slowly and safely</li>
                </ul>
                
                <h4>Drill Idea:</h4>
                <p>"Jog in a circle, then stretch like a star — arms and legs wide, then touch your toes."</p>
            </div>
        </div>
        
        <div class="lesson-section">
            <h3>2. Fun Fitness Games to Improve Stamina</h3>
            <p><strong>Definition:</strong> These games help children build stamina (energy to play longer) while having fun — like tag games, relay races, or obstacle runs.</p>
            
            <div class="key-points">
                <h4>Key Benefits:</h4>
                <ul>
                    <li><span class="fun-icon">🏃</span> Running, jumping, and quick movement</li>
                    <li><span class="fun-icon">😄</span> Fun and excitement instead of pressure</li>
                    <li><span class="fun-icon">⏱️</span> Learning to stay active for longer</li>
                </ul>
                
                <h4>Drill Idea:</h4>
                <p>"Red Light, Green Light" – Players run on "green", freeze on "red", building speed control and endurance.</p>
            </div>
        </div>
        
        <div class="lesson-section">
            <h3>3. Introduction to Playing as a Team</h3>
            <p><strong>Definition:</strong> Teaching young players how to work together, pass, support, and communicate on the field — even if they are still learning the rules.</p>
            
            <div class="key-points">
                <h4>Key Concepts:</h4>
                <ul>
                    <li><span class="fun-icon">🔄</span> Sharing the ball</li>
                    <li><span class="fun-icon">👥</span> Taking turns</li>
                    <li><span class="fun-icon">👏</span> Encouraging teammates</li>
                    <li><span class="fun-icon">📍</span> Learning positions and roles lightly</li>
                </ul>
                
                <h4>Drill Idea:</h4>
                <p>"Pass the ball in a circle. If someone drops it, cheer and pass again."</p>
            </div>
        </div>
        
        <div class="video-container">
            <h3>Watch and Learn: Teamwork in Football!</h3>
            <iframe width="560" height="315" src="https://www.youtube.com/embed/dF7wNjsRjEo" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
            <p><em>Fun teamwork exercises for young players</em></p>
        </div>
        
        <div class="quiz-container">
            <h3>🧠 Fitness & Team Play Quiz</h3>
            <p>Test your knowledge of football fitness and teamwork concepts.</p>
            
            <div id="quiz5">
                <div class="question" id="q5-1">
                    <h4>1. Why do we stretch before playing?</h4>
                    <div class="option" data-question="1" data-answer="A">A. To fall asleep</div>
                    <div class="option" data-question="1" data-answer="B">B. To warm up our muscles and prevent injury</div>
                    <div class="option" data-question="1" data-answer="C">C. To get tired</div>
                    <div class="feedback hidden" id="feedback5-1"></div>
                </div>
                
                <div class="question" id="q5-2">
                    <h4>2. Which of these is a fun fitness game?</h4>
                    <div class="option" data-question="2" data-answer="A">A. Sleeping race</div>
                    <div class="option" data-question="2" data-answer="B">B. Red Light, Green Light</div>
                    <div class="option" data-question="2" data-answer="C">C. Sitting contest</div>
                    <div class="feedback hidden" id="feedback5-2"></div>
                </div>
                
                <div class="question" id="q5-3">
                    <h4>3. What does stamina help you do?</h4>
                    <div class="option" data-question="3" data-answer="A">A. Play for longer without getting tired</div>
                    <div class="option" data-question="3" data-answer="B">B. Eat more snacks</div>
                    <div class="option" data-question="3" data-answer="C">C. Yell louder</div>
                    <div class="feedback hidden" id="feedback5-3"></div>
                </div>
                
                <div class="question" id="q5-4">
                    <h4>4. Why is playing as a team important?</h4>
                    <div class="option" data-question="4" data-answer="A">A. So you don't have to pass</div>
                    <div class="option" data-question="4" data-answer="B">B. To help each other and win together</div>
                    <div class="option" data-question="4" data-answer="C">C. To play alone</div>
                    <div class="feedback hidden" id="feedback5-4"></div>
                </div>
                
                <div class="question" id="q5-5">
                    <h4>5. What should you do when a teammate makes a mistake?</h4>
                    <div class="option" data-question="5" data-answer="A">A. Laugh</div>
                    <div class="option" data-question="5" data-answer="B">B. Yell at them</div>
                    <div class="option" data-question="5" data-answer="C">C. Cheer them on and help</div>
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
            "1-1": "B",
            "1-2": "C",
            "1-3": "C",
            "1-4": "B",
            "1-5": "C",
            // Module 2
            "2-1": "B",
            "2-2": "B",
            "2-3": "C",
            "2-4": "C",
            "2-5": "B",
            // Module 3
            "3-1": "B",
            "3-2": "C",
            "3-3": "B",
            "3-4": "C",
            "3-5": "B",
            // Module 4
            "4-1": "C",
            "4-2": "B",
            "4-3": "B",
            "4-4": "B",
            "4-5": "C",
            // Module 5
            "5-1": "B",
            "5-2": "B",
            "5-3": "A",
            "5-4": "B",
            "5-5": "C"
        };
        
        // Explanations for each question
        const explanations = {
            // Module 1
            "1-1": "Only the goalkeeper can use their hands, and only in their special area.",
            "1-2": "When the ball goes out, the other team gets to throw or kick it back in.",
            "1-3": "When the ball goes into the net, it's called a goal!",
            "1-4": "Red Light, Green Light helps you practice stopping the ball quickly.",
            "1-5": "Cones help you practice dribbling around objects on the field.",
            // Module 2
            "2-1": "Dribbling means moving the ball with control, not just kicking it far.",
            "2-2": "The sole (bottom) of your foot is great for stopping the ball.",
            "2-3": "Try to dribble around cones without knocking them over.",
            "2-4": "Looking up helps you see where you're going and where teammates are.",
            "2-5": "Using both feet makes you a better, more balanced player.",
            // Module 3
            "3-1": "The inside of your foot gives you the most control for short passes.",
            "3-2": "Stopping the ball gently helps you keep control for your next move.",
            "3-3": "Passing helps your team work together to score goals.",
            "3-4": "After passing, get ready to receive the ball back from your teammate.",
            "3-5": "Passing games are fun when you work together and pass accurately.",
            // Module 4
            "4-1": "The inside of your foot gives you the best control for accurate shots.",
            "4-2": "Your plant foot should be beside the ball for balance when shooting.",
            "4-3": "Shooting at targets helps you aim better during real games.",
            "4-4": "Friendly competition with friends makes shooting practice more fun.",
            "4-5": "Looking at the ball helps you kick it where you want it to go.",
            // Module 5
            "5-1": "Stretching warms up muscles and helps prevent injuries.",
            "5-2": "Red Light, Green Light is a fun game that also builds fitness.",
            "5-3": "Stamina helps you play longer without getting too tired.",
            "5-4": "Football is a team sport - working together helps you win.",
            "5-5": "Encouraging teammates makes the game more fun for everyone."
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