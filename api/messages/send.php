<?php
require __DIR__ . '/../../auth_lib.php';
ccw_load_environment();
require_once __DIR__ . '/../_rate_limit.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$user = ccw_verify_token_and_get_user(ccw_bearer_token());
$uid = (int) $user['id'];

ccw_rate_limit_or_fail('message', 'user:' . $uid, 30, 60);

$conversationId = (int) ($body['conversation_id'] ?? 0);
$listingId = (int) ($body['listing_id'] ?? 0);
$recipientId = (int) ($body['recipient_id'] ?? 0);
$recipientUsername = trim((string) ($body['recipient'] ?? ''));
$text = trim((string) ($body['body'] ?? ''));

if ($text === '' || strlen($text) > 2000) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Message must be 1-2000 characters.']);
    exit;
}

$pdo = ccw_db();
$now = (new DateTime())->format('Y-m-d H:i:s');
$conversation = null;

if ($conversationId > 0) {
    // Replying to an existing conversation: must be a participant.
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
} else {
    // New conversation: requires a listing or an explicit recipient.
    if ($listingId > 0) {
        $lStmt = $pdo->prepare("SELECT id, user_id, title FROM listings WHERE id = :id AND status = 'active' AND archived = 0");
        $lStmt->execute(['id' => $listingId]);
        $listing = $lStmt->fetch();
        if (!$listing) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Listing not found.']);
            exit;
        }
        $recipientId = (int) $listing['user_id'];
    }
    if ($recipientId <= 0 && $recipientUsername !== '') {
        $uStmt = $pdo->prepare('SELECT id FROM users WHERE LOWER(username) = LOWER(:u) LIMIT 1');
        $uStmt->execute(['u' => $recipientUsername]);
        $recipientId = (int) ($uStmt->fetchColumn() ?: 0);
    }
    if ($recipientId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'A listing or recipient is required to start a conversation.']);
        exit;
    }
    if ($recipientId === $uid) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'You cannot message yourself.']);
        exit;
    }
    $rStmt = $pdo->prepare('SELECT id FROM users WHERE id = :id');
    $rStmt->execute(['id' => $recipientId]);
    if (!$rStmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Recipient not found.']);
        exit;
    }

    // Reuse an existing conversation for the same listing + pair if one exists.
    $find = $pdo->prepare('SELECT * FROM conversations WHERE listing_id IS :lid AND buyer_id = :b AND seller_id = :s LIMIT 1');
    $find->bindValue(':lid', $listingId > 0 ? $listingId : null, $listingId > 0 ? PDO::PARAM_INT : PDO::PARAM_NULL);
    $find->bindValue(':b', $uid, PDO::PARAM_INT);
    $find->bindValue(':s', $recipientId, PDO::PARAM_INT);
    $find->execute();
    $conversation = $find->fetch();

    if (!$conversation) {
        $pdo->prepare('INSERT INTO conversations (listing_id, buyer_id, seller_id, created_at, updated_at) VALUES (:lid, :b, :s, :c, :u)')
            ->execute([
                'lid' => $listingId > 0 ? $listingId : null,
                'b' => $uid,
                's' => $recipientId,
                'c' => $now,
                'u' => $now,
            ]);
        $cStmt = $pdo->prepare('SELECT * FROM conversations WHERE id = :id');
        $cStmt->execute(['id' => (int) $pdo->lastInsertId()]);
        $conversation = $cStmt->fetch();
    }
}

$pdo->prepare('INSERT INTO messages (conversation_id, sender_id, body, created_at, read_at) VALUES (:c, :s, :b, :t, NULL)')
    ->execute(['c' => (int) $conversation['id'], 's' => $uid, 'b' => $text, 't' => $now]);
$messageId = (int) $pdo->lastInsertId();

$pdo->prepare('UPDATE conversations SET updated_at = :t WHERE id = :id')
    ->execute(['t' => $now, 'id' => (int) $conversation['id']]);

$otherId = ((int) $conversation['buyer_id'] === $uid) ? (int) $conversation['seller_id'] : (int) $conversation['buyer_id'];
$actorName = trim((string) ($user['display_name'] ?? '')) !== '' ? $user['display_name'] : $user['username'];
ccw_notify($otherId, 'message', $uid, (int) $conversation['id'], $actorName . ' sent you a message.');

echo json_encode([
    'success' => true,
    'conversation_id' => (int) $conversation['id'],
    'message' => [
        'id' => $messageId,
        'sender_id' => $uid,
        'body' => $text,
        'created_at' => $now,
        'read_at' => null,
    ],
]);
