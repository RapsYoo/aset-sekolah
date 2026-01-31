<?php
require_once '../inc/auth.php';
require_once '../inc/helpers.php';

require_admin();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token CSRF tidak valid';
    } elseif ($_POST['action'] === 'add_role') {
        $name = strtolower(trim($_POST['name'] ?? ''));
        if (empty($name)) {
            $error = 'Nama role harus diisi';
        } elseif (!preg_match('/^[a-z0-9_\-]+$/', $name)) {
            $error = 'Nama role hanya boleh huruf kecil, angka, underscore atau strip';
        } else {
            $exists = db_fetch_one("SELECT id FROM roles WHERE name = ?", 's', [$name]);
            if ($exists) {
                $error = 'Role sudah ada';
            } else {
                $stmt = db_prepare("INSERT INTO roles (name) VALUES (?)");
                $stmt->bind_param('s', $name);
                if ($stmt->execute()) {
                    $success = 'Role berhasil ditambahkan';
                } else {
                    $error = 'Gagal menambah role: ' . $stmt->error;
                }
            }
        }
    }
}

$roles = db_fetch_all("SELECT * FROM roles ORDER BY id ASC");
$page_title = 'Kelola Role';
require_once '../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="fas fa-user-shield me-2"></i>Kelola Role</h4>
        <p class="text-muted mb-0">Tambahkan atau hapus role untuk digunakan di Manajemen User</p>
    </div>
</div>

<!-- Form Tambah Role -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-plus-circle me-2 text-primary"></i>Tambah Role Baru</h5>
    </div>
    <div class="card-body">
        <form method="POST" class="row g-3 align-items-end needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="action" value="add_role">
            <div class="col-md-8">
                <label class="form-label small text-muted">Nama Role</label>
                <input type="text" class="form-control" name="name" placeholder="Contoh: operator, supervisor" required>
                <div class="form-text">Gunakan huruf kecil, angka, underscore (_), atau strip (-).</div>
                <div class="invalid-feedback">Nama role harus diisi.</div>
            </div>
            <div class="col-md-4 d-flex justify-content-end">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i>Tambah Role
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Daftar Role -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-list me-2 text-primary"></i>Daftar Role</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">ID</th>
                        <th>Nama Role</th>
                        <th class="pe-4 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($roles as $r): ?>
                        <tr>
                            <td class="ps-4"><?php echo (int)$r['id']; ?></td>
                            <td>
                                <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 rounded-pill px-3">
                                    <?php echo escape($r['name']); ?>
                                </span>
                            </td>
                            <td class="pe-4 text-end">
                                <?php
                                    $protected = in_array($r['name'], ['admin', 'pegawai']);
                                ?>
                                <div class="btn-group btn-group-sm">
                                    <a href="edit_role.php?id=<?php echo (int)$r['id']; ?>" class="btn btn-outline-warning" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if (!$protected): ?>
                                        <a href="delete_role.php?id=<?php echo (int)$r['id']; ?>" class="btn btn-outline-danger"
                                           onclick="return confirm('Yakin hapus role <?php echo escape($r['name']); ?>?')">
                                           <i class="fas fa-trash"></i>
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-outline-secondary" disabled title="Role default tidak dapat dihapus">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    (function () {
        const form = document.querySelector('form.needs-validation');
        if (form) {
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        }
    })();
</script>

<?php require_once '../inc/footer.php'; ?>
