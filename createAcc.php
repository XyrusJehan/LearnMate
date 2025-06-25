<?php
// createAcc.php
require 'db.php';
require 'mailer_verify.php';

session_start();

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

$errors = [];
$success = '';

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Rate limiting
$rateLimitKey = 'acc_create_' . md5($_SERVER['REMOTE_ADDR']);
if (!isset($_SESSION[$rateLimitKey])) {
    $_SESSION[$rateLimitKey] = ['attempts' => 0, 'last_attempt' => 0];
}

// Handle verification code request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_code'])) {
    $email = trim($_POST['email'] ?? '');
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        exit;
    }
    
    // Generate 6-digit code
    $verificationCode = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $_SESSION['verification'] = [
        'code' => $verificationCode,
        'email' => $email,
        'expiry' => time() + 600, // 10 minutes
        'attempts' => 0
    ];
    
    if (sendVerificationCode($email, $verificationCode)) {
        echo json_encode(['success' => true, 'message' => 'Verification code sent!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send code. Please try again.']);
    }
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $response = ['success' => false, 'message' => ''];
    
    try {
        // Validate input
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $role = $_POST['role'] ?? 'student';
        $verificationCode = $_POST['verification_code'] ?? '';
        
        // Basic validation
        if (empty($firstName) || empty($lastName) || empty($email) || empty($password) || empty($confirmPassword)) {
            throw new Exception('All fields are required');
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format');
        }
        
        if ($password !== $confirmPassword) {
            throw new Exception('Passwords do not match');
        }
        
        if (!preg_match('/^(?=.*[A-Z])(?=.*[0-9])(?=.*[!@#$%^&*]).{8,}$/', $password)) {
            throw new Exception('Password must be at least 8 characters with 1 uppercase letter, 1 number, and 1 special character');
        }
        
        if (!preg_match('/^\d{6}$/', $verificationCode)) {
            throw new Exception('Invalid verification code');
        }
        
        // Check if email already exists (double check)
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            throw new Exception('Email already registered');
        }
        
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert user into database
        $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, password, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$firstName, $lastName, $email, $hashedPassword, $role]);
        
        // Get the new user ID
        $userId = $pdo->lastInsertId();
        
        // Set session variables
        $_SESSION['user_id'] = $userId;
        $_SESSION['role'] = $role;
        $_SESSION['first_name'] = $firstName;
        $_SESSION['last_name'] = $lastName;
        
        $response['success'] = true;
        $response['role'] = $role;
        
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// If not a POST request or not registering, redirect to index
header('Location: index.php');
exit();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;">
    <title>Create Account</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="CAstyles.css">
    <style>
        .verification-container {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .verification-input {
            flex: 1;
            padding: 14px 16px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 16px;
            text-align: center;
            letter-spacing: 2px;
        }
        
        .btn-send-code {
            background-color: var(--secondary);
            color: var(--primary);
            border: 1px solid var(--primary-light);
            padding: 14px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 14px;
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
            font-size: 14px;
            margin-top: 5px;
            padding: 8px;
            border-radius: 5px;
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
            font-size: 14px;
            color: var(--text-light);
            text-align: center;
            margin-top: 10px;
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
            margin-left: 5px;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-header">
            <h1>Create Account</h1>
            <p>Join our community today and unlock exclusive features</p>
            <?php if ($success): ?>
                <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if (!empty($errors['rate_limit'])): ?>
                <div class="error-message"><?php echo htmlspecialchars($errors['rate_limit']); ?></div>
            <?php endif; ?>
            <?php if (!empty($errors['csrf'])): ?>
                <div class="error-message"><?php echo htmlspecialchars($errors['csrf']); ?></div>
            <?php endif; ?>
            <?php if (!empty($errors['database'])): ?>
                <div class="error-message"><?php echo htmlspecialchars($errors['database']); ?></div>
            <?php endif; ?>
            <div class="signin-prompt">
                Already have an account? <a href="index.php">Sign In</a>
            </div>
        </div>
        
        <form class="auth-form" method="POST" action="createAcc.php" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="register" value="1">
            
            <div class="form-row">
                <div class="form-group">
                    <label for="first_name">First Name</label>
                    <input type="text" id="first_name" name="first_name" 
                           placeholder="Enter your first name" required
                           value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                    <?php if (!empty($errors['first_name'])): ?>
                        <span class="error-text"><?php echo htmlspecialchars($errors['first_name']); ?></span>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name" 
                           placeholder="Enter your last name" required
                           value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                    <?php if (!empty($errors['last_name'])): ?>
                        <span class="error-text"><?php echo htmlspecialchars($errors['last_name']); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" 
                       placeholder="Enter your email" required
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                <?php if (!empty($errors['email'])): ?>
                    <span class="error-text"><?php echo htmlspecialchars($errors['email']); ?></span>
                <?php endif; ?>
            </div>
            
            <!-- Verification Code Section -->
            <div class="form-group">
                <div class="verification-container">
                    <input type="text" id="verification_code" name="verification_code" 
                           placeholder="Enter 6-digit code" class="verification-input"
                           maxlength="6" pattern="\d{6}" title="Please enter a 6-digit number"
                           value="<?php echo isset($_POST['verification_code']) ? htmlspecialchars($_POST['verification_code']) : ''; ?>">
                    <button type="button" id="send_code_btn" class="btn-send-code">Send Code</button>
                </div>
                <div id="verification_status" class="verification-status"></div>
                <?php if (!empty($errors['verification_code'])): ?>
                    <span class="error-text"><?php echo htmlspecialchars($errors['verification_code']); ?></span>
                <?php endif; ?>
                <div class="resend-code">
                    Didn't receive code? <a href="#" id="resend_code">Resend</a>
                    <span id="countdown"></span>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-container">
                        <input type="password" id="password" name="password" 
                               placeholder="••••••••" required minlength="10"
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
                    <div id="password-strength" class="password-strength"></div>
                    <?php if (!empty($errors['password'])): ?>
                        <span class="error-text"><?php echo htmlspecialchars($errors['password']); ?></span>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <div class="password-container">
                        <input type="password" id="confirm_password" name="confirm_password" 
                               placeholder="••••••••" required minlength="10"
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
                    <?php if (!empty($errors['confirm_password'])): ?>
                        <span class="error-text"><?php echo htmlspecialchars($errors['confirm_password']); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="form-group">
                <label for="role">Account Type</label>
                <select id="role" name="role" class="form-control">
                    <option value="student" <?php echo (isset($_POST['role']) && $_POST['role'] === 'student') ? 'selected' : ''; ?>>Student</option>
                    <option value="teacher" <?php echo (isset($_POST['role']) && $_POST['role'] === 'teacher') ? 'selected' : ''; ?>>Teacher</option>
                </select>
            </div>
            
            <div class="terms-check">
                <input type="checkbox" id="terms" name="terms" required <?php echo isset($_POST['terms']) ? 'checked' : ''; ?>>
                <label for="terms">I agree to the <a href="#" target="_blank">Terms & Conditions</a></label>
                <?php if (!empty($errors['terms'])): ?>
                    <span class="error-text"><?php echo htmlspecialchars($errors['terms']); ?></span>
                <?php endif; ?>
            </div>
            
            <button type="submit" class="btn btn-primary">Get Started</button>
        </form>
    </div>

    <script>
        // Password strength meter (existing code)
        function calculatePasswordStrength(password) {
            let strength = 0;
            if (password.length > 10) strength += 2;
            else if (password.length > 7) strength += 1;
            if (/[A-Z]/.test(password)) strength += 1;
            if (/[a-z]/.test(password)) strength += 1;
            if (/[0-9]/.test(password)) strength += 1;
            if (/[^A-Za-z0-9]/.test(password)) strength += 2;
            return strength;
        }
        
        function updatePasswordStrength() {
            const password = document.getElementById('password').value;
            const strengthMeter = document.getElementById('password-strength');
            const strength = calculatePasswordStrength(password);
            
            if (password.length === 0) {
                strengthMeter.textContent = '';
                strengthMeter.className = 'password-strength';
                return;
            }
            
            if (strength >= 6) {
                strengthMeter.textContent = 'Strong password';
                strengthMeter.className = 'password-strength strong';
            } else if (strength >= 3) {
                strengthMeter.textContent = 'Medium password';
                strengthMeter.className = 'password-strength medium';
            } else {
                strengthMeter.textContent = 'Weak password';
                strengthMeter.className = 'password-strength weak';
            }
        }
        
        // Password visibility toggle (existing code)
        document.querySelectorAll('.toggle-password').forEach(function(button) {
            button.addEventListener('click', function() {
                const input = this.parentElement.querySelector('input');
                const isPassword = input.type === 'password';
                input.type = isPassword ? 'text' : 'password';
                this.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
            });
        });
        
        // Verification code handling
        document.getElementById('send_code_btn').addEventListener('click', sendVerificationCode);
        document.getElementById('resend_code').addEventListener('click', function(e) {
            e.preventDefault();
            sendVerificationCode();
        });
        
        function sendVerificationCode() {
            const email = document.getElementById('email').value;
            const emailError = document.querySelector('#email + .error-text');
            const btn = document.getElementById('send_code_btn');
            const statusDiv = document.getElementById('verification_status');
            
            // Validate email
            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                if (!emailError) {
                    const emailGroup = document.querySelector('#email').parentNode;
                    const errorSpan = document.createElement('span');
                    errorSpan.className = 'error-text';
                    errorSpan.textContent = 'Please enter a valid email first';
                    emailGroup.appendChild(errorSpan);
                }
                return;
            }
            
            // Disable button and show loading
            btn.disabled = true;
            btn.textContent = 'Sending...';
            statusDiv.style.display = 'block';
            statusDiv.textContent = 'Sending verification code...';
            statusDiv.className = 'verification-status';
            
            // Send AJAX request
            fetch('createAcc.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `email=${encodeURIComponent(email)}&send_code=1&csrf_token=<?php echo $_SESSION['csrf_token']; ?>`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    statusDiv.textContent = data.message;
                    statusDiv.className = 'verification-status success';
                    startCountdown();
                } else {
                    statusDiv.textContent = data.message;
                    statusDiv.className = 'verification-status error';
                }
                btn.disabled = false;
                btn.textContent = 'Send Code';
            })
            .catch(error => {
                statusDiv.textContent = 'Error sending code. Please try again.';
                statusDiv.className = 'verification-status error';
                btn.disabled = false;
                btn.textContent = 'Send Code';
                console.error('Error:', error);
            });
        }
        
        function startCountdown() {
            const countdownEl = document.getElementById('countdown');
            const resendLink = document.getElementById('resend_code');
            let timeLeft = 60;
            
            resendLink.style.display = 'none';
            countdownEl.style.display = 'inline';
            countdownEl.textContent = `(0:${timeLeft < 10 ? '0' : ''}${timeLeft})`;
            
            const timer = setInterval(() => {
                timeLeft--;
                countdownEl.textContent = `(0:${timeLeft < 10 ? '0' : ''}${timeLeft})`;
                
                if (timeLeft <= 0) {
                    clearInterval(timer);
                    countdownEl.style.display = 'none';
                    resendLink.style.display = 'inline';
                }
            }, 1000);
        }
        
        // Form submission validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            if (password.length < 10) {
                e.preventDefault();
                alert('Password must be at least 10 characters long!');
                return false;
            }
            
            return true;
        });
        
        // Initialize password strength meter
        document.getElementById('password').addEventListener('input', updatePasswordStrength);
    </script>
</body>
</html>