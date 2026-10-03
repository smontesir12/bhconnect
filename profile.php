<?php
require 'config/db.php';
require 'includes/auth.php';
requireLogin();

$u = currentUser();
$userId = (int)$u['id'];
$msg = ''; $err = '';

/* ---------- Upload avatar ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'avatar') {
    $file = $_FILES['avatar'] ?? null;

    if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
        $err = "Please choose a file.";
    } elseif ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        $err = "File is too large. Maximum allowed is about 1.5 MB.";
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $err = "Upload error (code " . $file['error'] . "). Please try again.";
    } else {
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $mime = mime_content_type($file['tmp_name']);

        if (!isset($allowed[$mime])) {
            $err = "Only JPG, PNG, and WebP images are allowed. Detected: " . htmlspecialchars($mime);
        } elseif ($file['size'] > 1.5 * 1024 * 1024) {
            $err = "File must be 1.5 MB or smaller. Your file: " . number_format($file['size'] / 1024 / 1024, 2) . " MB.";
        } else {
            // Build the upload directory path
            $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/bhconnect/uploads/avatars/';

            // Create the folder if it does not exist
            if (!is_dir($uploadDir)) {
                if (!@mkdir($uploadDir, 0777, true)) {
                    $err = "Could not create upload folder at: " . htmlspecialchars($uploadDir);
                }
            }

            if (empty($err)) {
                $ext = $allowed[$mime];
                $filename = "user_{$userId}_" . time() . ".{$ext}";
                $dest = $uploadDir . $filename;

                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    // Delete the previous avatar file
                    $oldRow = $conn->query("SELECT avatar FROM users WHERE id=$userId")->fetch_assoc();
                    $old = $oldRow['avatar'] ?? null;
                    if (!empty($old) && file_exists($uploadDir . $old)) {
                        @unlink($uploadDir . $old);
                    }

                    $stmt = $conn->prepare("UPDATE users SET avatar=? WHERE id=?");
                    $stmt->bind_param("si", $filename, $userId);
                    $stmt->execute();
                    $msg = "Profile picture updated.";
                } else {
                    $err = "Could not save the file. Folder: " . htmlspecialchars($uploadDir) . " — please verify it exists and is writable.";
                }
            }
        }
    }
}

/* ---------- Change password ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'password') {
    $current = $_POST['current'] ?? '';
    $new     = $_POST['new'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    $row = $conn->query("SELECT password FROM users WHERE id=$userId")->fetch_assoc();
    if (!password_verify($current, $row['password'])) {
        $err = "Current password is incorrect.";
    } elseif (strlen($new) < 6) {
        $err = "New password must be at least 6 characters.";
    } elseif ($new !== $confirm) {
        $err = "New passwords do not match.";
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->bind_param("si", $hash, $userId);
        $stmt->execute();
        $msg = "Password changed successfully.";
    }
}

/* ---------- Fetch user ---------- */
$user = $conn->query("SELECT * FROM users WHERE id=$userId")->fetch_assoc();

$displayName = $user['username'];
if ($u['role'] === 'tenant' && !empty($u['tenant_id'])) {
    $r = $conn->query("SELECT full_name FROM tenants WHERE id=" . (int)$u['tenant_id'])->fetch_assoc();
    if ($r) $displayName = $r['full_name'];
}

/* ---------- Avatar display check ---------- */
$avatarFile = $user['avatar'] ?? null;
$hasAvatar = false;
if ($avatarFile) {
$fullPath = $_SERVER['DOCUMENT_ROOT'] . '/bhconnect/uploads/avatars/' . $avatarFile;
    if (file_exists($fullPath)) $hasAvatar = true;
}

include 'includes/header.php';
?>

<style>
  .password-wrapper { position: relative; margin-bottom: 10px; }
  .password-wrapper input { margin-bottom: 0; padding-right: 44px; }
  .password-toggle {
    position: absolute; right: 4px; top: 50%; transform: translateY(-50%);
    background: transparent; border: none; cursor: pointer;
    padding: 6px 10px; font-size: 16px; line-height: 1;
    color: var(--text-muted); border-radius: 4px;
    min-width: 36px; height: 32px;
    display: flex; align-items: center; justify-content: center;
  }
  .password-toggle:hover { background: var(--border-soft); }
  .password-toggle:focus { outline: none; }
</style>

<h1>My Profile</h1>
<?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>

<div class="card" style="text-align:center;">
  <div style="width:120px; height:120px; margin:0 auto 12px; border-radius:50%; overflow:hidden; background:var(--accent); display:flex; align-items:center; justify-content:center; color:#fff; font-size:42px; font-weight:600;">
    <?php if ($hasAvatar): ?>
      <img src="<?= BASE ?>/uploads/avatars/<?= htmlspecialchars($avatarFile) ?>?v=<?= time() ?>"
           alt="Avatar" style="width:100%; height:100%; object-fit:cover;">
    <?php else: ?>
      <?= strtoupper(substr($displayName, 0, 1)) ?>
    <?php endif; ?>
  </div>

  <h2 style="margin: 6px 0 2px;"><?= htmlspecialchars($displayName) ?></h2>
  <p style="color:var(--text-muted); font-size:13px; margin:0;">
    @<?= htmlspecialchars($user['username']) ?> · <?= htmlspecialchars($user['role']) ?>
  </p>
</div>

<div class="card">
  <h2>Change Profile Picture</h2>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="action" value="avatar">
    <label>Choose an image (JPG, PNG, WebP · max 2 MB)</label>
    <input type="file" name="avatar" accept="image/*" required>
    <button type="submit">Upload</button>
  </form>
</div>

<div class="card">
  <h2>Change Password</h2>
  <form method="post">
    <input type="hidden" name="action" value="password">
    <div class="grid-3">
      <div>
        <label>Current Password</label>
        <div class="password-wrapper">
          <input type="password" name="current" id="pw_current" required>
          <button type="button" class="password-toggle" onclick="togglePassword('pw_current', this)" title="Show password">👁</button>
        </div>
      </div>
      <div>
        <label>New Password</label>
        <div class="password-wrapper">
          <input type="password" name="new" id="pw_new" required minlength="6">
          <button type="button" class="password-toggle" onclick="togglePassword('pw_new', this)" title="Show password">👁</button>
        </div>
      </div>
      <div>
        <label>Confirm New Password</label>
        <div class="password-wrapper">
          <input type="password" name="confirm" id="pw_confirm" required minlength="6">
          <button type="button" class="password-toggle" onclick="togglePassword('pw_confirm', this)" title="Show password">👁</button>
        </div>
      </div>
    </div>
    <button type="submit">Change Password</button>
  </form>
</div>

<script>
function togglePassword(inputId, btn) {
  var input = document.getElementById(inputId);
  if (input.type === 'password') {
    input.type = 'text';
    btn.textContent = '🙈';
  } else {
    input.type = 'password';
    btn.textContent = '👁';
  }
}
</script>

<?php include 'includes/footer.php'; ?>
