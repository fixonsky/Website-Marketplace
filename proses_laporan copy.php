<?php

session_name('penyedia_session');
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

ob_start();

$host = "localhost";
$user = "root";
$password = "password123";
$database = "daftar_akun";

try {
    $conn = new mysqli($host, $user, $password, $database);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8");

    // Buat tabel laporan jika belum ada
    $createLaporanTable = "CREATE TABLE IF NOT EXISTS `laporan` (
        `id_laporan` varchar(50) NOT NULL PRIMARY KEY,
        `id_transaksi` varchar(50) DEFAULT NULL,
        `tanggal_laporan` timestamp DEFAULT CURRENT_TIMESTAMP,
        `username_penyedia` varchar(100) NOT NULL,
        `username_penyewa` varchar(100) NOT NULL,
        `nama_penyewa` varchar(255) NOT NULL,
        `nama_kostum` varchar(255) NOT NULL,
        `status_laporan` varchar(50) DEFAULT 'Pending',
        `deskripsi_laporan` text NOT NULL,
        `link_bukti` text DEFAULT NULL,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    if (!$conn->query($createLaporanTable)) {
        throw new Exception("Error creating laporan table: " . $conn->error);
    }

    $updateRiwayatStatusEnum = "ALTER TABLE `riwayat_pesanan` MODIFY COLUMN `status` enum('selesai','dilaporkan') DEFAULT 'selesai'";
    $conn->query($updateRiwayatStatusEnum); // Tidak perlu error handling karena mungkin sudah ada

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method.');
    }

    if (empty($_POST['id_transaksi']) || empty($_POST['deskripsi_laporan'])) {
        $debug = [
            'post_data' => $_POST,
            'id_transaksi' => $_POST['id_transaksi'] ?? 'not set',
            'deskripsi' => $_POST['deskripsi_laporan'] ?? 'not set'
        ];
        throw new Exception('Data transaksi atau deskripsi tidak lengkap. Debug: ' . json_encode($debug));
    }

    $idTransaksi = $_POST['id_transaksi'];
    $idPesanan = $_POST['id_pesanan'] ?? null;
    $deskripsiLaporan = $_POST['deskripsi_laporan'];

    // PERBAIKAN: Query yang lebih lengkap dengan JOIN ke semua tabel yang diperlukan
    $querySelect = "SELECT 
            p.id_pesanan,
            p.id_user, 
            p.id_penyedia,
            p.id_produk,
            p.nama_penyewa,
            p.nomor_hp,
            p.nama_kostum,
            p.size,
            p.quantity,
            p.tanggal_pinjam,
            p.jumlah_hari,
            p.tanggal_mulai,
            p.tanggal_selesai,
            p.id_transaksi,
            dt.username AS username_penyedia,
            up.username AS username_penyewa
        FROM pesanan p 
        LEFT JOIN data_toko dt ON p.id_penyedia = dt.id_user
        LEFT JOIN user_profiles up ON p.id_user = up.user_id
        WHERE p.id_transaksi = ? " . ($idPesanan ? "AND p.id_pesanan = ?" : "") . "
        LIMIT 1";
    
    $stmt = $conn->prepare($querySelect);
    
    if (!$stmt) {
        throw new Exception("Error preparing select query: " . $conn->error);
    }

    if ($idPesanan) {
        $stmt->bind_param("ss", $idTransaksi, $idPesanan);
    } else {
        $stmt->bind_param("s", $idTransaksi);
    }
        
    if (!$stmt->execute()) {
        throw new Exception("Error executing select query: " . $stmt->error);
    }
    
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        // DEBUGGING: Cek apakah transaksi ada di tabel pesanan dengan berbagai format
        $debugQueries = [
            "SELECT id_transaksi, COUNT(*) as count FROM pesanan WHERE id_transaksi = ? GROUP BY id_transaksi",
            "SELECT id_transaksi, COUNT(*) as count FROM pesanan WHERE id_transaksi = CAST(? AS CHAR) GROUP BY id_transaksi",
            "SELECT id_transaksi, COUNT(*) as count FROM pesanan WHERE CAST(id_transaksi AS CHAR) = ? GROUP BY id_transaksi"
        ];
        
        $debugInfo = [];
        foreach ($debugQueries as $query) {
            $debugStmt = $conn->prepare($query);
            if ($debugStmt) {
                $debugStmt->bind_param("s", $idTransaksi);
                $debugStmt->execute();
                $debugResult = $debugStmt->get_result();
                while ($row = $debugResult->fetch_assoc()) {
                    $debugInfo[] = "Found: id_transaksi='{$row['id_transaksi']}', count={$row['count']}";
                }
                $debugStmt->close();
            }
        }
        
        // Tampilkan semua id_transaksi yang ada untuk debugging
        $allTransaksiStmt = $conn->prepare("SELECT DISTINCT id_transaksi FROM pesanan LIMIT 10");
        if ($allTransaksiStmt) {
            $allTransaksiStmt->execute();
            $allTransaksiResult = $allTransaksiStmt->get_result();
            $existingIds = [];
            while ($row = $allTransaksiResult->fetch_assoc()) {
                $existingIds[] = $row['id_transaksi'];
            }
            $allTransaksiStmt->close();
            $debugInfo[] = "Existing IDs: " . implode(', ', $existingIds);
        }
        
        throw new Exception("Data pesanan tidak ditemukan untuk id_transaksi: $idTransaksi. Debug info: " . implode('; ', $debugInfo));
    }
    
    $pesanan = $result->fetch_assoc();
    $stmt->close();

    // Pilih nama yang terbaik (dari transaksi atau pesanan)
    $namaPenyewaFinal = $pesanan['nama_penyewa'];
    $namaKostumFinal = $pesanan['nama_kostum'];

    // PERBAIKAN: Ambil username langsung dari hasil query JOIN
    $usernamePenyedia = !empty($pesanan['username_penyedia']) ? 
                        $pesanan['username_penyedia'] : 
                        'provider_' . $pesanan['id_penyedia'];

    $usernamePenyewa = !empty($pesanan['username_penyewa']) ? 
                    $pesanan['username_penyewa'] : 
                    'renter_' . $pesanan['id_user'];

    // Handle upload gambar
    $linkBukti = null;
    $uploadedFiles = [];
    $uploadDir = 'uploads/laporan/';
    
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true)) {
            throw new Exception("Gagal membuat direktori upload: " . $uploadDir);
        }
    }

    if (isset($_FILES['bukti_laporan']) && is_array($_FILES['bukti_laporan']['name'])) {
        foreach ($_FILES['bukti_laporan']['name'] as $key => $name) {
            $tmpName = $_FILES['bukti_laporan']['tmp_name'][$key];
            if (!empty($name) && $_FILES['bukti_laporan']['error'][$key] == 0) {
                $fileExtension = pathinfo($name, PATHINFO_EXTENSION);
                $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
                
                if (in_array(strtolower($fileExtension), $allowedTypes)) {
                    $newFileName = uniqid('bukti_') . '.' . $fileExtension;
                    $uploadPath = $uploadDir . $newFileName;
                    
                    if (move_uploaded_file($tmpName, $uploadPath)) {
                        $uploadedFiles[] = 'uploads/laporan/' . $newFileName;
                    }
                }
            }
        }
        
        if (!empty($uploadedFiles)) {
            $linkBukti = implode(',', $uploadedFiles);
        }
    }

    // Generate ID Laporan
    $idLaporan = 'L' . date('YmdHis') . uniqid();

    $conn->begin_transaction();

    try {

        // PERBAIKAN: Query insert dengan pengecekan error yang lebih baik
        $queryInsert = "INSERT INTO laporan (id_laporan, id_transaksi, username_penyedia, username_penyewa, nama_penyewa, nama_kostum, deskripsi_laporan, link_bukti) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmtInsert = $conn->prepare($queryInsert);
        
        if (!$stmtInsert) {
            throw new Exception('Gagal menyiapkan query insert: ' . $conn->error);
        }
        
        if (!$stmtInsert->bind_param(
            "ssssssss", 
            $idLaporan, 
            $idTransaksi, 
            $usernamePenyedia,
            $usernamePenyewa,
            $namaPenyewaFinal, 
            $namaKostumFinal, 
            $deskripsiLaporan, 
            $linkBukti
        )) {
            throw new Exception('Gagal bind parameter: ' . $stmtInsert->error);
        }

        if (!$stmtInsert->execute()) {
            throw new Exception('Gagal menyimpan laporan: ' . $stmtInsert->error);
        }
        
        $stmtInsert->close();

        $queryInsertRiwayat = "INSERT INTO riwayat_pesanan 
            (id_pesanan_asli, id_transaksi, id_user, id_produk, id_penyedia, nama_penyewa, nomor_hp, 
                nama_kostum, size, quantity, tanggal_pinjam, jumlah_hari, tanggal_mulai, tanggal_selesai, 
                tanggal_pengembalian, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'dilaporkan')";
        
        $stmtRiwayat = $conn->prepare($queryInsertRiwayat);
        
        if (!$stmtRiwayat) {
            throw new Exception('Gagal menyiapkan query insert riwayat: ' . $conn->error);
        }
        
        // PERBAIKAN: Sesuaikan bind_param dengan 15 parameter (tanpa id_laporan)
        $id_pesanan_asli = $pesanan['id_pesanan'];
        $id_transaksi_val = $pesanan['id_transaksi'];
        $id_user_val = $pesanan['id_user'];
        $id_produk_val = $pesanan['id_produk'];
        $id_penyedia_val = $pesanan['id_penyedia'];
        $nama_penyewa_val = $pesanan['nama_penyewa'];
        $nomor_hp_val = $pesanan['nomor_hp'] ?? '';
        $nama_kostum_val = $pesanan['nama_kostum'];
        $size_val = $pesanan['size'] ?? '';
        $quantity_val = $pesanan['quantity'] ?? 1;
        $tanggal_pinjam_val = $pesanan['tanggal_pinjam'] ?? '0000-00-00';
        $jumlah_hari_val = $pesanan['jumlah_hari'] ?? 0;
        $tanggal_mulai_val = $pesanan['tanggal_mulai'];
        $tanggal_selesai_val = $pesanan['tanggal_selesai'];
        
        if (!$stmtRiwayat->bind_param(
            "isiiisisssisss",        // 15 parameter: i,s,i,i,i,s,s,s,s,i,s,i,s,s,s
            $id_pesanan_asli,        // id_pesanan_asli
            $id_transaksi_val,       // id_transaksi
            $id_user_val,            // id_user
            $id_produk_val,          // id_produk
            $id_penyedia_val,        // id_penyedia
            $nama_penyewa_val,       // nama_penyewa
            $nomor_hp_val,           // nomor_hp
            $nama_kostum_val,        // nama_kostum
            $size_val,               // size
            $quantity_val,           // quantity
            $tanggal_pinjam_val,     // tanggal_pinjam
            $jumlah_hari_val,        // jumlah_hari
            $tanggal_mulai_val,      // tanggal_mulai
            $tanggal_selesai_val     // tanggal_selesai
        )) {
            throw new Exception('Gagal bind parameter riwayat: ' . $stmtRiwayat->error);
        }

        if (!$stmtRiwayat->execute()) {
            throw new Exception('Gagal menyimpan ke riwayat pesanan: ' . $stmtRiwayat->error);
        }
        
        $stmtRiwayat->close();

        // 3. TAMBAHAN: Hapus data pesanan dari tabel pesanan setelah dipindah ke riwayat
        $queryDeletePesanan = "DELETE FROM pesanan WHERE id_pesanan = ?";
        $stmtDelete = $conn->prepare($queryDeletePesanan);
        
        if (!$stmtDelete) {
            throw new Exception('Gagal menyiapkan query delete pesanan: ' . $conn->error);
        }

        $id_pesanan_delete = $pesanan['id_pesanan'];
        if (!$stmtDelete->bind_param("i", $id_pesanan_delete)) {
            throw new Exception('Gagal bind parameter delete: ' . $stmtDelete->error);
        }
        

        if (!$stmtDelete->execute()) {
            throw new Exception('Gagal menghapus pesanan dari tabel pesanan: ' . $stmtDelete->error);
        }
        
        $stmtDelete->close();
        
        $conn->commit();

        // Bersihkan output buffer dan kirim response JSON
        ob_clean();
        header('Content-Type: application/json');
        
         echo json_encode([
            'success' => true,
            'message' => 'Laporan berhasil dikirim ke admin dan pesanan dipindah ke riwayat',
            'id_laporan' => $idLaporan,
            'debug_info' => [
                'id_transaksi' => $idTransaksi,
                'id_pesanan' => $pesanan['id_pesanan'],
                'nama_penyewa' => $namaPenyewaFinal,
                'nama_kostum' => $namaKostumFinal,
                'username_penyedia' => $usernamePenyedia,
                'username_penyewa' => $usernamePenyewa,
                'status_riwayat' => 'dilaporkan'
            ]
        ]);
    } catch (Exception $e) {
        // Rollback transaksi jika ada error
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    // Bersihkan output buffer dan kirim error response
    ob_clean();
    header('Content-Type: application/json', true, 500);
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan saat mengirim laporan: ' . $e->getMessage()
    ]);
}
?>