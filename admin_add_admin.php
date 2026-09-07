<?php
// admin_add_admin.php
require 'config.php';
require 'auth.php';
require_login();
require_role('admin');

$msg = ''; $msg_type = '';
$gym_name = htmlspecialchars(get_setting('gym_name', 'GymPro'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($name) || empty($email) || empty($password)) {
        $msg = "Please fill all required fields.";
        $msg_type = 'error';
    } else {
        $chk = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $chk->bind_param('s', $email);
        $chk->execute();
        $exists = $chk->get_result();

        if ($exists && $exists->num_rows > 0) {
            $msg = "This email is already registered.";
            $msg_type = 'error';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins = $conn->prepare("INSERT INTO users (name, email, password_hash, role, created_at) VALUES (?, ?, ?, 'admin', NOW())");
            if ($ins) {
                $ins->bind_param('sss', $name, $email, $hash);
                if ($ins->execute()) {
                    $msg = "Admin account created successfully for " . htmlspecialchars($email);
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
  <meta charset="UTF-8">
  <title>Add Admin — <?php echo $gym_name; ?></title>
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
  <div class="page-header"><h2>Add New <span class="accent">Admin</span></h2></div>
  <?php if ($msg): ?>
    <div class="alert alert-<?php echo $msg_type==='success'?'success':'error'; ?>" style="max-width:520px;">
      <?php echo $msg_type==='success'?'✅':'⚠'; ?> <?php echo $msg; ?>
    </div>
  <?php endif; ?>
  <div class="form-card">
    <h3 style="margin-bottom:4px;">Admin Account Details</h3>
    <p class="subtitle">This user will have full administrative access to <?php echo $gym_name; ?>.</p>
    <form method="post">
      <?php echo csrf_field(); ?>
      <div class="form-group"><label>Full Name *</label><input type="text" name="name" required placeholder="Admin Name"></div>
      <div class="form-group"><label>Email Address *</label><input type="email" name="email" required placeholder="admin@example.com"></div>
      <div class="form-group">
        <label>Temporary Password *</label>
        <input type="text" name="password" required placeholder="They should change this after login">
        <div style="font-size:11px;color:var(--text-3);margin-top:5px;">🔒 Password will be securely hashed with modern bcrypt encryption.</div>
      </div>
      <div class="form-actions">
        <button class="btn" type="submit">Create Admin Account</button>
        <a href="manage_admins.php" class="btn btn-ghost">Cancel</a>
      </div>
    </form>
  </div>
</div>
</body>
</html>