<?php
// approval_mailer.php
require 'vendor/autoload.php'; // Require PHPMailer if using Composer

class ApprovalMailer {
    private $mailer;
    private $fromEmail = 'no-reply@learnmate.com';
    private $fromName = 'LearnMate Admin';

    public function __construct() {
        $this->mailer = new PHPMailer\PHPMailer\PHPMailer(true);
        $this->configureMailer();
    }

    private function configureMailer() {
        // SMTP Configuration
        $this->mailer->isSMTP();
        $this->mailer->Host       = "smtp.gmail.com"; // Your SMTP server
        $this->mailer->SMTPAuth   = true;
        $this->mailer->Username   = "lozanessjehan@gmail.com"; // SMTP username
        $this->mailer->Password   = "eenn jbdg cnxg yrij"; // SMTP password
        $this->mailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $this->mailer->Port       = 465;
    }

    public function sendApprovalNotification($userEmail, $userName, $userRole) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->setFrom($this->fromEmail, $this->fromName);
            $this->mailer->addAddress($userEmail);

            $subject = "Your LearnMate Account Has Been Approved";
            $htmlContent = $this->getApprovalTemplate($userName, $userRole);
            $textContent = "Dear {$userName},\n\nYour account has been approved as a {$userRole}.\n\nYou can now login to your account.\n\nThank you!";

            $this->mailer->isHTML(true);
            $this->mailer->Subject = $subject;
            $this->mailer->Body    = $htmlContent;
            $this->mailer->AltBody = $textContent;

            return $this->mailer->send();
        } catch (Exception $e) {
            error_log("Approval email error: " . $e->getMessage());
            return false;
        }
    }

    public function sendRejectionNotification($userEmail, $userName) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->setFrom($this->fromEmail, $this->fromName);
            $this->mailer->addAddress($userEmail);

            $subject = "Your LearnMate Account Application";
            $htmlContent = $this->getRejectionTemplate($userName);
            $textContent = "Dear {$userName},\n\nWe regret to inform you that your account application has been rejected.\n\nPlease contact support if you believe this was a mistake.\n\nThank you for your interest in LearnMate.";

            $this->mailer->isHTML(true);
            $this->mailer->Subject = $subject;
            $this->mailer->Body    = $htmlContent;
            $this->mailer->AltBody = $textContent;

            return $this->mailer->send();
        } catch (Exception $e) {
            error_log("Rejection email error: " . $e->getMessage());
            return false;
        }
    }

    private function getApprovalTemplate($name, $role) {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #7F56D9; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background-color: #f9f9f9; }
                .footer { padding: 10px; text-align: center; font-size: 12px; color: #777; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Account Approved</h1>
                </div>
                <div class="content">
                    <p>Dear ' . htmlspecialchars($name) . ',</p>
                    <p>Your LearnMate account has been approved as a <strong>' . htmlspecialchars($role) . '</strong>.</p>
                    <p>You can now login to your account and start using LearnMate.</p>
                    <p><a href="localhost/LearnMate_1_pangalawa" style="background-color: #7F56D9; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px; display: inline-block;">Login Now</a></p>
                </div>
                <div class="footer">
                    <p>© ' . date('Y') . ' LearnMate. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>';
    }

    private function getRejectionTemplate($name) {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #F97066; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background-color: #f9f9f9; }
                .footer { padding: 10px; text-align: center; font-size: 12px; color: #777; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Account Application</h1>
                </div>
                <div class="content">
                    <p>Dear ' . htmlspecialchars($name) . ',</p>
                    <p>We regret to inform you that your LearnMate account application has been rejected.</p>
                    <p>If you believe this was a mistake, please contact our support team at support@learnmate.com.</p>
                    <p>Thank you for your interest in LearnMate.</p>
                </div>
                <div class="footer">
                    <p>© ' . date('Y') . ' LearnMate. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>';
    }
}
?>