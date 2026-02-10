<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';
include '../config/log_helper.php';

/* TAMBAH AREA */
if (isset($_POST['tambah'])) {
    $nama = trim($_POST['nama_area']); // FIX #10
    $kapasitas = intval($_POST['kapasitas']); // FIX #10
    
    // FIX #1: Prepared statement
    $stmt = $koneksi->prepare("INSERT INTO tb_area_parkir (nama_area, kapasitas, terisi) VALUES (?, ?, 0)");
    $stmt->bind_param("si", $nama, $kapasitas);
    
    // FIX #5: Error handling
    if ($stmt->execute()) {
        simpan_log($koneksi, $_SESSION['id_user'], "Menambah area parkir: $nama");
        $_SESSION['success'] = "Area parkir berhasil ditambahkan!";
    } else {
        $_SESSION['error'] = "Gagal: " . $stmt->error;
    }
    $stmt->close();
    header("Location: area.php");
    exit;
}

/* UPDATE AREA */
if (isset($_POST['update'])) {
    $id = intval($_POST['id_area']); // FIX #10
    $nama = trim($_POST['nama_area']);
    $kapasitas = intval($_POST['kapasitas']);
    
    // Cek terisi dengan prepared statement // FIX #1
    $stmt = $koneksi->prepare("SELECT terisi FROM tb_area_parkir WHERE id_area=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $cekTerisi = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // Validasi
    if ($kapasitas < $cekTerisi['terisi']) {
        $_SESSION['error'] = "Kapasitas tidak boleh kurang dari jumlah terisi!";
        header("Location: area.php");
        exit;
    }
    
    // Update dengan prepared statement // FIX #1
    $stmt = $koneksi->prepare("UPDATE tb_area_parkir SET nama_area=?, kapasitas=? WHERE id_area=?");
    $stmt->bind_param("sii", $nama, $kapasitas, $id);
    $stmt->execute();
    $stmt->close();
    
    simpan_log($koneksi, $_SESSION['id_user'], "Mengedit area parkir ID $id");
    header("Location: area.php");
    exit;
}

/* HAPUS AREA */
if (isset($_GET['hapus'])) {
    $id = intval($_GET['hapus']); // FIX #1 & #10
    
    // Cek terisi
    $stmt = $koneksi->prepare("SELECT terisi FROM tb_area_parkir WHERE id_area=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $cek = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($cek && $cek['terisi'] > 0) {
        $_SESSION['error'] = "Area masih terisi!";
        header("Location: area.php");
        exit;
    }
    
    // Delete
    $stmt = $koneksi->prepare("DELETE FROM tb_area_parkir WHERE id_area=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    
    header("Location: area.php");
    exit;
}

/* ===== AMBIL DATA AREA ===== */
$areas = mysqli_query($koneksi, "SELECT * FROM tb_area_parkir ORDER BY nama_area ASC");

/* ===== STATISTIK AREA ===== */
$totalArea = mysqli_num_rows($areas);
$totalKapasitas = mysqli_fetch_assoc(
    mysqli_query($koneksi, "SELECT SUM(kapasitas) as total FROM tb_area_parkir")
)['total'] ?? 0;
$totalTerisi = mysqli_fetch_assoc(
    mysqli_query($koneksi, "SELECT SUM(terisi) as total FROM tb_area_parkir")
)['total'] ?? 0;
$totalKosong = $totalKapasitas - $totalTerisi;
$persentaseOkupansi = $totalKapasitas > 0 ? ($totalTerisi / $totalKapasitas * 100) : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Manajemen Area Parkir</title>
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
.stat-card.red { border-left-color: #dc3545; }
.area-progress {
    height: 10px;
}
</style>
</head>

<body class="bg-light">
<div class="container-fluid my-4">

<!-- HEADER -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="mb-0">
            <i class="bi bi-geo-alt-fill text-primary"></i> 
            Manajemen Area Parkir
        </h3>
        <small class="text-muted">Kelola area dan kapasitas parkir</small>
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

<!-- NOTIFIKASI -->
<?php if (isset($_SESSION['success'])): ?>
<div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle-fill"></i>
    <?= htmlspecialchars($_SESSION['success']) ?>
    <button class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php unset($_SESSION['success']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <?= htmlspecialchars($_SESSION['error']) ?>
    <button class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php unset($_SESSION['error']); ?>
<?php endif; ?>

<!-- STATISTIK -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card stat-card blue shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Total Area</h6>
                        <h2 class="mb-0 fw-bold text-primary"><?= $totalArea ?></h2>
                        <small class="text-muted">Area parkir</small>
                    </div>
                    <div class="text-primary" style="font-size: 3rem; opacity: 0.3;">
                        <i class="bi bi-geo-alt-fill"></i>
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
                        <h6 class="text-muted mb-2">Total Kapasitas</h6>
                        <h2 class="mb-0 fw-bold text-success"><?= $totalKapasitas ?></h2>
                        <small class="text-muted">Slot parkir</small>
                    </div>
                    <div class="text-success" style="font-size: 3rem; opacity: 0.3;">
                        <i class="bi bi-grid-3x3-gap-fill"></i>
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
                        <h6 class="text-muted mb-2">Slot Terisi</h6>
                        <h2 class="mb-0 fw-bold text-warning"><?= $totalTerisi ?></h2>
                        <small class="text-muted">Kendaraan parkir</small>
                    </div>
                    <div class="text-warning" style="font-size: 3rem; opacity: 0.3;">
                        <i class="bi bi-car-front-fill"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card stat-card red shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Slot Kosong</h6>
                        <h2 class="mb-0 fw-bold text-danger"><?= $totalKosong ?></h2>
                        <small class="text-muted">Tersedia</small>
                    </div>
                    <div class="text-danger" style="font-size: 3rem; opacity: 0.3;">
                        <i class="bi bi-dash-circle-fill"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- OKUPANSI KESELURUHAN -->
<div class="card mb-4 shadow-sm border-info">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0"><i class="bi bi-pie-chart-fill text-info"></i> Okupansi Keseluruhan</h6>
            <strong><?= number_format($persentaseOkupansi, 1) ?>%</strong>
        </div>
        <div class="progress" style="height: 25px;">
            <?php
            if ($persentaseOkupansi >= 90) $barColor = 'bg-danger';
            elseif ($persentaseOkupansi >= 70) $barColor = 'bg-warning';
            elseif ($persentaseOkupansi > 0) $barColor = 'bg-success';
            else $barColor = 'bg-info';
            ?>
            <div class="progress-bar <?= $barColor ?> progress-bar-striped progress-bar-animated" 
                 role="progressbar" 
                 style="width: <?= $persentaseOkupansi ?>%">
                <?= $totalTerisi ?> / <?= $totalKapasitas ?> slot
            </div>
        </div>
    </div>
</div>

<!-- FORM TAMBAH -->
<div class="card mb-4 shadow-sm">
<div class="card-header bg-primary text-white fw-semibold">
    <i class="bi bi-plus-circle"></i> Tambah Area Parkir Baru
</div>
<div class="card-body">
<form method="post">
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Nama Area</label>
        <input name="nama_area" class="form-control" 
               placeholder="Contoh: Area A, Lantai 1, Basement" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">Kapasitas Maksimal</label>
        <input name="kapasitas" type="number" min="1" 
               class="form-control" 
               placeholder="Jumlah slot parkir" required>
    </div>
    <div class="col-md-2 d-flex align-items-end">
        <button name="tambah" class="btn btn-primary w-100">
            <i class="bi bi-plus-lg"></i> Tambah
        </button>
    </div>
</div>
</form>
</div>
</div>

<!-- TABEL AREA PARKIR -->
<div class="card shadow-sm">
<div class="card-header bg-secondary text-white fw-semibold">
    <i class="bi bi-table"></i> Daftar Area Parkir
</div>
<div class="card-body p-0">
<div class="table-responsive">
<table class="table table-hover mb-0">
<thead class="table-light">
<tr>
    <th style="width: 5%" class="text-center">No</th>
    <th>Nama Area</th>
    <th style="width: 10%" class="text-center">Kapasitas</th>
    <th style="width: 10%" class="text-center">Terisi</th>
    <th style="width: 10%" class="text-center">Kosong</th>
    <th style="width: 30%">Status Okupansi</th>
    <th style="width: 15%" class="text-center">Aksi</th>
</tr>
</thead>
<tbody>
<?php 
mysqli_data_seek($areas, 0);
$no = 1; 
if(mysqli_num_rows($areas) > 0):
while($area = mysqli_fetch_assoc($areas)): 
    $kapasitas = $area['kapasitas'];
    $terisi = $area['terisi'];
    $kosong = $kapasitas - $terisi;
    $persentase = $kapasitas > 0 ? ($terisi / $kapasitas * 100) : 0;
    
    // Tentukan warna dan status
    if ($persentase >= 90) {
        $progressColor = 'bg-danger';
        $statusBadge = '<span class="badge bg-danger">Penuh</span>';
    } elseif ($persentase >= 70) {
        $progressColor = 'bg-warning';
        $statusBadge = '<span class="badge bg-warning text-dark">Hampir Penuh</span>';
    } elseif ($persentase > 0) {
        $progressColor = 'bg-success';
        $statusBadge = '<span class="badge bg-success">Tersedia</span>';
    } else {
        $progressColor = 'bg-info';
        $statusBadge = '<span class="badge bg-info">Kosong</span>';
    }
?>
<tr>
    <td class="text-center"><?= $no++ ?></td>
    <td>
        <div>
            <strong><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($area['nama_area']) ?></strong>
        </div>
        <?= $statusBadge ?>
    </td>
    <td class="text-center">
        <strong class="text-success"><?= $kapasitas ?></strong>
    </td>
    <td class="text-center">
        <span class="badge bg-warning"><?= $terisi ?></span>
    </td>
    <td class="text-center">
        <span class="badge bg-success"><?= $kosong ?></span>
    </td>
    <td>
        <div class="mb-1">
            <small class="text-muted">
                <?= number_format($persentase, 1) ?>% - 
                <?= $terisi ?> dari <?= $kapasitas ?> slot
            </small>
        </div>
        <div class="progress area-progress">
            <div class="progress-bar <?= $progressColor ?> progress-bar-striped" 
                 role="progressbar" 
                 style="width: <?= $persentase ?>%"></div>
        </div>
    </td>
    <td class="text-center">
        <button class="btn btn-warning btn-sm" 
                data-bs-toggle="modal"
                data-bs-target="#edit<?= $area['id_area'] ?>">
            <i class="bi bi-pencil-square"></i> Edit
        </button>
        <a href="?hapus=<?= $area['id_area'] ?>"
           class="btn btn-danger btn-sm"
           onclick="return confirm('Yakin hapus area <?= htmlspecialchars($area['nama_area']) ?>?')">
            <i class="bi bi-trash"></i> Hapus
        </a>
    </td>
</tr>

<!-- MODAL EDIT -->
<div class="modal fade" id="edit<?= $area['id_area'] ?>">
<div class="modal-dialog">
<div class="modal-content">
<form method="post">
<div class="modal-header bg-warning">
    <h5 class="modal-title">
        <i class="bi bi-pencil-square"></i> Edit Area Parkir
    </h5>
    <button class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
    <input type="hidden" name="id_area" value="<?= $area['id_area'] ?>">
    
    <div class="mb-3">
        <label class="form-label">Nama Area</label>
        <input name="nama_area" class="form-control" 
               value="<?= htmlspecialchars($area['nama_area']) ?>" required>
    </div>
    
    <div class="mb-3">
        <label class="form-label">Kapasitas Maksimal</label>
        <input name="kapasitas" type="number" min="<?= $terisi ?>" 
               class="form-control" 
               value="<?= $kapasitas ?>" required>
        <small class="text-muted">
            <i class="bi bi-info-circle"></i> Minimal: <?= $terisi ?> (jumlah terisi saat ini)
        </small>
    </div>
    
    <div class="alert alert-info mb-0">
        <h6 class="alert-heading"><i class="bi bi-info-circle"></i> Informasi Area</h6>
        <hr>
        <ul class="mb-0 small">
            <li>Terisi saat ini: <strong><?= $terisi ?> kendaraan</strong></li>
            <li>Slot kosong: <strong><?= $kosong ?> slot</strong></li>
            <li>Okupansi: <strong><?= number_format($persentase, 1) ?>%</strong></li>
        </ul>
    </div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
        <i class="bi bi-x-circle"></i> Batal
    </button>
    <button name="update" class="btn btn-warning">
        <i class="bi bi-check-circle"></i> Simpan Perubahan
    </button>
</div>
</form>
</div>
</div>
</div>

<?php endwhile; ?>
<?php else: ?>
<tr>
    <td colspan="7" class="text-center text-muted py-5">
        <i class="bi bi-inbox display-1"></i>
        <p class="mt-3">Belum ada area parkir. Silakan tambahkan area parkir baru.</p>
    </td>
</tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>
</div>

<!-- INFO PANEL -->
<div class="card mt-4 border-info">
    <div class="card-body">
        <h6 class="card-title text-info">
            <i class="bi bi-info-circle-fill"></i> Informasi Sistem Area Parkir
        </h6>
        <div class="row">
            <div class="col-md-6">
                <ul class="mb-0 small">
                    <li>Kolom <strong>Terisi</strong> akan otomatis bertambah saat ada kendaraan masuk (status: masuk)</li>
                    <li>Kolom <strong>Terisi</strong> akan otomatis berkurang saat kendaraan keluar (status: keluar)</li>
                    <li>Area parkir tidak dapat dihapus jika masih ada kendaraan yang parkir</li>
                </ul>
            </div>
            <div class="col-md-6">
                <ul class="mb-0 small">
                    <li>Kapasitas tidak dapat diubah menjadi lebih kecil dari jumlah kendaraan yang sedang parkir</li>
                    <li>Sistem menggunakan trigger database untuk update otomatis</li>
                    <li>Semua perubahan akan tercatat dalam log aktivitas</li>
                </ul>
            </div>
        </div>
    </div>
</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>