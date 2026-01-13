<?php
session_start();
require_once 'config.php'; // Pastikan file config.php Anda ada dan berisi koneksi DB

header('Content-Type: application/json'); // Mengatur header untuk respons JSON

// Cek apakah pengguna sudah login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Anda harus login untuk melakukan adopsi non-finansial.']);
    exit;
}

// Cek apakah child_id diterima dari request POST
if (!isset($_POST['child_id']) || empty($_POST['child_id'])) {
    echo json_encode(['success' => false, 'message' => 'ID anak tidak ditemukan.']);
    exit;
}

$child_id = intval($_POST['child_id']);
$donatur_id = $_SESSION['user_id'];

// Koneksi ke database (gunakan kembali kode koneksi dari profilyatim.php atau config.php)
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "yayasan_amal";

$conn = new mysqli($servername, $username, $password, $dbname);

// Cek koneksi
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Koneksi database gagal: ' . $conn->connect_error]);
    exit;
}

// Langkah 1: Periksa apakah donatur sudah mengadopsi anak ini
$stmt_check = $conn->prepare("SELECT COUNT(*) FROM adopsi_non_finansial WHERE donatur_id = ? AND profile_ankytm_id = ? AND status = 'aktif'");
if (!$stmt_check) {
    echo json_encode(['success' => false, 'message' => 'Kesalahan persiapan statement (check): ' . $conn->error]);
    $conn->close();
    exit;
}
$stmt_check->bind_param("ii", $donatur_id, $child_id);
$stmt_check->execute();
$stmt_check->bind_result($count);
$stmt_check->fetch();
$stmt_check->close();

if ($count > 0) {
    echo json_encode(['success' => false, 'message' => 'Anda sudah mengadopsi anak ini secara non-finansial.']);
    $conn->close();
    exit;
}

// Langkah 2: Masukkan data adopsi baru ke database
$stmt_insert = $conn->prepare("INSERT INTO adopsi_non_finansial (donatur_id, profile_ankytm_id, status) VALUES (?, ?, 'aktif')");
if (!$stmt_insert) {
    echo json_encode(['success' => false, 'message' => 'Kesalahan persiapan statement (insert): ' . $conn->error]);
    $conn->close();
    exit;
}
$stmt_insert->bind_param("ii", $donatur_id, $child_id);

if ($stmt_insert->execute()) {
    $new_adopsi_id = $conn->insert_id; // Ambil ID yang baru dibuat
    echo json_encode(['success' => true, 'message' => 'Anda berhasil mengadopsi anak ini secara non-finansial!', 'adopsi_id' => $new_adopsi_id]);
} else {
    // Tangani kesalahan unik (jika ada, meskipun sudah dicek sebelumnya)
    if ($conn->errno == 1062) { // Kode error MySQL untuk duplicate entry for key 'PRIMARY' atau UNIQUE constraint
        echo json_encode(['success' => false, 'message' => 'Anda sudah mengadopsi anak ini secara non-finansial.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan data adopsi: ' . $stmt_insert->error]);
    }
}

$stmt_insert->close();
$conn->close();
?>