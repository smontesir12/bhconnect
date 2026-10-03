<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('landlord');

$msg = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /* ---------- SAVE TENANT ---------- */
    if ($action === 'save') {
        $id     = (int)($_POST['id'] ?? 0);
        $name   = trim($_POST['full_name']);
        $contact= trim($_POST['contact']);
        $email  = trim($_POST['email']);
        $emg    = trim($_POST['emergency_contact']);
        $roomId = $_POST['room_id'] !== '' ? (int)$_POST['room_id'] : null;
        $status = $_POST['status'];

        if ($name === '') {
            $err = "Full name is required.";
        } elseif ($id > 0) {
            $stmt = $conn->prepare("UPDATE tenants SET full_name=?, contact=?, email=?, emergency_contact=?, room_id=?, status=? WHERE id=?");
            $stmt->bind_param("ssssisi", $name, $contact, $email, $emg, $roomId, $status, $id);
            $stmt->execute();
            if ($roomId) $conn->query("UPDATE rooms SET status='occupied' WHERE id=" . (int)$roomId);
            $msg = "Tenant updated.";
        } else {
            $stmt = $conn->prepare("INSERT INTO tenants (full_name, contact, email, emergency_contact, room_id, status) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param("ssssis", $name, $contact, $email, $emg, $roomId, $status);
            $stmt->execute();
            if ($roomId) $conn->query("UPDATE rooms SET status='occupied' WHERE id=" . (int)$roomId);
            $msg = "Tenant added.";
        }
    }

    /* ---------- ARCHIVE TENANT ---------- */
    if ($action === 'archive') {
        $id = (int)$_POST['id'];
        $old = $conn->query("SELECT room_id FROM tenants WHERE id=$id")->fetch_assoc();
        $conn->query("UPDATE tenants SET status='archived' WHERE id=$id");
        if (!empty($old['room_id'])) {
            $rid = (int)$old['room_id'];
            $still = $conn->query("SELECT COUNT(*) c FROM tenants WHERE room_id=$rid AND status='active'")->fetch_assoc()['c'];
            if ($still == 0) $conn->query("UPDATE rooms SET status='vacant' WHERE id=$rid");
        }
        $msg = "Tenant archived.";
    }

    /* ---------- CREATE TENANT LOGIN ---------- */
    if ($action === 'create_login') {
        $tenantId = (int)$_POST['id'];
        $username = trim($_POST['username']);
        $password = $_POST['password'];

        $chk = $conn->prepare("SELECT id FROM users WHERE tenant_id = ?");
        $chk->bind_param("i", $tenantId);
        $chk->execute();
        
        if ($chk->get_result()->num_rows > 0) {
            $err = "This tenant already has a login account.";
        } elseif ($username === '' || strlen($password) < 6) {
            $err = "Username is required and password must be at least 6 characters.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, password, role, tenant_id) VALUES (?, ?, 'tenant', ?)");
            $stmt->bind_param("ssi", $username, $hash, $tenantId);
            if ($stmt->execute()) {
                $msg = "Login created for tenant. Username: <b>$username</b>";
            } else {
                $err = "Username already taken.";
            }
        }
    }
}

$edit = null;
if (!empty($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $edit = $conn->query("SELECT * FROM tenants WHERE id=$id")->fetch_assoc();
}

$rooms = $conn->query("SELECT id, room_number, status FROM rooms ORDER BY room_number");
$roomList = [];
while ($r = $rooms->fetch_assoc()) $roomList[] = $r;

// Optimized query to check for logins without nested loops
$tenants = $conn->query("
  SELECT t.*, r.room_number,
         (SELECT COUNT(*) FROM users u WHERE u.tenant_id = t.id) AS has_login
  FROM tenants t
  LEFT JOIN rooms r ON r.id = t.room_id
  ORDER BY t.status ASC, t.full_name ASC
");

include '../includes/header.php';  // <--- THIS IS THE MISSING LINE THAT BRINGS BACK THE MENU
?>

<h1>Tenants</h1>
<?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>

<div class="card">
  <h2><?= $edit ? 'Edit Tenant' : 'Add Tenant' ?></h2>
  <form method="post">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">

    <div class="grid-2">
      <div>
        <label>Full Name</label>
        <input name="full_name" required value="<?= htmlspecialchars($edit['full_name'] ?? '') ?>">
      </div>
      <div>
        <label>Contact Number</label>
        <input name="contact" value="<?= htmlspecialchars($edit['contact'] ?? '') ?>">
      </div>
      <div>
        <label>Email</label>
        <input name="email" type="email" value="<?= htmlspecialchars($edit['email'] ?? '') ?>">
      </div>
      <div>
        <label>Emergency Contact</label>
        <input name="emergency_contact" value="<?= htmlspecialchars($edit['emergency_contact'] ?? '') ?>">
      </div>
      <div>
        <label>Assigned Room</label>
        <select name="room_id">
          <option value="">— None —</option>
          <?php foreach ($roomList as $r): ?>
            <option value="<?= $r['id'] ?>" <?= ($edit['room_id'] ?? null) == $r['id'] ? 'selected' : '' ?>>
              Room <?= htmlspecialchars($r['room_number']) ?> (<?= $r['status'] ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Status</label>
        <select name="status">
          <?php foreach (['active','archived'] as $s): ?>
            <option value="<?= $s ?>" <?= ($edit['status'] ?? 'active') === $s ? 'selected' : '' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <button type="submit"><?= $edit ? 'Update Tenant' : 'Add Tenant' ?></button>
    <?php if ($edit): ?><a class="btn btn-gray" href="tenants.php">Cancel</a><?php endif; ?>
  </form>
</div>

<div class="card">
  <h2>Tenant List</h2>
  <table>
    <tr><th>Name</th><th>Room</th><th>Contact</th><th>Status</th><th>Actions</th></tr>
    <?php while ($t = $tenants->fetch_assoc()): ?>
      <tr>
        <td><?= htmlspecialchars($t['full_name']) ?></td>
        <td><?= $t['room_number'] ? 'Room ' . htmlspecialchars($t['room_number']) : '—' ?></td>
        <td><?= htmlspecialchars($t['contact']) ?></td>
        <td>
          <span class="badge <?= $t['status'] === 'active' ? 'badge-green' : 'badge-gray' ?>">
            <?= $t['status'] ?>
          </span>
        </td>
        <td class="actions">
          <a class="btn btn-sm" href="tenants.php?edit=<?= $t['id'] ?>">Edit</a>
          
          <?php if ($t['has_login'] == 0 && $t['status'] === 'active'): ?>
            <button type="button" class="btn btn-sm btn-gray" onclick="showLoginForm(<?= $t['id'] ?>, '<?= htmlspecialchars(addslashes($t['full_name'])) ?>')">Create Login</button>
          <?php elseif ($t['has_login'] > 0): ?>
            <span class="badge badge-green">Has Login</span>
          <?php endif; ?>

          <?php if ($t['status'] === 'active'): ?>
            <form method="post" class="inline-form" onsubmit="return confirm('Archive this tenant?')">
              <input type="hidden" name="action" value="archive">
              <input type="hidden" name="id" value="<?= $t['id'] ?>">
              <button class="btn btn-sm btn-gray" type="submit">Archive</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endwhile; ?>
  </table>
</div>

<!-- ============ CREATE LOGIN MODAL ============ -->
<div id="login_modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000;">
  <div style="background:#fff; max-width:400px; margin:80px auto; padding:24px; border-radius:8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
    <h3 style="margin-top:0;">Create Login for <span id="login_tenant_name" style="color:#2563eb;"></span></h3>
    <form method="post">
      <input type="hidden" name="action" value="create_login">
      <input type="hidden" name="id" id="login_tenant_id">
      
      <label>Username</label>
      <input name="username" required placeholder="e.g. juan.delacruz">
      
      <label>Password</label>
      <input name="password" type="password" required minlength="6" placeholder="At least 6 characters">
      
      <div style="margin-top:16px;">
        <button type="submit">Create Login</button>
        <button type="button" class="btn btn-gray" onclick="hideLoginForm()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
function showLoginForm(id, name) {
    document.getElementById('login_tenant_id').value = id;
    document.getElementById('login_tenant_name').textContent = name;
    document.getElementById('login_modal').style.display = 'block';
}
function hideLoginForm() {
    document.getElementById('login_modal').style.display = 'none';
}
</script>

<?php include '../includes/footer.php'; ?>