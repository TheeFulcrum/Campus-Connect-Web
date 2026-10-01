<?php
require __DIR__ . '/auth_lib.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$email = isset($_POST['email']) ? trim(strtolower($_POST['email'])) : '';
$campus = isset($_POST['campus']) ? trim($_POST['campus']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';
$realName = isset($_POST['real_name']) ? trim($_POST['real_name']) : '';
$bio = isset($_POST['bio']) ? trim($_POST['bio']) : '';
$avatar = isset($_POST['avatar']) ? trim($_POST['avatar']) : '';

$result = null;
try {
    $result = ccw_register_user($username, $email, $campus, $password, $realName, $bio, $avatar);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server configuration error. Please contact support.']);
    exit;
}

if (isset($result['error'])) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => $result['error']]);
    exit;
}

echo json_encode(['success' => true]);
