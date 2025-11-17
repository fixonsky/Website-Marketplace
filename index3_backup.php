<?php 

session_start();
if (session_status() !== PHP_SESSION_ACTIVE) {
    die("Session failed to start.");
}

$conn = new mysqli("localhost", "root", "password123", "daftar_akun");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Pastikan id_user tersedia dalam sesi
if (!isset($_SESSION['id_user'])) {
    die("User ID tidak ditemukan. Harap login terlebih dahulu.");
}

$iduser = $_SESSION['id_user']; // Ambil user_id dari sesi

// Mengambil data berdasarkan user_id
$query = "SELECT * FROM form_katalog WHERE id_user = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $iduser); // Mengikat id_user ke query
$stmt->execute();
$result = $stmt->get_result();

if (!$result) {
    die("Query failed: " . $conn->error);
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
    <link rel="stylesheet" href="katalog.css">
    
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
}

.navbar .logo {
    font-size: 1.5rem;
    font-weight: bold;
}

.navbar .nav-links {
    display: flex;
}

.navbar .nav-links li {
    margin-left: 1rem;
}

.navbar .nav-links a {
    color: white;
    font-size: 1rem;
}

.container {
    display: flex;
    flex: 1;
}

.sidebar {
    background-color: #f4f4f4;
    width: 200px;
    padding: 1rem;
    border-right: 1px solid #ddd;
}

.sidebar h3 {
    margin-bottom: 1rem;
}

.sidebar ul {
    list-style: none;
}

.sidebar ul li {
    margin-bottom: 0.5rem;
}

.sidebar ul li a {
    color: #333;
    text-decoration: none;
}

.main-content {
    flex: 1;
    padding: 1rem;
}

.main-content h1 {
    margin-bottom: 1rem;
}

.main-content p {
    margin-bottom: 1rem;
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
            <h3>Sidebar</h3>
            <ul>
                <li><a href="INDEXXX.php">Link 1</a></li>
                <li><a href="INDEXXX2.php">Link 2</a></li>
                <li><a href="INDEXXX3.php">Link 3</a></li>
                <li><a href="#">Link 4</a></li>
            </ul>
        </aside>
        <main class="col-md-20 ml-sm-auto col-lg-30 px-4 content" role="main" style="margin-left : 10px; margin-top: -20px;">
        <div class="form-section mt-4">
        <div class="form-header" style="margin-bottom: 40px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
     <div>
      <button onclick="window.location.href='form_katalog.php'" class="btn btn-success me-2 menu-item">
       Tambah Kostum
      </button>
      <button class="btn btn-outline-success">
       Set Bundle
      </button>
     </div>
     <div class="d-flex align-items-center">
      <select aria-label="Default select example" class="form-select me-2">
       <option selected="">
        Tanggal Dibuat
       </option>
       <option value="1">
        Option 1
       </option>
       <option value="2">
        Option 2
       </option>
       <option value="3">
        Option 3
       </option>
      </select>
      <div class="input-group">
       <input aria-label="Cari Kostum" class="form-control" placeholder="Cari Kostum" type="text"/>
       <button class="btn btn-outline-secondary" type="button">
        <i class="fas fa-search">
        </i>
       </button>
      </div>
     </div>
    </div>
    <div class="card">
     <div class="card-body">
      <h5 class="card-title">
       Kelola Katalog
      </h5>
      <table class="table table-bordered">
    <thead>
      <tr>
        <th>No</th>
        <th style = "width: 180px;">Foto</th>
        <th>Kostum</th>
        <th>Harga</th>
        <th>Aksi</th>
      </tr>
    </thead>
    <tbody>
    <?php 
    $no = 1;
    while ($row = $result->fetch_assoc()) { ?>
                        <tr>
                            <td> <?php echo $no++; ?></td>
                            <td>
                                <img src="/TUGAS_AKHIR_KESYA/bibong/foto_kostum/<?php echo htmlspecialchars($row['foto_kostum']); ?>" 
                                    onerror="this.onerror=null; this.src='default.jpg';" 
                                    alt="Kostum" 
                                    class="img-thumbnail" />
                            </td >
                            <td>
                                <strong ><?php echo $row['judul_post']; ?></strong>
                                <br>
                                <small class="styleAja">
                                    <i class="fas fa-tags"></i> <?php echo $row['kategori']; ?>
                                </small>
                                <br>
                                <small class="styleAja">
                                    <i class="fas fa-vest"></i> <?php echo $row['series']; ?>
                                </small>
                                <br>
                                <small class="styleAja">
                                    <i class="fas fa-user"></i> <?php echo $row['karakter']; ?>
                                </small>
                                <br>
                                <small class="styleAja">
                                    <i class="fas fa-check"></i> <?php echo $row['ukuran']; ?>
                                </small>
                                <br>
                                <small class="styleAja">
                                    <i class="fas fa-venus-mars"></i> <?php echo $row['gender']; ?>
                                </small>
                            </td>
                          <td> Rp <?php echo number_format($row['harga_sewa'], 0, ',', '.'); ?> / 3 hari
                          </td>
                            <td>
                            <a class="btn btn-primary btn-sm" style="font-family:calibri;" >
                                <i class="fas fa-edit"></i> Edit
                            </a>
                                <button class="btn btn-danger btn-sm delete-btn" data-id="<?php echo $row['id']; ?>"><i class="fas fa-trash" >Hapus</i></button>
                            </td>
                        </tr>
                    <?php } ?>
    </tbody>
  </table>
     </div>
    </div>
</div>

    </div>
    
<script>
  

  document.addEventListener('DOMContentLoaded', function () {
    
    const editButtons = document.querySelectorAll('.btn-primary');

    editButtons.forEach(function (button) {
        button.addEventListener('click', function (event) {
            event.preventDefault(); // Mencegah navigasi default
            const page = this.getAttribute('data-target');
            const id = this.getAttribute('data-id');

            if (page && id) {
                const url = `${page}?id=${id}`;
                
                // Memuat halaman ke dalam kerangka admin
                fetch(url)
                    .then(response => response.text())
                    .then(html => {
                        document.querySelector('#content').innerHTML = html;
                    })
                    .catch(error => {
                        console.error('Error loading page:', error);

                        if (!kostumId) {
                            alert("ID tidak valid.");
                            return;
                        }
                    
                        // alert('Gagal memuat halaman. Silakan coba lagi.');
                    });
            } else {
                alert('Gagal mendapatkan data untuk mengedit.');
            }
        });
    });

    $(document).on('click', '.delete-btn', function () {
        const kostumId = $(this).data('id');
        const confirmation = confirm("Apakah Anda yakin ingin menghapus kostum ini?");

        if (confirmation) {
            $.ajax({
                url: 'proses/hapus_kostum.php',
                type: 'POST',
                data: { id: kostumId },
                success: function (response) {
                    if (response === 'success') {
                        alert('Kostum berhasil dihapus!');
                        location.reload();
                    } else {
                        alert('Terjadi kesalahan. Gagal menghapus kostum.');
                    }
                },
                error: function () {
                    alert('Terjadi kesalahan pada server.');
                }
            });
        }
    });
    
});


    
</script>
</body>
</html>