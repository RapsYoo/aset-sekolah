<?php
require_once __DIR__ . '/config.php';
function storage_base_path() {
    $base = BASE_PATH . DIRECTORY_SEPARATOR . 'storage';
    if (!is_dir($base)) {
        @mkdir($base, 0777, true);
    }
    return $base;
}
function storage_is_configured() {
    $driver = config('storage_driver') ?? 'local';
    if ($driver === 'local') return true;
    return !empty(config('aws_bucket')) && !empty(config('aws_access_key_id')) && !empty(config('aws_secret_access_key'));
}
function storage_get_full_path($key) {
    $key = str_replace(['..', '\\'], ['', '/'], $key);
    $full = storage_base_path() . DIRECTORY_SEPARATOR . $key;
    $dir = dirname($full);
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    return $full;
}
function storage_put($key, $filePath, $mime) {
    $full = storage_get_full_path($key);
    if (is_uploaded_file($filePath)) {
        return move_uploaded_file($filePath, $full);
    }
    return copy($filePath, $full);
}
function storage_signed_url($key, $expiresSeconds = 1200) {
    return APP_URL . "/assets/download.php?key=" . urlencode($key);
}
function storage_delete($key) {
    $full = storage_get_full_path($key);
    if (file_exists($full)) {
        return unlink($full);
    }
    return false;
}
function storage_list_documents($year, $kib, $unitCode) {
    $folder = sprintf('documents/%d/%s/%s', (int)$year, $kib, $unitCode);
    $dir = storage_get_full_path($folder);
    $items = [];
    if (is_dir($dir)) {
        $files = scandir($dir);
        foreach ($files as $f) {
            if ($f === '.' || $f === '..') continue;
            $full = $dir . DIRECTORY_SEPARATOR . $f;
            if (is_file($full)) {
                $items[] = [
                    'key' => $folder . '/' . $f,
                    'name' => $f,
                    'size' => filesize($full),
                    'mtime' => filemtime($full)
                ];
            }
        }
    }
    usort($items, function($a, $b) { return ($b['mtime'] <=> $a['mtime']); });
    return $items;
}
?>
