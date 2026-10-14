<?php
require __DIR__ . '/../../auth_lib.php';
ccw_load_environment();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$user = ccw_verify_token_and_get_user(ccw_bearer_token());
$uid = (int) $user['id'];
$action = $body['action'] ?? 'save';

$pdo = ccw_db();
$now = time();
$nowText = (new DateTime())->format('Y-m-d H:i:s');

if ($action === 'delete') {
    $pdo->prepare('DELETE FROM notes WHERE user_id = :u AND listing_id IS NULL')->execute(['u' => $uid]);
    echo json_encode(['success' => true]);
    exit;
}

$noteBody = trim((string) ($body['body'] ?? ''));
if ($noteBody === '' || strlen($noteBody) > 60) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Note must be 1-60 characters.']);
    exit;
}

// One profile note per user; replace any existing unexpired one.
$pdo->prepare('DELETE FROM notes WHERE user_id = :u AND listing_id IS NULL')->execute(['u' => $uid]);
$pdo->prepare('INSERT INTO notes (user_id, listing_id, body, created_at, expires_at) VALUES (:u, NULL, :b, :c, :e)')
    ->execute(['u' => $uid, 'b' => $noteBody, 'c' => $nowText, 'e' => $now + 86400]);

http_response_code(201);
echo json_encode([
    'success' => true,
    'note' => [
        'id' => (int) $pdo->lastInsertId(),
        'body' => $noteBody,
        'created_at' => $nowText,
        'expires_at' => $now + 86400,
    ],
]);
