<?php
require __DIR__ . '/../../auth_lib.php';
ccw_load_environment();

header('Content-Type: application/json');

$username = trim((string) ($_GET['username'] ?? ''));
$q = trim((string) ($_GET['q'] ?? ''));
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

$where = 'f.follower_id = :id';
$params = ['id' => $targetId];
if ($q !== '') {
    $where .= ' AND (u.username LIKE :q OR u.display_name LIKE :q OR u.real_name LIKE :q)';
    $params['q'] = '%' . $q . '%';
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM follows f JOIN users u ON u.id = f.followee_id WHERE $where");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT u.username, u.display_name, u.real_name, u.bio, u.avatar, u.avatar_path, u.campus, f.created_at AS followed_at
    FROM follows f
    JOIN users u ON u.id = f.followee_id
    WHERE $where
    ORDER BY f.created_at DESC
    LIMIT :limit OFFSET :offset
");
foreach ($params as $k => $v) $stmt->bindValue(':' . $k, $v);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

$users = array_map(fn(array $r): array => [
    'username' => $r['username'],
    'display_name' => $r['display_name'],
    'real_name' => $r['real_name'],
    'bio' => $r['bio'],
    'campus' => $r['campus'],
    'avatar_url' => ccw_avatar_url($r),
    'followed_at' => $r['followed_at'],
], $rows);

echo json_encode([
    'success' => true,
    'users' => $users,
    'pagination' => ['page' => $page, 'per_page' => $limit, 'total' => $total],
]);
