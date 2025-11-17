<html>
 <head>
    <meta name="viewport" content="width=device-width, initial-scale=1">

  <title>
   KeyRental
  </title>
  <link crossorigin="anonymous" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" rel="stylesheet"/>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
  <style>
   body {
            font-family: Arial, sans-serif;
            padding: 10px;
        }
        .navbar {
            background-color: #6a1b9a;
        }
        .navbar-brand {
            color: #ffeb3b !important;
        }
        .navbar-nav .nav-link {
            color: #ffffff !important;
        }
        .search-bar {
            margin: 20px 0;
        }

        .nav-item {
            padding-top: 10px;
        }
      
        .card {
            border: none;
            margin-bottom: 20px;
        }
        .card img {
            max-width: 100%;
            height: auto;
            border-radius: 10px;
        }

        @media (max-width: 576px) {
            .search-bar input, .search-bar select {
                width: 100%;
                margin-bottom: 10px;
            }
        
            .card-title {
                font-size: 1.2rem;
                font-weight: bold;
            }
            .card-text {
                font-size: 1rem;
            }
            .btn-more {
                background-color: #e3f2fd;
                color: #1e88e5;
                border: none;
                padding: 5px 10px;
                border-radius: 5px;
            }

            .catalog-title {
                color: #1e88e5;
                margin: 20px 0;
                font-size: 1.5rem;
                text-align: center;
            }
        }

  </style>
 </head>
 <body>
  <nav class="navbar navbar-expand-lg navbar-dark">
   <div class="container-fluid">
    <a class="navbar-brand" href="#" style="font-weight: bold;">
     KeyRental
    </a>
    <button aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation" class="navbar-toggler" data-bs-target="#navbarNav" data-bs-toggle="collapse" type="button">
     <span class="navbar-toggler-icon">
     </span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
     <ul class="navbar-nav ms-auto">
      <li class="nav-item">
       <a class="nav-link" href="#">
        Home
       </a>
      </li>
      <li class="nav-item">
       <a class="nav-link" href="#">
        Cari Kostum
       </a>
      </li>
      <li class="nav-item">
       <a class="nav-link" href="#">
        List Rentalan
       </a>
      </li>
      <li class="nav-item">
       <a class="nav-link" href="#">
        Jadwal Event
       </a>
      </li>
      <li class="nav-item">
       <a class="nav-link" href="login.php">
        Buat Akun
       </a>
      </li>
      <li class="nav-item dropdown" style="position: relative; margin-top: -10px;">
        <!-- Replace 'profile.jpg' with the actual profile image URL -->
        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown"  role="button" data-bs-toggle="dropdown" aria-expanded="false">
          <img src="profile.jpeg" alt="User Profile" class="rounded-circle" width="40" height="40">
        </a>
        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
          <li><a class="dropdown-item" href="#">Kelola Profil</a></li>
          <li><a class="dropdown-item" href="#">Logout</a></li>
        </ul>
      </li>
     </ul>
    </div>
   </div>
  </nav>
  <div class="container">
   <div class="text-center mt-4">
    <h1>
     Platform Sewa Cosplay Indonesia
    </h1>
    <p>
     <i class="fas fa-folder">
     </i>
     6251 Katalog
     <i class="fas fa-user">
     </i>
     376 Rental Owner
    </p>
   </div>
   <div class="search-bar text-center">
    <input class="form-control d-inline-block w-50" placeholder="Cari karakter, anime, atau game" type="text"/>
    <div class="d-inline-block">
     <select class="form-select d-inline-block w-auto">
      <option>
       Semua Provinsi
      </option>
     </select>
     <select class="form-select d-inline-block w-auto">
      <option>
       Semua Kota
      </option>
     </select>
     <select class="form-select d-inline-block w-auto">
      <option>
       Semua Ukuran
      </option>
     </select>
     <select class="form-select d-inline-block w-auto">
      <option>
       Semua Gender
      </option>
     </select>
    </div>
   </div>
   <h2 class="catalog-title">
    Katalog Terbaru
   </h2>
   <div class="row">
    <div class="col-12 col-sm-6 col-md-4 col-lg-3">
     <div class="card">
      <img alt="Wig Xiao Genshin" class="card-img-top" src="https://storage.googleapis.com/a1aa/image/5Ex4OmpFRuIIH9DeNeyCvmHktIlI93fbSGLt5htWfWH9KtyOB.jpg" width="100%"/>
      <div class="card-body">
       <h5 class="card-title">
        Rental Wig Xiao Genshin Impact Sewa
       </h5>
       <p class="card-text">
        Rp 40.000 / 3 hari
       </p>
       <p class="card-text">
        Mamnei
        <br/>
        Genshin Impact
        <br/>
        Xiao
        <br/>
        All Size
        <br/>
        Pria
       </p>
      </div>
     </div>
    </div>
    <div class="col-md-3">
     <div class="card">
      <img alt="Wig Eula Genshin" class="card-img-top" height="300" src="https://storage.googleapis.com/a1aa/image/c3Mm83lqOyJfdygbfqw1sD1KeQZAwvDQmb6GEeRDIvXJLtyOB.jpg" width="100%"/>
      <div class="card-body">
       <h5 class="card-title">
        Rental Wig Eula Lawrence Genshin Impact
       </h5>
       <p class="card-text">
        Rp 45.000 / 3 hari
       </p>
       <p class="card-text">
        Mamnei
        <br/>
        Genshin Impact
        <br/>
        Eula Lawrence
        <br/>
        All Size
        <br/>
        Wanita
       </p>
      </div>
     </div>
    </div>
    <div class="col-md-3">
     <div class="card">
      <img alt="Yuta Okkotsu" class="card-img-top" height="300" src="https://storage.googleapis.com/a1aa/image/eFxZmbywoa2OLaSJPmhZPmEZlV7vytmdCiYgSwcwueY0SrsTA.jpg" width="100%"/>
      <div class="card-body">
       <h5 class="card-title">
        Rental Cosplay Yuuta Okkotsu Jujutsu Kaisen
       </h5>
       <p class="card-text">
        Rp 35.000 / 3 hari
       </p>
       <p class="card-text">
        No Brand (Taobao)
        <br/>
        Jujutsu Kaisen
        <br/>
        Okkotsu Yuuta
        <br/>
        All Size
        <br/>
        Pria
       </p>
      </div>
     </div>
    </div>
    <div class="col-md-3">
     <div class="card">
      <img alt="Keqing Bride" class="card-img-top" height="300" src="https://storage.googleapis.com/a1aa/image/NsMfF5s5TqS0RyxecCzCLdWo2nubApuQzmB82hFpfxIqlWZnA.jpg" width="100%"/>
      <div class="card-body">
       <h5 class="card-title">
        Rental Cosplay Keqing Bride Fanart Genshin Impact
       </h5>
       <p class="card-text">
        Rp 90.000 / 3 hari
       </p>
       <p class="card-text">
        Maker Lokal
        <br/>
        Genshin Impact
        <br/>
        Keqing
        <br/>
        All Size
        <br/>
        Wanita
       </p>
      </div>
     </div>
    </div>
    <div class="col-md-3">
     <div class="card">
      <img alt="Yae Miko Maid" class="card-img-top" height="300" src="https://storage.googleapis.com/a1aa/image/7YleGmfK7SnHJ0cwKVWUbS838Usxu8RCCBGVCnEkTwc2SrsTA.jpg" width="100%"/>
      <div class="card-body">
       <h5 class="card-title">
        Rental Cosplay Yae Miko Maid Fanart Genshin Impact
       </h5>
       <p class="card-text">
        Rp 95.000 / 3 hari
       </p>
       <p class="card-text">
        Maker Lokal
        <br/>
        Genshin Impact
        <br/>
        Yae Miko
        <br/>
        All Size
        <br/>
        Wanita
       </p>
      </div>
     </div>
    </div>
    <div class="col-md-3">
     <div class="card">
      <img alt="Gawr Gura" class="card-img-top" height="300" src="https://storage.googleapis.com/a1aa/image/dfcsXZPQ6uSHDqDStIzKrAZ1elT1uAXoAhdMOm1KdXAxSrsTA.jpg" width="100%"/>
      <div class="card-body">
       <h5 class="card-title">
        Rental cosplay Gawr Gura Vtuber Hololive EN
       </h5>
       <p class="card-text">
        Rp 67.000 / 3 hari
       </p>
       <p class="card-text">
        Mango William
        <br/>
        Hololive
        <br/>
        Gawr Gura
        <br/>
        All Size
        <br/>
        Wanita
       </p>
      </div>
     </div>
    </div>
   </div>
   <div class="text-center">
    <button class="btn-more">
     Selengkapnya
    </button>
   </div>
  </div>
  <script>
      src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" 
</script>
 </body>
</html>
