<?php
session_start(); // Pastikan session_start() dipanggil paling awal
require_once 'config.php'; // Pastikan file db_config.php ada dan berisi detail koneksi

// Cek jika user belum login
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Ambil data user, TERMASUK US_STATUS dan KOIN, SETIAP KALI HALAMAN DIMUAT
$stmt_user = $conn->prepare("SELECT id, username, email, gender, birth_date, profile_photo, US_STATUS, koin FROM users WHERE id = ?");
if ($stmt_user) {
    $stmt_user->bind_param("i", $user_id);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    $user = $result_user->fetch_assoc();
    $stmt_user->close();
} else {
    die("Kesalahan persiapan query user: " . $conn->error);
}


if(!$user) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// PERBARUI US_STATUS DAN KOIN DI SESI DENGAN DATA TERBARU DARI DATABASE
$_SESSION['US_STATUS'] = $user['US_STATUS'];
$_SESSION['user_coins'] = $user['koin']; // Simpan koin di sesi juga

// Mengambil flash message dari session jika ada
$success_message = '';
$error_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// --- TAB CONTROL LOGIC ---
$active_tab = $_GET['tab'] ?? 'profile'; // Default tab
$adopted_children = []; // Inisialisasi: PASTI array kosong jika tidak ada data

// Logika untuk tab "Adopsi Non-Finansial"
if ($active_tab == 'adoption_interactions') {
    // Ambil data anak yang diadopsi non-finansial oleh user ini
    $sql_adoptions = "SELECT
                        adn.id AS adopsi_id,
                        adn.tanggal_adopsi,
                        p.id AS anak_id,
                        p.nama AS anak_nama,
                        p.usia AS anak_usia,
                        p.asal AS anak_asal,
                        p.foto AS anak_foto
                      FROM adopsi_non_finansial adn
                      JOIN profile_ankytm p ON adn.profile_ankytm_id = p.id
                      WHERE adn.donatur_id = ? AND adn.status = 'aktif'";
    $stmt_adoptions = $conn->prepare($sql_adoptions);
    if ($stmt_adoptions) {
        $stmt_adoptions->bind_param("i", $user_id);
        $stmt_adoptions->execute();
        $result_adoptions = $stmt_adoptions->get_result();
        while ($row_adoption = $result_adoptions->fetch_assoc()) {
            $adopted_children[] = $row_adoption;
        }
        $stmt_adoptions->close();
    } else {
        // Jika query gagal, set pesan error
        $error_message = "Kesalahan persiapan query daftar adopsi: " . $conn->error;
    }
}


// --- LOGIKA EDIT/HAPUS DONASI (SUDAH ADA) ---
// Hanya proses jika tab yang aktif adalah 'donations' dan ada aksi donasi
if ($active_tab == 'donations') {
    $donation_id_to_process = $_GET['id_donasi'] ?? null;
    $action_donasi = $_GET['aksi_donasi'] ?? '';
    $current_donation_to_edit = null; // Data donasi yang sedang di-edit

    if ($donation_id_to_process && is_numeric($donation_id_to_process)) {
        // Ambil data donasi yang akan di-edit/dihapus
        // Perhatikan LEFT JOIN untuk programm, karena donasi koin tidak punya programm_id
        $stmt_fetch_donation = $conn->prepare("
            SELECT d.id, p.nama AS program_name, d.jumlah, d.tanggal, d.status_pembayaran, d.payment_method, d.pesan
            FROM donasi d
            LEFT JOIN programm p ON d.programm_id = p.id
            WHERE d.id = ? AND d.user_id = ?
        ");
        if ($stmt_fetch_donation) {
            $stmt_fetch_donation->bind_param("ii", $donation_id_to_process, $user_id);
            $stmt_fetch_donation->execute();
            $result_fetch_donation = $stmt_fetch_donation->get_result();
            $fetched_donation = $result_fetch_donation->fetch_assoc();
            $stmt_fetch_donation->close();

            if ($fetched_donation) {
                // Cek jika donasi sudah dibayar ATAU jenisnya koin_kuis, tidak boleh diedit/dihapus
                if ($fetched_donation['status_pembayaran'] == 'paid' || $fetched_donation['payment_method'] == 'koin_kuis') {
                    $_SESSION['error_message'] = "Donasi yang sudah berhasil dibayar atau berasal dari koin tidak dapat diubah atau dihapus.";
                    header("Location: profile.php?tab=donations"); // Redirect to donations tab
                    exit;
                }

                if ($action_donasi == 'edit') {
                    $current_donation_to_edit = $fetched_donation;
                    // Jika form edit donasi disubmit
                    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_edit_donasi'])) {
                        // Hapus titik dari nominal yang dikirim via POST sebelum validasi/simpan
                        $jumlah_mentah = str_replace('.', '', $_POST['jumlah'] ?? '0');
                        $jumlah_baru = (int)$jumlah_mentah;
                        $metode_bayar_baru = $_POST['payment_method'] ?? '';

                        if ($jumlah_baru <= 0) {
                            $_SESSION['error_message'] = "Jumlah donasi harus lebih besar dari 0.";
                        } elseif (!in_array($metode_bayar_baru, ['bank_transfer', 'e_wallet_qris'])) {
                            $_SESSION['error_message'] = "Metode pembayaran tidak valid.";
                        } else {
                            $stmt_update_donasi = $conn->prepare("UPDATE donasi SET jumlah = ?, payment_method = ? WHERE id = ? AND user_id = ?");
                            if ($stmt_update_donasi) {
                                $stmt_update_donasi->bind_param("isii", $jumlah_baru, $metode_bayar_baru, $donation_id_to_process, $user_id);

                                if ($stmt_update_donasi->execute()) {
                                    $_SESSION['success_message'] = "Donasi berhasil diperbarui.";
                                } else {
                                    $_SESSION['error_message'] = 'Gagal memperbarui donasi: ' . $conn->error;
                                }
                                $stmt_update_donasi->close();
                            } else {
                                $_SESSION['error_message'] = 'Kesalahan persiapan update donasi: ' . $conn->error;
                            }
                        }
                        header("Location: profile.php?tab=donations"); // Redirect untuk refresh data dan hapus parameter GET
                        exit;
                    }
                } elseif ($action_donasi == 'delete') {
                    $stmt_delete_donasi = $conn->prepare("DELETE FROM donasi WHERE id = ? AND user_id = ?");
                    if ($stmt_delete_donasi) {
                        $stmt_delete_donasi->bind_param("ii", $donation_id_to_process, $user_id);

                        if ($stmt_delete_donasi->execute()) {
                            $_SESSION['success_message'] = "Donasi berhasil dihapus.";
                        } else {
                            $_SESSION['error_message'] = 'Gagal menghapus donasi: ' . $conn->error;
                        }
                        $stmt_delete_donasi->close();
                    } else {
                        $_SESSION['error_message'] = 'Kesalahan persiapan hapus donasi: ' . $conn->error;
                    }
                    header("Location: profile.php?tab=donations"); // Redirect untuk refresh data
                    exit;
                }
            } else {
                $_SESSION['error_message'] = "Donasi tidak ditemukan atau Anda tidak memiliki akses.";
                header("Location: profile.php?tab=donations");
                exit;
            }
        }
    }
}


// --- LOGIKA UPDATE PROFIL PENGGUNA (YANG SUDAH ADA) ---
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_update_profile'])) { // Memastikan ini adalah submit form profil
    $email = $_POST['email'];
    $gender = $_POST['gender'];
    $birth_date = $_POST['birth_date'];

    // Cek duplikasi email, kecuali email user saat ini
    $stmt_check_email = $conn->prepare("SELECT * FROM users WHERE email = ? AND id != ?");
    if ($stmt_check_email) {
        $stmt_check_email->bind_param("si", $email, $user_id);
        $stmt_check_email->execute();
        $result_check_email = $stmt_check_email->get_result();

        if($result_check_email->num_rows > 0) {
            $error_message = 'Email sudah digunakan oleh pengguna lain!';
        } else {
            $profile_photo = $user['profile_photo']; // Ambil foto profil saat ini

            // Proses unggah foto profil baru
            if(isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0) {
                $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                $filename = $_FILES['profile_photo']['name'];
                $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                $max_file_size = 2 * 1024 * 1024; // 2 MB
                $upload_dir = 'uploads/'; // Konsisten menggunakan 'uploads/' untuk foto profil user
                if(!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                if(!in_array($file_ext, $allowed)) {
                    $error_message = "Format file tidak diizinkan. Hanya JPG, JPEG, PNG, GIF yang diizinkan.";
                } elseif ($_FILES['profile_photo']['size'] > $max_file_size) {
                    $error_message = "Ukuran file terlalu besar. Maksimal 2MB.";
                } else {
                    $new_filename = uniqid() . '.' . $file_ext;

                    if(move_uploaded_file($_FILES['profile_photo']['tmp_name'], $upload_dir . $new_filename)) {
                        // Hapus foto lama jika bukan 'default.jpg' (asumsi default.jpg juga di uploads/)
                        if($profile_photo != 'default.jpg' && file_exists($upload_dir . $profile_photo)) {
                            unlink($upload_dir . $profile_photo);
                        }
                        $profile_photo = $new_filename; // Update nama file untuk disimpan ke DB
                    } else {
                        $error_message = "Gagal mengunggah foto profil.";
                    }
                }
            } else if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == UPLOAD_ERR_NO_FILE) {
                // Tidak ada file baru diunggah, pertahankan nama file lama
            } else if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] != 0) {
                // Other upload errors
                 $error_message = "Terjadi kesalahan saat mengunggah file. Error code: " . $_FILES['profile_photo']['error'];
            }


            // Lakukan update hanya jika tidak ada error dari proses upload foto
            if (empty($error_message)) {
                $stmt_update_profile = $conn->prepare("UPDATE users SET email = ?, gender = ?, birth_date = ?, profile_photo = ? WHERE id = ?");
                if ($stmt_update_profile) {
                    $stmt_update_profile->bind_param("ssssi", $email, $gender, $birth_date, $profile_photo, $user_id);
                    if ($stmt_update_profile->execute()) {
                        // Ambil ulang data user untuk menampilkan informasi terbaru
                        $stmt_re_fetch_user = $conn->prepare("SELECT id, username, email, gender, birth_date, profile_photo, US_STATUS, koin FROM users WHERE id = ?"); // Ambil semua kolom penting, termasuk koin
                        if ($stmt_re_fetch_user) {
                            $stmt_re_fetch_user->bind_param("i", $user_id);
                            $stmt_re_fetch_user->execute();
                            $result_re_fetch_user = $stmt_re_fetch_user->get_result();
                            $user = $result_re_fetch_user->fetch_assoc(); // Update variabel $user
                            $stmt_re_fetch_user->close();

                            // Perbarui data di sesi juga
                            $_SESSION['email'] = $user['email'];
                            $_SESSION['US_STATUS'] = $user['US_STATUS'];
                            $_SESSION['profile_photo'] = $user['profile_photo']; // Pastikan foto di sesi juga update
                            $_SESSION['user_coins'] = $user['koin']; // Update koin di sesi

                            $success_message = 'Profil berhasil diperbarui!';
                        } else {
                            $error_message = 'Kesalahan saat mengambil ulang data user: ' . $conn->error;
                        }
                    } else {
                        $error_message = 'Gagal memperbarui profil: ' . $conn->error;
                    }
                    $stmt_update_profile->close();
                } else {
                    $error_message = 'Kesalahan persiapan update profil: ' . $conn->error;
                }
            }
        }
        $stmt_check_email->close();
    } else {
        $error_message = 'Kesalahan persiapan cek email: ' . $conn->error;
    }
}


// Ambil riwayat transaksi (donasi) pengguna
// Pastikan hanya diambil jika tab donations yang aktif
if ($active_tab == 'donations') {
    $transactions = [];
    $stmt_transactions = $conn->prepare("
        SELECT d.id, p.nama AS program_name, d.jumlah, d.tanggal, d.status_pembayaran, d.payment_method, d.pesan
        FROM donasi d
        LEFT JOIN programm p ON d.programm_id = p.id -- LEFT JOIN karena programm_id bisa NULL (untuk donasi koin)
        WHERE d.user_id = ?
        ORDER BY d.tanggal DESC
    ");
    if ($stmt_transactions) {
        $stmt_transactions->bind_param("i", $user_id);
        $stmt_transactions->execute();
        $transactions_result = $stmt_transactions->get_result();
        while ($row = $transactions_result->fetch_assoc()) {
            $transactions[] = $row;
        }
        $stmt_transactions->close();
    } else {
        $error_message = 'Kesalahan persiapan riwayat transaksi: ' . $conn->error;
    }
}


$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya - Rumah AYP</title>
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

            /* Warna spesifik untuk status dan tombol */
            --status-pending: #FFF3CD; /* Light yellow */
            --status-pending-text: #856404; /* Dark yellow text */
            --status-paid: #D4EDDA; /* Light green */
            --status-paid-text: #155724; /* Dark green text */
            --status-failed: #F8D7DA; /* Light red */
            --status-failed-text: #721C24; /* Dark red text */
        }

        /* Gaya Dasar Global & Tipografi */
        body {
            font-family: 'Montserrat', sans-serif; /* Menggunakan Montserrat untuk seluruh teks */
            line-height: 1.7;
            margin: 0;
            padding: 40px 20px; /* Padding untuk jarak dari tepi viewport */
            background: linear-gradient(135deg, #E0F2F1, #E3F2FD, #EDE7F6); /* Gradien hijau soft, biru, ungu */
            color: var(--color-text-primary); /* Warna teks utama */
            -webkit-font-smoothing: antialiased; /* Anti-aliasing font untuk tampilan lebih halus */
            -moz-osx-font-smoothing: grayscale; /* Anti-aliasing font untuk Firefox */
            overflow-x: hidden; /* Mencegah overflow horizontal */
            display: flex; /* Menggunakan flexbox untuk memusatkan container */
            justify-content: center;
            align-items: flex-start; /* Untuk memusatkan vertikal jika konten pendek */
            min-height: 100vh; /* Pastikan body mengisi seluruh tinggi viewport */
            box-sizing: border-box; /* Agar padding tidak menambah ukuran total */
        }
        h1, h2, h3, p, label, strong {
            font-family: 'Montserrat', sans-serif; /* Memastikan semua teks menggunakan Montserrat */
        }
        h1, h2, h3 {
            color: var(--color-text-primary);
            font-weight: 800;
        }
        p {
            color: var(--color-text-secondary);
            font-weight: 400;
        }

        /* Container utama */
        .profile-container {
            width: 100%;
            max-width: 1000px; /* Lebar maksimum untuk halaman profil */
            background-color: var(--color-bg-secondary); /* Latar belakang putih */
            border-radius: 20px; /* Radius sudut konsisten */
            box-shadow: var(--shadow-strong); /* Bayangan kuat */
            overflow: hidden; /* Pastikan konten tidak keluar */
            display: flex;
            flex-direction: column;
            border: 1px solid var(--color-border-subtle); /* Border tipis */
            animation: fadeInScale 0.6s ease-out forwards; /* Animasi muncul */
            margin-bottom: 40px; /* Margin bawah untuk spasi jika di-scroll */
            margin-top: 40px; /* Margin atas untuk spasi jika di-scroll */
        }

        /* Header Navigasi */
        .header-nav {
            background: linear-gradient(135deg, var(--color-gradient-start), var(--color-gradient-end)); /* Gradien utama */
            color: #ffffff;
            padding: 30px;
            text-align: center;
            box-shadow: var(--shadow-medium); /* Bayangan sedang */
            position: relative;
            overflow: hidden; /* Untuk pseudo-element */
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap; /* Untuk responsivitas tombol */
            gap: 20px;
        }
        .header-nav::before { /* Overlay dekoratif */
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
        .header-nav.animated { /* Animasi saat muncul */
            opacity: 1;
            transform: translateY(0);
            transition: opacity 0.8s ease-out, transform 0.8s ease-out;
        }

        .header-nav .header-title-group {
            flex-grow: 1;
            text-align: left;
            position: relative;
            z-index: 1;
        }
        .header-nav h2 {
            font-size: 2.8em;
            margin: 0;
            font-weight: 800;
            text-shadow: 2px 2px 6px rgba(0,0,0,0.25);
            color: white;
            line-height: 1.2;
        }
        .header-nav p {
            font-size: 1.1em;
            margin: 10px 0 0;
            opacity: 0.9;
            color: rgba(255, 255, 255, 0.9);
            text-align: left;
        }
        .nav-links {
            display: flex;
            gap: 15px; /* Jarak antar tombol */
            flex-shrink: 0; /* Agar tidak mengecil */
            position: relative;
            z-index: 1;
        }
        .nav-links a {
            background-color: rgba(255, 255, 255, 0.2); /* Latar belakang transparan */
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 25px; /* Sudut membulat */
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            box-shadow: var(--shadow-light); /* Bayangan ringan */
            border: 1px solid rgba(255, 255, 255, 0.3); /* Border transparan */
            backdrop-filter: blur(2px); /* Blur ringan */
            -webkit-backdrop-filter: blur(2px);
        }
        .nav-links a:hover {
            background-color: rgba(255, 255, 255, 0.4);
            transform: translateY(-3px) scale(1.02);
            box-shadow: var(--shadow-medium);
            border-color: rgba(255, 255, 255, 0.5);
        }
        /* Gaya spesifik untuk tombol Logout dan Admin Panel */
        .nav-links .home { /* Contoh: agar terlihat sedikit berbeda */
             background: linear-gradient(45deg, var(--color-gradient-start), var(--color-gradient-end));
             border: none;
             box-shadow: var(--shadow-medium);
        }
        .nav-links .home:hover {
             background: linear-gradient(45deg, var(--color-gradient-end), var(--color-gradient-start));
             box-shadow: var(--shadow-strong);
        }
        .nav-links .admin-panel {
            background: linear-gradient(45deg, #007bff, #0056b3); /* Biru */
            border: none;
        }
        .nav-links .admin-panel:hover {
            background: linear-gradient(45deg, #0056b3, #003d80);
        }
        .nav-links .logout {
            background: linear-gradient(45deg, #dc3545, #c82333); /* Merah */
            border: none;
        }
        .nav-links .logout:hover {
            background: linear-gradient(45deg, #c82333, #bd2130);
        }

        /* Pesan Notifikasi */
        .messages {
            padding: 20px 30px 0;
            z-index: 10;
        }
        .success, .error {
            padding: 15px 25px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-weight: 600;
            animation: fadeInSlideUp 0.6s ease-out forwards;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.05em;
            border: 1px solid;
            box-shadow: var(--shadow-light);
        }
        .success { background-color: #D4EDDA; color: #155724; border-color: #C3E6CB; }
        .success i { color: var(--color-gradient-end); } /* Ikon hijau */
        .error { background-color: #F8D7DA; color: #721C24; border-color: #F5C6CB; }
        .error i { color: var(--color-accent-secondary); } /* Ikon oranye */


        /* Area Konten Utama (Sidebar + Konten Tab) */
        .content-area {
            display: flex;
            gap: 25px;
            padding: 25px;
            position: relative;
            align-items: flex-start;
        }

        /* Sidebar Profil (Kiri) */
        .profile-sidebar {
            flex: 0 0 280px; /* Lebar tetap untuk sidebar */
            background-color: var(--color-bg-secondary); /* Latar belakang putih */
            padding: 25px;
            border-radius: 18px; /* Sudut membulat */
            text-align: center;
            box-shadow: var(--shadow-medium); /* Bayangan sedang */
            border: 1px solid var(--color-border-subtle); /* Border tipis */
            opacity: 0;
            transform: translateY(20px);
        }
        .profile-sidebar.animated { /* Animasi saat muncul */
            opacity: 1;
            transform: translateY(0);
            transition: opacity 0.6s ease-out, transform 0.6s ease-out;
        }

        .profile-photo-wrapper {
            position: relative;
            width: 150px;
            height: 150px;
            margin: 0 auto 20px;
            border-radius: 50%;
            overflow: hidden;
            background: linear-gradient(45deg, var(--color-gradient-start), var(--color-gradient-end)); /* Border gradien */
            padding: 5px; /* Ketebalan border gradien */
            box-shadow: 0 0 0 8px rgba(79, 195, 247, 0.1), var(--shadow-medium); /* Border luar halus + shadow */
            transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
        }
        .profile-photo-wrapper:hover {
            transform: scale(1.08) rotate(2deg);
            box-shadow: 0 0 0 10px rgba(79, 195, 247, 0.15), var(--shadow-strong);
        }
        .profile-photo {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
            border: 4px solid var(--color-bg-secondary); /* Border putih di dalam */
            display: block;
        }

        .profile-sidebar h3 {
            font-size: 2em;
            margin-bottom: 10px;
            color: var(--color-text-primary);
            font-weight: 700;
        }
        .profile-sidebar p {
            font-size: 1.05em;
            color: var(--color-text-secondary);
            margin-bottom: 20px;
        }
        /* Tampilan Koin di Sidebar */
        .profile-sidebar .coin-display {
            font-size: 1.2em;
            font-weight: 700;
            color: var(--color-text-primary);
            margin-top: 15px;
            margin-bottom: 15px;
            padding: 10px 15px;
            background-color: var(--color-bg-primary); /* Background soft gray */
            border-radius: 8px;
            border: 1px solid var(--color-border-subtle);
            display: inline-block;
            box-shadow: var(--shadow-light);
        }
        .profile-sidebar .coin-display span {
            color: var(--color-gradient-end); /* Warna hijau dari gradien */
        }
        .profile-sidebar .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 20px;
        }
        .profile-sidebar .action-buttons a {
            display: block; /* Agar setiap link menjadi baris baru */
            background: linear-gradient(45deg, var(--color-gradient-start), var(--color-gradient-end)); /* Gradien utama */
            color: white;
            padding: 12px 20px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-medium);
        }
        .profile-sidebar .action-buttons a:hover {
            background: linear-gradient(45deg, var(--color-gradient-end), var(--color-gradient-start)); /* Invert gradien */
            transform: translateY(-2px);
            box-shadow: var(--shadow-strong);
        }

        /* Konten Profil (Kanan) */
        .profile-content-wrapper {
            flex: 1; /* Mengisi sisa ruang */
            display: flex;
            flex-direction: column;
            gap: 25px; /* Jarak antar section konten */
        }

        .profile-content, .edit-donation-form-wrapper, .transaction-history, .adoption-history {
            background-color: var(--color-bg-secondary); /* Latar belakang putih */
            padding: 25px;
            border-radius: 18px;
            box-shadow: var(--shadow-medium);
            border: 1px solid var(--color-border-subtle);
            opacity: 0;
            transform: translateY(20px);
        }
        .profile-content.animated, .edit-donation-form-wrapper.animated, .transaction-history.animated, .adoption-history.animated {
            opacity: 1;
            transform: translateY(0);
            transition: opacity 0.6s ease-out, transform 0.6s ease-out;
        }

        .profile-content h3, .edit-donation-form-wrapper h3, .transaction-history h3, .adoption-history h3 {
            font-size: 2.2rem;
            margin-bottom: 25px;
            color: var(--color-text-primary);
            font-weight: 700;
            position: relative;
            padding-bottom: 10px;
            display: inline-block; /* Agar garis bawah sesuai teks */
        }
        .profile-content h3::after, .edit-donation-form-wrapper h3::after, .transaction-history h3::after, .adoption-history h3::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 80px;
            height: 3px;
            background: linear-gradient(to right, var(--color-gradient-start), var(--color-gradient-end));
            border-radius: 2px;
        }

        /* Form Group Umum */
        .form-group {
            margin-bottom: 18px;
            text-align: left; /* Label dan input sejajar kiri */
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: var(--color-text-primary);
            font-size: 1em;
        }
        input[type="text"],
        input[type="email"],
        input[type="date"],
        input[type="number"],
        select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--color-border-subtle);
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 1em;
            background-color: var(--color-bg-primary); /* Background input soft gray */
            color: var(--color-text-primary);
            transition: border-color 0.3s ease, box-shadow 0.3s ease, background-color 0.3s ease;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none; /* Hapus gaya default browser untuk select/number */
        }

        input[type="file"] {
            width: 100%;
            padding: 8px 0;
            border: none;
            background-color: transparent;
            font-size: 1em;
            color: var(--color-text-primary);
        }

        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="date"]:focus,
        input[type="number"]:focus,
        select:focus,
        input[type="file"]:focus {
            border-color: var(--color-gradient-start);
            box-shadow: 0 0 0 3px rgba(79, 195, 247, 0.2);
            outline: none;
            background-color: var(--color-bg-secondary);
        }
        input:disabled {
            background-color: var(--color-bg-primary);
            color: var(--color-text-secondary);
            cursor: not-allowed;
            opacity: 0.7;
        }

        small {
            display: block;
            color: var(--color-text-secondary);
            font-size: 0.85em;
            margin-top: 5px;
            line-height: 1.4;
        }

        button[type="submit"] {
            background: linear-gradient(45deg, var(--color-gradient-start), var(--color-gradient-end));
            color: white;
            padding: 14px 28px;
            border: none;
            border-radius: 30px;
            font-size: 1.1em;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            width: auto;
            display: inline-block;
            margin-top: 15px;
            box-shadow: var(--shadow-medium);
        }

        button[type="submit"]:hover {
            transform: translateY(-3px) scale(1.01);
            box-shadow: var(--shadow-strong);
        }
        .button-group { /* Untuk tombol Edit Donasi */
            display: flex;
            gap: 10px;
            justify-content: flex-end; /* Tombol ke kanan */
            margin-top: 20px;
        }
        .button-group button[type="submit"] {
            margin-top: 0; /* Override margin-top jika di dalam button-group */
            width: auto;
        }


        /* Riwayat Transaksi (Donasi) */
        .transaction-item {
            background-color: var(--color-bg-primary);
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 12px;
            box-shadow: var(--shadow-light);
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            border: 1px solid var(--color-border-subtle);
            transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
        }
        .transaction-item:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-medium);
            background-color: rgba(79, 195, 247, 0.05);
        }

        .transaction-info {
            flex-grow: 1;
            min-width: 200px;
        }
        .transaction-program-name {
            font-weight: 700;
            font-size: 1.1em;
            color: var(--color-text-primary);
            margin-bottom: 5px;
        }
        .transaction-date, .transaction-method, .transaction-pesan {
            font-size: 0.9em;
            color: var(--color-text-secondary);
            margin-bottom: 3px;
        }
        .transaction-amount {
            font-weight: 700;
            font-size: 1.1em;
            color: var(--color-gradient-end); /* Warna hijau untuk jumlah donasi */
            white-space: nowrap;
            margin-left: 20px;
        }
        .transaction-status {
            font-weight: 600;
            font-size: 0.9em;
            padding: 5px 10px;
            border-radius: 20px;
            margin-top: 8px;
            text-align: right;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .status-pending { background-color: var(--status-pending); color: var(--status-pending-text); }
        .status-paid { background-color: var(--status-paid); color: var(--status-paid-text); }
        .status-failed { background-color: var(--status-failed); color: var(--status-failed-text); }

        .transaction-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
            width: 100%;
            justify-content: flex-end;
        }
        .btn-action {
            padding: 8px 16px; /* Konsisten dengan btn-small */
            border-radius: 20px; /* Konsisten dengan btn-small */
            font-size: 0.9em;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            white-space: nowrap;
            display: inline-block;
            text-align: center;
            border: none;
            box-shadow: var(--shadow-light);
        }
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .btn-edit { background-color: var(--color-accent-secondary); color: white; } /* Oranye */
        .btn-edit:hover { background-color: #e09210; }
        .btn-delete { background-color: #dc3545; color: white; } /* Merah */
        .btn-delete:hover { background-color: #b52b39; }
        .btn-cancel { background-color: var(--color-text-secondary); color: white; } /* Abu-abu */
        .btn-cancel:hover { background-color: #61717c; }


        /* Gaya Tab Buttons */
        .tab-buttons {
            display: flex;
            justify-content: center;
            margin-bottom: 25px;
            border-bottom: 2px solid var(--color-border-subtle);
            padding-bottom: 10px;
            flex-wrap: wrap;
            gap: 10px; /* Jarak antar tombol tab */
        }
        .tab-button {
            background-color: transparent;
            border: none;
            padding: 12px 25px;
            font-family: 'Montserrat', sans-serif;
            font-size: 1.1em;
            font-weight: 600;
            color: var(--color-text-secondary);
            cursor: pointer;
            transition: color 0.3s ease, border-bottom-color 0.3s ease, background-color 0.3s ease;
            position: relative;
            bottom: -2px;
            flex-shrink: 0;
            border-radius: 8px 8px 0 0; /* Sudut atas membulat */
        }
        .tab-button.active {
            color: var(--color-gradient-start); /* Warna biru dari gradien */
            border-bottom: 2px solid var(--color-gradient-start);
            background-color: var(--color-bg-primary); /* Background soft gray */
            box-shadow: var(--shadow-light);
        }
        .tab-button:hover:not(.active) {
            color: var(--color-gradient-end); /* Warna hijau dari gradien saat hover */
            background-color: rgba(79, 195, 247, 0.05); /* Hover background biru transparan */
            border-bottom: 2px solid var(--color-gradient-end);
        }
        .tab-content-container {
            /* Konten tab */
        }

        /* Gaya untuk Adopsi Non-Finansial */
        .adoption-history {
            background-color: var(--color-bg-secondary);
            padding: 25px;
            border-radius: 18px;
            box-shadow: var(--shadow-medium);
            border: 1px solid var(--color-border-subtle);
            opacity: 0;
            transform: translateY(20px);
        }
        .adoption-history.animated {
            opacity: 1;
            transform: translateY(0);
            transition: opacity 0.6s ease-out, transform 0.6s ease-out;
        }
        .adoption-history h3 {
            font-size: 2.2rem;
            margin-bottom: 25px;
            color: var(--color-text-primary);
            font-weight: 700;
            position: relative;
            padding-bottom: 10px;
            display: inline-block;
        }
        .adoption-history h3::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 80px;
            height: 3px;
            background: linear-gradient(to right, var(--color-gradient-start), var(--color-gradient-end));
            border-radius: 2px;
        }
        .adopted-children-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .adopted-child-card {
            border: 1px solid var(--color-border-subtle);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            background-color: var(--color-bg-primary); /* Background soft gray */
            box-shadow: var(--shadow-light);
            transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
            cursor: pointer;
        }
        .adopted-child-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-medium);
            background-color: rgba(79, 195, 247, 0.05); /* Sedikit warna biru muda saat hover */
        }
        /* Style untuk ikon anak */
        .adopted-child-card .child-avatar-icon {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(45deg, var(--color-gradient-start), var(--color-gradient-end)); /* Gradien untuk avatar */
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            margin: 0 auto 10px;
            border: 3px solid var(--color-bg-secondary); /* Border putih di dalam gradien */
            color: white; /* Ikon putih */
            font-size: 60px; /* Ukuran ikon */
            box-shadow: var(--shadow-light);
        }
        .adopted-child-card .child-avatar-icon i {
            color: white; /* Pastikan ikon di dalam span juga putih */
        }
        .adopted-child-card h4 {
            color: var(--color-text-primary);
            margin: 5px 0;
            font-size: 1.2em;
            font-weight: 700;
        }
        .adopted-child-card p {
            font-size: 0.9em;
            color: var(--color-text-secondary);
            margin-bottom: 10px;
        }
        .adopted-child-card .btn-interact-card {
            background: linear-gradient(45deg, var(--color-gradient-start), var(--color-gradient-end));
            color: white;
            padding: 10px 20px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9em;
            transition: all 0.3s ease;
            display: inline-block;
            margin-top: 10px;
            box-shadow: var(--shadow-medium);
        }
        .adopted-child-card .btn-interact-card:hover {
            background: linear-gradient(45deg, var(--color-gradient-end), var(--color-gradient-start));
            transform: translateY(-2px);
            box-shadow: var(--shadow-strong);
        }

        /* Animasi */
        @keyframes fadeInScale { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
        @keyframes fadeInSlideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        /* Responsive */
        @media (max-width: 992px) {
            .profile-container {
                max-width: 700px;
            }
            .header-nav h2 {
                font-size: 2.2em;
            }
            .header-nav p {
                font-size: 1em;
            }
            .nav-links a {
                padding: 10px 18px;
                font-size: 0.9em;
            }
            .profile-sidebar {
                flex: 0 0 250px;
            }
            .profile-sidebar h3 {
                font-size: 1.8em;
            }
            .profile-sidebar p {
                font-size: 1em;
            }
            .profile-sidebar .coin-display {
                font-size: 1.1em;
            }
            .profile-content h3, .edit-donation-form-wrapper h3, .transaction-history h3, .adoption-history h3 {
                font-size: 2em;
            }
            .adopted-children-list {
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            }
        }

        @media (max-width: 768px) {
            body { padding: 20px; }
            .profile-container {
                flex-direction: column; /* Sidebar bertumpuk di mobile */
                max-width: 500px;
                margin-top: 15px;
                margin-bottom: 15px;
            }
            .header-nav {
                flex-direction: column;
                align-items: center;
                text-align: center;
                gap: 15px;
                padding: 25px;
            }
            .header-nav .header-title-group {
                text-align: center;
                width: 100%;
            }
            .header-nav h2 {
                font-size: 2em;
            }
            .header-nav p {
                font-size: 0.9em;
            }
            .nav-links {
                width: 100%;
                justify-content: center;
                flex-wrap: wrap;
                gap: 10px;
            }
            .nav-links a {
                padding: 8px 15px;
                font-size: 0.85em;
            }
            .content-area {
                flex-direction: column;
                padding: 20px;
                gap: 20px;
            }
            .profile-sidebar {
                flex: auto; /* Ambil lebar otomatis */
                width: 100%;
                padding: 20px;
            }
            .profile-content-wrapper {
                gap: 20px;
            }
            .profile-content, .edit-donation-form-wrapper, .transaction-history, .adoption-history {
                padding: 20px;
                border-radius: 15px;
            }
            .profile-content h3, .edit-donation-form-wrapper h3, .transaction-history h3, .adoption-history h3 {
                font-size: 1.8rem;
                text-align: center; /* Pusatkan judul */
            }
            .profile-content h3::after, .edit-donation-form-wrapper h3::after, .transaction-history h3::after, .adoption-history h3::after {
                left: 50%;
                transform: translateX(-50%);
            }
            .form-group label {
                font-size: 0.95em;
            }
            input[type="text"], input[type="email"], input[type="date"], input[type="number"], select {
                padding: 10px 12px;
                font-size: 0.95em;
            }
            button[type="submit"] {
                font-size: 1em;
                padding: 12px 25px;
                width: 100%; /* Tombol submit full width */
            }
            .button-group {
                flex-direction: column;
                align-items: center;
            }
            .button-group button[type="submit"] {
                width: 100%;
            }
            .btn-action {
                font-size: 0.8em;
                padding: 8px 12px;
            }
            .transaction-item {
                flex-direction: column;
                align-items: flex-start;
                padding: 12px;
            }
            .transaction-amount {
                margin-top: 10px;
                margin-left: 0;
                width: 100%;
                text-align: left;
            }
            .transaction-status {
                margin-left: 0;
                width: 100%;
                text-align: left;
            }
            .transaction-actions {
                justify-content: flex-start;
                margin-left: -5px; /* Adjust if needed */
            }
            .adopted-children-list {
                grid-template-columns: 1fr; /* Stack di mobile */
            }
        }

        @media (max-width: 480px) {
            body { padding: 10px; }
            .profile-container { border-radius: 10px; padding: 15px; }
            .header-nav h2 { font-size: 1.8em; }
            .header-nav p { font-size: 0.8em; }
            .nav-links a { font-size: 0.8em; padding: 6px 10px; }
            .profile-sidebar h3 { font-size: 1.6em; }
            .profile-sidebar p { font-size: 0.9em; }
            .profile-sidebar .coin-display { font-size: 1em; padding: 8px 12px; }
            .profile-content h3, .edit-donation-form-wrapper h3, .transaction-history h3, .adoption-history h3 { font-size: 1.6rem; }
            .form-group label, input[type="text"], input[type="email"], input[type="date"], input[type="number"], select { font-size: 0.9em; padding: 8px 10px; }
            button[type="submit"] { font-size: 1em; padding: 10px 20px; }
            .transaction-program-name { font-size: 1em; }
            .transaction-date, .transaction-method { font-size: 0.8em; }
            .transaction-amount { font-size: 1em; }
            .transaction-status { font-size: 0.7em; padding: 4px 8px; }
            .btn-action { font-size: 0.75em; padding: 6px 10px; }
            .adopted-child-card { padding: 15px; }
            .adopted-child-card .child-avatar-icon { width: 80px; height: 80px; font-size: 45px; }
            .adopted-child-card h4 { font-size: 1.1em; }
            .adopted-child-card p { font-size: 0.85em; }
            .adopted-child-card .btn-interact-card { font-size: 0.8em; padding: 8px 15px; }
        }
    </style>
</head>
<body>
    <div class="profile-container">
        <div class="header-nav animated">
            <div class="header-title-group">
                <h2>Profil Saya</h2>
                <p>Kelola informasi akun Anda dan riwayat donasi.</p>
            </div>
            <div class="nav-links">
                <a href="index.php" class="home">Beranda</a>
                <?php
                // Check if user status is ADMIN from the updated session variable
                if (isset($_SESSION['US_STATUS']) && $_SESSION['US_STATUS'] == 'ADMIN') {
                ?>
                    <a href="admin_panel.php" class="admin-panel">Admin Panel</a>
                <?php
                }
                ?>
                <a href="logout.php" class="logout">Logout</a>
            </div>
        </div>

        <div class="messages">
            <?php if($success_message): ?>
                <div class="success"><i class="fas fa-check-circle"></i> <?php echo $success_message; ?></div>
            <?php endif; ?>

            <?php if($error_message): ?>
                <div class="error"><i class="fas fa-times-circle"></i> <?php echo $error_message; ?></div>
            <?php endif; ?>
        </div>

        <div class="content-area">
            <div class="profile-sidebar animated">
                <div class="profile-photo-wrapper">
                    <img class="profile-photo" src="uploads/<?php echo htmlspecialchars($user['profile_photo'] ?? 'default.jpg'); ?>" alt="Foto Profil">
                </div>
                <h3><?php echo htmlspecialchars($user['username']); ?></h3>
                <p><?php echo htmlspecialchars($user['email']); ?></p>

                <div class="coin-display">
                    Koin Anda: <span><?php echo number_format($user['koin'], 0, ',', '.'); ?></span>
                </div>

                <div class="action-buttons">
                    <a href="quiz.php">Main Kuis</a>
                    <a href="donate_coins.php">Donasikan Koin</a>
                    <a href="coin_history.php">Riwayat Koin</a>
                </div>
            </div>

            <div class="profile-content-wrapper">
                <div class="tab-buttons">
                    <button class="tab-button <?php echo ($active_tab == 'profile' ? 'active' : ''); ?>" onclick="window.location.href='profile.php?tab=profile'">Profil Umum</button>
                    <button class="tab-button <?php echo ($active_tab == 'donations' ? 'active' : ''); ?>" onclick="window.location.href='profile.php?tab=donations'">Riwayat Donasi</button>
                    <button class="tab-button <?php echo ($active_tab == 'adoption_interactions' ? 'active' : ''); ?>" onclick="window.location.href='profile.php?tab=adoption_interactions'">Adopsi Non-Finansial</button>
                </div>

                <div class="tab-content-container">
                    <?php if ($active_tab == 'profile'): ?>
                        <div class="profile-content animated">
                            <h3>Edit Profil</h3>
                            <form method="POST" action="profile.php?tab=profile" enctype="multipart/form-data">
                                <input type="hidden" name="submit_update_profile" value="1">
                                <div class="form-group">
                                    <label for="username">Username:</label>
                                    <input type="text" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                                    <small>Username tidak dapat diubah.</small>
                                </div>

                                <div class="form-group">
                                    <label for="email">Email:</label>
                                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>

                                <div class="form-group">
                                    <label for="gender">Jenis Kelamin:</label>
                                    <select id="gender" name="gender" required>
                                        <option value="Laki-laki" <?php echo ($user['gender'] ?? '') == 'Laki-laki' ? 'selected' : ''; ?>>Laki-laki</option>
                                        <option value="Perempuan" <?php echo ($user['gender'] ?? '') == 'Perempuan' ? 'selected' : ''; ?>>Perempuan</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="birth_date">Tanggal Lahir:</label>
                                    <input type="date" id="birth_date" name="birth_date" value="<?php echo htmlspecialchars($user['birth_date'] ?? ''); ?>" required>
                                </div>

                                <div class="form-group">
                                    <label for="profile_photo">Foto Profil Baru:</label>
                                    <input type="file" id="profile_photo" name="profile_photo" accept="image/jpeg,image/png,image/gif">
                                    <small>Ukuran maksimal 2MB. Format: JPG, JPEG, PNG, GIF. Kosongkan jika tidak ingin mengubah.</small>
                                </div>

                                <button type="submit">Simpan Perubahan</button>
                            </form>
                        </div>
                    <?php elseif ($active_tab == 'donations'): ?>
                        <?php if ($current_donation_to_edit): ?>
                            <div class="edit-donation-form-wrapper animated">
                                <h3>Edit Donasi #<?php echo htmlspecialchars($current_donation_to_edit['id']); ?></h3>
                                <form method="POST" action="profile.php?tab=donations&aksi_donasi=edit&id_donasi=<?php echo htmlspecialchars($current_donation_to_edit['id']); ?>">
                                    <input type="hidden" name="submit_edit_donasi" value="1">
                                    <div class="form-group">
                                        <label for="program_name">Program Donasi:</label>
                                        <input type="text" id="program_name" value="<?php echo htmlspecialchars($current_donation_to_edit['program_name'] ?? 'Donasi Koin Kuis'); ?>" disabled>
                                    </div>

                                    <div class="form-group">
                                        <label for="jumlah">Jumlah Donasi (Rp):</label>
                                        <input type="text" id="jumlah_donasi_input" name="jumlah" value="<?php echo number_format($current_donation_to_edit['jumlah'], 0, ',', '.'); ?>" required> <small>Masukkan nominal dengan atau tanpa titik (misal: 10.000 atau 10000).</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="payment_method">Metode Pembayaran:</label>
                                        <select id="payment_method" name="payment_method" required>
                                            <option value="bank_transfer" <?php echo ($current_donation_to_edit['payment_method'] == 'bank_transfer' ? 'selected' : ''); ?>>Transfer Bank</option>
                                            <option value="e_wallet_qris" <?php echo ($current_donation_to_edit['payment_method'] == 'e_wallet_qris' ? 'selected' : ''); ?>>E-Wallet / QRIS</option>
                                            <option value="koin_kuis" <?php echo ($current_donation_to_edit['payment_method'] == 'koin_kuis' ? 'selected' : ''); ?> disabled>Koin Kuis</option>
                                        </select>
                                    </div>

                                    <div class="button-group">
                                        <button type="submit">Simpan Perubahan Donasi</button>
                                        <a href="profile.php?tab=donations" class="btn-action btn-cancel">Batal</a>
                                    </div>
                                </form>
                            </div>
                        <?php endif; ?>
                        <div class="transaction-history animated">
                            <h3>Riwayat Donasi Saya</h3>
                            <?php if (!empty($transactions)): ?>
                                <?php foreach ($transactions as $transaction): ?>
                                    <div class="transaction-item">
                                        <div class="transaction-info">
                                            <div class="transaction-program-name"><?php echo htmlspecialchars($transaction['program_name'] ?? 'Donasi Koin Kuis'); ?></div>
                                            <div class="transaction-date">Tanggal: <?php echo date('d F Y, H:i', strtotime($transaction['tanggal'])); ?></div>
                                            <div class="transaction-method">Metode: <?php echo htmlspecialchars($transaction['payment_method'] == 'bank_transfer' ? 'Transfer Bank' : ($transaction['payment_method'] == 'e_wallet_qris' ? 'E-Wallet / QRIS' : 'Koin Kuis')); ?></div>
                                            <?php if (!empty($transaction['pesan'])): ?>
                                                <small class="transaction-pesan">Pesan: "<?php echo htmlspecialchars($transaction['pesan']); ?>"</small>
                                            <?php endif; ?>
                                        </div>
                                        <div style="display: flex; flex-direction: column; align-items: flex-end; margin-left: auto;">
                                            <div class="transaction-amount">Rp <?php echo number_format($transaction['jumlah'], 0, ',', '.'); ?></div>
                                            <div class="transaction-status status-<?php echo htmlspecialchars($transaction['status_pembayaran']); ?>">
                                                <?php echo htmlspecialchars(ucfirst($transaction['status_pembayaran'])); ?>
                                            </div>
                                            <?php if ($transaction['status_pembayaran'] == 'pending'): ?>
                                                <small style="margin-top: 5px; color: var(--status-pending-text);">*Menunggu konfirmasi admin.</small>
                                            <?php elseif ($transaction['status_pembayaran'] == 'failed'): ?>
                                                <small style="margin-top: 5px; color: var(--status-failed-text);">*Donasi gagal/dibatalkan. Anda bisa mengedit/menghapus.</small>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($transaction['status_pembayaran'] != 'paid' && $transaction['payment_method'] != 'koin_kuis'): ?>
                                            <div class="transaction-actions">
                                                <a href="profile.php?tab=donations&aksi_donasi=edit&id_donasi=<?php echo htmlspecialchars($transaction['id']); ?>" class="btn-action btn-edit"><i class="fas fa-edit"></i> Edit</a>
                                                <a href="profile.php?tab=donations&aksi_donasi=delete&id_donasi=<?php echo htmlspecialchars($transaction['id']); ?>" class="btn-action btn-delete" onclick="return confirm('Apakah Anda yakin ingin menghapus donasi ini?')"><i class="fas fa-trash-alt"></i> Hapus</a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="animate-on-scroll" style="text-align: center; color: var(--color-text-secondary); padding: 20px;">Anda belum memiliki riwayat donasi.</p>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($active_tab == 'adoption_interactions'): ?>
                        <div class="adoption-history animated">
                                <h3>Anak Asuh yang Anda Adopsi Non-Finansial</h3>
                                <?php if (empty($adopted_children)): ?>
                                    <p class="animate-on-scroll" style="text-align: center; color: var(--color-text-secondary); padding: 20px;">Anda belum mengadopsi anak secara non-finansial. Kunjungi <a href="profilyatim.php" style="color: var(--color-gradient-start); font-weight: 600;">Profil Anak</a> untuk memulai.</p>
                                <?php else: ?>
                                    <div class="adopted-children-list">
                                        <?php foreach ($adopted_children as $child): ?>
                                            <div class="adopted-child-card animate-on-scroll" onclick="window.location.href='interaksi_anak.php?adopsi_id=<?php echo htmlspecialchars($child['adopsi_id']); ?>'">
                                                <div class="child-avatar-icon">
                                                    <?php if ($child['anak_foto'] && file_exists('uploads/' . $child['anak_foto'])): ?>
                                                        <img src="uploads/<?php echo htmlspecialchars($child['anak_foto']); ?>" alt="Foto <?php echo htmlspecialchars($child['anak_nama']); ?>">
                                                    <?php else: ?>
                                                        <i class="fas fa-user-circle"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <h4><?php echo htmlspecialchars($child['anak_nama']); ?></h4>
                                                <p>Usia: <?php echo htmlspecialchars($child['anak_usia']); ?> tahun</p>
                                                <p>Adopsi Sejak: <?php echo date('d M Y', strtotime($child['tanggal_adopsi'])); ?></p>
                                                <a href="interaksi_anak.php?adopsi_id=<?php echo htmlspecialchars($child['adopsi_id']); ?>" class="btn-interact-card">Lihat & Berinteraksi</a>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const jumlahInput = document.getElementById('jumlah_donasi_input');

            if (jumlahInput) {
                function formatRupiah(angka) {
                    var number_string = angka.replace(/[^,\d]/g, '').toString(),
                        split = number_string.split(','),
                        sisa = split[0].length % 3,
                        rupiah = split[0].substr(0, sisa),
                        ribuan = split[0].substr(sisa).match(/\d{3}/gi);

                    if (ribuan) {
                        separator = sisa ? '.' : '';
                        rupiah += separator + ribuan.join('.');
                    }

                    rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
                    return rupiah;
                }

                jumlahInput.addEventListener('keyup', function(e) {
                    // Hanya format jika bukan tombol navigasi atau delete
                    if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight' && e.key !== 'Backspace' && e.key !== 'Delete') {
                        this.value = formatRupiah(this.value);
                    }
                });

                // Hapus format rupiah sebelum submit form
                jumlahInput.form.addEventListener('submit', function() {
                    jumlahInput.value = jumlahInput.value.replace(/\./g, '');
                });
            }

            // --- Animation on Scroll Logic (Adapted for this page) ---
            function isInViewport(element, offset = 0) {
                if (!element) return false;
                const rect = element.getBoundingClientRect();
                const viewportHeight = (window.innerHeight || document.documentElement.clientHeight);
                return (
                    rect.top <= (viewportHeight - offset) &&
                    rect.bottom >= offset
                );
            }

            function animateOnScroll() {
                const headerNav = document.querySelector('.header-nav.animated'); // Already animated on load
                // We'll treat .animated as "already animated" and skip re-triggering for main sections

                const profileSidebar = document.querySelector('.profile-sidebar:not(.animated)');
                if (profileSidebar && isInViewport(profileSidebar, 100)) {
                    profileSidebar.classList.add('animated');
                }

                const contentSections = document.querySelectorAll('.profile-content:not(.animated), .edit-donation-form-wrapper:not(.animated), .transaction-history:not(.animated), .adoption-history:not(.animated)');
                contentSections.forEach((item, index) => {
                    if (isInViewport(item, 80)) {
                        setTimeout(() => {
                            item.classList.add('animated');
                        }, index * 100); // Stagger animation
                    }
                });

                // Specific staggered animation for adopted children cards (if tab is active)
                const adoptedChildCards = document.querySelectorAll('.adopted-child-card.animate-on-scroll:not(.animated)');
                if (adoptedChildCards.length > 0 && document.querySelector('.adoption-history.animated')) { // Only animate if parent section is visible
                    adoptedChildCards.forEach((card, index) => {
                        if (isInViewport(card, 50)) {
                            setTimeout(() => {
                                card.classList.add('animated');
                            }, index * 80 + 200); // Stagger with slight delay
                        }
                    });
                }


                // Specific animation for "no data" messages
                const noDonationMessage = document.querySelector('.transaction-history > p.animate-on-scroll:not(.animated)');
                if (noDonationMessage && isInViewport(noDonationMessage, 50)) {
                    noDonationMessage.classList.add('animated');
                }

                const noAdoptedMessage = document.querySelector('.adoption-history > p.animate-on-scroll:not(.animated)');
                if (noAdoptedMessage && isInViewport(noAdoptedMessage, 50)) {
                    noAdoptedMessage.classList.add('animated');
                }
            }

            // Initial animation trigger and event listener
            animateOnScroll();
            window.addEventListener('scroll', animateOnScroll);
        });
    </script>
</body>
</html>