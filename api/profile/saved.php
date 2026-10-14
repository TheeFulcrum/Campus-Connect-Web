<?php
require __DIR__ . '/../../auth_lib.php';
ccw_load_environment();

header('Content-Type: application/json');

// Saved listings are private to the owner.
$user = ccw_verify_token_and_get_user(ccw_bearer_token());
[$page, $limit, $offset] = ccw_pagination();
$pdo = ccw_db();
$uid = (int) $user['id'];

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM saves WHERE user_id = :id');
$countStmt->execute(['id' => $uid]);
$total = (int) $countStmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT l.id, l.title, l.price, l.category, l.campus, l.status, l.created_at, s.created_at AS saved_at,
           u.username AS seller_username
    FROM saves s
    JOIN listings l ON l.id = s.listing_id
    JOIN users u ON u.id = l.user_id
    WHERE s.user_id = :id
    ORDER BY s.created_at DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':id', $uid, PDO::PARAM_INT);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$listings = $stmt->fetchAll();

foreach ($listings as &$listing) {
    $img = $pdo->prepare('SELECT image_url FROM listing_images WHERE listing_id = :id ORDER BY display_order LIMIT 1');
    $img->execute(['id' => $listing['id']]);
    $listing['cover_image'] = $img->fetchColumn() ?: null;
}
unset($listing);

echo json_encode([
    'success' => true,
    'listings' => $listings,
    'pagination' => ['page' => $page, 'per_page' => $limit, 'total' => $total],
]);
