<?php

session_name('penyedia_session');
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['id_user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if (isset($_POST['kostum_id'])) {
    $kostumId = (int)$_POST['kostum_id'];
    $userId = $_SESSION['id_user'];
    
    try {
        $conn->begin_transaction();
        
        // Verifikasi kostum milik user
        $verifyQuery = "SELECT stok FROM form_katalog WHERE id = ? AND id_user = ?";
        $verifyStmt = $conn->prepare($verifyQuery);
        $verifyStmt->bind_param("ii", $kostumId, $userId);
        $verifyStmt->execute();
        $verifyResult = $verifyStmt->get_result();
        
        if ($verifyResult->num_rows === 0) {
            throw new Exception('Kostum tidak ditemukan atau tidak memiliki akses');
        }
        
        $kostumData = $verifyResult->fetch_assoc();
        $currentStok = (int)$kostumData['stok'];
        
        // Cek berapa banyak stok ID yang sudah ada
        $existingQuery = "SELECT COUNT(*) as existing_count FROM stok_items WHERE kostum_id = ?";
        $existingStmt = $conn->prepare($existingQuery);
        $existingStmt->bind_param("i", $kostumId);
        $existingStmt->execute();
        $existingResult = $existingStmt->get_result();
        $existingCount = $existingResult->fetch_assoc()['existing_count'];
        
        // Generate ID untuk stok yang belum ada
        if ($currentStok > $existingCount) {
            for ($i = $existingCount + 1; $i <= $currentStok; $i++) {
                $stokId = str_pad($i, 3, '0', STR_PAD_LEFT);
                $insertStmt = $conn->prepare("INSERT INTO stok_items (kostum_id, stok_id, status) VALUES (?, ?, 'available')");
                $insertStmt->bind_param("is", $kostumId, $stokId);
                $insertStmt->execute();
            }
        }
        
        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Stok ID berhasil digenerate']);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Kostum ID required']);
}
?>