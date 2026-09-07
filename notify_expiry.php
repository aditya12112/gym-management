<?php
/**
 * notify_expiry.php — GymPro Subscription Expiry Reminder
 * ─────────────────────────────────────────────────────────
 * Run this script daily via Windows Task Scheduler or a cron job.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mailer.php';

$cron_secret = get_setting('cron_secret', 'gymcron2024');
$gym_name    = get_setting('gym_name', 'GymPro');

$is_cli     = (php_sapi_name() === 'cli');
$is_browser = !$is_cli;

if ($is_browser) {
    $given = trim($_GET['secret'] ?? '');
    if ($given === '' || !hash_equals($cron_secret, $given)) {
        http_response_code(403);
        die('403 Forbidden: Invalid secret key.');
    }
    header('Content-Type: text/plain; charset=utf-8');
}

// Days before expiry to send reminders
$remind_days = [5, 2, 1];

$today      = date('Y-m-d');
$sent_count = 0;
$skip_count = 0;

echo "{$gym_name} Expiry Notifier — $today\n";
echo str_repeat('─', 45) . "\n";

foreach ($remind_days as $days) {
    $target_date = date('Y-m-d', strtotime("+$days days"));

    $stmt = $conn->prepare("
        SELECT id, name, email, membership_end
        FROM users
        WHERE role = 'member'
          AND membership_status = 'active'
          AND membership_end = ?
          AND email IS NOT NULL
          AND email != ''
    ");

    if (!$stmt) {
        echo "  [DB ERROR] days=$days : " . $conn->error . "\n";
        continue;
    }

    $stmt->bind_param('s', $target_date);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($member = $result->fetch_assoc()) {
        $name      = $member['name'];
        $email     = $member['email'];
        $end_date  = $member['membership_end'];

        echo "  Sending {$days}d reminder → $name <$email> (expires $end_date) … ";

        $ok = mail_expiry_reminder($email, $name, $end_date, $days);

        if ($ok) {
            echo "✅ SENT\n";
            $sent_count++;
        } else {
            echo "❌ FAILED (Check SMTP settings)\n";
            $skip_count++;
        }
    }
    $stmt->close();
}

echo str_repeat('─', 45) . "\n";
echo "Done! Sent: $sent_count  Failed: $skip_count\n";
