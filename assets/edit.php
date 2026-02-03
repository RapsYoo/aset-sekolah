<?php
require_once '../inc/auth.php';
require_once '../inc/helpers.php';
require_once '../inc/storage.php';

require_login();
require_can_edit();
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

$page_title = 'Edit Aset';
require_once '../inc/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header bg-warning text-dark bg-opacity-10 border-warning">
                <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Edit Data Aset</h5>
            </div>
            <div class="card-body p-4">

                <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                    <div class="mb-3">
                        <label class="form-label text-muted small text-uppercase fw-bold">Sekolah</label>
                        <input type="text" class="form-control bg-light"
                               value="<?php echo escape($asset['unit_name'] ?? 'N/A'); ?> (<?php echo escape($asset['unit_code'] ?? ''); ?>)"
                               disabled>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label text-muted small text-uppercase fw-bold">Jenis KIB</label>
                            <input type="text" class="form-control bg-light" value="KIB <?php echo escape($asset['kib_type']); ?>" disabled>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small text-uppercase fw-bold">Bulan</label>
                            <input type="text" class="form-control bg-light" value="<?php echo get_month_name($asset['month']); ?>" disabled>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small text-uppercase fw-bold">Tahun</label>
                            <input type="text" class="form-control bg-light" value="<?php echo $asset['year']; ?>" disabled>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="total" class="form-label fw-bold">Total Aset</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="number" class="form-control form-control-lg fw-bold text-primary" id="total" name="total"
                                   value="<?php echo $asset['total']; ?>" min="0" required>
                        </div>
                        <div class="invalid-feedback">Masukkan angka minimal 0.</div>
                        <div class="form-text text-primary fw-bold" id="totalFormatted"></div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Upload Dokumen Tambahan (Opsional)</label>
                        <div id="dz" class="p-4 text-center border border-2 border-dashed rounded bg-light" style="cursor: pointer; border-color: #dee2e6;">
                            <div class="mb-2">
                                <i class="fas fa-cloud-upload-alt fa-2x text-primary opacity-50"></i>
                            </div>
                            <span class="text-muted">Klik atau tarik file ke sini</span>
                            <input type="file" name="file" id="fileInput" accept=".pdf,.xlsx,.xls" class="d-none">
                        </div>
                        <div id="fileName" class="form-text text-success fw-bold mt-2"></div>
                        <div class="form-text">Maksimal 10 MB. Tipe: PDF, XLS, XLSX.</div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-between align-items-center">
                        <a href="detail.php?kib=<?php echo $asset['kib_type']; ?>&year=<?php echo $asset['year']; ?>&month=<?php echo $asset['month']; ?>" class="btn btn-light text-muted">Batal</a>
                        <button type="submit" class="btn btn-warning px-4" id="btnSubmit">
                            <i class="fas fa-save me-2"></i>Simpan Perubahan
                        </button>
                    </div>
                </form>

                <?php if (!empty($docs)): ?>
                    <hr class="my-4">
                    <h6 class="mb-3 text-muted text-uppercase small fw-bold"><i class="fas fa-file-alt me-2"></i>Dokumen Terkait</h6>
                    <div class="list-group list-group-flush">
                        <?php foreach ($docs as $doc): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <div class="d-flex align-items-center overflow-hidden me-3">
                                    <div class="bg-light rounded p-2 me-3">
                                        <i class="fas fa-file-pdf text-danger"></i>
                                    </div>
                                    <div class="text-truncate">
                                        <a href="download.php?key=<?php echo urlencode($doc['key']); ?>" class="text-dark text-decoration-none fw-medium text-truncate d-block">
                                            <?php echo escape($doc['name']); ?>
                                        </a>
                                        <small class="text-muted"><?php echo number_format($doc['size'] / 1024, 0, ',', '.'); ?> KB • <?php echo date('d/m/y', $doc['mtime']); ?></small>
                                    </div>
                                </div>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                    <input type="hidden" name="delete_key" value="<?php echo escape($doc['key']); ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus dokumen ini?')" title="Hapus Dokumen">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

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
                totalFormatted.textContent = v ? 'Rp ' + fmt.format(v) : '';
            };
            total.addEventListener('input', update);
            update();
        }

        if (dz && input) {
            dz.addEventListener('click', () => input.click());

            dz.addEventListener('dragover', (e) => {
                e.preventDefault();
                dz.classList.remove('bg-light');
                dz.classList.add('bg-white', 'border-primary');
            });

            dz.addEventListener('dragleave', () => {
                dz.classList.add('bg-light');
                dz.classList.remove('bg-white', 'border-primary');
            });

            dz.addEventListener('drop', (e) => {
                e.preventDefault();
                dz.classList.add('bg-light');
                dz.classList.remove('bg-white', 'border-primary');

                if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                    input.files = e.dataTransfer.files;
                    fileName.textContent = 'File terpilih: ' + e.dataTransfer.files[0].name;
                }
            });

            input.addEventListener('change', () => {
                if (input.files.length > 0) {
                    fileName.textContent = 'File terpilih: ' + input.files[0].name;
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
    })();
</script>

<?php require_once '../inc/footer.php'; ?>
