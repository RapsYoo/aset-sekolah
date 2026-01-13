<?php
/**
 * Script Setup Awal - Reset Password Hash
 * Jalankan: http://localhost/aset-sekolah/setup.php
 */

// Direct MySQL connection
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'aset_sekolah';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("❌ Koneksi DB gagal: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

// Password demo
$admin_password = 'admin123';
$pegawai_password = 'pegawai123';

// Generate hash
$admin_hash = password_hash($admin_password, PASSWORD_BCRYPT);
$pegawai_hash = password_hash($pegawai_password, PASSWORD_BCRYPT);

echo "<h2>🔐 Setup Password Hash</h2>";
echo "<hr>";

// Update admin password
$admin_email = 'admin@sekolah.com';
$stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
if (!$stmt) {
    die("❌ Prepare error: " . $conn->error);
}
$stmt->bind_param('ss', $admin_hash, $admin_email);
if ($stmt->execute()) {
    echo "✅ Admin password updated (" . $stmt->affected_rows . " row)<br>";
} else {
    echo "❌ Error: " . $stmt->error . "<br>";
}
$stmt->close();

// Update pegawai password
$pegawai_email = 'pegawai@sekolah.com';
$stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
$stmt->bind_param('ss', $pegawai_hash, $pegawai_email);
if ($stmt->execute()) {
    echo "✅ Pegawai password updated (" . $stmt->affected_rows . " row)<br>";
} else {
    echo "❌ Error: " . $stmt->error . "<br>";
}
$stmt->close();

echo "<hr>";
echo "<h3>✅ Setup Selesai!</h3>";
echo "<p><strong>Akun Login Demo yang Sudah Valid:</strong></p>";
echo "<table border='1' cellpadding='10' style='border-collapse:collapse;'>";
echo "<tr style='background:#f0f0f0;'><th>Email</th><th>Password</th><th>Role</th></tr>";
echo "<tr><td>admin@sekolah.com</td><td>admin123</td><td>Admin</td></tr>";
echo "<tr><td>pegawai@sekolah.com</td><td>pegawai123</td><td>Pegawai</td></tr>";
echo "</table>";
echo "<br><br>";
echo "<a href='index.php'><button style='padding:10px 20px; background:#667eea; color:white; border:none; border-radius:5px; cursor:pointer;'>➜ Login Sekarang</button></a>";
echo "&nbsp;";
echo "<a href='test_db.php'><button style='padding:10px 20px; background:#764ba2; color:white; border:none; border-radius:5px; cursor:pointer;'>➜ Test DB</button></a>";
echo "<br><br><em style='color:red;'>⚠️ Hapus atau rename file <code>setup.php</code> setelah selesai untuk keamanan!</em>";

$conn->close();
?>
