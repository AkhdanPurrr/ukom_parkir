<?php
session_start();
include '../config/koneksi.php';
include '../config/log_helper.php';

simpan_log($koneksi, $_SESSION['id_user'], 'Logout dari sistem');


session_destroy();
header("Location: login.php");
exit;

