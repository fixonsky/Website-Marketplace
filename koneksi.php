<?php
$host = 'localhost';
$user = 'root';
$pass = 'password123';
$dbname = 'daftar_akun';

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}
?>
