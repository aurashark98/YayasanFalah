<?php
// Pastikan tidak ada output HTML sebelum header
ob_start();

// Koneksi database (sesuaikan dengan config.php jika ada)
$host = "localhost";
$user = "root"; // sesuaikan
$pass = "";     // sesuaikan
$db = "yayasan_amal"; // Pastikan ini sesuai dengan nama database Anda

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Ambil nama tabel dari parameter GET
$tabel = $_GET['tabel'] ?? '';

// Pastikan tabel yang diminta valid dan ada dalam daftar yang diizinkan
// Ini adalah langkah keamanan penting untuk mencegah SQL Injection
$allowed_tables = ['berita', 'doa', 'donasi', 'profile_ankytm', 'programm', 'users', 'pengeluaran'];
if (!in_array($tabel, $allowed_tables)) {
    die("Tabel tidak valid atau tidak diizinkan.");
}

// Query untuk mengambil semua data dari tabel yang dipilih
// Menggunakan real_escape_string untuk keamanan nama tabel, meskipun sudah difilter oleh allowed_tables
$sql = "SELECT * FROM " . $conn->real_escape_string($tabel);
$result = $conn->query($sql);

if (!$result) {
    die("Gagal mengambil data: " . $conn->error);
}

// Nama file yang akan diunduh
$filename = "data_" . $tabel . "_" . date('Ymd_His') . ".csv";

// Set header untuk mengunduh file CSV
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Buka output stream untuk menulis data CSV
$output = fopen('php://output', 'w');

// Dapatkan nama kolom untuk header CSV
$header_row = [];
while ($field = $result->fetch_field()) {
    $header_row[] = $field->name;
}
fputcsv($output, $header_row);

// Tulis setiap baris data ke file CSV
while ($row = $result->fetch_assoc()) {
    // Menangani kolom yang mungkin perlu diformat ulang atau disembunyikan untuk Excel
    foreach ($row as $key => &$value) {
        // Contoh: jika ada kolom 'password', sembunyikan atau ganti
        if ($key === 'password') {
            $value = '********'; // Sembunyikan password
        }
        // Contoh: jika ada kolom nominal, pastikan format numerik tanpa titik/koma agar bisa dihitung di Excel
        if (in_array($key, ['jumlah', 'total_donasi'])) {
            $value = str_replace(['.', ','], '', $value); // Hapus format titik/koma
        }
        // Untuk data tanggal/waktu, pastikan format standar ISO (YYYY-MM-DD HH:MM:SS)
        // Ini membantu Excel mengenali format tanggal/waktu dengan benar
        if (in_array($key, ['tanggal', 'tanggal_doa', 'created_at', 'tanggal_pengeluaran', 'birth_date']) && !empty($value)) {
            $value = date('Y-m-d H:i:s', strtotime($value));
        }
        // Pastikan semua nilai adalah string untuk fputcsv
        $value = (string)$value;
    }
    fputcsv($output, $row);
}

// Tutup output stream
fclose($output);

// Hentikan eksekusi script setelah mengirim file
ob_end_flush();
exit();
?>  