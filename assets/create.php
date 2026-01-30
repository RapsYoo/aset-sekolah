<?php
require_once '../inc/auth.php';
require_once '../inc/helpers.php';
require_once '../inc/storage.php';

require_login();
$user = current_user();
$months = get_months();
$kib_types = get_kib_types();
$units = get_units();

// Ambil parameter dari query string (jika ada)
$prefill_kib = sanitize($_GET['kib'] ?? '');
$prefill_year = (int)($_GET['year'] ?? date('Y'));
$prefill_month = (int)($_GET['month'] ?? 0);

// Handle form submission
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token CSRF tidak valid';
    } else {
        $kib = sanitize($_POST['kib'] ?? '');
        $year = (int)($_POST['year'] ?? '');
        $month = (int)($_POST['month'] ?? '');
        $unit_id = (int)($_POST['unit_id'] ?? 0);
        $total = (int)($_POST['total'] ?? '');

        if (empty($kib) || empty($year) || empty($month) || empty($total) || empty($unit_id)) {
            $error = 'Semua field harus diisi';
        } elseif ($total < 0) {
            $error = 'Total aset tidak boleh negatif';
        } else {
            $stmt = db_prepare(
                "INSERT INTO assets_monthly (kib_type, year, month, unit_id, total, created_by) 
                 VALUES (?, ?, ?, ?, ?, ?) 
                 ON DUPLICATE KEY UPDATE total = ?, updated_at = NOW()"
            );
            $stmt->bind_param('siiiiii', $kib, $year, $month, $unit_id, $total, $user['id'], $total);
            
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
                        $unitCode = 'general';
                        $u = db_fetch_one("SELECT code FROM units WHERE id = ?", 'i', [$unit_id]);
                        if (!empty($u['code'])) $unitCode = $u['code'];
                        $key = sprintf('documents/%d/%s/%s/%s.%s', (int)$year, $kib, $unitCode, $uid, $ext ?: 'bin');
                        if (storage_put($key, $file['tmp_name'], $mime)) {
                            $checkDocs = db_fetch_one("SHOW TABLES LIKE 'documents'");
                            if ($checkDocs) {
                                $stmt2 = db_prepare("INSERT INTO documents (unit_id, kib_type, year, month, original_filename, object_key, mime_type, size, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                                $stmt2->bind_param('isiisssii', $unit_id, $kib, $year, $month, $file['name'], $key, $mime, $file['size'], $user['id']);
                                $stmt2->execute();
                            }
                            $uploadMsg = ' dan dokumen terunggah';
                        }
                    }
                }
                if (!empty($prefill_kib) && !empty($prefill_year) && !empty($prefill_month)) {
                    redirect_with_message("detail.php?kib=$kib&year=$year&month=$month", 'Data aset berhasil disimpan' . $uploadMsg . '!', 'success');
                } else {
                    $success = 'Data aset berhasil disimpan' . $uploadMsg . '!';
                }
            } else {
                $error = 'Gagal menyimpan data: ' . $stmt->error;
            }
        }
    }
}

$page_title = 'Input Aset';
require_once '../inc/header.php';
?>

<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    /* Select2 Bootstrap 5 Theme Fixes */
    .select2-container--default .select2-selection--single {
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
        height: 38px;
        padding: 0.375rem 0.75rem;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 36px;
        top: 1px;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 24px;
        padding-left: 0;
        color: #212529;
    }
</style>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-plus me-2"></i>Input Data Aset</h5>
            </div>
            <div class="card-body p-4">
                <p class="text-muted small mb-4">Masukkan rekap aset per bulan untuk setiap unit/sekolah.</p>

                <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                    <div class="mb-3">
                        <label for="unit_id" class="form-label">Unit / Sekolah</label>
                        <select class="form-select" id="unit_id" name="unit_id" required>
                            <option value="">-- Pilih Unit / Sekolah --</option>
                            <?php foreach ($units as $unit): ?>
                                <option value="<?php echo $unit['id']; ?>" <?php echo (isset($_POST['unit_id']) && (int)$_POST['unit_id'] === (int)$unit['id']) ? 'selected' : ''; ?>>
                                    <?php echo escape($unit['name']); ?> (<?php echo escape($unit['code']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Ketik nama sekolah untuk mencari lebih cepat.</div>
                        <div class="invalid-feedback">Pilih unit/sekolah.</div>
                    </div>

                    <div class="mb-3">
                        <label for="kib" class="form-label">Jenis KIB</label>
                        <select class="form-select" id="kib" name="kib" required>
                            <option value="">-- Pilih KIB --</option>
                            <?php foreach ($kib_types as $k): ?>
                                <option value="<?php echo $k; ?>" <?php echo (isset($_POST['kib']) ? ($_POST['kib'] == $k) : ($prefill_kib == $k)) ? 'selected' : ''; ?>>
                                    KIB <?php echo $k; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Pilih jenis KIB.</div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="year" class="form-label">Tahun</label>
                            <input type="number" class="form-control" id="year" name="year"
                                   value="<?php echo isset($_POST['year']) ? (int)$_POST['year'] : ($prefill_year ?: date('Y')); ?>" min="2020" max="2099" required>
                            <div class="invalid-feedback">Masukkan tahun valid.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="month" class="form-label">Bulan</label>
                            <select class="form-select" id="month" name="month" required>
                                <option value="">-- Pilih Bulan --</option>
                                <?php foreach ($months as $m => $name): ?>
                                    <option value="<?php echo $m; ?>" <?php echo (isset($_POST['month']) ? ((int)$_POST['month'] === (int)$m) : ($prefill_month == $m)) ? 'selected' : ''; ?>>
                                        <?php echo $name; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Pilih bulan.</div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="total" class="form-label">Total Aset</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="number" class="form-control" id="total" name="total"
                                   min="0" placeholder="0" required value="<?php echo isset($_POST['total']) ? (int)$_POST['total'] : ''; ?>">
                        </div>
                        <div class="invalid-feedback">Masukkan angka minimal 0.</div>
                        <div class="form-text text-primary fw-bold" id="totalFormatted"></div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Upload Dokumen (Opsional)</label>
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

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary" id="btnSubmit">
                            <i class="fas fa-save me-2"></i>Simpan Data
                        </button>
                        <a href="index.php" class="btn btn-light text-muted">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    $(document).ready(function () {
        $('#unit_id').select2({
            placeholder: '-- Pilih Unit / Sekolah --',
            allowClear: true,
            width: '100%'
        });
    });

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
