<?php
session_start();
require_once 'config.php'; // Pastikan ini mengarah ke file konfigurasi yang benar

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

// Ambil data berita dari database
$sql = "SELECT id, judul, deskripsi, tanggal, foto, link FROM berita ORDER BY tanggal DESC"; // Order by date for relevance
$result = $conn->query($sql);

// Cek apakah query berhasil
if (!$result) {
    die("Query failed: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Berita & Kegiatan - Rumah AYP</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Palet Warna (Modern Clean with Dynamic Gradients) - Konsisten dengan index.php & about.php */
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

        /* Navbar - Konsisten dengan index.php & about.php */
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

        /* Header for Berita Page - Konsisten dengan about.php */
        header {
            background: linear-gradient(135deg, var(--color-gradient-start), var(--color-gradient-end)); /* Gradien dari variabel */
            color: #ffffff;
            padding: 80px 0; /* Consistent padding with about.php header */
            text-align: center;
            box-shadow: var(--shadow-strong);
            position: relative;
            margin-top: 75px; /* Space for fixed navbar */
            border-bottom-left-radius: 25px; /* Consistent radius with about.php header */
            border-bottom-right-radius: 25px;
            opacity: 0; /* For animation */
            transform: translateY(20px); /* For animation */
            transition: opacity 0.8s ease-out, transform 0.8s ease-out;
            overflow: hidden; /* Penting untuk pseudo-element */
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
        header.animated { /* Menggunakan 'animated' dari JS */
            opacity: 1;
            transform: translateY(0);
        }
        
        header h1 {
            font-size: 3em; /* Consistent with other page headers */
            margin: 0 auto 20px auto; /* Centered with margin-bottom */
            font-weight: 800; /* Bold from Montserrat */
            text-shadow: 2px 2px 6px rgba(0,0,0,0.3);
            max-width: 90%;
            line-height: 1.2;
            position: relative; /* Agar di atas overlay */
            z-index: 1;
        }
        header p { /* Added a sub-headline for the header */
            font-size: 1.3em;
            max-width: 900px;
            margin: 0 auto; /* Centered */
            padding: 0 30px;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.9);
            position: relative; /* Agar di atas overlay */
            z-index: 1;
        }
        
        main {
            padding: 40px 20px; /* Consistent padding */
            max-width: 1200px; /* Consistent max-width */
            margin: 40px auto; /* Consistent margin */
        }
        
        /* Main section title - Consistent styling */
        main h2 {
            text-align: center;
            margin-bottom: 40px;
            color: var(--color-text-primary); /* Consistent primary text color */
            font-size: 2.8rem; /* Consistent size */
            font-weight: 800; /* Consistent bold */
            position: relative;
            padding-bottom: 15px;
            opacity: 0; /* For animation */
            transform: translateY(20px); /* For animation */
            transition: opacity 0.6s ease-out, transform 0.6s ease-out;
        }
        main h2.animated {
            opacity: 1;
            transform: translateY(0);
        }
        
        main h2::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100px; /* Consistent width */
            height: 4px; /* Consistent height */
            background: linear-gradient(to right, var(--color-gradient-start), var(--color-gradient-end)); /* Consistent gradient */
            border-radius: 2px;
        }

        .berita-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 35px;
            justify-content: center; /* Center items when they don't fill a row */
            margin-top: 30px;
        }
        
        .berita-link {
            text-decoration: none;
            color: inherit; /* Inherit text color from parent for default */
            display: flex; /* Make it a flex container to align content */
            flex-direction: column; /* Stack content vertically */
            height: 100%; /* Ensure link takes full height of grid item */
        }

        .berita-item {
            background: var(--color-bg-secondary); /* White background */
            border: 1px solid var(--color-border-subtle); /* Subtle border */
            border-radius: 15px; /* More rounded corners */
            padding: 25px;
            box-shadow: var(--shadow-medium); /* Consistent shadow */
            transition: transform 0.4s ease, box-shadow 0.4s ease;
            display: flex;
            flex-direction: column;
            height: 100%; /* Important for consistent card height */
            overflow: hidden; /* Hide overflowing parts during hover */
            cursor: pointer; /* Indicate clickable */
            opacity: 0; /* For animation */
            transform: translateY(20px); /* For animation */
        }
        .berita-item.animated { /* Menggunakan 'animated' dari JS */
            opacity: 1;
            transform: translateY(0);
        }
        
        .berita-item:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: var(--shadow-strong);
        }
        
        .berita-item img {
            width: 100%;
            height: 200px; /* Consistent image height */
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 15px;
            transition: transform 0.6s ease;
        }

        .berita-item:hover img {
            transform: scale(1.1);
        }
        
        .berita-item h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--color-text-primary);
            margin-bottom: 10px;
            line-height: 1.3;
        }
        
        .berita-item p {
            font-size: 0.95em;
            color: var(--color-text-secondary);
            margin-bottom: 15px;
            flex-grow: 1; /* Allows description to take available space */
            line-height: 1.6;
        }

        .berita-item .date { /* Specific style for date */
            font-size: 0.85em;
            color: var(--color-text-secondary);
            margin-top: auto; /* Pushes date to the bottom of the card */
            align-self: flex-end; /* Aligns date to the right */
            background-color: rgba(150, 201, 61, 0.1); /* Light accent background */
            padding: 5px 10px;
            border-radius: 5px;
        }

        /* No news message */
        .berita-list > p { /* Target the direct p child of berita-list */
            grid-column: 1 / -1; /* Make it span all columns */
            text-align: center;
            font-size: 1.2em;
            color: var(--color-text-secondary);
            padding: 50px 0;
            opacity: 0; /* For animation */
            transform: translateY(20px); /* For animation */
            transition: opacity 0.6s ease-out, transform 0.6s ease-out;
        }
        .berita-list > p.animated {
            opacity: 1;
            transform: translateY(0);
        }

        /* Footer - Consistent styling */
         footer {
            background-color: var(--color-footer-bg); /* Dark blue-grey from about.php footer */
            color: white;
            padding: 60px 20px 30px; /* Consistent padding */
            margin-top: 50px; /* Add margin to separate from content */
            box-shadow: inset 0 8px 20px rgba(0, 0, 0, 0.25);
            border-top-left-radius: 30px;
            border-top-right-radius: 30px;
            position: relative;
            z-index: 1;
        }

        .footer-container {
            max-width: 1100px; /* Consistent max-width */
            margin: auto;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 40px; /* Increased gap for better spacing */
        }

        .footer-column {
            flex: 1 1 250px; /* Consistent flex basis */
            min-width: 200px;
        }

        .footer-column h3 {
            font-size: 1.5em; /* Consistent font size */
            margin-bottom: 20px;
            color: #FFFFFF;
            border-bottom: 2px solid var(--color-accent-secondary); /* Accent line under headings */
            padding-bottom: 10px;
            font-weight: 700;
        }

        .footer-column p,
        .footer-column a {
            font-size: 0.95em; /* Consistent font size */
            color: rgba(255, 255, 255, 0.7); /* Lighter grey for text */
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer-column ul {
            list-style: none;
            padding: 0;
        }

        .footer-column li {
            margin-bottom: 10px; /* Consistent margin */
        }

        .footer-column a:hover {
            color: var(--color-accent-secondary); /* Consistent hover color */
            text-decoration: underline;
        }

        .footer-icons {
            display: flex;
            gap: 15px; /* Consistent gap */
            margin-top: 20px;
        }

        .footer-icons a {
            display: inline-block;
            transition: transform 0.3s ease, opacity 0.3s;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.08);
            padding: 8px; /* Consistent padding */
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }

        .footer-icons a:hover {
            transform: scale(1.1); /* Consistent hover effect */
            opacity: 1;
            background-color: rgba(255, 255, 255, 0.15);
        }

        .footer-icons img {
            width: 28px; /* Consistent icon size */
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


        /* Responsive adjustments - Consistent across pages */
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
            main h2 {
                font-size: 2.4rem;
            }
            .berita-list {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                gap: 25px;
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
            main h2 {
                font-size: 1.8rem;
                margin-bottom: 30px;
            }
            main h2::after {
                width: 70px;
                height: 3px;
            }
            .berita-list {
                grid-template-columns: 1fr; /* Single column on small screens */
                gap: 20px;
            }
            .berita-item {
                padding: 20px;
            }
            .berita-item img {
                height: 180px;
            }
            .berita-item h3 {
                font-size: 1.3rem;
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
                font-size: 0.85em;
            }
            main h2 {
                font-size: 1.5rem;
            }
            .berita-item img {
                height: 150px;
            }
            .berita-item h3 {
                font-size: 1.1rem;
            }
            .berita-item p {
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
        <li><a href="about.php">Tentang Kami</a></li>
        <li><a href="program.php">Program</a></li>
        <li><a href="berita.php" class="active">Berita</a></li>
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
        <li><a href="berita.php" class="active">Berita</a></li>
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

<header class="animate-on-scroll">
    <h1>Berita & Kegiatan Rumah AYP</h1>
    <p>Ikuti perkembangan terbaru dan kisah inspiratif dari kegiatan kami.</p>
</header>

<main>
    <h2 class="animate-on-scroll">Informasi Terbaru</h2>
    <div class="berita-list">
        <?php
        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                echo "<a href='" . htmlspecialchars($row["link"]) . "' target='_blank' class='berita-link'>";
                echo "<div class='berita-item animate-on-scroll'>"; // Tambah animate-on-scroll
                echo "<img src='" . htmlspecialchars($row["foto"]) . "' alt='" . htmlspecialchars($row["judul"]) . "' onerror=\"this.src='images/no-photo.png';\">";
                echo "<h3>" . htmlspecialchars($row["judul"]) . "</h3>";
                echo "<p>" . htmlspecialchars(substr($row["deskripsi"], 0, 150));
                echo (strlen($row["deskripsi"]) > 150) ? '...' : '';
                echo "</p>";
                echo "<p class='date'>Tanggal: " . date("d F Y", strtotime($row["tanggal"])) . "</p>";
                echo "</div>";
                echo "</a>";
            }
        } else {
            echo "<p class='no-news-message animate-on-scroll'>Tidak ada berita yang tersedia saat ini. Mohon kembali lagi nanti!</p>"; // Tambah animate-on-scroll
        }
        ?>
    </div>
</main>

<footer>
    <div class="footer-container">
        <div class="footer-column animate-on-scroll">
            <h3>Rumah AYP</h3>
            <p>Yayasan sosial yang berdedikasi untuk membantu mereka yang membutuhkan dan menciptakan masa depan yang lebih baik.</p>
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
        const navbarLogo = document.getElementById('navbarLogo'); // Ambil referensi logo navbar
        // Atur opacity awal logo navbar menjadi 1 agar selalu terlihat di halaman berita.php
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

            // Animasi staggered untuk berita items
            const beritaItems = document.querySelectorAll('.berita-item.animate-on-scroll:not(.animated)');
            beritaItems.forEach((item, index) => {
                if (isInViewport(item, 80)) {
                    setTimeout(() => {
                        item.classList.add('animated');
                    }, index * 100);
                }
            });

            // Animasi untuk pesan "Tidak ada berita"
            const noNewsMessage = document.querySelector('.no-news-message.animate-on-scroll:not(.animated)');
            if (noNewsMessage && isInViewport(noNewsMessage, 80)) {
                noNewsMessage.classList.add('animated');
            }
        }

        // Jalankan animasi scroll saat halaman dimuat dan setiap kali di-scroll
        animateOnScroll(); // Initial check on load
        window.addEventListener('scroll', animateOnScroll);
    });
</script>
</body>
</html>