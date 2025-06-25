<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '../vendor/autoload.php';

$mail = new PHPMailer(true);

$mail->isSMTP();
$mail->SMTPAuth = true;
$mail->Host = "smtp.gmail.com"; // Corrected SMTP host
$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
$mail->Port = 587;
$mail->Username = "lozanessjehan@gmail.com";
$mail->Password = "eenn jbdg cnxg yrij";

// Enable debugging if needed (comment out in production)
// $mail->SMTPDebug = SMTP::DEBUG_SERVER;

$mail->isHTML(true);

return $mail;