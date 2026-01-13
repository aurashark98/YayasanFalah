<?php
// quiz.php
session_start(); // Mulai sesi

// Pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); // Arahkan ke halaman login jika belum login
    exit();
}

include 'config.php'; // Pastikan file koneksi database ada dan benar

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Pengguna'; // Ambil username dari sesi
$question = null;
$message = '';
$user_coins = $_SESSION['user_coins'] ?? 0; // Ambil koin dari sesi

// Ambil pertanyaan acak yang belum dijawab oleh user ini
// LEFT JOIN digunakan untuk memastikan kita hanya mengambil pertanyaan yang user_id ini belum ada di riwayat_kuis_pengguna
$stmt = $conn->prepare("
    SELECT
        pk.id,
        pk.teks_pertanyaan,
        pk.pilihan_a,
        pk.pilihan_b,
        pk.pilihan_c,
        pk.pilihan_d
    FROM
        pertanyaan_kuis pk
    LEFT JOIN
        riwayat_kuis_pengguna rkp ON pk.id = rkp.pertanyaan_id AND rkp.user_id = ?
    WHERE
        rkp.id IS NULL
    ORDER BY RAND()
    LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $question = $result->fetch_assoc();
} else {
    $message = "Anda telah menjawab semua pertanyaan kuis! Tunggu pertanyaan baru atau donasikan koin Anda.";
}
$stmt->close();

// Mengambil flash message dari session jika ada (dari process_quiz.php)
$quiz_message = '';
$quiz_message_type = '';
if (isset($_SESSION['quiz_message'])) {
    $quiz_message = $_SESSION['quiz_message'];
    $quiz_message_type = $_SESSION['quiz_message_type'];
    unset($_SESSION['quiz_message']);
    unset($_SESSION['quiz_message_type']);
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Main Kuis - Rumah AYP</title>
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

            /* Warna spesifik untuk quiz (benar/salah) */
            --quiz-correct: #28a745; /* Hijau untuk benar */
            --quiz-wrong: #dc3545; /* Merah untuk salah */
            --quiz-info: #17a2b8; /* Biru untuk info */
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
        h1, h2, h3, p, label, strong {
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

        /* Container utama kuis */
        .quiz-container {
            width: 100%;
            max-width: 700px; /* Lebar maksimum yang sesuai untuk kuis */
            background-color: var(--color-bg-secondary); /* Latar belakang putih */
            border-radius: 20px; /* Radius sudut konsisten */
            box-shadow: var(--shadow-strong); /* Bayangan kuat */
            overflow: hidden; /* Pastikan konten tidak keluar */
            border: 1px solid var(--color-border-subtle); /* Border tipis */
            animation: fadeInScale 0.6s ease-out forwards; /* Animasi muncul */
            margin-top: 20px; /* Sedikit margin dari atas */
            margin-bottom: 20px; /* Sedikit margin dari bawah */
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
        .header-section::before { /* Overlay dekoratif (opsional, bisa dihapus jika tidak disukai) */
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
            font-size: 2.5em; /* Ukuran judul */
            margin: 0;
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

        /* Konten utama kuis */
        .quiz-content {
            padding: 25px;
            text-align: center;
        }

        /* Kotak Pertanyaan */
        .quiz-question {
            background-color: var(--color-bg-primary); /* Latar belakang soft gray */
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 25px;
            box-shadow: var(--shadow-light);
            border: 1px solid var(--color-border-subtle);
            min-height: 120px; /* Menjaga ukuran minimal */
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        .quiz-question p {
            font-size: 1.3em; /* Ukuran font pertanyaan */
            font-weight: 600;
            color: var(--color-text-primary);
            margin: 0;
            line-height: 1.5;
        }

        /* Opsi Jawaban */
        .quiz-options button {
            display: block;
            width: calc(100% - 20px); /* Sesuaikan dengan padding */
            padding: 15px 20px;
            margin: 10px auto;
            border: none;
            border-radius: 12px;
            background: var(--color-bg-secondary); /* Latar belakang putih */
            color: var(--color-text-primary);
            font-size: 1.1em;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            box-shadow: var(--shadow-light);
            border: 1px solid var(--color-border-subtle);
        }
        .quiz-options button:hover {
            background: linear-gradient(45deg, var(--color-gradient-start), var(--color-gradient-end)); /* Gradien utama saat hover */
            color: white;
            transform: translateY(-3px) scale(1.01);
            box-shadow: var(--shadow-medium);
        }

        /* Pesan Notifikasi (Success, Error, Info) */
        .message {
            padding: 15px 25px;
            border-radius: 12px;
            margin-top: 0; /* Sesuaikan margin-top */
            margin-bottom: 20px; /* Sesuaikan margin-bottom */
            font-weight: 600;
            animation: fadeInSlideUp 0.6s ease-out forwards;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.05em;
            justify-content: center;
            text-align: center;
            box-shadow: var(--shadow-light);
        }
        .message.success {
            background-color: #D4EDDA; /* Light green */
            color: #155724; /* Dark green text */
            border: 1px solid #C3E6CB;
        }
        .message.success i { color: var(--color-gradient-end); } /* Ikon hijau */

        .message.error {
            background-color: #F8D7DA; /* Light red */
            color: #721C24; /* Dark red text */
            border: 1px solid #F5C6CB;
        }
        .message.error i { color: var(--color-accent-secondary); } /* Ikon oranye */

        .message.info {
            background-color: #CFE2FF; /* Light blue */
            color: #055160; /* Dark blue text */
            border: 1px solid #B6D4FE;
        }
        .message.info i { color: var(--color-gradient-start); } /* Ikon biru */


        /* Navigasi Bawah (Tombol-tombol) - Konsisten dengan halaman lain */
        .nav-links-bottom {
            margin-top: 30px;
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        .nav-links-bottom a {
            display: inline-block;
            background: linear-gradient(45deg, var(--color-gradient-start), var(--color-gradient-end)); /* Gradien utama */
            color: white;
            padding: 12px 25px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95em;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-medium);
            border: none;
        }
        .nav-links-bottom a:hover {
            background: linear-gradient(45deg, var(--color-gradient-end), var(--color-gradient-start)); /* Invert gradien saat hover */
            transform: translateY(-2px);
            box-shadow: var(--shadow-strong);
        }
        /* Penyesuaian khusus untuk beberapa tombol di bawah */
        .nav-links-bottom a:first-child { /* Contoh untuk "Kembali ke Profil" */
            background-color: transparent;
            color: var(--color-text-primary);
            border: 1px solid var(--color-border-subtle);
            box-shadow: var(--shadow-light);
        }
        .nav-links-bottom a:first-child:hover {
            background-color: rgba(79, 195, 247, 0.1);
            color: var(--color-gradient-start);
            box-shadow: var(--shadow-medium);
            transform: translateY(-2px);
        }


        /* Animations */
        @keyframes fadeInScale {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        @keyframes fadeInSlideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            body { padding: 20px; }
            .quiz-container {
                border-radius: 15px;
                margin-top: 15px;
                margin-bottom: 15px;
            }
            .header-section { padding: 25px; }
            .header-section h1 { font-size: 2em; }
            .header-section p { font-size: 1em; }
            .coin-display { font-size: 1.1em; padding: 10px 20px; }
            .quiz-content { padding: 20px; }
            .quiz-question { padding: 20px; min-height: 100px; }
            .quiz-question p { font-size: 1.2em; }
            .quiz-options button { padding: 12px 15px; font-size: 1em; }
            .message { font-size: 0.95em; padding: 12px 18px; }
            .nav-links-bottom { gap: 10px; }
            .nav-links-bottom a { padding: 10px 20px; font-size: 0.9em; }
        }

        @media (max-width: 480px) {
            .quiz-container { border-radius: 10px; }
            .header-section h1 { font-size: 1.8em; }
            .header-section p { font-size: 0.9em; }
            .coin-display { font-size: 0.9em; padding: 8px 15px; }
            .quiz-question p { font-size: 1em; }
            .quiz-options button { font-size: 0.9em; padding: 10px 12px; }
            .message { font-size: 0.85em; padding: 10px 15px; }
            .nav-links-bottom { gap: 8px; }
            .nav-links-bottom a { font-size: 0.8em; padding: 8px 15px; }
        }
    </style>
</head>
<body>
    <div class="quiz-container">
        <div class="header-section">
            <h1>Halo, <?php echo htmlspecialchars($username); ?>!</h1>
            <p>Uji pengetahuanmu dan kumpulkan koin untuk berdonasi!</p>
            <div class="coin-display">
                Koin Anda: <?php echo number_format($user_coins, 0, ',', '.'); ?>
            </div>
        </div>

        <div class="quiz-content">
            <?php if (!empty($quiz_message)): ?>
                <div class="message <?php echo $quiz_message_type; ?>">
                    <?php if ($quiz_message_type == 'success'): ?><i class="fas fa-check-circle"></i><?php elseif ($quiz_message_type == 'error'): ?><i class="fas fa-times-circle"></i><?php else: ?><i class="fas fa-info-circle"></i><?php endif; ?>
                    <?php echo $quiz_message; ?>
                </div>
            <?php endif; ?>

            <?php if ($question): ?>
                <div class="quiz-question">
                    <p><strong><?php echo htmlspecialchars($question['teks_pertanyaan']); ?></strong></p>
                </div>
                <form action="process_quiz.php" method="POST">
                    <input type="hidden" name="question_id" value="<?php echo $question['id']; ?>">
                    <div class="quiz-options">
                        <button type="submit" name="answer" value="A">A. <?php echo htmlspecialchars($question['pilihan_a']); ?></button>
                        <button type="submit" name="answer" value="B">B. <?php echo htmlspecialchars($question['pilihan_b']); ?></button>
                        <button type="submit" name="answer" value="C">C. <?php echo htmlspecialchars($question['pilihan_c']); ?></button>
                        <button type="submit" name="answer" value="D">D. <?php echo htmlspecialchars($question['pilihan_d']); ?></button>
                    </div>
                </form>
            <?php else: ?>
                <div class="message info">
                    <p><i class="fas fa-info-circle"></i> <?php echo $message; ?></p>
                </div>
            <?php endif; ?>

            <div class="nav-links-bottom">
                <a href="profile.php">Kembali ke Profil</a>
                <a href="donate_coins.php">Donasikan Koin</a>
                <a href="coin_history.php">Riwayat Koin</a>
            </div>
        </div>
    </div>
</body>
</html>