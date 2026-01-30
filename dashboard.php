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

$page_title = 'Dashboard';
require_once 'inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">Dashboard Overview</h4>
        <p class="text-muted mb-0">Statistik aset sekolah untuk tahun <?php echo $selected_year; ?></p>
    </div>
    <div class="d-flex gap-2">
        <select class="form-select" style="max-width: 150px;" onchange="window.location.href='dashboard.php?year='+this.value">
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
            <div class="stat-icon">
                <i class="fas fa-boxes"></i>
            </div>
            <div class="stat-details">
                <h6 class="text-muted">Total Aset (Bln Ini)</h6>
                <h3><?php echo number_format($total_aset, 0, ',', '.'); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-details">
                <h6 class="text-muted">Total Pengguna</h6>
                <h3><?php echo $total_users_count; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-id-badge"></i>
            </div>
            <div class="stat-details">
                <h6 class="text-muted">Role Anda</h6>
                <h3><?php echo ucfirst(escape($user['role'])); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <div class="stat-details">
                <h6 class="text-muted">Bulan</h6>
                <h3><?php echo get_month_name($current_month); ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- Charts -->
<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                Data Aset KIB A (12 Bulan)
            </div>
            <div class="card-body">
                <canvas id="chartKibA"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                Data Aset KIB B (12 Bulan)
            </div>
            <div class="card-body">
                <canvas id="chartKibB"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                Data Aset KIB C (12 Bulan)
            </div>
            <div class="card-body">
                <canvas id="chartKibC"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                Data Aset KIB D (12 Bulan)
            </div>
            <div class="card-body">
                <canvas id="chartKibD"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                Data Aset KIB E (12 Bulan)
            </div>
            <div class="card-body">
                <canvas id="chartKibE"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                Data Aset KIB F (12 Bulan)
            </div>
            <div class="card-body">
                <canvas id="chartKibF"></canvas>
            </div>
        </div>
    </div>
</div>

<?php require_once 'inc/footer.php'; ?>

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
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
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
                            borderColor: '#4361ee',
                            backgroundColor: 'rgba(67, 97, 238, 0.1)',
                            tension: 0.4,
                            fill: true,
                            pointRadius: 4,
                            pointBackgroundColor: '#4361ee',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: true }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    borderDash: [2, 4],
                                    color: '#f0f0f0'
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                }
                            }
                        }
                    }
                });
            })
            .catch(error => {
                console.error(`❌ Error loading KIB ${kib} data:`, error);
            });
    });
</script>
