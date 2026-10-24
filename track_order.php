
<?php
require_once __DIR__ . '/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$order = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = trim($_POST['order_id'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if ($orderId === '' || $phone === '') {
        $error = 'Please enter both order number and phone number.';
    } else {
        try {
            $stmt = $pdo->prepare(
                "SELECT * FROM orders WHERE id = ? AND phone = ? LIMIT 1"
            );
            $stmt->execute([$orderId, $phone]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                $error = 'Order not found. Please check your details.';
            }
        } catch (PDOException $e) {
            $error = 'Unable to find the order. Please check the database configuration.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Your Order</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .tracking-box {
            max-width: 600px;
            margin: 40px auto;
            padding: 25px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 18px rgba(0,0,0,.08);
        }
        .tracking-box input {
            width: 100%;
            padding: 12px;
            margin: 8px 0 16px;
            box-sizing: border-box;
        }
        .tracking-box button {
            padding: 12px 20px;
            cursor: pointer;
        }
        .error { color: #b42318; }
        .status { font-weight: bold; color: #176b45; }
    </style>
</head>
<body>
    <main class="tracking-box">
        <h1>Track Your Order</h1>
        <p>Enter the order number and phone number used during checkout.</p>

        <?php if ($error): ?>
            <p class="error">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </p>
        <?php endif; ?>

        <form method="POST">
            <label for="order_id">Order Number</label>
            <input id="order_id" name="order_id" required>

            <label for="phone">Checkout Phone Number</label>
            <input id="phone" name="phone" type="tel"
                   autocomplete="tel" required>

            <button type="submit">Track Order</button>
        </form>

        <?php if ($order): ?>
            <hr>
            <h2>Order Details</h2>
            <p>Order Number:
                <?= htmlspecialchars((string)$order['id'], ENT_QUOTES, 'UTF-8') ?>
            </p>
            <p>Status:
                <span class="status">
                    <?= htmlspecialchars($order['status'] ?? 'Pending', ENT_QUOTES, 'UTF-8') ?>
                </span>
            </p>
            <p>Total:
                ₹<?= number_format((float)($order['total_amount'] ?? 0), 2) ?>
            </p>
        <?php endif; ?>

        <p><a href="index.php">Back to Home</a></p>
    </main>
</body>
</html>
