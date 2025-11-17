<?php

session_name('penyedia_session');
session_start();
require_once 'koneksi.php';

header('Content-Type: application/json');

if (!isset($_SESSION['id_user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kostumId = (int)$_POST['kostum_id'];
    $stokId = $_POST['stok_id'];
    
    try {
        $conn->begin_transaction();
        
        // Debug log
        error_log("Processing mark_single_ready: kostum_id=$kostumId, stok_id=$stokId, user_id=" . $_SESSION['id_user']);
        
        // Verify kostum belongs to user
        $verifyQuery = "SELECT id, stok FROM form_katalog WHERE id = ? AND id_user = ?";
        $verifyStmt = $conn->prepare($verifyQuery);
        $verifyStmt->bind_param("ii", $kostumId, $_SESSION['id_user']);
        $verifyStmt->execute();
        $verifyResult = $verifyStmt->get_result();
        
        if ($verifyResult->num_rows === 0) {
            throw new Exception('Kostum tidak ditemukan atau Anda tidak memiliki akses');
        }
        
        $kostumData = $verifyResult->fetch_assoc();
        $currentStok = (int)$kostumData['stok'];
        
        // Check if item exists and is in maintenance status
        $checkQuery = "SELECT stok_id, status FROM stok_items WHERE kostum_id = ? AND stok_id = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param("is", $kostumId, $stokId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows === 0) {
            throw new Exception("Stok ID {$stokId} tidak ditemukan untuk kostum ini");
        }
        
        $itemData = $checkResult->fetch_assoc();
        if ($itemData['status'] !== 'maintenance') {
            throw new Exception("Stok ID {$stokId} tidak dalam status perawatan (status saat ini: " . $itemData['status'] . ")");
        }
        
        // Update specific maintenance item to available
        $updateQuery = "UPDATE stok_items 
                        SET status = 'available' 
                        WHERE kostum_id = ? AND stok_id = ? AND status = 'maintenance'";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param("is", $kostumId, $stokId);
        $updateStmt->execute();
        
        if ($updateStmt->affected_rows === 0) {
            throw new Exception('Gagal mengupdate status item');
        }
        
        // Update stok di form_katalog (tambah 1) - PERBAIKAN: INI YANG MENGEMBALIKAN STOK KE INDEX PENYEWA
        $newStok = $currentStok + 1;
        $updateStokQuery = "UPDATE form_katalog SET stok = ? WHERE id = ?";
        $updateStokStmt = $conn->prepare($updateStokQuery);
        $updateStokStmt->bind_param("ii", $newStok, $kostumId);
        
        if (!$updateStokStmt->execute()) {
            throw new Exception('Gagal mengupdate stok di katalog');
        }
        
        $conn->commit();
        
        error_log("Successfully marked stok_id $stokId as ready for rent. Stock increased from $currentStok to $newStok");
        
        echo json_encode([
            'status' => 'success', 
            'message' => "Kostum ID {$stokId} berhasil ditandai siap untuk disewa",
            'new_stock' => $newStok
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error in mark_single_ready: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
}
?>