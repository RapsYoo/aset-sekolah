<?php
require_once '../inc/auth.php';
require_once '../inc/config.php';

require_login();

// Disable buffering to stream output
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no'); // Nginx
if(function_exists('apache_setenv')){
    @apache_setenv('no-gzip', 1);
}
@ini_set('zlib.output_compression', 0);
@ini_set('implicit_flush', 1);
for ($i = 0; $i < ob_get_level(); $i++) { ob_end_flush(); }
ob_implicit_flush(1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid method']) . "\n";
    exit;
}

$kib = $_POST['kib'] ?? '';
$unit = $_POST['unit'] ?? '';
$date = $_POST['date'] ?? '';
$type = $_POST['type'] ?? '';
$code = $_POST['code'] ?? '';

if (empty($kib) || empty($unit) || empty($date)) {
    echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap']) . "\n";
    exit;
}

// Prepare command
// Pastikan python3 tersedia dan path script benar
$scriptPath = BASE_PATH . '/automation/kib_headless.py';
$outputDir = BASE_PATH . '/storage/downloads';

if (!file_exists($outputDir)) {
    mkdir($outputDir, 0777, true);
}

// Escape arguments
$cmd = "python3 " . escapeshellarg($scriptPath) .
       " --kib " . escapeshellarg($kib) .
       " --unit " . escapeshellarg($unit) .
       " --date " . escapeshellarg($date) .
       " --output " . escapeshellarg($outputDir);

if (!empty($type)) $cmd .= " --type " . escapeshellarg($type);
if (!empty($code)) $cmd .= " --code " . escapeshellarg($code);

// Redirect stderr to stdout to capture errors
$cmd .= " 2>&1";

$descriptorspec = [
    0 => ["pipe", "r"],   // stdin
    1 => ["pipe", "w"],   // stdout
    2 => ["pipe", "w"]    // stderr
];

$process = proc_open($cmd, $descriptorspec, $pipes);

if (is_resource($process)) {
    // Non-blocking read? No, we want blocking read line by line
    while ($s = fgets($pipes[1])) {
        echo $s;
        flush();
    }

    fclose($pipes[0]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Gagal menjalankan script python']) . "\n";
}
?>
