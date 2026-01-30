<?php
require_once '../inc/auth.php';
require_once '../inc/helpers.php';

require_login();
$user = current_user();
$months = get_months();

// Ambil tahun dari query string
$year = (int)($_GET['year'] ?? date('Y'));
$years = range(2020, date('Y') + 1);

// Ambil data aset yang di-aggregate per KIB, bulan, tahun (ringkasan)
$assets_summary = db_fetch_all(
    "SELECT 
        a.kib_type,
        a.year,
        a.month,
        COUNT(DISTINCT a.unit_id) as jumlah_sekolah,
        SUM(a.total) as total_aset,
        MAX(a.created_at) as last_updated
     FROM assets_monthly a
     WHERE a.year = ? 
     GROUP BY a.kib_type, a.year, a.month
     ORDER BY a.kib_type, a.month",
    'i',
    [$year]
);

// Group by KIB untuk tampilan
$assets_by_kib = [];
foreach ($assets_summary as $summary) {
    $assets_by_kib[$summary['kib_type']][] = $summary;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Aset - Sistem Monitoring Aset Sekolah</title>
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
        body {
            background: #f8f9fa;
        }
        .table-container {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
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
            <a class="nav-link text-white" href="../dashboard.php">
                <i class="fas fa-arrow-left me-2"></i>Kembali
            </a>
        </div>
    </nav>

    <div class="container-fluid mt-5">
        <div class="row mb-4">
            <div class="col-md-8">
                <h3><i class="fas fa-list me-2"></i>Data Aset Sekolah</h3>
            </div>
            <div class="col-md-4">
                <form class="d-flex gap-2" method="GET">
                    <select class="form-select form-select-sm" name="year" onchange="this.form.submit()">
                        <?php foreach ($years as $y): ?>
                            <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>>
                                Tahun <?php echo $y; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <a href="create.php" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus me-1"></i>Tambah Aset
                    </a>
                </form>
            </div>
        </div>

        <!-- Tabel per KIB -->
        <?php foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $kib): ?>
            <div class="table-container mb-4">
                <h5 class="mb-3"><i class="fas fa-database me-2"></i>KIB <?php echo $kib; ?></h5>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Bulan</th>
                                <th>Tahun</th>
                                <th>Jumlah Sekolah</th>
                                <th>Total Aset</th>
                                <th>Terakhir Diupdate</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($assets_by_kib[$kib])): ?>
                                <?php foreach ($assets_by_kib[$kib] as $summary): ?>
                                    <tr>
                                        <td><strong><?php echo get_month_name($summary['month']); ?></strong></td>
                                        <td><?php echo $summary['year']; ?></td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $summary['jumlah_sekolah']; ?> Sekolah</span>
                                        </td>
                                        <td>
                                            <strong class="text-primary"><?php echo number_format($summary['total_aset'], 0, ',', '.'); ?></strong>
                                        </td>
                                        <td><?php echo format_date($summary['last_updated']); ?></td>
                                        <td>
                                            <a href="detail.php?kib=<?php echo $kib; ?>&year=<?php echo $summary['year']; ?>&month=<?php echo $summary['month']; ?>" 
                                               class="btn btn-sm btn-primary">
                                                <i class="fas fa-eye me-1"></i>Lihat Detail
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted">Tidak ada data</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php
        $fm = get_flash_message();
        $toastMessage = '';
        $toastType = 'info';
        if ($fm) {
            $toastMessage = $fm['message'] ?? '';
            $toastType = $fm['type'] ?? 'info';
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
</body>
</html>
