<?php
require 'config.php';
require 'auth.php';
require_login();
require_role('admin');

$msg = ''; $msg_type = '';
$gym_name = htmlspecialchars(get_setting('gym_name', 'GymPro'));

// Delete Admin
if (isset($_GET['delete'])) {
    if (!csrf_verify($_GET['csrf_token'] ?? '')) {
        $msg = "Security token invalid or expired. Cannot delete.";
        $msg_type = 'error';
    } else {
        $id = (int)$_GET['delete'];
        if ($id === (int)$_SESSION['user_id']) {
            $msg = "You cannot delete your own account.";
            $msg_type = 'error';
        } elseif ($id > 0) {
            $stmt = $conn->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $res->num_rows === 1 && $res->fetch_assoc()['role'] === 'admin') {
                $del = $conn->prepare("DELETE FROM users WHERE id = ?");
                $del->bind_param('i', $id);
                if ($del->execute()) {
                    $msg = "Admin deleted successfully.";
                    $msg_type = 'success';
                } else {
                    $msg = "Error deleting admin: " . htmlspecialchars($conn->error);
                    $msg_type = 'error';
                }
                $del->close();
            } else {
                $msg = "Admin not found.";
                $msg_type = 'error';
            }
            $stmt->close();
        }
    }
}

// Reset Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_id'], $_POST['reset_password'])) {
    require_csrf();
    $rid   = (int)$_POST['reset_id'];
    $newpw = $_POST['reset_password'];
    if ($rid === (int)$_SESSION['user_id']) {
        $msg = "Cannot reset your own password here. Use settings.";
        $msg_type = 'error';
    } elseif ($rid > 0 && $newpw !== '') {
        $hash = password_hash($newpw, PASSWORD_DEFAULT);
        $upd = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ? AND role = 'admin'");
        $upd->bind_param('si', $hash, $rid);
        if ($upd->execute()) {
            $msg = "Password securely reset with bcrypt for admin ID $rid.";
            $msg_type = 'success';
        } else {
            $msg = "Error: " . htmlspecialchars($conn->error);
            $msg_type = 'error';
        }
        $upd->close();
    }
}

$admins_q = $conn->query("SELECT id, name, email, created_at FROM users WHERE role='admin' ORDER BY name");
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <title>Manage Admins — <?php echo $gym_name; ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="assets/style.css?v=1.3">
  <script src="assets/theme.js?v=2.0"></script>
  <style>
    .inline-form { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
    .inline-form input { width:180px; flex-shrink:0; }
  </style>
</head>
<body>
<nav class="navbar">
  <div class="navbar-brand">🏋️ <?php echo $gym_name; ?></div>
  <div class="navbar-links">
    <a href="admin_dashboard.php">← Dashboard</a>
    <a href="logout.php" class="nav-logout">Logout</a>
  </div>
</nav>
<div class="container">
  <div class="page-header">
    <h2>Manage <span class="accent">Admins</span></h2>
    <a href="admin_add_admin.php" class="btn">+ Add Admin</a>
  </div>
  <?php if ($msg): ?>
    <div class="alert alert-<?php echo $msg_type==='success'?'success':'error'; ?>">
      <?php echo $msg_type==='success'?'✅':'⚠'; ?> <?php echo htmlspecialchars($msg); ?>
    </div>
  <?php endif; ?>

  <?php if ($admins_q && $admins_q->num_rows > 0): ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Name</th><th>Email</th><th>Joined</th><th>Actions</th></tr></thead>
      <tbody>
      <?php while($a = $admins_q->fetch_assoc()): $isMe = $a['id'] === (int)$_SESSION['user_id']; ?>
        <tr>
          <td style="font-weight:600;">
            <?php echo htmlspecialchars($a['name']); ?>
            <?php if ($isMe): ?><span class="badge badge-lime" style="margin-left:6px;font-size:10px;">You</span><?php endif; ?>
          </td>
          <td style="color:var(--text-2);"><?php echo htmlspecialchars($a['email']); ?></td>
          <td style="font-size:12px;color:var(--text-3);"><?php echo htmlspecialchars($a['created_at']); ?></td>
          <td>
            <div style="display:flex;flex-direction:column;gap:8px;">
              <a class="btn btn-sm btn-outline" href="edit_user.php?id=<?php echo (int)$a['id']; ?>">Edit</a>
              <?php if (!$isMe): ?>
                <form method="post" class="inline-form" onsubmit="return confirm('Reset password for <?php echo htmlspecialchars($a['name'],ENT_QUOTES); ?>?')">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="reset_id" value="<?php echo (int)$a['id']; ?>">
                  <input type="text" name="reset_password" placeholder="New temp password" required>
                  <button class="btn btn-sm" type="submit">Reset PW</button>
                </form>
                <a class="btn btn-sm btn-danger" href="?delete=<?php echo (int)$a['id']; ?>&csrf_token=<?php echo urlencode(csrf_token()); ?>" onclick="return confirm('Delete this admin?')">Delete</a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
    <p style="color:var(--text-3);">No admin accounts found.</p>
  <?php endif; ?>
</div>

</body>
</html>