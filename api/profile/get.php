<?php
require __DIR__ . '/../../auth_lib.php';
ccw_load_environment();

header('Content-Type: application/json');

$username = trim((string) ($_GET['username'] ?? ''));
if ($username === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Username is required.']);
    exit;
}

$pdo = ccw_db();
$stmt = $pdo->prepare('SELECT * FROM users WHERE LOWER(username) = LOWER(:u) LIMIT 1');
$stmt->execute(['u' => $username]);
$target = $stmt->fetch();

if (!$target) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'User not found.']);
    exit;
}

$targetId = (int) $target['id'];

// Optional auth: determine the viewer for is_me / is_following.
$viewer = null;
$token = ccw_bearer_token();
if ($token) {
    $tStmt = $pdo->prepare('SELECT email FROM tokens WHERE token_hash = :h AND expires_at > :now LIMIT 1');
    $tStmt->execute(['h' => hash('sha256', $token), 'now' => time()]);
    $tRow = $tStmt->fetch();
    if ($tRow) {
        $uStmt = $pdo->prepare('SELECT * FROM users WHERE email = :e LIMIT 1');
        $uStmt->execute(['e' => $tRow['email']]);
        $viewer = $uStmt->fetch() ?: null;
    }
}

$isMe = $viewer && (int) $viewer['id'] === $targetId;
$isFollowing = false;
if ($viewer && !$isMe) {
    $fStmt = $pdo->prepare('SELECT 1 FROM follows WHERE follower_id = :a AND followee_id = :b LIMIT 1');
    $fStmt->execute(['a' => (int) $viewer['id'], 'b' => $targetId]);
    $isFollowing = (bool) $fStmt->fetch();
}

$count = function (string $sql, array $params) use ($pdo): int {
    $s = $pdo->prepare($sql);
    $s->execute($params);
    return (int) $s->fetchColumn();
};

$profile = ccw_profile_shape($target);
$profile['counts'] = [
    'listings' => $count("SELECT COUNT(*) FROM listings WHERE user_id = :id AND status = 'active' AND archived = 0", ['id' => $targetId]),
    'followers' => $count('SELECT COUNT(*) FROM follows WHERE followee_id = :id', ['id' => $targetId]),
    'following' => $count('SELECT COUNT(*) FROM follows WHERE follower_id = :id', ['id' => $targetId]),
];
$ratingStmt = $pdo->prepare('SELECT COUNT(*) AS c, AVG(rating) AS a FROM reviews WHERE reviewee_id = :id');
$ratingStmt->execute(['id' => $targetId]);
$rating = $ratingStmt->fetch();
$profile['rating'] = [
    'count' => (int) ($rating['c'] ?? 0),
    'average' => $rating['a'] !== null ? round((float) $rating['a'], 1) : null,
];
$profile['is_me'] = (bool) $isMe;
$profile['is_following'] = $isFollowing;

// Active profile note (60 chars, expires after 24h), if any.
$noteStmt = $pdo->prepare('SELECT body, expires_at FROM notes WHERE user_id = :id AND listing_id IS NULL AND expires_at > :now ORDER BY id DESC LIMIT 1');
$noteStmt->execute(['id' => $targetId, 'now' => time()]);
$note = $noteStmt->fetch();
$profile['note'] = $note ? ['body' => $note['body'], 'expires_at' => (int) $note['expires_at']] : null;

echo json_encode(['success' => true, 'profile' => $profile]);
