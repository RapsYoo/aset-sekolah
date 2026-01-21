<?php
require_once '../inc/auth.php';
require_once '../inc/helpers.php';

require_login();
$user = current_user();
$months = get_months();
$kib_types = get_kib_types();

$id = (int)($_GET['id'] ?? 0);
$asset = db_fetch_one(
    "SELECT a.*, un.name as unit_name, un.code as unit_code
     FROM assets_monthly a
     LEFT JOIN units un ON a.unit_id = un.id
     WHERE a.id = ?",
    'i',
    [$id]
);

if (!$asset) {
    header("Location: index.php");
    exit();
}

// Handle update
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token CSRF tidak valid';
    } else {
        $total = (int)($_POST['total'] ?? '');

        if (empty($total)) {
            $error = 'Total aset harus diisi';
        } elseif ($total < 0) {
            $error = 'Total aset tidak boleh negatif';
        } else {
            $stmt = db_prepare("UPDATE assets_monthly SET total = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param('ii', $total, $id);
            
            if ($stmt->execute()) {
                // Redirect ke halaman detail
                header("Location: detail.php?kib={$asset['kib_type']}&year={$asset['year']}&month={$asset['month']}&success=1");
                exit();
            } else {
                $error = 'Gagal memperbarui data: ' . $stmt->error;
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
    <title>Edit Aset - Sistem Monitoring Aset Sekolah</title>
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
    </style>
</head>
<body class="bg-light">
    <!-- Navbar -->
    <nav class="navbar navbar-dark navbar-expand-lg">
        <div class="container-fluid">
            <a class="navbar-brand" href="../dashboard.php">
                <i class="fas fa-chart-bar me-2"></i>Monitoring Aset Sekolah
            </a>
            <a class="nav-link text-white" href="detail.php?kib=<?php echo $asset['kib_type']; ?>&year=<?php echo $asset['year']; ?>&month=<?php echo $asset['month']; ?>">
                <i class="fas fa-arrow-left me-2"></i>Kembali
            </a>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                        <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Edit Data Aset</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <?php echo escape($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        

                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                            <div class="mb-3">
                                <label class="form-label">Sekolah</label>
                                <input type="text" class="form-control" 
                                       value="<?php echo escape($asset['unit_name'] ?? 'N/A'); ?> (<?php echo escape($asset['unit_code'] ?? ''); ?>)" 
                                       disabled>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Jenis KIB</label>
                                <input type="text" class="form-control" value="KIB <?php echo escape($asset['kib_type']); ?>" disabled>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Bulan</label>
                                <input type="text" class="form-control" value="<?php echo get_month_name($asset['month']); ?>" disabled>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Tahun</label>
                                <input type="text" class="form-control" value="<?php echo $asset['year']; ?>" disabled>
                            </div>

                            <div class="mb-3">
                                <label for="total" class="form-label">Total Aset</label>
                                <input type="number" class="form-control" id="total" name="total" 
                                       value="<?php echo $asset['total']; ?>" min="0" required>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-between">
                                <a href="detail.php?kib=<?php echo $asset['kib_type']; ?>&year=<?php echo $asset['year']; ?>&month=<?php echo $asset['month']; ?>" class="btn btn-secondary">Batal</a>
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
</body>
</html>
