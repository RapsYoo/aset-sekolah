<?php
require_once '../inc/auth.php';
require_once '../inc/helpers.php';
require_once '../inc/automation.php';
require_admin();
$error = '';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit();
}
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_message'] = 'Token CSRF tidak valid';
    $_SESSION['flash_type'] = 'danger';
    header("Location: index.php");
    exit();
}
$kib = strtoupper(sanitize($_POST['kib'] ?? ''));
$tgl = sanitize($_POST['tgl'] ?? '');
$unit = sanitize($_POST['unit'] ?? '');
$jenis_aset = sanitize($_POST['jenis_aset'] ?? '1');
$jenis_kode = sanitize($_POST['jenis_kode'] ?? '1');
if (empty($kib) || empty($tgl) || empty($unit)) {
    $_SESSION['flash_message'] = 'Semua field wajib diisi';
    $_SESSION['flash_type'] = 'danger';
    header("Location: index.php");
    exit();
}
$job_id = automation_job_id();
$meta = ['kib' => $kib, 'tgl' => $tgl, 'unit' => $unit, 'jenis_aset' => $jenis_aset, 'jenis_kode' => $jenis_kode];
$dir = automation_init_job($job_id, $meta);
automation_start_process($job_id, $meta);
$_SESSION['flash_message'] = 'Job dimulai';
$_SESSION['flash_type'] = 'success';
header("Location: index.php?job=" . urlencode($job_id));
exit();
