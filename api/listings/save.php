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
$lStmt = $pdo->prepare("SELECT id, user_id, title FROM listings WHERE id = :id AND status = 'active' AND archived = 0");
$lStmt->execute(['id' => $listingId]);
$listing = $lStmt->fetch();

if (!$listing) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Listing not found.']);
    exit;
}

$now = (new DateTime())->format('Y-m-d H:i:s');
$pdo->prepare('INSERT OR IGNORE INTO saves (user_id, listing_id, created_at) VALUES (:u, :l, :c)')
    ->execute(['u' => (int) $user['id'], 'l' => $listingId, 'c' => $now]);

if ((int) $listing['user_id'] !== (int) $user['id']) {
    $actorName = trim((string) ($user['display_name'] ?? '')) !== '' ? $user['display_name'] : $user['username'];
    ccw_notify((int) $listing['user_id'], 'save', (int) $user['id'], $listingId, $actorName . ' saved your listing "' . $listing['title'] . '".');
}

$count = $pdo->prepare('SELECT COUNT(*) FROM saves WHERE listing_id = :id');
$count->execute(['id' => $listingId]);

echo json_encode(['success' => true, 'saved' => true, 'saves' => (int) $count->fetchColumn()]);
