<?php
require_once 'inc/auth.php';
require_once 'inc/helpers.php';

// Jika sudah login, redirect ke dashboard
if (is_logged_in()) {
    header("Location: dashboard.php");
    exit();
}

// Handle login
$error = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Email dan password harus diisi';
    } elseif (login($email, $password)) {
        header("Location: dashboard.php");
        exit();
    } else {
        $error = 'Email atau password salah';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistem Monitoring Aset Sekolah</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-container {
            width: 100%;
            max-width: 400px;
        }
        .card {
            border: none;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 2rem 1rem;
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        .btn-login:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
        }
    </style>
</head>
<body>
    <div class="login-container mx-auto">
        <div class="card">
            <div class="card-header text-center">
                <h3 class="mb-0">Sistem Monitoring Aset</h3>
                <small>Sekolah</small>
            </div>
            <div class="card-body p-4">
                

                <form method="POST" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required autofocus value="<?php echo escape($email); ?>">
                        <div class="invalid-feedback">Masukkan email yang valid.</div>
                        <small class="text-muted d-block mt-2">
                            Demo: <strong>admin@sekolah.com</strong> atau <strong>pegawai@sekolah.com</strong>
                        </small>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password" name="password" required minlength="6">
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">Tampilkan</button>
                        </div>
                        <div class="invalid-feedback">Password minimal 6 karakter.</div>
                        <small class="text-muted d-block mt-2">
                            Demo: <strong>admin123</strong> atau <strong>pegawai123</strong>
                        </small>
                    </div>

                    <button type="submit" class="btn btn-login w-100 text-white" id="btnLogin">Login</button>
                </form>
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
        }
        if (!empty($error) && empty($toastMessage)) {
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
            const btn = document.getElementById('btnLogin');
            const pwd = document.getElementById('password');
            const toggle = document.getElementById('togglePassword');
            if (toggle) {
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
