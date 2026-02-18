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
     WHERE r.name != 'pengembang'
     ORDER BY u.created_at DESC"
);

// Ambil roles (sembunyikan pengembang)
$roles = db_fetch_all("SELECT * FROM roles WHERE name != 'pengembang'");

$page_title = 'Manajemen User';
require_once '../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="fas fa-users-cog me-2"></i>Manajemen Pengguna</h4>
        <p class="text-muted mb-0">Kelola akun akses sistem</p>
    </div>
</div>

<!-- Form Tambah User -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-user-plus me-2 text-primary"></i>Tambah Pengguna Baru</h5>
    </div>
    <div class="card-body">
        <form method="POST" class="row g-3 needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="action" value="add_user">

            <div class="col-md-3">
                <label class="form-label small text-muted">Nama Lengkap</label>
                <input type="text" class="form-control" name="name" placeholder="Contoh: Budi Santoso" required>
                <div class="invalid-feedback">Nama harus diisi.</div>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted">Email</label>
                <input type="email" class="form-control" name="email" placeholder="email@sekolah.com" required>
                <div class="invalid-feedback">Email tidak valid.</div>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted">Password</label>
                <div class="input-group">
                    <input type="password" class="form-control" name="password" id="password" placeholder="Minimal 6 karakter" required minlength="6">
                    <button class="btn btn-outline-secondary" type="button" id="togglePassword"><i class="fas fa-eye"></i></button>
                </div>
                <div class="invalid-feedback">Password minimal 6 karakter.</div>
                <div class="form-text" id="pwdStrength"></div>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">Role</label>
                <select class="form-select" name="role_id" required>
                    <option value="">-- Pilih --</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?php echo $role['id']; ?>"><?php echo escape($role['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="invalid-feedback">Pilih role.</div>
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100" id="btnSubmit">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Daftar User -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-list me-2 text-primary"></i>Daftar Pengguna</h5>
        <div class="input-group" style="max-width: 300px;">
            <span class="input-group-text bg-light border-end-0"><i class="fas fa-search text-muted"></i></span>
            <input type="text" class="form-control border-start-0 bg-light" id="searchUser" placeholder="Cari nama, email, role...">
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Nama</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Terdaftar</th>
                        <th class="pe-4 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody id="userTableBody">
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center">
                                    <div class="bg-light rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 32px; height: 32px;">
                                        <i class="fas fa-user text-secondary"></i>
                                    </div>
                                    <span class="fw-medium"><?php echo escape($u['name']); ?></span>
                                </div>
                            </td>
                            <td><?php echo escape($u['email']); ?></td>
                            <td>
                                <span class="badge <?php echo $u['role_name'] === 'admin' ? 'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25' : 'bg-info bg-opacity-10 text-info border border-info border-opacity-25'; ?> rounded-pill px-3">
                                    <?php echo escape($u['role_name']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($u['is_active']): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill">Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 rounded-pill">Nonaktif</span>
                                <?php endif; ?>
                            </td>
                            <td><small class="text-muted"><?php echo format_date($u['created_at']); ?></small></td>
                            <td class="pe-4 text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="edit_user.php?id=<?php echo $u['id']; ?>" class="btn btn-outline-warning" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="delete_user.php?id=<?php echo $u['id']; ?>" class="btn btn-outline-danger"
                                       onclick="return confirm('Yakin hapus user <?php echo escape($u['name']); ?>?')" title="Hapus">
                                        <i class="fas fa-trash"></i>
                                    </a>
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
        const btn = document.getElementById('btnSubmit');
        const pwd = document.getElementById('password');
        const toggle = document.getElementById('togglePassword');
        const strength = document.getElementById('pwdStrength');

        if (toggle && pwd) {
            toggle.addEventListener('click', function () {
                const isPwd = pwd.getAttribute('type') === 'password';
                pwd.setAttribute('type', isPwd ? 'text' : 'password');
                toggle.innerHTML = isPwd ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>';
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
                const colors = ['text-danger', 'text-warning', 'text-info', 'text-primary', 'text-success'];

                if (v) {
                    strength.textContent = levels[Math.max(0, score - 1)];
                    strength.className = 'form-text fw-bold ' + colors[Math.max(0, score - 1)];
                } else {
                    strength.textContent = '';
                }
            };
            pwd.addEventListener('input', updateStrength);
        }

        if (form) {
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                } else {
                    if (btn) {
                        btn.disabled = true;
                        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
                    }
                }
                form.classList.add('was-validated');
            }, false);
        }
    })();

    // Search filter
    const searchUser = document.getElementById('searchUser');
    if (searchUser) {
        searchUser.addEventListener('input', function() {
            const query = this.value.toLowerCase();
            const rows = document.querySelectorAll('#userTableBody tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        });
    }
</script>

<?php require_once '../inc/footer.php'; ?>
