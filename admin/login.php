
<?php
require_once '../config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === 'admin' && $password === 'Admin@123') {
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Invalid username or password!';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | Foodie</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <div class="admin-login">
        <h1>🍔 Foodie Admin</h1>
        <p>Login to manage your food store</p>

        <?php if ($error): ?>
            <p style="color:red;">
                <?= htmlspecialchars($error) ?>
            </p>
        <?php endif; ?>

        <form method="POST">
            <input type="text" name="username"
                   placeholder="Username" required>

            <input type="password" name="password"
                   placeholder="Password" required>

            <button type="submit">Login</button>
        </form>

        <a href="../index.php">← Back to website</a>
    </div>
</body>
</html>
