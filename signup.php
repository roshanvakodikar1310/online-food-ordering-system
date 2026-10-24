<?php
session_start();
require_once 'db.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || $phone === '' || strlen($password) < 8) {
        $error = 'Please enter valid details. Password must be at least 8 characters.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare(
            "INSERT INTO users (name, email, phone, password)
             VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param("ssss", $name, $email, $phone, $hash);

        if ($stmt->execute()) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $stmt->insert_id;
            $_SESSION['user_name'] = $name;

            header("Location: index.php");
            exit;
        } else {
            $error = $stmt->errno === 1062
                ? 'This email is already registered.'
                : 'Registration failed. Please try again.';
        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Create Your Account</title>
<link rel="stylesheet" href="style.css">
<style>body.auth-page{min-height:100vh;display:grid;place-items:center;padding:24px;background:radial-gradient(circle at 15% 15%,#e2efd7,transparent 32%),var(--paper)}.auth-shell{width:min(100%,960px);display:grid;grid-template-columns:1fr 1fr;overflow:hidden;border:1px solid var(--line);border-radius:28px;background:white;box-shadow:var(--shadow)}.auth-promo{display:flex;flex-direction:column;justify-content:space-between;padding:clamp(28px,5vw,56px);background:var(--green);color:white;min-height:500px}.auth-promo h1{font:800 clamp(30px,4vw,48px)/1.1 Manrope,sans-serif;letter-spacing:-1.5px;margin:30px 0 14px}.auth-promo p{color:#d6e5d9;line-height:1.8}.auth-mark{font-size:34px}.auth-note{font-size:12px;color:#d6e5d9}.auth-form-wrap{padding:clamp(25px,4vw,48px);align-self:center}.auth-form-wrap h2{font:800 30px Manrope,sans-serif;margin:0 0 8px}.auth-subtitle{margin:0 0 24px;color:var(--muted);font-size:13px}.auth-form-wrap form{display:grid;gap:13px}.auth-form-wrap input{width:100%;padding:14px;border-radius:12px}.auth-form-wrap button{width:100%;margin-top:5px;padding:14px}.auth-foot{margin-top:22px;color:var(--muted);font-size:13px}.auth-foot a{font-weight:800}.auth-error{margin:12px 0;padding:12px;border-radius:10px;background:#fff0ee;color:#a32e22;font-size:13px}@media(max-width:680px){.auth-shell{grid-template-columns:1fr}.auth-promo{min-height:auto;padding:25px}.auth-promo h1{font-size:32px;margin:18px 0 8px}.auth-note{display:none}}
</style>
</head>
<body class="auth-page">
<div class="auth-shell">
<section class="auth-promo"><div><div class="auth-mark">🥬 Foodie Corner</div><h1>Good food.<br>Good mood.</h1><p>Fresh favourites, easy ordering, and a little something delicious for your day.</p></div><div class="auth-note">FRESH · TASTY · MADE FOR YOU</div></section>
<section class="auth-form-wrap">
<h2>Create account</h2>
<p class="auth-subtitle">Sign up and get your next favourite meal.</p>

<?php if ($error): ?>
<p class="auth-error"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<form method="POST">
<input name="name" placeholder="Full Name" required maxlength="100">
<input type="email" name="email" placeholder="Email Address" required maxlength="150">
<input type="tel" name="phone" placeholder="Phone Number" required maxlength="20">
<input type="password" name="password" placeholder="Password (minimum 8 characters)" required minlength="8">
<button type="submit">Sign Up</button>
</form>

<p class="auth-foot">Already registered? <a href="login.php">Login</a></p>
</section>
</div>
</body>
</html>