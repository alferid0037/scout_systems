
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
    
    header('Location: courses age 12-14.php');
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

    <h1>⚽ Advanced Football Training Program (Age 12-14)</h1>
    
    <div class="progress-tracker">
        Progress: 
        <span id="module1-progress" class="completed">1</span>
        <span id="module2-progress">2</span>
        <span id="module3-progress">3</span>
        <span id="module4-progress">4</span>
        <span id="module5-progress">5</span>
    </div>
    
    <div class="module-selector">
        <button id="module1-btn" class="module-btn">Module 1: Advanced Rules & Game Understanding</button>
        <button id="module2-btn" class="module-btn locked">Module 2: Advanced Ball Control & Dribbling</button>
        <button id="module3-btn" class="module-btn locked">Module 3: Advanced Passing & Receiving</button>
        <button id="module4-btn" class="module-btn locked">Module 4: Advanced Shooting & Scoring</button>
        <button id="module5-btn" class="module-btn locked">Module 5: Advanced Fitness & Team Play</button>
    </div>
    
    <!-- Module 1 Container -->
    <div id="module1-container" class="module-container active">
        <h2 class="module-title">1. Advanced Rules & Game Understanding</h2>
        
        <div class="lesson-section">
            <h3>1. Offside Rule Explained</h3>
            <p><strong>Definition:</strong> The offside rule prevents attackers from gaining an unfair advantage by positioning themselves closer to the opponent's goal than both the ball and the second-last defender when the ball is played to them.</p>
            
            <div class="key-points">
                <h4>Key Rules to Cover:</h4>
                <ul>
                    <li><span class="fun-icon">🚩</span> <strong>Offside position:</strong> When any part of the head, body or feet is in the opponents' half and closer to the opponents' goal line than both the ball and the second-last opponent</li>
                    <li><span class="fun-icon">✋</span> <strong>Offside offense:</strong> Only penalized if the player is involved in active play by interfering with play, interfering with an opponent, or gaining an advantage</li>
                    <li><span class="fun-icon">🔄</span> <strong>Exceptions:</strong> Not offside if receiving the ball directly from a goal kick, corner kick, or throw-in</li>
                </ul>
                
                <h4>Why It Matters:</h4>
                <p>Understanding the offside rule helps players make better positioning decisions and avoid giving away free kicks.</p>
            </div>
        </div>
        
        <div class="lesson-section">
            <h3>2. Fouls and Disciplinary Actions</h3>
            <p><strong>Definition:</strong> Understanding different types of fouls and the resulting disciplinary actions (yellow and red cards) helps players play competitively while staying within the rules.</p>
            
            <div class="key-points">
                <h4>Focus Areas:</h4>
                <ul>
                    <li><span class="fun-icon">🟨</span> <strong>Yellow card offenses:</strong> Unsporting behavior, dissent, persistent infringement, delaying restart, etc.</li>
                    <li><span class="fun-icon">🟥</span> <strong>Red card offenses:</strong> Serious foul play, violent conduct, denying obvious goal-scoring opportunity, offensive language</li>
                    <li><span class="fun-icon">⚖️</span> <strong>Direct vs indirect free kicks:</strong> Understanding which fouls result in which type of free kick</li>
                </ul>
                
                <h4>Activity Example:</h4>
                <p>Video analysis session showing real match situations and discussing whether the referee made the correct disciplinary decision.</p>
            </div>
        </div>
        
        <div class="lesson-section">
            <h3>3. Referee Signals and Game Management</h3>
            <p><strong>Definition:</strong> Recognizing referee signals and understanding game management helps players respond appropriately during matches.</p>
            
            <div class="position-grid">
                <div class="position-card">
                    <h4><span class="fun-icon">✋</span> Indirect Free Kick</h4>
                    <p>Referee raises arm straight up until the ball is touched by another player</p>
                </div>
                <div class="position-card">
                    <h4><span class="fun-icon">👉</span> Direct Free Kick</h4>
                    <p>Referee points in the direction of the kick</p>
                </div>
                <div class="position-card">
                    <h4><span class="fun-icon">🟨</span> Yellow Card</h4>
                    <p>Referee shows a yellow card for caution</p>
                </div>
                <div class="position-card">
                    <h4><span class="fun-icon">🟥</span> Red Card</h4>
                    <p>Referee shows a red card for sending off</p>
                </div>
            </div>
            
            <div class="key-points">
                <h4>Drill Idea:</h4>
                <p>Have players act as referees and demonstrate signals while explaining the corresponding rules.</p>
            </div>
        </div>
        
        <div class="video-container">
            <h3>Watch and Learn: Advanced Rules Explained</h3>
            <iframe width="560" height="315" src="https://www.youtube.com/embed/dQw4w9WgXcQ" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
            <p><em>Advanced explanations of football rules and referee signals</em></p>
        </div>
        
        <div class="quiz-container">
            <h3>🧠 Advanced Rules Quiz</h3>
            <p>Test what you've learned about advanced rules! Select one answer for each question.</p>
            
            <div id="quiz1">
                <div class="question" id="q1-1">
                    <h4>1. When is a player in an offside position?</h4>
                    <div class="option" data-question="1" data-answer="A">A. When they are in their own half</div>
                    <div class="option" data-question="1" data-answer="B">B. When they are level with the second-last defender</div>
                    <div class="option" data-question="1" data-answer="C">C. When they are closer to the opponent's goal line than both the ball and the second-last defender</div>
                    <div class="option" data-question="1" data-answer="D">D. When they are behind the ball</div>
                    <div class="feedback hidden" id="feedback1-1"></div>
                </div>
                
                <div class="question" id="q1-2">
                    <h4>2. Which of these is NOT a yellow card offense?</h4>
                    <div class="option" data-question="2" data-answer="A">A. Unsporting behavior</div>
                    <div class="option" data-question="2" data-answer="B">B. Dissent by word or action</div>
                    <div class="option" data-question="2" data-answer="C">C. Serious foul play</div>
                    <div class="option" data-question="2" data-answer="D">D. Delaying the restart of play</div>
                    <div class="feedback hidden" id="feedback1-2"></div>
                </div>
                
                <div class="question" id="q1-3">
                    <h4>3. What does the referee signal with a raised arm for a free kick?</h4>
                    <div class="option" data-question="3" data-answer="A">A. Direct free kick</div>
                    <div class="option" data-question="3" data-answer="B">B. Indirect free kick</div>
                    <div class="option" data-question="3" data-answer="C">C. Penalty kick</div>
                    <div class="option" data-question="3" data-answer="D">D. Goal kick</div>
                    <div class="feedback hidden" id="feedback1-3"></div>
                </div>
                
                <div class="question" id="q1-4">
                    <h4>4. When is a player not penalized for being in an offside position?</h4>
                    <div class="option" data-question="4" data-answer="A">A. When receiving the ball from a throw-in</div>
                    <div class="option" data-question="4" data-answer="B">B. When receiving the ball from a corner kick</div>
                    <div class="option" data-question="4" data-answer="C">C. When receiving the ball from a goal kick</div>
                    <div class="option" data-question="4" data-answer="D">D. All of the above</div>
                    <div class="feedback hidden" id="feedback1-4"></div>
                </div>
                
                <div class="question" id="q1-5">
                    <h4>5. What is the minimum number of defenders (including goalkeeper) that must be between an attacker and the goal for the attacker to be onside?</h4>
                    <div class="option" data-question="5" data-answer="A">A. 1</div>
                    <div class="option" data-question="5" data-answer="B">B. 2</div>
                    <div class="option" data-question="5" data-answer="C">C. 3</div>
                    <div class="option" data-question="5" data-answer="D">D. 4</div>
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
        <h2 class="module-title">2. Advanced Ball Control & Dribbling</h2>
        
        <div class="lesson-section">
            <h3>1. Advanced Dribbling Techniques</h3>
            <p><strong>Definition:</strong> Mastering advanced dribbling moves like step-overs, feints, and quick changes of direction to beat defenders in 1v1 situations.</p>
            
            <div class="key-points">
                <h4>Key Techniques:</h4>
                <ul>
                    <li><span class="fun-icon">🔄</span> <strong>Step-over:</strong> Fake direction change by moving foot around the ball</li>
                    <li><span class="fun-icon">🤸</span> <strong>Body feint:</strong> Use upper body movement to deceive defender</li>
                    <li><span class="fun-icon">✂️</span> <strong>Scissors:</strong> Combination of step-overs in alternating directions</li>
                    <li><span class="fun-icon">⚡</span> <strong>Change of pace:</strong> Alternate between slow and explosive movements</li>
                </ul>
            </div>
            
            <div class="key-points">
                <h4>Why It Matters:</h4>
                <p>Advanced dribbling skills create scoring opportunities and help maintain possession under pressure.</p>
            </div>
            
            <div class="key-points">
                <h4>Drill Idea:</h4>
                <p>"Dribble through a series of cones using a different move at each marker, finishing with a shot on goal."</p>
            </div>
        </div>
        
        <div class="lesson-section">
            <h3>2. Shielding and Protecting the Ball</h3>
            <p><strong>Definition:</strong> Using your body to maintain possession when under pressure from defenders.</p>
            
            <div class="key-points">
                <h4>Key Skills:</h4>
                <ul>
                    <li><span class="fun-icon">🛡️</span> <strong>Body positioning:</strong> Keep body between defender and ball</li>
                    <li><span class="fun-icon">👀</span> <strong>Awareness:</strong> Know where pressure is coming from</li>
                    <li><span class="fun-icon">🦶</span> <strong>Ball placement:</strong> Keep ball on far foot from defender</li>
                    <li><span class="fun-icon">⚖️</span> <strong>Balance:</strong> Stay low with knees bent for stability</li>
                </ul>
            </div>
            
            <div class="key-points">
                <h4>Drill Idea:</h4>
                <p>"1v1 shielding game in a small circle - attacker tries to protect the ball for 10 seconds while defender applies pressure."</p>
            </div>
        </div>
        
        <div class="lesson-section">
            <h3>3. Dribbling in Tight Spaces</h3>
            <p><strong>Definition:</strong> Maintaining control and making quick decisions when space is limited.</p>
            
            <div class="key-points">
                <h4>Key Concepts:</h4>
                <ul>
                    <li><span class="fun-icon">🔍</span> <strong>Close control:</strong> Keep ball within playing distance</li>
                    <li><span class="fun-icon">🔄</span> <strong>Quick turns:</strong> Use Cruyff turns, drag-backs, and other turning moves</li>
                    <li><span class="fun-icon">👀</span> <strong>Head up:</strong> Scan for space and teammates while dribbling</li>
                    <li><span class="fun-icon">⚡</span> <strong>Acceleration:</span> Explode into space when it opens up</li>
                </ul>
            </div>
            
            <div class="video-container">
                <h3>Watch and Learn: Advanced Dribbling</h3>
                <iframe width="560" height="315" src="https://www.youtube.com/embed/dQw4w9WgXcQ" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                <p><em>Advanced dribbling techniques and drills</em></p>
            </div>
            
            <div class="key-points">
                <h4>Drill Idea:</h4>
                <p>"4v4 in a small grid with emphasis on close control and quick decision making."</p>
            </div>
        </div>
        
        <div class="quiz-container">
            <h3>🧠 Advanced Dribbling Quiz</h3>
            <p>Test your knowledge of advanced dribbling techniques! Select one answer for each question.</p>
            
            <div id="quiz2">
                <div class="question" id="q2-1">
                    <h4>1. What is the purpose of a step-over move?</h4>
                    <div class="option" data-question="1" data-answer="A">A. To kick the ball harder</div>
                    <div class="option" data-question="1" data-answer="B">B. To fake a direction change and deceive the defender</div>
                    <div class="option" data-question="1" data-answer="C">C. To pass the ball to a teammate</div>
                    <div class="option" data-question="1" data-answer="D">D. To stop the ball completely</div>
                    <div class="feedback hidden" id="feedback2-1"></div>
                </div>
                
                <div class="question" id="q2-2">
                    <h4>2. When shielding the ball, where should you position your body?</h4>
                    <div class="option" data-question="2" data-answer="A">A. Facing the defender</div>
                    <div class="option" data-question="2" data-answer="B">B. Between the defender and the ball</div>
                    <div class="option" data-question="2" data-answer="C">C. Away from the ball</div>
                    <div class="option" data-question="2" data-answer="D">D. Behind the defender</div>
                    <div class="feedback hidden" id="feedback2-2"></div>
                </div>
                
                <div class="question" id="q2-3">
                    <h4>3. What is the most important aspect of dribbling in tight spaces?</h4>
                    <div class="option" data-question="3" data-answer="A">A. Kicking the ball far ahead</div>
                    <div class="option" data-question="3" data-answer="B">B. Keeping the ball close to your feet</div>
                    <div class="option" data-question="3" data-answer="C">C. Looking only at the ball</div>
                    <div class="option" data-question="3" data-answer="D">D. Standing straight up</div>
                    <div class="feedback hidden" id="feedback2-3"></div>
                </div>
                
                <div class="question" id="q2-4">
                    <h4>4. What should you do immediately after successfully performing a dribbling move?</h4>
                    <div class="option" data-question="4" data-answer="A">A. Stop and celebrate</div>
                    <div class="option" data-question="4" data-answer="B">B. Accelerate into the created space</div>
                    <div class="option" data-question="4" data-answer="C">C. Pass the ball backward</div>
                    <div class="option" data-question="4" data-answer="D">D. Ask for a substitution</div>
                    <div class="feedback hidden" id="feedback2-4"></div>
                </div>
                
                <div class="question" id="q2-5">
                    <h4>5. Which of these is NOT an advanced dribbling technique?</h4>
                    <div class="option" data-question="5" data-answer="A">A. Step-over</div>
                    <div class="option" data-question="5" data-answer="B">B. Body feint</div>
                    <div class="option" data-question="5" data-answer="C">C. Simple pass</div>
                    <div class="option" data-question="5" data-answer="D">D. Scissors move</div>
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
        <h2 class="module-title">3. Advanced Passing & Receiving</h2>
        
        <div class="lesson-section">
            <h3>1. Passing Under Pressure</h3>
            <p><strong>Definition:</strong> Developing the ability to make accurate passes while being closely marked or rushed by opponents.</p>
            
            <div class="key-points">
                <h4>Key Techniques:</h4>
                <ul>
                    <li><span class="fun-icon">👀</span> <strong>Scanning:</strong> Check surroundings before receiving the ball</li>
                    <li><span class="fun-icon">⚡</span> <strong>Quick release:</strong> One or two touch passing when under pressure</li>
                    <li><span class="fun-icon">🦶</span> <strong>Body shape:</strong> Position yourself to receive and pass in one motion</li>
                    <li><span class="fun-icon">🎯</span> <strong>Weight of pass:</strong> Adjust power based on distance to teammate</li>
                </ul>
            </div>
            
            <div class="key-points">
                <h4>Why It Matters:</h4>
                <p>Maintaining possession under pressure is crucial for controlling the tempo of the game.</p>
            </div>
            
            <div class="key-points">
                <h4>Drill Idea:</h4>
                <p>"4v2 rondo in a small grid with emphasis on quick passing and movement."</p>
            </div>
        </div>
        
        <div class="lesson-section">
            <h3>2. Advanced Receiving Techniques</h3>
            <p><strong>Definition:</strong> Controlling the ball with different body parts while preparing for the next action.</p>
            
            <div class="key-points">
                <h4>Key Skills:</h4>
                <ul>
                    <li><span class="fun-icon">🦶</span> <strong>First touch direction:</strong> Guide the ball into space with first touch</li>
                    <li><span class="fun-icon">👕</span> <strong>Chest control:</strong> Cushion aerial balls with chest</li>
                    <li><span class="fun-icon">🦵</span> <strong>Thigh control:</strong> Control high balls with thigh</li>
                    <li><span class="fun-icon">👟</span> <strong>Outside foot control:</strong> Use outside foot to change direction quickly</li>
                </ul>
            </div>
            
            <div class="video-container">
                <h3>Watch and Learn: Advanced Receiving</h3>
                <iframe width="560" height="315" src="https://www.youtube.com/embed/dQw4w9WgXcQ" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                <p><em>Techniques for controlling difficult passes</em></p>
            </div>
            
            <div class="key-points">
                <h4>Drill Idea:</h4>
                <p>"Partner passing with varied service - ground balls, aerial balls, hard passes, etc."</p>
            </div>
        </div>
        
        <div class="lesson-section">
            <h3>3. Long Range Passing</h3>
            <p><strong>Definition:</strong> Developing the ability to switch play and deliver accurate long passes.</p>
            
            <div class="key-points">
                <h4>Key Concepts:</h4>
                <ul>
                    <li><span class="fun-icon">👟</span> <strong>Technique:</strong> Use laces for power, inside foot for accuracy</li>
                    <li><span class="fun-icon">🌬️</span> <strong>Weight of pass:</strong> Adjust power based on distance</li>
                    <li><span class="fun-icon">👀</span> <strong>Vision:</strong> Spot opportunities to switch play</li>
                    <li><span class="fun-icon">🔄</span> <strong>Curve:</strong> Add bend to bypass defenders</li>
                </ul>
            </div>
            
            <div class="key-points">
                <h4>Drill Idea:</h4>
                <p>"Long pass accuracy challenge - players attempt to hit targets at varying distances."</p>
            </div>
        </div>
        
        <div class="quiz-container">
            <h3>🧠 Advanced Passing Quiz</h3>
            <p>Test your knowledge of advanced passing techniques! Select one answer for each question.</p>
            
            <div id="quiz3">
                <div class="question" id="q3-1">
                    <h4>1. What should you do before receiving a pass when under pressure?</h4>
                    <div class="option" data-question="1" data-answer="A">A. Close your eyes</div>
                    <div class="option" data-question="1" data-answer="B">B. Scan your surroundings</div>
                    <div class="option" data-question="1" data-answer="C">C. Turn your back to the play</div>
                    <div class="option" data-question="1" data-answer="D">D. Stand still</div>
                    <div class="feedback hidden" id="feedback3-1"></div>
                </div>
                
                <div class="question" id="q3-2">
                    <h4>2. What is the purpose of directing your first touch into space?</h4>
                    <div class="option" data-question="2" data-answer="A">A. To make the game more difficult</div>
                    <div class="option" data-question="2" data-answer="B">B. To create time and separation from defenders</div>
                    <div class="option" data-question="2" data-answer="C">C. To show off your skills</div>
                    <div class="option" data-question="2" data-answer="D">D. To tire yourself out</div>
                    <div class="feedback hidden" id="feedback3-2"></div>
                </div>
                
                <div class="question" id="q3-3">
                    <h4>3. Which part of the foot is typically used for powerful long passes?</h4>
                    <div class="option" data-question="3" data-answer="A">A. Inside</div>
                    <div class="option" data-question="3" data-answer="B">B. Outside</div>
                    <div class="option" data-question="3" data-answer="C">C. Laces</div>
                    <div class="option" data-question="3" data-answer="D">D. Heel</div>
                    <div class="feedback hidden" id="feedback3-3"></div>
                </div>
                
                <div class="question" id="q3-4">
                    <h4>4. When receiving an aerial ball, which body part should you NOT use?</h4>
                    <div class="option" data-question="4" data-answer="A">A. Chest</div>
                    <div class="option" data-question="4" data-answer="B">B. Thigh</div>
                    <div class="option" data-question="4" data-answer="C">C. Arm</div>
                    <div class="option" data-question="4" data-answer="D">D. Foot</div>
                    <div class="feedback hidden" id="feedback3-4"></div>
                </div>
                
                <div class="question" id="q3-5">
                    <h4>5. What is the main advantage of switching play with a long pass?</h4>
                    <div class="option" data-question="5" data-answer="A">A. It makes the game slower</div>
                    <div class="option" data-question="5" data-answer="B">B. It stretches the defense and creates space</div>
                    <div class="option" data-question="5" data-answer="C">C. It gives you a rest</div>
                    <div class="option" data-question="5" data-answer="D">D. It makes the crowd happy</div>
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
        <h2 class="module-title">4. Advanced Shooting & Scoring</h2>
        
        <div class="lesson-section">
            <h3>1. Shooting Techniques</h3>
            <p><strong>Definition:</strong> Mastering different shooting techniques for various game situations.</p>
            
            <div class="key-points">
                <h4>Key Techniques:</h4>
                <ul>
                    <li><span class="fun-icon">👟</span> <strong>Instep drive:</strong> Powerful shot with laces</li>
                    <li><span class="fun-icon">🦶</span> <strong>Side-foot:</strong> Accurate placement with inside foot</li>
                    <li><span class="fun-icon">⚽</span> <strong>Volleys:</strong> Striking the ball in mid-air</li>
                    <li><span class="fun-icon">🌀</span> <strong>Curved shots:</strong> Bending the ball around defenders</li>
                </ul>
            </div>
            
            <div class="key-points">
                <h4>Why It Matters:</h4>
                <p>Different game situations require different shooting techniques to maximize scoring chances.</p>
            </div>
            
            <div class="key-points">
                <h4>Drill Idea:</h4>
                <p>"Shooting circuit with different stations for each technique."</p>
            </div>
        </div>
        
        <div class="lesson-section">
            <h3>2. Finishing Under Pressure</h3>
            <p><strong>Definition:</strong> Maintaining composure and technique when shooting with defenders closing down.</p>
            
            <div class="key-points">
                <h4>Key Skills:</h4>
                <ul>
                    <li><span class="fun-icon">🧠</span> <strong>Decision making:</strong> Choose placement vs power</li>
                    <li><span class="fun-icon">⚡</span> <strong>Quick release:</strong> Shoot before defender can block</li>
                    <li><span class="fun-icon">👀</span> <strong>Goalkeeper awareness:</strong> Note positioning before shooting</li>
                    <li><span class="fun-icon">🦶</span> <strong>Body shape:</strong> Proper positioning for different shots</li>
                </ul>
            </div>
            
            <div class="video-container">
                <h3>Watch and Learn: Advanced Finishing</h3>
                <iframe width="560" height="315" src="https://www.youtube.com/embed/dQw4w9WgXcQ" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                <p><em>Techniques for scoring under pressure</em></p>
            </div>
            
            <div class="key-points">
                <h4>Drill Idea:</h4>
                <p>"1v1 to goal with defender applying pressure as attacker shoots."</p>
            </div>
        </div>
        
        <div class="lesson-section">
            <h3>3. Advanced Heading Techniques</h3>
            <p><strong>Definition:</strong> Using the head effectively for both scoring and defensive clearances.</p>
            
            <div class="key-points">
                <h4>Key Concepts:</h4>
                <ul>
                    <li><span class="fun-icon">🎯</span> <strong>Accuracy:</strong> Directing headers with purpose</li>
                    <li><span class="fun-icon">💪</span> <strong>Power:</strong> Generating force from core and neck</li>
                    <li><span class="fun-icon">🔄</span> <strong>Timing:</strong> Judging flight of the ball</li>
                    <li><span class="fun-icon">👀</span> <strong>Positioning:</strong> Getting in line with the ball</li>
                </ul>
            </div>
            
            <div class="key-points">
                <h4>Drill Idea:</h4>
                <p>"Crossing and heading practice with varied service."</p>
            </div>
        </div>
        
        <div class="quiz-container">
            <h3>🧠 Advanced Shooting Quiz</h3>
            <p>Test your knowledge of advanced shooting techniques! Select one answer for each question.</p>
            
            <div id="quiz4">
                <div class="question" id="q4-1">
                    <h4>1. Which part of the foot is best for powerful shots?</h4>
                    <div class="option" data-question="1" data-answer="A">A. Inside</div>
                    <div class="option" data-question="1" data-answer="B">B. Laces</div>
                    <div class="option" data-question="1" data-answer="C">C. Outside</div>
                    <div class="option" data-question="1" data-answer="D">D. Heel</div>
                    <div class="feedback hidden" id="feedback4-1"></div>
                </div>
                
                <div class="question" id="q4-2">
                    <h4>2. What should you look at when preparing to shoot?</h4>
                    <div class="option" data-question="2" data-answer="A">A. The crowd</div>
                    <div class="option" data-question="2" data-answer="B">B. The ball and goalkeeper's position</div>
                    <div class="option" data-question="2" data-answer="C">C. Your teammates</div>
                    <div class="option" data-question="2" data-answer="D">D. The referee</div>
                    <div class="feedback hidden" id="feedback4-2"></div>
                </div>
                
                <div class="question" id="q4-3">
                    <h4>3. What is the most important factor when heading the ball?</h4>
                    <div class="option" data-question="3" data-answer="A">A. Hair style</div>
                    <div class="option" data-question="3" data-answer="B">B. Timing and contact point</div>
                    <div class="option" data-question="3" data-answer="C">C. Shouting loudly</div>
                    <div class="option" data-question="3" data-answer="D">D. Closing your eyes</div>
                    <div class="feedback hidden" id="feedback4-3"></div>
                </div>
                
                <div class="question" id="q4-4">
                    <h4>4. When shooting under pressure, what should you focus on?</h4>
                    <div class="option" data-question="4" data-answer="A">A. Technique and placement</div>
                    <div class="option" data-question="4" data-answer="B">B. Kicking as hard as possible</div>
                    <div class="option" data-question="4" data-answer="C">C. The defender's face</div>
                    <div class="option" data-question="4" data-answer="D">D. The weather</div>
                    <div class="feedback hidden" id="feedback4-4"></div>
                </div>
                
                <div class="question" id="q4-5">
                    <h4>5. What is the advantage of a curved shot?</h4>
                    <div class="option" data-question="5" data-answer="A">A. It looks pretty</div>
                    <div class="option" data-question="5" data-answer="B">B. It can bend around defenders</div>
                    <div class="option" data-question="5" data-answer="C">C. It's easier than a straight shot</div>
                    <div class="option" data-question="5" data-answer="D">D. It makes noise</div>
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
        <h2 class="module-title">5. Advanced Fitness & Team Play</h2>
        
        <div class="lesson-section">
            <h3>1. Position-Specific Fitness</h3>
            <p><strong>Definition:</strong> Developing fitness attributes specific to different playing positions.</p>
            
            <div class="key-points">
                <h4>Key Attributes:</h4>
                <ul>
                    <li><span class="fun-icon">🏃</span> <strong>Forwards:</strong> Explosive speed, quick changes of direction</li>
                    <li><span class="fun-icon">🔄</span> <strong>Midfielders:</strong> Endurance, repeated sprints</li>
                    <li><span class="fun-icon">🛡️</span> <strong>Defenders:</strong> Strength, acceleration, jumping</li>
                    <li><span class="fun-icon">🧤</span> <strong>Goalkeepers:</strong> Reaction time, explosive power</li>
                </ul>
            </div>
            
            <div class="key-points">
                <h4>Why It Matters:</h4>
                <p>Different positions have different physical demands that should be trained specifically.</p>
            </div>
            
            <div class="key-points">
                <h4>Drill Idea:</h4>
                <p>"Position-specific conditioning circuits tailored to each player's role."</p>
            </div>
        </div>
        
        <div class="lesson-section">
            <h3>2. Tactical Formations</h3>
            <p><strong>Definition:</strong> Understanding different team formations and their strengths/weaknesses.</p>
            
            <div class="key-points">
                <h4>Common Formations:</h4>
                <ul>
                    <li><span class="fun-icon">4-4-2</span> Balanced attack and defense</li>
                    <li><span class="fun-icon">4-3-3</span> Attacking with wingers</li>
                    <li><span class="fun-icon">3-5-2</span> Strong midfield presence</li>
                    <li><span class="fun-icon">4-2-3-1</span> Flexible attacking options</li>
                </ul>
            </div>
            
            <div class="key-points">
                <h4>Why It Matters:</h4>
                <p>Understanding formations helps players know their roles and responsibilities.</p>
            </div>
            
            <div class="key-points">
                <h4>Drill Idea:</h4>
                <p>"Walkthrough of formations on a tactics board followed by small-sided games using different formations."</p>
            </div>
        </div>
        
        <div class="lesson-section">
            <h3>3. Advanced Team Communication</h3>
            <p><strong>Definition:</strong> Developing effective verbal and non-verbal communication on the field.</p>
            
            <div class="key-points">
                <h4>Key Concepts:</h4>
                <ul>
                    <li><span class="fun-icon">🗣️</span> <strong>Verbal cues:</strong> Calling for passes, warning of pressure</li>
                    <li><span class="fun-icon">👋</span> <strong>Hand signals:</strong> Pointing to space or indicating runs</li>
                    <li><span class="fun-icon">👀</span> <strong>Eye contact:</strong> Silent communication between players</li>
                    <li><span class="fun-icon">🔄</span> <strong>Positional rotation:</strong> Understanding when to switch positions</li>
                </ul>
            </div>
            
            <div class="video-container">
                <h3>Watch and Learn: Team Communication</h3>
                <iframe width="560" height="315" src="https://www.youtube.com/embed/dQw4w9WgXcQ" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                <p><em>Examples of effective team communication</em></p>
            </div>
            
            <div class="key-points">
                <h4>Drill Idea:</h4>
                <p>"Small-sided games where communication is emphasized and rewarded."</p>
            </div>
        </div>
        
        <div class="quiz-container">
            <h3>🧠 Advanced Team Play Quiz</h3>
            <p>Test your knowledge of advanced team concepts! Select one answer for each question.</p>
            
            <div id="quiz5">
                <div class="question" id="q5-1">
                    <h4>1. Which fitness attribute is most important for midfielders?</h4>
                    <div class="option" data-question="1" data-answer="A">A. Maximum strength</div>
                    <div class="option" data-question="1" data-answer="B">B. Endurance and repeated sprint ability</div>
                    <div class="option" data-question="1" data-answer="C">C. Jumping height</div>
                    <div class="option" data-question="1" data-answer="D">D. Reaction time</div>
                    <div class="feedback hidden" id="feedback5-1"></div>
                </div>
                
                <div class="question" id="q5-2">
                    <h4>2. What is the main advantage of a 4-3-3 formation?</h4>
                    <div class="option" data-question="2" data-answer="A">A. Strong defense</div>
                    <div class="option" data-question="2" data-answer="B">B. Attacking width with wingers</div>
                    <div class="option" data-question="2" data-answer="C">C. Many goalkeepers</div>
                    <div class="option" data-question="2" data-answer="D">D. Less running</div>
                    <div class="feedback hidden" id="feedback5-2"></div>
                </div>
                
                <div class="question" id="q5-3">
                    <h4>3. What should you call out when a teammate doesn't see an approaching defender?</h4>
                    <div class="option" data-question="3" data-answer="A">A. "Time!"</div>
                    <div class="option" data-question="3" data-answer="B">B. "Man on!"</div>
                    <div class="option" data-question="3" data-answer="C">C. "Shoot!"</div>
                    <div class="option" data-question="3" data-answer="D">D. "Substitute!"</div>
                    <div class="feedback hidden" id="feedback5-3"></div>
                </div>
                
                <div class="question" id="q5-4">
                    <h4>4. Which of these is NOT an effective communication method?</h4>
                    <div class="option" data-question="4" data-answer="A">A. Calling for the ball</div>
                    <div class="option" data-question="4" data-answer="B">B. Pointing to space</div>
                    <div class="option" data-question="4" data-answer="C">C. Making eye contact</div>
                    <div class="option" data-question="4" data-answer="D">D. Yelling insults</div>
                    <div class="feedback hidden" id="feedback5-4"></div>
                </div>
                
                <div class="question" id="q5-5">
                    <h4>5. What is the purpose of positional rotation?</h4>
                    <div class="option" data-question="5" data-answer="A">A. To confuse the opponents</div>
                    <div class="option" data-question="5" data-answer="B">B. To create space and attacking opportunities</div>
                    <div class="option" data-question="5" data-answer="C">C. To make the game more difficult</div>
                    <div class="option" data-question="5" data-answer="D">D. To rest players</div>
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
            "1-2": "C",
            "1-3": "B",
            "1-4": "D",
            "1-5": "B",
            // Module 2
            "2-1": "B",
            "2-2": "B",
            "2-3": "B",
            "2-4": "B",
            "2-5": "C",
            // Module 3
            "3-1": "B",
            "3-2": "B",
            "3-3": "C",
            "3-4": "C",
            "3-5": "B",
            // Module 4
            "4-1": "B",
            "4-2": "B",
            "4-3": "B",
            "4-4": "A",
            "4-5": "B",
            // Module 5
            "5-1": "B",
            "5-2": "B",
            "5-3": "B",
            "5-4": "D",
            "5-5": "B"
        };
        
        // Explanations for each question
        const explanations = {
            // Module 1
            "1-1": "A player is in an offside position if they are nearer to the opponent's goal line than both the ball and the second-last opponent.",
            "1-2": "Serious foul play is a red card offense, not a yellow card offense.",
            "1-3": "A raised arm signals an indirect free kick, where the ball must touch another player before a goal can be scored.",
            "1-4": "Players cannot be offside directly from goal kicks, corner kicks, or throw-ins.",
            "1-5": "There must be at least two defenders (usually the last defender and the goalkeeper) between the attacker and the goal.",
            // Module 2
            "2-1": "Step-overs are used to fake direction changes and deceive defenders.",
            "2-2": "When shielding, your body should be between the defender and the ball.",
            "2-3": "Keeping the ball close is essential in tight spaces to maintain control.",
            "2-4": "Accelerating into space after a move helps capitalize on the advantage created.",
            "2-5": "A simple pass is a basic technique, not an advanced dribbling move.",
            // Module 3
            "3-1": "Scanning before receiving helps you make better decisions under pressure.",
            "3-2": "Directing your first touch into space creates time and separation from defenders.",
            "3-3": "The laces provide the most power for long passes.",
            "3-4": "Using your arm to control the ball would be a handball offense.",
            "3-5": "Long passes can stretch the defense by quickly switching play to the opposite side.",
            // Module 4
            "4-1": "The laces provide the most power for shooting.",
            "4-2": "You should look at the ball as you strike it and be aware of the goalkeeper's position.",
            "4-3": "Proper timing and making contact with the forehead are crucial for effective heading.",
            "4-4": "Maintaining good technique and placement is more important than pure power under pressure.",
            "4-5": "Curved shots can bend around defenders or the goalkeeper.",
            // Module 5
            "5-1": "Midfielders need endurance to cover large areas of the pitch throughout the game.",
            "5-2": "The 4-3-3 formation provides width with wingers stretching the defense.",
            "5-3": "'Man on!' warns a teammate of approaching pressure.",
            "5-4": "Yelling insults is never appropriate and doesn't help team communication.",
            "5-5": "Positional rotation creates space and unexpected attacking opportunities."
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
            
            const submitBtn = document.getElementById(`submit-btn${moduleNum}`);
            submitBtn.disabled = !allAnswered;
        }
        
        // Submit a quiz for grading
        function submitQuiz(moduleNum) {
            quizSubmitted[moduleNum] = true;
            
            let score = 0;
            
            // Grade each question
            for (let q = 1; q <= 5; q++) {
                const questionId = `${moduleNum}-${q}`;
                const userAnswer = userAnswers[questionId];
                const isCorrect = userAnswer === correctAnswers[questionId];
                
                if (isCorrect) score++;
                
                // Show feedback
                const feedbackEl = document.getElementById(`feedback${moduleNum}-${q}`);
                feedbackEl.classList.remove('hidden');
                feedbackEl.classList.remove('correct', 'incorrect');
                feedbackEl.classList.add(isCorrect ? 'correct' : 'incorrect');
                
                feedbackEl.innerHTML = isCorrect 
                    ? '✅ Correct!'
                    : `❌ Incorrect. The correct answer is ${correctAnswers[questionId]}.`;
                
                feedbackEl.innerHTML += `<div class="explanation">${explanations[questionId]}</div>`;
            }
            
            // Show result
            const resultEl = document.getElementById(`result${moduleNum}`);
            resultEl.classList.remove('hidden');
            resultEl.textContent = `You scored ${score}/5 (${Math.round(score/5*100)}%)`;
            
            // Disable submit button
            document.getElementById(`submit-btn${moduleNum}`).disabled = true;
            
            // Check if module is passed (3+ correct answers)
            if (score >= 3) {
                moduleStatus[moduleNum] = true;
                progressIndicators[moduleNum].classList.add('completed');
                
                // Unlock next module if not the last one
                if (moduleNum < 5) {
                    moduleButtons[moduleNum + 1].classList.remove('locked');
                }
                
                // Save progress
                saveProgress();
                
                // Show success message
                resultEl.innerHTML += '<br><strong>Congratulations! You passed this module!</strong>';
                
                // Hide try again section if shown
                document.getElementById(`try-again${moduleNum}`).classList.add('hidden');
                
                // Submit completion to server
                submitModuleCompletion(moduleNum, score);
            } else {
                // Show try again section
                document.getElementById(`try-again${moduleNum}`).classList.remove('hidden');
            }
        }
        
        // Reset a quiz to try again
        function resetQuiz(moduleNum) {
            // Clear selections
            document.querySelectorAll(`#quiz${moduleNum} .option`).forEach(option => {
                option.classList.remove('selected');
            });
            
            // Clear feedback
            document.querySelectorAll(`#quiz${moduleNum} .feedback`).forEach(feedback => {
                feedback.classList.add('hidden');
                feedback.textContent = '';
            });
            
            // Hide result and try again section
            document.getElementById(`result${moduleNum}`).classList.add('hidden');
            document.getElementById(`try-again${moduleNum}`).classList.add('hidden');
            
            // Clear user answers
            for (let q = 1; q <= 5; q++) {
                delete userAnswers[`${moduleNum}-${q}`];
            }
            
            // Reset submit status
            quizSubmitted[moduleNum] = false;
            
            // Disable submit button
            document.getElementById(`submit-btn${moduleNum}`).disabled = true;
        }
        
        // Save progress to localStorage
        function saveProgress() {
            localStorage.setItem('courseProgress', JSON.stringify({
                moduleStatus,
                unlockedModule: getHighestUnlockedModule()
            }));
        }
        
        // Load progress from localStorage
        function loadProgress() {
            const savedProgress = localStorage.getItem('courseProgress');
            if (savedProgress) {
                const progress = JSON.parse(savedProgress);
                
                // Update module status
                for (const [moduleNum, isCompleted] of Object.entries(progress.moduleStatus)) {
                    if (isCompleted) {
                        moduleStatus[moduleNum] = true;
                        progressIndicators[moduleNum].classList.add('completed');
                    }
                }
                
                // Unlock modules up to the highest unlocked
                const highestUnlocked = progress.unlockedModule || 1;
                for (let m = 1; m <= highestUnlocked; m++) {
                    moduleButtons[m].classList.remove('locked');
                }
            }
        }
        
        // Get the highest unlocked module number
        function getHighestUnlockedModule() {
            let highest = 1;
            for (let m = 1; m <= 5; m++) {
                if (!moduleButtons[m].classList.contains('locked')) {
                    highest = m;
                }
            }
            return highest;
        }
        
        // Submit module completion to server
        function submitModuleCompletion(moduleNum, score) {
            const formData = new FormData();
            formData.append('complete_module', true);
            formData.append('module_id', moduleNum);
            formData.append('quiz_score', score);
            
            fetch('courses age 12-14.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            })
            .catch(error => {
                console.error('Error submitting module completion:', error);
            });
        }
    </script>
</body>
</html>