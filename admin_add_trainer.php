<?php
require 'config.php';
require 'auth.php';
require_login();
require_role('admin');

$msg = ''; $msg_type = '';
$gym_name = htmlspecialchars(get_setting('gym_name', 'GymPro'));

$conn->query("CREATE TABLE IF NOT EXISTS trainers (id INT PRIMARY KEY, speciality VARCHAR(100) DEFAULT '', experience_years INT DEFAULT 0, bio TEXT, CONSTRAINT fk_trainers_user FOREIGN KEY (id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $name       = trim($_POST['name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $password   = $_POST['password'] ?? '';
    $speciality = trim($_POST['speciality'] ?? '');
    $experience = (int)($_POST['experience'] ?? 0);
    $bio        = trim($_POST['bio'] ?? '');

    if (empty($name) || empty($email) || empty($password)) {
        $msg = "Name, email and password are required.";
        $msg_type = 'error';
    } else {
        $chk = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $chk->bind_param('s', $email);
        $chk->execute();
        $exists = $chk->get_result();

        if ($exists && $exists->num_rows > 0) {
            $msg = "Email is already registered.";
            $msg_type = 'error';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins = $conn->prepare("INSERT INTO users (name, email, password_hash, role, created_at) VALUES (?, ?, ?, 'trainer', NOW())");
            if ($ins) {
                $ins->bind_param('sss', $name, $email, $hash);
                if ($ins->execute()) {
                    $tid = (int)$conn->insert_id;
                    $t_ins = $conn->prepare("INSERT INTO trainers (id, speciality, experience_years, bio) VALUES (?, ?, ?, ?)");
                    if ($t_ins) {
                        $t_ins->bind_param('isis', $tid, $speciality, $experience, $bio);
                        $t_ins->execute();
                        $t_ins->close();
                    }
                    $msg = "Trainer created successfully for " . htmlspecialchars($email);
                    $msg_type = 'success';
                } else {
                    $msg = "Error: " . htmlspecialchars($conn->error);
                    $msg_type = 'error';
                }
                $ins->close();
            } else {
                $msg = "Database error. Please try again.";
                $msg_type = 'error';
            }
        }
        $chk->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8"><title>Add Trainer — <?php echo $gym_name; ?></title>
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
  <div class="page-header"><h2>Add New <span class="accent">Trainer</span></h2></div>
  <?php if ($msg): ?>
    <div class="alert alert-<?php echo $msg_type==='success'?'success':'error'; ?>" style="max-width:560px;">
      <?php echo $msg_type==='success'?'✅':'⚠'; ?> <?php echo $msg; ?>
    </div>
  <?php endif; ?>
  <div class="form-card" style="max-width:600px;">
    <h3 style="margin-bottom:4px;">Trainer Account Details</h3>
    <p class="subtitle">Creates a user account + trainer profile in one step.</p>
    <form method="post">
      <?php echo csrf_field(); ?>
      <div class="form-group"><label>Full Name *</label><input type="text" name="name" required placeholder="Trainer Name"></div>
      <div class="form-group"><label>Email Address *</label><input type="email" name="email" required placeholder="trainer@example.com"></div>
      <div class="form-group">
        <label>Temporary Password *</label>
        <input type="text" name="password" required placeholder="Share securely with the trainer">
        <div style="font-size:11px;color:var(--text-3);margin-top:5px;">🔒 Password will be securely hashed with modern bcrypt encryption.</div>
      </div>
      <hr class="form-divider">
      <div class="form-group"><label>Speciality</label><input type="text" name="speciality" placeholder="e.g., Strength Training, Yoga, CrossFit"></div>
      <div class="form-group"><label>Experience (years)</label><input type="number" name="experience" min="0" value="0"></div>
      <div class="form-group"><label>Bio</label><textarea name="bio" placeholder="Short bio about the trainer..."></textarea></div>
      <div class="form-actions">
        <button class="btn" type="submit">Create Trainer Account</button>
        <a href="manage_trainers.php" class="btn btn-ghost">Cancel</a>
      </div>
    </form>
  </div>
</div>
</body>
</html>