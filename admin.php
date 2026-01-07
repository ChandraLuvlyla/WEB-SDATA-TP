<?php
require_once 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit();
}

// Show message if exists
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// Handle search
$search_results = [];
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['search'])) {
    $search_type = sanitize($_POST['search_type']);
    $search_term = sanitize($_POST['search_term']);
    
    $transactions = getJSONData(TRANSACTIONS_FILE);
    $filtered_results = [];
    
    foreach ($transactions as $transaction) {
        $match = false;
        
        switch ($search_type) {
            case 'order_number':
                $match = isset($transaction['order_number']) && $transaction['order_number'] == $search_term;
                break;
            case 'customer_name':
                $match = isset($transaction['customer_name']) && stripos($transaction['customer_name'], $search_term) !== false;
                break;
            case 'customer_nik':
                $match = isset($transaction['customer_nik']) && $transaction['customer_nik'] == $search_term;
                break;
            case 'date':
                $match = isset($transaction['order_date']) && date('Y-m-d', strtotime($transaction['order_date'])) == $search_term;
                break;
            case 'status':
                $status = $transaction['status'] ?? 'completed';
                $match = stripos($status, $search_term) !== false;
                break;
        }
        
        if ($match) {
            $filtered_results[] = $transaction;
        }
    }
    
    $search_results = $filtered_results;
}

// Get statistics
$transactions = getJSONData(TRANSACTIONS_FILE);
$stats = getSalesStatistics();

$total_transactions = $stats['total_transactions'];
$total_revenue = $stats['total_revenue'];
$total_food = $stats['total_food'];
$total_drinks = $stats['total_drinks'];
$completed_orders = $stats['completed_orders'];
$cancelled_orders = $stats['cancelled_orders'];
$average_order_value = $stats['average_order_value'];
$daily_transactions = $stats['daily_transactions'];
$today_revenue = $stats['today_revenue'];
$monthly_revenue = $stats['monthly_revenue'];
$total_customers = $stats['customers_count'];

// Get recent transactions (last 10)
usort($transactions, function($a, $b) {
    $dateA = isset($a['order_date']) ? strtotime($a['order_date']) : 0;
    $dateB = isset($b['order_date']) ? strtotime($b['order_date']) : 0;
    return $dateB - $dateA;
});
$recent_transactions = array_slice($transactions, 0, 10);

// Get unique customers from transactions for top customers
$customer_totals = [];
foreach ($transactions as $transaction) {
    if (isset($transaction['customer_nik']) && isset($transaction['customer_name'])) {
        $nik = $transaction['customer_nik'];
        $name = $transaction['customer_name'];
        $amount = $transaction['total_amount'] ?? 0;
        
        if (!isset($customer_totals[$nik])) {
            $customer_totals[$nik] = [
                'name' => $name,
                'nik' => $nik,
                'total_spent' => 0,
                'visit_count' => 0
            ];
        }
        
        $customer_totals[$nik]['total_spent'] += $amount;
        $customer_totals[$nik]['visit_count']++;
    }
}

// Sort customers by total spent
usort($customer_totals, function($a, $b) {
    return $b['total_spent'] - $a['total_spent'];
});
$top_customers = array_slice($customer_totals, 0, 5);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Sistem Drive-Thru</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        
        .spinner {
            border: 5px solid #f3f3f3;
            border-top: 5px solid #36b37e;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .action-buttons form {
            margin: 0;
        }
    </style>
</head>
<body class="admin-dashboard">
    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="spinner"></div>
    </div>
    
    <div class="container">
        <!-- Header -->
        <div class="dashboard-header">
            <h1><i class="fas fa-tachometer-alt"></i> Dashboard Admin</h1>
            <div class="user-info">
                <span><i class="fas fa-user-shield"></i> Administrator</span>
                <a href="?logout=admin" class="logout-btn" id="logoutBtn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
        
        <!-- Message Notification -->
        <?php if (isset($message)): ?>
            <div class="alert alert-<?php echo $message['type']; ?>">
                <i class="fas fa-<?php echo $message['type'] == 'success' ? 'check-circle' : 'info-circle'; ?>"></i>
                <?php echo $message['text']; ?>
                <button type="button" class="close-alert">&times;</button>
            </div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <i class="fas fa-receipt"></i>
                <div class="stat-value"><?php echo $total_transactions; ?></div>
                <div class="stat-label">Total Transaksi</div>
            </div>
            
            <div class="stat-card success">
                <i class="fas fa-money-bill-wave"></i>
                <div class="stat-value">Rp <?php echo number_format($total_revenue, 0, ',', '.'); ?></div>
                <div class="stat-label">Total Pemasukan</div>
            </div>
            
            <div class="stat-card warning">
                <i class="fas fa-users"></i>
                <div class="stat-value"><?php echo $total_customers; ?></div>
                <div class="stat-label">Total Pelanggan</div>
            </div>
            
            <div class="stat-card info">
                <i class="fas fa-shopping-cart"></i>
                <div class="stat-value"><?php echo $daily_transactions; ?></div>
                <div class="stat-label">Transaksi Hari Ini</div>
            </div>
        </div>
        
        <!-- Detailed Statistics -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-chart-line"></i> Statistik Penjualan Detail</h2>
            </div>
            <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); margin-bottom: 20px;">
                <div class="stat-card primary">
                    <i class="fas fa-shopping-cart"></i>
                    <div class="stat-value"><?php echo $daily_transactions; ?></div>
                    <div class="stat-label">Transaksi Hari Ini</div>
                </div>
                
                <div class="stat-card success">
                    <i class="fas fa-money-bill-wave"></i>
                    <div class="stat-value">Rp <?php echo number_format($today_revenue, 0, ',', '.'); ?></div>
                    <div class="stat-label">Pendapatan Hari Ini</div>
                </div>
                
                <div class="stat-card warning">
                    <i class="fas fa-calendar-alt"></i>
                    <div class="stat-value">Rp <?php echo number_format($monthly_revenue, 0, ',', '.'); ?></div>
                    <div class="stat-label">Pendapatan Bulan Ini</div>
                </div>
                
                <div class="stat-card info">
                    <i class="fas fa-chart-pie"></i>
                    <div class="stat-value">Rp <?php echo number_format($average_order_value, 0, ',', '.'); ?></div>
                    <div class="stat-label">Rata-rata Pesanan</div>
                </div>
            </div>
            
            <div class="stats-details" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 20px;">
                <div class="stat-detail">
                    <h4><i class="fas fa-check-circle"></i> Pesanan Selesai</h4>
                    <div class="progress" style="height: 10px; background: #f0f0f0; border-radius: 5px; margin: 10px 0;">
                        <div class="progress-bar" style="height: 100%; background: #28a745; border-radius: 5px; width: <?php echo ($completed_orders * 100) / max(1, $total_transactions); ?>%"></div>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span><?php echo $completed_orders; ?> pesanan</span>
                        <span><?php echo round(($completed_orders * 100) / max(1, $total_transactions), 1); ?>%</span>
                    </div>
                </div>
                
                <div class="stat-detail">
                    <h4><i class="fas fa-times-circle"></i> Pesanan Dibatalkan</h4>
                    <div class="progress" style="height: 10px; background: #f0f0f0; border-radius: 5px; margin: 10px 0;">
                        <div class="progress-bar" style="height: 100%; background: #dc3545; border-radius: 5px; width: <?php echo ($cancelled_orders * 100) / max(1, $total_transactions); ?>%"></div>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span><?php echo $cancelled_orders; ?> pesanan</span>
                        <span><?php echo round(($cancelled_orders * 100) / max(1, $total_transactions), 1); ?>%</span>
                    </div>
                </div>
                
                <div class="stat-detail">
                    <h4><i class="fas fa-utensils"></i> Kategori Penjualan</h4>
                    <div style="display: flex; gap: 10px; margin-top: 10px;">
                        <div style="flex: 1;">
                            <div style="background: #ff8ba7; color: white; padding: 5px; text-align: center; border-radius: 5px; margin-bottom: 5px;">
                                Makanan
                            </div>
                            <div style="text-align: center; font-weight: bold;">
                                <?php echo $stats['by_category']['makanan'] ?? 0; ?>%
                            </div>
                        </div>
                        <div style="flex: 1;">
                            <div style="background: #36b37e; color: white; padding: 5px; text-align: center; border-radius: 5px; margin-bottom: 5px;">
                                Minuman
                            </div>
                            <div style="text-align: center; font-weight: bold;">
                                <?php echo $stats['by_category']['minuman'] ?? 0; ?>%
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sales Chart -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-chart-bar"></i> Grafik Penjualan 7 Hari Terakhir</h2>
            </div>
            <div id="salesChart" style="height: 300px; padding: 20px;">
                <?php
                // Generate data for last 7 days
                $chart_data = [];
                for ($i = 6; $i >= 0; $i--) {
                    $date = date('Y-m-d', strtotime("-$i days"));
                    $day_name = date('D', strtotime($date));
                    
                    // Calculate revenue for this day
                    $day_revenue = 0;
                    $day_orders = 0;
                    
                    foreach ($transactions as $transaction) {
                        $transaction_date = date('Y-m-d', strtotime($transaction['order_date'] ?? date('Y-m-d')));
                        if ($transaction_date == $date && ($transaction['status'] ?? 'completed') == 'completed') {
                            $day_revenue += ($transaction['total_amount'] ?? 0);
                            $day_orders++;
                        }
                    }
                    
                    $chart_data[] = [
                        'date' => $day_name,
                        'revenue' => $day_revenue,
                        'orders' => $day_orders
                    ];
                }
                
                // Find max revenue for chart scaling
                $max_revenue = 0;
                if (!empty($chart_data)) {
                    $revenues = array_column($chart_data, 'revenue');
                    if (!empty($revenues)) {
                        $max_revenue = max($revenues);
                    }
                }
                ?>
                
                <div style="display: flex; height: 250px; align-items: flex-end; gap: 15px; justify-content: space-around;">
                    <?php foreach($chart_data as $data): 
                        $height = $max_revenue > 0 ? ($data['revenue'] / $max_revenue) * 200 : 10;
                    ?>
                    <div style="text-align: center; flex: 1;">
                        <div style="position: relative;">
                            <div style="background: linear-gradient(to top, #36b37e, #2d9c6f); width: 40px; height: <?php echo $height; ?>px; margin: 0 auto; border-radius: 5px 5px 0 0; position: relative;">
                                <div style="position: absolute; top: -25px; left: 50%; transform: translateX(-50%); background: #333; color: white; padding: 3px 8px; border-radius: 4px; font-size: 12px; white-space: nowrap;">
                                    Rp <?php echo number_format($data['revenue'], 0, ',', '.'); ?>
                                </div>
                            </div>
                        </div>
                        <div style="margin-top: 10px; font-weight: bold;"><?php echo $data['date']; ?></div>
                        <div style="font-size: 12px; color: #666;"><?php echo $data['orders']; ?> pesanan</div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Search Form -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-search"></i> Pencarian Pesanan</h2>
            </div>
            <div class="search-form">
                <form method="POST" action="">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="search_type">Cari Berdasarkan:</label>
                            <select id="search_type" name="search_type" class="form-control" required>
                                <option value="order_number">Nomor Pesanan</option>
                                <option value="customer_name">Nama Pelanggan</option>
                                <option value="customer_nik">NIK Pelanggan</option>
                                <option value="date">Tanggal (YYYY-MM-DD)</option>
                                <option value="status">Status</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="search_term">Kata Kunci:</label>
                            <input type="text" id="search_term" name="search_term" required placeholder="Masukkan kata kunci pencarian">
                        </div>
                        
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" name="search" class="btn btn-primary btn-block">
                                <i class="fas fa-search"></i> Cari
                            </button>
                        </div>
                    </div>
                </form>
                
                <?php if (!empty($search_results)): ?>
                <div class="table-responsive">
                    <h3>Hasil Pencarian:</h3>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>No. Pesanan</th>
                                <th>Nama Pelanggan</th>
                                <th>NIK</th>
                                <th>Total</th>
                                <th>Item</th>
                                <th>Tanggal</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($search_results as $row): 
                                $status = $row['status'] ?? 'completed';
                                $total_items = ($row['food_count'] ?? 0) + ($row['drink_count'] ?? 0);
                            ?>
                            <tr>
                                <td>#<?php echo $row['order_number'] ?? ''; ?></td>
                                <td><?php echo htmlspecialchars($row['customer_name'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($row['customer_nik'] ?? '-'); ?></td>
                                <td>Rp <?php echo number_format($row['total_amount'] ?? 0, 0, ',', '.'); ?></td>
                                <td><?php echo $total_items; ?> item</td>
                                <td><?php echo date('d-m-Y H:i', strtotime($row['order_date'] ?? 'now')); ?></td>
                                <td>
                                    <?php
                                    $badge_class = 'badge-primary';
                                    if ($status == 'cancelled') $badge_class = 'badge-danger';
                                    elseif ($status == 'pending') $badge_class = 'badge-warning';
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>">
                                        <?php echo ucfirst($status); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="receipt.php?order=<?php echo $row['order_number'] ?? ''; ?>" 
                                       target="_blank" class="btn btn-primary btn-sm">
                                        <i class="fas fa-print"></i> Struk
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['search'])): ?>
                    <div class="alert alert-danger">Tidak ditemukan hasil pencarian.</div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Transactions -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-history"></i> Transaksi Terbaru</h2>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>No. Pesanan</th>
                            <th>Nama Pelanggan</th>
                            <th>NIK</th>
                            <th>Total</th>
                            <th>Item</th>
                            <th>Tanggal</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($recent_transactions as $row): 
                            $status = $row['status'] ?? 'completed';
                            $total_items = ($row['food_count'] ?? 0) + ($row['drink_count'] ?? 0);
                        ?>
                        <tr>
                            <td>#<?php echo $row['order_number'] ?? ''; ?></td>
                            <td><?php echo htmlspecialchars($row['customer_name'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($row['customer_nik'] ?? '-'); ?></td>
                            <td>Rp <?php echo number_format($row['total_amount'] ?? 0, 0, ',', '.'); ?></td>
                            <td><?php echo $total_items; ?> item</td>
                            <td><?php echo date('d-m-Y H:i', strtotime($row['order_date'] ?? 'now')); ?></td>
                            <td>
                                <?php
                                $badge_class = 'badge-primary';
                                if ($status == 'cancelled') $badge_class = 'badge-danger';
                                elseif ($status == 'pending') $badge_class = 'badge-warning';
                                ?>
                                <span class="badge <?php echo $badge_class; ?>">
                                    <?php echo ucfirst($status); ?>
                                </span>
                            </td>
                            <td>
                                <a href="receipt.php?order=<?php echo $row['order_number'] ?? ''; ?>" 
                                   target="_blank" class="btn btn-primary btn-sm">
                                    <i class="fas fa-print"></i> Struk
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Top Customers -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-crown"></i> Top 5 Pelanggan</h2>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Nama</th>
                            <th>NIK</th>
                            <th>Total Belanja</th>
                            <th>Kunjungan</th>
                            <th>Rata-rata</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $rank = 1;
                        foreach($top_customers as $row): 
                        $average = (isset($row['visit_count']) && $row['visit_count'] > 0) ? 
                                   ($row['total_spent'] / $row['visit_count']) : 0;
                        ?>
                        <tr>
                            <td><span class="badge badge-primary">#<?php echo $rank++; ?></span></td>
                            <td><?php echo htmlspecialchars($row['name'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['nik'] ?? '-'); ?></td>
                            <td>Rp <?php echo number_format($row['total_spent'] ?? 0, 0, ',', '.'); ?></td>
                            <td><?php echo $row['visit_count'] ?? 1; ?>x</td>
                            <td>Rp <?php echo number_format($average, 0, ',', '.'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-bolt"></i> Aksi Cepat</h2>
            </div>
            <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                <a href="process.php?action=export_transactions" class="btn btn-primary">
                    <i class="fas fa-file-export"></i> Export Transaksi
                </a>
                <a href="process.php?action=export_customers" class="btn btn-success">
                    <i class="fas fa-users-cog"></i> Export Data Pelanggan
                </a>
                <a href="process.php?action=generate_report" class="btn btn-warning" target="_blank">
                    <i class="fas fa-chart-pie"></i> Laporan Harian
                </a>
                <a href="process.php?action=reset_data" class="btn btn-danger" onclick="return confirm('Reset semua data transaksi?')">
                    <i class="fas fa-trash-alt"></i> Reset Data
                </a>
                <a href="reports.php" class="btn btn-info">
                    <i class="fas fa-chart-bar"></i> Dashboard Laporan
                </a>
            </div>
        </div>
    </div>
    
    <script src="script.js"></script>
    <script>
        // Loading overlay functions
        function showLoading() {
            document.getElementById('loadingOverlay').style.display = 'flex';
        }
        
        function hideLoading() {
            document.getElementById('loadingOverlay').style.display = 'none';
        }
        
        // Handle logout confirmation
        document.getElementById('logoutBtn').addEventListener('click', function(e) {
            if (!confirm('Yakin ingin logout?')) {
                e.preventDefault();
            }
        });
        
        // Close alert message
        document.querySelectorAll('.close-alert').forEach(button => {
            button.addEventListener('click', function() {
                this.parentElement.style.display = 'none';
            });
        });
        
        // Auto-hide alert after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>