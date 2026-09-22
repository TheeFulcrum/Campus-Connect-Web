<?php

const CCW_USERS_FILE = __DIR__ . '/users.json';
const CCW_TOKENS_FILE = __DIR__ . '/auth_tokens.json';
const CCW_OTP_FILE = __DIR__ . '/otp_codes.json';
const CCW_TOKEN_TTL_SECONDS = 60 * 60 * 24 * 30;
const CCW_OTP_TTL_SECONDS = 60 * 10;
const CCW_OTP_RESEND_SECONDS = 60;
const CCW_OTP_MAX_ATTEMPTS = 5;

function ccw_load_environment(): void {
    $environmentFile = __DIR__ . '/.env';
    if (!file_exists($environmentFile)) return;

    $lines = file($environmentFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) return;

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if ($key === '') continue;
        if (strlen($value) >= 2 && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
            $value = substr($value, 1, -1);
        }
        if (getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}

function ccw_environment(string $name): string {
    $value = getenv($name);
    return is_string($value) ? trim($value) : '';
}

function ccw_read_json(string $path): array {
    if (!file_exists($path)) {
        return [];
    }

    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function ccw_write_json(string $path, array $data): bool {
    return file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX) !== false;
}

function ccw_valid_email(string $email): bool {
    return preg_match('/^\d{10}@edenuniversity\.education$/i', $email) === 1;
}

function ccw_register_user(string $username, string $email, string $campus, string $password): array {
    $username = trim($username);
    $email = strtolower(trim($email));
    $campus = trim($campus);

    if (strlen($username) < 3) return ['error' => 'Username must be at least 3 characters.'];
    if (!ccw_valid_email($email)) return ['error' => 'Use your 10-digit ID@edenuniversity.education email.'];
    if (strlen($password) < 8) return ['error' => 'Password must be at least 8 characters.'];
    if ($campus === '') return ['error' => 'Campus is required.'];

    $users = ccw_read_json(CCW_USERS_FILE);
    foreach ($users as $user) {
        if (strcasecmp($user['username'] ?? '', $username) === 0) return ['error' => 'That username is already taken.'];
        if (strcasecmp($user['email'] ?? '', $email) === 0) return ['error' => 'An account with this email already exists.'];
    }

    $user = [
        'username' => $username,
        'email' => $email,
        'campus' => $campus,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'created_at' => date('c')
    ];
    $users[] = $user;

    return ccw_write_json(CCW_USERS_FILE, $users)
        ? ['user' => ccw_public_user($user)]
        : ['error' => 'Unable to create the account. Please try again.'];
}

function ccw_authenticate_user(string $identifier, string $password): array {
    $identifier = trim($identifier);
    $users = ccw_read_json(CCW_USERS_FILE);

    foreach ($users as $index => $user) {
        $emailMatches = strcasecmp($user['email'] ?? '', $identifier) === 0;
        $usernameMatches = strcasecmp($user['username'] ?? '', $identifier) === 0;
        if (!$emailMatches && !$usernameMatches) continue;

        $storedPassword = $user['password'] ?? '';
        $verified = password_verify($password, $storedPassword);
        $needsSave = false;

        if (!$verified && hash_equals($storedPassword, $password)) {
            $verified = true;
            $users[$index]['password'] = password_hash($password, PASSWORD_DEFAULT);
            $user = $users[$index];
            $needsSave = true;
        } elseif ($verified && password_needs_rehash($storedPassword, PASSWORD_DEFAULT)) {
            $users[$index]['password'] = password_hash($password, PASSWORD_DEFAULT);
            $needsSave = true;
        }

        if (!$verified) return ['error' => 'Invalid email or password.'];
        if ($needsSave && !ccw_write_json(CCW_USERS_FILE, $users)) return ['error' => 'Unable to update account security.'];
        return ['user' => ccw_public_user($user)];
    }

    return ['error' => 'Invalid email or password.'];
}

function ccw_find_user_by_email(string $email): ?array {
    foreach (ccw_read_json(CCW_USERS_FILE) as $user) {
        if (strcasecmp($user['email'] ?? '', $email) === 0) return $user;
    }
    return null;
}

function ccw_otp_key(string $email): string {
    return hash('sha256', strtolower(trim($email)));
}

function ccw_send_otp_email(string $email, string $code): int {
    $apiKey = ccw_environment('RESEND_API_KEY');
    $from = ccw_environment('CCW_FROM_EMAIL');
    if ($apiKey === '' || $from === '') return 0;

    $payload = json_encode([
        'from' => $from,
        'to' => [$email],
        'subject' => 'Your Campus Connect sign-in code',
        'html' => '<p>Your Campus Connect sign-in code is:</p><p style="font-size: 24px; font-weight: 700; letter-spacing: 4px;">' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</p><p>This code expires in 10 minutes. Do not share it with anyone.</p>'
    ]);

    $request = curl_init('https://api.resend.com/emails');
    curl_setopt_array($request, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15
    ]);
    curl_exec($request);
    $status = curl_getinfo($request, CURLINFO_RESPONSE_CODE);
    curl_close($request);
    return $status;
}

function ccw_request_otp(string $email): array {
    $email = strtolower(trim($email));
    if (!ccw_valid_email($email)) return ['error' => 'Use your 10-digit ID@edenuniversity.education email.'];
    if (!ccw_find_user_by_email($email)) return ['error' => 'No Campus Connect account exists for this email.'];

    $codes = ccw_read_json(CCW_OTP_FILE);
    $key = ccw_otp_key($email);
    $now = time();
    $existing = $codes[$key] ?? null;
    if ($existing && ($existing['sent_at'] ?? 0) + CCW_OTP_RESEND_SECONDS > $now) {
        return ['error' => 'Please wait one minute before requesting another code.'];
    }

    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $deliveryStatus = ccw_send_otp_email($email, $code);
    if ($deliveryStatus < 200 || $deliveryStatus >= 300) {
        if ($deliveryStatus === 422) {
            return ['error' => 'Resend rejected this email. Verify your sender domain and recipient permissions in Resend.'];
        }
        if ($deliveryStatus === 401 || $deliveryStatus === 403) {
            return ['error' => 'Resend rejected the API credentials or sender configuration.'];
        }
        return ['error' => 'Unable to send a sign-in code. Check the mail configuration and try again.'];
    }

    $codes[$key] = [
        'email' => $email,
        'code_hash' => password_hash($code, PASSWORD_DEFAULT),
        'sent_at' => $now,
        'expires_at' => $now + CCW_OTP_TTL_SECONDS,
        'attempts' => 0
    ];
    ccw_write_json(CCW_OTP_FILE, $codes);
    return ['success' => true];
}

function ccw_verify_otp(string $email, string $code): array {
    $email = strtolower(trim($email));
    $code = trim($code);
    $codes = ccw_read_json(CCW_OTP_FILE);
    $key = ccw_otp_key($email);
    $record = $codes[$key] ?? null;
    $now = time();

    if (!$record || ($record['expires_at'] ?? 0) <= $now) {
        unset($codes[$key]);
        ccw_write_json(CCW_OTP_FILE, $codes);
        return ['error' => 'That sign-in code has expired. Request a new one.'];
    }
    if (($record['attempts'] ?? 0) >= CCW_OTP_MAX_ATTEMPTS) {
        unset($codes[$key]);
        ccw_write_json(CCW_OTP_FILE, $codes);
        return ['error' => 'Too many attempts. Request a new sign-in code.'];
    }
    if (!preg_match('/^\d{6}$/', $code) || !password_verify($code, $record['code_hash'])) {
        $codes[$key]['attempts'] = ($record['attempts'] ?? 0) + 1;
        ccw_write_json(CCW_OTP_FILE, $codes);
        return ['error' => 'Invalid sign-in code.'];
    }

    unset($codes[$key]);
    ccw_write_json(CCW_OTP_FILE, $codes);
    $user = ccw_find_user_by_email($email);
    return $user ? ['user' => ccw_public_user($user)] : ['error' => 'Account not found.'];
}

function ccw_public_user(array $user): array {
    return [
        'username' => $user['username'],
        'email' => $user['email'],
        'campus' => $user['campus']
    ];
}

function ccw_create_token(string $email): string {
    $token = bin2hex(random_bytes(32));
    $tokens = ccw_read_json(CCW_TOKENS_FILE);
    $now = time();
    $tokens = array_filter($tokens, static fn(array $record): bool => ($record['expires_at'] ?? 0) > $now);
    $tokens[hash('sha256', $token)] = ['email' => $email, 'expires_at' => $now + CCW_TOKEN_TTL_SECONDS];
    ccw_write_json(CCW_TOKENS_FILE, $tokens);
    return $token;
}

function ccw_user_for_token(?string $token): ?array {
    if (empty($token)) return null;
    $tokens = ccw_read_json(CCW_TOKENS_FILE);
    $record = $tokens[hash('sha256', $token)] ?? null;
    if (!$record || ($record['expires_at'] ?? 0) <= time()) return null;

    foreach (ccw_read_json(CCW_USERS_FILE) as $user) {
        if (strcasecmp($user['email'] ?? '', $record['email']) === 0) return ccw_public_user($user);
    }
    return null;
}

function ccw_revoke_token(?string $token): void {
    if (empty($token)) return;
    $tokens = ccw_read_json(CCW_TOKENS_FILE);
    unset($tokens[hash('sha256', $token)]);
    ccw_write_json(CCW_TOKENS_FILE, $tokens);
}

function ccw_bearer_token(): ?string {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    return preg_match('/^Bearer\s+(.+)$/i', $header, $matches) === 1 ? trim($matches[1]) : null;
}