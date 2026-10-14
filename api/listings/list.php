<?php
require __DIR__ . '/../../auth_lib.php';
ccw_load_environment();

header('Content-Type: application/json');

$campus = trim($_GET['campus'] ?? '');
$category = trim($_GET['category'] ?? '');
$status = trim($_GET['status'] ?? 'active');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = (int)($_GET['limit'] ?? 20);
if ($perPage < 1) $perPage = 20;
if ($perPage > 50) $perPage = 50;
$offset = ($page - 1) * $perPage;

$pdo = ccw_db();

// Build query (archived listings are hidden from public browse)
$where = ['l.status = :status', 'l.archived = 0'];
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

// Get images for each listing, plus is_saved when the viewer is authenticated
$viewerId = null;
$token = ccw_bearer_token();
if ($token) {
    $tStmt = $pdo->prepare('SELECT email FROM tokens WHERE token_hash = :h AND expires_at > :now LIMIT 1');
    $tStmt->execute(['h' => hash('sha256', $token), 'now' => time()]);
    $tRow = $tStmt->fetch();
    if ($tRow) {
        $vu = $pdo->prepare('SELECT id FROM users WHERE email = :e LIMIT 1');
        $vu->execute(['e' => $tRow['email']]);
        $vRow = $vu->fetch();
        if ($vRow) $viewerId = (int) $vRow['id'];
    }
}

$listingsWithImages = [];
foreach ($listings as $listing) {
    $imgStmt = $pdo->prepare('SELECT id, image_url FROM listing_images WHERE listing_id = :listing_id ORDER BY display_order');
    $imgStmt->execute(['listing_id' => $listing['id']]);
    $images = $imgStmt->fetchAll();

    $listing['images'] = $images;
    if ($viewerId !== null) {
        $sStmt = $pdo->prepare('SELECT 1 FROM saves WHERE user_id = :u AND listing_id = :l LIMIT 1');
        $sStmt->execute(['u' => $viewerId, 'l' => (int) $listing['id']]);
        $listing['is_saved'] = (bool) $sStmt->fetch();
    }
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
