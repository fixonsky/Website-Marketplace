<?php
session_name('penyedia_session');
session_start();
require_once 'koneksi.php';

header('Content-Type: application/json');

if (!isset($_SESSION['id_user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$kostum_id = $_GET['kostum_id'] ?? null;

if (!$kostum_id) {
    echo json_encode(['status' => 'error', 'message' => 'Kostum ID required']);
    exit;
}

try {
    // PERBAIKAN: Validasi kepemilikan kostum
    $checkOwnership = $conn->prepare("SELECT id FROM form_katalog WHERE id = ? AND id_user = ?");
    $checkOwnership->bind_param("ii", $kostum_id, $_SESSION['id_user']);
    $checkOwnership->execute();
    $ownershipResult = $checkOwnership->get_result();
    
    if ($ownershipResult->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Kostum tidak ditemukan atau tidak memiliki akses']);
        exit;
    }

    // PERBAIKAN: Query yang lebih sederhana dan efektif
    $query = "SELECT 
                si.stok_id,
                si.status,
                CASE 
                    WHEN si.status = 'rented' THEN 'SEDANG DISEWA'
                    WHEN si.status = 'available' THEN 'TERSEDIA'
                    WHEN si.status = 'maintenance' THEN 'DALAM PERAWATAN'
                    WHEN si.status = 'booked' THEN 'DIPESAN'
                    ELSE si.status
                END as display_status
            FROM stok_items si 
            WHERE si.kostum_id = ? 
            ORDER BY CAST(si.stok_id AS UNSIGNED) ASC";
            
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $kostum_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $stok_data = [];
    while ($row = $result->fetch_assoc()) {
        $stok_data[] = [
            'stok_id' => $row['stok_id'],
            'status' => $row['status'],
            'display_status' => $row['display_status']
        ];
    }
    
    echo json_encode(['status' => 'success', 'data' => $stok_data]);
    
} catch (Exception $e) {
    error_log("Get stok IDs error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan saat mengambil data stok']);
}
?>