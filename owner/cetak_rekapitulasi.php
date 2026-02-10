<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'owner') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';
include '../config/log_helper.php';

// Ambil parameter filter
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

// Log aktivitas cetak
simpan_log($koneksi, $_SESSION['id_user'], "Mencetak rekapitulasi periode: $labelPeriode");

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
           AVG(t.durasi_jam) as rata_durasi
    FROM tb_transaksi t
    JOIN tb_area_parkir a ON t.id_area = a.id_area
    WHERE t.status='keluar' AND $whereDate
    GROUP BY a.id_area, a.nama_area
    ORDER BY total_pendapatan DESC
");

// DATA DETAIL TRANSAKSI
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
<title>Rekapitulasi Parkir - <?= $labelPeriode ?></title>
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Arial', sans-serif;
    font-size: 12px;
    line-height: 1.4;
    color: #333;
    padding: 20px;
}

.header {
    text-align: center;
    margin-bottom: 30px;
    padding-bottom: 15px;
    border-bottom: 3px solid #0d6efd;
}

.header h1 {
    font-size: 24px;
    color: #0d6efd;
    margin-bottom: 5px;
}

.header h2 {
    font-size: 18px;
    color: #666;
    font-weight: normal;
    margin-bottom: 10px;
}

.header .periode {
    font-size: 14px;
    color: #333;
    font-weight: bold;
    background: #f8f9fa;
    padding: 8px 15px;
    display: inline-block;
    border-radius: 5px;
    margin-top: 10px;
}

.info-cetak {
    text-align: right;
    font-size: 10px;
    color: #666;
    margin-bottom: 20px;
}

/* STATISTIK RINGKAS */
.statistik-ringkas {
    display: flex;
    gap: 15px;
    margin-bottom: 25px;
}

.stat-box {
    flex: 1;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    padding: 15px;
    text-align: center;
    background: #f8f9fa;
}

.stat-box.primary { border-color: #0d6efd; background: #e7f1ff; }
.stat-box.success { border-color: #198754; background: #d1f4e3; }
.stat-box.warning { border-color: #fd7e14; background: #fff3cd; }
.stat-box.info { border-color: #0dcaf0; background: #cff4fc; }

.stat-box .label {
    font-size: 10px;
    color: #666;
    text-transform: uppercase;
    font-weight: bold;
    margin-bottom: 5px;
}

.stat-box .value {
    font-size: 20px;
    font-weight: bold;
    color: #333;
}

.stat-box .sub {
    font-size: 9px;
    color: #666;
    margin-top: 3px;
}

/* SECTION */
.section {
    margin-bottom: 25px;
    page-break-inside: avoid;
}

.section-title {
    background: #0d6efd;
    color: white;
    padding: 8px 12px;
    font-size: 13px;
    font-weight: bold;
    margin-bottom: 10px;
    border-radius: 5px;
}

/* TABEL */
table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 15px;
    font-size: 11px;
}

table thead {
    background: #f8f9fa;
    font-weight: bold;
}

table th,
table td {
    border: 1px solid #dee2e6;
    padding: 8px 10px;
    text-align: left;
}

table th {
    background: #e9ecef;
    font-size: 10px;
    text-transform: uppercase;
    color: #495057;
}

table tbody tr:nth-child(even) {
    background: #f8f9fa;
}

table tbody tr:hover {
    background: #e7f1ff;
}

table .text-center {
    text-align: center;
}

table .text-end {
    text-align: right;
}

table .text-nowrap {
    white-space: nowrap;
}

/* FOOTER TABEL */
table tfoot {
    background: #343a40;
    color: white;
    font-weight: bold;
}

table tfoot td {
    border-color: #495057;
}

/* BADGE */
.badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: bold;
}

.badge.bg-success { background: #198754; color: white; }
.badge.bg-warning { background: #ffc107; color: #000; }
.badge.bg-info { background: #0dcaf0; color: #000; }
.badge.bg-primary { background: #0d6efd; color: white; }

/* FOOTER LAPORAN */
.footer-laporan {
    margin-top: 40px;
    padding-top: 20px;
    border-top: 2px solid #dee2e6;
}

.ttd-area {
    display: flex;
    justify-content: space-between;
    margin-top: 20px;
}

.ttd-box {
    text-align: center;
    width: 45%;
}

.ttd-box .label {
    font-size: 11px;
    margin-bottom: 60px;
}

.ttd-box .nama {
    font-weight: bold;
    border-top: 1px solid #333;
    padding-top: 5px;
    display: inline-block;
    min-width: 200px;
}

/* PRINT STYLES */
@media print {
    body {
        padding: 0;
    }
    
    .section {
        page-break-inside: avoid;
    }
    
    table {
        page-break-inside: auto;
    }
    
    table tr {
        page-break-inside: avoid;
        page-break-after: auto;
    }
}

@page {
    size: A4;
    margin: 15mm;
}
</style>
</head>

<body onload="window.print(); setTimeout(() => window.close(), 1000)">

<!-- HEADER -->
<div class="header">
    <h1>LAPORAN REKAPITULASI PARKIR</h1>
    <h2>Sistem Manajemen Parkir</h2>
    <div class="periode">
        Periode: <?= $labelPeriode ?>
    </div>
</div>

<!-- INFO CETAK -->
<div class="info-cetak">
    Dicetak oleh: <strong><?= htmlspecialchars($_SESSION['nama']) ?></strong> | 
    Tanggal Cetak: <?= date('d/m/Y H:i:s') ?> WIB
</div>

<!-- STATISTIK RINGKAS -->
<div class="statistik-ringkas">
    <div class="stat-box primary">
        <div class="label">Total Transaksi</div>
        <div class="value"><?= number_format($totalTransaksi) ?></div>
        <div class="sub">Transaksi Selesai</div>
    </div>
    
    <div class="stat-box success">
        <div class="label">Total Pendapatan</div>
        <div class="value">Rp <?= number_format($totalPendapatan, 0, ',', '.') ?></div>
        <div class="sub">Periode <?= $labelPeriode ?></div>
    </div>
    
    <div class="stat-box warning">
        <div class="label">Kendaraan Masuk</div>
        <div class="value"><?= number_format($kendaraanMasuk) ?></div>
        <div class="sub">Total Masuk</div>
    </div>
    
    <div class="stat-box info">
        <div class="label">Rata-rata Pendapatan</div>
        <div class="value">Rp <?= number_format($rataRataPendapatan, 0, ',', '.') ?></div>
        <div class="sub">Per Transaksi</div>
    </div>
</div>

<!-- REKAPITULASI PER JENIS KENDARAAN -->
<?php if (mysqli_num_rows($rekapJenis) > 0): ?>
<div class="section">
    <div class="section-title"> Rekapitulasi Per Jenis Kendaraan</div>
    <table>
        <thead>
            <tr>
                <th style="width: 5%">No</th>
                <th>Jenis Kendaraan</th>
                <th class="text-center">Jumlah Transaksi</th>
                <th class="text-end">Total Pendapatan</th>
                <th class="text-end">Rata-rata Pendapatan</th>
                <th class="text-center">Rata-rata Durasi</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            $totalJenis = 0;
            mysqli_data_seek($rekapJenis, 0);
            while($rj = mysqli_fetch_assoc($rekapJenis)):
                $totalJenis += $rj['total_pendapatan'];
            ?>
            <tr>
                <td class="text-center"><?= $no++ ?></td>
                <td>
                    <strong><?= ucfirst($rj['jenis_kendaraan']) ?></strong>
                </td>
                <td class="text-center"><?= number_format($rj['jumlah']) ?> unit</td>
                <td class="text-end">Rp <?= number_format($rj['total_pendapatan'], 0, ',', '.') ?></td>
                <td class="text-end">Rp <?= number_format($rj['rata_rata'], 0, ',', '.') ?></td>
                <td class="text-center"><?= number_format($rj['rata_durasi'], 1) ?> jam</td>
            </tr>
            <?php endwhile; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" class="text-end"><strong>TOTAL</strong></td>
                <td class="text-end"><strong>Rp <?= number_format($totalJenis, 0, ',', '.') ?></strong></td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    </table>
</div>
<?php endif; ?>

<!-- REKAPITULASI PER AREA PARKIR -->
<?php if (mysqli_num_rows($rekapArea) > 0): ?>
<div class="section">
    <div class="section-title"> Rekapitulasi Per Area Parkir</div>
    <table>
        <thead>
            <tr>
                <th style="width: 5%">No</th>
                <th>Nama Area</th>
                <th class="text-center">Jumlah Transaksi</th>
                <th class="text-end">Total Pendapatan</th>
                <th class="text-center">Rata-rata Durasi</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            $totalArea = 0;
            mysqli_data_seek($rekapArea, 0);
            while($ra = mysqli_fetch_assoc($rekapArea)):
                $totalArea += $ra['total_pendapatan'];
            ?>
            <tr>
                <td class="text-center"><?= $no++ ?></td>
                <td><strong><?= htmlspecialchars($ra['nama_area']) ?></strong></td>
                <td class="text-center"><?= number_format($ra['jumlah']) ?> transaksi</td>
                <td class="text-end">Rp <?= number_format($ra['total_pendapatan'], 0, ',', '.') ?></td>
                <td class="text-center"><?= number_format($ra['rata_durasi'], 1) ?> jam</td>
            </tr>
            <?php endwhile; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" class="text-end"><strong>TOTAL</strong></td>
                <td class="text-end"><strong>Rp <?= number_format($totalArea, 0, ',', '.') ?></strong></td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</div>
<?php endif; ?>

<!-- DETAIL TRANSAKSI -->
<?php if (mysqli_num_rows($rekapTotal) > 0): ?>
<div class="section">
    <div class="section-title"> Detail Transaksi</div>
    <table>
        <thead>
            <tr>
                <th style="width: 4%">No</th>
                <th>Plat Nomor</th>
                <th>Jenis</th>
                <th>Pemilik</th>
                <th>Area</th>
                <th class="text-nowrap">Waktu Masuk</th>
                <th class="text-nowrap">Waktu Keluar</th>
                <th class="text-center">Durasi</th>
                <th class="text-end">Biaya</th>
                <th>Petugas</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            $grandTotal = 0;
            mysqli_data_seek($rekapTotal, 0);
            while($rt = mysqli_fetch_assoc($rekapTotal)):
                $grandTotal += $rt['biaya_total'];
            ?>
            <tr>
                <td class="text-center"><?= $no++ ?></td>
                <td class="text-nowrap"><strong><?= htmlspecialchars($rt['plat_nomor']) ?></strong></td>
                <td><?= ucfirst($rt['jenis_kendaraan']) ?></td>
                <td><?= htmlspecialchars($rt['pemilik']) ?></td>
                <td><?= htmlspecialchars($rt['nama_area']) ?></td>
                <td class="text-nowrap"><?= date('d/m/Y H:i', strtotime($rt['waktu_masuk'])) ?></td>
                <td class="text-nowrap"><?= date('d/m/Y H:i', strtotime($rt['waktu_keluar'])) ?></td>
                <td class="text-center"><?= $rt['durasi_jam'] ?> jam</td>
                <td class="text-end">Rp <?= number_format($rt['biaya_total'], 0, ',', '.') ?></td>
                <td><?= htmlspecialchars($rt['petugas']) ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="8" class="text-end"><strong>GRAND TOTAL</strong></td>
                <td class="text-end"><strong>Rp <?= number_format($grandTotal, 0, ',', '.') ?></strong></td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</div>
<?php endif; ?>

<!-- FOOTER LAPORAN -->
<div class="footer-laporan">
    <div style="text-align: center; margin-bottom: 10px; font-size: 11px; color: #666;">
        <em>--- Akhir Laporan ---</em>
    </div>
    
    <div class="ttd-area">
        <div class="ttd-box">
            <div class="label">Mengetahui,<br>Pemilik</div>
            <div class="nama"><?= htmlspecialchars($_SESSION['nama']) ?></div>
        </div>
        
        <div class="ttd-box">
            <div class="label"><br><?= date('d F Y') ?></div>
            <div class="nama">Petugas Parkir</div>
        </div>
    </div>
</div>

</body>
</html>