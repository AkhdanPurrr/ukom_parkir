<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'owner') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';
include '../config/log_helper.php';

date_default_timezone_set('Asia/Jakarta');

// Log aktivitas akses dashboard
if (!isset($_SESSION['owner_dashboard_logged'])) {
    simpan_log($koneksi, $_SESSION['id_user'], 'Mengakses Dashboard Owner');
    $_SESSION['owner_dashboard_logged'] = true;
}

// Log saat memilih periode
if (isset($_GET['periode'])) {
    $periodeLog = $_GET['periode'];
    if ($periodeLog == 'custom' && isset($_GET['dari']) && isset($_GET['sampai'])) {
        $logMessage = "Melihat rekapitulasi periode custom: {$_GET['dari']} s/d {$_GET['sampai']}";
    } else {
        $periodeName = [
            'hari_ini' => 'Hari Ini',
            'kemarin' => 'Kemarin',
            'minggu_ini' => 'Minggu Ini',
            'bulan_ini' => 'Bulan Ini',
            'tahun_ini' => 'Tahun Ini'
        ];
        $logMessage = "Melihat rekapitulasi periode: " . ($periodeName[$periodeLog] ?? $periodeLog);
    }
    
    if (!isset($_SESSION['periode_logged']) || $_SESSION['periode_logged'] != $periodeLog . serialize($_GET)) {
        simpan_log($koneksi, $_SESSION['id_user'], $logMessage);
        $_SESSION['periode_logged'] = $periodeLog . serialize($_GET);
    }
}

// Log saat scroll ke rekapitulasi total (via AJAX atau flag)
if (isset($_GET['view_rekap_total']) && !isset($_SESSION['rekap_total_logged'])) {
    simpan_log($koneksi, $_SESSION['id_user'], 'Melihat detail rekapitulasi total transaksi');
    $_SESSION['rekap_total_logged'] = true;
}

// Filter periode
$periode = isset($_GET['periode']) ? $_GET['periode'] : 'hari_ini';
$customDari = isset($_GET['dari']) ? $_GET['dari'] : date('Y-m-d');
$customSampai = isset($_GET['sampai']) ? $_GET['sampai'] : date('Y-m-d');

// Set filter berdasarkan periode
switch($periode) {
    case 'hari_ini':
        $whereDate = "DATE(waktu_keluar) = CURDATE()";
        $labelPeriode = "Hari Ini";
        break;
    case 'kemarin':
        $whereDate = "DATE(waktu_keluar) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
        $labelPeriode = "Kemarin";
        break;
    case 'minggu_ini':
        $whereDate = "YEARWEEK(waktu_keluar, 1) = YEARWEEK(CURDATE(), 1)";
        $labelPeriode = "Minggu Ini";
        break;
    case 'bulan_ini':
        $whereDate = "MONTH(waktu_keluar) = MONTH(CURDATE()) AND YEAR(waktu_keluar) = YEAR(CURDATE())";
        $labelPeriode = "Bulan Ini";
        break;
    case 'tahun_ini':
        $whereDate = "YEAR(waktu_keluar) = YEAR(CURDATE())";
        $labelPeriode = "Tahun Ini";
        break;
    case 'custom':
        $whereDate = "DATE(waktu_keluar) BETWEEN '$customDari' AND '$customSampai'";
        $labelPeriode = date('d/m/Y', strtotime($customDari)) . " - " . date('d/m/Y', strtotime($customSampai));
        break;
    default:
        $whereDate = "DATE(waktu_keluar) = CURDATE()";
        $labelPeriode = "Hari Ini";
}

// STATISTIK UTAMA
$totalTransaksi = mysqli_fetch_assoc(mysqli_query($koneksi, "
    SELECT COUNT(*) as total FROM tb_transaksi 
    WHERE status='keluar' AND $whereDate
"))['total'];

$totalPendapatan = mysqli_fetch_assoc(mysqli_query($koneksi, "
    SELECT SUM(biaya_total) as total FROM tb_transaksi 
    WHERE status='keluar' AND $whereDate
"))['total'] ?? 0;

$kendaraanMasuk = mysqli_fetch_assoc(mysqli_query($koneksi, "
    SELECT COUNT(*) as total FROM tb_transaksi 
    WHERE $whereDate
"))['total'];

$kendaraanParkir = mysqli_fetch_assoc(mysqli_query($koneksi, "
    SELECT COUNT(*) as total FROM tb_transaksi 
    WHERE status='masuk'
"))['total'];

$rataRataPendapatan = $totalTransaksi > 0 ? $totalPendapatan / $totalTransaksi : 0;

// REKAPITULASI PER JENIS KENDARAAN
$rekapJenis = mysqli_query($koneksi, "
    SELECT k.jenis_kendaraan, 
           COUNT(*) as jumlah,
           SUM(t.biaya_total) as total_pendapatan,
           AVG(t.biaya_total) as rata_rata,
           AVG(t.durasi_jam) as rata_durasi
    FROM tb_transaksi t
    JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
    WHERE t.status='keluar' AND $whereDate
    GROUP BY k.jenis_kendaraan
    ORDER BY total_pendapatan DESC
");

// REKAPITULASI PER AREA PARKIR
$rekapArea = mysqli_query($koneksi, "
    SELECT a.nama_area,
           COUNT(*) as jumlah,
           SUM(t.biaya_total) as total_pendapatan,
           AVG(t.durasi_jam) as rata_durasi,
           a.kapasitas,
           a.terisi
    FROM tb_transaksi t
    JOIN tb_area_parkir a ON t.id_area = a.id_area
    WHERE t.status='keluar' AND $whereDate
    GROUP BY a.id_area, a.nama_area, a.kapasitas, a.terisi
    ORDER BY total_pendapatan DESC
");

// GRAFIK PENDAPATAN PER HARI (7 hari terakhir)
$grafikHarian = mysqli_query($koneksi, "
    SELECT DATE(waktu_keluar) as tanggal,
           COUNT(*) as jumlah,
           SUM(biaya_total) as pendapatan
    FROM tb_transaksi
    WHERE status='keluar' 
    AND DATE(waktu_keluar) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(waktu_keluar)
    ORDER BY tanggal ASC
");

// GRAFIK PENDAPATAN PER JAM (untuk hari ini)
$grafikPerJam = mysqli_query($koneksi, "
    SELECT HOUR(waktu_keluar) as jam,
           COUNT(*) as jumlah,
           SUM(biaya_total) as pendapatan
    FROM tb_transaksi
    WHERE status='keluar' 
    AND DATE(waktu_keluar) = CURDATE()
    GROUP BY HOUR(waktu_keluar)
    ORDER BY jam ASC
");

// DATA UNTUK REKAPITULASI TOTAL (semua transaksi)
$rekapTotal = mysqli_query($koneksi, "
    SELECT t.*, k.plat_nomor, k.jenis_kendaraan, k.warna, k.pemilik,
           a.nama_area, u.nama_lengkap as petugas
    FROM tb_transaksi t
    JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
    JOIN tb_area_parkir a ON t.id_area = a.id_area
    JOIN tb_user u ON t.id_user = u.id_user
    WHERE t.status='keluar' AND $whereDate
    ORDER BY t.waktu_keluar DESC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Dashboard Owner</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<style>
.stat-card {
    border-left: 4px solid;
    transition: transform 0.2s, box-shadow 0.2s;
}
.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 16px rgba(0,0,0,0.1);
}
.stat-card.blue { border-left-color: #0d6efd; }
.stat-card.green { border-left-color: #198754; }
.stat-card.purple { border-left-color: #6f42c1; }
.stat-card.orange { border-left-color: #fd7e14; }
.stat-card.red { border-left-color: #dc3545; }
</style>
</head>

<body class="bg-light">
<div class="container-fluid my-4">

<!-- HEADER -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="mb-0">
            <i class="bi bi-bar-chart-line-fill text-primary"></i> 
            Dashboard Owner
        </h3>
        <small class="text-muted">
            Selamat datang, <strong><?= htmlspecialchars($_SESSION['nama']); ?></strong>
        </small>
    </div>
    <a href="/parkir/auth/logout.php"
       class="btn btn-outline-danger btn-sm"
       onclick="return confirm('Yakin ingin logout?')">
        <i class="bi bi-box-arrow-right"></i> Logout
    </a>
</div>

<!-- FILTER PERIODE -->
<div class="card mb-4 shadow-sm">
<div class="card-header bg-primary text-white fw-semibold d-flex justify-content-between align-items-center">
    <span><i class="bi bi-calendar-range"></i> Filter Periode</span>
    <?php 
    // Build URL untuk cetak dengan parameter yang sama
    $cetakUrl = "cetak_rekapitulasi.php?periode=" . urlencode($periode);
    if ($periode === 'custom') {
        $cetakUrl .= "&dari=" . urlencode($customDari) . "&sampai=" . urlencode($customSampai);
    }
    ?>
    <a href="<?= $cetakUrl ?>" target="_blank" class="btn btn-light btn-sm">
        <i class="bi bi-printer-fill"></i> Cetak Rekapitulasi
    </a>
</div>
<div class="card-body">
<form method="get" class="row g-3">
    <div class="col-md-3">
        <label class="form-label">Periode</label>
        <select name="periode" class="form-select" id="periodeSelect" onchange="toggleCustomDate()">
            <option value="hari_ini" <?= $periode=='hari_ini'?'selected':'' ?>>Hari Ini</option>
            <option value="kemarin" <?= $periode=='kemarin'?'selected':'' ?>>Kemarin</option>
            <option value="minggu_ini" <?= $periode=='minggu_ini'?'selected':'' ?>>Minggu Ini</option>
            <option value="bulan_ini" <?= $periode=='bulan_ini'?'selected':'' ?>>Bulan Ini</option>
            <option value="tahun_ini" <?= $periode=='tahun_ini'?'selected':'' ?>>Tahun Ini</option>
            <option value="custom" <?= $periode=='custom'?'selected':'' ?>>Custom</option>
        </select>
    </div>
    <div class="col-md-3" id="customDateFrom" style="display: <?= $periode=='custom'?'block':'none' ?>">
        <label class="form-label">Dari Tanggal</label>
        <input type="date" name="dari" class="form-control" value="<?= $customDari ?>">
    </div>
    <div class="col-md-3" id="customDateTo" style="display: <?= $periode=='custom'?'block':'none' ?>">
        <label class="form-label">Sampai Tanggal</label>
        <input type="date" name="sampai" class="form-control" value="<?= $customSampai ?>">
    </div>
    <div class="col-md-3 d-flex align-items-end">
        <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-search"></i> Tampilkan
        </button>
    </div>
</form>
</div>
<div class="card-footer text-muted">
    <small><i class="bi bi-info-circle"></i> Menampilkan data: <strong><?= $labelPeriode ?></strong></small>
</div>
</div>

<!-- STATISTIK UTAMA -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card stat-card green shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Total Pendapatan</h6>
                        <h2 class="mb-0 fw-bold text-success">Rp <?= number_format($totalPendapatan, 0, ',', '.') ?></h2>
                        <small class="text-muted"><?= $totalTransaksi ?> transaksi</small>
                    </div>
                    <div class="text-success" style="font-size: 3rem; opacity: 0.3;">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card stat-card blue shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Kendaraan Masuk</h6>
                        <h2 class="mb-0 fw-bold text-primary"><?= $kendaraanMasuk ?></h2>
                        <small class="text-muted">Total kendaraan</small>
                    </div>
                    <div class="text-primary" style="font-size: 3rem; opacity: 0.3;">
                        <i class="bi bi-car-front-fill"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card stat-card purple shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Rata-rata/Transaksi</h6>
                        <h2 class="mb-0 fw-bold" style="color: #6f42c1;">Rp <?= number_format($rataRataPendapatan, 0, ',', '.') ?></h2>
                        <small class="text-muted">Per kendaraan</small>
                    </div>
                    <div style="font-size: 3rem; opacity: 0.3; color: #6f42c1;">
                        <i class="bi bi-calculator"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card stat-card orange shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Sedang Parkir</h6>
                        <h2 class="mb-0 fw-bold text-warning"><?= $kendaraanParkir ?></h2>
                        <small class="text-muted">Kendaraan aktif</small>
                    </div>
                    <div class="text-warning" style="font-size: 3rem; opacity: 0.3;">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- REKAPITULASI -->
<div class="row g-4 mb-4">
    <!-- REKAP PER JENIS KENDARAAN -->
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header bg-success text-white fw-semibold">
                <i class="bi bi-truck"></i> Rekapitulasi Per Jenis Kendaraan
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Jenis</th>
                                <th class="text-center">Jumlah</th>
                                <th class="text-end">Total</th>
                                <th class="text-center">Rata Durasi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($rekapJenis) > 0): 
                                $totalSemuaJenis = 0;
                                while($rj = mysqli_fetch_assoc($rekapJenis)):
                                $totalSemuaJenis += $rj['total_pendapatan'];
                            ?>
                            <tr>
                                <td><strong><?= ucfirst($rj['jenis_kendaraan']) ?></strong></td>
                                <td class="text-center">
                                    <span class="badge bg-primary"><?= $rj['jumlah'] ?></span>
                                </td>
                                <td class="text-end">
                                    <strong class="text-success">Rp <?= number_format($rj['total_pendapatan'], 0, ',', '.') ?></strong>
                                </td>
                                <td class="text-center">
                                    <small><?= number_format($rj['rata_durasi'], 1) ?> jam</small>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <tr class="table-secondary fw-bold">
                                <td colspan="2">TOTAL</td>
                                <td class="text-end text-success">Rp <?= number_format($totalSemuaJenis, 0, ',', '.') ?></td>
                                <td></td>
                            </tr>
                            <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-3">
                                    Tidak ada data
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- REKAP PER AREA -->
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header bg-info text-white fw-semibold">
                <i class="bi bi-geo-alt"></i> Rekapitulasi Per Area Parkir
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Area</th>
                                <th class="text-center">Transaksi</th>
                                <th class="text-end">Total</th>
                                <th class="text-center">Terisi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($rekapArea) > 0):
                                $totalSemuaArea = 0;
                                while($ra = mysqli_fetch_assoc($rekapArea)):
                                $totalSemuaArea += $ra['total_pendapatan'];
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($ra['nama_area']) ?></strong></td>
                                <td class="text-center">
                                    <span class="badge bg-primary"><?= $ra['jumlah'] ?></span>
                                </td>
                                <td class="text-end">
                                    <strong class="text-success">Rp <?= number_format($ra['total_pendapatan'], 0, ',', '.') ?></strong>
                                </td>
                                <td class="text-center">
                                    <small><?= $ra['terisi'] ?>/<?= $ra['kapasitas'] ?></small>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <tr class="table-secondary fw-bold">
                                <td colspan="2">TOTAL</td>
                                <td class="text-end text-success">Rp <?= number_format($totalSemuaArea, 0, ',', '.') ?></td>
                                <td></td>
                            </tr>
                            <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-3">
                                    Tidak ada data
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- GRAFIK -->
<div class="row g-4 mb-4">
    <!-- GRAFIK 7 HARI -->
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header bg-warning text-white fw-semibold">
                <i class="bi bi-graph-up"></i> Tren Pendapatan 7 Hari Terakhir
            </div>
            <div class="card-body">
                <canvas id="chartPendapatan"></canvas>
            </div>
        </div>
    </div>

    <!-- GRAFIK PER JAM HARI INI -->
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header bg-danger text-white fw-semibold">
                <i class="bi bi-clock-history"></i> Pendapatan Per Jam Hari Ini
            </div>
            <div class="card-body">
                <canvas id="chartPerJam"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- INFO PANEL -->
<div class="card border-info">
    <div class="card-body">
        <h6 class="card-title text-info">
            <i class="bi bi-info-circle-fill"></i> Informasi Dashboard
        </h6>
        <div class="row">
            <div class="col-md-6">
                <ul class="mb-0 small">
                    <li>Dashboard menampilkan rekapitulasi transaksi berdasarkan periode yang dipilih</li>
                    <li>Data pendapatan hanya menghitung transaksi dengan status "keluar"</li>
                    <li>Grafik tren menampilkan pendapatan 7 hari terakhir</li>
                </ul>
            </div>
            <div class="col-md-6">
                <ul class="mb-0 small">
                    <li>Kendaraan "Sedang Parkir" menampilkan data real-time saat ini</li>
                    <li>Grafik per jam menampilkan pola pendapatan harian</li>
                    <li>Gunakan filter custom untuk periode spesifik yang Anda inginkan</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- REKAPITULASI TOTAL TRANSAKSI -->
<div class="card mt-4 shadow-sm">
    <div class="card-header bg-dark text-white fw-semibold d-flex justify-content-between align-items-center">
        <span><i class="bi bi-file-earmark-text"></i> Rekapitulasi Total Transaksi</span>
        <span class="badge bg-light text-dark"><?= mysqli_num_rows($rekapTotal) ?> transaksi</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 4%">No</th>
                        <th>Plat Nomor</th>
                        <th>Jenis</th>
                        <th>Pemilik</th>
                        <th>Area</th>
                        <th>Waktu Masuk</th>
                        <th>Waktu Keluar</th>
                        <th class="text-center">Durasi</th>
                        <th class="text-end">Biaya</th>
                        <th>Petugas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if(mysqli_num_rows($rekapTotal) > 0):
                        $no = 1;
                        $grandTotal = 0;
                        while($rt = mysqli_fetch_assoc($rekapTotal)):
                        $grandTotal += $rt['biaya_total'];
                    ?>
                    <tr>
                        <td class="text-center"><?= $no++ ?></td>
                        <td><strong><?= htmlspecialchars($rt['plat_nomor']) ?></strong></td>
                        <td><?= ucfirst($rt['jenis_kendaraan']) ?></td>
                        <td><?= htmlspecialchars($rt['pemilik']) ?></td>
                        <td>
                            <span class="badge bg-info">
                                <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($rt['nama_area']) ?>
                            </span>
                        </td>
                        <td><small><?= date('d/m/Y H:i', strtotime($rt['waktu_masuk'])) ?></small></td>
                        <td><small><?= date('d/m/Y H:i', strtotime($rt['waktu_keluar'])) ?></small></td>
                        <td class="text-center">
                            <span class="badge bg-primary"><?= $rt['durasi_jam'] ?> jam</span>
                        </td>
                        <td class="text-end">
                            <strong class="text-success">Rp <?= number_format($rt['biaya_total'], 0, ',', '.') ?></strong>
                        </td>
                        <td><small><?= htmlspecialchars($rt['petugas']) ?></small></td>
                    </tr>
                    <?php 
                        endwhile;
                    ?>
                    <tr class="table-dark fw-bold">
                        <td colspan="8" class="text-end">GRAND TOTAL</td>
                        <td class="text-end text-warning">Rp <?= number_format($grandTotal, 0, ',', '.') ?></td>
                        <td></td>
                    </tr>
                    <?php else: ?>
                    <tr>
                        <td colspan="10" class="text-center text-muted py-5">
                            <i class="bi bi-inbox display-1"></i>
                            <p class="mt-3">Tidak ada data transaksi pada periode ini</p>
                            <small>Silakan pilih periode yang berbeda</small>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if(mysqli_num_rows($rekapTotal) > 0): ?>
    <div class="card-footer text-muted">
        <div class="row">
            <div class="col-md-6">
                <small>
                    <i class="bi bi-info-circle"></i> 
                    Menampilkan semua transaksi pada periode: <strong><?= $labelPeriode ?></strong>
                </small>
            </div>
            <div class="col-md-6 text-end">
                <small>
                    <i class="bi bi-calculator"></i> 
                    Total <?= mysqli_num_rows($rekapTotal) ?> transaksi
                </small>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Toggle custom date fields
function toggleCustomDate() {
    const periode = document.getElementById('periodeSelect').value;
    const customFrom = document.getElementById('customDateFrom');
    const customTo = document.getElementById('customDateTo');
    
    if (periode === 'custom') {
        customFrom.style.display = 'block';
        customTo.style.display = 'block';
    } else {
        customFrom.style.display = 'none';
        customTo.style.display = 'none';
    }
}

// Data untuk grafik 7 hari
<?php
$labels = [];
$data = [];
mysqli_data_seek($grafikHarian, 0);
while($gh = mysqli_fetch_assoc($grafikHarian)) {
    $labels[] = date('d/m', strtotime($gh['tanggal']));
    $data[] = $gh['pendapatan'];
}
?>

const ctx = document.getElementById('chartPendapatan');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode($labels) ?>,
        datasets: [{
            label: 'Pendapatan (Rp)',
            data: <?= json_encode($data) ?>,
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            tension: 0.3,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                display: true,
                position: 'top'
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return 'Rp ' + context.parsed.y.toLocaleString('id-ID');
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return 'Rp ' + value.toLocaleString('id-ID');
                    }
                }
            }
        }
    }
});

// Data untuk grafik per jam
<?php
$labelsJam = [];
$dataJam = [];
for($i = 0; $i < 24; $i++) {
    $labelsJam[] = str_pad($i, 2, '0', STR_PAD_LEFT) . ':00';
    $dataJam[$i] = 0;
}

mysqli_data_seek($grafikPerJam, 0);
while($gj = mysqli_fetch_assoc($grafikPerJam)) {
    $dataJam[$gj['jam']] = $gj['pendapatan'];
}
?>

const ctx2 = document.getElementById('chartPerJam');
new Chart(ctx2, {
    type: 'bar',
    data: {
        labels: <?= json_encode($labelsJam) ?>,
        datasets: [{
            label: 'Pendapatan (Rp)',
            data: <?= json_encode(array_values($dataJam)) ?>,
            backgroundColor: 'rgba(220, 53, 69, 0.5)',
            borderColor: 'rgb(220, 53, 69)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                display: true,
                position: 'top'
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return 'Rp ' + context.parsed.y.toLocaleString('id-ID');
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return 'Rp ' + value.toLocaleString('id-ID');
                    }
                }
            }
        }
    }
});
</script>

</body>
</html>