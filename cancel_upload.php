<?php
session_start();
header('Content-Type: application/json');

// Setup error reporting
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'cancel_upload_errors.log'); 

// Cek session
if (!isset($_SESSION['id_user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesi berakhir, silakan login kembali']);
    exit();
}

// Koneksi database
$conn = new mysqli('localhost', 'root', 'password123', 'daftar_akun');
if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'Koneksi database gagal']);
    exit();
}

try {
    // Validasi input
    if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
        http_response_code(400);
        throw new Exception('ID kostum tidak valid');
    }

    $kostumId = (int)$_POST['id'];
    $userId = (int)$_SESSION['id_user'];

    // Mulai transaksi
    $conn->begin_transaction();

    // 1. Verifikasi kepemilikan kostum
    $checkQuery = "SELECT f.id, f.status 
                  FROM form_katalog f
                  LEFT JOIN published_kostum p ON f.id = p.id_kostum
                  WHERE f.id = ? AND f.id_user = ?
                  FOR UPDATE";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("ii", $kostumId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        http_response_code(403);
        throw new Exception('Kostum tidak ditemukan atau bukan milik Anda');
    }
    
    $row = $result->fetch_assoc();
    if ($row['status'] !== 'published') {
        throw new Exception('Hanya kostum yang sudah dipublikasikan yang bisa dibatalkan');
    }
    $stmt->close();

    // 2. Update status is_canceled di published_kostum
    // Cek apakah record sudah ada
    $checkPublished = "SELECT id_kostum FROM published_kostum WHERE id_kostum = ?";
    $stmt = $conn->prepare($checkPublished);
    $stmt->bind_param("i", $kostumId);
    $stmt->execute();
    $publishedResult = $stmt->get_result();
    $publishedExists = $publishedResult->num_rows > 0;
    $stmt->close();
    
    if ($publishedExists) {
        $updateQuery = "UPDATE published_kostum SET is_canceled = 1, published_at = NOW() WHERE id_kostum = ?";
    } else {
        $updateQuery = "INSERT INTO published_kostum (id_kostum, is_canceled, published_at) VALUES (?, 1, NOW())";
    }
    
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("i", $kostumId);
    
    if (!$stmt->execute()) {
        throw new Exception('Gagal mengupdate status canceled: ' . $stmt->error);
    }
    $stmt->close();

    $updateStatusQuery = "UPDATE form_katalog SET status = 'draft' WHERE id = ?";
    $stmt = $conn->prepare($updateStatusQuery);
    $stmt->bind_param("i", $kostumId);
    if (!$stmt->execute()) {
        throw new Exception('Gagal mengupdate status kostum: ' . $stmt->error);
    }
    $stmt->close();

    // Commit transaksi
    $conn->commit();

    $timeQuery = "SELECT published_at FROM published_kostum WHERE id_kostum = ?";
    $stmt = $conn->prepare($timeQuery);
    $stmt->bind_param("i", $kostumId);
    $stmt->execute();
    $timeResult = $stmt->get_result();
    $publishedAt = $timeResult->fetch_assoc()['published_at'];
    $stmt->close();

    echo json_encode([
        'status' => 'success', 
        'message' => 'Unggahan berhasil dibatalkan',
        'kostum_id' => $kostumId,
        'published_at' => $publishedAt,
        'is_canceled' => true
    ]);

} catch (Exception $e) {
    // Rollback jika ada transaksi aktif
    if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) {
        $conn->rollback();
    }
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($stmt)) $stmt->close();
    if (isset($conn)) $conn->close();
}
?>