<?php
header('Content-Type: application/json');

// Database connection
$host = "localhost";
$user = "root";
$password = "password123";
$database = "daftar_akun";

try {
    $conn = new mysqli($host, $user, $password, $database);
    
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8");
    
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    $id_produk = $input['id_produk'] ?? null;
    
    if (!$id_produk) {
        echo json_encode(['success' => false, 'message' => 'ID Produk tidak ditemukan']);
        exit;
    }
    
    // Query kostum detail
    $query = "SELECT judul_post, kategori, series, karakter, ukuran FROM form_katalog WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id_produk);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $data = $result->fetch_assoc();
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Data kostum tidak ditemukan']);
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>