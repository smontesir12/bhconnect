<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('tenant');

$u = currentUser();
$tenantId = (int)$u['tenant_id'];

$msg = ''; $err = '';

/* ---------- Submit a correction request ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit') {
    $eventType = $_POST['requested_event'] ?? '';
    $reqTime   = str_replace('T', ' ', $_POST['requested_time'] ?? '');
    $reason    = trim($_POST['reason'] ?? '');

    if (!in_array($eventType, ['check_in', 'check_out'])) {
        $err = "Please select a valid event type.";
    } elseif ($reqTime === '' || strlen($reqTime) < 10) {
        $err = "Please enter a valid date and time.";
    } elseif ($reason === '') {
        $err = "Please explain why you are requesting this correction.";
    } elseif (strtotime($reqTime) > time()) {
        $err = "You cannot request a correction for a future time.";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO correction_requests 
              (tenant_id, requested_event, requested_time, reason, status) 
            VALUES (?, ?, ?, ?, 'pending')
        ");
        $stmt->bind_param("isss", $tenantId, $eventType, $reqTime, $reason);
        $stmt->execute();
        $msg = "Correction request submitted. The landlord will review it shortly.";
    }
}

/* ---------- List my requests ---------- */
$stmt = $conn->prepare("
    SELECT * FROM correction_requests 
    WHERE tenant_id = ? 
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $tenantId);
$stmt->execute();
$requests = $stmt->get_result();

include '../includes/header.php';
?>

<h1>Correction Requests</h1>
<?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>

<div class="alert alert-info">
  Forgot to scan? Use this form to request a correction. Approved requests are added 
  to your occupancy log and are used by the utility allocation algorithm.
</div>

<!-- ============ SUBMIT FORM ============ -->
<div class="card">
  <h2>Submit a Correction Request</h2>
  <form method="post">
    <input type="hidden" name="action" value="submit">

    <div class="grid-2">
      <div>
        <label>Missed Event Type</label>
        <select name="requested_event" required>
          <option value="">— Select —</option>
          <option value="check_in">Check-In (I returned to the boarding house)</option>
          <option value="check_out">Check-Out (I left the boarding house)</option>
        </select>
      </div>
      <div>
        <label>Actual Date &amp; Time</label>
        <input type="datetime-local" name="requested_time" required>
      </div>
    </div>

    <label>Reason</label>
    <textarea name="reason" rows="3" required placeholder="e.g. I forgot to scan when I left for the weekend on October 5, 2026."></textarea>

    <button type="submit">Submit Request</button>
  </form>
</div>

<!-- ============ MY REQUESTS ============ -->
<div class="card">
  <h2>My Requests</h2>
  <?php if ($requests->num_rows === 0): ?>
    <p style="color:#6b7280;">You have not submitted any correction requests yet.</p>
  <?php else: ?>
    <table>
      <tr>
        <th>Event</th><th>Requested Time</th><th>Reason</th>
        <th>Status</th><th>Landlord Remarks</th><th>Submitted</th>
      </tr>
      <?php while ($r = $requests->fetch_assoc()): ?>
        <?php
          $cls = [
            'pending'  => 'badge-yellow',
            'approved' => 'badge-green',
            'rejected' => 'badge-red'
          ][$r['status']] ?? 'badge-gray';
        ?>
        <tr>
          <td>
            <span class="badge <?= $r['requested_event'] === 'check_in' ? 'badge-green' : 'badge-yellow' ?>">
              <?= $r['requested_event'] === 'check_in' ? 'Check-In' : 'Check-Out' ?>
            </span>
          </td>
          <td><?= htmlspecialchars($r['requested_time']) ?></td>
          <td><?= htmlspecialchars($r['reason']) ?></td>
          <td><span class="badge <?= $cls ?>"><?= $r['status'] ?></span></td>
          <td><?= $r['landlord_remarks'] ? htmlspecialchars($r['landlord_remarks']) : '—' ?></td>
          <td><?= $r['created_at'] ?></td>
        </tr>
      <?php endwhile; ?>
    </table>
  <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>