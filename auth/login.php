<?php
session_start();

// Set timezone
date_default_timezone_set('Asia/Jakarta');

$conn = new mysqli("localhost", "root", "", "db_parkir");
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

$error = "";
if (isset($_POST['login'])) {
    // Sanitasi input
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $stmt = $conn->prepare("SELECT * FROM tb_user WHERE username = ? AND status_aktif = 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if ($password === $user['password']) {
            // FIX BUG #3: Regenerate session ID untuk mencegah session fixation
            session_regenerate_id(true);
            
            $_SESSION['id_user'] = $user['id_user'];
            $_SESSION['nama'] = $user['nama_lengkap'];
            $_SESSION['role'] = $user['role'];

            $log = $conn->prepare("INSERT INTO tb_log_aktivitas (id_user, aktivitas, waktu_aktivitas) VALUES (?, ?, NOW())");
            $aktivitas = "Login ke sistem";
            $log->bind_param("is", $user['id_user'], $aktivitas);
            $log->execute();

            if ($user['role'] == 'admin') {
                header("Location: ../admin/admin_dashboard.php");
            } elseif ($user['role'] == 'petugas') {
                header("Location: ../petugas/petugas_dashboard.php");
            } else {
                header("Location: ../owner/owner_dashboard.php");
            }
            exit;
        } else {
            $error = "Password salah";
        }
    } else {
        $error = "Username tidak ditemukan atau akun tidak aktif";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login - Sistem Parkir</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg,rgb(200, 221, 199) 0%,rgb(84, 159, 209) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            max-width: 900px;
            width: 100%;
            display: flex;
            min-height: 500px;
        }

        .login-left {
            flex: 1;
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            padding: 60px 40px;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .login-left::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: pulse 15s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .login-left-content {
            position: relative;
            z-index: 1;
        }

        .logo-icon {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            margin-bottom: 30px;
            backdrop-filter: blur(10px);
        }

        .login-left h2 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 15px;
            line-height: 1.2;
        }

        .login-left p {
            font-size: 16px;
            opacity: 0.9;
            line-height: 1.6;
        }

        .login-right {
            flex: 1;
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .login-header {
            margin-bottom: 40px;
        }

        .login-header h3 {
            font-size: 28px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .login-header p {
            color: #7f8c8d;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .input-group-custom {
            position: relative;
        }

        .input-group-custom i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #7f8c8d;
            font-size: 18px;
            z-index: 10;
        }

        .form-control {
            height: 50px;
            padding-left: 45px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.1);
        }

        .btn-login {
            width: 100%;
            height: 50px;
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            border: none;
            border-radius: 10px;
            color: white;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.4);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .alert {
            border-radius: 10px;
            border: none;
            padding: 15px 20px;
            margin-bottom: 25px;
            font-size: 14px;
        }

        .alert-danger {
            background: #fee;
            color: #c33;
        }

        .info-box {
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            border-left: 4px solid #3498db;
        }

        .info-box h6 {
            font-size: 14px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .info-box ul {
            margin: 0;
            padding-left: 20px;
            font-size: 13px;
            color: #7f8c8d;
        }

        .info-box li {
            margin-bottom: 5px;
        }

        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
            }

            .login-left {
                padding: 40px 30px;
                min-height: 250px;
            }

            .login-right {
                padding: 40px 30px;
            }

            .login-left h2 {
                font-size: 24px;
            }

            .login-header h3 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>

<div class="login-container">
    <!-- LEFT SIDE -->
    <div class="login-left">
        <div class="login-left-content">
            <div class="logo-icon">
                <i class="bi bi-car-front-fill"></i>
            </div>
            <h2>Sistem Manajemen<br>Parkir</h2>
            <p>Kelola area parkir Anda dengan mudah, efisien, dan terorganisir. Monitoring real-time untuk pengalaman terbaik.</p>
        </div>
    </div>

    <!-- RIGHT SIDE -->
    <div class="login-right">
        <div class="login-header">
            <h3>Selamat Datang</h3>
            <p>Silakan masuk untuk melanjutkan</p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-circle-fill me-2"></i>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label class="form-label">Username</label>
                <div class="input-group-custom">
                    <i class="bi bi-person-fill"></i>
                    <input type="text" 
                           name="username" 
                           class="form-control" 
                           placeholder="Masukkan username Anda"
                           required 
                           autofocus>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Password</label>
                <div class="input-group-custom">
                    <i class="bi bi-lock-fill"></i>
                    <input type="password" 
                           name="password" 
                           class="form-control" 
                           placeholder="Masukkan password Anda"
                           required>
                </div>
            </div>

            <button type="submit" name="login" class="btn-login">
                <i class="bi bi-box-arrow-in-right me-2"></i>
                Masuk
            </button>
        </form>

        <div class="info-box">
            <h6><i class="bi bi-info-circle me-2"></i>Akses Sistem</h6>
            <ul>
                <li><strong>Admin:</strong> Kelola user, tarif, area & kendaraan</li>
                <li><strong>Petugas:</strong> Transaksi parkir & riwayat</li>
                <li><strong>Owner:</strong> Rekapitulasi & laporan</li>
            </ul>
        </div>
    </div>
</div>

</body>
</html>