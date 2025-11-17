<?php
session_name('penyedia_session');
session_start();

error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

try {
    $conn = new mysqli("localhost", "root", "password123", "daftar_akun");
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    if (!isset($_GET['id_laporan'])) {
        throw new Exception("ID laporan tidak ditemukan");
    }
    
    $idLaporan = $_GET['id_laporan'];
    
    $stmt = $conn->prepare("SELECT * FROM laporan WHERE id_laporan = ?");
    $stmt->bind_param("s", $idLaporan);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if (!$row) {
        throw new Exception("Data laporan tidak ditemukan");
    }
    
    // Format tanggal
    $row['tanggal_laporan'] = date('d/m/Y H:i', strtotime($row['tanggal_laporan']));
    
    echo json_encode([
        'success' => true,
        'data' => $row
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>