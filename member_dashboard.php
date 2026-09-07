<?php
// member_dashboard.php
require 'config.php';
require 'auth.php';
require_login();
require_role('member');

date_default_timezone_set('Asia/Kolkata');
$user_id = (int)($_SESSION['user_id'] ?? 0);

function html($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function pretty_date($d){
    if (empty($d) || $d === '0000-00-00') return '—';
    try { $dt = new DateTime($d); return $dt->format('d M Y'); }
    catch (Exception $e){ return html($d); }
}
function fmt_time($t){
    if (empty($t)) return '—';
    $p = explode(':',$t); if (count($p) < 2) return html($t);
    $h = (int)$p[0]; $m = (int)$p[1]; $amp = $h>=12?'PM':'AM'; $hh=$h%12; if(!$hh)$hh=12;
    return sprintf('%02d:%02d %s',$hh,$m,$amp);
}

/* Membership */
$membership_start = $membership_end = null; $membership_status = 'inactive';
$uRes = $conn->prepare("SELECT membership_start, membership_end, membership_status FROM users WHERE id=? LIMIT 1");
if ($uRes){ $uRes->bind_param('i',$user_id); $uRes->execute(); $ur=$uRes->get_result();
    if ($ur && $ur->num_rows){ $u=$ur->fetch_assoc(); $membership_start=$u['membership_start']??null; $membership_end=$u['membership_end']??null; $membership_status=$u['membership_status']??'inactive'; } $uRes->close(); }

$days_left = null; $today_ts = strtotime(date('Y-m-d'));
if (!empty($membership_end)){ $end_ts=strtotime($membership_end); if ($end_ts!==false) $days_left=(int)floor(($end_ts-$today_ts)/86400); }

/* ── Auto expiry reminder: send email if ≤5 days left (once per day) ── */
if (is_int($days_left) && $days_left >= 0 && $days_left <= 5
    && strtolower($membership_status) === 'active') {
    // Ensure last_reminder_sent column exists
    $chk = $conn->query("SHOW COLUMNS FROM users LIKE 'last_reminder_sent'");
    if (!$chk || $chk->num_rows === 0) {
        $conn->query("ALTER TABLE users ADD COLUMN `last_reminder_sent` DATE DEFAULT NULL");
    }
    // Fetch member email & last sent date
    $rr = $conn->query("SELECT email, name, last_reminder_sent FROM users WHERE id=$user_id LIMIT 1");
    if ($rr && $rr->num_rows) {
        $rd = $rr->fetch_assoc();
        $today_str = date('Y-m-d');
        if ($rd['last_reminder_sent'] !== $today_str && !empty($rd['email'])) {
            require_once 'mailer.php';
            $sent = mail_expiry_reminder($rd['email'], $rd['name'], $membership_end, $days_left);
            if ($sent) {
                $conn->query("UPDATE users SET last_reminder_sent='$today_str' WHERE id=$user_id");
            }
        }
    }
}

/* Recent bookings */
$bookings_res = false;
$bst = $conn->prepare("SELECT b.*, t.day_of_week, t.start_time, t.end_time FROM bookings b LEFT JOIN time_slots t ON b.slot_id=t.id WHERE b.user_id=? ORDER BY b.created_at DESC LIMIT 4");
if ($bst){ $bst->bind_param('i',$user_id); $bst->execute(); $bookings_res=$bst->get_result(); }

/* Trainer name resolver */
function resolve_trainer_name($conn,$tid){
    $tid=(int)$tid; if($tid<=0) return 'Trainer';
    $pst=$conn->prepare("SELECT name FROM users WHERE id=? LIMIT 1");
    if($pst){ $pst->bind_param('i',$tid); $pst->execute(); $res=$pst->get_result(); $pst->close();
        if($res&&$res->num_rows){ $r=$res->fetch_assoc(); if(!empty($r['name'])) return $r['name']; } }
    return 'Trainer';
}

/* Assignments */
$has_plan_text = false;
try { $r=$conn->query("SHOW COLUMNS FROM user_plan_assignments LIKE 'plan_text'"); if($r&&$r->num_rows) $has_plan_text=true; } catch(Exception $e){}
$assignments = [];
$assign_sql = "SELECT a.id, a.plan_type, ".($has_plan_text?"a.plan_text":"NULL AS plan_text").", a.custom_notes, a.assigned_at, COALESCE(NULLIF(a.status,''),'assigned') AS status, a.trainer_id FROM user_plan_assignments a WHERE a.user_id=? ORDER BY a.assigned_at DESC LIMIT 30";
if ($pst=$conn->prepare($assign_sql)){ $pst->bind_param('i',$user_id); $pst->execute(); $res=$pst->get_result();
    while($row=$res->fetch_assoc()){ $desc=($has_plan_text&&!empty($row['plan_text']))?$row['plan_text']:($row['custom_notes']??'');
        $assignments[]=['id'=>$row['id'],'plan_type'=>$row['plan_type'],'description'=>$desc,'assigned_at'=>$row['assigned_at'],'status'=>$row['status']??'assigned','trainer_id'=>$row['trainer_id']]; } $pst->close(); }

$latest_diet=null; $latest_exercise=null;
foreach($assignments as $a){ if($a['plan_type']==='diet'&&!$latest_diet) $latest_diet=$a; if($a['plan_type']==='exercise'&&!$latest_exercise) $latest_exercise=$a; }
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <title>Member Dashboard — GymPro</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="assets/style.css?v=1.4">
  <script src="assets/theme.js?v=2.0"></script>
  <style>
    /* ── Layout ── */
    .dash-grid { display:grid; grid-template-columns:360px 1fr; gap:28px; align-items:start; }
    .panel     { display:flex; flex-direction:column; gap:24px; }
    @media (max-width:960px) { .dash-grid { grid-template-columns:1fr; } }

    /* ── Membership Ring ── */
    .mem-ring {
      width:120px; height:120px; border-radius:50%;
      display:flex; flex-direction:column; align-items:center; justify-content:center;
      border:4px solid var(--border); margin:0 auto 18px;
      transition:box-shadow .3s;
    }
    .mem-ring.active  { border-color:var(--accent); box-shadow:0 0 28px rgba(200,241,53,.25); }
    .mem-ring.expired { border-color:var(--danger);  box-shadow:0 0 28px rgba(255, 68, 68,.2); }
    .mem-ring .days   { font-family:'Barlow Condensed',sans-serif; font-size:2.4rem; font-weight:800; line-height:1; }
    .mem-ring .days-label { font-size:10px; letter-spacing:.08em; color:var(--text-3); margin-top:2px; }

    /* ── Membership action buttons ── */
    .mem-btn { display:flex; align-items:center; justify-content:center; gap:8px;
               width:100%; padding:13px 20px; font-size:15px; margin-top:6px; border-radius:var(--radius); }

    /* ── Quick Access tiles ── */
    .quick-links { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    .ql-item {
      display:flex; flex-direction:column; align-items:center; justify-content:center;
      gap:10px; padding:20px 14px; min-height:100px;
      background:var(--bg-3); border:1px solid var(--border); border-radius:var(--radius-lg);
      text-decoration:none; color:var(--text-2); font-size:13px; font-weight:600;
      letter-spacing:.02em; text-align:center;
      transition:all .22s ease;
    }
    .ql-item:hover  { border-color:var(--accent); color:var(--accent); background:var(--accent-glow); transform:translateY(-3px); box-shadow:var(--glow); opacity:1; }
    .ql-item.ql-highlight { border-color:var(--accent); color:var(--accent); }
    .ql-icon { font-size:28px; line-height:1; }

    /* ── Plan boxes ── */
    .plan-box {
      background:var(--bg-3); border:1px solid var(--border); border-radius:var(--radius-lg);
      padding:18px; position:relative; overflow:hidden;
    }
    .plan-box::before {
      content:''; position:absolute; top:0; left:0; right:0; height:3px;
      background:linear-gradient(90deg, var(--accent), transparent);
    }
    .plan-box.exercise::before { background:linear-gradient(90deg, #3b82f6, transparent); }
    .plan-box .plan-title { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; }
    .plan-box h4 { margin:0; font-size:1rem; }
    .plan-box .plan-text  { font-size:13px; color:var(--text-2); line-height:1.7; margin:8px 0; white-space:pre-wrap; max-height:150px; overflow-y:auto; }
    .plan-box .plan-meta  { font-size:11px; color:var(--text-3); border-top:1px solid var(--border); padding-top:8px; margin-top:8px; }
    .plan-box .plan-empty { font-size:13px; color:var(--text-3); margin-top:8px; text-align:center; padding:18px 0; }

    /* ── Section header row ── */
    .card-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; }
    .card-header .section-label { margin-bottom:0; }

    /* ── View-all button ── */
    .btn-view-all { padding:8px 18px; font-size:13px; }
  </style>
</head>
<body>

<!-- ═══════════════ NAVBAR ═══════════════ -->
<nav class="navbar">
  <div class="navbar-brand">🏋️ GYM<span>PRO</span></div>
  <div class="navbar-links"></div>
  <div class="navbar-actions">
    <span class="navbar-user">Hello, <strong><?php echo html($_SESSION['name']??''); ?></strong></span>
    <a href="book_slot.php">Book Slot</a>
    <a href="my_bookings.php">Bookings</a>
    <a href="bmi_calculator.php">BMI</a>
    <a href="complaints.php">Complaints</a>
    <button class="theme-btn" id="themeBtn" title="Toggle theme" aria-label="Toggle dark/light mode">
      <span class="moon">🌙</span>
      <span class="sun">☀️</span>
    </button>
    <a href="logout.php" class="nav-logout">Logout</a>
  </div>
</nav>

<!-- ═══════════════ MAIN ═══════════════ -->
<div class="container">
  <div class="page-header">
    <div>
      <p style="margin:0;font-size:13px;color:var(--text-3);"><?php echo date('l, d F Y'); ?></p>
      <h2>My <span class="accent">Dashboard</span></h2>
    </div>
    <a href="payments.php" class="btn btn-lg">💳 Manage Membership</a>
  </div>

  <div class="dash-grid">

    <!-- ═══ LEFT COLUMN ═══ -->
    <div class="panel">

      <!-- Membership Card -->
      <div class="card" style="text-align:center;">
        <?php
          $ms        = strtolower($membership_status??'inactive');
          $ringClass = ($ms==='active') ? 'active' : 'expired';
          $ringColor = $ms==='active' ? 'var(--accent)' : 'var(--danger)';
        ?>
        <div class="mem-ring <?php echo $ringClass; ?>">
          <div class="days" style="color:<?php echo $ringColor; ?>;">
            <?php echo is_int($days_left) ? abs($days_left) : '?'; ?>
          </div>
          <div class="days-label">
            <?php echo $ms==='active' ? 'DAYS LEFT' : ($days_left!==null&&$days_left<0 ? 'EXPIRED' : 'INACTIVE'); ?>
          </div>
        </div>

        <div class="section-label" style="justify-content:center;margin-bottom:12px;">MEMBERSHIP STATUS</div>

        <?php if ($ms==='active'): ?>
          <span class="badge badge-green" style="font-size:13px;padding:5px 16px;">✅ Active</span>
        <?php elseif ($days_left !== null && $days_left < 0): ?>
          <span class="badge badge-red" style="font-size:13px;padding:5px 16px;">❌ Expired</span>
        <?php else: ?>
          <span class="badge badge-gray" style="font-size:13px;padding:5px 16px;">⏸ Inactive</span>
        <?php endif; ?>

        <div style="margin-top:16px;font-size:13px;color:var(--text-3);line-height:2;">
          <?php if (!empty($membership_start)): ?>
            <div>From: <strong style="color:var(--text-1);"><?php echo pretty_date($membership_start); ?></strong></div>
          <?php endif; ?>
          <?php if (!empty($membership_end)): ?>
            <div>Until: <strong style="color:var(--text-1);"><?php echo pretty_date($membership_end); ?></strong></div>
          <?php endif; ?>
        </div>

        <?php if ($days_left !== null && $days_left < 0): ?>
          <div class="alert alert-error" style="margin-top:16px;text-align:left;">Your membership expired <?php echo abs($days_left); ?> day(s) ago.</div>
          <a href="payments.php" class="btn mem-btn">🔄 Renew Now</a>
        <?php elseif ($days_left !== null && $days_left <= 10 && $ms==='active'): ?>
          <div class="alert alert-warning" style="margin-top:16px;text-align:left;">Expires in <?php echo $days_left; ?> day(s). Renew soon!</div>
          <a href="payments.php" class="btn mem-btn">🔄 Renew Now</a>
        <?php elseif ($days_left === null): ?>
          <a href="payments.php" class="btn mem-btn" style="margin-top:18px;">💳 Buy Membership</a>
        <?php endif; ?>
      </div>

      <!-- Quick Access -->
      <div class="card">
        <div class="section-label">Quick Access</div>
        <div class="quick-links">
          <a href="book_slot.php"        class="ql-item"><span class="ql-icon">📅</span>Book Slot</a>
          <a href="my_bookings.php"      class="ql-item"><span class="ql-icon">📋</span>My Bookings</a>
          <a href="payments.php"         class="ql-item"><span class="ql-icon">💳</span>Payments</a>
          <a href="complaints.php"       class="ql-item"><span class="ql-icon">💬</span>Complaints</a>
          <a href="manage_exercises.php" class="ql-item"><span class="ql-icon">🏋️</span>Exercises</a>
          <a href="bmi_calculator.php"   class="ql-item"><span class="ql-icon">⚖️</span>BMI Calculator</a>
        </div>
      </div>

    </div><!-- /LEFT -->

    <!-- ═══ RIGHT COLUMN ═══ -->
    <div class="panel">

      <!-- Assigned Plans -->
      <div class="card">
        <div class="section-label">Assigned Plans</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:4px;">

          <!-- Diet -->
          <div class="plan-box">
            <div class="plan-title">
              <h4>🥗 Diet Plan</h4>
              <?php if ($latest_diet): ?>
                <?php $ds = strtolower($latest_diet['status']??'assigned'); ?>
                <span class="badge badge-<?php echo $ds==='approved'?'green':($ds==='rejected'?'red':'blue'); ?>">
                  <?php echo html(ucfirst($latest_diet['status']??'assigned')); ?>
                </span>
              <?php endif; ?>
            </div>
            <?php if ($latest_diet): ?>
              <div class="plan-text"><?php echo nl2br(html($latest_diet['description']??'—')); ?></div>
              <div class="plan-meta">By <?php echo html(resolve_trainer_name($conn,$latest_diet['trainer_id']??0)); ?> · <?php echo html($latest_diet['assigned_at']); ?></div>
            <?php else: ?>
              <div class="plan-empty">No diet plan assigned yet.</div>
            <?php endif; ?>
          </div>

          <!-- Exercise -->
          <div class="plan-box exercise">
            <div class="plan-title">
              <h4>💪 Exercise Plan</h4>
              <?php if ($latest_exercise): ?>
                <?php $es = strtolower($latest_exercise['status']??'assigned'); ?>
                <span class="badge badge-<?php echo $es==='approved'?'green':($es==='rejected'?'red':'blue'); ?>">
                  <?php echo html(ucfirst($latest_exercise['status']??'assigned')); ?>
                </span>
              <?php endif; ?>
            </div>
            <?php if ($latest_exercise): ?>
              <div class="plan-text"><?php echo nl2br(html($latest_exercise['description']??'—')); ?></div>
              <div class="plan-meta">By <?php echo html(resolve_trainer_name($conn,$latest_exercise['trainer_id']??0)); ?> · <?php echo html($latest_exercise['assigned_at']); ?></div>
            <?php else: ?>
              <div class="plan-empty">No exercise plan assigned yet.</div>
            <?php endif; ?>
          </div>

        </div>
      </div>

      <!-- Plan History -->
      <?php if (!empty($assignments)): ?>
      <div class="card">
        <div class="card-header">
          <div class="section-label">Plan History</div>
        </div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Date</th><th>Type</th><th>Description</th><th>By</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach($assignments as $a):
              $sc = strtolower($a['status']??'assigned');
              $bc = $sc==='approved'?'badge-green':($sc==='rejected'?'badge-red':'badge-blue');
            ?>
              <tr>
                <td style="font-size:12px;color:var(--text-3);white-space:nowrap;"><?php echo html($a['assigned_at']); ?></td>
                <td><span class="badge badge-<?php echo $a['plan_type']==='diet'?'green':'blue'; ?>"><?php echo html(ucfirst($a['plan_type'])); ?></span></td>
                <td style="max-width:280px;font-size:13px;"><?php echo nl2br(html(mb_strimwidth($a['description']??'—',0,100,'…'))); ?></td>
                <td style="font-size:13px;"><?php echo html(resolve_trainer_name($conn,$a['trainer_id']??0)); ?></td>
                <td><span class="badge <?php echo $bc; ?>"><?php echo html(ucfirst($a['status'])); ?></span></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>

      <!-- Recent Bookings -->
      <div class="card">
        <div class="card-header">
          <div class="section-label" style="margin-bottom:0;">Recent Bookings</div>
          <a href="my_bookings.php" class="btn btn-outline btn-view-all">View All →</a>
        </div>
        <?php if ($bookings_res && $bookings_res->num_rows): ?>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Day</th><th>Time</th><th>Status</th><th>Booked On</th></tr></thead>
            <tbody>
            <?php while($b=$bookings_res->fetch_assoc()):
              $bs  = strtolower($b['status']??'booked');
              $bbc = $bs==='booked'?'badge-green':($bs==='cancelled'?'badge-red':'badge-gray');
            ?>
              <tr>
                <td><?php echo html($b['day_of_week']??'—'); ?></td>
                <td><?php echo fmt_time($b['start_time']).' – '.fmt_time($b['end_time']); ?></td>
                <td><span class="badge <?php echo $bbc; ?>"><?php echo html(ucfirst($bs)); ?></span></td>
                <td style="font-size:12px;color:var(--text-3);"><?php echo html($b['created_at']??''); ?></td>
              </tr>
            <?php endwhile; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
          <p style="font-size:14px;color:var(--text-3);margin-top:12px;">No bookings yet. <a href="book_slot.php">Book a slot →</a></p>
        <?php endif; ?>
      </div>

    </div><!-- /RIGHT -->
  </div>
</div>


</body>
</html>