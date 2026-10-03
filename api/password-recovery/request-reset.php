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

    $result = ccw_create_password_reset_token($email);

    // In production environment, the reset URL would be sent via email
    // For development, we include it in the response for testing
    if (isset($result['reset_token']) && !empty(getenv('APP_ENV')) && getenv('APP_ENV') === 'development') {
        // Keep reset_url in response for testing
        http_response_code(200);
        echo json_encode($result);
    } else {
        // Remove sensitive token from production response
        unset($result['reset_token']);
        http_response_code(200);
        echo json_encode($result);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error. Please try again later.',
        'debug' => getenv('APP_ENV') === 'development' ? $e->getMessage() : null,
    ]);
}
