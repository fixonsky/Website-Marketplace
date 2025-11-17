<?php
session_name('penyedia_session');
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['id_user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'update_stok';
    
    if ($action === 'mark_ready') {
        // Fungsi untuk menandai kostum siap disewa
        $kostum_id = $_POST['kostum_id'];
        $stok_id = $_POST['stok_id'];
        
        try {
            $conn->begin_transaction();
            
            // Update status dari maintenance ke available
            $stmt = $conn->prepare("UPDATE stok_items SET status = 'available' WHERE kostum_id = ? AND stok_id = ?");
            $stmt->bind_param("is", $kostum_id, $stok_id);
            $stmt->execute();
            
            if ($stmt->affected_rows > 0) {
                // Update stok di form_katalog berdasarkan jumlah available
                $countAvailableQuery = "SELECT COUNT(*) as available_count FROM stok_items WHERE kostum_id = ? AND status = 'available'";
                $countStmt = $conn->prepare($countAvailableQuery);
                $countStmt->bind_param("i", $kostum_id);
                $countStmt->execute();
                $availableCount = $countStmt->get_result()->fetch_assoc()['available_count'];
                
                $updateStokQuery = "UPDATE form_katalog SET stok = ? WHERE id = ? AND id_user = ?";
                $updateStmt = $conn->prepare($updateStokQuery);
                $updateStmt->bind_param("iii", $availableCount, $kostum_id, $_SESSION['id_user']);
                $updateStmt->execute();
                
                $conn->commit();
                echo json_encode(['status' => 'success', 'message' => 'Kostum berhasil ditandai siap disewa', 'new_stock' => $availableCount]);
            } else {
                throw new Exception('Gagal mengupdate status kostum');
            }
            
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan: ' . $e->getMessage()]);
        }
        exit;
    }
    
    // PERBAIKAN UTAMA: Update stok manual oleh penyedia
    if (!isset($_POST['id']) || !isset($_POST['stok'])) {
        echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap']);
        exit;
    }
    
    $id = $_POST['id'];
    $stok_tersedia_baru = (int)$_POST['stok'];
    
    // Validasi input
    if ($stok_tersedia_baru < 0) {
        echo json_encode(['status' => 'error', 'message' => 'Stok tidak boleh negatif']);
        exit;
    }
    
    try {
        $conn->begin_transaction();
        
        // PERBAIKAN: Validasi kepemilikan kostum
        $checkOwnership = $conn->prepare("SELECT id, stok FROM form_katalog WHERE id = ? AND id_user = ?");
        $checkOwnership->bind_param("ii", $id, $_SESSION['id_user']);
        $checkOwnership->execute();
        $ownershipResult = $checkOwnership->get_result();
        
        if ($ownershipResult->num_rows === 0) {
            throw new Exception('Kostum tidak ditemukan atau tidak memiliki akses');
        }
        
        $kostum_data = $ownershipResult->fetch_assoc();
        $stok_sekarang = $kostum_data['stok'];
        
        // PERBAIKAN: Hitung stok yang sedang rented (tidak boleh diubah)
        $countRentedQuery = "SELECT COUNT(*) as rented_count FROM stok_items WHERE kostum_id = ? AND status = 'rented'";
        $countRentedStmt = $conn->prepare($countRentedQuery);
        $countRentedStmt->bind_param("i", $id);
        $countRentedStmt->execute();
        $rentedCount = $countRentedStmt->get_result()->fetch_assoc()['rented_count'];
        
        // PERBAIKAN: Validasi stok baru tidak boleh kurang dari yang sedang disewa
        if ($stok_tersedia_baru < $rentedCount) {
            throw new Exception("Stok tidak boleh kurang dari jumlah yang sedang disewa ($rentedCount item)");
        }
        
        // PERBAIKAN: Logika yang lebih sederhana - langsung update form_katalog
        $updateStokQuery = "UPDATE form_katalog SET stok = ? WHERE id = ? AND id_user = ?";
        $updateStmt = $conn->prepare($updateStokQuery);
        $updateStmt->bind_param("iii", $stok_tersedia_baru, $id, $_SESSION['id_user']);
        
        if (!$updateStmt->execute()) {
            throw new Exception('Gagal mengupdate stok di katalog: ' . $conn->error);
        }
        
        // PERBAIKAN: Sinkronisasi stok_items dengan stok baru
        $getCurrentStokItemsQuery = "SELECT COUNT(*) as total_items FROM stok_items WHERE kostum_id = ?";
        $getStmt = $conn->prepare($getCurrentStokItemsQuery);
        $getStmt->bind_param("i", $id);
        $getStmt->execute();
        $currentStokItems = $getStmt->get_result()->fetch_assoc()['total_items'];
        
        $selisih = $stok_tersedia_baru - $currentStokItems;
        
        if ($selisih > 0) {
            // Tambah stok_items baru
            $getMaxStokIdQuery = "SELECT COALESCE(MAX(CAST(stok_id AS UNSIGNED)), 0) as max_stok_id FROM stok_items WHERE kostum_id = ?";
            $maxStmt = $conn->prepare($getMaxStokIdQuery);
            $maxStmt->bind_param("i", $id);
            $maxStmt->execute();
            $maxStokId = $maxStmt->get_result()->fetch_assoc()['max_stok_id'];
            
            for ($i = 1; $i <= $selisih; $i++) {
                $newStokId = str_pad($maxStokId + $i, 3, '0', STR_PAD_LEFT);
                $insertStmt = $conn->prepare("INSERT INTO stok_items (kostum_id, stok_id, status) VALUES (?, ?, 'available')");
                $insertStmt->bind_param("is", $id, $newStokId);
                if (!$insertStmt->execute()) {
                    throw new Exception('Gagal menambah stok baru');
                }
            }
        } elseif ($selisih < 0) {
            // Kurangi stok_items (hapus yang maintenance terlebih dahulu, lalu available)
            $jumlahKurang = abs($selisih);
            
            // Hapus yang status maintenance dulu
            $deleteMaintenanceStmt = $conn->prepare("DELETE FROM stok_items WHERE kostum_id = ? AND status = 'maintenance' LIMIT ?");
            $deleteMaintenanceStmt->bind_param("ii", $id, $jumlahKurang);
            $deleteMaintenanceStmt->execute();
            $deletedMaintenance = $deleteMaintenanceStmt->affected_rows;
            
            // Jika masih kurang, hapus yang available
            $remaining = $jumlahKurang - $deletedMaintenance;
            if ($remaining > 0) {
                $deleteAvailableStmt = $conn->prepare("DELETE FROM stok_items WHERE kostum_id = ? AND status = 'available' ORDER BY CAST(stok_id AS UNSIGNED) DESC LIMIT ?");
                $deleteAvailableStmt->bind_param("ii", $id, $remaining);
                $deleteAvailableStmt->execute();
            }
        }
        
        $conn->commit();
        echo json_encode([
            'status' => 'success', 
            'message' => 'Stok berhasil diupdate',
            'stok_sebelumnya' => $stok_sekarang,
            'stok_sekarang' => $stok_tersedia_baru
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Update stok error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Method tidak diizinkan']);
}
?>