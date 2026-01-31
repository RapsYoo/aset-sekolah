<?php
require_once '../inc/auth.php';
require_once '../inc/helpers.php';

require_login();
$page_title = 'Automation KIB';
require_once '../inc/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-robot me-2"></i>Automation Download KIB</h5>
            </div>
            <div class="card-body">
                <form id="automationForm">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Pilih KIB</label>
                            <select class="form-select" id="kib" name="kib" required onchange="updateForm()">
                                <option value="A">KIB A (Tanah)</option>
                                <option value="B">KIB B (Peralatan & Mesin)</option>
                                <option value="C">KIB C (Gedung & Bangunan)</option>
                                <option value="D">KIB D (Jalan & Irigasi)</option>
                                <option value="E">KIB E (Aset Lainnya)</option>
                                <option value="F">KIB F (Konstruksi)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nama Unit</label>
                            <input type="text" class="form-control" name="unit" required placeholder="Contoh: SMAN 1 MAKASSAR">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Tanggal (Per Tanggal)</label>
                            <input type="date" class="form-control" name="date" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-6" id="typeGroup">
                            <label class="form-label">Jenis Aset</label>
                            <select class="form-select" name="type">
                                <option value="1">Semua</option>
                                <option value="2">Intrakomptabel</option>
                                <option value="3">Ekstrakomptabel</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3" id="codeGroup" style="display: none;">
                        <div class="col-md-6">
                            <label class="form-label">Jenis Kode Barang</label>
                            <select class="form-select" name="code">
                                <option value="1">Kode Barang Lama</option>
                                <option value="2">Kode Barang Baru</option>
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-success w-100" id="btnRun">
                        <i class="fas fa-play me-2"></i>Jalankan Automation
                    </button>
                </form>
            </div>
        </div>

        <div class="card shadow-sm d-none" id="consoleCard">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0 font-monospace"><i class="fas fa-terminal me-2"></i>Console Output</h6>
                <span class="badge bg-secondary" id="statusBadge">Running...</span>
            </div>
            <div class="card-body bg-black p-0">
                <div id="consoleOutput" class="p-3 font-monospace small text-success" style="height: 300px; overflow-y: auto; white-space: pre-wrap;"></div>
            </div>
            <div class="card-footer" id="resultFooter" style="display: none;">
                <div class="alert alert-success mb-0">
                    <i class="fas fa-check-circle me-2"></i>Selesai! File berhasil diunduh.
                    <div id="downloadLinks" class="mt-2"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function updateForm() {
    const kib = document.getElementById('kib').value;
    const typeGroup = document.getElementById('typeGroup');
    const codeGroup = document.getElementById('codeGroup');

    // Logic visibility based on KIB
    // Type: All except F? Script says F only uses date & unit.
    if (kib === 'F') {
        typeGroup.style.display = 'none';
    } else {
        typeGroup.style.display = 'block';
    }

    // Code: Only C and D
    if (kib === 'C' || kib === 'D') {
        codeGroup.style.display = 'block';
    } else {
        codeGroup.style.display = 'none';
    }
}

document.getElementById('automationForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const btn = document.getElementById('btnRun');
    const consoleCard = document.getElementById('consoleCard');
    const output = document.getElementById('consoleOutput');
    const status = document.getElementById('statusBadge');
    const footer = document.getElementById('resultFooter');
    const links = document.getElementById('downloadLinks');

    // Reset UI
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Memproses...';
    consoleCard.classList.remove('d-none');
    output.innerHTML = '';
    footer.style.display = 'none';
    links.innerHTML = '';
    status.className = 'badge bg-warning text-dark';
    status.innerText = 'Running...';

    const formData = new FormData(this);

    // Use Fetch with stream reader if possible, or XHR
    fetch('../api/run_automation.php', {
        method: 'POST',
        body: formData
    }).then(response => {
        const reader = response.body.getReader();
        const decoder = new TextDecoder();

        function read() {
            reader.read().then(({ done, value }) => {
                if (done) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-play me-2"></i>Jalankan Automation';
                    return;
                }

                const chunk = decoder.decode(value, { stream: true });
                const lines = chunk.split('\n');

                lines.forEach(line => {
                    if (!line.trim()) return;
                    try {
                        const data = JSON.parse(line);
                        if (data.status === 'result') {
                            status.className = 'badge bg-success';
                            status.innerText = 'Completed';
                            footer.style.display = 'block';
                            if (data.files && data.files.length > 0) {
                                data.files.forEach(f => {
                                    links.innerHTML += `<a href="../storage/downloads/${f}" class="btn btn-sm btn-outline-success me-2" download><i class="fas fa-file-excel me-1"></i>${f}</a>`;
                                });
                            } else {
                                links.innerHTML = '<span class="text-muted">Tidak ada file yang diunduh.</span>';
                            }
                        } else if (data.status === 'error') {
                            output.innerHTML += `<div class="text-danger">[ERROR] ${data.message}</div>`;
                            status.className = 'badge bg-danger';
                            status.innerText = 'Error';
                        } else {
                            // Info/Log
                            const time = new Date().toLocaleTimeString();
                            output.innerHTML += `<div><span class="text-muted">[${time}]</span> ${data.message}</div>`;
                        }
                    } catch (e) {
                        // Raw text fallback
                        output.innerHTML += `<div>${line}</div>`;
                    }
                    output.scrollTop = output.scrollHeight;
                });

                read();
            });
        }
        read();
    }).catch(err => {
        output.innerHTML += `<div class="text-danger">[SYSTEM ERROR] ${err}</div>`;
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-play me-2"></i>Jalankan Automation';
    });
});

// Init
updateForm();
</script>

<?php require_once '../inc/footer.php'; ?>
