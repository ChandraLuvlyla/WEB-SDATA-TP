<?php
require_once 'config.php';

// Redirect if already logged in
if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'admin') {
    header('Location: admin.php');
    exit();
} elseif (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'customer') {
    header('Location: customer.php');
    exit();
}

$error = '';
$success = '';

// Show logout message
if (isset($_GET['message']) && $_GET['message'] == 'logout_success') {
    $type = $_GET['type'] ?? 'user';
    if ($type == 'admin') {
        $success = "Anda telah berhasil logout dari sistem admin!";
    } else {
        $success = "Anda telah berhasil logout!";
    }
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['register'])) {
    $nama = sanitize($_POST['nama']);
    $nik = sanitize($_POST['nik']);
    $password = sanitize($_POST['password']);
    
    // Check for admin login
    if ($nama == 'admin' && $password == ADMIN_PASSWORD) {
        $_SESSION['user_type'] = 'admin';
        $_SESSION['admin_logged_in'] = true;
        header('Location: admin.php');
        exit();
    }
    
    // Check for customer login
    $customer = verifyCustomer($nik, $password, $nama);
    if ($customer) {
        $_SESSION['user_type'] = 'customer';
        $_SESSION['customer_nik'] = $customer['nik'];
        $_SESSION['customer_name'] = $customer['nama'];
        header('Location: customer.php');
        exit();
    } else {
        $error = "Nama, NIK atau password salah!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Drive-Thru Fast Food - Login</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="login-page">
    <div class="container">
        <div class="login-box">
            <div class="logo-section">
                <h1><i class="fas fa-hamburger"></i> Drive-Thru Fast Food</h1>
                <p>Sistem pemesanan makanan cepat saji dengan fitur lengkap</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <div class="form-container active">
                <h2>Login ke Sistem</h2>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="nama"><i class="fas fa-user"></i> Nama Lengkap</label>
                        <input type="text" id="nama" name="nama" required placeholder="Masukkan nama lengkap Anda">
                    </div>
                    
                    <div class="form-group">
                        <label for="nik"><i class="fas fa-id-card"></i> Nomor NIK</label>
                        <input type="text" id="nik" name="nik" required placeholder="Masukkan 16 digit NIK">
                        <small class="form-text">Contoh: 1234567890123456</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="password"><i class="fas fa-lock"></i> Password</label>
                        <input type="password" id="password" name="password" required placeholder="Masukkan password (4 digit terakhir NIK)">
                        <small class="form-text">Password adalah 4 digit terakhir NIK Anda</small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                    
                    <div class="login-info">
                        <h4><i class="fas fa-users"></i> Informasi Login Demo:</h4>
                        <p><strong>Admin:</strong><br>
                        Nama: <code>admin</code><br>
                        Password: <code>admin123</code></p>
                        
                        <p><strong>Pelanggan Demo:</strong><br>
                        • Nama: <code>Mita</code><br>
                        • NIK: <code>1234567890123456</code><br>
                        • Password: <code>3456</code> (4 digit terakhir NIK)</p>
                        
                        <p><strong>Cara Login:</strong><br>
                        1. Masukkan nama lengkap Anda<br>
                        2. Masukkan 16 digit NIK<br>
                        3. Masukkan 4 digit terakhir NIK sebagai password</p>
                    </div>
                </form>
            </div>
            
            <div class="features">
                <h3><i class="fas fa-star"></i> Fitur Sistem:</h3>
                <div class="features-grid">
                    <div class="feature">
                        <i class="fas fa-shopping-cart"></i>
                        <span>Pemesanan Online</span>
                    </div>
                    <div class="feature">
                        <i class="fas fa-search"></i>
                        <span>Pencarian Pesanan</span>
                    </div>
                    <div class="feature">
                        <i class="fas fa-chart-bar"></i>
                        <span>Statistik Penjualan</span>
                    </div>
                    <div class="feature">
                        <i class="fas fa-receipt"></i>
                        <span>Cetak Struk</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="script.js"></script>
</body>
</html>