<?php
include 'config.php';

if (!isset($_SESSION['verify_email'])) {
    header("Location: index.php");
    exit();
}

if (isset($_POST['verify'])) {
    $email = $_SESSION['verify_email'];
    $user_code = $conn->real_escape_string($_POST['verification_code']);

    // Check if verification code matches
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND verification_code = ?");
    $stmt->bind_param("ss", $email, $user_code);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        // Update user as verified
        $update = $conn->prepare("UPDATE users SET is_verified = 1, verification_code = NULL WHERE email = ?");
        $update->bind_param("s", $email);
        
        if ($update->execute()) {
            $_SESSION['message'] = "Email verified successfully! You can now login.";
            $_SESSION['message_type'] = "success";
            unset($_SESSION['verify_email']);
            header("Location: login.php");
            exit();
        } else {
            $_SESSION['message'] = "Verification failed!";
            $_SESSION['message_type'] = "error";
            header("Location: verify.php");
            exit();
        }
    } else {
        $_SESSION['message'] = "Invalid verification code!";
        $_SESSION['message_type'] = "error";
        header("Location: verify.php");
        exit();
    }
}
?>