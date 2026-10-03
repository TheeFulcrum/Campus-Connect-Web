<?php
require __DIR__ . '/../../auth_lib.php';
ccw_load_environment();

header('Content-Type: application/json');

$campus = trim($_GET['campus'] ?? '');
$category = trim($_GET['category'] ?? '');
$status = trim($_GET['status'] ?? 'active');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$pdo = ccw_db();

// Build query
$where = ['l.status = :status'];
$params = ['status' => $status];

if (!empty($campus)) {
    $where[] = 'l.campus = :campus';
    $params['campus'] = $campus;
}
if (!empty($category)) {
    $where[] = 'l.category = :category';
    $params['category'] = $category;
}

$whereClause = implode(' AND ', $where);

// Get total count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM listings l WHERE $whereClause");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

// Get listings
$stmt = $pdo->prepare("
    SELECT l.*, u.username, u.real_name, u.avatar
    FROM listings l
    JOIN users u ON l.user_id = u.id
    WHERE $whereClause
    ORDER BY l.created_at DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
foreach ($params as $key => $value) {
    $stmt->bindValue(":$key", $value);
}
$stmt->execute();
$listings = $stmt->fetchAll();

// Get images for each listing
$listingsWithImages = [];
foreach ($listings as $listing) {
    $imgStmt = $pdo->prepare('SELECT id, image_url FROM listing_images WHERE listing_id = :listing_id ORDER BY display_order');
    $imgStmt->execute(['listing_id' => $listing['id']]);
    $images = $imgStmt->fetchAll();
    
    $listing['images'] = $images;
    $listingsWithImages[] = $listing;
}

echo json_encode([
    'success' => true,
    'listings' => $listingsWithImages,
    'pagination' => [
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total
    ]
]);
