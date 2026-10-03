<?php
/**
 * Password Recovery - Complete Password Reset
 * POST /api/password-recovery/reset-password.php
 * 
 * Body: { action: "reset_password", token: "xxx", password: "newPassword" }
 * 
 * Returns: { success: bool, message?: string, error?: string }
 */

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../auth_lib.php';

ccw_load_environment();

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['action']) || $input['action'] !== 'reset_password') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action.']);
        exit;
    }

    $resetToken = trim($input['token'] ?? '');
    $newPassword = $input['password'] ?? '';

    if (empty($resetToken)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Reset token is required.']);
        exit;
    }

    if (empty($newPassword)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'New password is required.']);
        exit;
    }

    $result = ccw_reset_password_with_token($resetToken, $newPassword);

    http_response_code($result['success'] ? 200 : 400);
    echo json_encode($result);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error. Please try again later.',
        'debug' => getenv('APP_ENV') === 'development' ? $e->getMessage() : null,
    ]);
}
