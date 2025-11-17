<?php
session_name('penyedia_session');
session_start();
header('Content-Type: application/json');

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
    
    // Ambil data JSON dari request
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['action']) || !isset($input['id_pesanan'])) {
        throw new Exception("Data request tidak valid");
    }
    
    $action = $input['action'];
    $idPesanan = (int)$input['id_pesanan'];
    
    // Validasi ID pesanan
    if ($idPesanan <= 0) {
        throw new Exception("ID pesanan tidak valid");
    }
    
    // Cek apakah pesanan exists dan ambil data
    $checkQuery = "SELECT * FROM pesanan WHERE id_pesanan = ?";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("i", $idPesanan);
    $stmt->execute();
    $result = $stmt->get_result();
    $pesanan = $result->fetch_assoc();
    
    if (!$pesanan) {
        throw new Exception("Pesanan dengan ID {$idPesanan} tidak ditemukan");
    }
    
    // Cek apakah user berhak mengubah pesanan ini (jika ada session)
    if (isset($_SESSION['id_user']) && $_SESSION['id_user'] != $pesanan['id_penyedia']) {
        throw new Exception("Anda tidak memiliki akses untuk mengubah pesanan ini");
    }
    
    $conn->begin_transaction();
    
    try {
        if ($action === 'accept') {
            // TERIMA PESANAN
            
            // 1. Update status pesanan menjadi 'accepted'
            $updatePesananQuery = "UPDATE pesanan SET status = 'accepted', updated_at = CURRENT_TIMESTAMP WHERE id_pesanan = ?";
            $stmt = $conn->prepare($updatePesananQuery);
            $stmt->bind_param("i", $idPesanan);
            
            if (!$stmt->execute()) {
                throw new Exception("Gagal update status pesanan: " . $stmt->error);
            }

            $checkStatusItemColumn = $conn->query("SHOW COLUMNS FROM transaksi_items LIKE 'status_item'");
            
            if ($checkStatusItemColumn->num_rows > 0) {
                // Update status_item untuk produk dari penyedia ini menjadi 'all_providers_approved'
                $updateTransaksiItemsQuery = "UPDATE transaksi_items ti 
                                            INNER JOIN form_katalog fc ON ti.id_produk = fc.id
                                            SET ti.status_item = 'all_providers_approved'
                                            WHERE ti.id_transaksi = ? 
                                            AND fc.id_user = ?
                                            AND ti.id_produk = ?";
                $stmtTransaksiItems = $conn->prepare($updateTransaksiItemsQuery);
                $stmtTransaksiItems->bind_param("sii", $pesanan['id_transaksi'], $pesanan['id_penyedia'], $pesanan['id_produk']);
                
                if (!$stmtTransaksiItems->execute()) {
                    throw new Exception("Gagal update status transaksi_items: " . $stmtTransaksiItems->error);
                }
                
                error_log("Updated status_item to all_providers_approved for provider {$pesanan['id_penyedia']}, product {$pesanan['id_produk']}, transaction {$pesanan['id_transaksi']}");
            }

            $checkAllAcceptedQuery = "SELECT COUNT(*) as total, 
                                             SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted
                                      FROM pesanan 
                                      WHERE id_transaksi = ?";
            $stmtCheck = $conn->prepare($checkAllAcceptedQuery);
            $stmtCheck->bind_param("s", $pesanan['id_transaksi']);
            $stmtCheck->execute();
            $countResult = $stmtCheck->get_result()->fetch_assoc();
            
            $totalPesanan = $countResult['total'];
            $acceptedPesanan = $countResult['accepted'];

            if ($totalPesanan > 0 && $acceptedPesanan == $totalPesanan) {
                $updateTransaksiStatusQuery = "UPDATE transaksi SET status_validasi = 'all_providers_approved' WHERE id_transaksi = ?";
                $stmtUpdateTransaksi = $conn->prepare($updateTransaksiStatusQuery);
                $stmtUpdateTransaksi->bind_param("s", $pesanan['id_transaksi']);
                
                if (!$stmtUpdateTransaksi->execute()) {
                    throw new Exception("Gagal update status transaksi ke all_providers_approved: " . $stmtUpdateTransaksi->error);
                }

                $createNotifTable = "CREATE TABLE IF NOT EXISTS `notifications_penyewa` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `id_user` int(11) NOT NULL,
                    `id_transaksi` varchar(10) DEFAULT NULL,
                    `id_pesanan` int(11) DEFAULT NULL,
                    `type` varchar(50) NOT NULL,
                    `title` varchar(255) NOT NULL,
                    `message` text NOT NULL,
                    `icon` varchar(100) DEFAULT 'zmdi-notifications',
                    `class` varchar(50) DEFAULT 'info',
                    `is_read` tinyint(1) DEFAULT 0,
                    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `id_user` (`id_user`),
                    KEY `id_transaksi` (`id_transaksi`),
                    KEY `id_pesanan` (`id_pesanan`),
                    KEY `is_read` (`is_read`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

                $conn->query($createNotifTable);
                $getPenyewaQuery = "SELECT id_user, nama_lengkap FROM transaksi WHERE id_transaksi = ?";
                $stmtPenyewa = $conn->prepare($getPenyewaQuery);
                $stmtPenyewa->bind_param("s", $pesanan['id_transaksi']);
                $stmtPenyewa->execute();
                $penyewaResult = $stmtPenyewa->get_result();
                $penyewaData = $penyewaResult->fetch_assoc();

                if ($penyewaData) {
                    
                    $insertNotifQuery = "INSERT INTO notifications_penyewa 
                                    (id_user, id_transaksi, id_pesanan, type, title, message, icon, class, is_read) 
                                    VALUES (?, ?, ?, 'order_accepted', 'Pesanan Diterima!', 
                                            'Semua penyedia telah menyetujui pesanan Anda. Silakan konfirmasi penerimaan kostum.', 
                                            'zmdi-check-circle', 'success', 0)";
                    
                    $stmtNotif = $conn->prepare($insertNotifQuery);
                    $stmtNotif->bind_param("isi", $penyewaData['id_user'], $pesanan['id_transaksi'], $idPesanan);
                    
                    if ($stmtNotif->execute()) {
                        error_log("âœ… Notifikasi acceptance berhasil dikirim untuk transaksi {$pesanan['id_transaksi']}");
                    } else {
                        error_log("âŒ Gagal mengirim notifikasi acceptance: " . $stmtNotif->error);
                    }
                }
                error_log("Semua penyedia telah menerima pesanan untuk transaksi {$pesanan['id_transaksi']}");

                $getBookingDatesQuery = "SELECT DISTINCT 
                                            p.id_produk, 
                                            p.tanggal_mulai, 
                                            p.tanggal_selesai, 
                                            p.jumlah_hari,
                                            p.nama_penyewa,
                                            ti.tanggal_peminjaman
                                         FROM pesanan p
                                         LEFT JOIN transaksi_items ti ON p.id_transaksi = ti.id_transaksi 
                                                                      AND p.id_produk = ti.id_produk
                                         WHERE p.id_transaksi = ? AND p.status = 'accepted'";
                
                $stmtBookingDates = $conn->prepare($getBookingDatesQuery);
                $stmtBookingDates->bind_param("s", $pesanan['id_transaksi']);
                $stmtBookingDates->execute();
                $bookingResult = $stmtBookingDates->get_result();
                
                // Pastikan tabel unavailable_dates ada
                $createUnavailableTable = "CREATE TABLE IF NOT EXISTS `unavailable_dates` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `product_id` int(11) NOT NULL,
                    `date` date NOT NULL,
                    `booked_by` varchar(255) DEFAULT NULL,
                    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `unique_product_date` (`product_id`, `date`),
                    KEY `product_id` (`product_id`),
                    KEY `date` (`date`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
                
                $conn->query($createUnavailableTable);
                
                while ($bookingRow = $bookingResult->fetch_assoc()) {
                    $productId = $bookingRow['id_produk'];
                    $bookedBy = $bookingRow['nama_penyewa'];
                    $tanggalPeminjaman = $bookingRow['tanggal_peminjaman'];
                    
                    // Proses tanggal peminjaman dari transaksi_items jika ada
                    $datesToBook = [];
                    
                    if (!empty($tanggalPeminjaman)) {
                        // Parse tanggal dari string comma-separated
                        $tanggalArray = array_filter(explode(',', $tanggalPeminjaman), function($date) {
                            return !empty(trim($date));
                        });
                        
                        foreach ($tanggalArray as $tanggal) {
                            $tanggal = trim($tanggal);
                            if (!empty($tanggal)) {
                                $datesToBook[] = date('Y-m-d', strtotime($tanggal));
                            }
                        }
                    } else {
                        // Fallback ke tanggal_mulai dan tanggal_selesai dari pesanan
                        $tanggalMulai = $bookingRow['tanggal_mulai'];
                        $tanggalSelesai = $bookingRow['tanggal_selesai'];
                        $jumlahHari = $bookingRow['jumlah_hari'];
                        
                        if (!empty($tanggalMulai)) {
                            if (!empty($tanggalSelesai)) {
                                // Generate range tanggal dari mulai ke selesai
                                $startDate = new DateTime($tanggalMulai);
                                $endDate = new DateTime($tanggalSelesai);
                                $interval = new DateInterval('P1D');
                                $dateRange = new DatePeriod($startDate, $interval, $endDate->modify('+1 day'));
                                
                                foreach ($dateRange as $date) {
                                    $datesToBook[] = $date->format('Y-m-d');
                                }
                            } else {
                                // Jika hanya ada tanggal_mulai, gunakan jumlah_hari
                                $startDate = new DateTime($tanggalMulai);
                                for ($i = 0; $i < $jumlahHari; $i++) {
                                    $datesToBook[] = $startDate->format('Y-m-d');
                                    $startDate->modify('+1 day');
                                }
                            }
                        }
                    }
                    
                    // Insert tanggal-tanggal ke tabel unavailable_dates
                    foreach ($datesToBook as $dateToBook) {
                        $insertBookingQuery = "INSERT INTO unavailable_dates (product_id, date, booked_by) 
                                              VALUES (?, ?, ?) 
                                              ON DUPLICATE KEY UPDATE 
                                              booked_by = VALUES(booked_by)";
                        
                        $stmtInsertBooking = $conn->prepare($insertBookingQuery);
                        $stmtInsertBooking->bind_param("iss", $productId, $dateToBook, $bookedBy);
                        
                        if ($stmtInsertBooking->execute()) {
                            error_log("âœ… Tanggal {$dateToBook} berhasil ditandai sebagai booked untuk produk {$productId} oleh {$bookedBy}");
                        } else {
                            error_log("âŒ Gagal menandai tanggal {$dateToBook} sebagai booked untuk produk {$productId}: " . $stmtInsertBooking->error);
                        }
                    }
                    
                    if (!empty($datesToBook)) {
                        error_log("ðŸ“… Berhasil menandai " . count($datesToBook) . " tanggal sebagai booked untuk produk {$productId}");
                    }
                }
                
                error_log("ðŸŽ¯ Proses penandaan tanggal booking selesai untuk transaksi {$pesanan['id_transaksi']}");
            }
            

            $checkPeminjamanQuery = "SELECT COUNT(*) as jumlah FROM peminjaman WHERE id_pesanan = ?";
            $stmt = $conn->prepare($checkPeminjamanQuery);
            $stmt->bind_param("i", $idPesanan);
            $stmt->execute();
            $result = $stmt->get_result();
            $peminjamanCount = $result->fetch_assoc()['jumlah'];
            
            if ($peminjamanCount == 0) {
                $id_pesanan = (int)$pesanan['id_pesanan'];
                $id_transaksi = !empty($pesanan['id_transaksi']) ? (string)$pesanan['id_transaksi'] : '0';
                $id_penyewa = !empty($pesanan['id_user']) ? (int)$pesanan['id_user'] : 0;
                $id_penyedia = !empty($pesanan['id_penyedia']) ? (int)$pesanan['id_penyedia'] : 0;
                $id_produk = !empty($pesanan['id_produk']) ? (int)$pesanan['id_produk'] : 0;
                $nama_penyewa = !empty($pesanan['nama_penyewa']) ? (string)$pesanan['nama_penyewa'] : 'Unknown';
                $nomor_hp = !empty($pesanan['nomor_hp']) ? (string)$pesanan['nomor_hp'] : '';
                $nama_kostum = !empty($pesanan['nama_kostum']) ? (string)$pesanan['nama_kostum'] : 'Unknown';
                $size = isset($pesanan['size']) ? (string)$pesanan['size'] : '';
                $quantity = !empty($pesanan['quantity']) ? (int)$pesanan['quantity'] : 1;
                $tanggal_mulai = !empty($pesanan['tanggal_mulai']) ? (string)$pesanan['tanggal_mulai'] : date('Y-m-d');
                $tanggal_selesai = !empty($pesanan['tanggal_selesai']) ? (string)$pesanan['tanggal_selesai'] : date('Y-m-d', strtotime('+1 day'));
                $jumlah_hari = !empty($pesanan['jumlah_hari']) ? (int)$pesanan['jumlah_hari'] : 1;
                $status_peminjaman = 'sedang_berjalan';
                
                // Debug: Log data yang akan di-insert
                $debugData = [
                    'id_pesanan' => $id_pesanan,
                    'id_transaksi' => $id_transaksi,
                    'id_penyewa' => $id_penyewa,
                    'id_penyedia' => $id_penyedia,
                    'id_produk' => $id_produk,
                    'nama_penyewa' => $nama_penyewa,
                    'nomor_hp' => $nomor_hp,
                    'nama_kostum' => $nama_kostum,
                    'size' => $size,
                    'quantity' => $quantity,
                    'tanggal_mulai' => $tanggal_mulai,
                    'tanggal_selesai' => $tanggal_selesai,
                    'jumlah_hari' => $jumlah_hari,
                    'status_peminjaman' => $status_peminjaman
                ];
                error_log("Data yang akan di-insert: " . json_encode($debugData));
                
                // Validasi data yang wajib ada
                if (empty($nama_penyewa) || $nama_penyewa === 'Unknown') {
                    throw new Exception("Nama penyewa kosong atau tidak valid");
                }
                if (empty($nama_kostum) || $nama_kostum === 'Unknown') {
                    throw new Exception("Nama kostum kosong atau tidak valid");
                }
                
                // 3. Insert ke tabel peminjaman dengan query yang lebih sederhana
                $insertPeminjamanQuery = "INSERT INTO peminjaman (
                    id_pesanan, id_transaksi, id_penyewa, id_penyedia, id_produk,
                    nama_penyewa, nomor_hp, nama_kostum, size, quantity,
                    tanggal_mulai, tanggal_selesai, jumlah_hari, status_peminjaman
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $conn->prepare($insertPeminjamanQuery);
                
                if (!$stmt) {
                    throw new Exception("Prepare statement gagal: " . $conn->error);
                }
            
                error_log("Binding parameters...");
                $bindResult = $stmt->bind_param("isiissssssssis", 
                    $id_pesanan,        // i - integer
                    $id_transaksi,      // s - string
                    $id_penyewa,        // i - integer
                    $id_penyedia,       // i - integer
                    $id_produk,         // i - integer (PERBAIKAN: sebelumnya 's')
                    $nama_penyewa,      // s - string
                    $nomor_hp,          // s - string
                    $nama_kostum,       // s - string
                    $size,              // s - string
                    $quantity,          // s - string (PERBAIKAN: ubah ke string karena bisa varchar)
                    $tanggal_mulai,     // s - string
                    $tanggal_selesai,   // s - string
                    $jumlah_hari,       // i - integer
                    $status_peminjaman  // s - string
                );
                
                if (!$bindResult) {
                    throw new Exception("Gagal bind parameter: " . $stmt->error);
                }
                
                error_log("Parameters bound successfully, executing...");
                if (!$stmt->execute()) {
                    throw new Exception("Gagal insert ke peminjaman: " . $stmt->error);
                }
                
                error_log("Pesanan {$idPesanan} berhasil diterima dan dipindahkan ke peminjaman");
            }

             $createNotificationTable = "CREATE TABLE IF NOT EXISTS `notifications_admin` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `type` varchar(100) NOT NULL,
                `title` varchar(255) NOT NULL,
                `message` text NOT NULL,
                `id_transaksi` varchar(10) DEFAULT NULL,
                `id_user` int(11) DEFAULT NULL,
                `is_read` tinyint(1) DEFAULT 0,
                `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            $conn->query($createNotificationTable);
            
            // Insert notifikasi untuk admin
            $notificationTitle = "Konfirmasi Pengembalian Kostum oleh Penyedia";
            $notificationMessage = "Penyedia telah mengkonfirmasi bahwa kostum \"" . $pesananData['nama_kostum'] . "\" telah dikembalikan oleh penyewa. Pesanan telah dipindahkan ke riwayat. ID Transaksi: " . $pesananData['id_transaksi'];
            
            $insertNotification = "INSERT INTO notifications_admin (type, title, message, id_transaksi, id_user, is_read) 
                                  VALUES ('provider_confirmed_returned', ?, ?, ?, NULL, 0)";
            $notifStmt = $conn->prepare($insertNotification);
            $notifStmt->bind_param("sss", $notificationTitle, $notificationMessage, $pesananData['id_transaksi']);
            $notifStmt->execute();
            
            // 6. Log aktivitas
            error_log("Pesanan {$idPesanan} - kostum telah dikembalikan dan dipindahkan ke riwayat");

            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Pesanan berhasil diterima dan dipindahkan ke data peminjaman',
                'id_pesanan' => $idPesanan,
                'id_transaksi' => $pesanan['id_transaksi'],
                'all_accepted' => ($acceptedPesanan == $totalPesanan) 
            ]);
            
        } elseif ($action === 'reject') {
            // TOLAK PESANAN
            
            $reason = isset($input['reason']) ? trim($input['reason']) : '';
            
            if (empty($reason)) {
                throw new Exception("Alasan penolakan harus diisi");
            }

            $createRejectionTable = "CREATE TABLE IF NOT EXISTS `rejection_reasons` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `id_transaksi` varchar(10) NOT NULL,
                `id_pesanan` int(11) NOT NULL,
                `id_penyedia` int(11) NOT NULL,
                `provider_name` varchar(255) DEFAULT NULL,
                `product_name` varchar(255) DEFAULT NULL,
                `reason` text NOT NULL,
                `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `id_transaksi` (`id_transaksi`),
                KEY `id_pesanan` (`id_pesanan`),
                KEY `id_penyedia` (`id_penyedia`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            
            $conn->query($createRejectionTable);

            // 2. Buat tabel notifikasi admin jika belum ada
            $createAdminNotifTable = "CREATE TABLE IF NOT EXISTS `notifications_admin` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `id_transaksi` varchar(10) DEFAULT NULL,
                `id_pesanan` int(11) DEFAULT NULL,
                `type` varchar(50) NOT NULL,
                `title` varchar(255) NOT NULL,
                `message` text NOT NULL,
                `icon` varchar(100) DEFAULT 'mdi-alert-circle',
                `class` varchar(50) DEFAULT 'danger',
                `is_read` tinyint(1) DEFAULT 0,
                `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `id_transaksi` (`id_transaksi`),
                KEY `id_pesanan` (`id_pesanan`),
                KEY `is_read` (`is_read`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            
            $conn->query($createAdminNotifTable);

            $getProviderProductQuery = "SELECT 
                                            at.nama as provider_name,
                                            fk.judul_post as product_name
                                        FROM akun_toko at
                                        INNER JOIN form_katalog fk ON at.id = fk.id_user
                                        WHERE at.id = ? AND fk.id = ?";
            $stmtProviderProduct = $conn->prepare($getProviderProductQuery);
            $stmtProviderProduct->bind_param("ii", $pesanan['id_penyedia'], $pesanan['id_produk']);
            $stmtProviderProduct->execute();
            $providerProductResult = $stmtProviderProduct->get_result();
            $providerProductData = $providerProductResult->fetch_assoc();
            
            $providerName = $providerProductData ? $providerProductData['provider_name'] : 'Unknown Provider';
            $productName = $providerProductData ? $providerProductData['product_name'] : 'Unknown Product';

            // 4. Simpan alasan penolakan
            $insertRejectionQuery = "INSERT INTO rejection_reasons 
                                    (id_transaksi, id_pesanan, id_penyedia, provider_name, product_name, reason) 
                                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmtRejection = $conn->prepare($insertRejectionQuery);
            $stmtRejection->bind_param("siisss", 
                $pesanan['id_transaksi'], 
                $idPesanan, 
                $pesanan['id_penyedia'], 
                $providerName, 
                $productName, 
                $reason
            );
            
            if (!$stmtRejection->execute()) {
                throw new Exception("Gagal menyimpan alasan penolakan: " . $stmtRejection->error);
            }


            $updatePesananStatusQuery = "UPDATE pesanan SET status = 'rejected', updated_at = CURRENT_TIMESTAMP WHERE id_pesanan = ?";
            $stmtUpdatePesanan = $conn->prepare($updatePesananStatusQuery);
            $stmtUpdatePesanan->bind_param("i", $idPesanan);
            
            if (!$stmtUpdatePesanan->execute()) {
                throw new Exception("Gagal update status pesanan: " . $stmtUpdatePesanan->error);
            }

            $updateTransaksiStatusQuery = "UPDATE transaksi SET status_validasi = 'provider_rejected' WHERE id_transaksi = ?";
            $stmtUpdateTransaksi = $conn->prepare($updateTransaksiStatusQuery);
            $stmtUpdateTransaksi->bind_param("s", $pesanan['id_transaksi']);
            
            if (!$stmtUpdateTransaksi->execute()) {
                throw new Exception("Gagal update status transaksi: " . $stmtUpdateTransaksi->error);
            }

            // 6. Kirim notifikasi ke admin
            $insertAdminNotifQuery = "INSERT INTO notifications_admin 
                                    (id_transaksi, id_pesanan, type, title, message, icon, class, is_read) 
                                    VALUES (?, ?, 'provider_rejection', 'Pesanan Ditolak Penyedia', ?, 'mdi-alert-circle', 'danger', 0)";
            
            $notifMessage = "Penyedia \"{$providerName}\" menolak pesanan produk \"{$productName}\" untuk transaksi {$pesanan['id_transaksi']}. Alasan: {$reason}";
            
            $stmtAdminNotif = $conn->prepare($insertAdminNotifQuery);
            $stmtAdminNotif->bind_param("sis", $pesanan['id_transaksi'], $idPesanan, $notifMessage);
                
            if (!$stmtAdminNotif->execute()) {
                throw new Exception("Gagal mengirim notifikasi ke admin: " . $stmtAdminNotif->error);
            }

            error_log("Pesanan {$idPesanan} ditolak dengan alasan: {$reason}");
            
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Pesanan berhasil ditolak dan notifikasi telah dikirim ke admin',
                'id_pesanan' => $idPesanan,
                'id_transaksi' => $pesanan['id_transaksi'],
                'reason' => $reason
            ]);
            
        } elseif ($action === 'pesanan_diterima') {
            // KONFIRMASI PESANAN DITERIMA PENYEWA
            
            // 1. Update status pesanan menjadi 'pesanan_diterima_penyewa'
            $updatePesananQuery = "UPDATE pesanan SET status = 'pesanan_diterima_penyewa', updated_at = CURRENT_TIMESTAMP WHERE id_pesanan = ?";
            $stmt = $conn->prepare($updatePesananQuery);
            $stmt->bind_param("i", $idPesanan);
            
            if (!$stmt->execute()) {
                throw new Exception("Gagal update status pesanan: " . $stmt->error);
            }

            $checkStatusItemColumn = $conn->query("SHOW COLUMNS FROM transaksi_items LIKE 'status_item'");
            
            if ($checkStatusItemColumn->num_rows > 0) {
                // Update status_item hanya untuk produk dari penyedia ini
                $updateTransaksiItemsQuery = "UPDATE transaksi_items ti 
                                            INNER JOIN form_katalog fc ON ti.id_produk = fc.id
                                            SET ti.status_item = 'costume_received'  
                                            WHERE ti.id_transaksi = ? 
                                            AND fc.id_user = ?
                                            AND ti.id_produk = ?";
                $stmtTransaksiItems = $conn->prepare($updateTransaksiItemsQuery);
                $stmtTransaksiItems->bind_param("sii", $pesanan['id_transaksi'], $pesanan['id_penyedia'], $pesanan['id_produk']);
                
                if (!$stmtTransaksiItems->execute()) {
                    throw new Exception("Gagal update status transaksi_items: " . $stmtTransaksiItems->error);
                }
                
                error_log("Updated status_item to costume_received for provider {$pesanan['id_penyedia']}, product {$pesanan['id_produk']}, transaction {$pesanan['id_transaksi']}");
            }
            
            // 2. Log aktivitas
            error_log("Pesanan {$idPesanan} telah dikonfirmasi diterima oleh penyewa");
            
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Status berhasil diupdate: Pesanan telah diterima oleh penyewa',
                'id_pesanan' => $idPesanan
            ]);
        
            } elseif ($action === 'kostum_kembali') {
            // KONFIRMASI KOSTUM TELAH DIKEMBALIKAN
            
            // 1. Ambil data pesanan untuk dipindahkan ke riwayat
            $getPesananDataQuery = "SELECT 
                p.*, 
                COALESCE(t.nama_lengkap, p.nama_penyewa) as nama_penyewa_benar 
            FROM pesanan p 
            LEFT JOIN transaksi t ON p.id_transaksi = t.id_transaksi 
            WHERE p.id_pesanan = ?";
            $stmtGetData = $conn->prepare($getPesananDataQuery);
            $stmtGetData->bind_param("i", $idPesanan);
            $stmtGetData->execute();
            $pesananData = $stmtGetData->get_result()->fetch_assoc();
            
            if (!$pesananData) {
                throw new Exception("Data pesanan tidak ditemukan untuk dipindahkan ke riwayat");
            }
            
            // 2. Buat tabel riwayat_pesanan jika belum ada
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
                `status` enum('selesai') DEFAULT 'selesai',
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
            
            // 3. Insert data ke tabel riwayat_pesanan
            $insertRiwayatQuery = "INSERT INTO riwayat_pesanan (
                id_pesanan_asli, id_transaksi, id_user, id_produk, id_penyedia,
                nama_penyewa, nomor_hp, nama_kostum, size, quantity,
                tanggal_pinjam, jumlah_hari, tanggal_mulai, tanggal_selesai,
                tanggal_pengembalian, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'selesai')";
            
            $stmtInsertRiwayat = $conn->prepare($insertRiwayatQuery);
            $stmtInsertRiwayat->bind_param("isiiiisssisiss", 
                $pesananData['id_pesanan'],
                $pesananData['id_transaksi'],
                $pesananData['id_user'],
                $pesananData['id_produk'],
                $pesananData['id_penyedia'],
                $pesananData['nama_penyewa_benar'],
                $pesananData['nomor_hp'],
                $pesananData['nama_kostum'],
                $pesananData['size'],
                $pesananData['quantity'],
                $pesananData['tanggal_pinjam'],
                $pesananData['jumlah_hari'],
                $pesananData['tanggal_mulai'],
                $pesananData['tanggal_selesai']
            );
            
            if (!$stmtInsertRiwayat->execute()) {
                throw new Exception("Gagal menyimpan data ke riwayat pesanan: " . $stmtInsertRiwayat->error);
            }
            
            // 4. Hapus data dari tabel pesanan
            $deletePesananQuery = "DELETE FROM pesanan WHERE id_pesanan = ?";
            $stmtDeletePesanan = $conn->prepare($deletePesananQuery);
            $stmtDeletePesanan->bind_param("i", $idPesanan);
            
            if (!$stmtDeletePesanan->execute()) {
                throw new Exception("Gagal menghapus data dari tabel pesanan: " . $stmtDeletePesanan->error);
            }

            // 5. Update status_item di transaksi_items jika ada
            $checkStatusItemColumn = $conn->query("SHOW COLUMNS FROM transaksi_items LIKE 'status_item'");
            
            if ($checkStatusItemColumn->num_rows > 0) {
                $updateTransaksiItemsQuery = "UPDATE transaksi_items ti 
                                            INNER JOIN form_katalog fc ON ti.id_produk = fc.id
                                            SET ti.status_item = 'completed' 
                                            WHERE ti.id_transaksi = ? 
                                            AND fc.id_user = ?
                                            AND ti.id_produk = ?";
                $stmtTransaksiItems = $conn->prepare($updateTransaksiItemsQuery);
                $stmtTransaksiItems->bind_param("sii", $pesananData['id_transaksi'], $pesananData['id_penyedia'], $pesananData['id_produk']);
                
                if (!$stmtTransaksiItems->execute()) {
                    error_log("Gagal update status transaksi_items: " . $stmtTransaksiItems->error);
                } else {
                    error_log("Updated status_item to completed for provider {$pesananData['id_penyedia']}, product {$pesananData['id_produk']}, transaction {$pesananData['id_transaksi']}");
                }
            }

            // 6. Log aktivitas
            error_log("Pesanan {$idPesanan} - kostum telah dikembalikan dan dipindahkan ke riwayat");
            
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Kostum telah dikembalikan dan pesanan dipindahkan ke riwayat',
                'id_pesanan' => $idPesanan,
                'moved_to_history' => true
            ]);

        } else {
            throw new Exception("Action tidak valid: {$action}");
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Error in proses_pesanan.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => [
            'id_pesanan' => isset($idPesanan) ? $idPesanan : 'tidak ada',
            'id_transaksi' => isset($pesanan['id_transaksi']) ? $pesanan['id_transaksi'] : 'tidak ada',
            'error_line' => $e->getLine(),
            'error_file' => $e->getFile()
        ]
    ]);
    
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
