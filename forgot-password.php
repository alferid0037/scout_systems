<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = sanitize($_POST['email']);
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Valid email is required";
    } else {
        // Check if email exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->rowCount() > 0) {
            // Generate reset token
            $token = bin2hex(random_bytes(16));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Store token in database
            $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE email = ?");
            $stmt->execute([$token, $expiry, $email]);
            
            // Send reset email
            if (sendPasswordResetEmail($email, $token)) {
                $message = "Password reset link has been sent to your email";
            } else {
                $error = "Failed to send reset email";
            }
        } else {
            $error = "Email not found";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password</title>
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
            padding: 20px;
        }

        .container {
            width: 500px;
            background-color: rgba(255, 255, 255, 0.95);
            border-radius: 8px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            padding: 40px;
            text-align: center;
            border: 3px solid #1a3e72;
        }

        h2 {
            font-size: 28px;
            margin-bottom: 20px;
            color: #1a3e72;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 700;
            position: relative;
            padding-bottom: 10px;
        }

        h2::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background: #f0a500;
        }

        p {
            margin-bottom: 25px;
            font-size: 16px;
            color: #555;
            line-height: 1.5;
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

        .message {
            color: #388e3c;
            background-color: #edf7ed;
            padding: 12px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid #388e3c;
        }

        form div {
            margin-bottom: 25px;
            text-align: left;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #1a3e72;
        }

        input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            transition: all 0.3s;
            background-color: #f9f9f9;
        }

        input:focus {
            border-color: #1a3e72;
            outline: none;
            box-shadow: 0 0 0 3px rgba(26, 62, 114, 0.2);
            background-color: #fff;
        }

        button {
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

        button:hover {
            background: linear-gradient(135deg, #2a5ca7, #3a7cd9);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(26, 62, 114, 0.3);
        }

        a {
            color: #1a3e72;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        a:hover {
            color: #f0a500;
            text-decoration: underline;
        }

        .password-icon {
            font-size: 50px;
            color: #1a3e72;
            margin-bottom: 20px;
        }

        /* Responsive design */
        @media (max-width: 600px) {
            .container {
                width: 95%;
                padding: 30px;
            }
        }
    </style>
</head>
<body>
      <div class="container">
        <div class="password-icon">
            <i class="fas fa-key"></i>
        </div>
    <h2>Forgot Password</h2>
      <p>Enter your email address and we'll send you a link to reset your password.</p>
    <?php if (isset($error)): ?>
        <div class="error"><?php echo $error; ?></div>
    <?php endif; ?>
    <?php if (isset($message)): ?>
        <div class="message"><?php echo $message; ?></div>
    <?php endif; ?>
    
    <form method="post">
        <div>
            <label for="email">Email Address:</label>
            <input type="email" id="email" name="email" required placeholder="Enter your registered email">
        </div>
        <button type="submit">Reset Password</button>
    </form>
    <p>Remember your password? <a href="signin.php">signin here</a></p>
</body>
</html>