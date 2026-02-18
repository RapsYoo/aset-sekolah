<?php
require_once '../inc/auth.php';
require_once '../inc/helpers.php';

require_admin();

$id = (int)($_GET['id'] ?? 0);
$user_data = db_fetch_one("SELECT u.*, r.name as role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id = ?", 'i', [$id]);

if (!$user_data || $user_data['role_name'] === 'pengembang') {
    header("Location: users.php");
    exit();
}

$error = '';
$success = '';
$roles = db_fetch_all("SELECT * FROM roles WHERE name != 'pengembang'");

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
                $user_data['role_id'] = $role_id; // update local data for display
                // refetch role name logic slightly complex here without query, so rely on select box logic
            } else {
                $error = 'Gagal memperbarui user!';
            }
        }
    }
}

$page_title = 'Edit User';
require_once '../inc/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header bg-warning text-dark bg-opacity-10 border-warning">
                <h5 class="mb-0"><i class="fas fa-user-edit me-2"></i>Edit Pengguna</h5>
            </div>
            <div class="card-body p-4">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                    <div class="mb-3">
                        <label for="name" class="form-label">Nama Lengkap</label>
                        <input type="text" class="form-control" id="name" name="name"
                               value="<?php echo escape($user_data['name']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control bg-light" id="email"
                               value="<?php echo escape($user_data['email']); ?>" disabled>
                        <div class="form-text"><i class="fas fa-info-circle me-1"></i>Email tidak dapat diubah.</div>
                    </div>

                    <div class="mb-3">
                        <label for="role_id" class="form-label">Role</label>
                        <select class="form-select" id="role_id" name="role_id" required>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?php echo $role['id']; ?>"
                                    <?php echo (int)$user_data['role_id'] === (int)$role['id'] ? 'selected' : ''; ?>>
                                    <?php echo escape($role['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active"
                                   <?php echo $user_data['is_active'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_active">Akun Aktif</label>
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-between align-items-center">
                        <a href="users.php" class="btn btn-light text-muted">Batal</a>
                        <button type="submit" class="btn btn-warning px-4">
                            <i class="fas fa-save me-2"></i>Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../inc/footer.php'; ?>
