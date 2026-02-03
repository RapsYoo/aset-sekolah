<?php
require_once '../inc/auth.php';

require_login();
require_can_edit();

$id = (int)($_GET['id'] ?? 0);
$stmt = db_prepare("DELETE FROM assets_monthly WHERE id = ?");
$stmt->bind_param('i', $id);

if ($stmt->execute()) {
    $_SESSION['flash_message'] = 'Data aset berhasil dihapus!';
    $_SESSION['flash_type'] = 'success';
} else {
    $_SESSION['flash_message'] = 'Gagal menghapus data!';
    $_SESSION['flash_type'] = 'danger';
}

header("Location: index.php");
exit();
?>
