<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('tenant');

$u = currentUser();
$tenantId = (int)$u['tenant_id'];

$bills = $conn->query("
  SELECT ua.*, ub.utility_type, ub.billing_start, ub.billing_end
  FROM utility_allocations ua
  JOIN utility_bills ub ON ub.id = ua.bill_id
  WHERE ua.tenant_id = $tenantId
  ORDER BY ub.billing_start DESC
");

include '../includes/header.php';
?>
<h1>My Billing Statement</h1>
<div class="card">
  <h2>My Utility Shares</h2>
  <?php if ($bills->num_rows === 0): ?>
    <p style="color:#6b7280;">No utility allocations yet.</p>
  <?php else: ?>
    <table>
      <tr><th>Period</th><th>Type</th><th>Approved Days</th><th>Total Days</th><th>My Share</th></tr>
      <?php while ($b = $bills->fetch_assoc()): ?>
        <tr>
          <td><?= $b['billing_start'] ?> → <?= $b['billing_end'] ?></td>
          <td><?= ucfirst($b['utility_type']) ?></td>
          <td><?= $b['approved_days'] ?></td>
          <td><?= $b['total_days'] ?></td>
          <td><strong>₱<?= number_format($b['share_amount'], 2) ?></strong></td>
        </tr>
      <?php endwhile; ?>
    </table>
  <?php endif; ?>
</div>
<?php include '../includes/footer.php'; ?>