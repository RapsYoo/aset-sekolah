<?php
/**
 * Script Debug Data Aset & API
 * Jalankan: http://localhost/aset-sekolah/debug_chart.php
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

echo "<h2>🔍 Debug Chart & Data Aset</h2>";
echo "<hr>";

// 1. Check data di tabel assets_monthly
echo "<h3>1️⃣ Cek Data Aset di Database</h3>";
$result = $conn->query("SELECT * FROM assets_monthly ORDER BY kib_type, year, month");

if ($result->num_rows > 0) {
    echo "✅ <strong>Data ditemukan (" . $result->num_rows . " records):</strong><br>";
    echo "<table border='1' cellpadding='8' style='border-collapse:collapse;'>";
    echo "<tr style='background:#f0f0f0;'>";
    echo "<th>ID</th><th>KIB</th><th>Tahun</th><th>Bulan</th><th>Total</th><th>Created</th>";
    echo "</tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>KIB " . $row['kib_type'] . "</td>";
        echo "<td>" . $row['year'] . "</td>";
        echo "<td>" . $row['month'] . "</td>";
        echo "<td>" . number_format($row['total']) . "</td>";
        echo "<td>" . $row['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table><br>";
} else {
    echo "❌ <strong>Tidak ada data aset di database!</strong><br>";
    echo "Silakan input aset terlebih dahulu di: <a href='assets/create.php'><code>assets/create.php</code></a><br><br>";
}

// 2. Test API endpoint untuk tiap KIB
echo "<h3>2️⃣ Test API Endpoint</h3>";
$kibTypes = ['A', 'B', 'C', 'D', 'E', 'F'];
$currentYear = date('Y');

foreach ($kibTypes as $kib) {
    echo "<strong>KIB $kib (Tahun $currentYear):</strong><br>";
    
    // Query langsung
    $result = $conn->prepare(
        "SELECT month, total FROM assets_monthly WHERE kib_type = ? AND year = ? ORDER BY month"
    );
    $result->bind_param('si', $kib, $currentYear);
    $result->execute();
    $data = $result->get_result()->fetch_all(MYSQLI_ASSOC);
    
    if (count($data) > 0) {
        echo "✅ Ada " . count($data) . " data<br>";
        echo "<code>" . json_encode($data) . "</code><br>";
    } else {
        echo "⚠️ Tidak ada data untuk KIB $kib tahun $currentYear<br>";
    }
    echo "<br>";
}

echo "<hr>";
echo "<h3>3️⃣ Instruksi Memperbaiki Chart</h3>";
echo "<ol>";
echo "<li>Pastikan sudah input aset dengan tahun " . $currentYear . " (bulan apapun)</li>";
echo "<li>Buka <a href='dashboard.php'><code>dashboard.php</code></a> di browser</li>";
echo "<li>Buka DevTools (F12) → Console untuk lihat error</li>";
echo "<li>Jika masih error, run script ini lagi untuk cek data</li>";
echo "</ol>";

echo "<br><a href='dashboard.php'><button style='padding:10px 20px; background:#667eea; color:white; border:none; border-radius:5px; cursor:pointer;'>▶ Kembali ke Dashboard</button></a>";
echo "&nbsp;";
echo "<a href='assets/create.php'><button style='padding:10px 20px; background:#764ba2; color:white; border:none; border-radius:5px; cursor:pointer;'>▶ Input Aset Baru</button></a>";

$conn->close();
?>
