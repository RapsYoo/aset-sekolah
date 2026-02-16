<?php
/**
 * AJAX Endpoint: Parse file Excel KIB (HTML-based .xls)
 * Menerima file upload, parsing dengan DOMDocument, return JSON.
 */
require_once '../inc/auth.php';
require_once '../inc/helpers.php';

require_login();
require_can_edit();

header('Content-Type: application/json; charset=utf-8');

// ─────────────────────────────────────────────────
//  FUNGSI PARSER (dari scraping/index.php)
// ─────────────────────────────────────────────────

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

    $infoTable = $tables->item(0);
    $info = extractInfoSekolah($xpath, $infoTable);

    $dataTable = $tables->item(1);
    [$header, $items, $jumlahHarga, $totalBarang] = extractDataBarang($xpath, $dataTable);

    return [
        'info'         => $info,
        'header'       => $header,
        'items'        => $items,
        'jumlah_harga' => $jumlahHarga,
        'total_barang' => $totalBarang,
    ];
}

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
            if ($key !== '') {
                $info[$key] = $value;
            }
        }
    }
    return $info;
}

function extractDataBarang(DOMXPath $xpath, DOMNode $table): array
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

    $header        = [];
    $items         = [];
    $jumlahHarga   = '0';
    $totalBarang   = 0;

    $dataStartRow = 0;
    foreach ($matrix as $idx => $row) {
        if (!empty($row[0]) && strtolower($row[0]) === 'no') {
            $header       = $row;
            $dataStartRow = $idx + 1;
            
            // Cek apakah baris berikutnya adalah penomoran kolom (1, 2, 3, ...)
            if (isset($matrix[$idx + 1])) {
                $nextRow = $matrix[$idx + 1];
                // Hitung berapa banyak cell yang berisi angka berurutan
                $seqCount = 0;
                foreach ($nextRow as $ci => $cell) {
                    if (trim($cell) === (string)($ci + 1)) {
                        $seqCount++;
                    }
                }
                // Jika >= 3 cell berurutan (1,2,3...), ini baris penomoran kolom
                if ($seqCount >= 3) {
                    $dataStartRow = $idx + 2;
                }
            }
            break;
        }
    }

    for ($i = $dataStartRow; $i < count($matrix); $i++) {
        $row = $matrix[$i];
        if (empty($row) || (count($row) === 1 && trim($row[0]) === '')) {
            continue;
        }

        if (isset($row[0]) && strtolower(trim($row[0])) === 'jumlah') {
            foreach ($row as $cell) {
                $clean = str_replace([',', '.'], '', $cell);
                if (is_numeric($clean) && strlen($clean) > 3) {
                    $jumlahHarga = $cell;
                    break;
                }
            }
            continue;
        }

        if (isset($row[0]) && is_numeric($row[0])) {
            // Skip baris yang terlihat seperti penomoran kolom (semua cell = angka kecil berurutan)
            $isNumberingRow = true;
            $numericCells = 0;
            foreach ($row as $ci => $cell) {
                if (trim($cell) === (string)($ci + 1)) {
                    $numericCells++;
                }
            }
            if ($numericCells >= 3 && $numericCells >= count($row) * 0.5) {
                continue; // skip baris penomoran
            }
            
            $items[] = $row;
            $totalBarang++;
        }
    }

    return [$header, $items, $jumlahHarga, $totalBarang];
}

function formatRupiahParse(string $raw): string
{
    $raw = trim($raw);
    $numeric = (float) str_replace(',', '', $raw);
    if ($numeric === 0.0) return $raw;
    return 'Rp ' . number_format($numeric, 0, ',', '.');
}

// ─────────────────────────────────────────────────
//  PROSES UPLOAD AJAX
// ─────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['excel_file'])) {
    echo json_encode(['success' => false, 'error' => 'Tidak ada file yang dikirim.']);
    exit;
}

$file = $_FILES['excel_file'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'Upload gagal. Kode error: ' . $file['error']]);
    exit;
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['xls', 'xlsx'])) {
    echo json_encode(['success' => false, 'error' => 'Hanya file .xls atau .xlsx yang diperbolehkan.']);
    exit;
}

if ($file['size'] > 10 * 1024 * 1024) {
    echo json_encode(['success' => false, 'error' => 'Ukuran file melebihi batas 10 MB.']);
    exit;
}

try {
    $result = parseKibExcel($file['tmp_name']);

    // Coba match nama unit/sekolah dari info ke database
    $infoValues = array_values($result['info']);
    $infoKeys   = array_keys($result['info']);

    // ── Ekstrak Nama Sekolah & Kode Lokasi dari info ──
    $namaUnit = '';
    $kodeLokasi = '';
    
    // Cari "SUB UNIT ORGANISASI" → nama sekolah
    // Cari "NO.KODE LOKASI" atau "KODE LOKASI" → kode unit
    foreach ($infoKeys as $idx => $key) {
        $keyLower = strtolower(trim($key));
        $val = trim($infoValues[$idx] ?? '');
        
        // Nama sekolah: prioritas "SUB UNIT" > lainnya
        if (strpos($keyLower, 'sub unit') !== false || strpos($keyLower, 'sub_unit') !== false) {
            $namaUnit = $val;
        }
        // Kode lokasi
        if (strpos($keyLower, 'kode lokasi') !== false || strpos($keyLower, 'no.kode') !== false || $keyLower === 'no kode lokasi') {
            $kodeLokasi = $val;
        }
    }
    
    // Fallback nama: coba dari nama file (misal "SMKN 4 BARRU.xls")
    if (empty($namaUnit)) {
        $fileBaseName = trim(pathinfo($file['name'], PATHINFO_FILENAME));
        // Jangan pakai nama file jika mengandung "dinas"
        if (!empty($fileBaseName) && stripos($fileBaseName, 'dinas') === false) {
            $namaUnit = $fileBaseName;
        }
    }
    
    // Fallback nama: cari key yang mengandung "nama" tapi bukan "dinas"
    if (empty($namaUnit)) {
        foreach ($infoKeys as $idx => $key) {
            $keyLower = strtolower($key);
            if (strpos($keyLower, 'nama') !== false || strpos($keyLower, 'sekolah') !== false) {
                $candidate = trim($infoValues[$idx] ?? '');
                if (!empty($candidate) && stripos($candidate, 'dinas') === false) {
                    $namaUnit = $candidate;
                    break;
                }
            }
        }
    }
    
    // ── Match / Auto-Insert ke database units ──
    $unitMatch = null;
    $unitAutoCreated = false;
    
    if (!empty($namaUnit)) {
        $units = get_units();
        
        // 1) Coba exact match atau partial match terhadap DB
        foreach ($units as $u) {
            if (
                strtolower(trim($u['name'])) === strtolower(trim($namaUnit)) ||
                stripos($u['name'], $namaUnit) !== false ||
                stripos($namaUnit, $u['name']) !== false
            ) {
                $unitMatch = $u;
                break;
            }
        }
        
        // 2) Coba match dari kode lokasi
        if (!$unitMatch && !empty($kodeLokasi)) {
            foreach ($units as $u) {
                if (strtolower(trim($u['code'])) === strtolower(trim($kodeLokasi))) {
                    $unitMatch = $u;
                    break;
                }
            }
        }
        
        // 3) Tidak ditemukan → auto-insert ke tabel units
        if (!$unitMatch) {
            $newCode = !empty($kodeLokasi) ? $kodeLokasi : ('AUTO_' . strtoupper(substr(md5($namaUnit), 0, 8)));
            $newName = $namaUnit;
            
            // Cek apakah code sudah ada (untuk avoid duplicate key)
            $existing = db_fetch_one("SELECT id, code, name FROM units WHERE code = ?", 's', [$newCode]);
            if ($existing) {
                // Code sudah ada, gunakan yang existing
                $unitMatch = $existing;
            } else {
                // Insert baru
                $stmtInsert = db_prepare("INSERT INTO units (code, name) VALUES (?, ?)");
                $stmtInsert->bind_param('ss', $newCode, $newName);
                if ($stmtInsert->execute()) {
                    $newId = $stmtInsert->insert_id;
                    $unitMatch = [
                        'id'   => $newId,
                        'code' => $newCode,
                        'name' => $newName,
                    ];
                    $unitAutoCreated = true;
                }
            }
        }
    }

    // Detect KIB type dari info (key + values) dan juga judul/header
    $kibType = '';
    $kibLabel = '';
    
    // Cek semua info keys dan values untuk KIB
    $allInfoText = '';
    foreach ($infoKeys as $idx => $key) {
        $allInfoText .= $key . ' ' . ($infoValues[$idx] ?? '') . ' ';
    }
    
    // Cari pattern "KIB A" s.d "KIB F" atau "Kartu Inventaris Barang A" dll
    if (preg_match('/kib\s*([a-f])/i', $allInfoText, $m)) {
        $kibType = strtoupper($m[1]);
    }
    // Cari dari deskripsi: "Aset Tetap - Tanah" => KIB A, "Peralatan" => KIB B, dll
    if (empty($kibType)) {
        $textLower = strtolower($allInfoText);
        if (strpos($textLower, 'tanah') !== false) { $kibType = 'A'; $kibLabel = 'KIB A - Tanah'; }
        elseif (strpos($textLower, 'peralatan') !== false || strpos($textLower, 'mesin') !== false) { $kibType = 'B'; $kibLabel = 'KIB B - Peralatan dan Mesin'; }
        elseif (strpos($textLower, 'gedung') !== false || strpos($textLower, 'bangunan') !== false) { $kibType = 'C'; $kibLabel = 'KIB C - Gedung dan Bangunan'; }
        elseif (strpos($textLower, 'jalan') !== false || strpos($textLower, 'irigasi') !== false || strpos($textLower, 'jaringan') !== false) { $kibType = 'D'; $kibLabel = 'KIB D - Jalan, Irigasi, dan Jaringan'; }
        elseif (strpos($textLower, 'aset tetap lainnya') !== false || strpos($textLower, 'buku') !== false || strpos($textLower, 'perpustakaan') !== false) { $kibType = 'E'; $kibLabel = 'KIB E - Aset Tetap Lainnya'; }
        elseif (strpos($textLower, 'konstruksi') !== false) { $kibType = 'F'; $kibLabel = 'KIB F - Konstruksi Dalam Pengerjaan'; }
    }

    // Cek nama file juga
    if (empty($kibType)) {
        if (preg_match('/kib\s*([a-f])/i', $file['name'], $m)) {
            $kibType = strtoupper($m[1]);
        }
    }
    
    // Set label default jika belum ada
    if (empty($kibLabel) && !empty($kibType)) {
        $kibLabels = [
            'A' => 'KIB A - Tanah',
            'B' => 'KIB B - Peralatan dan Mesin',
            'C' => 'KIB C - Gedung dan Bangunan',
            'D' => 'KIB D - Jalan, Irigasi, dan Jaringan',
            'E' => 'KIB E - Aset Tetap Lainnya',
            'F' => 'KIB F - Konstruksi Dalam Pengerjaan',
        ];
        $kibLabel = $kibLabels[$kibType] ?? 'KIB ' . $kibType;
    }

    // Hitung total unit dari kolom jumlah
    $totalUnit = 0;
    foreach ($result['items'] as $row) {
        $val = isset($row[13]) ? $row[13] : '';
        $clean = str_replace([',', '.'], '', $val);
        if (is_numeric($clean) && (int)$clean < 100000) {
            $totalUnit += (int)$clean;
        }
    }

    // Format items untuk preview
    $formattedItems = [];
    foreach ($result['items'] as $row) {
        $no       = $row[0]  ?? '';
        $nama     = $row[1]  ?? '';
        $kode     = ltrim($row[2]  ?? '', "'");
        $register = ltrim($row[3]  ?? '', "'");
        $judul    = $row[4]  ?? '';
        $jumlah   = $row[13] ?? '';
        $tahun    = $row[14] ?? '';
        $asal     = $row[15] ?? '';
        $harga    = $row[16] ?? '';
        $kab      = $row[18] ?? ($row[17] ?? '');

        $formattedItems[] = [
            'no'       => $no,
            'nama'     => $nama,
            'kode'     => $kode,
            'register' => $register,
            'judul'    => $judul,
            'jumlah'   => $jumlah,
            'tahun'    => $tahun,
            'asal'     => $asal,
            'harga'    => $harga ? formatRupiahParse($harga) : '',
            'kab'      => $kab,
        ];
    }

    // Parse numeric total
    $jumlahHargaNumeric = (float) str_replace(',', '', $result['jumlah_harga']);

    echo json_encode([
        'success'       => true,
        'info'          => $result['info'],
        'nama_unit'     => $namaUnit,
        'kode_lokasi'   => $kodeLokasi,
        'unit_match'    => $unitMatch,
        'unit_auto_created' => $unitAutoCreated,
        'kib_type'      => $kibType,
        'kib_label'     => $kibLabel,
        'total_barang'  => $result['total_barang'],
        'total_unit'    => $totalUnit,
        'jumlah_harga'  => $result['jumlah_harga'],
        'jumlah_harga_numeric' => $jumlahHargaNumeric,
        'jumlah_harga_formatted' => formatRupiahParse($result['jumlah_harga']),
        'items'         => $formattedItems,
        'filename'      => $file['name'],
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'Gagal memproses file: ' . $e->getMessage()]);
}
