<?php
require 'config/db.php';
require 'includes/auth.php';
requireLogin();

$u = currentUser();
$userId = (int)$u['id'];

/* ---------- Mark all as read ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_read') {
    $conn->query("UPDATE notifications SET is_read=1 WHERE user_id=$userId");
    header("Location: " . BASE . "/notifications.php");
    exit;
}

/* ---------- Auto-generate for tenant ---------- */
if ($u['role'] === 'tenant' && !empty($u['tenant_id'])) {
    require_once 'includes/notifications.php';
    generateDueNotifications($conn, (int)$u['tenant_id'], $userId);
}

/* ---------- Fetch ---------- */
$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY is_read ASC, created_at DESC LIMIT 50");
$stmt->bind_param("i", $userId);
$stmt->execute();
$notifs = $stmt->get_result();

include 'includes/header.php';
?>

<h1>Notifications</h1>

<?php if ($notifs->num_rows === 0): ?>
  <div class="card">
    <p style="color:#6b7280;">No notifications yet. You'll see reminders here when a payment is due soon or overdue.</p>
  </div>
<?php else: ?>
  <div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
      <h2 style="margin:0;">Recent Activity</h2>
      <form method="post">
        <input type="hidden" name="action" value="mark_read">
        <button class="btn btn-sm btn-gray" type="submit">Mark all as read</button>
      </form>
    </div>

    <?php while ($n = $notifs->fetch_assoc()): ?>
      <?php
        $colors = ['info'=>'#dbeafe', 'warning'=>'#fef3c7', 'success'=>'#d1fae5', 'danger'=>'#fee2e2'];
        $bg = $colors[$n['type']] ?? '#f3f4f6';
        $opacity = $n['is_read'] ? '0.6' : '1';
      ?>
      <div style="background:<?= $bg ?>; opacity:<?= $opacity ?>; padding:12px 16px; border-radius:6px; margin-bottom:8px;">
        <div style="display:flex; justify-content:space-between; gap:12px;">
          <div style="flex:1;">
            <div style="font-weight:600; font-size:14px; margin-bottom:2px;">
              <?= htmlspecialchars($n['title']) ?>
              <?php if (!$n['is_read']): ?>
                <span style="background:#2563eb;color:#fff;border-radius:10px;padding:1px 8px;font-size:10px;margin-left:6px;">NEW</span>
              <?php endif; ?>
            </div>
            <div style="font-size:13px; color:#374151;"><?= htmlspecialchars($n['message']) ?></div>
            <div style="font-size:11px; color:#6b7280; margin-top:4px;"><?= $n['created_at'] ?></div>
          </div>
          <?php if ($n['link']): ?>
            <a href="<?= htmlspecialchars($n['link']) ?>" class="btn btn-sm">View</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endwhile; ?>
  </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>