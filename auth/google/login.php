<?php
require_once __DIR__.'/../../config/google_oauth.php';
require_once __DIR__.'/../../includes/security.php';

session_start();

// Generate CSRF token
$csrfToken = bin2hex(random_bytes(32));
$_SESSION['oauth_csrf_token'] = $csrfToken;

$googleConfig = require __DIR__.'/../../config/google_oauth.php';

$params = [
    'response_type' => 'code',
    'client_id' => $googleConfig['client_id'],
    'redirect_uri' => 'https://learnmate.up.railway.app/auth/google/callback',
    'scope' => implode(' ', $googleConfig['scopes']),
    'state' => generateStateToken(),
    'nonce' => bin2hex(random_bytes(16)),
    'prompt' => 'select_account',
    'access_type' => 'online'
];

// Store all security tokens
$_SESSION['oauth_state'] = $params['state'];
$_SESSION['oauth_nonce'] = $params['nonce'];

header('Location: ' . $googleConfig['authorization_endpoint'] . '?' . http_build_query($params));
exit;

function generateStateToken() {
    return hash_hmac('sha256', bin2hex(random_bytes(32)), 'your_secret_key_here');
}
?>