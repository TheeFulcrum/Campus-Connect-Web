<?php
require __DIR__ . '/auth_lib.php';

header('Content-Type: application/json');
session_start();

$user = empty($_SESSION['user']['email']) ? null : ccw_find_user_by_email($_SESSION['user']['email']);
if (!$user || empty($user['email_verified'])) {
    $_SESSION = [];
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated.']);
    exit;
}

echo json_encode(['success' => true, 'user' => ccw_public_user($user)]);