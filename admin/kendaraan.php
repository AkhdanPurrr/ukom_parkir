<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// Set timezone
date_default_timezone_set('Asia/Jakarta');

include '../config/koneksi.php';
include '../config/log_helper.php';

/* ===== AMBIL ENUM JENIS KENDARAAN DARI TB_TARIF ===== */
$enum = mysqli_query($koneksi, "SHOW COLUMNS FROM tb_tarif LIKE 'jenis_kendaraan'");
$enumRow = mysqli_fetch_assoc($enum);
preg_match("/^enum\((.*)\)$/", $enumRow['Type'], $matches);
$jenisKendaraanOptions = array_map(fn($v) => trim($v, "'"), explode(',', $matches[1]));

/* ===== TAMBAH KENDARAAN ===== */
if (isset($_POST['tambah'])) {
    // FIX BUG #1 & #10: Sanitasi dan trim input
    $plat    = trim($_POST['plat_nomor']);
    $jenis   = trim($_POST['jenis_kendaraan']);
    $warna   = trim($_POST['warna']);
    $pemilik = trim($_POST['pemilik']);
    $idUser  = $_SESSION['id_user'];

    // FIX BUG #1: Gunakan prepared statement
    $stmt = $koneksi->prepare("INSERT INTO tb_kendaraan (plat_nomor, jenis_kendaraan, warna, pemilik, id_user) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssi", $plat, $jenis, $warna, $pemilik, $idUser);
    
    if ($stmt->execute()) {
        simpan_log($koneksi, $_SESSION['id_user'], "Menambah kendaraan $plat");
        header("Location: kendaraan.php?success=1");
    } else {
        // FIX BUG #5: Error handling
        $_SESSION['error'] = "Gagal menambah kendaraan: " . $stmt->error;
        header("Location: kendaraan.php");
    }
    $stmt->close();
    exit;
}

/* ===== UPDATE KENDARAAN ===== */
if (isset($_POST['update'])) {
    // FIX BUG #1 & #10: Sanitasi dan trim input
    $id      = intval($_POST['id_kendaraan']);
    $plat    = trim($_POST['plat_nomor']);
    $jenis   = trim($_POST['jenis_kendaraan']);
    $warna   = trim($_POST['warna']);
    $pemilik = trim($_POST['pemilik']);

    // FIX BUG #1: Gunakan prepared statement
    $stmt = $koneksi->prepare("UPDATE tb_kendaraan SET plat_nomor=?, jenis_kendaraan=?, warna=?, pemilik=? WHERE id_kendaraan=?");
    $stmt->bind_param("ssssi", $plat, $jenis, $warna, $pemilik, $id);
    
    if ($stmt->execute()) {
        simpan_log($koneksi, $_SESSION['id_user'], "Mengedit kendaraan ID $id");
    } else {
        // FIX BUG #5: Error handling
        $_SESSION['error'] = "Gagal update kendaraan: " . $stmt->error;
    }
    $stmt->close();
    header("Location: kendaraan.php");
    exit;
}

/* ===== HAPUS ===== */
if (isset($_GET['hapus'])) {
    // FIX BUG #1: Sanitasi input
    $id = intval($_GET['hapus']);
    
    // FIX BUG #1: Gunakan prepared statement
    $stmt = $koneksi->prepare("DELETE FROM tb_kendaraan WHERE id_kendaraan=?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        simpan_log($koneksi, $_SESSION['id_user'], "Menghapus kendaraan ID $id");
    }
    $stmt->close();
    header("Location: kendaraan.php");
    exit;
}

/* ===== DATA ===== */
$kendaraan = mysqli_query($koneksi, "
    SELECT k.*, u.nama_lengkap 
    FROM tb_kendaraan k 
    JOIN tb_user u ON k.id_user = u.id_user
    ORDER BY k.id_kendaraan DESC
");

// FIX BUG #5: Error handling untuk query
if (!$kendaraan) {
    die("Error query kendaraan: " . mysqli_error($koneksi));
}

/* ===== STATISTIK ===== */
$totalKendaraan = mysqli_num_rows($kendaraan);
$totalMotor = mysqli_fetch_assoc(
    mysqli_query($koneksi, "SELECT COUNT(*) as total FROM tb_kendaraan WHERE jenis_kendaraan='motor'")
)['total'] ?? 0;
$totalMobil = mysqli_fetch_assoc(
    mysqli_query($koneksi, "SELECT COUNT(*) as total FROM tb_kendaraan WHERE jenis_kendaraan='mobil'")
)['total'] ?? 0;
$totalLainnya = mysqli_fetch_assoc(
    mysqli_query($koneksi, "SELECT COUNT(*) as total FROM tb_kendaraan WHERE jenis_kendaraan='lainnya'")
)['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Manajemen Kendaraan</title>
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
            <i class="bi bi-car-front text-primary"></i> 
            Manajemen Kendaraan
        </h3>
        <small class="text-muted">
            Kelola data kendaraan terdaftar dalam sistem
        </small>
    </div>
    <div class="d-flex gap-2">
        <a href="admin_dashboard.php" class="btn btn-outline-secondary btn-sm">
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
<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle-fill me-2"></i>
    Data kendaraan berhasil ditambahkan
    <button class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>
    <?= htmlspecialchars($_SESSION['error']) ?>
    <button class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php unset($_SESSION['error']); ?>
<?php endif; ?>

<!-- STATISTIK CARDS -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card stat-card blue shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Total Kendaraan</h6>
                        <h2 class="mb-0 fw-bold text-primary"><?= $totalKendaraan ?></h2>
                        <small class="text-muted">Terdaftar</small>
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
                        <h6 class="text-muted mb-2">Motor</h6>
                        <h2 class="mb-0 fw-bold text-success"><?= $totalMotor ?></h2>
                        <small class="text-muted">Unit</small>
                    </div>
                    <div class="text-success" style="font-size: 3rem; opacity: 0.3;">
                        <i class="bi bi-bicycle"></i>
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
                        <h6 class="text-muted mb-2">Mobil</h6>
                        <h2 class="mb-0 fw-bold text-warning"><?= $totalMobil ?></h2>
                        <small class="text-muted">Unit</small>
                    </div>
                    <div class="text-warning" style="font-size: 3rem; opacity: 0.3;">
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
                        <h6 class="text-muted mb-2">Lainnya</h6>
                        <h2 class="mb-0 fw-bold" style="color: #6f42c1;"><?= $totalLainnya ?></h2>
                        <small class="text-muted">Unit</small>
                    </div>
                    <div style="font-size: 3rem; opacity: 0.3; color: #6f42c1;">
                        <i class="bi bi-truck"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- FORM TAMBAH -->
<div class="card mb-4 shadow-sm">
<div class="card-header bg-primary text-white fw-semibold">
    <i class="bi bi-plus-circle-fill"></i> Tambah Kendaraan Baru
</div>
<div class="card-body">
<form method="post">
<div class="row g-3">
    <div class="col-md-3">
        <label class="form-label small fw-semibold">Plat Nomor</label>
        <input name="plat_nomor" class="form-control" placeholder="Contoh: B 1234 XYZ" required>
    </div>
    <div class="col-md-2">
        <label class="form-label small fw-semibold">Jenis Kendaraan</label>
        <select name="jenis_kendaraan" class="form-select" required>
            <option value="">Pilih Jenis</option>
            <?php foreach($jenisKendaraanOptions as $jenis): ?>
                <?php if(!empty($jenis)): ?>
                <option value="<?= htmlspecialchars($jenis) ?>">
                    <?= ucfirst(htmlspecialchars($jenis)) ?>
                </option>
                <?php endif; ?>
            <?php endforeach ?>
        </select>
    </div>
    <div class="col-md-2">
        <label class="form-label small fw-semibold">Warna</label>
        <input name="warna" class="form-control" placeholder="Contoh: Hitam" required>
    </div>
    <div class="col-md-3">
        <label class="form-label small fw-semibold">Nama Pemilik</label>
        <input name="pemilik" class="form-control" placeholder="Nama lengkap pemilik" required>
    </div>
    <div class="col-md-2">
        <label class="form-label small fw-semibold">&nbsp;</label>
        <button name="tambah" class="btn btn-primary w-100">
            <i class="bi bi-plus-lg"></i> Tambah
        </button>
    </div>
</div>
<div class="mt-3">
    <div class="alert alert-info mb-0 py-2">
        <small>
            <i class="bi bi-info-circle-fill me-1"></i>
            Data akan tersimpan atas nama: <strong><?= htmlspecialchars($_SESSION['nama']) ?></strong>
        </small>
    </div>
</div>
</form>
</div>
</div>

<!-- TABEL DATA -->
<div class="card shadow-sm">
<div class="card-header bg-secondary text-white fw-semibold">
    <i class="bi bi-table"></i> Daftar Kendaraan Terdaftar
</div>
<div class="card-body p-0">
<div class="table-responsive">
<table class="table table-hover align-middle mb-0">
<thead class="table-light">
<tr>
    <th class="text-center" style="width: 60px;">No</th>
    <th>Plat Nomor</th>
    <th>Jenis</th>
    <th>Warna</th>
    <th>Pemilik</th>
    <th>Diinput Oleh</th>
    <th class="text-center" style="width: 180px;">Aksi</th>
</tr>
</thead>
<tbody>
<?php 
mysqli_data_seek($kendaraan, 0);
$no=1; 
if (mysqli_num_rows($kendaraan) > 0):
    while($k=mysqli_fetch_assoc($kendaraan)): 
?>
<tr>
    <td class="text-center text-muted small"><?= $no++ ?></td>
    <td>
        <strong><?= htmlspecialchars($k['plat_nomor']) ?></strong>
    </td>
    <td>
        <?php 
        $badgeColor = match($k['jenis_kendaraan']) {
            'motor' => 'bg-success',
            'mobil' => 'bg-warning text-dark',
            'lainnya' => 'bg-info',
            default => 'bg-secondary'
        };
        ?>
        <span class="badge <?= $badgeColor ?>">
            <?= ucfirst(htmlspecialchars($k['jenis_kendaraan'])) ?>
        </span>
    </td>
    <td><?= htmlspecialchars($k['warna']) ?></td>
    <td><?= htmlspecialchars($k['pemilik']) ?></td>
    <td>
        <small class="text-muted">
            <i class="bi bi-person-fill me-1"></i>
            <?= htmlspecialchars($k['nama_lengkap']) ?>
        </small>
    </td>
    <td class="text-center">
        <button class="btn btn-warning btn-sm" 
                data-bs-toggle="modal"
                data-bs-target="#edit<?= $k['id_kendaraan'] ?>">
            <i class="bi bi-pencil-square"></i> Edit
        </button>
        <a href="?hapus=<?= $k['id_kendaraan'] ?>"
           class="btn btn-danger btn-sm"
           onclick="return confirm('Hapus kendaraan <?= htmlspecialchars($k['plat_nomor']) ?>?')">
            <i class="bi bi-trash"></i> Hapus
        </a>
    </td>
</tr>

<!-- MODAL EDIT -->
<div class="modal fade" id="edit<?= $k['id_kendaraan'] ?>">
<div class="modal-dialog">
<div class="modal-content">
<form method="post">
<div class="modal-header bg-warning">
    <h5 class="modal-title">
        <i class="bi bi-pencil-square"></i> Edit Data Kendaraan
    </h5>
    <button class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
<input type="hidden" name="id_kendaraan" value="<?= $k['id_kendaraan'] ?>">

<div class="mb-3">
    <label class="form-label fw-semibold">Plat Nomor</label>
    <input name="plat_nomor" class="form-control" value="<?= htmlspecialchars($k['plat_nomor']) ?>" required>
</div>

<div class="mb-3">
    <label class="form-label fw-semibold">Jenis Kendaraan</label>
    <select name="jenis_kendaraan" class="form-select" required>
        <?php foreach($jenisKendaraanOptions as $jenis): ?>
            <?php if(!empty($jenis)): ?>
            <option value="<?= htmlspecialchars($jenis) ?>" <?= $jenis==$k['jenis_kendaraan']?'selected':'' ?>>
                <?= ucfirst(htmlspecialchars($jenis)) ?>
            </option>
            <?php endif; ?>
        <?php endforeach ?>
    </select>
</div>

<div class="mb-3">
    <label class="form-label fw-semibold">Warna</label>
    <input name="warna" class="form-control" value="<?= htmlspecialchars($k['warna']) ?>" required>
</div>

<div class="mb-3">
    <label class="form-label fw-semibold">Nama Pemilik</label>
    <input name="pemilik" class="form-control" value="<?= htmlspecialchars($k['pemilik']) ?>" required>
</div>

<div class="alert alert-info mb-0">
    <small>
        <i class="bi bi-info-circle-fill"></i> 
        Diinput oleh: <strong><?= htmlspecialchars($k['nama_lengkap']) ?></strong>
    </small>
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

<?php endwhile; else: ?>
<tr>
    <td colspan="7" class="text-center py-5">
        <i class="bi bi-inbox display-6 text-muted"></i>
        <p class="mt-2 text-muted">Belum ada data kendaraan terdaftar</p>
    </td>
</tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>
</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>