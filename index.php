<?php
// index.php
session_start();
require 'db.php';
require 'database.php';

// If user is not logged in, show login form
if (!isset($_SESSION['user_id'])) {
    $error = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['register'])) {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                if ($user['role'] === 'pending') {
                    $error = 'Your account is being review for approval. Please wait for update in you Email.';
                } elseif (password_verify($password, $user['password'])) {
                    // Only log in if account is not pending
                    if ($user['role'] !== 'pending') {
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['first_name'] = $user['first_name'];
                        $_SESSION['last_name'] = $user['last_name'];

                        switch ($user['role']) {
                            case 'admin':
                                header('Location: admin_dashboard.php');
                                break;
                            case 'teacher':
                                header('Location: teacher_dashboard.php');
                                break;
                            case 'student':
                                header('Location: student_dashboard.php');
                                break;
                            default:
                                header('Location: student_flashcards/landing_page.php');
                        }
                        exit();
                    }
                } else {
                    $error = 'Invalid email or password';
                }
            } else {
                $error = 'Invalid email or password';
            }
        } catch (PDOException $e) {
            $error = 'Login failed: ' . $e->getMessage();
        }
    }
} else {
    // If user is logged in, redirect to appropriate dashboard
    switch ($_SESSION['role']) {
        case 'admin':
            header('Location: admin_dashboard.php');
            break;
        case 'teacher':
            header('Location: teacher_dashboard.php');
            break;
        case 'student':
            header('Location: student_dashboard.php');
            break;
        case 'pending':
            // This shouldn't normally happen as pending users shouldn't be able to login
            session_destroy();
            header('Location: index.php');
            exit();
        default:
            header('Location: student_flashcards/landing_page.php');
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Learnmate - Smart Learning Platform</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
/* Root Variables */
:root {
  --primary: #7E22CE;
  --primary-light: #A855F7;
  --secondary: #F3E8FF;
  --text: #1F2937;
  --text-light: #6B7280;
  --light: #FFFFFF;
  --border: #E5E7EB;
  --error: #EF4444;
  --brand-color: #5D3FD3;
  --google-blue: #4285F4;
  --pink-bg: #FCE7F3;
  --pink-dark: #DB2777;
}

/* Base Styles */
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
  font-family: 'Poppins', sans-serif;
}

body {
  background-color: #F9FAFB;
  overflow-x: hidden;
  line-height: 1.5;
}

.gradient-text {
  background: linear-gradient(90deg, #3b82f6 0%, #8b5cf6 50%);
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
}

/* Layout Styles */
.max-w-6xl {
  max-width: 72rem;
}

.mx-auto {
  margin-left: auto;
  margin-right: auto;
}

.px-4 {
  padding-left: 1rem;
  padding-right: 1rem;
}

.py-4 {
  padding-top: 1rem;
  padding-bottom: 1rem;
}

.py-16 {
  padding-top: 4rem;
  padding-bottom: 4rem;
}

.py-12 {
  padding-top: 3rem;
  padding-bottom: 3rem;
}

.py-24 {
  padding-top: 6rem;
  padding-bottom: 6rem;
}

.mb-4 {
  margin-bottom: 1rem;
}

.mb-8 {
  margin-bottom: 2rem;
}

.mb-12 {
  margin-bottom: 3rem;
}

.mt-12 {
  margin-top: 3rem;
}

/* Navigation Styles */
nav {
  background-color: var(--light);
  box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
  position: sticky;
  top: 0;
  z-index: 50;
}

.flex {
  display: flex;
}

.justify-between {
  justify-content: space-between;
}

.items-center {
  align-items: center;
}

/* Button Styles */
.btn {
  padding: 0.75rem 1rem;
  border-radius: 0.5rem;
  font-weight: 500;
  font-size: 0.875rem;
  cursor: pointer;
  transition: all 0.3s ease;
  text-align: center;
  border: none;
}

.bg-blue-600 {
  background-color: #2563eb;
}

.hover\:bg-blue-700:hover {
  background-color: #1d4ed8;
}

.text-white {
  color: var(--light);
}

.rounded-md {
  border-radius: 0.375rem;
}

/* Hero Section */
.md\:flex {
  display: flex;
}

.md\:w-1\/2 {
  width: 50%;
}

.md\:mb-0 {
  margin-bottom: 0;
}

.text-4xl {
  font-size: 2.25rem;
  line-height: 2.5rem;
}

.md\:text-5xl {
  font-size: 3rem;
  line-height: 1;
}

.font-bold {
  font-weight: 700;
}

.text-gray-800 {
  color: #1f2937;
}

.text-lg {
  font-size: 1.125rem;
  line-height: 1.75rem;
}

.text-gray-600 {
  color: #4b5563;
}

.rounded-lg {
  border-radius: 0.5rem;
}

.shadow-lg {
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
}

/* Features Section */
.grid {
  display: grid;
}

.md\:grid-cols-3 {
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.gap-8 {
  gap: 2rem;
}

.bg-gray-50 {
  background-color: #f9fafb;
}

.p-6 {
  padding: 1.5rem;
}

.text-3xl {
  font-size: 1.875rem;
  line-height: 2.25rem;
}

.text-center {
  text-align: center;
}

.text-xl {
  font-size: 1.25rem;
  line-height: 1.75rem;
}

.font-semibold {
  font-weight: 600;
}

/* Footer Styles */
.bg-gray-800 {
  background-color: #1f2937;
}

.text-white {
  color: var(--light);
}

.text-gray-400 {
  color: #9ca3af;
}

.hover\:text-white:hover {
  color: var(--light);
}

.border-t {
  border-top-width: 1px;
}

.border-gray-700 {
  border-color: #374151;
}

.pt-8 {
  padding-top: 2rem;
}

/* Modal Styles */
.modal {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.5);
  z-index: 1000;
  overflow-y: auto;
  padding: 1.25rem;
}

.modal-content {
  background: var(--light);
  border-radius: 1rem;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
  width: 95%;
  max-width: 56.25rem;
  margin: 1.25rem auto;
  position: relative;
  overflow: hidden;
}

.close-modal {
  position: absolute;
  top: 0.9375rem;
  right: 0.9375rem;
  font-size: 1.5rem;
  color: var(--text-light);
  cursor: pointer;
  z-index: 10;
  background: rgba(255, 255, 255, 0.8);
  border-radius: 50%;
  width: 2.25rem;
  height: 2.25rem;
  display: flex;
  align-items: center;
  justify-content: center;
}

.close-modal:hover {
  color: var(--text);
}

/* Auth Container */
.auth-container {
  background: var(--light);
  border-radius: 1rem;
  width: 100%;
  max-width: 100%;
  overflow: hidden;
  display: flex;
  gap: 0;
}

.auth-container::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 0.375rem;
  background: linear-gradient(90deg, var(--primary), var(--primary-light));
}

/* Signin Section */
.signin-section, 
.auth-header, 
.auth-form {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    padding: 2rem;
}

.signin-section {
  flex: 1;
  padding: 2.5rem;
  display: flex;
  flex-direction: column;
}

.signin-header {
  margin-bottom: 1.875rem;
  text-align: center;
}

.signin-header h1 {
  color: var(--text);
  font-size: 1.5rem;
  font-weight: 600;
  margin-bottom: 0.5rem;
}

.brand-name {
  color: var(--brand-color);
  font-weight: 700;
}

.signin-subtitle {
  color: var(--text-light);
  font-size: 0.875rem;
}

.social-login {
  margin-bottom: 1.5625rem;
}

.google-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.625rem;
  width: 100%;
  padding: 0.75rem;
  border-radius: 0.5rem;
  border: 1px solid var(--border);
  background: var(--light);
  color: var(--text);
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s ease;
}

.google-btn:hover {
  background: #f8fafc;
  border-color: #d1d5db;
}

.google-logo {
  width: 1.125rem;
  height: 1.125rem;
}

.divider {
  display: flex;
  align-items: center;
  margin: 1.25rem 0;
  color: var(--text-light);
  font-size: 0.875rem;
}

.divider::before,
.divider::after {
  content: "";
  flex: 1;
  border-bottom: 1px solid var(--border);
}

.divider span {
  padding: 0 0.75rem;
}

.signin-form {
  display: flex;
  flex-direction: column;
  gap: 0.9375rem;
}

/* Form Group Styles */
.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.form-group label {
  font-size: 0.875rem;
  color: var(--text);
  font-weight: 500;
}

.input-with-icon {
  position: relative;
}

.input-icon {
  position: absolute;
  left: 0.875rem;
  top: 50%;
  transform: translateY(-50%);
  width: 1rem;
  height: 1rem;
  pointer-events: none;
}

.password-container {
  position: relative;
}

.password-container input {
  padding-right: 2.5rem;
}

.toggle-password {
  position: absolute;
  right: 0.75rem;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  cursor: pointer;
  padding: 0.3125rem;
  color: var(--text-light);
  display: flex;
  align-items: center;
  justify-content: center;
  width: 1.875rem;
  height: 1.875rem;
}

.eye-icon {
  width: 1.25rem;
  height: 1.25rem;
  fill: none;
  stroke: currentColor;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
  transition: all 0.3s ease;
  position: absolute;
}

.eye-open {
  display: none;
  opacity: 0;
}

.eye-closed {
  display: block;
  opacity: 1;
}

.password-visible .eye-closed {
  display: none;
  opacity: 0;
}

.password-visible .eye-open {
  display: block;
  opacity: 1;
}

.toggle-password:hover .eye-icon {
  color: var(--primary);
}

.input-with-icon input,
.auth-form input {
  width: 100%;
  padding: 0.75rem 0.75rem 0.75rem 2.5rem;
  border: 1px solid var(--border);
  border-radius: 0.5rem;
  font-size: 0.875rem;
  transition: all 0.3s ease;
}

.auth-form input {
  padding: 0.875rem 1rem;
}

.input-with-icon input:focus,
.auth-form input:focus {
  outline: none;
  border-color: var(--primary-light);
  box-shadow: 0 0 0 3px rgba(168, 85, 247, 0.2);
}

.form-options {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.8125rem;
}

.remember-me {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.forgot-password {
  color: var(--primary);
  text-decoration: none;
  font-weight: 500;
  cursor: pointer;
}

.forgot-password:hover {
  text-decoration: underline;
}

/* Button Styles */
.btn-primary {
  background-color: var(--primary);
  color: white;
  position: relative;
  overflow: hidden;
}

.btn-primary:hover {
  background-color: var(--primary-light);
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(126, 34, 206, 0.2);
}

.btn-primary::after {
  content: "→";
  position: absolute;
  right: 1.25rem;
  top: 50%;
  transform: translateY(-50%);
  opacity: 0;
  transition: all 0.3s ease;
}

.btn-primary:hover::after {
  opacity: 1;
  right: 0.9375rem;
}

/* Welcome Section */
.welcome-section {
  flex: 1;
  background: linear-gradient(135deg, var(--brand-color), #7E57C2);
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  text-align: center;
  color: var(--light);
}

.welcome-content h2 {
  font-size: 1.5rem;
  font-weight: 600;
  margin-bottom: 0.9375rem;
}

.welcome-content p {
  font-size: 0.875rem;
  margin-bottom: 1.5625rem;
  opacity: 0.9;
}

.btn-outline {
  background-color: transparent;
  color: var(--light);
  border: 1px solid var(--light);
  padding: 0.75rem;
  border-radius: 0.5rem;
  font-weight: 500;
  font-size: 0.9375rem;
  cursor: pointer;
  transition: all 0.3s ease;
  text-align: center;
  width: 100%;
}

.btn-outline:hover {
  background-color: rgba(255, 255, 255, 0.1);
}

/* Auth Header */
.auth-header {
  flex: 1;
  display: flex;
  flex-direction: column;
  justify-content: center;
  text-align: left;
  padding-right: 1.25rem;
}

.auth-header h1 {
  color: var(--primary);
  font-size: 2rem;
  font-weight: 600;
  margin-bottom: 0.625rem;
}

.auth-header p {
  color: var(--text-light);
  font-size: 1rem;
  margin-bottom: 1.875rem;
}

.signin-prompt {
  margin-top: auto;
  font-size: 0.875rem;
}

.signin-prompt a,
.signin-prompt button {
  color: var(--primary);
  text-decoration: none;
  font-weight: 500;
  background: none;
  border: none;
  cursor: pointer;
  padding: 0;
}

.signin-prompt a:hover,
.signin-prompt button:hover {
  text-decoration: underline;
}

/* Form Row */
.form-row {
  display: flex;
  gap: 1rem;
}

.form-row .form-group {
  flex: 1;
  min-width: 0;
}

/* Terms Check */
.terms-check {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin: 0.5rem 0;
}

.terms-check input {
  width: 1rem;
  height: 1rem;
}

.terms-check label {
  font-size: 0.875rem;
  color: var(--text);
}

.terms-check a {
  color: var(--primary);
  text-decoration: none;
  font-weight: 500;
}

/* Verification */
.verification-container {
  display: flex;
  gap: 0.625rem;
  margin-bottom: 0.625rem;
}

.verification-input {
  flex: 1;
  padding: 0.875rem 1rem;
  border: 1px solid var(--border);
  border-radius: 0.5rem;
  font-size: 1rem;
  text-align: center;
  letter-spacing: 2px;
  transition: all 0.3s ease;
}

.verification-input:focus {
  outline: none;
  border-color: var(--primary-light);
  box-shadow: 0 0 0 3px rgba(168, 85, 247, 0.2);
}

.btn-send-code {
  background-color: var(--secondary);
  color: var(--primary);
  border: 1px solid var(--primary-light);
  padding: 0.875rem;
  border-radius: 0.5rem;
  font-weight: 500;
  font-size: 0.875rem;
  cursor: pointer;
  transition: all 0.3s ease;
  white-space: nowrap;
}

.btn-send-code:hover {
  background-color: var(--primary-light);
  color: white;
}

.btn-send-code:disabled {
  background-color: var(--border);
  color: var(--text-light);
  cursor: not-allowed;
  border-color: var(--border);
}

.verification-status {
  font-size: 0.875rem;
  margin-top: 0.3125rem;
  padding: 0.5rem;
  border-radius: 0.3125rem;
  text-align: center;
  display: none;
}

.verification-status.success {
  background-color: #ddffdd;
  color: #4f8a10;
}

.verification-status.error {
  background-color: #ffdddd;
  color: #d8000c;
}

.resend-code {
  font-size: 0.875rem;
  color: var(--text-light);
  text-align: center;
  margin-top: 0.625rem;
}

.resend-code a {
  color: var(--primary);
  text-decoration: none;
  font-weight: 500;
  cursor: pointer;
}

.resend-code a:hover {
  text-decoration: underline;
}

#countdown {
  color: var(--primary);
  font-weight: 500;
  margin-left: 0.3125rem;
  display: none;
}

/* Error Messages */
.error-message {
  color: var(--error);
  font-size: 0.875rem;
  margin-bottom: 0.9375rem;
  padding: 0.625rem;
  background-color: rgba(239, 68, 68, 0.1);
  border-radius: 0.3125rem;
}

.error-text {
  color: var(--error);
  font-size: 0.8125rem;
}



/* Forgot Password Modal */
#forgotPasswordModal .modal-content {
  max-width: 28rem;
  width: 90%;
}

.recovery-card {
  background: var(--light);
  border-radius: 1rem;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
  padding: 2.5rem;
  text-align: center;
  position: relative;
  overflow: hidden;
  max-width: 30rem;
  margin: 0 auto;
}

.recovery-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 0.375rem;
  background: linear-gradient(90deg, var(--primary), var(--primary-light));
}

.recovery-header {
  margin-bottom: 1.875rem;
}

.recovery-header h1 {
  color: var(--text);
  font-size: 1.5rem;
  font-weight: 600;
  margin-bottom: 0.5rem;
}

.recovery-header p {
  color: var(--text-light);
  font-size: 0.875rem;
}

.recovery-form {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.email-icon {
  position: absolute;
  left: 0.875rem;
  top: 50%;
  transform: translateY(-50%);
  width: 1.25rem;
  height: 1.25rem;
  fill: var(--text-light);
}

.btn-reset {
  background-color: var(--primary);
  color: white;
  margin-top: 0.625rem;
}

.btn-reset:hover {
  background-color: var(--primary-light);
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(126, 34, 206, 0.2);
}

.back-to-login {
  margin-top: 1.5625rem;
}

.back-link {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  color: var(--pink-dark);
  text-decoration: none;
  font-weight: 500;
  font-size: 0.875rem;
  transition: all 0.3s ease;
  background: none;
  border: none;
  cursor: pointer;
}

.back-arrow {
  width: 1rem;
  height: 1rem;
  fill: none;
  stroke: var(--pink-dark);
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
  transition: all 0.3s ease;
}

.back-link:hover {
  color: var(--primary);
}

.back-link:hover .back-arrow {
  stroke: var(--primary);
  transform: translateX(-3px);
}

/* Alert Styles */
.alert {
  position: fixed;
  top: 1.25rem;
  left: 0;
  right: 0;
  display: flex;
  justify-content: center;
  z-index: 1000;
}

.alert div {
  padding: 0.9375rem;
  color: white;
  border-radius: 0.25rem;
  box-shadow: 0 2px 5px rgba(0,0,0,0.2);
  max-width: 31.25rem;
  width: 100%;
  text-align: center;
  transition: opacity 0.5s ease;
}

.alert-success {
  background-color: #4CAF50;
}

.alert-error {
  background-color: #f44336;
}

/* Step Wizard Styles */
.wizard-steps {
  display: flex;
  justify-content: space-between;
  margin-bottom: 2rem;
  padding: 1rem;
  background: #f8fafc;
  border-radius: 0.5rem;
}

.wizard-step {
  display: flex;
  align-items: center;
  flex: 1;
  text-align: center;
  position: relative;
}

.wizard-step:not(:last-child)::after {
  content: '';
  position: absolute;
  top: 50%;
  right: -50%;
  width: 100%;
  height: 2px;
  background: var(--border);
  z-index: 1;
}

.wizard-step.active:not(:last-child)::after {
  background: var(--primary);
}

.wizard-step-number {
  width: 2rem;
  height: 2rem;
  border-radius: 50%;
  background: var(--border);
  color: var(--text-light);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  margin-right: 0.5rem;
  z-index: 2;
}

.wizard-step.active .wizard-step-number {
  background: var(--primary);
  color: white;
}

.wizard-step.completed .wizard-step-number {
  background: var(--primary);
  color: white;
}

.wizard-step-title {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--text-light);
}

.wizard-step.active .wizard-step-title,
.wizard-step.completed .wizard-step-title {
  color: var(--text);
}

.step-content {
  display: none;
}

.step-content.active {
  display: block;
}

.step-navigation {
  display: flex;
  justify-content: space-between;
  margin-top: 1.5rem;
}

.btn-back {
  background: transparent;
  color: var(--primary);
  border: 1px solid var(--primary);
}

.btn-back:hover {
  background: var(--secondary);
}

/* Responsive Styles */
@media (max-width: 768px) {
  .hero-section {
    padding-top: 3rem;
    padding-bottom: 3rem;
  }
  
  .md\:flex {
    flex-direction: column;
  }
  
  .md\:w-1\/2 {
    width: 100%;
  }
  
  .md\:mb-0 {
    margin-bottom: 2rem;
  }
  
  .md\:text-5xl {
    font-size: 2.5rem;
  }
  
  .auth-container {
    flex-direction: column;
    max-width: 26.25rem;
    padding: 1.875rem;
    gap: 1.875rem;
  }

  .auth-header {
    text-align: center;
    padding-right: 0;
  }

  .auth-header h1 {
    font-size: 1.75rem;
  }

  .auth-header p {
    font-size: 0.875rem;
    margin-bottom: 1.25rem;
  }

  .form-row {
    flex-direction: column;
    gap: 1.25rem;
  }

  .signin-section, .welcome-section {
    padding: 1.875rem;
  }
  
  .welcome-section {
    padding: 2rem 1.5rem;
  }
  
  .recovery-card {
    padding: 1.875rem 1.25rem;
  }
  
  .recovery-header h1 {
    font-size: 1.375rem;
  }
  
  .grid {
    grid-template-columns: 1fr;
  }
  
  .md\:grid-cols-3 {
    grid-template-columns: 1fr;
  }
  
  .features-section .grid {
    gap: 1rem;
  }
  
  .features-section .bg-gray-50 {
    padding: 1.5rem;
  }

  .wizard-steps {
    flex-direction: column;
    align-items: flex-start;
  }

  .wizard-step:not(:last-child)::after {
    display: none;
  }

  .wizard-step {
    margin-bottom: 1rem;
  }
}

@media (max-width: 480px) {
  .auth-container {
    padding: 1.5625rem 1.25rem;
    max-width: 95vw;
  }
  
  .text-4xl {
    font-size: 1.75rem;
  }
  
  .text-lg {
    font-size: 1rem;
  }
  
  nav {
    padding: 0.75rem 1rem;
  }
  
  .hero-section img {
    height: auto;
    max-width: 100%;
  }
  
  .footer .grid-cols-2 {
    grid-template-columns: 1fr;
  }
  
  .modal-content {
    margin: 0.5rem auto;
  }
  
  .verification-container {
    flex-direction: column;
  }
  
  .btn-send-code {
    width: 100%;
  }
}

.prose {
    line-height: 1.6;
    color: var(--text);
}

.prose h3 {
    color: var(--primary);
}

.prose ul {
    margin-bottom: 1rem;
}

.prose p {
    margin-bottom: 1rem;
}

.modal-content::-webkit-scrollbar {
    width: 8px;
}

.modal-content::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.modal-content::-webkit-scrollbar-thumb {
    background: var(--primary-light);
    border-radius: 10px;
}

.modal-content::-webkit-scrollbar-thumb:hover {
    background: var(--primary);
}
/* Add these styles to your existing CSS */

/* Signup Progress Steps */
.signup-progress {
    margin: 1.5rem 0;
}

.step-number {
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.step.active .step-number {
    background-color: var(--primary);
    color: white;
    border-color: var(--primary-light);
}

.step.completed .step-number {
    background-color: var(--primary-light);
    color: white;
}

.step-label {
    transition: all 0.3s ease;
}

.step.active .step-label,
.step.completed .step-label {
    color: var(--primary);
    font-weight: 500;
}

.progress-bar {
    z-index: 0;
}

.progress-fill {
    transition: width 0.3s ease;
}

/* Form Steps */
.form-step {
    transition: all 0.3s ease;
}

/* File Upload */
.file-upload-container {
    transition: all 0.3s ease;
    cursor: pointer;
}

.file-upload-container:hover {
    border-color: var(--primary-light);
    background-color: var(--secondary);
}



/* Password Match Indicator */
#password-match.match {
    color: #4f8a10;
}

#password-match.mismatch {
    color: var(--error);
}
.step.completed .step-number {
    background-color: var(--primary-light);
    color: white;
}

.step.completed .step-label {
    color: var(--primary);
    font-weight: 500;
}

.progress-fill {
    transition: width 0.5s ease;
}
</style>
</head>
<body class="font-sans bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-sm py-4">
        <div class="max-w-6xl mx-auto px-4 flex justify-between items-center">
            <div class="flex items-center">
                <img src="LMlogo.png" alt="LearnMate Logo" class="h-10 w-10 md:h-10 md:w-10">
                <span class="ml-2 text-xl font-bold">Learn<span class="gradient-text">mate</span></span>
            </div>
            
            <button id="signupBtn" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">
                Sign Up
            </button>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="max-w-6xl mx-auto px-4 py-16 md:py-24">
        <div class="md:flex items-center">
            <div class="md:w-1/2 mb-12 md:mb-0">
                <h1 class="text-4xl md:text-5xl font-bold text-gray-800 mb-4">
                    Learn better, <span class="gradient-text">Together</span>
                </h1>
                <p class="text-lg text-gray-600 mb-8">
                    Upload your study materials and get personalized quizzes, summaries, flashcards, and progress tracking to help you master any subject.
                </p>
                <div class="flex flex-col sm:flex-row space-y-4 sm:space-y-0 sm:space-x-4">
                    <button id="getStartedBtn" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-md">
                        Get Started
                    </button>
                </div>
            </div>
            <div class="md:w-1/2">
                <img src="https://images.unsplash.com/photo-1522202176988-66273c2fd55f?ixlib=rb-1.2.1&auto=format&fit=crop&w=1351&q=80" 
                     alt="Students learning" 
                     class="rounded-lg shadow-lg w-full h-auto">
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="bg-white py-16">
        <div class="max-w-6xl mx-auto px-4">
            <h2 class="text-3xl font-bold text-center mb-12">How Learnmate Helps You</h2>
            
            <div class="grid md:grid-cols-3 gap-8">
                <!-- Upload Materials -->
                <div class="bg-gray-50 p-6 rounded-lg">
                    <div class="text-blue-600 text-3xl mb-4">
                        <i class="fas fa-file-pdf"></i>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">PDF Upload</h3>
                    <p class="text-gray-600">
                        Upload your study materials in PDF format for processing and organization.
                    </p>
                </div>
                
                <!-- Smart Quizzes -->
                <div class="bg-gray-50 p-6 rounded-lg">
                    <div class="text-purple-600 text-3xl mb-4">
                        <i class="fas fa-question-circle"></i>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">Manual Quiz Creation</h3>
                    <p class="text-gray-600">
                        Create custom quizzes manually to test your knowledge on any subject.
                    </p>
                </div>
                
                <!-- Highlight to Flashcards -->
                <div class="bg-gray-50 p-6 rounded-lg">
                    <div class="text-blue-600 text-3xl mb-4">
                        <i class="fas fa-highlighter"></i>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">Highlight to Flashcards</h3>
                    <p class="text-gray-600">
                        Highlight important text in your PDFs to automatically generate flashcards.
                    </p>
                </div>
                
                <!-- Flashcard Reviewer -->
                <div class="bg-gray-50 p-6 rounded-lg">
                    <div class="text-blue-600 text-3xl mb-4">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">Flashcard Reviewer</h3>
                    <p class="text-gray-600">
                        Review your generated flashcards for effective memorization and recall.
                    </p>
                </div>

                <!-- Classroom Management -->
                <div class="bg-gray-50 p-6 rounded-lg">
                    <div class="text-blue-600 text-3xl mb-4">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">Classroom System</h3>
                    <p class="text-gray-600">
                        Teachers can create classes and students can join to share materials and quizzes.
                    </p>
                </div>
                <!-- Progress Tracking -->
                <div class="bg-gray-50 p-6 rounded-lg">
                    <div class="text-blue-600 text-3xl mb-4">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">Progress Tracking</h3>
                    <p class="text-gray-600">
                        Track your learning progress with visual reports and statistics.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <footer class="bg-gray-800 text-white py-12">
        <div class="max-w-6xl mx-auto px-4">
            <div class="md:flex justify-between">
                <div class="mb-8 md:mb-0">
                    <div class="flex items-center mb-4">
                        <img src="LMlogo.png" alt="LearnMate Logo" class="h-10 w-10 md:h-10 md:w-10">
                        <span class="ml-2 text-x1 font-bold">Learn<span class="gradient-text">mate</span></span>
                    </div>
                    <p class="text-gray-400">
                        Making learning easier for students everywhere.
                    </p>
                </div>
                
                <div class="grid grid-cols-2 md:grid-cols-3 gap-8">
                    <div>
                        <h3 class="font-semibold mb-4">Product</h3>
                        <ul class="space-y-2">
                            <li><a href="#" class="text-gray-400 hover:text-white">Features</a></li>
                            <li><a href="#" class="text-gray-400 hover:text-white">Pricing</a></li>
                            <li><a href="#" class="text-gray-400 hover:text-white">FAQ</a></li>
                        </ul>
                    </div>
                    
                    <div>
                        <h3 class="font-semibold mb-4">Company</h3>
                        <ul class="space-y-2">
                            <li><a href="#" class="text-gray-400 hover:text-white">About</a></li>
                            <li><a href="#" class="text-gray-400 hover:text-white">Contact</a></li>
                            <li><a href="#" class="text-gray-400 hover:text-white">Privacy</a></li>
                        </ul>
                    </div>
                    
                    <div>
                        <h3 class="font-semibold mb-4">Connect</h3>
                        <div class="flex space-x-4">
                            <a href="#" class="text-gray-400 hover:text-white text-xl">
                                <i class="fab fa-twitter"></i>
                            </a>
                            <a href="#" class="text-gray-400 hover:text-white text-xl">
                                <i class="fab fa-instagram"></i>
                            </a>
                            <a href="#" class="text-gray-400 hover:text-white text-xl">
                                <i class="fab fa-facebook"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="border-t border-gray-700 mt-12 pt-8 text-center text-gray-400">
                &copy; <?php echo date("Y"); ?> Learnmate. All rights reserved.
            </div>
            <div class="mt-8 text-center">
                <h3 class="text-lg font-semibold text-white mb-2">About Us</h3>
                <ul class="text-gray-300 space-y-1">
                    <li><b>Front/Back End:</b> Aleta, Francis Kim M.</li>
                    <li><b>Front/Back End:</b> De Alday, Jade Anthony C.</li>
                    <li><b>Front End:</b> Dorado, Jazer Neil O.</li>
                    <li><b>Non-Technical Leader:</b> Ellio, James Young G.</li>
                    <li><b>Technical Leader:</b> Lozañes, Xyrus Jehan B.</li>
                    <li><b>Test Framer:</b> Recto, Janice M.</li>
                    <li><b>Test Framer:</b> Vindiola, Nico C.</li>
                </ul>
            </div>
        </div>
    </footer>

    <!-- Login Modal -->
    <div id="loginModal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <div class="auth-container">
                <div class="signin-section">
                    <div class="signin-header">
                        <h1>Sign in to <span class="brand-name">LearnMate</span></h1>
                        <p class="signin-subtitle">Welcome back! Please enter your details</p>
<?php if (isset($error) && $error): ?>
    <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>
                    </div>

                    <div class="social-login">
                        <a href="auth/google/login.php" class="google-btn">
                            <img src="GoogleLogo.png" alt="Google logo" class="google-logo">
                            Continue with Google
                        </a>
                        <div class="divider">
                            <span>or</span>
                        </div>
                    </div>

                    <?php if (isset($_GET['error']) && $_GET['error'] === 'google_login_failed' && isset($_SESSION['oauth_error'])): ?>
                        <div class="error-message">Google login failed: <?php echo htmlspecialchars($_SESSION['oauth_error']); ?></div>
                        <?php unset($_SESSION['oauth_error']); ?>
                    <?php endif; ?>

                    <form class="signin-form" method="POST" action="index.php">
                        <div class="form-group">
                            <div class="input-with-icon">
                                <img src="UserNameIcon.png" alt="Email icon" class="input-icon">
                                <input type="email" name="email" placeholder="Email" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="input-with-icon password-container">
                                <img src="PasswordIcon.png" alt="Password icon" class="input-icon">
                                <input type="password" id="password" name="password" placeholder="Password" required>
                                <button type="button" class="toggle-password" aria-label="Show password">
                                    <svg class="eye-icon eye-closed" viewBox="0 0 24 24" width="20" height="20">
                                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                        <line x1="1" y1="1" x2="23" y2="23"></line>
                                    </svg>
                                    <svg class="eye-icon eye-open" viewBox="0 0 24 24" width="20" height="20">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                </button>
                            </div>
                            <div class="form-options">
                                <div class="remember-me">
                                    <input type="checkbox" id="remember" name="remember">
                                    <label for="remember">Remember me</label>
                                </div>
                                <button type="button" class="forgot-password" id="forgotPasswordBtn">Forgot password?</button>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">Sign In</button>
                    </form>
                </div>

                <div class="welcome-section">
                    <div class="welcome-content">
                        <h2>Hello, Friend!</h2>
                        <p>Enter your personal details and start your journey with us</p>
                        <button id="switchToSignup" class="btn btn-outline">Sign Up</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

  <!-- Signup Modal -->
<div id="signupModal" class="modal">
    <div class="modal-content">
        <span class="close-modal">&times;</span>
        <div class="auth-container">
            <div class="signin-section">
                <div class="signin-header">
                    <h1>Create your <span class="brand-name">LearnMate</span> account</h1>
                    <p class="signin-subtitle">Get started with your educational journey in just 3 steps</p>
                    
                    <!-- Signup Progress Steps -->
                    <div class="signup-progress mb-6">
                        <div class="steps flex justify-between relative">
                            <div class="step flex flex-col items-center relative z-10">
                                <div class="step-number w-8 h-8 rounded-full bg-purple-100 flex items-center justify-center text-purple-600 font-semibold">1</div>
                                <span class="step-label mt-2 text-xs text-purple-600 font-medium">Basic Info</span>
                            </div>
                            <div class="step flex flex-col items-center relative z-10">
                                <div class="step-number w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center text-gray-400 font-semibold">2</div>
                                <span class="step-label mt-2 text-xs text-gray-400">Verification</span>
                            </div>
                            <div class="step flex flex-col items-center relative z-10">
                                <div class="step-number w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center text-gray-400 font-semibold">3</div>
                                <span class="step-label mt-2 text-xs text-gray-400">Password & ID</span>
                            </div>
                            <div class="progress-bar absolute top-4 left-0 right-0 h-1 bg-gray-200">
                                <div class="progress-fill h-full bg-purple-600 transition-all duration-300" style="width: 33%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <form class="auth-form" method="POST" action="createAcc.php" autocomplete="off" enctype="multipart/form-data" id="signupForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                    <input type="hidden" name="register" value="1">
                    
                    <!-- Step 1: Basic Information -->
                    <div class="form-step active" data-step="1">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="first_name">First Name</label>
                                <input type="text" id="first_name" name="first_name" 
                                       placeholder="Enter your first name" required
                                       oninput="validateName(this)">
                                <small class="text-gray-500 text-xs">Your given name</small>
                            </div>
                            <div class="form-group">
                                <label for="last_name">Last Name</label>
                                <input type="text" id="last_name" name="last_name" 
                                       placeholder="Enter your last name" required
                                       oninput="validateName(this)">
                                <small class="text-gray-500 text-xs">Your family name</small>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address</label>

                                <input type="email" id="email" name="email" 
                                       placeholder="your@email.com" required
                                       oninput="validateEmail(this)">
                            
                            <small class="text-gray-500 text-xs">We'll send a verification code to this email</small>
                            <div id="email-error" class="error-text" style="display: none;"></div>
                        </div>
                        
                        <div class="flex justify-end mt-4">
                            <button type="button" class="btn btn-primary next-step" 
                                    onclick="nextStep(1)">Continue →</button>
                        </div>
                    </div>
                    
                    <!-- Step 2: Email Verification -->
                    <div class="form-step" data-step="2" style="display: none;">
                        <div class="bg-purple-50 p-4 rounded-lg mb-4">
                            <div class="flex items-start">
                                <svg class="text-purple-600 mt-1 mr-2" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                                </svg>
                                <div>
                                    <h4 class="font-medium text-purple-800">Verify your email</h4>
                                    <p class="text-sm text-purple-600">6-digit verification code to <span id="display-email" class="font-semibold"></span></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="verification_code">Verification Code</label>
                            <div class="verification-container">
                                <input type="text" id="verification_code" name="verification_code" 
                                       placeholder="Enter 6-digit code" class="verification-input"
                                       maxlength="6" pattern="\d{6}" title="Please enter a 6-digit number">
                                <button type="button" id="send_code_btn" class="btn-send-code">Send Code</button>
                            </div>
                            <div id="verification_status" class="verification-status"></div>
                            <div class="resend-code">
                                Didn't receive code? <a href="#" id="resend_code">Resend</a>
                                <span id="countdown"></span>
                            </div>
                        </div>
                        
                        <div class="flex justify-between mt-4">
                            <button type="button" class="btn btn-outline prev-step" 
                                    onclick="prevStep(2)">← Back</button>
                            <button type="button" class="btn btn-primary next-step" 
                                    onclick="nextStep(2)">Continue →</button>
                        </div>
                    </div>
                    
                    <!-- Step 3: Password & ID -->
                    <div class="form-step" data-step="3" style="display: none;">
<div class="form-row">
    <div class="form-group">
        <label for="password">Create Password</label>
        <div class="password-container">
            <input type="password" id="password" name="password" 
                   placeholder="Password" 
                   required minlength="8"
                   pattern="^(?=.*[A-Z])(?=.*[0-9])(?=.*[!@#$%^&*]).{8,}$"
                   autocomplete="new-password"
                   oninput="checkPasswordMatch()">
            <button type="button" class="toggle-password" aria-label="Show password">
                <svg class="eye-icon eye-closed" viewBox="0 0 24 24" width="20" height="20">
                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                    <line x1="1" y1="1" x2="23" y2="23"></line>
                </svg>
                <svg class="eye-icon eye-open" viewBox="0 0 24 24" width="20" height="20">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                </svg>
            </button>
        </div>

    </div>
    <div class="form-group">
        <label for="confirm_password">Confirm Password</label>
        <div class="password-container">
            <input type="password" id="confirm_password" name="confirm_password" 
                   placeholder="Confirm password" required minlength="8"
                   autocomplete="new-password"
                   oninput="checkPasswordMatch()">
            <button type="button" class="toggle-password" aria-label="Show password">
                <svg class="eye-icon eye-closed" viewBox="0 0 24 24" width="20" height="20">
                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                    <line x1="1" y1="1" x2="23" y2="23"></line>
                </svg>
                <svg class="eye-icon eye-open" viewBox="0 0 24 24" width="20" height="20">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                </svg>
            </button>
        </div>
        <div id="password-match" class="text-xs mt-1" style="display: none;"></div>
    </div>
</div>
                        
                        <!-- ID Upload Field -->
                        <div class="form-group">
                            <label for="id_file">Upload ID (Student/Teacher ID)</label>
                            <div class="file-upload-container border-2 border-dashed border-gray-300 rounded-lg p-4 text-center">
                                <input type="file" id="id_file" name="id_file" accept="image/*,.pdf" required
                                       class="hidden" onchange="previewIdFile(this)">
                                <div id="file-preview" class="flex flex-col items-center justify-center py-4">
                                    <svg class="w-12 h-12 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                    </svg>
                                    <p class="text-sm text-gray-600">
                                        <span class="font-semibold text-purple-600 cursor-pointer hover:underline">Click to upload</span> or drag and drop
                                    </p>
                                    <p class="text-xs text-gray-500 mt-1">PNG, JPG, or PDF (Max 5MB)</p>
                                </div>
                                <div id="file-info" class="hidden">
                                    <div class="flex items-center justify-between bg-gray-50 p-2 rounded">
                                        <div class="flex items-center">
                                            <svg class="w-5 h-5 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                            </svg>
                                            <span id="file-name" class="text-sm font-medium text-gray-700 truncate max-w-xs"></span>
                                        </div>
                                        <button type="button" onclick="removeFile()" class="text-gray-400 hover:text-gray-600">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <small class="text-gray-500 text-xs">Upload a clear photo or scan of your valid student or teacher ID</small>
                        </div>
                        
                        <div class="terms-check">
                            <input type="checkbox" id="terms" name="terms" required>
                            <label for="terms">I agree to the <a href="#" id="termsLink" class="text-purple-600 hover:underline">Terms & Conditions</a></label>
                        </div>
                        
                        <div class="flex justify-between mt-6">
                            <button type="button" class="btn btn-outline prev-step" 
                                    onclick="prevStep(3)">← Back</button>
                            <button type="submit" class="btn btn-primary">Create Account</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="welcome-section">
                <div class="welcome-content">
                    <h2>Already have an account?</h2>
                    <p>Sign in to access your personalized learning dashboard</p>
                    <button id="switchToLogin" class="btn btn-outline">Sign In</button>
                </div>
            </div>
        </div>
    </div>
</div>
    <!-- Forgot Password Modal -->
    <div id="forgotPasswordModal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <div class="recovery-card">
                <div class="recovery-header">
                    <h1>Forgot Your Password?</h1>
                    <p>Enter your email to receive a password reset link</p>
                </div>
                
                <form class="recovery-form" id="resetForm" method="POST">
                    <div class="form-group">
                        <label for="forgot-email">Email Address</label>
                        <div class="input-with-icon">
                            <svg class="email-icon" viewBox="0 0 24 24">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                <polyline points="22,6 12,13 2,6"/>
                            </svg>
                            <input type="email" id="forgot-email" name="email" placeholder="your@email.com" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-reset">Send Reset Link</button>
                </form>
                
                <div class="back-to-login">
                    <button class="back-link" id="backToLogin">
                        <svg class="back-arrow" viewBox="0 0 24 24">
                            <path d="M19 12H5M12 19l-7-7 7-7"/>
                        </svg>
                        Back to Sign In
                    </button>
                </div>
                <div id="alert-container" style="display: none;"></div>
            </div>
        </div>
    </div>

    <!-- Terms and Conditions Modal -->
    <div id="termsModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <span class="close-modal">&times;</span>
            <div class="p-8">
                <h2 class="text-2xl font-bold mb-6 text-center">Terms and Conditions</h2>
                <div class="prose max-w-none overflow-y-auto" style="max-height: 70vh;">
                    <p class="text-sm text-gray-600 mb-4">Effective Date: <?php echo date('Y-m-d'); ?></p>
                    <p class="text-sm text-gray-600 mb-8">Platform Name: LearnMate</p>

                    <p class="mb-4">Welcome to our educational platform. These Terms and Conditions ("Terms") govern your use of our web-based educational application ("the Platform"). By accessing or using the Platform, you agree to be bound by these Terms. If you do not agree, please refrain from using the Platform.</p>

                    <h3 class="font-semibold text-lg mt-6 mb-3">1. Eligibility</h3>
                    <p class="mb-4">The Platform is intended for users aged 13 years and above. By using the Platform, you represent that you meet the minimum age requirement.</p>

                    <h3 class="font-semibold text-lg mt-6 mb-3">2. Account Registration</h3>
                    <p class="mb-4">To access certain features, such as uploading content or creating quizzes:</p>
                    <ul class="list-disc pl-6 mb-4 space-y-2">
                        <li>You must register for an account.</li>
                        <li>You are responsible for maintaining the confidentiality of your login credentials.</li>
                        <li>You agree to provide accurate and complete information and to update it as needed.</li>
                    </ul>

                    <h3 class="font-semibold text-lg mt-6 mb-3">3. User Responsibilities</h3>
                    <p class="mb-4">You agree to use the Platform responsibly and not to:</p>
                    <ul class="list-disc pl-6 mb-4 space-y-2">
                        <li>Upload harmful, illegal, or plagiarized content.</li>
                        <li>Share your login credentials with others.</li>
                        <li>Use the platform in a way that disrupts other users or the overall operation of the service.</li>
                        <li>Attempt to gain unauthorized access to other users' accounts or data.</li>
                    </ul>

                    <h3 class="font-semibold text-lg mt-6 mb-3">4. Content Ownership and Usage</h3>
                    <p class="mb-2 font-medium">User-Generated Content:</p>
                    <p class="mb-4">You retain ownership of the files, quizzes, flashcards, and any other materials you upload. However, by submitting content, you grant the Platform a non-exclusive, royalty-free license to store, display, and distribute that content for educational purposes within the Platform.</p>
                    <p class="mb-2 font-medium">Platform Content:</p>
                    <p class="mb-4">All tools, interfaces, and designs are property of the Platform and are protected by intellectual property laws. Unauthorized reproduction is prohibited.</p>

                    <h3 class="font-semibold text-lg mt-6 mb-3">5. Educational Use Only</h3>
                    <p class="mb-4">The Platform is intended solely for educational purposes. Any commercial or promotional use is not permitted without written consent.</p>

                    <h3 class="font-semibold text-lg mt-6 mb-3">6. Privacy</h3>
                    <p class="mb-4">We respect your privacy. Information collected from users will be handled in accordance with our Privacy Policy. We do not sell or share personal data with third parties, except as required by law.</p>

                    <h3 class="font-semibold text-lg mt-6 mb-3">7. Prohibited Content</h3>
                    <p class="mb-4">You agree not to upload, post, or share content that:</p>
                    <ul class="list-disc pl-6 mb-4 space-y-2">
                        <li>Is abusive, offensive, or threatening</li>
                        <li>Violates copyright or intellectual property laws</li>
                        <li>Contains malware or harmful code</li>
                        <li>Encourages cheating or academic dishonesty</li>
                    </ul>

                    <h3 class="font-semibold text-lg mt-6 mb-3">8. Limitation of Liability</h3>
                    <p class="mb-4">We strive to ensure the Platform is functional and secure. However, we are not responsible for:</p>
                    <ul class="list-disc pl-6 mb-4 space-y-2">
                        <li>Any loss or corruption of data</li>
                        <li>Downtime or technical issues</li>
                        <li>The accuracy or reliability of user-generated content</li>
                    </ul>

                    <h3 class="font-semibold text-lg mt-6 mb-3">9. Termination of Access</h3>
                    <p class="mb-4">We reserve the right to suspend or terminate your access if you violate these Terms or engage in any harmful behavior.</p>

                    <h3 class="font-semibold text-lg mt-6 mb-3">10. Changes to Terms</h3>
                    <p class="mb-4">We may update these Terms periodically. You will be notified of significant changes, and continued use of the Platform after changes indicates your acceptance.</p>

                    <h3 class="font-semibold text-lg mt-6 mb-3">11. Contact Information</h3>
                    <p class="mb-4">If you have questions or concerns about these Terms, please contact us at:</p>
                    <p class="mb-2">📧 support@learnmate.com</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // DOM Elements
        const loginModal = document.getElementById('loginModal');
        const signupModal = document.getElementById('signupModal');
        const forgotPasswordModal = document.getElementById('forgotPasswordModal');
        const termsModal = document.getElementById('termsModal');
        const getStartedBtn = document.getElementById('getStartedBtn');
        const signupBtn = document.getElementById('signupBtn');
        const switchToSignup = document.getElementById('switchToSignup');
        const switchToLogin = document.getElementById('switchToLogin');
        const forgotPasswordBtn = document.getElementById('forgotPasswordBtn');
        const backToLogin = document.getElementById('backToLogin');
        const termsLink = document.getElementById('termsLink');
        const closeButtons = document.querySelectorAll('.close-modal');

        // Modal Control Functions
        function openModal(modal) {
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeModal(modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Modal Event Listeners
        getStartedBtn?.addEventListener('click', () => openModal(loginModal));
        signupBtn?.addEventListener('click', () => openModal(signupModal));
        switchToSignup?.addEventListener('click', () => {
            closeModal(loginModal);
            openModal(signupModal);
        });
        switchToLogin?.addEventListener('click', () => {
            closeModal(signupModal);
            openModal(loginModal);
        });
        forgotPasswordBtn?.addEventListener('click', () => {
            closeModal(loginModal);
            openModal(forgotPasswordModal);
        });
        backToLogin?.addEventListener('click', () => {
            closeModal(forgotPasswordModal);
            openModal(loginModal);
        });
        termsLink?.addEventListener('click', (e) => {
            e.preventDefault();
            openModal(termsModal);
        });

        closeButtons.forEach(button => {
            button.addEventListener('click', () => {
                closeModal(loginModal);
                closeModal(signupModal);
                closeModal(forgotPasswordModal);
                closeModal(termsModal);
            });
        });

        window.addEventListener('click', (e) => {
            if (e.target === loginModal) closeModal(loginModal);
            if (e.target === signupModal) closeModal(signupModal);
            if (e.target === forgotPasswordModal) closeModal(forgotPasswordModal);
            if (e.target === termsModal) closeModal(termsModal);
        });

        // Password Toggle Functionality
        document.querySelectorAll('.toggle-password').forEach(button => {
            button.addEventListener('click', function() {
                const input = this.parentElement.querySelector('input');
                const isPassword = input.type === 'password';
                input.type = isPassword ? 'text' : 'password';
                this.classList.toggle('password-visible');
                this.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
            });
        });

        // Step Wizard Functionality
        function updateWizardStep(currentStep, newStep) {
            // Update step content
            document.querySelector(`.step-content[data-step="${currentStep}"]`).classList.remove('active');
            document.querySelector(`.step-content[data-step="${newStep}"]`).classList.add('active');

            // Update wizard steps
            document.querySelector(`.wizard-step[data-step="${currentStep}"]`).classList.remove('active');
            document.querySelector(`.wizard-step[data-step="${newStep}"]`).classList.add('active');

            if (parseInt(currentStep) < parseInt(newStep)) {
                document.querySelector(`.wizard-step[data-step="${currentStep}"]`).classList.add('completed');
            } else {
                document.querySelector(`.wizard-step[data-step="${newStep}"]`).classList.remove('completed');
            }
        }

        // Step Navigation
        document.querySelectorAll('.next-step').forEach(button => {
            button.addEventListener('click', () => {
                const currentStep = button.closest('.step-content').dataset.step;
                const nextStep = button.dataset.next;

                // Validate current step before proceeding
                if (currentStep === '1') {
                    const firstName = document.getElementById('first_name').value.trim();
                    const lastName = document.getElementById('last_name').value.trim();
                    const email = document.getElementById('email').value.trim();
                    const emailError = document.getElementById('email-error');

                    if (!firstName || !lastName || !email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                        emailError.textContent = 'Please fill in all fields with a valid email';
                        emailError.style.display = 'block';
                        return;
                    }

                    // Check email availability
                    fetch('check_email.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `email=${encodeURIComponent(email)}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.exists) {
                            emailError.textContent = 'Email already registered. Please use a different email or sign in.';
                            emailError.style.display = 'block';
                        } else {
                            emailError.style.display = 'none';
                            updateWizardStep(currentStep, nextStep);
                            // Auto-send verification code on step transition
                            sendVerificationCode();
                        }
                    })
                    .catch(error => {
                        emailError.textContent = 'Error checking email. Please try again.';
                        emailError.style.display = 'block';
                        console.error('Error:', error);
                    });
                } else if (currentStep === '2') {
                    const verificationCode = document.getElementById('verification_code').value.trim();
                    const password = document.getElementById('password').value;
                    const confirmPassword = document.getElementById('confirm_password').value;
                    const statusDiv = document.getElementById('verification_status');

                    if (!/^\d{6}$/.test(verificationCode)) {
                        showStatusError(statusDiv, 'Please enter a valid 6-digit verification code');
                        return;
                    }

                    if (password !== confirmPassword) {
                        showStatusError(statusDiv, 'Passwords do not match');
                        return;
                    }

                    // Validate password requirements
                    const passwordRegex = /^(?=.*[A-Z])(?=.*[0-9])(?=.*[!@#$%^&*]).{8,}$/;
                    if (!passwordRegex.test(password)) {
                        showStatusError(statusDiv, 'Password must be at least 8 characters with uppercase, number, and special character');
                        return;
                    }

                    updateWizardStep(currentStep, nextStep);
                }
            });
        });

        document.querySelectorAll('.prev-step').forEach(button => {
            button.addEventListener('click', () => {
                const currentStep = button.closest('.step-content').dataset.step;
                const prevStep = button.dataset.prev;
                updateWizardStep(currentStep, prevStep);
            });
        });

        // Verification Code System
        function startCountdown() {
            const countdownEl = document.getElementById('countdown');
            const resendLink = document.getElementById('resend_code');
            const sendCodeBtn = document.getElementById('send_code_btn');
            let timeLeft = 60;
            
            resendLink.style.display = 'none';
            countdownEl.style.display = 'inline';
            sendCodeBtn.disabled = true;
            
            const timer = setInterval(() => {
                timeLeft--;
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                countdownEl.textContent = `(${minutes}:${seconds < 10 ? '0' : ''}${seconds})`;
                
                if (timeLeft <= 0) {
                    clearInterval(timer);
                    countdownEl.style.display = 'none';
                    resendLink.style.display = 'inline';
                    sendCodeBtn.disabled = false;
                }
            }, 1000);
        }

        function sendVerificationCode() {
            const email = document.getElementById('email').value;
            const btn = document.getElementById('send_code_btn');
            const statusDiv = document.getElementById('verification_status');
            const emailError = document.getElementById('email-error');
            
            // Clear previous messages
            statusDiv.textContent = '';
            statusDiv.className = 'verification-status';
            emailError.style.display = 'none';
            
            // Validate email
            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                emailError.textContent = 'Please enter a valid email';
                emailError.style.display = 'block';
                return;
            }
            
            // Disable button and show loading
            btn.disabled = true;
            btn.textContent = 'Sending...';
            statusDiv.style.display = 'block';
            statusDiv.textContent = 'Checking email...';
            
            // Send verification code
            fetch('send_verification_code.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `email=${encodeURIComponent(email)}`
            })
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    statusDiv.textContent = 'Verification code sent! Check your email.';
                    statusDiv.className = 'verification-status success';
                    startCountdown();
                } else {
                    throw new Error(data.message || 'Failed to send verification code');
                }
            })
            .catch(error => {
                emailError.textContent = error.message;
                emailError.style.display = 'block';
                console.error('Error:', error);
            })
            .finally(() => {
                btn.textContent = 'Send Code';
            });
        }

        // Signup Form Submission
        document.querySelector('.auth-form')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const form = this;
            const formData = new FormData(form);
            const submitBtn = form.querySelector('button[type="submit"]');
            const statusDiv = document.getElementById('verification_status');
            
            // Validate step 3
            const idFile = document.getElementById('id_file').files[0];
            const terms = document.getElementById('terms').checked;

            if (!idFile) {
                showStatusError(statusDiv, 'Please upload your ID file');
                return;
            }

            if (!terms) {
                showStatusError(statusDiv, 'Please agree to the Terms & Conditions');
                return;
            }

            // Disable submit button during processing
            submitBtn.disabled = true;
            submitBtn.textContent = 'Creating account...';
            
            // Submit the form
            fetch('createAcc.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
.then(data => {
    if (data.success) {
        // Show success message
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-success';
        alertDiv.textContent = data.message;
         markAllStepsCompleted();
        // Insert after the form
        form.parentNode.insertBefore(alertDiv, form.nextSibling);
        
        // Hide the form
        form.style.display = 'none';
        
        // Mark all steps as completed
        document.querySelectorAll('.step').forEach(step => {
            step.classList.add('completed');
            step.classList.remove('active');
        });
        
        // Update progress bar to 100%
        document.querySelector('.progress-fill').style.width = '100%';
        
        // Show a back to login button
        const backButton = document.createElement('button');
        backButton.className = 'btn btn-outline';
        backButton.textContent = 'Back to Login';
        backButton.onclick = function() {
            closeModal(signupModal);
            openModal(loginModal);
        };
        
        alertDiv.parentNode.insertBefore(backButton, alertDiv.nextSibling);
    } else {
        throw new Error(data.message || 'Account creation failed');
    }
})
            .catch(error => {
                showStatusError(statusDiv, error.message);
                console.error('Error:', error);
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Get Started';
            });
        });

        // Forgot Password Form
        document.getElementById('resetForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const form = this;
            const formData = new FormData(form);
            const submitBtn = form.querySelector('button[type="submit"]');
            const alertContainer = document.getElementById('alert-container');
            
            submitBtn.disabled = true;
            submitBtn.textContent = 'Sending...';
            alertContainer.innerHTML = '';
            alertContainer.style.display = 'block';
            
            fetch('send_password_reset.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(message => {
                const alertDiv = document.createElement('div');
                alertDiv.className = message.includes('sent') || message.includes('exists') 
                    ? 'alert-success' 
                    : 'alert-error';
                alertDiv.textContent = message;
                alertDiv.style.padding = '15px';
                alertDiv.style.margin = '10px auto';
                alertDiv.style.borderRadius = '4px';
                alertContainer.appendChild(alertDiv);
                
                setTimeout(() => {
                    alertDiv.style.opacity = '0';
                    setTimeout(() => {
                        alertContainer.style.display = 'none';
                    }, 500);
                }, 5000);
            })
            .catch(error => {
                console.error('Error:', error);
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert-error';
                alertDiv.textContent = 'An error occurred. Please try again.';
                alertContainer.appendChild(alertDiv);
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Send Reset Link';
            });
        });

        // Helper Functions
        function showStatusError(element, message) {
            element.textContent = message;
            element.className = 'verification-status error';
            element.style.display = 'block';
        }

        // Event Listeners
        document.getElementById('send_code_btn')?.addEventListener('click', sendVerificationCode);
        document.getElementById('resend_code')?.addEventListener('click', (e) => {
            e.preventDefault();
            sendVerificationCode();
        });
        // Add these functions to your existing JavaScript

// Form step navigation
let currentStep = 1;

function nextStep(step) {
    // Validate current step before proceeding
    if (step === 1 && !validateStep1()) return;
    if (step === 2 && !validateStep2()) return;
    
    // Hide current step
    document.querySelector(`.form-step[data-step="${step}"]`).style.display = 'none';
    
    // Show next step
    currentStep = step + 1;
    document.querySelector(`.form-step[data-step="${currentStep}"]`).style.display = 'block';
    
    // Update progress bar
    updateProgress(currentStep);
    
    // For step 2, display the email
    if (currentStep === 2) {
        document.getElementById('display-email').textContent = document.getElementById('email').value;
    }
}

function prevStep(step) {
    // Hide current step
    document.querySelector(`.form-step[data-step="${step}"]`).style.display = 'none';
    
    // Show previous step
    currentStep = step - 1;
    document.querySelector(`.form-step[data-step="${currentStep}"]`).style.display = 'block';
    
    // Update progress bar
    updateProgress(currentStep);
}

function updateProgress(step) {
    const progressFill = document.querySelector('.progress-fill');
    const steps = document.querySelectorAll('.step');
    
    // Update progress bar width
    progressFill.style.width = `${(step - 1) * 33}%`;
    
    // Update step indicators
    steps.forEach((s, index) => {
        if (index < step - 1) {
            s.classList.add('completed');
            s.classList.remove('active');
        } else if (index === step - 1) {
            s.classList.add('active');
            s.classList.remove('completed');
        } else {
            s.classList.remove('active', 'completed');
        }
    });
}

// Step validation
function validateStep1() {
    const firstName = document.getElementById('first_name');
    const lastName = document.getElementById('last_name');
    const email = document.getElementById('email');
    const emailError = document.getElementById('email-error');
    
    // Reset errors
    emailError.style.display = 'none';
    
    // Validate names
    if (!firstName.value.trim()) {
        showError(firstName, 'Please enter your first name');
        return false;
    }
    
    if (!lastName.value.trim()) {
        showError(lastName, 'Please enter your last name');
        return false;
    }
    
    // Validate email
    if (!email.value || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
        showError(email, 'Please enter a valid email address');
        return false;
    }
    
    return true;
}

function validateStep2() {
    const verificationCode = document.getElementById('verification_code');
    const statusDiv = document.getElementById('verification_status');
    
    if (!verificationCode.value || !/^\d{6}$/.test(verificationCode.value)) {
        statusDiv.textContent = 'Please enter a valid 6-digit verification code';
        statusDiv.className = 'verification-status error';
        statusDiv.style.display = 'block';
        return false;
    }
    
    return true;
}

function showError(input, message) {
    const errorDiv = input.nextElementSibling;
    if (errorDiv && errorDiv.classList.contains('error-text')) {
        errorDiv.textContent = message;
        errorDiv.style.display = 'block';
    } else {
        const newError = document.createElement('div');
        newError.className = 'error-text';
        newError.textContent = message;
        input.parentNode.insertBefore(newError, input.nextSibling);
    }
    input.focus();
}



// Password match checker
function checkPasswordMatch() {
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    const matchDiv = document.getElementById('password-match');
    
    if (!password || !confirmPassword) {
        matchDiv.style.display = 'none';
        return;
    }
    
    matchDiv.style.display = 'block';
    
    if (password === confirmPassword) {
        matchDiv.textContent = '✓ Passwords match';
        matchDiv.className = 'text-green-600';
    } else {
        matchDiv.textContent = '✗ Passwords do not match';
        matchDiv.className = 'text-red-600';
    }
}

// Add event listeners for password fields
document.getElementById('password').addEventListener('input', checkPasswordMatch);
document.getElementById('confirm_password').addEventListener('input', checkPasswordMatch);

// File upload preview
function previewIdFile(input) {
    const filePreview = document.getElementById('file-preview');
    const fileInfo = document.getElementById('file-info');
    const fileName = document.getElementById('file-name');
    
    if (input.files && input.files[0]) {
        const file = input.files[0];
        
        // Validate file size (5MB max)
        if (file.size > 5 * 1024 * 1024) {
            alert('File size exceeds 5MB limit');
            input.value = '';
            return;
        }
        
        fileName.textContent = file.name;
        filePreview.style.display = 'none';
        fileInfo.style.display = 'block';
    }
}

function removeFile() {
    const fileInput = document.getElementById('id_file');
    const filePreview = document.getElementById('file-preview');
    const fileInfo = document.getElementById('file-info');
    
    fileInput.value = '';
    fileInfo.style.display = 'none';
    filePreview.style.display = 'flex';
}

// Input validation helpers
function validateName(input) {
    const errorDiv = input.nextElementSibling.nextElementSibling;
    if (!input.value.trim()) {
        if (errorDiv && errorDiv.classList.contains('error-text')) {
            errorDiv.style.display = 'block';
        }
    } else {
        if (errorDiv && errorDiv.classList.contains('error-text')) {
            errorDiv.style.display = 'none';
        }
    }
}

function validateEmail(input) {
    const errorDiv = document.getElementById('email-error');
    if (!input.value || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input.value)) {
        errorDiv.textContent = 'Please enter a valid email address';
        errorDiv.style.display = 'block';
    } else {
        errorDiv.style.display = 'none';
    }
}

// Make file upload area clickable
document.querySelector('.file-upload-container').addEventListener('click', function() {
    document.getElementById('id_file').click();
});

// Allow drag and drop for file upload
document.querySelector('.file-upload-container').addEventListener('dragover', function(e) {
    e.preventDefault();
    this.classList.add('border-purple-500', 'bg-purple-50');
});

document.querySelector('.file-upload-container').addEventListener('dragleave', function(e) {
    e.preventDefault();
    this.classList.remove('border-purple-500', 'bg-purple-50');
});

document.querySelector('.file-upload-container').addEventListener('drop', function(e) {
    e.preventDefault();
    this.classList.remove('border-purple-500', 'bg-purple-50');
    
    const fileInput = document.getElementById('id_file');
    if (e.dataTransfer.files.length) {
        fileInput.files = e.dataTransfer.files;
        previewIdFile(fileInput);
    }
});

// Add this function to your JavaScript
function markAllStepsCompleted() {
    const steps = document.querySelectorAll('.step');
    steps.forEach(step => {
        step.classList.add('completed');
        step.classList.remove('active');
    });
    
    // Update progress bar to 100%
    document.querySelector('.progress-fill').style.width = '100%';
    
    // Update step numbers to checkmarks
    document.querySelectorAll('.step-number').forEach(number => {
        number.innerHTML = '✓';
    });
}
        </script>
</body>
</html>