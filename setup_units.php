<?php
/**
 * Setup Units Table
 * Jalankan: http://localhost/aset-sekolah/setup_units.php
 */

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'aset_sekolah';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("❌ Koneksi DB gagal: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

echo "<h2>🔧 Setup Tabel Units</h2>";
echo "<hr>";

// Cek apakah tabel units sudah ada
$check = $conn->query("SHOW TABLES LIKE 'units'");
if ($check->num_rows > 0) {
    echo "⚠️ Tabel units sudah ada. Menghapus data lama...<br>";
    $conn->query("DROP TABLE units");
    echo "✅ Tabel lama dihapus<br><br>";
}

// Buat tabel units
$sql_units = "
CREATE TABLE units (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)
";

if ($conn->query($sql_units)) {
    echo "✅ Tabel units berhasil dibuat<br>";
} else {
    echo "❌ Error: " . $conn->error . "<br>";
    exit;
}

// Insert sample data
$units_data = [
    ['SKL001', 'Sekolah Dasar Negeri 1'],
    ['SKL002', 'Sekolah Dasar Negeri 2'],
    ['SKL003', 'Sekolah Menengah Pertama 1']
];

foreach ($units_data as $unit) {
    $stmt = $conn->prepare("INSERT INTO units (code, name) VALUES (?, ?)");
    $stmt->bind_param('ss', $unit[0], $unit[1]);
    if ($stmt->execute()) {
        echo "✅ Unit '" . $unit[1] . "' ditambahkan<br>";
    } else {
        echo "❌ Error: " . $stmt->error . "<br>";
    }
}

echo "<hr>";
echo "<h3>✅ Setup Selesai!</h3>";
echo "<p>Tabel units sudah siap digunakan.</p>";
echo "<a href='admin/units.php'><button style='padding:10px 20px; background:#667eea; color:white; border:none; border-radius:5px; cursor:pointer;'>▶ Buka Kelola Unit</button></a>";
echo "<br><br><em style='color:red;'>⚠️ Hapus atau rename file <code>setup_units.php</code> setelah selesai!</em>";

$conn->close();
?>
