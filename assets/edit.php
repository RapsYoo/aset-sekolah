<?php
require_once '../inc/auth.php';
require_once '../inc/helpers.php';
require_once '../inc/storage.php';

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

// List dokumen terkait
$unitCode = $asset['unit_code'] ?? 'general';
$docs = storage_list_documents($asset['year'], $asset['kib_type'], $unitCode);

// Handle update
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token CSRF tidak valid';
    } else if (isset($_POST['delete_key'])) {
        $key = sanitize($_POST['delete_key'] ?? '');
        if (!empty($key) && storage_delete($key)) {
            redirect_with_message("edit.php?id={$asset['id']}", 'Dokumen berhasil dihapus!', 'success');
        } else {
            $error = 'Gagal menghapus dokumen';
        }
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
                $uploadMsg = '';
                if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['file'];
                    $allowedExt = ['pdf', 'xls', 'xlsx'];
                    $maxMb = 10;
                    $sizeOk = ($file['size'] <= $maxMb * 1024 * 1024);
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $detected = finfo_file($finfo, $file['tmp_name']);
                    finfo_close($finfo);
                    $mime = $detected ?: 'application/octet-stream';
                    if ($sizeOk && in_array($ext, $allowedExt)) {
                        $uid = bin2hex(random_bytes(16));
                        $unitCode = $asset['unit_code'] ?? 'general';
                        $key = sprintf('documents/%d/%s/%s/%s.%s', (int)$asset['year'], $asset['kib_type'], $unitCode, $uid, $ext ?: 'bin');
                        if (storage_put($key, $file['tmp_name'], $mime)) {
                            $checkDocs = db_fetch_one("SHOW TABLES LIKE 'documents'");
                            if ($checkDocs) {
                                $stmt2 = db_prepare("INSERT INTO documents (unit_id, kib_type, year, month, original_filename, object_key, mime_type, size, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                                $stmt2->bind_param('isiisssii', $asset['unit_id'], $asset['kib_type'], $asset['year'], $asset['month'], $file['name'], $key, $mime, $file['size'], $user['id']);
                                $stmt2->execute();
                            }
                            $uploadMsg = ' dan dokumen terunggah';
                        }
                    }
                }
                redirect_with_message("detail.php?kib={$asset['kib_type']}&year={$asset['year']}&month={$asset['month']}", 'Perubahan berhasil disimpan' . $uploadMsg . '!', 'success');
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

                        <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
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
                                <div class="invalid-feedback">Masukkan angka minimal 0.</div>
                                <div class="form-text" id="totalFormatted"></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Upload Dokumen (Opsional)</label>
                                <div id="dz" class="dropzone mb-2" style="border: 2px dashed #667eea; border-radius: 10px; padding: 20px; text-align: center; background: #f8f9ff; color: #444; cursor: pointer;">
                                    <div>
                                        <i class="fas fa-cloud-upload-alt me-2"></i>
                                        <span>Tarik & lepas file PDF/XLS/XLSX ke sini atau klik untuk memilih</span>
                                    </div>
                                    <input type="file" name="file" id="fileInput" accept=".pdf,.xlsx,.xls" class="d-none">
                                </div>
                                <div id="fileName" class="form-text"></div>
                                <div class="form-text">Maksimal 10 MB. Tipe: PDF, XLS, XLSX.</div>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-between">
                                <a href="detail.php?kib=<?php echo $asset['kib_type']; ?>&year=<?php echo $asset['year']; ?>&month=<?php echo $asset['month']; ?>" class="btn btn-secondary">Batal</a>
                                <button type="submit" class="btn" id="btnSubmit" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                                    <i class="fas fa-save me-2"></i>Simpan Perubahan
                                </button>
                            </div>
                        </form>
                        <hr class="my-4">
                        <h6 class="mb-2"><i class="fas fa-file-alt me-2"></i>Dokumen Terkait</h6>
                        <?php if (!empty($docs)): ?>
                            <div class="list-group">
                                <?php foreach ($docs as $doc): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <a href="download.php?key=<?php echo urlencode($doc['key']); ?>" class="text-decoration-none">
                                                <i class="fas fa-file me-2"></i><?php echo escape($doc['name']); ?>
                                            </a>
                                            <small class="text-muted ms-2"><?php echo number_format($doc['size'], 0, ',', '.'); ?> B • <?php echo date('d/m/Y H:i', $doc['mtime']); ?></small>
                                        </div>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                            <input type="hidden" name="delete_key" value="<?php echo escape($doc['key']); ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Hapus dokumen ini?')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-muted">Belum ada dokumen untuk data ini.</div>
                        <?php endif; ?>
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
            const total = document.getElementById('total');
            const totalFormatted = document.getElementById('totalFormatted');
            const dz = document.getElementById('dz');
            const input = document.getElementById('fileInput');
            const fileName = document.getElementById('fileName');
            if (total && totalFormatted) {
                const fmt = new Intl.NumberFormat('id-ID');
                const update = () => {
                    const v = parseInt(total.value || '0', 10);
                    totalFormatted.textContent = v ? 'Nilai: Rp ' + fmt.format(v) : '';
                };
                total.addEventListener('input', update);
                update();
            }
            if (dz && input) {
                dz.addEventListener('click', () => input.click());
                dz.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    dz.style.background = '#eef2ff';
                });
                dz.addEventListener('dragleave', () => {
                    dz.style.background = '#f8f9ff';
                });
                dz.addEventListener('drop', (e) => {
                    e.preventDefault();
                    dz.style.background = '#f8f9ff';
                    if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                        input.files = e.dataTransfer.files;
                        fileName.textContent = e.dataTransfer.files[0].name;
                    }
                });
                input.addEventListener('change', () => {
                    if (input.files.length > 0) {
                        fileName.textContent = input.files[0].name;
                    }
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
