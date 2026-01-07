<?php
require_once 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit();
}

$action = $_GET['action'] ?? 'dashboard';
$date = $_GET['date'] ?? date('Y-m-d');

// Get daily report
$daily_report = getDailyReport($date);

// Get monthly report
function getMonthlyReport($year, $month) {
    $transactions = getJSONData(TRANSACTIONS_FILE);
    $report = [
        'year' => $year,
        'month' => $month,
        'total_transactions' => 0,
        'total_revenue' => 0,
        'total_food' => 0,
        'total_drinks' => 0,
        'daily_data' => []
    ];
    
    for ($day = 1; $day <= 31; $day++) {
        $report['daily_data'][$day] = [
            'transactions' => 0,
            'revenue' => 0,
            'food' => 0,
            'drinks' => 0
        ];
    }
    
    foreach ($transactions as $transaction) {
        if (($transaction['status'] ?? 'completed') !== 'completed') continue;
        
        $transaction_date = $transaction['order_date'] ?? date('Y-m-d H:i:s');
        $transaction_year = date('Y', strtotime($transaction_date));
        $transaction_month = date('m', strtotime($transaction_date));
        $transaction_day = date('d', strtotime($transaction_date));
        
        if ($transaction_year == $year && $transaction_month == $month) {
            $report['total_transactions']++;
            $report['total_revenue'] += ($transaction['total_amount'] ?? 0);
            $report['total_food'] += ($transaction['food_count'] ?? 0);
            $report['total_drinks'] += ($transaction['drink_count'] ?? 0);
            
            $day_num = (int)$transaction_day;
            if ($day_num >= 1 && $day_num <= 31) {
                $report['daily_data'][$day_num]['transactions']++;
                $report['daily_data'][$day_num]['revenue'] += ($transaction['total_amount'] ?? 0);
                $report['daily_data'][$day_num]['food'] += ($transaction['food_count'] ?? 0);
                $report['daily_data'][$day_num]['drinks'] += ($transaction['drink_count'] ?? 0);
            }
        }
    }
    
    return $report;
}

$current_year = date('Y');
$current_month = date('m');
$monthly_report = getMonthlyReport($current_year, $current_month);

// Get payment method statistics
function getPaymentMethodStats($date = null) {
    $transactions = getJSONData(TRANSACTIONS_FILE);
    $stats = [
        'tunai' => 0,
        'qris' => 0,
        'debit' => 0,
        'kredit' => 0,
        'ewallet' => 0
    ];
    
    foreach ($transactions as $transaction) {
        if (($transaction['status'] ?? 'completed') !== 'completed') continue;
        
        if ($date) {
            $transaction_date = date('Y-m-d', strtotime($transaction['order_date'] ?? date('Y-m-d')));
            if ($transaction_date != $date) {
                continue;
            }
        }
        
        $method = $transaction['payment_method'] ?? 'tunai';
        if (isset($stats[$method])) {
            $stats[$method]++;
        } else {
            $stats[$method] = 1;
        }
    }
    
    return $stats;
}

$payment_stats = getPaymentMethodStats($date);

// Get customer stats
function getCustomerStats() {
    global $CUSTOMERS;
    $transactions = getJSONData(TRANSACTIONS_FILE);
    
    $customer_data = [];
    foreach ($CUSTOMERS as $customer) {
        $customer_data[$customer['nik']] = [
            'nama' => $customer['nama'],
            'nik' => $customer['nik'],
            'total_transactions' => 0,
            'total_spent' => 0,
            'last_order' => null
        ];
    }
    
    foreach ($transactions as $transaction) {
        if (($transaction['status'] ?? 'completed') !== 'completed') continue;
        
        $nik = $transaction['customer_nik'] ?? '';
        if (isset($customer_data[$nik])) {
            $customer_data[$nik]['total_transactions']++;
            $customer_data[$nik]['total_spent'] += ($transaction['total_amount'] ?? 0);
            $customer_data[$nik]['last_order'] = $transaction['order_date'] ?? null;
        }
    }
    
    // Sort by total spent
    usort($customer_data, function($a, $b) {
        return $b['total_spent'] - $a['total_spent'];
    });
    
    return $customer_data;
}

$customer_stats = getCustomerStats();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Laporan - Sistem Drive-Thru</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .report-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .date-selector {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .chart-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card-report {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-top: 4px solid #36b37e;
        }
        
        .stat-card-report .value {
            font-size: 32px;
            font-weight: bold;
            color: #36b37e;
            margin-bottom: 10px;
        }
        
        .stat-card-report .label {
            color: #666;
            font-size: 14px;
        }
        
        .tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 2px solid #eaeaea;
        }
        
        .tab {
            padding: 10px 20px;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            font-size: 16px;
            font-weight: 600;
            color: #666;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .tab.active {
            color: #36b37e;
            border-bottom-color: #36b37e;
        }
        
        .tab:hover:not(.active) {
            color: #2d9c6f;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .table-responsive {
            overflow-x: auto;
            margin-bottom: 20px;
        }
        
        .monthly-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 10px;
            margin-top: 20px;
        }
        
        .day-cell {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            text-align: center;
            border: 1px solid #eaeaea;
        }
        
        .day-cell.today {
            background: #e3f2fd;
            border-color: #2196f3;
        }
        
        .day-cell .day-number {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .day-cell .day-revenue {
            font-size: 12px;
            color: #36b37e;
        }
        
        .payment-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .payment-stat {
            background: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .payment-stat .method {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .payment-stat .count {
            font-size: 24px;
            font-weight: bold;
            color: #36b37e;
        }
    </style>
</head>
<body class="admin-dashboard">
    <div class="container">
        <!-- Header -->
        <div class="dashboard-header">
            <h1><i class="fas fa-chart-bar"></i> Dashboard Laporan</h1>
            <div class="user-info">
                <a href="admin.php" class="btn btn-primary btn-sm" style="margin-right: 10px;">
                    <i class="fas fa-tachometer-alt"></i> Dashboard Utama
                </a>
                <a href="?logout=admin" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
        
        <!-- Report Header -->
        <div class="report-header">
            <h2><i class="fas fa-calendar-alt"></i> Laporan Penjualan</h2>
            <div class="date-selector">
                <form method="GET" action="" style="display: flex; gap: 10px; align-items: center;">
                    <label for="date" style="color: white;">Pilih Tanggal:</label>
                    <input type="date" id="date" name="date" value="<?php echo $date; ?>" class="form-control" style="max-width: 200px;">
                    <button type="submit" class="btn btn-light">
                        <i class="fas fa-search"></i> Tampilkan
                    </button>
                    <a href="reports.php" class="btn btn-light">
                        <i class="fas fa-sync"></i> Hari Ini
                    </a>
                </form>
            </div>
            <p style="margin-top: 10px; opacity: 0.9;">Tanggal: <?php echo date('d F Y', strtotime($date)); ?></p>
        </div>
        
        <!-- Tabs -->
        <div class="tabs">
            <button class="tab active" data-tab="daily">Harian</button>
            <button class="tab" data-tab="monthly">Bulanan</button>
            <button class="tab" data-tab="payment">Pembayaran</button>
            <button class="tab" data-tab="customers">Pelanggan</button>
            <button class="tab" data-tab="transactions">Detail Transaksi</button>
        </div>
        
        <!-- Daily Report Tab -->
        <div id="daily-tab" class="tab-content active">
            <!-- Stats Cards -->
            <div class="stats-cards">
                <div class="stat-card-report">
                    <div class="value"><?php echo $daily_report['total_transactions']; ?></div>
                    <div class="label">Total Transaksi</div>
                </div>
                <div class="stat-card-report">
                    <div class="value">Rp <?php echo number_format($daily_report['total_revenue'] * 1.1, 0, ',', '.'); ?></div>
                    <div class="label">Total Pendapatan</div>
                </div>
                <div class="stat-card-report">
                    <div class="value"><?php echo $daily_report['total_food']; ?></div>
                    <div class="label">Makanan Terjual</div>
                </div>
                <div class="stat-card-report">
                    <div class="value"><?php echo $daily_report['total_drinks']; ?></div>
                    <div class="label">Minuman Terjual</div>
                </div>
                <div class="stat-card-report">
                    <div class="value"><?php echo $daily_report['completed_orders']; ?></div>
                    <div class="label">Pesanan Selesai</div>
                </div>
                <div class="stat-card-report">
                    <div class="value"><?php echo $daily_report['cancelled_orders']; ?></div>
                    <div class="label">Pesanan Dibatalkan</div>
                </div>
            </div>
            
            <!-- Revenue Chart -->
            <div class="chart-container">
                <h3><i class="fas fa-chart-line"></i> Pendapatan Harian</h3>
                <canvas id="revenueChart" height="100"></canvas>
            </div>
            
            <!-- Transaction Chart -->
            <div class="chart-container">
                <h3><i class="fas fa-chart-pie"></i> Distribusi Pesanan</h3>
                <canvas id="transactionChart" height="100"></canvas>
            </div>
        </div>
        
        <!-- Monthly Report Tab -->
        <div id="monthly-tab" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-calendar"></i> Laporan Bulanan - <?php echo date('F Y', strtotime($current_year . '-' . $current_month . '-01')); ?></h3>
                </div>
                <div class="card-body">
                    <div class="stats-cards">
                        <div class="stat-card-report">
                            <div class="value"><?php echo $monthly_report['total_transactions']; ?></div>
                            <div class="label">Total Transaksi</div>
                        </div>
                        <div class="stat-card-report">
                            <div class="value">Rp <?php echo number_format($monthly_report['total_revenue'] * 1.1, 0, ',', '.'); ?></div>
                            <div class="label">Total Pendapatan</div>
                        </div>
                        <div class="stat-card-report">
                            <div class="value"><?php echo $monthly_report['total_food']; ?></div>
                            <div class="label">Total Makanan</div>
                        </div>
                        <div class="stat-card-report">
                            <div class="value"><?php echo $monthly_report['total_drinks']; ?></div>
                            <div class="label">Total Minuman</div>
                        </div>
                    </div>
                    
                    <h4 style="margin-top: 30px;">Transaksi Per Hari</h4>
                    <div class="monthly-grid">
                        <?php for ($day = 1; $day <= 31; $day++): 
                            $is_today = ($day == date('d') && $current_month == date('m') && $current_year == date('Y'));
                        ?>
                        <div class="day-cell <?php echo $is_today ? 'today' : ''; ?>">
                            <div class="day-number"><?php echo $day; ?></div>
                            <div class="day-transactions"><?php echo $monthly_report['daily_data'][$day]['transactions']; ?> transaksi</div>
                            <div class="day-revenue">Rp <?php echo number_format($monthly_report['daily_data'][$day]['revenue'], 0, ',', '.'); ?></div>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Payment Report Tab -->
        <div id="payment-tab" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-credit-card"></i> Statistik Metode Pembayaran</h3>
                </div>
                <div class="card-body">
                    <div class="payment-stats">
                        <?php foreach ($payment_stats as $method => $count): 
                            if ($count > 0):
                                $method_names = [
                                    'tunai' => 'Tunai',
                                    'qris' => 'QRIS',
                                    'debit' => 'Kartu Debit',
                                    'kredit' => 'Kartu Kredit',
                                    'ewallet' => 'E-Wallet'
                                ];
                        ?>
                        <div class="payment-stat">
                            <div class="method"><?php echo $method_names[$method] ?? ucfirst($method); ?></div>
                            <div class="count"><?php echo $count; ?></div>
                            <div class="percentage"><?php echo round(($count / array_sum($payment_stats)) * 100, 1); ?>%</div>
                        </div>
                        <?php endif; endforeach; ?>
                    </div>
                    
                    <div class="chart-container" style="margin-top: 30px;">
                        <h4>Distribusi Metode Pembayaran</h4>
                        <canvas id="paymentChart" height="100"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Customers Tab -->
        <div id="customers-tab" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-users"></i> Statistik Pelanggan</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Nama</th>
                                    <th>NIK</th>
                                    <th>Total Transaksi</th>
                                    <th>Total Belanja</th>
                                    <th>Rata-rata</th>
                                    <th>Pesanan Terakhir</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($customer_stats as $index => $customer): 
                                    $avg = $customer['total_transactions'] > 0 ? $customer['total_spent'] / $customer['total_transactions'] : 0;
                                ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($customer['nama']); ?></td>
                                    <td><?php echo $customer['nik']; ?></td>
                                    <td><?php echo $customer['total_transactions']; ?></td>
                                    <td>Rp <?php echo number_format($customer['total_spent'], 0, ',', '.'); ?></td>
                                    <td>Rp <?php echo number_format($avg, 0, ',', '.'); ?></td>
                                    <td><?php echo $customer['last_order'] ? date('d-m-Y', strtotime($customer['last_order'])) : '-'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Transactions Detail Tab -->
        <div id="transactions-tab" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-list"></i> Detail Transaksi - <?php echo date('d F Y', strtotime($date)); ?></h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($daily_report['transactions'])): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>No. Pesanan</th>
                                    <th>Pelanggan</th>
                                    <th>NIK</th>
                                    <th>Subtotal</th>
                                    <th>Pajak</th>
                                    <th>Total</th>
                                    <th>Metode Bayar</th>
                                    <th>Status</th>
                                    <th>Waktu</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($daily_report['transactions'] as $transaction): 
                                    $total_with_tax = ($transaction['total_amount'] ?? 0) * 1.1;
                                    $tax_amount = ($transaction['total_amount'] ?? 0) * 0.1;
                                ?>
                                <tr>
                                    <td>#<?php echo $transaction['order_number']; ?></td>
                                    <td><?php echo htmlspecialchars($transaction['customer_name']); ?></td>
                                    <td><?php echo $transaction['customer_nik']; ?></td>
                                    <td>Rp <?php echo number_format($transaction['total_amount'], 0, ',', '.'); ?></td>
                                    <td>Rp <?php echo number_format($tax_amount, 0, ',', '.'); ?></td>
                                    <td>Rp <?php echo number_format($total_with_tax, 0, ',', '.'); ?></td>
                                    <td>
                                        <span class="badge badge-info">
                                            <?php echo ucfirst($transaction['payment_method'] ?? 'tunai'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $status = $transaction['status'] ?? 'completed';
                                        $badge_class = 'badge-success';
                                        if ($status == 'cancelled') $badge_class = 'badge-danger';
                                        ?>
                                        <span class="badge <?php echo $badge_class; ?>">
                                            <?php echo ucfirst($status); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('H:i', strtotime($transaction['order_date'])); ?></td>
                                    <td>
                                        <a href="receipt.php?order=<?php echo $transaction['order_number']; ?>" target="_blank" class="btn btn-primary btn-sm">
                                            <i class="fas fa-print"></i> Struk
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 40px;">
                            <i class="fas fa-receipt" style="font-size: 60px; color: #ccc; margin-bottom: 20px;"></i>
                            <h4 style="color: #666;">Tidak ada transaksi pada tanggal ini</h4>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Export Options -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-download"></i> Export Laporan</h3>
            </div>
            <div class="card-body" style="display: flex; gap: 15px; flex-wrap: wrap;">
                <a href="process.php?action=export_transactions&date=<?php echo $date; ?>" class="btn btn-primary">
                    <i class="fas fa-file-excel"></i> Export Transaksi Harian
                </a>
                <a href="process.php?action=export_transactions" class="btn btn-success">
                    <i class="fas fa-file-excel"></i> Export Semua Transaksi
                </a>
                <a href="process.php?action=export_customers" class="btn btn-warning">
                    <i class="fas fa-users"></i> Export Data Pelanggan
                </a>
                <a href="process.php?action=generate_report&date=<?php echo $date; ?>" class="btn btn-info" target="_blank">
                    <i class="fas fa-print"></i> Cetak Laporan
                </a>
            </div>
        </div>
    </div>
    
    <script>
        // Tab functionality
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', function() {
                const tabId = this.getAttribute('data-tab');
                
                // Update active tab
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                // Show active content
                document.querySelectorAll('.tab-content').forEach(content => {
                    content.classList.remove('active');
                });
                document.getElementById(tabId + '-tab').classList.add('active');
            });
        });
        
        // Revenue Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        
        // Prepare hourly data for today
        const hours = ['06:00', '08:00', '10:00', '12:00', '14:00', '16:00', '18:00', '20:00', '22:00'];
        const revenueData = hours.map(() => Math.floor(Math.random() * 500000) + 100000);
        
        const revenueChart = new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: hours,
                datasets: [{
                    label: 'Pendapatan (Rp)',
                    data: revenueData,
                    borderColor: '#36b37e',
                    backgroundColor: 'rgba(54, 179, 126, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: true,
                        text: 'Pendapatan per Jam'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'Rp ' + value.toLocaleString('id-ID');
                            }
                        }
                    }
                }
            }
        });
        
        // Transaction Chart
        const transactionCtx = document.getElementById('transactionChart').getContext('2d');
        const transactionChart = new Chart(transactionCtx, {
            type: 'doughnut',
            data: {
                labels: ['Selesai', 'Dibatalkan'],
                datasets: [{
                    data: [<?php echo $daily_report['completed_orders']; ?>, <?php echo $daily_report['cancelled_orders']; ?>],
                    backgroundColor: [
                        '#36b37e',
                        '#ff6b6b'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: true,
                        text: 'Status Pesanan'
                    }
                }
            }
        });
        
        // Payment Chart
        const paymentCtx = document.getElementById('paymentChart').getContext('2d');
        const paymentChart = new Chart(paymentCtx, {
            type: 'pie',
            data: {
                labels: ['Tunai', 'QRIS', 'Kartu Debit', 'Kartu Kredit', 'E-Wallet'],
                datasets: [{
                    data: [
                        <?php echo $payment_stats['tunai']; ?>,
                        <?php echo $payment_stats['qris']; ?>,
                        <?php echo $payment_stats['debit']; ?>,
                        <?php echo $payment_stats['kredit']; ?>,
                        <?php echo $payment_stats['ewallet']; ?>
                    ],
                    backgroundColor: [
                        '#36b37e',
                        '#4d96ff',
                        '#ffd93d',
                        '#ff6b6b',
                        '#9d65c9'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right',
                    }
                }
            }
        });
        
        // Auto-refresh data every 5 minutes
        setInterval(() => {
            location.reload();
        }, 300000);
    </script>
</body>
</html>