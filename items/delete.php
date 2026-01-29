<?php
require_once '../inc/auth.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$stmt = db_prepare("DELETE FROM items WHERE id = ?");
$stmt->bind_param('i', $id);

if ($stmt->execute()) {
    $_SESSION['flash_message'] = 'Barang berhasil dihapus!';
    $_SESSION['flash_type'] = 'success';
} else {
    $_SESSION['flash_message'] = 'Gagal menghapus barang!';
    $_SESSION['flash_type'] = 'danger';
}

header("Location: index.php");
exit();
