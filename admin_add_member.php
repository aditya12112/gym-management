<?php
require 'config.php';
require 'auth.php';
require_login();
require_role('admin');

$msg = ''; $msg_type = 'success';
$gym_name = htmlspecialchars(get_setting('gym_name', 'GymPro'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    $age      = isset($_POST['age']) && $_POST['age'] !== '' ? (int)$_POST['age'] : null;
    $gender   = trim($_POST['gender'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');

    if ($name === '' || $email === '' || $password === '' || $confirm === '') {
        $msg = "Please fill in all required fields."; $msg_type = 'error';
    } elseif ($password !== $confirm) {
        $msg = "Passwords do not match."; $msg_type = 'error';
    } elseif (strlen($password) < 6) {
        $msg = "Password must be at least 6 characters."; $msg_type = 'error';
    } else {
        $chk = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $chk->bind_param('s', $email);
        $chk->execute();
        $dup = $chk->get_result();

        if ($dup && $dup->num_rows > 0) {
            $msg = "This email is already registered."; $msg_type = 'error';
        } else {
            $passHash = password_hash($password, PASSWORD_DEFAULT);
            $ins = $conn->prepare("INSERT INTO users (name, email, password_hash, role, age, gender, phone, created_at) VALUES (?, ?, ?, 'member', ?, ?, ?, NOW())");
            if ($ins) {
                $ins->bind_param('sssiss', $name, $email, $passHash, $age, $gender, $phone);
                if ($ins->execute()) {
                    $msg = "Member account created successfully for " . htmlspecialchars($email);
                    $msg_type = 'success';
                    $name = $email = $age = $gender = $phone = '';
                } else {
                    $msg = "Database error: " . htmlspecialchars($conn->error); $msg_type = 'error';
                }
                $ins->close();
            } else {
                $msg = "Database error. Please try again."; $msg_type = 'error';
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
  <title>Add Member — <?php echo $gym_name; ?></title>
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
    <h2>Add New <span class="accent">Member</span></h2>
  </div>

  <?php if ($msg): ?>
    <div class="alert alert-<?php echo $msg_type === 'success' ? 'success' : 'error'; ?>" style="max-width:560px;">
      <?php echo $msg_type === 'success' ? '✅' : '⚠'; ?> <?php echo $msg; ?>
    </div>
  <?php endif; ?>

  <div class="form-card">
    <h3 style="margin-bottom:4px;">Member Account Details</h3>
    <p class="subtitle">A temporary password will be set. Advise the member to change it after first login.</p>

    <form method="post">
      <?php echo csrf_field(); ?>
      <div class="form-group">
        <label>Full Name *</label>
        <input type="text" name="name" required value="<?php echo htmlspecialchars($name ?? ''); ?>" placeholder="John Doe">
      </div>
      <div class="form-group">
        <label>Email Address *</label>
        <input type="email" name="email" required value="<?php echo htmlspecialchars($email ?? ''); ?>" placeholder="john@example.com">
      </div>
      <div class="form-group">
        <label>Password *</label>
        <input type="password" name="password" required placeholder="Temporary password (min 6 chars)">
      </div>
      <div class="form-group">
        <label>Confirm Password *</label>
        <input type="password" name="confirm_password" required placeholder="Re-enter password">
      </div>

      <hr class="form-divider">

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
        <div class="form-group">
          <label>Age</label>
          <input type="number" name="age" min="1" max="120" value="<?php echo htmlspecialchars(isset($age) && $age !== null ? (string)$age : ''); ?>" placeholder="25">
        </div>
        <div class="form-group">
          <label>Gender</label>
          <select name="gender">
            <option value="">Select</option>
            <option value="Male"   <?php if(isset($gender)&&$gender==='Male') echo 'selected'; ?>>Male</option>
            <option value="Female" <?php if(isset($gender)&&$gender==='Female') echo 'selected'; ?>>Female</option>
            <option value="Other"  <?php if(isset($gender)&&$gender==='Other') echo 'selected'; ?>>Other</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label>Phone Number</label>
        <input type="text" name="phone" value="<?php echo htmlspecialchars($phone ?? ''); ?>" placeholder="+91 99999 00000">
      </div>

      <div class="form-actions">
        <button class="btn" type="submit">Create Member Account</button>
        <a href="manage_users.php" class="btn btn-ghost">Cancel</a>
      </div>
    </form>
  </div>
</div>

</body>
</html>