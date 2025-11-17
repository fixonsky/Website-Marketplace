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
        // Fungsi baru untuk menandai kostum siap disewa
        $kostum_id = $_POST['kostum_id'];
        $stok_id = $_POST['stok_id'];
        
        try {
            $conn->begin_transaction();
            
            // Update status dari maintenance ke available
            $stmt = $conn->prepare("UPDATE stok_items SET status = 'available' WHERE kostum_id = ? AND stok_id = ?");
            $stmt->bind_param("is", $kostum_id, $stok_id);
            $stmt->execute();
            
            if ($stmt->affected_rows > 0) {
                // Update stok di form_katalog (tambah 1)
                $updateStokQuery = "UPDATE form_katalog SET stok = stok + 1 WHERE id = ? AND id_user = ?";
                $updateStmt = $conn->prepare($updateStokQuery);
                $updateStmt->bind_param("ii", $kostum_id, $_SESSION['id_user']);
                $updateStmt->execute();
                
                $conn->commit();
                echo json_encode(['status' => 'success', 'message' => 'Kostum berhasil ditandai siap disewa']);
            } else {
                throw new Exception('Gagal mengupdate status kostum');
            }
            
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan: ' . $e->getMessage()]);
        }
        exit;
    }
    
    // Kode update stok yang sudah ada tetap sama
    $id = $_POST['id'];
    $stok = (int)$_POST['stok'];
    
    // Validasi input
    if ($stok < 0) {
        echo json_encode(['status' => 'error', 'message' => 'Stok tidak boleh negatif']);
        exit;
    }
    
    try {
        $conn->begin_transaction();
        
        // Ambil stok lama
        $stmt = $conn->prepare("SELECT stok FROM form_katalog WHERE id = ? AND id_user = ?");
        $stmt->bind_param("ii", $id, $_SESSION['id_user']);
        $stmt->execute();
        $result = $stmt->get_result();
        $oldData = $result->fetch_assoc();
        
        if (!$oldData) {
            throw new Exception('Data kostum tidak ditemukan');
        }
        
        $oldStok = (int)$oldData['stok'];
        
        // Update stok di form_katalog
        $stmt = $conn->prepare("UPDATE form_katalog SET stok = ? WHERE id = ? AND id_user = ?");
        $stmt->bind_param("iii", $stok, $id, $_SESSION['id_user']);
        $stmt->execute();
        
        if ($stmt->affected_rows === 0) {
            throw new Exception('Gagal mengupdate stok');
        }
        
        // Kelola ID stok individual
        if ($stok > $oldStok) {
            // Tambah stok - buat ID baru
            for ($i = $oldStok + 1; $i <= $stok; $i++) {
                $stokId = str_pad($i, 3, '0', STR_PAD_LEFT);
                $insertStmt = $conn->prepare("INSERT INTO stok_items (kostum_id, stok_id, status) VALUES (?, ?, 'available')");
                $insertStmt->bind_param("is", $id, $stokId);
                $insertStmt->execute();
            }
        } elseif ($stok < $oldStok) {
            // Kurangi stok - hapus ID tertinggi yang available
            $deleteStmt = $conn->prepare("DELETE FROM stok_items WHERE kostum_id = ? AND status = 'available' ORDER BY CAST(stok_id AS UNSIGNED) DESC LIMIT ?");
            $deleteCount = $oldStok - $stok;
            $deleteStmt->bind_param("ii", $id, $deleteCount);
            $deleteStmt->execute();
        }
        
        $conn->commit();
        echo json_encode(['status' => 'success']);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Method tidak diizinkan']);
}
?>