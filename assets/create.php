<?php
require_once '../inc/auth.php';
require_once '../inc/helpers.php';
require_once '../inc/storage.php';

require_login();
require_can_edit();
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

// Siapkan units sebagai JSON untuk matching di JavaScript
$unitsJson = json_encode($units, JSON_UNESCAPED_UNICODE);

$page_title = 'Input Aset';
require_once '../inc/header.php';
?>

<style>
    /* ── Excel Upload Zone ─────────────────────────── */
    .excel-upload-card {
        background: linear-gradient(135deg, #f0f7ff 0%, #e8f0fe 100%);
        border: 2px dashed #93c5fd;
        border-radius: 16px;
        padding: 32px 28px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    .excel-upload-card:hover {
        border-color: #2563eb;
        background: linear-gradient(135deg, #dbeafe 0%, #c7d7f7 100%);
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(37, 99, 235, 0.15);
    }
    .excel-upload-card.drag-over {
        border-color: #2563eb;
        background: linear-gradient(135deg, #bfdbfe 0%, #a5c4f3 100%);
        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.15);
    }
    .excel-upload-card.has-file {
        border-style: solid;
        border-color: #10b981;
        background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
    }
    .excel-upload-card .upload-icon {
        font-size: 3rem;
        margin-bottom: 12px;
        display: block;
    }
    .excel-upload-card .upload-title {
        font-size: 1.05rem;
        font-weight: 700;
        color: #1e3a5f;
        margin-bottom: 4px;
    }
    .excel-upload-card .upload-subtitle {
        font-size: 0.85rem;
        color: #64748b;
    }
    .excel-upload-card .file-info {
        margin-top: 12px;
        font-size: 0.9rem;
        font-weight: 600;
        color: #059669;
    }

    /* ── Parsing Progress ──────────────────────────── */
    .parse-progress {
        display: none;
        margin-top: 16px;
    }
    .parse-progress .spinner-border {
        width: 1.2rem;
        height: 1.2rem;
    }

    /* ── Auto-filled indicator ─────────────────────── */
    .auto-filled {
        position: relative;
    }
    .auto-filled::after {
        content: '✓ Auto';
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 0.7rem;
        color: #059669;
        font-weight: 700;
        background: #ecfdf5;
        padding: 2px 8px;
        border-radius: 4px;
        border: 1px solid #a7f3d0;
    }

    /* ── Preview Section ───────────────────────────── */
    .preview-section {
        display: none;
        margin-top: 24px;
    }
    .preview-section.show {
        display: block;
        animation: fadeSlideIn 0.4s ease;
    }
    @keyframes fadeSlideIn {
        from { opacity: 0; transform: translateY(12px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .preview-stats {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        gap: 12px;
        margin-bottom: 20px;
    }
    .preview-stat {
        background: #fff;
        border-radius: 12px;
        padding: 16px;
        text-align: center;
        border: 1px solid #e2e8f0;
        box-shadow: 0 1px 4px rgba(0,0,0,0.04);
    }
    .preview-stat .stat-icon { font-size: 1.5rem; margin-bottom: 6px; }
    .preview-stat .stat-label { font-size: 0.72rem; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
    .preview-stat .stat-value { font-size: 1.1rem; font-weight: 800; color: #1e293b; margin-top: 2px; }

    .preview-table-wrap {
        max-height: 350px;
        overflow-y: auto;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
    }
    .preview-table-wrap table {
        font-size: 0.82rem;
        margin-bottom: 0;
    }
    .preview-table-wrap thead th {
        position: sticky;
        top: 0;
        background: #1e3a5f;
        color: #fff;
        font-weight: 600;
        font-size: 0.78rem;
        white-space: nowrap;
        z-index: 2;
    }
    .preview-table-wrap tbody td { vertical-align: middle; }
    .preview-table-wrap .td-harga { color: #059669; font-weight: 700; white-space: nowrap; }
    .preview-table-wrap .badge-tahun {
        background: #eff6ff; color: #1d4ed8; border-radius: 4px; padding: 1px 6px; font-size: 0.75rem; font-weight: 600;
    }
    .preview-table-wrap .badge-asal {
        background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; border-radius: 4px; padding: 1px 6px; font-size: 0.75rem; font-weight: 600;
    }

    /* ── Info Grid ──────────────────────────────────── */
    .info-grid-preview {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 10px;
        margin-bottom: 16px;
    }
    .info-grid-preview .info-item {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 10px 14px;
    }
    .info-grid-preview .info-label {
        font-size: 0.7rem;
        color: #64748b;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .info-grid-preview .info-value {
        font-size: 0.88rem;
        color: #1e293b;
        font-weight: 600;
        margin-top: 2px;
    }

    /* ── Form field readonly styling ───────────────── */
    .form-control[readonly].auto-fill-field,
    textarea[readonly].auto-fill-field {
        background-color: #f0fdf4;
        border-color: #a7f3d0;
        color: #065f46;
        font-weight: 600;
    }
    textarea.total-textarea {
        resize: none;
        font-size: 1.15rem;
        font-weight: 700;
        height: auto;
        min-height: 48px;
        color: #059669;
    }

    /* ── Search Input in Preview ────────────────────── */
    .preview-search {
        padding: 6px 12px;
        border: 1.5px solid #cbd5e1;
        border-radius: 8px;
        font-size: 0.82rem;
        outline: none;
        width: 200px;
        transition: border-color 0.2s;
    }
    .preview-search:focus { border-color: #2563eb; }
</style>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-primary text-white d-flex align-items-center">
                <h5 class="mb-0"><i class="fas fa-file-excel me-2"></i>Input Data Aset dari Excel</h5>
            </div>
            <div class="card-body p-4">
                <p class="text-muted small mb-3">Upload file Excel KIB (.xls) untuk mengisi data otomatis. Sistem akan membaca isi file dan mengisi semua field secara otomatis.</p>

                <!-- Excel Upload Zone -->
                <div class="excel-upload-card" id="excelUploadZone">
                    <span class="upload-icon">📂</span>
                    <div class="upload-title">Klik atau seret file Excel KIB di sini</div>
                    <div class="upload-subtitle">Format: .xls atau .xlsx · Maks 10 MB</div>
                    <div class="file-info" id="excelFileInfo" style="display:none;"></div>
                    <input type="file" id="excelFileInput" accept=".xls,.xlsx" class="d-none">
                </div>

                <!-- Parsing Progress -->
                <div class="parse-progress text-center" id="parseProgress">
                    <div class="spinner-border text-primary me-2" role="status"></div>
                    <span class="text-primary fw-semibold">Memproses file Excel...</span>
                </div>

                <!-- Error Message -->
                <div class="alert alert-danger mt-3 d-none" id="parseError" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <span id="parseErrorText"></span>
                </div>
            </div>
        </div>

        <!-- Preview Section (muncul setelah parse berhasil) -->
        <div class="preview-section" id="previewSection">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white border-bottom">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <h6 class="mb-0 fw-bold text-primary"><i class="fas fa-eye me-2"></i>Preview Data dari Excel</h6>
                        <span class="badge bg-primary-subtle text-primary" id="previewFileName"></span>
                    </div>
                </div>
                <div class="card-body p-3">
                    <!-- Info Sekolah -->
                    <div class="info-grid-preview" id="infoGrid"></div>

                    <!-- Stats -->
                    <div class="preview-stats" id="previewStats"></div>

                    <!-- Data Table -->
                    <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
                        <h6 class="mb-0 fw-semibold"><i class="fas fa-list me-1"></i> Daftar Barang Inventaris</h6>
                        <input type="text" class="preview-search" id="previewSearch" placeholder="🔍 Cari barang...">
                    </div>
                    <div class="preview-table-wrap">
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Barang</th>
                                    <th>Kode Barang</th>
                                    <th>Register</th>
                                    <th>Judul / Ket</th>
                                    <th>Jml</th>
                                    <th>Tahun</th>
                                    <th>Asal Usul</th>
                                    <th>Harga</th>
                                    <th>Kab/Kota</th>
                                </tr>
                            </thead>
                            <tbody id="previewTableBody"></tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-2 px-1">
                        <span class="text-muted small" id="previewTotalItems"></span>
                        <span class="fw-bold text-success" id="previewTotalHarga"></span>
                    </div>
                </div>
            </div>

            <!-- Form Input (auto-filled) -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-check-circle me-2"></i>Simpan Data Aset</h5>
                </div>
                <div class="card-body p-4">
                    <p class="text-muted small mb-4">Data di bawah telah terisi otomatis dari file Excel. Silakan periksa dan koreksi jika perlu, lalu simpan.</p>

                    <form method="POST" enctype="multipart/form-data" class="needs-validation" id="saveForm" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                        <!-- Unit / Sekolah (readonly, auto-filled from Excel) -->
                        <div class="mb-3">
                            <label for="unit_name" class="form-label fw-semibold">Unit / Sekolah</label>
                            <div class="position-relative auto-filled" id="unitAutoFilled" style="display:none;">
                                <input type="text" class="form-control auto-fill-field" id="unit_name" readonly>
                            </div>
                            <input type="hidden" name="unit_id" id="unit_id" value="">
                            <div class="form-text" id="unitMatchInfo">Nama unit/sekolah akan otomatis terisi dari file Excel yang diupload.</div>
                        </div>

                        <!-- Jenis KIB -->
                        <div class="mb-3">
                            <label for="kib" class="form-label fw-semibold">Jenis KIB</label>
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

                        <!-- Tahun & Bulan -->
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="year" class="form-label fw-semibold">Tahun</label>
                                <input type="number" class="form-control" id="year" name="year"
                                       value="<?php echo isset($_POST['year']) ? (int)$_POST['year'] : ($prefill_year ?: date('Y')); ?>" min="2020" max="2099" required>
                                <div class="invalid-feedback">Masukkan tahun valid.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="month" class="form-label fw-semibold">Bulan</label>
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

                        <!-- Total Aset (readonly textarea, auto-filled) -->
                        <div class="mb-4">
                            <label for="total_display" class="form-label fw-semibold">Total Aset</label>
                            <textarea class="form-control total-textarea auto-fill-field" id="total_display" readonly rows="1" placeholder="Otomatis dari file Excel"></textarea>
                            <input type="hidden" name="total" id="total" value="<?php echo isset($_POST['total']) ? (int)$_POST['total'] : ''; ?>">
                            <div class="form-text" id="totalInfo">Total aset akan otomatis terisi dari file Excel yang diupload.</div>
                        </div>

                        <!-- Upload Dokumen (Opsional) -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Upload Dokumen Pendukung (Opsional)</label>
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

                        <!-- Buttons -->
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success btn-lg" id="btnSubmit">
                                <i class="fas fa-save me-2"></i>Simpan Data Aset
                            </button>
                            <a href="index.php" class="btn btn-light text-muted">Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
(function() {
    // Elements
    const uploadZone    = document.getElementById('excelUploadZone');
    const fileInput     = document.getElementById('excelFileInput');
    const fileInfo      = document.getElementById('excelFileInfo');
    const parseProgress = document.getElementById('parseProgress');
    const parseError    = document.getElementById('parseError');
    const parseErrorText = document.getElementById('parseErrorText');
    const previewSection = document.getElementById('previewSection');

    // Units data from PHP
    const dbUnits = <?php echo $unitsJson; ?>;

    // ── Upload Zone Events ──────────────────────────
    uploadZone.addEventListener('click', () => fileInput.click());

    uploadZone.addEventListener('dragover', e => {
        e.preventDefault();
        uploadZone.classList.add('drag-over');
    });
    uploadZone.addEventListener('dragleave', () => {
        uploadZone.classList.remove('drag-over');
    });
    uploadZone.addEventListener('drop', e => {
        e.preventDefault();
        uploadZone.classList.remove('drag-over');
        if (e.dataTransfer.files.length > 0) {
            fileInput.files = e.dataTransfer.files;
            handleFileSelected(e.dataTransfer.files[0]);
        }
    });
    fileInput.addEventListener('change', () => {
        if (fileInput.files.length > 0) {
            handleFileSelected(fileInput.files[0]);
        }
    });

    function handleFileSelected(file) {
        // Validate extension
        const ext = file.name.split('.').pop().toLowerCase();
        if (!['xls', 'xlsx'].includes(ext)) {
            showError('Hanya file .xls atau .xlsx yang diperbolehkan.');
            return;
        }
        if (file.size > 10 * 1024 * 1024) {
            showError('Ukuran file melebihi batas 10 MB.');
            return;
        }

        // Update UI
        uploadZone.classList.add('has-file');
        fileInfo.style.display = 'block';
        fileInfo.textContent = '📄 ' + file.name + ' (' + formatSize(file.size) + ')';

        // Start parsing
        parseFile(file);
    }

    function formatSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function showError(msg) {
        parseError.classList.remove('d-none');
        parseErrorText.textContent = msg;
        parseProgress.style.display = 'none';
    }

    function hideError() {
        parseError.classList.add('d-none');
    }

    // ── AJAX Parse ──────────────────────────────────
    function parseFile(file) {
        hideError();
        parseProgress.style.display = 'block';
        previewSection.classList.remove('show');

        const formData = new FormData();
        formData.append('excel_file', file);

        fetch('parse_excel.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            parseProgress.style.display = 'none';
            if (!data.success) {
                showError(data.error || 'Gagal memproses file.');
                return;
            }
            populatePreview(data);
            autoFillForm(data);
        })
        .catch(err => {
            parseProgress.style.display = 'none';
            showError('Terjadi kesalahan saat memproses file: ' + err.message);
        });
    }

    // ── Populate Preview Section ─────────────────────
    function populatePreview(data) {
        // Filename badge
        document.getElementById('previewFileName').textContent = '📄 ' + data.filename;

        // Info Grid
        const infoGrid = document.getElementById('infoGrid');
        infoGrid.innerHTML = '';
        if (data.info) {
            for (const [key, val] of Object.entries(data.info)) {
                infoGrid.innerHTML += `
                    <div class="info-item">
                        <div class="info-label">${escHtml(key)}</div>
                        <div class="info-value">${escHtml(val) || '—'}</div>
                    </div>`;
            }
        }

        // Stats
        const stats = document.getElementById('previewStats');
        stats.innerHTML = `
            <div class="preview-stat">
                <div class="stat-icon">📦</div>
                <div class="stat-label">Jenis Barang</div>
                <div class="stat-value">${data.total_barang.toLocaleString('id-ID')}</div>
            </div>
            <div class="preview-stat">
                <div class="stat-icon">💰</div>
                <div class="stat-label">Total Nilai</div>
                <div class="stat-value" style="font-size:0.95rem;">${data.jumlah_harga_formatted}</div>
            </div>
            <div class="preview-stat">
                <div class="stat-icon">🔢</div>
                <div class="stat-label">Total Unit</div>
                <div class="stat-value">${data.total_unit.toLocaleString('id-ID')}</div>
            </div>`;

        // Table
        const tbody = document.getElementById('previewTableBody');
        tbody.innerHTML = '';
        if (data.items && data.items.length > 0) {
            data.items.forEach(item => {
                tbody.innerHTML += `
                <tr>
                    <td class="text-center fw-semibold text-muted">${escHtml(item.no)}</td>
                    <td class="fw-semibold">${escHtml(item.nama)}</td>
                    <td><small>${escHtml(item.kode)}</small></td>
                    <td><small>${escHtml(item.register)}</small></td>
                    <td><small>${escHtml(item.judul)}</small></td>
                    <td class="text-center fw-bold text-primary">${escHtml(item.jumlah)}</td>
                    <td>${item.tahun ? '<span class="badge-tahun">' + escHtml(item.tahun) + '</span>' : ''}</td>
                    <td>${item.asal ? '<span class="badge-asal">' + escHtml(item.asal) + '</span>' : ''}</td>
                    <td class="td-harga">${escHtml(item.harga)}</td>
                    <td><small>${escHtml(item.kab)}</small></td>
                </tr>`;
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-3">Tidak ada data barang.</td></tr>';
        }

        // Footer
        document.getElementById('previewTotalItems').textContent = data.total_barang + ' jenis barang';
        document.getElementById('previewTotalHarga').textContent = 'JUMLAH: ' + data.jumlah_harga_formatted;

        // Show preview
        previewSection.classList.add('show');
    }

    // ── Auto Fill Form ──────────────────────────────
    function autoFillForm(data) {
        // Unit / Sekolah (always auto-filled, never dropdown)
        const unitNameInput = document.getElementById('unit_name');
        const unitIdInput   = document.getElementById('unit_id');
        const unitAutoFill  = document.getElementById('unitAutoFilled');
        const unitMatchInfo = document.getElementById('unitMatchInfo');

        if (data.unit_match) {
            unitNameInput.value = data.unit_match.name + ' (' + data.unit_match.code + ')';
            unitIdInput.value   = data.unit_match.id;
            unitAutoFill.style.display = 'block';
            
            if (data.unit_auto_created) {
                unitMatchInfo.innerHTML = '<span class="text-info"><i class="fas fa-plus-circle me-1"></i>Unit baru "' + escHtml(data.unit_match.name) + '" otomatis ditambahkan ke database dengan kode ' + escHtml(data.unit_match.code) + '.</span>';
            } else {
                unitMatchInfo.innerHTML = '<span class="text-success"><i class="fas fa-check-circle me-1"></i>Sekolah ditemukan di database.</span>';
            }
        } else if (data.nama_unit) {
            unitNameInput.value = data.nama_unit;
            unitAutoFill.style.display = 'block';
            unitMatchInfo.innerHTML = '<span class="text-warning"><i class="fas fa-exclamation-circle me-1"></i>Nama sekolah tidak dapat diproses.</span>';
        } else {
            unitAutoFill.style.display = 'none';
            unitMatchInfo.innerHTML = '<span class="text-muted">Nama sekolah tidak terdeteksi dari file.</span>';
        }

        // KIB Type (auto-select dropdown)
        if (data.kib_type) {
            const kibSelect = document.getElementById('kib');
            for (let i = 0; i < kibSelect.options.length; i++) {
                if (kibSelect.options[i].value === data.kib_type) {
                    kibSelect.selectedIndex = i;
                    break;
                }
            }
        }

        // Total Aset (readonly textarea)
        if (data.jumlah_harga_numeric && data.jumlah_harga_numeric > 0) {
            const totalDisplay = document.getElementById('total_display');
            const totalHidden  = document.getElementById('total');
            const totalInfo    = document.getElementById('totalInfo');
            
            totalHidden.value  = Math.round(data.jumlah_harga_numeric);
            totalDisplay.value = data.jumlah_harga_formatted;
            totalInfo.innerHTML = '<span class="text-success"><i class="fas fa-check-circle me-1"></i>Total aset terisi otomatis dari file Excel.</span>';
        }
    }

    // ── Preview Search ──────────────────────────────
    document.getElementById('previewSearch').addEventListener('input', function() {
        const q = this.value.toLowerCase();
        const rows = document.querySelectorAll('#previewTableBody tr');
        rows.forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    });



    // ── Document Upload (optional file) ─────────────
    const dz    = document.getElementById('dz');
    const docInput  = document.getElementById('fileInput');
    const docName   = document.getElementById('fileName');

    if (dz && docInput) {
        dz.addEventListener('click', () => docInput.click());
        dz.addEventListener('dragover', e => {
            e.preventDefault();
            dz.classList.remove('bg-light');
            dz.classList.add('bg-white', 'border-primary');
        });
        dz.addEventListener('dragleave', () => {
            dz.classList.add('bg-light');
            dz.classList.remove('bg-white', 'border-primary');
        });
        dz.addEventListener('drop', e => {
            e.preventDefault();
            dz.classList.add('bg-light');
            dz.classList.remove('bg-white', 'border-primary');
            if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                docInput.files = e.dataTransfer.files;
                docName.textContent = 'File terpilih: ' + e.dataTransfer.files[0].name;
            }
        });
        docInput.addEventListener('change', () => {
            if (docInput.files.length > 0) {
                docName.textContent = 'File terpilih: ' + docInput.files[0].name;
            }
        });
    }

    // ── Form Validation ─────────────────────────────
    const form = document.getElementById('saveForm');
    const btn  = document.getElementById('btnSubmit');

    if (form) {
        form.addEventListener('submit', function(event) {
            // Check unit_id
            const unitId = document.getElementById('unit_id').value;
            if (!unitId) {
                event.preventDefault();
                event.stopPropagation();
                document.getElementById('unitMatchInfo').innerHTML = '<span class="text-danger"><i class="fas fa-times-circle me-1"></i>Pilih unit/sekolah terlebih dahulu!</span>';
                return;
            }

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

    // ── Helper ──────────────────────────────────────
    function escHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
})();
</script>

<?php require_once '../inc/footer.php'; ?>
