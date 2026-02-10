<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'petugas') {
    header("Location: ../auth/login.php");
    exit;
}

// Set timezone
date_default_timezone_set('Asia/Jakarta');

include '../config/koneksi.php';
include '../config/log_helper.php';

// Log aktivitas akses halaman
if (!isset($_SESSION['riwayat_logged'])) {
    simpan_log($koneksi, $_SESSION['id_user'], 'Mengakses halaman Riwayat Transaksi');
    $_SESSION['riwayat_logged'] = true;
}

// FIX BUG #2: Sanitasi input GET dengan prepared statement
$where = "WHERE 1=1";
$params = [];
$types = "";

// FIX BUG #2: Sanitasi input dari GET
if (!empty($_GET['dari']) && !empty($_GET['sampai'])) {
    $dari = trim($_GET['dari']);
    $sampai = trim($_GET['sampai']);
    $where .= " AND DATE(t.waktu_masuk) BETWEEN ? AND ?";
    $params[] = $dari;
    $params[] = $sampai;
    $types .= "ss";
}

if (!empty($_GET['status'])) {
    $status = trim($_GET['status']);
    if (in_array($status, ['masuk', 'keluar'])) { // Validasi
        $where .= " AND t.status = ?";
        $params[] = $status;
        $types .= "s";
    }
}

if (!empty($_GET['plat'])) {
    $plat = trim($_GET['plat']);
    $plat = "%" . $plat . "%";
    $where .= " AND k.plat_nomor LIKE ?";
    $params[] = $plat;
    $types .= "s";
}

if (!empty($_GET['area'])) {
    $idArea = intval($_GET['area']);
    $where .= " AND t.id_area = ?";
    $params[] = $idArea;
    $types .= "i";
}

// Query data dengan prepared statement untuk statistik
$sql = "SELECT t.*, k.plat_nomor, k.jenis_kendaraan, k.warna, k.pemilik,
           a.nama_area, u.nama_lengkap as petugas
    FROM tb_transaksi t
    JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
    JOIN tb_area_parkir a ON t.id_area = a.id_area
    JOIN tb_user u ON t.id_user = u.id_user
    $where
    ORDER BY t.waktu_masuk DESC";

$stmt = $koneksi->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$riwayat = $stmt->get_result();

// FIX BUG #5: Error handling
if (!$riwayat) {
    die("Error query: " . $koneksi->error);
}

/* ===== STATISTIK ===== */
$totalTransaksi = $riwayat->num_rows;

// Hitung total masuk
$sqlMasuk = "SELECT COUNT(*) as total FROM tb_transaksi t $where AND t.status='masuk'";
$stmtMasuk = $koneksi->prepare($sqlMasuk);
if (!empty($params)) {
    $stmtMasuk->bind_param($types, ...$params);
}
$stmtMasuk->execute();
$totalMasuk = $stmtMasuk->get_result()->fetch_assoc()['total'];

// Hitung total keluar
$sqlKeluar = "SELECT COUNT(*) as total FROM tb_transaksi t $where AND t.status='keluar'";
$stmtKeluar = $koneksi->prepare($sqlKeluar);
if (!empty($params)) {
    $stmtKeluar->bind_param($types, ...$params);
}
$stmtKeluar->execute();
$totalKeluar = $stmtKeluar->get_result()->fetch_assoc()['total'];

// Hitung total pendapatan
$sqlPendapatan = "SELECT SUM(biaya_total) as total FROM tb_transaksi t $where AND t.status='keluar'";
$stmtPendapatan = $koneksi->prepare($sqlPendapatan);
if (!empty($params)) {
    $stmtPendapatan->bind_param($types, ...$params);
}
$stmtPendapatan->execute();
$totalPendapatan = $stmtPendapatan->get_result()->fetch_assoc()['total'] ?? 0;

// Data untuk filter
$areaList = mysqli_query($koneksi, "SELECT * FROM tb_area_parkir ORDER BY nama_area ASC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Riwayat Transaksi</title>
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
.stat-card.orange { border-left-color: #fd7e14; }
.stat-card.purple { border-left-color: #6f42c1; }
</style>
</head>

<body class="bg-light">
<div class="container-fluid my-4">

<!-- HEADER -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="mb-0">
            <i class="bi bi-clock-history text-primary"></i> 
            Riwayat Transaksi
        </h3>
        <small class="text-muted">Histori transaksi parkir</small>
    </div>
    <div class="d-flex gap-2">
        <a href="petugas_dashboard.php" class="btn btn-secondary btn-sm">
            <i class="bi bi-arrow-left-circle"></i> Dashboard
        </a>
        <a href="/parkir/auth/logout.php"
           class="btn btn-outline-danger btn-sm"
           onclick="return confirm('Logout?')">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </div>
</div>

<!-- STATISTIK -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card stat-card blue shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Total Transaksi</h6>
                        <h2 class="mb-0 fw-bold text-primary"><?= $totalTransaksi ?></h2>
                        <small class="text-muted">Record</small>
                    </div>
                    <div class="text-primary" style="font-size: 3rem; opacity: 0.3;">
                        <i class="bi bi-list-ul"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card stat-card green shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Sedang Parkir</h6>
                        <h2 class="mb-0 fw-bold text-success"><?= $totalMasuk ?></h2>
                        <small class="text-muted">Kendaraan</small>
                    </div>
                    <div class="text-success" style="font-size: 3rem; opacity: 0.3;">
                        <i class="bi bi-arrow-down-circle-fill"></i>
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
                        <h6 class="text-muted mb-2">Sudah Keluar</h6>
                        <h2 class="mb-0 fw-bold text-warning"><?= $totalKeluar ?></h2>
                        <small class="text-muted">Kendaraan</small>
                    </div>
                    <div class="text-warning" style="font-size: 3rem; opacity: 0.3;">
                        <i class="bi bi-arrow-up-circle-fill"></i>
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
                        <h6 class="text-muted mb-2">Total Pendapatan</h6>
                        <h2 class="mb-0 fw-bold" style="color: #6f42c1;">Rp <?= number_format($totalPendapatan, 0, ',', '.') ?></h2>
                        <small class="text-muted">Dari filter</small>
                    </div>
                    <div style="font-size: 3rem; opacity: 0.3; color: #6f42c1;">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- FILTER -->
<div class="card mb-4 shadow-sm">
<div class="card-header bg-primary text-white fw-semibold">
    <i class="bi bi-funnel"></i> Filter Pencarian
</div>
<div class="card-body">
<form method="get" class="row g-3">
    <div class="col-md-2">
        <label class="form-label">Dari Tanggal</label>
        <input type="date" name="dari" class="form-control" 
               value="<?= htmlspecialchars($_GET['dari'] ?? '') ?>">
    </div>
    <div class="col-md-2">
        <label class="form-label">Sampai Tanggal</label>
        <input type="date" name="sampai" class="form-control" 
               value="<?= htmlspecialchars($_GET['sampai'] ?? '') ?>">
    </div>
    <div class="col-md-2">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
            <option value="">Semua Status</option>
            <option value="masuk" <?= isset($_GET['status']) && $_GET['status']=='masuk' ? 'selected' : '' ?>>Masuk</option>
            <option value="keluar" <?= isset($_GET['status']) && $_GET['status']=='keluar' ? 'selected' : '' ?>>Keluar</option>
        </select>
    </div>
    <div class="col-md-2">
        <label class="form-label">Plat Nomor</label>
        <input type="text" name="plat" class="form-control text-uppercase" 
               placeholder="B1234XYZ"
               value="<?= htmlspecialchars($_GET['plat'] ?? '') ?>">
    </div>
    <div class="col-md-2">
        <label class="form-label">Area Parkir</label>
        <select name="area" class="form-select">
            <option value="">Semua Area</option>
            <?php while($area = mysqli_fetch_assoc($areaList)): ?>
            <option value="<?= $area['id_area'] ?>" 
                    <?= isset($_GET['area']) && $_GET['area']==$area['id_area'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($area['nama_area']) ?>
            </option>
            <?php endwhile; ?>
        </select>
    </div>
    <div class="col-md-2 d-flex align-items-end gap-2">
        <button type="submit" class="btn btn-primary flex-fill">
            <i class="bi bi-search"></i> Filter
        </button>
        <a href="riwayat.php" class="btn btn-secondary">
            <i class="bi bi-arrow-clockwise"></i>
        </a>
    </div>
</form>
</div>
</div>

<!-- TABEL RIWAYAT -->
<div class="card shadow-sm">
<div class="card-header bg-secondary text-white fw-semibold">
    <i class="bi bi-table"></i> Daftar Riwayat Transaksi
</div>
<div class="card-body p-0">
<div class="table-responsive">
<table class="table table-hover table-striped mb-0">
<thead class="table-light">
<tr>
    <th style="width: 4%" class="text-center">No</th>
    <th>Plat Nomor</th>
    <th>Jenis</th>
    <th>Pemilik</th>
    <th>Area</th>
    <th>Waktu Masuk</th>
    <th>Waktu Keluar</th>
    <th class="text-center">Durasi</th>
    <th class="text-end">Biaya</th>
    <th class="text-center">Status</th>
    <th>Petugas</th>
</tr>
</thead>
<tbody>
<?php 
if ($riwayat->num_rows > 0):
    $no = 1;
    while($r = $riwayat->fetch_assoc()):
?>
<tr>
    <td class="text-center"><?= $no++ ?></td>
    <td><strong><?= htmlspecialchars($r['plat_nomor']) ?></strong></td>
    <td><?= ucfirst(htmlspecialchars($r['jenis_kendaraan'])) ?></td>
    <td><?= htmlspecialchars($r['pemilik']) ?></td>
    <td>
        <span class="badge bg-info">
            <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($r['nama_area']) ?>
        </span>
    </td>
    <td><small><?= date('d/m/Y H:i', strtotime($r['waktu_masuk'])) ?></small></td>
    <td>
        <?php if($r['status'] == 'keluar'): ?>
            <small><?= date('d/m/Y H:i', strtotime($r['waktu_keluar'])) ?></small>
        <?php else: ?>
            <span class="badge bg-secondary">-</span>
        <?php endif; ?>
    </td>
    <td class="text-center">
        <?php if($r['status'] == 'keluar'): ?>
            <span class="badge bg-primary"><?= htmlspecialchars($r['durasi_jam']) ?> jam</span>
        <?php else: ?>
            <?php
            $waktuMasuk = strtotime($r['waktu_masuk']);
            $waktuSekarang = time();
            $selisih = $waktuSekarang - $waktuMasuk;
            $jam = floor($selisih / 3600);
            $menit = floor(($selisih % 3600) / 60);
            ?>
            <small class="text-muted"><?= $jam ?>j <?= $menit ?>m</small>
        <?php endif; ?>
    </td>
    <td class="text-end">
        <?php if($r['status'] == 'keluar'): ?>
            <strong class="text-success">Rp <?= number_format($r['biaya_total']) ?></strong>
        <?php else: ?>
            <span class="text-muted">-</span>
        <?php endif; ?>
    </td>
    <td class="text-center">
        <?php if($r['status'] == 'masuk'): ?>
            <span class="badge bg-success">
                <i class="bi bi-arrow-down-circle"></i> Masuk
            </span>
        <?php else: ?>
            <span class="badge bg-danger">
                <i class="bi bi-arrow-up-circle"></i> Keluar
            </span>
        <?php endif; ?>
    </td>
    <td><small><?= htmlspecialchars($r['petugas']) ?></small></td>
</tr>
<?php 
    endwhile;
else:
?>
<tr>
    <td colspan="11" class="text-center text-muted py-5">
        <i class="bi bi-inbox display-1"></i>
        <p class="mt-3">Tidak ada data transaksi</p>
        <small>Coba ubah filter pencarian</small>
    </td>
</tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>
<?php if ($riwayat->num_rows > 0): ?>
<div class="card-footer text-muted">
    <small>
        <i class="bi bi-info-circle"></i> 
        Menampilkan <?= $riwayat->num_rows ?> transaksi
    </small>
</div>
<?php endif; ?>
</div>

<!-- INFO PANEL -->
<div class="card mt-4 border-info">
    <div class="card-body">
        <h6 class="card-title text-info">
            <i class="bi bi-info-circle-fill"></i> Informasi Riwayat
        </h6>
        <div class="row">
            <div class="col-md-6">
                <ul class="mb-0 small">
                    <li>Gunakan filter untuk mempersempit pencarian data</li>
                    <li>Status <span class="badge bg-success">Masuk</span> = Kendaraan masih parkir</li>
                    <li>Status <span class="badge bg-danger">Keluar</span> = Kendaraan sudah keluar</li>
                </ul>
            </div>
            <div class="col-md-6">
                <ul class="mb-0 small">
                    <li>Durasi yang sedang berjalan akan terupdate otomatis saat refresh</li>
                    <li>Biaya hanya muncul untuk transaksi yang sudah selesai</li>
                    <li>Data diurutkan dari transaksi terbaru</li>
                </ul>
            </div>
        </div>
    </div>
</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>