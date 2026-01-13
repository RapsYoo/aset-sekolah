<?php
// Disable output buffering dan set JSON header PERTAMA
ob_start();
ob_clean();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once '../inc/config.php';

// Koneksi database
$conn = new mysqli(
    config('db_host'),
    config('db_user'),
    config('db_pass'),
    config('db_name'),
    config('db_port')
);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$conn->set_charset("utf8mb4");

// Validasi request
$kib = isset($_GET['kib']) ? trim($_GET['kib']) : '';
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

if (empty($kib) || !in_array($kib, ['A', 'B', 'C', 'D', 'E', 'F'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid KIB type', 'received' => $kib]);
    exit;
}

if ($year < 2020 || $year > 2099) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid year', 'received' => $year]);
    exit;
}

// Ambil data dari database
$stmt = $conn->prepare("SELECT month, total FROM assets_monthly WHERE kib_type = ? AND year = ? ORDER BY month");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Prepare failed: ' . $conn->error]);
    exit;
}

$stmt->bind_param('si', $kib, $year);
if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['error' => 'Execute failed: ' . $stmt->error]);
    exit;
}

$result = $stmt->get_result();
$data = $result->fetch_all(MYSQLI_ASSOC);

// Return JSON
echo json_encode($data ?: [], JSON_PRETTY_PRINT);

$stmt->close();
$conn->close();
ob_end_flush();
?>
