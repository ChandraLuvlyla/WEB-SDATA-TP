<?php
require_once 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit();
}

$action = $_GET['action'] ?? '';
$date = $_GET['date'] ?? null;

switch($action) {
    case 'export_transactions':
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="transaksi_' . ($date ? $date : 'semua') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['No. Pesanan', 'Nama Pelanggan', 'Kode Pelanggan', 'Subtotal', 'Pajak', 'Total', 'Makanan', 'Minuman', 'Status', 'Metode Bayar', 'Tanggal']);
        
        $transactions = getJSONData(TRANSACTIONS_FILE);
        foreach($transactions as $row) {
            // Filter by date if specified
            if ($date && date('Y-m-d', strtotime($row['order_date'] ?? '')) != $date) {
                continue;
            }
            
            $tax = ($row['total_amount'] ?? 0) * 0.1;
            $total_with_tax = ($row['total_amount'] ?? 0) * 1.1;
            
            fputcsv($output, [
                $row['order_number'] ?? '',
                $row['customer_name'] ?? '',
                $row['customer_kode'] ?? '',
                $row['total_amount'] ?? 0,
                $tax,
                $total_with_tax,
                $row['food_count'] ?? 0,
                $row['drink_count'] ?? 0,
                $row['status'] ?? 'completed',
                $row['payment_method'] ?? 'tunai',
                $row['order_date'] ?? ''
            ]);
        }
        fclose($output);
        exit();
        
    case 'export_customers':
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="pelanggan_drivethru.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Nama', 'Kode Pelanggan', 'Total Belanja', 'Kunjungan', 'Tanggal Daftar']);
        
        $customers = getJSONData(CUSTOMERS_FILE);
        foreach($customers as $row) {
            fputcsv($output, [
                $row['name'] ?? '',
                $row['kode'] ?? '',
                $row['total_spent'] ?? 0,
                $row['visit_count'] ?? 1,
                $row['created_at'] ?? ''
            ]);
        }
        fclose($output);
        exit();
        
    case 'export_requests':
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="special_requests.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Request ID', 'No. Pesanan', 'Pelanggan', 'Kode Pelanggan', 'Total', 'Tipe', 'Status', 'Alasan', 'Tanggal Request', 'Tanggal Aksi']);
        
        $requests = getJSONData(ORDER_REQUESTS_FILE);
        foreach($requests as $row) {
            fputcsv($output, [
                $row['request_id'] ?? '',
                $row['order_number'] ?? '',
                $row['customer_name'] ?? '',
                $row['customer_kode'] ?? '',
                $row['total_amount'] ?? 0,
                $row['type'] ?? 'special_order',
                $row['status'] ?? '',
                $row['reason'] ?? '',
                $row['request_date'] ?? '',
                $row['action_date'] ?? ''
            ]);
        }
        fclose($output);
        exit();
        
    case 'generate_report':
        $report_date = $date ?? date('Y-m-d');
        $daily_report = getDailyReport($report_date);
        
        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <title>Laporan Harian - " . date('d F Y', strtotime($report_date)) . "</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                h1 { color: #333; border-bottom: 2px solid #36b37e; padding-bottom: 10px; }
                .report-section { margin: 20px 0; }
                table { width: 100%; border-collapse: collapse; margin: 10px 0; }
                th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
                th { background: #f8f9fa; }
                .total-row { font-weight: bold; background: #e9ecef; }
                .footer { margin-top: 30px; text-align: center; color: #666; font-size: 12px; }
                .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin: 20px 0; }
                .stat-box { background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center; border-left: 4px solid #36b37e; }
                .stat-value { font-size: 28px; font-weight: bold; color: #36b37e; }
                .stat-label { color: #666; margin-top: 5px; }
            </style>
        </head>
        <body>
            <h1>Laporan Harian - Drive-Thru Fast Food</h1>
            <p>Tanggal: " . date('d F Y', strtotime($report_date)) . "</p>
            <p>Dicetak: " . date('d F Y H:i') . "</p>
            
            <div class='stats-grid'>
                <div class='stat-box'>
                    <div class='stat-value'>" . $daily_report['total_transactions'] . "</div>
                    <div class='stat-label'>Total Transaksi</div>
                </div>
                <div class='stat-box'>
                    <div class='stat-value'>Rp " . number_format($daily_report['total_revenue'] * 1.1, 0, ',', '.') . "</div>
                    <div class='stat-label'>Total Pendapatan</div>
                </div>
                <div class='stat-box'>
                    <div class='stat-value'>" . $daily_report['completed_orders'] . "</div>
                    <div class='stat-label'>Pesanan Selesai</div>
                </div>
            </div>
            
            <div class='report-section'>
                <h2>Detail Transaksi</h2>
                <table>
                    <tr><th>No. Pesanan</th><th>Pelanggan</th><th>Kode</th><th>Subtotal</th><th>Pajak</th><th>Total</th><th>Status</th><th>Metode Bayar</th><th>Waktu</th></tr>
        ";
        
        foreach($daily_report['transactions'] as $transaction) {
            $tax = ($transaction['total_amount'] ?? 0) * 0.1;
            $total_with_tax = ($transaction['total_amount'] ?? 0) * 1.1;
            
            $html .= "<tr>
                <td>#{$transaction['order_number']}</td>
                <td>{$transaction['customer_name']}</td>
                <td>{$transaction['customer_kode']}</td>
                <td>Rp " . number_format($transaction['total_amount'], 0, ',', '.') . "</td>
                <td>Rp " . number_format($tax, 0, ',', '.') . "</td>
                <td>Rp " . number_format($total_with_tax, 0, ',', '.') . "</td>
                <td>" . ucfirst($transaction['status'] ?? 'completed') . "</td>
                <td>" . ucfirst($transaction['payment_method'] ?? 'tunai') . "</td>
                <td>" . date('H:i', strtotime($transaction['order_date'])) . "</td>
            </tr>";
        }
        
        $html .= "
                </table>
            </div>
            
            <div class='report-section'>
                <h2>Ringkasan Produk</h2>
                <table>
                    <tr><td>Total Makanan Terjual</td><td>" . $daily_report['total_food'] . " item</td></tr>
                    <tr><td>Total Minuman Terjual</td><td>" . $daily_report['total_drinks'] . " item</td></tr>
                    <tr class='total-row'><td>Total Item Terjual</td><td>" . ($daily_report['total_food'] + $daily_report['total_drinks']) . " item</td></tr>
                </table>
            </div>
            
            <div class='footer'>
                <p>Laporan ini dibuat otomatis oleh Sistem Drive-Thru Fast Food</p>
                <p>© " . date('Y') . " - www.drivethru-fastfood.com</p>
            </div>
        </body>
        </html>
        ";
        
        echo $html;
        exit();
        
    case 'reset_data':
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            // Reset transactions only
            resetSystemData(true, true);
            
            $_SESSION['message'] = [
                'type' => 'success',
                'text' => "Semua data telah direset!"
            ];
            
            header('Location: admin.php');
            exit();
        } else {
            // Show confirmation form
            echo '
            <!DOCTYPE html>
            <html>
            <head>
                <title>Reset Data</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    .confirmation { max-width: 500px; margin: 50px auto; padding: 30px; border: 2px solid #ff6b6b; border-radius: 10px; }
                    .btn { padding: 10px 20px; margin: 0 10px; border: none; border-radius: 5px; cursor: pointer; }
                    .btn-danger { background: #ff6b6b; color: white; }
                    .btn-primary { background: #36b37e; color: white; }
                </style>
            </head>
            <body>
                <div class="confirmation">
                    <h2>Konfirmasi Reset Data</h2>
                    <p><strong>Peringatan:</strong> Tindakan ini akan menghapus semua data transaksi dan requests.</p>
                    <p>Data yang akan direset:</p>
                    <ul>
                        <li>Semua transaksi</li>
                        <li>Semua item keranjang</li>
                        <li>Semua order requests</li>
                        <li>Total belanja pelanggan</li>
                        <li>Jumlah kunjungan pelanggan</li>
                    </ul>
                    <p>Data pelanggan dan menu tetap akan disimpan.</p>
                    <form method="POST" action="">
                        <button type="submit" class="btn btn-danger">Ya, Reset Semua Data</button>
                        <a href="admin.php" class="btn btn-primary">Batal</a>
                    </form>
                </div>
            </body>
            </html>';
            exit();
        }
        break;
        
    default:
        header('Location: admin.php');
        exit();
}