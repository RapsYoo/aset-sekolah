<?php
require_once __DIR__ . '/config.php';

// Koneksi database menggunakan MySQLi
$conn = new mysqli(
    config('db_host'),
    config('db_user'),
    config('db_pass'),
    config('db_name'),
    config('db_port')
);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8mb4");

/**
 * Fungsi helper untuk menjalankan prepared statement
 */
function db_prepare($sql) {
    global $conn;
    return $conn->prepare($sql);
}

/**
 * Fungsi untuk execute query dengan bind parameters
 */
function db_execute($stmt, $types = '', $params = []) {
    if (!empty($types) && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    return $stmt->execute();
}

/**
 * Fungsi untuk ambil satu row
 */
function db_fetch_one($sql, $types = '', $params = []) {
    $stmt = db_prepare($sql);
    if (!empty($types) && !empty($params)) {
        db_execute($stmt, $types, $params);
    } else {
        $stmt->execute();
    }
    return $stmt->get_result()->fetch_assoc();
}

/**
 * Fungsi untuk ambil semua rows
 */
function db_fetch_all($sql, $types = '', $params = []) {
    $stmt = db_prepare($sql);
    if (!empty($types) && !empty($params)) {
        db_execute($stmt, $types, $params);
    } else {
        $stmt->execute();
    }
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

?>
