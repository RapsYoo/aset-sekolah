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

$flash = get_flash_message();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Barang - Sistem Monitoring Aset Sekolah</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #667eea;
            --secondary: #764ba2;
        }
        .navbar { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); }
        body { background: #f8f9fa; }
        .table-container { background: white; border-radius: 8px; padding: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
    </style>
</head>
<body>
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

    <div class="container-fluid mt-4">
        <div class="row mb-3">
            <div class="col-md-6">
                <h3><i class="fas fa-box-open me-2"></i>Kelola Barang</h3>
                <small class="text-muted">CRUD data barang per unit beserta kondisinya.</small>
            </div>
            <div class="col-md-6 text-end">
                <a href="create.php" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i>Tambah Barang
                </a>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?php echo escape($flash['type']); ?> alert-dismissible fade show" role="alert">
                <?php echo escape($flash['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8">
                <div class="table-container mb-4">
                    <form method="GET" class="row g-3 align-items-end mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Filter Unit / Sekolah</label>
                            <select class="form-select" name="unit_id">
                                <option value="0">Semua Unit</option>
                                <?php foreach ($units as $u): ?>
                                    <option value="<?php echo $u['id']; ?>" <?php echo ($selected_unit === (int)$u['id']) ? 'selected' : ''; ?>>
                                        <?php echo escape($u['name']); ?> (<?php echo escape($u['code']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-outline-primary w-100" type="submit">
                                <i class="fas fa-filter me-1"></i>Terapkan
                            </button>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Unit</th>
                                    <th>Jenis Barang</th>
                                    <th>Kondisi</th>
                                    <th>Foto</th>
                                    <th>Dibuat Oleh</th>
                                    <th>Waktu</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($items)): ?>
                                    <?php foreach ($items as $it): ?>
                                        <tr>
                                            <td><strong><?php echo escape($it['unit_name']); ?></strong> <small class="text-muted">(<?php echo escape($it['unit_code']); ?>)</small></td>
                                            <td><?php echo escape($it['item_name']); ?></td>
                                            <td>
                                                <?php
                                                    $label = $conditions[$it['condition']] ?? $it['condition'];
                                                    $badgeClass = ($it['condition'] === 'layak_pakai') ? 'bg-success' : 'bg-danger';
                                                ?>
                                                <span class="badge <?php echo $badgeClass; ?>"><?php echo escape($label); ?></span>
                                            </td>
                                            <td>
                                                <?php if (!empty($it['photo_key'])): ?>
                                                    <?php $imgUrl = APP_URL . '/assets/download.php?key=' . urlencode($it['photo_key']) . '&inline=1'; ?>
                                                    <a href="<?php echo $imgUrl; ?>" target="_blank" title="Lihat foto">
                                                        <img src="<?php echo $imgUrl; ?>" alt="<?php echo escape($it['item_name']); ?>" class="img-thumbnail" style="max-height:64px;max-width:64px;object-fit:cover;">
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo escape($it['created_by_name'] ?? '-'); ?></td>
                                            <td><?php echo format_date($it['created_at']); ?></td>
                                            <td>
                                                <a href="edit.php?id=<?php echo (int)$it['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="delete.php?id=<?php echo (int)$it['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Yakin hapus barang ini?');">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="6" class="text-center text-muted">Belum ada data barang.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="table-container">
                    <h5 class="mb-3"><i class="fas fa-chart-pie me-2"></i>Diagram Kondisi Barang</h5>
                    <canvas id="itemsPie" height="220"></canvas>
                    <small class="text-muted">Hijau: Layak Pakai, Merah: Tidak Layak Pakai</small>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        const pieData = {
            labels: ['Layak Pakai', 'Tidak Layak Pakai'],
            datasets: [{
                data: [<?php echo (int)$counts['layak_pakai']; ?>, <?php echo (int)$counts['tidak_layak_pakai']; ?>],
                backgroundColor: ['#28a745', '#dc3545'],
                borderWidth: 1
            }]
        };
        const ctx = document.getElementById('itemsPie').getContext('2d');
        new Chart(ctx, {
            type: 'pie',
            data: pieData,
            options: {
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    </script>
</body>
</html>
