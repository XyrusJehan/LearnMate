<?php
header('Content-Type: text/plain'); // Set content type to plain text for AJAX response

$email = $_POST["email"];

$token = bin2hex(random_bytes(16));
$token_hash = hash("sha256", $token);
$expiry = date('Y-m-d H:i:s', time() + 60 * 30);

$mysqli = require __DIR__ . "/database.php";

$sql = "UPDATE users
        SET reset_token_hash = ?,
            reset_token_expire_at = ?
        WHERE email = ?";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param("sss", $token_hash, $expiry, $email);
$stmt->execute();

if ($mysqli->affected_rows) {
    $mail = require __DIR__ . "/mailer.php";

    $mail->setFrom("noreply@example.com");
    $mail->addAddress($email);
    $mail->Subject = "Password Reset Request";
    $mail->Body = <<<END
    Click <a href="http://localhost/LearnMate_1_pangalawa/reset_password.php?token=$token">here</a> 
    to reset your password. The link will expire in 30 minutes.
    END;

    try {
        $mail->send();
        echo "Password reset link sent. Please check your email.";
    } catch (Exception $e) {
        echo "Error: Could not send reset link. Please try again later.";
    }
} else {
    echo "If this email exists in our system, you will receive a reset link.";
    // Don't reveal whether the email exists or not for security
}