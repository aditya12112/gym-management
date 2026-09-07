<?php
// register.php
require 'config.php';
require 'auth.php';
require_once 'mailer.php';

$msg = ''; $msg_type = '';
$gym_name = htmlspecialchars(get_setting('gym_name', 'GymPro'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    $age      = (int)($_POST['age'] ?? 0);
    $gender   = $_POST['gender'] ?? '';
    $phone    = trim($_POST['phone'] ?? '');

    if ($password !== $confirm) {
        $msg = "Passwords do not match."; $msg_type = 'error';
    } elseif (strlen($password) < 6) {
        $msg = "Password must be at least 6 characters."; $msg_type = 'error';
    } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = "Please enter a valid email address."; $msg_type = 'error';
    } else {
        $chk = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $chk->bind_param('s', $email);
        $chk->execute();
        $check = $chk->get_result();

        if ($check && $check->num_rows > 0) {
            $msg = "This email is already registered."; $msg_type = 'error';
        } else {
            // Generate 6-digit OTP and store form data in session
            $otp = rand(100000, 999999);
            $_SESSION['reg_otp']       = $otp;
            $_SESSION['reg_otp_time']  = time();
            $_SESSION['reg_data']      = compact('name','email','password','age','gender','phone');

            // Send OTP email
            $subject = "Your {$gym_name} Registration OTP";
            $digits = str_split((string)$otp);
            $digit_cells = '';
            foreach ($digits as $d) {
                $digit_cells .= "<td style='width:40px;height:50px;text-align:center;vertical-align:middle;font-size:26px;font-weight:900;color:#0f172a;background:#f8fafc;border:2px solid #e2e8f0;border-radius:8px;padding:0;'>$d</td><td style='width:8px;'></td>";
            }
            $body = "
              <h2 style='color:#0f172a;margin:0 0 8px;'>Verify Your Email</h2>
              <p style='color:#475569;margin:0 0 20px;font-size:15px;'>Hi <strong>" . htmlspecialchars($name) . "</strong>, use the 6-digit OTP below to complete your registration.</p>
              <table cellpadding='0' cellspacing='0' border='0' style='margin:0 auto 20px;'><tr>$digit_cells</tr></table>
              <div style='background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:12px 16px;margin-bottom:16px;'>
                <p style='margin:0;font-size:13px;color:#166534;text-align:center;'>This OTP expires in <strong>10 minutes</strong>. Do not share it with anyone.</p>
              </div>";

            $sent = send_gym_mail($email, $name, $subject, $body);

            if ($sent) {
                header("Location: verify_registration_otp.php");
                exit;
            } else {
                // If SMTP is not yet configured, provide a friendly message and bypass in development if needed
                $msg = "Could not dispatch verification email. Please ensure SMTP is configured in Admin Settings.";
                $msg_type = 'error';
            }
        }
        $chk->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Register — <?php echo $gym_name; ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="assets/style.css">
  <style>
    .pw-wrap { position:relative; }
    .pw-wrap input { padding-right:42px; }
    .pw-toggle { position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-3);cursor:pointer;font-size:16px;padding:0; }
    .pw-toggle:hover { color:var(--accent); }
    #match_msg { font-size:12px; margin-top:5px; height:16px; }
    .match-ok  { color:var(--success); }
    .match-err { color:var(--danger); }
    .two-col { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
  </style>
</head>
<body>
<div class="auth-page" style="align-items:flex-start;padding-top:40px;">
  <div class="auth-box" style="max-width:500px;">
    <div class="auth-logo">
      <div class="logo-mark">🏋️ <?php echo $gym_name; ?></div>
      <p>Create your member account</p>
    </div>

    <div class="auth-card">
      <h2>Join <?php echo $gym_name; ?></h2>
      <p class="auth-subtitle">Fill in your details to get started as a member.</p>

      <?php if ($msg): ?>
        <div class="alert alert-<?php echo $msg_type==='success'?'success':'error'; ?>" style="margin-bottom:16px;">
          <?php echo $msg_type==='success'?'✅':'⚠'; ?> <?php echo htmlspecialchars($msg); ?>
          <?php if ($msg_type==='success'): ?> <a href="login.php" style="margin-left:8px;font-weight:700;">Login →</a><?php endif; ?>
        </div>
      <?php endif; ?>

      <form method="post" id="regForm">
        <?php echo csrf_field(); ?>
        <div class="form-group">
          <label>Full Name *</label>
          <input type="text" name="name" required placeholder="John Doe" value="<?php echo htmlspecialchars($_POST['name']??''); ?>">
        </div>
        <div class="form-group">
          <label>Email Address *</label>
          <input type="email" name="email" required placeholder="you@example.com" value="<?php echo htmlspecialchars($_POST['email']??''); ?>">
        </div>

        <div class="form-group">
          <label>Password *</label>
          <div class="pw-wrap">
            <input type="password" id="pw1" name="password" required placeholder="Min 6 characters">
            <button type="button" class="pw-toggle" onclick="togglePw('pw1')">👁</button>
          </div>
        </div>
        <div class="form-group">
          <label>Confirm Password *</label>
          <div class="pw-wrap">
            <input type="password" id="pw2" name="confirm_password" required placeholder="Re-enter password" oninput="checkMatch()">
            <button type="button" class="pw-toggle" onclick="togglePw('pw2')">👁</button>
          </div>
          <div id="match_msg"></div>
        </div>

        <div class="two-col">
          <div class="form-group">
            <label>Age</label>
            <input type="number" name="age" min="1" max="120" placeholder="25" value="<?php echo htmlspecialchars($_POST['age']??''); ?>">
          </div>
          <div class="form-group">
            <label>Gender</label>
            <select name="gender">
              <option value="Male"   <?php if(($_POST['gender']??'')==='Male') echo 'selected'; ?>>Male</option>
              <option value="Female" <?php if(($_POST['gender']??'')==='Female') echo 'selected'; ?>>Female</option>
              <option value="Other"  <?php if(($_POST['gender']??'')==='Other') echo 'selected'; ?>>Other</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label>Phone</label>
          <input type="text" name="phone" placeholder="+91 99999 00000" value="<?php echo htmlspecialchars($_POST['phone']??''); ?>">
        </div>

        <button class="btn" id="regBtn" type="submit" style="width:100%;justify-content:center;padding:13px;">
          Create Account →
        </button>
      </form>
    </div>

    <div style="text-align:center;margin-top:16px;font-size:14px;color:var(--text-3);">
      Already have an account? <a href="login.php" style="color:var(--accent);font-weight:600;">Login</a>
    </div>
    <div style="text-align:center;margin-top:8px;">
      <a href="index.php" style="color:var(--text-3);font-size:13px;">← Back to homepage</a>
    </div>
  </div>
</div>
<script>
function checkMatch(){
  const pw=document.getElementById('pw1').value;
  const cf=document.getElementById('pw2').value;
  const msg=document.getElementById('match_msg');
  const btn=document.getElementById('regBtn');
  if(!cf){ msg.innerHTML=''; btn.disabled=false; return; }
  if(pw===cf){ msg.innerHTML='<span class="match-ok">✓ Passwords match</span>'; btn.disabled=false; }
  else { msg.innerHTML='<span class="match-err">✗ Passwords do not match</span>'; btn.disabled=true; }
}
function togglePw(id){
  const f=document.getElementById(id);
  f.type=f.type==='password'?'text':'password';
}
</script>
<script src="assets/theme.js"></script>
</body>
</html>