<?php
require __DIR__ . '/../../auth_lib.php';
ccw_load_environment();

header('Content-Type: application/json');

// Collections are private to the owner.
$user = ccw_verify_token_and_get_user(ccw_bearer_token());
$pdo = ccw_db();
$uid = (int) $user['id'];

$stmt = $pdo->prepare('SELECT id, name, created_at FROM collections WHERE user_id = :u ORDER BY created_at DESC');
$stmt->execute(['u' => $uid]);
$collections = $stmt->fetchAll();

foreach ($collections as &$collection) {
    $items = $pdo->prepare('
        SELECT ci.listing_id, l.title, l.price, l.status, l.category, l.campus, l.quantity, l.priority, l.event_at,
               (SELECT image_url FROM listing_images li WHERE li.listing_id = l.id ORDER BY li.display_order LIMIT 1) AS cover_image
        FROM collection_items ci
        JOIN listings l ON l.id = ci.listing_id
        WHERE ci.collection_id = :c
        ORDER BY ci.created_at DESC
    ');
    $items->execute(['c' => $collection['id']]);
    $collection['items'] = $items->fetchAll();
    $collection['item_count'] = count($collection['items']);
}
unset($collection);

echo json_encode(['success' => true, 'collections' => $collections]);
