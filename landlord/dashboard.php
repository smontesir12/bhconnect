<?php
require '../config/db.php';
require '../includes/auth.php';
requireRole('landlord');

$totalRooms   = $conn->query("SELECT COUNT(*) c FROM rooms")->fetch_assoc()['c'];
$vacant       = $conn->query("SELECT COUNT(*) c FROM rooms WHERE status='vacant'")->fetch_assoc()['c'];
$occupied     = $conn->query("SELECT COUNT(*) c FROM rooms WHERE status='occupied'")->fetch_assoc()['c'];
$activeTenants= $conn->query("SELECT COUNT(*) c FROM tenants WHERE status='active'")->fetch_assoc()['c'];
$openMaint    = $conn->query("SELECT COUNT(*) c FROM maintenance_requests WHERE status IN ('pending','in_progress')")->fetch_assoc()['c'];
$pendingCorr  = $conn->query("SELECT COUNT(*) c FROM correction_requests WHERE status='pending'")->fetch_assoc()['c'];
$unpaid       = $conn->query("SELECT COUNT(*) c FROM payments WHERE status IN ('unpaid','partial')")->fetch_assoc()['c'];

include '../includes/header.php';
?>

<h1>Landlord Dashboard</h1>

<div class="grid-4">
  <div class="stat"><div class="num"><?= $totalRooms ?></div><div class="lbl">Total Rooms</div></div>
  <div class="stat"><div class="num"><?= $vacant ?></div><div class="lbl">Vacant</div></div>
  <div class="stat"><div class="num"><?= $occupied ?></div><div class="lbl">Occupied</div></div>
  <div class="stat"><div class="num"><?= $activeTenants ?></div><div class="lbl">Active Tenants</div></div>
</div>

<div class="grid-4" style="margin-top:12px;">
  <div class="stat"><div class="num"><?= $openMaint ?></div><div class="lbl">Open Maintenance</div></div>
  <div class="stat"><div class="num"><?= $pendingCorr ?></div><div class="lbl">Pending Corrections</div></div>
  <div class="stat"><div class="num"><?= $unpaid ?></div><div class="lbl">Unpaid / Partial</div></div>
</div>

<div class="card" style="margin-top:24px;">
  <h2>Quick Links</h2>
  <a class="btn" href="rooms.php">Manage Rooms</a>
  <a class="btn" href="tenants.php">Manage Tenants</a>
  <a class="btn" href="contracts.php">Contracts</a>
  <a class="btn btn-gray" href="utility.php">Utility Allocation</a>
</div>
<div class="stat">
  <div class="num" style="color:#dc2626;"><?php
    echo (int)$conn->query("
      SELECT COUNT(*) c FROM payments
      WHERE status IN ('unpaid','partial')
        AND due_date IS NOT NULL
        AND due_date < CURDATE()
    ")->fetch_assoc()['c'];
  ?></div>
  <div class="lbl">Overdue Payments</div>
</div>
<?php include '../includes/footer.php'; ?>