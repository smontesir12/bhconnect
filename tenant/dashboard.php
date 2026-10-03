<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('tenant');

$u = currentUser();
$tenantId = (int)$u['tenant_id'];
require_once '../includes/notifications.php';

$u = currentUser();
$userId = (int)$u['id'];

// Auto-generate due-date notifications for this tenant
generateDueNotifications($conn, $tenantId, $userId);

// Fetch pending payments
$pendingPayments = $conn->query("
    SELECT * FROM payments
    WHERE tenant_id = $tenantId
      AND status IN ('unpaid','partial')
    ORDER BY due_date IS NULL, due_date ASC
");

/* ---------- Get tenant info + room + active contract ---------- */
$stmt = $conn->prepare("
  SELECT t.*, r.room_number, r.room_type,
         c.move_in, c.move_out, c.rental_rate, c.status AS contract_status
  FROM tenants t
  LEFT JOIN rooms r ON r.id = t.room_id
  LEFT JOIN contracts c ON c.tenant_id = t.id AND c.status = 'active'
  WHERE t.id = ?
  LIMIT 1
");
$stmt->bind_param("i", $tenantId);
$stmt->execute();
$info = $stmt->get_result()->fetch_assoc();

if (!$info) {
    die("Tenant record not found. Please contact the landlord.");
}

/* ---------- Get recent occupancy logs ---------- */
$logStmt = $conn->prepare("
  SELECT * FROM occupancy_logs
  WHERE tenant_id = ?
  ORDER BY event_time DESC
  LIMIT 5
");
$logStmt->bind_param("i", $tenantId);
$logStmt->execute();
$logs = $logStmt->get_result();

/* ---------- Count utility allocations ---------- */
$billStmt = $conn->prepare("
  SELECT COUNT(*) c FROM utility_allocations WHERE tenant_id = ?
");
$billStmt->bind_param("i", $tenantId);
$billStmt->execute();
$billCount = $billStmt->get_result()->fetch_assoc()['c'];

include '../includes/header.php';
?>

<h1>Welcome, <?= htmlspecialchars($info['full_name']) ?> 👋</h1>

<div class="grid-3">
  <div class="stat">
    <div class="num"><?= htmlspecialchars($info['room_number'] ?? 'N/A') ?></div>
    <div class="lbl">My Room</div>
  </div>
  <div class="stat">
    <div class="num">₱<?= number_format($info['rental_rate'] ?? 0, 0) ?></div>
    <div class="lbl">Monthly Rate</div>
  </div>
  <div class="stat">
    <div class="num"><?= htmlspecialchars($info['contract_status'] ?? 'None') ?></div>
    <div class="lbl">Contract Status</div>
  </div>
</div>

<div class="card" style="margin-top:20px;">
  <h2>My Contract</h2>
  <?php if ($info['contract_status'] === 'active'): ?>
    <table>
      <tr><td><strong>Move-In</strong></td><td><?= $info['move_in'] ?></td></tr>
      <tr><td><strong>Move-Out</strong></td><td><?= $info['move_out'] ?: 'Ongoing' ?></td></tr>
      <tr><td><strong>Monthly Rent</strong></td><td>₱<?= number_format($info['rental_rate'], 2) ?></td></tr>
    </table>
  <?php else: ?>
    <p style="color:#6b7280;">No active contract on file. Please coordinate with the landlord.</p>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Quick Actions</h2>
  <a class="btn" href="scan.php">📷 Scan QR (Check In / Out)</a>
  <a class="btn btn-gray" href="logs.php">View My Occupancy Logs</a>
  <a class="btn btn-gray" href="billing.php">View My Billing (<?= $billCount ?>)</a>
  <a class="btn btn-gray" href="maintenance.php">Maintenance Requests</a>
</div>

<div class="card">
  <h2>My Recent Occupancy Logs</h2>
  <?php if ($logs->num_rows === 0): ?>
    <p style="color:#6b7280;">No check-in/check-out events yet. Click "Scan QR" to log your first event.</p>
  <?php else: ?>
    <table>
      <tr><th>Type</th><th>Time</th><th>Status</th></tr>
      <?php while ($l = $logs->fetch_assoc()): ?>
        <tr>
          <td>
            <span class="badge <?= $l['event_type'] === 'check_in' ? 'badge-green' : 'badge-yellow' ?>">
              <?= $l['event_type'] === 'check_in' ? 'Check In' : 'Check Out' ?>
            </span>
          </td>
          <td><?= $l['event_time'] ?></td>
          <td><?= htmlspecialchars($l['status']) ?></td>
        </tr>
      <?php endwhile; ?>
    </table>
  <?php endif; ?>
</div>
<?php if ($pendingPayments->num_rows > 0): ?>
<div class="card" style="margin-top:20px; border-left: 4px solid #d97706;">
  <h2>💳 My Pending Payments</h2>
  <table>
    <tr><th>Description</th><th>Amount</th><th>Due Date</th><th>Status</th></tr>
    <?php while ($p = $pendingPayments->fetch_assoc()): ?>
      <?php
        $isOverdue = $p['due_date'] && $p['due_date'] < date('Y-m-d');
      ?>
      <tr>
        <td><?= htmlspecialchars($p['period_label'] ?: ucfirst($p['payment_type']) . ' Payment') ?></td>
        <td><strong>₱<?= number_format($p['amount'], 2) ?></strong></td>
        <td>
          <?php if ($p['due_date']): ?>
            <span style="<?= $isOverdue ? 'color:#dc2626;font-weight:600;' : '' ?>">
              <?= $p['due_date'] ?><?= $isOverdue ? ' ⚠' : '' ?>
            </span>
          <?php else: ?>—<?php endif; ?>
        </td>
        <td>
          <span class="badge <?= $p['status'] === 'partial' ? 'badge-yellow' : 'badge-red' ?>">
            <?= $p['status'] ?>
          </span>
        </td>
      </tr>
    <?php endwhile; ?>
  </table>
</div>
<?php endif; ?>
<?php include '../includes/footer.php'; ?>