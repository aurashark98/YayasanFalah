<?php
session_start();
require_once 'config.php'; // Pastikan ini mengarah ke file konfigurasi database Anda

$pesan_sukses = '';
$pesan_error = '';

// Proses pengiriman formulir
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nama = htmlspecialchars(trim($_POST['nama']));
    $email = htmlspecialchars(trim($_POST['email']));
    $subjek = htmlspecialchars(trim($_POST['subjek']));
    $pesan = htmlspecialchars(trim($_POST['pesan']));

    // Validasi sederhana
    if (empty($nama) || empty($email) || empty($pesan)) {
        $pesan_error = "Nama, Email, dan Pesan tidak boleh kosong.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $pesan_error = "Format email tidak valid.";
    } else {
        // Persiapkan dan ikat parameter
        $stmt = $conn->prepare("INSERT INTO kontak (nama, email, subjek, pesan) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $nama, $email, $subjek, $pesan);

        if ($stmt->execute()) {
            $pesan_sukses = "Pesan Anda berhasil terkirim. Terima kasih!";
            // Bersihkan kolom formulir setelah pengiriman berhasil
            $_POST = array(); // Bersihkan array POST untuk mereset kolom formulir
        } else {
            $pesan_error = "Terjadi kesalahan saat mengirim pesan. Silakan coba lagi. Error: " . $stmt->error;
            error_log("Error inserting contact message: " . $stmt->error);
        }
        $stmt->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kontak Kami - Rumah AYP</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        /* Menggunakan ulang variabel dari index.php/about.php untuk konsistensi */
        :root {
            --color-bg-primary: #F0F2F5; /* Abu-abu terang lembut */
            --color-bg-secondary: #FFFFFF; /* Putih murni */
            --color-gradient-start: #4FC3F7; /* Biru terang */
            --color-gradient-end: #8BC34A;   /* Hijau terang */
            --color-accent-secondary: #FFA726; /* Oranye cerah */
            --color-text-primary: #37474F; /* Abu-abu biru gelap */
            --color-text-secondary: #78909C; /* Abu-abu biru sedang */
            --color-border-subtle: #CFD8DC; /* Abu-abu biru terang */

            --shadow-light: 0 4px 15px rgba(0, 0, 0, 0.05);
            --shadow-medium: 0 8px 25px rgba(0, 0, 0, 0.1);
            --shadow-strong: 0 12px 35px rgba(0, 0, 0, 0.15);
        }

        body {
            font-family: 'Montserrat', sans-serif;
            line-height: 1.7;
            margin: 0;
            padding: 0;
            background: linear-gradient(135deg, #E0F2F1, #E3F2FD, #EDE7F6); /* Konsisten dengan about.php */
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

        /* Gaya Navbar (disalin dari index.php/about.php, disesuaikan untuk status aktif di kontak.php) */
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.7rem 4%;
            background: var(--color-bg-secondary); /* Latar belakang putih */
            border-bottom-left-radius: 15px;
            border-bottom-right-radius: 15px;
            backdrop-filter: blur(8px);
            box-shadow: var(--shadow-medium);
            position: fixed;
            width: calc(100% - 40px);
            max-width: 1200px;
            left: 50%;
            transform: translateX(-50%);
            height: 75px;
            top: 15px;
            z-index: 1000;
            transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            border: 1px solid var(--color-border-subtle);
        }
        .navbar.scrolled {
            width: 100%;
            max-width: none;
            left: 0;
            transform: translateX(0);
            top: 0;
            border-radius: 0;
            background-color: rgba(255, 255, 255, 0.99);
            box-shadow: var(--shadow-strong);
            border-color: transparent;
        }
        .navbar .logo img {
            width: 120px;
            height: auto;
            display: block;
            position: relative;
            margin-left: -10px;
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

        /* Menu Hamburger untuk Seluler (disalin dari index.php/about.php) */
        .hamburger-menu {
            display: none;
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

        /* Tombol Auth Umum (desktop) (disalin dari index.php/about.php) */
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

        /* Header Khusus Halaman Kontak (konsisten dengan about.php) */
        header {
            background: linear-gradient(135deg, var(--color-gradient-start), var(--color-gradient-end));
            color: #ffffff;
            padding: 80px 0 60px; /* Padding atas disesuaikan dengan gaya about.php, bawah disesuaikan */
            text-align: center;
            margin-top: 75px; /* Sesuaikan dengan tinggi navbar */
            box-shadow: var(--shadow-strong); /* Bayangan lebih kuat */
            border-bottom-left-radius: 25px; /* Sudut membulat */
            border-bottom-right-radius: 25px;
            position: relative;
            z-index: 0;
            overflow: hidden;
        }
        header h1 {
            font-size: 3em; /* Ukuran font disesuaikan untuk konsistensi */
            margin-bottom: 20px;
            text-shadow: 2px 2px 6px rgba(0,0,0,0.3); /* Bayangan disesuaikan */
            font-weight: 800; /* Bobot disesuaikan */
            color: #FFFFFF;
        }
        header p {
            font-size: 1.3em;
            max-width: 900px;
            margin: 0 auto;
            padding: 0 30px;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.9);
            line-height: 1.6;
        }

        /* Pembagi Bagian (SVG) (disalin dari index.php/about.php) */
        .section-divider {
            width: 100%;
            height: 100px;
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
        /* Hanya satu pembagi yang dibutuhkan untuk halaman ini: header ke bagian kontak */
        .divider-header-to-contact .shape-fill {
             fill: var(--color-bg-secondary); /* Isi dari header ke bg-bagian (putih) */
        }


        /* Kontainer Bagian Konten Utama (disalin dari index.php/about.php) */
        main {
            padding: 40px 20px; /* Padding konsisten */
            max-width: 1200px; /* Lebar maksimum konsisten */
            margin: 40px auto; /* Margin konsisten */
        }
        .section-container {
            background: var(--color-bg-secondary); /* Latar belakang putih */
            border-radius: 15px; /* Radius konsisten */
            padding: 50px 40px; /* Padding konsisten */
            box-shadow: var(--shadow-medium); /* Bayangan konsisten */
            margin-bottom: 40px; /* Margin bawah konsisten */
            border: 1px solid var(--color-border-subtle); /* Border tipis konsisten */
            text-align: center;
            position: relative;
            overflow: hidden; /* Penting untuk animasi */
            opacity: 0; /* Status awal untuk animasi */
            transform: translateY(20px); /* Status awal untuk animasi */
            transition: opacity 0.6s ease-out, transform 0.6s ease-out; /* Transisi halus */
        }
        .section-container.animated { /* Kelas yang ditambahkan oleh JS untuk animasi */
             opacity: 1;
             transform: translateY(0);
        }

        /* Judul Bagian (disalin dari index.php/about.php) */
        .section-container h2 {
            font-size: 2.8em; /* Ukuran font disesuaikan untuk konsistensi dengan about.php */
            margin-bottom: 35px; /* Margin disesuaikan */
            padding-bottom: 15px;
            position: relative;
            display: inline-block;
            color: var(--color-text-primary);
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

        /* Gaya Khusus Formulir Kontak */
        .contact-info {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 30px;
            margin-bottom: 50px;
        }
        .info-box {
            background-color: var(--color-bg-primary); /* Latar belakang abu-abu terang */
            border: 1px solid var(--color-border-subtle);
            padding: 30px;
            border-radius: 12px;
            box-shadow: var(--shadow-light);
            flex: 1 1 280px;
            text-align: left;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
            opacity: 0; /* Status awal untuk animasi */
            transform: translateY(20px); /* Status awal untuk animasi */
        }
        .info-box.animated { /* Animasi untuk kotak info */
            animation: fadeInUp 0.7s ease-out forwards;
        }
        .info-box:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-medium);
            border-color: var(--color-gradient-start);
        }
        .info-box .icon {
            font-size: 2em;
            color: var(--color-gradient-start);
            flex-shrink: 0;
        }
        .info-box .text h3 {
            font-size: 1.4em;
            margin-bottom: 5px;
            color: var(--color-text-primary);
        }
        .info-box .text p, .info-box .text a {
            font-size: 0.95em;
            color: var(--color-text-secondary);
            text-decoration: none;
            transition: color 0.3s ease;
        }
        .info-box .text a:hover {
            color: var(--color-gradient-end);
        }

        .contact-form-container {
            background-color: var(--color-bg-primary); /* Latar belakang abu-abu terang */
            max-width: 700px;
            margin: 0 auto;
            text-align: left;
            padding: 35px;
            border-radius: 12px;
            box-shadow: var(--shadow-light);
            border: 1px solid var(--color-border-subtle);
            transition: background-color 0.3s ease, border-color 0.3s ease;
            opacity: 0; /* Status awal untuk animasi */
            transform: translateY(20px); /* Status awal untuk animasi */
        }
        .contact-form-container.animated { /* Animasi untuk formulir kontak */
            animation: fadeInScale 0.7s ease-out forwards;
        }
        .contact-form-container h3 {
            font-size: 2em;
            margin-bottom: 25px;
            color: var(--color-text-primary);
            text-align: center;
            border-bottom: 2px solid var(--color-gradient-end);
            padding-bottom: 10px;
            display: inline-block;
            margin-left: auto;
            margin-right: auto;
        }
        .contact-form-container label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--color-text-primary);
            font-size: 0.95em;
        }
        .contact-form-container input[type="text"],
        .contact-form-container input[type="email"],
        .contact-form-container textarea {
            width: calc(100% - 24px); /* Menyesuaikan untuk padding */
            padding: 12px;
            margin-bottom: 20px;
            border: 1px solid var(--color-border-subtle);
            border-radius: 8px;
            font-family: 'Montserrat', sans-serif;
            font-size: 1em;
            background-color: var(--color-bg-secondary); /* Latar belakang putih untuk kolom input */
            color: var(--color-text-primary);
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .contact-form-container input[type="text"]:focus,
        .contact-form-container input[type="email"]:focus,
        .contact-form-container textarea:focus {
            border-color: var(--color-gradient-start);
            box-shadow: 0 0 0 3px rgba(79, 195, 247, 0.2);
            outline: none;
            background-color: var(--color-bg-secondary);
        }
        .contact-form-container textarea {
            resize: vertical;
            min-height: 150px;
        }
        .contact-form-container button[type="submit"] {
            display: inline-block;
            background: linear-gradient(45deg, var(--color-gradient-start), var(--color-gradient-end));
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
        .contact-form-container button[type="submit"]:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }
        .contact-form-container button[type="submit"]::after {
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
        .contact-form-container button[type="submit"]:active::after {
            width: 200%;
            height: 200%;
            opacity: 1;
            transition: width 0s, height 0s, opacity 0.4s ease-out;
        }

        .message-alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 600;
            text-align: center;
            opacity: 0;
            transform: translateY(-10px);
            animation: fadeInDown 0.5s forwards;
        }

        .message-alert.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message-alert.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Animasi (disalin dari index.php/about.php) */
        .animate-on-scroll {
            opacity: 0; /* Status awal untuk semua elemen animasi */
            transform: translateY(20px); /* Status awal untuk sebagian besar elemen */
            transition: opacity 0.6s ease-out, transform 0.6s ease-out; /* Transisi default */
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
            animation-fill-mode: forwards; /* Pertahankan status akhir animasi */
            /* Properti animasi diatur langsung di JS untuk elemen tertentu
               atau melalui atribut data-animation */
        }
        /* Animasi spesifik untuk elemen di kontak.php yang dikontrol oleh JS */
        /* Gaya-gaya ini menimpa .animate-on-scroll.animated jika data-animation diatur */
        header.animated { animation: fadeIn 0.7s ease-out forwards; } /* Untuk header */
        .info-box.animated { animation: fadeInUp 0.7s ease-out forwards; } /* Untuk kotak info */
        .contact-form-container.animated { animation: fadeInScale 0.7s ease-out forwards; } /* Untuk formulir */
        .message-alert.animated { animation: fadeInDown 0.5s forwards; } /* Untuk peringatan pesan */


        /* Penyesuaian Responsif (disalin dan disesuaikan dari index.php/about.php) */
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
                padding: 60px 0 40px; /* Padding disesuaikan */
            }
            header h1 {
                font-size: 2.5em; /* Ukuran font disesuaikan */
            }
            header p {
                font-size: 1.2em;
            }
            main { /* Disesuaikan untuk konsistensi */
                padding: 30px 20px;
                margin: 30px auto;
            }
            .section-container {
                padding: 40px 30px;
            }
            .section-container h2 {
                font-size: 2.4em; /* Ukuran font disesuaikan */
                margin-bottom: 30px;
            }
            .contact-info {
                gap: 20px;
            }
            .info-box {
                flex: 1 1 250px;
                padding: 25px;
            }
            .contact-form-container {
                padding: 30px;
            }
        }

        @media (max-width: 768px) {
            .navbar {
                flex-wrap: wrap;
                justify-content: space-between;
                height: auto;
                padding: 15px 20px;
                background: rgba(255, 255, 255, 0.98);
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
            .mobile-nav-overlay.open .mobile-nav {
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
            }
            header {
                margin-top: 75px; /* Disesuaikan untuk navbar tetap */
                padding: 50px 0 30px; /* Padding disesuaikan */
            }
            header h1 {
                font-size: 2.2em; /* Ukuran font disesuaikan */
            }
            header p {
                font-size: 1.1em;
                padding: 0 20px;
            }
            .section-divider {
                height: 70px;
            }
            main { /* Disesuaikan untuk konsistensi */
                padding: 20px 15px;
                margin: 20px auto;
            }
            .section-container {
                padding: 30px 20px; /* Padding disesuaikan */
                margin: 20px auto; /* Margin disesuaikan */
            }
            .section-container h2 {
                font-size: 2em; /* Ukuran font disesuaikan */
                margin-bottom: 25px;
            }
            .contact-info {
                flex-direction: column;
                align-items: center;
                gap: 20px;
            }
            .info-box {
                width: 100%; /* Lebar penuh di seluler */
                max-width: 380px; /* Lebar maksimum agar rapi */
                padding: 20px;
            }
            .contact-form-container {
                padding: 20px; /* Padding disesuaikan */
            }
            .contact-form-container h3 {
                font-size: 1.8em;
            }
        }

        @media (max-width: 480px) {
            .navbar .logo img {
                width: 90px;
                margin-left: 0;
            }
            header {
                padding: 40px 0 20px; /* Padding disesuaikan */
            }
            header h1 {
                font-size: 1.6em; /* Ukuran font lebih disesuaikan */
            }
            header p {
                font-size: 0.9em;
            }
            .section-divider {
                height: 50px;
            }
            .section-container {
                padding: 25px 10px; /* Padding lebih disesuaikan */
                margin: 15px auto; /* Margin lebih disesuaikan */
            }
            .section-container h2 {
                font-size: 1.8em; /* Ukuran font lebih disesuaikan */
                margin-bottom: 20px;
            }
            .info-box .icon {
                font-size: 1.6em;
            }
            .info-box .text h3 {
                font-size: 1.1em;
            }
            .info-box .text p, .info-box .text a {
                font-size: 0.8em;
            }
            .contact-form-container {
                padding: 15px;
            }
            .contact-form-container h3 {
                font-size: 1.5em;
            }
            .contact-form-container input[type="text"],
            .contact-form-container input[type="email"],
            .contact-form-container textarea {
                padding: 8px;
                font-size: 0.9em;
            }
            .contact-form-container button[type="submit"] {
                padding: 10px 15px;
                font-size: 0.95em;
            }
        }

        /* Gaya Khusus untuk tombol "Kembali ke Tentang Kami" */
        .back-to-about-btn {
            display: inline-block;
            background: linear-gradient(45deg, var(--color-gradient-end), var(--color-gradient-start)); /* Gradien terbalik atau berbeda agar menonjol */
            color: #ffffff;
            padding: 12px 25px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1em;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-medium);
            margin-top: 40px; /* Jarak dari form/konten di atasnya */
        }
        .back-to-about-btn:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-strong);
            opacity: 0.9;
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

    <header class="animate-on-scroll" data-animation="fade-in">
        <h1>Hubungi Kami</h1>
        <p>Kami siap mendengarkan. Jangan ragu untuk menghubungi kami melalui formulir di bawah ini atau detail kontak kami.</p>
    </header>

    <div class="section-divider bottom divider-header-to-contact">
        <svg data-name="Layer 1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120" preserveAspectRatio="none">
            <path d="M0,0V46.29c47.79,22.2,103.59,32.17,158,32.17,208.65,0,367.59-41.43,500.4-41.43,148.67,0,227.42,23.53,357.49,23.53,100.4,0,183.94-18.16,275-31.54V0Z" class="shape-fill"></path>
        </svg>
    </div>

    <main>
        <section class="section-container contact-section animate-on-scroll" data-animation="fade-in">
            <h2>Kirim Pesan Kepada Kami</h2>

            <div class="contact-info">
                <div class="info-box animate-on-scroll" data-animation="fade-up">
                    <i class="fas fa-map-marker-alt icon"></i>
                    <div class="text">
                        <h3>Alamat</h3>
                        <p><a href="https://www.google.com/maps?q=Jati,+Sidoarjo" target="_blank"> Jati Selatan 3, Sidoarjo, Jawa Timur</p>
                    </div>
                </div>
                <div class="info-box animate-on-scroll" data-animation="fade-up">
                    <i class="fas fa-envelope icon"></i>
                    <div class="text">
                        <h3>Email</h3>
                        <p><a href="mailto:falahmotor37@gmail.com">Falahmotor37@gmail.com</a></p>
                    </div>
                </div>
                <div class="info-box animate-on-scroll" data-animation="fade-up">
                    <i class="fas fa-phone-alt icon"></i>
                    <div class="text">
                        <h3>Telepon/WhatsApp</h3>
                        <p><a href="https://wa.me/6285808436591" target="_blank">+62 858-0843-6591</a></p>
                    </div>
                </div>
            </div>

            <div class="contact-form-container animate-on-scroll" data-animation="fade-in-scale">
                <h3>Formulir Kontak</h3>
                <?php if ($pesan_sukses): ?>
                    <div class="message-alert success animated">
                        <?php echo $pesan_sukses; ?>
                    </div>
                <?php endif; ?>
                <?php if ($pesan_error): ?>
                    <div class="message-alert error animated">
                        <?php echo $pesan_error; ?>
                    </div>
                <?php endif; ?>
                <form action="kontak.php" method="POST">
                    <label for="nama">Nama Lengkap:</label>
                    <input type="text" id="nama" name="nama" value="<?php echo htmlspecialchars($_POST['nama'] ?? ''); ?>" required>

                    <label for="email">Email Anda:</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>

                    <label for="subjek">Subjek:</label>
                    <input type="text" id="subjek" name="subjek" value="<?php echo htmlspecialchars($_POST['subjek'] ?? ''); ?>">

                    <label for="pesan">Pesan Anda:</label>
                    <textarea id="pesan" name="pesan" required><?php echo htmlspecialchars($_POST['pesan'] ?? ''); ?></textarea>

                    <button type="submit" class="btn-ripple">Kirim Pesan</button>
                </form>
            </div>

            <a href="about.php" class="back-to-about-btn animate-on-scroll" data-animation="fade-up">Kembali ke Halaman Tentang Kami</a>
        </section>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Navbar scroll effect
            const mainNavbar = document.getElementById('mainNavbar');
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

            // Hamburger Menu Logic
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
                const elements = document.querySelectorAll('.animate-on-scroll:not(.animated)');
                elements.forEach(element => {
                    // Skip elements handled by specific staggered animations (e.g., info-box, footer-column)
                    if (element.classList.contains('info-box') ||
                        element.classList.contains('footer-column')) {
                        return;
                    }

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

                // Staggered animation for info boxes
                const infoBoxes = document.querySelectorAll('.contact-info .info-box.animate-on-scroll:not(.animated)');
                infoBoxes.forEach((box, index) => {
                    if (isInViewport(box, 80)) {
                        setTimeout(() => {
                            box.classList.add('animated');
                            box.style.animation = 'fadeInUp 0.7s ease-out forwards';
                        }, index * 150);
                    }
                });

                // (Removed footer-column animation logic since footer is removed)

                // Animate message alerts if they appear after submission
                const messageAlerts = document.querySelectorAll('.message-alert:not(.animated)');
                messageAlerts.forEach(alert => {
                    // Ensure the alert is actually triggered before trying to animate
                    if (alert.style.opacity === '0' || !alert.style.opacity) {
                        alert.classList.add('animated');
                    }
                });
            }

            // Ripple effect on buttons
            document.querySelectorAll('.btn-ripple').forEach(button => {
                button.addEventListener('click', function(e) {
                    const x = e.clientX - e.target.getBoundingClientRect().left;
                    const y = e.clientY - e.target.getBoundingClientRect().top;

                    this.style.setProperty('--x', x + 'px');
                    this.style.setProperty('--y', y + 'px');
                });
            });

            // Run animations on load and scroll
            setTimeout(() => {
                animateOnScroll();
                window.addEventListener('scroll', animateOnScroll);
            }, 100); // Small delay to ensure DOM is fully rendered
        });
    </script>
</body>
</html>