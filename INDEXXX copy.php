<?php
session_name('penyedia_session');
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'check_blacklist_provider.php';

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

    $id_penyedia = null;

    // Cek berbagai kemungkinan nama session
    $sessionKeys = ['id_user', 'user_id', 'id', 'login_id', 'account_id'];
    foreach ($sessionKeys as $key) {
        if (isset($_SESSION[$key]) && !empty($_SESSION[$key])) {
            $id_penyedia = $_SESSION[$key];
            break;
        }
    }

    // Function untuk mendapatkan notifikasi penyedia
    function getProviderNotifications($conn, $id_penyedia) {
        $notifications = [];
        
        if ($id_penyedia) {
            // Cek pesanan baru yang status approved
            $sql = "SELECT COUNT(*) as count FROM pesanan WHERE id_penyedia = ? AND status = 'approved'";
            $stmt = $conn->prepare($sql);
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
            
            // Cek peminjaman yang terlambat
            $sql = "SELECT COUNT(*) as count FROM peminjaman 
                    WHERE id_penyedia = ? AND status_peminjaman = 'sedang_berjalan' 
                    AND CURDATE() > tanggal_selesai";
            $stmt = $conn->prepare($sql);
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


    function getPemasukanBulanIni($conn, $id_penyedia) {
        $bulanIni = date('Y-m'); // Format: YYYY-MM untuk bulan saat ini
        $totalPemasukan = 0;
        
        if ($id_penyedia) {
            // Query untuk mengambil total subtotal dari riwayat pesanan yang sudah dicairkan dana nya di bulan ini
            $query = "SELECT SUM(COALESCE(ti.subtotal, 0)) as total_pemasukan
                    FROM riwayat_pesanan rp
                    LEFT JOIN transaksi_items ti ON rp.id_transaksi = ti.id_transaksi AND rp.id_produk = ti.id_produk
                    LEFT JOIN peminjaman pm ON rp.id_transaksi = pm.id_transaksi AND rp.id_produk = pm.id_produk
                    WHERE rp.id_penyedia = ? 
                    AND rp.status = 'selesai'
                    AND pm.bukti_pencairan_dana IS NOT NULL 
                    AND pm.bukti_pencairan_dana != ''
                    AND DATE_FORMAT(rp.tanggal_selesai, '%Y-%m') = ?";
            
            $stmt = $conn->prepare($query);
            if ($stmt) {
                $stmt->bind_param("is", $id_penyedia, $bulanIni);
                $stmt->execute();
                $result = $stmt->get_result()->fetch_assoc();
                $totalPemasukan = $result['total_pemasukan'] ?? 0;
            }
        }
        
        return $totalPemasukan;
    }

    function getDanaMenungguPencairan($conn, $id_penyedia) {
        $totalDana = 0;
        
        if ($id_penyedia) {
            // Query untuk mengambil total subtotal dari riwayat pesanan yang dana belum dicairkan
            $query = "SELECT SUM(COALESCE(ti.subtotal, 0)) as total_dana
                      FROM riwayat_pesanan rp
                      LEFT JOIN transaksi_items ti ON rp.id_transaksi = ti.id_transaksi AND rp.id_produk = ti.id_produk
                      LEFT JOIN peminjaman pm ON rp.id_transaksi = pm.id_transaksi AND rp.id_produk = pm.id_produk
                      WHERE rp.id_penyedia = ? 
                      AND rp.status = 'selesai'
                      AND (pm.bukti_pencairan_dana IS NULL OR pm.bukti_pencairan_dana = '')";
            
            $stmt = $conn->prepare($query);
            if ($stmt) {
                $stmt->bind_param("i", $id_penyedia);
                $stmt->execute();
                $result = $stmt->get_result()->fetch_assoc();
                $totalDana = $result['total_dana'] ?? 0;
            }
        }
        
        return $totalDana;
    }

    function getDanaBerhasilDicairkan($conn, $id_penyedia) {
        $totalDana = 0;
        
        if ($id_penyedia) {
            // Query untuk mengambil total subtotal dari riwayat pesanan yang dana sudah dicairkan
            $query = "SELECT SUM(COALESCE(ti.subtotal, 0)) as total_dana
                      FROM riwayat_pesanan rp
                      LEFT JOIN transaksi_items ti ON rp.id_transaksi = ti.id_transaksi AND rp.id_produk = ti.id_produk
                      LEFT JOIN peminjaman pm ON rp.id_transaksi = pm.id_transaksi AND rp.id_produk = pm.id_produk
                      WHERE rp.id_penyedia = ? 
                      AND rp.status = 'selesai'
                      AND pm.bukti_pencairan_dana IS NOT NULL 
                      AND pm.bukti_pencairan_dana != ''";
            
            $stmt = $conn->prepare($query);
            if ($stmt) {
                $stmt->bind_param("i", $id_penyedia);
                $stmt->execute();
                $result = $stmt->get_result()->fetch_assoc();
                $totalDana = $result['total_dana'] ?? 0;
            }
        }
        
        return $totalDana;
    }

    function getTransaksiTerbaru($conn, $id_penyedia, $limit = 5) {
        $transaksi = [];
        
        if ($id_penyedia) {
            $query = "SELECT 
                        p.id_pesanan,
                        p.id_transaksi,
                        COALESCE(
                            NULLIF(t.nama_lengkap, ''),
                            NULLIF(p.nama_penyewa, '0'),
                            NULLIF(p.nama_penyewa, ''),
                            'Tidak diketahui'
                        ) as nama_penyewa,
                        p.nomor_hp,
                        p.nama_kostum,
                        p.tanggal_mulai,
                        p.jumlah_hari,
                        COALESCE(ti.subtotal, 0) as subtotal,
                        p.status,
                        p.created_at
                    FROM pesanan p
                    LEFT JOIN transaksi t ON p.id_transaksi = t.id_transaksi
                    LEFT JOIN transaksi_items ti ON p.id_transaksi = ti.id_transaksi AND p.id_produk = ti.id_produk
                    WHERE p.id_penyedia = ?
                    ORDER BY p.created_at DESC
                    LIMIT ?";
            
            $stmt = $conn->prepare($query);
            if ($stmt) {
                $stmt->bind_param("ii", $id_penyedia, $limit);
                $stmt->execute();
                $result = $stmt->get_result();
                
                while ($row = $result->fetch_assoc()) {
                    $transaksi[] = $row;
                }
                
                $stmt->close();
            }
        }
        
        return $transaksi;
    }

    // Dapatkan pemasukan bulan ini
    $pemasukanBulanIni = getPemasukanBulanIni($conn, $id_penyedia);
    $danaMenungguPencairan = getDanaMenungguPencairan($conn, $id_penyedia);
    $danaBerhasilDicairkan = getDanaBerhasilDicairkan($conn, $id_penyedia);
    $transaksiTerbaru = getTransaksiTerbaru($conn, $id_penyedia, 5);

    // Dapatkan notifikasi
    $notifications = getProviderNotifications($conn, $id_penyedia);

    if (!isset($_SESSION['notification_read'])) {
        $_SESSION['notification_read'] = false;
    }
    if ($_SESSION['notification_read'] === true) {
        $unreadCount = 0;
    } else {
        $unreadCount = array_sum(array_column($notifications, 'count'));
    }

    // Tambahkan kode berikut SETELAH perhitungan $unreadCount
    if ($unreadCount > 0 && $_SESSION['notification_read'] === true) {
        $_SESSION['notification_read'] = false;
    }


    $totalKostum = 0;
    if ($id_penyedia) {
        $sql = "SELECT COUNT(*) as total FROM form_katalog WHERE id_user = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $id_penyedia);
            $stmt->execute();
            $result = $stmt->get_result();
            $totalKostum = $result->fetch_assoc()['total'];
            $stmt->close();
        }
    }

    // Hitung transaksi berjalan berdasarkan id_penyedia
    $totalTransaksi = 0;
    $menungguKonfirmasi = 0;
    $sedangDisewa = 0;

    if ($id_penyedia) {
        // Total transaksi berjalan (semua pesanan yang belum selesai)
        $sql = "SELECT COUNT(*) as total FROM pesanan WHERE id_penyedia = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $id_penyedia);
            $stmt->execute();
            $result = $stmt->get_result();
            $totalTransaksi = $result->fetch_assoc()['total'];
            $stmt->close();
        }
        
        // Menunggu konfirmasi (status 'approved')
        $sql = "SELECT COUNT(*) as total FROM pesanan WHERE id_penyedia = ? AND status = 'approved'";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $id_penyedia);
            $stmt->execute();
            $result = $stmt->get_result();
            $menungguKonfirmasi = $result->fetch_assoc()['total'];
            $stmt->close();
        }
        
        // Sedang disewa (status selain 'approved')
        $sql = "SELECT COUNT(*) as total FROM pesanan WHERE id_penyedia = ? AND status != 'approved'";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $id_penyedia);
            $stmt->execute();
            $result = $stmt->get_result();
            $sedangDisewa = $result->fetch_assoc()['total'];
            $stmt->close();
        }
    }
    
} catch (Exception $e) {
    error_log("Error in INDEXXX.php: " . $e->getMessage());
    $notifications = [];
    $unreadCount = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Website dengan Navbar dan Sidebar</title>
    <link crossorigin="anonymous" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="dashboard.css">
    <!-- <link rel="stylesheet" href="index.css"> -->
    
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
            max-width: 1200px;
            margin: 0 auto;
        }

         /* CSS untuk Sidebar */
    .sidebar {
        background-color: #EFEFEF;
        width: 220px;
        padding: 1rem 1rem;
        border-right: 1px solid #e0e0e0;
        box-shadow: 2px 0 5px rgba(0,0,0,0.05);
        transition: all 0.3s ease;
        margin-left: 0; /* Hapus margin negatif */
        padding-left: 20px;
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
        padding-right : 40px;
        white-space : nowrap;
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
            width: calc(100% - 200px);
        }

        .main-content h1 {
            margin-bottom: 1rem;
        }

        .main-content p {
            margin-bottom: 1rem;
        }

        .content {
            width: 100%;
            padding: 20px;
        }

        .form-section {
            width: 100%;
        }

        .row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -15px;
        }

        .col-md-4 {
            flex: 0 0 33.333333%;
            max-width: 33.333333%;
            padding: 0 15px;
            margin-bottom: 20px;
        }

        .card {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .table-container {
            width: 115%;
            overflow-x: auto;
            margin-top: 20px;
        }

        table {
            width: 100%;
            min-width : 100%;
            table-layout : auto;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background-color: #f4f4f4;
            font-weight: bold;
        }

        .scrollable {
            overflow-x: auto;
        }

        .home-icon {
            position: relative;
            display: inline-block;
            padding: 0.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .home-icon:hover {
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .home-icon i {
            font-size: 1.2rem;
            color: white;
        }

        .home-icon a {
            color: white !important;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .home-icon a:hover {
            background-color: transparent !important;
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
        <aside class="sidebar" style="margin-left : -110px; padding-left : 15px; padding-right : 100px;">
            <ul>
                <li><a href="INDEXXX.php">Dashboard</a></li>
                <li><a href="INDEXXX2.php">Profile</a></li>
                <li><a href="INDEXXX3.php" style ="padding-right : 100px;">Katalog Kostum</a></li>
                <li><a href="pesanan.php">Pesanan</a></li>
            </ul>
        </aside>
        <main class="col-md-20 ml-sm-auto col-lg-30 px-4 content" role="main" style="margin-left : 20px; margin-top: -50px;">
            <div class="form-section mt-4">
                <div class="form-header" style = "margin-right : -200px;">
                    <h2 class="form-title">Dashboard</h2>
                </div>
                <div class="row" style="margin-right: -150px;">
                    <!-- Card 1 - Total Kostum -->
                    <div class="col-md-4">
                        <div class="card text-white" style="background: linear-gradient(135deg, #6c757d 0%, #495057 100%); border: none; box-shadow: 0 8px 25px rgba(108, 117, 125, 0.3);">
                            <div class="card-body text-center" style="padding: 25px 20px;">
                                <div style="background: rgba(255,255,255,0.2); width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px;">
                                    <i class="fas fa-tshirt fa-2x"></i>
                                </div>
                                <h3 class="card-title" style="font-weight: 700; font-size: 28px; margin-bottom: 8px;"><?= $totalKostum ?? 0 ?></h3>
                                <p class="card-text" style="font-size: 14px; opacity: 0.9; margin-bottom: 0;">Total Kostum</p>
                            </div>
                        </div>
                    </div>

                    <!-- Card 2 - Transaksi Berjalan -->
                    <div class="col-md-4">
                        <div class="card text-white" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); border: none; box-shadow: 0 8px 25px rgba(23, 162, 184, 0.3);">
                            <div class="card-body text-center" style="padding: 25px 20px;">
                                <div style="background: rgba(255,255,255,0.2); width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px;">
                                    <i class="fas fa-exchange-alt" style="font-size: 24px; color: white;"></i>
                                </div>
                                <h3 class="card-title" style="font-weight: 700; font-size: 28px; margin-bottom: 8px;"><?= $totalTransaksi ?? 0 ?></h3>
                                <p class="card-text" style="font-size: 14px; opacity: 0.9; margin-bottom: 8px;">Transaksi Berjalan</p>
                                
                                <!-- Detail Breakdown -->
                                <div style="background: rgba(255,255,255,0.15); border-radius: 8px; padding: 10px; margin-top: 10px;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                                        <span style="font-size: 12px; opacity: 0.9;">Menunggu Konfirmasi:</span>
                                        <span style="font-size: 12px; font-weight: 600;"><?= $menungguKonfirmasi ?? 0 ?></span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <span style="font-size: 12px; opacity: 0.9;">Sedang Disewa:</span>
                                        <span style="font-size: 12px; font-weight: 600;"><?= $sedangDisewa ?? 0 ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Card 3 - Pemasukan Bulan Ini -->
                    <div class="col-md-4">
                        <div class="card text-white" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); border: none; box-shadow: 0 8px 25px rgba(40, 167, 69, 0.3);">
                            <div class="card-body text-center" style="padding: 25px 20px;">
                                <div style="background: rgba(255,255,255,0.2); width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px;">
                                    <i class="fas fa-wallet fa-2x"></i>
                                </div>
                                <h3 class="card-title" style="font-weight: 700; font-size: 28px; margin-bottom: 8px;">
                                    Rp <?= $pemasukanBulanIni > 0 ? number_format($pemasukanBulanIni, 0, ',', '.') : '0' ?>
                                </h3>
                                <p class="card-text" style="font-size: 14px; opacity: 0.9; margin-bottom: 0;">Pemasukan Bulan Ini</p>
                                <small style="font-size: 11px; opacity: 0.8;">
                                    <?= date('F Y') ?> • Dana dicairkan
                                </small>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Section Pencairan Dana -->
                <div class="row mt-4" style="margin-right: -150px;">
                    <div class="col-md-6">
                        <div class="card" style="border: none; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border-radius: 12px;">
                            <div class="card-header" style="background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%); border-radius: 12px 12px 0 0; border: none; padding: 20px;">
                                <h5 class="mb-0" style="color: white; font-weight: 600; display: flex; align-items: center;">
                                    <i class="fas fa-hourglass-half me-2" style="margin-right: 10px;"></i>
                                    Dana Menunggu Pencairan
                                </h5>
                            </div>
                            <div class="card-body" style="padding: 30px; text-align: center;">
                                <div style="background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%); width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; opacity: 0.9;">
                                    <i class="fas fa-coins fa-2x" style="color: white;"></i>
                                </div>
                                <h2 style="color: #dc6545; font-weight: 700; margin-bottom: 10px;">
                                    Rp <?= $danaMenungguPencairan > 0 ? number_format($danaMenungguPencairan, 0, ',', '.') : '0' ?>
                                </h2>
                                <p class="text-muted" style="margin-bottom: 15px;">Dana yang sedang diproses</p>
                                <small class="text-muted">
                                    <i class="fas fa-info-circle"></i>
                                    Proses pencairan 1-3 hari kerja
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card" style="border: none; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border-radius: 12px;">
                            <div class="card-header" style="background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%); border-radius: 12px 12px 0 0; border: none; padding: 20px;">
                                <h5 class="mb-0" style="color: white; font-weight: 600; display: flex; align-items: center;">
                                    <i class="fas fa-check-circle me-2" style="margin-right: 10px;"></i>
                                    Dana Berhasil Dicairkan
                                </h5>
                            </div>
                            <div class="card-body" style="padding: 30px; text-align: center;">
                                <div style="background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%); width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                                    <i class="fas fa-money-check-alt fa-2x" style="color: white;"></i>
                                </div>
                                <h2 style="color: #28a745; font-weight: 700; margin-bottom: 10px;">
                                    Rp <?= $danaBerhasilDicairkan > 0 ? number_format($danaBerhasilDicairkan, 0, ',', '.') : '0' ?>
                                </h2>
                                <p class="text-muted" style="margin-bottom: 15px;">Total dana yang telah dicairkan</p>
                                <small class="text-success">
                                    <i class="fas fa-calendar-check"></i>
                                    Terakhir dicairkan: 8 Sep 2025
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabel Ringkasan Transaksi Terbaru -->
                <div class="table-container mt-4">
                    <div class="card" style="border: none; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border-radius: 12px;">
                        <div class="card-header" style="background: linear-gradient(135deg, #495057 0%, #343a40 100%); border-radius: 12px 12px 0 0; border: none; padding: 20px;">
                            <h5 class="mb-0" style="color: white; font-weight: 600; display: flex; align-items: center;">
                                <i class="fas fa-list-alt me-2" style="margin-right: 10px;"></i>
                                Transaksi Terbaru
                            </h5>
                        </div>
                        <div class="card-body" style="padding: 0;">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0" style="border-collapse: separate; border-spacing: 0;">
                                    <thead style="background-color: #f8f9fa;">
                                        <tr>
                                            <th style="padding: 15px; font-weight: 600; color: #495057; border: none;">Penyewa</th>
                                            <th style="padding: 15px; font-weight: 600; color: #495057; border: none;">Kostum</th>
                                            <th style="padding: 15px; font-weight: 600; color: #495057; border: none;">Tanggal</th>
                                            <th style="padding: 15px; font-weight: 600; color: #495057; border: none;">Durasi</th>
                                            <th style="padding: 15px; font-weight: 600; color: #495057; border: none;">Total</th>
                                            <th style="padding: 15px; font-weight: 600; color: #495057; border: none;">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($transaksiTerbaru)): ?>
                                            <?php foreach ($transaksiTerbaru as $index => $transaksi): ?>
                                        <tr style="border-bottom: 1px solid #f0f0f0;">
                                            <td style="padding: 15px; border: none; vertical-align: middle;">
                                                <div style="font-weight: 600; color: #2c3e50; margin-bottom: 4px;">
                                                    <?= htmlspecialchars($transaksi['nama_penyewa']) ?>
                                                </div>
                                                <small style="color: #7f8c8d;">
                                                    <?= !empty($transaksi['nomor_hp']) ? htmlspecialchars($transaksi['nomor_hp']) : 'No. HP tidak tersedia' ?>
                                                </small>
                                            </td>
                                            <td style="padding: 15px; border: none; vertical-align: middle;">
                                                <div style="font-weight: 500; color: #2c3e50;">
                                                    <?= htmlspecialchars($transaksi['nama_kostum']) ?>
                                                </div>
                                            </td>
                                            <td style="padding: 15px; border: none; vertical-align: middle; color: #495057;">
                                                <?= date('d/m/Y', strtotime($transaksi['tanggal_mulai'])) ?>
                                            </td>
                                            <td style="padding: 15px; border: none; vertical-align: middle; color: #495057;">
                                                <?= $transaksi['jumlah_hari'] ?> hari
                                            </td>
                                            <td style="padding: 15px; border: none; vertical-align: middle;">
                                                <div style="font-weight: 600; color: #28a745;">
                                                    Rp <?= number_format($transaksi['subtotal'], 0, ',', '.') ?>
                                                </div>
                                            </td>
                                            <td style="padding: 15px; border: none; vertical-align: middle;">
                                                <span class="badge" style="background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%); color: white; padding: 8px 16px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; box-shadow: 0 2px 8px rgba(255, 193, 7, 0.3);">
                                                    <i class="fas fa-clock" style="margin-right: 6px; font-size: 10px;"></i>
                                                    Menunggu Konfirmasi
                                                </span>
                                            </td>
                                        </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                        <tr style="border-bottom: 1px solid #f0f0f0;">
                                            <td colspan="6" style="padding: 30px; border: none; text-align: center; color: #6c757d;">
                                                <i class="fas fa-inbox" style="font-size: 48px; color: #e9ecef; margin-bottom: 15px; display: block;"></i>
                                                <div style="font-size: 16px; font-weight: 500; margin-bottom: 8px;">Belum ada transaksi</div>
                                                <small>Transaksi akan muncul setelah ada pesanan masuk</small>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="card-footer" style="background: #f8f9fa; border-top: 1px solid #e9ecef; padding: 15px 20px; border-radius: 0 0 12px 12px;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <small class="text-muted">Menampilkan <?= count($transaksiTerbaru) ?> dari <?= $totalTransaksi ?> transaksi terbaru</small>
                                    <a href="pesanan.php" style="color: #485fc7; text-decoration: none; font-size: 14px; font-weight: 500;">
                                        <i class="fas fa-arrow-right" style="margin-left: 8px;"></i>
                                        Lihat Semua Pesanan
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

<script>
// Notification functions

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
            showToast('Menampilkan pesanan baru...', 'info');
            highlightApprovedOrders();
            break;
        case 'reminder':
            showToast('Menampilkan peminjaman yang akan berakhir...', 'warning');
            break;
        case 'alert':
            showToast('Menampilkan peminjaman terlambat...', 'danger');
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

function highlightApprovedOrders() {
    // Dashboard biasanya tidak ada tabel, tapi fungsi ini tetap disediakan agar konsisten
    // Jika ada tabel, tambahkan highlight di sini
}

function viewAllNotifications() {
    console.log('View all notifications clicked');
    showToast('Fitur lihat semua notifikasi akan segera tersedia', 'info');
    document.getElementById('notificationDropdown').classList.remove('show');
}

function showToast(message, type = 'info') {
    // Create toast element
    const toast = document.createElement('div');
    toast.style.cssText = `
        position: fixed;
        top: 80px;
        right: 20px;
        background: ${type === 'success' ? '#28a745' : type === 'warning' ? '#ffc107' : type === 'danger' ? '#dc3545' : '#17a2b8'};
        color: white;
        padding: 12px 20px;
        border-radius: 4px;
        z-index: 9999;
        font-size: 14px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        transform: translateX(100%);
        transition: transform 0.3s ease;
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
</script>

<?php if (isset($conn)) { $conn->close(); } ?>

</body>
</html>