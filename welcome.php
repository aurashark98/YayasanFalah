<?php
session_start();

// Cek apakah pengguna sudah login
if (!isset($_SESSION['username'])) {
    header("Location: login.php"); // Arahkan ke halaman login jika belum login
    exit;
}

// Arahkan ke halaman awal setelah login
header("Location: index.php");
exit();
?>