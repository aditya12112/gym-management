<?php
// trainer_dashboard.php
require 'config.php';
require 'auth.php';
require_login();
require_role('trainer');

date_default_timezone_set('Asia/Kolkata');
$trainer_id = (int)($_SESSION['user_id'] ?? 0);

function html($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function human_date($d) {
    if (empty($d)||$d==='0000-00-00') return '—';
    try { $dt=new DateTime($d); return $dt->format('d M Y'); } catch(Exception $e){ return html($d); }
}
function fmt_time($t) {
    if (empty($t)) return '—';
    $p=explode(':',$t); if(count($p)<2) return html($t);
    $h=(int)$p[0]; $m=(int)$p[1]; $amp=$h>=12?'PM':'AM'; $hh=$h%12; if(!$hh)$hh=12;
    return sprintf('%02d:%02d %s',$hh,$m,$amp);
}

$activeTab = $_GET['tab'] ?? 'assign';

/* Members list (Active only) */
$members = [];
try {
    $res = $conn->query("SELECT id, name, email FROM users WHERE role='member' AND membership_status='active' ORDER BY name");
    if ($res) while ($r=$res->fetch_assoc()) $members[]=$r;
} catch(Exception $e){}

/* Fetch Trainer's Saved Exercises */
$saved_exercises = [];
try {
    $se_res = $conn->query("
        SELECT e.name, e.body_part, e.description 
        FROM user_saved_exercises usex 
        JOIN exercises e ON usex.exercise_id = e.id 
        WHERE usex.user_id = $trainer_id 
        ORDER BY e.body_part, e.name
    ");
    if ($se_res) while ($r = $se_res->fetch_assoc()) $saved_exercises[] = $r;
} catch (Exception $e){}

/* Time slots */
$slots = $conn->query("SELECT * FROM time_slots WHERE trainer_id=".intval($trainer_id)." ORDER BY FIELD(LOWER(day_of_week),'mon','monday','tue','tuesday','wed','wednesday','thu','thursday','fri','friday','sat','saturday','sun','sunday'), start_time");

/* Leaves */
$leaves = $conn->query("SELECT id, COALESCE(NULLIF(leave_start,''),NULLIF(leave_date,'')) AS leave_start, COALESCE(NULLIF(leave_end,''),NULLIF(leave_date,'')) AS leave_end, COALESCE(total_days,1) AS total_days, reason, status FROM trainer_leaves WHERE trainer_id=".intval($trainer_id)." ORDER BY id DESC LIMIT 8");

/* Assignments */
$descCol = null;
try { $r=$conn->query("SHOW COLUMNS FROM user_plan_assignments LIKE 'plan_text'"); if($r&&$r->num_rows) $descCol='plan_text'; } catch(Exception $e){}
if (!$descCol) { try { $r=$conn->query("SHOW COLUMNS FROM user_plan_assignments LIKE 'custom_notes'"); if($r&&$r->num_rows) $descCol='custom_notes'; } catch(Exception $e){} }
$descSelect = $descCol ? "a.`{$descCol}` AS plan_text" : "'' AS plan_text";

$assignments_res = null;
$as_sql = "SELECT a.id, a.assigned_at, a.plan_type, {$descSelect}, COALESCE(NULLIF(a.status,''),'assigned') AS status, a.user_id AS target_id FROM user_plan_assignments a WHERE a.trainer_id=? ORDER BY a.assigned_at DESC LIMIT 40";
if ($stmt=$conn->prepare($as_sql)){ $stmt->bind_param('i',$trainer_id); $stmt->execute(); $assignments_res=$stmt->get_result(); $stmt->close(); }

function resolve_user_name($conn, $user_id) {
    $uid = (int)$user_id;
    $pst=$conn->prepare("SELECT name FROM users WHERE id=? LIMIT 1");
    if($pst){ $pst->bind_param('i', $uid); $pst->execute(); $res=$pst->get_result(); $pst->close();
        if($res&&$res->num_rows){ $r=$res->fetch_assoc(); if(!empty($r['name'])) return $r['name']; } }
    return 'ID '.$uid;
}

/* Flash messages */
$success = $_SESSION['success'] ?? ''; unset($_SESSION['success']);
$error   = $_SESSION['error']   ?? ''; unset($_SESSION['error']);

/* Stats */
$total_members = count($members);
$total_assignments = 0;
try { $r=$conn->query("SELECT COUNT(*) c FROM user_plan_assignments WHERE trainer_id=".intval($trainer_id)); if($r){ $total_assignments=(int)$r->fetch_assoc()['c']; } } catch(Exception $e){}
$pending_leaves = 0;
try { $r=$conn->query("SELECT COUNT(*) c FROM trainer_leaves WHERE trainer_id=".intval($trainer_id)." AND status='pending'"); if($r){ $pending_leaves=(int)$r->fetch_assoc()['c']; } } catch(Exception $e){}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <title>Trainer Dashboard — GymPro</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="assets/style.css?v=1.5">
  <script src="assets/theme.js?v=2.0"></script>
  <style>
    .assign-grid { display:grid; grid-template-columns:1fr 1fr; gap:18px; }
    .tab-bar { display:flex; gap:6px; margin-bottom:20px; }
    .tab-btn { padding:8px 16px; border-radius:var(--radius); border:1px solid var(--border); background:var(--bg-3); color:var(--text-2); cursor:pointer; font-family:'DM Sans',sans-serif; font-size:13px; font-weight:600; transition:all 0.2s; }
    .tab-btn.active { background:var(--accent); color:#0a0a0a; border-color:var(--accent); }
    .tab-panel { display:none; }
    .tab-panel.active { display:block; }
    @media (max-width:700px) { .assign-grid { grid-template-columns:1fr; } }
  </style>
</head>
<body>
<nav class="navbar">
  <div class="navbar-brand">🏋️ GYM<span>PRO</span></div>
  <div class="navbar-links"></div>
  <div class="navbar-actions">
    <span class="navbar-user">Hello, <strong><?php echo html($_SESSION['name']??''); ?></strong></span>
    <a href="trainer_leave.php">Leaves</a>
    <a href="manage_exercises.php">Exercises</a>
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
      <p style="margin:0;font-size:13px;color:var(--text-3);"><?php echo date('l, d F Y'); ?></p>
      <h2>Trainer <span class="accent">Dashboard</span></h2>
    </div>
    <a href="trainer_leave.php" class="btn btn-outline">+ Request Leave</a>
  </div>

  <?php if ($success): ?><div class="alert alert-success">✅ <?php echo html($success); ?></div><?php endif; ?>
  <?php if ($error):   ?><div class="alert alert-error">⚠ <?php echo html($error); ?></div><?php endif; ?>

  <!-- Stats -->
  <div class="stat-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:28px;">
    <div class="stat-card"><span class="stat-icon">👥</span><div class="stat-label">Members</div><div class="stat-num"><?php echo $total_members; ?></div></div>
    <div class="stat-card"><span class="stat-icon">📋</span><div class="stat-label">Total Assignments</div><div class="stat-num"><?php echo $total_assignments; ?></div></div>
    <div class="stat-card"><span class="stat-icon">🏖️</span><div class="stat-label">Pending Leaves</div><div class="stat-num" style="color:<?php echo $pending_leaves>0?'var(--warning)':'var(--text-1)'; ?>"><?php echo $pending_leaves; ?></div></div>
  </div>

  <!-- Tabs -->
  <div class="tab-bar">
    <a href="?tab=assign" class="tab-btn <?php echo $activeTab==='assign'?'active':''; ?>" style="text-decoration:none; display:inline-block;">📋 Assign Plans</a>
    <a href="?tab=history" class="tab-btn <?php echo $activeTab==='history'?'active':''; ?>" style="text-decoration:none; display:inline-block;">📜 Assignment History</a>
    <a href="?tab=slots" class="tab-btn <?php echo $activeTab==='slots'?'active':''; ?>" style="text-decoration:none; display:inline-block;">🕐 My Slots</a>
    <a href="?tab=leaves" class="tab-btn <?php echo $activeTab==='leaves'?'active':''; ?>" style="text-decoration:none; display:inline-block;">🏖️ My Leaves</a>
  </div>

  <!-- TAB: Assign Plans -->
  <div id="tab-assign" class="tab-panel <?php echo $activeTab==='assign'?'active':''; ?>" style="<?php echo $activeTab==='assign'?'display:block;':'display:none;'; ?>">
    <div class="assign-grid">
      <!-- Diet -->
      <div class="card">
        <h3 style="margin-bottom:4px;">🥗 Assign Diet Plan</h3>
        <p style="font-size:13px;color:var(--text-3);margin-bottom:20px;">Write a personalised meal plan for a member.</p>
        <form method="post" action="assign_plan.php">
          <input type="hidden" name="plan_type" value="diet">
          <div class="form-group">
            <label>Select Member</label>
            <select name="user_id" required>
              <option value="">Choose a member...</option>
              <?php foreach($members as $m): ?>
                <option value="<?php echo (int)$m['id']; ?>"><?php echo html($m['name']); ?> <?php if(!empty($m['email'])) echo '('.$m['email'].')'; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Diet Description</label>
            <textarea name="plan_text" rows="5" required placeholder="e.g. Breakfast: Oats + 2 eggs. Lunch: Brown rice + chicken 200g..."></textarea>
          </div>
          <button class="btn" type="submit">Assign Diet →</button>
        </form>
      </div>
      <!-- Exercise -->
      <div class="card">
        <h3 style="margin-bottom:4px;">💪 Assign Exercise Plan</h3>
        <p style="font-size:13px;color:var(--text-3);margin-bottom:20px;">Create a workout routine for a member.</p>
        <form method="post" action="assign_plan.php">
          <input type="hidden" name="plan_type" value="exercise">
          <div class="form-group">
            <label>Select Member</label>
            <select name="user_id" required>
              <option value="">Choose a member...</option>
              <?php foreach($members as $m): ?>
                <option value="<?php echo (int)$m['id']; ?>"><?php echo html($m['name']); ?> <?php if(!empty($m['email'])) echo '('.$m['email'].')'; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Exercise Description</label>
            <?php if (!empty($saved_exercises)): ?>
            <div style="margin-bottom:8px; display:flex; gap:8px; overflow-x:auto; padding-bottom:8px;">
              <span style="font-size:12px; color:var(--text-3); align-self:center; white-space:nowrap;">Quick Insert:</span>
              <?php foreach($saved_exercises as $ex): 
                $exName = json_encode($ex['name']);
                $exDesc = json_encode($ex['description']);
              ?>
                <button type="button" class="btn btn-sm" style="background:var(--bg-3); border-color:var(--border); color:var(--text-2); border-radius:12px; font-size:11px; padding:4px 10px; white-space:nowrap;" onclick='insertExercise(<?php echo htmlspecialchars($exName, ENT_QUOTES); ?>, <?php echo htmlspecialchars($exDesc, ENT_QUOTES); ?>)'>
                  + <?php echo html($ex['name']); ?>
                </button>
              <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div style="margin-bottom:8px; font-size:12px; color:var(--text-3);">
              <em>Pro-tip: <a href="manage_exercises.php" style="color:var(--accent);">Browse exercises</a> and save them to your library for quick-insert here!</em>
            </div>
            <?php endif; ?>
            <textarea id="exPlanText" name="plan_text" rows="5" required placeholder="e.g. Day 1: Push-ups 3x15, Squats 3x20. Day 2: Rest..."></textarea>
          </div>
          <button class="btn" type="submit">Assign Exercise →</button>
        </form>
      </div>
    </div>
  </div>

  <!-- TAB: Assignment History -->
  <div id="tab-history" class="tab-panel <?php echo $activeTab==='history'?'active':''; ?>" style="<?php echo $activeTab==='history'?'display:block;':'display:none;'; ?>">
    <div class="card">
      <h3 style="margin-bottom:16px;">Your Assignments</h3>
      <?php
        $hasList = $assignments_res && $assignments_res->num_rows;
        $rows = [];
        if ($hasList) while($a=$assignments_res->fetch_assoc()) $rows[]=$a;
      ?>
      <?php if (empty($rows)): ?>
        <p style="color:var(--text-3);">No assignments yet.</p>
      <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Date</th><th>Type</th><th>Member</th><th>Description</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
          <?php foreach($rows as $a):
            $status=trim($a['status']??'assigned');
            $bc=$status==='approved'?'badge-green':($status==='rejected'?'badge-red':'badge-blue');
            $memberName=resolve_user_name($conn,(int)$a['target_id']);
            $desc=$a['plan_text']??'';
          ?>
            <tr>
              <td style="font-size:12px;color:var(--text-3);white-space:nowrap;"><?php echo html($a['assigned_at']); ?></td>
              <td><span class="badge badge-<?php echo $a['plan_type']==='diet'?'green':'blue'; ?>"><?php echo html(ucfirst($a['plan_type'])); ?></span></td>
              <td style="font-weight:600;"><?php echo html($memberName); ?></td>
              <td style="font-size:13px;max-width:260px;"><?php echo nl2br(html(mb_strimwidth($desc,0,90,'…'))); ?></td>
              <td><span class="badge <?php echo $bc; ?>"><?php echo html(ucfirst($status)); ?></span></td>
              <td style="white-space:nowrap;">
                <a class="btn btn-sm btn-outline" href="assign_edit.php?id=<?php echo (int)$a['id']; ?>">Edit</a>
                <a class="btn btn-sm btn-danger" href="assign_delete.php?id=<?php echo (int)$a['id']; ?>" onclick="return confirm('Delete this assignment?')">Delete</a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- TAB: My Slots -->
  <div id="tab-slots" class="tab-panel <?php echo $activeTab==='slots'?'active':''; ?>" style="<?php echo $activeTab==='slots'?'display:block;':'display:none;'; ?>">
    <div class="card">
      <h3 style="margin-bottom:16px;">Your Assigned Time Slots</h3>
      <?php if ($slots && $slots->num_rows): ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Day</th><th>Start</th><th>End</th><th>Capacity</th></tr></thead>
          <tbody>
          <?php while($s=$slots->fetch_assoc()): ?>
            <tr>
              <td style="font-weight:600;"><?php echo html($s['day_of_week']); ?></td>
              <td><?php echo fmt_time($s['start_time']); ?></td>
              <td><?php echo fmt_time($s['end_time']); ?></td>
              <td><span class="badge badge-lime"><?php echo (int)min($s['capacity'],20); ?> seats</span></td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
        <p style="color:var(--text-3);">No time slots assigned by admin yet.</p>
      <?php endif; ?>
    </div>
  </div>

  <!-- TAB: Leaves -->
  <div id="tab-leaves" class="tab-panel <?php echo $activeTab==='leaves'?'active':''; ?>" style="<?php echo $activeTab==='leaves'?'display:block;':'display:none;'; ?>">
    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h3 style="margin:0;">Leave History</h3>
        <a href="trainer_leave.php" class="btn btn-sm">+ New Request</a>
      </div>
      <?php if ($leaves && $leaves->num_rows): ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>From</th><th>To</th><th>Days</th><th>Reason</th><th>Status</th></tr></thead>
          <tbody>
          <?php while($l=$leaves->fetch_assoc()):
            $ls=strtolower(trim($l['status']??'pending'));
            $lbc=$ls==='approved'?'badge-green':($ls==='rejected'?'badge-red':'badge-yellow');
          ?>
            <tr>
              <td><?php echo human_date($l['leave_start']); ?></td>
              <td><?php echo human_date($l['leave_end']); ?></td>
              <td><strong><?php echo (int)($l['total_days']??1); ?></strong></td>
              <td style="font-size:13px;"><?php echo nl2br(html($l['reason']??'')); ?></td>
              <td><span class="badge <?php echo $lbc; ?>"><?php echo html(ucfirst($ls)); ?></span></td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
        <p style="color:var(--text-3);">No leave requests found.</p>
      <?php endif; ?>
    </div>
  </div>

</div>

<script>
function insertExercise(name, desc) {
    const box = document.getElementById('exPlanText');
    let textToInsert = "💪 [" + name + "]\n" + desc;
    
    if (box.value.trim().length > 0) {
        if (!box.value.endsWith('\n')) box.value += '\n\n';
        else box.value += '\n';
    } else {
        box.value = ''; // clear any empty whitespace
    }
    
    box.value += textToInsert + '\n\n';
    box.focus();
}
</script>
</body>
</html>