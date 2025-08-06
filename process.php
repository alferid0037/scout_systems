<?php
include 'config.php';

if (isset($_POST['signup'])) {
    $name = $conn->real_escape_string($_POST['name']);
    $email = $conn->real_escape_string($_POST['email']);
    $password = $conn->real_escape_string($_POST['password']);
    $confirm_password = $conn->real_escape_string($_POST['confirm_password']);

    // Validate passwords match
    if ($password !== $confirm_password) {
        $_SESSION['message'] = "Passwords do not match!";
        $_SESSION['message_type'] = "error";
        header("Location: index.php");
        exit();
    }

    // Check if email already exists
    $check_email = $conn->prepare("SELECT email FROM users WHERE email = ?");
    $check_email->bind_param("s", $email);
    $check_email->execute();
    $check_email->store_result();

    if ($check_email->num_rows > 0) {
        $_SESSION['message'] = "Email already exists!";
        $_SESSION['message_type'] = "error";
        header("Location: index.php");
        exit();
    }

    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Generate 4-digit verification code
    $verification_code = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);

    // Insert user into database with verification code
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, verification_code) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $name, $email, $hashed_password, $verification_code);

    if ($stmt->execute()) {
        // Send verification email
        $subject = "Your Verification Code";
        $message = "Hello $name,\n\nYour verification code is: $verification_code\n\nPlease enter this code to verify your email address.";
        $headers = "From: " . FROM_NAME . " <" . FROM_EMAIL . ">";

        if (mail($email, $subject, $message, $headers)) {
            $_SESSION['verify_email'] = $email;
            $_SESSION['message'] = "Verification code sent to your email!";
            $_SESSION['message_type'] = "success";
            header("Location: verify.php");
            exit();
        } else {
            $_SESSION['message'] = "Failed to send verification email!";
            $_SESSION['message_type'] = "error";
            header("Location: index.php");
            exit();
        }
    } else {
        $_SESSION['message'] = "Registration failed!";
        $_SESSION['message_type'] = "error";
        header("Location: index.php");
        exit();
    }
}
?>