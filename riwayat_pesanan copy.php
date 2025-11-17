<?php
session_name('penyedia_session');
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

    // Buat tabel riwayat_pesanan jika belum ada
    $checkRiwayatTable = $conn->query("SHOW TABLES LIKE 'riwayat_pesanan'");
    if ($checkRiwayatTable->num_rows == 0) {
        $createRiwayatTable = "CREATE TABLE IF NOT EXISTS `riwayat_pesanan` (
            `id_riwayat` int(11) NOT NULL AUTO_INCREMENT,
            `id_pesanan_asli` int(11) NOT NULL,
            `id_transaksi` varchar(6) NOT NULL,
            `id_user` int(11) NOT NULL,
            `id_produk` int(11) NOT NULL,
            `id_penyedia` int(11) NOT NULL,
            `nama_penyewa` varchar(255) NOT NULL,
            `nomor_hp` varchar(20) NOT NULL,
            `nama_kostum` varchar(255) NOT NULL,
            `size` varchar(10) DEFAULT '',
            `quantity` int(11) NOT NULL DEFAULT 1,
            `tanggal_pinjam` date NOT NULL,
            `jumlah_hari` int(11) NOT NULL,
            `tanggal_mulai` date NOT NULL,
            `tanggal_selesai` date NOT NULL,
            `tanggal_pengembalian` datetime NOT NULL,
            `status` enum('selesai','dilaporkan') DEFAULT 'selesai',
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id_riwayat`),
            KEY `id_pesanan_asli` (`id_pesanan_asli`),
            KEY `id_transaksi` (`id_transaksi`),
            KEY `id_user` (`id_user`),
            KEY `id_produk` (`id_produk`),
            KEY `id_penyedia` (`id_penyedia`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        if (!$conn->query($createRiwayatTable)) {
            throw new Exception("Gagal membuat tabel riwayat_pesanan: " . $conn->error);
        }
    } 

    $id_penyedia = null;

    

    $alterRiwayatStatus = "ALTER TABLE `riwayat_pesanan` MODIFY COLUMN `status` enum('selesai','dilaporkan','ditolak') DEFAULT 'selesai'";
    if ($conn->query($alterRiwayatStatus)) {
        error_log("Enum status riwayat_pesanan berhasil diupdate untuk menambah 'ditolak'");
    } else {
        error_log("Gagal update enum status riwayat_pesanan: " . $conn->error);
    }

    // Cek berbagai kemungkinan nama session
    $sessionKeys = ['id_user', 'user_id', 'id', 'login_id', 'account_id'];
    foreach ($sessionKeys as $key) {
        if (isset($_SESSION[$key]) && !empty($_SESSION[$key])) {
            $id_penyedia = $_SESSION[$key];
            break;
        }
    }
    
    // Ambil data nama toko untuk navbar
    $nama_toko = "Toko Saya"; // Default value
    if ($id_penyedia) {
        $query_toko = "SELECT name FROM data_toko WHERE id_user = ?";
        $stmt_toko = $conn->prepare($query_toko);
        if ($stmt_toko) {
            $stmt_toko->bind_param("i", $id_penyedia);
            $stmt_toko->execute();
            $result_toko = $stmt_toko->get_result();
            if ($result_toko->num_rows > 0) {
                $data_toko_navbar = $result_toko->fetch_assoc();
                $nama_toko = htmlspecialchars($data_toko_navbar['name']);
            }
            $stmt_toko->close();
        }
    }

    // Function untuk mendapatkan notifikasi penyedia (dari pesanan aktif)
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

    $filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : '';
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';

    // Konfigurasi pagination
    $records_per_page = 15; // Jumlah data per halaman
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $offset = ($page - 1) * $records_per_page;

    $whereStatus = '';
    $params = [];
    $types = '';

    if ($filter_status == 'belum_dicairkan') {
        $whereStatus = " AND rp.status = 'selesai' AND (pm.bukti_pencairan_dana IS NULL OR pm.bukti_pencairan_dana = '')";
    } elseif ($filter_status == 'sudah_dicairkan') {
        $whereStatus = " AND rp.status = 'selesai' AND pm.bukti_pencairan_dana IS NOT NULL AND pm.bukti_pencairan_dana != ''";
    } elseif ($filter_status == 'ditolak') {
        $whereStatus = " AND rp.status = 'ditolak'";
    }

    if ($id_penyedia) {
        // Build WHERE clause untuk search
        $whereClause = "WHERE rp.id_penyedia = ?";
        $params = [$id_penyedia];
        $paramTypes = "i";

        if (!empty($search)) {
            $whereClause .= " AND (rp.id_transaksi LIKE ? OR rp.nama_penyewa LIKE ?)";
            $searchParam = "%$search%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $paramTypes .= "ss";
        }

        // Count query untuk pagination
        $count_query = "SELECT COUNT(*) as total 
                        FROM riwayat_pesanan rp
                        LEFT JOIN form_katalog fk ON rp.id_produk = fk.id
                        LEFT JOIN transaksi t ON rp.id_transaksi = t.id_transaksi
                        LEFT JOIN peminjaman pm ON rp.id_transaksi = pm.id_transaksi AND rp.id_produk = pm.id_produk
                        LEFT JOIN transaksi_items ti ON rp.id_transaksi = ti.id_transaksi AND rp.id_produk = ti.id_produk
                        $whereClause $whereStatus";
        
        $count_stmt = $conn->prepare($count_query);
        $count_stmt->bind_param($paramTypes, ...$params);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $total_records = $count_result->fetch_assoc()['total'];
        $total_pages = ceil($total_records / $records_per_page);
       
        // Jika ada ID penyedia, filter riwayat pesanan berdasarkan ID penyedia
        $query = "SELECT 
            rp.id_riwayat,
            rp.id_pesanan_asli,
            rp.id_transaksi,
            rp.id_produk,
            COALESCE(
                NULLIF(pm.nama_penyewa, '0'), 
                NULLIF(pm.nama_penyewa, ''), 
                NULLIF(t.nama_lengkap, ''), 
                NULLIF(rp.nama_penyewa, '0'), 
                NULLIF(rp.nama_penyewa, ''), 
                'Unknown'
            ) as nama_penyewa,
            rp.nomor_hp,
            rp.nama_kostum,
            rp.size,
            rp.quantity,
            COALESCE(t.tanggal_transaksi, rp.tanggal_pinjam) as tanggal_pinjam,
            rp.jumlah_hari,
            rp.tanggal_mulai,
            rp.tanggal_selesai,
            rp.tanggal_pengembalian,
            rp.status,
            rp.created_at,
            fk.foto_kostum,
            fk.judul_post,
            fk.kategori,
            fk.series,
            fk.karakter,
            fk.ukuran,
            pm.bukti_pencairan_dana,
            pm.keterangan_bukti,
            COALESCE(ti.subtotal, 0) as subtotal
        FROM riwayat_pesanan rp
        LEFT JOIN form_katalog fk ON rp.id_produk = fk.id
        LEFT JOIN transaksi t ON rp.id_transaksi = t.id_transaksi
        LEFT JOIN peminjaman pm ON rp.id_transaksi = pm.id_transaksi AND rp.id_produk = pm.id_produk
        LEFT JOIN transaksi_items ti ON rp.id_transaksi = ti.id_transaksi AND rp.id_produk = ti.id_produk
        $whereClause $whereStatus
        ORDER BY rp.tanggal_pengembalian DESC
        LIMIT ?, ?";
        
        $params[] = $offset;
        $params[] = $records_per_page;
        $paramTypes .= "ii";

        $stmt = $conn->prepare($query);
        if (!$stmt) {
            error_log("Error preparing provider query: " . $conn->error);
            $result = false;
        } else {
            $stmt->bind_param($paramTypes, ...$params);
            if (!$stmt->execute()) {
                error_log("Error executing provider query: " . $stmt->error);
                $result = false;
            } else {
                $result = $stmt->get_result();
            }
        }
    } else {

        $whereClause = "WHERE 1=1";
        $params = [];
        $paramTypes = "";

        if (!empty($search)) {
            $whereClause .= " AND (rp.id_transaksi LIKE ? OR rp.nama_penyewa LIKE ?)";
            $searchParam = "%$search%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $paramTypes = "ss";
        }

        // Count query untuk pagination (admin mode)
        $count_query = "SELECT COUNT(*) as total 
                        FROM riwayat_pesanan rp
                        LEFT JOIN form_katalog fk ON rp.id_produk = fk.id
                        LEFT JOIN transaksi t ON rp.id_transaksi = t.id_transaksi
                        LEFT JOIN peminjaman pm ON rp.id_transaksi = pm.id_transaksi AND rp.id_produk = pm.id_produk
                        LEFT JOIN transaksi_items ti ON rp.id_transaksi = ti.id_transaksi AND rp.id_produk = ti.id_produk
                        $whereClause $whereStatus";
        
        if (!empty($params)) {
            $count_stmt = $conn->prepare($count_query);
            $count_stmt->bind_param($paramTypes, ...$params);
            $count_stmt->execute();
            $count_result = $count_stmt->get_result();
            $total_records = $count_result->fetch_assoc()['total'];
        } else {
            $count_result = $conn->query($count_query);
            $total_records = $count_result->fetch_assoc()['total'];
        }
        $total_pages = ceil($total_records / $records_per_page);

        // Mode admin: Menampilkan semua riwayat pesanan
        $query = "SELECT 
            rp.id_riwayat,
            rp.id_pesanan_asli,
            rp.id_transaksi,
            rp.id_produk,
            COALESCE(
                NULLIF(pm.nama_penyewa, '0'), 
                NULLIF(pm.nama_penyewa, ''), 
                NULLIF(t.nama_lengkap, ''), 
                NULLIF(rp.nama_penyewa, '0'), 
                NULLIF(rp.nama_penyewa, ''), 
                'Unknown'
            ) as nama_penyewa,
            rp.nomor_hp,
            rp.nama_kostum,
            rp.size,
            rp.quantity,
            COALESCE(t.tanggal_transaksi, rp.tanggal_pinjam) as tanggal_pinjam,
            rp.jumlah_hari,
            rp.tanggal_mulai,
            rp.tanggal_selesai,
            rp.tanggal_pengembalian,
            rp.status,
            rp.created_at,
            fk.foto_kostum,
            fk.judul_post,
            fk.kategori,
            fk.series,
            fk.karakter,
            fk.ukuran,
            pm.bukti_pencairan_dana,
            pm.keterangan_bukti,
            COALESCE(ti.subtotal, 0) as subtotal
        FROM riwayat_pesanan rp
        LEFT JOIN form_katalog fk ON rp.id_produk = fk.id
        LEFT JOIN transaksi t ON rp.id_transaksi = t.id_transaksi
        LEFT JOIN peminjaman pm ON rp.id_transaksi = pm.id_transaksi AND rp.id_produk = pm.id_produk
        LEFT JOIN transaksi_items ti ON rp.id_transaksi = ti.id_transaksi AND rp.id_produk = ti.id_produk
        $whereClause $whereStatus
        ORDER BY rp.tanggal_pengembalian DESC
        LIMIT ?, ?";
        
        $params[] = $offset;
        $params[] = $records_per_page;
        $paramTypes .= "ii";
        
        if (!empty($params)) {
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                error_log("Error preparing admin query: " . $conn->error);
                $result = false;
            } else {
                $stmt->bind_param($paramTypes, ...$params);
                if (!$stmt->execute()) {
                    error_log("Error executing admin query: " . $stmt->error);
                    $result = false;
                } else {
                    $result = $stmt->get_result();
                }
            }
        } else {
            // Jika tidak ada parameter, gunakan query langsung dengan LIMIT
            $query .= " LIMIT $offset, $records_per_page";
            $result = $conn->query($query);
            if (!$result) {
                error_log("Error executing admin query: " . $conn->error);
                $result = false;
            }
        }
    }
    
} catch (Exception $e) {
    error_log("Error in riwayat_pesanan.php: " . $e->getMessage());
    $result = false;
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
        position: relative;
    }

    .sidebar {
        background-color: #EFEFEF;
        width: 180px;
        padding: 1rem;
        border-right: 1px solid #e0e0e0;
        box-shadow: 2px 0 5px rgba(0,0,0,0.05);
        position: sticky;
        top: 0;
        height: 100vh;
        overflow-y: auto;
        flex-shrink: 0;
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
        width: calc(100% - 220px);
        position: relative;
        z-index: 1;
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

    .table-responsive {
        overflow-x: auto;
        margin-top: 20px;
    }

    .table {
        margin-bottom: 0;
        background-color: white;
        table-layout: fixed;
        width: 100%;
    }

    .table td {
        vertical-align: middle;
        text-align: center;
        padding: 10px 8px;
        font-size: 0.85rem;
        word-wrap: break-word;
        overflow: hidden;
    }

    .table th {
        background-color: #f8f9fa;
        border-top: none;
        font-weight: 600;
        color: #495057;
        text-align: center;
        vertical-align: middle;
        padding: 12px 8px;
        font-size: 0.9rem;
        word-wrap: break-word;
    }

    .table td {
        vertical-align: middle;
        text-align: center;
        padding: 10px 8px;
        font-size: 0.85rem;
    }

    .table tbody tr:hover {
        background-color: #f8f9fa;
    }

    .badge {
        font-size: 0.75rem;
        padding: 0.4em 0.6em;
    }

    .text-nowrap {
        white-space: nowrap;
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

     /* Status badge colors */
    .badge.bg-warning { background-color: #ffc107 !important; }
    .badge.bg-success { background-color: #198754 !important; }
    .badge.bg-info { background-color: #0dcaf0 !important; }
    .badge.bg-secondary { background-color: #6c757d !important; }
    .badge.bg-danger { background-color: #dc3545 !important; }
    .badge.bg-primary { background-color: #0d6efd !important; }

    /* Action buttons styling - PERBAIKI */
    .action-buttons {
        display: flex;
        gap: 5px;
        justify-content: center;
        align-items: center;
        flex-wrap: nowrap;
    }

    .btn-accept, .btn-reject {
        border: none;
        border-radius: 4px;
        padding: 6px 10px;
        font-size: 0.8rem;
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 35px;
        height: 30px;
    }

    .btn-primary.btn-small {
        background-color: #007bff;
        color: white;
        border: none;
        border-radius: 4px;
        padding: 6px 10px;
        font-size: 0.75rem;
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
        min-width: auto;
        height: 30px;
    }

    .btn-primary.btn-small:hover {
        background-color: #0056b3;
        transform: translateY(-1px);
    }

    .btn-accept {
        background-color: #28a745;
        color: white;
    }

    .btn-accept:hover {
        background-color: #218838;
        transform: translateY(-1px);
    }

    .btn-reject {
        background-color: #dc3545;
        color: white;
    }

    .btn-reject:hover {
        background-color: #c82333;
        transform: translateY(-1px);
    }

    .btn-small {
        padding: 4px 8px;
        font-size: 0.75rem;
        min-width: 30px;
        height: 26px;
    }

    /* Loading state */
    .btn-loading {
        opacity: 0.6;
        pointer-events: none;
    }

    /* Modal styles - PERBAIKI */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
    }

    .modal.show {
        display: flex !important;
        align-items: center;
        justify-content: center;
    }

    .modal-content {
        background-color: #fefefe;
        margin: 10% auto;
        padding: 0;
        border-radius: 8px;
        width: 90%;
        max-width: 500px;
        position: relative;
        box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px;
        margin-bottom: 0;
        border-bottom: 1px solid #eee;
        background-color: #f8f9fa;
        border-radius: 8px 8px 0 0;
    }

    .modal-header h5 {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 600;
        color: #333;
    }

    .modal-close {
        background: none;
        border: none;
        font-size: 24px;
        cursor: pointer;
        color: #999;
        padding: 0;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .modal-close:hover {
        color: #333;
        background-color: #e9ecef;
        border-radius: 50%;
    }

    .modal-body {
        padding: 20px;
    }

    .form-group {
        margin-bottom: 15px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #333;
    }

    .form-group textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        resize: vertical;
        min-height: 100px;
        font-family: inherit;
        font-size: 0.9rem;
        box-sizing: border-box;
    }

    .form-group textarea:focus {
        outline: none;
        border-color: #007bff;
        box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
    }

    .modal-footer {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        padding: 20px;
        margin-top: 0;
        border-top: 1px solid #eee;
        background-color: #f8f9fa;
        border-radius: 0 0 8px 8px;
    }

    .btn-cancel, .btn-submit {
        border: none;
        padding: 10px 20px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 0.9rem;
        font-weight: 500;
        transition: all 0.2s ease;
    }

    .btn-cancel {
        background-color: #6c757d;
        color: white;
    }

    .btn-cancel:hover {
        background-color: #5a6268;
    }

    .btn-submit {
        background-color: #dc3545;
        color: white;
    }

    .btn-submit:hover {
        background-color: #c82333;
    }

    /* Debug info styling */
    .debug-box {
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 5px;
        padding: 10px;
        margin-bottom: 10px;
        font-size: 0.85rem;
    }

    .session-debug {
        background-color: #fff3cd;
        border-left: 4px solid #ffc107;
    }

    .debug-info {
        background-color: #e7f3ff;
        border-left: 4px solid #2196F3;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .action-buttons {
            flex-direction: column;
            gap: 3px;
        }
        
        .btn-accept, .btn-reject {
            width: 100%;
            min-width: auto;
        }
    }

    .detail-info {
        padding: 10px 0;
    }

    .detail-row {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid #f0f0f0;
    }

    .detail-row:last-child {
        border-bottom: none;
    }

    .detail-row strong {
        color: #333;
        min-width: 100px;
    }

    .detail-row span {
        color: #666;
        text-align: right;
        flex: 1;
    }

    .btn-success.btn-small {
        background-color: #28a745;
        color: white;
        border: none;
        border-radius: 4px;
        padding: 15px 17px; /* Diubah dari 6px 10px menjadi 8px 10px */
        font-size: 0.75rem;
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
        min-width: auto;
        height: 40px; /* Diubah dari 30px menjadi 34px */
    }

    .btn-success.btn-small:hover {
        background-color: #218838;
        transform: translateY(-1px);
    }

    #bukti-pencairan-modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.6);
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    #bukti-pencairan-modal.show {
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 1;
    }

    #bukti-pencairan-modal .modal-content {
        background-color: white;
        border-radius: 8px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        overflow: hidden;
        max-height: 90vh;
        overflow-y: auto;
    }

    #bukti-pencairan-modal .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem 1.5rem;
        background-color: #f8f9fa;
        border-bottom: 1px solid #e9ecef;
    }

    #bukti-pencairan-modal .modal-header h5 {
        margin: 0;
        font-weight: 600;
        color: #495057;
    }

    #bukti-pencairan-modal .modal-close {
        font-size: 24px;
        font-weight: bold;
        color: #adb5bd;
        cursor: pointer;
        line-height: 1;
    }

    #bukti-pencairan-modal .modal-close:hover {
        color: #6c757d;
    }

    #bukti-pencairan-modal .modal-body {
        padding: 2rem;
    }

    #bukti-pencairan-modal .modal-footer {
        display: flex;
        justify-content: flex-end;
        padding: 1rem 1.5rem;
        background-color: #f8f9fa;
        border-top: 1px solid #e9ecef;
    }

    .col-bukti-pencairan {
        width: 120px;
        text-align: center;
    }

    .col-bukti-pencairan .btn {
        font-size: 0.8rem;
        padding: 0.375rem 0.5rem;
    }

    .badge.bg-info { 
        background-color: #0dcaf0 !important; 
        color: white !important;
    }

    .badge.bg-warning { 
        background-color: #ffc107 !important; 
        color: #000 !important;
    }

    /* Container status untuk alignment vertikal */
    .status-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 4px;
    }

    .status-container .badge {
        white-space: nowrap;
        min-width: fit-content;
    }

    .subtotal-amount {
        font-weight: bold;
        color: #28a745;
        font-size: 14px;
    }
    
    .table td.subtotal-cell {
        background-color: #f8f9fa;
        font-weight: 600;
    }

    .search-results-info {
        background-color: #e7f3ff;
        border: 1px solid #b8daff;
        padding: 8px 12px;
        border-radius: 4px;
        margin-bottom: 15px;
    }

    .search-results-info .fas {
        color: #0056b3;
    }

    .input-group .btn {
        z-index: 1;
    }

    .btn-outline-danger:hover {
        color: #fff;
        background-color: #dc3545;
        border-color: #dc3545;
    }

    /* Pagination styling */
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

    .pagination-sm .page-link {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
    }

    /* Responsive search form */
    @media (max-width: 768px) {
        .d-flex.align-items-center.gap-3 form {
            flex-direction: column;
            align-items: stretch !important;
            gap: 10px !important;
        }
        
        .input-group {
            max-width: 100% !important;
        }
        
        .form-label {
            text-align: center;
        }
        
        .pagination {
            justify-content: center;
        }
        
        .pagination .page-item {
            margin: 0 2px;
        }
        
        .d-flex.justify-content-between {
            flex-direction: column;
            gap: 15px;
            text-align: center;
        }
    }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="logo">Selamat Datang, <?php echo $nama_toko; ?></div>
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
        </ul>
    </nav>

    <div class="container">
        <aside class="sidebar" style="margin-left : -110px;">
            <ul>
                <li><a href="INDEXXX.php">Dashboard</a></li>
                <li><a href="INDEXXX2.php">Profile</a></li>
                <li><a href="INDEXXX3.php">Katalog Kostum</a></li>
                <li><a href="pesanan.php">Pesanan</a></li>
                <li><a href="riwayat_pesanan.php">Riwayat Pesanan</a></li>
            </ul>
        </aside>
        <main class="col-md-20 ml-sm-auto col-lg-30 px-4 content" role="main">
            <div class="form-section">
                <div class="form-header" style="margin-bottom : 20px; margin-top : -19px; margin-left: -20px;">
                    <h2 class="form-title">Riwayat Pesanan Kostum</h2>

                    <!-- Debug session info
                    <div class="debug-box session-debug">
                        <strong>Debug Session:</strong> <?= isset($sessionInfo) ? $sessionInfo : 'No session info' ?>
                        <br><strong>Session Data:</strong> 
                        <?php 
                        $filteredSession = array_filter($_SESSION, function($value) {
                            return !is_array($value) && !is_object($value);
                        });
                        echo !empty($filteredSession) ? json_encode($filteredSession) : 'Empty session';
                        ?>
                    </div> -->

                    <!-- Debug info
                    <div class="debug-box debug-info">
                        <strong>Debug Info:</strong><br>
                        <?php foreach ($debugInfo as $info): ?>
                            • <?= $info ?><br>
                        <?php endforeach; ?>
                    </div> -->

                    <!-- <?php if ($id_penyedia): ?>
                        <div class="debug-box debug-info">
                            <i class="fas fa-info-circle"></i>
                            Menampilkan pesanan untuk produk Anda (ID Penyedia: <?= $id_penyedia ?>)
                        </div>
                    <?php else: ?>
                        <div class="debug-box debug-info">
                            <i class="fas fa-exclamation-triangle"></i>
                            Mode Admin: Menampilkan semua pesanan
                        </div>
                    <?php endif; ?> -->

                </div>

                <div class="mb-3">
                    <div class="d-flex align-items-center gap-3">
                        <!-- Filter Status -->
                        <form method="get" class="d-flex align-items-center gap-2">
                            <label for="filter_status" class="form-label mb-0 fw-bold" style="white-space: nowrap;">Filter Status:</label>
                            <div class="input-group" style="width: auto;">
                                <select class="form-select" id="filter_status" name="filter_status" style="min-width: 250px;" onchange="this.form.submit()">
                                    <option value="">Semua Status</option>
                                    <option value="belum_dicairkan" <?= (isset($_GET['filter_status']) && $_GET['filter_status']=='belum_dicairkan')?'selected':''; ?>>Selesai - Belum cair</option>
                                    <option value="sudah_dicairkan" <?= (isset($_GET['filter_status']) && $_GET['filter_status']=='sudah_dicairkan')?'selected':''; ?>>Selesai - Sudah cair</option>
                                    <option value="ditolak" <?= (isset($_GET['filter_status']) && $_GET['filter_status']=='ditolak')?'selected':''; ?>>Ditolak</option>
                                </select>
                            </div>
                        </form>

                        <!-- Cari Riwayat -->
                        <form method="GET" class="d-flex align-items-center gap-2 ms-3" style="flex: 1;">
                            <label for="searchInput" class="form-label mb-0 fw-bold" style="white-space: nowrap;">Cari Riwayat:</label>
                            <div class="input-group" style="max-width: 400px;">
                                <input name="search" id="searchInput" class="form-control" placeholder="ID Transaksi atau Nama Penyewa..." type="text" 
                                    value="<?= htmlspecialchars($search) ?>" />
                                <button class="btn btn-outline-secondary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                                <?php if (!empty($search)): ?>
                                    <a href="?" class="btn btn-outline-danger" title="Hapus pencarian">
                                        <i class="fas fa-times"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (isset($_GET['filter_status']) && !empty($_GET['filter_status'])): ?>
                                <input type="hidden" name="filter_status" value="<?= htmlspecialchars($_GET['filter_status']) ?>">
                            <?php endif; ?>
                            
                            <?php if (!empty($search)): ?>
                                <div class="text-muted small" style="white-space: nowrap;">
                                    <i class="fas fa-search"></i> 
                                    Hasil untuk: "<strong><?= htmlspecialchars($search) ?></strong>"
                                </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <div class="table-responsive" style="margin-top: 30px;">
                    <table class="table table-striped table-bordered">
                        <thead>
                                <tr>
                                    <th style="width: 6%;">No</th>
                                    <th style="width: 10%;">ID Transaksi</th>
                                    <th style="width: 15%;">Nama Penyewa</th>
                                    <th style="width: 12%;">No. Telp</th>
                                    <th style="width: 15%;">Foto Kostum</th>
                                    <th style="width: 13%;">Detail Kostum</th>
                                    <th style="width: 6%;">Size</th>
                                    <th style="width: 5%;">Qty</th>
                                    <th style="width: 10%;">Subtotal</th>
                                    <th style="width: 12%;">Tgl Pinjam</th>
                                    <th style="width: 6%;">Jml Hari</th>
                                    <th style="width: 12%;">Tgl Mulai</th>
                                    <th style="width: 12%;">Tgl Selesai</th>
                                    <th style="width: 12%;">Status</th>
                                    <th class="col-bukti-pencairan" style="width: 10%;">Bukti Pencairan</th>
                                </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if ($result && $result->num_rows > 0):
                                $counter = $total_records - $offset;
                                while ($row = $result->fetch_assoc()): 
                                    // Format tanggal
                                    $tanggalPinjam = date('d/m/Y', strtotime($row['tanggal_pinjam']));
                                    $tanggalMulai = date('d/m/Y', strtotime($row['tanggal_mulai']));
                                    $tanggalSelesai = date('d/m/Y', strtotime($row['tanggal_selesai']));
                                    
                                    // Status badge color
                                    $statusClass = '';
                                    switch($row['status']) {
                                        case 'pending':
                                            $statusClass = 'bg-warning';
                                            break;
                                        case 'approved':
                                            $statusClass = 'bg-success';
                                            break;
                                        case 'processing':
                                            $statusClass = 'bg-info';
                                            break;
                                        case 'completed':
                                            $statusClass = 'bg-secondary';
                                            break;
                                        case 'cancelled':
                                            $statusClass = 'bg-danger';
                                            break;
                                        case 'accepted':
                                            $statusClass = 'bg-success';
                                            break;
                                        case 'rejected':
                                            $statusClass = 'bg-danger';
                                            break;
                                        case 'kostum_diterima_penyewa':
                                            $statusClass = 'bg-primary';
                                            break;
                                        case 'pesanan_diterima_penyewa':
                                            $statusClass = 'bg-success';
                                            break;
                                        case 'kostum_telah_dikembalikan':
                                            $statusClass = 'bg-secondary';
                                            break;
                                        case 'selesai':
                                            $statusClass = 'bg-success';
                                            break;
                                        case 'dilaporkan':  // TAMBAHAN: Handle status dilaporkan
                                            $statusClass = 'bg-danger';
                                            break;
                                        default:
                                            $statusClass = 'bg-primary';
                                    }
                            ?>
                            <tr>
                                <td><?= $counter--; ?></td>
                                <td class="text-nowrap" style="font-weight: 600; color: #007bff;"><?= htmlspecialchars($row['id_transaksi']); ?></td>
                                <td><?= htmlspecialchars($row['nama_penyewa']); ?></td>
                                <td class="text-nowrap"><?= htmlspecialchars($row['nomor_hp']); ?></td>
                                <td style="text-align: center; padding-top: 8px;">
                                    <?php 
                                    $fotoKostum = $row['foto_kostum'];
                                    if (!empty($fotoKostum)): 
                                        // Ambil foto pertama jika ada multiple foto (comma separated)
                                        $fotoArray = explode(',', $fotoKostum);
                                        $fotoUtama = trim($fotoArray[0]);
                                         $fotoPath = 'foto_kostum/' . $fotoUtama;
                                        $fullPath = __DIR__ . '/foto_kostum/' . $fotoUtama;
                                        
                                        // Cek apakah file foto ada
                                        if (file_exists($fotoPath)):
                                    ?>
                                        <img src="<?= htmlspecialchars($fotoPath); ?>" 
                                             alt="<?= htmlspecialchars($row['nama_kostum']); ?>" 
                                             style="width: 90px; height: 90px; object-fit: cover; border-radius: 10px; border: 1px solid #ddd; cursor: pointer;"
                                             onclick="showImagePreview('<?= htmlspecialchars($fotoPath); ?>', '<?= htmlspecialchars($row['nama_kostum']); ?>')"
                                             title="Klik untuk memperbesar">
                                    <?php 
                                        else:
                                    ?>
                                        <div style="width: 50px; height: 50px; background-color: #f8f9fa; border: 1px solid #ddd; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #6c757d;">
                                            No Image
                                        </div>
                                    <?php 
                                        endif;
                                    else: 
                                    ?>
                                        <div style="width: 50px; height: 50px; background-color: #f8f9fa; border: 1px solid #ddd; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #6c757d;">
                                            No Image
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-info btn-sm" 
                                            onclick="showKostumDetail(<?= $row['id_produk']; ?>, '<?= htmlspecialchars($row['judul_post'] ?: $row['nama_kostum'], ENT_QUOTES); ?>')"
                                            title="Lihat Detail Kostum"
                                            style="background-color: #17a2b8; color: white; border: none; padding: 5px 10px; border-radius: 4px; font-size: 0.8rem; cursor: pointer;">
                                        <i class="fas fa-eye"></i> Detail
                                    </button>
                                </td>
                                <td><?= htmlspecialchars($row['size'] ?: '-'); ?></td>
                                <td><?= $row['quantity']; ?></td>
                                <td style="font-weight: bold; color: #28a745;">
                                    Rp <?= number_format($row['subtotal'], 0, ',', '.'); ?>
                                </td>
                                <td class="text-nowrap"><?= $tanggalPinjam; ?></td>
                                <td><?= $row['jumlah_hari']; ?></td>
                                <td class="text-nowrap"><?= $tanggalMulai; ?></td>
                                <td class="text-nowrap"><?= $tanggalSelesai; ?></td>
                                <td>
                                    <div style="display: flex; flex-direction: column; align-items: center; gap: 4px;">
                                        <!-- Status utama -->
                                        <?php if ($row['status'] == 'selesai'): ?>
                                            <span class="badge bg-success">Selesai</span>
                                        <?php elseif ($row['status'] == 'dilaporkan'): ?>
                                            <span class="badge bg-warning">Dilaporkan</span>
                                        <?php elseif ($row['status'] == 'ditolak'): ?>
                                            <span class="badge bg-danger">Ditolak</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><?= ucfirst($row['status']) ?></span>
                                        <?php endif; ?>
                                        
                                        <!-- Status pencairan dana (hanya tampil jika status selesai) -->
                                        <?php if ($row['status'] == 'selesai'): ?>
                                            <?php if (!empty($row['bukti_pencairan_dana'])): ?>
                                                <span class="badge bg-info" style="font-size: 0.7rem;">Sudah cair</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning" style="font-size: 0.7rem;">Belum cair</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="col-bukti-pencairan">
                                    <?php if ($row['status'] == 'ditolak'): ?>
                                        <span class="badge bg-secondary">Belum&nbsp;<br>Ada</span>
                                    <?php elseif (!empty($row['bukti_pencairan_dana'])): ?>
                                        <button class="btn btn-success btn-small" onclick="viewBuktiPencairan('<?= htmlspecialchars($row['bukti_pencairan_dana']) ?>', '<?= htmlspecialchars($row['nama_kostum']) ?>')">
                                            <i class="fas fa-eye"></i> Lihat Bukti
                                        </button>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Belum&nbsp;<br>Ada</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php 
                                endwhile; 
                            else: 
                            ?>
                            <tr>
                                <td colspan="15" style="text-align: center; padding: 20px;">
                                    <?php if ($id_penyedia): ?>
                                        Tidak ada pesanan untuk produk Anda.
                                    <?php else: ?>
                                        Tidak ada data pesanan atau session tidak valid.
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div class="text-muted">
                        <?php if (!empty($search)): ?>
                            <i class="fas fa-search"></i> 
                            Hasil pencarian untuk "<strong><?= htmlspecialchars($search) ?></strong>": 
                            <?= $total_records ?> riwayat pesanan ditemukan
                            <?php if ($id_penyedia): ?>
                                untuk produk Anda
                            <?php endif; ?>
                            <br>
                            <small>
                                Menampilkan <?= min($records_per_page, $result ? $result->num_rows : 0) ?> dari <?= $total_records ?> data
                                (Halaman <?= $page ?> dari <?= $total_pages ?>)
                            </small>
                        <?php else: ?>
                            Menampilkan <?= min($records_per_page, $result ? $result->num_rows : 0) ?> dari <?= $total_records ?> riwayat pesanan
                            <?php if ($id_penyedia): ?>
                                untuk produk Anda
                            <?php endif; ?>
                            <br>
                            <small>Halaman <?= $page ?> dari <?= $total_pages ?></small>
                        <?php endif; ?>
                        
                        <?php if (!empty($search) || !empty($_GET['filter_status'])): ?>
                            <div class="mt-1">
                                <a href="?" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-refresh"></i> Reset Filter & Pencarian
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <nav aria-label="Page navigation">
                        <ul class="pagination pagination-sm mb-0">
                            <?php 
                            // Build query string untuk pagination
                            $query_params = [];
                            if (!empty($search)) $query_params['search'] = $search;
                            if (!empty($_GET['filter_status'])) $query_params['filter_status'] = $_GET['filter_status'];
                            $query_string = !empty($query_params) ? '&' . http_build_query($query_params) : '';
                            ?>
                            
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $page - 1 ?><?= $query_string ?>" aria-label="Previous">
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
                                    <a class="page-link" href="?page=1<?= $query_string ?>">1</a>
                                </li>
                                <?php if ($start_page > 2): ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">...</span>
                                    </li>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?><?= $query_string ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($end_page < $total_pages): ?>
                                <?php if ($end_page < $total_pages - 1): ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">...</span>
                                    </li>
                                <?php endif; ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $total_pages ?><?= $query_string ?>"><?= $total_pages ?></a>
                                </li>
                            <?php endif; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $page + 1 ?><?= $query_string ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            </div>
        </main>
    </div>

    
    <!-- Modal Tolak Pesanan -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h5>Tolak Pesanan</h5>
                <button type="button" class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p><strong>Nama Kostum:</strong> <span id="rejectItemName"></span></p>
                <div class="form-group">
                    <label for="rejectReason">Alasan Penolakan:</label>
                    <textarea id="rejectReason" placeholder="Masukkan alasan mengapa pesanan ditolak..." required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal()">Batal</button>
                <button type="button" class="btn-submit" onclick="submitReject()">Tolak Pesanan</button>
            </div>
        </div>
    </div>

    <!-- Modal Preview Foto Kostum -->
    <div id="imagePreviewModal" class="modal">
        <div class="modal-content" style="max-width: 600px; text-align: center;">
            <div class="modal-header">
                <h5 id="imagePreviewTitle">Preview Foto Kostum</h5>
                <button type="button" class="modal-close" onclick="closeImagePreview()">&times;</button>
            </div>
            <div class="modal-body" style="padding: 20px;">
                <img id="imagePreviewImg" src="" alt="Foto Kostum" style="max-width: 100%; max-height: 400px; object-fit: contain; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeImagePreview()">Tutup</button>
            </div>
        </div>
    </div>

    <div id="buktiModal" class="modal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h5>Informasi Bukti</h5>
                <button type="button" class="modal-close" onclick="closeBuktiModal()">&times;</button>
            </div>
            <div class="modal-body" style="text-align: center; padding: 30px 20px;">
                <div style="background-color: #f8f9fa; border-radius: 8px; padding: 20px; margin-bottom: 15px;">
                    <i class="fas fa-file-alt" style="font-size: 2rem; color: #6c757d; margin-bottom: 10px;"></i>
                    <p style="margin: 0; font-size: 1rem; color: #495057; font-weight: 500;">
                        ini tombol bukti
                    </p>
                </div>
                <p style="margin: 0; font-size: 0.9rem; color: #6c757d;">
                    Modal untuk menampilkan informasi bukti pesanan
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeBuktiModal()">Tutup</button>
            </div>
        </div>
    </div>

    <!-- Modal Detail Kostum -->
<div id="kostumDetailModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h5 id="kostumDetailTitle">Detail Kostum</h5>
            <button type="button" class="modal-close" onclick="closeKostumDetailModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="detail-info">
                <div class="detail-row">
                    <strong>Nama Kostum:</strong>
                    <span id="detailNamaKostum">-</span>
                </div>
                <div class="detail-row">
                    <strong>Kategori:</strong>
                    <span id="detailKategori">-</span>
                </div>
                <div class="detail-row">
                    <strong>Series:</strong>
                    <span id="detailSeries">-</span>
                </div>
                <div class="detail-row">
                    <strong>Karakter:</strong>
                    <span id="detailKarakter">-</span>
                </div>
                <div class="detail-row">
                    <strong>Ukuran:</strong>
                    <span id="detailUkuran">-</span>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-cancel" onclick="closeKostumDetailModal()">Tutup</button>
        </div>
    </div>
</div>

 <script>
let currentRejectId = null;

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
            // Focus on approved orders in table
            highlightApprovedOrders();
            break;
        case 'reminder':
            showToast('Menampilkan peminjaman yang akan berakhir...', 'warning');
            // Could redirect to peminjaman page or show modal
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

function confirmReceived(idPesanan, namaKostum) {
    if (confirm(`Konfirmasi bahwa pesanan "${namaKostum}" telah diterima oleh penyewa?`)) {
        const button = event.target.closest('button');
        const originalHtml = button.innerHTML;
        button.classList.add('btn-loading');
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        button.disabled = true;

        fetch('proses_pesanan.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'pesanan_diterima',
                id_pesanan: idPesanan
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Status berhasil diupdate: Pesanan telah diterima oleh penyewa!');
                location.reload();
            } else {
                alert('Error: ' + data.message);
                button.classList.remove('btn-loading');
                button.innerHTML = originalHtml;
                button.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Terjadi kesalahan. Silakan coba lagi.');
            button.classList.remove('btn-loading');
            button.innerHTML = originalHtml;
            button.disabled = false;
        });
    }
}

function highlightApprovedOrders() {
    const rows = document.querySelectorAll('table tbody tr');
    rows.forEach(row => {
        const statusBadge = row.querySelector('.badge.bg-success');
        if (statusBadge && statusBadge.textContent.trim() === 'Approved') {
            row.style.backgroundColor = '#fff3cd';
            row.style.border = '2px solid #ffc107';
            row.style.transition = 'all 0.3s ease';
            
            // Remove highlight after 5 seconds
            setTimeout(() => {
                row.style.backgroundColor = '';
                row.style.border = '';
            }, 5000);
        }
    });
}

function viewBuktiPencairan(filePath, namaKostum) {
    if (!filePath) {
        alert('Bukti pencairan tidak tersedia');
        return;
    }

    // Create modal if not exists
    let modal = document.getElementById('bukti-pencairan-modal');
    if (!modal) {
        const modalHtml = `
            <div id="bukti-pencairan-modal" class="modal">
                <div class="modal-content" style="max-width: 800px;">
                    <div class="modal-header">
                        <h5 id="bukti-pencairan-title">Bukti Pencairan Dana</h5>
                        <span class="modal-close" onclick="closeBuktiPencairanModal()">&times;</span>
                    </div>
                    <div class="modal-body">
                        <div id="bukti-pencairan-content" class="text-center">
                            <!-- Content will be loaded here -->
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" onclick="closeBuktiPencairanModal()">Tutup</button>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        modal = document.getElementById('bukti-pencairan-modal');
    }

    // Set title
    document.getElementById('bukti-pencairan-title').textContent = `Bukti Pencairan Dana - ${namaKostum}`;

    // Determine file type and display accordingly
    const fileExtension = filePath.split('.').pop().toLowerCase();
    const content = document.getElementById('bukti-pencairan-content');

    if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension)) {
        content.innerHTML = `
            <img src="../${filePath}" alt="Bukti Pencairan Dana" 
                 style="max-width: 100%; max-height: 500px; border-radius: 6px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);"
                 onerror="this.parentElement.innerHTML='<div class=\\'alert alert-warning\\'>Gambar tidak dapat dimuat</div>'">
        `;
    } else if (fileExtension === 'pdf') {
        content.innerHTML = `
            <div>
                <p class="text-center mb-3">
                    <i class="fas fa-file-pdf fa-3x text-danger"></i>
                </p>
                <p class="text-center">
                    <a href="../${filePath}" target="_blank" class="btn btn-primary">
                        <i class="fas fa-external-link-alt"></i> Buka PDF
                    </a>
                </p>
            </div>
        `;
    } else {
        content.innerHTML = '<div class="alert alert-warning">Format file tidak dapat ditampilkan</div>';
    }

    // Show modal
    modal.classList.add('show');
}

function closeBuktiPencairanModal() {
    const modal = document.getElementById('bukti-pencairan-modal');
    if (modal) {
        modal.classList.remove('show');
    }
}

// Close modal when clicking outside
document.addEventListener('click', function(event) {
    const modal = document.getElementById('bukti-pencairan-modal');
    if (modal && event.target === modal) {
        closeBuktiPencairanModal();
    }
});

function viewAllNotifications() {
    console.log('View all notifications clicked');
    showToast('Fitur lihat semua notifikasi akan segera tersedia', 'info');
    
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

function acceptOrder(idPesanan, namaKostum) {
    if (confirm(`Apakah Anda yakin ingin menerima pesanan "${namaKostum}"?\n\nPesanan yang diterima akan dipindahkan ke data peminjaman.`)) {
        const button = event.target.closest('button');
        const originalHtml = button.innerHTML;
        button.classList.add('btn-loading');
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        button.disabled = true;

        fetch('proses_pesanan.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'accept',
                id_pesanan: idPesanan
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Pesanan berhasil diterima dan dipindahkan ke data peminjaman!');
                location.reload();
            } else {
                alert('Error: ' + data.message);
                button.classList.remove('btn-loading');
                button.innerHTML = originalHtml;
                button.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Terjadi kesalahan. Silakan coba lagi.');
            button.classList.remove('btn-loading');
            button.innerHTML = originalHtml;
            button.disabled = false;
        });
    }
}

function showRejectModal(idPesanan, namaKostum) {
    currentRejectId = idPesanan;
    document.getElementById('rejectItemName').textContent = namaKostum;
    document.getElementById('rejectReason').value = '';
    document.getElementById('rejectModal').classList.add('show');
}

function showImagePreview(imagePath, kostumName) {
    const modal = document.getElementById('imagePreviewModal');
    const img = document.getElementById('imagePreviewImg');
    const title = document.getElementById('imagePreviewTitle');
    
    img.src = imagePath;
    img.alt = kostumName;
    title.textContent = 'Foto Kostum: ' + kostumName;
    
    modal.classList.add('show');
}

function closeImagePreview() {
    const modal = document.getElementById('imagePreviewModal');
    modal.classList.remove('show');
}

function closeModal() {
    document.getElementById('rejectModal').classList.remove('show');
    document.getElementById('imagePreviewModal').classList.remove('show');
    currentRejectId = null;
}

function showKostumDetail(idProduk, namaKostum) {
    // Set title
    document.getElementById('kostumDetailTitle').textContent = 'Detail: ' + namaKostum;
    
    // Fetch data via AJAX
    fetch('get_kostum_detail.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            id_produk: idProduk
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('detailNamaKostum').textContent = data.data.judul_post || '-';
            document.getElementById('detailKategori').textContent = data.data.kategori || '-';
            document.getElementById('detailSeries').textContent = data.data.series || '-';
            document.getElementById('detailKarakter').textContent = data.data.karakter || '-';
            document.getElementById('detailUkuran').textContent = data.data.ukuran || '-';
        } else {
            // Show error or default values
            document.getElementById('detailNamaKostum').textContent = namaKostum || '-';
            document.getElementById('detailKategori').textContent = '-';
            document.getElementById('detailSeries').textContent = '-';
            document.getElementById('detailKarakter').textContent = '-';
            document.getElementById('detailUkuran').textContent = '-';
        }
        
        // Show modal
        document.getElementById('kostumDetailModal').classList.add('show');
    })
    .catch(error => {
        console.error('Error:', error);
        // Show modal with basic info on error
        document.getElementById('detailNamaKostum').textContent = namaKostum || '-';
        document.getElementById('detailKategori').textContent = '-';
        document.getElementById('detailSeries').textContent = '-';
        document.getElementById('detailKarakter').textContent = '-';
        document.getElementById('detailUkuran').textContent = '-';
        document.getElementById('kostumDetailModal').classList.add('show');
    });
}

function closeKostumDetailModal() {
    document.getElementById('kostumDetailModal').classList.remove('show');
}

function submitReject() {
    const reason = document.getElementById('rejectReason').value.trim();
    
    if (!reason) {
        alert('Harap masukkan alasan penolakan');
        return;
    }

    const submitBtn = event.target;
    submitBtn.classList.add('btn-loading');
    submitBtn.textContent = 'Memproses...';

    fetch('proses_pesanan.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'reject',
            id_pesanan: currentRejectId,
            reason: reason
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Pesanan berhasil ditolak!');
            closeModal();
            location.reload();
        } else {
            alert('Error: ' + data.message);
            submitBtn.classList.remove('btn-loading');
            submitBtn.textContent = 'Tolak Pesanan';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Terjadi kesalahan. Silakan coba lagi.');
        submitBtn.classList.remove('btn-loading');
        submitBtn.textContent = 'Tolak Pesanan';
    });
}

function confirmReturned(idPesanan, namaKostum) {
    if (confirm(`Konfirmasi bahwa kostum "${namaKostum}" telah dikembalikan?`)) {
        const button = event.target.closest('button');
        const originalHtml = button.innerHTML;
        button.classList.add('btn-loading');
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        button.disabled = true;

        fetch('proses_pesanan.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'kostum_kembali',
                id_pesanan: idPesanan
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Status berhasil diupdate: Kostum telah dikembalikan!');
                location.reload();
            } else {
                alert('Error: ' + data.message);
                button.classList.remove('btn-loading');
                button.innerHTML = originalHtml;
                button.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Terjadi kesalahan. Silakan coba lagi.');
            button.classList.remove('btn-loading');
            button.innerHTML = originalHtml;
            button.disabled = false;
        });
    }
}
// Close modal when clicking outside
window.onclick = function(event) {
    const rejectModal = document.getElementById('rejectModal');
    const imageModal = document.getElementById('imagePreviewModal');
    
    if (event.target == rejectModal) {
        closeModal();
    }
    if (event.target == imageModal) {
        closeImagePreview();
    }
}

// Close modal with ESC key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeModal();
    }
});

function showBuktiModal() {
    document.getElementById('buktiModal').classList.add('show');
}

function closeBuktiModal() {
    document.getElementById('buktiModal').classList.remove('show');
}

// Update fungsi closeModal yang sudah ada
function closeModal() {
    document.getElementById('rejectModal').classList.remove('show');
    document.getElementById('imagePreviewModal').classList.remove('show');
    document.getElementById('buktiModal').classList.remove('show');
    currentRejectId = null;
}

function viewBuktiPencairan(filePath, namaKostum) {
    if (!filePath) {
        alert('Bukti pencairan tidak tersedia');
        return;
    }

    // Create modal if not exists
    let modal = document.getElementById('bukti-pencairan-modal');
    if (!modal) {
        const modalHtml = `
            <div id="bukti-pencairan-modal" class="modal">
                <div class="modal-content" style="max-width: 800px;">
                    <div class="modal-header">
                        <h5 id="bukti-pencairan-title">Bukti Pencairan Dana</h5>
                        <span class="modal-close" onclick="closeBuktiPencairanModal()">&times;</span>
                    </div>
                    <div class="modal-body">
                        <div id="bukti-pencairan-content" class="text-center">
                            <!-- Content will be loaded here -->
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-cancel" onclick="closeBuktiPencairanModal()">Tutup</button>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        modal = document.getElementById('bukti-pencairan-modal');
    }

    // Set title
    document.getElementById('bukti-pencairan-title').textContent = `Bukti Pencairan Dana - ${namaKostum}`;

    // Determine file type and display accordingly
    const fileExtension = filePath.split('.').pop().toLowerCase();
    const content = document.getElementById('bukti-pencairan-content');

    if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension)) {
        content.innerHTML = `
            <img src="../${filePath}" alt="Bukti Pencairan Dana" 
                 style="max-width: 100%; max-height: 500px; border-radius: 6px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);"
                 onerror="this.parentElement.innerHTML='<div class=\\'alert alert-warning\\'>Gambar tidak dapat dimuat</div>'">
        `;
    } else if (fileExtension === 'pdf') {
        content.innerHTML = `
            <div>
                <p class="text-center mb-3">
                    <i class="fas fa-file-pdf fa-3x text-danger"></i>
                </p>
                <p class="text-center">
                    <a href="../${filePath}" target="_blank" class="btn btn-primary">
                        <i class="fas fa-external-link-alt"></i> Buka PDF
                    </a>
                </p>
            </div>
        `;
    } else {
        content.innerHTML = '<div class="alert alert-warning">Format file tidak dapat ditampilkan</div>';
    }

    // Show modal
    modal.classList.add('show');
}

function closeBuktiPencairanModal() {
    const modal = document.getElementById('bukti-pencairan-modal');
    if (modal) {
        modal.classList.remove('show');
    }
}

// Close modal when clicking outside
document.addEventListener('click', function(event) {
    const modal = document.getElementById('bukti-pencairan-modal');
    if (modal && event.target === modal) {
        closeBuktiPencairanModal();
    }
});

// Update event listener window.onclick yang sudah ada
window.onclick = function(event) {
    const rejectModal = document.getElementById('rejectModal');
    const imageModal = document.getElementById('imagePreviewModal');
    const buktiModal = document.getElementById('buktiModal');
    
    if (event.target == rejectModal) {
        closeModal();
    }
    if (event.target == imageModal) {
        closeImagePreview();
    }
    if (event.target == buktiModal) {
        closeBuktiModal();
    }
}
</script>

<?php if (isset($conn)) { $conn->close(); } ?>

</body>
</html>