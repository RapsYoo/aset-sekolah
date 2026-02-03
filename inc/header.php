<?php
// Ensure APP_URL is defined (safety check)
if (!defined('APP_URL')) {
    // Fallback if config wasn't loaded (shouldn't happen if structure is followed)
    define('APP_URL', 'http://localhost/aset-sekolah');
}

// Helper to determine active state
function is_active($path) {
    $current = $_SERVER['SCRIPT_NAME']; // e.g., /aset-sekolah/dashboard.php
    if (strpos($current, $path) !== false) {
        return 'active';
    }
    return '';
}

if (!isset($user) && function_exists('current_user')) {
    $user = current_user();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'Sistem Monitoring Aset Sekolah'; ?></title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/public/css/style.css?v=<?php echo time(); ?>">

    <!-- Chart.js (Loaded in head to ensure availability for inline scripts) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <nav class="sidebar">
            <div class="sidebar-brand">
                <i class="fas fa-school me-2"></i> SIM Aset
            </div>

            <div class="sidebar-menu">
                <div class="sidebar-heading">Menu Utama</div>

                <a href="<?php echo APP_URL; ?>/dashboard.php" class="sidebar-link <?php echo is_active('dashboard.php'); ?>">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>

                <a href="<?php echo APP_URL; ?>/assets/index.php" class="sidebar-link <?php echo is_active('assets/'); ?>">
                    <i class="fas fa-boxes"></i> <!-- Changed icon to be more specific -->
                    <span>Data Aset</span>
                </a>

                <a href="<?php echo APP_URL; ?>/items/index.php" class="sidebar-link <?php echo is_active('items/'); ?>">
                    <i class="fas fa-box-open"></i>
                    <span>Kelola Barang</span>
                </a>

                <?php if (function_exists('is_admin') && is_admin()): ?>
                    <div class="sidebar-divider"></div>
                    <div class="sidebar-heading">Administrator</div>

                    <a href="<?php echo APP_URL; ?>/admin/units.php" class="sidebar-link <?php echo is_active('admin/units.php'); ?>">
                        <i class="fas fa-building"></i>
                        <span>Kelola Unit</span>
                    </a>

                    <a href="<?php echo APP_URL; ?>/admin/users.php" class="sidebar-link <?php echo is_active('admin/users.php'); ?>">
                        <i class="fas fa-users"></i>
                        <span>Manajemen User</span>
                    </a>
                    
                    <a href="<?php echo APP_URL; ?>/admin/roles.php" class="sidebar-link <?php echo is_active('admin/roles.php'); ?>">
                        <i class="fas fa-user-shield"></i>
                        <span>Kelola Role</span>
                    </a>
                    <a href="<?php echo APP_URL; ?>/automation/index.php" class="sidebar-link <?php echo is_active('automation/'); ?>">
                        <i class="fas fa-robot"></i>
                        <span>Automation</span>
                    </a>
                <?php endif; ?>

                <div class="sidebar-divider"></div>
                <div class="sidebar-heading">Akun</div>

                <a href="<?php echo APP_URL; ?>/profile.php" class="sidebar-link <?php echo is_active('profile.php'); ?>">
                    <i class="fas fa-user-circle"></i>
                    <span>Profil Saya</span>
                </a>

                <a href="<?php echo APP_URL; ?>/logout.php" class="sidebar-link text-danger">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </nav>

        <!-- Main Section -->
        <div class="main">
            <!-- Top Header -->
            <header class="header">
                <div class="d-flex align-items-center">
                    <i class="fas fa-bars header-toggle me-3"></i>
                    <h5 class="m-0 d-none d-md-block text-muted">
                        <?php echo isset($page_title) ? $page_title : 'Dashboard'; ?>
                    </h5>
                </div>

                <div class="d-flex align-items-center">
                    <div class="dropdown">
                        <a class="d-flex align-items-center text-decoration-none dropdown-toggle text-dark" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 35px; height: 35px;">
                                <?php echo strtoupper(substr($user['name'] ?? 'U', 0, 1)); ?>
                            </div>
                            <span class="d-none d-sm-inline fw-medium"><?php echo escape($user['name'] ?? 'User'); ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg">
                            <li><h6 class="dropdown-header">Login sebagai <?php echo escape($user['role'] ?? ''); ?></h6></li>
                            <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/profile.php"><i class="fas fa-user me-2"></i> Profil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?php echo APP_URL; ?>/logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                        </ul>
                    </div>
                </div>
            </header>

            <!-- Content Body -->
            <div class="content">
