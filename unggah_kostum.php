<?php
session_name('penyedia_session');
session_start();
require_once 'koneksi.php';

// Setup error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

header('Content-Type: application/json');

try { 
    // Validasi input
    if (!isset($_POST['id']) || empty($_POST['id']) || !isset($_SESSION['id_user'])) {
        throw new Exception("Data tidak lengkap");
    }

    $kostumId = (int)$_POST['id'];
    $userId = (int)$_SESSION['id_user'];

    // Validasi koneksi database
    if (!$conn || $conn->connect_error) {
        throw new Exception("Koneksi database gagal: " . ($conn->connect_error ?? 'Unknown error'));
    }

    // Mulai transaksi
    $conn->begin_transaction();

    // 1. Verifikasi kepemilikan data dan ambil data kostum
    $checkQuery = "SELECT id, judul_post, status FROM form_katalog WHERE id = ? AND id_user = ? FOR UPDATE";
    $stmt = $conn->prepare($checkQuery);
    if (!$stmt) {
        throw new Exception("Prepare statement gagal: " . $conn->error);
    }

    $stmt->bind_param("ii", $kostumId, $userId);
    if (!$stmt->execute()) {
        throw new Exception("Query verifikasi gagal: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        throw new Exception("Data kostum tidak ditemukan atau tidak memiliki akses");
    }
    
    $kostumData = $result->fetch_assoc();
    $judulPost = $kostumData['judul_post'];
    $stmt->close();

    $updateQuery = "UPDATE form_katalog SET status = 'published' WHERE id = ?";
    $stmt = $conn->prepare($updateQuery);
    if (!$stmt) {
        throw new Exception("Prepare update gagal: " . $conn->error);
    }

    $stmt->bind_param("i", $kostumId);
    if (!$stmt->execute()) {
        throw new Exception("Update status gagal: " . $stmt->error);
    }
    $stmt->close();

    $checkPublishedQuery = "SELECT id_kostum FROM published_kostum WHERE id_kostum = ?";
    $stmt = $conn->prepare($checkPublishedQuery);
    if (!$stmt) {
        throw new Exception("Prepare check published gagal: " . $conn->error);
    }
    
    $stmt->bind_param("i", $kostumId);
    if (!$stmt->execute()) {
        throw new Exception("Check published gagal: " . $stmt->error);
    }

    $isAlreadyPublished = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    // Reset is_canceled ke 0 jika ada dan set published_at ke waktu sekarang
    $insertQuery = "INSERT INTO published_kostum (id_kostum, published_at) 
                   VALUES (?, NOW())
                   ON DUPLICATE KEY UPDATE 
                       published_at = NOW()";
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
    
    echo json_encode([
        'status' => 'success', 
        'message' => "Kostum '{$judulPost}' berhasil diunggah!",
        'kostum_id' => $kostumId
    ]);
    exit;
       
} catch (Exception $e) {
    // Rollback jika ada transaksi aktif
    if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) {
        $conn->rollback();
    }
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => $e->getMessage(),
    ]);
    exit;
}
?>