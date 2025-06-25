<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/vendor/autoload.php';

session_start();

// Set content type to JSON
header('Content-Type: application/json');

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

function sendVerificationCode($email) {
    $mail = getMailer();
    
    // Generate a 6-digit verification code
    $code = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
    
    // Store in session for verification later
    $_SESSION['verification_code'] = $code;
    $_SESSION['verification_email'] = $email;
    $_SESSION['verification_expiry'] = time() + 600; // 10 minutes
    
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
        return ['success' => true];
    } catch (Exception $e) {
        error_log("Mailer Error: " . $mail->ErrorInfo);
        return ['success' => false, 'message' => $mail->ErrorInfo];
    }
}

// Handle the request
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    if ($_GET['action'] === 'send_code' && isset($_GET['email'])) {
        $email = filter_var($_GET['email'], FILTER_VALIDATE_EMAIL);
        if ($email) {
            $result = sendVerificationCode($email);
            echo json_encode($result);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid email address']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
    }
    exit;
}
?>