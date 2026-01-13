<?php
session_start();
// Pastikan ini mengarah ke file konfigurasi yang benar
require_once 'config.php'; // Ganti dari 'config.php' menjadi 'db_config.php'

// --- LOGIKA PENGAMBILAN DATA UNTUK GRAFIK ---

// 1. Data untuk Donut Chart "Progres Dana" (Donasi Terkumpul vs Sisa Dana)
$total_donasi_terkumpul = 0;
$total_pengeluaran_donasi_global = 0;

// MENGAMBIL TOTAL DONASI DARI TABEL 'DONASI' DENGAN STATUS 'PAID'
// Ini akan mencakup donasi finansial DAN donasi koin kuis
$sql_donasi_terkumpul = "SELECT SUM(jumlah) AS total_donasi_terkumpul FROM donasi WHERE status_pembayaran = 'paid'";
$result_donasi = $conn->query($sql_donasi_terkumpul);

if ($result_donasi && $result_donasi->num_rows > 0) {
    $row_donasi = $result_donasi->fetch_assoc();
    $total_donasi_terkumpul = (float)$row_donasi['total_donasi_terkumpul'];
}

// Mengambil total pengeluaran keseluruhan dari tabel 'pengeluaran' untuk sisa dana
$sql_total_pengeluaran_global = "SELECT SUM(jumlah) AS total_pengeluaran_global FROM pengeluaran";
$result_total_pengeluaran_global = $conn->query($sql_total_pengeluaran_global);

if ($result_total_pengeluaran_global && $result_total_pengeluaran_global->num_rows > 0) {
    $row_global_pengeluaran = $result_total_pengeluaran_global->fetch_assoc();
    $total_pengeluaran_donasi_global = (float)$row_global_pengeluaran['total_pengeluaran_global'];
}

$sisa_dana = $total_donasi_terkumpul - $total_pengeluaran_donasi_global;

// Pastikan sisa dana tidak negatif, jika pengeluaran melebihi donasi terkumpul
if ($sisa_dana < 0) {
    $sisa_dana = 0;
}

// Data untuk Donut Chart "Progres Dana"
$labels_progres_dana = ['Dana Disalurkan', 'Sisa Dana'];
$data_progres_dana = [$total_pengeluaran_donasi_global, $sisa_dana];
$colors_progres_dana = ['rgba(255, 159, 64, 0.8)', 'rgba(75, 192, 192, 0.8)'];

// 2. Data untuk Donut Chart "Dana Disalurkan per Kategori"
$labels_pengeluaran_kategori = [];
$data_pengeluaran_kategori = [];

$sql_pengeluaran_per_tipe = "SELECT tipe, SUM(jumlah) AS total_jumlah FROM pengeluaran GROUP BY tipe";
$result_pengeluaran_per_tipe = $conn->query($sql_pengeluaran_per_tipe);

if ($result_pengeluaran_per_tipe && $result_pengeluaran_per_tipe->num_rows > 0) {
    while ($row = $result_pengeluaran_per_tipe->fetch_assoc()) {
        $labels_pengeluaran_kategori[] = htmlspecialchars($row['tipe']);
        $data_pengeluaran_kategori[] = (float)$row['total_jumlah'];
    }
} else {
    $labels_pengeluaran_kategori = ['Belum Ada Data'];
    $data_pengeluaran_kategori = [1];
}

// 3. Data untuk Donut Chart "Total Donasi per Program"
$labels_donasi_per_program = [];
$data_donasi_per_program = [];

$sql_donasi_per_program = "SELECT p.nama AS program_nama, SUM(d.jumlah) AS total_jumlah
                           FROM donasi d
                           JOIN programm p ON d.programm_id = p.id
                           WHERE d.status_pembayaran = 'paid' AND d.programm_id IS NOT NULL
                           GROUP BY p.nama";
$result_donasi_per_program = $conn->query($sql_donasi_per_program);

if ($result_donasi_per_program && $result_donasi_per_program->num_rows > 0) {
    while ($row = $result_donasi_per_program->fetch_assoc()) {
        $labels_donasi_per_program[] = htmlspecialchars($row['program_nama']);
        $data_donasi_per_program[] = (float)$row['total_jumlah'];
    }
} else {
    $labels_donasi_per_program = ['Belum Ada Donasi Program'];
    $data_donasi_per_program = [1];
}

// --- LOGIKA PENGAMBILAN DATA SPONSOR ---
$active_sponsors = [];
$sql_sponsors = "SELECT nama_sponsor, logo_url, website_url, deskripsi FROM sponsors WHERE is_active = TRUE ORDER BY nama_sponsor ASC";
$result_sponsors = $conn->query($sql_sponsors);

if ($result_sponsors && $result_sponsors->num_rows > 0) {
    while ($row = $result_sponsors->fetch_assoc()) {
        $active_sponsors[] = $row;
    }
}
// --- AKHIR LOGIKA PENGAMBILAN DATA SPONSOR ---

$conn->close(); // Tutup koneksi setelah semua data diambil
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tentang Kami - Rumah AYP</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Palet Warna (Modern Clean with Dynamic Gradients) - Konsisten dengan index.php */
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
        }

        /* General Body & Typography - Latar belakang gradien baru */
        body {
            font-family: 'Montserrat', sans-serif; /* Menggunakan Montserrat */
            line-height: 1.7;
            margin: 0;
            padding: 0;
            /* Latar belakang body diubah ke gradien baru */
            background: linear-gradient(135deg, #E0F2F1, #E3F2FD, #EDE7F6); /* Hijau Soft, Biru Soft, Ungu Soft */
            color: var(--color-text-primary);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            overflow-x: hidden;
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

        /* Navbar - Konsisten dengan index.php */
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
            color: var(--color-text-primary); /* Warna ikon gelap */
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
        .mobile-nav .auth-buttons-mobile .profile-link,
        .mobile-nav .auth-buttons-mobile .btn-login {
            background-color: rgba(0, 0, 0, 0.05);
            color: var(--color-text-primary);
            border: 1px solid var(--color-border-subtle);
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

        /* Profile link for logged-in users */
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
        .btn-login { /* Tombol Register juga pakai style ini di about.php */
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
        .btn-register { /* Tombol Login juga pakai style ini di about.php */
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

        /* REBRANDED HEADER STYLES */
        header {
            background: linear-gradient(135deg, var(--color-gradient-start), var(--color-gradient-end)); /* Gradien dari variabel */
            color: #ffffff;
            padding: 80px 0; /* Padding lebih besar */
            text-align: center;
            margin-top: 75px; /* Jarak dari fixed navbar */
            box-shadow: var(--shadow-strong); /* Bayangan lebih kuat */
            border-bottom-left-radius: 25px; /* Sudut membulat */
            border-bottom-right-radius: 25px;
        }

        header h1 {
            font-size: 3em; /* Ukuran font lebih besar */
            margin-bottom: 20px;
            text-shadow: 2px 2px 6px rgba(0,0,0,0.3);
            font-weight: 800;
        }

        header p {
            font-size: 1.3em;
            max-width: 900px;
            margin: 0 auto;
            padding: 0 30px;
            font-weight: 500; /* Sedikit lebih tebal */
            color: rgba(255, 255, 255, 0.9);
        }

        main {
            padding: 40px 20px;
            max-width: 1200px; /* Lebar maksimum main content */
            margin: 40px auto; /* Margin di atas dan bawah */
        }

        .about-section, .chart-section, .sponsors-section, .call-to-action {
            background: var(--color-bg-secondary); /* Latar belakang putih */
            border-radius: 15px; /* Radius sudut konsisten */
            padding: 50px 40px; /* Padding konsisten */
            box-shadow: var(--shadow-medium); /* Bayangan konsisten */
            margin-bottom: 40px;
            border: 1px solid var(--color-border-subtle); /* Border halus konsisten */
            text-align: center;
            position: relative;
            overflow: hidden; /* Penting untuk animasi */
        }
        .about-section.fade-in, .chart-section.fade-in, .sponsors-section.fade-in, .call-to-action.fade-in {
             opacity: 1;
             transform: translateY(0);
        }


        .about-section h2, .chart-section h2, .sponsors-section h2 {
            color: var(--color-text-primary); /* Warna judul utama */
            font-size: 2.8em; /* Ukuran judul section */
            margin-bottom: 35px;
            border-bottom: 4px solid;
            border-image: linear-gradient(to right, var(--color-gradient-start), var(--color-gradient-end)) 1;
            padding-bottom: 15px;
            display: inline-block;
            font-weight: 800;
        }

        .about-section p {
            line-height: 1.8;
            margin-bottom: 20px;
            font-size: 1.05em;
        }

        .about-section ul {
            list-style: none;
            padding: 0;
            margin-left: 0;
            margin-bottom: 20px;
        }

        .about-section ul li {
            margin-bottom: 12px; /* Jarak antar list item */
            line-height: 1.7;
            padding-left: 30px; /* Ruang untuk ikon */
            position: relative;
            color: var(--color-text-secondary);
            font-size: 1.0em;
        }

        .about-section ul li::before {
            content: '\f058'; /* FontAwesome check-circle */
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            color: var(--color-gradient-end); /* Warna hijau dari gradien */
            position: absolute;
            left: 0;
            top: 0;
            font-size: 1.2em;
        }


        /* Call to action section */
        .call-to-action {
            text-align: center;
            padding: 50px;
            background-color: var(--color-bg-secondary); /* Atau bisa diganti dengan warna soft gradien */
            border-radius: 15px;
            box-shadow: var(--shadow-medium);
            margin-top: 40px;
        }
        .call-to-action h3 {
            color: var(--color-text-primary);
            font-size: 2.2em;
            margin-bottom: 20px;
            font-weight: 700;
        }

        .call-to-action p {
            font-size: 1.1em;
            margin-bottom: 30px;
            color: var(--color-text-secondary);
        }

        .btn-primary {
            display: inline-block;
            background: linear-gradient(45deg, var(--color-gradient-start), var(--color-gradient-end));
            color: #ffffff;
            padding: 15px 35px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 700;
            font-size: 1.1em;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-medium);
            border: none;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-strong);
            opacity: 0.9;
        }

        /* Section Separator */
        .section-separator {
            border: 0;
            height: 1px;
            background-image: linear-gradient(to right, rgba(79, 195, 247, 0.2), var(--color-gradient-start), rgba(79, 195, 247, 0.2));
            margin: 50px auto;
            width: 70%;
            opacity: 0.6;
        }

        /* Animations for content sections */
        .animate-on-scroll {
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.6s ease-out, transform 0.6s ease-out;
        }
        .animate-on-scroll.animated { /* Menggunakan 'animated' dari JS */
            opacity: 1;
            transform: translateY(0);
        }
        /* Specific for staggered animation (e.g., value items, vision/mission boxes) */
        .animate-on-scroll.pop-in { /* Untuk .value-item */
            opacity: 1;
            transform: scale(1);
            transition: opacity 0.5s ease-out, transform 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }

        /* Vision & Mission Grid */
        .vision-mission-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 30px;
            margin-top: 40px;
            margin-bottom: 40px;
        }

        .vision-box, .mission-box {
            background-color: var(--color-bg-primary); /* Latar belakang soft gray */
            padding: 35px;
            border-radius: 15px;
            box-shadow: var(--shadow-light);
            text-align: left;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
            border: 1px solid var(--color-border-subtle);
            opacity: 0;
            transform: translateY(30px); /* Mulai dari bawah sedikit */
            transition: opacity 0.7s ease-out, transform 0.7s ease-out;
        }
        .vision-box.animated, .mission-box.animated { /* Menggunakan 'animated' dari JS */
            opacity: 1;
            transform: translateY(0);
        }

        .vision-box h3, .mission-box h3 {
            color: var(--color-text-primary);
            font-size: 2em; /* Ukuran heading box */
            margin-bottom: 20px;
            border-bottom: 2px solid var(--color-accent-secondary); /* Warna aksen */
            padding-bottom: 10px;
            font-weight: 700;
        }

        .mission-box ul {
            list-style: none;
            padding: 0;
            margin-top: 15px;
        }

        .mission-box ul li {
            position: relative;
            padding-left: 35px;
            margin-bottom: 15px;
            line-height: 1.7;
            color: var(--color-text-secondary);
        }

        .mission-box ul li::before {
            content: '\f058'; /* FontAwesome check-circle */
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            color: var(--color-gradient-start); /* Warna biru dari gradien */
            position: absolute;
            left: 0;
            top: 0;
            font-size: 1.2em;
        }

        /* Key Values Section - Tag-like display */
        .key-values-container {
            margin-top: 50px;
            text-align: center;
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.7s ease-out, transform 0.7s ease-out 0.2s;
        }
        .key-values-container.animated { /* Menggunakan 'animated' dari JS */
            opacity: 1;
            transform: translateY(0);
        }

        .key-values-wrapper {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 15px;
            margin-top: 30px;
        }

        .value-item {
            background-color: #E8F5E9; /* Soft Green Background */
            border: 1px solid #D4EDDA; /* Matching Border */
            padding: 12px 28px;
            border-radius: 30px;
            font-weight: 600;
            color: #2E7D32; /* Darker Green Text */
            white-space: nowrap;
            transition: background-color 0.3s ease, transform 0.3s ease, box-shadow 0.3s ease;
            cursor: default;
            transform: scale(0.8);
            opacity: 0;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1); /* Subtle shadow */
        }

        .value-item:hover {
            background-color: #C8E6C9; /* Lighter green on hover */
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
        }
        .value-item.pop-in {
            transform: scale(1);
            opacity: 1;
        }


        /* Styles for the chart section */
        .chart-section {
            padding-top: 60px; /* Jarak dari atas konten */
            min-height: 500px; /* Biar ada ruang untuk grafik */
            background: var(--color-bg-secondary); /* Latar belakang putih */
            border-radius: 15px; /* Radius sudut konsisten */
            padding: 50px 40px; /* Padding konsisten */
            box-shadow: var(--shadow-medium); /* Bayangan konsisten */
            margin-bottom: 40px;
            border: 1px solid var(--color-border-subtle); /* Border halus konsisten */
            text-align: center;
            position: relative;
            overflow: hidden;
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.6s ease-out, transform 0.6s ease-out;
        }
        .chart-section.animated {
             opacity: 1;
             transform: translateY(0);
        }
        .chart-section h2 {
            color: var(--color-text-primary); /* Warna judul utama */
            font-size: 2.8em; /* Ukuran judul section */
            margin-bottom: 35px;
            border-bottom: 4px solid;
            border-image: linear-gradient(to right, var(--color-gradient-start), var(--color-gradient-end)) 1;
            padding-bottom: 15px;
            display: inline-block;
            font-weight: 800;
        }
        .chart-section p {
            margin-bottom: 40px;
            font-size: 1.1em;
        }
        .chart-container-wrapper {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-around;
            align-items: flex-start;
            gap: 30px;
            margin-top: 30px;
        }
        .chart-container {
            flex: 1 1 30%;
            min-width: 300px;
            max-width: 450px;
            height: 380px; /* Tinggi yang cukup untuk chart dan legend */
            padding: 25px;
            background-color: var(--color-bg-primary); /* Background soft gray */
            border-radius: 12px;
            box-shadow: var(--shadow-light);
            box-sizing: border-box;
            border: 1px solid var(--color-border-subtle);
            text-align: left; /* Teks heading chart di kiri */
        }
        .chart-container h3 {
            font-size: 1.5em;
            color: var(--color-text-primary);
            margin-bottom: 20px;
            text-align: center; /* Judul chart di tengah */
            font-weight: 700;
        }
        @media (max-width: 1000px) {
            .chart-container {
                flex: 1 1 45%;
                height: 350px;
            }
        }
        @media (max-width: 768px) {
            .chart-container {
                flex: 1 1 95%;
                height: 320px;
                padding: 20px;
            }
            .chart-section {
                min-height: auto;
            }
        }

        /* Styles for the sponsor section */
        .sponsors-section {
            background: var(--color-bg-secondary);
            border-radius: 15px;
            padding: 50px 40px;
            box-shadow: var(--shadow-medium);
            margin-bottom: 40px;
            border: 1px solid var(--color-border-subtle);
            text-align: center;
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.6s ease-out, transform 0.6s ease-out;
        }
        .sponsors-section.animated {
            opacity: 1;
            transform: translateY(0);
        }
        .sponsors-section h2 {
            color: var(--color-text-primary);
            font-size: 2.8em;
            margin-bottom: 35px;
            border-bottom: 4px solid;
            border-image: linear-gradient(to right, var(--color-gradient-start), var(--color-gradient-end)) 1;
            padding-bottom: 15px;
            display: inline-block;
            font-weight: 800;
        }
        .sponsors-section p {
            font-size: 1.1em;
            margin-bottom: 30px;
        }
        .sponsor-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); /* Min-width disesuaikan */
            gap: 40px; /* Jarak antar kartu lebih besar */
            margin-top: 40px;
        }
        .sponsor-item {
            background: var(--color-bg-primary); /* Background soft gray */
            padding: 30px; /* Padding lebih besar */
            border-radius: 15px; /* Radius sudut lebih besar */
            box-shadow: var(--shadow-light); /* Shadow lebih menonjol */
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            text-align: center;
            border: 1px solid var(--color-border-subtle); /* Border lebih solid */
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            min-height: 300px; /* Tinggi minimum untuk konsistensi */
        }
        .sponsor-item:hover {
            transform: translateY(-8px) scale(1.02); /* Lift dan scale lebih dramatis */
            box-shadow: var(--shadow-medium); /* Shadow lebih kuat */
        }
        .sponsor-logo-wrapper {
            background-color: var(--color-bg-secondary); /* Latar belakang putih untuk logo */
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px; /* Jarak dari nama */
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            width: 100%;
            max-width: 160px; /* Kontrol ukuran wrapper */
            height: 100px; /* Tinggi tetap untuk konsistensi logo area */
            display: flex;
            justify-content: center;
            align-items: center;
            border: 1px solid var(--color-border-subtle);
            flex-shrink: 0;
        }
        .sponsor-item img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        .sponsor-item h3 {
            font-size: 1.8em; /* Nama sponsor lebih besar */
            color: var(--color-text-primary);
            margin-bottom: 15px; /* Jarak dari deskripsi */
            font-weight: 700;
            line-height: 1.3;
        }
        .sponsor-item p {
            font-size: 1em;
            color: var(--color-text-secondary);
            margin-bottom: 25px;
            flex-grow: 1;
            line-height: 1.6;
        }
        .sponsor-item a.btn-sponsor {
            background: linear-gradient(45deg, var(--color-gradient-start), var(--color-gradient-end)); /* Warna branding utama */
            color: white;
            padding: 12px 25px;
            border-radius: 30px;
            text-decoration: none;
            font-size: 1em;
            font-weight: 600;
            box-shadow: var(--shadow-light);
            transition: all 0.3s ease;
            flex-shrink: 0;
        }
        .sponsor-item a.btn-sponsor:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
            opacity: 0.9;
        }

        /* Style untuk pesan jika tidak ada sponsor */
        .no-sponsors-message {
            background-color: #FFF8E1; /* Warna warning kuning muda */
            color: #D3A70F; /* Teks kuning gelap */
            padding: 30px;
            border-radius: 12px;
            border: 1px solid #FFE082;
            font-size: 1.1em;
            text-align: center;
            margin-top: 30px;
            animation: fadeInScale 0.8s ease-out;
        }
        .no-sponsors-message a {
            color: #D3A70F;
            font-weight: bold;
            text-decoration: underline;
        }
        .no-sponsors-message a:hover {
            color: #A1887F; /* Coklat kemerahan */
        }


        /* Footer Styles - Menggunakan variabel dan gaya konsisten */
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
            flex: 1 1 250px; /* Fleksibel dengan ukuran dasar 250px */
            min-width: 200px;
        }

        .footer-column h3 {
            font-size: 1.5em;
            margin-bottom: 20px;
            color: #FFFFFF;
            border-bottom: 2px solid var(--color-accent-secondary); /* Aksen sekunder */
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
        /* Filter ikon footer untuk menjaga warna aslinya, atau sesuaikan jika ikon monokrom */
        .footer-icons img {
            width: 28px;
            height: 28px;
            vertical-align: middle;
            filter: none; /* Pertahankan warna asli ikon */
        }

        .footer-bottom {
            margin-top: 40px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 20px;
            text-align: center;
            font-size: 0.8em;
            color: rgba(255, 255, 255, 0.6);
        }


        /* Responsive Adjustments */
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
            header p {
                font-size: 1.2em;
            }
            .about-section, .chart-section, .sponsors-section, .call-to-action {
                padding: 40px 30px;
            }
            .about-section h2, .chart-section h2, .sponsors-section h2 {
                font-size: 2.4em;
            }
            .vision-box h3, .mission-box h3 {
                font-size: 1.8em;
            }
            .chart-container {
                min-width: 280px;
            }
            .sponsor-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .navbar {
                flex-wrap: wrap;
                justify-content: space-between;
                height: auto;
                padding: 15px 20px;
                background-color: rgba(255, 255, 255, 0.98); /* Lebih solid di mobile */
                box-shadow: var(--shadow-medium);
                width: 100%;
                top: 0;
                border-radius: 0;
            }
            .navbar .logo {
                margin-bottom: 0;
            }
            .nav-links, .navbar .auth-buttons {
                display: none; /* Sembunyikan di sini, akan ditangani oleh hamburger */
            }
            /* Hamburger Menu untuk Mobile (tampilkan) */
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
            .mobile-nav-overlay { /* Overlay Navigasi Mobile */
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
                margin-top: 65px; /* Sesuaikan lagi */
            }
            header h1 {
                font-size: 2em;
            }
            header p {
                font-size: 1.1em;
                padding: 0 20px;
            }
            .about-section, .chart-section, .sponsors-section, .call-to-action {
                padding: 30px 20px;
            }
            .about-section h2, .chart-section h2, .sponsors-section h2 {
                font-size: 2em;
                margin-bottom: 25px;
            }
            .vision-mission-grid {
                grid-template-columns: 1fr;
            }
            .vision-box h3, .mission-box h3 {
                font-size: 1.6em;
            }
            .chart-section {
                min-height: auto; /* Disesuaikan untuk mobile */
            }
            .chart-container-wrapper {
                flex-direction: column;
                align-items: center;
            }
            .chart-container {
                width: 100%;
                height: 300px;
                min-width: unset;
            }
            .sponsor-grid {
                grid-template-columns: 1fr; /* Stack di mobile */
            }
            .footer-container {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
            .footer-column {
                min-width: 90%;
            }
            .footer-icons {
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .navbar .logo img {
                width: 90px;
            }
            header h1 {
                font-size: 1.8em;
            }
            header p {
                font-size: 1em;
            }
            .about-section h2, .chart-section h2, .sponsors-section h2 {
                font-size: 1.6em;
            }
            .about-section p, .call-to-action p {
                font-size: 0.95em;
            }
            .vision-box h3, .mission-box h3 {
                font-size: 1.4em;
            }
            .value-item {
                padding: 10px 20px;
                font-size: 0.9em;
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
        <li><a href="about.php" class="active">Tentang Kami</a></li>
        <li><a href="program.php">Program</a></li>
        <li><a href="berita.php">Berita</a></li>
        <li><a href="profilyatim.php">Profil Anak</a></li>
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
        <li><a href="about.php" class="active">Tentang Kami</a></li>
        <li><a href="program.php">Program</a></li>
        <li><a href="berita.php">Berita</a></li>
        <li><a href="profilyatim.php">Profil Anak</a></li>
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

    <header>
        <h1>Mewujudkan Harapan, Membangun Masa Depan</h1>
        <p>Kami adalah Rumah AYP, sebuah yayasan yang berdedikasi penuh untuk menyemai harapan dan membentuk masa depan cerah bagi anak-anak yatim, piatu, dan dhuafa di seluruh Indonesia.</p>
    </header>

    <main>
        <section class="about-section animate-on-scroll">
            <h2 class="animate-on-scroll">Siapa Kami?</h2>
            <div class="about-introduction animate-on-scroll">
                <p><strong>Rumah AYP</strong> lahir dari kepedulian mendalam terhadap potensi luar biasa yang tersembunyi dalam diri setiap anak. Kami percaya bahwa dengan lingkungan yang aman, mendidik, dan penuh kasih sayang, setiap anak, tanpa terkecuali, berhak mendapatkan kesempatan terbaik untuk tumbuh kembang. Sejak berdiri, kami telah berkomitmen untuk menjadi rumah kedua bagi mereka, tempat mereka bisa merasa nyaman, belajar, dan berani bermimpi.</p>
                <p>Program-program kami dirancang komprehensif, tidak hanya memenuhi kebutuhan dasar seperti pangan dan sandang, tetapi juga fokus pada pengembangan pendidikan, penguatan karakter, dan peningkatan keterampilan hidup. Kami ingin memastikan setiap anak di Rumah AYP memiliki bekal yang cukup untuk menghadapi masa depan dengan percaya diri, mandiri, dan berdaya saing.</p>
            </div>

            <hr class="section-separator animate-on-scroll">

            <h2 class="animate-on-scroll" id="visiMisiHeading">Visi & Misi Kami</h2>
            <div class="vision-mission-grid">
                <div class="vision-box animate-on-scroll">
                    <h3>Visi Kami</h3>
                    <p>Menjadi platform digital terpercaya dan inovatif yang memfasilitasi kebaikan serta kepedulian sosial, dengan mengedepankan transparansi, kemudahan akses, dan pemberdayaan berkelanjutan bagi anak yatim, piatu, dan masyarakat kurang mampu di Indonesia.</p>
                    <p class="mt-3">Kami membayangkan sebuah dunia di mana teknologi menjadi jembatan utama untuk menghubungkan hati dermawan dengan mereka yang membutuhkan. Visi kami adalah menciptakan ekosistem digital yang tidak hanya efisien dalam penyaluran bantuan, tetapi tetapi juga menjadi sumber inspirasi dan perubahan positif yang berkelanjutan.</p>
                </div>
                <div class="mission-box animate-on-scroll">
                    <h3>Misi Kami</h3>
                    <p>Untuk mencapai visi tersebut, kami mengemban misi sebagai berikut:</p>
                    <ul>
                        <li><span class="highlight-text">Menyediakan informasi yang akurat dan terkini</span> mengenai setiap program bantuan dan kegiatan yayasan, memastikan setiap langkah kami dapat dipantau.</li>
                        <li><span class="highlight-text">Membangun sistem donasi yang aman, transparan, dan mudah diakses</span>, memungkinkan siapa saja untuk berpartisipasi dalam kebaikan tanpa hambatan.</li>
                        <li><span class="highlight-text">Memfasilitasi komunikasi dua arah yang efektif</span> antara donatur, yayasan, dan penerima manfaat, membangun hubungan yang kuat dan saling percaya.</li>
                        <li><span class="highlight-text">Mengembangkan layanan digital yang responsif, intuitif, dan inklusif</span>, memastikan teknologi kami dapat digunakan oleh semua lapisan masyarakat.</li>
                        <li><span class="highlight-text">Mendukung peningkatan kualitas hidup anak yatim dan kaum dhuafa</span> secara holistik melalui inovasi teknologi, pendidikan, dan pelatihan keterampilan.</li>
                    </ul>
                </div>
            </div>
            <hr class="section-separator animate-on-scroll">

            <h2 class="animate-on-scroll" id="nilaiKamiHeading">Nilai-Nilai Kami</h2>
            <div class="key-values-container animate-on-scroll">
                <p>Setiap tindakan dan keputusan di Rumah AYP dilandasi oleh nilai-nilai inti yang kami pegang teguh:</p>
                <div class="key-values-wrapper">
                    <div class="value-item">Kasih Sayang</div>
                    <div class="value-item">Integritas</div>
                    <div class="value-item">Tanggung Jawab</div>
                    <div class="value-item">Kemandirian</div>
                    <div class="value-item">Kolaborasi</div>
                    <div class="value-item">Inovasi</div>
                    <div class="value-item">Transparansi</div>
                </div>
            </div>
        </section>

        <section class="sponsors-section animate-on-scroll">
            <h2>Sponsor Kami</h2>
            <p>Terima kasih kepada para sponsor yang telah mendukung misi kami dan memungkinkan konversi koin Anda menjadi donasi nyata.</p>

            <?php if (!empty($active_sponsors)): ?>
                <div class="sponsor-grid">
                    <?php foreach ($active_sponsors as $sponsor): ?>
                        <div class="sponsor-item animate-on-scroll">
                            <div class="sponsor-logo-wrapper">
                                <?php if ($sponsor['logo_url']): ?>
                                    <img src="<?php echo htmlspecialchars($sponsor['logo_url']); ?>" alt="Logo <?php echo htmlspecialchars($sponsor['nama_sponsor']); ?>">
                                <?php else: ?>
                                    <div style="color: #bbb; font-size: 0.9em;">(Logo Tidak Tersedia)</div>
                                <?php endif; ?>
                            </div>
                            <h3><?php echo htmlspecialchars($sponsor['nama_sponsor']); ?></h3>
                            <?php if ($sponsor['deskripsi']): ?>
                                <p><?php echo nl2br(htmlspecialchars($sponsor['deskripsi'])); ?></p>
                            <?php endif; ?>
                            <?php if ($sponsor['website_url']): ?>
                                <a href="<?php echo htmlspecialchars($sponsor['website_url']); ?>" target="_blank" class="btn-sponsor">Kunjungi Website</a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-sponsors-message animate-on-scroll">
                    <p>Saat ini belum ada sponsor aktif yang mendukung program donasi koin kami. Jika Anda atau perusahaan Anda tertarik untuk menjadi sponsor dan membantu mewujudkan lebih banyak donasi, silakan <a href="kontak.php">hubungi kami</a>!</p>
                </div>
            <?php endif; ?>
        </section>


        <section class="chart-section animate-on-scroll">
            <h2>Laporan Dana Kami</h2>
            <p>Visualisasi progres donasi dan alokasi dana yang telah disalurkan.</p>

            <div class="chart-container-wrapper">
                <div class="chart-container">
                    <h3> Rekap Penggunaan </h3>
                    <canvas id="progresDanaChart"></canvas>
                </div>
                <div class="chart-container">
                    <h3>Dana Disalurkan per Kebutuhan</h3>
                    <canvas id="pengeluaranKategoriChart"></canvas>
                </div>
                <div class="chart-container">
                    <h3>Total Donasi per Program</h3>
                    <canvas id="donasiPerProgramChart"></canvas>
                </div>
            </div>
        </section>

        <section class="call-to-action animate-on-scroll">
            <h3>Mari Bergabung Bersama Kami!</h3>
            <p>Dukungan Anda sangat berarti. Jadilah bagian dari perubahan positif dalam kehidupan anak-anak yatim dan piatu. Bersama, kita wujudkan senyum dan masa depan yang lebih cerah.</p>
            <a href="program.php" class="btn-primary">Lihat Program Kami</a>
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
        // Data yang diambil dari PHP
        const totalDonasiTerkumpul = <?php echo $total_donasi_terkumpul; ?>;
        const totalPengeluaranGlobal = <?php echo $total_pengeluaran_donasi_global; ?>;
        const sisaDana = <?php echo $sisa_dana; ?>;


        const labelsProgresDana = <?php echo json_encode($labels_progres_dana); ?>;
        const dataProgresDana = <?php echo json_encode($data_progres_dana); ?>;
        const colorsProgresDana = <?php echo json_encode($colors_progres_dana); ?>;


        const labelsPengeluaranKategori = <?php echo json_encode($labels_pengeluaran_kategori); ?>;
        const dataPengeluaranKategori = <?php echo json_encode($data_pengeluaran_kategori); ?>;

        const labelsDonasiPerProgram = <?php echo json_encode($labels_donasi_per_program); ?>;
        const dataDonasiPerProgram = <?php echo json_encode($data_donasi_per_program); ?>;

        // --- Navbar Scroll Logic ---
        const mainNavbar = document.getElementById('mainNavbar');
        const navbarLogo = document.getElementById('navbarLogo'); // Ambil referensi logo navbar
        // Atur opacity awal logo navbar menjadi 1 agar selalu terlihat di halaman about.php
        navbarLogo.style.opacity = '1';
        navbarLogo.style.transition = 'none'; // Matikan transisi welcome overlay agar tidak mempengaruhi di sini

        window.addEventListener('scroll', function() {
            if (window.scrollY > 30) { // Jika discroll lebih dari 30px
                mainNavbar.classList.add('scrolled');
            } else {
                mainNavbar.classList.remove('scrolled');
            }
        });
        // Pemeriksaan awal saat halaman dimuat, untuk menerapkan gaya yang tepat
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
            document.body.classList.toggle('no-scroll'); // Mencegah scrolling body di mobile
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


        // Fungsi untuk memeriksa apakah elemen ada di viewport
        function isInViewport(element, offset = 0) {
            if (!element) return false;
            const rect = element.getBoundingClientRect();
            const viewportHeight = (window.innerHeight || document.documentElement.clientHeight);
            return (
                rect.top <= (viewportHeight - offset) &&
                rect.bottom >= offset
            );
        }

        // Fungsi untuk menambahkan kelas animasi saat elemen masuk viewport
        function animateOnScroll() {
            const elements = document.querySelectorAll('.animate-on-scroll:not(.animated)');
            elements.forEach(element => {
                if (isInViewport(element, 100)) { // Offset 100px
                    element.classList.add('animated');
                }
            });

            // Animasi untuk Vision & Mission Boxes
            const visionMissionBoxes = document.querySelectorAll('.vision-box.animate-on-scroll:not(.animated), .mission-box.animate-on-scroll:not(.animated)');
            visionMissionBoxes.forEach((box, index) => {
                if (isInViewport(box, 80)) {
                    setTimeout(() => {
                        box.classList.add('animated');
                    }, index * 150); // Staggered delay for boxes
                }
            });

            // Animasi untuk Key Value Items
            const keyValuesContainer = document.querySelector('.key-values-container.animate-on-scroll:not(.animated)');
            if (keyValuesContainer && isInViewport(keyValuesContainer, 80)) {
                keyValuesContainer.classList.add('animated'); // Animasi kontainer utama
                const valueItems = keyValuesContainer.querySelectorAll('.value-item:not(.pop-in)');
                valueItems.forEach((item, index) => {
                    setTimeout(() => {
                        item.classList.add('pop-in');
                    }, index * 120 + 300); // Staggered pop-in for individual values
                });
            }

            // Animasi untuk Sponsor Items (jika ada)
            const sponsorItems = document.querySelectorAll('.sponsor-item.animate-on-scroll:not(.animated)');
            sponsorItems.forEach((item, index) => {
                if (isInViewport(item, 80)) {
                    setTimeout(() => {
                        item.classList.add('animated');
                    }, index * 100);
                }
            });

            // Animasi untuk Chart Section (panggil chart hanya sekali)
            const chartSection = document.querySelector('.chart-section.animate-on-scroll:not(.animated)');
            if (chartSection && isInViewport(chartSection, 80)) {
                chartSection.classList.add('animated');
                createCharts(); // Panggil fungsi createCharts hanya sekali saat masuk viewport
            }
        }

        // Fungsi untuk membuat semua grafik Donut
        function createCharts() {
            // 1. Donut Chart untuk Progres Dana (Dana Disalurkan vs Sisa Dana)
            const ctxProgresDana = document.getElementById('progresDanaChart').getContext('2d');
            new Chart(ctxProgresDana, {
                type: 'doughnut',
                data: {
                    labels: labelsProgresDana,
                    datasets: [{
                        label: 'Jumlah (Rp)',
                        data: dataProgresDana,
                        backgroundColor: colorsProgresDana,
                        borderColor: colorsProgresDana.map(color => color.replace('0.8', '1')), // Border lebih solid
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw;
                                    const total = context.dataset.data.reduce((sum, current) => sum + current, 0);
                                    const percentage = (total === 0) ? 0 : ((value / total) * 100).toFixed(2);
                                    return `${label}: Rp ${value.toLocaleString('id-ID')} (${percentage}%)`;
                                }
                            }
                        },
                        title: {
                            display: false
                        },
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 15
                            }
                        }
                    }
                }
            });

            // 2. Donut Chart untuk Dana Disalurkan per Kategori
            const ctxPengeluaranKategori = document.getElementById('pengeluaranKategoriChart').getContext('2d');

            // Generate warna acak yang cerah
            const backgroundColorsKategori = dataPengeluaranKategori.map(() => {
                const r = Math.floor(Math.random() * 200);
                const g = Math.floor(Math.random() * 200);
                const b = Math.floor(Math.random() * 200);
                return `rgba(${r}, ${g}, ${b}, 0.8)`;
            });
            const borderColorsKategori = backgroundColorsKategori.map(color => color.replace('0.8', '1'));

            new Chart(ctxPengeluaranKategori, {
                type: 'doughnut',
                data: {
                    labels: labelsPengeluaranKategori,
                    datasets: [{
                        label: 'Jumlah (Rp)',
                        data: dataPengeluaranKategori,
                        backgroundColor: backgroundColorsKategori,
                        borderColor: borderColorsKategori,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw;
                                    const total = context.dataset.data.reduce((sum, current) => sum + current, 0);
                                    const percentage = (total === 0) ? 0 : ((value / total) * 100).toFixed(2);
                                    return `${label}: Rp ${value.toLocaleString('id-ID')} (${percentage}%)`;
                                }
                            }
                        },
                        title: {
                            display: false
                        },
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 15
                            }
                        }
                    }
                }
            });

            // 3. Donut Chart untuk Total Donasi per Program
            const ctxDonasiPerProgram = document.getElementById('donasiPerProgramChart').getContext('2d');

            const backgroundColorsDonasiProgram = dataDonasiPerProgram.map(() => {
                const r = Math.floor(Math.random() * 200);
                const g = Math.floor(Math.random() * 200);
                const b = Math.floor(Math.random() * 200);
                return `rgba(${r}, ${g}, ${b}, 0.8)`;
            });
            const borderColorsDonasiProgram = backgroundColorsDonasiProgram.map(color => color.replace('0.8', '1'));

            new Chart(ctxDonasiPerProgram, {
                type: 'doughnut',
                data: {
                    labels: labelsDonasiPerProgram,
                    datasets: [{
                        label: 'Jumlah Donasi (Rp)',
                        data: dataDonasiPerProgram,
                        backgroundColor: backgroundColorsDonasiProgram,
                        borderColor: borderColorsDonasiProgram,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw;
                                    const total = context.dataset.data.reduce((sum, current) => sum + current, 0);
                                    const percentage = (total === 0) ? 0 : ((value / total) * 100).toFixed(2);
                                    return `${label}: Rp ${value.toLocaleString('id-ID')} (${percentage}%)`;
                                }
                            }
                        },
                        title: {
                            display: false
                        },
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 15
                            }
                        }
                    }
                }
            });
        }

        // Jalankan animasi scroll saat halaman dimuat dan setiap kali di-scroll
        animateOnScroll(); // Initial check on load
        window.addEventListener('scroll', animateOnScroll);
    });
</script>
</body>
</html>