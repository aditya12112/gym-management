<?php
// verify_registration_otp.php
require 'config.php';
require 'auth.php';
require_once 'mailer.php';

// Redirect if no pending registration
if (empty($_SESSION['reg_otp']) || empty($_SESSION['reg_data'])) {
    header("Location: register.php"); exit;
}

$msg = ''; $msg_type = '';
$reg = $_SESSION['reg_data'];
$gym_name = htmlspecialchars(get_setting('gym_name', 'GymPro'));

/* ── Resend OTP ── */
if (isset($_POST['resend'])) {
    require_csrf();
    $otp = rand(100000, 999999);
    $_SESSION['reg_otp']      = $otp;
    $_SESSION['reg_otp_time'] = time();
    $name  = $reg['name'];
    $email = $reg['email'];
    $body  = "
      <h2 style='color:#0f172a;margin:0 0 6px;'>New OTP - {$gym_name} Registration</h2>
      <p style='color:#475569;margin:0 0 24px;'>Hi <strong>" . htmlspecialchars($name) . "</strong>, here is your new OTP:</p>
      <div style='font-size:40px;font-weight:900;letter-spacing:10px;padding:24px;background:#f8fafc;border-radius:8px;text-align:center;color:#0f172a;border:1px solid #e2e8f0;'>$otp</div>
      <p style='color:#64748b;margin-top:20px;font-size:13px;'>Expires in <strong>10 minutes</strong>.</p>";
    send_gym_mail($email, $name, "Your New {$gym_name} Registration OTP", $body);
    $msg = "A new OTP has been sent to your email."; $msg_type = 'success';
}

/* ── Verify OTP ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['otp'])) {
    require_csrf();
    $entered  = trim($_POST['otp'] ?? '');
    $stored   = (string)($_SESSION['reg_otp'] ?? '');
    $otp_time = (int)($_SESSION['reg_otp_time'] ?? 0);

    if ($entered === '') {
        $msg = "Please enter the OTP."; $msg_type = 'error';
    } elseif (time() - $otp_time > 600) {
        $msg = "OTP has expired. Please request a new one."; $msg_type = 'error';
    } elseif ($entered !== $stored) {
        $msg = "Incorrect OTP. Please try again."; $msg_type = 'error';
    } else {
        // OTP correct — create the account
        $name     = $reg['name'];
        $email    = $reg['email'];
        $password = $reg['password'];
        $age      = !empty($reg['age']) ? (int)$reg['age'] : null;
        $gender   = $reg['gender'] ?? '';
        $phone    = $reg['phone'] ?? '';
        $passHash = password_hash($password, PASSWORD_DEFAULT);

        // Double-check email not registered in the meantime
        $chk = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $chk->bind_param('s', $email);
        $chk->execute();
        $chk_res = $chk->get_result();

        if ($chk_res && $chk_res->num_rows > 0) {
            $msg = "This email was registered while you were verifying. Please log in."; $msg_type = 'error';
            unset($_SESSION['reg_otp'], $_SESSION['reg_otp_time'], $_SESSION['reg_data']);
        } else {
            $ins = $conn->prepare("INSERT INTO users (name, email, password_hash, role, age, gender, phone, created_at) VALUES (?, ?, ?, 'member', ?, ?, ?, NOW())");
            if ($ins) {
                $ins->bind_param('sssiss', $name, $email, $passHash, $age, $gender, $phone);
                if ($ins->execute()) {
                    unset($_SESSION['reg_otp'], $_SESSION['reg_otp_time'], $_SESSION['reg_data']);
                    mail_welcome($email, $name);
                    header("Location: login.php?registered=1"); exit;
                } else {
                    $msg = "Account creation failed: " . htmlspecialchars($conn->error); $msg_type = 'error';
                }
                $ins->close();
            } else {
                $msg = "Database error. Please try again."; $msg_type = 'error';
            }
        }
        $chk->close();
    }
}

$masked_email = '';
if (!empty($reg['email'])) {
    $parts = explode('@', $reg['email']);
    $masked_email = substr($parts[0], 0, 2) . str_repeat('*', max(0, strlen($parts[0]) - 2)) . '@' . $parts[1];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Verify Email — <?php echo $gym_name; ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="assets/style.css">
  <style>
    .otp-inputs { display:flex; gap:10px; justify-content:center; margin:20px 0; }
    .otp-inputs input[type=text] {
      width:52px; height:60px; text-align:center; font-size:1.6rem; font-weight:800;
      border:2px solid var(--border); border-radius:var(--radius); background:var(--bg-3);
      color:var(--text-1); transition:border-color .2s;
    }
    .otp-inputs input[type=text]:focus { border-color:var(--accent); outline:none; }
    .timer { font-size:13px; color:var(--text-3); text-align:center; margin-bottom:16px; }
    .timer span { color:var(--accent); font-weight:700; }
  </style>
</head>
<body>
<div class="auth-page">
  <div class="auth-box">
    <div class="auth-logo">
      <div class="logo-mark">🏋️ <?php echo $gym_name; ?></div>
      <p>Email Verification</p>
    </div>

    <div class="auth-card">
      <div style="text-align:center;margin-bottom:20px;">
        <div style="font-size:48px;">📧</div>
        <h2 style="margin:8px 0 4px;">Check Your Email</h2>
        <p class="auth-subtitle">We sent a 6-digit OTP to<br><strong><?php echo htmlspecialchars($masked_email); ?></strong></p>
      </div>

      <?php if ($msg): ?>
        <div class="alert alert-<?php echo $msg_type==='success'?'success':'error'; ?>" style="margin-bottom:16px;">
          <?php echo $msg_type==='success'?'✅':'⚠'; ?> <?php echo htmlspecialchars($msg); ?>
        </div>
      <?php endif; ?>

      <form method="post" id="otpForm">
        <?php echo csrf_field(); ?>
        <div class="otp-inputs" id="otpBoxes">
          <input type="text" maxlength="1" class="otp-box" inputmode="numeric" pattern="[0-9]" autocomplete="off">
          <input type="text" maxlength="1" class="otp-box" inputmode="numeric" pattern="[0-9]" autocomplete="off">
          <input type="text" maxlength="1" class="otp-box" inputmode="numeric" pattern="[0-9]" autocomplete="off">
          <input type="text" maxlength="1" class="otp-box" inputmode="numeric" pattern="[0-9]" autocomplete="off">
          <input type="text" maxlength="1" class="otp-box" inputmode="numeric" pattern="[0-9]" autocomplete="off">
          <input type="text" maxlength="1" class="otp-box" inputmode="numeric" pattern="[0-9]" autocomplete="off">
        </div>
        <input type="hidden" name="otp" id="otpHidden">

        <div class="timer" id="timerBlock">OTP expires in <span id="countdown">10:00</span></div>

        <button class="btn" type="submit" style="width:100%;justify-content:center;padding:13px;font-size:15px;">
          Verify & Create Account →
        </button>
      </form>

      <form method="post" style="margin-top:14px;text-align:center;">
        <?php echo csrf_field(); ?>
        <button name="resend" value="1" class="btn btn-outline btn-sm" type="submit" id="resendBtn" disabled>
          Resend OTP
        </button>
        <div style="font-size:12px;color:var(--text-3);margin-top:6px;" id="resendHint">
          Resend available after countdown ends.
        </div>
      </form>

      <div style="text-align:center;margin-top:18px;">
        <a href="register.php" style="color:var(--text-3);font-size:13px;">← Change email / Go back</a>
      </div>
    </div>
  </div>
</div>

<script>
// Auto-advance OTP boxes and merge into hidden input
const boxes = document.querySelectorAll('.otp-box');
boxes.forEach((box, i) => {
  box.addEventListener('input', () => {
    box.value = box.value.replace(/\D/g, '');
    if (box.value && i < boxes.length - 1) boxes[i+1].focus();
    syncOtp();
  });
  box.addEventListener('keydown', e => {
    if (e.key === 'Backspace' && !box.value && i > 0) {
      boxes[i-1].focus();
    }
  });
  box.addEventListener('paste', e => {
    e.preventDefault();
    const text = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g,'');
    [...text.slice(0, 6)].forEach((ch, j) => { if (boxes[j]) boxes[j].value = ch; });
    if (boxes[Math.min(text.length, 5)]) boxes[Math.min(text.length, 5)].focus();
    syncOtp();
  });
});
function syncOtp() {
  document.getElementById('otpHidden').value = [...boxes].map(b => b.value).join('');
}
boxes[0].focus();

// Countdown timer (10 min)
let secs = 600;
const cd = document.getElementById('countdown');
const resendBtn = document.getElementById('resendBtn');
const resendHint = document.getElementById('resendHint');
const timer = setInterval(() => {
  secs--;
  const m = Math.floor(secs/60).toString().padStart(2,'0');
  const s = (secs%60).toString().padStart(2,'0');
  cd.textContent = m+':'+s;
  if (secs <= 0) {
    clearInterval(timer);
    cd.textContent = 'Expired';
    resendBtn.disabled = false;
    resendHint.textContent = '';
  }
}, 1000);
</script>
<script src="assets/theme.js"></script>
</body>
</html>
