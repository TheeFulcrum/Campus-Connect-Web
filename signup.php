<?php
// signup.php — creates a new account in users.json
// NOTE: prototype-stage only. Passwords are stored in plaintext,
// same caveat as the Android app's SQLite database and login.php.

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$email = isset($_POST['email']) ? trim(strtolower($_POST['email'])) : '';
$campus = isset($_POST['campus']) ? trim($_POST['campus']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

if (strlen($username) < 3) {
    echo json_encode(['success' => false, 'error' => 'Username must be at least 3 characters.']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !str_ends_with($email, '.education')) {
    echo json_encode(['success' => false, 'error' => 'Please use a valid .education email.']);
    exit;
}
if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'error' => 'Password must be at least 6 characters.']);
    exit;
}

$file = __DIR__ . '/users.json';
$users = file_exists($file) ? json_decode(file_get_contents($file), true) : [];

foreach ($users as $user) {
    if (strtolower($user['email']) === $email) {
        echo json_encode(['success' => false, 'error' => 'An account with this email already exists.']);
        exit;
    }
}

$users[] = [
    'username' => $username,
    'email' => $email,
    'campus' => $campus,
    'password' => $password, // prototype only — plaintext, same caveat as the app's SQLite DB
    'created_at' => date('c')
];

file_put_contents($file, json_encode($users, JSON_PRETTY_PRINT), LOCK_EX);

echo json_encode(['success' => true]);
