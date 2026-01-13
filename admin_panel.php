<?php
session_start();

// Menggunakan db_config.php yang sudah dibuat sebelumnya
include 'config.php'; // Pastikan file ini ada di lokasi yang benar

$tabel = $_GET['tabel'] ?? '';
$aksi = $_GET['aksi'] ?? ''; // Pastikan $aksi selalu terdefinisi
$id = $_GET['id'] ?? '';
$search_query = $_GET['search_query'] ?? '';

// --- LOGIKA PAGINASI BARU ---
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10; // Default 10 item per halaman
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;    // Default halaman 1
$offset = ($page - 1) * $limit; // Hitung offset untuk query SQL
$total_records = 0; // Inisialisasi total record
// --- AKHIR LOGIKA PAGINASI BARU ---


// Tambahkan tabel kuis dan koin, serta sponsors
$tabels = [
    'berita', 'doa', 'donasi', 'profile_ankytm', 'programm', 'users', 'pengeluaran',
    'adopsi_non_finansial', 'interaksi_adopsi',
    'pertanyaan_kuis', 'riwayat_kuis_pengguna', 'transaksi_koin', // Tabel kuis dan koin
    'sponsors' // Tabel sponsor baru
];

$searchable_columns = [
    'berita' => ['judul', 'deskripsi'],
    'doa' => ['nama_pengirim', 'isi_doa'],
    'donasi' => ['nama', 'email', 'pesan'],
    'profile_ankytm' => ['nama', 'asal', 'cerita'],
    'programm' => ['nama', 'deskripsi'],
    'users' => ['username', 'email'],
    'pengeluaran' => ['keterangan', 'tipe'],
    'adopsi_non_finansial' => [],
    'interaksi_adopsi' => ['subjek', 'konten_pesan', 'tipe_interaksi'],
    'pertanyaan_kuis' => ['teks_pertanyaan', 'pilihan_a', 'pilihan_b', 'pilihan_c', 'pilihan_d'],
    'riwayat_kuis_pengguna' => ['jawaban_pengguna'],
    'transaksi_koin' => ['deskripsi', 'tipe_transaksi'],
    'sponsors' => ['nama_sponsor', 'deskripsi']
];

function set_flash_message($message, $type = 'success') {
    $_SESSION['flash_message'] = ['message' => $message, 'type' => $type];
}

function get_flash_message() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

function renderForm($tabel, $data = [], $aksi_current = '')
{
    global $conn;

    // Tambahkan konfigurasi form untuk tabel baru
    $fields_config = [
        'berita' => ['foto', 'judul', 'deskripsi', 'tanggal', 'link'],
        'doa' => ['user_id', 'nama_pengirim', 'isi_doa', 'tanggal_doa'],
        'donasi' => ['programm_id', 'user_id', 'nama', 'email', 'jumlah', 'pesan', 'tanggal', 'status_pembayaran', 'payment_method'],
        'profile_ankytm' => ['nama', 'usia', 'asal', 'cerita', 'foto'],
        'programm' => ['gambar', 'nama', 'deskripsi', 'total_donasi'],
        'users' => ['username', 'email', 'gender', 'birth_date', 'profile_photo', 'password', 'US_STATUS', 'koin', 'created_at'],
        'pengeluaran' => ['jumlah', 'keterangan', 'tanggal', 'tipe', 'profile_ankytm_id'],
        'adopsi_non_finansial' => ['donatur_id', 'profile_ankytm_id', 'tanggal_adopsi', 'status'],
        'interaksi_adopsi' => ['adopsi_id', 'tipe_interaksi', 'subjek', 'konten_pesan', 'tanggal_interaksi', 'status', 'catatan_admin'],
        'pertanyaan_kuis' => ['teks_pertanyaan', 'pilihan_a', 'pilihan_b', 'pilihan_c', 'pilihan_d', 'jawaban_benar'],
        'riwayat_kuis_pengguna' => ['user_id', 'pertanyaan_id', 'jawaban_pengguna', 'is_correct'],
        'transaksi_koin' => ['user_id', 'tipe_transaksi', 'jumlah_koin', 'deskripsi'],
        'sponsors' => ['nama_sponsor', 'logo_url', 'website_url', 'deskripsi', 'is_active']
    ];

    $fields = $fields_config[$tabel];

    echo "<form method='POST' class='form-card' id='dataForm' enctype='multipart/form-data'>";
    echo "<h3 class='form-title'>" . (isset($data['id']) ? "Edit Data " : "Tambah Data ") . ucwords(str_replace('_', ' ', $tabel)) . "</h3>";

    foreach ($fields as $field) {
        $value = $data[$field] ?? '';
        $isDisabledForEdit = ($aksi_current === 'edit' && in_array($field, ['created_at']));
        // $isDisabledPassword tidak lagi digunakan untuk mengontrol atribut disabled,
        // sekarang dikontrol secara langsung di bawah untuk field password.

        echo "<div class='form-group'>";
        echo "<label for='{$field}' class='form-label'>" . ucwords(str_replace('_', ' ', $field)) . "</label>";

        // === START FIELD RENDERING LOGIC ===

        if ($field === 'status_pembayaran' && $tabel === 'donasi') {
            echo "<select class='form-control' id='{$field}' name='$field' required>";
            echo "<option value='pending'" . ($value == 'pending' ? ' selected' : '') . ">Pending</option>";
            echo "<option value='paid'" . ($value == 'paid' ? ' selected' : '') . ">Paid</option>";
            echo "<option value='failed'" . ($value == 'failed' ? ' selected' : '') . ">Failed</option>";
            echo "</select>";
        } elseif ($field === 'payment_method' && $tabel === 'donasi') {
            echo "<select class='form-control' id='{$field}' name='$field' required>";
            echo "<option value='bank_transfer'" . ($value == 'bank_transfer' ? ' selected' : '') . ">Transfer Bank</option>";
            echo "<option value='e_wallet_qris'" . ($value == 'e_wallet_qris' ? ' selected' : '') . ">E-Wallet / QRIS</option>";
            echo "<option value='koin_kuis'" . ($value == 'koin_kuis' ? ' selected' : '') . ">Koin Kuis</option>";
            echo "</select>";
        } elseif (in_array($field, ['deskripsi', 'isi_doa', 'cerita', 'pesan', 'keterangan', 'konten_pesan', 'catatan_admin', 'teks_pertanyaan'])) {
            echo "<textarea class='form-control' id='{$field}' name='$field' rows='4'>" . htmlspecialchars($value) . "</textarea>";
        } elseif ($field == 'tanggal' && $tabel === 'berita') {
            echo "<input type='date' class='form-control' id='{$field}' name='$field' value='" . htmlspecialchars($value) . "'>";
        } elseif (in_array($field, ['tanggal_doa', 'tanggal_pengeluaran', 'tanggal', 'created_at', 'tanggal_interaksi', 'tanggal_adopsi', 'tanggal_transaksi'])) {
            $formatted_value = ($value) ? date('Y-m-d\TH:i', strtotime($value)) : '';
            echo "<input type='datetime-local' class='form-control' id='{$field}' name='$field' value='" . htmlspecialchars($formatted_value) . "'" . ($isDisabledForEdit ? ' disabled' : '') . ">";
            if ($isDisabledForEdit) echo "<input type='hidden' name='$field' value='{$formatted_value}'>";
        } elseif ($field == 'birth_date') {
            echo "<input type='date' class='form-control' id='{$field}' name='$field' value='" . htmlspecialchars($value) . "'>";
        } elseif ($field == 'email') {
            echo "<input type='email' class='form-control' id='{$field}' name='$field' value='" . htmlspecialchars($value) . "'>";
        } elseif ($field == 'password') {
            // --- PERBAIKAN UNTUK PAMERAN: ADMIN TIDAK BISA UBAH PASSWORD USER ---
            if ($tabel === 'users' && $aksi_current === 'edit') {
                echo "<input type='password' class='form-control' id='{$field}' name='$field' placeholder='Pengubahan password dinonaktifkan sementara' disabled>";
                echo "<small style='color: #dc3545;'>* Fitur pengubahan password dinonaktifkan untuk pameran.</small>";
            } else {
                // Untuk CREATE atau tabel lain, tetap bisa input password
                echo "<input type='password' class='form-control' id='{$field}' name='$field' placeholder='Masukkan password'>";
                // Peringatan: Anda tidak menggunakan hashing password. Pertimbangkan untuk menggunakan password_hash().
            }
            // --- AKHIR PERBAIKAN PAMERAN ---
        } elseif (in_array($field, ['jumlah', 'jumlah_koin']) && ($tabel == 'donasi' || $tabel == 'pengeluaran' || $tabel == 'transaksi_koin')) {
            echo "<input type='text' class='form-control format-nominal' id='{$field}' name='$field' value='" . htmlspecialchars($value) . "' placeholder='Masukkan nominal (contoh: 100.000 atau -50)'>";
        } elseif ($field == 'total_donasi' && $tabel == 'programm') {
            echo "<input type='text' class='form-control format-nominal' id='{$field}' name='$field' value='" . htmlspecialchars($value) . "' placeholder='Total Donasi' disabled>";
        } elseif ($field == 'koin' && $tabel == 'users') {
            echo "<input type='number' class='form-control' id='{$field}' name='$field' value='" . htmlspecialchars($value) . "' required>";
        } elseif ($field == 'gender' && $tabel === 'users') { // Hapus $isDisabledForEdit untuk gender
            echo "<select class='form-control' id='{$field}' name='$field' required>";
            echo "<option value='Laki-laki'" . ($value == 'Laki-laki' ? ' selected' : '') . ">Laki-laki</option>";
            echo "<option value='Perempuan'" . ($value == 'Perempuan' ? ' selected' : '') . ">Perempuan</option>";
            echo "</select>";
        } elseif ($field == 'US_STATUS' && $tabel == 'users') {
            echo "<select class='form-control' id='{$field}' name='$field' required>";
            echo "<option value='USER'" . ($value == 'USER' ? ' selected' : '') . ">USER</option>";
            echo "<option value='ADMIN'" . ($value == 'ADMIN' ? ' selected' : '') . ">ADMIN</option>";
            echo "</select>";
        } elseif ($tabel === 'pengeluaran' && $field === 'tipe') {
            echo "<select class='form-control' id='{$field}' name='$field' required>";
            echo "<option value=''>Pilih Tipe Pengeluaran</option>";
            echo "<option value='Sumbangan Pendidikan'" . ($value == 'Sumbangan Pendidikan' ? ' selected' : '') . ">Sumbangan Pendidikan</option>";
            echo "<option value='Kebutuhan Sehari-hari'" . ($value == 'Kebutuhan Sehari-hari' ? ' selected' : '') . ">Kebutuhan Sehari-hari</option>";
            echo "<option value='Biaya Kesehatan'" . ($value == 'Biaya Kesehatan' ? ' selected' : '') . ">Biaya Kesehatan</option>";
            echo "<option value='Biaya Operasional Yayasan'" . ($value == 'Biaya Operasional Yayasan' ? ' selected' : '') . ">Biaya Operasional Yayasan</option>";
            echo "<option value='Lain-lain'" . ($value == 'Lain-lain' ? ' selected' : '') . ">Lain-lain</option>";
            echo "</select>";
        } elseif ($field === 'programm_id' && $tabel === 'donasi') {
            echo "<select class='form-control' id='{$field}' name='$field'>";
            echo "<option value=''>Pilih Program</option>";
            $program_res = $conn->query("SELECT id, nama FROM programm");
            if ($program_res && $program_res->num_rows > 0) {
                while ($program_row = $program_res->fetch_assoc()) {
                    echo "<option value='{$program_row['id']}'" . ($value == $program_row['id'] ? ' selected' : '') . ">" . htmlspecialchars($program_row['nama']) . "</option>";
                }
            }
            echo "</select>";
        } elseif (($field === 'user_id' && ($tabel === 'donasi' || $tabel === 'doa' || $tabel === 'adopsi_non_finansial' || $tabel === 'riwayat_kuis_pengguna' || $tabel === 'transaksi_koin'))) {
            echo "<select class='form-control' id='{$field}' name='$field' " . ($isDisabledForEdit ? 'disabled' : '') . ">";
            echo "<option value=''>Pilih User</option>";
            $user_res = $conn->query("SELECT id, username FROM users");
            if ($user_res && $user_res->num_rows > 0) {
                while ($user_row = $user_res->fetch_assoc()) {
                    echo "<option value='{$user_row['id']}'" . ($value == $user_row['id'] ? ' selected' : '') . ">" . htmlspecialchars($user_row['username']) . "</option>";
                }
            }
            echo "</select>";
            if ($isDisabledForEdit) echo "<input type='hidden' name='$field' value='{$value}'>";
        } elseif (($field === 'profile_ankytm_id' && ($tabel === 'pengeluaran' || $tabel === 'adopsi_non_finansial'))) {
            $isDisabled = ($aksi_current === 'edit' && $tabel === 'adopsi_non_finansial') ? 'disabled' : '';
            echo "<select class='form-control' id='{$field}' name='$field' $isDisabled>";
            echo "<option value=''>Pilih Anak Yatim</option>";
            $ankytm_res = $conn->query("SELECT id, nama FROM profile_ankytm");
            if ($ankytm_res && $ankytm_res->num_rows > 0) {
                while ($ankytm_row = $ankytm_res->fetch_assoc()) {
                    echo "<option value='{$ankytm_row['id']}'" . ($value == $ankytm_row['id'] ? ' selected' : '') . ">" . htmlspecialchars($ankytm_row['nama']) . "</option>";
                }
            }
            echo "</select>";
            if ($isDisabled) echo "<input type='hidden' name='$field' value='{$value}'>";
        } elseif ($field === 'adopsi_id' && $tabel === 'interaksi_adopsi') {
             $isDisabled = ($aksi_current === 'edit') ? 'disabled' : '';
             echo "<select class='form-control' id='{$field}' name='$field' $isDisabled>";
             echo "<option value=''>Pilih Adopsi Non-Finansial</option>";
             $adopsi_res = $conn->query("SELECT an.id, u.username, p.nama AS anak_nama FROM adopsi_non_finansial an JOIN users u ON an.donatur_id = u.id JOIN profile_ankytm p ON an.profile_ankytm_id = p.id");
             if ($adopsi_res && $adopsi_res->num_rows > 0) {
                 while ($adopsi_row = $adopsi_res->fetch_assoc()) {
                     echo "<option value='{$adopsi_row['id']}'" . ($value == $adopsi_row['id'] ? ' selected' : '') . ">" . htmlspecialchars($adopsi_row['username'] . ' - ' . $adopsi_row['anak_nama']) . "</option>";
                 }
             }
             echo "</select>";
             if ($isDisabled) echo "<input type='hidden' name='$field' value='{$value}'>";
        } elseif ($field === 'status' && $tabel === 'adopsi_non_finansial') {
            echo "<select class='form-control' id='{$field}' name='$field' required>";
            echo "<option value='aktif'" . ($value == 'aktif' ? ' selected' : '') . ">Aktif</option>";
            echo "<option value='selesai'" . ($value == 'selesai' ? ' selected' : '') . ">Selesai</option>";
            echo "<option value='ditinjau'" . ($value == 'ditinjau' ? ' selected' : '') . ">Ditinjau</option>";
            echo "</select>";
        } elseif ($field === 'status' && $tabel === 'interaksi_adopsi') {
            echo "<select class='form-control' id='{$field}' name='$field' required>";
            echo "<option value='ditinjau_admin'" . ($value == 'ditinjau_admin' ? ' selected' : '') . ">Ditinjau Admin</option>";
            echo "<option value='diteruskan_ke_anak'" . ($value == 'diteruskan_ke_anak' ? ' selected' : '') . ">Diteruskan ke Anak</option>";
            echo "<option value='disetujui_admin'" . ($value == 'disetujui_admin' ? ' selected' : '') . ">Disetujui Admin</option>";
            echo "<option value='ditolak_admin'" . ($value == 'ditolak_admin' ? ' selected' : '') . ">Ditolak Admin</option>";
            echo "</select>";
        } elseif ($field === 'tipe_interaksi' && $tabel === 'interaksi_adopsi') {
            $isDisabled = ($aksi_current === 'edit') ? 'disabled' : '';
            echo "<input type='text' class='form-control' id='{$field}' name='$field' value='" . htmlspecialchars($value) . "' $isDisabled>";
            if ($isDisabled) echo "<input type='hidden' name='$field' value='{$value}'>";
        } elseif (in_array($field, ['foto', 'gambar', 'profile_photo', 'logo_url'])) {
            echo "<input type='text' class='form-control' id='{$field}' name='$field' value='" . htmlspecialchars($value) . "' placeholder='URL gambar atau path lokal'>";
            if (!empty($value)) {
                echo "<p style='margin-top: 5px;'><img src='" . htmlspecialchars($value) . "' alt='Current Image' style='max-width: 100px; height: auto; border-radius: 4px;'></p>";
            }
        } elseif ($field === 'jawaban_benar' && $tabel === 'pertanyaan_kuis') {
            echo "<select class='form-control' id='{$field}' name='$field' required>";
            echo "<option value='A'" . ($value == 'A' ? ' selected' : '') . ">A</option>";
            echo "<option value='B'" . ($value == 'B' ? ' selected' : '') . ">B</option>";
            echo "<option value='C'" . ($value == 'C' ? ' selected' : '') . ">C</option>";
            echo "<option value='D'" . ($value == 'D' ? ' selected' : '') . ">D</option>";
            echo "</select>";
        } elseif ($field === 'pertanyaan_id' && $tabel === 'riwayat_kuis_pengguna') {
            echo "<select class='form-control' id='{$field}' name='$field' " . ($isDisabledForEdit ? 'disabled' : '') . ">";
            echo "<option value=''>Pilih Pertanyaan</option>";
            $pertanyaan_res = $conn->query("SELECT id, teks_pertanyaan FROM pertanyaan_kuis");
            if ($pertanyaan_res && $pertanyaan_res->num_rows > 0) {
                while ($pertanyaan_row = $pertanyaan_res->fetch_assoc()) {
                    echo "<option value='{$pertanyaan_row['id']}'" . ($value == $pertanyaan_row['id'] ? ' selected' : '') . ">" . htmlspecialchars(substr($pertanyaan_row['teks_pertanyaan'], 0, 50)) . "...</option>";
                }
            }
            echo "</select>";
            if ($isDisabledForEdit) echo "<input type='hidden' name='$field' value='{$value}'>";
        } elseif ($field === 'jawaban_pengguna' && $tabel === 'riwayat_kuis_pengguna') {
            echo "<select class='form-control' id='{$field}' name='$field' required>";
            echo "<option value='A'" . ($value == 'A' ? ' selected' : '') . ">A</option>";
            echo "<option value='B'" . ($value == 'B' ? ' selected' : '') . ">B</option>";
            echo "<option value='C'" . ($value == 'C' ? ' selected' : '') . ">C</option>";
            echo "<option value='D'" . ($value == 'D' ? ' selected' : '') . ">D</option>";
            echo "</select>";
        } elseif ($field === 'is_correct' && $tabel === 'riwayat_kuis_pengguna') {
            echo "<select class='form-control' id='{$field}' name='$field' required>";
            echo "<option value='1'" . ($value == '1' ? ' selected' : '') . ">Ya</option>";
            echo "<option value='0'" . ($value == '0' ? ' selected' : '') . ">Tidak</option>";
            echo "</select>";
        } elseif ($field === 'tipe_transaksi' && $tabel === 'transaksi_koin') {
            echo "<select class='form-control' id='{$field}' name='$field' required>";
            echo "<option value='penambahan_kuis'" . ($value == 'penambahan_kuis' ? ' selected' : '') . ">Penambahan (Kuis)</option>";
            echo "<option value='donasi_koin'" . ($value == 'donasi_koin' ? ' selected' : '') . ">Donasi Koin</option>";
            echo "</select>";
        } elseif ($field === 'is_active' && $tabel === 'sponsors') {
            echo "<select class='form-control' id='{$field}' name='$field' required>";
            echo "<option value='1'" . ($value == '1' ? ' selected' : '') . ">Aktif</option>";
            echo "<option value='0'" . ($value == '0' ? ' selected' : '') . ">Tidak Aktif</option>";
            echo "</select>";
        }
        else {
            echo "<input type='text' class='form-control' id='{$field}' name='$field' value='" . htmlspecialchars($value) . "'>";
        }
        echo "</div>";
    }
    echo "<div class='button-group-right'>";
    echo "<button class='btn btn-success' type='submit'>Simpan</button>";
    echo "<a href='?tabel=$tabel' class='btn btn-secondary'>Batal</a></div>";
    echo "</form><hr class='separator'>";
}

// Handle POST requests for form submissions (create/edit)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_GET['tabel']) && in_array($_GET['tabel'], $tabels)) {
    $tabel = $_GET['tabel'];
    $aksi = $_GET['aksi'] ?? 'create';
    $id = $_GET['id'] ?? null;

    $post_data = $_POST;

    // Remove disabled fields from $post_data if present
    if ($aksi === 'edit') {
        if ($tabel === 'adopsi_non_finansial') {
            $original_data_q = $conn->query("SELECT donatur_id, profile_ankytm_id, tanggal_adopsi FROM adopsi_non_finansial WHERE id=" . intval($id));
            if ($original_data_q && $original_data_q->num_rows > 0) {
                $original_row = $original_data_q->fetch_assoc();
                $post_data['donatur_id'] = $original_row['donatur_id'];
                $post_data['profile_ankytm_id'] = $original_row['profile_ankytm_id'];
                $post_data['tanggal_adopsi'] = $original_row['tanggal_adopsi'];
            }
        } elseif ($tabel === 'interaksi_adopsi') {
            $original_data_q = $conn->query("SELECT adopsi_id, tipe_interaksi, tanggal_interaksi FROM interaksi_adopsi WHERE id=" . intval($id));
            if ($original_data_q && $original_data_q->num_rows > 0) {
                $original_row = $original_data_q->fetch_assoc();
                $post_data['adopsi_id'] = $original_row['adopsi_id'];
                $post_data['tipe_interaksi'] = $original_row['tipe_interaksi'];
                $post_data['tanggal_interaksi'] = $original_row['tanggal_interaksi'];
            }
        }
        // created_at selalu disabled
        if (isset($post_data['created_at'])) {
            unset($post_data['created_at']);
        }
    }


    // Sanitize and prepare data
    foreach ($post_data as $key => &$value) {
        if (in_array($key, ['jumlah', 'total_donasi', 'jumlah_koin'])) {
            $value = str_replace(['.', ','], '', $value);
            $value = (int)$value;
        } elseif ($key === 'profile_ankytm_id' && $value === '') {
            $value = NULL;
        } elseif ($key === 'password') {
            // --- PERBAIKAN PENTING UNTUK PAMERAN: ADMIN TIDAK BISA UBAH PASSWORD ---
            // Jika dalam mode EDIT dan tabel 'users'
            if ($tabel === 'users' && $aksi === 'edit') {
                // Hapus field password dari data yang akan di-update
                // Karena field inputnya disabled, maka $_POST['password'] tidak akan ada.
                // Jika somehow ada (karena tampering), kita abaikan atau hapus saja.
                unset($post_data[$key]);
                continue; // Lanjutkan ke field berikutnya, jangan proses password
            }
            // Jika sedang CREATE (admin membuat user baru), password akan diambil langsung
            // Jika Anda sudah menggunakan password_hash() untuk register, di sini juga harus di-hash.
            // Saat ini, diasumsikan password disimpan PLAIN TEXT.
            // JANGAN MENGGUNAKAN password_hash() DI SINI JIKA PASSWORD DI DB ANDA PLAIN TEXT.
            // $value = password_hash($value, PASSWORD_DEFAULT); // Jika nanti pakai hashing, aktifkan ini
            // --------------------------------------------------------------------------
        } elseif ($key === 'is_correct' && $tabel === 'riwayat_kuis_pengguna') {
            $value = ($value == '1' || $value === true) ? 1 : 0;
        } elseif ($key === 'is_active' && $tabel === 'sponsors') {
            $value = ($value == '1' || $value === true) ? 1 : 0;
        }
    }
    unset($value); // Penting untuk unset reference

    // Handle file uploads
    $image_fields = ['foto', 'gambar', 'profile_photo', 'logo_url'];
    foreach ($image_fields as $img_field) {
        if (isset($_FILES[$img_field]) && $_FILES[$img_field]['error'] == UPLOAD_ERR_OK) {
             $upload_dir = 'uploads/';
             if ($img_field === 'logo_url' && $tabel === 'sponsors') {
                 $upload_dir .= 'logos/';
             }
             if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
             $filename = uniqid() . '_' . basename($_FILES[$img_field]['name']);
             $target_file = $upload_dir . $filename;
             if (move_uploaded_file($_FILES[$img_field]['tmp_name'], $target_file)) {
                 $post_data[$img_field] = $target_file;
             } else {
                 set_flash_message("Gagal mengunggah gambar {$img_field}.", "danger");
                 header("Location: ?tabel=$tabel&aksi=$aksi&id=$id");
                 exit();
             }
        }
    }

    // Build SQL query
    $fields = array_keys($post_data);
    $values = array_map(function ($val) use ($conn) {
        if (is_bool($val)) {
            return $val ? 'TRUE' : 'FALSE';
        }
        return ($val === NULL) ? 'NULL' : "'" . $conn->real_escape_string($val) . "'";
    }, array_values($post_data));

    if ($aksi == 'create') {
        $sql = "INSERT INTO $tabel (`" . implode('`,`', $fields) . "`) VALUES (" . implode(',', $values) . ")";
    } else { // aksi == 'edit'
        $pairs = [];
        foreach ($fields as $i => $f) {
            $val = $values[$i];
            if ($f === 'id') continue;
            $pairs[] = "`$f` = " . $val;
        }
        $sql = "UPDATE $tabel SET " . implode(',', $pairs) . " WHERE id=" . intval($id);
    }

    if ($conn->query($sql)) {
        set_flash_message("Data berhasil disimpan.");
        header("Location: ?tabel=$tabel");
        exit();
    } else {
        set_flash_message("Gagal: " . $conn->error, "danger");
    }
}

// Handle DELETE requests
if ($tabel && in_array($tabel, $tabels) && $aksi == 'delete') {
    // Cek khusus untuk donasi yang berasal dari koin_kuis
    if ($tabel === 'donasi') {
        $stmt_check_donation = $conn->prepare("SELECT payment_method FROM donasi WHERE id = ?");
        $stmt_check_donation->bind_param("i", $id);
        $stmt_check_donation->execute();
        $res_check = $stmt_check_donation->get_result()->fetch_assoc();
        $stmt_check_donation->close();

        if ($res_check && $res_check['payment_method'] === 'koin_kuis') {
            set_flash_message("Donasi yang berasal dari koin kuis tidak dapat dihapus melalui admin panel ini.", "danger");
            header("Location: ?tabel=$tabel");
            exit();
        }
    }

    // Handle foreign key constraints for adopsi_non_finansial and interaksi_adopsi
    if ($tabel == 'adopsi_non_finansial') {
        $conn->query("DELETE FROM interaksi_adopsi WHERE adopsi_id=" . intval($id));
    }
    if ($conn->query("DELETE FROM $tabel WHERE id=" . intval($id))) {
        set_flash_message("Data dihapus.");
    } else {
        set_flash_message("Gagal menghapus: " . $conn->error, "danger");
    }
    header("Location: ?tabel=$tabel");
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Rumah AYP - Admin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <style>
        /* General Resets & Body Styles */
        :root {
            --sidebar-collapsed-width: 60px;
            --sidebar-expanded-width: 280px;

            --color-bg-light: #F7F9FA;
            --color-bg-medium: #F0F2F5;
            --color-bg-dark: #FFFFFF; /* For content cards */
            --color-sidebar-dark: #1F2833; /* Lebih gelap dari sebelumnya */
            --color-sidebar-gradient-start: #283344;
            --color-sidebar-gradient-end: #1F2833;
            --color-header-blue: #C0DEED; /* Light Blue, lebih pucat */
            --color-text-dark: #212529;
            --color-text-medium: #495057;
            --color-text-light: #616161;
            --color-border-light: #E0E0E0;
            --color-border-medium: #CED4DA;

            --color-primary-blue: #007bff; /* Biru Bootstrap */
            --color-success-green: #28a745;
            --color-warning-yellow: #ffc107;
            --color-danger-red: #dc3545;
            --color-secondary-grey: #6c757d;

            --color-active-link: #4CAF50; /* Hijau sebagai highlight aktif */
            --color-table-header-bg: #E9ECEF;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(to bottom right, var(--color-bg-light), var(--color-bg-medium));
            display: flex;
            min-height: 100vh;
            overflow-x: hidden;
            color: var(--color-text-medium);
        }

        /* Main Layout Container */
        .admin-layout {
            display: flex;
            width: 100%;
        }

        /* Sidebar Styles */
        #sidebar {
            width: var(--sidebar-collapsed-width); /* Lebar default: kolaps */
            background: var(--color-sidebar-dark);
            color: var(--color-bg-light);
            padding-top: 20px;
            position: fixed;
            top: 0;
            left: 0;
            height: 100%;
            overflow-y: auto;
            transition: width 0.3s ease-out; /* Hanya transisi lebar */
            z-index: 1050;
            box-shadow: 5px 0 20px rgba(0,0,0,0.3);
            /* Pastikan sidebar selalu terlihat (tidak translateX(-nya)) */
        }

        /* Sidebar saat di-hover (diperluas) */
        #sidebar:hover { /* Sidebar langsung merespons hover */
            width: var(--sidebar-expanded-width);
        }

        /* Pergeseran konten utama saat sidebar di-hover */
        #sidebar:hover ~ .main-content-wrapper {
            margin-left: var(--sidebar-expanded-width);
        }

        #sidebar .sidebar-header {
            padding: 15px 10px; /* Padding lebih kecil untuk logo kecil */
            margin-bottom: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            overflow: hidden; /* Sembunyikan jika ada elemen yang melebihi */
        }
        #sidebar .sidebar-header img {
            max-width: 40px; /* Logo kecil di collapsed state */
            height: auto;
            margin-bottom: 15px;
            display: block;
            margin-left: auto;
            margin-right: auto;
            filter: drop-shadow(0 0 5px rgba(255,255,255,0.1));
            transition: max-width 0.3s ease-out;
        }
        /* Logo besar saat sidebar diperluas (oleh hover) */
        #sidebar:hover .sidebar-header img {
            max-width: 130px;
        }

        #sidebar .list-group {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        #sidebar .list-group-item {
            display: flex;
            align-items: center;
            background-color: transparent;
            border: none;
            border-radius: 4px;
            color: rgba(255,255,255,0.85);
            padding: 12px 0; /* Padding vertikal, horizontal 0 */
            margin: 6px auto; /* Margin auto untuk senter ikon secara horizontal */
            text-decoration: none;
            transition: background-color 0.3s ease, color 0.3s ease, transform 0.2s ease;
            font-size: 0.95rem;
            font-weight: 500;
            overflow: hidden; /* Penting untuk menyembunyikan teks */
            justify-content: center; /* Senter ikon secara horizontal */
        }
        /* Gaya saat sidebar diperluas (oleh hover) */
        #sidebar:hover .list-group-item {
            padding: 12px 25px; /* Kembali ke padding normal */
            margin: 6px 15px; /* Kembali ke margin normal */
            justify-content: flex-start; /* Kembali ke rata kiri */
        }

        #sidebar .list-group-item svg {
            margin-right: 0; /* Margin 0 saat kolaps */
            width: 18px;
            height: 18px;
            color: rgba(255,255,255,0.7);
            transition: color 0.3s ease, margin-right 0.3s ease;
            flex-shrink: 0;
        }
        #sidebar:hover .list-group-item svg {
            margin-right: 15px; /* Margin saat diperluas */
        }

        #sidebar .list-group-item .text-label { /* Kelas untuk teks menu */
            opacity: 0;
            width: 0; /* Lebar 0 saat collapsed */
            white-space: nowrap;
            overflow: hidden;
            transition: opacity 0.3s ease-out, width 0.3s ease-out;
        }
        #sidebar:hover .list-group-item .text-label {
            opacity: 1;
            width: auto; /* Otomatis menyesuaikan lebar teks */
        }

        #sidebar .list-group-item:hover,
        #sidebar .list-group-item.active {
            background-color: rgba(255,255,255,0.1);
            color: var(--color-active-link);
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            transform: translateX(5px);
        }
        #sidebar .list-group-item:hover svg,
        #sidebar .list-group-item.active svg {
            color: var(--color-active-link);
        }

        #sidebar .sidebar-footer {
            padding: 20px 10px; /* Padding disesuaikan */
            border-top: 1px solid rgba(255,255,255,0.1);
            text-align: center;
            margin-top: auto;
            overflow: hidden; /* Penting untuk menyembunyikan footer saat kolaps */
        }
        #sidebar .sidebar-footer .btn {
            color: var(--color-bg-light);
            font-weight: 600;
            display: inline-block;
            text-decoration: none;
            padding: 8px 18px;
            border-radius: 4px;
            background-color: rgba(255,255,255,0.05);
            transition: background-color 0.2s ease, color 0.2s ease;
            border: none;
            font-size: 0.9rem;
            opacity: 0; /* Sembunyikan tombol footer saat kolaps */
            transition: opacity 0.3s ease-out;
            width: 100%; /* Agar tombol rata saat diperluas */
        }
        #sidebar:hover .sidebar-footer .btn {
            opacity: 1; /* Tampilkan tombol footer saat diperluas */
        }
        #sidebar .sidebar-footer .btn .text-label { /* Tambahkan .text-label jika ada di HTML */
            opacity: 0;
            width: 0;
            white-space: nowrap;
            overflow: hidden;
            transition: opacity 0.3s ease-out, width 0.3s ease-out;
        }
         #sidebar:hover .sidebar-footer .btn .text-label {
            opacity: 1;
            width: auto;
        }


        /* Main Content Wrapper */
        .main-content-wrapper {
            flex-grow: 1;
            margin-left: var(--sidebar-collapsed-width); /* Margin default sesuai lebar sidebar kolaps */
            transition: margin-left 0.3s ease-in-out;
            background: linear-gradient(to bottom right, var(--color-bg-light), var(--color-bg-medium));
            color: var(--color-text-medium);
            display: flex;
            flex-direction: column;
        }


        /* Top Header Bar */
        .top-header-bar {
            background-color: var(--color-header-blue);
            padding: 15px 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: white;
            height: 60px;
        }
        .top-header-bar .header-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
            color: var(--color-text-dark);
        }
        .top-header-bar .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            color: var(--color-text-dark);
        }
        .top-header-bar .user-info .user-name {
            font-weight: 500;
        }
        .top-header-bar .user-info svg {
            width: 20px;
            height: 20px;
        }

        /* Content Area */
        .content-area {
            padding: 30px;
            flex-grow: 1;
        }

        .main-title {
            color: var(--color-text-dark);
            margin-top: 0;
            margin-bottom: 30px;
            font-weight: 700;
            font-size: 2.2rem;
            border-bottom: 1px solid var(--color-border-light);
            padding-bottom: 15px;
        }

        /* Menu Toggle Icon (Hanya tampil di mobile, berfungsi sebagai click toggle) */
        #menu-toggle-icon {
            display: none; /* Sembunyikan di desktop */
            position: fixed;
            top: 20px;
            left: 20px;
            width: 40px;
            height: 40px;
            background-color: var(--color-primary-blue);
            border-radius: 50%;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 1200;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            transition: transform 0.3s ease;
            color: white;
        }
        #menu-toggle-icon svg {
            transition: transform 0.3s ease;
        }


        /* Filter and Action Bar */
        .filter-action-bar {
            background-color: var(--color-bg-dark);
            padding: 20px 30px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
            margin-bottom: 30px;
        }
        .filter-action-bar .left-actions,
        .filter-action-bar .right-filters {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        .filter-action-bar .search-form {
            display: flex;
            flex-grow: 1;
            max-width: 300px;
        }
        .filter-action-bar .search-input-group {
            display: flex;
            border: 1px solid var(--color-border-medium);
            border-radius: 6px;
            overflow: hidden;
            flex-grow: 1;
        }
        .filter-action-bar .search-input-group input {
            border: none;
            padding: 8px 12px;
            font-size: 0.9rem;
            outline: none;
            flex-grow: 1;
        }
        .filter-action-bar .search-input-group button {
            background-color: var(--color-primary-blue);
            border: none;
            color: white;
            padding: 8px 12px;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        .filter-action-bar .search-input-group button:hover {
            background-color: #388EE6;
        }
        .filter-action-bar .results-dropdown select {
            border: 1px solid var(--color-border-medium);
            border-radius: 6px;
            padding: 8px 12px;
            font-size: 0.9rem;
            background-color: white;
            cursor: pointer;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23495057' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 16px 12px;
            padding-right: 2.5rem;
        }
        .filter-action-bar .results-text {
            font-size: 0.9rem;
            color: var(--color-text-light);
        }

        /* Form Card Styling */
        .form-card {
            padding: 40px;
            background-color: var(--color-bg-dark);
            border-radius: 8px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        .form-title {
            margin-top: 0;
            margin-bottom: 30px;
            color: var(--color-text-dark);
            font-size: 1.6rem;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            font-weight: 600;
            color: var(--color-text-light);
            margin-bottom: 8px;
        }
        .form-control {
            width: 100%;
            padding: 10px 15px;
            border-radius: 6px;
            border: 1px solid var(--color-border-medium);
            background-color: var(--color-bg-dark);
            color: var(--color-text-medium);
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.03);
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            box-sizing: border-box;
        }
        .form-control:focus {
            border-color: var(--color-primary-blue);
            box-shadow: 0 0 0 0.2rem rgba(66, 165, 245, 0.25);
            outline: none;
        }
        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }
        .form-control[type="select"] {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23495057' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 16px 12px;
            padding-right: 2.5rem;
        }


        /* Button Styles */
        .button-group-right {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 25px;
        }
        .btn {
            padding: 10px 22px;
            font-weight: 600;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 3px 8px rgba(0,0,0,0.1);
            color: #FFFFFF;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.95rem;
        }
        .btn svg {
            margin-right: 8px;
            width: 18px;
            height: 18px;
        }
        .btn-primary {
            background-color: var(--color-primary-blue);
        }
        .btn-primary:hover {
            background-color: #2196F3;
            transform: translateY(-1px);
            box-shadow: 0 5px 12px rgba(0,0,0,0.18);
        }
        .btn-success {
            background-color: var(--color-success-green);
        }
        .btn-success:hover {
            background-color: #4CAF50;
            transform: translateY(-1px);
            box-shadow: 0 5px 12px rgba(0,0,0,0.18);
        }
        .btn-warning {
            background-color: var(--color-warning-yellow);
            color: var(--color-text-medium);
        }
        .btn-warning:hover {
            background-color: #FFB300;
            transform: translateY(-1px);
            box-shadow: 0 5px 12px rgba(0,0,0,0.18);
        }
        .btn-danger {
            background-color: var(--color-danger-red);
        }
        .btn-danger:hover {
            background-color: #D32F2F;
            transform: translateY(-1px);
            box-shadow: 0 5px 12px rgba(0,0,0,0.18);
        }
        .btn-secondary {
            background-color: var(--color-secondary-grey);
        }
        .btn-secondary:hover {
            background-color: #757575;
            transform: translateY(-1px);
            box-shadow: 0 5px 12px rgba(0,0,0,0.18);
        }
        .btn-icon-only {
            width: 36px;
            height: 36px;
            padding: 0;
            border-radius: 50%;
            box-shadow: none;
            margin: 0 2px;
        }
        .btn-icon-only svg {
            margin-right: 0;
        }
        .btn-icon-only.view { background-color: #1E90FF; }
        .btn-icon-only.view:hover { background-color: #1C86EE; }
        .btn-icon-only.edit { background-color: #FFD700; color: var(--color-text-dark); }
        .btn-icon-only.edit:hover { background-color: #DAA520; }
        .btn-icon-only.delete { background-color: #DC143C; }
        .btn-icon-only.delete:hover { background-color: #B22222; }


        /* Table Styles */
        .table-container {
            overflow-x: auto;
            background-color: var(--color-bg-dark);
            border-radius: 8px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            color: var(--color-text-medium);
        }
        .data-table thead {
            background-color: var(--color-table-header-bg);
            color: var(--color-text-light);
        }
        .data-table th, .data-table td {
            padding: 12px 15px; /* */
            vertical-align: middle;
            text-align: left;
            border-bottom: 1px solid var(--color-border-light);
            word-break: break-word; /* */
        }
        .data-table th {
            color: var(--color-text-light);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9rem;
        }
        .data-table tbody tr:last-child td {
            border-bottom: none;
        }
        .data-table tbody tr:nth-child(odd) {
            background-color: var(--color-bg-dark);
        }
        .data-table tbody tr:nth-child(even) {
            background-color: var(--color-bg-light);
        }
        .data-table img {
            max-width: 70px;
            height: auto;
            border-radius: 4px;
            object-fit: cover;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .data-table .action-cell {
            white-space: normal; /* */
            text-align: center;
            padding: 8px;
        }
        .data-table .action-buttons-group {
            display: flex;
            flex-wrap: wrap; /* */
            justify-content: center;
            gap: 5px;
        }

        /* Pagination Styles */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 30px;
            padding: 10px 0;
            background-color: var(--color-bg-dark);
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            font-size: 0.9rem;
        }
        .pagination .page-link {
            padding: 8px 12px;
            margin: 0 4px;
            text-decoration: none;
            color: var(--color-text-medium);
            border-radius: 4px;
            transition: background-color 0.2s ease, color 0.2s ease;
            font-weight: 500;
        }
        .pagination .page-link:hover {
            background-color: var(--color-border-light);
        }
        .pagination .page-link.active {
            background-color: var(--color-primary-blue);
            color: white;
            font-weight: 700;
        }
        .pagination .page-link.disabled {
            color: var(--color-border-medium);
            cursor: not-allowed;
        }
        .pagination .page-link.disabled:hover {
            background-color: transparent;
        }
        .pagination-results-info {
            font-size: 0.85rem;
            color: var(--color-text-light);
            margin-left: 20px;
        }

        /* Alert Messages */
        .alert-message {
            padding: 15px 20px;
            border-radius: 6px;
            font-size: 1rem;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            position: relative;
            display: flex;
            align-items: center;
            border: 1px solid;
        }
        .alert-message.alert-success {
            border-color: var(--color-success-green);
            color: #388E3C;
            background-color: #E8F5E9;
        }
        .alert-message.alert-danger {
            border-color: var(--color-danger-red);
            color: #C62828;
            background-color: #FFEBEE;
        }
        .alert-message.alert-info {
            border-color: var(--color-primary-blue);
            color: #1976D2;
            background-color: #E3F2FD;
        }
        .alert-close-button {
            background: none;
            border: none;
            font-size: 1.5rem;
            line-height: 1;
            padding: 0;
            cursor: pointer;
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: inherit;
            opacity: 0.7;
            transition: opacity 0.2s ease;
        }
        .alert-close-button:hover {
            opacity: 1;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .filter-action-bar {
                flex-direction: column;
                align-items: flex-start;
            }
            .filter-action-bar .right-filters {
                margin-top: 15px;
            }
        }
        @media (max-width: 768px) {
            /* Di layar kecil (mobile/tablet), sidebar akan sepenuhnya tersembunyi/muncul */
            #sidebar {
                width: 0;
                transform: translateX(0);
                box-shadow: none;
                overflow-y: hidden;
            }
            #sidebar.sidebar-expanded {
                width: var(--sidebar-expanded-width);
                transform: translateX(0);
                box-shadow: 5px 0 20px rgba(0,0,0,0.3);
                overflow-y: auto;
            }
            #sidebar .sidebar-header {
                padding: 15px 25px;
            }
            #sidebar .sidebar-header img {
                max-width: 130px;
            }
            #sidebar .list-group-item {
                padding: 12px 25px;
                margin: 6px 15px;
                justify-content: flex-start;
            }
            #sidebar .list-group-item svg {
                 margin-right: 15px;
            }
            #sidebar .list-group-item .text-label {
                opacity: 1;
                width: auto;
            }
            #sidebar .sidebar-footer {
                padding: 20px 25px;
            }
            #sidebar .sidebar-footer .btn {
                opacity: 1;
            }


            /* Menu Toggle Icon (Hanya tampil di mobile, berfungsi sebagai click toggle) */
            #menu-toggle-icon {
                display: flex;
                left: 20px;
                top: 20px;
                width: 40px;
                height: 40px;
                background-color: var(--color-primary-blue);
                border-radius: 50%;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                z-index: 1200;
                box-shadow: 0 2px 10px rgba(0,0,0,0.2);
                transition: transform 0.3s ease;
                color: white;
            }
            #menu-toggle-icon.active svg {
                 transform: rotate(90deg);
            }

            .main-content-wrapper {
                margin-left: 0;
            }
            /* Tidak ada pergeseran konten di mobile, hanya overlay */
            .main-content-wrapper.content-shifted {
                margin-left: 0;
            }

            .content-area {
                padding: 20px;
            }
            .main-title {
                font-size: 2rem;
            }
            .form-card {
                padding: 30px;
            }
            .data-table th, .data-table td {
                padding: 10px 12px; /* */
                font-size: 0.9rem;
            }
            .data-table .action-cell {
                padding: 5px;
            }
            .btn-icon-only {
                width: 30px;
                height: 30px;
            }
            .pagination {
                flex-wrap: wrap;
                justify-content: center;
                gap: 5px;
            }
            .pagination-results-info {
                margin-left: 0;
                margin-top: 10px;
                text-align: center;
                width: 100%;
            }
        }
        @media (max-width: 480px) {
            .main-title {
                font-size: 1.8rem;
            }
            .btn {
                padding: 8px 15px;
                font-size: 0.85rem;
            }
            .form-control {
                padding: 8px 12px;
                font-size: 0.9rem;
            }
            .button-group-right {
                flex-direction: column;
                gap: 8px;
            }
            .btn {
                width: 100%;
            }
            .filter-action-bar {
                padding: 15px;
            }
            .filter-action-bar .search-form {
                flex-direction: column;
                width: 100%;
                max-width: none;
            }
            .filter-action-bar .search-input-group {
                width: 100%;
            }
            .filter-action-bar .left-actions,
            .filter-action-bar .right-filters {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <div id="menu-toggle-icon">
            <i data-feather="menu"></i> </div>

        <div id="sidebar">
            <div class="sidebar-header">
                <img src="logo_rumah_ayp.png" alt="Rumah AYP Logo">
            </div>
            <ul class="list-group">
                <?php foreach ($tabels as $t): /* */ ?>
                    <li><a href="?tabel=<?= $t ?>" class="list-group-item <?= ($tabel == $t) ? 'active' : '' ?>">
                        <i data-feather="<?=
                            ($t == 'berita' ? 'file-text' :
                            ($t == 'doa' ? 'heart' :
                            ($t == 'donasi' ? 'dollar-sign' :
                            ($t == 'profile_ankytm' ? 'users' :
                            ($t == 'programm' ? 'target' :
                            ($t == 'users' ? 'user' :
                            ($t == 'pengeluaran' ? 'trending-down' :
                            ($t == 'adopsi_non_finansial' ? 'link' :
                            ($t == 'interaksi_adopsi' ? 'message-circle' :
                            ($t == 'pertanyaan_kuis' ? 'help-circle' : // Icon untuk pertanyaan kuis
                            ($t == 'riwayat_kuis_pengguna' ? 'clock' : // Icon untuk riwayat kuis pengguna
                            ($t == 'transaksi_koin' ? 'dollar-sign' :
                            ($t == 'sponsors' ? 'award' : 'circle'))))))))))))) // Icon untuk tabel sponsors
                        ?>"></i>
                        <span class="text-label"><?= ucwords(str_replace('_', ' ', $t)) ?></span>
                    </a></li>
                <?php endforeach; /* */ ?>
            </ul>
            <div class="sidebar-footer">
                <a href="index.php" class="btn"><span class="text-label">← Kembali ke Halaman Utama</span></a>
            </div>
        </div>

        <div class="main-content-wrapper" id="content-wrapper">
            <header class="top-header-bar">
                <div class="header-title">Rumah AYP Dashboard</div>

            </header>

            <div class="content-area">
                <?php
                $flash = get_flash_message(); /* */
                if ($flash): /* */
                ?>
                <div class="alert-message alert-<?= htmlspecialchars($flash['type']) ?>" role="alert">
                    <?= htmlspecialchars($flash['message']) /* */ ?>
                    <button type="button" class="alert-close-button" onclick="this.parentElement.style.display='none';" aria-label="Close">&times;</button>
                </div>
                <?php endif; /* */ ?>

                <?php
                if (($tabel && in_array($tabel, $tabels)) && ($aksi == 'create' || $aksi == 'edit')) { /* */
                    $editData = []; /* */
                    if ($aksi == 'edit') { /* */
                        $q = $conn->query("SELECT * FROM $tabel WHERE id=" . intval($id)); /* */ // Added intval() for safety
                        if ($q && $q->num_rows > 0) { /* */
                            $editData = $q->fetch_assoc(); /* */
                            if (isset($editData['jumlah'])) { /* */
                                $editData['jumlah'] = number_format($editData['jumlah'], 0, ',', '.'); /* */
                            }
                            if ($tabel === 'programm' && isset($editData['total_donasi'])) { /* */
                                $editData['total_donasi'] = number_format($editData['total_donasi'], 0, ',', '.'); /* */
                            }
                             if ($tabel === 'transaksi_koin' && isset($editData['jumlah_koin'])) { // Tambahkan format untuk jumlah_koin
                                $editData['jumlah_koin'] = number_format($editData['jumlah_koin'], 0, ',', '.');
                            }
                        } else { /* */
                            echo "<p class='alert-message alert-danger'>Data tidak ditemukan.</p>"; /* */
                        }
                    }
                    renderForm($tabel, $editData, $aksi); /* */ // PASS $aksi KE renderForm
                } elseif ($tabel && in_array($tabel, $tabels)) { /* */
                    echo "<div class='filter-action-bar'>"; /* */
                    echo "    <div class='left-actions'>"; /* */
                    // Tombol 'Tambah Data Baru' hanya muncul jika tabel BUKAN 'donasi', 'adopsi_non_finansial', 'interaksi_adopsi'
                    // Sekarang riwayat_kuis_pengguna dan transaksi_koin juga tidak bisa ditambah manual
                    if (!in_array($tabel, ['donasi', 'adopsi_non_finansial', 'interaksi_adopsi', 'riwayat_kuis_pengguna', 'transaksi_koin'])) { /* */
                        echo "        <a href='?tabel=$tabel&aksi=create' class='btn btn-primary'><i data-feather='plus-circle'></i> Tambah Data Baru</a>"; /* */
                    } else if ($tabel === 'adopsi_non_finansial') { /* */
                         echo "        <p class='alert-message alert-info' style='margin:0;'>Adopsi Non-Finansial dibuat dari sisi Donatur.</p>"; /* */
                    } else if ($tabel === 'interaksi_adopsi') { /* */
                         echo "        <p class='alert-message alert-info' style='margin:0;'>Interaksi Adopsi dibuat dari sisi Donatur.</p>"; /* */
                    } else if ($tabel === 'riwayat_kuis_pengguna') {
                        echo "        <p class='alert-message alert-info' style='margin:0;'>Riwayat Kuis Pengguna otomatis dicatat saat user bermain kuis.</p>";
                    } else if ($tabel === 'transaksi_koin') {
                        echo "        <p class='alert-message alert-info' style='margin:0;'>Transaksi Koin otomatis dicatat saat user bermain kuis/donasi.</p>";
                    }

                    echo "        <a href='export_to_excel.php?tabel=" . htmlspecialchars($tabel) . "' class='btn btn-secondary'><i data-feather='download'></i> Download Data Excel</a>"; /* */
                    echo "    </div>"; /* */
                    echo "    <div class='right-filters'>"; /* */
                    echo "        <form action='?tabel=" . htmlspecialchars($tabel) . "' method='GET' class='search-form'>"; /* */
                    echo "            <input type='hidden' name='tabel' value='" . htmlspecialchars($tabel) . "'>"; /* */
                    echo "            <div class='search-input-group'>"; /* */
                    echo "                <input type='text' name='search_query' placeholder='Cari...' value='" . htmlspecialchars($search_query) . "' />"; /* */
                    echo "                <button type='submit'><i data-feather='search'></i></button>"; /* */
                    echo "            </div>"; /* */
                    echo "        </form>"; /* */
                    echo "        <div class='results-dropdown'>"; /* */
                    echo "            <select onchange=\"window.location.href = '?tabel=" . htmlspecialchars($tabel) . "&search_query=" . htmlspecialchars($search_query) . "&page=1&limit=' + this.value;\">"; /* */
                    $limits = [10, 25, 50, 100];
                    foreach($limits as $l) {
                        echo "<option value='{$l}'" . ($limit == $l ? ' selected' : '') . ">Results {$l}</option>";
                    }
                    echo "            </select>"; /* */
                    echo "        </div>"; /* */
                    echo "    </div>"; /* */
                    echo "</div>"; /* */

                    $where_clause = ""; /* */
                    if (!empty($search_query) && isset($searchable_columns[$tabel])) { /* */
                        $search_terms = explode(' ', $search_query); /* */
                        $conditions = []; /* */
                        foreach ($search_terms as $term) { /* */
                            $escaped_term = $conn->real_escape_string($term); /* */
                            $like_conditions = []; /* */
                            foreach ($searchable_columns[$tabel] as $column) { /* */
                                $like_conditions[] = "`" . $column . "` LIKE '%" . $escaped_term . "%'"; /* */
                            }
                            if (!empty($like_conditions)) { /* */
                                $conditions[] = "(" . implode(' AND ', $like_conditions) . ")"; /* */
                            }
                        }
                        if (!empty($conditions)) { /* */
                            $where_clause = " WHERE " . implode(' AND ', $conditions) . " "; /* Spasi tambahan di akhir */
                        }
                    }

                    // Query untuk menghitung total records (tanpa LIMIT)
                    $count_sql = "";
                    if ($tabel === 'adopsi_non_finansial') {
                        $count_sql = "SELECT COUNT(*) FROM adopsi_non_finansial an JOIN users u ON an.donatur_id = u.id JOIN profile_ankytm p ON an.profile_ankytm_id = p.id" . $where_clause;
                    } elseif ($tabel === 'interaksi_adopsi') {
                         $count_sql = "SELECT COUNT(*) FROM interaksi_adopsi ia JOIN adopsi_non_finansial an ON ia.adopsi_id = an.id JOIN users u ON an.donatur_id = u.id JOIN profile_ankytm p ON an.profile_ankytm_id = p.id" . $where_clause;
                    } elseif ($tabel === 'riwayat_kuis_pengguna') {
                         $count_sql = "SELECT COUNT(*) FROM riwayat_kuis_pengguna rkp JOIN users u ON rkp.user_id = u.id JOIN pertanyaan_kuis pk ON rkp.pertanyaan_id = pk.id" . $where_clause;
                    } elseif ($tabel === 'transaksi_koin') {
                        $count_sql = "SELECT COUNT(*) FROM transaksi_koin tk JOIN users u ON tk.user_id = u.id" . $where_clause;
                    } elseif ($tabel === 'sponsors') {
                         $count_sql = "SELECT COUNT(*) FROM sponsors" . $where_clause;
                    }
                    else {
                        $count_sql = "SELECT COUNT(*) FROM " . $conn->real_escape_string($tabel) . $where_clause;
                    }

                    $count_result = $conn->query($count_sql);
                    if ($count_result) {
                        $total_records = $count_result->fetch_row()[0];
                    }

                    $total_pages = ceil($total_records / $limit);


                    // SQL Query utama (dengan LIMIT dan OFFSET)
                    $sql = "";
                    if ($tabel === 'adopsi_non_finansial') {
                        $sql = "SELECT an.id, u.username AS donatur_nama, p.nama AS anak_nama, an.tanggal_adopsi, an.status
                                FROM adopsi_non_finansial an
                                JOIN users u ON an.donatur_id = u.id
                                JOIN profile_ankytm p ON an.profile_ankytm_id = p.id" . $where_clause . " LIMIT $limit OFFSET $offset";
                    } elseif ($tabel === 'interaksi_adopsi') {
                         $sql = "SELECT ia.id, ia.tipe_interaksi, ia.subjek, ia.konten_pesan, ia.tanggal_interaksi, ia.status, ia.catatan_admin, u.username AS donatur_nama, p.nama AS anak_nama
                                FROM interaksi_adopsi ia
                                JOIN adopsi_non_finansial an ON ia.adopsi_id = an.id
                                JOIN users u ON an.donatur_id = u.id
                                JOIN profile_ankytm p ON an.profile_ankytm_id = p.id" . $where_clause . " ORDER BY ia.tanggal_interaksi DESC LIMIT $limit OFFSET $offset";
                    } elseif ($tabel === 'riwayat_kuis_pengguna') {
                         $sql = "SELECT rkp.id, u.username AS user_nama, pk.teks_pertanyaan AS pertanyaan_teks, rkp.jawaban_pengguna, rkp.is_correct, rkp.tanggal_jawab
                                FROM riwayat_kuis_pengguna rkp
                                JOIN users u ON rkp.user_id = u.id
                                JOIN pertanyaan_kuis pk ON rkp.pertanyaan_id = pk.id" . $where_clause . " ORDER BY rkp.tanggal_jawab DESC LIMIT $limit OFFSET $offset";
                    } elseif ($tabel === 'transaksi_koin') {
                        $sql = "SELECT tk.id, u.username AS user_nama, tk.tipe_transaksi, tk.jumlah_koin, tk.deskripsi, tk.tanggal_transaksi
                                FROM transaksi_koin tk
                                JOIN users u ON tk.user_id = u.id" . $where_clause . " ORDER BY tk.tanggal_transaksi DESC LIMIT $limit OFFSET $offset";
                    } elseif ($tabel === 'sponsors') {
                         $sql = "SELECT * FROM sponsors" . $where_clause . " LIMIT $limit OFFSET $offset";
                    }
                    else {
                        $sql = "SELECT * FROM " . $conn->real_escape_string($tabel) . $where_clause . " LIMIT $limit OFFSET $offset";
                    }

                    $result = $conn->query($sql);

                    if ($result && $result->num_rows > 0) {
                        echo "<div class='table-container'><table class='data-table'><thead><tr>";
                        $field_names = [];
                        if ($tabel === 'interaksi_adopsi') {
                            echo "<th>ID</th><th>Donatur</th><th>Anak Asuh</th><th>Tipe Interaksi</th><th>Subjek</th><th>Pesan</th><th>Tanggal</th><th>Status</th><th>Catatan Admin</th>";
                            $field_names = ['id', 'donatur_nama', 'anak_nama', 'tipe_interaksi', 'subjek', 'konten_pesan', 'tanggal_interaksi', 'status', 'catatan_admin'];
                        } elseif ($tabel === 'adopsi_non_finansial') {
                             echo "<th>ID</th><th>Donatur</th><th>Anak Asuh</th><th>Tanggal Adopsi</th><th>Status</th>";
                             $field_names = ['id', 'donatur_nama', 'anak_nama', 'tanggal_adopsi', 'status'];
                        } elseif ($tabel === 'riwayat_kuis_pengguna') {
                            echo "<th>ID</th><th>User</th><th>Pertanyaan</th><th>Jawaban User</th><th>Benar?</th><th>Tanggal Jawab</th>";
                            $field_names = ['id', 'user_nama', 'pertanyaan_teks', 'jawaban_pengguna', 'is_correct', 'tanggal_jawab'];
                        } elseif ($tabel === 'transaksi_koin') {
                            echo "<th>ID</th><th>User</th><th>Tipe Transaksi</th><th>Jumlah Koin</th><th>Deskripsi</th><th>Tanggal Transaksi</th>";
                            $field_names = ['id', 'user_nama', 'tipe_transaksi', 'jumlah_koin', 'deskripsi', 'tanggal_transaksi'];
                        } elseif ($tabel === 'sponsors') {
                            echo "<th>ID</th><th>Nama Sponsor</th><th>Logo</th><th>Website</th><th>Deskripsi</th><th>Aktif?</th><th>Dibuat Pada</th>";
                            $field_names = ['id', 'nama_sponsor', 'logo_url', 'website_url', 'deskripsi', 'is_active', 'created_at'];
                        }
                        else {
                            while ($field = $result->fetch_field()) {
                                $field_names[] = $field->name;
                                echo "<th>" . htmlspecialchars(ucwords(str_replace('_', ' ', $field->name))) . "</th>";
                            }
                        }
                        echo "<th class='action-cell'>Aksi</th></tr></thead><tbody>";
                        while ($row = $result->fetch_assoc()) {
                            echo "<tr>";
                            foreach ($field_names as $field_name) {
                                $val = $row[$field_name];
                                if (in_array($field_name, ['foto', 'gambar', 'profile_photo', 'logo_url']) && !empty($val)) {
                                    $image_src = filter_var($val, FILTER_VALIDATE_URL) ? $val : htmlspecialchars($val);
                                    echo "<td><img src='" . $image_src . "' alt='Gambar'></td>";
                                } elseif (in_array($field_name, ['deskripsi', 'isi_doa', 'cerita', 'pesan', 'keterangan', 'konten_pesan', 'pertanyaan_teks'])) {
                                    echo "<td>" . htmlspecialchars(substr($val, 0, 100)) . (strlen($val) > 100 ? '...' : '') . "</td>";
                                } elseif ($field_name === 'password') {
                                    echo "<td>********</td>"; // Selalu tampilkan sensor untuk password
                                } elseif ((in_array($field_name, ['jumlah']) && ($tabel == 'donasi' || $tabel == 'pengeluaran')) || ($field_name === 'total_donasi' && $tabel === 'programm')) {
                                    echo "<td>Rp " . number_format($val, 0, ',', '.') . "</td>";
                                } elseif ($field_name === 'jumlah_koin' && $tabel === 'transaksi_koin') {
                                     $sign = $val > 0 ? '+' : '';
                                     echo "<td style='color: " . ($val > 0 ? 'var(--color-success-green)' : 'var(--color-danger-red)') . "; font-weight: bold;'>{$sign}" . number_format($val, 0, ',', '.') . "</td>";
                                } elseif ($field_name === 'programm_id' && $tabel === 'donasi') {
                                    $program_id = $val;
                                    $program_q = $conn->query("SELECT nama FROM programm WHERE id = " . intval($program_id));
                                    $program_name = $program_q && $program_q->num_rows > 0 ? $program_q->fetch_assoc()['nama'] : "N/A";
                                    echo "<td>" . htmlspecialchars($program_name) . "</td>";
                                } elseif (in_array($field_name, ['user_id', 'donatur_nama', 'user_nama'])) {
                                    $display_name = $val;
                                    if (in_array($tabel, ['donasi', 'doa', 'riwayat_kuis_pengguna', 'transaksi_koin']) && $field_name === 'user_id') {
                                        $user_id_val = $val;
                                        $username_q = $conn->query("SELECT username FROM users WHERE id = " . intval($user_id_val));
                                        $display_name = $username_q && $username_q->num_rows > 0 ? $username_q->fetch_assoc()['username'] : "N/A";
                                    }
                                    echo "<td>" . htmlspecialchars($display_name) . "</td>";
                                } elseif (in_array($field_name, ['profile_ankytm_id', 'anak_nama'])) {
                                    $display_name = $val;
                                    if ($tabel === 'pengeluaran' && $field_name === 'profile_ankytm_id') {
                                        $ankytm_id_val = $val;
                                        $ankytm_q = $conn->query("SELECT nama FROM profile_ankytm WHERE id = " . intval($ankytm_id_val));
                                        $display_name = $ankytm_q && $ankytm_q->num_rows > 0 ? $ankytm_q->fetch_assoc()['nama'] : "N/A";
                                    }
                                    echo "<td>" . htmlspecialchars($display_name) . "</td>";
                                } elseif (in_array($field_name, ['tanggal_doa', 'tanggal_pengeluaran', 'tanggal', 'created_at', 'tanggal_interaksi', 'tanggal_adopsi', 'tanggal_transaksi'])) {
                                    echo "<td>" . htmlspecialchars(date('Y-m-d H:i:s', strtotime($val))) . "</td>";
                                } elseif ($field_name === 'tanggal' && $tabel === 'berita') {
                                    echo "<td>" . htmlspecialchars($val) . "</td>";
                                } elseif ($field_name === 'is_correct' && $tabel === 'riwayat_kuis_pengguna') {
                                    echo "<td>" . ($val ? 'Ya' : 'Tidak') . "</td>";
                                } elseif ($field_name === 'tipe_transaksi' && $tabel === 'transaksi_koin') {
                                    echo "<td>" . htmlspecialchars(ucwords(str_replace('_', ' ', $val))) . "</td>";
                                } elseif ($field_name === 'is_active' && $tabel === 'sponsors') {
                                    echo "<td>" . ($val ? 'Aktif' : 'Tidak Aktif') . "</td>";
                                }
                                else {
                                    echo "<td>" . htmlspecialchars($val) . "</td>";
                                }
                            }
                            echo "<td class='action-cell'>
                                <div class='action-buttons-group'>";
                            // Tombol Edit
                            if (!($tabel === 'donasi' && $row['payment_method'] === 'koin_kuis')) {
                                echo "<a href='?tabel=$tabel&aksi=edit&id={$row['id']}' class='btn btn-icon-only edit' title='Edit Data'><i data-feather='edit'></i></a>";
                            } else {
                                echo "<button class='btn btn-icon-only edit' title='Tidak dapat diedit' disabled><i data-feather='edit'></i></button>";
                            }

                            // Tombol Delete
                            if (!($tabel === 'donasi' && $row['payment_method'] === 'koin_kuis')) {
                                echo "<a href='?tabel=$tabel&aksi=delete&id={$row['id']}' class='btn btn-icon-only delete' title='Hapus Data' onclick=\"return confirm('Apakah Anda yakin ingin menghapus data ini?')\"><i data-feather='trash-2'></i></a>";
                            } else {
                                echo "<button class='btn btn-icon-only delete' title='Tidak dapat dihapus' disabled><i data-feather='trash-2'></i></button>";
                            }
                            echo "</div>
                                </td></tr>";
                        }
                        echo "</tbody></table></div>";

                        // --- PAGINASI LINK BARU ---
                        $start_record_display = $offset + 1;
                        $end_record_display = min($offset + $result->num_rows, $total_records); // Menggunakan $result->num_rows untuk jumlah yang benar di halaman ini

                        echo "<div class='pagination'>";
                        // Tombol Previous
                        if ($page > 1) {
                            echo "<a href='?tabel=" . htmlspecialchars($tabel) . "&search_query=" . htmlspecialchars($search_query) . "&limit={$limit}&page=" . ($page - 1) . "' class='page-link'>&laquo;</a>";
                        } else {
                            echo "<span class='page-link disabled'>&laquo;</span>";
                        }

                        // Nomor Halaman
                        $num_links = 5; // Jumlah link halaman yang ditampilkan (misal: 1 2 [3] 4 5)
                        $start_page_link = max(1, $page - floor($num_links / 2));
                        $end_page_link = min($total_pages, $start_page_link + $num_links - 1);

                        if ($start_page_link > 1) {
                            echo "<a href='?tabel=" . htmlspecialchars($tabel) . "&search_query=" . htmlspecialchars($search_query) . "&limit={$limit}&page=1' class='page-link'>1</a>";
                            if ($start_page_link > 2) {
                                echo "<span class='page-link disabled'>...</span>";
                            }
                        }

                        for ($p = $start_page_link; $p <= $end_page_link; $p++) {
                            echo "<a href='?tabel=" . htmlspecialchars($tabel) . "&search_query=" . htmlspecialchars($search_query) . "&limit={$limit}&page={$p}' class='page-link" . ($page == $p ? " active" : "") . "'>{$p}</a>";
                        }

                        if ($end_page_link < $total_pages) {
                            if ($end_page_link < $total_pages - 1) {
                                echo "<span class='page-link disabled'>...</span>";
                            }
                            echo "<a href='?tabel=" . htmlspecialchars($tabel) . "&search_query=" . htmlspecialchars($search_query) . "&limit={$limit}&page={$total_pages}' class='page-link'>{$total_pages}</a>";
                        }

                        // Tombol Next
                        if ($page < $total_pages) {
                            echo "<a href='?tabel=" . htmlspecialchars($tabel) . "&search_query=" . htmlspecialchars($search_query) . "&limit={$limit}&page=" . ($page + 1) . "' class='page-link'>&raquo;</a>";
                        } else {
                            echo "<span class='page-link disabled'>&raquo;</span>";
                        }

                        echo "<span class='pagination-results-info'>Showing " . $start_record_display . " to " . $end_record_display . " of " . $total_records . " results</span>";
                        echo "</div>";
                        // --- AKHIR PAGINASI LINK BARU ---

                    } else {
                        echo "<p class='alert-message alert-info'>Tidak ada data ditemukan untuk tabel ini" . (!empty($search_query) ? " dengan pencarian '" . htmlspecialchars($search_query) . "'" : "") . ".</p>";
                    }
                } else {
                    echo "<div class='alert-message alert-info'>Selamat datang di Admin Panel! Silakan pilih salah satu tabel dari menu samping untuk mulai mengelola data.</div>";
                }
                ?>
            </div>
        </div>
    </div>

<script>
    const sidebar = document.getElementById('sidebar');
    const contentWrapper = document.getElementById('content-wrapper');
    const menuToggleIcon = document.getElementById('menu-toggle-icon');
    const menuIcon = menuToggleIcon.querySelector('i[data-feather]');

    let isSidebarLockedOpen = false;
    let isMobile = window.innerWidth <= 768;

    function openSidebar() {
        sidebar.classList.add('sidebar-expanded');
        contentWrapper.classList.add('content-shifted');
        feather.replace();
    }

    function closeSidebar() {
        sidebar.classList.remove('sidebar-expanded');
        contentWrapper.classList.remove('content-shifted');
        feather.replace();
    }

    function setupDesktopHover() {
        menuToggleIcon.removeEventListener('click', toggleMobileSidebar);
        contentWrapper.removeEventListener('click', closeMobileSidebarOnClickOutside);

        sidebar.addEventListener('mouseenter', function() {
            if (!isSidebarLockedOpen) openSidebar();
        });
        sidebar.addEventListener('mouseleave', function() {
            if (!isSidebarLockedOpen) closeSidebar();
        });

        menuToggleIcon.setAttribute('data-feather', isSidebarLockedOpen ? 'lock' : 'unlock');
        feather.replace();
    }

    function toggleSidebarStateByClick() {
        if (isMobile) {
            if (sidebar.classList.contains('sidebar-expanded')) {
                closeSidebar();
                menuToggleIcon.classList.remove('active');
            } else {
                openSidebar();
                menuToggleIcon.classList.add('active');
            }
            isSidebarLockedOpen = sidebar.classList.contains('sidebar-expanded');
        } else {
            isSidebarLockedOpen = !isSidebarLockedOpen;

            if (isSidebarLockedOpen) {
                openSidebar();
                menuToggleIcon.setAttribute('data-feather', 'lock');
                sidebar.removeEventListener('mouseleave', closeSidebar);
            } else {
                closeSidebar();
                menuToggleIcon.setAttribute('data-feather', 'unlock');
                sidebar.addEventListener('mouseleave', closeSidebar);
            }
            feather.replace();
        }
    }

    function toggleMobileSidebar() {
        if (sidebar.classList.contains('sidebar-expanded')) {
            closeSidebar();
            menuToggleIcon.classList.remove('active');
        } else {
            openSidebar();
            menuToggleIcon.classList.add('active');
        }
    }

    function closeMobileSidebarOnClickOutside(event) {
        if (isMobile && sidebar.classList.contains('sidebar-expanded') && !sidebar.contains(event.target) && !menuToggleIcon.contains(event.target)) {
            closeSidebar();
            menuToggleIcon.classList.remove('active');
        }
    }

    function adjustLayoutOnResize() {
        const currentlyMobile = window.innerWidth <= 768;
        if (currentlyMobile !== isMobile) {
            isMobile = currentlyMobile;

            if (isMobile) {
                closeSidebar();
                isSidebarLockedOpen = false;
                menuToggleIcon.style.display = 'flex';

                sidebar.removeEventListener('mouseenter', openSidebar);
                sidebar.removeEventListener('mouseleave', closeSidebar);

                menuToggleIcon.addEventListener('click', toggleMobileSidebar);
                contentWrapper.addEventListener('click', closeMobileSidebarOnClickOutside);
            } else {
                closeSidebar();
                isSidebarLockedOpen = false;
                menuToggleIcon.style.display = 'none';

                menuToggleIcon.removeEventListener('click', toggleMobileSidebar);
                contentWrapper.removeEventListener('click', closeMobileSidebarOnClickOutside);

                setupDesktopHover();
            }
        }
        feather.replace();
    }

    window.addEventListener('resize', adjustLayoutOnResize);
    window.addEventListener('load', adjustLayoutOnResize);

    menuToggleIcon.addEventListener('click', toggleSidebarStateByClick);

    document.addEventListener('DOMContentLoaded', () => {
        feather.replace();

        const formatNominalInputs = document.querySelectorAll('.format-nominal');
        formatNominalInputs.forEach(input => {
            function formatNumberWithDots(num) {
                const cleanedNum = String(num).replace(/[^0-9-]/g, '');
                if (cleanedNum === '') return '';
                return parseInt(cleanedNum).toLocaleString('id-ID');
            }
            input.addEventListener('input', function() {
                this.value = formatNumberWithDots(this.value);
            });
            const form = input.closest('form');
            if (form) {
                form.addEventListener('submit', function() {
                    formatNominalInputs.forEach(inputToClean => {
                        inputToClean.value = inputToClean.value.replace(/[^0-9-]/g, '');
                    });
                });
            }
        });
    });
</script>

</body>
</html>