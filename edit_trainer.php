<?php
// edit_trainer.php
require 'config.php';
require 'auth.php';
require_login();
require_role('admin');

$msg = ''; $msg_type = '';
$gym_name = htmlspecialchars(get_setting('gym_name', 'GymPro'));
$tid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($tid <= 0) die("Invalid trainer id.");

$conn->query("CREATE TABLE IF NOT EXISTS trainers (id INT PRIMARY KEY, speciality VARCHAR(100) DEFAULT '', experience_years INT DEFAULT 0, bio TEXT, CONSTRAINT fk_trainers_user FOREIGN KEY (id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$res = $conn->prepare("SELECT u.*, t.speciality, t.experience_years, t.bio FROM users u LEFT JOIN trainers t ON u.id=t.id WHERE u.id = ? LIMIT 1");
$res->bind_param('i', $tid);
$res->execute();
$trainer_q = $res->get_result();
if (!$trainer_q || $trainer_q->num_rows === 0) die("Trainer not found.");
$trainer = $trainer_q->fetch_assoc();
$res->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $name       = trim($_POST['name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $role       = trim($_POST['role'] ?? 'trainer');
    $speciality = trim($_POST['speciality'] ?? '');
    $experience = (int)($_POST['experience'] ?? 0);
    $bio        = trim($_POST['bio'] ?? '');
    $new_pw     = $_POST['new_password'] ?? '';

    if (empty($name) || empty($email)) {
        $msg = "Name and email required.";
        $msg_type = 'error';
    } else {
        $dup = $conn->prepare("SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1");
        $dup->bind_param('si', $email, $tid);
        $dup->execute();
        $dup_res = $dup->get_result();

        if ($dup_res && $dup_res->num_rows > 0) {
            $msg = "Email is already used by another account.";
            $msg_type = 'error';
        } else {
            $valid_role = in_array($role, ['trainer', 'member', 'admin']) ? $role : 'trainer';
            $upd = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, role = ? WHERE id = ?");
            $upd->bind_param('ssssi', $name, $email, $phone, $valid_role, $tid);
            $upd->execute();
            $upd->close();

            if ($valid_role === 'trainer') {
                $t_upd = $conn->prepare("INSERT INTO trainers (id, speciality, experience_years, bio) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE speciality = VALUES(speciality), experience_years = VALUES(experience_years), bio = VALUES(bio)");
                $t_upd->bind_param('isis', $tid, $speciality, $experience, $bio);
                $t_upd->execute();
                $t_upd->close();
            }

            if (!empty($new_pw)) {
                $hash = password_hash($new_pw, PASSWORD_DEFAULT);
                $pw_upd = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $pw_upd->bind_param('si', $hash, $tid);
                $pw_upd->execute();
                $pw_upd->close();
                $msg = "Trainer updated and password securely reset.";
            } else {
                $msg = "Trainer updated successfully.";
            }
            $msg_type = 'success';

            $r2 = $conn->prepare("SELECT u.*, t.speciality, t.experience_years, t.bio FROM users u LEFT JOIN trainers t ON u.id=t.id WHERE u.id = ? LIMIT 1");
            $r2->bind_param('i', $tid);
            $r2->execute();
            $trainer = $r2->get_result()->fetch_assoc();
            $r2->close();
        }
        $dup->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><title>Edit Trainer — <?php echo $gym_name; ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<nav class="navbar">
  <div class="navbar-brand">🏋️ <?php echo $gym_name; ?></div>
  <div class="navbar-links">
    <a href="manage_trainers.php">← Trainers</a>
    <a href="admin_dashboard.php">Dashboard</a>
    <a href="logout.php" class="nav-logout">Logout</a>
  </div>
</nav>
<div class="container">
  <div class="page-header">
    <div>
      <h2>Edit <span class="accent">Trainer</span></h2>
      <p style="margin:0;font-size:14px;color:var(--text-3);"><?php echo htmlspecialchars($trainer['name']); ?></p>
    </div>
  </div>
  <?php if ($msg): ?>
    <div class="alert alert-<?php echo $msg_type==='success'?'success':'error'; ?>" style="max-width:580px;">
      <?php echo $msg_type==='success'?'✅':'⚠'; ?> <?php echo htmlspecialchars($msg); ?>
    </div>
  <?php endif; ?>
  <div class="form-card" style="max-width:600px;">
    <form method="post">
      <?php echo csrf_field(); ?>
      <div class="form-group"><label>Full Name *</label><input type="text" name="name" required value="<?php echo htmlspecialchars($trainer['name']); ?>"></div>
      <div class="form-group"><label>Email *</label><input type="email" name="email" required value="<?php echo htmlspecialchars($trainer['email']); ?>"></div>
      <div class="form-group"><label>Phone</label><input type="text" name="phone" value="<?php echo htmlspecialchars($trainer['phone']??''); ?>"></div>
      <div class="form-group">
        <label>Role</label>
        <select name="role">
          <option value="trainer" <?php if($trainer['role']==='trainer') echo 'selected'; ?>>Trainer</option>
          <option value="member"  <?php if($trainer['role']==='member') echo 'selected'; ?>>Member</option>
          <option value="admin"   <?php if($trainer['role']==='admin') echo 'selected'; ?>>Admin</option>
        </select>
      </div>
      <hr class="form-divider">
      <h4 style="margin-bottom:16px;">Trainer Profile</h4>
      <div class="form-group"><label>Speciality</label><input type="text" name="speciality" value="<?php echo htmlspecialchars($trainer['speciality']??''); ?>"></div>
      <div class="form-group"><label>Experience (years)</label><input type="number" name="experience" min="0" value="<?php echo htmlspecialchars($trainer['experience_years']??0); ?>"></div>
      <div class="form-group"><label>Bio</label><textarea name="bio"><?php echo htmlspecialchars($trainer['bio']??''); ?></textarea></div>
      <hr class="form-divider">
      <div class="form-group">
        <label>Reset Password <span style="color:var(--text-3);font-size:11px;text-transform:none;">(leave blank to keep current)</span></label>
        <input type="text" name="new_password" placeholder="New temporary password">
      </div>
      <div class="form-actions">
        <button class="btn" type="submit">Save Changes</button>
        <a href="manage_trainers.php" class="btn btn-ghost">Cancel</a>
      </div>
    </form>
  </div>
</div>
<script src="assets/theme.js"></script>
</body>
</html>