<?php
// Atur zona waktu ke Asia/Jakarta (WIB)
date_default_timezone_set('Asia/Jakarta');

function simpan_log($koneksi, $id_user, $aktivitas) {
    // Menggunakan format datetime yang sesuai untuk MySQL
    $waktu = date('Y-m-d H:i:s');
    
    // Gunakan prepared statement agar lebih aman dari SQL Injection
    $stmt = $koneksi->prepare("INSERT INTO tb_log_aktivitas (id_user, aktivitas, waktu_aktivitas) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $id_user, $aktivitas, $waktu);
    $stmt->execute();
    $stmt->close();
}
?>