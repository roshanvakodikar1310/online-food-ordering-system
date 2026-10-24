
<?php
require_once __DIR__ . '/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

function foodEscape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}


 // Read products from MySQL
$foods = [];
$categories = [];

try {
    try {
        $stmt = $pdo->query(
            "SELECT id, name, price, category, image, is_available
             FROM products ORDER BY id DESC"
        );
    } catch (PDOException $missingAvailabilityColumn) {
        // Compatibility with a database before the admin manager is first opened.
        $stmt = $pdo->query(
            "SELECT id, name, price, category, image, 1 AS is_available
             FROM products ORDER BY id DESC"
        );
    }

    $products = $stmt->fetchAll();

    foreach ($products as $food) {
        if (isset($food['is_available']) && (int)$food['is_available'] !== 1) {
            continue;
        }
        $id = (int)$food['id'];
        $foods[$id] = $food;

        $category = trim($food['category'] ?? 'Other');

        if ($category !== '') {
            $categories[] = $category;
        }
    }

    $categories = array_values(array_unique($categories));
    sort($categories);

} catch (PDOException $e) {
    error_log('Products loading failed: ' . $e->getMessage());
    $products = [];
}


// Add product to cart
if (isset($_GET['add'])) {
    $id = (int)$_GET['add'];

    if (isset($foods[$id])) {
        if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        $_SESSION['cart'][$id] =
            min(99, (int)($_SESSION['cart'][$id] ?? 0) + 1);
    }

    header('Location: index.php?added=1#menu');
    exit;
}

$cartCount = array_sum($_SESSION['cart'] ?? []);
$added = isset($_GET['added']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Foodie Corner | Order Delicious Food</title>
    <meta name="description" content="Explore delicious food and order your favourites online.">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header class="modern-header">
    <a class="brand" href="index.php">
        <span class="brand-icon">🍔</span>
        <span>Foodie<span class="brand-accent">Corner</span>
            <small>GOOD FOOD, GOOD MOOD</small>
        </span>
    </a>

    <nav class="main-nav">
        <a class="active" href="index.php">Home</a>
        <a href="#menu">Our Menu</a>
        <a href="my_orders.php">My Orders</a>
        <a href="#about">About</a>
        
        <span class="nav-user">Hi, <?= foodEscape($_SESSION['user_name'] ?? 'User') ?></span>
        <a href="logout.php">Log Out</a>
    </nav>

    <div class="header-actions">
        <a class="cart-link" href="cart.php">
            🛒 Cart <span class="cart-badge"><?= (int)$cartCount ?></span>
        </a>
    </div>
</header>

<main>
    <section class="modern-hero">
        <div class="hero-copy">
            <span class="eyebrow">🍽️ FRESH · TASTY · DELIVERED</span>
            <h1>Your favourite food,<br>delivered with <span>love.</span></h1>
            <p>Discover delicious meals made for your cravings.
               Choose your favourites and order in just a few clicks.</p>
            <div class="hero-buttons">
                <a class="primary-button" href="#menu">Explore Menu <span>→</span></a>
                <a class="secondary-button" href="cart.php">View Cart</a>
            </div>
            <div class="hero-points">
                <span>✓ Easy ordering</span>
                <span>✓ Fresh favourites</span>
                <span>✓ COD available</span>
            </div>
        </div>

        <div class="hero-art" aria-label="Delicious food illustration">
            <div class="hero-orbit orbit-one"></div>
            <div class="hero-orbit orbit-two"></div>
            <div class="hero-food">🍕</div>
            <span class="floating-food floating-one">🍔</span>
            <span class="floating-food floating-two">🍟</span>
            <span class="floating-food floating-three">🥤</span>
            <div class="hero-tag">
                <span>😋</span>
                <div><strong>Made for you</strong><small>Every craving counts</small></div>
            </div>
        </div>
    </section>

    <section class="benefit-strip">
        <div><span>🥗</span><p><strong>Fresh Choices</strong><small>Pick your favourite meals</small></p></div>
        <div><span>⚡</span><p><strong>Easy Ordering</strong><small>Simple and quick checkout</small></p></div>
        <div><span>💵</span><p><strong>Pay on Delivery</strong><small>Cash on delivery available</small></p></div>
    </section>

    <section class="menu-section" id="menu">
        <div class="section-heading">
            <div>
                <span class="eyebrow">EXPLORE OUR MENU</span>
                <h2>Find your <span>favourite.</span></h2>
                <p>Something delicious is waiting for you.</p>
            </div>
            <div class="menu-search">
                <span>⌕</span>
                <input type="search" id="foodSearch" placeholder="Search food..." aria-label="Search food">
            </div>
        </div>

        <?php if ($added): ?>
            <div class="success-message">✓ Food added to your cart!</div>
        <?php endif; ?>

        <div class="category-list" id="categoryList">
            <button type="button" class="category-chip selected" data-category="all">🍽️ All Food</button>
            <?php foreach ($categories as $category): ?>
                <button type="button" class="category-chip"
                        data-category="<?= foodEscape(strtolower($category)) ?>">
                    <?= foodEscape($category) ?>
                </button>
            <?php endforeach; ?>
        </div>

        <div class="modern-food-grid" id="foodGrid">
            <?php foreach ($foods as $id => $food):
                $name = $food['name'] ?? 'Food Item';
                $category = $food['category'] ?? 'Other';
                $image = $food['image'] ?? '';
                $price = (float)($food['price'] ?? 0);
            ?>
                <article class="modern-food-card"
                    data-name="<?= foodEscape(strtolower($name)) ?>"
                    data-category="<?= foodEscape(strtolower($category)) ?>">

                    <div class="modern-food-image">
                        <?php if ($image !== ''): ?>
                            <img src="<?= foodEscape($image) ?>"
                                 alt="<?= foodEscape($name) ?>"
                                 loading="lazy"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='grid';">
                        <?php endif; ?>
                        <div class="food-placeholder" <?= $image !== '' ? 'style="display:none"' : '' ?>>🍽️</div>
                        <span class="food-category"><?= foodEscape($category) ?></span>
                    </div>

                    <div class="modern-food-info">
                        <h3><?= foodEscape($name) ?></h3>
                        <p>Freshly selected for your cravings.</p>
                        <div class="food-card-bottom">
                            <strong class="food-price">₹<?= number_format($price, 2) ?></strong>
                            <a class="add-food-button" href="index.php?add=<?= (int)$id ?>">+ Add</a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="no-food-results" id="noFoodResults" hidden>
            <span>🔎</span>
            <h3>No food found</h3>
            <p>Try another search or category.</p>
        </div>
    </section>

    <section class="about-section" id="about">
        <div class="about-emoji">❤️</div>
        <div>
            <span class="eyebrow">MADE WITH CARE</span>
            <h2>Good food brings people together.</h2>
            <p>Browse the menu, add items to your cart, and place your order easily.</p>
        </div>
        <a class="primary-button" href="#menu">Order Now →</a>
    </section>
</main>

<footer class="modern-footer">
    <a class="brand footer-brand" href="index.php">
        <span class="brand-icon">🍔</span>
        <span>Foodie<span class="brand-accent">Corner</span>
            <small>GOOD FOOD, GOOD MOOD</small>
        </span>
    </a>
    <p>Made with ❤️ for food lovers.</p>
    <a href="admin/login.php">Admin Login</a>
    <small>© <?= date('Y') ?> Foodie Corner. Student Mini Project.</small>
</footer>

<script src="script.js"></script>
</body>
</html>
