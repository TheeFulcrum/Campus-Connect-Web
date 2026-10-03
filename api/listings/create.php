<?php
require __DIR__ . '/../../auth_lib.php';
ccw_load_environment();

header('Content-Type: application/json');

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $body['action'] ?? '';
$token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token = preg_replace('/^Bearer\s+/i', '', $token);

if ($action !== 'create_listing') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid action.']);
    exit;
}

if (empty($token)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated.']);
    exit;
}

$title = trim($body['title'] ?? '');
$description = trim($body['description'] ?? '');
$category = trim($body['category'] ?? '');
$campus = trim($body['campus'] ?? '');
$price = $body['price'] ?? null;

if (empty($title) || empty($description) || empty($category) || empty($campus)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Title, description, category, and campus are required.']);
    exit;
}

if (strlen($title) > 160) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Title must be 160 characters or less.']);
    exit;
}

$pdo = ccw_db();
$user = ccw_verify_token_and_get_user($token);
$userId = $user['id'];
$now = (new DateTime())->format('Y-m-d H:i:s');

try {
    $stmt = $pdo->prepare('
        INSERT INTO listings (user_id, title, description, category, campus, price, status, created_at, updated_at)
        VALUES (:user_id, :title, :description, :category, :campus, :price, :status, :created_at, :updated_at)
    ');
    $stmt->execute([
        'user_id' => $userId,
        'title' => $title,
        'description' => $description,
        'category' => $category,
        'campus' => $campus,
        'price' => $price,
        'status' => 'active',
        'created_at' => $now,
        'updated_at' => $now
    ]);

    $listingId = (int) $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'listing' => [
            'id' => $listingId,
            'user_id' => $userId,
            'title' => $title,
            'description' => $description,
            'category' => $category,
            'campus' => $campus,
            'price' => $price,
            'status' => 'active',
            'created_at' => $now
        ]
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to create listing.']);
}
