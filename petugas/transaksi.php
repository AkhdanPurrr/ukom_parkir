<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'petugas') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';
include '../config/log_helper.php';

// Handler untuk skip print atau clear session setelah print
if (isset($_GET['skip_print']) || isset($_GET['after_print'])) {
    unset($_SESSION['cetak_struk']);
    unset($_SESSION['success']);
    header("Location: transaksi.php");
    exit;
}

// Log aktivitas akses halaman
if (!isset($_SESSION['transaksi_logged'])) {
    simpan_log($koneksi, $_SESSION['id_user'], 'Mengakses halaman Transaksi Parkir');
    $_SESSION['transaksi_logged'] = true;
}

// Ambil enum jenis kendaraan dari tb_tarif
$enum = mysqli_query($koneksi, "SHOW COLUMNS FROM tb_tarif LIKE 'jenis_kendaraan'");
$enumRow = mysqli_fetch_assoc($enum);
preg_match("/^enum\((.*)\)$/", $enumRow['Type'], $matches);
$jenisKendaraanOptions = array_map(fn($v) => trim($v, "'"), explode(',', $matches[1]));

// Ambil data area parkir
$areas = mysqli_query($koneksi, "SELECT * FROM tb_area_parkir WHERE (kapasitas - terisi) > 0 ORDER BY nama_area ASC");

/* ===== PROSES KENDARAAN MASUK ===== */
if (isset($_POST['masuk'])) {
    $plat = strtoupper(trim($_POST['plat_nomor']));
    $jenis = $_POST['jenis_kendaraan'];
    $warna = !empty($_POST['warna']) ? $_POST['warna'] : '-';
    $pemilik = !empty($_POST['pemilik']) ? $_POST['pemilik'] : '-';
    $idArea = $_POST['id_area'];
    $idUser = $_SESSION['id_user'];
    
    // Cek apakah plat nomor sudah ada dan masih parkir
    $cekPlat = mysqli_query($koneksi, "
        SELECT t.id_parkir FROM tb_transaksi t
        JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
        WHERE k.plat_nomor = '$plat' AND t.status = 'masuk'
    ");
    
    if (mysqli_num_rows($cekPlat) > 0) {
        $_SESSION['error'] = "Kendaraan dengan plat nomor $plat masih parkir!";
        header("Location: transaksi.php");
        exit;
    }
    
    $tarif = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT id_tarif FROM tb_tarif WHERE jenis_kendaraan = '$jenis'"));
    
    // Cek apakah kendaraan sudah pernah terdaftar
    $cekKendaraan = mysqli_query($koneksi, "SELECT id_kendaraan FROM tb_kendaraan WHERE plat_nomor = '$plat'");
    if (mysqli_num_rows($cekKendaraan) > 0) {
        $kendaraan = mysqli_fetch_assoc($cekKendaraan);
        $idKendaraan = $kendaraan['id_kendaraan'];
        mysqli_query($koneksi, "UPDATE tb_kendaraan SET jenis_kendaraan = '$jenis', warna = '$warna', pemilik = '$pemilik' WHERE id_kendaraan = '$idKendaraan'");
    } else {
        mysqli_query($koneksi, "INSERT INTO tb_kendaraan (plat_nomor, jenis_kendaraan, warna, pemilik, id_user) VALUES ('$plat', '$jenis', '$warna', '$pemilik', '$idUser')");
        $idKendaraan = mysqli_insert_id($koneksi);
    }
    
    $waktuMasuk = date('Y-m-d H:i:s');
    mysqli_query($koneksi, "INSERT INTO tb_transaksi (id_kendaraan, waktu_masuk, waktu_keluar, id_tarif, durasi_jam, biaya_total, status, id_user, id_area)
                            VALUES ('$idKendaraan', '$waktuMasuk', '$waktuMasuk', '{$tarif['id_tarif']}', 0, 0, 'masuk', '$idUser', '$idArea')");
    
    mysqli_query($koneksi, "UPDATE tb_area_parkir SET terisi = terisi + 1 WHERE id_area = '$idArea'");
    
    $areaInfo = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT nama_area FROM tb_area_parkir WHERE id_area='$idArea'"));
    simpan_log($koneksi, $idUser, "Mencatat kendaraan masuk: $plat ($jenis) di {$areaInfo['nama_area']}");
    
    $_SESSION['success'] = "Kendaraan $plat berhasil masuk parkir!";
    header("Location: transaksi.php");
    exit;
}

/* ===== PROSES KENDARAAN KELUAR (MANUAL) ===== */
if (isset($_POST['keluar'])) {
    $idParkir = intval($_POST['id_parkir']);
    $waktuKeluar = date('Y-m-d H:i:s');
    
    $stmt = $koneksi->prepare("
        SELECT t.*, tar.tarif_per_jam, k.plat_nomor, k.jenis_kendaraan, k.warna, a.nama_area
        FROM tb_transaksi t
        JOIN tb_tarif tar ON t.id_tarif = tar.id_tarif
        JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
        JOIN tb_area_parkir a ON t.id_area = a.id_area
        WHERE t.id_parkir = ? AND t.status = 'masuk' LIMIT 1
    ");
    $stmt->bind_param("i", $idParkir);
    $stmt->execute();
    $transaksi = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$transaksi) {
        $_SESSION['error'] = "ID Parkir #$idParkir tidak ditemukan atau sudah keluar!";
        header("Location: transaksi.php");
        exit;
    }
    
    // Hitung durasi dan biaya
    $waktuMasuk = strtotime($transaksi['waktu_masuk']);
    $selisihDetik = strtotime($waktuKeluar) - $waktuMasuk;
    $jam = floor($selisihDetik / 3600);
    $menitSisa = floor(($selisihDetik % 3600) / 60);
    $durasiJam = ($menitSisa > 10) ? $jam + 1 : $jam;
    if ($durasiJam < 1) $durasiJam = 1;
    $biayaTotal = $durasiJam * $transaksi['tarif_per_jam'];
    
    $updateStmt = $koneksi->prepare("UPDATE tb_transaksi SET waktu_keluar = ?, durasi_jam = ?, biaya_total = ?, status = 'keluar' WHERE id_parkir = ?");
    $updateStmt->bind_param("siii", $waktuKeluar, $durasiJam, $biayaTotal, $idParkir);
    
    if ($updateStmt->execute()) {
        mysqli_query($koneksi, "UPDATE tb_area_parkir SET terisi = terisi - 1 WHERE id_area = '{$transaksi['id_area']}'");
        
        // Simpan data untuk STRUK KELUAR
        $_SESSION['cetak_struk'] = [
            'id_parkir' => $idParkir,
            'plat_nomor' => $transaksi['plat_nomor'],
            'jenis_kendaraan' => $transaksi['jenis_kendaraan'],
            'warna' => $transaksi['warna'],
            'area' => $transaksi['nama_area'],
            'waktu_masuk' => $transaksi['waktu_masuk'],
            'waktu_keluar' => $waktuKeluar,
            'durasi' => $durasiJam,
            'biaya' => $biayaTotal,
            'petugas' => $_SESSION['nama']
        ];
        
        simpan_log($koneksi, $_SESSION['id_user'], "Kendaraan keluar: {$transaksi['plat_nomor']} | Biaya: Rp " . number_format($biayaTotal));
        $_SESSION['success'] = "Kendaraan {$transaksi['plat_nomor']} berhasil keluar.";
    }
    header("Location: transaksi.php");
    exit;
}

/* ===== DATA KENDARAAN PARKIR & STATISTIK ===== */
$kendaraanParkir = mysqli_query($koneksi, "SELECT t.*, k.plat_nomor, k.jenis_kendaraan, a.nama_area, tar.tarif_per_jam FROM tb_transaksi t JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan JOIN tb_area_parkir a ON t.id_area = a.id_area JOIN tb_tarif tar ON t.id_tarif = tar.id_tarif WHERE t.status = 'masuk' ORDER BY t.waktu_masuk DESC");
$totalParkir = mysqli_num_rows($kendaraanParkir);
$totalMotor = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as t FROM tb_transaksi t JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan WHERE t.status = 'masuk' AND k.jenis_kendaraan = 'motor'"))['t'] ?? 0;
$totalMobil = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as t FROM tb_transaksi t JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan WHERE t.status = 'masuk' AND k.jenis_kendaraan = 'mobil'"))['t'] ?? 0;
$struk = $_SESSION['cetak_struk'] ?? null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Transaksi Parkir</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .stat-card { border-left: 4px solid; transition: 0.2s; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .stat-card.blue { border-left-color: #0d6efd; }
        .stat-card.green { border-left-color: #198754; }
        .stat-card.orange { border-left-color: #fd7e14; }
    </style>
</head>
<body class="bg-light">
<div class="container-fluid my-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-0"><i class="bi bi-arrow-left-right text-primary"></i> Transaksi Parkir</h3>
            <small class="text-muted">Kelola operasional kendaraan masuk dan keluar</small>
        </div>
        <div class="d-flex gap-2">
            <a href="riwayat.php" class="btn btn-info btn-sm"><i class="bi bi-clock-history"></i> Riwayat</a>
            <a href="petugas_dashboard.php" class="btn btn-secondary btn-sm"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="/parkir/auth/logout.php" class="btn btn-outline-danger btn-sm" onclick="return confirm('Logout?')"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle-fill me-2"></i><?= $_SESSION['success'] ?><button class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php unset($_SESSION['success']); endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= $_SESSION['error'] ?><button class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php unset($_SESSION['error']); endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card stat-card blue shadow-sm"><div class="card-body d-flex justify-content-between">
                <div><h6 class="text-muted">Total Parkir</h6><h2 class="fw-bold text-primary"><?= $totalParkir ?></h2></div>
                <div class="text-primary opacity-25" style="font-size: 2.5rem;"><i class="bi bi-car-front-fill"></i></div>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card green shadow-sm"><div class="card-body d-flex justify-content-between">
                <div><h6 class="text-muted">Motor</h6><h2 class="fw-bold text-success"><?= $totalMotor ?></h2></div>
                <div class="text-success opacity-25" style="font-size: 2.5rem;"><i class="bi bi-bicycle"></i></div>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card orange shadow-sm"><div class="card-body d-flex justify-content-between">
                <div><h6 class="text-muted">Mobil</h6><h2 class="fw-bold text-warning"><?= $totalMobil ?></h2></div>
                <div class="text-warning opacity-25" style="font-size: 2.5rem;"><i class="bi bi-truck-front-fill"></i></div>
            </div></div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-success text-white fw-bold"><i class="bi bi-arrow-down-circle"></i> Kendaraan Masuk</div>
                <div class="card-body">
                    <form method="post">
                        <div class="mb-3"><label class="form-label fw-bold">Plat Nomor</label><input name="plat_nomor" class="form-control text-uppercase" placeholder="B 1234 XYZ" required autofocus></div>
                        <div class="mb-3"><label class="form-label fw-bold">Jenis Kendaraan</label>
                            <select name="jenis_kendaraan" class="form-select" required>
                                <option value="">Pilih Jenis</option>
                                <?php foreach($jenisKendaraanOptions as $opt): if(!empty($opt)): ?>
                                <option value="<?= $opt ?>"><?= ucfirst($opt) ?></option>
                                <?php endif; endforeach; ?>
                            </select>
                        </div>
                        <div class="row mb-3">
                            <div class="col"><label class="form-label fw-bold">Warna</label><input name="warna" class="form-control"></div>
                            <div class="col"><label class="form-label fw-bold">Pemilik</label><input name="pemilik" class="form-control"></div>
                        </div>
                        <div class="mb-3"><label class="form-label fw-bold">Area Parkir</label>
                            <select name="id_area" class="form-select" required>
                                <?php mysqli_data_seek($areas, 0); while($a = mysqli_fetch_assoc($areas)): ?>
                                <option value="<?= $a['id_area'] ?>"><?= $a['nama_area'] ?> (Sisa: <?= $a['kapasitas']-$a['terisi'] ?>)</option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <button name="masuk" class="btn btn-success w-100 fw-bold"><i class="bi bi-plus-circle"></i> Masukkan</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-danger text-white fw-bold"><i class="bi bi-box-arrow-right"></i> Proses Kendaraan Keluar</div>
                <div class="card-body d-flex flex-column justify-content-center">
                    <div class="text-center mb-4">
                        <i class="bi bi-keyboard text-danger display-4"></i>
                        <h5>Input ID Parkir untuk Checkout</h5>
                        <p class="text-muted small">ID Parkir dapat dilihat pada struk masuk atau tabel di bawah.</p>
                    </div>
                    <form method="post" class="row g-2 px-md-5">
                        <div class="col-8"><input name="id_parkir" type="number" class="form-control form-control-lg" placeholder="Contoh: 12" required></div>
                        <div class="col-4"><button name="keluar" class="btn btn-danger btn-lg w-100 fw-bold" onclick="return confirm('Keluarkan kendaraan?')">Keluar</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mt-4 border-0">
        <div class="card-header bg-primary text-white fw-bold"><i class="bi bi-list-ul"></i> Daftar Kendaraan Aktif</div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="text-center">ID Parkir</th>
                        <th>Plat Nomor</th>
                        <th>Jenis</th>
                        <th>Area</th>
                        <th>Waktu Masuk</th>
                        <th class="text-end">Tarif/Jam</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($totalParkir > 0): while($k = mysqli_fetch_assoc($kendaraanParkir)): ?>
                    <tr>
                        <td class="text-center"><span class="badge bg-dark">#<?= str_pad($k['id_parkir'], 5, '0', STR_PAD_LEFT) ?></span></td>
                        <td><strong><?= $k['plat_nomor'] ?></strong></td>
                        <td><span class="badge bg-secondary"><?= ucfirst($k['jenis_kendaraan']) ?></span></td>
                        <td><i class="bi bi-geo-alt"></i> <?= $k['nama_area'] ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($k['waktu_masuk'])) ?></td>
                        <td class="text-end">Rp <?= number_format($k['tarif_per_jam']) ?></td>
                    </tr>
                <?php endwhile; else: ?>
                    <tr><td colspan="6" class="text-center py-5 text-muted">Tidak ada kendaraan di lokasi.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($struk): ?>
<div class="modal fade" id="modalCetakStruk" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg">
            <div class="modal-header bg-success text-white"><h5 class="modal-title"><i class="bi bi-printer"></i> Struk Pembayaran Parkir</h5></div>
            <div class="modal-body bg-light">
                <div id="strukPrintArea" class="bg-white p-4 shadow-sm mx-auto" style="width: 300px; font-family: monospace;">
                    <div class="text-center border-bottom pb-2 mb-2">
                        <h6 class="mb-0 fw-bold">SISTEM PARKIR DIGITAL</h6>
                        <small>Struk Bukti Pembayaran</small>
                    </div>
                    <table class="w-100 small">
                        <tr><td>ID</td><td>: #<?= str_pad($struk['id_parkir'], 5, '0', STR_PAD_LEFT) ?></td></tr>
                        <tr><td>Plat</td><td>: <?= $struk['plat_nomor'] ?></td></tr>
                        <tr><td>Masuk</td><td>: <?= date('d/m/Y H:i', strtotime($struk['waktu_masuk'])) ?></td></tr>
                        <tr><td>Keluar</td><td>: <?= date('d/m/Y H:i', strtotime($struk['waktu_keluar'])) ?></td></tr>
                        <tr><td colspan="2"><hr class="my-1"></td></tr>
                        <tr><td>Durasi</td><td>: <?= $struk['durasi'] ?> Jam</td></tr>
                        <tr class="fw-bold"><td>TOTAL</td><td>: Rp <?= number_format($struk['biaya']) ?></td></tr>
                    </table>
                    <div class="text-center mt-3 pt-2 border-top small">Terima Kasih Atas Kunjungan Anda</div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="window.location.href='transaksi.php?skip_print=1'">Tutup</button>
                <button class="btn btn-success" onclick="printStruk()"><i class="bi bi-printer-fill"></i> Cetak Struk</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    <?php if ($struk): ?>
    document.addEventListener('DOMContentLoaded', function() {
        new bootstrap.Modal(document.getElementById('modalCetakStruk')).show();
    });
    <?php endif; ?>

    function printStruk() {
        const content = document.getElementById('strukPrintArea').innerHTML;
        const win = window.open('', '', 'width=400,height=600');
        win.document.write('<html><head><title>Print Struk</title><style>body{font-family:monospace;padding:20px;width:300px} .text-center{text-align:center} .w-100{width:100%} .fw-bold{font-weight:bold} .border-bottom{border-bottom:1px dashed #000} .border-top{border-top:1px dashed #000}</style></head><body>');
        win.document.write(content);
        win.document.write('</body></html>');
        win.document.close();
        win.focus();
        setTimeout(() => { win.print(); win.close(); window.location.href='transaksi.php?after_print=1'; }, 500);
    }
</script>
</body>
</html>