<?php
// forgot_password.php — Secure OTP Password Recovery
require 'config.php';
require 'auth.php';
require_once 'mailer.php';

$msg = ""; $msg_type = "";
$gym_name = htmlspecialchars(get_setting('gym_name', 'GymPro'));

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!csrf_verify()) {
        $msg = "Session expired or invalid security token. Please try again.";
        $msg_type = "error";
    } else {
        $email = trim($_POST['email'] ?? '');

        $stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res && $res->num_rows === 1) {
                $user = $res->fetch_assoc();
                $otp = rand(100000, 999999);
                $_SESSION['reset_email']    = $email;
                $_SESSION['reset_otp']      = $otp;
                $_SESSION['reset_otp_time'] = time();

                $sent = mail_reset_otp($email, (string)$otp);
                if ($sent) {
                    header("Location: verify_otp.php");
                    exit;
                } else {
                    $msg = "Could not send OTP email. Please ensure SMTP is configured or contact your gym administrator.";
                    $msg_type = "error";
                }
            } else {
                $msg = "No account found with that email address.";
                $msg_type = "error";
            }
            $stmt->close();
        } else {
            $msg = "Database error. Please try again later.";
            $msg_type = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Forgot Password — <?php echo $gym_name; ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="assets/style.css">
  <script src="assets/theme.js"></script>
</head>
<body>
<div class="auth-page">
  <div class="auth-box">
    <div class="auth-logo">
      <div class="logo-mark">🏋️ <?php echo $gym_name; ?></div>
      <p>Password Recovery</p>
    </div>

    <div class="auth-card">
      <h2>Forgot Password?</h2>
      <p class="auth-subtitle">Enter your registered email and we'll send you a 6-digit OTP to reset your password.</p>

      <?php if ($msg): ?>
        <div class="alert alert-<?php echo $msg_type; ?>" style="margin-bottom:16px;">
          <?php echo htmlspecialchars($msg); ?>
        </div>
      <?php endif; ?>

      <form method="post">
        <?php echo csrf_field(); ?>
        <div class="form-group">
          <label>Email Address</label>
          <input type="email" name="email" required placeholder="you@example.com"
                 value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
        </div>
        <button class="btn" type="submit" style="width:100%;justify-content:center;padding:13px;">
          Send OTP →
        </button>
      </form>

      <div style="text-align:center;margin-top:20px;">
        <a href="login.php" style="color:var(--text-3);font-size:13px;">← Back to Login</a>
      </div>
    </div>
  </div>
</div>
</body>
</html>