<?php
require_once 'inc/auth.php';
require_once 'inc/helpers.php';

require_login();
$user = current_user();

// Get current month & year
$current_month = (int)date('m');
$current_year = (int)date('Y');

// Get selected year from query string
$selected_year = (int)($_GET['year'] ?? $current_year);

// Get available years (dari 2020 sampai tahun sekarang)
$years = range(2020, $current_year + 1);

// Query total aset bulan ini dengan tahun terpilih
$total_aset_bulan_ini = db_fetch_one(
    "SELECT SUM(total) as total FROM assets_monthly WHERE month = ? AND year = ?",
    'ii',
    [$current_month, $selected_year]
);
$total_aset = $total_aset_bulan_ini['total'] ?? 0;

// Query total pengguna
$total_users = db_fetch_one(
    "SELECT COUNT(*) as count FROM users WHERE is_active = 1"
);
$total_users_count = $total_users['count'] ?? 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistem Monitoring Aset Sekolah</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <style>
        :root {
            --primary: #667eea;
            --secondary: #764ba2;
        }
        body {
            background: #f8f9fa;
        }
        .navbar {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .sidebar {
            background: white;
            border-right: 1px solid #dee2e6;
            min-height: calc(100vh - 56px);
        }
        .sidebar .nav-link {
            color: #333;
            border-left: 3px solid transparent;
            margin-bottom: 0.5rem;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: #f8f9fa;
            border-left-color: var(--primary);
            color: var(--primary);
        }
        .main-content {
            padding: 2rem;
        }
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            border-left: 4px solid var(--primary);
        }
        .chart-container {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            margin-top: 2rem;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--secondary) 0%, var(--primary) 100%);
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-dark navbar-expand-lg">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-chart-bar me-2"></i>Monitoring Aset Sekolah
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-2"></i><?php echo escape($user['name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user"></i> Profil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2">
                <div class="sidebar">
                    <ul class="nav flex-column p-3">
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php">
                                <i class="fas fa-home me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="assets/index.php">
                                <i class="fas fa-list me-2"></i>Data Aset
                            </a>
                        </li>
                        <?php if (is_admin()): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="admin/units.php">
                                    <i class="fas fa-building me-2"></i>Kelola Unit
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="admin/users.php">
                                    <i class="fas fa-users me-2"></i>Manajemen User
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-10">
                <div class="main-content">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1>Dashboard</h1>
                        <div class="d-flex gap-2">
                            <select class="form-select form-select-sm" style="max-width: 150px;" onchange="window.location.href='dashboard.php?year='+this.value">
                                <option value="">-- Pilih Tahun --</option>
                                <?php foreach (array_reverse($years) as $y): ?>
                                    <option value="<?php echo $y; ?>" <?php echo $selected_year == $y ? 'selected' : ''; ?>>
                                        Tahun <?php echo $y; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <a href="assets/create.php" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>Input Aset
                            </a>
                        </div>
                    </div>

                    <!-- Stat Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="stat-card">
                                <h6 class="text-muted">Total Aset (Bulan Ini - <?php echo $selected_year; ?>)</h6>
                                <h3><?php echo number_format($total_aset, 0, ',', '.'); ?></h3>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <h6 class="text-muted">Total Pengguna Aktif</h6>
                                <h3><?php echo $total_users_count; ?></h3>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <h6 class="text-muted">Role Anda</h6>
                                <h3><?php echo ucfirst(escape($user['role'])); ?></h3>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <h6 class="text-muted">Bulan</h6>
                                <h3><?php echo get_month_name($current_month); ?></h3>
                            </div>
                        </div>
                    </div>

                    <!-- Charts -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="chart-container">
                                <h5>Data Aset KIB A (12 Bulan)</h5>
                                <canvas id="chartKibA"></canvas>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="chart-container">
                                <h5>Data Aset KIB B (12 Bulan)</h5>
                                <canvas id="chartKibB"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-md-6">
                            <div class="chart-container">
                                <h5>Data Aset KIB C (12 Bulan)</h5>
                                <canvas id="chartKibC"></canvas>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="chart-container">
                                <h5>Data Aset KIB D (12 Bulan)</h5>
                                <canvas id="chartKibD"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-md-6">
                            <div class="chart-container">
                                <h5>Data Aset KIB E (12 Bulan)</h5>
                                <canvas id="chartKibE"></canvas>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="chart-container">
                                <h5>Data Aset KIB F (12 Bulan)</h5>
                                <canvas id="chartKibF"></canvas>
                            </div>
                        </div>
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
        const APP_URL = '<?php echo APP_URL; ?>';
        const kibTypes = ['A', 'B', 'C', 'D', 'E', 'F'];
        
        // Get selected year dari URL atau dari current year
        const urlParams = new URLSearchParams(window.location.search);
        const selectedYear = parseInt(urlParams.get('year')) || new Date().getFullYear();

        console.log('🚀 Dashboard loaded');
        console.log('APP_URL:', APP_URL);
        console.log('Selected Year:', selectedYear);

        // Inisialisasi chart untuk setiap KIB
        kibTypes.forEach(kib => {
            const apiUrl = `${APP_URL}/api/assets.php?kib=${kib}&year=${selectedYear}`;
            console.log(`📊 Fetching KIB ${kib} from:`, apiUrl);

            fetch(apiUrl)
                .then(response => {
                    console.log(`✅ KIB ${kib} response status:`, response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log(`📈 KIB ${kib} data:`, data);

                    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                    const chartData = new Array(12).fill(0);
                    
                    if (Array.isArray(data)) {
                        data.forEach(item => {
                            chartData[item.month - 1] = item.total;
                        });
                    }

                    const canvasId = `chartKib${kib}`;
                    const canvasElement = document.getElementById(canvasId);
                    
                    if (!canvasElement) {
                        console.error(`❌ Canvas element #${canvasId} not found!`);
                        return;
                    }

                    const ctx = canvasElement.getContext('2d');
                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: months,
                            datasets: [{
                                label: `KIB ${kib} (Tahun ${selectedYear})`,
                                data: chartData,
                                borderColor: '#667eea',
                                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                                tension: 0.4,
                                fill: true,
                                pointRadius: 5,
                                pointBackgroundColor: '#667eea',
                                pointBorderColor: '#fff',
                                pointBorderWidth: 2
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: { display: true }
                            },
                            scales: {
                                y: { beginAtZero: true }
                            }
                        }
                    });

                    console.log(`✅ Chart KIB ${kib} rendered successfully`);
                })
                .catch(error => {
                    console.error(`❌ Error loading KIB ${kib} data:`, error);
                });
        });
    </script>
</body>
</html>
