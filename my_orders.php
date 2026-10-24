
<?php
require_once __DIR__ . '/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$phone = trim($_POST['phone'] ?? '');
$normalizedPhone = preg_replace('/\D+/', '', $phone);
$orders = [];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($phone === '') {
        $error = 'Please enter the phone number used at checkout.';
    } else {
        try {
            if ($normalizedPhone === '') {
                $error = 'Please enter a valid phone number.';
            } else {
                // Match phone numbers even if the customer entered spaces, +, brackets or hyphens.
                $stmt = $pdo->prepare(
                    "SELECT id, customer_name, phone, address,
                            total_amount, status, created_at
                     FROM orders
                     WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', ''), '(', ''), ')', ''), '.', '') = ?
                        OR phone = ?
                     ORDER BY created_at DESC, id DESC"
                );
                $stmt->execute([$normalizedPhone, $phone]);
                $orders = $stmt->fetchAll();
            }

            if ($orders) {
                $itemStmt = $pdo->prepare(
                    'SELECT product_name, price, quantity, subtotal
                     FROM order_items
                     WHERE order_id = ?
                     ORDER BY id ASC'
                );

                foreach ($orders as &$order) {
                    $itemStmt->execute([(int)$order['id']]);
                    $order['items'] = $itemStmt->fetchAll();
                }
                unset($order);
            }
        } catch (PDOException $ex) {
            error_log('My Orders error: ' . $ex->getMessage());
            $error = 'Could not load orders. Please check the database tables.';
        }
    }
}

$currentStatuses = ['Pending', 'Preparing', 'Out for Delivery'];

function isCurrentOrder($status, $currentStatuses) {
    return in_array($status, $currentStatuses, true);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Orders | Foodie Corner</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .my-orders-wrap{width:min(1000px,92%);margin:40px auto 60px}
        .orders-card{background:#fff;padding:24px;border-radius:18px;
            box-shadow:0 8px 28px rgba(0,0,0,.07);margin:20px 0}
        .orders-form{display:flex;gap:12px;flex-wrap:wrap;margin-top:18px}
        .orders-form input{flex:1;min-width:220px;padding:13px;
            border:1px solid #ddd;border-radius:9px;font:inherit}
        .order-heading{display:flex;justify-content:space-between;
            gap:12px;flex-wrap:wrap;align-items:center}
        .order-status{display:inline-block;background:#edf6ed;color:#21643c;
            border-radius:20px;padding:7px 12px;font-weight:700}
        .past-status{background:#f0f0f0;color:#555}
        .order-info{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
            gap:12px;margin:18px 0}
        .order-info>div{background:#f7f8f4;padding:13px;border-radius:10px;
            overflow-wrap:anywhere}
        .order-info small{display:block;color:#666;margin-bottom:5px}
        .order-table{width:100%;border-collapse:collapse;margin:12px 0}
        .order-table th,.order-table td{text-align:left;padding:10px 7px;
            border-bottom:1px solid #eee}
        .order-table th:last-child,.order-table td:last-child{text-align:right}
        .order-error{color:#a22;background:#fff0ed;padding:12px;border-radius:9px}
        .order-section-title{margin-top:32px}
        @media(max-width:560px){.orders-card{padding:16px}.order-table{font-size:13px}}
    </style>
</head>
<body>
<header class="modern-header">
    <a class="brand" href="index.php">
        <span class="brand-icon">🍔</span>
        <span>Foodie<span class="brand-accent">Corner</span>
        <small>GOOD FOOD, GOOD MOOD</small></span>
    </a>
    <nav class="main-nav">
        <a href="index.php">Home</a>
        <a href="index.php#menu">Our Menu</a>
        <a href="track_order.php">Track Order</a>
        <a class="active" href="my_orders.php">My Orders</a>
    </nav>
    <div class="header-actions"><a class="cart-link" href="cart.php">🛒 Cart</a></div>
</header>

<main class="my-orders-wrap">
    <div class="section-heading">
        <div>
            <span class="eyebrow">YOUR ORDER HISTORY</span>
            <h2>My <span>Orders</span></h2>
            <p>View your current and previous orders with item and delivery details.</p>
        </div>
    </div>

    <section class="orders-card">
        <h3>Find your orders</h3>
        <p>Enter the phone number used during checkout.</p>
        <form method="post" class="orders-form">
            <input type="tel" name="phone" maxlength="20"
                   value="<?= e($phone) ?>"
                   placeholder="Checkout phone number" required>
            <button class="primary-button" type="submit">View My Orders</button>
        </form>

        <?php if ($error !== ''): ?>
            <p class="order-error"><?= e($error) ?></p>
        <?php endif; ?>

        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error && !$orders): ?>
            <p>No orders found for that phone number.</p>
        <?php endif; ?>
    </section>

    <?php
    $currentOrders = array_filter(
        $orders,
        fn($o) => isCurrentOrder($o['status'], $currentStatuses)
    );
    $pastOrders = array_filter(
        $orders,
        fn($o) => !isCurrentOrder($o['status'], $currentStatuses)
    );
    ?>

    <?php foreach ([
        'Current Orders' => $currentOrders,
        'Past Orders' => $pastOrders
    ] as $sectionTitle => $sectionOrders): ?>
        <?php if ($sectionOrders): ?>
            <h2 class="order-section-title"><?= e($sectionTitle) ?></h2>

            <?php foreach ($sectionOrders as $order): ?>
                <section class="orders-card">
                    <div class="order-heading">
                        <h3>Order #<?= (int)$order['id'] ?></h3>
                        <span class="order-status <?= isCurrentOrder($order['status'], $currentStatuses) ? '' : 'past-status' ?>">
                            <?= e($order['status']) ?>
                        </span>
                    </div>

                    <div class="order-info">
                        <div><small>Order Date</small><strong><?= e($order['created_at']) ?></strong></div>
                        <div><small>Customer</small><strong><?= e($order['customer_name']) ?></strong></div>
                        <div><small>Phone</small><strong><?= e($order['phone']) ?></strong></div>
                        <div><small>Total Amount</small><strong>₹<?= number_format((float)$order['total_amount'], 2) ?></strong></div>
                        <div><small>Payment</small><strong>Cash on Delivery</strong></div>
                    </div>

                    <h4>Food Items</h4>
                    <div style="overflow-x:auto">
                        <table class="order-table">
                            <thead><tr><th>Item</th><th>Qty</th><th>Price</th><th>Subtotal</th></tr></thead>
                            <tbody>
                            <?php foreach ($order['items'] as $item): ?>
                                <tr>
                                    <td><?= e($item['product_name']) ?></td>
                                    <td><?= (int)$item['quantity'] ?></td>
                                    <td>₹<?= number_format((float)$item['price'], 2) ?></td>
                                    <td>₹<?= number_format((float)$item['subtotal'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <p><strong>Delivery Address:</strong><br><?= nl2br(e($order['address'])) ?></p>
                    <a class="primary-button"
                       href="track_order.php?order_id=<?= (int)$order['id'] ?>">
                        Track This Order →
                    </a>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endforeach; ?>
</main>

<footer class="modern-footer">
    <a href="index.php">← Back to Home</a>
    <small>© <?= date('Y') ?> Foodie Corner. Student Mini Project.</small>
</footer>
</body>
</html>
