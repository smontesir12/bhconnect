<?php
require '../config/db.php';
require '../includes/auth.php';
requireRole('landlord');

$msg = ''; $err = '';

/* ---------- Save (add or update) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $id       = (int)($_POST['id'] ?? 0);
    $tenantId = (int)($_POST['tenant_id'] ?? 0);
    $roomId   = (int)($_POST['room_id'] ?? 0);
    $moveIn   = $_POST['move_in'] ?? '';
    $moveOut  = $_POST['move_out'] ?? '';
    $rate     = (float)($_POST['rental_rate'] ?? 0);
    $deposit  = (float)($_POST['deposit'] ?? 0);
    $sched    = trim($_POST['billing_schedule'] ?? 'monthly');
    $status   = $_POST['status'] ?? 'active';

    if ($tenantId <= 0 || $roomId <= 0 || $moveIn === '') {
        $err = "Tenant, room, and move-in date are required.";
    } elseif ($moveOut !== '' && $moveOut < $moveIn) {
        $err = "Move-out date cannot be before move-in date.";
    } else {
        $moveOutVal = $moveOut !== '' ? $moveOut : null;

        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE contracts SET tenant_id=?, room_id=?, move_in=?, move_out=?, rental_rate=?, deposit=?, billing_schedule=?, status=? WHERE id=?");
            $stmt->bind_param("iissddssi", $tenantId, $roomId, $moveIn, $moveOutVal, $rate, $deposit, $sched, $status, $id);
            $stmt->execute();
            $msg = "Contract updated.";
        } else {
            $stmt = $conn->prepare("INSERT INTO contracts (tenant_id, room_id, move_in, move_out, rental_rate, deposit, billing_schedule, status) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->bind_param("iissddss", $tenantId, $roomId, $moveIn, $moveOutVal, $rate, $deposit, $sched, $status);
            $stmt->execute();
            $msg = "Contract created.";
        }

        // If contract is active, mark room occupied
        if ($status === 'active') {
            $conn->query("UPDATE rooms SET status='occupied' WHERE id=" . (int)$roomId);
        }
    }
}

/* ---------- Delete ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int)$_POST['id'];
    $conn->query("DELETE FROM contracts WHERE id=$id");
    $msg = "Contract deleted.";
}

/* ---------- End contract ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'end') {
    $id = (int)$_POST['id'];
    $today = date('Y-m-d');
    $c = $conn->query("SELECT room_id FROM contracts WHERE id=$id")->fetch_assoc();
    $conn->query("UPDATE contracts SET status='ended', move_out='$today' WHERE id=$id");
    if ($c) {
        $rid = (int)$c['room_id'];
        $stillActive = $conn->query("SELECT COUNT(*) c FROM contracts WHERE room_id=$rid AND status='active'")->fetch_assoc()['c'];
        if ($stillActive == 0) {
            $conn->query("UPDATE rooms SET status='vacant' WHERE id=$rid");
        }
    }
    $msg = "Contract ended.";
}

/* ---------- Edit ---------- */
$edit = null;
if (!empty($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $edit = $conn->query("SELECT * FROM contracts WHERE id=$id")->fetch_assoc();
}

/* ---------- Dropdowns ---------- */
$tenants = $conn->query("SELECT id, full_name FROM tenants WHERE status='active' ORDER BY full_name");
$rooms   = $conn->query("SELECT id, room_number, rental_rate FROM rooms ORDER BY room_number");

/* ---------- Contract list ---------- */
$contracts = $conn->query("
    SELECT c.*, t.full_name, r.room_number
    FROM contracts c
    JOIN tenants t ON t.id = c.tenant_id
    JOIN rooms   r ON r.id = c.room_id
    ORDER BY c.status ASC, c.move_in DESC
");

include '../includes/header.php';
?>

<h1>Contracts</h1>
<?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>

<div class="alert alert-info" style="font-size:13px;">
  <strong>Note:</strong> Only contracts with status <em>active</em> are used by the utility allocation algorithm
  to compute approved occupied days. Ensure move-in dates are correct before computing a bill.
</div>

<!-- ============ FORM ============ -->
<div class="card">
  <h2><?= $edit ? 'Edit Contract' : 'New Contract' ?></h2>
  <form method="post">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">

    <div class="grid-2">
      <div>
        <label>Tenant</label>
        <select name="tenant_id" required>
          <option value="">— Select Tenant —</option>
          <?php while ($t = $tenants->fetch_assoc()): ?>
            <option value="<?= $t['id'] ?>" <?= ($edit['tenant_id'] ?? 0) == $t['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($t['full_name']) ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>
      <div>
        <label>Room</label>
        <select name="room_id" required>
          <option value="">— Select Room —</option>
          <?php while ($r = $rooms->fetch_assoc()): ?>
            <option value="<?= $r['id'] ?>" <?= ($edit['room_id'] ?? 0) == $r['id'] ? 'selected' : '' ?>>
              Room <?= htmlspecialchars($r['room_number']) ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>
      <div>
        <label>Move-In Date</label>
        <input type="date" name="move_in" required value="<?= htmlspecialchars($edit['move_in'] ?? '') ?>">
      </div>
      <div>
        <label>Move-Out Date (leave blank if ongoing)</label>
        <input type="date" name="move_out" value="<?= htmlspecialchars($edit['move_out'] ?? '') ?>">
      </div>
      <div>
        <label>Rental Rate (₱ / month)</label>
        <input type="number" step="0.01" min="0" name="rental_rate" value="<?= $edit['rental_rate'] ?? 0 ?>">
      </div>
      <div>
        <label>Deposit (₱)</label>
        <input type="number" step="0.01" min="0" name="deposit" value="<?= $edit['deposit'] ?? 0 ?>">
      </div>
      <div>
        <label>Billing Schedule</label>
        <select name="billing_schedule">
          <?php foreach (['monthly','semi-monthly','weekly'] as $s): ?>
            <option value="<?= $s ?>" <?= ($edit['billing_schedule'] ?? 'monthly') === $s ? 'selected' : '' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Status</label>
        <select name="status">
          <?php foreach (['active','ended'] as $s): ?>
            <option value="<?= $s ?>" <?= ($edit['status'] ?? 'active') === $s ? 'selected' : '' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <button type="submit"><?= $edit ? 'Update Contract' : 'Create Contract' ?></button>
    <?php if ($edit): ?><a class="btn btn-gray" href="contracts.php">Cancel</a><?php endif; ?>
  </form>
</div>

<!-- ============ CONTRACT LIST ============ -->
<div class="card">
  <h2>All Contracts</h2>
  <table>
    <tr>
      <th>Tenant</th><th>Room</th><th>Move-In</th><th>Move-Out</th>
      <th>Rate</th><th>Status</th><th>Actions</th>
    </tr>
    <?php while ($c = $contracts->fetch_assoc()): ?>
      <tr>
        <td><?= htmlspecialchars($c['full_name']) ?></td>
        <td>Room <?= htmlspecialchars($c['room_number']) ?></td>
        <td><?= $c['move_in'] ?></td>
        <td><?= $c['move_out'] ?: '—' ?></td>
        <td>₱<?= number_format($c['rental_rate'], 2) ?></td>
        <td>
          <span class="badge <?= $c['status'] === 'active' ? 'badge-green' : 'badge-gray' ?>">
            <?= $c['status'] ?>
          </span>
        </td>
        <td class="actions">
          <a class="btn btn-sm" href="contracts.php?edit=<?= $c['id'] ?>">Edit</a>
          <?php if ($c['status'] === 'active'): ?>
            <form method="post" class="inline-form" onsubmit="return confirm('End this contract today?')">
              <input type="hidden" name="action" value="end">
              <input type="hidden" name="id" value="<?= $c['id'] ?>">
              <button class="btn btn-sm btn-gray" type="submit">End</button>
            </form>
          <?php endif; ?>
          <form method="post" class="inline-form" onsubmit="return confirm('Permanently delete this contract?')">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $c['id'] ?>">
            <button class="btn btn-sm btn-danger" type="submit">Delete</button>
          </form>
        </td>
      </tr>
    <?php endwhile; ?>
  </table>
</div>

<?php include '../includes/footer.php'; ?>