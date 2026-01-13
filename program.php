<?php
session_start(); // Pastikan session_start() dipanggil paling awal

// Koneksi ke database
$servername = "localhost"; // Ganti dengan nama server Anda
$username = "root"; // Ganti dengan username database Anda
$password = ""; // Ganti dengan password database Anda
$dbname = "yayasan_amal"; // Nama database yang telah dibuat

// Membuat koneksi
$conn = new mysqli($servername, $username, $password, $dbname);

// Cek koneksi
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Set zona waktu default ke Asia/Jakarta agar waktu PHP sesuai dengan WIB
date_default_timezone_set('Asia/Jakarta');

// Variabel untuk menyimpan pesan
$message = '';

// Variabel untuk melacak mode halaman
$donationMode = false;
$paymentInstructionMode = false;
$detailMode = false;

$programmId = null;
$programmData = null; // Data program yang sedang dilihat

// Inisialisasi variabel untuk daftar semua program, default null
$result_all_programs = null;

// Logika penentuan mode halaman
if(isset($_GET['donate']) && isset($_GET['id'])) {
    $donationMode = true;
    $programmId = (int)$_GET['id'];

    // Ambil data program yang spesifik untuk halaman donasi
    // Kita tidak lagi mengambil total_donasi dari tabel program karena akan dihitung dinamis
    $stmt = $conn->prepare("SELECT id, nama, deskripsi, gambar FROM programm WHERE id = ?");
    $stmt->bind_param("i", $programmId);
    $stmt->execute();
    $result_program_data = $stmt->get_result();

    if($result_program_data->num_rows > 0) {
        $programmData = $result_program_data->fetch_assoc();

        // Hitung total_donasi_paid secara dinamis untuk program ini
        $stmt_total_paid = $conn->prepare("SELECT SUM(jumlah) AS total_paid FROM donasi WHERE programm_id = ? AND status_pembayaran = 'paid'");
        $stmt_total_paid->bind_param("i", $programmId);
        $stmt_total_paid->execute();
        $total_paid_result = $stmt_total_paid->get_result()->fetch_assoc();
        $programmData['total_donasi_paid'] = $total_paid_result['total_paid'] ?? 0;
        $stmt_total_paid->close();

    } else {
        $message = "Program donasi tidak ditemukan.";
        $donationMode = false; // Batalkan mode donasi jika program tidak ada
    }
    $stmt->close();
}
elseif(isset($_POST['submit_donation'])) {
    if(!isset($_SESSION['user_id'])) {
        header("Location: login.php?redirect=program.php&msg=login_untuk_donasi");
        exit();
    } else {
        $programmId = (int)$_POST['programm_id'];
        $amount = (int)str_replace('.', '', $_POST['amount_formatted']);
        $nama = $conn->real_escape_string($_POST['nama']);
        $email = $conn->real_escape_string($_POST['email']);
        $pesan = $conn->real_escape_string($_POST['pesan']);
        $tanggal = date('Y-m-d H:i:s'); // Ini yang memastikan tanggal dan jam real-time terambil
        $userId = $_SESSION['user_id'];
        $paymentMethod = $conn->real_escape_string($_POST['payment_method']);

        $paymentStatus = 'pending'; // Status awal selalu pending

        if($amount < 10000) {
            $message = "Jumlah donasi minimal Rp 10.000.";
        } else {
            $conn->begin_transaction();
            try {
                // Insert donasi dengan status pending
                $stmt = $conn->prepare("INSERT INTO donasi (programm_id, user_id, nama, email, jumlah, pesan, tanggal, status_pembayaran, payment_method) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("iississss", $programmId, $userId, $nama, $email, $amount, $pesan, $tanggal, $paymentStatus, $paymentMethod);

                if(!$stmt->execute()) {
                    throw new Exception("Terjadi kesalahan saat menyimpan donasi: " . $stmt->error);
                }
                $stmt->close();

                $lastDonationId = $conn->insert_id;

                // Ambil data program yang diperlukan untuk instruksi pembayaran (jika belum terisi)
                // Hanya ambil nama, bukan total_donasi karena kita tidak update total_donasi di sini
                if (!$programmData) {
                    $stmt_program_info = $conn->prepare("SELECT nama FROM programm WHERE id = ?");
                    $stmt_program_info->bind_param("i", $programmId);
                    $stmt_program_info->execute();
                    $tempProgramData = $stmt_program_info->get_result()->fetch_assoc();
                    if($tempProgramData) {
                        $programmData['nama'] = $tempProgramData['nama'];
                    }
                    $stmt_program_info->close();
                }

                // Triggers di database akan menangani update total_donasi saat status_pembayaran berubah menjadi 'paid'
                // Jadi, tidak perlu update total_donasi di sini secara manual.

                $conn->commit();

                $_SESSION['payment_details'] = [
                    'amount' => $amount,
                    'donation_id' => $lastDonationId,
                    'program_name' => $programmData['nama'] ?? 'Nama Program Tidak Diketahui', // Fallback jika nama program gagal diambil
                    'payment_method' => $paymentMethod
                ];

                header("Location: program.php?payment_instruction=true&id=" . $programmId);
                exit();

            } catch (Exception $e) {
                $conn->rollback();
                $message = "Terjadi kesalahan: " . $e->getMessage();
            }
        }
    }
}
elseif(isset($_GET['payment_instruction']) && isset($_GET['id'])) {
    $paymentInstructionMode = true;
    $programmId = (int)$_GET['id'];

    $stmt = $conn->prepare("SELECT id, nama, deskripsi, gambar FROM programm WHERE id = ?");
    $stmt->bind_param("i", $programmId);
    $stmt->execute();
    $result_program_data = $stmt->get_result();
    if($result_program_data->num_rows > 0) {
        $programmData = $result_program_data->fetch_assoc();
    } else {
        header("Location: program.php?status=error&msg=Program_donasi_tidak_ditemukan.");
        exit();
    }
    $stmt->close();

    if(isset($_SESSION['payment_details'])) {
        $paymentDetails = $_SESSION['payment_details'];
        unset($_SESSION['payment_details']); // Hapus setelah digunakan
    } else {
        header("Location: program.php?status=error&msg=Sesi_pembayaran_tidak_ditemukan.");
        exit();
    }
}
elseif(isset($_GET['detail']) && isset($_GET['id'])) {
    $detailMode = true;
    $programmId = (int)$_GET['id'];

    // Ambil data program spesifik untuk halaman detail
    // Kita tidak lagi mengambil total_donasi dari tabel program karena akan dihitung dinamis
    $stmt = $conn->prepare("SELECT id, gambar, nama, deskripsi FROM programm WHERE id = ?");
    $stmt->bind_param("i", $programmId);
    $stmt->execute();
    $result_program_data = $stmt->get_result();

    if($result_program_data->num_rows > 0) {
        $programmData = $result_program_data->fetch_assoc();

        // Hitung total_donasi_paid secara dinamis untuk program ini
        $stmt_total_paid = $conn->prepare("SELECT SUM(jumlah) AS total_paid FROM donasi WHERE programm_id = ? AND status_pembayaran = 'paid'");
        $stmt_total_paid->bind_param("i", $programmId);
        $stmt_total_paid->execute();
        $total_paid_result = $stmt_total_paid->get_result()->fetch_assoc();
        $programmData['total_donasi_paid'] = $total_paid_result['total_paid'] ?? 0;
        $stmt_total_paid->close();

    } else {
        $message = "Program tidak ditemukan.";
    }
    $stmt->close();

    // Ambil data donasi terkait program (Hanya yang sudah 'paid')
    $donasi = [];
    $stmt = $conn->prepare("SELECT nama, jumlah, pesan, tanggal FROM donasi WHERE programm_id = ? AND status_pembayaran = 'paid' ORDER BY tanggal DESC LIMIT 5");
    $stmt->bind_param("i", $programmId);
    $stmt->execute();
    $donasiResult = $stmt->get_result();

    while($row = $donasiResult->fetch_assoc()) {
        $donasi[] = $row;
    }
    $stmt->close();
}
// Ini adalah kondisi default: jika tidak ada mode khusus, tampilkan semua program
else {
    // Ambil semua program, tapi jangan ambil total_donasi dari kolom di tabel programm
    $sql = "SELECT id, nama, deskripsi, gambar FROM programm";
    $result_all_programs = $conn->query($sql); // Variabel ini diisi di sini

    if ($result_all_programs) {
        $programs_data_with_total = [];
        while($row = $result_all_programs->fetch_assoc()) {
            // Untuk setiap program, hitung total_donasi_paid secara dinamis
            $stmt_total_paid = $conn->prepare("SELECT SUM(jumlah) AS total_paid FROM donasi WHERE programm_id = ? AND status_pembayaran = 'paid'");
            $stmt_total_paid->bind_param("i", $row['id']);
            $stmt_total_paid->execute();
            $total_paid_result = $stmt_total_paid->get_result()->fetch_assoc();
            $row['total_donasi_paid'] = $total_paid_result['total_paid'] ?? 0;
            $stmt_total_paid->close();
            $programs_data_with_total[] = $row;
        }
        $result_all_programs = $programs_data_with_total; // Ganti dengan data yang sudah dihitung
    } else {
        error_log("Query for all programs failed: " . $conn->error);
        $message = "Terjadi kesalahan saat memuat program. Silakan coba lagi nanti.";
        $result_all_programs = []; // Pastikan array kosong jika ada error
    }
}
?>


<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php
        if ($donationMode && $programmData) {
            echo "Donasi - ".htmlspecialchars($programmData['nama']);
        } elseif ($detailMode && $programmData) {
            echo "Detail - ".htmlspecialchars($programmData['nama']);
        } elseif ($paymentInstructionMode && $programmData) {
            echo "Instruksi Pembayaran - ".htmlspecialchars($programmData['nama']);
        } else {
            echo "Program Yayasan Amal";
        }
    ?></title>
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

            /* Warna spesifik untuk tombol interaksi/adopsi (sesuai gambar di profilyatim.php) */
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
            opacity: 0; /* Untuk animasi */
            transform: translateY(20px); /* Untuk animasi */
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
        header p {
            font-size: 1.1em;
            max-width: 700px;
            margin: 15px auto 0;
            opacity: 0.9;
        }

        main {
            max-width: 1200px; /* Lebar maksimum konten */
            margin: 40px auto; /* Margin di atas dan bawah */
            padding: 0 20px; /* Padding samping */
        }

        /* Section Titles - Rebranded */
        main h2 {
            text-align: center;
            margin-bottom: 40px; /* More space below heading */
            color: var(--color-text-primary); /* Use primary text color */
            font-size: 2.8rem; /* Larger font size */
            font-weight: 800; /* Bolder font weight */
            position: relative;
            padding-bottom: 15px;
            opacity: 0; /* For animation */
            transform: translateY(20px); /* For animation */
            transition: opacity 0.6s ease-out, transform 0.6s ease-out;
        }

        main h2::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100px; /* Wider underline */
            height: 4px; /* Thicker underline */
            background: linear-gradient(to right, var(--color-gradient-start), var(--color-gradient-end)); /* Gradien underline */
            border-radius: 2px;
        }
        main h2.animated { /* Class untuk animasi */
            opacity: 1;
            transform: translateY(0);
        }

        .title { /* Specific class for top heading on program list page */
            padding-top: 20px; /* Adjust padding for better visual balance */
        }

        /* Program List - Rebranded (adapted from profile-list) */
        .program-list {
            display: grid; /* Menggunakan Grid */
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); /* Kolom responsif, min 300px */
            gap: 30px; /* Jarak antar kartu */
            justify-content: center;
            list-style-type: none; /* Hilangkan bullet point */
            padding: 0; /* Hilangkan padding default ul */
        }

        .program-item {
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
            height: 100%; /* Pastikan kartu memiliki tinggi yang sama */
        }
        .program-item.animated { /* Animasi saat muncul */
            opacity: 1;
            transform: translateY(0);
            transition: opacity 0.6s ease-out, transform 0.6s ease-out;
        }

        .program-item:hover {
            transform: translateY(-8px); /* Efek lift saat hover */
            box-shadow: var(--shadow-strong); /* Bayangan lebih kuat */
            background-color: var(--color-bg-primary); /* Sedikit perubahan warna saat hover */
        }
        .program-image {
            height: 220px; /* Tinggi gambar konsisten */
            overflow: hidden;
            border-bottom: 1px solid var(--color-border-subtle); /* Pemisah gambar dan info */
        }
        .program-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        .program-item:hover .program-image img {
            transform: scale(1.08); /* Zoom gambar saat hover */
        }
        .program-content {
            padding: 25px;
            flex-grow: 1; /* Agar konten mengisi ruang */
            display: flex;
            flex-direction: column;
        }
        .program-title {
            text-decoration: none;
            color: var(--color-text-primary); /* Darker text for title */
            font-weight: 700;
            margin-bottom: 15px;
            font-size: 1.5rem; /* Larger title font */
            line-height: 1.3;
            transition: color 0.3s;
        }

        .program-title:hover {
            color: var(--color-gradient-start); /* Highlight on hover with primary color */
        }

        .program-description {
            margin-bottom: 15px;
            color: var(--color-text-secondary);
            flex-grow: 1;
            font-size: 0.95em;
            line-height: 1.7;
            max-height: 100px; /* Batasi tinggi deskripsi */
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 4; /* Batasi 4 baris */
            -webkit-box-orient: vertical;
        }

        .program-progress-text { /* New style for total donasi text */
            font-size: 0.95em;
            color: var(--color-text-primary);
            margin-top: 10px;
            margin-bottom: 20px;
            text-align: right; /* Align to the right */
            font-weight: 600;
        }

        /* Buttons (Donasi Sekarang, Lanjutkan Pembayaran, Batalkan) - Rebranded (adapted from profilyatim.php's buttons) */
        .donate-button, .btn-primary { /* Primary action button */
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
            cursor: pointer;
            text-align: center;
            align-self: flex-start; /* Sejajarkan ke kiri (start) dalam flex container */
            margin-top: auto; /* Push to bottom for program-item */
            width: 100%; /* Make button full width in card */
        }

        .donate-button:hover, .btn-primary:hover {
            background: linear-gradient(45deg, var(--color-gradient-end), var(--color-gradient-start));
            transform: translateY(-3px) scale(1.01);
            box-shadow: var(--shadow-strong);
        }

        .btn-secondary { /* Secondary action button (e.g., Batalkan) */
            display: inline-block;
            background-color: transparent;
            color: var(--color-text-primary);
            padding: 12px 25px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1em;
            transition: all 0.3s ease;
            border: 1px solid var(--color-border-subtle);
            cursor: pointer;
            text-align: center;
            box-shadow: var(--shadow-light);
            width: auto; /* Let content define width */
        }
        .btn-secondary:hover {
            background-color: var(--color-bg-primary);
            box-shadow: var(--shadow-medium);
            transform: translateY(-2px);
        }


        /* Donation Form Styles - Rebranded */
        .donation-container {
            background: var(--color-bg-secondary);
            border-radius: 15px;
            padding: 40px; /* More padding */
            box-shadow: var(--shadow-medium);
            max-width: 750px; /* Wider form */
            margin: 30px auto; /* More margin */
            border: 1px solid var(--color-border-subtle);
            opacity: 0; /* For animation */
            transform: translateY(20px); /* For animation */
            transition: opacity 0.6s ease-out, transform 0.6s ease-out;
        }
        .donation-container.animated { /* Class for animation */
            opacity: 1;
            transform: translateY(0);
        }

        .form-group {
            margin-bottom: 25px; /* More space between form groups */
        }

        .form-group label {
            display: block;
            margin-bottom: 10px; /* More space below label */
            font-weight: 600; /* Bolder labels */
            color: var(--color-text-primary);
            font-size: 1.05rem;
        }

        .form-control {
            width: 100%;
            padding: 14px 18px; /* Larger padding */
            border: 1px solid var(--color-border-subtle);
            border-radius: 10px; /* More rounded inputs */
            font-size: 1.05rem;
            transition: border-color 0.3s, box-shadow 0.3s;
            background-color: #fcfdfe; /* Slightly off-white input background */
        }

        .form-control:focus {
            border-color: var(--color-gradient-start);
            box-shadow: 0 0 0 3px rgba(79, 195, 247, 0.2); /* Soft focus ring */
            outline: none;
        }

        /* Payment Methods */
        .payment-methods {
            margin-top: 30px;
            margin-bottom: 25px;
        }
        .payment-methods label {
            font-weight: 600;
            color: var(--color-text-primary);
            font-size: 1.05rem;
            margin-bottom: 15px;
            display: block;
        }
        .payment-options {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }
        .payment-option {
            display: flex;
            align-items: center;
            background-color: var(--color-bg-primary); /* Soft light gray */
            border: 2px solid var(--color-border-subtle);
            border-radius: 10px;
            padding: 15px 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            flex-grow: 1; /* Allow options to grow */
            min-width: 200px; /* Minimum width for each option */
        }
        .payment-option:hover {
            border-color: var(--color-gradient-start);
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }
        .payment-option input[type="radio"] {
            margin-right: 15px;
            width: 20px;
            height: 20px;
            accent-color: var(--color-gradient-start); /* Custom color for radio button */
        }
        .payment-option img {
            height: 30px;
            width: auto;
            margin-right: 10px;
        }
        .payment-option span {
            font-weight: 500;
            color: var(--color-text-primary);
        }
        .payment-option.selected {
            border-color: var(--color-gradient-start);
            box-shadow: 0 0 0 3px rgba(79, 195, 247, 0.2);
            background-color: #e0f7fa; /* Light blue tint */
        }

        .payment-details-info {
            background-color: #e0f7fa; /* Light blue tint */
            border: 1px solid var(--color-gradient-start);
            border-radius: 10px;
            padding: 25px;
            margin-top: 30px;
            font-size: 1.05rem;
            color: var(--color-text-primary);
        }
        .payment-details-info h4 {
            color: var(--color-gradient-start);
            font-size: 1.4rem;
            margin-bottom: 15px;
            font-weight: 700;
        }
        .payment-details-info p {
            margin-bottom: 10px;
        }
        .payment-details-info strong {
            color: #333;
        }
        .qr-code-img {
            max-width: 150px;
            height: auto;
            display: block;
            margin: 20px auto 0;
            border: 1px solid var(--color-border-subtle);
            border-radius: 5px;
        }


        .donation-summary {
            background-color: var(--color-bg-primary); /* Use light background */
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px; /* More margin */
            border: 1px solid var(--color-border-subtle);
            display: flex; /* Flexbox for alignment */
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap; /* Allow wrapping */
            box-shadow: var(--shadow-light);
        }

        .donation-program {
            font-weight: 700;
            font-size: 1.4rem;
            color: var(--color-gradient-start);
            margin-bottom: 5px;
        }
        .donation-summary p {
            margin: 0;
            font-size: 1.1em;
            color: var(--color-text-secondary);
            flex-basis: 100%;
        }
        .donation-current-amount {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--color-gradient-end); /* Green accent */
            white-space: nowrap;
            margin-top: 10px;
        }

        .alert {
            padding: 20px 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            font-size: 1.1rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 15px;
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.6s ease-out, transform 0.6s ease-out;
        }
        .alert.animated { /* Class for animation */
            opacity: 1;
            transform: translateY(0);
        }

        .alert-success {
            background-color: #e6f7e9;
            color: #1a6f2c;
            border: 1px solid #c8e6c9;
        }
        .alert-success::before {
            content: '✓';
            font-size: 1.5em;
            color: #28a745;
        }

        .alert:not(.alert-success) { /* For error alerts */
            background-color: #fcebeb;
            color: #8c2a2a;
            border: 1px solid #f5c6cb;
        }
        .alert:not(.alert-success)::before {
            content: '!';
            font-size: 1.5em;
            color: #dc3545;
            font-weight: bold;
            display: inline-block;
            width: 20px;
            text-align: center;
        }

        /* Program Detail Styles - Rebranded (adapted from profilyatim.php's modal-content) */
        .program-detail {
            background: var(--color-bg-secondary);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow-medium);
            margin-bottom: 40px;
            border: 1px solid var(--color-border-subtle);
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.6s ease-out, transform 0.6s ease-out;
            max-width: 900px; /* Max width for detail page content */
            margin-left: auto;
            margin-right: auto;
        }
        .program-detail.animated { /* Class for animation */
            opacity: 1;
            transform: translateY(0);
        }

        .program-detail-image {
            width: 100%;
            height: 400px; /* Same height as modal image */
            overflow: hidden;
            border-bottom: 1px solid var(--color-border-subtle);
        }

        .program-detail-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .program-detail-content {
            padding: 35px;
        }

        .program-detail-title {
            font-size: 2.5rem;
            color: var(--color-text-primary);
            margin-bottom: 25px;
            font-weight: 800;
            text-align: center;
            position: relative;
            padding-bottom: 15px;
            display: inline-block; /* To center the underline */
            left: 50%;
            transform: translateX(-50%);
            white-space: nowrap;
        }
        .program-detail-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background: linear-gradient(to right, var(--color-gradient-start), var(--color-gradient-end));
            border-radius: 2px;
        }

        .program-detail-description {
            margin-bottom: 30px;
            line-height: 1.8;
            font-size: 1.05rem;
            color: var(--color-text-secondary);
        }

        .program-detail-target,
        .program-detail-collected {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--color-text-primary);
            margin-bottom: 25px;
            padding: 12px 20px;
            background-color: var(--color-bg-primary);
            border-radius: 8px;
            display: inline-block;
            border: 1px solid var(--color-border-subtle);
            box-shadow: var(--shadow-light);
        }
        .program-detail-collected {
            color: var(--color-gradient-end); /* Green accent */
            background-color: rgba(139, 195, 74, 0.1);
            border-color: rgba(139, 195, 74, 0.2);
            margin-left: 20px; /* Space between target and collected */
        }
        .program-detail-target {
            color: var(--color-gradient-start); /* Blue accent */
            background-color: rgba(79, 195, 247, 0.1);
            border-color: rgba(79, 195, 247, 0.2);
        }


        .action-buttons {
            display: flex;
            gap: 20px;
            margin-top: 30px;
            justify-content: center;
            flex-wrap: wrap;
        }

        /* Donor List */
        .donor-list {
            margin-top: 50px;
            background-color: var(--color-bg-secondary);
            padding: 35px;
            border-radius: 15px;
            box-shadow: var(--shadow-medium);
            border: 1px solid var(--color-border-subtle);
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.6s ease-out, transform 0.6s ease-out;
            max-width: 900px; /* Max width for detail page content */
            margin-left: auto;
            margin-right: auto;
        }
        .donor-list.animated { /* Class for animation */
            opacity: 1;
            transform: translateY(0);
        }

        .donor-list h3 {
            margin-bottom: 25px;
            font-size: 2rem;
            color: var(--color-text-primary);
            text-align: center;
            position: relative;
            padding-bottom: 10px;
            font-weight: 800;
        }
        .donor-list h3::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 3px;
            background: linear-gradient(to right, var(--color-gradient-start), var(--color-gradient-end));
            border-radius: 2px;
        }

        .donor-item {
            background: #fdfdfd;
            padding: 18px 25px;
            border-radius: 10px;
            margin-bottom: 15px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            border: 1px solid #f0f4f2;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .donor-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .donor-info {
            flex: 1;
            min-width: 180px;
        }

        .donor-name {
            font-weight: 700;
            color: var(--color-text-primary);
            font-size: 1.1rem;
            margin-bottom: 5px;
        }

        .donor-message {
            color: var(--color-text-secondary);
            margin-top: 5px;
            font-style: italic;
            font-size: 0.95rem;
        }

        .donor-amount {
            font-weight: 700;
            color: var(--color-gradient-start);
            background-color: rgba(79, 195, 247, 0.1);
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 1.05rem;
            white-space: nowrap;
            margin-left: 20px;
        }

        .donor-date {
            font-size: 0.88rem;
            color: #888;
            margin-top: 5px;
            width: 100%;
            text-align: right;
            order: 3;
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
            .program-item {
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            }
            .donation-container {
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
            main h2 {
                font-size: 2rem;
                margin-bottom: 30px;
                padding-bottom: 10px;
            }
            main h2::after {
                width: 80px;
                height: 3px;
            }
            .program-list {
                grid-template-columns: 1fr;
                gap: 25px;
            }
            .program-item {
                padding: 20px;
            }
            .program-image {
                height: 180px;
            }
            .program-title {
                font-size: 1.3rem;
            }
            .donation-container {
                padding: 25px;
            }
            .program-detail-image {
                height: 250px;
            }
            .program-detail-title {
                font-size: 1.8rem;
            }
            .program-detail-content {
                padding: 25px;
            }

            .action-buttons {
                flex-direction: column;
                gap: 15px;
            }
            .donate-button, .btn-primary, .btn-secondary {
                width: 100%;
                padding: 12px 20px;
            }

            .program-detail-target,
            .program-detail-collected {
                margin-left: 0;
                margin-bottom: 15px;
                display: block;
                text-align: center;
                width: 100%;
            }

            .donor-list {
                padding: 25px;
            }
            .donor-list h3 {
                font-size: 1.6rem;
                padding-bottom: 8px;
            }
            .donor-item {
                flex-direction: column;
                align-items: flex-start;
                padding: 15px;
            }
            .donor-info {
                flex: none; /* Reset flex-grow */
                width: 100%;
                min-width: unset;
            }
            .donor-amount {
                margin-left: 0;
                margin-top: 10px;
                width: auto;
                align-self: flex-end;
                order: 2;
            }
            .donor-date {
                text-align: left;
                margin-top: 8px;
                order: 4;
            }
        }

        @media (max-width: 480px) {
            .navbar .logo img {
                width: 90px;
            }
            header h1 {
                font-size: 1.9rem;
            }
            header p {
                font-size: 0.9em;
            }
            main h2 {
                font-size: 1.6rem;
                margin-bottom: 25px;
            }
            main h2::after {
                width: 60px;
            }
            .program-item {
                padding: 15px;
            }
            .program-image {
                height: 160px;
            }
            .program-title {
                font-size: 1.2rem;
            }
            .program-detail-title {
                font-size: 1.6rem;
                margin-bottom: 20px;
            }
            .program-detail-description {
                font-size: 0.95rem;
            }
            .donor-list h3 {
                font-size: 1.4rem;
            }
            .donor-name {
                font-size: 1rem;
            }
            .donor-message {
                font-size: 0.85rem;
            }
            .donor-amount {
                font-size: 0.95rem;
                padding: 6px 12px;
            }
            .payment-options {
                flex-direction: column;
                gap: 15px;
            }
            .payment-option {
                min-width: unset;
                width: 100%;
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
            <li><a href="program.php" class="active">Program</a></li>
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
            <li><a href="program.php" class="active">Program</a></li>
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


    <main>
        <?php if(isset($_GET['status']) && $_GET['status'] == 'success'): ?>
            <div class="alert alert-success animated">
                Donasi Anda telah berhasil dikirim. Terima kasih atas kontribusi Anda!
            </div>
        <?php elseif(isset($_GET['status']) && $_GET['status'] == 'error' && isset($_GET['msg'])): ?>
            <div class="alert animated">
                Terjadi kesalahan: <?php echo htmlspecialchars(str_replace('_', ' ', $_GET['msg'])); ?>
            </div>
        <?php endif; ?>

        <?php if(!empty($message)): ?>
            <div class="alert <?php echo strpos($message, 'berhasil') !== false ? 'alert-success' : ''; ?> animated">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if($paymentInstructionMode && $programmData && isset($paymentDetails)): ?>
            <header class="animated">
                <h1>Instruksi Pembayaran Donasi</h1>
                <p>Donasi Anda untuk "<?php echo htmlspecialchars($programmData['nama']); ?>" berhasil tercatat. Mohon selesaikan pembayaran.</p>
            </header>

            <div class="donation-container animated">
                <h2>Langkah Selanjutnya</h2>
                <div class="payment-details-info">
                    <h4>Detail Donasi Anda</h4>
                    <p>ID Donasi: <strong>#<?php echo htmlspecialchars($paymentDetails['donation_id']); ?></strong></p>
                    <p>Program Donasi: <strong><?php echo htmlspecialchars($programmData['nama']); ?></strong></p>
                    <p>Jumlah Donasi: <strong>Rp <?php echo number_format($paymentDetails['amount'], 0, ',', '.'); ?></strong></p>
                    <p>Metode Pembayaran: <strong><?php echo htmlspecialchars($paymentDetails['payment_method'] == 'bank_transfer' ? 'Transfer Bank' : 'E-Wallet / QRIS'); ?></strong></p>
                </div>

                <?php if ($paymentDetails['payment_method'] == 'bank_transfer'): ?>
                    <div class="payment-details-info" style="margin-top: 20px;">
                        <h4>Transfer Bank</h4>
                        <p>Mohon transfer sejumlah Rp <?php echo number_format($paymentDetails['amount'], 0, ',', '.'); ?> ke rekening berikut:</p>
                        <p><strong>Bank: BCA</strong></p>
                        <p><strong>Nomor Rekening: 1234-5678-90 (a.n. Rumah AYP)</strong></p>
                        <p style="font-style: italic; color: #555;">Pastikan nominal transfer sesuai untuk memudahkan verifikasi.</p>
                    </div>
                <?php elseif ($paymentDetails['payment_method'] == 'e_wallet_qris'): ?>
                    <div class="payment-details-info" style="margin-top: 20px;">
                        <h4>Pembayaran via E-Wallet (QRIS)</h4>
                        <p>Mohon scan QRIS di bawah ini dengan aplikasi e-wallet Anda (Gopay, OVO, Dana, LinkAja, dll.) dan masukkan jumlah donasi Rp <?php echo number_format($paymentDetails['amount'], 0, ',', '.'); ?>.</p>
                        <img src="qris.jpg" alt="QRIS Code" class="qr-code-img"> <p style="text-align: center; font-style: italic; color: #555;"></p>
                    </div>
                <?php endif; ?>

                <div class="payment-details-info" style="margin-top: 20px;">
                    <h4>Konfirmasi Pembayaran</h4>
                    <p>Setelah melakukan pembayaran, mohon lakukan konfirmasi agar donasi Anda segera diproses:</p>
                   <p>Kirim bukti transfer/pembayaran Anda ke: <strong>WA <a href="https://wa.me/6285808436591" target="_blank" style="color: #007bff; text-decoration: none;">0858-8084-36591</a> (Admin Rumah AYP)</strong></p> <p>Sertakan ID Donasi Anda: <strong>#<?php echo htmlspecialchars($paymentDetails['donation_id']); ?></strong></p>
                </div>

                <div class="action-buttons">
                    <a href="program.php" class="btn-primary">Selesai / Kembali ke Program</a>
                </div>
            </div>

        <?php elseif($donationMode && $programmData): ?>
            <header class="animated">
                <h1>Ayo Berdonasi untuk "<?php echo htmlspecialchars($programmData['nama']); ?>"</h1>
                <p>Setiap donasi Anda membawa harapan baru bagi mereka yang membutuhkan.</p>
            </header>

            <div class="donation-container animated">
                <div class="donation-summary">
                    <div>
                        <h3 class="donation-program"><?php echo htmlspecialchars($programmData['nama']); ?></h3>
                        <p><?php echo htmlspecialchars(substr($programmData['deskripsi'], 0, 100)); ?>...</p>
                    </div>
                    <div class="donation-current-amount">
                        Terkumpul: Rp <?php echo number_format($programmData['total_donasi_paid'], 0, ',', '.'); ?>
                    </div>
                </div>

                <form action="program.php" method="POST">
                    <input type="hidden" name="programm_id" value="<?php echo $programmId; ?>">
                    <input type="hidden" name="amount_formatted" id="amount_hidden">
                    <div class="form-group">
                        <label for="nama">Nama (akan ditampilkan)</label>
                        <input type="text" id="nama" name="nama" class="form-control" value="<?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : ''; ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" class="form-control" value="<?php echo isset($_SESSION['email']) ? htmlspecialchars($_SESSION['email']) : ''; ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="amount">Jumlah Donasi (Rp)</label>
                        <input type="text" id="amount" name="amount" class="form-control" value="10.000" required pattern="[0-9.,]+" inputmode="numeric">
                        <small style="color:var(--color-text-secondary); display: block; margin-top: 5px;">Minimal donasi Rp 10.000</small>
                    </div>

                    <div class="payment-methods">
                        <label>Pilih Metode Pembayaran</label>
                        <div class="payment-options">
                            <label class="payment-option">
                                <input type="radio" name="payment_method" value="bank_transfer" required checked>
                                <img src="https://cdn-icons-png.flaticon.com/512/1054/1054238.png" alt="Bank Transfer Icon">
                                <span>Transfer Bank</span>
                            </label>
                            <label class="payment-option">
                                <input type="radio" name="payment_method" value="e_wallet_qris" required>
                                <img src="https://cdn-icons-png.flaticon.com/512/12732/12732103.png" alt="QRIS Icon">
                                <span>E-Wallet / QRIS</span>
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="pesan">Pesan/Doa (opsional)</label>
                        <textarea id="pesan" name="pesan" class="form-control" rows="4" placeholder="Tulis pesan atau doa terbaik Anda di sini..."></textarea>
                    </div>

                    <div class="action-buttons">
                        <a href="program.php" class="btn-secondary">Batalkan</a>
                        <button type="submit" name="submit_donation" class="btn-primary">Lanjutkan Pembayaran</button>
                    </div>
                </form>
            </div>

        <?php elseif($detailMode && $programmData): ?>
            <div class="program-detail animated">
                <div class="program-detail-image">
                    <img src="<?php echo htmlspecialchars($programmData['gambar']); ?>" alt="<?php echo htmlspecialchars($programmData['nama']); ?>">
                </div>

                <div class="program-detail-content">
                    <h1 class="program-detail-title"><?php echo htmlspecialchars($programmData['nama']); ?></h1>
                    <p class="program-detail-description"><?php echo nl2br(htmlspecialchars($programmData['deskripsi'])); ?></p>
                    <div style="display: flex; flex-wrap: wrap; justify-content: center; gap: 15px; margin-bottom: 25px;">
                        <div class="program-detail-target">Target Dana: Rp <?php echo number_format(100000000, 0, ',', '.'); /* Assuming a fixed target for demonstration */ ?></div>
                        <div class="program-detail-collected">Terkumpul: Rp <?php echo number_format($programmData['total_donasi_paid'], 0, ',', '.'); ?></div>
                    </div>

                    <div class="action-buttons">
                        <a href="program.php" class="btn-secondary">Kembali ke Daftar Program</a>
                        <a href="program.php?donate=true&id=<?php echo $programmId; ?>" class="btn-primary">Donasi Sekarang</a>
                    </div>
                </div>
            </div>

            <?php if(!empty($donasi)): ?>
                <div class="donor-list animated">
                    <h3>Donatur Terbaru</h3>
                    <?php foreach($donasi as $d): ?>
                        <div class="donor-item">
                            <div class="donor-info">
                                <div class="donor-name"><?php echo htmlspecialchars($d['nama']); ?></div>
                                <?php if(!empty($d['pesan'])): ?>
                                    <div class="donor-message">"<?php echo htmlspecialchars($d['pesan']); ?>"</div>
                                <?php endif; ?>
                            </div>
                            <div class="donor-amount">Rp <?php echo number_format($d['jumlah'], 0, ',', '.'); ?></div>
                            <div class="donor-date"><?php echo date('d M Y H:i:s', strtotime($d['tanggal'])); ?></div> </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="donor-list animated">
                    <h3>Donatur Terbaru</h3>
                    <p style="text-align: center; color: #777; padding: 20px;">Belum ada donatur yang terverifikasi untuk program ini. Jadilah yang pertama berdonasi!</p>
                </div>
            <?php endif; ?>

        <?php else: /* Ini adalah bagian utama untuk menampilkan semua program */ ?>
            <header class="animated">
                <h1>Program-Program Bantuan Kami</h1>
            </header>

            <h2 class="title animated">Pilih Program Donasi Pilihan Anda</h2>
            <ul class="program-list" >
                <?php if (!empty($result_all_programs)): // Menggunakan !empty() karena sekarang bisa jadi array kosong atau sudah berisi data ?>
                    <?php foreach($result_all_programs as $row): // Loop melalui array yang sudah diproses ?>
                        <li class="program-item animated">
                            <div class="program-image">
                                <img src="<?php echo htmlspecialchars($row['gambar']); ?>" alt="<?php echo htmlspecialchars($row['nama']); ?>" onerror="this.src='images/placeholder_program.png';">
                            </div>
                            <div class="program-content">
                                <a href="program.php?detail=true&id=<?php echo $row['id']; ?>" class="program-title"><?php echo htmlspecialchars($row['nama']); ?></a>
                                <p class="program-description"><?php echo htmlspecialchars(substr($row['deskripsi'], 0, 150)); ?><?php echo (strlen($row['deskripsi']) > 150) ? '...' : ''; ?></p>
                                <p class="program-progress-text">Terkumpul: <strong>Rp <?php echo number_format($row['total_donasi_paid'], 0, ',', '.'); ?></strong></p>
                                <a href="program.php?donate=true&id=<?php echo $row['id']; ?>" class="donate-button">Donasi Sekarang</a>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align: center; grid-column: 1 / -1; margin: 30px 0; color: #666;" class="animated">Tidak ada program yang tersedia saat ini.</p>
                <?php endif; ?>
            </ul>
        <?php endif; ?>
    </main>

    <footer class="animated">
    <div class="footer-container">
        <div class="footer-column animated">
            <h3>Rumah AYP</h3>
            <p>Yayasan sosial yang berdedikasi untuk membantu mereka yang dibutuhkan dan menciptakan masa depan yang lebih baik.</p>
        </div>

        <div class="footer-column animated">
            <h3>Navigasi Cepat</h3>
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="about.php">Tentang Kami</a></li>
                <li><a href="program.php">Program Donasi</a></li>
                <li><a href="berita.php">Berita & Artikel</a></li>
                <li><a href="profilyatim.php">Profil Anak Asuh</a></li>
            </ul>
        </div>

        <div class="footer-column animated">
            <h3>Jam Layanan</h3>
            <p>Senin - Jumat: 08.00 - 17.00 WIB</p>
            <p>Sabtu - Minggu: Tutup (Kecuali acara khusus)</p>
        </div>

        <div class="footer-column animated">
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
        // --- Navbar Scroll Logic (from profilyatim.php) ---
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
        // Initial check on load for navbar
        if (window.scrollY > 30) {
            mainNavbar.classList.add('scrolled');
        } else {
            mainNavbar.classList.remove('scrolled');
        }

        // --- Hamburger Menu Logic (from profilyatim.php) ---
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

        // Function to check if an element is in viewport (from profilyatim.php)
        function isInViewport(element, offset = 0) {
            if (!element) return false;
            const rect = element.getBoundingClientRect();
            const viewportHeight = (window.innerHeight || document.documentElement.clientHeight);
            return (
                rect.top <= (viewportHeight - offset) &&
                rect.bottom >= offset
            );
        }

        // Function to add animation class when element enters viewport (adapted for program elements)
        function animateOnScroll() {
            // Animate header
            const headerElement = document.querySelector('header.animated:not(.animated)');
            if (headerElement && isInViewport(headerElement, 100)) {
                headerElement.classList.add('animated');
            }

            // Animate main h2 title
            const mainTitle = document.querySelector('main h2.animated:not(.animated)');
            if (mainTitle && isInViewport(mainTitle, 100)) {
                mainTitle.classList.add('animated');
            }

            // Animate program items with stagger (similar to profile-cards)
            const programItems = document.querySelectorAll('.program-item.animated:not(.animated)');
            programItems.forEach((item, index) => {
                if (isInViewport(item, 80)) {
                    setTimeout(() => {
                        item.classList.add('animated');
                    }, index * 150); // Stagger animation
                }
            });

            // Animate donation-container, program-detail, donor-list
            const sectionsToAnimate = document.querySelectorAll('.donation-container.animated:not(.animated), .program-detail.animated:not(.animated), .donor-list.animated:not(.animated)');
            sectionsToAnimate.forEach(section => {
                if (isInViewport(section, 100)) {
                    section.classList.add('animated');
                }
            });

            // Animate alerts
            const alerts = document.querySelectorAll('.alert.animated:not(.animated)');
            alerts.forEach(alert => {
                if (isInViewport(alert, 50)) {
                    alert.classList.add('animated');
                }
            });

            // Animate no data message if present
            const noDataMessage = document.querySelector('p.animated:not(.animated)');
            if (noDataMessage && isInViewport(noDataMessage, 100)) {
                noDataMessage.classList.add('animated');
            }

            // Animate footer columns
            const footerColumns = document.querySelectorAll('footer .footer-column.animated:not(.animated)');
            footerColumns.forEach((col, index) => {
                if (isInViewport(col, 80)) {
                    setTimeout(() => {
                        col.classList.add('animated');
                    }, index * 100);
                }
            });
        }

        // Run on page load and every time on scroll
        animateOnScroll(); // Initial check on load
        window.addEventListener('scroll', animateOnScroll);

        // Check for status messages on page load
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('status')) {
            const statusAlert = document.querySelector('.alert'); // Handle both success and danger alerts
            if (statusAlert) {
                // Ensure it animates on page load
                statusAlert.classList.add('animated');
            }
        }

        // --- Payment Method Selection Logic ---
        const paymentOptions = document.querySelectorAll('.payment-option');
        if (paymentOptions.length > 0) {
            paymentOptions.forEach(option => {
                option.addEventListener('click', function() {
                    // Remove 'selected' class from all options
                    paymentOptions.forEach(opt => opt.classList.remove('selected'));
                    // Add 'selected' class to the clicked option
                    this.classList.add('selected');
                    // Check the radio button inside the clicked option
                    this.querySelector('input[type="radio"]').checked = true;
                });
            });

            // Set initial selected state based on default checked radio button
            const initialChecked = document.querySelector('.payment-option input[type="radio"]:checked');
            if (initialChecked) {
                initialChecked.closest('.payment-option').classList.add('selected');
            }
        }

        // --- Auto-format currency input ---
        const amountInput = document.getElementById('amount');
        const amountHidden = document.getElementById('amount_hidden'); // Hidden field for raw number

        if (amountInput) {
            // Function to format number with dots for thousands
            function formatNumber(num) {
                // Pastikan input adalah angka, bahkan jika ada titik sebelumnya
                let cleanNum = String(num).replace(/\D/g, ''); // Hapus semua non-digit
                if (cleanNum === '') return ''; // Jika kosong setelah dibersihkan, kembalikan kosong

                return parseInt(cleanNum, 10).toLocaleString('id-ID'); // Gunakan toLocaleString untuk format ID
            }

            // Function to parse formatted number back to integer
            function parseNumber(str) {
                if (!str) return 0; // Handle empty string
                return parseInt(str.replace(/\./g, ''), 10);
            }

            // Initial format when page loads (if value is pre-filled)
            // Ini akan memastikan nilai awal seperti '10.000' tetap terformat
            amountInput.value = formatNumber(parseNumber(amountInput.value));
            if (amountHidden) {
                amountHidden.value = parseNumber(amountInput.value);
            }

            // On input, format the number
            amountInput.addEventListener('input', function(e) {
                let value = this.value;
                this.value = formatNumber(parseNumber(value)); // Format saat mengetik
                if (amountHidden) {
                    amountHidden.value = parseNumber(this.value); // Update hidden field
                }
            });

            // Ensure correct value is sent on form submission
            amountInput.closest('form').addEventListener('submit', function() {
                if (amountHidden && amountInput) {
                    amountHidden.value = parseNumber(amountInput.value);
                }
            });
        }
    });
</script>
</body>
</html>     