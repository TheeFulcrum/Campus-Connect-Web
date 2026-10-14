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
$noteBody = trim((string) ($body['body'] ?? ''));

if ($listingId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Listing ID is required.']);
    exit;
}
if ($noteBody === '' || strlen($noteBody) > 1000) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Note must be 1-1000 characters.']);
    exit;
}

$pdo = ccw_db();
$lStmt = $pdo->prepare('SELECT id FROM listings WHERE id = :id LIMIT 1');
$lStmt->execute(['id' => $listingId]);
if (!$lStmt->fetch()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Listing not found.']);
    exit;
}

$now = time();
$nowText = (new DateTime())->format('Y-m-d H:i:s');
$expiresAt = $now + 86400; // notes expire 24h after creation

$pdo->prepare('INSERT INTO notes (user_id, listing_id, body, created_at, expires_at) VALUES (:u, :l, :b, :c, :e)')
    ->execute(['u' => (int) $user['id'], 'l' => $listingId, 'b' => $noteBody, 'c' => $nowText, 'e' => $expiresAt]);

http_response_code(201);
echo json_encode([
    'success' => true,
    'note' => [
        'id' => (int) $pdo->lastInsertId(),
        'listing_id' => $listingId,
        'body' => $noteBody,
        'created_at' => $nowText,
        'expires_at' => $expiresAt,
    ],
]);
