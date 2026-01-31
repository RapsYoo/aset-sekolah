<?php
require_once '../inc/auth.php';
require_once '../inc/helpers.php';

require_admin();

$id = (int)($_GET['id'] ?? 0);
$role = db_fetch_one("SELECT * FROM roles WHERE id = ?", 'i', [$id]);
if (!$role) {
    $_SESSION['flash_message'] = 'Role tidak ditemukan';
    $_SESSION['flash_type'] = 'danger';
    header("Location: roles.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token CSRF tidak valid';
    } else {
        $new_name = strtolower(trim($_POST['name'] ?? ''));
        $current_name = strtolower($role['name']);

        if (empty($new_name)) {
            $error = 'Nama role harus diisi';
        } elseif (!preg_match('/^[a-z0-9_\-]+$/', $new_name)) {
            $error = 'Nama role hanya boleh huruf kecil, angka, underscore atau strip';
        } elseif (in_array($current_name, ['admin', 'pegawai'])) {
            $error = 'Role default tidak dapat diubah';
        } else {
            $exists = db_fetch_one("SELECT id FROM roles WHERE name = ? AND id <> ?", 'si', [$new_name, $id]);
            if ($exists) {
                $error = 'Role dengan nama tersebut sudah ada';
            } else {
                $stmt = db_prepare("UPDATE roles SET name = ? WHERE id = ?");
                $stmt->bind_param('si', $new_name, $id);
                if ($stmt->execute()) {
                    $_SESSION['flash_message'] = 'Role berhasil diperbarui';
                    $_SESSION['flash_type'] = 'success';
                    header("Location: roles.php");
                    exit();
                } else {
                    $error = 'Gagal memperbarui role: ' . $stmt->error;
                }
            }
        }
    }
}

$page_title = 'Edit Role';
require_once '../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="fas fa-user-shield me-2"></i>Edit Role</h4>
        <p class="text-muted mb-0">Ubah nama role yang ada</p>
    </div>
    <a href="roles.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Kembali</a>
    </div>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Form Edit Role</h5>
    </div>
    <div class="card-body">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo escape($error); ?></div>
        <?php endif; ?>

        <form method="POST" class="row g-3 needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <div class="col-md-8">
                <label class="form-label small text-muted">Nama Role</label>
                <input type="text" class="form-control" name="name" value="<?php echo escape($role['name']); ?>" required>
                <div class="form-text">Huruf kecil, angka, underscore (_), atau strip (-).</div>
                <div class="invalid-feedback">Nama role harus diisi.</div>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-save me-1"></i>Simpan Perubahan
                </button>
            </div>
        </form>
    </div>

<?php require_once '../inc/footer.php'; ?>
