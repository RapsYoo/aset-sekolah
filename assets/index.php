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

$page_title = 'Data Aset';
require_once '../inc/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h4 class="mb-1"><i class="fas fa-list me-2"></i>Data Aset Sekolah</h4>
        <p class="text-muted mb-0">Rekapitulasi aset per KIB untuk Tahun <?php echo $year; ?></p>
    </div>
    <div class="d-flex gap-2">
        <form class="d-flex gap-2" method="GET">
            <select class="form-select" name="year" onchange="this.form.submit()" style="min-width: 120px;">
                <?php foreach ($years as $y): ?>
                    <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>>
                        Tahun <?php echo $y; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <a href="create.php" class="btn btn-primary text-nowrap">
                <i class="fas fa-plus me-1"></i>Tambah Aset
            </a>
        </form>
    </div>
</div>

<!-- Tabel per KIB -->
<?php foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $kib): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-database me-2 text-primary"></i>KIB <?php echo $kib; ?></h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Bulan</th>
                            <th>Tahun</th>
                            <th>Jumlah Sekolah</th>
                            <th>Total Aset</th>
                            <th>Terakhir Diupdate</th>
                            <th class="pe-4 text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($assets_by_kib[$kib])): ?>
                            <?php foreach ($assets_by_kib[$kib] as $summary): ?>
                                <tr>
                                    <td class="ps-4"><strong><?php echo get_month_name($summary['month']); ?></strong></td>
                                    <td><?php echo $summary['year']; ?></td>
                                    <td>
                                        <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 rounded-pill px-3">
                                            <?php echo $summary['jumlah_sekolah']; ?> Sekolah
                                        </span>
                                    </td>
                                    <td>
                                        <strong class="text-primary">Rp <?php echo number_format($summary['total_aset'], 0, ',', '.'); ?></strong>
                                    </td>
                                    <td><?php echo format_date($summary['last_updated']); ?></td>
                                    <td class="pe-4 text-end">
                                        <a href="detail.php?kib=<?php echo $kib; ?>&year=<?php echo $summary['year']; ?>&month=<?php echo $summary['month']; ?>"
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye me-1"></i>Lihat Detail
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="fas fa-inbox fa-2x mb-3 d-block opacity-25"></i>
                                    Belum ada data untuk KIB <?php echo $kib; ?> tahun ini.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php require_once '../inc/footer.php'; ?>
