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
$username = trim((string) ($body['username'] ?? ''));

if ($username === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Username is required.']);
    exit;
}

$pdo = ccw_db();
$tStmt = $pdo->prepare('SELECT id FROM users WHERE LOWER(username) = LOWER(:u) LIMIT 1');
$tStmt->execute(['u' => $username]);
$target = $tStmt->fetch();

if (!$target) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'User not found.']);
    exit;
}

$pdo->prepare('DELETE FROM follows WHERE follower_id = :a AND followee_id = :b')
    ->execute(['a' => (int) $user['id'], 'b' => (int) $target['id']]);

$count = $pdo->prepare('SELECT COUNT(*) FROM follows WHERE followee_id = :id');
$count->execute(['id' => (int) $target['id']]);

echo json_encode([
    'success' => true,
    'following' => false,
    'followers' => (int) $count->fetchColumn(),
]);
