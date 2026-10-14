<?php
require __DIR__ . '/../../auth_lib.php';
ccw_load_environment();
require_once __DIR__ . '/../_rate_limit.php';

header('Content-Type: application/json');

$q = trim((string) ($_GET['q'] ?? ''));

ccw_rate_limit_or_fail('search', ccw_client_ip(), 60, 60);

if ($q === '' || strlen($q) < 2) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Search query must be at least 2 characters.']);
    exit;
}
if (strlen($q) > 100) {
    $q = substr($q, 0, 100);
}

[$page, $limit, $offset] = ccw_pagination();
$pdo = ccw_db();
$like = '%' . $q . '%';

// --- Users ---
$userStmt = $pdo->prepare('
    SELECT username, display_name, real_name, bio, campus, avatar, avatar_path
    FROM users
    WHERE username LIKE :q OR display_name LIKE :q OR real_name LIKE :q
    ORDER BY username
    LIMIT :limit OFFSET :offset
');
$userStmt->bindValue(':q', $like);
$userStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$userStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$userStmt->execute();
$users = array_map(fn(array $r): array => [
    'username' => $r['username'],
    'display_name' => $r['display_name'],
    'real_name' => $r['real_name'],
    'bio' => $r['bio'],
    'campus' => $r['campus'],
    'avatar_url' => ccw_avatar_url($r),
], $userStmt->fetchAll());

$userCount = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username LIKE :q OR display_name LIKE :q OR real_name LIKE :q');
$userCount->execute(['q' => $like]);
$userTotal = (int) $userCount->fetchColumn();

// --- Listings ---
$listingStmt = $pdo->prepare("
    SELECT l.id, l.title, l.description, l.price, l.category, l.campus, l.created_at, u.username AS seller_username
    FROM listings l
    JOIN users u ON u.id = l.user_id
    WHERE l.status = 'active' AND l.archived = 0
      AND (l.title LIKE :q OR l.description LIKE :q OR l.category LIKE :q)
    ORDER BY l.created_at DESC
    LIMIT :limit OFFSET :offset
");
$listingStmt->bindValue(':q', $like);
$listingStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$listingStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$listingStmt->execute();
$listings = $listingStmt->fetchAll();

foreach ($listings as &$listing) {
    $img = $pdo->prepare('SELECT image_url FROM listing_images WHERE listing_id = :id ORDER BY display_order LIMIT 1');
    $img->execute(['id' => $listing['id']]);
    $listing['cover_image'] = $img->fetchColumn() ?: null;
}
unset($listing);

$listingCount = $pdo->prepare("SELECT COUNT(*) FROM listings WHERE status = 'active' AND archived = 0 AND (title LIKE :q OR description LIKE :q OR category LIKE :q)");
$listingCount->execute(['q' => $like]);
$listingTotal = (int) $listingCount->fetchColumn();

echo json_encode([
    'success' => true,
    'query' => $q,
    'users' => $users,
    'listings' => $listings,
    'pagination' => [
        'page' => $page,
        'per_page' => $limit,
        'users_total' => $userTotal,
        'listings_total' => $listingTotal,
    ],
]);
