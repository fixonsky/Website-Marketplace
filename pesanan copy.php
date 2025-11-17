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

    $checkTable = $conn->query("SHOW TABLES LIKE 'pesanan'");
    if ($checkTable->num_rows == 0) {
        // Buat tabel pesanan jika belum ada
        $createTable = "CREATE TABLE `pesanan` (
            `id_pesanan` int(11) NOT NULL AUTO_INCREMENT,
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
            `status` enum('pending','approved','processing','completed','cancelled') DEFAULT 'approved',
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id_pesanan`),
            KEY `id_transaksi` (`id_transaksi`),
            KEY `id_user` (`id_user`),
            KEY `id_produk` (`id_produk`),
            KEY `id_penyedia` (`id_penyedia`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        if (!$conn->query($createTable)) {
            throw new Exception("Gagal membuat tabel pesanan: " . $conn->error);
        }
    } else {
        $checkTransaksiColumn = $conn->query("SHOW COLUMNS FROM pesanan LIKE 'id_transaksi'");
        if ($checkTransaksiColumn->num_rows > 0) {
            $columnInfo = $checkTransaksiColumn->fetch_assoc();
            if (strpos($columnInfo['Type'], 'varchar') === false) {
                // Kolom masih INT, ubah ke VARCHAR(6)
                $alterTransaksiColumn = "ALTER TABLE `pesanan` MODIFY COLUMN `id_transaksi` VARCHAR(6) NOT NULL";
                if (!$conn->query($alterTransaksiColumn)) {
                    error_log("Gagal mengubah kolom id_transaksi: " . $conn->error);
                }
            }
        }

        // Cek apakah kolom id_penyedia ada di tabel pesanan
        $checkColumn = $conn->query("SHOW COLUMNS FROM pesanan LIKE 'id_penyedia'");
        if ($checkColumn->num_rows == 0) {
            // Tambahkan kolom id_penyedia jika belum ada
            $addColumn = "ALTER TABLE `pesanan` ADD COLUMN `id_penyedia` INT(11) NOT NULL AFTER `id_produk`";
            if (!$conn->query($addColumn)) {
                throw new Exception("Gagal menambahkan kolom id_penyedia: " . $conn->error);
            }
        }

        // Update enum status untuk menambah 'accepted' dan 'rejected'
        $alterStatus = "ALTER TABLE `pesanan` MODIFY COLUMN `status` enum('pending','approved','processing','completed','cancelled','accepted','rejected','kostum_diterima_penyewa','pesanan_diterima_penyewa') DEFAULT 'approved' NOT NULL";

        // Tambahkan debug setelah alter:
        if ($conn->query($alterStatus)) {
            error_log("Enum status berhasil diupdate");
        } else {
            error_log("Gagal update enum status: " . $conn->error);
        }
    }

    $checkPeminjamanTable = $conn->query("SHOW TABLES LIKE 'peminjaman'");
    if ($checkPeminjamanTable->num_rows == 0) {
        $createPeminjamanTable = "CREATE TABLE `peminjaman` (
            `id_peminjaman` int(11) NOT NULL AUTO_INCREMENT,
            `id_pesanan` int(11) NOT NULL,
            `id_transaksi` varchar(6) NOT NULL,
            `id_penyewa` int(11) NOT NULL,
            `id_penyedia` int(11) NOT NULL,
            `id_produk` int(11) NOT NULL,
            `nama_penyewa` varchar(255) NOT NULL,
            `nomor_hp` varchar(20) NOT NULL,
            `nama_kostum` varchar(255) NOT NULL,
            `size` varchar(10) DEFAULT '',
            `quantity` int(11) NOT NULL DEFAULT 1,
            `tanggal_mulai` date NOT NULL,
            `tanggal_selesai` date NOT NULL,
            `jumlah_hari` int(11) NOT NULL,
            `status_peminjaman` enum('sedang_berjalan','selesai','terlambat') DEFAULT 'sedang_berjalan',
            `tanggal_diterima` timestamp DEFAULT CURRENT_TIMESTAMP,
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id_peminjaman`),
            KEY `id_pesanan` (`id_pesanan`),
            KEY `id_transaksi` (`id_transaksi`),
            KEY `id_penyewa` (`id_penyewa`),
            KEY `id_penyedia` (`id_penyedia`),
            KEY `id_produk` (`id_produk`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        if (!$conn->query($createPeminjamanTable)) {
            throw new Exception("Gagal membuat tabel peminjaman: " . $conn->error);
        }

        $createProviderConfirmationTable = "CREATE TABLE IF NOT EXISTS `provider_confirmations` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `id_pesanan` int(11) NOT NULL,
            `id_transaksi` varchar(10) NOT NULL,
            `id_penyedia` int(11) NOT NULL,
            `confirmed_at` timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_confirmation` (`id_pesanan`, `id_penyedia`),
            KEY `id_transaksi` (`id_transaksi`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $conn->query($createProviderConfirmationTable);

        $createProviderReturnTable = "CREATE TABLE IF NOT EXISTS `provider_return_confirmations` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `id_pesanan` int(11) NOT NULL,
            `id_transaksi` varchar(10) NOT NULL,
            `id_penyedia` int(11) NOT NULL,
            `confirmed_at` timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_return_confirmation` (`id_pesanan`, `id_penyedia`),
            KEY `id_transaksi` (`id_transaksi`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $conn->query($createProviderReturnTable);

        } else {
        // PERBAIKAN: Update struktur tabel peminjaman yang sudah ada
        
        // Cek dan update kolom id_transaksi menjadi VARCHAR(6)
        $checkPeminjamanTransaksiColumn = $conn->query("SHOW COLUMNS FROM peminjaman LIKE 'id_transaksi'");
        if ($checkPeminjamanTransaksiColumn->num_rows > 0) {
            $columnInfo = $checkPeminjamanTransaksiColumn->fetch_assoc();
            if (strpos($columnInfo['Type'], 'varchar') === false) {
                // Kolom masih INT, ubah ke VARCHAR(6)
                $alterPeminjamanTransaksiColumn = "ALTER TABLE `peminjaman` MODIFY COLUMN `id_transaksi` VARCHAR(6) NOT NULL";
                if (!$conn->query($alterPeminjamanTransaksiColumn)) {
                    error_log("Gagal mengubah kolom id_transaksi di peminjaman: " . $conn->error);
                }
            }
        }
    }

    $id_penyedia = null;

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

    $sessionInfo = "Session keys found: " . implode(', ', array_keys($_SESSION));
    $debugInfo = [];

    // Cek jumlah total pesanan
    $totalPesanan = $conn->query("SELECT COUNT(*) as total FROM pesanan")->fetch_assoc()['total'];
    $debugInfo[] = "Total pesanan di database: " . $totalPesanan;

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
            $sql = "SELECT COUNT(*) as count 
                    FROM pesanan p
                    INNER JOIN transaksi_items ti ON p.id_transaksi = ti.id_transaksi AND p.id_produk = ti.id_produk
                    WHERE p.id_penyedia = ? AND ti.status_item = 'costume_received'";
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

    $search = isset($_GET['search']) ? trim($_GET['search']) : '';

    $records_per_page = 15; // Jumlah data per halaman
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $offset = ($page - 1) * $records_per_page;

    if ($id_penyedia) {
        $debugInfo[] = "Mode penyedia: Filter pesanan untuk ID penyedia: {$id_penyedia}";
        
        // Cek apakah tabel provider_confirmations ada
        $checkProviderTable = $conn->query("SHOW TABLES LIKE 'provider_confirmations'");
        $checkProviderReturnTable = $conn->query("SHOW TABLES LIKE 'provider_return_confirmations'");

        $whereClause = "WHERE p.id_penyedia = ?";
        $params = [$id_penyedia];
        $paramTypes = "i";

        if (!empty($search)) {
            $whereClause .= " AND (p.id_transaksi LIKE ? OR p.nama_penyewa LIKE ?)";
            $searchParam = "%$search%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $paramTypes .= "ss";
        }

        // Tambahkan filter status jika ada
        if (isset($_GET['status']) && !empty($_GET['status'])) {
            $statusFilter = $_GET['status'];
            
            if ($whereClause === "") {
                $whereClause = "WHERE ";
            } else {
                $whereClause .= " AND ";
            }
            
            switch($statusFilter) {
                case 'approved':
                    $whereClause .= "p.status = 'approved'";
                    break;
                case 'accepted':
                    $whereClause .= "p.status = 'accepted'";
                    break;
                case 'pesanan_diterima_penyewa_waiting':
                    $whereClause .= "p.status = 'pesanan_diterima_penyewa' AND ti.status_item != 'costume_received'";
                    break;
                case 'pesanan_diterima_penyewa_ready':
                    $whereClause .= "p.status = 'pesanan_diterima_penyewa' AND ti.status_item = 'costume_received'";
                    break;
                case 'telat_dikembalikan': // TAMBAHKAN CASE INI
                    $whereClause .= "p.status = 'pesanan_diterima_penyewa' AND CURDATE() > p.tanggal_selesai";
                    break;
                default:
                    // Tidak ada filter tambahan
            }
        }

        $count_query = "SELECT COUNT(*) as total 
                        FROM pesanan p
                        LEFT JOIN transaksi t ON p.id_transaksi = t.id_transaksi
                        LEFT JOIN form_katalog fk ON p.id_produk = fk.id
                        LEFT JOIN transaksi_items ti ON p.id_transaksi = ti.id_transaksi AND p.id_produk = ti.id_produk
                        $whereClause";
        
        $count_stmt = $conn->prepare($count_query);
        $count_stmt->bind_param($paramTypes, ...$params);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $total_records = $count_result->fetch_assoc()['total'];
        $total_pages = ceil($total_records / $records_per_page);

        if ($checkProviderTable->num_rows > 0) {
            // Jika tabel provider_confirmations ada
            if ($checkProviderReturnTable->num_rows > 0) {
                // Jika kedua tabel ada
                $query = "SELECT 
                    p.id_pesanan,
                    p.id_transaksi,
                    p.id_produk,
                    p.nama_penyewa,
                    p.nomor_hp,
                    p.nama_kostum,
                    p.size,
                    p.quantity,
                    DATE(t.tanggal_transaksi) as tanggal_pinjam,
                    p.jumlah_hari,
                    p.tanggal_mulai,
                    p.tanggal_selesai,
                    p.status,
                    p.created_at,
                    fk.foto_kostum,
                    fk.judul_post,
                    fk.kategori,
                    fk.series,
                    fk.karakter,
                    fk.ukuran,
                    ti.status_item,
                    pc.confirmed_at as provider_confirmed,
                    prc.confirmed_at as provider_return_confirmed,
                    ti.subtotal,
                    ti.harga_satuan,
                    COALESCE(ti.subtotal, 0) as subtotal
                FROM pesanan p
                LEFT JOIN transaksi t ON p.id_transaksi = t.id_transaksi
                LEFT JOIN form_katalog fk ON p.id_produk = fk.id
                LEFT JOIN provider_confirmations pc ON p.id_pesanan = pc.id_pesanan AND p.id_penyedia = pc.id_penyedia
                LEFT JOIN provider_return_confirmations prc ON p.id_pesanan = prc.id_pesanan AND p.id_penyedia = prc.id_penyedia
                LEFT JOIN transaksi_items ti ON p.id_transaksi = ti.id_transaksi AND p.id_produk = ti.id_produk
                $whereClause
                ORDER BY p.created_at DESC
                LIMIT ?, ?";
            } else {
                // Jika hanya tabel provider_confirmations yang ada
                $query = "SELECT 
                    p.id_pesanan,
                    p.id_transaksi,
                    p.id_produk,
                    p.nama_penyewa,
                    p.nomor_hp,
                    p.nama_kostum,
                    p.size,
                    p.quantity,
                    DATE(t.tanggal_transaksi) as tanggal_pinjam,
                    p.jumlah_hari,
                    p.tanggal_mulai,
                    p.tanggal_selesai,
                    p.status,
                    p.created_at,
                    fk.foto_kostum,
                    fk.judul_post,
                    fk.kategori,
                    fk.series,
                    fk.karakter,
                    fk.ukuran,
                    ti.status_item,
                    pc.confirmed_at as provider_confirmed,
                    NULL as provider_return_confirmed,
                    COALESCE(ti.subtotal, 0) as subtotal
                FROM pesanan p
                LEFT JOIN transaksi t ON p.id_transaksi = t.id_transaksi
                LEFT JOIN form_katalog fk ON p.id_produk = fk.id
                LEFT JOIN provider_confirmations pc ON p.id_pesanan = pc.id_pesanan AND p.id_penyedia = pc.id_penyedia
                LEFT JOIN transaksi_items ti ON p.id_transaksi = ti.id_transaksi AND p.id_produk = ti.id_produk
                $whereClause
                ORDER BY p.created_at DESC
                LIMIT ?, ?";
            }
        } else {
            // Jika tabel belum ada, gunakan query tanpa JOIN
            $query = "SELECT 
                p.id_pesanan,
                p.id_transaksi,
                p.id_produk,
                p.nama_penyewa,
                p.nomor_hp,
                p.nama_kostum,
                p.size,
                p.quantity,
                DATE(t.tanggal_transaksi) as tanggal_pinjam,
                p.jumlah_hari,
                p.tanggal_mulai,
                p.tanggal_selesai,
                p.status,
                p.created_at,
                fk.foto_kostum,
                fk.judul_post,
                fk.kategori,
                ti.status_item,
                fk.series,
                fk.karakter,
                fk.ukuran,
                NULL as provider_confirmed,
                NULL as provider_return_confirmed,
                COALESCE(ti.subtotal, 0) as subtotal
            FROM pesanan p
            LEFT JOIN transaksi t ON p.id_transaksi = t.id_transaksi
            LEFT JOIN form_katalog fk ON p.id_produk = fk.id
            LEFT JOIN transaksi_items ti ON p.id_transaksi = ti.id_transaksi AND p.id_produk = ti.id_produk
            $whereClause
            ORDER BY p.created_at DESC
            LIMIT ?, ?";
        }

        $params[] = $offset;
        $params[] = $records_per_page;
        $paramTypes .= "ii";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("SQL prepare failed: " . $conn->error . " Query: " . $query);
        }
        $stmt->bind_param($paramTypes, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $debugInfo[] = "Mode admin: Menampilkan semua pesanan";

        $whereClause = "";
        $params = [];
        $paramTypes = "";

        if (!empty($search)) {
            $whereClause = "WHERE (p.id_transaksi LIKE ? OR p.nama_penyewa LIKE ?)";
            $searchParam = "%$search%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $paramTypes = "ss";
        }

        // Tambahkan filter status jika ada
        if (isset($_GET['status']) && !empty($_GET['status'])) {
            $statusFilter = $_GET['status'];
            
            if ($whereClause === "") {
                $whereClause = "WHERE ";
            } else {
                $whereClause .= " AND ";
            }
            
            switch($statusFilter) {
                case 'approved':
                    $whereClause .= "p.status = 'approved'";
                    break;
                case 'accepted':
                    $whereClause .= "p.status = 'accepted'";
                    break;
                case 'pesanan_diterima_penyewa_waiting':
                    $whereClause .= "p.status = 'pesanan_diterima_penyewa' AND ti.status_item != 'costume_received'";
                    break;
                case 'pesanan_diterima_penyewa_ready':
                    $whereClause .= "p.status = 'pesanan_diterima_penyewa' AND ti.status_item = 'costume_received'";
                    break;
                default:
                    // Tidak ada filter tambahan
            }
        }

        $count_query = "SELECT COUNT(*) as total 
                        FROM pesanan p
                        LEFT JOIN transaksi t ON p.id_transaksi = t.id_transaksi
                        LEFT JOIN form_katalog fk ON p.id_produk = fk.id
                        LEFT JOIN transaksi_items ti ON p.id_transaksi = ti.id_transaksi AND p.id_produk = ti.id_produk
                        $whereClause";
        
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
            
        // Cek apakah tabel provider_confirmations ada untuk mode admin juga
        $checkProviderTable = $conn->query("SHOW TABLES LIKE 'provider_confirmations'");
        $checkProviderReturnTableAdmin = $conn->query("SHOW TABLES LIKE 'provider_return_confirmations'");
        
        if ($checkProviderTable->num_rows > 0) {
            if ($checkProviderReturnTableAdmin->num_rows > 0) {
                // Jika kedua tabel ada
                $query = "SELECT 
                    p.id_pesanan,
                    p.id_transaksi,
                    p.id_produk,
                    p.nama_penyewa,
                    p.nomor_hp,
                    p.nama_kostum,
                    p.size,
                    p.quantity,
                    DATE(t.tanggal_transaksi) as tanggal_pinjam,
                    p.jumlah_hari,
                    p.tanggal_mulai,
                    p.tanggal_selesai,
                    p.status,
                    p.created_at,
                    fk.foto_kostum,
                    fk.judul_post,
                    fk.kategori,
                    fk.series,
                    fk.karakter,
                    fk.ukuran,
                    ti.status_item,
                    pc.confirmed_at as provider_confirmed,
                    prc.confirmed_at as provider_return_confirmed,
                    COALESCE(ti.subtotal, 0) as subtotal
                FROM pesanan p
                LEFT JOIN transaksi t ON p.id_transaksi = t.id_transaksi
                LEFT JOIN form_katalog fk ON p.id_produk = fk.id
                LEFT JOIN provider_confirmations pc ON p.id_pesanan = pc.id_pesanan AND p.id_penyedia = pc.id_penyedia
                LEFT JOIN provider_return_confirmations prc ON p.id_pesanan = prc.id_pesanan AND p.id_penyedia = prc.id_penyedia
                LEFT JOIN transaksi_items ti ON p.id_transaksi = ti.id_transaksi AND p.id_produk = ti.id_produk
                $whereClause
                ORDER BY p.created_at DESC
                LIMIT ?, ?";
            } else {
                // Jika hanya tabel provider_confirmations yang ada
                $query = "SELECT 
                    p.id_pesanan,
                    p.id_transaksi,
                    p.id_produk,
                    p.nama_penyewa,
                    p.nomor_hp,
                    p.nama_kostum,
                    p.size,
                    p.quantity,
                    DATE(t.tanggal_transaksi) as tanggal_pinjam,
                    p.jumlah_hari,
                    p.tanggal_mulai,
                    p.tanggal_selesai,
                    p.status,
                    p.created_at,
                    fk.foto_kostum,
                    fk.judul_post,
                    fk.kategori,
                    fk.series,
                    fk.karakter,
                    fk.ukuran,
                    ti.status_item,
                    pc.confirmed_at as provider_confirmed,
                    NULL as provider_return_confirmed,
                    COALESCE(ti.subtotal, 0) as subtotal
                FROM pesanan p
                LEFT JOIN transaksi t ON p.id_transaksi = t.id_transaksi
                LEFT JOIN form_katalog fk ON p.id_produk = fk.id
                LEFT JOIN provider_confirmations pc ON p.id_pesanan = pc.id_pesanan AND p.id_penyedia = pc.id_penyedia
                LEFT JOIN transaksi_items ti ON p.id_transaksi = ti.id_transaksi AND p.id_produk = ti.id_produk
                $whereClause
                ORDER BY p.created_at DESC
                LIMIT ?, ?";
            }
        } else {
            $query = "SELECT 
                p.id_pesanan,
                p.id_transaksi,
                p.id_produk,
                p.nama_penyewa,
                p.nomor_hp,
                p.nama_kostum,
                p.size,
                p.quantity,
                DATE(t.tanggal_transaksi) as tanggal_pinjam,
                p.jumlah_hari,
                p.tanggal_mulai,
                p.tanggal_selesai,
                p.status,
                p.created_at,
                fk.foto_kostum,
                fk.judul_post,
                fk.kategori,
                fk.series,
                fk.karakter,
                fk.ukuran,
                ti.status_item,
                NULL as provider_confirmed,
                NULL as provider_return_confirmed,
                COALESCE(ti.subtotal, 0) as subtotal
            FROM pesanan p
            LEFT JOIN transaksi t ON p.id_transaksi = t.id_transaksi
            LEFT JOIN form_katalog fk ON p.id_produk = fk.id
            LEFT JOIN transaksi_items ti ON p.id_transaksi = ti.id_transaksi AND p.id_produk = ti.id_produk
            $whereClause
            ORDER BY p.created_at DESC
            LIMIT ?, ?";
        }

        $params[] = $offset;
        $params[] = $records_per_page;
        $paramTypes .= "ii";
        
        if (!empty($params)) {
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception("SQL prepare failed: " . $conn->error);
            }
            $stmt->bind_param($paramTypes, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $query .= " LIMIT $offset, $records_per_page";
            $result = $conn->query($query);
        }
    }
    
} catch (Exception $e) {
    error_log("Error in pesanan.php: " . $e->getMessage());
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
        margin-right: 150px;
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
        right: -130px;
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
        margin-left: -25px;
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
        flex-direction: column; /* UBAH INI dari row ke column */
        gap: 5px;
        justify-content: center;
        align-items: center;
        flex-wrap: nowrap;
        width: 100%;
    }

    /* Baris atas untuk tombol Terima dan Tolak */
    .action-buttons .top-row {
        display: flex;
        gap: 5px;
        justify-content: center;
        width: 100%;
    }

    /* Baris bawah untuk tombol Lapor */
    .action-buttons .bottom-row {
        display: flex;
        justify-content: center;
        width: 100%;
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

    .btn-lapor-small {
        background-color: #ffc107;
        border-color: #ffc107;
        color: #212529;
        border: none;
        border-radius: 4px;
        padding: 4px 8px;
        font-size: 0.7rem;
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
        min-width: 70px;
        height: 26px;
        width: 100%;
        max-width: 80px;
    }

    .btn-lapor-small:hover {
        background-color: #e0a800;
        border-color: #d39e00;
        color: #212529;
    }

    .btn-warning {
        background-color: #ffc107;
        border-color: #ffc107;
        color: #212529;
    }

    .btn-warning:hover {
        background-color: #e0a800;
        border-color: #d39e00;
        color: #212529;
    }

    .btn-warning.btn-sm {
        padding: 6px 10px;
        font-size: 0.8rem;
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
        border: none;
        border-radius: 4px;
        padding: 4px 8px;
        font-size: 0.7rem;
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 30px;
        height: 26px;
    }

    .btn-accept:hover {
        background-color: #218838;
        transform: translateY(-1px);
    }

    .btn-sm.btn-warning {
        background-color: #ffc107;
        border-color: #ffc107;
        color: #212529;
        border: none;
        border-radius: 4px;
        padding: 4px 8px;
        font-size: 0.7rem;
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 30px;
        height: 26px;
    }

    .btn-sm.btn-warning:hover {
        background-color: #e0a800;
        border-color: #d39e00;
        color: #212529;
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
    #laporanModal .modal-content {
        max-width: 600px;
        width: 90%;
    }

    #laporanModal .form-group {
        margin-bottom: 15px;
    }

    #laporanModal .form-group label {
        font-weight: bold;
        margin-bottom: 5px;
        display: block;
    }

    #laporanModal .form-control {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
    }

    #laporanModal textarea.form-control {
        resize: vertical;
        min-height: 80px;
    }

    #laporanModal .preview-image {
        display: inline-block;
        margin: 5px;
        position: relative;
    }

    #laporanModal .preview-image img {
        width: 100px;
        height: 100px;
        object-fit: cover;
        border: 2px solid #ddd;
        border-radius: 4px;
    }

    #laporanModal .remove-preview {
        position: absolute;
        top: -8px;
        right: -8px;
        background: red;
        color: white;
        border: none;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        font-size: 12px;
        cursor: pointer;
    }

    #confirmReceivedModal .modal-content {
        max-width: 500px;
    }

    #confirmReceivedModal .form-group {
        margin-bottom: 15px;
    }

    #confirmReceivedModal .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 500;
    }

    #confirmReceivedModal .form-control {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
    }

    #confirmReceivedModal textarea.form-control {
        resize: vertical;
        min-height: 80px;
    }

    #confirmReceivedModal .preview-image {
        text-align: center;
        margin-top: 10px;
    }

    #confirmReceivedModal .preview-image img {
        max-width: 100%;
        max-height: 200px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }

    #confirmReceivedModal .text-muted {
        font-size: 12px;
        color: #666;
        margin-top: 3px;
    }


    .badge.bg-warning.text-dark { 
        background-color: #ffc107 !important; 
        color: #000 !important;
    }

    .badge.bg-warning.text-dark i {
        margin-right: 5px;
    }

    .form-select {
        background-color: white;
        border: 1px solid #ced4da;
        border-radius: 0.375rem;
        padding: 0.375rem 0.75rem;
        font-size: 0.9rem;
        line-height: 1.5;
        color: #495057;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right 0.75rem center;
        background-size: 16px 12px;
        padding-right: 2.5rem;
    }

    .form-select:focus {
        border-color: #86b7fe;
        outline: 0;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }

    .form-label {
        margin-bottom: 0.5rem;
        font-weight: 500;
        color: #495057;
    }

    .gap-3 {
        gap: 1rem !important;
    }

    .d-flex {
        display: flex !important;
    }

    .align-items-center {
        align-items: center !important;
    }

    .fw-bold {
        font-weight: 700 !important;
    }

    .mb-0 {
        margin-bottom: 0 !important;
    }

    .mb-3 {
        margin-bottom: 1rem !important;
    }

    .ms-2 {
        margin-left: 0.5rem !important;
    }

    .text-muted {
        color: #6c757d !important;
    }

        .badge.bg-warning.text-dark i {
            margin-right: 5px;
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
    }

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

    /* Responsive pagination */
    @media (max-width: 576px) {
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

    #laporanModal .image-preview-container {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 15px;
        margin-top: 15px;
        max-height: 300px;
        overflow-y: auto;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 8px;
        background-color: #f9f9f9;
    }

    #laporanModal .image-preview-item {
        position: relative;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        background: white;
    }

    #laporanModal .image-preview-item img {
        width: 100%;
        height: 120px;
        object-fit: cover;
        display: block;
    }

    #laporanModal .image-preview-item .remove-btn {
        position: absolute;
        top: 5px;
        right: 5px;
        background: rgba(255, 0, 0, 0.8);
        color: white;
        border: none;
        border-radius: 50%;
        width: 25px;
        height: 25px;
        cursor: pointer;
        font-size: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background-color 0.2s;
    }

    #laporanModal .image-preview-item .remove-btn:hover {
        background: rgba(255, 0, 0, 1);
    }

    #laporanModal .image-preview-item .file-name {
        padding: 8px;
        font-size: 11px;
        text-align: center;
        color: #666;
        word-break: break-all;
    }

    #laporanModal .help-text {
        color: #666;
        font-size: 12px;
        margin-top: 5px;
        display: block;
    }

    #laporanModal .form-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 20px;
        padding-top: 15px;
        border-top: 1px solid #eee;
    }

    /* Loading state */
    .submit-laporan.loading {
        opacity: 0.7;
        pointer-events: none;
    }

    .submit-laporan.loading::after {
        content: " (Mengirim...)";
    }

    .table th:nth-child(15), 
    .table td:nth-child(15) {
        width: 14% !important; /* Naikkan dari 12% ke 14% */
        min-width: 140px;
    }

    .table th:nth-child(4), 
    .table td:nth-child(4) {
        width: 11% !important; /* No. Telp dari 12% ke 11% */
    }

    .table th:nth-child(6), 
    .table td:nth-child(6) {
        width: 12% !important; /* Detail Kostum dari 13% ke 12% */
    }

    .table th:nth-child(10), 
    .table td:nth-child(10) {
        width: 11% !important; /* Tgl Pinjam dari 12% ke 11% */
    }

    .accepted-action-buttons {
        display: flex;
        flex-direction: column; /* Ubah dari row ke column */
        gap: 8px; /* Tambah space antara tombol */
        justify-content: center;
        align-items: center;
        flex-wrap: nowrap;
        width: 100%;
    }

    .accepted-action-buttons .btn-accept,
    .accepted-action-buttons .btn-warning.btn-small {
        width: 100%;
        max-width: 80px;
        margin: 0 auto;
    }

    .btn-warning.btn-small {
        background-color: #ffc107;
        border-color: #ffc107;
        color: #212529;
        border: none;
        border-radius: 4px;
        padding: 4px 8px;
        font-size: 0.7rem;
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
        min-width: 70px;
        height: 26px;
        white-space: nowrap;
    }

    .return-action-buttons {
        display: flex;
        flex-direction: column; /* Kembalikan ke column untuk penjajaran vertikal */
        gap: 8px; /* Tambah space antara tombol */
        justify-content: center;
        align-items: center;
        flex-wrap: nowrap;
        width: 100%;
    }

    .return-action-buttons .btn-sm.btn-warning,
    .return-action-buttons .btn-warning.btn-small {
        width: 100%; /* Kembalikan ke 100% untuk penjajaran vertikal */
        max-width: 80px;
        margin: 0 auto; /* Pusatkan tombol */
    }

    .return-action-buttons .btn-warning.btn-small {
        background-color: #ffc107;
        border-color: #ffc107;
        color: #212529;
        border: none;
        border-radius: 4px;
        padding: 4px 8px;
        font-size: 0.7rem;
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
        min-width: 70px;
        height: 26px;
        white-space: nowrap;
    }

    .return-action-buttons .btn-sm.btn-warning:hover,
    .return-action-buttons .btn-warning.btn-small:hover {
        background-color: #e0a800;
        border-color: #d39e00;
        color: #212529;
        transform: translateY(-1px);
    }

    /* Tombol Dikembalikan */
    .return-action-buttons .btn-sm.btn-warning {
        background-color: #ffc107;
        border-color: #ffc107;
        color: #212529;
        border: none;
        border-radius: 4px;
        padding: 4px 8px;
        font-size: 0.7rem;
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 30px;
        height: 26px;
        white-space: nowrap;
    }

    .table th:nth-child(1), .table td:nth-child(1) { width: 4%; } /* No */
    .table th:nth-child(2), .table td:nth-child(2) { width: 8%; } /* ID Transaksi */
    .table th:nth-child(3), .table td:nth-child(3) { width: 12%; } /* Nama Penyewa */
    .table th:nth-child(4), .table td:nth-child(4) { width: 10%; } /* No. Telp */
    .table th:nth-child(5), .table td:nth-child(5) { width: 12%; } /* Foto Kostum */
    .table th:nth-child(6), .table td:nth-child(6) { width: 10%; } /* Detail Kostum */
    .table th:nth-child(7), .table td:nth-child(7) { width: 5%; } /* Size */
    .table th:nth-child(8), .table td:nth-child(8) { width: 4%; } /* Qty */
    .table th:nth-child(9), .table td:nth-child(9) { width: 8%; } /* Subtotal */
    .table th:nth-child(10), .table td:nth-child(10) { width: 8%; } /* Tgl Pinjam */
    .table th:nth-child(11), .table td:nth-child(11) { width: 5%; } /* Jml Hari */
    .table th:nth-child(12), .table td:nth-child(12) { width: 8%; } /* Tgl Mulai */
    .table th:nth-child(13), .table td:nth-child(13) { width: 8%; } /* Tgl Selesai */
    .table th:nth-child(14), .table td:nth-child(14) { width: 12%; } /* Status */
    .table th:nth-child(15), .table td:nth-child(15) { width: 14%; } /* Aksi */

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
            </li>
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
                    <h2 class="form-title">Data Pesanan Kostum</h2>
                    <div class="mb-3">
                        <div class="d-flex align-items-center gap-3">
                            <label for="statusFilter" class="form-label mb-0 fw-bold">Filter Status:</label>
                            <select id="statusFilter" class="form-select" style="width: auto; min-width: 200px;">
                                <option value="">Semua Status</option>
                                <option value="approved">Menunggu Konfirmasi</option>
                                <option value="accepted">Menunggu Konfirmasi Diterima</option>
                                <option value="pesanan_diterima_penyewa_waiting">Menunggu Konfirmasi Penyewa</option>
                                <option value="pesanan_diterima_penyewa_ready">Menunggu Konfirmasi Dikembalikan</option>
                                <option value="telat_dikembalikan">Telat Dikembalikan</option>
                            </select>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="clearFilter()">
                                <i class="fas fa-times"></i> Reset
                            </button>

                            <form method="GET" class="d-flex align-items-center gap-2" style="flex: 1;">
                                <input type="hidden" name="status" value="<?= isset($_GET['status']) ? htmlspecialchars($_GET['status']) : '' ?>">
                                <label for="searchInput" class="form-label mb-0 fw-bold" style="white-space: nowrap;">Cari Pesanan:</label>
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
                                <?php if (!empty($search)): ?>
                                    <div class="text-muted small" style="white-space: nowrap;">
                                        <i class="fas fa-search"></i> 
                                        Hasil untuk: "<strong><?= htmlspecialchars($search) ?></strong>"
                                    </div>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>

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

                <div class="table-responsive" style="margin-top: 30px;">
                    <table class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th style="width: 5%;">No</th>
                                <th style="width: 8%;">ID Transaksi</th>
                                <th style="width: 14%;">Nama Penyewa</th>
                                <th style="width: 13%;">No. Telp</th>
                                <th style="width: 14%;">Foto Kostum</th>
                                <th style="width: 12%;">Detail Kostum</th>
                                <th style="width: 5%;">Size</th>
                                <th style="width: 4%;">Qty</th>
                                <th style="width: 8%;">Subtotal</th>
                                <th style="width: 10%;">Tgl Pinjam</th>
                                <th style="width: 5%;">Jml Hari</th>
                                <th style="width: 10%;">Tgl Mulai</th>
                                <th style="width: 10%;">Tgl Selesai</th>
                                <th style="width: 14%;">Status</th>
                                <th style="width: 14%;">Aksi</th>
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
                                        default:
                                            $statusClass = 'bg-primary';
                                    }
                            ?>
                            <tr>
                                <td><?= $counter--; ?></td>
                                <td><?= htmlspecialchars($row['id_transaksi']); ?></td> 
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
                                    <span class="badge <?= $statusClass; ?>">
                                        <?php 
                                        switch($row['status']) {
                                            case 'pending': echo 'Pending'; break;
                                            case 'approved': echo 'Approved'; break;
                                            case 'processing': echo 'Processing'; break;
                                            case 'completed': echo 'Completed'; break;
                                            case 'cancelled': echo 'Cancelled'; break;
                                            case 'accepted': echo 'Accepted'; break;
                                            case 'rejected': echo 'Rejected'; break;
                                            case 'kostum_diterima_penyewa': echo 'Kostum Diterima'; break;
                                            case 'pesanan_diterima_penyewa': 
                                                echo '<span title="Pesanan telah diterima oleh penyewa"><i class="fas fa-check-circle"></i> Diterima</span>';
                                                $tanggalSelesai = $row['tanggal_selesai'];
                                                $tanggalSekarang = date('Y-m-d');
                                                
                                                if (strtotime($tanggalSekarang) > strtotime($tanggalSelesai)) {
                                                    $hariTerlambat = (strtotime($tanggalSekarang) - strtotime($tanggalSelesai)) / (60 * 60 * 24);
                                                    echo '<br><small class="text-danger" title="Kostum telat dikembalikan ' . $hariTerlambat . ' hari">
                                                                <i class="fas fa-clock"></i> Telat ' . $hariTerlambat . ' hari
                                                            </small>';
                                                    } 
                                                break;
                                            case 'provider_confirmed_received':
                                                    echo '<span class="badge bg-info">Penyedia Konfirmasi Diterima</span>';
                                                    break;
                                                case 'both_confirmed_received':
                                                    echo '<span class="badge bg-success">Kedua Pihak Konfirmasi</span>';
                                                    break;
                                            default: echo ucfirst($row['status']);
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    $currentStatus = $row['status'] ?? '';
                                    $statusDebug = "Status: '$currentStatus' | Length: " . strlen($currentStatus) . " | Type: " . gettype($currentStatus);
                                    ?>
                                    <!-- DEBUG: <?= $statusDebug ?> -->
                                    
                                    <?php if (trim($currentStatus) === 'approved'): ?>
                                        <div class="action-buttons">
                                            <div class="top-row">
                                                <button class="btn-accept" onclick="acceptOrder(<?= $row['id_pesanan']; ?>, '<?= htmlspecialchars($row['nama_kostum'], ENT_QUOTES); ?>')" title="Terima pesanan">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button class="btn-reject" onclick="showRejectModal(<?= $row['id_pesanan']; ?>, '<?= htmlspecialchars($row['nama_kostum'], ENT_QUOTES); ?>')" title="Tolak pesanan">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                            <div class="bottom-row">
                                                <button onclick="showLaporanModal(<?php echo $row['id_pesanan']; ?>, '<?php echo htmlspecialchars($row['nama_kostum'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($row['nama_penyewa'], ENT_QUOTES); ?>')" 
                                                        data-transaksi="<?php echo htmlspecialchars($row['id_transaksi']); ?>"
                                                        data-pesanan="<?php echo $row['id_pesanan']; ?>"
                                                        class="btn-lapor-small" title="Laporkan Penyewa">
                                                    <i class="fas fa-exclamation-triangle"></i> Lapor
                                                </button>
                                            </div>
                                        </div>
                                    <?php elseif ($row['status'] == 'accepted'): ?>
                                        <div class="accepted-action-buttons">
                                            <button class="btn-accept" 
                                                    onclick="confirmReceived(<?php echo $row['id_pesanan']; ?>, '<?php echo htmlspecialchars($row['nama_kostum'], ENT_QUOTES); ?>')">
                                                <i class="fa fa-check"></i> Diterima
                                            </button>
                                            <button onclick="showLaporanModal(<?php echo $row['id_pesanan']; ?>, '<?php echo htmlspecialchars($row['nama_kostum'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($row['nama_penyewa'], ENT_QUOTES); ?>')" 
                                                    data-transaksi="<?php echo htmlspecialchars($row['id_transaksi']); ?>"
                                                    data-pesanan="<?php echo $row['id_pesanan']; ?>"
                                                    class="btn-warning btn-small" title="Laporkan Penyewa">
                                                <i class="fas fa-exclamation-triangle"></i> Lapor
                                            </button>
                                        </div>
                                    <?php elseif ($row['status'] == 'pesanan_diterima_penyewa'): ?>
                                          
                                        <?php 
                                        // Cek apakah provider sudah konfirmasi return
                                        $checkProviderReturn = "SELECT COUNT(*) as count FROM provider_return_confirmations WHERE id_pesanan = ?";
                                        $stmtCheck = $conn->prepare($checkProviderReturn);
                                        $stmtCheck->bind_param("i", $row['id_pesanan']);
                                        $stmtCheck->execute();
                                        $providerReturned = $stmtCheck->get_result()->fetch_assoc()['count'] > 0;
                                        
                                        $customerConfirmed = ($row['status_item'] == 'costume_received');
                                        ?>
                                        
                                        <?php if (!$providerReturned): ?>
                                            <?php if ($customerConfirmed): ?>
                                                <div class="return-action-buttons">
                                                    <button class="btn-sm btn-warning" 
                                                            onclick="confirmReturned(<?php echo $row['id_pesanan']; ?>, '<?php echo htmlspecialchars($row['nama_kostum'], ENT_QUOTES); ?>')" >
                                                        <i class="fa fa-undo"></i> Dikembalikan
                                                    </button>

                                                    <button onclick="showLaporanModal(<?php echo $row['id_pesanan']; ?>, '<?php echo htmlspecialchars($row['nama_kostum'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($row['nama_penyewa'], ENT_QUOTES); ?>')" 
                                                            data-transaksi="<?php echo htmlspecialchars($row['id_transaksi']); ?>"
                                                            data-pesanan="<?php echo $row['id_pesanan']; ?>"
                                                            class="btn btn-warning btn-small" title="Laporkan Penyewa"  >
                                                        <i class="fas fa-exclamation-triangle"></i> Lapor
                                                    </button>
                                                </div>
                                            <?php else: ?>
                                                <span class="badge bg-info" title="Menunggu konfirmasi dari penyewa">
                                                    <i class="fa fa-clock"></i> Menunggu
                                                </span>
                                                <div class="return-action-buttons" style="margin-top: 5px;">
                                                    <button onclick="showLaporanModal(<?php echo $row['id_pesanan']; ?>, '<?php echo htmlspecialchars($row['nama_kostum'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($row['nama_penyewa'], ENT_QUOTES); ?>')" 
                                                            data-transaksi="<?php echo htmlspecialchars($row['id_transaksi']); ?>"
                                                            data-pesanan="<?php echo $row['id_pesanan']; ?>"
                                                            class="btn btn-warning btn-small" title="Laporkan Penyewa"
                                                            style="margin-left: 5px;">
                                                        <i class="fas fa-exclamation-triangle"></i> Lapor
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-info" title="Menunggu konfirmasi dari penyewa">
                                                <i class="fa fa-clock"></i> Menunggu
                                            </span>
                                        <?php endif; ?>
                                    <?php elseif ($row['status'] == 'provider_confirmed_returned'): ?>
                                            <span class="badge bg-info" title="Menunggu konfirmasi dari penyewa">
                                                <i class="fa fa-clock"></i> Menunggu
                                            </span>

                                  
                                    <?php elseif (trim($currentStatus) === 'kostum_diterima_penyewa'): ?>
                                        <span class="badge bg-primary">Kostum Diterima Penyewa</span>
                                        <?php if (!empty($row['bukti_diterima_path'])): ?>
                                            <br><small>
                                                <a href="javascript:void(0)" onclick="showImagePreview('<?php echo htmlspecialchars($row['bukti_diterima_path']); ?>', 'Bukti Diterima')" 
                                                style="color: #007bff; text-decoration: none;">
                                                    <i class="fas fa-image"></i> Lihat Bukti
                                                </a>
                                            </small>
                                        <?php endif; ?>

                                        <button onclick="showLaporanModal(<?php echo $row['id_pesanan']; ?>, '<?php echo htmlspecialchars($row['nama_kostum'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($row['nama_penyewa'], ENT_QUOTES); ?>')" 
                                                data-transaksi="<?php echo htmlspecialchars($row['id_transaksi']); ?>"
                                                data-pesanan="<?php echo $row['id_pesanan']; ?>"
                                                class="btn btn-warning btn-small" title="Laporkan Penyewa"
                                                style="margin-left: 5px;">
                                            <i class="fas fa-exclamation-triangle"></i> Lapor
                                        </button>
                                    <?php elseif (trim($currentStatus) === 'rejected'): ?>
                                        <span class="badge bg-danger">Ditolak</span>
                                    <?php elseif (trim($currentStatus) === 'completed'): ?>
                                        <span class="badge bg-secondary">Selesai</span>
                                    <?php elseif (trim($currentStatus) === 'cancelled'): ?>
                                        <span class="badge bg-warning">Dibatalkan</span>
                                    <?php else: ?>
                                        <!-- Fallback: Tampilkan tombol jika status tidak dikenal -->
                                        <div class="action-buttons">
                                            <button class="btn-accept btn-small" 
                                                    onclick="acceptOrder(<?= $row['id_pesanan']; ?>, '<?= htmlspecialchars($row['nama_kostum'], ENT_QUOTES); ?>')"
                                                    title="Terima Pesanan">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button class="btn-reject btn-small" 
                                                    onclick="showRejectModal(<?= $row['id_pesanan']; ?>, '<?= htmlspecialchars($row['nama_kostum'], ENT_QUOTES); ?>')"
                                                    title="Tolak Pesanan">
                                                <i class="fas fa-times"></i>
                                            </button>

                                            <button onclick="showLaporanModal(<?php echo $row['id_pesanan']; ?>, '<?php echo htmlspecialchars($row['nama_kostum'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($row['nama_penyewa'], ENT_QUOTES); ?>')" 
                                                    data-transaksi="<?php echo $row['id_transaksi']; ?>"
                                                    class="btn btn-warning btn-small" title="Laporkan Penyewa">
                                                <i class="fas fa-exclamation-triangle"></i> Lapor
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="11" style="text-align: center; padding: 20px;">
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
                            <?= $total_records ?> pesanan ditemukan
                            <?php if ($id_penyedia): ?>
                                untuk produk Anda
                            <?php endif; ?>
                            <br>
                            <small>
                                Menampilkan <?= min($records_per_page, $result ? $result->num_rows : 0) ?> dari <?= $total_records ?> data
                                (Halaman <?= $page ?> dari <?= $total_pages ?>)
                            </small>
                        <?php else: ?>
                            Menampilkan <?= min($records_per_page, $result ? $result->num_rows : 0) ?> dari <?= $total_records ?> pesanan
                            <?php if ($id_penyedia): ?>
                                untuk produk Anda
                            <?php endif; ?>
                            <br>
                            <small>Halaman <?= $page ?> dari <?= $total_pages ?></small>
                        <?php endif; ?>
                        
                        <?php if (!empty($search)): ?>
                            <div class="mt-1">
                                <a href="?" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-refresh"></i> Tampilkan Semua Pesanan
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
                            // Tambahkan parameter status jika ada filter yang dipilih
                            if (isset($_GET['status']) && !empty($_GET['status'])) {
                                $query_params['status'] = $_GET['status'];
                            }
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

<!-- Modal Laporan Penyewa -->
<div id="laporanModal" class="modal">
    <div class="modal-background">
        <div class="modal-content">
            <span class="close" onclick="closeLaporanModal()">&times;</span>
            <h3>Laporkan Masalah</h3>
            <form id="laporanForm" enctype="multipart/form-data">
                <input type="hidden" id="laporan_id_transaksi" name="id_transaksi">
                <input type="hidden" id="laporan_id_pesanan" name="id_pesanan">
                
                <div class="form-group">
                    <label for="laporan_nama_penyewa">Nama Penyewa:</label>
                    <input type="text" id="laporan_nama_penyewa" name="nama_penyewa" readonly class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="laporan_nama_kostum">Nama Kostum:</label>
                    <input type="text" id="laporan_nama_kostum" name="nama_kostum" readonly class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="deskripsi_laporan">Deskripsi Masalah:</label>
                    <textarea id="deskripsi_laporan" name="deskripsi_laporan" rows="4" placeholder="Jelaskan masalah yang terjadi..." required class="form-control"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="bukti_laporan">Bukti Laporan (Gambar - Maksimal 10):</label>
                    <input type="file" id="bukti_laporan" name="bukti_laporan[]" multiple accept="image/*" class="form-control" onchange="previewMultipleImages(this)">
                    <div id="image-preview-container" class="image-preview-container"></div>
                    <small class="help-text">Pilih hingga 10 gambar sebagai bukti laporan. Format yang didukung: JPG, PNG, GIF (Max: 5MB per file)</small>
                </div>
                
                <div class="form-actions">
                    <button type="button" onclick="submitLaporanBaru()" class="btn btn-warning submit-laporan">Submit Laporan</button>
                    <button type="button" onclick="closeLaporanModal()" class="btn btn-secondary">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="confirmReceivedModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h5>Konfirmasi Kostum Diterima</h5>
            <button type="button" class="modal-close" onclick="closeConfirmReceivedModal()">&times;</button>
        </div>
        <div class="modal-body">
            <!-- HIMBAUAN PENTING - BAGIAN BARU -->
            <div style="background-color: #fff3cd; border: 2px solid #ffc107; border-radius: 8px; padding: 15px; margin-bottom: 20px; border-left: 5px solid #ff6b35;">
                <div style="display: flex; align-items: flex-start; gap: 10px;">
                    <i class="fas fa-exclamation-triangle" style="color: #ff6b35; font-size: 20px; margin-top: 2px;"></i>
                    <div>
                        <h6 style="color: #d63384; font-weight: bold; margin: 0 0 8px 0; font-size: 14px;">
                            ⚠️ PERHATIAN PENTING - WAJIB DIBACA!
                        </h6>
                        <div style="color: #856404; font-size: 13px; line-height: 1.5;">
                            <p style="margin: 0 0 8px 0; font-weight: 600;">
                                <strong>SEBELUM memberikan kostum kepada penyewa, PASTIKAN:</strong>
                            </p>
                            <ol style="margin: 0; padding-left: 18px;">
                                <li style="margin-bottom: 5px;">
                                    <strong>Penyewa sudah mengonfirmasi</strong> bahwa pesanan telah diterima di aplikasi mereka
                                </li>
                                <li style="margin-bottom: 5px;">
                                    Status pesanan sudah berubah menjadi <span style="background: #28a745; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px;">DITERIMA</span>
                                </li>
                            </ol>
                            <p style="margin: 10px 0 0 0; font-weight: 600; color: #d63384;">
                                <i class="fas fa-info-circle"></i> 
                                Tombol "Kostum Telah Dikembalikan" hanya akan muncul setelah penyewa mengonfirmasi penerimaan pesanan!
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <!-- END HIMBAUAN PENTING -->

            <form id="confirmReceivedForm">
                <input type="hidden" id="confirmReceivedIdPesanan">
                
                <div style="text-align: center; margin-bottom: 15px;">
                    <p style="margin: 0; color: #666; font-size: 14px;">Konfirmasi penyerahan kostum:</p>
                    <h6 style="margin: 5px 0; color: #333;" id="confirmReceivedKostumName"></h6>
                </div>

                <div class="form-group">
                    <label for="bukti_diterima">Bukti Foto Penyerahan Kostum *</label>
                    <input type="file" id="bukti_diterima" name="bukti_diterima" class="form-control" 
                           accept="image/*" required>
                    <div class="text-muted" style="font-size: 12px; margin-top: 3px;">
                        Format: JPG, PNG, GIF. Maksimal 5MB
                    </div>
                    <div id="preview_bukti_diterima" class="preview-image"></div>
                </div>

                <div class="form-group">
                    <label for="catatan_konfirmasi">Catatan Tambahan</label>
                    <textarea id="catatan_konfirmasi" name="catatan_konfirmasi" 
                              class="form-control" rows="3" 
                              placeholder="Tambahkan catatan jika diperlukan (opsional)"></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-cancel" onclick="closeConfirmReceivedModal()">Batal</button>
            <button type="button" class="btn-submit" onclick="submitConfirmReceived()">Konfirmasi Penyerahan</button>
        </div>
    </div>
</div>

 <script>
let currentRejectId = null;
let selectedFiles = [];

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
            window.location.href = 'pesanan.php?status=approved';
            break;
        case 'pencairan_dana':
            window.location.href = 'riwayat_pesanan.php?filter_status=sudah_dicairkan';
            break;
        case 'accepted':
            window.location.href = 'pesanan.php?status=accepted';
            break;
        case 'return_confirm':
            // Alternatif: langsung ke halaman pesanan dengan scroll ke bagian yang relevan
            window.location.href = 'pesanan.php?status=pesanan_diterima_penyewa';
            break;
        case 'alert':
            window.location.href = 'pesanan.php?status=telat_dikembalikan';
            break;
        default:
            showToast('Notifikasi diklik', 'info');
    }
    
    // Close dropdown
    document.getElementById('notificationDropdown').classList.remove('show');
}

function confirmReceived(idPesanan, namaKostum) {
    // Tampilkan modal instead of confirm dialog
    document.getElementById('confirmReceivedIdPesanan').value = idPesanan;
    document.getElementById('confirmReceivedKostumName').textContent = namaKostum;
    document.getElementById('confirmReceivedForm').reset();
    document.getElementById('preview_bukti_diterima').innerHTML = '';
    document.getElementById('confirmReceivedModal').classList.add('show');
}

function closeConfirmReceivedModal() {
    document.getElementById('confirmReceivedModal').classList.remove('show');
    document.getElementById('confirmReceivedForm').reset();
    document.getElementById('preview_bukti_diterima').innerHTML = '';
}

function submitConfirmReceived() {
    const form = document.getElementById('confirmReceivedForm');
    const formData = new FormData();
    
    const idPesanan = document.getElementById('confirmReceivedIdPesanan').value;
    const buktiFile = document.getElementById('bukti_diterima').files[0];
    const catatan = document.getElementById('catatan_konfirmasi').value;
    
    // Validasi
    if (!buktiFile) {
        alert('Harap upload bukti foto terlebih dahulu!');
        return;
    }
    
    // Validasi ukuran file (max 5MB)
    if (buktiFile.size > 5 * 1024 * 1024) {
        alert('Ukuran file terlalu besar! Maksimal 5MB');
        return;
    }
    
    // Validasi format file
    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    if (!allowedTypes.includes(buktiFile.type)) {
        alert('Format file tidak didukung! Gunakan JPG, PNG, atau GIF');
        return;
    }
    
    // Siapkan data
    formData.append('action', 'pesanan_diterima');
    formData.append('id_pesanan', idPesanan);
    formData.append('bukti_diterima', buktiFile);
    formData.append('catatan_konfirmasi', catatan);
    
    const submitBtn = document.querySelector('#confirmReceivedModal .btn-submit');
    const originalText = submitBtn.textContent;
    
    // Loading state
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
    
    fetch('proses_pesanan.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Konfirmasi berhasil! Status pesanan telah diperbarui.');
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Terjadi kesalahan'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Terjadi kesalahan saat memproses konfirmasi');
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
        closeConfirmReceivedModal();
    });
}

// Preview image untuk bukti diterima
document.addEventListener('DOMContentLoaded', function() {
    const buktiInput = document.getElementById('bukti_diterima');
    if (buktiInput) {
        buktiInput.addEventListener('change', function() {
            const file = this.files[0];
            const preview = document.getElementById('preview_bukti_diterima');
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = `
                        <img src="${e.target.result}" style="max-width: 100%; max-height: 200px; border: 1px solid #ddd; border-radius: 4px;">
                        <p style="margin-top: 5px; font-size: 12px; color: #666;">Preview: ${file.name}</p>
                    `;
                };
                reader.readAsDataURL(file);
            } else {
                preview.innerHTML = '';
            }
        });
    }
});

// Helper function untuk escape single quotes
function escapeSingleQuotes(str) {
    return str.replace(/'/g, "\\'");
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
        .then(response => {
            // Log respons untuk debugging
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers);
            
            // Cek jika response ok
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            // Ambil text dulu untuk melihat respons mentah
            return response.text();
        })
        .then(text => {
            console.log('Raw response:', text);
            
            // Coba parse JSON
            try {
                const data = JSON.parse(text);
                
                if (data.success) {
                    alert('Pesanan berhasil diterima dan dipindahkan ke data peminjaman!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                    button.classList.remove('btn-loading');
                    button.innerHTML = originalHtml;
                    button.disabled = false;
                }
            } catch (jsonError) {
                console.error('JSON Parse Error:', jsonError);
                console.error('Response text:', text);
                alert('Error: Respons server tidak valid. Cek console untuk detail.');
                button.classList.remove('btn-loading');
                button.innerHTML = originalHtml;
                button.disabled = false;
            }
        })
        .catch(error => {
            console.error('Fetch Error:', error);
            alert('Terjadi kesalahan: ' + error.message);
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

function showLaporanModal(idPesanan, namaKostum, namaPenyewa) {
    // PERBAIKAN: Coba ambil dari data-attribute terlebih dahulu
    const button = event.target.closest('button');
    let idTransaksi = button ? button.getAttribute('data-transaksi') : null;
    
    console.log('Button found:', button);
    console.log('ID Transaksi from data-attribute:', idTransaksi);
    
    if (idTransaksi && idTransaksi !== 'null' && idTransaksi !== '') {
        // Langsung gunakan id_transaksi dari data-attribute
        setupLaporanModal(idPesanan, idTransaksi, namaKostum, namaPenyewa);
        return;
    }
    
    // Fallback: gunakan AJAX jika data-attribute tidak ada
    console.log('Fallback to AJAX for id_transaksi');
    fetch('get_transaksi_id.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            id_pesanan: idPesanan
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.id_transaksi) {
            setupLaporanModal(idPesanan, data.id_transaksi, namaKostum, namaPenyewa);
        } else {
            alert('ID Transaksi tidak ditemukan: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error fetching id_transaksi:', error);
        alert('Terjadi kesalahan saat mengambil data transaksi.');
    });
}

function setupLaporanModal(idPesanan, idTransaksi, namaKostum, namaPenyewa) {
    document.getElementById('laporan_id_pesanan').value = idPesanan;
    document.getElementById('laporan_id_transaksi').value = idTransaksi;
    document.getElementById('laporan_nama_kostum').value = namaKostum;
    document.getElementById('laporan_nama_penyewa').value = namaPenyewa;
    document.getElementById('laporanModal').style.display = 'block';
}

function previewMultipleImages(input) {
    const files = Array.from(input.files);
    const maxFiles = 10;
    const maxSize = 5 * 1024 * 1024; // 5MB
    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    
    // Validate file count
    if (files.length > maxFiles) {
        alert(`Maksimal ${maxFiles} gambar yang dapat diupload.`);
        input.value = '';
        return;
    }
    
    // Validate files
    const validFiles = [];
    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        
        // Check file type
        if (!allowedTypes.includes(file.type)) {
            alert(`File "${file.name}" tidak didukung. Gunakan format JPG, PNG, atau GIF.`);
            continue;
        }
        
        // Check file size
        if (file.size > maxSize) {
            alert(`File "${file.name}" terlalu besar. Maksimal 5MB per file.`);
            continue;
        }
        
        validFiles.push(file);
    }
    
    if (validFiles.length === 0) {
        input.value = '';
        return;
    }
    
    selectedFiles = validFiles;
    displayImagePreviews(validFiles);
}

function displayImagePreviews(files) {
    const container = document.getElementById('image-preview-container');
    container.innerHTML = '';
    
    files.forEach((file, index) => {
        const reader = new FileReader();
        reader.onload = function(e) {
            const previewItem = document.createElement('div');
            previewItem.className = 'image-preview-item';
            previewItem.innerHTML = `
                <img src="${e.target.result}" alt="Preview ${index + 1}">
                <button class="remove-btn" onclick="removeImagePreview(${index})" title="Hapus gambar">×</button>
                <div class="file-name">${file.name}</div>
            `;
            container.appendChild(previewItem);
        };
        reader.readAsDataURL(file);
    });
}

function removeImagePreview(index) {
    selectedFiles.splice(index, 1);
    displayImagePreviews(selectedFiles);
    
    // Update file input
    const fileInput = document.getElementById('bukti_laporan');
    const dt = new DataTransfer();
    selectedFiles.forEach(file => dt.items.add(file));
    fileInput.files = dt.files;
}

function submitLaporanBaru() {
    const form = document.getElementById('laporanForm');
    const submitBtn = document.querySelector('.submit-laporan');
    
    // Validate form
    const idTransaksi = document.getElementById('laporan_id_transaksi').value;
    const idPesanan = document.getElementById('laporan_id_pesanan').value;
    const deskripsi = document.getElementById('deskripsi_laporan').value.trim();
    
    if (!idTransaksi || !idPesanan || !deskripsi) {
        alert('Semua field wajib diisi!');
        return;
    }
    
    if (selectedFiles.length === 0) {
        if (!confirm('Anda belum memilih bukti gambar. Lanjutkan tanpa bukti?')) {
            return;
        }
    }
    
    // Show loading state
    submitBtn.classList.add('loading');
    submitBtn.disabled = true;
    
    const formData = new FormData();
    formData.append('id_transaksi', idTransaksi);
    formData.append('id_pesanan', idPesanan);
    formData.append('nama_penyewa', document.getElementById('laporan_nama_penyewa').value);
    formData.append('nama_kostum', document.getElementById('laporan_nama_kostum').value);
    formData.append('deskripsi_laporan', deskripsi);
    
    // Add files
    selectedFiles.forEach((file, index) => {
        formData.append('bukti_laporan[]', file);
    });
    
    fetch('proses_laporan.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Laporan berhasil dikirim!');
            closeLaporanModal();
            // Optional: refresh page or update UI
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Gagal mengirim laporan'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Terjadi kesalahan saat mengirim laporan');
    })
    .finally(() => {
        submitBtn.classList.remove('loading');
        submitBtn.disabled = false;
    });
}

function closeLaporanModal() {
    document.getElementById('laporanModal').style.display = 'none';
    document.getElementById('laporanForm').reset();
    document.getElementById('image-preview-container').innerHTML = '';
    selectedFiles = [];
}


// Preview gambar bukti laporan
document.addEventListener('DOMContentLoaded', function() {
    const buktiInput = document.getElementById('bukti_laporan');
    if (buktiInput) {
        buktiInput.addEventListener('change', function(e) {
            const files = e.target.files;
            const preview = document.getElementById('preview_bukti');
            preview.innerHTML = '';
            
            for (let i = 0; i < files.length; i++) {
                if (files[i].type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const div = document.createElement('div');
                        div.className = 'preview-image';
                        div.innerHTML = `
                            <img src="${e.target.result}" alt="Preview">
                            <button type="button" class="remove-preview" onclick="removePreview(this, ${i})">&times;</button>
                        `;
                        preview.appendChild(div);
                    };
                    reader.readAsDataURL(files[i]);
                }
            }
        });
    }
});

function removePreview(button, index) {
    const fileInput = document.getElementById('bukti_laporan');
    const dt = new DataTransfer();
    const files = fileInput.files;
    
    for (let i = 0; i < files.length; i++) {
        if (i !== index) {
            dt.items.add(files[i]);
        }
    }
    
    fileInput.files = dt.files;
    button.parentElement.remove();
}

// Submit form laporan

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
                alert('Kostum berhasil dikonfirmasi dikembalikan dan dipindahkan ke riwayat!');
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

document.addEventListener('DOMContentLoaded', function() {
    const statusFilter = document.getElementById('statusFilter');
    
    // Set nilai filter dari URL jika ada
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('status')) {
        statusFilter.value = urlParams.get('status');
    }
    
    statusFilter.addEventListener('change', function() {
        const selectedStatus = this.value;
        
        // Build URL dengan parameter status
        const urlParams = new URLSearchParams(window.location.search);
        
        if (selectedStatus) {
            urlParams.set('status', selectedStatus);
        } else {
            urlParams.delete('status');
        }
        
        // Hapus parameter page saat ganti filter
        urlParams.delete('page');
        
        // Redirect ke URL baru
        window.location.href = '?' + urlParams.toString();
    });
});

function clearFilter() {
    // Clear semua parameter dan redirect ke halaman awal
    window.location.href = window.location.pathname;
}

document.addEventListener('DOMContentLoaded', function() {
    setTimeout(() => {
        updateFilterCounter();
        updateRowNumbers();
    }, 100);
});

// Close modal with ESC key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeModal();
    }
});
</script>

<?php if (isset($conn)) { $conn->close(); } ?>

</body>
</html>