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
$tStmt = $pdo->prepare('SELECT id, username, display_name FROM users WHERE LOWER(username) = LOWER(:u) LIMIT 1');
$tStmt->execute(['u' => $username]);
$target = $tStmt->fetch();

if (!$target) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'User not found.']);
    exit;
}
if ((int) $target['id'] === (int) $user['id']) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'You cannot follow yourself.']);
    exit;
}

$now = (new DateTime())->format('Y-m-d H:i:s');
$pdo->prepare('INSERT OR IGNORE INTO follows (follower_id, followee_id, created_at) VALUES (:a, :b, :c)')
    ->execute(['a' => (int) $user['id'], 'b' => (int) $target['id'], 'c' => $now]);

$actorName = trim((string) ($user['display_name'] ?? '')) !== '' ? $user['display_name'] : $user['username'];
ccw_notify((int) $target['id'], 'follow', (int) $user['id'], (int) $user['id'], $actorName . ' started following you.');

$count = $pdo->prepare('SELECT COUNT(*) FROM follows WHERE followee_id = :id');
$count->execute(['id' => (int) $target['id']]);

echo json_encode([
    'success' => true,
    'following' => true,
    'followers' => (int) $count->fetchColumn(),
]);
