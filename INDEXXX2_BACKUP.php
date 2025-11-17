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

// Function untuk mendapatkan notifikasi penyedia
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

$notifications = getProviderNotifications($conn, $iduser);
$unreadCount = array_sum(array_column($notifications, 'count'));

// Ambil data toko berdasarkan id_user
$query = "SELECT * FROM data_toko WHERE id_user = ?";
$stmt = $conn->prepare($query);
if (!$stmt) {
    echo json_encode(['error' => 'Persiapan query gagal: ' . $conn->error]);
    exit();
}
$stmt->bind_param("i", $iduser);
$stmt->execute();
$data_toko = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = htmlspecialchars($_POST['username']);
    $name = htmlspecialchars($_POST['name']);
    $medsos_instagram = htmlspecialchars($_POST['medsos_instagram']);
    $medsos_whatsapp = htmlspecialchars($_POST['medsos_whatsapp']);
    $alamat = htmlspecialchars($_POST['alamat']);
    $deskripsi = htmlspecialchars($_POST['deskripsi']);

    // Validasi URL untuk Instagram
    if (!empty($medsos_instagram) && !filter_var($medsos_instagram, FILTER_VALIDATE_URL)) {
        echo json_encode(['error' => 'Instagram harus berupa URL yang valid (contoh: https://instagram.com/username)']);
        exit();
    }

    // Validasi URL untuk WhatsApp
    if (!empty($medsos_whatsapp) && !filter_var($medsos_whatsapp, FILTER_VALIDATE_URL)) {
        echo json_encode(['error' => 'WhatsApp harus berupa URL yang valid (contoh: https://wa.me/nomor)']);
        exit();
    }

    // Validasi direktori upload
    $upload_dir = 'foto_profil/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $profile_photo = $data_toko['profile_photo'] ?? null; // Keep existing photo
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == UPLOAD_ERR_OK) {
        $file_type = mime_content_type($_FILES['profile_photo']['tmp_name']);
        if (in_array($file_type, ['image/jpeg', 'image/png', 'image/jpg'])) {
            $file_extension = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
            $new_filename = 'profile_' . $iduser . '_' . time() . '.' . $file_extension;
            $profile_photo = $upload_dir . $new_filename;
            
            if (!move_uploaded_file($_FILES['profile_photo']['tmp_name'], $profile_photo)) {
                echo json_encode(['error' => 'Gagal mengunggah foto profil']);
                exit();
            }
        } else {
            echo json_encode(['error' => 'Format foto profil tidak valid. Gunakan JPG, JPEG, atau PNG']);
            exit();
        }
    }

    // Handle Foto Toko Upload
    $foto_toko = $data_toko['foto_toko'] ?? null; // Keep existing photo
    if (isset($_FILES['foto_toko']) && $_FILES['foto_toko']['error'] == UPLOAD_ERR_OK) {
        $file_type = mime_content_type($_FILES['foto_toko']['tmp_name']);
        if (in_array($file_type, ['image/jpeg', 'image/png', 'image/jpg'])) {
            $file_extension = pathinfo($_FILES['foto_toko']['name'], PATHINFO_EXTENSION);
            $new_filename = 'toko_' . $iduser . '_' . time() . '.' . $file_extension;
            $foto_toko = $upload_dir . $new_filename;
            
            if (!move_uploaded_file($_FILES['foto_toko']['tmp_name'], $foto_toko)) {
                echo json_encode(['error' => 'Gagal mengunggah foto toko']);
                exit();
            }
        } else {
            echo json_encode(['error' => 'Format foto toko tidak valid. Gunakan JPG, JPEG, atau PNG']);
            exit();
        }
    }

    $query = "INSERT INTO data_toko (id_user, username, name, medsos_instagram, medsos_whatsapp, alamat, deskripsi, profile_photo, foto_toko) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
              ON DUPLICATE KEY UPDATE 
              username = ?, 
              name = ?, 
              medsos_instagram = ?, 
              medsos_whatsapp = ?, 
              alamat = ?, 
              deskripsi = ?, 
              profile_photo = ?, 
              foto_toko = ?";

$stmt = $conn->prepare($query);
if (!$stmt) {
    echo json_encode(['error' => 'Persiapan query gagal: ' . $conn->error]);
    exit();
}
$stmt->bind_param(
    "issssssssssssssss", // **17 karakter sesuai jumlah parameter**
    $iduser, $username, $name, $medsos_instagram, $medsos_whatsapp, $alamat, $deskripsi, $profile_photo, $foto_toko,
    $username, $name, $medsos_instagram, $medsos_whatsapp, $alamat, $deskripsi, $profile_photo, $foto_toko
);

    if ($stmt->execute()) {
        $stmt->close();

        $query = "SELECT * FROM data_toko WHERE id_user = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $iduser);
        $stmt->execute();
        $data_toko = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        echo json_encode([
            'success' => true,
            'username' => $data_toko['username'],
            'name' => $data_toko['name'],
            'medsos_instagram' => $data_toko['medsos_instagram'],
            'medsos_whatsapp' => $data_toko['medsos_whatsapp'],
            'alamat' => $data_toko['alamat'],
            'deskripsi' => $data_toko['deskripsi'],
            'profile_photo' => $data_toko['profile_photo'] ? $data_toko['profile_photo'] . '?' . time() : 'icon.jpg',
            'foto_toko' => $data_toko['foto_toko'] ? $data_toko['foto_toko'] . '?' . time() : 'store.jpg',
        ]);
        exit; 
    } else {

        echo json_encode(['error' => 'Gagal memperbarui profil: ' . $stmt->error]);
    }
} 

$conn->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Toko - Rental Kostum</title>

    <meta name="theme-color" content="#333">
    <meta name="description" content="Kelola profil toko rental kostum Anda">
    
    <!-- Favicon & Icons -->
    <link rel="icon" type="image/png" sizes="192x192" href="./icon.jpg">
    <link rel="apple-touch-icon" href="./icon.jpg">
    <link rel="apple-touch-icon" sizes="152x152" href="./icon.jpg">
    <link rel="apple-touch-icon" sizes="180x180" href="./icon.jpg">
    <link rel="apple-touch-icon" sizes="167x167" href="./icon.jpg">
    <meta name="msapplication-TileColor" content="#333333">
    <meta name="msapplication-tap-highlight" content="no">

    <!-- PWA Manifest -->
    <link rel="manifest" href="./Manifest.json">

    <!-- Favicon & Icons -->
    <link rel="icon" type="image/png" sizes="192x192" href="./icon.jpg">
    <link rel="apple-touch-icon" href="./icon.jpg">
    <link rel="apple-touch-icon" sizes="152x152" href="./icon.jpg">
    <link rel="apple-touch-icon" sizes="180x180" href="./icon.jpg">
    <link rel="apple-touch-icon" sizes="167x167" href="./icon.jpg">
    
    <!-- Preload critical resources -->
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" as="style">
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" as="style">

    <link crossorigin="anonymous" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="profile.css">
    
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
        position: relative; /* Tambahkan ini */
    }

        /* CSS untuk Sidebar */
    .sidebar {
        background-color: #EFEFEF;
        width: 220px;
        padding: 1rem;
        border-right: 1px solid #e0e0e0;
        box-shadow: 2px 0 5px rgba(0,0,0,0.05);
        position: sticky; /* Buat sidebar tetap */
        top: 0;
        height: 100vh;
        overflow-y: auto;
        flex-shrink: 0; /* Pastikan sidebar tidak menyusut */
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
        margin-left: 0; /* Hapus margin negatif */
        width: calc(100% - 220px); /* Sesuaikan dengan lebar sidebar */
        position: relative;
        z-index: 1; /* Pastikan content di atas sidebar */
    }

    .content {
        width: 100%;
        padding: 20px;
    }

    .form-section {
        width: 116%;
        background-color: white;
        padding: 20px;
        border-radius: 5px;
        box-shadow: 0 0 10px rgba(0,0,0,0.1);
        margin-left: -30px;
        margin-top : -20px;
    }

    .row {
        display: flex;
        flex-wrap: wrap;
        margin: 0 -15px;
        z-index : 1000;
        padding-bottom : 10px;
    }

    .col-md-3 {
        flex: 0 0 25%;
        max-width: 25%;
        padding: 0 15px;
        margin-bottom: 20px;
    }

    .card {
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);

    }

    @media (display-mode: standalone) {
        body {
            user-select: none;
            -webkit-user-select: none;
        }
        
        .navbar {
            padding-top: env(safe-area-inset-top);
        }
    }

    /* Install button animation */
    .install-btn {
        transform: translateY(100px);
        transition: all 0.3s ease;
    }

    /* Offline indicator */
    .offline-indicator {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        background: #ffc107;
        color: #212529;
        text-align: center;
        padding: 8px;
        z-index: 9999;
        transform: translateY(-100%);
        transition: transform 0.3s ease;
    }

    .offline-indicator.show {
        transform: translateY(0);
    }

    /* Mobile improvements */
    @media (max-width: 768px) {
        .sidebar {
            position: fixed;
            left: -250px;
            top: 0;
            height: 100vh;
            z-index: 1000;
            transition: left 0.3s ease;
            width: 250px;
        }
        
        .sidebar.active {
            left: 0;
        }
        
        .main-content {
            width: 100%;
            margin-left: 0;
        }
        
        .form-section {
            width: 100%;
            margin-left: 0;
            padding: 15px;
        }
        
        .navbar {
            position: relative;
        }
        
        .mobile-menu-btn {
            display: block;
            background: none;
            border: none;
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
        }
    }

    @media (min-width: 769px) {
        .mobile-menu-btn {
            display: none;
        }
    }

    @media (display-mode: standalone) {
        body {
            user-select: none;
            -webkit-user-select: none;
            -webkit-touch-callout: none;
            -webkit-tap-highlight-color: transparent;
        }
        
        .navbar {
            padding-top: env(safe-area-inset-top);
        }
        
        /* Hide browser UI elements in standalone mode */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
    }

    /* Modern PWA viewport units */
    @supports (height: 100dvh) {
        body {
            min-height: 100dvh;
        }
    }

    /* Focus visible for accessibility */
    *:focus-visible {
        outline: 2px solid #007bff;
        outline-offset: 2px;
    }

    /* Touch improvements */
    button, .btn, .notification-bell {
        touch-action: manipulation;
        -webkit-tap-highlight-color: transparent;
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
        <aside class="sidebar" style="margin-left : -110px;">
            <ul>
                <li><a href="INDEXXX.php">Dashboard</a></li>
                <li><a href="INDEXXX2.php">Profile</a></li>
                <li><a href="INDEXXX3.php">Katalog Kostum</a></li>
                <li><a href="pesanan.php">Pesanan</a></li>
            </ul>
        </aside>
        <main class="col-md-20 ml-sm-auto col-lg-30 px-4 content" role="main">
        <div class="form-section">
            <div class="form-header" style = "margin-bottom : 20px; margin-top : -19px; margin-left: -20px; ">
                <h2 class="form-title"> Lengkapi Informasi Toko Anda</h2>
            </div>
        <form method="POST" action="" enctype="multipart/form-data" id="profile-form" style = "margin-top : 50px;">
            <div class="row">
                <div class="col-md-4 text-center">
                    <!-- Foto Profil -->
                    <label for="foto-profil" class="text-form">Foto Profil / Logo Toko</label>
                    <img id="photo-preview" 
                        src="<?= isset($data_toko['profile_photo']) ? $data_toko['profile_photo'] . '?' . time() : 'icon.jpg' ?>" 
                        width="300" style="border-radius: 50%; display: block; margin: auto;" alt="Foto Profil Toko" />
                    <br />
                    <label class="btn btn-primary mt-2" style = "margin-top : 50px;">
                        Pilih Foto
                        <input type="file" id="profile-photo" name="profile_photo" accept="image/*" style="display: none;" onchange="previewImage(event, 'photo-preview')">
                    </label>

                    <!-- Foto Toko -->
                    <div class="text-center mt-4" style = "padding-top : 30px;">
                        <label for="foto_toko" class="text-form">Foto Toko / Tempat Rental *</label>
                        <img id="toko-preview" 
                            src="<?= isset($data_toko['foto_toko']) ? $data_toko['foto_toko'] . '?' . time() : 'store.jpg' ?>" 
                            width="250" style=" display: block; margin: auto;" alt="Foto Toko" />
                        <br />
                        <label class="btn btn-primary mt-2">
                            Pilih Foto
                            <input type="file" id="foto_toko" name="foto_toko" accept="image/*" style="display: none;" onchange="previewImage(event, 'toko-preview')">
                        </label>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="row" style = "margin-right: 30px;">
                        <div class="col-md-6 form-group" style="margin-bottom: 40px;">
                            <label for="username" class="text-form">Username Toko</label>
                            <div class="input-group">
                                <span class="input-group-text">@</span>
                                <input class="form-control" id="username" name="username" placeholder="Buat Username" type="text" 
                                       value="<?= htmlspecialchars($data_toko['username'] ?? '') ?>" required> 
                            </div>
                        </div>
                        <!-- Nama -->
                        <div class="col-md-6 form-group">
                            <label for="name" class="text-form">Nama Toko</label>
                            <input class="form-control" id="name" name="name" placeholder="Nama Toko" type="text" 
                                value="<?= htmlspecialchars($data_toko['name'] ?? '') ?>" required>
                        </div>

                      <!-- Media Sosial -->
                        <div class="col-md-12 form-group" style = "padding-bottom : 10px;">
                            <label class="text-form">Media Sosial</label>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="input-group mb-2">
                                        <span class="input-group-text">Instagram</span>
                                        <input class="form-control" id="medsos_instagram" name="medsos_instagram" 
                                            placeholder="Username Instagram" type="text" 
                                            value="<?= htmlspecialchars($data_toko['medsos_instagram'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="input-group mb-2">
                                        <span class="input-group-text">Whatsapp</span>
                                        <input class="form-control" id="medsos_whatsapp" name="medsos_whatsapp" 
                                            placeholder="Username Whatsapp" type="text" 
                                            value="<?= htmlspecialchars($data_toko['medsos_whatsapp'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Alamat Toko -->
                        <div class="col-md-12 form-group">
                            <label for="alamat" class="text-form" style="margin-top: 20px;">Alamat Toko / Tempat Rental *</label>
                            <textarea class="form-control" id="alamat" name="alamat" rows="6" placeholder="Masukkan alamat toko/tempat rental Anda dengan lengkap" required><?= htmlspecialchars($data_toko['alamat'] ?? '') ?></textarea>
                        </div>

                        <!-- Deskripsi -->
                        <div class="col-md-12 form-group" style = "padding-top : 40px;">
                            <label for="deskripsi" class="text-form">Deskripsi Toko</label>
                            <textarea class="form-control" id="deskripsi" name="deskripsi" rows="6" placeholder="Masukkan deskripsi tentang layanan dan produk Anda" required><?= htmlspecialchars($data_toko['deskripsi'] ?? '') ?></textarea>
                        </div>

                        <div class="text-end mt-4">
                            <button class="btn btn-success" style="margin-left: 20px; margin-top: 44px;" name="submit_profile" type="submit">Simpan profil</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</main>

<script>

window.addEventListener('load', () => {
  // Register Service Worker
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('./sw.js')
      .then(registration => {
        console.log('SW registered: ', registration);
        
        // Check for updates
        registration.addEventListener('updatefound', () => {
          const newWorker = registration.installing;
          newWorker.addEventListener('statechange', () => {
            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
              showUpdateAvailable();
            }
          });
        });
      })
      .catch(registrationError => {
        console.log('SW registration failed: ', registrationError);
      });
  }

  // Check if already installed
  window.addEventListener('appinstalled', () => {
    console.log('PWA was installed');
    showToast('Aplikasi berhasil diinstall!', 'success');
  });
});

function showUpdateAvailable() {
  const updateNotification = document.createElement('div');
  updateNotification.innerHTML = `
    <div style="position: fixed; top: 70px; right: 20px; background: #007bff; color: white; padding: 15px 20px; border-radius: 8px; z-index: 9999; box-shadow: 0 4px 12px rgba(0,0,0,0.3);">
      <div style="display: flex; align-items: center; gap: 10px;">
        <i class="fas fa-sync-alt"></i>
        <span>Update tersedia!</span>
        <button onclick="updateApp()" style="background: rgba(255,255,255,0.2); border: none; color: white; padding: 5px 10px; border-radius: 4px; cursor: pointer;">
          Update
        </button>
        <button onclick="this.parentElement.parentElement.remove()" style="background: none; border: none; color: white; cursor: pointer;">
          ×
        </button>
      </div>
    </div>
  `;
  document.body.appendChild(updateNotification);
}

function updateApp() {
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.getRegistration().then(registration => {
      if (registration && registration.waiting) {
        registration.waiting.postMessage({ action: 'skipWaiting' });
        window.location.reload();
      }
    });
  }
}

function toggleNotifications() {
    const dropdown = document.getElementById('notificationDropdown');
    dropdown.classList.toggle('show');
    
    // Close when clicking outside
    if (dropdown.classList.contains('show')) {
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

function previewImage(event, targetId) {
    // Validasi apakah ada file yang dipilih
    if (!event.target.files || !event.target.files[0]) {
        console.log('Tidak ada file yang dipilih');
        return;
    }
    
    const file = event.target.files[0];
    
    // Validasi tipe file
    if (!file.type.match('image.*')) {
        alert('Silakan pilih file gambar (JPG, JPEG, PNG)');
        event.target.value = ''; // Reset input
        return;
    }
    
    // Validasi ukuran file (maksimal 5MB)
    if (file.size > 5 * 1024 * 1024) {
        alert('Ukuran file terlalu besar. Maksimal 5MB');
        event.target.value = ''; // Reset input
        return;
    }
    
    const reader = new FileReader();
    reader.onload = function(e) {
        const output = document.getElementById(targetId);
        if (output) {
            output.src = e.target.result;
            output.style.display = 'block';
        } else {
            console.error('Elemen dengan ID "' + targetId + '" tidak ditemukan.');
        }
    };
    
    reader.onerror = function() {
        alert('Terjadi kesalahan saat membaca file');
        event.target.value = ''; // Reset input
    };
    
    reader.readAsDataURL(file);
}

// function previewTokoImage(event) {
//     var reader = new FileReader();
//     reader.onload = function(){
//         var output = document.getElementById('toko-preview');
//         output.src = reader.result;
//     };
//     reader.readAsDataURL(event.target.files[0]);
// }

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('profile-form');
    
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(form);

            if (!navigator.onLine) {
                storeFormDataOffline(formData);
                showToast('Data disimpan offline. Akan disinkronkan saat online.', 'warning');
                return;
            }

            // Show loading indicator
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Menyimpan...';
            submitBtn.disabled = true;

            fetch('INDEXXX2.php', {
                method: 'POST',
                body: formData,
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            })
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        // Update form values
                        document.getElementById('username').value = data.username;
                        document.getElementById('name').value = data.name;
                        document.getElementsByName('medsos_instagram')[0].value = data.medsos_instagram;
                        document.getElementsByName('medsos_whatsapp')[0].value = data.medsos_whatsapp;
                        document.getElementById('alamat').value = data.alamat;
                        document.getElementById('deskripsi').value = data.deskripsi;
                        
                        // Update preview images
                        const photoPreview = document.getElementById('photo-preview');
                        if (photoPreview && data.profile_photo) {
                            photoPreview.src = data.profile_photo;
                        }
                        
                        const tokoPreview = document.getElementById('toko-preview');
                        if (tokoPreview && data.foto_toko) {
                            tokoPreview.src = data.foto_toko;
                        }
                        
                        showToast('Profil berhasil diperbarui!', 'success');
                        
                    } else {
                        showToast(data.error || 'Terjadi kesalahan!', 'danger');
                    }
                } catch (error) {
                    console.error('Error parsing JSON:', error);
                    console.error('Response text:', text);
                    showToast('Respons server tidak valid.', 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Gagal memperbarui profil.', 'danger');
            })
            .finally(() => {
                // Restore button
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            });
        });
    } else {
        console.error('Form tidak ditemukan.');
    }
});

function storeFormDataOffline(formData) {
    if ('serviceWorker' in navigator && 'sync' in window.ServiceWorkerRegistration.prototype) {
        // Store in IndexedDB for background sync
        const data = {};
        for (let [key, value] of formData.entries()) {
            data[key] = value;
        }
        localStorage.setItem('pendingProfileUpdate', JSON.stringify(data));
        
        // Register for background sync
        navigator.serviceWorker.ready.then(registration => {
            return registration.sync.register('profile-sync');
        });
    }
}

function updateDisplayedData(data) {
    // Update profile photo if changed
    const photoPreview = document.getElementById('photo-preview');
    if (photoPreview && data.profile_photo) {
        photoPreview.src = data.profile_photo;
    }
    
    // Update toko photo if changed  
    const tokoPreview = document.getElementById('toko-preview');
    if (tokoPreview && data.foto_toko) {
        tokoPreview.src = data.foto_toko;
    }
}

// Network status monitoring
window.addEventListener('online', () => {
    showToast('Koneksi tersambung kembali', 'success');
    
    // Trigger background sync if there's pending data
    if (localStorage.getItem('pendingProfileUpdate')) {
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.ready.then(registration => {
                return registration.sync.register('profile-sync');
            });
        }
    }
});

window.addEventListener('offline', () => {
    showToast('Anda sedang offline. Data akan disimpan untuk disinkronkan nanti.', 'warning');
});
</script>
</body>
</html>