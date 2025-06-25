<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/vendor/autoload.php';

function getMailer() {
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->SMTPAuth = true;
    $mail->Host = "smtp.gmail.com";
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->Username = "lozanessjehan@gmail.com";
    $mail->Password = "eenn jbdg cnxg yrij";

    $mail->isHTML(true);
    return $mail;
}

function sendVerificationCode($email, $code) {
    $mail = getMailer();
    
    try {
        $mail->setFrom('lozanessjehan@gmail.com', 'LearnMate');
        $mail->addAddress($email);
        $mail->Subject = 'Your LearnMate Verification Code';
        
        $mail->Body = "
            <div style='font-family: Poppins, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e5e7eb; border-radius: 8px;'>
                <h2 style='color: #7E22CE;'>Email Verification</h2>
                <p>Thank you for registering with LearnMate!</p>
                <p>Your verification code is:</p>
                <div style='background-color: #F3E8FF; padding: 15px; text-align: center; font-size: 24px; font-weight: bold; letter-spacing: 2px; color: #7E22CE; border-radius: 6px; margin: 20px 0;'>
                    $code
                </div>
                <p>This code will expire in 10 minutes.</p>
                <p style='font-size: 12px; color: #6B7280; margin-top: 30px;'>If you didn't request this code, please ignore this email.</p>
            </div>
        ";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer Error: " . $mail->ErrorInfo);
        return false;
    }
}
?>