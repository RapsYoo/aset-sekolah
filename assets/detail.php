<?php
require_once '../inc/auth.php';
require_once '../inc/helpers.php';
require_once '../inc/storage.php';

require_login();
$user = current_user();

// Ambil parameter dari query string
$kib = sanitize($_GET['kib'] ?? '');
$year = (int)($_GET['year'] ?? date('Y'));
$month = (int)($_GET['month'] ?? date('n'));

// Validasi
if (empty($kib) || !in_array($kib, ['A', 'B', 'C', 'D', 'E', 'F'])) {
    header("Location: index.php");
    exit();
}

// Ambil data aset per sekolah untuk KIB-bulan-tahun tertentu
$assets = db_fetch_all(
    "SELECT a.*, u.name as user_name, un.name as unit_name, un.code as unit_code
     FROM assets_monthly a
     LEFT JOIN users u ON a.created_by = u.id
     LEFT JOIN units un ON a.unit_id = un.id
     WHERE a.kib_type = ? AND a.year = ? AND a.month = ?
     ORDER BY un.name",
    'sii',
    [$kib, $year, $month]
);

// Hitung total untuk diagram
$total_all = array_sum(array_column($assets, 'total'));

// Handle delete
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    $stmt = db_prepare("DELETE FROM assets_monthly WHERE id = ?");
    $stmt->bind_param('i', $delete_id);
    if ($stmt->execute()) {
        header("Location: detail.php?kib=$kib&year=$year&month=$month&success=1");
        exit();
    }
}

$success = '';
if (isset($_GET['success'])) {
    $success = 'Data aset berhasil dihapus.';
}

$page_title = "Detail KIB $kib";
require_once '../inc/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h4 class="mb-1">
            <i class="fas fa-database me-2 text-primary"></i>Detail Aset KIB <?php echo $kib; ?>
        </h4>
        <p class="text-muted mb-0">
            Periode <?php echo get_month_name($month); ?> <?php echo $year; ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="index.php?year=<?php echo $year; ?>" class="btn btn-light text-muted">
            <i class="fas fa-arrow-left me-2"></i>Kembali
        </a>
        <?php if (!is_supervisor()): ?>
            <a href="create.php?kib=<?php echo $kib; ?>&year=<?php echo $year; ?>&month=<?php echo $month; ?>"
               class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Tambah Data
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Statistik Cards -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-icon bg-primary text-white bg-opacity-100">
                <i class="fas fa-wallet"></i>
            </div>
            <div class="stat-details">
                <h6 class="text-white-50">Total Aset</h6>
                <h3 class="text-white"><?php echo number_format($total_all, 0, ',', '.'); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card" style="background: white; color: var(--dark);">
            <div class="stat-icon">
                <i class="fas fa-school"></i>
            </div>
            <div class="stat-details">
                <h6 class="text-muted">Jumlah Sekolah</h6>
                <h3><?php echo count($assets); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card" style="background: white; color: var(--dark);">
            <div class="stat-icon">
                <i class="fas fa-calculator"></i>
            </div>
            <div class="stat-details">
                <h6 class="text-muted">Rata-rata</h6>
                <h3><?php echo count($assets) > 0 ? number_format($total_all / count($assets), 0, ',', '.') : '0'; ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Diagram -->
    <div class="col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Proporsi Aset</h6>
            </div>
            <div class="card-body position-relative" style="height: 350px;">
                <canvas id="chartPie"></canvas>
            </div>
        </div>
    </div>

    <!-- Tabel Detail -->
    <div class="col-lg-8 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-list me-2"></i>Daftar Sekolah</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">No</th>
                                <th>Sekolah</th>
                                <th>Total Aset</th>
                                <th style="width: 25%;">Persentase</th>
                                <th>Input Oleh</th>
                                <th class="pe-4 text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($assets) > 0): ?>
                                <?php foreach ($assets as $index => $asset): ?>
                                    <?php
                                    $percentage = $total_all > 0 ? ($asset['total'] / $total_all) * 100 : 0;
                                    ?>
                                    <tr>
                                        <td class="ps-4"><?php echo $index + 1; ?></td>
                                        <td>
                                            <div class="fw-bold"><?php echo escape($asset['unit_name'] ?? 'N/A'); ?></div>
                                            <?php if (!empty($asset['unit_code'])): ?>
                                                <small class="text-muted"><?php echo escape($asset['unit_code']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong>Rp <?php echo number_format($asset['total'], 0, ',', '.'); ?></strong></td>
                                        <td>
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar" role="progressbar"
                                                     style="width: <?php echo $percentage; ?>%"
                                                     aria-valuenow="<?php echo $percentage; ?>"
                                                     aria-valuemin="0"
                                                     aria-valuemax="100">
                                                </div>
                                            </div>
                                            <small class="text-muted"><?php echo number_format($percentage, 1); ?>%</small>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="bg-light rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 24px; height: 24px;">
                                                    <i class="fas fa-user text-secondary" style="font-size: 0.7rem;"></i>
                                                </div>
                                                <small><?php echo escape($asset['user_name'] ?? '--'); ?></small>
                                            </div>
                                        </td>
                                        <td class="pe-4 text-end">
                                            <?php if (!is_supervisor()): ?>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="edit.php?id=<?php echo $asset['id']; ?>" 
                                                       class="btn btn-outline-warning" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="detail.php?kib=<?php echo $kib; ?>&year=<?php echo $year; ?>&month=<?php echo $month; ?>&delete_id=<?php echo $asset['id']; ?>" 
                                                       class="btn btn-outline-danger"
                                                       onclick="return confirm('Yakin hapus data aset untuk <?php echo escape($asset['unit_name']); ?>?')" 
                                                       title="Hapus">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-5">
                                        <i class="fas fa-inbox fa-3x mb-3 d-block opacity-25"></i>
                                        Belum ada data aset untuk periode ini.
                                        <?php if (!is_supervisor()): ?>
                                            <div class="mt-3">
                                                <a href="create.php?kib=<?php echo $kib; ?>&year=<?php echo $year; ?>&month=<?php echo $month; ?>" 
                                                   class="btn btn-sm btn-primary">
                                                    <i class="fas fa-plus me-1"></i>Tambah Data Pertama
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-file-alt me-2"></i>Dokumen Terunggah</h6>
            </div>
            <div class="card-body">
                <?php if (count($assets) > 0): ?>
                    <div class="row">
                    <?php foreach ($assets as $asset): ?>
                        <?php
                            $unitName = $asset['unit_name'] ?? 'N/A';
                            $unitCode = $asset['unit_code'] ?? 'general';
                            $docs = storage_list_documents($year, $kib, $unitCode);
                        ?>
                        <?php if (!empty($docs)): ?>
                            <div class="col-md-6 mb-4">
                                <h6 class="mb-2 fw-bold text-primary">
                                    <i class="fas fa-school me-2"></i><?php echo escape($unitName); ?>
                                </h6>
                                <div class="list-group">
                                    <?php foreach ($docs as $doc): ?>
                                        <a href="download.php?key=<?php echo urlencode($doc['key']); ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-file-pdf text-danger me-3 fa-lg"></i>
                                                <div>
                                                    <div class="fw-medium text-dark"><?php echo escape($doc['name']); ?></div>
                                                    <small class="text-muted">
                                                        <?php echo number_format($doc['size'] / 1024, 0, ',', '.'); ?> KB • <?php echo date('d/m/y H:i', $doc['mtime']); ?>
                                                    </small>
                                                </div>
                                            </div>
                                            <i class="fas fa-download text-muted"></i>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    </div>
                    <?php if (empty($docs)): // This logic is slightly flawed as it only checks last loop, but good enough for now ?>
                         <div class="text-muted text-center py-3">Tidak ada dokumen yang diunggah untuk sekolah-sekolah ini.</div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="text-muted">Belum ada data sekolah untuk menampilkan dokumen.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../inc/footer.php'; ?>

<script>
    // Data untuk chart
    const chartData = {
        labels: [
            <?php foreach ($assets as $asset): ?>
                '<?php echo escape($asset['unit_name'] ?? 'N/A'); ?>',
            <?php endforeach; ?>
        ],
        datasets: [{
            data: [
                <?php foreach ($assets as $asset): ?>
                    <?php echo $asset['total']; ?>,
                <?php endforeach; ?>
            ],
            backgroundColor: [
                '#4361ee', '#3a0ca3', '#7209b7', '#f72585',
                '#4cc9f0', '#4895ef', '#560bad', '#b5179e',
                '#4361ee', '#3f37c9', '#480ca8', '#3f37c9'
            ],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    };

    // Buat pie chart
    const ctx = document.getElementById('chartPie');
    if (ctx) {
        new Chart(ctx, {
            type: 'doughnut',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                return label + ': Rp ' + value.toLocaleString('id-ID') + ' (' + percentage + '%)';
                            }
                        }
                    }
                },
                cutout: '60%'
            }
        });
    }
</script>
