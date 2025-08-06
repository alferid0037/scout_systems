<?php
require_once 'config.php';

if (!isset($_SESSION['email']) || !isset($_SESSION['verification_sent'])) {
    redirect('signup.php');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $code = sanitize($_POST['code']);
    
    // Verify code
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND verification_code = ?");
    $stmt->execute([$_SESSION['email'], $code]);
    
    if ($stmt->rowCount() > 0) {
        // Mark user as verified
        $stmt = $pdo->prepare("UPDATE users SET is_verified = 1, verification_code = NULL WHERE email = ?");
        $stmt->execute([$_SESSION['email']]);
        
        // Get user ID
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$_SESSION['email']]);
        $user = $stmt->fetch();
        
        // Log user in
        $_SESSION['user_id'] = $user['id'];
        unset($_SESSION['email']);
        unset($_SESSION['verification_sent']);
        
        redirect('signin.php');
    } else {
        $error = "Invalid verification code";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify Email</title>
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
            font-size: 18px;
            text-align: center;
            letter-spacing: 5px;
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

        .verification-icon {
            font-size: 50px;
            color: #1a3e72;
            margin-bottom: 20px;
        }

        .code-inputs {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }

        .code-inputs input {
            width: 22%;
            height: 60px;
            font-size: 24px;
        }

        /* Responsive design */
        @media (max-width: 600px) {
            .container {
                width: 95%;
                padding: 30px;
            }
            
            .code-inputs input {
                width: 20%;
                height: 50px;
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
     <div class="container">
        <div class="verification-icon">
            <i class="fas fa-envelope"></i>
        </div>
    <h2>Verify Your Email</h2>
    <p>We've sent a 4-digit verification code to <?php echo htmlspecialchars($_SESSION['email']); ?></p>
    
    <?php if (isset($error)): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <form method="post">
        <div>
            <label for="code">Verification Code:</label>
            <input type="text" id="code" name="code" maxlength="4" pattern="\d{4}" required>
        </div>
        <button type="submit">Verify</button>
    </form>
    <p>Didn't receive code? <a href="resend.php">Resend</a></p>
</body>
</html>