<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['auth_provider']) || $_SESSION['auth_provider'] !== 'google') {
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? '';
    
    // Validate inputs
    if (empty($password)) {
        $error = 'Password is required';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (!in_array($role, ['student', 'teacher'])) {
        $error = 'Please select a valid role';
    } elseif (!preg_match('/^(?=.*[A-Z])(?=.*[0-9])(?=.*[!@#$%^&*]).{8,}$/', $password)) {
        $error = 'Password must contain at least 8 characters with uppercase, number and special character';
    } else {
        // Update user with password and role
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ?, role = ? WHERE id = ?");
        if ($stmt->execute([$hashedPassword, $role, $_SESSION['user_id']])) {
            // Update session with new role
            $_SESSION['role'] = $role;
            unset($_SESSION['auth_provider']); // Now they can login with email/password
            $success = 'Account setup complete!';
            
            // Redirect to appropriate dashboard after 2 seconds
            header("Refresh: 2; url={$role}_dashboard.php");
        } else {
            $error = 'Failed to complete account setup';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Your Account - Learnmate</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
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
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #F9FAFB;
        }

        .auth-container {
            background: var(--light);
            border-radius: 1rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            width: 100%;
            max-width: 28rem;
            margin: 2rem auto;
            position: relative;
            overflow: hidden;
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

        .signin-section {
            padding: 2.5rem;
            display: flex;
            flex-direction: column;
        }

        .signin-header {
            margin-bottom: 1.875rem;
            text-align: center;
        }

        .signin-header h2 {
            color: var(--text);
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .signin-subtitle {
            color: var(--text-light);
            font-size: 0.875rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-bottom: 1.25rem;
        }

        .form-group label {
            font-size: 0.875rem;
            color: var(--text);
            font-weight: 500;
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

        .auth-form input {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            font-size: 0.875rem;
            transition: all 0.3s ease;
        }

        .auth-form input:focus {
            outline: none;
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(168, 85, 247, 0.2);
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
            padding: 0.875rem;
            border-radius: 0.5rem;
            font-weight: 500;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            border: none;
            width: 100%;
        }

        .btn-primary:hover {
            background-color: var(--primary-light);
        }

        .role-selection {
            margin: 1.5rem 0;
        }

        .role-option {
            display: sticky;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            margin-bottom: 0.75rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .role-option:hover {
            border-color: var(--primary-light);
            background-color: var(--secondary);
        }

        .role-option input {
            margin-left: auto;
            margin-right: auto;
        }
        .role-label {
            flex-grow: 1;
        }
        .role-title {
            font-weight: 500;
            color: var(--text);
            font-size: 0.875rem;
        }

        .role-desc {
            font-size: 0.8125rem;
            color: var(--text-light);
            margin-top: 0.25rem;
        }

        .error-message {
            color: var(--error);
            font-size: 0.875rem;
            margin-bottom: 1rem;
            padding: 0.75rem;
            background-color: rgba(239, 68, 68, 0.1);
            border-radius: 0.5rem;
            border-left: 4px solid var(--error);
        }

        .success-message {
            color: #16a34a;
            font-size: 0.875rem;
            margin-bottom: 1rem;
            padding: 0.75rem;
            background-color: rgba(22, 163, 74, 0.1);
            border-radius: 0.5rem;
            border-left: 4px solid #16a34a;
        }

        .password-strength {
            font-size: 0.8125rem;
            margin-top: 0.3125rem;
        }

        .password-strength.strong {
            color: #4f8a10;
        }

        .password-strength.medium {
            color: #FFA500;
        }

        .password-strength.weak {
            color: var(--error);
        }
    </style>
</head>
<body class="font-sans bg-gray-50 min-h-screen flex items-center justify-center p-4">
    <div class="auth-container">
        <div class="signin-section">
            <div class="signin-header">
                <h2>Complete Your Account</h2>
                <p class="signin-subtitle">Please choose your role and set a password for local login</p>
                
                <?php if ($error): ?>
                    <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
                    <p class="text-center text-sm text-gray-600 mt-4">Redirecting you to your dashboard...</p>
                <?php else: ?>
                    <form method="POST" class="auth-form">
                    <div class="form-group role-selection">
                        <label>Select Your Role:</label>
                        
                        <label class="role-option">
                            <div class="role-label">
                                <span class="role-title">Student</span>
                                <span class="role-desc">I'm here to learn and study</span>
                            </div>
                            <input type="radio" name="role" value="student" required>
                        </label>
                        
                        <label class="role-option">
                            <div class="role-label">
                                <span class="role-title">Teacher</span>
                                <span class="role-desc">I'll be creating and managing courses</span>
                            </div>
                            <input type="radio" name="role" value="teacher" required>
                        </label>
                    </div>
                        
                        <div class="form-group">
                            <label for="password">New Password</label>
                            <div class="password-container">
                                <input type="password" id="password" name="password" 
                                       placeholder="Password" 
                                       required minlength="8"
                                       pattern="^(?=.*[A-Z])(?=.*[0-9])(?=.*[!@#$%^&*]).{8,}$"
                                       title="Must contain at least 8 character with Uppercase, number and special character"
                                       autocomplete="new-password">
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
                            <small class="text-xs text-gray-500">Must contain at least 8 characters with uppercase, number and special character</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm Password</label>
                            <div class="password-container">
                                <input type="password" id="confirm_password" name="confirm_password" 
                                       placeholder="Confirm password" required minlength="8"
                                       autocomplete="new-password">
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
                        
                        <button type="submit" class="btn-primary">Complete Setup</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Password toggle functionality
        document.querySelectorAll('.toggle-password').forEach(button => {
            button.addEventListener('click', function() {
                const input = this.parentElement.querySelector('input');
                const isPassword = input.type === 'password';
                input.type = isPassword ? 'text' : 'password';
                this.classList.toggle('password-visible');
                this.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
            });
        });

        // Password strength indicator (optional)
        const passwordInput = document.getElementById('password');
        if (passwordInput) {
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                const strengthIndicator = document.createElement('div');
                strengthIndicator.className = 'password-strength';
                
                // Remove any existing indicator
                const existingIndicator = this.parentElement.querySelector('.password-strength');
                if (existingIndicator) {
                    existingIndicator.remove();
                }
                
                if (password.length === 0) return;
                
                let strength = 'weak';
                if (password.length >= 12 && /[A-Z]/.test(password) && /[0-9]/.test(password) && /[!@#$%^&*]/.test(password)) {
                    strength = 'strong';
                } else if (password.length >= 8 && /[A-Z]/.test(password) && /[0-9]/.test(password)) {
                    strength = 'medium';
                }
                
                strengthIndicator.textContent = `Strength: ${strength}`;
                strengthIndicator.classList.add(strength);
                this.parentElement.appendChild(strengthIndicator);
            });
        }
    </script>
</body>
</html>