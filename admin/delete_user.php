<?php
require_once '../inc/auth.php';

require_admin();

$id = (int)($_GET['id'] ?? 0);
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
