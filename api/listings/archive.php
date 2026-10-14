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
$listingId = (int) ($body['listing_id'] ?? 0);

if ($listingId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Listing ID is required.']);
    exit;
}

$pdo = ccw_db();
$stmt = $pdo->prepare('SELECT user_id FROM listings WHERE id = :id');
$stmt->execute(['id' => $listingId]);
$listing = $stmt->fetch();

if (!$listing) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Listing not found.']);
    exit;
}
if ((int) $listing['user_id'] !== (int) $user['id']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'You can only archive your own listings.']);
    exit;
}

$now = (new DateTime())->format('Y-m-d H:i:s');
$pdo->prepare('UPDATE listings SET archived = 1, updated_at = :u WHERE id = :id')
    ->execute(['u' => $now, 'id' => $listingId]);

echo json_encode(['success' => true, 'archived' => true]);
