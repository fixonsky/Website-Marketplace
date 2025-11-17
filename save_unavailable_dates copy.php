<?php
session_start();

header('Content-Type: application/json');

// Enable error reporting 
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once 'koneksi.php';

function sendError($message, $code = 400) {
    http_response_code($code);
    die(json_encode([
        'status' => 'error',
        'message' => $message
    ]));
}

// Fungsi untuk log debug
function debug_log($message) {
    file_put_contents('debug.log', date('[Y-m-d H:i:s] ') . $message . PHP_EOL, FILE_APPEND);
}

try {
    // Validasi session
    if (!isset($_SESSION['id_user'])) {
        throw new Exception("Anda harus login terlebih dahulu", 401);
    }

    debug_log("Memulai proses penyimpanan data booking");

    // Baca input JSON
    $json = file_get_contents('php://input');
    if ($json === false) {
        throw new Exception("Tidak dapat membaca data input", 400);
    }

    debug_log("Data JSON diterima: " . $json);

    $data = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Data JSON tidak valid: " . json_last_error_msg(), 400);
    }

    // Validasi data wajib
    $requiredFields = ['product_id', 'booked_by', 'dates'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field])) {
            throw new Exception("Field wajib tidak ditemukan: " . $field, 400);
        }
    }

    $productId = (int)$data['product_id'];
    $bookedBy = trim($data['booked_by']);
    $dates = $data['dates'];

    debug_log("Data yang akan diproses - Product ID: $productId, Booked By: $bookedBy, Dates: " . implode(', ', $dates));

    if ($productId <= 0) {
        throw new Exception("ID Produk tidak valid", 400);
    }

    if (empty($bookedBy)) {
        throw new Exception("Nama pemesan harus diisi", 400);
    }

    if (!is_array($dates) || empty($dates)) {
        throw new Exception("Tidak ada tanggal yang dipilih", 400);
    }

    // Verifikasi koneksi database
    if ($conn->connect_errno) {
        throw new Exception("Koneksi database gagal: " . $conn->connect_error, 500);
    }

    // Verifikasi produk
    $checkStmt = $conn->prepare("SELECT id FROM form_katalog WHERE id = ? AND id_user = ?");
    if (!$checkStmt) {
        throw new Exception("Gagal mempersiapkan query verifikasi: " . $conn->error, 500);
    }
    
    $checkStmt->bind_param("ii", $productId, $_SESSION['id_user']);
    if (!$checkStmt->execute()) {
        throw new Exception("Gagal mengeksekusi query verifikasi: " . $checkStmt->error, 500);
    }
    
    $checkResult = $checkStmt->get_result();
    if ($checkResult->num_rows === 0) {
        throw new Exception("Produk tidak ditemukan atau bukan milik Anda", 404);
    }

    debug_log("Verifikasi produk berhasil");

    // Mulai transaksi
    $conn->begin_transaction();
    debug_log("Transaksi database dimulai");

    // Persiapkan query untuk insert
    $stmtInsert = $conn->prepare("INSERT INTO unavailable_dates (product_id, date, booked_by) VALUES (?, ?, ?) 
                             ON DUPLICATE KEY UPDATE booked_by = VALUES(booked_by)");
    if (!$stmtInsert) {
        throw new Exception("Gagal mempersiapkan query insert: " . $conn->error, 500);
    }

    $insertedCount = 0;
    $skippedDates = [];
    
    foreach ($dates as $date) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            debug_log("Format tanggal tidak valid: " . $date);
            $skippedDates[] = $date;
            continue;
        }
        
        debug_log("Mencoba menyimpan tanggal: " . $date);
        
        $stmtInsert->bind_param("iss", $productId, $date, $bookedBy);
        if (!$stmtInsert->execute()) {
            // Jika error karena duplikasi, skip saja
            if ($stmtInsert->errno == 1062) { // Error code for duplicate entry
                debug_log("Tanggal $date sudah ada, dilewati");
                $skippedDates[] = $date;
                continue;
            }
            throw new Exception("Gagal menyimpan booking untuk tanggal $date: " . $stmtInsert->error, 500);
        }
        
        $insertedCount += $stmtInsert->affected_rows;
        debug_log("Berhasil menyimpan tanggal $date, affected rows: " . $stmtInsert->affected_rows);
    }

    // Commit transaksi
    $conn->commit();
    debug_log("Transaksi berhasil di-commit. Total disimpan: $insertedCount, Dilewati: " . count($skippedDates));

    // Response
    $response = [
        'status' => 'success',
        'message' => 'Data booking berhasil disimpan',
        'inserted' => $insertedCount,
        'skipped' => $skippedDates
    ];
    
    if ($insertedCount === 0 && empty($skippedDates)) {
        $response['message'] = 'Tidak ada data yang disimpan';
    } elseif ($insertedCount === 0) {
        $response['message'] = 'Semua tanggal yang dipilih sudah ada di database';
    }
    
    echo json_encode($response);
    debug_log("Response dikirim: " . json_encode($response));

} catch (Exception $e) {
    // Rollback jika transaksi aktif
    if (isset($conn) && $conn instanceof mysqli && $conn->connect_errno === 0) {
        debug_log("Rollback transaksi karena error");
        $conn->rollback();
    }
    
    http_response_code($e->getCode() ?: 500);
    $errorResponse = [
        'status' => 'error',
        'message' => $e->getMessage(),
        'code' => $e->getCode()
    ];
    
    echo json_encode($errorResponse);
    debug_log("Error terjadi: " . $e->getMessage() . " (Code: " . $e->getCode() . ")");

} finally {
    // Tutup statement dan koneksi
    if (isset($checkStmt)) {
        $checkStmt->close();
    }
    if (isset($stmtInsert)) {
        $stmtInsert->close();
    }
    if (isset($conn)) {
        $conn->close();
    }
    debug_log("Proses selesai");
    exit;
}
?>