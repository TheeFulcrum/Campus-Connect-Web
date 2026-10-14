<?php
require __DIR__ . '/../../auth_lib.php';
ccw_load_environment();

header('Content-Type: application/json');

$username = trim((string) ($_GET['username'] ?? ''));
if ($username === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Username is required.']);
    exit;
}

[$page, $limit, $offset] = ccw_pagination();
$pdo = ccw_db();

$uStmt = $pdo->prepare('SELECT id FROM users WHERE LOWER(username) = LOWER(:u) LIMIT 1');
$uStmt->execute(['u' => $username]);
$target = $uStmt->fetch();
if (!$target) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'User not found.']);
    exit;
}
$targetId = (int) $target['id'];

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM reviews WHERE reviewee_id = :id');
$countStmt->execute(['id' => $targetId]);
$total = (int) $countStmt->fetchColumn();

$avgStmt = $pdo->prepare('SELECT AVG(rating) FROM reviews WHERE reviewee_id = :id');
$avgStmt->execute(['id' => $targetId]);
$average = $avgStmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT r.id, r.rating, r.comment, r.created_at, r.listing_id,
           u.username AS reviewer_username, u.display_name AS reviewer_display_name, u.avatar_path AS reviewer_avatar_path,
           l.title AS listing_title
    FROM reviews r
    JOIN users u ON u.id = r.reviewer_id
    JOIN listings l ON l.id = r.listing_id
    WHERE r.reviewee_id = :id
    ORDER BY r.created_at DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':id', $targetId, PDO::PARAM_INT);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$reviews = $stmt->fetchAll();

foreach ($reviews as &$review) {
    $review['reviewer_avatar_url'] = ccw_avatar_url(['avatar_path' => $review['reviewer_avatar_path'], 'avatar' => '']);
    unset($review['reviewer_avatar_path']);
}
unset($review);

echo json_encode([
    'success' => true,
    'reviews' => $reviews,
    'rating' => [
        'count' => $total,
        'average' => $average !== null && $average !== false ? round((float) $average, 1) : null,
    ],
    'pagination' => ['page' => $page, 'per_page' => $limit, 'total' => $total],
]);
