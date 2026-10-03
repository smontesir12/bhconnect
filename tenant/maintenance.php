<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('tenant');

$u = currentUser();
$tenantId = (int)$u['tenant_id'];

$msg = ''; $err = '';

/* ---------- Submit a maintenance request ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit') {
    $category    = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $urgency     = $_POST['urgency'] ?? 'low';

    if ($category === '' || $description === '') {
        $err = "Please select a category and describe the issue.";
    } elseif (!in_array($urgency, ['low','medium','high'])) {
        $err = "Invalid urgency level.";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO maintenance_requests (tenant_id, category, description, urgency, status)
            VALUES (?, ?, ?, ?, 'pending')
        ");
        $stmt->bind_param("isss", $tenantId, $category, $description, $urgency);
        $stmt->execute();
        $msg = "Maintenance request submitted. The landlord will review it shortly.";
    }
}

/* ---------- List my requests ---------- */
$stmt = $conn->prepare("
    SELECT * FROM maintenance_requests 
    WHERE tenant_id = ? 
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $tenantId);
$stmt->execute();
$requests = $stmt->get_result();

include '../includes/header.php';
?>

<h1>Maintenance Requests</h1>
<?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>

<div class="alert alert-info">
  Report an issue in your room or in the common areas. You will see the status 
  update here as the landlord processes your request.
</div>

<!-- ============ SUBMIT FORM ============ -->
<div class="card">
  <h2>Report an Issue</h2>
  <form method="post">
    <input type="hidden" name="action" value="submit">

    <div class="grid-2">
      <div>
        <label>Category</label>
        <select name="category" required>
          <option value="">— Select —</option>
          <option value="Plumbing">Plumbing (leak, clogged drain, faucet)</option>
          <option value="Electrical">Electrical (outlet, light, wiring)</option>
          <option value="Furniture">Furniture (bed, cabinet, chair)</option>
          <option value="Door/Lock">Door / Lock</option>
          <option value="Appliance">Appliance (fan, aircon, heater)</option>
          <option value="Pest">Pest Control</option>
          <option value="Other">Other</option>
        </select>
      </div>
      <div>
        <label>Urgency</label>
        <select name="urgency" required>
          <option value="low">Low — Can wait</option>
          <option value="medium" selected>Medium — Needs attention soon</option>
          <option value="high">High — Urgent</option>
        </select>
      </div>
    </div>

    <label>Description</label>
    <textarea name="description" rows="4" required 
      placeholder="Describe the issue clearly. Example: The faucet in Room 101 is leaking continuously and water is pooling on the floor."></textarea>

    <button type="submit">Submit Request</button>
  </form>
</div>

<!-- ============ MY REQUESTS ============ -->
<div class="card">
  <h2>My Requests</h2>
  <?php if ($requests->num_rows === 0): ?>
    <p style="color:#6b7280;">You have not submitted any maintenance requests yet.</p>
  <?php else: ?>
    <table>
      <tr>
        <th>Category</th><th>Description</th><th>Urgency</th>
        <th>Status</th><th>Submitted</th>
      </tr>
      <?php while ($r = $requests->fetch_assoc()): ?>
        <?php
          $urgCls = ['low'=>'badge-gray','medium'=>'badge-yellow','high'=>'badge-red'][$r['urgency']] ?? 'badge-gray';
          $stCls  = [
            'pending'     => 'badge-yellow',
            'in_progress' => 'badge-gray',
            'resolved'    => 'badge-green',
            'closed'      => 'badge-gray'
          ][$r['status']] ?? 'badge-gray';
        ?>
        <tr>
          <td><?= htmlspecialchars($r['category']) ?></td>
          <td><?= htmlspecialchars($r['description']) ?></td>
          <td><span class="badge <?= $urgCls ?>"><?= $r['urgency'] ?></span></td>
          <td><span class="badge <?= $stCls ?>"><?= str_replace('_',' ', $r['status']) ?></span></td>
          <td><?= $r['created_at'] ?></td>
        </tr>
      <?php endwhile; ?>
    </table>
  <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>