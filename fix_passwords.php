<?php
/**
 * Script untuk memperbaiki password hash di database
 * Jalankan: http://localhost/aset-sekolah/fix_passwords.php
 */

require_once 'inc/config.php';
require_once 'inc/db.php';

// Generate password hash yang benar
$admin_password = 'admin123';
$pegawai_password = 'pegawai123';

$admin_hash = password_hash($admin_password, PASSWORD_DEFAULT);
$pegawai_hash = password_hash($pegawai_password, PASSWORD_DEFAULT);

echo "<h2>🔧 Fix Password Hash</h2>";
echo "<hr>";

// Update password admin
$stmt = db_prepare("UPDATE users SET password_hash = ? WHERE email = 'admin@sekolah.com'");
$stmt->bind_param('s', $admin_hash);
if ($stmt->execute()) {
    echo "✅ Password admin berhasil diupdate<br>";
    echo "   Email: admin@sekolah.com<br>";
    echo "   Password: admin123<br>";
    echo "   Hash: " . substr($admin_hash, 0, 50) . "...<br><br>";
} else {
    echo "❌ Error update admin: " . $stmt->error . "<br><br>";
}

// Update password pegawai
$stmt = db_prepare("UPDATE users SET password_hash = ? WHERE email = 'pegawai@sekolah.com'");
$stmt->bind_param('s', $pegawai_hash);
if ($stmt->execute()) {
    echo "✅ Password pegawai berhasil diupdate<br>";
    echo "   Email: pegawai@sekolah.com<br>";
    echo "   Password: pegawai123<br>";
    echo "   Hash: " . substr($pegawai_hash, 0, 50) . "...<br><br>";
} else {
    echo "❌ Error update pegawai: " . $stmt->error . "<br><br>";
}

// Test login
echo "<hr>";
echo "<h3>🧪 Test Login</h3>";

$test_admin = db_fetch_one(
    "SELECT * FROM users WHERE email = ?",
    's',
    ['admin@sekolah.com']
);

if ($test_admin && password_verify($admin_password, $test_admin['password_hash'])) {
    echo "✅ Admin login test: <strong>BERHASIL</strong><br>";
} else {
    echo "❌ Admin login test: <strong>GAGAL</strong><br>";
}

$test_pegawai = db_fetch_one(
    "SELECT * FROM users WHERE email = ?",
    's',
    ['pegawai@sekolah.com']
);

if ($test_pegawai && password_verify($pegawai_password, $test_pegawai['password_hash'])) {
    echo "✅ Pegawai login test: <strong>BERHASIL</strong><br>";
} else {
    echo "❌ Pegawai login test: <strong>GAGAL</strong><br>";
}

echo "<hr>";
echo "<h3>✅ Selesai!</h3>";
echo "<p>Sekarang Anda bisa login dengan:</p>";
echo "<ul>";
echo "<li><strong>Email:</strong> admin@sekolah.com | <strong>Password:</strong> admin123</li>";
echo "<li><strong>Email:</strong> pegawai@sekolah.com | <strong>Password:</strong> pegawai123</li>";
echo "</ul>";
echo "<a href='index.php'><button style='padding:10px 20px; background:#667eea; color:white; border:none; border-radius:5px; cursor:pointer;'>▶ Coba Login</button></a>";
echo "<br><br><em style='color:red;'>⚠️ Hapus atau rename file <code>fix_passwords.php</code> setelah selesai!</em>";
?>

