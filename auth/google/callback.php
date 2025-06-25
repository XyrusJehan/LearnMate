<?php
require_once __DIR__.'/../../config/google_oauth.php';
require_once __DIR__.'/../../db.php';

session_start();

// Check for errors from Google
if (isset($_GET['error'])) {
    $_SESSION['oauth_error'] = $_GET['error_description'] ?? 'Google authentication failed';
    header('Location: ../../index.php?error=google_login_failed');
    exit;
}

// Verify we have an authorization code
if (!isset($_GET['code'])) {
    $_SESSION['oauth_error'] = 'No authorization code received from Google';
    header('Location: ../../index.php?error=google_login_failed');
    exit;
}

// Process the authorization code
try {
    $googleConfig = require __DIR__.'/../../config/google_oauth.php';
    
    // Verify state parameter for CSRF protection
    if (!isset($_GET['state']) || !isset($_SESSION['oauth_state']) || $_GET['state'] !== $_SESSION['oauth_state']) {
        throw new Exception('Invalid state parameter');
    }
    unset($_SESSION['oauth_state']);

    // Exchange authorization code for access token
    $tokenResponse = file_get_contents($googleConfig['token_endpoint'], false, stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => 'Content-type: application/x-www-form-urlencoded',
            'content' => http_build_query([
                'code' => $_GET['code'],
                'client_id' => $googleConfig['client_id'],
                'client_secret' => $googleConfig['client_secret'],
                'redirect_uri' => $googleConfig['redirect_uri'],
                'grant_type' => 'authorization_code'
            ])
        ]
    ]));
    
    if ($tokenResponse === false) {
        throw new Exception('Failed to get token from Google');
    }
    
    $tokenData = json_decode($tokenResponse, true);
    
    if (isset($tokenData['error'])) {
        throw new Exception($tokenData['error_description'] ?? 'Google OAuth error');
    }
    
    // Get user info
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $googleConfig['userinfo_endpoint']);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $tokenData['access_token']
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $userInfoResponse = curl_exec($ch);
    
    if (curl_errno($ch)) {
        throw new Exception('Failed to fetch user info: ' . curl_error($ch));
    }
    curl_close($ch);
    
    $userInfo = json_decode($userInfoResponse, true);
    
    if (isset($userInfo['error'])) {
        throw new Exception($userInfo['error_description'] ?? 'Failed to fetch user info');
    }
    
    if (empty($userInfo['email'])) {
        throw new Exception('Google account email not found');
    }
    
    // Database operations (same as your existing code)
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$userInfo['email']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, password, role, avatar) VALUES (?, ?, ?, ?, 'student', ?)");
        $stmt->execute([
            $userInfo['given_name'] ?? 'Google',
            $userInfo['family_name'] ?? 'User',
            $userInfo['email'],
            '',
            $userInfo['picture'] ?? null
        ]);
        
        $userId = $pdo->lastInsertId();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        $_SESSION['require_role_selection'] = true;
    }
    
    // Login user
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['first_name'] = $user['first_name'];
    $_SESSION['last_name'] = $user['last_name'];
    $_SESSION['auth_provider'] = 'google';
    
    // Redirect
    if (isset($_SESSION['require_role_selection'])) {
        header('Location: ../../password_setup.php');
    } else {
        switch ($user['role']) {
            case 'admin': header('Location: ../../admin_dashboard.php'); break;
            case 'teacher': header('Location: ../../teacher_dashboard.php'); break;
            case 'student': header('Location: ../../student_dashboard.php'); break;
            default: header('Location: ../../index.php');
        }
    }
    exit();
    
} catch (Exception $e) {
    error_log('Google OAuth error: ' . $e->getMessage());
    $_SESSION['oauth_error'] = $e->getMessage();
    header('Location: ../../index.php?error=google_login_failed');
    exit();
}
?>