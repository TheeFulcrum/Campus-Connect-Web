<?php
// Removes a device's push token (called on logout).
// POST /api/devices/unregister.php  Body: {"token": "<FCM token>"}
require __DIR__ . '/../../auth_lib.php';
ccw_load_environment();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$user = ccw_verify_token_and_get_user(ccw_bearer_token());
$body = json_decode(file_get_contents('php://input'), true) ?? [];
$token = trim((string) ($body['token'] ?? ''));

if ($token !== '') {
    ccw_db()->prepare('DELETE FROM device_tokens WHERE token = :t AND user_id = :u')
        ->execute(['t' => $token, 'u' => (int) $user['id']]);
}

echo json_encode(['success' => true]);
