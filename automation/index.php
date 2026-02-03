<?php
require_once '../inc/auth.php';
require_once '../inc/helpers.php';
require_once '../inc/storage.php';
require_once '../inc/automation.php';
require_admin();
$page_title = 'Automation';
require_once '../inc/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-0"><?php echo escape($page_title); ?></h4>
        <div class="text-muted">Jalankan otomasi KIB dan pantau progres</div>
    </div>
    <div>
        <a href="<?php echo APP_URL; ?>/automation/index.php" class="btn btn-outline-secondary">
            <i class="fas fa-rotate-left me-1"></i>Reset
        </a>
    </div>
</div>
<div class="row">
    <div class="col-md-5">
        <div class="card">
            <div class="card-header">Form Automation</div>
            <div class="card-body">
                <form method="post" action="<?php echo APP_URL; ?>/automation/run.php" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <div class="mb-3">
                        <label class="form-label">Jenis KIB</label>
                        <select name="kib" id="kib" class="form-select" required>
                            <?php foreach (get_kib_types() as $k): ?>
                                <option value="<?php echo $k; ?>"><?php echo $k; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Pilih jenis KIB</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tanggal</label>
                        <input type="date" name="tgl" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        <div class="invalid-feedback">Isi tanggal</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Unit</label>
                        <input type="text" name="unit" class="form-control" placeholder="Nama unit/sekolah" required>
                        <div class="invalid-feedback">Isi nama unit</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Jenis Aset</label>
                        <select name="jenis_aset" id="jenis_aset" class="form-select">
                            <option value="1">Semua</option>
                            <option value="2">Intrakomptabel</option>
                            <option value="3">Ekstrakomptabel</option>
                        </select>
                    </div>
                    <div class="mb-3" id="jenis_kode_wrap">
                        <label class="form-label">Jenis Kode Barang</label>
                        <select name="jenis_kode" id="jenis_kode" class="form-select">
                            <option value="1">Kode Barang Lama</option>
                            <option value="2">Kode Barang Baru</option>
                        </select>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <span class="spinner-border spinner-border-sm me-2 d-none" id="runSpinner"></span>
                            Jalankan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-7">
        <div class="card">
            <div class="card-header">Console & Hasil Unduhan</div>
            <div class="card-body">
                <?php $job = sanitize($_GET['job'] ?? ''); ?>
                <?php if (!empty($job)): ?>
                    <div class="mb-3">
                        <div class="badge bg-info text-dark">Job: <?php echo escape($job); ?></div>
                        <span class="badge bg-secondary ms-2" id="statusBadge">Running</span>
                    </div>
                    <div class="mb-3">
                        <pre class="bg-dark text-white p-3" style="height: 280px; overflow: auto" id="consoleBox"></pre>
                    </div>
                    <div>
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-2">Files</h6>
                            <button class="btn btn-sm btn-outline-secondary" id="refreshBtn">
                                <i class="fas fa-sync-alt me-1"></i>Refresh
                            </button>
                        </div>
                        <div id="filesBox"></div>
                    </div>
                <?php else: ?>
                    <div class="text-muted">Jalankan job untuk melihat progres</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php require_once '../inc/footer.php'; ?>
<?php if (!empty($job)): ?>
<script>
    (function () {
        const jobId = "<?php echo $job; ?>";
        const statusEl = document.getElementById('statusBadge');
        const consoleEl = document.getElementById('consoleBox');
        const filesEl = document.getElementById('filesBox');
        const refreshBtn = document.getElementById('refreshBtn');
        let polling = true;
        function fetchStatus() {
            if (!polling) return;
            fetch("<?php echo APP_URL; ?>/automation/status.php?job=" + encodeURIComponent(jobId))
                .then(r => r.json())
                .then(d => {
                    if (d.status) statusEl.textContent = d.status.toUpperCase();
                    if (d.status && (d.status === 'done' || d.status === 'error')) {
                        polling = false;
                    }
                    if (d.log) {
                        consoleEl.textContent = d.log;
                        consoleEl.scrollTop = consoleEl.scrollHeight;
                    }
                    filesEl.innerHTML = '';
                    if (Array.isArray(d.files)) {
                        let html = '';
                        d.files.forEach(f => {
                            const url = "<?php echo APP_URL; ?>/assets/download.php?key=" + encodeURIComponent(f.key);
                            html += '<div class="d-flex align-items-center justify-content-between border rounded px-3 py-2 mb-2">';
                            html += '<div><i class="far fa-file-excel text-success me-2"></i>' + f.name + '</div>';
                            html += '<div><a href="' + url + '" class="btn btn-sm btn-outline-success"><i class="fas fa-download me-1"></i>Download</a></div>';
                            html += '</div>';
                        });
                        filesEl.innerHTML = html || '<div class="text-muted">Belum ada file</div>';
                    }
                })
                .catch(() => {});
        }
        fetchStatus();
        const iv = setInterval(fetchStatus, 3000);
        if (refreshBtn) refreshBtn.addEventListener('click', fetchStatus);
    })();
</script>
<?php endif; ?>
<script>
    (function () {
        const kibSelect = document.getElementById('kib');
        const kodeWrap = document.getElementById('jenis_kode_wrap');
        const kodeSelect = document.getElementById('jenis_kode');
        function updateKodeVisibility() {
            const val = (kibSelect.value || '').toUpperCase();
            const show = (val === 'C' || val === 'D');
            kodeWrap.style.display = show ? '' : 'none';
            kodeSelect.disabled = !show;
        }
        updateKodeVisibility();
        kibSelect.addEventListener('change', updateKodeVisibility);
    })();
</script>
