<?php
require_once '../inc/auth.php';
require_once '../inc/helpers.php';

require_login();
$user = current_user();
$units = get_units();
$conditions = get_item_conditions();

$selected_unit = (int)($_GET['unit_id'] ?? 0);

if ($selected_unit > 0) {
    $items = db_fetch_all(
        "SELECT i.*, u.name AS unit_name, u.code AS unit_code, us.name AS created_by_name
         FROM items i
         LEFT JOIN units u ON i.unit_id = u.id
         LEFT JOIN users us ON i.created_by = us.id
         WHERE i.unit_id = ?
         ORDER BY i.created_at DESC",
        'i',
        [$selected_unit]
    );
    $summary = db_fetch_all(
        "SELECT `condition`, COUNT(*) AS cnt
         FROM items
         WHERE unit_id = ?
         GROUP BY `condition`",
        'i',
        [$selected_unit]
    );
} else {
    $items = db_fetch_all(
        "SELECT i.*, u.name AS unit_name, u.code AS unit_code, us.name AS created_by_name
         FROM items i
         LEFT JOIN units u ON i.unit_id = u.id
         LEFT JOIN users us ON i.created_by = us.id
         ORDER BY i.created_at DESC"
    );
    $summary = db_fetch_all(
        "SELECT `condition`, COUNT(*) AS cnt
         FROM items
         GROUP BY `condition`"
    );
}

$counts = ['layak_pakai' => 0, 'tidak_layak_pakai' => 0];
foreach ($summary as $row) {
    $key = $row['condition'] ?? '';
    if (isset($counts[$key])) $counts[$key] = (int)$row['cnt'];
}

$page_title = 'Kelola Barang';
require_once '../inc/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h4 class="mb-1"><i class="fas fa-box-open me-2"></i>Kelola Barang</h4>
        <p class="text-muted mb-0">Daftar inventaris barang dan kondisi per unit</p>
    </div>
    <div class="d-flex gap-2">
        <a href="create.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Tambah Barang
        </a>
    </div>
</div>

<div class="row">
    <div class="col-lg-8 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="fas fa-list me-2"></i>Daftar Barang</h6>

                <form method="GET" class="d-flex gap-2" style="max-width: 300px;">
                    <select class="form-select form-select-sm" name="unit_id" onchange="this.form.submit()">
                        <option value="0">Semua Unit</option>
                        <?php foreach ($units as $u): ?>
                            <option value="<?php echo $u['id']; ?>" <?php echo ($selected_unit === (int)$u['id']) ? 'selected' : ''; ?>>
                                <?php echo escape($u['name']); ?> (<?php echo escape($u['code']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Unit</th>
                                <th>Jenis Barang</th>
                                <th>Kondisi</th>
                                <th>Foto</th>
                                <th>Waktu</th>
                                <th class="pe-4 text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($items)): ?>
                                <?php foreach ($items as $it): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-bold text-dark"><?php echo escape($it['unit_name']); ?></div>
                                            <small class="text-muted"><?php echo escape($it['unit_code']); ?></small>
                                        </td>
                                        <td><?php echo escape($it['item_name']); ?></td>
                                        <td>
                                            <?php
                                                $label = $conditions[$it['condition']] ?? $it['condition'];
                                                $badgeClass = ($it['condition'] === 'layak_pakai') ? 'bg-success' : 'bg-danger';
                                            ?>
                                            <span class="badge <?php echo $badgeClass; ?> bg-opacity-10 text-<?php echo ($it['condition'] === 'layak_pakai') ? 'success' : 'danger'; ?> border border-<?php echo ($it['condition'] === 'layak_pakai') ? 'success' : 'danger'; ?> border-opacity-25 rounded-pill px-3">
                                                <?php echo escape($label); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($it['photo_key'])): ?>
                                                <?php $imgUrl = APP_URL . '/assets/download.php?key=' . urlencode($it['photo_key']) . '&inline=1'; ?>
                                                <a href="<?php echo $imgUrl; ?>" target="_blank" title="Lihat foto" class="d-block" style="width: 40px; height: 40px;">
                                                    <img src="<?php echo $imgUrl; ?>" alt="Foto" class="img-fluid rounded border" style="width: 100%; height: 100%; object-fit: cover;">
                                                </a>
                                            <?php else: ?>
                                                <div class="bg-light rounded d-flex align-items-center justify-content-center text-muted border" style="width: 40px; height: 40px;">
                                                    <i class="fas fa-image"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?php echo format_date($it['created_at']); ?></small>
                                        </td>
                                        <td class="pe-4 text-end">
                                            <div class="btn-group btn-group-sm">
                                                <a href="edit.php?id=<?php echo (int)$it['id']; ?>" class="btn btn-outline-secondary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="delete.php?id=<?php echo (int)$it['id']; ?>" class="btn btn-outline-danger" onclick="return confirm('Yakin hapus barang ini?');">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="text-center text-muted py-5">Belum ada data barang.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="fas fa-chart-pie me-2"></i>Kondisi Barang</h6>
            </div>
            <div class="card-body text-center position-relative" style="height: 300px;">
                <canvas id="itemsPie"></canvas>
            </div>
            <div class="card-footer bg-light">
                <div class="d-flex justify-content-around text-center">
                    <div>
                        <h4 class="mb-0 text-success fw-bold"><?php echo (int)$counts['layak_pakai']; ?></h4>
                        <small class="text-muted">Layak</small>
                    </div>
                    <div>
                        <h4 class="mb-0 text-danger fw-bold"><?php echo (int)$counts['tidak_layak_pakai']; ?></h4>
                        <small class="text-muted">Rusak</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../inc/footer.php'; ?>

<script>
    const pieData = {
        labels: ['Layak Pakai', 'Tidak Layak Pakai'],
        datasets: [{
            data: [<?php echo (int)$counts['layak_pakai']; ?>, <?php echo (int)$counts['tidak_layak_pakai']; ?>],
            backgroundColor: ['#4cc9f0', '#f72585'],
            hoverBackgroundColor: ['#4895ef', '#b5179e'],
            borderWidth: 0
        }]
    };

    const ctx = document.getElementById('itemsPie').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: pieData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        usePointStyle: true
                    }
                }
            },
            cutout: '70%'
        }
    });
</script>
