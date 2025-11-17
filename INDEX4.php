<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Order Table</title>
  <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Website dengan Navbar dan Sidebar</title>
    <link crossorigin="anonymous" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="transaksi.css">
    
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
                <li><a href="INDEX4.php">Link 4</a></li>
            </ul>
        </aside>
        <main style="margin-top: 50px; margin-bottom: 30px;">
            <div class="form-section mt-4">
                <div class="form-header" style="margin-bottom: 40px;">
                    <h2 class="form-title"> Lengkapi Informasi Toko Anda</h2>
                </div>
                <div class="table-container">
                    <table>
                    <thead>
                        <tr>
                        <th>No</th>
                        <th>Tanggal</th>
                        <th>Nama Penyewa</th>
                        <th>Kostum</th>
                        <th>Jumlah</th>
                        <th>Total</th>
                        <th>Bukti</th>
                        <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                        <td>1</td>
                        <td>24/11/2024</td>
                        <td>John Doe</td>
                        <td>Iron Man</td>
                        <td>2</td>
                        <td>Rp 600,000</td>
                        <td><a href="#">Lihat</a></td>
                        <td>
                            <button class="action-button" title="Detail">
                            <i class="fas fa-info-circle"></i>
                            </button>
                        </td>
                        </tr>
                        <tr>
                        <td>2</td>
                        <td>23/11/2024</td>
                        <td>Jane Smith</td>
                        <td>Elsa</td>
                        <td>1</td>
                        <td>Rp 300,000</td>
                        <td><a href="#">Lihat</a></td>
                        <td>
                            <button class="action-button" title="Detail">
                            <i class="fas fa-info-circle"></i>
                            </button>
                        </td>
                        </tr>
                        <tr>
                        <td>3</td>
                        <td>22/11/2024</td>
                        <td>Michael Johnson</td>
                        <td>Superman</td>
                        <td>3</td>
                        <td>Rp 900,000</td>
                        <td><a href="#">Lihat</a></td>
                        <td>
                            <button class="action-button" title="Detail">
                            <i class="fas fa-info-circle"></i>
                            </button>
                        </td>
                        </tr>
                    </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

  <!-- Font Awesome -->
  <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</body>
</html>
