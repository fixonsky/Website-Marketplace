<?php
// File: add_unique_constraint_peminjaman_aktif.php
// Jalankan sekali saja untuk menambahkan unique constraint

// Tambahkan error reporting untuk debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "Memulai proses cleanup database...<br>";
flush();

try {
    // Koneksi database - gunakan kredensial yang sama dengan file lainnya
    $host = "localhost";
    $user = "root";
    $password = "password123";
    $database = "daftar_akun";
    
    $conn = new mysqli($host, $user, $password, $database);

    if ($conn->connect_error) {
        throw new Exception("Koneksi database gagal: " . $conn->connect_error);
    }
    
    echo "✅ Koneksi database berhasil<br>";
    flush();

    // 1. Cek struktur tabel dulu
    echo "Memeriksa struktur tabel peminjaman_aktif...<br>";
    flush();
    
    $checkTableSql = "SHOW TABLES LIKE 'peminjaman_aktif'";
    $tableResult = $conn->query($checkTableSql);
    
    if ($tableResult->num_rows == 0) {
        throw new Exception("Tabel peminjaman_aktif tidak ditemukan!");
    }
    
    echo "✅ Tabel peminjaman_aktif ditemukan<br>";
    flush();

    // 2. Hitung data sebelum cleanup
    $countBeforeSql = "SELECT COUNT(*) as total FROM peminjaman_aktif";
    $countResult = $conn->query($countBeforeSql);
    $countBefore = $countResult->fetch_assoc()['total'];
    
    echo "Jumlah data sebelum cleanup: " . $countBefore . "<br>";
    flush();

    // 3. Identifikasi data duplikat
    echo "Mencari data duplikat...<br>";
    flush();
    
    $findDuplicatesSql = "SELECT id_pesanan, COUNT(*) as jumlah 
                         FROM peminjaman_aktif 
                         GROUP BY id_pesanan 
                         HAVING COUNT(*) > 1";
    $duplicatesResult = $conn->query($findDuplicatesSql);
    
    if ($duplicatesResult->num_rows > 0) {
        echo "Ditemukan " . $duplicatesResult->num_rows . " id_pesanan yang duplikat:<br>";
        while($row = $duplicatesResult->fetch_assoc()) {
            echo "- ID Pesanan: " . $row['id_pesanan'] . " (duplikat: " . $row['jumlah'] . ")<br>";
        }
        flush();
        
        // 4. Hapus data duplikat - simpan yang terbaru
        echo "Menghapus data duplikat...<br>";
        flush();
        
        $cleanupSql = "DELETE pa1 FROM peminjaman_aktif pa1
                      INNER JOIN peminjaman_aktif pa2 
                      WHERE pa1.id_peminjaman_aktif < pa2.id_peminjaman_aktif 
                      AND pa1.id_pesanan = pa2.id_pesanan";

        $result = $conn->query($cleanupSql);
        if ($result) {
            $deletedRows = $conn->affected_rows;
            echo "✅ Berhasil menghapus " . $deletedRows . " data duplikat<br>";
            flush();
        } else {
            echo "⚠️ Tidak ada data duplikat yang perlu dihapus atau error: " . $conn->error . "<br>";
            flush();
        }
    } else {
        echo "✅ Tidak ditemukan data duplikat<br>";
        flush();
    }

    // 5. Cek apakah constraint sudah ada
    echo "Memeriksa existing constraints...<br>";
    flush();
    
    $checkConstraintSql = "SELECT CONSTRAINT_NAME 
                          FROM information_schema.TABLE_CONSTRAINTS 
                          WHERE TABLE_SCHEMA = '$database' 
                          AND TABLE_NAME = 'peminjaman_aktif' 
                          AND CONSTRAINT_NAME = 'unique_id_pesanan'";
    
    $constraintResult = $conn->query($checkConstraintSql);
    
    if ($constraintResult->num_rows > 0) {
        echo "✅ Constraint unique_id_pesanan sudah ada<br>";
        flush();
    } else {
        // 6. Tambah unique constraint
        echo "Menambahkan unique constraint...<br>";
        flush();
        
        $alterSql = "ALTER TABLE peminjaman_aktif ADD UNIQUE KEY unique_id_pesanan (id_pesanan)";
        $alterResult = $conn->query($alterSql);
        
        if ($alterResult) {
            echo "✅ Berhasil menambahkan unique constraint pada id_pesanan<br>";
            flush();
        } else {
            echo "❌ Gagal menambahkan constraint: " . $conn->error . "<br>";
            flush();
        }
    }

    // 7. Hitung data setelah cleanup
    $countAfterSql = "SELECT COUNT(*) as total FROM peminjaman_aktif";
    $countAfterResult = $conn->query($countAfterSql);
    $countAfter = $countAfterResult->fetch_assoc()['total'];
    
    echo "Jumlah data setelah cleanup: " . $countAfter . "<br>";
    flush();

    echo "<br><strong>✅ PROSES SELESAI!</strong><br>";
    echo "Data peminjaman_aktif sudah dibersihkan dan siap digunakan.<br>";

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "<br>";
}

// Tutup koneksi
if (isset($conn)) {
    $conn->close();
}

echo "<br><hr>";
echo "<small>File ini sudah selesai dijalankan. Anda bisa menghapus file ini atau menyimpannya sebagai backup.</small>";
?>