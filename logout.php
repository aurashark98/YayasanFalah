<?php
session_start(); // Memulai session
session_destroy(); // Menghancurkan session
header("Location:index.php"); // Arahkan kembali ke halaman login
exit();
?>