<?php

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

function ccw_data_file(string $name): ?string {
    static $directory = null;
    static $available = null;

    if (!in_array($name, ['users.json', 'auth_tokens.json', 'otp_codes.json', 'waitlist.csv'], true)) return null;
    if ($available === null) {
        ccw_load_environment();
        $documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? __DIR__);
        $configuredDirectory = ccw_environment('CCW_DATA_DIR');
        $directory = $configuredDirectory !== ''
            ? $configuredDirectory
            : dirname($documentRoot !== false ? $documentRoot : __DIR__) . DIRECTORY_SEPARATOR . 'campus-connect-data';
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            $available = false;
        } else {
            @chmod($directory, 0700);
            $directory = realpath($directory);
            $documentRootPrefix = $documentRoot !== false ? rtrim($documentRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR : '';
            $available = $directory !== false && ($documentRoot === false || ($directory !== $documentRoot && !str_starts_with($directory . DIRECTORY_SEPARATOR, $documentRootPrefix)));
        }
    }
    if (!$available) return null;

    $path = $directory . DIRECTORY_SEPARATOR . $name;
    $legacyPath = __DIR__ . DIRECTORY_SEPARATOR . $name;
    if (!file_exists($path) && is_file($legacyPath)) {
        if (!@rename($legacyPath, $path)) return null;
        @chmod($path, 0600);
    }
    return $path;
}

function ccw_read_json(?string $path): array {
    if ($path === null || !is_file($path)) {
        return [];
    }

    $contents = @file_get_contents($path);
    if (!is_string($contents)) return [];
    $data = json_decode($contents, true);
    return is_array($data) ? $data : [];
}

function ccw_write_json(?string $path, array $data): bool {
    if ($path === null) return false;
    $contents = json_encode($data, JSON_PRETTY_PRINT);
    if ($contents === false || @file_put_contents($path, $contents, LOCK_EX) === false) return false;
    @chmod($path, 0600);
    return true;
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

    $users = ccw_read_json(ccw_data_file('users.json'));
    foreach ($users as $user) {
        if (strcasecmp($user['username'] ?? '', $username) === 0) return ['error' => 'That username is already taken.'];
        if (strcasecmp($user['email'] ?? '', $email) === 0) return ['error' => 'An account with this email already exists.'];
    }

    $user = [
        'username' => $username,
        'email' => $email,
        'campus' => $campus,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'email_verified' => false,
        'created_at' => date('c')
    ];
    $users[] = $user;

    return ccw_write_json(ccw_data_file('users.json'), $users)
        ? ['user' => ccw_public_user($user)]
        : ['error' => 'Unable to create the account. Please try again.'];
}

function ccw_authenticate_user(string $identifier, string $password): array {
    $identifier = trim($identifier);
    $users = ccw_read_json(ccw_data_file('users.json'));

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
        if ($needsSave && !ccw_write_json(ccw_data_file('users.json'), $users)) return ['error' => 'Unable to update account security.'];
        if (empty($user['email_verified'])) return ['error' => 'Verify your email with a sign-in code before logging in.'];
        return ['user' => ccw_public_user($user)];
    }

    return ['error' => 'Invalid email or password.'];
}

function ccw_find_user_by_email(string $email): ?array {
    foreach (ccw_read_json(ccw_data_file('users.json')) as $user) {
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

    $codes = ccw_read_json(ccw_data_file('otp_codes.json'));
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
    if (!ccw_write_json(ccw_data_file('otp_codes.json'), $codes)) {
        return ['error' => 'Unable to save the sign-in code. Please try again.'];
    }
    return ['success' => true];
}

function ccw_verify_otp(string $email, string $code): array {
    $email = strtolower(trim($email));
    $code = trim($code);
    $codes = ccw_read_json(ccw_data_file('otp_codes.json'));
    $key = ccw_otp_key($email);
    $record = $codes[$key] ?? null;
    $now = time();

    if (!$record || ($record['expires_at'] ?? 0) <= $now) {
        unset($codes[$key]);
        ccw_write_json(ccw_data_file('otp_codes.json'), $codes);
        return ['error' => 'That sign-in code has expired. Request a new one.'];
    }
    if (($record['attempts'] ?? 0) >= CCW_OTP_MAX_ATTEMPTS) {
        unset($codes[$key]);
        ccw_write_json(ccw_data_file('otp_codes.json'), $codes);
        return ['error' => 'Too many attempts. Request a new sign-in code.'];
    }
    if (!preg_match('/^\d{6}$/', $code) || !password_verify($code, $record['code_hash'])) {
        $codes[$key]['attempts'] = ($record['attempts'] ?? 0) + 1;
        if (!ccw_write_json(ccw_data_file('otp_codes.json'), $codes)) {
            return ['error' => 'Unable to update the sign-in code. Please try again.'];
        }
        return ['error' => 'Invalid sign-in code.'];
    }

    unset($codes[$key]);
    if (!ccw_write_json(ccw_data_file('otp_codes.json'), $codes)) {
        return ['error' => 'Unable to verify the sign-in code. Please try again.'];
    }
    $users = ccw_read_json(ccw_data_file('users.json'));
    foreach ($users as $index => $user) {
        if (strcasecmp($user['email'] ?? '', $email) !== 0) continue;
        $users[$index]['email_verified'] = true;
        if (!ccw_write_json(ccw_data_file('users.json'), $users)) {
            return ['error' => 'Unable to verify the account. Request a new sign-in code and try again.'];
        }
        return ['user' => ccw_public_user($users[$index])];
    }
    return ['error' => 'Account not found.'];
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
    $tokens = ccw_read_json(ccw_data_file('auth_tokens.json'));
    $now = time();
    $tokens = array_filter($tokens, static fn(array $record): bool => ($record['expires_at'] ?? 0) > $now);
    $tokens[hash('sha256', $token)] = ['email' => $email, 'expires_at' => $now + CCW_TOKEN_TTL_SECONDS];
    ccw_write_json(ccw_data_file('auth_tokens.json'), $tokens);
    return $token;
}

function ccw_user_for_token(?string $token): ?array {
    if (empty($token)) return null;
    $tokens = ccw_read_json(ccw_data_file('auth_tokens.json'));
    $record = $tokens[hash('sha256', $token)] ?? null;
    if (!$record || ($record['expires_at'] ?? 0) <= time()) return null;

    foreach (ccw_read_json(ccw_data_file('users.json')) as $user) {
        if (strcasecmp($user['email'] ?? '', $record['email']) === 0) return ccw_public_user($user);
    }
    return null;
}

function ccw_revoke_token(?string $token): void {
    if (empty($token)) return;
    $tokens = ccw_read_json(ccw_data_file('auth_tokens.json'));
    unset($tokens[hash('sha256', $token)]);
    ccw_write_json(ccw_data_file('auth_tokens.json'), $tokens);
}

function ccw_bearer_token(): ?string {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    return preg_match('/^Bearer\s+(.+)$/i', $header, $matches) === 1 ? trim($matches[1]) : null;
}