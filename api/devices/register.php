<?php
// Registers (or re-assigns) a device's push token for the signed-in user.
// POST /api/devices/register.php  Body: {"token": "<FCM token>", "platform": "android"}
require __DIR__ . '/../../auth_lib.php';
ccw_load_environment();
require_once __DIR__ . '/../_rate_limit.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$user = ccw_verify_token_and_get_user(ccw_bearer_token());
ccw_rate_limit_or_fail('device_register', 'user:' . (int) $user['id'], 30, 3600);

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$token = trim((string) ($body['token'] ?? ''));
$platform = trim((string) ($body['platform'] ?? 'android'));

if ($token === '' || strlen($token) > 4096 || !preg_match('/^[A-Za-z0-9_:.\-]+$/', $token)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'A valid device token is required.']);
    exit;
}
if ($platform !== 'android') $platform = 'android';

$now = (new DateTime())->format('Y-m-d H:i:s');
// A token belongs to one user at a time: signing in on a shared phone re-assigns it.
ccw_db()->prepare('INSERT INTO device_tokens (token, user_id, platform, created_at, updated_at) VALUES (:t, :u, :p, :c, :c)
    ON CONFLICT(token) DO UPDATE SET user_id = excluded.user_id, platform = excluded.platform, updated_at = excluded.updated_at')
    ->execute(['t' => $token, 'u' => (int) $user['id'], 'p' => $platform, 'c' => $now]);

echo json_encode(['success' => true]);
