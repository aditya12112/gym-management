<?php
require 'config.php';
require 'auth.php';
require_login();
require_role('admin');

$msg = ''; $msg_type = '';
$gym_name = htmlspecialchars(get_setting('gym_name', 'GymPro'));

// Delete Member
if (isset($_GET['delete'])) {
    if (!csrf_verify($_GET['csrf_token'] ?? '')) {
        $msg = "Security token invalid or expired. Cannot delete.";
        $msg_type = 'error';
    } else {
        $id = (int)$_GET['delete'];
        if ($id === (int)$_SESSION['user_id']) {
            $msg = "Cannot delete your own account.";
            $msg_type = 'error';
        } elseif ($id > 0) {
            $del = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'member'");
            $del->bind_param('i', $id);
            if ($del->execute()) {
                $msg = "Member deleted successfully.";
                $msg_type = 'success';
            } else {
                $msg = "Error: " . htmlspecialchars($conn->error);
                $msg_type = 'error';
            }
            $del->close();
        }
    }
}

// Promote to Trainer
if (isset($_GET['promote_trainer'])) {
    if (!csrf_verify($_GET['csrf_token'] ?? '')) {
        $msg = "Security token invalid or expired. Action rejected.";
        $msg_type = 'error';
    } else {
        $id = (int)$_GET['promote_trainer'];
        if ($id > 0) {
            $upd = $conn->prepare("UPDATE users SET role = 'trainer' WHERE id = ?");
            $upd->bind_param('i', $id);
            if ($upd->execute()) {
                $conn->query("CREATE TABLE IF NOT EXISTS trainers (id INT PRIMARY KEY, speciality VARCHAR(100) DEFAULT '', experience_years INT DEFAULT 0, bio TEXT, CONSTRAINT fk_tu FOREIGN KEY (id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
                $t_chk = $conn->prepare("SELECT id FROM trainers WHERE id = ?");
                $t_chk->bind_param('i', $id);
                $t_chk->execute();
                if ($t_chk->get_result()->num_rows === 0) {
                    $t_ins = $conn->prepare("INSERT INTO trainers (id, speciality, experience_years, bio) VALUES (?, '', 0, '')");
                    $t_ins->bind_param('i', $id);
                    $t_ins->execute();
                    $t_ins->close();
                }
                $t_chk->close();
                $msg = "User promoted to trainer.";
                $msg_type = 'success';
            } else {
                $msg = "Error: " . htmlspecialchars($conn->error);
                $msg_type = 'error';
            }
            $upd->close();
        }
    }
}

// Search
$search = trim($_GET['q'] ?? '');
if ($search !== '') {
    $param = '%' . $search . '%';
    $users_stmt = $conn->prepare("SELECT id, name, email, phone, age, gender, created_at FROM users WHERE role = 'member' AND (name LIKE ? OR email LIKE ? OR phone LIKE ?) ORDER BY created_at DESC");
    $users_stmt->bind_param('sss', $param, $param, $param);
    $users_stmt->execute();
    $users_q = $users_stmt->get_result();
} else {
    $users_q = $conn->query("SELECT id, name, email, phone, age, gender, created_at FROM users WHERE role = 'member' ORDER BY created_at DESC");
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <title>Manage Members — <?php echo $gym_name; ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="assets/style.css?v=1.2">
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
    <h2>Manage <span class="accent">Members</span></h2>
    <a href="admin_add_member.php" class="btn">+ Add Member</a>
  </div>

  <?php if ($msg): ?><div class="alert alert-<?php echo $msg_type==='success'?'success':'error'; ?>"><?php echo $msg_type==='success'?'✅':'⚠'; ?> <?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

  <!-- Search -->
  <form method="get" style="margin-bottom:20px;display:flex;gap:10px;max-width:480px;">
    <input type="text" name="q" placeholder="Search by name, email or phone…" value="<?php echo htmlspecialchars($search); ?>" style="flex:1;">
    <button class="btn" type="submit">Search</button>
    <?php if ($search): ?><a href="manage_users.php" class="btn btn-ghost">Clear</a><?php endif; ?>
  </form>

  <?php if ($users_q && $users_q->num_rows > 0): ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Age</th><th>Gender</th><th>Joined</th><th>Actions</th></tr></thead>
      <tbody>
      <?php while($u = $users_q->fetch_assoc()): ?>
        <tr>
          <td style="font-weight:600;"><?php echo htmlspecialchars($u['name']); ?></td>
          <td style="color:var(--text-2);"><?php echo htmlspecialchars($u['email']); ?></td>
          <td style="color:var(--text-3);"><?php echo htmlspecialchars($u['phone'] ?? '—'); ?></td>
          <td><?php echo htmlspecialchars((string)($u['age'] ?? '—')); ?></td>
          <td><?php echo htmlspecialchars($u['gender'] ?? '—'); ?></td>
          <td style="font-size:12px;color:var(--text-3);"><?php echo htmlspecialchars($u['created_at']); ?></td>
          <td style="white-space:nowrap;">
            <a class="btn btn-sm btn-outline" href="edit_user.php?id=<?php echo (int)$u['id']; ?>">Edit</a>
            <a class="btn btn-sm btn-outline" href="?promote_trainer=<?php echo (int)$u['id']; ?>&csrf_token=<?php echo urlencode(csrf_token()); ?>" onclick="return confirm('Promote to Trainer?')">→ Trainer</a>
            <a class="btn btn-sm btn-danger" href="?delete=<?php echo (int)$u['id']; ?>&csrf_token=<?php echo urlencode(csrf_token()); ?>" onclick="return confirm('Delete this member?')">Delete</a>
          </td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
    <div class="card" style="text-align:center;padding:50px;">
      <div style="font-size:40px;margin-bottom:12px;">👥</div>
      <p style="color:var(--text-3);"><?php echo $search ? 'No members match your search.' : 'No members registered yet.'; ?></p>
      <a href="admin_add_member.php" class="btn" style="margin-top:12px;">+ Add First Member</a>
    </div>
  <?php endif; ?>
</div>
<script src="assets/theme.js?v=1.2"></script>
</body>
</html>