<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offline - Rental Kostum</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #333, #555);
            color: white;
        }
        .offline-container {
            text-align: center;
            padding: 2rem;
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
            backdrop-filter: blur(10px);
        }
        .offline-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.7;
        }
        h1 { margin-bottom: 1rem; }
        p { margin-bottom: 2rem; opacity: 0.9; }
        .retry-btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
        }
        .retry-btn:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="offline-container">
        <div class="offline-icon">📡</div>
        <h1>Anda Sedang Offline</h1>
        <p>Koneksi internet tidak tersedia. Periksa koneksi Anda dan coba lagi.</p>
        <button class="retry-btn" onclick="window.location.reload()">
            Coba Lagi
        </button>
    </div>
</body>
</html>