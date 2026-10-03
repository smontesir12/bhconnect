<?php
require_once 'config/db.php';
require_once 'includes/auth.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $u);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user && password_verify($p, $user['password'])) {
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['role']      = $user['role'];
        $_SESSION['tenant_id'] = $user['tenant_id'];

        header("Location: " . ($user['role'] === 'landlord'
            ? '/bhconnect/landlord/dashboard.php'
            : '/bhconnect/tenant/dashboard.php'));
        exit;
    } else {
        $error = "Invalid username or password.";
    }
}
?>
<!DOCTYPE html>
<html><head><title>BHConnect Login</title>
<link rel="stylesheet" href="/bhconnect/assets/css/style.css"></head>
<body>
<div class="login-box">
  <h1>BHConnect</h1>
  <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
  <form method="post">
    <input name="username" placeholder="Username" required>
    <input name="password" type="password" placeholder="Password" required>
    <button type="submit">Log In</button>
  </form>
</div>
</body></html>