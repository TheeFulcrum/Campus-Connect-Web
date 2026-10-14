<?php
require __DIR__ . '/../../auth_lib.php';
ccw_load_environment();

header('Content-Type: application/json');

// Notes are private to their author; expired notes are never returned.
$user = ccw_verify_token_and_get_user(ccw_bearer_token());
[$page, $limit, $offset] = ccw_pagination();
$pdo = ccw_db();
$uid = (int) $user['id'];
$now = time();

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM notes WHERE user_id = :u AND expires_at > :now');
$countStmt->execute(['u' => $uid, 'now' => $now]);
$total = (int) $countStmt->fetchColumn();

$stmt = $pdo->prepare('
    SELECT n.id, n.listing_id, n.body, n.created_at, n.expires_at, l.title AS listing_title
    FROM notes n
    JOIN listings l ON l.id = n.listing_id
    WHERE n.user_id = :u AND n.expires_at > :now
    ORDER BY n.created_at DESC
    LIMIT :limit OFFSET :offset
');
$stmt->bindValue(':u', $uid, PDO::PARAM_INT);
$stmt->bindValue(':now', $now, PDO::PARAM_INT);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$notes = $stmt->fetchAll();

echo json_encode([
    'success' => true,
    'notes' => $notes,
    'pagination' => ['page' => $page, 'per_page' => $limit, 'total' => $total],
]);
