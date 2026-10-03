<?php
require __DIR__ . '/../../auth_lib.php';
ccw_load_environment();

header('Content-Type: application/json');

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Listing ID is required.']);
    exit;
}

$pdo = ccw_db();

$stmt = $pdo->prepare('
    SELECT l.*, u.username, u.real_name, u.avatar, u.campus as user_campus
    FROM listings l
    JOIN users u ON l.user_id = u.id
    WHERE l.id = :id
');
$stmt->execute(['id' => $id]);
$listing = $stmt->fetch();

if (!$listing) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Listing not found.']);
    exit;
}

// Get images
$imgStmt = $pdo->prepare('SELECT id, image_url FROM listing_images WHERE listing_id = :listing_id ORDER BY display_order');
$imgStmt->execute(['listing_id' => $id]);
$images = $imgStmt->fetchAll();

$listing['images'] = $images;
$listing['user_profile'] = [
    'username' => $listing['username'],
    'real_name' => $listing['real_name'],
    'campus' => $listing['user_campus'],
    'avatar' => $listing['avatar']
];

echo json_encode([
    'success' => true,
    'listing' => $listing
]);
