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

$result = ccw_register_user($username, $email, $campus, $password);
if (isset($result['error'])) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => $result['error']]);
    exit;
}

echo json_encode(['success' => true]);
