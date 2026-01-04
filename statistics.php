<?php
// statistics.php - API untuk mendapatkan data statistik
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$type = $_GET['type'] ?? 'daily';
$period = $_GET['period'] ?? '7days';

$stats = getSalesStatistics($type);

// Get hourly data for today
$hourly_data = [];
for ($i = 0; $i < 24; $i++) {
    $hour = str_pad($i, 2, '0', STR_PAD_LEFT);
    $hourly_data[$hour] = 0;
}

$transactions = getJSONData(TRANSACTIONS_FILE);
$today = date('Y-m-d');

foreach ($transactions as $transaction) {
    if (date('Y-m-d', strtotime($transaction['order_date'])) == $today && $transaction['status'] == 'completed') {
        $hour = date('H', strtotime($transaction['order_date']));
        $hourly_data[$hour] += $transaction['total_amount'];
    }
}

$response = [
    'stats' => $stats,
    'hourly_data' => $hourly_data,
    'timestamp' => date('Y-m-d H:i:s')
];

echo json_encode($response);