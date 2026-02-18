<?php
require_once __DIR__ . '/db.php';

/**
 * Login user
 */
function login($email, $password) {
    $user = db_fetch_one(
        "SELECT u.*, r.name as role_name FROM users u 
         LEFT JOIN roles r ON u.role_id = r.id 
         WHERE u.email = ?",
        's',
        [$email]
    );
    
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role_name'] ?? 'pegawai';
        return true;
    }
    return false;
}

/**
 * Logout user
 */
function logout() {
    session_destroy();
}

/**
 * Check apakah user sudah login
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Check apakah user adalah pengembang (developer)
 */
function is_pengembang() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'pengembang';
}

/**
 * Check apakah user adalah admin (atau pengembang yang punya akses admin)
 */
function is_admin() {
    return isset($_SESSION['user_role']) && ($_SESSION['user_role'] === 'admin' || $_SESSION['user_role'] === 'pengembang');
}

/**
 * Check apakah user murni admin (bukan pengembang)
 */
function is_real_admin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function is_supervisor() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'supervisor';
}

function can_edit() {
    return !is_supervisor();
}

/**
 * Get current user
 */
function current_user() {
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'name' => $_SESSION['user_name'] ?? null,
        'email' => $_SESSION['user_email'] ?? null,
        'role' => $_SESSION['user_role'] ?? null
    ];
}

/**
 * Generate CSRF token
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verify_csrf_token($token) {
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

/**
 * Redirect dengan flash message
 */
function redirect_with_message($url, $message, $type = 'success') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    header("Location: $url");
    exit();
}

/**
 * Get dan hapus flash message
 */
function get_flash_message() {
    $message = $_SESSION['flash_message'] ?? null;
    $type = $_SESSION['flash_type'] ?? 'info';
    
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
    
    return $message ? ['message' => $message, 'type' => $type] : null;
}

/**
 * Require login
 */
function require_login() {
    if (!is_logged_in()) {
        header("Location: " . APP_URL . "/index.php");
        exit();
    }
}

/**
 * Require admin
 */
function require_admin() {
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        die("Akses Ditolak");
    }
}

function require_can_edit() {
    require_login();
    if (!can_edit()) {
        http_response_code(403);
        die("Akses Ditolak");
    }
}

?>
