<?php
require 'mailer_verify.php';
session_start();

// Ensure we're sending proper JSON
header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email address');
    }

    // Generate a 6-digit verification code
    $verificationCode = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);

    // Store the code in session
    $_SESSION['verification_code'] = $verificationCode;
    $_SESSION['verification_email'] = $email;
    $_SESSION['verification_expiry'] = time() + 600; // 10 minutes expiry

    // Send the email
    if (sendVerificationCode($email, $verificationCode)) {
        echo json_encode([
            'success' => true,
            'message' => 'Verification code sent successfully'
        ]);
    } else {
        throw new Exception('Failed to send verification email');
    }
} catch (Exception $e) {
    // Log the error for debugging
    error_log('Verification error: ' . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

exit; // Ensure no extra output
?> 