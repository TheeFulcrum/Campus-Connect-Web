<?php

const CCW_USERS_FILE = __DIR__ . '/users.json';
const CCW_TOKENS_FILE = __DIR__ . '/auth_tokens.json';
const CCW_OTP_FILE = __DIR__ . '/otp_codes.json';
const CCW_DB_PATH = __DIR__ . '/ccw.sqlite';
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

function ccw_use_json_auth_store(): bool {
    static $useJsonStore = null;
    if (is_bool($useJsonStore)) {
        return $useJsonStore;
    }

    if (!class_exists('PDO')) {
        $useJsonStore = true;
        return true;
    }

    $useJsonStore = !in_array('sqlite', PDO::getAvailableDrivers(), true);
    return $useJsonStore;
}

function ccw_db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $pdo = new PDO('sqlite:' . CCW_DB_PATH, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $pdo->exec('CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        email TEXT NOT NULL UNIQUE,
        campus TEXT NOT NULL,
        password TEXT NOT NULL,
        created_at TEXT NOT NULL,
        real_name TEXT NOT NULL DEFAULT "",
        bio TEXT NOT NULL DEFAULT "",
        avatar TEXT NOT NULL DEFAULT ""
    );');
    // Ensure new columns exist on legacy DBs
    foreach (['real_name', 'bio', 'avatar'] as $col) {
        try { $pdo->exec("ALTER TABLE users ADD COLUMN $col TEXT NOT NULL DEFAULT ''"); } catch (Throwable $e) {}
    }

    $pdo->exec('CREATE TABLE IF NOT EXISTS tokens (
        token_hash TEXT PRIMARY KEY,
        email TEXT NOT NULL,
        expires_at INTEGER NOT NULL
    );');

    $pdo->exec('CREATE TABLE IF NOT EXISTS otp_codes (
        otp_key TEXT PRIMARY KEY,
        email TEXT NOT NULL,
        code_hash TEXT NOT NULL,
        sent_at INTEGER NOT NULL,
        expires_at INTEGER NOT NULL,
        attempts INTEGER NOT NULL DEFAULT 0
    );');

    $pdo->exec('CREATE TABLE IF NOT EXISTS password_reset_tokens (
        token TEXT PRIMARY KEY,
        email TEXT NOT NULL,
        token_hash TEXT NOT NULL,
        expires_at INTEGER NOT NULL,
        used INTEGER DEFAULT 0,
        created_at TEXT NOT NULL
    );');

    $pdo->exec('CREATE TABLE IF NOT EXISTS listings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        description TEXT NOT NULL,
        category TEXT NOT NULL,
        campus TEXT NOT NULL,
        price REAL,
        status TEXT NOT NULL DEFAULT "active",
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    );');

    $pdo->exec('CREATE TABLE IF NOT EXISTS listing_images (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        listing_id INTEGER NOT NULL,
        image_url TEXT NOT NULL,
        display_order INTEGER DEFAULT 0,
        created_at TEXT NOT NULL,
        FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
    );');

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_tokens_email ON tokens(email);');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_otp_email ON otp_codes(email);');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_password_reset_email ON password_reset_tokens(email);');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_listings_user_id ON listings(user_id);');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_listings_campus ON listings(campus);');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_listings_category ON listings(category);');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_listing_images_listing_id ON listing_images(listing_id);');

    ccw_migrate_legacy_data($pdo);
    return $pdo;
}

function ccw_migrate_legacy_data(PDO $pdo): void {
    $legacyUsers = ccw_read_json(CCW_USERS_FILE);
    if (!empty($legacyUsers)) {
        $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        if ($count === 0) {
            foreach ($legacyUsers as $user) {
                if (empty($user['email'] ?? '') || empty($user['password'] ?? '')) continue;
                $stmt = $pdo->prepare('INSERT OR IGNORE INTO users (username, email, campus, real_name, bio, avatar, password, created_at) VALUES (:username, :email, :campus, :real_name, :bio, :avatar, :password, :created_at)');
                $stmt->execute([
                    'username' => $user['username'] ?? '',
                    'email' => strtolower(trim((string) ($user['email'] ?? ''))),
                    'campus' => $user['campus'] ?? '',
                    'real_name' => $user['real_name'] ?? '',
                    'bio' => $user['bio'] ?? '',
                    'avatar' => $user['avatar'] ?? '',
                    'password' => $user['password'] ?? '',
                    'created_at' => $user['created_at'] ?? date('c'),
                ]);
            }
        }
    }

    $legacyTokens = ccw_read_json(CCW_TOKENS_FILE);
    if (!empty($legacyTokens)) {
        $count = (int) $pdo->query('SELECT COUNT(*) FROM tokens')->fetchColumn();
        if ($count === 0) {
            foreach ($legacyTokens as $tokenHash => $record) {
                $stmt = $pdo->prepare('INSERT OR IGNORE INTO tokens (token_hash, email, expires_at) VALUES (:token_hash, :email, :expires_at)');
                $stmt->execute([
                    'token_hash' => $tokenHash,
                    'email' => strtolower(trim((string) ($record['email'] ?? ''))),
                    'expires_at' => (int) ($record['expires_at'] ?? 0),
                ]);
            }
        }
    }

    $legacyOtp = ccw_read_json(CCW_OTP_FILE);
    if (!empty($legacyOtp)) {
        $count = (int) $pdo->query('SELECT COUNT(*) FROM otp_codes')->fetchColumn();
        if ($count === 0) {
            foreach ($legacyOtp as $otpKey => $record) {
                $stmt = $pdo->prepare('INSERT OR IGNORE INTO otp_codes (otp_key, email, code_hash, sent_at, expires_at, attempts) VALUES (:otp_key, :email, :code_hash, :sent_at, :expires_at, :attempts)');
                $stmt->execute([
                    'otp_key' => $otpKey,
                    'email' => strtolower(trim((string) ($record['email'] ?? ''))),
                    'code_hash' => $record['code_hash'] ?? '',
                    'sent_at' => (int) ($record['sent_at'] ?? 0),
                    'expires_at' => (int) ($record['expires_at'] ?? 0),
                    'attempts' => (int) ($record['attempts'] ?? 0),
                ]);
            }
        }
    }
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

function ccw_register_user(string $username, string $email, string $campus, string $password, string $realName = '', string $bio = '', string $avatar = ''): array {
    $username = trim($username);
    $email = strtolower(trim($email));
    $campus = trim($campus);
    $realName = trim($realName);
    $bio = trim($bio);
    $avatar = trim($avatar);
    if ($avatar !== '' && strlen($avatar) > 500000) $avatar = '';

    if (strlen($username) < 3) return ['error' => 'Username must be at least 3 characters.'];
    if (!ccw_valid_email($email)) return ['error' => 'Use your 10-digit ID@edenuniversity.education email.'];
    if (strlen($password) < 8) return ['error' => 'Password must be at least 8 characters.'];
    if ($campus === '') return ['error' => 'Campus is required.'];

    if (ccw_use_json_auth_store()) {
        $users = ccw_read_json(CCW_USERS_FILE);
        foreach ($users as $user) {
            if (strcasecmp($user['username'] ?? '', $username) === 0) return ['error' => 'That username is already taken.'];
            if (strcasecmp($user['email'] ?? '', $email) === 0) return ['error' => 'An account with this email already exists.'];
        }

        $user = [
            'username' => $username,
            'email' => $email,
            'campus' => $campus,
            'real_name' => $realName,
            'bio' => $bio,
            'avatar' => $avatar,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'created_at' => date('c')
        ];
        $users[] = $user;

        return ccw_write_json(CCW_USERS_FILE, $users)
            ? ['user' => ccw_public_user($user)]
            : ['error' => 'Unable to create the account. Please try again.'];
    }

    $pdo = ccw_db();
    $stmt = $pdo->prepare('SELECT 1 FROM users WHERE LOWER(username) = LOWER(:username) OR LOWER(email) = LOWER(:email) LIMIT 1');
    $stmt->execute(['username' => $username, 'email' => $email]);
    if ($stmt->fetch()) {
        return ['error' => 'An account with this email already exists.'];
    }

    $user = [
        'username' => $username,
        'email' => $email,
        'campus' => $campus,
        'real_name' => $realName,
        'bio' => $bio,
        'avatar' => $avatar,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'created_at' => date('c')
    ];

    $insert = $pdo->prepare('INSERT INTO users (username, email, campus, real_name, bio, avatar, password, created_at) VALUES (:username, :email, :campus, :real_name, :bio, :avatar, :password, :created_at)');
    $insert->execute([
        'username' => $username,
        'email' => $email,
        'campus' => $campus,
        'real_name' => $realName,
        'bio' => $bio,
        'avatar' => $avatar,
        'password' => $user['password'],
        'created_at' => $user['created_at'],
    ]);

    return ['user' => ccw_public_user($user)];
}

function ccw_authenticate_user(string $identifier, string $password): array {
    $identifier = trim($identifier);

    if (ccw_use_json_auth_store()) {
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

    $pdo = ccw_db();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE LOWER(username) = LOWER(:identifier) OR LOWER(email) = LOWER(:identifier) LIMIT 1');
    $stmt->execute(['identifier' => $identifier]);
    $user = $stmt->fetch();

    if (!$user) {
        return ['error' => 'Invalid email or password.'];
    }

    $storedPassword = (string) ($user['password'] ?? '');
    $verified = password_verify($password, $storedPassword);
    $needsSave = false;

    if (!$verified && hash_equals($storedPassword, $password)) {
        $verified = true;
        $needsSave = true;
    }

    if ($verified && password_needs_rehash($storedPassword, PASSWORD_DEFAULT)) {
        $needsSave = true;
    }

    if (!$verified) {
        return ['error' => 'Invalid email or password.'];
    }

    if ($needsSave) {
        $update = $pdo->prepare('UPDATE users SET password = :password WHERE id = :id');
        $update->execute(['password' => password_hash($password, PASSWORD_DEFAULT), 'id' => (int) $user['id']]);
    }

    return ['user' => ccw_public_user($user)];
}

function ccw_find_user_by_email(string $email): ?array {
    if (ccw_use_json_auth_store()) {
        foreach (ccw_read_json(CCW_USERS_FILE) as $user) {
            if (strcasecmp($user['email'] ?? '', $email) === 0) return $user;
        }
        return null;
    }

    $pdo = ccw_db();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE LOWER(email) = LOWER(:email) LIMIT 1');
    $stmt->execute(['email' => strtolower(trim($email))]);
    $user = $stmt->fetch();
    return $user ?: null;
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

    if (ccw_use_json_auth_store()) {
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

    $pdo = ccw_db();
    $key = ccw_otp_key($email);
    $now = time();
    $existing = $pdo->prepare('SELECT * FROM otp_codes WHERE otp_key = :otp_key LIMIT 1');
    $existing->execute(['otp_key' => $key]);
    $record = $existing->fetch();
    if ($record && ((int) $record['sent_at']) + CCW_OTP_RESEND_SECONDS > $now) {
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

    $upsert = $pdo->prepare('INSERT INTO otp_codes (otp_key, email, code_hash, sent_at, expires_at, attempts) VALUES (:otp_key, :email, :code_hash, :sent_at, :expires_at, 0) ON CONFLICT(otp_key) DO UPDATE SET email = excluded.email, code_hash = excluded.code_hash, sent_at = excluded.sent_at, expires_at = excluded.expires_at, attempts = 0');
    $upsert->execute([
        'otp_key' => $key,
        'email' => $email,
        'code_hash' => password_hash($code, PASSWORD_DEFAULT),
        'sent_at' => $now,
        'expires_at' => $now + CCW_OTP_TTL_SECONDS,
    ]);

    return ['success' => true];
}

function ccw_verify_otp(string $email, string $code): array {
    $email = strtolower(trim($email));
    $code = trim($code);

    if (ccw_use_json_auth_store()) {
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

    $pdo = ccw_db();
    $stmt = $pdo->prepare('SELECT * FROM otp_codes WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $record = $stmt->fetch();
    $now = time();

    if (!$record || ((int) $record['expires_at']) <= $now) {
        $pdo->prepare('DELETE FROM otp_codes WHERE email = :email')->execute(['email' => $email]);
        return ['error' => 'That sign-in code has expired. Request a new one.'];
    }

    if ((int) ($record['attempts'] ?? 0) >= CCW_OTP_MAX_ATTEMPTS) {
        $pdo->prepare('DELETE FROM otp_codes WHERE email = :email')->execute(['email' => $email]);
        return ['error' => 'Too many attempts. Request a new sign-in code.'];
    }

    if (!preg_match('/^\d{6}$/', $code) || !password_verify($code, $record['code_hash'])) {
        $attempts = (int) $record['attempts'] + 1;
        $update = $pdo->prepare('UPDATE otp_codes SET attempts = :attempts WHERE email = :email');
        $update->execute(['attempts' => $attempts, 'email' => $email]);
        return ['error' => 'Invalid sign-in code.'];
    }

    $pdo->prepare('DELETE FROM otp_codes WHERE email = :email')->execute(['email' => $email]);
    $user = ccw_find_user_by_email($email);
    return $user ? ['user' => ccw_public_user($user)] : ['error' => 'Account not found.'];
}

function ccw_public_user(array $user): array {
    return [
        'username' => $user['username'] ?? '',
        'email' => $user['email'] ?? '',
        'campus' => $user['campus'] ?? '',
        'real_name' => $user['real_name'] ?? '',
        'bio' => $user['bio'] ?? '',
        'avatar' => $user['avatar'] ?? ''
    ];
}
function ccw_update_profile(string $email, array $fields): array {
    $email = strtolower(trim($email));
    $realName = trim((string) ($fields['real_name'] ?? ''));
    $bio = trim((string) ($fields['bio'] ?? ''));
    $avatar = trim((string) ($fields['avatar'] ?? ''));
    $campus = trim((string) ($fields['campus'] ?? ''));
    $username = trim((string) ($fields['username'] ?? ''));
    if ($avatar !== '' && strlen($avatar) > 500000) $avatar = '';
    if ($username !== '' && strlen($username) < 3) return ['error' => 'Username must be at least 3 characters.'];
    if (ccw_use_json_auth_store()) {
        $users = ccw_read_json(CCW_USERS_FILE);
        foreach ($users as $i => $u) {
            if (strcasecmp($u['email'] ?? '', $email) !== 0) continue;
            if ($username !== '' && strcasecmp($u['username'] ?? '', $username) !== 0) {
                foreach ($users as $other) { if ($other !== $u && strcasecmp($other['username'] ?? '', $username) === 0) return ['error' => 'That username is already taken.']; }
                $users[$i]['username'] = $username;
            }
            if ($realName !== '') $users[$i]['real_name'] = $realName; elseif (array_key_exists('real_name', $fields)) $users[$i]['real_name'] = '';
            if (array_key_exists('bio', $fields)) $users[$i]['bio'] = $bio;
            if (array_key_exists('avatar', $fields)) $users[$i]['avatar'] = $avatar;
            if ($campus !== '') $users[$i]['campus'] = $campus;
            ccw_write_json(CCW_USERS_FILE, $users);
            return ['user' => ccw_public_user($users[$i])];
        }
        return ['error' => 'Account not found.'];
    }
    $pdo = ccw_db();
    $user = ccw_find_user_by_email($email);
    if (!$user) return ['error' => 'Account not found.'];
    if ($username !== '' && strcasecmp($user['username'] ?? '', $username) !== 0) {
        $chk = $pdo->prepare('SELECT 1 FROM users WHERE LOWER(username)=LOWER(:u) AND LOWER(email)!=LOWER(:e) LIMIT 1');
        $chk->execute(['u' => $username, 'e' => $email]);
        if ($chk->fetch()) return ['error' => 'That username is already taken.'];
    }
    $updates = [];
    $params = ['email' => $email];
    if ($username !== '') { $updates[] = 'username = :username'; $params['username'] = $username; }
    if (array_key_exists('real_name', $fields)) { $updates[] = 'real_name = :real_name'; $params['real_name'] = $realName; }
    if (array_key_exists('bio', $fields)) { $updates[] = 'bio = :bio'; $params['bio'] = $bio; }
    if (array_key_exists('avatar', $fields)) { $updates[] = 'avatar = :avatar'; $params['avatar'] = $avatar; }
    if ($campus !== '') { $updates[] = 'campus = :campus'; $params['campus'] = $campus; }
    if ($updates) {
        $pdo->prepare('UPDATE users SET ' . implode(', ', $updates) . ' WHERE LOWER(email)=LOWER(:email)')->execute($params);
    }
    $fresh = ccw_find_user_by_email($email);
    return $fresh ? ['user' => ccw_public_user($fresh)] : ['error' => 'Account not found.'];
}

function ccw_create_token(string $email): string {
    $token = bin2hex(random_bytes(32));

    if (ccw_use_json_auth_store()) {
        $tokens = ccw_read_json(CCW_TOKENS_FILE);
        $now = time();
        $tokens = array_filter($tokens, static fn(array $record): bool => ($record['expires_at'] ?? 0) > $now);
        $tokens[hash('sha256', $token)] = ['email' => $email, 'expires_at' => $now + CCW_TOKEN_TTL_SECONDS];
        ccw_write_json(CCW_TOKENS_FILE, $tokens);
        return $token;
    }

    $pdo = ccw_db();
    $now = time();
    $pdo->prepare('DELETE FROM tokens WHERE expires_at <= :now')->execute(['now' => $now]);
    $stmt = $pdo->prepare('INSERT INTO tokens (token_hash, email, expires_at) VALUES (:token_hash, :email, :expires_at) ON CONFLICT(token_hash) DO UPDATE SET email = excluded.email, expires_at = excluded.expires_at');
    $stmt->execute([
        'token_hash' => hash('sha256', $token),
        'email' => strtolower(trim($email)),
        'expires_at' => $now + CCW_TOKEN_TTL_SECONDS,
    ]);
    return $token;
}

function ccw_user_for_token(?string $token): ?array {
    if (empty($token)) return null;

    if (ccw_use_json_auth_store()) {
        $tokens = ccw_read_json(CCW_TOKENS_FILE);
        $record = $tokens[hash('sha256', $token)] ?? null;
        if (!$record || ($record['expires_at'] ?? 0) <= time()) return null;

        foreach (ccw_read_json(CCW_USERS_FILE) as $user) {
            if (strcasecmp($user['email'] ?? '', $record['email']) === 0) return ccw_public_user($user);
        }
        return null;
    }

    $pdo = ccw_db();
    $stmt = $pdo->prepare('SELECT * FROM tokens WHERE token_hash = :token_hash AND expires_at > :now LIMIT 1');
    $stmt->execute(['token_hash' => hash('sha256', $token), 'now' => time()]);
    $record = $stmt->fetch();
    if (!$record) return null;

    $user = ccw_find_user_by_email($record['email']);
    return $user ? ccw_public_user($user) : null;
}

function ccw_revoke_token(?string $token): void {
    if (empty($token)) return;

    if (ccw_use_json_auth_store()) {
        $tokens = ccw_read_json(CCW_TOKENS_FILE);
        unset($tokens[hash('sha256', $token)]);
        ccw_write_json(CCW_TOKENS_FILE, $tokens);
        return;
    }

    $pdo = ccw_db();
    $pdo->prepare('DELETE FROM tokens WHERE token_hash = :token_hash')->execute(['token_hash' => hash('sha256', $token)]);
}

function ccw_bearer_token(): ?string {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    return preg_match('/^Bearer\s+(.+)$/i', $header, $matches) === 1 ? trim($matches[1]) : null;
}

function ccw_verify_token_and_get_user(?string $token): ?array {
    if (empty($token)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not authenticated.']);
        exit;
    }

    $pdo = ccw_db();
    $tokenStmt = $pdo->prepare('SELECT email FROM tokens WHERE token_hash = :hash AND expires_at > :now');
    $tokenStmt->execute(['hash' => hash('sha256', $token), 'now' => time()]);
    $tokenRow = $tokenStmt->fetch();

    if (!$tokenRow) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid or expired token.']);
        exit;
    }

    $userStmt = $pdo->prepare('SELECT id FROM users WHERE email = :email');
    $userStmt->execute(['email' => $tokenRow['email']]);
    $user = $userStmt->fetch();

    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'User not found.']);
        exit;
    }

    return $user;
}

/**
 * Generate a password reset token for a user email
 * Returns an array with the reset token or error message
 */
function ccw_create_password_reset_token(string $email): array {
    $email = strtolower(trim($email));

    $pdo = ccw_db();
    $stmt = $pdo->prepare('SELECT id FROM users WHERE LOWER(email) = LOWER(:email) LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if (!$user) {
        // For security, don't reveal if email exists
        return ['success' => true, 'message' => 'If an account with that email exists, a password reset link will be sent.'];
    }

    // Generate a secure random token
    $resetToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $resetToken);
    $expiresAt = time() + (60 * 60); // 1 hour expiration

    // Store in database
    try {
        $stmt = $pdo->prepare('INSERT INTO password_reset_tokens (token, email, token_hash, expires_at, created_at) VALUES (:token, :email, :token_hash, :expires_at, :created_at)');
        $stmt->execute([
            'token' => $resetToken,
            'email' => $email,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
            'created_at' => date('c'),
        ]);
    } catch (Throwable $e) {
        return ['success' => false, 'error' => 'Unable to generate reset token.'];
    }

    // In production, you would send this via email
    // For now, return the token to be used in reset link
    return [
        'success' => true,
        'message' => 'If an account with that email exists, a password reset link will be sent.',
        'reset_token' => $resetToken, // In production, send via email only
        'reset_url' => 'reset-password.html?token=' . urlencode($resetToken),
    ];
}

/**
 * Verify a password reset token and return user info
 */
function ccw_verify_reset_token(string $resetToken): array {
    if (empty($resetToken)) {
        return ['success' => false, 'error' => 'Invalid reset token.'];
    }

    $tokenHash = hash('sha256', $resetToken);
    $pdo = ccw_db();

    $stmt = $pdo->prepare('SELECT token, email, expires_at, used FROM password_reset_tokens WHERE token_hash = :token_hash LIMIT 1');
    $stmt->execute(['token_hash' => $tokenHash]);
    $tokenRecord = $stmt->fetch();

    if (!$tokenRecord) {
        return ['success' => false, 'error' => 'Invalid or expired reset token.'];
    }

    if ((int) $tokenRecord['used'] === 1) {
        return ['success' => false, 'error' => 'This reset token has already been used.'];
    }

    if (time() > (int) $tokenRecord['expires_at']) {
        return ['success' => false, 'error' => 'Reset token has expired. Please request a new one.'];
    }

    return [
        'success' => true,
        'email' => $tokenRecord['email'],
        'token' => $resetToken,
    ];
}

/**
 * Reset password using a valid reset token
 */
function ccw_reset_password_with_token(string $resetToken, string $newPassword): array {
    $tokenVerification = ccw_verify_reset_token($resetToken);
    if (!$tokenVerification['success']) {
        return $tokenVerification;
    }

    $email = $tokenVerification['email'];

    // Validate password strength
    if (strlen($newPassword) < 8) {
        return ['success' => false, 'error' => 'Password must be at least 8 characters long.'];
    }

    $pdo = ccw_db();

    // Find user by email
    $stmt = $pdo->prepare('SELECT id FROM users WHERE LOWER(email) = LOWER(:email) LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if (!$user) {
        return ['success' => false, 'error' => 'User not found.'];
    }

    // Update password
    try {
        $update = $pdo->prepare('UPDATE users SET password = :password WHERE id = :id');
        $update->execute([
            'password' => password_hash($newPassword, PASSWORD_DEFAULT),
            'id' => (int) $user['id'],
        ]);

        // Mark token as used
        $tokenHash = hash('sha256', $resetToken);
        $markUsed = $pdo->prepare('UPDATE password_reset_tokens SET used = 1 WHERE token_hash = :token_hash');
        $markUsed->execute(['token_hash' => $tokenHash]);

        return [
            'success' => true,
            'message' => 'Password has been reset successfully. You can now log in with your new password.',
        ];
    } catch (Throwable $e) {
        return ['success' => false, 'error' => 'Unable to reset password. Please try again.'];
    }
}
}