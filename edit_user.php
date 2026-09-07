<?php
require 'config.php';
require 'auth.php';
require_login();
require_role('admin');

$msg = ''; $msg_type = '';
$gym_name = htmlspecialchars(get_setting('gym_name', 'GymPro'));
$uid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($uid <= 0) die("Invalid user id.");

$user_stmt = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$user_stmt->bind_param('i', $uid);
$user_stmt->execute();
$user_q = $user_stmt->get_result();
if (!$user_q || $user_q->num_rows === 0) die("User not found.");
$user = $user_q->fetch_assoc();
$user_stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $name   = trim($_POST['name'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $phone  = trim($_POST['phone'] ?? '');
    $age    = isset($_POST['age']) && $_POST['age'] !== '' ? (int)$_POST['age'] : null;
    $gender = trim($_POST['gender'] ?? '');
    $role   = trim($_POST['role'] ?? 'member');
    $new_pw = $_POST['new_password'] ?? '';

    if ($name === '' || $email === '') {
        $msg = "Name and email are required."; $msg_type = 'error';
    } else {
        $dup = $conn->prepare("SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1");
        $dup->bind_param('si', $email, $uid);
        $dup->execute();
        $dup_res = $dup->get_result();

        if ($dup_res && $dup_res->num_rows > 0) {
            $msg = "Email is already used by another account."; $msg_type = 'error';
        } else {
            $roleEsc = in_array($role, ['member','trainer','admin']) ? $role : 'member';

            $upd = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, age = ?, gender = ?, role = ? WHERE id = ?");
            $upd->bind_param('sssissi', $name, $email, $phone, $age, $gender, $roleEsc, $uid);

            if (!$upd->execute()) {
                $msg = "Error: " . htmlspecialchars($conn->error); $msg_type = 'error';
            } else {
                if (!empty($new_pw)) {
                    $hash = password_hash($new_pw, PASSWORD_DEFAULT);
                    $pw_upd = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    $pw_upd->bind_param('si', $hash, $uid);
                    $pw_upd->execute();
                    $pw_upd->close();
                    $msg = "User updated and password securely reset.";
                } else {
                    $msg = "User updated successfully.";
                }
                $msg_type = 'success';

                if ($roleEsc === 'trainer') {
                    $conn->query("CREATE TABLE IF NOT EXISTS trainers (
                        id INT PRIMARY KEY, speciality VARCHAR(100) DEFAULT '',
                        experience_years INT DEFAULT 0, bio TEXT,
                        CONSTRAINT fk_trainers_user FOREIGN KEY (id) REFERENCES users(id) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
                    $t_chk = $conn->prepare("SELECT id FROM trainers WHERE id = ? LIMIT 1");
                    $t_chk->bind_param('i', $uid);
                    $t_chk->execute();
                    if ($t_chk->get_result()->num_rows === 0) {
                        $t_ins = $conn->prepare("INSERT INTO trainers (id, speciality, experience_years, bio) VALUES (?, '', 0, '')");
                        $t_ins->bind_param('i', $uid);
                        $t_ins->execute();
                        $t_ins->close();
                    }
                    $t_chk->close();
                }

                $user_stmt = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
                $user_stmt->bind_param('i', $uid);
                $user_stmt->execute();
                $user = $user_stmt->get_result()->fetch_assoc();
                $user_stmt->close();
            }
            $upd->close();
        }
        $dup->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <title>Edit User — <?php echo $gym_name; ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="assets/style.css?v=1.3">
  <script src="assets/theme.js?v=2.0"></script>
</head>
<body>
<nav class="navbar">
  <div class="navbar-brand">🏋️ <?php echo $gym_name; ?></div>
  <div class="navbar-links">
    <a href="manage_users.php">← Members</a>
    <a href="admin_dashboard.php">Dashboard</a>
    <a href="logout.php" class="nav-logout">Logout</a>
  </div>
</nav>

<div class="container">
  <div class="page-header">
    <div>
      <h2>Edit <span class="accent">User</span></h2>
      <p style="margin:0;font-size:14px;color:var(--text-3);">
        <?php echo htmlspecialchars($user['name']); ?> · ID: <?php echo (int)$user['id']; ?>
      </p>
    </div>
  </div>

  <?php if ($msg): ?>
    <div class="alert alert-<?php echo $msg_type === 'success' ? 'success' : 'error'; ?>" style="max-width:560px;">
      <?php echo $msg_type === 'success' ? '✅' : '⚠'; ?> <?php echo htmlspecialchars($msg); ?>
    </div>
  <?php endif; ?>

  <div class="form-card">
    <form method="post">
      <?php echo csrf_field(); ?>
      <div class="form-group">
        <label>Full Name *</label>
        <input type="text" name="name" required value="<?php echo htmlspecialchars($user['name']); ?>">
      </div>
      <div class="form-group">
        <label>Email Address *</label>
        <input type="email" name="email" required value="<?php echo htmlspecialchars($user['email']); ?>">
      </div>
      <div class="form-group">
        <label>Phone</label>
        <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
        <div class="form-group">
          <label>Age</label>
          <input type="number" name="age" value="<?php echo htmlspecialchars((string)($user['age'] ?? '')); ?>">
        </div>
        <div class="form-group">
          <label>Gender</label>
          <select name="gender">
            <option value="">Select</option>
            <option value="Male"   <?php if(($user['gender'] ?? '')==='Male') echo 'selected'; ?>>Male</option>
            <option value="Female" <?php if(($user['gender'] ?? '')==='Female') echo 'selected'; ?>>Female</option>
            <option value="Other"  <?php if(($user['gender'] ?? '')==='Other') echo 'selected'; ?>>Other</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label>Role</label>
        <select name="role">
          <option value="member"  <?php if($user['role']==='member')  echo 'selected'; ?>>Member</option>
          <option value="trainer" <?php if($user['role']==='trainer') echo 'selected'; ?>>Trainer</option>
          <option value="admin"   <?php if($user['role']==='admin')   echo 'selected'; ?>>Admin</option>
        </select>
      </div>

      <hr class="form-divider">

      <div class="form-group">
        <label>Reset Password <span style="color:var(--text-3);font-size:11px;text-transform:none;">(leave blank to keep current)</span></label>
        <input type="text" name="new_password" placeholder="New temporary password">
      </div>

      <div class="form-actions">
        <button class="btn" type="submit">Save Changes</button>
        <a href="manage_users.php" class="btn btn-ghost">Cancel</a>
      </div>
    </form>
  </div>
</div>

</body>
</html>