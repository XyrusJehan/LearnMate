<?php
require_once __DIR__.'/../../config/google_oauth.php';

session_start();

$googleConfig = require __DIR__.'/../../config/google_oauth.php';

$params = [
    'response_type' => 'code',
    'client_id' => $googleConfig['client_id'],
    'redirect_uri' => 'https://learnmate.up.railway.app/auth/google/callback.php',
    'scope' => implode(' ', $googleConfig['scopes']),
    'access_type' => 'online',
    'prompt' => 'select_account',
    'state' => bin2hex(random_bytes(16))
];

$_SESSION['oauth_state'] = $params['state'];
header('Location: ' . $googleConfig['authorization_endpoint'] . '?' . http_build_query($params));
exit;
?>