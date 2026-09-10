<?php
// login.php — validates credentials against users.json
// NOTE: prototype-stage only. Passwords are stored in plaintext in
// users.json, same caveat as the Android app's SQLite database.
// Do not use this approach for real users without hashing (password_hash)
// and a proper server-side session mechanism.

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$identifier = isset($_POST['identifier'])
    ? trim($_POST['identifier'])
    : (isset($_POST['email']) ? trim($_POST['email']) : '');
$password = isset($_POST['password']) ? $_POST['password'] : '';

if ($identifier === '' || $password === '') {
    echo json_encode(['success' => false, 'error' => 'Username or email and password are required.']);
    exit;
}

if (str_contains($identifier, '@') && !preg_match('/^\d{10}@edenuniversity\.education$/i', $identifier)) {
    echo json_encode(['success' => false, 'error' => 'Use your 10-digit ID@edenuniversity.education email.']);
    exit;
}

$file = __DIR__ . '/users.json';
$users = file_exists($file) ? json_decode(file_get_contents($file), true) : [];

foreach ($users as $user) {
    $emailMatches = strtolower($user['email']) === strtolower($identifier);
    $usernameMatches = strtolower($user['username']) === strtolower($identifier);

    if (($emailMatches || $usernameMatches) && $user['password'] === $password) {
        echo json_encode([
            'success' => true,
            'username' => $user['username'],
            'email' => $user['email'],
            'campus' => $user['campus']
        ]);
        exit;
    }
}

echo json_encode(['success' => false, 'error' => 'Invalid email or password.']);
