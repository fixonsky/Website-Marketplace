<?php
session_start();
require_once 'koneksi.php'; // Your database connection file

header('Content-Type: application/json');

if (!isset($_SESSION['id_user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$productId = $data['product_id'] ?? null;
$dates = $data['dates'] ?? [];
$name = $data['name'] ?? '';

if (!$productId || empty($dates) || empty($name)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
    exit;
}

try {
    // Begin transaction
    $conn->begin_transaction();
    
    // Prepare statement
    $stmt = $conn->prepare("INSERT INTO bookings (product_id, booking_date, booked_by) VALUES (?, ?, ?)");
    
    foreach ($dates as $date) {
        $stmt->bind_param("iss", $productId, $date, $name);
        $stmt->execute();
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$stmt->close();
$conn->close();
?>