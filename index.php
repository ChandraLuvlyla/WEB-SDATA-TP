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
    $kode = strtoupper(sanitize($_POST['kode']));
    $password = sanitize($_POST['password']);
    
    // Check for admin login
    if ($kode == 'ADMIN' && $password == 'admin123') {
        $_SESSION['user_type'] = 'admin';
        $_SESSION['admin_logged_in'] = true;
        header('Location: admin.php');
        exit();
    }
    
    // Check for customer login
    $customer = getCustomerByKode($kode);
    if ($customer) {
        if (verifyPassword($password, $customer['password'])) {
            $_SESSION['user_type'] = 'customer';
            $_SESSION['customer_id'] = $customer['id'];
            $_SESSION['customer_name'] = $customer['name'];
            $_SESSION['customer_kode'] = $customer['kode'];
            header('Location: customer.php');
            exit();
        } else {
            $error = "Kode pelanggan atau password salah!";
        }
    } else {
        $error = "Kode pelanggan tidak ditemukan!";
    }
}

// Handle customer registration
if (isset($_POST['register'])) {
    $name = sanitize($_POST['name']);
    $kode = strtoupper(sanitize($_POST['kode']));
    $password = sanitize($_POST['password']);
    $confirm_password = sanitize($_POST['confirm_password']);
    
    // Validate inputs
    if (strlen($name) < 3) {
        $error = "Nama minimal 3 karakter!";
    } elseif (strlen($kode) < 2) {
        $error = "Kode pelanggan minimal 2 karakter!";
    } elseif (strlen($password) < 4) {
        $error = "Password minimal 4 karakter!";
    } elseif ($password !== $confirm_password) {
        $error = "Password tidak cocok!";
    } elseif (!preg_match('/^[A-Za-z0-9]+$/', $kode)) {
        $error = "Kode pelanggan hanya boleh berisi huruf dan angka!";
    } else {
        // Check if Kode already exists
        if (getCustomerByKode($kode)) {
            $error = "Kode pelanggan sudah terdaftar!";
        } else {
            // Get all customers
            $customers = getJSONData(CUSTOMERS_FILE);
            $new_id = empty($customers) ? 1 : (max(array_column($customers, 'id')) + 1);
            
            // Add new customer
            $new_customer = [
                'id' => $new_id,
                'name' => $name,
                'kode' => $kode,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'total_spent' => 0,
                'visit_count' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $customers[] = $new_customer;
            saveJSONData(CUSTOMERS_FILE, $customers);
            
            $success = "Registrasi berhasil! Silakan login dengan kode: " . htmlspecialchars($kode);
        }
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
            
            <div class="tabs">
                <button class="tab-btn active" data-tab="login">Login</button>
                <button class="tab-btn" data-tab="register">Register Pelanggan</button>
            </div>
            
            <div class="tab-content">
                <!-- Login Form -->
                <div id="login-form" class="form-container active">
                    <h2>Login ke Sistem</h2>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="kode"><i class="fas fa-id-card"></i> Kode Pelanggan</label>
                            <input type="text" id="kode" name="kode" required placeholder="Masukkan kode pelanggan Anda">
                            <small class="form-text">Contoh: A01, A02, B01</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="password"><i class="fas fa-lock"></i> Password</label>
                            <input type="password" id="password" name="password" required placeholder="Masukkan password">
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-sign-in-alt"></i> Login
                        </button>
                        
                        <div class="login-info">
                            <h4><i class="fas fa-users"></i> Informasi Login Demo:</h4>
                            <p><strong>Admin:</strong><br>
                            Kode: <code>ADMIN</code><br>
                            Password: <code>admin123</code></p>
                            
                            <p><strong>Pelanggan Demo:</strong><br>
                            • Kode: <code>A01</code> (Mita) - Password: <code>3456</code><br>
                            • Kode: <code>A02</code> (Dini) - Password: <code>3421</code><br>
                            • Kode: <code>B01</code> (Budi) - Password: <code>budi123</code><br>
                            • Kode: <code>C01</code> (Sari) - Password: <code>sari456</code></p>
                        </div>
                    </form>
                </div>
                
                <!-- Registration Form -->
                <div id="register-form" class="form-container">
                    <h2>Registrasi Pelanggan Baru</h2>
                    <form method="POST" action="">
                        <input type="hidden" name="register" value="1">
                        
                        <div class="form-group">
                            <label for="reg_name"><i class="fas fa-user"></i> Nama Lengkap</label>
                            <input type="text" id="reg_name" name="name" required placeholder="Masukkan nama lengkap" minlength="3">
                        </div>
                        
                        <div class="form-group">
                            <label for="reg_kode"><i class="fas fa-id-card"></i> Kode Pelanggan</label>
                            <input type="text" id="reg_kode" name="kode" required placeholder="Contoh: D01, E02, F03" minlength="2" style="text-transform: uppercase;">
                            <small class="form-text">Gunakan kombinasi huruf dan angka</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="reg_password"><i class="fas fa-key"></i> Password</label>
                            <input type="password" id="reg_password" name="password" required placeholder="Minimal 4 karakter" minlength="4">
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password"><i class="fas fa-key"></i> Konfirmasi Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" required placeholder="Ketik ulang password">
                        </div>
                        
                        <button type="submit" class="btn btn-success btn-block">
                            <i class="fas fa-user-plus"></i> Daftar Sekarang
                        </button>
                    </form>
                </div>
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