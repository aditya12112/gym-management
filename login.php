<?php
require 'config.php';
require 'auth.php';

$error = '';
$gym_name = htmlspecialchars(get_setting('gym_name', 'GymPro'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = "Session expired or invalid security token. Please try again.";
    } else {
        $email = trim($_POST['email'] ?? '');
        $pass  = $_POST['password'] ?? '';
        $selected_role = trim($_POST['login_role'] ?? '');

        if ($email === '' || $pass === '' || $selected_role === '') {
            $error = "Please choose a role and enter your credentials.";
        } else {
            $stmt = $conn->prepare("SELECT id, name, email, password_hash, role FROM users WHERE email = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $res = $stmt->get_result();

                if ($res && $res->num_rows === 1) {
                    $user = $res->fetch_assoc();
                    $stored_hash = $user['password_hash'];
                    $password_matched = false;

                    // 1. Verify using standard modern password_verify
                    if (password_verify($pass, $stored_hash)) {
                        $password_matched = true;
                    }
                    // 2. Backward compatibility: auto-upgrade legacy MD5 hash to bcrypt
                    elseif (strlen($stored_hash) === 32 && hash_equals($stored_hash, md5($pass))) {
                        $password_matched = true;
                        $new_hash = password_hash($pass, PASSWORD_DEFAULT);
                        $upd = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                        if ($upd) {
                            $upd->bind_param('si', $new_hash, $user['id']);
                            $upd->execute();
                            $upd->close();
                        }
                    }

                    if (!$password_matched) {
                        $error = "Incorrect password. Please try again.";
                    } elseif ($user['role'] !== $selected_role) {
                        $error = "Selected role doesn't match your account.";
                    } else {
                        // Prevent session fixation
                        session_regenerate_id(true);

                        $_SESSION['user_id'] = (int)$user['id'];
                        $_SESSION['name']    = $user['name'];
                        $_SESSION['role']    = $user['role'];

                        if ($user['role'] === 'admin')        header("Location: admin_dashboard.php");
                        elseif ($user['role'] === 'trainer')  header("Location: trainer_dashboard.php");
                        else                                  header("Location: member_dashboard.php");
                        exit;
                    }
                } else {
                    $error = "No account found with that email.";
                }
                $stmt->close();
            } else {
                $error = "A database error occurred. Please try again later.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <title>Login — <?php echo $gym_name; ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="assets/style.css?v=1.4">
  <script src="assets/theme.js?v=2.0"></script>
  <style>
    /* Hide native Edge password toggle */
    input::-ms-reveal,
    input::-ms-clear { display: none; }
    
    /* Show password toggle */
    .pw-wrap { position: relative; }
    .pw-wrap input { padding-right: 42px; }
    .pw-toggle {
      position: absolute; right: 12px; top: 50%;
      transform: translateY(-50%);
      background: none; border: none;
      color: var(--text-3); cursor: pointer;
      font-size: 16px; padding: 0;
      transition: color 0.2s;
    }
    .pw-toggle:hover { color: var(--accent); }
    .divider {
      display: flex; align-items: center; gap: 12px;
      color: var(--text-3); font-size: 12px; margin: 20px 0;
    }
    .divider::before, .divider::after {
      content: ''; flex: 1; height: 1px; background: var(--border);
    }
    .auth-footer {
      text-align: center; margin-top: 22px;
      font-size: 14px; color: var(--text-3);
    }
    .auth-footer a { color: var(--accent); font-weight: 600; }
  </style>
</head>
<body>
<div class="auth-page">
  <div class="auth-box">

    <div class="auth-logo">
      <div class="logo-mark">🏋️ <?php echo $gym_name; ?></div>
      <p>Sign in to your account</p>
    </div>

    <div class="auth-card">
      <h2>Welcome back</h2>
      <p class="auth-subtitle">Choose your role and enter your credentials</p>

      <?php if ($error): ?>
        <div class="alert alert-error" style="margin-bottom:16px;">
          ⚠ <?php echo htmlspecialchars($error); ?>
        </div>
      <?php endif; ?>

      <?php $selected_role = $_POST['login_role'] ?? 'member'; ?>

      <!-- Role Tabs -->
      <div class="role-tabs" id="roleTabs">
        <div class="role-tab <?php echo $selected_role==='member'?'active':''; ?>" data-role="member">👤 Member</div>
        <div class="role-tab <?php echo $selected_role==='trainer'?'active':''; ?>" data-role="trainer">🧑‍🏫 Trainer</div>
        <div class="role-tab <?php echo $selected_role==='admin'?'active':''; ?>" data-role="admin">👑 Admin</div>
      </div>

      <form method="post">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="login_role" id="loginRole" value="<?php echo htmlspecialchars($selected_role); ?>">

        <div class="form-group">
          <label>Email Address</label>
          <input type="email" name="email" placeholder="you@example.com" required
                 value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
        </div>

        <div class="form-group">
          <label>Password</label>
          <div class="pw-wrap">
            <input type="password" name="password" id="pw" placeholder="Enter your password" required>
            <button type="button" class="pw-toggle" onclick="togglePw()">👁</button>
          </div>
        </div>

        <button type="submit" class="btn" style="width:100%;justify-content:center;padding:13px;">
          Sign In →
        </button>
      </form>

      <div class="divider">or</div>

      <a href="forgot_password.php" class="btn btn-ghost" style="width:100%;justify-content:center;">
        🔑 Forgot Password? Get OTP
      </a>

    </div>

    <div class="auth-footer">
      New to <?php echo $gym_name; ?>? <a href="register.php">Create a member account</a>
    </div>
    <div class="auth-footer" style="margin-top:8px;">
      <a href="index.php" style="color:var(--text-3); font-size:13px;">← Back to homepage</a>
    </div>

  </div>
</div>

<script>
  document.querySelectorAll('.role-tab').forEach(tab => {
    tab.addEventListener('click', function() {
      document.querySelectorAll('.role-tab').forEach(t => t.classList.remove('active'));
      this.classList.add('active');
      document.getElementById('loginRole').value = this.dataset.role;
    });
  });

  function togglePw() {
    const pw = document.getElementById('pw');
    pw.type = pw.type === 'password' ? 'text' : 'password';
  }
</script>

</body>
</html>