<?php
/**
 * KIB Excel Reader - Parser untuk file Excel Kartu Inventaris Barang
 * File Excel berformat HTML yang disimpan dengan ekstensi .xls
 */

$result = null;
$error  = null;

// ─────────────────────────────────────────────────
//  FUNGSI PARSER
// ─────────────────────────────────────────────────

/**
 * Parse file XLS (yang sebenarnya berisi HTML) dan ekstrak data KIB.
 *
 * @param  string $filepath  Path absolut ke file .xls
 * @return array             Array berisi info sekolah, data barang, dan total
 */
function parseKibExcel(string $filepath): array
{
    $html = file_get_contents($filepath);
    if ($html === false) {
        throw new RuntimeException("Tidak bisa membaca file.");
    }

    // Deteksi encoding lalu konversi ke UTF-8
    $encoding = mb_detect_encoding($html, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
    if ($encoding && $encoding !== 'UTF-8') {
        $html = mb_convert_encoding($html, 'UTF-8', $encoding);
    }

    // Sembunyikan warning DOM dari HTML yang tidak sempurna
    $dom = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();

    $xpath  = new DOMXPath($dom);
    $tables = $xpath->query('//table');

    if ($tables->length < 2) {
        throw new RuntimeException("Struktur tabel tidak dikenali.");
    }

    // ── Tabel 1: Info sekolah ─────────────────────────────────
    $infoTable = $tables->item(0);
    $info = extractInfoSekolah($xpath, $infoTable);

    // ── Tabel 2: Data barang ──────────────────────────────────
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

/**
 * Ekstrak informasi sekolah dari tabel pertama.
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
            if ($key !== '') {
                $info[$key] = $value;
            }
        }
    }
    return $info;
}

/**
 * Ekstrak baris data, header, total barang, dan jumlah harga dari tabel kedua.
 */
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

    // Deteksi baris header (mengandung kata "No" / "Nama Barang")
    $dataStartRow = 0;
    foreach ($matrix as $idx => $row) {
        if (!empty($row[0]) && in_array(strtolower($row[0]), ['no', '1'])) {
            if (strtolower($row[0]) === 'no') {
                $header       = $row;
                $dataStartRow = $idx + 1;
            } elseif ($row[0] === '1' && isset($matrix[$idx - 1])) {
                // Baris penomor kolom (1,2,3,...), data mulai sesudahnya
                $dataStartRow = $idx + 1;
            }
        }
    }

    // Ambil baris data (bukan header, bukan jumlah)
    for ($i = $dataStartRow; $i < count($matrix); $i++) {
        $row = $matrix[$i];
        if (empty($row) || (count($row) === 1 && trim($row[0]) === '')) {
            continue;
        }

        // Deteksi baris JUMLAH
        if (isset($row[0]) && strtolower(trim($row[0])) === 'jumlah') {
            // Cari nilai harga total (kolom pertama yang ada angka/koma)
            foreach ($row as $cell) {
                $clean = str_replace([',', '.'], '', $cell);
                if (is_numeric($clean) && strlen($clean) > 3) {
                    $jumlahHarga = $cell;
                    break;
                }
            }
            continue;
        }

        // Hanya ambil baris yang diawali angka (nomor urut)
        if (isset($row[0]) && is_numeric($row[0])) {
            $items[] = $row;
            $totalBarang++;
        }
    }

    return [$header, $items, $jumlahHarga, $totalBarang];
}

/**
 * Format angka rupiah ke string yang lebih terbaca.
 */
function formatRupiah(string $raw): string
{
    // Hilangkan karakter non-numerik kecuali titik dan koma
    $raw = trim($raw);
    // Format: "1,853,926,780.00" → parse sebagai float
    $numeric = (float) str_replace(',', '', $raw);
    if ($numeric === 0.0) return $raw; // kembalikan asli jika gagal
    return 'Rp ' . number_format($numeric, 0, ',', '.');
}

// ─────────────────────────────────────────────────
//  PROSES UPLOAD
// ─────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Upload gagal. Kode error: ' . $file['error'];
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['xls', 'xlsx'])) {
            $error = 'Hanya file .xls atau .xlsx yang diperbolehkan.';
        } elseif ($file['size'] > 10 * 1024 * 1024) {
            $error = 'Ukuran file melebihi batas 10 MB.';
        } else {
            try {
                $result           = parseKibExcel($file['tmp_name']);
                $result['filename'] = htmlspecialchars($file['name']);
            } catch (Throwable $e) {
                $error = 'Gagal memproses file: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>KIB Excel Reader — Inventaris Aset</title>
<style>
  /* ── Reset & Base ──────────────────────────────── */
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
    background: #f0f4f8;
    color: #1e293b;
    min-height: 100vh;
  }

  /* ── Header ───────────────────────────────────── */
  .app-header {
    background: linear-gradient(135deg, #1e3a5f 0%, #2563eb 100%);
    color: #fff;
    padding: 28px 32px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 4px 20px rgba(37,99,235,.35);
  }
  .app-header .icon { font-size: 2.2rem; }
  .app-header h1 { font-size: 1.5rem; font-weight: 700; letter-spacing: -.3px; }
  .app-header p  { font-size: .875rem; opacity: .85; margin-top: 2px; }

  /* ── Main Layout ──────────────────────────────── */
  .container { max-width: 1100px; margin: 0 auto; padding: 32px 20px; }

  /* ── Upload Card ──────────────────────────────── */
  .upload-card {
    background: #fff;
    border-radius: 16px;
    padding: 36px;
    box-shadow: 0 2px 12px rgba(0,0,0,.08);
    margin-bottom: 28px;
  }
  .upload-card h2 {
    font-size: 1.1rem; font-weight: 600;
    color: #1e3a5f; margin-bottom: 20px;
    display: flex; align-items: center; gap: 8px;
  }
  .upload-zone {
    border: 2.5px dashed #93c5fd;
    border-radius: 12px;
    padding: 40px 20px;
    text-align: center;
    background: #eff6ff;
    transition: all .2s;
    cursor: pointer;
  }
  .upload-zone:hover { border-color: #2563eb; background: #dbeafe; }
  .upload-zone .upload-icon { font-size: 3rem; margin-bottom: 10px; }
  .upload-zone p { color: #475569; font-size: .95rem; }
  .upload-zone small { color: #94a3b8; font-size: .8rem; }
  #excel_file { display: none; }

  .btn-primary {
    display: inline-flex; align-items: center; gap: 8px;
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    color: #fff; border: none; border-radius: 10px;
    padding: 12px 28px; font-size: .95rem; font-weight: 600;
    cursor: pointer; margin-top: 20px;
    transition: transform .15s, box-shadow .15s;
    box-shadow: 0 4px 12px rgba(37,99,235,.4);
  }
  .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(37,99,235,.45); }
  .btn-primary:active { transform: translateY(0); }

  #file-label {
    display: block; margin-top: 10px;
    font-size: .85rem; color: #2563eb; font-weight: 500;
  }

  /* ── Alert ────────────────────────────────────── */
  .alert {
    padding: 14px 18px; border-radius: 10px;
    margin-bottom: 20px; font-size: .9rem;
    display: flex; align-items: flex-start; gap: 10px;
  }
  .alert-error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }

  /* ── Result Area ──────────────────────────────── */
  .result-header {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 20px; flex-wrap: wrap; gap: 12px;
  }
  .result-header h2 { font-size: 1.15rem; font-weight: 700; color: #1e3a5f; }
  .badge-file {
    background: #eff6ff; color: #1d4ed8;
    border: 1px solid #bfdbfe; border-radius: 20px;
    padding: 4px 14px; font-size: .8rem; font-weight: 600;
  }

  /* ── Info Grid ────────────────────────────────── */
  .info-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(240px,1fr));
    gap: 14px; margin-bottom: 24px;
  }
  .info-item {
    background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px;
    padding: 14px 16px;
  }
  .info-item .label { font-size: .75rem; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; }
  .info-item .value { font-size: .95rem; color: #1e293b; font-weight: 600; margin-top: 4px; }

  /* ── Stats Cards ──────────────────────────────── */
  .stats-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(210px,1fr));
    gap: 16px; margin-bottom: 28px;
  }
  .stat-card {
    border-radius: 14px; padding: 22px 20px;
    display: flex; align-items: center; gap: 16px;
    box-shadow: 0 2px 10px rgba(0,0,0,.07);
  }
  .stat-card.blue   { background: linear-gradient(135deg,#2563eb,#3b82f6); color:#fff; }
  .stat-card.green  { background: linear-gradient(135deg,#059669,#10b981); color:#fff; }
  .stat-card.orange { background: linear-gradient(135deg,#d97706,#f59e0b); color:#fff; }
  .stat-card .stat-icon { font-size: 2.2rem; }
  .stat-card .stat-label { font-size: .8rem; opacity: .88; font-weight: 500; }
  .stat-card .stat-value { font-size: 1.35rem; font-weight: 800; margin-top: 2px; line-height: 1.2; }

  /* ── Data Table ───────────────────────────────── */
  .table-wrapper {
    background: #fff; border-radius: 14px;
    box-shadow: 0 2px 12px rgba(0,0,0,.07);
    overflow: hidden;
  }
  .table-toolbar {
    padding: 16px 20px; border-bottom: 1px solid #e2e8f0;
    display: flex; align-items: center; justify-content: space-between;
    gap: 12px; flex-wrap: wrap;
  }
  .table-toolbar h3 { font-size: 1rem; font-weight: 700; color: #1e3a5f; }
  .search-box {
    padding: 8px 14px; border: 1.5px solid #cbd5e1; border-radius: 8px;
    font-size: .875rem; outline: none; width: 220px;
    transition: border-color .2s;
  }
  .search-box:focus { border-color: #2563eb; }
  .table-scroll { overflow-x: auto; }
  table { width: 100%; border-collapse: collapse; font-size: .85rem; }
  thead th {
    background: #1e3a5f; color: #fff; text-align: left;
    padding: 12px 14px; white-space: nowrap; font-weight: 600;
    position: sticky; top: 0;
  }
  tbody tr { border-bottom: 1px solid #f1f5f9; transition: background .15s; }
  tbody tr:hover { background: #f0f7ff; }
  tbody td { padding: 10px 14px; vertical-align: top; }
  tbody td:first-child { font-weight: 600; color: #475569; text-align: center; width: 44px; }
  .td-nama { font-weight: 600; color: #1e293b; min-width: 180px; }
  .td-harga { font-weight: 700; color: #059669; white-space: nowrap; }
  .td-jumlah { text-align: center; font-weight: 700; color: #2563eb; }
  .badge-tahun {
    display: inline-block; background: #eff6ff;
    color: #1d4ed8; border-radius: 6px; padding: 1px 8px;
    font-size: .78rem; font-weight: 600;
  }
  .badge-asal {
    display: inline-block; background: #f0fdf4;
    color: #166534; border: 1px solid #bbf7d0;
    border-radius: 6px; padding: 1px 8px;
    font-size: .78rem; font-weight: 600;
  }
  .table-footer {
    padding: 14px 20px; border-top: 2px solid #e2e8f0;
    display: flex; justify-content: space-between; align-items: center;
    flex-wrap: wrap; gap: 8px;
    background: #f8fafc;
  }
  .table-footer .total-label { font-size: .85rem; color: #64748b; font-weight: 600; }
  .table-footer .total-value { font-size: 1rem; font-weight: 800; color: #059669; }
  .no-data { padding: 40px; text-align: center; color: #94a3b8; }

  /* ── Upload area click ────────────────────────── */
  .upload-zone label { cursor: pointer; display: block; }

  @media (max-width: 600px) {
    .app-header { padding: 20px 16px; }
    .upload-card { padding: 24px 16px; }
    .container { padding: 20px 12px; }
  }
</style>
</head>
<body>

<!-- Header -->
<header class="app-header">
  <div class="icon">📊</div>
  <div>
    <h1>KIB Excel Reader</h1>
    <p>Parser Kartu Inventaris Barang — Aset Tetap Lainnya / Tanah</p>
  </div>
</header>

<div class="container">

  <!-- Upload Card -->
  <div class="upload-card">
    <h2>📁 Upload File Excel KIB</h2>
    <?php if ($error): ?>
      <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST" enctype="multipart/form-data" id="uploadForm">
      <div class="upload-zone" onclick="document.getElementById('excel_file').click()">
        <div class="upload-icon">📂</div>
        <p><strong>Klik untuk memilih file</strong> atau seret & lepas ke sini</p>
        <small>Format: .xls atau .xlsx · Maks 10 MB</small>
        <span id="file-label">Belum ada file dipilih</span>
      </div>
      <input type="file" name="excel_file" id="excel_file" accept=".xls,.xlsx"
             onchange="document.getElementById('file-label').textContent = this.files[0]?.name ?? 'Belum ada file dipilih'">
      <br>
      <button type="submit" class="btn-primary">
        ⚡ Proses &amp; Tampilkan Data
      </button>
    </form>
  </div>

  <!-- Hasil -->
  <?php if ($result): ?>
  <div>
    <div class="result-header">
      <h2>📋 Hasil Parsing</h2>
      <span class="badge-file">📄 <?= $result['filename'] ?></span>
    </div>

    <!-- Info Sekolah -->
    <?php if (!empty($result['info'])): ?>
    <div class="info-grid">
      <?php foreach ($result['info'] as $key => $val): ?>
      <div class="info-item">
        <div class="label"><?= htmlspecialchars(trim($key)) ?></div>
        <div class="value"><?= htmlspecialchars(trim($val)) ?: '—' ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Stat Cards -->
    <div class="stats-grid">
      <div class="stat-card blue">
        <div class="stat-icon">📦</div>
        <div>
          <div class="stat-label">Total Jenis Barang</div>
          <div class="stat-value"><?= number_format($result['total_barang']) ?></div>
        </div>
      </div>
      <div class="stat-card green">
        <div class="stat-icon">💰</div>
        <div>
          <div class="stat-label">Total Nilai Aset</div>
          <div class="stat-value" style="font-size:1.05rem">
            <?= formatRupiah($result['jumlah_harga']) ?>
          </div>
        </div>
      </div>
      <?php
        // Hitung jumlah keseluruhan unit/eksemplar (kolom "Jumlah")
        $totalUnit = 0;
        foreach ($result['items'] as $row) {
            // Kolom 13 (index 13) = Jumlah, tapi posisi bisa beda — cari kolom yang berisi angka kecil
            // Gunakan kolom ke-13 (0-based index 13) sesuai header
            $val = isset($row[13]) ? $row[13] : '';
            $clean = str_replace([',', '.'], '', $val);
            if (is_numeric($clean) && (int)$clean < 100000) {
                $totalUnit += (int)$clean;
            }
        }
      ?>
      <div class="stat-card orange">
        <div class="stat-icon">🔢</div>
        <div>
          <div class="stat-label">Total Unit / Eksemplar</div>
          <div class="stat-value"><?= number_format($totalUnit) ?></div>
        </div>
      </div>
    </div>

    <!-- Tabel Data -->
    <div class="table-wrapper">
      <div class="table-toolbar">
        <h3>📚 Daftar Barang Inventaris</h3>
        <input type="text" class="search-box" id="searchInput"
               placeholder="🔍 Cari nama barang..." oninput="filterTable()">
      </div>
      <div class="table-scroll">
        <table id="kibTable">
          <thead>
            <tr>
              <th>No</th>
              <th>Nama Barang</th>
              <th>Kode Barang</th>
              <th>Register</th>
              <th>Judul / Keterangan</th>
              <th>Jumlah</th>
              <th>Tahun</th>
              <th>Asal Usul</th>
              <th>Harga</th>
              <th>Kab/Kota</th>
            </tr>
          </thead>
          <tbody id="kibBody">
            <?php if (empty($result['items'])): ?>
            <tr><td colspan="10" class="no-data">Tidak ada data barang ditemukan.</td></tr>
            <?php else: ?>
            <?php foreach ($result['items'] as $row): ?>
            <?php
              // Mapping kolom berdasarkan struktur:
              // 0=No, 1=NamaBarang, 2=KodeBarang, 3=Register,
              // 4=Judul, 5-12=detail, 13=Jumlah, 14=TahunPembelian, 15=AsalUsul, 16=Harga, 17=Ruangan, 18=KabKota
              $no       = $row[0]  ?? '';
              $nama     = $row[1]  ?? '';
              $kode     = $row[2]  ?? '';
              $register = $row[3]  ?? '';
              $judul    = $row[4]  ?? '';
              $jumlah   = $row[13] ?? '';
              $tahun    = $row[14] ?? '';
              $asal     = $row[15] ?? '';
              $harga    = $row[16] ?? '';
              $kab      = $row[18] ?? ($row[17] ?? '');

              // Bersihkan awalan quote (') dari kode barang dan register
              $kode     = ltrim($kode, "'");
              $register = ltrim($register, "'");
            ?>
            <tr>
              <td><?= htmlspecialchars($no) ?></td>
              <td class="td-nama"><?= htmlspecialchars($nama) ?></td>
              <td><?= htmlspecialchars($kode) ?></td>
              <td><?= htmlspecialchars($register) ?></td>
              <td><?= htmlspecialchars($judul) ?></td>
              <td class="td-jumlah"><?= htmlspecialchars($jumlah) ?></td>
              <td><?php if ($tahun): ?><span class="badge-tahun"><?= htmlspecialchars($tahun) ?></span><?php endif; ?></td>
              <td><?php if ($asal): ?><span class="badge-asal"><?= htmlspecialchars($asal) ?></span><?php endif; ?></td>
              <td class="td-harga"><?= $harga ? formatRupiah($harga) : '' ?></td>
              <td><?= htmlspecialchars($kab) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <div class="table-footer">
        <span class="total-label">Total <?= $result['total_barang'] ?> jenis barang</span>
        <span class="total-value">JUMLAH: <?= formatRupiah($result['jumlah_harga']) ?></span>
      </div>
    </div>

  </div>
  <?php endif; ?>

</div>

<script>
// Pencarian tabel real-time
function filterTable() {
    const q     = document.getElementById('searchInput').value.toLowerCase();
    const rows  = document.querySelectorAll('#kibBody tr');
    rows.forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}

// Drag & drop upload zone
const zone = document.querySelector('.upload-zone');
zone.addEventListener('dragover', e => { e.preventDefault(); zone.style.borderColor = '#2563eb'; });
zone.addEventListener('dragleave', () => { zone.style.borderColor = '#93c5fd'; });
zone.addEventListener('drop', e => {
    e.preventDefault();
    zone.style.borderColor = '#93c5fd';
    const file = e.dataTransfer.files[0];
    if (file) {
        const input = document.getElementById('excel_file');
        const dt    = new DataTransfer();
        dt.items.add(file);
        input.files = dt.files;
        document.getElementById('file-label').textContent = file.name;
    }
});
</script>
</body>
</html>
