<?php
require_once '../inc/auth.php';
require_once '../inc/helpers.php';

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

$success = isset($_GET['success']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Aset KIB <?php echo $kib; ?> - <?php echo get_month_name($month); ?> <?php echo $year; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --primary: #667eea;
            --secondary: #764ba2;
        }
        .navbar {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        }
        body {
            background: #f8f9fa;
        }
        .table-container {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        .chart-container {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            height: 400px;
        }
        .stat-card {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-dark navbar-expand-lg">
        <div class="container-fluid">
            <a class="navbar-brand" href="../dashboard.php">
                <i class="fas fa-chart-bar me-2"></i>Monitoring Aset Sekolah
            </a>
            <a class="nav-link text-white" href="index.php?year=<?php echo $year; ?>">
                <i class="fas fa-arrow-left me-2"></i>Kembali
            </a>
        </div>
    </nav>

    <div class="container-fluid mt-5">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-md-8">
                <h3>
                    <i class="fas fa-database me-2"></i>Detail Aset KIB <?php echo $kib; ?>
                    <small class="text-muted">- <?php echo get_month_name($month); ?> <?php echo $year; ?></small>
                </h3>
            </div>
            <div class="col-md-4 text-end">
                <a href="create.php?kib=<?php echo $kib; ?>&year=<?php echo $year; ?>&month=<?php echo $month; ?>" 
                   class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i>Tambah Aset Sekolah
                </a>
            </div>
        </div>

        

        <!-- Statistik -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <h5 class="mb-0">Total Aset</h5>
                    <h2 class="mb-0"><?php echo number_format($total_all, 0, ',', '.'); ?></h2>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <h5 class="mb-0">Jumlah Sekolah</h5>
                    <h2 class="mb-0"><?php echo count($assets); ?></h2>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <h5 class="mb-0">Rata-rata per Sekolah</h5>
                    <h2 class="mb-0"><?php echo count($assets) > 0 ? number_format($total_all / count($assets), 0, ',', '.') : '0'; ?></h2>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Diagram -->
            <div class="col-md-5 mb-4">
                <div class="chart-container">
                    <h5 class="mb-3"><i class="fas fa-chart-pie me-2"></i>Persentase Aset per Sekolah</h5>
                    <canvas id="chartPie"></canvas>
                </div>
            </div>

            <!-- Tabel Detail -->
            <div class="col-md-7 mb-4">
                <div class="table-container">
                    <h5 class="mb-3"><i class="fas fa-list me-2"></i>Daftar Sekolah</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>No</th>
                                    <th>Sekolah</th>
                                    <th>Total Aset</th>
                                    <th>Persentase</th>
                                    <th>Input Oleh</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($assets) > 0): ?>
                                    <?php foreach ($assets as $index => $asset): ?>
                                        <?php 
                                        $percentage = $total_all > 0 ? ($asset['total'] / $total_all) * 100 : 0;
                                        ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td>
                                                <strong><?php echo escape($asset['unit_name'] ?? 'N/A'); ?></strong>
                                                <?php if (!empty($asset['unit_code'])): ?>
                                                    <br><small class="text-muted"><?php echo escape($asset['unit_code']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><strong><?php echo number_format($asset['total'], 0, ',', '.'); ?></strong></td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar" role="progressbar" 
                                                         style="width: <?php echo $percentage; ?>%" 
                                                         aria-valuenow="<?php echo $percentage; ?>" 
                                                         aria-valuemin="0" 
                                                         aria-valuemax="100">
                                                        <?php echo number_format($percentage, 1); ?>%
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo escape($asset['user_name'] ?? '--'); ?></td>
                                            <td>
                                                <a href="edit.php?id=<?php echo $asset['id']; ?>" 
                                                   class="btn btn-sm btn-warning" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="detail.php?kib=<?php echo $kib; ?>&year=<?php echo $year; ?>&month=<?php echo $month; ?>&delete_id=<?php echo $asset['id']; ?>" 
                                                   class="btn btn-sm btn-danger" 
                                                   onclick="return confirm('Yakin hapus data aset untuk <?php echo escape($asset['unit_name']); ?>?')" 
                                                   title="Hapus">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">
                                            <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                            Belum ada data aset untuk periode ini.
                                            <br>
                                            <a href="create.php?kib=<?php echo $kib; ?>&year=<?php echo $year; ?>&month=<?php echo $month; ?>" 
                                               class="btn btn-sm btn-primary mt-2">
                                                <i class="fas fa-plus me-1"></i>Tambah Data Pertama
                                            </a>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php
        $fm = get_flash_message();
        $toastMessage = '';
        $toastType = 'info';
        if ($fm) {
            $toastMessage = $fm['message'] ?? '';
            $toastType = $fm['type'] ?? 'info';
        } elseif (!empty($success)) {
            $toastMessage = 'Data berhasil dihapus!';
            $toastType = 'success';
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
        <script>
            (function () {
                const toastEl = document.getElementById('mainToast');
                if (toastEl && typeof bootstrap !== 'undefined') {
                    new bootstrap.Toast(toastEl).show();
                }
            })();
        </script>
    <?php endif; ?>
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
                    '#667eea', '#764ba2', '#f093fb', '#4facfe', 
                    '#00f2fe', '#43e97b', '#fa709a', '#fee140',
                    '#30cfd0', '#330867', '#a8edea', '#fed6e3'
                ],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        };

        // Buat pie chart
        const ctx = document.getElementById('chartPie');
        if (ctx) {
            new Chart(ctx, {
                type: 'pie',
                data: chartData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                padding: 15,
                                usePointStyle: true
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    return label + ': ' + value.toLocaleString('id-ID') + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        }
    </script>
</body>
</html>

