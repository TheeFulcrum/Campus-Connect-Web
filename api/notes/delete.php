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
$noteId = (int) ($body['id'] ?? 0);

if ($noteId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Note ID is required.']);
    exit;
}

$pdo = ccw_db();
$nStmt = $pdo->prepare('SELECT user_id FROM notes WHERE id = :id LIMIT 1');
$nStmt->execute(['id' => $noteId]);
$note = $nStmt->fetch();

if (!$note) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Note not found.']);
    exit;
}
if ((int) $note['user_id'] !== (int) $user['id']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'You can only delete your own notes.']);
    exit;
}

$pdo->prepare('DELETE FROM notes WHERE id = :id')->execute(['id' => $noteId]);

echo json_encode(['success' => true]);
