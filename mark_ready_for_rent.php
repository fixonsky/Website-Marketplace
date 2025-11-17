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
    
    try {
        $conn->begin_transaction();
        
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
        
        // Hitung berapa item yang dalam maintenance
        $countMaintenanceQuery = "SELECT COUNT(*) as maintenance_count FROM stok_items WHERE kostum_id = ? AND status = 'maintenance'";
        $countStmt = $conn->prepare($countMaintenanceQuery);
        $countStmt->bind_param("i", $kostumId);
        $countStmt->execute();
        $maintenanceCount = $countStmt->get_result()->fetch_assoc()['maintenance_count'];
        
        if ($maintenanceCount == 0) {
            throw new Exception('Tidak ada kostum dalam perawatan');
        }
        
        // Update all maintenance items to available
        $updateQuery = "UPDATE stok_items 
                        SET status = 'available' 
                        WHERE kostum_id = ? AND status = 'maintenance'";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param("i", $kostumId);
        $updateStmt->execute();
        
        $updatedCount = $updateStmt->affected_rows;
        
        // PERBAIKAN: UPDATE STOK DI FORM_KATALOG - INI YANG MENGEMBALIKAN STOK KE INDEX PENYEWA
        $newStok = $currentStok + $updatedCount;
        $updateStokQuery = "UPDATE form_katalog SET stok = ? WHERE id = ?";
        $updateStokStmt = $conn->prepare($updateStokQuery);
        $updateStokStmt->bind_param("ii", $newStok, $kostumId);
        
        if (!$updateStokStmt->execute()) {
            throw new Exception('Gagal mengupdate stok di katalog');
        }
        
        $conn->commit();
        
        error_log("Successfully marked all maintenance items as ready for rent. Stock increased from $currentStok to $newStok");
        
        echo json_encode([
            'status' => 'success', 
            'message' => "{$updatedCount} kostum berhasil ditandai siap untuk disewa",
            'updated_count' => $updatedCount,
            'new_stock' => $newStok
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error in mark_ready_for_rent: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
}
?>