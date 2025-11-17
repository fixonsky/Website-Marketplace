<?php
require_once 'koneksi.php';

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

if ($conn->query($createStokItemsTable)) {
    echo "Tabel stok_items berhasil dibuat atau sudah ada.\n";
} else {
    echo "Error: " . $conn->error . "\n";
}

// Pastikan kolom status_item ada di transaksi_items
$checkStatusItemColumn = $conn->query("SHOW COLUMNS FROM transaksi_items LIKE 'status_item'");
if ($checkStatusItemColumn->num_rows == 0) {
    $addStatusItemColumn = "ALTER TABLE transaksi_items ADD COLUMN status_item VARCHAR(50) DEFAULT NULL AFTER tanggal_peminjaman";
    if ($conn->query($addStatusItemColumn)) {
        echo "Kolom status_item berhasil ditambahkan ke transaksi_items.\n";
    } else {
        echo "Error menambahkan kolom status_item: " . $conn->error . "\n";
    }
}

$conn->close();
?>