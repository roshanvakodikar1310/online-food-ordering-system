
<?php
require_once __DIR__ . '/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

function checkoutEscape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$error = '';
$successOrderId = isset($_GET['success']) ? (int)$_GET['success'] : 0;
$foods = [];
$total = 0;

$cart = $_SESSION['cart'];

if ($cart) {
    $ids = array_values(array_filter(array_map('intval', array_keys($cart))));

    if ($ids) {
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare(
            "SELECT id, name, price, image
             FROM products WHERE id IN ($marks)"
        );
        $stmt->execute($ids);

        foreach ($stmt->fetchAll() as $food) {
            $id = (int)$food['id'];
            $qty = max(1, min(99, (int)($cart[$id] ?? 1)));
            $food['quantity'] = $qty;
            $food['subtotal'] = (float)$food['price'] * $qty;
            $foods[$id] = $food;
            $total += $food['subtotal'];
        }
    }
}

// Process order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$successOrderId) {
    $customerName = trim($_POST['customer_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if (!$foods || $total <= 0) {
        $error = 'Your cart is empty. Please add food before checkout.';
    } elseif ($customerName === '' || $phone === '' || $address === '') {
        $error = 'Please fill in all customer details.';
    } elseif (!preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) {
        $error = 'Please enter a valid phone number.';
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "INSERT INTO orders
                 (customer_name, phone, address, total_amount, status)
                 VALUES (?, ?, ?, ?, 'Pending')"
            );
            $stmt->execute([
                $customerName,
                $phone,
                $address,
                $total
            ]);

            $orderId = (int)$pdo->lastInsertId();

            $itemStmt = $pdo->prepare(
                "INSERT INTO order_items
                 (order_id, product_id, product_name, price, quantity, subtotal)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );

            foreach ($foods as $food) {
                $itemStmt->execute([
                    $orderId,
                    (int)$food['id'],
                    $food['name'],
                    $food['price'],
                    $food['quantity'],
                    $food['subtotal']
                ]);
            }

            $pdo->commit();
            $_SESSION['cart'] = [];

            header('Location: checkout.php?success=' . $orderId);
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('Checkout failed: ' . $e->getMessage());
            $error = 'Could not place your order. Please try again.';
        }
    }
}

if ($successOrderId > 0) {
    $stmt = $pdo->prepare(
        "SELECT id, customer_name, total_amount, status
         FROM orders WHERE id = ?"
    );
    $stmt->execute([$successOrderId]);
    $confirmedOrder = $stmt->fetch();

    if (!$confirmedOrder) {
        $successOrderId = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Checkout | Foodie Corner</title>
    <link rel="stylesheet" href="style.css">
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
        <a href="cart.php">Cart</a>
    </nav>
</header>

<main class="menu-section">
    <?php if ($successOrderId && $confirmedOrder): ?>
        <div class="about-section">
            <div class="about-emoji">✅</div>
            <div>
                <span class="eyebrow">ORDER CONFIRMED</span>
                <h2>Thank you, <?= checkoutEscape($confirmedOrder['customer_name']) ?>!</h2>
                <p>Your order number is <strong>#<?= (int)$confirmedOrder['id'] ?></strong>.</p>
                <p>Total: <strong>₹<?= number_format((float)$confirmedOrder['total_amount'], 2) ?></strong></p>
                <p>Payment method: Cash on Delivery (COD)</p>
                <p>Status: <?= checkoutEscape($confirmedOrder['status']) ?></p>
            </div>
        </div>
        <a class="primary-button" href="index.php#menu">Order More Food →</a>

    <?php elseif (!$foods): ?>
        <h2>Your cart is empty</h2>
        <p>Add food to your cart before checkout.</p>
        <a class="primary-button" href="index.php#menu">Browse Menu →</a>

    <?php else: ?>
        <div class="section-heading">
            <div>
                <span class="eyebrow">ALMOST THERE</span>
                <h2>Checkout <span>details.</span></h2>
                <p>Enter your details and choose Cash on Delivery.</p>
            </div>
        </div>

        <?php if ($error !== ''): ?>
            <p class="success-message"><?= checkoutEscape($error) ?></p>
        <?php endif; ?>

        <h3>Order Summary</h3>
        <?php foreach ($foods as $food): ?>
            <p>
                <?= checkoutEscape($food['name']) ?>
                × <?= (int)$food['quantity'] ?> —
                ₹<?= number_format($food['subtotal'], 2) ?>
            </p>
        <?php endforeach; ?>
        <h3>Total: ₹<?= number_format($total, 2) ?></h3>

        <form method="POST" class="product-form">
            <label for="customer_name">Full name</label>
            <input id="customer_name" type="text" name="customer_name"
                   maxlength="150" required>

            <label for="phone">Phone number</label>
            <input id="phone" type="tel" name="phone"
                   maxlength="20" required>

            <label for="address">Full delivery address</label>
            <textarea id="address" name="address"
                      maxlength="2000" rows="4" required></textarea>

            <p><strong>Payment:</strong> Cash on Delivery (COD)</p>
            <button type="submit">Place Order</button>
        </form>
    <?php endif; ?>
</main>

<footer class="modern-footer">
    <a href="cart.php">← Back to Cart</a>
    <small>© <?= date('Y') ?> Foodie Corner. Student Mini Project.</small>
</footer>
</body>
</html>
