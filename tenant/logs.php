<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('tenant');

$u = currentUser();
$tenantId = (int)$u['tenant_id'];

$logs = $conn->query("SELECT * FROM occupancy_logs WHERE tenant_id=$tenantId ORDER BY event_time DESC");
include '../includes/header.php';
?>
<h1>My Occupancy Logs</h1>
<div class="card">
<?php if ($logs->num_rows === 0): ?>
  <p style="color:#6b7280;">No logs yet.</p>
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
<?php include '../includes/footer.php'; ?>