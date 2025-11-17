<?php
session_name('penyedia_session');
session_start();
header('Content-Type: application/json');

// At the start of the file
error_log("Processing request with action: " . ($_POST['action'] ?? 'none'));
error_log("POST data: " . print_r($_POST, true));

// Inside the pesanan_diterima block
error_log("Processing pesanan_diterima for id_pesanan: $idPesanan");
error_log("Pesanan data: " . print_r($pesanan, true));
error_log("Provider product data: " . print_r($providerProductData, true));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'pesanan_diterima' && !empty($_POST['id_pesanan'])) {
        header('Content-Type: application/json');
        
        try {
            $conn = new mysqli("localhost", "root", "password123", "daftar_akun");
            if ($conn->connect_error) {
                throw new Exception("Koneksi database gagal");
            }

            $idPesanan = (int)$_POST['id_pesanan'];
            $buktiFile = isset($_FILES['bukti_diterima']) ? $_FILES['bukti_diterima'] : null;
            $catatan = isset($_POST['catatan_konfirmasi']) ? $_POST['catatan_konfirmasi'] : '';

            $conn->begin_transaction(); 

            $buktiPath = '';

            
            if ($buktiFile && $buktiFile['error'] === UPLOAD_ERR_OK) {
                // Jalur server untuk mengunggah file
                $uploadDirServer = __DIR__ . "/../../uploads/bukti_diterima/"; // Naik 2 level ke root
                
                error_log("Upload directory: " . $uploadDirServer);

                // Buat folder jika belum ada
                if (!file_exists($uploadDirServer)) {
                    mkdir($uploadDirServer, 0777, true);
                    error_log("Created directory: " . $uploadDirServer);
                }
                
                $fileName = 'bukti_' . $idPesanan . '_' . time() . '.' . pathinfo($buktiFile['name'], PATHINFO_EXTENSION);
                $targetFile = $uploadDirServer . $fileName;
                
                if (!move_uploaded_file($buktiFile['tmp_name'], $targetFile)) {
                    throw new Exception("Gagal upload file bukti");
                }
                
                // Simpan path lengkap untuk database
                $buktiPath = 'uploads/bukti_diterima/' . $fileName;
                
                // Debug info
                error_log("File uploaded to: " . $targetFile);
                error_log("Database path: " . $buktiPath);
                error_log("File exists: " . (file_exists($targetFile) ? 'YES' : 'NO'));
            }
            
            // Update status pesanan
            $updateQuery = "UPDATE pesanan SET status = 'pesanan_diterima_penyewa', bukti_diterima_path = ?, updated_at = CURRENT_TIMESTAMP WHERE id_pesanan = ?";
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param("si", $buktiPath, $idPesanan);
            if (!$stmt->execute()) {
                throw new Exception("Gagal update status pesanan: " . $stmt->error);
            }

            // PERBAIKAN: Query yang benar untuk mengambil data dari tabel yang tepat
            $getPesananQuery = "SELECT 
                p.id_pesanan,
                p.id_transaksi, 
                p.id_user as id_penyewa,
                p.id_penyedia,
                p.nama_kostum,
                p.tanggal_pinjam,
                p.tanggal_mulai,
                p.tanggal_selesai,
                p.jumlah_hari,
                p.nomor_hp,
                ti.size,
                ti.quantity,
                ti.subtotal,
                fc.judul_post as nama_kostum_detail
            FROM pesanan p 
            LEFT JOIN transaksi_items ti ON p.id_transaksi = ti.id_transaksi AND p.id_produk = ti.id_produk
            LEFT JOIN form_katalog fc ON p.id_produk = fc.id
            WHERE p.id_pesanan = ?";

            $stmtPesanan = $conn->prepare($getPesananQuery);
            $stmtPesanan->bind_param("i", $idPesanan);
            $stmtPesanan->execute();
            $resultPesanan = $stmtPesanan->get_result();
            $pesananData = $resultPesanan->fetch_assoc();

            if (!$pesananData) {
                throw new Exception("Data pesanan tidak ditemukan untuk ID: " . $idPesanan);
            }

            // Debug: Log data yang ditemukan
            error_log("Data pesanan lengkap: " . json_encode($pesananData));

            // Pastikan semua data tidak NULL dan sesuai tipe dengan data dari tabel yang benar
            $dataInsert = [
                'id_pesanan' => (int)$pesananData['id_pesanan'],
                'id_transaksi' => (string)$pesananData['id_transaksi'],
                'id_penyewa' => (int)$pesananData['id_penyewa'],
                'id_penyedia' => (int)$pesananData['id_penyedia'],
                'nama_kostum' => (string)($pesananData['nama_kostum_detail'] ?? $pesananData['nama_kostum'] ?? 'Unknown'),
                'size' => (string)($pesananData['size'] ?? ''), // Dari transaksi_items
                'quantity' => (int)($pesananData['quantity'] ?? 1), // Dari transaksi_items
                'tanggal_mulai' => (string)($pesananData['tanggal_mulai'] ?? date('Y-m-d')), // Dari pesanan
                'tanggal_selesai' => (string)($pesananData['tanggal_selesai'] ?? date('Y-m-d')), // Dari pesanan
                'jumlah_hari' => (int)($pesananData['jumlah_hari'] ?? 1),
                'nomor_hp' => (string)($pesananData['nomor_hp'] ?? ''),
                'bukti' => (string)$buktiPath, 
                'keterangan' => (string)$catatan,
                'subtotal' => (float)($pesananData['subtotal'] ?? 0) // Dari transaksi_items
            ];

            // Debug: Log data yang akan diinsert
            error_log("Data untuk insert ke peminjaman_aktif: " . json_encode($dataInsert));

            // PERBAIKAN: Update query untuk menambahkan kolom total
            $insertPeminjamanAktif = "INSERT INTO peminjaman_aktif 
                (id_pesanan, id_transaksi, id_penyewa, id_penyedia, nama_kostum, 
                size, quantity, tanggal_mulai, tanggal_selesai, jumlah_hari, 
                status_peminjaman, tanggal_diterima, nomor_hp, bukti, keterangan, total) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'aktif', NOW(), ?, ?, ?, ?)";

            $stmtPeminjamanAktif = $conn->prepare($insertPeminjamanAktif);
            
            if (!$stmtPeminjamanAktif) {
                throw new Exception("Gagal prepare statement: " . $conn->error);
            }

            // PERBAIKAN: Bind parameters dengan menambahkan parameter untuk total
            $bindResult = $stmtPeminjamanAktif->bind_param("isiisssisssssd", 
                $dataInsert['id_pesanan'],      // i - 1
                $dataInsert['id_transaksi'],    // s - 2
                $dataInsert['id_penyewa'],      // i - 3
                $dataInsert['id_penyedia'],     // i - 4
                $dataInsert['nama_kostum'],     // s - 5
                $dataInsert['size'],            // s - 6
                $dataInsert['quantity'],        // i - 7
                $dataInsert['tanggal_mulai'],   // s - 8
                $dataInsert['tanggal_selesai'], // s - 9
                $dataInsert['jumlah_hari'],     // i - 10
                $dataInsert['nomor_hp'],        // s - 11
                $dataInsert['bukti'],           // s - 12
                $dataInsert['keterangan'],      // s - 13
                $dataInsert['subtotal']         // d - 14 (double/float)
            );

            if (!$bindResult) {
                throw new Exception("Gagal bind parameters: " . $stmtPeminjamanAktif->error);
            }

            if (!$stmtPeminjamanAktif->execute()) {
                error_log("Error detail: " . $stmtPeminjamanAktif->error);
                error_log("Error number: " . $stmtPeminjamanAktif->errno);
                throw new Exception("Gagal insert ke peminjaman_aktif: " . $stmtPeminjamanAktif->error);
            }

            error_log("✅ Data berhasil diinsert ke peminjaman_aktif untuk pesanan {$idPesanan}");
                        
         

            $getPesananDetails = $conn->prepare("
                SELECT 
                    p.id_transaksi, 
                    p.id_user, 
                    p.id_produk,
                    t.nama_lengkap AS nama_penyewa,
                    fc.judul_post AS nama_kostum
                FROM pesanan p
                JOIN transaksi t ON p.id_transaksi = t.id_transaksi
                LEFT JOIN form_katalog fc ON p.id_produk = fc.id
                WHERE p.id_pesanan = ?
            ");
            $getPesananDetails->bind_param("i", $idPesanan);
            $getPesananDetails->execute();
            $resultDetails = $getPesananDetails->get_result();
            $pesananDetails = $resultDetails->fetch_assoc();

            if ($pesananDetails) {
                $idTransaksi = $pesananDetails['id_transaksi'];
                $idUserCustomer = $pesananDetails['id_user']; // ID pengguna (penyewa)
                $namaPenyewa = $pesananDetails['nama_penyewa'];
                $namaKostum = $pesananDetails['nama_kostum'];

                // Pastikan tabel notifications_admin ada. Ini penting jika belum dibuat secara manual.
                $createNotificationTable = "
                    CREATE TABLE IF NOT EXISTS `notifications_admin` (
                        `id` INT AUTO_INCREMENT PRIMARY KEY,
                        `id_transaksi` VARCHAR(255) NULL,
                        `id_pesanan` INT NULL,
                        `id_user` INT NULL,
                        `type` VARCHAR(50) NOT NULL,
                        `title` VARCHAR(255) NOT NULL,
                        `message` TEXT NOT NULL,
                        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        `is_read` BOOLEAN DEFAULT FALSE,
                        INDEX(`type`),
                        INDEX(`created_at`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                ";
                $conn->query($createNotificationTable);

                // Masukkan notifikasi ke tabel notifications_admin
                $title = 'Kostum Telah Diterima Penyewa';
                $message = "Kostum '{$namaKostum}' pada transaksi {$idTransaksi} telah dikonfirmasi diterima oleh {$namaPenyewa}.";
                $notificationType = 'customer_received_costume'; 

                $insertNotification = $conn->prepare("
                    INSERT INTO notifications_admin (id_transaksi, id_pesanan, id_user, type, title, message)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $insertNotification->bind_param(
                    "siisss", 
                    $idTransaksi, 
                    $idPesanan, 
                    $idUserCustomer, 
                    $notificationType, 
                    $title, 
                    $message
                );
                
                if (!$insertNotification->execute()) {
                    throw new Exception("Gagal menyimpan notifikasi admin: " . $insertNotification->error);
                }
            }

               
            
                $conn->commit();
                echo json_encode(['success' => true]);
                exit;
            
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }
}

$host = "localhost";
$user = "root";
$password = "password123";
$database = "daftar_akun";



try {
    $conn = new mysqli($host, $user, $password, $database);    
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
    $conn->query("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

    function escapeSpecialChars($str) {
        // Hanya gunakan untuk non-prepared statements
        // Untuk prepared statements, escaping tidak diperlukan
        return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
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

            error_log("🎯 Mulai proses pengurangan stok untuk pesanan {$idPesanan}");
    
           $checkExistingQuery = "SELECT id_peminjaman_aktif FROM peminjaman_aktif WHERE id_pesanan = ?";
                $stmtCheck = $conn->prepare($checkExistingQuery);
                $stmtCheck->bind_param("i", $idPesanan);
                $stmtCheck->execute();
                $existingResult = $stmtCheck->get_result();
                
                if ($existingResult->num_rows > 0) {
                    // DATA SUDAH ADA - LAKUKAN UPDATE SAJA
                    $updatePeminjamanAktifQuery = "UPDATE peminjaman_aktif 
                                                SET status_peminjaman = 'Diterima Penyedia', 
                                                    updated_at = CURRENT_TIMESTAMP
                                                WHERE id_pesanan = ?";
                    $stmtUpdatePeminjamanAktif = $conn->prepare($updatePeminjamanAktifQuery);
                    $stmtUpdatePeminjamanAktif->bind_param("i", $idPesanan);
                    
                    if (!$stmtUpdatePeminjamanAktif->execute()) {
                        throw new Exception("Gagal mengupdate peminjaman_aktif untuk pesanan {$idPesanan}: " . $stmtUpdatePeminjamanAktif->error);
                    } else {
                        error_log("✅ Berhasil update peminjaman_aktif untuk pesanan {$idPesanan} dengan status 'Diterima Penyedia'");
                    }
                } else {
                    // DATA BELUM ADA - LAKUKAN INSERT BARU
                    $getPesananForAktif = "SELECT 
                        p.id_pesanan,
                        p.id_transaksi,
                        p.id_penyedia,
                        p.nama_kostum,
                        p.size,
                        p.quantity,
                        p.tanggal_mulai,
                        p.tanggal_selesai,
                        p.jumlah_hari,
                        p.nomor_hp,
                        t.id_user as id_penyewa,
                        ti.subtotal
                    FROM pesanan p 
                    LEFT JOIN transaksi t ON p.id_transaksi = t.id_transaksi
                    LEFT JOIN transaksi_items ti ON p.id_transaksi = ti.id_transaksi AND p.id_produk = ti.id_produk
                    WHERE p.id_pesanan = ?";
                    
                    $stmtPesananAktif = $conn->prepare($getPesananForAktif);
                    $stmtPesananAktif->bind_param("i", $idPesanan);
                    $stmtPesananAktif->execute();
                    $resultPesananAktif = $stmtPesananAktif->get_result();
                    $pesananAktifData = $resultPesananAktif->fetch_assoc();

                    if ($pesananAktifData) {
                        $insertPeminjamanAktif = "INSERT INTO peminjaman_aktif 
                            (id_pesanan, id_transaksi, id_penyewa, id_penyedia, nama_kostum, 
                            size, quantity, tanggal_mulai, tanggal_selesai, jumlah_hari, 
                            status_peminjaman, tanggal_diterima, nomor_hp, bukti, keterangan, total) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, '', ?, ?)";

                        $stmtInsertAktif = $conn->prepare($insertPeminjamanAktif);
                        
                        if (!$stmtInsertAktif) {
                            throw new Exception("Gagal prepare statement peminjaman_aktif: " . $conn->error);
                        }

                        $statusPeminjaman = 'Diterima Penyedia';
                        $keteranganAktif = 'Pesanan telah diterima oleh penyedia, menunggu konfirmasi diterima penyewa';
                        $totalHarga = (float)($pesananAktifData['subtotal'] ?? 0);

                        $stmtInsertAktif->bind_param("isiissississsd", 
                            $pesananAktifData['id_pesanan'],
                            $pesananAktifData['id_transaksi'],
                            $pesananAktifData['id_penyewa'],
                            $pesananAktifData['id_penyedia'],
                            $pesananAktifData['nama_kostum'],
                            $pesananAktifData['size'],
                            $pesananAktifData['quantity'],
                            $pesananAktifData['tanggal_mulai'],
                            $pesananAktifData['tanggal_selesai'],
                            $pesananAktifData['jumlah_hari'],
                            $statusPeminjaman,
                            $pesananAktifData['nomor_hp'],
                            $keteranganAktif,
                            $totalHarga
                        );

                        if (!$stmtInsertAktif->execute()) {
                            error_log("Error insert ke peminjaman_aktif: " . $stmtInsertAktif->error);
                            throw new Exception("Gagal insert ke peminjaman_aktif: " . $stmtInsertAktif->error);
                        }

                        error_log("✅ Data berhasil diinsert ke peminjaman_aktif untuk pesanan {$idPesanan} dengan status 'Diterima Penyedia'");
                    }
                } 

            // Ambil data quantity dan id_produk dari pesanan yang baru saja diterima
            $getOrderDataQuery = "SELECT quantity, id_produk, nama_kostum FROM pesanan WHERE id_pesanan = ?";
            $stmtOrderData = $conn->prepare($getOrderDataQuery);
            $stmtOrderData->bind_param("i", $idPesanan);
            $stmtOrderData->execute();
            $orderResult = $stmtOrderData->get_result();
            $orderData = $orderResult->fetch_assoc();
            
            if ($orderData) {
                $quantityToReduce = (int)$orderData['quantity'];
                $productId = (int)$orderData['id_produk'];
                $kostumName = $orderData['nama_kostum'];
                
                // PERBAIKAN: Cek dulu apakah ada stok_items untuk produk ini
                $checkStokItemsQuery = "SELECT COUNT(*) as total_items FROM stok_items WHERE kostum_id = ?";
                $checkStmt = $conn->prepare($checkStokItemsQuery);
                $checkStmt->bind_param("i", $productId);
                $checkStmt->execute();
                $totalItems = $checkStmt->get_result()->fetch_assoc()['total_items'];
                
                if ($totalItems == 0) {
                    // Jika belum ada stok_items, buat berdasarkan stok di form_katalog
                    $getCurrentStokQuery = "SELECT stok FROM form_katalog WHERE id = ?";
                    $stokStmt = $conn->prepare($getCurrentStokQuery);
                    $stokStmt->bind_param("i", $productId);
                    $stokStmt->execute();
                    $currentStok = $stokStmt->get_result()->fetch_assoc()['stok'];
                    
                    // Buat stok_items berdasarkan stok yang ada
                    for ($i = 1; $i <= $currentStok; $i++) {
                        $stokId = str_pad($i, 3, '0', STR_PAD_LEFT);
                        $insertStmt = $conn->prepare("INSERT INTO stok_items (kostum_id, stok_id, status) VALUES (?, ?, 'available')");
                        $insertStmt->bind_param("is", $productId, $stokId);
                        $insertStmt->execute();
                    }
                }
                
                // Update stok_items: tandai sebagai rented (gunakan yang available first) sesuai quantity
                $rentStokQuery = "UPDATE stok_items 
                                SET status = 'rented' 
                                WHERE kostum_id = ? 
                                AND status = 'available' 
                                ORDER BY CAST(stok_id AS UNSIGNED) ASC 
                                LIMIT ?";
                $stmtRentStok = $conn->prepare($rentStokQuery);
                $stmtRentStok->bind_param("ii", $productId, $quantityToReduce);
                $stmtRentStok->execute();
                
                $rentedCount = $stmtRentStok->affected_rows;
                error_log("✅ Updated {$rentedCount} stock items from available to 'rented' status for product {$productId}");
                
                // Hitung available stok untuk form_katalog
                $countAvailableQuery = "SELECT COUNT(*) as available_count FROM stok_items WHERE kostum_id = ? AND status = 'available'";
                $stmtCount = $conn->prepare($countAvailableQuery);
                $stmtCount->bind_param("i", $productId);
                $stmtCount->execute();
                $countResult = $stmtCount->get_result();
                $availableCount = $countResult->fetch_assoc()['available_count'];
                
                // Update stok di form_katalog dengan jumlah yang available
                $updateStokQuery = "UPDATE form_katalog SET stok = ? WHERE id = ?";
                $stmtUpdateStok = $conn->prepare($updateStokQuery);
                $stmtUpdateStok->bind_param("ii", $availableCount, $productId);
                
                if ($stmtUpdateStok->execute()) {
                    error_log("✅ Stok berhasil dikurangi untuk kostum '{$kostumName}' (ID: {$productId}). Available: {$availableCount}");
                } else {
                    error_log("❌ Gagal mengupdate stok untuk produk {$productId}: " . $stmtUpdateStok->error);
                }
            }

            error_log("🎯 Menambahkan notifikasi untuk penyewa");

            // Pastikan tabel notifications_penyewa ada (sesuai dengan file mark_notification_read.php)
            $createNotificationsTable = "CREATE TABLE IF NOT EXISTS `notifications_penyewa` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `id_user` int(11) NOT NULL,
                `title` varchar(255) NOT NULL,
                `message` text NOT NULL,
                `type` varchar(50) NOT NULL DEFAULT 'info',
                `is_read` tinyint(1) NOT NULL DEFAULT 0,
                `id_transaksi` varchar(10) DEFAULT NULL,
                `id_pesanan` int(11) DEFAULT NULL,
                `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `id_user` (`id_user`),
                KEY `is_read` (`is_read`),
                KEY `id_transaksi` (`id_transaksi`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

            $conn->query($createNotificationsTable);

            // Ambil data customer dari transaksi
            $getCustomerQuery = "SELECT t.id_user as customer_id 
                                FROM transaksi t 
                                WHERE t.id_transaksi = ?";
            $stmtCustomer = $conn->prepare($getCustomerQuery);
            $stmtCustomer->bind_param("s", $pesanan['id_transaksi']);
            $stmtCustomer->execute();
            $customerResult = $stmtCustomer->get_result();
            $customerData = $customerResult->fetch_assoc();

            if ($customerData) {
                $customerId = $customerData['customer_id'];
                $notificationTitle = "Lakukan konfirmasi penerimaan kostum";
                $notificationMessage = "Pesanan sewa kostum {$pesanan['nama_kostum']} dengan id transaksi {$pesanan['id_transaksi']} telah diterima oleh penyedia.";
                
                // Insert notifikasi ke tabel notifications_penyewa
                $insertNotificationQuery = "INSERT INTO notifications_penyewa 
                                        (id_user, title, message, type, id_transaksi, id_pesanan) 
                                        VALUES (?, ?, ?, 'success', ?, ?)";
                $stmtNotification = $conn->prepare($insertNotificationQuery);
                $stmtNotification->bind_param("isssi", 
                    $customerId, 
                    $notificationTitle, 
                    $notificationMessage, 
                    $pesanan['id_transaksi'],
                    $idPesanan
                );
                
                if ($stmtNotification->execute()) {
                    error_log("✅ Notifikasi berhasil ditambahkan untuk customer ID: {$customerId}");
                } else {
                    error_log("❌ Gagal menambahkan notifikasi: " . $stmtNotification->error);
                }
            } else {
                error_log("❌ Data customer tidak ditemukan untuk transaksi: {$pesanan['id_transaksi']}");
            }

            // 2. LANGSUNG BOOKING TANGGAL UNTUK PESANAN INI
            error_log("🎯 Mulai proses booking tanggal untuk pesanan {$idPesanan}");
            
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

            // Ambil data booking untuk pesanan ini saja
            $getBookingDataQuery = "SELECT 
                                        p.id_produk, 
                                        p.tanggal_mulai, 
                                        p.tanggal_selesai, 
                                        p.jumlah_hari,
                                        p.nama_penyewa,
                                        ti.tanggal_peminjaman
                                     FROM pesanan p
                                     LEFT JOIN transaksi_items ti ON p.id_transaksi = ti.id_transaksi 
                                                                  AND p.id_produk = ti.id_produk
                                     WHERE p.id_pesanan = ?";
            
            $stmtBookingData = $conn->prepare($getBookingDataQuery);
            $stmtBookingData->bind_param("i", $idPesanan);
            $stmtBookingData->execute();
            $bookingResult = $stmtBookingData->get_result();
            $bookingRow = $bookingResult->fetch_assoc();
            
            if ($bookingRow) {
                $productId = $bookingRow['id_produk'];
                $bookedBy = $bookingRow['nama_penyewa'];
                $tanggalPeminjaman = $bookingRow['tanggal_peminjaman'];
                
                // Debug: Log data yang diambil
                error_log("📋 Debug booking data untuk pesanan {$idPesanan}:");
                error_log("- Product ID: {$productId}");
                error_log("- Booked by: {$bookedBy}");
                error_log("- Tanggal peminjaman dari DB: " . var_export($tanggalPeminjaman, true));
                
                // Proses tanggal peminjaman
                $datesToBook = [];
                
                if (!empty($tanggalPeminjaman)) {
                    // Parse tanggal dari string comma-separated
                    $tanggalArray = array_filter(explode(',', $tanggalPeminjaman), function($date) {
                        return !empty(trim($date));
                    });
                    
                    foreach ($tanggalArray as $tanggal) {
                        $tanggal = trim($tanggal);
                        if (!empty($tanggal)) {
                            // Coba berbagai format tanggal
                            $dateToBook = null;
                            
                            // Format Y-m-d
                            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
                                $dateToBook = $tanggal;
                            }
                            // Format d/m/Y
                            elseif (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $tanggal)) {
                                $dateToBook = date('Y-m-d', strtotime(str_replace('/', '-', $tanggal)));
                            }
                            // Format lainnya
                            else {
                                $timestamp = strtotime($tanggal);
                                if ($timestamp !== false) {
                                    $dateToBook = date('Y-m-d', $timestamp);
                                }
                            }
                            
                            if ($dateToBook && $dateToBook !== '1970-01-01') {
                                $datesToBook[] = $dateToBook;
                            }
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
                $successCount = 0;
                foreach ($datesToBook as $dateToBook) {
                    $insertBookingQuery = "INSERT INTO unavailable_dates (product_id, date, booked_by) 
                                          VALUES (?, ?, ?) 
                                          ON DUPLICATE KEY UPDATE 
                                          booked_by = VALUES(booked_by)";
                    
                    $stmtInsertBooking = $conn->prepare($insertBookingQuery);
                    $stmtInsertBooking->bind_param("iss", $productId, $dateToBook, $bookedBy);
                    
                    if ($stmtInsertBooking->execute()) {
                        $successCount++;
                        error_log("✅ Tanggal {$dateToBook} berhasil ditandai sebagai booked untuk produk {$productId} oleh {$bookedBy}");
                    } else {
                        error_log("❌ Gagal menandai tanggal {$dateToBook} sebagai booked untuk produk {$productId}: " . $stmtInsertBooking->error);
                    }
                }
                
                if ($successCount > 0) {
                    error_log("📅 Berhasil menandai {$successCount} tanggal sebagai booked untuk produk {$productId}");
                } else {
                    error_log("⚠️ Tidak ada tanggal yang berhasil di-booking untuk produk {$productId}");
                }
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
                    $stmtNotif->bind_param("siissss", 
                        $pesanan['id_transaksi'], 
                        $idPesanan, 
                        $pesanan['id_penyedia'],
                        $providerName,
                        $idPesanan,
                        $productName,
                        $pesanan['id_transaksi']
                    );
                                        
                    if ($stmtNotif->execute()) {
                        error_log("✅ Notifikasi acceptance berhasil dikirim untuk transaksi {$pesanan['id_transaksi']}");
                    } else {
                        error_log("❌ Gagal mengirim notifikasi acceptance: " . $stmtNotif->error);
                    }
                }
                error_log("Semua penyedia telah menerima pesanan untuk transaksi {$pesanan['id_transaksi']}");

            }
            

            // $checkPeminjamanQuery = "SELECT COUNT(*) as jumlah FROM peminjaman WHERE id_pesanan = ?";
            // $stmt = $conn->prepare($checkPeminjamanQuery);
            // $stmt->bind_param("i", $idPesanan);
            // $stmt->execute();
            // $result = $stmt->get_result();
            // $peminjamanCount = $result->fetch_assoc()['jumlah'];
            
            // if ($peminjamanCount == 0) {
            //     $id_pesanan = (int)$pesanan['id_pesanan'];
            //     $id_transaksi = !empty($pesanan['id_transaksi']) ? (string)$pesanan['id_transaksi'] : '0';
            //     $id_penyewa = !empty($pesanan['id_user']) ? (int)$pesanan['id_user'] : 0;
            //     $id_penyedia = !empty($pesanan['id_penyedia']) ? (int)$pesanan['id_penyedia'] : 0;
            //     $id_produk = !empty($pesanan['id_produk']) ? (int)$pesanan['id_produk'] : 0;                $nama_penyewa = !empty($pesanan['nama_penyewa']) ? mysqli_real_escape_string($conn, $pesanan['nama_penyewa']) : 'Unknown';
            //     $nomor_hp = !empty($pesanan['nomor_hp']) ? (string)$pesanan['nomor_hp'] : '';
            //     $nama_penyewa = !empty($pesanan['nama_penyewa']) ? (string)$pesanan['nama_penyewa'] : 'Unknown';
            //     $nama_kostum = !empty($pesanan['nama_kostum']) ? (string)$pesanan['nama_kostum'] : 'Unknown';
            //     $size = isset($pesanan['size']) ? (string)$pesanan['size'] : '';
            //     $quantity = !empty($pesanan['quantity']) ? (int)$pesanan['quantity'] : 1;
            //     $tanggal_mulai = !empty($pesanan['tanggal_mulai']) ? (string)$pesanan['tanggal_mulai'] : date('Y-m-d');
            //     $tanggal_selesai = !empty($pesanan['tanggal_selesai']) ? (string)$pesanan['tanggal_selesai'] : date('Y-m-d', strtotime('+1 day'));
            //     $jumlah_hari = !empty($pesanan['jumlah_hari']) ? (int)$pesanan['jumlah_hari'] : 1;
            //     $status_peminjaman = 'sedang_berjalan';
                
            //     // Debug: Log data yang akan di-insert
            //     $debugData = [
            //         'id_pesanan' => $id_pesanan,
            //         'id_transaksi' => $id_transaksi,
            //         'id_penyewa' => $id_penyewa,
            //         'id_penyedia' => $id_penyedia,
            //         'id_produk' => $id_produk,
            //         'nama_penyewa' => $nama_penyewa,
            //         'nomor_hp' => $nomor_hp,
            //         'nama_kostum' => $nama_kostum,
            //         'size' => $size,
            //         'quantity' => $quantity,
            //         'tanggal_mulai' => $tanggal_mulai,
            //         'tanggal_selesai' => $tanggal_selesai,
            //         'jumlah_hari' => $jumlah_hari,
            //         'status_peminjaman' => $status_peminjaman
            //     ];
            //     error_log("Data yang akan di-insert: " . json_encode($debugData));
                
            //     // Validasi data yang wajib ada
            //     if (empty($nama_penyewa) || $nama_penyewa === 'Unknown') {
            //         throw new Exception("Nama penyewa kosong atau tidak valid");
            //     }
            //     if (empty($nama_kostum) || $nama_kostum === 'Unknown') {
            //         throw new Exception("Nama kostum kosong atau tidak valid");
            //     }
                
            //     // 3. Insert ke tabel peminjaman dengan query yang lebih sederhana
            //     $insertPeminjamanQuery = "INSERT INTO peminjaman (
            //         id_pesanan, id_transaksi, id_penyewa, id_penyedia, id_produk,
            //         nama_penyewa, nomor_hp, nama_kostum, size, quantity,
            //         tanggal_mulai, tanggal_selesai, jumlah_hari, status_peminjaman
            //     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
            //     $stmt = $conn->prepare($insertPeminjamanQuery);
                
            //     if (!$stmt) {
            //         throw new Exception("Prepare statement gagal: " . $conn->error);
            //     }                error_log("Binding parameters...");
            //     $bindResult = $stmt->bind_param("isiisssssiisis",  
            //         $id_pesanan,        // i - integer
            //         $id_transaksi,      // s - string
            //         $id_penyewa,        // i - integer
            //         $id_penyedia,       // i - integer
            //         $id_produk,         // i - integer
            //         $nama_penyewa,      // s - string
            //         $nomor_hp,          // s - string
            //         $nama_kostum,       // s - string
            //         $size,              // s - string
            //         $quantity,          // i - integer (PERBAIKAN: ubah kembali ke integer)
            //         $tanggal_mulai,     // s - string
            //         $tanggal_selesai,   // s - string
            //         $jumlah_hari,       // i - integer
            //         $status_peminjaman  // s - string
            //     );
                
            //     if (!$bindResult) {
            //         throw new Exception("Gagal bind parameter: " . $stmt->error);
            //     }
                
            //     error_log("Parameters bound successfully, executing...");
            //     if (!$stmt->execute()) {
            //         throw new Exception("Gagal insert ke peminjaman: " . $stmt->error);
            //     }
                
            //     error_log("Pesanan {$idPesanan} berhasil diterima dan dipindahkan ke peminjaman");
            // }

    

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
                `id_produk` int(11) NOT NULL,
                `reason` text NOT NULL,
                `tanggal_penolakan` timestamp DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_rejection` (`id_transaksi`, `id_pesanan`, `id_produk`, `id_penyedia`),
                KEY `id_transaksi` (`id_transaksi`),
                KEY `id_pesanan` (`id_pesanan`),
                KEY `id_penyedia` (`id_penyedia`),
                KEY `id_produk` (`id_produk`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            
            $conn->query($createRejectionTable);

            $createRefundTable = "CREATE TABLE IF NOT EXISTS `refunds` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `id_transaksi` varchar(10) NOT NULL,
                `id_pesanan` int(11) DEFAULT NULL,
                `id_produk` int(11) DEFAULT NULL,
                `id_penyedia` int(11) DEFAULT NULL,
                `bukti_refund_path` varchar(255) DEFAULT '',
                `catatan_refund` text,
                `tanggal_refund` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `status_refund` enum('processed','completed') DEFAULT 'processed',
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_refund_item` (`id_transaksi`,`id_pesanan`,`id_produk`),
                KEY `id_transaksi` (`id_transaksi`),
                KEY `idx_produk` (`id_produk`),
                KEY `idx_penyedia` (`id_penyedia`),
                KEY `idx_pesanan` (`id_pesanan`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

            $conn->query($createRefundTable);

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
                                        fk.judul_post as product_name,
                                        fk.harga_sewa,
                                        t.nama_lengkap as customer_name,
                                        t.id_user as customer_id
                                        FROM akun_toko at 
                                        INNER JOIN form_katalog fk ON at.id = fk.id_user
                                        INNER JOIN transaksi t ON t.id_transaksi = ?
                                        WHERE at.id = ? AND fk.id = ?";
            $stmtProviderProduct = $conn->prepare($getProviderProductQuery);
            $stmtProviderProduct->bind_param("sii", $pesanan['id_transaksi'], $pesanan['id_penyedia'], $pesanan['id_produk']);
            $stmtProviderProduct->execute();
            $providerProductResult = $stmtProviderProduct->get_result();
            $providerProductData = $providerProductResult->fetch_assoc();

            if (!$providerProductData) {
                throw new Exception("Data penyedia atau produk tidak ditemukan");
            }
                    
            $providerName = $providerProductData ? $providerProductData['provider_name'] : 'Unknown Provider';
            $productName = $providerProductData ? $providerProductData['product_name'] : 'Unknown Product';
            $productPrice = $providerProductData['harga_sewa'];
            $customerName = $providerProductData['customer_name'];
            $customerId = $providerProductData['customer_id'];

            // 4. Simpan alasan penolakan
            $insertRejectionQuery = "INSERT INTO rejection_reasons 
                                    (id_transaksi, id_pesanan, id_penyedia, id_produk, reason) 
                                    VALUES (?, ?, ?, ?, ?)";
            $stmtRejection = $conn->prepare($insertRejectionQuery);
            $stmtRejection->bind_param("siiis", 
                $pesanan['id_transaksi'], 
                $idPesanan, 
                $pesanan['id_penyedia'], 
                $pesanan['id_produk'],
                $reason
            );
            
            if (!$stmtRejection->execute()) {
                throw new Exception("Gagal menyimpan alasan penolakan: " . $stmtRejection->error);
            }

            $catatan_refund = "Pesanan ditolak oleh penyedia: {$providerName}. Produk: {$productName}. Alasan: {$reason}";

            $insertRefundQuery = "INSERT INTO refunds (id_transaksi, id_pesanan, id_produk, id_penyedia, bukti_refund_path, catatan_refund, status_refund, tanggal_refund) 
                                VALUES (?, ?, ?, ?, '', ?, 'processed', NOW())";
            $stmtRefund = $conn->prepare($insertRefundQuery);
            $stmtRefund->bind_param("siiis", 
                $pesanan['id_transaksi'],
                $idPesanan,
                $pesanan['id_produk'], 
                $pesanan['id_penyedia'],
                $catatan_refund
            );

            if (!$stmtRefund->execute()) {
                throw new Exception("Gagal membuat data refund: " . $stmtRefund->error);
            }

            error_log("✅ Data refund berhasil dibuat untuk pesanan {$idPesanan}, produk {$pesanan['id_produk']}");

            $updatePesananStatusQuery = "UPDATE pesanan SET status = 'rejected', updated_at = CURRENT_TIMESTAMP WHERE id_pesanan = ?";
            $stmtUpdatePesanan = $conn->prepare($updatePesananStatusQuery);
            $stmtUpdatePesanan->bind_param("i", $idPesanan);
            
            if (!$stmtUpdatePesanan->execute()) {
                throw new Exception("Gagal update status pesanan: " . $stmtUpdatePesanan->error);
            }

            $moveToRiwayatQuery = "INSERT INTO riwayat_pesanan (
                id_pesanan_asli, 
                id_transaksi, 
                id_user, 
                id_produk, 
                id_penyedia, 
                nama_penyewa, 
                nomor_hp, 
                nama_kostum, 
                size, 
                quantity, 
                tanggal_pinjam, 
                jumlah_hari, 
                tanggal_mulai, 
                tanggal_selesai, 
                tanggal_pengembalian,
                status, 
                created_at
            ) 
            SELECT 
                p.id_pesanan,
                p.id_transaksi,
                p.id_user,
                p.id_produk,
                p.id_penyedia,
                p.nama_penyewa,
                p.nomor_hp,
                p.nama_kostum,
                p.size,
                p.quantity,
                p.tanggal_pinjam,
                p.jumlah_hari,
                p.tanggal_mulai,
                p.tanggal_selesai,
                NOW() as tanggal_pengembalian,
                'ditolak' as status,
                p.created_at
            FROM pesanan p 
            WHERE p.id_pesanan = ? AND p.status = 'rejected'";
            
            $stmtMoveToRiwayat = $conn->prepare($moveToRiwayatQuery);
            if (!$stmtMoveToRiwayat) {
                throw new Exception("Error preparing move to riwayat query: " . $conn->error);
            }
            
            $stmtMoveToRiwayat->bind_param("i", $idPesanan);  // PERBAIKAN: gunakan $idPesanan bukan $id_pesanan
            if (!$stmtMoveToRiwayat->execute()) {
                throw new Exception("Gagal memindahkan data ke riwayat pesanan: " . $stmtMoveToRiwayat->error);
            }
            
            // Hapus data dari tabel pesanan setelah berhasil dipindahkan
            $deletePesananQuery = "DELETE FROM pesanan WHERE id_pesanan = ? AND status = 'rejected'";
            $stmtDeletePesanan = $conn->prepare($deletePesananQuery);
            if (!$stmtDeletePesanan) {
                throw new Exception("Error preparing delete pesanan query: " . $conn->error);
            }
            
            $stmtDeletePesanan->bind_param("i", $idPesanan);  // PERBAIKAN: gunakan $idPesanan bukan $id_pesanan
            if (!$stmtDeletePesanan->execute()) {
                throw new Exception("Gagal menghapus data pesanan yang ditolak: " . $stmtDeletePesanan->error);
            }

            error_log("Pesanan ID $idPesanan berhasil dipindahkan ke riwayat dengan status ditolak");

            $checkAllItemsStatusQuery = "SELECT 
                                        COUNT(*) as total_items,
                                        SUM(CASE WHEN p.status IN ('accepted', 'rejected') THEN 1 ELSE 0 END) as processed_items,
                                        SUM(CASE WHEN p.status = 'rejected' THEN 1 ELSE 0 END) as rejected_items,
                                        SUM(CASE WHEN p.status = 'accepted' THEN 1 ELSE 0 END) as accepted_items
                                        FROM pesanan p 
                                        WHERE p.id_transaksi = ?";
            $stmtCheckAll = $conn->prepare($checkAllItemsStatusQuery);
            $stmtCheckAll->bind_param("s", $pesanan['id_transaksi']);
            $stmtCheckAll->execute();
            $allItemsResult = $stmtCheckAll->get_result()->fetch_assoc();
            
            $totalItems = $allItemsResult['total_items'];
            $processedItems = $allItemsResult['processed_items'];
            $rejectedItems = $allItemsResult['rejected_items'];
            $acceptedItems = $allItemsResult['accepted_items'];
            
            // Update status transaksi berdasarkan kondisi semua item
            if ($totalItems > 0 && $processedItems == $totalItems) {
                if ($rejectedItems == $totalItems) {
                    // Semua item ditolak
                    $newTransaksiStatus = 'rejected';
                } else if ($rejectedItems > 0 && $acceptedItems > 0) {
                    // Sebagian item diterima, sebagian ditolak
                    $newTransaksiStatus = 'partially_rejected';
                } else if ($acceptedItems == $totalItems) {
                    // Semua item diterima
                    $newTransaksiStatus = 'validated';
                } else {
                    // Status lainnya
                    $newTransaksiStatus = 'provider_rejected';
                }

                $updateTransaksiQuery = "UPDATE transaksi SET status_validasi = ? WHERE id_transaksi = ?";
                $stmtUpdateTransaksi = $conn->prepare($updateTransaksiQuery);
                $stmtUpdateTransaksi->bind_param("ss", $newTransaksiStatus, $pesanan['id_transaksi']);
                $stmtUpdateTransaksi->execute();
                
                error_log("✅ Updated transaction status to {$newTransaksiStatus} for transaction {$pesanan['id_transaksi']} (Total: {$totalItems}, Rejected: {$rejectedItems}, Accepted: {$acceptedItems})");
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
                'message' => 'Pesanan berhasil ditolak dan data refund telah dibuat',
                'data' => [
                    'id_pesanan' => $idPesanan,
                    'id_transaksi' => $pesanan['id_transaksi'],
                    'id_produk' => $pesanan['id_produk'],
                    'reason' => $reason,
                    'refund_created' => true,
                    'transaction_status' => $newTransaksiStatus ?? 'partial_processing'
                ]
            ]);
            
        } elseif ($action === 'pesanan_diterima') {
            try {
                $conn->begin_transaction();

                if (!isset($_FILES['bukti_diterima']) || $_FILES['bukti_diterima']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception("Bukti foto harus diupload");
                }
                
                $buktiFile = $_FILES['bukti_diterima'];
                $catatan = isset($_POST['catatan_konfirmasi']) ? trim($_POST['catatan_konfirmasi']) : '';
                
                // Validasi file
                $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                $fileType = mime_content_type($buktiFile['tmp_name']);
                
                if (!in_array($fileType, $allowedTypes)) {
                    throw new Exception("Format file tidak didukung. Gunakan JPG, PNG, atau GIF");
                }
                
                if ($buktiFile['size'] > 5 * 1024 * 1024) { // 5MB
                    throw new Exception("Ukuran file terlalu besar. Maksimal 5MB");
                }
                
                // Buat folder upload jika belum ada
                $uploadDir = 'uploads/bukti_diterima/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                // Generate nama file unik
                $fileName = 'bukti_diterima_' . $idPesanan . '_' . time() . '.' . $fileExtension;
                $filePath = $uploadDir . $fileName;
                
                // Upload file
                if (!move_uploaded_file($buktiFile['tmp_name'], $filePath)) {
                    throw new Exception("Gagal mengupload file bukti");
                }

                $buktiForDB = $fileName;

                // 1. Update status pesanan
                $updatePesananQuery = "UPDATE pesanan SET status = 'pesanan_diterima_penyewa', bukti_diterima_path = ?, updated_at = CURRENT_TIMESTAMP WHERE id_pesanan = ?";
                $stmt = $conn->prepare($updatePesananQuery);
                $stmt->bind_param("si", $filePath, $idPesanan);
                
                if (!$stmt->execute()) {
                    // Hapus file jika update database gagal
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                    throw new Exception("Gagal update status pesanan: " . $stmt->error);
                }

                // 2. UPDATE data yang sudah ada di peminjaman_aktif (bukan insert baru)
                $updatePeminjamanAktifQuery = "UPDATE peminjaman_aktif 
                                            SET status_peminjaman = 'Konfirmasi Diterima', 
                                                bukti = ?,
                                                keterangan = CONCAT(COALESCE(keterangan, ''), ' | Konfirmasi diterima: ', ?),
                                                updated_at = CURRENT_TIMESTAMP
                                            WHERE id_pesanan = ?";
                $stmtUpdatePeminjamanAktif = $conn->prepare($updatePeminjamanAktifQuery);
                
                $keteranganUpdate = !empty($catatan) ? $catatan : 'Penyedia mengkonfirmasi kostum telah diterima penyewa';
                $stmtUpdatePeminjamanAktif->bind_param("ssi", $buktiForDB, $keteranganUpdate, $idPesanan);
                
                if (!$stmtUpdatePeminjamanAktif->execute()) {
                    throw new Exception("Gagal mengupdate peminjaman_aktif untuk pesanan {$idPesanan}: " . $stmtUpdatePeminjamanAktif->error);
                } else {
                    error_log("✅ Berhasil update peminjaman_aktif untuk pesanan {$idPesanan} dengan status 'Konfirmasi Diterima'");
                }

                $getPesananDetails = $conn->prepare("
                    SELECT p.*, 
                        t.id_user as customer_id,
                        u1.nama_lengkap as customer_name,
                        u2.nama_lengkap as provider_name
                    FROM pesanan p
                    JOIN transaksi_aplikasi t ON p.id_transaksi = t.id_transaksi
                    JOIN user_accounts u1 ON t.id_user = u1.id
                    JOIN user_accounts u2 ON p.id_penyedia = u2.id
                    WHERE p.id_pesanan = ?
                ");
                $getPesananDetails->bind_param("i", $idPesanan);
                $getPesananDetails->execute();
                $resultDetails = $getPesananDetails->get_result();
                $pesananDetails = $resultDetails->fetch_assoc();

                if ($pesananDetails) {
                    // Kirim notifikasi ke admin tentang konfirmasi
                    $notificationType = 'provider_confirmed_received';
                    $title = 'Penyedia Konfirmasi Pesanan Diterima';
                    $message = "Penyedia '{$pesananDetails['provider_name']}' telah mengkonfirmasi bahwa kostum '{$pesananDetails['nama_kostum']}' telah diterima oleh penyewa '{$pesananDetails['customer_name']}' untuk transaksi {$pesananDetails['id_transaksi']}.";
                    
                    if (!empty($catatan)) {
                        $message .= " Catatan: {$catatan}";
                    }

                    $insertAdminNotification = $conn->prepare("
                        INSERT INTO notifications_admin (id_transaksi, id_pesanan, type, title, message, is_read) 
                        VALUES (?, ?, ?, ?, ?, 0)
                    ");
                    $insertAdminNotification->bind_param(
                        "sisss", 
                        $pesananDetails['id_transaksi'],
                        $idPesanan,
                        $notificationType,
                        $title,
                        $message
                    );

                    if (!$insertAdminNotification->execute()) {
                        error_log("Gagal mengirim notifikasi admin: " . $insertAdminNotification->error);
                    }
                } else {
                    error_log("Pesanan details tidak ditemukan untuk id_pesanan: {$idPesanan}");
                }

                // 2. Simpan konfirmasi provider
                $insertProviderConfirmation = "INSERT INTO provider_confirmations (id_pesanan, id_transaksi, id_penyedia) 
                                            VALUES (?, ?, ?)                                                                                                       
                                            ON DUPLICATE KEY UPDATE confirmed_at = CURRENT_TIMESTAMP";
                $stmtConfirm = $conn->prepare($insertProviderConfirmation);
                $stmtConfirm->bind_param("isi", $idPesanan, $pesanan['id_transaksi'], $pesanan['id_penyedia']);
                
                if (!$stmtConfirm->execute()) {
                    throw new Exception("Gagal menyimpan konfirmasi provider: " . $stmtConfirm->error);
                }

                $createAdminNotifTable = "CREATE TABLE IF NOT EXISTS `notifications_admin` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `id_transaksi` varchar(10) DEFAULT NULL,
                    `id_pesanan` int(11) DEFAULT NULL,
                    `id_user` int(11) DEFAULT NULL,
                    `type` varchar(50) NOT NULL,
                    `title` varchar(255) NOT NULL,
                    `message` text NOT NULL,
                    `icon` varchar(100) DEFAULT 'mdi-check-circle',
                    `class` varchar(50) DEFAULT 'success',
                    `is_read` tinyint(1) DEFAULT 0,
                    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `id_transaksi` (`id_transaksi`),
                    KEY `id_pesanan` (`id_pesanan`),
                    KEY `id_user` (`id_user`),
                    KEY `is_read` (`is_read`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

                $conn->query($createAdminNotifTable);

                // 4. Get provider name for notification
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
                
                if (!$providerProductResult) {
                    throw new Exception("Gagal mendapatkan data provider dan produk: " . $conn->error);
                }
                
                $providerProductData = $providerProductResult->fetch_assoc();
                $providerName = $providerProductData['provider_name'] ?? 'Unknown Provider';
                $productName = $providerProductData['product_name'] ?? 'Unknown Product';

                // 5. Insert notification for admin
                $insertNotifQuery = "INSERT INTO notifications_admin 
                    (id_transaksi, id_pesanan, id_user, type, title, message, icon, class, is_read) 
                    VALUES (?, ?, ?, 'provider_confirmed_received', 'Penyedia Konfirmasi Pesanan Diterima', 
                            CONCAT('Penyedia ', ?, ' telah mengkonfirmasi bahwa pesanan #', ?, ' (', ?, ') telah diterima penyewa'), 
                            'mdi-check-circle', 'success', 0)";
                $stmtNotif = $conn->prepare($insertNotifQuery);
                $stmtNotif->bind_param("siissss", 
                    $pesanan['id_transaksi'], 
                    $idPesanan, 
                    $pesanan['id_penyedia'],
                    $providerName,
                    $idPesanan,
                    $productName,
                    $pesanan['id_transaksi']
                );

                // Setelah execute notifikasi
                if (!$stmtNotif->execute()) {
                    throw new Exception("Gagal mengirim notifikasi ke admin: " . $stmtNotif->error);
                }

                $conn->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Status berhasil diupdate: Pesanan telah diterima oleh penyewa',
                    'id_pesanan' => $idPesanan,
                    'new_status' => 'pesanan_diterima_penyewa',
                    'bukti_path' => $filePath
                    
                ]);
                exit;

            } catch (Exception $e) {
                $conn->rollback();
                error_log("Error in pesanan_diterima: " . $e->getMessage());
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Terjadi kesalahan: ' . $e->getMessage()
                ]);
                exit; 
            }
        
        } elseif ($action === 'kostum_kembali') {
            $idPesanan = (int)$input['id_pesanan'];
    
            // Validasi input
            if (empty($idPesanan)) {
                throw new Exception("ID Pesanan tidak boleh kosong");
            }
            
            // Cek pesanan ada dan statusnya valid
            $checkQuery = "SELECT p.*, t.id_user as id_penyewa, t.nama_lengkap
                        FROM pesanan p 
                        JOIN transaksi t ON p.id_transaksi = t.id_transaksi
                        WHERE p.id_pesanan = ?";
            $stmtCheck = $conn->prepare($checkQuery);
            $stmtCheck->bind_param("i", $idPesanan);
            $stmtCheck->execute();
            $pesanan = $stmtCheck->get_result()->fetch_assoc();
            
            if (!$pesanan) {
                throw new Exception("Pesanan tidak ditemukan");
            }
            
            // Pastikan status pesanan sesuai untuk konfirmasi pengembalian
            if ($pesanan['status'] !== 'pesanan_diterima_penyewa') {
                throw new Exception("Status pesanan tidak valid untuk konfirmasi pengembalian kostum");
            }
            
            // Update status pesanan menjadi 'costume_returned'
            $updatePesananQuery = "UPDATE pesanan 
                                SET status = 'costume_returned', 
                                    updated_at = CURRENT_TIMESTAMP 
                                WHERE id_pesanan = ?";
            $stmtUpdatePesanan = $conn->prepare($updatePesananQuery);
            $stmtUpdatePesanan->bind_param("i", $idPesanan);
            
            if (!$stmtUpdatePesanan->execute()) {
                throw new Exception("Gagal mengupdate status pesanan: " . $stmtUpdatePesanan->error);
            }
            
            $checkCustomerConfirmationQuery = "SELECT COUNT(*) as customer_confirmed 
                                             FROM customer_return_confirmations crc
                                             JOIN transaksi_items ti ON crc.id_transaksi = ti.id_transaksi AND crc.id_item = ti.id_item
                                             WHERE crc.id_transaksi = ? AND ti.id_produk = ?";
            $stmtCheckCustomer = $conn->prepare($checkCustomerConfirmationQuery);
            $stmtCheckCustomer->bind_param("si", $pesanan['id_transaksi'], $pesanan['id_produk']);
            $stmtCheckCustomer->execute();
            $customerResult = $stmtCheckCustomer->get_result();
            $customerConfirmed = $customerResult->fetch_assoc()['customer_confirmed'] > 0;
            
            if ($customerConfirmed) {
                // Jika customer sudah konfirmasi, set ke both_confirmed_returned
                $newStatus = 'both_confirmed_returned';
            } else {
                // Jika customer belum konfirmasi, set ke provider_confirmed_returned (status baru)
                $newStatus = 'provider_confirmed_returned';
            }

            // Update status di transaksi_items juga (jika diperlukan untuk consistency)
            $updateTransaksiItemsQuery = "UPDATE transaksi_items 
                                        SET status_item = ?,
                                            updated_at = CURRENT_TIMESTAMP
                                        WHERE id_transaksi = ? 
                                        AND id_produk = ?";
            $stmtUpdateTransaksiItems = $conn->prepare($updateTransaksiItemsQuery);
            $stmtUpdateTransaksiItems->bind_param("ssi", $newStatus, $pesanan['id_transaksi'], $pesanan['id_produk']);
            
            if (!$stmtUpdateTransaksiItems->execute()) {
                throw new Exception("Gagal mengupdate status transaksi_items: " . $stmtUpdateTransaksiItems->error);
            }

            error_log("🔄 Mulai proses pengembalian stok untuk pesanan yang dikembalikan {$idPesanan}");

            $getQuantityQuery = "SELECT quantity, id_produk FROM pesanan WHERE id_pesanan = ?";
            $stmtQuantity = $conn->prepare($getQuantityQuery);
            $stmtQuantity->bind_param("i", $idPesanan);
            $stmtQuantity->execute();
            $quantityResult = $stmtQuantity->get_result();
            $quantityData = $quantityResult->fetch_assoc();
            
            if ($quantityData) {
                $quantityToReturn = (int)$quantityData['quantity'];
                $idProduk = (int)$quantityData['id_produk'];
                
                // Kembalikan stock items dari status 'rented' ke 'available'
                $returnStokItemsQuery = "UPDATE stok_items 
                                        SET status = 'maintenance' 
                                        WHERE kostum_id = ? 
                                        AND status = 'rented' 
                                        LIMIT ?";
                $stmtReturnUpdate = $conn->prepare($returnStokItemsQuery);
                $stmtReturnUpdate->bind_param("ii", $idProduk, $quantityToReturn);
                
                if ($stmtReturnUpdate->execute()) {
                    $returnedCount = $stmtReturnUpdate->affected_rows;
                    error_log("✅ Mengubah {$returnedCount} stock items dari 'rented' ke 'maintenance' status untuk product {$idProduk}");
                    
                    // TIDAK UPDATE STOK DI form_katalog saat dikembalikan
                    // Stok akan bertambah otomatis saat penyedia menandai "siap sewa" di INDEXXX3
                    
                } else {
                    error_log("❌ Gagal mengubah status stock items: " . $stmtReturnUpdate->error);
                }
            } else {
                error_log("❌ Gagal mengambil data quantity untuk pesanan {$idPesanan}");
            }

            // Cek apakah customer sudah konfirmasi return juga untuk menentukan status final
            $checkCustomerReturnQuery = "SELECT COUNT(*) as customer_confirmed 
                                        FROM customer_return_confirmations crc
                                        INNER JOIN transaksi_items ti ON crc.id_transaksi = ti.id_transaksi AND crc.id_item = ti.id_item
                                        WHERE crc.id_transaksi = ? 
                                        AND ti.id_produk = ?";
            $stmtCustomerCheck = $conn->prepare($checkCustomerReturnQuery);
            $stmtCustomerCheck->bind_param("si", $pesanan['id_transaksi'], $pesanan['id_produk']);
            $stmtCustomerCheck->execute();
            $customerResult = $stmtCustomerCheck->get_result();
            $customerConfirmed = $customerResult->fetch_assoc()['customer_confirmed'] > 0;

            if ($customerConfirmed) {
                // Update status final ke both_confirmed_returned jika kedua pihak sudah konfirmasi
                $updateFinalStatusQuery = "UPDATE transaksi_items 
                                        SET status_item = 'both_confirmed_returned'
                                        WHERE id_transaksi = ? AND id_produk = ?";
                $stmtFinalUpdate = $conn->prepare($updateFinalStatusQuery);
                $stmtFinalUpdate->bind_param("si", $pesanan['id_transaksi'], $pesanan['id_produk']);
                $stmtFinalUpdate->execute();

                $message = 'Kostum telah dikonfirmasi dikembalikan oleh kedua pihak dan stok telah dikembalikan';
            } else {
                $message = 'Penyedia telah mengkonfirmasi kostum dikembalikan dan stok telah dikembalikan. Menunggu konfirmasi dari penyewa.';
            }

            $insertPeminjamanQuery = "INSERT INTO peminjaman (
                id_pesanan, 
                id_transaksi, 
                id_penyewa, 
                id_penyedia, 
                id_produk,
                nama_penyewa, 
                nomor_hp, 
                nama_kostum, 
                size, 
                quantity, 
                tanggal_mulai, 
                tanggal_selesai, 
                jumlah_hari, 
                status_peminjaman,
                tanggal_diterima
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";
            
            $stmtInsertPeminjaman = $conn->prepare($insertPeminjamanQuery);
            
            // Tentukan nama penyewa yang akan digunakan
            $namaPenyewa = !empty($pesanan['nama_lengkap']) ? $pesanan['nama_lengkap'] : $pesanan['nama_penyewa'];
            $statusPeminjaman = 'kostum_dikembalikan'; // Status khusus untuk kostum yang sudah dikembalikan
            
            $stmtInsertPeminjaman->bind_param(
                "isiiissssiissi",
                $pesanan['id_pesanan'],
                $pesanan['id_transaksi'],
                $pesanan['id_penyewa'],
                $pesanan['id_penyedia'],
                $pesanan['id_produk'],
                $namaPenyewa,
                $pesanan['nomor_hp'],
                $pesanan['nama_kostum'],
                $pesanan['size'],
                $pesanan['quantity'],
                $pesanan['tanggal_mulai'],
                $pesanan['tanggal_selesai'],
                $pesanan['jumlah_hari'],
                $statusPeminjaman
            );
            
            if (!$stmtInsertPeminjaman->execute()) {
                error_log("Gagal insert ke tabel peminjaman: " . $stmtInsertPeminjaman->error);
                // Tidak throw exception karena ini tidak kritis, pesanan sudah terupdate
            } else {
                error_log("✅ Data kostum yang dikembalikan berhasil ditambahkan ke tabel peminjaman untuk admin");
            }

            $getPesananDetails = $conn->prepare("
                SELECT 
                    p.id_transaksi, 
                    p.id_penyedia, 
                    p.id_produk,
                    t.nama_lengkap AS nama_penyewa,
                    at.nama AS nama_penyedia,
                    fc.judul_post AS nama_kostum
                FROM pesanan p
                JOIN transaksi t ON p.id_transaksi = t.id_transaksi
                JOIN akun_toko at ON p.id_penyedia = at.id
                LEFT JOIN form_katalog fc ON p.id_produk = fc.id
                WHERE p.id_pesanan = ?
            ");
            $getPesananDetails->bind_param("i", $idPesanan);
            $getPesananDetails->execute();
            $resultDetails = $getPesananDetails->get_result();
            $pesananDetails = $resultDetails->fetch_assoc();

            if (!$pesananDetails) {
                throw new Exception("Detail pesanan tidak ditemukan untuk ID: " . $idPesanan);
            }

            $idTransaksi = $pesananDetails['id_transaksi'];
            $idProduk = $pesananDetails['id_produk'];
            $idUserPenyedia = $pesananDetails['id_penyedia'];
            $namaPenyewa = $pesananDetails['nama_penyewa'];
            $namaPenyedia = $pesananDetails['nama_penyedia'];
            $namaKostum = $pesananDetails['nama_kostum'];

            $getPesananDataQuery = "SELECT 
                p.id_pesanan, p.id_transaksi, p.id_user, p.id_produk, p.id_penyedia,
                COALESCE(
                    NULLIF(pm.nama_penyewa, '0'), 
                    NULLIF(pm.nama_penyewa, ''), 
                    NULLIF(t.nama_lengkap, ''), 
                    NULLIF(p.nama_penyewa, '0'), 
                    NULLIF(p.nama_penyewa, ''), 
                    'Unknown'
                ) as nama_penyewa_benar,
                p.nomor_hp, p.nama_kostum, p.size, p.quantity,
                p.tanggal_pinjam, p.jumlah_hari, p.tanggal_mulai, p.tanggal_selesai
            FROM pesanan p 
            LEFT JOIN transaksi t ON p.id_transaksi = t.id_transaksi 
            LEFT JOIN peminjaman pm ON p.id_transaksi = pm.id_transaksi AND p.id_produk = pm.id_produk
            WHERE p.id_pesanan = ?";
            $stmtGetData = $conn->prepare($getPesananDataQuery);
            $stmtGetData->bind_param("i", $idPesanan);
            $stmtGetData->execute();
            $pesananData = $stmtGetData->get_result()->fetch_assoc();
            
            if (!$pesananData) {
                throw new Exception("Data pesanan tidak ditemukan untuk dipindahkan ke riwayat");
            }

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

            $insertRiwayatQuery = "INSERT INTO riwayat_pesanan (
                id_pesanan_asli, id_transaksi, id_user, id_produk, id_penyedia,
                nama_penyewa, nomor_hp, nama_kostum, size, quantity,
                tanggal_pinjam, jumlah_hari, tanggal_mulai, tanggal_selesai,
                tanggal_pengembalian, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'selesai')";

            $stmtInsertRiwayat = $conn->prepare($insertRiwayatQuery);

            // Pastikan nama_penyewa tidak kosong atau '0'
            $namaPenyewaFinal = $pesananData['nama_penyewa_benar'];
            if (empty($namaPenyewaFinal) || $namaPenyewaFinal === '0') {
                // Fallback: coba ambil dari tabel transaksi langsung
                $getTransaksiQuery = "SELECT nama_lengkap FROM transaksi WHERE id_transaksi = ?";
                $stmtTransaksi = $conn->prepare($getTransaksiQuery);
                $stmtTransaksi->bind_param("s", $pesananData['id_transaksi']);
                $stmtTransaksi->execute();
                $transaksiResult = $stmtTransaksi->get_result()->fetch_assoc();
                if ($transaksiResult && !empty($transaksiResult['nama_lengkap']) && $transaksiResult['nama_lengkap'] !== '0') {
                    $namaPenyewaFinal = $transaksiResult['nama_lengkap'];
                } else {
                    $namaPenyewaFinal = 'Penyewa-' . $pesananData['id_user'];
                }
            }

            $stmtInsertRiwayat->bind_param("isiiiisssisiss", 
                $pesananData['id_pesanan'],         // id_pesanan_asli (integer)
                $pesananData['id_transaksi'],       // id_transaksi (string)
                $pesananData['id_user'],            // id_user (integer)
                $pesananData['id_produk'],          // id_produk (integer)
                $pesananData['id_penyedia'],        // id_penyedia (integer)
                $namaPenyewaFinal,                  // nama_penyewa (string) - DIPERBAIKI
                $pesananData['nomor_hp'],           // nomor_hp (string)
                $pesananData['nama_kostum'],        // nama_kostum (string)
                $pesananData['size'],               // size (string)
                $pesananData['quantity'],           // quantity (integer)
                $pesananData['tanggal_pinjam'],     // tanggal_pinjam (string)
                $pesananData['jumlah_hari'],        // jumlah_hari (integer)
                $pesananData['tanggal_mulai'],      // tanggal_mulai (string)
                $pesananData['tanggal_selesai']     // tanggal_selesai (string)
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

            $updatePeminjamanQuery = "UPDATE peminjaman SET status_peminjaman = 'selesai', updated_at = CURRENT_TIMESTAMP WHERE id_pesanan = ?";
            $stmt = $conn->prepare($updatePeminjamanQuery);
            $stmt->bind_param("i", $idPesanan);
            if (!$stmt->execute()) {
                error_log("Gagal update status peminjaman: " . $stmt->error);
            }

            $insertConfirmationQuery = "
                INSERT INTO provider_return_confirmations (id_pesanan, id_transaksi, id_penyedia)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE confirmed_at = CURRENT_TIMESTAMP
            ";
            $stmt = $conn->prepare($insertConfirmationQuery);
            $stmt->bind_param("isi", $idPesanan, $idTransaksi, $idUserPenyedia);
            if (!$stmt->execute()) {
                throw new Exception("Gagal menyimpan konfirmasi pengembalian penyedia: " . $stmt->error);
            }


            $notificationType = 'provider_confirmed_returned_costume';
            $title = 'Kostum Telah Dikembalikan (Konfirmasi Penyedia)';
            $message = "Penyedia '{$namaPenyedia}' telah mengkonfirmasi pengembalian kostum '{$namaKostum}' oleh penyewa '{$namaPenyewa}' untuk transaksi {$idTransaksi}.";

            $insertAdminNotification = $conn->prepare("
                INSERT INTO notifications_admin (id_transaksi, id_pesanan, id_user, type, title, message)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $insertAdminNotification->bind_param(
                "siisss", 
                $idTransaksi, 
                $idPesanan, 
                $idUserPenyedia, // ID penyedia sebagai id_user yang memicu notifikasi
                $notificationType, 
                $title, 
                $message
            );

            if (!$insertAdminNotification->execute()) {
                error_log("Gagal menyimpan notifikasi admin (kostum kembali): " . $insertAdminNotification->error);
            }
            
            // 2. Simpan konfirmasi provider di tabel terpisah untuk return
            $createProviderReturnTable = "CREATE TABLE IF NOT EXISTS `provider_return_confirmations` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `id_pesanan` int(11) NOT NULL,
                `id_transaksi` varchar(10) NOT NULL,
                `id_penyedia` int(11) NOT NULL,
                `confirmed_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_provider_return` (`id_pesanan`, `id_penyedia`),
                KEY `id_transaksi` (`id_transaksi`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            
            $conn->query($createProviderReturnTable);
            
            $insertProviderReturnConfirmation = "INSERT INTO provider_return_confirmations (id_pesanan, id_transaksi, id_penyedia) 
                                                VALUES (?, ?, ?) 
                                                ON DUPLICATE KEY UPDATE confirmed_at = CURRENT_TIMESTAMP";
            $stmtProviderReturn = $conn->prepare($insertProviderReturnConfirmation);
            $stmtProviderReturn->bind_param("isi", $idPesanan, $pesanan['id_transaksi'], $pesanan['id_penyedia']);
            $stmtProviderReturn->execute();

            // PERBAIKAN: Langsung kembalikan stok ketika penyedia konfirmasi pengembalian
            error_log("🔄 Mulai proses pengembalian stok untuk pesanan yang dikembalikan {$idPesanan}");
            
            
            // 6. Log aktivitas
            error_log("Pesanan {$idPesanan} - kostum telah dikembalikan dan stok telah dikembalikan");
        
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => $customerConfirmed ? 'Kostum telah dikembalikan. Kedua pihak telah konfirmasi.' : 'Konfirmasi penyedia berhasil. Menunggu konfirmasi penyewa.',
                'id_pesanan' => $idPesanan,
                'moved_to_history' => true,
                'status' => $customerConfirmed ? 'both_confirmed_returned' : 'provider_confirmed_returned',
                'both_confirmed' => $customerConfirmed,
                'stock_restored' => true,
                'customer_confirmed' => $customerConfirmed
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
