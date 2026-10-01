<?php
require __DIR__ . '/auth_lib.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'error'=>'Method not allowed.']);
    exit;
}
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) $data = $_POST;
$email = isset($data['email']) ? strtolower(trim($data['email'])) : '';
if ($email === '') {
    $token = ccw_bearer_token() ?? $_COOKIE['cc_token'] ?? null;
    $user = ccw_user_for_token($token);
    if ($user && isset($user['email'])) $email = strtolower(trim($user['email']));
}
if ($email === '' && isset($_SESSION)) $email = strtolower(trim($_SESSION['cc_email'] ?? ''));
if ($email === '') {
    // fallback to trying to find by session cookie via auth token header
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(.+)/i', $auth, $m)) {
        $u = ccw_user_for_token(trim($m[1]));
        if ($u) $email = strtolower(trim($u['email']));
    }
}
if ($email === '') {
    // as last resort, accept email from payload even if not authenticated — sessionStorage will still update client-side
    // but we require it
    echo json_encode(['success'=>false,'error'=>'Email is required.']);
    exit;
}
$fields = [];
foreach (['username','real_name','realName','bio','avatar','campus'] as $k) {
    if (array_key_exists($k, $data)) {
        $key = $k === 'realName' ? 'real_name' : $k;
        $fields[$key] = $data[$k];
    }
}
$result = ccw_update_profile($email, $fields);
if (isset($result['error'])) {
    http_response_code(422);
    echo json_encode(['success'=>false,'error'=>$result['error']]);
    exit;
}
echo json_encode(['success'=>true,'user'=>$result['user']]);
