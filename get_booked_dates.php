<?php
session_start();
require_once 'koneksi.php';

if (ob_get_level()) ob_end_clean();

header('Content-Type: application/json');

// Enable error reporting
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

try {
    // Validasi product_id
    $productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
    if ($productId <= 0) {
        throw new Exception("ID Produk tidak valid");
    }

    // Verifikasi produk
    $checkStmt = $conn->prepare("SELECT id FROM form_katalog WHERE id = ?");
    $checkStmt->bind_param("i", $productId);
    $checkStmt->execute();

    if ($checkStmt->errno) {
        throw new Exception("Error memverifikasi produk: " . $checkStmt->error);
    }

    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows === 0) {
        throw new Exception("Produk tidak ditemukan");
    }

    // Ambil data tanggal yang sudah dibooking
    $stmt = $conn->prepare("SELECT date, booked_by FROM unavailable_dates WHERE product_id = ?");
    $stmt->bind_param("i", $productId);
    $stmt->execute();

    if ($stmt->errno) {
        throw new Exception("Error mengambil data booking: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $bookedDates = [];
    
    while ($row = $result->fetch_assoc()) {
        $bookedDates[] = [
            'date' => $row['date'],
            'booked_by' => $row['booked_by'] ?? '',

        ];
    }
    
    echo json_encode([
        'status' => 'success', 
        'booked_dates' => $bookedDates // Perbaikan: gunakan booked_dates bukan dates
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error', 
        'message' => $e->getMessage()
    ]);
    error_log("Error in get_booked_dates.php: " . $e->getMessage());
} finally {
    if (isset($checkStmt)) $checkStmt->close();
    if (isset($stmt)) $stmt->close();
    $conn->close();
    exit;
}
?>
