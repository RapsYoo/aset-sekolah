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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Aset - Sistem Monitoring Aset Sekolah</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        :root {
            --primary: #667eea;
            --secondary: #764ba2;
        }
        .navbar {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        }
        body {
            background: radial-gradient(circle at top left, #eef2ff 0, #f8f9fa 45%, #fdf2ff 100%);
        }
        .card {
            border: none;
            border-radius: 16px;
            overflow: hidden;
        }
        .card-header {
            border-bottom: none;
        }
        .card-header h5 {
            font-weight: 600;
            letter-spacing: .3px;
        }
        .card-body {
            padding: 1.75rem 2rem 2rem;
        }
        .form-label {
            font-weight: 500;
            color: #4b5563;
        }
        .form-control, .form-select {
            border-radius: 10px;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 .15rem rgba(102, 126, 234, .25);
        }
        .btn-gradient {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: #fff;
            border: none;
            border-radius: 999px;
            font-weight: 500;
            box-shadow: 0 10px 25px rgba(102, 126, 234, .35);
        }
        .btn-gradient:hover {
            filter: brightness(1.03);
            color: #fff;
        }
        /* Select2 styling */
        .select2-container--default .select2-selection--single {
            height: 42px;
            padding: 6px 10px;
            border-radius: 10px;
            border: 1px solid #ced4da;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 28px;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px;
        }
        .select2-container--default.select2-container--focus .select2-selection--single {
            border-color: var(--primary);
            box-shadow: 0 0 0 .15rem rgba(102, 126, 234, .25);
        }
        .form-text {
            font-size: 0.8rem;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-dark navbar-expand-lg" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
        <div class="container-fluid">
            <a class="navbar-brand" href="../dashboard.php">
                <i class="fas fa-chart-bar me-2"></i>Monitoring Aset Sekolah
            </a>
            <a class="nav-link text-white" href="../dashboard.php">
                <i class="fas fa-arrow-left me-2"></i>Kembali ke Dashboard
            </a>
        </div>
    </nav>

    <div class="container py-5">
        <div class="row justify-content-center align-items-center">
            <div class="col-md-7 col-lg-6">
                <div class="card shadow-lg">
                    <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                        <h5 class="mb-1"><i class="fas fa-plus me-2"></i>Input Data Aset</h5>
                        <small class="text-white-50">Masukkan rekap aset per bulan untuk setiap unit/sekolah.</small>
                    </div>
                    <div class="card-body">

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

                            <div class="mb-3">
                                <label for="year" class="form-label">Tahun</label>
                                <input type="number" class="form-control" id="year" name="year" 
                                       value="<?php echo isset($_POST['year']) ? (int)$_POST['year'] : ($prefill_year ?: date('Y')); ?>" min="2020" max="2099" required>
                                <div class="invalid-feedback">Masukkan tahun antara 2020–2099.</div>
                            </div>

                            <div class="mb-3">
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

                            <div class="mb-4">
                                <label for="total" class="form-label">Total Aset</label>
                                <input type="number" class="form-control" id="total" name="total" 
                                       min="0" placeholder="Contoh: 1500" required value="<?php echo isset($_POST['total']) ? (int)$_POST['total'] : ''; ?>">
                                <div class="invalid-feedback">Masukkan angka minimal 0.</div>
                                <div class="form-text" id="totalFormatted"></div>
                            </div>
                            <div class="mb-4">
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

                            <button type="submit" class="btn btn-gradient w-100 py-2" id="btnSubmit">
                                <i class="fas fa-save me-2"></i>Simpan Data Aset
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <?php
        $toastMessage = '';
        $toastType = 'info';
        if (!empty($success)) {
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
    <?php endif; ?>
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
