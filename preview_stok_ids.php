<?php

session_name('penyedia_session');
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['id_user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if (!isset($_POST['kostum_id']) || !isset($_POST['new_stok'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing parameters']);
    exit;
}

$kostumId = (int)$_POST['kostum_id'];
$newStok = (int)$_POST['new_stok'];
$userId = $_SESSION['id_user'];

try {
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
    
    // Ambil existing stok items dengan informasi detail
    $existingQuery = "SELECT 
                        si.stok_id, 
                        si.status, 
                        si.created_at,
                        CASE 
                            WHEN si.status = 'rented' THEN 
                                CONCAT(
                                    COALESCE(u.username, 'Penyewa'), 
                                    ' (', 
                                    DATE_FORMAT(pm.tanggal_mulai, '%d/%m/%y'), 
                                    ' - ', 
                                    DATE_FORMAT(pm.tanggal_selesai, '%d/%m/%y'), 
                                    ')'
                                )
                            ELSE NULL 
                        END as renter_info,
                        CASE 
                            WHEN si.status = 'rented' THEN 
                                CONCAT(
                                    DATE_FORMAT(pm.tanggal_mulai, '%d %b %Y'), 
                                    ' - ', 
                                    DATE_FORMAT(pm.tanggal_selesai, '%d %b %Y')
                                )
                            ELSE NULL 
                        END as rental_dates
                      FROM stok_items si 
                      LEFT JOIN peminjaman pm ON si.kostum_id = pm.id_produk AND si.status = 'rented'
                      LEFT JOIN pesanan p ON pm.id_pesanan = p.id_pesanan
                      LEFT JOIN daftar_akun u ON p.id_penyewa = u.id
                      WHERE si.kostum_id = ? 
                      ORDER BY CAST(si.stok_id AS UNSIGNED) ASC";
    $existingStmt = $conn->prepare($existingQuery);
    $existingStmt->bind_param("i", $kostumId);
    $existingStmt->execute();
    $existingResult = $existingStmt->get_result();
    
    $existingItems = [];
    while ($row = $existingResult->fetch_assoc()) {
        $existingItems[] = $row;
    }
    
    $existingCount = count($existingItems);
    $previewData = [
        'existing' => [],
        'new' => [],
        'willBeRemoved' => []
    ];
    
    if ($newStok > $existingCount) {
        // Akan menambah stok
        $previewData['existing'] = $existingItems;
        
        // Generate new IDs
        for ($i = $existingCount + 1; $i <= $newStok; $i++) {
            $newStokId = str_pad($i, 3, '0', STR_PAD_LEFT);
            $previewData['new'][] = [
                'stok_id' => $newStokId,
                'status' => 'available',
                'renter_info' => null,
                'rental_dates' => null
            ];
        }
        
    } elseif ($newStok < $existingCount) {
        // Akan mengurangi stok
        $previewData['existing'] = array_slice($existingItems, 0, $newStok);
        
        // Items yang akan dihapus (dari yang tertinggi)
        $itemsToRemove = array_slice($existingItems, $newStok);
        foreach ($itemsToRemove as $index => $item) {
            $item['original_number'] = $newStok + $index + 1;
            $previewData['willBeRemoved'][] = $item;
        }
        
    } else {
        // Stok sama, tidak ada perubahan
        $previewData['existing'] = $existingItems;
    }
    
    echo json_encode([
        'status' => 'success', 
        'data' => $previewData
    ]);
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>