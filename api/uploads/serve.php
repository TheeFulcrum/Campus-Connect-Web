<?php
// Controlled serving of user uploads stored outside the web root.
require __DIR__ . '/../../auth_lib.php';
ccw_load_environment();
require_once __DIR__ . '/../_uploads.php';

$f = (string) ($_GET['f'] ?? '');

// Only allow paths produced by ccw_store_image_upload(): subdir/32-hex-chars.ext
if (!preg_match('#^[a-z0-9_-]+/[a-f0-9]{32}\.(jpg|jpeg|png|webp|gif)$#i', $f)) {
    http_response_code(400);
    exit;
}

$base = realpath(ccw_upload_base_dir());
if ($base === false) {
    http_response_code(404);
    exit;
}

$path = realpath($base . '/' . $f);
if ($path === false || !str_starts_with($path, $base . '/') || !is_file($path)) {
    http_response_code(404);
    exit;
}

// Re-verify the real content type before serving; never trust the extension.
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($path) ?: '';
$allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
if (!in_array($mime, $allowed, true)) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: inline');
header('Cache-Control: private, max-age=86400');
readfile($path);
