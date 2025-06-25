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
    require __DIR__ . "/mailer.php";
    
    $mail = getMailer(); // Use the getMailer function from your mailer.php
    
    try {
        // Set the "From" address to a no-reply address
        $mail->setFrom("no-reply@learnmate.com", "LearnMate");
        // Set the "Reply-To" address to your actual support email
        $mail->addReplyTo("support@learnmate.com", "LearnMate Support");
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = "Reset Your LearnMate Password";

        $mail->Body = <<<END
<html>
<head>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Roboto&display=swap');
  </style>
</head>
<body style="font-family: 'Roboto', sans-serif; background: #f9f9f9; margin: 0; padding: 0;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background: linear-gradient(to right,rgb(173, 126, 214), #7e22ce); padding: 40px 0;">
    <tr>
      <td align="center">
        <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
          <tr>
            <td align="center" style="padding: 40px 20px 10px;">
              <img src="https://i.imgur.com/bLAAIWo.png" alt="LearnMate Logo" width="220" style="margin-bottom: 10px;">
              <h1 style="font-size: 24px; color: #333;">Reset Your Password</h1>
              <p style="font-size: 16px; color: #555;">We received a request to reset your LearnMate password. Click the button below to continue.</p>
            </td>
          </tr>
          <tr>
            <td align="center" style="padding: 20px;">
              <a href="http://localhost/LearnMate_1_pangalawa/reset_password.php?token=$token"
                 style="background-color: #7e22ce; color: #fff; padding: 15px 25px; text-decoration: none; font-weight: bold; border-radius: 5px; display: inline-block;">
                Reset Password
              </a>
              <p style="font-size: 14px; color: #999; margin-top: 15px;">This link will expire in 30 minutes.</p>
            </td>
          </tr>
          <tr>
            <td style="padding: 20px 30px; background-color: #f6f6f6;">
              <h2 style="font-size: 18px; color: #333;">Need Help?</h2>
              <p style="font-size: 14px; color: #555;">
                If you didn't request a password reset, you can ignore this email. Your password will remain the same.
              </p>
              <p style="font-size: 14px; color: #555;">
                Still having trouble? <a href="mailto:support@learnmate.com" style="color:  #7e22ce;">Contact Support</a>
              </p>
            </td>
          </tr>
          <tr>
            <td align="center" style="padding: 15px; font-size: 12px; color: #999;">
              © 2025 LearnMate. All rights reserved.<br>
              LearnMate Inc., Education Lane, Knowledge City, PH
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
END;

        $mail->send();
        echo "Password reset link sent. Please check your email.";
    } catch (Exception $e) {
        echo "Error: Could not send reset link. Please try again later.";
        error_log("Mailer Error: " . $mail->ErrorInfo);
    }
} else {
    echo "If this email exists in our system, you will receive a reset link.";
}