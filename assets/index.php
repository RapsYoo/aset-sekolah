<?php
require_once '../inc/auth.php';
require_once '../inc/helpers.php';

require_login();
$user = current_user();
$months = get_months();

// Ambil tahun dari query string
$year = (int)($_GET['year'] ?? date('Y'));
$years = range(2020, date('Y') + 1);

// Ambil data aset
$assets = db_fetch_all(
    "SELECT * FROM assets_monthly WHERE year = ? ORDER BY kib_type, month",
    'i',
    [$year]
);

// Group by KIB
$assets_by_kib = [];
foreach ($assets as $asset) {
    $assets_by_kib[$asset['kib_type']][] = $asset;
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
                        <i class="fas fa-plus me-1"></i>Tambah
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
                                <th>Total Aset</th>
                                <th>Input Oleh</th>
                                <th>Tanggal Input</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($assets_by_kib[$kib])): ?>
                                <?php foreach ($assets_by_kib[$kib] as $asset): ?>
                                    <tr>
                                        <td><?php echo get_month_name($asset['month']); ?></td>
                                        <td><?php echo $asset['year']; ?></td>
                                        <td><?php echo number_format($asset['total'], 0, ',', '.'); ?></td>
                                        <td>--</td>
                                        <td><?php echo format_date($asset['created_at']); ?></td>
                                        <td>
                                            <a href="edit.php?id=<?php echo $asset['id']; ?>" class="btn btn-sm btn-warning">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="delete.php?id=<?php echo $asset['id']; ?>" class="btn btn-sm btn-danger" 
                                               onclick="return confirm('Yakin hapus data?')">
                                                <i class="fas fa-trash"></i>
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
</body>
</html>
