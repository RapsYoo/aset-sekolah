<?php
require_once '../inc/auth.php';
require_once '../inc/helpers.php';

require_admin();

$id = (int)($_GET['id'] ?? 0);
$user_data = db_fetch_one("SELECT u.*, r.name as role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id = ?", 'i', [$id]);

if (!$user_data) {
    header("Location: users.php");
    exit();
}

$error = '';
$success = '';
$roles = db_fetch_all("SELECT * FROM roles");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token CSRF tidak valid';
    } else {
        $name = sanitize($_POST['name'] ?? '');
        $role_id = (int)($_POST['role_id'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if (empty($name) || empty($role_id)) {
            $error = 'Semua field harus diisi';
        } else {
            $stmt = db_prepare("UPDATE users SET name = ?, role_id = ?, is_active = ? WHERE id = ?");
            $stmt->bind_param('siii', $name, $role_id, $is_active, $id);
            
            if ($stmt->execute()) {
                $success = 'User berhasil diperbarui!';
                $user_data['name'] = $name;
                $user_data['is_active'] = $is_active;
            } else {
                $error = 'Gagal memperbarui user!';
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
    <title>Edit User - Sistem Monitoring Aset Sekolah</title>
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
            <a class="navbar-brand" href="../dashboard.php">
                <i class="fas fa-chart-bar me-2"></i>Monitoring Aset Sekolah
            </a>
            <a class="nav-link text-white" href="users.php">
                <i class="fas fa-arrow-left me-2"></i>Kembali
            </a>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                        <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Edit User</h5>
                    </div>
                    <div class="card-body">
                        

                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                            <div class="mb-3">
                                <label for="name" class="form-label">Nama</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo escape($user_data['name']); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" 
                                       value="<?php echo escape($user_data['email']); ?>" disabled>
                                <small class="text-muted">Email tidak dapat diubah</small>
                            </div>

                            <div class="mb-3">
                                <label for="role_id" class="form-label">Role</label>
                                <select class="form-select" id="role_id" name="role_id" required>
                                    <?php foreach ($roles as $role): ?>
                                        <option value="<?php echo $role['id']; ?>" 
                                            <?php echo $user_data['role_name'] === $role['name'] ? 'selected' : ''; ?>>
                                            <?php echo escape($role['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="is_active" name="is_active"
                                       <?php echo $user_data['is_active'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_active">
                                    Aktif
                                </label>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-between">
                                <a href="users.php" class="btn btn-secondary">Batal</a>
                                <button type="submit" class="btn" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
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
        <script>
            (function () {
                const toastEl = document.getElementById('mainToast');
                if (toastEl && typeof bootstrap !== 'undefined') {
                    new bootstrap.Toast(toastEl).show();
                }
            })();
        </script>
    <?php endif; ?>
</body>
</html>
