<?php
require_once '../inc/auth.php';
require_once '../inc/helpers.php';
require_once '../inc/storage.php';

require_login();
require_can_edit();
$user = current_user();
$units = get_units();
$conditions = get_item_conditions();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token CSRF tidak valid';
    } else {
        $unit_id = (int)($_POST['unit_id'] ?? 0);
        $item_name = sanitize($_POST['item_name'] ?? '');
        $condition = sanitize($_POST['condition'] ?? '');

        if ($unit_id <= 0 || empty($item_name) || empty($condition)) {
            $error = 'Semua field harus diisi';
        } elseif (!array_key_exists($condition, $conditions)) {
            $error = 'Kondisi barang tidak valid';
        } else {
            $stmt = db_prepare("INSERT INTO items (unit_id, item_name, `condition`, created_by) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('issi', $unit_id, $item_name, $condition, $user['id']);
            if ($stmt->execute()) {
                $uploadMsg = '';
                if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['photo'];
                    $allowedExt = ['jpg', 'jpeg', 'png'];
                    $maxMb = 5;
                    $sizeOk = ($file['size'] <= $maxMb * 1024 * 1024);
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $detected = finfo_file($finfo, $file['tmp_name']);
                    finfo_close($finfo);
                    $mime = $detected ?: 'application/octet-stream';
                    $mimeOk = in_array($mime, ['image/jpeg', 'image/png']);
                    if ($sizeOk && in_array($ext, $allowedExt) && $mimeOk) {
                        global $conn;
                        $newId = $conn->insert_id;
                        $u = db_fetch_one("SELECT code FROM units WHERE id = ?", 'i', [$unit_id]);
                        $unitCode = !empty($u['code']) ? $u['code'] : 'general';
                        $uid = bin2hex(random_bytes(8));
                        $key = sprintf('items/photos/%s/%d_%s.%s', $unitCode, (int)$newId, $uid, $ext ?: 'bin');
                        if (storage_put($key, $file['tmp_name'], $mime)) {
                            $stmt2 = db_prepare("UPDATE items SET photo_key = ?, photo_mime = ?, photo_size = ? WHERE id = ?");
                            $stmt2->bind_param('ssii', $key, $mime, $file['size'], $newId);
                            $stmt2->execute();
                            $uploadMsg = ' dan foto terunggah';
                        }
                    }
                }
                $_SESSION['flash_message'] = 'Barang berhasil ditambahkan' . $uploadMsg . '!';
                $_SESSION['flash_type'] = 'success';
                header("Location: index.php");
                exit();
            } else {
                $error = 'Gagal menyimpan data: ' . $stmt->error;
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
    <title>Tambah Barang - Sistem Monitoring Aset Sekolah</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #667eea; --secondary: #764ba2; }
        .navbar { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); }
        body { background: #f8f9fa; }
        .card { box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
    </style>
    <script>
        (function () {
            'use strict';
            window.addEventListener('load', function () {
                var forms = document.getElementsByClassName('needs-validation');
                Array.prototype.filter.call(forms, function (form) {
                    form.addEventListener('submit', function (event) {
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();
    </script>
<?php if (!empty($error)): ?>
    <script>console.error(<?php echo json_encode($error); ?>);</script>
<?php endif; ?>
</head>
<body>
    <nav class="navbar navbar-dark navbar-expand-lg">
        <div class="container-fluid">
            <a class="navbar-brand" href="../dashboard.php">
                <i class="fas fa-chart-bar me-2"></i>Monitoring Aset Sekolah
            </a>
            <a class="nav-link text-white" href="index.php">
                <i class="fas fa-arrow-left me-2"></i>Kembali
            </a>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                        <h5 class="mb-1"><i class="fas fa-plus me-2"></i>Tambah Barang</h5>
                        <small class="text-white-50">Masukkan data barang dan kondisinya.</small>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger"><?php echo escape($error); ?></div>
                        <?php endif; ?>
                        <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                            <div class="mb-3">
                                <label class="form-label">Unit / Sekolah</label>
                                <select class="form-select" name="unit_id" required>
                                    <option value="">-- Pilih Unit / Sekolah --</option>
                                    <?php foreach ($units as $u): ?>
                                        <option value="<?php echo $u['id']; ?>">
                                            <?php echo escape($u['name']); ?> (<?php echo escape($u['code']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Pilih unit/sekolah.</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Jenis Barang</label>
                                <input type="text" class="form-control" name="item_name" required placeholder="Contoh: Meja Siswa, Proyektor">
                                <div class="invalid-feedback">Isi jenis/nama barang.</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Kondisi Barang</label>
                                <select class="form-select" name="condition" required>
                                    <option value="">-- Pilih Kondisi --</option>
                                    <?php foreach ($conditions as $key => $label): ?>
                                        <option value="<?php echo $key; ?>"><?php echo escape($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Pilih kondisi barang.</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Foto Bukti Barang (jpg/png, maks 5MB)</label>
                                <input type="file" class="form-control" name="photo" accept=".jpg,.jpeg,.png,image/jpeg,image/png">
                                <div class="form-text">Opsional: unggah foto sebagai bukti.</div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-save me-1"></i>Simpan
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
