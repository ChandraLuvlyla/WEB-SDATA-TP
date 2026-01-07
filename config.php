<?php
session_start();

// Konfigurasi path data JSON
define('DATA_DIR', 'data/');
define('MENU_FILE', DATA_DIR . 'menu.json');
define('TRANSACTIONS_FILE', DATA_DIR . 'transactions.json');
define('CART_FILE', DATA_DIR . 'cart.json');

// Admin credentials
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'admin123');

// Data pelanggan berdasarkan NIK NAMA.txt (tanpa database)
$CUSTOMERS = [
    [
        'nik' => '1234567890123456',
        'nama' => 'Mita',
        'password' => '3456'
    ],
    [
        'nik' => '1134650178923421',
        'nama' => 'Dini',
        'password' => '3421'
    ],
    [
        'nik' => '1975213910275390',
        'nama' => 'Lyla',
        'password' => '5390'
    ],
    [
        'nik' => '1075297130111777',
        'nama' => 'Ema',
        'password' => '1777'
    ],
    [
        'nik' => '1904308549275942',
        'nama' => 'Andi',
        'password' => '5942'
    ],
    [
        'nik' => '1234508097686902',
        'nama' => 'Fitri',
        'password' => '6902'
    ],
    [
        'nik' => '1345436657729011',
        'nama' => 'Desi',
        'password' => '9011'
    ],
    [
        'nik' => '1257933920145934',
        'nama' => 'Amar',
        'password' => '5934'
    ],
    [
        'nik' => '1175690222334550',
        'nama' => 'Eva',
        'password' => '4550'
    ],
    [
        'nik' => '1345556954768060',
        'nama' => 'Theo',
        'password' => '8060'
    ],
    [
        'nik' => '1257973339990277',
        'nama' => 'Rino',
        'password' => '9027'
    ],
    [
        'nik' => '1379028093275999',
        'nama' => 'Aska',
        'password' => '5999'
    ],
    [
        'nik' => '1257463425234233',
        'nama' => 'Rina',
        'password' => '4233'
    ],
    [
        'nik' => '1579563273425784',
        'nama' => 'Caca',
        'password' => '5784'
    ],
    [
        'nik' => '1113970002345678',
        'nama' => 'Olivia',
        'password' => '5678'
    ],
    [
        'nik' => '1243335443667678',
        'nama' => 'Naema',
        'password' => '7678'
    ],
    [
        'nik' => '1233454366778900',
        'nama' => 'Zen',
        'password' => '8900'
    ],
    [
        'nik' => '1779105100325791',
        'nama' => 'Rivia',
        'password' => '5791'
    ],
    [
        'nik' => '1232534646767989',
        'nama' => 'Alina',
        'password' => '7989'
    ],
    [
        'nik' => '1233257923579221',
        'nama' => 'Davin',
        'password' => '9221'
    ]
];

// Helper functions
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function verifyCustomer($nik, $password, $nama = '') {
    global $CUSTOMERS;
    
    foreach ($CUSTOMERS as $customer) {
        if ($customer['nik'] === $nik && $customer['password'] === $password) {
            if ($nama === '' || strtolower($customer['nama']) === strtolower($nama)) {
                return $customer;
            }
        }
    }
    return false;
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
        'customer_nik' => $order_data['customer_nik'] ?? '',
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
    
    return $transaction;
}

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
        ],
        'customers_count' => count($GLOBALS['CUSTOMERS']),
        'today_revenue' => 0,
        'monthly_revenue' => 0,
        'daily_transactions' => 0
    ];
    
    $today = date('Y-m-d');
    $current_month = date('Y-m');
    
    foreach ($transactions as $transaction) {
        $stats['total_transactions']++;
        
        $status = $transaction['status'] ?? 'completed';
        if ($status == 'completed') {
            $stats['total_revenue'] += ($transaction['total_amount'] ?? 0);
            $stats['total_food'] += ($transaction['food_count'] ?? 0);
            $stats['total_drinks'] += ($transaction['drink_count'] ?? 0);
            $stats['completed_orders']++;
            
            $order_date = date('Y-m-d', strtotime($transaction['order_date'] ?? date('Y-m-d')));
            if ($order_date == $today) {
                $stats['today_revenue'] += ($transaction['total_amount'] ?? 0);
                $stats['daily_transactions']++;
            }
            
            $order_month = date('Y-m', strtotime($transaction['order_date'] ?? date('Y-m-d')));
            if ($order_month == $current_month) {
                $stats['monthly_revenue'] += ($transaction['total_amount'] ?? 0);
            }
            
        } elseif ($status == 'cancelled') {
            $stats['cancelled_orders']++;
        }
    }
    
    if ($stats['completed_orders'] > 0) {
        $stats['average_order_value'] = $stats['total_revenue'] / $stats['completed_orders'];
    }
    
    $total_items = max(1, ($stats['total_food'] + $stats['total_drinks']));
    if ($total_items > 0) {
        $stats['by_category']['makanan'] = round(($stats['total_food'] * 100) / $total_items, 1);
        $stats['by_category']['minuman'] = round(($stats['total_drinks'] * 100) / $total_items, 1);
    }
    
    return $stats;
}

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
        
        if (isset($filters['customer_nik']) && isset($transaction['customer_nik'])) {
            if ($transaction['customer_nik'] != $filters['customer_nik']) {
                $match = false;
            }
        }
        
        if ($match) {
            $filtered[] = $transaction;
        }
    }
    
    return $filtered;
}

function resetSystemData() {
    // Reset transactions
    saveJSONData(TRANSACTIONS_FILE, []);
    
    // Reset cart
    saveJSONData(CART_FILE, []);
    
    return true;
}

// Admin authentication function
function adminLogin($username, $password) {
    if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
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
    if (!isset($_SESSION['customer_nik']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'customer') {
        header('Location: index.php?message=login_required');
        exit();
    }
}

// Initialize data files if they don't exist
function initializeData() {
    if (!file_exists(DATA_DIR)) {
        mkdir(DATA_DIR, 0755, true);
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
        $transactions = [];
        saveJSONData(TRANSACTIONS_FILE, $transactions);
    }
    
    // Initialize cart
    if (!file_exists(CART_FILE)) {
        saveJSONData(CART_FILE, []);
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
        unset($_SESSION['customer_nik']);
        unset($_SESSION['customer_name']);
        unset($_SESSION['user_type']);
        session_destroy();
        header('Location: index.php?message=logout_success&type=customer');
        exit();
    }
}
?>