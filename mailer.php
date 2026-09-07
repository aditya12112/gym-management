<?php
// mailer.php — Central PHPMailer helper for GymPro (Environment & Settings Driven)

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';

// Dynamically determine the base URL so emails work automatically on AWS, VPS, or localhost
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
if (!defined('APP_URL')) {
    define('APP_URL', $protocol . '://' . $host . $path);
}

/**
 * Send an HTML email using dynamically configured SMTP credentials.
 */
function send_gym_mail(string $to_email, string $to_name, string $subject, string $html_body): bool {
    $mail_host = get_setting('mail_host', 'smtp.gmail.com');
    $mail_port = (int)get_setting('mail_port', 587);
    $mail_user = get_setting('mail_user', '');
    $mail_pass = get_setting('mail_pass', '');
    $mail_from = get_setting('mail_from', '') ?: $mail_user;
    $gym_name  = get_setting('gym_name', 'GymPro');
    $mail_name = get_setting('mail_from_name', '') ?: $gym_name;

    // Gracefully handle unconfigured mail settings
    if (empty($mail_user) || empty($mail_pass)) {
        error_log("GymPro Mailer Info: SMTP credentials not set in Admin Settings. Email to '{$to_email}' was skipped.");
        return false;
    }

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $mail_host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $mail_user;
        $mail->Password   = $mail_pass;
        $mail->SMTPSecure = ($mail_port == 465) ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $mail_port;
        $mail->CharSet    = 'UTF-8';
        $mail->Encoding   = 'base64';

        $mail->setFrom($mail_from ?: $mail_user, $mail_name);
        $mail->addAddress($to_email, $to_name ?: $to_email);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = gym_email_wrap($html_body);
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("GymPro Mailer error: " . $e->getMessage());
        return false;
    }
}

/**
 * Wrap content in branded email template.
 */
function gym_email_wrap(string $content): string {
    $gym_name = htmlspecialchars(get_setting('gym_name', 'GymPro'));
    return "
    <div style='font-family:Arial,sans-serif;background:#f0f2f5;padding:32px 16px;'>
      <div style='max-width:520px;margin:0 auto;'>
        <!-- Header -->
        <div style='background:#0f172a;border-radius:12px 12px 0 0;padding:24px 32px;text-align:center;'>
          <span style='font-family:Georgia,serif;font-size:24px;font-weight:900;letter-spacing:1px;color:#ffffff;'>
            🏋️ " . strtoupper($gym_name) . "
          </span>
        </div>
        <!-- Body -->
        <div style='background:#ffffff;padding:32px;border-radius:0 0 12px 12px;box-shadow:0 4px 20px rgba(0,0,0,0.08);'>
          $content
        </div>
        <!-- Footer -->
        <div style='text-align:center;margin-top:20px;font-size:12px;color:#94a3b8;'>
          This is an automated message from {$gym_name}. Please do not reply to this email.
        </div>
      </div>
    </div>";
}

/**
 * Pre-built: Password Reset OTP email
 */
function mail_reset_otp(string $email, string $otp): bool {
    $gym_name = get_setting('gym_name', 'GymPro');
    $subject  = "Your {$gym_name} Password Reset OTP";
    $body = "
      <h2 style='color:#0f172a;margin:0 0 6px;'>Password Reset Request</h2>
      <p style='color:#475569;margin:0 0 20px;'>You requested to reset your password. Use the OTP below to proceed:</p>
      <div style='font-size:38px;font-weight:900;letter-spacing:8px;padding:20px;background:#f8fafc;border-radius:8px;text-align:center;color:#0f172a;border:1px solid #e2e8f0;margin-bottom:20px;'>$otp</div>
      <p style='color:#64748b;font-size:13px;'>This OTP expires in <strong>5 minutes</strong>. If you did not request this, please ignore this email.</p>";
    return send_gym_mail($email, 'Member', $subject, $body);
}

/**
 * Pre-built: Payment Confirmed email
 */
function mail_payment_confirmed(string $email, string $name, string $plan, float $amount, string $pay_date, string $end_date): bool {
    $gym_name = get_setting('gym_name', 'GymPro');
    $currency = get_setting('gym_currency', '₹');
    $subject = "Payment Confirmed - {$gym_name} Membership";
    $body = "
      <h2 style='color:#0f172a;margin:0 0 6px;'>Payment Confirmed!</h2>
      <p style='color:#475569;margin:0 0 24px;'>Hi <strong>" . htmlspecialchars($name) . "</strong>, your membership payment has been approved.</p>
      <div style='background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:20px;margin-bottom:24px;'>
        <table style='width:100%;border-collapse:collapse;font-size:14px;'>
          <tr><td style='padding:8px 0;color:#64748b;'>Plan</td>         <td style='padding:8px 0;font-weight:700;color:#0f172a;text-align:right;'>" . htmlspecialchars($plan) . "</td></tr>
          <tr style='border-top:1px solid #e2e8f0;'><td style='padding:8px 0;color:#64748b;'>Amount Paid</td>    <td style='padding:8px 0;font-weight:700;color:#0f172a;text-align:right;'>{$currency}" . number_format($amount, 0) . "</td></tr>
          <tr style='border-top:1px solid #e2e8f0;'><td style='padding:8px 0;color:#64748b;'>Payment Date</td>  <td style='padding:8px 0;color:#0f172a;text-align:right;'>" . htmlspecialchars($pay_date) . "</td></tr>
          <tr style='border-top:1px solid #e2e8f0;'><td style='padding:8px 0;color:#64748b;'>Valid Until</td>   <td style='padding:8px 0;font-weight:700;color:#22c55e;text-align:right;'>" . htmlspecialchars($end_date) . "</td></tr>
        </table>
      </div>
      <div style='background:#c8f135;border-radius:8px;padding:14px 20px;text-align:center;margin-bottom:24px;'>
        <span style='font-size:15px;font-weight:700;color:#0a0a0a;'>🎉 Your membership is now ACTIVE — Keep crushing it!</span>
      </div>
      <p style='color:#64748b;font-size:13px;'>Log in to your dashboard to view your plans and book slots.</p>";
    return send_gym_mail($email, $name, $subject, $body);
}

/**
 * Pre-built: Payment Rejected email
 */
function mail_payment_rejected(string $email, string $name, string $plan, float $amount): bool {
    $gym_name = get_setting('gym_name', 'GymPro');
    $currency = get_setting('gym_currency', '₹');
    $subject = "Payment Not Approved - {$gym_name}";
    $body = "
      <h2 style='color:#0f172a;margin:0 0 6px;'>Payment Not Approved</h2>
      <p style='color:#475569;margin:0 0 24px;'>Hi <strong>" . htmlspecialchars($name) . "</strong>, unfortunately your payment could not be approved.</p>
      <div style='background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:20px;margin-bottom:24px;'>
        <table style='width:100%;border-collapse:collapse;font-size:14px;'>
          <tr><td style='padding:8px 0;color:#64748b;'>Plan</td>    <td style='padding:8px 0;font-weight:700;text-align:right;'>" . htmlspecialchars($plan) . "</td></tr>
          <tr style='border-top:1px solid #fecaca;'><td style='padding:8px 0;color:#64748b;'>Amount</td>  <td style='padding:8px 0;font-weight:700;text-align:right;'>{$currency}" . number_format($amount, 0) . "</td></tr>
        </table>
      </div>
      <p style='color:#475569;'>Please contact the gym or re-submit your payment with the correct details.</p>";
    return send_gym_mail($email, $name, $subject, $body);
}

/**
 * Pre-built: Subscription Expiry Reminder email
 */
function mail_expiry_reminder(string $email, string $name, string $end_date, int $days_left): bool {
    $gym_name = get_setting('gym_name', 'GymPro');
    $subject = "Reminder: Your {$gym_name} Membership Expires in $days_left Day(s)";
    $urgency_color = $days_left <= 2 ? '#ef4444' : '#f59e0b';
    $body = "
      <h2 style='color:#0f172a;margin:0 0 6px;'>Membership Expiring Soon!</h2>
      <p style='color:#475569;margin:0 0 24px;'>Hi <strong>" . htmlspecialchars($name) . "</strong>, your {$gym_name} membership is expiring soon.</p>
      <div style='background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:20px;margin-bottom:24px;text-align:center;'>
        <div style='font-size:40px;margin-bottom:8px;'>⏳</div>
        <div style='font-size:28px;font-weight:900;color:$urgency_color;'>$days_left Day(s) Left</div>
        <div style='color:#64748b;margin-top:6px;font-size:14px;'>Expires on: <strong>" . htmlspecialchars($end_date) . "</strong></div>
      </div>
      <p style='color:#475569;margin-bottom:20px;'>Don't lose access to your workouts, trainer plans, and slot bookings. Renew now to stay on track!</p>
      <div style='text-align:center;margin-bottom:16px;'>
        <a href='" . APP_URL . "/payments.php'
           style='display:inline-block;background:#c8f135;color:#0a0a0a;padding:14px 32px;border-radius:8px;font-weight:700;font-size:15px;text-decoration:none;'>
          🔄 Renew Membership Now
        </a>
      </div>
      <p style='color:#94a3b8;font-size:12px;text-align:center;'>After renewing, your new membership period starts from where this one ends.</p>";
    return send_gym_mail($email, $name, $subject, $body);
}

/**
 * Pre-built: Registration Welcome email
 */
function mail_welcome(string $email, string $name): bool {
    $gym_name = get_setting('gym_name', 'GymPro');
    $subject = "Welcome to {$gym_name} - Your Account is Ready!";
    $body = "
      <h2 style='color:#0f172a;margin:0 0 8px;'>Welcome to {$gym_name}!</h2>
      <p style='color:#475569;margin:0 0 20px;font-size:15px;'>Hi <strong>" . htmlspecialchars($name) . "</strong>, your account has been created successfully. We're thrilled to have you on board!</p>
      <div style='background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:16px;margin-bottom:20px;'>
        <div style='border-bottom:1px solid #bbf7d0;padding-bottom:10px;margin-bottom:10px;'>
          <div style='font-size:12px;color:#64748b;margin-bottom:2px;'>Name</div>
          <div style='font-size:15px;font-weight:700;color:#0f172a;'>" . htmlspecialchars($name) . "</div>
        </div>
        <div>
          <div style='font-size:12px;color:#64748b;margin-bottom:2px;'>Email</div>
          <div style='font-size:14px;color:#0f172a;word-break:break-all;'>" . htmlspecialchars($email) . "</div>
        </div>
      </div>
      <div style='background:#c8f135;border-radius:8px;padding:14px 20px;text-align:center;margin-bottom:24px;'>
        <span style='font-size:15px;font-weight:700;color:#0a0a0a;'>You're all set! Start your fitness journey today.</span>
      </div>
      <div style='text-align:center;margin-top:24px;'>
        <a href='" . APP_URL . "/login.php'
           style='display:inline-block;background:#c8f135;color:#0a0a0a;padding:14px 32px;border-radius:8px;font-weight:700;font-size:15px;text-decoration:none;'>
          Login to Dashboard
        </a>
      </div>";
    return send_gym_mail($email, $name, $subject, $body);
}

/**
 * Pre-built: Plan Assigned email (diet or exercise)
 */
function mail_plan_assigned(string $email, string $name, string $plan_type, string $plan_text, string $trainer_name): bool {
    $gym_name   = get_setting('gym_name', 'GymPro');
    $is_diet    = strtolower($plan_type) === 'diet';
    $icon       = $is_diet ? '🥗' : '💪';
    $label      = $is_diet ? 'Diet Plan' : 'Exercise Plan';
    $color      = $is_diet ? '#22c55e' : '#3b82f6';
    $bg         = $is_diet ? '#f0fdf4' : '#eff6ff';
    $border     = $is_diet ? '#bbf7d0' : '#bfdbfe';
    $subject    = "New $label Assigned - {$gym_name}";

    $preview = mb_strlen($plan_text) > 400 ? mb_substr($plan_text, 0, 400) . '…' : $plan_text;
    $preview_html = nl2br(htmlspecialchars($preview));

    $body = "
      <h2 style='color:#0f172a;margin:0 0 6px;'>$icon New $label Assigned!</h2>
      <p style='color:#475569;margin:0 0 24px;'>Hi <strong>" . htmlspecialchars($name) . "</strong>, your trainer has assigned you a new $label.</p>
      <div style='background:$bg;border:1px solid $border;border-radius:8px;padding:20px;margin-bottom:24px;'>
        <div style='font-size:12px;color:#64748b;margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em;'>Assigned by</div>
        <div style='font-weight:700;font-size:15px;color:#0f172a;margin-bottom:16px;'>" . htmlspecialchars($trainer_name) . "</div>
        <div style='font-size:12px;color:#64748b;margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em;'>Plan Details</div>
        <div style='font-size:14px;color:#1e293b;line-height:1.8;background:#fff;border-radius:6px;padding:14px;border:1px solid $border;'>$preview_html</div>
      </div>
      <div style='text-align:center;margin-bottom:16px;'>
        <a href='" . APP_URL . "/member_dashboard.php'
           style='display:inline-block;background:$color;color:#fff;padding:14px 32px;border-radius:8px;font-weight:700;font-size:15px;text-decoration:none;'>
          View in Dashboard
        </a>
      </div>";
    return send_gym_mail($email, $name, $subject, $body);
}
