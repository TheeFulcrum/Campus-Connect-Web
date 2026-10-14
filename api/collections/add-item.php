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
$collectionId = (int) ($body['collection_id'] ?? 0);
$listingId = (int) ($body['listing_id'] ?? 0);

if ($collectionId <= 0 || $listingId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Collection ID and listing ID are required.']);
    exit;
}

$pdo = ccw_db();
$cStmt = $pdo->prepare('SELECT user_id FROM collections WHERE id = :id LIMIT 1');
$cStmt->execute(['id' => $collectionId]);
$collection = $cStmt->fetch();
if (!$collection) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Collection not found.']);
    exit;
}
if ((int) $collection['user_id'] !== (int) $user['id']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'You can only modify your own collections.']);
    exit;
}

$lStmt = $pdo->prepare('SELECT id FROM listings WHERE id = :id LIMIT 1');
$lStmt->execute(['id' => $listingId]);
if (!$lStmt->fetch()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Listing not found.']);
    exit;
}

$pdo->prepare('INSERT OR IGNORE INTO collection_items (collection_id, listing_id, created_at) VALUES (:c, :l, :t)')
    ->execute(['c' => $collectionId, 'l' => $listingId, 't' => (new DateTime())->format('Y-m-d H:i:s')]);

echo json_encode(['success' => true, 'collection_id' => $collectionId, 'listing_id' => $listingId]);
