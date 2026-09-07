<?php
// reset_password.php
require 'config.php';
require 'auth.php';

$msg = ''; $msg_type = '';
$gym_name = htmlspecialchars(get_setting('gym_name', 'GymPro'));

if (!isset($_SESSION['reset_email'])) {
    header("Location: forgot_password.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require_csrf();
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if ($password === '' || $confirm === '') {
        $msg = "All fields are required."; $msg_type = 'error';
    } elseif ($password !== $confirm) {
        $msg = "Passwords do not match."; $msg_type = 'error';
    } elseif (strlen($password) < 6) {
        $msg = "Password must be at least 6 characters."; $msg_type = 'error';
    } else {
        $email = $_SESSION['reset_email'];
        $hash  = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
        if ($stmt) {
            $stmt->bind_param('ss', $hash, $email);
            if ($stmt->execute()) {
                unset($_SESSION['reset_email'], $_SESSION['reset_otp'], $_SESSION['reset_otp_time']);
                $msg = "Password updated securely! Redirecting to login...";
                $msg_type = 'success';
                header("refresh:2;url=login.php");
            } else {
                $msg = "Error updating password.";
                $msg_type = 'error';
            }
            $stmt->close();
        } else {
            $msg = "Database error. Please try again.";
            $msg_type = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><title>Reset Password — <?php echo $gym_name; ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="assets/style.css">
  <style>
    .pw-wrap{position:relative;} .pw-wrap input{padding-right:42px;}
    .pw-toggle{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-3);cursor:pointer;font-size:16px;}
    .strength{height:4px;border-radius:2px;margin-top:6px;transition:all 0.3s;background:var(--border);}
    .strength-bar{height:100%;border-radius:2px;width:0;transition:width 0.3s,background 0.3s;}
  </style>
</head>
<body>
<div class="auth-page">
  <div class="auth-box">
    <div class="auth-logo">
      <div class="logo-mark">🏋️ <?php echo $gym_name; ?></div>
      <p>Set a new password</p>
    </div>
    <div class="auth-card">
      <h2>Reset Password</h2>
      <p class="auth-subtitle">Choose a strong password of at least 6 characters.</p>
      <?php if ($msg): ?>
        <div class="alert alert-<?php echo $msg_type==='success'?'success':'error'; ?>" style="margin-bottom:16px;">
          <?php echo $msg_type==='success'?'✅':'⚠'; ?> <?php echo htmlspecialchars($msg); ?>
        </div>
      <?php endif; ?>
      <?php if ($msg_type !== 'success'): ?>
      <form method="post">
        <?php echo csrf_field(); ?>
        <div class="form-group">
          <label>New Password</label>
          <div class="pw-wrap">
            <input type="password" id="pw1" name="password" required oninput="checkStrength()" placeholder="Min 6 characters">
            <button type="button" class="pw-toggle" onclick="togglePw('pw1')">👁</button>
          </div>
          <div class="strength"><div class="strength-bar" id="sBar"></div></div>
          <div id="sLabel" style="font-size:11px;color:var(--text-3);margin-top:4px;"></div>
        </div>
        <div class="form-group">
          <label>Confirm New Password</label>
          <div class="pw-wrap">
            <input type="password" id="pw2" name="confirm_password" required placeholder="Re-enter password">
            <button type="button" class="pw-toggle" onclick="togglePw('pw2')">👁</button>
          </div>
        </div>
        <button class="btn" type="submit" style="width:100%;justify-content:center;padding:13px;">Update Password →</button>
      </form>
      <?php endif; ?>
    </div>
  </div>
</div>
<script>
function togglePw(id){ const f=document.getElementById(id); f.type=f.type==='password'?'text':'password'; }
function checkStrength(){
  const pw=document.getElementById('pw1').value;
  const bar=document.getElementById('sBar'); const lbl=document.getElementById('sLabel');
  let score=0;
  if(pw.length>=6) score++; if(pw.length>=10) score++;
  if(/[A-Z]/.test(pw)) score++; if(/[0-9]/.test(pw)) score++; if(/[^A-Za-z0-9]/.test(pw)) score++;
  const levels=[{w:'0%',c:'var(--border)',t:''},{w:'25%',c:'var(--danger)',t:'Weak'},{w:'50%',c:'var(--warning)',t:'Fair'},{w:'75%',c:'var(--info)',t:'Good'},{w:'100%',c:'var(--success)',t:'Strong'}];
  const l=levels[Math.min(score,4)];
  bar.style.width=l.w; bar.style.background=l.c; lbl.textContent=l.t; lbl.style.color=l.c;
}
</script>
<script src="assets/theme.js"></script>
</body>
</html>