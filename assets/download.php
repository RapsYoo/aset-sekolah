<?php
require_once '../inc/auth.php';
require_once '../inc/helpers.php';
require_once '../inc/storage.php';
require_login();
$key = sanitize($_GET['key'] ?? '');
if (empty($key)) {
    http_response_code(400);
    die('Bad Request');
}
$full = storage_get_full_path($key);
if (!file_exists($full)) {
    http_response_code(404);
    die('File tidak ditemukan');
}
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $full);
finfo_close($finfo);
header('Content-Type: ' . ($mime ?: 'application/octet-stream'));
header('Content-Length: ' . filesize($full));
header('Content-Disposition: attachment; filename="' . basename($full) . '"');
readfile($full);
exit();
