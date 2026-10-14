<?php
// Shared rate-limit helper. Uses the rate_limits table (created in ccw_db()).
require_once __DIR__ . '/../auth_lib.php';

/**
 * Fixed-window rate limiter backed by SQLite.
 * Returns true if the request is allowed, false if the limit is exceeded.
 *
 * @param string $bucket   Logical bucket, e.g. 'login', 'otp', 'search', 'message', 'upload'
 * @param string $key      Identity key, e.g. IP address or user id
 * @param int    $max      Max allowed hits per window
 * @param int    $windowS  Window length in seconds
 */
function ccw_rate_limit(string $bucket, string $key, int $max, int $windowS): bool {
    if (ccw_use_json_auth_store()) return true; // no SQLite available; fail open
    $pdo = ccw_db();
    $now = time();
    $id = $bucket . ':' . $key;

    $stmt = $pdo->prepare('SELECT count, window_start FROM rate_limits WHERE bucket = :b AND rate_key = :k');
    $stmt->execute(['b' => $bucket, 'k' => $id]);
    $row = $stmt->fetch();

    if (!$row || ((int) $row['window_start']) + $windowS <= $now) {
        $pdo->prepare('INSERT INTO rate_limits (bucket, rate_key, count, window_start) VALUES (:b, :k, 1, :w)
                       ON CONFLICT(bucket, rate_key) DO UPDATE SET count = 1, window_start = excluded.window_start')
            ->execute(['b' => $bucket, 'k' => $id, 'w' => $now]);
        return true;
    }

    if ((int) $row['count'] >= $max) return false;

    $pdo->prepare('UPDATE rate_limits SET count = count + 1 WHERE bucket = :b AND rate_key = :k')
        ->execute(['b' => $bucket, 'k' => $id]);
    return true;
}

/**
 * Enforce a rate limit or emit a 429 JSON response and exit.
 */
function ccw_rate_limit_or_fail(string $bucket, string $key, int $max, int $windowS): void {
    if (!ccw_rate_limit($bucket, $key, $max, $windowS)) {
        http_response_code(429);
        echo json_encode(['success' => false, 'error' => 'Too many requests. Please slow down and try again shortly.']);
        exit;
    }
}

function ccw_client_ip(): string {
    return trim((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
}
