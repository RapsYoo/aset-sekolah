<?php
require_once '../inc/auth.php';
require_once '../inc/helpers.php';

require_admin();

// Handle add user
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token CSRF tidak valid';
    } elseif ($_POST['action'] === 'add_user') {
        $name = sanitize($_POST['name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role_id = (int)($_POST['role_id'] ?? 0);

        if (empty($name) || empty($email) || empty($password) || empty($role_id)) {
            $error = 'Semua field harus diisi';
        } elseif (strlen($password) < 6) {
            $error = 'Password minimal 6 karakter';
        } else {
            $password_hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = db_prepare(
                "INSERT INTO users (name, email, password_hash, role_id) VALUES (?, ?, ?, ?)"
            );
            $stmt->bind_param('sssi', $name, $email, $password_hash, $role_id);
            
            if ($stmt->execute()) {
                $success = 'User berhasil ditambahkan!';
            } else {
                $error = 'Gagal menambah user: ' . $stmt->error;
            }
        }
    }
}

// Ambil data users
$users = db_fetch_all(
    "SELECT u.*, r.name as role_name FROM users u 
     LEFT JOIN roles r ON u.role_id = r.id 
     ORDER BY u.created_at DESC"
);

// Ambil roles
$roles = db_fetch_all("SELECT * FROM roles");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen User - Sistem Monitoring Aset Sekolah</title>
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
                <h3><i class="fas fa-users me-2"></i>Manajemen Pengguna</h3>
            </div>
        </div>

        <!-- Form Tambah User -->
        <div class="table-container mb-4">
            <h5 class="mb-3"><i class="fas fa-user-plus me-2"></i>Tambah Pengguna Baru</h5>

            

            <form method="POST" class="row g-3 needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="action" value="add_user">

                <div class="col-md-3">
                    <input type="text" class="form-control" name="name" placeholder="Nama Lengkap" required>
                    <div class="invalid-feedback">Nama harus diisi.</div>
                </div>
                <div class="col-md-3">
                    <input type="email" class="form-control" name="email" placeholder="Email" required>
                    <div class="invalid-feedback">Email tidak valid.</div>
                </div>
                <div class="col-md-2">
                    <div class="input-group">
                        <input type="password" class="form-control" name="password" id="password" placeholder="Password" required minlength="6">
                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">Tampilkan</button>
                    </div>
                    <div class="invalid-feedback">Password minimal 6 karakter.</div>
                    <div class="form-text" id="pwdStrength"></div>
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="role_id" required>
                        <option value="">-- Role --</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?php echo $role['id']; ?>"><?php echo escape($role['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback">Pilih role.</div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn w-100" id="btnSubmit" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                        Tambah
                    </button>
                </div>
            </form>
        </div>

        <!-- Daftar User -->
        <div class="table-container">
            <h5 class="mb-3"><i class="fas fa-list me-2"></i>Daftar Pengguna</h5>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Nama</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Terdaftar</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td><?php echo escape($u['name']); ?></td>
                                <td><?php echo escape($u['email']); ?></td>
                                <td>
                                    <span class="badge <?php echo $u['role_name'] === 'admin' ? 'bg-danger' : 'bg-info'; ?>">
                                        <?php echo escape($u['role_name']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $u['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                        <?php echo $u['is_active'] ? 'Aktif' : 'Nonaktif'; ?>
                                    </span>
                                </td>
                                <td><?php echo format_date($u['created_at']); ?></td>
                                <td>
                                    <a href="edit_user.php?id=<?php echo $u['id']; ?>" class="btn btn-sm btn-warning">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="delete_user.php?id=<?php echo $u['id']; ?>" class="btn btn-sm btn-danger"
                                       onclick="return confirm('Yakin hapus user?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
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
            const pwd = document.getElementById('password');
            const toggle = document.getElementById('togglePassword');
            const strength = document.getElementById('pwdStrength');
            if (toggle && pwd) {
                toggle.addEventListener('click', function () {
                    const isPwd = pwd.getAttribute('type') === 'password';
                    pwd.setAttribute('type', isPwd ? 'text' : 'password');
                    toggle.textContent = isPwd ? 'Sembunyikan' : 'Tampilkan';
                });
            }
            if (pwd && strength) {
                const updateStrength = () => {
                    const v = pwd.value || '';
                    let score = 0;
                    if (v.length >= 6) score++;
                    if (/[A-Z]/.test(v)) score++;
                    if (/[a-z]/.test(v)) score++;
                    if (/\d/.test(v)) score++;
                    if (/[^A-Za-z0-9]/.test(v)) score++;
                    const levels = ['Sangat lemah', 'Lemah', 'Sedang', 'Kuat', 'Sangat kuat'];
                    strength.textContent = v ? 'Kekuatan: ' + levels[Math.max(0, score - 1)] : '';
                };
                pwd.addEventListener('input', updateStrength);
                updateStrength();
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
