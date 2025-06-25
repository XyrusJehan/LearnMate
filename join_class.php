<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require 'db.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';
$isInvalidCode = false; // Flag for invalid codes

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $classCode = strtoupper(trim($_POST['class_code']));
    
    if (empty($classCode)) {
        $error = 'Please enter a class code';
    } else {
        try {
            // Check if class exists
            $stmt = $pdo->prepare("SELECT id, class_name FROM classes WHERE class_code = ?");
            $stmt->execute([$classCode]);
            $class = $stmt->fetch();
            
            if (!$class) {
                $error = 'Invalid class code';
                $isInvalidCode = true;
            } else {
                // Check if student is already enrolled
                $stmt = $pdo->prepare("
                    SELECT id FROM class_students 
                    WHERE class_id = ? AND student_id = ?
                ");
                $stmt->execute([$class['id'], $_SESSION['user_id']]);
                
                if ($stmt->fetch()) {
                    $error = 'You are already enrolled in this class';
                } else {
                    // Enroll student in class
                    $stmt = $pdo->prepare("
                        INSERT INTO class_students (class_id, student_id) 
                        VALUES (?, ?)
                    ");
                    $stmt->execute([$class['id'], $_SESSION['user_id']]);
                    
                    // Add activity
                    $stmt = $pdo->prepare("
                        INSERT INTO activities (student_id, description, icon, group_id) 
                        VALUES (?, ?, 'user-plus', ?)
                    ");
                    $stmt->execute([
                        $_SESSION['user_id'],
                        'Joined class: ' . $class['class_name'],
                        $class['id']
                    ]);
                    
                    // Set session variables for success message
                    $_SESSION['join_success'] = true;
                    $_SESSION['joined_class_name'] = $class['class_name'];
                    
                    // Redirect to class details page
                    header("Location: student_class_details.php?id=" . $class['id']);
                    exit();
                }
            }
        } catch (PDOException $e) {
            $error = 'An error occurred. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join Class - LearnMate</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/theme.css">
    <style>
        .join-class-container {
            max-width: 400px;
            margin: 40px auto;
            padding: var(--space-lg);
            background-color: var(--bg-white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
        }

        .join-class-header {
            text-align: center;
            margin-bottom: var(--space-lg);
        }

        .join-class-header h1 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: var(--space-sm);
        }

        .join-class-header p {
            color: var(--text-light);
            font-size: 14px;
        }

        .form-group {
            margin-bottom: var(--space-md);
        }

        .form-group label {
            display: block;
            margin-bottom: var(--space-xs);
            font-weight: 500;
            color: var(--text-dark);
        }

        .form-control {
            width: 100%;
            padding: var(--space-sm) var(--space-md);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-md);
            font-size: 16px;
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(127, 86, 217, 0.1);
        }

        .btn-join {
            width: 100%;
            padding: var(--space-sm) var(--space-md);
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-join:hover {
            background-color: var(--primary-dark);
        }

        .btn-join:disabled {
            background-color: var(--text-light);
            cursor: not-allowed;
        }

        .alert {
            padding: var(--space-sm) var(--space-md);
            border-radius: var(--radius-md);
            margin-bottom: var(--space-md);
            font-size: 14px;
        }

        .alert-error {
            background-color: #FEF3F2;
            color: #B42318;
            border: 1px solid #FDA29B;
        }

        .alert-warning {
            background-color: #FFFAEB;
            color: #B54708;
            border: 1px solid #FEC84B;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: var(--space-xs);
            color: var(--text-medium);
            text-decoration: none;
            font-size: 14px;
            margin-bottom: var(--space-md);
        }

        .back-link:hover {
            color: var(--primary);
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .retry-status {
            text-align: center;
            margin-top: 10px;
            font-size: 14px;
            color: var(--text-light);
            min-height: 20px;
        }

        .retry-status.retrying {
            color: var(--primary);
        }

        .retry-status.error {
            color: var(--danger);
        }
    </style>
</head>
<body data-theme="<?php echo isset($_SESSION['theme']) ? htmlspecialchars($_SESSION['theme']) : 'light'; ?>">
    <div class="loading-overlay">
        <div class="loading-spinner"></div>
    </div>

    <div class="join-class-container">
        <a href="student_dashboard.php" class="back-link">
            <i class="fas fa-arrow-left"></i>
            Back to Dashboard
        </a>

        <div class="join-class-header">
            <h1>Join a Class</h1>
            <p>Enter the class code provided by your teacher</p>
        </div>

        <?php if ($error): ?>
            <div class="alert <?php echo $isInvalidCode ? 'alert-error' : 'alert-warning'; ?>">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="joinClassForm">
            <div class="form-group">
                <label for="class_code">Class Code</label>
                <input type="text" 
                       id="class_code" 
                       name="class_code" 
                       class="form-control" 
                       placeholder="Enter 6-digit code"
                       maxlength="6"
                       pattern="[A-Za-z0-9]{6}"
                       required
                       autocomplete="off"
                       value="<?php echo isset($_POST['class_code']) ? htmlspecialchars($_POST['class_code']) : ''; ?>">
            </div>
            <button type="submit" class="btn-join" id="joinButton">
                Join Class
            </button>
            <div class="retry-status" id="retryStatus"></div>
        </form>
    </div>

    <script>
        document.getElementById('class_code').addEventListener('input', function(e) {
            this.value = this.value.toUpperCase();
        });

        document.getElementById('joinClassForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const joinButton = document.getElementById('joinButton');
            const loadingOverlay = document.querySelector('.loading-overlay');
            const retryStatus = document.getElementById('retryStatus');
            const classCode = document.getElementById('class_code').value;
            
            loadingOverlay.style.display = 'flex';
            joinButton.disabled = true;
            retryStatus.className = 'retry-status retrying';
            
            let retryCount = 0;
            const maxRetries = 5;
            const retryDelay = 3000;
            
            async function attemptJoin() {
                retryCount++;
                retryStatus.textContent = `Attempting to join... (${retryCount}/${maxRetries})`;
                
                try {
                    const formData = new FormData();
                    formData.append('class_code', classCode);
                    
                    const response = await fetch(window.location.href, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    
                    const text = await response.text();
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(text, 'text/html');
                    
                    const errorDiv = doc.querySelector('.alert-error');
                    const warningDiv = doc.querySelector('.alert-warning');
                    
                    if (response.redirected) {
                        window.location.href = response.url;
                    } else if (errorDiv && errorDiv.textContent.includes('Invalid class code')) {
                        retryStatus.className = 'retry-status error';
                        retryStatus.textContent = 'Invalid class code';
                        setTimeout(() => window.location.reload(), 1000);
                    } else if (warningDiv && warningDiv.textContent.includes('already enrolled')) {
                        retryStatus.className = 'retry-status error';
                        retryStatus.textContent = 'Already enrolled in this class';
                        setTimeout(() => window.location.reload(), 1000);
                    } else if (retryCount < maxRetries) {
                        retryStatus.textContent = `Connection issue, retrying... (${retryCount}/${maxRetries})`;
                        setTimeout(attemptJoin, retryDelay);
                    } else {
                        retryStatus.className = 'retry-status error';
                        retryStatus.textContent = 'Failed to join class. Please try again.';
                        setTimeout(() => window.location.reload(), 2000);
                    }
                } catch (error) {
                    console.error('Error:', error);
                    if (retryCount < maxRetries) {
                        retryStatus.textContent = `Network error, retrying... (${retryCount}/${maxRetries})`;
                        setTimeout(attemptJoin, retryDelay);
                    } else {
                        retryStatus.className = 'retry-status error';
                        retryStatus.textContent = 'Network error. Please check your connection.';
                        setTimeout(() => window.location.reload(), 2000);
                    }
                }
            }
            
            await attemptJoin();
        });
    </script>
</body>
</html>