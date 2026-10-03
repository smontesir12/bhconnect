<?php
require '../config/db.php';
require '../includes/auth.php';
requireRole('landlord');

$msg = ''; $err = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id     = (int)($_POST['id'] ?? 0);
        $num    = trim($_POST['room_number']);
        $type   = trim($_POST['room_type']);
        $cap    = (int)$_POST['capacity'];
        $rate   = (float)$_POST['rental_rate'];
        $status = $_POST['status'];

        if ($num === '') {
            $err = "Room number is required.";
        } elseif ($id > 0) {
            $stmt = $conn->prepare("UPDATE rooms SET room_number=?, room_type=?, capacity=?, rental_rate=?, status=? WHERE id=?");
            $stmt->bind_param("ssidsi", $num, $type, $cap, $rate, $status, $id);
            $stmt->execute();
            $msg = "Room updated.";
        } else {
            $stmt = $conn->prepare("INSERT INTO rooms (room_number, room_type, capacity, rental_rate, status) VALUES (?,?,?,?,?)");
            $stmt->bind_param("ssids", $num, $type, $cap, $rate, $status);
            $stmt->execute();
            $msg = "Room added.";
        }
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        // Block delete if active tenant assigned
        $chk = $conn->prepare("SELECT COUNT(*) c FROM tenants WHERE room_id=? AND status='active'");
        $chk->bind_param("i", $id); $chk->execute();
        if ($chk->get_result()->fetch_assoc()['c'] > 0) {
            $err = "Cannot delete: room has active tenants.";
        } else {
            $conn->query("DELETE FROM rooms WHERE id=" . $id);
            $msg = "Room deleted.";
        }
    }
}

// Editing?
$edit = null;
if (!empty($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $edit = $conn->query("SELECT * FROM rooms WHERE id=$id")->fetch_assoc();
}

$rooms = $conn->query("SELECT * FROM rooms ORDER BY room_number");
include '../includes/header.php';
?>

<h1>Rooms</h1>
<?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>

<div class="card">
  <h2><?= $edit ? 'Edit Room' : 'Add Room' ?></h2>
  <form method="post">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">

    <div class="grid-2">
      <div>
        <label>Room Number</label>
        <input name="room_number" required value="<?= htmlspecialchars($edit['room_number'] ?? '') ?>">
      </div>
      <div>
        <label>Room Type</label>
        <input name="room_type" value="<?= htmlspecialchars($edit['room_type'] ?? '') ?>" placeholder="e.g. Single / Double">
      </div>
      <div>
        <label>Capacity</label>
        <input name="capacity" type="number" min="1" value="<?= $edit['capacity'] ?? 1 ?>">
      </div>
      <div>
        <label>Rental Rate (₱)</label>
        <input name="rental_rate" type="number" step="0.01" value="<?= $edit['rental_rate'] ?? 0 ?>">
      </div>
      <div>
        <label>Status</label>
        <select name="status">
          <?php foreach (['vacant','occupied','reserved','maintenance'] as $s): ?>
            <option value="<?= $s ?>" <?= ($edit['status'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <button type="submit"><?= $edit ? 'Update Room' : 'Add Room' ?></button>
    <?php if ($edit): ?><a class="btn btn-gray" href="rooms.php">Cancel</a><?php endif; ?>
  </form>
</div>

<div class="card">
  <h2>Room List</h2>
  <table>
    <tr><th>#</th><th>Type</th><th>Capacity</th><th>Rate</th><th>Status</th><th>Actions</th></tr>
    <?php while ($r = $rooms->fetch_assoc()): ?>
      <tr>
        <td><?= htmlspecialchars($r['room_number']) ?></td>
        <td><?= htmlspecialchars($r['room_type']) ?></td>
        <td><?= (int)$r['capacity'] ?></td>
        <td>₱<?= number_format($r['rental_rate'], 2) ?></td>
        <td>
          <?php
            $cls = ['vacant'=>'badge-green','occupied'=>'badge-red','reserved'=>'badge-yellow','maintenance'=>'badge-gray'][$r['status']] ?? 'badge-gray';
          ?>
          <span class="badge <?= $cls ?>"><?= $r['status'] ?></span>
        </td>
        <td class="actions">
          <a class="btn btn-sm" href="rooms.php?edit=<?= $r['id'] ?>">Edit</a>
          <form method="post" class="inline-form" onsubmit="return confirm('Delete this room?')">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $r['id'] ?>">
            <button class="btn btn-sm btn-danger" type="submit">Delete</button>
          </form>
        </td>
      </tr>
    <?php endwhile; ?>
  </table>
</div>

<?php include '../includes/footer.php'; ?>