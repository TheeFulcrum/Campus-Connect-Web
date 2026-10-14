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

$total = (int) $pdo->query("SELECT COUNT(*) FROM listings WHERE user_id = $targetId AND status = 'sold'")->fetchColumn();

$stmt = $pdo->prepare("
    SELECT l.id, l.title, l.price, l.category, l.campus, l.status, l.created_at, l.sold_at
    FROM listings l
    WHERE l.user_id = :id AND l.status = 'sold'
    ORDER BY l.sold_at DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':id', $targetId, PDO::PARAM_INT);
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
