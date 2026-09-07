<?php
// manage_exercises.php
require 'config.php';
require 'auth.php';
require_login();

$role     = $_SESSION['role']??'';
$is_admin = ($role==='admin');
$user_id  = (int)($_SESSION['user_id']??0);

$back = 'member_dashboard.php';
if ($role === 'admin')   $back = 'admin_dashboard.php';
if ($role === 'trainer') $back = 'trainer_dashboard.php';

/* Ensure tables */
$conn->query("CREATE TABLE IF NOT EXISTS exercises (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL, body_part VARCHAR(100) DEFAULT NULL, difficulty VARCHAR(30) DEFAULT NULL, description TEXT DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
$conn->query("CREATE TABLE IF NOT EXISTS user_saved_exercises (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, exercise_id INT NOT NULL, saved_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uniq_user_ex (user_id,exercise_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

/* Seed */
$cnt=$conn->query("SELECT COUNT(*) c FROM exercises")->fetch_assoc()['c'];
if (!$cnt) {
    $samples=[['Push-up','Chest','Easy','Standard push-ups: 3 sets of 10–15 reps.'],['Squat','Legs','Easy','Bodyweight squats: 3×12–15. Sit back into heels.'],['Plank','Core','Easy','Hold 30–60 seconds. Core tight, hips level.'],['Lunges','Legs','Medium','Forward lunges: 3×10 per leg.'],['Bench Press','Chest','Medium','4×8–10 reps. Control the bar.'],['Deadlift','Back','Hard','4×5. Neutral spine, bar close to legs.'],['Pull-up','Back','Hard','3 sets to failure. Full ROM.'],['Shoulder Press','Shoulders','Medium','Dumbbell press: 3×8–12.'],['Bicycle Crunch','Core','Easy','3×20 reps. Focus on obliques.'],['Calf Raise','Legs','Easy','3×15–20. Pause at top.']];
    $ins=$conn->prepare("INSERT INTO exercises (name,body_part,difficulty,description) VALUES (?,?,?,?)");
    if ($ins) { foreach($samples as $s){ $ins->bind_param('ssss',$s[0],$s[1],$s[2],$s[3]); $ins->execute(); } $ins->close(); }
}

/* POST actions */
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $action=$_POST['action']??'';
    if ($action==='save_exercise'&&$user_id>0) {
        $eid=(int)($_POST['exercise_id']??0);
        if ($eid>0) { $pst=$conn->prepare("INSERT IGNORE INTO user_saved_exercises (user_id,exercise_id) VALUES (?,?)"); if($pst){ $pst->bind_param('ii',$user_id,$eid); $pst->execute(); $pst->close(); $_SESSION['success']="Exercise saved."; } }
        header('Location: '.$_SERVER['PHP_SELF']); exit;
    }
    if ($action==='add_exercise'&&$is_admin) {
        $n=trim($_POST['name']??''); $bp=trim($_POST['body_part']??''); $df=trim($_POST['difficulty']??''); $desc=trim($_POST['description']??'');
        if ($n==='') { $_SESSION['error']="Name is required."; }
        else { $pst=$conn->prepare("INSERT INTO exercises (name,body_part,difficulty,description) VALUES (?,?,?,?)"); if($pst){ $pst->bind_param('ssss',$n,$bp,$df,$desc); $pst->execute(); $pst->close(); $_SESSION['success']="Exercise added."; } }
        header('Location: '.$_SERVER['PHP_SELF']); exit;
    }
}
if ($is_admin&&isset($_GET['delete'])) {
    $del=(int)$_GET['delete'];
    if ($del>0) { $pst=$conn->prepare("DELETE FROM exercises WHERE id=? LIMIT 1"); if($pst){ $pst->bind_param('i',$del); $pst->execute(); $pst->close(); $_SESSION['success']="Exercise deleted."; } }
    header('Location: '.$_SERVER['PHP_SELF']); exit;
}

$ex_res=$conn->query("SELECT * FROM exercises ORDER BY body_part,difficulty,name");
$saved_ids=[];
if ($user_id>0) { $r=$conn->query("SELECT exercise_id FROM user_saved_exercises WHERE user_id=$user_id"); if($r) while($row=$r->fetch_assoc()) $saved_ids[]=(int)$row['exercise_id']; }

$success=$_SESSION['success']??''; unset($_SESSION['success']);
$error  =$_SESSION['error']??'';   unset($_SESSION['error']);

$diff_badges=['Easy'=>'badge-green','Medium'=>'badge-yellow','Hard'=>'badge-red'];
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8"><title>Exercises — GymPro</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="assets/style.css?v=1.3">
  <script src="assets/theme.js?v=2.0"></script>
  <style>
    .ex-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:16px; margin-top:4px; }
    .ex-card { background:var(--card); border:1px solid var(--border); border-radius:var(--radius-lg); padding:20px; transition:all 0.2s; }
    .ex-card:hover { border-color:var(--border-2); box-shadow:var(--shadow-md); }
    .ex-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px; }
    .ex-title { font-family:'Barlow Condensed',sans-serif; font-size:1.15rem; font-weight:700; }
    .ex-body { font-size:12px; color:var(--text-3); margin-top:4px; }
    .ex-desc { font-size:13px; color:var(--text-2); margin:10px 0; line-height:1.55; }
    .add-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:12px; }
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
    <h2><?php echo $is_admin?'Manage':'Browse'; ?> <span class="accent">Exercises</span></h2>
    <?php if (!$is_admin&&!empty($saved_ids)): ?>
      <span class="badge badge-lime"><?php echo count($saved_ids); ?> saved</span>
    <?php endif; ?>
  </div>

  <?php if ($success): ?><div class="alert alert-success">✅ <?php echo htmlspecialchars($success); ?></div><?php endif; ?>
  <?php if ($error):   ?><div class="alert alert-error">⚠ <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

  <!-- Exercise cards -->
  <div class="ex-grid">
  <?php if ($ex_res&&$ex_res->num_rows): while($e=$ex_res->fetch_assoc()):
    $isSaved=in_array((int)$e['id'],$saved_ids); $dc=$diff_badges[$e['difficulty']]??'badge-gray';
  ?>
    <div class="ex-card">
      <div class="ex-header">
        <div>
          <div class="ex-title"><?php echo htmlspecialchars($e['name']); ?></div>
          <div class="ex-body">💪 <?php echo htmlspecialchars($e['body_part']??'—'); ?></div>
        </div>
        <span class="badge <?php echo $dc; ?>"><?php echo htmlspecialchars($e['difficulty']??'—'); ?></span>
      </div>
      <p class="ex-desc"><?php echo nl2br(htmlspecialchars($e['description']??'')); ?></p>
      <div style="margin-top:12px;">
        <?php if ($is_admin): ?>
          <a class="btn btn-sm btn-danger" href="?delete=<?php echo (int)$e['id']; ?>" onclick="return confirm('Delete this exercise?')">Delete</a>
        <?php else: ?>
          <?php if ($isSaved): ?>
            <span class="badge badge-lime">✓ Saved</span>
          <?php else: ?>
            <form method="post" style="display:inline;">
              <input type="hidden" name="action" value="save_exercise">
              <input type="hidden" name="exercise_id" value="<?php echo (int)$e['id']; ?>">
              <button class="btn btn-sm" type="submit">+ Save</button>
            </form>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  <?php endwhile; else: ?><p style="color:var(--text-3);">No exercises found.</p><?php endif; ?>
  </div>

  <!-- Admin Add Form -->
  <?php if ($is_admin): ?>
  <div class="card" style="margin-top:32px;">
    <h3 style="margin-bottom:20px;">Add New Exercise</h3>
    <form method="post">
      <input type="hidden" name="action" value="add_exercise">
      <div class="add-grid">
        <div class="form-group"><label>Exercise Name *</label><input type="text" name="name" required placeholder="e.g., Barbell Row"></div>
        <div class="form-group"><label>Body Part</label><input type="text" name="body_part" placeholder="e.g., Back, Chest, Legs"></div>
        <div class="form-group">
          <label>Difficulty</label>
          <select name="difficulty"><option value="">Select</option><option>Easy</option><option>Medium</option><option>Hard</option></select>
        </div>
      </div>
      <div class="form-group"><label>Description</label><textarea name="description" rows="3" placeholder="Sets, reps, tips..."></textarea></div>
      <button class="btn" type="submit">Add Exercise</button>
    </form>
  </div>
  <?php endif; ?>

</div>

</body>
</html>