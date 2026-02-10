<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';
include '../config/log_helper.php';

/* AMBIL ENUM JENIS KENDARAAN */
$enum = mysqli_query($koneksi, "SHOW COLUMNS FROM tb_tarif LIKE 'jenis_kendaraan'");
$enumRow = mysqli_fetch_assoc($enum);
preg_match("/^enum\((.*)\)$/", $enumRow['Type'], $matches);
$enumValues = array_map(fn($v) => trim($v, "'"), explode(',', $matches[1]));

/* TAMBAH TARIF */
if (isset($_POST['tambah'])) {
    $jenis = trim($_POST['jenis_kendaraan']); // FIX #10
    $tarif = intval($_POST['tarif_per_jam']); // FIX #10
    
    // Cek duplicate // FIX #1
    $stmt = $koneksi->prepare("SELECT id_tarif FROM tb_tarif WHERE jenis_kendaraan=?");
    $stmt->bind_param("s", $jenis);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $_SESSION['error'] = "Tarif untuk jenis ini sudah ada!";
        $stmt->close();
        header("Location: tarif.php");
        exit;
    }
    $stmt->close();
    
    // Insert // FIX #1
    $stmt = $koneksi->prepare("INSERT INTO tb_tarif (jenis_kendaraan, tarif_per_jam) VALUES (?, ?)");
    $stmt->bind_param("si", $jenis, $tarif);
    
    if ($stmt->execute()) {
        simpan_log($koneksi, $_SESSION['id_user'], "Menambah tarif $jenis");
        $_SESSION['success'] = "Tarif berhasil ditambahkan!";
    } else {
        $_SESSION['error'] = "Gagal: " . $stmt->error;
    }
    $stmt->close();
    header("Location: tarif.php");
    exit;
}

/* UPDATE TARIF */
if (isset($_POST['update'])) {
    $id = intval($_POST['id_tarif']); // FIX #10
    $tarif = intval($_POST['tarif_per_jam']);
    
    // FIX #1: Prepared statement
    $stmt = $koneksi->prepare("UPDATE tb_tarif SET tarif_per_jam=? WHERE id_tarif=?");
    $stmt->bind_param("ii", $tarif, $id);
    $stmt->execute();
    $stmt->close();
    
    simpan_log($koneksi, $_SESSION['id_user'], "Mengedit tarif ID $id");
    header("Location: tarif.php");
    exit;
}

/* HAPUS TARIF */
if (isset($_GET['hapus'])) {
    $id = intval($_GET['hapus']); // FIX #1 & #10
    
    $stmt = $koneksi->prepare("DELETE FROM tb_tarif WHERE id_tarif=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    
    simpan_log($koneksi, $_SESSION['id_user'], "Menghapus tarif ID $id");
    header("Location: tarif.php");
    exit;
}

$data = mysqli_query($koneksi, "SELECT * FROM tb_tarif ORDER BY jenis_kendaraan ASC");

// Statistik Tarif
$totalTarif = mysqli_num_rows($data);
$tarifTertinggi = mysqli_fetch_assoc(
    mysqli_query($koneksi, "SELECT MAX(tarif_per_jam) as max FROM tb_tarif")
)['max'] ?? 0;
$tarifTerendah = mysqli_fetch_assoc(
    mysqli_query($koneksi, "SELECT MIN(tarif_per_jam) as min FROM tb_tarif")
)['min'] ?? 0;
$rataRataTarif = mysqli_fetch_assoc(
    mysqli_query($koneksi, "SELECT AVG(tarif_per_jam) as avg FROM tb_tarif")
)['avg'] ?? 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen Tarif Parkir</title>
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
        
        .tarif-badge {
            font-size: 1.1rem;
            padding: 0.5em 1em;
        }
    </style>
</head>

<body class="bg-light">

<div class="container-fluid my-4">

    <!-- HEADER -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-0">
                <i class="bi bi-cash-coin text-primary"></i> 
                Manajemen Tarif Parkir
            </h3>
            <small class="text-muted">
                Kelola tarif parkir untuk setiap jenis kendaraan
            </small>
        </div>

        <div class="d-flex gap-2">
            <a href="admin_dashboard.php" class="btn btn-secondary btn-sm">
                <i class="bi bi-arrow-left-circle"></i> Dashboard
            </a>
            <a href="/parkir/auth/logout.php"
               class="btn btn-outline-danger btn-sm"
               onclick="return confirm('Yakin ingin logout?')">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </div>

    <!-- NOTIFIKASI -->
    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle-fill"></i>
        <?php 
        if ($_GET['success'] == 'tambah') echo 'Tarif parkir berhasil ditambahkan!';
        elseif ($_GET['success'] == 'update') echo 'Tarif parkir berhasil diupdate!';
        elseif ($_GET['success'] == 'hapus') echo 'Tarif parkir berhasil dihapus!';
        ?>
        <button class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- STATISTIK CARDS -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card stat-card blue shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Total Jenis Tarif</h6>
                            <h2 class="mb-0 fw-bold text-primary"><?= $totalTarif ?></h2>
                            <small class="text-muted">Jenis kendaraan</small>
                        </div>
                        <div class="text-primary" style="font-size: 3rem; opacity: 0.3;">
                            <i class="bi bi-list-check"></i>
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
                            <h6 class="text-muted mb-2">Tarif Tertinggi</h6>
                            <h2 class="mb-0 fw-bold text-success">Rp <?= number_format($tarifTertinggi, 0, ',', '.') ?></h2>
                            <small class="text-muted">Per jam</small>
                        </div>
                        <div class="text-success" style="font-size: 3rem; opacity: 0.3;">
                            <i class="bi bi-arrow-up-circle"></i>
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
                            <h6 class="text-muted mb-2">Tarif Terendah</h6>
                            <h2 class="mb-0 fw-bold text-warning">Rp <?= number_format($tarifTerendah, 0, ',', '.') ?></h2>
                            <small class="text-muted">Per jam</small>
                        </div>
                        <div class="text-warning" style="font-size: 3rem; opacity: 0.3;">
                            <i class="bi bi-arrow-down-circle"></i>
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
                            <h6 class="text-muted mb-2">Rata-rata Tarif</h6>
                            <h2 class="mb-0 fw-bold" style="color: #6f42c1;">Rp <?= number_format($rataRataTarif, 0, ',', '.') ?></h2>
                            <small class="text-muted">Per jam</small>
                        </div>
                        <div style="font-size: 3rem; opacity: 0.3; color: #6f42c1;">
                            <i class="bi bi-calculator"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- FORM TAMBAH -->
    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-primary text-white fw-semibold">
            <i class="bi bi-plus-circle"></i> Tambah Tarif Parkir
        </div>
        <div class="card-body">
            <form method="post">
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Jenis Kendaraan</label>
                        <select name="jenis_kendaraan" class="form-select" required>
                            <option value="">Pilih Jenis Kendaraan</option>
                            <?php foreach ($enumValues as $v): ?>
                                <?php if (!empty($v)): ?>
                                <option value="<?= $v ?>">
                                    <i class="bi bi-car-front"></i> <?= ucfirst($v) ?>
                                </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Tarif per Jam (Rp)</label>
                        <input type="number" 
                               name="tarif_per_jam"
                               class="form-control"
                               placeholder="Contoh: 5000"
                               min="0"
                               step="1000"
                               required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">&nbsp;</label>
                        <button type="submit"
                                name="tambah"
                                class="btn btn-primary w-100">
                            <i class="bi bi-plus-lg"></i> Tambah
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- TABEL TARIF -->
    <div class="card shadow-sm">
        <div class="card-header bg-secondary text-white fw-semibold">
            <i class="bi bi-table"></i> Daftar Tarif Parkir
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-bordered mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width:5%" class="text-center">No</th>
                            <th style="width:30%">
                                <i class="bi bi-car-front-fill"></i> Jenis Kendaraan
                            </th>
                            <th style="width:35%">
                                <i class="bi bi-cash-stack"></i> Tarif per Jam
                            </th>
                            <th style="width:30%" class="text-center">
                                <i class="bi bi-gear-fill"></i> Aksi
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php 
                    mysqli_data_seek($data, 0);
                    if (mysqli_num_rows($data) > 0):
                        $no = 1;
                        while ($d = mysqli_fetch_assoc($data)): 
                    ?>
                        <tr>
                            <td class="text-center fw-bold"><?= $no++ ?></td>
                            <td>
                                <span class="badge bg-info tarif-badge">
                                    <i class="bi bi-truck"></i> <?= ucfirst($d['jenis_kendaraan']) ?>
                                </span>
                            </td>
                            <td>
                                <h5 class="mb-0 text-success"> 
                                    Rp <?= number_format($d['tarif_per_jam'], 0, ',', '.') ?>
                                </h5>
                                <small class="text-muted">per jam parkir</small>
                            </td>
                            <td class="text-center">
                                <button class="btn btn-warning btn-sm"
                                        data-bs-toggle="modal"
                                        data-bs-target="#edit<?= $d['id_tarif'] ?>">
                                    <i class="bi bi-pencil-square"></i> Edit
                                </button>
                                <a href="?hapus=<?= $d['id_tarif'] ?>"
                                   onclick="return confirm('Yakin hapus tarif <?= ucfirst($d['jenis_kendaraan']) ?>?')"
                                   class="btn btn-danger btn-sm">
                                    <i class="bi bi-trash3"></i> Hapus
                                </a>
                            </td>
                        </tr>

                        <!-- MODAL EDIT -->
                        <div class="modal fade" id="edit<?= $d['id_tarif'] ?>">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="post">
                                        <div class="modal-header bg-warning">
                                            <h5 class="modal-title">
                                                <i class="bi bi-pencil-square"></i> Edit Tarif Parkir
                                            </h5>
                                            <button type="button"
                                                    class="btn-close"
                                                    data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <input type="hidden"
                                                   name="id_tarif"
                                                   value="<?= $d['id_tarif'] ?>">
                                            
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">
                                                    <i class="bi bi-car-front"></i> Jenis Kendaraan
                                                </label>
                                                <select name="jenis_kendaraan"
                                                        class="form-select" required>
                                                    <?php foreach ($enumValues as $v): ?>
                                                        <?php if (!empty($v)): ?>
                                                        <option value="<?= $v ?>"
                                                            <?= $v == $d['jenis_kendaraan'] ? 'selected' : '' ?>>
                                                            <?= ucfirst($v) ?>
                                                        </option>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">
                                                    <i class="bi bi-cash-stack"></i> Tarif per Jam (Rp)
                                                </label>
                                                <input type="number"
                                                       name="tarif_per_jam"
                                                       class="form-control"
                                                       value="<?= $d['tarif_per_jam'] ?>"
                                                       min="0"
                                                       step="1000"
                                                       required>
                                            </div>

                                            <div class="alert alert-info mb-0">
                                                <small>
                                                    <i class="bi bi-info-circle"></i> 
                                                    Tarif akan diterapkan untuk semua transaksi baru
                                                </small>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button"
                                                    class="btn btn-secondary"
                                                    data-bs-dismiss="modal">
                                                <i class="bi bi-x-circle"></i> Batal
                                            </button>
                                            <button type="submit"
                                                    name="update"
                                                    class="btn btn-warning">
                                                <i class="bi bi-check-circle"></i> Simpan Perubahan
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php 
                        endwhile;
                    else:
                    ?>
                        <tr>
                            <td colspan="4" class="text-center py-5">
                                <i class="bi bi-inbox display-1 text-muted"></i>
                                <p class="mt-3 text-muted">
                                    Belum ada tarif parkir. Silakan tambahkan tarif baru.
                                </p>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- INFO PANEL -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-info">
                <div class="card-body">
                    <h6 class="card-title text-info">
                        <i class="bi bi-info-circle-fill"></i> Informasi Tarif Parkir
                    </h6>
                    <div class="row">
                        <div class="col-md-6">
                            <ul class="mb-0 small">
                                <li>Tarif parkir dihitung <strong>per jam</strong></li>
                                <li>Setiap jenis kendaraan hanya boleh memiliki <strong>satu tarif</strong></li>
                                <li>Perubahan tarif berlaku untuk <strong>transaksi baru</strong></li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul class="mb-0 small">
                                <li>Jenis kendaraan sesuai dengan <strong>enum di database</strong></li>
                                <li>Gunakan kelipatan 1000 untuk kemudahan perhitungan</li>
                                <li>Tarif tidak dapat dihapus jika ada transaksi terkait</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>