<?php
require __DIR__ . '/../../auth_lib.php';
ccw_load_environment();
require_once __DIR__ . '/../_rate_limit.php';
require_once __DIR__ . '/../_uploads.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$user = ccw_verify_token_and_get_user(ccw_bearer_token());
ccw_rate_limit_or_fail('upload', 'user:' . (int) $user['id'], 20, 3600);

$pdo = ccw_db();
$listingId = (int) ($_POST['listing_id'] ?? 0);

// When a listing_id is given, images attach to it immediately and must belong to the caller.
$listing = null;
if ($listingId > 0) {
    $lStmt = $pdo->prepare('SELECT user_id FROM listings WHERE id = :id');
    $lStmt->execute(['id' => $listingId]);
    $listing = $lStmt->fetch();
    if (!$listing) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Listing not found.']);
        exit;
    }
    if ((int) $listing['user_id'] !== (int) $user['id']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'You can only add images to your own listings.']);
        exit;
    }
}

// Normalize the images[] multi-file upload array.
$files = [];
if (!empty($_FILES['images']) && is_array($_FILES['images']['name'])) {
    foreach ($_FILES['images']['name'] as $i => $name) {
        $files[] = [
            'name' => $name,
            'type' => $_FILES['images']['type'][$i] ?? '',
            'tmp_name' => $_FILES['images']['tmp_name'][$i] ?? '',
            'error' => $_FILES['images']['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            'size' => $_FILES['images']['size'][$i] ?? 0,
        ];
    }
} elseif (!empty($_FILES['image'])) {
    $files[] = $_FILES['image'];
}

if (!$files) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No image files were uploaded.']);
    exit;
}
if (count($files) > 10) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'You can upload at most 10 images at a time.']);
    exit;
}

$uploaded = [];
$errors = [];
$nextOrder = 0;
if ($listing) {
    $oStmt = $pdo->prepare('SELECT COALESCE(MAX(display_order), -1) + 1 FROM listing_images WHERE listing_id = :id');
    $oStmt->execute(['id' => $listingId]);
    $nextOrder = (int) $oStmt->fetchColumn();
}

foreach ($files as $file) {
    $stored = ccw_store_image_upload($file, 'listings', 5 * 1024 * 1024, 1600);
    if (isset($stored['error'])) {
        $errors[] = $stored['error'];
        continue;
    }
    $url = ccw_upload_url($stored['path']);
    $imageId = null;
    if ($listing) {
        $pdo->prepare('INSERT INTO listing_images (listing_id, image_url, display_order, created_at) VALUES (:l, :u, :o, :c)')
            ->execute([
                'l' => $listingId,
                'u' => $url,
                'o' => $nextOrder++,
                'c' => (new DateTime())->format('Y-m-d H:i:s'),
            ]);
        $imageId = (int) $pdo->lastInsertId();
    }
    $uploaded[] = ['id' => $imageId, 'image_url' => $url];
}

if (!$uploaded && $errors) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => $errors[0]]);
    exit;
}

echo json_encode(['success' => true, 'images' => $uploaded] + ($errors ? ['warnings' => $errors] : []));
