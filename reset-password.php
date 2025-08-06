<?php
require_once 'config.php';

// Check if token is valid
if (isset($_GET['token'])) {
    $token = sanitize($_GET['token']);
    
    $stmt = $pdo->prepare("SELECT id, reset_token_expiry FROM users WHERE reset_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if (!$user || strtotime($user['reset_token_expiry']) < time()) {
        $_SESSION['error'] = "Invalid or expired reset token";
        redirect('forgot-password.php');
    }
    
    $_SESSION['reset_token'] = $token;
} elseif (!isset($_SESSION['reset_token'])) {
    redirect('forgot-password.php');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate inputs
    $errors = [];
    
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (!isPasswordStrong($password)) {
        $errors[] = "Password must be at least 8 characters with uppercase, lowercase, number, and special character";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    if (empty($errors)) {
        // Hash new password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Update password and clear reset token
        $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE reset_token = ?");
        if ($stmt->execute([$hashed_password, $_SESSION['reset_token']])) {
            unset($_SESSION['reset_token']);
            $_SESSION['message'] = "Password updated successfully. Please login.";
            redirect('login.php');
        } else {
            $errors[] = "Failed to update password";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 400px; margin: 0 auto; padding: 20px; }
        .error { color: red; margin-bottom: 15px; }
        input { width: 100%; padding: 8px; margin: 8px 0; box-sizing: border-box; }
        button { background-color: #4CAF50; color: white; padding: 10px 15px; border: none; cursor: pointer; }
        button:hover { background-color: #45a049; }
    </style>
</head>
<body>
    <h2>Reset Password</h2>
    <?php if (!empty($errors)): ?>
        <div class="error">
            <?php foreach ($errors as $error): ?>
                <p><?php echo htmlspecialchars($error); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <form method="post">
        <div>
            <label for="password">New Password:</label>
            <input type="password" id="password" name="password" required>
        </div>
        <div>
            <label for="confirm_password">Confirm New Password:</label>
            <input type="password" id="confirm_password" name="confirm_password" required>
        </div>
        <button type="submit">Update Password</button>
    </form>
</body>
</html>