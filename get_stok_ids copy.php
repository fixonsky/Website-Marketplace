<?php

session_name('penyedia_session'); 
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['id_user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['kostum_id'])) {
    $kostumId = (int)$_GET['kostum_id'];
    
    try {
        // Ambil semua ID stok untuk kostum ini dengan status yang lebih detail
        $stmt = $conn->prepare("
            SELECT si.stok_id, si.status 
            FROM stok_items si 
            INNER JOIN form_katalog fc ON si.kostum_id = fc.id 
            WHERE si.kostum_id = ? AND fc.id_user = ? 
            ORDER BY CAST(si.stok_id AS UNSIGNED) ASC
        ");
        $stmt->bind_param("ii", $kostumId, $_SESSION['id_user']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $stokIds = [];
        while ($row = $result->fetch_assoc()) {
            // Konversi status untuk tampilan yang lebih user-friendly
            $displayStatus = $row['status'];
            switch ($row['status']) {
                case 'available':
                    $displayStatus = 'TERSEDIA';
                    break;
                case 'booked':
                    $displayStatus = 'DIPESAN';
                    break;
                case 'rented':
                    $displayStatus = 'SEDANG DISEWA';
                    break;
                case 'maintenance':
                    $displayStatus = 'DALAM PERAWATAN';
                    break;
                default:
                    $displayStatus = strtoupper($row['status']);
            }
            
            $stokIds[] = [
                'stok_id' => $row['stok_id'],
                'status' => $row['status'], // Status asli untuk logic
                'display_status' => $displayStatus // Status untuk tampilan
            ];
        }
        
        echo json_encode(['status' => 'success', 'data' => $stokIds]);
        
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Parameter tidak valid']);
}
?>