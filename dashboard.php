<?php
require_once 'config.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    redirect('signin.php');
}

// Get user data
$stmt = $pdo->prepare("SELECT username, email, created_at FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Get player registration data
function getPlayerRegistration($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM player_registrations WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Calculate age function
function calculateAge($day, $month, $year) {
    $birth_date = new DateTime("$year-$month-$day");
    $today = new DateTime();
    return $today->diff($birth_date)->y;
}

// Get course progress based on age
function getCourseProgress($user_id) {
    global $pdo;

    // First, get the player's registration to determine their age
    $registration = getPlayerRegistration($user_id);
    if (!$registration) {
        return []; // No registration, no courses
    }

    $player_age = calculateAge($registration['birth_day'], $registration['birth_month'], $registration['birth_year']);
    $age_group_key = '';

    if ($player_age >= 6 && $player_age <= 8) {
        $age_group_key = '6-8';
    } elseif ($player_age >= 9 && $player_age <= 11) {
        $age_group_key = '9-11';
    } elseif ($player_age >= 12 && $player_age <= 14) {
        $age_group_key = '12-14';
    } elseif ($player_age >= 15 && $player_age <= 18) {
        $age_group_key = '15-18';
    }

    if (empty($age_group_key)) {
        return []; // Age is outside the defined groups
    }

    // The new course structure provided by the user
    // In a real application, this would likely be fetched from the database
    $courses_by_age = [
        "1" => [
            "title" => "Module 1: Introduction to Football & Basic Rules",
            "content" => [
                "6-8" => ["Understand what football is", "Learn the basic rules (no hands, out of bounds, goals)", "Simple games to practice ball control", "Quiz"],
                "9-11" => ["Detailed rules overview", "Importance of teamwork", "Basic positions on the field", "Quiz"],
                "12-14" => ["Understanding offside rule", "Fouls and penalties explained", "Referee signals and game flow", "Quiz"],
                "15-18" => ["In-depth study of game strategies", "Role of different positions tactically", "Analysis of professional matches for rules", "Quiz"]
            ]
        ],
        "2" => [
            "title" => "Module 2: Ball Control & Dribbling",
            "content" => [
                "6-8" => ["Basic dribbling with feet", "Simple ball stops and starts", "Fun obstacle dribbling drills", "Quiz"],
                "9-11" => ["Using different parts of the foot", "Changing direction while dribbling", "Shielding the ball from opponents", "Quiz"],
                "12-14" => ["Advanced dribbling moves (step-overs, feints)", "Dribbling under pressure", "One-on-one dribbling drills", "Quiz"],
                "15-18" => ["Creative dribbling techniques", "Dribbling in tight spaces", "Integrating dribbling into team play", "Quiz"]
            ]
        ],
        "3" => [
            "title" => "Module 3: Passing & Receiving",
             "content" => [
                "6-8" => ["Simple short passes with inside foot", "Basic receiving and controlling the ball", "Passing games in pairs", "Quiz"],
                "9-11" => ["Passing accuracy drills", "Receiving with different body parts (chest, thigh)", "Introduction to long passes", "Quiz"],
                "12-14" => ["Passing under pressure", "One-touch passing drills", "Communication during passing", "Quiz"],
                "15-18" => ["Tactical passing (through balls, switches)", "Quick combination plays", "Analyzing passing in real matches", "Quiz"]
            ]
        ],
        "4" => [
            "title" => "Module 4: Shooting & Scoring",
            "content" => [
                 "6-8" => ["Basic shooting techniques with inside foot", "Target practice (shooting at goals)", "Fun shooting games", "Quiz"],
                 "9-11" => ["Shooting with laces for power", "Accuracy and placement drills", "Shooting on the move", "Quiz"],
                 "12-14" => ["Shooting under pressure", "Volley and half-volley shooting", "Penalty kick basics", "Quiz"],
                 "15-18" => ["Finishing techniques in different scenarios", "Shooting with both feet", "Advanced penalty and free kick techniques", "Quiz"]
            ]
        ],
        "5" => [
            "title" => "Module 5: Fitness & Team Play",
            "content" => [
                "6-8" => ["Basic warm-ups and stretches", "Fun fitness games to improve stamina", "Introduction to playing as a team", "Final quiz"],
                "9-11" => ["Endurance and speed drills", "Understanding positions in team play", "Basic tactical awareness", "Final quiz"],
                "12-14" => ["Position-specific fitness", "Team formations and roles", "Communication on the field", "Final quiz"],
                "15-18" => ["Advanced conditioning and recovery", "In-depth tactical formations", "Leadership and decision making", "Final quiz"]
            ]
        ]
    ];

    $age_specific_modules = [];
    foreach ($courses_by_age as $module_number => $module_data) {
        $age_specific_modules[] = [
            'id' => $module_number, // Using the key as a mock ID
            'module_number' => $module_number,
            'title' => $module_data['title'],
            'content_for_age' => $module_data['content'][$age_group_key]
        ];
    }

    // Now, fetch the user's progress for these modules
    $stmt = $pdo->prepare("
        SELECT module_id, progress_percentage, is_completed
        FROM player_progress
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $progress_map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $progress_map[$row['module_id']] = $row;
    }

    // Combine the course structure with the user's progress
    $final_course_progress = [];
    foreach ($age_specific_modules as $module) {
        $module_id = $module['id'];
        $module['progress_percentage'] = $progress_map[$module_id]['progress_percentage'] ?? 0;
        $module['is_completed'] = $progress_map[$module_id]['is_completed'] ?? false;
        $final_course_progress[] = $module;
    }

    return $final_course_progress;
}


// Get notifications
function getNotifications($user_id, $limit = 5) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM notifications
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$user_id, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting notifications: " . $e->getMessage());
        return [];
    }
}

// Handle profile updates (existing code)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_type'])) {
    $update_type = $_POST['update_type'];

    if ($update_type === 'profile') {
        $new_username = trim($_POST['username']);
        $new_email = trim($_POST['email']);

        // Handle profile photo upload
        $profile_photo_path = '';
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $file_extension = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png'];
            $max_file_size = 2 * 1024 * 1024; // 2MB

            if (!in_array($file_extension, $allowed_extensions)) {
                $_SESSION['error'] = 'Only JPG, JPEG, and PNG files are allowed for profile photos.';
            } elseif ($_FILES['profile_photo']['size'] > $max_file_size) {
                $_SESSION['error'] = 'Profile photo file size exceeds the maximum allowed size (2MB).';
            } else {
                $photo_name = 'profile_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_extension;
                if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $upload_dir . $photo_name)) {
                    $profile_photo_path = $photo_name;
                } else {
                    $_SESSION['error'] = 'Failed to upload profile photo.';
                }
            }
        }

        // Validate inputs
        if (empty($new_username) || empty($new_email)) {
            $_SESSION['error'] = 'Username and email are required.';
        } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = 'Please enter a valid email address.';
        } else {
            // Check if email already exists (excluding current user)
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$new_email, $_SESSION['user_id']]);
            if ($stmt->fetch()) {
                $_SESSION['error'] = 'Email address already exists.';
            } else {
                // Update profile
                $query = "UPDATE users SET username = ?, email = ? WHERE id = ?";
                $params = [$new_username, $new_email, $_SESSION['user_id']];

                // If a new profile photo was uploaded, update player_registrations as well
                if (!empty($profile_photo_path)) {
                    // Update user's profile_photo_path (if such a column exists in 'users' table)
                    $stmt_update_user_photo = $pdo->prepare("UPDATE users SET profile_photo_path = ? WHERE id = ?");
                    $stmt_update_user_photo->execute([$profile_photo_path, $_SESSION['user_id']]);

                    // Also update player_registrations table if a registration exists
                    $existing_registration = getPlayerRegistration($_SESSION['user_id']);
                    if ($existing_registration) {
                        $stmt_update_player_reg = $pdo->prepare("UPDATE player_registrations SET photo_path = ? WHERE user_id = ?");
                        $stmt_update_player_reg->execute([$profile_photo_path, $_SESSION['user_id']]);
                    }
                }

                $stmt = $pdo->prepare($query);
                if ($stmt->execute($params)) {
                    $_SESSION['success'] = 'Profile updated successfully!';
                    // Refresh user data
                    $user = $pdo->prepare("SELECT username, email, created_at, profile_photo_path FROM users WHERE id = ?");
                    $user->execute([$_SESSION['user_id']]);
                    $user = $user->fetch();

                } else {
                    $_SESSION['error'] = 'Failed to update profile. Please try again.';
                }
            }
        }
    } elseif ($update_type === 'password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        // Validate inputs
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $_SESSION['error'] = 'All password fields are required.';
        } elseif (strlen($new_password) < 8) {
            $_SESSION['error'] = 'New password must be at least 8 characters long.';
        } elseif ($new_password !== $confirm_password) {
            $_SESSION['error'] = 'New passwords do not match.';
        } else {
            // Verify current password
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $stored_password = $stmt->fetch()['password'];

            if (password_verify($current_password, $stored_password)) {
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                if ($stmt->execute([$hashed_password, $_SESSION['user_id']])) {
                    $_SESSION['success'] = 'Password updated successfully!';
                } else {
                    $_SESSION['error'] = 'Failed to update password. Please try again.';
                }
            } else {
                $_SESSION['error'] = 'Current password is incorrect.';
            }
        }
    }

    header('Location: dashboard.php');
    exit();
}

// Get data for dashboard
$registration = getPlayerRegistration($_SESSION['user_id']);
$course_progress = getCourseProgress($_SESSION['user_id']);
$notifications = getNotifications($_SESSION['user_id']);

// Calculate progress statistics
$total_modules = count($course_progress);
$completed_modules = 0;
foreach ($course_progress as $module) {
    if ($module['is_completed']) {
        $completed_modules++;
    }
}

// Get display name (prefer registration name, fallback to username)
$display_name = $user['username'];
if ($registration && !empty($registration['first_name'])) {
    $display_name = $registration['first_name'] . ' ' . $registration['last_name'];
}

// Determine the correct course link based on age group
$player_age_group_link = '';
if ($registration) {
    $player_age = calculateAge($registration['birth_day'], $registration['birth_month'], $registration['birth_year']);
    if ($player_age >= 6 && $player_age <= 8) {
        $player_age_group_link = 'courses%20age%206-8.php';
    } elseif ($player_age >= 9 && $player_age <= 11) {
        $player_age_group_link = 'courses%20age%209-11.php';
    } elseif ($player_age >= 12 && $player_age <= 14) {
        $player_age_group_link = 'courses%20age%2012-14.php';
    } elseif ($player_age >= 15 && $player_age <= 18) {
        $player_age_group_link = 'courses%20age%2015-18.php';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Dashboard - Ethio Scout</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@200..1000&family=Rubik:wght@300..900&family=Bebas+Neue&family=Montserrat:wght@400;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #3562A6;
            --secondary: #0E1EB5;
            --dark: #0C1E2E;
            --light: #F5F5F5;
            --accent: #078930;
            --text: #333333;
        }

        body {
            margin: 0;
            font-family: 'Montserrat', sans-serif;
            background-color: var(--light);
            color: var(--text);
            line-height: 1.6;
        }

        nav {
            background: linear-gradient(135deg, var(--dark) 0%, #000 100%);
            color: white;
            padding: 15px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 4px solid var(--primary);
            height: 80px;
        }

        .logo-container {
            display: flex;
            align-items: center;
        }

        .logo-container img {
            height: 50px;
            border-radius: 100%;
            margin-right: 15px;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
        }

        .logo-container::after {
            content: "ETHIO SCOUT";
            font-family: 'Bebas Neue', sans-serif;
            font-size: 1.8rem;
            color: var(--primary);
            letter-spacing: 1px;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 25px;
        }

        .nav-links a {
            text-decoration: none;
            color: white;
            font-weight: 600;
            font-size: 1rem;
            padding: 8px 12px;
            border-radius: 4px;
            transition: all 0.3s ease;
            position: relative;
        }

        .nav-links a:hover {
            color: var(--primary);
            transform: translateY(-2px);
        }

        .nav-links a::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: 0;
            left: 0;
            background-color: var(--primary);
            transition: width 0.3s ease;
        }

        .nav-links a:hover::after {
            width: 100%;
        }

        .user-welcome {
            color: var(--primary);
            font-weight: 600;
            margin-right: 15px;
            font-size: 1rem;
        }

        .profile-dropdown {
            position: relative;
            display: flex;
            margin-left: 15px;
        }

        .profile-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .profile-icon i {
            font-size: 1.5rem;
            color: white;
        }

        .profile-icon:hover {
            background-color: var(--primary);
            transform: scale(1.1);
        }

        .profile-content {
            display: none;
            position: absolute;
            top: 50px;
            right: 0;
            background-color: white;
            min-width: 220px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
            z-index: 1000;
            border-radius: 8px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .profile-content.active {
            display: block;
        }

        .profile-header {
            padding: 15px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .profile-pic {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid white;
        }

        .profile-name {
            font-weight: 600;
            font-size: 0.9rem;
        }

        .profile-content a {
            color: var(--text);
            padding: 12px 16px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }

        .profile-content a:hover {
            background-color: #f5f5f5;
            color: var(--primary);
            padding-left: 20px;
        }

        .profile-content a:not(:last-child) {
            border-bottom: 1px solid #eee;
        }

        /* Hero Section */
        #container {
            background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)),
                        url('/placeholder.svg?height=400&width=1200') no-repeat center center/cover;
            height: 60vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            color: white;
        }

        .sliding-header {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 3rem;
            letter-spacing: 2px;
            margin-bottom: 20px;
            color: var(--primary);
        }

        .hero-subtitle {
            font-size: 1.2rem;
            max-width: 600px;
            margin-bottom: 30px;
        }

        .hero-actions {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .hero-btn {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 15px 40px;
            font-size: 1.1rem;
            font-weight: 700;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(53, 98, 166, 0.4);
            text-transform: uppercase;
            letter-spacing: 1px;
            position: relative;
            overflow: hidden;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .hero-btn:hover {
            background-color: var(--secondary);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(14, 30, 181, 0.6);
        }

        .hero-btn.course-btn {
            background-color: var(--accent);
            box-shadow: 0 4px 15px rgba(7, 137, 48, 0.4);
        }

        .hero-btn.course-btn:hover {
            background-color: #065a28;
            box-shadow: 0 6px 20px rgba(7, 137, 48, 0.6);
        }

        /* Dashboard Styles */
        .dashboard-container {
            display: none;
            padding: 30px 5%;
            max-width: 1200px;
            margin: 0 auto;
        }

        .dashboard-container.active {
            display: block;
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--primary);
        }

        .dashboard-title {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 2.5rem;
            color: var(--primary);
            margin: 0;
        }

        .dashboard-content {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        /* Profile Dashboard */
        .profile-info {
            display: flex;
            gap: 30px;
            margin-bottom: 30px;
        }

        .profile-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid var(--primary);
        }

        .profile-details {
            flex: 1;
        }

        .profile-name-large {
            font-size: 1.8rem;
            margin: 0 0 10px 0;
            color: var(--primary);
        }

        .status-badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-top: 10px;
            display: inline-block;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-approved {
            background: #d4edda;
            color: #155724;
        }

        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .progress-bar {
            width: 100%;
            height: 20px;
            background: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            margin: 15px 0;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            transition: width 0.3s ease;
        }

        .course-list {
            list-style: none;
            padding: 0;
        }

        .course-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }

        .course-item:last-child {
            border-bottom: none;
        }

        .course-status {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-in-progress {
            background: #fff3cd;
            color: #856404;
        }

        .btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn:hover {
            background: var(--secondary);
            transform: translateY(-2px);
        }

        .btn-disabled {
            background: #6c757d;
            cursor: not-allowed;
        }

        .btn-disabled:hover {
            background: #6c757d;
            transform: none;
        }

        .notification-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            transition: background 0.3s ease;
        }

        .notification-item:hover {
            background: #f8f9fa;
        }

        .notification-title {
            font-weight: 600;
            margin-bottom: 5px;
        }

        .notification-time {
            font-size: 0.8rem;
            color: #666;
        }

        .auto-assignment-notice {
            background: rgba(7, 137, 48, 0.1);
            color: var(--accent);
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
            border: 1px solid rgba(7, 137, 48, 0.2);
        }

        .auto-assignment-notice i {
            font-size: 1.2rem;
        }

        /* Settings Styles */
        .settings-tabs {
            display: flex;
            list-style: none;
            padding: 0;
            margin: 0 0 20px 0;
            border-bottom: 2px solid #eee;
        }

        .settings-tabs li {
            padding: 10px 20px;
            cursor: pointer;
            font-weight: 600;
            color: #666;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
        }

        .settings-tabs li:hover {
            color: var(--primary);
        }

        .settings-tabs li.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }

        .settings-tab-content {
            display: none;
        }

        .settings-tab-content.active {
            display: block;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text);
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5ee;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s;
            background-color: var(--light);
            box-sizing: border-box;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(53, 98, 166, 0.2);
            background-color: white;
        }

        .save-button {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .save-button:hover {
            background-color: var(--secondary);
            transform: translateY(-2px);
        }

        /* Footer */
        footer {
            background-color: var(--dark);
            color: white;
            padding: 60px 5% 30px;
            position: relative;
            margin-top: 50px;
        }

        footer::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 10px;
            background: linear-gradient(90deg, var(--primary), var(--secondary), var(--accent));
        }

        .footer-container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
        }

        .footer-section h3 {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 1.8rem;
            color: var(--primary);
            margin-bottom: 20px;
            letter-spacing: 1px;
            position: relative;
        }

        .footer-section h3::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 0;
            width: 50px;
            height: 3px;
            background-color: var(--secondary);
        }

        .footer-section p, .footer-section a {
            color: #ddd;
            margin-bottom: 10px;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .footer-section a:hover {
            color: var(--primary);
            padding-left: 5px;
        }

        .social-links {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .social-links a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background-color: rgba(255,255,255,0.1);
            border-radius: 50%;
            color: white;
            font-size: 1.2rem;
            transition: all 0.3s ease;
        }

        .social-links a:hover {
            background-color: var(--primary);
            color: var(--dark);
            transform: translateY(-3px);
        }

        .map-embed iframe {
            width: 100%;
            height: 200px;
            border: none;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .copyright {
            grid-column: 1 / -1;
            text-align: center;
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            color: #aaa;
            font-size: 0.9rem;
        }

        /* Alert Messages */
        .alert {
            position: fixed;
            top: 90px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 8px;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            max-width: 400px;
            animation: slideIn 0.3s ease-out;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .profile-info {
                flex-direction: column;
                text-align: center;
                align-items: center;
            }

            .sliding-header {
                font-size: 2rem;
            }

            .hero-actions {
                flex-direction: column;
                align-items: center;
            }

            .user-welcome {
                display: none;
            }

            .footer-container {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .footer-section h3::after {
                left: 50%;
                transform: translateX(-50%);
            }

            .social-links {
                justify-content: center;
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

    <!-- Navigation -->
    <nav>
        <div class="logo-container">
            <img src="images\Football%20Award%20Vector.jpg" alt="Ethio Scout Logo">
        </div>

        <div class="nav-links">
            <a href="home.php">Main Site</a>
            <?php if ($registration && !empty($player_age_group_link)): ?>
                <a style="
                    background: linear-gradient(135deg, var(--dark) 0%, #000 100%);
                    color: white;
                    padding: 15px 5%;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
                    position: sticky;
                    top: 0;
                    z-index: 1000;
                    border-bottom: 4px solid var(--primary);
                    height: 40px;
                " href="<?php echo htmlspecialchars($player_age_group_link); ?>" class="hero-btn course-btn">
                    My Training Course
                </a>
            <?php endif; ?>
            <span class="user-welcome">Welcome, <?php echo htmlspecialchars($display_name); ?></span>

            <div class="profile-dropdown">
                <div class="profile-icon">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="profile-content">
                    <div class="profile-header">
                        <?php if ($registration && !empty($registration['photo_path'])): ?>
                            <img src="uploads/<?php echo htmlspecialchars($registration['photo_path']); ?>" alt="Profile Picture" class="profile-pic">
                        <?php else: ?>
                            <img src="/placeholder.svg?height=40&width=40" alt="Profile Picture Placeholder" class="profile-pic">
                        <?php endif; ?>
                        <span class="profile-name"><?php echo htmlspecialchars($display_name); ?></span>
                    </div>
                    <a href="#" class="dashboard-link" data-dashboard="profile"><i class="fas fa-user"></i> View Profile</a>
                    <a href="#" class="dashboard-link" data-dashboard="settings"><i class="fas fa-cog"></i> Settings</a>
                    <a href="#" class="dashboard-link" data-dashboard="notification"><i class="fas fa-bell"></i> Notifications</a>
                    <a href="signin.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
    </nav>


        <!-- Hero Section -->
         <div id="hero-section">
        <div id="container">
            <div class="container">
                <div class="sliding-header">
                    <span>ETHIO VIRTUAL SCOUTING SYSTEM</span>
                </div>
                <p class="hero-subtitle">"Empower youth through Ethiopia's Online Scouting System! Build skills, foster leadership, and unite for a brighter, impactful future together!"</p>

                <div class="hero-actions">
                    <?php if ($registration && !empty($player_age_group_link)): ?>
                        <a href="<?php echo htmlspecialchars($player_age_group_link); ?>" class="hero-btn course-btn">
                            <i class="fas fa-graduation-cap"></i> My Training Course

                        </a>
                    <?php endif; ?>

                    <?php if (!$registration): ?>
                        <a href="player-registration.php" class="hero-btn">
                            <i class="fas fa-user-plus"></i> Register as Player
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
                    </div>
        <!-- Profile Dashboard -->
        <div class="dashboard-container" id="profile-dashboard">
            <div class="dashboard-header">
                <h2 class="dashboard-title">My Profile</h2>
            </div>
            <div class="dashboard-content">
                <div class="profile-info">
                    <?php if ($registration && !empty($registration['photo_path'])): ?>
                        <img src="uploads/<?php echo htmlspecialchars($registration['photo_path']); ?>" alt="Profile Picture" class="profile-avatar">
                    <?php else: ?>
                        <img src="/placeholder.svg?height=150&width=150" alt="Profile Picture Placeholder" class="profile-avatar">
                    <?php endif; ?>
                    <div class="profile-details">
                        <h3 class="profile-name-large">Welcome, <?php echo htmlspecialchars($display_name); ?></h3>
                        <p><strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                        <p>Member since: <?php echo date('F j, Y', strtotime($user['created_at'])); ?></p>
                        <?php if ($registration): ?>
                            <p><strong>Registration Status:</strong>
                                <span class="status-badge status-<?php echo $registration['registration_status'] ?? 'pending'; ?>">
                                    <?php echo ucfirst($registration['registration_status'] ?? 'pending'); ?>
                                    <?php if (($registration['registration_status'] ?? 'pending') === 'pending'): ?>
                                        (waiting approval 5-6 Hours for admin)
                                    <?php endif; ?>
                                </span>
                            </p>
                            <p><strong>Age:</strong> <?php echo calculateAge($registration['birth_day'], $registration['birth_month'], $registration['birth_year']); ?> years</p>
                            <p><strong>Location:</strong> <?php echo htmlspecialchars($registration['city']); ?></p>
                        <?php else: ?>
                            <p>Complete your registration to access all features.</p>
                            <a href="player-registration.php" class="btn">Complete Registration</a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Course Progress Section -->
                <?php if ($registration && isset($registration['registration_status']) && $registration['registration_status'] === 'approved'): ?>
                    <div style="margin-top: 30px;">
                        <h3>Course Progress</h3>
                        <div class="auto-assignment-notice">
                            <i class="fas fa-graduation-cap"></i>
                            <span>Courses automatically assigned based on your age (<?php echo calculateAge($registration['birth_day'], $registration['birth_month'], $registration['birth_year']); ?> years)</span>
                        </div>
                        <p><strong><?php echo $completed_modules; ?></strong> of <strong><?php echo $total_modules; ?></strong> modules completed</p>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $total_modules > 0 ? round(($completed_modules / $total_modules) * 100) : 0; ?>%"></div>
                        </div>
                        <p><?php echo $total_modules > 0 ? round(($completed_modules / $total_modules) * 100) : 0; ?>% Complete</p>

                        <?php if (!empty($course_progress)): ?>
                            <ul class="course-list">
                                <?php foreach ($course_progress as $module): ?>
                                    <li class="course-item">
                                        <div>
                                            <strong>Module <?php echo isset($module['module_number']) ? $module['module_number'] : 'N/A'; ?>:</strong>
                                            <?php echo htmlspecialchars($module['title'] ?? 'Untitled Module'); ?>
                                        </div>
                                        <span class="course-status status-<?php echo $module['is_completed'] ? 'completed' : 'in-progress'; ?>">
                                            <?php echo $module['is_completed'] ? 'Completed' : 'In Progress'; ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <?php if (!empty($player_age_group_link)): ?>
                            <a href="<?php echo htmlspecialchars($player_age_group_link); ?>" class="btn" style="margin-top: 20px;">Start Learning</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Settings Dashboard -->
        <div class="dashboard-container" id="settings-dashboard">
            <div class="dashboard-header">
                <h2 class="dashboard-title">Account Settings</h2>
            </div>
            <div class="dashboard-content">
                <ul class="settings-tabs">
                    <li class="active" data-tab="profile-settings">Profile Settings</li>
                    <li data-tab="password-settings">Change Password</li>
                </ul>

                <!-- Profile Settings Tab -->
                <div class="settings-tab-content active" id="profile-settings">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" class="form-control"
                                   value="<?php echo htmlspecialchars($user['username']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" class="form-control"
                                   value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>

                        <div class="form-group">
              <label for="profile_photo">Profile Photo</label>
              <input type="file" id="profile_photo" name="profile_photo" class="form-control" accept="image/jpeg,image/png">
              <small class="form-text">JPG, PNG only. Max size: 2MB</small>
            </div>

            <input type="hidden" name="update_type" value="profile">
            <button type="submit" class="save-button">Update Profile</button>
                    </form>
                </div>

                <!-- Password Settings Tab -->
                <div class="settings-tab-content" id="password-settings">
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" class="form-control" required>
                            <small style="color: #666; font-size: 0.9rem;">Minimum 8 characters</small>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                        </div>

                        <input type="hidden" name="update_type" value="password">
                        <button type="submit" class="save-button">
                            <i class="fas fa-key"></i> Change Password
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Notification Dashboard -->
        <div class="dashboard-container" id="notification-dashboard">
            <div class="dashboard-header">
                <h2 class="dashboard-title">Notifications</h2>
            </div>
            <div class="dashboard-content">
                <?php if (!empty($notifications)): ?>
                    <?php foreach ($notifications as $notification): ?>
                        <div class="notification-item">
                            <div class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                            <p><?php echo htmlspecialchars($notification['message']); ?></p>
                            <div class="notification-time">
                                <?php echo date('M j, Y g:i a', strtotime($notification['created_at'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No notifications to display.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer id="contact">
        <div class="footer-container">
            <div class="footer-section">
                <h3>Address</h3>
                <p>Addis Ababa Stadium - ADDIS ABEBA</p>
                <p>2Q89+5G7, Addis Ababa</p>
                <p><a href="#">View on Map</a></p>
            </div>
            <div class="footer-section">
                <h3>Contact</h3>
                <p>Phone: +251-11/515 6205</p>
                <p>Email: info@ethioscout.org</p>
                <p>Website: www.ethioscout.org</p>
            </div>
            <div class="footer-section">
                <h3>Social Media</h3>
                <div class="social-links">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-telegram"></i></a>
                    <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    <a href="#"><i class="fab fa-whatsapp"></i></a>
                </div>
            </div>
            <div class="footer-section">
                <h3>Map</h3>
                <div class="map-embed">
                    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3940.869244261849!2d38.76331531536616!3d9.012722893547026!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x164b85f1a4d1f8b5%3A0x7fddde27ad21a9aa!2sEthiopian%20Football%20Federation!5e0!3m2!1sen!2set!4v1633081234567!5m2!1sen!2set" allowfullscreen="" loading="lazy"></iframe>
                </div>
            </div>
            <p class="copyright">© 2024 Ethiopian Football Federation. All rights reserved.</p>
        </div>
    </footer>

    <!-- JavaScript -->
    <script>
        // Profile dropdown
        document.addEventListener('DOMContentLoaded', () => {
            const profileDropdown = document.querySelector('.profile-dropdown');
            const profileContent = profileDropdown.querySelector('.profile-content');

            profileDropdown.addEventListener('click', function (e) {
                e.stopPropagation();
                profileContent.classList.toggle('active');
            });

            document.addEventListener('click', function () {
                profileContent.classList.remove('active');
            });
        });

        // Dashboard navigation
        document.querySelectorAll('.dashboard-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const dashboardId = this.getAttribute('data-dashboard');

                document.querySelectorAll('.dashboard-container').forEach(dashboard => {
                    dashboard.classList.remove('active');
                });
                document.getElementById('hero-section').style.display = 'none'; // Hide hero section

                if (dashboardId) {
                    const targetDashboard = document.getElementById(`${dashboardId}-dashboard`);
                    if (targetDashboard) {
                        targetDashboard.classList.add('active');
                    }
                }

                document.querySelector('.profile-content').classList.remove('active');
            });
        });

        // Tab switching for settings
        document.querySelectorAll('.settings-tabs li').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.settings-tabs li').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.settings-tab-content').forEach(c => c.classList.remove('active'));

                this.classList.add('active');
                const tabId = this.getAttribute('data-tab');
                document.getElementById(tabId).classList.add('active');
            });
        });

        // Animate sliding header letters
        const slidingHeader = document.querySelector('.sliding-header span');
        if (slidingHeader) {
            const text = slidingHeader.textContent;
            slidingHeader.textContent = '';

            text.split('').forEach((letter, i) => {
                const span = document.createElement('span');
                span.textContent = letter === ' ' ? '\u00A0' : letter;
                span.style.animationDelay = `${i * 0.05}s`;
                slidingHeader.appendChild(span);
            });
        }

        // Form validation for password change
        document.getElementById('password-settings').querySelector('form').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New passwords do not match!');
                return false;
            }

            if (newPassword.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long!');
                return false;
            }
        });

        // Client-side file size validation for profile photo
        document.getElementById('profile-settings').querySelector('form').addEventListener('submit', function(e) {
            const profilePhotoInput = document.getElementById('profile_photo');
            if (profilePhotoInput.files.length > 0) {
                const file = profilePhotoInput.files[0];
                const maxSize = 2 * 1024 * 1024; // 2MB

                if (file.size > maxSize) {
                    e.preventDefault();
                    alert('Profile photo file size exceeds the maximum allowed size (2MB).');
                    return false;
                }
            }
        });
    </script>
</body>
</html>