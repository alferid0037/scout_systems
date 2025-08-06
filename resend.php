<?php
require_once 'config.php';

if (!isset($_SESSION['email'])) {
    redirect('signup.php');
}

// Generate new verification code
$verification_code = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);

// Update code in database
$stmt = $pdo->prepare("UPDATE users SET verification_code = ? WHERE email = ?");
$stmt->execute([$verification_code, $_SESSION['email']]);

// Resend email
if (sendVerificationEmail($_SESSION['email'], $verification_code)) {
    $_SESSION['verification_sent'] = true;
    $_SESSION['message'] = "New verification code sent to your email";
} else {
    $_SESSION['error'] = "Failed to resend verification code";
}

redirect('verify-code.php');
?>