<?php
// booking_view.php  — member booking detail page (admin)
require 'config.php';
require 'auth.php';
require_login();
require_role('admin');

date_default_timezone_set('Asia/Kolkata');

function html($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if ($user_id <= 0) {
    header('Location: bookings_history.php');
    exit;
}

/* ── Member info ── */
$member_name  = 'Unknown';
$member_email = '';
$mem_status   = 'inactive';
$mem_start    = null;
$mem_end      = null;
try {
    $r = $conn->prepare("SELECT name, email, membership_status, membership_start, membership_end FROM users WHERE id = ? LIMIT 1");
    if ($r) {
        $r->bind_param('i', $user_id);
        $r->execute();
        $res = $r->get_result();
        $r->close();
        if ($res && $res->num_rows) {
            $u = $res->fetch_assoc();
            $member_name  = $u['name'] ?: ('ID ' . $user_id);
            $member_email = $u['email'] ?? '';
            $mem_status   = strtolower($u['membership_status'] ?? 'inactive');
            $mem_start    = $u['membership_start'];
            $mem_end      = $u['membership_end'];
        }
    }
} catch (Exception $e) {}

/* ── Bookings ── */
$bookings = [];
$error    = null;
try {
    $stmt = $conn->prepare("
        SELECT b.id AS booking_id, b.slot_id, b.status, b.created_at,
               t.day_of_week, t.start_time, t.end_time
        FROM bookings b
        LEFT JOIN time_slots t ON b.slot_id = t.id
        WHERE b.user_id = ?
        ORDER BY b.created_at DESC
        LIMIT 200
    ");
    if ($stmt) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $bookings[] = $row;
        $stmt->close();
    } else {
        $error = $conn->error;
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}

function fmt_time($t) {
    if (empty($t)) return '—';
    $p = explode(':', $t); if (count($p) < 2) return htmlspecialchars($t);
    $h = (int)$p[0]; $m = (int)$p[1]; $ap = $h >= 12 ? 'PM' : 'AM'; $hh = $h % 12; if (!$hh) $hh = 12;
    return sprintf('%d:%02d %s', $hh, $m, $ap);
}

$ms_badge = $mem_status === 'active' ? 'badge-green' : ($mem_status === 'inactive' ? 'badge-gray' : 'badge-red');
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <title><?php echo html($member_name); ?> — Bookings · GymPro</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="assets/style.css?v=1.3">
  <script src="assets/theme.js?v=2.0"></script>
  <style>
    .meta-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:14px; margin-bottom:28px; }
    .meta-card { background:var(--card); border:1px solid var(--border); border-radius:var(--radius-lg); padding:18px; }
    .meta-label { font-size:11px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--text-3); margin-bottom:6px; }
    .meta-val { font-family:'Barlow Condensed',sans-serif; font-size:1.3rem; font-weight:700; color:var(--text-1); }
  </style>
</head>
<body>

<nav class="navbar">
  <div class="navbar-brand">🏋️ GYM<span>PRO</span></div>
  <div class="navbar-links">
    <a href="admin_dashboard.php">← Dashboard</a>
    <a href="logout.php" class="nav-logout">Logout</a>
  </div>
</nav>

<div class="container">

  <div class="page-header">
    <div>
      <div style="font-size:13px;color:var(--text-3);margin-bottom:4px;">Member Detail</div>
      <h2>📋 <?php echo html($member_name); ?></h2>
      <?php if ($member_email): ?>
        <div style="font-size:13px;color:var(--text-3);margin-top:4px;"><?php echo html($member_email); ?></div>
      <?php endif; ?>
    </div>
    <a href="bookings_history.php" class="btn btn-outline">← Back to History</a>
  </div>

  <!-- Member meta stats -->
  <div class="meta-grid">
    <div class="meta-card">
      <div class="meta-label">Membership Status</div>
      <div class="meta-val"><span class="badge <?php echo $ms_badge; ?>"><?php echo ucfirst($mem_status); ?></span></div>
    </div>
    <div class="meta-card">
      <div class="meta-label">Total Bookings</div>
      <div class="meta-val"><?php echo count($bookings); ?></div>
    </div>
    <div class="meta-card">
      <div class="meta-label">Member Since</div>
      <div class="meta-val" style="font-size:1rem;"><?php echo $mem_start ? date('d M Y', strtotime($mem_start)) : '—'; ?></div>
    </div>
    <div class="meta-card">
      <div class="meta-label">Valid Until</div>
      <div class="meta-val" style="font-size:1rem;"><?php echo $mem_end ? date('d M Y', strtotime($mem_end)) : '—'; ?></div>
    </div>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-error">⚠ Database error: <?php echo html($error); ?></div>
  <?php endif; ?>

  <!-- Bookings table -->
  <div class="card">
    <h3 style="margin-bottom:16px;">All Bookings</h3>
    <?php if (empty($bookings)): ?>
      <div style="text-align:center;padding:50px;">
        <div style="font-size:40px;margin-bottom:12px;">📅</div>
        <p style="color:var(--text-3);">No bookings found for this member.</p>
      </div>
    <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Day</th>
            <th>Time</th>
            <th>Status</th>
            <th>Booked At</th>
          </tr>
        </thead>
        <tbody>
        <?php $i = 0; foreach ($bookings as $b): $i++;
          $bs  = strtolower($b['status'] ?? 'booked');
          $bbc = $bs === 'booked' ? 'badge-green' : ($bs === 'cancelled' ? 'badge-red' : 'badge-gray');
        ?>
          <tr>
            <td style="color:var(--text-3);font-size:12px;"><?php echo $i; ?></td>
            <td style="font-weight:600;"><?php echo html($b['day_of_week'] ?? '—'); ?></td>
            <td style="font-size:13px;white-space:nowrap;">
              <?php echo fmt_time($b['start_time']); ?> – <?php echo fmt_time($b['end_time']); ?>
            </td>
            <td><span class="badge <?php echo $bbc; ?>"><?php echo html(ucfirst($bs)); ?></span></td>
            <td style="font-size:13px;color:var(--text-3);white-space:nowrap;"><?php echo html($b['created_at'] ?? ''); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

</div>
</body>
</html>
