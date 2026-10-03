<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('tenant');

$u = currentUser();
$tenantId = (int)$u['tenant_id'];

$msg = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = trim($_POST['token'] ?? '');
    $now   = date('Y-m-d H:i:s');

    // 1. Validate token
    $stmt = $conn->prepare("SELECT * FROM qr_sessions WHERE token = ? AND expires_at > ?");
    $stmt->bind_param("ss", $token, $now);
    $stmt->execute();
    $session = $stmt->get_result()->fetch_assoc();

    if (!$session) {
        $err = "Invalid or expired QR code. Please ask the landlord to refresh it.";
    } else {
        // 2. Determine event type (auto-toggle)
        $last = $conn->query("SELECT event_type FROM occupancy_logs WHERE tenant_id=$tenantId ORDER BY event_time DESC LIMIT 1")->fetch_assoc();
        $nextType = (!$last || $last['event_type'] === 'check_out') ? 'check_in' : 'check_out';

        // 3. Prevent duplicate scans within 30 seconds
        $recent = $conn->query("SELECT id FROM occupancy_logs WHERE tenant_id=$tenantId AND event_time > DATE_SUB('$now', INTERVAL 30 SECOND)")->num_rows;
        if ($recent > 0) {
            $err = "You already scanned recently. Please wait 30 seconds before scanning again.";
        } else {
            // 4. Save the log
            $stmt = $conn->prepare("INSERT INTO occupancy_logs (tenant_id, event_type, event_time, qr_session_id, status) VALUES (?, ?, ?, ?, 'valid')");
            $stmt->bind_param("issi", $tenantId, $nextType, $now, $session['id']);
            $stmt->execute();

            $label = ($nextType === 'check_in') ? 'Check-In' : 'Check-Out';
            $msg = "✅ Successfully recorded: <strong>$label</strong> at $now";
        }
    }
}

// Get last log
$last = $conn->query("SELECT * FROM occupancy_logs WHERE tenant_id=$tenantId ORDER BY event_time DESC LIMIT 1")->fetch_assoc();

include '../includes/header.php';
?>

<h1>Scan QR Code</h1>
<?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>

<div class="card">
  <h2>Enter QR Token</h2>
  <p style="font-size:13px; color:#6b7280;">
    Scan the QR code shown at the boarding house entrance using any QR reader, or ask the landlord for the token. Paste it below.
  </p>

  <form method="post">
    <label>QR Token</label>
    <input name="token" required placeholder="e.g. a1b2c3d4e5f6..." value="<?= htmlspecialchars($_GET['token'] ?? '') ?>">
    <button type="submit">Record Occupancy Event</button>
  </form>
</div>

<div class="card">
  <h2>My Last Recorded Event</h2>
  <?php if ($last): ?>
    <p>
      <strong>Type:</strong>
      <span class="badge <?= $last['event_type'] === 'check_in' ? 'badge-green' : 'badge-yellow' ?>">
        <?= $last['event_type'] === 'check_in' ? 'Check-In' : 'Check-Out' ?>
      </span><br>
      <strong>Time:</strong> <?= $last['event_time'] ?><br>
      <strong>Status:</strong> <?= $last['status'] ?>
    </p>
    <p style="font-size:13px; color:#6b7280;">
      The system will automatically toggle to the opposite event on your next scan.
    </p>
  <?php else: ?>
    <p style="color:#6b7280;">No events yet. Your first scan will be recorded as a Check-In.</p>
  <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>