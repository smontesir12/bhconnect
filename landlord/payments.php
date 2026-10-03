<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('landlord');

$msg = ''; $err = '';

/* ---------- Record Payment ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $tenantId    = (int)$_POST['tenant_id'];
    $type        = $_POST['payment_type'];
    $allocId     = !empty($_POST['allocation_id']) ? (int)$_POST['allocation_id'] : null;
    $amount      = (float)$_POST['amount'];
    $date        = $_POST['payment_date'];
    $dueDate     = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
    $periodLabel = trim($_POST['period_label'] ?? '');
    $status      = $_POST['status'];
    $notes       = trim($_POST['notes']);

    if ($tenantId <= 0 || $amount <= 0 || $date === '') {
        $err = "Please fill in all required fields.";
    } else {
        $receiptNo = 'RCPT-' . date('Ymd') . '-' . rand(1000, 9999);

        $stmt = $conn->prepare("
            INSERT INTO payments 
              (tenant_id, allocation_id, amount, payment_type, payment_date, due_date, period_label, status, receipt_no, notes) 
            VALUES (?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->bind_param("iidsssssss", 
            $tenantId, $allocId, $amount, $type, $date, 
            $dueDate, $periodLabel, $status, $receiptNo, $notes
        );

        if ($stmt->execute()) {
            $msg = "Payment recorded. Receipt No: <strong>$receiptNo</strong>";
        } else {
            $err = "Error recording payment: " . $conn->error;
        }
    }
}

/* ---------- Delete Payment ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int)$_POST['id'];
    $conn->query("DELETE FROM payments WHERE id=$id");
    $msg = "Payment deleted.";
}

/* ---------- Fetch Data for Dropdowns ---------- */
$tenants = $conn->query("SELECT id, full_name FROM tenants WHERE status='active' ORDER BY full_name");

$allocations = $conn->query("
    SELECT ua.id, ua.share_amount, ub.utility_type, ub.billing_start, ub.billing_end, t.full_name 
    FROM utility_allocations ua
    JOIN utility_bills ub ON ub.id = ua.bill_id
    JOIN tenants t ON t.id = ua.tenant_id
    ORDER BY ub.billing_start DESC
");

/* ---------- Fetch All Payments ---------- */
$payments = $conn->query("
    SELECT p.*, t.full_name 
    FROM payments p 
    JOIN tenants t ON t.id = p.tenant_id 
    ORDER BY 
        CASE WHEN p.status IN ('unpaid','partial') THEN 0 ELSE 1 END,
        p.due_date IS NULL,
        p.due_date ASC,
        p.payment_date DESC
");

/* ---------- Stats ---------- */
$overdueCount = $conn->query("
    SELECT COUNT(*) c FROM payments
    WHERE status IN ('unpaid','partial')
      AND due_date IS NOT NULL
      AND due_date < CURDATE()
")->fetch_assoc()['c'];

$pendingCount = $conn->query("
    SELECT COUNT(*) c FROM payments
    WHERE status IN ('unpaid','partial')
")->fetch_assoc()['c'];

$paidThisMonth = $conn->query("
    SELECT IFNULL(SUM(amount), 0) total FROM payments
    WHERE status = 'paid'
      AND MONTH(payment_date) = MONTH(CURDATE())
      AND YEAR(payment_date) = YEAR(CURDATE())
")->fetch_assoc()['total'];

include '../includes/header.php';
?>

<h1>Payments</h1>
<?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>

<!-- ============ STAT CARDS ============ -->
<div class="grid-3">
  <div class="stat">
    <div class="num" style="color:#dc2626;"><?= (int)$overdueCount ?></div>
    <div class="lbl">Overdue Payments</div>
  </div>
  <div class="stat">
    <div class="num" style="color:#d97706;"><?= (int)$pendingCount ?></div>
    <div class="lbl">Unpaid / Partial</div>
  </div>
  <div class="stat">
    <div class="num" style="color:#059669;">₱<?= number_format($paidThisMonth, 2) ?></div>
    <div class="lbl">Collected This Month</div>
  </div>
</div>

<!-- ============ RECORD PAYMENT FORM ============ -->
<div class="card">
  <h2>Record New Payment</h2>
  <form method="post">
    <input type="hidden" name="action" value="save">

    <div class="grid-2">
      <div>
        <label>Tenant *</label>
        <select name="tenant_id" required>
          <option value="">— Select Tenant —</option>
          <?php while ($t = $tenants->fetch_assoc()): ?>
            <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['full_name']) ?></option>
          <?php endwhile; ?>
        </select>
      </div>
      <div>
        <label>Payment Type *</label>
        <select name="payment_type" required>
          <option value="rent">Rent</option>
          <option value="utility">Utility</option>
        </select>
      </div>
      <div>
        <label>Link to Utility Allocation (Optional)</label>
        <select name="allocation_id">
          <option value="">— None (Rent or Manual Utility) —</option>
          <?php while ($a = $allocations->fetch_assoc()): ?>
            <option value="<?= $a['id'] ?>">
              <?= htmlspecialchars($a['full_name']) ?> — 
              <?= ucfirst($a['utility_type']) ?> 
              (<?= $a['billing_start'] ?> → <?= $a['billing_end'] ?>) 
              — ₱<?= number_format($a['share_amount'], 2) ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>
      <div>
        <label>Amount (₱) *</label>
        <input type="number" step="0.01" min="0.01" name="amount" required>
      </div>
      <div>
        <label>Payment Date *</label>
        <input type="date" name="payment_date" required value="<?= date('Y-m-d') ?>">
      </div>
      <div>
        <label>Due Date (for unpaid/partial)</label>
        <input type="date" name="due_date">
      </div>
      <div>
        <label>Period Label (optional)</label>
        <input name="period_label" placeholder="e.g. October 2026 Rent">
      </div>
      <div>
        <label>Status *</label>
        <select name="status" required>
          <option value="paid">Paid (Full)</option>
          <option value="partial">Partial</option>
          <option value="unpaid">Unpaid</option>
        </select>
      </div>
    </div>

    <label>Notes</label>
    <textarea name="notes" rows="2" placeholder="e.g. GCash ref 12345, 1st of 2 payments"></textarea>

    <button type="submit">Record Payment</button>
  </form>
</div>

<!-- ============ PAYMENT HISTORY ============ -->
<div class="card">
  <h2>Payment History</h2>
  <table>
    <tr>
      <th>Date</th><th>Receipt No</th><th>Tenant</th><th>Type</th>
      <th>Amount</th><th>Due Date</th><th>Status</th><th>Actions</th>
    </tr>
    <?php while ($p = $payments->fetch_assoc()): ?>
      <?php $isOverdue = $p['due_date'] && $p['status'] !== 'paid' && $p['due_date'] < date('Y-m-d'); ?>
      <tr>
        <td><?= $p['payment_date'] ?></td>
        <td><code style="font-size:11px;"><?= htmlspecialchars($p['receipt_no']) ?></code></td>
        <td><?= htmlspecialchars($p['full_name']) ?></td>
        <td>
          <?= ucfirst($p['payment_type']) ?>
          <?php if ($p['period_label']): ?>
            <div style="font-size:11px; color:#94a3b8;"><?= htmlspecialchars($p['period_label']) ?></div>
          <?php endif; ?>
        </td>
        <td>₱<?= number_format($p['amount'], 2) ?></td>
        <td>
          <?php if ($p['due_date']): ?>
            <?php if ($isOverdue): ?>
              <span style="color:#dc2626; font-weight:600;"><?= $p['due_date'] ?> ⚠</span>
            <?php else: ?>
              <?= $p['due_date'] ?>
            <?php endif; ?>
          <?php else: ?>
            —
          <?php endif; ?>
        </td>
        <td>
          <?php
            $cls = ['paid'=>'badge-green','partial'=>'badge-yellow','unpaid'=>'badge-red'][$p['status']] ?? 'badge-gray';
          ?>
          <span class="badge <?= $cls ?>"><?= $p['status'] ?></span>
        </td>
        <td class="actions">
          <a class="btn btn-sm" href="receipt.php?id=<?= $p['id'] ?>" target="_blank">Receipt</a>
          <form method="post" class="inline-form" onsubmit="return confirm('Delete this payment record?')">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $p['id'] ?>">
            <button class="btn btn-sm btn-danger" type="submit">Delete</button>
          </form>
        </td>
      </tr>
    <?php endwhile; ?>
  </table>
</div>

<?php include '../includes/footer.php'; ?>