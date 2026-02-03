<?php
require_once '../inc/auth.php';
require_once '../inc/helpers.php';
require_once '../inc/automation.php';
require_admin();
header('Content-Type: application/json');
$job = sanitize($_GET['job'] ?? '');
if (empty($job)) {
    echo json_encode(['error' => 'missing_job']);
    exit();
}
$data = automation_get_status($job);
echo json_encode($data);
exit();
