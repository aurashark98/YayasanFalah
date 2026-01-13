<?php
session_start();
require_once 'config.php'; // Pastikan ini mengarah ke file konfigurasi yang benar

// Proses kirim doa
if (isset($_POST['kirim_doa'])) {
    $nama = htmlspecialchars($_POST['nama_pengirim']);
    $isi = htmlspecialchars($_POST['isi_doa']);
    $user_id = null; // Default null jika tidak ada user yang login

    // Cek apakah user sudah login
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
    }

    // Persiapkan statement untuk INSERT
    // Query disesuaikan untuk menerima user_id
    $stmt = $conn->prepare("INSERT INTO doa (nama_pengirim, isi_doa, user_id) VALUES (?, ?, ?)");

    // Bind parameter: "ssi" untuk string, string, integer
    $stmt->bind_param("ssi", $nama, $isi, $user_id);

    if ($stmt->execute()) {
        // Redirect untuk mencegah pengiriman ulang form saat refresh
        header("Location: index.php");
        exit();
    } else {
        // Tangani kesalahan jika penyisipan gagal
        error_log("Error inserting doa: " . $stmt->error);
        header("Location: index.php?error=doa_gagal"); // Contoh redirect dengan error
        exit();
    }
}

// Ambil doa-doa terbaru untuk ditampilkan
$doa_list_html = '';
if (isset($conn)) {
    $result_doa = $conn->query("SELECT * FROM doa ORDER BY tanggal_doa DESC LIMIT 10");
    if ($result_doa) {
        while($row = $result_doa->fetch_assoc()):
            $doa_list_html .= "<div class='doa-item animate-on-scroll' data-animation='fade-up'>";
            $doa_list_html .= "<strong>" . htmlspecialchars($row['nama_pengirim'] ? $row['nama_pengirim'] : 'Anonim') . ":</strong>";
            $doa_list_html .= "<p>" . nl2br(htmlspecialchars($row['isi_doa'])) . "</p>";
            $doa_list_html .= "<small>" . date('d M Y H:i', strtotime($row['tanggal_doa'])) . "</small>";
            $doa_list_html .= "</div>";
        endwhile;
    } else {
        $doa_list_html = "<p>Gagal memuat doa. Silakan coba lagi nanti.</p>";
    }
} else {
    $doa_list_html = "<p>Koneksi database tidak tersedia. Mohon periksa konfigurasi.</p>";
}

// --- Ambil data program dari tabel 'programm' ---
$program_data = [];
if (isset($conn)) {
    // Pilih id, nama, dan gambar dari tabel programm
    $result_programs = $conn->query("SELECT id, nama, gambar FROM programm ORDER BY id ASC");
    if ($result_programs) {
        while($row = $result_programs->fetch_assoc()):
            $program_data[] = [
                'id' => $row['id'],
                'image_url' => htmlspecialchars($row['gambar']),
                'alt_text' => htmlspecialchars($row['nama']),
                'title' => htmlspecialchars($row['nama'])
            ];
        endwhile;
    } else {
        error_log("Error fetching programs: " . $conn->error);
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rumah AYP | Berbagi Kebaikan, Mengukir Senyum</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /*
        Palet Warna (Modern Clean with Dynamic Gradients):
        Latar Belakang Utama: #F0F2F5 (Soft Light Gray)
        Latar Belakang Sekunder: #FFFFFF (Pure White) - Untuk kontainer section
        Aksen Primer (Gradient): #4FC3F7 (Light Blue) ke #8BC34A (Light Green)
        Aksen Sekunder: #FFA726 (Vibrant Orange)
        Teks Primer: #37474F (Dark Blue Gray)
        Teks Sekunder: #78909C (Medium Blue Gray)
        Border & Garis: #CFD8DC (Light Blue Gray)
        Footer Background: #263238 (Dark Blue Gray)
        */

        :root {
            --color-bg-primary: #F0F2F5; /* Latar belakang utama: Soft Light Gray */
            --color-bg-secondary: #FFFFFF; /* Latar belakang section: Putih Murni */
            --color-gradient-start: #4FC3F7; /* Light Blue */
            --color-gradient-end: #8BC34A;   /* Light Green */
            --color-accent-secondary: #FFA726; /* Vibrant Orange */
            --color-text-primary: #37474F; /* Teks gelap utama */
            --color-text-secondary: #78909C; /* Teks abu-abu lebih lembut */
            --color-border-subtle: #CFD8DC; /* Border halus */
            --color-footer-bg: #263238; /* Footer gelap */

            --shadow-light: 0 4px 15px rgba(0, 0, 0, 0.05);
            --shadow-medium: 0 8px 25px rgba(0, 0, 0, 0.1);
            --shadow-strong: 0 12px 35px rgba(0, 0, 0, 0.15);
        }

        /* Global & Tipografi */
        body {
            font-family: 'Montserrat', sans-serif;
            line-height: 1.7;
            margin: 0;
            padding: 0;
            /* Latar belakang body diubah ke gradien yang lebih terlihat */
            background: linear-gradient(135deg, #8BCDC3, #66B2FF, #9966CC); /* Hijau sedang, Biru sedang, Ungu sedang */
            color: var(--color-text-primary); /* Teks default: Gelap */
            transition: background-color 0.3s ease;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            overflow-x: hidden; /* Mencegah overflow horizontal */
        }
        h1, h2, h3, p { /* Terapkan Montserrat ke semua teks */
            font-family: 'Montserrat', sans-serif;
        }
        h1, h2, h3 {
            color: var(--color-text-primary); /* Judul default: Gelap */
            font-weight: 800;
            transition: color 0.3s ease;
        }
        /* Teks paragraf umum */
        p {
            color: var(--color-text-secondary);
            font-weight: 400; /* Lebih ringan */
        }
        /* Judul dan teks di atas latar putih sudah disesuaikan secara default */

        /* Welcome Animation Overlay */
        #welcome-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #A8E6CE, #87CEEB, #9370DB);
            display: flex;
            flex-direction: column; /* Untuk menumpuk logo dan teks */
            justify-content: center;
            align-items: center;
            z-index: 9999;
            opacity: 1; /* Dimulai solid */
            transition: opacity 1s ease-out;
            overflow: hidden; /* Pastikan garis tidak keluar */
        }
        #welcome-logo-initial-container {
            position: relative;
            width: 250px; /* Ukuran container logo */
            height: 250px;
            display: flex;
            justify-content: center;
            align-items: center;
            opacity: 0;
            transform: translateY(-50px) scale(0.8); /* Posisi awal untuk slideInBounce */
            animation: slideInBounce 1.2s forwards cubic-bezier(0.175, 0.885, 0.32, 1.275) 0.5s; /* Animasi slideInBounce */
        }
        #welcome-logo-initial { /* Logo besar di tengah welcome screen */
            width: 100%;
            height: 100%;
            object-fit: contain;
            z-index: 2; /* Di atas garis */
        }
        /* Bagian CSS untuk garis telah dikomentari */
        /*
        .welcome-line {
            position: absolute;
            background-color: rgba(255, 255, 255, 0.4);
            animation-fill-mode: forwards;
        }
        .line-1 {
            width: 0; height: 3px; top: 30%; left: 10%;
            animation: drawLineHorizontal 0.8s ease-out 1.2s forwards;
        }
        .line-2 {
            width: 3px; height: 0; top: 10%; right: 20%;
            animation: drawLineVertical 0.8s ease-out 1.4s forwards;
        }
        .line-3 {
            width: 0; height: 3px; bottom: 30%; left: 25%;
            animation: drawLineHorizontal 0.8s ease-out 1.6s forwards;
        }
        .line-4 {
            width: 3px; height: 0; bottom: 10%; right: 10%;
            animation: drawLineVertical 0.8s ease-out 1.8s forwards;
        }
        @keyframes drawLineHorizontal {
            from { width: 0; }
            to { width: 100px; } // Panjang garis
        }
        @keyframes drawLineVertical {
            from { height: 0; }
            to { height: 100px; } // Panjang garis
        }
        */

        /* Animasi Baru untuk Logo Welcome */
        @keyframes slideInBounce {
            0% { opacity: 0; transform: translateY(-50px) scale(0.8); }
            60% { opacity: 1; transform: translateY(10px) scale(1.05); }
            100% { opacity: 1; transform: translateY(0) scale(1); }
        }

        #welcome-text {
            font-family: 'Montserrat', sans-serif; /* Menggunakan Montserrat untuk konsistensi */
            font-size: 3.5em; /* Ukuran font lebih besar untuk dampak */
            color: white; /* Teks welcome: Putih */
            opacity: 0;
            transform: translateY(40px); /* Mulai lebih jauh dari bawah */
            animation: fadeInText 1.0s forwards cubic-bezier(0.25, 0.46, 0.45, 0.94) 1.8s; /* Animasi lebih halus, delay sedikit lebih lama */
            font-weight: 800; /* Lebih tebal */
            text-shadow: 2px 2px 8px rgba(0,0,0,0.5); /* Bayangan lebih jelas */
            text-align: center;
            line-height: 1.2;
            max-width: 90%; /* Pastikan teks tidak terlalu lebar */
            letter-spacing: 1px; /* Jarak antar huruf */
        }
        @media (max-width: 768px) {
            #welcome-text {
                font-size: 2.5em;
                line-height: 1.3;
            }
        }
        @media (max-width: 480px) {
            #welcome-text {
                font-size: 1.8em;
                line-height: 1.4;
            }
        }
        @keyframes fadeInText {
            from { opacity: 0; transform: translateY(40px) scale(0.95); } /* Mulai sedikit lebih kecil dan bawah */
            to { opacity: 1; transform: translateY(0) scale(1); } /* Berakhir normal */
        }
        /* Animasi fade-out overlay setelah teks */
        #welcome-overlay.fade-out {
            opacity: 0;
            pointer-events: none; /* Penting agar bisa klik di bawahnya */
        }

        /* Logo Navbar - Awalnya tersembunyi */
        .navbar .logo img {
            width: 120px;
            height: auto;
            display: block;
            position: relative;
            margin-left: -10px;
            opacity: 0; /* Awalnya tersembunyi */
            transition: opacity 0.3s ease; /* Transisi saat muncul */
        }

        /* Navbar */
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
            transform: translateX(-50%); /* Tetap di tengah */
            height: 75px;
            top: 15px; /* Posisi awal mengambang */
            z-index: 1000;

            /* ANIMASI BARU UNTUK SCROLLING */
            transition: transform 0.4s ease-out, top 0.4s ease-out, width 0.4s ease-out,
                        border-radius 0.4s ease-out, background-color 0.4s ease-out,
                        box-shadow 0.4s ease-out, border-color 0.4s ease-out; /* Lebih spesifik */
            opacity: 1; /* Pastikan opacity dimulai dari 1 */
        }
        /* Navbar saat di-scroll */
        .navbar.scrolled {
            width: 100%;
            max-width: none;
            left: 0;
            transform: translateX(0); /* Kembali ke skala 1, tanpa zoom */
            top: 0; /* Menempel di atas */
            border-radius: 0; /* Sudut hilang */
            background-color: rgba(255, 255, 255, 0.99);
            box-shadow: var(--shadow-strong);
            border-color: transparent;
        }

        .navbar .auth-buttons {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .nav-links {
            display: flex;
            gap: 2rem;
            list-style: none;
            margin: 0 auto;
        }
        .nav-links a {
            color: var(--color-text-primary); /* Link navbar: Gelap */
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
            color: var(--color-gradient-start); /* Warna awal gradien */
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


        /* Tombol Auth Umum (di desktop) */
        .profile-link, .btn-login {
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
        .profile-link:hover, .btn-login:hover {
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
        /* Ripple effect */
        .btn-register::after {
            content: '';
            position: absolute;
            width: 0;
            height: 0;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.4);
            transition: width 0.4s ease-out, height 0.4s ease-out, opacity 0.4s ease-out;
            transform: translate(-50%, -50%);
            top: var(--y, 50%);
            left: var(--x, 50%);
            opacity: 0;
        }
        .btn-register:active::after {
            width: 200%;
            height: 200%;
            opacity: 1;
            transition: width 0s, height 0s, opacity 0.4s ease-out;
        }


        /* Carousel Program */
        .program-carousel-section {
            margin-top: 75px; /* Tinggi navbar */
            padding: 0;
            background: var(--color-bg-primary); /* Latar belakang utama */
            box-shadow: none;
            border: none;
            border-radius: 0;
            max-width: 100%;
            overflow: hidden;
            position: relative; /* Penting untuk positioning absolute anak-anaknya */
            height: 700px; /* Tinggi carousel */
        }
        .carousel-container {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            border-radius: 0;
            box-shadow: none;
            perspective: 1000px;
        }
        .carousel-inner {
            display: flex;
            width: 100%;
            height: 100%;
            transition: transform 0.8s cubic-bezier(0.2, 1, 0.3, 1);
            transform-style: preserve-3d;
        }
        .carousel-item {
            min-width: 100%;
            height: 100%;
            box-sizing: border-box;
            cursor: pointer;
            position: relative;
            transform-style: preserve-3d;
            overflow: hidden;
        }
        .carousel-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform 1s ease-out;
            transform: translateZ(0);
        }
        .carousel-item:hover img {
            transform: scale(1.05) translateZ(10px);
        }
        .carousel-item .overlay-text {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to top, rgba(0,0,0,0.6) 0%, rgba(0,0,0,0) 60%);
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            align-items: center;
            padding: 40px 20px;
            font-size: 2.5em;
            font-weight: 700;
            text-align: center;
            letter-spacing: 0.5px;
            opacity: 1;
            transition: background 0.3s ease;
            text-shadow: 2px 2px 8px rgba(0,0,0,0.5);
        }
        .carousel-item:hover .overlay-text {
             background: linear-gradient(to top, rgba(0,0,0,0.7) 0%, rgba(0,0,0,0.1) 60%);
        }

        .carousel-button {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background-color: rgba(0, 0, 0, 0.3);
            color: white;
            border: none;
            padding: 15px 10px;
            cursor: pointer;
            font-size: 2.2em;
            z-index: 10;
            border-radius: 8px;
            transition: background-color 0.3s ease, opacity 0.3s ease;
            opacity: 0.9;
        }
        .carousel-button:hover {
            background-color: rgba(0, 0, 0, 0.5);
            opacity: 1;
        }
        .carousel-button.prev {
            left: 25px;
        }
        .carousel-button.next {
            right: 25px;
        }
        .carousel-dots {
            display: flex;
            justify-content: center;
            gap: 12px;
            position: absolute; /* Posisikan secara absolut */
            bottom: 25px;      /* Jarak dari bawah section */
            left: 50%;         /* Pusatkan secara horizontal */
            transform: translateX(-50%); /* Penyesuaian untuk pemusatan */
            z-index: 10;       /* Pastikan di atas elemen lain jika perlu */
            width: 100%;       /* Agar centering bekerja dengan baik */
        }
        .dot {
            width: 14px;
            height: 14px;
            background-color: rgba(0, 0, 0, 0.2);
            border-radius: 50%;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }
        .dot.active {
            background: linear-gradient(45deg, var(--color-gradient-start), var(--color-gradient-end));
            transform: scale(1.2);
            border: 2px solid rgba(255, 255, 255, 0.8);
        }
        .dot:hover {
            background-color: rgba(0, 0, 0, 0.4);
        }

        /* Shape Divider (SVG) */
        .section-divider {
            width: 100%;
            height: 100px; /* Tinggi divider */
            position: relative;
            overflow: hidden;
            background-color: transparent;
        }
        .section-divider.bottom {
            margin-bottom: -1px;
        }
        .section-divider.bottom svg {
            display: block;
            position: absolute;
            bottom: 0;
            width: 100%;
            height: 100%;
            transform: rotate(180deg);
        }
        .section-divider.top {
            margin-top: -1px;
        }
        .section-divider.top svg {
            display: block;
            position: absolute;
            top: 0;
            width: 100%;
            height: 100%;
        }
        .section-divider svg path {
            fill: var(--color-bg-secondary); /* Default: putih untuk mengisi section container */
            transition: fill 0.3s ease;
        }
        /* Override fill colors for specific dividers */
        .divider-carousel-to-whatwedo .shape-fill {
             fill: var(--color-bg-secondary); /* Dari primary-bg (carousel) ke section-bg (putih) */
        }
        .divider-whatwedo-to-doa .shape-fill {
            fill: var(--color-bg-primary); /* Dari section-bg (putih) ke primary-bg (doa section) */
        }
        .divider-to-footer .shape-fill {
            fill: var(--color-footer-bg); /* Dari primary-bg (doa section) ke footer-bg */
        }


        /* Bagian Konten Utama (di atas latar putih) */
        .section-container {
            background: var(--color-bg-secondary); /* Latar belakang: Putih Murni */
            border-radius: 15px;
            padding: 60px 40px;
            box-shadow: var(--shadow-medium);
            margin: 0 auto;
            max-width: 1100px;
            border: 1px solid var(--color-border-subtle);
            text-align: center;
            transition: background-color 0.3s ease, border-color 0.3s ease;
            position: relative;
            z-index: 1;
        }

        /* Judul Bagian */
        .section-container h2 {
            font-size: 3.2em;
            margin-bottom: 45px;
            padding-bottom: 15px;
            position: relative;
            display: inline-block;
            color: var(--color-text-primary); /* Teks gelap */
            font-weight: 800;
        }
        .section-container h2::after {
            content: '';
            position: absolute;
            width: 100px;
            height: 4px;
            background: linear-gradient(to right, var(--color-gradient-start), var(--color-gradient-end));
            left: 50%;
            bottom: 0;
            transform: translateX(-50%);
            border-radius: 2px;
        }

        /* Bagian "Apa yang Kami Lakukan?" */
        .what-we-do-section {
            padding-top: 60px;
            padding-bottom: 60px;
        }
        .what-we-do-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }
        .grid-item {
            background-color: var(--color-bg-primary);
            border: 1px solid var(--color-border-subtle);
            padding: 35px;
            border-radius: 12px;
            text-align: center;
            box-shadow: var(--shadow-light);
            transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease, background-color 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .grid-item:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: var(--shadow-medium);
            border-color: var(--color-gradient-end);
            background-color: var(--color-bg-primary);
        }
        .grid-icon {
            width: 80px;
            height: 80px;
            margin-bottom: 25px;
            filter: none; /* Hapus filter default */
            transition: filter 0.3s ease, opacity 0.3s ease;
        }
        /* Filter spesifik untuk setiap ikon agar sesuai warna asli / tema cerah */
        /* Catatan: Filter ini adalah estimasi terbaik untuk gambar PNG hitam */
        .grid-item:nth-child(1) .grid-icon { /* Pendidikan: Biru */
            filter: brightness(0) saturate(100%) invert(55%) sepia(85%) saturate(300%) hue-rotate(185deg) brightness(100%) contrast(100%);
        }
        .grid-item:nth-child(2) .grid-icon { /* Kesehatan: Hijau */
            filter: brightness(0) saturate(100%) invert(65%) sepia(50%) saturate(300%) hue-rotate(90deg) brightness(100%) contrast(100%);
        }
        .grid-item:nth-child(3) .grid-icon { /* Pemberdayaan: Ungu */
            filter: brightness(0) saturate(100%) invert(45%) sepia(80%) saturate(200%) hue-rotate(270deg) brightness(100%) contrast(100%);
        }
        .grid-item:nth-child(4) .grid-icon { /* Sosial: Orange */
            filter: brightness(0) saturate(100%) invert(60%) sepia(50%) saturate(300%) hue-rotate(0deg) brightness(100%) contrast(100%);
        }
        .grid-item:hover .grid-icon {
            filter: none; /* Kembali ke warna asli ikon PNG */
            opacity: 1;
        }
        .grid-item h3 {
            color: var(--color-text-primary);
            font-size: 1.8em;
            margin-bottom: 15px;
            font-weight: 700;
        }
        .grid-item p {
            color: var(--color-text-secondary);
            opacity: 1;
            font-size: 1.05em;
            line-height: 1.7;
        }

        /* Bagian Doa */
        .doa-section {
            background: var(--color-bg-primary);
            padding-top: 60px;
            padding-bottom: 60px;
            color: var(--color-text-primary);
        }
        .doa-section h2 {
            color: var(--color-text-primary);
        }
        .doa-form {
            background-color: var(--color-bg-secondary);
            max-width: 650px;
            margin: 0 auto 50px;
            text-align: left;
            padding: 35px;
            border-radius: 12px;
            box-shadow: var(--shadow-light);
            border: 1px solid var(--color-border-subtle);
            transition: background-color 0.3s ease, border-color 0.3s ease;
        }
        .doa-form p {
            font-size: 1.05em;
            color: var(--color-text-secondary);
            font-weight: 400;
            margin-bottom: 20px;
        }
        .doa-form p a {
            color: var(--color-gradient-start);
            text-decoration: underline;
            font-weight: 600;
        }
        .doa-form input,
        .doa-form textarea {
            width: calc(100% - 24px);
            padding: 12px;
            margin-bottom: 20px;
            border: 1px solid var(--color-border-subtle);
            border-radius: 8px;
            font-family: 'Montserrat', sans-serif;
            font-size: 1em;
            background-color: var(--color-bg-primary);
            color: var(--color-text-primary);
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .doa-form input:focus,
        .doa-form textarea:focus {
            border-color: var(--color-gradient-start);
            box-shadow: 0 0 0 3px rgba(79, 195, 247, 0.2);
            outline: none;
            background-color: var(--color-bg-primary);
        }
        .doa-form textarea {
            resize: vertical;
            min-height: 130px;
        }
        .doa-form button {
            display: inline-block;
            background: var(--color-accent-secondary);
            color: #ffffff;
            padding: 14px 28px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1.1em;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-light);
            border: none;
            width: 100%;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: relative;
            overflow: hidden;
        }
        .doa-form button:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }
        .doa-form button::after {
            content: '';
            position: absolute;
            width: 0;
            height: 0;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.4);
            transition: width 0.4s ease-out, height 0.4s ease-out, opacity 0.4s ease-out;
            transform: translate(-50%, -50%);
            top: var(--y, 50%);
            left: var(--x, 50%);
            opacity: 0;
        }
        .doa-form button:active::after {
            width: 200%;
            height: 200%;
            opacity: 1;
            transition: width 0s, height 0s, opacity 0.4s ease-out;
        }
        .doa-form .btn-disabled {
            background: var(--color-border-subtle);
            color: var(--color-text-secondary);
            opacity: 0.7;
            cursor: not-allowed;
            box-shadow: none;
            text-transform: none;
        }
        .doa-form .btn-disabled:hover {
            transform: translateY(0);
            box-shadow: none;
        }
        .doa-form .btn-disabled::after {
            display: none;
        }

        .doa-list {
            max-width: 900px;
            margin: 40px auto 0;
            display: grid;
            gap: 25px;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        }
        .doa-item {
            opacity: 0; /* Initial state for animation */
            background-color: var(--color-bg-secondary);
            border: 1px solid var(--color-border-subtle);
            padding: 25px;
            border-radius: 10px;
            text-align: left;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
            transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.3s ease, border-color 0.3s ease;
        }
        .doa-item:hover {
            transform: translateY(-5px) scale(1.01);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.03);
            background-color: var(--color-bg-secondary);
            border-color: var(--color-gradient-start);
        }
        .doa-item strong {
            color: var(--color-text-primary);
            font-size: 1.1em;
            margin-bottom: 8px;
            display: block;
            font-weight: 600;
        }
        .doa-item p {
            color: var(--color-text-secondary);
            opacity: 0.8;
            font-size: 0.95em;
            flex-grow: 1;
            line-height: 1.7;
        }
        .doa-item small {
            color: var(--color-text-secondary);
            opacity: 0.6;
            font-size: 0.8em;
            align-self: flex-end;
        }

        /* Pemisah Horizontal dalam Section */
        .section-separator {
            border: 0;
            height: 1px;
            background-image: linear-gradient(to right, rgba(79, 195, 247, 0.2), var(--color-gradient-start), rgba(79, 195, 247, 0.2));
            margin: 40px auto;
            width: 60%;
            opacity: 0.6;
        }

        /* Footer */
        footer {
            background-color: var(--color-footer-bg);
            color: white;
            padding: 60px 20px 30px;
            margin-top: 0;
            box-shadow: inset 0 8px 20px rgba(0, 0, 0, 0.25);
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
        /* Filter untuk ikon footer agar sesuai warna asli */
        .footer-icons img {
            width: 28px;
            height: 28px;
            vertical-align: middle;
            filter: none; /* Hapus filter default */
        }
        /* Filter ini akan diterapkan jika ikon yang Anda gunakan adalah versi hitam/putih.
           Saya telah memperbarui URL ikon di HTML ke versi yang umumnya berwarna. */
        .footer-icons a[aria-label*="Instagram"] img {
            /* filter: invert(29%) sepia(85%) saturate(1633%) hue-rotate(280deg) brightness(101%) contrast(101%); */
        }
        .footer-icons a[aria-label*="WhatsApp"] img {
            /* filter: invert(35%) sepia(76%) saturate(1478%) hue-rotate(92deg) brightness(104%) contrast(101%); */
        }
        .footer-icons a[aria-label*="Maps"] img {
            /* filter: invert(32%) sepia(34%) saturate(2222%) hue-rotate(189deg) brightness(98%) contrast(104%); */
        }


        .footer-bottom {
            margin-top: 40px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 20px;
            text-align: center;
            font-size: 0.8em;
            color: rgba(255, 255, 255, 0.6);
        }

        /* Animasi Kustom */
        .animate-on-scroll {
            opacity: 0;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeInScale {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .animate-on-scroll.animated {
            animation-fill-mode: forwards;
        }


        /* Responsive Adjustments */
        @media (max-width: 1024px) {
            .navbar {
                padding: 0.7rem 3%;
                height: 65px;
                width: calc(100% - 30px); /* Adjust width */
                top: 10px; /* Adjust top */
            }
            .navbar.scrolled {
                width: 100%;
                top: 0;
            }
            .nav-links {
                gap: 1.5rem;
            }
            .program-carousel-section {
                height: 500px;
                margin-top: 65px;
            }
            .carousel-item .overlay-text {
                font-size: 1.4em;
                padding: 18px 15px;
            }
            .carousel-button {
                padding: 10px 6px;
                font-size: 1.8em;
            }
            .section-container {
                padding: 50px 30px;
            }
            .section-container h2 {
                font-size: 2.8em;
            }
            .what-we-do-grid {
                gap: 25px;
            }
            .grid-icon {
                width: 70px;
                height: 70px;
            }
            .grid-item h3 {
                font-size: 1.6em;
            }
            .doa-form {
                padding: 30px;
            }
        }

        @media (max-width: 768px) {
            .navbar {
                flex-wrap: wrap;
                justify-content: space-between;
                height: auto;
                padding: 15px 20px;
                background: rgba(255, 255, 255, 0.98); /* Lebih solid di mobile */
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
            .hamburger-menu {
                display: block;
            }

            .mobile-nav-overlay.open {
                display: flex;
            }

            /* Penyesuaian mobile-nav-overlay saat terbuka */
            .mobile-nav-overlay.open .mobile-nav {
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
            }

            .program-carousel-section {
                margin-top: 75px;
                height: 450px;
            }
            .carousel-item .overlay-text {
                font-size: 1.2em;
                padding: 15px 10px;
            }
            .carousel-button {
                padding: 8px 4px;
                font-size: 1.5em;
            }
            .section-container {
                padding: 40px 20px;
                margin: 30px auto;
            }
            .section-container h2 {
                font-size: 2.5em;
                margin-bottom: 35px;
            }
            .what-we-do-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            .grid-icon {
                width: 60px;
                height: 60px;
            }
            .grid-item h3 {
                font-size: 1.4em;
            }
            .doa-form {
                padding: 25px;
            }
            .doa-list {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            .footer-container {
                flex-direction: column;
                align-items: center;
                text-align: center;
                gap: 30px;
            }
            .footer-icons {
                justify-content: center;
            }
            .footer-column {
                min-width: unset;
                width: 90%;
            }
        }

        @media (max-width: 480px) {
            .navbar .logo img {
                width: 90px;
                margin-left: 0;
            }
            .program-carousel-section {
                margin-top: 75px;
                height: 350px;
            }
            .carousel-item .overlay-text {
                font-size: 1em;
                padding: 10px 8px;
            }
            .carousel-button {
                padding: 6px 3px;
                font-size: 1.2em;
            }
            .section-container {
                padding: 30px 15px;
                margin: 20px auto;
            }
            .section-container h2 {
                font-size: 2em;
                margin-bottom: 25px;
            }
            .grid-icon {
                width: 50px;
                height: 50px;
            }
            .grid-item h3 {
                font-size: 1.2em;
            }
            .grid-item p {
                font-size: 0.85em;
            }
            .doa-form {
                padding: 20px;
            }
            .doa-form input,
            .doa-form textarea {
                padding: 10px;
                font-size: 0.95em;
            }
            .doa-form button {
                padding: 12px 20px;
                font-size: 1em;
            }
            .doa-item {
                padding: 20px;
            }
            .doa-item strong {
                font-size: 1em;
            }
            .doa-item p {
                font-size: 0.85em;
            }
            .footer-icons img {
                width: 24px;
                height: 24px;
                padding: 6px;
            }
        }
    </style>
</head>
<body>
<?php if (!isset($_SESSION['user_id'])): // Tampilkan overlay hanya jika user belum login ?>
<div id="welcome-overlay">
    <div id="welcome-logo-initial-container">
        <img id="welcome-logo-initial" src="logo_rumah_ayp.png" alt="Rumah AYP Logo">
    </div>
    <div id="welcome-text">Selamat Datang di Rumah AYP!<br>Mari Berbagi Kebaikan.</div>
</div>
<?php endif; ?>

<nav class="navbar" id="mainNavbar">
    <div class="logo">
        <img src="logo_rumah_ayp.png" alt="logorumah ayp" id="navbarLogo"/>
    </div>
    <ul class="nav-links" id="mainNavLinks">
        <li><a href="index.php" class="active">Home</a></li>
        <li><a href="about.php">Tentang Kami</a></li>
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
        <li><a href="index.php" class="active">Home</a></li>
        <li><a href="about.php">Tentang Kami</a></li>
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


<section class="program-carousel-section animate-on-scroll" data-animation="fade-in">
    <div class="carousel-container">
        <div class="carousel-inner" id="programCarouselInner">
            <?php if (!empty($program_data)): ?>
                <?php
                $is_logged_in = isset($_SESSION['user_id']);
                foreach ($program_data as $program):
                    $target_url = $is_logged_in ? "program.php?donate=true&id=" . $program['id'] : "login.php";
                ?>
                    <div class="carousel-item" data-program-id="<?php echo $program['id']; ?>">
                        <a href="<?php echo htmlspecialchars($target_url); ?>">
                            <img src="<?php echo htmlspecialchars($program['image_url']); ?>" alt="<?php echo htmlspecialchars($program['alt_text']); ?>">
                            <div class="overlay-text"><?php echo htmlspecialchars($program['title']); ?></div>
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>Tidak ada program donasi yang tersedia saat ini.</p>
            <?php endif; ?>
        </div>
        <?php if (count($program_data) > 1): ?>
            <button class="carousel-button prev" id="carouselPrevBtn">❮</button>
            <button class="carousel-button next" id="carouselNextBtn">❯</button>
        <?php endif; ?>
    </div>
    <?php if (count($program_data) > 1): ?>
        <div class="carousel-dots" id="carouselDots">
            <?php for ($i = 0; $i < count($program_data); $i++): ?>
                <span class="dot" data-index="<?php echo $i; ?>"></span>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</section>

<div class="section-divider bottom divider-carousel-to-whatwedo">
    <svg data-name="Layer 1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120" preserveAspectRatio="none">
        <path d="M0,0V46.29c47.79,22.2,103.59,32.17,158,32.17,208.65,0,367.59-41.43,500.4-41.43,148.67,0,227.42,23.53,357.49,23.53,100.4,0,183.94-18.16,275-31.54V0Z" class="shape-fill"></path>
    </svg>
</div>


<section class="section-container what-we-do-section animate-on-scroll" data-animation="fade-in">
    <h2>Apa yang Kami Lakukan?</h2>
    <div class="what-we-do-grid">
        <div class="grid-item animate-on-scroll" data-animation="fade-up">
            <img src="https://cdn-icons-png.flaticon.com/512/2906/2906274.png" alt="Pendidikan" class="grid-icon">
            <h3>Pendidikan Berkualitas</h3>
            <p>Memberikan akses pendidikan yang layak dan berkualitas bagi anak-anak yatim dan dhuafa, memastikan mereka memiliki masa depan yang cerah.</p>
        </div>
        <div class="grid-item animate-on-scroll" data-animation="fade-up">
            <img src="https://cdn-icons-png.flaticon.com/512/3050/3050228.png" alt="Kesehatan" class="grid-icon">
            <h3>Bantuan Kesehatan</h3>
            <p>Menyediakan fasilitas dan dukungan kesehatan yang memadai, termasuk pemeriksaan rutin dan akses obat-obatan bagi yang membutuhkan.</p>
        </div>
        <div class="grid-item animate-on-scroll" data-animation="fade-up">
            <img src="https://cdn-icons-png.flaticon.com/512/2906/2906377.png" alt="Kesejahteraan" class="grid-icon">
            <h3>Pemberdayaan Masyarakat</h3>
            <p>Mengadakan program-program pemberdayaan untuk meningkatkan keterampilan dan kemandirian ekonomi keluarga dhuafa.</p>
        </div>
        <div class="grid-item animate-on-scroll" data-animation="fade-up">
            <img src="https://cdn-icons-png.flaticon.com/512/3391/3391807.png" alt="Sosial" class="grid-icon">
            <h3>Dukungan Sosial & Kemanusiaan</h3>
            <p>Menyalurkan bantuan logistik, makanan, dan kebutuhan dasar lainnya kepada korban bencana dan keluarga prasejahtera.</p>
        </div>
    </div>
</section>

<div class="section-divider top divider-whatwedo-to-doa">
    <svg data-name="Layer 1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120" preserveAspectRatio="none">
        <path d="M0,0V46.29c47.79,22.2,103.59,32.17,158,32.17,208.65,0,367.59-41.43,500.4-41.43,148.67,0,227.42,23.53,357.49,23.53,100.4,0,183.94-18.16,275-31.54V0Z" class="shape-fill"></path>
    </svg>
</div>

<section class="section-container doa-section animate-on-scroll" data-animation="fade-in">
    <h2>Sampaikan Doa Terbaik Anda</h2>
    <form action="index.php" method="POST" class="doa-form animate-on-scroll" data-animation="fade-in-scale">
        <?php if(isset($_SESSION['user_id'])): ?>
            <input type="text" name="nama_pengirim" placeholder="Nama Anda (Opsional)" value="<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>" >
        <?php else: ?>
            <p style="text-align: center;">
                Anda harus <a href="login.php">Login</a> untuk mengirim doa.
            </p>
            <input type="text" name="nama_pengirim" placeholder="Nama Anda (Opsional)" disabled>
        <?php endif; ?>

        <textarea name="isi_doa" placeholder="Tuliskan harapan dan doa tulus Anda di sini..." <?php echo isset($_SESSION['user_id']) ? '' : 'disabled'; ?> required></textarea>

        <?php if(isset($_SESSION['user_id'])): ?>
            <button type="submit" name="kirim_doa" class="btn-ripple">Kirim Doa</button>
        <?php else: ?>
            <button type="button" class="btn-disabled">Kirim Doa</button>
            <p style="text-align: center; font-size: 0.95em; margin-top: 15px;">
                Atau <a href="register.php">Daftar</a> jika belum punya akun.
            </p>
        <?php endif; ?>
    </form>

    <hr class="section-separator animate-on-scroll" data-animation="fade-in">

    <h2>Doa dari Sahabat Dermawan</h2>
    <div class="doa-list">
        <?php echo $doa_list_html; ?>
    </div>
</section>

<div class="section-divider bottom divider-to-footer">
    <svg data-name="Layer 1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120" preserveAspectRatio="none">
        <path d="M0,0V46.29c47.79,22.2,103.59,32.17,158,32.17,208.65,0,367.59-41.43,500.4-41.43,148.67,0,227.42,23.53,357.49,23.53,100.4,0,183.94-18.16,275-31.54V0Z" class="shape-fill"></path>
    </svg>
</div>

<footer>
    <div class="footer-container">
        <div class="footer-column animate-on-scroll" data-animation="fade-up">
            <h3>Rumah AYP</h3>
            <p>Yayasan sosial yang berdedikasi untuk membantu mereka yang membutuhkan dan menciptakan masa depan yang lebih baik.</p>
        </div>

        <div class="footer-column animate-on-scroll" data-animation="fade-up">
            <h3>Navigasi Cepat</h3>
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="about.php">Tentang Kami</a></li>
                <li><a href="program.php">Program Donasi</a></li>
                <li><a href="berita.php">Berita & Artikel</a></li>
                <li><a href="profilyatim.php">Profil Anak Asuh</a></li>
            </ul>
        </div>

        <div class="footer-column animate-on-scroll" data-animation="fade-up">
            <h3>Jam Layanan</h3>
            <p>Senin - Jumat: 08.00 - 17.00 WIB</p>
            <p>Sabtu - Minggu: Tutup (Kecuali acara khusus)</p>
        </div>

        <div class="footer-column animate-on-scroll" data-animation="fade-up">
            <h3>Terhubung Dengan Kami</h3>
            <div class="footer-icons">
                <a href="https://www.instagram.com/fallstirkta?igsh=MWtnOXo1d2dzNG94eQ==" target="_blank" aria-label="Instagram Rumah AYP">
                    <img src="https://cdn-icons-png.flaticon.com/512/174/174855.png" alt="Instagram"> </a>
                <a href="https://wa.me/6285808436591" target="_blank" aria-label="WhatsApp Rumah AYP">
                    <img src="https://cdn-icons-png.flaticon.com/512/733/733585.png" alt="WhatsApp"> </a>
                <a href="https://maps.google.com/?q=Jati, Sidoarjo" target="_blank" aria-label="Lokasi Rumah AYP di Google Maps">
                    <img src="https://cdn-icons-png.flaticon.com/512/684/684908.png" alt="Maps"> </a>
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
        // Welcome Overlay Logic
        const welcomeOverlay = document.getElementById('welcome-overlay');
        const welcomeText = document.getElementById('welcome-text');
        const welcomeLogoInitialContainer = document.getElementById('welcome-logo-initial-container'); // Container logo besar
        const navbarLogo = document.getElementById('navbarLogo'); // Logo di navbar

        // Hanya jalankan logika welcome overlay jika elemen overlay ada (artinya user belum login)
        if (welcomeOverlay) {
            // Sembunyikan logo navbar di awal
            navbarLogo.style.opacity = '0';
            navbarLogo.style.transition = 'none'; // Matikan transisi defaultnya

            // Tampilkan overlay selamat datang
            welcomeOverlay.style.display = 'flex';
            // Picu animasi logo selamat datang dan teks
            setTimeout(() => {
                welcomeLogoInitialContainer.style.opacity = '1';
                welcomeText.style.opacity = '1';
                welcomeText.style.transform = 'translateY(0)';
            }, 100);

            // Mulai animasi logo ke navbar setelah animasi teks selamat datang
            setTimeout(() => {
                // Dapatkan posisi akhir logo navbar
                const navbarLogoRect = navbarLogo.getBoundingClientRect();

                // Dapatkan posisi tengah logo selamat datang saat ini (container)
                const welcomeLogoInitialContainerRect = welcomeLogoInitialContainer.getBoundingClientRect();
                const welcomeLogoInitialContainerCenterX = welcomeLogoInitialContainerRect.left + welcomeLogoInitialContainerRect.width / 2;
                const welcomeLogoInitialContainerCenterY = welcomeLogoInitialContainerRect.top + welcomeLogoInitialContainerRect.height / 2;

                // Hitung posisi tengah target logo di navbar
                const targetLogoCenterX = navbarLogoRect.left + navbarLogoRect.width / 2;
                const targetLogoCenterY = navbarLogoRect.top + navbarLogoRect.height / 2;

                // Set properti awal untuk animasi logo selamat datang
                welcomeLogoInitialContainer.style.position = 'fixed'; // Ubah positioning untuk animasi
                welcomeLogoInitialContainer.style.top = welcomeLogoInitialContainerCenterY + 'px';
                welcomeLogoInitialContainer.style.left = welcomeLogoInitialContainerCenterX + 'px';
                welcomeLogoInitialContainer.style.transform = 'translate(-50%, -50%) scale(1)'; // Set initial transform based on its current center
                welcomeLogoInitialContainer.style.width = welcomeLogoInitialContainerRect.width + 'px'; // Set current width
                welcomeLogoInitialContainer.style.height = welcomeLogoInitialContainerRect.height + 'px'; // Set current height
                welcomeLogoInitialContainer.style.transition = 'none'; // Pastikan tidak ada transisi yang mengganggu

                // Definisikan keyframes untuk animasi logoToNavbar secara dinamis
                const logoToNavbarKeyframes = `
                    @keyframes logoToNavbar {
                        0% {
                            top: ${welcomeLogoInitialContainerCenterY}px;
                            left: ${welcomeLogoInitialContainerCenterX}px;
                            width: ${welcomeLogoInitialContainerRect.width}px;
                            transform: translate(-50%, -50%) scale(1);
                            opacity: 1;
                        }
                        100% {
                            top: ${targetLogoCenterY}px;
                            left: ${targetLogoCenterX}px;
                            width: ${navbarLogoRect.width}px; /* Ukuran akhir sesuai navbar logo */
                            transform: translate(-50%, -50%) scale(1); /* Pastikan scale 1 di akhir */
                            opacity: 1;
                        }
                    }
                `;

                // Tambahkan keyframes ke stylesheet
                const styleSheet = document.styleSheets[0];
                // Hapus rule lama jika ada, untuk menghindari duplikasi saat debugging
                for(let i = 0; i < styleSheet.cssRules.length; i++) {
                    if(styleSheet.cssRules[i].name === 'logoToNavbar') {
                        styleSheet.deleteRule(i);
                        break;
                    }
                }
                styleSheet.insertRule(logoToNavbarKeyframes, styleSheet.cssRules.length);

                // Terapkan animasi pada container logo selamat datang
                welcomeLogoInitialContainer.style.animation = `logoToNavbar 1.0s forwards cubic-bezier(0.68, -0.55, 0.265, 1.55)`;

                // Sembunyikan teks welcome
                welcomeText.style.opacity = '0';
                welcomeText.style.transition = 'opacity 0.3s ease-out';


                // Tampilkan logo navbar dan sembunyikan logo animasi setelah animasi selesai
                setTimeout(() => {
                    navbarLogo.style.opacity = '1';
                    navbarLogo.style.transition = ''; // Aktifkan kembali transisi default logo navbar
                    welcomeLogoInitialContainer.style.display = 'none'; // Sembunyikan container logo yang dianimasikan
                }, 3000); // Waktu ini harus setelah animasi logoToNavbar selesai (1s) + waktu sebelumnya

                // Sembunyikan overlay selamat datang sepenuhnya setelah animasi logo selesai
                setTimeout(() => {
                    welcomeOverlay.classList.add('fade-out');
                    document.body.style.overflowY = 'auto'; // Aktifkan kembali scroll body
                }, 3500); // Total durasi animasi selamat datang

            }, 2000); // Setelah welcomeText animation selesai
        } else {
            // Jika welcomeOverlay tidak ada (user sudah login), pastikan logo navbar terlihat dari awal
            navbarLogo.style.opacity = '1';
            document.body.style.overflowY = 'auto'; // Pastikan scroll aktif
        }


        // Efek Navbar Saat Digulir
        const mainNavbar = document.getElementById('mainNavbar');
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


        // Hamburger Menu Logic
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


        // Fungsi untuk memeriksa apakah elemen ada di dalam viewport
        function isInViewport(element, offset = 0) {
            if (!element) return false;
            const rect = element.getBoundingClientRect();
            const viewportHeight = (window.innerHeight || document.documentElement.clientHeight);
            return (
                rect.top <= (viewportHeight - offset) &&
                rect.bottom >= offset
            );
        }

        // Fungsi untuk menambahkan kelas animasi saat elemen memasuki viewport
        function animateOnScroll() {
            const elements = document.querySelectorAll('.animate-on-scroll:not(.animated)');
            elements.forEach(element => {
                if (isInViewport(element, 100)) {
                    const animationType = element.dataset.animation || 'fade-in';
                    element.classList.add('animated');
                    if (animationType === 'fade-up') {
                        element.style.animation = 'fadeInUp 0.7s ease-out forwards';
                    } else if (animationType === 'fade-in-scale') {
                        element.style.animation = 'fadeInScale 0.7s ease-out forwards';
                    } else if (animationType === 'fade-in') {
                        element.style.animation = 'fadeIn 0.7s ease-out forwards';
                    }
                }
            });

            const staggeredItems = document.querySelectorAll('.what-we-do-grid .grid-item.animate-on-scroll:not(.animated), .doa-list .doa-item.animate-on-scroll:not(.animated)');
            staggeredItems.forEach((item, index) => {
                if (isInViewport(item, 80)) {
                    const animationType = item.dataset.animation || 'fade-up';
                    setTimeout(() => {
                        item.classList.add('animated');
                        if (animationType === 'fade-up') {
                            item.style.animation = 'fadeInUp 0.7s ease-out forwards';
                        }
                    }, index * 100);
                }
            });
        }

        // Jalankan saat halaman dimuat dan saat menggulir
        animateOnScroll();
        window.addEventListener('scroll', animateOnScroll);

        // Efek Ripple pada Tombol
        document.querySelectorAll('.btn-ripple').forEach(button => {
            button.addEventListener('click', function(e) {
                const x = e.clientX - e.target.getBoundingClientRect().left;
                const y = e.clientY - e.target.getBoundingClientRect().top;

                this.style.setProperty('--x', x + 'px');
                this.style.setProperty('--y', y + 'px');
            });
        });

        // --- JavaScript Carousel Program ---
        const carouselInner = document.getElementById('programCarouselInner');
        const prevBtn = document.getElementById('carouselPrevBtn');
        const nextBtn = document.getElementById('carouselNextBtn');
        const carouselDotsContainer = document.getElementById('carouselDots');
        const carouselItems = document.querySelectorAll('.carousel-item');
        const totalItems = carouselItems.length;
        let currentIndex = 0;
        let autoSlideInterval;

        if (totalItems > 0) {
            function updateCarousel() {
                const offset = -currentIndex * 100;
                carouselInner.style.transform = `translateX(${offset}%)`;
                updateDots();
            }

            function updateDots() {
                const dots = document.querySelectorAll('.dot');
                dots.forEach((dot, index) => {
                    dot.classList.toggle('active', index === currentIndex);
                });
            }

            if (nextBtn) {
                nextBtn.addEventListener('click', () => {
                    currentIndex = (currentIndex + 1) % totalItems;
                    updateCarousel();
                    resetAutoSlide();
                });
            }

            if (prevBtn) {
                prevBtn.addEventListener('click', () => {
                    currentIndex = (currentIndex - 1 + totalItems) % totalItems;
                    updateCarousel();
                    resetAutoSlide();
                });
            }

            if (carouselDotsContainer) {
                carouselDotsContainer.addEventListener('click', (event) => {
                    if (event.target.classList.contains('dot')) {
                        const index = parseInt(event.target.dataset.index);
                        if (!isNaN(index)) {
                            currentIndex = index;
                            updateCarousel();
                            resetAutoSlide();
                        }
                    }
                });
            }

            // Touch/Swipe Logic
            let touchStartX = 0;
            let touchEndX = 0;

            carouselInner.addEventListener('touchstart', (e) => {
                touchStartX = e.touches[0].clientX;
                clearInterval(autoSlideInterval);
            });

            carouselInner.addEventListener('touchmove', (e) => {
                touchEndX = e.touches[0].clientX;
            });

            carouselInner.addEventListener('touchend', () => {
                if (touchStartX - touchEndX > 50) {
                    currentIndex = (currentIndex + 1) % totalItems;
                } else if (touchEndX - touchStartX > 50) {
                    currentIndex = (currentIndex - 1 + totalItems) % totalItems;
                }
                updateCarousel();
                resetAutoSlide();
            });

            function startAutoSlide() {
                autoSlideInterval = setInterval(() => {
                    currentIndex = (currentIndex + 1) % totalItems;
                    updateCarousel();
                }, 5000);
            }

            function resetAutoSlide() {
                clearInterval(autoSlideInterval);
                startAutoSlide();
            }

            updateCarousel();
            startAutoSlide();
        } else {
            if (prevBtn) prevBtn.style.display = 'none';
            if (nextBtn) nextBtn.style.display = 'none';
            if (carouselDotsContainer) carouselDotsContainer.style.display = 'none';
        }
    });
</script>
</body>
</html>