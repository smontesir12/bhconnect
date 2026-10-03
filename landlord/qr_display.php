<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('landlord');

/* ---------- Get or create an active QR session ---------- */
$now = date('Y-m-d H:i:s');
$res = $conn->query("SELECT * FROM qr_sessions WHERE expires_at > '$now' ORDER BY id DESC LIMIT 1");

if ($res->num_rows > 0) {
    $session = $res->fetch_assoc();
} else {
    $token   = bin2hex(random_bytes(8));  // shorter, easier for demo
    $expires = date('Y-m-d H:i:s', strtotime('+5 minutes'));
    $stmt = $conn->prepare("INSERT INTO qr_sessions (token, expires_at) VALUES (?, ?)");
    $stmt->bind_param("ss", $token, $expires);
    $stmt->execute();
    $session = $conn->query("SELECT * FROM qr_sessions WHERE id = " . $conn->insert_id)->fetch_assoc();
}

$qrImg = "https://api.qrserver.com/v1/create-qr-code/?size=280x280&data=" . urlencode($session['token']);

include '../includes/header.php';
?>

<h1>Authorized QR Code</h1>

<div class="alert alert-info">
  Display this QR code at the boarding house entrance. Tenants scan or enter the token in their account to record check-in / check-out. This code expires in 5 minutes.
</div>

<div class="card" style="text-align:center;">
  <img src="<?= $qrImg ?>" alt="QR Code"
       style="border: 4px solid #fff; box-shadow: 0 2px 10px rgba(0,0,0,0.15); border-radius: 8px;">

  <p style="font-size:14px; color:#6b7280; margin-top:16px;">
    Token: <code style="font-size:16px; background:#f3f4f6; padding:4px 8px; border-radius:4px;"><?= htmlspecialchars($session['token']) ?></code><br>
    Expires at: <strong><?= $session['expires_at'] ?></strong>
  </p>

  <div style="margin-top:16px;">
    <a href="qr_display.php" class="btn">🔄 Refresh QR Code</a>
  </div>
</div>

<?php include '../includes/footer.php'; ?>