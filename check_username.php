<?php
session_name('penyedia_session');
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'localhost';
$user = 'root';
$pass = 'password123';
$dbname = 'daftar_akun';

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    exit(json_encode(['error' => 'Koneksi ke database gagal']));
}

if (!isset($_SESSION['id_user'])) {
    echo json_encode(['error' => 'Sesi berakhir, silakan login kembali']);
    exit();
}

$iduser = $_SESSION['id_user'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {
    $username = trim($_POST['username']);
    
    if (empty($username)) {
        echo json_encode(['exists' => false, 'hasSpace' => false]);
        exit();
    }
    
    // ===== TAMBAHKAN VALIDASI SPASI =====
    $hasSpace = strpos($username, ' ') !== false;
    
    if ($hasSpace) {
        echo json_encode(['exists' => false, 'hasSpace' => true]);
        exit();
    }
    // ===== END VALIDASI SPASI =====
    
    // Cek apakah username sudah digunakan oleh toko lain
    $query = "SELECT id_user FROM data_toko WHERE username = ? AND id_user != ?";
    $stmt = $conn->prepare($query);
    
    if ($stmt) {
        $stmt->bind_param("si", $username, $iduser);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $exists = $result->num_rows > 0;
        
        echo json_encode(['exists' => $exists, 'hasSpace' => false]);
        
        $stmt->close();
    } else {
        echo json_encode(['error' => 'Query preparation failed']);
    }
} else {
    echo json_encode(['error' => 'Invalid request']);
}

$conn->close();
?>