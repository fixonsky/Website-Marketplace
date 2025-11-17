<?php


// Database connection
$host = "localhost";
$user = "root";
$password = "password123";
$database = "daftar_akun";

try {
    $conn_blacklist = new mysqli($host, $user, $password, $database);
    
    if ($conn_blacklist->connect_error) {
        throw new Exception("Database connection failed: " . $conn_blacklist->connect_error);
    }
    
    $conn_blacklist->set_charset("utf8");
    
    // Cek apakah user sudah login dan memiliki level penyedia (id_level = 2)
    if (isset($_SESSION['id_user']) && isset($_SESSION['id_level']) && $_SESSION['id_level'] == 2) {
        $id_user = $_SESSION['id_user'];
        
        // Cek status akun penyedia di database
        $stmt = $conn_blacklist->prepare("SELECT status FROM akun_toko WHERE id = ? AND id_level = 2");
        $stmt->bind_param("i", $id_user);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            
            // Jika status akun adalah BLACKLIST atau Blacklist (case insensitive), redirect ke halaman blocked
            if (strtoupper($row['status']) === 'BLACKLIST') {
                session_destroy();
                header("Location: blocked_account.php");
                exit();
            }
        }
    }
    
} catch (Exception $e) {
    error_log("Error checking blacklist status: " . $e->getMessage());
}

if (isset($conn_blacklist)) {
    $conn_blacklist->close();
}
?>