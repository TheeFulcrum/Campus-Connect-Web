<?php
require __DIR__ . '/../../auth_lib.php';
ccw_load_environment();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$user = ccw_verify_token_and_get_user(ccw_bearer_token());
$pdo = ccw_db();

$updates = [];
$params = ['id' => (int) $user['id']];

if (array_key_exists('display_name', $body)) {
    $displayName = trim((string) $body['display_name']);
    if (strlen($displayName) > 60) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Display name must be 60 characters or less.']);
        exit;
    }
    $updates[] = 'display_name = :display_name';
    $params['display_name'] = $displayName;
}

if (array_key_exists('username', $body)) {
    $username = trim((string) $body['username']);
    if (strlen($username) < 3 || strlen($username) > 30 || !preg_match('/^[A-Za-z0-9_.]+$/', $username)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Usernames must be 3-30 characters using letters, numbers, dots, or underscores.']);
        exit;
    }
    if (strcasecmp($username, (string) $user['username']) !== 0) {
        $chk = $pdo->prepare('SELECT 1 FROM users WHERE LOWER(username) = LOWER(:u) AND id != :id LIMIT 1');
        $chk->execute(['u' => $username, 'id' => (int) $user['id']]);
        if ($chk->fetch()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'error' => 'That username is already taken.']);
            exit;
        }
    }
    $updates[] = 'username = :username';
    $params['username'] = $username;
}

if (array_key_exists('bio', $body)) {
    $bio = trim((string) $body['bio']);
    if (strlen($bio) > 500) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Bio must be 500 characters or less.']);
        exit;
    }
    $updates[] = 'bio = :bio';
    $params['bio'] = $bio;
}

if (array_key_exists('link', $body)) {
    $link = trim((string) $body['link']);
    if ($link !== '' && (strlen($link) > 200 || !preg_match('#^https?://#i', $link))) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Link must start with http:// or https:// and be 200 characters or less.']);
        exit;
    }
    $updates[] = 'link = :link';
    $params['link'] = $link;
}

if (array_key_exists('campus', $body)) {
    $campus = trim((string) $body['campus']);
    if ($campus === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Campus cannot be empty.']);
        exit;
    }
    $updates[] = 'campus = :campus';
    $params['campus'] = $campus;
}

if (!$updates) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No fields to update.']);
    exit;
}

try {
    $pdo->prepare('UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = :id')->execute($params);
    $fresh = $pdo->prepare('SELECT * FROM users WHERE id = :id');
    $fresh->execute(['id' => (int) $user['id']]);
    echo json_encode(['success' => true, 'profile' => ccw_profile_shape($fresh->fetch())]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to update profile.']);
}
