<?php
require __DIR__ . '/../../auth_lib.php';
ccw_load_environment();

header('Content-Type: application/json');

$user = ccw_verify_token_and_get_user(ccw_bearer_token());
$pdo = ccw_db();
$uid = (int) $user['id'];

$conversationId = (int) ($_GET['conversation_id'] ?? 0);
$afterId = (int) ($_GET['after_id'] ?? 0);
[$page, $limit, $offset] = ccw_pagination();

if ($conversationId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Conversation ID is required.']);
    exit;
}

// Only participants can read a thread.
$cStmt = $pdo->prepare('SELECT * FROM conversations WHERE id = :id LIMIT 1');
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

if ($afterId > 0) {
    // Polling mode: only messages newer than the client's latest.
    $stmt = $pdo->prepare("
        SELECT m.id, m.sender_id, m.body, m.created_at, m.read_at, u.username AS sender_username
        FROM messages m JOIN users u ON u.id = m.sender_id
        WHERE m.conversation_id = :cid AND m.id > :after
        ORDER BY m.id ASC
        LIMIT :limit
    ");
    $stmt->bindValue(':cid', $conversationId, PDO::PARAM_INT);
    $stmt->bindValue(':after', $afterId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $messages = $stmt->fetchAll();
    echo json_encode(['success' => true, 'messages' => $messages, 'mode' => 'delta']);
    exit;
}

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE conversation_id = :cid');
$countStmt->execute(['cid' => $conversationId]);
$total = (int) $countStmt->fetchColumn();

// Latest page first (most recent messages), ascending within the page.
$stmt = $pdo->prepare("
    SELECT m.id, m.sender_id, m.body, m.created_at, m.read_at, u.username AS sender_username
    FROM messages m JOIN users u ON u.id = m.sender_id
    WHERE m.conversation_id = :cid
    ORDER BY m.id DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':cid', $conversationId, PDO::PARAM_INT);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$messages = array_reverse($stmt->fetchAll());

echo json_encode([
    'success' => true,
    'messages' => $messages,
    'mode' => 'page',
    'pagination' => ['page' => $page, 'per_page' => $limit, 'total' => $total],
]);
