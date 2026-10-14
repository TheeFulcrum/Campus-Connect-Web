<?php
require __DIR__ . '/../../auth_lib.php';
ccw_load_environment();
require_once __DIR__ . '/../_rate_limit.php';
require_once __DIR__ . '/../_uploads.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$user = ccw_verify_token_and_get_user(ccw_bearer_token());
ccw_rate_limit_or_fail('upload', 'user:' . (int) $user['id'], 20, 3600);

if (empty($_FILES['avatar'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No avatar file was uploaded.']);
    exit;
}

$stored = ccw_store_image_upload($_FILES['avatar'], 'avatars', 5 * 1024 * 1024, 512);
if (isset($stored['error'])) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => $stored['error']]);
    exit;
}

$pdo = ccw_db();
$pdo->prepare('UPDATE users SET avatar_path = :p WHERE id = :id')
    ->execute(['p' => $stored['path'], 'id' => (int) $user['id']]);

// Remove the previous uploaded avatar file (not the legacy base64 field).
$old = trim((string) ($user['avatar_path'] ?? ''));
if ($old !== '' && $old !== $stored['path']) {
    ccw_delete_upload($old);
}

echo json_encode(['success' => true, 'avatar_url' => ccw_upload_url($stored['path'])]);
