<?php
// process_quiz.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['question_id']) && isset($_POST['answer'])) {
    $question_id = $_POST['question_id'];
    $user_answer = strtoupper($_POST['answer']);

    $stmt = $conn->prepare("SELECT jawaban_benar FROM pertanyaan_kuis WHERE id = ?");
    $stmt->bind_param("i", $question_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $correct_answer = strtoupper($row['jawaban_benar']);

        $is_correct = ($user_answer === $correct_answer);

        $stmt_history = $conn->prepare("
            INSERT IGNORE INTO riwayat_kuis_pengguna (user_id, pertanyaan_id, jawaban_pengguna, is_correct)
            VALUES (?, ?, ?, ?)
        ");
        $stmt_history->bind_param("iisb", $user_id, $question_id, $user_answer, $is_correct);
        $stmt_history->execute();

        if ($stmt_history->affected_rows > 0) {
            if ($is_correct) {
                $conn->begin_transaction(); // Mulai transaksi
                try {
                    // Update koin di tabel users
                    $stmt_update_coins = $conn->prepare("UPDATE users SET koin = koin + 1 WHERE id = ?");
                    $stmt_update_coins->bind_param("i", $user_id);
                    $stmt_update_coins->execute();
                    $stmt_update_coins->close();

                    // CATAT TRANSAKSI KOIN KE TABEL BARU
                    $description = "Jawaban benar pertanyaan ID: " . $question_id;
                    $stmt_log_coin = $conn->prepare("INSERT INTO transaksi_koin (user_id, tipe_transaksi, jumlah_koin, deskripsi) VALUES (?, ?, ?, ?)");
                    $tipe_transaksi = 'penambahan_kuis';
                    $jumlah_koin_add = 1;
                    $stmt_log_coin->bind_param("isis", $user_id, $tipe_transaksi, $jumlah_koin_add, $description);
                    $stmt_log_coin->execute();
                    $stmt_log_coin->close();

                    $conn->commit(); // Commit transaksi jika berhasil
                    $_SESSION['user_coins'] = ($_SESSION['user_coins'] ?? 0) + 1;
                    $_SESSION['quiz_message'] = "Jawaban Anda benar! Anda mendapatkan 1 koin.";
                    $_SESSION['quiz_message_type'] = "success";
                } catch (Exception $e) {
                    $conn->rollback(); // Rollback jika ada error
                    $_SESSION['quiz_message'] = "Terjadi kesalahan saat memproses koin: " . $e->getMessage();
                    $_SESSION['quiz_message_type'] = "error";
                }
            } else {
                $_SESSION['quiz_message'] = "Jawaban Anda salah.";
                $_SESSION['quiz_message_type'] = "error";
            }
        } else {
            $_SESSION['quiz_message'] = "Anda sudah menjawab pertanyaan ini sebelumnya.";
            $_SESSION['quiz_message_type'] = "info";
        }
        $stmt_history->close();
    } else {
        $_SESSION['quiz_message'] = "Pertanyaan tidak ditemukan.";
        $_SESSION['quiz_message_type'] = "error";
    }

    $stmt->close();
} else {
    $_SESSION['quiz_message'] = "Permintaan tidak valid.";
    $_SESSION['quiz_message_type'] = "error";
}

$conn->close();
header('Location: quiz.php');
exit();
?>