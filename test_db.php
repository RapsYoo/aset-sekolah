<?php
/**
 * Script Test Koneksi Database
 * Jalankan: http://localhost/aset-sekolah/test_db.php
 */

// Config database
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'aset_sekolah';
$db_port = 3306;

echo "<h2>🔍 Test Koneksi Database</h2>";
echo "<hr>";

// Test 1: Koneksi ke MySQL Server
echo "<h3>1️⃣ Koneksi ke MySQL Server</h3>";
$conn = @mysqli_connect($db_host, $db_user, $db_pass);
if ($conn) {
    echo "✅ <strong>Berhasil terhubung ke MySQL!</strong><br>";
    echo "Host: <code>$db_host</code><br>";
    echo "User: <code>$db_user</code><br>";
    echo "Pass: <code>" . ($db_pass ?: '(kosong)') . "</code><br>";
    echo "MySQL Version: <code>" . mysqli_get_server_info($conn) . "</code><br><br>";
} else {
    echo "❌ <strong>Gagal terhubung ke MySQL!</strong><br>";
    echo "Error: <code>" . mysqli_connect_error() . "</code><br><br>";
    exit;
}

// Test 2: Select database
echo "<h3>2️⃣ Memilih Database</h3>";
if (mysqli_select_db($conn, $db_name)) {
    echo "✅ <strong>Database '<code>$db_name</code>' berhasil dipilih!</strong><br><br>";
} else {
    echo "❌ <strong>Gagal memilih database '<code>$db_name</code>'!</strong><br>";
    echo "Error: <code>" . mysqli_error($conn) . "</code><br>";
    echo "Cek apakah sudah run: <code>mysql -u root < migrations/schema.sql</code><br><br>";
    exit;
}

// Test 3: Check tables
echo "<h3>3️⃣ Daftar Tabel di Database</h3>";
$result = mysqli_query($conn, "SHOW TABLES");
if ($result) {
    $tables = mysqli_fetch_all($result, MYSQLI_NUM);
    if (count($tables) > 0) {
        echo "✅ <strong>Tabel ditemukan:</strong><br>";
        echo "<ul>";
        foreach ($tables as $table) {
            echo "<li><code>" . $table[0] . "</code></li>";
        }
        echo "</ul><br>";
    } else {
        echo "❌ <strong>Tidak ada tabel di database!</strong><br>";
        echo "Silakan import schema: <code>mysql -u root < migrations/schema.sql</code><br><br>";
        exit;
    }
} else {
    echo "❌ Error: " . mysqli_error($conn) . "<br><br>";
    exit;
}

// Test 4: Check users table
echo "<h3>4️⃣ Cek Data di Tabel 'users'</h3>";
$result = mysqli_query($conn, "SELECT id, name, email, role_id FROM users");
if ($result) {
    $users = mysqli_fetch_all($result, MYSQLI_ASSOC);
    if (count($users) > 0) {
        echo "✅ <strong>User ditemukan:</strong><br>";
        echo "<table border='1' cellpadding='8'>";
        echo "<tr><th>ID</th><th>Nama</th><th>Email</th><th>Role ID</th></tr>";
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>" . $user['id'] . "</td>";
            echo "<td>" . $user['name'] . "</td>";
            echo "<td>" . $user['email'] . "</td>";
            echo "<td>" . $user['role_id'] . "</td>";
            echo "</tr>";
        }
        echo "</table><br>";
    } else {
        echo "❌ <strong>Tidak ada user di database!</strong><br><br>";
    }
} else {
    echo "❌ Error: " . mysqli_error($conn) . "<br><br>";
}

// Test 5: Test password verify
echo "<h3>5️⃣ Test Login dengan Akun Demo</h3>";
$admin_query = mysqli_query($conn, "SELECT * FROM users WHERE email = 'admin@sekolah.com'");
$admin = mysqli_fetch_assoc($admin_query);

if ($admin) {
    $test_password = 'admin123';
    $hash = $admin['password_hash'];
    
    echo "User: <code>" . $admin['email'] . "</code><br>";
    echo "Password Hash di DB: <code>" . substr($hash, 0, 20) . "...</code><br>";
    echo "Test Password: <code>$test_password</code><br>";
    
    if (password_verify($test_password, $hash)) {
        echo "✅ <strong>Password BENAR! Login akan berhasil.</strong><br>";
    } else {
        echo "❌ <strong>Password SALAH! Jalankan setup.php untuk reset password.</strong><br>";
    }
} else {
    echo "❌ <strong>User 'admin@sekolah.com' tidak ditemukan!</strong><br>";
}

echo "<hr>";
echo "<h3>✅ Semua Test Selesai!</h3>";
echo "<p><a href='setup.php'><button>▶ Run Setup Password</button></a> ";
echo "<a href='index.php'><button>▶ Login Sekarang</button></a></p>";

mysqli_close($conn);
?>
