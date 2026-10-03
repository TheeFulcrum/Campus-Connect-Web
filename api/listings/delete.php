<?php
require __DIR__ . '/../../auth_lib.php';
ccw_load_environment();

header('Content-Type: application/json');

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $body['action'] ?? '';
$token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token = preg_replace('/^Bearer\s+/i', '', $token);

if ($action !== 'delete_listing') {
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

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Listing ID is required.']);
    exit;
}

$pdo = ccw_db();

// Verify token and get user
$tokenStmt = $pdo->prepare('SELECT email FROM tokens WHERE token_hash = :hash AND expires_at > :now');
$tokenStmt->execute(['hash' => hash('sha256', $token), 'now' => time()]);
$tokenRow = $tokenStmt->fetch();

if (!$tokenRow) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid or expired token.']);
    exit;
}

$userStmt = $pdo->prepare('SELECT id FROM users WHERE email = :email');
$userStmt->execute(['email' => $tokenRow['email']]);
$user = $userStmt->fetch();

if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'User not found.']);
    exit;
}

// Verify ownership
$listingStmt = $pdo->prepare('SELECT user_id FROM listings WHERE id = :id');
$listingStmt->execute(['id' => $id]);
$listing = $listingStmt->fetch();

if (!$listing || $listing['user_id'] != $user['id']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'You can only delete your own listings.']);
    exit;
}

try {
    $pdo->prepare('DELETE FROM listing_images WHERE listing_id = :id')->execute(['id' => $id]);
    $pdo->prepare('DELETE FROM listings WHERE id = :id')->execute(['id' => $id]);

    echo json_encode([
        'success' => true,
        'message' => 'Listing deleted.'
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to delete listing.']);
}
