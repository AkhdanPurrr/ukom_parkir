<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';
include '../config/log_helper.php';

// Log aktivitas mengakses halaman log
if (!isset($_SESSION['log_page_logged'])) {
    simpan_log($koneksi, $_SESSION['id_user'], 'Mengakses halaman Log Aktivitas');
    $_SESSION['log_page_logged'] = true;
}

date_default_timezone_set('Asia/Jakarta');

// Filter dengan prepared statement
$where = "";
$params = [];
$types = "";

// FIX #1 & #10: Sanitasi input
if (!empty($_GET['dari']) && !empty($_GET['sampai'])) {
    $dari = trim($_GET['dari']);
    $sampai = trim($_GET['sampai']);
    $where = "WHERE DATE(l.waktu_aktivitas) BETWEEN ? AND ?";
    $params = [$dari, $sampai];
    $types = "ss";
}

if (!empty($_GET['role'])) {
    $role = trim($_GET['role']);
    // Validasi role
    if (in_array($role, ['admin', 'petugas', 'owner'])) {
        if ($where) {
            $where .= " AND u.role = ?";
        } else {
            $where = "WHERE u.role = ?";
        }
        $params[] = $role;
        $types .= "s";
    }
}

$sql = "SELECT l.*, u.nama_lengkap, u.role 
        FROM tb_log_aktivitas l 
        JOIN tb_user u ON l.id_user = u.id_user 
        $where 
        ORDER BY l.waktu_aktivitas DESC";

$stmt = $koneksi->prepare($sql);
if ($where) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// FIX #5: Error handling
if (!$result) {
    die("Error: " . $koneksi->error);
}
// Statistik
$totalLogs = $result->num_rows;
$totalToday = mysqli_fetch_assoc(
    mysqli_query($koneksi, "SELECT COUNT(*) as total FROM tb_log_aktivitas WHERE DATE(waktu_aktivitas) = CURDATE()")
)['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Log Aktivitas</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .stat-card {
            border-left: 4px solid;
        }
        .stat-card.blue { border-left-color: #0d6efd; }
        .stat-card.green { border-left-color: #198754; }
        .badge-role {
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
                <i class="bi bi-clock-history text-dark"></i> 
                Log Aktivitas Sistem
            </h3>
            <small class="text-muted">Memantau seluruh aktivitas pengguna</small>
        </div>
        <div class="d-flex gap-2">
            <a href="admin_dashboard.php" class="btn btn-secondary btn-sm">
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
        <div class="col-md-6">
            <div class="card stat-card blue shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Total Log (Filter)</h6>
                            <h2 class="mb-0 fw-bold text-primary"><?= $totalLogs ?></h2>
                            <small class="text-muted">Aktivitas tercatat</small>
                        </div>
                        <div class="text-primary" style="font-size: 3rem; opacity: 0.3;">
                            <i class="bi bi-journal-text"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card stat-card green shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Aktivitas Hari Ini</h6>
                            <h2 class="mb-0 fw-bold text-success"><?= $totalToday ?></h2>
                            <small class="text-muted">Log hari ini</small>
                        </div>
                        <div class="text-success" style="font-size: 3rem; opacity: 0.3;">
                            <i class="bi bi-calendar-check"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- FILTER -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white fw-semibold">
            <i class="bi bi-funnel"></i> Filter Log Aktivitas
        </div>
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Dari Tanggal</label>
                    <input type="date" name="dari" class="form-control" 
                           value="<?= htmlspecialchars($_GET['dari'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Sampai Tanggal</label>
                    <input type="date" name="sampai" class="form-control" 
                           value="<?= htmlspecialchars($_GET['sampai'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Role</label>
                    <select name="role" class="form-select">
                        <option value="">Semua Role</option>
                        <option value="admin" <?= ($_GET['role'] ?? '') == 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="petugas" <?= ($_GET['role'] ?? '') == 'petugas' ? 'selected' : '' ?>>Petugas</option>
                        <option value="owner" <?= ($_GET['role'] ?? '') == 'owner' ? 'selected' : '' ?>>Owner</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">
                        <i class="bi bi-search"></i> Filter
                    </button>
                    <a href="log.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-clockwise"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- TABEL LOG -->
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white fw-semibold">
            <i class="bi bi-list-ul"></i> Daftar Log Aktivitas
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 5%" class="text-center">No</th>
                            <th style="width: 20%">Pengguna</th>
                            <th style="width: 10%">Role</th>
                            <th>Aktivitas</th>
                            <th style="width: 18%">Waktu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if ($result->num_rows > 0):
                            $no = 1;
                            while ($row = $result->fetch_assoc()): 
                        ?>
                        <tr>
                            <td class="text-center text-muted"><?= $no++ ?></td>
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars($row['nama_lengkap']) ?></div>
                            </td>
                            <td>
                                <?php
                                $badgeClass = 'bg-secondary';
                                if ($row['role'] == 'admin') $badgeClass = 'bg-danger';
                                elseif ($row['role'] == 'petugas') $badgeClass = 'bg-primary';
                                elseif ($row['role'] == 'owner') $badgeClass = 'bg-info';
                                ?>
                                <span class="badge badge-role <?= $badgeClass ?>">
                                    <?= ucfirst($row['role']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($row['aktivitas']) ?></td>
                            <td>
                                <small class="text-muted">
                                    <i class="bi bi-clock me-1"></i>
                                    <?= date('d/m/Y H:i:s', strtotime($row['waktu_aktivitas'])) ?>
                                </small>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <i class="bi bi-info-circle display-6 text-muted"></i>
                                <p class="mt-2 text-muted">Tidak ada log aktivitas yang ditemukan.</p>
                                <a href="log.php" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-arrow-clockwise"></i> Tampilkan Semua
                                </a>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($result->num_rows > 0): ?>
        <div class="card-footer text-muted">
            <small>
                <i class="bi bi-info-circle"></i> 
                Menampilkan <?= $totalLogs ?> log aktivitas
                <?php if (!empty($_GET['dari']) && !empty($_GET['sampai'])): ?>
                    dari <?= date('d/m/Y', strtotime($_GET['dari'])) ?> 
                    sampai <?= date('d/m/Y', strtotime($_GET['sampai'])) ?>
                <?php endif; ?>
            </small>
        </div>
        <?php endif; ?>
    </div>

    <!-- INFO PANEL -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-info">
                <div class="card-body">
                    <h6 class="card-title text-info">
                        <i class="bi bi-info-circle-fill"></i> Informasi Log Aktivitas
                    </h6>
                    <ul class="mb-0 small">
                        <li>Log mencatat semua aktivitas pengguna dalam sistem</li>
                        <li>Gunakan filter tanggal dan role untuk pencarian spesifik</li>
                        <li>Data log tidak dapat dihapus untuk menjaga integritas audit</li>
                        <li>Zona waktu: Asia/Jakarta (WIB)</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>