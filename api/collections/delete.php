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

if ($collectionId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Collection ID is required.']);
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
    echo json_encode(['success' => false, 'error' => 'You can only delete your own collections.']);
    exit;
}

$pdo->prepare('DELETE FROM collection_items WHERE collection_id = :c')->execute(['c' => $collectionId]);
$pdo->prepare('DELETE FROM collections WHERE id = :c')->execute(['c' => $collectionId]);

echo json_encode(['success' => true]);
