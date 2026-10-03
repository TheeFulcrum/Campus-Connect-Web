<?php
/**
 * Password Recovery - Verify Reset Token
 * GET /api/password-recovery/verify-token.php?token=xxx
 * 
 * Returns: { success: bool, email?: string, error?: string }
 */

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../auth_lib.php';

ccw_load_environment();

try {
    $resetToken = trim($_GET['token'] ?? '');

    if (empty($resetToken)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid or missing reset token.']);
        exit;
    }

    $result = ccw_verify_reset_token($resetToken);

    if ($result['success']) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'email' => $result['email'] ?? '',
        ]);
    } else {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $result['error'] ?? 'Invalid token.',
        ]);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error. Please try again later.',
        'debug' => getenv('APP_ENV') === 'development' ? $e->getMessage() : null,
    ]);
}
