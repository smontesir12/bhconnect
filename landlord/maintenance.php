<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('landlord');

$msg = ''; $err = '';

/* ---------- Update status ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    $id      = (int)$_POST['id'];
    $status  = $_POST['status'] ?? '';

    if (!in_array($status, ['pending','in_progress','resolved','closed'])) {
        $err = "Invalid status.";
    } else {
        $stmt = $conn->prepare("UPDATE maintenance_requests SET status=? WHERE id=?");
        $stmt->bind_param("si", $status, $id);
        $stmt->execute();
        $msg = "Request #$id updated to <strong>" . str_replace('_',' ', $status) . "</strong>.";
    }
}

/* ---------- Filter ---------- */
$filter = $_GET['filter'] ?? 'open';
$where  = "WHERE mr.status IN ('pending','in_progress')";
if ($filter === 'resolved') $where = "WHERE mr.status IN ('resolved','closed')";
if ($filter === 'all')      $where = "";

/* ---------- Fetch requests ---------- */
$requests = $conn->query("
    SELECT mr.*, t.full_name, r.room_number
    FROM maintenance_requests mr
    JOIN tenants t ON t.id = mr.tenant_id
    LEFT JOIN rooms r ON r.id = t.room_id
    $where
    ORDER BY 
        FIELD(mr.urgency, 'high','medium','low'),
        mr.created_at DESC
");

/* ---------- Stat counts ---------- */
$stats = $conn->query("
    SELECT 
        SUM(status='pending')     AS pending,
        SUM(status='in_progress') AS in_progress,
        SUM(status='resolved')    AS resolved,
        SUM(status='closed')      AS closed
    FROM maintenance_requests
")->fetch_assoc();

include '../includes/header.php';
?>

<h1>Maintenance Requests</h1>
<?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>

<div class="grid-4">
  <div class="stat"><div class="num"><?= (int)$stats['pending'] ?></div><div class="lbl">Pending</div></div>
  <div class="stat"><div class="num"><?= (int)$stats['in_progress'] ?></div><div class="lbl">In Progress</div></div>
  <div class="stat"><div class="num"><?= (int)$stats['resolved'] ?></div><div class="lbl">Resolved</div></div>
  <div class="stat"><div class="num"><?= (int)$stats['closed'] ?></div><div class="lbl">Closed</div></div>
</div>

<div class="card" style="margin-top:16px;">
  <h2>Filter</h2>
  <a class="btn <?= $filter==='open'?'':'btn-gray' ?>" href="?filter=open">Open</a>
  <a class="btn <?= $filter==='resolved'?'':'btn-gray' ?>" href="?filter=resolved">Resolved / Closed</a>
  <a class="btn <?= $filter==='all'?'':'btn-gray' ?>" href="?filter=all">All</a>
</div>

<div class="card">
  <h2>Requests (<?= $requests->num_rows ?>)</h2>
  <?php if ($requests->num_rows === 0): ?>
    <p style="color:#6b7280;">No requests match this filter.</p>
  <?php else: ?>
    <table>
      <tr>
        <th>Tenant</th><th>Room</th><th>Category</th>
        <th>Description</th><th>Urgency</th><th>Status</th>
        <th>Submitted</th><th>Actions</th>
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
          <td><?= htmlspecialchars($r['full_name']) ?></td>
          <td><?= $r['room_number'] ? 'Room ' . htmlspecialchars($r['room_number']) : '—' ?></td>
          <td><?= htmlspecialchars($r['category']) ?></td>
          <td style="max-width: 260px;"><?= htmlspecialchars($r['description']) ?></td>
          <td><span class="badge <?= $urgCls ?>"><?= $r['urgency'] ?></span></td>
          <td><span class="badge <?= $stCls ?>"><?= str_replace('_',' ', $r['status']) ?></span></td>
          <td><?= $r['created_at'] ?></td>
          <td class="actions">
            <form method="post" class="inline-form">
              <input type="hidden" name="action" value="update_status">
              <input type="hidden" name="id" value="<?= $r['id'] ?>">
              <select name="status" onchange="this.form.submit()" style="width: auto; margin: 0; padding: 4px 6px; font-size: 12px;">
                <option value="pending"     <?= $r['status']==='pending'?'selected':'' ?>>Pending</option>
                <option value="in_progress" <?= $r['status']==='in_progress'?'selected':'' ?>>In Progress</option>
                <option value="resolved"    <?= $r['status']==='resolved'?'selected':'' ?>>Resolved</option>
                <option value="closed"      <?= $r['status']==='closed'?'selected':'' ?>>Closed</option>
              </select>
            </form>
          </td>
        </tr>
      <?php endwhile; ?>
    </table>
  <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>