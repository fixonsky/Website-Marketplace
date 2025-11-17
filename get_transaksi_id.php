<?php
// filepath: c:\AppServ\www\TUGAS_AKHIR_KESYA\bibong\get_transaksi_id.php

session_name('penyedia_session');
session_start();

header('Content-Type: application/json');

$host = "localhost";
$user = "root";
$password = "password123";
$database = "daftar_akun";

try {
    $conn = new mysqli($host, $user, $password, $database);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8");
    
    // Ambil data POST
    $input = json_decode(file_get_contents('php://input'), true);
    $idPesanan = $input['id_pesanan'] ?? null;
    
    if (!$idPesanan) {
        throw new Exception('ID Pesanan tidak diberikan');
    }
    
    // Query untuk mengambil id_transaksi berdasarkan id_pesanan
    $query = "SELECT id_transaksi FROM pesanan WHERE id_pesanan = ? LIMIT 1";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception("Error preparing query: " . $conn->error);
    }
    
    $stmt->bind_param("i", $idPesanan);
    
    if (!$stmt->execute()) {
        throw new Exception("Error executing query: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Pesanan dengan ID $idPesanan tidak ditemukan");
    }
    
    $row = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'id_transaksi' => $row['id_transaksi']
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>