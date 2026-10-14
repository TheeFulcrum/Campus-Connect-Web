<?php
require __DIR__ . '/../../auth_lib.php';
ccw_load_environment();

header('Content-Type: application/json');

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $body['action'] ?? '';
$token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token = preg_replace('/^Bearer\s+/i', '', $token);

if ($action !== 'update_listing') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid action.']);
    exit;
}

if (empty($token)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated.']);
    exit;
}

$id = (int) ($body['id'] ?? 0);
$title = trim($body['title'] ?? '');
$description = trim($body['description'] ?? '');
$price = $body['price'] ?? null;
$status = trim($body['status'] ?? '');

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Listing ID is required.']);
    exit;
}

$imageIds = $body['image_ids'] ?? null;

$pdo = ccw_db();
$user = ccw_verify_token_and_get_user($token);

// Verify ownership
$listingStmt = $pdo->prepare('SELECT user_id FROM listings WHERE id = :id');
$listingStmt->execute(['id' => $id]);
$listing = $listingStmt->fetch();

if (!$listing || $listing['user_id'] != $user['id']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'You can only update your own listings.']);
    exit;
}

$now = (new DateTime())->format('Y-m-d H:i:s');
$updates = ['updated_at = :updated_at'];
$params = ['updated_at' => $now, 'id' => $id];

if (!empty($title)) {
    $updates[] = 'title = :title';
    $params['title'] = $title;
}
if (!empty($description)) {
    $updates[] = 'description = :description';
    $params['description'] = $description;
}
if ($price !== null) {
    $updates[] = 'price = :price';
    $params['price'] = $price;
}
if (!empty($status)) {
    $updates[] = 'status = :status';
    $params['status'] = $status;
}

$updateClause = implode(', ', $updates);

try {
    $stmt = $pdo->prepare("UPDATE listings SET $updateClause WHERE id = :id");
    $stmt->execute($params);

    // Append additional uploaded images when image_ids (serving URLs) are provided.
    if (is_array($imageIds) && $imageIds) {
        $urls = [];
        foreach (array_slice($imageIds, 0, 10) as $url) {
            $url = trim((string) $url);
            if (preg_match('#^/api/uploads/serve\.php\?f=[a-z0-9_%.-]+$#i', $url)) $urls[] = $url;
        }
        if ($urls) {
            $oStmt = $pdo->prepare('SELECT COALESCE(MAX(display_order), -1) + 1 FROM listing_images WHERE listing_id = :id');
            $oStmt->execute(['id' => $id]);
            $order = (int) $oStmt->fetchColumn();
            $ins = $pdo->prepare('INSERT INTO listing_images (listing_id, image_url, display_order, created_at) VALUES (:l, :u, :o, :c)');
            foreach ($urls as $url) {
                $ins->execute(['l' => $id, 'u' => $url, 'o' => $order++, 'c' => $now]);
            }
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Listing updated.',
        'listing' => [
            'id' => $id,
            'title' => $title,
            'price' => $price,
            'status' => $status,
            'updated_at' => $now
        ]
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to update listing.']);
}
