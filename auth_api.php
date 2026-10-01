<?php
require __DIR__ . '/auth_lib.php';
ccw_load_environment();

header('Content-Type: application/json');

function respond(int $status, array $body): void {
    http_response_code($status);
    echo json_encode($body);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(405, ['success' => false, 'error' => 'Method not allowed.']);

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) $body = $_POST;
$action = $body['action'] ?? '';

if ($action === 'request_otp') {
    $result = ccw_request_otp($body['email'] ?? '');
    if (isset($result['error'])) respond(422, ['success' => false, 'error' => $result['error']]);
    respond(200, ['success' => true]);
}

if ($action === 'verify_otp') {
    $result = ccw_verify_otp($body['email'] ?? '', $body['code'] ?? '');
    if (isset($result['error'])) respond(401, ['success' => false, 'error' => $result['error']]);
    $token = ccw_create_token($result['user']['email']);
    if ($token === null) respond(500, ['success' => false, 'error' => 'Unable to create a session. Please try again.']);
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
    ]);
    session_start();
    session_regenerate_id(true);
    $_SESSION['user'] = $result['user'];
    respond(200, ['success' => true, 'user' => $result['user'], 'token' => $token]);
}

if ($action === 'register') {
    $result = ccw_register_user($body['username'] ?? '', $body['email'] ?? '', $body['campus'] ?? '', $body['password'] ?? '');
    if (isset($result['error'])) respond(422, ['success' => false, 'error' => $result['error']]);
    respond(201, ['success' => true, 'verification_required' => true, 'user' => $result['user']]);
}

if ($action === 'login') {
    $result = ccw_authenticate_user($body['identifier'] ?? '', $body['password'] ?? '');
    if (isset($result['error'])) respond(401, ['success' => false, 'error' => $result['error']]);
    $token = ccw_create_token($result['user']['email']);
    if ($token === null) respond(500, ['success' => false, 'error' => 'Unable to create a session. Please try again.']);
    respond(200, ['success' => true, 'user' => $result['user'], 'token' => $token]);
}

$token = ccw_bearer_token();
if ($action === 'session') {
    $user = ccw_user_for_token($token);
    if (!$user) respond(401, ['success' => false, 'error' => 'Session expired.']);
    respond(200, ['success' => true, 'user' => $user]);
}

if ($action === 'logout') {
    ccw_revoke_token($token);
    respond(200, ['success' => true]);
}

respond(400, ['success' => false, 'error' => 'Unknown action.']);