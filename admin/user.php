<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// FIX BUG #6: Set timezone
date_default_timezone_set('Asia/Jakarta');

include '../config/koneksi.php';
include '../config/log_helper.php';

$enum = mysqli_query($koneksi, "SHOW COLUMNS FROM tb_user LIKE 'role'");
$row  = mysqli_fetch_assoc($enum);
preg_match("/^enum\((.*)\)$/", $row['Type'], $matches);
$roles = array_map(fn($v) => trim($v, "'"), explode(',', $matches[1]));

if (isset($_POST['tambah'])) {
    // FIX BUG #10: Trim input
    $nama     = trim($_POST['nama_lengkap']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $role     = trim($_POST['role']);
    $status   = intval($_POST['status_aktif']);

    // FIX BUG #1: Gunakan prepared statement untuk cek username
    $stmt = $koneksi->prepare("SELECT id_user FROM tb_user WHERE username=?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $_SESSION['error'] = "Username '$username' sudah digunakan. Silakan gunakan username lain.";
        $stmt->close();
        header("Location: user.php");
        exit;
    }
    $stmt->close();

    // FIX BUG #1: Gunakan prepared statement untuk cek nama
    $stmt = $koneksi->prepare("SELECT id_user FROM tb_user WHERE nama_lengkap=?");
    $stmt->bind_param("s", $nama);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $_SESSION['error'] = "Nama lengkap '$nama' sudah terdaftar. Silakan gunakan nama yang berbeda.";
        $stmt->close();
        header("Location: user.php");
        exit;
    }
    $stmt->close();

    // FIX BUG #1: Insert dengan prepared statement
    $stmt = $koneksi->prepare("INSERT INTO tb_user (nama_lengkap, username, password, role, status_aktif) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssi", $nama, $username, $password, $role, $status);
    
    // FIX BUG #5: Error handling
    if ($stmt->execute()) {
        simpan_log($koneksi, $_SESSION['id_user'], "Menambah user ($username - $role)");
        $_SESSION['success'] = "User berhasil ditambahkan.";
    } else {
        $_SESSION['error'] = "Gagal menambahkan user: " . $stmt->error;
    }
    $stmt->close();
    header("Location: user.php");
    exit;
}

if (isset($_POST['update'])) {
    // FIX BUG #10: Trim dan sanitasi
    $id       = intval($_POST['id_user']);
    $nama     = trim($_POST['nama_lengkap']);
    $username = trim($_POST['username']);
    $role     = trim($_POST['role']);
    $status   = intval($_POST['status_aktif']);

    // FIX BUG #1: Prepared statement untuk cek username
    $stmt = $koneksi->prepare("SELECT id_user FROM tb_user WHERE username=? AND id_user != ?");
    $stmt->bind_param("si", $username, $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $_SESSION['error'] = "Username '$username' sudah digunakan oleh user lain.";
        $stmt->close();
        header("Location: user.php");
        exit;
    }
    $stmt->close();

    // FIX BUG #1: Prepared statement untuk cek nama
    $stmt = $koneksi->prepare("SELECT id_user FROM tb_user WHERE nama_lengkap=? AND id_user != ?");
    $stmt->bind_param("si", $nama, $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $_SESSION['error'] = "Nama lengkap '$nama' sudah digunakan oleh user lain.";
        $stmt->close();
        header("Location: user.php");
        exit;
    }
    $stmt->close();

    // FIX BUG #1: Update dengan prepared statement
    if (!empty($_POST['password'])) {
        $password = trim($_POST['password']);
        $stmt = $koneksi->prepare("UPDATE tb_user SET nama_lengkap=?, username=?, password=?, role=?, status_aktif=? WHERE id_user=?");
        $stmt->bind_param("ssssii", $nama, $username, $password, $role, $status, $id);
    } else {
        $stmt = $koneksi->prepare("UPDATE tb_user SET nama_lengkap=?, username=?, role=?, status_aktif=? WHERE id_user=?");
        $stmt->bind_param("sssii", $nama, $username, $role, $status, $id);
    }

    // FIX BUG #5: Error handling
    if ($stmt->execute()) {
        simpan_log($koneksi, $_SESSION['id_user'], "Mengedit user ID $id");
        $_SESSION['success'] = "User berhasil diperbarui.";
    } else {
        $_SESSION['error'] = "Gagal update user: " . $stmt->error;
    }
    $stmt->close();
    header("Location: user.php");
    exit;
}

if (isset($_GET['hapus'])) {
    // FIX BUG #1: Sanitasi input
    $id = intval($_GET['hapus']);
    
    // Cek role admin
    $stmt = $koneksi->prepare("SELECT role FROM tb_user WHERE id_user=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $cek = $result->fetch_assoc();
    $stmt->close();

    if ($cek && $cek['role'] === 'admin') {
        $_SESSION['error'] = "User dengan role admin tidak dapat dihapus!";
        header("Location: user.php");
        exit;
    }

    // FIX BUG #1: Delete dengan prepared statement
    $stmt = $koneksi->prepare("DELETE FROM tb_user WHERE id_user=?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        simpan_log($koneksi, $_SESSION['id_user'], "Menghapus user ID $id");
    }
    $stmt->close();
    header("Location: user.php");
    exit;
}

$data = mysqli_query($koneksi, "SELECT * FROM tb_user ORDER BY id_user ASC");

// FIX BUG #5: Error handling
if (!$data) {
    die("Error query: " . mysqli_error($koneksi));
}

// Statistik User
$totalUser = mysqli_num_rows($data);
$totalAdmin = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as total FROM tb_user WHERE role='admin'"))['total'];
$totalPetugas = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as total FROM tb_user WHERE role='petugas'"))['total'];
$totalOwner = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as total FROM tb_user WHERE role='owner'"))['total'];
$userAktif = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as total FROM tb_user WHERE status_aktif=1"))['total'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Manajemen User</title>
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
.stat-card.red { border-left-color: #dc3545; }
.stat-card.purple { border-left-color: #6f42c1; }
.stat-card.green { border-left-color: #198754; }
</style>
</head>

<body class="bg-light">
<div class="container-fluid my-4">

<!-- HEADER -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="mb-0">
            <i class="bi bi-people-fill text-primary"></i> 
            Manajemen User
        </h3>
        <small class="text-muted">Kelola data pengguna sistem parkir</small>
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
<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <?= htmlspecialchars($_SESSION['error']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle-fill"></i>
        <?= htmlspecialchars($_SESSION['success']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<!-- STATISTIK -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card stat-card blue shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Total User</h6>
                        <h2 class="mb-0 fw-bold text-primary"><?= $totalUser ?></h2>
                        <small class="text-muted">Pengguna</small>
                    </div>
                    <div class="text-primary" style="font-size: 3rem; opacity: 0.3;">
                        <i class="bi bi-people-fill"></i>
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
                        <h6 class="text-muted mb-2">Admin</h6>
                        <h2 class="mb-0 fw-bold text-danger"><?= $totalAdmin ?></h2>
                        <small class="text-muted">Administrator</small>
                    </div>
                    <div class="text-danger" style="font-size: 3rem; opacity: 0.3;">
                        <i class="bi bi-shield-fill-check"></i>
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
                        <h6 class="text-muted mb-2">Petugas</h6>
                        <h2 class="mb-0 fw-bold" style="color: #6f42c1;"><?= $totalPetugas ?></h2>
                        <small class="text-muted">Operator</small>
                    </div>
                    <div style="font-size: 3rem; opacity: 0.3; color: #6f42c1;">
                        <i class="bi bi-person-badge-fill"></i>
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
                        <h6 class="text-muted mb-2">User Aktif</h6>
                        <h2 class="mb-0 fw-bold text-success"><?= $userAktif ?></h2>
                        <small class="text-muted">Aktif</small>
                    </div>
                    <div class="text-success" style="font-size: 3rem; opacity: 0.3;">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- FORM TAMBAH -->
<div class="card mb-4 shadow-sm">
<div class="card-header bg-primary text-white fw-semibold">
    <i class="bi bi-plus-circle"></i> Tambah User Baru
</div>
<div class="card-body">
<form method="post">
<div class="row g-3">
    <div class="col-md-3">
        <label class="form-label">Nama Lengkap</label>
        <input name="nama_lengkap" class="form-control" placeholder="Nama Lengkap" required>
    </div>
    <div class="col-md-2">
        <label class="form-label">Username</label>
        <input name="username" class="form-control" placeholder="Username" required>
    </div>
    <div class="col-md-2">
        <label class="form-label">Password</label>
        <input name="password" type="password" class="form-control" placeholder="Password" required>
    </div>
    <div class="col-md-2">
        <label class="form-label">Role</label>
        <select name="role" class="form-select" required>
            <option value="">Pilih Role</option>
            <?php foreach ($roles as $r): ?>
                <?php if($r !== ''): ?>
                <option value="<?= htmlspecialchars($r) ?>"><?= ucfirst(htmlspecialchars($r)) ?></option>
                <?php endif; ?>
            <?php endforeach ?>
        </select>
    </div>
    <div class="col-md-2">
        <label class="form-label">Status</label>
        <select name="status_aktif" class="form-select" required>
            <option value="1">Aktif</option>
            <option value="0">Nonaktif</option>
        </select>
    </div>
    <div class="col-md-1 d-flex align-items-end">
        <button name="tambah" class="btn btn-primary w-100">
            <i class="bi bi-plus-lg"></i> Tambah
        </button>
    </div>
</div>
</form>
</div>
</div>

<!-- TABEL USER -->
<div class="card shadow-sm">
<div class="card-header bg-secondary text-white fw-semibold">
    <i class="bi bi-table"></i> Daftar User
</div>
<div class="card-body p-0">
<div class="table-responsive">
<table class="table table-hover table-striped mb-0">
<thead class="table-light">
<tr>
    <th style="width: 5%" class="text-center">No</th>
    <th>Nama Lengkap</th>
    <th>Username</th>
    <th style="width: 12%">Role</th>
    <th style="width: 10%" class="text-center">Status</th>
    <th style="width: 15%" class="text-center">Aksi</th>
</tr>
</thead>
<tbody>
<?php 
mysqli_data_seek($data, 0);
$no=1; 
while($u=mysqli_fetch_assoc($data)): 
?>
<tr>
<td class="text-center"><?= $no++ ?></td>
<td>
    <strong><?= htmlspecialchars($u['nama_lengkap']) ?></strong>
</td>
<td><?= htmlspecialchars($u['username']) ?></td>
<td>
    <?php if($u['role'] == 'admin'): ?>
        <span class="badge bg-danger"><i class="bi bi-shield-fill-check"></i> Admin</span>
    <?php elseif($u['role'] == 'petugas'): ?>
        <span class="badge bg-info"><i class="bi bi-person-badge"></i> Petugas</span>
    <?php else: ?>
        <span class="badge bg-secondary"><i class="bi bi-person"></i> <?= ucfirst(htmlspecialchars($u['role'])) ?></span>
    <?php endif; ?>
</td>
<td class="text-center">
    <?= $u['status_aktif']
        ? '<span class="badge bg-success">Aktif</span>'
        : '<span class="badge bg-secondary">Nonaktif</span>' ?>
</td>
<td class="text-center">
<button class="btn btn-warning btn-sm"
        data-bs-toggle="modal"
        data-bs-target="#edit<?= $u['id_user'] ?>">
    <i class="bi bi-pencil-square"></i> Edit
</button>

<?php if ($u['role'] !== 'admin'): ?>
<a href="?hapus=<?= $u['id_user'] ?>"
   class="btn btn-danger btn-sm"
   onclick="return confirm('Hapus user <?= htmlspecialchars($u['nama_lengkap']) ?>?')">
   <i class="bi bi-trash"></i> Hapus
</a>
<?php else: ?>
<button class="btn btn-secondary btn-sm" disabled title="Admin tidak dapat dihapus">
    <i class="bi bi-lock"></i> Protected
</button>
<?php endif; ?>
</td>
</tr>

<!-- MODAL EDIT USER -->
<div class="modal fade" id="edit<?= $u['id_user'] ?>">
<div class="modal-dialog">
<div class="modal-content">
<form method="post">
<div class="modal-header bg-warning">
<h5 class="modal-title"><i class="bi bi-pencil-square"></i> Edit User</h5>
<button class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
<input type="hidden" name="id_user" value="<?= $u['id_user'] ?>">

<div class="mb-3">
    <label class="form-label">Nama Lengkap</label>
    <input name="nama_lengkap" class="form-control"
           value="<?= htmlspecialchars($u['nama_lengkap']) ?>" required>
</div>

<div class="mb-3">
    <label class="form-label">Username</label>
    <input name="username" class="form-control"
           value="<?= htmlspecialchars($u['username']) ?>" required>
</div>

<div class="mb-3">
    <label class="form-label">Password</label>
    <input name="password" type="password" class="form-control"
           placeholder="Kosongkan jika tidak ingin mengubah password">
    <small class="text-muted">Isi hanya jika ingin mengubah password</small>
</div>

<div class="mb-3">
    <label class="form-label">Role</label>
    <select name="role" class="form-select">
    <?php foreach ($roles as $r): ?>
        <?php if($r !== ''): ?>
        <option value="<?= htmlspecialchars($r) ?>" <?= $r==$u['role']?'selected':'' ?>>
        <?= ucfirst(htmlspecialchars($r)) ?>
        </option>
        <?php endif; ?>
    <?php endforeach ?>
    </select>
</div>

<div class="mb-3">
    <label class="form-label">Status</label>
    <select name="status_aktif" class="form-select">
        <option value="1" <?= $u['status_aktif']==1?'selected':'' ?>>Aktif</option>
        <option value="0" <?= $u['status_aktif']==0?'selected':'' ?>>Nonaktif</option>
    </select>
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

<?php endwhile ?>

<?php if(mysqli_num_rows($data) == 0): ?>
<tr>
    <td colspan="6" class="text-center text-muted py-4">
        Belum ada data user
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
            <i class="bi bi-info-circle-fill"></i> Informasi Manajemen User
        </h6>
        <div class="row">
            <div class="col-md-6">
                <ul class="mb-0 small">
                    <li>User dengan role <strong>Admin</strong> memiliki akses penuh ke sistem</li>
                    <li>User dengan role <strong>Petugas</strong> hanya dapat mengelola transaksi parkir</li>
                    <li>User yang statusnya <strong>Nonaktif</strong> tidak dapat login ke sistem</li>
                    <li><strong class="text-danger">Username harus unik</strong> - tidak boleh sama dengan user lain</li>
                </ul>
            </div>
            <div class="col-md-6">
                <ul class="mb-0 small">
                    <li>Password akan terenkripsi otomatis oleh sistem</li>
                    <li>User dengan role <strong>Admin</strong> tidak dapat dihapus untuk keamanan</li>
                    <li>Semua aktivitas akan tercatat dalam log sistem</li>
                    <li><strong class="text-danger">Nama Lengkap harus unik</strong> - tidak boleh sama dengan user lain</li>
                </ul>
            </div>
        </div>
    </div>
</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>