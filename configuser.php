<?php
$host = 'localhost';
$db = 'login_form';
$user = 'root'; // Ganti dengan username database Anda
$password = ''; // Ganti dengan password database Anda

// Membuat koneksi
$conn = new mysqli($host, $user, $password, $db);

// Cek koneksi
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>