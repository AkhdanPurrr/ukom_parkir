<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'petugas') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';
include '../config/log_helper.php';

date_default_timezone_set('Asia/Jakarta');

// Log aktivitas mengakses dashboard
if (!isset($_SESSION['dashboard_logged'])) {
    simpan_log($koneksi, $_SESSION['id_user'], 'Mengakses Dashboard Petugas');
    $_SESSION['dashboard_logged'] = true;
}

// Statistik untuk Petugas
$totalKendaraanMasuk = mysqli_fetch_assoc(
    mysqli_query($koneksi, "SELECT COUNT(*) as total FROM tb_transaksi WHERE status='masuk'")
)['total'] ?? 0;

$totalKendaraanHariIni = mysqli_fetch_assoc(
    mysqli_query($koneksi, "SELECT COUNT(*) as total FROM tb_transaksi WHERE DATE(waktu_masuk) = CURDATE()")
)['total'] ?? 0;

$pendapatanHariIni = mysqli_fetch_assoc(
    mysqli_query($koneksi, "SELECT SUM(biaya_total) as total FROM tb_transaksi WHERE DATE(waktu_keluar) = CURDATE() AND status='keluar'")
)['total'] ?? 0;

$totalTransaksiSelesai = mysqli_fetch_assoc(
    mysqli_query($koneksi, "SELECT COUNT(*) as total FROM tb_transaksi WHERE status='keluar'")
)['total'] ?? 0;

// Data Area Parkir untuk Quick View
$areaList = mysqli_query($koneksi, "
    SELECT id_area, nama_area, kapasitas, terisi, (kapasitas - terisi) as kosong
    FROM tb_area_parkir 
    ORDER BY nama_area ASC
");

// Transaksi Terbaru (5 terakhir)
$transaksiTerbaru = mysqli_query($koneksi, "
    SELECT t.*, k.plat_nomor, k.jenis_kendaraan, a.nama_area
    FROM tb_transaksi t
    JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
    JOIN tb_area_parkir a ON t.id_area = a.id_area
    ORDER BY t.waktu_masuk DESC
    LIMIT 5
");

// Log saat melihat statistik (hanya sekali per session)
if (isset($_GET['refresh_stats']) && !isset($_SESSION['stats_logged'])) {
    simpan_log($koneksi, $_SESSION['id_user'], 'Melihat statistik parkir');
    $_SESSION['stats_logged'] = true;
    header("Location: petugas_dashboard.php");
    exit;
}

// Log saat melihat detail area parkir
if (isset($_GET['view_area']) && !isset($_SESSION['area_logged'])) {
    simpan_log($koneksi, $_SESSION['id_user'], 'Melihat status area parkir');
    $_SESSION['area_logged'] = true;
    header("Location: petugas_dashboard.php");
    exit;
}

// Log saat melihat riwayat terbaru
if (isset($_GET['view_recent']) && !isset($_SESSION['recent_logged'])) {
    simpan_log($koneksi, $_SESSION['id_user'], 'Melihat transaksi terbaru');
    $_SESSION['recent_logged'] = true;
    header("Location: petugas_dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Petugas</title>
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
        
        .menu-card {
            transition: all 0.3s ease;
            cursor: pointer;
            border: 2px solid transparent;
        }
        .menu-card:hover {
            transform: scale(1.05);
            border-color: #0d6efd;
            box-shadow: 0 10px 25px rgba(13, 110, 253, 0.2);
        }
        .menu-icon {
            font-size: 3.5rem;
            margin-bottom: 1rem;
        }
        .badge-status {
            font-size: 0.75rem;
            padding: 0.35em 0.65em;
        }
        .area-progress {
            height: 8px;
        }
        .quick-action-btn {
            border-radius: 50px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .quick-action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body class="bg-light">

<div class="container-fluid my-4">

    <!-- HEADER -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-0">
                <i class="bi bi-speedometer2 text-primary"></i> 
                Dashboard Petugas
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

    <!-- STATISTIK CARDS -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card stat-card blue shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Kendaraan Masuk</h6>
                            <h2 class="mb-0 fw-bold text-primary"><?= $totalKendaraanMasuk ?></h2>
                            <small class="text-muted">Sedang parkir</small>
                        </div>
                        <div class="text-primary" style="font-size: 3rem; opacity: 0.3;">
                            <i class="bi bi-car-front-fill"></i>
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
                            <h6 class="text-muted mb-2">Transaksi Hari Ini</h6>
                            <h2 class="mb-0 fw-bold text-success"><?= $totalKendaraanHariIni ?></h2>
                            <small class="text-muted">Total masuk</small>
                        </div>
                        <div class="text-success" style="font-size: 3rem; opacity: 0.3;">
                            <i class="bi bi-calendar-check"></i>
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
                            <h6 class="text-muted mb-2">Pendapatan Hari Ini</h6>
                            <h2 class="mb-0 fw-bold text-warning">Rp <?= number_format($pendapatanHariIni, 0, ',', '.') ?></h2>
                            <small class="text-muted">Dari <?= mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as total FROM tb_transaksi WHERE DATE(waktu_keluar) = CURDATE() AND status='keluar'"))['total'] ?> transaksi</small>
                        </div>
                        <div class="text-warning" style="font-size: 3rem; opacity: 0.3;">
                            <i class="bi bi-cash-stack"></i>
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
                            <h6 class="text-muted mb-2">Transaksi Selesai</h6>
                            <h2 class="mb-0 fw-bold" style="color: #6f42c1;"><?= $totalTransaksiSelesai ?></h2>
                            <small class="text-muted">Total keseluruhan</small>
                        </div>
                        <div style="font-size: 3rem; opacity: 0.3; color: #6f42c1;">
                            <i class="bi bi-clipboard-check"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MENU UTAMA -->
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <a href="../petugas/transaksi.php" class="text-decoration-none" 
               onclick="logActivity('Membuka halaman Transaksi Parkir')">
                <div class="card menu-card shadow-sm h-100">
                    <div class="card-body text-center py-5">
                        <div class="menu-icon text-primary">
                            <i class="bi bi-plus-circle-fill"></i>
                        </div>
                        <h4 class="mb-2">Transaksi Parkir</h4>
                        <p class="text-muted mb-3">
                            Input kendaraan masuk dan keluar
                        </p>
                        <button class="btn btn-primary quick-action-btn">
                            <i class="bi bi-arrow-right-circle"></i> Buka Transaksi
                        </button>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-6">
            <a href="../petugas/riwayat.php" class="text-decoration-none"
               onclick="logActivity('Membuka halaman Riwayat Transaksi')">
                <div class="card menu-card shadow-sm h-100">
                    <div class="card-body text-center py-5">
                        <div class="menu-icon text-success">
                            <i class="bi bi-clock-history"></i>
                        </div>
                        <h4 class="mb-2">Riwayat Transaksi</h4>
                        <p class="text-muted mb-3">
                            Lihat histori transaksi parkir
                        </p>
                        <button class="btn btn-success quick-action-btn">
                            <i class="bi bi-list-ul"></i> Lihat Riwayat
                        </button>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <div class="row g-4">
        <!-- STATUS AREA PARKIR -->
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white fw-semibold d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-geo-alt-fill"></i> Status Area Parkir</span>
                    <a href="?view_area=1" class="btn btn-sm btn-light" title="Log aktivitas melihat area">
                        <i class="bi bi-eye"></i>
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Area</th>
                                    <th class="text-center">Terisi</th>
                                    <th class="text-center">Kapasitas</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (mysqli_num_rows($areaList) > 0): ?>
                                    <?php while($area = mysqli_fetch_assoc($areaList)): 
                                        $persentase = $area['kapasitas'] > 0 ? ($area['terisi'] / $area['kapasitas'] * 100) : 0;
                                        
                                        if ($persentase >= 90) {
                                            $progressColor = 'bg-danger';
                                            $badge = '<span class="badge badge-status bg-danger">Penuh</span>';
                                        } elseif ($persentase >= 70) {
                                            $progressColor = 'bg-warning';
                                            $badge = '<span class="badge badge-status bg-warning text-dark">Hampir Penuh</span>';
                                        } elseif ($persentase > 0) {
                                            $progressColor = 'bg-success';
                                            $badge = '<span class="badge badge-status bg-success">Tersedia</span>';
                                        } else {
                                            $progressColor = 'bg-info';
                                            $badge = '<span class="badge badge-status bg-info">Kosong</span>';
                                        }
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($area['nama_area']) ?></strong>
                                            <div class="progress area-progress mt-1">
                                                <div class="progress-bar <?= $progressColor ?>" 
                                                     style="width: <?= $persentase ?>%"></div>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <strong><?= $area['terisi'] ?></strong>
                                        </td>
                                        <td class="text-center">
                                            <?= $area['kapasitas'] ?>
                                        </td>
                                        <td><?= $badge ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-3">
                                            Belum ada area parkir
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- TRANSAKSI TERBARU -->
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white fw-semibold d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-clock"></i> Transaksi Terbaru</span>
                    <a href="?view_recent=1" class="btn btn-sm btn-light" title="Log aktivitas melihat transaksi">
                        <i class="bi bi-eye"></i>
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Plat Nomor</th>
                                    <th>Area</th>
                                    <th>Waktu</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (mysqli_num_rows($transaksiTerbaru) > 0): ?>
                                    <?php while($trans = mysqli_fetch_assoc($transaksiTerbaru)): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($trans['plat_nomor']) ?></strong>
                                            <br><small class="text-muted"><?= ucfirst($trans['jenis_kendaraan']) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($trans['nama_area']) ?></td>
                                        <td>
                                            <small><?= date('d/m/Y H:i', strtotime($trans['waktu_masuk'])) ?></small>
                                        </td>
                                        <td>
                                            <?php if ($trans['status'] == 'masuk'): ?>
                                                <span class="badge badge-status bg-primary">Masuk</span>
                                            <?php else: ?>
                                                <span class="badge badge-status bg-secondary">Keluar</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-3">
                                            Belum ada transaksi
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php if (mysqli_num_rows($transaksiTerbaru) > 0): ?>
                <div class="card-footer text-center">
                    <a href="../petugas/riwayat.php" class="text-decoration-none"
                       onclick="logActivity('Membuka halaman Riwayat Transaksi dari dashboard')">
                        Lihat Semua Transaksi <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- AKTIVITAS TERAKHIR -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white fw-semibold">
                    <i class="bi bi-activity"></i> Aktivitas Saya Hari Ini
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 5%">No</th>
                                    <th>Aktivitas</th>
                                    <th style="width: 20%">Waktu</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $myLogs = mysqli_query($koneksi, "
                                    SELECT aktivitas, waktu_aktivitas 
                                    FROM tb_log_aktivitas 
                                    WHERE id_user = '{$_SESSION['id_user']}' 
                                    AND DATE(waktu_aktivitas) = CURDATE()
                                    ORDER BY waktu_aktivitas DESC 
                                    LIMIT 10
                                ");
                                
                                if (mysqli_num_rows($myLogs) > 0):
                                    $no = 1;
                                    while($log = mysqli_fetch_assoc($myLogs)):
                                ?>
                                <tr>
                                    <td class="text-center"><?= $no++ ?></td>
                                    <td><?= htmlspecialchars($log['aktivitas']) ?></td>
                                    <td><?= date('H:i:s', strtotime($log['waktu_aktivitas'])) ?></td>
                                </tr>
                                <?php 
                                    endwhile;
                                else:
                                ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-3">
                                        Belum ada aktivitas hari ini
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

    <!-- INFO PANEL -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-info">
                <div class="card-body">
                    <h6 class="card-title text-info">
                        <i class="bi bi-info-circle-fill"></i> Informasi Petugas
                    </h6>
                    <div class="row">
                        <div class="col-md-6">
                            <ul class="mb-0 small">
                                <li>Gunakan menu <strong>Transaksi Parkir</strong> untuk mencatat kendaraan masuk dan keluar</li>
                                <li>Cek <strong>Status Area Parkir</strong> sebelum mengarahkan kendaraan</li>
                                <li>Semua aktivitas akan tercatat dalam sistem log</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul class="mb-0 small">
                                <li>Gunakan <strong>Riwayat Transaksi</strong> untuk melihat histori lengkap</li>
                                <li>Pastikan data plat nomor dan area parkir sudah benar</li>
                                <li>Klik tombol <i class="bi bi-eye"></i> untuk mencatat aktivitas melihat data</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Function untuk log activity via JavaScript 
function logActivity(activity) {
    console.log('Activity logged: ' + activity);
}
</script>

</body>
</html>