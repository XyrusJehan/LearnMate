<?php
$token = $_GET["token"];

$token_hash = hash('sha256', $token);

$mysqli = require __DIR__ . "/database.php";

$sql = "SELECT * FROM users
        WHERE reset_token_hash = ?";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param("s", $token_hash);
$stmt->execute();

$result = $stmt->get_result();

$user = $result->fetch_assoc();

if ($user === null) {
    die("Invalid or expired token.");
}

if (strtotime($user["reset_token_expire_at"]) <= time()) {
    die("Invalid or expired token.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | LearnMate</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="reset-styles.css">
</head>
<body>
    <div class="reset-container">
        <div class="reset-card">
            <div class="reset-header">
                <h1>Reset Your Password</h1>
                <p>Create a new password for your account</p>
            </div>
            
            <form class="reset-form" action="process_reset_password.php" method="post">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                
                <div class="form-group">
                    <label for="password">New Password</label>
                    <div class="input-with-icon">
                        <svg class="password-icon" viewBox="0 0 24 24">
                            <path d="M12 15a2 2 0 1 0 0-4 2 2 0 0 0 0 4z"/>
                            <path d="M21 12c-1.4 4-4.9 7-9 7s-7.6-3-9-7c1.4-4 4.9-7 9-7s7.6 3 9 7"/>
                        </svg>
                        <input type="password" id="password" name="password" placeholder="Enter new password" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password_confirmation">Confirm Password</label>
                    <div class="input-with-icon">
                        <svg class="password-icon" viewBox="0 0 24 24">
                            <path d="M12 15a2 2 0 1 0 0-4 2 2 0 0 0 0 4z"/>
                            <path d="M21 12c-1.4 4-4.9 7-9 7s-7.6-3-9-7c1.4-4 4.9-7 9-7s7.6 3 9 7"/>
                        </svg>
                        <input type="password" id="password_confirmation" name="password_confirmation" placeholder="Confirm new password" required>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-reset">Reset Password</button>
            </form>
            
            <div class="back-to-login">
                <a href="index.php" class="back-link">
                    <svg class="back-arrow" viewBox="0 0 24 24">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                    Back to Sign In
                </a>
            </div>
        </div>
    </div>
</body>
</html>