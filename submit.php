<?php
// submit.php — handles waitlist signups for Campus Connect
// Stores emails in waitlist.csv (one per line). Swap this out for a
// database or mail service later on if you want.

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$email = isset($_POST['email']) ? trim($_POST['email']) : '';

// Basic validation
if ($email === '') {
    echo json_encode(['success' => false, 'error' => 'Email is required.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Please enter a valid email address.']);
    exit;
}

if (!str_ends_with(strtolower($email), '.education')) {
    echo json_encode(['success' => false, 'error' => 'Please use your .education university email.']);
    exit;
}

// Store the signup
$file = __DIR__ . '/waitlist.csv';
$entry = date('Y-m-d H:i:s') . ',' . $email . PHP_EOL;

// Avoid duplicate entries
$existing = file_exists($file) ? file_get_contents($file) : '';
if (strpos($existing, ',' . $email . PHP_EOL) === false && strpos($existing, ',' . $email) === false) {
    file_put_contents($file, $entry, FILE_APPEND | LOCK_EX);
}

// Optional: send yourself a notification email
// Uncomment and set your address to get notified of each signup.
// mail('you@yourdomain.com', 'New Campus Connect waitlist signup', $email);

echo json_encode(['success' => true]);
