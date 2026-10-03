<?php
require '../config/db.php';
require '../includes/auth.php';
require '../includes/utility.php';   // ← pulls in the algorithm
requireRole('landlord');

$msg = ''; $err = '';

/* ---------- Add a new utility bill ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_bill') {
    $start  = $_POST['billing_start'];
    $end    = $_POST['billing_end'];
    $type   = $_POST['utility_type'];
    $amount = (float)$_POST['total_amount'];

    if ($start === '' || $end === '' || $amount <= 0) {
        $err = "Please fill in all fields with valid values.";
    } elseif ($end < $start) {
        $err = "Billing end must be on or after billing start.";
    } else {
        $stmt = $conn->prepare("INSERT INTO utility_bills (billing_start, billing_end, utility_type, total_amount) VALUES (?,?,?,?)");
        $stmt->bind_param("sssd", $start, $end, $type, $amount);
        $stmt->execute();
        $msg = "Utility bill added. Click 'Compute Allocation' to run the algorithm.";
    }
}

/* ---------- Compute allocation for a bill ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'compute') {
    $billId = (int)$_POST['bill_id'];

    // Clear any previous allocation for this bill (so re-running is safe)
    $conn->query("DELETE FROM utility_allocations WHERE bill_id = $billId");

    $result = computeAllocation($conn, $billId);
    if (empty($result['rows'])) {
        $err = "No active tenants found — nothing to allocate.";
    } else {
        $totalDays = $result['total_days'];
        $stmt = $conn->prepare("INSERT INTO utility_allocations (bill_id, tenant_id, approved_days, total_days, share_amount) VALUES (?,?,?,?,?)");
        foreach ($result['rows'] as $r) {
            $stmt->bind_param("iiiid", $billId, $r['tenant_id'], $r['approved_days'], $totalDays, $r['share_amount']);
            $stmt->execute();
        }
        $msg = "Allocation computed for bill #$billId (total approved days: $totalDays).";
    }
}

/* ---------- Delete a bill ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_bill') {
    $billId = (int)$_POST['bill_id'];
    $conn->query("DELETE FROM utility_allocations WHERE bill_id = $billId");
    $conn->query("DELETE FROM utility_bills WHERE id = $billId");
    $msg = "Bill and its allocations deleted.";
}

/* ---------- View a specific bill's allocation ---------- */
$viewBill = null; $viewRows = [];
if (!empty($_GET['view'])) {
    $vid = (int)$_GET['view'];
    $viewBill = $conn->query("SELECT * FROM utility_bills WHERE id = $vid")->fetch_assoc();
    if ($viewBill) {
        $viewRows = $conn->query("
            SELECT ua.*, t.full_name
            FROM utility_allocations ua
            JOIN tenants t ON t.id = ua.tenant_id
            WHERE ua.bill_id = $vid
            ORDER BY t.full_name
        ")->fetch_all(MYSQLI_ASSOC);
    }
}

/* ---------- List of all bills ---------- */
$bills = $conn->query("SELECT * FROM utility_bills ORDER BY billing_start DESC");

include '../includes/header.php';
?>

<h1>Shared Utility Allocation</h1>
<?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>

<!-- ============ ADD BILL FORM ============ -->
<div class="card">
  <h2>Add Utility Bill</h2>
  <form method="post">
    <input type="hidden" name="action" value="add_bill">
    <div class="grid-3">
      <div>
        <label>Billing Start</label>
        <input type="date" name="billing_start" required>
      </div>
      <div>
        <label>Billing End</label>
        <input type="date" name="billing_end" required>
      </div>
      <div>
        <label>Utility Type</label>
        <select name="utility_type">
          <option value="electricity">Electricity</option>
          <option value="water">Water</option>
        </select>
      </div>
      <div>
        <label>Total Bill (₱)</label>
        <input type="number" step="0.01" min="0.01" name="total_amount" required>
      </div>
    </div>
    <button type="submit">Add Bill</button>
  </form>
</div>

<!-- ============ BILL LIST ============ -->
<div class="card">
  <h2>Bills</h2>
  <table>
    <tr>
      <th>#</th><th>Period</th><th>Type</th><th>Total</th>
      <th>Created</th><th>Actions</th>
    </tr>
    <?php while ($b = $bills->fetch_assoc()): ?>
      <tr>
        <td><?= $b['id'] ?></td>
        <td><?= $b['billing_start'] ?> → <?= $b['billing_end'] ?></td>
        <td><?= ucfirst($b['utility_type']) ?></td>
        <td>₱<?= number_format($b['total_amount'], 2) ?></td>
        <td><?= $b['created_at'] ?></td>
        <td class="actions">
          <form method="post" class="inline-form">
            <input type="hidden" name="action" value="compute">
            <input type="hidden" name="bill_id" value="<?= $b['id'] ?>">
            <button class="btn btn-sm" type="submit">Compute Allocation</button>
          </form>
          <a class="btn btn-sm btn-gray" href="utility.php?view=<?= $b['id'] ?>">View</a>
          <form method="post" class="inline-form" onsubmit="return confirm('Delete this bill and its allocations?')">
            <input type="hidden" name="action" value="delete_bill">
            <input type="hidden" name="bill_id" value="<?= $b['id'] ?>">
            <button class="btn btn-sm btn-danger" type="submit">Delete</button>
          </form>
        </td>
      </tr>
    <?php endwhile; ?>
  </table>
</div>

<!-- ============ ALLOCATION DETAIL ============ -->
<?php if ($viewBill): ?>
<div class="card">
  <h2>Allocation — Bill #<?= $viewBill['id'] ?> (<?= ucfirst($viewBill['utility_type']) ?>, ₱<?= number_format($viewBill['total_amount'], 2) ?>)</h2>
  <p style="font-size:13px;color:#6b7280;">
    Period: <?= $viewBill['billing_start'] ?> → <?= $viewBill['billing_end'] ?>
  </p>

  <?php if (empty($viewRows)): ?>
    <div class="alert alert-info">No allocation computed yet. Click “Compute Allocation” in the bills list.</div>
  <?php else: ?>
    <table>
      <tr><th>Tenant</th><th>Approved Days</th><th>Total Days</th><th>Share</th></tr>
      <?php $sum = 0; foreach ($viewRows as $r): $sum += $r['share_amount']; ?>
        <tr>
          <td><?= htmlspecialchars($r['full_name']) ?></td>
          <td><?= $r['approved_days'] ?></td>
          <td><?= $r['total_days'] ?></td>
          <td>₱<?= number_format($r['share_amount'], 2) ?></td>
        </tr>
      <?php endforeach; ?>
      <tr style="font-weight:bold;background:#f3f4f6;">
        <td colspan="3">Total</td>
        <td>₱<?= number_format($sum, 2) ?></td>
      </tr>
    </table>
    <p style="font-size:12px;color:#6b7280;margin-top:8px;">
      Formula: <code>Share = (Approved Days ÷ Total Days) × Total Bill</code>
    </p>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
mp:<?php
require '../config/db.php';
require '../includes/auth.php';
require '../includes/utility.php';   // ← pulls in the algorithm
requireRole('landlord');

$msg = ''; $err = '';

/* ---------- Add a new utility bill ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_bill') {
    $start  = $_POST['billing_start'];
    $end    = $_POST['billing_end'];
    $type   = $_POST['utility_type'];
    $amount = (float)$_POST['total_amount'];

    if ($start === '' || $end === '' || $amount <= 0) {
        $err = "Please fill in all fields with valid values.";
    } elseif ($end < $start) {
        $err = "Billing end must be on or after billing start.";
    } else {
        $stmt = $conn->prepare("INSERT INTO utility_bills (billing_start, billing_end, utility_type, total_amount) VALUES (?,?,?,?)");
        $stmt->bind_param("sssd", $start, $end, $type, $amount);
        $stmt->execute();
        $msg = "Utility bill added. Click 'Compute Allocation' to run the algorithm.";
    }
}

/* ---------- Compute allocation for a bill ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'compute') {
    $billId = (int)$_POST['bill_id'];

    // Clear any previous allocation for this bill (so re-running is safe)
    $conn->query("DELETE FROM utility_allocations WHERE bill_id = $billId");

    $result = computeAllocation($conn, $billId);
    if (empty($result['rows'])) {
        $err = "No active tenants found — nothing to allocate.";
    } else {
        $totalDays = $result['total_days'];
        $stmt = $conn->prepare("INSERT INTO utility_allocations (bill_id, tenant_id, approved_days, total_days, share_amount) VALUES (?,?,?,?,?)");
        foreach ($result['rows'] as $r) {
            $stmt->bind_param("iiiid", $billId, $r['tenant_id'], $r['approved_days'], $totalDays, $r['share_amount']);
            $stmt->execute();
        }
        $msg = "Allocation computed for bill #$billId (total approved days: $totalDays).";
    }
}

/* ---------- Delete a bill ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_bill') {
    $billId = (int)$_POST['bill_id'];
    $conn->query("DELETE FROM utility_allocations WHERE bill_id = $billId");
    $conn->query("DELETE FROM utility_bills WHERE id = $billId");
    $msg = "Bill and its allocations deleted.";
}

/* ---------- View a specific bill's allocation ---------- */
$viewBill = null; $viewRows = [];
if (!empty($_GET['view'])) {
    $vid = (int)$_GET['view'];
    $viewBill = $conn->query("SELECT * FROM utility_bills WHERE id = $vid")->fetch_assoc();
    if ($viewBill) {
        $viewRows = $conn->query("
            SELECT ua.*, t.full_name
            FROM utility_allocations ua
            JOIN tenants t ON t.id = ua.tenant_id
            WHERE ua.bill_id = $vid
            ORDER BY t.full_name
        ")->fetch_all(MYSQLI_ASSOC);
    }
}

/* ---------- List of all bills ---------- */
$bills = $conn->query("SELECT * FROM utility_bills ORDER BY billing_start DESC");

include '../includes/header.php';
?>

<h1>Shared Utility Allocation</h1>
<?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>

<!-- ============ ADD BILL FORM ============ -->
<div class="card">
  <h2>Add Utility Bill</h2>
  <form method="post">
    <input type="hidden" name="action" value="add_bill">
    <div class="grid-3">
      <div>
        <label>Billing Start</label>
        <input type="date" name="billing_start" required>
      </div>
      <div>
        <label>Billing End</label>
        <input type="date" name="billing_end" required>
      </div>
      <div>
        <label>Utility Type</label>
        <select name="utility_type">
          <option value="electricity">Electricity</option>
          <option value="water">Water</option>
        </select>
      </div>
      <div>
        <label>Total Bill (₱)</label>
        <input type="number" step="0.01" min="0.01" name="total_amount" required>
      </div>
    </div>
    <button type="submit">Add Bill</button>
  </form>
</div>

<!-- ============ BILL LIST ============ -->
<div class="card">
  <h2>Bills</h2>
  <table>
    <tr>
      <th>#</th><th>Period</th><th>Type</th><th>Total</th>
      <th>Created</th><th>Actions</th>
    </tr>
    <?php while ($b = $bills->fetch_assoc()): ?>
      <tr>
        <td><?= $b['id'] ?></td>
        <td><?= $b['billing_start'] ?> → <?= $b['billing_end'] ?></td>
        <td><?= ucfirst($b['utility_type']) ?></td>
        <td>₱<?= number_format($b['total_amount'], 2) ?></td>
        <td><?= $b['created_at'] ?></td>
        <td class="actions">
          <form method="post" class="inline-form">
            <input type="hidden" name="action" value="compute">
            <input type="hidden" name="bill_id" value="<?= $b['id'] ?>">
            <button class="btn btn-sm" type="submit">Compute Allocation</button>
          </form>
          <a class="btn btn-sm btn-gray" href="utility.php?view=<?= $b['id'] ?>">View</a>
          <form method="post" class="inline-form" onsubmit="return confirm('Delete this bill and its allocations?')">
            <input type="hidden" name="action" value="delete_bill">
            <input type="hidden" name="bill_id" value="<?= $b['id'] ?>">
            <button class="btn btn-sm btn-danger" type="submit">Delete</button>
          </form>
        </td>
      </tr>
    <?php endwhile; ?>
  </table>
</div>

<!-- ============ ALLOCATION DETAIL ============ -->
<?php if ($viewBill): ?>
<div class="card">
  <h2>Allocation — Bill #<?= $viewBill['id'] ?> (<?= ucfirst($viewBill['utility_type']) ?>, ₱<?= number_format($viewBill['total_amount'], 2) ?>)</h2>
  <p style="font-size:13px;color:#6b7280;">
    Period: <?= $viewBill['billing_start'] ?> → <?= $viewBill['billing_end'] ?>
  </p>

  <?php if (empty($viewRows)): ?>
    <div class="alert alert-info">No allocation computed yet. Click “Compute Allocation” in the bills list.</div>
  <?php else: ?>
    <table>
      <tr><th>Tenant</th><th>Approved Days</th><th>Total Days</th><th>Share</th></tr>
      <?php $sum = 0; foreach ($viewRows as $r): $sum += $r['share_amount']; ?>
        <tr>
          <td><?= htmlspecialchars($r['full_name']) ?></td>
          <td><?= $r['approved_days'] ?></td>
          <td><?= $r['total_days'] ?></td>
          <td>₱<?= number_format($r['share_amount'], 2) ?></td>
        </tr>
      <?php endforeach; ?>
      <tr style="font-weight:bold;background:#f3f4f6;">
        <td colspan="3">Total</td>
        <td>₱<?= number_format($sum, 2) ?></td>
      </tr>
    </table>
    <p style="font-size:12px;color:#6b7280;margin-top:8px;">
      Formula: <code>Share = (Approved Days ÷ Total Days) × Total Bill</code>
    </p>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>