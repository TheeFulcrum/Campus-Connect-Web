<?php
/**
 * Password Recovery - Complete Password Reset
 * POST /api/password-recovery/reset-password.php
 * 
 * Body: { action: "reset_password", token: "xxx", password: "newPassword" }
 *   or: { action: "reset_password", email: "user@example.com", code: "123456", password: "newPassword" }
 * 
 * Returns: { success: bool, message?: string, error?: string }
 */

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../auth_lib.php';
require_once __DIR__ . '/../_rate_limit.php';

ccw_load_environment();

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['action']) || $input['action'] !== 'reset_password') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action.']);
        exit;
    }

    ccw_rate_limit_or_fail('password_reset_submit', ccw_client_ip(), 20, 3600);

    $newPassword = $input['password'] ?? '';
    if (empty($newPassword)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'New password is required.']);
        exit;
    }

    $resetToken = trim($input['token'] ?? '');
    $email = trim($input['email'] ?? '');
    $code = trim((string) ($input['code'] ?? ''));

    if ($resetToken !== '') {
        $result = ccw_reset_password_with_token($resetToken, $newPassword);
    } elseif ($email !== '' && $code !== '') {
        $result = ccw_reset_password_with_code($email, $code, $newPassword);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Reset code is required.']);
        exit;
    }

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
