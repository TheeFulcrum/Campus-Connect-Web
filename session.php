<?php
header('Content-Type: application/json');
session_start();

if (empty($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated.']);
    exit;
}

echo json_encode(['success' => true, 'user' => $_SESSION['user']]);