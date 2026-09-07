<?php
// assign_edit.php
require 'config.php';
require 'auth.php';
require_login();
require_role('trainer');

$trainer_id = (int)($_SESSION['user_id'] ?? 0);
$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);

if ($id <= 0) { $_SESSION['error']="Invalid assignment."; header('Location: trainer_dashboard.php'); exit; }

$pst=$conn->prepare("SELECT id,user_id,plan_type,COALESCE(plan_text,'') AS plan_text,COALESCE(custom_notes,'') AS custom_notes,COALESCE(status,'assigned') AS status FROM user_plan_assignments WHERE id=? AND trainer_id=? LIMIT 1");
if (!$pst) { $_SESSION['error']="DB error."; header('Location: trainer_dashboard.php'); exit; }
$pst->bind_param('ii',$id,$trainer_id); $pst->execute(); $res=$pst->get_result();
if (!$res||$res->num_rows===0) { $pst->close(); $_SESSION['error']="Assignment not found."; header('Location: trainer_dashboard.php'); exit; }
$row=$res->fetch_assoc(); $pst->close();

$error = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $new_text=trim($_POST['plan_text']??''); $new_status=trim($_POST['status']??'');
    if ($new_text==='') { $error="Description cannot be empty."; }
    else {
        $col=$conn->query("SHOW COLUMNS FROM user_plan_assignments LIKE 'plan_text'");
        $useCol=$col&&$col->num_rows?'plan_text':'custom_notes';
        $upd=$conn->prepare("UPDATE user_plan_assignments SET `$useCol`=?,status=? WHERE id=? AND trainer_id=? LIMIT 1");
        if ($upd){ $upd->bind_param('ssii',$new_text,$new_status,$id,$trainer_id); $ok=$upd->execute(); $upd->close();
            if ($ok){ $_SESSION['success']="Assignment updated."; header('Location: trainer_dashboard.php'); exit; }
            else $error="Update failed: ".$conn->error;
        } else $error="DB error.";
    }
}

$plan_text_value = $row['plan_text']?:$row['custom_notes'];
$status_value    = $row['status']??'assigned';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><title>Edit Assignment — GymPro</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<nav class="navbar">
  <div class="navbar-brand">🏋️ GYM<span>PRO</span></div>
  <div class="navbar-links">
    <a href="trainer_dashboard.php">← Dashboard</a>
    <a href="logout.php" class="nav-logout">Logout</a>
  </div>
</nav>
<div class="container">
  <div class="page-header">
    <h2>Edit <span class="accent">Assignment</span></h2>
    <span class="badge badge-<?php echo $row['plan_type']==='diet'?'green':'blue'; ?>">
      <?php echo htmlspecialchars(ucfirst($row['plan_type'])); ?> Plan
    </span>
  </div>
  <?php if ($error): ?><div class="alert alert-error" style="max-width:580px;">⚠ <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

  <div class="form-card" style="max-width:620px;">
    <h3 style="margin-bottom:4px;">Editing <?php echo htmlspecialchars(ucfirst($row['plan_type'])); ?> for Member ID <?php echo (int)$row['user_id']; ?></h3>
    <p class="subtitle">Update the plan description and status.</p>
    <form method="post">
      <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
      <div class="form-group">
        <label>Plan Description *</label>
        <textarea name="plan_text" rows="7" required><?php echo htmlspecialchars($plan_text_value); ?></textarea>
      </div>
      <div class="form-group">
        <label>Status</label>
        <select name="status">
          <?php foreach(['assigned','approved','completed','cancelled','rejected'] as $s): ?>
            <option value="<?php echo $s; ?>" <?php if($s===$status_value) echo 'selected'; ?>><?php echo ucfirst($s); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-actions">
        <button class="btn" type="submit">Save Changes</button>
        <a href="trainer_dashboard.php" class="btn btn-ghost">Cancel</a>
      </div>
    </form>
  </div>
</div>
<script src="assets/theme.js"></script>
</body>
</html>