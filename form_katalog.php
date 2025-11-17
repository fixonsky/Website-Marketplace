
<?php
session_name('penyedia_session');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (session_status() !== PHP_SESSION_ACTIVE) {
    die("Session failed to start.");
}

$conn = new mysqli("localhost", "root", "password123", "daftar_akun");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function processMultipleUkuran($ukuranArray) {
    if (is_array($ukuranArray)) {
        return implode(',', $ukuranArray);
    }
    return $ukuranArray;
}

function getUkuranArray($ukuranString) {
    if (empty($ukuranString)) return [];
    return explode(',', $ukuranString);
}

function processUkuranWithMeasurements($ukuranArray, $ldArray, $lpArray) {
    if (is_array($ukuranArray)) {
        $result = [];
        foreach ($ukuranArray as $index => $ukuran) {
            $ld = isset($ldArray[$index]) ? $ldArray[$index] : '';
            $lp = isset($lpArray[$index]) ? $lpArray[$index] : '';
            $result[] = $ukuran . '(' . $ld . '/' . $lp . ')';
        }
        return implode(',', $result);
    }
    return $ukuranArray;
}

function getUkuranWithMeasurements($ukuranString) {
    if (empty($ukuranString)) return [];
    $ukuranArray = explode(',', $ukuranString);
    $result = [];
    
    foreach ($ukuranArray as $ukuran) {
        if (preg_match('/^(.+)\((\d*)\/?(\d*)\)$/', $ukuran, $matches)) {
            $result[] = [
                'size' => trim($matches[1]),
                'ld' => $matches[2] ?? '',
                'lp' => $matches[3] ?? ''
            ];
        } else {
            $result[] = [
                'size' => trim($ukuran),
                'ld' => '',
                'lp' => ''
            ];
        }
    }
    return $result;
}

if (!isset($_SESSION['id_user'])) {
    die("User ID tidak ditemukan. Harap login terlebih dahulu.");
}

$iduser = $_SESSION['id_user'];
$data = [];
$existingPhotos = [];

// Tambahkan pengecekan is_canceled lebih awal
$isCanceled = false;
if (isset($data['is_canceled']) && $data['is_canceled'] == 1 && isset($data['status']) && $data['status'] === 'published') {
    $isCanceled = true;
}

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    if (is_numeric($id)) {
        $query = "SELECT f.*, p.is_canceled, p.published_at 
                  FROM form_katalog f
                  LEFT JOIN published_kostum p ON f.id = p.id_kostum
                  WHERE f.id = ? AND f.id_user = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $id, $_SESSION['id_user']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $data = $result->fetch_assoc();
            $existingPhotos = !empty($data['foto_kostum']) ? explode(',', $data['foto_kostum']) : [];

            // Gunakan parameter canceled dari URL jika ada, jika tidak gunakan dari database
            $isCanceled = isset($_GET['canceled']) ? $_GET['canceled'] == '1' : 
                         (isset($data['is_canceled']) && $data['is_canceled'] == 1 && 
                          isset($data['status']) && $data['status'] === 'published');
            // Debugging - tampilkan data yang diambil
            error_log("Data dari database: " . print_r($data, true));   
            
        } else {
            echo "Data tidak ditemukan.";
        }
    } else {
        echo "ID tidak valid.";
    }
}

// Query untuk series dan karakter tetap sama
$querySeries = "SELECT DISTINCT series FROM form_katalog WHERE id_user = ?";
$stmtSeries = $conn->prepare($querySeries);
$stmtSeries->bind_param("i", $iduser);
$stmtSeries->execute();
$resultSeries = $stmtSeries->get_result();

$queryKarakter = "SELECT DISTINCT karakter FROM form_katalog WHERE id_user = ?";
$stmtKarakter = $conn->prepare($queryKarakter);
$stmtKarakter->bind_param("i", $iduser);
$stmtKarakter->execute();
$resultKarakter = $stmtKarakter->get_result();

// Modifikasi array_merge untuk tidak menimpa series dan karakter jika sudah ada
$defaults = [
    'judul_post' => '',
    'kategori' => '',
    'series' => '',
    'karakter' => '',
    'ukuran' => '',
    'gender' => '',
    'harga_sewa' => '',
    'jumlah_hari' => '',
    'kebijakan_penyewaan' => '',
    'deskripsi' => '',
    'foto_kostum' => '',
    'stok' => '1',
    'status' => 'draft'
];

$data = array_merge($defaults, $data);

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    error_log("POST Data received:");
    error_log("Ukuran: " . print_r($_POST['ukuran'] ?? [], true));
    error_log("LD: " . print_r($_POST['ld'] ?? [], true));
    error_log("LP: " . print_r($_POST['lp'] ?? [], true));

    // Ambil data dari form
    $judulPost = $_POST['judulPost'] ?? '';
    $kategori = $_POST['kategori'] ?? '';
    $series = $_POST['series'] ?? '';
    $karakter = $_POST['karakter'] ?? '';  // Perbaiki line 113

    // Proses ukuran dengan LD dan LP
    $ukuranArray = $_POST['ukuran'] ?? [];
    $ldArray = $_POST['ld'] ?? [];
    $lpArray = $_POST['lp'] ?? [];

    if (is_array($ukuranArray) && count($ukuranArray) > 0) {
        $ukuranResult = [];
        for ($i = 0; $i < count($ukuranArray); $i++) {
            $size = $ukuranArray[$i];
            $ld = isset($ldArray[$i]) ? $ldArray[$i] : '';
            $lp = isset($lpArray[$i]) ? $lpArray[$i] : '';
            
            // Format: Size(LD/LP)
            $ukuranResult[] = $size . '(' . $ld . '/' . $lp . ')';
        }
        $ukuran = implode(',', $ukuranResult);
    } else {
        $ukuran = '';
    }

    error_log("Processed ukuran: " . $ukuran);

    $gender = $_POST['gender'] ?? '';
    $hargaSewa = $_POST['hargaSewa'] ?? '';
    $jumlahHari = $_POST['jumlahHari'] ?? '';
    $kebijakan = $_POST['kebijakan'] ?? '';
    $deskripsi = $_POST['deskripsi'] ?? '';
    $stok = $_POST['stok'] ?? '';
    $status = 'draft';

    if (!isset($_POST['id'])) {
        // Data baru
        $checkStatusQuery = "SELECT f.status, p.is_canceled 
            FROM form_katalog f
            LEFT JOIN published_kostum p ON f.id = p.id_kostum
            WHERE f.id = ? AND f.id_user = ?";
        $stmtCheck = $conn->prepare($checkStatusQuery);
        $stmtCheck->bind_param("ii", $_POST['id'], $iduser);
        $stmtCheck->execute();
        $statusResult = $stmtCheck->get_result();
        
        if ($statusResult->num_rows > 0) {
            $row = $statusResult->fetch_assoc();
            $currentStatus = $row['status'];
            $isCanceled = ($row['is_canceled'] == 1 && $currentStatus === 'published');

            if ($isCanceled) {
                // Jika kostum canceled, pertahankan status published
                $status = 'published';
            } else {
                $status = $currentStatus;
                // Jika dari draft ke aktif, set published
                if (($currentStatus === NULL || $currentStatus == 'draft') && $keterangan == 'aktif') {
                    $status = 'published';
                }
            }
        } else {
            $status = 'draft';
        }
        $stmtCheck->close();
    }
    
    $existingPhotos = !empty($data['foto_kostum']) ? explode(',', $data['foto_kostum']) : [];
    $fotoFinal = $existingPhotos;

    // Handle deleted photos
    if (!empty($_POST['deleted_photos'])) {
        $deletedPhotos = explode(',', $_POST['deleted_photos']);
        $fotoFinal = array_diff($fotoFinal, $deletedPhotos);
        
        // Hapus file fisik yang dihapus
        foreach ($deletedPhotos as $photo) {
            $photoPath = "foto_kostum/" . $photo;
            if (file_exists($photoPath) && $photo !== 'default.jpg') {
                unlink($photoPath);
            }
        }
    }

    error_log("Processing photos - Existing: " . count($existingPhotos) . ", Deleted: " . (!empty($_POST['deleted_photos']) ? count(explode(',', $_POST['deleted_photos'])) : 0) . ", New: " . (!empty($_FILES['input_foto']['name'][0]) ? count($_FILES['input_foto']['name']) : 0));
        
   $uploadedFiles = [];
    if (!empty($_FILES['input_foto']['name'][0])) {
        error_log("New files detected: " . count($_FILES['input_foto']['name']));
        
        foreach ($_FILES['input_foto']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['input_foto']['error'][$key] === UPLOAD_ERR_OK) {
                $originalName = basename($_FILES['input_foto']['name'][$key]);
                $fileExtension = pathinfo($originalName, PATHINFO_EXTENSION);
                $filename = time() . "_" . uniqid() . "." . $fileExtension;
                $targetPath = "foto_kostum/" . $filename;
                
                if (move_uploaded_file($tmp_name, $targetPath)) {
                    $uploadedFiles[] = $filename;
                    error_log("Successfully uploaded: " . $filename);
                } else {
                    error_log("Failed to upload: " . $_FILES['input_foto']['name'][$key]);
                }
            }
        }
    }

    error_log("Final photos: " . count($fotoFinal) . " files");

    // Gabungkan foto yang sudah ada dengan foto baru
    $fotoFinal = array_merge($fotoFinal, $uploadedFiles);

    // Validasi jumlah foto maksimal
    if (count($fotoFinal) > 5) {
        die("Error: Maksimal 5 foto yang diizinkan.");
    }

    $fotoKostum = !empty($fotoFinal) ? implode(',', $fotoFinal) : '';
    
    if ($id > 0) {
        // Update data
        $query = "UPDATE form_katalog SET 
                  judul_post = ?, kategori = ?, series = ?, karakter = ?, 
                  ukuran = ?, gender = ?, harga_sewa = ?, 
                  jumlah_hari = ?, kebijakan_penyewaan = ?, deskripsi = ?, 
                foto_kostum = ?, stok = ?, status = ?
                  WHERE id = ? AND id_user = ?";
        $stmt = $conn->prepare($query);

        if (!$stmt) {
            die("Error preparing UPDATE statement: " . $conn->error);
        }

        $stmt->bind_param("sssssssssssssii", 
            $judulPost, $kategori, $series, $karakter, 
            $ukuran, $gender, $hargaSewa, 
            $jumlahHari, $kebijakan, $deskripsi,
            $fotoKostum, $stok, $status, $id, $iduser);
    } else {
        // Insert data baru
        $query = "INSERT INTO form_katalog 
                  (id_user, judul_post, kategori, series, karakter, ukuran, 
                   gender, harga_sewa, jumlah_hari, kebijakan_penyewaan, 
                   deskripsi, foto_kostum, stok, status) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
         $stmt->bind_param("isssssssssssss", 
            $iduser, $judulPost, $kategori, $series, $karakter, 
            $ukuran, $gender, $hargaSewa, $jumlahHari, 
            $kebijakan, $deskripsi, $fotoKostum, $stok, $status);
    }
    
    if ($stmt->execute()) {

        $recordId = $id ? $id : $conn->insert_id;

        if (!$id) { // Hanya untuk kostum baru (INSERT)
            // Buat tabel stok_items jika belum ada
            $createStokItemsTable = "CREATE TABLE IF NOT EXISTS `stok_items` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `kostum_id` int(11) NOT NULL,
                `stok_id` varchar(10) NOT NULL,
                `status` enum('available','rented','maintenance','booked') NOT NULL DEFAULT 'available',
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_kostum_stok` (`kostum_id`, `stok_id`),
                KEY `idx_kostum_id` (`kostum_id`),
                KEY `idx_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            
            $conn->query($createStokItemsTable);
            
            // Buat data stok_items berdasarkan jumlah stok
            $jumlahStok = (int)$stok;
            if ($jumlahStok > 0) {
                for ($i = 1; $i <= $jumlahStok; $i++) {
                    $stokId = str_pad($i, 3, '0', STR_PAD_LEFT);
                    $insertStokItemQuery = "INSERT INTO stok_items (kostum_id, stok_id, status) VALUES (?, ?, 'available')";
                    $stokItemStmt = $conn->prepare($insertStokItemQuery);
                    $stokItemStmt->bind_param("is", $recordId, $stokId);
                    $stokItemStmt->execute();
                }
                error_log("✅ Berhasil membuat {$jumlahStok} stok items untuk kostum ID: {$recordId}");
            }
        } else {
            // PERBAIKAN: Untuk UPDATE, sinkronkan dengan stok_items yang ada
            $jumlahStokBaru = (int)$stok;
            
            // Cek stok_items yang sudah ada
            $checkExistingQuery = "SELECT COUNT(*) as total_count FROM stok_items WHERE kostum_id = ?";
            $checkStmt = $conn->prepare($checkExistingQuery);
            $checkStmt->bind_param("i", $recordId);
            $checkStmt->execute();
            $existingTotal = $checkStmt->get_result()->fetch_assoc()['total_count'];
            
            if ($existingTotal == 0 && $jumlahStokBaru > 0) {
                // Jika belum ada stok_items sama sekali, buat dari awal
                for ($i = 1; $i <= $jumlahStokBaru; $i++) {
                    $stokId = str_pad($i, 3, '0', STR_PAD_LEFT);
                    $insertStokItemQuery = "INSERT INTO stok_items (kostum_id, stok_id, status) VALUES (?, ?, 'available')";
                    $stokItemStmt = $conn->prepare($insertStokItemQuery);
                    $stokItemStmt->bind_param("is", $recordId, $stokId);
                    $stokItemStmt->execute();
                }
                error_log("✅ Berhasil membuat {$jumlahStokBaru} stok items untuk kostum ID: {$recordId} (UPDATE case)");
            } else {
                // Sinkronkan dengan stok_items yang ada (gunakan logika dari update_stok.php)
                $getCurrentStokQuery = "SELECT 
                                        COUNT(CASE WHEN status = 'available' THEN 1 END) as available_count,
                                        COALESCE(MAX(CAST(stok_id AS UNSIGNED)), 0) as max_stok_id
                                        FROM stok_items WHERE kostum_id = ?";
                $getCurrentStmt = $conn->prepare($getCurrentStokQuery);
                $getCurrentStmt->bind_param("i", $recordId);
                $getCurrentStmt->execute();
                $currentData = $getCurrentStmt->get_result()->fetch_assoc();
                
                $currentAvailable = (int)$currentData['available_count'];
                $maxStokId = (int)$currentData['max_stok_id'];
                
                if ($jumlahStokBaru > $currentAvailable) {
                    // Tambah stok available
                    $jumlahTambahan = $jumlahStokBaru - $currentAvailable;
                    
                    for ($i = 1; $i <= $jumlahTambahan; $i++) {
                        $newStokId = str_pad($maxStokId + $i, 3, '0', STR_PAD_LEFT);
                        $insertStmt = $conn->prepare("INSERT INTO stok_items (kostum_id, stok_id, status) VALUES (?, ?, 'available')");
                        $insertStmt->bind_param("is", $recordId, $newStokId);
                        $insertStmt->execute();
                    }
                } elseif ($jumlahStokBaru < $currentAvailable) {
                    // Kurangi stok available
                    $jumlahKurang = $currentAvailable - $jumlahStokBaru;
                    
                    $deleteStmt = $conn->prepare("DELETE FROM stok_items 
                                                WHERE kostum_id = ? AND status = 'available' 
                                                ORDER BY CAST(stok_id AS UNSIGNED) DESC 
                                                LIMIT ?");
                    $deleteStmt->bind_param("ii", $recordId, $jumlahKurang);
                    $deleteStmt->execute();
                }
            }
        }

        $_SESSION['success_message'] = 'Data kostum telah disimpan';

        // Handle published status
        if ($status == 'published') {
            // Check if already in published table
            // $checkPublished = "SELECT * FROM published_kostum WHERE id_kostum = ?";
            // $stmtCheck = $conn->prepare($checkPublished);
            // $stmtCheck->bind_param("i", $recordId);
            // $stmtCheck->execute();
            
            // if ($stmtCheck->get_result()->num_rows == 0) {
            //     $insertPublished = "INSERT INTO published_kostum (id_kostum, published_at) VALUES (?, NOW())";
            //     $stmtInsert = $conn->prepare($insertPublished);
            //     $stmtInsert->bind_param("i", $recordId);
            //     $stmtInsert->execute();
            // }
            
            header("Location: /TUGAS_AKHIR_KESYA/indexuser/index.php?status=success");
        } else {
            // For draft, redirect to draft management page
             header("Location: INDEXXX3.php?saved=1");
        }
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }
    
    $conn->close();
}
?>

<!-- HTML bagian tetap sama seperti sebelumnya -->

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Website dengan Navbar dan Sidebar</title>
    <link crossorigin="anonymous" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- <link rel="stylesheet" href="form_katalog.css"> -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    
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

    .container {
        display: flex;
        padding : 70px;
        flex: 1;
        width: 100%;
        max-width: 1200px;
        margin: 0 auto;
        position: relative;
    }

    /* CSS untuk Sidebar */
    .sidebar {
        background-color: #EFEFEF;
        width: 220px;
        padding: 1rem;
        border-right: 1px solid #e0e0e0;
        box-shadow: 2px 0 5px rgba(0,0,0,0.05);
        position: sticky;
        top: 0;
        height: 100vh;
        overflow-y: auto;
        flex-shrink: 0;
        margin-left: -170px;
        margin-top : -70px;
        margin-bottom : -55px;
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
        width: 125%;
        background-color: white;
        padding: 20px;
        border-radius: 5px;
        box-shadow: 0 0 10px rgba(0,0,0,0.1);
        margin-left: -32px;
        margin-top : -86px;
        margin-bottom : -70px;
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

    /* Style for form elements */
    input[readonly], select[disabled], textarea[readonly] {
        background-color: #f5f5f5;
        border-color: #ddd;
        cursor: not-allowed;
    }

    /* Style for photo upload section */
    .photo-costume {
        margin : 0 auto;
        margin-bottom : -50px;
    }

    .image-placeholder {
        padding: 50px;
 
        text-align: center;
        border-radius: 5px;
       
    }

    .text-form {
        display: block;
        font-weight: bold;
        margin-bottom: 5px;
    }

    .text-form-2 {
        display: block;
        color: #6c757d;
        margin-bottom: 10px;
    }

    #default-photo {
        max-width: 100%;
        height: auto;
        margin-bottom: 10px;
    }

    #photo-preview-container {
        display: flex;
        flex-wrap: nowrap;
        gap: 10px;
        margin-bottom: 10px;
      
        justify-content : center;
        padding : 10px 0;
    }

    .preview-image {
        max-width: 150px;
        max-height: 150px;
        border-radius: 5px;
    }

    .remove-button {
        position: absolute;
        top: 5px;
        right: 5px;
        background: #dc3545;
        color: white;
        border: none;
        border-radius: 50%;
        width: 25px;
        height: 25px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }

    .error-message {
        color: #dc3545;
        font-size: 0.875rem;
        margin-top: 5px;
    }

    #choosePhotoButton {
        position: relative;
        z-index: 10;
        cursor: pointer !important;
    }

    #input-foto {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        opacity: 0;
        cursor: pointer;
        z-index: 11;
    }

    .photo-costume {
        position: relative;
        z-index: 1;
    }

    .image-placeholder {
        position: relative;
        z-index: 1;
    }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="logo">Logo</div>
        <ul class="nav-links">
            <li><a href="#">Home</a></li>
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
        <main class = "main-content">
            <div class="form-section">
                <div class="form-header" style="margin-bottom: 20px; margin-top: -19px; margin-left: -20px;">
                    <h2 class="form-title">Lengkapi Data Kostum Anda</h2>
                </div>    
                
                <form action="" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="deleted_photos" id="deletedPhotosInput" value="">
                        <div class="row">
                            <div class="col-md-4 photo-costume">
                                <input type="hidden" name="id" value="<?php echo isset($_GET['id']) ? $_GET['id'] : ''; ?>">
                                <div class="image-placeholder" id="imagePlaceholder">
                                    <?php if (empty($existingPhotos)): ?>
                                        <?php if (!$isCanceled): ?>
                                            <label for="input-foto" class="text-form">Masukkan Foto Kostum Anda</label>
                                            <label for="teks" class="text-form-2">(Max 5 foto)</label>
                                            <img id="default-photo" src="foto_kostum/default.jpg" alt="Default Profile">
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <!-- PERBAIKAN: Container untuk preview foto yang sudah ada dan baru -->
                                    <div id="photo-preview-container">
                                        <?php if (!empty($existingPhotos)): ?>
                                            <?php foreach ($existingPhotos as $index => $photo): ?>
                                                <div style="position: relative; display: inline-block; margin: 5px;">
                                                    <img src="foto_kostum/<?php echo htmlspecialchars($photo); ?>" 
                                                        class="preview-image" 
                                                        style="max-width: 150px; max-height: 150px; border-radius: 5px;"
                                                        data-filename="<?php echo htmlspecialchars($photo); ?>">
                                                    <?php if (!$isCanceled): ?>
                                                        <button type="button" class="remove-button" 
                                                                onclick="removeExistingImage(this, '<?php echo htmlspecialchars($photo); ?>')">
                                                            ✖
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if (!$isCanceled): ?>
                                        <div style="position: relative; display: inline-block;">
                                            <label class="btn btn-outline-secondary" id="choosePhotoButton" style="cursor: pointer; position: relative; z-index: 10;">
                                                <?php echo (!empty($existingPhotos) ? 'Tambah Foto' : 'Pilih Foto'); ?>
                                            </label>
                                            <input type="file" id="input-foto" name="input_foto[]" accept="image/*" 
                                                style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; z-index: 11;" multiple>
                                        </div>
                                    <?php endif; ?>
                                    <p class="error-message" id="errorMessage"></p>
                                </div>
                            </div>
                        </div>
                        <div class="row" style = "margin-top : 50px;">
                            <div class="col-md-6">
                                <div class="mb-6">
                                    <label class="form-label" for="judulPost"> Judul Post </label>
                                    <input class="form-control" name="judulPost" id="judulPost" type="text" 
                                    value="<?php echo htmlspecialchars($data['judul_post'] ?? ''); ?>" placeholder="Rental Cosplay Keqing Opulent Genshin Impact" <?php echo $isCanceled ? 'readonly' : ''; ?> required />
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label" for="kategori"> Kategori </label>
                                    <select class="form-select" name="kategori" id="kategori" required onchange="handleKategoriChange()" <?php echo $isCanceled ? 'disabled' : ''; ?>>
                                        <option value="Cosplay" <?php echo ($data['kategori'] ?? '') === 'Cosplay' ? 'selected' : ''; ?>> Cosplay</option>
                                        <option value="Adat Daerah" <?php echo ($data['kategori'] ?? '') === 'Adat Daerah' ? 'selected' : ''; ?>> Adat Daerah</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label" for="series"> Series </label>
                                    <select class="form-select" name="series" id="series" required onchange="handleSeriesChange()" <?php echo $isCanceled ? 'disabled' : ''; ?>>
                                        <option value="" <?php echo empty($data['series']) ? 'selected' : ''; ?>>Pilih Series</option>
                                        <?php while ($rowSeries = $resultSeries->fetch_assoc()): ?>
                                            <option value="<?php echo htmlspecialchars($rowSeries['series']); ?>" <?php echo ($data['series'] ?? '') === $rowSeries['series'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($rowSeries['series']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label" for="karakter"> Karakter </label>
                                    <select class="form-select" name="karakter" id="karakter" required <?php echo $isCanceled ? 'disabled' : ''; ?>>
                                        <option value="" <?php echo empty($data['karakter']) ? 'selected' : ''; ?>> Pilih Karakter </option>
                                        <?php while ($rowKarakter = $resultKarakter->fetch_assoc()): ?>
                                            <option value="<?php echo htmlspecialchars($rowKarakter['karakter']); ?>" <?php echo ($data['karakter'] ?? '') === $rowKarakter['karakter'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($rowKarakter['karakter']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label"> Ukuran </label>
                                    <div class="ukuran-checkbox-container" style="display: flex; flex-wrap: wrap; gap: 10px; padding: 10px; border: 1px solid #ced4da; border-radius: 0.375rem; background-color: <?php echo $isCanceled ? '#f5f5f5' : '#fff'; ?>;">
                                        <?php 
                                        $ukuranOptions = ['S', 'M', 'L', 'XL', 'XXL', 'XXXL'];
                                        $selectedUkuran = getUkuranWithMeasurements($data['ukuran'] ?? '');
                                        $selectedSizes = array_column($selectedUkuran, 'size');

                                        foreach ($ukuranOptions as $ukuran): 
                                            $isChecked = in_array($ukuran, $selectedSizes);
                                        ?>
                                            <div class="form-check" style="margin-right: 15px;">
                                                 <input class="form-check-input ukuran-checkbox" type="checkbox" name="ukuran[]" 
                                                       value="<?php echo $ukuran; ?>" id="ukuran_<?php echo $ukuran; ?>"
                                                       <?php echo $isChecked ? 'checked' : ''; ?>
                                                       <?php echo $isCanceled ? 'disabled' : ''; ?>
                                                       onchange="toggleUkuranMeasurement('<?php echo $ukuran; ?>')">
                                                <label class="form-check-label" for="ukuran_<?php echo $ukuran; ?>" 
                                                       style="font-weight: 500; cursor: <?php echo $isCanceled ? 'not-allowed' : 'pointer'; ?>;">
                                                    <?php echo $ukuran; ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <small class="form-text text-muted">Pilih satu atau lebih ukuran yang tersedia</small>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label" for="hargaSewa"> Harga Sewa </label>
                                    <div class="input-group">
                                        <input class="form-control" name="hargaSewa" id="hargaSewa" placeholder="Tanpa titik" type="number" 
                                        value="<?php echo htmlspecialchars($data['harga_sewa'] ?? ''); ?>" min="0" required <?php echo $isCanceled ? '' : ''; ?> />
                                        <span class="input-group-text">
                                        /
                                        </span>
                                        <input class="form-control" name="jumlahHari" id="jumlahHari" type="number"
                                        value="<?php echo htmlspecialchars($data['jumlah_hari'] ?? ''); ?>"  placeholder="3" min="1" required <?php echo $isCanceled ? '' : ''; ?> />
                                        <span class="input-group-text"> hari </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row" id="measurement-container">
                            <?php 
                            foreach ($selectedUkuran as $ukuranData): 
                                $size = $ukuranData['size'];
                                $ld = $ukuranData['ld'];
                                $lp = $ukuranData['lp'];
                            ?>
                            <div class="col-md-6 measurement-form" id="measurement_<?php echo $size; ?>" style="margin-bottom: 15px;">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Ukuran <?php echo $size; ?></h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">LD (Lebar Dada) - cm</label>
                                                    <input type="number" class="form-control" name="ld[]" 
                                                            value="<?php echo $ld; ?>" 
                                                            placeholder="Contoh: 50" min="0" 
                                                            <?php echo $isCanceled ? 'readonly' : ''; ?> required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">LP (Lebar Pinggang) - cm</label>
                                                    <input type="number" class="form-control" name="lp[]" 
                                                            value="<?php echo $lp; ?>" 
                                                            placeholder="Contoh: 45" min="0" 
                                                            <?php echo $isCanceled ? 'readonly' : ''; ?> required>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label" for="stok"> Stok Produk </label>
                                    <input class="form-control" name="stok" id="stok" type="number"
                                    value="<?php echo htmlspecialchars($data['stok'] ?? ''); ?>"  placeholder="3" min="1" required />
                                </div>
                            </div>

                               <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label" for="gender"> Gender </label>
                                    <select class="form-select" name="gender" id="gender" required <?php echo $isCanceled ? 'disabled' : ''; ?>>
                                        <option value="Laki-laki" <?php echo ($data['gender'] ?? '') === 'Laki-laki' ? 'selected' : ''; ?>> Laki-laki </option>
                                        <option value="Perempuan" <?php echo ($data['gender'] ?? '') === 'Perempuan' ? 'selected' : ''; ?>> Perempuan </option>
                                        <option value="Unisex" <?php echo ($data['gender'] ?? '') === 'Unisex' ? 'selected' : ''; ?>> Unisex </option>
                                    </select>
                                </div>
                            </div>
                        </div>     
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label" for="deskripsi"> Deskripsi </label>
                                    <textarea class="form-control" name="deskripsi" id="deskripsi" rows="5" <?php echo $isCanceled ? 'readonly' : ''; ?> required><?php echo htmlspecialchars($data['deskripsi'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label" for="kebijakan"> Kebijakan Penyewaan </label>
                                    <textarea class="form-control" name="kebijakan" id="kebijakan" rows="5" <?php echo $isCanceled ? 'readonly' : ''; ?> required><?php echo htmlspecialchars($data['kebijakan_penyewaan'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a class="btn btn-secondary" >Kembali</a>
                            <button class="btn btn-primary" type="submit" name="submit" 
                                        <?php echo $isCanceled ? 'style="background-color: #6c757d; border-color: #6c757d;"' : ''; ?>>
                                    <?php echo $isCanceled ? 'Update Harga/Stok' : 'Simpan'; ?>
                            </button>
                        </div>
                </form>
            </div>
        </main>
    </div>

    <?php if (isset($_GET['status'])): ?>
     <script>

document.addEventListener('DOMContentLoaded', function() {

    <?php if ($_GET['status'] === 'success'): ?>
        Swal.fire({
            title: 'Sukses!',
            text: 'Data berhasil disimpan',
            icon: 'success',
            confirmButtonText: 'OK'
        });
    <?php elseif ($_GET['status'] === 'updated'): ?>
        Swal.fire({
            title: 'Sukses!',
            text: 'Data berhasil diperbarui',
            icon: 'success',
            confirmButtonText: 'OK'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'katalog_kostum.php'; // Redirect setelah OK
            }
        });
    <?php endif; ?>
});
</script>
    <?php endif; ?>

<script>
    
    let existingImages = <?php echo isset($existingPhotos) ? json_encode($existingPhotos) : json_encode([]); ?>;
    let deletedPhotos = [];
    let newPhotos = [];

    const sizeHierarchy = ["S", "M", "L", "XL", "XXL", "XXXL"]; // Urutan ukuran

    const requiredFields = [
        "judulPost",
        "kategori",
        "series",
        "karakter",
        "ukuran",
        "muatSampai",
        "gender",
        "hargaSewa",
        "jumlahHari",
        "deskripsi",
        "kebijakan",
    ];

    const seriesOptions = {
        "Cosplay": {
            "attack-on-titan": "Attack on Titan",
            "demon-slayer": "Demon Slayer",
            "genshin-impact": "Genshin Impact",
            "naruto": "Naruto",
            "kakegurui" : "Kakegurui",
            "one-piece": "One Piece",
            "jujutsu-kaisen": "Jujutsu Kaisen",
            "My Hero Academia": "My Hero Academia",
            "Bleach": "Bleach",
            "Tokyo Revengers": "Tokyo Revengers",
            "Tokyo Ghoul": "Tokyo Ghoul",
            "Black Clover": "Black Clover",
            "Fairy Tail": "Fairy Tail",
            "Dragon Ball": "Dragon Ball",
            "Sword Art Online": "Sword Art Online",
            "Death Note": "Death Note",
            "Fullmetal Alchemist": "Fullmetal Alchemist",
            "Hunter x Hunter": "Hunter x Hunter",
            "One Piece": "One Piece",
            "Sailor Moon": "Sailor Moon",
            "Spy x Family": "Spy x Family",
            "Chainsaw Man": "Chainsaw Man",
            "Re:Zero": "Re:Zero"
        },
        "Adat Daerah": {
            "kalimantan-barat": "Kalimantan Barat",
            "Kalimantan-Timur" : "Kalimantan Timur",
            "Kalimantan-Selatan" : "Kalimantan Selatan",
            "Kalimantan-Tengah" : "Kalimantan Tengah",
            "sumatera-utara": "Sumatera Utara",
            "Sumatera-Barat": "Sumatera Barat",
            "Sumatera-Selatan": "Sumatera Selatan",
            "Bali": "Bali - Bali",
            "Lampung" : "Lampung",
            "Yogyakarta" : "Yogyakarta",
            "Jawa-Tengah" : "Jawa Tengah",
            "Jawa-Timur" : "Jawa Timur",
            "Jawa-Barat" : "Jawa Barat",
            "Aceh" : "Aceh",
            "Bengkulu" : "Bengkulu",
            "Riau" : "Riau",
            "Sulawesi-Utara" : "Sulawesi Utara",
            "Sulawesi-Tengah" : "Sulawesi Tengah",
            "Sulawesi-Barat" : "Sulawesi Barat",
            "Sulawesi-Selatan" : "Sulawesi Selatan",
            "Nusa-Tenggara-Barat" : "Nusa Tenggara Barat",
            "Nusa-Tenggara-Timur" : "Nusa Tenggara Timur",
            "Papua" : "Papua",
            "Maluku" : "Maluku",
            

            
        }
    };

    const karakterOptions = {
        "attack-on-titan": ["Eren Yeager", "Mikasa Ackerman", "Armin Arlert", "Levi Ackerman", "Sasha Blouse", "Hange Zoe", "Reiner Braun", "Annie Leonhart", "Jean Kirstein", "Connie Springer"],
        "demon-slayer": ["Tanjiro Kamado", "Nezuko Kamado", "Zenitsu Agatsuma", "Inosuke Hashibira", "Giyu Tomioka", "Tsuginuki Yorichi", "Gyome Himejima",
            "kokushibo", "Akaza", "Doma", "Kanao Tsuyuri", "Kanroji Mitsuri", "Himejima Gyomei", "Tokito Muichiro", "Rengoku Kyojuro", "Tengen Uzui", "Shinobu Kocho"],
        "genshin-impact": ["Keqing", "Diluc", "Zhongli", "Furina", "Lumine", "Aether", "Venti", "Klee", "Mona", "Qiqi", "Ganyu", "Xiao", "Albedo", "Eula", "Raiden Shogun", "Ayaka"],
        "naruto": ["Naruto Uzumaki", "Sasuke Uchiha", "Sakura Haruno", "Kakashi Hatake", "Hinata Hyuga", "Itachi Uchiha", "Gaara", "Jiraiya", "Tsunade", "Orochimaru"],
        "kakegurui": ["Momobami Ririka", "Saotome Mary", "Itsuki Sumeragi", "Kirari Momobami", "Yumeko Jabami", "Midari Ikishima", "Runa Yomozuki", "Sayaka Igarashi"],
        "one-piece": ["Monkey D. Luffy", "Roronoa Zoro", "Nami", "Usopp", "Sanji", "Tony Tony Chopper", "Nico Robin", "Franky", "Brook", "Jinbe"],
        "jujutsu-kaisen": ["Yuji Itadori", "Megumi Fushiguro", "Nobara Kugisaki", "Satoru Gojo", "Suguru Geto", "Kento Nanami", "Toge Inumaki", "Panda", "Maki Zenin", "Aoi Todo"],
        "My Hero Academia": ["Izuku Midoriya", "Katsuki Bakugo", "Shoto Todoroki", "All Might", "Ochaco Uraraka", "Tenya Iida", "Tsuyu Asui", "Eijiro Kirishima", "Momo Yaoyorozu", "Fumikage Tokoyami"],
        "Bleach": ["Ichigo Kurosaki", "Rukia Kuchiki", "Orihime Inoue", "Uryu Ishida", "Yasutora Sado", "Renji Abarai", "Byakuya Kuchiki", "Toshiro Hitsugaya", "Kenpachi Zaraki", "Grimmjow Jaegerjaquez"],
        "Tokyo Revengers": ["Takemichi Hanagaki", "Manjiro Sano", "Ken Ryuguji", "Tetta Kisaki", "Chifuyu Matsuno", "Keisuke Baji", "Kazutora Hanemiya", "Hakkai Shiba", "Mikey", "Draken"],
        "Tokyo Ghoul": ["Ken Kaneki", "Touka Kirishima", "Rize Kamishiro", "Uta", "Nishiki Nishio", "Hinami Fueguchi", "Koutarou Amon", "Juuzou Suzuya", "Shuu Tsukiyama", "Yoshimura"],
        "Black Clover": ["Asta", "Yuno", "Noelle Silva", "Yami Sukehiro", "Mimosa Vermillion", "Luck Voltia", "Magna Swing", "Finral Roulacase", "Gauche Adlai", "Zora Ideale"],
        "Fairy Tail": ["Natsu Dragneel", "Lucy Heartfilia", "Gray Fullbuster", "Erza Scarlet", "Wendy Marvell", "Happy", "Carla", "Gajeel Redfox", "Juvia Lockser", "Laxus Dreyar"],
        "Dragon Ball": ["Goku", "Vegeta", "Bulma", "Piccolo", "Gohan", "Trunks", "Krillin", "Android 18", "Frieza", "Cell"],
        'Sword Art Online': ['Kirito', 'Asuna', 'Sinon', 'Leafa', 'Yui', 'Klein', 'Agil', 'Lisbeth', 'Silica', 'Eugeo'],
        "Death Note": ["Light Yagami", "L Lawliet", "Misa Amane", "Ryuk", "Near", "Mello", "Soichiro Yagami", "Teru Mikami", "Rem", "Higuchi"],
        "Fullmetal Alchemist": ["Edward Elric", "Alphonse Elric", "Roy Mustang", "Riza Hawkeye", "Winry Rockbell", "Scar", "Alex Louis Armstrong", "Maes Hughes", "Ling Yao", "Greed"],
        "Hunter x Hunter": ["Gon Freecss", "Killua Zoldyck", "Kurapika", "Leorio Paradinight", "Hisoka Morow", "Chrollo Lucilfer", "Illumi Zoldyck", "Biscuit Krueger", "Meruem", "Netero"],
        "Sailor Moon": ["Usagi Tsukino", "Ami Mizuno", "Rei Hino", "Makoto Kino", "Minako Aino", "Mamoru Chiba", "Luna", "Artemis", "Sailor Pluto", "Sailor Saturn"],
        "Spy x Family": ["Loid Forger", "Yor Forger", "Anya Forger", "Bond Forger", "Franky Franklin", "Sylvia Sherwood", "Damian Desmond", "Eden Academy Staff", "Yuri Briar", "Fiona Frost"],
        "Chainsaw Man": ["Denji", "Power", "Aki Hayakawa", "Makima", "Himeno", "Kobeni Higashiyama", "Hirokazu Arai", "Kishibe", "Beam", "Angel Devil"],
        "Re:zero": ["Subaru Natsuki", "Emilia", "Rem", "Ram", "Beatrice", "Roswaal L Mathers", "Crusch Karsten", "Felt", "Wilhelm van Astrea", "Puck"],

    };

    function handleKategoriChange() {
        const kategori = document.getElementById('kategori').value;
        const karakter = document.getElementById('karakter');
        const series = document.getElementById('series');

        series.innerHTML = '<option value="" selected>Pilih Series</option>';
        karakter.innerHTML = '<option value="" selected>Pilih Karakter</option>';


        if (kategori === 'Cosplay') {
            karakter.disabled = false; // Aktifkan karakter
            series.disabled = false;   // Aktifkan series
            // Isi dengan pilihan series untuk Cosplay
            for (const [key, value] of Object.entries(seriesOptions["Cosplay"])) {
                const option = new Option(value, key);
                series.add(option);
            }
        } else if (kategori === 'Adat Daerah') {
            karakter.disabled = true;  // Nonaktifkan karakter
            series.disabled = false;   // Aktifkan series
            // Isi dengan pilihan series untuk Adat Daerah
            for (const [key, value] of Object.entries(seriesOptions["Adat Daerah"])) {
                const option = new Option(value, key);
                series.add(option);
            }
        }
    }

    function handleSeriesChange() {
        const series = document.getElementById('series').value;
        const karakter = document.getElementById('karakter');

        // Kosongkan pilihan karakter
        karakter.innerHTML = '<option value="" selected>Pilih Karakter</option>';

        if (karakterOptions[series]) {
            karakter.disabled = false; // Aktifkan karakter jika ada pilihan
            // Isi dengan karakter yang sesuai dengan series yang dipilih
            karakterOptions[series].forEach(function(character) {
                const option = new Option(character, character.toLowerCase().replace(/\s+/g, '-'));
                karakter.add(option);
            });

            // Set nilai karakter yang sudah ada jika tersedia
            const existingKarakter = "<?php echo isset($data['karakter']) ? addslashes($data['karakter']) : ''; ?>";
                if (existingKarakter) {
                    // Cari option yang sesuai dengan nilai yang ada
                    for (let i = 0; i < karakter.options.length; i++) {
                        if (karakter.options[i].value === existingKarakter) {
                            karakter.value = existingKarakter;
                            break;
                        }
                    }
                }
        } else {
            karakter.disabled = true; // Nonaktifkan karakter jika series bukan dari Cosplay
        }
    }

    // Inisialisasi ketika halaman dimuat
    // Modifikasi fungsi handleSeriesChange untuk memastikan karakter terisi
function handleSeriesChange() {
    const series = document.getElementById('series').value;
    const karakter = document.getElementById('karakter');

    // Kosongkan pilihan karakter
    karakter.innerHTML = '<option value="" selected>Pilih Karakter</option>';

    if (karakterOptions[series]) {
        karakter.disabled = false;
        karakterOptions[series].forEach(function(character) {
            const option = new Option(character, character);
            karakter.add(option);
        });

        // Set nilai karakter yang sudah ada jika tersedia
        const existingKarakter = "<?php echo isset($data['karakter']) ? addslashes($data['karakter']) : ''; ?>";
        if (existingKarakter) {
            // Cari option yang sesuai dengan nilai yang ada
            for (let i = 0; i < karakter.options.length; i++) {
                if (karakter.options[i].value === existingKarakter) {
                    karakter.value = existingKarakter;
                    break;
                }
            }
        }
    } else {
        karakter.disabled = true;
    }
}

function toggleUkuranMeasurement(size) {
    const checkbox = document.getElementById('ukuran_' + size);
    const measurementContainer = document.getElementById('measurement-container');
    const existingForm = document.getElementById('measurement_' + size);
    
    if (checkbox.checked) {
        // Jika checkbox dicentang, tambahkan form measurement
        if (!existingForm) {
            const measurementForm = document.createElement('div');
            measurementForm.className = 'col-md-6 measurement-form';
            measurementForm.id = 'measurement_' + size;
            measurementForm.style.marginBottom = '15px';
            
            measurementForm.innerHTML = `
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Ukuran ${size}</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">LD (Lebar Dada) - cm</label>
                                    <input type="number" class="form-control" name="ld[]" 
                                            placeholder="Contoh: 50" min="0" 
                                            ${<?php echo $isCanceled ? "'readonly'" : "''"; ?>} required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">LP (Lebar Pinggang) - cm</label>
                                    <input type="number" class="form-control" name="lp[]" 
                                            placeholder="Contoh: 45" min="0" 
                                            ${<?php echo $isCanceled ? "'readonly'" : "''"; ?>} required>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            measurementContainer.appendChild(measurementForm);
        }
    } else {
        // Jika checkbox tidak dicentang, hapus form measurement
        if (existingForm) {
            existingForm.remove();
        }
    }
    
    validateUkuranSelection();
}

function syncUkuranWithMeasurements() {
    const checkedSizes = [];
    
    // Ambil ukuran yang dicentang dalam urutan yang sama dengan checkbox
    document.querySelectorAll('.ukuran-checkbox:checked').forEach(checkbox => {
        checkedSizes.push(checkbox.value);
    });
    
    // Hapus form measurement yang tidak diperlukan
    const measurementForms = document.querySelectorAll('.measurement-form');
    measurementForms.forEach(form => {
        const size = form.id.replace('measurement_', '');
        if (!checkedSizes.includes(size)) {
            form.remove();
        }
    });
    
    // Tambahkan form measurement untuk ukuran yang dicentang
    checkedSizes.forEach(size => {
        const measurementForm = document.getElementById('measurement_' + size);
        if (!measurementForm) {
            toggleUkuranMeasurement(size);
        }
    });
}

function reorderMeasurementForms() {
    const container = document.getElementById('measurement-container');
    const checkedSizes = [];
    
    // Ambil ukuran yang dicentang dalam urutan checkbox
    document.querySelectorAll('.ukuran-checkbox:checked').forEach(checkbox => {
        checkedSizes.push(checkbox.value);
    });
    
    // Pindahkan form dalam urutan yang benar
    checkedSizes.forEach(size => {
        const form = document.getElementById('measurement_' + size);
        if (form) {
            container.appendChild(form);
        }
    });
}

function validateUkuranSelection() {
    const checkedBoxes = document.querySelectorAll('.ukuran-checkbox:checked');
    const errorElement = document.getElementById('ukuran-error');
    
    if (checkedBoxes.length === 0) {
        if (!errorElement) {
            const error = document.createElement('div');
            error.id = 'ukuran-error';
            error.className = 'error-message';
            error.textContent = 'Pilih minimal satu ukuran';
            document.querySelector('.ukuran-checkbox-container').parentNode.appendChild(error);
        }
        return false;
    } else {
        if (errorElement) {
            errorElement.remove();
        }
        return true;
    }
}

function validateMeasurements() {
        const measurementForms = document.querySelectorAll('.measurement-form');
        let allValid = true;
        
        measurementForms.forEach(form => {
            const ldInput = form.querySelector('input[name="ld[]"]');
            const lpInput = form.querySelector('input[name="lp[]"]');
            
            if (!ldInput.value || ldInput.value <= 0) {
                allValid = false;
                ldInput.style.borderColor = '#dc3545';
            } else {
                ldInput.style.borderColor = '#ced4da';
            }
            
            if (!lpInput.value || lpInput.value <= 0) {
                allValid = false;
                lpInput.style.borderColor = '#dc3545';
            } else {
                lpInput.style.borderColor = '#ced4da';
            }
        });
        
        return allValid;
    }


// Modifikasi inisialisasi DOM
    document.addEventListener("DOMContentLoaded", function() {
        displayExistingPhotos();
        handleKategoriChange();

        const choosePhotoButton = document.getElementById('choosePhotoButton');
        const inputFoto = document.getElementById('input-foto');

        if (choosePhotoButton && inputFoto) {
            // Method 1: Klik langsung pada tombol
            choosePhotoButton.addEventListener('click', function(e) {
                console.log('Choose photo button clicked');
                inputFoto.click();
            });

            // Method 2: Pastikan input file juga bisa diklik
            inputFoto.addEventListener('click', function(e) {
                console.log('Input file clicked');
                e.stopPropagation();
            });

            // Method 3: Change event untuk preview
            inputFoto.addEventListener('change', function(e) {
                console.log('File selected:', e.target.files.length);
                previewImages(e);
            });
        } else {
            console.log('Photo elements not found:', {
                choosePhotoButton: !!choosePhotoButton,
                inputFoto: !!inputFoto
            });
        }
        
        // Set series dan karakter jika ada data
        const existingSeries = "<?php echo isset($data['series']) ? addslashes($data['series']) : ''; ?>";
        if (existingSeries) {
            document.getElementById('series').value = existingSeries;
            handleSeriesChange();
        }
        
        const existingKarakter = "<?php echo isset($data['karakter']) ? addslashes($data['karakter']) : ''; ?>";
        if (existingKarakter) {
            // Tunggu sebentar untuk memastikan options sudah terisi
            setTimeout(() => {
                document.getElementById('karakter').value = existingKarakter;
            }, 100);
        }

        document.querySelectorAll('.ukuran-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                syncUkuranWithMeasurements();
                reorderMeasurementForms();
                validateUkuranSelection();
            });
        });

        syncUkuranWithMeasurements();
        reorderMeasurementForms();

    //    updateMuatSampaiFromCheckbox();
    });


    document.querySelector("form").addEventListener("submit", function (e) {
        let allFilled = true;


        if (!validateUkuranSelection()) {
            allFilled = false;
        }

        const checkedSizes = [];
        document.querySelectorAll('.ukuran-checkbox:checked').forEach(checkbox => {
            checkedSizes.push(checkbox.value);
        });

        let measurementValid = true;
        checkedSizes.forEach((size, index) => {
            const form = document.getElementById('measurement_' + size);
            if (!form) {
                measurementValid = false;
                return;
            }
            
            const ldInput = form.querySelector('input[name="ld[]"]');
            const lpInput = form.querySelector('input[name="lp[]"]');
            
            if (!ldInput || !lpInput || !ldInput.value || !lpInput.value) {
                measurementValid = false;
                if (ldInput) ldInput.style.borderColor = '#dc3545';
                if (lpInput) lpInput.style.borderColor = '#dc3545';
            } else {
                if (ldInput) ldInput.style.borderColor = '#ced4da';
                if (lpInput) lpInput.style.borderColor = '#ced4da';
            }
        });

        // Validasi measurements
        if (!validateMeasurements()) {
            allFilled = false;
        }


    // Cek setiap field jika sudah terisi
    requiredFields.forEach(field => {
        let element = document.querySelector(`[name="${field}"]`);
        if (element) {
            if (element.tagName === "SELECT" && element.value === "") {
                allFilled = false;
            }
            if (element.tagName === "TEXTAREA" && element.value.trim() === "") {
                allFilled = false;
            }
            if (element.type === "radio" && !document.querySelector(`input[name="${field}"]:checked`)) {
                allFilled = false;
            }
            if (element.tagName === "INPUT" && element.type !== "radio" && element.value.trim() === "") {
                allFilled = false;
            }
        }
    });

            // // Jika ada field yang tidak terisi
            // if (!allFilled) {
            //     alert("Ada field yang tidak terisi. Harap isi semua field yang diperlukan.");
            //     e.preventDefault(); // Hentikan pengiriman form
            //     return; // Keluar dari fungsi
            // }
        
    });



// Ambil data foto yang sudah diupload sebelumnya dari PHP

const photoPreviewContainer = document.getElementById('photo-preview-container');
const defaultPhoto = document.getElementById('default-photo');
const choosePhotoButton = document.getElementById('choosePhotoButton');
const errorMessage = document.getElementById('errorMessage');

// Jika ada foto yang sudah diupload sebelumnya, tampilkan di container preview
function displayExistingPhotos() {
    const previewContainer = document.getElementById('photo-preview-container');
    
    if (existingImages.length > 0) {
        // Sembunyikan gambar default
        const defaultPhoto = document.getElementById('default-photo');
        if (defaultPhoto) defaultPhoto.style.display = 'none';
        
        // Update teks tombol
        const chooseButton = document.getElementById('choosePhotoButton');
        if (chooseButton) chooseButton.textContent = 'Tambah Foto';
        
        console.log('Existing photos displayed:', existingImages.length); // Debug
    }
}

document.addEventListener('DOMContentLoaded', function() {
    setTimeout(debugPhotoUpload, 1000);
});

function debugPhotoUpload() {
    const choosePhotoButton = document.getElementById('choosePhotoButton');
    const inputFoto = document.getElementById('input-foto');
    
    console.log('Debug Photo Upload:', {
        choosePhotoButton: {
            exists: !!choosePhotoButton,
            style: choosePhotoButton ? window.getComputedStyle(choosePhotoButton) : null,
            onclick: choosePhotoButton ? choosePhotoButton.onclick : null
        },
        inputFoto: {
            exists: !!inputFoto,
            style: inputFoto ? window.getComputedStyle(inputFoto) : null
        }
    });
}


// Fungsi untuk menghapus gambar
function removeImage(imgElement) {
    const filename = imgElement.dataset.filename;
    deletedPhotos.push(filename);
    document.getElementById('deletedPhotosInput').value = deletedPhotos.join(',');
    
    // Hapus dari DOM
    imgElement.parentElement.remove();
    
    // Periksa jika tidak ada gambar lagi
    if (photoPreviewContainer.children.length === 0 && defaultPhoto) {
        defaultPhoto.style.display = 'block';
        if (choosePhotoButton) choosePhotoButton.textContent = 'Pilih Foto';
    }
}

function removeExistingImage(button, filename) {
    if (confirm('Apakah Anda yakin ingin menghapus gambar ini?')) {
        // Tambahkan ke daftar foto yang dihapus
        deletedPhotos.push(filename);
        document.getElementById('deletedPhotosInput').value = deletedPhotos.join(',');
        
        // Hapus dari array existingImages
        existingImages = existingImages.filter(img => img !== filename);
        
        // Hapus elemen dari DOM
        const container = button.parentElement;
        container.remove();
        
        // Periksa jika tidak ada gambar lagi
        const previewContainer = document.getElementById('photo-preview-container');
        if (previewContainer.children.length === 0) {
            const defaultPhoto = document.getElementById('default-photo');
            if (defaultPhoto) defaultPhoto.style.display = 'block';
            const chooseButton = document.getElementById('choosePhotoButton');
            if (chooseButton) chooseButton.textContent = 'Pilih Foto';
        }
    }
}


function previewImages(event) {
    const files = event.target.files;
    const errorMessage = document.getElementById('errorMessage');
    errorMessage.textContent = '';

    console.log('Files selected:', files.length); // Debug

    // Validasi jumlah total file (existing + baru)
    const totalCurrentPhotos = document.querySelectorAll('#photo-preview-container img').length;
    const totalFiles = totalCurrentPhotos + files.length;
    
    if (totalFiles > 5) {
        errorMessage.textContent = 'Maksimal 5 foto yang bisa diunggah';
        event.target.value = '';
        return;
    }

    // Proses setiap file baru
    Array.from(files).forEach(file => {
        if (!file.type.startsWith('image/')) {
            errorMessage.textContent = 'Hanya file gambar yang diizinkan';
            return;
        }

        // Validasi ukuran file (max 2MB)
        if (file.size > 2 * 1024 * 1024) {
            errorMessage.textContent = 'Ukuran file maksimal 2MB';
            return;
        }

        const reader = new FileReader();
        reader.onload = function(e) {
            // Buat elemen untuk gambar baru
            const imgElement = document.createElement('img');
            imgElement.src = e.target.result;
            imgElement.classList.add('preview-image');
            imgElement.style.maxWidth = '150px';
            imgElement.style.maxHeight = '150px';
            imgElement.style.borderRadius = '5px';
            imgElement.dataset.tempId = 'temp_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            
            const removeButton = document.createElement('button');
            removeButton.innerHTML = '✖';
            removeButton.className = 'remove-button';
            removeButton.onclick = function() {
                removeNewImage(imgElement);
            };
            
            const container = document.createElement('div');
            container.style.position = 'relative';
            container.style.display = 'inline-block';
            container.style.margin = '5px';
            container.appendChild(imgElement);
            container.appendChild(removeButton);
            
            // Tambahkan ke container preview
            const previewContainer = document.getElementById('photo-preview-container');
            previewContainer.appendChild(container);
            
            // Tambahkan ke array newPhotos
            newPhotos.push({
                element: imgElement,
                file: file,
                tempId: imgElement.dataset.tempId
            });
            
            // Sembunyikan foto default jika ada
            const defaultPhoto = document.getElementById('default-photo');
            if (defaultPhoto) defaultPhoto.style.display = 'none';
            
            // Update teks tombol
            const chooseButton = document.getElementById('choosePhotoButton');
            if (chooseButton) chooseButton.textContent = 'Tambah Foto';
            
            console.log('New photo added to preview'); // Debug
        };
        reader.readAsDataURL(file);
    });
}



function removeNewImage(imgElement) {
    const tempId = imgElement.dataset.tempId;
    
    // Hapus dari array newPhotos
    newPhotos = newPhotos.filter(photo => photo.tempId !== tempId);
    
    // Hapus dari DOM
    const container = imgElement.parentElement;
    container.remove();
    
    // Periksa jika tidak ada gambar lagi
    const previewContainer = document.getElementById('photo-preview-container');
    const remainingImages = previewContainer.querySelectorAll('img');
    
    if (remainingImages.length === 0) {
        const defaultPhoto = document.getElementById('default-photo');
        if (defaultPhoto) defaultPhoto.style.display = 'block';
        const chooseButton = document.getElementById('choosePhotoButton');
        if (chooseButton) chooseButton.textContent = 'Pilih Foto';
    }
    
    console.log('New photo removed, remaining:', newPhotos.length); // Debug
}

// Fungsi untuk menambahkan lebih banyak foto
function addMorePhotos() {
    document.getElementById('input-foto').click(); // Memanggil dialog pemilihan file
}

//     function updateMuatSampaiOptions(ukuranPilihan) {
//     const muatSampaiSelect = document.getElementById("muatSampai");
//     const allOptions = Array.from(muatSampaiSelect.options);

//     allOptions.forEach(option => {
//         if (sizeHierarchy.indexOf(option.value) <= sizeHierarchy.indexOf(ukuranPilihan)) {
//             option.disabled = true; // Nonaktifkan jika ukuran lebih kecil atau sama
//         } else {
//             option.disabled = false; // Aktifkan ukuran yang lebih besar
//         }
//     });
// }

// Menangani perubahan ukuran
document.getElementById("ukuran").addEventListener("change", function() {

    <?php if ($isCanceled): ?>
        console.log("Kostum dalam status canceled - hanya harga dan stok yang bisa diubah");
        
        // Daftar field yang boleh diubah
        const allowedFields = ['hargaSewa', 'jumlahHari', 'stok'];
        
        // Kunci semua field kecuali yang diizinkan
        document.querySelectorAll('input, select, textarea').forEach(field => {
            if (!allowedFields.includes(field.id)) {
                field.setAttribute('readonly', 'readonly');
                if (field.tagName === 'SELECT') {
                    field.setAttribute('disabled', 'disabled');
                }
                
                // Tambahkan styling untuk menunjukkan field terkunci
                field.style.backgroundColor = '#f5f5f5';
                field.style.borderColor = '#ddd';
                field.style.cursor = 'not-allowed';
            }
        });
        
        // Validasi saat form submit
        document.querySelector("form").addEventListener("submit", function(e) {
            let unauthorizedChange = false;
            
            document.querySelectorAll('input, select, textarea').forEach(field => {
                if (!allowedFields.includes(field.id)) {
                    const originalValue = field.getAttribute('data-original-value') || field.value;
                    if (field.value !== originalValue) {
                        unauthorizedChange = true;
                    }
                }
            });
            
            if (unauthorizedChange) {
                e.preventDefault();
                alert('Anda hanya dapat mengubah harga dan stok untuk kostum yang dibatalkan.');
                return false;
            }
        });
    <?php endif; ?>

    const ukuranPilihan = this.value; // Ambil ukuran yang dipilih
    // updateMuatSampaiOptions(ukuranPilihan); // Perbarui opsi Muat Sampai
});

// Inisialisasi ketika halaman dimuat
document.addEventListener("DOMContentLoaded", function() {

    <?php if ($isCanceled): ?>
        console.log("Kostum dalam status canceled - hanya harga dan stok yang bisa diubah");
        
        // Daftar field yang boleh diubah
        const allowedFields = ['hargaSewa', 'jumlahHari', 'stok'];
        
        // Simpan nilai asli untuk semua field
        document.querySelectorAll('input, select, textarea').forEach(field => {
            field.setAttribute('data-original-value', field.value);
        });
        
        // Kunci semua field kecuali yang diizinkan
        document.querySelectorAll('input, select, textarea').forEach(field => {
            if (!allowedFields.includes(field.id)) {
                field.setAttribute('readonly', 'readonly');
                if (field.tagName === 'SELECT') {
                    field.setAttribute('disabled', 'disabled');
                }
                
                // Tambahkan styling untuk menunjukkan field terkunci
                field.style.backgroundColor = '#f5f5f5';
                field.style.borderColor = '#ddd';
                field.style.cursor = 'not-allowed';
            }
        });
        
        // Validasi saat form submit
        document.querySelector("form").addEventListener("submit", function(e) {
            let unauthorizedChange = false;
            
            document.querySelectorAll('input, select, textarea').forEach(field => {
                if (!allowedFields.includes(field.id)) {
                    const originalValue = field.getAttribute('data-original-value');
                    if (field.value !== originalValue) {
                        unauthorizedChange = true;
                        console.log(`Field ${field.id} diubah dari ${originalValue} menjadi ${field.value}`);
                    }
                }
            });
            
            if (unauthorizedChange) {
                e.preventDefault();
                alert('Anda hanya dapat mengubah harga dan stok untuk kostum yang dibatalkan.');
                return false;
            }
        });
    <?php endif; ?>

    handleKategoriChange();

    // Jika ada data yang sudah diisi, set series dan karakter
    <?php if (!empty($data['series'])): ?>
        document.getElementById('series').value = "<?php echo htmlspecialchars($data['series']); ?>";
        handleSeriesChange();
    <?php endif; ?>
    
    <?php if (!empty($data['karakter'])): ?>
        document.getElementById('karakter').value = "<?php echo htmlspecialchars($data['karakter']); ?>";
    <?php endif; ?>

    // updateMuatSampaiOptions(document.getElementById("ukuran").value); // update pada saat load

});

  
    document.getElementById("hargaSewa").addEventListener("input", function (e) {
        let value = e.target.value;
        // Hapus karakter non-angka
        e.target.value = value.replace(/[^0-9]/g, "");
    });

    document.getElementById("jumlahHari").addEventListener("input", function (e) {
        let value = e.target.value;
        // Hapus karakter non-angka
        e.target.value = value.replace(/[^0-9]/g, "");
    });

    document.querySelector("form").addEventListener("submit", function (e) {
        const hargaSewa = document.getElementById("hargaSewa").value;
        const jumlahHari = document.getElementById("jumlahHari").value;

        if (!hargaSewa || isNaN(hargaSewa) || hargaSewa <= 0) {
            alert("Harga Sewa harus diisi dengan angka positif.");
            e.preventDefault();
        }

        if (!jumlahHari || isNaN(jumlahHari) || jumlahHari <= 0) {
            alert("Jumlah Hari harus diisi dengan angka lebih besar dari nol.");
            e.preventDefault();
        }
    });



    </script>
</body>
</html>