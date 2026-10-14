<?php
require __DIR__ . '/../../auth_lib.php';
ccw_load_environment();

header('Content-Type: application/json');

$user = ccw_verify_token_and_get_user(ccw_bearer_token());
$uid = (int) $user['id'];
$pdo = ccw_db();

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM messages m
    JOIN conversations c ON c.id = m.conversation_id
    WHERE (c.buyer_id = :uid OR c.seller_id = :uid)
      AND m.sender_id != :uid
      AND m.read_at IS NULL
");
$stmt->execute(['uid' => $uid]);

echo json_encode(['success' => true, 'unread' => (int) $stmt->fetchColumn()]);
