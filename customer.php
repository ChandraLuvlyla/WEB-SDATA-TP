<?php
require_once 'config.php';

// Check if customer is logged in
if (!isset($_SESSION['customer_id'])) {
    header('Location: index.php');
    exit();
}

$customer_id = $_SESSION['customer_id'];
$customer_name = $_SESSION['customer_name'];
$customer_kode = $_SESSION['customer_kode'];
$session_id = session_id();

// Show message if exists
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// Get customer info
$customers = getJSONData(CUSTOMERS_FILE);
$customer_info = null;
foreach ($customers as $customer) {
    if (isset($customer['id']) && $customer['id'] == $customer_id) {
        $customer_info = $customer;
        break;
    }
}

// Get menu items
$menu_items = getJSONData(MENU_FILE);

// Handle cart actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_to_cart'])) {
        $item_id = sanitize($_POST['item_id']);
        $quantity = sanitize($_POST['quantity']);
        
        addCartItem($session_id, $item_id, $quantity);
        $_SESSION['cart_message'] = 'Item berhasil ditambahkan ke keranjang!';
        header('Location: customer.php');
        exit();
    }
    
    if (isset($_POST['update_cart'])) {
        $item_id = sanitize($_POST['item_id']);
        $quantity = sanitize($_POST['quantity']);
        
        updateCartItem($session_id, $item_id, $quantity);
        $_SESSION['cart_message'] = 'Keranjang berhasil diperbarui!';
        header('Location: customer.php');
        exit();
    }
    
    if (isset($_POST['checkout'])) {
        // Get cart items
        $cart_items = getCartItems($session_id);
        
        if (!empty($cart_items)) {
            // Get menu items for pricing
            $menu_lookup = [];
            foreach ($menu_items as $item) {
                if (isset($item['id'])) {
                    $menu_lookup[$item['id']] = $item;
                }
            }
            
            // Calculate totals
            $total_amount = 0;
            $food_count = 0;
            $drink_count = 0;
            
            foreach ($cart_items as $cart_item) {
                $menu_item = $menu_lookup[$cart_item['item_id']] ?? null;
                if ($menu_item) {
                    $item_total = $menu_item['price'] * $cart_item['quantity'];
                    $total_amount += $item_total;
                    
                    if ($menu_item['category'] == 'makanan') {
                        $food_count += $cart_item['quantity'];
                    } else {
                        $drink_count += $cart_item['quantity'];
                    }
                }
            }
            
            if ($total_amount > 0) {
                // Create order directly without approval
                $order_number = generateOrderNumber();
                $order_data = [
                    'order_number' => $order_number,
                    'customer_name' => $customer_name,
                    'customer_kode' => $customer_kode,
                    'total_amount' => $total_amount,
                    'food_count' => $food_count,
                    'drink_count' => $drink_count,
                    'payment_method' => $_POST['payment_method'] ?? 'tunai'
                ];
                
                // Create transaction langsung
                createTransactionDirect($order_data);
                
                // Clear cart
                clearCart($session_id);
                
                $_SESSION['checkout_message'] = "Pesanan #$order_number berhasil dibuat! Total: Rp " . number_format($total_amount, 0, ',', '.');
                header('Location: customer.php');
                exit();
            }
        } else {
            $_SESSION['cart_message'] = 'Keranjang kosong!';
            header('Location: customer.php');
            exit();
        }
    }
    
    // Handle pembatalan pesanan langsung
    if (isset($_POST['cancel_order'])) {
        $order_number = sanitize($_POST['order_number']);
        $reason = sanitize($_POST['cancel_reason']);
        
        if (cancelOrderDirect($order_number, $reason, $customer_kode)) {
            $_SESSION['message'] = [
                'type' => 'success',
                'text' => "Pesanan #$order_number berhasil dibatalkan!"
            ];
        } else {
            $_SESSION['message'] = [
                'type' => 'error',
                'text' => "Gagal membatalkan pesanan. Pesanan tidak ditemukan atau sudah dibatalkan."
            ];
        }
        header('Location: customer.php');
        exit();
    }
}

// Get cart items with menu details
$cart_items = getCartItems($session_id);
$cart_with_details = [];
$cart_total = 0;
$cart_items_count = 0;

foreach ($cart_items as $cart_item) {
    foreach ($menu_items as $menu_item) {
        if (isset($menu_item['id'], $cart_item['item_id']) && $menu_item['id'] == $cart_item['item_id']) {
            $item_total = $menu_item['price'] * $cart_item['quantity'];
            $cart_with_details[] = [
                'item_id' => $cart_item['item_id'],
                'name' => $menu_item['name'],
                'price' => $menu_item['price'],
                'category' => $menu_item['category'],
                'quantity' => $cart_item['quantity']
            ];
            $cart_total += $item_total;
            $cart_items_count += $cart_item['quantity'];
            break;
        }
    }
}

// Get customer order history
$transactions = getJSONData(TRANSACTIONS_FILE);
$order_history = array_filter($transactions, function($transaction) use ($customer_kode) {
    return isset($transaction['customer_kode']) && $transaction['customer_kode'] === $customer_kode;
});
usort($order_history, function($a, $b) {
    $dateA = isset($a['order_date']) ? strtotime($a['order_date']) : 0;
    $dateB = isset($b['order_date']) ? strtotime($b['order_date']) : 0;
    return $dateB - $dateA;
});
$order_history = array_slice($order_history, 0, 10);

// Get pending requests
$all_requests = getJSONData(ORDER_REQUESTS_FILE);
$customer_requests = array_filter($all_requests, function($request) use ($customer_kode) {
    return isset($request['customer_kode'], $request['status']) && 
           $request['customer_kode'] === $customer_kode && $request['status'] == 'pending';
});
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Pelanggan - Sistem Drive-Thru</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="customer-dashboard">
    <div class="container">
        <!-- Header -->
        <div class="dashboard-header">
            <h1><i class="fas fa-user-circle"></i> Selamat Datang, <?php echo htmlspecialchars($customer_name); ?>!</h1>
            <div class="user-info">
                <span><i class="fas fa-id-card"></i> Kode: <?php echo htmlspecialchars($customer_kode); ?></span>
                <a href="?logout=customer" class="logout-btn" onclick="return confirm('Yakin ingin logout?')">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
        
        <!-- Message Notification -->
        <?php if (isset($message)): ?>
            <div class="alert alert-<?php echo $message['type']; ?>">
                <i class="fas fa-<?php echo $message['type'] == 'success' ? 'check-circle' : 'info-circle'; ?>"></i>
                <?php echo $message['text']; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['cart_message'])): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <?php echo $_SESSION['cart_message']; ?>
                <?php unset($_SESSION['cart_message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['checkout_message'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $_SESSION['checkout_message']; ?>
                <?php unset($_SESSION['checkout_message']); ?>
            </div>
        <?php endif; ?>
        
        <!-- Customer Stats -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <i class="fas fa-money-bill-wave"></i>
                <div class="stat-value">Rp <?php echo number_format($customer_info['total_spent'] ?? 0, 0, ',', '.'); ?></div>
                <div class="stat-label">Total Belanja</div>
            </div>
            
            <div class="stat-card success">
                <i class="fas fa-shopping-cart"></i>
                <div class="stat-value"><?php echo $cart_items_count; ?></div>
                <div class="stat-label">Item di Keranjang</div>
            </div>
            
            <div class="stat-card warning">
                <i class="fas fa-history"></i>
                <div class="stat-value"><?php echo $customer_info['visit_count'] ?? 1; ?></div>
                <div class="stat-label">Total Kunjungan</div>
            </div>
            
            <div class="stat-card info">
                <i class="fas fa-trophy"></i>
                <div class="stat-value">
                    <?php 
                    $total_spent = $customer_info['total_spent'] ?? 0;
                    if ($total_spent > 100000) echo 'Premium';
                    elseif ($total_spent > 50000) echo 'Reguler';
                    else echo 'Baru';
                    ?>
                </div>
                <div class="stat-label">Kategori</div>
            </div>
        </div>
        
        <!-- Notifikasi Pending Requests -->
        <?php if (!empty($customer_requests)): ?>
        <div class="card">
            <div class="card-header" style="background: #fff3cd; border-color: #ffeaa7;">
                <h2><i class="fas fa-clock"></i> Menunggu Approval (<?php echo count($customer_requests); ?>)</h2>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>No. Pesanan</th>
                            <th>Total</th>
                            <th>Tanggal Request</th>
                            <th>Status</th>
                            <th>Tipe</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($customer_requests as $request): ?>
                        <tr>
                            <td>#<?php echo $request['order_number']; ?></td>
                            <td>Rp <?php echo number_format($request['total_amount'], 0, ',', '.'); ?></td>
                            <td><?php echo date('d-m-Y H:i', strtotime($request['request_date'])); ?></td>
                            <td><span class="badge badge-warning">Pending</span></td>
                            <td>
                                <?php 
                                if (isset($request['type']) && $request['type'] == 'cancel') {
                                    echo '<span class="badge badge-danger">Pembatalan</span>';
                                } else {
                                    echo '<span class="badge badge-primary">Pesanan Baru</span>';
                                }
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Menu Section -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-utensils"></i> Menu Makanan & Minuman</h2>
            </div>
            <div class="menu-grid">
                <?php foreach($menu_items as $item): ?>
                <div class="menu-item">
                    <div class="menu-item-img">
                        <i class="fas fa-<?php echo $item['category'] == 'makanan' ? 'hamburger' : 'wine-glass-alt'; ?>"></i>
                    </div>
                    <div class="menu-item-content">
                        <div class="menu-item-title"><?php echo htmlspecialchars($item['name']); ?></div>
                        <div class="menu-item-price">Rp <?php echo number_format($item['price'], 0, ',', '.'); ?></div>
                        <span class="menu-item-category"><?php echo ucfirst($item['category']); ?></span>
                        <form method="POST" action="" class="add-to-cart-form">
                            <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                            <div class="form-row" style="align-items: center; margin-bottom: 10px;">
                                <div class="form-group" style="flex: 1; margin-bottom: 0;">
                                    <label>Jumlah:</label>
                                    <input type="number" name="quantity" value="1" min="1" max="10" style="width: 100%; padding: 8px;">
                                </div>
                                <button type="submit" name="add_to_cart" class="btn btn-primary" style="margin-top: 20px;">
                                    <i class="fas fa-cart-plus"></i> Tambah
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Cart Section -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-shopping-cart"></i> Keranjang Belanja</h2>
                <span class="badge badge-primary"><?php echo $cart_items_count; ?> item</span>
            </div>
            
            <?php if ($cart_items_count > 0): ?>
            <div class="cart-items">
                <?php foreach($cart_with_details as $item): ?>
                <div class="cart-item">
                    <div class="cart-item-name">
                        <?php echo htmlspecialchars($item['name']); ?>
                        <small>(Rp <?php echo number_format($item['price'], 0, ',', '.'); ?>)</small>
                    </div>
                    <div class="cart-item-qty">
                        <form method="POST" action="" style="display: flex; align-items: center; gap: 10px;">
                            <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                            <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" min="0" max="10" style="width: 50px; text-align: center;">
                            <button type="submit" name="update_cart" class="btn btn-primary btn-sm">
                                <i class="fas fa-sync-alt"></i> Update
                            </button>
                        </form>
                    </div>
                    <div class="cart-item-total">
                        Rp <?php echo number_format($item['price'] * $item['quantity'], 0, ',', '.'); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="cart-total">
                <div class="cart-total-row">
                    <span>Subtotal:</span>
                    <span>Rp <?php echo number_format($cart_total, 0, ',', '.'); ?></span>
                </div>
                <div class="cart-total-row">
                    <span>Pajak (10%):</span>
                    <span>Rp <?php echo number_format($cart_total * 0.1, 0, ',', '.'); ?></span>
                </div>
                <div class="cart-total-row total">
                    <span>Total:</span>
                    <span>Rp <?php echo number_format($cart_total * 1.1, 0, ',', '.'); ?></span>
                </div>
                
                <!-- Form pembayaran -->
                <form method="POST" action="" style="margin-top: 20px;">
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label for="payment_method" style="display: block; margin-bottom: 8px; font-weight: bold;">Metode Pembayaran:</label>
                        <select id="payment_method" name="payment_method" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                            <option value="tunai">Tunai</option>
                            <option value="qris">QRIS</option>
                            <option value="debit">Kartu Debit</option>
                            <option value="kredit">Kartu Kredit</option>
                        </select>
                    </div>
                    
                    <button type="submit" name="checkout" class="btn btn-success btn-block" onclick="return confirm('Konfirmasi pembayaran dan buat pesanan?')">
                        <i class="fas fa-cash-register"></i> Bayar & Buat Pesanan
                    </button>
                </form>
            </div>
            <?php else: ?>
                <div style="text-align: center; padding: 40px;">
                    <i class="fas fa-shopping-cart" style="font-size: 60px; color: #ff8ba7; margin-bottom: 20px;"></i>
                    <h3 style="color: #6c757d;">Keranjang Belanja Kosong</h3>
                    <p>Tambahkan item dari menu di atas</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Order History -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-history"></i> Riwayat Pesanan</h2>
            </div>
            <?php if (!empty($order_history)): ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>No. Pesanan</th>
                            <th>Total</th>
                            <th>Item</th>
                            <th>Tanggal</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($order_history as $order): 
                            $status = $order['status'] ?? 'completed';
                            $total_items = ($order['food_count'] ?? 0) + ($order['drink_count'] ?? 0);
                        ?>
                        <tr>
                            <td>#<?php echo $order['order_number']; ?></td>
                            <td>Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></td>
                            <td><?php echo $total_items; ?> item</td>
                            <td><?php echo date('d-m-Y H:i', strtotime($order['order_date'])); ?></td>
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
                                <?php if ($status == 'completed'): ?>
                                <div style="display: flex; gap: 5px;">
                                    <a href="receipt.php?order=<?php echo $order['order_number']; ?>" target="_blank" class="btn btn-primary btn-sm">
                                        <i class="fas fa-print"></i> Struk
                                    </a>
                                    <button type="button" class="btn btn-danger btn-sm" onclick="showCancelModal(<?php echo $order['order_number']; ?>)">
                                        <i class="fas fa-times"></i> Batalkan
                                    </button>
                                </div>
                                <?php elseif ($status == 'pending'): ?>
                                    <span class="badge badge-warning">Menunggu Approval</span>
                                <?php else: ?>
                                    <a href="receipt.php?order=<?php echo $order['order_number']; ?>" target="_blank" class="btn btn-primary btn-sm">
                                        <i class="fas fa-print"></i> Struk
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div style="text-align: center; padding: 20px;">
                    <p style="color: #6c757d;">Belum ada riwayat pesanan</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal Pembatalan -->
    <div id="cancelModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h3><i class="fas fa-times-circle"></i> Batalkan Pesanan</h3>
            <form method="POST" action="">
                <input type="hidden" id="cancel_order_number" name="order_number">
                
                <div class="form-group">
                    <label for="cancel_reason">Alasan Pembatalan:</label>
                    <textarea id="cancel_reason" name="cancel_reason" rows="4" required 
                              placeholder="Mohon jelaskan alasan pembatalan pesanan..." 
                              style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;"></textarea>
                </div>
                
                <div class="modal-buttons">
                    <button type="submit" name="cancel_order" class="btn btn-danger btn-sm">
                        <i class="fas fa-check"></i> Ajukan Pembatalan
                    </button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="closeCancelModal()">
                        <i class="fas fa-times"></i> Batal
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="script.js"></script>
    <script>
        function showCancelModal(orderNumber) {
            document.getElementById('cancel_order_number').value = orderNumber;
            document.getElementById('cancelModal').style.display = 'flex';
        }
        
        function closeCancelModal() {
            document.getElementById('cancelModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('cancelModal');
            if (event.target == modal) {
                closeCancelModal();
            }
        }
    </script>
</body>
</html>