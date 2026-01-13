<?php
session_start();
// Memuat file konfigurasi database
// Pastikan 'config.php' sudah benar dan berisi DB_HOST, DB_USER, DB_PASS, DB_NAME
require_once 'config.php';

// Buat koneksi ke database menggunakan konstanta dari config.php
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Cek koneksi
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

$login_message = ''; // Variabel untuk menyimpan pesan login (error/success)

// Jika form disubmit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username_or_email = $_POST['username_or_email']; // Gunakan satu input untuk username/email
    $password = $_POST['password'];

    // Query untuk cek user berdasarkan username atau email, dan ambil US_STATUS, koin, dll.
    $stmt = $conn->prepare("SELECT id, username, password, US_STATUS, koin FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username_or_email, $username_or_email); // Bind dua kali untuk OR
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        // Login berhasil
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['US_STATUS'] = $user['US_STATUS'];
        $_SESSION['user_coins'] = $user['koin']; // Simpan koin di sesi

        // Redirect berdasarkan US_STATUS
        if ($user['US_STATUS'] === 'ADMIN') {
            $_SESSION['success_message'] = "Selamat datang, Admin " . htmlspecialchars($user['username']) . "!";
            header("Location: admin_panel.php");
        } else {
            $_SESSION['success_message'] = "Selamat datang, " . htmlspecialchars($user['username']) . "!";
            header("Location: index.php"); // Atau welcome.php, sesuai flow aplikasi Anda
        }
        exit();
    } else {
        // Set pesan error menggunakan variabel sesi agar bisa ditampilkan di halaman jika redirect
        $_SESSION['error_message'] = "Username, email, atau password salah.";
        // Untuk menjaga flash message, kita tidak perlu mengisi $login_message di sini
        // Tapi kita akan me-redirect kembali ke halaman ini sendiri untuk menampilkan pesan
        header("Location: login.php");
        exit();
    }

    $stmt->close();
}

// Ambil pesan dari sesi jika ada (dari redirect sebelumnya)
if (isset($_SESSION['success_message'])) {
    $login_message = "<div class='message success-message'><i class='fas fa-check-circle'></i> " . htmlspecialchars($_SESSION['success_message']) . "</div>";
    unset($_SESSION['success_message']);
} elseif (isset($_SESSION['error_message'])) {
    $login_message = "<div class='message error-message'><i class='fas fa-times-circle'></i> " . htmlspecialchars($_SESSION['error_message']) . "</div>";
    unset($_SESSION['error_message']);
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Rumah AYP</title>
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
        }

        /* Gaya Dasar Global & Tipografi - Latar belakang body menggunakan gradien baru */
        body {
            font-family: 'Montserrat', sans-serif; /* Menggunakan Montserrat untuk seluruh teks */
            line-height: 1.7;
            margin: 0;
            padding: 2rem; /* Padding dari tepi viewport */
            background: linear-gradient(135deg, #E0F2F1, #E3F2FD, #EDE7F6); /* Gradien hijau soft, biru, ungu */
            color: var(--color-text-primary); /* Warna teks utama */
            -webkit-font-smoothing: antialiased; /* Anti-aliasing font untuk tampilan lebih halus */
            -moz-osx-font-smoothing: grayscale; /* Anti-aliasing font untuk Firefox */
            overflow-x: hidden; /* Mencegah overflow horizontal */
            display: flex; /* Menggunakan flexbox untuk memusatkan container */
            justify-content: center;
            align-items: center; /* Untuk memusatkan vertikal */
            min-height: 100vh; /* Pastikan body mengisi seluruh tinggi viewport */
            box-sizing: border-box; /* Agar padding tidak menambah ukuran total */
        }
        h1, h2, h3, p, label {
            font-family: 'Montserrat', sans-serif; /* Memastikan semua teks menggunakan Montserrat */
        }

        /* Login Container */
        .login-container {
            background: var(--color-bg-secondary); /* Latar belakang putih */
            padding: 2.5rem; /* Padding internal */
            border-radius: 15px; /* Sudut membulat */
            box-shadow: var(--shadow-strong); /* Bayangan kuat */
            width: 100%;
            max-width: 400px; /* Lebar maksimum */
            position: relative; /* Untuk pseudo-element */
            overflow: hidden; /* Pastikan pseudo-element tidak keluar */
            animation: fadeInScale 0.6s ease-out forwards; /* Animasi saat muncul */
            border: 1px solid var(--color-border-subtle); /* Border tipis */
        }
        .login-container::before { /* Dekorasi sudut atas-kiri */
            content: '';
            position: absolute;
            top: -30px;
            left: -30px;
            width: 80px;
            height: 80px;
            background: radial-gradient(circle at top left, var(--color-gradient-start), transparent 70%);
            border-radius: 50%;
            opacity: 0.2;
            z-index: 0;
        }
        .login-container::after { /* Dekorasi sudut bawah-kanan */
            content: '';
            position: absolute;
            bottom: -30px;
            right: -30px;
            width: 100px;
            height: 100px;
            background: radial-gradient(circle at bottom right, var(--color-gradient-end), transparent 70%);
            border-radius: 50%;
            opacity: 0.2;
            z-index: 0;
        }

        /* Login Header */
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
            position: relative; /* Agar di atas pseudo-elements */
            z-index: 1;
        }
        .login-header h2 {
            color: var(--color-text-primary); /* Warna teks utama */
            font-size: 2em; /* Ukuran judul */
            margin-bottom: 0.5rem;
            font-weight: 700;
        }
        .login-header p {
            color: var(--color-text-secondary); /* Warna teks sekunder */
            font-size: 0.95em;
        }

        /* Form Group */
        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
            z-index: 1;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--color-text-primary);
            font-weight: 600;
            font-size: 0.95em;
        }
        .form-group input {
            width: 100%;
            padding: 0.9rem 1rem; /* Padding lebih besar */
            border: 1px solid var(--color-border-subtle); /* Border tipis */
            border-radius: 8px;
            transition: border-color 0.3s ease, box-shadow 0.3s ease, background-color 0.3s ease;
            font-size: 1em; /* Ukuran font input */
            color: var(--color-text-primary);
            background-color: var(--color-bg-primary); /* Background input soft gray */
        }
        .form-group input:focus {
            border-color: var(--color-gradient-start); /* Warna border saat fokus */
            outline: none;
            box-shadow: 0 0 0 3px rgba(79, 195, 247, 0.2); /* Shadow fokus yang konsisten */
            background-color: var(--color-bg-secondary); /* Background putih saat fokus */
        }

        /* Tombol Login */
        .login-button {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(45deg, var(--color-gradient-start), var(--color-gradient-end)); /* Gradien utama */
            border: none;
            border-radius: 8px; /* Sudut membulat */
            color: white;
            font-weight: 700; /* Lebih tebal */
            font-size: 1.1em; /* Ukuran font tombol */
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-medium); /* Bayangan sedang */
            position: relative;
            overflow: hidden;
            z-index: 1;
        }
        .login-button:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-strong); /* Bayangan lebih kuat saat hover */
        }
        .login-button::after { /* Efek ripple pada tombol */
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
        .login-button:active::after {
            width: 200%;
            height: 200%;
            opacity: 1;
            transition: width 0s, height 0s, opacity 0.4s ease-out;
        }


        /* Login Footer */
        .login-footer {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.95em;
            position: relative;
            z-index: 1;
        }
        .login-footer p {
            color: var(--color-text-secondary); /* Warna teks sekunder */
            margin-bottom: 0;
        }
        .login-footer a {
            color: var(--color-gradient-start); /* Warna biru dari gradien */
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease, text-decoration 0.3s ease;
        }
        .login-footer a:hover {
            color: var(--color-gradient-end); /* Warna hijau dari gradien saat hover */
            text-decoration: underline;
        }

        /* Tombol Kembali (CSS baru) */
        .back-to-home-link {
            display: inline-block;
            margin-top: 15px; /* Jarak dari teks di atasnya */
            color: var(--color-text-secondary); /* Warna teks sekunder */
            text-decoration: none;
            font-weight: 600;
            padding: 8px 15px;
            border: 1px solid var(--color-border-subtle);
            border-radius: 20px;
            transition: all 0.3s ease;
            font-size: 0.9em;
        }
        .back-to-home-link:hover {
            background-color: var(--color-bg-primary); /* Warna background hover */
            color: var(--color-text-primary); /* Warna teks hover */
            box-shadow: var(--shadow-light);
            transform: translateY(-1px);
        }

        /* Styles for messages (error/success) */
        .message {
            padding: 15px 20px;
            margin-bottom: 1.5rem;
            border-radius: 8px;
            font-size: 0.95em;
            font-weight: 600;
            text-align: left;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: var(--shadow-light); /* Bayangan ringan pada pesan */
            animation: fadeInSlideUp 0.6s ease-out forwards; /* Animasi muncul */
            position: relative;
            z-index: 1;
        }
        .message i { /* Ikon Font Awesome di pesan */
            font-size: 1.2em;
            vertical-align: middle;
        }
        .error-message {
            background-color: #F8D7DA; /* Light red */
            color: #721C24; /* Dark red text */
            border: 1px solid #F5C6CB;
        }
        .error-message i { color: var(--color-accent-secondary); } /* Ikon oranye */

        .success-message {
            background-color: #D4EDDA; /* Light green */
            color: #155724; /* Dark green text */
            border: 1px solid #C3E6CB;
        }
        .success-message i { color: var(--color-gradient-end); } /* Ikon hijau */

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
        @media (max-width: 480px) {
            body {
                padding: 1rem;
            }
            .login-container {
                padding: 1.5rem;
                border-radius: 10px;
            }
            .login-header h2 {
                font-size: 1.6em;
            }
            .login-header p {
                font-size: 0.85em;
            }
            .form-group input {
                padding: 0.7rem;
                font-size: 0.9em;
            }
            .login-button {
                padding: 0.8rem;
                font-size: 1em;
            }
            .login-footer {
                font-size: 0.85em;
            }
            .message {
                padding: 10px 15px;
                font-size: 0.85em;
            }
            .message i {
                font-size: 1em;
            }
            .back-to-home-link {
                font-size: 0.8em;
                padding: 6px 12px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h2>Masuk ke Akun Anda</h2>
            <p>Silakan masukkan username atau email dan password Anda</p>
        </div>

        <?php
        // Tampilkan pesan login jika ada
        echo $login_message;
        ?>
        
        <form action="" method="POST">
            <div class="form-group">
                <label for="username_or_email">Username atau Email</label>
                <input type="text" id="username_or_email" name="username_or_email" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="login-button">Masuk</button>
        </form>
        
        <div class="login-footer">
            <p>Belum punya akun? <a href="register.php">Daftar Sekarang</a></p>
            <a href="index.php" class="back-to-home-link">Kembali ke Halaman Utama</a>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Efek Ripple pada Tombol Login
            const loginButton = document.querySelector('.login-button');
            if (loginButton) {
                loginButton.addEventListener('click', function(e) {
                    const x = e.clientX - e.target.getBoundingClientRect().left;
                    const y = e.clientY - e.target.getBoundingClientRect().top;
                    
                    this.style.setProperty('--x', x + 'px');
                    this.style.setProperty('--y', y + 'px');
                });
            }
        });
    </script>
</body>
</html>