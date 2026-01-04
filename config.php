<?php
session_start();

// Konfigurasi path data JSON
define('DATA_DIR', 'data/');
define('CUSTOMERS_FILE', DATA_DIR . 'customers.json');
define('MENU_FILE', DATA_DIR . 'menu.json');
define('TRANSACTIONS_FILE', DATA_DIR . 'transactions.json');
define('CART_FILE', DATA_DIR . 'cart.json');
define('ORDER_REQUESTS_FILE', DATA_DIR . 'order_requests.json');

// Admin credentials
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD_HASH', password_hash('admin123', PASSWORD_DEFAULT));

// Helper functions
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function verifyPassword($password, $hashed_password) {
    return password_verify($password, $hashed_password);
}

function getJSONData($file) {
    if (!file_exists($file)) {
        return [];
    }
    $data = file_get_contents($file);
    $decoded = json_decode($data, true);
    return is_array($decoded) ? $decoded : [];
}

function saveJSONData($file, $data) {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents($file, $json) !== false;
}

function generateOrderNumber() {
    $transactions = getJSONData(TRANSACTIONS_FILE);
    if (empty($transactions)) return 1001;
    
    $order_numbers = [];
    foreach ($transactions as $transaction) {
        if (isset($transaction['order_number'])) {
            $order_numbers[] = $transaction['order_number'];
        }
    }
    
    return empty($order_numbers) ? 1001 : max($order_numbers) + 1;
}

function getCustomerByKode($kode) {
    $customers = getJSONData(CUSTOMERS_FILE);
    foreach ($customers as $customer) {
        if (isset($customer['kode']) && strtoupper($customer['kode']) === strtoupper($kode)) {
            return $customer;
        }
    }
    return null;
}

function getCartItems($session_id) {
    $cart = getJSONData(CART_FILE);
    $items = [];
    foreach ($cart as $item) {
        if (isset($item['session_id']) && $item['session_id'] === $session_id) {
            $items[] = $item;
        }
    }
    return $items;
}

function addCartItem($session_id, $item_id, $quantity) {
    $cart = getJSONData(CART_FILE);
    
    // Check if item exists
    foreach ($cart as &$item) {
        if (isset($item['session_id'], $item['item_id']) && 
            $item['session_id'] === $session_id && $item['item_id'] == $item_id) {
            $item['quantity'] += $quantity;
            saveJSONData(CART_FILE, $cart);
            return true;
        }
    }
    
    // Add new item
    $cart[] = [
        'session_id' => $session_id,
        'item_id' => $item_id,
        'quantity' => $quantity,
        'added_at' => date('Y-m-d H:i:s')
    ];
    
    saveJSONData(CART_FILE, $cart);
    return true;
}

function updateCartItem($session_id, $item_id, $quantity) {
    $cart = getJSONData(CART_FILE);
    $updated = false;
    
    foreach ($cart as $key => &$item) {
        if (isset($item['session_id'], $item['item_id']) && 
            $item['session_id'] === $session_id && $item['item_id'] == $item_id) {
            if ($quantity <= 0) {
                unset($cart[$key]);
            } else {
                $item['quantity'] = $quantity;
            }
            $updated = true;
            break;
        }
    }
    
    if ($updated) {
        saveJSONData(CART_FILE, array_values($cart));
    }
    return $updated;
}

function clearCart($session_id) {
    $cart = getJSONData(CART_FILE);
    $new_cart = [];
    
    foreach ($cart as $item) {
        if (isset($item['session_id']) && $item['session_id'] !== $session_id) {
            $new_cart[] = $item;
        }
    }
    
    saveJSONData(CART_FILE, $new_cart);
}

// Fungsi untuk langsung membuat transaksi (tanpa approval)
function createTransactionDirect($order_data) {
    $transactions = getJSONData(TRANSACTIONS_FILE);
    
    // Handle empty transactions array
    $new_id = 1;
    if (!empty($transactions)) {
        $ids = [];
        foreach ($transactions as $transaction) {
            if (isset($transaction['id'])) {
                $ids[] = $transaction['id'];
            }
        }
        if (!empty($ids)) {
            $new_id = max($ids) + 1;
        }
    }
    
    $transaction = [
        'id' => $new_id,
        'order_number' => $order_data['order_number'] ?? 0,
        'customer_name' => $order_data['customer_name'] ?? '',
        'customer_kode' => $order_data['customer_kode'] ?? '',
        'total_amount' => $order_data['total_amount'] ?? 0,
        'food_count' => $order_data['food_count'] ?? 0,
        'drink_count' => $order_data['drink_count'] ?? 0,
        'order_date' => date('Y-m-d H:i:s'),
        'status' => 'completed',
        'payment_method' => $order_data['payment_method'] ?? 'tunai',
        'payment_status' => 'paid'
    ];
    
    $transactions[] = $transaction;
    saveJSONData(TRANSACTIONS_FILE, $transactions);
    
    // Update customer stats
    $customers = getJSONData(CUSTOMERS_FILE);
    foreach ($customers as &$customer) {
        if (isset($customer['kode']) && $customer['kode'] === ($order_data['customer_kode'] ?? '')) {
            $customer['total_spent'] += ($order_data['total_amount'] ?? 0);
            $customer['visit_count']++;
            break;
        }
    }
    saveJSONData(CUSTOMERS_FILE, $customers);
    
    return $transaction;
}

// Fungsi untuk pembatalan langsung
function cancelOrderDirect($order_number, $reason, $customer_kode) {
    $transactions = getJSONData(TRANSACTIONS_FILE);
    $updated = false;
    
    foreach ($transactions as &$transaction) {
        if (isset($transaction['order_number'], $transaction['customer_kode'], $transaction['status']) && 
            $transaction['order_number'] == $order_number && 
            $transaction['customer_kode'] == $customer_kode &&
            $transaction['status'] == 'completed') {
            
            // Cek apakah pesanan masih bisa dibatalkan (misal: kurang dari 1 jam)
            $order_time = strtotime($transaction['order_date'] ?? 'now');
            $current_time = time();
            $time_diff = $current_time - $order_time;
            
            if ($time_diff > 3600) { // Lebih dari 1 jam
                return false; // Tidak bisa dibatalkan
            }
            
            $transaction['status'] = 'cancelled';
            $transaction['cancel_reason'] = $reason;
            $transaction['cancel_date'] = date('Y-m-d H:i:s');
            $updated = true;
            break;
        }
    }
    
    if ($updated) {
        saveJSONData(TRANSACTIONS_FILE, $transactions);
        
        // Kurangi statistik pelanggan
        $customers = getJSONData(CUSTOMERS_FILE);
        foreach ($customers as &$customer) {
            if (isset($customer['kode']) && $customer['kode'] === $customer_kode) {
                // Cari transaksi yang dibatalkan
                foreach ($transactions as $transaction) {
                    if (isset($transaction['order_number']) && $transaction['order_number'] == $order_number) {
                        $customer['total_spent'] -= ($transaction['total_amount'] ?? 0);
                        $customer['total_spent'] = max(0, $customer['total_spent'] ?? 0); // Jangan sampai negatif
                        break;
                    }
                }
                break;
            }
        }
        saveJSONData(CUSTOMERS_FILE, $customers);
        
        return true;
    }
    
    return false;
}

// Fungsi untuk mendapatkan statistik penjualan
function getSalesStatistics($period = 'daily') {
    $transactions = getJSONData(TRANSACTIONS_FILE);
    
    $stats = [
        'total_transactions' => 0,
        'total_revenue' => 0,
        'total_food' => 0,
        'total_drinks' => 0,
        'completed_orders' => 0,
        'cancelled_orders' => 0,
        'average_order_value' => 0,
        'by_category' => [
            'makanan' => 0,
            'minuman' => 0
        ]
    ];
    
    $today = date('Y-m-d');
    $current_month = date('Y-m');
    $today_revenue = 0;
    $monthly_revenue = 0;
    $daily_transactions = 0;
    
    foreach ($transactions as $transaction) {
        // Total transaksi (semua status)
        $stats['total_transactions']++;
        
        // Hanya hitung revenue dan item dari transaksi yang completed
        $status = $transaction['status'] ?? 'completed';
        if ($status == 'completed') {
            $stats['total_revenue'] += ($transaction['total_amount'] ?? 0);
            $stats['total_food'] += ($transaction['food_count'] ?? 0);
            $stats['total_drinks'] += ($transaction['drink_count'] ?? 0);
            $stats['completed_orders']++;
            
            // Statistik harian (hanya completed)
            $order_date = date('Y-m-d', strtotime($transaction['order_date'] ?? date('Y-m-d')));
            if ($order_date == $today) {
                $today_revenue += ($transaction['total_amount'] ?? 0);
                $daily_transactions++;
            }
            
            // Statistik bulanan (hanya completed)
            $order_month = date('Y-m', strtotime($transaction['order_date'] ?? date('Y-m-d')));
            if ($order_month == $current_month) {
                $monthly_revenue += ($transaction['total_amount'] ?? 0);
            }
            
        } elseif ($status == 'cancelled') {
            $stats['cancelled_orders']++;
        }
    }
    
    // Rata-rata nilai pesanan (hanya yang completed)
    if ($stats['completed_orders'] > 0) {
        $stats['average_order_value'] = $stats['total_revenue'] / $stats['completed_orders'];
    }
    
    // Statistik per kategori (hanya yang completed)
    $total_items = max(1, ($stats['total_food'] + $stats['total_drinks']));
    if ($total_items > 0) {
        $stats['by_category']['makanan'] = round(($stats['total_food'] * 100) / $total_items, 1);
        $stats['by_category']['minuman'] = round(($stats['total_drinks'] * 100) / $total_items, 1);
    }
    
    // Tambahkan statistik periodik
    $stats['today_revenue'] = $today_revenue;
    $stats['monthly_revenue'] = $monthly_revenue;
    $stats['daily_transactions'] = $daily_transactions;
    
    return $stats;
}

// Fungsi untuk laporan harian
function getDailyReport($date) {
    $transactions = getJSONData(TRANSACTIONS_FILE);
    
    $report = [
        'date' => $date,
        'total_transactions' => 0,
        'total_revenue' => 0,
        'total_food' => 0,
        'total_drinks' => 0,
        'completed_orders' => 0,
        'cancelled_orders' => 0,
        'transactions' => []
    ];
    
    foreach ($transactions as $transaction) {
        $transaction_date = date('Y-m-d', strtotime($transaction['order_date'] ?? date('Y-m-d')));
        
        if ($transaction_date == $date) {
            $report['total_transactions']++;
            $report['transactions'][] = $transaction;
            
            if (($transaction['status'] ?? 'completed') == 'completed') {
                $report['total_revenue'] += ($transaction['total_amount'] ?? 0);
                $report['total_food'] += ($transaction['food_count'] ?? 0);
                $report['total_drinks'] += ($transaction['drink_count'] ?? 0);
                $report['completed_orders']++;
            } elseif (($transaction['status'] ?? 'completed') == 'cancelled') {
                $report['cancelled_orders']++;
            }
        }
    }
    
    return $report;
}

// Fungsi untuk menyetujui request pembatalan
function approveCancelRequest($request_id) {
    $request = getOrderRequest($request_id);
    if (!$request) return false;
    
    // Update status transaksi yang sesuai
    $transactions = getJSONData(TRANSACTIONS_FILE);
    $updated = false;
    
    foreach ($transactions as &$transaction) {
        if (isset($transaction['order_number']) && 
            $transaction['order_number'] == ($request['order_number'] ?? '') &&
            isset($transaction['status']) && 
            $transaction['status'] == 'completed') {
            
            $transaction['status'] = 'cancelled';
            $transaction['cancel_reason'] = $request['reason'] ?? 'Dibatalkan oleh admin';
            $transaction['cancel_date'] = date('Y-m-d H:i:s');
            $updated = true;
            break;
        }
    }
    
    if ($updated) {
        saveJSONData(TRANSACTIONS_FILE, $transactions);
        
        // Update customer stats
        $customers = getJSONData(CUSTOMERS_FILE);
        foreach ($customers as &$customer) {
            if (isset($customer['kode']) && $customer['kode'] === ($request['customer_kode'] ?? '')) {
                // Kurangi total spent dari transaksi yang dibatalkan
                foreach ($transactions as $transaction) {
                    if (isset($transaction['order_number']) && $transaction['order_number'] == ($request['order_number'] ?? '')) {
                        $customer['total_spent'] -= ($transaction['total_amount'] ?? 0);
                        break;
                    }
                }
                break;
            }
        }
        saveJSONData(CUSTOMERS_FILE, $customers);
        
        // Update request status
        updateOrderRequestStatus($request_id, 'approved', 'Pembatalan disetujui', 'Admin');
        
        return true;
    }
    
    return false;
}

// Fungsi untuk order requests (untuk kebutuhan khusus yang butuh approval)
function addOrderRequest($order_data, $type = 'special_order') {
    $requests = getJSONData(ORDER_REQUESTS_FILE);
    
    // Generate request ID
    $request_id = 1;
    if (!empty($requests)) {
        $ids = [];
        foreach ($requests as $request) {
            if (isset($request['request_id'])) {
                $ids[] = $request['request_id'];
            }
        }
        if (!empty($ids)) {
            $request_id = max($ids) + 1;
        }
    }
    
    $request = [
        'request_id' => $request_id,
        'order_number' => $order_data['order_number'] ?? 0,
        'customer_name' => $order_data['customer_name'] ?? '',
        'customer_kode' => $order_data['customer_kode'] ?? '',
        'total_amount' => $order_data['total_amount'] ?? 0,
        'food_count' => $order_data['food_count'] ?? 0,
        'drink_count' => $order_data['drink_count'] ?? 0,
        'request_date' => date('Y-m-d H:i:s'),
        'type' => $type,
        'status' => 'pending',
        'action_by' => '',
        'action_date' => '',
        'reason' => $order_data['reason'] ?? ''
    ];
    
    $requests[] = $request;
    saveJSONData(ORDER_REQUESTS_FILE, $requests);
    return $request_id;
}

function updateOrderRequestStatus($request_id, $status, $reason = '', $action_by = '') {
    $requests = getJSONData(ORDER_REQUESTS_FILE);
    $updated = false;
    
    foreach ($requests as &$request) {
        if (isset($request['request_id']) && $request['request_id'] == $request_id) {
            $request['status'] = $status;
            $request['reason'] = $reason;
            $request['action_by'] = $action_by;
            $request['action_date'] = date('Y-m-d H:i:s');
            $updated = true;
            break;
        }
    }
    
    if ($updated) {
        saveJSONData(ORDER_REQUESTS_FILE, $requests);
    }
    return $updated;
}

function getOrderRequest($request_id) {
    $requests = getJSONData(ORDER_REQUESTS_FILE);
    foreach ($requests as $request) {
        if (isset($request['request_id']) && $request['request_id'] == $request_id) {
            return $request;
        }
    }
    return null;
}

function getOrderRequestsByStatus($status) {
    $requests = getJSONData(ORDER_REQUESTS_FILE);
    $filtered = [];
    
    foreach ($requests as $request) {
        if (isset($request['status']) && $request['status'] == $status) {
            $filtered[] = $request;
        }
    }
    
    return $filtered;
}

// Fungsi untuk mendapatkan semua transaksi dengan filter
function getTransactions($filters = []) {
    $transactions = getJSONData(TRANSACTIONS_FILE);
    
    if (empty($filters)) {
        return $transactions;
    }
    
    $filtered = [];
    foreach ($transactions as $transaction) {
        $match = true;
        
        if (isset($filters['date'])) {
            $order_date = date('Y-m-d', strtotime($transaction['order_date'] ?? date('Y-m-d')));
            if ($order_date != $filters['date']) {
                $match = false;
            }
        }
        
        if (isset($filters['status']) && isset($transaction['status'])) {
            if ($transaction['status'] != $filters['status']) {
                $match = false;
            }
        }
        
        if (isset($filters['customer_kode']) && isset($transaction['customer_kode'])) {
            if ($transaction['customer_kode'] != $filters['customer_kode']) {
                $match = false;
            }
        }
        
        if ($match) {
            $filtered[] = $transaction;
        }
    }
    
    return $filtered;
}

// Fungsi untuk reset data dengan aman
function resetSystemData($preserve_customers = true, $preserve_menu = true) {
    // Reset transactions
    saveJSONData(TRANSACTIONS_FILE, []);
    
    // Reset cart
    saveJSONData(CART_FILE, []);
    
    // Reset order requests
    saveJSONData(ORDER_REQUESTS_FILE, []);
    
    // Reset customer stats jika preserve_customers = true
    if ($preserve_customers) {
        $customers = getJSONData(CUSTOMERS_FILE);
        foreach ($customers as &$customer) {
            $customer['total_spent'] = 0;
            $customer['visit_count'] = 1;
        }
        saveJSONData(CUSTOMERS_FILE, $customers);
    }
    
    return true;
}

// Admin authentication function
function adminLogin($username, $password) {
    if ($username === ADMIN_USERNAME && password_verify($password, ADMIN_PASSWORD_HASH)) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['user_type'] = 'admin';
        $_SESSION['admin_username'] = $username;
        return true;
    }
    return false;
}

// Check if admin is logged in
function isAdminLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true && 
           isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
}

// Protect admin pages
function requireAdminLogin() {
    if (!isAdminLoggedIn()) {
        header('Location: index.php?message=access_denied&type=admin');
        exit();
    }
}

// Protect customer pages
function requireCustomerLogin() {
    if (!isset($_SESSION['customer_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'customer') {
        header('Location: customer_login.php?message=login_required');
        exit();
    }
}

// Initialize data files if they don't exist
function initializeData() {
    if (!file_exists(DATA_DIR)) {
        mkdir(DATA_DIR, 0755, true);
    }
    
    // Initialize customers
    if (!file_exists(CUSTOMERS_FILE)) {
        $customers = [
            [
                'id' => 1,
                'name' => 'Mita',
                'kode' => 'A01',
                'password' => password_hash('3456', PASSWORD_DEFAULT),
                'total_spent' => 150000,
                'visit_count' => 5,
                'created_at' => date('Y-m-d H:i:s')
            ],
            [
                'id' => 2,
                'name' => 'Dini',
                'kode' => 'A02',
                'password' => password_hash('3421', PASSWORD_DEFAULT),
                'total_spent' => 200000,
                'visit_count' => 8,
                'created_at' => date('Y-m-d H:i:s')
            ],
            [
                'id' => 3,
                'name' => 'Budi',
                'kode' => 'B01',
                'password' => password_hash('budi123', PASSWORD_DEFAULT),
                'total_spent' => 75000,
                'visit_count' => 3,
                'created_at' => date('Y-m-d H:i:s')
            ],
            [
                'id' => 4,
                'name' => 'Sari',
                'kode' => 'C01',
                'password' => password_hash('sari456', PASSWORD_DEFAULT),
                'total_spent' => 120000,
                'visit_count' => 6,
                'created_at' => date('Y-m-d H:i:s')
            ]
        ];
        saveJSONData(CUSTOMERS_FILE, $customers);
    }
    
    // Initialize menu
    if (!file_exists(MENU_FILE)) {
        $menu = [
            [
                'id' => 1,
                'name' => 'Burger Special',
                'price' => 35000,
                'category' => 'makanan',
                'cost' => 15000
            ],
            [
                'id' => 2,
                'name' => 'French Fries',
                'price' => 20000,
                'category' => 'makanan',
                'cost' => 8000
            ],
            [
                'id' => 3,
                'name' => 'Chicken Nuggets',
                'price' => 25000,
                'category' => 'makanan',
                'cost' => 12000
            ],
            [
                'id' => 4,
                'name' => 'Pizza Slice',
                'price' => 30000,
                'category' => 'makanan',
                'cost' => 18000
            ],
            [
                'id' => 5,
                'name' => 'Hot Dog',
                'price' => 22000,
                'category' => 'makanan',
                'cost' => 10000
            ],
            [
                'id' => 6,
                'name' => 'Soft Drink',
                'price' => 12000,
                'category' => 'minuman',
                'cost' => 3000
            ],
            [
                'id' => 7,
                'name' => 'Ice Cream Sundae',
                'price' => 15000,
                'category' => 'minuman',
                'cost' => 5000
            ],
            [
                'id' => 8,
                'name' => 'Chocolate Milkshake',
                'price' => 22000,
                'category' => 'minuman',
                'cost' => 8000
            ],
            [
                'id' => 9,
                'name' => 'Vanilla Milkshake',
                'price' => 22000,
                'category' => 'minuman',
                'cost' => 8000
            ],
            [
                'id' => 10,
                'name' => 'Iced Tea',
                'price' => 10000,
                'category' => 'minuman',
                'cost' => 2000
            ]
        ];
        saveJSONData(MENU_FILE, $menu);
    }
    
    // Initialize transactions
    if (!file_exists(TRANSACTIONS_FILE)) {
        $transactions = [
            [
                'id' => 1,
                'order_number' => 1001,
                'customer_name' => 'Mita',
                'customer_kode' => 'A01',
                'total_amount' => 57000,
                'food_count' => 2,
                'drink_count' => 1,
                'order_date' => date('Y-m-d H:i:s'),
                'status' => 'completed',
                'payment_method' => 'tunai',
                'payment_status' => 'paid'
            ],
            [
                'id' => 2,
                'order_number' => 1002,
                'customer_name' => 'Dini',
                'customer_kode' => 'A02',
                'total_amount' => 45000,
                'food_count' => 1,
                'drink_count' => 2,
                'order_date' => date('Y-m-d', strtotime('-1 day')) . ' 14:30:00',
                'status' => 'completed',
                'payment_method' => 'qris',
                'payment_status' => 'paid'
            ]
        ];
        saveJSONData(TRANSACTIONS_FILE, $transactions);
    }
    
    // Initialize cart
    if (!file_exists(CART_FILE)) {
        saveJSONData(CART_FILE, []);
    }
    
    // Initialize order requests
    if (!file_exists(ORDER_REQUESTS_FILE)) {
        saveJSONData(ORDER_REQUESTS_FILE, []);
    }
}

// Initialize data
initializeData();

// Handle logout
if (isset($_GET['logout'])) {
    $logout_type = sanitize($_GET['logout']);
    
    if ($logout_type == 'admin') {
        unset($_SESSION['admin_logged_in']);
        unset($_SESSION['user_type']);
        session_destroy();
        header('Location: index.php?message=logout_success&type=admin');
        exit();
    } elseif ($logout_type == 'customer') {
        unset($_SESSION['customer_id']);
        unset($_SESSION['customer_name']);
        unset($_SESSION['customer_kode']);
        unset($_SESSION['user_type']);
        session_destroy();
        header('Location: index.php?message=logout_success&type=customer');
        exit();
    }
}
?>