<?php
// Konfigurasi database
session_start();

// Load environment variables (bisa pakai dotenv atau manual)
$config = [
    'db_host' => 'localhost',
    'db_user' => 'root',
    'db_pass' => '',
    'db_name' => 'aset_sekolah',
    'db_port' => 3306,
    'app_url' => 'http://localhost/aset-sekolah'
];

// Fungsi untuk mendapatkan nilai config
function config($key) {
    global $config;
    return $config[$key] ?? null;
}

// Define base path
define('BASE_PATH', dirname(dirname(__FILE__)));
define('APP_URL', config('app_url'));
?>
