<?php
/**
 * Password Recovery - Request Reset Link
 * POST /api/password-recovery/request-reset.php
 * 
 * Body: { action: "request_reset", email: "user@example.com" }
 * 
 * Returns: { success: bool, message: string, reset_url?: string }
 * Note: reset_url is included in development only for testing
 */

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../auth_lib.php';
require_once __DIR__ . '/../_rate_limit.php';

ccw_load_environment();

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['action']) || $input['action'] !== 'request_reset') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action.']);
        exit;
    }

    $email = trim($input['email'] ?? '');
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Please provide a valid email address.']);
        exit;
    }

    ccw_rate_limit_or_fail('password_reset_ip', ccw_client_ip(), 10, 3600);
    ccw_rate_limit_or_fail('password_reset_email', hash('sha256', strtolower($email)), 3, 3600);

    $result = ccw_create_password_reset_token($email);

    // The token and link are delivered by email only; expose them in development for testing
    if (getenv('APP_ENV') !== 'development') {
        unset($result['reset_token'], $result['reset_url']);
    }
    http_response_code(isset($result['error']) ? 500 : 200);
    echo json_encode($result);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error. Please try again later.',
        'debug' => getenv('APP_ENV') === 'development' ? $e->getMessage() : null,
    ]);
}
