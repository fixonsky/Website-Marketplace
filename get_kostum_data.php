<?php
session_name('penyedia_session');
session_start();
require_once 'koneksi.php';

header('Content-Type: application/json');

if (!isset($_SESSION['id_user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$id = $_GET['id'] ?? null;

if (!$id) {
    echo json_encode(['status' => 'error', 'message' => 'ID required']);
    exit;
}

try {
    // PERBAIKAN: Query sederhana hanya untuk mengambil data kostum
    $query = "SELECT id, judul_post, stok FROM form_katalog WHERE id = ? AND id_user = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $id, $_SESSION['id_user']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($data = $result->fetch_assoc()) {
        // PERBAIKAN: Hanya return data, jangan buat/modifikasi stok_items di sini
        echo json_encode(['status' => 'success', 'data' => $data]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Data tidak ditemukan']);
    }
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>