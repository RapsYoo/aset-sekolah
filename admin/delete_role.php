<?php
require_once '../inc/auth.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    $_SESSION['flash_message'] = 'Role tidak valid';
    $_SESSION['flash_type'] = 'danger';
    header("Location: roles.php");
    exit();
}

$role = db_fetch_one("SELECT * FROM roles WHERE id = ?", 'i', [$id]);
if (!$role) {
    $_SESSION['flash_message'] = 'Role tidak ditemukan';
    $_SESSION['flash_type'] = 'danger';
    header("Location: roles.php");
    exit();
}

$name = strtolower($role['name']);
if (in_array($name, ['admin', 'pegawai', 'pengembang'])) {
    $_SESSION['flash_message'] = 'Role default tidak dapat dihapus';
    $_SESSION['flash_type'] = 'warning';
    header("Location: roles.php");
    exit();
}

$cnt = db_fetch_one("SELECT COUNT(*) AS c FROM users WHERE role_id = ?", 'i', [$id]);
if (($cnt['c'] ?? 0) > 0) {
    $_SESSION['flash_message'] = 'Tidak bisa menghapus role yang sedang dipakai oleh user';
    $_SESSION['flash_type'] = 'danger';
    header("Location: roles.php");
    exit();
}

$stmt = db_prepare("DELETE FROM roles WHERE id = ?");
$stmt->bind_param('i', $id);
if ($stmt->execute()) {
    $_SESSION['flash_message'] = 'Role berhasil dihapus';
    $_SESSION['flash_type'] = 'success';
} else {
    $_SESSION['flash_message'] = 'Gagal menghapus role: ' . $stmt->error;
    $_SESSION['flash_type'] = 'danger';
}

header("Location: roles.php");
exit();
