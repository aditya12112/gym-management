<?php
// trainer_leave.php
require 'config.php';
require 'auth.php';
require_login();

$role    = $_SESSION['role'] ?? '';
$user_id = (int)($_SESSION['user_id'] ?? 0);

/* Ensure schema */
foreach(['leave_start DATE NULL','leave_end DATE NULL','total_days INT DEFAULT 1'] as $col_def) {
    $col = explode(' ',$col_def)[0];
    try { $r=$conn->query("SHOW COLUMNS FROM trainer_leaves LIKE '$col'"); if(!$r||$r->num_rows===0) $conn->query("ALTER TABLE trainer_leaves ADD COLUMN $col_def"); } catch(Exception $e){}
}

/* Trainer submits */
if ($role==='trainer'&&$_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='submit_leave') {
    $start=trim($_POST['leave_start']??''); $end=trim($_POST['leave_end']??''); $reason=trim($_POST['reason']??'');
    if (empty($start)||empty($end)) { $_SESSION['error']="Please provide start and end dates."; }
    else {
        try { $d1=new DateTime($start); $d2=new DateTime($end); $days=$d1->diff($d2)->days+1; if($days<1)$days=1; } catch(Exception $e){ $_SESSION['error']="Invalid dates."; header('Location: trainer_leave.php'); exit; }
        $pst=$conn->prepare("INSERT INTO trainer_leaves (trainer_id,leave_start,leave_end,total_days,reason,status) VALUES (?,?,?,?,?,'pending')");
        if ($pst){ $pst->bind_param('issis',$user_id,$start,$end,$days,$reason); $pst->execute() ? $_SESSION['success']="Leave request submitted." : $_SESSION['error']="DB error: ".$conn->error; $pst->close(); }
    }
    header('Location: trainer_leave.php'); exit;
}

/* Admin updates */
if ($role==='admin'&&$_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='update_status') {
    $lid=(int)($_POST['leave_id']??0); $status=in_array($_POST['status']??'',['pending','approved','rejected'])?$_POST['status']:'pending';
    if ($lid>0) { $pst=$conn->prepare("UPDATE trainer_leaves SET status=? WHERE id=? LIMIT 1"); if($pst){ $pst->bind_param('si',$status,$lid); $pst->execute() ? $_SESSION['success']="Leave status updated." : $_SESSION['error']="DB error."; $pst->close(); } }
    header('Location: trainer_leave.php'); exit;
}

/* Fetch */
if ($role==='trainer') {
    $pst=$conn->prepare("SELECT id,leave_start,leave_end,total_days,reason,COALESCE(status,'pending') AS status FROM trainer_leaves WHERE trainer_id=? ORDER BY leave_start DESC,id DESC");
    $pst->bind_param('i',$user_id); $pst->execute(); $leaves=$pst->get_result(); $pst->close();
} else {
    $leaves=$conn->query("SELECT l.id,l.trainer_id,u.name AS trainer_name,l.leave_start,l.leave_end,l.total_days,l.reason,COALESCE(l.status,'pending') AS status FROM trainer_leaves l LEFT JOIN users u ON u.id=l.trainer_id ORDER BY l.leave_start DESC,l.id DESC");
}

$success=$_SESSION['success']??''; unset($_SESSION['success']);
$error  =$_SESSION['error']??'';   unset($_SESSION['error']);
$back   =$role==='admin'?'admin_dashboard.php':'trainer_dashboard.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8"><title>Trainer Leave — GymPro</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="assets/style.css?v=1.3">
  <script src="assets/theme.js?v=2.0"></script>
  <style>
    .leave-grid { display:grid; grid-template-columns:360px 1fr; gap:24px; align-items:start; }
    @media (max-width:900px) { .leave-grid { grid-template-columns:1fr; } }
  </style>
</head>
<body>
<nav class="navbar">
  <div class="navbar-brand">🏋️ GYM<span>PRO</span></div>
  <div class="navbar-links">
    <a href="<?php echo $back; ?>">← Dashboard</a>
    <a href="logout.php" class="nav-logout">Logout</a>
  </div>
</nav>

<div class="container">
  <div class="page-header">
    <h2>Trainer <span class="accent">Leave</span></h2>
    <?php if ($role==='trainer'): ?><span class="badge badge-<?php echo $role==='trainer'?'blue':'green'; ?>"><?php echo ucfirst($role); ?></span><?php endif; ?>
  </div>

  <?php if ($success): ?><div class="alert alert-success">✅ <?php echo htmlspecialchars($success); ?></div><?php endif; ?>
  <?php if ($error):   ?><div class="alert alert-error">⚠ <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

  <div class="leave-grid">

    <?php if ($role==='trainer'): ?>
    <!-- Request Form -->
    <div class="form-card">
      <h3 style="margin-bottom:4px;">Apply for Leave</h3>
      <p class="subtitle">Select your leave dates and reason below.</p>
      <form method="post">
        <input type="hidden" name="action" value="submit_leave">
        <div class="form-group">
          <label>From (Start Date)</label>
          <input type="date" name="leave_start" required min="<?php echo date('Y-m-d'); ?>">
        </div>
        <div class="form-group">
          <label>To (End Date)</label>
          <input type="date" name="leave_end" required min="<?php echo date('Y-m-d'); ?>">
        </div>
        <div class="form-group">
          <label>Reason <span style="color:var(--text-3);font-size:11px;text-transform:none;">(optional)</span></label>
          <textarea name="reason" placeholder="e.g., Medical appointment, personal leave..."></textarea>
        </div>
        <button class="btn" type="submit">Submit Leave Request</button>
      </form>
    </div>
    <?php endif; ?>

    <!-- Table -->
    <div class="card">
      <h3 style="margin-bottom:16px;">
        <?php echo $role==='admin'?'All Leave Requests':'Your Leave History'; ?>
      </h3>
      <?php if ($leaves && $leaves->num_rows > 0): ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <?php if ($role==='admin'): ?><th>Trainer</th><?php endif; ?>
              <th>From</th><th>To</th><th>Days</th><th>Reason</th><th>Status</th>
              <?php if ($role==='admin'): ?><th>Action</th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
          <?php while($l=$leaves->fetch_assoc()):
            $ls=strtolower(trim($l['status']??'pending'));
            $lbc=$ls==='approved'?'badge-green':($ls==='rejected'?'badge-red':'badge-yellow');
          ?>
            <tr>
              <?php if ($role==='admin'): ?>
                <td style="font-weight:600;"><?php echo htmlspecialchars($l['trainer_name']??'ID '.$l['trainer_id']); ?></td>
              <?php endif; ?>
              <td><?php echo htmlspecialchars($l['leave_start']??'—'); ?></td>
              <td><?php echo htmlspecialchars($l['leave_end']??'—'); ?></td>
              <td><strong><?php echo (int)($l['total_days']??1); ?></strong></td>
              <td style="font-size:13px;max-width:180px;"><?php echo nl2br(htmlspecialchars($l['reason']??'')); ?></td>
              <td><span class="badge <?php echo $lbc; ?>"><?php echo htmlspecialchars(ucfirst($ls)); ?></span></td>
              <?php if ($role==='admin'): ?>
              <td>
                <form method="post" style="display:flex;gap:8px;align-items:center;">
                  <input type="hidden" name="action" value="update_status">
                  <input type="hidden" name="leave_id" value="<?php echo (int)$l['id']; ?>">
                  <select name="status">
                    <option value="pending"  <?php if($ls==='pending') echo 'selected'; ?>>Pending</option>
                    <option value="approved" <?php if($ls==='approved') echo 'selected'; ?>>Approved</option>
                    <option value="rejected" <?php if($ls==='rejected') echo 'selected'; ?>>Rejected</option>
                  </select>
                  <button class="btn btn-sm" type="submit">Save</button>
                </form>
              </td>
              <?php endif; ?>
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

</body>
</html>