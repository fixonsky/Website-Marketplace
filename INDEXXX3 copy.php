<?php 
session_name('penyedia_session');
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() !== PHP_SESSION_ACTIVE) {
    die("Session failed to start.");
}

$conn = new mysqli("localhost", "root", "password123", "daftar_akun");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Pastikan id_user tersedia dalam sesi
if (!isset($_SESSION['id_user'])) {
    die("User ID tidak ditemukan. Harap login terlebih dahulu.");
}

$iduser = $_SESSION['id_user']; 

// Tambahkan baris berikut sebelum getProviderNotifications:
$id_penyedia = $iduser;

function getProviderNotifications($conn, $id_penyedia) {
    $notifications = [];
    
    if ($id_penyedia) {
        // Cek pesanan baru yang status approved
        $sql = "SELECT COUNT(*) as count FROM pesanan WHERE id_penyedia = ? AND status = 'approved'";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $id_penyedia);
            $stmt->execute();
            $newOrders = $stmt->get_result()->fetch_assoc()['count'];
            
            if ($newOrders > 0) {
                $notifications[] = [
                    'id' => 'new_orders',
                    'type' => 'order',
                    'icon' => 'fas fa-shopping-bag',
                    'class' => 'is-success',
                    'title' => 'Pesanan Baru',
                    'message' => "Anda memiliki {$newOrders} pesanan baru yang perlu konfirmasi",
                    'time' => 'Baru saja',
                    'count' => $newOrders
                ];
            }
        }

        // Notifikasi pencairan dana selesai
        $sql = "SELECT COUNT(*) as count 
                FROM riwayat_pesanan rp
                LEFT JOIN peminjaman pm ON rp.id_transaksi = pm.id_transaksi AND rp.id_produk = pm.id_produk
                WHERE rp.id_penyedia = ? 
                AND rp.status = 'selesai'
                AND pm.bukti_pencairan_dana IS NOT NULL 
                AND pm.bukti_pencairan_dana != ''";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id_penyedia);
        $stmt->execute();
        $pencairanCount = $stmt->get_result()->fetch_assoc()['count'];
        if ($pencairanCount > 0) {
            $notifications[] = [
                'id' => 'pencairan_dana',
                'type' => 'pencairan_dana',
                'icon' => 'fas fa-money-bill-wave',
                'class' => 'is-info',
                'title' => 'Pencairan Dana',
                'message' => "{$pencairanCount} peminjaman sudah dilakukan pencairan dana",
                'time' => 'Baru saja',
                'count' => $pencairanCount
            ];
        }
        
        $sql = "SELECT COUNT(*) as count FROM pesanan WHERE id_penyedia = ? AND status = 'accepted'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id_penyedia);
        $stmt->execute();
        $acceptedOrders = $stmt->get_result()->fetch_assoc()['count'];

        if ($acceptedOrders > 0) {
            $notifications[] = [
                'id' => 'accepted_orders',
                'type' => 'accepted',
                'icon' => 'fas fa-check-circle',
                'class' => 'is-info',
                'title' => 'Konfirmasi diterima',
                'message' => "{$acceptedOrders} peminjaman menunggu konfirmasi pesanan telah diterima",
                'time' => 'Baru saja',
                'count' => $acceptedOrders
            ];
        }

        // Notifikasi untuk status 'Pesanan Diterima Penyewa'
        $sql = "SELECT COUNT(*) as count FROM pesanan WHERE id_penyedia = ? AND status = 'pesanan_diterima_penyewa'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id_penyedia);
        $stmt->execute();
        $returnConfirmCount = $stmt->get_result()->fetch_assoc()['count'];
        if ($returnConfirmCount > 0) {
            $notifications[] = [
                'id' => 'return_confirm',
                'type' => 'return_confirm',
                'icon' => 'fas fa-undo-alt',
                'class' => 'is-warning',
                'title' => 'Konfirmasi Pengembalian Kostum',
                'message' => "{$returnConfirmCount} peminjaman menunggu konfirmasi kostum telah dikembalikan",
                'time' => 'Baru saja',
                'count' => $returnConfirmCount
            ];
        }

        // Cek peminjaman yang akan berakhir dalam 3 hari
        $sql = "SELECT COUNT(*) as count FROM peminjaman 
                WHERE id_penyedia = ? AND status_peminjaman = 'sedang_berjalan' 
                AND DATEDIFF(tanggal_selesai, CURDATE()) <= 3 AND DATEDIFF(tanggal_selesai, CURDATE()) >= 0";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $id_penyedia);
            $stmt->execute();
            $endingSoon = $stmt->get_result()->fetch_assoc()['count'];
            
            if ($endingSoon > 0) {
                $notifications[] = [
                    'id' => 'ending_soon',
                    'type' => 'reminder',
                    'icon' => 'fas fa-clock',
                    'class' => 'is-warning',
                    'title' => 'Peminjaman Berakhir',
                    'message' => "{$endingSoon} peminjaman akan berakhir dalam 3 hari",
                    'time' => '2 jam yang lalu',
                    'count' => $endingSoon
                ];
            }
        }
        
        // Cek peminjaman yang terlambat
        $sql = "SELECT COUNT(*) as count FROM peminjaman 
                WHERE id_penyedia = ? AND status_peminjaman = 'sedang_berjalan' 
                AND CURDATE() > tanggal_selesai";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $id_penyedia);
            $stmt->execute();
            $overdue = $stmt->get_result()->fetch_assoc()['count'];
            
            if ($overdue > 0) {
                $notifications[] = [
                    'id' => 'overdue',
                    'type' => 'alert',
                    'icon' => 'fas fa-exclamation-triangle',
                    'class' => 'is-danger',
                    'title' => 'Peminjaman Terlambat',
                    'message' => "{$overdue} peminjaman sudah melewati batas waktu",
                    'time' => '5 jam yang lalu',
                    'count' => $overdue
                ];
            }
        }
    }
    
    // Jika tidak ada notifikasi
    if (empty($notifications)) {
        $notifications[] = [
            'id' => 'no_notifications',
            'type' => 'info',
            'icon' => 'fas fa-info-circle',
            'class' => 'is-info',
            'title' => 'Tidak ada notifikasi',
            'message' => 'Semua pesanan dan peminjaman sudah terkendali',
            'time' => 'Sekarang',
            'count' => 0
        ];
    }
    
    return $notifications;
}

// Dapatkan notifikasi
$notifications = getProviderNotifications($conn, $id_penyedia);

    // Hitung jumlah notifikasi penting (hanya yang benar-benar unread, misal status 'approved')
    $newOrderNotif = 0;
    $notifIdsForBadge = [
        'new_orders',
        'accepted_orders',
        'return_confirm',
        'overdue',
        'ending_soon',
        'pencairan_dana'
        // tambahkan id lain jika memang ingin badge muncul juga
    ];
    foreach ($notifications as $notif) {
        if (in_array($notif['id'], $notifIdsForBadge)) {
            $newOrderNotif += (int)$notif['count'];
        }
    }

    // Inisialisasi session array untuk tracking per id
    if (!isset($_SESSION['notification_read'])) {
        $_SESSION['notification_read'] = false;
    }
    if (!isset($_SESSION['notification_last_count_by_id'])) {
        $_SESSION['notification_last_count_by_id'] = [];
    }

    // Jika user klik bell (lihat set_notification_read.php), simpan jumlah notifikasi terakhir per id
    if ($_SESSION['notification_read'] === true) {
        // Simpan jumlah terakhir per id
        foreach ($notifications as $notif) {
            if (in_array($notif['id'], $notifIdsForBadge)) {
                $_SESSION['notification_last_count_by_id'][$notif['id']] = (int)$notif['count'];
            }
        }
        $_SESSION['notification_read'] = false; // reset agar badge bisa muncul lagi jika ada notifikasi baru
    }

    // Hitung badge: jumlah total notifikasi baru dari semua id
    $unreadCount = 0;
    foreach ($notifications as $notif) {
        if (in_array($notif['id'], $notifIdsForBadge)) {
            $lastCount = isset($_SESSION['notification_last_count_by_id'][$notif['id']]) ? (int)$_SESSION['notification_last_count_by_id'][$notif['id']] : 0;
            $unreadCount += max(0, (int)$notif['count'] - $lastCount);
        }
    }

// Konfigurasi paging
$records_per_page = 7; // Jumlah data per halaman
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

$count_query = "SELECT COUNT(*) as total FROM form_katalog WHERE id_user = ?";
$count_stmt = $conn->prepare($count_query);
$count_stmt->bind_param("i", $iduser);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);

$query = "SELECT f.id, f.judul_post, f.kategori, f.series, f.karakter, f.ukuran, 
                 f.gender, f.harga_sewa, f.jumlah_hari, f.status, f.keterangan, f.stok,
                 SUBSTRING_INDEX(f.foto_kostum, ',', 1) AS foto_kostum,
                 p.published_at
          FROM form_katalog f
          LEFT JOIN published_kostum p ON f.id = p.id_kostum
          WHERE f.id_user = ?
          ORDER BY f.id DESC
          LIMIT ?, ?";
$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Error preparing query: " . $conn->error); // Tambahkan pengecekan error
}

$stmt->bind_param("iii", $iduser, $offset, $records_per_page);
if (!$stmt->execute()) {
    die("Error executing query: " . $stmt->error);
}
$result = $stmt->get_result();

// Hapus atau ganti dengan:
error_log("User ID: " . $_SESSION['id_user']);
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Website dengan Navbar dan Sidebar</title>
    <link crossorigin="anonymous" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="katalog.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    
    <style>
    body, h1, h3, ul, li, a {
        margin: 0;
        padding: 0;
        text-decoration: none;
        list-style: none;
    }

    body {
        font-family: Arial, sans-serif;
        line-height: 1.6;
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        min-height: 100vh;
    }

    .navbar {
        background-color: #333;
        color: white;
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem;
        width: 100%;
    }

    .navbar .logo {
        font-size: 1.5rem;
        font-weight: bold;
        min-width: 200px;
    }

    .navbar .nav-links {
        display: flex;
        margin-left: auto;
    }

    .navbar .nav-links li {
        margin-left: 1.5rem;
    }

    .navbar .nav-links a {
        color: white;
        font-size: 1rem;
    }

    .navbar .nav-links a:hover {
        background-color: rgba(255, 255, 255, 0.1);
    }

    /* Notification Bell Styling */
    .notification-bell {
        position: relative;
        display: inline-block;
        padding: 0.5rem;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .notification-bell:hover {
        background-color: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
    }

    .notification-bell i {
        font-size: 1.2rem;
        color: white;
    }

    .notification-badge {
        position: absolute;
        top: 2px;
        right: 2px;
        background: #ff3860;
        color: white;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        font-size: 11px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        border: 2px solid #333;
        min-width: 20px;
    }

    /* Dropdown Notification */
    .notification-dropdown {
        position: absolute;
        top: 100%;
        right: -50px;
        width: 350px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
        border: 1px solid #e1e5e9;
        z-index: 1000;
        opacity: 0;
        visibility: hidden;
        transform: translateY(-10px);
        transition: all 0.3s ease;
        max-height: 400px;
        overflow-y: auto;
    }

    .notification-dropdown.show {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .notification-header {
        background-color: #f8f9fa;
        padding: 15px 20px;
        border-bottom: 1px solid #e9ecef;
        border-radius: 8px 8px 0 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .notification-header h5 {
        margin: 0;
        font-size: 16px;
        font-weight: 600;
        color: #2c3e50;
    }

    .mark-all-read {
        background: none;
        border: none;
        color: #485fc7;
        cursor: pointer;
        font-size: 12px;
        padding: 4px 8px;
        border-radius: 4px;
        transition: background-color 0.2s ease;
    }

    .mark-all-read:hover {
        background-color: #e9ecef;
    }

    .notification-item {
        padding: 15px 20px;
        border-bottom: 1px solid #f0f0f0;
        display: block;
        color: inherit;
        text-decoration: none;
        transition: all 0.2s ease;
    }

    .notification-item:last-child {
        border-bottom: none;
    }

    .notification-item:hover {
        background-color: #f8f9fa;
        text-decoration: none;
    }

    .notification-content {
        display: flex;
        align-items: flex-start;
        gap: 12px;
    }

    .notification-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        flex-shrink: 0;
    }

    .notification-icon.is-success {
        background-color: #d4edda;
        color: #155724;
    }

    .notification-icon.is-warning {
        background-color: #fff3cd;
        color: #856404;
    }

    .notification-icon.is-danger {
        background-color: #f8d7da;
        color: #721c24;
    }

    .notification-icon.is-info {
        background-color: #d1ecf1;
        color: #0c5460;
    }

    .notification-text {
        flex: 1;
        min-width: 0;
    }

    .notification-title {
        font-weight: 600;
        font-size: 14px;
        margin-bottom: 4px;
        color: #2c3e50;
        line-height: 1.3;
    }

    .notification-message {
        font-size: 13px;
        color: #6c757d;
        margin-bottom: 6px;
        line-height: 1.4;
    }

    .notification-time {
        font-size: 11px;
        color: #999;
        font-style: italic;
    }

    .notification-footer {
        padding: 15px 20px;
        text-align: center;
        background-color: #fafafa;
        border-radius: 0 0 8px 8px;
        border-top: 1px solid #e9ecef;
    }

    .notification-footer a {
        color: #485fc7;
        font-weight: 500;
        text-decoration: none;
        font-size: 14px;
    }

    .notification-footer a:hover {
        text-decoration: underline;
    }

    /* Custom scrollbar untuk dropdown */
    .notification-dropdown::-webkit-scrollbar {
        width: 6px;
    }

    .notification-dropdown::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 3px;
    }

    .notification-dropdown::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 3px;
    }

    .notification-dropdown::-webkit-scrollbar-thumb:hover {
        background: #a8a8a8;
    }

    .container {
        display: flex;
        flex: 1;
        width: 100%;
        max-width: none; /* Ubah dari 1200px ke none */
        margin: 0;
        position: relative;
    }

    /* CSS untuk Sidebar - Diperbaiki untuk responsiveness */
    .sidebar {
        background-color: #EFEFEF;
        width: 220px;
        min-width: 220px; /* Tambahkan min-width */
        padding: 1rem;
        border-right: 1px solid #e0e0e0;
        box-shadow: 2px 0 5px rgba(0,0,0,0.05);
        position: sticky;
        top: 0;
        height: 100vh;
        overflow-y: auto;
        flex-shrink: 0;
        margin-left: 0; /* Ubah dari -110px ke 0 */
    }

    
    .sidebar ul {
        padding: -20px 5px 5px;
        margin: 0;
    }
    
    .sidebar li {
        margin-bottom: 0.5rem;
        border-radius: 5px;
        transition: all 0.2s ease;
    }
    
    .sidebar li:hover {
        background-color: #e9ecef;
    }
    
    .sidebar a {
        color: #495057;
        font-size: 1rem;
        font-weight: 600;
        padding: 0.75rem 1rem;
        display: block;
        text-decoration: none;
        transition: all 0.2s ease;
        border-left: 3px solid transparent;
        padding-left : 15px;
    }
    
    .sidebar a:hover {
        color: #1F7D53;
        border-left: 3px solid #6c757d;
        padding-left: 1.25rem;
    }
    
    .sidebar li.active {
        background-color: #e9ecef;
    }
    
    .sidebar li.active a {
        color: #212529;
        border-left: 3px solid #495057;
        font-weight: 600;
    }

    
    .main-content {
        flex: 1;
        padding: 1rem 2rem;
        margin-left: 0;
        width: auto; /* Ubah dari calc(100% - 220px) ke auto */
        position: relative;
        z-index: 1;
        min-width: 0; /* Tambahkan untuk mencegah overflow */
    }

    .content {
        width: 100%;
        padding: 20px;
    }

    .form-section {
        width: 100%; /* Ubah dari 116% ke 100% */
        background-color: white;
        padding: 20px;
        border-radius: 5px;
        box-shadow: 0 0 10px rgba(0,0,0,0.1);
        margin-left: 0; /* Ubah dari -30px ke 0 */
        margin-top: 0; /* Ubah dari -20px ke 0 */
    }


    /* Style for dropdown */
    .dropdown-toggle::after {
        display: none;
    }

    .dropdown-menu {
        min-width: auto;
        padding: 0;
    }

    .dropdown-item {
        padding: 0.25rem 1rem;
        font-size: 0.875rem;
    }

    .dropdown-item:hover {
        background-color: #f8f9fa;
        color: #dc3545;
    }

    /* Dropdown Style */
    .status-dropdown {
        position: relative;
        display: inline-block;
    }

    .status-dropdown-content {
        display: none;
        position: absolute;
        background-color: #f9f9f9;
        min-width: 120px;
        box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
        z-index: 1;
        border-radius: 4px;
    }

    .status-dropdown-content a {
        color: black;
        padding: 8px 12px;
        text-decoration: none;
        display: block;
        font-size: 14px;
    }

    .status-dropdown-content a:hover {
        background-color: #f1f1f1;
        color: #dc3545;
    }

    .status-dropdown:hover .status-dropdown-content {
        display: block;
    }

    .status-btn {
        background-color: #28a745;
        color: white;
        border: none;
        padding: 6px 12px;
        border-radius: 4px;
        cursor: default;
        font-size: 14px;
    }

    .status-btn:after {
        content: "▼";
        margin-left: 5px;
        font-size: 10px;
    }

    .form-header {
        background-color: #f5f5f5;
        padding: 10px;
        border-radius: 5px 5px 0 0;
        margin-bottom: 10px;
        border: 1px solid #ddd;
    }

    .form-title {
        margin: 0;
        font-size: 18px;
        font-weight: bold;
        color: #333;
        font-family: monospace;
        padding: 5px;
        padding-left: 20px;
    }

    .img-thumbnail {
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 5px;
        margin: 5px;
        width: 150px;
        height: 150px;
        object-fit: cover;
        transition: transform 0.2s;
    }

    .img-thumbnail:hover {
        transform: scale(1.05);
    }

    .styleAja {
        font-family: 'arial', sans-serif;
        font-weight: 400;
        display: inline-block;
        margin: 2px 0;
        line-height: 1.4;
    }

    .btn-warning {
        background-color: #ffc107;
        color: #212529;
        border-color: #ffc107;
    }

    .btn-warning:hover {
        background-color: #e0a800;
        border-color: #d39e00;
    }

    /* Paging Style */
    .pagination {
        margin: 20px 0;
    }

    .page-item.active .page-link {
        background-color: #1F7D53;
        border-color: #1F7D53;
    }

    .page-link {
        color: #1F7D53;
    }

    .page-link:hover {
        color: #1F7D53;
        background-color: #f8f9fa;
    }

    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        justify-content: center;
        align-items: center;
    }
    
    .modal-content {
        background-color: white;
        padding: 20px;
        border-radius: 5px;
        width: 500px;
        max-width: 90%;
        max-height: 80vh; 
        overflow-y: auto; 
        display: flex;
        flex-direction: column;
    }

    .modal-body {
        flex: 1;
        overflow-y: auto;
        padding-right: 10px; /* Ruang untuk scrollbar */
    }
    
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 1px solid #eee;
    }
    
    .modal-title {
        font-size: 18px;
        font-weight: bold;
    }
    
    .close-modal {
        background: none;
        border: none;
        font-size: 20px;
        cursor: pointer;
    }
    
    .form-group {
        margin-bottom: 15px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 500;
    }
    
    .form-control {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    
    .modal-footer {
        padding-top: 15px;
        border-top: 1px solid #eee;
        background: white;
        position: sticky;
        bottom: 0;
        z-index: 10;
    }
    
    .btn {
        padding: 8px 15px;
        border-radius: 4px;
        cursor: pointer;
    }
    
    .btn-primary {
        background-color: #1F7D53;
        color: white;
        border: none;
    }
    
    .btn-secondary {
        background-color: #6c757d;
        color: white;
        border: none;
    }
    .form-check {
        margin-top: 15px;
        margin-bottom: 15px;
    }
    
    .form-check-input {
        margin-right: 8px;
    }
    /* Calendar styling */
    .update-calendar {
        font-family: Arial, sans-serif;
        border: 1px solid #e6e6e6;
        border-radius: 4px;
        padding: 5px;
        background: #fff;
        margin-top: 15px;
        min-height : 300px;
        width: 100%; /* Lebar penuh */
    }

    @media (max-width: 576px) {
        .update-calendar {
            min-height: 250px;
        }
        .modal-content {
            width: 95%;
        }
    }

    .update-calendar .fc-header-toolbar {
        margin-bottom: 0.5em !important;
    }

    .update-calendar .fc-button {
        background: #1F7D53 !important;
        border: none !important;
        color: white !important;
        text-transform: capitalize !important;
        border-radius: 2px !important;
        padding: 3px 8px !important;
        font-size: 12px !important;
    }

    .update-calendar .fc-button:hover {
        background: #165c3d !important;
    }

    .update-calendar .fc-daygrid-day-number {
        color: #333;
        font-size: 0.8em;
    }

    .update-calendar .fc-day-today {
        background-color: rgba(31, 125, 83, 0.1) !important;
    }

    .update-calendar .fc-day-disabled {
        background-color: #f9f9f9;
        color: #ccc;
    }

    .update-calendar .fc-daygrid-event {
        margin: 1px;
    }
    .fc-event-booked {
        background-color: #ff4d4d !important;
        border-color: #ff4d4d !important;
        color: white !important;
        padding: 2px;
        border-radius: 3px;
        font-size: 0.8em;
        text-align: center;
    }
    .fc-day-disabled {
        background-color: #f9f9f9;
        color: #ccc;
        pointer-events: none;
    }

    .fc-daygrid-event-dot {
        display: none;
    }

    .fc-day-selected {
        background-color: rgba(0, 123, 255, 0.3) !important;
    }

    #selectedDatesList {
        max-height: 100px;
        overflow-y: auto;
        margin-top: 10px;
        padding: 5px;
        border: 1px solid #eee;
        border-radius: 4px;
    }

    .selected-date-item {
        display: flex;
        justify-content: space-between;
        padding: 3px 5px;
        margin-bottom: 3px;
        background-color: #f8f9fa;
        border-radius: 3px;
    }

    .remove-date {
        color: #dc3545;
        cursor: pointer;
    }

    /* Tooltip style */
    .tooltip-inner {
        background-color: #ff4d4d;
    }

    .bs-tooltip-auto[data-popper-placement^=top] .tooltip-arrow::before,
    .bs-tooltip-top .tooltip-arrow::before {
        border-top-color: #ff4d4d;
    }

    .booking-btn-container {
        margin-top: 15px;
        text-align: center;
        display: none; /* Hidden by default */
    }
    
    #bookingBtn {
        background-color: #1F7D53;
        color: white;
        padding: 8px 20px;
        border: none;
        border-radius: 4px;
        font-weight: bold;
        transition: all 0.3s ease;
    }
    
    #bookingBtn:hover {
        background-color: #165c3d;
    }
    
    #bookingBtn:disabled {
        background-color: #cccccc;
        cursor: not-allowed;
    }
    /* Booking Modal Styles */
    #bookingModal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 1100;
        justify-content: center;
        align-items: center;
    }
    
    #bookingModal .modal-content {
        background-color: white;
        padding: 20px;
        border-radius: 5px;
        width: 400px;
        max-width: 90%;
    }
    
    #bookingModal .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 1px solid #eee;
    }
    
    #bookingModal .modal-title {
        font-size: 18px;
        font-weight: bold;
    }
    
    #bookingModal .close-modal {
        background: none;
        border: none;
        font-size: 20px;
        cursor: pointer;
    }
    
    #bookingModal .form-group {
        margin-bottom: 15px;
    }
    
    #bookingModal .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 500;
    }
    
    #bookingModal .form-control {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    
    #bookingModal .modal-footer {
        padding-top: 15px;
        border-top: 1px solid #eee;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }
    
    /* Style for booked dates with names */
    .fc-event-booked-with-name {
        background-color: #ff8c66 !important;
        border-color: #ff8c66 !important;
        color: white !important;
        padding: 2px;
        border-radius: 3px;
        font-size: 0.7em;
        text-align: center;
        white-space: normal !important;
        line-height: 1.2;
    }
    .fc-event-booked,
    .fc-event-booked-with-name {
        background-color: #ff4d4d !important;
        border-color: #ff4d4d !important;
        color: white !important;
        pointer-events: none !important;
        cursor: not-allowed !important;
    }

    .fc-day-disabled {
        background-color: #f9f9f9 !important;
        color: #ccc !important;
        pointer-events: none !important;
    }

    .fc-day-selected {
        background-color: rgba(113, 127, 224, 0.2) !important;
    }

    .update-calendar .fc-daygrid-day {
        cursor: pointer;
    }

    .update-calendar .fc-daygrid-day:hover {
        background-color: #f0f8ff;
        transition: background-color 0.2s ease;
    }

    /* Untuk tanggal yang disabled tetap gunakan cursor default */
    .update-calendar .fc-day-disabled {
        cursor: not-allowed !important;
    }

    /* Untuk tanggal yang sudah dibooking tetap gunakan cursor not-allowed */
    .update-calendar .fc-day[style*="pointer-events: none"] {
        cursor: not-allowed !important;
    }

    /* Styling untuk header kalender agar tidak ikut berubah cursor */
    .update-calendar .fc-col-header-cell {
        cursor: default;
    }

    .update-calendar .fc-button {
        cursor: pointer;
    }

    .badge-secondary {
        background-color: #6c757d;
        color: white;
    }

    .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
        line-height: 1.5;
        border-radius: 0.2rem;
    }

    .font-weight-bold {
        font-weight: 600 !important;
    }

    .border-bottom {
        border-bottom: 1px solid #dee2e6 !important;
    }

    #stokIdList .d-flex:last-child {
        border-bottom: none !important;
    }

   .badge-success { 
        background-color: #28a745 !important; 
        color: white !important; 
    }
    .badge-warning { 
        background-color: #ffc107 !important; 
        color: #212529 !important; 
    }
    .badge-info { 
        background-color: #17a2b8 !important; 
        color: white !important; 
    }
    .badge-secondary { 
        background-color: #6c757d !important; 
        color: white !important; 
    }

    /* Stock ID Container Styling */
    #stokIdContainer {
        border: 1px solid #dee2e6;
        border-radius: 4px;
        padding: 15px;
        background-color: #f8f9fa;
        margin-top: 15px;
    }

    .btn-xs {
        padding: 2px 6px;
        font-size: 10px;
        line-height: 1.2;
        border-radius: 3px;
    }

    @media screen and (min-width: 1px) {
    .container {
        min-width: 100vw;
    }
    
    .sidebar {
        flex: 0 0 220px;
    }
    
    .main-content {
        flex: 1 1 auto;
        overflow-x: auto;
    }
    
    .form-section {
        overflow-x: auto;
    }
    
    .table-responsive {
        overflow-x: auto;
    }
}


.table-container {
    width: 100%;
    overflow-x: auto;
    margin: 0;
}

.table {
    width: 100%;
    min-width: 800px; /* Minimum width untuk table */
    margin-bottom: 0;
}

/* Untuk zoom out yang ekstrem */
@media screen and (max-width: 768px) {
    .sidebar {
        width: 200px;
        min-width: 200px;
    }
    
    .main-content {
        padding: 0.5rem 1rem;
    }
}

/* Untuk zoom in yang ekstrem */
@media screen and (min-width: 1400px) {
    .container {
        max-width: none;
    }
}

html {
    box-sizing: border-box;
}

*, *:before, *:after {
    box-sizing: inherit;
}

body {
    min-width: 100%;
    overflow-x: auto;
}

/* Flexible layout untuk semua zoom level */
.container {
    display: flex;
    width: 100vw;
    min-height: calc(100vh - 60px); /* Adjust based on navbar height */
}

/* Ensure sidebar stays fixed width but responsive */
.sidebar {
    flex: 0 0 auto;
    width: clamp(180px, 15vw, 220px); /* Responsive width dengan min dan max */
}

/* Main content takes remaining space */
.main-content {
    flex: 1 1 auto;
    min-width: 0;
    width: 0; /* Trick untuk flex item */
}
            </style>
</head>
<body>
    <nav class="navbar">
        <div class="logo">Logo</div>
        <ul class="nav-links">

            <!-- Notification Bell -->
            <li class="notification-bell" id="notificationBell">
                <i class="fas fa-bell"></i>
                <?php if ($unreadCount > 0): ?>
                <span class="notification-badge" id="notificationBadge"><?= $unreadCount > 99 ? '99+' : $unreadCount ?></span>
                <?php endif; ?>
                
                <!-- Dropdown Notification -->
                <div class="notification-dropdown" id="notificationDropdown">
                    <div class="notification-header">
                        <h5>Notifikasi</h5>
                        <button class="mark-all-read" onclick="markAllAsRead()">
                            <i class="fas fa-check-all"></i> Tandai dibaca
                        </button>
                    </div>
                    
                    <div class="notification-list">
                        <?php foreach($notifications as $notification): ?>
                        <a href="#" class="notification-item" onclick="handleNotificationClick('<?= $notification['type'] ?>')">
                            <div class="notification-content">
                                <div class="notification-icon <?= $notification['class'] ?>">
                                    <i class="<?= $notification['icon'] ?>"></i>
                                </div>
                                <div class="notification-text">
                                    <div class="notification-title"><?= htmlspecialchars($notification['title']) ?></div>
                                    <div class="notification-message"><?= htmlspecialchars($notification['message']) ?></div>
                                    <div class="notification-time"><?= htmlspecialchars($notification['time']) ?></div>
                                </div>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="notification-footer">
                        <a href="#" onclick="viewAllNotifications()">Lihat semua notifikasi</a>
                    </div>
                </div>
            </li>
            <li><a href="#">About</a></li>
            <li><a href="#">Services</a></li>
            <li><a href="#">Contact</a></li>
        </ul>
    </nav>

    <div class="container">
        <aside class="sidebar">
            <ul>
                <li><a href="INDEXXX.php">Dashboard</a></li>
                <li><a href="INDEXXX2.php">Profile</a></li>
                <li><a href="INDEXXX3.php">Katalog Kostum</a></li>
                <li><a href="pesanan.php">Pesanan</a></li>
            </ul>
        </aside>
        <main class="col-md-20 ml-sm-auto col-lg-30 px-4 content" role="main">
        <div class="form-section">
            <div class="form-header" style = "margin-bottom : 20px; margin-top : -19px; margin-left: -20px;">
                <h2 class="form-title"> Lengkapi Data Kostum Anda</h2>
            </div>
    <div class="d-flex justify-content-between align-items-center mb-3">
     <div>
      <button onclick="window.location.href='form_katalog.php'" class="btn btn-success me-2 menu-item">
       Tambah Kostum
      </button>
     </div>
     <div class="d-flex align-items-center">
      <select aria-label="Default select example" class="form-select me-2">
       <option selected="">
        Tanggal Dibuat
       </option>
       <option value="1">
        Option 1
       </option>
       <option value="2">
        Option 2
       </option>
       <option value="3">
        Option 3
       </option>
      </select>
      <div class="input-group">
       <input aria-label="Cari Kostum" class="form-control" placeholder="Cari Kostum" type="text"/>
       <button class="btn btn-outline-secondary" type="button">
        <i class="fas fa-search">
        </i>
       </button>
      </div>
     </div>
    </div>
    <div class="card">
        <div class="card-body">
            <div class="table-container">
                
                <h5 class="card-title">
                Kelola Katalog
                </h5>
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                            <th>No</th>
                            <th style = "width: 180px;">Foto</th>
                            <th>Kostum</th>
                            <th>Harga</th>
                            <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                                $no = $total_records - $offset;
                                while ($row = $result->fetch_assoc()) { 
                                    $isPublished = $row['status'] === 'published';
                                    $wasEverPublished = !empty($row['published_at']);

                                    $showUpdateButton = $isPublished;
                                    $showUploadButton = !$isPublished;
                            ?>

                            <tr>
                                <td> <?php echo $no++; ?></td>
                                <td>
                                    <img src="foto_kostum/<?php echo htmlspecialchars($row['foto_kostum']); ?>" 
                                        onerror="this.onerror=null; this.src='foto_kostum/default.jpg';"   
                                        alt="Kostum" 
                                        class="img-thumbnail" />
                                </td >
                                <td>
                                    <strong ><?php echo $row['judul_post']; ?></strong>
                                    <br>
                                    <small class="styleAja">
                                        <i class="fas fa-tags"></i> <?php echo $row['kategori']; ?>
                                    </small>
                                    <br>
                                    <small class="styleAja">
                                        <i class="fas fa-vest"></i> <?php echo $row['series']; ?>
                                    </small>
                                    <br>
                                    <small class="styleAja">
                                        <i class="fas fa-ruler"></i> 
                                        <?php 
                                            // Parse ukuran dengan LD/LP
                                            $ukuranDisplay = '';
                                            if (!empty($row['ukuran'])) {
                                                $ukuranArray = explode(',', $row['ukuran']);
                                                $ukuranFormatted = [];
                                                foreach ($ukuranArray as $ukuran) {
                                                    if (preg_match('/^(.+)\((\d+)\/(\d+)\)$/', $ukuran, $matches)) {
                                                        $size = $matches[1];
                                                        $ld = $matches[2];
                                                        $lp = $matches[3];
                                                        $ukuranFormatted[] = "$size (LD:$ld LP:$lp)";
                                                    } else {
                                                        $ukuranFormatted[] = $ukuran;
                                                    }
                                                }
                                                $ukuranDisplay = implode(', ', $ukuranFormatted);
                                            }
                                            echo htmlspecialchars($ukuranDisplay);
                                        ?>
                                    </small>
                                    <br>
                                    <small class="styleAja">
                                        <i class="fas fa-user"></i> <?php echo $row['karakter']; ?>
                                    </small>
                                    <br>
                                    <small class="styleAja">
                                        <i class="fas fa-venus-mars"></i> <?php echo $row['gender']; ?>
                                    </small>
                                    <br>
                                    <small class="styleAja" style="color: <?php echo ($row['stok'] > 0) ? '#28a745' : '#dc3545'; ?>">
                                        <i class="fas fa-boxes"></i> Stok: <span class="stok-value"><?php echo $row['stok']; ?></span>
                                    </small>
                                </td>

                                <td> Rp <?php echo number_format($row['harga_sewa'], 0, ',', '.'); ?> / <?php echo $row['jumlah_hari']; ?> hari </td>
                                <td>
                                    <div style="display: flex; flex-wrap: wrap; gap: 3px;">
                                        <?php if (!$isPublished): ?>
                                            <!-- Tombol Edit (hanya untuk yang belum pernah dipublish) -->
                                            <a href="form_katalog.php?id=<?php echo $row['id']; ?>" 
                                            class="btn btn-primary btn-sm" 
                                            style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                                                    padding: 5px 10px; white-space: nowrap;">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                        <?php endif; ?>
                                        
                                        <!-- Tombol Hapus -->
                                        <button class="btn btn-danger btn-sm delete-btn" 
                                                data-id="<?php echo $row['id']; ?>" 
                                                style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                                                    padding: 5px 10px; white-space: nowrap;">
                                            <i class="fas fa-trash"></i> Hapus
                                        </button>
                                        
                                        <?php if ($showUpdateButton): ?>
                                            <!-- Tombol Update Stok -->
                                            <button class="btn btn-warning btn-sm update-btn" 
                                                    data-id="<?php echo $row['id']; ?>">
                                                <i class="fas fa-sync-alt"></i> Update Stok
                                            </button>
                                        <?php else: ?>
                                            <!-- Tombol Unggah -->
                                            <button class="btn btn-info btn-sm upload-btn" 
                                                    data-id="<?php echo $row['id']; ?>">
                                                <i class="fas fa-upload"></i> Unggah
                                            </button>
                                        <?php endif; ?>
                                    </div>

                                    <div style="width: 100%; margin-top: 5px;">
                                        <small style="font-style: italic; color: #6c757d;">
                                            <?php 
                                            if ($isPublished) {
                                                echo "Diposting: " . ($row['published_at'] 
                                                    ? date('d M Y H:i', strtotime($row['published_at'])) 
                                                    : 'Baru saja');
                                            } elseif ($wasEverPublished) {
                                                echo "Terakhir diposting: " . ($row['published_at'] 
                                                    ? date('d M Y H:i', strtotime($row['published_at'])) 
                                                    : 'Baru saja');
                                            }
                                            ?>
                                        </small>
                                    </div>
                                </td>
                            </tr>
                                <?php } ?>
                        </tbody>
                </table>

                <div class="modal-overlay" id="updateStockModal">
                    <div class="modal-content" style = "width : 500px;">
                        <div class="modal-header">
                            <h3 class="modal-title">Update Stok Kostum</h3>
                            <button class="close-modal" id="closeModal">&times;</button>
                        </div>
                        <div class = "modal-body">
                            <form id="updateStockForm">
                                <input type="hidden" id="kostumId" name="id">
                                <div class="form-group">
                                    <label for="stok">Jumlah Stok Tersedia</label>
                                    <input type="number" class="form-control" id="stok" name="stok" min="0" step="1" required>
                                </div>
                                <div class="form-group form-check">
                                    <input type="checkbox" class="form-check-input" id="outOfStock">
                                    <label class="form-check-label" for="outOfStock">Stok Habis</label>
                                </div>

                                <div class="form-group">
                                    <label>ID Stok Individu:</label>
                                    <div id="stok-ids-container" class="mt-2 border p-3" style="max-height: 300px; overflow-y: auto;">
                                        <!-- Stok IDs akan dimuat di sini -->
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Kalender Ketersediaan</label>
                                    <div class="d-flex justify-content-between mb-2">
                                        <button type="button" id="toggleDateSelection" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-calendar-check"></i> Pilih Tanggal
                                        </button>
                                        <div id="selectedDatesInfo" class="small text-muted">0 tanggal dipilih</div>
                                    </div>
                                    <div id="updateCalendar" class="update-calendar"></div>
                                    <div class="booking-btn-container">
                                        <button id="bookingBtn" class="btn">Booking</button>
                                    </div>
                                </div>
                                
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" id="cancelUpdate">Batal</button>
                                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                                </div>
                            </form>
                        </div>
            </div>      
        </div>
    </div>

    <!-- Add this HTML right before the closing </body> tag -->
    <div id="bookingModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Booking Details</h3>
                <button class="close-modal" id="closeBookingModal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="bookingName">Nama Pemesan</label>
                    <input type="text" class="form-control" id="bookingName" placeholder="Masukkan nama pemesan">
                </div>
                <div class="form-group">
                    <label>Tanggal yang Dipilih:</label>
                    <div id="selectedDatesPreview" style="max-height: 100px; overflow-y: auto; padding: 5px; border: 1px solid #eee; border-radius: 4px;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="cancelBooking">Batal</button>
                <button type="button" class="btn btn-primary" id="confirmBooking">Konfirmasi</button>
            </div>
        </div>
    </div>

  <nav aria-label="Page navigation">
        <ul class="pagination justify-content-center">
            <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $page - 1; ?>" aria-label="Previous">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
            <?php endif; ?>
            
            <?php 
            // Tampilkan maksimal 5 nomor halaman
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            
            if ($start_page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=1">1</a>
                </li>
                <?php if ($start_page > 2): ?>
                    <li class="page-item disabled">
                        <span class="page-link">...</span>
                    </li>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
            
            <?php if ($end_page < $total_pages): ?>
                <?php if ($end_page < $total_pages - 1): ?>
                    <li class="page-item disabled">
                        <span class="page-link">...</span>
                    </li>
                <?php endif; ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $total_pages; ?>"><?php echo $total_pages; ?></a>
                </li>
            <?php endif; ?>
            
            <?php if ($page < $total_pages): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $page + 1; ?>" aria-label="Next">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>

                <!-- Info paging -->
                <div class="text-center text-muted mb-3">
                    Menampilkan <?php echo min($records_per_page, $result->num_rows); ?> dari <?php echo $total_records; ?> data
                </div>
     </div>
    </div>

</div>

    </div>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
<script>
    // Deklarasikan di bagian paling atas script JavaScript
    let calendarInstance = null;
    let isSelectingDates = false;
    let selectedDates = [];
    let currentKostumId = null;
    let bookedDatesCache = [];

    function loadStokIds(kostumId) {
        console.log('Loading stok IDs for kostum:', kostumId);
        currentKostumId = kostumId;
        
        $.ajax({
            url: 'get_stok_ids.php',
            method: 'GET',
            data: {
                kostum_id: kostumId
            },
            dataType: 'json',
            success: function(response) {
                console.log('Stok IDs response:', response);
                if (response.status === 'success') {
                    displayStokIds(response.data);
                    
                    // PERBAIKAN: Update input stok dengan jumlah available saja
                    const availableCount = response.data.filter(item => item.status === 'available').length;
                    $('#stok').val(availableCount);
                    
                } else {
                    console.error('Error loading stok IDs:', response.message);
                    $('#stok-ids-container').html('<p class="text-warning">Tidak dapat memuat data stok</p>');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error loading stok IDs:', error, xhr.responseText);
                $('#stok-ids-container').html('<p class="text-warning">Tidak dapat memuat data stok</p>');
            }
        });
    }

    function displayStokIds(stokIds) {
        const container = $('#stok-ids-container');
        if (!stokIds || stokIds.length === 0) {
            container.html('<p>Tidak ada data stok tersedia</p>');
            return;
        }
        
        let html = '';
        let maintenanceCount = 0;
        
        stokIds.forEach((item, index) => {
            let statusText = item.display_status;
            let statusBadge = 'primary';
            let buttonHtml = '';
            let backgroundColor = 'transparent';
            
            switch (item.status) {
                case 'available':
                    statusBadge = 'success';
                    statusText = 'TERSEDIA';
                    break;
                case 'booked':
                    statusBadge = 'warning';
                    statusText = 'DIPESAN';
                    break;
                case 'rented':
                    statusBadge = 'info';
                    // PERBAIKAN: Tampilkan "SEDANG DISEWA" jika costume sudah received
                    if (item.is_costume_received == 1) {
                        statusText = 'SEDANG DISEWA';
                        backgroundColor = '#e3f2fd';
                    } else {
                        statusText = 'DALAM PROSES SEWA';
                    }
                    break;
                case 'maintenance':
                    statusBadge = 'secondary';
                    statusText = 'DALAM PERAWATAN';
                    maintenanceCount++;
                    
                    buttonHtml = `<button class="btn btn-sm btn-success ml-2" 
                                    onclick="markSingleReady('${item.stok_id}', ${currentKostumId})"
                                    style="padding: 2px 8px; font-size: 11px;">
                                    Siap Sewa
                                </button>`;
                    break;
            }
            
            html += `
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom" 
                    style="background-color: ${backgroundColor};">
                    <span><strong>STOK ${parseInt(item.stok_id)} - ID ${item.stok_id}</strong></span>
                    <div class="d-flex align-items-center">
                        <span class="badge badge-${statusBadge}">${statusText}</span>
                        ${buttonHtml}
                    </div>
                </div>
            `;
        });
        
        // Tampilkan tombol untuk menandai semua maintenance sebagai siap
        if (maintenanceCount > 0) {
            html += `
                <div class="mt-3 text-center">
                    <button class="btn btn-success btn-sm" 
                            onclick="markAllReady(${currentKostumId})"
                            style="padding: 5px 15px;">
                        <i class="fas fa-check-circle"></i> Tandai Semua Siap Sewa (${maintenanceCount})
                    </button>
                </div>
            `;
        }
        
        container.html(html);
    }

    function markSingleReady(stokId, kostumId) {
        if (confirm('Apakah Anda yakin kostum ini sudah siap untuk disewakan lagi?')) {
            // Disable button sementara untuk mencegah double click
            $(`button[onclick="markSingleReady('${stokId}', ${kostumId})"]`).prop('disabled', true);
            
            $.ajax({
                url: 'mark_single_ready.php',
                method: 'POST',
                data: {
                    stok_id: stokId,
                    kostum_id: kostumId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        showToast(response.message, 'success');
                        
                        // Update tampilan stok di halaman utama jika ada
                        if (response.new_stock) {
                            $(`#stok-${kostumId}`).text(response.new_stock);
                            $(`tr[data-kostum-id="${kostumId}"] .stok-display`).text(response.new_stock);
                        }
                        
                        // Refresh tampilan stok setelah delay singkat
                        setTimeout(function() {
                            loadStokIds(kostumId);
                        }, 500);
                    } else {
                        showToast(response.message, 'error');
                        // Re-enable button jika error
                        $(`button[onclick="markSingleReady('${stokId}', ${kostumId})"]`).prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error, xhr.responseText);
                    // Tidak tampilkan alert error, hanya log ke console
                    console.error('Error marking single ready:', xhr.responseText);
                    
                    // Re-enable button
                    $(`button[onclick="markSingleReady('${stokId}', ${kostumId})"]`).prop('disabled', false);
                    
                    // Coba refresh data meskipun ada error
                    setTimeout(function() {
                        loadStokIds(kostumId);
                    }, 500);
                }
            });
        }
    }

    function markAllReady(kostumId) {
        if (confirm('Apakah Anda yakin ingin menandai SEMUA kostum dalam perawatan siap untuk disewakan?')) {
            // Disable button sementara
            $(`button[onclick="markAllReady(${kostumId})"]`).prop('disabled', true);
            
            $.ajax({
                url: 'mark_ready_for_rent.php',
                method: 'POST',
                data: {
                    kostum_id: kostumId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        showToast(response.message, 'success');
                        
                        // Update tampilan stok di halaman utama jika ada
                        if (response.new_stock) {
                            $(`#stok-${kostumId}`).text(response.new_stock);
                            $(`tr[data-kostum-id="${kostumId}"] .stok-display`).text(response.new_stock);
                        }
                        
                        // Refresh tampilan stok setelah delay singkat
                        setTimeout(function() {
                            loadStokIds(kostumId);
                        }, 500);
                    } else {
                        showToast(response.message, 'error');
                        // Re-enable button jika error
                        $(`button[onclick="markAllReady(${kostumId})"]`).prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error, xhr.responseText);
                    // Tidak tampilkan alert error, hanya log ke console
                    console.error('Error marking all ready:', xhr.responseText);
                    
                    // Re-enable button
                    $(`button[onclick="markAllReady(${kostumId})"]`).prop('disabled', false);
                    
                    // Coba refresh data meskipun ada error
                    setTimeout(function() {
                        loadStokIds(kostumId);
                    }, 500);
                }
            });
        }
    }

    function setNotificationRead() {
        var xhr = new XMLHttpRequest();
        xhr.open("POST", "set_notification_read.php", true);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        xhr.send("read=1");
    }

    function toggleNotifications() {
        const dropdown = document.getElementById('notificationDropdown');
        dropdown.classList.toggle('show');
        
        // Jika dropdown dibuka, sembunyikan badge notifikasi & set status read ke server
        if (dropdown.classList.contains('show')) {
            const badge = document.getElementById('notificationBadge');
            if (badge) {
                badge.style.display = 'none';
            }
            setNotificationRead();
            document.addEventListener('click', closeNotificationsOutside);
        } else {
            document.removeEventListener('click', closeNotificationsOutside);
        }
    }

function closeNotificationsOutside(event) {
    const dropdown = document.getElementById('notificationDropdown');
    const bell = document.getElementById('notificationBell');
    
    if (!bell.contains(event.target)) {
        dropdown.classList.remove('show');
        document.removeEventListener('click', closeNotificationsOutside);
    }
}

function markAllAsRead() {
    // Simulate marking all as read
    const badge = document.getElementById('notificationBadge');
    if (badge) {
        badge.style.display = 'none';
    }
    
    // In real implementation, you would make an AJAX call here
    console.log('All notifications marked as read');
    showToast('Semua notifikasi telah ditandai dibaca', 'success');
}

function handleNotificationClick(type) {
    console.log('Notification clicked:', type);
    
    switch(type) {
        case 'order':
            showToast('Mengarahkan ke halaman pesanan...', 'info');
            // Redirect to pesanan page
            setTimeout(() => {
                window.location.href = 'pesanan.php';
            }, 1500);
            break;
        case 'reminder':
            showToast('Menampilkan peminjaman yang akan berakhir...', 'warning');
            // Could show modal or redirect
            break;
        case 'alert':
            showToast('Menampilkan peminjaman terlambat...', 'danger');
            // Handle overdue rentals
            break;
        case 'pencairan_dana':
            showToast('Menampilkan data pencairan dana...', 'info');
            // Bisa tambahkan aksi lain jika perlu
            break;
        default:
            showToast('Notifikasi diklik', 'info');
    }
    
    // Close dropdown
    document.getElementById('notificationDropdown').classList.remove('show');
}

function viewAllNotifications() {
    console.log('View all notifications clicked');
    showToast('Mengarahkan ke halaman pesanan...', 'info');
    
    // Redirect to pesanan page
    setTimeout(() => {
        window.location.href = 'pesanan.php';
    }, 1500);
    
    // Close dropdown
    document.getElementById('notificationDropdown').classList.remove('show');
}

function showToast(message, type = 'info') {
    // Hapus toast yang mungkin masih ada
    $('.custom-toast').remove();
    
    // Create toast element
    const toast = document.createElement('div');
    toast.className = 'custom-toast';
    toast.style.cssText = `
        position: fixed;
        top: 80px;
        right: 20px;
        background: ${type === 'success' ? '#28a745' : type === 'warning' ? '#ffc107' : type === 'error' ? '#dc3545' : '#17a2b8'};
        color: white;
        padding: 12px 20px;
        border-radius: 4px;
        z-index: 9999;
        font-size: 14px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        transform: translateX(100%);
        transition: transform 0.3s ease;
        max-width: 300px;
        word-wrap: break-word;
    `;
    toast.textContent = message;
    
    document.body.appendChild(toast);
    
    // Show toast
    setTimeout(() => {
        toast.style.transform = 'translateX(0)';
    }, 100);
    
    // Hide toast after 3 seconds
    setTimeout(() => {
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300);
    }, 3000);
}

// Add event listener for notification bell
document.getElementById('notificationBell').addEventListener('click', function(e) {
    e.stopPropagation();
    toggleNotifications();
});

// Close dropdown with ESC key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        document.getElementById('notificationDropdown').classList.remove('show');
    }
});

    function loadBookedDates(kostumId) {
        return new Promise((resolve) => {
            $.ajax({
                url: 'get_booked_dates.php?product_id=' + kostumId,
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {

                        bookedDatesCache = response.booked_dates.map(item => item.date);

                        const events = response.booked_dates.map(function(item) {
                            const hasName = item.booked_by && item.booked_by.trim() !== '';
                            return {
                                title: hasName ? item.booked_by : 'Booked',
                                start: item.date,
                                allDay: true,
                                display: 'background',
                                backgroundColor: hasName ? '#ff8c66' : '#ff4d4d',
                                className: hasName ? 'fc-event-booked-with-name' : 'fc-event-booked',
                                extendedProps: {
                                    isBooked: true,
                                    bookedBy: item.booked_by
                                }
                            };
                        });
                        resolve(events);
                    } else {
                        console.error('Gagal memuat booked dates:', response.message);
                        bookedDatesCache = [];
                        resolve([]);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error mengambil booked dates:', error);
                    bookedDatesCache = [];
                    resolve([]);
                }
            });
        });
    }

    function isDateBooked(dateStr) {
        // Cek dari cache terlebih dahulu
        if (bookedDatesCache.includes(dateStr)) {
            return true;
        }
        
        // Cek dari events kalender sebagai fallback
        if (calendarInstance) {
            const events = calendarInstance.getEvents();
            return events.some(event => {
                const eventDateStr = event.startStr;
                return eventDateStr === dateStr && event.extendedProps && event.extendedProps.isBooked;
            });
        }
        
        return false;
    }

    function checkDateRangeAvailability(startDateStr, duration) {
        const startDate = new Date(startDateStr);
        const unavailableDates = [];
        
        for (let i = 0; i < duration; i++) {
            const currentDate = new Date(startDate);
            currentDate.setDate(startDate.getDate() + i);
            const currentDateStr = currentDate.toISOString().split('T')[0];
            
            if (isDateBooked(currentDateStr)) {
                unavailableDates.push(currentDateStr);
            }
        }
        
        return unavailableDates;
    }

    document.addEventListener('DOMContentLoaded', function () {
        // Fungsi delete tetap sama
        
        // Di file INDEXXX3.php, update bagian AJAX upload-btn
        $(document).on('click', '.upload-btn', function() {
            const kostumId = $(this).data('id');
            const button = $(this);
            const row = $(this).closest('tr');
            const judulPost = row.find('td:nth-child(3) strong').text();
            
            if (confirm(`Anda yakin ingin mengunggah "${judulPost}"?`)) {
                button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Mengunggah...');

                $.ajax({
                    url: 'unggah_kostum.php',
                    type: 'POST',
                    data: { id: kostumId },
                    dataType: 'json',
                    success: function(response) {
                        if (response && response.status === 'success') {
                            location.reload(); // Reload halaman untuk update tampilan
                        } else {
                            const errorMsg = response?.message || 'Gagal mengunggah kostum';
                            alert(errorMsg);
                            button.prop('disabled', false).html('<i class="fas fa-upload"></i> Unggah');
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('Terjadi kesalahan: ' + error);
                        button.prop('disabled', false).html('<i class="fas fa-upload"></i> Unggah');
                    }
                });
            }
        });

        let isSelectingDates = false;
        let selectedDates = [];

        function initUpdateCalendar(kostumId) {
            currentKostumId = kostumId;

            // Ambil data jumlah hari sewa dari tabel - PERBAIKAN DISINI
            const row = $(`button.update-btn[data-id="${kostumId}"]`).closest('tr');
            const hargaText = row.find('td:nth-child(4)').text().trim();
            // Mencari pola "Rp xxx.xxx / x hari"
            const match = hargaText.match(/Rp\s*[\d.,]+\s*\/\s*(\d+)\s*hari/);
            const jumlahHari = match ? parseInt(match[1]) : 1;

            window.rentalDuration = jumlahHari;
            console.log('Durasi sewa:', window.rentalDuration, 'hari'); // Untuk debugging

            // Cek localStorage untuk selectedDates yang tersimpan
            const savedDates = localStorage.getItem('tempSelectedDates_' + kostumId);
            if (savedDates) {
                selectedDates = JSON.parse(savedDates);
                updateSelectedDatesInfo();
            }

            const calendarEl = document.getElementById('updateCalendar');
            
            // Hancurkan kalender sebelumnya jika ada
            if (calendarInstance) {
                calendarInstance.destroy();
                calendarInstance = null;
            }
            
            calendarInstance = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev',
                    center: 'title',
                    right: 'next'
                },
                height: 'auto',
                dayMaxEvents: true,
                validRange: {
                    start: new Date()
                },
                
                events: function(fetchInfo, successCallback, failureCallback) {
                    loadBookedDates(kostumId)
                        .then(events => successCallback(events))
                        .catch(() => successCallback([]));
                },
                
                dayCellDidMount: function(info) {
                    const dateStr = info.date.toISOString().split('T')[0];
                    
                    // Tandai tanggal yang sudah lewat
                    if (info.date < new Date()) {
                        info.el.classList.add('fc-day-disabled');
                        info.el.style.backgroundColor = '#f9f9f9';
                        info.el.style.color = '#ccc';
                        info.el.style.pointerEvents = 'none';
                        return;
                    }
                    
                    // Tandai tanggal yang sudah dipilih
                    if (selectedDates.includes(dateStr)) {
                        info.el.classList.add('fc-day-selected');
                    }
                },

                eventDidMount: function(info) {
                    const dateStr = info.event.startStr;
                    const dateEl = document.querySelector(`[data-date="${dateStr}"]`);
                    
                    if (dateEl && info.event.extendedProps && info.event.extendedProps.isBooked) {
                        // Terapkan styling untuk tanggal yang dibooking
                        dateEl.style.cursor = 'not-allowed';
                        dateEl.style.pointerEvents = 'none';
                        dateEl.style.opacity = '0.8';
                        
                        // Pastikan warna konsisten berdasarkan jenis booking
                        const hasName = info.event.extendedProps.bookedBy && 
                                    info.event.extendedProps.bookedBy.trim() !== '' && 
                                    info.event.extendedProps.bookedBy !== 'Pemilik';
                        
                        if (hasName) {
                            // Booking dengan nama (customer) - merah muda
                            dateEl.style.backgroundColor = '#ff8c66 !important';
                            dateEl.style.border = '1px solid #ff6b47';
                        } else {
                            // Booking tanpa nama atau pemilik - merah tua
                            dateEl.style.backgroundColor = '#ff4d4d !important';
                            dateEl.style.border = '1px solid #ff2626';
                        }
                    }
                },
                
                // Tambahkan eventsSet untuk memastikan styling diterapkan setelah events dimuat
                eventsSet: function(events) {
                    // Tunggu sebentar untuk memastikan DOM sudah terupdate
                    setTimeout(() => {
                        events.forEach(event => {
                            if (event.extendedProps && event.extendedProps.isBooked) {
                                const dateStr = event.startStr;
                                const dateEl = document.querySelector(`[data-date="${dateStr}"]`);
                                
                                if (dateEl) {
                                    dateEl.style.cursor = 'not-allowed';
                                    dateEl.style.pointerEvents = 'none';
                                    dateEl.style.opacity = '0.8';
                                    
                                    const hasName = event.extendedProps.bookedBy && 
                                                event.extendedProps.bookedBy.trim() !== '' && 
                                                event.extendedProps.bookedBy !== 'Pemilik';
                                    
                                    if (hasName) {
                                        dateEl.style.backgroundColor = '#ff8c66';
                                        dateEl.style.border = '1px solid #ff6b47';
                                    } else {
                                        dateEl.style.backgroundColor = '#ff4d4d';
                                        dateEl.style.border = '1px solid #ff2626';
                                    }
                                }
                            }
                        });
                    }, 100);
                },

                dateClick: function(info) {
                    if (!isSelectingDates) return;
                    if (info.date < new Date()) return;
                    
                    const dateStr = info.dateStr;
                
                    if (isDateBooked(dateStr)) {
                        return false;
                    }

                    const unavailableDates = checkDateRangeAvailability(dateStr, window.rentalDuration);

                    if (unavailableDates.length > 0) {
                        return false;
                    }
                    
                    // Hapus semua seleksi sebelumnya
                    selectedDates = [];
                    $('.fc-day-selected').removeClass('fc-day-selected');
                    
                    // Tambahkan rentang tanggal berdasarkan durasi sewa
                    const startDate = new Date(dateStr);
                    let datesAdded = 0;

                    for (let i = 0; i < window.rentalDuration; i++) {
                        const currentDate = new Date(startDate);
                        currentDate.setDate(startDate.getDate() + i);
                        const currentDateStr = currentDate.toISOString().split('T')[0];
                        
                        if (!isDateBooked(currentDateStr) && currentDate >= new Date()) {
                            selectedDates.push(currentDateStr);
                            const dateCell = $(`.fc-day[data-date="${currentDateStr}"]`);
                            dateCell.addClass('fc-day-selected');
                            datesAdded++;
                        }
                    }

                    if (datesAdded === 0) {
                        return false;
                    }
                    
                    localStorage.setItem('tempSelectedDates_' + currentKostumId, JSON.stringify(selectedDates));
                    
                    updateSelectedDatesInfo();
                    $('.booking-btn-container').toggle(selectedDates.length > 0);
                }
            });
            
        
            calendarInstance.render();

            // Reset state seleksi tanggal
            isSelectingDates = false;
            updateSelectedDatesInfo();
            $('#toggleDateSelection').removeClass('btn-primary').addClass('btn-outline-primary');

            // Di dalam initUpdateCalendar(), ubah bagian toggle date selection:
            $('#toggleDateSelection').off('click').on('click', function() {
                isSelectingDates = !isSelectingDates;
                $(this).toggleClass('btn-primary', isSelectingDates)
                    .toggleClass('btn-outline-primary', !isSelectingDates);
                
                // Hanya simpan jika toggle aktif dan modal booking tidak terlihat
                if (!isSelectingDates && selectedDates.length > 0 && !$('#bookingModal').is(':visible')) {
                    saveSelectedDates(kostumId);
                }

                if (!isSelectingDates) {
                    $('.booking-btn-container').hide();
                }
            });

            // Handle booking button click
            $('#bookingBtn').off('click').on('click', function() {
                if (selectedDates.length > 0) {
                    showBookingModal();
                }
            });

            // Reset state saat modal dibuka
            isSelectingDates = false;
            updateSelectedDatesInfo();
            $('#toggleDateSelection').removeClass('btn-primary').addClass('btn-outline-primary');
        }

        function showBookingModal() {
            if (!selectedDates || selectedDates.length === 0) return;
            
            $('#confirmBooking').off('click').on('click', confirmBooking);

            selectedDates.sort();

            // Format dates for display
            let formattedDates = [];
            if (selectedDates.length > 1) {
                // Jika lebih dari 1 tanggal, tampilkan sebagai rentang
                const startDate = new Date(selectedDates[0]);
                const endDate = new Date(selectedDates[selectedDates.length - 1]);
                
                formattedDates.push(
                    startDate.toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' }) + 
                    ' - ' + 
                    endDate.toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' }) + 
                    ` (${selectedDates.length} hari)`
                );
            } else {
                // Jika hanya 1 tanggal
                const date = new Date(selectedDates[0]);
                formattedDates.push(
                    date.toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' })
                );
            }
            
            // Update the preview in the modal
            $('#selectedDatesPreview').html(
                formattedDates.map(date => `<div class="selected-date-item">${date}</div>`).join('')
            );
            
            // Show the modal
            $('#bookingModal').css('display', 'flex');
            $('#bookingName').focus();
        }

        function updateSelectedDatesInfo() {
            const count = selectedDates.length;
            let infoText = count + ' tanggal dipilih';
            
            if (count > 1) {
                const startDate = new Date(selectedDates[0]);
                const endDate = new Date(selectedDates[count - 1]);
                infoText += ` (${startDate.getDate()} - ${endDate.getDate()} ${endDate.toLocaleDateString('id-ID', { month: 'short' })})`;
            }
            
            $('#selectedDatesInfo').text(infoText);
        }

        function saveSelectedDates(kostumId) {
            if (selectedDates.length === 0) return;
            
            // Hanya jalankan jika ini aksi pemilik (bukan customer) DAN tombol toggle sedang aktif
            if ($('#bookingModal').is(':visible') || !$('#toggleDateSelection').hasClass('btn-primary')) {
                return;
            }
            
            $('#bookingBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Memproses...');
            
            const bookingData = {
                product_id: kostumId,
                booked_by: 'Pemilik',
                dates: selectedDates
            };

            console.log('Data yang akan dikirim:', bookingData); // Debug log

            $.ajax({
                url: 'save_unavailable_dates.php',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(bookingData),
                dataType: 'json',
                success: function(response) {
                    console.log('Response dari server:', response); // Debug log
                    $('#bookingBtn').prop('disabled', false).html('Booking');
                    if (response && response.status === 'success') {
                        // Hapus data dari localStorage
                        localStorage.removeItem('tempSelectedDates_' + kostumId);
                        
                        // Refresh calendar
                        if (calendarInstance) {
                            calendarInstance.refetchEvents();
                        }

                        // Reset selection
                        selectedDates = [];
                        updateSelectedDatesInfo();
                        $('.booking-btn-container').hide();
                        $('.fc-day-selected').removeClass('fc-day-selected');
                        
                        // Tidak tampilkan alert untuk pemilik
                    } else {
                        console.error('Error dari server:', response?.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error AJAX:', error, xhr.responseText);
                    $('#bookingBtn').prop('disabled', false).html('Booking');
                }
            });
        }

        function confirmBooking() {
            console.log("Data yang akan dikirim:", {
                product_id: currentKostumId,
                booked_by: $('#bookingName').val(),
                dates: selectedDates
            });

            const bookingName = $('#bookingName').val().trim();
            
            if (!bookingName) {
                alert('Harap masukkan nama pemesan');
                return;
            }

            if (!selectedDates || selectedDates.length === 0) {
                alert('Silakan pilih setidaknya satu tanggal');
                return;
            }
            
            $('#confirmBooking').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Memproses...');
            
            const datesToBook = [...selectedDates];
            const bookingData = {
                product_id: currentKostumId,
                booked_by: bookingName,
                dates: datesToBook
            };

            console.log('Data booking yang akan dikirim:', bookingData); // Debug log

            $.ajax({
                url: 'save_unavailable_dates.php',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(bookingData),
                dataType: 'json',
                success: function(response) {
                    console.log('Response booking:', response); // Debug log
                    if (response && response.status === 'success') {
                        alert('Booking berhasil disimpan!');
                        $('#bookingModal').hide();
                        
                        localStorage.removeItem('tempSelectedDates_' + currentKostumId);
                        
                        if (calendarInstance) {
                            calendarInstance.refetchEvents();
                        }
                        
                        selectedDates = [];
                        updateSelectedDatesInfo();
                        $('.booking-btn-container').hide();
                        $('.fc-day-selected').removeClass('fc-day-selected');
                        $('#bookingName').val('');
                    } else {
                        alert(response?.message || 'Gagal menyimpan booking');
                    }
                    $('#confirmBooking').prop('disabled', false).html('Konfirmasi');
                },
                error: function(xhr, status, error) {
                    console.error('Error booking:', error, xhr.responseText);
                    let errorMsg = 'Terjadi kesalahan saat menyimpan booking';
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response && response.message) {
                            errorMsg = response.message;
                        }
                    } catch (e) {
                        console.error("Error parsing response:", e);
                    }
                    alert(errorMsg);
                    $('#confirmBooking').prop('disabled', false).html('Konfirmasi');
                }
            });
        }

        // Update the booking button click handler
        $('#bookingBtn').on('click', function() {
            if (selectedDates.length > 0) {
                showBookingModal();
            }
        });

        // Add handlers for the booking modal
        $('#closeBookingModal, #cancelBooking').on('click', function() {
            $('#bookingModal').hide();
        });

        $('#confirmBooking').on('click', confirmBooking);

        // Allow pressing Enter in the name field to confirm
        $('#bookingName').on('keypress', function(e) {
            if (e.which === 13) {
                confirmBooking();
            }
        });

        
        // Handle tombol Update Stok
        

        function previewStokIds(stokCount) {
            const container = $('#stokIdList');
            let html = '<div class="text-info mb-2"><small><em>Preview ID Stok yang akan dibuat:</em></small></div>';
            
            for (let i = 1; i <= stokCount; i++) {
                const stokId = String(i).padStart(3, '0');
                html += `<div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <span><strong>STOK ${i} - ID ${stokId}</strong></span>
                            <span class="badge badge-success">TERSEDIA</span>
                        </div>`;
            }
            
            container.html(html);
            $('#stokIdContainer').show();
        }


        $('#stok').on('input', function() {
            const stokValue = parseInt($(this).val()) || 0;
            if (stokValue > 0) {
                previewStokIds(stokValue);
                $('#outOfStock').prop('checked', false);
            } else {
                $('#stokIdContainer').hide();
            }
        });

        $('#outOfStock').on('change', function() {
            if ($(this).is(':checked')) {
                $('#stok').val(0);
                $('#stokIdContainer').hide();
            }
        });

        $(document).on('click', '.update-btn', function() {
            setTimeout(() => {
                const kostumId = $(this).data('id');
                currentKostumId = kostumId;
                
                // Load data kostum
                $.ajax({
                    url: 'get_kostum_data.php',
                    type: 'GET',
                    data: { id: kostumId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            const data = response.data;
                            
                            // Isi form dengan data existing
                            $('#kostumId').val(data.id);
                            $('#updateStockModal').show();
                            
                            // Load existing stock IDs dan set nilai stok berdasarkan available count
                            loadStokIds(kostumId);
                            
                            $('#updateStockForm [name="id"]').val(data.id);
                            
                            // Initialize calendar
                            setTimeout(() => {
                                initUpdateCalendar(kostumId);
                            }, 100);
                        }
                    },
                    error: function() {
                        alert('Error loading data');
                    }
                });
            }, 100);
        });

         $(document).on('click', function(e) {
            if ($(e.target).is('#updateStockModal')) {
                $('#updateStockModal').hide();
            }
        });

        // Tutup modal saat klik di luar
        $(document).on('click', function(e) {
            if ($(e.target).hasClass('modal-overlay') && $('#bookingModal').is(':visible')) {
                $('#bookingModal').hide();
            }
        });

        // Fungsi untuk handle tombol hapus
        $(document).on('click', '.delete-btn', function() {
            const kostumId = $(this).data('id');
            const row = $(this).closest('tr');
            const judulPost = row.find('td:nth-child(3) strong').text();
            
            if (confirm(`Anda yakin ingin menghapus "${judulPost}"? Aksi ini tidak dapat dibatalkan!`)) {
                const button = $(this);
                button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Menghapus...');
                
                $.ajax({
                    url: 'hapus_kostum.php',
                    type: 'POST',
                    data: { id: kostumId },
                    dataType: 'json',
                    success: function(response) {
                        if (response && response.status === 'success') {
                            // Hapus baris dari tabel
                            row.fadeOut(300, function() {
                                $(this).remove();
                                // Update nomor urut
                                $('tbody tr').each(function(index) {
                                    $(this).find('td:first').text(index + 1);
                                });
                            });
                        } else {
                            const errorMsg = response?.message || 'Gagal menghapus kostum';
                            alert(errorMsg);
                            button.prop('disabled', false).html('<i class="fas fa-trash"></i> Hapus');
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('Terjadi kesalahan: ' + error);
                        button.prop('disabled', false).html('<i class="fas fa-trash"></i> Hapus');
                    }
                });
            }
        });

        

        // Handle form submission
        $('#updateStockForm').on('submit', function(e) {
            e.preventDefault();
            
            const kostumId = $('#kostumId').val();
            const stok = $('#stok').val();
            
            console.log('Submitting form with kostumId:', kostumId, 'stok:', stok);
            
            // Validasi input
            if (!kostumId) {
                alert('ID kostum tidak ditemukan');
                return;
            }
            
            if (isNaN(stok) || stok === '') {
                alert('Harap masukkan jumlah stok yang valid');
                return;
            }
            
            // Show loading state
            const submitBtn = $(this).closest('.modal-content').find('button[type="submit"]');
            const originalText = submitBtn.html();
            submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Menyimpan...');
            
            $.ajax({
                url: 'update_stok.php',
                type: 'POST',
                data: {
                    id: kostumId,
                    stok: parseInt(stok)
                },
                dataType: 'json',
                success: function(response) {
                    console.log('Update response:', response);
                    if (response.status === 'success') {
                        alert('Stok berhasil diupdate!');
                        $('#updateStockModal').hide();
                        location.reload();
                    } else {
                        alert('Error: ' + response.message);
                    }
                    submitBtn.prop('disabled', false).html(originalText);
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', {
                        status: xhr.status,
                        responseText: xhr.responseText,
                        error: error
                    });
                    alert('Terjadi kesalahan: ' + error);
                    submitBtn.prop('disabled', false).html(originalText);
                }
            });
        });

        // Close modal handlers tetap sama
        $('#closeModal, #cancelUpdate').on('click', function() {
            $('#updateStockModal').hide();
        });
            
        // Close modal when clicking outside
        $(document).on('click', function(e) {
            if ($(e.target).hasClass('modal-overlay')) {
                $('#updateStockModal').hide();
            }
        });
    });
</script>
</body>
</html>