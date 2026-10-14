<?php
require __DIR__ . '/../../auth_lib.php';
ccw_load_environment();

header('Content-Type: application/json');

$user = ccw_verify_token_and_get_user(ccw_bearer_token());
[$page, $limit, $offset] = ccw_pagination();
$pdo = ccw_db();
$uid = (int) $user['id'];

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM conversations WHERE buyer_id = :id OR seller_id = :id');
$countStmt->execute(['id' => $uid]);
$total = (int) $countStmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT c.id, c.listing_id, c.buyer_id, c.seller_id, c.updated_at,
           l.title AS listing_title,
           b.username AS buyer_username, s.username AS seller_username,
           s.display_name AS seller_display_name, b.display_name AS buyer_display_name,
           s.avatar_path AS seller_avatar_path, b.avatar_path AS buyer_avatar_path,
           s.avatar AS seller_avatar, b.avatar AS buyer_avatar,
           (SELECT body FROM messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_message,
           (SELECT created_at FROM messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_message_at,
           (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.sender_id != :uid AND m.read_at IS NULL) AS unread
    FROM conversations c
    LEFT JOIN listings l ON l.id = c.listing_id
    JOIN users b ON b.id = c.buyer_id
    JOIN users s ON s.id = c.seller_id
    WHERE c.buyer_id = :uid OR c.seller_id = :uid
    ORDER BY c.updated_at DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$conversations = $stmt->fetchAll();

foreach ($conversations as &$c) {
    $c['unread'] = (int) $c['unread'];
    $isBuyer = (int) $c['buyer_id'] === $uid;
    $c['other_id'] = $isBuyer ? (int) $c['seller_id'] : (int) $c['buyer_id'];
    $c['other_username'] = $isBuyer ? $c['seller_username'] : $c['buyer_username'];
    $c['other_display_name'] = $isBuyer ? $c['seller_display_name'] : $c['buyer_display_name'];
    $c['other_avatar_url'] = ccw_avatar_url([
        'avatar_path' => $isBuyer ? $c['seller_avatar_path'] : $c['buyer_avatar_path'],
        'avatar' => $isBuyer ? $c['seller_avatar'] : $c['buyer_avatar'],
    ]);
    unset($c['seller_avatar_path'], $c['buyer_avatar_path'], $c['seller_avatar'], $c['buyer_avatar']);
}
unset($c);

echo json_encode([
    'success' => true,
    'conversations' => $conversations,
    'pagination' => ['page' => $page, 'per_page' => $limit, 'total' => $total],
]);
