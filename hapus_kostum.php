<?php
session_name('penyedia_session');
session_start();
require 'koneksi.php'; // Sesuaikan dengan file koneksi Anda

header('Content-Type: application/json');

// Pastikan hanya metode POST yang diterima
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Metode request tidak diizinkan']);
    exit;
}

// Pastikan user sudah login
if (!isset($_SESSION['id_user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Anda harus login terlebih dahulu']);
    exit;
}

// Validasi input
if (!isset($_POST['id']) || empty($_POST['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'ID kostum tidak valid']);
    exit;
}

$id_kostum = (int)$_POST['id'];
$id_user = $_SESSION['id_user'];

// Mulai transaksi
$conn->begin_transaction();

try {
    // 1. Hapus dari published_kostum jika ada
    $stmt = $conn->prepare("DELETE FROM published_kostum WHERE id_kostum = ?");
    $stmt->bind_param("i", $id_kostum);
    $stmt->execute();
    
    // 2. Hapus dari form_katalog dengan verifikasi kepemilikan
    $stmt = $conn->prepare("DELETE FROM form_katalog WHERE id = ? AND id_user = ?");
    $stmt->bind_param("ii", $id_kostum, $id_user);
    $stmt->execute();
    
    if ($stmt->affected_rows === 0) {
        throw new Exception("Kostum tidak ditemukan atau Anda tidak memiliki izin untuk menghapusnya");
    }
    
    // Commit transaksi jika semua query berhasil
    $conn->commit();
    
    echo json_encode(['status' => 'success', 'message' => 'Kostum berhasil dihapus']);
} catch (Exception $e) {
    // Rollback transaksi jika terjadi error
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?>