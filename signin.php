<?php
require_once 'config.php';

if (isLoggedIn()) {
    redirect('signin.php');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    
    // Validate inputs
    $errors = [];
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    }
    
    if (empty($errors)) {
        // Check user credentials
        $stmt = $pdo->prepare("SELECT id, password, is_verified FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            if ($user['is_verified']) {
                // Login successful
                $_SESSION['user_id'] = $user['id'];
                redirect('dashboard.php');
            } else {
                // User not verified
                $_SESSION['email'] = $email;
                $_SESSION['verification_sent'] = true;
                redirect('verify-code.php');
            }
        } else {
            $errors[] = "Invalid email or password";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="login.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@200..1000&family=Rubik:wght@300..900&family=Bebas+Neue&family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    <title>Login</title>
    <style>
     /* login.css */
:root {
    --primary: #3562A6;
    --secondary: #0E1EB5;
    --dark: #0C1E2E;
    --light: #F5F5F5;
    --accent: #078930;
    --text: #333333;
    --error: #e74c3c;
}

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

        .btn {
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

        .btn:hover {
            background: linear-gradient(135deg, #2a5ca7, #3a7cd9);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(26, 62, 114, 0.3);
        }

        .options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 20px 0;
            font-size: 14px;
        }

        .options label {
            display: flex;
            align-items: center;
            color: #555;
            font-weight: 500;
            cursor: pointer;
        }

        .options input[type="checkbox"] {
            width: auto;
            margin-right: 8px;
        }

        .options a {
            color: #1a3e72;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .options a:hover {
            color: #f0a500;
            text-decoration: underline;
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
            <h2>Welcome Back!</h2>
            <p>Sign in to access your scouting dashboard.</p>
            <p class="tagline">"Tracking talent, creating opportunities"</p>
        </div>
        <div class="right">
    <h2>signin</h2>
    <?php if (!empty($errors)): ?>
        <div class="error">
            <?php foreach ($errors as $error): ?>
                <p><?php echo htmlspecialchars($error); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <form method="post">
                <div>
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" required placeholder="Enter your email">
                </div>
                <div>
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required placeholder="Enter your password">
                </div>
                
                <div class="options">
                    <label>
                        <input name="remember_me" type="checkbox"> Remember me
                    </label>
                    <a href="forgot-password.php">Forgot password?</a>
                </div>
                
                <button type="submit" class="btn">Sign In</button>
            </form>
            
            <div class="register">
                New here? <a href="signup.php">Create an Account</a>
            </div>
        </div>
    </div>
</body>
</html>