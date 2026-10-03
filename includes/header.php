<?php
/**
 * NOTE: This file does NOT require auth.php or db.php.
 * Every page loads config/db.php and includes/auth.php BEFORE including this header,
 * so $conn, currentUser(), initials(), etc. are already available.
 * This avoids double-loading and __DIR__-related crashes.
 */

$u = currentUser();
$role = $u['role'] ?? null;
$currentFile = basename($_SERVER['PHP_SELF']);

/* ---------- SVG icon helper ---------- */
if (!function_exists('icon')) {
function icon($name) {
    $icons = [
        'dashboard'  => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>',
        'rooms'      => '<path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6"/>',
        'tenants'    => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'contracts'  => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
        'utility'    => '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
        'payments'   => '<rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/>',
        'maintenance'=> '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
        'qr'         => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><line x1="14" y1="14" x2="14" y2="21"/><line x1="21" y1="14" x2="21" y2="21"/><line x1="14" y1="21" x2="21" y2="21"/>',
        'corrections'=> '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>',
        'billing'    => '<path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1z"/><line x1="8" y1="8" x2="16" y2="8"/><line x1="8" y1="12" x2="16" y2="12"/>',
        'logs'       => '<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>',
        'logout'     => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        'bell'       => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
        'user'       => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
    ];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . ($icons[$name] ?? '') . '</svg>';
}
}

/* ---------- Initials helper ---------- */
if (!function_exists('initials')) {
function initials($name) {
    $name = trim((string)$name);
    if ($name === '') return '?';
    $parts = preg_split('/\s+/', $name);
    $first = isset($parts[0][0]) ? $parts[0][0] : '?';
    $last  = (count($parts) > 1 && isset($parts[count($parts)-1][0])) ? $parts[count($parts)-1][0] : '';
    return strtoupper($first . $last);
}
}

/* ---------- Get display name ---------- */
$displayName = $u['username'] ?? 'User';
if ($role === 'tenant' && !empty($u['tenant_id'])) {
    $tid = (int)$u['tenant_id'];
    $row = $conn->query("SELECT full_name FROM tenants WHERE id=$tid")->fetch_assoc();
    if ($row) $displayName = $row['full_name'];
}

/* ---------- Unread notifications count ---------- */
$unread = 0;
if (!empty($u['id'])) {
    $chk = $conn->prepare("SELECT COUNT(*) c FROM notifications WHERE user_id = ? AND is_read = 0");
    $chk->bind_param("i", $u['id']);
    $chk->execute();
    $unread = (int)$chk->get_result()->fetch_assoc()['c'];
}

/* ---------- Safe avatar fetch (for sidebar) ---------- */
$sidebarAvatar = null;
$sidebarHasAvatar = false;
if (!empty($u['id'])) {
    $avRes = $conn->query("SELECT avatar FROM users WHERE id=" . (int)$u['id']);
    if ($avRes) {
        $avRow = $avRes->fetch_assoc();
        $sidebarAvatar = $avRow['avatar'] ?? null;
        if (!empty($sidebarAvatar)) {
            $avPath = $_SERVER['DOCUMENT_ROOT'] . '/bhconnect/uploads/avatars/' . $sidebarAvatar;
            if (file_exists($avPath)) $sidebarHasAvatar = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>BHConnect</title>
<script>
(function() {
  var saved = localStorage.getItem('theme') || 'light';
  document.documentElement.setAttribute('data-theme', saved);
})();
</script>
<link rel="stylesheet" href="<?= BASE ?>/assets/css/style.css">
</head>
<body>

<?php if ($role): ?>
<button class="theme-toggle" onclick="toggleTheme()" title="Toggle dark mode">
  <span id="theme-icon">🌙</span>
</button>

<script>
function toggleTheme() {
  var current = document.documentElement.getAttribute('data-theme') || 'light';
  var next = current === 'dark' ? 'light' : 'dark';
  document.documentElement.setAttribute('data-theme', next);
  localStorage.setItem('theme', next);
  document.getElementById('theme-icon').textContent = next === 'dark' ? '☀️' : '🌙';
}
document.addEventListener('DOMContentLoaded', function() {
  var theme = document.documentElement.getAttribute('data-theme') || 'light';
  var icon = document.getElementById('theme-icon');
  if (icon) icon.textContent = theme === 'dark' ? '☀️' : '🌙';
});
</script>

<aside class="sidebar">

  <!-- ===== USER CARD (TOP) ===== -->
  <a href="<?= BASE ?>/profile.php" class="user-card" style="text-decoration:none;">
    <div class="avatar">
      <?php if ($sidebarHasAvatar): ?>
        <img src="<?= BASE ?>/uploads/avatars/<?= htmlspecialchars($sidebarAvatar) ?>?v=<?= time() ?>"
             alt="Avatar" style="width:100%; height:100%; object-fit:cover; border-radius:50%;">
      <?php else: ?>
        <?= initials($displayName) ?>
      <?php endif; ?>
    </div>
    <div class="user-info">
      <div class="user-name"><?= htmlspecialchars($displayName) ?></div>
      <div class="user-role"><?= htmlspecialchars($role) ?></div>
    </div>
  </a>

  <!-- ===== MAIN MENU ===== -->
  <div class="menu-label">MAIN MENU</div>
  <nav class="menu">
    <?php if ($role === 'landlord'): ?>
      <a href="<?= BASE ?>/landlord/dashboard.php" class="<?= $currentFile === 'dashboard.php' ? 'active' : '' ?>">
        <?= icon('dashboard') ?><span>Dashboard</span>
      </a>
      <a href="<?= BASE ?>/landlord/rooms.php" class="<?= $currentFile === 'rooms.php' ? 'active' : '' ?>">
        <?= icon('rooms') ?><span>Rooms</span>
      </a>
      <a href="<?= BASE ?>/landlord/tenants.php" class="<?= $currentFile === 'tenants.php' ? 'active' : '' ?>">
        <?= icon('tenants') ?><span>Tenants</span>
      </a>
      <a href="<?= BASE ?>/landlord/contracts.php" class="<?= $currentFile === 'contracts.php' ? 'active' : '' ?>">
        <?= icon('contracts') ?><span>Contracts</span>
      </a>
      <a href="<?= BASE ?>/landlord/utility.php" class="<?= $currentFile === 'utility.php' ? 'active' : '' ?>">
        <?= icon('utility') ?><span>Utility</span>
      </a>
      <a href="<?= BASE ?>/landlord/payments.php" class="<?= $currentFile === 'payments.php' ? 'active' : '' ?>">
        <?= icon('payments') ?><span>Payments</span>
      </a>
      <a href="<?= BASE ?>/landlord/maintenance.php" class="<?= $currentFile === 'maintenance.php' ? 'active' : '' ?>">
        <?= icon('maintenance') ?><span>Maintenance</span>
      </a>
      <a href="<?= BASE ?>/landlord/qr_display.php" class="<?= $currentFile === 'qr_display.php' ? 'active' : '' ?>">
        <?= icon('qr') ?><span>QR Code</span>
      </a>
      <a href="<?= BASE ?>/landlord/corrections.php" class="<?= $currentFile === 'corrections.php' ? 'active' : '' ?>">
        <?= icon('corrections') ?><span>Corrections</span>
      </a>
    <?php else: ?>
      <a href="<?= BASE ?>/tenant/dashboard.php" class="<?= $currentFile === 'dashboard.php' ? 'active' : '' ?>">
        <?= icon('dashboard') ?><span>Dashboard</span>
      </a>
      <a href="<?= BASE ?>/tenant/billing.php" class="<?= $currentFile === 'billing.php' ? 'active' : '' ?>">
        <?= icon('billing') ?><span>Billing</span>
      </a>
      <a href="<?= BASE ?>/tenant/scan.php" class="<?= $currentFile === 'scan.php' ? 'active' : '' ?>">
        <?= icon('qr') ?><span>Scan QR</span>
      </a>
      <a href="<?= BASE ?>/tenant/logs.php" class="<?= $currentFile === 'logs.php' ? 'active' : '' ?>">
        <?= icon('logs') ?><span>My Logs</span>
      </a>
      <a href="<?= BASE ?>/tenant/corrections.php" class="<?= $currentFile === 'corrections.php' ? 'active' : '' ?>">
        <?= icon('corrections') ?><span>Corrections</span>
      </a>
      <a href="<?= BASE ?>/tenant/maintenance.php" class="<?= $currentFile === 'maintenance.php' ? 'active' : '' ?>">
        <?= icon('maintenance') ?><span>Maintenance</span>
      </a>
    <?php endif; ?>
  </nav>

  <!-- ===== ACCOUNT ===== -->
  <div class="menu-label">ACCOUNT</div>
  <nav class="menu">
    <a href="<?= BASE ?>/profile.php" class="<?= $currentFile === 'profile.php' ? 'active' : '' ?>">
      <?= icon('user') ?><span>My Profile</span>
    </a>
    <a href="<?= BASE ?>/logout.php">
      <?= icon('logout') ?><span>Logout</span>
    </a>
  </nav>

  <!-- ===== NOTIFICATIONS ===== -->
  <div class="menu-label">NOTIFICATIONS</div>
  <nav class="menu">
    <a href="<?= BASE ?>/notifications.php" class="<?= $currentFile === 'notifications.php' ? 'active' : '' ?>">
      <?= icon('bell') ?><span>Alerts<?= $unread > 0 ? ' <span style="background:#dc2626;color:#fff;border-radius:10px;padding:1px 7px;font-size:11px;margin-left:4px;">' . $unread . '</span>' : '' ?></span>
    </a>
  </nav>

  <!-- ===== BRAND (BOTTOM) ===== -->
  <div class="brand">
    <div class="brand-icon">BH</div>
    <div class="brand-text">
      <div class="brand-name">BHConnect</div>
      <div class="brand-sub">Boarding House System</div>
    </div>
  </div>

</aside>
<?php endif; ?>

<main class="content">