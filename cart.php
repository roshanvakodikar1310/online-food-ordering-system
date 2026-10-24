
<?php
require_once __DIR__ . '/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

function cartEscape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$message = '';
$cart = &$_SESSION['cart'];

// Update quantities
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cart'])) {
    foreach (($_POST['qty'] ?? []) as $id => $qty) {
        $id = (int)$id;
        $qty = filter_var($qty, FILTER_VALIDATE_INT);

        if (!isset($cart[$id])) {
            continue;
        }

        if ($qty === false || $qty <= 0) {
            unset($cart[$id]);
        } else {
            $cart[$id] = min(99, $qty);
        }
    }

    header('Location: cart.php');
    exit;
}

// Remove an item
if (isset($_GET['remove'])) {
    unset($cart[(int)$_GET['remove']]);
    header('Location: cart.php');
    exit;
}

$foods = [];
$subtotal = 0;

if ($cart) {
    $ids = array_values(array_filter(array_map('intval', array_keys($cart))));

    if ($ids) {
        $marks = implode(',', array_fill(0, count($ids), '?'));
        try {
            $stmt = $pdo->prepare(
                "SELECT id, name, price, category, image, is_available
                 FROM products WHERE id IN ($marks)"
            );
            $stmt->execute($ids);
        } catch (PDOException $missingAvailabilityColumn) {
            $stmt = $pdo->prepare(
                "SELECT id, name, price, category, image, 1 AS is_available
                 FROM products WHERE id IN ($marks)"
            );
            $stmt->execute($ids);
        }

        foreach ($stmt->fetchAll() as $food) {
            if (isset($food['is_available']) && (int)$food['is_available'] !== 1) {
                unset($cart[(int)$food['id']]);
                continue;
            }
            $id = (int)$food['id'];
            $qty = max(1, min(99, (int)($cart[$id] ?? 1)));
            $food['quantity'] = $qty;
            $food['line_total'] = (float)$food['price'] * $qty;
            $foods[$id] = $food;
            $subtotal += $food['line_total'];
        }
    }
}

$cartCount = array_sum($cart);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Your Cart | Foodie Corner</title>
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
        <a href="index.php#menu">Our Menu</a>
    </nav>
    <div class="header-actions">
        <a class="cart-link" href="cart.php">🛒 Cart
            <span class="cart-badge"><?= (int)$cartCount ?></span>
        </a>
    </div>
</header>

<main class="menu-section">
    <div class="section-heading">
        <div>
            <span class="eyebrow">YOUR ORDER</span>
            <h2>Your shopping <span>cart.</span></h2>
            <p>Review your favourite food before checkout.</p>
        </div>
    </div>

    <?php if (!$foods): ?>
        <div class="no-food-results">
            <span>🛒</span>
            <h3>Your cart is empty</h3>
            <p>Add some delicious food to get started.</p>
            <a class="primary-button" href="index.php#menu">Browse Menu →</a>
        </div>
    <?php else: ?>
        <form method="POST">
            <input type="hidden" name="update_cart" value="1">

            <div class="product-list">
                <?php foreach ($foods as $food): ?>
                    <div class="admin-card">
                        <img src="<?= cartEscape($food['image'] ? $food['image'] : '') ?>"
                             alt="<?= cartEscape($food['name']) ?>"
                             style="width:100%;max-width:180px;border-radius:10px;"
                             onerror="this.style.display='none'">
                        <h3><?= cartEscape($food['name']) ?></h3>
                        <p>Price: ₹<?= number_format((float)$food['price'], 2) ?></p>
                        <label>
                            Quantity:
                            <input type="number"
                                   name="qty[<?= (int)$food['id'] ?>]"
                                   value="<?= (int)$food['quantity'] ?>"
                                   min="0" max="99">
                        </label>
                        <p>Item total: ₹<?= number_format($food['line_total'], 2) ?></p>
                        <a href="cart.php?remove=<?= (int)$food['id'] ?>"
                           onclick="return confirm('Remove this item?')">Remove</a>
                    </div>
                <?php endforeach; ?>
            </div>

            <h2>Subtotal: ₹<?= number_format($subtotal, 2) ?></h2>
            <button type="submit">Update Cart</button>
            <a class="primary-button" href="checkout.php">Proceed to Checkout →</a>
        </form>
    <?php endif; ?>
</main>

<footer class="modern-footer">
    <a href="index.php">← Continue Shopping</a>
    <small>© <?= date('Y') ?> Foodie Corner. Student Mini Project.</small>
</footer>
</body>
</html>
