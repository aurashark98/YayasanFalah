<?php
// donate_coins.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php'; // Pastikan ini mengarah ke file db_config.php yang benar

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Definisikan limit donasi harian
$DAILY_DONATION_LIMIT = 10000; // 10.000 koin per hari

// Ambil saldo koin user
$stmt_coins = $conn->prepare("SELECT koin, username, email FROM users WHERE id = ?");
$stmt_coins->bind_param("i", $user_id);
$stmt_coins->execute();
$result_coins = $stmt_coins->get_result();
$user_current_coins = 0;
$user_username = '';
$user_email = '';
if ($result_coins->num_rows > 0) {
    $user_data = $result_coins->fetch_assoc();
    $user_current_coins = $user_data['koin'];
    $user_username = $user_data['username'];
    $user_email = $user_data['email'];
} else {
    $_SESSION['donate_message'] = "Pengguna tidak ditemukan.";
    $_SESSION['donate_message_type'] = "error";
    header('Location: profile.php');
    exit();
}
$stmt_coins->close();

// --- Pengecekan Sponsor Aktif ---
$has_active_sponsor = false;
$sql_check_sponsor = "SELECT COUNT(*) AS active_sponsors FROM sponsors WHERE is_active = TRUE";
$result_check_sponsor = $conn->query($sql_check_sponsor);

if ($result_check_sponsor && $result_check_sponsor->num_rows > 0) {
    $row_sponsor = $result_check_sponsor->fetch_assoc();
    if ($row_sponsor['active_sponsors'] > 0) {
        $has_active_sponsor = true;
    }
}
// --- Akhir Pengecekan Sponsor Aktif ---

// --- Inisialisasi dan Pengecekan Limit Donasi Harian (Dipindahkan ke luar blok POST) ---
$donated_today = 0;
$sql_donated_today = "SELECT SUM(jumlah_koin) AS total_donated_today
                      FROM transaksi_koin
                      WHERE user_id = ?
                      AND tipe_transaksi = 'donasi_koin'
                      AND DATE(tanggal_transaksi) = CURDATE()"; // CURDATE() untuk hari ini
$stmt_donated_today = $conn->prepare($sql_donated_today);
$stmt_donated_today->bind_param("i", $user_id);
$stmt_donated_today->execute();
$result_donated_today = $stmt_donated_today->get_result();
if ($result_donated_today && $result_donated_today->num_rows > 0) {
    $row_donated_today = $result_donated_today->fetch_assoc();
    // jumlah_koin untuk 'donasi_koin' adalah negatif, jadi kita ambil nilai absolutnya
    $donated_today = abs((int)$row_donated_today['total_donated_today']);
}
$stmt_donated_today->close();

$remaining_limit = $DAILY_DONATION_LIMIT - $donated_today;
// Pastikan remaining_limit tidak negatif jika limit sudah terlampaui
if ($remaining_limit < 0) {
    $remaining_limit = 0;
}
// --- Akhir Pengecekan Limit Donasi Harian ---


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['amount'])) {
    $amount_to_donate = (int)$_POST['amount'];

    if ($amount_to_donate <= 0) {
        $message = "Jumlah donasi harus lebih dari 0.";
        $message_type = "error";
    } elseif ($amount_to_donate > $user_current_coins) {
        $message = "Koin Anda tidak cukup. Saldo Anda: " . number_format($user_current_coins, 0, ',', '.') . " koin.";
        $message_type = "error";
    } elseif (!$has_active_sponsor) {
        $message = "Maaf, saat ini tidak ada sponsor aktif yang mendukung konversi koin menjadi donasi. Donasi koin tidak dapat dilakukan.";
        $message_type = "error";
    } elseif ($amount_to_donate > ($DAILY_DONATION_LIMIT - $donated_today)) { // Pengecekan limit harian yang lebih akurat untuk proses POST
        $message = "Anda hanya bisa mendonasikan maksimal " . number_format($DAILY_DONATION_LIMIT, 0, ',', '.') . " koin per hari. Anda sudah mendonasikan " . number_format($donated_today, 0, ',', '.') . " koin hari ini. Sisa limit: " . number_format($remaining_limit, 0, ',', '.') . " koin.";
        $message_type = "error";
    }
    else {
        // Lanjutkan proses donasi jika semua validasi lolos
        $conn->begin_transaction();

        try {
            // Kurangi koin dari saldo pengguna di tabel users
            $stmt_update = $conn->prepare("UPDATE users SET koin = koin - ? WHERE id = ?");
            $stmt_update->bind_param("ii", $amount_to_donate, $user_id);
            $stmt_update->execute();

            if ($stmt_update->affected_rows > 0) {
                // Catat donasi ke tabel `donasi`
                $pesan_donasi = "Donasi dari koin kuis yang didukung sponsor.";
                $status_pembayaran = "paid";
                $payment_method = "koin_kuis";
                $programm_id = NULL;

                $stmt_insert_donasi = $conn->prepare("
                    INSERT INTO donasi (programm_id, user_id, nama, email, jumlah, pesan, tanggal, status_pembayaran, payment_method)
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?)
                ");
                $stmt_insert_donasi->bind_param("iissssss", $programm_id, $user_id, $user_username, $user_email, $amount_to_donate, $pesan_donasi, $status_pembayaran, $payment_method);
                $stmt_insert_donasi->execute();

                if ($stmt_insert_donasi->affected_rows > 0) {
                    // Catat transaksi koin ke tabel `transaksi_koin`
                    $description_log = "Donasi sebesar " . $amount_to_donate . " koin melalui sponsor.";
                    $stmt_log_coin = $conn->prepare("INSERT INTO transaksi_koin (user_id, tipe_transaksi, jumlah_koin, deskripsi) VALUES (?, ?, ?, ?)");
                    $tipe_transaksi_deduct = 'donasi_koin';
                    $jumlah_koin_deduct = -$amount_to_donate;
                    $stmt_log_coin->bind_param("isis", $user_id, $tipe_transaksi_deduct, $jumlah_koin_deduct, $description_log);
                    $stmt_log_coin->execute();
                    $stmt_log_coin->close();

                    $conn->commit();
                    $_SESSION['user_coins'] = $user_current_coins - $amount_to_donate;
                    $_SESSION['donate_message'] = "Berhasil mendonasikan " . number_format($amount_to_donate, 0, ',', '.') . " koin (" . number_format($amount_to_donate, 0, ',', '.') . " Rupiah)! Terima kasih atas donasi Anda yang didukung sponsor.";
                    $_SESSION['donate_message_type'] = "success";
                    header('Location: profile.php?tab=donations');
                    exit();
                } else {
                    $conn->rollback();
                    $message = "Gagal mencatat donasi finansial. Silakan coba lagi.";
                    $message_type = "error";
                }
                $stmt_insert_donasi->close();
            } else {
                $conn->rollback();
                $message = "Gagal mengurangi koin di saldo pengguna. Silakan coba lagi.";
                $message_type = "error";
            }
            $stmt_update->close();

        } catch (Exception $e) {
            $conn->rollback();
            $message = "Terjadi kesalahan: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donasikan Koin - Rumah AYP</title>
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

            /* Warna spesifik untuk donate_coins (penambahan/pengurangan koin) */
            --color-coin-plus: #28a745; /* Hijau untuk penambahan */
            --color-coin-minus: #dc3545; /* Merah untuk pengurangan */
        }

        /* Gaya Dasar Global & Tipografi - Latar belakang body menggunakan gradien baru */
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
            align-items: center; /* Untuk memusatkan vertikal jika konten pendek */
            min-height: 100vh; /* Pastikan body mengisi seluruh tinggi viewport */
            box-sizing: border-box; /* Agar padding tidak menambah ukuran total */
        }
        h1, h2, h3, p, label, small {
            font-family: 'Montserrat', sans-serif; /* Memastikan semua teks menggunakan Montserrat */
        }
        h1 {
            color: white; /* Judul di header section selalu putih */
            font-weight: 800; /* Tebal */
            text-shadow: 2px 2px 6px rgba(0,0,0,0.25);
        }
        p {
            color: var(--color-text-secondary); /* Warna teks sekunder */
            font-weight: 400; /* Normal */
        }

        /* Container utama */
        .container {
            width: 100%;
            max-width: 600px; /* Lebar maksimum yang lebih cocok untuk form donasi */
            background-color: var(--color-bg-secondary); /* Latar belakang putih */
            border-radius: 20px; /* Radius sudut konsisten */
            box-shadow: var(--shadow-strong); /* Bayangan kuat */
            overflow: hidden; /* Pastikan konten tidak keluar */
            display: flex;
            flex-direction: column;
            border: 1px solid var(--color-border-subtle); /* Border tipis */
            animation: fadeInScale 0.6s ease-out forwards; /* Animasi muncul */
            margin-top: 20px; /* Sedikit margin dari atas */
            margin-bottom: 20px; /* Sedikit margin dari bawah */
        }

        /* Header Section */
        .header {
            background: linear-gradient(135deg, var(--color-gradient-start), var(--color-gradient-end)); /* Gradien utama */
            color: #ffffff;
            padding: 30px;
            text-align: center;
            box-shadow: var(--shadow-medium); /* Bayangan sedang */
            position: relative;
            overflow: hidden; /* Untuk pseudo-element */
        }
        .header::before { /* Overlay dekoratif (opsional, bisa dihapus jika tidak disukai) */
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
        .header h1 {
            font-size: 2.5em; /* Ukuran judul */
            margin: 0;
            position: relative;
            z-index: 1;
        }
        /* Tampilan Jumlah Koin */
        .coin-display {
            padding: 12px 20px;
            background-color: var(--color-bg-primary); /* Latar belakang soft gray */
            border-radius: 12px;
            display: inline-block;
            margin-top: 15px;
            font-size: 1.1em;
            font-weight: 600;
            color: var(--color-text-primary); /* Warna teks utama */
            border: 1px solid var(--color-border-subtle);
            box-shadow: var(--shadow-light);
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
        }
        .coin-display:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        /* Konten utama di dalam container */
        .content {
            padding: 25px;
            text-align: center;
        }

        /* Form Group */
        .form-group {
            margin-bottom: 20px;
            text-align: left; /* Teks label sejajar kiri */
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--color-text-primary);
            font-size: 1.05em;
        }
        .form-group input[type="number"] {
            width: 100%; /* Lebar penuh */
            padding: 12px 15px;
            border: 1px solid var(--color-border-subtle);
            border-radius: 8px; /* Sudut sedikit membulat */
            box-sizing: border-box;
            font-size: 1.1em;
            background-color: var(--color-bg-primary); /* Latar belakang input soft gray */
            color: var(--color-text-primary);
            transition: border-color 0.3s ease, box-shadow 0.3s ease, background-color 0.3s ease;
        }
        .form-group input[type="number"]:focus {
            border-color: var(--color-gradient-start); /* Border warna gradien saat fokus */
            box-shadow: 0 0 0 3px rgba(79, 195, 247, 0.2); /* Shadow fokus yang konsisten */
            outline: none;
            background-color: var(--color-bg-secondary); /* Latar belakang putih saat fokus */
        }

        .form-group small {
            display: block;
            margin-top: 8px;
            font-size: 0.9em;
            color: var(--color-text-secondary);
        }
        .form-group small a {
            color: var(--color-gradient-start);
            text-decoration: underline;
            font-weight: 600;
        }

        /* Tombol Submit */
        .form-group button[type="submit"] {
            background: linear-gradient(45deg, var(--color-gradient-start), var(--color-gradient-end)); /* Gradien utama */
            color: white;
            padding: 14px 30px;
            border: none;
            border-radius: 30px; /* Sudut sangat membulat */
            font-size: 1.1em;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-medium); /* Bayangan sedang */
            width: 100%; /* Tombol lebar penuh */
        }
        .form-group button[type="submit"]:hover {
            transform: translateY(-3px) scale(1.01);
            box-shadow: var(--shadow-strong); /* Bayangan lebih kuat saat hover */
        }
        .form-group button[type="submit"]:disabled {
            background: var(--color-border-subtle); /* Warna abu-abu */
            color: var(--color-text-secondary);
            cursor: not-allowed;
            box-shadow: none;
            transform: none;
            opacity: 0.7;
        }

        /* Pesan Notifikasi (Success, Error, Info) */
        .message {
            padding: 15px 25px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-weight: 600;
            animation: fadeInSlideUp 0.6s ease-out forwards;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.05em;
            justify-content: center;
            text-align: center;
        }
        .message.success {
            background-color: #D4EDDA; /* Light green */
            color: #155724; /* Dark green text */
            border: 1px solid #C3E6CB;
        }
        .message.error {
            background-color: #F8D7DA; /* Light red */
            color: #721C24; /* Dark red text */
            border: 1px solid #F5C6CB;
        }
        .message.info {
            background-color: #CFE2FF; /* Light blue */
            color: #055160; /* Dark blue text */
            border: 1px solid #B6D4FE;
        }

        /* Navigasi Bawah (Tombol-tombol) - Konsisten dengan btn-login/btn-register */
        .nav-links-bottom {
            margin-top: 30px;
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        .nav-links-bottom a {
            display: inline-block;
            /* Menggunakan gaya btn-login/btn-register dari index.php */
            background-color: transparent; /* Default transparan */
            color: var(--color-text-primary); /* Default teks gelap */
            padding: 10px 20px;
            border-radius: 18px; /* Sudut membulat */
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9em;
            transition: all 0.3s ease;
            border: 1px solid var(--color-border-subtle);
            box-shadow: var(--shadow-light); /* Shadow ringan */
        }
        .nav-links-bottom a:nth-child(2) { /* Contoh untuk "Main Kuis", bisa pakai gradien */
            background: linear-gradient(45deg, var(--color-gradient-start), var(--color-gradient-end));
            color: #FFFFFF;
            box-shadow: var(--shadow-medium);
            border: none;
        }
        .nav-links-bottom a:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
            background-color: rgba(79, 195, 247, 0.1); /* Hover background untuk tombol non-gradien */
            color: var(--color-gradient-start); /* Hover text color */
        }
        .nav-links-bottom a:nth-child(2):hover { /* Hover untuk tombol gradien */
            background: linear-gradient(45deg, var(--color-gradient-end), var(--color-gradient-start)); /* Invert gradien */
            box-shadow: var(--shadow-strong);
            color: #FFFFFF;
        }


        /* Animasi */
        @keyframes fadeInScale {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        @keyframes fadeInSlideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            body {
                padding: 20px;
            }
            .container {
                border-radius: 15px;
                margin-top: 15px;
                margin-bottom: 15px;
            }
            .header {
                padding: 25px;
            }
            .header h1 {
                font-size: 2em;
            }
            .coin-display {
                font-size: 1.1em;
                padding: 10px 20px;
            }
            .content {
                padding: 20px;
            }
            .form-group label {
                font-size: 1em;
            }
            .form-group input[type="number"] {
                padding: 10px 12px;
                font-size: 1em;
            }
            .form-group small {
                font-size: 0.85em;
            }
            .form-group button[type="submit"] {
                padding: 12px 25px;
                font-size: 1em;
            }
            .message {
                font-size: 0.95em;
                padding: 12px 18px;
            }
            .nav-links-bottom {
                gap: 10px;
            }
            .nav-links-bottom a {
                padding: 8px 15px;
                font-size: 0.85em;
            }
        }

        @media (max-width: 480px) {
            .container {
                border-radius: 10px;
            }
            .header h1 {
                font-size: 1.8em;
            }
            .coin-display {
                font-size: 0.9em;
            }
            .form-group label {
                font-size: 0.95em;
            }
            .form-group input[type="number"] {
                font-size: 0.95em;
            }
            .form-group button[type="submit"] {
                font-size: 0.95em;
            }
            .message {
                font-size: 0.85em;
            }
            .nav-links-bottom a {
                font-size: 0.8em;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Donasikan Koin Anda</h1>
            <div class="coin-display">
                Koin Anda: <?php echo number_format($user_current_coins, 0, ',', '.'); ?>
            </div>
        </div>

        <div class="content">
            <?php
            // Ambil flash message dari sesi (dari proses POST)
            if (isset($_SESSION['donate_message'])) {
                echo '<div class="message ' . htmlspecialchars($_SESSION['donate_message_type']) . '">';
                echo '<i class="';
                if ($_SESSION['donate_message_type'] == 'success') echo 'fas fa-check-circle';
                else if ($_SESSION['donate_message_type'] == 'error') echo 'fas fa-times-circle';
                else echo 'fas fa-info-circle';
                echo '"></i> ';
                echo htmlspecialchars($_SESSION['donate_message']);
                echo '</div>';
                unset($_SESSION['donate_message']);
                unset($_SESSION['donate_message_type']);
            }
            // Tampilkan pesan error jika tidak ada sponsor dari pengecekan awal
            else if (!$has_active_sponsor) {
                echo '<div class="message error">';
                echo '<i class="fas fa-exclamation-triangle"></i> ';
                echo 'Maaf, saat ini tidak ada sponsor aktif yang mendukung konversi koin menjadi donasi. Donasi koin tidak dapat dilakukan.';
                echo '</div>';
            }
            ?>

            <form action="donate_coins.php" method="POST">
                <div class="form-group">
                    <label for="amount">Jumlah Koin yang Ingin Didonasikan (1 Koin = 1 Rupiah):</label>
                    <input type="number" id="amount" name="amount" min="1" max="<?php echo $user_current_coins; ?>" required <?php echo (!$has_active_sponsor || $user_current_coins == 0 || $remaining_limit <= 0 ? 'disabled' : ''); ?>>
                    <?php if ($has_active_sponsor): ?>
                         <small>Sisa limit donasi hari ini: <?php echo number_format($remaining_limit, 0, ',', '.') . ' koin (dari total ' . number_format($DAILY_DONATION_LIMIT, 0, ',', '.') . ' koin).'; ?></small>
                    <?php else: ?>
                         <small>Silakan cek halaman <a href="about.php">Tentang Kami</a> untuk melihat daftar sponsor.</small>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <button type="submit" <?php echo (!$has_active_sponsor || $user_current_coins == 0 || $remaining_limit <= 0 ? 'disabled' : ''); ?>>Donasikan Sekarang</button>
                </div>
            </form>

            <div class="nav-links-bottom">
                <a href="profile.php" class="btn-secondary-link">Kembali ke Profil</a>
                <a href="quiz.php" class="btn-primary-gradient">Main Kuis</a>
                <a href="about.php" class="btn-secondary-link">Lihat Sponsor</a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Logika untuk menampilkan pesan flash (sudah ada di PHP, ini hanya memastikan visualnya)
            // Tidak ada JS khusus untuk animasi di sini karena sudah ditangani oleh CSS dan PHP echo

            // Mengatur button style untuk nav-links-bottom secara manual di JS
            // Ini untuk memberikan kelas CSS yang tepat agar style-nya sama dengan btn-login/btn-register
            const navLinksBottom = document.querySelector('.nav-links-bottom');
            if (navLinksBottom) {
                const links = navLinksBottom.querySelectorAll('a');
                links.forEach((link, index) => {
                    // Tombol kedua (Main Kuis) akan menggunakan gaya gradien
                    if (index === 1) { // Index 1 adalah tombol kedua
                        link.classList.add('btn-primary-gradient');
                    } else {
                        link.classList.add('btn-secondary-link');
                    }
                });
            }

            // Menambahkan kelas untuk style tombol gradien dan non-gradien
            // ini perlu ditambahkan di CSS
            // .btn-primary-gradient {
            //     background: linear-gradient(45deg, var(--color-gradient-start), var(--color-gradient-end));
            //     color: #FFFFFF;
            //     box-shadow: var(--shadow-medium);
            //     border: none;
            // }
            // .btn-primary-gradient:hover {
            //     background: linear-gradient(45deg, var(--color-gradient-end), var(--color-gradient-start));
            //     box-shadow: var(--shadow-strong);
            // }
            // .btn-secondary-link {
            //     background-color: transparent;
            //     color: var(--color-text-primary);
            //     border: 1px solid var(--color-border-subtle);
            //     box-shadow: var(--shadow-light);
            // }
            // .btn-secondary-link:hover {
            //     background-color: rgba(79, 195, 247, 0.1);
            //     color: var(--color-gradient-start);
            //     box-shadow: var(--shadow-medium);
            // }

            // Menangani input type number agar tidak bisa dimasukkan nilai negatif atau melebihi batas
            const amountInput = document.getElementById('amount');
            if (amountInput) {
                amountInput.addEventListener('input', function() {
                    let value = parseInt(this.value);
                    const max = parseInt(this.max);
                    const min = parseInt(this.min);

                    if (isNaN(value)) {
                        this.value = '';
                    } else if (value < min) {
                        this.value = min;
                    } else if (value > max) {
                        this.value = max;
                    }
                });
            }
        });
    </script>
</body>
</html>