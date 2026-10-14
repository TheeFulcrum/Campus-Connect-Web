<?php
require __DIR__ . '/../../auth_lib.php';
ccw_load_environment();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$user = ccw_verify_token_and_get_user(ccw_bearer_token());
$pdo = ccw_db();

$now = (new DateTime())->format('Y-m-d H:i:s');
$upd = $pdo->prepare('UPDATE notifications SET read_at = :t WHERE user_id = :id AND read_at IS NULL');
$upd->execute(['t' => $now, 'id' => (int) $user['id']]);

echo json_encode(['success' => true, 'marked' => $upd->rowCount()]);
