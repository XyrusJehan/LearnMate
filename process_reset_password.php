<?php
$token = $_POST["token"];

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

$errors = [];

if (strlen($_POST["password"]) < 8) {
    $errors[] = "Password must be at least 8 characters long.";
}

if (!preg_match("/[a-z]/i", $_POST["password"])) {
    $errors[] = "Password must contain at least one letter.";
}

if (!preg_match("/[0-9]/", $_POST["password"])) {
    $errors[] = "Password must contain at least one number.";
}

if ($_POST["password"] !== $_POST["password_confirmation"]) {
    $errors[] = "Passwords must match.";
}

if (empty($errors)) {
    $password_hash = password_hash($_POST["password"], PASSWORD_DEFAULT);
    
    $sql = "UPDATE users
            SET password = ?,
                reset_token_hash = NULL,
                reset_token_expire_at = NULL
            WHERE id = ?";
    
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("si", $password_hash, $user["id"]);
    $stmt->execute();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset | LearnMate</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="reset-styles.css">
</head>
<body>
    <div class="reset-container">
        <div class="reset-card">
            <div class="reset-header">
                <h1><?php echo empty($errors) ? 'Password Reset Successful' : 'Reset Password'; ?></h1>
                <p><?php echo empty($errors) ? 'Your password has been updated successfully.' : 'Please fix the following errors:'; ?></p>
            </div>
            
            <?php if (!empty($errors)): ?>
                <form class="reset-form" action="process_reset_password.php" method="post">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    
                    <?php foreach ($errors as $error): ?>
                        <div class="error-message"><?php echo $error; ?></div>
                    <?php endforeach; ?>
                    
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
                    
                    <button type="submit" class="btn btn-reset">Try Again</button>
                </form>
            <?php else: ?>
                <div class="success-message">
                    You can now login with your new password.
                </div>
                
                <a href="index.php" class="btn btn-reset">Go to Login</a>
            <?php endif; ?>
            
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