<?php
// manage_timeslots.php
require 'config.php';
require 'auth.php';
require_login();
require_role('admin');

$msg = ''; $msg_type = '';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $trainer_id=(int)($_POST['trainer_id']??0);
    $day  = $conn->real_escape_string($_POST['day_of_week']??'');
    $start= $conn->real_escape_string($_POST['start_time']??'');
    $end  = $conn->real_escape_string($_POST['end_time']??'');
    $cap  = min((int)($_POST['capacity']??10), 20);

    if ($trainer_id<=0||empty($day)||empty($start)||empty($end)) { $msg="All fields are required."; $msg_type='error'; }
    elseif ($cap<1) { $msg="Capacity must be at least 1."; $msg_type='error'; }
    else {
        if ($conn->query("INSERT INTO time_slots (trainer_id,day_of_week,start_time,end_time,capacity) VALUES ($trainer_id,'$day','$start','$end',$cap)")) {
            $msg="Time slot added."; $msg_type='success';
        } else { $msg="Error: ".htmlspecialchars($conn->error); $msg_type='error'; }
    }
}

if (isset($_GET['delete'])) {
    $id=(int)$_GET['delete'];
    if ($id>0&&$conn->query("DELETE FROM time_slots WHERE id=$id")) { $msg="Slot deleted."; $msg_type='success'; }
    else { $msg="Error deleting slot."; $msg_type='error'; }
}

$trainers = $conn->query("SELECT id,name FROM users WHERE role='trainer' ORDER BY name");
$slots    = $conn->query("SELECT t.*,u.name AS trainer_name FROM time_slots t JOIN users u ON t.trainer_id=u.id ORDER BY FIELD(t.day_of_week,'Mon','Tue','Wed','Thu','Fri','Sat','Sun'),t.start_time");
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8"><title>Manage Time Slots — GymPro</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="assets/style.css?v=1.3">
  <script src="assets/theme.js?v=2.0"></script>
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
  <div class="page-header"><h2>Manage <span class="accent">Time Slots</span></h2></div>

  <?php if ($msg): ?><div class="alert alert-<?php echo $msg_type==='success'?'success':'error'; ?>"><?php echo $msg_type==='success'?'✅':'⚠'; ?> <?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

  <div style="display:grid;grid-template-columns:380px 1fr;gap:24px;align-items:start;">

    <!-- Add Form -->
    <div class="form-card">
      <h3 style="margin-bottom:4px;">Add Time Slot</h3>
      <p class="subtitle">Max capacity is capped at 20 seats per slot.</p>
      <form method="post">
        <div class="form-group">
          <label>Trainer</label>
          <select name="trainer_id" required>
            <option value="">Select trainer</option>
            <?php while($t=$trainers->fetch_assoc()): ?>
              <option value="<?php echo (int)$t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Day</label>
          <select name="day_of_week" required>
            <?php foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $d): ?>
              <option><?php echo $d; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div class="form-group"><label>Start Time</label><input type="time" name="start_time" required></div>
          <div class="form-group"><label>End Time</label><input type="time" name="end_time" required></div>
        </div>
        <div class="form-group">
          <label>Capacity <span style="font-size:11px;color:var(--text-3);text-transform:none;">(max 20)</span></label>
          <input type="number" name="capacity" value="10" min="1" max="20" required>
        </div>
        <button class="btn" type="submit">Add Slot</button>
      </form>
    </div>

    <!-- Slots Table -->
    <div class="card">
      <h3 style="margin-bottom:16px;">All Time Slots</h3>
      <?php if ($slots && $slots->num_rows > 0): ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Day</th><th>Trainer</th><th>Start</th><th>End</th><th>Capacity</th><th>Action</th></tr></thead>
          <tbody>
          <?php while($s=$slots->fetch_assoc()): ?>
            <tr>
              <td><span class="badge badge-lime"><?php echo htmlspecialchars($s['day_of_week']); ?></span></td>
              <td style="font-weight:600;"><?php echo htmlspecialchars($s['trainer_name']); ?></td>
              <td><?php echo htmlspecialchars($s['start_time']); ?></td>
              <td><?php echo htmlspecialchars($s['end_time']); ?></td>
              <td><span class="badge badge-blue"><?php echo (int)min($s['capacity'],20); ?></span></td>
              <td><a class="btn btn-sm btn-danger" href="?delete=<?php echo (int)$s['id']; ?>" onclick="return confirm('Delete this slot?')">Delete</a></td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?><p style="color:var(--text-3);">No slots created yet.</p><?php endif; ?>
    </div>

  </div>
</div>

</body>
</html>