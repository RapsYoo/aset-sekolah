<?php
require_once '../inc/auth.php';

require_admin();

$id = (int)($_GET['id'] ?? 0);

// Cegah hapus akun pengembang
$target_user = db_fetch_one("SELECT u.*, r.name as role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id = ?", 'i', [$id]);
if ($target_user && $target_user['role_name'] === 'pengembang') {
    $_SESSION['flash_message'] = 'Akses ditolak!';
    $_SESSION['flash_type'] = 'danger';
    header("Location: users.php");
    exit();
}

$stmt = db_prepare("DELETE FROM users WHERE id = ?");
$stmt->bind_param('i', $id);

if ($stmt->execute()) {
    $_SESSION['flash_message'] = 'User berhasil dihapus!';
    $_SESSION['flash_type'] = 'success';
} else {
    $_SESSION['flash_message'] = 'Gagal menghapus user!';
    $_SESSION['flash_type'] = 'danger';
}

header("Location: users.php");
exit();
?>
