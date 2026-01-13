<?php
session_start();
require_once 'config.php';

// Koneksi ke database
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "yayasan_amal";

$conn = new mysqli($servername, $username, $password, $dbname);

// Cek koneksi
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Mengecek tabel apa saja yang tersedia di database
$tablesQuery = "SHOW TABLES";
$tablesResult = $conn->query($tablesQuery);

$availableTables = [];
if ($tablesResult->num_rows > 0) {
    while($tableRow = $tablesResult->fetch_row()) {
        $availableTables[] = $tableRow[0];
    }
}

// Ambil data profil anak yatim piatu dari database
$tableName = 'profile_ankytm'; // nama tabel default

// Cek apakah tabel profile_ankytm ada
if (!in_array($tableName, $availableTables)) {
    foreach ($availableTables as $table) {
        if (strpos($table, 'profil') !== false || strpos($table, 'anak') !== false || strpos($table, 'yatim') !== false) {
            $tableName = $table;
            break;
        }
    }
}

// Cek struktur tabel yang kita pilih
$columnsQuery = "SHOW COLUMNS FROM $tableName";
$columnsResult = $conn->query($columnsQuery);

$availableColumns = [];
if ($columnsResult && $columnsResult->num_rows > 0) {
    while($columnRow = $columnsResult->fetch_assoc()) {
        $availableColumns[] = $columnRow['Field'];
    }
}

// Persiapkan query SQL berdasarkan kolom yang tersedia
$requiredColumns = ['id', 'nama', 'usia', 'asal', 'cerita', 'foto'];
$selectColumns = [];

foreach ($requiredColumns as $column) {
    if (in_array($column, $availableColumns)) {
        $selectColumns[] = $column;
    }
}

// Jika kolom-kolom utama tidak ada, tampilkan pesan error
if (empty($selectColumns)) {
    die("Struktur tabel tidak sesuai. Harap periksa database.");
}

$columnsString = implode(', ', $selectColumns);
$sql = "SELECT $columnsString FROM $tableName";
$result = $conn->query($sql);

// Cek apakah query berhasil
if (!$result) {
    die("Query failed: " . $conn->error . "<br>SQL: $sql");
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Anak Yatim Piatu - Rumah AYP</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Palet Warna (Modern Clean with Dynamic Gradients) - Konsisten dengan halaman lain */
        :root {
            --color-bg-primary: #F0F2F5; /* Soft Light Gray */
            --color-bg-secondary: #FFFFFF; /* Pure White */
            --color-gradient-start: #4FC3F7; /* Light Blue */
            --color-gradient-end: #8BC34A;   /* Light Green */
            --color-accent-secondary: #FFA726; /* Vibrant Orange */
            --color-text-primary: #37474F; /* Dark Blue Gray */
            --color-text-secondary: #78909C; /* Medium Blue Gray */
            --color-border-subtle: #CFD8DC; /* Light Blue Gray */
            --color-footer-bg: #263238; /* Dark Blue Gray */

            --shadow-light: 0 4px 15px rgba(0, 0, 0, 0.05);
            --shadow-medium: 0 8px 25px rgba(0, 0, 0, 0.1);
            --shadow-strong: 0 12px 35px rgba(0, 0, 0, 0.15);

            /* Warna spesifik untuk tombol interaksi/adopsi (sesuai gambar) */
            --btn-custom-light-green: #96c93d; /* Warna dari gambar tombol 'Baca Selengkapnya' */
            --btn-custom-teal: #00b09b; /* Warna dari gambar tombol 'Mulai Berinteraksi' */
            --btn-custom-dark-green-start: #008f7b; /* Lebih gelap dari teal untuk Mulai Berinteraksi */
            --btn-custom-dark-green-end: #2E8B57; /* Sea Green untuk Mulai Berinteraksi */
        }

        /* Gaya Dasar Global & Tipografi - Latar belakang body menggunakan gradien baru */
        body {
            font-family: 'Montserrat', sans-serif; /* Menggunakan Montserrat untuk seluruh teks */
            line-height: 1.7;
            margin: 0;
            padding: 0;
            background: linear-gradient(135deg, #E0F2F1, #E3F2FD, #EDE7F6); /* Gradien hijau soft, biru, ungu */
            color: var(--color-text-primary); /* Warna teks utama */
            -webkit-font-smoothing: antialiased; /* Anti-aliasing font untuk tampilan lebih halus */
            -moz-osx-font-smoothing: grayscale; /* Anti-aliasing font untuk Firefox */
            overflow-x: hidden; /* Mencegah overflow horizontal */
        }
        h1, h2, h3, p {
            font-family: 'Montserrat', sans-serif;
        }
        h1, h2, h3 {
            color: var(--color-text-primary);
            font-weight: 800;
        }
        p {
            color: var(--color-text-secondary);
            font-weight: 400;
        }

        /* Navbar - Konsisten dengan halaman lain */
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.7rem 4%;
            background: var(--color-bg-secondary); /* Latar belakang putih */
            border-bottom-left-radius: 15px; /* Sudut membulat */
            border-bottom-right-radius: 15px;
            backdrop-filter: blur(8px);
            box-shadow: var(--shadow-medium); /* Bayangan lebih menonjol */
            position: fixed;
            width: calc(100% - 40px);
            max-width: 1200px;
            left: 50%;
            transform: translateX(-50%); /* Tengah */
            height: 75px;
            top: 15px; /* Awalnya mengambang */
            z-index: 1000;
            transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94); /* Easing yang smooth */
            border: 1px solid var(--color-border-subtle); /* Border halus */
        }
        /* Navbar saat di-scroll */
        .navbar.scrolled {
            width: 100%; /* Melebar penuh */
            max-width: none; /* Menghilangkan batasan max-width */
            left: 0;
            transform: translateX(0); /* Kembali ke skala 1, tanpa zoom */
            top: 0; /* Menempel di atas */
            border-radius: 0; /* Sudut hilang */
            background-color: rgba(255, 255, 255, 0.99); /* Hampir solid */
            box-shadow: var(--shadow-strong); /* Bayangan lebih kuat */
            border-color: transparent; /* Hilangkan border saat menempel */
        }
        .navbar .logo img {
           width: 120px; /* Ukuran logo di navbar */
           height: auto;
           display: block;
           position: relative;
           margin-left: -10px; /* Sesuaikan posisi jika perlu */
        }
        .navbar .auth-buttons {
            display: flex;
            align-items: center;
            gap: 10px; /* Jarak antar tombol login/register */
        }
        .nav-links {
            display: flex;
            gap: 2rem;
            list-style: none;
            margin: 0 auto; /* Tengah */
        }
        .nav-links a {
            color: var(--color-text-primary);
            text-decoration: none;
            font-weight: 600;
            padding: 5px 0;
            transition: color 0.3s ease, transform 0.2s ease, opacity 0.3s ease;
            position: relative;
            font-size: 0.95em;
            letter-spacing: 0.5px;
        }
        .nav-links a::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            background: linear-gradient(to right, var(--color-gradient-start), var(--color-gradient-end));
            left: 0;
            bottom: -5px;
            transition: width 0.3s ease;
        }
        .nav-links a:hover::after, .nav-links a.active::after {
            width: 100%;
        }
        .nav-links a:hover, .nav-links a.active {
            color: var(--color-gradient-start);
            transform: translateY(-1px);
            opacity: 0.9;
        }

        /* Hamburger Menu untuk Mobile */
        .hamburger-menu {
            display: none; /* Sembunyikan di desktop */
            font-size: 2em;
            color: var(--color-text-primary);
            cursor: pointer;
            z-index: 1001;
            transition: color 0.3s ease;
        }
        .hamburger-menu:hover {
            color: var(--color-gradient-start);
        }
        .mobile-nav-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(8px);
            z-index: 999;
            justify-content: center;
            align-items: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .mobile-nav-overlay.open {
            display: flex;
            opacity: 1;
        }
        .mobile-nav {
            list-style: none;
            padding: 0;
            text-align: center;
            transform: translateY(20px);
            opacity: 0;
            transition: transform 0.3s ease, opacity 0.3s ease;
        }
        .mobile-nav-overlay.open .mobile-nav {
            transform: translateY(0);
            opacity: 1;
        }
        .mobile-nav li {
            margin: 25px 0;
        }
        .mobile-nav a {
            color: var(--color-text-primary);
            text-decoration: none;
            font-size: 1.8em;
            font-weight: 700;
            transition: color 0.3s ease;
        }
        .mobile-nav a:hover, .mobile-nav a.active {
            color: var(--color-accent-secondary);
        }
        .mobile-nav .auth-buttons-mobile {
            margin-top: 40px;
            display: flex;
            flex-direction: column;
            gap: 18px;
        }
        .mobile-nav .auth-buttons-mobile .profile-link,
        .mobile-nav .auth-buttons-mobile .btn-login,
        .mobile-nav .auth-buttons-mobile .btn-register {
            width: 90%;
            margin: 0 auto;
            padding: 15px 20px;
            font-size: 1.2em;
            text-align: center;
            border-radius: 30px;
        }
        .mobile-nav .auth-buttons-mobile .profile-link:hover,
        .mobile-nav .auth-buttons-mobile .btn-login:hover {
            background-color: rgba(0, 0, 0, 0.1);
        }
        .mobile-nav .auth-buttons-mobile .btn-register {
            background: linear-gradient(45deg, var(--color-gradient-start), var(--color-gradient-end));
            color: #FFFFFF;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .mobile-nav .auth-buttons-mobile .btn-register:hover {
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }

        /* Tombol Otentikasi (Login/Register) */
        .profile-link {
            color: var(--color-text-primary);
            text-decoration: none;
            font-weight: 600;
            padding: 7px 14px;
            border-radius: 18px;
            background-color: transparent;
            transition: all 0.3s ease;
            border: 1px solid var(--color-border-subtle);
            font-size: 0.85em;
        }
        .profile-link:hover {
            background-color: rgba(79, 195, 247, 0.1);
            color: var(--color-gradient-start);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transform: translateY(-1px);
        }
        .btn-login {
            color: var(--color-text-primary);
            text-decoration: none;
            font-weight: 600;
            padding: 7px 14px;
            border-radius: 18px;
            background-color: transparent;
            transition: all 0.3s ease;
            border: 1px solid var(--color-border-subtle);
            font-size: 0.85em;
        }
        .btn-login:hover {
            background-color: rgba(79, 195, 247, 0.1);
            color: var(--color-gradient-start);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transform: translateY(-1px);
        }
        .btn-register {
            display: inline-block;
            background: linear-gradient(45deg, var(--color-gradient-start), var(--color-gradient-end));
            color: #ffffff;
            padding: 7px 14px;
            border-radius: 18px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85em;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-light);
            border: none;
            position: relative;
            overflow: hidden;
        }
        .btn-register:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-medium);
        }


        /* Header section */
        header {
            background: linear-gradient(135deg, var(--color-gradient-start), var(--color-gradient-end)); /* Gradien dari variabel */
            color: #ffffff;
            padding: 80px 0; /* Padding lebih besar */
            text-align: center;
            margin-top: 75px; /* Jarak dari fixed navbar */
            box-shadow: var(--shadow-strong); /* Bayangan lebih kuat */
            border-bottom-left-radius: 25px; /* Sudut membulat */
            border-bottom-right-radius: 25px;
            position: relative; /* Penting untuk pseudo-element */
            overflow: hidden; /* Pastikan overlay tidak keluar */
        }
        header::before { /* Overlay dekoratif */
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" opacity="0.1"><circle cx="20" cy="20" r="15" fill="%23fff"/><circle cx="80" cy="80" r="10" fill="%23fff"/><rect x="50" y="10" width="10" height="10" fill="%23fff"/></svg>');
            background-size: 50px;
            opacity: 0.05;
            z-index: 0;
        }
        header.animated { /* Animasi saat muncul */
            opacity: 1;
            transform: translateY(0);
            transition: opacity 0.8s ease-out, transform 0.8s ease-out;
        }
        header h1 {
            font-size: 3em; /* Ukuran font lebih besar */
            margin: 0 auto;
            font-weight: 800;
            text-shadow: 2px 2px 6px rgba(0,0,0,0.3);
            color: white; /* Judul selalu putih di header */
            position: relative; /* Agar di atas overlay */
            z-index: 1;
        }

        main {
            max-width: 1200px; /* Lebar maksimum konten */
            margin: 40px auto; /* Margin di atas dan bawah */
            padding: 0 20px; /* Padding samping */
        }

        /* Intro section */
        .profile-intro {
            background: var(--color-bg-secondary); /* Latar belakang putih */
            border-radius: 15px; /* Sudut membulat */
            padding: 50px 40px; /* Padding internal */
            box-shadow: var(--shadow-medium); /* Bayangan sedang */
            margin-bottom: 40px;
            border: 1px solid var(--color-border-subtle); /* Border tipis */
            text-align: center;
            opacity: 0; /* Untuk animasi */
            transform: translateY(20px); /* Untuk animasi */
        }
        .profile-intro.animated { /* Animasi saat muncul */
            opacity: 1;
            transform: translateY(0);
            transition: opacity 0.6s ease-out, transform 0.6s ease-out;
        }
        .profile-intro h2 {
            color: var(--color-text-primary); /* Warna judul utama */
            font-size: 2.8em; /* Ukuran judul */
            margin-bottom: 35px; /* Jarak bawah */
            border-bottom: 4px solid;
            border-image: linear-gradient(to right, var(--color-gradient-start), var(--color-gradient-end)) 1; /* Garis bawah gradien */
            padding-bottom: 15px;
            text-align: center;
            position: relative;
            display: inline-block;
            font-weight: 800;
        }
        .profile-intro p {
            max-width: 800px;
            margin: 0 auto;
            line-height: 1.8;
            color: var(--color-text-secondary);
            font-size: 1.05em;
        }

        /* Profile List & Cards */
        .profile-list {
            display: grid; /* Menggunakan Grid */
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); /* Kolom responsif, min 300px */
            gap: 30px; /* Jarak antar kartu */
            justify-content: center;
        }

        .profile-card {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow-medium); /* Bayangan sedang */
            transition: transform 0.3s ease, box-shadow 0.3s ease, background-color 0.3s ease;
            background: var(--color-bg-secondary); /* Latar belakang putih */
            border: 1px solid var(--color-border-subtle); /* Border tipis */
            display: flex;
            flex-direction: column; /* Konten bertumpuk */
            opacity: 0; /* Untuk animasi */
            transform: translateY(20px); /* Untuk animasi */
        }
        .profile-card.animated { /* Animasi saat muncul */
            opacity: 1;
            transform: translateY(0);
            transition: opacity 0.6s ease-out, transform 0.6s ease-out;
        }

        .profile-card:hover {
            transform: translateY(-8px); /* Efek lift saat hover */
            box-shadow: var(--shadow-strong); /* Bayangan lebih kuat */
            background-color: var(--color-bg-primary); /* Sedikit perubahan warna saat hover */
        }
        .profile-image {
            height: 220px;
            overflow: hidden;
            border-bottom: 1px solid var(--color-border-subtle); /* Pemisah gambar dan info */
        }
        .profile-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        .profile-card:hover .profile-image img {
            transform: scale(1.08); /* Zoom gambar saat hover */
        }
        .profile-info {
            padding: 25px;
            flex-grow: 1; /* Agar info mengisi ruang */
            display: flex;
            flex-direction: column;
        }
        .profile-info h3 {
            margin: 0 0 12px 0;
            color: var(--color-text-primary); /* Warna teks utama */
            font-size: 1.5em; /* Ukuran nama anak */
            font-weight: 700;
        }
        .profile-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 0.9em;
            color: var(--color-text-secondary);
            border-bottom: 1px dashed var(--color-border-subtle); /* Pemisah titik-titik */
            padding-bottom: 10px;
        }
        .profile-story {
            font-size: 0.95em;
            line-height: 1.7;
            color: var(--color-text-secondary);
            max-height: 100px; /* Batasi tinggi cerita */
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 4; /* Batasi 4 baris */
            -webkit-box-orient: vertical;
            margin-bottom: 20px;
            flex-grow: 1;
        }
        .read-more { /* Konsisten dengan btn-primary */
            display: inline-block;
            background: linear-gradient(45deg, var(--color-gradient-start), var(--color-gradient-end));
            color: #ffffff;
            padding: 12px 25px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 700;
            font-size: 1em;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-medium);
            border: none;
            text-align: center;
            align-self: flex-start; /* Sejajarkan ke kiri (start) dalam flex container */
            margin-top: 15px; /* Jarak dari cerita */
        }
        .read-more:hover {
            background: linear-gradient(45deg, var(--color-gradient-end), var(--color-gradient-start));
            transform: translateY(-3px) scale(1.02);
            box-shadow: var(--shadow-strong);
        }

        /* Ringkasan Penyaluran Dana di Kartu */
        .spending-summary {
            font-size: 0.9em;
            color: var(--color-text-secondary);
            margin-top: 15px;
            margin-bottom: 10px;
            padding-top: 10px;
            border-top: 1px dashed var(--color-border-subtle);
            line-height: 1.5;
            text-align: left;
        }
        .spending-summary strong {
            color: var(--color-text-primary);
            font-weight: 600;
        }

        /* Modal for profile details */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.7); /* Overlay lebih gelap */
            overflow: auto;
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
        }
        .modal-content {
            background-color: var(--color-bg-secondary); /* Background putih */
            margin: 5% auto;
            padding: 40px;
            border-radius: 15px;
            width: 90%;
            max-width: 700px;
            position: relative;
            box-shadow: var(--shadow-strong);
            animation: fadeInScale 0.3s ease-out forwards;
            border: 1px solid var(--color-border-subtle);
        }
        @keyframes fadeInScale {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        .close-modal {
            position: absolute;
            top: 15px;
            right: 20px;
            color: var(--color-text-secondary);
            font-size: 32px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s ease, transform 0.2s ease;
        }
        .close-modal:hover {
            color: var(--color-text-primary);
            transform: rotate(90deg);
        }
        .modal-image {
            text-align: center;
            margin-bottom: 25px;
        }
        .modal-image img {
            max-width: 80%; /* Disesuaikan agar tidak terlalu besar di modal */
            max-height: 350px;
            border-radius: 10px;
            box-shadow: var(--shadow-medium);
            border: 3px solid var(--color-gradient-start); /* Border dengan warna gradien */
            object-fit: cover; /* Pastikan gambar memenuhi area */
        }
        .modal-content h2 {
            color: var(--color-text-primary);
            font-size: 2.2em;
            margin-bottom: 15px;
            text-align: center;
            border-bottom: 2px solid var(--color-accent-secondary); /* Garis bawah aksen */
            padding-bottom: 10px;
            display: inline-block;
            position: relative;
            left: 50%;
            transform: translateX(-50%);
            white-space: nowrap;
        }
        .modal-content p {
            font-size: 1em;
            line-height: 1.7;
            color: var(--color-text-secondary);
            margin-bottom: 10px;
        }
        .modal-content h3 {
            color: var(--color-text-primary);
            font-size: 1.5em;
            margin-top: 25px;
            margin-bottom: 15px;
            text-align: left; /* Heading di modal lebih baik kiri */
            border-bottom: 2px solid var(--color-border-subtle); /* Garis bawah tipis */
            padding-bottom: 5px;
        }
        /* Daftar pengeluaran di modal */
        .pengeluaran-list {
            list-style: none;
            padding: 0;
            margin-top: 10px;
            border: 1px solid var(--color-border-subtle);
            border-radius: 8px;
            padding: 15px;
            background-color: var(--color-bg-primary); /* Background soft gray */
        }
        .pengeluaran-list li {
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px dashed var(--color-border-subtle);
            font-size: 0.95em;
            color: var(--color-text-primary);
        }
        .pengeluaran-list li:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .pengeluaran-list li strong {
            color: var(--color-text-primary);
        }

        .debug-info {
            background: var(--color-bg-primary);
            padding: 15px;
            margin: 20px auto;
            border-radius: 8px;
            border: 1px solid var(--color-border-subtle);
            font-family: 'Open Sans', sans-serif;
            font-size: 0.9em;
            color: var(--color-text-primary);
            max-width: 1200px;
        }
        .debug-info h3 {
            font-size: 1.2em;
            margin-bottom: 10px;
            color: var(--color-accent-secondary);
        }
        .debug-info ul {
            list-style: disc inside;
            padding-left: 15px;
        }
        .debug-info li {
            margin-bottom: 5px;
        }


        /* Footer - Konsisten dengan halaman lain */
        footer {
            background-color: var(--color-footer-bg);
            color: white;
            padding: 60px 20px 30px;
            margin-top: 50px;
            box-shadow: inset 0 8px 20px rgba(0, 0, 0, 0.25); /* Bayangan ke dalam */
            border-top-left-radius: 30px;
            border-top-right-radius: 30px;
            position: relative;
            z-index: 1;
        }

        .footer-container {
            max-width: 1100px;
            margin: auto;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 40px;
        }

        .footer-column {
            flex: 1 1 250px;
            min-width: 200px;
        }

        .footer-column h3 {
            font-size: 1.5em;
            margin-bottom: 20px;
            color: #FFFFFF;
            border-bottom: 2px solid var(--color-accent-secondary);
            padding-bottom: 10px;
            font-weight: 700;
        }

        .footer-column p,
        .footer-column a {
            font-size: 0.95em;
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer-column ul {
            list-style: none;
            padding: 0;
        }

        .footer-column li {
            margin-bottom: 10px;
        }

        .footer-column a:hover {
            color: var(--color-accent-secondary);
            text-decoration: underline;
        }

        .footer-icons {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .footer-icons a {
            display: inline-block;
            transition: transform 0.3s ease, opacity 0.3s;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.08);
            padding: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }

        .footer-icons a:hover {
            transform: scale(1.1);
            opacity: 1;
            background-color: rgba(255, 255, 255, 0.15);
        }
        .footer-icons img {
            width: 28px;
            height: 28px;
            vertical-align: middle;
            filter: none;
        }

        .footer-bottom {
            margin-top: 40px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 20px;
            text-align: center;
            font-size: 0.8em;
            color: rgba(255, 255, 255, 0.6);
        }

        /* Responsive design */
        @media (max-width: 1024px) {
            .navbar {
                padding: 0.7rem 3%;
                height: 65px;
                width: calc(100% - 30px);
                top: 10px;
            }
            .navbar.scrolled {
                width: 100%;
                top: 0;
            }
            .nav-links {
                gap: 1.5rem;
            }
            header {
                padding: 60px 0;
            }
            header h1 {
                font-size: 2.5em;
            }
            .profile-intro {
                padding: 40px 30px;
            }
            .profile-intro h2 {
                font-size: 2.4em;
            }
            .profile-list {
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap: 25px;
            }
            .modal-content {
                width: 90%;
                margin: 10% auto;
            }
        }
        @media (max-width: 768px) {
            .navbar {
                flex-wrap: wrap;
                justify-content: space-between;
                height: auto;
                padding: 15px 20px;
                background-color: rgba(255, 255, 255, 0.98);
                box-shadow: var(--shadow-medium);
                width: 100%;
                top: 0;
                border-radius: 0;
            }
            .navbar .logo {
                margin-bottom: 0;
            }
            .nav-links, .navbar .auth-buttons {
                display: none;
            }
            /* Hamburger Menu */
            .hamburger-menu {
                display: block;
                font-size: 2em;
                color: var(--color-text-primary);
                cursor: pointer;
                z-index: 1001;
                transition: color 0.3s ease;
            }
            .hamburger-menu:hover {
                color: var(--color-gradient-start);
            }
            .mobile-nav-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(255, 255, 255, 0.98);
                backdrop-filter: blur(8px);
                z-index: 999;
                justify-content: center;
                align-items: center;
                opacity: 0;
                transition: opacity 0.3s ease;
            }
            .mobile-nav-overlay.open {
                display: flex;
                opacity: 1;
            }
            .mobile-nav {
                list-style: none;
                padding: 0;
                text-align: center;
                transform: translateY(20px);
                opacity: 0;
                transition: transform 0.3s ease, opacity 0.3s ease;
            }
            .mobile-nav-overlay.open .mobile-nav {
                transform: translateY(0);
                opacity: 1;
            }
            .mobile-nav li {
                margin: 25px 0;
            }
            .mobile-nav a {
                color: var(--color-text-primary);
                text-decoration: none;
                font-size: 1.8em;
                font-weight: 700;
                transition: color 0.3s ease;
            }
            .mobile-nav a:hover, .mobile-nav a.active {
                color: var(--color-accent-secondary);
            }
            .mobile-nav .auth-buttons-mobile {
                margin-top: 40px;
                display: flex;
                flex-direction: column;
                gap: 18px;
            }
            .mobile-nav .auth-buttons-mobile .profile-link,
            .mobile-nav .auth-buttons-mobile .btn-login,
            .mobile-nav .auth-buttons-mobile .btn-register {
                width: 90%;
                margin: 0 auto;
                padding: 15px 20px;
                font-size: 1.2em;
                text-align: center;
                border-radius: 30px;
            }
            .mobile-nav .auth-buttons-mobile .profile-link:hover,
            .mobile-nav .auth-buttons-mobile .btn-login:hover {
                background-color: rgba(0, 0, 0, 0.1);
            }
            .mobile-nav .auth-buttons-mobile .btn-register {
                background: linear-gradient(45deg, var(--color-gradient-start), var(--color-gradient-end));
                color: #FFFFFF;
                box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            }
            .mobile-nav .auth-buttons-mobile .btn-register:hover {
                box-shadow: 0 8px 20px rgba(0,0,0,0.15);
            }

            header {
                padding: 50px 0;
                margin-top: 65px;
            }
            header h1 {
                font-size: 2em;
            }
            main {
                padding: 20px 15px;
            }
            .profile-intro {
                padding: 25px;
                margin-bottom: 30px;
            }
            .profile-intro h2 {
                font-size: 1.8em;
            }
            .profile-intro p {
                font-size: 0.95em;
            }
            .profile-list {
                grid-template-columns: 1fr; /* Single column on small screens */
            }
            .modal-content {
                padding: 25px;
                margin: 10% auto;
            }
            .modal-content h2 {
                font-size: 1.8em;
            }
            .modal-image img {
                max-width: 100%;
                max-height: 250px;
            }
            .footer-container {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
            .footer-icons {
                justify-content: center;
            }
            .footer-column {
                min-width: unset;
                width: 100%;
            }
            .footer-column h3 {
                border-bottom: none;
                padding-bottom: 0;
            }
        }
        @media (max-width: 480px) {
            .navbar .logo img {
                width: 90px;
            }
            header h1 {
                font-size: 1.6em;
            }
            .profile-intro h2 {
                font-size: 1.5em;
            }
            .profile-card {
                padding-bottom: 15px; /* Kurangi padding bawah kartu */
            }
            .profile-image {
                height: 180px; /* Kurangi tinggi gambar */
            }
            .profile-info {
                padding: 20px; /* Kurangi padding info */
            }
            .profile-info h3 {
                font-size: 1.3em;
            }
            .profile-meta, .profile-story, .spending-summary {
                font-size: 0.85em;
            }
            .read-more, .btn-primary { /* Menggunakan btn-primary sebagai base */
                padding: 10px 18px;
                font-size: 0.9em;
            }
            .modal-content {
                padding: 20px;
            }
            .modal-content h2 {
                font-size: 1.6em;
            }
            .modal-content p, .pengeluaran-list li {
                font-size: 0.9em;
            }
            .modal-content h3 {
                font-size: 1.3em;
            }
        }

        /* --- Custom Button Styles for Adopsi/Interaksi --- */
        /* Tombol Adopsi Non-Finansial */
        .btn-adopt-custom {
            display: inline-block;
            background: linear-gradient(135deg, var(--btn-custom-teal), var(--btn-custom-light-green)); /* Gradien Teal ke Hijau Muda */
            color: #ffffff; /* Warna teks putih */
            padding: 12px 25px;
            border-radius: 30px; /* Bentuk pill */
            text-decoration: none;
            font-weight: 700;
            font-size: 1em;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: none;
            cursor: pointer;
            text-align: center;
            align-self: flex-start; /* Sejajarkan ke kiri */
            margin-top: 15px; /* Jarak dari elemen di atasnya */
        }

        .btn-adopt-custom:hover {
            background: linear-gradient(135deg, var(--btn-custom-light-green), var(--btn-custom-teal)); /* Invert gradien saat hover */
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
        }

        /* Tombol Mulai Berinteraksi */
        .btn-interact-custom {
            display: inline-block;
            background: linear-gradient(135deg, var(--btn-custom-dark-green-start), var(--btn-custom-dark-green-end)); /* Gradien hijau lebih gelap */
            color: #ffffff; /* Warna teks putih */
            padding: 12px 25px;
            border-radius: 30px; /* Bentuk pill */
            text-decoration: none;
            font-weight: 700;
            font-size: 1em;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: none;
            cursor: pointer;
            text-align: center;
            align-self: flex-start; /* Sejajarkan ke kiri */
            margin-top: 15px; /* Jarak dari elemen di atasnya */
        }

        .btn-interact-custom:hover {
            background: linear-gradient(135deg, var(--btn-custom-dark-green-end), var(--btn-custom-dark-green-start)); /* Invert gradien saat hover */
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
        }
        /* Responsif untuk tombol kustom */
        @media (max-width: 480px) {
            .btn-adopt-custom, .btn-interact-custom {
                padding: 10px 18px;
                font-size: 0.9em;
                width: auto; /* Biarkan lebar menyesuaikan konten */
            }
        }
    </style>
</head>
<body>
<nav class="navbar" id="mainNavbar">
    <div class="logo">
        <img src="logo_rumah_ayp.png" alt="logorumah ayp" id="navbarLogo"/>
    </div>
    <ul class="nav-links" id="mainNavLinks">
        <li><a href="index.php">Home</a></li>
        <li><a href="about.php">Tentang Kami</a></li>
        <li><a href="program.php">Program</a></li>
        <li><a href="berita.php">Berita</a></li>
        <li><a href="profilyatim.php" class="active">Profil Anak</a></li>
    </ul>
    <div class="auth-buttons" id="mainAuthButtons">
        <?php if(isset($_SESSION['user_id'])): ?>
            <div class="user-info">
                <a href="profile.php" class="profile-link" title="Profil Anda">
                    <?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'User'; ?>
                </a>
            </div>
            <form action="logout.php" method="POST" style="display:inline;">
                <button type="submit" class="btn-register">Logout</button>
            </form>
        <?php else: ?>
            <a href="register.php" class="btn-login">Register</a>
            <a href="login.php" class="btn-register">Login</a>
        <?php endif; ?>
    </div>
    <div class="hamburger-menu" id="hamburgerIcon">
        <i class="fas fa-bars"></i>
    </div>
</nav>

<div class="mobile-nav-overlay" id="mobileNavOverlay">
    <ul class="mobile-nav">
        <li><a href="index.php">Home</a></li>
        <li><a href="about.php">Tentang Kami</a></li>
        <li><a href="program.php">Program</a></li>
        <li><a href="berita.php">Berita</a></li>
        <li><a href="profilyatim.php" class="active">Profil Anak</a></li>
        <div class="auth-buttons-mobile">
            <?php if(isset($_SESSION['user_id'])): ?>
                <a href="profile.php" class="profile-link">
                    <?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'User'; ?>
                </a>
                <form action="logout.php" method="POST" style="display:inline;">
                    <button type="submit" class="btn-register">Logout</button>
                </form>
            <?php else: ?>
                <a href="register.php" class="btn-login">Register</a>
                <a href="login.php" class="btn-register">Login</a>
            <?php endif; ?>
        </div>
    </ul>
</div>


<header class="animate-on-scroll">
    <h1>Profil Anak Yatim Piatu</h1>
</header>

<main>
    <?php if (isset($_GET['debug']) && $_GET['debug'] == 1): ?>
    <div class="debug-info">
        <h3>Informasi Debug</h3>
        <p>Tabel yang tersedia di database:</p>
        <ul>
            <?php foreach ($availableTables as $table): ?>
                <li><?php echo $table; ?></li>
            <?php endforeach; ?>
        </ul>
        <p>Tabel yang digunakan: <?php echo $tableName; ?></p>
        <p>Kolom yang tersedia di tabel <?php echo $tableName; ?>:</p>
        <ul>
            <?php foreach ($availableColumns as $column): ?>
                <li><?php echo $column; ?></li>
            <?php endforeach; ?>
        </ul>
        <p>SQL Query: <?php echo htmlspecialchars($sql); ?></p>
    </div>
    <?php endif; ?>

    <section class="profile-intro animate-on-scroll">
        <h2>Kenali Anak-Anak Kami</h2>
        <p>Setiap anak memiliki cerita dan perjalanan yang unik. Di Rumah AYP, kami berkomitmen untuk memberikan dukungan, kasih sayang, dan kesempatan yang mereka butuhkan untuk tumbuh dan berkembang. Mari berkenalan dengan anak-anak luar biasa yang menjadi bagian dari keluarga kami.</p>
        <p style="margin-top: 20px;">
            Dengan fitur "Adopsi Non-Finansial", Anda bisa membina anak asuh secara emosional, mengirimkan surat, motivasi, atau informasi hadiah di hari spesial mereka. Ciptakan kedekatan jangka panjang yang berarti!
        </p>
    </section>

    <section class="profile-list">
        <?php
        if ($result && $result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                // Ensure all expected columns are handled, provide defaults if missing
                $id = isset($row["id"]) ? htmlspecialchars($row["id"]) : uniqid('profile_'); // Generate unique ID if not available
                $nama = isset($row["nama"]) ? htmlspecialchars($row["nama"]) : 'Nama tidak tersedia';
                $usia = isset($row["usia"]) ? htmlspecialchars($row["usia"]) : 'Tidak diketahui';
                $asal = isset($row["asal"]) ? htmlspecialchars($row["asal"]) : 'Tidak diketahui';
                $cerita = isset($row["cerita"]) ? htmlspecialchars($row["cerita"]) : 'Tidak ada cerita tersedia.';

                // --- PERBAIKAN PATH FOTO ANAK YATIM (Menggunakan nilai langsung dari database) ---
                $base_foto_name = isset($row["foto"]) && $row["foto"] ? htmlspecialchars($row["foto"]) : '';
                // Asumsi: $row["foto"] sudah berisi path relatif yang benar dari root project
                // Contoh: 'rina.jpg' (jika di root), atau 'images/rina.jpg', atau 'uploads/rina.jpg'
                $foto_path = empty($base_foto_name) ? 'images/no-photo.png' : $base_foto_name;
                // JavaScript onerror akan menangani jika path ini tidak valid.
                // --- AKHIR PERBAIKAN PATH FOTO ---

                // --- PENGAMBILAN DATA PENGELUARAN UNTUK RINGKASAN DI KARTU DAN DETAIL DI MODAL ---
                $ringkasan_pengeluaran = '';
                $total_disalurkan_anak_ini = 0;
                $detail_pengeluaran_html = ''; // Untuk modal

                // Pastikan kolom 'id' ada di $row sebelum menggunakannya
                if (isset($row['id'])) {
                    $sql_pengeluaran_anak = "SELECT jumlah, tipe, keterangan, tanggal FROM pengeluaran WHERE profile_ankytm_id = ? ORDER BY tanggal DESC LIMIT 3"; // Batasi 3 terbaru untuk ringkasan
                    $stmt_pengeluaran_anak = $conn->prepare($sql_pengeluaran_anak);
                    if ($stmt_pengeluaran_anak) {
                        $stmt_pengeluaran_anak->bind_param("i", $row['id']);
                        $stmt_pengeluaran_anak->execute();
                        $result_pengeluaran_anak = $stmt_pengeluaran_anak->get_result();

                        if ($result_pengeluaran_anak && $result_pengeluaran_anak->num_rows > 0) {
                            $ringkasan_pengeluaran .= "<div class='spending-summary'>";
                            $ringkasan_pengeluaran .= "<strong>Penyaluran Dana Terbaru:</strong><br>";
                            $detail_pengeluaran_html .= "<ul class='pengeluaran-list'>"; // Mulai list untuk detail modal
                            while($pengeluaran_row = $result_pengeluaran_anak->fetch_assoc()) {
                                $tipe_formatted = ucwords(str_replace('_', ' ', htmlspecialchars($pengeluaran_row['tipe'])));
                                $jumlah_formatted = 'Rp ' . number_format(htmlspecialchars($pengeluaran_row['jumlah']), 0, ',', '.');
                                $tanggal_formatted = date('d M Y', strtotime(htmlspecialchars($pengeluaran_row['tanggal'])));
                                $keterangan_short = htmlspecialchars(substr($pengeluaran_row['keterangan'], 0, 50)) . (strlen($pengeluaran_row['keterangan']) > 50 ? '...' : '');

                                $total_disalurkan_anak_ini += (float)$pengeluaran_row['jumlah'];

                                // Ringkasan di kartu
                                $ringkasan_pengeluaran .= "<span>• " . $tipe_formatted . ": " . $jumlah_formatted . "</span><br>";

                                // Detail di modal
                                $detail_pengeluaran_html .= "<li><strong>" . $tipe_formatted . "</strong>: " . $jumlah_formatted . " (" . $tanggal_formatted . ")<br><small>" . htmlspecialchars($pengeluaran_row['keterangan']) . "</small></li>";
                            }
                            $ringkasan_pengeluaran .= "</div>";
                            $detail_pengeluaran_html .= "</ul>"; // Tutup list untuk detail modal
                        } else {
                            $ringkasan_pengeluaran = "<div class='spending-summary' style='color: var(--color-text-secondary);'>Belum ada penyaluran dana spesifik untuk anak ini.</div>";
                            $detail_pengeluaran_html = "<p style='font-size: 0.9em; color: var(--color-text-secondary);'>Belum ada data penyaluran dana spesifik untuk anak ini.</p>";
                        }
                        $stmt_pengeluaran_anak->close();
                    } else {
                        // Handle error jika statement gagal disiapkan
                        $ringkasan_pengeluaran = "<div class='spending-summary' style='color: #dc3545;'>Error: Gagal menyiapkan query pengeluaran.</div>";
                        $detail_pengeluaran_html = "<p style='font-size: 0.9em; color: #dc3545;'>Error: Gagal menyiapkan query pengeluaran.</p>";
                    }
                } else {
                     $ringkasan_pengeluaran = "<div class='spending-summary' style='color: var(--color-text-secondary);'>ID anak tidak tersedia untuk pengeluaran.</div>";
                     $detail_pengeluaran_html = "<p style='font-size: 0.9em; color: var(--color-text-secondary);'>ID anak tidak tersedia untuk pengeluaran.</p>";
                }
                // --- AKHIR PENGAMBILAN DATA PENGELUARAN ---

                // Menampilkan kartu profil
                echo "<div class='profile-card animate-on-scroll' data-id='$id'>";
                echo "  <div class='profile-image'>";
                echo "    <img src='$foto_path' alt='Foto $nama' onerror=\"this.src='images/no-photo.png';\">"; // onerror ditangani di JS
                echo "  </div>";
                echo "  <div class='profile-info'>";
                echo "    <h3>$nama</h3>";
                echo "    <div class='profile-meta'>";
                echo "      <span>Usia: " . (is_numeric($usia) ? "$usia tahun" : $usia) . "</span>";
                echo "      <span>Asal: $asal</span>";
                echo "    </div>";
                echo "    <div class='profile-story'>" . nl2br($cerita) . "</div>";

                // Tampilkan ringkasan pengeluaran di sini (LUAR MODAL)
                echo $ringkasan_pengeluaran;

                echo "    <a href='javascript:void(0)' class='read-more' onclick='openModal(\"$id\")'>Baca Selengkapnya</a>";

                // --- LOGIKA TOMBOL ADOPSI/INTERAKSI (BERDASARKAN STATUS LOGIN DAN ADOPSI) ---
                if (isset($_SESSION['user_id'])) {
                    $is_adopted_by_current_user = false;
                    $adopsi_id = null; // Untuk menyimpan adopsi_id jika sudah diadopsi
                    // Pastikan $row['id'] ada sebelum digunakan
                    if (isset($row['id'])) {
                        $stmt_check_adopsi = $conn->prepare("SELECT id FROM adopsi_non_finansial WHERE donatur_id = ? AND profile_ankytm_id = ? AND status = 'aktif'");
                        if ($stmt_check_adopsi) {
                            $stmt_check_adopsi->bind_param("ii", $_SESSION['user_id'], $row['id']);
                            $stmt_check_adopsi->execute();
                            $stmt_check_adopsi->bind_result($adopsi_id_fetched);
                            if ($stmt_check_adopsi->fetch()) {
                                $is_adopted_by_current_user = true;
                                $adopsi_id = $adopsi_id_fetched;
                            }
                            $stmt_check_adopsi->close();
                        }
                    }


                    if ($is_adopted_by_current_user) {
                        // Jika sudah login dan sudah adopsi: Tombol "Mulai Berinteraksi"
                        // Mengarah ke interaksi_anak.php dengan adopsi_id
                        echo "    <a href='interaksi_anak.php?adopsi_id=" . htmlspecialchars($adopsi_id) . "' class='btn-interact-custom'>Mulai Berinteraksi</a>"; // Menggunakan kelas kustom
                    } else {
                        // Jika sudah login dan belum adopsi: Tombol "Adopsi Non-Finansial" (memicu AJAX)
                        $child_id_for_js = isset($row['id']) ? $row['id'] : '';
                        $child_name_for_js = isset($row['nama']) ? addslashes($row['nama']) : ''; // Escape for JS string
                        echo "    <button class='btn-adopt-custom' onclick='confirmAdoption(\"$child_id_for_js\", \"$child_name_for_js\")'>Adopsi Non-Finansial</button>"; // Menggunakan kelas kustom
                    }
                } else {
                    // Jika belum login: Tombol "Login untuk Adopsi Non-Finansial" (mengarahkan ke halaman login)
                    echo "    <a href='login.php' class='btn-adopt-custom'>Login untuk Adopsi Non-Finansial</a>"; // Menggunakan kelas kustom
                }
                echo "  </div>";
                echo "</div>";

                // Membuat modal untuk setiap profil
                echo "<div id='modal-$id' class='modal'>";
                echo "  <div class='modal-content'>";
                echo "    <span class='close-modal' onclick='closeModal(\"$id\")'>×</span>";
                echo "    <div class='modal-image'>";
                echo "      <img src='$foto_path' alt='Foto $nama' onerror=\"this.src='images/no-photo.png';\">";
                echo "    </div>";
                echo "    <h2>$nama</h2>";
                echo "    <p><strong>Usia:</strong> " . (is_numeric($usia) ? "$usia tahun" : $usia) . "</p>";
                echo "    <p><strong>Asal:</strong> $asal</p>";
                echo "    <h3>Cerita $nama:</h3>";
                echo "    <p>" . nl2br($cerita) . "</p>"; // nl2br untuk baris baru

                echo "    <h3>Detail Penyaluran Dana untuk $nama (Total: Rp " . number_format($total_disalurkan_anak_ini, 0, ',', '.') . "):</h3>";
                echo $detail_pengeluaran_html; // Tampilkan daftar pengeluaran detail
                echo "  </div>";
                echo "</div>";
            }
        } else {
            echo "<p class='no-data animate-on-scroll'>Belum ada data profil anak yang tersedia. Silakan periksa database Anda.</p>";
        }
        ?>
    </section>
</main>

<footer>
    <div class="footer-container">
        <div class="footer-column animate-on-scroll">
            <h3>Rumah AYP</h3>
            <p>Yayasan sosial yang berdedikasi untuk membantu mereka yang dibutuhkan dan menciptakan masa depan yang lebih baik.</p>
        </div>

        <div class="footer-column animate-on-scroll">
            <h3>Navigasi Cepat</h3>
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="about.php">Tentang Kami</a></li>
                <li><a href="program.php">Program Donasi</a></li>
                <li><a href="berita.php">Berita & Artikel</a></li>
                <li><a href="profilyatim.php">Profil Anak Asuh</a></li>
            </ul>
        </div>

        <div class="footer-column animate-on-scroll">
            <h3>Jam Layanan</h3>
            <p>Senin - Jumat: 08.00 - 17.00 WIB</p>
            <p>Sabtu - Minggu: Tutup (Kecuali acara khusus)</p>
        </div>

        <div class="footer-column animate-on-scroll">
            <h3>Terhubung Dengan Kami</h3>
            <div class="footer-icons">
                <a href="https://www.instagram.com/fallstirkta?igsh=MWtnOXo1d2dzNG94eQ==" target="_blank" aria-label="Instagram Rumah AYP">
                    <img src="https://cdn-icons-png.flaticon.com/512/174/174855.png" alt="Instagram">
                </a>
                <a href="https://wa.me/6285808436591" target="_blank" aria-label="WhatsApp Rumah AYP">
                    <img src="https://cdn-icons-png.flaticon.com/512/733/733585.png" alt="WhatsApp">
                </a>
                <a href="https://maps.google.com/?q=Jati, Sidoarjo" target="_blank" aria-label="Lokasi Rumah AYP di Google Maps">
                    <img src="https://cdn-icons-png.flaticon.com/512/684/684908.png" alt="Maps">
                </a>
            </div>
          <p style="margin-top: 15px;">Alamat: jalan jati selatan 3 </p>
        </div>
    </div>
    <div class="footer-bottom">
        <p>© <?php echo date('Y'); ?> Rumah AYP. Hak Cipta Dilindungi Undang-Undang.</p>
    </div>
</footer>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- Navbar Scroll Logic ---
        const mainNavbar = document.getElementById('mainNavbar');
        const navbarLogo = document.getElementById('navbarLogo');
        // Atur opacity awal logo navbar menjadi 1 agar selalu terlihat di halaman profilyatim.php
        navbarLogo.style.opacity = '1';
        navbarLogo.style.transition = 'none';

        window.addEventListener('scroll', function() {
            if (window.scrollY > 30) {
                mainNavbar.classList.add('scrolled');
            } else {
                mainNavbar.classList.remove('scrolled');
            }
        });
        if (window.scrollY > 30) {
            mainNavbar.classList.add('scrolled');
        } else {
            mainNavbar.classList.remove('scrolled');
        }

        // --- Hamburger Menu Logic ---
        const hamburgerIcon = document.getElementById('hamburgerIcon');
        const mobileNavOverlay = document.getElementById('mobileNavOverlay');
        const mobileNavLinks = mobileNavOverlay.querySelectorAll('a');

        hamburgerIcon.addEventListener('click', function() {
            mobileNavOverlay.classList.toggle('open');
            hamburgerIcon.querySelector('i').classList.toggle('fa-bars');
            hamburgerIcon.querySelector('i').classList.toggle('fa-times');
            document.body.classList.toggle('no-scroll');
        });

        mobileNavLinks.forEach(link => {
            link.addEventListener('click', () => {
                if (mobileNavOverlay.classList.contains('open')) {
                    mobileNavOverlay.classList.remove('open');
                    hamburgerIcon.querySelector('i').classList.remove('fa-times');
                    hamburgerIcon.querySelector('i').classList.add('fa-bars');
                    document.body.classList.remove('no-scroll');
                }
            });
        });

        // Function to check if an element is in viewport
        function isInViewport(element, offset = 0) {
            if (!element) return false;
            const rect = element.getBoundingClientRect();
            const viewportHeight = (window.innerHeight || document.documentElement.clientHeight);
            return (
                rect.top <= (viewportHeight - offset) &&
                rect.bottom >= offset
            );
        }

        // Function to add animation class when element enters viewport
        function animateOnScroll() {
            // Animate header
            const headerElement = document.querySelector('header.animate-on-scroll:not(.animated)');
            if (headerElement && isInViewport(headerElement, 100)) {
                headerElement.classList.add('animated');
            }

            // Animate profile intro
            const profileIntro = document.querySelector('.profile-intro.animate-on-scroll:not(.animated)');
            if (profileIntro && isInViewport(profileIntro, 100)) {
                profileIntro.classList.add('animated');
            }

            // Animate profile cards with stagger
            const profileCards = document.querySelectorAll('.profile-card.animate-on-scroll:not(.animated)');
            profileCards.forEach((card, index) => {
                if (isInViewport(card, 80)) {
                    setTimeout(() => {
                        card.classList.add('animated');
                    }, index * 150); // Stagger animation
                }
            });

            // Animate no data message if present
            const noDataMessage = document.querySelector('.no-data.animate-on-scroll:not(.animated)');
            if (noDataMessage && isInViewport(noDataMessage, 100)) {
                noDataMessage.classList.add('animated');
            }

            // Animate footer columns
            const footerColumns = document.querySelectorAll('footer .footer-column.animate-on-scroll:not(.animated)');
            footerColumns.forEach((col, index) => {
                if (isInViewport(col, 80)) {
                    setTimeout(() => {
                        col.classList.add('animated');
                    }, index * 100);
                }
            });
        }

        // Functions for modal
        window.openModal = function(id) {
            document.getElementById('modal-' + id).style.display = 'block';
            document.body.style.overflow = 'hidden'; // Prevent body scrolling when modal is open
        };

        window.closeModal = function(id) {
            document.getElementById('modal-' + id).style.display = 'none';
            document.body.style.overflow = 'auto'; // Allow body scrolling again
        };

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        };

        // Handle image loading errors
        document.querySelectorAll('.profile-image img, .modal-image img').forEach(img => {
            img.onerror = function() {
                this.src = 'images/no-photo.png'; // Fallback image if original fails
            };
        });

        // Fungsi untuk konfirmasi adopsi non-finansial
        window.confirmAdoption = function(childId, childName) {
            // Check if user is logged in
            <?php if (!isset($_SESSION['user_id'])): ?>
                alert("Anda harus login untuk mengadopsi anak secara non-finansial.");
                window.location.href = 'login.php'; // Redirect to login page
                return; // Stop function execution
            <?php endif; ?>

            if (confirm("Apakah Anda yakin ingin mengadopsi " + childName + " secara non-finansial? Anda akan bisa berinteraksi lebih dekat melalui Profil Anda.")) {
                initiateNonFinancialAdoption(childId);
            }
        };

        function initiateNonFinancialAdoption(childId) {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'process_adoption.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                if (xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        alert(response.message);
                        // REDIRECT KE HALAMAN INTERAKSI BARU SETELAH SUKSES ADOPSI
                        window.location.href = 'interaksi_anak.php?adopsi_id=' + response.adopsi_id; // Menggunakan adopsi_id dari respons
                    } else {
                        alert("Gagal mengadopsi: " + response.message);
                    }
                } else {
                    alert('Terjadi kesalahan saat memproses permintaan.');
                }
            };
            xhr.onerror = function() {
                alert('Terjadi kesalahan jaringan.');
            };
            xhr.send('child_id=' + childId);
        }

        // Run on page load and every time on scroll
        animateOnScroll(); // Initial check on load
        window.addEventListener('scroll', animateOnScroll);
    });
</script>
</body>
</html>
<?php
$conn->close();
?>