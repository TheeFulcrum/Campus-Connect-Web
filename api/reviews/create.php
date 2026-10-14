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
$uid = (int) $user['id'];

$listingId = (int) ($body['listing_id'] ?? 0);
$rating = (int) ($body['rating'] ?? 0);
$comment = trim((string) ($body['comment'] ?? ''));

if ($listingId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Listing ID is required.']);
    exit;
}
if ($rating < 1 || $rating > 5) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Rating must be between 1 and 5.']);
    exit;
}
if (strlen($comment) > 1000) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Comment must be 1000 characters or less.']);
    exit;
}

$pdo = ccw_db();
$lStmt = $pdo->prepare('SELECT id, user_id, status, title FROM listings WHERE id = :id');
$lStmt->execute(['id' => $listingId]);
$listing = $lStmt->fetch();

if (!$listing) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Listing not found.']);
    exit;
}

$revieweeId = (int) $listing['user_id'];
if ($revieweeId === $uid) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'You cannot review your own listing.']);
    exit;
}

// A review requires a completed transaction: the listing must be sold,
// and the reviewer must have a conversation with the seller about it.
if ($listing['status'] !== 'sold') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'You can only review after the listing has been sold.']);
    exit;
}
$convStmt = $pdo->prepare('SELECT 1 FROM conversations WHERE listing_id = :lid AND buyer_id = :uid AND seller_id = :sid LIMIT 1');
$convStmt->execute(['lid' => $listingId, 'uid' => $uid, 'sid' => $revieweeId]);
if (!$convStmt->fetch()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Only the buyer in this transaction can leave a review.']);
    exit;
}

$now = (new DateTime())->format('Y-m-d H:i:s');
try {
    $pdo->prepare('INSERT INTO reviews (reviewer_id, reviewee_id, listing_id, rating, comment, created_at) VALUES (:a, :b, :l, :r, :c, :t)')
        ->execute(['a' => $uid, 'b' => $revieweeId, 'l' => $listingId, 'r' => $rating, 'c' => $comment, 't' => $now]);
} catch (PDOException $e) {
    // UNIQUE(reviewer_id, listing_id) — one review per transaction.
    http_response_code(409);
    echo json_encode(['success' => false, 'error' => 'You have already reviewed this transaction.']);
    exit;
}

$actorName = trim((string) ($user['display_name'] ?? '')) !== '' ? $user['display_name'] : $user['username'];
ccw_notify($revieweeId, 'review', $uid, $listingId, $actorName . ' left you a ' . $rating . '-star review for "' . $listing['title'] . '".');

echo json_encode([
    'success' => true,
    'review' => [
        'id' => (int) $pdo->lastInsertId(),
        'listing_id' => $listingId,
        'rating' => $rating,
        'comment' => $comment,
        'created_at' => $now,
    ],
]);
