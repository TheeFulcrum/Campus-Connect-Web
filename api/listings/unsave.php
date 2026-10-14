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
$pdo->prepare('DELETE FROM saves WHERE user_id = :u AND listing_id = :l')
    ->execute(['u' => (int) $user['id'], 'l' => $listingId]);

$count = $pdo->prepare('SELECT COUNT(*) FROM saves WHERE listing_id = :id');
$count->execute(['id' => $listingId]);

echo json_encode(['success' => true, 'saved' => false, 'saves' => (int) $count->fetchColumn()]);
