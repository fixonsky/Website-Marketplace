<?php
session_start();
header('Content-Type: application/json');
require_once 'koneksi.php';

// Validasi session dan input
if (!isset($_SESSION['id_user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if (!isset($_POST['product_id'], $_POST['date'], $_POST['action'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$productId = (int)$_POST['product_id'];
$date = $_POST['date'];
$action = $_POST['action'];

try {
    // Validasi format tanggal
    $dateObj = new DateTime($date);
    $dateFormatted = $dateObj->format('Y-m-d');
    
    if ($action === 'close') {
        // Tambahkan ke daftar tanggal yang tidak tersedia
        $query = "INSERT INTO unavailable_dates (product_id, date) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE date = VALUES(date)";
    } else {
        // Hapus dari daftar tanggal yang tidak tersedia
        $query = "DELETE FROM unavailable_dates WHERE product_id = ? AND date = ?";
    }
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("is", $productId, $dateFormatted);
    $stmt->execute();
    
    echo json_encode(['status' => 'success']);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>