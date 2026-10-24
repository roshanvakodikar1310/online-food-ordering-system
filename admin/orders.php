
<?php

require_once __DIR__ . '/../config.php';

if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$message = '';
$allowedStatuses = [
    'Pending',
    'Preparing',
    'Out for Delivery',
    'Delivered',
    'Cancelled'
];

// Update order status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $orderId = (int)($_POST['order_id'] ?? 0);
    $status = $_POST['status'] ?? '';

    if ($orderId > 0 && in_array($status, $allowedStatuses, true)) {
        try {
            $stmt = $pdo->prepare(
                "UPDATE orders SET status = ? WHERE id = ?"
            );
            $stmt->execute([$status, $orderId]);

            header('Location: orders.php?updated=1');
            exit;
        } catch (PDOException $e) {
            error_log('Order status update failed: ' . $e->getMessage());
            $message = 'Could not update order status.';
        }
    } else {
        $message = 'Invalid order or status.';
    }
}

if (isset($_GET['updated'])) {
    $message = 'Order status updated successfully.';
}

try {
    $stmt = $pdo->query(
        "SELECT id, customer_name, phone, address,
                total_amount, status, created_at
         FROM orders
         ORDER BY id DESC"
    );
    $orders = $stmt->fetchAll();

    $itemStmt = $pdo->prepare(
        "SELECT product_name, price, quantity, subtotal
         FROM order_items
         WHERE order_id = ?
         ORDER BY id ASC"
    );
} catch (PDOException $e) {
    error_log('Orders loading failed: ' . $e->getMessage());
    $orders = [];
    $itemStmt = null;
    $message = 'Could not load orders from MySQL.';
}

function orderEscape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Orders | Foodie Corner</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .orders-wrap {
            width: min(1100px, 94%);
            margin: 30px auto;
        }
        .order-panel {
            background: #fff;
            border-radius: 14px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 5px 20px rgba(0,0,0,.07);
            overflow-wrap: anywhere;
        }
        .order-panel img { max-width: 100%; }
        .order-panel select, .order-panel button {
            margin: 6px 4px 6px 0;
            padding: 9px;
        }
        .order-items { padding-left: 20px; }
    </style>
</head>
<body>
<div class="orders-wrap">
    <h1>Customer Orders</h1>

    <?php if ($message !== ''): ?>
        <p><?= orderEscape($message) ?></p>
    <?php endif; ?>

    <?php if (!$orders): ?>
        <p>No orders found.</p>
    <?php endif; ?>

    <?php foreach ($orders as $order): ?>
        <section class="order-panel">
            <h2>Order #<?= (int)$order['id'] ?></h2>

            <p><strong>Customer:</strong>
                <?= orderEscape($order['customer_name']) ?></p>
            <p><strong>Phone:</strong>
                <?= orderEscape($order['phone'] ?? '') ?></p>
            <p><strong>Address:</strong>
                <?= nl2br(orderEscape($order['address'])) ?></p>
            <p><strong>Order date:</strong>
                <?= orderEscape($order['created_at']) ?></p>
            <p><strong>Total:</strong>
                ₹<?= number_format((float)$order['total_amount'], 2) ?></p>
            <p><strong>Payment:</strong> Cash on Delivery</p>
            <p><strong>Current status:</strong>
                <?= orderEscape($order['status']) ?></p>

            <h3>Items</h3>
            <?php
            $items = [];

            if ($itemStmt) {
                $itemStmt->execute([(int)$order['id']]);
                $items = $itemStmt->fetchAll();
            }
            ?>

            <?php if ($items): ?>
                <ul class="order-items">
                    <?php foreach ($items as $item): ?>
                        <li>
                            <?= orderEscape($item['product_name']) ?>
                            — Qty: <?= (int)$item['quantity'] ?>
                            — ₹<?= number_format((float)$item['subtotal'], 2) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>No item details available.</p>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="order_id"
                       value="<?= (int)$order['id'] ?>">

                <label for="status-<?= (int)$order['id'] ?>">
                    Update status:
                </label>

                <select id="status-<?= (int)$order['id'] ?>"
                        name="status" required>
                    <?php foreach ($allowedStatuses as $status): ?>
                        <option value="<?= orderEscape($status) ?>"
                            <?= $order['status'] === $status ? 'selected' : '' ?>>
                            <?= orderEscape($status) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" name="update_status" value="1">
                    Update Status
                </button>
            </form>
        </section>
    <?php endforeach; ?>

    <p><a href="dashboard.php">← Back to Dashboard</a></p>
</div>
</body>
</html>
