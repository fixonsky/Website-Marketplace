<?php
session_start();
$conn = new mysqli("localhost", "root", "password123", "daftar_akun");

if (isset($_GET['series'])) {
    $series = $_GET['series'];
    $query = "SELECT DISTINCT karakter FROM form_katalog WHERE series = ? AND id_user = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $series, $_SESSION['id_user']);
    $stmt->execute();
    $result = $stmt->get_result();
    $karakter = [];
    while ($row = $result->fetch_assoc()) {
        $karakter[] = $row['karakter'];
    }
    echo json_encode($karakter);
}
?>