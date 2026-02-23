<?php
/**
 * parse_excel.php - Universal KIB Parser (A-F) untuk Auto-Fill Form
 * Endpoint AJAX untuk parsing file Excel KIB
 */

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once '../inc/auth.php';
require_once '../inc/storage.php';

require_login();

// ═══════════════════════════════════════════════════════════════════
//  KONFIGURASI STRUKTUR KIB
// ═══════════════════════════════════════════════════════════════════

const KIB_STRUCTURES = [
    'A' => [
        'name'       => 'KIB A — TANAH',
        'identifier' => 'TANAH',
        'cols'       => [
            'nama'     => 1,
            'kode'     => 2,
            'register' => 3,
            'judul'    => 4,
            'jumlah'   => null,
            'tahun'    => 5,
            'asal'     => 12,
            'harga'    => 13,
            'kab'      => 7,
        ]
    ],
    'B' => [
        'name'       => 'KIB B — PERALATAN DAN MESIN',
        'identifier' => 'PERALATAN DAN MESIN',
        'cols'       => [
            'nama'     => 2,
            'kode'     => 1,
            'register' => 3,
            'judul'    => 4,
            'jumlah'   => 15,
            'tahun'    => 7,
            'asal'     => 13,
            'harga'    => 16,
            'kab'      => 14,
        ]
    ],
    'C' => [
        'name'       => 'KIB C — GEDUNG DAN BANGUNAN',
        'identifier' => 'GEDUNG DAN BANGUNAN',
        'cols'       => [
            'nama'     => 1,
            'kode'     => 3,
            'register' => 5,
            'judul'    => 1,
            'jumlah'   => null,
            'tahun'    => 17,
            'asal'     => 18,
            'harga'    => 19,
            'kab'      => 8,
        ]
    ],
    'D' => [
        'name'       => 'KIB D — JALAN, JEMBATAN, IRIGASI',
        'identifier' => 'JALAN',
        'cols'       => [
            'nama'     => 1,
            'kode'     => 2,
            'register' => 3,
            'judul'    => 5,
            'jumlah'   => null,
            'tahun'    => 14,
            'asal'     => 15,
            'harga'    => 16,
            'kab'      => 8,
        ]
    ],
    'E' => [
        'name'       => 'KIB E — ASET TETAP LAINNYA',
        'identifier' => 'ASET TETAP LAINNYA',
        'cols'       => [
            'nama'     => 1,
            'kode'     => 2,
            'register' => 3,
            'judul'    => 4,
            'jumlah'   => 13,
            'tahun'    => 14,
            'asal'     => 15,
            'harga'    => 16,
            'kab'      => 18,
        ]
    ],
    'F' => [
        'name'       => 'KIB F — KONSTRUKSI DALAM PENGERJAAN',
        'identifier' => 'KONSTRUKSI DALAM PENGERJAAN',
        'cols'       => [
            'nama'     => 1,
            'kode'     => 2,
            'register' => 10,
            'judul'    => 5,
            'jumlah'   => null,
            'tahun'    => 8,
            'asal'     => 11,
            'harga'    => 12,
            'kab'      => 6,
        ]
    ],
];

// ═══════════════════════════════════════════════════════════════════
//  FUNGSI PARSER
// ═══════════════════════════════════════════════════════════════════

/**
 * Parse file XLS (HTML) dan ekstrak data KIB
 */
function parseKibExcel(string $filepath): array
{
    $html = file_get_contents($filepath);
    if ($html === false) {
        throw new RuntimeException("Tidak bisa membaca file.");
    }

    $encoding = mb_detect_encoding($html, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
    if ($encoding && $encoding !== 'UTF-8') {
        $html = mb_convert_encoding($html, 'UTF-8', $encoding);
    }

    $dom = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();

    $xpath  = new DOMXPath($dom);
    $tables = $xpath->query('//table');

    if ($tables->length < 2) {
        throw new RuntimeException("Struktur tabel tidak dikenali.");
    }

    // Deteksi Jenis KIB
    $infoTable = $tables->item(0);
    $kibType   = detectKibType($xpath, $infoTable);
    
    if (!$kibType) {
        throw new RuntimeException("Jenis KIB tidak dikenali dari judul tabel.");
    }

    // Ekstrak Info Sekolah
    $info = extractInfoSekolah($xpath, $infoTable);

    // Ekstrak Data Barang
    $dataTable = $tables->item(1);
    [$items, $jumlahHarga, $totalBarang, $totalUnit] = extractDataBarang($xpath, $dataTable, $kibType);

    return [
        'kib_type'     => $kibType,
        'kib_name'     => KIB_STRUCTURES[$kibType]['name'],
        'info'         => $info,
        'items'        => $items,
        'jumlah_harga' => $jumlahHarga,
        'total_barang' => $totalBarang,
        'total_unit'   => $totalUnit,
    ];
}

/**
 * Deteksi jenis KIB dari judul tabel pertama
 */
function detectKibType(DOMXPath $xpath, DOMNode $table): ?string
{
    $rows = $xpath->query('.//tr', $table);
    if ($rows->length === 0) return null;

    $firstRow = $rows->item(0);
    $cells    = $xpath->query('.//td|.//th', $firstRow);
    
    if ($cells->length === 0) return null;
    
    $title = strtoupper(trim($cells->item(0)->textContent));

    foreach (KIB_STRUCTURES as $type => $config) {
        if (strpos($title, $config['identifier']) !== false) {
            return $type;
        }
    }

    return null;
}

/**
 * Ekstrak informasi sekolah dari tabel pertama
 */
function extractInfoSekolah(DOMXPath $xpath, DOMNode $table): array
{
    $info = [];
    $rows = $xpath->query('.//tr', $table);

    foreach ($rows as $row) {
        $cells = $xpath->query('.//td|.//th', $row);
        
        if ($cells->length >= 3) {
            $key   = trim($cells->item(1)->textContent);
            $value = trim($cells->item(2)->textContent);
            $value = ltrim($value, ': ');
            if ($key !== '') {
                $info[$key] = $value;
            }
        } elseif ($cells->length === 2) {
            $key   = trim($cells->item(0)->textContent);
            $value = trim($cells->item(1)->textContent);
            $value = ltrim($value, ': ');
            if ($key !== '' && $key !== 'SULAWESI SELATAN') {
                $info[$key] = $value;
            }
        }
    }
    
    return $info;
}

/**
 * Ekstrak data barang berdasarkan jenis KIB
 */
function extractDataBarang(DOMXPath $xpath, DOMNode $table, string $kibType): array
{
    $allRows = $xpath->query('.//tr', $table);
    $matrix  = [];

    foreach ($allRows as $row) {
        $cells     = $xpath->query('.//td|.//th', $row);
        $rowValues = [];
        foreach ($cells as $cell) {
            $rowValues[] = trim(preg_replace('/\s+/', ' ', $cell->textContent));
        }
        $matrix[] = $rowValues;
    }

    $items       = [];
    $jumlahHarga = '0';
    $totalBarang = 0;
    $totalUnit   = 0;
    
    $config      = KIB_STRUCTURES[$kibType];

    // Deteksi baris data dimulai (skip header)
    $dataStartRow = 0;
    foreach ($matrix as $idx => $row) {
        if (!empty($row) && count($row) > 5) {
            // Cari baris penomor kolom: 1, 2, 3, 4, ...
            if ($row[0] === '1' && $row[1] === '2' && $row[2] === '3') {
                $dataStartRow = $idx + 1;
                break;
            }
        }
    }

    // Ambil baris data
    for ($i = $dataStartRow; $i < count($matrix); $i++) {
        $row = $matrix[$i];
        
        if (empty($row) || (count($row) === 1 && trim($row[0]) === '')) {
            continue;
        }

        // Deteksi baris JUMLAH
        if (isset($row[0]) && strtolower(trim($row[0])) === 'jumlah') {
            if ($kibType === 'B') {
                // Format KIB B: Jumlah | totalUnit | totalHarga
                $totalUnit   = isset($row[1]) ? $row[1] : '0';
                $jumlahHarga = isset($row[2]) ? $row[2] : '0';
            } else {
                // Format lainnya: Jumlah | totalHarga
                foreach ($row as $cell) {
                    $clean = str_replace([',', '.'], '', $cell);
                    if (is_numeric($clean) && strlen($clean) > 3) {
                        $jumlahHarga = $cell;
                        break;
                    }
                }
            }
            continue;
        }

        // Hanya ambil baris data (diawali angka)
        if (isset($row[0]) && is_numeric($row[0]) && intval($row[0]) > 0) {
            $items[] = $row;
            $totalBarang++;
            
            // Hitung total unit jika ada kolom jumlah
            if ($config['cols']['jumlah'] !== null && isset($row[$config['cols']['jumlah']])) {
                $jml = $row[$config['cols']['jumlah']];
                $clean = str_replace([',', '.'], '', $jml);
                if (is_numeric($clean) && intval($clean) < 100000) {
                    $totalUnit += intval($clean);
                }
            }
        }
    }

    return [$items, $jumlahHarga, $totalBarang, $totalUnit];
}

/**
 * Format angka rupiah
 */
function formatRupiah(string $raw): string
{
    $raw = trim($raw);
    $numeric = (float) str_replace(',', '', $raw);
    if ($numeric === 0.0) return $raw;
    return 'Rp ' . number_format($numeric, 0, ',', '.');
}

/**
 * Ambil nilai dari row berdasarkan index kolom KIB
 */
function getColValue(array $row, string $kibType, string $colName): string
{
    $config = KIB_STRUCTURES[$kibType];
    $index  = $config['cols'][$colName] ?? null;
    
    if ($index === null || !isset($row[$index])) {
        return '';
    }
    
    $value = $row[$index];
    
    // Bersihkan prefix quote dari kode barang dan register
    if (in_array($colName, ['kode', 'register'])) {
        $value = ltrim($value, "'");
    }
    
    return $value;
}

/**
 * Match dan create unit berdasarkan nama sekolah
 */
function matchOrCreateUnit(string $namaUnit, string $kodeLokasi = ''): ?array
{
    if (empty($namaUnit)) return null;

    // Coba match dengan nama yang ada di database
    $stmt = db_prepare("SELECT id, name, code FROM units WHERE LOWER(name) LIKE LOWER(?) LIMIT 1");
    $pattern = '%' . $namaUnit . '%';
    $stmt->bind_param('s', $pattern);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $unit = $result->fetch_assoc();
        return [
            'id'   => (int)$unit['id'],
            'name' => $unit['name'],
            'code' => $unit['code'],
            'auto_created' => false
        ];
    }

    // Jika tidak ditemukan, create unit baru
    // Gunakan kode lokasi dari Excel jika tersedia, kalau tidak generate dari nama
    if (!empty($kodeLokasi)) {
        $code = trim($kodeLokasi);
    } else {
        $code = strtoupper(preg_replace('/[^A-Z0-9]/', '', $namaUnit));
        if (strlen($code) > 20) {
            $code = substr($code, 0, 20);
        }
        if (empty($code)) {
            $code = 'UNIT' . time();
        }
    }

    // Pastikan code unik
    $baseCode = $code;
    $counter = 1;
    while (true) {
        $checkStmt = db_prepare("SELECT id FROM units WHERE code = ?");
        $checkStmt->bind_param('s', $code);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        if ($checkResult->num_rows === 0) break;
        $code = $baseCode . $counter;
        $counter++;
    }

    // Insert unit baru
    $insertStmt = db_prepare("INSERT INTO units (name, code) VALUES (?, ?)");
    $insertStmt->bind_param('ss', $namaUnit, $code);
    
    if ($insertStmt->execute()) {
        return [
            'id'   => $insertStmt->insert_id,
            'name' => $namaUnit,
            'code' => $code,
            'auto_created' => true
        ];
    }

    return null;
}

// ═══════════════════════════════════════════════════════════════════
//  MAIN HANDLER
// ═══════════════════════════════════════════════════════════════════

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method tidak diperbolehkan');
    }

    if (!isset($_FILES['excel']) || $_FILES['excel']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File tidak ditemukan atau error upload');
    }

    $file = $_FILES['excel'];
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($ext, ['xls', 'xlsx'])) {
        throw new Exception('Hanya file .xls atau .xlsx yang diperbolehkan');
    }

    if ($file['size'] > 10 * 1024 * 1024) {
        throw new Exception('Ukuran file melebihi batas 10 MB');
    }

    // Parse Excel
    $result = parseKibExcel($file['tmp_name']);

    // Format untuk response
    $jumlahHargaNumeric = (float) str_replace(',', '', $result['jumlah_harga']);
    $jumlahHargaFormatted = formatRupiah($result['jumlah_harga']);

    // Ekstrak nama unit dari info (prioritas: sub unit organisasi > unit organisasi)
    $namaUnit = '';
    $unitKeywords = ['sub unit organisasi', 'sub unit', 'nama sekolah', 'sekolah', 'unit organisasi'];
    foreach ($unitKeywords as $keyword) {
        foreach ($result['info'] as $key => $val) {
            $keyLower = strtolower(trim($key));
            if (strpos($keyLower, $keyword) !== false && !empty($val)) {
                // Abaikan "DINAS PENDIDIKAN" karena itu unit induk, bukan sekolah
                if (strtoupper(trim($val)) === 'DINAS PENDIDIKAN') {
                    continue;
                }
                $namaUnit = $val;
                break 2;
            }
        }
    }

    // Ekstrak kode lokasi dari info (case-insensitive)
    $kodeLokasi = '';
    foreach ($result['info'] as $key => $val) {
        if (stripos($key, 'kode lokasi') !== false && !empty($val)) {
            $kodeLokasi = trim($val);
            break;
        }
    }

    // Match atau create unit
    $unitMatch = null;
    $unitAutoCreated = false;
    if (!empty($namaUnit)) {
        $unitMatch = matchOrCreateUnit($namaUnit, $kodeLokasi);
        if ($unitMatch) {
            $unitAutoCreated = $unitMatch['auto_created'];
            unset($unitMatch['auto_created']);
        }
    }

    // Format items untuk preview
    $formattedItems = [];
    foreach ($result['items'] as $row) {
        $formattedItems[] = [
            'no'       => $row[0] ?? '',
            'nama'     => getColValue($row, $result['kib_type'], 'nama'),
            'kode'     => getColValue($row, $result['kib_type'], 'kode'),
            'register' => getColValue($row, $result['kib_type'], 'register'),
            'judul'    => getColValue($row, $result['kib_type'], 'judul'),
            'jumlah'   => getColValue($row, $result['kib_type'], 'jumlah'),
            'tahun'    => getColValue($row, $result['kib_type'], 'tahun'),
            'asal'     => getColValue($row, $result['kib_type'], 'asal'),
            'harga'    => formatRupiah(getColValue($row, $result['kib_type'], 'harga')),
            'kab'      => getColValue($row, $result['kib_type'], 'kab'),
        ];
    }

    // Response
    echo json_encode([
        'success'                => true,
        'kib_type'               => $result['kib_type'],
        'kib_name'               => $result['kib_name'],
        'info'                   => $result['info'],
        'items'                  => $formattedItems,
        'total_barang'           => $result['total_barang'],
        'total_unit'             => $result['total_unit'],
        'jumlah_harga'           => $result['jumlah_harga'],
        'jumlah_harga_numeric'   => $jumlahHargaNumeric,
        'jumlah_harga_formatted' => $jumlahHargaFormatted,
        'nama_unit'              => $namaUnit,
        'unit_match'             => $unitMatch,
        'unit_auto_created'      => $unitAutoCreated,
        'has_jumlah_column'      => KIB_STRUCTURES[$result['kib_type']]['cols']['jumlah'] !== null,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}