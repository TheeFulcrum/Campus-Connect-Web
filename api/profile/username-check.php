<?php
require __DIR__ . '/../../auth_lib.php';
ccw_load_environment();

header('Content-Type: application/json');

$username = trim((string) ($_GET['username'] ?? ''));

if ($username === '' || strlen($username) < 3 || strlen($username) > 30 || !preg_match('/^[A-Za-z0-9_.]+$/', $username)) {
    echo json_encode([
        'success' => true,
        'available' => false,
        'reason' => 'Usernames must be 3-30 characters using letters, numbers, dots, or underscores.',
    ]);
    exit;
}

$pdo = ccw_db();
$stmt = $pdo->prepare('SELECT id, email FROM users WHERE LOWER(username) = LOWER(:u) LIMIT 1');
$stmt->execute(['u' => $username]);
$existing = $stmt->fetch();

$available = !$existing;

// The current user may keep their own username.
if ($existing) {
    $token = ccw_bearer_token();
    if ($token) {
        $tStmt = $pdo->prepare('SELECT email FROM tokens WHERE token_hash = :h AND expires_at > :now LIMIT 1');
        $tStmt->execute(['h' => hash('sha256', $token), 'now' => time()]);
        $tRow = $tStmt->fetch();
        if ($tRow && strcasecmp($tRow['email'], $existing['email']) === 0) {
            $available = true;
        }
    }
}

echo json_encode(['success' => true, 'available' => $available]);
