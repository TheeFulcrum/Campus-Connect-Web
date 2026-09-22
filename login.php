<?php
// login.php — validates credentials against users.json and starts a server session.
require __DIR__ . '/auth_lib.php';

header('Content-Type: application/json');
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
]);
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$identifier = isset($_POST['identifier'])
    ? trim($_POST['identifier'])
    : (isset($_POST['email']) ? trim($_POST['email']) : '');
$password = isset($_POST['password']) ? $_POST['password'] : '';

if ($identifier === '' || $password === '') {
    echo json_encode(['success' => false, 'error' => 'Username or email and password are required.']);
    exit;
}

if (str_contains($identifier, '@') && !ccw_valid_email($identifier)) {
    echo json_encode(['success' => false, 'error' => 'Use your 10-digit ID@edenuniversity.education email.']);
    exit;
}

$result = ccw_authenticate_user($identifier, $password);
if (isset($result['error'])) {
    echo json_encode(['success' => false, 'error' => $result['error']]);
    exit;
}

session_regenerate_id(true);
$_SESSION['user'] = $result['user'];
echo json_encode(['success' => true] + $result['user']);
