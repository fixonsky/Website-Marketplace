<?php
require_once 'koneksi.php';

echo "<h2>Migrasi Data Kostum yang Sudah Ada</h2>";

try {
    $conn->begin_transaction();
    
    // Buat tabel stok_items jika belum ada
    $createStokItemsTable = "CREATE TABLE IF NOT EXISTS `stok_items` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `kostum_id` int(11) NOT NULL,
        `stok_id` varchar(10) NOT NULL,
        `status` enum('available','rented','maintenance','booked') NOT NULL DEFAULT 'available',
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_kostum_stok` (`kostum_id`, `stok_id`),
        KEY `idx_kostum_id` (`kostum_id`),
        KEY `idx_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $conn->query($createStokItemsTable);
    echo "✅ Tabel stok_items sudah ready<br>";
    
    // Ambil semua kostum yang sudah ada
    $getAllKostumQuery = "SELECT id, stok, judul_post FROM form_katalog WHERE stok > 0";
    $result = $conn->query($getAllKostumQuery);
    
    $totalProcessed = 0;
    
    while ($row = $result->fetch_assoc()) {
        $kostumId = $row['id'];
        $stok = (int)$row['stok'];
        $judulPost = $row['judul_post'];
        
        // Cek apakah sudah ada stok_items untuk kostum ini
        $checkQuery = "SELECT COUNT(*) as count FROM stok_items WHERE kostum_id = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param("i", $kostumId);
        $checkStmt->execute();
        $existing = $checkStmt->get_result()->fetch_assoc()['count'];
        
        if ($existing == 0 && $stok > 0) {
            // Buat stok_items berdasarkan stok di form_katalog
            for ($i = 1; $i <= $stok; $i++) {
                $stokId = str_pad($i, 3, '0', STR_PAD_LEFT);
                $insertStmt = $conn->prepare("INSERT INTO stok_items (kostum_id, stok_id, status) VALUES (?, ?, 'available')");
                $insertStmt->bind_param("is", $kostumId, $stokId);
                $insertStmt->execute();
            }
            echo "✅ Kostum '{$judulPost}' (ID: {$kostumId}): Dibuat {$stok} stok items<br>";
            $totalProcessed++;
        } else {
            echo "ℹ️ Kostum '{$judulPost}' (ID: {$kostumId}): Sudah ada {$existing} stok items<br>";
        }
    }
    
    $conn->commit();
    echo "<br><strong>Migrasi selesai! Total kostum yang diproses: {$totalProcessed}</strong>";
    
} catch (Exception $e) {
    $conn->rollback();
    echo "<br><strong style='color: red;'>Error: " . $e->getMessage() . "</strong>";
}

$conn->close();
?>