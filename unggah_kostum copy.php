<?php
session_start();
require_once 'koneksi.php';

// Setup error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0); // Nonaktifkan display error di production
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

// Setup logging
$logFile = 'unggah_kostum.log';
$logMessage = function($message) use ($logFile) {
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $message . PHP_EOL, FILE_APPEND);
};

// Pastikan tidak ada output sebelum header
if (ob_get_length()) ob_clean();

try {
    $logMessage("========== Memulai Proses Unggah ==========");
    
    // Validasi input
    if (!isset($_POST['id']) || empty($_POST['id']) || !isset($_SESSION['id_user'])) {
        throw new Exception("Data tidak lengkap");
    }

    $kostumId = (int)$_POST['id'];
    $userId = (int)$_SESSION['id_user'];
    $logMessage("Mengunggah kostum ID: $kostumId untuk user ID: $userId");

    // Validasi koneksi database
    if (!$conn || $conn->connect_error) {
        throw new Exception("Koneksi database gagal: " . ($conn->connect_error ?? 'Unknown error'));
    }

    // Mulai transaksi
    $conn->begin_transaction();

        // 1. Verifikasi kepemilikan data (versi aman jika kolom status belum ada)
    $checkQuery = "SELECT id FROM form_katalog WHERE id = ? AND id_user = ?";
    $stmt = $conn->prepare($checkQuery);
    if (!$stmt) {
        throw new Exception("Prepare statement gagal: " . $conn->error);
    }

    $stmt->bind_param("ii", $kostumId, $userId);
    if (!$stmt->execute()) {
        // Cek jika error karena kolom tidak ada
        if ($conn->errno == 1054) { // Error code for unknown column
            // Coba query tanpa kolom status
            $checkQuery = "SELECT id FROM form_katalog WHERE id = ? AND id_user = ?";
            $stmt = $conn->prepare($checkQuery);
            if (!$stmt) {
                throw new Exception("Prepare statement gagal: " . $conn->error);
            }
            $stmt->bind_param("ii", $kostumId, $userId);
            if (!$stmt->execute()) {
                throw new Exception("Query verifikasi gagal: " . $stmt->error);
            }
        } else {
            throw new Exception("Query verifikasi gagal: " . $stmt->error);
        }
    }
    
    $stmt->store_result();
    if ($stmt->num_rows === 0) {
        throw new Exception("Data kostum tidak ditemukan atau sudah dipublikasikan");
    }
    $stmt->close();

    // 2. Update status menjadi published
    $updateQuery = "UPDATE form_katalog SET status = 'published' WHERE id = ? AND id_user = ?";
    $stmt = $conn->prepare($updateQuery);
    if (!$stmt) {
        throw new Exception("Prepare update gagal: " . $conn->error);
    }
    
    $stmt->bind_param("ii", $kostumId, $userId);
    if (!$stmt->execute()) {
        throw new Exception("Update status gagal: " . $stmt->error);
    }
    
    $affectedRows = $stmt->affected_rows;
    $stmt->close();

    // 3. Masukkan ke tabel published_kostum
    $insertQuery = "INSERT INTO published_kostum (id_kostum, published_at) VALUES (?, NOW()) 
                   ON DUPLICATE KEY UPDATE published_at = NOW()";
    $stmt = $conn->prepare($insertQuery);
    if (!$stmt) {
        throw new Exception("Prepare insert gagal: " . $conn->error);
    }
    
    $stmt->bind_param("i", $kostumId);
    if (!$stmt->execute()) {
        throw new Exception("Publish kostum gagal: " . $stmt->error);
    }
    $stmt->close();

    $conn->commit();
    
    // Response sukses
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success', 
        'message' => 'Kostum berhasil diunggah!',
        'affected_rows' => $affectedRows
    ]);
    exit;
    
} catch (Exception $e) {
    // Rollback jika ada transaksi aktif
    if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) {
        $conn->rollback();
    }
    
    $errorMsg = $e->getMessage();
    $logMessage("ERROR: " . $errorMsg);
    
    // Response error
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => $errorMsg,
        'error_details' => $e->getTraceAsString()
    ]);
    exit;
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
    $logMessage("========== Akhir Proses Unggah ==========\n");
}
?>