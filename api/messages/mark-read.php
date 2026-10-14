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
$conversationId = (int) ($body['conversation_id'] ?? 0);

if ($conversationId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Conversation ID is required.']);
    exit;
}

$pdo = ccw_db();
$cStmt = $pdo->prepare('SELECT buyer_id, seller_id FROM conversations WHERE id = :id LIMIT 1');
$cStmt->execute(['id' => $conversationId]);
$conversation = $cStmt->fetch();

if (!$conversation) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Conversation not found.']);
    exit;
}
if ((int) $conversation['buyer_id'] !== $uid && (int) $conversation['seller_id'] !== $uid) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'You are not part of this conversation.']);
    exit;
}

$now = (new DateTime())->format('Y-m-d H:i:s');
$upd = $pdo->prepare('UPDATE messages SET read_at = :t WHERE conversation_id = :cid AND sender_id != :uid AND read_at IS NULL');
$upd->execute(['t' => $now, 'cid' => $conversationId, 'uid' => $uid]);

echo json_encode(['success' => true, 'marked' => $upd->rowCount()]);
