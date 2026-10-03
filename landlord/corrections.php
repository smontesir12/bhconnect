<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('landlord');

$msg = ''; $err = '';

/* ---------- Approve a correction request ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'approve') {
    $reqId = (int)$_POST['id'];

    // Fetch the request
    $req = $conn->query("SELECT * FROM correction_requests WHERE id=$reqId")->fetch_assoc();
    if (!$req) {
        $err = "Correction request not found.";
    } elseif ($req['status'] !== 'pending') {
        $err = "This request has already been processed.";
    } else {
        // Insert the approved occupancy log
        $stmt = $conn->prepare("
            INSERT INTO occupancy_logs 
              (tenant_id, event_type, event_time, status) 
            VALUES (?, ?, ?, 'approved')
        ");
        $stmt->bind_param("iss", $req['tenant_id'], $req['requested_event'], $req['requested_time']);
        $stmt->execute();
        $newLogId = $conn->insert_id;

        // Mark the request as approved and link it to the log
        $remarks = trim($_POST['landlord_remarks'] ?? '');
        $stmt = $conn->prepare("
            UPDATE correction_requests 
            SET status='approved', landlord_remarks=?, log_id=? 
            WHERE id=?
        ");
        $stmt->bind_param("sii", $remarks, $newLogId, $reqId);
        $stmt->execute();

        $msg = "Request approved. Occupancy log created.";
    }
}

/* ---------- Reject a correction request ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reject') {
    $reqId = (int)$_POST['id'];
    $remarks = trim($_POST['landlord_remarks'] ?? '');

    if ($remarks === '') {
        $err = "Please provide a reason for rejecting the request.";
    } else {
        $stmt = $conn->prepare("
            UPDATE correction_requests 
            SET status='rejected', landlord_remarks=? 
            WHERE id=? AND status='pending'
        ");
        $stmt->bind_param("si", $remarks, $reqId);
        $stmt->execute();
        $msg = "Request rejected.";
    }
}

/* ---------- Fetch requests grouped by status ---------- */
$pending = $conn->query("
    SELECT cr.*, t.full_name 
    FROM correction_requests cr
    JOIN tenants t ON t.id = cr.tenant_id
    WHERE cr.status = 'pending'
    ORDER BY cr.created_at ASC
");

$resolved = $conn->query("
    SELECT cr.*, t.full_name 
    FROM correction_requests cr
    JOIN tenants t ON t.id = cr.tenant_id
    WHERE cr.status IN ('approved', 'rejected')
    ORDER BY cr.created_at DESC
    LIMIT 20
");

include '../includes/header.php';
?>

<h1>Correction Requests</h1>
<?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>

<div class="alert alert-info">
  Review occupancy corrections requested by tenants. Approving a request adds the 
  corresponding event to the tenant's occupancy log with status <strong>approved</strong>, 
  which the utility allocation algorithm will then include.
</div>

<!-- ============ PENDING REQUESTS ============ -->
<div class="card">
  <h2>⏳ Pending Requests (<?= $pending->num_rows ?>)</h2>
  <?php if ($pending->num_rows === 0): ?>
    <p style="color:#6b7280;">No pending requests. All caught up!</p>
  <?php else: ?>
    <?php while ($r = $pending->fetch_assoc()): ?>
      <div style="border: 1px solid #e5e7eb; border-radius: 6px; padding: 14px; margin-bottom: 12px; background: #fafbfc;">
        <div style="display: flex; justify-content: space-between; align-items: start; gap: 16px; flex-wrap: wrap;">
          <div style="flex: 1; min-width: 250px;">
            <div style="font-weight: 600; font-size: 15px; margin-bottom: 6px;">
              <?= htmlspecialchars($r['full_name']) ?>
              <span class="badge <?= $r['requested_event'] === 'check_in' ? 'badge-green' : 'badge-yellow' ?>" style="margin-left: 8px;">
                <?= $r['requested_event'] === 'check_in' ? 'Check-In' : 'Check-Out' ?>
              </span>
            </div>
            <div style="font-size: 13px; color: #475569; margin-bottom: 4px;">
              <strong>Requested time:</strong> <?= htmlspecialchars($r['requested_time']) ?>
            </div>
            <div style="font-size: 13px; color: #475569; margin-bottom: 4px;">
              <strong>Reason:</strong> <?= htmlspecialchars($r['reason']) ?>
            </div>
            <div style="font-size: 12px; color: #94a3b8;">
              Submitted: <?= $r['created_at'] ?>
            </div>
          </div>

          <div style="min-width: 280px;">
            <!-- Approve form -->
            <form method="post" style="margin-bottom: 8px;">
              <input type="hidden" name="action" value="approve">
              <input type="hidden" name="id" value="<?= $r['id'] ?>">
              <input name="landlord_remarks" placeholder="Optional remarks" style="margin-bottom: 6px;">
              <button type="submit" class="btn btn-sm">✓ Approve</button>
            </form>

            <!-- Reject form -->
            <form method="post" onsubmit="return confirm('Reject this request?')">
              <input type="hidden" name="action" value="reject">
              <input type="hidden" name="id" value="<?= $r['id'] ?>">
              <input name="landlord_remarks" placeholder="Reason for rejection (required)" style="margin-bottom: 6px;">
              <button type="submit" class="btn btn-sm btn-danger">✗ Reject</button>
            </form>
          </div>
        </div>
      </div>
    <?php endwhile; ?>
  <?php endif; ?>
</div>

<!-- ============ RESOLVED REQUESTS ============ -->
<div class="card">
  <h2>✅ Recently Resolved</h2>
  <?php if ($resolved->num_rows === 0): ?>
    <p style="color:#6b7280;">No resolved requests yet.</p>
  <?php else: ?>
    <table>
      <tr>
        <th>Tenant</th><th>Event</th><th>Requested Time</th>
        <th>Status</th><th>Remarks</th><th>Resolved</th>
      </tr>
      <?php while ($r = $resolved->fetch_assoc()): ?>
        <tr>
          <td><?= htmlspecialchars($r['full_name']) ?></td>
          <td>
            <span class="badge <?= $r['requested_event'] === 'check_in' ? 'badge-green' : 'badge-yellow' ?>">
              <?= $r['requested_event'] === 'check_in' ? 'Check-In' : 'Check-Out' ?>
            </span>
          </td>
          <td><?= htmlspecialchars($r['requested_time']) ?></td>
          <td>
            <span class="badge <?= $r['status'] === 'approved' ? 'badge-green' : 'badge-red' ?>">
              <?= $r['status'] ?>
            </span>
          </td>
          <td><?= $r['landlord_remarks'] ? htmlspecialchars($r['landlord_remarks']) : '—' ?></td>
          <td><?= $r['created_at'] ?></td>
        </tr>
      <?php endwhile; ?>
    </table>
  <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>