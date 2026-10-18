<?php
// Firebase Cloud Messaging (HTTP v1) sender. Silently does nothing until
// CCW_FCM_SERVICE_ACCOUNT points at a Firebase service-account JSON file.
require_once __DIR__ . '/auth_lib.php';

const CCW_PUSH_TITLES = [
    'message' => 'New message',
    'follow' => 'New follower',
    'save' => 'Listing saved',
    'review' => 'New review',
    'listing_limited' => 'Limited stock',
    'low_stock' => 'Almost gone',
    'event_reminder' => 'Event reminder',
];

const CCW_PUSH_CHANNELS = [
    'message' => 'messages',
    'listing_limited' => 'listings',
    'low_stock' => 'listings',
    'event_reminder' => 'events',
];

function ccw_push_service_account(): ?array {
    static $account = false;
    if ($account !== false) return $account;
    $account = null;
    $path = ccw_environment('CCW_FCM_SERVICE_ACCOUNT');
    if ($path === '' || !is_readable($path)) return null;
    $json = json_decode((string) file_get_contents($path), true);
    if (is_array($json) && !empty($json['client_email']) && !empty($json['private_key']) && !empty($json['project_id'])) {
        $account = $json;
    }
    return $account;
}

function ccw_push_base64url(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/** OAuth access token for the FCM API, cached on disk until shortly before it expires. */
function ccw_push_access_token(array $account): ?string {
    $cacheFile = sys_get_temp_dir() . '/ccw_fcm_token_' . md5($account['client_email']) . '.json';
    $cached = is_readable($cacheFile) ? json_decode((string) file_get_contents($cacheFile), true) : null;
    if (is_array($cached) && ($cached['expires_at'] ?? 0) > time() + 60) return $cached['token'];

    $tokenUri = $account['token_uri'] ?? 'https://oauth2.googleapis.com/token';
    $now = time();
    $header = ccw_push_base64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $claims = ccw_push_base64url(json_encode([
        'iss' => $account['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud' => $tokenUri,
        'iat' => $now,
        'exp' => $now + 3600,
    ]));
    $signature = '';
    if (!openssl_sign($header . '.' . $claims, $signature, $account['private_key'], OPENSSL_ALGO_SHA256)) return null;
    $jwt = $header . '.' . $claims . '.' . ccw_push_base64url($signature);

    $request = curl_init($tokenUri);
    curl_setopt_array($request, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
    ]);
    $response = json_decode((string) curl_exec($request), true);
    curl_close($request);
    if (!is_array($response) || empty($response['access_token'])) return null;

    @file_put_contents($cacheFile, json_encode([
        'token' => $response['access_token'],
        'expires_at' => $now + (int) ($response['expires_in'] ?? 3600),
    ]), LOCK_EX);
    @chmod($cacheFile, 0600);
    return $response['access_token'];
}

/** Sends one data message to a device token. Returns false when the token is no longer valid. */
function ccw_push_send_to_token(array $account, string $accessToken, string $deviceToken, array $data): bool {
    $payload = json_encode(['message' => [
        'token' => $deviceToken,
        'data' => array_map('strval', $data),
        'android' => ['priority' => 'HIGH'],
    ]]);
    $request = curl_init('https://fcm.googleapis.com/v1/projects/' . rawurlencode($account['project_id']) . '/messages:send');
    curl_setopt_array($request, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken, 'Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
    ]);
    curl_exec($request);
    $status = (int) curl_getinfo($request, CURLINFO_RESPONSE_CODE);
    curl_close($request);
    if ($status === 404 || $status === 400) return false;
    if ($status < 200 || $status >= 300) error_log('Campus Connect: FCM send returned ' . $status);
    return true;
}

function ccw_push_notification(int $userId, string $type, ?int $entityId, string $body, ?string $title = null): void {
    $account = ccw_push_service_account();
    if (!$account) return;

    $pdo = ccw_db();
    $stmt = $pdo->prepare('SELECT token FROM device_tokens WHERE user_id = :u');
    $stmt->execute(['u' => $userId]);
    $tokens = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (!$tokens) return;

    $accessToken = ccw_push_access_token($account);
    if (!$accessToken) {
        error_log('Campus Connect: unable to obtain an FCM access token');
        return;
    }

    $data = [
        'type' => $type,
        'channel' => CCW_PUSH_CHANNELS[$type] ?? 'activity',
        'title' => $title ?: (CCW_PUSH_TITLES[$type] ?? 'Campus Connect'),
        'body' => $body,
        'entity_id' => $entityId ?? 0,
    ];
    foreach ($tokens as $token) {
        if (!ccw_push_send_to_token($account, $accessToken, $token, $data)) {
            $pdo->prepare('DELETE FROM device_tokens WHERE token = :t')->execute(['t' => $token]);
        }
    }
}
