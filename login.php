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

$email = isset($_POST['email']) ? trim(strtolower($_POST['email'])) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

if ($email === '' || $password === '') {
    echo json_encode(['success' => false, 'error' => 'Email and password are required.']);
    exit;
}

$file = __DIR__ . '/users.json';
$users = file_exists($file) ? json_decode(file_get_contents($file), true) : [];

foreach ($users as $user) {
    if (strtolower($user['email']) === $email && $user['password'] === $password) {
        echo json_encode([
            'success' => true,
            'username' => $user['username'],
            'campus' => $user['campus']
        ]);
        exit;
    }
}

echo json_encode(['success' => false, 'error' => 'Invalid email or password.']);
