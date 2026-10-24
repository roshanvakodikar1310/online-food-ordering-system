<?php
require_once __DIR__ . '/../config.php';

if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

function productEscape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$message = '';
$messageType = 'success';

// Add availability support to older installations without requiring manual SQL.
try {
    $columns = $pdo->query("SHOW COLUMNS FROM products LIKE 'is_available'")->fetchAll();
    if (!$columns) {
        $pdo->exec("ALTER TABLE products ADD COLUMN is_available TINYINT(1) NOT NULL DEFAULT 1");
    }
} catch (PDOException $e) {
    error_log('Availability column check failed: ' . $e->getMessage());
}

if (empty($_SESSION['product_csrf'])) {
    $_SESSION['product_csrf'] = bin2hex(random_bytes(32));
}

function saveProductImage($file, $oldPath = '') {
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return $oldPath;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Image upload failed. Please try again.');
    }
    if ($file['size'] > 3 * 1024 * 1024) {
        throw new RuntimeException('Image must be smaller than 3 MB.');
    }
    $info = @getimagesize($file['tmp_name']);
    $mime = $info['mime'] ?? '';
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!$info || !isset($extensions[$mime])) {
        throw new RuntimeException('Only valid JPG, PNG or WEBP images are allowed.');
    }

    $imageDir = dirname(__DIR__) . '/images/';
    if (!is_dir($imageDir) && !mkdir($imageDir, 0755, true) && !is_dir($imageDir)) {
        throw new RuntimeException('Could not create the images folder.');
    }
    $filename = bin2hex(random_bytes(12)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($file['tmp_name'], $imageDir . $filename)) {
        throw new RuntimeException('Could not save the uploaded image. Check folder permissions.');
    }
    return 'images/' . $filename;
}

// All mutations use POST, including delete and availability toggle.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['product_csrf'], $_POST['csrf_token'] ?? '')) {
        $message = 'Your session expired. Please refresh the page and try again.';
        $messageType = 'error';
    } else {
        $action = $_POST['action'] ?? 'add';
        try {
            if ($action === 'delete') {
                $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                if (!$id || $id < 1) {
                    throw new RuntimeException('Invalid product selected.');
                }
                $stmt = $pdo->prepare('DELETE FROM products WHERE id = ?');
                $stmt->execute([$id]);
                $message = $stmt->rowCount() ? 'Food item deleted successfully.' : 'Food item was not found.';
            } elseif ($action === 'toggle') {
                $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                $available = (($_POST['is_available'] ?? '0') === '1') ? 1 : 0;
                if (!$id || $id < 1) {
                    throw new RuntimeException('Invalid product selected.');
                }
                $stmt = $pdo->prepare('UPDATE products SET is_available = ? WHERE id = ?');
                $stmt->execute([$available, $id]);
                $message = $available ? 'Food item is now available.' : 'Food item marked unavailable.';
            } elseif ($action === 'add') {
                $name = trim($_POST['name'] ?? '');
                $price = filter_var($_POST['price'] ?? '', FILTER_VALIDATE_FLOAT);
                $category = trim($_POST['category'] ?? '');
                if ($name === '' || mb_strlen($name) > 120 || $price === false || $price <= 0 || $category === '') {
                    throw new RuntimeException('Enter a valid name, price and category.');
                }
                if (!isset($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
                    throw new RuntimeException('Please select a food image.');
                }
                $imagePath = saveProductImage($_FILES['image']);
                $stmt = $pdo->prepare('INSERT INTO products (name, price, category, image, is_available) VALUES (?, ?, ?, ?, 1)');
                $stmt->execute([$name, $price, $category, $imagePath]);
                $message = 'Food item added successfully!';
            } elseif ($action === 'edit') {
                $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
                $name = trim($_POST['name'] ?? '');
                $price = filter_var($_POST['price'] ?? '', FILTER_VALIDATE_FLOAT);
                $category = trim($_POST['category'] ?? '');
                if (!$id || $id < 1 || $name === '' || mb_strlen($name) > 120 || $price === false || $price <= 0 || $category === '') {
                    throw new RuntimeException('Enter a valid name, price and category.');
                }
                $stmt = $pdo->prepare('SELECT image FROM products WHERE id = ?');
                $stmt->execute([$id]);
                $existing = $stmt->fetch();
                if (!$existing) {
                    throw new RuntimeException('Food item was not found.');
                }
                $imagePath = saveProductImage($_FILES['image'] ?? null, $existing['image'] ?? '');
                $stmt = $pdo->prepare('UPDATE products SET name = ?, price = ?, category = ?, image = ? WHERE id = ?');
                $stmt->execute([$name, $price, $category, $imagePath, $id]);
                if ($imagePath !== ($existing['image'] ?? '') && !empty($existing['image'])) {
                    $oldFile = dirname(__DIR__) . '/' . ltrim($existing['image'], '/');
                    if (is_file($oldFile) && strpos(realpath($oldFile), realpath(dirname(__DIR__) . '/images')) === 0) {
                        @unlink($oldFile);
                    }
                }
                $message = 'Food item updated successfully!';
            }
        } catch (RuntimeException $e) {
            $message = $e->getMessage();
            $messageType = 'error';
        } catch (PDOException $e) {
            error_log('Product management action failed: ' . $e->getMessage());
            $message = 'Could not complete this action. If this item is used in an order, it may not be safe to delete it.';
            $messageType = 'error';
        }
    }
}

$editProduct = null;
if (isset($_GET['edit']) && ctype_digit((string)$_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT id, name, price, category, image FROM products WHERE id = ?');
    $stmt->execute([(int)$_GET['edit']]);
    $editProduct = $stmt->fetch() ?: null;
}

try {
    $products = $pdo->query('SELECT id, name, price, category, image, is_available FROM products ORDER BY id DESC')->fetchAll();
} catch (PDOException $e) {
    error_log('Products loading failed: ' . $e->getMessage());
    $products = [];
    $message = 'Could not load products from the database.';
    $messageType = 'error';
}
$categories = ['Burgers', 'Pizza', 'Pasta', 'Snacks', 'Drinks', 'Desserts', 'Indian', 'Other'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Manage Food | Foodie Corner</title><link rel="stylesheet" href="../style.css">
<style>
.pm-wrap{width:min(1180px,94%);margin:30px auto 60px;color:#20352a}.pm-top{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:22px}.pm-top h1{margin:0 0 5px}.pm-top p{margin:0;color:#6b7b70}.pm-actions{display:flex;gap:9px;flex-wrap:wrap}.pm-btn,.pm-actions a{border:0;border-radius:10px;padding:11px 15px;background:#176b45;color:#fff;text-decoration:none;font-weight:700;cursor:pointer;font:inherit;font-weight:700}.pm-actions a.secondary{background:#edf3ee;color:#234b37}.pm-alert{padding:13px 16px;border-radius:11px;margin:14px 0;background:#e9f7ed;color:#17623b}.pm-alert.error{background:#fff0ee;color:#9b2c20}.pm-panel{background:#fff;border:1px solid #e5ece6;border-radius:18px;padding:22px;margin:20px 0;box-shadow:0 9px 26px rgba(25,60,40,.06)}.pm-panel h2{margin:0 0 18px}.pm-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.pm-field{display:flex;flex-direction:column;gap:7px}.pm-field.full{grid-column:1/-1}.pm-field label{font-weight:700;font-size:.93rem}.pm-field input,.pm-field select{width:100%;padding:12px;border:1px solid #d9e2db;border-radius:10px;background:#fff;font:inherit;box-sizing:border-box}.pm-form-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.pm-cancel{color:#42614d;text-decoration:none;font-weight:700}.pm-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}.pm-card{border:1px solid #e7ede8;border-radius:15px;overflow:hidden;background:#fff;display:flex;flex-direction:column}.pm-card img{width:100%;height:175px;object-fit:cover;background:#f3f5f3}.pm-card-body{padding:15px;display:flex;flex-direction:column;gap:8px;flex:1}.pm-card h3{margin:0;font-size:1.12rem}.pm-meta{display:flex;justify-content:space-between;align-items:center;gap:8px;color:#6d7b71;font-size:.92rem}.pm-price{font-size:1.15rem;font-weight:800;color:#176b45}.pm-badge{font-size:.78rem;font-weight:800;padding:5px 9px;border-radius:20px;background:#e7f7ec;color:#17623b}.pm-badge.off{background:#fff0e8;color:#9a4b16}.pm-card-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:5px}.pm-card-actions a,.pm-card-actions button{border:0;border-radius:8px;padding:8px 10px;text-decoration:none;font-weight:700;font-size:.86rem;cursor:pointer}.pm-edit{background:#edf3ee;color:#234b37}.pm-delete{background:#fff0ee;color:#a52c20}.pm-toggle{background:#fff3d8;color:#7a5800}.pm-empty{text-align:center;padding:30px;color:#718075}.pm-inline{display:inline}.pm-note{font-size:.87rem;color:#718075;margin:0}@media(max-width:850px){.pm-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:560px){.pm-form,.pm-grid{grid-template-columns:1fr}.pm-panel{padding:16px}.pm-card img{height:200px}}
</style></head>
<body><main class="pm-wrap">
<header class="pm-top"><div><h1>🍽️ Food Product Management</h1><p>Add, update and control the items shown on your website.</p></div><nav class="pm-actions"><a href="dashboard.php">Dashboard</a><a class="secondary" href="../index.php">View Website</a><a class="secondary" href="logout.php">Logout</a></nav></header>
<?php if ($message !== ''): ?><div class="pm-alert <?= $messageType === 'error' ? 'error' : '' ?>" role="status"><?= productEscape($message) ?></div><?php endif; ?>
<section class="pm-panel" id="product-form"><h2><?= $editProduct ? '✏️ Edit Food Item' : '➕ Add New Food Item' ?></h2>
<form class="pm-form" method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf_token" value="<?= productEscape($_SESSION['product_csrf']) ?>">
<input type="hidden" name="action" value="<?= $editProduct ? 'edit' : 'add' ?>">
<?php if ($editProduct): ?><input type="hidden" name="id" value="<?= (int)$editProduct['id'] ?>"><?php endif; ?>
<div class="pm-field"><label for="name">Food name</label><input id="name" name="name" maxlength="120" placeholder="e.g. Paneer Tikka Pizza" value="<?= productEscape($editProduct['name'] ?? '') ?>" required></div>
<div class="pm-field"><label for="price">Price (₹)</label><input id="price" type="number" name="price" min="0.01" step="0.01" placeholder="e.g. 199" value="<?= productEscape($editProduct['price'] ?? '') ?>" required></div>
<div class="pm-field"><label for="category">Category</label><select id="category" name="category" required><option value="">Choose category</option><?php foreach ($categories as $category): ?><option value="<?= productEscape($category) ?>" <?= (($editProduct['category'] ?? '') === $category) ? 'selected' : '' ?>><?= productEscape($category) ?></option><?php endforeach; ?><?php if ($editProduct && !in_array($editProduct['category'], $categories, true)): ?><option selected value="<?= productEscape($editProduct['category']) ?>"><?= productEscape($editProduct['category']) ?></option><?php endif; ?></select></div>
<div class="pm-field"><label for="image">Food image <?= $editProduct ? '(optional — choose only to replace)' : '' ?></label><input id="image" type="file" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" <?= $editProduct ? '' : 'required' ?>><p class="pm-note">JPG, PNG or WEBP · Maximum 3 MB</p></div>
<div class="pm-field full"><div class="pm-form-actions"><button class="pm-btn" type="submit"><?= $editProduct ? 'Save Changes' : 'Add Food Item' ?></button><?php if ($editProduct): ?><a class="pm-cancel" href="products.php">Cancel edit</a><?php endif; ?></div></div>
</form></section>
<section class="pm-panel"><h2>📋 Existing Food Items <span style="color:#718075;font-size:.9rem;font-weight:500">(<?= count($products) ?>)</span></h2>
<?php if (!$products): ?><div class="pm-empty">No food items found. Use the form above to add your first item.</div><?php else: ?><div class="pm-grid">
<?php foreach ($products as $product): $available = (int)($product['is_available'] ?? 1) === 1; ?>
<article class="pm-card"><img src="../<?= productEscape($product['image'] ?? '') ?>" alt="<?= productEscape($product['name']) ?>" onerror="this.style.display='none'"><div class="pm-card-body"><div class="pm-meta"><span><?= productEscape($product['category']) ?></span><span class="pm-badge <?= $available ? '' : 'off' ?>"><?= $available ? 'Available' : 'Unavailable' ?></span></div><h3><?= productEscape($product['name']) ?></h3><div class="pm-price">₹<?= number_format((float)$product['price'], 2) ?></div><div class="pm-card-actions"><a class="pm-edit" href="products.php?edit=<?= (int)$product['id'] ?>#product-form">✏️ Edit</a>
<form class="pm-inline" method="post"><input type="hidden" name="csrf_token" value="<?= productEscape($_SESSION['product_csrf']) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$product['id'] ?>"><input type="hidden" name="is_available" value="<?= $available ? '0' : '1' ?>"><button class="pm-toggle" type="submit"><?= $available ? 'Mark unavailable' : 'Make available' ?></button></form>
<form class="pm-inline" method="post" onsubmit="return confirm('Delete this food item? If it is linked to an order, deletion may be blocked.');"><input type="hidden" name="csrf_token" value="<?= productEscape($_SESSION['product_csrf']) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$product['id'] ?>"><button class="pm-delete" type="submit">🗑 Delete</button></form></div></div></article>
<?php endforeach; ?></div><?php endif; ?></section>
</main></body></html>
