<?php
require_once 'inc/auth.php';
require_once 'inc/helpers.php';

require_login();
$user = current_user();

// Handle update profil
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token CSRF tidak valid';
    } else {
        $name = sanitize($_POST['name'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($name)) {
            $error = 'Nama harus diisi';
        } else {
            // Update nama
            $stmt = db_prepare("UPDATE users SET name = ? WHERE id = ?");
            $stmt->bind_param('si', $name, $user['id']);
            
            if (!$stmt->execute()) {
                $error = 'Gagal memperbarui profil!';
            }

            // Update password jika diisi
            if (!empty($password)) {
                if (strlen($password) < 6) {
                    $error = 'Password minimal 6 karakter!';
                } else {
                    $password_hash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = db_prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    $stmt->bind_param('si', $password_hash, $user['id']);
                    
                    if (!$stmt->execute()) {
                        $error = 'Gagal memperbarui password!';
                    }
                }
            }

            if (empty($error)) {
                $success = 'Profil berhasil diperbarui!';
                $_SESSION['user_name'] = $name;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil - Sistem Monitoring Aset Sekolah</title>
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
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-dark navbar-expand-lg">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-chart-bar me-2"></i>Monitoring Aset Sekolah
            </a>
            <a class="nav-link text-white" href="dashboard.php">
                <i class="fas fa-arrow-left me-2"></i>Kembali
            </a>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                        <h5 class="mb-0"><i class="fas fa-user me-2"></i>Profil Saya</h5>
                    </div>
                    <div class="card-body">
                        

                        <form method="POST" class="needs-validation" novalidate>
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                            <div class="mb-3">
                                <label for="name" class="form-label">Nama</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo escape($user['name']); ?>" required>
                                <div class="invalid-feedback">Nama harus diisi.</div>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" 
                                       value="<?php echo escape($user['email']); ?>" disabled>
                                <small class="text-muted">Email tidak dapat diubah</small>
                            </div>

                            <div class="mb-3">
                                <label for="role" class="form-label">Role</label>
                                <input type="text" class="form-control" id="role" 
                                       value="<?php echo ucfirst(escape($user['role'])); ?>" disabled>
                            </div>

                            <hr>

                            <h6>Ubah Password (Opsional)</h6>

                            <div class="mb-3">
                                <label for="password" class="form-label">Password Baru</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password"
                                           placeholder="Biarkan kosong jika tidak ingin mengubah" minlength="6">
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">Tampilkan</button>
                                </div>
                                <div class="invalid-feedback">Minimal 6 karakter.</div>
                                <small class="text-muted">Minimal 6 karakter</small>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-between">
                                <a href="dashboard.php" class="btn btn-secondary">Batal</a>
                                <button type="submit" class="btn" id="btnSubmit" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                                    <i class="fas fa-save me-2"></i>Simpan Perubahan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php
        $toastMessage = '';
        $toastType = 'info';
        if (!empty($success)) {
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
            if (toggle && pwd) {
                toggle.addEventListener('click', function () {
                    const isPwd = pwd.getAttribute('type') === 'password';
                    pwd.setAttribute('type', isPwd ? 'text' : 'password');
                    toggle.textContent = isPwd ? 'Sembunyikan' : 'Tampilkan';
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
            const toastEl = document.getElementById('mainToast');
            if (toastEl && typeof bootstrap !== 'undefined') {
                new bootstrap.Toast(toastEl).show();
            }
        })();
    </script>
</body>
</html>
