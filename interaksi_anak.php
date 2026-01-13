<?php
session_start();
require_once 'config.php';

// Cek jika user belum login
if(!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = "Anda harus login untuk berinteraksi dengan anak asuh.";
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$adopsi_id = $_GET['adopsi_id'] ?? null;

// Inisialisasi variabel pesan di awal untuk mencegah "Undefined variable" warning
$success_message = '';
$error_message = '';

// Ambil flash message dari session jika ada
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

if (!$adopsi_id || !is_numeric($adopsi_id)) {
    $_SESSION['error_message'] = "ID Adopsi tidak valid.";
    header("Location: profile.php?tab=adoption_interactions"); // Redirect ke daftar adopsi
    exit;
}

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($mysqli->connect_error) {
    die("Koneksi gagal: " . $mysqli->connect_error);
}

$selected_adopsi = null;
$interactions_history = [];
$current_interaction_to_edit = null; // Untuk data interaksi yang sedang diedit

// Ambil detail anak asuh yang dipilih untuk interaksi (verifikasi kepemilikan adopsi)
$sql_single_adoption = "SELECT
                            adn.id AS adopsi_id,
                            adn.tanggal_adopsi,
                            p.id AS anak_id,
                            p.nama AS anak_nama,
                            p.usia AS anak_usia,
                            p.asal AS anak_asal,
                            p.foto AS anak_foto,
                            p.cerita AS anak_cerita
                        FROM adopsi_non_finansial adn
                        JOIN profile_ankytm p ON adn.profile_ankytm_id = p.id
                        WHERE adn.id = ? AND adn.donatur_id = ? AND adn.status = 'aktif'";
$stmt_single_adoption = $mysqli->prepare($sql_single_adoption);
if ($stmt_single_adoption) {
    $stmt_single_adoption->bind_param("ii", $adopsi_id, $user_id);
    $stmt_single_adoption->execute();
    $result_single_adoption = $stmt_single_adoption->get_result();
    $selected_adopsi = $result_single_adoption->fetch_assoc();
    $stmt_single_adoption->close();

    if (!$selected_adopsi) {
        $_SESSION['error_message'] = "Anak asuh tidak ditemukan atau Anda tidak memiliki akses ke adopsi ini.";
        header("Location: profile.php?tab=adoption_interactions");
        exit;
    }
} else {
    $_SESSION['error_message'] = "Kesalahan persiapan query detail adopsi: " . $mysqli->error;
    header("Location: profile.php?tab=adoption_interactions");
    exit;
}

// --- LOGIKA KIRIM/EDIT/HAPUS INTERAKSI ---
$action_interaction = $_GET['aksi_interaksi'] ?? '';
$interaction_id_to_process = $_GET['interaksi_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_interaction'])) {
    $tipe_interaksi = htmlspecialchars($_POST['tipe_interaksi']);
    $subjek = htmlspecialchars($_POST['subjek']);
    $konten_pesan = htmlspecialchars($_POST['konten_pesan']);

    if (isset($_POST['interaction_id_edit']) && is_numeric($_POST['interaction_id_edit'])) {
        // Update existing interaction
        $edit_id = intval($_POST['interaction_id_edit']);
        $stmt_update_interaction = $mysqli->prepare("UPDATE interaksi_adopsi SET tipe_interaksi = ?, subjek = ?, konten_pesan = ?, status = 'ditinjau_admin' WHERE id = ? AND adopsi_id = ?");
        if ($stmt_update_interaction) {
            $stmt_update_interaction->bind_param("sssii", $tipe_interaksi, $subjek, $konten_pesan, $edit_id, $adopsi_id);
            if ($stmt_update_interaction->execute()) {
                $_SESSION['success_message'] = "Interaksi berhasil diperbarui dan akan ditinjau ulang oleh admin.";
            } else {
                $_SESSION['error_message'] = "Gagal memperbarui interaksi: " . $mysqli->error;
            }
            $stmt_update_interaction->close();
        } else {
            $_SESSION['error_message'] = "Kesalahan persiapan update interaksi: " . $mysqli->error;
        }
    } else {
        // Insert new interaction
        $stmt_insert_interaction = $mysqli->prepare("INSERT INTO interaksi_adopsi (adopsi_id, tipe_interaksi, subjek, konten_pesan) VALUES (?, ?, ?, ?)");
        if ($stmt_insert_interaction) {
            $stmt_insert_interaction->bind_param("isss", $adopsi_id, $tipe_interaksi, $subjek, $konten_pesan);
            if ($stmt_insert_interaction->execute()) {
                $_SESSION['success_message'] = "Pesan/interaksi Anda telah berhasil dikirim ke admin untuk ditinjau. Terima kasih!";
            } else {
                $_SESSION['error_message'] = "Gagal mengirim interaksi: " . $mysqli->error;
            }
            $stmt_insert_interaction->close();
        } else {
            $_SESSION['error_message'] = "Kesalahan persiapan statement (interaksi): " . $mysqli->error;
        }
    }
    header("Location: interaksi_anak.php?adopsi_id=" . $adopsi_id);
    exit;
}

if ($action_interaction == 'edit' && $interaction_id_to_process) {
    $stmt_fetch_interaction = $mysqli->prepare("SELECT id, tipe_interaksi, subjek, konten_pesan FROM interaksi_adopsi WHERE id = ? AND adopsi_id = ?");
    if ($stmt_fetch_interaction) {
        $stmt_fetch_interaction->bind_param("ii", $interaction_id_to_process, $adopsi_id);
        $stmt_fetch_interaction->execute();
        $result_fetch_interaction = $stmt_fetch_interaction->get_result();
        $current_interaction_to_edit = $result_fetch_interaction->fetch_assoc();
        $stmt_fetch_interaction->close();

        if (!$current_interaction_to_edit) {
            $_SESSION['error_message'] = "Interaksi tidak ditemukan atau Anda tidak memiliki akses.";
            header("Location: interaksi_anak.php?adopsi_id=" . $adopsi_id);
            exit;
        }
    } else {
        $_SESSION['error_message'] = "Kesalahan persiapan ambil interaksi: " . $mysqli->error;
        header("Location: interaksi_anak.php?adopsi_id=" . $adopsi_id);
        exit;
    }
} elseif ($action_interaction == 'delete' && $interaction_id_to_process) {
    $stmt_delete_interaction = $mysqli->prepare("DELETE FROM interaksi_adopsi WHERE id = ? AND adopsi_id = ?");
    if ($stmt_delete_interaction) {
        $stmt_delete_interaction->bind_param("ii", $interaction_id_to_process, $adopsi_id);
        if ($stmt_delete_interaction->execute()) {
            $_SESSION['success_message'] = "Interaksi berhasil dihapus.";
        } else {
            $_SESSION['error_message'] = "Gagal menghapus interaksi: " . $mysqli->error;
        }
        $stmt_delete_interaction->close();
    } else {
        $_SESSION['error_message'] = "Kesalahan persiapan hapus interaksi: " . $mysqli->error;
    }
    header("Location: interaksi_anak.php?adopsi_id=" . $adopsi_id);
    exit;
}

// Ambil riwayat interaksi untuk anak ini (setelah semua POST/GET aksi diproses)
$sql_interactions_history = "SELECT
                                id,
                                tipe_interaksi,
                                subjek,
                                konten_pesan,
                                tanggal_interaksi,
                                status,
                                catatan_admin
                             FROM interaksi_adopsi
                             WHERE adopsi_id = ?
                             ORDER BY tanggal_interaksi DESC";
$stmt_interactions_history = $mysqli->prepare($sql_interactions_history);
if ($stmt_interactions_history) {
    $stmt_interactions_history->bind_param("i", $adopsi_id);
    $stmt_interactions_history->execute();
    $result_interactions_history = $stmt_interactions_history->get_result();
    while ($row_interaction = $result_interactions_history->fetch_assoc()) {
        $interactions_history[] = $row_interaction;
    }
    $stmt_interactions_history->close();
} else {
    $error_message = "Kesalahan mengambil riwayat interaksi: " . $mysqli->error;
}


$mysqli->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interaksi dengan <?php echo htmlspecialchars($selected_adopsi['anak_nama']); ?> - Rumah AYP</title>
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

            /* Warna spesifik untuk status interaksi */
            --status-ditinjau: #FFF3CD; /* Light yellow */
            --status-ditinjau-text: #856404; /* Dark yellow text */
            --status-diteruskan: #D4EDDA; /* Light green */
            --status-diteruskan-text: #155724; /* Dark green text */
            --status-disetujui: #D1ECF1; /* Light blue */
            --status-disetujui-text: #0C5460; /* Dark blue text */
            --status-ditolak: #F8D7DA; /* Light red */
            --status-ditolak-text: #721C24; /* Dark red text */

            /* Tambahan: Warna khusus untuk tombol hapus */
            --color-delete-button-bg: #DC3545; /* Merah standar */
            --color-delete-button-hover: #C82333; /* Merah lebih gelap untuk hover */
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
        .container {
            width: 100%;
            max-width: 900px; /* Lebar maksimum yang lebih besar untuk halaman interaksi */
            background-color: var(--color-bg-secondary); /* Latar belakang putih */
            border-radius: 20px; /* Radius sudut konsisten */
            box-shadow: var(--shadow-strong); /* Bayangan kuat */
            overflow: hidden; /* Pastikan konten tidak keluar */
            border: 1px solid var(--color-border-subtle); /* Border tipis */
            animation: fadeInScale 0.6s ease-out forwards; /* Animasi muncul */
            margin-top: 20px; /* Sedikit margin dari atas */
            margin-bottom: 20px; /* Sedikit margin dari bawah */
            padding: 30px; /* Padding di dalam container */
        }

        /* Header Section */
        .header-section {
            background: linear-gradient(135deg, var(--color-gradient-start), var(--color-gradient-end)); /* Gradien utama */
            color: #ffffff;
            padding: 30px;
            text-align: center;
            border-radius: 15px; /* Sudut membulat untuk header internal */
            margin-bottom: 30px;
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

        /* Pesan Notifikasi (Success, Error) */
        .messages {
            padding: 0 0 20px; /* Padding di sekitar blok pesan */
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
        }
        .success { background-color: #D4EDDA; color: #155724; border-color: #C3E6CB; }
        .success i { color: var(--color-gradient-end); } /* Ikon hijau */
        .error { background-color: #F8D7DA; color: #721C24; border-color: #F5C6CB; }
        .error i { color: var(--color-accent-secondary); } /* Ikon oranye */


        /* Tautan Kembali */
        .back-link {
            display: inline-block;
            margin-bottom: 25px;
            color: var(--color-gradient-start); /* Warna biru dari gradien */
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease, transform 0.2s ease;
        }
        .back-link:hover {
            color: var(--color-gradient-end); /* Warna hijau dari gradien saat hover */
            transform: translateX(-3px); /* Efek slide ringan saat hover */
        }

        /* Kartu Detail Anak */
        .child-detail-card {
            background-color: var(--color-bg-primary); /* Background soft gray */
            padding: 25px;
            border-radius: 18px; /* Radius sudut besar */
            box-shadow: var(--shadow-medium); /* Bayangan sedang */
            border: 1px solid var(--color-border-subtle); /* Border tipis */
            display: flex;
            flex-wrap: wrap; /* Agar responsif */
            gap: 25px; /* Jarak antar kolom */
            align-items: flex-start;
            margin-bottom: 30px;
        }
        .child-detail-card .image-area {
            flex: 0 0 200px; /* Lebar tetap untuk gambar */
            text-align: center;
        }
        .child-detail-card .image-area img {
            width: 180px; height: 180px;
            border-radius: 50%; /* Bentuk lingkaran */
            object-fit: cover;
            border: 5px solid var(--color-gradient-start); /* Border dengan warna gradien */
            box-shadow: var(--shadow-light); /* Bayangan ringan */
            transition: transform 0.3s ease;
        }
        .child-detail-card .image-area img:hover {
            transform: scale(1.03); /* Sedikit zoom saat hover */
        }
        .child-detail-card .info-area {
            flex: 1; /* Mengisi sisa ruang */
            min-width: 280px; /* Lebar minimum agar tidak terlalu sempit */
        }
        .child-detail-card .info-area h2 {
            font-size: 2em; margin-top: 0; color: var(--color-text-primary);
            position: relative; padding-bottom: 10px; margin-bottom: 15px;
        }
        .child-detail-card .info-area h2::after {
            content: ''; position: absolute; bottom: 0; left: 0;
            width: 60px; height: 3px;
            background: linear-gradient(to right, var(--color-gradient-start), var(--color-gradient-end)); /* Garis bawah gradien */
            border-radius: 2px;
        }
        .child-detail-card .info-area p { line-height: 1.6; margin-bottom: 10px; font-size: 1em; }
        .child-detail-card .info-area strong { color: var(--color-text-primary); font-weight: 700; }

        /* Bagian Formulir Interaksi & Riwayat Interaksi */
        .interaction-form-section, .interaction-history-section {
            background-color: var(--color-bg-secondary); /* Latar belakang putih */
            padding: 30px;
            border-radius: 18px;
            box-shadow: var(--shadow-medium);
            border: 1px solid var(--color-border-subtle);
            margin-bottom: 30px;
        }
        .interaction-form-section h3, .interaction-history-section h3 {
            font-size: 1.8rem; margin-bottom: 20px; color: var(--color-text-primary);
            position: relative; padding-bottom: 10px;
        }
        .interaction-form-section h3::after, .interaction-history-section h3::after {
            content: ''; position: absolute; bottom: 0; left: 0;
            width: 50px; height: 3px;
            background: linear-gradient(to right, var(--color-gradient-start), var(--color-gradient-end));
            border-radius: 2px;
        }
        .interaction-form-section p { margin-bottom: 15px; font-size: 1em; }
        .form-group { margin-bottom: 18px; text-align: left; }
        label { display: block; margin-bottom: 5px; font-weight: 600; color: var(--color-text-primary); font-size: 1em; }
        input[type="text"], select, textarea {
            width: 100%; padding: 12px 15px; border: 1px solid var(--color-border-subtle);
            border-radius: 8px; box-sizing: border-box; font-size: 1em;
            background-color: var(--color-bg-primary); color: var(--color-text-primary);
            transition: border-color 0.3s ease, box-shadow 0.3s ease, background-color 0.3s ease;
        }
        input[type="text"]:focus, select:focus, textarea:focus {
            border-color: var(--color-gradient-start); box-shadow: 0 0 0 3px rgba(79, 195, 247, 0.2); outline: none; background-color: var(--color-bg-secondary);
        }
        textarea { resize: vertical; min-height: 100px; }

        /* Tombol Form */
        button[type="submit"] {
            background: linear-gradient(45deg, var(--color-gradient-start), var(--color-gradient-end));
            color: white; padding: 14px 28px; border: none; border-radius: 30px;
            font-size: 1.1em; font-weight: 700; cursor: pointer;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            width: auto; display: inline-block; margin-top: 15px; box-shadow: var(--shadow-medium);
        }
        button[type="submit"]:hover {
            transform: translateY(-3px) scale(1.01);
            box-shadow: var(--shadow-strong);
        }
        .btn-small { /* Tombol Edit/Hapus */
            padding: 8px 16px;
            font-size: 0.9em;
            font-weight: 600;
            border-radius: 20px;
            text-decoration: none;
            transition: all 0.2s ease;
            box-shadow: var(--shadow-light);
            border: none;
            display: inline-block;
        }
        .btn-small.btn-edit { background-color: var(--color-accent-secondary); color: white; } /* Oranye */
        .btn-small.btn-edit:hover { background-color: #e09210; transform: translateY(-2px); box-shadow: var(--shadow-medium); }

        /* Gaya untuk tombol delete */
        .btn-small.btn-delete {
            background-color: var(--color-delete-button-bg); /* Latar merah dari variabel custom */
            color: white; /* Warna teks putih */
        }
        .btn-small.btn-delete .fas.fa-trash-alt {
            color: white; /* Ikon juga tetap putih agar kontras */
        }
        .btn-small.btn-delete:hover {
            background-color: var(--color-delete-button-hover); /* Latar lebih gelap saat hover */
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .btn-small.btn-cancel { background-color: var(--color-text-secondary); color: white; } /* Abu-abu */
        .btn-small.btn-cancel:hover { background-color: #61717c; transform: translateY(-2px); box-shadow: var(--shadow-medium); }


        /* Item Riwayat Interaksi */
        .interaction-item {
            background-color: var(--color-bg-primary); /* Latar belakang soft gray */
            border: 1px solid var(--color-border-subtle);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: var(--shadow-light);
            transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
        }
        .interaction-item:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-medium);
            background-color: rgba(79, 195, 247, 0.05); /* Sedikit warna biru muda saat hover */
        }
        .interaction-item p { margin: 5px 0; font-size: 0.95em; color: var(--color-text-secondary); }
        .interaction-item strong { color: var(--color-text-primary); font-weight: 600; }
        .interaction-item .status {
            font-weight: bold; display: inline-block; margin-left: 10px;
            padding: 4px 10px; border-radius: 15px; font-size: 0.8em;
            vertical-align: middle; /* Memusatkan vertikal dengan teks */
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        /* Warna status */
        .interaction-item .status.ditinjau_admin { background-color: var(--status-ditinjau); color: var(--status-ditinjau-text); }
        .interaction-item .status.diteruskan_ke_anak { background-color: var(--status-diteruskan); color: var(--status-diteruskan-text); }
        .interaction-item .status.disetujui_admin { background-color: var(--status-disetujui); color: var(--status-disetujui-text); }
        .interaction-item .status.ditolak_admin { background-color: var(--status-ditolak); color: var(--status-ditolak-text); }
        .interaction-item .admin-note {
            font-style: italic;
            color: var(--color-text-secondary);
            border-top: 1px dashed var(--color-border-subtle);
            padding-top: 10px;
            margin-top: 15px;
            font-size: 0.9em;
        }

        .interaction-item .action-buttons {
            display: flex;
            gap: 8px;
            margin-top: 15px;
            justify-content: flex-end; /* Tombol ke kanan */
        }

        /* Animasi */
        @keyframes fadeInScale { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
        @keyframes fadeInSlideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        /* Responsive */
        @media (max-width: 768px) {
            body { padding: 15px; }
            .container { padding: 20px; border-radius: 15px; }
            .header-section { padding: 25px; margin-bottom: 25px; }
            .header-section h1 { font-size: 2em; }
            .header-section p { font-size: 0.9em; }

            .child-detail-card {
                flex-direction: column; /* Stack di mobile */
                text-align: center;
                gap: 20px;
                padding: 20px;
            }
            .child-detail-card .image-area { flex: none; width: 100%; }
            .child-detail-card .image-area img {
                width: 150px; height: 150px; /* Ukuran gambar lebih kecil */
            }
            .child-detail-card .info-area { flex: none; width: 100%; text-align: center; }
            .child-detail-card .info-area h2 {
                font-size: 1.8em; text-align: center;
            }
            .child-detail-card .info-area h2::after { left: 50%; transform: translateX(-50%); }
            .child-detail-card .info-area p { font-size: 0.9em; }

            .interaction-form-section, .interaction-history-section {
                padding: 20px;
                margin-bottom: 20px;
            }
            .interaction-form-section h3, .interaction-history-section h3 {
                font-size: 1.6rem;
                text-align: center; /* Judul form/riwayat di tengah */
            }
            .interaction-form-section h3::after, .interaction-history-section h3::after {
                left: 50%; transform: translateX(-50%);
            }
            .form-group label { font-size: 0.95em; }
            input[type="text"], select, textarea { font-size: 0.95em; padding: 10px 12px; }
            button[type="submit"] { font-size: 1em; padding: 12px 25px; width: 100%; }

            .interaction-item { padding: 15px; }
            .interaction-item p { font-size: 0.9em; }
            .interaction-item .status { font-size: 0.75em; padding: 3px 8px; }
            .interaction-item .admin-note { font-size: 0.85em; }
            .interaction-item .action-buttons { justify-content: center; }
            .btn-small { padding: 6px 10px; font-size: 0.8em; }
        }

        @media (max-width: 480px) {
            .container { border-radius: 10px; padding: 15px; }
            .header-section h1 { font-size: 1.8em; }
            .header-section p { font-size: 0.8em; }
            .coin-display { font-size: 0.9em; padding: 8px 15px; }
            .child-detail-card { padding: 15px; }
            .child-detail-card .image-area img { width: 120px; height: 120px; border-width: 3px; }
            .child-detail-card .info-area h2 { font-size: 1.6em; }
            .child-detail-card .info-area p { font-size: 0.85em; }
            .interaction-form-section h3, .interaction-history-section h3 { font-size: 1.4rem; }
            input[type="text"], select, textarea { font-size: 0.9em; padding: 8px 10px; }
            button[type="submit"] { font-size: 0.9em; padding: 10px 20px; }
            .interaction-item p { font-size: 0.85em; }
            .btn-small { font-size: 0.75em; padding: 5px 10px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-section">
            <h1>Interaksi Anak Asuh</h1>
            <p>Berinteraksi secara emosional dengan anak asuh yang Anda dukung.</p>
        </div>

        <div class="messages">
            <?php if($success_message): ?>
                <div class="success"><i class="fas fa-check-circle"></i> <?php echo $success_message; ?></div>
            <?php endif; ?>
            <?php if($error_message): ?>
                <div class="error"><i class="fas fa-times-circle"></i> <?php echo $error_message; ?></div>
            <?php endif; ?>
        </div>

        <a href="profile.php?tab=adoption_interactions" class="back-link"><i class="fas fa-arrow-left"></i> Kembali ke Daftar Adopsi Saya</a>

        <div class="child-detail-card">
            <div class="image-area">
                <img src="<?php echo htmlspecialchars($selected_adopsi['anak_foto'] ? $selected_adopsi['anak_foto'] : 'no-photo.png'); ?>" alt="Foto <?php echo htmlspecialchars($selected_adopsi['anak_nama']); ?>">
            </div>
            <div class="info-area">
                <h2><?php echo htmlspecialchars($selected_adopsi['anak_nama']); ?></h2>
                <p><strong>Usia:</strong> <?php echo htmlspecialchars($selected_adopsi['anak_usia']); ?> tahun</p>
                <p><strong>Asal:</strong> <?php echo htmlspecialchars($selected_adopsi['anak_asal']); ?></p>
                <h3>Cerita <?php echo htmlspecialchars($selected_adopsi['anak_nama']); ?>:</h3>
                <p><?php echo nl2br(htmlspecialchars($selected_adopsi['anak_cerita'])); ?></p>
            </div>
        </div>

        <?php if ($action_interaction == 'edit' && $current_interaction_to_edit): ?>
            <div class="interaction-form-section">
                <h3>Edit Interaksi Anda</h3>
                <form action="interaksi_anak.php?adopsi_id=<?php echo htmlspecialchars($adopsi_id); ?>" method="POST">
                    <input type="hidden" name="submit_interaction" value="1">
                    <input type="hidden" name="interaction_id_edit" value="<?php echo htmlspecialchars($current_interaction_to_edit['id']); ?>">
                    <div class="form-group">
                        <label for="tipe_interaksi">Tipe Interaksi:</label>
                        <select id="tipe_interaksi" name="tipe_interaksi" required>
                            <option value="surat_pesan" <?php echo ($current_interaction_to_edit['tipe_interaksi'] == 'surat_pesan' ? 'selected' : ''); ?>>Surat / Pesan</option>
                            <option value="hadiah_ultah" <?php echo ($current_interaction_to_edit['tipe_interaksi'] == 'hadiah_ultah' ? 'selected' : ''); ?>>Informasi Hadiah (Ulang Tahun/Lainnya)</option>
                            <option value="motivasi_umum" <?php echo ($current_interaction_to_edit['tipe_interaksi'] == 'motivasi_umum' ? 'selected' : ''); ?>>Motivasi Umum</option>
                            <option value="lain_lain" <?php echo ($current_interaction_to_edit['tipe_interaksi'] == 'lain_lain' ? 'selected' : ''); ?>>Lain-lain</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="subjek">Subjek:</label>
                        <input type="text" id="subjek" name="subjek" value="<?php echo htmlspecialchars($current_interaction_to_edit['subjek']); ?>" required maxlength="255">
                    </div>

                    <div class="form-group">
                        <label for="konten_pesan">Isi Pesan / Keterangan:</label>
                        <textarea id="konten_pesan" name="konten_pesan" rows="8" required><?php echo htmlspecialchars($current_interaction_to_edit['konten_pesan']); ?></textarea>
                    </div>

                    <button type="submit">Simpan Perubahan Interaksi</button>
                    <a href="interaksi_anak.php?adopsi_id=<?php echo htmlspecialchars($adopsi_id); ?>" class="btn-small btn-cancel">Batal</a>
                </form>
            </div>
        <?php else: ?>
            <div class="interaction-form-section">
                <h3>Kirim Interaksi Baru</h3>
                <p>Gunakan formulir ini untuk mengirim surat, motivasi, atau informasi hadiah untuk anak asuh Anda. Pesan Anda akan ditinjau oleh admin yayasan sebelum diteruskan.</p>
                <form action="interaksi_anak.php?adopsi_id=<?php echo htmlspecialchars($adopsi_id); ?>" method="POST">
                    <input type="hidden" name="submit_interaction" value="1">
                    <div class="form-group">
                        <label for="tipe_interaksi">Tipe Interaksi:</label>
                        <select id="tipe_interaksi" name="tipe_interaksi" required>
                            <option value="surat_pesan">Surat / Pesan</option>
                            <option value="hadiah_ultah">Informasi Hadiah (Ulang Tahun/Lainnya)</option>
                            <option value="motivasi_umum">Motivasi Umum</option>
                            <option value="lain_lain">Lain-lain</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="subjek">Subjek:</label>
                        <input type="text" id="subjek" name="subjek" required maxlength="255">
                    </div>

                    <div class="form-group">
                        <label for="konten_pesan">Isi Pesan / Keterangan:</label>
                        <textarea id="konten_pesan" name="konten_pesan" rows="8" required></textarea>
                    </div>

                    <button type="submit">Kirim Interaksi</button>
                </form>
            </div>
        <?php endif; ?>

        <div class="interaction-history-section">
            <h3>Riwayat Interaksi Anda</h3>
            <?php if (empty($interactions_history)): ?>
                <p style="text-align: center; color: var(--color-text-secondary);">Anda belum memiliki riwayat interaksi dengan anak ini.</p>
            <?php else: ?>
                <?php foreach ($interactions_history as $interaction): ?>
                    <div class="interaction-item">
                        <p>
                            <strong>Tipe:</strong> <?php echo ucwords(str_replace('_', ' ', htmlspecialchars($interaction['tipe_interaksi']))); ?>
                            <span class="status <?php echo htmlspecialchars($interaction['status']); ?>"><?php echo ucwords(str_replace('_', ' ', htmlspecialchars($interaction['status']))); ?></span>
                        </p>
                        <p><strong>Subjek:</strong> <?php echo htmlspecialchars($interaction['subjek']); ?></p>
                        <p><strong>Tanggal:</strong> <?php echo date('d M Y H:i', strtotime($interaction['tanggal_interaksi'])); ?></p>
                        <p><strong>Pesan Anda:</strong><br><?php echo nl2br(htmlspecialchars($interaction['konten_pesan'])); ?></p>
                        <?php if (!empty($interaction['catatan_admin'])): ?>
                            <p class="admin-note"><strong>Catatan Admin:</strong> <?php echo nl2br(htmlspecialchars($interaction['catatan_admin'])); ?></p>
                        <?php endif; ?>
                        <div class="action-buttons">
                            <a href="interaksi_anak.php?adopsi_id=<?php echo htmlspecialchars($adopsi_id); ?>&aksi_interaksi=edit&interaksi_id=<?php echo htmlspecialchars($interaction['id']); ?>" class="btn-small btn-edit"><i class="fas fa-edit"></i> Edit</a>
                            <a href="interaksi_anak.php?adopsi_id=<?php echo htmlspecialchars($adopsi_id); ?>&aksi_interaksi=delete&interaksi_id=<?php echo htmlspecialchars($interaction['id']); ?>" class="btn-small btn-delete" onclick="return confirm('Apakah Anda yakin ingin menghapus interaksi ini?')"><i class="fas fa-trash-alt"></i> Hapus</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>