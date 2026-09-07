<?php
// admin_settings.php — GymPro White-Label & System Settings
require 'config.php';
require 'auth.php';
require_login();
require_role('admin');
require_once 'mailer.php';

$msg = ''; $msg_type = 'success';

// Save Settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    require_csrf();

    $settings_to_update = [
        'gym_name'       => trim($_POST['gym_name'] ?? 'GymPro'),
        'gym_tagline'    => trim($_POST['gym_tagline'] ?? ''),
        'gym_currency'   => trim($_POST['gym_currency'] ?? '₹'),
        'gym_email'      => trim($_POST['gym_email'] ?? ''),
        'gym_phone'      => trim($_POST['gym_phone'] ?? ''),
        'cron_secret'    => trim($_POST['cron_secret'] ?? 'gymcron2024'),
        'mail_host'      => trim($_POST['mail_host'] ?? 'smtp.gmail.com'),
        'mail_port'      => trim($_POST['mail_port'] ?? '587'),
        'mail_user'      => trim($_POST['mail_user'] ?? ''),
        'mail_from'      => trim($_POST['mail_from'] ?? ''),
        'mail_from_name' => trim($_POST['mail_from_name'] ?? '')
    ];

    // Only update password if a new one was provided
    if (!empty($_POST['mail_pass'])) {
        $settings_to_update['mail_pass'] = trim($_POST['mail_pass']);
    }

    $all_ok = true;
    foreach ($settings_to_update as $k => $v) {
        if (!update_setting($k, $v)) {
            $all_ok = false;
        }
    }

    if ($all_ok) {
        $msg = "Settings updated successfully.";
        $msg_type = 'success';
    } else {
        $msg = "Error updating settings: " . htmlspecialchars($conn->error);
        $msg_type = 'error';
    }
}

// Send Test Email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_test_mail'])) {
    require_csrf();
    $test_email = trim($_POST['test_email'] ?? '');
    if (empty($test_email) || !filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
        $msg = "Please enter a valid recipient email for the test.";
        $msg_type = 'error';
    } else {
        $test_subject = "Test Email from " . get_setting('gym_name', 'GymPro');
        $test_body = "
            <h2 style='color:#0f172a;margin:0 0 10px;'>SMTP Configuration Working!</h2>
            <p style='color:#475569;font-size:15px;'>This test email confirms that your outgoing SMTP mail settings are properly configured and operational.</p>
            <div style='background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:14px;margin-top:16px;'>
                <p style='margin:0;font-size:13px;color:#166534;'>Sent on: " . date('Y-m-d H:i:s') . "</p>
            </div>
        ";
        $ok = send_gym_mail($test_email, 'Admin Test', $test_subject, $test_body);
        if ($ok) {
            $msg = "Test email sent successfully to " . htmlspecialchars($test_email);
            $msg_type = 'success';
        } else {
            $msg = "Failed to send test email. Please check your SMTP Host, Port, Username, and Password.";
            $msg_type = 'error';
        }
    }
}

$gym_name       = htmlspecialchars(get_setting('gym_name', 'GymPro'));
$gym_tagline    = htmlspecialchars(get_setting('gym_tagline', 'Smart Gym Management System'));
$gym_currency   = htmlspecialchars(get_setting('gym_currency', '₹'));
$gym_email      = htmlspecialchars(get_setting('gym_email', 'admin@gympro.com'));
$gym_phone      = htmlspecialchars(get_setting('gym_phone', '+91 98765 43210'));
$cron_secret    = htmlspecialchars(get_setting('cron_secret', 'gymcron2024'));

$mail_host      = htmlspecialchars(get_setting('mail_host', 'smtp.gmail.com'));
$mail_port      = htmlspecialchars(get_setting('mail_port', '587'));
$mail_user      = htmlspecialchars(get_setting('mail_user', ''));
$mail_from      = htmlspecialchars(get_setting('mail_from', ''));
$mail_from_name = htmlspecialchars(get_setting('mail_from_name', 'GymPro'));
$has_mail_pass  = get_setting('mail_pass', '') !== '';

$cron_url = APP_URL . "/notify_expiry.php?secret=" . urlencode(get_setting('cron_secret', 'gymcron2024'));
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <title>System Settings — <?php echo $gym_name; ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="assets/style.css?v=1.4">
  <script src="assets/theme.js?v=2.0"></script>
  <style>
    .settings-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
    @media (max-width: 900px) { .settings-grid { grid-template-columns: 1fr; } }
    .card-title { font-family: 'Barlow Condensed', sans-serif; font-size: 1.3rem; font-weight: 800; text-transform: uppercase; margin-bottom: 6px; }
    .code-box { background: var(--bg-3); border: 1px solid var(--border); padding: 12px 14px; border-radius: var(--radius); font-family: monospace; font-size: 12px; word-break: break-all; color: var(--accent); }
  </style>
</head>
<body>
<nav class="navbar">
  <div class="navbar-brand">🏋️ <?php echo $gym_name; ?></div>
  <div class="navbar-links">
    <a href="admin_dashboard.php">← Dashboard</a>
    <button class="theme-btn" id="themeBtn" title="Toggle theme" aria-label="Toggle dark/light mode">
      <span class="moon">🌙</span>
      <span class="sun">☀️</span>
    </button>
    <a href="logout.php" class="nav-logout">Logout</a>
  </div>
</nav>

<div class="container">
  <div class="page-header">
    <div>
      <p style="margin:0;font-size:13px;color:var(--text-3);">Admin Management</p>
      <h2>System &amp; <span class="accent">Gym Settings</span></h2>
    </div>
  </div>

  <?php if ($msg): ?>
    <div class="alert alert-<?php echo $msg_type === 'success' ? 'success' : 'error'; ?>" style="margin-bottom:24px;">
      <?php echo $msg_type === 'success' ? '✅' : '⚠'; ?> <?php echo $msg; ?>
    </div>
  <?php endif; ?>

  <form method="post">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="save_settings" value="1">

    <div class="settings-grid">
      <!-- General Gym Branding -->
      <div class="card">
        <div class="card-title">🏢 Gym Branding &amp; Localization</div>
        <p class="subtitle">Customize the business identity for client deployment.</p>

        <div class="form-group">
          <label>Gym / Business Name *</label>
          <input type="text" name="gym_name" required value="<?php echo $gym_name; ?>" placeholder="e.g. Iron Fitness Club">
        </div>

        <div class="form-group">
          <label>Tagline</label>
          <input type="text" name="gym_tagline" value="<?php echo $gym_tagline; ?>" placeholder="e.g. Train Hard, Stay Strong">
        </div>

        <div style="display:grid;grid-template-columns:120px 1fr;gap:14px;">
          <div class="form-group">
            <label>Currency Symbol *</label>
            <input type="text" name="gym_currency" required value="<?php echo $gym_currency; ?>" placeholder="₹, $, €, £">
          </div>
          <div class="form-group">
            <label>Contact Phone</label>
            <input type="text" name="gym_phone" value="<?php echo $gym_phone; ?>" placeholder="+91 98765 43210">
          </div>
        </div>

        <div class="form-group">
          <label>Official Contact Email</label>
          <input type="email" name="gym_email" value="<?php echo $gym_email; ?>" placeholder="contact@yourgym.com">
        </div>

        <div class="form-group">
          <label>Cron Secret Key (for Automated Expiry Reminders)</label>
          <input type="text" name="cron_secret" value="<?php echo $cron_secret; ?>" required>
          <div style="font-size:11px;color:var(--text-3);margin-top:4px;">Used to secure scheduled background notification jobs from unauthorized triggers.</div>
        </div>
      </div>

      <!-- Outgoing Email / SMTP Settings -->
      <div class="card">
        <div class="card-title">✉️ SMTP Email Server Settings</div>
        <p class="subtitle">Configure transactional emails for OTPs, bookings, and receipts.</p>

        <div style="display:grid;grid-template-columns:1fr 110px;gap:14px;">
          <div class="form-group">
            <label>SMTP Host</label>
            <input type="text" name="mail_host" value="<?php echo $mail_host; ?>" placeholder="smtp.gmail.com">
          </div>
          <div class="form-group">
            <label>SMTP Port</label>
            <input type="text" name="mail_port" value="<?php echo $mail_port; ?>" placeholder="587">
          </div>
        </div>

        <div class="form-group">
          <label>SMTP Username / Email</label>
          <input type="text" name="mail_user" value="<?php echo $mail_user; ?>" placeholder="notifications@yourgym.com">
        </div>

        <div class="form-group">
          <label>SMTP Password / App Password</label>
          <input type="password" name="mail_pass" placeholder="<?php echo $has_mail_pass ? '••••••••  (Leave blank to keep current)' : 'Enter SMTP password'; ?>">
          <div style="font-size:11px;color:var(--text-3);margin-top:4px;">For Gmail, generate a 16-character Google App Password with 2FA enabled.</div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
          <div class="form-group">
            <label>From Email Address</label>
            <input type="email" name="mail_from" value="<?php echo $mail_from; ?>" placeholder="noreply@yourgym.com">
          </div>
          <div class="form-group">
            <label>From Name</label>
            <input type="text" name="mail_from_name" value="<?php echo $mail_from_name; ?>" placeholder="GymPro">
          </div>
        </div>
      </div>
    </div>

    <div style="margin-top:24px;">
      <button class="btn" type="submit" style="padding:14px 28px;font-size:15px;">💾 Save System Settings</button>
    </div>
  </form>

  <hr style="border:0;border-top:1px solid var(--border);margin:36px 0;">

  <!-- Auxiliary Panels: Test Email & Automation Info -->
  <div class="settings-grid">
    <!-- Test Email Card -->
    <div class="card">
      <div class="card-title">🧪 Test SMTP Configuration</div>
      <p class="subtitle">Send a live test message to verify your outgoing mail settings.</p>
      <form method="post">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="send_test_mail" value="1">
        <div class="form-group">
          <label>Recipient Email</label>
          <input type="email" name="test_email" required placeholder="admin@example.com">
        </div>
        <button class="btn btn-outline" type="submit">Send Test Email →</button>
      </form>
    </div>

    <!-- Scheduled Tasks Card -->
    <div class="card">
      <div class="card-title">⏰ Scheduled Tasks &amp; Cron</div>
      <p class="subtitle">Automated daily membership expiry reminder setup.</p>
      <div style="margin-bottom:12px;">
        <label style="font-size:12px;color:var(--text-3);">Browser / Webhook Trigger URL:</label>
        <div class="code-box"><?php echo htmlspecialchars($cron_url); ?></div>
      </div>
      <div style="margin-bottom:12px;">
        <label style="font-size:12px;color:var(--text-3);">Live Hosting / cPanel Cron Job Command (Daily at 9:00 AM):</label>
        <div class="code-box">curl -s "<?php echo htmlspecialchars($cron_url); ?>" > /dev/null 2>&1</div>
      </div>
      <div>
        <label style="font-size:12px;color:var(--text-3);">Windows Task Scheduler CLI Command (Local):</label>
        <div class="code-box">C:\xampp\php\php.exe <?php echo htmlspecialchars(__DIR__ . '\notify_expiry.php'); ?></div>
      </div>
    </div>
  </div>

</div>

</body>
</html>
