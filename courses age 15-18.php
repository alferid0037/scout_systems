
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

// Set module-specific colors for Age 15-18
$module_colors = [
    1 => '#673AB7', // Module 1 - Deep Purple
    2 => '#009688', // Module 2 - Teal
    3 => '#FF5722', // Module 3 - Deep Orange
    4 => '#C62828', // Module 4 - Dark Red
    5 => '#0D47A1'  // Module 5 - Dark Blue
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
        
        .position-grid, .tactical-grid, .moves-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .position-card, .tactical-card, .move-card {
            background-color: #f5f5f5;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid var(--primary);
        }
        
        .position-card h4, .tactical-card h4, .move-card h4 {
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
        
        #module1-container .submit-btn:hover { background-color: #5E35B1; }
        #module2-container .submit-btn:hover { background-color: #00796B; }
        #module3-container .submit-btn:hover { background-color: #E64A19; }
        #module4-container .submit-btn:hover { background-color: #B71C1C; }
        #module5-container .submit-btn:hover { background-color: #1565C0; }
        
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

    <h1>⚽ Advanced Football Training Program (Age 15-18)</h1>
    
    <div class="progress-tracker">
        Progress: 
        <span id="module1-progress" class="completed">1</span>
        <span id="module2-progress">2</span>
        <span id="module3-progress">3</span>
        <span id="module4-progress">4</span>
        <span id="module5-progress">5</span>
    </div>
    
    <div class="module-selector">
        <button id="module1-btn" class="module-btn">Module 1: Tactics & Rules</button>
        <button id="module2-btn" class="module-btn locked">Module 2: Dribbling</button>
        <button id="module3-btn" class="module-btn locked">Module 3: Passing</button>
        <button id="module4-btn" class="module-btn locked">Module 4: Finishing</button>
        <button id="module5-btn" class="module-btn locked">Module 5: Leadership</button>
    </div>
    
    <!-- Module 1 Container -->
    <div id="module1-container" class="module-container active">
        <h2 class="module-title">1. Tactics & Rules</h2>
        
        <div class="lesson-section">
            <h3>1. In-depth Study of Game Strategies</h3>
            <p><strong>Definition:</strong> Learning how football teams build tactics such as attacking and defensive formations (e.g., 4-3-3, 4-4-2, 3-5-2), pressing styles, counterattacks, and possession play.</p>
            
            <div class="key-points">
                <h4>Key Concepts:</h4>
                <ul>
                    <li><span class="fun-icon">🏗️</span> Build-up play from the back</li>
                    <li><span class="fun-icon">⚡</span> High press vs. low block</li>
                    <li><span class="fun-icon">🔄</span> Switching play and creating space</li>
                    <li><span class="fun-icon">⏱️</span> Game management and tempo control</li>
                </ul>
            </div>
            
            <div class="tactical-grid">
                <div class="tactical-card">
                    <h4><span class="fun-icon">⚡</span> High Press</h4>
                    <p>Aggressive pressing high up the pitch to win possession quickly</p>
                </div>
                <div class="tactical-card">
                    <h4><span class="fun-icon">🔄</span> Counterattack</h4>
                    <p>Quick transition from defense to attack when winning possession</p>
                </div>
            </div>
            
            <div class="key-points">
                <h4>Formation Example:</h4>
                <p>A 4-3-3 formation provides width in attack while maintaining defensive stability with 4 defenders and 3 midfielders.</p>
            </div>
        </div>
        
        <div class="lesson-section">
            <h3>2. Advanced Rules Interpretation</h3>
            <p><strong>Definition:</strong> Understanding nuanced rules like offside exceptions, advantage rule application, and referee signals.</p>
            
            <div class="key-points">
                <h4>Key Rules to Cover:</h4>
                <ul>
                    <li><span class="fun-icon">🚩</span> Offside rule exceptions (goal kicks, throw-ins)</li>
                    <li><span class="fun-icon">🔄</span> Advantage rule application</li>
                    <li><span class="fun-icon">🖐️</span> Handball rule interpretations</li>
                    <li><span class="fun-icon">⏱️</span> Time wasting rules</li>
                </ul>
            </div>
        </div>
        
        <div class="video-container">
            <h3>Watch and Learn: Tactical Analysis</h3>
            <iframe width="560" height="315" src="https://www.youtube.com/embed/r_F9c_g-20Y" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
            <p><em>Professional examples of tactical formations and strategies</em></p>
        </div>
        
        <div class="quiz-container">
            <h3>🧠 Tactics & Rules Quiz</h3>
            <p>Test your knowledge of advanced football tactics and rules! Select one answer for each question.</p>
            
            <div id="quiz1">
                <div class="question" id="q1-1">
                    <h4>1. Which tactical formation is best suited for quick counter-attacks?</h4>
                    <div class="option" data-question="1" data-answer="A">A. 4-4-2</div>
                    <div class="option" data-question="1" data-answer="B">B. 3-5-2</div>
                    <div class="option" data-question="1" data-answer="C">C. 4-2-3-1</div>
                    <div class="option" data-question="1" data-answer="D">D. 5-3-2</div>
                    <div class="feedback hidden" id="feedback1-1"></div>
                </div>
                
                <div class="question" id="q1-2">
                    <h4>2. What is the main tactical responsibility of a defensive midfielder in a 4-3-3 system?</h4>
                    <div class="option" data-question="2" data-answer="A">A. Score goals</div>
                    <div class="option" data-question="2" data-answer="B">B. Press high and mark full-backs</div>
                    <div class="option" data-question="2" data-answer="C">C. Shield the backline and distribute the ball</div>
                    <div class="option" data-question="2" data-answer="D">D. Play as a second striker</div>
                    <div class="feedback hidden" id="feedback1-2"></div>
                </div>
                
                <div class="question" id="q1-3">
                    <h4>3. In an offside situation, when is a player considered not offside?</h4>
                    <div class="option" data-question="3" data-answer="A">A. When receiving the ball from a goal kick</div>
                    <div class="option" data-question="3" data-answer="B">B. When behind the last defender</div>
                    <div class="option" data-question="3" data-answer="C">C. When behind the ball</div>
                    <div class="option" data-question="3" data-answer="D">D. All of the above</div>
                    <div class="feedback hidden" id="feedback1-3"></div>
                </div>
                
                <div class="question" id="q1-4">
                    <h4>4. How does a team benefit from using a 'false 9' system?</h4>
                    <div class="option" data-question="4" data-answer="A">A. Strengthens central defense</div>
                    <div class="option" data-question="4" data-answer="B">B. Increases width in attack</div>
                    <div class="option" data-question="4" data-answer="C">C. Draws center-backs out of position to create space</div>
                    <div class="option" data-question="4" data-answer="D">D. Improves set-piece defending</div>
                    <div class="feedback hidden" id="feedback1-4"></div>
                </div>
                
                <div class="question" id="q1-5">
                    <h4>5. Which scenario would most likely result in a yellow card?</h4>
                    <div class="option" data-question="5" data-answer="A">A. Slight shoulder contact</div>
                    <div class="option" data-question="5" data-answer="B">B. Taking off your shirt during celebration</div>
                    <div class="option" data-question="5" data-answer="C">C. Accidental handball in midfield</div>
                    <div class="option" data-question="5" data-answer="D">D. Standing in front of a throw-in</div>
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
        <h2 class="module-title">2. Dribbling</h2>
        
        <div class="lesson-section">
            <h3>1. Creative Dribbling Techniques</h3>
            <p><strong>Definition:</strong> Developing individual flair and improvisation to beat defenders.</p>
            
            <div class="key-points">
                <h4>Focus Areas:</h4>
                <ul>
                    <li><span class="fun-icon">🔄</span> Use of body feints, step-overs, drag-backs, and quick changes of direction</li>
                    <li><span class="fun-icon">🎩</span> Incorporating tricks like the Elastico, Cruyff Turn, and La Croqueta</li>
                    <li><span class="fun-icon">👀</span> Reading the defender's body shape and reaction</li>
                    <li><span class="fun-icon">⚡</span> Combining speed with control for maximum effect</li>
                </ul>
            </div>
            
            <div class="moves-grid">
                <div class="move-card">
                    <h4><span class="fun-icon">🎩</span> Elastico</h4>
                    <p>Quick outside-inside foot flick to deceive defender</p>
                </div>
                <div class="move-card">
                    <h4><span class="fun-icon">🔄</span> Cruyff Turn</h4>
                    <p>Fake shot/pass followed by quick drag-back</p>
                </div>
                <div class="move-card">
                    <h4><span class="fun-icon">👟</span> La Croqueta</h4>
                    <p>Rapid inside-to-inside foot switch</p>
                </div>
                <div class="move-card">
                    <h4><span class="fun-icon">💃</span> Body Feint</h4>
                    <p>Shoulder drop to fake direction change</p>
                </div>
            </div>
        </div>
        
        <div class="lesson-section">
            <h3>2. Dribbling in Tight Spaces</h3>
            <p><strong>Definition:</strong> Techniques for maintaining possession and creating opportunities in congested areas.</p>
            
            <div class="key-points">
                <h4>Key Skills:</h4>
                <ul>
                    <li><span class="fun-icon">🔒</span> Close ball control using all parts of the foot</li>
                    <li><span class="fun-icon">🔄</span> Quick changes of direction</li>
                    <li><span class="fun-icon">👀</span> Awareness of surrounding players</li>
                    <li><span class="fun-icon">🤸</span> Use of body to shield the ball</li>
                </ul>
            </div>
            
            <div class="key-points">
                <h4>Drill Idea:</h4>
                <p>Set up a small grid (5x5m) with 4 players trying to maintain possession against 2 defenders.</p>
            </div>
        </div>
        
        <div class="video-container">
            <h3>Watch and Learn: Advanced Dribbling Techniques</h3>
            <iframe width="560" height="315" src="https://www.youtube.com/embed/e_qgS6-Jm2A" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
            <p><em>Mastering creative dribbling and team integration</em></p>
        </div>
        
        <div class="quiz-container">
            <h3>🧠 Advanced Dribbling Quiz</h3>
            <p>Test your tactical and technical understanding of high-level dribbling concepts.</p>
            
            <div id="quiz2">
                <div class="question" id="q2-1">
                    <h4>1. Which of the following is most effective for escaping pressure in tight spaces?</h4>
                    <div class="option" data-question="1" data-answer="A">A. Long sprint dribble</div>
                    <div class="option" data-question="1" data-answer="B">B. Backheel pass</div>
                    <div class="option" data-question="1" data-answer="C">C. Quick body feint + change of direction</div>
                    <div class="option" data-question="1" data-answer="D">D. Lofted through ball</div>
                    <div class="feedback hidden" id="feedback2-1"></div>
                </div>
                
                <div class="question" id="q2-2">
                    <h4>2. What is the main risk of excessive dribbling in a team system?</h4>
                    <div class="option" data-question="2" data-answer="A">A. Losing the ball and disrupting team shape</div>
                    <div class="option" data-question="2" data-answer="B">B. Too many passes</div>
                    <div class="option" data-question="2" data-answer="C">C. Drawing a foul</div>
                    <div class="option" data-question="2" data-answer="D">D. Confusing the coach</div>
                    <div class="feedback hidden" id="feedback2-2"></div>
                </div>
                
                <div class="question" id="q2-3">
                    <h4>3. The 'La Croqueta' move is best used when:</h4>
                    <div class="option" data-question="3" data-answer="A">A. Switching play</div>
                    <div class="option" data-question="3" data-answer="B">B. Escaping a defender side-by-side</div>
                    <div class="option" data-question="3" data-answer="C">C. Shooting on goal</div>
                    <div class="option" data-question="3" data-answer="D">D. Dribbling diagonally in open space</div>
                    <div class="feedback hidden" id="feedback2-3"></div>
                </div>
                
                <div class="question" id="q2-4">
                    <h4>4. Which principle helps decide when to dribble in team play?</h4>
                    <div class="option" data-question="4" data-answer="A">A. Always dribble in the final third</div>
                    <div class="option" data-question="4" data-answer="B">B. Dribble if you see open space or an isolated defender</div>
                    <div class="option" data-question="4" data-answer="C">C. Dribble only near the touchline</div>
                    <div class="option" data-question="4" data-answer="D">D. Avoid dribbling at all times</div>
                    <div class="feedback hidden" id="feedback2-4"></div>
                </div>
                
                <div class="question" id="q2-5">
                    <h4>5. When dribbling to draw defenders and pass, what is key?</h4>
                    <div class="option" data-question="5" data-answer="A">A. Head down and fast touches</div>
                    <div class="option" data-question="5" data-answer="B">B. Look for teammate movement and time your pass</div>
                    <div class="option" data-question="5" data-answer="C">C. Avoid all contact</div>
                    <div class="option" data-question="5" data-answer="D">D. Use one foot only</div>
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
        <h2 class="module-title">3. Passing</h2>
        
        <div class="lesson-section">
            <h3>1. Tactical Passing (Through Balls, Switches)</h3>
            <p><strong>Definition:</strong> Advanced passing techniques used to break defenses or shift the point of attack.</p>
            
            <div class="key-points">
                <h4>Focus Areas:</h4>
                <ul>
                    <li><span class="fun-icon">🎯</span> Through balls: Splitting defenders to find teammates making forward runs</li>
                    <li><span class="fun-icon">🔄</span> Switches of play: Changing the direction of attack to exploit weak sides</li>
                    <li><span class="fun-icon">⚖️</span> Weight, timing, and angles of passes</li>
                    <li><span class="fun-icon">👀</span> Reading defensive lines and exploiting space</li>
                </ul>
            </div>
            
            <div class="tactical-grid">
                <div class="tactical-card">
                    <h4><span class="fun-icon">🎯</span> Through Ball Execution</h4>
                    <p>Play when attacker is making run, defense is high, and passing lane is open</p>
                </div>
                <div class="tactical-card">
                    <h4><span class="fun-icon">🔄</span> Switch of Play</h4>
                    <p>Use diagonal balls to change point of attack when opponents overload one side</p>
                </div>
            </div>
        </div>
        
        <div class="lesson-section">
            <h3>2. Third-Man Combinations</h3>
            <p><strong>Definition:</strong> Complex passing patterns involving three players to bypass defensive pressure.</p>
            
            <div class="key-points">
                <h4>Key Concepts:</h4>
                <ul>
                    <li><span class="fun-icon">👥</span> Understanding positional rotations</li>
                    <li><span class="fun-icon">⏱️</span> Timing of runs and passes</li>
                    <li><span class="fun-icon">👀</span> Awareness of teammate positioning</li>
                    <li><span class="fun-icon">⚡</span> Quick one-touch passing</li>
                </ul>
            </div>
            
            <div class="key-points">
                <h4>Drill Idea:</h4>
                <p>Set up a 3v1 possession drill in a small area to practice quick combinations.</p>
            </div>
        </div>
        
        <div class="video-container">
            <h3>Watch and Learn: Tactical Passing</h3>
            <iframe width="560" height="315" src="https://www.youtube.com/embed/3H8jYgQfN4Y" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
            <p><em>Professional examples of tactical passing patterns</em></p>
        </div>
        
        <div class="quiz-container">
            <h3>🧠 Tactical Passing Quiz</h3>
            <p>Test your knowledge of tactical decision-making, passing technique, and match awareness.</p>
            
            <div id="quiz3">
                <div class="question" id="q3-1">
                    <h4>1. What is the main purpose of a through ball?</h4>
                    <div class="option" data-question="1" data-answer="A">A. To maintain possession</div>
                    <div class="option" data-question="1" data-answer="B">B. To stretch the defense horizontally</div>
                    <div class="option" data-question="1" data-answer="C">C. To send a teammate into space behind defenders</div>
                    <div class="option" data-question="1" data-answer="D">D. To waste time when ahead</div>
                    <div class="feedback hidden" id="feedback3-1"></div>
                </div>
                
                <div class="question" id="q3-2">
                    <h4>2. Which passing pattern involves three players and helps bypass pressure?</h4>
                    <div class="option" data-question="2" data-answer="A">A. Wall pass</div>
                    <div class="option" data-question="2" data-answer="B">B. Overlap</div>
                    <div class="option" data-question="2" data-answer="C">C. Third-man combination</div>
                    <div class="option" data-question="2" data-answer="D">D. Backpass</div>
                    <div class="feedback hidden" id="feedback3-2"></div>
                </div>
                
                <div class="question" id="q3-3">
                    <h4>3. Switching play is most effective when:</h4>
                    <div class="option" data-question="3" data-answer="A">A. You are deep in your own half</div>
                    <div class="option" data-question="3" data-answer="B">B. The ball is in the corner</div>
                    <div class="option" data-question="3" data-answer="C">C. One side is overloaded and the opposite side has space</div>
                    <div class="option" data-question="3" data-answer="D">D. You are trying to waste time</div>
                    <div class="feedback hidden" id="feedback3-3"></div>
                </div>
                
                <div class="question" id="q3-4">
                    <h4>4. Which player typically dictates the tempo of passing in a professional team?</h4>
                    <div class="option" data-question="4" data-answer="A">A. Striker</div>
                    <div class="option" data-question="4" data-answer="B">B. Full-back</div>
                    <div class="option" data-question="4" data-answer="C">C. Central midfielder (e.g., deep-lying playmaker)</div>
                    <div class="option" data-question="4" data-answer="D">D. Goalkeeper</div>
                    <div class="feedback hidden" id="feedback3-4"></div>
                </div>
                
                <div class="question" id="q3-5">
                    <h4>5. In high-level matches, what helps players execute quick combination plays effectively?</h4>
                    <div class="option" data-question="5" data-answer="A">A. Long touches</div>
                    <div class="option" data-question="5" data-answer="B">B. Communication and off-ball movement</div>
                    <div class="option" data-question="5" data-answer="C">C. Individual dribbling skill</div>
                    <div class="option" data-question="5" data-answer="D">D. Avoiding risky passes</div>
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
        <h2 class="module-title">4. Finishing</h2>
        
        <div class="lesson-section">
            <h3>1. Finishing Techniques in Different Scenarios</h3>
            <p><strong>Definition:</strong> Mastering how to finish in varied match situations under pressure.</p>
            
            <div class="key-points">
                <h4>Focus Areas:</h4>
                <ul>
                    <li><span class="fun-icon">🥅</span> 1-on-1 with the goalkeeper (using finesse or power)</li>
                    <li><span class="fun-icon">⚡</span> First-time finishes (volleys, crosses)</li>
                    <li><span class="fun-icon">🔄</span> Finishing from cut-backs and rebounds</li>
                    <li><span class="fun-icon">📏</span> Finishing inside vs. outside the box</li>
                    <li><span class="fun-icon">🧠</span> Composure in front of goal under defensive pressure</li>
                </ul>
            </div>
            
            <div class="key-points">
                <h4>Drill Idea:</h4>
                <p>Set up different finishing stations (volleys, 1v1, first-time shots) and rotate players through them.</p>
            </div>
        </div>
        
        <div class="lesson-section">
            <h3>2. Advanced Shooting Techniques</h3>
            <p><strong>Definition:</strong> Specialized shooting methods for different game situations.</p>
            
            <div class="key-points">
                <h4>Key Techniques:</h4>
                <ul>
                    <li><span class="fun-icon">🌀</span> Curved shots (banana kicks)</li>
                    <li><span class="fun-icon">💨</span> Knuckleball technique</li>
                    <li><span class="fun-icon">👟</span> Chip shots</li>
                    <li><span class="fun-icon">⚡</span> Half-volleys</li>
                </ul>
            </div>
        </div>
        
        <div class="video-container">
            <h3>Watch and Learn: Professional Finishing Techniques</h3>
            <iframe width="560" height="315" src="https://www.youtube.com/embed/gH-N3nL_q0s" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
            <p><em>Advanced shooting and finishing demonstrations</em></p>
        </div>
        
        <div class="quiz-container">
            <h3>🧠 Finishing & Shooting Quiz</h3>
            <p>Test your knowledge of decision-making, shooting mechanics, and advanced finishing situations.</p>
            
            <div id="quiz4">
                <div class="question" id="q4-1">
                    <h4>1. In a 1-on-1 with the goalkeeper, what technique increases scoring chances?</h4>
                    <div class="option" data-question="1" data-answer="A">A. Power shot from far out</div>
                    <div class="option" data-question="1" data-answer="B">B. Shooting immediately without observing the keeper</div>
                    <div class="option" data-question="1" data-answer="C">C. A composed finish with eye contact and controlled placement</div>
                    <div class="option" data-question="1" data-answer="D">D. Chip the ball every time</div>
                    <div class="feedback hidden" id="feedback4-1"></div>
                </div>
                
                <div class="question" id="q4-2">
                    <h4>2. Why is it important to develop your weaker foot for shooting?</h4>
                    <div class="option" data-question="2" data-answer="A">A. To avoid passing</div>
                    <div class="option" data-question="2" data-answer="B">B. To shoot without repositioning or wasting time</div>
                    <div class="option" data-question="2" data-answer="C">C. Because it looks impressive</div>
                    <div class="option" data-question="2" data-answer="D">D. To make goalkeepers tired</div>
                    <div class="feedback hidden" id="feedback4-2"></div>
                </div>
                
                <div class="question" id="q4-3">
                    <h4>3. Which technique is used to strike a free kick that dips quickly over the wall?</h4>
                    <div class="option" data-question="3" data-answer="A">A. Knuckleball</div>
                    <div class="option" data-question="3" data-answer="B">B. Inside-foot curl</div>
                    <div class="option" data-question="3" data-answer="C">C. Driven low shot</div>
                    <div class="option" data-question="3" data-answer="D">D. Outside foot lob</div>
                    <div class="feedback hidden" id="feedback4-3"></div>
                </div>
                
                <div class="question" id="q4-4">
                    <h4>4. What should a player consider most when taking a penalty under pressure?</h4>
                    <div class="option" data-question="4" data-answer="A">A. The crowd noise</div>
                    <div class="option" data-question="4" data-answer="B">B. The goalkeeper's shirt color</div>
                    <div class="option" data-question="4" data-answer="C">C. Their preferred corner and timing</div>
                    <div class="option" data-question="4" data-answer="D">D. Shooting as hard as possible</div>
                    <div class="feedback hidden" id="feedback4-4"></div>
                </div>
                
                <div class="question" id="q4-5">
                    <h4>5. What's the key advantage of finishing from a cut-back pass?</h4>
                    <div class="option" data-question="5" data-answer="A">A. You can wait longer to shoot</div>
                    <div class="option" data-question="5" data-answer="B">B. You face the goal with defenders trailing</div>
                    <div class="option" data-question="5" data-answer="C">C. It's a slow pass so you have time</div>
                    <div class="option" data-question="5" data-answer="D">D. It always leads to a penalty</div>
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
        <h2 class="module-title">5. Leadership</h2>
        
        <div class="lesson-section">
            <h3>1. Advanced Conditioning and Recovery</h3>
            <p><strong>Definition:</strong> Focused training on peak physical performance and optimal recovery methods.</p>
            
            <div class="key-points">
                <h4>Focus Areas:</h4>
                <ul>
                    <li><span class="fun-icon">🏋️</span> Position-specific endurance and strength training</li>
                    <li><span class="fun-icon">⚡</span> HIIT (High-Intensity Interval Training) for match fitness</li>
                    <li><span class="fun-icon">🔥</span> Proper warm-up/cool-down routines</li>
                    <li><span class="fun-icon">🛡️</span> Injury prevention strategies (stretching, mobility, core)</li>
                    <li><span class="fun-icon">🧊</span> Recovery: hydration, nutrition, sleep, massage, ice baths</li>
                </ul>
            </div>
        </div>
        
        <div class="lesson-section">
            <h3>2. On-Field Leadership and Communication</h3>
            <p><strong>Definition:</strong> Developing leadership skills to organize and motivate teammates during matches.</p>
            
            <div class="key-points">
                <h4>Key Skills:</h4>
                <ul>
                    <li><span class="fun-icon">🗣️</span> Effective communication (clear, concise, positive)</li>
                    <li><span class="fun-icon">👥</span> Reading the game and organizing teammates</li>
                    <li><span class="fun-icon">💪</span> Leading by example (work rate, attitude)</li>
                    <li><span class="fun-icon">🧠</span> Maintaining composure under pressure</li>
                </ul>
            </div>
        </div>
        
        <div class="lesson-section">
            <h3>3. Tactical Flexibility and Adaptation</h3>
            <p><strong>Definition:</strong> Understanding how to adjust tactics based on game situations and opponent strengths.</p>
            
            <div class="key-points">
                <h4>Key Concepts:</h4>
                <ul>
                    <li><span class="fun-icon">🔄</span> Recognizing when to change formation or strategy</li>
                    <li><span class="fun-icon">👥</span> Adjusting to opponent strengths and weaknesses</li>
                    <li><span class="fun-icon">⏱️</span> Game management in different score situations</li>
                    <li><span class="fun-icon">🧠</span> Making quick tactical decisions under pressure</li>
                </ul>
            </div>
        </div>
        
        <div class="video-container">
            <h3>Watch and Learn: Tactical Analysis & Leadership</h3>
            <iframe width="560" height="315" src="https://www.youtube.com/embed/dF7wNjsRjEo" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
            <p><em>Professional examples of tactical awareness and leadership</em></p>
        </div>
        
        <div class="quiz-container">
            <h3>🧠 Final Comprehensive Quiz</h3>
            <p>Test your understanding of physical, tactical, and mental aspects of advanced football.</p>
            
            <div id="quiz5">
                <div class="question" id="q5-1">
                    <h4>1. Which of the following is best for full recovery after intense training?</h4>
                    <div class="option" data-question="1" data-answer="A">A. Sleeping 4 hours and drinking soda</div>
                    <div class="option" data-question="1" data-answer="B">B. Stretching, proper hydration, and 8+ hours of sleep</div>
                    <div class="option" data-question="1" data-answer="C">C. Only lifting weights the next day</div>
                    <div class="option" data-question="1" data-answer="D">D. Skipping meals and resting the legs only</div>
                    <div class="feedback hidden" id="feedback5-1"></div>
                </div>
                
                <div class="question" id="q5-2">
                    <h4>2. In which tactical formation are wing-backs most active in both attack and defense?</h4>
                    <div class="option" data-question="2" data-answer="A">A. 4-4-2</div>
                    <div class="option" data-question="2" data-answer="B">B. 3-5-2</div>
                    <div class="option" data-question="2" data-answer="C">C. 4-3-3</div>
                    <div class="option" data-question="2" data-answer="D">D. 4-2-4</div>
                    <div class="feedback hidden" id="feedback5-2"></div>
                </div>
                
                <div class="question" id="q5-3">
                    <h4>3. What is a key trait of a good on-field leader?</h4>
                    <div class="option" data-question="3" data-answer="A">A. Playing the loudest music in the locker room</div>
                    <div class="option" data-question="3" data-answer="B">B. Always being the best player technically</div>
                    <div class="option" data-question="3" data-answer="C">C. Staying quiet and leading by example only</div>
                    <div class="option" data-question="3" data-answer="D">D. Communicating clearly and motivating teammates</div>
                    <div class="feedback hidden" id="feedback5-3"></div>
                </div>
                
                <div class="question" id="q5-4">
                    <h4>4. Why is tactical flexibility important for a team?</h4>
                    <div class="option" data-question="4" data-answer="A">A. It confuses the coach</div>
                    <div class="option" data-question="4" data-answer="B">B. It helps teams buy time</div>
                    <div class="option" data-question="4" data-answer="C">C. It allows teams to adapt to different opponents and match situations</div>
                    <div class="option" data-question="4" data-answer="D">D. It guarantees winning</div>
                    <div class="feedback hidden" id="feedback5-4"></div>
                </div>
                
                <div class="question" id="q5-5">
                    <h4>5. What is the biggest benefit of decision-making under pressure?</h4>
                    <div class="option" data-question="5" data-answer="A">A. You get to show off tricks</div>
                    <div class="option" data-question="5" data-answer="B">B. It helps avoid injury</div>
                    <div class="option" data-question="5" data-answer="C">C. It leads to smarter play and better team outcomes</div>
                    <div class="option" data-question="5" data-answer="D">D. It slows down the game</div>
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
            "1-1": "A",
            "1-2": "C",
            "1-3": "D",
            "1-4": "C",
            "1-5": "B",
            // Module 2
            "2-1": "C",
            "2-2": "A",
            "2-3": "B",
            "2-4": "B",
            "2-5": "B",
            // Module 3
            "3-1": "C",
            "3-2": "C",
            "3-3": "C",
            "3-4": "C",
            "3-5": "B",
            // Module 4
            "4-1": "C",
            "4-2": "B",
            "4-3": "B",
            "4-4": "C",
            "4-5": "B",
            // Module 5
            "5-1": "B",
            "5-2": "B",
            "5-3": "D",
            "5-4": "C",
            "5-5": "C"
        };
        
        // Explanations for each question
        const explanations = {
            // Module 1
            "1-1": "The 4-4-2 formation provides a solid defensive block and two forwards who can quickly transition into attack, making it effective for counter-attacking.",
            "1-2": "A defensive midfielder's key role is to protect the defense by breaking up opponent attacks and to initiate offensive plays by distributing the ball.",
            "1-3": "A player is not offside if they are in their own half, level with the second-last defender, or receiving the ball from a goal kick, corner kick, or throw-in.",
            "1-4": "A 'false 9' drops deep from the forward position, drawing center-backs out of position and creating space for attacking midfielders or wingers to run into.",
            "1-5": "Taking off your shirt during a goal celebration is considered unsporting behavior and is a mandatory yellow card offense according to FIFA rules.",
            // Module 2
            "2-1": "In tight spaces, a quick body feint combined with a sharp change of direction is highly effective for unbalancing defenders and creating an opening.",
            "2-2": "Excessive dribbling can lead to losing possession in dangerous areas, disrupting the team's attacking shape, and preventing faster ball movement.",
            "2-3": "The 'La Croqueta' is a rapid inside-to-inside foot switch, ideal for escaping a defender who is pressing you side-by-side in a tight situation.",
            "2-4": "A player should decide to dribble when they identify open space to exploit or when facing an isolated defender they can realistically beat.",
            "2-5": "When dribbling to draw defenders, it's crucial to keep your head up, observe your teammates' movements, and time your pass precisely to release them into space.",
            // Module 3
            "3-1": "The main purpose of a through ball is to penetrate the opponent's defensive line by sending a teammate into open space behind the defenders.",
            "3-2": "A 'third-man combination' involves a player passing to a teammate, who then immediately passes to a third player making a run, effectively bypassing pressure.",
            "3-3": "Switching play is most effective when one side of the field is congested with defenders, and the opposite side has open space that can be exploited.",
            "3-4": "The central midfielder, often a deep-lying playmaker, is typically responsible for controlling the game's tempo through their passing and distribution.",
            "3-5": "In high-level matches, quick combination plays rely heavily on clear communication (verbal and non-verbal) and intelligent off-ball movement to create passing options and exploit gaps.",
            // Module 4
            "4-1": "In a 1-on-1 situation with the goalkeeper, a composed finish with controlled placement (e.g., into a corner) and observing the keeper's movement significantly increases scoring chances over a wild power shot.",
            "4-2": "Developing your weaker foot for shooting allows you to take shots without needing to reposition the ball or yourself, saving valuable time and creating more scoring opportunities.",
            "4-3": "An inside-foot curl is a technique used for free kicks (and open play shots) where the ball is struck with the inside of the foot to impart spin, causing it to curve and dip over a defensive wall.",
            "4-4": "During a penalty kick, maintaining confidence and focus is paramount. It allows the player to execute their chosen technique and placement despite the immense pressure.",
            "4-5": "Finishing from a cut-back pass is advantageous because it often allows the attacking player to face the goal with defenders trailing behind, providing a clearer shooting opportunity.",
            // Module 5
            "5-1": "Optimal recovery after intense training involves a combination of proper stretching, adequate hydration, sufficient sleep (8+ hours), and good nutrition to repair muscles and replenish energy.",
            "5-2": "The 3-5-2 formation utilizes wing-backs who are expected to cover the entire flank, contributing significantly to both defensive duties and attacking width.",
            "5-3": "A good on-field leader not only leads by example through their play but also communicates clearly, provides instructions, and motivates their teammates to perform better.",
            "5-4": "Tactical flexibility allows a team to change its formation, pressing intensity, or attacking approach during a match, enabling them to adapt to different opponents, game states, or unexpected challenges.",
            "5-5": "The ability to make quick, intelligent decisions under pressure is a hallmark of advanced players. It leads to smarter passes, better shot selections, and overall improved team outcomes in fast-paced game situations."
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
        
        // Get the text for the correct answer
        function getCorrectAnswerText(questionId) {
            const [moduleNum, qNum] = questionId.split('-');
            const correctKey = correctAnswers[questionId];
            
            const options = document.querySelectorAll(`#q${moduleNum}-${qNum} .option`);
            for (const option of options) {
                if (option.getAttribute('data-answer') === correctKey) {
                    return option.textContent;
                }
            }
            return '';
        }
        
        // Get a result message based on score
        function getResultMessage(moduleNum, score) {
            if (score === 5) {
                return "Perfect! You've mastered this module!";
            } else if (score >= 3) {
                return "Good job! You've passed this module.";
            } else {
                return "Keep trying! You'll get it next time.";
            }
        }
        
        // Unlock a module
        function unlockModule(moduleNum) {
            moduleButtons[moduleNum].classList.remove('locked');
            moduleButtons[moduleNum].textContent = moduleButtons[moduleNum].textContent.replace(" 🔒", "");
        }
        
        // Update progress indicators
        function updateProgress() {
            for (let i = 1; i <= 5; i++) {
                if (moduleStatus[i]) {
                    progressIndicators[i].classList.add('completed');
                } else {
                    progressIndicators[i].classList.remove('completed');
                }
            }
            
            // Save progress to localStorage
            saveProgress();
        }
        
        // Save progress to localStorage
        function saveProgress() {
            localStorage.setItem('moduleProgress', JSON.stringify(moduleStatus));
        }
        
        // Load progress from localStorage
        function loadProgress() {
            const savedProgress = localStorage.getItem('moduleProgress');
            if (savedProgress) {
                const progress = JSON.parse(savedProgress);
                
                for (let i = 1; i <= 5; i++) {
                    if (progress[i]) {
                        moduleStatus[i] = true;
                        progressIndicators[i].classList.add('completed');
                        
                        // Unlock this module and previous ones
                        for (let j = 1; j <= i; j++) {
                            if (moduleButtons[j]) {
                                moduleButtons[j].classList.remove('locked');
                                moduleButtons[j].textContent = moduleButtons[j].textContent.replace(" 🔒", "");
                            }
                        }
                    }
                }
            }
        }
    </script>
</body>
</html>