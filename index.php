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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/public/css/style.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="login-bg">
        <div class="login-card">
            <div class="login-left d-flex flex-column justify-content-center">
                <div class="mb-5">
                    <h3 class="fw-bold text-primary">Selamat Datang</h3>
                    <p class="text-muted">Silakan login untuk mengakses sistem monitoring.</p>
                </div>

                <form method="POST" class="needs-validation" novalidate>
                    <div class="mb-4">
                        <label for="email" class="form-label text-muted text-uppercase small fw-bold">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="fas fa-envelope text-muted"></i></span>
                            <input type="email" class="form-control border-start-0 ps-0 bg-light" id="email" name="email"
                                   required autofocus value="<?php echo escape($email); ?>" placeholder="name@school.com">
                            <div class="invalid-feedback">Masukkan email yang valid.</div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label text-muted text-uppercase small fw-bold">Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="fas fa-lock text-muted"></i></span>
                            <input type="password" class="form-control border-start-0 ps-0 bg-light" id="password" name="password"
                                   required minlength="6" placeholder="******">
                            <button class="btn btn-outline-secondary border-start-0 bg-light" type="button" id="togglePassword">
                                <i class="fas fa-eye text-muted"></i>
                            </button>
                            <div class="invalid-feedback">Password minimal 6 karakter.</div>
                        </div>
                    </div>

                    <div class="d-grid mb-4">
                        <button type="submit" class="btn btn-primary py-2 fw-bold shadow-sm" id="btnLogin">
                            LOGIN
                        </button>
                    </div>

                </form>

            </div>

            <div class="login-right d-none d-md-flex">
                <div class="login-right-content">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <img src="<?php echo APP_URL; ?>/asetgambar/logo_login.png" alt="Logo" style="width: 70px; height: 70px; object-fit: contain;">
                        <div>
                            <h2 class="fw-bold mb-0" style="letter-spacing: 2px;">SIMBAKDA</h2>
                            <p class="mb-0 small" style="letter-spacing: 1px;">PROVINSI SULAWESI SELATAN</p>
                        </div>
                    </div>
                    <p class="lead mb-0 mt-3">Kelola dan pantau aset sekolah dengan mudah, cepat, dan akurat.</p>
                </div>
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
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const toastEl = document.getElementById('mainToast');
                if (toastEl && typeof bootstrap !== 'undefined') {
                    new bootstrap.Toast(toastEl).show();
                }
            });
        </script>
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
                    toggle.innerHTML = isPwd ? '<i class="fas fa-eye-slash text-muted"></i>' : '<i class="fas fa-eye text-muted"></i>';
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
        })();
    </script>
</body>
</html>
