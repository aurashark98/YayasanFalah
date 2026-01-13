<?php
// coin_history.php
session_start();

// Memastikan user sudah login, jika belum redirect ke halaman login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Memuat file konfigurasi database
include 'config.php';

// Mengambil data user dari session
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Pengguna';
$user_coins = $_SESSION['user_coins'] ?? 0;

$coin_transactions = [];

// Mengambil riwayat transaksi koin pengguna dari database
$stmt = $conn->prepare("
    SELECT id, tipe_transaksi, jumlah_koin, deskripsi, tanggal_transaksi
    FROM transaksi_koin
    WHERE user_id = ?
    ORDER BY tanggal_transaksi DESC
");
$stmt->bind_param("i", $user_id); // 'i' menandakan tipe data integer
$stmt->execute();
$result = $stmt->get_result();

// Memproses hasil query
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $coin_transactions[] = $row;
    }
}
$stmt->close(); // Menutup statement
$conn->close(); // Menutup koneksi database
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Koin - Rumah AYP</title>
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

            /* Warna spesifik untuk coin_history */
            --color-coin-plus: #28a745; /* Hijau untuk penambahan */
            --color-coin-minus: #dc3545; /* Merah untuk pengurangan */
            --color-header-overlay: rgba(255, 255, 255, 0.05); /* Overlay transparan untuk header */
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
        h1, h2, h3, p {
            font-family: 'Montserrat', sans-serif; /* Memastikan semua heading dan paragraf menggunakan Montserrat */
        }
        h1, h2, h3 {
            color: var(--color-text-primary); /* Warna judul utama */
            font-weight: 800; /* Tebal */
        }
        p {
            color: var(--color-text-secondary); /* Warna teks sekunder */
            font-weight: 400; /* Normal */
        }

        /* Container utama */
        .container {
            width: 100%;
            max-width: 800px; /* Lebar maksimum konsisten */
            background-color: var(--color-bg-secondary); /* Latar belakang putih */
            border-radius: 20px; /* Radius sudut lebih besar */
            box-shadow: var(--shadow-strong); /* Bayangan kuat */
            overflow: hidden; /* Pastikan konten tidak keluar */
            display: flex;
            flex-direction: column;
            border: 1px solid var(--color-border-subtle); /* Border tipis */
            animation: fadeInScale 0.6s ease-out forwards; /* Animasi muncul */
            margin-bottom: 40px; /* Margin bawah untuk spasi jika di-scroll */
            margin-top: 40px; /* Margin atas untuk spasi jika di-scroll */
        }

        /* Header Section */
        .header-section {
            background: linear-gradient(135deg, var(--color-gradient-start), var(--color-gradient-end)); /* Gradien utama */
            color: #ffffff;
            padding: 30px;
            text-align: center;
            box-shadow: var(--shadow-medium); /* Bayangan sedang */
            position: relative;
            overflow: hidden; /* Untuk pseudo-element */
        }
        .header-section::before { /* Overlay dekoratif */
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
        .header-section h1 {
            font-size: 2.5em;
            margin: 0;
            font-weight: 800;
            text-shadow: 2px 2px 6px rgba(0,0,0,0.25);
            color: white; /* Pastikan judul tetap putih */
            line-height: 1.2;
            position: relative;
            z-index: 1;
        }
        .header-section p {
            font-size: 1.1em;
            margin: 10px 0 0;
            opacity: 0.9;
            color: rgba(255, 255, 255, 0.9); /* Pastikan paragraf tetap putih */
            position: relative;
            z-index: 1;
        }

        /* Tampilan Jumlah Koin */
        .coin-display {
            padding: 15px 25px;
            background-color: var(--color-bg-secondary); /* Latar belakang putih */
            border-radius: 15px; /* Sudut membulat */
            display: inline-block; /* Agar menyesuaikan konten */
            margin-top: 20px;
            font-size: 1.2em;
            font-weight: 600;
            color: var(--color-text-primary); /* Warna teks utama */
            border: 1px solid var(--color-border-subtle); /* Border tipis */
            box-shadow: var(--shadow-light); /* Bayangan ringan */
            transition: all 0.3s ease;
        }
        .coin-display:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        /* Konten utama di dalam container */
        .content {
            padding: 25px;
        }

        /* Daftar Transaksi */
        .transaction-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .transaction-item {
            background-color: var(--color-bg-primary); /* Latar belakang abu-abu muda */
            border-radius: 12px; /* Sudut membulat */
            padding: 15px;
            margin-bottom: 12px;
            box-shadow: var(--shadow-light); /* Bayangan ringan */
            display: flex;
            flex-wrap: wrap; /* Agar responsif pada layar kecil */
            justify-content: space-between;
            align-items: center;
            border: 1px solid var(--color-border-subtle); /* Border tipis */
            transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
        }
        .transaction-item:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-medium);
            background-color: rgba(79, 195, 247, 0.05); /* Sedikit warna biru muda saat hover */
        }

        .transaction-info {
            flex-grow: 1;
            min-width: 200px; /* Lebar minimum agar tidak terlalu sempit */
        }
        .transaction-type {
            font-weight: 700;
            font-size: 1.1em;
            color: var(--color-text-primary);
            margin-bottom: 5px;
        }
        .transaction-description, .transaction-date {
            font-size: 0.9em;
            color: var(--color-text-secondary);
            margin-bottom: 3px;
        }
        .transaction-amount {
            font-weight: 700;
            font-size: 1.1em;
            white-space: nowrap; /* Jangan memisahkan angka koin */
            margin-left: 20px;
            text-align: right;
        }
        .amount-plus {
            color: var(--color-coin-plus); /* Hijau untuk penambahan */
        }
        .amount-minus {
            color: var(--color-coin-minus); /* Merah untuk pengurangan */
        }

        /* Pesan Tidak Ada Transaksi */
        .no-transactions {
            text-align: center;
            color: var(--color-text-secondary);
            padding: 20px;
            font-style: italic;
        }

        /* Navigasi Bawah (Tombol-tombol) */
        .nav-links-bottom {
            margin-top: 30px;
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap; /* Agar tombol responsif */
        }
        .nav-links-bottom a {
            display: inline-block;
            background: linear-gradient(45deg, var(--color-gradient-start), var(--color-gradient-end)); /* Gradien utama */
            color: #ffffff;
            padding: 12px 25px;
            border-radius: 30px; /* Sudut sangat membulat */
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95em;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-light); /* Bayangan ringan */
            border: none;
        }
        .nav-links-bottom a:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium); /* Bayangan lebih jelas saat hover */
            opacity: 0.9;
        }

        /* Animasi */
        @keyframes fadeInScale {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            body {
                padding: 20px;
            }
            .container {
                border-radius: 15px;
                margin-top: 20px;
                margin-bottom: 20px;
            }
            .header-section {
                padding: 25px;
            }
            .header-section h1 {
                font-size: 2em;
            }
            .header-section p {
                font-size: 1em;
            }
            .coin-display {
                font-size: 1.1em;
                padding: 10px 20px;
            }
            .content {
                padding: 20px;
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
            .nav-links-bottom a {
                padding: 10px 20px;
                font-size: 0.9em;
            }
        }

        @media (max-width: 480px) {
            .container {
                border-radius: 10px;
            }
            .header-section h1 {
                font-size: 1.8em;
            }
            .header-section p {
                font-size: 0.9em;
            }
            .coin-display {
                font-size: 1em;
            }
            .transaction-type {
                font-size: 1em;
            }
            .transaction-description, .transaction-date {
                font-size: 0.85em;
            }
            .transaction-amount {
                font-size: 1em;
            }
            .nav-links-bottom {
                gap: 10px;
            }
            .nav-links-bottom a {
                font-size: 0.85em;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-section">
            <h1>Riwayat Koin Anda</h1>
            <p>Lihat semua transaksi koin Anda.</p>
            <div class="coin-display">
                Koin Anda Saat Ini: <?php echo htmlspecialchars($user_coins); ?>
            </div>
        </div>

        <div class="content">
            <?php if (!empty($coin_transactions)): ?>
                <ul class="transaction-list">
                    <?php foreach ($coin_transactions as $transaction): ?>
                        <li class="transaction-item">
                            <div class="transaction-info">
                                <div class="transaction-type">
                                    <?php
                                    if ($transaction['tipe_transaksi'] == 'penambahan_kuis') {
                                        echo 'Penambahan Koin (Kuis)';
                                    } elseif ($transaction['tipe_transaksi'] == 'donasi_koin') {
                                        echo 'Donasi Koin';
                                    } else {
                                        // Mengubah format string transaksi: underscore ke spasi dan huruf pertama kapital
                                        echo htmlspecialchars(ucfirst(str_replace('_', ' ', $transaction['tipe_transaksi'])));
                                    }
                                    ?>
                                </div>
                                <?php if (!empty($transaction['deskripsi'])): ?>
                                    <div class="transaction-description"><?php echo htmlspecialchars($transaction['deskripsi']); ?></div>
                                <?php endif; ?>
                                <div class="transaction-date"><?php echo date('d F Y, H:i:s', strtotime($transaction['tanggal_transaksi'])); ?></div>
                            </div>
                            <div class="transaction-amount <?php echo ($transaction['jumlah_koin'] > 0 ? 'amount-plus' : 'amount-minus'); ?>">
                                <?php echo ($transaction['jumlah_koin'] > 0 ? '+' : ''); ?><?php echo htmlspecialchars($transaction['jumlah_koin']); ?> Koin
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="no-transactions">Anda belum memiliki riwayat transaksi koin.</p>
            <?php endif; ?>

            <div class="nav-links-bottom">
                <a href="profile.php">Kembali ke Profil</a>
                <a href="quiz.php">Main Kuis</a>
                <a href="donate_coins.php">Donasikan Koin</a>
            </div>
        </div>
    </div>
</body>
</html>