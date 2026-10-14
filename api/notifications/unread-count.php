<?php
require __DIR__ . '/../../auth_lib.php';
ccw_load_environment();

header('Content-Type: application/json');

$user = ccw_verify_token_and_get_user(ccw_bearer_token());
$pdo = ccw_db();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :id AND read_at IS NULL');
$stmt->execute(['id' => (int) $user['id']]);

echo json_encode(['success' => true, 'unread' => (int) $stmt->fetchColumn()]);
