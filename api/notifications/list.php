<?php
require __DIR__ . '/../../auth_lib.php';
ccw_load_environment();

header('Content-Type: application/json');

$user = ccw_verify_token_and_get_user(ccw_bearer_token());
[$page, $limit, $offset] = ccw_pagination();
$pdo = ccw_db();
$uid = (int) $user['id'];

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :id');
$countStmt->execute(['id' => $uid]);
$total = (int) $countStmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT n.id, n.type, n.entity_id, n.body, n.read_at, n.created_at,
           u.username AS actor_username, u.display_name AS actor_display_name
    FROM notifications n
    LEFT JOIN users u ON u.id = n.actor_id
    WHERE n.user_id = :id
    ORDER BY n.id DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':id', $uid, PDO::PARAM_INT);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

echo json_encode([
    'success' => true,
    'notifications' => $stmt->fetchAll(),
    'pagination' => ['page' => $page, 'per_page' => $limit, 'total' => $total],
]);
