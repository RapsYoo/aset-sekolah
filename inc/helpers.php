<?php

/**
 * Escape output untuk keamanan
 */
function escape($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Format tanggal
 */
function format_date($date, $format = 'd M Y') {
    return date($format, strtotime($date));
}

/**
 * Format currency
 */
function format_currency($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

/**
 * Get daftar bulan
 */
function get_months() {
    return [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret',
        4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September',
        10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
}

/**
 * Get nama bulan
 */
function get_month_name($month) {
    $months = get_months();
    return $months[$month] ?? '';
}

/**
 * KIB types
 */
function get_kib_types() {
    return ['A', 'B', 'C', 'D', 'E', 'F'];
}

/**
 * Kondisi barang
 */
function get_item_conditions() {
    return [
        'layak_pakai' => 'Layak Pakai',
        'tidak_layak_pakai' => 'Tidak Layak Pakai'
    ];
}

/**
 * Ambil semua units (sekolah)
 */
function get_units() {
    return db_fetch_all("SELECT id, code, name FROM units ORDER BY name ASC");
}

/**
 * Sanitize input
 */
function sanitize($input) {
    return trim(htmlspecialchars($input ?? '', ENT_QUOTES, 'UTF-8'));
}

?>
