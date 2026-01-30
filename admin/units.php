<?php
require_once '../inc/auth.php';
require_once '../inc/helpers.php';

require_admin();

// Handle add unit
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token CSRF tidak valid';
    } elseif ($_POST['action'] === 'add_unit') {
        $code = sanitize($_POST['code'] ?? '');
        $name = sanitize($_POST['name'] ?? '');

        if (empty($code) || empty($name)) {
            $error = 'Kode dan nama unit harus diisi';
        } else {
            $stmt = db_prepare(
                "INSERT INTO units (code, name) VALUES (?, ?)"
            );
            $stmt->bind_param('ss', $code, $name);
            
            if ($stmt->execute()) {
                $success = 'Unit berhasil ditambahkan!';
            } else {
                if (strpos($stmt->error, 'Duplicate entry') !== false) {
                    $error = 'Kode unit sudah ada, gunakan kode lain!';
                } else {
                    $error = 'Gagal menambah unit: ' . $stmt->error;
                }
            }
        }
    }
}

// Ambil data units
$units = db_fetch_all("SELECT * FROM units ORDER BY created_at DESC");

$page_title = 'Manajemen Unit';
require_once '../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="fas fa-building me-2"></i>Manajemen Unit/Sekolah</h4>
        <p class="text-muted mb-0">Kelola daftar sekolah atau unit kerja</p>
    </div>
</div>

<!-- Form Tambah Unit -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-plus me-2 text-primary"></i>Tambah Unit Baru</h5>
    </div>
    <div class="card-body">
        <form method="POST" class="row g-3 needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="action" value="add_unit">

            <div class="col-md-4">
                <label class="form-label small text-muted">Kode Unit</label>
                <input type="text" class="form-control" name="code" id="code" placeholder="Contoh: SKL001" required pattern="[A-Z0-9]{3,20}">
                <div class="invalid-feedback">Kode 3–20 karakter, huruf besar/angka.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label small text-muted">Nama Sekolah/Unit</label>
                <input type="text" class="form-control" name="name" placeholder="Nama Sekolah Lengkap" required>
                <div class="invalid-feedback">Nama harus diisi.</div>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100" id="btnSubmit">
                    <i class="fas fa-save me-2"></i>Simpan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Daftar Unit -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-list me-2 text-primary"></i>Daftar Unit/Sekolah</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Kode Unit</th>
                        <th>Nama Sekolah</th>
                        <th>Terdaftar</th>
                        <th class="pe-4 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($units) > 0): ?>
                        <?php foreach ($units as $unit): ?>
                            <tr>
                                <td class="ps-4"><span class="badge bg-light text-dark border font-monospace"><?php echo escape($unit['code']); ?></span></td>
                                <td class="fw-medium text-primary"><?php echo escape($unit['name']); ?></td>
                                <td class="text-muted small"><?php echo format_date($unit['created_at']); ?></td>
                                <td class="pe-4 text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="units_edit.php?id=<?php echo $unit['id']; ?>" class="btn btn-outline-warning" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="units_delete.php?id=<?php echo $unit['id']; ?>" class="btn btn-outline-danger"
                                           onclick="return confirm('Yakin hapus unit ini?')" title="Hapus">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted py-5">
                                <i class="fas fa-school fa-3x mb-3 d-block opacity-25"></i>
                                Tidak ada unit terdaftar
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    (function () {
        const form = document.querySelector('form.needs-validation');
        const btn = document.getElementById('btnSubmit');
        const code = document.getElementById('code');

        if (code) {
            code.addEventListener('input', function () {
                code.value = code.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
            });
        }

        if (form) {
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                } else {
                    if (btn) {
                        btn.disabled = true;
                        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Menyimpan...';
                    }
                }
                form.classList.add('was-validated');
            }, false);
        }
    })();
</script>

<?php require_once '../inc/footer.php'; ?>
