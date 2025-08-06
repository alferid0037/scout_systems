<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate inputs
    $errors = [];
    
    if (empty($username)) {
        $errors[] = "Username is required";
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (!isPasswordStrong($password)) {
        $errors[] = "Password must be at least 8 characters with uppercase, lowercase, number, and special character";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    // Check if email exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->rowCount() > 0) {
        $errors[] = "Email already registered";
    }
    
    // Check if username exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->rowCount() > 0) {
        $errors[] = "Username already taken";
    }
    
    if (empty($errors)) {
        // Generate verification code
        $verification_code = generateVerificationCode();
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert user into database
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, verification_code) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$username, $email, $hashed_password, $verification_code])) {
            // Send verification email
            if (sendVerificationEmail($email, $verification_code)) {
                $_SESSION['email'] = $email;
                $_SESSION['verification_sent'] = true;
                redirect('verify-code.php');
            } else {
                $errors[] = "Failed to send verification email";
            }
        } else {
            $errors[] = "Registration failed. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Ethio Online Scouting System</title>
    <link rel="stylesheet" href="signup.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@200..1000&family=Rubik:wght@300..900&family=Bebas+Neue&family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    <title>Sign Up</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Montserrat', 'Arial', sans-serif;
        }

        body {
            background: url('https://images.unsplash.com/photo-1574629810360-7efbbe195018') no-repeat center center fixed;
            background-size: cover;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: #333;
        }

        .container {
            display: flex;
            width: 900px;
            background-color: rgba(255, 255, 255, 0.95);
            border-radius: 8px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            border: 3px solid #1a3e72;
        }

        .left {
            flex: 1;
            background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), 
                        url('https://images.unsplash.com/photo-1540747913346-19e32dc3e97e') center/cover no-repeat;
            color: white;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            text-align: center;
            position: relative;
        }

        .left::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(26, 62, 114, 0.8);
            z-index: 0;
        }

        .left h2, .left p {
            position: relative;
            z-index: 1;
        }

        .left h2 {
            font-size: 36px;
            margin-bottom: 15px;
            color: #fff;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 700;
        }

        .left p {
            font-size: 18px;
            opacity: 0.9;
            margin-bottom: 30px;
        }

        .right {
            flex: 1;
            padding: 40px 50px;
            background-color: #fff;
        }

        .right h2 {
            font-size: 28px;
            margin-bottom: 30px;
            color: #1a3e72;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 700;
            position: relative;
            padding-bottom: 10px;
        }

        .right h2::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background: #f0a500;
        }

        form div {
            position: relative;
            margin-bottom: 25px;
        }

        form label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #1a3e72;
        }

        form input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            transition: all 0.3s;
            background-color: #f9f9f9;
        }

        form input:focus {
            border-color: #1a3e72;
            outline: none;
            box-shadow: 0 0 0 3px rgba(26, 62, 114, 0.2);
            background-color: #fff;
        }

        button[type="submit"] {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #1a3e72, #2a5ca7);
            border: none;
            border-radius: 4px;
            color: white;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 10px;
        }

        button[type="submit"]:hover {
            background: linear-gradient(135deg, #2a5ca7, #3a7cd9);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(26, 62, 114, 0.3);
        }

        .register {
            text-align: center;
            margin-top: 25px;
            color: #666;
            font-size: 15px;
        }

        .register a {
            color: #1a3e72;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .register a:hover {
            color: #f0a500;
            text-decoration: underline;
        }

        .error {
            color: #d32f2f;
            background-color: #fdecea;
            padding: 12px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid #d32f2f;
        }

        .error p {
            margin-bottom: 5px;
        }

        .error p:last-child {
            margin-bottom: 0;
        }

        .scout-badge {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            display: block;
            position: relative;
            z-index: 1;
        }

        .tagline {
            font-style: italic;
            margin-top: 20px;
            color: #f0a500;
            font-weight: 500;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
                width: 95%;
                margin: 20px 0;
            }
            
            .left, .right {
                padding: 30px;
            }
            
            .left {
                padding-bottom: 20px;
            }
            
            body {
                background-attachment: scroll;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="left">
            <img src="https://cdn-icons-png.flaticon.com/512/53/53283.png" alt="Football Scout Logo" class="scout-badge">
            <h2>Join Our Team!</h2>
            <p>Register to access our exclusive football scouting network.</p>
            <p class="tagline">"Discovering tomorrow's champions today"</p>
        </div>
        <div class="right">
            <h2>Sign Up</h2>
            <?php if (!empty($errors)): ?>
                <div class="error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <form method="post">
                <div>
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div>
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div>
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div>
                    <label for="confirm_password">Confirm Password:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                <button type="submit">Sign Up</button>
            </form>
            <div class="register">
                <p>Already have an account? <a href="signin.php">sign in here</a></p>
            </div>
        </div>
    </div>
</body>
</html>