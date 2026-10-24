<?php
require_once __DIR__ . '/../config.php';

if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$stats = [
    'products' => 0,
    'orders' => 0,
    'pending' => 0,
    'revenue' => 0.0,
];
$recentOrders = [];
$loadError = '';

try {
    // Use MySQL as the single source of truth, matching the storefront,
    // checkout, product management, and order management pages.
    $stats['products'] = (int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
    $stats['orders'] = (int)$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();

    $pendingStmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'Pending'");
    $stats['pending'] = (int)$pendingStmt->fetchColumn();

    // Revenue is calculated from placed orders, excluding cancelled orders.
    $revenueStmt = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE status <> 'Cancelled'");
    $stats['revenue'] = (float)$revenueStmt->fetchColumn();

    $recentOrders = $pdo->query(
        'SELECT id, customer_name, total_amount, status, created_at
         FROM orders ORDER BY id DESC LIMIT 5'
    )->fetchAll();
} catch (PDOException $e) {
    error_log('Admin dashboard loading failed: ' . $e->getMessage());
    $loadError = 'Dashboard data could not be loaded. Please check that the MySQL tables are available.';
}

function dashboardEscape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Foodie Corner</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .dashboard-wrap{width:min(1180px,94%);margin:32px auto 60px}
        .dashboard-top{display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:24px}
        .dashboard-top h1{margin:0 0 8px}
        .dashboard-top p{margin:0;color:#777}
        .dashboard-actions{display:flex;gap:10px;flex-wrap:wrap}
        .dashboard-actions a,.dashboard-link{display:inline-block;padding:10px 15px;border-radius:10px;text-decoration:none;font-weight:700}
        .dashboard-actions a{background:#176b45;color:#fff}
        .dashboard-actions a.secondary{background:#f2f4f2;color:#234b37}
        .dashboard-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin:24px 0}
        .dashboard-stat{background:#fff;border:1px solid #e8eee9;border-radius:16px;padding:22px;box-shadow:0 8px 24px rgba(20,60,40,.06)}
        .dashboard-stat .stat-label{color:#66756b;font-size:.92rem;margin:0 0 12px}
        .dashboard-stat .stat-value{font-size:clamp(1.5rem,3vw,2rem);font-weight:800;color:#174c3c;margin:0}
        .dashboard-panel{background:#fff;border:1px solid #e8eee9;border-radius:16px;padding:22px;box-shadow:0 8px 24px rgba(20,60,40,.05);overflow-x:auto}
        .dashboard-panel h2{margin-top:0}
        .dashboard-table{width:100%;border-collapse:collapse;min-width:620px}
        .dashboard-table th,.dashboard-table td{text-align:left;padding:13px 12px;border-bottom:1px solid #edf0ed}
        .dashboard-table th{color:#496052;background:#f7faf7;font-size:.9rem}
        .status-pill{display:inline-block;padding:5px 10px;border-radius:20px;background:#fff3d8;color:#805a00;font-size:.82rem;font-weight:700}
        .dashboard-message{padding:12px 15px;border-radius:10px;background:#fff3e0;color:#754c00;margin:16px 0}
        @media(max-width:850px){.dashboard-stats{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:480px){.dashboard-stats{grid-template-columns:1fr}.dashboard-stat,.dashboard-panel{padding:17px}}
    </style>
</head>
<body>
<div class="dashboard-wrap">
    <header class="dashboard-top">
        <div>
            <h1>Admin Dashboard</h1>
            <p>Welcome back! Here is your Foodie Corner store overview.</p>
        </div>
        <nav class="dashboard-actions" aria-label="Admin navigation">
            <a href="products.php">Manage Products</a>
            <a href="orders.php">Manage Orders</a>
            <a class="secondary" href="../index.php">View Website</a>
            <a class="secondary" href="logout.php">Logout</a>
        </nav>
    </header>

    <?php if ($loadError !== ''): ?>
        <p class="dashboard-message"><?= dashboardEscape($loadError) ?></p>
    <?php endif; ?>

    <section class="dashboard-stats" aria-label="Store statistics">
        <article class="dashboard-stat">
            <p class="stat-label">Total Food Items</p>
            <p class="stat-value"><?= number_format($stats['products']) ?></p>
        </article>
        <article class="dashboard-stat">
            <p class="stat-label">Total Orders</p>
            <p class="stat-value"><?= number_format($stats['orders']) ?></p>
        </article>
        <article class="dashboard-stat">
            <p class="stat-label">Pending Orders</p>
            <p class="stat-value"><?= number_format($stats['pending']) ?></p>
        </article>
        <article class="dashboard-stat">
            <p class="stat-label">Order Value (excluding cancelled)</p>
            <p class="stat-value">₹<?= number_format($stats['revenue'], 2) ?></p>
        </article>
    </section>

    <section class="dashboard-panel">
        <h2>Recent Orders</h2>
        <?php if (!$recentOrders): ?>
            <p>No orders have been placed yet.</p>
        <?php else: ?>
            <table class="dashboard-table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recentOrders as $order): ?>
                    <tr>
                        <td>#<?= (int)$order['id'] ?></td>
                        <td><?= dashboardEscape($order['customer_name']) ?></td>
                        <td>₹<?= number_format((float)$order['total_amount'], 2) ?></td>
                        <td><span class="status-pill"><?= dashboardEscape($order['status']) ?></span></td>
                        <td><?= dashboardEscape($order['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
</div>
</body>
</html>
