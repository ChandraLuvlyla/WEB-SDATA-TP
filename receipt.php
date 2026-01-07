<?php
require_once 'config.php';

if (!isset($_GET['order'])) {
    header('Location: index.php');
    exit();
}

$order_number = sanitize($_GET['order']);
$transactions = getJSONData(TRANSACTIONS_FILE);
$order = null;

foreach ($transactions as $transaction) {
    if (isset($transaction['order_number']) && $transaction['order_number'] == $order_number) {
        $order = $transaction;
        break;
    }
}

if (!$order) {
    die("Pesanan tidak ditemukan!");
}

$customer_name = $order['customer_name'] ?? 'Pelanggan';
$customer_nik = $order['customer_nik'] ?? '-';
$total = $order['total_amount'] ?? 0;
$tax = $total * 0.1;
$grand_total = $total + $tax;
$status = $order['status'] ?? 'completed';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk Pesanan #<?php echo $order_number; ?></title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            margin: 0;
            padding: 20px;
            background: white;
        }
        
        .receipt {
            max-width: 300px;
            margin: 0 auto;
            border: 1px dashed #ccc;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        
        .header h1 {
            margin: 0;
            font-size: 24px;
            color: #ff8ba7;
        }
        
        .header p {
            margin: 5px 0;
            font-size: 12px;
        }
        
        .customer-info {
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px dashed #ccc;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        
        .info-row .label {
            font-weight: bold;
        }
        
        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 10px;
        }
        
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
        
        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .items {
            margin-bottom: 15px;
        }
        
        .item-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        .summary {
            border-top: 2px dashed #333;
            padding-top: 10px;
            margin-top: 15px;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .grand-total {
            font-size: 18px;
            color: #ff8ba7;
            border-top: 1px solid #333;
            padding-top: 5px;
            margin-top: 10px;
        }
        
        .footer {
            text-align: center;
            margin-top: 20px;
            font-size: 12px;
            color: #666;
            border-top: 1px dashed #ccc;
            padding-top: 10px;
        }
        
        .barcode {
            text-align: center;
            margin: 20px 0;
            font-family: 'Libre Barcode 128', cursive;
            font-size: 36px;
        }
        
        @media print {
            body {
                padding: 0;
            }
            
            .no-print {
                display: none;
            }
            
            .receipt {
                border: none;
                padding: 10px;
            }
        }
        
        .print-btn {
            text-align: center;
            margin: 20px auto;
        }
        
        .print-btn button {
            background: #36b37e;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        
        .print-btn button:hover {
            background: #2d9c6f;
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Barcode+128&display=swap" rel="stylesheet">
</head>
<body>
    <div class="print-btn no-print">
        <button onclick="window.print()">
            <i class="fas fa-print"></i> Cetak Struk
        </button>
        <button onclick="window.close()" style="background: #ff6b6b; margin-left: 10px;">
            <i class="fas fa-times"></i> Tutup
        </button>
    </div>
    
    <div class="receipt">
        <div class="header">
            <h1>DRIVE-THRU FAST FOOD</h1>
            <p>Jl. Makan Enak No. 123</p>
            <p>Telp: (021) 1234-5678</p>
        </div>
        
        <div class="customer-info">
            <div class="info-row">
                <span class="label">No. Pesanan:</span>
                <span>
                    #<?php echo $order_number; ?>
                    <span class="status-badge status-<?php echo $status; ?>">
                        <?php echo strtoupper($status); ?>
                    </span>
                </span>
            </div>
            <div class="info-row">
                <span class="label">Tanggal:</span>
                <span>
                    <?php echo date('d-m-Y H:i', strtotime($order['order_date'])); ?>
                </span>
            </div>
            <div class="info-row">
                <span class="label">Nama:</span>
                <span><?php echo htmlspecialchars($customer_name); ?></span>
            </div>
            <div class="info-row">
                <span class="label">NIK:</span>
                <span><?php echo htmlspecialchars($customer_nik); ?></span>
            </div>
        </div>
        
        <?php if (isset($order['food_count']) && isset($order['drink_count'])): ?>
        <div class="items">
            <div class="item-row">
                <span>Makanan:</span>
                <span><?php echo $order['food_count']; ?> item</span>
            </div>
            <div class="item-row">
                <span>Minuman:</span>
                <span><?php echo $order['drink_count']; ?> item</span>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="summary">
            <?php if ($total > 0): ?>
            <div class="total-row">
                <span>Subtotal:</span>
                <span>Rp <?php echo number_format($total, 0, ',', '.'); ?></span>
            </div>
            <div class="total-row">
                <span>Pajak (10%):</span>
                <span>Rp <?php echo number_format($tax, 0, ',', '.'); ?></span>
            </div>
            <div class="total-row grand-total">
                <span>TOTAL:</span>
                <span>Rp <?php echo number_format($grand_total, 0, ',', '.'); ?></span>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="barcode">
            *<?php echo $order_number; ?>*
        </div>
        
        <div class="footer">
            <p>Terima kasih atas kunjungan Anda!</p>
            <p>Struk ini sebagai bukti pembayaran</p>
            <p>www.drivethru-fastfood.com</p>
        </div>
    </div>
    
    <script>
        // Auto print setelah 1 detik
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 1000);
        };
    </script>
</body>
</html>