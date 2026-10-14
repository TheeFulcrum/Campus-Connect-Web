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
$name = trim((string) ($body['name'] ?? ''));

if ($name === '' || strlen($name) > 80) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Collection name must be 1-80 characters.']);
    exit;
}

$pdo = ccw_db();
$now = (new DateTime())->format('Y-m-d H:i:s');
$pdo->prepare('INSERT INTO collections (user_id, name, created_at) VALUES (:u, :n, :c)')
    ->execute(['u' => (int) $user['id'], 'n' => $name, 'c' => $now]);

http_response_code(201);
echo json_encode([
    'success' => true,
    'collection' => [
        'id' => (int) $pdo->lastInsertId(),
        'name' => $name,
        'created_at' => $now,
    ],
]);
