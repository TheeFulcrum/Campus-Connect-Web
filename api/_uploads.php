<?php
// Shared secure image-upload helper.
// Stores files OUTSIDE the web root in a protected directory; files are
// served only through api/uploads/serve.php. Never trusts the client filename.
require_once __DIR__ . '/../auth_lib.php';

function ccw_upload_base_dir(): string {
    $dir = ccw_environment('CCW_UPLOAD_DIR');
    if ($dir === '') $dir = '/var/www/campusconnect-data/uploads';
    return rtrim($dir, '/');
}

/**
 * Validate, re-encode, resize, and store an uploaded image.
 *
 * @param array  $file      The $_FILES['...'] entry
 * @param string $subdir    Subdirectory inside the upload base, e.g. 'avatars' or 'listings'
 * @param int    $maxBytes  Max upload size in bytes
 * @param int    $maxDim    Max width/height after resize
 * @return array ['path' => relative path] or ['error' => message]
 */
function ccw_store_image_upload(array $file, string $subdir, int $maxBytes = 5242880, int $maxDim = 1600): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['error' => 'No file was uploaded or the upload failed.'];
    }
    if (($file['size'] ?? 0) <= 0 || $file['size'] > $maxBytes) {
        return ['error' => 'Image must be ' . round($maxBytes / 1048576) . 'MB or smaller.'];
    }

    $tmp = $file['tmp_name'];
    if (!is_uploaded_file($tmp)) {
        return ['error' => 'Invalid upload.'];
    }

    // Validate the real MIME type from file contents, never the client header.
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp) ?: '';
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    if (!isset($allowed[$mime])) {
        return ['error' => 'Only JPEG, PNG, WebP, or GIF images are allowed.'];
    }

    $info = @getimagesize($tmp);
    if ($info === false || $info[0] < 1 || $info[1] < 1) {
        return ['error' => 'The file is not a valid image.'];
    }

    if (!extension_loaded('gd')) {
        return ['error' => 'Image processing is not available on this server.'];
    }

    // Re-encode through GD: strips any embedded payloads and normalizes the format.
    $src = @imagecreatefromstring(file_get_contents($tmp));
    if ($src === false) {
        return ['error' => 'The image could not be processed.'];
    }

    $w = imagesx($src);
    $h = imagesy($src);
    $scale = min(1.0, $maxDim / max($w, $h));
    $nw = max(1, (int) round($w * $scale));
    $nh = max(1, (int) round($h * $scale));

    $dst = imagecreatetruecolor($nw, $nh);
    if ($dst === false) {
        imagedestroy($src);
        return ['error' => 'The image could not be processed.'];
    }
    // Flatten transparency onto white for formats that support it.
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefill($dst, 0, 0, $white);
    imagealphablending($dst, true);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

    $safeSubdir = preg_replace('/[^a-z0-9_-]/i', '', $subdir);
    $dir = ccw_upload_base_dir() . '/' . $safeSubdir;
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
        imagedestroy($src);
        imagedestroy($dst);
        return ['error' => 'Upload storage is unavailable.'];
    }

    // Random server-side filename; client name is never used.
    $name = bin2hex(random_bytes(16)) . '.jpg';
    $dest = $dir . '/' . $name;

    $ok = imagejpeg($dst, $dest, 85);
    imagedestroy($src);
    imagedestroy($dst);

    if (!$ok) {
        return ['error' => 'The image could not be saved.'];
    }
    chmod($dest, 0640);

    return ['path' => $safeSubdir . '/' . $name];
}

/**
 * Build the public serving URL for a stored relative upload path.
 */
function ccw_upload_url(string $relativePath): string {
    return '/api/uploads/serve.php?f=' . rawurlencode($relativePath);
}

/**
 * Best-effort delete of a previously stored upload (relative path only).
 */
function ccw_delete_upload(?string $relativePath): void {
    $relativePath = (string) $relativePath;
    if ($relativePath === '' || !preg_match('#^[a-z0-9_-]+/[a-f0-9]{32}\.(jpg|jpeg|png|webp|gif)$#i', $relativePath)) return;
    $base = realpath(ccw_upload_base_dir());
    if ($base === false) return;
    $full = realpath($base . '/' . $relativePath);
    if ($full === false || !str_starts_with($full, $base . '/')) return;
    if (is_file($full)) @unlink($full);
}
