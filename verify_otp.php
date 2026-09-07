<?php
// verify_otp.php
require 'config.php';
$msg = ''; $msg_type = '';

if (!isset($_SESSION['reset_email'], $_SESSION['reset_otp'], $_SESSION['reset_otp_time'])) {
    header("Location: forgot_password.php"); exit;
}

$OTP_EXPIRY = 300;
$remaining  = max(0, $OTP_EXPIRY - (time() - $_SESSION['reset_otp_time']));

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $otp = trim($_POST['otp']??'');
    if (time() - $_SESSION['reset_otp_time'] > $OTP_EXPIRY) {
        unset($_SESSION['reset_otp'], $_SESSION['reset_otp_time']);
        $msg = "OTP has expired. Please request a new one."; $msg_type='error';
    } elseif ($otp == $_SESSION['reset_otp']) {
        header("Location: reset_password.php"); exit;
    } else {
        $msg = "Incorrect OTP. Please try again."; $msg_type='error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Verify OTP — GymPro</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="assets/style.css">
  <style>
    .otp-input-group {
      display:flex; gap:10px; justify-content:center; margin:24px 0;
    }
    .otp-digit {
      width:52px; height:60px; text-align:center; font-size:1.6rem; font-weight:700;
      font-family:'Barlow Condensed',sans-serif;
      background:var(--bg-2); border:2px solid var(--border); border-radius:10px;
      color:var(--text-1); outline:none; caret-color:var(--accent);
      transition:border-color 0.2s, box-shadow 0.2s;
    }
    .otp-digit:focus { border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-glow); }
    .timer-ring {
      width:80px; height:80px; border-radius:50%; margin:0 auto 16px;
      border:3px solid var(--border); display:flex; flex-direction:column;
      align-items:center; justify-content:center; position:relative;
    }
    .timer-ring.urgent { border-color:var(--danger); }
    .timer-ring.ok { border-color:var(--accent); }
    .timer-num { font-family:'Barlow Condensed',sans-serif; font-size:1.6rem; font-weight:800; line-height:1; }
    .timer-label { font-size:9px; color:var(--text-3); letter-spacing:0.05em; }
    .email-chip {
      display:inline-flex; align-items:center; gap:6px; padding:5px 12px;
      background:var(--accent-glow); border:1px solid rgba(200,241,53,0.25);
      border-radius:999px; font-size:13px; font-weight:600; color:var(--accent);
      margin-bottom:20px;
    }
  </style>
</head>
<body>
<div class="auth-page">
  <div class="auth-box">
    <div class="auth-logo">
      <div class="logo-mark">🏋️ GYM<span>PRO</span></div>
      <p>OTP Verification</p>
    </div>

    <div class="auth-card" style="text-align:center;">
      <!-- Timer -->
      <?php if ($remaining > 0): ?>
      <div class="timer-ring <?php echo $remaining<=60?'urgent':'ok'; ?>" id="timerRing">
        <div class="timer-num" id="timerNum"><?php echo $remaining; ?></div>
        <div class="timer-label">seconds</div>
      </div>
      <p style="font-size:13px;color:var(--text-3);margin-bottom:16px;">OTP expires in <span id="timerText"><?php echo $remaining; ?></span>s</p>
      <?php else: ?>
      <div style="font-size:40px;margin-bottom:12px;">⏱️</div>
      <?php endif; ?>

      <h2 style="margin-bottom:6px;">Enter Your OTP</h2>

      <div class="email-chip">
        📧 <?php echo htmlspecialchars($_SESSION['reset_email']??''); ?>
      </div>

      <p style="font-size:13px;color:var(--text-3);">We sent a 6-digit code to your email. Enter it below.</p>

      <?php if ($msg): ?>
        <div class="alert alert-<?php echo $msg_type==='error'?'error':'success'; ?>" style="text-align:left;margin:16px 0;">
          <?php echo htmlspecialchars($msg); ?>
        </div>
      <?php endif; ?>

      <?php if ($remaining > 0): ?>
      <form method="post" id="otpForm">
        <!-- 6 individual digit inputs for UX, combined into hidden field -->
        <div class="otp-input-group">
          <?php for($i=1;$i<=6;$i++): ?>
            <input class="otp-digit" type="text" maxlength="1" id="d<?php echo $i; ?>" inputmode="numeric" pattern="[0-9]" autocomplete="one-time-code">
          <?php endfor; ?>
        </div>
        <input type="hidden" name="otp" id="otpHidden">
        <button class="btn" type="submit" style="width:100%;justify-content:center;padding:13px;" onclick="combineOtp()">
          Verify OTP →
        </button>
      </form>
      <?php else: ?>
        <div class="alert alert-error" style="text-align:left;margin:16px 0;">Your OTP has expired.</div>
        <a href="forgot_password.php" class="btn" style="width:100%;justify-content:center;">Request New OTP</a>
      <?php endif; ?>

      <div style="margin-top:20px;font-size:13px;">
        <a href="forgot_password.php" style="color:var(--text-3);">← Request a new OTP</a>
      </div>
    </div>
  </div>
</div>

<script>
// OTP digit navigation
const digits = document.querySelectorAll('.otp-digit');
digits.forEach((d, i) => {
  d.addEventListener('input', (e) => {
    if (d.value.length === 1 && i < digits.length - 1) digits[i+1].focus();
  });
  d.addEventListener('keydown', (e) => {
    if (e.key === 'Backspace' && d.value === '' && i > 0) digits[i-1].focus();
  });
  d.addEventListener('paste', (e) => {
    e.preventDefault();
    const paste = (e.clipboardData||window.clipboardData).getData('text').replace(/\D/g,'').slice(0,6);
    paste.split('').forEach((ch, j) => { if (digits[j]) digits[j].value = ch; });
    if (digits[Math.min(paste.length, 5)]) digits[Math.min(paste.length, 5)].focus();
  });
});

function combineOtp() {
  let val = '';
  digits.forEach(d => val += d.value);
  document.getElementById('otpHidden').value = val;
}

// Countdown
let timeLeft = <?php echo (int)$remaining; ?>;
const numEl  = document.getElementById('timerNum');
const txtEl  = document.getElementById('timerText');
const ringEl = document.getElementById('timerRing');

if (numEl) {
  const tick = setInterval(() => {
    timeLeft = Math.max(0, timeLeft - 1);
    numEl.textContent = timeLeft;
    if (txtEl) txtEl.textContent = timeLeft;
    if (ringEl) {
      if (timeLeft <= 60) { ringEl.classList.add('urgent'); ringEl.classList.remove('ok'); }
    }
    if (timeLeft === 0) {
      clearInterval(tick);
      document.getElementById('otpForm')?.remove();
      document.querySelector('.auth-card').insertAdjacentHTML('beforeend', '<div class="alert alert-error" style="text-align:left;margin-top:16px;">OTP expired. <a href="forgot_password.php">Request a new one →</a></div>');
    }
  }, 1000);
}
</script>
<script src="assets/theme.js"></script>
</body>
</html>