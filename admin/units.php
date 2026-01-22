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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Unit - Sistem Monitoring Aset Sekolah</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #667eea;
            --secondary: #764ba2;
        }
        .navbar {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        }
        body {
            background: #f8f9fa;
        }
        .table-container {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-dark navbar-expand-lg">
        <div class="container-fluid">
            <a class="navbar-brand" href="../dashboard.php">
                <i class="fas fa-chart-bar me-2"></i>Monitoring Aset Sekolah
            </a>
            <a class="nav-link text-white" href="../dashboard.php">
                <i class="fas fa-arrow-left me-2"></i>Kembali
            </a>
        </div>
    </nav>

    <div class="container-fluid mt-5">
        <div class="row mb-4">
            <div class="col-md-6">
                <h3><i class="fas fa-building me-2"></i>Manajemen Unit/Sekolah</h3>
            </div>
        </div>

        <!-- Form Tambah Unit -->
        <div class="table-container mb-4">
            <h5 class="mb-3"><i class="fas fa-plus me-2"></i>Tambah Unit Baru</h5>

            

            <form method="POST" class="row g-3 needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="action" value="add_unit">

                <div class="col-md-4">
                    <input type="text" class="form-control" name="code" id="code" placeholder="Kode Unit (contoh: SKL001)" required pattern="[A-Z0-9]{3,20}">
                    <div class="invalid-feedback">Kode 3–20 karakter, huruf besar/angka.</div>
                </div>
                <div class="col-md-6">
                    <input type="text" class="form-control" name="name" placeholder="Nama Sekolah/Unit" required>
                    <div class="invalid-feedback">Nama harus diisi.</div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn w-100" id="btnSubmit" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                        Tambah
                    </button>
                </div>
            </form>
        </div>

        <!-- Daftar Unit -->
        <div class="table-container">
            <h5 class="mb-3"><i class="fas fa-list me-2"></i>Daftar Unit/Sekolah</h5>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Kode Unit</th>
                            <th>Nama Sekolah</th>
                            <th>Terdaftar</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($units) > 0): ?>
                            <?php foreach ($units as $unit): ?>
                                <tr>
                                    <td><code><?php echo escape($unit['code']); ?></code></td>
                                    <td><?php echo escape($unit['name']); ?></td>
                                    <td><?php echo format_date($unit['created_at']); ?></td>
                                    <td>
                                        <a href="units_edit.php?id=<?php echo $unit['id']; ?>" class="btn btn-sm btn-warning">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <a href="units_delete.php?id=<?php echo $unit['id']; ?>" class="btn btn-sm btn-danger"
                                           onclick="return confirm('Yakin hapus unit ini?')">
                                            <i class="fas fa-trash"></i> Hapus
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted">Tidak ada unit terdaftar</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php
        $fm = get_flash_message();
        $toastMessage = '';
        $toastType = 'info';
        if ($fm) {
            $toastMessage = $fm['message'] ?? '';
            $toastType = $fm['type'] ?? 'info';
        } elseif (!empty($success)) {
            $toastMessage = $success;
            $toastType = 'success';
        } elseif (!empty($error)) {
            $toastMessage = $error;
            $toastType = 'danger';
        }
        $toastClass = 'text-bg-info';
        if ($toastType === 'success') $toastClass = 'text-bg-success';
        elseif ($toastType === 'danger') $toastClass = 'text-bg-danger';
        elseif ($toastType === 'warning') $toastClass = 'text-bg-warning';
    ?>
    <?php if (!empty($toastMessage)): ?>
        <div class="toast-container position-fixed top-0 end-0 p-3">
            <div id="mainToast" class="toast align-items-center <?php echo $toastClass; ?> border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="4000">
                <div class="d-flex">
                    <div class="toast-body"><?php echo escape($toastMessage); ?></div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        </div>
    <?php endif; ?>
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
                            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Memproses...';
                        }
                    }
                    form.classList.add('was-validated');
                }, false);
            }
            const toastEl = document.getElementById('mainToast');
            if (toastEl && typeof bootstrap !== 'undefined') {
                new bootstrap.Toast(toastEl).show();
            }
        })();
    </script>
</body>
</html>
