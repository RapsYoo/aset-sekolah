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
                $user['name'] = $name; // Update local var
            }
        }
    }
}

$page_title = 'Profil Saya';
require_once 'inc/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-user-circle me-2"></i>Profil Saya</h5>
            </div>
            <div class="card-body p-4">
                <form method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                    <div class="text-center mb-4">
                        <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 80px; height: 80px;">
                            <span class="display-5 text-primary fw-bold"><?php echo strtoupper(substr($user['name'], 0, 1)); ?></span>
                        </div>
                        <h5 class="mb-0"><?php echo escape($user['name']); ?></h5>
                        <small class="text-muted"><?php echo escape($user['email']); ?></small>
                        <div class="mt-2">
                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 rounded-pill px-3">
                                <?php echo ucfirst(escape($user['role'])); ?>
                            </span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="name" class="form-label">Nama Lengkap</label>
                        <input type="text" class="form-control" id="name" name="name"
                               value="<?php echo escape($user['name']); ?>" required>
                        <div class="invalid-feedback">Nama harus diisi.</div>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control bg-light" id="email"
                               value="<?php echo escape($user['email']); ?>" disabled>
                    </div>

                    <hr class="my-4">

                    <h6 class="mb-3 text-primary"><i class="fas fa-lock me-2"></i>Ubah Password</h6>

                    <div class="mb-4">
                        <label for="password" class="form-label">Password Baru</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password" name="password"
                                   placeholder="Kosongkan jika tidak diubah" minlength="6">
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback">Minimal 6 karakter.</div>
                        <div class="form-text">Minimal 6 karakter.</div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-between align-items-center">
                        <a href="dashboard.php" class="btn btn-light text-muted">Batal</a>
                        <button type="submit" class="btn btn-primary px-4" id="btnSubmit">
                            <i class="fas fa-save me-2"></i>Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

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
                toggle.innerHTML = isPwd ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>';
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

<?php require_once 'inc/footer.php'; ?>
