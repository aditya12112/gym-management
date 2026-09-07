<?php
// manage_trainers.php
require 'config.php';
require 'auth.php';
require_login();
require_role('admin');

$msg = ''; $msg_type = '';
$gym_name = htmlspecialchars(get_setting('gym_name', 'GymPro'));

if (isset($_GET['delete'])) {
    if (!csrf_verify($_GET['csrf_token'] ?? '')) {
        $msg = "Security token invalid or expired. Action rejected.";
        $msg_type = 'error';
    } else {
        $id = (int)$_GET['delete'];
        if ($id > 0) {
            $del = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'trainer'");
            $del->bind_param('i', $id);
            if ($del->execute()) {
                $del->close();
                header("Location: manage_trainers.php?msg=deleted");
                exit;
            } else {
                $msg = "Error deleting trainer: " . htmlspecialchars($conn->error);
                $msg_type = 'error';
                $del->close();
            }
        }
    }
}

$search = trim($_GET['q'] ?? '');
if ($search !== '') {
    $param = '%' . $search . '%';
    $t_stmt = $conn->prepare("SELECT u.*, t.speciality, t.experience_years FROM users u LEFT JOIN trainers t ON u.id = t.id WHERE u.role = 'trainer' AND (u.name LIKE ? OR u.email LIKE ?) ORDER BY u.name");
    $t_stmt->bind_param('ss', $param, $param);
    $t_stmt->execute();
    $trainers = $t_stmt->get_result();
} else {
    $trainers = $conn->query("SELECT u.*, t.speciality, t.experience_years FROM users u LEFT JOIN trainers t ON u.id = t.id WHERE u.role = 'trainer' ORDER BY u.name");
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <title>Manage Trainers — <?php echo $gym_name; ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="assets/style.css?v=1.3">
  <script src="assets/theme.js?v=2.0"></script>
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
    <h2>Manage <span class="accent">Trainers</span></h2>
    <a href="admin_add_trainer.php" class="btn">+ Add Trainer</a>
  </div>
  <?php if ($msg): ?><div class="alert alert-<?php echo $msg_type; ?>"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
  <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?><div class="alert alert-success">✅ Trainer deleted successfully.</div><?php endif; ?>

  <form method="get" style="margin-bottom:20px;display:flex;gap:10px;max-width:440px;">
    <input type="text" name="q" placeholder="Search by name or email…" value="<?php echo htmlspecialchars($search); ?>" style="flex:1;">
    <button class="btn" type="submit">Search</button>
    <?php if ($search): ?><a href="manage_trainers.php" class="btn btn-ghost">Clear</a><?php endif; ?>
  </form>

  <?php if ($trainers && $trainers->num_rows > 0): ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Name</th><th>Email</th><th>Speciality</th><th>Experience</th><th>Actions</th></tr></thead>
      <tbody>
      <?php while($r = $trainers->fetch_assoc()): ?>
        <tr>
          <td style="font-weight:600;"><?php echo htmlspecialchars($r['name']); ?></td>
          <td style="color:var(--text-2);"><?php echo htmlspecialchars($r['email']); ?></td>
          <td><?php echo htmlspecialchars($r['speciality'] ?? '—'); ?></td>
          <td><?php echo ($r['experience_years'] ?? 0) ? htmlspecialchars((string)$r['experience_years']).' yrs' : '—'; ?></td>
          <td style="white-space:nowrap;">
            <a class="btn btn-sm btn-outline" href="edit_trainer.php?id=<?php echo (int)$r['id']; ?>">Edit</a>
            <a class="btn btn-sm btn-danger" href="?delete=<?php echo (int)$r['id']; ?>&csrf_token=<?php echo urlencode(csrf_token()); ?>" onclick="return confirm('Delete trainer?')">Delete</a>
          </td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
    <div class="card" style="text-align:center;padding:50px;">
      <div style="font-size:40px;margin-bottom:12px;">🧑‍🏫</div>
      <p style="color:var(--text-3);">No trainers found.</p>
      <a href="admin_add_trainer.php" class="btn" style="margin-top:12px;">+ Add First Trainer</a>
    </div>
  <?php endif; ?>
</div>

</body>
</html>