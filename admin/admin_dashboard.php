<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';
include '../config/log_helper.php';

date_default_timezone_set('Asia/Jakarta');

// Log aktivitas mengakses dashboard
if (!isset($_SESSION['admin_dashboard_logged'])) {
    simpan_log($koneksi, $_SESSION['id_user'], 'Mengakses Dashboard Admin');
    $_SESSION['admin_dashboard_logged'] = true;
}

// Ringkasan data
$totalUser = $koneksi->query("SELECT COUNT(*) FROM tb_user")->fetch_row()[0];
$totalKendaraan = $koneksi->query("SELECT COUNT(*) FROM tb_kendaraan")->fetch_row()[0];
$totalArea = $koneksi->query("SELECT COUNT(*) FROM tb_area_parkir")->fetch_row()[0];
$totalTarif = $koneksi->query("SELECT COUNT(*) FROM tb_tarif")->fetch_row()[0];

// Data Area Parkir
$areaList = mysqli_query($koneksi, "
    SELECT id_area, nama_area, kapasitas, terisi 
    FROM tb_area_parkir 
    ORDER BY nama_area ASC 
    LIMIT 5
");

// Aktivitas Terbaru
$recentLogs = mysqli_query($koneksi, "
    SELECT l.aktivitas, l.waktu_aktivitas, u.nama_lengkap, u.role
    FROM tb_log_aktivitas l
    JOIN tb_user u ON l.id_user = u.id_user
    ORDER BY l.waktu_aktivitas DESC
    LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Admin</title>
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
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }
        .badge-status {
            font-size: 0.75rem;
            padding: 0.35em 0.65em;
        }
    </style>
</head>
<body class="bg-light">

<div class="container-fluid my-4">

    <!-- HEADER -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-0">
                <i class="bi bi-shield-check text-danger"></i> 
                Dashboard Admin
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
                            <h6 class="text-muted mb-2">Total User</h6>
                            <h2 class="mb-0 fw-bold text-primary"><?= $totalUser ?></h2>
                            <small class="text-muted">Pengguna sistem</small>
                        </div>
                        <div class="text-primary" style="font-size: 3rem; opacity: 0.3;">
                            <i class="bi bi-people-fill"></i>
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
                            <h6 class="text-muted mb-2">Total Kendaraan</h6>
                            <h2 class="mb-0 fw-bold text-success"><?= $totalKendaraan ?></h2>
                            <small class="text-muted">Terdaftar</small>
                        </div>
                        <div class="text-success" style="font-size: 3rem; opacity: 0.3;">
                            <i class="bi bi-car-front-fill"></i>
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
                            <h6 class="text-muted mb-2">Total Area Parkir</h6>
                            <h2 class="mb-0 fw-bold text-warning"><?= $totalArea ?></h2>
                            <small class="text-muted">Lokasi parkir</small>
                        </div>
                        <div class="text-warning" style="font-size: 3rem; opacity: 0.3;">
                            <i class="bi bi-geo-alt-fill"></i>
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
                            <h6 class="text-muted mb-2">Total Tarif</h6>
                            <h2 class="mb-0 fw-bold" style="color: #6f42c1;"><?= $totalTarif ?></h2>
                            <small class="text-muted">Jenis tarif</small>
                        </div>
                        <div style="font-size: 3rem; opacity: 0.3; color: #6f42c1;">
                            <i class="bi bi-cash-coin"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MENU UTAMA -->
    <div class="row g-3 mb-4">
        <div class="col-md-2-4">
            <a href="tarif.php" class="text-decoration-none">
                <div class="card menu-card shadow-sm text-center h-100">
                    <div class="card-body py-4">
                        <div class="menu-icon text-primary">
                            <i class="bi bi-cash-stack"></i>
                        </div>
                        <h6 class="mb-0 fw-semibold">Tarif Parkir</h6>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-2-4">
            <a href="area.php" class="text-decoration-none">
                <div class="card menu-card shadow-sm text-center h-100">
                    <div class="card-body py-4">
                        <div class="menu-icon text-success">
                            <i class="bi bi-pin-map-fill"></i>
                        </div>
                        <h6 class="mb-0 fw-semibold">Area Parkir</h6>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-2-4">
            <a href="kendaraan.php" class="text-decoration-none">
                <div class="card menu-card shadow-sm text-center h-100">
                    <div class="card-body py-4">
                        <div class="menu-icon text-warning">
                            <i class="bi bi-truck"></i>
                        </div>
                        <h6 class="mb-0 fw-semibold">Kendaraan</h6>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-2-4">
            <a href="user.php" class="text-decoration-none">
                <div class="card menu-card shadow-sm text-center h-100">
                    <div class="card-body py-4">
                        <div class="menu-icon text-info">
                            <i class="bi bi-person-gear"></i>
                        </div>
                        <h6 class="mb-0 fw-semibold">User</h6>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-2-4">
            <a href="log.php" class="text-decoration-none">
                <div class="card menu-card shadow-sm text-center h-100">
                    <div class="card-body py-4">
                        <div class="menu-icon text-dark">
                            <i class="bi bi-clock-history"></i>
                        </div>
                        <h6 class="mb-0 fw-semibold">Log Aktivitas</h6>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <div class="row g-4">
        <!-- STATUS AREA PARKIR -->
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white fw-semibold">
                    <i class="bi bi-geo-alt-fill"></i> Status Area Parkir
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
                                            $badge = '<span class="badge badge-status bg-danger">Penuh</span>';
                                        } elseif ($persentase >= 70) {
                                            $badge = '<span class="badge badge-status bg-warning text-dark">Hampir Penuh</span>';
                                        } elseif ($persentase > 0) {
                                            $badge = '<span class="badge badge-status bg-success">Tersedia</span>';
                                        } else {
                                            $badge = '<span class="badge badge-status bg-info">Kosong</span>';
                                        }
                                    ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($area['nama_area']) ?></strong></td>
                                        <td class="text-center"><strong><?= $area['terisi'] ?></strong></td>
                                        <td class="text-center"><?= $area['kapasitas'] ?></td>
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
                <?php if (mysqli_num_rows($areaList) > 0): ?>
                <div class="card-footer text-center">
                    <a href="area.php" class="text-decoration-none">
                        Lihat Semua Area <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- AKTIVITAS TERBARU -->
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white fw-semibold">
                    <i class="bi bi-activity"></i> Aktivitas Terbaru
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>User</th>
                                    <th>Aktivitas</th>
                                    <th>Waktu</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (mysqli_num_rows($recentLogs) > 0): ?>
                                    <?php while($log = mysqli_fetch_assoc($recentLogs)): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($log['nama_lengkap']) ?></strong>
                                            <br><small class="text-muted"><?= ucfirst($log['role']) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($log['aktivitas']) ?></td>
                                        <td>
                                            <small><?= date('d/m H:i', strtotime($log['waktu_aktivitas'])) ?></small>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-3">
                                            Belum ada aktivitas
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php if (mysqli_num_rows($recentLogs) > 0): ?>
                <div class="card-footer text-center">
                    <a href="log.php" class="text-decoration-none">
                        Lihat Semua Log <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- INFO PANEL -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-info">
                <div class="card-body">
                    <h6 class="card-title text-info">
                        <i class="bi bi-info-circle-fill"></i> Informasi Admin
                    </h6>
                    <div class="row">
                        <div class="col-md-6">
                            <ul class="mb-0 small">
                                <li>Kelola <strong>Tarif Parkir</strong> untuk mengatur biaya per jam</li>
                                <li>Atur <strong>Area Parkir</strong> dan kapasitasnya</li>
                                <li>Pantau <strong>Log Aktivitas</strong> untuk audit sistem</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul class="mb-0 small">
                                <li>Manajemen <strong>User</strong> untuk kontrol akses</li>
                                <li>Data <strong>Kendaraan</strong> terdaftar dalam sistem</li>
                                <li>Semua perubahan tercatat otomatis</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<style>
    .col-md-2-4 {
        flex: 0 0 auto;
        width: 20%;
    }
    @media (max-width: 768px) {
        .col-md-2-4 {
            width: 50%;
        }
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>