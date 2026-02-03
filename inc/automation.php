<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';
function automation_job_id() {
    return str_replace('.', '', uniqid('job_', true));
}
function automation_job_dir($job_id) {
    $key = 'automation/' . $job_id;
    return storage_get_full_path($key);
}
function automation_init_job($job_id, $meta = []) {
    $dir = automation_job_dir($job_id);
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    $status = [
        'job_id' => $job_id,
        'status' => 'queued',
        'meta' => $meta,
        'created_at' => time(),
        'updated_at' => time()
    ];
    file_put_contents($dir . DIRECTORY_SEPARATOR . 'status.json', json_encode($status));
    return $dir;
}
function automation_update_status($job_id, $status, $meta = []) {
    $dir = automation_job_dir($job_id);
    $path = $dir . DIRECTORY_SEPARATOR . 'status.json';
    $data = [
        'job_id' => $job_id,
        'status' => $status,
        'meta' => $meta,
        'updated_at' => time()
    ];
    file_put_contents($path, json_encode($data));
}
function automation_get_status($job_id) {
    $dir = automation_job_dir($job_id);
    $path = $dir . DIRECTORY_SEPARATOR . 'status.json';
    if (file_exists($path)) {
        $data = json_decode(file_get_contents($path), true);
    } else {
        $data = ['job_id' => $job_id, 'status' => 'unknown', 'updated_at' => time()];
    }
    $log = $dir . DIRECTORY_SEPARATOR . 'log.txt';
    $log_text = '';
    if (file_exists($log)) {
        $log_text = file_get_contents($log);
    }
    $done = $dir . DIRECTORY_SEPARATOR . 'done.flag';
    if (file_exists($done)) {
        $data['status'] = 'done';
    }
    $data['log'] = $log_text;
    $files = [];
    if (is_dir($dir)) {
        $list = scandir($dir);
        foreach ($list as $f) {
            if ($f === '.' || $f === '..') continue;
            $full = $dir . DIRECTORY_SEPARATOR . $f;
            if (is_file($full) && preg_match('/\.xls$/i', $f)) {
                $files[] = [
                    'name' => $f,
                    'size' => filesize($full),
                    'mtime' => filemtime($full),
                    'key' => 'automation/' . $job_id . '/' . $f
                ];
            }
        }
    }
    usort($files, function($a, $b) { return ($b['mtime'] <=> $a['mtime']); });
    $data['files'] = $files;
    return $data;
}
function automation_start_process($job_id, $params) {
    $python = config('python_path') ?? 'python';
    $script = BASE_PATH . DIRECTORY_SEPARATOR . 'automation' . DIRECTORY_SEPARATOR . 'KIB_Master.py';
    $dir = automation_job_dir($job_id);
    $log = $dir . DIRECTORY_SEPARATOR . 'log.txt';
    $kib = strtoupper($params['kib'] ?? 'A');
    $tgl = $params['tgl'] ?? date('Y-m-d');
    $unit = $params['unit'] ?? '';
    $aset = $params['jenis_aset'] ?? '1';
    $kode = $params['jenis_kode'] ?? '1';
    $quote = function($s) {
        return '"' . $s . '"';
    };
    $parts = [
        $quote($python),
        $quote($script),
        $quote('cli'),
        $quote($kib),
        $quote($tgl),
        $quote($unit),
        $quote($aset),
        $quote($kode),
        $quote($dir),
        $quote($dir),
    ];
    $cmd = implode(' ', $parts);
    $redirect = ' > ' . $quote($log) . ' 2>&1';
    $command_arg = '"' . $cmd . $redirect . '"';
    $command = 'start "" /B cmd /C ' . $command_arg;
    @file_put_contents($dir . DIRECTORY_SEPARATOR . 'cmd.txt', $command . PHP_EOL);
    $proc = @popen($command, 'r');
    if ($proc === false) {
        automation_update_status($job_id, 'error', ['message' => 'Failed to start process']);
        return false;
    }
    @pclose($proc);
    automation_update_status($job_id, 'running', $params);
    return true;
}
?>
