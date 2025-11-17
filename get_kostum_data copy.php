<?php

session_name('penyedia_session');
session_start();
require_once 'koneksi.php';

header('Content-Type: application/json');

if (!isset($_SESSION['id_user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if (!isset($_GET['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'ID kostum tidak ditemukan']);
    exit;
}

$kostumId = (int)$_GET['id'];
$userId = $_SESSION['id_user'];

try {
    // Ambil data kostum dari form_katalog
    $stmt = $conn->prepare("SELECT id, judul_post, stok FROM form_katalog WHERE id = ? AND id_user = ?");
    $stmt->bind_param("ii", $kostumId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Data kostum tidak ditemukan']);
        exit;
    }
    
    $data = $result->fetch_assoc();
    echo json_encode(['status' => 'success', 'data' => $data]);
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan: ' . $e->getMessage()]);
}
?>